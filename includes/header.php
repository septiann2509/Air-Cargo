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
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <style>
        /* InJourney Gradients & Theme Styles */
        body {
            font-family: 'Barlow', sans-serif;
        }
        .bg-injourney-gradient {
            background: linear-gradient(96.84deg, #0ca1af 17.89%, #087f8a 44.05%, #014d54 91.57%) !important;
        }
        .bg-footer-gradient, footer.bg-footer-gradient, header.bg-navbar-gradient {
            background: linear-gradient(to right, #0CA1AF, #087F8A, #014D54) !important;
        }
        .bg-teal-gradient {
            background: linear-gradient(135deg, #04AFBF 0%, #087F8A 100%) !important;
        }
        .bg-navy-gradient {
            background: linear-gradient(135deg, #0D1C42 0%, #081126 100%) !important;
        }
    </style>
</head>
<body class="<?= isset($bodyClass) ? $bodyClass : 'bg-[#F8FAFC] text-slate-800' ?> min-h-screen flex flex-col font-sans selection:bg-[#04AFBF] selection:text-white">

    <!-- Top Navigation Bar (Warna Sesuai Referensi Gambar Klien: InJourney Teal Gradient) -->
    <header class="sticky top-0 z-50 text-white shadow-xl transition-all duration-300 border-b border-white/20" style="background: linear-gradient(to right, #0CA1AF, #087F8A, #014D54) !important;">
        <div class="w-full max-w-[1700px] mx-auto px-4 sm:px-6 lg:px-8 xl:px-10">
            <div class="flex items-center justify-between h-16">
                
                <!-- Logo & Brand (AC — PT Aerospace Consultant, matching footer design) -->
                <div class="flex items-center space-x-3 flex-shrink-0">
                    <a href="index.php" class="flex items-center space-x-3 group cursor-pointer select-none">
                        <div class="w-10 h-10 rounded-xl bg-white text-[#087F8A] flex items-center justify-center font-black shadow-lg shadow-teal-900/30 group-hover:scale-105 transition-transform border border-white/40">
                            <span class="text-sm font-black tracking-tight">AC</span>
                        </div>
                        <div class="leading-tight">
                            <div class="flex items-center space-x-2">
                                <span class="font-extrabold text-sm sm:text-base tracking-tight text-white whitespace-nowrap"><?= $companyName ?></span>
                                <span class="text-[9px] px-2 py-0.5 rounded-full bg-white/20 text-white font-extrabold uppercase tracking-wider border border-white/30 backdrop-blur-sm whitespace-nowrap hidden sm:inline-block">Advisory</span>
                            </div>
                            <p class="text-[10px] text-teal-100 font-semibold tracking-wider uppercase whitespace-nowrap"><?= $companyTagline ?></p>
                        </div>
                    </a>
                </div>

                <!-- Nav Menu Links (Rapih, 1 Baris Sejajar, Bebas Text-Wrap) -->
                <nav class="hidden xl:flex items-center gap-1 2xl:gap-2">
                    <a href="index.php" class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 whitespace-nowrap text-xs xl:text-[13px] 2xl:text-sm font-bold <?= ($currentPage === 'index.php') ? 'bg-white text-[#087F8A] shadow-md font-extrabold' : 'text-white/85 hover:text-white hover:bg-white/15' ?>">
                        <i class="fa-solid fa-compass text-xs <?= ($currentPage === 'index.php') ? 'text-[#087F8A]' : 'text-teal-200' ?>"></i>
                        <span>Beranda</span>
                    </a>
                    <a href="dashboard.php" class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 whitespace-nowrap text-xs xl:text-[13px] 2xl:text-sm font-bold <?= ($currentPage === 'dashboard.php') ? 'bg-white text-[#087F8A] shadow-md font-extrabold' : 'text-white/85 hover:text-white hover:bg-white/15' ?>">
                        <i class="fa-solid fa-chart-pie text-xs <?= ($currentPage === 'dashboard.php') ? 'text-[#087F8A]' : 'text-teal-200' ?>"></i>
                        <span>Dashboard Eksekutif</span>
                    </a>
                    <a href="simulation.php" class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 whitespace-nowrap text-xs xl:text-[13px] 2xl:text-sm font-bold <?= ($currentPage === 'simulation.php') ? 'bg-white text-[#087F8A] shadow-md font-extrabold' : 'text-white/85 hover:text-white hover:bg-white/15' ?>">
                        <i class="fa-solid fa-microchip text-xs <?= ($currentPage === 'simulation.php') ? 'text-[#087F8A]' : 'text-teal-200' ?>"></i>
                        <span>Simulasi 7-Tahap</span>
                        <span class="text-[9px] px-1.5 py-0.2 rounded-full <?= ($currentPage === 'simulation.php') ? 'bg-teal-100 text-[#087F8A]' : 'bg-teal-400/25 text-teal-100 border border-teal-300/30' ?> font-bold">PoC</span>
                    </a>
                    <a href="database_viewer.php" class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 whitespace-nowrap text-xs xl:text-[13px] 2xl:text-sm font-bold <?= ($currentPage === 'database_viewer.php') ? 'bg-white text-[#087F8A] shadow-md font-extrabold' : 'text-white/85 hover:text-white hover:bg-white/15' ?>">
                        <i class="fa-solid fa-database text-xs <?= ($currentPage === 'database_viewer.php') ? 'text-[#087F8A]' : 'text-teal-200' ?>"></i>
                        <span>Database Live</span>
                    </a>
                </nav>

                <!-- User & Action Controls -->
                <div class="flex items-center gap-2.5 flex-shrink-0">
                    <?php if ($user): ?>
                        <div class="hidden lg:flex items-center space-x-2 text-xs bg-white/15 backdrop-blur-md px-3 py-1.5 rounded-lg border border-white/25 text-white shadow-sm whitespace-nowrap flex-shrink-0">
                            <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse"></span>
                            <span class="font-bold text-white"><?= htmlspecialchars($user['full_name']) ?></span>
                            <span class="text-[9px] px-1.5 py-0.5 rounded bg-white text-[#087F8A] uppercase font-black tracking-wider"><?= htmlspecialchars($user['role']) ?></span>
                        </div>
                        <button onclick="confirmResetData()" title="Reset data simulasi ke awal demo" class="px-3 py-1.5 rounded-lg bg-white/15 hover:bg-rose-900/50 text-white hover:text-rose-100 border border-white/25 hover:border-rose-400/40 text-xs font-bold shadow-sm transition-all flex items-center gap-1.5 whitespace-nowrap flex-shrink-0">
                            <i class="fa-solid fa-rotate-left text-amber-300 text-xs"></i>
                            <span class="hidden md:inline">Reset Demo</span>
                        </button>
                        <a href="logout.php" title="Keluar" class="p-2 rounded-lg bg-white/15 hover:bg-white/25 text-white text-xs font-semibold border border-white/25 shadow-sm transition-colors flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="px-3.5 py-1.5 rounded-lg bg-white text-[#087F8A] hover:bg-teal-50 font-extrabold text-xs shadow-md transition-all flex items-center gap-1.5 whitespace-nowrap">
                            <i class="fa-solid fa-lock text-xs"></i>
                            <span>Masuk Portal</span>
                        </a>
                    <?php endif; ?>

                    <!-- Mobile Menu Hamburger Button -->
                    <button id="mobile-nav-toggle" class="xl:hidden p-2 text-white hover:text-teal-200 focus:outline-none" aria-label="Toggle Mobile Menu">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>
                </div>

            </div>
        </div>
    </header>

    <!-- Mobile Navigation Drawer Overlay (InJourney Teal Gradient) -->
    <div id="mobile-nav-drawer" class="fixed inset-0 z-40 flex flex-col justify-between px-6 py-20 text-white transform -translate-y-full transition-transform duration-300 xl:hidden shadow-2xl border-b border-white/20" style="background: linear-gradient(to right, #0CA1AF, #087F8A, #014D54) !important;">
        <div class="flex flex-col space-y-4 text-base font-bold text-center">
            <a href="index.php" class="py-2.5 rounded-xl <?= ($currentPage === 'index.php') ? 'bg-white text-[#087F8A]' : 'text-white hover:bg-white/15' ?>">Beranda</a>
            <a href="dashboard.php" class="py-2.5 rounded-xl <?= ($currentPage === 'dashboard.php') ? 'bg-white text-[#087F8A]' : 'text-white hover:bg-white/15' ?>">Dashboard Eksekutif</a>
            <a href="simulation.php" class="py-2.5 rounded-xl <?= ($currentPage === 'simulation.php') ? 'bg-white text-[#087F8A]' : 'text-white hover:bg-white/15' ?>">Simulasi 7-Tahap PoC</a>
            <a href="database_viewer.php" class="py-2.5 rounded-xl <?= ($currentPage === 'database_viewer.php') ? 'bg-white text-[#087F8A]' : 'text-white hover:bg-white/15' ?>">Database Live</a>
        </div>
        <div class="text-center text-xs text-white/80 space-y-1">
            <p class="font-bold text-white"><?= $companyName ?> &bull; <?= $companyTagline ?></p>
            <p>Treasury Tower SCBD &bull; InJourney Airports Design System</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('mobile-nav-toggle');
            const drawer = document.getElementById('mobile-nav-drawer');
            if (toggleBtn && drawer) {
                toggleBtn.addEventListener('click', () => {
                    drawer.classList.toggle('-translate-y-full');
                });
            }
        });
    </script>

    <!-- Main Content Wrapper -->
    <main class="flex-grow">
