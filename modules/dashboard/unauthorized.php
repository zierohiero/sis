<?php
/**
 * Unauthorized Access Page
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

$pageTitle = 'Akses Ditolak';
$school = getSchoolProfile();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak - <?= $school ? htmlspecialchars($school['name']) : 'SIS' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .error-container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .error-icon {
            font-size: 80px;
            color: #f56565;
            margin-bottom: 20px;
        }

        .error-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 15px;
        }

        .error-message {
            color: var(--gray-color);
            margin-bottom: 30px;
        }

        .btn-back {
            display: inline-block;
            padding: 12px 30px;
            background: var(--primary-color);
            color: white;
            border-radius: var(--radius-md);
            text-decoration: none;
            transition: background 0.3s;
        }

        .btn-back:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h1 class="error-title">Akses Ditolak</h1>
        <p class="error-message">
            Maaf, Anda tidak memiliki izin untuk mengakses halaman ini.
            Silakan hubungi administrator jika Anda merasa ini adalah kesalahan.
        </p>
        <a href="<?= BASE_URL ?>modules/dashboard/index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>
</body>
</html>
