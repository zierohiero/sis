<?php
/**
 * Student Form (Add/Edit)
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pdo = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student = null;
$pageTitle = $id ? 'Edit Siswa' : 'Tambah Siswa';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();

    if (!$student) {
        redirect('index.php', 'Data siswa tidak ditemukan', 'danger');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nis' => sanitize($_POST['nis'] ?? ''),
        'nisn' => sanitize($_POST['nisn'] ?? ''),
        'name' => sanitize($_POST['name'] ?? ''),
        'gender' => sanitize($_POST['gender'] ?? ''),
        'birth_place' => sanitize($_POST['birth_place'] ?? ''),
        'birth_date' => $_POST['birth_date'] ?? null,
        'father_name' => sanitize($_POST['father_name'] ?? ''),
        'mother_name' => sanitize($_POST['mother_name'] ?? ''),
        'address' => $_POST['address'] ?? '',
        'phone' => sanitize($_POST['phone'] ?? ''),
        'status' => sanitize($_POST['status'] ?? 'Aktif')
    ];

    // Validation
    $errors = [];

    if (empty($data['name'])) {
        $errors[] = 'Nama siswa wajib diisi';
    }

    if (empty($data['gender']) || !in_array($data['gender'], ['L', 'P'])) {
        $errors[] = 'Jenis kelamin tidak valid';
    }

    // Check duplicate NIS
    $stmt = $pdo->prepare("SELECT id FROM students WHERE nis = ? AND id != ?");
    $stmt->execute([$data['nis'], $id]);
    if ($stmt->fetch()) {
        $errors[] = 'NIS sudah terdaftar';
    }

    // Check duplicate NISN
    if (!empty($data['nisn'])) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE nisn = ? AND id != ?");
        $stmt->execute([$data['nisn'], $id]);
        if ($stmt->fetch()) {
            $errors[] = 'NISN sudah terdaftar';
        }
    }

    // Handle photo upload
    $photoPath = $student['photo'] ?? null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFile($_FILES['photo'], STUDENT_PHOTO_PATH);
        if ($uploadResult['success']) {
            // Delete old photo
            if ($photoPath) {
                deleteFile($photoPath, STUDENT_PHOTO_PATH);
            }
            $photoPath = '/assets/uploads/students/' . $uploadResult['filename'];
        } else {
            $errors[] = 'Gagal mengupload foto: ' . $uploadResult['message'];
        }
    }

    if (empty($errors)) {
        if ($id) {
            // Update
            $sql = "UPDATE students SET nis = ?, nisn = ?, name = ?, gender = ?, birth_place = ?,
                    birth_date = ?, father_name = ?, mother_name = ?, address = ?, phone = ?,
                    status = ?, photo = COALESCE(?, photo)
                    WHERE id = ?";
            $params = [
                $data['nis'], $data['nisn'], $data['name'], $data['gender'], $data['birth_place'],
                $data['birth_date'], $data['father_name'], $data['mother_name'], $data['address'],
                $data['phone'], $data['status'], $photoPath, $id
            ];
        } else {
            // Insert
            $sql = "INSERT INTO students (nis, nisn, name, gender, birth_place, birth_date,
                    father_name, mother_name, address, phone, status, photo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $data['nis'], $data['nisn'], $data['name'], $data['gender'], $data['birth_place'],
                $data['birth_date'], $data['father_name'], $data['mother_name'], $data['address'],
                $data['phone'], $data['status'], $photoPath
            ];
        }

        $stmt = $pdo->prepare($sql);

        if ($stmt->execute($params)) {
            redirect('index.php', 'Data siswa berhasil ' . ($id ? 'diperbarui' : 'ditambahkan'), 'success');
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
        <form method="POST" action="" enctype="multipart/form-data" class="form-grid">
            <div class="form-section">
                <h3 class="form-section-title">Informasi Pribadi</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nis" class="form-label form-label-required">NIS</label>
                        <input type="text" id="nis" name="nis" class="form-control" required
                               value="<?= htmlspecialchars($student['nis'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="nisn">NISN</label>
                        <input type="text" id="nisn" name="nisn" class="form-control"
                               value="<?= htmlspecialchars($student['nisn'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="name" class="form-label form-label-required">Nama Lengkap</label>
                    <input type="text" id="name" name="name" class="form-control" required
                           value="<?= htmlspecialchars($student['name'] ?? '') ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="gender" class="form-label form-label-required">Jenis Kelamin</label>
                        <select id="gender" name="gender" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            <option value="L" <?= (isset($student['gender']) && $student['gender'] === 'L') ? 'selected' : '' ?>>
                                Laki-laki
                            </option>
                            <option value="P" <?= (isset($student['gender']) && $student['gender'] === 'P') ? 'selected' : '' ?>>
                                Perempuan
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="birth_place">Tempat Lahir</label>
                        <input type="text" id="birth_place" name="birth_place" class="form-control"
                               value="<?= htmlspecialchars($student['birth_place'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="birth_date">Tanggal Lahir</label>
                    <input type="date" id="birth_date" name="birth_date" class="form-control"
                           value="<?= htmlspecialchars($student['birth_date'] ?? '') ?>">
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Informasi Orang Tua</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="father_name">Nama Ayah</label>
                        <input type="text" id="father_name" name="father_name" class="form-control"
                               value="<?= htmlspecialchars($student['father_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="mother_name">Nama Ibu</label>
                        <input type="text" id="mother_name" name="mother_name" class="form-control"
                               value="<?= htmlspecialchars($student['mother_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Alamat</label>
                    <textarea id="address" name="address" class="form-control" rows="3"><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">No. Telepon</label>
                        <input type="text" id="phone" name="phone" class="form-control"
                               value="<?= htmlspecialchars($student['phone'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="Aktif" <?= (isset($student['status']) && $student['status'] === 'Aktif') ? 'selected' : '' ?>>
                                Aktif
                            </option>
                            <option value="Tidak Aktif" <?= (isset($student['status']) && $student['status'] === 'Tidak Aktif') ? 'selected' : '' ?>>
                                Tidak Aktif
                            </option>
                            <option value="Lulus" <?= (isset($student['status']) && $student['status'] === 'Lulus') ? 'selected' : '' ?>>
                                Lulus
                            </option>
                            <option value="Keluar" <?= (isset($student['status']) && $student['status'] === 'Keluar') ? 'selected' : '' ?>>
                                Keluar
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Foto</h3>

                <div class="form-group">
                    <label for="photo">Upload Foto</label>
                    <input type="file" id="photo" name="photo" class="form-control" accept="image/jpeg,image/png,image/jpg">
                    <small class="form-text">Format: JPG, PNG. Maksimal 2MB.</small>

                    <?php if (!empty($student['photo'])): ?>
                        <div class="photo-preview">
                            <img src="<?= BASE_URL . htmlspecialchars($student['photo']) ?>" alt="Foto Siswa">
                            <p class="photo-label">Foto saat ini</p>
                        </div>
                    <?php endif; ?>
                </div>
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

.page-header h1 {
    font-size: 24px;
    font-weight: 700;
    color: var(--dark-color);
}

.form-grid {
    display: grid;
    gap: 30px;
}

.form-section {
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 20px;
}

.form-section-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-color);
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

.photo-preview {
    margin-top: 15px;
    text-align: center;
}

.photo-preview img {
    width: 150px;
    height: 200px;
    object-fit: cover;
    border-radius: var(--radius-md);
    border: 2px solid var(--border-color);
}

.photo-label {
    margin-top: 8px;
    font-size: 13px;
    color: var(--gray-color);
}

.form-actions {
    display: flex;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
