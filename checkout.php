<?php
require_once 'config/session_config.php';

$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    header('Location: cart.php');
    exit();
}

// Use first item in cart to prefill payment page
$item = reset($cart);
$query = http_build_query([
    'venue_id' => $item['venue_id'] ?? 1,
    'venue_name' => $item['venue_name'] ?? '',
    'venue_price' => $item['venue_price'] ?? 0,
    'event_type' => $item['event_type'] ?? '',
    'date' => $item['event_date'] ?? '',
    'time' => $item['event_time'] ?? '',
    'duration' => $item['duration'] ?? '',
    'guests' => $item['guests'] ?? 0,
    'name' => $item['guest_name'] ?? ''
]);

header('Location: payment.php?' . $query);
exit();
