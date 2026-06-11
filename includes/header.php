<?php
// Only load configs if not already loaded
if (!function_exists('getBaseUrl')) {
    require_once dirname(dirname(__FILE__)) . '/config/database.php';
    require_once dirname(dirname(__FILE__)) . '/config/session_config.php';
}

$baseUrl     = isset($baseUrl) ? $baseUrl : getBaseUrl();
$current     = basename($_SERVER['PHP_SELF']);
$isAdmin     = isAdmin();
$cartCount   = getCartCount();
$currentUser = getCurrentUser();
?>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">

    <!-- BRAND -->
    <a class="navbar-brand fw-bold" href="<?php echo $baseUrl; ?>index.php">
      Tagpo<span class="text-primary">.</span>
    </a>

    <!-- TOGGLER -->
    <button class="navbar-toggler border-0 shadow-none" type="button"
      data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- NAV LINKS -->
    <div class="collapse navbar-collapse" id="mainNav">

      <ul class="navbar-nav mx-auto">

        <li class="nav-item">
          <a class="nav-link <?php echo $current === 'index.php' ? 'fw-semibold text-dark' : ''; ?>"
             href="<?php echo $baseUrl; ?>index.php">Home</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="<?php echo $baseUrl; ?>index.php#venues">Explore Venues</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="<?php echo $baseUrl; ?>index.php#about">About Us</a>
        </li>

        <!-- Cart -->
        <li class="nav-item">
          <a class="nav-link position-relative <?php echo $current === 'cart.php' ? 'fw-semibold text-dark' : ''; ?>"
             href="<?php echo $baseUrl; ?>cart.php">
            Cart
            <?php if ($cartCount > 0): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?php echo $cartCount; ?>
              </span>
            <?php endif; ?>
          </a>
        </li>

        <!-- My Bookings — visible to all logged-in non-admin users -->
        <?php if (isLoggedIn() && !$isAdmin): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo $current === 'my_bookings.php' ? 'fw-semibold text-dark' : ''; ?>"
               href="<?php echo $baseUrl; ?>my_bookings.php">
              My Bookings
            </a>
          </li>
        <?php endif; ?>

        <!-- Admin Dropdown -->
        <?php if ($isAdmin): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-danger fw-semibold" href="#"
               role="button" data-bs-toggle="dropdown">
              Admin
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>admin/admin.php?view=dashboard">Dashboard</a></li>
              <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>admin/add_venue.php">Add Venue</a></li>
              <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>admin/admin.php?view=bookings">View All Bookings</a></li>
            </ul>
          </li>
        <?php endif; ?>

      </ul>

      <!-- RIGHT SIDE: User info + logout / login buttons -->
      <div class="d-flex align-items-center gap-2">

        <?php if (isLoggedIn()): ?>

          <span class="fw-semibold d-none d-md-inline">
            Hi, <?php echo htmlspecialchars($currentUser['first_name']); ?> 👋
          </span>

          <a href="<?php echo $baseUrl; ?>auth/logout.php" class="btn btn-danger btn-sm">
            Logout
          </a>

        <?php else: ?>

          <a href="<?php echo $baseUrl; ?>auth/login.php"
             class="text-decoration-none fw-semibold text-secondary">
            Log In
          </a>

          <a href="<?php echo $baseUrl; ?>auth/signup.php" class="btn btn-book shadow-sm">
            Sign Up
          </a>

        <?php endif; ?>

      </div>

    </div>
  </div>
</nav>

<script src="<?php echo $baseUrl; ?>assets/shortcuts.js"></script>