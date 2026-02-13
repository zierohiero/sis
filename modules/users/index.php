<?php
/**
 * Users Management
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pageTitle = 'Pengguna';
$pdo = getDBConnection();

// Get all users
$stmt = $pdo->query("
    SELECT u.*, t.name as teacher_name
    FROM users u
    LEFT JOIN teachers t ON u.id = t.user_id
    ORDER BY u.role, u.username
");
$users = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1>Data Pengguna</h1>
        <div class="page-actions">
            <a href="form.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Pengguna
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>Username</th>
                        <th>Nama</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th width="120">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                <div class="empty-state">
                                    <i class="fas fa-users-cog empty-state-icon"></i>
                                    <p class="empty-state-title">Tidak ada data pengguna</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($user['teacher_name'] ?? '-') ?></td>
                                <td>
                                    <span class="badge badge-primary"><?= htmlspecialchars($user['role']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $user['status'] ? 'success' : 'danger' ?>">
                                        <?= $user['status'] ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="form.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['username'] !== 'admin'): ?>
                                            <button onclick="return confirm('Hapus pengguna <?= htmlspecialchars($user['username']) ?>?')"
                                                    data-href="delete.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline btn-delete" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
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
        if (confirm('')) {
            window.location.href = this.getAttribute('data-href');
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
