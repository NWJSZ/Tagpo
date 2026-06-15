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
$currentPage = 'payments';

/* ── Reference code helper (Binago para sa database custom codes) ───────────────── */
function getDisplayPaymentCode($row): string {
    return !empty($row['payment_code']) ? $row['payment_code'] : 'PMT-' . str_pad($row['payment_id'], 4, '0', STR_PAD_LEFT);
}

function getDisplayBookingCode($row): string {
    return !empty($row['booking_code']) ? $row['booking_code'] : 'BKN-' . str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT);
}

function statusBadgeClass(string $status): string {
    return match($status) {
        'paid'      => 'badge-paid',
        'pending'   => 'badge-pending',
        'failed'    => 'badge-failed',
        'refunded'  => 'badge-refunded',
        default     => 'badge-inactive',
    };
}

/* ── Handle approve / flag / refund actions ──────────────────────────── */
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'], $_POST['action'])) {
    $paymentId = (int) $_POST['payment_id'];
    $action    = $_POST['action'];

    $newStatus = match($action) {
        'approve' => 'paid',
        'flag'    => 'failed',
        'refund'  => 'refunded',
        default   => null,
    };

    if ($newStatus) {
        $stmt = $conn->prepare("UPDATE payments SET payment_status = ? WHERE payment_id = ?");
        $stmt->bind_param('si', $newStatus, $paymentId);
        $stmt->execute();
        $stmt->close();
        $flash = "Payment record updated to " . ucfirst($newStatus) . ".";
    }
}

/* ── Filters ─────────────────────────────────────────────────── */
$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';

/* ── Stats ───────────────────────────────────────────────────── */
$totalPayments = (int) ($conn->query("SELECT COUNT(*) AS c FROM payments")->fetch_assoc()['c'] ?? 0);
$totalPaidSum  = (float) ($conn->query("SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE payment_status='paid'")->fetch_assoc()['s'] ?? 0);
$pendingCount  = (int) ($conn->query("SELECT COUNT(*) AS c FROM payments WHERE payment_status='pending'")->fetch_assoc()['c'] ?? 0);
$failedCount   = (int) ($conn->query("SELECT COUNT(*) AS c FROM payments WHERE payment_status='failed'")->fetch_assoc()['c'] ?? 0);

/* ── Fetch payments joined with booking + user info ─────────────── */
$sql = "
    SELECT p.payment_id, p.payment_code, p.cart_id, p.amount, p.payment_method, p.payment_status,
           p.transaction_id, p.payment_date,
           b.booking_id, b.booking_code, b.event_date, b.event_time, b.duration, b.guest_count,
           u.user_code, u.first_name, u.last_name, u.email, u.phone,
           v.name AS venue_name
    FROM payments p
    JOIN bookings b ON b.cart_id = p.cart_id
    JOIN users u ON b.user_id = u.id
    JOIN venues v ON b.venue_id = v.id
    WHERE 1=1
";
$params = [];
$types  = '';

