<?php
/**
 * Session Configuration Handler
 * Manages session lifecycle, cookie duration, and inactivity timeout
 */

// Load environment variables if .env exists
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if ($envContent) {
        // Parse .env file manually to handle edge cases
        foreach (explode(PHP_EOL, $envContent) as $line) {
            $line = trim($line);
            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if (!empty($key)) {
                    putenv("$key=$value");
                }
            }
        }
    }
}

// Set session configuration BEFORE session_start()
if (session_status() === PHP_SESSION_NONE) {
    // Cookie will last 7 days
    ini_set('session.cookie_lifetime', 60 * 60 * 24 * 7);
    // Session garbage collection: 1 hour
    ini_set('session.gc_maxlifetime', 60 * 60);
    // Secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    session_start();
}

// ========================================
// INACTIVITY TIMEOUT CHECK (30 minutes)
// ========================================
define('INACTIVITY_TIMEOUT', 1800); // 30 minutes in seconds (1800)

if (isset($_SESSION['current_user'])) {
    // Check if last_activity is set
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
    
    // Check if user has been inactive
    $inactivityTime = time() - $_SESSION['last_activity'];
    
    if ($inactivityTime > INACTIVITY_TIMEOUT) {
        // User is inactive, destroy session
        session_unset();
        session_destroy();
        $_SESSION = [];
        
        // Clear user session cookie
        if (isset($_COOKIE['user_session'])) {
            setcookie('user_session', '', time() - 3600, '/');
        }
        
        // Redirect directly to login page with the inactivity trigger message
        header("Location: " . getBaseUrl() . "auth/login.php?session_expired=inactivity");
        exit();
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

// ========================================
// COOKIE & SESSION EXPIRATION CHECK
// ========================================
if (isset($_SESSION['current_user']) && !isset($_COOKIE['user_session'])) {
    // User exists in session but cookie was cleared/expired
    session_unset();
    session_destroy();
    $_SESSION = [];
    header("Location: " . getBaseUrl() . "auth/login.php?session_expired=true");
    exit();
}

// ========================================
// CART INITIALIZATION
// ========================================
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ========================================
// HELPER FUNCTION: Get base URL (FIXED)
// ========================================
function getBaseUrl() {
    // Get the base directory (one level above current script)
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
    
    // Calculate relative path from web root
    $relativePath = str_replace($docRoot, '', $scriptDir);
    
    // Remove any /auth, /admin, /config subdirectories from the end
    $relativePath = preg_replace('#/(auth|admin|config)/?$#', '', $relativePath);
    
    // Ensure it starts with /
    if (empty($relativePath) || $relativePath === '\\') {
        return '/';
    }
    
    return rtrim($relativePath, '/\\') . '/';
}

// ========================================
// HELPER FUNCTION: Check if user is logged in
// ========================================
function isLoggedIn() {
    return isset($_SESSION['current_user']) && !empty($_SESSION['current_user']);
}

// ========================================
// HELPER FUNCTION: Get current user
// ========================================
function getCurrentUser() {
    return $_SESSION['current_user'] ?? null;
}

// ========================================
// HELPER FUNCTION: Is admin
// ========================================
function isAdmin() {
    $user = getCurrentUser();
    return $user && isset($user['role']) && $user['role'] === 'admin';
}

// ========================================
// HELPER FUNCTION: Get cart count
// ========================================
function getCartCount() {
    return isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
}
?>