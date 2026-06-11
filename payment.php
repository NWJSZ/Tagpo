<?php
require_once 'config/database.php';
require_once 'config/session_config.php';
require_once 'config/app.php';

$baseUrl = getBaseUrl();

$_SESSION['last_activity'] = time();
if (isset($_SESSION['current_user'])) {
    setcookie('user_session', $_SESSION['current_user']['email'], time() + (86400 * 7), '/');
}

if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

/* ==========================================================
   BUILD PAYMENT DATA FROM SELECTED SESSION-CART ITEMS
   ========================================================== */
$venueNamesArray = [];
$venuePrice      = 0;
$eventType       = '';
$eventDate       = '';
$eventTime       = '';
$duration        = '';
$guestCount      = 0;
$addons          = [];
$selectedBookingIds = [];
$cartIdForPayment   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['pay_now'])) {
    // Coming from cart.php with selected_items[]
    $selectedIndices = $_POST['selected_items'] ?? [];

    foreach ($selectedIndices as $index) {
        $index = (int) $index;
        if (!isset($_SESSION['cart'][$index])) continue;

        $item = $_SESSION['cart'][$index];
        $venueNamesArray[]    = $item['venue_name'];
        $venuePrice          += (float) ($item['venue_price'] ?? 0);
        if (empty($eventType))  $eventType  = $item['event_type'] ?? '';
        if (empty($eventDate))  $eventDate  = $item['event_date'] ?? '';
        if (empty($eventTime))  $eventTime  = $item['event_time'] ?? '';
        if (empty($duration))   $duration   = $item['duration']   ?? '';
        $guestCount           += (int) ($item['guests'] ?? 0);
        if (!empty($item['addons'])) $addons = array_merge($addons, $item['addons']);
        if (!empty($item['booking_id'])) $selectedBookingIds[] = (int) $item['booking_id'];
        if (!$cartIdForPayment && !empty($item['cart_id'])) $cartIdForPayment = (int) $item['cart_id'];
    }

    $venueName = implode(', ', $venueNamesArray);
    $addons    = array_unique($addons);

} else {
    // Fallback: GET params (from checkout.php redirect) or re-POST
    $venueName  = $_GET['venue_name'] ?? $_POST['venue_name'] ?? '';
    $venuePrice = (float) ($_GET['venue_price'] ?? $_POST['venue_price'] ?? 0);
    $eventType  = $_GET['event_type']  ?? $_POST['event_type']  ?? '';
    $eventDate  = $_GET['date']        ?? $_POST['event_date']  ?? '';
    $eventTime  = $_GET['time']        ?? $_POST['event_time']  ?? '';
    $duration   = $_GET['duration']    ?? $_POST['duration']    ?? '';
    $guestCount = (int) ($_GET['guests'] ?? $_POST['guests'] ?? 0);
    $addons     = $_GET['addons']      ?? $_POST['addons']      ?? [];
}

// ── Addon price lookup from DB (single query) ────────────────────────────────
$addonRows = [];
if (!empty($addons)) {
    $placeholders = implode(',', array_fill(0, count($addons), '?'));
    $types = str_repeat('s', count($addons));
    $stmt  = $conn->prepare(
        "SELECT addon_name, price FROM addons WHERE addon_name IN ($placeholders)"
    );
    $stmt->bind_param($types, ...$addons);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $addonRows[$row['addon_name']] = (float) $row['price'];
    }
    $stmt->close();
}

// ── Build fee breakdown ───────────────────────────────────────────────────────
$fees       = [(float) $venuePrice];
$feeLabels  = ['Venue Price'];

foreach ($addons as $addon) {
    $price = $addonRows[$addon] ?? 2000.00; // sensible fallback
    $fees[]      = $price;
    $feeLabels[] = $addon . ' Add-on';
}

$total = array_sum($fees);

