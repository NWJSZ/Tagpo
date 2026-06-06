<?php
require_once 'config/session_config.php';

// Update activity
$_SESSION['last_activity'] = time();

// Refresh cookie if logged in
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    setcookie('user_session', $currentUser['email'], time() + (60 * 60 * 24 * 7), '/');
}

$id = $_GET['id'] ?? null;

$hardcoded_venues = [
  [
    'id'       => 1,
    'name'     => 'Paradiso Terrestre',
    'location' => 'Molino, Cavite City',
    'price'    => 35000,
    'cap'      => 500,
    'standing' => 500,
    'catering' => false,
    'rating'   => 4.8,
    'reviews'  => 36,
    'response' => '2 hrs',
    'image'    => 'assets/images/paradiso1.jpg',
    'gallery'  => [
      ['label' => 'Garden Terrace', 'src' => 'assets/images/paradiso1.jpg'],
      ['label' => 'Main Hall',    'src' => 'assets/images/paradiso2.jpg'],
      ['label' => 'Al Fresco',    'src' => 'assets/images/paradiso3.jpg'],
      ['label' => 'Bridal Suite', 'src' => 'assets/images/paradiso4.jpg'],
      ['label' => 'Ballroom',     'src' => 'assets/images/paradiso5.jpg'],
    ],
    'desc'     => 'Paradiso Terrestre is the perfect venue for every occasion — whether you\'re planning a corporate event, celebrating a wedding, or hosting a debut. With its breathtaking ambiance and character-filled spaces, it consistently attracts elegant events of all kinds. Located along the National Highway of Molino, Cavite City, just 10 minutes away from Alabang via the newly constructed Daang Hari & MCX.',
    'why'      => [
      'Multi-functional event space for any occasion',
      'Easy access to public transportation & major highways',
      'Large outdoor and indoor venues available',
      'Just 10 minutes from Alabang via Daang Hari & MCX',
    ],
    'amenities' => [
      ['icon' => '🅿️', 'label' => 'Free Parking'],
      ['icon' => '❄️', 'label' => 'Air Conditioning'],
      ['icon' => '🎤', 'label' => 'Sound System'],
      ['icon' => '💡', 'label' => 'Stage Lighting'],
      ['icon' => '📽️', 'label' => 'Projector & Screen'],
      ['icon' => '♿', 'label' => 'Wheelchair Access'],
      ['icon' => '🛡️', 'label' => '24/7 Security'],
      ['icon' => '📶', 'label' => 'Free Wi-Fi'],
    ],
    'reviews_list' => [
      ['name' => 'Maria Reyes',    'initials' => 'MR', 'color' => '#6366f1', 'date' => 'March 2026 · Wedding', 'rating' => 5, 'text' => 'Absolutely beautiful venue! The outdoor garden was perfect for our wedding ceremony. The staff were attentive and the whole place was immaculate. Highly recommend!'],
      ['name' => 'Juan dela Cruz', 'initials' => 'JD', 'color' => '#10b981', 'date' => 'January 2026 · Corporate Event', 'rating' => 4, 'text' => 'Great venue for our annual company event. Accessible from Metro Manila and the halls are very spacious. The sound system and lighting setup were impressive.'],
    ],
  ],
  [
    'id'       => 2,
    'name'     => 'Blue Gardens',
    'location' => 'Makati City, Metro Manila',
    'price'    => 60000,
    'cap'      => 250,
    'standing' => 300,
    'catering' => true,
    'rating'   => 4.9,
    'reviews'  => 52,
    'response' => '1 hr',
    'image'    => 'assets/images/gardens1.jpg',
    'gallery'  => [
      ['label' => 'Garden Terrace', 'src' => 'assets/images/gardens1.jpg'],
      ['label' => 'Main Hall',    'src' => 'assets/images/gardens2.jpg'],
      ['label' => 'Al Fresco',    'src' => 'assets/images/gardens3.jpg'],
      ['label' => 'Bridal Suite', 'src' => 'assets/images/gardens4.jpg'],
      ['label' => 'Ballroom',     'src' => 'assets/images/gardens5.jpg'],
    ],
    'desc'     => 'Blue Gardens is a luxury indoor ballroom ideal for proms, galas, and formal events. Situated in the heart of Makati, it offers world-class service and an elegant atmosphere that transforms any gathering into an unforgettable experience.',
    'why'      => [
      'Luxury ballroom with premium interior design',
      'In-house catering with curated menus available',
      'Prime Makati location — walking distance from hotels',
      'Dedicated event coordinator for every booking',
    ],
    'amenities' => [
      ['icon' => '🅿️', 'label' => 'Valet Parking'],
      ['icon' => '❄️', 'label' => 'Central Air Conditioning'],
      ['icon' => '🎤', 'label' => 'Professional Sound System'],
      ['icon' => '🍽️', 'label' => 'In-House Catering'],
      ['icon' => '📽️', 'label' => 'LED Wall Display'],
      ['icon' => '♿', 'label' => 'Wheelchair Access'],
      ['icon' => '💐', 'label' => 'Floral Arrangements'],
      ['icon' => '📶', 'label' => 'High-Speed Wi-Fi'],
    ],
    'reviews_list' => [
      ['name' => 'Angela Santos', 'initials' => 'AS', 'color' => '#f59e0b', 'date' => 'February 2026 · Prom Night',  'rating' => 5, 'text' => 'Our prom was absolutely magical here! The ballroom looked stunning and the staff were so accommodating. Everyone had an amazing night!'],
      ['name' => 'Carlo Mendoza', 'initials' => 'CM', 'color' => '#3b82f6', 'date' => 'December 2025 · Gala Dinner', 'rating' => 5, 'text' => 'Top-notch venue for formal events. The food was excellent and the ambiance was perfect. Worth every peso!'],
    ],
  ],
  [
    'id'       => 3,
    'name'     => 'The Green Lounge Events Place',
    'location' => 'Quezon City, Metro Manila',
    'price'    => 45000,
    'cap'      => 300,
    'standing' => 400,
    'catering' => false,
    'rating'   => 4.7,
    'reviews'  => 28,
    'response' => '3 hrs',
    'image'    => 'assets/images/lounge1.jpg',
    'gallery'  => [
      ['label' => 'Garden Terrace', 'src' => 'assets/images/lounge1.jpg'],
      ['label' => 'Main Hall',    'src' => 'assets/images/lounge2.jpg'],
      ['label' => 'Al Fresco',    'src' => 'assets/images/lounge3.jpg'],
      ['label' => 'Bridal Suite', 'src' => 'assets/images/lounge4.jpg'],
      ['label' => 'Ballroom',     'src' => 'assets/images/lounge5.png'],
    ],
    'desc'     => 'The Green Lounge is a modern event space with a minimalist aesthetic and striking architectural lighting. Located in Quezon City, it is perfect for contemporary celebrations, product launches, and intimate gatherings that demand a stylish and versatile backdrop.',
    'why'      => [
      'Modern minimalist design with architectural lighting',
      'Flexible layout — ideal for any event type',
      'Located along a major QC thoroughfare',
      'Ample free parking for up to 100 vehicles',
    ],
    'amenities' => [
      ['icon' => '🅿️', 'label' => 'Free Parking (100 slots)'],
      ['icon' => '❄️', 'label' => 'Industrial AC System'],
      ['icon' => '🎤', 'label' => 'Premium Sound System'],
      ['icon' => '💡', 'label' => 'Architectural Lighting'],
      ['icon' => '📽️', 'label' => 'Twin Projectors'],
      ['icon' => '♿', 'label' => 'Wheelchair Access'],
      ['icon' => '🛡️', 'label' => '24/7 Security'],
      ['icon' => '📶', 'label' => 'Free Wi-Fi'],
    ],
    'reviews_list' => [
      ['name' => 'Bea Villanueva', 'initials' => 'BV', 'color' => '#ec4899', 'date' => 'March 2026 · Birthday Party',   'rating' => 5, 'text' => 'Such a stunning venue! The lighting alone makes it worth it. Everyone kept asking where we had the party. Will definitely book again!'],
      ['name' => 'Paolo Cruz',     'initials' => 'PC', 'color' => '#14b8a6', 'date' => 'November 2025 · Product Launch', 'rating' => 4, 'text' => 'Very professional setup. The space is flexible and the staff helped us configure the layout perfectly for our launch event.'],
    ],
  ],
];

