<?php
// includes/admin_sidebar.php
// Usage: include at top of every admin page AFTER $currentPage is set
// e.g. $currentPage = 'bookings';
$currentPage = $currentPage ?? '';
?>
<nav class="admin-sidebar" id="adminSidebar">
  <div class="sidebar-brand">
    <a href="../index.php" class="brand-link">
      <span class="brand-logo">T</span>
      <span class="brand-name">Tagpo<span class="brand-dot">.</span></span>
    </a>
    <span class="admin-badge">Admin</span>
  </div>

  <div class="sidebar-section-label">Main</div>
  <ul class="sidebar-nav">
    <li>
      <a href="dashboard.php" class="sidebar-link <?= $currentPage==='dashboard' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
      </a>
    </li>
    <li>
      <a href="manage-bookings.php" class="sidebar-link <?= $currentPage==='bookings' ? 'active' : '' ?>">
        <i class="bi bi-calendar-check"></i><span>Bookings</span>
      </a>
    </li>
    <li>
      <a href="manage-users.php" class="sidebar-link <?= $currentPage==='users' ? 'active' : '' ?>">
        <i class="bi bi-people"></i><span>Users</span>
      </a>
    </li>
    <li>
      <a href="manage-venues.php" class="sidebar-link <?= $currentPage==='venues' ? 'active' : '' ?>">
        <i class="bi bi-building"></i><span>Venues</span>
      </a>
    </li>
    <li>
      <a href="manage-payments.php" class="sidebar-link <?= $currentPage==='payments' ? 'active' : '' ?>">
        <i class="bi bi-cash-stack"></i><span>Payments</span>
      </a>
    </li>
  </ul>

  <div class="sidebar-section-label">Account</div>
  <ul class="sidebar-nav">
    <li>
      <a href="../index.php" class="sidebar-link">
        <i class="bi bi-house"></i><span>Client View</span>
      </a>
    </li>
    <li>
      <a href="../auth/logout.php" class="sidebar-link sidebar-link--danger">
        <i class="bi bi-box-arrow-right"></i><span>Logout</span>
      </a>
    </li>
  </ul>
</nav>