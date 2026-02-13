<?php
/**
 * Class Form (Add/Edit)
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pdo = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$class = null;
$pageTitle = $id ? 'Edit Kelas' : 'Tambah Kelas';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$id]);
    $class = $stmt->fetch();

    if (!$class) {
        redirect('index.php', 'Data kelas tidak ditemukan', 'danger');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => sanitize($_POST['name'] ?? ''),
        'level' => sanitize($_POST['level'] ?? ''),
        'capacity' => (int)($_POST['capacity'] ?? 32)
    ];

    $errors = [];

    if (empty($data['name'])) {
        $errors[] = 'Nama kelas wajib diisi';
    }

    if (empty($data['level']) || !in_array($data['level'], ['X', 'XI', 'XII'])) {
        $errors[] = 'Tingkat kelas tidak valid';
    }

    // Check duplicate name
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE name = ? AND id != ?");
    $stmt->execute([$data['name'], $id]);
    if ($stmt->fetch()) {
        $errors[] = 'Nama kelas sudah terdaftar';
    }

    if (empty($errors)) {
        if ($id) {
            $sql = "UPDATE classes SET name = ?, level = ?, capacity = ? WHERE id = ?";
            $params = [$data['name'], $data['level'], $data['capacity'], $id];
        } else {
            $sql = "INSERT INTO classes (name, level, capacity) VALUES (?, ?, ?)";
            $params = [$data['name'], $data['level'], $data['capacity']];
        }

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            redirect('index.php', 'Data kelas berhasil ' . ($id ? 'diperbarui' : 'ditambahkan'), 'success');
        } else {
            $errors[] = 'Terjadi kesalahan saat menyimpan data';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars($pageTitle) ?></h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>
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
    <div class="card-body">
        <form method="POST" action="">
            <div class="form-group">
                <label for="name" class="form-label form-label-required">Nama Kelas</label>
                <input type="text" id="name" name="name" class="form-control" required
                       placeholder="Contoh: X-A, XI-B, XII-C"
                       value="<?= htmlspecialchars($class['name'] ?? '') ?>">
                <small class="form-text">Gunakan format huruf kapital diikuti tanda hubung dan nama kelas (contoh: X-A)</small>
            </div>

            <div class="form-group">
                <label for="level" class="form-label form-label-required">Tingkat</label>
                <select id="level" name="level" class="form-control" required>
                    <option value="">-- Pilih Tingkat --</option>
                    <option value="X" <?= (isset($class['level']) && $class['level'] === 'X') ? 'selected' : '' ?>>
                        Kelas X
                    </option>
                    <option value="XI" <?= (isset($class['level']) && $class['level'] === 'XI') ? 'selected' : '' ?>>
                        Kelas XI
                    </option>
                    <option value="XII" <?= (isset($class['level']) && $class['level'] === 'XII') ? 'selected' : '' ?>>
                        Kelas XII
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="capacity">Kapasitas</label>
                <input type="number" id="capacity" name="capacity" class="form-control" min="1" max="50"
                       value="<?= htmlspecialchars($class['capacity'] ?? 32) ?>">
                <small class="form-text">Jumlah maksimal siswa dalam satu kelas</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.form-actions {
    display: flex;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
