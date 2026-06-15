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

function refCode(int $bookingId): string {
    return 'TGP-' . strtoupper(substr(md5('tagpo_' . $bookingId), 0, 8));
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

/* ── Handle approve / flag actions ──────────────────────────── */
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
        $flash = "Payment #{$paymentId} updated to " . ucfirst($newStatus) . ".";
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
    SELECT p.payment_id, p.cart_id, p.amount, p.payment_method, p.payment_status,
           p.transaction_id, p.payment_date,
           b.booking_id, b.event_date,
           u.first_name, u.last_name, u.email,
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
    $sql .= " AND (p.transaction_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payments | Tagpo Admin</title>
  <?php include 'includes/admin_style.php'; ?>
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

    <!-- Stat cards -->
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

    <!-- Table -->
    <div class="panel-card">
      <div class="panel-card-header">
        <h2>All Transactions</h2>
      </div>
      <div class="filter-toolbar">
        <form method="get" class="search-box" style="flex:1;">
          <i class="bi bi-search"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by transaction ID, name, or email...">
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
              <th>Reference</th>
              <th>Transaction ID</th>
              <th>Customer</th>
              <th>Venue</th>
              <th>Amount</th>
              <th>Method</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $p): ?>
              <tr>
                <td class="fw-600"><?= refCode($p['booking_id']) ?></td>
                <td class="text-muted-sm"><?= htmlspecialchars($p['transaction_id'] ?? '—') ?></td>
                <td>
                  <div class="user-name-block">
                    <div class="name"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></div>
                    <div class="email"><?= htmlspecialchars($p['email']) ?></div>
                  </div>
                </td>
                <td><?= htmlspecialchars($p['venue_name']) ?></td>
                <td>&#8369;<?= number_format($p['amount'], 2) ?></td>
                <td><?= htmlspecialchars(ucwords(str_replace('_',' ',$p['payment_method']))) ?></td>
                <td><span class="badge-status <?= statusBadgeClass($p['payment_status']) ?>"><?= htmlspecialchars(ucfirst($p['payment_status'])) ?></span></td>
                <td class="text-muted-sm"><?= $p['payment_date'] ? htmlspecialchars(date('M j, Y', strtotime($p['payment_date']))) : '—' ?></td>
                <td>
                  <form method="post" class="d-flex gap-8">
                    <input type="hidden" name="payment_id" value="<?= (int)$p['payment_id'] ?>">
                    <?php if ($p['payment_status'] === 'pending'): ?>
                      <button type="submit" name="action" value="approve" class="icon-btn" title="Approve"><i class="bi bi-check-lg"></i></button>
                      <button type="submit" name="action" value="flag" class="icon-btn" title="Flag / Reject"><i class="bi bi-flag"></i></button>
                    <?php elseif ($p['payment_status'] === 'paid'): ?>
                      <button type="submit" name="action" value="refund" class="icon-btn" title="Refund"><i class="bi bi-arrow-counterclockwise"></i></button>
                    <?php else: ?>
                      <span class="text-muted-sm">—</span>
                    <?php endif; ?>
                  </form>
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

</body>
</html>