<?php
// index.php
// Landing Page Konsultan — Rekomendasi Sistem Air Cargo & Intermodal Terminal
require_once __DIR__ . '/config/database.php';
$pageTitle = 'Beranda Konsultansi — Solusi Air Cargo & Intermodal Terminal';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="relative overflow-hidden pt-12 pb-20 border-b border-slate-800">
    <!-- Ambient glowing backgrounds -->
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[350px] bg-sky-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-1/3 right-10 w-96 h-96 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        
        <div class="text-center max-w-3xl mx-auto mb-10">
            <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-sky-950/80 border border-sky-500/40 text-sky-400 text-xs font-semibold mb-6 shadow-sm">
                <i class="fa-solid fa-certificate text-sky-400"></i>
                <span>Proposal Konsultansi & Blueprint Integrasi — Topik 6</span>
            </div>
            
            <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight leading-tight mb-6">
                Rekomendasi Arsitektur Digital & Integrasi Data <br>
                <span class="bg-gradient-to-r from-sky-400 via-indigo-300 to-sky-200 bg-clip-text text-transparent">
                    Air Cargo & Intermodal Terminal
                </span>
            </h1>

            <p class="text-sm sm:text-base text-slate-300 leading-relaxed font-normal">
                Panduan komprehensif bagi pengelola terminal kargo bandara untuk mentransformasi alur kargo udara secara terpadu—dari gerbang kedatangan truk (darat) hingga pemuatan ke perut pesawat (udara) berbasis standar internasional <strong>GS1 SSCC</strong> dan arsitektur <strong>4-Layer IT Logistik</strong>.
            </p>

            <!-- Call to Actions -->
            <div class="flex flex-wrap items-center justify-center gap-4 mt-8">
                <a href="simulation.php" class="px-6 py-3 rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-semibold text-sm shadow-xl shadow-sky-500/25 transition-all transform hover:-translate-y-0.5 flex items-center">
                    <i class="fa-solid fa-microchip mr-2"></i>
                    <span>Uji Coba Simulasi PoC</span>
                </a>
                <a href="dashboard.php" class="px-6 py-3 rounded-xl bg-slate-800/90 hover:bg-slate-700/90 text-slate-200 font-semibold text-sm border border-slate-700 transition-all flex items-center">
                    <i class="fa-solid fa-chart-line mr-2 text-sky-400"></i>
                    <span>Buka Operations Dashboard</span>
                </a>
                <a href="blueprint.php" class="px-6 py-3 rounded-xl bg-slate-900/90 hover:bg-slate-800 text-indigo-300 font-semibold text-sm border border-indigo-700/50 transition-all flex items-center">
                    <i class="fa-solid fa-book mr-2"></i>
                    <span>Baca Dokumen Rekomendasi (SRS)</span>
                </a>
            </div>
        </div>

        <!-- Quick Highlight Numbers -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-5xl mx-auto pt-6">
            <div class="glass-panel p-4 rounded-xl border border-slate-700/60 text-center">
                <div class="text-2xl font-extrabold text-sky-400 font-mono">4 Layers</div>
                <div class="text-xs text-slate-400 mt-1">Taksonomi IT Logistik</div>
            </div>
            <div class="glass-panel p-4 rounded-xl border border-slate-700/60 text-center">
                <div class="text-2xl font-extrabold text-indigo-400 font-mono">7 Tahapan</div>
                <div class="text-xs text-slate-400 mt-1">Aliran Perpindahan Data</div>
            </div>
            <div class="glass-panel p-4 rounded-xl border border-slate-700/60 text-center">
                <div class="text-2xl font-extrabold text-emerald-400 font-mono">18 Digit</div>
                <div class="text-xs text-slate-400 mt-1">Standar GS1 SSCC Paspor Kargo</div>
            </div>
            <div class="glass-panel p-4 rounded-xl border border-slate-700/60 text-center">
                <div class="text-2xl font-extrabold text-amber-400 font-mono">&lt; 45 Menit</div>
                <div class="text-xs text-slate-400 mt-1">Target Dwell Time Truk-ke-ULD</div>
            </div>
        </div>

    </div>
