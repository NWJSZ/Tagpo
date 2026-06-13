<?php
require_once 'config/database.php';
require_once 'config/session_config.php';
require_once 'config/app.php';
require_once 'data.php';

// Update activity
$_SESSION['last_activity'] = time();

// Refresh cookie if logged in
if (isLoggedIn()) {
  $currentUser = getCurrentUser();
  setcookie('user_session', $currentUser['email'], time() + (60 * 60 * 24 * 7), '/');
}


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

// STEP 6: Fetch reviews from database
if ($selected) {
  $venueId = (int) $selected['id'];
  
  // Fetch all reviews for this venue from database
  $reviewsQuery = $conn->prepare(
    "SELECT r.review_id, r.rating, r.review_text, r.review_date, u.first_name, u.last_name 
     FROM reviews r 
     JOIN users u ON r.user_id = u.id 
     WHERE r.venue_id = ? 
     ORDER BY r.review_date DESC"
  );
  $reviewsQuery->bind_param('i', $venueId);
  $reviewsQuery->execute();
  $reviewsResult = $reviewsQuery->get_result();
  
  $dbReviews = [];
  $totalRating = 0;
  $reviewCount = 0;
  
  while ($row = $reviewsResult->fetch_assoc()) {
    $reviewCount++;
    $totalRating += $row['rating'];
    
    $firstName = htmlspecialchars($row['first_name'] ?? '');
    $lastName = htmlspecialchars($row['last_name'] ?? '');
    $fullName = trim($firstName . ' ' . $lastName);
    if (empty($fullName)) {
      $fullName = 'Anonymous';
    }
    
    // Extract initials
    $initials = substr($firstName, 0, 1) . substr($lastName, 0, 1);
    if (strlen($initials) < 2) {
      $initials = substr($fullName, 0, 2);
    }
    $initials = strtoupper($initials);
    
    // Generate consistent color from name
    $colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];
    $colorIndex = (ord($firstName[0] ?? 'A') + ord($lastName[0] ?? 'A')) % count($colors);
    $color = $colors[$colorIndex];
    
    // Format date
    $reviewDate = new DateTime($row['review_date']);
    $formattedDate = $reviewDate->format('F Y');
    
    $dbReviews[] = [
      'name' => $fullName,
      'initials' => $initials,
      'color' => $color,
      'date' => $formattedDate,
      'rating' => (int) $row['rating'],
      'text' => htmlspecialchars($row['review_text'] ?? '')
    ];
  }
  $reviewsQuery->close();
  
  // Update selected venue with database reviews if any exist
  if ($reviewCount > 0) {
    $selected['reviews_list'] = $dbReviews;
    $selected['reviews'] = $reviewCount;
    $selected['rating'] = round($totalRating / $reviewCount, 1);
  }
}

