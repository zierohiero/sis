<?php
/**
 * Extracurricular Activities & Grades
 * Ekstrakurikuler
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator', 'Wali Kelas']);

$pageTitle = 'Ekstrakurikuler';
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

// Get activities
$activities = [];
$stmt = $pdo->query("SELECT * FROM extracurricular_activities ORDER BY name");
$activities = $stmt->fetchAll();

// Get students and their extracurricular grades
$students = [];
if ($classFilter) {
    $homeroomId = null;
    foreach ($classes as $c) {
        if ($c['id'] == $classFilter) {
            $homeroomId = $c['homeroom_id'];
            break;
        }
    }

    if ($homeroomId) {
        $stmt = $pdo->prepare("
            SELECT s.*, se.id as enrollment_id
            FROM students s
            JOIN student_enrollments se ON s.id = se.student_id
            WHERE se.homeroom_teacher_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$homeroomId]);
        $students = $stmt->fetchAll();

        // Get existing grades
        foreach ($students as &$student) {
            $stmt = $pdo->prepare("
                SELECT ep.*, ea.name as activity_name
                FROM extracurricular_predicates ep
                JOIN extracurricular_activities ea ON ep.activity_id = ea.id
                WHERE ep.enrollment_id = ?
            ");
            $stmt->execute([$student['enrollment_id']]);
            $student['activities'] = $stmt->fetchAll();
        }
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grades'])) {
    foreach ($_POST['grades'] as $enrollmentId => $activitiesData) {
        foreach ($activitiesData as $activityId => $data) {
            $predicate = sanitize($data['predicate'] ?? '');
            $notes = sanitize($data['notes'] ?? '');

            if (!empty($predicate)) {
                $stmt = $pdo->prepare("
                    INSERT INTO extracurricular_predicates (enrollment_id, activity_id, predicate, notes)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        predicate = VALUES(predicate),
                        notes = VALUES(notes)
                ");
                $stmt->execute([$enrollmentId, $activityId, $predicate, $notes]);
            }
        }
    }

    redirect('index.php?class=' . $classFilter, 'Nilai ekstrakurikuler berhasil disimpan', 'success');
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.filter-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.predicate-select {
    width: 100%;
    padding: 6px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
}

.notes-input {
    width: 100%;
    padding: 6px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
}
</style>

<div class="page-header">
    <h1>Nilai Ekstrakurikuler</h1>
</div>

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
            <form method="POST" action="">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>Nama Siswa</th>
                                <?php foreach ($activities as $activity): ?>
                                    <th width="150"><?= htmlspecialchars($activity['name']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="<?= count($activities) + 2 ?>" class="text-center">
                                        <div class="empty-state">
                                            <p class="empty-state-title">Belum ada siswa di kelas ini</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; ?>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($student['name']) ?></td>
                                        <?php foreach ($activities as $activity): ?>
                                            <?php
                                            $existingGrade = null;
                                            foreach ($student['activities'] as $act) {
                                                if ($act['activity_id'] == $activity['id']) {
                                                    $existingGrade = $act;
                                                    break;
                                                }
                                            }
                                            ?>
                                            <td>
                                                <div style="margin-bottom: 5px;">
                                                    <select name="grades[<?= $student['enrollment_id'] ?>][<?= $activity['id'] ?>][predicate]"
                                                            class="predicate-select">
                                                        <option value="">-</option>
                                                        <option value="A" <?= ($existingGrade && $existingGrade['predicate'] === 'A') ? 'selected' : '' ?>>A</option>
                                                        <option value="B" <?= ($existingGrade && $existingGrade['predicate'] === 'B') ? 'selected' : '' ?>>B</option>
                                                        <option value="C" <?= ($existingGrade && $existingGrade['predicate'] === 'C') ? 'selected' : '' ?>>C</option>
                                                        <option value="D" <?= ($existingGrade && $existingGrade['predicate'] === 'D') ? 'selected' : '' ?>>D</option>
                                                        <option value="E" <?= ($existingGrade && $existingGrade['predicate'] === 'E') ? 'selected' : '' ?>>E</option>
                                                    </select>
                                                </div>
                                                <input type="text" name="grades[<?= $student['enrollment_id'] ?>][<?= $activity['id'] ?>][notes]"
                                                       class="notes-input" placeholder="Catatan"
                                                       value="<?= htmlspecialchars($existingGrade['notes'] ?? '') ?>">
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Nilai
                    </button>
                    <a href="<?= BASE_URL ?>modules/dashboard/index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-futbol empty-state-icon"></i>
                <p class="empty-state-title">Pilih kelas untuk melihat dan mengelola nilai ekstrakurikuler</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
