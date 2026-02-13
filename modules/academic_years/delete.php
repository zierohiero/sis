<?php
/**
 * Delete Academic Year
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pdo = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    // Check if active
    $stmt = $pdo->prepare("SELECT status FROM academic_years WHERE id = ?");
    $stmt->execute([$id]);
    $ay = $stmt->fetch();

    if ($ay && $ay['status'] === 'Aktif') {
        redirect('index.php', 'Tidak dapat menghapus tahun pelajaran yang sedang aktif', 'danger');
    }

    // Check if has classes
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM homeroom_teachers WHERE academic_year_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetch()['count'];

    if ($count > 0) {
        redirect('index.php', 'Tidak dapat menghapus tahun pelajaran yang sudah memiliki kelas', 'danger');
    }

    $stmt = $pdo->prepare("DELETE FROM academic_years WHERE id = ?");
    $stmt->execute([$id]);

    redirect('index.php', 'Data tahun pelajaran berhasil dihapus', 'success');
} else {
    redirect('index.php', 'Invalid request', 'danger');
}
