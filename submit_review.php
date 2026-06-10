<?php
require_once 'config/database.php';
require_once 'config/session_config.php';

if (!isLoggedIn()) {
    header("Location: auth/login.php");
    exit;
}

$users = getCurrentUser();

$id = $users['id'] ?? null;

if (!$id && !empty($users['email'])) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $users['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $id = (int) $row['id'];
        $_SESSION['current_user']['id'] = $id;
    }
    $stmt->close();
}

$venue_id = filter_input(INPUT_POST, 'venue_id', FILTER_VALIDATE_INT);
$rating   = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
$text     = trim($_POST['review_text'] ?? '');

/* =========================
   VALIDATION
========================= */
if (!$id) {
    die("Session error: user not found in database");
}

if (!$venue_id || !$rating || $rating < 1 || $rating > 5 || $text === '') {
    die("Missing required fields");
}

/* =========================
   INSERT REVIEW
========================= */
$stmt = $conn->prepare(
    "INSERT INTO reviews (id, venue_id, rating, review_text)
     VALUES (?, ?, ?, ?)"
);

$stmt->bind_param("iiis", $id, $venue_id, $rating, $text);

if ($stmt->execute()) {
    header("Location: venue.php?id=" . $venue_id);
    exit;
} else {
    echo "Error submitting review: " . htmlspecialchars($stmt->error);
}
?>
