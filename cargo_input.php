<?php
// cargo_input.php
// Form Input Data Kargo untuk Simulasi 3D Interaktif
require_once __DIR__ . '/config/database.php';
requireAuth();

$user = getCurrentUser();
$db = getDb();

// Ambil daftar penerbangan aktif untuk dropdown
$flightsStmt = $db->query("SELECT id, flight_number, airline, aircraft_type, destination, departure_time, max_cargo_weight_kg, current_total_weight_kg, status FROM flight_manifests WHERE status IN ('OPEN','LOADING') ORDER BY departure_time ASC");
$flights = $flightsStmt->fetchAll();

// Ambil daftar kargo yang sudah dibuat
$cargosStmt = $db->query("
    SELECT c.*, t.truck_plate, t.dock_slot
    FROM cargo_shipments c
    LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
    ORDER BY c.created_at DESC
    LIMIT 20
");
$existingCargos = $cargosStmt->fetchAll();

$pageTitle = 'Input Data Kargo — Simulasi 3D Terminal';
$bodyClass = 'bg-[#F8FAFC] text-slate-800';
include __DIR__ . '/includes/header.php';

if (!function_exists('getStatusBadgeClass')) {
    function getStatusBadgeClass($status) {
        switch (strtoupper((string)$status)) {
            case 'REGISTERED':
                return 'bg-blue-50 text-blue-700 border-blue-200';
            case 'SCANNED':
                return 'bg-teal-50 text-teal-700 border-teal-200';
            case 'SECURITY_CHECK':
                return 'bg-amber-50 text-amber-700 border-amber-200';
            case 'CLEARED':
                return 'bg-emerald-50 text-emerald-700 border-emerald-200';
            case 'SUSPECT':
                return 'bg-rose-50 text-rose-700 border-rose-300';
            case 'WEIGHED':
                return 'bg-indigo-50 text-indigo-700 border-indigo-200';
            case 'ALLOCATED':
                return 'bg-cyan-50 text-cyan-700 border-cyan-200';
            case 'LOADED':
                return 'bg-purple-50 text-purple-700 border-purple-200';
            default:
                return 'bg-slate-50 text-slate-700 border-slate-200';
        }
    }
}
?>

<div class="relative w-full overflow-x-hidden min-h-screen bg-[#F8FAFC]">

    <!-- ═══════════════════════════════════════════════════════════════════════════
         MOTIF BATIK INJOURNEY RESMI (SISI KIRI & KANAN MENGALIR KE BAWAH)
         Continuous Full-Height InJourney Kawung Flanks — Mengalir Rapi dari Atas ke Bawah
         ═══════════════════════════════════════════════════════════════════════════ -->

    <!-- Kolom Motif Sisi Kiri (Full Height Mengalir ke Bawah, Mirrored Symmetrical) -->
    <div class="absolute inset-y-0 left-0 w-48 sm:w-64 lg:w-80 xl:w-96 pointer-events-none select-none z-0 overflow-hidden" aria-hidden="true">
        <div class="w-full h-full opacity-90 transform -scale-x-100 origin-center"
             style="background-image: url('assets/img/injourney/pattern-green-BTZ3yksm.png'); background-repeat: repeat-y; background-position: right top; background-size: 100% auto;">
        </div>
    </div>

    <!-- Kolom Motif Sisi Kanan (Full Height Mengalir ke Bawah, Natural Orientation) -->
    <div class="absolute inset-y-0 right-0 w-48 sm:w-64 lg:w-80 xl:w-96 pointer-events-none select-none z-0 overflow-hidden" aria-hidden="true">
        <div class="w-full h-full opacity-90"
             style="background-image: url('assets/img/injourney/pattern-green-BTZ3yksm.png'); background-repeat: repeat-y; background-position: right top; background-size: 100% auto;">
        </div>
    </div>

    <!-- KONTEN UTAMA (Tengah) -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Section 1: Page Header Card -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2.5">
                    <span class="w-2.5 h-6 rounded-full bg-gradient-to-b from-[#0CA1AF] to-[#087F8A]"></span>
                    <h1 class="text-2xl lg:text-3xl font-extrabold text-[#0D1C42] tracking-tight">Input Data Kargo untuk Simulasi 3D</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#087F8A]/10 text-[#087F8A] border border-[#087F8A]/30 uppercase tracking-wider">
                        LIVE INPUT
                    </span>
                </div>
                <p class="text-xs lg:text-sm text-slate-500 mt-1 pl-5">
                    Masukkan data kargo yang akan dikirimkan melalui truk ke terminal bandara. Data ini akan digunakan dalam simulasi 3D denah terminal.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="layout_3d.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-[#0CA1AF] to-[#087F8A] text-white font-bold text-xs shadow-md shadow-teal-900/10 hover:opacity-95 transition-all">
                    <i class="fa-solid fa-cubes"></i>
                    <span>Lihat Denah 3D</span>
                </a>
                <a href="simulation.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-[#087F8A]/30 bg-white text-[#087F8A] hover:bg-[#087F8A]/5 font-bold text-xs transition-all">
                    <i class="fa-solid fa-microchip"></i>
                    <span>Simulasi 7-Tahap</span>
                </a>
            </div>
        </div>

        <!-- Section 2: Input Form Card (2-column grid on large screens) -->
        <div class="bg-white p-6 lg:p-8 rounded-2xl border border-gray-200/90 shadow-sm">
            <form id="cargoForm" action="api/cargo_input.php" method="POST" onsubmit="event.preventDefault(); submitCargoForm('3d');">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    
                    <!-- Left column (lg:col-span-7) -->
                    <div class="lg:col-span-7 space-y-6">
                        
                        <!-- Group 1: Informasi Pengiriman -->
                        <div class="p-5 rounded-2xl bg-slate-50/70 border border-slate-200/80 space-y-4">
                            <h3 class="text-sm font-bold text-[#0D1C42] mb-3 flex items-center gap-2">
                                <i class="fa-solid fa-truck-ramp-box text-[#087F8A]"></i>
                                Informasi Pengiriman
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-[#0D1C42] mb-1.5" for="shipper">
                                        Nama Pengirim (Shipper) <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="text" id="shipper" name="shipper" required 
                                           value="PT Logistik Nusantara" 
                                           placeholder="PT Logistik Nusantara"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 bg-white text-sm text-slate-800 focus:border-[#0CA1AF] focus:ring-2 focus:ring-[#0CA1AF]/20 focus:outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[#0D1C42] mb-1.5" for="consignee">
                                        Nama Penerima (Consignee) <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="text" id="consignee" name="consignee" required 
                                           value="Bali Distribution Center" 
                                           placeholder="Bali Distribution Center"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 bg-white text-sm text-slate-800 focus:border-[#0CA1AF] focus:ring-2 focus:ring-[#0CA1AF]/20 focus:outline-none transition-all">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold text-[#0D1C42] mb-1.5" for="description">
                                        Deskripsi Barang <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="text" id="description" name="description" required 
                                           value="Pakaian &amp; Tekstil Premium Ekspor" 
                                           placeholder="Pakaian & Tekstil Premium Ekspor"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 bg-white text-sm text-slate-800 focus:border-[#0CA1AF] focus:ring-2 focus:ring-[#0CA1AF]/20 focus:outline-none transition-all">
                                </div>
                            </div>
                        </div>

                        <!-- Group 2: Detail Kargo -->
                        <div class="p-5 rounded-2xl bg-slate-50/70 border border-slate-200/80 space-y-4">
                            <h3 class="text-sm font-bold text-[#0D1C42] mb-3 flex items-center gap-2">
                                <i class="fa-solid fa-boxes-stacked text-[#087F8A]"></i>
                                Detail Kargo
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-[#0D1C42] mb-1.5" for="commodity_type">
                                        Tipe Komoditas <span class="text-rose-500">*</span>
                                    </label>
                                    <select id="commodity_type" name="commodity_type" required 
                                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 bg-white text-sm text-slate-800 focus:border-[#0CA1AF] focus:ring-2 focus:ring-[#0CA1AF]/20 focus:outline-none transition-all">
                                        <option value="GENERAL_CARGO" selected>GENERAL_CARGO</option>
                                        <option value="PERISHABLE">PERISHABLE</option>
                                        <option value="DANGEROUS_GOODS">DANGEROUS_GOODS</option>
                                        <option value="VALUABLE">VALUABLE</option>
                                        <option value="PHARMA">PHARMA</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[#0D1C42] mb-1.5" for="quantity">
                                        Jumlah Koli <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="number" id="quantity" name="quantity" required min="1" max="500" 
                                           value="50" 
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 bg-white text-sm text-slate-800 focus:border-[#0CA1AF] focus:ring-2 focus:ring-[#0CA1AF]/20 focus:outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[#0D1C42] mb-1.5" for="declared_weight_kg">
                                        Berat Deklarasi (kg) <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="number" id="declared_weight_kg" name="declared_weight_kg" required min="1" max="15000" step="0.1" 
                                           value="350" 
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 bg-white text-sm text-slate-800 focus:border-[#0CA1AF] focus:ring-2 focus:ring-[#0CA1AF]/20 focus:outline-none transition-all">
                                </div>
                            </div>
                        </div>

                        <!-- Group 3: Data Truk Pengangkut -->
                        <div class="p-5 rounded-2xl bg-slate-50/70 border border-slate-200/80 space-y-4">
                            <h3 class="text-sm font-bold text-[#0D1C42] mb-3 flex items-center gap-2">
                                <i class="fa-solid fa-truck text-[#087F8A]"></i>
                                Data Truk Pengangkut
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-[#0D1C42] mb-1.5" for="driver_name">
                                        Nama Sopir
                                    </label>
                                    <input type="text" id="driver_name" name="driver_name" 
                                           value="Suryadi Pratama" 
                                           placeholder="Suryadi Pratama"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 bg-white text-sm text-slate-800 focus:border-[#0CA1AF] focus:ring-2 focus:ring-[#0CA1AF]/20 focus:outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[#0D1C42] mb-1.5" for="truck_plate">
                                        Plat Nomor Truk
                                    </label>
                                    <input type="text" id="truck_plate" name="truck_plate" 
                                           value="B 9482 XX" 
                                           placeholder="B 9482 XX"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 bg-white text-sm text-slate-800 focus:border-[#0CA1AF] focus:ring-2 focus:ring-[#0CA1AF]/20 focus:outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[#0D1C42] mb-1.5" for="dock_slot">
                                        Alokasi Dermaga (Truk)
                                    </label>
                                    <select id="dock_slot" name="dock_slot" 
                                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 bg-white text-sm text-slate-800 focus:border-[#0CA1AF] focus:ring-2 focus:ring-[#0CA1AF]/20 focus:outline-none transition-all font-semibold">
                                        <option value="DOCK-01">Truk 1 (Dermaga 01)</option>
                                        <option value="DOCK-02">Truk 2 (Dermaga 02)</option>
                                        <option value="DOCK-03" selected>Truk 3 (Dermaga 03)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Group 4: Opsi Simulasi X-Ray -->
                        <div class="p-5 rounded-2xl bg-slate-50/70 border border-slate-200/80 space-y-4">
                            <h3 class="text-sm font-bold text-[#0D1C42] mb-3 flex items-center gap-2">
                                <i class="fa-solid fa-shield-halved text-[#087F8A]"></i>
                                Opsi Simulasi X-Ray
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="relative flex items-center p-3.5 rounded-xl border-2 border-emerald-400 bg-emerald-50/50 cursor-pointer hover:bg-emerald-50 transition-all">
                                    <input type="radio" name="xray_result" value="CLEARED" checked class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 border-gray-300">
                                    <div class="ml-3">
                                        <span class="block text-xs font-bold text-emerald-800">✅ CLEARED</span>
                                        <span class="block text-[11px] text-emerald-600 font-medium">Barang Aman (Lolos X-Ray)</span>
                                    </div>
                                </label>
                                <label class="relative flex items-center p-3.5 rounded-xl border-2 border-rose-300 bg-rose-50/50 cursor-pointer hover:bg-rose-50 transition-all">
                                    <input type="radio" name="xray_result" value="SUSPECT" class="w-4 h-4 text-rose-600 focus:ring-rose-500 border-gray-300">
                                    <div class="ml-3">
                                        <span class="block text-xs font-bold text-rose-800">⚠️ SUSPECT</span>
                                        <span class="block text-[11px] text-rose-600 font-medium">Barang Berbahaya (Masuk Karantina)</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                    </div>

                    <!-- Right column (lg:col-span-5): Preview Card & Flow Diagram -->
                    <div class="lg:col-span-5">
                        <div class="sticky top-24 bg-slate-50 border border-slate-200/90 rounded-2xl p-5 space-y-4 shadow-sm">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-4 rounded-full bg-[#087F8A]"></span>
                                    <h3 class="text-sm font-bold text-[#0D1C42]">Preview Data Kargo</h3>
                                </div>
                                <span id="prev-xray" class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    ✅ CLEARED
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="bg-white p-2.5 rounded-xl border border-slate-200">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">AWB Number</span>
                                    <span class="font-mono font-bold text-slate-700 text-xs">Auto-generated</span>
                                </div>
                                <div class="bg-white p-2.5 rounded-xl border border-slate-200">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">SSCC</span>
                                    <span class="font-mono font-bold text-[#087F8A] text-xs">Auto-generated</span>
                                </div>
                            </div>

                            <div class="bg-white p-3 rounded-xl border border-slate-200 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-[10px] uppercase font-bold text-slate-400">Rute Pengiriman</span>
                                    <span id="prev-commodity" class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-teal-50 text-teal-700 border border-teal-200">
                                        GENERAL_CARGO
                                    </span>
                                </div>
                                <div class="text-xs font-semibold text-[#0D1C42] flex items-center gap-1.5 flex-wrap">
                                    <span id="prev-shipper">PT Logistik Nusantara</span>
                                    <i class="fa-solid fa-arrow-right text-[10px] text-slate-400"></i>
                                    <span id="prev-consignee">Bali Distribution Center</span>
                                </div>
                                <div class="text-xs text-slate-600 pt-1.5 border-t border-slate-100 flex items-start gap-1.5">
                                    <i class="fa-solid fa-box text-slate-400 text-xs mt-0.5"></i>
                                    <span id="prev-desc" class="line-clamp-2">Pakaian &amp; Tekstil Premium Ekspor</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="bg-white p-2.5 rounded-xl border border-slate-200">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">Berat Deklarasi</span>
                                    <span id="prev-weight" class="font-bold text-[#0D1C42] text-xs">350 kg</span>
                                </div>
                                <div class="bg-white p-2.5 rounded-xl border border-slate-200">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">Kuantitas</span>
                                    <span id="prev-qty" class="font-bold text-[#0D1C42] text-xs">50 koli</span>
                                </div>
                            </div>

                            <div class="bg-white p-2.5 rounded-xl border border-slate-200 text-xs">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Data Truk Pengangkut</span>
                                <div class="flex items-center justify-between">
                                    <span id="prev-plate" class="font-mono font-bold text-slate-800">B 9482 XX</span>
                                    <span class="text-slate-500 text-[11px] flex items-center gap-1">
                                        <i class="fa-solid fa-id-card text-slate-400 text-[10px]"></i>
                                        <span id="prev-driver">Suryadi Pratama</span>
                                    </span>
                                </div>
                            </div>

                            <div class="pt-2">
                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-2 text-center">
                                    Visual Alur Jalur Kargo
                                </span>
                                <div id="flow-diagram" class="p-3 bg-white rounded-xl border border-slate-200">
                                    <!-- Diisi oleh JS updateFlowDiagram() -->
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Section 3: Submit Buttons Row -->
                <div class="mt-8 pt-6 border-t border-gray-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3">
                    <button type="reset" onclick="setTimeout(updatePreview, 50)" class="px-5 py-3 rounded-xl font-bold text-slate-600 border border-slate-300 bg-white hover:bg-slate-50 transition-all text-xs lg:text-sm flex items-center justify-center gap-2 cursor-pointer shadow-sm">
                        <i class="fa-solid fa-rotate-left"></i>
                        <span>Reset Form</span>
                    </button>
                    <button type="button" onclick="submitCargoForm('sim')" class="px-4 py-3 rounded-xl font-bold text-[#087F8A] border-2 border-[#087F8A] bg-white hover:bg-[#087F8A]/5 transition-all text-xs lg:text-sm flex items-center justify-center gap-2 cursor-pointer shadow-sm">
                        <i class="fa-solid fa-chart-simple"></i>
                        <span>Simulasi 7-Tahap</span>
                    </button>
                    <button type="button" onclick="submitCargoForm('3d')" class="px-4 py-3 rounded-xl font-bold text-slate-700 border border-slate-300 bg-slate-100 hover:bg-slate-200 transition-all text-xs lg:text-sm flex items-center justify-center gap-2 cursor-pointer shadow-sm">
                        <i class="fa-solid fa-cubes"></i>
                        <span>Denah 3D Terminal</span>
                    </button>
                    <button type="button" onclick="submitCargoForm('live')" class="px-6 py-3 rounded-xl font-extrabold text-white shadow-xl shadow-teal-900/30 hover:opacity-95 transition-all text-xs lg:text-sm flex items-center justify-center gap-2 cursor-pointer ring-2 ring-teal-400/40" style="background: linear-gradient(135deg, #0CA1AF 0%, #087F8A 50%, #014D54 100%);">
                        <i class="fa-solid fa-gamepad text-amber-300 text-sm"></i>
                        <span>Kirim &amp; Buka Simulasi Live (All-in-One)</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Section 4: Existing Cargo Table -->
        <div class="bg-white p-6 lg:p-8 rounded-2xl border border-gray-200/90 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-6 rounded-full bg-gradient-to-b from-[#0CA1AF] to-[#087F8A]"></span>
                    <div>
                        <h2 class="text-lg lg:text-xl font-extrabold text-[#0D1C42]">Kargo yang Sudah Terdaftar</h2>
                        <p class="text-xs text-slate-500">Daftar kargo terbaru dalam sistem yang siap divisualisasikan dalam simulasi 3D atau simulasi 7-tahap.</p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-[#087F8A]/10 text-[#087F8A] border border-[#087F8A]/20 self-start sm:self-auto">
                    <?= count($existingCargos) ?> Kargo Tersedia
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-gray-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                            <th class="py-3 px-4">AWB &amp; SSCC</th>
                            <th class="py-3 px-4">Deskripsi</th>
                            <th class="py-3 px-4">Tipe</th>
                            <th class="py-3 px-4">Berat</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Truk</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($existingCargos)): ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    <i class="fa-solid fa-box-open text-2xl mb-2 text-slate-300 block"></i>
                                    Belum ada kargo terdaftar. Silakan isi formulir di atas untuk memulai simulasi.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($existingCargos as $cargo): ?>
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-[#0D1C42] font-mono text-xs"><?= htmlspecialchars($cargo['awb_number']) ?></div>
                                        <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($cargo['sscc']) ?></div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-slate-800 max-w-[220px] truncate" title="<?= htmlspecialchars($cargo['description']) ?>">
                                            <?= htmlspecialchars($cargo['description']) ?>
                                        </div>
                                        <div class="text-[10px] text-slate-400 truncate max-w-[220px]">
                                            <?= htmlspecialchars($cargo['shipper']) ?> &rarr; <?= htmlspecialchars($cargo['consignee']) ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700">
                                            <?= htmlspecialchars($cargo['commodity_type']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-700"><?= number_format($cargo['declared_weight_kg'], 1) ?> kg</div>
                                        <div class="text-[10px] text-slate-400"><?= (int)$cargo['quantity'] ?> koli</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold border <?= getStatusBadgeClass($cargo['status']) ?>">
                                            <?= htmlspecialchars($cargo['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-mono font-bold text-slate-700"><?= htmlspecialchars($cargo['truck_plate'] ?? '-') ?></div>
                                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($cargo['dock_slot'] ?? '-') ?></div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a href="simulation_live.php?cargo_id=<?= urlencode($cargo['id']) ?>&autostart=1" 
                                               class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-teal-600 text-white hover:bg-teal-700 transition-all shadow-xs" 
                                               title="Simulasi Live (Input &amp; Visual)">
                                                <i class="fa-solid fa-gamepad text-amber-300"></i>
                                                <span>Live</span>
                                            </a>
                                            <a href="layout_3d.php?cargo_id=<?= urlencode($cargo['id']) ?>&xray=<?= urlencode($cargo['status']) ?>" 
                                               class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-bold bg-[#087F8A]/10 text-[#087F8A] hover:bg-[#087F8A] hover:text-white transition-all shadow-xs" 
                                               title="Denah 3D Terminal">
                                                <i class="fa-solid fa-cubes"></i>
                                                <span>Denah 3D</span>
                                            </a>
                                            <a href="simulation.php?cargo_id=<?= urlencode($cargo['id']) ?>" 
                                               class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-bold border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 transition-all shadow-xs" 
                                               title="Simulasi 7-Tahap">
                                                <i class="fa-solid fa-chart-column text-[#087F8A]"></i>
                                                <span>7-Tahap</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
// Live preview update
function updatePreview() {
    const form = document.getElementById('cargoForm');
    // Update preview elements with form values
    document.getElementById('prev-shipper').textContent = form.shipper.value || 'PT Logistik Nusantara';
    document.getElementById('prev-consignee').textContent = form.consignee.value || 'Bali Distribution Center';
    document.getElementById('prev-desc').textContent = form.description.value || 'Paket Kargo Umum';
    document.getElementById('prev-commodity').textContent = form.commodity_type.value;
    document.getElementById('prev-weight').textContent = (form.declared_weight_kg.value || '350') + ' kg';
    document.getElementById('prev-qty').textContent = (form.quantity.value || '50') + ' koli';
    document.getElementById('prev-driver').textContent = form.driver_name.value || 'Auto-assign';
    const dockText = form.dock_slot ? form.dock_slot.options[form.dock_slot.selectedIndex]?.text : '';
    document.getElementById('prev-plate').textContent = (form.truck_plate.value || 'Auto-assign') + (dockText ? ' &bull; ' + dockText : '');
    
    // Update X-Ray preview
    const xrayResult = form.querySelector('input[name=xray_result]:checked')?.value || 'CLEARED';
    const xrayBadge = document.getElementById('prev-xray');
    if (xrayResult === 'SUSPECT') {
        xrayBadge.textContent = '⚠️ SUSPECT';
        xrayBadge.className = 'px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-300 animate-pulse';
    } else {
        xrayBadge.textContent = '✅ CLEARED';
        xrayBadge.className = 'px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200';
    }
    
    // Update flow diagram
    updateFlowDiagram(xrayResult);
}

function updateFlowDiagram(xrayResult) {
    const flowContainer = document.getElementById('flow-diagram');
    if (xrayResult === 'SUSPECT') {
        flowContainer.innerHTML = `
            <div class="flex items-center flex-wrap gap-2 justify-center">
                <span class="px-2 py-1 rounded-lg bg-blue-100 text-blue-700 text-xs font-bold">🚚 Truk</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="px-2 py-1 rounded-lg bg-teal-100 text-teal-700 text-xs font-bold">📡 RFID</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="px-2 py-1 rounded-lg bg-amber-100 text-amber-700 text-xs font-bold">🔍 X-Ray</span>
                <i class="fa-solid fa-arrow-right text-rose-400"></i>
                <span class="px-2 py-1 rounded-lg bg-rose-100 text-rose-700 text-xs font-bold animate-pulse">🛑 KARANTINA</span>
            </div>
            <p class="text-xs text-rose-600 font-semibold mt-2 text-center">⚠️ Barang terdeteksi berbahaya — akan dialihkan ke Ruang Karantina!</p>
        `;
    } else {
        flowContainer.innerHTML = `
            <div class="flex items-center flex-wrap gap-2 justify-center">
                <span class="px-2 py-1 rounded-lg bg-blue-100 text-blue-700 text-xs font-bold">🚚 Truk</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="px-2 py-1 rounded-lg bg-teal-100 text-teal-700 text-xs font-bold">📡 RFID</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="px-2 py-1 rounded-lg bg-amber-100 text-amber-700 text-xs font-bold">🔍 X-Ray</span>
                <i class="fa-solid fa-arrow-right text-emerald-400"></i>
                <span class="px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-bold">✅ Aman</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="px-2 py-1 rounded-lg bg-green-100 text-green-700 text-xs font-bold">📦 Storage</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="px-2 py-1 rounded-lg bg-purple-100 text-purple-700 text-xs font-bold">⚖️ Timbangan</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="px-2 py-1 rounded-lg bg-sky-100 text-sky-700 text-xs font-bold">✈️ Pesawat</span>
            </div>
            <p class="text-xs text-emerald-600 font-semibold mt-2 text-center">✅ Barang aman — akan dimuat ke pesawat!</p>
        `;
    }
}

function submitCargoForm(redirectTo = '3d') {
    const form = document.getElementById('cargoForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    data.xray_result = form.querySelector('input[name=xray_result]:checked')?.value || 'CLEARED';
    
    Swal.fire({
        title: 'Memproses Data Kargo...',
        text: 'Mendaftarkan kargo ke database dan menyiapkan simulasi...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#ffffff',
        color: '#0D1C42',
        customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
    });
    
    fetch('api/cargo_input.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire({
                title: 'Kargo Berhasil Didaftarkan!',
                html: `<div class="text-left text-sm space-y-1">
                    <p><strong>Cargo ID:</strong> ${res.cargo_id}</p>
                    <p><strong>AWB:</strong> ${res.awb_number}</p>
                    <p><strong>SSCC:</strong> ${res.sscc}</p>
                    <p><strong>Truk:</strong> ${res.truck_plate} (${res.dock_slot})</p>
                    <p><strong>X-Ray:</strong> <span class="${res.xray_result === 'SUSPECT' ? 'text-rose-600 font-bold' : 'text-emerald-600 font-bold'}">${res.xray_result}</span></p>
                </div>`,
                icon: 'success',
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: '#087F8A',
                confirmButtonText: redirectTo === 'live' ? '🎮 Buka Simulasi Live (All-in-One)' : (redirectTo === '3d' ? '🎮 Buka Denah 3D' : '📊 Buka Simulasi 7-Tahap'),
                showCancelButton: true,
                cancelButtonText: 'Tutup',
                cancelButtonColor: '#94a3b8',
                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
            }).then(result => {
                if (result.isConfirmed) {
                    if (redirectTo === 'live') {
                        window.location.href = `simulation_live.php?cargo_id=${res.cargo_id}&xray=${res.xray_result}&autostart=1`;
                    } else if (redirectTo === '3d') {
                        window.location.href = `layout_3d.php?cargo_id=${res.cargo_id}&xray=${res.xray_result}`;
                    } else {
                        window.location.href = `simulation.php?cargo_id=${res.cargo_id}`;
                    }
                } else {
                    window.location.reload();
                }
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: res.message,
                icon: 'error',
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: '#087F8A',
                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
            });
        }
    })
    .catch(err => {
        Swal.fire({ title: 'Error', text: err.toString(), icon: 'error', confirmButtonColor: '#087F8A' });
    });
}

// Attach live preview listeners
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('cargoForm');
    form.querySelectorAll('input, select, textarea').forEach(el => {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });
    updatePreview();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
