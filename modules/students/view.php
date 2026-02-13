<?php
require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(array('Administrator', 'Wali Kelas'));

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    redirect('index.php', 'Siswa tidak ditemukan', 'danger');
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("
    SELECT s.*, c.name as class_name, c.level as class_level
    FROM students s
    JOIN student_enrollments se ON s.id = se.student_id
    JOIN homeroom_teachers ht ON se.homeroom_teacher_id = ht.id
    JOIN classes c ON ht.class_id = c.id
    WHERE s.id = ?
");
$stmt->execute(array($id));
$student = $stmt->fetch();

if (!$student) {
    redirect('index.php', 'Data siswa tidak ditemukan', 'danger');
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>Data Siswa</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php if ($student): ?>
            <table class="table">
                <tr>
                    <th width="150">Foto</th>
                    <th>NIS/NISN</th>
                    <th>Nama Lengkap</th>
                    <th>L/P</th>
                    <th>TTL</th>
                    <th>Alamat</th>
                </tr>
                <tr>
                    <td>
                        <?php if (!empty($student['photo'])): ?>
                            <img src="<?php echo BASE_URL . htmlspecialchars($student['photo']); ?>"
                                 style="width:100px;height:120px;object-fit:cover;border-radius:8px;">
                        <?php else: ?>
                            <div style="width:100px;height:120px;background:#f0f0f0;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-user" style="font-size:40px;"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($student['nis']); ?></strong><br>
                        <small><?php echo htmlspecialchars($student['nisn'] ?? '-'); ?></small>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($student['name']); ?></strong><br>
                        <?php if (!empty($student['birth_place']) || !empty($student['birth_date'])): ?>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($student['birth_place'] ?? ''); ?>,
                                <?php echo formatDate($student['birth_date']); ?>
                                (<?php echo calculateAge($student['birth_date']); ?> tahun)
                            </small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($student['gender']); ?></td>
                    <td>
                        <?php if (!empty($student['father_name']) echo htmlspecialchars($student['father_name']) . '<br>'; ?>
                        <?php if (!empty($student['mother_name'])) echo htmlspecialchars($student['mother_name']); ?>
                    </td>
                    <td><?php echo nl2br(htmlspecialchars($student['address'] ?? '-')); ?></td>
                </tr>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
