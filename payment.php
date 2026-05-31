<?php
require_once 'config/session_config.php';

// Update activity
$_SESSION['last_activity'] = time();

// Refresh cookie
if (isset($_SESSION['current_user'])) {
    setcookie('user_session', $_SESSION['current_user']['email'], time() + (60 * 60 * 24 * 7), '/');
}

// Check if user session expired (no cookie but had session)
if (!isset($_COOKIE['user_session']) && isset($_SESSION['current_user'])) {
    session_destroy();
    $_SESSION = [];
    header("Location: index.php?expired=true");
    exit();
}

// Clear cart if session expired
if (!isset($_COOKIE['user_session']) && isset($_SESSION['cart'])) {
    unset($_SESSION['cart']);
}

/* =========================================
   DATA FROM VENUE.PHP (POST) OR BOOKING1.PHP (GET) OR CART.PHP (POST)
========================================= */
$venueId     = $_POST['venue_id'] ?? $_GET['venue_id'] ?? 1;
$venueName   = $_POST['venue_name'] ?? $_GET['venue_name'] ?? 'Paradiso Terrestre';
$venuePrice  = (int) ($_POST['venue_price'] ?? $_GET['venue_price'] ?? 35000);
$eventType   = $_POST['event_type'] ?? $_GET['event_type'] ?? '';
$eventDate   = $_POST['event_date'] ?? $_GET['date'] ?? date('Y-m-d');
$eventTime   = $_POST['event_time'] ?? $_GET['time'] ?? '18:00';
$duration    = $_POST['duration'] ?? $_GET['duration'] ?? '4 hours';
$guestCount  = (int) ($_POST['guests'] ?? $_GET['guests'] ?? 50);
$customerName = $_POST['guest_name'] ?? $_POST['name'] ?? $_GET['name'] ?? 'Guest';
$addons = $_POST['addons'] ?? $_GET['addons'] ?? [];

/* =========================
   NUMERIC ARRAY & CALCULATION
========================= */
$fees = [$venuePrice]; 
$feeLabels = ["Venue Price"];

// Guest Count Fee - more than 100 guests
$guestCountFee = 0;
if ($guestCount > 100) {
    $guestCountFee = 5000;
    $fees[] = $guestCountFee;
    $feeLabels[] = "Guest Count Fee (>100 pax)";
}

// Full Day Fee
$fullDayFee = 0;
if (strtolower($duration) === 'full day') {
    $fullDayFee = 10000;
    $fees[] = $fullDayFee;
    $feeLabels[] = "Full Day Fee";
}

// Add-ons Fees
$addonsFees = [];
if (!empty($addons)) {
    foreach ($addons as $addon) {
        $addonPrice = 0;
        if ($addon === "Catering") {
            $addonPrice = 5000;
        } elseif ($addon === "Photo Booth") {
            $addonPrice = 3000;
        } elseif ($addon === "DJ Booth") {
            $addonPrice = 4000;
        }
        if ($addonPrice > 0) {
            $fees[] = $addonPrice;
            $feeLabels[] = "$addon Add-on";
            $addonsFees[$addon] = $addonPrice;
        }
    }
}

$total = 0;
$i = 0;
for ($i = 0; $i < count($fees); $i++) {
    $total += $fees[$i];
}

$breakdown = [
    "Venue: $venueName",
    "Event Type: $eventType",
    "Date: $eventDate",
    "Time: $eventTime",
    "Duration: $duration",
    "Guests: $guestCount pax"
];

if (!empty($addons)) {
    $breakdown[] = "Add-ons: " . implode(", ", $addons);
}

foreach ($fees as $index => $value) {
    $breakdown[] = $feeLabels[$index] . ": ₱" . number_format($value);
}

/* =========================
   DO WHILE (SIMULATION)
========================= */
$attempt = 1;
do {
    $processStatus = "Payment attempt #$attempt initialized";
    $attempt++;
} while ($attempt < 2);

/* =========================
   OOP CLASS
========================= */
class Payment {
    private $firstName, $lastName, $email, $method, $card;

    public function __construct($firstName, $lastName, $email) {
        $this->firstName = $firstName;
        $this->lastName  = $lastName;
        $this->email     = $email;
    }

    // SETTERS
    public function setFirstName($fn)  { $this->firstName = $fn; }
    public function setLastName($ln)   { $this->lastName  = $ln; }
    public function setEmail($email)   { $this->email     = $email; }
    public function setMethod($method) { $this->method    = $method; }
    public function setCard($card)     { $this->card      = $card; }

    // GETTERS
    public function getCardLast4() {
        return !empty($this->card) ? substr($this->card, -4) : "N/A";
    }

    public function getSummary($total, $venueName, $venuePrice, $customerName) {
        return $customerName . " has successfully booked " . $venueName . 
               " (₱" . number_format($venuePrice) . ") for ₱" . number_format($total);
    }
}


