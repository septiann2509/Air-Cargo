<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$user = getCurrentUser();

$companyName    = 'PT Aerospace Consultant';
$companyTagline = 'Air Cargo & Intermodal Advisory';
$companyShort   = 'AC';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?><?= $companyName ?> Advisory Portal</title>
    <link rel="shortcut icon" type="image/x-icon" href="https://injourneyairports.id/kawung.ico">

    <!-- Barlow Font — Sama persis dengan InJourney Airports & Landing Page -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,600&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        injourney: {
                            teal: '#0CA1AF',
                            darkteal: '#087F8A',
                            deepcyan: '#014D54',
                            cyanbtn: '#04AFBF',
                            navy: '#0D1C42',
                            dark: '#404042',
                            light: '#F5F5F5'
                        },
                        primary: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            500: '#04AFBF',
                            600: '#087F8A',
                            700: '#014D54',
                            800: '#0D1C42',
                            900: '#081126',
                        },
                        darkbg: '#081126',
                        darkcard: '#0D1C42',
                        darkborder: '#162a52'
                    },
                    fontFamily: {
                        sans: ['"Barlow"', 'sans-serif'],
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
<body class="bg-[#081126] text-slate-100 min-h-screen flex flex-col font-sans selection:bg-[#04AFBF] selection:text-white">

    <!-- Top Navigation Bar (InJourney Deep Navy Concept #0D1C42) -->
    <header class="sticky top-0 z-50 bg-[#0D1C42] border-b border-[#0CA1AF]/25 shadow-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <!-- Logo & Brand (AC — PT Aerospace Consultant) -->
                <div class="flex items-center space-x-3">
                    <a href="index.php" class="flex items-center space-x-3 group">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-[#0CA1AF] to-[#014D54] flex items-center justify-center text-white font-extrabold shadow-lg shadow-teal-500/25 group-hover:scale-105 transition-transform border border-teal-300/30">
                            <span class="text-sm tracking-tight"><?= $companyShort ?></span>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="font-extrabold text-base tracking-tight text-white"><?= $companyName ?></span>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-teal-950 border border-teal-400/40 text-teal-300 font-bold uppercase tracking-wider">Advisory</span>
                            </div>
                            <p class="text-[10.5px] text-teal-200/80 font-medium"><?= $companyTagline ?></p>
                        </div>
                    </a>
                </div>

                <!-- Nav Menu Links (InJourney Style) -->
                <nav class="hidden lg:flex items-center space-x-1 text-sm font-semibold">
                    <a href="index.php" class="px-3 py-2 rounded-lg transition-all <?= ($currentPage === 'index.php') ? 'bg-[#087F8A] text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/10' ?>">
                        <i class="fa-solid fa-compass mr-1.5 text-xs text-teal-300"></i> Beranda
                    </a>
                    <a href="dashboard.php" class="px-3 py-2 rounded-lg transition-all <?= ($currentPage === 'dashboard.php') ? 'bg-[#087F8A] text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/10' ?>">
                        <i class="fa-solid fa-chart-pie mr-1.5 text-xs text-teal-300"></i> Dashboard
                    </a>
                    <a href="simulation.php" class="px-3 py-2 rounded-lg transition-all <?= ($currentPage === 'simulation.php') ? 'bg-[#087F8A] text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/10' ?>">
                        <i class="fa-solid fa-microchip mr-1.5 text-xs text-teal-300"></i> Simulasi 7-Tahap PoC
                    </a>
                    <a href="database_viewer.php" class="px-3 py-2 rounded-lg transition-all <?= ($currentPage === 'database_viewer.php') ? 'bg-[#087F8A] text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/10' ?>">
                        <i class="fa-solid fa-database mr-1.5 text-xs text-teal-300"></i> Database Live
                    </a>
                    <a href="blueprint.php" class="px-3 py-2 rounded-lg transition-all <?= ($currentPage === 'blueprint.php') ? 'bg-[#087F8A] text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/10' ?>">
                        <i class="fa-solid fa-book-open mr-1.5 text-xs text-teal-300"></i> Blueprint SRS
                    </a>
                    <a href="panduan.php" class="px-3 py-2 rounded-lg transition-all <?= ($currentPage === 'panduan.php') ? 'bg-amber-600/30 text-amber-300 border border-amber-500/40' : 'text-amber-300/90 hover:text-amber-200 hover:bg-white/10' ?>">
                        <i class="fa-solid fa-book-open-reader mr-1.5 text-xs"></i> Panduan Tim
                    </a>
                </nav>

                <!-- User & Action Controls -->
                <div class="flex items-center space-x-3">
                    <?php if ($user): ?>
                        <div class="hidden sm:flex items-center space-x-2 text-xs bg-slate-900/90 px-3 py-1.5 rounded-lg border border-teal-500/30">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span class="font-bold text-white"><?= htmlspecialchars($user['full_name']) ?></span>
                            <span class="text-[9px] px-1.5 py-0.5 rounded bg-teal-950 text-teal-300 border border-teal-600/40 uppercase font-bold"><?= htmlspecialchars($user['role']) ?></span>
                        </div>
                        <button onclick="confirmResetData()" title="Reset data simulasi ke awal demo" class="px-2.5 py-1.5 rounded-lg bg-slate-900/90 hover:bg-rose-950/80 hover:text-rose-300 text-slate-300 border border-slate-700 text-xs font-semibold transition-colors">
                            <i class="fa-solid fa-rotate-left mr-1 text-teal-400"></i> Reset Demo
                        </button>
                        <a href="logout.php" title="Keluar" class="px-3 py-1.5 rounded-lg bg-slate-900/90 hover:bg-slate-800 text-slate-300 text-xs font-semibold border border-slate-700 transition-colors">
                            <i class="fa-solid fa-arrow-right-from-bracket text-red-300"></i>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="px-4 py-2 rounded-lg bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white text-xs font-bold shadow-lg shadow-teal-500/30 transition-all flex items-center gap-1.5 border border-teal-300/30">
                            <i class="fa-solid fa-lock text-xs"></i>
                            <span>Masuk Portal</span>
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </header>

    <!-- Main Content Wrapper -->
    <main class="flex-grow">
