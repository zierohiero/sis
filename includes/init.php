<?php
/**
 * Application Initialization
 * Start session and include required files
 */

// Include helper functions FIRST (before calling any functions)
require_once __DIR__ . '/functions.php';

// Include configuration
require_once __DIR__ . '/../config/database.php';

// Start secure session (now that functions are loaded)
secureSessionStart();

// Set error reporting based on environment
define('DEBUG_MODE', getenv('APP_ENV') !== 'production');

if (DEBUG_MODE) {
    // Development environment
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // Production environment
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// Security headers
require_once __DIR__ . '/security_header.php';

// HTTPS Enforcement - Redirect HTTP to HTTPS
if (getenv('APP_ENV') === 'production' && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
    $httpsUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $httpsUrl);
    exit;
}
