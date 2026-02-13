<?php
/**
 * Competencies Management
 * Manajemen Capaian Kompetensi
 */

require_once __DIR__ . '/../includes/init.php';

requireLogin();
requireRole(['Administrator', 'Wali Kelas', 'Guru']);

$pageTitle = 'Capaian Kompetensi';
$pdo = getDBConnection();
$academicYear = getActiveAcademicYear();

if (!$academicYear) {
    redirect(BASE_URL . 'modules/dashboard/index.php', 'Silakan atur tahun pelajaran aktif terlebih dahulu', 'warning');
}

// Get filters
$classFilter = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$subjectFilter = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

// Get classes based on role
$classes = [];
if (hasRole(['Administrator', 'Wali Kelas'])) {
    $stmt = $pdo->query("
        SELECT DISTINCT c.*
        FROM classes c
        JOIN homeroom_teachers ht ON c.id = ht.class_id
        WHERE ht.academic_year_id = {$academicYear['id']}
        ORDER BY c.level, c.name
    ");
    $classes = $stmt->fetchAll();
}

// Get competencies
$competencies = [];
if ($classFilter && $subjectFilter) {
    $stmt = $pdo->prepare("
        SELECT c.* FROM competencies c
        WHERE c.subject_id = ?
        ORDER BY c.code, c.description
    ");
    $stmt->execute([$subjectFilter]);
    $competencies = $stmt->fetchAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'subject_id' => (int)($_POST['subject_id'] ?? 0),
        'code' => sanitize($_POST['code'] ?? ''),
        'description' => $_POST['description'] ?? '',
        'type' => sanitize($_POST['type'] ?? 'Pengetahuan')
    ];

    $errors = [];

    if (empty($data['subject_id'])) {
        $errors[] = 'Mata pelajaran wajib dipilih';
    }

    if (empty($data['description'])) {
        $errors[] = 'Deskripsi kompetensi wajib diisi';
    }

    if (empty($errors)) {
        $sql = "INSERT INTO competencies (subject_id, code, description, type) VALUES (?, ?, ?, ?)";
        $params = [$data['subject_id'], $data['code'], $data['description'], $data['type']];

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            redirect('competencies.php?class=' . $classFilter . '&subject=' . $subjectFilter, 'Kompetensi berhasil ditambahkan', 'success');
        } else {
            $errors[] = 'Terjadi kesalahan saat menyimpan data';
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM competencies WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    redirect('competencies.php?class=' . $classFilter . '&subject=' . $subjectFilter, 'Kompetensi berhasil dihapus', 'success');
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.filter-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.filter-group {
    flex: 1;
}

.competency-table {
    width: 100%;
    border-collapse: collapse;
}

.competency-table th,
.competency-table td {
    border: 1px solid var(--border-color);
    padding: 12px;
    text-align: left;
}

.competency-table th {
    background: var(--light-gray);
    font-weight: 600;
}
</style>

<div class="page-header">
    <h1>Capaian Kompetensi</h1>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <form method="GET" action="" class="filter-bar">
            <div class="filter-group">
                <select name="class" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['id'] ?>" <?= $classFilter == $class['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <select name="subject" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Pilih Mapel --</option>
                    <?php
                    if ($classFilter) {
                        $stmt = $pdo->prepare("
                            SELECT * FROM subjects
                            WHERE level = (SELECT level FROM classes WHERE id = ?)
                            ORDER BY groups, name
                        ");
                        $stmt->execute([$classFilter]);
                        $subjects = $stmt->fetchAll();

                        foreach ($subjects as $subject):
                    ?>
                        <option value="<?= $subject['id'] ?>" <?= $subjectFilter == $subject['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subject['name']) ?>
                        </option>
                    <?php endforeach; } ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($classFilter && $subjectFilter): ?>
        <div class="card-body">
            <!-- Add Competency Form -->
            <div style="background: var(--light-gray); padding: 20px; border-radius: var(--radius-md); margin-bottom: 20px;">
                <h4 style="margin-bottom: 15px;">Tambah Kompetensi Baru</h4>
                <form method="POST" action="">
                    <input type="hidden" name="subject_id" value="<?= $subjectFilter ?>">
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="font-size: 13px; font-weight: 500;">Kode</label>
                            <input type="text" name="code" class="form-control" placeholder="Contoh: 3.1">
                        </div>
                        <div>
                            <label style="font-size: 13px; font-weight: 500;">Tipe</label>
                            <select name="type" class="form-control">
                                <option value="Pengetahuan">Pengetahuan</option>
                                <option value="Keterampilan">Keterampilan</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 13px; font-weight: 500;">Deskripsi Kompetensi</label>
                            <input type="text" name="description" class="form-control" required placeholder="Deskripsi kompetensi">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Tambah
                    </button>
                </form>
            </div>

            <!-- Competencies List -->
            <div class="table-responsive">
                <table class="table competency-table">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th width="80">Kode</th>
                            <th>Deskripsi Kompetensi</th>
                            <th width="120">Tipe</th>
                            <th width="80">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($competencies)): ?>
                            <tr>
                                <td colspan="5" class="text-center">
                                    <div class="empty-state">
                                        <p class="empty-state-title">Belum ada kompetensi</p>
                                        <p class="empty-state-text">Tambahkan kompetensi menggunakan form di atas.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($competencies as $comp): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><span class="badge badge-primary"><?= htmlspecialchars($comp['code']) ?></span></td>
                                    <td><?= htmlspecialchars($comp['description']) ?></td>
                                    <td><?= htmlspecialchars($comp['type']) ?></td>
                                    <td>
                                        <a href="?class=<?= $classFilter ?>&subject=<?= $subjectFilter ?>&delete=<?= $comp['id'] ?>"
                                           class="btn btn-sm btn-outline btn-delete"
                                           onclick="return confirm('Hapus kompetensi ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-bullseye empty-state-icon"></i>
                <p class="empty-state-title">Pilih kelas dan mata pelajaran</p>
                <p class="empty-state-text">Pilih kelas dan mata pelajaran untuk mengelola kompetensi.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
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
        if (!confirm('')) {
            event.preventDefault();
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
