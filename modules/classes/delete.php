<?php
/**
 * Delete Class
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pdo = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
    $stmt->execute([$id]);

    redirect('index.php', 'Data kelas berhasil dihapus', 'success');
} else {
    redirect('index.php', 'Invalid request', 'danger');
}
