<?php
/**
 * Subjects Management
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pageTitle = 'Mata Pelajaran';
$pdo = getDBConnection();

// Get filter
$levelFilter = isset($_GET['level']) ? sanitize($_GET['level']) : '';
$groupFilter = isset($_GET['group']) ? sanitize($_GET['group']) : '';

// Build query
$where = ['1=1'];
$params = [];

if (!empty($levelFilter)) {
    $where[] = "level = ?";
    $params[] = $levelFilter;
}

if (!empty($groupFilter)) {
    $where[] = "groups = ?";
    $params[] = $groupFilter;
}

$whereClause = implode(' AND ', $where);

// Get subjects
$stmt = $pdo->prepare("
    SELECT * FROM subjects
    WHERE $whereClause
    ORDER BY FIELD(level, 'X', 'XI', 'XII'), FIELD(groups, 'Umum', 'Pilihan', 'Muatan Lokal', 'Diniah'), name
");
$stmt->execute($params);
$subjects = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1>Mata Pelajaran</h1>
        <div class="page-actions">
            <a href="form.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Mapel
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="" class="filter-form">
            <div class="filter-group">
                <select name="level" class="form-control">
                    <option value="">Semua Tingkat</option>
                    <option value="X" <?= $levelFilter === 'X' ? 'selected' : '' ?>>Kelas X</option>
                    <option value="XI" <?= $levelFilter === 'XI' ? 'selected' : '' ?>>Kelas XI</option>
                    <option value="XII" <?= $levelFilter === 'XII' ? 'selected' : '' ?>>Kelas XII</option>
                </select>
            </div>
            <div class="filter-group">
                <select name="group" class="form-control">
                    <option value="">Semua Kelompok</option>
                    <option value="Umum" <?= $groupFilter === 'Umum' ? 'selected' : '' ?>>Umum</option>
                    <option value="Pilihan" <?= $groupFilter === 'Pilihan' ? 'selected' : '' ?>>Pilihan</option>
                    <option value="Muatan Lokal" <?= $groupFilter === 'Muatan Lokal' ? 'selected' : '' ?>>Muatan Lokal</option>
                    <option value="Diniah" <?= $groupFilter === 'Diniah' ? 'selected' : '' ?>>Diniah</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-filter"></i> Filter
            </button>
            <?php if (!empty($levelFilter) || !empty($groupFilter)): ?>
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
                        <th>Kode</th>
                        <th>Nama Mata Pelajaran</th>
                        <th>Tingkat</th>
                        <th>Kelompok</th>
                        <th width="120">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                <div class="empty-state">
                                    <i class="fas fa-book empty-state-icon"></i>
                                    <p class="empty-state-title">Tidak ada data mata pelajaran</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; ?>
                        <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <span class="badge badge-primary"><?= htmlspecialchars($subject['code'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($subject['name']) ?></strong>
                                    <?php if ($subject['is_diniah']): ?>
                                        <span class="badge badge-success" style="margin-left: 8px;">Diniah</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($subject['level']) ?></td>
                                <td><?= htmlspecialchars($subject['groups']) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="form.php?id=<?= $subject['id'] ?>" class="btn btn-sm btn-outline" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="return confirmDelete('<?= htmlspecialchars($subject['name']) ?>')"
                                                data-href="delete.php?id=<?= $subject['id'] ?>" class="btn btn-sm btn-outline btn-delete" title="Hapus">
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
    </div>
</div>

<style>
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