</section>

<!-- Section: Problem Statement (Kondisi Klien Saat Ini / As-Is) -->
<section class="py-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center max-w-2xl mx-auto mb-12">
        <span class="text-xs font-bold text-rose-400 uppercase tracking-widest bg-rose-950/60 px-3 py-1 rounded-full border border-rose-800/50">
            Tantangan Klien (As-Is Bottlenecks)
        </span>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-white mt-3">
            Mengapa Terminal Kargo Klien Membutuhkan Sistem Ini?
        </h2>
        <p class="text-xs sm:text-sm text-slate-400 mt-2">
            Identifikasi masalah operasional di lapangan yang menyebabkan keterlambatan, inefisiensi, dan risiko keselamatan penerbangan.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Card 1 -->
        <div class="glass-panel p-6 rounded-2xl border border-slate-800 hover:border-rose-500/40 transition-colors">
            <div class="w-12 h-12 rounded-xl bg-rose-950/60 border border-rose-500/30 text-rose-400 flex items-center justify-center text-xl mb-4">
                <i class="fa-solid fa-truck-ramp-box"></i>
            </div>
            <h3 class="text-sm font-bold text-white mb-2">Kemacetan Dock & Antrean Truk</h3>
            <p class="text-xs text-slate-400 leading-relaxed">
                Truk tiba tanpa reservasi slot waktu (time-slot booking), memicu dwell time hingga 4 jam di gerbang masuk terminal kargo bandara.
            </p>
        </div>

        <!-- Card 2 -->
        <div class="glass-panel p-6 rounded-2xl border border-slate-800 hover:border-amber-500/40 transition-colors">
            <div class="w-12 h-12 rounded-xl bg-amber-950/60 border border-amber-500/30 text-amber-400 flex items-center justify-center text-xl mb-4">
                <i class="fa-solid fa-keyboard"></i>
            </div>
            <h3 class="text-sm font-bold text-white mb-2">Entri Manual Rawan Human Error</h3>
            <p class="text-xs text-slate-400 leading-relaxed">
                Pencatatan koli manual memicu salah salin nomor lot/batch dan AWB, mengakibatkan data silo antara pengirim, gudang, dan maskapai.
            </p>
        </div>

        <!-- Card 3 -->
        <div class="glass-panel p-6 rounded-2xl border border-slate-800 hover:border-sky-500/40 transition-colors">
            <div class="w-12 h-12 rounded-xl bg-sky-950/60 border border-sky-500/30 text-sky-400 flex items-center justify-center text-xl mb-4">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h3 class="text-sm font-bold text-white mb-2">Risiko Keamanan AVSEC Terlambat</h3>
            <p class="text-xs text-slate-400 leading-relaxed">
                Deteksi barang berbahaya (Dangerous Goods/Lithium) yang terlambat dapat menggagalkan pemuatan di saat pesawat sudah hampir *pushback*.
            </p>
        </div>

        <!-- Card 4 -->
        <div class="glass-panel p-6 rounded-2xl border border-slate-800 hover:border-indigo-500/40 transition-colors">
            <div class="w-12 h-12 rounded-xl bg-indigo-950/60 border border-indigo-500/30 text-indigo-400 flex items-center justify-center text-xl mb-4">
                <i class="fa-solid fa-scale-unbalanced"></i>
            </div>
            <h3 class="text-sm font-bold text-white mb-2">Ketidakcocokan Weight & Balance</h3>
            <p class="text-xs text-slate-400 leading-relaxed">
                Selisih berat timbangan fisik dan deklarasi dokumen e-AWB membahayakan titik keseimbangan pesawat (*Center of Gravity*).
            </p>
        </div>
    </div>
</section>

