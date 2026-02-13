<?php
require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(array('Administrator', 'Wali Kelas', 'Guru'));

$pageTitle = 'Nilai Formatif (Ulangan Harian)';
$pdo = getDBConnection();
$academicYear = getActiveAcademicYear();

if (!$academicYear) {
    redirect(BASE_URL . 'modules/dashboard/index.php', 'Silakan atur tahun pelajaran aktif terlebih dahulu', 'warning');
}

// Get filters
$classFilter = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$subjectFilter = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

// Get classes based on role
$classes = array();
if (hasRole(array('Administrator', 'Wali Kelas'))) {
    $stmt = $pdo->query("
        SELECT c.*, ht.id as homeroom_id
        FROM classes c
        JOIN homeroom_teachers ht ON c.id = ht.class_id
        WHERE ht.academic_year_id = " . $academicYear['id'] . "
        ORDER BY c.level, c.name
    ");
    $classes = $stmt->fetchAll();
}

// Get subjects
$subjects = array();
$stmt = $pdo->query("SELECT * FROM subjects ORDER BY level, groups, name");
$subjects = $stmt->fetchAll();

// Get students and their formative scores
$students = array();
if ($classFilter && $subjectFilter) {
    $stmt = $pdo->prepare("
        SELECT s.*, se.id as enrollment_id
        FROM students s
        JOIN student_enrollments se ON s.id = se.student_id
        WHERE se.homeroom_teacher_id = ?
        ORDER BY s.name
    ");
    $stmt->execute(array($classFilter));
    $students = $stmt->fetchAll();

    // Get existing scores (optimized - single query instead of N+1)
    $enrollmentIds = array_column($students, 'enrollment_id');
    if (!empty($enrollmentIds)) {
        $placeholders = str_repeat('?,', count($enrollmentIds) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT fs.*, c.code, fs.enrollment_id
            FROM formative_scores fs
            JOIN competencies c ON fs.competency_id = c.id
            WHERE fs.enrollment_id IN ($placeholders)
        ");
        $stmt->execute($enrollmentIds);
        $allScores = $stmt->fetchAll();

        // Group scores by enrollment_id
        $scoresByEnrollment = array();
        foreach ($allScores as $score) {
            $scoresByEnrollment[$score['enrollment_id']][] = $score;
        }

        // Attach scores to students
        foreach ($students as &$student) {
            $student['scores'] = $scoresByEnrollment[$student['enrollment_id']] ?? array();
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scores'])) {
    $pdo->beginTransaction();

    try {
        foreach ($_POST['scores'] as $enrollmentId => $competencyScores) {
            foreach ($competencyScores as $competencyId => $scoreValue) {
                $scoreValue = (float)str_replace(',', '.', $scoreValue);

                if ($scoreValue > 100) {
                    $scoreValue = 100;
                } elseif ($scoreValue < 0) {
                    $scoreValue = 0;
                }

                // Check if exists
                $stmt = $pdo->prepare("
                    SELECT id FROM formative_scores
                    WHERE enrollment_id = ? AND competency_id = ?
                ");
                $stmt->execute(array($enrollmentId, $competencyId));
                $existing = $stmt->fetch();

                if (!empty($scoreValue)) {
                    if ($existing) {
                        // Delete if score is empty
                        $stmt = $pdo->prepare("DELETE FROM formative_scores WHERE id = ?");
                        $stmt->execute(array($existing['id']));
                    } else {
                        // Insert
                        $stmt = $pdo->prepare("
                            INSERT INTO formative_scores (enrollment_id, competency_id, score, notes)
                            VALUES (?, ?, ?, '')
                        ");
                        $stmt->execute(array($enrollmentId, $competencyId, $scoreValue));
                    }
                } else {
                    if ($existing) {
                        // Update
                        $stmt = $pdo->prepare("
                            UPDATE formative_scores SET score = ?
                            WHERE id = ?
                        ");
                        $stmt->execute(array($scoreValue, $existing['id']));
                    } else {
                        // Insert
                        $stmt = $pdo->prepare("
                            INSERT INTO formative_scores (enrollment_id, competency_id, score)
                            VALUES (?, ?, '')
                        ");
                        $stmt->execute(array($enrollmentId, $competencyId, $scoreValue));
                    }
                }
            }
        }

        $pdo->commit();
        redirect('formative.php?class=' . $classFilter . '&subject=' . $subjectFilter, 'Nilai berhasil disimpan', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.filter-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.filter-group {
    flex: 1;
}

.score-table {
    width: 100%;
    border-collapse: collapse;
}

.score-table th,
.score-table td {
    border: 1px solid var(--border-color);
    padding: 10px;
    text-align: center;
}

.score-table th {
    background: var(--light-gray);
    font-weight: 600;
}

.score-input {
    width: 70px;
    padding: 6px 8px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    text-align: center;
}

.score-valid {
    background: #c6f6d5 !important;
    border-color: #48bb78 !important;
}

.score-invalid {
    background: #fed7d7 !important;
    border-color: #f56565 !important;
}
</style>

<div class="page-header">
    <h1>Nilai Formatif (Ulangan Harian)</h1>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <form method="GET" action="" class="filter-bar">
            <div class="filter-group">
                <select name="class" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['homeroom_id']; ?>"
                                <?php echo $classFilter == $class['homeroom_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <select name="subject" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Pilih Mata Pelajaran --</option>
                    <?php
                    // Get subjects for the selected class level
                    if ($classFilter) {
                        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE level = (SELECT level FROM classes c JOIN homeroom_teachers ht ON c.id = ht.class_id WHERE ht.id = ?) ORDER BY groups, name");
                        $stmt->execute(array($classFilter));
                        $classSubjects = $stmt->fetchAll();
                    } else {
                        $classSubjects = $subjects;
                    }

                    foreach ($classSubjects as $subject):
                    ?>
                        <option value="<?php echo $subject['id']; ?>"
                                <?php echo $subjectFilter == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['name']) ?> (<?php echo $subject['code']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if (!empty($students)): ?>
        <div class="card-body">
            <form method="POST" action="">
                <div class="table-responsive">
                    <table class="table score-table">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <?php
                                // Get competencies for the selected subject
                                if ($subjectFilter) {
                                    $stmt = $pdo->prepare("SELECT * FROM competencies WHERE subject_id = ? ORDER BY code");
                                    $stmt->execute(array($subjectFilter));
                                    $competencies = $stmt->fetchAll();

                                    foreach ($competencies as $comp):
                                        ?>
                                            <th><?php echo htmlspecialchars($comp['description']); ?></th>
                                        <?php endforeach;
                                } else {
                                            ?>
                                            <th>Pilih mata pelajaran dulu</th>
                                        <?php
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            foreach ($students as $student):
                                ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($student['nis']); ?></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <?php
                                    // Get scores for this student
                                    $scoresMap = array();
                                    foreach ($student['scores'] as $s) {
                                        $scoresMap[$s['competency_id']] = $s;
                                    }

                                    if ($subjectFilter) {
                                        foreach ($competencies as $comp):
                                            ?>
                                                    <td>
                                                        <input type="number"
                                                               name="scores[<?php echo $student['enrollment_id']; ?>][<?php echo $comp['id']; ?>]"
                                                               class="score-input"
                                                               min="0" max="100" step="0.01"
                                                               value="<?php echo isset($scoresMap[$comp['id']]['score']) ? $scoresMap[$comp['id']]['score'] : ''; ?>"
                                                               oninput="updateScore(this)">
                                                    </td>
                                                    <?php endforeach;
                                    }
                                    ?>
                                </tr>
                                <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Nilai
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-edit empty-state-icon"></i>
                <p class="empty-state-title">Pilih kelas dan mata pelajaran</p>
                <p class="empty-state-text">Pilih kelas dan mata pelajaran untuk memasukkan nilai.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function updateScore(input) {
    const value = parseFloat(input.value);

    if (isNaN(value) || input.value === '') {
        input.classList.remove('score-valid', 'score-invalid');
        return;
    }

    if (value >= 0 && value <= 100) {
        input.classList.remove('score-invalid');
        input.classList.add('score-valid');
    } else {
        input.classList.remove('score-valid');
        input.classList.add('score-invalid');
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
