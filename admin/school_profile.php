<?php
/**
 * School Profile Management
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../includes/init.php';

requireLogin();
requireRole('Administrator');

$pageTitle = 'Profil Sekolah';
$pdo = getDBConnection();

// Get current school profile
$stmt = $pdo->prepare("SELECT * FROM school_profile LIMIT 1");
$stmt->execute();
$school = $stmt->fetch();

// Handle DELETE request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && $school) {
    // Verify CSRF token
    if (!isset($_GET['token']) || !verifyCSRFToken($_GET['token'])) {
        redirect('school_profile.php', 'Token tidak valid', 'danger');
    }

    // Delete logo file if exists
    if (!empty($school['logo'])) {
        $logoPath = BASE_PATH . $school['logo'];
        if (file_exists($logoPath)) {
            unlink($logoPath);
        }
    }

    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM school_profile WHERE id = ?");
    if ($stmt->execute([$school['id']])) {
        logActivity('delete', 'school_profile', 'Hapus profil sekolah', $school['id']);
        redirect('school_profile.php', 'Profil sekolah berhasil dihapus', 'success');
    } else {
        redirect('school_profile.php', 'Gagal menghapus profil sekolah', 'danger');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = array(
        'name' => sanitize($_POST['name'] ?? ''),
        'npsn' => sanitize($_POST['npsn'] ?? ''),
        'address' => $_POST['address'] ?? '',
        'phone' => sanitize($_POST['phone'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'website' => sanitize($_POST['website'] ?? ''),
        'principal_name' => sanitize($_POST['principal_name'] ?? ''),
        'principal_nip' => sanitize($_POST['principal_nip'] ?? '')
    );

    $errors = array();

    if (empty($data['name'])) {
        $errors[] = 'Nama sekolah wajib diisi';
    }

    // Validate email
    if (!empty($data['email']) && !validateEmail($data['email'])) {
        $errors[] = 'Format email tidak valid';
    }

    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFile($_FILES['logo'], BASE_PATH . '/assets/uploads/');
        if ($uploadResult['success']) {
            $data['logo'] = '/assets/uploads/' . $uploadResult['filename'];
        } else {
            $errors[] = 'Gagal mengupload logo: ' . $uploadResult['message'];
        }
    }

    if (empty($errors)) {
        if ($school) {
            // Update
            $sql = "UPDATE school_profile SET name = ?, npsn = ?, address = ?, phone = ?,
                    email = ?, website = ?, principal_name = ?, principal_nip = ?,
                    logo = COALESCE(?, logo)
                    WHERE id = ?";
            $params = array(
                $data['name'], $data['npsn'], $data['address'], $data['phone'],
                $data['email'], $data['website'], $data['principal_name'], $data['principal_nip'],
                $data['logo'], $school['id']
            );

            $logAction = 'update';
            $logDesc = 'Update profil sekolah';
        } else {
            // Insert
            $sql = "INSERT INTO school_profile (name, npsn, address, phone, email, website, principal_name, principal_nip, logo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = array(
                $data['name'], $data['npsn'], $data['address'], $data['phone'],
                $data['email'], $data['website'], $data['principal_name'], $data['principal_nip'],
                $data['logo']
            );

            $logAction = 'create';
            $logDesc = 'Tambah profil sekolah baru';
        }

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            logActivity($logAction, 'school_profile', $logDesc);
            redirect('school_profile.php', 'Profil sekolah berhasil disimpan', 'success');
        } else {
            $errors[] = 'Terjadi kesalahan saat menyimpan data';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Profil Sekolah</h1>
    <?php if ($school): ?>
        <div class="page-header-actions">
            <a href="?action=delete&token=<?= htmlspecialchars(generateCSRFToken()) ?>"
               class="btn btn-danger"
               onclick="return confirm('Apakah Anda yakin ingin menghapus profil sekolah?')">
                <i class="fas fa-trash"></i> Hapus Profil
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($school): ?>
    <!-- MODE: EDIT - Data sudah ada -->
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <strong>Mode Edit:</strong> Anda sedang mengedit profil sekolah yang sudah ada.
    </div>
<?php else: ?>
    <!-- MODE: ADD - Belum ada data -->
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Mode Tambah:</strong> Belum ada profil sekolah. Silakan isi data profil sekolah.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if ($school): ?>
            <!-- Tampilkan tombol edit jika data sudah ada -->
            <div style="margin-bottom: 20px; text-align: right;">
                <button type="button" class="btn btn-primary" onclick="showForm()">
                    <i class="fas fa-edit"></i> Edit Profil
                </button>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" id="schoolForm" style="<?php echo $school ? 'display:none;' : ''; ?>">
            <div class="form-group">
                <label for="name" class="form-label form-label-required">Nama Sekolah</label>
                <input type="text" id="name" name="name" class="form-control" required
                       value="<?php echo htmlspecialchars($school['name'] ?? ''); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="npsn">NPSN</label>
                    <input type="text" id="npsn" name="npsn" class="form-control"
                           value="<?php echo htmlspecialchars($school['npsn'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="phone">No. Telepon</label>
                    <input type="text" id="phone" name="phone" class="form-control"
                           value="<?php echo htmlspecialchars($school['phone'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?php echo htmlspecialchars($school['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" class="form-control"
                           value="<?php echo htmlspecialchars($school['website'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="address">Alamat</label>
                <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($school['address'] ?? ''); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="principal_name">Kepala Sekolah</label>
                    <input type="text" id="principal_name" name="principal_name" class="form-control"
                           value="<?php echo htmlspecialchars($school['principal_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="principal_nip">NIP Kepala Sekolah</label>
                    <input type="text" id="principal_nip" name="principal_nip" class="form-control"
                           value="<?php echo htmlspecialchars($school['principal_nip'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="logo">Logo Sekolah</label>
                <input type="file" id="logo" name="logo" class="form-control" accept="image/*">
                <small class="form-text">Format: JPG, PNG. Maksimal 2MB.</small>

                <?php if (!empty($school['logo'])): ?>
                    <div style="margin-top: 10px;">
                        <img src="<?php echo BASE_URL . htmlspecialchars($school['logo']); ?>"
                             style="max-height: 100px;" alt="Logo saat ini">
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <a href="<?php echo BASE_URL; ?>modules/dashboard/index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <?php if ($school): ?>
                <button type="button" class="btn btn-secondary" onclick="hideForm()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($school): ?>
        <!-- Tampilkan data saat ini jika form disembunyikan -->
        <div id="profileDisplay" style="display: none;">
            <h3>Data Profil Sekolah Saat Ini</h3>
            <table class="table table-bordered">
                <tr>
                    <th width="30%">Nama Sekolah</th>
                    <td><?php echo htmlspecialchars($school['name']); ?></td>
                </tr>
                <tr>
                    <th>NPSN</th>
                    <td><?php echo htmlspecialchars($school['npsn'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <th>No. Telepon</th>
                    <td><?php echo htmlspecialchars($school['phone'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?php echo htmlspecialchars($school['email'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <th>Website</th>
                    <td><?php echo htmlspecialchars($school['website'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <th>Alamat</th>
                    <td><?php echo nl2br(htmlspecialchars($school['address'] ?? '-')); ?></td>
                </tr>
                <tr>
                    <th>Kepala Sekolah</th>
                    <td><?php echo htmlspecialchars($school['principal_name'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <th>NIP Kepala Sekolah</th>
                    <td><?php echo htmlspecialchars($school['principal_nip'] ?? '-'); ?></td>
                </tr>
                <?php if (!empty($school['logo'])): ?>
                <tr>
                    <th>Logo</th>
                    <td>
                        <img src="<?php echo BASE_URL . htmlspecialchars($school['logo']); ?>"
                             style="max-height: 80px;" alt="Logo Sekolah">
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showForm() {
    document.getElementById('schoolForm').style.display = 'block';
    document.getElementById('profileDisplay').style.display = 'none';
}

function hideForm() {
    document.getElementById('schoolForm').style.display = 'none';
    document.getElementById('profileDisplay').style.display = 'block';
}
</script>

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
    margin: 0;
}

.page-header-actions {
    display: flex;
    gap: 10px;
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

    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
}

.form-actions {
    display: flex;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

#profileDisplay table {
    margin-top: 20px;
}

#profileDisplay th {
    background-color: #f8f9fa;
    font-weight: 600;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