<!-- Section: Taksonomi 4-Layer IT Logistik (Rekomendasi Konsultan) -->
<section class="py-16 bg-slate-900/40 border-y border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12">
            <div>
                <span class="text-xs font-bold text-sky-400 uppercase tracking-widest bg-sky-950/60 px-3 py-1 rounded-full border border-sky-800/50">
                    Kerangka Arsitektur Terpadu
                </span>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-white mt-3">
                    Rekomendasi Taksonomi 4-Layer IT Logistik
                </h2>
                <p class="text-xs sm:text-sm text-slate-400 mt-2">
                    Struktur sistem bertingkat yang kami sarankan untuk menghubungkan dunia fisik bandara dengan komputasi awan.
                </p>
            </div>
            <a href="blueprint.php" class="mt-4 md:mt-0 inline-flex items-center text-xs font-semibold text-sky-400 hover:text-sky-300">
                <span>Rincian Spesifikasi Perangkat</span>
                <i class="fa-solid fa-arrow-right ml-1.5"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- Layer 1 -->
            <div class="glass-panel p-6 rounded-2xl border border-sky-500/20 relative">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-mono font-bold text-sky-400 px-2 py-0.5 rounded bg-sky-950 border border-sky-800">Layer 1</span>
                    <i class="fa-solid fa-qrcode text-sky-400 text-lg"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Sensing & Data Capture</h3>
                <p class="text-xs text-slate-400 mb-4">Pengumpulan data fisik otomatis dari lapangan kargo tanpa entri manual.</p>
                <div class="space-y-2 text-xs">
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <strong class="text-slate-200 block mb-0.5">High-Speed Scanner</strong>
                        <span class="text-slate-400 text-[11px]">Barcode & RFID UHF tunnel (2-3 m/s)</span>
                    </div>
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <strong class="text-slate-200 block mb-0.5">Dual-View X-Ray</strong>
                        <span class="text-slate-400 text-[11px]">Terowongan 180x180 cm, 320kV generator</span>
                    </div>
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <strong class="text-slate-200 block mb-0.5">Smart ULD Floor Scale</strong>
                        <span class="text-slate-400 text-[11px]">Kapasitas 15 Ton, akurasi ±1 kg</span>
                    </div>
                </div>
            </div>

            <!-- Layer 2 -->
            <div class="glass-panel p-6 rounded-2xl border border-indigo-500/20 relative">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-mono font-bold text-indigo-400 px-2 py-0.5 rounded bg-indigo-950 border border-indigo-800">Layer 2</span>
                    <i class="fa-solid fa-wifi text-indigo-400 text-lg"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Network & Connectivity</h3>
                <p class="text-xs text-slate-400 mb-4">Transmisi data latensi rendah dari peralatan gudang ke server pusat.</p>
                <div class="space-y-2 text-xs">
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <strong class="text-slate-200 block mb-0.5">Industrial Wi-Fi 6</strong>
                        <span class="text-slate-400 text-[11px]">Koneksi PDA & Forklift di area steril</span>
                    </div>
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <strong class="text-slate-200 block mb-0.5">RS-232 / TCP Socket</strong>
                        <span class="text-slate-400 text-[11px]">Interface serial timbangan lantai & scanner</span>
                    </div>
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <strong class="text-slate-200 block mb-0.5">4G/5G Cellular</strong>
                        <span class="text-slate-400 text-[11px]">Telematika armada truk & tracking ETA</span>
                    </div>
                </div>
            </div>

            <!-- Layer 3 -->
            <div class="glass-panel p-6 rounded-2xl border border-emerald-500/20 relative">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-mono font-bold text-emerald-400 px-2 py-0.5 rounded bg-emerald-950 border border-emerald-800">Layer 3</span>
                    <i class="fa-solid fa-server text-emerald-400 text-lg"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Application & Software</h3>
                <p class="text-xs text-slate-400 mb-4">Mesin pengolah data operasional kargo dan pengambilan keputusan.</p>
                <div class="space-y-2 text-xs">
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <strong class="text-slate-200 block mb-0.5">CMS (Cargo Management)</strong>
                        <span class="text-slate-400 text-[11px]">Master e-AWB, lokasi rak, mutasi kargo</span>
                    </div>
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <strong class="text-slate-200 block mb-0.5">AVSEC Security System</strong>
                        <span class="text-slate-400 text-[11px]">Screening kargo & penerbitan sertifikat CSD</span>
                    </div>
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <strong class="text-slate-200 block mb-0.5">Weight & Balance Engine</strong>
                        <span class="text-slate-400 text-[11px]">Perhitungan Center of Gravity & Loadsheet</span>
                    </div>
                </div>
            </div>

            <!-- Layer 4 -->
            <div class="glass-panel p-6 rounded-2xl border border-amber-500/20 relative">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-mono font-bold text-amber-400 px-2 py-0.5 rounded bg-amber-950 border border-amber-800">Layer 4</span>
                    <i class="fa-solid fa-network-wired text-amber-400 text-lg"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Integration & Data Protocol</h3>
                <p class="text-xs text-slate-400 mb-4">Standar format data global agar seluruh sistem saling memahami data.</p>
                <div class="space-y-2 text-xs">
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <strong class="text-slate-200 block mb-0.5">Standar GS1 SSCC (00)</strong>
                        <span class="text-slate-400 text-[11px]">18-digit identifier unit logistik</span>
                    </div>
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <strong class="text-slate-200 block mb-0.5">RESTful API & JSON</strong>
                        <span class="text-slate-400 text-[11px]">Payload terstruktur standar industri</span>
                    </div>
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <strong class="text-slate-200 block mb-0.5">Webhooks & EDI</strong>
                        <span class="text-slate-400 text-[11px]">Notifikasi real-time & integrasi Bea Cukai</span>
                    </div>
                </div>
            </div>

        </div>

    </div>
