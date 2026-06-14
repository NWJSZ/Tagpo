<?php
require_once 'config/database.php';
require_once 'config/session_config.php';

if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

$user    = getCurrentUser();
$userId  = (int) ($user['id'] ?? 0);

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

$venueId    = filter_input(INPUT_POST, 'venue_id', FILTER_VALIDATE_INT);
$rating     = filter_input(INPUT_POST, 'rating',   FILTER_VALIDATE_INT);
$reviewText = trim($_POST['review_text'] ?? '');

if (!$userId) {
    die('Session error: Hindi matukoy ang user profile mo.');
}

if (!$venueId || !$rating || $rating < 1 || $rating > 5 || $reviewText === '') {
    die('Kailangan sagutan ang lahat ng fields at ang rating ay 1 hanggang 5.');
}

$stmt = $conn->prepare("SELECT id FROM venues WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $venueId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    $stmt->close();
    die('Hindi mahanap ang venue na ito.');
}
$stmt->close();

$stmt = $conn->prepare(
    "INSERT INTO reviews (user_id, venue_id, rating, review_text, review_date)
     VALUES (?, ?, ?, ?, NOW())"
);
$stmt->bind_param('iiis', $userId, $venueId, $rating, $reviewText);

if ($stmt->execute()) {
    header('Location: venue.php?id=' . $venueId . '&review=success');
    exit();
} else {
    error_log('Review insert failed: ' . $stmt->error);
    die('Paumanhin, nagka-error sa pag-save ng review mo.');
}