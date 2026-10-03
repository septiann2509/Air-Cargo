<?php
// index.php — Homepage 100% InJourney Airports Architecture dengan Video Latar Jelas
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/database.php';
$user = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

// Profil Perusahaan Konsultan
$companyName    = 'PT Aerospace Consultant';
$companyTagline = 'Air Cargo & Intermodal Advisory';
$companyShort   = 'AC';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $companyName ?> — <?= $companyTagline ?> | InJourney Airports Ecosystem</title>
    <meta name="description" content="<?= $companyName ?> adalah firma konsultan teknologi logistik yang merekomendasikan arsitektur sistem digital dan integrasi multimoda Air Cargo & Intermodal Terminal Bandara.">
    <link rel="shortcut icon" type="image/x-icon" href="https://injourneyairports.id/kawung.ico">

    <!-- Barlow Font — Sama persis dengan InJourney Airports -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,600&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Barlow"', 'sans-serif'],
                    },
                    colors: {
                        injourney: {
                            teal: '#0CA1AF',
                            darkteal: '#087F8A',
                            deepcyan: '#014D54',
                            cyanbtn: '#04AFBF',
                            navy: '#0D1C42',
                            dark: '#404042',
                            light: '#F5F5F5'
                        }
                    }
                }
            }
        }
    </script>

    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* InJourney Custom CSS */
        body {
            font-family: 'Barlow', sans-serif;
            color: #404042;
            background: #ffffff;
            overflow-x: hidden;
        }

        /* InJourney link underline animation */
        .link-underline {
            border-bottom-width: 0;
            background-image: linear-gradient(transparent, transparent), linear-gradient(#fff, #fff);
            background-size: 0 2px;
            background-position: 0 100%;
            background-repeat: no-repeat;
            transition: background-size .4s ease-in-out;
        }
        .link-underline-black {
            background-image: linear-gradient(transparent, transparent), linear-gradient(#000, #000);
        }
        .link-underline-teal {
            background-image: linear-gradient(transparent, transparent), linear-gradient(#087f8a, #087f8a);
        }
        .link-underline:hover {
            background-size: 100% 2px;
            background-position: 0 100%;
        }

        /* InJourney gradient header & footer */
        .bg-injourney-gradient {
            background: linear-gradient(96.84deg, #0ca1af 17.89%, #087f8a 44.05%, #014d54 91.57%);
        }
        .bg-footer-gradient {
            background: linear-gradient(to right, #0CA1AF, #087F8A, #014D54);
        }

        /* High clarity hero text shadow agar teks tetap sangat jelas terbaca di atas video yang terang */
        .hero-title-shadow {
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.75), 0 1px 3px rgba(0, 0, 0, 0.85);
        }
        .hero-desc-shadow {
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.75), 0 1px 2px rgba(0, 0, 0, 0.85);
        }

        /* Video styling */
        video {
            object-fit: cover;
        }
    </style>
</head>
<body class="selection:bg-[#0CA1AF] selection:text-white">

<!-- ═══════════════════════════════════════════════════════════════════════════
     HEADER & NAVBAR (100% PERSIS INJOURNEY DESKTOP & RESPONSIVE HEADER)
     - Transparent saat di atas hero (scrollY < 160) dengan teks berbayang halus
     - Menjadi solid putih (bg-white text-black shadow-md) saat di-scroll
     ═══════════════════════════════════════════════════════════════════════════ -->
<header id="main-header" class="fixed top-0 z-50 flex items-center justify-between w-full pt-3 2xl:pt-4 text-lg font-medium px-6 lg:px-12 xl:px-16 3xl:px-32 transition-all duration-300 bg-transparent text-white">
    
    <!-- Brand Logo -->
    <a href="index.php" class="flex items-center gap-3 cursor-pointer select-none">
        <div class="w-10 h-10 lg:w-11 lg:h-11 rounded-xl bg-gradient-to-tr from-[#0CA1AF] to-[#014D54] flex items-center justify-center text-white font-extrabold shadow-lg shadow-teal-500/30">
            <span class="text-base tracking-tighter"><?= $companyShort ?></span>
        </div>
        <div class="leading-tight">
            <span id="brand-title" class="font-extrabold text-base lg:text-lg block tracking-tight transition-colors duration-300 text-white hero-desc-shadow">
                <?= $companyName ?>
            </span>
            <span id="brand-subtitle" class="text-[10px] uppercase tracking-widest font-semibold block transition-colors duration-300 text-teal-200 hero-desc-shadow">
                <?= $companyTagline ?>
            </span>
        </div>
    </a>

    <!-- Navigation Menus -->
    <nav class="hidden xl:flex items-center text-sm 2xl:text-base">
        <ul class="flex items-center gap-2 2xl:gap-6">
            <li>
                <a href="index.php" class="nav-item p-1.5 font-semibold link-underline link-underline-white transition-colors duration-300 hero-desc-shadow">
                    Beranda
                </a>
            </li>
            <li class="relative group">
                <a href="dashboard.php" class="nav-item p-1.5 font-medium link-underline link-underline-white transition-colors duration-300 hero-desc-shadow">
                    Dashboard Eksekutif
                </a>
            </li>
            <li class="relative group">
                <a href="simulation.php" class="nav-item p-1.5 font-medium link-underline link-underline-white transition-colors duration-300 flex items-center gap-1 hero-desc-shadow">
                    <span>Simulasi 7-Tahap PoC</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-[#04AFBF] text-white font-bold animate-pulse shadow">Live</span>
                </a>
            </li>
            <li>
                <a href="database_viewer.php" class="nav-item p-1.5 font-medium link-underline link-underline-white transition-colors duration-300 hero-desc-shadow">
                    Database Live
                </a>
            </li>
            <li>
                <a href="blueprint.php" class="nav-item p-1.5 font-medium link-underline link-underline-white transition-colors duration-300 hero-desc-shadow">
                    Blueprint SRS
                </a>
            </li>
            <li>
                <a href="panduan.php" class="nav-item p-1.5 font-semibold text-amber-300 hover:text-amber-200 transition-colors duration-300 flex items-center gap-1 hero-desc-shadow">
                    <i class="fa-solid fa-book-open-reader text-xs"></i>
                    <span>Panduan Tim</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Right Controls: Language Selector & Auth Portal -->
    <div class="flex items-center gap-3 lg:gap-5">
        
        <!-- Language Switcher Pill (InJourney style) -->
        <div class="hidden sm:flex items-center gap-1 text-xs font-bold">
            <button id="lang-id" class="border-2 rounded-full px-3 py-0.5 border-white bg-white text-black transition-all shadow">ID</button>
            <button id="lang-en" class="border-2 rounded-full px-3 py-0.5 border-white text-white hover:bg-white/20 transition-all">EN</button>
        </div>

        <!-- Auth Button / User Status -->
        <?php if ($user): ?>
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-black/40 backdrop-blur-md border border-white/20 text-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="font-semibold"><?= htmlspecialchars($user['full_name']) ?></span>
                <a href="logout.php" title="Keluar" class="ml-1 text-red-300 hover:text-red-100">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        <?php else: ?>
            <a href="login.php" class="flex items-center gap-2 px-4 py-1.5 lg:px-5 lg:py-2 rounded-full bg-[#04AFBF] hover:bg-[#087F8A] text-white text-xs lg:text-sm font-bold shadow-lg shadow-teal-500/20 transition-all transform hover:-translate-y-0.5">
                <i class="fa-solid fa-lock text-xs"></i>
                <span>Masuk Portal</span>
            </a>
        <?php endif; ?>

        <!-- Mobile Menu Toggle Button -->
        <button id="mobile-toggle" class="xl:hidden p-2 text-xl focus:outline-none" aria-label="Toggle Navigation">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
</header>

<!-- Mobile Navigation Overlay (InJourney Fullscreen Gradient Drawer) -->
<div id="mobile-drawer" class="fixed inset-0 z-40 bg-injourney-gradient flex flex-col justify-between px-8 py-20 text-white transform -translate-y-full transition-transform duration-500 xl:hidden">
    <div class="flex flex-col space-y-6 text-xl text-center font-semibold">
        <a href="index.php" class="hover:text-teal-200">Beranda</a>
        <a href="dashboard.php" class="hover:text-teal-200">Dashboard Eksekutif</a>
        <a href="simulation.php" class="hover:text-teal-200">Simulasi 7-Tahap PoC</a>
        <a href="database_viewer.php" class="hover:text-teal-200">Database Live</a>
        <a href="blueprint.php" class="hover:text-teal-200">Blueprint SRS</a>
        <a href="panduan.php" class="text-amber-300 hover:text-amber-100">Panduan Tim</a>
    </div>
    <div class="text-center text-xs text-white/70 space-y-1">
        <p><?= $companyName ?> &bull; Topik 6 ITL Trisakti 2026</p>
        <p>InJourney Airports Ecosystem &bull; All Rights Reserved</p>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     HERO SECTION DENGAN VIDEO LOKAL TERANG & JELAS TERLIHAT
     - Video diputar langsung dari file lokal: assets/video/injourney_hero.mp4
     - Overlay tipis & transparan (black/30 dengan gradient halus) agar video terlihat jernih
     - Signature curved cutout InJourney tetap ada di sudut kanan bawah
     ═══════════════════════════════════════════════════════════════════════════ -->
<section class="relative h-screen lg:pr-6 overflow-hidden bg-slate-900 select-none">
    
    <!-- Background Video Lokal (Terang, Jernih & Sangat Jelas Terlihat) -->
    <div class="relative w-full h-screen">
        <!-- Overlay sangat lembut hanya di belakang teks kiri (black/40 ke transparan) agar pemandangan video tetap terang benderang -->
        <div class="absolute inset-0 bg-gradient-to-r from-black/45 via-black/15 to-transparent z-10 pointer-events-none"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/25 via-transparent to-transparent z-10 pointer-events-none"></div>
        
        <video id="hero-video" class="object-cover w-full h-screen" style="filter: brightness(1.22) contrast(1.05);" width="100%" loop autoplay muted playsinline preload="auto">
            <source src="assets/video/injourney_hero.mp4" type="video/mp4">
            <source src="https://injourneyairports.id/assets/home-background-video-renamed-BHhD8CQ5.mp4" type="video/mp4">
        </video>
    </div>

    <!-- Hero Content Overlay (Persis posisi InJourney Hero) -->
    <div class="absolute top-1/2 -translate-y-1/2 lg:top-[260px] lg:translate-y-0 2xl:top-[280px] 3xl:top-72 px-6 lg:px-16 2xl:px-32 w-full z-20">
        <div class="flex flex-col space-y-6 lg:space-y-10 items-start justify-start max-w-5xl">
            
            <!-- Category Tagline -->
            <div class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-black/40 border border-teal-300/60 text-teal-300 text-xs 2xl:text-sm font-bold tracking-wider uppercase backdrop-blur-md shadow-lg">
                <i class="fa-solid fa-plane-departure text-xs text-amber-300"></i>
                <span>Laporan Konsultansi Teknologi Logistik &bull; Topik 6</span>
            </div>

            <!-- Big Main Headline (Barlow ExtraBold dengan teks bayangan tajam) -->
            <h1 class="text-3xl font-extrabold text-white sm:text-5xl lg:text-6xl 2xl:text-7xl leading-tight lg:leading-[1.1] tracking-tight hero-title-shadow">
                Transformasi Digital <span class="text-[#4EBDC3]">Air Cargo</span> &amp; Intermodal Terminal
            </h1>

            <p class="text-white text-base lg:text-xl 2xl:text-2xl font-normal max-w-3xl leading-relaxed hero-desc-shadow">
                Rekomendasi arsitektur 4-layer IT logistik terpadu, standardisasi serial data GS1 SSCC 18-digit, dan simulasi otomasi alur fisik-digital kargo udara dari gerbang darat hingga pesawat.
            </p>

            <!-- Actions & InJourney Play Video Style -->
            <div class="flex flex-wrap items-center gap-4 lg:gap-8 pt-2">
                <a href="simulation.php" class="px-6 py-3.5 lg:px-8 lg:py-4 rounded-full bg-[#04AFBF] hover:bg-[#087F8A] text-white font-bold text-sm lg:text-base shadow-2xl shadow-teal-500/40 transition-all transform hover:-translate-y-0.5 flex items-center gap-3 border border-white/20">
                    <i class="fa-solid fa-play text-sm"></i>
                    <span>Uji Coba Simulasi 7-Tahap PoC</span>
                </a>
                
                <a href="blueprint.php" class="px-6 py-3.5 lg:px-8 lg:py-4 rounded-full bg-black/40 hover:bg-black/60 border-2 border-white/60 text-white font-semibold text-sm lg:text-base backdrop-blur-md transition-all flex items-center gap-2 shadow-lg">
                    <i class="fa-solid fa-file-invoice text-sm"></i>
                    <span>Baca Dokumen Blueprint SRS</span>
                </a>
            </div>

        </div>
    </div>

    <!-- ── SIGNATURE INJOURNEY CURVED CUTOUT AT BOTTOM RIGHT ── -->
    <!-- SVG Top Arc -->
    <div class="absolute right-0 bottom-16 lg:bottom-24 2xl:bottom-28 lg:right-6 z-20 pointer-events-none hidden sm:block">
        <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M48 0C48 26.5097 26.5097 48 0 48H48V0Z" fill="white"/>
        </svg>
    </div>
    <!-- SVG Left Arc -->
    <div class="absolute bottom-0 right-48 lg:right-72 2xl:right-80 z-20 pointer-events-none hidden sm:block">
        <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M48 0C48 26.5097 26.5097 48 0 48H48V0Z" fill="white"/>
        </svg>
    </div>
    <!-- White Tab Container -->
    <div class="absolute bottom-0 right-0 h-16 lg:h-24 2xl:h-28 bg-white rounded-tl-3xl lg:rounded-tl-[48px] w-64 lg:w-80 2xl:w-96 p-4 lg:p-6 flex gap-3 lg:gap-6 items-center z-20 shadow-2xl">
        <img src="assets/img/injourney/kawung-logo-side-CktPU2GK.png" alt="InJourney Kawung" class="object-contain w-8 lg:w-12">
        <div class="leading-tight">
            <span class="text-xs lg:text-sm font-extrabold text-[#0D1C42] block"><?= $companyName ?></span>
            <span class="text-[10px] lg:text-xs text-gray-500 font-medium block">Advisory Portal &bull; ITL Trisakti 2026</span>
        </div>
    </div>

</section>


<!-- ═══════════════════════════════════════════════════════════════════════════
     SECTION 1: TENTANG KAMI / PROFIL (100% PERSIS INJOURNEY "SHORT PROFILE")
     - Memakai motif sudut hijau (pattern-green-BTZ3yksm.png)
     - Logo + Headline profil + Pill button "Profil Kami"
     - 2 Kolom teks justified yang rapi dan elegan
     ═══════════════════════════════════════════════════════════════════════════ -->
<section class="relative pt-12 lg:pt-20 pb-16 px-6 lg:px-16 2xl:px-32 bg-white overflow-hidden">
    
    <!-- Top-Right InJourney Green Pattern Asset -->
    <img src="assets/img/injourney/pattern-green-BTZ3yksm.png" alt="Green Pattern" class="absolute top-0 right-0 w-48 xl:w-80 pointer-events-none opacity-90 select-none">

    <div class="max-w-7xl mx-auto space-y-8 lg:space-y-12 relative z-10">
        
        <!-- Header Row: Emblem + Tagline + Button -->
        <div class="flex flex-col lg:flex-row justify-between items-center lg:items-start gap-6 border-b border-gray-100 pb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-[#087F8A] flex items-center justify-center text-white text-xl font-bold">
                    <?= $companyShort ?>
                </div>
                <div>
                    <h3 class="font-extrabold text-xl lg:text-2xl text-[#0D1C42]">Profil Konsultan</h3>
                    <p class="text-xs text-[#087F8A] font-semibold tracking-wider uppercase">Firma Penasihat Teknologi Logistik Bandara</p>
                </div>
            </div>

            <!-- Big Centered InJourney Tagline -->
            <div class="text-xl lg:text-2xl xl:text-3xl font-bold text-center lg:text-left text-[#0D1C42] max-w-xl">
                Solusi Arsitektur Sistem Kargo Udara Kelas Dunia untuk Klien Pengelola Bandara
            </div>

            <!-- InJourney Styled Pill Button -->
            <a href="blueprint.php" class="hidden xl:inline-flex items-center gap-2 px-6 py-2.5 border-2 border-[#0D1C42] hover:bg-[#0D1C42] hover:text-white rounded-full font-bold text-sm transition-all duration-300 transform hover:-translate-y-0.5">
                <span>Pelajari Blueprint SRS</span>
                <i class="fa-solid fa-arrow-right text-xs"></i>
            </a>
        </div>

        <!-- 2-Column Justified Description (Sama seperti InJourney full_profile 1 & 2) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-14 text-justify text-base lg:text-lg text-slate-700 leading-relaxed">
            <div class="space-y-4">
                <p>
                    <strong class="text-[#0D1C42] font-semibold"><?= $companyName ?></strong> adalah firma konsultan teknologi logistik independen yang dibentuk untuk memberikan arahan strategis dan perancangan arsitektur sistem informasi kepada pengelola terminal kargo bandara serta entitas multimoda. Kami memposisikan diri secara tegas sebagai <span class="font-semibold text-[#087F8A]">penasihat teknis (advisory team)</span> yang merumuskan rekomendasi komprehensif, bukan sebagai vendor pelaksana perangkat keras semata.
                </p>
                <p>
                    Tantangan utama yang dihadapi terminal kargo modern saat ini meliputi tingginya waktu inap kargo (<em>dwell time</em>), inkonsistensi pencatatan manifes kertas e-AWB, serta belum optimalnya interoperabilitas antara sistem transportasi darat (TMS/TAS) dengan sistem kargo udara bandara (CMS).
                </p>
            </div>
            <div class="space-y-4">
                <p>
                    Untuk menjawab tantangan tersebut, kami merekomendasikan integrasi standardisasi internasional <span class="font-semibold text-[#0D1C42]">GS1 Serial Shipping Container Code (SSCC 18-Digit)</span> sebagai pengenal identitas tunggal setiap palet kargo, sensor otomatis RFID UHF, jembatan timbang cerdas OIML R76, dan screening AVSEC Dual-View yang terkoneksi langsung ke Cargo Security Declaration (CSD).
                </p>
                <p>
                    Melalui portal ini, kami menyajikan dokumen <strong class="text-[#0D1C42]">Blueprint Software Requirements Specification (SRS)</strong> lengkap beserta simulator Proof-of-Concept interaktif agar pihak manajemen klien dapat memvalidasi seluruh aliran data secara transparan sebelum mengambil keputusan investasi modal.
                </p>
            </div>
        </div>

    </div>
</section>


<!-- ═══════════════════════════════════════════════════════════════════════════
     SECTION 2: FAKTA & CAPAIAN METRIK (100% PERSIS INJOURNEY ACCOMPLISHMENTS)
     - Background Solid InJourney Dark Teal: #087F8A
     - 3 Kolom Ikon Resmi InJourney: Users Icon, Plane Icon, Box Icon
     ═══════════════════════════════════════════════════════════════════════════ -->
<section class="bg-[#087F8A] text-white py-14 lg:py-20 px-6 lg:px-16 2xl:px-32 relative select-none">
    <div class="max-w-7xl mx-auto space-y-12">
        
        <!-- Header Text -->
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 border-b border-white/20 pb-8">
            <div class="space-y-2">
                <span class="text-xs uppercase font-bold tracking-widest text-teal-200">Fakta Kinerja &bull; Target SLA</span>
                <h2 class="text-3xl lg:text-4xl 2xl:text-5xl font-bold">Tolok Ukur Efisiensi Operasional</h2>
            </div>
            <p class="text-white/80 text-sm lg:text-base max-w-md text-justify">
                Hasil analisis kami menetapkan target penurunan waktu tunggu dan tingkat akurasi tinggi melalui penerapan otomasi sensing AIDC dan validasi REST API.
            </p>
        </div>

        <!-- 3 InJourney Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-12 text-center lg:text-left">
            
            <!-- Metric 1: Users Icon -->
            <div class="flex flex-col lg:flex-row items-center gap-6 p-6 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all">
                <img src="assets/img/injourney/users-icon-C6U03lPH.svg" alt="Users Icon" class="h-16 lg:h-20 flex-shrink-0">
                <div class="space-y-1 text-center lg:text-left">
                    <div class="flex items-baseline justify-center lg:justify-start gap-1 font-extrabold">
                        <span class="text-4xl 2xl:text-5xl tracking-tight">154+</span>
                        <span class="text-xl 2xl:text-2xl text-teal-200">Mitra</span>
                    </div>
                    <span class="text-xs lg:text-sm font-semibold text-white/90 block uppercase tracking-wider">Operator Logistik &amp; Maskapai</span>
                    <span class="text-xs text-white/60 block">Terhubung e-AWB IATA &amp; TAS</span>
                </div>
            </div>

            <!-- Metric 2: Plane Icon -->
            <div class="flex flex-col lg:flex-row items-center gap-6 p-6 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all">
                <img src="assets/img/injourney/plane-icon-DqGVdr9T.svg" alt="Plane Icon" class="h-16 lg:h-20 flex-shrink-0">
                <div class="space-y-1 text-center lg:text-left">
                    <div class="flex items-baseline justify-center lg:justify-start gap-1 font-extrabold">
                        <span class="text-4xl 2xl:text-5xl tracking-tight">1,2 Juta</span>
                    </div>
                    <span class="text-xs lg:text-sm font-semibold text-white/90 block uppercase tracking-wider">Pergerakan Kargo Udara</span>
                    <span class="text-xs text-white/60 block">Pertukaran Data Real-Time per Tahun</span>
                </div>
            </div>

            <!-- Metric 3: Box Icon -->
            <div class="flex flex-col lg:flex-row items-center gap-6 p-6 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all">
                <img src="assets/img/injourney/box-icon-kyphuuRe.svg" alt="Box Icon" class="h-16 lg:h-20 flex-shrink-0">
                <div class="space-y-1 text-center lg:text-left">
                    <div class="flex items-baseline justify-center lg:justify-start gap-1 font-extrabold">
                        <span class="text-4xl 2xl:text-5xl tracking-tight">1.295.162</span>
                    </div>
                    <span class="text-xs lg:text-sm font-semibold text-white/90 block uppercase tracking-wider">Ton Kargo Termonitor</span>
                    <span class="text-xs text-white/60 block">Dukungan GS1 SSCC Berstandar Internasional</span>
                </div>
            </div>

        </div>

        <!-- Secondary SLA KPI Badges -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t border-white/10 text-center">
            <div class="p-3">
                <span class="text-2xl lg:text-3xl font-black text-amber-300 block">&minus;81%</span>
                <span class="text-xs text-white/70 uppercase tracking-wider font-semibold">Penurunan Dwell Time</span>
            </div>
            <div class="p-3">
                <span class="text-2xl lg:text-3xl font-black text-emerald-300 block">99.8%</span>
                <span class="text-xs text-white/70 uppercase tracking-wider font-semibold">Akurasi Pembacaan AIDC</span>
            </div>
            <div class="p-3">
                <span class="text-2xl lg:text-3xl font-black text-teal-200 block">&lt; 45 Mnt</span>
                <span class="text-xs text-white/70 uppercase tracking-wider font-semibold">Turnaround Kargo</span>
            </div>
            <div class="p-3">
                <span class="text-2xl lg:text-3xl font-black text-white block">100% CSD</span>
                <span class="text-xs text-white/70 uppercase tracking-wider font-semibold">Kepatuhan Regulasi ICAO</span>
            </div>
        </div>

    </div>
</section>


<!-- ═══════════════════════════════════════════════════════════════════════════
     SECTION 3: LAYANAN & BISNIS KONSULTANSI (100% PERSIS INJOURNEY BUSINESS RELATIONS)
     - Header: flex-col-reverse dengan judul besar di samping
     - Grid 2-kolom atau 3-kolom deskripsi bisnis
     ═══════════════════════════════════════════════════════════════════════════ -->
<section class="py-16 lg:py-24 px-6 lg:px-16 2xl:px-32 bg-white">
    <div class="max-w-7xl mx-auto space-y-12">
        
        <!-- Header Persis InJourney Business Relations -->
        <div class="flex flex-col-reverse lg:flex-row justify-between items-start gap-8 border-b border-gray-100 pb-8">
            <div class="text-slate-600 text-base lg:text-lg max-w-2xl text-justify leading-relaxed">
                Kami menyediakan enam pilar konsultansi terpadu untuk merancang transformasi terminal kargo bandara menuju era otomasi penuh, memastikan kesinambungan antara aspek legal, keamanan aviasi, dan efisiensi waktu operasional.
            </div>
            <h2 class="text-3xl lg:text-5xl font-extrabold text-[#0D1C42] tracking-tight text-right flex-shrink-0">
                Pilar Rekomendasi<br><span class="text-[#087F8A]">Sistem Kargo</span>
            </h2>
        </div>

        <!-- Grid 6 Layanan / Bisnis Konsultansi -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            
            <!-- Item 1: Airport Cargo Terminal -->
            <div class="p-8 rounded-2xl bg-[#F8FAFC] border border-gray-200 hover:border-[#0CA1AF] hover:shadow-xl hover:shadow-teal-900/5 transition-all space-y-4 group">
                <div class="w-12 h-12 rounded-xl bg-teal-50 text-[#087F8A] group-hover:bg-[#087F8A] group-hover:text-white flex items-center justify-center text-xl transition-colors">
                    <i class="fa-solid fa-warehouse"></i>
                </div>
                <h3 class="font-bold text-xl text-[#0D1C42]">Terminal Kargo Bandara</h3>
                <p class="text-sm text-slate-600 text-justify leading-relaxed">
                    Perancangan alur kargo inbound dan outbound, pengelolaan zonasi gudang domestik dan internasional, serta integrasi platform Cargo Management System (CMS) berstandar IATA e-AWB.
                </p>
                <a href="blueprint.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8A] hover:text-[#0CA1AF] pt-2">
                    <span>Lihat Spesifikasi Teknis</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </a>
            </div>

            <!-- Item 2: Intermodal Connectivity -->
            <div class="p-8 rounded-2xl bg-[#F8FAFC] border border-gray-200 hover:border-[#0CA1AF] hover:shadow-xl hover:shadow-teal-900/5 transition-all space-y-4 group">
                <div class="w-12 h-12 rounded-xl bg-teal-50 text-[#087F8A] group-hover:bg-[#087F8A] group-hover:text-white flex items-center justify-center text-xl transition-colors">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                </div>
                <h3 class="font-bold text-xl text-[#0D1C42]">Konektivitas Intermodal</h3>
                <p class="text-sm text-slate-600 text-justify leading-relaxed">
                    Penyelarasan sistem transportasi darat (TMS) armada truk dengan Truck Appointment System (TAS) gerbang bandara, memastikan waktu tiba terjadwal dan bebas antrean panjang.
                </p>
                <a href="simulation.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8A] hover:text-[#0CA1AF] pt-2">
                    <span>Simulasikan Gerbang Truk</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </a>
            </div>

            <!-- Item 3: AVSEC Screening -->
            <div class="p-8 rounded-2xl bg-[#F8FAFC] border border-gray-200 hover:border-[#0CA1AF] hover:shadow-xl hover:shadow-teal-900/5 transition-all space-y-4 group">
                <div class="w-12 h-12 rounded-xl bg-teal-50 text-[#087F8A] group-hover:bg-[#087F8A] group-hover:text-white flex items-center justify-center text-xl transition-colors">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h3 class="font-bold text-xl text-[#0D1C42]">Keamanan AVSEC Dual-View</h3>
                <p class="text-sm text-slate-600 text-justify leading-relaxed">
                    Rekomendasi mesin X-Ray dual-view berkemampuan identifikasi barang berbahaya (Dangerous Goods) dan penerbitan otomatis Cargo Security Declaration (CSD) sesuai mandat ICAO.
                </p>
                <a href="simulation.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8A] hover:text-[#0CA1AF] pt-2">
                    <span>Protokol CSD &bull; Cleared / Suspect</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </a>
            </div>

            <!-- Item 4: Smart ULD Weighing -->
            <div class="p-8 rounded-2xl bg-[#F8FAFC] border border-gray-200 hover:border-[#0CA1AF] hover:shadow-xl hover:shadow-teal-900/5 transition-all space-y-4 group">
                <div class="w-12 h-12 rounded-xl bg-teal-50 text-[#087F8A] group-hover:bg-[#087F8A] group-hover:text-white flex items-center justify-center text-xl transition-colors">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <h3 class="font-bold text-xl text-[#0D1C42]">Penimbangan ULD Presisi</h3>
                <p class="text-sm text-slate-600 text-justify leading-relaxed">
                    Sistem timbangan digital kapasitas 15 Ton dengan sertifikasi OIML R76, menghubungkan data berat kotor dan tara secara real-time ke modul Weight and Balance penerbangan.
                </p>
                <a href="database_viewer.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8A] hover:text-[#0CA1AF] pt-2">
                    <span>Lihat Rekor Timbangan Live</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </a>
            </div>

            <!-- Item 5: GS1 SSCC Serialization -->
            <div class="p-8 rounded-2xl bg-[#F8FAFC] border border-gray-200 hover:border-[#0CA1AF] hover:shadow-xl hover:shadow-teal-900/5 transition-all space-y-4 group">
                <div class="w-12 h-12 rounded-xl bg-teal-50 text-[#087F8A] group-hover:bg-[#087F8A] group-hover:text-white flex items-center justify-center text-xl transition-colors">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <h3 class="font-bold text-xl text-[#0D1C42]">Standardisasi GS1 SSCC</h3>
                <p class="text-sm text-slate-600 text-justify leading-relaxed">
                    Implementasi barcode 18-digit Serial Shipping Container Code (AI 00) dan Global Trade Item Number (GTIN) guna meniadakan duplikasi data dan human error pelabelan.
                </p>
                <a href="blueprint.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8A] hover:text-[#0CA1AF] pt-2">
                    <span>Struktur AI (00) &amp; Check Digit</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </a>
            </div>

            <!-- Item 6: Executive Advisory & ROI -->
            <div class="p-8 rounded-2xl bg-[#F8FAFC] border border-gray-200 hover:border-[#0CA1AF] hover:shadow-xl hover:shadow-teal-900/5 transition-all space-y-4 group">
                <div class="w-12 h-12 rounded-xl bg-teal-50 text-[#087F8A] group-hover:bg-[#087F8A] group-hover:text-white flex items-center justify-center text-xl transition-colors">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <h3 class="font-bold text-xl text-[#0D1C42]">Kajian Kelayakan &amp; ROI</h3>
                <p class="text-sm text-slate-600 text-justify leading-relaxed">
                    Analisis Return on Investment (ROI), pemetaan risiko implementasi bertahap (Phased Rollout), serta strategi manajemen perubahan bagi operator lapangan dan maskapai.
                </p>
                <a href="blueprint.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8A] hover:text-[#0CA1AF] pt-2">
                    <span>Baca Bab 7 Analisis ROI</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </a>
            </div>

        </div>

    </div>
</section>


<!-- ═══════════════════════════════════════════════════════════════════════════
     SECTION 4: INTERACTIVE SELECTOR (100% PERSIS INJOURNEY DESTINATION SELECTOR)
     - Banner selector warna Cyan InJourney: #04AFBF
     - "Pilih Jalur Simulasi Kargo / Terminal Tujuan"
     - Dropdown interaktif 7 Tahapan Kargo
     ═══════════════════════════════════════════════════════════════════════════ -->
<section class="py-12 px-6 lg:px-16 2xl:px-32 bg-[#014D54] relative select-none">
    <div class="max-w-7xl mx-auto flex flex-col lg:flex-row items-center justify-between gap-6">
        
        <div class="text-center lg:text-left space-y-1">
            <span class="text-xs uppercase font-bold tracking-widest text-teal-300">Laboratorium Uji Alur</span>
            <h3 class="text-2xl lg:text-3xl font-bold text-white">Temukan Informasi &amp; Uji Alur Data Kargo</h3>
        </div>

        <!-- InJourney Styled Cyan Trigger Bar -->
        <div class="w-full lg:w-auto relative">
            <button id="selector-trigger" class="w-full lg:w-96 py-3 px-6 rounded-full lg:rounded-xl bg-[#04AFBF] hover:bg-[#0CA1AF] text-white font-bold text-sm lg:text-base flex items-center justify-between shadow-lg transition-all">
                <span>Pilih Tahapan Alur Kargo</span>
                <i class="fa-solid fa-chevron-down text-xs ml-3 transition-transform" id="selector-chevron"></i>
            </button>

            <!-- Dropdown Menu Box -->
            <div id="selector-dropdown" class="hidden absolute left-0 right-0 mt-3 p-4 rounded-2xl bg-[#087F8A] text-white shadow-2xl z-30 border border-teal-400/30 space-y-2">
                <a href="simulation.php?stage=1" class="flex items-center justify-between p-2.5 rounded-lg hover:bg-white/10 transition-colors">
                    <span class="text-sm font-semibold">1. Kedatangan Truk (TMS Gate Inbound)</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold">READY</span>
                </a>
                <a href="simulation.php?stage=2" class="flex items-center justify-between p-2.5 rounded-lg hover:bg-white/10 transition-colors">
                    <span class="text-sm font-semibold">2. Pemindaian Gerbang RFID (GS1 SSCC 18D)</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold">READY</span>
                </a>
                <a href="simulation.php?stage=3" class="flex items-center justify-between p-2.5 rounded-lg hover:bg-white/10 transition-colors">
                    <span class="text-sm font-semibold">3. Registrasi Kargo CMS (e-AWB Match)</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold">READY</span>
                </a>
                <a href="simulation.php?stage=4" class="flex items-center justify-between p-2.5 rounded-lg hover:bg-white/10 transition-colors">
                    <span class="text-sm font-semibold">4. Pemeriksaan Keamanan AVSEC (CSD)</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold">ACTIVE</span>
                </a>
                <a href="simulation.php?stage=5" class="flex items-center justify-between p-2.5 rounded-lg hover:bg-white/10 transition-colors">
                    <span class="text-sm font-semibold">5. Penimbangan ULD Smart Scale (15T)</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-500/20 text-slate-300 font-bold">STANDBY</span>
                </a>
                <a href="simulation.php?stage=6" class="flex items-center justify-between p-2.5 rounded-lg hover:bg-white/10 transition-colors">
                    <span class="text-sm font-semibold">6. Build-Up ULD &amp; Weight/Balance Plan</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-500/20 text-slate-300 font-bold">STANDBY</span>
                </a>
                <a href="simulation.php?stage=7" class="flex items-center justify-between p-2.5 rounded-lg hover:bg-white/10 transition-colors">
                    <span class="text-sm font-semibold">7. Pemuatan Pesawat &amp; Manifes Final</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-500/20 text-slate-300 font-bold">STANDBY</span>
                </a>
                <div class="pt-2 border-t border-white/20 text-center">
                    <a href="simulation.php" class="text-xs font-bold text-teal-200 hover:text-white underline">
                        Buka Laboratorium Simulasi Lengkap &rarr;
                    </a>
                </div>
            </div>
        </div>

    </div>
</section>


<!-- ═══════════════════════════════════════════════════════════════════════════
     SECTION 5: ARSITEKTUR 4-LAYER IT LOGISTIK
     ═══════════════════════════════════════════════════════════════════════════ -->
<section class="py-16 lg:py-24 px-6 lg:px-16 2xl:px-32 bg-[#F8FAFC]">
    <div class="max-w-7xl mx-auto space-y-12">
        
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-6">
            <div>
                <span class="text-xs uppercase font-bold tracking-widest text-[#087F8A]">Kerangka Kerja Rekomendasi</span>
                <h2 class="text-3xl lg:text-4xl font-extrabold text-[#0D1C42]">Taksonomi 4-Layer IT Logistik</h2>
            </div>
            <p class="text-sm lg:text-base text-slate-600 max-w-md text-justify">
                Struktur arsitektur modular yang menjamin pemisahan fungsi operasional antara perangkat keras lapangan, jaringan transmisi, logika perangkat lunak, dan format data pertukaran.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- Layer 1 -->
            <div class="p-6 rounded-2xl bg-white border-t-4 border-amber-500 shadow-sm hover:shadow-lg transition-all space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Layer 01 &bull; Fisik</span>
                <h3 class="font-bold text-lg text-[#0D1C42]">Sensing &amp; Capture</h3>
                <p class="text-xs text-slate-600 text-justify leading-relaxed">
                    Pengambilan data fisik otomatis tanpa entri manual melalui RFID UHF, Barcode Scanner 2D, Dual-View X-Ray, dan timbangan digital.
                </p>
                <div class="pt-2 flex flex-wrap gap-1.5">
                    <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-700">RFID UHF</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-700">X-Ray CSD</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-700">Scale 15T</span>
                </div>
            </div>

            <!-- Layer 2 -->
            <div class="p-6 rounded-2xl bg-white border-t-4 border-[#0CA1AF] shadow-sm hover:shadow-lg transition-all space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider text-[#0CA1AF]">Layer 02 &bull; Jaringan</span>
                <h3 class="font-bold text-lg text-[#0D1C42]">Network Connectivity</h3>
                <p class="text-xs text-slate-600 text-justify leading-relaxed">
                    Transmisi data berlatensi rendah dan tahan interferensi melalui Industrial Wi-Fi 6, kabel Ethernet, serial RS-232, dan koneksi seluler 4G/5G.
                </p>
                <div class="pt-2 flex flex-wrap gap-1.5">
                    <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-700">Wi-Fi 6</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-700">RS-232</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-700">Ethernet</span>
                </div>
            </div>

            <!-- Layer 3 -->
            <div class="p-6 rounded-2xl bg-white border-t-4 border-[#087F8A] shadow-sm hover:shadow-lg transition-all space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider text-[#087F8A]">Layer 03 &bull; Aplikasi</span>
                <h3 class="font-bold text-lg text-[#0D1C42]">Software Engine</h3>
                <p class="text-xs text-slate-600 text-justify leading-relaxed">
                    Pusat pemrosesan keputusan operasional mencakup Cargo Management System (CMS), Truck Appointment (TAS), dan modul kalkulasi Weight &amp; Balance.
                </p>
                <div class="pt-2 flex flex-wrap gap-1.5">
                    <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-700">CMS e-AWB</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-700">TMS Gateway</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-700">W&amp;B Loadplan</span>
                </div>
            </div>

            <!-- Layer 4 -->
            <div class="p-6 rounded-2xl bg-white border-t-4 border-[#014D54] shadow-sm hover:shadow-lg transition-all space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider text-[#014D54]">Layer 04 &bull; Protokol</span>
                <h3 class="font-bold text-lg text-[#0D1C42]">Integration &amp; GS1</h3>
                <p class="text-xs text-slate-600 text-justify leading-relaxed">
                    Standar pertukaran universal berbasis REST API dengan payload JSON, mematuhi format GS1 SSCC (00), GTIN (01), dan protokol e-AWB IATA.
                </p>
                <div class="pt-2 flex flex-wrap gap-1.5">
                    <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-700">GS1 SSCC</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-700">REST JSON</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-700">IATA Standard</span>
                </div>
            </div>

        </div>

    </div>
</section>


<!-- ═══════════════════════════════════════════════════════════════════════════
     SECTION 6: TIM KONSULTAN (CARGOPINNACLE CONSULTING — NO NIM)
     - Bersih, profesional, nama perusahaan konsultan, tanpa NIM sesuai instruksi
     ═══════════════════════════════════════════════════════════════════════════ -->
<section class="py-16 lg:py-24 px-6 lg:px-16 2xl:px-32 bg-white">
    <div class="max-w-7xl mx-auto space-y-12">
        
        <div class="text-center max-w-2xl mx-auto space-y-3">
            <span class="text-xs uppercase font-bold tracking-widest text-[#087F8A]">Penasihat Teknis Proyek</span>
            <h2 class="text-3xl lg:text-4xl font-extrabold text-[#0D1C42]"><?= $companyName ?></h2>
            <p class="text-sm lg:text-base text-slate-600">
                Susunan tim spesialis penasihat arsitektur dan integrasi sistem digital Air Cargo &amp; Intermodal Terminal.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            
            <!-- Member 1: Lead Architect -->
            <div class="p-8 rounded-2xl bg-[#F8FAFC] border border-gray-200 text-center space-y-4 hover:border-[#0CA1AF] hover:shadow-xl transition-all group">
                <div class="w-20 h-20 rounded-full mx-auto bg-gradient-to-tr from-[#0D1C42] to-[#087F8A] text-white flex items-center justify-center font-black text-2xl shadow-md group-hover:scale-105 transition-transform">
                    RP
                </div>
                <div>
                    <h4 class="font-extrabold text-lg text-[#0D1C42]">Raden Panji Atha Fairuz</h4>
                    <span class="text-xs font-bold text-[#087F8A] block uppercase tracking-wider mt-1">Lead System Architect</span>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Penanggung jawab arsitektur makro sistem, perancangan To-Be flow, dan kepatuhan standar internasional IATA/ICAO.
                </p>
            </div>

            <!-- Member 2: Data Integration -->
            <div class="p-8 rounded-2xl bg-[#F8FAFC] border border-gray-200 text-center space-y-4 hover:border-[#0CA1AF] hover:shadow-xl transition-all group">
                <div class="w-20 h-20 rounded-full mx-auto bg-gradient-to-tr from-[#087F8A] to-[#0CA1AF] text-white flex items-center justify-center font-black text-2xl shadow-md group-hover:scale-105 transition-transform">
                    MF
                </div>
                <div>
                    <h4 class="font-extrabold text-lg text-[#0D1C42]">Muhammad Fathir Septianto</h4>
                    <span class="text-xs font-bold text-[#087F8A] block uppercase tracking-wider mt-1">Data Integration Specialist</span>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Spesialis struktur data relasional MySQL, protokol pertukaran REST API JSON, dan standardisasi kode GS1 SSCC 18-digit.
                </p>
            </div>

            <!-- Member 3: Software Process -->
            <div class="p-8 rounded-2xl bg-[#F8FAFC] border border-gray-200 text-center space-y-4 hover:border-[#0CA1AF] hover:shadow-xl transition-all group">
                <div class="w-20 h-20 rounded-full mx-auto bg-gradient-to-tr from-[#0CA1AF] to-emerald-600 text-white flex items-center justify-center font-black text-2xl shadow-md group-hover:scale-105 transition-transform">
                    RT
                </div>
                <div>
                    <h4 class="font-extrabold text-lg text-[#0D1C42]">Riepka Tiara</h4>
                    <span class="text-xs font-bold text-[#087F8A] block uppercase tracking-wider mt-1">Software Process Specialist</span>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Penyusun proses bisnis operasional kargo, perancangan antarmuka simulasi, dan integrasi modul CMS-TAS.
                </p>
            </div>

            <!-- Member 4: Hardware & QA -->
            <div class="p-8 rounded-2xl bg-[#F8FAFC] border border-gray-200 text-center space-y-4 hover:border-[#0CA1AF] hover:shadow-xl transition-all group">
                <div class="w-20 h-20 rounded-full mx-auto bg-gradient-to-tr from-[#0D1C42] to-amber-600 text-white flex items-center justify-center font-black text-2xl shadow-md group-hover:scale-105 transition-transform">
                    NA
                </div>
                <div>
                    <h4 class="font-extrabold text-lg text-[#0D1C42]">Nessa Amanda Ghassani</h4>
                    <span class="text-xs font-bold text-[#087F8A] block uppercase tracking-wider mt-1">Hardware &amp; QA Specialist</span>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Kajian spesifikasi teknis perangkat sensor (RFID, X-Ray, timbangan digital) serta pengujian validasi alur sistem (QA).
                </p>
            </div>

        </div>

    </div>
</section>


<!-- ═══════════════════════════════════════════════════════════════════════════
     SECTION 7: BERITA & PUBLIKASI (100% PERSIS INJOURNEY NEWS CARDS)
     ═══════════════════════════════════════════════════════════════════════════ -->
<section class="py-16 lg:py-24 px-6 lg:px-16 2xl:px-32 bg-[#F8FAFC]">
    <div class="max-w-7xl mx-auto space-y-12">
        
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4 border-b border-gray-200 pb-6">
            <div>
                <span class="text-xs uppercase font-bold tracking-widest text-[#087F8A]">Publikasi &bull; Berita Terkini</span>
                <h2 class="text-3xl lg:text-4xl font-extrabold text-[#0D1C42]">Wawasan Industri &amp; Regulasi</h2>
            </div>
            <a href="blueprint.php" class="text-xs font-bold text-[#087F8A] hover:text-[#0CA1AF] flex items-center gap-1">
                <span>Lihat Seluruh Publikasi</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- Article 1 -->
            <div class="bg-white rounded-2xl overflow-hidden border border-gray-200 shadow-sm hover:shadow-xl transition-all flex flex-col justify-between group">
                <div class="relative h-48 overflow-hidden bg-slate-800">
                    <img src="https://injourneyairports.id/news/uploads/news/img_20260906_112331.jpg_20260915_091815_7de0d580.jpeg" alt="Bandara Soekarno Hatta" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <span class="absolute top-4 left-4 px-3 py-1 rounded-full bg-[#087F8A] text-white text-[10px] font-bold uppercase tracking-wider">
                        Operasional Bandara
                    </span>
                </div>
                <div class="p-6 space-y-3 flex-grow flex flex-col justify-between">
                    <div class="space-y-2">
                        <span class="text-xs text-gray-400 font-semibold block">08 September 2026</span>
                        <h4 class="font-bold text-base text-[#0D1C42] group-hover:text-[#087F8A] transition-colors line-clamp-2">
                            Kesiapan Fasilitas Sisi Udara dan Landside Kargo di Tiga Bandara Utama InJourney Airports
                        </h4>
                        <p class="text-xs text-slate-500 line-clamp-3 text-justify">
                            Pemulihan operasional kargo dan lalu lintas penerbangan dilakukan bertahap dengan penerapan Air Traffic Flow Management (ATFM) dan koordinasi sistem keselamatan aviasi.
                        </p>
                    </div>
                    <div class="pt-4 border-t border-gray-100">
                        <a href="blueprint.php" class="text-xs font-bold text-[#087F8A] flex items-center gap-1.5">
                            <span>Baca Selengkapnya</span>
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Article 2 -->
            <div class="bg-white rounded-2xl overflow-hidden border border-gray-200 shadow-sm hover:shadow-xl transition-all flex flex-col justify-between group">
                <div class="relative h-48 overflow-hidden bg-[#014D54] flex items-center justify-center p-6 text-white text-center">
                    <div class="space-y-2">
                        <i class="fa-solid fa-qrcode text-4xl text-[#0CA1AF]"></i>
                        <span class="block text-xs font-bold text-teal-200 uppercase tracking-widest">Standar Internasional</span>
                    </div>
                    <span class="absolute top-4 left-4 px-3 py-1 rounded-full bg-[#04AFBF] text-white text-[10px] font-bold uppercase tracking-wider">
                        GS1 Standard
                    </span>
                </div>
                <div class="p-6 space-y-3 flex-grow flex flex-col justify-between">
                    <div class="space-y-2">
                        <span class="text-xs text-gray-400 font-semibold block">14 Agustus 2026</span>
                        <h4 class="font-bold text-base text-[#0D1C42] group-hover:text-[#087F8A] transition-colors line-clamp-2">
                            Penerapan SSCC 18-Digit sebagai Syarat Mutlak Akselerasi Dwell Time Kargo Udara
                        </h4>
                        <p class="text-xs text-slate-500 line-clamp-3 text-justify">
                            Studi kasus implementasi AIDC membuktikan bahwa eliminasi manual barcode scan mampu menghemat waktu proses penerimaan kargo dari 180 detik menjadi 15 detik per palet.
                        </p>
                    </div>
                    <div class="pt-4 border-t border-gray-100">
                        <a href="blueprint.php" class="text-xs font-bold text-[#087F8A] flex items-center gap-1.5">
                            <span>Baca Selengkapnya</span>
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Article 3 -->
            <div class="bg-white rounded-2xl overflow-hidden border border-gray-200 shadow-sm hover:shadow-xl transition-all flex flex-col justify-between group">
                <div class="relative h-48 overflow-hidden bg-[#0D1C42] flex items-center justify-center p-6 text-white text-center">
                    <div class="space-y-2">
                        <i class="fa-solid fa-scale-balanced text-4xl text-amber-400"></i>
                        <span class="block text-xs font-bold text-amber-200 uppercase tracking-widest">Aviation Safety</span>
                    </div>
                    <span class="absolute top-4 left-4 px-3 py-1 rounded-full bg-amber-600 text-white text-[10px] font-bold uppercase tracking-wider">
                        ICAO &bull; CSD
                    </span>
                </div>
                <div class="p-6 space-y-3 flex-grow flex flex-col justify-between">
                    <div class="space-y-2">
                        <span class="text-xs text-gray-400 font-semibold block">02 Juli 2026</span>
                        <h4 class="font-bold text-base text-[#0D1C42] group-hover:text-[#087F8A] transition-colors line-clamp-2">
                            Korelasi Berat Aktual ULD dengan Titik Keseimbangan Pesawat (Center of Gravity)
                        </h4>
                        <p class="text-xs text-slate-500 line-clamp-3 text-justify">
                            Integrasi data otomatis dari Smart Scale ke sistem kalkulasi loadsheet meminimalisasi risiko kelebihan muatan serta menjamin efisiensi konsumsi bahan bakar armada.
                        </p>
                    </div>
                    <div class="pt-4 border-t border-gray-100">
                        <a href="blueprint.php" class="text-xs font-bold text-[#087F8A] flex items-center gap-1.5">
                            <span>Baca Selengkapnya</span>
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>

        </div>

    </div>
</section>


<!-- ═══════════════════════════════════════════════════════════════════════════
     FOOTER (100% PERSIS INJOURNEY DESKTOP FOOTER DENGAN GRADASI TEAL-CYAN)
     - Gradient: linear-gradient(to right, #0CA1AF, #087F8A, #014D54)
     - Logo Resmi InJourney + Alamat Kantor Pusat Bandara Soekarno Hatta + Call Center 172
     ═══════════════════════════════════════════════════════════════════════════ -->
<footer class="bg-footer-gradient text-white pt-16 pb-10 px-6 lg:px-16 2xl:px-32 select-none">
    <div class="max-w-7xl mx-auto space-y-12">
        
        <!-- 5-Column InJourney Footer Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-8 lg:gap-10 pb-12 border-b border-white/20">
            
            <!-- Col 1: Brand & Advisory Mission -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white text-[#087F8A] flex items-center justify-center font-black text-lg shadow-md">
                        <?= $companyShort ?>
                    </div>
                    <div>
                        <span class="font-extrabold text-xl block leading-tight"><?= $companyName ?></span>
                        <span class="text-xs text-teal-200 block uppercase tracking-wider font-semibold"><?= $companyTagline ?></span>
                    </div>
                </div>
                <p class="text-xs text-white/80 leading-relaxed text-justify max-w-sm">
                    Firma penasihat transformasi digital terminal kargo bandara dan integrasi logistik multimoda. Proyek konsultansi akademik di bawah bimbingan Institut Transportasi &amp; Logistik (ITL) Trisakti, Jakarta.
                </p>
                <div class="flex items-center gap-3 pt-2">
                    <span class="px-2.5 py-1 rounded bg-white/10 text-[10px] font-semibold tracking-wider">PHP 8.2</span>
                    <span class="px-2.5 py-1 rounded bg-white/10 text-[10px] font-semibold tracking-wider">MySQL 8</span>
                    <span class="px-2.5 py-1 rounded bg-white/10 text-[10px] font-semibold tracking-wider">GS1 SSCC</span>
                    <span class="px-2.5 py-1 rounded bg-white/10 text-[10px] font-semibold tracking-wider">IATA e-AWB</span>
                </div>
            </div>

            <!-- Col 2: Navigation Links -->
            <div class="space-y-3">
                <h4 class="font-bold text-sm tracking-wider uppercase text-teal-200">Navigasi Utama</h4>
                <ul class="space-y-2 text-xs text-white/85">
                    <li><a href="index.php" class="hover:text-white transition-colors">Beranda Konsultansi</a></li>
                    <li><a href="dashboard.php" class="hover:text-white transition-colors">Dashboard Eksekutif</a></li>
                    <li><a href="simulation.php" class="hover:text-white transition-colors">Simulasi 7-Tahap PoC</a></li>
                    <li><a href="database_viewer.php" class="hover:text-white transition-colors">Database Viewer Live</a></li>
                    <li><a href="blueprint.php" class="hover:text-white transition-colors">Blueprint Dokumen SRS</a></li>
                    <li><a href="panduan.php" class="text-amber-300 hover:text-amber-100 font-bold">Panduan Prompting Tim</a></li>
                </ul>
            </div>

            <!-- Col 3: Standar & Regulasi -->
            <div class="space-y-3">
                <h4 class="font-bold text-sm tracking-wider uppercase text-teal-200">Standar &amp; Ekosistem</h4>
                <ul class="space-y-2 text-xs text-white/85">
                    <li><span class="text-white/60">GS1 SSCC 18-Digit (AI 00)</span></li>
                    <li><span class="text-white/60">IATA Cargo Services (e-AWB)</span></li>
                    <li><span class="text-white/60">ICAO Security CSD Protocol</span></li>
                    <li><span class="text-white/60">OIML R76 Weighing Standard</span></li>
                    <li><span class="text-white/60">Truck Appointment System (TAS)</span></li>
                    <li><span class="text-white/60">REST API Integration Spec</span></li>
                </ul>
            </div>

            <!-- Col 4: Official InJourney Address & Contact Center -->
            <div class="space-y-3">
                <h4 class="font-bold text-sm tracking-wider uppercase text-teal-200">Kontak &amp; Alamat</h4>
                <div class="space-y-1.5 text-xs text-white/85">
                    <p class="font-semibold text-white">InJourney Airports Center</p>
                    <p>Bandar Udara Internasional Soekarno-Hatta</p>
                    <p class="text-white/70">Jl. M2, Pajang, Kec. Benda, Kota Tangerang, Banten 15126</p>
                    <div class="pt-2">
                        <span class="block text-white/60 text-[10px] uppercase font-bold">Layanan Contact Center</span>
                        <span class="text-lg font-extrabold text-amber-300">172</span>
                        <span class="block text-[10px] text-white/70">(021) 1500-138 / WA: 0811984138</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Copyright Row -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-white/70 text-center sm:text-left">
            <div>
                &copy; <?= date('Y') ?> <strong class="text-white"><?= $companyName ?></strong>. Proyek Akademik ITL Trisakti &bull; Topik 6: Air Cargo &amp; Intermodal Terminal.
            </div>
            <div class="flex items-center gap-4 text-[11px]">
                <span>Desain Terinspirasi dari InJourney Airports</span>
                <span>&bull;</span>
                <a href="panduan.php" class="text-amber-300 hover:underline">Panduan Sistem</a>
            </div>
        </div>

    </div>
</footer>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SCRIPTS: HEADER SCROLL DYNAMICS, VIDEO AUTOPLAY & SELECTOR ACCORDION
     ═══════════════════════════════════════════════════════════════════════════ -->
<script>
    // Pastikan video langsung bermain begitu halaman dibuka
    const heroVideo = document.getElementById('hero-video');
    if (heroVideo) {
        heroVideo.play().catch(e => {
            console.log('Video autoplay prevented, clicking or interacting will resume playback', e);
        });
    }

    // Header Dynamic Scroll Behavior (Sama persis dengan InJourney: transparent di atas, solid putih saat scroll)
    const header = document.getElementById('main-header');
    const brandTitle = document.getElementById('brand-title');
    const brandSubtitle = document.getElementById('brand-subtitle');
    const navItems = document.querySelectorAll('.nav-item');
    const langId = document.getElementById('lang-id');
    const langEn = document.getElementById('lang-en');
    const mobileToggle = document.getElementById('mobile-toggle');
    const mobileDrawer = document.getElementById('mobile-drawer');

    function updateHeaderOnScroll() {
        const scrolled = window.scrollY > 160;
        if (scrolled) {
            header.classList.remove('bg-transparent', 'text-white');
            header.classList.add('bg-white', 'text-black', 'shadow-md', 'py-3');
            
            brandTitle.classList.remove('text-white', 'hero-desc-shadow');
            brandTitle.classList.add('text-[#0D1C42]');
            
            brandSubtitle.classList.remove('text-teal-200', 'hero-desc-shadow');
            brandSubtitle.classList.add('text-[#087F8A]');

            navItems.forEach(el => {
                el.classList.remove('link-underline-white', 'hero-desc-shadow');
                el.classList.add('link-underline-black');
            });

            langId.classList.remove('border-white', 'bg-white', 'text-black');
            langId.classList.add('border-black', 'bg-black', 'text-white');
            langEn.classList.remove('border-white', 'text-white');
            langEn.classList.add('border-black', 'text-black');
            mobileToggle.classList.add('text-black');
        } else {
            header.classList.add('bg-transparent', 'text-white');
            header.classList.remove('bg-white', 'text-black', 'shadow-md', 'py-3');
            
            brandTitle.classList.add('text-white', 'hero-desc-shadow');
            brandTitle.classList.remove('text-[#0D1C42]');
            
            brandSubtitle.classList.add('text-teal-200', 'hero-desc-shadow');
            brandSubtitle.classList.remove('text-[#087F8A]');

            navItems.forEach(el => {
                el.classList.add('link-underline-white', 'hero-desc-shadow');
                el.classList.remove('link-underline-black');
            });

            langId.classList.add('border-white', 'bg-white', 'text-black');
            langId.classList.remove('border-black', 'bg-black', 'text-white');
            langEn.classList.add('border-white', 'text-white');
            langEn.classList.remove('border-black', 'text-black');
            mobileToggle.classList.remove('text-black');
        }
    }

    window.addEventListener('scroll', updateHeaderOnScroll, { passive: true });
    updateHeaderOnScroll();

    // Mobile Drawer Toggle
    mobileToggle.addEventListener('click', () => {
        mobileDrawer.classList.toggle('-translate-y-full');
    });
    mobileDrawer.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', () => mobileDrawer.classList.add('-translate-y-full'));
    });

    // InJourney Style Simulator Dropdown Trigger
    const trigger = document.getElementById('selector-trigger');
    const dropdown = document.getElementById('selector-dropdown');
    const chevron = document.getElementById('selector-chevron');

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = !dropdown.classList.contains('hidden');
        if (isOpen) {
            dropdown.classList.add('hidden');
            chevron.classList.remove('rotate-180');
        } else {
            dropdown.classList.remove('hidden');
            chevron.classList.add('rotate-180');
        }
    });

    document.addEventListener('click', () => {
        if (!dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
            chevron.classList.remove('rotate-180');
        }
    });
</script>

</body>
</html>
