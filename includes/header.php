<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>Air Cargo & Intermodal Advisory Portal (Kelompok 1)</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        darkbg: '#0b1329',
                        darkcard: '#111e38',
                        darkborder: '#1e293b'
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    }
                }
            }
        }
    </script>
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-[#090f20] text-slate-100 min-h-screen flex flex-col font-sans selection:bg-sky-500 selection:text-white">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-50 glass-panel border-b border-slate-800 shadow-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <!-- Logo & Brand -->
                <div class="flex items-center space-x-3">
                    <a href="index.php" class="flex items-center space-x-3 group">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-600 via-indigo-600 to-sky-400 flex items-center justify-center shadow-lg shadow-sky-500/20 group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-plane-departure text-white text-lg"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="font-extrabold text-lg tracking-tight bg-gradient-to-r from-sky-400 via-indigo-300 to-white bg-clip-text text-transparent">AeroIntermodal</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-sky-950/80 border border-sky-500/30 text-sky-400 font-semibold uppercase tracking-wider">Advisory</span>
                            </div>
                            <p class="text-[11px] text-slate-400 font-medium">Air Cargo & Intermodal Terminal Solutions</p>
                        </div>
                    </a>
                </div>

                <!-- Nav Menu Links -->
                <nav class="hidden md:flex items-center space-x-1">
                    <a href="index.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-all <?= ($currentPage === 'index.php') ? 'bg-sky-600/20 text-sky-400 border border-sky-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-compass mr-1.5 text-xs"></i> Beranda Konsultansi
                    </a>
                    <a href="dashboard.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-all <?= ($currentPage === 'dashboard.php') ? 'bg-sky-600/20 text-sky-400 border border-sky-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-chart-pie mr-1.5 text-xs"></i> Dashboard
                    </a>
                    <a href="simulation.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-all <?= ($currentPage === 'simulation.php') ? 'bg-sky-600/20 text-sky-400 border border-sky-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-microchip mr-1.5 text-xs"></i> Simulasi 7-Tahap PoC
                    </a>
                    <a href="database_viewer.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-all <?= ($currentPage === 'database_viewer.php') ? 'bg-sky-600/20 text-sky-400 border border-sky-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-database mr-1.5 text-xs"></i> Database Viewer
                    </a>
                    <a href="blueprint.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-all <?= ($currentPage === 'blueprint.php') ? 'bg-sky-600/20 text-sky-400 border border-sky-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-book-open mr-1.5 text-xs"></i> Blueprint SRS
                    </a>
                </nav>

                <!-- User & Action Controls -->
                <div class="flex items-center space-x-3">
                    <?php if ($user): ?>
                        <div class="hidden lg:flex items-center space-x-2 text-xs bg-slate-800/80 px-3 py-1.5 rounded-lg border border-slate-700">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span class="text-slate-400">Aktif:</span>
                            <span class="font-semibold text-slate-200"><?= htmlspecialchars($user['full_name']) ?></span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-sky-900/60 text-sky-300 border border-sky-700/50 uppercase"><?= htmlspecialchars($user['role']) ?></span>
                        </div>
                        <button onclick="confirmResetData()" title="Reset data simulasi ke awal demo" class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-rose-900/50 hover:text-rose-300 text-slate-400 border border-slate-700 text-xs font-medium transition-colors">
                            <i class="fa-solid fa-rotate-left mr-1"></i> Reset Demo
                        </button>
                        <a href="logout.php" title="Keluar" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-slate-700 transition-colors">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="px-4 py-2 rounded-lg bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white text-xs font-semibold shadow-lg shadow-sky-500/25 transition-all">
                            <i class="fa-solid fa-arrow-right-to-bracket mr-1.5"></i> Masuk Portal
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </header>

    <!-- Main Content Wrapper -->
    <main class="flex-grow">
