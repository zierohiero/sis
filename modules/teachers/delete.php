<?php
/**
 * Delete Teacher
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pdo = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    // Get teacher data first
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
    $stmt->execute([$id]);
    $teacher = $stmt->fetch();

    if ($teacher) {
        // Delete photo
        if (!empty($teacher['photo'])) {
            deleteFile($teacher['photo'], TEACHER_PHOTO_PATH);
        }

        // Delete teacher
        $stmt = $pdo->prepare("DELETE FROM teachers WHERE id = ?");
        $stmt->execute([$id]);

        redirect('index.php', 'Data guru berhasil dihapus', 'success');
    } else {
        redirect('index.php', 'Data guru tidak ditemukan', 'danger');
    }
} else {
    redirect('index.php', 'Invalid request', 'danger');
}
