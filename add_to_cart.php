<?php
require_once 'config/database.php';
require_once 'config/session_config.php';
require_once 'config/app.php';

$baseUrl = getBaseUrl();

// Check if user session expired (no cookie but had session)
if (!isset($_COOKIE['user_session']) && isset($_SESSION['current_user'])) {
    session_destroy();
    $_SESSION = [];
    header("Location: index.php?expired=true");
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Refresh the cookie to extend 7 days
if (isset($_SESSION['current_user'])) {
    setcookie('user_session', $_SESSION['current_user']['email'], time() + (60 * 60 * 24 * 7), '/');
}

$item = [
    'venue_id'   => $_POST['venue_id'],
    'venue_name' => $_POST['venue_name'],
    'venue_price'=> $_POST['venue_price'],
    'guest_name' => $_POST['guest_name'],
    'event_type' => $_POST['event_type'],
    'event_date' => $_POST['event_date'],
    'event_time' => $_POST['event_time'],
    'duration'   => $_POST['duration'],
    'guests'     => $_POST['guests'],
    'addons'     => $_POST['addons'] ?? []
];

// init cart if wala pa
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// add item
$_SESSION['cart'][] = $item;

// redirect to cart page
header("Location: cart.php");
exit();
?>