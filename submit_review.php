<?php
require_once 'config/database.php';
require_once 'config/session_config.php';

if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

$user    = getCurrentUser();
$userId  = (int) ($user['id'] ?? 0);

// If user ID is missing from session, look it up by email
if (!$userId && !empty($user['email'])) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $user['email']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $userId = (int) $row['id'];
        $_SESSION['current_user']['id'] = $userId;
    }
}

// FIX: column is user_id not id in reviews table
$venueId    = filter_input(INPUT_POST, 'venue_id', FILTER_VALIDATE_INT);
$rating     = filter_input(INPUT_POST, 'rating',   FILTER_VALIDATE_INT);
$reviewText = trim($_POST['review_text'] ?? '');

if (!$userId) {
    die('Session error: could not identify user.');
}
if (!$venueId || !$rating || $rating < 1 || $rating > 5 || $reviewText === '') {
    die('All review fields are required and rating must be between 1 and 5.');
}

// Check venue exists
$stmt = $conn->prepare("SELECT id FROM venues WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $venueId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    $stmt->close();
    die('Invalid venue.');
}
$stmt->close();

// FIX: use correct column name user_id (not id) and correct column review_text
$stmt = $conn->prepare(
    "INSERT INTO reviews (user_id, venue_id, rating, review_text)
     VALUES (?, ?, ?, ?)"
);
$stmt->bind_param('iiis', $userId, $venueId, $rating, $reviewText);

if ($stmt->execute()) {
    header('Location: venue.php?id=' . $venueId . '&review=success');
    exit();
} else {
    error_log('Review insert failed: ' . $stmt->error);
    die('Failed to submit review. Please try again.');
}
