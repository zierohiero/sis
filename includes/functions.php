<?php
/**
 * Helper Functions
 * Sistem Informasi Sekolah
 */

function sanitize($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function hasRole($roles)
{
    if (!isset($_SESSION['user_role'])) {
        return false;
    }

    if (!is_array($roles)) {
        $roles = array($roles);
    }

    return in_array($_SESSION['user_role'], $roles);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'modules/auth/login.php');
        exit;
    }
}

function requireRole($roles)
{
    requireLogin();
    if (!hasRole($roles)) {
        header('Location: ' . BASE_URL . 'modules/dashboard/unauthorized.php');
        exit;
    }
}

function redirect($url, $message = '', $type = 'success')
{
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header('Location: ' . $url);
    exit;
}

function getFlashMessage()
{
    if (isset($_SESSION['flash_message'])) {
        $message = array(
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type']
        );
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return $message;
    }
    return null;
}

function formatDate($date, $format = 'd/m/Y')
{
    if (empty($date) || $date == '0000-00-00') {
        return '-';
    }
    return date($format, strtotime($date));
}

function formatIndonesianDate($date)
{
    if (empty($date) || $date == '0000-00-00') {
        return '-';
    }

    $months = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );

    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = $months[date('n', $timestamp)];
    $year = date('Y', $timestamp);

    return "$day $month $year";
}

function getGradePredicate($score)
{
    if ($score >= 90) {
        return array('predicate' => 'A', 'description' => 'Sangat Baik');
    } elseif ($score >= 80) {
        return array('predicate' => 'B', 'description' => 'Baik');
    } elseif ($score >= 70) {
        return array('predicate' => 'C', 'description' => 'Cukup');
    } elseif ($score >= 60) {
        return array('predicate' => 'D', 'description' => 'Kurang');
    } else {
        return array('predicate' => 'E', 'description' => 'Sangat Kurang');
    }
}

function uploadFile($file, $targetDir, $maxSize = 2097152) // 2MB default
{
    // Check upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return array('success' => false, 'message' => 'Upload gagal');
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return array('success' => false, 'message' => 'Ukuran file terlalu besar (max ' . ($maxSize/1024/1024) . 'MB)');
    }
    
    // Get MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf'
    );
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate both MIME type and extension
    if (!array_key_exists($mimeType, $allowedMimes) || 
        !in_array($extension, array_values($allowedMimes))) {
        return array('success' => false, 'message' => 'Tipe file tidak diizinkan');
    }
    
    // Verify extension matches MIME type
    if ($extension !== $allowedMimes[$mimeType]) {
        return array('success' => false, 'message' => 'Extensi file tidak valid');
    }
    
    // Create directory if not exists
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // Generate random filename (not predictable)
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $targetPath = $targetDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // For images: validate it's a real image
        if (strpos($mimeType, 'image/') === 0) {
            $imageInfo = getimagesize($targetPath);
            if ($imageInfo === false) {
                unlink($targetPath);
                return array('success' => false, 'message' => 'File gambar tidak valid');
            }
        }
        
        return array('success' => true, 'filename' => $filename);
    }
    
    return array('success' => false, 'message' => 'Gagal menyimpan file');
}

