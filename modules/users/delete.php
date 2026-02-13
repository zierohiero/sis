<?php
/**
 * Delete User
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pdo = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    // Check if it's the main admin
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if ($user && $user['username'] === 'admin') {
        redirect('index.php', 'Tidak dapat menghapus akun admin utama', 'danger');
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    redirect('index.php', 'Data pengguna berhasil dihapus', 'success');
} else {
    redirect('index.php', 'Invalid request', 'danger');
}
