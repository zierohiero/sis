<?php
/**
 * Login Page
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'modules/dashboard/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Sesi Anda telah kadaluwarsa. Silakan coba lagi.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Username dan password harus diisi';
        } elseif (isRateLimited($username)) {
            $error = 'Terlalu banyak percobaan login gagal. Silakan tunggu 15 menit.';
            logActivity('login_failed_rate_limit', 'auth', "Rate limit exceeded for user: $username");
        } else {
        $pdo = getDBConnection();

        $stmt = $pdo->prepare("
            SELECT u.*, t.name as teacher_name
            FROM users u
            LEFT JOIN teachers t ON u.id = t.user_id
            WHERE u.username = ? AND u.status = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && verifyPassword($password, $user['password'])) {
            // Clear rate limit on successful login
            clearRateLimit($username);

            // Regenerate session ID to prevent session fixation attack
            session_regenerate_id(true);

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_name'] = $user['teacher_name'] ?? $user['username'];

            // Log successful login
            logActivity('login', 'auth', "User logged in successfully", $user['id']);

            // Redirect based on role
            $redirectMap = [
                'Administrator' => 'modules/dashboard/index.php',
                'Wali Kelas' => 'modules/dashboard/index.php',
                'Guru' => 'modules/dashboard/index.php',
                'Ustaz' => 'modules/dashboard/index.php',
            ];

            $redirect = $redirectMap[$user['role']] ?? 'modules/dashboard/index.php';
            header('Location: ' . BASE_URL . $redirect);
            exit;
        } else {
            // Log failed login attempt
            logActivity('login_failed', 'auth', "Failed login attempt for user: $username");
            $error = 'Username atau password salah';
        }
    }
    }
}

$school = getSchoolProfile();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= $school ? htmlspecialchars($school['name']) : 'Sistem Informasi Sekolah' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <?php if ($school && !empty($school['logo'])): ?>
                    <img src="<?= BASE_URL ?><?= htmlspecialchars($school['logo']) ?>" alt="Logo Sekolah" class="school-logo">
                <?php else: ?>
                    <div class="school-logo-placeholder">
                        <i class="fas fa-school"></i>
                    </div>
                <?php endif; ?>
                <h1><?= $school ? htmlspecialchars($school['name']) : 'Sistem Informasi Sekolah' ?></h1>
                <p class="text-muted">Silakan login untuk melanjutkan</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Username
                    </label>
                    <input type="text" id="username" name="username" required autofocus
                           placeholder="Masukkan username" autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="password-input">
                        <input type="password" id="password" name="password" required
                               placeholder="Masukkan password" autocomplete="current-password">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggle-icon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div class="login-footer">
                <p>&copy; <?= date('Y') ?> <?= $school ? htmlspecialchars($school['name']) : 'Sistem Informasi Sekolah' ?></p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggle-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
