<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_config.php';
require_once dirname(__DIR__) . '/config/app.php';

$_SESSION['last_activity'] = time();
if (isLoggedIn()) {
    setcookie('user_session', getCurrentUser()['email'], time() + 86400 * 7, '/');
}
if (!isAdmin()) {
    header('Location: ../auth/login.php');
    exit();
}

$currentUser = getCurrentUser();
$currentPage = 'bookings';

/* ── Reference code helper ──────────────────────────────────── */
function refCode(int $bookingId): string {
    return 'TGP-' . strtoupper(substr(md5('tagpo_' . $bookingId), 0, 8));
}

function statusBadgeClass(string $status): string {
    return match($status) {
        'confirmed' => 'badge-confirmed',
        'pending'   => 'badge-pending',
        'cancelled' => 'badge-cancelled',
        'paid'      => 'badge-paid',
        'failed'    => 'badge-failed',
        'refunded'  => 'badge-refunded',
        default     => 'badge-inactive',
    };
}

/* ── Handle drawer actions (Approve / Reject / Confirm) ─────────── */
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['booking_id'])) {
    $bookingId = (int) $_POST['booking_id'];
    $action    = $_POST['action'];

    // Get the cart_id linked to this booking
    $stmt = $conn->prepare("SELECT cart_id FROM bookings WHERE booking_id = ?");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $cartId = (int) $row['cart_id'];

        if ($action === 'approve_payment') {
            $stmt = $conn->prepare("UPDATE payments SET payment_status = 'paid' WHERE cart_id = ?");
            $stmt->bind_param('i', $cartId);
            $stmt->execute();
            $stmt->close();
            $flash = 'Payment approved successfully.';
        } elseif ($action === 'reject_payment') {
            $stmt = $conn->prepare("UPDATE payments SET payment_status = 'failed' WHERE cart_id = ?");
            $stmt->bind_param('i', $cartId);
            $stmt->execute();
            $stmt->close();
            $flash = 'Payment rejected.';
        } elseif ($action === 'confirm_booking') {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE booking_id = ?");
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $stmt->close();
            $flash = 'Booking confirmed.';
        } elseif ($action === 'cancel_booking') {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?");
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $stmt->close();
            $flash = 'Booking cancelled.';
        }
    }
}

/* ── View / filters ──────────────────────────────────────────── */
$view   = $_GET['view']   ?? 'table';
$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';

