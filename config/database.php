<?php
/**
 * Database Configuration
 * Sistem Informasi Sekolah
 */

// Load environment variables from .env file if exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove quotes if present
        $value = trim($value, '"\'');
        if (!defined($key)) {
            define($key, $value);
        }
    }
}

// Fallback to default values if not defined
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'sis_db');
if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost/sis/');

// Base Path
define('BASE_PATH', dirname(__DIR__));

// Upload directories
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/');
define('STUDENT_PHOTO_PATH', UPLOAD_PATH . 'students/');
define('TEACHER_PHOTO_PATH', UPLOAD_PATH . 'teachers/');

// Timezone
date_default_timezone_set('Asia/Jakarta');

/**
 * Get Database Connection
 *
 * @return PDO
 */
function getDBConnection()
{
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
    // Log error ke file, jangan tampilkan ke user
    error_log("Database Connection Error: " . $e->getMessage());

    // Di production: tampilkan pesan generik
    if (getenv('APP_ENV') === 'production') {
        die("Terjadi kesalahan koneksi database. Silakan hubungi administrator.");
    }

    // Di development: tampilkan error untuk debugging
    die("Database Connection Failed: " . $e->getMessage());
}
}

/**
 * Get Active Academic Year
 *
 * @return array|null
 */
function getActiveAcademicYear()
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM academic_years WHERE status = 'Aktif' LIMIT 1");
    $stmt->execute();
    return $stmt->fetch();
}

/**
 * Get School Profile
 *
 * @return array|null
 */
function getSchoolProfile()
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM school_profile LIMIT 1");
    $stmt->execute();
    return $stmt->fetch();
}
