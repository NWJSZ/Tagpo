<?php
require_once 'config/database.php';
require_once 'config/session_config.php';
require_once 'config/app.php';

$baseUrl = getBaseUrl();

// Update activity timestamp
$_SESSION['last_activity'] = time();

// Refresh cookie if logged in
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    setcookie('user_session', $currentUser['email'], time() + (60 * 60 * 24 * 7), '/');
}


$eventTypes = ['Wedding', 'Birthday / Debut', 'Prom / Ball','Corporate Event', 'Reunion', 'Anniversary'];
$guestOptions = [50, 75, 100, 150, 200];

$defaultVenues = [
    [
        'id'       => 1,
        'name'     => 'Paradiso Terrestre',
        'location' => 'Molino, Cavite City',
        'price'    => 35000,
        'cap'      => 500,
        'image'    => 'images/paradiso1.jpg',
    ],
    [
        'id'       => 2,
        'name'     => 'Blue Gardens',
        'location' => 'Makati City, Metro Manila',
        'price'    => 60000,
        'cap'      => 250,
        'image'    => 'images/gardens1.jpg',
    ],
    [
        'id'       => 3,
        'name'     => 'The Green Lounge Events Place',
        'location' => 'Quezon City',
        'price'    => 45000,
        'cap'      => 300,
        'image'    => 'images/lounge1.jpg',
    ],
];

$customVenues = $_SESSION['venues'] ?? [];
$allVenues = array_merge($defaultVenues, $customVenues);

$errorMessage = '';
$name = '';
$date = '';
$type = '';
$guests = 50;
$customerName = '';

// Get venue data from POST (from venue.php) or GET (from index.php)
$venueId = $_POST['venue_id'] ?? $_GET['venue_id'] ?? '';
$venueName = $_POST['venue_name'] ?? $_GET['venue_name'] ?? '';
$venuePrice = $_POST['venue_price'] ?? $_GET['venue_price'] ?? '';
$venueCapacity = $_POST['venue_cap'] ?? $_GET['venue_cap'] ?? 500;

// Pre-fill from POST if coming from venue.php
if (!empty($_POST['venue_id'])) {
    $name = $_POST['guest_name'] ?? $_POST['name'] ?? '';
    $date = $_POST['event_date'] ?? $_POST['date'] ?? '';
    $type = $_POST['event_type'] ?? '';
    $guests = $_POST['guests'] ?? 50;
    $time = $_POST['event_time'] ?? '';
    $duration = $_POST['duration'] ?? '';
}

// Pre-fill from GET when coming from index.php
if (empty($name) && !empty($_GET['name'])) {
    $name = $_GET['name'];
}
if (empty($date) && !empty($_GET['event_date'])) {
    $date = $_GET['event_date'];
}
if (empty($type) && !empty($_GET['event_type'])) {
    $type = $_GET['event_type'];
}
if (empty($guests) && !empty($_GET['guests'])) {
    $guests = $_GET['guests'];
}

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {

        $venue_id   = $_POST['venue_id'] ?? null;
        $venue_name = $_POST['venue_name'] ?? 'Unknown Venue';
        $venue_price = (int)($_POST['venue_price'] ?? 0);

        $name = $_POST['guest_name'] ?? $_POST['name'] ?? 'Guest';
        $date = $_POST['event_date'] ?? $_POST['date'] ?? '';
        $type = $_POST['event_type'] ?? '';
        $guests = $_POST['guests'] ?? 50;
        $time = $_POST['event_time'] ?? '';
        $duration = $_POST['duration'] ?? '';

        if ($date === '' || $type === '' || $guests === '') {
            die("❌ Please complete all booking fields.");
        }

        if (!is_numeric($guests) || (int)$guests < 50) {
            die("❌ Minimum 50 guests required.");
        }

        $guests = (int)$guests;

        // ✅ FINAL PRICE = VENUE ONLY
        $total_price = $venue_price;

        $booking = [
            "name"        => $name,
            "venue"       => $venue_name,
            "date"        => $date,
            "event_type"  => $type,
            "guests"      => $guests,
            "time"        => $time,
            "duration"    => $duration,
            "price"       => $total_price
        ];

        file_put_contents("bookings.json", json_encode($booking) . PHP_EOL, FILE_APPEND);

        $query = http_build_query([
            'venue_id'     => $venue_id,
            'venue_name'   => $venue_name,
            'venue_price'  => $venue_price,
            'event_type'   => $type,
            'date'         => $date,
            'time'         => $time,
            'duration'     => $duration,
            'guests'       => $guests,
            'name'         => $name
        ]);

        header("Location: payment.php?$query");

        echo "BOOKING PAGE REACHED";
        exit;
    }
    ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Book an Event | VENUESUITE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>

