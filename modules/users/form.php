<?php
/**
 * User Form (Add/Edit)
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator']);

$pdo = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = null;
$pageTitle = $id ? 'Edit Pengguna' : 'Tambah Pengguna';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        redirect('index.php', 'Data pengguna tidak ditemukan', 'danger');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username' => sanitize($_POST['username'] ?? ''),
        'role' => sanitize($_POST['role'] ?? ''),
        'status' => isset($_POST['status']) ? 1 : 0
    ];

    $password = $_POST['password'] ?? '';

    $errors = [];

    if (empty($data['username'])) {
        $errors[] = 'Username wajib diisi';
    }

    if (empty($data['role']) || !in_array($data['role'], ['Administrator', 'Wali Kelas', 'Guru', 'Ustaz'])) {
        $errors[] = 'Role tidak valid';
    }

    // Check duplicate username
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?" . ($id ? " AND id != ?" : ""));
    $stmt->execute(array_filter([$data['username'], $id]));
    if ($stmt->fetch()) {
        $errors[] = 'Username sudah terdaftar';
    }

    if (!$id && empty($password)) {
        $errors[] = 'Password wajib diisi untuk pengguna baru';
    }

    if (empty($errors)) {
        if ($id) {
            if (!empty($password)) {
                $sql = "UPDATE users SET username = ?, password = ?, role = ?, status = ? WHERE id = ?";
                $params = [$data['username'], hashPassword($password), $data['role'], $data['status'], $id];
            } else {
                $sql = "UPDATE users SET username = ?, role = ?, status = ? WHERE id = ?";
                $params = [$data['username'], $data['role'], $data['status'], $id];
            }
        } else {
            $sql = "INSERT INTO users (username, password, role, status) VALUES (?, ?, ?, ?)";
            $params = [$data['username'], hashPassword($password), $data['role'], $data['status']];
        }

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            redirect('index.php', 'Data pengguna berhasil ' . ($id ? 'diperbarui' : 'ditambahkan'), 'success');
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
                <label for="username" class="form-label form-label-required">Username</label>
                <input type="text" id="username" name="username" class="form-control" required
                       value="<?= htmlspecialchars($user['username'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Password <?= $id ? '(biarkan kosong jika tidak diubah)' : '' ?></label>
                <input type="password" id="password" name="password" class="form-control" minlength="6"
                       placeholder="<?= $id ? '' : 'Minimal 6 karakter' ?>">
            </div>

            <div class="form-group">
                <label for="role" class="form-label form-label-required">Role</label>
                <select id="role" name="role" class="form-control" required>
                    <option value="">-- Pilih Role --</option>
                    <option value="Administrator" <?= (isset($user['role']) && $user['role'] === 'Administrator') ? 'selected' : '' ?>>
                        Administrator
                    </option>
                    <option value="Wali Kelas" <?= (isset($user['role']) && $user['role'] === 'Wali Kelas') ? 'selected' : '' ?>>
                        Wali Kelas
                    </option>
                    <option value="Guru" <?= (isset($user['role']) && $user['role'] === 'Guru') ? 'selected' : '' ?>>
                        Guru
                    </option>
                    <option value="Ustaz" <?= (isset($user['role']) && $user['role'] === 'Ustaz') ? 'selected' : '' ?>>
                        Ustaz
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="status" value="1"
                           <?= (isset($user['status']) && $user['status']) || !$id ? 'checked' : '' ?>>
                    <span>Aktif</span>
                </label>
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
