<?php
/**
 * Summative Scores (Nilai STS/SAS)
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator', 'Wali Kelas', 'Guru', 'Ustaz']);

$pageTitle = 'Nilai Sumatif (STS/SAS)';
$pdo = getDBConnection();
$academicYear = getActiveAcademicYear();

if (!$academicYear) {
    redirect(BASE_URL . 'modules/dashboard/index.php', 'Silakan atur tahun pelajaran aktif terlebih dahulu', 'warning');
}

// Get filters
$classFilter = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$subjectFilter = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$typeFilter = isset($_GET['type']) ? sanitize($_GET['type']) : 'SAS';

// Get classes based on role
$classes = [];
if (hasRole(['Administrator', 'Wali Kelas', 'Ustaz'])) {
    $stmt = $pdo->query("
        SELECT DISTINCT c.*, ht.id as homeroom_id
        FROM classes c
        JOIN homeroom_teachers ht ON c.id = ht.class_id
        WHERE ht.academic_year_id = {$academicYear['id']}
        ORDER BY c.level, c.name
    ");
    $classes = $stmt->fetchAll();
}

// Get subjects
$subjects = [];
$stmt = $pdo->query("SELECT * FROM subjects ORDER BY level, groups, name");
$subjects = $stmt->fetchAll();

// Get subject allocations for teacher
$allocations = [];
if (hasRole(['Guru', 'Ustaz'])) {
    $stmt = $pdo->prepare("
        SELECT sa.*, c.name as class_name, sub.name as subject_name
        FROM subject_allocations sa
        JOIN homeroom_teachers ht ON sa.homeroom_teacher_id = ht.id
        JOIN classes c ON ht.class_id = c.id
        JOIN subjects sub ON sa.subject_id = sub.id
        JOIN teachers t ON sa.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE u.id = ? AND ht.academic_year_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $academicYear['id']]);
    $allocations = $stmt->fetchAll();
}

// Get students and scores
$students = [];
$scores = [];

if ($classFilter && $subjectFilter) {
    // Get homeroom teacher ID
    $stmt = $pdo->prepare("
        SELECT ht.id FROM homeroom_teachers ht
        WHERE ht.class_id = ? AND ht.academic_year_id = ?
    ");
    $stmt->execute([$classFilter, $academicYear['id']]);
    $homeroom = $stmt->fetch();

    if ($homeroom) {
        // Get allocation ID
        $stmt = $pdo->prepare("
            SELECT sa.id FROM subject_allocations sa
            WHERE sa.homeroom_teacher_id = ? AND sa.subject_id = ?
        ");
        $stmt->execute([$homeroom['id'], $subjectFilter]);
        $allocation = $stmt->fetch();

        if ($allocation) {
            // Get students
            $stmt = $pdo->prepare("
                SELECT s.*, se.id as enrollment_id
                FROM students s
                JOIN student_enrollments se ON s.id = se.student_id
                WHERE se.homeroom_teacher_id = ?
                ORDER BY s.name
            ");
            $stmt->execute([$homeroom['id']]);
            $students = $stmt->fetchAll();

            // Get existing scores
            $stmt = $pdo->prepare("
                SELECT * FROM summative_scores
                WHERE allocation_id = ? AND type = ?
            ");
            $stmt->execute([$allocation['id'], $typeFilter]);
            $scoresData = $stmt->fetchAll();

            foreach ($scoresData as $score) {
                $scores[$score['enrollment_id']] = $score;
            }
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scores'])) {
    $pdo->beginTransaction();

    try {
        foreach ($_POST['scores'] as $enrollmentId => $scoreValue) {
            $scoreValue = (float)str_replace(',', '.', $scoreValue);

            if ($scoreValue > 100) {
                $scoreValue = 100;
            } elseif ($scoreValue < 0) {
                $scoreValue = 0;
            }

            if (isset($scores[$enrollmentId])) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE summative_scores
                    SET score = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $scoreValue,
                    sanitize($_POST['notes'][$enrollmentId] ?? ''),
                    $scores[$enrollmentId]['id']
                ]);
            } else {
                // Insert
                if (!empty($scoreValue)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO summative_scores (enrollment_id, allocation_id, type, score, notes)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $enrollmentId,
                        $_POST['allocation_id'],
                        $typeFilter,
                        $scoreValue,
                        sanitize($_POST['notes'][$enrollmentId] ?? '')
                    ]);
                }
            }
        }

        $pdo->commit();
        redirect('summative.php?class=' . $classFilter . '&subject=' . $subjectFilter . '&type=' . $typeFilter,
                'Nilai berhasil disimpan', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.grade-input-table {
    width: 100%;
    border-collapse: collapse;
}

.grade-input-table th,
.grade-input-table td {
    border: 1px solid var(--border-color);
    padding: 10px;
    text-align: left;
}

.grade-input-table th {
    background: var(--light-gray);
    font-weight: 600;
}

.grade-input-table .score-input {
    width: 80px;
    padding: 6px 10px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    text-align: center;
}

.grade-input-table .notes-input {
    width: 100%;
    min-width: 200px;
    padding: 6px 10px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
}

.grade-input-table tbody tr:hover {
    background: var(--light-gray);
}

.score-valid {
    background: #c6f6d5 !important;
    border-color: #48bb78 !important;
}

.score-invalid {
    background: #fed7d7 !important;
    border-color: #f56565 !important;
}

.filter-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.type-toggle {
    display: flex;
    gap: 10px;
}

.type-toggle .btn {
    flex: 1;
}
</style>

<div class="page-header">
    <h1><?= htmlspecialchars($pageTitle) ?></h1>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="filter-bar">
            <form method="GET" action="" class="filter-form" style="display: flex; gap: 15px; flex: 1;">
                <div class="filter-group">
                    <select name="class" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>" <?= $classFilter == $class['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <select name="subject" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Pilih Mata Pelajaran --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['id'] ?>" <?= $subjectFilter == $subject['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subject['name']) ?> (<?= $subject['level'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <div class="type-toggle">
                <a href="?class=<?= $classFilter ?>&subject=<?= $subjectFilter ?>&type=STS"
                   class="btn <?= $typeFilter === 'STS' ? 'btn-primary' : 'btn-outline' ?>">
                    STS (Tengah Semester)
                </a>
                <a href="?class=<?= $classFilter ?>&subject=<?= $subjectFilter ?>&type=SAS"
                   class="btn <?= $typeFilter === 'SAS' ? 'btn-primary' : 'btn-outline' ?>">
                    SAS (Akhir Semester)
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($students)): ?>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="allocation_id" value="<?= $allocation['id'] ?? '' ?>">

                <div class="table-responsive">
                    <table class="table grade-input-table">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th width="120">Nilai (0-100)</th>
                                <th>Catatan</th>
                                <th width="80">Predikat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; ?>
                            <?php foreach ($students as $student): ?>
                                <?php
                                $existingScore = $scores[$student['enrollment_id']] ?? null;
                                $scoreValue = $existingScore ? $existingScore['score'] : '';
                                $notesValue = $existingScore ? $existingScore['notes'] : '';
                                $predicate = $existingScore && !empty($scoreValue) ? getGradePredicate((float)$scoreValue) : null;
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($student['nis']) ?></td>
                                    <td><?= htmlspecialchars($student['name']) ?></td>
                                    <td>
                                        <input type="number" name="scores[<?= $student['enrollment_id'] ?>]"
                                               class="score-input"
                                               value="<?= $scoreValue ?>"
                                               min="0" max="100" step="0.01"
                                               oninput="updatePredicate(this)">
                                    </td>
                                    <td>
                                        <input type="text" name="notes[<?= $student['enrollment_id'] ?>]"
                                               class="notes-input"
                                               value="<?= htmlspecialchars($notesValue) ?>"
                                               placeholder="Catatan (opsional)">
                                    </td>
                                    <td class="predicate-cell">
                                        <?php if ($predicate): ?>
                                            <span class="badge badge-<?= $predicate['predicate'] === 'A' || $predicate['predicate'] === 'B' ? 'success' : ($predicate['predicate'] === 'D' || $predicate['predicate'] === 'E' ? 'danger' : 'warning') ?>">
                                                <?= $predicate['predicate'] ?> - <?= $predicate['description'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Nilai
                    </button>
                    <a href="summative.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-clipboard-list empty-state-icon"></i>
                <p class="empty-state-title">Silakan pilih kelas dan mata pelajaran</p>
                <p class="empty-state-text">Pilih kelas dan mata pelajaran untuk memasukkan nilai siswa.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function updatePredicate(input) {
    const row = input.closest('tr');
    const cell = row.querySelector('.predicate-cell');
    const value = parseFloat(input.value);

    if (isNaN(value) || input.value === '') {
        cell.innerHTML = '';
        input.classList.remove('score-valid', 'score-invalid');
        return;
    }

    if (value >= 0 && value <= 100) {
        input.classList.remove('score-invalid');
        input.classList.add('score-valid');

        let predicate, description, badgeClass;
        if (value >= 90) {
            predicate = 'A'; description = 'Sangat Baik'; badgeClass = 'success';
        } else if (value >= 80) {
            predicate = 'B'; description = 'Baik'; badgeClass = 'success';
        } else if (value >= 70) {
            predicate = 'C'; description = 'Cukup'; badgeClass = 'warning';
        } else if (value >= 60) {
            predicate = 'D'; description = 'Kurang'; badgeClass = 'danger';
        } else {
            predicate = 'E'; description = 'Sangat Kurang'; badgeClass = 'danger';
        }

        cell.innerHTML = `<span class="badge badge-${badgeClass}">${predicate} - ${description}</span>`;
    } else {
        input.classList.remove('score-valid');
        input.classList.add('score-invalid');
        cell.innerHTML = '<span class="badge badge-danger">Nilai tidak valid</span>';
    }
}

// Initialize predicates on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.score-input').forEach(input => {
        updatePredicate(input);
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
