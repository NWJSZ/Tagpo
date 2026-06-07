<?php
require_once 'config/database.php';
require_once 'config/session_config.php';
require_once 'config/app.php';

$baseUrl = getBaseUrl();

// Update activity
$_SESSION['last_activity'] = time();

// Refresh cookie
if (isset($_SESSION['current_user'])) {
    setcookie('user_session', $_SESSION['current_user']['email'], time() + (60 * 60 * 24 * 7), '/');
}

// Check if user session expired
if (!isset($_COOKIE['user_session']) && isset($_SESSION['current_user'])) {
    session_destroy();
    $_SESSION = [];
    header("Location: index.php?expired=true");
    exit();
}

/* ==========================================================
   DYNAMIC CART DATA BINDING
   ========================================================== */
$venueNamesArray = [];
$venuePrice = 0;
$eventType = '';
$eventDate = '';
$eventTime = '';
$duration = '';
$guestCount = 0;
$addons = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
    
    foreach ($_POST['selected_items'] as $index) {
        if (isset($_SESSION['cart'][$index])) {
            $item = $_SESSION['cart'][$index];
            
            $venueNamesArray[] = $item['venue_name'];
            $venuePrice += (int)($item['venue_price'] ?? 35000);
            
            if (empty($eventType)) $eventType = $item['event_type'] ?? 'General Event';
            if (empty($eventDate)) $eventDate = $item['event_date'] ?? date('Y-m-d');
            if (empty($eventTime)) $eventTime = $item['event_time'] ?? '18:00';
            if (empty($duration)) $duration = $item['duration'] ?? '4 hours';
            
            $guestCount += (int)($item['guests'] ?? 50);
            
            if (!empty($item['addons']) && is_array($item['addons'])) {
                $addons = array_merge($addons, $item['addons']);
            }
        }
    }
    
    $venueName = implode(", ", $venueNamesArray);
    $addons = array_unique($addons);

} else {
    $venueName   = $_POST['venue_name'] ?? $_GET['venue_name'] ?? 'Paradiso Terrestre';
    $venuePrice  = (int) ($_POST['venue_price'] ?? $_GET['venue_price'] ?? 35000);
    $eventType   = $_POST['event_type'] ?? $_GET['event_type'] ?? 'General Event';
    $eventDate   = $_POST['event_date'] ?? $_GET['date'] ?? date('Y-m-d');
    $eventTime   = $_POST['event_time'] ?? $_GET['time'] ?? '18:00';
    $duration    = $_POST['duration'] ?? $_GET['duration'] ?? '4 hours';
    $guestCount  = (int) ($_POST['guests'] ?? $_GET['guests'] ?? 50);
    $addons      = $_POST['addons'] ?? $_GET['addons'] ?? [];
}

$customerName = $_SESSION['current_user']['name'] ?? $_POST['guest_name'] ?? $_GET['name'] ?? 'Guest';

/* =========================================
   NUMERIC ARRAY & CALCULATION WITH PRICING
   ========================================= */
$fees = [$venuePrice]; 
$feeLabels = ["Venue Price"];

// Guest Count Fee - higit sa 100 guests
if ($guestCount > 100) {
    $fees[] = 5000;
    $feeLabels[] = "Guest Count Fee (>100 pax)";
}

// Full Day Fee
if (strtolower($duration) === 'full day') {
    $fees[] = 10000;
    $feeLabels[] = "Full Day Fee";
}

// Add-ons Fees calculation base sa package rules mo
if (!empty($addons)) {
    foreach ($addons as $addon) {
        $addonPrice = 0;
        
        // Dynamic matching depende sa Event Type at Add-on Name
        if ($eventType === 'Wedding') {
            if ($addon === "Catering Service" || $addon === "Catering") $addonPrice = 8000;
            elseif ($addon === "Bridal Car") $addonPrice = 3500;
            elseif ($addon === "Floral Arrangement Package") $addonPrice = 2500;
            elseif ($addon === "Wedding Stage Decoration") $addonPrice = 4000;
            elseif ($addon === "Photo Booth") $addonPrice = 2500;
        } elseif ($eventType === 'Birthday' || $eventType === 'Birthday / Debut') {
            if ($addon === "Catering Service" || $addon === "Catering") $addonPrice = 6000;
            elseif ($addon === "Balloon & Themed Setup") $addonPrice = 2000;
            elseif ($addon === "Photo Booth") $addonPrice = 2500;
            elseif ($addon === "Clown / Event Host") $addonPrice = 1500;
            elseif ($addon === "Cake Styling Setup") $addonPrice = 1000;
        } elseif ($eventType === 'Prom/Ball' || $eventType === 'Prom / Ball') {
            if ($addon === "DJ Booth") $addonPrice = 3000;
            elseif ($addon === "LED Lights Setup") $addonPrice = 2500;
            elseif ($addon === "Red Carpet Entrance Setup") $addonPrice = 1500;
            elseif ($addon === "Photo Booth") $addonPrice = 2500;
            elseif ($addon === "Emcee / Host") $addonPrice = 2000;
        } elseif ($eventType === 'Corporate Event') {
            if ($addon === "Projector & Screen Setup") $addonPrice = 2000;
            elseif ($addon === "Sound System") $addonPrice = 3000;
            elseif ($addon === "Microphones & Stage Setup") $addonPrice = 2500;
            elseif ($addon === "Coffee Break Catering") $addonPrice = 5000;
            elseif ($addon === "LED Display Wall") $addonPrice = 8000;
        } elseif ($eventType === 'Reunion') {
            if ($addon === "Buffet Catering" || $addon === "Catering") $addonPrice = 7000;
            elseif ($addon === "Photo Booth") $addonPrice = 2500;
            elseif ($addon === "Memory Slideshow / Projector") $addonPrice = 1500;
            elseif ($addon === "Event Host / Emcee") $addonPrice = 2000;
        } elseif ($eventType === 'Anniversary') {
            if ($addon === "Romantic Venue Styling") $addonPrice = 3000;
            elseif ($addon === "Floral Arrangement Package") $addonPrice = 2000;
            elseif ($addon === "Candle & Lights Setup") $addonPrice = 1500;
            elseif ($addon === "Live Acoustic Music") $addonPrice = 5000;
        }

        // Fallback pricing kung walang nag-match sa listahan mo para hindi maging error/zero
        if ($addonPrice === 0) {
            if ($addon === "Photo Booth") $addonPrice = 2500;
            elseif ($addon === "Catering Service" || $addon === "Catering") $addonPrice = 6000;
            else $addonPrice = 2000; 
        }
        
        $fees[] = $addonPrice;
        $feeLabels[] = "$addon Add-on";
    }
}

