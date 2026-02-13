<?php
/**
 * Logout
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

// Log logout before destroying session
if (isLoggedIn()) {
    logActivity('logout', 'auth', 'User logged out');
}

// Destroy session using the secure function
sessionDestroy();

// Redirect to login
header('Location: ' . BASE_URL . 'modules/auth/login.php');
exit;