function stars(float $rating): string
{
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
  <style>
    /* Styling for add-on options */
    .addon-group-item {
      padding: 6px 10px;
      margin-bottom: 5px;
      background: rgba(0, 0, 0, 0.02);
      border-radius: 6px;
      transition: background 0.2s ease;
    }

    .addon-group-item:hover {
      background: rgba(0, 0, 0, 0.05);
    }

    .addon-group-item input[type="checkbox"] {
      margin-right: 8px;
      cursor: pointer;
    }

    .addon-group-item label {
      cursor: pointer;
      font-weight: normal;
      width: 100%;
      margin-bottom: 0;
    }

    @keyframes slideOutRight {
      from {
        transform: translateX(0);
        opacity: 1;
      }

      to {
        transform: translateX(400px);
        opacity: 0;
      }
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>

  <?php if ($selected): ?>

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

    <div class="tab-bar">
      <div class="tab active" onclick="setTab(this)">Photos</div>
      <div class="tab" onclick="scrollToSection(about)">About</div>
      <div class="tab" onclick="scrollToSection(capacity)">Capacity</div>
      <div class="tab" onclick="scrollToSection(amenities)">Information</div>
      <div class="tab" onclick="scrollToSection(reviews)">Reviews</div>
    </div>

    <div class="gallery-grid">
      <?php foreach ($selected['gallery'] as $image): ?>
        <div class="gallery-img" data-src="<?php echo htmlspecialchars($image['src']); ?>" onclick="openLightbox(this.dataset.src)">
          <img src="<?php echo htmlspecialchars($image['src']); ?>" alt="<?php echo htmlspecialchars($image['label']); ?>" />
        </div>
      <?php endforeach; ?>
    </div>

    <div id="lightbox" class="lightbox" onclick="closeLightbox(event)">
      <div class="lightbox-content">
        <button class="lightbox-close" type="button" aria-label="Close">&times;</button>
        <button class="lightbox-prev" type="button" onclick="prevImage(event)">&#10094;</button>
        <img class="lightbox-img" id="lightboxImg" src="" alt="Gallery image" />
        <button class="lightbox-next" type="button" onclick="nextImage(event)">&#10095;</button>
      </div>
    </div>

    <div class="main-wrap">

      <div>

        <h1 class="venue-title"><?php echo htmlspecialchars($selected['name']); ?></h1>

        <div class="venue-meta">
          <span>📍 <?php echo htmlspecialchars($selected['location']); ?></span>
          <span>⭐ <?php echo $selected['rating']; ?> (<?php echo $selected['reviews']; ?> reviews)</span>
          <span class="venue-badge-inline green">✓ Verified Venue</span>
          <span class="venue-badge-inline">⚡ Responds within <?php echo htmlspecialchars($selected['response']); ?></span>
        </div>

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

        <div class="section" id="about">
          <div class="section-title">About This Venue</div>
          <p><?php echo htmlspecialchars($selected['desc']); ?></p>
        </div>

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

        <div class="section" id="location">
          <div class="section-title">Location</div>
          <div class="map-placeholder">
            <span><?php echo htmlspecialchars($selected['location']); ?></span>
          </div>
          <p class="map-sub">📍 <?php echo htmlspecialchars($selected['location']); ?>, Philippines</p>
        </div>

        <div class="section" id="reviews">
          <div class="section-title">Guest Reviews</div>
          
          <?php if (!empty($_GET['review']) && $_GET['review'] === 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <strong>✓ Review Submitted!</strong> Your review has been posted successfully. Thank you for your feedback!
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>
          
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

        <div class="review-form mt-4 p-3 border rounded bg-light">

          <h5 class="mb-3">Write a Review</h5>

          <form action="submit_review.php" method="POST">

            <input type="hidden" name="venue_id" value="<?php echo $selected['id']; ?>">

            <div class="mb-2">
              <label>Rating</label>
              <select name="rating" class="form-control" required>
                <option value="">Select rating</option>
                <option value="5">★★★★★ (5)</option>
                <option value="4">★★★★☆ (4)</option>
                <option value="3">★★★☆☆ (3)</option>
                <option value="2">★★☆☆☆ (2)</option>
                <option value="1">★☆☆☆☆ (1)</option>
              </select>
            </div>

            <div class="mb-2">
              <label>Review</label>
              <textarea name="review_text" class="form-control" rows="3" placeholder="Share your experience..." required></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-sm">
              Submit Review
            </button>

          </form>
        </div>

      </div>
      <div>
        <div class="booking-card">
          <h3>₱<?php echo number_format($selected['price']); ?></h3>
          <div class="price-sub">Starting package · Prices vary by event type</div>

          <div class="form-tabs">
            <div class="form-tab active" onclick="switchTab(this)">Event Information </div>
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
              <select class="form-control" name="event_id" required>
                <option value="">Search Event Type</option>
                <option value="Wedding">Wedding</option>
                <option value="Birthday / Debut">Birthday / Debut</option>
                <option value="Prom / Ball">Prom / Ball</option>
                <option value="Corporate Event">Corporate Event</option>
                <option value="Reunion">Reunion</option>
                <option value="Anniversary">Anniversary</option>
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
              <label class="d-block mb-2">Add-ons</label>

              <div id="no-event-message" class="text-muted small">Please select an event type to view available add-ons.</div>

              <div class="addon-group-item wedding-addon" data-event="Wedding" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Catering Service"> Catering Service (+₱8,000)</label>
              </div>
              <div class="addon-group-item wedding-addon" data-event="Wedding" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Bridal Car"> Bridal Car (+₱3,500)</label>
              </div>
              <div class="addon-group-item wedding-addon" data-event="Wedding" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Floral Arrangement Package"> Floral Arrangement Package (+₱2,500)</label>
              </div>
              <div class="addon-group-item wedding-addon" data-event="Wedding" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Wedding Stage Decoration"> Wedding Stage Decoration (+₱4,000)</label>
              </div>
              <div class="addon-group-item wedding-addon" data-event="Wedding" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Photo Booth"> Photo Booth (+₱2,500)</label>
              </div>

              <div class="addon-group-item birthday-addon" data-event="Birthday / Debut" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Catering Service"> Catering Service (+₱6,000)</label>
              </div>
              <div class="addon-group-item birthday-addon" data-event="Birthday / Debut" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Balloon & Themed Setup"> Balloon & Themed Setup (+₱2,000)</label>
              </div>
              <div class="addon-group-item birthday-addon" data-event="Birthday / Debut" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Photo Booth"> Photo Booth (+₱2,500)</label>
              </div>
              <div class="addon-group-item birthday-addon" data-event="Birthday / Debut" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Clown / Event Host"> Clown / Event Host (+₱1,500)</label>
              </div>
              <div class="addon-group-item birthday-addon" data-event="Birthday / Debut" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Cake Styling Setup"> Cake Styling Setup (+₱1,000)</label>
              </div>

              <div class="addon-group-item prom-addon" data-event="Prom / Ball" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="DJ Booth"> DJ Booth (+₱3,000)</label>
              </div>
              <div class="addon-group-item prom-addon" data-event="Prom / Ball" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="LED Lights Setup"> LED Lights Setup (+₱2,500)</label>
              </div>
              <div class="addon-group-item prom-addon" data-event="Prom / Ball" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Red Carpet Entrance Setup"> Red Carpet Entrance Setup (+₱1,500)</label>
              </div>
              <div class="addon-group-item prom-addon" data-event="Prom / Ball" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Photo Booth"> Photo Booth (+₱2,500)</label>
              </div>
              <div class="addon-group-item prom-addon" data-event="Prom / Ball" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Emcee / Host"> Emcee / Host (+₱2,000)</label>
              </div>

              <div class="addon-group-item corporate-addon" data-event="Corporate Event" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Projector & Screen Setup"> Projector & Screen Setup (+₱2,000)</label>
              </div>
              <div class="addon-group-item corporate-addon" data-event="Corporate Event" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Sound System"> Sound System (+₱3,000)</label>
              </div>
              <div class="addon-group-item corporate-addon" data-event="Corporate Event" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Microphones & Stage Setup"> Microphones & Stage Setup (+₱2,500)</label>
              </div>
              <div class="addon-group-item corporate-addon" data-event="Corporate Event" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Coffee Break Catering"> Coffee Break Catering (+₱5,000)</label>
              </div>
              <div class="addon-group-item corporate-addon" data-event="Corporate Event" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="LED Display Wall"> LED Display Wall (+₱8,000)</label>
              </div>

              <div class="addon-group-item reunion-addon" data-event="Reunion" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Buffet Catering"> Buffet Catering (+₱7,000)</label>
              </div>
              <div class="addon-group-item reunion-addon" data-event="Reunion" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Photo Booth"> Photo Booth (+₱2,500)</label>
              </div>
              <div class="addon-group-item reunion-addon" data-event="Reunion" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Memory Slideshow / Projector"> Memory Slideshow / Projector (+₱1,500)</label>
              </div>
              <div class="addon-group-item reunion-addon" data-event="Reunion" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Event Host / Emcee"> Event Host / Emcee (+₱2,000)</label>
              </div>

              <div class="addon-group-item anniversary-addon" data-event="Anniversary" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Romantic Venue Styling"> Romantic Venue Styling (+₱3,000)</label>
              </div>
              <div class="addon-group-item anniversary-addon" data-event="Anniversary" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Floral Arrangement Package"> Floral Arrangement Package (+₱2,000)</label>
              </div>
              <div class="addon-group-item anniversary-addon" data-event="Anniversary" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Candle & Lights Setup"> Candle & Lights Setup (+₱1,500)</label>
              </div>
              <div class="addon-group-item anniversary-addon" data-event="Anniversary" style="display: none;">
                <label><input type="checkbox" name="addons[]" value="Live Acoustic Music"> Live Acoustic Music (+₱5,000)</label>
              </div>
            </div>

            <button type="submit" class="btn-enquire">
              Add to Cart
            </button>

            <div class="free-note">✓ Free to enquire — no booking fees</div>

            <hr class="divider" />
            <div class="card-footer-links">
              <a href="#">🛡️ Secure booking</a>
              <a href="#">💬 Chat with venue</a>
              <a href="#">📋 View packages</a>
            </div>
          </form>
        </div>
      </div><?php else: ?>

      <div class="not-found">
        <h2>Venue Not Found</h2>
        <p>The venue you're looking for doesn't exist or may have been removed.</p>
        <a href="index.php">← Back to all venues</a>
      </div>
    <?php endif; ?>

    </div><?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
        if (!input) return;
        const val = parseInt(input.value) + delta;
        input.value = Math.max(50, Math.min(parseInt(input.max), val));
      }

      function scrollToSection(id) {
        if (!id) {
          window.scrollTo({
            top: 0,
            behavior: 'smooth'
          });
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

        galleryImages = Array.from(document.querySelectorAll('.gallery-img')).map(el => el.dataset.src);
        currentImageIndex = galleryImages.indexOf(src);

        lightboxImg.src = src;
        lightbox.classList.add('show');
        document.body.style.overflow = 'hidden';
      }

      function closeLightbox(event) {
        if (event) {
          if (event.target.id === 'lightbox') {
            // Backdrop click
          } else if (!event.target.closest('.lightbox-content') && event.target.id !== 'lightbox') {
            if (!event.target.classList.contains('lightbox-close') &&
              !event.target.classList.contains('lightbox-prev') &&
              !event.target.classList.contains('lightbox-next')) {
              return;
            }
          }
        }

        const lightbox = document.getElementById('lightbox');
        if (lightbox) {
          lightbox.classList.remove('show');
          document.body.style.overflow = 'auto';
        }
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

      document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
          closeLightbox();
        }
      });

      document.querySelector('.lightbox-close')?.addEventListener('click', closeLightbox);

      // Initialize date input with minimum date (today)
      function initDatePicker() {
        const dateInput = document.getElementById('event_date');
        if (dateInput) {
          const today = new Date();
          const minDate = today.toISOString().split('T')[0];
          dateInput.setAttribute('min', minDate);

          dateInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());

            if (selectedDate < todayDate) {
              alert('⚠️ Past dates are not allowed. Please select today or a future date.');
              this.value = '';
            }
          });

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

      // Dynamic Add-ons Filtering Function
      function initAddonFilters() {
        const eventSelect = document.querySelector('select[name="event_id"]');
        const addonItems = document.querySelectorAll('.addon-group-item');
        const noEventMessage = document.getElementById('no-event-message');

        if (eventSelect) {
          eventSelect.addEventListener('change', function() {
            const selectedEvent = this.value;
            let countVisible = 0;

            addonItems.forEach(item => {
              const checkbox = item.querySelector('input[type="checkbox"]');
              if (checkbox) checkbox.checked = false;

              if (item.getAttribute('data-event') === selectedEvent) {
                item.style.display = 'block';
                countVisible++;
              } else {
                item.style.display = 'none';
              }
            });

            if (noEventMessage) {
              if (countVisible > 0) {
                noEventMessage.style.display = 'none';
              } else {
                noEventMessage.style.display = 'block';
              }
            }
          });
        }
      }

      // Initialize on page load
      document.addEventListener('DOMContentLoaded', function() {
        initDatePicker();
        initAddonFilters();
      });
    </script>

</body>

</html>