<?php include 'includes/header.php'; ?>

<section class="hero-bg text-white d-flex align-items-center" style="background: #273449; min-height: 220px;">
  <div class="container text-center py-5">
    <p class="section-eyebrow text-uppercase mb-3" style="letter-spacing: 0.3em; color: rgba(255,255,255,.7); font-size: 0.8rem;">Secure payment</p>
    <h1 class="display-4 fw-bold mb-3">Complete your booking</h1>
    <p class="lead opacity-75">Finish your reservation for <?php echo htmlspecialchars($venueName ?: 'your chosen venue'); ?></p>
  </div>
</section>

<div class="container py-5">
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card p-4 shadow-sm rounded-4 border-0">
        <h4 class="mb-4">Step 1: Select a Venue</h4>

        <div class="venue-selection-grid mb-5">
          <?php foreach ($allVenues as $v): ?>
            <div class="venue-option-card <?php echo ($venueId == $v['id']) ? 'selected' : ''; ?>" 
                 onclick="selectVenue(this, <?php echo $v['id']; ?>, '<?php echo addslashes($v['name']); ?>', <?php echo $v['price']; ?>, <?php echo $v['cap']; ?>)">
              <div class="venue-option-img" style="background-image: url('<?php echo $v['image']; ?>')"></div>
              <div class="venue-option-info">
                <h6><?php echo htmlspecialchars($v['name']); ?></h6>
                <p class="location">📍 <?php echo htmlspecialchars($v['location']); ?></p>
                <div class="venue-option-meta">
                  <span>🪑 <?php echo number_format($v['cap']); ?> capacity</span>
                  <span class="price">₱<?php echo number_format($v['price']); ?></span>
                </div>
              </div>
              <div class="venue-option-check">✓</div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (!empty($venueName)): ?>
          <div class="alert alert-success mb-4">
            ✓ <strong><?php echo htmlspecialchars($venueName); ?></strong> selected
          </div>
        <?php endif; ?>

        <hr class="my-4">
        <h4 class="mb-4">Step 2: Complete Your Booking</h4>

        <?php if ($errorMessage): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <form method="POST" onsubmit="return validateBooking()">
          <input type="hidden" name="venue_id" id="venueId" value="<?php echo htmlspecialchars($venueId); ?>">
          <input type="hidden" name="venue_name" id="venueName" value="<?php echo htmlspecialchars($venueName); ?>">
          <input type="hidden" name="venue_price" id="venuePrice" value="<?php echo htmlspecialchars($venuePrice); ?>">
          <input type="hidden" name="venue_cap" id="venueCap" value="<?php echo htmlspecialchars($venueCapacity); ?>">
          <input type="hidden" name="confirm_booking" value="1">

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Your Name</label>
              <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" placeholder="John Doe" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Event Date</label>
              <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date); ?>" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Event Type</label>
            <select name="event_type" class="form-select" required>
              <option value="">Select event type</option>
              <?php foreach ($eventTypes as $event): ?>
                <option value="<?php echo htmlspecialchars($event); ?>" <?php echo ($type === $event) ? 'selected' : ''; ?>><?php echo htmlspecialchars($event); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Number of Guests</label>
            <div class="guests-input-wrap">
              <button type="button" class="guests-btn" onclick="adjustBookingGuests(-10)">−</button>
              <input type="number" id="bookingGuestCount" name="guests" value="<?php echo (int)$guests; ?>"
                     min="50" max="999" onchange="validateGuestCount()"/>
              <button type="button" class="guests-btn" onclick="adjustBookingGuests(10)">+</button>
            </div>
            <small class="text-muted d-block mt-2">⚠️ Minimum 50 guests</small>
          </div>

          <button type="submit" class="btn btn-primary btn-lg w-100">Continue to Payment</button>
        </form>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card p-4 shadow-sm rounded-4 border-0 bg-white" style="position: sticky; top: 100px;">
        <h5 class="mb-3">Booking Summary</h5>
        
        <div class="mb-3">
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Venue</span>
            <strong id="summaryVenue"><?php echo $venueName ? htmlspecialchars($venueName) : 'Not selected'; ?></strong>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Event Type</span>
            <strong id="summaryType"><?php echo $type ? htmlspecialchars($type) : 'Not selected'; ?></strong>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Date</span>
            <strong id="summaryDate"><?php echo $date ? htmlspecialchars($date) : 'Not selected'; ?></strong>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Guests</span>
            <strong id="summaryGuests"><?php echo (int)$guests; ?> guests</strong>
          </div>
        </div>

        <div style="border-top: 2px solid #e5e7eb; padding-top: 12px;">
          <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted">Venue Price</span>
            <strong class="h5" id="summaryPrice"><?php echo $venuePrice ? '₱' . number_format($venuePrice) : '—'; ?></strong>
          </div>
        </div>

        <div class="p-3 rounded-4 bg-light mt-3">
          <p class="mb-2"><strong>What happens next?</strong></p>
          <ul class="mb-0 ps-3" style="font-size: 0.9rem;">
            <li>Select your venue from the list</li>
            <li>Fill in event details</li>
            <li>Proceed to secure payment</li>
            <li>Receive instant confirmation</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function selectVenue(el, id, name, price, cap) {
    document.querySelectorAll('.venue-option-card').forEach(card => {
      card.classList.remove('selected');
    });
    el.classList.add('selected');
    
    document.getElementById('venueId').value = id;
    document.getElementById('venueName').value = name;
    document.getElementById('venuePrice').value = price;
    document.getElementById('venueCap').value = cap;
    
    // Update summary
    document.getElementById('summaryVenue').textContent = name;
    document.getElementById('summaryPrice').textContent = '₱' + price.toLocaleString('en-PH');
  }
  
  function adjustBookingGuests(delta) {
    const input = document.getElementById('bookingGuestCount');
    const val = parseInt(input.value) + delta;
    input.value = Math.max(50, Math.min(999, val));
    document.getElementById('summaryGuests').textContent = input.value + ' guests';
  }
  
  function validateGuestCount() {
    const input = document.getElementById('bookingGuestCount');
    if (parseInt(input.value) < 50) {
      input.value = 50;
    }
    document.getElementById('summaryGuests').textContent = input.value + ' guests';
  }
  
  function validateBooking() {
    if (!document.getElementById('venueId').value) {
      alert('❌ Please select a venue');
      return false;
    }
    if (!document.querySelector('input[name="name"]').value) {
      alert('❌ Please enter your name');
      return false;
    }
    if (!document.querySelector('input[name="date"]').value) {
      alert('❌ Please select an event date');
      return false;
    }
    if (!document.querySelector('select[name="event_type"]').value) {
      alert('❌ Please select an event type');
      return false;
    }
    return true;
  }
  
  // Update summary when form fields change
  document.querySelector('input[name="name"]')?.addEventListener('input', function() {
    // Could update summary here if needed
  });
  
  document.querySelector('input[name="date"]')?.addEventListener('change', function() {
    document.getElementById('summaryDate').textContent = this.value || 'Not selected';
  });
  
  document.querySelector('select[name="event_type"]')?.addEventListener('change', function() {
    document.getElementById('summaryType').textContent = this.value || 'Not selected';
  });
</script>
</body>
</html>