$customerName = '';
$user = getCurrentUser();
if ($user) {
    $customerName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

$methodMsg   = '';

/* ==========================================================
   PROCESS PAYMENT SUBMISSION
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $phone     = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $method    = $_POST['method'] ?? '';

    // Re-compute total from hidden fields passed back through the form
    $formVenuePrice = (float) ($_POST['venue_price'] ?? $venuePrice);
    $formAddons     = is_array($_POST['form_addons'] ?? null) ? $_POST['form_addons'] : $addons;
    $formTotal      = (float) ($_POST['form_total']  ?? $total);

    // Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die('Invalid email address.');
    }
    if (strlen($phone) !== 10) {
        die('Phone number must be exactly 10 digits.');
    }
    if (empty($method)) {
        die('Please select a payment method.');
    }

    // Card-specific validation
    $cardHolderName   = '';
    $cardLastFour     = '';
    $cardExpiryMonth  = 0;
    $cardExpiryYear   = 0;
    $gcashPhone       = '';
    $gcashAccountName = '';

    if ($method === 'card') {
        $cardRaw = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
        $expiry  = trim($_POST['expiry'] ?? '');
        $cvv     = trim($_POST['cvv']    ?? '');

        if (strlen($cardRaw) !== 16) die('Card number must be 16 digits.');
        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) die('Invalid expiry format (MM/YY).');
        if (strlen($cvv) !== 3) die('CVV must be 3 digits.');

        $cardHolderName  = trim($firstName . ' ' . $lastName);
        $cardLastFour    = substr($cardRaw, -4);
        [$monthStr, $yearStr] = explode('/', $expiry);
        $cardExpiryMonth = (int) $monthStr;
        $cardExpiryYear  = (int) ('20' . $yearStr);

    } elseif ($method === 'gcash') {
        $gcashAccountName = trim($_POST['gcash_name']   ?? '');
        $gcashPhone       = preg_replace('/\D/', '', $_POST['gcash_number'] ?? '');
        if (strlen($gcashPhone) !== 11) die('GCash number must be 11 digits.');
    }

    $dbMethod = match($method) {
        'card'  => 'credit_card',
        'gcash' => 'gcash',
        default => 'credit_card',
    };

    $methodMsg = match($method) {
        'card'  => 'Credit Card',
        'gcash' => 'GCash',
        default => 'Credit Card',
    };

    $transactionId = strtoupper(uniqid('TXN-'));

    // ── DB writes inside a transaction ───────────────────────────────────────
    $conn->begin_transaction();
    try {
        // 1. Ensure there is an active cart in the DB for this user
        $uid = (int) $user['id'];

        // Re-read selected booking IDs passed as hidden fields
        $hiddenBookingIds = [];
        if (!empty($_POST['booking_ids'])) {
            foreach ((array) $_POST['booking_ids'] as $bid) {
                $hiddenBookingIds[] = (int) $bid;
            }
        }

        // Derive cart_id from the first booking (if available)
        $dbCartId = null;
        if (!empty($hiddenBookingIds)) {
            $stmt = $conn->prepare(
                "SELECT cart_id FROM bookings WHERE booking_id = ? AND user_id = ? LIMIT 1"
            );
            $stmt->bind_param('ii', $hiddenBookingIds[0], $uid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) $dbCartId = (int) $row['cart_id'];
        }

        // Fallback: get active cart
        if (!$dbCartId) {
            $stmt = $conn->prepare(
                "SELECT cart_id FROM carts WHERE user_id = ? AND status = 'active' LIMIT 1"
            );
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $dbCartId = $row ? (int) $row['cart_id'] : null;
        }

        // If still none, create one
        if (!$dbCartId) {
            $stmt = $conn->prepare("INSERT INTO carts (user_id, status) VALUES (?, 'active')");
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $dbCartId = (int) $conn->insert_id;
            $stmt->close();
        }

        // 2. Insert into payments
        $stmt = $conn->prepare(
            "INSERT INTO payments
                (cart_id, amount, payment_method, payment_status, transaction_id, payment_date)
             VALUES (?, ?, ?, 'paid', ?, NOW())"
        );
        $stmt->bind_param('idss', $dbCartId, $formTotal, $dbMethod, $transactionId);
        $stmt->execute();
        $paymentId = (int) $conn->insert_id;
        $stmt->close();

        // 3. Insert into card_payments or gcash_payments
        if ($method === 'card') {
            $stmt = $conn->prepare(
                "INSERT INTO card_payments
                    (payment_id, card_holder_name, card_last_four, card_expiry_month, card_expiry_year)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('issii',
                $paymentId, $cardHolderName, $cardLastFour,
                $cardExpiryMonth, $cardExpiryYear
            );
            $stmt->execute();
            $stmt->close();

        } elseif ($method === 'gcash') {
            $stmt = $conn->prepare(
                "INSERT INTO gcash_payments (payment_id, gcash_phone_number, gcash_account_name)
                 VALUES (?, ?, ?)"
            );
            $stmt->bind_param('iss', $paymentId, $gcashPhone, $gcashAccountName);
            $stmt->execute();
            $stmt->close();
        }

        // 4. Mark bookings as confirmed
        if (!empty($hiddenBookingIds)) {
            $placeholders = implode(',', array_fill(0, count($hiddenBookingIds), '?'));
            $types = str_repeat('i', count($hiddenBookingIds));
            $stmt  = $conn->prepare(
                "UPDATE bookings SET status = 'confirmed'
                 WHERE booking_id IN ($placeholders) AND user_id = ?"
            );
            $stmt->bind_param($types . 'i', ...[...$hiddenBookingIds, $uid]);
            $stmt->execute();
            $stmt->close();
        }

        // 5. Mark cart as checked_out
        $stmt = $conn->prepare(
            "UPDATE carts SET status = 'checked_out' WHERE cart_id = ?"
        );
        $stmt->bind_param('i', $dbCartId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log('Payment failed: ' . $e->getMessage());
        die('Payment processing failed. Please try again.');
    }

    // 6. Store receipt data in session
    $_SESSION['receipt_data'] = [
        'invoice_number' => $transactionId,
        'customer_name'  => trim($firstName . ' ' . $lastName),
        'first_name'     => $firstName,
        'last_name'      => $lastName,
        'email'          => $email,
        'phone'          => '+63' . $phone,
        'venue_name'     => $venueName ?: $_POST['form_venue_name'] ?? '',
        'venue_price'    => $venuePrice,
        'event_type'     => $eventType  ?: $_POST['form_event_type'] ?? '',
        'event_date'     => $eventDate  ?: $_POST['form_event_date'] ?? '',
        'event_time'     => $eventTime  ?: $_POST['form_event_time'] ?? '',
        'duration'       => $duration   ?: $_POST['form_duration']   ?? '',
        'guest_count'    => $guestCount ?: (int)($_POST['form_guests'] ?? 0),
        'addons'         => $addons,
        'fee_labels'     => $feeLabels,
        'fees'           => $fees,
        'total'          => $formTotal,
        'payment_method' => $methodMsg,
        'card_last4'     => $cardLastFour ?: null,
        'timestamp'      => time(),
    ];

    // 7. Clear paid items from session cart
    if (!empty($_POST['selected_indices'])) {
        foreach ((array) $_POST['selected_indices'] as $idx) {
            unset($_SESSION['cart'][(int) $idx]);
        }
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    } else {
        $_SESSION['cart'] = [];
    }

    header('Location: receipt.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payment | TAGPO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <style>.payment-section{transition:all .2s ease-in-out;}</style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<section class="hero-bg text-white py-5" style="background:#2c3e50;">
  <div class="container text-center">
    <p class="section-eyebrow text-uppercase">Secure payment</p>
    <h1 class="display-5 fw-bold">Complete your booking</h1>
    <p class="lead opacity-75">
      <?= htmlspecialchars($venueName ?: 'Selected Venue') ?> — ₱<?= number_format($total) ?>
      (<?= htmlspecialchars($customerName) ?>)
    </p>
  </div>
</section>

<main class="container my-5">
  <div class="row g-5">

    <!-- LEFT: Booking Summary -->
    <div class="col-lg-6">
      <div class="card p-4 shadow-sm border-0">
        <h4 class="fw-bold mb-4">Booking Summary</h4>

        <div class="p-3 mb-4" style="background:#f8f9fa;border-left:5px solid #0d6efd;border-radius:5px;">
          <h5 class="fw-bold"><?= htmlspecialchars($venueName) ?></h5>
          <p class="mb-1 text-muted small">Customer: <?= htmlspecialchars($customerName) ?></p>
          <p class="mb-1 text-muted small">Event: <?= htmlspecialchars($eventType) ?></p>
          <p class="mb-1 text-muted small">Date: <?= htmlspecialchars($eventDate) ?> at <?= htmlspecialchars($eventTime) ?> (<?= htmlspecialchars($duration) ?>)</p>
          <p class="mb-0 text-muted small">Guests: <?= (int)$guestCount ?> pax</p>
        </div>

        <h5 class="fw-bold">Payment Breakdown</h5>
        <ul class="list-unstyled">
          <?php foreach ($feeLabels as $i => $label): ?>
            <li class="py-1 border-bottom d-flex justify-content-between">
              <span><?= htmlspecialchars($label) ?></span>
              <span class="fw-bold">₱<?= number_format($fees[$i]) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <span class="h5">Total to Pay</span>
          <span class="h4 fw-bold text-primary">₱<?= number_format($total) ?></span>
        </div>
      </div>
    </div>

    <!-- RIGHT: Checkout Form -->
    <div class="col-lg-6">
      <div class="card p-4 shadow-sm">
        <h3 class="mb-4">Checkout</h3>

        <form method="POST" id="payment-checkout-form">

          <!-- Pass cart state back through the form -->
          <?php if (!empty($_POST['selected_items'])): ?>
            <?php foreach ((array)$_POST['selected_items'] as $idx): ?>
              <input type="hidden" name="selected_indices[]" value="<?= (int)$idx ?>">
            <?php endforeach; ?>
          <?php endif; ?>

          <?php foreach ($selectedBookingIds as $bid): ?>
            <input type="hidden" name="booking_ids[]" value="<?= (int)$bid ?>">
          <?php endforeach; ?>

          <!-- Carry booking details for receipt -->
          <input type="hidden" name="form_venue_name"  value="<?= htmlspecialchars($venueName) ?>">
          <input type="hidden" name="form_event_type"  value="<?= htmlspecialchars($eventType) ?>">
          <input type="hidden" name="form_event_date"  value="<?= htmlspecialchars($eventDate) ?>">
          <input type="hidden" name="form_event_time"  value="<?= htmlspecialchars($eventTime) ?>">
          <input type="hidden" name="form_duration"    value="<?= htmlspecialchars($duration) ?>">
          <input type="hidden" name="form_guests"      value="<?= (int)$guestCount ?>">
          <input type="hidden" name="venue_price"      value="<?= (float)$venuePrice ?>">
          <input type="hidden" name="form_total"       value="<?= (float)$total ?>">
          <?php foreach ($addons as $a): ?>
            <input type="hidden" name="form_addons[]"  value="<?= htmlspecialchars($a) ?>">
          <?php endforeach; ?>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">First Name</label>
              <input type="text" name="first_name" class="form-control"
                     value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Last Name</label>
              <input type="text" name="last_name" class="form-control"
                     value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control"
                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <div class="input-group">
              <span class="input-group-text">+63</span>
              <input type="text" name="phone" id="phone_input" class="form-control"
                     placeholder="9XX XXX XXXX" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Payment Method</label>
            <select name="method" id="method_select" class="form-select"
                    onchange="updatePaymentFields()" required>
              <option value="" selected disabled>-- Select Payment Method --</option>
              <option value="card">Credit / Debit Card</option>
              <option value="gcash">GCash</option>
            </select>
          </div>

          <!-- Card fields -->
          <div id="card_section" class="payment-section" style="display:none;">
            <div class="mb-3">
              <label class="form-label">Card Number</label>
              <input type="text" name="card_number" id="card_number" class="form-control"
                     placeholder="0000 0000 0000 0000" maxlength="19">
            </div>
            <div class="row">
              <div class="col-6 mb-3">
                <label class="form-label">Expiry Date</label>
                <input type="text" name="expiry" id="expiry" class="form-control"
                       placeholder="MM/YY" maxlength="5">
              </div>
              <div class="col-6 mb-3">
                <label class="form-label">CVV</label>
                <input type="text" name="cvv" id="cvv" class="form-control"
                       placeholder="123" maxlength="3">
              </div>
            </div>
          </div>

          <!-- GCash fields -->
          <div id="gcash_section" class="payment-section" style="display:none;">
            <div class="mb-3">
              <label class="form-label">GCash Account Name</label>
              <input type="text" name="gcash_name" id="gcash_name" class="form-control"
                     placeholder="Full Name">
            </div>
            <div class="mb-3">
              <label class="form-label">GCash Number</label>
              <input type="text" name="gcash_number" id="gcash_number" class="form-control"
                     placeholder="09XX XXX XXXX" maxlength="13">
            </div>
          </div>

          <button type="submit" name="pay_now" class="btn btn-primary w-100 py-3 fw-bold mt-2">
            Pay ₱<?= number_format($total) ?> Now
          </button>
        </form>
      </div>
    </div>

  </div>
</main>

<?php include 'includes/footer.php'; ?>

<script>
function updatePaymentFields() {
  const method = document.getElementById('method_select').value;
  document.getElementById('card_section').style.display  = method === 'card'  ? 'block' : 'none';
  document.getElementById('gcash_section').style.display = method === 'gcash' ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function () {
  // Phone formatter
  document.getElementById('phone_input').addEventListener('input', function () {
    let v = this.value.replace(/\D/g,'').slice(0,10);
    let f = '';
    if (v.length > 0) f = v.slice(0,3);
    if (v.length > 3) f += ' ' + v.slice(3,6);
    if (v.length > 6) f += ' ' + v.slice(6,10);
    this.value = f;
  });

  // Card number formatter
  const cardInput = document.getElementById('card_number');
  if (cardInput) {
    cardInput.addEventListener('input', function () {
      let v = this.value.replace(/\D/g,'').slice(0,16);
      this.value = v.match(/.{1,4}/g)?.join(' ') ?? v;
    });
  }

  // Expiry formatter
  const expiryInput = document.getElementById('expiry');
  if (expiryInput) {
    expiryInput.addEventListener('input', function () {
      let v = this.value.replace(/\D/g,'');
      if (v.length >= 2) {
        let m = Math.min(12, Math.max(1, parseInt(v.slice(0,2))));
        this.value = String(m).padStart(2,'0') + (v.length > 2 ? '/' + v.slice(2,4) : '');
      } else {
        this.value = v;
      }
    });
  }

  // CVV formatter
  const cvvInput = document.getElementById('cvv');
  if (cvvInput) {
    cvvInput.addEventListener('input', function () {
      this.value = this.value.replace(/\D/g,'').slice(0,3);
    });
  }

  // GCash number formatter
  const gcashInput = document.getElementById('gcash_number');
  if (gcashInput) {
    gcashInput.addEventListener('input', function () {
      let v = this.value.replace(/\D/g,'').slice(0,11);
      let f = '';
      if (v.length > 0) f = v.slice(0,4);
      if (v.length > 4) f += ' ' + v.slice(4,7);
      if (v.length > 7) f += ' ' + v.slice(7,11);
      this.value = f;
    });
  }

  // Form validation
  document.getElementById('payment-checkout-form').addEventListener('submit', function (e) {
    const phone = document.getElementById('phone_input').value.replace(/\D/g,'');
    if (phone.length !== 10) { e.preventDefault(); alert('Phone number must be 10 digits.'); return; }

    const method = document.getElementById('method_select').value;
    if (method === 'card') {
      if (cardInput.value.replace(/\D/g,'').length !== 16) { e.preventDefault(); alert('Card number must be 16 digits.'); return; }
      if (!expiryInput.value.match(/^(0[1-9]|1[0-2])\/\d{2}$/)) { e.preventDefault(); alert('Invalid expiry date (MM/YY).'); return; }
      if (cvvInput.value.length !== 3) { e.preventDefault(); alert('CVV must be 3 digits.'); return; }
    }
    if (method === 'gcash') {
      const g = gcashInput.value.replace(/\D/g,'');
      if (g.length !== 11) { e.preventDefault(); alert('GCash number must be 11 digits.'); return; }
    }
  });
});
</script>
</body>
</html>
