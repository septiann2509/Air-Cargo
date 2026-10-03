<?php
// dashboard.php — Executive & Operations Dashboard (InJourney Airports Design System)
require_once __DIR__ . '/config/database.php';
requireAuth();

$user = getCurrentUser();
$db = getDb();

// Ambil data flights
$flightsStmt = $db->query("SELECT * FROM flight_manifests ORDER BY departure_time ASC");
$flights = $flightsStmt->fetchAll();

// Ambil data ULDs
$uldsStmt = $db->query("SELECT u.*, f.flight_number FROM uld_containers u LEFT JOIN flight_manifests f ON u.flight_id = f.id ORDER BY u.uld_code ASC");
$ulds = $uldsStmt->fetchAll();

// Ambil kargo terbaru
$cargoStmt = $db->query("
    SELECT c.*, t.truck_plate, t.dock_slot, u.uld_code, f.flight_number 
    FROM cargo_shipments c
    LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
    LEFT JOIN uld_containers u ON c.uld_id = u.id
    LEFT JOIN flight_manifests f ON u.flight_id = f.id
    ORDER BY c.created_at DESC
");
$cargos = $cargoStmt->fetchAll();

$pageTitle = 'Executive & Operations Dashboard';
$bodyClass = 'bg-[#F8FAFC] text-slate-800';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    
    <!-- Top Greeting & Header Bar (InJourney Style) -->
    <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-gray-200 gap-4">
        <div>
            <div class="flex items-center space-x-2.5">
                <span class="w-2.5 h-6 rounded-full bg-gradient-to-b from-[#0CA1AF] to-[#087F8A]"></span>
                <h1 class="text-2xl lg:text-3xl font-extrabold text-[#0D1C42] tracking-tight">Executive Dashboard Konsultan</h1>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#087F8A]/10 text-[#087F8A] border border-[#087F8A]/30 uppercase tracking-wider">
                    Live Operations
                </span>
            </div>
            <p class="text-xs lg:text-sm text-slate-500 mt-1 pl-5">
                Pemantauan Real-Time Alur Kargo Udara &amp; Rekomendasi Solusi untuk <strong class="text-[#0D1C42] font-semibold"><?= htmlspecialchars($user['organization']) ?></strong>
            </p>
        </div>

        <div class="flex items-center space-x-2.5">
            <button onclick="openNewCargoModal()" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-lg shadow-teal-500/25 transition-all flex items-center gap-1.5 transform hover:-translate-y-0.5">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Input Kargo Baru</span>
            </button>
            <a href="simulation.php" class="px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-[#0D1C42] font-bold text-xs border border-gray-300 shadow-sm transition-all flex items-center gap-1.5 transform hover:-translate-y-0.5">
                <i class="fa-solid fa-play text-xs text-[#087F8A]"></i>
                <span>Buka Lab Simulasi</span>
            </a>
            <button onclick="refreshDashboardData()" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-600 hover:text-[#087F8A] border border-gray-300 text-xs shadow-sm transition-colors" title="Perbarui Data">
                <i class="fa-solid fa-arrows-rotate"></i>
            </button>
        </div>
    </div>

    <!-- KPI Summary Grid (InJourney White Cards with Soft Shadow) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <!-- Metric 1: Total Inbound -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm hover:shadow-xl hover:border-[#0CA1AF] transition-all group">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-500 font-bold uppercase tracking-wider block">Total Inbound Kargo</span>
                    <h3 id="stat-total-cargos" class="text-3xl font-black text-[#0D1C42] mt-1.5 font-mono"><?= count($cargos) ?></h3>
                    <div class="mt-2">
                        <span class="text-[11px] font-bold text-[#087F8A] bg-teal-50 px-2.5 py-0.5 rounded-full border border-teal-200/60 inline-flex items-center">
                            <i class="fa-solid fa-barcode mr-1 text-xs"></i> Standar GS1 SSCC
                        </span>
                    </div>
                </div>
                <div class="w-13 h-13 p-3.5 rounded-2xl bg-gradient-to-tr from-[#0CA1AF]/10 to-[#087F8A]/20 text-[#087F8A] border border-[#0CA1AF]/30 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
            </div>
        </div>

        <!-- Metric 2: AVSEC Status -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm hover:shadow-xl hover:border-emerald-400 transition-all group">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-500 font-bold uppercase tracking-wider block">Pemeriksaan AVSEC X-Ray</span>
                    <div class="flex items-baseline space-x-2 mt-1.5">
                        <span id="stat-avsec-cleared" class="text-3xl font-black text-emerald-600 font-mono">0</span>
                        <span class="text-xs text-slate-500 font-semibold">Cleared /</span>
                        <span id="stat-avsec-suspect" class="text-xl font-bold text-rose-600 font-mono">0</span>
                        <span class="text-[10px] text-rose-600 font-bold">Suspect</span>
                    </div>
                    <div class="mt-2">
                        <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200 inline-flex items-center">
                            <i class="fa-solid fa-shield-halved mr-1 text-xs"></i> CSD Digital Ready
                        </span>
                    </div>
                </div>
                <div class="w-13 h-13 p-3.5 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-person-military-pointing"></i>
                </div>
            </div>
        </div>

        <!-- Metric 3: ULD Build-Up Status -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm hover:shadow-xl hover:border-[#087F8A] transition-all group">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-500 font-bold uppercase tracking-wider block">Kontainer ULD Aktif</span>
                    <h3 class="text-3xl font-black text-[#0D1C42] mt-1.5 font-mono"><?= count($ulds) ?></h3>
                    <div class="mt-2">
                        <span class="text-[11px] font-bold text-[#014D54] bg-teal-50 px-2.5 py-0.5 rounded-full border border-teal-200/60 inline-flex items-center">
                            <i class="fa-solid fa-weight-hanging mr-1 text-xs"></i> W&amp;B OIML R76
                        </span>
                    </div>
                </div>
                <div class="w-13 h-13 p-3.5 rounded-2xl bg-teal-50 text-[#014D54] border border-teal-200 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-box-open"></i>
                </div>
            </div>
        </div>

        <!-- Metric 4: Flight Manifests -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm hover:shadow-xl hover:border-amber-400 transition-all group">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-500 font-bold uppercase tracking-wider block">Penerbangan Kargo</span>
                    <h3 class="text-3xl font-black text-[#0D1C42] mt-1.5 font-mono"><?= count($flights) ?></h3>
                    <div class="mt-2">
                        <span class="text-[11px] font-bold text-amber-800 bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200 inline-flex items-center">
                            <i class="fa-solid fa-plane-up mr-1 text-xs"></i> GA-707 &amp; SQ-801
                        </span>
                    </div>
                </div>
                <div class="w-13 h-13 p-3.5 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-plane"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- 7-Stage Pipeline Live Breakdown Tracker (InJourney Clean White Card) -->
    <div class="bg-white p-6 lg:p-7 rounded-2xl border border-gray-200/90 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 pb-4 border-b border-gray-100">
            <div>
                <h3 class="text-base font-extrabold text-[#0D1C42] flex items-center gap-2">
                    <i class="fa-solid fa-network-wired text-[#087F8A]"></i>
                    Status Pipeline Kargo di 7 Tahapan Rekomendasi
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Monitoring kuantitas kargo yang sedang aktif di setiap workstation terminal</p>
            </div>
            <span class="text-[11px] font-mono font-bold px-3 py-1 rounded-full bg-teal-50 text-[#087F8A] border border-teal-200 self-start sm:self-auto flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-[#0CA1AF] animate-pulse"></span>
                <span>Real-Time Sync</span>
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3.5" id="pipeline-counters-container">
            <!-- Stage 1 -->
            <div class="bg-[#F8FAFC] p-3.5 rounded-xl border border-gray-200/80 text-center hover:border-[#0CA1AF] hover:bg-teal-50/20 hover:shadow-md transition-all">
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">1. Truk Tiba</span>
                <span id="stage-count-1" class="text-2xl font-black text-[#087F8A] font-mono my-1 block">0</span>
                <span class="text-[10px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-md border border-gray-200 inline-block">TMS / TAS</span>
            </div>
            <!-- Stage 2 -->
            <div class="bg-[#F8FAFC] p-3.5 rounded-xl border border-gray-200/80 text-center hover:border-[#0CA1AF] hover:bg-teal-50/20 hover:shadow-md transition-all">
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">2. Scan RFID</span>
                <span id="stage-count-2" class="text-2xl font-black text-[#087F8A] font-mono my-1 block">0</span>
                <span class="text-[10px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-md border border-gray-200 inline-block">Gate AIDC</span>
            </div>
            <!-- Stage 3 -->
            <div class="bg-[#F8FAFC] p-3.5 rounded-xl border border-gray-200/80 text-center hover:border-[#0CA1AF] hover:bg-teal-50/20 hover:shadow-md transition-all">
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">3. CMS e-AWB</span>
                <span id="stage-count-3" class="text-2xl font-black text-[#087F8A] font-mono my-1 block">0</span>
                <span class="text-[10px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-md border border-gray-200 inline-block">Registrasi</span>
            </div>
            <!-- Stage 4 -->
            <div class="bg-[#F8FAFC] p-3.5 rounded-xl border border-gray-200/80 text-center hover:border-[#0CA1AF] hover:bg-teal-50/20 hover:shadow-md transition-all">
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">4. AVSEC X-Ray</span>
                <span id="stage-count-4" class="text-2xl font-black text-[#087F8A] font-mono my-1 block">0</span>
                <span class="text-[10px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-md border border-gray-200 inline-block">Dual-View</span>
            </div>
            <!-- Stage 5 -->
            <div class="bg-[#F8FAFC] p-3.5 rounded-xl border border-gray-200/80 text-center hover:border-[#0CA1AF] hover:bg-teal-50/20 hover:shadow-md transition-all">
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">5. Penimbangan</span>
                <span id="stage-count-5" class="text-2xl font-black text-[#087F8A] font-mono my-1 block">0</span>
                <span class="text-[10px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-md border border-gray-200 inline-block">Scale 15T</span>
            </div>
            <!-- Stage 6 -->
            <div class="bg-[#F8FAFC] p-3.5 rounded-xl border border-gray-200/80 text-center hover:border-[#0CA1AF] hover:bg-teal-50/20 hover:shadow-md transition-all">
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">6. Build ULD</span>
                <span id="stage-count-6" class="text-2xl font-black text-[#087F8A] font-mono my-1 block">0</span>
                <span class="text-[10px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-md border border-gray-200 inline-block">Weight &amp; Bal</span>
            </div>
            <!-- Stage 7 -->
            <div class="bg-[#F8FAFC] p-3.5 rounded-xl border border-gray-200/80 text-center hover:border-emerald-400 hover:bg-emerald-50/20 hover:shadow-md transition-all">
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">7. Flight Load</span>
                <span id="stage-count-7" class="text-2xl font-black text-emerald-600 font-mono my-1 block">0</span>
                <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200 inline-block">Departed</span>
            </div>
        </div>
    </div>

    <!-- Middle Row: Charts & Flight Status (InJourney Light White Cards) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Chart 1: Commodity Types -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-sm font-extrabold text-[#0D1C42] mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-chart-pie text-[#087F8A]"></i>
                    Komposisi Kargo per Komoditas
                </h3>
                <p class="text-xs text-slate-500 mb-4">Distribusi jenis kargo pada manifes aktif</p>
            </div>
            <div class="h-56 relative flex items-center justify-center">
                <canvas id="commodityChart"></canvas>
            </div>
        </div>

        <!-- Chart 2: ULD Capacities -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-sm font-extrabold text-[#0D1C42] mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-chart-bar text-[#087F8A]"></i>
                    Status Utilisasi Kontainer ULD
                </h3>
                <p class="text-xs text-slate-500 mb-4">Kondisi muatan ULD (Empty, Loading, Full, Loaded)</p>
            </div>
            <div class="h-56 relative">
                <canvas id="uldWeightChart"></canvas>
            </div>
        </div>

        <!-- Panel 3: Active Flights & Loadsheet Status -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-sm font-extrabold text-[#0D1C42] mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-plane-departure text-[#087F8A]"></i>
                    Jadwal Penerbangan &amp; Manifes
                </h3>
                <p class="text-xs text-slate-500 mb-4">Monitoring kapasitas dan loadsheet pesawat</p>
                
                <div class="space-y-3">
                    <?php foreach ($flights as $f): ?>
                        <div class="p-3.5 rounded-xl bg-[#F8FAFC] border border-gray-200 text-xs hover:border-[#0CA1AF] transition-all">
                            <div class="flex items-center justify-between">
                                <span class="font-extrabold text-sm text-[#0D1C42]"><?= htmlspecialchars($f['flight_number']) ?></span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $f['status'] === 'CLOSED' ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300' ?>">
                                    <?= htmlspecialchars($f['status']) ?>
                                </span>
                            </div>
                            <p class="text-[11.5px] text-slate-600 mt-1">
                                Rute: <strong class="text-[#0D1C42]"><?= htmlspecialchars($f['origin']) ?> &rarr; <?= htmlspecialchars($f['destination']) ?></strong>
                            </p>
                            <div class="mt-2 flex items-center justify-between text-[11px] text-slate-500">
                                <span>Pesawat: <strong class="text-slate-700"><?= htmlspecialchars($f['aircraft_type']) ?></strong></span>
                                <span class="font-mono text-[#087F8A] font-bold"><?= number_format($f['current_total_weight_kg']) ?> / <?= number_format($f['max_cargo_weight_kg']) ?> kg</span>
                            </div>
                            <div class="w-full bg-gray-200 h-2 rounded-full mt-1.5 overflow-hidden">
                                <?php 
                                    $pct = $f['max_cargo_weight_kg'] > 0 ? min(100, round(($f['current_total_weight_kg'] / $f['max_cargo_weight_kg']) * 100)) : 0;
                                ?>
                                <div class="bg-gradient-to-r from-[#04AFBF] to-[#087F8A] h-full rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <a href="simulation.php" class="mt-4 block text-center py-2.5 px-3 rounded-xl bg-[#F8FAFC] hover:bg-teal-50 text-[#087F8A] hover:text-[#014D54] text-xs font-bold border border-gray-200 hover:border-teal-300 transition-all shadow-sm">
                <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Buka Simulasi Pemuatan ULD
            </a>
        </div>

    </div>

    <!-- Active Cargo Shipments Table (InJourney Modern Clean Table) -->
    <div class="bg-white rounded-2xl border border-gray-200/90 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-extrabold text-[#0D1C42] flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-[#087F8A]"></i>
                    Daftar Kargo Masuk &amp; Status Tahapan Terkini
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Pilih kargo untuk melanjutkan atau menguji simulasinya di laboratorium sistem</p>
            </div>
            <a href="database_viewer.php?table=cargo_shipments" class="text-xs text-[#087F8A] hover:text-[#0CA1AF] font-bold flex items-center gap-1 self-start sm:self-auto">
                <span>Buka di Database Explorer</span>
                <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-[#F8FAFC] text-slate-600 font-bold border-b border-gray-200 uppercase text-[10.5px] tracking-wider">
                    <tr>
                        <th class="py-3.5 px-5">AWB &amp; ID Kargo</th>
                        <th class="py-3.5 px-5">GS1 SSCC (18-Digit)</th>
                        <th class="py-3.5 px-5">Shipper &amp; Komoditas</th>
                        <th class="py-3.5 px-5">Koli / Berat</th>
                        <th class="py-3.5 px-5">Tahapan &amp; Status</th>
                        <th class="py-3.5 px-5">Alokasi Dock / ULD</th>
                        <th class="py-3.5 px-5 text-center">Aksi Simulasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-mono text-[11.5px]">
                    <?php if (empty($cargos)): ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 font-sans">
                                <i class="fa-solid fa-box-open text-3xl mb-2 text-slate-300 block"></i>
                                Belum ada data kargo. Silakan klik tombol "Input Kargo Baru" di atas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cargos as $c): ?>
                            <tr class="hover:bg-teal-50/40 transition-colors">
                                <td class="py-3.5 px-5">
                                    <div class="font-extrabold text-[#0D1C42]"><?= htmlspecialchars($c['awb_number']) ?></div>
                                    <span class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($c['id']) ?></span>
                                </td>
                                <td class="py-3.5 px-5">
                                    <span class="text-[#087F8A] font-bold"><?= htmlspecialchars($c['sscc']) ?></span>
                                    <span class="text-[10px] text-slate-400 block font-mono">GTIN: <?= htmlspecialchars($c['gtin']) ?></span>
                                </td>
                                <td class="py-3.5 px-5 font-sans">
                                    <div class="text-slate-800 font-bold"><?= htmlspecialchars($c['shipper']) ?></div>
                                    <div class="text-[11px] text-slate-500 truncate max-w-xs"><?= htmlspecialchars($c['description']) ?></div>
                                </td>
                                <td class="py-3.5 px-5">
                                    <span class="text-slate-900 font-bold"><?= $c['quantity'] ?> koli</span>
                                    <span class="text-[10.5px] text-slate-500 block"><?= number_format($c['actual_weight_kg'] ?? $c['declared_weight_kg'], 1) ?> kg</span>
                                </td>
                                <td class="py-3.5 px-5 font-sans">
                                    <div class="flex items-center space-x-2">
                                        <span class="w-6 h-6 rounded-full bg-teal-50 text-[#087F8A] border border-teal-200 text-[11px] font-bold flex items-center justify-center font-mono">
                                            <?= $c['current_stage'] ?>
                                        </span>
                                        <?php
                                            $badgeClass = 'bg-slate-100 text-slate-700 border-slate-300';
                                            if ($c['status'] === 'CLEARED') $badgeClass = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                                            elseif ($c['status'] === 'SUSPECT') $badgeClass = 'bg-rose-100 text-rose-800 border-rose-300';
                                            elseif ($c['status'] === 'LOADED') $badgeClass = 'bg-teal-100 text-[#014D54] border-teal-300';
                                            elseif ($c['status'] === 'ALLOCATED') $badgeClass = 'bg-blue-100 text-blue-800 border-blue-300';
                                        ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $badgeClass ?>">
                                            <?= htmlspecialchars($c['status']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-5 font-sans">
                                    <div class="text-slate-700">Dock: <span class="text-[#087F8A] font-bold font-mono"><?= htmlspecialchars($c['dock_slot'] ?? '-') ?></span></div>
                                    <div class="text-[10.5px] text-slate-400 font-mono">ULD: <?= htmlspecialchars($c['uld_code'] ?? 'Belum Di-build') ?></div>
                                </td>
                                <td class="py-3.5 px-5 text-center font-sans">
                                    <a href="simulation.php?cargo_id=<?= urlencode($c['id']) ?>" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-teal-50 hover:bg-[#087F8A] text-[#087F8A] hover:text-white border border-teal-200 hover:border-[#087F8A] text-xs font-bold transition-all shadow-sm">
                                        <i class="fa-solid fa-play mr-1.5 text-[10px]"></i> Simulasi
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal Input Kargo Baru (InJourney Light Card) -->
<div id="newCargoModal" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-lg rounded-2xl border border-gray-200 p-6 shadow-2xl relative">
        <button onclick="closeNewCargoModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-700 text-lg transition-colors">
            <i class="fa-solid fa-xmark"></i>
        </button>
        
        <h3 class="text-base font-extrabold text-[#0D1C42] mb-1 flex items-center gap-2">
            <i class="fa-solid fa-truck-ramp-box text-[#087F8A]"></i>
            Input Kargo Inbound Baru (TMS Gate)
        </h3>
        <p class="text-xs text-slate-500 mb-5">Sistem akan secara otomatis men-generate nomor SSCC 18 digit standar GS1 dan kode booking slot dock.</p>

        <form id="newCargoForm" onsubmit="handleCreateCargo(event)" class="space-y-3.5 text-xs">
            <div>
                <label class="block text-slate-700 font-bold mb-1">Nama Pengirim (Shipper)</label>
                <input type="text" id="inp_shipper" required value="PT Indo Pharma Laboratories" class="w-full bg-[#F8FAFC] border border-gray-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:bg-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none transition-all">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-700 font-bold mb-1">Penerima (Consignee)</label>
                    <input type="text" id="inp_consignee" required value="Singapore General Hospital Logistics" class="w-full bg-[#F8FAFC] border border-gray-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:bg-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none transition-all">
                </div>
                <div>
                    <label class="block text-slate-700 font-bold mb-1">Tipe Komoditas</label>
                    <select id="inp_commodity_type" class="w-full bg-[#F8FAFC] border border-gray-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:bg-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none transition-all font-sans">
                        <option value="GENERAL_CARGO">General Cargo (Umum)</option>
                        <option value="PHARMA" selected>Pharma / Vaksin (Cold Chain)</option>
                        <option value="PERISHABLE">Perishable (Makanan/Ikan)</option>
                        <option value="VALUABLE">Valuable Goods (Elektronik)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-slate-700 font-bold mb-1">Deskripsi Barang</label>
                <input type="text" id="inp_description" required value="Vaksin Rantai Dingin & Suplemen Kesehatan" class="w-full bg-[#F8FAFC] border border-gray-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:bg-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none transition-all">
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-slate-700 font-bold mb-1">Jumlah Koli</label>
                    <input type="number" id="inp_quantity" required value="60" min="1" class="w-full bg-[#F8FAFC] border border-gray-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:bg-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none transition-all font-mono">
                </div>
                <div>
                    <label class="block text-slate-700 font-bold mb-1">Berat Deklarasi (kg)</label>
                    <input type="number" step="0.1" id="inp_weight" required value="420.0" min="1" class="w-full bg-[#F8FAFC] border border-gray-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:bg-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none transition-all font-mono">
                </div>
                <div>
                    <label class="block text-slate-700 font-bold mb-1">Supir Truk</label>
                    <input type="text" id="inp_driver" required value="Joko Susanto" class="w-full bg-[#F8FAFC] border border-gray-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:bg-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none transition-all">
                </div>
            </div>

            <div class="pt-4 flex items-center justify-end space-x-2.5 border-t border-gray-100">
                <button type="button" onclick="closeNewCargoModal()" class="px-4 py-2.5 rounded-xl bg-gray-100 text-slate-700 hover:bg-gray-200 text-xs font-bold transition-all">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-lg shadow-teal-500/20 transition-all">
                    Daftarkan Kargo &amp; Truk
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Dashboard Scripts -->
<script>
    let commChart = null;
    let uldChart = null;

    document.addEventListener('DOMContentLoaded', () => {
        refreshDashboardData();
    });

    function refreshDashboardData() {
        fetch('api/stats.php')
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    const d = response.data;
                    document.getElementById('stat-total-cargos').textContent = d.total_shipments;
                    document.getElementById('stat-avsec-cleared').textContent = d.avsec_stats.CLEARED || 0;
                    document.getElementById('stat-avsec-suspect').textContent = d.avsec_stats.SUSPECT || 0;

                    // Update pipeline counters
                    for (let s = 1; s <= 7; s++) {
                        const el = document.getElementById('stage-count-' + s);
                        if (el) el.textContent = d.stages_breakdown[s] || 0;
                    }

                    renderCommodityChart(d.commodities);
                    renderUldChart(d.uld_stats);
                }
            })
            .catch(err => console.error('Error fetching stats:', err));
    }

    function renderCommodityChart(commodities) {
        const ctx = document.getElementById('commodityChart').getContext('2d');
        const labels = Object.keys(commodities).length ? Object.keys(commodities) : ['Pharma', 'General', 'Perishable'];
        const dataValues = Object.values(commodities).length ? Object.values(commodities) : [1, 1, 1];

        if (commChart) commChart.destroy();
        commChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: dataValues,
                    backgroundColor: ['#087F8A', '#0CA1AF', '#014D54', '#f59e0b', '#10b981'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { 
                            color: '#404042', 
                            font: { family: 'Barlow', size: 11, weight: '600' },
                            padding: 12
                        }
                    }
                }
            }
        });
    }

    function renderUldChart(uldStats) {
        const ctx = document.getElementById('uldWeightChart').getContext('2d');
        if (uldChart) uldChart.destroy();
        uldChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Empty', 'Loading', 'Full', 'Loaded'],
                datasets: [{
                    label: 'Jumlah ULD',
                    data: [uldStats.EMPTY || 0, uldStats.LOADING || 0, uldStats.FULL || 0, uldStats.LOADED || 0],
                    backgroundColor: ['#94a3b8', '#087F8A', '#0CA1AF', '#10b981'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, color: '#64748b', font: { family: 'Barlow' } },
                        grid: { color: '#f1f5f9' }
                    },
                    x: {
                        ticks: { color: '#64748b', font: { family: 'Barlow', weight: '600' } },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    function openNewCargoModal() {
        document.getElementById('newCargoModal').classList.remove('hidden');
    }

    function closeNewCargoModal() {
        document.getElementById('newCargoModal').classList.add('hidden');
    }

    function handleCreateCargo(e) {
        e.preventDefault();
        const payload = {
            shipper: document.getElementById('inp_shipper').value,
            consignee: document.getElementById('inp_consignee').value,
            commodity_type: document.getElementById('inp_commodity_type').value,
            description: document.getElementById('inp_description').value,
            quantity: parseInt(document.getElementById('inp_quantity').value),
            declared_weight_kg: parseFloat(document.getElementById('inp_weight').value),
            driver_name: document.getElementById('inp_driver').value
        };

        fetch('api/cargo.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closeNewCargoModal();
                Swal.fire({
                    title: 'Kargo Berhasil Didaftarkan!',
                    html: `Nomor AWB: <b class="text-[#087F8A] font-mono">${res.awb_number}</b><br>GS1 SSCC: <b class="text-[#0CA1AF] font-mono">${res.sscc}</b>`,
                    icon: 'success',
                    background: '#ffffff',
                    color: '#0D1C42',
                    confirmButtonColor: '#087F8A',
                    customClass: {
                        popup: 'rounded-2xl shadow-2xl border border-gray-200'
                    }
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        });
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
