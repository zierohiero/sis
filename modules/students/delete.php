<?php
/**
 * Delete Student
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pdo = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    // Get student data first (for photo deletion)
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();

    if ($student) {
        // Delete photo if exists
        if (!empty($student['photo'])) {
            deleteFile($student['photo'], STUDENT_PHOTO_PATH);
        }

        // Delete student (cascade will handle related records)
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$id]);

        redirect('index.php', 'Data siswa berhasil dihapus', 'success');
    } else {
        redirect('index.php', 'Data siswa tidak ditemukan', 'danger');
    }
} else {
    redirect('index.php', 'Invalid request', 'danger');
}
