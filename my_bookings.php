<?php
require_once 'config/database.php';
require_once 'config/session_config.php';
require_once 'config/app.php';

$_SESSION['last_activity'] = time();

if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

$user   = getCurrentUser();
$userId = (int) $user['id'];

// Refresh cookie
setcookie('user_session', $user['email'], time() + 86400 * 7, '/');

/* ── Active URL View Filters ────────────────────────────────────────────────── */
$view = $_GET['view'] ?? 'table';

// Status badge helper (Premium Style natin para hindi mukhang basa)
if (!function_exists('statusBadge')) {
    function statusBadge(string $status): string {
      $status = strtolower(trim($status));
      $style = match($status) {
        'confirmed' => 'background-color: #e6f6ec; color: #15803d; border: 1px solid #bbf7d0;',
        'paid'      => 'background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;',
        'pending'   => 'background-color: #fef9c3; color: #a16207; border: 1px solid #fef08a;',
        'cancelled' => 'background-color: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2;',
        'refunded'  => 'background-color: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff;',
        'failed'    => 'background-color: #fff5f5; color: #c53030; border: 1px solid #feb2b2;',
        default     => 'background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb;',
      };
      $labelText = match($status) {
        'pending'   => 'Pending Approval',
        default     => ucfirst($status),
      };
      return '<span class="badge rounded-pill fw-semibold px-3 py-2" style="font-size: 0.78rem; letter-spacing: 0.3px; ' . $style . '">' . htmlspecialchars($labelText) . '</span>';
    }
}

// Helper para sa uniform coding conventions ng unique Reference Codes
function getDisplayBookingCode($row): string {
    return !empty($row['booking_code']) ? $row['booking_code'] : 'BKN-' . str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT);
}

// ── Fetch all bookings for this user (Para sa Table View) ────────────────────
$sql = "
    SELECT
        b.booking_id, b.booking_code, b.event_date, b.event_time, b.duration, b.guest_count, b.total_price,
        b.status        AS booking_status,
        v.name          AS venue_name,
        v.location      AS venue_location,
        v.image_url     AS venue_image,
        e.event_name,
        c.status        AS cart_status,
        p.payment_status, p.payment_method, p.transaction_id, p.payment_date
    FROM bookings b
    JOIN venues   v ON b.venue_id  = v.id
    JOIN event    e ON b.event_id  = e.event_id
    JOIN carts    c ON b.cart_id   = c.cart_id
    LEFT JOIN payments p ON p.cart_id = c.cart_id
    WHERE b.user_id = ?
    ORDER BY b.booking_id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Fetch addons per booking ──────────────────────────────────────────────────
$bookingIds = array_column($bookings, 'booking_id');
$addonsByBooking = [];

if (!empty($bookingIds)) {
    $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
    $types = str_repeat('i', count($bookingIds));
    $stmt = $conn->prepare(
        "SELECT ba.booking_id, a.addon_name, ba.unit_price
         FROM booking_addons ba
         JOIN addons a ON ba.addon_id = a.addon_id
         WHERE ba.booking_id IN ($placeholders)"
    );
    $stmt->bind_param($types, ...$bookingIds);
    $stmt->execute();
    $addonRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($addonRows as $row) {
        $addonsByBooking[$row['booking_id']][] = $row;
    }
}

// ── FETCH CALENDAR DATA (Strictly filtered para sa logged-in customer ra!) ──
$weekOffset = isset($_GET['week']) ? (int) $_GET['week'] : 0;
$weekStart  = (new DateTime('monday this week'))->modify(($weekOffset * 7) . ' days');
$weekDays   = [];
for ($i = 0; $i < 7; $i++) {
    $weekDays[] = (clone $weekStart)->modify("+$i days");
}
$weekEnd = (clone $weekStart)->modify('+6 days');

