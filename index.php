<?php
require_once 'config/session_config.php';

// Update activity timestamp
$_SESSION['last_activity'] = time();

// Refresh cookie if logged in
$currentUser = getCurrentUser();
if ($currentUser) {
  setcookie('user_session', $currentUser['email'], time() + (60 * 60 * 24 * 7), '/');
}

// Check for session expiry message
$sessionExpired = isset($_GET['session_expired']);

// Get logged in status and admin status
$isLoggedIn = isLoggedIn();
$isAdmin = isAdmin();

// 2. Data Preparation
$defaultVenues = [
  [
    'id'       => 1,
    'name'     => 'Paradiso Terrestre',
    'location' => 'Molino, Cavite City',
    'price'    => '35,000',
    'cap'      => 500,
    'rating'   => 4.8,
    'reviews'  => 36,
    'tag'      => 'Wedding · Debut',
    'image'    => 'assets/images/paradiso1.jpg',
  ],
  [
    'id'       => 2,
    'name'     => 'Blue Gardens',
    'location' => 'Makati City',
    'price'    => '60,000',
    'cap'      => 250,
    'rating'   => 4.9,
    'reviews'  => 52,
    'tag'      => 'Prom · Gala',
    'image'    => 'assets/images/gardens1.jpg',
  ],
  [
    'id'       => 3,
    'name'     => 'The Green Lounge Events Place',
    'location' => 'Quezon City',
    'price'    => '45,000',
    'cap'      => 300,
    'rating'   => 4.7,
    'reviews'  => 28,
    'tag'      => 'Birthday · Corporate',
    'image'    => 'assets/images/lounge1.jpg',
  ],
];

// Kunin ang venues mula sa session (yung mga in-add ni admin)
$customVenues = $_SESSION['venues'] ?? [];

// Pagsamahin ang default at custom venues
$venues = array_merge($defaultVenues, $customVenues);