function deleteFile($filename, $targetDir)
{
    $filepath = $targetDir . $filename;
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

function hashPassword($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

function getUserName($userId)
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT u.username, t.name FROM users u LEFT JOIN teachers t ON u.id = t.user_id WHERE u.id = ?");
    $stmt->execute(array($userId));
    $result = $stmt->fetch();
    return $result ? $result['username'] : 'Unknown';
}

function paginate($total, $perPage = 10, $currentPage = 1)
{
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return array(
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset
    );
}

function buildPagination($pagination, $urlPattern = '?page=%d')
{
    $html = '<nav aria-label="Page navigation"><ul class="pagination">';

    if ($pagination['current_page'] > 1) {
        $html .= sprintf('<li class="page-item"><a class="page-link" href="%s">Previous</a></li>', sprintf($urlPattern, $pagination['current_page'] - 1));
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }

    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $pagination['current_page']) {
            $html .= sprintf('<li class="page-item active"><span class="page-link">%s</span></li>', $i);
        } else {
            $html .= sprintf('<li class="page-item"><a class="page-link" href="%s">%s</a></li>', sprintf($urlPattern, $i), $i);
        }
    }

    if ($pagination['current_page'] < $pagination['total_pages']) {
        $html .= sprintf('<li class="page-item"><a class="page-link" href="%s">Next</a></li>', sprintf($urlPattern, $pagination['current_page'] + 1));
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

function getLevelLabel($level)
{
    $labels = array(
        'X' => 'Kelas X',
        'XI' => 'Kelas XI',
        'XII' => 'Kelas XII'
    );
    return $labels[$level] ?? $level;
}

function getSemesterLabel($semester)
{
    $labels = array(
        'Gasal' => 'Semester Gasal (Ganjil)',
        'Genap' => 'Semester Genap'
    );
    return $labels[$semester] ?? $semester;
}

function calculateAge($birthDate)
{
    if (empty($birthDate) || $birthDate == '0000-00-00') {
        return 0;
    }
    return floor((time() - strtotime($birthDate)) / 31556926);
}

function formatPhone($phone)
{
    if (empty($phone)) {
        return '-';
    }
    return $phone;
}

function truncate($text, $length = 50, $suffix = '...')
{
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getGreeting()
{
    $hour = date('H');

    if ($hour >= 4 && $hour < 10) {
        return 'Selamat Pagi';
    } elseif ($hour >= 10 && $hour < 15) {
        return 'Selamat Siang';
    } elseif ($hour >= 15 && $hour < 18) {
        return 'Selamat Sore';
    } else {
        return 'Selamat Malam';
    }
}

function hasActiveAcademicYear()
{
    return getActiveAcademicYear() !== null;
}

function getRoleMenuItems($role)
{
    $menus = array(
        'Administrator' => array(
            array('icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'url' => 'modules/dashboard/index.php'),
            array('icon' => 'fas fa-calendar-alt', 'text' => 'Tahun Pelajaran', 'url' => 'modules/academic_years/index.php'),
            array('icon' => 'fas fa-school', 'text' => 'Profil Sekolah', 'url' => 'admin/school_profile.php'),
            array('icon' => 'fas fa-users-cog', 'text' => 'Pengguna', 'url' => 'modules/users/index.php'),
            array('icon' => 'fas fa-chalkboard-teacher', 'text' => 'Guru', 'url' => 'modules/teachers/index.php'),
            array('icon' => 'fas fa-user-graduate', 'text' => 'Siswa', 'url' => 'modules/students/index.php'),
            array('icon' => 'fas fa-door-open', 'text' => 'Kelas', 'url' => 'modules/classes/index.php'),
            array('icon' => 'fas fa-book', 'text' => 'Mata Pelajaran', 'url' => 'modules/subjects/index.php'),
            array('icon' => 'fas fa-user-tie', 'text' => 'Wali Kelas', 'url' => 'admin/homeroom_teachers.php'),
            array('icon' => 'fas fa-user-plus', 'text' => 'Pengelompokan Siswa', 'url' => 'admin/student_enrollments.php'),
            array('icon' => 'fas fa-chalkboard', 'text' => 'Pengampu Mapel', 'url' => 'admin/subject_allocations.php'),
            array('icon' => 'fas fa-bullseye', 'text' => 'Kompetensi', 'url' => 'admin/competencies.php'),
            array('icon' => 'fas fa-star', 'text' => 'Nilai Formatif', 'url' => 'modules/grades/formative.php'),
            array('icon' => 'fas fa-clipboard-check', 'text' => 'Nilai Sumatif', 'url' => 'modules/grades/summative.php'),
            array('icon' => 'fas fa-futbol', 'text' => 'Ekstrakurikuler', 'url' => 'modules/extracurricular/index.php'),
            array('icon' => 'fas fa-clipboard-list', 'text' => 'Presensi', 'url' => 'admin/attendances.php'),
            array('icon' => 'fas fa-print', 'text' => 'Cetak Rapor', 'url' => 'modules/reports/index.php'),
        ),
        'Wali Kelas' => array(
            array('icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'url' => 'modules/dashboard/index.php'),
            array('icon' => 'fas fa-user-graduate', 'text' => 'Siswa', 'url' => 'modules/students/index.php'),
            array('icon' => 'fas fa-bullseye', 'text' => 'Kompetensi', 'url' => 'admin/competencies.php'),
            array('icon' => 'fas fa-star', 'text' => 'Nilai Formatif', 'url' => 'modules/grades/formative.php'),
            array('icon' => 'fas fa-clipboard-check', 'text' => 'Nilai Sumatif', 'url' => 'modules/grades/summative.php'),
            array('icon' => 'fas fa-futbol', 'text' => 'Ekstrakurikuler', 'url' => 'modules/extracurricular/index.php'),
            array('icon' => 'fas fa-clipboard-list', 'text' => 'Presensi', 'url' => 'admin/attendances.php'),
            array('icon' => 'fas fa-print', 'text' => 'Cetak Rapor', 'url' => 'modules/reports/index.php'),
        ),
        'Guru' => array(
            array('icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'url' => 'modules/dashboard/index.php'),
            array('icon' => 'fas fa-bullseye', 'text' => 'Kompetensi', 'url' => 'admin/competencies.php'),
            array('icon' => 'fas fa-star', 'text' => 'Nilai Formatif', 'url' => 'modules/grades/formative.php'),
            array('icon' => 'fas fa-clipboard-check', 'text' => 'Nilai Sumatif', 'url' => 'modules/grades/summative.php'),
        ),
        'Ustaz' => array(
            array('icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'url' => 'modules/dashboard/index.php'),
            array('icon' => 'fas fa-clipboard-check', 'text' => 'Nilai Sumatif', 'url' => 'modules/grades/summative.php'),
        ),
    );

    return $menus[$role] ?? array();
}

/**
 * Validate Email
 *
 * @param string $email
 * @return bool
 */
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate Phone Number (Indonesia format)
 *
 * @param string $phone
 * @return bool
 */
function validatePhone($phone)
{
    // Remove spaces, dashes, parentheses
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Indonesia phone: 08-12 digits, starting with 08 or 62
    return preg_match('/^(08|62)[0-9]{8,11}$/', $phone);
}

/**
 * Validate NIS (Nomor Induk Siswa)
 *
 * @param string $nis
 * @return bool
 */
function validateNIS($nis)
{
    // NIS: numeric, 4-10 digits
    return preg_match('/^[0-9]{4,10}$/', $nis);
}

/**
 * Validate NISN (Nomor Induk Siswa Nasional)
 *
 * @param string $nisn
 * @return bool
 */
function validateNISN($nisn)
{
    // NISN: 10 digits
    return preg_match('/^[0-9]{10}$/', $nisn);
}

/**
 * Log Activity to Audit Trail
 *
 * @param string $action Action performed (login, logout, create, update, delete, etc.)
 * @param string $module Module name (students, teachers, users, etc.)
 * @param string $description Description of the action
 * @param int|null $targetId ID of affected record
 * @return bool
 */
function logActivity($action, $module, $description = '', $targetId = null)
{
    if (!file_exists(__DIR__ . '/../logs/audit.log')) {
        return false;
    }
    
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NULL';
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $timestamp = date('Y-m-d H:i:s');
    
    $logEntry = sprintf(
        "[%s] User:%s(%s) Role:%s IP:%s Action:%s Module:%s ID:%s - %s",
        $timestamp,
        $username,
        $userId,
        $role,
        $ip,
        $action,
        $module,
        $targetId ?? 'N/A',
        $description
    );
    
    return file_put_contents(
        __DIR__ . '/../logs/audit.log',
        $logEntry . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Check Login Rate Limit (Simple implementation)
 * Max 5 failed attempts per 15 minutes
 *
 * @param string $username
 * @return bool True if rate limited
 */
function isRateLimited($username)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $key = 'login_attempt_' . md5($ip . '_' . $username);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = array(
            'count' => 0,
            'first_attempt' => time()
        );
    }
    
    $attempts = $_SESSION[$key];
    $timeWindow = 15 * 60; // 15 minutes
    
    // Reset if time window passed
    if (time() - $attempts['first_attempt'] > $timeWindow) {
        $_SESSION[$key] = array(
            'count' => 0,
            'first_attempt' => time()
        );
        return false;
    }
    
    // Increment attempt count
    $_SESSION[$key]['count']++;
    
    // Check if exceeded
    return $_SESSION[$key]['count'] > 5;
}

/**
 * Clear Rate Limit (call on successful login)
 *
 * @param string $username
 * @return void
 */
function clearRateLimit($username)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $key = 'login_attempt_' . md5($ip . '_' . $username);
    
    if (isset($_SESSION[$key])) {
        unset($_SESSION[$key]);
    }
}

/**
 * Secure Session Initialization
 */
function secureSessionStart()
{
    // Set custom session parameters
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1'); // HTTPS only
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', '1800'); // 30 minutes
    
    session_start();
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    } else if (time() - $_SESSION['created'] > 900) {
        // Regenerate every 15 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Validate session
    validateSession();
}

/**
 * Validate Session (IP binding & User Agent check)
 */
function validateSession()
{
    if (isset($_SESSION['ip']) && isset($_SESSION['user_agent'])) {
        // Check IP address
        if ($_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
            sessionDestroy();
            redirect(BASE_URL . 'modules/auth/login.php');
        }
        
        // Check user agent
        if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            sessionDestroy();
            redirect(BASE_URL . 'modules/auth/login.php');
        }
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        sessionDestroy();
        redirect(BASE_URL . 'modules/auth/login.php');
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Destroy Session Securely
 */
function sessionDestroy()
{
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Validate Password Strength
 * 
 * Requirements:
 * - Minimum 8 characters
 * - At least 1 uppercase letter
 * - At least 1 lowercase letter
 * - At least 1 number
 * - At least 1 special character
 */
function validatePasswordStrength($password)
{
    $errors = array();
    
    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password harus mengandung huruf kapital';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password harus mengandung huruf kecil';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password harus mengandung angka';
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password harus mengandung karakter spesial';
    }
    
    return empty($errors) ? true : $errors;
}
