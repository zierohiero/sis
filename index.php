<?php
/**
 * Main Entry Point
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/includes/init.php';

// If logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'modules/dashboard/index.php');
    exit;
}

// Otherwise, redirect to login
header('Location: ' . BASE_URL . 'modules/auth/login.php');
exit;