/* ── Stats ───────────────────────────────────────────────────── */
$totalBookings = (int) ($conn->query("SELECT COUNT(*) AS c FROM bookings")->fetch_assoc()['c'] ?? 0);
$pendingPay    = (int) ($conn->query("SELECT COUNT(*) AS c FROM payments WHERE payment_status='pending'")->fetch_assoc()['c'] ?? 0);
$todaySessions = (int) ($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE event_date = CURDATE()")->fetch_assoc()['c'] ?? 0);

/* ── Fetch bookings ──────────────────────────────────────────── */
$sql = "
    SELECT b.booking_id, b.cart_id, b.event_date, b.event_time, b.duration, b.guest_count,
           b.total_price, b.status AS booking_status,
           u.first_name, u.last_name, u.email,
           v.name AS venue_name,
           e.event_name,
           p.payment_id, p.amount AS payment_amount, p.payment_method, p.payment_status, p.transaction_id
    FROM bookings b
    JOIN users  u ON b.user_id  = u.id
    JOIN venues v ON b.venue_id = v.id
    JOIN event  e ON b.event_id = e.event_id
    LEFT JOIN payments p ON p.cart_id = b.cart_id
    WHERE 1=1
";
$params = [];
$types  = '';

if ($search !== '') {
    // If search looks like a reference code (starts with TGP-), search by ref code
    if (stripos($search, 'TGP-') === 0) {
        // Get all booking IDs and find matching reference codes
        $allBookings = $conn->query("SELECT booking_id FROM bookings")->fetch_all(MYSQLI_ASSOC);
        $matchingIds = [];
        
        foreach ($allBookings as $b) {
            $refCode = refCode($b['booking_id']);
            if (stripos($refCode, $search) !== false) {
                $matchingIds[] = $b['booking_id'];
            }
        }
        
        if (!empty($matchingIds)) {
            $placeholders = implode(',', array_fill(0, count($matchingIds), '?'));
            $sql .= " AND b.booking_id IN ($placeholders)";
            $types .= str_repeat('i', count($matchingIds));
            $params = array_merge($params, $matchingIds);
        } else {
            // No matching reference codes, return empty results
            $sql .= " AND 0=1";
        }
    } else {
        // Regular search by booking ID, name, email, venue
        $sql .= " AND (CAST(b.booking_id AS CHAR) LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR v.name LIKE ?)";
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like, $like);
        $types .= 'sssss';
    }
}
if ($statusFilter !== '') {
    $sql .= " AND b.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}
$sql .= " ORDER BY b.booking_id DESC";

$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Addons per booking ──────────────────────────────────────── */
$bookingIds = array_column($bookings, 'booking_id');
$addonsByBooking = [];
if (!empty($bookingIds)) {
    $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
    $types2 = str_repeat('i', count($bookingIds));
    $stmt = $conn->prepare("
        SELECT ba.booking_id, a.addon_name, ba.quantity, ba.unit_price
        FROM booking_addons ba
        JOIN addons a ON ba.addon_id = a.addon_id
        WHERE ba.booking_id IN ($placeholders)
    ");
    $stmt->bind_param($types2, ...$bookingIds);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $addonsByBooking[$row['booking_id']][] = $row;
    }
    $stmt->close();
}

/* ── Calendar data (current week, navigable) ──────────────────── */
$weekOffset = isset($_GET['week']) ? (int) $_GET['week'] : 0;
$weekStart  = (new DateTime('monday this week'))->modify(($weekOffset * 7) . ' days');
$weekDays   = [];
for ($i = 0; $i < 7; $i++) {
    $weekDays[] = (clone $weekStart)->modify("+$i days");
}
$weekEnd = (clone $weekStart)->modify('+6 days');

$venues = $conn->query("SELECT id, name, capacity, image_url FROM venues WHERE archived = 0 ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("
    SELECT b.booking_id, b.venue_id, b.event_date, b.event_time, b.duration,
           u.first_name, u.last_name, e.event_name, b.status
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN event e ON b.event_id = e.event_id
    WHERE b.event_date BETWEEN ? AND ? AND b.status != 'cancelled'
");
$ws = $weekStart->format('Y-m-d');
$we = $weekEnd->format('Y-m-d');
$stmt->bind_param('ss', $ws, $we);
$stmt->execute();
$calBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Index calendar bookings by venue + date + hour
$calIndex = [];
foreach ($calBookings as $cb) {
    $hour = (int) substr($cb['event_time'], 0, 2);
    $key  = $cb['venue_id'] . '_' . $cb['event_date'] . '_' . $hour;
    $calIndex[$key][] = $cb;
}

$calHours = range(8, 20); // 8 AM - 8 PM
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bookings | Tagpo Admin</title>
  <?php include 'includes/admin_style.php'; ?>
</head>
<body>

<?php include 'includes/admin_sidebar.php'; ?>

<div class="admin-main">

  <header class="admin-topbar">
    <div class="topbar-title">Bookings</div>
    <div class="topbar-right">
      <div class="topbar-date"><i class="bi bi-calendar3"></i> <?= date('M j, Y (D)') ?></div>
      <div class="topbar-avatar"><?= htmlspecialchars(strtoupper(substr($currentUser['first_name'] ?? 'A', 0, 1))) ?></div>
    </div>
  </header>

  <div class="admin-content">

    <div class="page-header d-flex justify-between" style="align-items:flex-end;">
      <div>
        <h1>Bookings</h1>
        <p>Manage court reservations, lessons, social play, and private sessions.</p>
      </div>
      <div class="view-tabs">
        <a class="view-tab <?= $view==='table' ? 'active' : '' ?>" href="?view=table" style="text-decoration:none;display:inline-block;">Table View</a>
        <a class="view-tab <?= $view==='calendar' ? 'active' : '' ?>" href="?view=calendar" style="text-decoration:none;display:inline-block;">Calendar View</a>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert-bar success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <!-- Stat cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="stat-card">
          <div class="stat-icon green"><i class="bi bi-calendar-check"></i></div>
          <div>
            <div class="stat-label">Total Bookings</div>
            <div class="stat-value"><?= $totalBookings ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card">
          <div class="stat-icon orange"><i class="bi bi-cash-coin"></i></div>
          <div>
            <div class="stat-label">Pending Payments</div>
            <div class="stat-value"><?= $pendingPay ?></div>
            <div class="stat-sub">Need review</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="bi bi-clock-history"></i></div>
          <div>
            <div class="stat-label">Today's Sessions</div>
            <div class="stat-value"><?= $todaySessions ?></div>
            <div class="stat-sub">Across all venues</div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($view === 'calendar'): ?>

      <!-- ════════════ CALENDAR VIEW ════════════ -->
      <div class="panel-card">
        <div class="panel-card-header">
          <h2>Booking Timeline</h2>
          <div class="d-flex gap-8">
            <a class="btn-action btn-outline-gray" href="?view=calendar&week=<?= $weekOffset - 1 ?>"><i class="bi bi-chevron-left"></i></a>
            <span class="text-muted-sm" style="align-self:center;">
              <?= $weekStart->format('M j') ?> &ndash; <?= $weekEnd->format('M j, Y') ?>
            </span>
            <a class="btn-action btn-outline-gray" href="?view=calendar&week=<?= $weekOffset + 1 ?>"><i class="bi bi-chevron-right"></i></a>
          </div>
        </div>

        <div class="cal-grid">
          <div class="cal-venue-col">
            <div class="cal-venue-cell" style="border-bottom:1px solid var(--border); height:42px; background:var(--bg);"></div>
            <?php foreach ($venues as $v): ?>
              <div class="cal-venue-cell" style="height:<?= count($calHours) * 52 ?>px;">
                <?php if ($v['image_url']): ?>
                  <img src="../<?= htmlspecialchars($v['image_url']) ?>" class="cal-venue-img" alt="">
                <?php endif; ?>
                <div class="cal-venue-name"><?= htmlspecialchars($v['name']) ?></div>
                <div class="cal-venue-cap">Capacity: <?= (int)$v['capacity'] ?></div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="cal-timeline">
            <div class="cal-week-header">
              <div class="cal-day-head">Time</div>
              <?php foreach ($weekDays as $d): ?>
                <div class="cal-day-head <?= $d->format('Y-m-d') === date('Y-m-d') ? 'today' : '' ?>">
                  <?= strtoupper($d->format('D')) ?><br><?= $d->format('M j') ?>
                </div>
              <?php endforeach; ?>
            </div>

            <?php foreach ($venues as $v): ?>
              <div class="cal-rows">
                <?php foreach ($calHours as $hour): ?>
                  <div class="cal-time-row">
                    <div class="cal-time-label"><?= date('g A', mktime($hour)) ?></div>
                    <?php foreach ($weekDays as $d):
                      $dateStr = $d->format('Y-m-d');
                      $key = $v['id'] . '_' . $dateStr . '_' . $hour;
                      $items = $calIndex[$key] ?? [];
                    ?>
                      <div class="cal-time-cell <?= $dateStr === date('Y-m-d') ? 'today-col' : '' ?>">
                        <?php foreach ($items as $it): ?>
                          <span class="cal-booking-chip" title="<?= htmlspecialchars($it['event_name']) ?>"
                                onclick="window.location='?view=table&q=<?= urlencode($it['first_name']) ?>'">
                            <?= htmlspecialchars($it['first_name'] . ' - ' . $it['event_name']) ?>
                          </span>
                        <?php endforeach; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    <?php else: ?>

      <!-- ════════════ TABLE VIEW ════════════ -->
      <div class="panel-card">
        <div class="panel-card-header">
          <h2>All Bookings</h2>
        </div>
        <div class="filter-toolbar">
          <form method="get" class="search-box" style="flex:1;">
            <input type="hidden" name="view" value="table">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by reference, user, or venue...">
            <button type="submit" style="display:none;"></button>
          </form>
          <form method="get" class="d-flex gap-8">
            <input type="hidden" name="view" value="table">
            <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
            <select name="status" class="filter-select" onchange="this.form.submit()">
              <option value="">All Statuses</option>
              <option value="pending"   <?= $statusFilter==='pending'   ? 'selected' : '' ?>>Pending</option>
              <option value="confirmed" <?= $statusFilter==='confirmed' ? 'selected' : '' ?>>Confirmed</option>
              <option value="cancelled" <?= $statusFilter==='cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
          </form>
        </div>

        <?php if (empty($bookings)): ?>
          <div class="panel-card-body text-muted-sm">No bookings found.</div>
        <?php else: ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Reference</th>
                <th>Client</th>
                <th>Venue</th>
                <th>Date / Time</th>
                <th>Status</th>
                <th>Payment</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $b):
                $addons = $addonsByBooking[$b['booking_id']] ?? [];
                $addonsTotal = 0;
                foreach ($addons as $a) $addonsTotal += $a['unit_price'] * $a['quantity'];

                $addonsHtml = '';
                if (empty($addons)) {
                    $addonsHtml = '<div class="text-muted-sm">No add-ons selected.</div>';
                } else {
                    foreach ($addons as $a) {
                        $addonsHtml .= '<div class="info-row"><div class="info-label">'
                            . htmlspecialchars($a['addon_name']) . ' &times; ' . (int)$a['quantity'] . '</div>'
                            . '<div class="info-value">&#8369;' . number_format($a['unit_price'] * $a['quantity'], 2) . '</div></div>';
                    }
                }
              ?>
                <tr class="booking-row"
                    data-id="<?= (int)$b['booking_id'] ?>"
                    data-ref="<?= refCode($b['booking_id']) ?>"
                    data-date="<?= htmlspecialchars(date('l, F j, Y', strtotime($b['event_date']))) ?>"
                    data-time="<?= htmlspecialchars(date('g:i A', strtotime($b['event_time'])) . ' - ' . date('g:i A', strtotime($b['event_time'] . ' + ' . $b['duration'] . ' hours'))) ?>"
                    data-event="<?= htmlspecialchars($b['event_name']) ?>"
                    data-venue="<?= htmlspecialchars($b['venue_name']) ?>"
                    data-guests="<?= (int)$b['guest_count'] ?>"
                    data-name="<?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name'], ENT_QUOTES) ?>"
                    data-email="<?= htmlspecialchars($b['email'], ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($b['booking_status']) ?>"
                    data-status-label="<?= htmlspecialchars(ucfirst($b['booking_status'])) ?>"
                    data-status-class="<?= statusBadgeClass($b['booking_status']) ?>"
                    data-pay-status="<?= htmlspecialchars($b['payment_status'] ?? 'none') ?>"
                    data-pay-status-label="<?= htmlspecialchars($b['payment_status'] ? ucfirst($b['payment_status']) : 'No payment record') ?>"
                    data-pay-status-class="<?= statusBadgeClass($b['payment_status'] ?? '') ?>"
                    data-pay-method="<?= htmlspecialchars($b['payment_method'] ? ucwords(str_replace('_',' ',$b['payment_method'])) : 'N/A') ?>"
                    data-txid="<?= htmlspecialchars($b['transaction_id'] ?? 'N/A') ?>"
                    data-venue-price="<?= number_format($b['total_price'] - $addonsTotal, 2) ?>"
                    data-addons-total="<?= number_format($addonsTotal, 2) ?>"
                    data-total="<?= number_format($b['total_price'], 2) ?>"
                    data-addons-html="<?= htmlspecialchars($addonsHtml, ENT_QUOTES) ?>">
                  <td class="fw-600"><?= refCode($b['booking_id']) ?></td>
                  <td>
                    <div class="user-name-block">
                      <div class="name"><?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?></div>
                      <div class="email"><?= htmlspecialchars($b['email']) ?></div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($b['venue_name']) ?></td>
                  <td>
                    <?= htmlspecialchars(date('M j, Y', strtotime($b['event_date']))) ?>
                    <div class="text-muted-sm"><?= htmlspecialchars(date('g:i A', strtotime($b['event_time']))) ?></div>
                  </td>
                  <td><span class="badge-status <?= statusBadgeClass($b['booking_status']) ?>"><?= htmlspecialchars(ucfirst($b['booking_status'])) ?></span></td>
                  <td>
                    <?php if ($b['payment_status']): ?>
                      <span class="badge-status <?= statusBadgeClass($b['payment_status']) ?>"><?= htmlspecialchars(ucfirst($b['payment_status'])) ?></span>
                    <?php else: ?>
                      <span class="badge-status badge-inactive">No record</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <div class="pagination-bar">
          <span>Showing <?= count($bookings) ?> of <?= $totalBookings ?> bookings</span>
        </div>
      </div>

    <?php endif; ?>

  </div>
</div>

<!-- Side Drawer -->
<div class="side-drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="side-drawer" id="bookingDrawer">
  <div class="drawer-header">
    <div>
      <h3>Booking Details</h3>
      <p id="drawerRef"></p>
    </div>
    <button class="drawer-close" onclick="closeDrawer()"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="drawer-body">

    <div class="drawer-section">
      <div class="drawer-section-title">Booking Information</div>
      <div class="info-row"><div class="info-label">Date</div><div class="info-value" id="drawerDate"></div></div>
      <div class="info-row"><div class="info-label">Time</div><div class="info-value" id="drawerTime"></div></div>
      <div class="info-row"><div class="info-label">Event Type</div><div class="info-value" id="drawerEvent"></div></div>
      <div class="info-row"><div class="info-label">Venue</div><div class="info-value" id="drawerVenue"></div></div>
      <div class="info-row"><div class="info-label">Guests</div><div class="info-value" id="drawerGuests"></div></div>
      <div class="info-row"><div class="info-label">Booking Status</div><div class="info-value" id="drawerStatus"></div></div>
    </div>

    <div class="drawer-section">
      <div class="drawer-section-title">Customer Information</div>
      <div class="info-row"><div class="info-label">Name</div><div class="info-value" id="drawerName"></div></div>
      <div class="info-row"><div class="info-label">Email</div><div class="info-value" id="drawerEmail"></div></div>
    </div>

    <div class="drawer-section">
      <div class="drawer-section-title">Add-ons</div>
      <div id="drawerAddons"></div>
    </div>

    <div class="drawer-section">
      <div class="drawer-section-title">Payment Information</div>
      <div class="info-row"><div class="info-label">Venue / Base Price</div><div class="info-value">&#8369;<span id="drawerVenuePrice"></span></div></div>
      <div class="info-row"><div class="info-label">Add-ons Total</div><div class="info-value">&#8369;<span id="drawerAddonsTotal"></span></div></div>
      <div class="info-row"><div class="info-label fw-600">Grand Total</div><div class="info-value fw-600">&#8369;<span id="drawerTotal"></span></div></div>
      <div class="info-row"><div class="info-label">Method</div><div class="info-value" id="drawerMethod"></div></div>
      <div class="info-row"><div class="info-label">Transaction ID</div><div class="info-value" id="drawerTxId"></div></div>
      <div class="info-row"><div class="info-label">Payment Status</div><div class="info-value" id="drawerPayStatus"></div></div>
    </div>
  </div>

  <div class="drawer-footer">
    <form method="post" id="drawerForm">
      <input type="hidden" name="booking_id" id="formBookingId">
      <input type="hidden" name="action" id="formAction">
    </form>
    <button class="btn-full btn-full-green" id="btnApprove" onclick="submitAction('approve_payment')">
      <i class="bi bi-check-circle me-1"></i> Approve Payment
    </button>
    <button class="btn-full btn-full-red" id="btnReject" onclick="submitAction('reject_payment')">
      <i class="bi bi-x-circle me-1"></i> Reject Payment
    </button>
    <button class="btn-full btn-full-outline" id="btnConfirm" onclick="submitAction('confirm_booking')">
      <i class="bi bi-patch-check me-1"></i> Confirm Booking
    </button>
  </div>
</div>

<script>
function closeDrawer() {
  document.getElementById('bookingDrawer').classList.remove('open');
  document.getElementById('drawerOverlay').classList.remove('open');
}
function submitAction(action) {
  document.getElementById('formAction').value = action;
  document.getElementById('drawerForm').submit();
}
function openDrawer(row) {
  const d = row.dataset;
  document.getElementById('drawerRef').textContent     = d.ref;
  document.getElementById('drawerDate').textContent    = d.date;
  document.getElementById('drawerTime').textContent    = d.time;
  document.getElementById('drawerEvent').textContent   = d.event;
  document.getElementById('drawerVenue').textContent   = d.venue;
  document.getElementById('drawerGuests').textContent  = d.guests;
  document.getElementById('drawerStatus').innerHTML    = '<span class="badge-status ' + d.statusClass + '">' + d.statusLabel + '</span>';
  document.getElementById('drawerName').textContent    = d.name;
  document.getElementById('drawerEmail').textContent   = d.email;
  document.getElementById('drawerAddons').innerHTML    = d.addonsHtml;
  document.getElementById('drawerVenuePrice').textContent  = d.venuePrice;
  document.getElementById('drawerAddonsTotal').textContent = d.addonsTotal;
  document.getElementById('drawerTotal').textContent       = d.total;
  document.getElementById('drawerMethod').textContent      = d.payMethod;
  document.getElementById('drawerTxId').textContent        = d.txid;
  document.getElementById('drawerPayStatus').innerHTML = '<span class="badge-status ' + d.payStatusClass + '">' + d.payStatusLabel + '</span>';

  document.getElementById('formBookingId').value = d.id;

  // Toggle action buttons depending on current state
  document.getElementById('btnApprove').style.display = (d.payStatus === 'pending') ? '' : 'none';
  document.getElementById('btnReject').style.display  = (d.payStatus === 'pending') ? '' : 'none';
  document.getElementById('btnConfirm').style.display = (d.status === 'pending') ? '' : 'none';

  document.getElementById('bookingDrawer').classList.add('open');
  document.getElementById('drawerOverlay').classList.add('open');
}
document.querySelectorAll('.booking-row').forEach(row => {
  row.addEventListener('click', () => openDrawer(row));
});

<?php if (isset($_GET['booking'])): ?>
window.addEventListener('DOMContentLoaded', () => {
  const row = document.querySelector('.booking-row[data-id="<?= (int)$_GET['booking'] ?>"]');
  if (row) openDrawer(row);
});
<?php endif; ?>
</script>

</body>
</html>