// Filter venues by location if search parameter exists
$location = $_GET['location'] ?? '';
if (!empty($location)) {
  $filteredVenues = [];
  foreach ($venues as $venue) {
    $venueLocation = $venue['location'];

    // hatiin yung string by comma
    $parts = str_getcsv($venueLocation);

    foreach ($parts as $part) {
      if (stripos(trim($part), $location) !== false) {
        $filteredVenues[] = $venue;
        break;
      }
    }
  }
  $venues = $filteredVenues;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Tagpo | Find Your Perfect Venue</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>

<body>

  <?php if (isset($sessionExpired) && $sessionExpired): ?>
    <div class="alert alert-warning alert-dismissible fade show m-3" role="alert" style="font-size: 1.1rem; font-weight: 500;">
      <strong>Session Expired!</strong> Your login session has timed out after 60 seconds.
      <a href="login.php" class="alert-link btn btn-sm btn-warning ms-3">Log In Again</a>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <script>
      // Clear wishlist when session expires
      localStorage.removeItem('tagpo_wishlist');
    </script>
  <?php endif; ?>

  <?php include 'includes/header.php'; ?>

  <section class="hero-bg text-center">
    <div class="container position-relative" style="z-index:2; max-width: 900px;">
      <p class="section-eyebrow fade-up">Weddings, Birthdays, Proms & Special Events</p>

      <h1 class="display-4 fw-bold mb-4 fade-up-1">
        From first click to <br>
        <span class="hero-gold">confirmed celebration</span>
      </h1>

      <div class="fade-up-2">
        <p class="lead mb-4" style="color: rgba(255,255,255,0.9); font-weight: 500;">
          Plan your perfect event—without the stress. From venue search to final booking, everything is in one place. No more switching between tools or chasing details.
        </p>

        <p class="mx-auto" style="color: rgba(255,255,255,0.7); font-size: 0.95rem; max-width: 750px; line-height: 1.8;">
          With <strong>Tagpo</strong>, you can browse venues, manage reservations, and organize your event seamlessly.
          So you can focus on what truly matters—celebrating your special moments.
        </p>
      </div>
    </div>
  </section>

  <div class="container search-container fade-up">
    <div class="card search-card p-4 mb-5">
      <form action="search.php" method="GET" class="row g-3 align-items-end">

        <div class="col-lg-3 col-md-6">
          <label class="form-label">WHERE</label>
          <div class="input-group">
            <span class="input-group-text border-end-0">
              <i class="bi bi-geo-alt"></i>
            </span>
            <input type="text" name="location" class="form-control border-start-0"
              placeholder="City or area"
              value="<?php echo htmlspecialchars($_GET['location'] ?? ''); ?>" />
          </div>
        </div>

        <div class="col-lg-3 col-md-6">
          <label class="form-label">EVENT TYPE</label>
          <select name="type" class="form-select">
            <option value="">All Event Types</option>
            <option value="birthday" <?php echo ($_GET['type'] ?? '') === 'birthday'   ? 'selected' : ''; ?>>Birthday Party</option>
            <option value="prom" <?php echo ($_GET['type'] ?? '') === 'prom'       ? 'selected' : ''; ?>>Prom / Ball</option>
            <option value="wedding" <?php echo ($_GET['type'] ?? '') === 'wedding'    ? 'selected' : ''; ?>>Wedding</option>
            <option value="corporate" <?php echo ($_GET['type'] ?? '') === 'corporate' ? 'selected' : ''; ?>>Corporate Event</option>
            <option value="reunion" <?php echo ($_GET['type'] ?? '') === 'reunion'    ? 'selected' : ''; ?>>Reunion</option>
            <option value="anniversary" <?php echo ($_GET['type'] ?? '') === 'anniversary' ? 'selected' : ''; ?>>Anniversary</option>
          </select>
        </div>

        <div class="col-lg-2 col-md-6">
          <label class="form-label">GUESTS</label>
          <select name="guests" class="form-control" style="color: #6c757d;">
            <option value="" disabled selected hidden>How many?</option>
            <option value="250" <?php echo ($_GET['guests'] ?? '') === '250' ? 'selected' : ''; ?>>Up to 250 pax</option>
            <option value="251-300" <?php echo ($_GET['guests'] ?? '') === '251-300' ? 'selected' : ''; ?>>251 to 300 pax</option>
            <option value="300+" <?php echo ($_GET['guests'] ?? '') === '300+' ? 'selected' : ''; ?>>300 pax and above</option>
          </select>
        </div>

        <div class="col-lg-2 col-md-6">
          <label class="form-label">MAX BUDGET</label>
          <input type="number" name="budget" class="form-control"
            placeholder="₱ Amount"
            value="<?php echo htmlspecialchars($_GET['budget'] ?? ''); ?>" />
        </div>

        <div class="col-lg-2">
          <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
            <i class="bi bi-search me-2"></i>Search
          </button>
        </div>

      </form>
    </div>

    <div id="venues" class="d-flex align-items-end justify-content-between mb-4">
      <div>
        <p class="section-eyebrow mb-1">Featured Venues</p>
        <h2 class="section-heading mb-1">Top Picks For You</h2>
        <p class="section-sub">Handpicked venues across the Philippines</p>
      </div>
      <a href="search.php" class="btn-view btn btn-outline-dark btn-sm px-4 d-none d-md-inline-block">
        View All <i class="bi bi-arrow-right ms-1"></i>
      </a>
    </div>

    <div class="row g-4 mb-5">
      <?php foreach ($venues as $i => $v): ?>
        <div class="col-md-4 fade-up-<?php echo $i + 1; ?>">
          <div class="card venue-card h-100">

            <?php if ($isAdmin && $v['id'] > 10): ?>
              <a href="delete_venue.php?id=<?php echo $v['id']; ?>"
                class="btn btn-danger btn-sm position-absolute"
                style="top: 10px; right: 10px; z-index: 5; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;"
                onclick="return confirm('Are you sure you want to remove this venue?')">
                <i class="bi bi-trash"></i>
              </a>
            <?php endif; ?>

            <div class="venue-img-wrapper position-relative overflow-hidden">
              <img src="<?php echo htmlspecialchars($v['image'] ?? 'assets/images/default.jpg'); ?>" alt="<?php echo htmlspecialchars($v['name']); ?>" class="img-fluid w-100" />
              <span class="venue-badge"><?php echo htmlspecialchars($v['tag']); ?></span>
            </div>

            <div class="card-body d-flex flex-column">
              <h5 class="mb-1"><?php echo htmlspecialchars($v['name']); ?></h5>

              <div class="venue-meta mb-2">
                <span><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($v['location']); ?></span>
                <span>⭐ <?php echo $v['rating']; ?> (<?php echo $v['reviews']; ?>)</span>
              </div>

              <div class="d-flex justify-content-between align-items-center mt-auto pt-3"
                style="border-top: 1px solid var(--border);">
                <div>
                  <div class="price-tag">
                    ₱<?php echo htmlspecialchars($v['price']); ?>
                    <small>/ package</small>
                  </div>
                  <small class="text-muted" style="font-size:.75rem;">
                    <i class="bi bi-people me-1"></i>Up to <?php echo number_format($v['cap']); ?> guests
                  </small>
                </div>
                <a href="venue.php?id=<?php echo (int)$v['id']; ?>" class="btn-view btn">
                  View <i class="bi bi-arrow-right ms-1"></i>
                </a>
              </div>
            </div>

          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- ==================== FEATURED SECTION ====================
    <section class="feature-section">
      <div class="container">
        <div class="feature-with-image">
          <div class="feature-image">
            <img src="https://i.pinimg.com/1200x/67/cd/04/67cd04f1d7b0432e8d214d4b7d67f6f2.jpg" alt="Event Planning">
          </div>
          <div class="feature-text">
            <span class="featured-badge">Award Winning Platform</span>
            <h2>Because Every Event <span style="font-style: italic; color: #d4c5b0;">Deserves Perfection</span></h2>
            <p>Planning an event shouldn't be stressful. From finding the perfect venue to managing bookings and payments, Tagpo simplifies the entire process. Browse hundreds of venues across the Philippines, compare prices and amenities, and book with confidence.</p>
            <p>Our platform is designed specifically for Filipino event planners, with venues that understand local preferences and celebration traditions.</p>
            <a href="search.php" class="btn btn-dark btn-lg mt-3">
              <i class="bi bi-search me-2"></i>Browse Venues
            </a>
          </div>
        </div>
      </div>
    </section> -->

    <div id="features" class="feature-strip rounded-3 mb-5">
      <div class="row g-0 justify-content-center">
        <div class="col-md-3 col-6">
          <div class="feature-item">
            <div class="feature-icon">🔍</div>
            <h6>Easy Search</h6>
            <p>Filter by location, capacity, and budget in seconds.</p>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="feature-item">
            <div class="feature-icon">✅</div>
            <h6>Verified Venues</h6>
            <p>Every venue is personally reviewed and verified.</p>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="feature-item">
            <div class="feature-icon">💬</div>
            <h6>Free Enquiry</h6>
            <p>Message venues directly — no booking fees, ever.</p>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="feature-item">
            <div class="feature-icon">🛡️</div>
            <h6>Secure Booking</h6>
            <p>Your event details and payments are always protected.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <section id="about" class="about-section">
    <div class="container">
      <div class="row align-items-center">

        <div class="col-lg-6 mb-5 mb-lg-0 fade-up">
          <div class="about-image-wrap position-relative">
            <div class="about-img-large shadow-lg rounded-4 overflow-hidden"
              style="height: 400px; background: url('https://i.pinimg.com/webp70/736x/ea/12/da/ea12da5040ef7fa4d43f57bdc4465912.webp') center/cover;">
            </div>

            <div class="about-img-small shadow rounded-4 overflow-hidden border border-4 border-white position-absolute"
              style="width: 250px; height: 180px; bottom: -30px; right: -20px; background: url('https://i.pinimg.com/1200x/f1/f1/31/f1f1318bcad18f4924765e5c6db5275c.jpg') center/cover;">
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="about-content fade-up-1">
            <p class="section-eyebrow">Our Story</p>
            <h2 class="section-heading mb-4">Where Every Great Story <span class="text-gold">Finds Its Place</span></h2>
            <p class="text-muted mb-4">
              Founded in the heart of the Philippines, <strong>Tagpo</strong> was born from a simple realization: finding the perfect venue shouldn't be the hardest part of planning an event.
            </p>
            <p class="text-muted">
              We bridge the gap between exclusive spaces and visionary hosts. Whether it's a coastal wedding in Cavite or a high-rise gala in Makati, we curate only the most exceptional locations to ensure your milestones are nothing short of legendary.
            </p>

            <div class="row mt-4">
              <div class="col-6">
                <div class="stat-box">
                  <span class="stat-number">500+</span>
                  <small class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">Verified Venues</small>
                </div>
              </div>
              <div class="col-6">
                <div class="stat-box">
                  <span class="stat-number">12k</span>
                  <small class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">Happy Events</small>
                </div>
              </div>
            </div>

            <a href="#" class="btn-book mt-5">
              Learn More About Us <i class="bi bi-arrow-right ms-2"></i>
            </a>
          </div>
        </div>

      </div>
    </div>
  </section>

  <?php include 'includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    <?php if (isset($_SESSION['current_user']) && !isset($sessionExpired)): ?>
      setTimeout(function() {
        localStorage.removeItem('tagpo_wishlist');

        alert('Your session has expired after 30 minutes. Please log in again.');
        window.location.reload(); //test
      }, 1800000); // 30 minutes
    <?php endif; ?>
  </script>

</body>

</html>