if ($search !== '') {
    // Isinama natin sa search query ang mga bago nating column codes para madaling mahanap
    $sql .= " AND (p.payment_code LIKE ? OR b.booking_code LIKE ? OR p.transaction_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
    $types .= 'ssssss';
}
if ($statusFilter !== '') {
    $sql .= " AND p.payment_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}
$sql .= " GROUP BY p.payment_id ORDER BY p.payment_id DESC";

$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Fetch secondary payment account tables (GCash / Card details) ── */
$gcashDetails = [];
$cardDetails = [];

$res = $conn->query("SELECT * FROM gcash_payments");
while($row = $res->fetch_assoc()) { $gcashDetails[$row['payment_id']] = $row; }

$res = $conn->query("SELECT * FROM card_payments");
while($row = $res->fetch_assoc()) { $cardDetails[$row['payment_id']] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payments | Tagpo Admin</title>
  <?php include 'includes/admin_style.php'; ?>
  <style>
    .clickable-row { cursor: pointer; transition: background 0.15s ease; }
    .clickable-row:hover { background-color: rgba(0, 0, 0, 0.015) !important; }
  </style>
</head>
<body>

<?php include 'includes/admin_sidebar.php'; ?>

<div class="admin-main">

  <header class="admin-topbar">
    <div class="topbar-title">Payments</div>
    <div class="topbar-right">
      <div class="topbar-date"><i class="bi bi-calendar3"></i> <?= date('M j, Y (D)') ?></div>
      <div class="topbar-avatar"><?= htmlspecialchars(strtoupper(substr($currentUser['first_name'] ?? 'A', 0, 1))) ?></div>
    </div>
  </header>

  <div class="admin-content">

    <div class="page-header">
      <h1>Payments</h1>
      <p>Trace transactions, verify reference codes, and approve or flag payments.</p>
    </div>

    <?php if ($flash): ?>
      <div class="alert-bar success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="bi bi-receipt"></i></div>
          <div>
            <div class="stat-label">Total Payments</div>
            <div class="stat-value"><?= $totalPayments ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon green"><i class="bi bi-cash-stack"></i></div>
          <div>
            <div class="stat-label">Total Collected</div>
            <div class="stat-value">&#8369;<?= number_format($totalPaidSum, 0) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon orange"><i class="bi bi-hourglass-split"></i></div>
          <div>
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?= $pendingCount ?></div>
            <div class="stat-sub">Need review</div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon purple"><i class="bi bi-x-octagon"></i></div>
          <div>
            <div class="stat-label">Failed / Flagged</div>
            <div class="stat-value"><?= $failedCount ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="panel-card">
      <div class="panel-card-header">
        <h2>All Transactions</h2>
      </div>
      <div class="filter-toolbar">
        <form method="get" class="search-box" style="flex:1;">
          <i class="bi bi-search"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by payment code, booking, ID, name...">
          <button type="submit" style="display:none;"></button>
        </form>
        <form method="get" class="d-flex gap-8">
          <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
          <select name="status" class="filter-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="pending"  <?= $statusFilter==='pending'  ? 'selected' : '' ?>>Pending</option>
            <option value="paid"     <?= $statusFilter==='paid'     ? 'selected' : '' ?>>Paid</option>
            <option value="failed"   <?= $statusFilter==='failed'   ? 'selected' : '' ?>>Failed</option>
            <option value="refunded" <?= $statusFilter==='refunded' ? 'selected' : '' ?>>Refunded</option>
          </select>
        </form>
      </div>

      <?php if (empty($payments)): ?>
        <div class="panel-card-body text-muted-sm">No payments found.</div>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Payment Code</th>
              <th>Booking Ref</th>
              <th>Transaction ID</th>
              <th>Customer</th>
              <th>Venue</th>
              <th>Amount</th>
              <th>Method</th>
              <th>Status</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $p): 
                $pCode = getDisplayPaymentCode($p);
                $bCode = getDisplayBookingCode($p);
                $custName = $p['first_name'] . ' ' . $p['last_name'];
                $methodName = ucwords(str_replace('_', ' ', $p['payment_method']));
                
                // Get extended information variables for side drawer
                $methodDetailsHtml = '';
                if ($p['payment_method'] === 'gcash' && isset($gcashDetails[$p['payment_id']])) {
                    $g = $gcashDetails[$p['payment_id']];
                    $methodDetailsHtml = '<div class="info-row"><div class="info-label">GCash Account Name</div><div class="info-value">'.htmlspecialchars($g['gcash_account_name']).'</div></div>'
                                       . '<div class="info-row"><div class="info-label">GCash Phone Number</div><div class="info-value">'.htmlspecialchars($g['gcash_phone_number']).'</div></div>';
                } elseif (in_array($p['payment_method'], ['credit_card', 'debit_card']) && isset($cardDetails[$p['payment_id']])) {
                    $c = $cardDetails[$p['payment_id']];
                    $methodDetailsHtml = '<div class="info-row"><div class="info-label">Cardholder Name</div><div class="info-value">'.htmlspecialchars($c['card_holder_name']).'</div></div>'
                                       . '<div class="info-row"><div class="info-label">Card Info</div><div class="info-value">•••• •••• •••• '.htmlspecialchars($c['card_last_four']).' (Exp: '.htmlspecialchars($c['card_expiry_month']).'/'.htmlspecialchars($c['card_expiry_year']).')</div></div>';
                } else {
                    $methodDetailsHtml = '<div class="text-muted-sm">No additional metadata available.</div>';
                }
            ?>
              <tr class="payment-row clickable-row"
                  data-id="<?= (int)$p['payment_id'] ?>"
                  data-pcode="<?= htmlspecialchars($pCode) ?>"
                  data-bcode="<?= htmlspecialchars($bCode) ?>"
                  data-txid="<?= htmlspecialchars($p['transaction_id'] ?? 'N/A') ?>"
                  data-name="<?= htmlspecialchars($custName, ENT_QUOTES) ?>"
                  data-ucode="<?= htmlspecialchars($p['user_code'] ?? 'N/A') ?>"
                  data-email="<?= htmlspecialchars($p['email'], ENT_QUOTES) ?>"
                  data-phone="<?= htmlspecialchars($p['phone'] ?? 'Not provided', ENT_QUOTES) ?>"
                  data-venue="<?= htmlspecialchars($p['venue_name'], ENT_QUOTES) ?>"
                  data-amount="<?= number_format($p['amount'], 2) ?>"
                  data-method="<?= htmlspecialchars($methodName) ?>"
                  data-status="<?= htmlspecialchars($p['payment_status']) ?>"
                  data-status-label="<?= htmlspecialchars(ucfirst($p['payment_status'])) ?>"
                  data-status-class="<?= statusBadgeClass($p['payment_status']) ?>"
                  data-date="<?= $p['payment_date'] ? htmlspecialchars(date('l, F j, Y (g:i A)', strtotime($p['payment_date']))) : 'N/A' ?>"
                  data-details-html="<?= htmlspecialchars($methodDetailsHtml, ENT_QUOTES) ?>">
                
                <td class="fw-600"><?= htmlspecialchars($pCode) ?></td>
                <td class="text-muted-sm" style="font-weight: 500;"><?= htmlspecialchars($bCode) ?></td>
                <td class="text-muted-sm"><?= htmlspecialchars($p['transaction_id'] ?? '—') ?></td>
                <td>
                  <div class="user-name-block">
                    <div class="name"><?= htmlspecialchars($custName) ?></div>
                    <div class="email"><?= htmlspecialchars($p['email']) ?></div>
                  </div>
                </td>
                <td><?= htmlspecialchars($p['venue_name']) ?></td>
                <td class="fw-600">&#8369;<?= number_format($p['amount'], 2) ?></td>
                <td><?= htmlspecialchars($methodName) ?></td>
                <td><span class="badge-status <?= statusBadgeClass($p['payment_status']) ?>"><?= htmlspecialchars(ucfirst($p['payment_status'])) ?></span></td>
                <td class="text-muted-sm"><?= $p['payment_date'] ? htmlspecialchars(date('M j, Y', strtotime($p['payment_date']))) : '—' ?></td>
                <td>
                   <i class="bi bi-chevron-right text-muted-sm"></i>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div class="pagination-bar">
        <span>Showing <?= count($payments) ?> of <?= $totalPayments ?> payments</span>
      </div>
    </div>

  </div>
</div>

<div class="side-drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="side-drawer" id="paymentDrawer">
  <div class="drawer-header">
    <div>
      <h3>Transaction Overview</h3>
      <p id="drawerPaymentCode" style="font-weight: 600; color: #1f2937; margin: 0;"></p>
    </div>
    <button class="drawer-close" onclick="closeDrawer()"><i class="bi bi-x-lg"></i></button>
  </div>
  
  <div class="drawer-body">
    <div class="drawer-section">
      <div class="drawer-section-title">Payment Information</div>
      <div class="info-row"><div class="info-label">Payment ID Code</div><div class="info-value" id="drawerPCode" style="font-weight:600;"></div></div>
      <div class="info-row"><div class="info-label">Linked Booking</div><div class="info-value" id="drawerBCode"></div></div>
      <div class="info-row"><div class="info-label">Transaction Reference</div><div class="info-value" id="drawerTxId"></div></div>
      <div class="info-row"><div class="info-label">Amount Transacted</div><div class="info-value fw-600" style="color: #059669;">&#8369;<span id="drawerAmount"></span></div></div>
      <div class="info-row"><div class="info-label">Payment Method</div><div class="info-value" id="drawerMethod"></div></div>
      <div class="info-row"><div class="info-label">Payment Date / Time</div><div class="info-value text-muted-sm" id="drawerDate"></div></div>
      <div class="info-row"><div class="info-label">Status</div><div class="info-value" id="drawerStatus"></div></div>
    </div>

    <div class="drawer-section">
      <div class="drawer-section-title">Customer Information</div>
      <div class="info-row"><div class="info-label">Member Code</div><div class="info-value" id="drawerUserCode"></div></div>
      <div class="info-row"><div class="info-label">Full Name</div><div class="info-value" id="drawerName"></div></div>
      <div class="info-row"><div class="info-label">Email Address</div><div class="info-value" id="drawerEmail"></div></div>
      <div class="info-row"><div class="info-label">Phone Connection</div><div class="info-value" id="drawerPhone"></div></div>
    </div>

    <div class="drawer-section">
      <div class="drawer-section-title">Account / Gateway Metadata</div>
      <div id="drawerGatewayDetails"></div>
    </div>
  </div>

  <div class="drawer-footer" style="display: flex; flex-direction: column; gap: 8px;">
    <form method="post" id="drawerForm">
      <input type="hidden" name="payment_id" id="formPaymentId">
      <input type="hidden" name="action" id="formAction">
    </form>
    <button class="btn-full btn-full-green" id="btnApprove" onclick="submitAction('approve')">
      <i class="bi bi-check-circle me-1"></i> Approve Payment
    </button>
    <button class="btn-full btn-full-red" id="btnFlag" onclick="submitAction('flag')">
      <i class="bi bi-flag me-1"></i> Flag / Reject Payment
    </button>
    <button class="btn-full btn-full-outline" id="btnRefund" onclick="submitAction('refund')">
      <i class="bi bi-arrow-counterclockwise me-1"></i> Issue Refund
    </button>
  </div>
</div>

<script>
function closeDrawer() {
  document.getElementById('paymentDrawer').classList.remove('open');
  document.getElementById('drawerOverlay').classList.remove('open');
}

function submitAction(action) {
  let msg = "Proceed with this payment update?";
  if (action === 'refund') msg = "Are you sure you want to log a refund for this transaction?";
  if (action === 'flag') msg = "Are you sure you want to reject/flag this payment?";
  
  if (confirm(msg)) {
      document.getElementById('formAction').value = action;
      document.getElementById('drawerForm').submit();
  }
}

function openDrawer(row) {
  const d = row.dataset;
  
  // Fill the Side Drawer Fields
  document.getElementById('drawerPaymentCode').textContent = d.pcode;
  document.getElementById('drawerPCode').textContent       = d.pcode;
  document.getElementById('drawerBCode').textContent       = d.bcode + " (" + d.venue + ")";
  document.getElementById('drawerTxId').textContent        = d.txid;
  document.getElementById('drawerAmount').textContent      = d.amount;
  document.getElementById('drawerMethod').textContent      = d.method;
  document.getElementById('drawerDate').textContent        = d.date;
  document.getElementById('drawerStatus').innerHTML        = '<span class="badge-status ' + d.statusClass + '">' + d.statusLabel + '</span>';
  
  document.getElementById('drawerUserCode').textContent    = d.ucode;
  document.getElementById('drawerName').textContent        = d.name;
  document.getElementById('drawerEmail').textContent       = d.email;
  document.getElementById('drawerPhone').textContent       = d.phone;
  
  // GCash / Card explicit inner rows mapping
  document.getElementById('drawerGatewayDetails').innerHTML = d.detailsHtml;

  // Pass current working ID into hidden structural form input
  document.getElementById('formPaymentId').value = d.id;

  // Toggle footer action state controllers dynamically
  document.getElementById('btnApprove').style.display = (d.status === 'pending') ? '' : 'none';
  document.getElementById('btnFlag').style.display    = (d.status === 'pending') ? '' : 'none';
  document.getElementById('btnRefund').style.display  = (d.status === 'paid') ? '' : 'none';

  // Toggle CSS Transitions
  document.getElementById('paymentDrawer').classList.add('open');
  document.getElementById('drawerOverlay').classList.add('open');
}

document.querySelectorAll('.payment-row').forEach(row => {
  row.addEventListener('click', () => openDrawer(row));
});
</script>

</body>
</html>