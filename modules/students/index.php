<?php
/**
 * Students Management
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator', 'Wali Kelas']);

$pageTitle = 'Data Siswa';
$pdo = getDBConnection();
$academicYear = getActiveAcademicYear();

// Pagination
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Search and filter
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build query
$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = "(s.name LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

if (!empty($statusFilter)) {
    $where[] = "s.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM students s WHERE $whereClause");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$pagination = paginate($total, $perPage, $page);

// Get students
$stmt = $pdo->prepare("
    SELECT s.*,
           c.name as class_name,
           se.id as enrollment_id
    FROM students s
    LEFT JOIN student_enrollments se ON s.id = se.student_id
    LEFT JOIN homeroom_teachers ht ON se.homeroom_teacher_id = ht.id
    LEFT JOIN classes c ON ht.class_id = c.id
    LEFT JOIN academic_years ay ON ht.academic_year_id = ay.id
    WHERE $whereClause
    ORDER BY s.name ASC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$students = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Data Siswa</h1>
    <?php if (hasRole(['Administrator'])): ?>
    <a href="form.php" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-700">
        <i class="fas fa-plus text-xs"></i> Tambah Siswa
    </a>
    <?php endif; ?>
</div>

<!-- Card -->
<div class="rounded-xl border border-slate-200 bg-white">

    <!-- Filter bar -->
    <div class="border-b border-slate-100 px-5 py-4">
        <form method="GET" action="" class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" placeholder="Cari nama, NIS, NISN..."
                       value="<?= htmlspecialchars($search) ?>"
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-700 placeholder-slate-400 transition-all focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-100">
            </div>
            <div class="min-w-[180px]">
                <select name="status"
                        class="w-full appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2 pr-9 text-sm text-slate-700 transition-all focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-100">
                    <option value="">Semua Status</option>
                    <option value="Aktif" <?= $statusFilter === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="Tidak Aktif" <?= $statusFilter === 'Tidak Aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                    <option value="Lulus" <?= $statusFilter === 'Lulus' ? 'selected' : '' ?>>Lulus</option>
                    <option value="Keluar" <?= $statusFilter === 'Keluar' ? 'selected' : '' ?>>Keluar</option>
                </select>
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-slate-700">
                <i class="fas fa-filter text-xs"></i> Filter
            </button>
            <?php if (!empty($search) || !empty($statusFilter)): ?>
            <a href="index.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50">
                <i class="fas fa-times text-xs"></i> Reset
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50">
                    <th class="w-12 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">No</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">NIS/NISN</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Siswa</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">L/P</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kelas</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <?php if (hasRole(['Administrator'])): ?>
                    <th class="w-36 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($students)): ?>
                <tr>
                    <td colspan="<?= hasRole(['Administrator']) ? '7' : '6' ?>" class="px-5 py-16 text-center">
                        <div class="text-slate-400">
                            <i class="fas fa-user-graduate text-4xl"></i>
                            <p class="mt-3 text-base font-semibold text-slate-700">Tidak ada data siswa</p>
                            <p class="mt-1 text-sm text-slate-500">
                                <?php if (!empty($search)): ?>
                                    Data tidak ditemukan. Coba kata kunci lain.
                                <?php else: ?>
                                    Belum ada data siswa. Klik tombol Tambah Siswa untuk menambah data.
                                <?php endif; ?>
                            </p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php $no = $pagination['offset'] + 1; ?>
                    <?php foreach ($students as $student): ?>
                    <tr class="transition-colors hover:bg-slate-50">
                        <td class="px-5 py-3 text-slate-500"><?= $no++ ?></td>
                        <td class="px-5 py-3">
                            <span class="font-semibold text-slate-800"><?= htmlspecialchars($student['nis'] ?? '-') ?></span>
                            <br>
                            <span class="text-xs text-slate-500"><?= htmlspecialchars($student['nisn'] ?? '-') ?></span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <?php if (!empty($student['photo'])): ?>
                                    <img src="<?= BASE_URL . htmlspecialchars($student['photo']) ?>"
                                         alt="" class="h-8 w-8 shrink-0 rounded-full object-cover">
                                <?php else: ?>
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs text-slate-400">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-slate-800"><?= htmlspecialchars($student['name']) ?></p>
                                    <p class="truncate text-xs text-slate-500">
                                        <?= htmlspecialchars($student['birth_place'] ?? '-') ?>, <?= formatDate($student['birth_date']) ?>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-slate-600"><?= htmlspecialchars($student['gender']) ?></td>
                        <td class="px-5 py-3">
                            <?php if (!empty($student['class_name'])): ?>
                                <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700"><?= htmlspecialchars($student['class_name']) ?></span>
                            <?php else: ?>
                                <span class="text-slate-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3">
                            <?php
                            $statusMap = [
                                'Aktif'       => 'bg-emerald-50 text-emerald-700',
                                'Tidak Aktif' => 'bg-amber-50 text-amber-700',
                                'Lulus'       => 'bg-primary-50 text-primary-700',
                                'Keluar'      => 'bg-red-50 text-red-700',
                            ];
                            $cls = $statusMap[$student['status']] ?? 'bg-slate-100 text-slate-600';
                            ?>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= $cls ?>">
                                <?= htmlspecialchars($student['status']) ?>
                            </span>
                        </td>
                        <?php if (hasRole(['Administrator'])): ?>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-1.5">
                                <a href="view.php?id=<?= $student['id'] ?>" title="Lihat"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700">
                                    <i class="fas fa-eye text-xs"></i>
                                </a>
                                <a href="form.php?id=<?= $student['id'] ?>" title="Edit"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700">
                                    <i class="fas fa-edit text-xs"></i>
                                </a>
                                <button title="Hapus"
                                        data-delete-url="delete.php?id=<?= $student['id'] ?>"
                                        data-delete-confirm="Hapus data siswa &quot;<?= htmlspecialchars($student['name']) ?>&quot;?"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 text-red-500 transition-colors hover:bg-red-50 hover:text-red-700">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="border-t border-slate-100 px-5 py-4">
        <?= buildPagination($pagination, '?page=%d' . (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '')) ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
