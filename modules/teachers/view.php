<?php
require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(array('Administrator'));

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    redirect('index.php', 'Guru tidak ditemukan', 'danger');
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("
    SELECT t.*, u.username, u.role
    FROM teachers t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
");
$stmt->execute(array($id));
$teacher = $stmt->fetch();

if (!$teacher) {
    redirect('index.php', 'Data guru tidak ditemukan', 'danger');
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>Data Guru</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php if ($teacher): ?>
            <table class="table">
                <tr>
                    <th width="150">Foto</th>
                    <th>NIP</th>
                    <th>Nama</th>
                    <th>L/P</th>
                    <th>Username</th>
                    <th>Role</th>
                </tr>
                <tr>
                    <td>
                        <?php if (!empty($teacher['photo'])): ?>
                            <img src="<?php echo BASE_URL . htmlspecialchars($teacher['photo']); ?>"
                                 style="width:120px;height:140px;object-fit:cover;border-radius:8px;">
                        <?php else: ?>
                            <div style="width:120px;height:140px;background:#f0f0f0;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-user-tie" style="font-size:40px;"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars($teacher['nip'] ?? '-'); ?></strong></td>
                    <td><strong><?php echo htmlspecialchars($teacher['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($teacher['gender']); ?></td>
                    <td><?php echo htmlspecialchars($teacher['username'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($teacher['role']); ?></td>
                </tr>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
