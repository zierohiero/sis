<?php
/**
 * Teachers Management
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pageTitle = 'Data Guru';
$pdo = getDBConnection();

// Pagination
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Search
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = "(t.name LIKE ? OR t.nip LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

$whereClause = implode(' AND ', $where);

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM teachers t WHERE $whereClause");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$pagination = paginate($total, $perPage, $page);

// Get teachers
$stmt = $pdo->prepare("
    SELECT t.*, u.username, u.role, u.status as user_status
    FROM teachers t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE $whereClause
    ORDER BY t.name ASC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$teachers = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1>Data Guru</h1>
        <div class="page-actions">
            <a href="form.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Guru
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="" class="search-form">
            <input type="text" name="search" placeholder="Cari nama atau NIP..."
                   value="<?= htmlspecialchars($search) ?>" class="form-control">
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-search"></i> Cari
            </button>
            <?php if (!empty($search)): ?>
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Reset
                </a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>NIP</th>
                        <th>Nama Guru</th>
                        <th>L/P</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teachers)): ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="empty-state">
                                    <i class="fas fa-chalkboard-teacher empty-state-icon"></i>
                                    <p class="empty-state-title">Tidak ada data guru</p>
                                    <p class="empty-state-text">
                                        <?php if (!empty($search)): ?>
                                            Data tidak ditemukan. Coba kata kunci lain.
                                        <?php else: ?>
                                            Belum ada data guru. Klik tombol Tambah Guru untuk menambah data.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = $pagination['offset'] + 1; ?>
                        <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($teacher['nip'] ?? '-') ?></td>
                                <td>
                                    <div class="user-cell">
                                        <?php if (!empty($teacher['photo'])): ?>
                                            <img src="<?= BASE_URL . htmlspecialchars($teacher['photo']) ?>" class="avatar" alt="Foto">
                                        <?php else: ?>
                                            <div class="avatar-placeholder">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= htmlspecialchars($teacher['name']) ?></strong>
                                            <?php if (!empty($teacher['birth_place']) || !empty($teacher['birth_date'])): ?>
                                                <br><small class="text-muted">
                                                    <?= htmlspecialchars($teacher['birth_place'] ?? '') ?>,
                                                    <?= formatDate($teacher['birth_date']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($teacher['gender']) ?></td>
                                <td><?= htmlspecialchars($teacher['username'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($teacher['role'])): ?>
                                        <span class="badge badge-primary"><?= htmlspecialchars($teacher['role']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view.php?id=<?= $teacher['id'] ?>" class="btn btn-sm btn-outline" title="Lihat">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="form.php?id=<?= $teacher['id'] ?>" class="btn btn-sm btn-outline" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="return confirm('Hapus data guru <?= htmlspecialchars($teacher['name'], ENT_QUOTES) ?>?')"
                                                data-href="delete.php?id=<?= $teacher['id'] ?>" class="btn btn-sm btn-outline btn-delete" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
            <?= buildPagination($pagination, '?page=%d' . (!empty($search) ? '&search=' . urlencode($search) : '')) ?>
        <?php endif; ?>
    </div>
</div>

<style>
.search-form {
    display: flex;
    gap: 10px;
    max-width: 400px;
}

.search-form .form-control {
    flex: 1;
}

.action-buttons {
    display: flex;
    gap: 5px;
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
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function() {
        const href = this.getAttribute('data-href');
        const message = this.getAttribute('onclick');
        if (confirm(message.replace(/^return confirm\('(.*)'\)$/, '$1'))) {
            window.location.href = href;
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
