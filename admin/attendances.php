<?php
/**
 * Students Attendances Management
 * Rekapitulasi Presensi Siswa
 */

require_once __DIR__ . '/../includes/init.php';

requireLogin();
requireRole(['Administrator', 'Wali Kelas']);

$pageTitle = 'Presensi Siswa';
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

// Get students and attendance
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
            SELECT
                s.*,
                se.id as enrollment_id,
                sa.present,
                sa.sick,
                sa.permit,
                sa.alpha,
                sa.notes
            FROM students s
            JOIN student_enrollments se ON s.id = se.student_id
            LEFT JOIN students_attendances sa ON se.id = sa.enrollment_id
            WHERE se.homeroom_teacher_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$homeroomId]);
        $students = $stmt->fetchAll();
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendances'])) {
    foreach ($_POST['attendances'] as $enrollmentId => $data) {
        $present = (int)($data['present'] ?? 0);
        $sick = (int)($data['sick'] ?? 0);
        $permit = (int)($data['permit'] ?? 0);
        $alpha = (int)($data['alpha'] ?? 0);
        $notes = sanitize($data['notes'] ?? '');

        $stmt = $pdo->prepare("
            INSERT INTO students_attendances (enrollment_id, present, sick, permit, alpha, notes)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                present = VALUES(present),
                sick = VALUES(sick),
                permit = VALUES(permit),
                alpha = VALUES(alpha),
                notes = VALUES(notes)
        ");
        $stmt->execute([$enrollmentId, $present, $sick, $permit, $alpha, $notes]);
    }

    redirect('attendances.php?class=' . $classFilter, 'Data presensi berhasil disimpan', 'success');
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.filter-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.attendance-table {
    width: 100%;
    border-collapse: collapse;
}

.attendance-table th,
.attendance-table td {
    border: 1px solid var(--border-color);
    padding: 10px;
    text-align: center;
}

.attendance-table th {
    background: var(--light-gray);
    font-weight: 600;
}

.attendance-table td.text-left {
    text-align: left;
}

.attendance-input {
    width: 60px;
    padding: 6px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    text-align: center;
}

.notes-input {
    width: 100%;
    min-width: 150px;
    padding: 6px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
}

.total-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.total-badge.present { background: #c6f6d5; color: #22543d; }
.total-badge.sick { background: #bee3f8; color: #2a4365; }
.total-badge.permit { background: #feebc8; color: #7c2d12; }
.total-badge.alpha { background: #fed7d7; color: #742a2a; }
</style>

<div class="page-header">
    <h1>Presensi Siswa</h1>
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
                    <table class="table attendance-table">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th width="80">Hadir</th>
                                <th width="80">Sakit</th>
                                <th width="80">Izin</th>
                                <th width="80">Alpha</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">
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
                                        <td><?= htmlspecialchars($student['nis']) ?></td>
                                        <td class="text-left"><?= htmlspecialchars($student['name']) ?></td>
                                        <td>
                                            <input type="number" name="attendances[<?= $student['enrollment_id'] ?>][present]"
                                                   class="attendance-input" min="0" value="<?= htmlspecialchars($student['present'] ?? 0) ?>">
                                        </td>
                                        <td>
                                            <input type="number" name="attendances[<?= $student['enrollment_id'] ?>][sick]"
                                                   class="attendance-input" min="0" value="<?= htmlspecialchars($student['sick'] ?? 0) ?>">
                                        </td>
                                        <td>
                                            <input type="number" name="attendances[<?= $student['enrollment_id'] ?>][permit]"
                                                   class="attendance-input" min="0" value="<?= htmlspecialchars($student['permit'] ?? 0) ?>">
                                        </td>
                                        <td>
                                            <input type="number" name="attendances[<?= $student['enrollment_id'] ?>][alpha]"
                                                   class="attendance-input" min="0" value="<?= htmlspecialchars($student['alpha'] ?? 0) ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="attendances[<?= $student['enrollment_id'] ?>][notes]"
                                                   class="notes-input" value="<?= htmlspecialchars($student['notes'] ?? '') ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Presensi
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
                <i class="fas fa-clipboard-list empty-state-icon"></i>
                <p class="empty-state-title">Pilih kelas untuk melihat dan mengelola presensi</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
