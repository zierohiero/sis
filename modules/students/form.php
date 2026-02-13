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

    $errors = [];

    if (empty($data['name'])) {
        $errors[] = 'Nama siswa wajib diisi';
    }

    if (empty($data['gender']) || !in_array($data['gender'], ['L', 'P'])) {
        $errors[] = 'Jenis kelamin tidak valid';
    }

    $stmt = $pdo->prepare("SELECT id FROM students WHERE nis = ? AND id != ?");
    $stmt->execute([$data['nis'], $id]);
    if ($stmt->fetch()) {
        $errors[] = 'NIS sudah terdaftar';
    }

    if (!empty($data['nisn'])) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE nisn = ? AND id != ?");
        $stmt->execute([$data['nisn'], $id]);
        if ($stmt->fetch()) {
            $errors[] = 'NISN sudah terdaftar';
        }
    }

    $photoPath = $student['photo'] ?? null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFile($_FILES['photo'], STUDENT_PHOTO_PATH);
        if ($uploadResult['success']) {
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

<!-- Page Header -->
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900"><?= htmlspecialchars($pageTitle) ?></h1>
    <a href="index.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50">
        <i class="fas fa-arrow-left text-xs"></i> Kembali
    </a>
</div>

<!-- Errors -->
<?php if (!empty($errors)): ?>
<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
    <div class="flex items-start gap-2.5">
        <i class="fas fa-exclamation-circle mt-0.5 shrink-0 text-red-500"></i>
        <ul class="list-disc space-y-0.5 pl-4">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Form -->
<div class="rounded-xl border border-slate-200 bg-white">
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="divide-y divide-slate-100">

            <!-- Section: Personal Info -->
            <div class="px-6 py-5">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Informasi Pribadi</h2>
                <div class="grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2">
                    <div>
                        <label for="nis" class="mb-1.5 block text-sm font-medium text-slate-700">NIS <span class="text-red-500">*</span></label>
                        <input type="text" id="nis" name="nis" required value="<?= htmlspecialchars($student['nis'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div>
                        <label for="nisn" class="mb-1.5 block text-sm font-medium text-slate-700">NISN</label>
                        <input type="text" id="nisn" name="nisn" value="<?= htmlspecialchars($student['nisn'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div class="md:col-span-2">
                        <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name" required value="<?= htmlspecialchars($student['name'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div>
                        <label for="gender" class="mb-1.5 block text-sm font-medium text-slate-700">Jenis Kelamin <span class="text-red-500">*</span></label>
                        <select id="gender" name="gender" required
                                class="w-full appearance-none rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                            <option value="">-- Pilih --</option>
                            <option value="L" <?= (isset($student['gender']) && $student['gender'] === 'L') ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= (isset($student['gender']) && $student['gender'] === 'P') ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label for="birth_place" class="mb-1.5 block text-sm font-medium text-slate-700">Tempat Lahir</label>
                        <input type="text" id="birth_place" name="birth_place" value="<?= htmlspecialchars($student['birth_place'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div>
                        <label for="birth_date" class="mb-1.5 block text-sm font-medium text-slate-700">Tanggal Lahir</label>
                        <input type="date" id="birth_date" name="birth_date" value="<?= htmlspecialchars($student['birth_date'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                    </div>
                </div>
            </div>

            <!-- Section: Parents -->
            <div class="px-6 py-5">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Informasi Orang Tua</h2>
                <div class="grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2">
                    <div>
                        <label for="father_name" class="mb-1.5 block text-sm font-medium text-slate-700">Nama Ayah</label>
                        <input type="text" id="father_name" name="father_name" value="<?= htmlspecialchars($student['father_name'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div>
                        <label for="mother_name" class="mb-1.5 block text-sm font-medium text-slate-700">Nama Ibu</label>
                        <input type="text" id="mother_name" name="mother_name" value="<?= htmlspecialchars($student['mother_name'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div class="md:col-span-2">
                        <label for="address" class="mb-1.5 block text-sm font-medium text-slate-700">Alamat</label>
                        <textarea id="address" name="address" rows="3"
                                  class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100"><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label for="phone" class="mb-1.5 block text-sm font-medium text-slate-700">No. Telepon</label>
                        <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($student['phone'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div>
                        <label for="status" class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                        <select id="status" name="status"
                                class="w-full appearance-none rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                            <option value="Aktif" <?= (isset($student['status']) && $student['status'] === 'Aktif') ? 'selected' : '' ?>>Aktif</option>
                            <option value="Tidak Aktif" <?= (isset($student['status']) && $student['status'] === 'Tidak Aktif') ? 'selected' : '' ?>>Tidak Aktif</option>
                            <option value="Lulus" <?= (isset($student['status']) && $student['status'] === 'Lulus') ? 'selected' : '' ?>>Lulus</option>
                            <option value="Keluar" <?= (isset($student['status']) && $student['status'] === 'Keluar') ? 'selected' : '' ?>>Keluar</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section: Photo -->
            <div class="px-6 py-5">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Foto</h2>
                <div>
                    <label for="photo" class="mb-1.5 block text-sm font-medium text-slate-700">Upload Foto</label>
                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/jpg"
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-xs file:font-medium file:text-primary-700">
                    <p class="mt-1.5 text-xs text-slate-500">Format: JPG, PNG. Maksimal 2MB.</p>
                    <?php if (!empty($student['photo'])): ?>
                    <div class="mt-4">
                        <img src="<?= BASE_URL . htmlspecialchars($student['photo']) ?>" alt="Foto Siswa"
                             class="h-36 w-28 rounded-lg border border-slate-200 object-cover">
                        <p class="mt-1.5 text-xs text-slate-500">Foto saat ini</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 rounded-b-xl">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-700">
                <i class="fas fa-save text-xs"></i> Simpan
            </button>
            <a href="index.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50">
                Batal
            </a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