$methodMsg = "";

/* =========================================
   FORM PROCESSING
========================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['pay_now'])) {

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $method    = $_POST['method'] ?? 'card';
    
    if (empty($phone)) {
        die("Phone number is required.");
    }

    // Validate based on payment method
    $cardRaw = '';
    if ($method === 'card') {
        $cardRaw = $_POST['card_number'] ?? '';
        $card = str_replace(' ', '', $cardRaw);
        
        if (empty($card) || !is_numeric($card)) {
            die("Card number required for card payment.");
        }
        if (empty($_POST['expiry'])) {
            die("Expiry date required for card payment.");
        }
        if (empty($_POST['cvv'])) {
            die("CVV required for card payment.");
        }
    } else if ($method === 'gcash') {
        if (empty($_POST['gcash_name'])) {
            die("GCash account name required.");
        }
        if (empty($_POST['gcash_number'])) {
            die("GCash number required.");
        }
    } else if ($method === 'paypal') {
        if (empty($_POST['paypal_email'])) {
            die("PayPal email required.");
        }
        if (empty($_POST['paypal_number'])) {
            die("PayPal account number required.");
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email format.");
    }

    switch ($method) {
        case "card":   $methodMsg = "Credit Card Payment"; break;
        case "gcash":  $methodMsg = "GCash Payment"; break;
        case "paypal": $methodMsg = "PayPal Payment"; break;
        default:       $methodMsg = "Unknown Method";
    }

    $payment = new Payment($firstName, $lastName, $email);
    $payment->setMethod($method);
    $payment->setCard($cardRaw ?? '');

    $_SESSION['payment'] = [
        "summary" => $payment->getSummary($total, $venueName, $venuePrice, $customerName),
        "card" => ($method === "card") ? $payment->getCardLast4() : null,
        "method" => $methodMsg
    ];

    $_SESSION['receipt_data'] = [
        'invoice_number' => strtoupper(uniqid('TP-')),
        'customer_name' => $customerName,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone,
        'venue_name' => $venueName,
        'venue_price' => $venuePrice,
        'event_type' => $eventType,
        'event_date' => $eventDate,
        'event_time' => $eventTime,
        'duration' => $duration,
        'guest_count' => $guestCount,
        'addons' => $addons,
        'fee_labels' => $feeLabels,
        'fees' => $fees,
        'total' => $total,
        'payment_method' => $methodMsg,
        'card_last4' => ($method === 'card') ? $payment->getCardLast4() : null,
        'timestamp' => time()
    ];

    header('Location: ' . getBaseUrl() . 'receipt.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payment | VENUESUITE</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/styles.css"/>
</head>
<body>

<?php include 'includes/header.php'; ?>

<section class="hero-bg text-white py-5" style="background: #2c3e50;">
  <div class="container text-center">
    <p class="section-eyebrow text-uppercase">Secure payment</p>
    <h1 class="display-5 fw-bold">Complete your booking</h1>
    <p class="lead opacity-75">Finish your reservation for <?= htmlspecialchars($venueName) ?> - ₱<?= number_format($venuePrice) ?> (<?= htmlspecialchars($customerName) ?>)</p>
  </div>
</section>

<main class="container my-5">
  <div class="row g-5">

    <div class="col-lg-6">
      <div class="card p-4 shadow-sm border-0">
        <h4 class="fw-bold mb-4">Booking Details</h4>
        
        <div class="p-3 mb-4" style="background: #f8f9fa; border-left: 5px solid #0d6efd; border-radius: 5px;">
            <h5 class="fw-bold"><?= htmlspecialchars($venueName) ?> - ₱<?= number_format($venuePrice) ?></h5>
            <p class="mb-1 text-muted small">Customer: <?= htmlspecialchars($customerName) ?></p>
            <p class="mb-1 text-muted small">Event: <?= htmlspecialchars($eventType) ?></p>
            <p class="mb-1 text-muted small">Schedule: <?= htmlspecialchars($eventDate) ?> at <?= htmlspecialchars($eventTime) ?> (<?= htmlspecialchars($duration) ?>)</p>
            <p class="mb-0 text-muted small">Capacity: <?= $guestCount ?> Guests</p>
        </div>

        <h5 class="fw-bold">Payment Breakdown</h5>
        <ul class="list-unstyled">
          <?php foreach ($breakdown as $item): ?>
            <li class="py-1 border-bottom d-flex justify-content-between">
                <span><?= strpos($item, ':') !== false ? explode(':', $item)[0] : $item ?></span>
                <span class="fw-bold"><?= strpos($item, ':') !== false ? explode(':', $item)[1] : '' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="h5">Total to Pay</span>
            <span class="h4 fw-bold text-primary">₱<?= number_format($total); ?></span>
        </div>
      </div>

      <?php if (isset($_SESSION['payment'])): ?>
        <div class="alert alert-success mt-4 shadow-sm">
          <h5 class="alert-heading">Booking Confirmed!</h5>
          <p class="mb-2"><strong>Venue:</strong> <?= htmlspecialchars($venueName); ?> (₱<?= number_format($venuePrice); ?>)</p>
          <p class="mb-2"><strong>Customer:</strong> <?= htmlspecialchars($customerName); ?></p>
          <p class="mb-2"><strong>Event:</strong> <?= htmlspecialchars($eventType); ?> on <?= htmlspecialchars($eventDate); ?> at <?= htmlspecialchars($eventTime); ?></p>
          <p class="mb-0"><strong>Total Paid:</strong> ₱<?= number_format($total); ?> via <?= $_SESSION['payment']['method']; ?></p>
          <?php if (!empty($_SESSION['payment']['card'])): ?>
            <small>Card ending in: ****<?= $_SESSION['payment']['card']; ?></small>
          <?php endif; ?>

          <div class="mt-3">
            <a href="<?= htmlspecialchars(getBaseUrl() . 'receipt.php'); ?>" class="btn btn-outline-primary btn-sm">View Receipt</a>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-lg-6">
      <div class="card p-4 shadow-sm">
        <h3 class="mb-4">Checkout</h3>

        <form method="POST">
          <input type="hidden" name="venue_id" value="<?= $venueId ?>">
          <input type="hidden" name="venue_name" value="<?= htmlspecialchars($venueName) ?>">
          <input type="hidden" name="venue_price" value="<?= $venuePrice ?>">
          <input type="hidden" name="event_type" value="<?= htmlspecialchars($eventType) ?>">
          <input type="hidden" name="event_date" value="<?= htmlspecialchars($eventDate) ?>">
          <input type="hidden" name="event_time" value="<?= htmlspecialchars($eventTime) ?>">
          <input type="hidden" name="duration" value="<?= htmlspecialchars($duration) ?>">
          <input type="hidden" name="guests" value="<?= $guestCount ?>">
          <input type="hidden" name="customer_name" value="<?= htmlspecialchars($customerName) ?>">
          <?php if (!empty($addons)): ?>
            <?php foreach ($addons as $addon): ?>
              <input type="hidden" name="addons[]" value="<?= htmlspecialchars($addon) ?>">
            <?php endforeach; ?>
          <?php endif; ?>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">First Name</label>
              <input type="text" name="first_name" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Last Name</label>
              <input type="text" name="last_name" class="form-control" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <div class="input-group">
                <span class="input-group-text">+63</span>
                <input type="text" name="phone" id="phone_input" class="form-control" placeholder="9XX XXX XXXX" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Payment Method</label>
            <select name="method" id="method_select" class="form-select" onchange="updatePaymentFields()">
              <option value="card">Credit Card</option>
              <option value="gcash">GCash</option>
              <option value="paypal">PayPal</option>
            </select>
          </div>

          <!-- CREDIT CARD SECTION -->
          <div id="card_section" class="payment-section">
            <div class="mb-3">
              <label class="form-label">Card Number</label>
              <input type="text" name="card_number" id="card_number" class="form-control" placeholder="0000 0000 0000 0000">
            </div>
            <div class="row">
              <div class="col-6 mb-3">
                <label class="form-label">Expiry Date</label>
                <input type="text" name="expiry" id="expiry" class="form-control" placeholder="MM/YY">
              </div>
              <div class="col-6 mb-3">
                <label class="form-label">CVV</label>
                <input type="text" name="cvv" id="cvv" class="form-control" placeholder="123">
              </div>
            </div>
          </div>

          <!-- GCASH SECTION -->
          <div id="gcash_section" class="payment-section" style="display: none;">
            <div class="mb-3">
              <label class="form-label">GCash Account Name</label>
              <input type="text" name="gcash_name" id="gcash_name" class="form-control" placeholder="Full Name">
            </div>
            <div class="mb-3">
              <label class="form-label">GCash Number</label>
              <input type="text" name="gcash_number" id="gcash_number" class="form-control" placeholder="09XX XXX XXXX">
            </div>
          </div>

          <!-- PAYPAL SECTION -->
          <div id="paypal_section" class="payment-section" style="display: none;">
            <div class="mb-3">
              <label class="form-label">PayPal Email</label>
              <input type="email" name="paypal_email" id="paypal_email" class="form-control" placeholder="your@email.com">
            </div>
            <div class="mb-3">
              <label class="form-label">PayPal Account Number</label>
              <input type="text" name="paypal_number" id="paypal_number" class="form-control" placeholder="Account number">
            </div>
          </div>

          <button type="submit" name="pay_now" class="btn btn-primary w-100 py-3 fw-bold mt-2">
            Pay ₱<?= number_format($total); ?> Now
          </button>

          <p class="text-center text-muted small mt-3"><?= $processStatus; ?></p>
        </form>
      </div>
    </div>

  </div>
</main>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/payment.js"></script>

</body>
</html>