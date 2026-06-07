<?php
/**
 * Database Connection Handler
 * Manages all database connections and queries
 * 
 * Database credentials should be set in .env file or environment variables
 */

// Load environment variables if .env exists
if (file_exists(dirname(__DIR__) . '/.env')) {
    $env = parse_ini_file(dirname(__DIR__) . '/.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

// Database configuration from environment or defaults
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'tagpo_db');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    // Log error for debugging (don't expose to user)
    error_log("Database connection failed: " . $conn->connect_error);
    
    // Show user-friendly error
    die("Database connection failed. Please try again later.");
}

// Set charset to UTF-8 for proper encoding
if (!$conn->set_charset("utf8mb4")) {
    error_log("Error setting charset: " . $conn->error);
}

// ========================================
// HELPER FUNCTIONS
// ========================================

/**
 * Execute a query and return result
 */
function executeQuery($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result && $conn->error) {
        error_log("Query failed: " . $conn->error . " | SQL: " . $sql);
        throw new Exception("Database query failed");
    }
    return $result;
}

/**
 * Get a single row from database
 */
function getRow($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        error_log("Query error: " . $conn->error);
        return null;
    }
    return $result->fetch_assoc();
}

/**
 * Get multiple rows from database
 */
function getRows($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        error_log("Query error: " . $conn->error);
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Execute prepared statement safely (prevents SQL injection)
 */
function preparedQuery($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        throw new Exception("Database error");
    }
    
    // Bind parameters
    $stmt->bind_param($types, ...$params);
    
    // Execute
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        throw new Exception("Database error");
    }
    
    return $stmt->get_result();
}

/**
 * Get last inserted ID
 */
function getLastInsertId($conn) {
    return $conn->insert_id;
}

/**
 * Get number of affected rows
 */
function getAffectedRows($conn) {
    return $conn->affected_rows;
}

/**
 * Escape string for safe SQL
 */
function escapeString($conn, $str) {
    return $conn->real_escape_string($str);
}

function getCartCount($conn, $userId) {
    $sql = "SELECT COUNT(*) as total FROM cart_items WHERE user_id = $userId";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    return $row['total'] ?? 0;
}

?>
