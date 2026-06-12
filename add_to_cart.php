<?php
require_once 'config/database.php';
require_once 'config/session_config.php';
require_once 'config/app.php';

// Must be logged in to add to cart
if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

$_SESSION['last_activity'] = time();

$currentUser = getCurrentUser();
$userId = (int) $currentUser['id'];

// ── Collect & sanitise POST inputs ──────────────────────────────────────────
$venueId      = filter_input(INPUT_POST, 'venue_id',    FILTER_VALIDATE_INT);
$venueName    = trim($_POST['venue_name']  ?? '');
$venuePrice   = filter_input(INPUT_POST, 'venue_price', FILTER_VALIDATE_FLOAT);
$eventType    = trim($_POST['event_type']  ?? '');
$eventDate    = trim($_POST['event_date']  ?? '');
$eventTime    = trim($_POST['event_time']  ?? '');
$durationRaw  = trim($_POST['duration']    ?? '');
$guestCount   = filter_input(INPUT_POST, 'guests',      FILTER_VALIDATE_INT);
$addons       = is_array($_POST['addons'] ?? null) ? $_POST['addons'] : [];

if (!$venueId || !$venuePrice || empty($eventType) || empty($eventDate) ||
    empty($eventTime) || $guestCount < 1) {
    die('Invalid booking data. Please go back and fill in all required fields.');
}

// Normalise duration string ("4 hours", "Full day") to integer hours
$durationHours = (int) filter_var($durationRaw, FILTER_SANITIZE_NUMBER_INT);
if ($durationHours < 1) $durationHours = 4;

// ── Resolve event_id from event_name ────────────────────────────────────────
$eventNameMap = [
    'Wedding'          => 'Wedding',
    'Birthday / Debut' => 'Birthday',
    'Prom / Ball'      => 'Prom',
    'Corporate Event'  => 'Corporate Event',
    'Reunion'          => 'Gala',
    'Anniversary'      => 'Gala',
];
$dbEventName = $eventNameMap[$eventType] ?? $eventType;

$stmt = $conn->prepare("SELECT event_id FROM event WHERE event_name = ? LIMIT 1");
$stmt->bind_param('s', $dbEventName);
$stmt->execute();
$eventRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$eventId = $eventRow ? (int) $eventRow['event_id'] : 1;

// ── Resolve addon prices from DB ─────────────────────────────────────────────
$addonPriceMap = [];
if (!empty($addons)) {
    $placeholders = implode(',', array_fill(0, count($addons), '?'));
    $types = 'i' . str_repeat('s', count($addons));
    $stmt = $conn->prepare(
        "SELECT addon_id, addon_name, price FROM addons
         WHERE event_id = ? AND addon_name IN ($placeholders)"
    );
    $stmt->bind_param($types, $eventId, ...$addons);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $addonPriceMap[$row['addon_name']] = [
            'id'    => (int)   $row['addon_id'],
            'price' => (float) $row['price'],
        ];
    }
    $stmt->close();
}

$addonsTotal = 0;
foreach ($addons as $addonName) {
    if (isset($addonPriceMap[$addonName])) {
        $addonsTotal += $addonPriceMap[$addonName]['price'];
    }
}
$totalPrice = $venuePrice + $addonsTotal;

// ── Get or create an active cart ─────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT cart_id FROM carts WHERE user_id = ? AND status = 'active' LIMIT 1"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$cartRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($cartRow) {
    $cartId = (int) $cartRow['cart_id'];
} else {
    $stmt = $conn->prepare("INSERT INTO carts (user_id, status) VALUES (?, 'active')");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $cartId = (int) $conn->insert_id;
    $stmt->close();
}

// ── Insert booking ────────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "INSERT INTO bookings
        (cart_id, user_id, venue_id, event_id, event_date, event_time,
         duration, guest_count, total_price, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
);
$stmt->bind_param(
    'iiiissiid',
    $cartId, $userId, $venueId, $eventId,
    $eventDate, $eventTime,
    $durationHours, $guestCount, $totalPrice
);

if (!$stmt->execute()) {
    error_log('Booking insert failed: ' . $stmt->error);
    die('Failed to save booking. Please try again.');
}
$bookingId = (int) $conn->insert_id;
$stmt->close();

// ── Insert payment record ────────────────────────────────────────────
$stmt = $conn->prepare(
    "INSERT INTO payments (booking_id, amount, method, status)
     VALUES (?, ?, 'gcash', 'pending')"
);
$stmt->bind_param('id', $bookingId, $totalPrice);

if (!$stmt->execute()) {
    error_log('Payment insert failed: ' . $stmt->error);
    die('Payment processing failed. Please try again.');
}
$stmt->close();


// ── Insert booking_addons ─────────────────────────────────────────────────────
if (!empty($addonPriceMap)) {
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO booking_addons (booking_id, addon_id, quantity, unit_price)
         VALUES (?, ?, 1, ?)"
    );
    foreach ($addons as $addonName) {
        if (isset($addonPriceMap[$addonName])) {
            $addonId    = $addonPriceMap[$addonName]['id'];
            $addonPrice = $addonPriceMap[$addonName]['price'];
            $stmt->bind_param('iid', $bookingId, $addonId, $addonPrice);
            $stmt->execute();
        }
    }
    $stmt->close();
}

// ── Mirror to session cart for cart.php display ───────────────────────────────
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
$_SESSION['cart'][] = [
    'booking_id'  => $bookingId,
    'cart_id'     => $cartId,
    'venue_id'    => $venueId,
    'venue_name'  => $venueName,
    'venue_price' => $venuePrice,
    'event_type'  => $eventType,
    'event_id'    => $eventId,
    'event_date'  => $eventDate,
    'event_time'  => $eventTime,
    'duration'    => $durationRaw,
    'guests'      => $guestCount,
    'addons'      => $addons,
    'total_price' => $totalPrice,
];

$_SESSION['last_venue_visited'] = 'venue.php?id=' . $venueId;

header('Location: cart.php');
exit();
