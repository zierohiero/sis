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
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: {
                        50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc',
                        400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca',
                        800: '#3730a3', 900: '#312e81', 950: '#1e1b4b'
                    }
                },
                fontFamily: {
                    sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                },
            },
        },
    }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-50 font-sans antialiased flex items-center justify-center p-5">

    <div class="w-full max-w-sm">
        <div class="rounded-2xl border border-slate-200 bg-white px-8 py-10 shadow-sm">

            <!-- Header -->
            <div class="mb-8 text-center">
                <?php if ($school && !empty($school['logo'])): ?>
                    <img src="<?= BASE_URL ?><?= htmlspecialchars($school['logo']) ?>" alt="Logo Sekolah"
                         class="mx-auto mb-5 h-16 w-16 rounded-xl object-contain">
                <?php else: ?>
                    <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-xl bg-primary-600 text-2xl text-white">
                        <i class="fas fa-school"></i>
                    </div>
                <?php endif; ?>
                <h1 class="text-xl font-bold tracking-tight text-slate-900"><?= $school ? htmlspecialchars($school['name']) : 'Sistem Informasi Sekolah' ?></h1>
                <p class="mt-1.5 text-sm text-slate-500">Silakan login untuk melanjutkan</p>
            </div>

            <!-- Error alert -->
            <?php if (!empty($error)): ?>
                <div class="mb-5 flex items-center gap-2.5 rounded-lg border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-800">
                    <i class="fas fa-exclamation-circle shrink-0 text-red-500"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div>
                    <label for="username" class="mb-1.5 block text-sm font-medium text-slate-700">
                        <i class="fas fa-user mr-1 text-xs text-primary-600"></i> Username
                    </label>
                    <input type="text" id="username" name="username" required autofocus
                           placeholder="Masukkan username" autocomplete="username"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-400 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">
                        <i class="fas fa-lock mr-1 text-xs text-primary-600"></i> Password
                    </label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required
                               placeholder="Masukkan password" autocomplete="current-password"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 pr-11 text-sm text-slate-800 placeholder-slate-400 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                        <button type="button" onclick="togglePassword()"
                                class="absolute right-1 top-1/2 -translate-y-1/2 rounded-md p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-primary-600">
                            <i class="fas fa-eye text-sm" id="toggle-icon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white transition-all hover:bg-primary-700 hover:shadow-md active:scale-[0.99]">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <!-- Footer -->
            <div class="mt-6 border-t border-slate-100 pt-5 text-center">
                <p class="text-xs text-slate-400">&copy; <?= date('Y') ?> <?= $school ? htmlspecialchars($school['name']) : 'Sistem Informasi Sekolah' ?></p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            var passwordInput = document.getElementById('password');
            var toggleIcon = document.getElementById('toggle-icon');
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
