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
$eventName       = '';
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
        if (empty($eventType))  $eventType  = $item['event_id'] ?? '';
        if (empty($eventName))  $eventName  = $item['event_type'] ?? '';
        if (empty($eventDate))  $eventDate  = $item['event_date'] ?? '';
        if (empty($eventTime))  $eventTime  = $item['event_time'] ?? '';
        if (empty($duration))   $duration   = $item['duration']   ?? '';
        $guestCount           += (int) ($item['guests'] ?? 0);
        if (!empty($item['addons'])) $addons = array_merge($addons, $item['addons']);
        if (!empty($item['cart_id'])) $selectedBookingIds[] = (int) $item['cart_id'];
        if (!$cartIdForPayment && !empty($item['cart_id'])) $cartIdForPayment = (int) $item['cart_id'];
    }

    $venueName = implode(', ', $venueNamesArray);
    $addons    = array_unique($addons);

} else {
    // Fallback: GET params (from checkout.php redirect) or re-POST
    $venueName  = $_GET['venue_name'] ?? $_POST['venue_name'] ?? '';
    $venuePrice = (float) ($_GET['venue_price'] ?? $_POST['venue_price'] ?? 0);
    $eventType  = $_GET['event_id']  ?? $_POST['event_id']  ?? '';
    $eventName  = $_GET['event_name'] ?? $_POST['form_event_name'] ?? '';
    $eventDate  = $_GET['date']        ?? $_POST['event_date']  ?? '';
    $eventTime  = $_GET['time']        ?? $_POST['event_time']  ?? '';
    $duration   = $_GET['duration']    ?? $_POST['duration']    ?? '';
    $guestCount = (int) ($_GET['guests'] ?? $_POST['guests'] ?? $_GET['guest_count'] ?? $_POST['guest_count'] ?? 0);
    $addons     = $_GET['addons']      ?? $_POST['addons']      ?? [];
}

