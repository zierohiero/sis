<?php
/**
 * Report Card (Cetak Rapor)
 * Sistem Informasi Sekolah
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Administrator', 'Wali Kelas']);

$pageTitle = 'Cetak Rapor';
$pdo = getDBConnection();
$academicYear = getActiveAcademicYear();

if (!$academicYear) {
    redirect(BASE_URL . 'modules/dashboard/index.php', 'Silakan atur tahun pelajaran aktif terlebih dahulu', 'warning');
}

// Get filters
$classFilter = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$studentFilter = isset($_GET['student']) ? (int)$_GET['student'] : 0;

// Get classes
$classes = [];
$stmt = $pdo->query("
    SELECT DISTINCT c.*
    FROM classes c
    JOIN homeroom_teachers ht ON c.id = ht.class_id
    WHERE ht.academic_year_id = {$academicYear['id']}
    ORDER BY c.level, c.name
");
$classes = $stmt->fetchAll();

// Get students when class is selected
$students = [];
if ($classFilter) {
    $stmt = $pdo->prepare("
        SELECT s.*, se.id as enrollment_id
        FROM students s
        JOIN student_enrollments se ON s.id = se.student_id
        JOIN homeroom_teachers ht ON se.homeroom_teacher_id = ht.id
        WHERE ht.class_id = ? AND ht.academic_year_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$classFilter, $academicYear['id']]);
    $students = $stmt->fetchAll();
}

// Get report data
$reportData = null;
if ($studentFilter && $classFilter) {
    // Get student info
    $stmt = $pdo->prepare("
        SELECT
            s.*,
            se.id as enrollment_id,
            c.name as class_name,
            c.level as class_level,
            ht.id as homeroom_id,
            t.name as homeroom_teacher_name,
            t.nip as homeroom_teacher_nip
        FROM students s
        JOIN student_enrollments se ON s.id = se.student_id
        JOIN homeroom_teachers ht ON se.homeroom_teacher_id = ht.id
        JOIN classes c ON ht.class_id = c.id
        JOIN teachers t ON ht.teacher_id = t.id
        WHERE s.id = ? AND ht.class_id = ? AND ht.academic_year_id = ?
    ");
    $stmt->execute([$studentFilter, $classFilter, $academicYear['id']]);
    $reportData = $stmt->fetch();

    if ($reportData) {
        // Get subjects and scores
        $stmt = $pdo->prepare("
            SELECT
                sub.id as subject_id,
                sub.code,
                sub.name as subject_name,
                sub.groups,
                sub.is_diniah,
                sa.id as allocation_id,
                ss_sts.score as score_sts,
                ss_sas.score as score_sas,
                fc.avg_score as formative_avg,
                (COALESCE(ss_sas.score, 0) + COALESCE(ss_sts.score, 0) + COALESCE(fc.avg_score, 0)) / 3 as final_score
            FROM subject_allocations sa
            JOIN subjects sub ON sa.subject_id = sub.id
            JOIN homeroom_teachers ht ON sa.homeroom_teacher_id = ht.id
            LEFT JOIN summative_scores ss_sts ON ss_sts.allocation_id = sa.id AND ss_sts.enrollment_id = ? AND ss_sts.type = 'STS'
            LEFT JOIN summative_scores ss_sas ON ss_sas.allocation_id = sa.id AND ss_sas.enrollment_id = ? AND ss_sas.type = 'SAS'
            LEFT JOIN (
                SELECT fs.competency_id, AVG(fs.score) as avg_score, c.subject_id
                FROM formative_scores fs
                JOIN competencies c ON fs.competency_id = c.id
                WHERE fs.enrollment_id = ?
                GROUP BY c.subject_id
            ) fc ON fc.subject_id = sub.id
            WHERE ht.id = ?
            ORDER BY sub.groups, sub.name
        ");
        $stmt->execute([
            $reportData['enrollment_id'],
            $reportData['enrollment_id'],
            $reportData['enrollment_id'],
            $reportData['homeroom_id']
        ]);
        $reportData['subjects'] = $stmt->fetchAll();

        // Get extracurricular
        $stmt = $pdo->prepare("
            SELECT
                ea.name as activity_name,
                ep.predicate,
                ep.notes
            FROM extracurricular_predicates ep
            JOIN extracurricular_activities ea ON ep.activity_id = ea.id
            WHERE ep.enrollment_id = ?
            ORDER BY ea.name
        ");
        $stmt->execute([$reportData['enrollment_id']]);
        $reportData['extracurricular'] = $stmt->fetchAll();

        // Get attendance
        $stmt = $pdo->prepare("
            SELECT * FROM students_attendances WHERE enrollment_id = ?
        ");
        $stmt->execute([$reportData['enrollment_id']]);
        $reportData['attendance'] = $stmt->fetch();

        // Check score completeness
        $totalSubjects = count(array_filter($reportData['subjects'], fn($s) => !$s['is_diniah']));
        $completedSubjects = count(array_filter($reportData['subjects'], fn($s) => !$s['is_diniah'] && !empty($s['score_sas'])));
        $reportData['is_complete'] = ($totalSubjects > 0 && $totalSubjects === $completedSubjects);

        // Get school profile
        $reportData['school'] = getSchoolProfile();
    }
}

$school = getSchoolProfile();
include __DIR__ . '/../../includes/header.php';
?>

<style>
.report-filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.report-preview {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 40px;
    max-width: 800px;
    margin: 0 auto;
}

.report-preview @media {
    .report-preview {
        box-shadow: none;
        border: none;
    }
}

.report-header {
    text-align: center;
    margin-bottom: 30px;
}

.report-logo {
    width: 100px;
    height: 100px;
    object-fit: contain;
    margin-bottom: 10px;
}

.report-school-name {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 5px;
}

.report-title {
    font-size: 14px;
    color: var(--gray-color);
    margin-bottom: 20px;
}

.report-section {
    margin-bottom: 25px;
}

.report-section-title {
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 2px solid var(--primary-color);
}

.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.report-table th,
.report-table td {
    border: 1px solid #333;
    padding: 8px;
    text-align: center;
}

.report-table th {
    background: #f0f0f0;
    font-weight: 600;
}

.report-table td.text-left {
    text-align: left;
}

.report-info {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    font-size: 13px;
}

.report-info-row {
    display: flex;
    gap: 5px;
}

.report-info-label {
    font-weight: 600;
    min-width: 100px;
}

.report-footer {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 40px;
}

.report-signature {
    text-align: center;
}

.report-signature-title {
    margin-bottom: 60px;
    font-size: 13px;
}

.report-signature-name {
    font-weight: 600;
    font-size: 13px;
}

.report-signature-nip {
    font-size: 11px;
    color: var(--gray-color);
}

.completeness-badge {
    padding: 15px;
    border-radius: var(--radius-md);
    text-align: center;
    margin-bottom: 20px;
}

.completeness-badge.complete {
    background: #c6f6d5;
    color: #22543d;
}

.completeness-badge.incomplete {
    background: #feebc8;
    color: #7c2d12;
}
</style>

<div class="page-header">
    <h1>Cetak Rapor</h1>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="" class="report-filters">
            <div class="form-group">
                <label for="class">Pilih Kelas</label>
                <select id="class" name="class" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['id'] ?>" <?= $classFilter == $class['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="student">Pilih Siswa</label>
                <select id="student" name="student" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Pilih Siswa --</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= $student['id'] ?>" <?= $studentFilter == $student['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($reportData): ?>
        <div class="card-body">
            <?php if (!$reportData['is_complete']): ?>
                <div class="completeness-badge incomplete">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Peringatan:</strong> Nilai siswa belum lengkap.
                    Selesaikan input nilai sebelum mencetak rapor.
                </div>
            <?php endif; ?>

            <div class="report-preview" id="reportContent">
                <!-- Header -->
                <div class="report-header">
                    <?php if (!empty($reportData['school']['logo'])): ?>
                        <img src="<?= BASE_URL . htmlspecialchars($reportData['school']['logo']) ?>" class="report-logo" alt="Logo">
                    <?php endif; ?>
                    <h2 class="report-school-name"><?= htmlspecialchars($reportData['school']['name']) ?></h2>
                    <p class="report-title">LAPORAN HASIL BELAJAR SISWA</p>
                </div>

                <!-- Student Info -->
                <div class="report-section">
                    <div class="report-info">
                        <div class="report-info-row">
                            <span class="report-info-label">Nama:</span>
                            <span><?= htmlspecialchars($reportData['name']) ?></span>
                        </div>
                        <div class="report-info-row">
                            <span class="report-info-label">NIS/NISN:</span>
                            <span><?= htmlspecialchars($reportData['nis'] ?? '-') ?> / <?= htmlspecialchars($reportData['nisn'] ?? '-') ?></span>
                        </div>
                        <div class="report-info-row">
                            <span class="report-info-label">Kelas:</span>
                            <span><?= htmlspecialchars($reportData['class_name']) ?></span>
                        </div>
                        <div class="report-info-row">
                            <span class="report-info-label">Semester:</span>
                            <span><?= htmlspecialchars($academicYear['semester']) ?> - <?= htmlspecialchars($academicYear['period']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Regular Subjects -->
                <div class="report-section">
                    <h3 class="report-section-title">Nilai Mata Pelajaran</h3>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Mata Pelajaran</th>
                                <th>STS</th>
                                <th>SAS</th>
                                <th>Nilai Akhir</th>
                                <th>Predikat</th>
                                <th>Deskripsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $regularSubjects = array_filter($reportData['subjects'], fn($s) => !$s['is_diniah']);
                            foreach ($regularSubjects as $subject):
                                $finalScore = round($subject['final_score'], 1);
                                $predicate = getGradePredicate($finalScore);
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td class="text-left"><?= htmlspecialchars($subject['subject_name']) ?></td>
                                    <td><?= $subject['score_sts'] ?: '-' ?></td>
                                    <td><?= $subject['score_sas'] ?: '-' ?></td>
                                    <td><?= $finalScore ?></td>
                                    <td><?= $predicate['predicate'] ?></td>
                                    <td class="text-left"><?= $predicate['description'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Diniah Subjects -->
                <?php
                $diniahSubjects = array_filter($reportData['subjects'], fn($s) => $s['is_diniah']);
                if (!empty($diniahSubjects)): ?>
                    <div class="report-section">
                        <h3 class="report-section-title">Nilai Mata Pelajaran Diniah</h3>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Mata Pelajaran</th>
                                    <th>STS</th>
                                    <th>SAS</th>
                                    <th>Nilai Akhir</th>
                                    <th>Predikat</th>
                                    <th>Deskripsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                foreach ($diniahSubjects as $subject):
                                    $finalScore = round($subject['final_score'], 1);
                                    $predicate = getGradePredicate($finalScore);
                                    ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td class="text-left"><?= htmlspecialchars($subject['subject_name']) ?></td>
                                        <td><?= $subject['score_sts'] ?: '-' ?></td>
                                        <td><?= $subject['score_sas'] ?: '-' ?></td>
                                        <td><?= $finalScore ?></td>
                                        <td><?= $predicate['predicate'] ?></td>
                                        <td class="text-left"><?= $predicate['description'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Extracurricular -->
                <?php if (!empty($reportData['extracurricular'])): ?>
                    <div class="report-section">
                        <h3 class="report-section-title">Ekstrakurikuler</h3>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kegiatan</th>
                                    <th>Predikat</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; ?>
                                <?php foreach ($reportData['extracurricular'] as $extra): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td class="text-left"><?= htmlspecialchars($extra['activity_name']) ?></td>
                                        <td><?= htmlspecialchars($extra['predicate']) ?></td>
                                        <td class="text-left"><?= htmlspecialchars($extra['notes'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Attendance -->
                <div class="report-section">
                    <h3 class="report-section-title">Kehadiran</h3>
                    <table class="report-table">
                        <tr>
                            <td width="150">Sakit</td>
                            <td><?= $reportData['attendance']['sick'] ?? 0 ?> hari</td>
                        </tr>
                        <tr>
                            <td>Izin</td>
                            <td><?= $reportData['attendance']['permit'] ?? 0 ?> hari</td>
                        </tr>
                        <tr>
                            <td>Tanpa Keterangan</td>
                            <td><?= $reportData['attendance']['alpha'] ?? 0 ?> hari</td>
                        </tr>
                    </table>
                </div>

                <!-- Footer Signatures -->
                <div class="report-footer">
                    <div class="report-signature">
                        <p class="report-signature-title">Mengetahui,<br>Orang Tua/Wali</p>
                        <p class="report-signature-name"></p>
                        <p class="report-signature-nip">(................................)</p>
                    </div>
                    <div class="report-signature">
                        <p class="report-signature-title">Mengetahui,<br>Kepala Sekolah</p>
                        <p class="report-signature-name"><?= htmlspecialchars($reportData['school']['principal_name']) ?></p>
                        <p class="report-signature-nip">NIP. <?= htmlspecialchars($reportData['school']['principal_nip'] ?? '-') ?></p>
                    </div>
                    <div class="report-signature">
                        <p class="report-signature-title">
                            <?= formatDate(date('Y-m-d'), 'd M Y') ?><br>
                            Wali Kelas
                        </p>
                        <p class="report-signature-name"><?= htmlspecialchars($reportData['homeroom_teacher_name']) ?></p>
                        <p class="report-signature-nip">NIP. <?= htmlspecialchars($reportData['homeroom_teacher_nip'] ?? '-') ?></p>
                    </div>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 30px;">
                <?php if ($reportData['is_complete']): ?>
                    <button onclick="printReport()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Cetak Rapor
                    </button>
                <?php endif; ?>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-file-alt empty-state-icon"></i>
                <p class="empty-state-title">Pilih siswa untuk mencetak rapor</p>
                <p class="empty-state-text">Pilih kelas dan siswa di atas untuk melihat dan mencetak rapor.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function printReport() {
    const content = document.getElementById('reportContent').innerHTML;
    const printWindow = window.open('', '_blank');

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Cetak Rapor - <?= htmlspecialchars($reportData['name'] ?? '') ?></title>
            <style>
                @page {
                    size: F4;
                    margin: 15mm;
                }
                body {
                    font-family: 'Times New Roman', serif;
                    font-size: 12pt;
                    color: #000;
                }
                .report-header { text-align: center; margin-bottom: 20px; }
                .report-logo { width: 80px; height: 80px; object-fit: contain; }
                .report-school-name { font-size: 16pt; font-weight: bold; margin: 10px 0; }
                .report-title { font-size: 12pt; }
                .report-section { margin-bottom: 20px; }
                .report-section-title {
                    font-size: 12pt;
                    font-weight: bold;
                    margin-bottom: 10px;
                    border-bottom: 2px solid #000;
                    padding-bottom: 5px;
                }
                .report-table { width: 100%; border-collapse: collapse; font-size: 11pt; }
                .report-table th, .report-table td {
                    border: 1px solid #000;
                    padding: 6px 8px;
                    text-align: center;
                }
                .report-table th { background: #f0f0f0; font-weight: bold; }
                .report-table td.text-left { text-align: left; }
                .report-info { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; font-size: 11pt; margin-bottom: 15px; }
                .report-info-row { display: flex; gap: 5px; }
                .report-info-label { font-weight: bold; min-width: 100px; }
                .report-footer {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 20px;
                    margin-top: 50px;
                }
                .report-signature { text-align: center; }
                .report-signature-title { font-size: 11pt; margin-bottom: 50px; }
                .report-signature-name { font-weight: bold; }
                .report-signature-nip { font-size: 10pt; }
            </style>
        </head>
        <body>${content}</body>
        </html>
    `);

    printWindow.document.close();
    printWindow.print();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