// Kumuha ng active venues para sa rendering grid
$venues = $conn->query("SELECT id, name, capacity, image_url FROM venues WHERE archived = 0 ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("
    SELECT b.booking_id, b.booking_code, b.venue_id, b.event_date, b.event_time, b.duration,
           e.event_name, b.status
    FROM bookings b
    JOIN event e ON b.event_id = e.event_id
    WHERE b.user_id = ? 
      AND b.event_date BETWEEN ? AND ? 
      AND b.status != 'cancelled'
");
$ws = $weekStart->format('Y-m-d');
$we = $weekEnd->format('Y-m-d');
$stmt->bind_param('iss', $userId, $ws, $we);
$stmt->execute();
$calBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$calIndex = [];
foreach ($calBookings as $cb) {
    $hour = (int) substr($cb['event_time'], 0, 2);
    $key  = $cb['venue_id'] . '_' . $cb['event_date'] . '_' . $hour;
    $calIndex[$key][] = $cb;
}

$calHours = range(8, 20); // 8 AM to 8 PM timeline setup
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Bookings | TAGPO</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"/>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/admin_style.css"/> 
  <style>
    .booking-card {
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      background: #fff;
      overflow: hidden;
      transition: box-shadow var(--transition);
    }
    .booking-card:hover { box-shadow: var(--shadow); }
    .booking-venue-thumb { width: 90px; height: 90px; object-fit: cover; border-radius: var(--radius); flex-shrink: 0; background: var(--bg); }
    .meta-pill { display: inline-flex; align-items: center; gap: 5px; background: var(--bg); border: 1px solid var(--border); border-radius: 20px; padding: 3px 10px; font-size: 0.78rem; color: var(--muted); font-weight: 500; }
    .addon-chip { display: inline-block; background: #f0f4ff; color: #1a56db; border-radius: 20px; padding: 2px 10px; font-size: 0.75rem; font-weight: 500; margin: 2px 2px 2px 0; }
    .booking-total { font-family: 'Playfair Display', serif; font-size: 1.3rem; font-weight: 700; color: var(--deep); }
    .empty-state { text-align: center; padding: 80px 20px; }
    .empty-state .empty-icon { font-size: 4rem; opacity: .25; margin-bottom: 16px; }
    .section-divider { height: 1px; background: var(--border); margin: 12px 0; }
    .payment-info-row { font-size: 0.82rem; color: var(--muted); }
    .payment-info-row strong { color: var(--deep); }

    /* Custom Toggle Switch for Views */
    .view-tabs { display: inline-flex; background: #eef2f6; padding: 4px; border-radius: 30px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
    .view-tab { padding: 8px 20px; border-radius: 30px; font-size: 0.85rem; font-weight: 600; color: #64748b; transition: all 0.2s; text-decoration: none; }
    .view-tab.active { background: #fff; color: #0f172a; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
    .view-tab:hover { color: #0f172a; }

    /* ════════════════ CALENDAR SYSTEM LUXE CSS ════════════════ */
    .calendar-wrapper {
      background: #ffffff;
      border-radius: 16px;
      border: 1px solid #e2e8f0;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
    }
    .calendar-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }
    .calendar-table th, .calendar-table td {
      border: 1px solid #f1f5f9;
      padding: 12px;
      vertical-align: top;
    }
    /* Header Days styling */
    .calendar-th-day {
      background: #f8fafc;
      text-align: center;
      font-weight: 600;
      font-size: 0.85rem;
      color: #475569;
      border-bottom: 2px solid #e2e8f0 !important;
      padding: 15px 10px !important;
    }
    .calendar-th-day.today-highlight {
      background: #f0f7ff;
      color: #1e40af;
    }
    /* Venue Identity column */
    .venue-cell-info {
      background: #f8fafc;
      font-weight: 600;
      color: #1e293b;
      font-size: 0.9rem;
      border-right: 2px solid #e2e8f0 !important;
    }
    .venue-sub-cap {
      font-size: 0.75rem;
      color: #94a3b8;
      font-weight: 400;
      margin-top: 2px;
    }
    /* Day Grid dynamic slots */
    .day-slot-container {
      min-height: 120px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    /* Premium Minimalist Booking Chips */
    .premium-cal-chip {
      background: #eff6ff;
      color: #1d4ed8;
      border: 1px solid #bfdbfe;
      padding: 6px 10px;
      border-radius: 8px;
      font-size: 0.75rem;
      font-weight: 600;
      display: flex;
      flex-direction: column;
      gap: 2px;
      transition: all 0.2s ease;
      cursor: pointer;
      box-shadow: 0 1px 3px rgba(29, 78, 216, 0.04);
    }
    .premium-cal-chip:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(29, 78, 216, 0.12);
      background: #1d4ed8;
      color: #ffffff;
      border-color: #1d4ed8;
    }
    .chip-time {
      font-size: 0.68rem;
      opacity: 0.8;
      font-weight: 500;
    }
    .chip-title {
      font-family: 'DM Sans', sans-serif;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* ══════════════════════════════════════════════
       MOBILE FIXES — my_bookings.php only
       Desktop styles above are untouched.
    ══════════════════════════════════════════════ */
    @media (max-width: 768px) {

      /* PAGE HEADER: stack heading + view toggle vertically */
      .container .d-flex.justify-content-between.align-items-end {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 12px;
      }

      /* VIEW TABS: stretch full width so both tabs are equal */
      .view-tabs { width: 100%; display: flex; }
      .view-tab  { flex: 1; text-align: center; padding: 8px 10px; }

      /* ── CALENDAR: make it horizontally scrollable ──
         8 columns (venues + 7 days) never fit a phone width.
         Wrap it so user scrolls right cleanly. */
      .calendar-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 12px;
      }
      .calendar-table {
        min-width: 660px;
        table-layout: fixed;
      }
      /* Narrow the venue name column */
      .calendar-table thead th:first-child,
      .venue-cell-info {
        width: 110px !important;
        min-width: 110px;
        font-size: 0.78rem;
      }
      /* Day header columns */
      .calendar-th-day {
        min-width: 78px;
        padding: 10px 6px !important;
        font-size: 0.78rem;
      }
      .calendar-table td { padding: 8px 6px; }
      .day-slot-container { min-height: 80px; }
      .premium-cal-chip   { font-size: 0.7rem; padding: 4px 6px; }

      /* Calendar nav buttons + date range: wrap on small screens */
      .card > .d-flex.justify-content-between.align-items-center {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 10px;
      }
      .card .d-flex.gap-2.align-items-center {
        flex-wrap: wrap;
        gap: 8px !important;
      }

      /* ── BOOKING CARDS (LIST VIEW) ── */

      /* Stack thumbnail above content instead of cramped side-by-side */
      .booking-card .d-flex.gap-4.align-items-start {
        flex-direction: column;
        gap: 16px !important;
      }

      /* Thumbnail: full-width banner */
      .booking-venue-thumb {
        width: 100% !important;
        height: 160px !important;
        border-radius: var(--radius) !important;
      }
      .booking-venue-thumb-placeholder {
        width: 100% !important;
        height: 120px;
        border-radius: var(--radius);
        background: var(--bg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: var(--muted);
      }

      /* Venue name + status badge row: stack, left-align badge */
      .booking-card .d-flex.justify-content-between.align-items-start {
        flex-direction: column;
        gap: 8px;
      }
      .booking-card .text-end { text-align: left !important; }

      /* Bottom row (payment info + total): stack */
      .booking-card .d-flex.justify-content-between.align-items-end {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 12px;
      }
      /* Complete Payment button: full width */
      .booking-card .btn.btn-sm.btn-primary {
        width: 100%;
        text-align: center;
        margin-top: 8px;
      }

      .booking-total { font-size: 1.1rem; }
    }
  </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="breadcrumb-bar">
  <div class="container">
    <a href="index.php">Home</a>
    <span class="mx-2" style="color:#d1d5db;">/</span>
    <span>My Bookings</span>
  </div>
</div>

<div class="container mt-5 mb-2">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
    <div>
      <span class="section-eyebrow">Your Reservations</span>
      <h2 class="section-heading">My Bookings</h2>
      <p class="section-sub">All your event bookings and payment history in one place.</p>
    </div>
    <div class="view-tabs">
      <a class="view-tab <?= $view==='table' ? 'active' : '' ?>" href="?view=table">List View</a>
      <a class="view-tab <?= $view==='calendar' ? 'active' : '' ?>" href="?view=calendar">Calendar View</a>
    </div>
  </div>
</div>

<div class="container mb-5">
  <?php if (empty($bookings)): ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <h4>No bookings yet</h4>
      <p class="text-muted mb-4">You haven't made any bookings. Start by exploring our venues!</p>
      <a href="search.php" class="btn-book">Browse Venues</a>
    </div>

  <?php else: ?>

    <?php if ($view === 'calendar'): ?>
      <div class="card p-4 shadow-sm border-0 mb-5" style="border-radius:16px;">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
          <div>
            <h5 class="fw-bold mb-1" style="font-family:'Playfair Display',serif;">My Reservation Calendar</h5>
            <p class="text-muted small mb-0">Track your reserved timeslots across our premier locations</p>
          </div>
          <div class="d-flex gap-2 align-items-center">
            <a class="btn btn-sm btn-outline-secondary px-3 rounded-pill" href="?view=calendar&week=<?= $weekOffset - 1 ?>">
              <i class="bi bi-chevron-left me-1"></i> Prev Week
            </a>
            <span class="fw-bold text-dark px-3 bg-light py-1 rounded-pill border" style="font-size:0.88rem;">
              <?= $weekStart->format('M j') ?> &ndash; <?= $weekEnd->format('M j, Y') ?>
            </span>
            <a class="btn btn-sm btn-outline-secondary px-3 rounded-pill" href="?view=calendar&week=<?= $weekOffset + 1 ?>">
              Next Week <i class="bi bi-chevron-right ms-1"></i>
            </a>
          </div>
        </div>

        <div class="calendar-wrapper">
          <table class="calendar-table">
            <thead>
              <tr>
                <th style="width: 180px; background: #f8fafc; border-bottom: 2px solid #e2e8f0;">Venues</th>
                <?php foreach ($weekDays as $d): 
                  $isToday = $d->format('Y-m-d') === date('Y-m-d');
                ?>
                  <th class="calendar-th-day <?= $isToday ? 'today-highlight' : '' ?>">
                    <div style="font-size: 0.72rem; letter-spacing: 0.5px; text-transform: uppercase; opacity: 0.7;">
                      <?= $d->format('D') ?>
                    </div>
                    <div style="font-size: 1.1rem; margin-top: 2px;">
                      <?= $d->format('M j') ?>
                    </div>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($venues as $v): ?>
                <tr>
                  <td class="venue-cell-info">
                    <div class="truncate-text" title="<?= htmlspecialchars($v['name']) ?>"><?= htmlspecialchars($v['name']) ?></div>
                    <div class="venue-sub-cap"><i class="bi bi-people me-1"></i>Max <?= (int)$v['capacity'] ?></div>
                  </td>

                  <?php foreach ($weekDays as $d): 
                    $dateStr = $d->format('Y-m-d');
                  ?>
                    <td>
                      <div class="day-slot-container">
                        <?php 
                        // Maghanap ng bookings para sa venue at araw na ito sa kahit anong oras
                        $hasBooking = false;
                        foreach ($calBookings as $cb) {
                            if ((int)$cb['venue_id'] === (int)$v['id'] && $cb['event_date'] === $dateStr) {
                                $hasBooking = true;
                                $calCode = getDisplayBookingCode($cb);
                                $formattedTime = date('g:i A', strtotime($cb['event_time']));
                                ?>
                                <div class="premium-cal-chip" 
                                     onclick="window.location='?view=table'"
                                     title="Click to view details in list view">
                                  <span class="chip-time"><i class="bi bi-clock me-1"></i><?= $formattedTime ?></span>
                                  <span class="chip-title fw-bold"><?= htmlspecialchars($calCode) ?></span>
                                  <span class="chip-title text-truncate" style="font-size:0.7rem; opacity:0.9;"><?= htmlspecialchars($cb['event_name']) ?></span>
                                </div>
                                <?php
                            }
                        }
                        
                        // Kung walang booking, mag-iwan ng ultra-clean minimal plain text indicator
                        if (!$hasBooking): ?>
                          <div class="text-center my-auto text-muted opacity-25 small italic" style="font-size: 0.75rem; font-style: italic;">--</div>
                        <?php endif; ?>
                      </div>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php else: ?>
      <div class="row gy-4">
        <?php foreach ($bookings as $b): ?>
          <?php
            $addons       = $addonsByBooking[$b['booking_id']] ?? [];
            $hasImage     = !empty($b['venue_image']);
            $isPaid       = $b['payment_status'] === 'paid';
            $isPending    = $b['payment_status'] === 'pending';
            $isConfirmed  = $b['booking_status'] === 'confirmed';
            $txId         = $b['transaction_id'] ?? null;
            $payDate      = $b['payment_date']   ?? null;
          ?>
          <div class="col-12">
            <div class="booking-card p-4">
              <div class="d-flex gap-4 align-items-start flex-wrap">
                <?php if ($hasImage): ?>
                  <img src="<?= htmlspecialchars($b['venue_image']) ?>" alt="<?= htmlspecialchars($b['venue_name']) ?>" class="booking-venue-thumb">
                <?php else: ?>
                  <div class="booking-venue-thumb-placeholder"><i class="bi bi-building"></i></div>
                <?php endif; ?>

                <div class="flex-grow-1">
                  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                      <h5 class="mb-1 fw-bold" style="font-family:'Playfair Display',serif;">
                        <?= htmlspecialchars($b['venue_name']) ?> 
                        <span class="text-muted small fs-6">(<?= htmlspecialchars(getDisplayBookingCode($b)) ?>)</span>
                      </h5>
                      <p class="text-muted small mb-2"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($b['venue_location']) ?></p>
                    </div>
                    <div class="text-end">
                      <?= statusBadge($b['booking_status']) ?>
                      <?php if ($b['payment_status'] && $b['payment_status'] !== $b['booking_status']): ?>
                        <br><small class="text-muted mt-2 d-inline-block">Payment: <?= statusBadge($b['payment_status']) ?></small>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="meta-pill"><i class="bi bi-calendar-event"></i><?= htmlspecialchars(date('F j, Y', strtotime($b['event_date']))) ?></span>
                    <span class="meta-pill"><i class="bi bi-clock"></i><?= htmlspecialchars(date('g:i A', strtotime($b['event_time']))) ?></span>
                    <span class="meta-pill"><i class="bi bi-hourglass-split"></i><?= (int)$b['duration'] ?> hr<?= $b['duration'] > 1 ? 's' : '' ?></span>
                    <span class="meta-pill"><i class="bi bi-people"></i><?= (int)$b['guest_count'] ?> guests</span>
                    <span class="meta-pill"><i class="bi bi-star"></i><?= htmlspecialchars($b['event_name']) ?></span>
                  </div>

                  <?php if (!empty($addons)): ?>
                    <div class="mb-3">
                      <span class="text-muted" style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.8px;">Add-ons:</span><br>
                      <?php foreach ($addons as $addon): ?>
                        <span class="addon-chip"><?= htmlspecialchars($addon['addon_name']) ?></span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <div class="section-divider"></div>

                  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mt-3">
                    <div class="payment-info-row">
                      <?php if ($isPaid): ?>
                        <div><strong>Method:</strong> <?= htmlspecialchars(ucwords(str_replace('_', ' ', $b['payment_method']))) ?></div>
                        <?php if ($txId): ?><div><strong>Transaction ID:</strong> <?= htmlspecialchars($txId) ?></div><?php endif; ?>
                      <?php elseif ($isPending): ?>
                        <div class="text-warning fw-semibold" style="color: #a16207 !important;"><i class="bi bi-clock-history me-1"></i>Awaiting Admin Verification</div>
                      <?php else: ?>
                        <div class="text-danger-light fw-semibold" style="color: #b91c1c !important;"><i class="bi bi-exclamation-circle me-1"></i>Payment not yet completed</div>
                      <?php endif; ?>
                    </div>
                    <div class="text-end">
                      <div class="booking-total">&#8369;<?= number_format($b['total_price'], 2) ?></div>
                      <?php if (!$isPaid && !$isPending && $b['booking_status'] !== 'cancelled'): ?>
                        <a href="cart.php" class="btn btn-sm btn-primary mt-2"><i class="bi bi-credit-card me-1"></i>Complete Payment</a>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>