</section>

<!-- Section: Alur Rekomendasi 7 Tahapan -->
<section class="py-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center max-w-2xl mx-auto mb-12">
        <span class="text-xs font-bold text-indigo-400 uppercase tracking-widest bg-indigo-950/60 px-3 py-1 rounded-full border border-indigo-800/50">
            Alur Operasional To-Be
        </span>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-white mt-3">
            7 Tahapan Aliran Perpindahan Data Kargo
        </h2>
        <p class="text-xs sm:text-sm text-slate-400 mt-2">
            Rangkaian proses yang kami rekomendasikan kepada klien, divisualisasikan dalam sistem simulasi kami.
        </p>
    </div>

    <!-- Stepper Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-3">
        
        <!-- Step 1 -->
        <div class="glass-panel p-4 rounded-xl border border-slate-800 hover:border-sky-500/50 transition-all flex flex-col justify-between">
            <div>
                <span class="w-6 h-6 rounded-full bg-sky-900 text-sky-300 font-bold text-xs flex items-center justify-center mb-2">1</span>
                <h4 class="text-xs font-bold text-white mb-1">Kedatangan Truk</h4>
                <p class="text-[11px] text-slate-400">Time-slot booking & alokasi nomor dock melalui TAS/TMS.</p>
            </div>
            <span class="text-[10px] text-sky-400 font-mono mt-3">TMS / TAS</span>
        </div>

        <!-- Step 2 -->
        <div class="glass-panel p-4 rounded-xl border border-slate-800 hover:border-sky-500/50 transition-all flex flex-col justify-between">
            <div>
                <span class="w-6 h-6 rounded-full bg-sky-900 text-sky-300 font-bold text-xs flex items-center justify-center mb-2">2</span>
                <h4 class="text-xs font-bold text-white mb-1">Scan RFID Gate</h4>
                <p class="text-[11px] text-slate-400">Pembacaan massal SSCC (18 digit), GTIN, Batch, & Expiry.</p>
            </div>
            <span class="text-[10px] text-sky-400 font-mono mt-3">RFID UHF / Barcode</span>
        </div>

        <!-- Step 3 -->
        <div class="glass-panel p-4 rounded-xl border border-slate-800 hover:border-sky-500/50 transition-all flex flex-col justify-between">
            <div>
                <span class="w-6 h-6 rounded-full bg-sky-900 text-sky-300 font-bold text-xs flex items-center justify-center mb-2">3</span>
                <h4 class="text-xs font-bold text-white mb-1">Registrasi CMS</h4>
                <p class="text-[11px] text-slate-400">Pencocokan e-AWB, verifikasi consignee & destinasi kargo.</p>
            </div>
            <span class="text-[10px] text-sky-400 font-mono mt-3">CMS Master e-AWB</span>
        </div>

        <!-- Step 4 -->
        <div class="glass-panel p-4 rounded-xl border border-slate-800 hover:border-sky-500/50 transition-all flex flex-col justify-between">
            <div>
                <span class="w-6 h-6 rounded-full bg-sky-900 text-sky-300 font-bold text-xs flex items-center justify-center mb-2">4</span>
                <h4 class="text-xs font-bold text-white mb-1">X-Ray AVSEC</h4>
                <p class="text-[11px] text-slate-400">Screening sinar-X Dual-View (Status CLEARED atau SUSPECT).</p>
            </div>
            <span class="text-[10px] text-sky-400 font-mono mt-3">AVSEC & CSD Issued</span>
        </div>

        <!-- Step 5 -->
        <div class="glass-panel p-4 rounded-xl border border-slate-800 hover:border-sky-500/50 transition-all flex flex-col justify-between">
            <div>
                <span class="w-6 h-6 rounded-full bg-sky-900 text-sky-300 font-bold text-xs flex items-center justify-center mb-2">5</span>
                <h4 class="text-xs font-bold text-white mb-1">Penimbangan</h4>
                <p class="text-[11px] text-slate-400">Pengukuran berat aktual di timbangan lantai industri ULD.</p>
            </div>
            <span class="text-[10px] text-sky-400 font-mono mt-3">Smart ULD Scale</span>
        </div>

        <!-- Step 6 -->
        <div class="glass-panel p-4 rounded-xl border border-slate-800 hover:border-sky-500/50 transition-all flex flex-col justify-between">
            <div>
                <span class="w-6 h-6 rounded-full bg-sky-900 text-sky-300 font-bold text-xs flex items-center justify-center mb-2">6</span>
                <h4 class="text-xs font-bold text-white mb-1">Build-Up ULD</h4>
                <p class="text-[11px] text-slate-400">Konsolidasi ke kontainer (AKE/PMC) & kalkulasi CoG pesawat.</p>
            </div>
            <span class="text-[10px] text-sky-400 font-mono mt-3">Weight & Balance</span>
        </div>

        <!-- Step 7 -->
        <div class="glass-panel p-4 rounded-xl border border-slate-800 hover:border-sky-500/50 transition-all flex flex-col justify-between">
            <div>
                <span class="w-6 h-6 rounded-full bg-sky-900 text-sky-300 font-bold text-xs flex items-center justify-center mb-2">7</span>
                <h4 class="text-xs font-bold text-white mb-1">Loading Pesawat</h4>
                <p class="text-[11px] text-slate-400">Penerbitan Loadsheet final, ramp loading, status DEPARTED.</p>
            </div>
            <span class="text-[10px] text-sky-400 font-mono mt-3">Flight Manifest Final</span>
        </div>

    </div>

    <!-- Banner Call to Action to Simulation -->
    <div class="mt-8 p-6 rounded-2xl bg-gradient-to-r from-sky-950 via-slate-900 to-indigo-950 border border-sky-500/30 flex flex-col md:flex-row items-center justify-between shadow-2xl">
        <div class="mb-4 md:mb-0">
            <h3 class="text-base font-bold text-white">Ingin Melihat Demonstrasi Alur Data Secara Live?</h3>
            <p class="text-xs text-slate-300 mt-1">Gunakan platform simulator kami untuk menguji pertukaran data JSON dari gerbang hingga kokpit.</p>
        </div>
        <a href="simulation.php" class="px-5 py-2.5 rounded-xl bg-sky-500 hover:bg-sky-400 text-white font-bold text-xs transition-all shadow-lg shadow-sky-500/25 flex items-center">
            <span>Buka Simulator PoC Interaktif</span>
            <i class="fa-solid fa-arrow-right ml-2"></i>
        </a>
    </div>

