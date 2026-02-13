<?php
/**
 * Auto Attendance Creator
 * Menggantikan fungsi trigger untuk shared hosting
 * Panggil file ini setelah import database atau setelah enrollment siswa
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();

    // Cek enrollment yang belum punya attendance record
    $stmt = $pdo->query("
        SELECT se.id
        FROM student_enrollments se
        LEFT JOIN students_attendances sa ON se.id = sa.enrollment_id
        WHERE sa.id IS NULL
    ");

    $enrollments = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($enrollments) > 0) {
        $pdo->beginTransaction();

        $insert = $pdo->prepare("
            INSERT INTO students_attendances (enrollment_id, present, sick, permit, alpha)
            VALUES (?, 0, 0, 0, 0)
        ");

        foreach ($enrollments as $enrollmentId) {
            $insert->execute([$enrollmentId]);
        }

        $pdo->commit();
        echo "✅ Success: " . count($enrollments) . " attendance records created.\n";
    } else {
        echo "✅ All enrollments already have attendance records.\n";
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
