<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_config.php';
require_once dirname(__DIR__) . '/config/app.php';

$baseUrl = '../';

// Update activity
$_SESSION['last_activity'] = time();

// Refresh cookie if logged in
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    setcookie('user_session', $currentUser['email'], time() + (60 * 60 * 24 * 7), '/');
}

// Security check - Admin only
if (!isAdmin()) {
    header("Location: ../index.php?expired=true");
    exit();
}

if (isset($_GET['id'])) {
    $id_to_delete = $_GET['id'];

    if (isset($_SESSION['venues'])) {
        foreach ($_SESSION['venues'] as $key => $venue) {
            // Kung nag-match yung ID, tatanggalin sa array
            if ($venue['id'] == $id_to_delete) {
                unset($_SESSION['venues'][$key]);
            }
        }
        // Re-index ang array para walang butas na index
        $_SESSION['venues'] = array_values($_SESSION['venues']);
    }
}

// Balik agad sa index pagkatapos i-delete
header("Location: ../index.php");
exit;
?>