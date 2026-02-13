<?php
/**
 * Dashboard Page
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

$pageTitle = 'Dashboard';
$currentUser = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'role' => $_SESSION['user_role'] ?? ''
];

$pdo = getDBConnection();
$academicYear = getActiveAcademicYear();

// Get statistics based on role
$stats = [];

if (hasRole(['Administrator'])) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM teachers");
    $stats['teachers'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE status = 'Aktif'");
    $stats['students'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM classes");
    $stats['classes'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM subjects");
    $stats['subjects'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("
        SELECT
            COUNT(DISTINCT ss.enrollment_id) as total_students,
            COUNT(DISTINCT CASE WHEN ss.type = 'SAS' THEN ss.allocation_id END) as completed_subjects
        FROM student_enrollments se
        LEFT JOIN summative_scores ss ON se.id = ss.enrollment_id
    ");
    $scoreStats = $stmt->fetch();
    $stats['score_progress'] = $scoreStats['completed_subjects'] > 0
        ? round(($scoreStats['completed_subjects'] / ($scoreStats['total_students'] * 10)) * 100)
        : 0;

} elseif (hasRole(['Wali Kelas'])) {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT se.student_id) as students,
            c.name as class_name
        FROM homeroom_teachers ht
        JOIN teachers t ON ht.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        JOIN classes c ON ht.class_id = c.id
        LEFT JOIN student_enrollments se ON ht.id = se.homeroom_teacher_id
        WHERE u.id = ?
        GROUP BY ht.id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $homeroom = $stmt->fetch();
    $stats['students'] = $homeroom['students'] ?? 0;
    $stats['class_name'] = $homeroom['class_name'] ?? 'N/A';

} elseif (hasRole(['Guru', 'Ustaz'])) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT sa.homeroom_teacher_id) as classes
        FROM subject_allocations sa
        JOIN teachers t ON sa.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['classes'] = $stmt->fetch()['classes'] ?? 0;
}

// Get recent activities (admin only)
$recentActivities = [];
if (hasRole(['Administrator'])) {
    $stmt = $pdo->query("
        (SELECT 'Siswa' as type, name as title, created_at, 'students' as url
         FROM students ORDER BY created_at DESC LIMIT 3)
        UNION ALL
        (SELECT 'Guru' as type, name as title, created_at, 'teachers' as url
         FROM teachers ORDER BY created_at DESC LIMIT 3)
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentActivities = $stmt->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
?>

<!-- Page header -->
<div class="mb-6">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900"><?= getGreeting() ?>, <?= htmlspecialchars($currentUser['name']) ?>!</h1>
    <p class="mt-1 text-sm text-slate-500">Selamat datang di Sistem Informasi Sekolah</p>
</div>

<!-- Academic year banner -->
<?php if ($academicYear): ?>
    <div class="mb-5 flex items-center gap-2.5 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
        <i class="fas fa-calendar-alt shrink-0"></i>
        Tahun Pelajaran Aktif: <strong><?= htmlspecialchars($academicYear['period']) ?></strong>
        - Semester <strong><?= htmlspecialchars($academicYear['semester']) ?></strong>
    </div>
<?php else: ?>
    <div class="mb-5 flex items-center gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <i class="fas fa-exclamation-triangle shrink-0"></i>
        Belum ada tahun pelajaran yang aktif. Silakan atur terlebih dahulu.
    </div>
<?php endif; ?>

<?php if (hasRole(['Administrator'])): ?>

    <!-- Stat cards -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php
        $statItems = [
            ['label' => 'Total Guru',           'value' => $stats['teachers'] ?? 0, 'icon' => 'fas fa-chalkboard-teacher', 'color' => 'bg-primary-50 text-primary-600'],
            ['label' => 'Total Siswa Aktif',     'value' => $stats['students'] ?? 0, 'icon' => 'fas fa-user-graduate',      'color' => 'bg-emerald-50 text-emerald-600'],
            ['label' => 'Total Kelas',           'value' => $stats['classes'] ?? 0,  'icon' => 'fas fa-door-open',           'color' => 'bg-amber-50 text-amber-600'],
            ['label' => 'Total Mata Pelajaran',  'value' => $stats['subjects'] ?? 0, 'icon' => 'fas fa-book',                'color' => 'bg-red-50 text-red-600'],
        ];
        foreach ($statItems as $s): ?>
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 transition-shadow hover:shadow-md">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg <?= $s['color'] ?>">
                <i class="<?= $s['icon'] ?> text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold tracking-tight text-slate-900"><?= $s['value'] ?></p>
                <p class="text-[13px] text-slate-500"><?= $s['label'] ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Score progress -->
    <?php if (isset($stats['score_progress'])): ?>
    <div class="mb-6 rounded-xl border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="text-[15px] font-semibold text-slate-900">Progres Input Nilai</h2>
            <span class="text-sm font-semibold text-primary-600"><?= $stats['score_progress'] ?>%</span>
        </div>
        <div class="px-5 py-5">
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-primary-600 transition-all duration-700" style="width: <?= $stats['score_progress'] ?>%"></div>
            </div>
            <p class="mt-2.5 text-[13px] text-slate-500">Kelengkapan nilai rapor siswa</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent activities -->
    <?php if (!empty($recentActivities)): ?>
    <div class="rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-[15px] font-semibold text-slate-900">Aktivitas Terbaru</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tipe</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($recentActivities as $activity): ?>
                    <tr class="transition-colors hover:bg-slate-50">
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700"><?= htmlspecialchars($activity['type']) ?></span>
                        </td>
                        <td class="px-5 py-3 font-medium text-slate-800"><?= htmlspecialchars($activity['title']) ?></td>
                        <td class="px-5 py-3 text-slate-500"><?= formatDate($activity['created_at'], 'd M Y, H:i') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

<?php elseif (hasRole(['Wali Kelas'])): ?>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                <i class="fas fa-users text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold tracking-tight text-slate-900"><?= $stats['students'] ?? 0 ?></p>
                <p class="text-[13px] text-slate-500">Siswa Wali Kelas</p>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                <i class="fas fa-door-open text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold tracking-tight text-slate-900"><?= htmlspecialchars($stats['class_name'] ?? 'N/A') ?></p>
                <p class="text-[13px] text-slate-500">Kelas</p>
            </div>
        </div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="text-sm text-slate-500">Selamat datang di panel Wali Kelas. Gunakan menu di sebelah kiri untuk mengelola data siswa, nilai, dan presensi.</p>
    </div>

<?php elseif (hasRole(['Guru', 'Ustaz'])): ?>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                <i class="fas fa-chalkboard text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold tracking-tight text-slate-900"><?= $stats['classes'] ?? 0 ?></p>
                <p class="text-[13px] text-slate-500">Kelas Diampu</p>
            </div>
        </div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="text-sm text-slate-500">Selamat datang di panel Guru. Gunakan menu di sebelah kiri untuk mengelola kompetensi dan nilai siswa.</p>
    </div>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