$total = 0;
for ($i = 0; $i < count($fees); $i++) {
    $total += $fees[$i];
}

// BREAKDOWN DISPLAYER
$breakdownItems = [];
$breakdownItems["Venue"] = $venueName;
$breakdownItems["Event Type"] = $eventType;
$breakdownItems["Date"] = $eventDate;
$breakdownItems["Time"] = $eventTime;
$breakdownItems["Duration"] = $duration;
$breakdownItems["Guests"] = $guestCount . " pax";

foreach ($feeLabels as $index => $label) {
    $breakdownItems[$label] = "₱" . number_format($fees[$index]);
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

    public function setMethod($method) { $this->method    = $method; }
    public function setCard($card)     { $this->card      = $card; }

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
    $phone     = str_replace(' ', '', trim($_POST['phone'] ?? ''));
    $method    = $_POST['method'] ?? '';
    
    if (empty($phone) || strlen($phone) !== 10 || !is_numeric($phone)) {
        die("Phone number is required and must be exactly 10 digits.");
    }

    if (empty($method)) {
        die("Please select a valid payment method.");
    }

    $cardRaw = '';
    if ($method === 'card') {
        $cardRaw = $_POST['card_number'] ?? '';
        $card = str_replace(' ', '', $cardRaw);
        $expiry = trim($_POST['expiry'] ?? '');
        $cvv = trim($_POST['cvv'] ?? '');
        
        if (strlen($card) !== 16 || !is_numeric($card)) {
            die("Card number required for card payment and must be exactly 16 digits.");
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/[2-9][6-9]$/', $expiry)) {
            die("Invalid Expiry Date format or Year must be 26 or higher.");
        }
        if (strlen($cvv) !== 3 || !is_numeric($cvv)) {
            die("CVV must be exactly 3 digits.");
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
        'phone' => '+63' . $phone,
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

    // Kapag nakapag-bayad na, tanggalin na sa cart session yung mga binayaran
    if (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        foreach ($_POST['selected_items'] as $index) {
            unset($_SESSION['cart'][$index]);
        }
        $_SESSION['cart'] = array_values($_SESSION['cart']); // re-index cart array
    }

    header('Location: receipt.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payment | TAGPO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <style>
    .payment-section {
      transition: all 0.2s ease-in-out;
    }
  </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<section class="hero-bg text-white py-5" style="background: #2c3e50;">
  <div class="container text-center">
    <p class="section-eyebrow text-uppercase">Secure payment</p>
    <h1 class="display-5 fw-bold">Complete your booking</h1>
    <p class="lead opacity-75">Finish your reservation for <?= htmlspecialchars($venueName) ?> - ₱<?= number_format($total) ?> (<?= htmlspecialchars($customerName) ?>)</p>
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
          <?php foreach ($breakdownItems as $label => $value): ?>
            <li class="py-1 border-bottom d-flex justify-content-between">
                <span><?= htmlspecialchars($label) ?></span>
                <span class="fw-bold"><?= htmlspecialchars($value) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="h5">Total to Pay</span>
            <span class="h4 fw-bold text-primary">₱<?= number_format($total); ?></span>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card p-4 shadow-sm">
        <h3 class="mb-4">Checkout</h3>

        <form method="POST" id="payment-checkout-form">
          
          <?php if (isset($_POST['selected_items']) && is_array($_POST['selected_items'])): ?>
            <?php foreach ($_POST['selected_items'] as $index): ?>
              <input type="hidden" name="selected_items[]" value="<?= $index ?>">
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
            <select name="method" id="method_select" class="form-select" onchange="updatePaymentFields()" required>
              <option value="" selected disabled>-- Select Payment Method --</option>
              <option value="card">Credit Card</option>
              <option value="gcash">GCash</option>
              <option value="paypal">PayPal</option>
            </select>
          </div>

          <div id="card_section" class="payment-section" style="display: none;">
            <div class="mb-3">
              <label class="form-label">Card Number</label>
              <input type="text" name="card_number" id="card_number" class="form-control" placeholder="0000 0000 0000 0000" maxlength="19">
            </div>
            <div class="row">
              <div class="col-6 mb-3">
                <label class="form-label">Expiry Date</label>
                <input type="text" name="expiry" id="expiry" class="form-control" placeholder="MM/YY" maxlength="5">
              </div>
              <div class="col-6 mb-3">
                <label class="form-label">CVV</label>
                <input type="text" name="cvv" id="cvv" class="form-control" placeholder="123" maxlength="3">
              </div>
            </div>
          </div>

          <div id="gcash_section" class="payment-section" style="display: none;">
            <div class="mb-3">
              <label class="form-label">GCash Account Name</label>
              <input type="text" name="gcash_name" id="gcash_name" class="form-control" placeholder="Full Name">
            </div>
            <div class="mb-3">
              <label class="form-label">GCash Number</label>
              <input type="text" name="gcash_number" id="gcash_number" class="form-control" placeholder="09XX XXX XXXX" maxlength="13">
            </div>
          </div>

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

<script>
function updatePaymentFields() {
    const method = document.getElementById("method_select").value;
    document.getElementById("card_section").style.display = "none";
    document.getElementById("gcash_section").style.display = "none";
    document.getElementById("paypal_section").style.display = "none";
    
    if (method === "card") {
        document.getElementById("card_section").style.display = "block";
    } else if (method === "gcash") {
        document.getElementById("gcash_section").style.display = "block";
    } else if (method === "paypal") {
        document.getElementById("paypal_section").style.display = "block";
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const phoneInput = document.getElementById("phone_input");
    phoneInput.addEventListener("input", function() {
        let value = this.value.replace(/\D/g, ''); 
        if (value.length > 10) value = value.slice(0, 10); 
        
        let formatted = '';
        if (value.length > 0) formatted = value.substring(0, 3);
        if (value.length > 3) formatted += ' ' + value.substring(3, 6);
        if (value.length > 6) formatted += ' ' + value.substring(6, 10);
        this.value = formatted;
    });

    const cardInput = document.getElementById("card_number");
    if (cardInput) {
        cardInput.addEventListener("input", function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 16) value = value.slice(0, 16);
            let matches = value.match(/\d{1,4}/g);
            this.value = matches ? matches.join(' ') : value;
        });
    }

    const expiryInput = document.getElementById("expiry");
    if (expiryInput) {
        expiryInput.addEventListener("input", function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 2) {
                let month = parseInt(value.substring(0, 2), 10);
                if (month > 12) month = 12;
                if (month === 0) month = 1;
                let monthStr = month.toString().padStart(2, '0');
                let yearStr = value.substring(2, 4);
                if (yearStr.length === 2) {
                    let year = parseInt(yearStr, 10);
                    if (year < 26) yearStr = '26';
                }
                this.value = monthStr + (value.length > 2 ? '/' + yearStr : '');
            } else {
                this.value = value;
            }
        });
    }

    const cvvInput = document.getElementById("cvv");
    if (cvvInput) {
        cvvInput.addEventListener("input", function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 3) value = value.slice(0, 3);
            this.value = value;
        });
    }

    const gcashInput = document.getElementById("gcash_number");
    if (gcashInput) {
        gcashInput.addEventListener("input", function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            let formatted = '';
            if (value.length > 0) formatted = value.substring(0, 4);
            if (value.length > 4) formatted += ' ' + value.substring(4, 7);
            if (value.length > 7) formatted += ' ' + value.substring(7, 11);
            this.value = formatted;
        });
    }

    const form = document.getElementById("payment-checkout-form");
    form.addEventListener("submit", function(e) {
        const cleanPhone = phoneInput.value.replace(/\D/g, '');
        if (cleanPhone.length !== 10) {
            e.preventDefault();
            alert("Error: Ang phone number ay kailangang maging 10 digits.");
            return;
        }

        const method = document.getElementById("method_select").value;
        if (method === 'card') {
            const cleanCard = cardInput.value.replace(/\D/g, '');
            const cleanCvv = cvvInput.value.replace(/\D/g, '');
            const cleanExpiry = expiryInput.value;

            if (cleanCard.length !== 16) {
                e.preventDefault();
                alert("Error: Ang Card Number ay kailangang 16 digits.");
                return;
            }
            if (cleanExpiry.length !== 5) {
                e.preventDefault();
                alert("Error: Pakikumpleto ang Expiry Date (MM/YY).");
                return;
            }
            if (cleanCvv.length !== 3) {
                e.preventDefault();
                alert("Error: Ang CVV code ay dapat maglaman ng 3 digits.");
                return;
            }
        }
    });
});
</script>
</body>
</html>