<?php
/**
 * Subject Allocations
 * Penugasan Guru Mata Pelajaran
 */

require_once __DIR__ . '/../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pageTitle = 'Pengampu Mata Pelajaran';
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

// Get subjects and allocations
$allocations = [];
if ($classFilter) {
    $homeroomId = null;
    foreach ($classes as $c) {
        if ($c['id'] == $classFilter) {
            $homeroomId = $c['homeroom_id'];
            break;
        }
    }

    if ($homeroomId) {
        $stmt = $pdo->query("
            SELECT sub.*, sa.id as allocation_id, sa.teacher_id, t.name as teacher_name
            FROM subjects sub
            LEFT JOIN subject_allocations sa ON sub.id = sa.subject_id AND sa.homeroom_teacher_id = $homeroomId
            LEFT JOIN teachers t ON sa.teacher_id = t.id
            WHERE sub.level = (SELECT level FROM classes WHERE id = $classFilter)
            ORDER BY sub.groups, sub.name
        ");
        $allocations = $stmt->fetchAll();
    }
}

// Get teachers
$teachers = [];
$stmt = $pdo->query("SELECT * FROM teachers ORDER BY name");
$teachers = $stmt->fetchAll();

// Handle allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocations'])) {
    foreach ($_POST['allocations'] as $subjectId => $teacherId) {
        $teacherId = (int)$teacherId;

        // Get homeroom ID
        $homeroomId = null;
        foreach ($classes as $c) {
            if ($c['id'] == $classFilter) {
                $homeroomId = $c['homeroom_id'];
                break;
            }
        }

        if ($homeroomId) {
            // Check if exists
            $stmt = $pdo->prepare("
                SELECT id FROM subject_allocations
                WHERE homeroom_teacher_id = ? AND subject_id = ?
            ");
            $stmt->execute([$homeroomId, $subjectId]);
            $existing = $stmt->fetch();

            if ($teacherId > 0) {
                if ($existing) {
                    // Update
                    $stmt = $pdo->prepare("
                        UPDATE subject_allocations SET teacher_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$teacherId, $existing['id']]);
                } else {
                    // Insert
                    $stmt = $pdo->prepare("
                        INSERT INTO subject_allocations (homeroom_teacher_id, subject_id, teacher_id)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$homeroomId, $subjectId, $teacherId]);
                }
            } else if ($existing) {
                // Delete if teacher not selected
                $stmt = $pdo->prepare("DELETE FROM subject_allocations WHERE id = ?");
                $stmt->execute([$existing['id']]);
            }
        }
    }

    redirect('subject_allocations.php?class=' . $classFilter, 'Penugasan guru mata pelajaran berhasil disimpan', 'success');
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.filter-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.allocation-table {
    width: 100%;
    border-collapse: collapse;
}

.allocation-table th,
.allocation-table td {
    border: 1px solid var(--border-color);
    padding: 12px;
    text-align: left;
}

.allocation-table th {
    background: var(--light-gray);
    font-weight: 600;
}

.teacher-select {
    width: 100%;
    padding: 8px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
}

.group-header {
    background: var(--primary-color);
    color: white;
    padding: 8px 12px;
    font-weight: 600;
}
</style>

<div class="page-header">
    <h1>Pengampu Mata Pelajaran</h1>
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
                    <table class="table allocation-table">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>Kode</th>
                                <th>Mata Pelajaran</th>
                                <th>Kelompok</th>
                                <th>Guru Pengampu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allocations)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        Tidak ada mata pelajaran untuk tingkat ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; ?>
                                <?php foreach ($allocations as $alloc): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td>
                                            <span class="badge badge-primary">
                                                <?= htmlspecialchars($alloc['code'] ?? '-') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($alloc['name']) ?></strong>
                                            <?php if ($alloc['is_diniah']): ?>
                                                <span class="badge badge-success" style="margin-left: 8px;">Diniah</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($alloc['groups']) ?></td>
                                        <td>
                                            <input type="hidden" name="allocations[<?= $alloc['id'] ?>]" value="0">
                                            <select name="allocations[<?= $alloc['id'] ?>]" class="teacher-select">
                                                <option value="0">-- Pilih Guru --</option>
                                                <?php foreach ($teachers as $teacher): ?>
                                                    <option value="<?= $teacher['id'] ?>"
                                                            <?= ($alloc['teacher_id'] ?? 0) == $teacher['id'] ? 'selected' : '' ?>>
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
    <?php else: ?>
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-chalkboard empty-state-icon"></i>
                <p class="empty-state-title">Pilih kelas untuk melihat dan mengelola pengampu mata pelajaran</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
