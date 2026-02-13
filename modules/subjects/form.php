<?php
/**
 * Subject Form (Add/Edit)
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pdo = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$subject = null;
$pageTitle = $id ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$id]);
    $subject = $stmt->fetch();

    if (!$subject) {
        redirect('index.php', 'Data mata pelajaran tidak ditemukan', 'danger');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'code' => sanitize($_POST['code'] ?? ''),
        'name' => sanitize($_POST['name'] ?? ''),
        'groups' => sanitize($_POST['groups'] ?? ''),
        'level' => sanitize($_POST['level'] ?? ''),
        'is_diniah' => isset($_POST['is_diniah']) ? 1 : 0
    ];

    $errors = [];

    if (empty($data['name'])) {
        $errors[] = 'Nama mata pelajaran wajib diisi';
    }

    if (empty($data['groups']) || !in_array($data['groups'], ['Umum', 'Pilihan', 'Muatan Lokal', 'Diniah'])) {
        $errors[] = 'Kelompok mata pelajaran tidak valid';
    }

    if (empty($data['level']) || !in_array($data['level'], ['X', 'XI', 'XII'])) {
        $errors[] = 'Tingkat kelas tidak valid';
    }

    // Check duplicate code
    if (!empty($data['code'])) {
        $stmt = $pdo->prepare("SELECT id FROM subjects WHERE code = ? AND id != ?");
        $stmt->execute([$data['code'], $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Kode mata pelajaran sudah terdaftar';
        }
    }

    // Auto-set is_diniah if group is Diniah
    if ($data['groups'] === 'Diniah') {
        $data['is_diniah'] = 1;
    }

    if (empty($errors)) {
        if ($id) {
            $sql = "UPDATE subjects SET code = ?, name = ?, groups = ?, level = ?, is_diniah = ? WHERE id = ?";
            $params = [$data['code'], $data['name'], $data['groups'], $data['level'], $data['is_diniah'], $id];
        } else {
            $sql = "INSERT INTO subjects (code, name, groups, level, is_diniah) VALUES (?, ?, ?, ?, ?)";
            $params = [$data['code'], $data['name'], $data['groups'], $data['level'], $data['is_diniah']];
        }

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            redirect('index.php', 'Data mata pelajaran berhasil ' . ($id ? 'diperbarui' : 'ditambahkan'), 'success');
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
            <div class="form-row">
                <div class="form-group">
                    <label for="code">Kode</label>
                    <input type="text" id="code" name="code" class="form-control"
                           placeholder="Contoh: MAT, BIN, BIG"
                           value="<?= htmlspecialchars($subject['code'] ?? '') ?>">
                    <small class="form-text">Kode singkat untuk mata pelajaran (opsional)</small>
                </div>

                <div class="form-group">
                    <label for="name" class="form-label form-label-required">Nama Mata Pelajaran</label>
                    <input type="text" id="name" name="name" class="form-control" required
                           placeholder="Contoh: Matematika, Bahasa Indonesia"
                           value="<?= htmlspecialchars($subject['name'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="groups" class="form-label form-label-required">Kelompok</label>
                    <select id="groups" name="groups" class="form-control" required>
                        <option value="">-- Pilih Kelompok --</option>
                        <option value="Umum" <?= (isset($subject['groups']) && $subject['groups'] === 'Umum') ? 'selected' : '' ?>>
                            Umum
                        </option>
                        <option value="Pilihan" <?= (isset($subject['groups']) && $subject['groups'] === 'Pilihan') ? 'selected' : '' ?>>
                            Pilihan
                        </option>
                        <option value="Muatan Lokal" <?= (isset($subject['groups']) && $subject['groups'] === 'Muatan Lokal') ? 'selected' : '' ?>>
                            Muatan Lokal
                        </option>
                        <option value="Diniah" <?= (isset($subject['groups']) && $subject['groups'] === 'Diniah') ? 'selected' : '' ?>>
                            Diniah
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="level" class="form-label form-label-required">Tingkat</label>
                    <select id="level" name="level" class="form-control" required>
                        <option value="">-- Pilih Tingkat --</option>
                        <option value="X" <?= (isset($subject['level']) && $subject['level'] === 'X') ? 'selected' : '' ?>>
                            Kelas X
                        </option>
                        <option value="XI" <?= (isset($subject['level']) && $subject['level'] === 'XI') ? 'selected' : '' ?>>
                            Kelas XI
                        </option>
                        <option value="XII" <?= (isset($subject['level']) && $subject['level'] === 'XII') ? 'selected' : '' ?>>
                            Kelas XII
                        </option>
                    </select>
                </div>
            </div>

            <?php if (isset($subject['groups']) && $subject['groups'] !== 'Diniah'): ?>
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="is_diniah" value="1"
                               <?= (isset($subject['is_diniah']) && $subject['is_diniah']) ? 'checked' : '' ?>>
                        <span>Termasuk mata pelajaran Diniah (akan dihitung terpisah di rapor)</span>
                    </label>
                </div>
            <?php endif; ?>

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

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

.form-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.form-checkbox span {
    color: var(--dark-color);
}

.form-actions {
    display: flex;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
