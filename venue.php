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

// Kunin ang id mula sa URL parameter (?id=xxx)
$id = $_GET['id'] ?? null;

// STEP 3: Kunin yung mga in-add ng Admin mula sa Session
$session_venues = $_SESSION['venues'] ?? [];

// STEP 4: PAGSAMAHIN SILA. 
$venues = array_merge($hardcoded_venues, $session_venues);

// STEP 5: Hanapin yung venue base sa ID na nasa URL (?id=xxxx)
$selected = null;
foreach ($venues as $v) {
  if ($v['id'] == $id) {
    $selected = $v;
    break;
  }
}

// STEP 6: Fetch reviews from database using correct first_name and last_name columns
if ($selected) {
  $venueId = (int) $selected['id'];
  
  $reviewsQuery = $conn->prepare(
    "SELECT r.review_id, r.rating, r.review_text, r.review_date, u.first_name, u.last_name 
     FROM reviews r 
     LEFT JOIN users u ON r.user_id = u.id 
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
      $fullName = 'Anonymous Guest';
    }
    
    // Extract initials safely
    $initials = substr($firstName, 0, 1) . substr($lastName, 0, 1);
    if (strlen($initials) < 2) {
      $initials = substr($fullName, 0, 2);
    }
    $initials = strtoupper($initials);
    if (empty($initials)) {
      $initials = "AG";
    }
    
    // Generate consistent color from first_name
    $colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];
    $colorIndex = ord($firstName[0] ?? 'A') % count($colors);
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
  
  // Update selected venue structure for HTML display
  if ($reviewCount > 0) {
    $selected['reviews_list'] = $dbReviews;
    $selected['reviews'] = $reviewCount;
    $selected['rating'] = round($totalRating / $reviewCount, 1);
  } else {
    $selected['reviews_list'] = [];
    $selected['reviews'] = 0;
    $selected['rating'] = 0.0; 
  }
}

$venueEventOptions = [];
if ($selected) {
  $venueId = (int) $selected['id'];
  $stmt = $conn->prepare(
    "SELECT e.event_id, e.event_name
     FROM venue_events ve
     JOIN event e ON ve.event_id = e.event_id
     WHERE ve.venue_id = ?
       AND e.archived = 0
     ORDER BY e.event_name ASC"
  );
  $stmt->bind_param('i', $venueId);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $venueEventOptions[] = $row;
  }
  $stmt->close();

  if (empty($venueEventOptions)) {
    $venueEventOptions = $conn->query("SELECT event_id, event_name FROM event WHERE archived = 0 ORDER BY event_name ASC")->fetch_all(MYSQLI_ASSOC);
  }
}

function stars(float $rating): string
{
  $full  = (int) floor($rating);
  $empty = 5 - $full;
  return str_repeat('★', $full) . str_repeat('☆', $empty);
}

