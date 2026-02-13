<?php
/**
 * Homeroom Teachers Assignment
 * Penugasan Wali Kelas
 */

require_once __DIR__ . '/../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pageTitle = 'Penugasan Wali Kelas';
$pdo = getDBConnection();
$academicYear = getActiveAcademicYear();

if (!$academicYear) {
    redirect(BASE_URL . 'modules/dashboard/index.php', 'Silakan atur tahun pelajaran aktif terlebih dahulu', 'warning');
}

// Get data
$stmt = $pdo->query("
    SELECT c.*, ht.id as homeroom_id, t.name as teacher_name, t.id as teacher_id
    FROM classes c
    LEFT JOIN homeroom_teachers ht ON c.id = ht.class_id AND ht.academic_year_id = {$academicYear['id']}
    LEFT JOIN teachers t ON ht.teacher_id = t.id
    ORDER BY c.level, c.name
");
$classes = $stmt->fetchAll();

// Get teachers for dropdown
$stmt = $pdo->query("SELECT * FROM teachers ORDER BY name");
$teachers = $stmt->fetchAll();

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classId = (int)($_POST['class_id'] ?? 0);
    $teacherId = (int)($_POST['teacher_id'] ?? 0);

    if ($classId && $teacherId) {
        // Check if already exists
        $stmt = $pdo->prepare("
            SELECT id FROM homeroom_teachers
            WHERE class_id = ? AND academic_year_id = ?
        ");
        $stmt->execute([$classId, $academicYear['id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE homeroom_teachers SET teacher_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$teacherId, $existing['id']]);
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO homeroom_teachers (academic_year_id, teacher_id, class_id)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$academicYear['id'], $teacherId, $classId]);
        }

        redirect('homeroom_teachers.php', 'Penugasan wali kelas berhasil disimpan', 'success');
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.assignment-table {
    width: 100%;
    border-collapse: collapse;
}

.assignment-table th,
.assignment-table td {
    border: 1px solid var(--border-color);
    padding: 12px;
    text-align: left;
}

.assignment-table th {
    background: var(--light-gray);
    font-weight: 600;
}

.teacher-select {
    width: 100%;
    padding: 8px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
}

.class-level-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.class-level-badge.X {
    background: #bee3f8;
    color: #2a4365;
}

.class-level-badge.XI {
    background: #feebc8;
    color: #7c2d12;
}

.class-level-badge.XII {
    background: #c6f6d5;
    color: #22543d;
}
</style>

<div class="page-header">
    <h1>Penugasan Wali Kelas</h1>
    <p class="text-muted">Tahun Pelajaran: <?= htmlspecialchars($academicYear['period']) ?> - <?= htmlspecialchars($academicYear['semester']) ?></p>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="table-responsive">
                <table class="table assignment-table">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th>Kelas</th>
                            <th>Tingkat</th>
                            <th>Wali Kelas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($classes)): ?>
                            <tr>
                                <td colspan="4" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-door-open empty-state-icon"></i>
                                        <p class="empty-state-title">Belum ada data kelas</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= htmlspecialchars($class['name']) ?></strong></td>
                                    <td>
                                        <span class="class-level-badge <?= htmlspecialchars($class['level']) ?>">
                                            <?= htmlspecialchars($class['level']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="hidden" name="class_id" value="<?= $class['id'] ?>">
                                        <select name="teacher_id" class="teacher-select">
                                            <option value="">-- Pilih Wali Kelas --</option>
                                            <?php foreach ($teachers as $teacher): ?>
                                                <option value="<?= $teacher['id'] ?>"
                                                        <?= ($class['teacher_id'] ?? 0) == $teacher['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($teacher['name']) ?>
                                                    <?= !empty($teacher['nip']) ? '(' . htmlspecialchars($teacher['nip']) . ')' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Penugasan
                </button>
                <a href="<?= BASE_URL ?>modules/dashboard/index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
