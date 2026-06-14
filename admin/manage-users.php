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
$currentPage = 'users';

/* ── Search ──────────────────────────────────────────────── */
$search = trim($_GET['q'] ?? '');

$sql = "SELECT id, first_name, last_name, email, phone, role FROM users WHERE 1=1"; 
$params = [];
$types  = '';
if ($search !== '') {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
    $types  = 'ssss';
}
$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Stats ───────────────────────────────────────────────── */
// Binago natin para mabilang ang 0 o 'user' depende sa database structure mo
$totalUsers   = (int) ($conn->query("SELECT COUNT(*) AS c FROM users WHERE role='user' OR role='0'")->fetch_assoc()['c'] ?? 0);
$activeUsers  = (int) ($conn->query("
    SELECT COUNT(DISTINCT user_id) AS c FROM bookings
    WHERE event_date >= CURDATE() - INTERVAL 30 DAY
")->fetch_assoc()['c'] ?? 0);
$newThisWeek  = $totalUsers > 0 ? max(0, min($totalUsers, 1)) : 0;

/* ── Per-user booking history (for side drawer) ─────────────── */
$userIds = array_column($users, 'id');
$historyByUser = [];
$bookingCountByUser = [];

if (!empty($userIds)) {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $types2 = str_repeat('i', count($userIds));

    $stmt = $conn->prepare("
        SELECT b.booking_id, b.user_id, b.event_date, b.event_time, b.status, b.total_price,
               v.name AS venue_name, e.event_name
        FROM bookings b
        JOIN venues v ON b.venue_id = v.id
        JOIN event  e ON b.event_id = e.event_id
        WHERE b.user_id IN ($placeholders)
        ORDER BY b.event_date DESC
    ");
    $stmt->bind_param($types2, ...$userIds);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $uid = $row['user_id'];
        $bookingCountByUser[$uid] = ($bookingCountByUser[$uid] ?? 0) + 1;
        if (!isset($historyByUser[$uid])) $historyByUser[$uid] = [];
        if (count($historyByUser[$uid]) < 8) $historyByUser[$uid][] = $row;
    }
}

function statusBadgeClass(string $status): string {
    return match($status) {
        'confirmed' => 'badge-confirmed',
        'pending'   => 'badge-pending',
        'cancelled' => 'badge-cancelled',
        default     => 'badge-inactive',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Users | Tagpo Admin</title>
  <?php include 'includes/admin_style.php'; ?>
  
  <style>
    .badge-role {
      display: inline-block;
      padding: 4px 12px;
      font-size: 12px;
      font-weight: 600;
      border-radius: 50px; /* Hugis Capsule */
      text-align: center;
    }
    .badge-admin-red {
      background-color: #fde8e8;
      color: #e02424;
      border: 1px solid #f8b4b4;
    }
    .badge-user-green {
      background-color: #def7ec;
      color: #03543f;
      border: 1px solid #84e1bc;
    }
  </style>
</head>
<body>

<?php include 'includes/admin_sidebar.php'; ?>

<div class="admin-main">

  <header class="admin-topbar">
    <div class="topbar-title">Users</div>
    <div class="topbar-right">
      <div class="topbar-date"><i class="bi bi-calendar3"></i> <?= date('M j, Y (D)') ?></div>
      <div class="topbar-avatar"><?= htmlspecialchars(strtoupper(substr($currentUser['first_name'] ?? 'A', 0, 1))) ?></div>
    </div>
  </header>

  <div class="admin-content">

    <div class="page-header">
      <h1>Users</h1>
      <p>Manage all registered Tagpo members and track their booking activity.</p>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="stat-card">
          <div class="stat-icon green"><i class="bi bi-people"></i></div>
          <div>
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-sub">All members</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="bi bi-person-check"></i></div>
          <div>
            <div class="stat-label">Active (Last 30 Days)</div>
            <div class="stat-value"><?= $activeUsers ?></div>
            <div class="stat-sub">Booked recently</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card">
          <div class="stat-icon orange"><i class="bi bi-person-plus"></i></div>
          <div>
            <div class="stat-label">Showing</div>
            <div class="stat-value"><?= count($users) ?></div>
            <div class="stat-sub">of <?= $totalUsers ?> users</div>
          </div>
        </div>
      </div>
    </div>

    <div class="panel-card">
      <div class="panel-card-header">
        <h2>All Users</h2>
      </div>
      <div class="filter-toolbar">
        <form method="get" class="search-box" style="flex:1;">
          <i class="bi bi-search"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, email, or phone number...">
        </form>
      </div>

      <?php if (empty($users)): ?>
        <div class="panel-card-body text-muted-sm">No users found.</div>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>User ID</th>
              <th>Full Name</th>
              <th>Email</th>
              <th>Phone Number</th>
              <th>Role</th> <th>Bookings</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u):
              $fullName = $u['first_name'] . ' ' . $u['last_name'];
              $initial  = strtoupper(substr($u['first_name'], 0, 1));
              $history  = $historyByUser[$u['id']] ?? [];
              $bookingsCount = $bookingCountByUser[$u['id']] ?? 0;

              // Dito natin kino-convert ang 1 o 0 para maging Text at Capsule badge
              if ($u['role'] == '1' || strtolower($u['role']) === 'admin') {
                  $roleText = 'Admin';
                  $roleClass = 'badge-admin-red';
              } else {
                  $roleText = 'User';
                  $roleClass = 'badge-user-green';
              }

              $historyHtml = '';
              if (empty($history)) {
                  $historyHtml = '<div class="text-muted-sm">No bookings yet.</div>';
              } else {
                  foreach ($history as $h) {
                      $historyHtml .= '<div class="info-row"><div>'
                          . '<div class="info-value" style="text-align:left;">' . htmlspecialchars($h['venue_name']) . '</div>'
                          . '<div class="text-muted-sm">' . htmlspecialchars($h['event_name']) . ' &middot; ' . htmlspecialchars(date('M j, Y', strtotime($h['event_date']))) . '</div>'
                          . '</div>'
                          . '<div class="text-end"><span class="badge-status ' . statusBadgeClass($h['status']) . '">' . htmlspecialchars(ucfirst($h['status'])) . '</span>'
                          . '<div class="text-muted-sm">&#8369;' . number_format($h['total_price'], 2) . '</div></div>'
                          . '</div>';
                  }
              }
            ?>
              <tr class="user-row"
                  data-id="<?= (int)$u['id'] ?>"
                  data-name="<?= htmlspecialchars($fullName, ENT_QUOTES) ?>"
                  data-initial="<?= htmlspecialchars($initial, ENT_QUOTES) ?>"
                  data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>"
                  data-phone="<?= htmlspecialchars($u['phone'] ?: 'Not provided', ENT_QUOTES) ?>"
                  data-role="<?= htmlspecialchars($roleText, ENT_QUOTES) ?>"
                  data-bookings="<?= $bookingsCount ?>"
                  data-history="<?= htmlspecialchars($historyHtml, ENT_QUOTES) ?>">
                <td class="text-muted-sm">#<?= (int)$u['id'] ?></td>
                <td>
                  <div class="d-flex align-center gap-8">
                    <div class="user-avatar"><?= htmlspecialchars($initial) ?></div>
                    <div class="user-name-block">
                      <div class="name"><?= htmlspecialchars($fullName) ?></div>
                    </div>
                  </div>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['phone'] ?: 'Not provided') ?></td>
                
                <td>
                  <span class="badge-role <?= $roleClass ?>">
                    <?= $roleText ?>
                  </span>
                </td>

                <td><?= $bookingsCount ?></td>
                <td><i class="bi bi-chevron-right text-muted-sm"></i></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div class="pagination-bar">
        <span>Showing 1 to <?= count($users) ?> of <?= $totalUsers ?> users</span>
      </div>
    </div>

  </div>
</div>

<div class="side-drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="side-drawer" id="userDrawer">
  <div class="drawer-header">
    <div class="d-flex align-center gap-8">
      <div class="user-avatar" id="drawerInitial" style="width:44px;height:44px;font-size:16px;"></div>
      <div>
        <h3 id="drawerName"></h3>
        <p id="drawerRoleText">Standard Member</p> </div>
    </div>
    <button class="drawer-close" onclick="closeDrawer()"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="drawer-body">
    <div class="drawer-section">
      <div class="drawer-section-title">User Information</div>
      <div class="info-row"><div class="info-label">User ID</div><div class="info-value" id="drawerId"></div></div>
      <div class="info-row"><div class="info-label">Email</div><div class="info-value" id="drawerEmail"></div></div>
      <div class="info-row"><div class="info-label">Phone</div><div class="info-value" id="drawerPhone"></div></div>
      <div class="info-row"><div class="info-label">Role</div><div class="info-value" id="drawerRole"></div></div> <div class="info-row"><div class="info-label">Total Bookings</div><div class="info-value" id="drawerBookings"></div></div>
    </div>
    <div class="drawer-section">
      <div class="drawer-section-title">Booking History</div>
      <div id="drawerHistory"></div>
    </div>
  </div>
</div>

<script>
function openDrawer(row) {
  document.getElementById('drawerName').textContent     = row.dataset.name;
  document.getElementById('drawerInitial').textContent  = row.dataset.initial;
  document.getElementById('drawerId').textContent       = '#' + row.dataset.id;
  document.getElementById('drawerEmail').textContent    = row.dataset.email;
  document.getElementById('drawerPhone').textContent    = row.dataset.phone;
  document.getElementById('drawerRole').textContent     = row.dataset.role;
  document.getElementById('drawerRoleText').textContent = row.dataset.role + " Member";
  document.getElementById('drawerBookings').textContent = row.dataset.bookings;
  document.getElementById('drawerHistory').innerHTML    = row.dataset.history;

  document.getElementById('userDrawer').classList.add('open');
  document.getElementById('drawerOverlay').classList.add('open');
}
function closeDrawer() {
  document.getElementById('userDrawer').classList.remove('open');
  document.getElementById('drawerOverlay').classList.remove('open');
}
document.querySelectorAll('.user-row').forEach(row => {
  row.addEventListener('click', () => openDrawer(row));
});
</script>

</body>
</html>