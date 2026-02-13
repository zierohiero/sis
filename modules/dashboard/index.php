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
    // Get all statistics
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM teachers");
    $stats['teachers'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE status = 'Aktif'");
    $stats['students'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM classes");
    $stats['classes'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM subjects");
    $stats['subjects'] = $stmt->fetch()['count'];

    // Calculate score progress
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
    // Get homeroom class statistics
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
    // Get teacher statistics
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

// Get recent activities
$recentActivities = [];

// Only show for administrators
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

<div class="page-header">
    <div class="page-greeting">
        <h1><?= getGreeting() ?>, <?= htmlspecialchars($currentUser['name']) ?>!</h1>
        <p class="text-muted">Selamat datang di Sistem Informasi Sekolah</p>
    </div>
</div>

<?php if ($academicYear): ?>
    <div class="alert alert-info">
        <i class="fas fa-calendar-alt"></i>
        Tahun Pelajaran Aktif: <strong><?= htmlspecialchars($academicYear['period']) ?></strong>
        - Semester <strong><?= htmlspecialchars($academicYear['semester']) ?></strong>
    </div>
<?php else: ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        Belum ada tahun pelajaran yang aktif. Silakan atur terlebih dahulu.
    </div>
<?php endif; ?>

<?php if (hasRole(['Administrator'])): ?>
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['teachers'] ?? 0 ?></h3>
                <p>Total Guru</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['students'] ?? 0 ?></h3>
                <p>Total Siswa Aktif</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-door-open"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['classes'] ?? 0 ?></h3>
                <p>Total Kelas</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['subjects'] ?? 0 ?></h3>
                <p>Total Mata Pelajaran</p>
            </div>
        </div>
    </div>

    <!-- Score Progress -->
    <?php if (isset($stats['score_progress'])): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Progres Input Nilai</h3>
            </div>
            <div class="card-body">
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $stats['score_progress'] ?>%">
                            <span class="progress-text"><?= $stats['score_progress'] ?>%</span>
                        </div>
                    </div>
                    <p class="progress-label">Kelengkapan nilai rapor siswa</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Activities -->
    <?php if (!empty($recentActivities)): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Aktivitas Terbaru</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tipe</th>
                                <th>Nama</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $activity): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-primary"><?= htmlspecialchars($activity['type']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($activity['title']) ?></td>
                                    <td><?= formatDate($activity['created_at'], 'd M Y, H:i') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php elseif (hasRole(['Wali Kelas'])): ?>
    <!-- Homeroom Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['students'] ?? 0 ?></h3>
                <p>Siswa Wali Kelas</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-door-open"></i>
            </div>
            <div class="stat-info">
                <h3><?= htmlspecialchars($stats['class_name'] ?? 'N/A') ?></h3>
                <p>Kelas</p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <p class="text-muted">Selamat datang di panel Wali Kelas. Gunakan menu di sebelah kiri untuk mengelola data siswa, nilai, dan presensi.</p>
        </div>
    </div>

<?php elseif (hasRole(['Guru', 'Ustaz'])): ?>
    <!-- Teacher Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-chalkboard"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['classes'] ?? 0 ?></h3>
                <p>Kelas Diampu</p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <p class="text-muted">Selamat datang di panel Guru. Gunakan menu di sebelah kiri untuk mengelola kompetensi dan nilai siswa.</p>
        </div>
    </div>
<?php endif; ?>

<style>
.page-header {
    margin-bottom: 24px;
}

.page-greeting h1 {
    font-size: 24px;
    font-weight: 700;
    color: var(--slate-900);
    margin-bottom: 4px;
    letter-spacing: -0.025em;
}

.text-muted {
    color: var(--slate-500);
    font-size: 14px;
}

.progress-container {
    padding: 16px 0;
}

.progress-bar {
    height: 10px;
    background: var(--slate-100);
    border-radius: 100px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    background: var(--primary-color);
    border-radius: 100px;
    transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}

.progress-text {
    position: absolute;
    right: 0;
    top: -24px;
    color: var(--slate-700);
    font-weight: 600;
    font-size: 13px;
}

.progress-label {
    color: var(--slate-500);
    font-size: 13px;
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
