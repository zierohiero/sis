<?php
/**
 * Academic Year Form (Add/Edit)
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pdo = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$academicYear = null;
$pageTitle = $id ? 'Edit Tahun Pelajaran' : 'Tambah Tahun Pelajaran';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM academic_years WHERE id = ?");
    $stmt->execute([$id]);
    $academicYear = $stmt->fetch();

    if (!$academicYear) {
        redirect('index.php', 'Data tahun pelajaran tidak ditemukan', 'danger');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'period' => sanitize($_POST['period'] ?? ''),
        'semester' => sanitize($_POST['semester'] ?? ''),
        'status' => sanitize($_POST['status'] ?? 'Nonaktif')
    ];

    $errors = [];

    if (empty($data['period'])) {
        $errors[] = 'Periode wajib diisi';
    }

    if (empty($data['semester']) || !in_array($data['semester'], ['Gasal', 'Genap'])) {
        $errors[] = 'Semester tidak valid';
    }

    // Validate period format (YYYY/YYYY)
    if (!empty($data['period']) && !preg_match('/^\d{4}\/\d{4}$/', $data['period'])) {
        $errors[] = 'Format periode harus YYYY/YYYY (contoh: 2024/2025)';
    }

    // Check duplicate
    $stmt = $pdo->prepare("SELECT id FROM academic_years WHERE period = ? AND semester = ?" . ($id ? " AND id != ?" : ""));
    $stmt->execute(array_filter([$data['period'], $data['semester'], $id]));
    if ($stmt->fetch()) {
        $errors[] = 'Tahun pelajaran untuk periode dan semester ini sudah ada';
    }

    if (empty($errors)) {
        if ($id) {
            $sql = "UPDATE academic_years SET period = ?, semester = ?, status = ? WHERE id = ?";
            $params = [$data['period'], $data['semester'], $data['status'], $id];
        } else {
            $sql = "INSERT INTO academic_years (period, semester, status) VALUES (?, ?, ?)";
            $params = [$data['period'], $data['semester'], $data['status']];
        }

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            // If activating, deactivate others
            if ($data['status'] === 'Aktif') {
                $pdo->query("UPDATE academic_years SET status = 'Nonaktif' WHERE id != " . ($id ?: $pdo->lastInsertId()));
            }

            redirect('index.php', 'Data tahun pelajaran berhasil ' . ($id ? 'diperbarui' : 'ditambahkan'), 'success');
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
                <label for="period" class="form-label form-label-required">Periode</label>
                <input type="text" id="period" name="period" class="form-control" required
                       placeholder="Contoh: 2024/2025"
                       value="<?= htmlspecialchars($academicYear['period'] ?? '') ?>">
                <small class="form-text">Format: YYYY/YYYY (contoh: 2024/2025)</small>
            </div>

            <div class="form-group">
                <label for="semester" class="form-label form-label-required">Semester</label>
                <select id="semester" name="semester" class="form-control" required>
                    <option value="">-- Pilih Semester --</option>
                    <option value="Gasal" <?= (isset($academicYear['semester']) && $academicYear['semester'] === 'Gasal') ? 'selected' : '' ?>>
                        Gasal (Ganjil)
                    </option>
                    <option value="Genap" <?= (isset($academicYear['semester']) && $academicYear['semester'] === 'Genap') ? 'selected' : '' ?>>
                        Genap
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="status" value="Aktif"
                           <?= (isset($academicYear['status']) && $academicYear['status'] === 'Aktif') ? 'checked' : '' ?>>
                    <span>Set sebagai tahun pelajaran aktif</span>
                </label>
                <small class="form-text">Hanya satu tahun pelajaran yang dapat aktif dalam satu waktu</small>
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

.form-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.form-actions {
    display: flex;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