// ── Addon price lookup from DB (single query) ────────────────────────────────
$addonRows = [];
if (!empty($addons)) {
    $placeholders = implode(',', array_fill(0, count($addons), '?'));
    $types = str_repeat('s', count($addons));
    $stmt  = $conn->prepare(
        "SELECT addon_name, price FROM addons WHERE archived = 0 AND addon_name IN ($placeholders)"
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
    $price = $addonRows[$addon] ?? 2000.00;
    $fees[]      = $price;
    $feeLabels[] = $addon . ' Add-on';
}

$total = array_sum($fees);

$customerName = '';
$user = getCurrentUser();
if ($user) {
    $customerName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

if (empty($eventName) && !empty($eventType) && ctype_digit(strval($eventType))) {
    $stmt = $conn->prepare("SELECT event_name FROM event WHERE event_id = ? AND archived = 0 LIMIT 1");
    $stmt->bind_param('i', $eventType);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $eventName = $row['event_name'];
    }
}

if (empty($eventName)) {
    $eventName = $eventType;
}

$methodMsg = '';

/* ==========================================================
   PROCESS PAYMENT SUBMISSION
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $phone     = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $method    = $_POST['method'] ?? '';

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

    // Update the saved user phone if it changed
    $uid = (int) $user['id'];
    $existingUserPhone = $user['phone'] ?? '';
    if ($phone !== $existingUserPhone) {
        $updatePhoneStmt = $conn->prepare("UPDATE users SET phone = ? WHERE id = ?");
        $updatePhoneStmt->bind_param('si', $phone, $uid);
        $updatePhoneStmt->execute();
        $updatePhoneStmt->close();
        $_SESSION['current_user']['phone'] = $phone;
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

        [$monthStr, $yearStr] = explode('/', $expiry);
        $cardExpiryMonth = (int) $monthStr;
        $cardExpiryYear  = (int) ('20' . $yearStr);
        $currentYear  = (int) date('Y');
        $currentMonth = (int) date('n');
        if ($cardExpiryYear < $currentYear || ($cardExpiryYear === $currentYear && $cardExpiryMonth < $currentMonth)) {
            die('Expired card. Please use a current or future expiry date.');
        }

        if (strlen($cvv) !== 3) die('CVV must be 3 digits.');

        $cardHolderName = trim($firstName . ' ' . $lastName);
        $cardLastFour   = substr($cardRaw, -4);

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

    // Card = paid immediately; GCash = pending until admin confirmation
    $paymentStatus = ($method === 'card') ? 'paid'   : 'pending';
    $bookingStatus = ($method === 'card') ? 'confirmed' : 'pending';

    $transactionId = strtoupper(uniqid('TXN-'));

    // Re-read selected booking IDs passed as hidden fields
    $hiddenBookingIds = [];
    if (!empty($_POST['cart_ids'])) {
        foreach ((array) $_POST['cart_ids'] as $bid) {
            $hiddenBookingIds[] = (int) $bid;
        }
    }

    // ── DB writes inside a transaction ───────────────────────────────────────
    $conn->begin_transaction();
    try {
        // 1. Derive cart_id from the first booking (if available)
        $dbCartId = null;
        if (!empty($hiddenBookingIds)) {
            $stmt = $conn->prepare(
                "SELECT cart_id FROM bookings WHERE cart_id = ? AND user_id = ? LIMIT 1"
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

        // 2. Check if a payment record already exists for this cart
        $stmt = $conn->prepare(
            "SELECT payment_id FROM payments WHERE cart_id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $dbCartId);
        $stmt->execute();
        $existingPayment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existingPayment) {
            // Update the existing payment record
            $paymentId = (int) $existingPayment['payment_id'];
            $stmt = $conn->prepare(
                "UPDATE payments
                 SET amount = ?, payment_method = ?, payment_status = ?,
                     transaction_id = ?, payment_date = NOW()
                 WHERE payment_id = ?"
            );
            $stmt->bind_param('dsssi', $formTotal, $dbMethod, $paymentStatus, $transactionId, $paymentId);
            $stmt->execute();
            $stmt->close();
        } else {
            // Insert a fresh payment record
            $stmt = $conn->prepare(
                "INSERT INTO payments
                    (cart_id, amount, payment_method, payment_status, transaction_id, payment_date)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('idsss', $dbCartId, $formTotal, $dbMethod, $paymentStatus, $transactionId);
            $stmt->execute();
            $paymentId = (int) $conn->insert_id;
            $stmt->close();
        }

        // 3. Insert into card_payments or gcash_payments child table
        if ($method === 'card') {
            $stmt = $conn->prepare("DELETE FROM card_payments WHERE payment_id = ?");
            $stmt->bind_param('i', $paymentId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                "INSERT INTO card_payments
                    (payment_id, card_holder_name, card_last_four, card_expiry_month, card_expiry_year)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('issii', $paymentId, $cardHolderName, $cardLastFour, $cardExpiryMonth, $cardExpiryYear);
            $stmt->execute();
            $stmt->close();

        } elseif ($method === 'gcash') {
            $stmt = $conn->prepare("DELETE FROM gcash_payments WHERE payment_id = ?");
            $stmt->bind_param('i', $paymentId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                "INSERT INTO gcash_payments
                    (payment_id, gcash_phone_number, gcash_account_name)
                 VALUES (?, ?, ?)"
            );
            $stmt->bind_param('iss', $paymentId, $gcashPhone, $gcashAccountName);
            $stmt->execute();
            $stmt->close();
        }

        // 4. Mark bookings — confirmed for card, awaiting_confirmation for gcash
        if (!empty($hiddenBookingIds)) {
            $placeholders = implode(',', array_fill(0, count($hiddenBookingIds), '?'));
            $types = str_repeat('i', count($hiddenBookingIds));
            $stmt  = $conn->prepare(
                "UPDATE bookings SET status = '$bookingStatus'
                 WHERE cart_id IN ($placeholders) AND user_id = ?"
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
        die('Payment processing failed: ' . htmlspecialchars($e->getMessage()) . '. Please try again.');
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
        'event_id'       => $eventType  ?: $_POST['form_event_id'] ?? '',
        'event_name'     => $eventName  ?: $_POST['form_event_name'] ?? '',
        'event_date'     => $eventDate  ?: $_POST['form_event_date'] ?? '',
        'event_time'     => $eventTime  ?: $_POST['form_event_time'] ?? '',
        'duration'       => $duration   ?: $_POST['form_duration']   ?? '',
        'guest_count'    => $guestCount ?: (int)($_POST['form_guests'] ?? 0),
        'addons'         => $addons,
        'fee_labels'     => $feeLabels,
        'fees'           => $fees,
        'total'          => $formTotal,
        'payment_method' => $methodMsg,
        'payment_status' => $paymentStatus,
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

    // 8. Show appropriate notification then redirect
    if ($method === 'card') {
        // Success popup for card payments
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payment Successful | TAGPO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"/>
  <style>
    body { background: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
    .success-card { background: #fff; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.12); padding: 2.5rem 3rem; text-align: center; max-width: 420px; width: 100%; animation: fadeIn .4s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }
    .checkmark { font-size: 4rem; color: #198754; }
    .progress-bar-wrap { height: 5px; background: #e9ecef; border-radius: 99px; overflow: hidden; margin-top: 1.5rem; }
    .progress-bar-fill { height: 100%; background: #198754; border-radius: 99px; animation: shrink 3s linear forwards; }
    @keyframes shrink { from { width: 100%; } to { width: 0%; } }
  </style>
</head>
<body>
  <div class="success-card">
    <div class="checkmark"><i class="bi bi-check-circle-fill"></i></div>
    <h3 class="fw-bold mt-3 mb-1">Payment Successful!</h3>
    <p class="text-muted mb-1">
      <strong>₱<?= number_format($formTotal) ?></strong> has been deducted from your account.
    </p>
    <p class="text-muted small">Transaction ID: <span class="fw-semibold"><?= htmlspecialchars($transactionId) ?></span></p>
    <p class="text-muted small mb-0">Redirecting you to your receipt...</p>
    <div class="progress-bar-wrap"><div class="progress-bar-fill"></div></div>
  </div>
  <script>setTimeout(function () { window.location.href = 'receipt.php'; }, 3000);</script>
</body>
</html>
        <?php
    } else {
        // GCash — go straight to receipt (status is pending, admin will confirm)
        header('Location: receipt.php');
    }
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
  <style>
    .payment-section { transition: all .2s ease-in-out; }
    .input-invalid {
      border-color: #dc3545 !important;
      box-shadow: 0 0 0 0.15rem rgba(220, 53, 69, 0.25);
    }
    .invalid-feedback-inline {
      display: none;
      color: #dc3545;
      font-size: 0.9rem;
      margin-top: 0.35rem;
    }
    .invalid-feedback-inline.active {
      display: block;
    }
    /* Simple Terms Container styling */
    .terms-container {
      max-height: 120px;
      overflow-y: auto;
      background-color: #f8f9fa;
      border: 1px solid #dee2e6;
      padding: 10px;
      font-size: 0.85rem;
      color: #6c757d;
      margin-bottom: 15px;
      border-radius: 4px;
    }
  </style>
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

    <div class="col-lg-6">
      <div class="card p-4 shadow-sm border-0">
        <h4 class="fw-bold mb-4">Booking Summary</h4>

        <div class="p-3 mb-4" style="background:#f8f9fa;border-left:5px solid #0d6efd;border-radius:5px;">
          <h5 class="fw-bold"><?= htmlspecialchars($venueName) ?></h5>
          <p class="mb-1 text-muted small">Customer: <?= htmlspecialchars($customerName) ?></p>
          <p class="mb-1 text-muted small">Event: <?= htmlspecialchars($eventName) ?></p>
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

    <div class="col-lg-6">
      <div class="card p-4 shadow-sm">
        <h3 class="mb-4">Checkout</h3>

        <form method="POST" id="payment-checkout-form">

          <?php if (!empty($_POST['selected_items'])): ?>
            <?php foreach ((array)$_POST['selected_items'] as $idx): ?>
              <input type="hidden" name="selected_indices[]" value="<?= (int)$idx ?>">
            <?php endforeach; ?>
          <?php endif; ?>

          <?php foreach ($selectedBookingIds as $bid): ?>
            <input type="hidden" name="cart_ids[]" value="<?= (int)$bid ?>">
          <?php endforeach; ?>

          <input type="hidden" name="form_venue_name"  value="<?= htmlspecialchars($venueName) ?>">
          <input type="hidden" name="form_event_id"    value="<?= htmlspecialchars($eventType) ?>">
          <input type="hidden" name="form_event_name"  value="<?= htmlspecialchars($eventName) ?>">
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
                     placeholder="9XX XXX XXXX" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
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
                <div id="expiry_feedback" class="invalid-feedback-inline">Expired card. Please use a current or future expiry date.</div>
              </div>
              <div class="col-6 mb-3">
                <label class="form-label">CVV</label>
                <input type="text" name="cvv" id="cvv" class="form-control"
                       placeholder="123" maxlength="3">
              </div>
            </div>
          </div>

          <div id="gcash_section" class="payment-section" style="display:none;">
            <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
              <i class="bi bi-info-circle-fill mt-1"></i>
              <div>
                <strong>GCash — Manual Verification</strong><br>
                <span class="small">Send your payment to one of the GCash accounts below. Your booking will be marked as <strong>pending</strong> until our admin confirms your payment.</span>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Send Payment To</label>
              <select class="form-select" id="gcash_account_select" onchange="fillGcashAccount(this)">
                <option value="" disabled selected>-- Select Admin GCash Account --</option>
                <option value="09686347062|Jen Mae Ilao">09686347062 — Jen Mae Ilao</option>
                <option value="09053731204|Natalie Paduhilao">09053731204 — Natalie Paduhilao</option>
                <option value="09764344103|Wayne Tanglao">09764344103 — Wayne Tanglao</option>
                <option value="09391234567|Admin TAGPO">09391234567 — Admin TAGPO</option>
              </select>
              <div class="mt-2 p-2 rounded" id="gcash_account_display" style="display:none; background:#e8f4fd; border:1px solid #b8daff;">
                <small class="text-primary fw-semibold"><i class="bi bi-phone-fill me-1"></i><span id="gcash_display_number"></span> — <span id="gcash_display_name"></span></small>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Your GCash Account Name</label>
              <input type="text" name="gcash_name" id="gcash_name" class="form-control"
                     placeholder="Full Name">
            </div>
            <div class="mb-3">
              <label class="form-label">Your GCash Number</label>
              <input type="text" name="gcash_number" id="gcash_number" class="form-control"
                     placeholder="09XX XXX XXXX" maxlength="13">
            </div>
          </div>

          <div class="mt-4 mb-3">
            <label class="form-label fw-bold">Terms & Conditions</label>
            <div class="terms-container">
              <h6>1. Booking and Reservations</h6>
              <p>All bookings made through TAGPO are subject to venue availability and final confirmation from the administrator.</p>
              <h6>2. Cancellation and Refunds</h6>
              <p>Cancellations made 7 days prior to the event are eligible for a partial refund. Late cancellations may incur a forfeiture fee.</p>
              <h6>3. Payment Terms</h6>
              <p>Card payments are processed instantly. GCash payments require manual verification by an administrator before the booking is fully confirmed.</p>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="terms_checkbox">
              <label class="form-check-label small text-muted" for="terms_checkbox" style="cursor: pointer; user-select: none;">
                I have read and agree to the TAGPO Terms & Conditions.
              </label>
            </div>
          </div>

          <button type="submit" name="pay_now" id="pay_button" class="btn btn-primary w-100 py-3 fw-bold mt-2" disabled style="opacity: 0.6; cursor: not-allowed; transition: all 0.2s ease;">
            Pay ₱<?= number_format($total) ?> Now
          </button>
        </form>
    </div>

  </div>
</main>

<?php include 'includes/footer.php'; ?>

<script>
function fillGcashAccount(select) {
  const val = select.value;
  if (!val) return;
  const [number, name] = val.split('|');
  document.getElementById('gcash_display_number').textContent = number;
  document.getElementById('gcash_display_name').textContent = name;
  document.getElementById('gcash_account_display').style.display = 'block';
}

function updatePaymentFields() {
  const method = document.getElementById('method_select').value;
  document.getElementById('card_section').style.display  = method === 'card'  ? 'block' : 'none';
  document.getElementById('gcash_section').style.display = method === 'gcash' ? 'block' : 'none';
  
  const gcashSelect = document.getElementById('gcash_account_select');
  if (method !== 'gcash') {
    if(gcashSelect) gcashSelect.value = '';
    document.getElementById('gcash_account_display').style.display = 'none';
    if(gcashSelect) gcashSelect.removeAttribute('required');
  } else {
    if(gcashSelect) gcashSelect.setAttribute('required', 'required');
  }
}

// --- TERMS & CONDITIONS DISABLE/ENABLE LOGIC ---
  const termsCheckbox = document.getElementById('terms_checkbox');
  const payButton = document.getElementById('pay_button');

  if (termsCheckbox && payButton) {
    termsCheckbox.addEventListener('change', function () {
      if (this.checked) {
        // Kapag naka-tsek: Active at normal na hitsura
        payButton.disabled = false;
        payButton.style.opacity = "1";
        payButton.style.cursor = "pointer";
      } else {
        // Kapag tinanggal ang tsek: Naka-lock at malabo uli
        payButton.disabled = true;
        payButton.style.opacity = "0.6";
        payButton.style.cursor = "not-allowed";
      }
    });
  }

document.addEventListener('DOMContentLoaded', function () {
  document.getElementById('phone_input').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 10);
    let f = '';
    if (v.length > 0) f = v.slice(0, 3);
    if (v.length > 3) f += ' ' + v.slice(3, 6);
    if (v.length > 6) f += ' ' + v.slice(6, 10);
    this.value = f;
  });

  const cardInput = document.getElementById('card_number');
  if (cardInput) {
    cardInput.addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '').slice(0, 16);
      this.value = v.match(/.{1,4}/g)?.join(' ') ?? v;
    });
  }

  const expiryInput = document.getElementById('expiry');
  const expiryFeedback = document.getElementById('expiry_feedback');
  function setExpiryInvalid(message) {
    if (!expiryInput || !expiryFeedback) return;
    expiryInput.classList.add('input-invalid');
    expiryFeedback.textContent = message;
    expiryFeedback.classList.add('active');
  }
  function clearExpiryInvalid() {
    if (!expiryInput || !expiryFeedback) return;
    expiryInput.classList.remove('input-invalid');
    expiryFeedback.classList.remove('active');
  }

  if (expiryInput) {
    expiryInput.addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '');
      if (v.length >= 2) {
        let m = Math.min(12, Math.max(1, parseInt(v.slice(0, 2))));
        this.value = String(m).padStart(2, '0') + (v.length > 2 ? '/' + v.slice(2, 4) : '');
      } else {
        this.value = v;
      }

      if (this.value.match(/^(0[1-9]|1[0-2])\/\d{2}$/)) {
        const [month, year] = this.value.split('/').map(Number);
        const expiryYear = 2000 + year;
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth() + 1;
        if (expiryYear < currentYear || (expiryYear === currentYear && month < currentMonth)) {
          setExpiryInvalid('Expired card. Please use a current or future expiry date.');
        } else {
          clearExpiryInvalid();
        }
      } else {
        clearExpiryInvalid();
      }
    });
  }

  const cvvInput = document.getElementById('cvv');
  if (cvvInput) {
    cvvInput.addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, '').slice(0, 3);
    });
  }

  const gcashInput = document.getElementById('gcash_number');
  if (gcashInput) {
    gcashInput.addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '').slice(0, 11);
      let f = '';
      if (v.length > 0) f = v.slice(0, 4);
      if (v.length > 4) f += ' ' + v.slice(4, 7);
      if (v.length > 7) f += ' ' + v.slice(7, 11);
      this.value = f;
    });
  }

  document.getElementById('payment-checkout-form').addEventListener('submit', function (e) {
    const phone = document.getElementById('phone_input').value.replace(/\D/g, '');
    if (phone.length !== 10) { e.preventDefault(); alert('Phone number must be 10 digits.'); return; }

    const method = document.getElementById('method_select').value;
    if (method === 'card') {
      if (cardInput.value.replace(/\D/g, '').length !== 16) { e.preventDefault(); alert('Card number must be 16 digits.'); return; }
      if (!expiryInput.value.match(/^(0[1-9]|1[0-2])\/\d{2}$/)) { e.preventDefault(); alert('Invalid expiry date (MM/YY).'); return; }
      const [month, year] = expiryInput.value.split('/').map(Number);
      const expiryYear = 2000 + year;
      const now = new Date();
      const currentYear = now.getFullYear();
      const currentMonth = now.getMonth() + 1;
      if (expiryYear < currentYear || (expiryYear === currentYear && month < currentMonth)) {
        e.preventDefault();
        setExpiryInvalid('Expired card. Please use a current or future expiry date.');
        expiryInput.focus();
        return;
      }
      if (cvvInput.value.length !== 3) { e.preventDefault(); alert('CVV must be 3 digits.'); return; }
    }
    
    if (method === 'gcash') {
      const gcashAccountSelect = document.getElementById('gcash_account_select');
      if (!gcashAccountSelect.value) {
        e.preventDefault();
        alert('Please select an admin GCash account to send payment to.');
        gcashAccountSelect.focus();
        return;
      }
      
      const g = gcashInput.value.replace(/\D/g, '');
      if (g.length !== 11) { e.preventDefault(); alert('GCash number must be 11 digits.'); return; }
    }

    // EXTRA SECURITY VALIDATION FOR TERMS CHECKBOX
    const termsCheck = document.getElementById('terms_checkbox');
    if (!termsCheck.checked) {
      e.preventDefault();
      alert('You must agree to the Terms & Conditions before proceeding.');
      termsCheck.focus();
      return;
    }
    
  });
});
</script>
</body>
</html>