<?php
/**
 * Students Management
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator', 'Wali Kelas']);

$pageTitle = 'Data Siswa';
$pdo = getDBConnection();
$academicYear = getActiveAcademicYear();

// Pagination
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Search and filter
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build query
$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = "(s.name LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

if (!empty($statusFilter)) {
    $where[] = "s.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM students s WHERE $whereClause");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$pagination = paginate($total, $perPage, $page);

// Get students
$stmt = $pdo->prepare("
    SELECT s.*,
           c.name as class_name,
           se.id as enrollment_id
    FROM students s
    LEFT JOIN student_enrollments se ON s.id = se.student_id
    LEFT JOIN homeroom_teachers ht ON se.homeroom_teacher_id = ht.id
    LEFT JOIN classes c ON ht.class_id = c.id
    LEFT JOIN academic_years ay ON ht.academic_year_id = ay.id
    WHERE $whereClause
    ORDER BY s.name ASC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$students = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1>Data Siswa</h1>
        <div class="page-actions">
            <?php if (hasRole(['Administrator'])): ?>
                <a href="form.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Siswa
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-filters">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Cari nama, NIS, NISN..."
                           value="<?= htmlspecialchars($search) ?>" class="form-control">
                </div>
                <div class="filter-group">
                    <select name="status" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="Aktif" <?= $statusFilter === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="Tidak Aktif" <?= $statusFilter === 'Tidak Aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                        <option value="Lulus" <?= $statusFilter === 'Lulus' ? 'selected' : '' ?>>Lulus</option>
                        <option value="Keluar" <?= $statusFilter === 'Keluar' ? 'selected' : '' ?>>Keluar</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($statusFilter)): ?>
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table" id="studentsTable">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>NIS/NISN</th>
                        <th>Nama Siswa</th>
                        <th>L/P</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <?php if (hasRole(['Administrator'])): ?>
                            <th width="150">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="<?php echo hasRole(['Administrator']) ? '7' : '6' ?>" class="text-center">
                                <div class="empty-state">
                                    <i class="fas fa-user-graduate empty-state-icon"></i>
                                    <p class="empty-state-title">Tidak ada data siswa</p>
                                    <p class="empty-state-text">
                                        <?php if (!empty($search)): ?>
                                            Data tidak ditemukan. Coba kata kunci lain.
                                        <?php else: ?>
                                            Belum ada data siswa. Klik tombol Tambah Siswa untuk menambah data.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = $pagination['offset'] + 1; ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($student['nis'] ?? '-') ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($student['nisn'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <div class="user-cell">
                                        <?php if (!empty($student['photo'])): ?>
                                            <img src="<?= BASE_URL . htmlspecialchars($student['photo']) ?>"
                                                 class="avatar" alt="Foto">
                                        <?php else: ?>
                                            <div class="avatar-placeholder">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= htmlspecialchars($student['name']) ?></strong><br>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($student['birth_place'] ?? '-') ?>,
                                                <?= formatDate($student['birth_date']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($student['gender']) ?></td>
                                <td>
                                    <?php if (!empty($student['class_name'])): ?>
                                        <span class="badge badge-primary"><?= htmlspecialchars($student['class_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClasses = [
                                        'Aktif' => 'success',
                                        'Tidak Aktif' => 'warning',
                                        'Lulus' => 'primary',
                                        'Keluar' => 'danger'
                                    ];
                                    $statusClass = $statusClasses[$student['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge badge-<?= $statusClass ?>">
                                        <?= htmlspecialchars($student['status']) ?>
                                    </span>
                                </td>
                                <?php if (hasRole(['Administrator'])): ?>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view.php?id=<?= $student['id'] ?>"
                                               class="btn btn-sm btn-outline" title="Lihat">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="form.php?id=<?= $student['id'] ?>"
                                               class="btn btn-sm btn-outline" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button onclick="return confirmDelete(<?= htmlspecialchars(json_encode($student['name'])) ?>)"
                                                    data-href="delete.php?id=<?= $student['id'] ?>"
                                                    class="btn btn-sm btn-outline btn-delete" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
            <?= buildPagination($pagination, '?page=%d' . (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '')) ?>
        <?php endif; ?>
    </div>
</div>

<style>
.page-header {
    margin-bottom: 24px;
}

.page-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header-content h1 {
    font-size: 24px;
    font-weight: 700;
    color: var(--dark-color);
}

.page-actions {
    display: flex;
    gap: 10px;
}

.card-filters {
    width: 100%;
}

.filter-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.avatar-placeholder {
    width: 32px;
    height: 32px;
    background: var(--light-gray);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-color);
}

.btn-delete {
    color: var(--danger-color);
    border-color: var(--danger-color);
}

.btn-delete:hover {
    background: var(--danger-color);
    color: white;
}
</style>

<script>
function confirmDelete(name) {
    return confirm(`Apakah Anda yakin ingin menghapus data siswa "${name}"? Tindakan ini tidak dapat dibatalkan.`);
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirmDelete(this.closest('tr').querySelector('strong').textContent)) {
                window.location.href = this.getAttribute('data-href');
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
