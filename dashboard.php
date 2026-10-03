<?php
// dashboard.php
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
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Top Greeting & Header Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 mb-8 border-b border-slate-800 gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <h1 class="text-2xl font-black text-white tracking-tight">Executive Dashboard Konsultan</h1>
                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-[#087F8A]/20 text-[#0CA1AF] border border-[#087F8A]/50 uppercase">
                    Live Operations
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Pemantauan Real-Time Alur Kargo Udara & Rekomendasi Solusi untuk <strong class="text-slate-200"><?= htmlspecialchars($user['organization']) ?></strong>
            </p>
        </div>

        <div class="flex items-center space-x-2">
            <button onclick="openNewCargoModal()" class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-semibold text-xs shadow-lg shadow-teal-500/20 transition-all flex items-center">
                <i class="fa-solid fa-plus mr-1.5"></i>
                <span>Input Kargo Baru</span>
            </button>
            <a href="simulation.php" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs border border-slate-700 transition-all flex items-center">
                <i class="fa-solid fa-play mr-1.5 text-[#0CA1AF]"></i>
                <span>Buka Lab Simulasi</span>
            </a>
            <button onclick="refreshDashboardData()" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700 text-xs transition-colors" title="Perbarui Data">
                <i class="fa-solid fa-arrows-rotate"></i>
            </button>
        </div>
    </div>

    <!-- KPI Summary Grid (Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        
        <!-- Metric 1: Total Inbound -->
        <div class="glass-panel p-5 rounded-2xl border border-slate-800 relative overflow-hidden group hover:border-[#0CA1AF]/50 transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-medium">Total Inbound Kargo</span>
                    <h3 id="stat-total-cargos" class="text-2xl font-black text-white mt-1 font-mono"><?= count($cargos) ?></h3>
                    <p class="text-[11px] text-[#0CA1AF] mt-1 flex items-center">
                        <i class="fa-solid fa-barcode mr-1"></i> Standar GS1 SSCC
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-[#087F8A]/20 border border-[#0CA1AF]/30 text-[#0CA1AF] flex items-center justify-center text-xl">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
            </div>
        </div>

        <!-- Metric 2: AVSEC Status -->
        <div class="glass-panel p-5 rounded-2xl border border-slate-800 relative overflow-hidden group hover:border-emerald-500/50 transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-medium">Pemeriksaan AVSEC X-Ray</span>
                    <div class="flex items-center space-x-2 mt-1">
                        <span id="stat-avsec-cleared" class="text-2xl font-black text-emerald-400 font-mono">0</span>
                        <span class="text-xs text-slate-400">Cleared /</span>
                        <span id="stat-avsec-suspect" class="text-base font-bold text-rose-400 font-mono">0</span>
                        <span class="text-[10px] text-rose-400">Suspect</span>
                    </div>
                    <p class="text-[11px] text-emerald-400 mt-1 flex items-center">
                        <i class="fa-solid fa-shield-halved mr-1"></i> CSD Digital Ready
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-950/70 border border-emerald-500/30 text-emerald-400 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-person-military-pointing"></i>
                </div>
            </div>
        </div>

        <!-- Metric 3: ULD Build-Up Status -->
        <div class="glass-panel p-5 rounded-2xl border border-slate-800 relative overflow-hidden group hover:border-[#0CA1AF]/50 transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-medium">Kontainer ULD Aktif</span>
                    <h3 class="text-2xl font-black text-white mt-1 font-mono"><?= count($ulds) ?></h3>
                    <p class="text-[11px] text-teal-300 mt-1 flex items-center">
                        <i class="fa-solid fa-weight-hanging mr-1"></i> Weight &amp; Balance Safe
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-[#014D54]/50 border border-[#0CA1AF]/30 text-teal-300 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-box-open"></i>
                </div>
            </div>
        </div>

        <!-- Metric 4: Flight Manifests -->
        <div class="glass-panel p-5 rounded-2xl border border-slate-800 relative overflow-hidden group hover:border-amber-500/50 transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-medium">Penerbangan Kargo</span>
                    <h3 class="text-2xl font-black text-white mt-1 font-mono"><?= count($flights) ?></h3>
                    <p class="text-[11px] text-amber-400 mt-1 flex items-center">
                        <i class="fa-solid fa-plane-up mr-1"></i> GA-707 &amp; SQ-801
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-950/70 border border-amber-500/30 text-amber-400 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-plane"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- 7-Stage Pipeline Live Breakdown Tracker -->
    <div class="glass-panel p-6 rounded-2xl border border-slate-800 mb-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-white flex items-center">
                    <i class="fa-solid fa-network-wired text-[#0CA1AF] mr-2"></i>
                    Status Pipeline Kargo di 7 Tahapan Rekomendasi
                </h3>
                <p class="text-[11px] text-slate-400">Monitoring jumlah kargo yang sedang berada di setiap workstation</p>
            </div>
            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">Real-Time Sync</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3" id="pipeline-counters-container">
            <!-- Stage 1 -->
            <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-800 text-center">
                <span class="text-[10px] text-slate-400 font-semibold block uppercase">1. Truk Tiba</span>
                <span id="stage-count-1" class="text-xl font-bold text-[#0CA1AF] font-mono my-1 block">0</span>
                <span class="text-[10px] text-slate-400">TMS/TAS</span>
            </div>
            <!-- Stage 2 -->
            <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-800 text-center">
                <span class="text-[10px] text-slate-400 font-semibold block uppercase">2. Scan RFID</span>
                <span id="stage-count-2" class="text-xl font-bold text-[#0CA1AF] font-mono my-1 block">0</span>
                <span class="text-[10px] text-slate-400">Gate AIDC</span>
            </div>
            <!-- Stage 3 -->
            <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-800 text-center">
                <span class="text-[10px] text-slate-400 font-semibold block uppercase">3. CMS e-AWB</span>
                <span id="stage-count-3" class="text-xl font-bold text-[#0CA1AF] font-mono my-1 block">0</span>
                <span class="text-[10px] text-slate-400">Registrasi</span>
            </div>
            <!-- Stage 4 -->
            <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-800 text-center">
                <span class="text-[10px] text-slate-400 font-semibold block uppercase">4. AVSEC X-Ray</span>
                <span id="stage-count-4" class="text-xl font-bold text-[#0CA1AF] font-mono my-1 block">0</span>
                <span class="text-[10px] text-slate-400">Dual-View</span>
            </div>
            <!-- Stage 5 -->
            <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-800 text-center">
                <span class="text-[10px] text-slate-400 font-semibold block uppercase">5. Penimbangan</span>
                <span id="stage-count-5" class="text-xl font-bold text-[#0CA1AF] font-mono my-1 block">0</span>
                <span class="text-[10px] text-slate-400">Scale 15T</span>
            </div>
            <!-- Stage 6 -->
            <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-800 text-center">
                <span class="text-[10px] text-slate-400 font-semibold block uppercase">6. Build ULD</span>
                <span id="stage-count-6" class="text-xl font-bold text-[#0CA1AF] font-mono my-1 block">0</span>
                <span class="text-[10px] text-slate-400">Weight &amp; Bal</span>
            </div>
            <!-- Stage 7 -->
            <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-800 text-center">
                <span class="text-[10px] text-slate-400 font-semibold block uppercase">7. Flight Load</span>
                <span id="stage-count-7" class="text-xl font-bold text-emerald-400 font-mono my-1 block">0</span>
                <span class="text-[10px] text-slate-400">Departed</span>
            </div>
        </div>
    </div>

    <!-- Middle Row: Charts & Flight Status -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        
        <!-- Chart 1: Commodity Types -->
        <div class="glass-panel p-5 rounded-2xl border border-slate-800">
            <h3 class="text-xs font-bold text-white mb-3 flex items-center">
                <i class="fa-solid fa-chart-pie text-[#0CA1AF] mr-2"></i>
                Komposisi Kargo Berdasarkan Komoditas
            </h3>
            <div class="h-52 relative flex items-center justify-center">
                <canvas id="commodityChart"></canvas>
            </div>
        </div>

        <!-- Chart 2: ULD Capacities -->
        <div class="glass-panel p-5 rounded-2xl border border-slate-800">
            <h3 class="text-xs font-bold text-white mb-3 flex items-center">
                <i class="fa-solid fa-chart-bar text-teal-300 mr-2"></i>
                Utilisasi Beban Kontainer ULD (kg)
            </h3>
            <div class="h-52 relative">
                <canvas id="uldWeightChart"></canvas>
            </div>
        </div>

        <!-- Panel 3: Active Flights & Loadsheet Status -->
        <div class="glass-panel p-5 rounded-2xl border border-slate-800 flex flex-col justify-between">
            <div>
                <h3 class="text-xs font-bold text-white mb-3 flex items-center">
                    <i class="fa-solid fa-plane-departure text-emerald-400 mr-2"></i>
                    Jadwal Penerbangan & Manifes Kargo
                </h3>
                <div class="space-y-2.5">
                    <?php foreach ($flights as $f): ?>
                        <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-white"><?= htmlspecialchars($f['flight_number']) ?></span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $f['status'] === 'CLOSED' ? 'bg-amber-950 text-amber-300 border border-amber-800' : 'bg-emerald-950 text-emerald-300 border border-emerald-800' ?>">
                                    <?= htmlspecialchars($f['status']) ?>
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">
                                Rute: <strong class="text-slate-200"><?= htmlspecialchars($f['origin']) ?> &rarr; <?= htmlspecialchars($f['destination']) ?></strong>
                            </p>
                            <div class="mt-2 flex items-center justify-between text-[11px] text-slate-400">
                                <span>Pesawat: <?= htmlspecialchars($f['aircraft_type']) ?></span>
                                <span class="font-mono text-[#0CA1AF]"><?= number_format($f['current_total_weight_kg']) ?> / <?= number_format($f['max_cargo_weight_kg']) ?> kg</span>
                            </div>
                            <div class="w-full bg-slate-800 h-1.5 rounded-full mt-1.5 overflow-hidden">
                                <?php 
                                    $pct = $f['max_cargo_weight_kg'] > 0 ? min(100, round(($f['current_total_weight_kg'] / $f['max_cargo_weight_kg']) * 100)) : 0;
                                ?>
                                <div class="bg-gradient-to-r from-[#04AFBF] to-[#087F8A] h-full rounded-full" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <a href="simulation.php" class="mt-4 block text-center py-2 px-3 rounded-xl bg-slate-800 hover:bg-[#087F8A]/30 text-[#0CA1AF] text-xs font-semibold border border-slate-700 hover:border-[#087F8A]/50 transition-colors">
                <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Buka Simulasi Pemuatan ULD
            </a>
        </div>

    </div>

    <!-- Active Cargo Shipments Table -->
    <div class="glass-panel rounded-2xl border border-slate-800 overflow-hidden">
        <div class="p-5 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-white flex items-center">
                    <i class="fa-solid fa-list-check text-[#0CA1AF] mr-2"></i>
                    Daftar Kargo Masuk & Status Tahapan Terkini
                </h3>
                <p class="text-[11px] text-slate-400">Pilih kargo untuk melanjutkan atau menguji simulasinya di laboratorium sistem</p>
            </div>
            <a href="database_viewer.php?table=cargo_shipments" class="text-xs text-[#0CA1AF] hover:text-teal-200 font-semibold flex items-center">
                <span>Buka di Database Explorer</span>
                <i class="fa-solid fa-chevron-right ml-1 text-[10px]"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-900/90 text-slate-400 font-medium border-b border-slate-800 uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="py-3 px-4">AWB & ID Kargo</th>
                        <th class="py-3 px-4">GS1 SSCC (18-Digit)</th>
                        <th class="py-3 px-4">Shipper & Komoditas</th>
                        <th class="py-3 px-4">Koli / Berat</th>
                        <th class="py-3 px-4">Tahapan & Status</th>
                        <th class="py-3 px-4">Alokasi Dock / ULD</th>
                        <th class="py-3 px-4 text-center">Aksi Simulasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                    <?php if (empty($cargos)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500 font-sans">
                                Belum ada data kargo. Silakan klik tombol "Input Kargo Baru" di atas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cargos as $c): ?>
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-white"><?= htmlspecialchars($c['awb_number']) ?></div>
                                    <span class="text-[10px] text-slate-500"><?= htmlspecialchars($c['id']) ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-[#0CA1AF] font-bold"><?= htmlspecialchars($c['sscc']) ?></span>
                                    <span class="text-[10px] text-slate-500 block">GTIN: <?= htmlspecialchars($c['gtin']) ?></span>
                                </td>
                                <td class="py-3 px-4 font-sans">
                                    <div class="text-slate-200 font-medium"><?= htmlspecialchars($c['shipper']) ?></div>
                                    <div class="text-[11px] text-slate-400 truncate max-w-xs"><?= htmlspecialchars($c['description']) ?></div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-white font-bold"><?= $c['quantity'] ?> koli</span>
                                    <span class="text-[10px] text-slate-400 block"><?= number_format($c['actual_weight_kg'] ?? $c['declared_weight_kg'], 1) ?> kg</span>
                                </td>
                                <td class="py-3 px-4 font-sans">
                                    <div class="flex items-center space-x-1.5">
                                        <span class="w-5 h-5 rounded-full bg-[#087F8A]/20 text-[#0CA1AF] border border-[#087F8A]/50 text-[10px] font-bold flex items-center justify-center">
                                            <?= $c['current_stage'] ?>
                                        </span>
                                        <?php
                                            $badgeClass = 'bg-slate-800 text-slate-300';
                                            if ($c['status'] === 'CLEARED') $badgeClass = 'bg-emerald-950/80 text-emerald-300 border border-emerald-700/60';
                                            elseif ($c['status'] === 'SUSPECT') $badgeClass = 'bg-rose-950/80 text-rose-300 border border-rose-700/60';
                                            elseif ($c['status'] === 'LOADED') $badgeClass = 'bg-[#014D54] text-teal-200 border border-teal-600/60';
                                            elseif ($c['status'] === 'ALLOCATED') $badgeClass = 'bg-[#087F8A]/20 text-[#0CA1AF] border border-[#087F8A]/50';
                                        ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $badgeClass ?>">
                                            <?= htmlspecialchars($c['status']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-slate-300">Dock: <span class="text-[#0CA1AF]"><?= htmlspecialchars($c['dock_slot'] ?? '-') ?></span></div>
                                    <div class="text-[10px] text-slate-500">ULD: <?= htmlspecialchars($c['uld_code'] ?? 'Belum Di-build') ?></div>
                                </td>
                                <td class="py-3 px-4 text-center font-sans">
                                    <a href="simulation.php?cargo_id=<?= urlencode($c['id']) ?>" class="inline-flex items-center px-2.5 py-1 rounded-lg bg-[#087F8A]/20 hover:bg-[#087F8A] text-[#0CA1AF] hover:text-white border border-[#087F8A]/40 text-[11px] font-semibold transition-all">
                                        <i class="fa-solid fa-play mr-1 text-[10px]"></i> Simulasi
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

<!-- Modal Input Kargo Baru -->
<div id="newCargoModal" class="fixed inset-0 z-50 hidden bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="glass-panel w-full max-w-lg rounded-2xl border border-slate-700 p-6 shadow-2xl relative">
        <button onclick="closeNewCargoModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-lg">
            <i class="fa-solid fa-xmark"></i>
        </button>
        
        <h3 class="text-base font-bold text-white mb-1 flex items-center">
            <i class="fa-solid fa-truck-ramp-box text-[#0CA1AF] mr-2"></i>
            Input Kargo Inbound Baru (TMS Gate)
        </h3>
        <p class="text-xs text-slate-400 mb-5">Sistem akan secara otomatis men-generate nomor SSCC 18 digit standar GS1 dan kode booking slot dock.</p>

        <form id="newCargoForm" onsubmit="handleCreateCargo(event)" class="space-y-3.5 text-xs">
            <div>
                <label class="block text-slate-300 font-medium mb-1">Nama Pengirim (Shipper)</label>
                <input type="text" id="inp_shipper" required value="PT Indo Pharma Laboratories" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Penerima (Consignee)</label>
                    <input type="text" id="inp_consignee" required value="Singapore General Hospital Logistics" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none">
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Tipe Komoditas</label>
                    <select id="inp_commodity_type" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none">
                        <option value="GENERAL_CARGO">General Cargo (Umum)</option>
                        <option value="PHARMA" selected>Pharma / Vaksin (Cold Chain)</option>
                        <option value="PERISHABLE">Perishable (Makanan/Ikan)</option>
                        <option value="VALUABLE">Valuable Goods (Elektronik)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-slate-300 font-medium mb-1">Deskripsi Barang</label>
                <input type="text" id="inp_description" required value="Vaksin Rantai Dingin & Suplemen Kesehatan" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none">
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Jumlah Koli</label>
                    <input type="number" id="inp_quantity" required value="60" min="1" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none">
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Berat Deklarasi (kg)</label>
                    <input type="number" step="0.1" id="inp_weight" required value="420.0" min="1" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none">
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Supir Truk</label>
                    <input type="text" id="inp_driver" required value="Joko Susanto" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] focus:outline-none">
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeNewCargoModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white text-xs font-semibold">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-semibold text-xs shadow-lg shadow-teal-500/20">
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
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#94a3b8', font: { size: 10 } }
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
                    backgroundColor: ['#64748b', '#087F8A', '#0CA1AF', '#10b981'],
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
                        ticks: { stepSize: 1, color: '#94a3b8' },
                        grid: { color: '#162a52' }
                    },
                    x: {
                        ticks: { color: '#94a3b8' },
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
                    html: `AWB: <b>${res.awb_number}</b><br>GS1 SSCC: <b>${res.sscc}</b>`,
                    icon: 'success',
                    background: '#0D1C42',
                    color: '#fff',
                    confirmButtonColor: '#087F8A'
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
