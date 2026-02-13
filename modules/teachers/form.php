<?php
/**
 * Teacher Form (Add/Edit)
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pdo = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$teacher = null;
$pageTitle = $id ? 'Edit Guru' : 'Tambah Guru';

if ($id) {
    $stmt = $pdo->prepare("SELECT t.*, u.username, u.role FROM teachers t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ?");
    $stmt->execute([$id]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        redirect('index.php', 'Data guru tidak ditemukan', 'danger');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nip' => sanitize($_POST['nip'] ?? ''),
        'name' => sanitize($_POST['name'] ?? ''),
        'gender' => sanitize($_POST['gender'] ?? ''),
        'birth_place' => sanitize($_POST['birth_place'] ?? ''),
        'birth_date' => $_POST['birth_date'] ?? null,
        'address' => $_POST['address'] ?? '',
        'phone' => sanitize($_POST['phone'] ?? ''),
        'username' => sanitize($_POST['username'] ?? ''),
        'role' => sanitize($_POST['role'] ?? 'Guru')
    ];

    // Validation
    $errors = [];

    if (empty($data['name'])) {
        $errors[] = 'Nama guru wajib diisi';
    }

    if (empty($data['gender']) || !in_array($data['gender'], ['L', 'P'])) {
        $errors[] = 'Jenis kelamin tidak valid';
    }

    // Check duplicate NIP
    if (!empty($data['nip'])) {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE nip = ? AND id != ?");
        $stmt->execute([$data['nip'], $id]);
        if ($stmt->fetch()) {
            $errors[] = 'NIP sudah terdaftar';
        }
    }

    // Handle photo upload
    $photoPath = $teacher['photo'] ?? null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFile($_FILES['photo'], TEACHER_PHOTO_PATH);
        if ($uploadResult['success']) {
            if ($photoPath) {
                deleteFile($photoPath, TEACHER_PHOTO_PATH);
            }
            $photoPath = '/assets/uploads/teachers/' . $uploadResult['filename'];
        } else {
            $errors[] = 'Gagal mengupload foto: ' . $uploadResult['message'];
        }
    }

    if (empty($errors)) {
        $pdo->beginTransaction();

        try {
            // Handle user account
            if (!empty($data['username'])) {
                // Check if username exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?" . ($id ? " AND id != ?" : ""));
                $stmt->execute(array_filter([$data['username'], $id]));
                if ($stmt->fetch()) {
                    throw new Exception('Username sudah terdaftar');
                }

                // Password
                $password = $_POST['password'] ?? '';
                if (empty($password) && !$id) {
                    $password = 'guru123'; // Default password
                }

                if ($id) {
                    // Update existing user or create new
                    $userId = $teacher['user_id'] ?? null;
                    if (!empty($password)) {
                        $passwordHash = hashPassword($password);
                        if ($userId) {
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                            $stmt->execute([$data['username'], $passwordHash, $data['role'], $userId]);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                            $stmt->execute([$data['username'], $passwordHash, $data['role']]);
                            $userId = $pdo->lastInsertId();
                        }
                    } else if ($userId) {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                        $stmt->execute([$data['username'], $data['role'], $userId]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                        $stmt->execute([$data['username'], hashPassword($password), $data['role']]);
                        $userId = $pdo->lastInsertId();
                    }
                } else {
                    // New user
                    $passwordHash = hashPassword($password);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                    $stmt->execute([$data['username'], $passwordHash, $data['role']]);
                    $userId = $pdo->lastInsertId();
                }

                $data['user_id'] = $userId;
            }

            // Save teacher data
            if ($id) {
                $sql = "UPDATE teachers SET nip = ?, name = ?, gender = ?, birth_place = ?,
                        birth_date = ?, address = ?, phone = ?, user_id = ?, photo = COALESCE(?, photo)
                        WHERE id = ?";
                $params = [
                    $data['nip'], $data['name'], $data['gender'], $data['birth_place'],
                    $data['birth_date'], $data['address'], $data['phone'], $data['user_id'] ?? null,
                    $photoPath, $id
                ];
            } else {
                $sql = "INSERT INTO teachers (nip, name, gender, birth_place, birth_date, address, phone, user_id, photo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = [
                    $data['nip'], $data['name'], $data['gender'], $data['birth_place'],
                    $data['birth_date'], $data['address'], $data['phone'], $data['user_id'] ?? null, $photoPath
                ];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $pdo->commit();
            redirect('index.php', 'Data guru berhasil ' . ($id ? 'diperbarui' : 'ditambahkan'), 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
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
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-section">
                <h3 class="form-section-title">Informasi Pribadi</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nip">NIP</label>
                        <input type="text" id="nip" name="nip" class="form-control"
                               value="<?= htmlspecialchars($teacher['nip'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="name" class="form-label form-label-required">Nama Lengkap</label>
                        <input type="text" id="name" name="name" class="form-control" required
                               value="<?= htmlspecialchars($teacher['name'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="gender" class="form-label form-label-required">Jenis Kelamin</label>
                        <select id="gender" name="gender" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            <option value="L" <?= (isset($teacher['gender']) && $teacher['gender'] === 'L') ? 'selected' : '' ?>>
                                Laki-laki
                            </option>
                            <option value="P" <?= (isset($teacher['gender']) && $teacher['gender'] === 'P') ? 'selected' : '' ?>>
                                Perempuan
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="birth_place">Tempat Lahir</label>
                        <input type="text" id="birth_place" name="birth_place" class="form-control"
                               value="<?= htmlspecialchars($teacher['birth_place'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="birth_date">Tanggal Lahir</label>
                    <input type="date" id="birth_date" name="birth_date" class="form-control"
                           value="<?= htmlspecialchars($teacher['birth_date'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="address">Alamat</label>
                    <textarea id="address" name="address" class="form-control" rows="3"><?= htmlspecialchars($teacher['address'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="phone">No. Telepon</label>
                    <input type="text" id="phone" name="phone" class="form-control"
                           value="<?= htmlspecialchars($teacher['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Akun Pengguna</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control"
                               value="<?= htmlspecialchars($teacher['username'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" class="form-control">
                            <option value="Guru" <?= (isset($teacher['role']) && $teacher['role'] === 'Guru') ? 'selected' : '' ?>>Guru</option>
                            <option value="Wali Kelas" <?= (isset($teacher['role']) && $teacher['role'] === 'Wali Kelas') ? 'selected' : '' ?>>Wali Kelas</option>
                            <option value="Ustaz" <?= (isset($teacher['role']) && $teacher['role'] === 'Ustaz') ? 'selected' : '' ?>>Ustaz</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password <?= $id ? '(Biarkan kosong jika tidak diubah)' : '' ?></label>
                    <input type="password" id="password" name="password" class="form-control" minlength="6">
                    <small class="form-text">Minimal 6 karakter. Default: guru123</small>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Foto</h3>

                <div class="form-group">
                    <label for="photo">Upload Foto</label>
                    <input type="file" id="photo" name="photo" class="form-control" accept="image/*">

                    <?php if (!empty($teacher['photo'])): ?>
                        <div class="photo-preview">
                            <img src="<?= BASE_URL . htmlspecialchars($teacher['photo']) ?>" alt="Foto Guru">
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

.form-section {
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 20px;
    margin-bottom: 20px;
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