</section>

<!-- Section: Tim Konsultan Kelompok 1 -->
<section class="py-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 border-t border-slate-800">
    <div class="text-center max-w-2xl mx-auto mb-12">
        <span class="text-xs font-bold text-emerald-400 uppercase tracking-widest bg-emerald-950/60 px-3 py-1 rounded-full border border-emerald-800/50">
            Profil Tim Konsultan
        </span>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-white mt-3">
            Tim Advisory Konsultansi Kelompok 1
        </h2>
        <p class="text-xs sm:text-sm text-slate-400 mt-2">
            Mahasiswa Program Studi S1 Logistik, Fakultas Sistem Transportasi dan Logistik — ITL Trisakti.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <div class="glass-panel p-5 rounded-2xl border border-slate-800 text-center">
            <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-sky-600 to-indigo-600 text-white flex items-center justify-center text-xl font-bold mx-auto mb-3 shadow-lg shadow-sky-500/20">
                RP
            </div>
            <h3 class="text-sm font-bold text-white">Raden Panji Atha Firjatullah</h3>
            <p class="text-xs text-sky-400 font-mono mt-0.5">22D507001024</p>
            <div class="mt-3 pt-3 border-t border-slate-800/80">
                <span class="text-[11px] px-2 py-0.5 rounded bg-sky-950 text-sky-300 font-medium">Lead System Architect</span>
                <p class="text-[11px] text-slate-400 mt-2 leading-relaxed">Perancang arsitektur integrasi sistem, alur kargo udara, dan integrasi 4-layer.</p>
            </div>
        </div>

        <div class="glass-panel p-5 rounded-2xl border border-slate-800 text-center">
            <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center text-xl font-bold mx-auto mb-3 shadow-lg shadow-indigo-500/20">
                MF
            </div>
            <h3 class="text-sm font-bold text-white">Muhammad Fathir Septianto</h3>
            <p class="text-xs text-indigo-400 font-mono mt-0.5">24D507001002</p>
            <div class="mt-3 pt-3 border-t border-slate-800/80">
                <span class="text-[11px] px-2 py-0.5 rounded bg-indigo-950 text-indigo-300 font-medium">Data Integration Specialist</span>
                <p class="text-[11px] text-slate-400 mt-2 leading-relaxed">Spesialis struktur JSON payload, standar GS1 SSCC, dan simulasi PoC.</p>
            </div>
        </div>

        <div class="glass-panel p-5 rounded-2xl border border-slate-800 text-center">
            <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-emerald-600 to-teal-600 text-white flex items-center justify-center text-xl font-bold mx-auto mb-3 shadow-lg shadow-emerald-500/20">
                RT
            </div>
            <h3 class="text-sm font-bold text-white">Riepka Tiara</h3>
            <p class="text-xs text-emerald-400 font-mono mt-0.5">24D507001016</p>
            <div class="mt-3 pt-3 border-t border-slate-800/80">
                <span class="text-[11px] px-2 py-0.5 rounded bg-emerald-950 text-emerald-300 font-medium">Software & Process Specialist</span>
                <p class="text-[11px] text-slate-400 mt-2 leading-relaxed">Flowchart proses bisnis CMS, AVSEC, dan alur integrasi intermodal.</p>
            </div>
        </div>

        <div class="glass-panel p-5 rounded-2xl border border-slate-800 text-center">
            <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-amber-600 to-orange-600 text-white flex items-center justify-center text-xl font-bold mx-auto mb-3 shadow-lg shadow-amber-500/20">
                NA
            </div>
            <h3 class="text-sm font-bold text-white">Nessa Amanda Ghassani</h3>
            <p class="text-xs text-amber-400 font-mono mt-0.5">24D507001025</p>
            <div class="mt-3 pt-3 border-t border-slate-800/80">
                <span class="text-[11px] px-2 py-0.5 rounded bg-amber-950 text-amber-300 font-medium">Hardware & QA Specialist</span>
                <p class="text-[11px] text-slate-400 mt-2 leading-relaxed">Spesifikasi hardware scanner, X-Ray, timbangan ULD, dan kelayakan ROI.</p>
            </div>
        </div>

    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
