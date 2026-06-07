<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================
// SESSION HELPERS
// =====================

function isLoggedIn() {
    return isset($_SESSION['current_user']);
}

function getCurrentUser() {
    return $_SESSION['current_user'] ?? null;
}

function isAdmin() {
    return isset($_SESSION['current_user']['role']) &&
           $_SESSION['current_user']['role'] === 'admin';
}

function getCartCount() {
    if (!isset($_SESSION['cart'])) {
        return 0;
    }

    return count($_SESSION['cart']);
}