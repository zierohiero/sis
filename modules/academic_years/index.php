<?php
/**
 * Academic Years Management
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pageTitle = 'Tahun Pelajaran';
$pdo = getDBConnection();

// Get all academic years
$stmt = $pdo->query("
    SELECT *,
           (SELECT COUNT(*) FROM homeroom_teachers WHERE academic_year_id = academic_years.id) as class_count
    FROM academic_years
    ORDER BY period DESC, semester DESC
");
$academicYears = $stmt->fetchAll();

// Handle form submission (activate/deactivate)
if (isset($_GET['activate'])) {
    $pdo->beginTransaction();

    try {
        // Deactivate all
        $pdo->query("UPDATE academic_years SET status = 'Nonaktif'");

        // Activate selected
        $stmt = $pdo->prepare("UPDATE academic_years SET status = 'Aktif' WHERE id = ?");
        $stmt->execute([(int)$_GET['activate']]);

        $pdo->commit();
        redirect('index.php', 'Tahun pelajaran berhasil diaktifkan', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        redirect('index.php', 'Gagal mengaktifkan tahun pelajaran', 'danger');
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1>Tahun Pelajaran</h1>
        <div class="page-actions">
            <a href="form.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Tahun Pelajaran
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
                        <th>Periode</th>
                        <th>Semester</th>
                        <th>Kelas Terdaftar</th>
                        <th>Status</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($academicYears)): ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                <div class="empty-state">
                                    <i class="fas fa-calendar-alt empty-state-icon"></i>
                                    <p class="empty-state-title">Tidak ada data tahun pelajaran</p>
                                    <p class="empty-state-text">Silakan tambah tahun pelajaran baru.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; ?>
                        <?php foreach ($academicYears as $ay): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($ay['period']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($ay['semester']) ?></td>
                                <td><?= $ay['class_count'] ?> kelas</td>
                                <td>
                                    <?php if ($ay['status'] === 'Aktif'): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($ay['status'] !== 'Aktif'): ?>
                                            <a href="index.php?activate=<?= $ay['id'] ?>"
                                               class="btn btn-sm btn-success" title="Aktifkan">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="form.php?id=<?= $ay['id'] ?>" class="btn btn-sm btn-outline" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($ay['status'] !== 'Aktif' && $ay['class_count'] == 0): ?>
                                            <button onclick="return confirm('Hapus tahun pelajaran <?= htmlspecialchars($ay['period']) ?>?')"
                                                    data-href="delete.php?id=<?= $ay['id'] ?>" class="btn btn-sm btn-outline btn-delete" title="Hapus">
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
