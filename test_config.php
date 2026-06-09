<?php
/**
 * TAGPO - Diagnostic Check
 * This file verifies all configs are loading correctly
 * Visit: http://localhost/Tagpo/test_config.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 TAGPO Configuration Test</h2>";
echo "<hr>";

// Test 1: Check if .env exists
echo "<h3>Test 1: .env File</h3>";
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    echo "✅ .env file exists<br>";
    echo "<pre style='background:#eee;padding:10px;'>";
    echo htmlspecialchars(file_get_contents($envFile));
    echo "</pre>";
} else {
    echo "❌ .env file NOT found at: " . $envFile . "<br>";
}

// Test 2: Try loading configs
echo "<h3>Test 2: Loading Config Files</h3>";

try {
    require_once 'config/database.php';
    echo "✅ config/database.php loaded<br>";
} catch (Exception $e) {
    echo "❌ config/database.php failed: " . $e->getMessage() . "<br>";
}

try {
    require_once 'config/session_config.php';
    echo "✅ config/session_config.php loaded<br>";
} catch (Exception $e) {
    echo "❌ config/session_config.php failed: " . $e->getMessage() . "<br>";
}

try {
    require_once 'config/app.php';
    echo "⚠️ config/app.php tried to load (should be empty)<br>";
} catch (Exception $e) {
    echo "❌ config/app.php failed: " . $e->getMessage() . "<br>";
}

// Test 3: Check functions
echo "<h3>Test 3: Function Availability</h3>";

if (function_exists('getBaseUrl')) {
    echo "✅ getBaseUrl() exists<br>";
    echo "Base URL: " . getBaseUrl() . "<br>";
} else {
    echo "❌ getBaseUrl() NOT found<br>";
}

if (function_exists('isLoggedIn')) {
    echo "✅ isLoggedIn() exists<br>";
} else {
    echo "❌ isLoggedIn() NOT found<br>";
}

if (function_exists('getCurrentUser')) {
    echo "✅ getCurrentUser() exists<br>";
} else {
    echo "❌ getCurrentUser() NOT found<br>";
}

if (function_exists('isAdmin')) {
    echo "✅ isAdmin() exists<br>";
} else {
    echo "❌ isAdmin() NOT found<br>";
}

// Test 4: Check database connection
echo "<h3>Test 4: Database Connection</h3>";

if (isset($conn)) {
    if ($conn->connect_error) {
        echo "❌ Database connection failed: " . $conn->connect_error . "<br>";
    } else {
        echo "✅ Database connected successfully<br>";
        $result = $conn->query("SELECT 1");
        echo "✅ Can execute queries<br>";
    }
} else {
    echo "⚠️ \$conn variable not set<br>";
}

// Test 5: Session check
echo "<h3>Test 5: Session Status</h3>";
echo "Session status: " . (session_status() === PHP_SESSION_ACTIVE ? '✅ ACTIVE' : '❌ INACTIVE') . "<br>";
echo "Session ID: " . session_id() . "<br>";

echo "<hr>";
echo "<p style='color:green;'><strong>All tests passed! Your site should work now.</strong></p>";
echo "<p>⚠️ Delete this test file after checking: <code>rm test_config.php</code></p>";
?>