// STEP 3: Kunin yung mga in-add ng Admin mula sa Session
$session_venues = $_SESSION['venues'] ?? [];

// STEP 4: PAGSAMAHIN SILA. 
// Ngayon, ang $venues ay naglalaman na ng original + new venues.
$venues = array_merge($hardcoded_venues, $session_venues);

// STEP 5: Hanapin yung venue base sa ID na nasa URL (?id=xxxx)
$selected = null;
foreach ($venues as $v) {
    if ($v['id'] == $id) {
        $selected = $v;
        break;
    }
}
function stars(float $rating): string {
    $full  = (int) floor($rating);
    $empty = 5 - $full;
    return str_repeat('★', $full) . str_repeat('☆', $empty);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $selected ? htmlspecialchars($selected['name']) . ' | Tagpo' : 'Venue Not Found | Tagpo'; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>

<body>

  <?php include 'includes/header.php'; ?>

  <?php if ($selected): ?>

    <!-- BREADCRUMB -->
    <div class="breadcrumb-bar">
      <div class="container d-flex align-items-center gap-3">

        <a href="index.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
          ← Back
        </a>

        <div>
          <a href="index.php">Home</a>
          <span class="mx-2">/</span>
          <a href="index.php">Venues</a>
          <span class="mx-2">/</span>
          <span><?php echo htmlspecialchars($selected['name']); ?></span>
        </div>

      </div>
    </div>

    <!-- TAB BAR -->
    <div class="tab-bar">
      <div class="tab active" onclick="setTab(this)">Photos</div>
      <div class="tab" onclick="scrollToSection(about)">About</div>
      <div class="tab" onclick="scrollToSection(capacity)">Capacity</div>
      <div class="tab" onclick="scrollToSection(amenities)">Information</div>
      <div class="tab" onclick="scrollToSection(reviews)">Reviews</div>
    </div>

    <!-- GALLERY -->
    <div class="gallery-grid">
      <?php foreach ($selected['gallery'] as $image): ?>
        <div class="gallery-img" data-src="<?php echo htmlspecialchars($image['src']); ?>" onclick="openLightbox(this.dataset.src)">
          <img src="<?php echo htmlspecialchars($image['src']); ?>" alt="<?php echo htmlspecialchars($image['label']); ?>" />
        </div>
      <?php endforeach; ?>
    </div>

    <!-- LIGHTBOX MODAL -->
    <div id="lightbox" class="lightbox" onclick="closeLightbox(event)">
      <div class="lightbox-content">
        <button class="lightbox-close" type="button" aria-label="Close">&times;</button>
        <button class="lightbox-prev" type="button" onclick="prevImage(event)">&#10094;</button>
        <img class="lightbox-img" id="lightboxImg" src="" alt="Gallery image" />
        <button class="lightbox-next" type="button" onclick="nextImage(event)">&#10095;</button>
      </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-wrap">

      <!-- LEFT COLUMN -->
      <div>

        <h1 class="venue-title"><?php echo htmlspecialchars($selected['name']); ?></h1>

        <div class="venue-meta">
          <span>📍 <?php echo htmlspecialchars($selected['location']); ?></span>
          <span>⭐ <?php echo $selected['rating']; ?> (<?php echo $selected['reviews']; ?> reviews)</span>
          <span class="venue-badge-inline green">✓ Verified Venue</span>
          <span class="venue-badge-inline">⚡ Responds within <?php echo htmlspecialchars($selected['response']); ?></span>
        </div>

        <!-- WHY THIS VENUE -->
        <div class="why-card">
          <h4>Why This Venue</h4>
          <ul class="why-list">
            <?php foreach ($selected['why'] as $point): ?>
              <li>
                <div class="check-icon"></div>
                <?php echo htmlspecialchars($point); ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <!-- CAPACITY -->
        <div class="capacity-row" id="capacity">
          <div class="cap-card">
            <div class="cap-icon">🪑</div>
            <div class="cap-label">Seats</div>
            <div class="cap-value"><?php echo number_format($selected['cap']); ?> <span>guests</span></div>
          </div>
          <div class="cap-card">
            <div class="cap-icon">🧍</div>
            <div class="cap-label">Standing</div>
            <div class="cap-value"><?php echo number_format($selected['standing']); ?> <span>guests</span></div>
          </div>
          <div class="cap-card">
            <div class="cap-icon">🍽️</div>
            <div class="cap-label">External Catering</div>
            <?php if ($selected['catering']): ?>
              <div class="cap-value allowed">Allowed</div>
            <?php else: ?>
              <div class="cap-value not-allowed">Not allowed</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ABOUT -->
        <div class="section" id="about">
          <div class="section-title">About This Venue</div>
          <p><?php echo htmlspecialchars($selected['desc']); ?></p>
        </div>

        <!-- AMENITIES -->
        <div class="section" id="amenities">
          <div class="section-title">Amenities &amp; Features</div>
          <div class="amenities-grid">
            <?php foreach ($selected['amenities'] as $a): ?>
              <div class="amenity-item">
                <span><?php echo $a['icon']; ?></span>
                <?php echo htmlspecialchars($a['label']); ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- LOCATION -->
        <div class="section" id="location">
          <div class="section-title">Location</div>
          <div class="map-placeholder">
            <span><?php echo htmlspecialchars($selected['location']); ?></span>
          </div>
          <p class="map-sub">📍 <?php echo htmlspecialchars($selected['location']); ?>, Philippines</p>
        </div>

        <!-- REVIEWS -->
        <div class="section" id="reviews">
          <div class="section-title">Guest Reviews</div>
          <div class="review-summary">
            <div class="rating-big"><?php echo $selected['rating']; ?></div>
            <div>
              <div class="stars"><?php echo stars($selected['rating']); ?></div>
              <div class="review-count">Based on <?php echo $selected['reviews']; ?> reviews</div>
            </div>
          </div>

          <?php foreach ($selected['reviews_list'] as $r): ?>
            <div class="review-card">
              <div class="reviewer">
                <div class="reviewer-avatar" style="background:<?php echo $r['color']; ?>;">
                  <?php echo htmlspecialchars($r['initials']); ?>
                </div>
                <div>
                  <div class="reviewer-name"><?php echo htmlspecialchars($r['name']); ?></div>
                  <div class="reviewer-date"><?php echo htmlspecialchars($r['date']); ?></div>
                </div>
                <div class="stars-sm">
                  <?php echo str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']); ?>
                </div>
              </div>
              <div class="review-text"><?php echo htmlspecialchars($r['text']); ?></div>
            </div>
          <?php endforeach; ?>
        </div>

      </div><!-- /left column -->

      <!-- RIGHT COLUMN: BOOKING CARD -->
      <div>
        <div class="booking-card">
          <h3>₱<?php echo number_format($selected['price']); ?></h3>
          <div class="price-sub">Starting package · Prices vary by event type</div>

          <div class="form-tabs">
            <div class="form-tab active" onclick="switchTab(this)">Event Information </div>
            <!-- <div class="form-tab" onclick="switchTab(this)">Your Details 📌</div> -->
          </div>

          <form action="add_to_cart.php" method="POST">
            <input type="hidden" name="venue_id" value="<?php echo $selected['id']; ?>">
            <input type="hidden" name="venue_name" value="<?php echo htmlspecialchars($selected['name']); ?>">
            <input type="hidden" name="venue_price" value="<?php echo $selected['price']; ?>">

            <input type="hidden" name="name" value="Guest" />

            <div class="form-group">
              <label>Name</label>
              <input type="text" name="guest_name" class="form-control" placeholder="John Doe" required />
            </div>

            <div class="form-group">
              <label>Event Type</label>
              <select class="form-control" name="event_type" required>
                <option value="">Search Event Type</option>
                <option>Wedding</option>
                <option>Birthday / Debut</option>
                <option>Prom / Ball</option>
                <option>Corporate Event</option>
                <option>Reunion</option>
                <option>Anniversary</option>
              </select>
            </div>

            <div class="form-group">
              <label>Event Date</label>
              <input type="date" class="form-control" name="event_date" id="event_date" min="<?php echo date('Y-m-d'); ?>" required />
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Event Time</label>
                <select class="form-control" name="event_time">
                  <option value="">Select Time</option>
                  <option>8:00 AM</option>
                  <option>10:00 AM</option>
                  <option>12:00 PM</option>
                  <option>2:00 PM</option>
                  <option>4:00 PM</option>
                  <option>6:00 PM</option>
                </select>
              </div>

              <div class="form-group">
                <label>Duration</label>
                <select class="form-control" name="duration">
                  <option value="">Hours</option>
                  <option>3 hours</option>
                  <option>4 hours</option>
                  <option>6 hours</option>
                  <option>8 hours</option>
                  <option>Full day</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>Number of Guests</label>
              <div class="guests-input-wrap">
                <button type="button" class="guests-btn" onclick="adjustGuests(-10)">−</button>
                <input type="number" id="guestCount" name="guests"
                  value="50"
                  min="50"
                  max="<?php echo (int)$selected['cap']; ?>" />
                <button type="button" class="guests-btn" onclick="adjustGuests(10)">+</button>
              </div>
            </div>

            <div class="form-group">
              <label>Add-ons</label>

              <div class="addon-item">
                <input type="checkbox" name="addons[]" value="Catering">
                Catering Service (+₱5,000)
              </div>

              <div class="addon-item">
                <input type="checkbox" name="addons[]" value="Photo Booth">
                Photo Booth (+₱3,000)
              </div>

              <div class="addon-item wedding-addon">
                <input type="checkbox" name="addons[]" value="Bridal Car">
                Bridal Car (+₱8,000)
              </div>

              <div class="addon-item prom-addon">
                <input type="checkbox" name="addons[]" value="DJ Booth">
                DJ Booth (+₱4,000)
              </div>
            </div>

            <button type="submit" class="btn-enquire">
              Add to Cart
            </button>

          <button class="btn-wishlist">♡ Save to Wishlist</button>
          <div class="free-note">✓ Free to enquire — no booking fees</div>

          <hr class="divider" />
          <div class="card-footer-links">
            <a href="#">🛡️ Secure booking</a>
            <a href="#">💬 Chat with venue</a>
            <a href="#">📋 View packages</a>
          </div>
        </div>
      </div><!-- /right column -->

    <?php else: ?>

      <!-- VENUE NOT FOUND -->
      <div class="not-found">
        <h2>Venue Not Found</h2>
        <p>The venue you're looking for doesn't exist or may have been removed.</p>
        <a href="index.php">← Back to all venues</a>
      </div>
    </div>

  <?php endif; ?>

  </div><!-- /main wrap -->

  <?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Initialize wishlist button state
  function initWishlist() {
    const venueId = <?php echo $selected['id']; ?>;
    const wishlistBtn = document.querySelector('.btn-wishlist');
    const wishlist = JSON.parse(localStorage.getItem('tagpo_wishlist')) || [];
    
    if (wishlist.includes(venueId)) {
      wishlistBtn.classList.add('active');
      wishlistBtn.innerHTML = '♥ Saved to Wishlist';
    }
    
    wishlistBtn.addEventListener('click', toggleWishlist);
  }

  function toggleWishlist() {
    const venueId = <?php echo isset($selected['id']) ? $selected['id'] : 0; ?>;
    const venueName = '<?php echo htmlspecialchars($selected['name']); ?>';
    const wishlistBtn = document.querySelector('.btn-wishlist');
    
    wishlistBtn.classList.add('loading');
    
    // Get current wishlist
    let wishlist = JSON.parse(localStorage.getItem('tagpo_wishlist')) || [];
    let isAdded = false;
    
    setTimeout(() => {
      if (wishlist.includes(venueId)) {
        // Remove from wishlist
        wishlist = wishlist.filter(id => id !== venueId);
        showToast('Removed from wishlist', false);
        wishlistBtn.innerHTML = '♡ Save to Wishlist';
        wishlistBtn.classList.remove('active');
      } else {
        // Add to wishlist
        wishlist.push(venueId);
        isAdded = true;
        showToast(venueName + ' saved to wishlist!', true);
        wishlistBtn.innerHTML = '♥ Saved to Wishlist';
        wishlistBtn.classList.add('active');
      }
      
      // Save to localStorage
      localStorage.setItem('tagpo_wishlist', JSON.stringify(wishlist));
      wishlistBtn.classList.remove('loading');
    }, 300);
  }

  function showToast(message, isSuccess) {
    const toast = document.createElement('div');
    toast.className = 'wishlist-toast' + (isSuccess ? '' : ' error');
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
      toast.style.animation = 'slideOutRight 0.3s ease forwards';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  function setTab(el) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
  }
  
  function switchTab(el) {
    document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
  }
  
  function adjustGuests(delta) {
    const input = document.getElementById('guestCount');
    const val   = parseInt(input.value) + delta;
    input.value = Math.max(50, Math.min(parseInt(input.max), val));
  }

  function scrollToSection(id) {
    if (!id) {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
      const el = document.getElementById(id);
      if (el) {
        el.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    }
  }

  // LIGHTBOX FUNCTIONS
  let currentImageIndex = 0;
  let galleryImages = [];

  function openLightbox(src) {
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightboxImg');
    
    // Get all gallery images
    galleryImages = Array.from(document.querySelectorAll('.gallery-img')).map(el => el.dataset.src);
    
    // Find current image index
    currentImageIndex = galleryImages.indexOf(src);
    
    lightboxImg.src = src;
    lightbox.classList.add('show');
    document.body.style.overflow = 'hidden'; // Prevent scrolling
  }

  function closeLightbox(event) {
    // Close lightbox - allow close button or clicking outside content
    if (event) {
      // If clicking on lightbox backdrop, allow closing
      if (event.target.id === 'lightbox') {
        // Clicking outside the image area - close
      } else if (!event.target.closest('.lightbox-content') && event.target.id !== 'lightbox') {
        // If it's not inside lightbox-content, don't close (e.g., if it's an arrow button)
        if (!event.target.classList.contains('lightbox-close') && 
            !event.target.classList.contains('lightbox-prev') && 
            !event.target.classList.contains('lightbox-next')) {
          return;
        }
      }
    }
    
    const lightbox = document.getElementById('lightbox');
    lightbox.classList.remove('show');
    document.body.style.overflow = 'auto';
  }

  function nextImage(event) {
    event.stopPropagation();
    currentImageIndex = (currentImageIndex + 1) % galleryImages.length;
    document.getElementById('lightboxImg').src = galleryImages[currentImageIndex];
  }

  function prevImage(event) {
    event.stopPropagation();
    currentImageIndex = (currentImageIndex - 1 + galleryImages.length) % galleryImages.length;
    document.getElementById('lightboxImg').src = galleryImages[currentImageIndex];
  }

  // Close lightbox with Escape key
  document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
      closeLightbox();
    }
  });

  // Close lightbox when clicking the X button
  document.querySelector('.lightbox-close')?.addEventListener('click', closeLightbox);

  // Initialize date input with minimum date (today)
  function initDatePicker() {
    const dateInput = document.getElementById('event_date');
    if (dateInput) {
      const today = new Date();
      const minDate = today.toISOString().split('T')[0];
      dateInput.setAttribute('min', minDate);
      
      // Validate on change to prevent past dates
      dateInput.addEventListener('change', function() {
        const selectedDate = new Date(this.value);
        const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        
        if (selectedDate < todayDate) {
          alert('⚠️ Past dates are not allowed. Please select today or a future date.');
          this.value = '';
        }
      });
      
      // Prevent typing past dates
      dateInput.addEventListener('blur', function() {
        if (this.value) {
          const selectedDate = new Date(this.value);
          const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
          
          if (selectedDate < todayDate) {
            alert('⚠️ Past dates are not allowed. Please select today or a future date.');
            this.value = '';
          }
        }
      });
    }
  }

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', function() {
    initWishlist();
    initDatePicker();
  });
</script>

<style>
  @keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(400px); opacity: 0; }
  }
</style>

</body>
</html>