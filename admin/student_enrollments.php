<?php
/**
 * Student Enrollments
 * Pengelompokan Siswa ke Kelas
 */

require_once __DIR__ . '/../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pageTitle = 'Pengelompokan Siswa';
$pdo = getDBConnection();
$academicYear = getActiveAcademicYear();

if (!$academicYear) {
    redirect(BASE_URL . 'modules/dashboard/index.php', 'Silakan atur tahun pelajaran aktif terlebih dahulu', 'warning');
}

// Get filters
$classFilter = isset($_GET['class']) ? (int)$_GET['class'] : 0;

// Get classes
$classes = [];
$stmt = $pdo->query("
    SELECT c.*, ht.id as homeroom_id
    FROM classes c
    JOIN homeroom_teachers ht ON c.id = ht.class_id
    WHERE ht.academic_year_id = {$academicYear['id']}
    ORDER BY c.level, c.name
");
$classes = $stmt->fetchAll();

// Get enrolled students
$enrolledStudents = [];
if ($classFilter) {
    $stmt = $pdo->prepare("
        SELECT s.*, se.id as enrollment_id
        FROM students s
        JOIN student_enrollments se ON s.id = se.student_id
        WHERE se.homeroom_teacher_id = ?
        ORDER BY s.name
    ");

    // Get homeroom ID
    foreach ($classes as $c) {
        if ($c['id'] == $classFilter) {
            $stmt->execute([$c['homeroom_id']]);
            $enrolledStudents = $stmt->fetchAll();
            break;
        }
    }
}

// Get available students (not enrolled yet)
$availableStudents = [];
if ($classFilter) {
    $stmt = $pdo->prepare("
        SELECT s.* FROM students s
        WHERE s.status = 'Aktif'
        AND s.id NOT IN (
            SELECT student_id FROM student_enrollments se
            JOIN homeroom_teachers ht ON se.homeroom_teacher_id = ht.id
            WHERE ht.academic_year_id = ?
        )
        ORDER BY s.name
    ");
    $stmt->execute([$academicYear['id']]);
    $availableStudents = $stmt->fetchAll();
}

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_students'])) {
    $studentIds = $_POST['students'] ?? [];

    if (!empty($studentIds) && $classFilter) {
        $pdo->beginTransaction();

        try {
            foreach ($classes as $c) {
                if ($c['id'] == $classFilter) {
                    $homeroomId = $c['homeroom_id'];
                    break;
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO student_enrollments (student_id, homeroom_teacher_id)
                VALUES (?, ?)
            ");

            $attendanceStmt = $pdo->prepare("
                INSERT INTO students_attendances (enrollment_id, present, sick, permit, alpha)
                VALUES (?, 0, 0, 0, 0)
            ");

            foreach ($studentIds as $studentId) {
                $stmt->execute([(int)$studentId, $homeroomId]);
                $enrollmentId = $pdo->lastInsertId();
                // Create attendance record (menggantikan trigger)
                $attendanceStmt->execute([$enrollmentId]);
            }

            $pdo->commit();
            redirect('student_enrollments.php?class=' . $classFilter, 'Siswa berhasil ditambahkan', 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Handle removal
if (isset($_GET['remove'])) {
    $stmt = $pdo->prepare("DELETE FROM student_enrollments WHERE id = ?");
    $stmt->execute([(int)$_GET['remove']]);
    redirect('student_enrollments.php?class=' . $classFilter, 'Siswa berhasil dikeluarkan dari kelas', 'success');
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.filter-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.student-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 10px;
}

.student-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-bottom: 1px solid var(--border-color);
}

.student-item:last-child {
    border-bottom: none;
}

.student-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.student-item label {
    flex: 1;
    cursor: pointer;
}

.count-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
}

.count-badge.enrolled {
    background: #c6f6d5;
    color: #22543d;
}

.count-badge.available {
    background: #bee3f8;
    color: #2a4365;
}
</style>

<div class="page-header">
    <h1>Pengelompokan Siswa</h1>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <form method="GET" action="" class="filter-bar">
            <div style="flex: 1;">
                <select name="class" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['id'] ?>" <?= $classFilter == $class['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($classFilter): ?>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px;">
                <!-- Available Students -->
                <div>
                    <h3 style="margin-bottom: 15px;">
                        Siswa Tersedia
                        <span class="count-badge available"><?= count($availableStudents) ?> siswa</span>
                    </h3>

                    <?php if (empty($availableStudents)): ?>
                        <p class="text-muted">Tidak ada siswa tersedia.</p>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="add_students" value="1">
                            <div class="student-list">
                                <?php foreach ($availableStudents as $student): ?>
                                    <div class="student-item">
                                        <input type="checkbox" name="students[]" value="<?= $student['id'] ?>" id="student_<?= $student['id'] ?>">
                                        <label for="student_<?= $student['id'] ?>">
                                            <strong><?= htmlspecialchars($student['name']) ?></strong>
                                            <small>(<?= htmlspecialchars($student['nis'] ?? '-') ?>)</small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-primary" style="margin-top: 15px; width: 100%;">
                                <i class="fas fa-user-plus"></i> Tambahkan Siswa Terpilih
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Enrolled Students -->
                <div>
                    <h3 style="margin-bottom: 15px;">
                        Siswa Terdaftar
                        <span class="count-badge enrolled"><?= count($enrolledStudents) ?> siswa</span>
                    </h3>

                    <?php if (empty($enrolledStudents)): ?>
                        <p class="text-muted">Belum ada siswa di kelas ini.</p>
                    <?php else: ?>
                        <div class="student-list">
                            <?php foreach ($enrolledStudents as $student): ?>
                                <div class="student-item">
                                    <div>
                                        <strong><?= htmlspecialchars($student['name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($student['nis'] ?? '-') ?></small>
                                    </div>
                                    <a href="?class=<?= $classFilter ?>&remove=<?= $student['enrollment_id'] ?>"
                                       class="btn btn-sm btn-outline btn-delete"
                                       onclick="return confirm('Keluarkan siswa ini dari kelas?')">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-users empty-state-icon"></i>
                <p class="empty-state-title">Pilih kelas untuk melihat dan mengelola siswa</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.btn-delete {
    color: var(--danger-color);
    border-color: var(--danger-color);
}

.btn-delete:hover {
    background: var(--danger-color);
    color: white;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
