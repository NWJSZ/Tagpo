<?php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tagpo_db');
define('DB_PORT', 3306);

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection failed. Please try again later.");
}

if (!$conn->set_charset("utf8mb4")) {
    error_log("Charset error: " . $conn->error);
}

// =====================
// DB FUNCTIONS
// =====================

function executeQuery($conn, $sql) {
    return $conn->query($sql);
}

function getRow($conn, $sql) {
    $result = $conn->query($sql);
    return $result ? $result->fetch_assoc() : null;
}

function getRows($conn, $sql) {
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function preparedQuery($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    return $stmt->get_result();
}

/**
 * Detect which foreign key column exists on the payments table.
 * Returns 'cart_id' or 'booking_id' (prefers cart_id if present), or null if none found.
 *
 * @param mysqli $conn
 * @return string|null
 */
function getPaymentsForeignKeyColumn(mysqli $conn): ?string
{
    $res = $conn->query("SHOW COLUMNS FROM `payments`");
    if (!$res) return null;

    $fields = [];
    while ($row = $res->fetch_assoc()) {
        $fields[] = $row['Field'];
    }

    // Prefer cart_id if both exist to match newer schema expectations
    if (in_array('cart_id', $fields, true)) return 'cart_id';
    if (in_array('booking_id', $fields, true)) return 'booking_id';
    return null;
}