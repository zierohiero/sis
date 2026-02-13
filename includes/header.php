<?php
/**
 * Header Template
 * Sistem Informasi Sekolah
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/database.php';
}

$currentUser = array(
    'id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL,
    'name' => isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest',
    'role' => isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Guest',
    'username' => isset($_SESSION['username']) ? $_SESSION['username'] : ''
);

$school = getSchoolProfile();
$academicYear = getActiveAcademicYear();

// Get grouped menu items
$menuGroups = getRoleMenuItems($currentUser['role']);

// Determine active page
$currentPath = $_SERVER['PHP_SELF'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?><?php echo $school ? htmlspecialchars($school['name']) : 'Sistem Informasi Sekolah'; ?></title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: {
                        50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc',
                        400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca',
                        800: '#3730a3', 900: '#312e81', 950: '#1e1b4b'
                    }
                },
                fontFamily: {
                    sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                },
            },
        },
    }
    </script>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;450;500;550;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Minimal custom CSS (print, animations only) -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css">
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body class="bg-slate-50 text-slate-800 font-sans text-sm antialiased">
    <?php if (isLoggedIn()): ?>
    <div class="flex min-h-screen">

        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar-base fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white transition-all duration-200 ease-out max-md:-translate-x-full max-md:shadow-xl">

            <!-- Sidebar Header -->
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 shrink-0">
                <?php if ($school && !empty($school['logo'])): ?>
                    <img src="<?php echo BASE_URL . htmlspecialchars($school['logo']); ?>" alt="Logo" class="h-9 w-9 rounded-lg object-contain shrink-0">
                <?php else: ?>
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-white text-base">
                        <i class="fas fa-school"></i>
                    </div>
                <?php endif; ?>
                <div class="min-w-0 sidebar-text">
                    <p class="truncate text-sm font-semibold text-slate-900 leading-tight"><?php echo $school ? htmlspecialchars($school['name']) : 'SIS'; ?></p>
                    <?php if ($academicYear): ?>
                        <p class="truncate text-xs text-slate-500"><?php echo htmlspecialchars($academicYear['period']) . ' - ' . htmlspecialchars($academicYear['semester']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto px-3 py-3 sidebar-scroll">
                <?php foreach ($menuGroups as $group): ?>
                    <?php if (!empty($group['label'])): ?>
                        <p class="sidebar-text mt-4 mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 first:mt-0"><?php echo htmlspecialchars($group['label']); ?></p>
                    <?php endif; ?>
                    <ul class="space-y-0.5">
                        <?php foreach ($group['items'] as $item):
                            $itemUrl = BASE_URL . $item['url'];
                            $isActive = (strpos($currentPath, $item['url']) !== false);
                        ?>
                        <li>
                            <a href="<?php echo $itemUrl; ?>"
                               class="flex items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium transition-colors <?php echo $isActive ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'; ?>"
                               onclick="closeSidebarMobile()">
                                <i class="<?php echo $item['icon']; ?> w-5 text-center text-sm <?php echo $isActive ? 'text-primary-600' : 'text-slate-400'; ?>"></i>
                                <span class="sidebar-text"><?php echo htmlspecialchars($item['text']); ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </nav>

            <!-- Sidebar Footer -->
            <div class="shrink-0 border-t border-slate-100 px-3 py-3">
                <a href="<?php echo BASE_URL; ?>modules/auth/logout.php"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium text-slate-500 transition-colors hover:bg-red-50 hover:text-red-600">
                    <i class="fas fa-sign-out-alt w-5 text-center text-sm"></i>
                    <span class="sidebar-text">Logout</span>
                </a>
            </div>
        </aside>

        <!-- Mobile overlay -->
        <div id="sidebarOverlay" class="fixed inset-0 z-30 hidden bg-slate-900/30 backdrop-blur-sm md:hidden" onclick="closeSidebar()"></div>

        <!-- Main content -->
        <div class="main-area flex flex-1 flex-col md:ml-64 transition-all duration-200 ease-out">

            <!-- Topbar -->
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 md:px-6">

                <!-- Left: Toggle + Title + Search -->
                <div class="flex flex-1 items-center gap-3 min-w-0">
                    <button onclick="toggleSidebar()" aria-label="Toggle sidebar"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700">
                        <i class="fas fa-bars text-lg"></i>
                    </button>

                    <h1 class="hidden text-base font-semibold text-slate-900 truncate sm:block">
                        <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?>
                    </h1>

                    <!-- Search -->
                    <div class="relative ml-auto w-full max-w-xs">
                        <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                        <input type="text" id="globalSearch" placeholder="Cari..." autocomplete="off"
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-10 text-[13px] text-slate-700 placeholder-slate-400 transition-all focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-100">
                        <kbd class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 rounded border border-slate-200 bg-white px-1.5 text-[10px] text-slate-400 leading-5 font-sans">/</kbd>
                    </div>
                </div>

                <!-- Right: User -->
                <div class="shrink-0">
                    <div class="relative" id="userDropdown">
                        <button onclick="toggleUserDropdown()"
                                class="flex items-center gap-2.5 rounded-lg border border-transparent px-2 py-1.5 transition-all hover:border-slate-200 hover:bg-slate-50">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs text-white">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="hidden text-left sm:block">
                                <span class="block text-[13px] font-semibold leading-tight text-slate-800"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                                <span class="block text-[11px] leading-tight text-slate-500"><?php echo htmlspecialchars($currentUser['role']); ?></span>
                            </div>
                            <i class="fas fa-chevron-down hidden text-[10px] text-slate-400 sm:block"></i>
                        </button>

                        <!-- Dropdown -->
                        <div id="userDropdownMenu" class="absolute right-0 top-full z-50 mt-1.5 hidden min-w-[200px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg animate-dropdown">
                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                                <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($currentUser['role']); ?></p>
                            </div>
                            <a href="<?php echo BASE_URL; ?>modules/auth/logout.php"
                               class="flex items-center gap-2.5 px-4 py-2.5 text-[13px] text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900">
                                <i class="fas fa-sign-out-alt w-4 text-center text-xs text-slate-400"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-5 md:p-7">
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <?php $flash = getFlashMessage(); ?>
                    <div class="alert-flash mb-5 flex items-center gap-2.5 rounded-lg border px-4 py-3 text-sm
                        <?php
                        $t = $flash['type'];
                        if ($t === 'success') echo 'border-emerald-200 bg-emerald-50 text-emerald-800';
                        elseif ($t === 'danger') echo 'border-red-200 bg-red-50 text-red-800';
                        elseif ($t === 'warning') echo 'border-amber-200 bg-amber-50 text-amber-800';
                        else echo 'border-sky-200 bg-sky-50 text-sky-800';
                        ?>">
                        <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?> shrink-0"></i>
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>
    <?php endif; ?>