$addonsQuery = $conn->query(
    "SELECT a.addon_id, a.addon_name, a.price, e.event_name 
     FROM addons a 
     JOIN event e ON a.event_id = e.event_id 
     WHERE a.archived = 0 
     ORDER BY a.addon_name ASC"
);
$dbAddons = [];
if ($addonsQuery) {
    while ($row = $addonsQuery->fetch_assoc()) {
        $dbAddons[] = $row;
    }
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
      <div class="tab active" onclick="setTab(this); scrollToSection('')">Photos</div>
      <div class="tab" onclick="setTab(this); scrollToSection('about')">About</div>
      <div class="tab" onclick="setTab(this); scrollToSection('capacity')">Capacity</div>
      <div class="tab" onclick="setTab(this); scrollToSection('amenities')">Information</div>
      <div class="tab" onclick="setTab(this); scrollToSection('reviews')">Reviews</div>
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
          <span>
            <?php if ($selected['reviews'] > 0): ?>
              ⭐ <?php echo $selected['rating']; ?> (<?php echo $selected['reviews']; ?> reviews)
            <?php else: ?>
              ⭐ No reviews yet
            <?php endif; ?>
          </span>
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
            <?php 
            $db_amenities = [];
            if (isset($conn) && $selected) {
                $v_id = (int)$selected['id'];
                $amQuery = $conn->prepare("SELECT amenity_name FROM amenities WHERE venue_id = ?");
                $amQuery->bind_param('i', $v_id);
                $amQuery->execute();
                $amResult = $amQuery->get_result();
                while ($row = $amResult->fetch_assoc()) {
                    $db_amenities[] = $row;
                }
                $amQuery->close();
            }

            $amenities_to_loop = !empty($db_amenities) ? $db_amenities : ($selected['amenities'] ?? []);

            if (!empty($amenities_to_loop)): 
              foreach ($amenities_to_loop as $a): 
                $raw_amenity = $a['amenity_name'] ?? $a['label'] ?? $a['name'] ?? '';
                
                if (!empty($raw_amenity)):
                  $parts = explode('|', $raw_amenity);
                  $icon  = isset($parts[1]) ? trim($parts[0]) : '✨'; 
                  $label = isset($parts[1]) ? trim($parts[1]) : trim($parts[0]);
            ?>
                  <div class="amenity-item">
                    <?php echo htmlspecialchars($label); ?>
                  </div>
            <?php 
                endif;
              endforeach; 
            else: 
            ?>
              <p class="text-muted">No amenities listed for this venue.</p>
            <?php endif; ?>
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
            <?php if ($selected['reviews'] > 0): ?>
              <div class="rating-big"><?php echo $selected['rating']; ?></div>
              <div>
                <div class="stars"><?php echo stars($selected['rating']); ?></div>
                <div class="review-count">Based on <?php echo $selected['reviews']; ?> reviews</div>
              </div>
            <?php else: ?>
              <div class="rating-big">—</div>
              <div>
                <div class="stars" style="color: #ccc;"><?php echo stars(0); ?></div>
                <div class="review-count">No reviews yet</div>
              </div>
            <?php endif; ?>
          </div>

          <?php if (empty($selected['reviews_list'])): ?>
            <p class="text-muted text-center my-4">Be the first to share your experience about this venue!</p>
          <?php else: ?>
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
          <?php endif; ?>
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
                <?php foreach ($venueEventOptions as $evt): ?>
                  <option value="<?= (int)$evt['event_id'] ?>"><?= htmlspecialchars($evt['event_name']) ?></option>
                <?php endforeach; ?>
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

  <?php if (!empty($dbAddons)): ?>
    <?php foreach ($dbAddons as $addon): ?>
      <div class="addon-group-item dynamic-addon" data-event="<?php echo htmlspecialchars($addon['event_name']); ?>" style="display: none;">
        <label>
          <input type="checkbox" name="addons[]" value="<?php echo htmlspecialchars($addon['addon_name']); ?>"> 
          <?php echo htmlspecialchars($addon['addon_name']); ?> (+₱<?php echo number_format($addon['price']); ?>)
        </label>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="text-muted small">No add-ons available.</div>
  <?php endif; ?>
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
      </div>
    
    <?php else: ?>
      <div class="not-found">
        <h2>Venue Not Found</h2>
        <p>The venue you're looking for doesn't exist or may have been removed.</p>
        <a href="index.php">← Back to all venues</a>
      </div>
    <?php endif; ?>

  </div>

  <?php include 'includes/footer.php'; ?>

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
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        const el = document.getElementById(id);
        if (el) {
          const yOffset = -140; 
          const y = el.getBoundingClientRect().top + window.pageYOffset + yOffset;
          window.scrollTo({ top: y, behavior: 'smooth' });
        }
      }
    }

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
      }
    }

    function initAddonFilters() {
      const eventSelect = document.querySelector('select[name="event_id"]');
      // FIX: Ginawa nating '.dynamic-addon' para sigurado nating ang database items ang mahila
      const addonItems = document.querySelectorAll('.dynamic-addon');
      const noEventMessage = document.getElementById('no-event-message');

      if (eventSelect) {
        eventSelect.addEventListener('change', function() {
          // .trim() para walang whitespace error sa pag-compare ng String text
          const selectedEventName = this.selectedOptions[0]?.textContent ? this.selectedOptions[0].textContent.trim() : '';
          let countVisible = 0;

          addonItems.forEach(item => {
            const checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.checked = false;

            // FIX: Gumamit ng .trim() para kung "Wedding " ang nasa DB, tutugma pa rin sa UI
            if (item.getAttribute('data-event').trim() === selectedEventName) {
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

    document.addEventListener('DOMContentLoaded', function() {
      initDatePicker();
      initAddonFilters();

      const bookingForm = document.querySelector('form[action="add_to_cart.php"]');
      
      if (bookingForm) {
        const eventSelect = document.querySelector('select[name="event_id"]');
        const dateInput   = document.getElementById('event_date');
        const timeSelect  = document.querySelector('select[name="event_time"]');
        const duration    = document.querySelector('select[name="duration"]');
        const guestInput  = document.getElementById('guestCount');

        function clearFieldError(element) {
          if (element) {
            element.classList.remove('is-invalid');
            const errorDiv = element.parentNode.querySelector('.invalid-feedback');
            if (errorDiv) errorDiv.remove();
          }
        }

        function showFieldError(element, message) {
          if (element) {
            clearFieldError(element);
            element.classList.add('is-invalid');
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback';
            errorDiv.style.display = 'block';
            errorDiv.style.fontSize = '0.8rem';
            errorDiv.style.marginTop = '4px';
            errorDiv.innerText = message;
            
            element.parentNode.appendChild(errorDiv);
          }
        }

        if (eventSelect) eventSelect.addEventListener('change', () => { if(eventSelect.value !== "") clearFieldError(eventSelect); });
        if (dateInput)   dateInput.addEventListener('change', () => { if(dateInput.value !== "") clearFieldError(dateInput); });
        if (timeSelect)  timeSelect.addEventListener('change', () => { if(timeSelect.value !== "") clearFieldError(timeSelect); });
        if (duration)    duration.addEventListener('change', () => { if(duration.value !== "") clearFieldError(duration); });
        if (guestInput)  guestInput.addEventListener('input', () => { if(guestInput.value !== "" && parseInt(guestInput.value) >= 1) clearFieldError(guestInput); });

        bookingForm.addEventListener('submit', function(event) {
          let hasError = false;

          if (!eventSelect || eventSelect.value === "") {
            showFieldError(eventSelect, "⚠️ Please select an event type.");
            hasError = true;
          }
          if (!dateInput || dateInput.value === "") {
            showFieldError(dateInput, "⚠️ Please select an event date.");
            hasError = true;
          }
          if (!timeSelect || timeSelect.value === "") {
            showFieldError(timeSelect, "⚠️ Please select a starting time.");
            hasError = true;
          }
          if (!duration || duration.value === "") {
            showFieldError(duration, "⚠️ Please select a package duration.");
            hasError = true;
          }
          if (!guestInput || guestInput.value === "" || parseInt(guestInput.value) < 1) {
            showFieldError(guestInput, "⚠️ Please specify a valid guest count.");
            hasError = true;
          }

          if (hasError) {
            event.preventDefault();
            const firstInvalid = bookingForm.querySelector('.is-invalid');
            if (firstInvalid) {
              firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
          }
        });
      }
    });
</script>
</body>
</html>