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
$currentPage = 'dashboard';

/* ── Stats ───────────────────────────────────────────────── */
$totalBookings  = (int) ($conn->query("SELECT COUNT(*) AS c FROM bookings")->fetch_assoc()['c'] ?? 0);
$totalUsers     = (int) ($conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'user'")->fetch_assoc()['c'] ?? 0);
$pendingPay     = (int) ($conn->query("SELECT COUNT(*) AS c FROM payments WHERE payment_status = 'pending'")->fetch_assoc()['c'] ?? 0);
$activeVenues   = (int) ($conn->query("SELECT COUNT(*) AS c FROM venues")->fetch_assoc()['c'] ?? 0);

$newThisWeek = (int) ($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE event_date >= CURDATE() - INTERVAL 7 DAY")->fetch_assoc()['c'] ?? 0);

/* ── Recent bookings ─────────────────────────────────────── */
$recent = $conn->query("
    SELECT b.booking_id, b.event_date, b.event_time, b.status, b.total_price,
           u.first_name, u.last_name, v.name AS venue_name, e.event_name
    FROM bookings b
    JOIN users u  ON b.user_id  = u.id
    JOIN venues v ON b.venue_id = v.id
    JOIN event  e ON b.event_id = e.event_id
    ORDER BY b.booking_id DESC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | Tagpo Admin</title>
  <?php include 'includes/admin_style.php'; ?>
</head>
<body>

<?php include 'includes/admin_sidebar.php'; ?>

<div class="admin-main">

  <header class="admin-topbar">
    <div class="topbar-title">Dashboard</div>
    <div class="topbar-right">
      <div class="topbar-date"><i class="bi bi-calendar3"></i> <?= date('M j, Y (D)') ?></div>
      <div class="topbar-avatar"><?= htmlspecialchars(strtoupper(substr($currentUser['first_name'] ?? 'A', 0, 1))) ?></div>
    </div>
  </header>

  <div class="admin-content">

    <div class="page-header">
      <h1>Welcome back, <?= htmlspecialchars($currentUser['first_name'] ?? 'Admin') ?></h1>
      <p>Here's what's happening with Tagpo today.</p>
    </div>

    <!-- Stat cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="bi bi-calendar-check"></i></div>
          <div>
            <div class="stat-label">Total Bookings</div>
            <div class="stat-value"><?= $totalBookings ?></div>
            <div class="stat-sub">+<?= $newThisWeek ?> this week</div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon green"><i class="bi bi-people"></i></div>
          <div>
            <div class="stat-label">Registered Users</div>
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-sub">All members</div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon orange"><i class="bi bi-cash-coin"></i></div>
          <div>
            <div class="stat-label">Pending Payments</div>
            <div class="stat-value"><?= $pendingPay ?></div>
            <div class="stat-sub">Need review</div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon purple"><i class="bi bi-building"></i></div>
          <div>
            <div class="stat-label">Active Venues</div>
            <div class="stat-value"><?= $activeVenues ?></div>
            <div class="stat-sub">Available for booking</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent bookings -->
    <div class="panel-card">
      <div class="panel-card-header">
        <h2>Recent Bookings</h2>
        <a href="manage-bookings.php" class="btn-action btn-outline-green">View All <i class="bi bi-arrow-right"></i></a>
      </div>
      <?php if (empty($recent)): ?>
        <div class="panel-card-body text-muted-sm">No bookings yet.</div>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Client</th>
              <th>Venue</th>
              <th>Event</th>
              <th>Date / Time</th>
              <th>Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $b): ?>
              <tr onclick="location.href='manage-bookings.php?booking=<?= $b['booking_id'] ?>'">
                <td class="fw-600"><?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?></td>
                <td><?= htmlspecialchars($b['venue_name']) ?></td>
                <td><?= htmlspecialchars($b['event_name']) ?></td>
                <td>
                  <?= htmlspecialchars(date('M j, Y', strtotime($b['event_date']))) ?>
                  <span class="text-muted-sm"><?= htmlspecialchars(date('g:i A', strtotime($b['event_time']))) ?></span>
                </td>
                <td>&#8369;<?= number_format($b['total_price'], 2) ?></td>
                <td><span class="badge-status <?= statusBadgeClass($b['status']) ?>"><?= htmlspecialchars(ucfirst($b['status'])) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </div>
</div>

</body>
</html>