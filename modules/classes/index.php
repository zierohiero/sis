<?php
/**
 * Classes Management
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pageTitle = 'Data Kelas';
$pdo = getDBConnection();

// Get all classes
$stmt = $pdo->query("
    SELECT c.*, COUNT(DISTINCT ht.id) as homeroom_count
    FROM classes c
    LEFT JOIN homeroom_teachers ht ON c.id = ht.class_id
    GROUP BY c.id
    ORDER BY c.level, c.name
");
$classes = $stmt->fetchAll();

// Get class groups by level
$classesByLevel = [];
foreach ($classes as $class) {
    $classesByLevel[$class['level']][] = $class;
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1>Data Kelas</h1>
        <div class="page-actions">
            <a href="form.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Kelas
            </a>
        </div>
    </div>
</div>

<?php foreach (['X', 'XI', 'XII'] as $level): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= getLevelLabel($level) ?></h3>
        </div>
        <div class="card-body">
            <?php if (empty($classesByLevel[$level])): ?>
                <p class="text-muted">Belum ada kelas untuk tingkat ini.</p>
            <?php else: ?>
                <div class="class-grid">
                    <?php foreach ($classesByLevel[$level] as $class): ?>
                        <div class="class-card">
                            <div class="class-icon">
                                <i class="fas fa-door-open"></i>
                            </div>
                            <div class="class-info">
                                <h4><?= htmlspecialchars($class['name']) ?></h4>
                                <p class="text-muted">
                                    Kapasitas: <?= $class['capacity'] ?> siswa
                                </p>
                                <p class="text-muted">
                                    Wali Kelas: <?= $class['homeroom_count'] ?> periode
                                </p>
                            </div>
                            <div class="class-actions">
                                <a href="form.php?id=<?= $class['id'] ?>" class="btn btn-sm btn-outline" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="return confirmDelete('<?= htmlspecialchars($class['name']) ?>')"
                                        data-href="delete.php?id=<?= $class['id'] ?>" class="btn btn-sm btn-outline btn-delete" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.page-header-content h1 {
    font-size: 24px;
    font-weight: 700;
}

.class-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.class-card {
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 20px;
    display: flex;
    gap: 15px;
    transition: box-shadow 0.3s;
}

.class-card:hover {
    box-shadow: var(--shadow-md);
}

.class-icon {
    width: 50px;
    height: 50px;
    background: var(--primary-color);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
}

.class-info {
    flex: 1;
}

.class-info h4 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--dark-color);
}

.class-info p {
    font-size: 13px;
    margin-bottom: 4px;
}

.class-actions {
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
function confirmDelete(name) {
    return confirm(`Apakah Anda yakin ingin menghapus kelas "${name}"?`);
}

document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function() {
        if (confirmDelete('')) {
            window.location.href = this.getAttribute('data-href');
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
