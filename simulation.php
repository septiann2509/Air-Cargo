<?php
// simulation.php
// Laboratorium Simulasi Interaktif 7 Tahapan Perpindahan Data Kargo (InJourney Airports Design System)
// Dedicated System Dashboards: TMS, CMS, AVSEC System, Load & Balance / Load Planning System + Alur Terpadu
require_once __DIR__ . '/config/database.php';
requireAuth();

$user = getCurrentUser();
$db = getDb();

// Dapatkan daftar kargo
$cargosStmt = $db->query("SELECT id, awb_number, sscc, shipper, description, current_stage, status FROM cargo_shipments ORDER BY created_at DESC");
$cargos = $cargosStmt->fetchAll();

// Kargo terpilih & Tahap awal & Sistem aktif
$selectedCargoId = $_GET['cargo_id'] ?? ($cargos[0]['id'] ?? '');
$initialStage = (int)($_GET['stage'] ?? 0);
$activeSystem = $_GET['system'] ?? 'all';
if (!in_array($activeSystem, ['all', 'tms', 'cms', 'avsec', 'load_balance'])) {
    $activeSystem = 'all';
}

// Data pendukung untuk Dashboard Fitur Spesifik
$trucks = $db->query("SELECT id, truck_plate, driver_name, transporter_company, dock_slot, booking_code, arrival_time, status FROM truck_arrivals ORDER BY arrival_time DESC, created_at DESC LIMIT 20")->fetchAll();
$totalTrucksCount = (int)$db->query("SELECT COUNT(*) FROM truck_arrivals")->fetchColumn();
$ulds = $db->query("SELECT u.*, f.flight_number, f.destination FROM uld_containers u LEFT JOIN flight_manifests f ON u.flight_id = f.id ORDER BY u.uld_code ASC")->fetchAll();
$flights = $db->query("SELECT * FROM flight_manifests ORDER BY departure_time ASC")->fetchAll();
$secChecks = $db->query("SELECT s.*, c.awb_number, c.commodity_type, c.sscc FROM security_checks s JOIN cargo_shipments c ON s.cargo_id = c.id ORDER BY s.check_time DESC LIMIT 6")->fetchAll();
$weightRecords = $db->query("SELECT w.*, c.awb_number FROM weight_records w JOIN cargo_shipments c ON w.cargo_id = c.id ORDER BY w.weigh_time DESC LIMIT 6")->fetchAll();

$pageTitle = 'Laboratorium Simulasi 7-Tahap & Dedicated System Dashboards';
$bodyClass = 'bg-[#F8FAFC] text-slate-800';
include __DIR__ . '/includes/header.php';
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

    <!-- KONTEN UTAMA SIMULASI (Tengah) -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    
        <!-- Top Bar: Title & Cargo Selector (InJourney White Card) -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2.5">
                    <span class="w-2.5 h-6 rounded-full bg-gradient-to-b from-[#0CA1AF] to-[#087F8A]"></span>
                    <h1 class="text-2xl lg:text-3xl font-extrabold text-[#0D1C42] tracking-tight">Laboratorium Simulasi Aliran Data (PoC)</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#087F8A]/10 text-[#087F8A] border border-[#087F8A]/30 uppercase tracking-wider">
                        Interactive Engine
                    </span>
                </div>
                <p class="text-xs lg:text-sm text-slate-500 mt-1 pl-5">
                    Visualisasi terpadu perpindahan data antara <strong class="text-[#0D1C42] font-semibold">Hardware Lapangan (Layer 1)</strong> dan <strong class="text-[#0D1C42] font-semibold">Sistem Software (Layer 3)</strong> dari <strong class="text-[#087F8A] font-semibold">Kedatangan Truk (TMS) hingga Flight Loading</strong>.
                </p>
            </div>

            <!-- Selector & Global Action Buttons -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center space-x-2 bg-slate-50 px-3.5 py-2 rounded-xl border border-gray-200 shadow-inner">
                    <label for="cargoSelect" class="text-xs text-slate-500 font-bold">Pilih Kargo:</label>
                    <select id="cargoSelect" onchange="changeCargo(this.value)" class="bg-transparent text-xs font-bold text-[#0D1C42] focus:outline-none cursor-pointer">
                        <?php foreach ($cargos as $cg): ?>
                            <option value="<?= htmlspecialchars($cg['id']) ?>" class="bg-white text-slate-800" <?= $cg['id'] === $selectedCargoId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cg['awb_number']) ?> &bull; <?= htmlspecialchars($cg['description']) ?> (Tahap <?= $cg['current_stage'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Auto-Run Demo Button -->
                <button onclick="triggerAutoRun()" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-lg shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-wand-magic-sparkles mr-1.5"></i>
                    <span>Auto-Run Demo (1-Click)</span>
                </button>

                <!-- Reset Button -->
                <button onclick="confirmResetData()" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-600 hover:text-rose-600 text-xs font-bold border border-gray-300 shadow-sm transition-colors flex items-center">
                    <i class="fa-solid fa-rotate-left mr-1.5"></i> Reset Demo
                </button>
            </div>
        </div>

        <!-- Active Cargo Header Banner -->
        <div id="cargoHeaderCard" class="bg-white p-5 rounded-2xl border border-gray-200/90 shadow-sm relative overflow-hidden">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 text-xs">
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-1">Nomor Air Waybill</span>
                    <span id="card-awb" class="text-[#0D1C42] font-mono font-black text-sm tracking-tight">-</span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-1">GS1 SSCC (18-Digit)</span>
                    <span id="card-sscc" class="text-[#087F8A] font-mono font-bold">-</span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-1">Shipper &amp; Penerima</span>
                    <span id="card-shipper" class="text-slate-700 truncate block font-medium">-</span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-1">Komoditas &amp; Koli</span>
                    <span id="card-commodity" class="text-slate-700 block font-semibold">-</span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-1">Berat (Deklarasi / Timbang)</span>
                    <span id="card-weight" class="text-slate-700 font-mono block font-semibold">-</span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-1">Status Operasional</span>
                    <span id="card-status-badge" class="inline-block px-3 py-1 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-600 border border-slate-200">
                        LOADING...
                    </span>
                </div>
            </div>

            <!-- Master Cargo JSON Payload Sub-Banner (Hardware Surrogate PoC) -->
            <div class="mt-3.5 pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center space-x-2">
                    <span class="px-2.5 py-1 rounded-lg bg-teal-50 border border-teal-200 text-[#087F8A] font-bold text-[11px] flex items-center gap-1.5">
                        <i class="fa-solid fa-microchip text-[#0CA1AF]"></i>
                        <span>Kode JSON Payload Kargo:</span>
                        <span id="card-payload-id" class="font-mono font-black text-[#0D1C42]">-</span>
                    </span>
                    <span class="text-[11px] text-slate-500 hidden sm:inline">&bull; 1 Dokumen JSON Terpadu Pengganti Hardware Lapangan (Tahap 1 s/d 7)</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="openMasterCargoPayloadModal()" class="px-3 py-1 rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-700 hover:text-[#087F8A] font-bold text-[11px] border border-gray-200 hover:border-teal-300 transition-all flex items-center gap-1.5 shadow-sm cursor-pointer">
                        <i class="fa-solid fa-code text-[#087F8A]"></i>
                        <span>Lihat Master JSON Kargo</span>
                    </button>
                    <button type="button" onclick="copyMasterPayloadJson()" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] font-semibold border border-gray-200 transition-all cursor-pointer" title="Salin Master JSON Kargo">
                        <i class="fa-solid fa-copy"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- SYSTEM DASHBOARD SWITCHER TABS (Pembagian Khusus Sesuai Alur: Kedatangan Truk s/d Flight Loading) -->
        <div class="bg-white p-3 rounded-2xl border border-gray-200/90 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-1.5" id="systemTabSwitcher">
                <!-- Tab 1: Alur Terpadu 7-Tahap -->
                <button type="button" onclick="switchSystemDashboard('all')" id="tabBtn-all" class="system-tab-btn px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 bg-[#087F8A] text-white shadow-md shadow-teal-500/20">
                    <i class="fa-solid fa-arrows-split-up-and-left text-sm"></i>
                    <span>Alur Terpadu 7-Tahap</span>
                    <span class="text-[9px] px-2 py-0.5 rounded-full bg-white/20 text-white font-extrabold tracking-wide uppercase">All-in-One</span>
                </button>

                <!-- Tab 2: Dashboard TMS -->
                <button type="button" onclick="switchSystemDashboard('tms')" id="tabBtn-tms" class="system-tab-btn px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 bg-slate-50 text-slate-700 hover:bg-teal-50 hover:text-[#087F8A] border border-gray-200">
                    <i class="fa-solid fa-truck text-sm"></i>
                    <span>Dashboard TMS</span>
                    <span class="text-[9px] px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 font-extrabold">Tahap 1</span>
                </button>

                <!-- Tab 3: Dashboard CMS -->
                <button type="button" onclick="switchSystemDashboard('cms')" id="tabBtn-cms" class="system-tab-btn px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 bg-slate-50 text-slate-700 hover:bg-teal-50 hover:text-[#087F8A] border border-gray-200">
                    <i class="fa-solid fa-laptop-code text-sm"></i>
                    <span>Dashboard CMS</span>
                    <span class="text-[9px] px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 font-extrabold">Tahap 2-3</span>
                </button>

                <!-- Tab 4: Dashboard AVSEC System -->
                <button type="button" onclick="switchSystemDashboard('avsec')" id="tabBtn-avsec" class="system-tab-btn px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 bg-slate-50 text-slate-700 hover:bg-teal-50 hover:text-[#087F8A] border border-gray-200">
                    <i class="fa-solid fa-shield-halved text-sm"></i>
                    <span>Dashboard AVSEC</span>
                    <span class="text-[9px] px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 font-extrabold">Tahap 4</span>
                </button>

                <!-- Tab 5: Dashboard Load & Balance System -->
                <button type="button" onclick="switchSystemDashboard('load_balance')" id="tabBtn-load_balance" class="system-tab-btn px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 bg-slate-50 text-slate-700 hover:bg-teal-50 hover:text-[#087F8A] border border-gray-200">
                    <i class="fa-solid fa-scale-balanced text-sm"></i>
                    <span>Load &amp; Balance</span>
                    <span class="text-[9px] px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 font-extrabold">Tahap 5-7</span>
                </button>
            </div>

            <!-- Active Indicator Pill -->
            <div class="flex items-center gap-2 text-xs text-slate-600 bg-slate-50 px-3 py-1.5 rounded-xl border border-gray-200/80">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span id="activeSystemIndicatorText" class="font-bold text-[#0D1C42]">Alur Terpadu End-to-End</span>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- VIEW 1: ALUR TERPADU 7-TAHAP (DEFAULT MASTER FLOW VIEW)                    -->
        <!-- ========================================================================= -->
        <div id="view-system-all" class="system-view space-y-6">
            
            <!-- 7-Stage Interactive Progress Bar -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                    <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider flex items-center">
                        <i class="fa-solid fa-arrows-split-up-and-left text-[#087F8A] mr-2 text-sm"></i>
                        Progres 7 Tahapan Perpindahan Data Kargo
                    </h3>
                    <span id="currentStageBadge" class="text-[11px] font-mono px-3 py-1 rounded-full bg-teal-50 text-[#087F8A] border border-teal-200 font-bold">
                        Tahap Aktif: 1
                    </span>
                </div>

                <!-- Visual Stepper -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2.5" id="stepperContainer">
                    <?php
                    $stagesConfig = [
                        1 => ['icon' => 'fa-truck', 'name' => '1. Kedatangan Truk', 'sub' => 'TMS / TAS Gate'],
                        2 => ['icon' => 'fa-code', 'name' => '2. Ingest JSON Data', 'sub' => 'AIDC JSON Stream'],
                        3 => ['icon' => 'fa-laptop-code', 'name' => '3. Registrasi CMS', 'sub' => 'e-AWB Matching'],
                        4 => ['icon' => 'fa-shield-halved', 'name' => '4. X-Ray AVSEC', 'sub' => 'Dual-View Scan'],
                        5 => ['icon' => 'fa-scale-balanced', 'name' => '5. Penimbangan', 'sub' => 'Smart ULD Scale'],
                        6 => ['icon' => 'fa-boxes-packing', 'name' => '6. Build-Up ULD', 'sub' => 'Weight & Balance'],
                        7 => ['icon' => 'fa-plane-departure', 'name' => '7. Flight Loading', 'sub' => 'Manifes Final']
                    ];
                    foreach ($stagesConfig as $num => $stg):
                    ?>
                        <button type="button" onclick="selectStageView(<?= $num ?>)" id="step-btn-<?= $num ?>" class="stage-step-card text-left p-3.5 rounded-xl border border-gray-200 bg-slate-50/70 hover:border-[#0CA1AF] hover:bg-teal-50/30 transition-all flex flex-col justify-between">
                            <div class="flex items-center justify-between mb-2">
                                <span id="step-circle-<?= $num ?>" class="w-6 h-6 rounded-full bg-slate-200 text-slate-600 text-xs font-bold flex items-center justify-center font-mono">
                                    <?= $num ?>
                                </span>
                                <i class="fa-solid <?= $stg['icon'] ?> text-slate-400 text-xs" id="step-icon-<?= $num ?>"></i>
                            </div>
                            <div>
                                <div class="text-[11px] font-bold text-slate-700 leading-snug" id="step-title-<?= $num ?>"><?= $stg['name'] ?></div>
                                <div class="text-[10px] text-slate-500 mt-0.5"><?= $stg['sub'] ?></div>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Main Workspace: Stage Action Simulator & JSON Payload Inspector -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <!-- Left Column: Stage Visualizer & Interactive Triggers (7 cols) -->
                <div class="lg:col-span-7 space-y-6">
                    
                    <!-- Stage Detail & Execution Card -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <!-- Stage Header -->
                        <div class="flex items-center justify-between pb-4 mb-5 border-b border-gray-100">
                            <div class="flex items-center space-x-3">
                                <div id="activeStageIconWrap" class="w-12 h-12 rounded-xl bg-teal-50 border border-teal-200 text-[#087F8A] flex items-center justify-center text-xl shadow-sm">
                                    <i id="activeStageIcon" class="fa-solid fa-truck"></i>
                                </div>
                                <div>
                                    <span id="activeStageNumber" class="text-[10px] font-mono uppercase tracking-wider text-[#087F8A] font-bold">Tahap 1 dari 7</span>
                                    <h2 id="activeStageTitle" class="text-base lg:text-lg font-bold text-[#0D1C42]">Kedatangan Truk &amp; Booking Slot (TMS/TAS)</h2>
                                </div>
                            </div>
                            <span id="stageCompleteBadge" class="text-xs px-3 py-1 rounded-full font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                Status: Pending
                            </span>
                        </div>

                        <!-- Hardware & Software Badges (Layer 1 Virtual Sensing via JSON Payload) -->
                        <div class="grid grid-cols-2 gap-3 mb-5 text-xs">
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] text-slate-500 font-bold block uppercase tracking-wider">Layer 1: Hardware Lapangan</span>
                                    <span class="text-[9px] font-mono font-bold text-[#087F8A] bg-teal-50 px-2 py-0.5 rounded border border-teal-200 flex items-center gap-1">
                                        <i class="fa-solid fa-code text-[8px]"></i> JSON Payload Surrogate
                                    </span>
                                </div>
                                <span id="activeHardwareName" class="text-[#0D1C42] font-bold block mt-1">-</span>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200">
                                <span class="text-[10px] text-slate-500 font-bold block uppercase tracking-wider">Layer 3: Software Sistem</span>
                                <span id="activeSoftwareName" class="text-[#0D1C42] font-bold block mt-0.5">-</span>
                            </div>
                        </div>

                        <!-- Interactive Stage Visual Graphics -->
                        <div id="stageVisualGraphic" class="p-6 rounded-xl bg-gradient-to-br from-slate-50 to-teal-50/30 border border-gray-200 mb-6 flex flex-col items-center justify-center text-center relative overflow-hidden min-h-[175px] shadow-inner">
                            <!-- Dynamic Graphic inserted via JS -->
                        </div>

                        <!-- Stage Explanation / Advisory Recommendation -->
                        <div class="mb-6 p-4 rounded-xl bg-teal-50/50 border border-teal-100 text-xs">
                            <h4 class="text-[#0D1C42] font-bold mb-1 flex items-center text-xs">
                                <i class="fa-solid fa-lightbulb text-amber-500 mr-2"></i>
                                Penjelasan Konsultan (To-Be Architecture):
                            </h4>
                            <p id="activeStageExplanation" class="text-slate-600 leading-relaxed text-[11.5px]">
                                -
                            </p>
                        </div>

                        <!-- Action Buttons Container -->
                        <div id="stageActionButtons" class="pt-2 flex flex-wrap items-center gap-3">
                            <!-- Dynamic Buttons rendered via JS based on current stage -->
                        </div>

                        <!-- Quick Stage Navigation Toolbar -->
                        <div class="mt-5 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                            <button type="button" onclick="navigateStep(-1)" id="btnNavPrev" class="px-3.5 py-2 rounded-xl border border-gray-200 bg-white hover:bg-slate-50 text-slate-700 font-bold flex items-center gap-1.5 shadow-sm transition-all" title="Kembali ke Tahap Sebelumnya">
                                <i class="fa-solid fa-arrow-left text-[11px]"></i>
                                <span>Tahap Sebelumnya</span>
                            </button>
                            <div class="flex items-center gap-1 font-mono text-[11px]">
                                <span class="text-[10px] uppercase font-bold text-slate-400 mr-1 font-sans">Lompat:</span>
                                <?php for($i = 1; $i <= 7; $i++): ?>
                                    <button type="button" onclick="selectStageView(<?= $i ?>)" id="quick-stage-<?= $i ?>" class="w-8 h-8 rounded-xl border border-gray-200 bg-white hover:bg-teal-50 hover:border-teal-300 hover:text-[#087F8A] font-bold text-slate-700 transition-all flex items-center justify-center" title="Buka Langsung Tahap <?= $i ?>">
                                        <?= $i ?>
                                    </button>
                                <?php endfor; ?>
                            </div>
                            <button type="button" onclick="navigateStep(1)" id="btnNavNext" class="px-3.5 py-2 rounded-xl border border-teal-200 bg-teal-50 hover:bg-teal-100 text-[#087F8A] font-bold flex items-center gap-1.5 shadow-sm transition-all" title="Lanjut ke Tahap Selanjutnya">
                                <span>Tahap Selanjutnya</span>
                                <i class="fa-solid fa-arrow-right text-[11px]"></i>
                            </button>
                        </div>
                    </div>

                    <!-- GS1 e-Label Anatomy Visualization -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-100">
                            <h3 class="text-xs font-bold text-[#0D1C42] flex items-center">
                                <i class="fa-solid fa-tag text-[#087F8A] mr-2"></i>
                                Anatomi Standar GS1 e-Label (Pallet &amp; Cargo Unit)
                            </h3>
                            <span class="text-[10px] font-mono text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-gray-200 font-semibold">GS1-128 / SSCC-18</span>
                        </div>
                        
                        <div class="bg-white text-slate-900 p-4 rounded-xl font-mono text-[11px] shadow-sm border-2 border-dashed border-gray-300">
                            <div class="border-b border-gray-200 pb-2 mb-2 grid grid-cols-2 gap-2 text-[10px]">
                                <div>
                                    <strong class="text-slate-800">SHIP TO:</strong> <span id="label-dest" class="text-slate-700">DPS - DENPASAR</span><br>
                                    <strong class="text-slate-800">CONSIGNEE:</strong> <span id="label-consignee" class="text-slate-700">-</span>
                                </div>
                                <div class="text-right">
                                    <strong class="text-slate-800">AWB:</strong> <span id="label-awb" class="text-slate-700">-</span><br>
                                    <strong class="text-slate-800">PIECES:</strong> <span id="label-qty" class="text-slate-700">-</span> | <strong class="text-slate-800">WEIGHT:</strong> <span id="label-weight" class="text-slate-700">-</span>
                                </div>
                            </div>
                            <div class="border-b border-gray-200 pb-2 mb-2 text-center">
                                <div class="h-8 barcode-stripe rounded w-full mb-1"></div>
                                <div class="text-[9px] text-slate-600 font-bold">
                                    (01) <span id="label-gtin">-</span> (10) <span id="label-batch">-</span> (17) <span id="label-exp">-</span>
                                </div>
                            </div>
                            <div class="text-center pt-1">
                                <div class="h-10 barcode-stripe rounded w-full mb-1"></div>
                                <div class="text-xs font-black tracking-widest text-[#0D1C42]">
                                    (00) <span id="label-sscc">-</span>
                                </div>
                                <span class="text-[8px] text-slate-500 uppercase tracking-tighter block mt-0.5">Serial Shipping Container Code (SSCC) — Primary Key Unit Logistik</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Live JSON Payload Inspector & Transaction Logs (5 cols) -->
                <div class="lg:col-span-5 space-y-6">
                    
                    <!-- JSON Payload Inspector Card -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm flex flex-col h-[520px]">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-3">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#087F8A]"></span>
                                <h3 class="text-xs font-bold text-[#0D1C42]">REST API &amp; JSON Payload Inspector</h3>
                            </div>
                            <button onclick="copyPayloadToClipboard()" class="text-[10px] text-slate-600 hover:text-[#087F8A] px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 border border-gray-200 transition-colors font-semibold flex items-center">
                                <i class="fa-solid fa-copy mr-1.5 text-[#087F8A]"></i> Salin JSON
                            </button>
                        </div>

                        <!-- Sub-Tabs: Request vs Response vs Master JSON -->
                        <div class="flex space-x-1.5 mb-3">
                            <button id="tabBtnRequest" onclick="switchPayloadTab('request')" class="flex-1 py-1.5 px-2 rounded-lg text-xs font-bold bg-[#087F8A] text-white shadow-sm transition-all flex items-center justify-center cursor-pointer">
                                <i class="fa-solid fa-arrow-up-right-from-square mr-1 text-[11px]"></i> Request
                            </button>
                            <button id="tabBtnResponse" onclick="switchPayloadTab('response')" class="flex-1 py-1.5 px-2 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:text-slate-800 border border-gray-200 transition-all flex items-center justify-center cursor-pointer">
                                <i class="fa-solid fa-arrow-down-left-and-up-right-to-center mr-1 text-[11px]"></i> Response
                            </button>
                            <button id="tabBtnMaster" onclick="switchPayloadTab('master')" class="flex-1 py-1.5 px-2 rounded-lg text-xs font-semibold bg-slate-100 text-[#087F8A] hover:bg-teal-50 hover:text-[#0D1C42] border border-gray-200 transition-all flex items-center justify-center font-bold cursor-pointer" title="Lihat Satu Dokumen JSON Terpadu untuk Kargo Ini">
                                <i class="fa-solid fa-box-open mr-1 text-[11px]"></i> Master JSON
                            </button>
                        </div>

                        <!-- Code Container with Scrollbar -->
                        <div class="flex-grow overflow-auto bg-[#0D1C42] border border-slate-700/80 rounded-xl p-4 text-xs font-mono relative shadow-inner">
                            <pre id="jsonPayloadDisplay" class="text-emerald-300 leading-relaxed text-[11px]">// Memuat data JSON payload...</pre>
                        </div>

                        <!-- Standard Compliance Indicator -->
                        <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-[11px]">
                            <span class="text-slate-500 font-medium">Standar Protokol:</span>
                            <span id="payloadStandardBadge" class="font-mono text-emerald-700 font-bold bg-emerald-50 px-2.5 py-0.5 rounded-lg border border-emerald-200 text-[10px]">
                                GS1 AIDC / JSON Schema
                            </span>
                        </div>
                    </div>

                    <!-- Transaction Audit Trail Logs (Timeline) -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-100">
                            <h3 class="text-xs font-bold text-[#0D1C42] flex items-center">
                                <i class="fa-solid fa-clock-rotate-left text-[#087F8A] mr-2"></i>
                                Audit Trail Perpindahan Data (Database Log)
                            </h3>
                            <span id="logCountBadge" class="text-[10px] font-mono text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full border border-gray-200 font-bold">0 Record</span>
                        </div>

                        <div id="auditTimelineContainer" class="space-y-2.5 max-h-60 overflow-y-auto pr-1">
                            <p class="text-slate-400 text-xs text-center py-4">Belum ada aktivitas terekam.</p>
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- VIEW 2: DASHBOARD TMS (TRANSPORT MANAGEMENT SYSTEM & TAS GATE - TAHAP 1) -->
        <!-- ========================================================================= -->
        <div id="view-system-tms" class="system-view space-y-6 hidden">
            
            <!-- TMS System Header Banner -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-gray-100">
                    <div class="flex items-center space-x-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-teal-50 border border-teal-200 text-[#087F8A] flex items-center justify-center text-2xl shadow-sm">
                            <i class="fa-solid fa-truck"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h2 class="text-lg lg:text-xl font-extrabold text-[#0D1C42]">Dashboard TMS — Transport Management System &amp; TAS Gate</h2>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-[#087F8A] border border-teal-200">
                                    Tahap 1 Operasional
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Inbound Gateway Management: Pengaturan Slot Kedatangan Truk Kontainer, Barrier Gate Otomatis, &amp; Alokasi Dock Unloading.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-mono text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-200 font-bold flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            TAS Portal Online
                        </span>
                    </div>
                </div>

                <!-- 4-Layer Architecture Pill Strip -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-[11px]">
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 1 (Hardware)</span>
                        <span class="font-bold text-[#0D1C42] block mt-0.5">Long-Range RFID &amp; Boom Barrier</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 2 (Network)</span>
                        <span class="font-bold text-[#0D1C42] block mt-0.5">Wi-Fi 6 Industrial &amp; Fiber Optic</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 3 (Software)</span>
                        <span class="font-bold text-[#0D1C42] block mt-0.5">Intermodal TMS / TAS Web Portal</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 4 (Protocol)</span>
                        <span class="font-bold text-[#087F8A] block mt-0.5">EDIFACT / REST API Ingest</span>
                    </div>
                </div>
            </div>

            <!-- TMS Live KPI Strip -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Truk Terjadwal Hari Ini</span>
                        <span id="tms-total-trucks-kpi" class="text-2xl font-black text-[#0D1C42] font-mono mt-1 block"><?= $totalTrucksCount ?> <span class="text-xs text-slate-500 font-sans font-normal">Armada</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 text-[#087F8A] flex items-center justify-center text-lg">
                        <i class="fa-solid fa-truck-moving"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Alokasi Dock Slot</span>
                        <span id="tms-dock-summary-stat" class="text-2xl font-black text-emerald-600 font-mono mt-1 block">0/4 Terisi <span class="text-xs text-slate-500 font-sans font-normal">4 Kosong</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-warehouse"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Efisiensi Dwell Time</span>
                        <span class="text-2xl font-black text-[#087F8A] font-mono mt-1 block">-81% <span class="text-xs text-emerald-600 font-sans font-bold">15 mnt</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 text-[#087F8A] flex items-center justify-center text-lg">
                        <i class="fa-solid fa-gauge-high"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Status Barrier Gate</span>
                        <span id="tms-barrier-status-kpi" class="text-2xl font-black text-emerald-700 font-mono mt-1 block">READY <span class="text-xs text-slate-500 font-sans font-normal">1.2s Boom</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-torii-gate"></i>
                    </div>
                </div>
            </div>

            <!-- TMS Main Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left Column: Dock Management & Auto-Synced Truck Console (7 cols) -->
                <div class="lg:col-span-7 space-y-6">
                    
                    <!-- Visual Dock Allocation Cards -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 mb-4 border-b border-gray-100 gap-2">
                            <div>
                                <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider flex items-center">
                                    <i class="fa-solid fa-dolly text-[#087F8A] mr-2 text-sm"></i>
                                    Status Live Terminal Cargo Docks (Inbound Unloading Bay)
                                </h3>
                                <p class="text-[10px] text-slate-500 mt-0.5">Alokasi dock otomatis tersinkron dari database <code class="font-mono text-[#087F8A]">truck_arrivals</code> &mdash; klik kartu untuk inspeksi detail armada</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span id="tms-dock-occupied-badge" class="text-[10px] font-mono text-rose-700 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-200 font-bold">0 Terisi</span>
                                <span id="tms-dock-available-badge" class="text-[10px] font-mono text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200 font-bold">4 Kosong</span>
                            </div>
                        </div>

                        <!-- 4 Interactive Inspection Dock Cards (Read-Only Sync dari Database) -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" id="tmsDockCardsContainer">
                            <?php foreach (['DOCK-01', 'DOCK-02', 'DOCK-03', 'DOCK-04'] as $dockName): ?>
                                <div id="dock-card-<?= $dockName ?>" onclick="handleDockCardClick('<?= $dockName ?>')" class="p-3.5 rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50/20 text-center transition-all cursor-pointer hover:border-emerald-500 hover:bg-emerald-50 hover:shadow-md relative overflow-hidden group select-none" title="Klik untuk lihat rincian dock">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold text-emerald-700 uppercase font-mono"><?= str_replace('-', ' ', $dockName) ?></span>
                                        <span id="dock-badge-<?= $dockName ?>" class="text-[8.5px] px-1.5 py-0.5 rounded font-extrabold uppercase bg-emerald-100 text-emerald-700 border border-emerald-200">KOSONG</span>
                                    </div>
                                    <div id="dock-icon-<?= $dockName ?>" class="my-2 text-2xl text-emerald-500 group-hover:scale-105 transition-transform">
                                        <i class="fa-solid fa-circle-check"></i>
                                    </div>
                                    <span id="dock-plate-<?= $dockName ?>" class="text-xs font-bold text-emerald-700 block font-mono truncate">TERSEDIA</span>
                                    <span id="dock-status-<?= $dockName ?>" class="text-[9px] text-emerald-700 font-semibold block mt-0.5 truncate">Standby Leveler</span>
                                    <span id="dock-sub-<?= $dockName ?>" class="text-[8.5px] text-slate-400 font-semibold block mt-1">Klik Info</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Auto-Synced TMS Truck Arrival Console (Zero Re-Entry Read-Only View) -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm relative overflow-hidden">
                        
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 mb-4 border-b border-gray-100 gap-2">
                            <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                                <i class="fa-solid fa-id-card-clip text-[#087F8A] text-base"></i>
                                <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider">
                                    Data Kedatangan Truk di TMS (Auto-Sync)
                                </h3>
                                <span class="px-2.5 py-0.5 rounded-full text-[9.5px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase tracking-wide">
                                    Zero Re-entry &bull; Khusus TMS
                                </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span id="tms-cargo-stage-badge" class="text-[10px] font-mono text-[#087F8A] bg-teal-50 px-2.5 py-1 rounded-full border border-teal-200 font-bold">
                                    Tahap 1: SCHEDULED
                                </span>
                            </div>
                        </div>

                        <!-- Zero Re-entry & Data Filtering Info Strip -->
                        <div class="mb-4 p-3.5 rounded-xl bg-teal-50/70 border border-teal-200/90 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="space-y-0.5">
                                <div class="font-bold text-[#0D1C42] flex items-center gap-1.5">
                                    <i class="fa-solid fa-diagram-project text-[#087F8A]"></i>
                                    <span>Sinkronisasi Otomatis dari Input Kargo (Single Entry Point)</span>
                                </div>
                                <p class="text-[11px] text-slate-600 leading-relaxed">
                                    Tidak ada pengisian ulang data di TMS. Sistem hanya menerima atribut transportasi &amp; slot dock yang dibutuhkan modul TMS (detail komoditas, berat, &amp; SSCC tetap di CMS/W&amp;B).
                                </p>
                            </div>
                            <a href="cargo_input.php" class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white hover:bg-teal-50 text-[#087F8A] font-bold text-[11px] border border-teal-300 shadow-xs transition-all">
                                <i class="fa-solid fa-plus"></i>
                                <span>Input Kargo Baru</span>
                            </a>
                        </div>

                        <!-- Read-Only TMS Data Grid (Hanya Data yang Dibutuhkan TMS) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 mb-5 text-xs">
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200">
                                <span class="text-[10px] text-slate-400 block uppercase font-bold tracking-wider mb-1">
                                    <i class="fa-solid fa-car text-[#087F8A] mr-1"></i> Nomor Plat Polisi
                                </span>
                                <span id="tms-view-plate" class="text-sm font-mono font-black text-[#0D1C42] block">-</span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200">
                                <span class="text-[10px] text-slate-400 block uppercase font-bold tracking-wider mb-1">
                                    <i class="fa-solid fa-user text-[#087F8A] mr-1"></i> Nama Supir Terdaftar
                                </span>
                                <span id="tms-view-driver" class="text-xs font-bold text-slate-800 block mt-0.5">-</span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200">
                                <span class="text-[10px] text-slate-400 block uppercase font-bold tracking-wider mb-1">
                                    <i class="fa-solid fa-building text-[#087F8A] mr-1"></i> Vendor Transportasi
                                </span>
                                <span id="tms-view-transporter" class="text-xs font-semibold text-slate-800 block mt-0.5 truncate" title="-">-</span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200">
                                <span class="text-[10px] text-slate-400 block uppercase font-bold tracking-wider mb-1">
                                    <i class="fa-solid fa-warehouse text-[#087F8A] mr-1"></i> Alokasi Dock Unloading
                                </span>
                                <span id="tms-view-dock" class="text-sm font-mono font-black text-[#087F8A] block">-</span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200">
                                <span class="text-[10px] text-slate-400 block uppercase font-bold tracking-wider mb-1">
                                    <i class="fa-solid fa-barcode text-[#087F8A] mr-1"></i> Kode Booking TAS
                                </span>
                                <span id="tms-view-booking" class="text-xs font-mono font-bold text-[#087F8A] block mt-0.5">-</span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200">
                                <span class="text-[10px] text-slate-400 block uppercase font-bold tracking-wider mb-1">
                                    <i class="fa-solid fa-shield text-[#087F8A] mr-1"></i> Status TAS Gate
                                </span>
                                <span id="tms-view-status" class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-50 text-amber-700 border border-amber-200 mt-0.5">-</span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200 sm:col-span-2 lg:col-span-1">
                                <span class="text-[10px] text-slate-400 block uppercase font-bold tracking-wider mb-1">
                                    <i class="fa-solid fa-hashtag text-[#087F8A] mr-1"></i> ID Armada (TMS)
                                </span>
                                <span id="tms-view-truck-id" class="text-[11px] font-mono font-bold text-slate-700 block mt-0.5 truncate">-</span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200 sm:col-span-2">
                                <span class="text-[10px] text-slate-400 block uppercase font-bold tracking-wider mb-1">
                                    <i class="fa-solid fa-clock text-[#087F8A] mr-1"></i> Waktu Pre-Alert / Kedatangan (ETA)
                                </span>
                                <span id="tms-view-arrival" class="text-xs font-mono font-semibold text-slate-700 block mt-0.5">-</span>
                            </div>
                        </div>

                        <!-- TMS Operational Action Buttons (Hanya Trigger Gate Verification, Tanpa Re-entry Form) -->
                        <div class="pt-2 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <button type="button" id="btn-tms-verify-gate" onclick="submitTMSCheckIn()" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                                    <i id="btn-tms-verify-icon" class="fa-solid fa-torii-gate mr-2"></i>
                                    <span id="btn-tms-verify-text">Verifikasi Kedatangan Truk di Gate (Tahap 1)</span>
                                </button>
                            </div>
                            <button type="button" onclick="switchSystemDashboard('cms')" class="px-4 py-2.5 rounded-xl bg-teal-50 hover:bg-teal-100 text-[#087F8A] text-xs font-bold border border-teal-200 shadow-sm transition-all flex items-center cursor-pointer">
                                <span>Lanjut ke Dashboard CMS (Tahap 2 &amp; 3)</span>
                                <i class="fa-solid fa-arrow-right ml-1.5 text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Inbound Trucks Feed Table -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100">
                            <div>
                                <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider flex items-center">
                                    <i class="fa-solid fa-list-check text-[#087F8A] mr-2 text-sm"></i>
                                    Log Antrean Kedatangan Truk (Database: truck_arrivals)
                                </h3>
                                <p class="text-[10px] text-slate-500 mt-0.5">Terisi otomatis saat data dimasukkan pada halaman Input Kargo (Zero Re-entry)</p>
                            </div>
                            <span id="tms-truck-count-badge" class="text-[10px] font-mono text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full border border-gray-200 font-bold"><?= $totalTrucksCount ?> Armada</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-[10px] uppercase font-bold text-slate-400 border-b border-gray-100 bg-slate-50/50">
                                        <th class="py-2.5 px-3">Plat Nomor</th>
                                        <th class="py-2.5 px-3">Supir</th>
                                        <th class="py-2.5 px-3">Transporter</th>
                                        <th class="py-2.5 px-3">Dock</th>
                                        <th class="py-2.5 px-3">Kode Booking</th>
                                        <th class="py-2.5 px-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="tmsTruckArrivalsTbody" class="divide-y divide-gray-100 text-slate-700">
                                    <?php foreach ($trucks as $trk): ?>
                                        <tr class="hover:bg-slate-50/70 transition-colors">
                                            <td class="py-2 px-3 font-mono font-bold text-[#0D1C42]"><?= htmlspecialchars($trk['truck_plate']) ?></td>
                                            <td class="py-2 px-3"><?= htmlspecialchars($trk['driver_name']) ?></td>
                                            <td class="py-2 px-3 text-slate-500"><?= htmlspecialchars($trk['transporter_company']) ?></td>
                                            <td class="py-2 px-3 font-mono font-bold text-[#087F8A]"><?= htmlspecialchars($trk['dock_slot']) ?></td>
                                            <td class="py-2 px-3 font-mono text-[11px] text-slate-500"><?= htmlspecialchars($trk['booking_code']) ?></td>
                                            <td class="py-2 px-3">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <?= htmlspecialchars($trk['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Right Column: TMS Dedicated Payload Inspector & Advisory (5 cols) -->
                <div class="lg:col-span-5 space-y-6">
                    
                    <!-- TMS JSON Payload Inspector -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm flex flex-col h-[460px]">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-3">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#087F8A]"></span>
                                <h3 class="text-xs font-bold text-[#0D1C42]">TMS Inbound Gate API Payload</h3>
                            </div>
                            <span class="text-[10px] font-mono text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 font-bold">TMS-ONLY FILTERED</span>
                        </div>
                        <div class="flex-grow overflow-auto bg-[#0D1C42] border border-slate-700/80 rounded-xl p-4 text-xs font-mono relative shadow-inner">
                            <pre id="tmsPayloadDisplay" class="text-emerald-300 leading-relaxed text-[11px]">// Memuat JSON TMS Gate Ingest...</pre>
                        </div>
                    </div>

                    <!-- To-Be Consultant Recommendation Card -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm space-y-3">
                        <div class="flex items-center space-x-2 text-xs font-bold text-[#0D1C42]">
                            <i class="fa-solid fa-graduation-cap text-[#087F8A]"></i>
                            <span>Rekomendasi Konsultan (To-Be TMS/TAS &amp; Zero Re-entry):</span>
                        </div>
                        <p class="text-slate-600 text-[11.5px] leading-relaxed">
                            Penerapan <strong>Truck Appointment System (TAS)</strong> terintegrasi dengan prinsip <strong>Zero Re-entry Data</strong> membuat data armada dari Input Kargo langsung mengalir ke gerbang bandara tanpa input ulang. TMS hanya menyerap data kendaraan &amp; slot dock, sementara detail komoditas diproses oleh CMS, AVSEC, dan Weight &amp; Balance.
                        </p>
                        <div class="pt-2">
                            <button onclick="switchSystemDashboard('cms')" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-[#0CA1AF] to-[#087F8A] hover:from-[#087F8A] hover:to-[#014D54] text-white font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer">
                                <span>Lanjut ke CMS Dashboard (Tahap 2 &amp; 3)</span>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </button>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- VIEW 3: DASHBOARD CMS (CARGO MANAGEMENT SYSTEM & INBOUND AIDC - TAHAP 2-3)-->
        <!-- ========================================================================= -->
        <div id="view-system-cms" class="system-view space-y-6 hidden">
            
            <!-- CMS Header Banner -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-gray-100">
                    <div class="flex items-center space-x-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-teal-50 border border-teal-200 text-[#087F8A] flex items-center justify-center text-2xl shadow-sm">
                            <i class="fa-solid fa-laptop-code"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h2 class="text-lg lg:text-xl font-extrabold text-[#0D1C42]">Dashboard CMS — Cargo Management System &amp; Inbound AIDC</h2>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-[#087F8A] border border-teal-200">
                                    Tahap 2 &amp; 3 Operasional
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                AIDC Inbound Data Ingestion (Payload JSON), Validasi GS1 SSCC 18-Digit, &amp; Pencocokan Dokumen Electronic Air Waybill (e-AWB).
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-mono text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-200 font-bold flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            CMS Core Active
                        </span>
                    </div>
                </div>

                <!-- 4-Layer Architecture Pill Strip -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-[11px]">
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 1 (Ingest Format)</span>
                        <span class="font-bold text-[#0D1C42] block mt-0.5">JSON Payload (AIDC Inbound)</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 2 (Network)</span>
                        <span class="font-bold text-[#0D1C42] block mt-0.5">Industrial Ethernet (REST / MQTT)</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 3 (Software)</span>
                        <span class="font-bold text-[#0D1C42] block mt-0.5">CMS Core &amp; Inbound Edge Service</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 4 (Protocol)</span>
                        <span class="font-bold text-[#087F8A] block mt-0.5">GS1 AI (00) SSCC &amp; IATA e-AWB</span>
                    </div>
                </div>
            </div>

            <!-- CMS Live KPI Strip -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Format Stream Ingest</span>
                        <span class="text-2xl font-black text-[#0D1C42] font-mono mt-1 block">JSON <span class="text-xs text-slate-500 font-sans font-normal">Payload</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 text-[#087F8A] flex items-center justify-center text-lg">
                        <i class="fa-solid fa-code"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">GS1 SSCC Validasi</span>
                        <span class="text-2xl font-black text-emerald-600 font-mono mt-1 block">100% <span class="text-xs text-slate-500 font-sans font-normal">Captured</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-barcode"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">e-AWB Match Rate</span>
                        <span class="text-2xl font-black text-[#087F8A] font-mono mt-1 block">100% <span class="text-xs text-emerald-600 font-sans font-bold">Matched</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 text-[#087F8A] flex items-center justify-center text-lg">
                        <i class="fa-solid fa-file-circle-check"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Routing Alur Keamanan</span>
                        <span class="text-2xl font-black text-emerald-700 font-mono mt-1 block">LANE-02 <span class="text-xs text-slate-500 font-sans font-normal">AVSEC</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-arrows-turn-to-dots"></i>
                    </div>
                </div>
            </div>

            <!-- CMS Main Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left Column (7 cols) -->
                <div class="lg:col-span-7 space-y-6">
                    
                    <!-- Scanner & e-AWB Workstation Card (JSON Ingestion) -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm relative overflow-hidden">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 mb-4 border-b border-gray-100 gap-2">
                            <div class="flex items-center space-x-2">
                                <i class="fa-solid fa-code text-[#087F8A] text-base"></i>
                                <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider">
                                    Pemindaian Inbound Data Ingest (JSON) &amp; Pencocokan e-AWB
                                </h3>
                            </div>
                            <span id="cms-scanner-status-badge" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 uppercase tracking-wide">
                                <i class="fa-solid fa-hourglass-start mr-1"></i>Menunggu Ingest JSON
                            </span>
                        </div>

                        <!-- JSON Ingestion Stream Console (Menggantikan Hardware Fisik RFID dengan Data JSON) -->
                        <div class="p-4 rounded-xl bg-[#0A1628] border border-slate-800 mb-5 shadow-inner relative overflow-hidden">
                            <div class="flex items-center justify-between pb-2.5 mb-2.5 border-b border-slate-800 text-xs font-mono">
                                <div class="flex items-center space-x-2">
                                    <span class="flex h-2.5 w-2.5 relative">
                                        <span id="cms-json-indicator-ping" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                        <span id="cms-json-indicator-dot" class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                                    </span>
                                    <span class="text-slate-200 font-bold text-[11px] tracking-wide">STREAM: AIDC_INBOUND_INGEST.json</span>
                                    <span class="text-[10px] text-teal-400 bg-teal-950/60 px-1.5 py-0.5 rounded border border-teal-800/60">application/json</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span id="cms-json-status-tag" class="text-[10px] font-mono text-amber-300 bg-amber-950/60 px-2 py-0.5 rounded border border-amber-800/80">STANDBY - WAITING JSON INGEST</span>
                                    <span class="hidden sm:inline-block text-[10px] font-mono text-slate-400 bg-slate-800/60 px-2 py-0.5 rounded border border-slate-700">REST API / MQTT</span>
                                </div>
                            </div>

                            <!-- JSON Raw Viewer -->
                            <pre id="cms-json-payload-display" class="font-mono text-[11px] leading-relaxed text-emerald-400 bg-[#060D1A] p-3.5 rounded-xl border border-slate-800/90 overflow-x-auto max-h-52 scrollbar-thin select-all"><code>// Memuat stream JSON pemindaian...</code></pre>

                            <!-- Console Bottom Metadata -->
                            <div class="mt-3 pt-2.5 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-2.5 text-xs font-mono">
                                <div class="flex items-center space-x-2">
                                    <span class="text-slate-400 text-[11px]">Primary Key (SSCC):</span>
                                    <span id="cms-captured-sscc" class="text-slate-400 font-semibold bg-slate-800/80 px-2.5 py-0.5 rounded border border-slate-700 font-mono italic text-[11px]">[ Menunggu Ingest JSON ]</span>
                                </div>
                                <div id="cms-gs1-pill-strip" class="hidden flex flex-wrap items-center gap-2 text-[10.5px]">
                                    <span class="bg-emerald-950/60 px-2 py-0.5 rounded border border-emerald-800/70 text-emerald-400 font-mono">AI (01) GTIN: <strong id="cms-gs1-gtin">-</strong></span>
                                    <span class="bg-teal-950/60 px-2 py-0.5 rounded border border-teal-800/70 text-teal-300 font-mono">Batch: <strong id="cms-gs1-batch">-</strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- Sequential Action Buttons: Step 1 (Ingest JSON) -> Step 2 (Validate e-AWB) -->
                        <div class="pt-2 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <!-- Tombol Tahap 2: Ingest Data Pemindaian JSON -->
                                <button type="button" id="btn-cms-scan-rfid" onclick="executeStage2Scan()" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                                    <i class="fa-solid fa-code mr-2 text-sm"></i>
                                    <span id="btn-cms-scan-text">Kirim / Ingest Payload JSON (Tahap 2)</span>
                                </button>

                                <!-- Tombol Tahap 3: Validasi e-AWB (Awalnya Terkunci) -->
                                <button type="button" id="btn-cms-validate-awb" onclick="executeStage3Validate()" class="px-5 py-2.5 rounded-xl bg-slate-100 text-slate-400 text-xs font-bold border border-gray-200 cursor-not-allowed shadow-none flex items-center transition-all opacity-70">
                                    <i id="btn-cms-validate-icon" class="fa-solid fa-lock mr-2 text-slate-400"></i>
                                    <span id="btn-cms-validate-text">Validasi &amp; Registrasi e-AWB (Terkunci - Ingest JSON Dulu)</span>
                                </button>
                            </div>

                            <button type="button" onclick="switchSystemDashboard('tms')" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-600 hover:text-[#087F8A] text-xs font-bold border border-gray-300 shadow-sm transition-colors flex items-center">
                                <i class="fa-solid fa-arrow-left mr-1.5 text-xs"></i>
                                <span>Kembali ke TMS</span>
                            </button>
                        </div>
                    </div>

                    <?php
                    // Filter cargos yang telah tervalidasi e-AWB dan teregistrasi di CMS (Stage >= 4, artinya Tahap 3 selesai)
                    $cmsRegisteredCargos = array_filter($cargos, function($cg) {
                        return (int)$cg['current_stage'] >= 4;
                    });
                    ?>

                    <!-- Inbound Cargo Ingestion Queue Table -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100">
                            <div>
                                <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider flex items-center">
                                    <i class="fa-solid fa-boxes-stacked text-[#087F8A] mr-2 text-sm"></i>
                                    Antrean Masuk Kargo di CMS (Database: cargo_shipments)
                                </h3>
                                <p class="text-[10px] text-slate-500 mt-0.5">Kargo otomatis masuk ke antrean ini setelah divalidasi dan registrasi di e-AWB (Tahap 3)</p>
                            </div>
                            <span id="cms-cargo-count-badge" class="text-[10px] font-mono text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full border border-gray-200 font-bold"><?= count($cmsRegisteredCargos) ?> Shipment</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-[10px] uppercase font-bold text-slate-400 border-b border-gray-100 bg-slate-50/50">
                                        <th class="py-2.5 px-3">Air Waybill (e-AWB)</th>
                                        <th class="py-2.5 px-3">SSCC (18-Digit)</th>
                                        <th class="py-2.5 px-3">Komoditas</th>
                                        <th class="py-2.5 px-3">Shipper</th>
                                        <th class="py-2.5 px-3">Tahap</th>
                                        <th class="py-2.5 px-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="cmsCargoQueueTbody" class="divide-y divide-gray-100 text-slate-700">
                                    <?php if (empty($cmsRegisteredCargos)): ?>
                                        <tr id="cms-empty-queue-row">
                                            <td colspan="6" class="py-8 text-center text-slate-400">
                                                <div class="w-12 h-12 rounded-2xl bg-slate-50 border border-gray-200 text-slate-400 flex items-center justify-center text-xl mx-auto mb-2">
                                                    <i class="fa-solid fa-inbox"></i>
                                                </div>
                                                <span class="font-bold text-[#0D1C42] block text-xs">Belum Ada Antrean Kargo Terdaftar di CMS</span>
                                                <span class="text-[11px] text-slate-400 mt-0.5 block">Kargo akan masuk ke antrean ini secara otomatis setelah berhasil dipindai via Ingest JSON (Tahap 2) &amp; divalidasi registrasi e-AWB (Tahap 3).</span>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($cmsRegisteredCargos as $cg): ?>
                                            <tr id="cms-cargo-row-<?= htmlspecialchars($cg['id']) ?>" class="hover:bg-slate-50/70 transition-colors">
                                                <td class="py-2 px-3 font-mono font-bold text-[#0D1C42]"><?= htmlspecialchars($cg['awb_number']) ?></td>
                                                <td class="py-2 px-3 font-mono text-[11px] text-[#087F8A] font-bold"><?= htmlspecialchars($cg['sscc']) ?></td>
                                                <td class="py-2 px-3 truncate max-w-[120px]"><?= htmlspecialchars($cg['description']) ?></td>
                                                <td class="py-2 px-3 text-slate-500 truncate max-w-[120px]"><?= htmlspecialchars($cg['shipper']) ?></td>
                                                <td class="py-2 px-3 font-bold text-[#087F8A]">Tahap <?= htmlspecialchars($cg['current_stage']) ?></td>
                                                <td class="py-2 px-3">
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-[#087F8A] border border-teal-200">
                                                        <?= htmlspecialchars($cg['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Dedicated Payload Inspector & Recommendations (5 cols) -->
                <div class="lg:col-span-5 space-y-6">
                    
                    <!-- CMS JSON Payload Inspector -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm flex flex-col h-[460px]">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-3">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#087F8A]"></span>
                                <h3 class="text-xs font-bold text-[#0D1C42]">CMS AIDC &amp; e-AWB JSON Payload</h3>
                            </div>
                            <span class="text-[10px] font-mono text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 font-bold">GS1 / IATA e-AWB</span>
                        </div>
                        <div class="flex-grow overflow-auto bg-[#0D1C42] border border-slate-700/80 rounded-xl p-4 text-xs font-mono relative shadow-inner">
                            <pre id="cmsPayloadDisplay" class="text-emerald-300 leading-relaxed text-[11px]">// Memuat JSON CMS Ingest...</pre>
                        </div>
                    </div>

                    <!-- To-Be Consultant Recommendation Card -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm space-y-3">
                        <div class="flex items-center space-x-2 text-xs font-bold text-[#0D1C42]">
                            <i class="fa-solid fa-graduation-cap text-[#087F8A]"></i>
                            <span>Rekomendasi Konsultan (To-Be Inbound AIDC):</span>
                        </div>
                        <p class="text-slate-600 text-[11.5px] leading-relaxed">
                            Pencocokan otomatis tag <strong>GS1 SSCC 18-digit</strong> ke dokumen <strong>e-AWB IATA</strong> mengeliminasi seluruh kesalahan entri data manual (human error = 0%). Data fisik dan digital seketika tersinkronisasi, dan kargo langsung diteruskan ke jalur conveyor menuju X-Ray AVSEC tanpa tertahan di area receiving.
                        </p>
                        <div class="pt-2 flex items-center gap-2">
                            <button onclick="switchSystemDashboard('tms')" class="flex-1 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-arrow-left text-xs"></i>
                                <span>TMS</span>
                            </button>
                            <button onclick="switchSystemDashboard('avsec')" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-[#0CA1AF] to-[#087F8A] hover:from-[#087F8A] hover:to-[#014D54] text-white font-bold text-xs shadow-md transition-all flex items-center justify-center gap-1.5">
                                <span>AVSEC System</span>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </button>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- VIEW 4: DASHBOARD AVSEC SYSTEM (AVIATION SECURITY & SCREENING - TAHAP 4)   -->
        <!-- ========================================================================= -->
        <div id="view-system-avsec" class="system-view space-y-6 hidden">
            
            <!-- AVSEC Header Banner -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-gray-100">
                    <div class="flex items-center space-x-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-teal-50 border border-teal-200 text-[#087F8A] flex items-center justify-center text-2xl shadow-sm">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h2 class="text-lg lg:text-xl font-extrabold text-[#0D1C42]">Dashboard AVSEC System — Aviation Security &amp; Screening</h2>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-[#087F8A] border border-teal-200">
                                    Tahap 4 Operasional
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Dual-View High-Energy X-Ray (320kV), Penerbitan Sertifikat Keamanan CSD Digital, &amp; Isolasi Otomatis Kargo Karantina (SUSPECT).
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-mono text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-200 font-bold flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            ICAO Annex 17 Active
                        </span>
                    </div>
                </div>

                <!-- 4-Layer Architecture Pill Strip -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-[11px]">
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 1 (Hardware)</span>
                        <span class="font-bold text-[#0D1C42] block mt-0.5">Dual-View X-Ray (180x180cm, 320kV)</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 2 (Network)</span>
                        <span class="font-bold text-[#0D1C42] block mt-0.5">Secure AVSEC Industrial Network</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 3 (Software)</span>
                        <span class="font-bold text-[#0D1C42] block mt-0.5">Aviation Security System (AVSEC)</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 4 (Protocol)</span>
                        <span class="font-bold text-[#087F8A] block mt-0.5">ICAO CSD Certificate &amp; REST Alert</span>
                    </div>
                </div>
            </div>

            <!-- AVSEC Live KPI Strip -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Penetrasi Baja X-Ray</span>
                        <span class="text-2xl font-black text-[#0D1C42] font-mono mt-1 block">80 <span class="text-xs text-slate-500 font-sans font-normal">mm Baja 320kV</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 text-[#087F8A] flex items-center justify-center text-lg">
                        <i class="fa-solid fa-atom"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Tingkat Kelolosan (Pass)</span>
                        <span class="text-2xl font-black text-emerald-600 font-mono mt-1 block">98.5% <span class="text-xs text-slate-500 font-sans font-normal">CLEARED</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-shield-check"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Sertifikat CSD Digital</span>
                        <span class="text-2xl font-black text-[#087F8A] font-mono mt-1 block">ACTIVE <span class="text-xs text-emerald-600 font-sans font-bold">Encrypted</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 text-[#087F8A] flex items-center justify-center text-lg">
                        <i class="fa-solid fa-certificate"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Protokol Karantina</span>
                        <span class="text-2xl font-black text-rose-600 font-mono mt-1 block">AUTO-LOCK <span class="text-xs text-slate-500 font-sans font-normal">Spur Vault</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
            </div>

            <!-- AVSEC Main Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left Column: Dual-View Console & CSD Certificate (7 cols) -->
                <div class="lg:col-span-7 space-y-6">
                    
                    <!-- AVSEC X-Ray Screening Stream Console (Menggantikan Hardware Fisik dengan Data JSON) -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 mb-4 border-b border-gray-100 gap-2">
                            <div class="flex items-center space-x-2">
                                <i class="fa-solid fa-shield-halved text-[#087F8A] text-base"></i>
                                <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider">
                                    Pemeriksaan Keamanan AVSEC (Data Stream JSON &amp; Sertifikasi CSD)
                                </h3>
                            </div>
                            <span id="avsec-status-indicator" class="text-[10px] font-mono px-3 py-1 rounded-full font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                STATUS: CLEARED
                            </span>
                        </div>

                        <!-- Dark JSON Stream Console for AVSEC -->
                        <div class="p-4 rounded-xl bg-[#0A1628] border border-slate-800 mb-5 shadow-inner relative overflow-hidden">
                            <div class="flex items-center justify-between pb-2.5 mb-2.5 border-b border-slate-800 text-xs font-mono">
                                <div class="flex items-center space-x-2">
                                    <span class="flex h-2.5 w-2.5 relative">
                                        <span id="avsec-json-indicator-ping" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                        <span id="avsec-json-indicator-dot" class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                                    </span>
                                    <span class="text-slate-200 font-bold text-[11px] tracking-wide">STREAM: AVSEC_XRAY_SECURITY_EVALUATION.json</span>
                                    <span class="text-[10px] text-teal-400 bg-teal-950/60 px-1.5 py-0.5 rounded border border-teal-800/60">ICAO Annex 17 / JSON</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span id="avsec-json-status-tag" class="text-[10px] font-mono text-emerald-300 bg-emerald-950/60 px-2 py-0.5 rounded border border-emerald-800/80">LIVE SCREENING ACTIVE</span>
                                </div>
                            </div>

                            <!-- JSON Raw Viewer for AVSEC -->
                            <pre id="avsec-json-stream-display" class="font-mono text-[11px] leading-relaxed text-emerald-400 bg-[#060D1A] p-3.5 rounded-xl border border-slate-800/90 overflow-x-auto max-h-56 scrollbar-thin select-all"><code>// Memuat stream data keamanan AVSEC...</code></pre>
                        </div>

                        <!-- AI Agent AVSEC Reasoning Card (LangGraph Perceive → Think → Act) -->
                        <div id="avsec-ai-agent-box" class="hidden p-4 rounded-2xl border border-violet-200 bg-violet-50/75 shadow-sm mb-5 space-y-2.5">
                            <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-violet-200/70">
                                <div class="flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-lg bg-violet-600 text-white flex items-center justify-center text-xs shadow-sm"><i class="fa-solid fa-robot"></i></span>
                                    <div>
                                        <h4 class="text-xs font-black text-[#0D1C42]">Penalaran Otonom AI Agent AVSEC</h4>
                                        <p class="text-[10px] text-slate-500">Perceive (Manifes &amp; X-Ray) &rarr; Think (DGR / ICAO Annex 17) &rarr; Act (Karantina &amp; CSD)</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 text-[10px] font-mono">
                                    <span id="avsec-agent-engine-badge" class="px-2 py-0.5 rounded-full font-bold bg-violet-100 text-violet-800 border border-violet-200">langgraph-js</span>
                                    <span id="avsec-agent-metrics" class="px-2 py-0.5 rounded-full font-bold bg-white text-slate-700 border border-slate-200">Risiko 0% &bull; Keyakinan 0%</span>
                                </div>
                            </div>
                            <div id="avsec-agent-headline" class="text-xs font-bold text-[#0D1C42] bg-white px-3 py-2 rounded-xl border border-violet-100">-</div>
                            <div id="avsec-agent-trace" class="grid grid-cols-1 md:grid-cols-3 gap-2 text-[11px]"></div>
                        </div>

                        <!-- Interactive CSD Certificate Card (Ditampilkan saat CLEARED) -->
                        <div id="avsec-certificate-wrap" class="p-5 rounded-2xl border border-emerald-300 bg-emerald-50/50 shadow-sm mb-5">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-2xl shadow-sm">
                                        <i class="fa-solid fa-file-shield"></i>
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-mono uppercase tracking-wider text-emerald-700 font-bold">ICAO Standard Security Document</span>
                                        <h4 class="text-sm font-black text-[#0D1C42]">Consignment Security Declaration (CSD)</h4>
                                        <span id="avsec-csd-number" class="text-xs font-mono font-bold text-emerald-800">CSD-ID-20261017-9482</span>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-200 text-emerald-800 uppercase tracking-wider">
                                    SPX - CARGO AIRCRAFT ONLY
                                </span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-4 pt-3 border-t border-emerald-200/80 text-[11px] text-slate-700">
                                <div><span class="text-slate-400 block text-[9px] font-bold">PETUGAS SCREENER:</span> <strong id="avsec-inspector-id">AVSEC-OPR-07</strong></div>
                                <div><span class="text-slate-400 block text-[9px] font-bold">METODE SCREENING:</span> <strong>XRY (Dual-View 320kV)</strong></div>
                                <div><span class="text-slate-400 block text-[9px] font-bold">WAKTU CLEARANCE:</span> <strong id="avsec-clearance-time">LIVE VALIDATED</strong></div>
                            </div>
                        </div>

                        <!-- Suspect Warning & Ruang Khusus Karantina Box (Ditampilkan saat SUSPECT) -->
                        <div id="avsec-suspect-warning-wrap" class="p-5 rounded-2xl border-2 border-rose-400 bg-rose-50 shadow-md mb-5 hidden">
                            <div class="flex items-start space-x-3 text-rose-700 mb-3">
                                <i class="fa-solid fa-triangle-exclamation text-3xl mt-0.5 animate-bounce"></i>
                                <div>
                                    <h4 class="text-sm font-black text-rose-800">PERINGATAN AVSEC: KARGO DIKUNCI DI RUANG KHUSUS KARANTINA</h4>
                                    <p class="text-xs text-rose-600 mt-0.5">Terdeteksi anomali kepadatan tinggi (Z &gt; 20). Kargo diisolasi otomatis di ruang karantina dan dilarang masuk apron!</p>
                                </div>
                            </div>
                            
                            <div class="p-3 bg-white/90 rounded-xl border border-rose-200 text-xs text-slate-700 mb-3 space-y-1">
                                <div class="font-bold text-rose-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-shield text-rose-600"></i>
                                    <span>SOP Penanganan Khusus Ruang Karantina:</span>
                                </div>
                                <p class="text-[11.5px] text-slate-600">
                                    Silakan tentukan tindak lanjut kargo: lakukan <strong>pemeriksaan fisik sekunder</strong> untuk me-release jika aman, atau <strong>tolak kargo</strong> untuk dikembalikan ke truk pengangkut asal.
                                </p>
                            </div>

                            <!-- Tombol Penanganan Khusus Karantina -->
                            <div class="flex flex-wrap items-center gap-2.5">
                                <button type="button" onclick="executeStageAction(4, 'RELEASE')" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-all flex items-center">
                                    <i class="fa-solid fa-user-shield mr-1.5"></i>
                                    <span>Pemeriksaan Fisik Sekunder (Hand Search / ETD) &rarr; Rilis CLEARED</span>
                                </button>
                                <button type="button" onclick="executeStageAction(4, 'RETURN_TO_TRUCK')" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-black text-white font-bold text-xs shadow-sm transition-all flex items-center">
                                    <i class="fa-solid fa-truck-arrow-right mr-1.5"></i>
                                    <span>Tolak &amp; Kembalikan Barang ke Truk Semula</span>
                                </button>
                            </div>
                        </div>

                        <!-- AVSEC AI Agent Trigger (Tanpa pilihan manual CLEARED/SUSPECT) -->
                        <div class="pt-1 space-y-2.5">
                            <label class="inline-flex items-center gap-2 text-xs text-slate-600 cursor-pointer select-none">
                                <input type="checkbox" id="avsec-dash-inject-anomaly" class="w-3.5 h-3.5 text-rose-600 rounded border-gray-300">
                                <span>Sisipkan anomali berbahaya tersembunyi di sensor X-Ray (opsional uji penyelundupan)</span>
                            </label>
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" onclick="executeStageAction(4, document.getElementById('avsec-dash-inject-anomaly')?.checked ? 'SUSPECT' : 'AUTO')" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-[#087F8A] hover:from-violet-500 hover:to-[#0CA1AF] text-white font-bold text-xs shadow-md shadow-violet-500/25 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                                    <i class="fa-solid fa-robot mr-2"></i>
                                    <span>Jalankan Screening Otonom AI Agent AVSEC</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- AVSEC Security Inspection Log Table -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100">
                            <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider flex items-center">
                                <i class="fa-solid fa-clipboard-check text-[#087F8A] mr-2 text-sm"></i>
                                Audit Pemeriksaan AVSEC (Database: security_checks)
                            </h3>
                            <span class="text-[10px] font-mono text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-gray-200 font-bold"><?= count($secChecks) ?> Record</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-[10px] uppercase font-bold text-slate-400 border-b border-gray-100 bg-slate-50/50">
                                        <th class="py-2.5 px-3">Air Waybill</th>
                                        <th class="py-2.5 px-3">Hasil X-Ray</th>
                                        <th class="py-2.5 px-3">Nomor CSD</th>
                                        <th class="py-2.5 px-3">Inspector</th>
                                        <th class="py-2.5 px-3">Density Reading</th>
                                        <th class="py-2.5 px-3">Waktu</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-slate-700">
                                    <?php foreach ($secChecks as $sc): ?>
                                        <tr class="hover:bg-slate-50/70 transition-colors">
                                            <td class="py-2 px-3 font-mono font-bold text-[#0D1C42]"><?= htmlspecialchars($sc['awb_number']) ?></td>
                                            <td class="py-2 px-3">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= ($sc['xray_result'] === 'CLEARED') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-300 animate-pulse' ?>">
                                                    <?= htmlspecialchars($sc['xray_result']) ?>
                                                </span>
                                            </td>
                                            <td class="py-2 px-3 font-mono text-[11px] text-[#087F8A]"><?= htmlspecialchars($sc['csd_number'] ?? 'DITAHAN') ?></td>
                                            <td class="py-2 px-3"><?= htmlspecialchars($sc['inspector_id']) ?></td>
                                            <td class="py-2 px-3 text-slate-500 truncate max-w-[120px]"><?= htmlspecialchars($sc['density_reading']) ?></td>
                                            <td class="py-2 px-3 text-[10px] text-slate-400"><?= htmlspecialchars($sc['check_time']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Right Column: AVSEC Dedicated Payload & Advisory (5 cols) -->
                <div class="lg:col-span-5 space-y-6">
                    
                    <!-- AVSEC JSON Payload Inspector -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm flex flex-col h-[460px]">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-3">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#087F8A]"></span>
                                <h3 class="text-xs font-bold text-[#0D1C42]">AVSEC Screening &amp; CSD JSON Payload</h3>
                            </div>
                            <span class="text-[10px] font-mono text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 font-bold">ICAO CSD</span>
                        </div>
                        <div class="flex-grow overflow-auto bg-[#0D1C42] border border-slate-700/80 rounded-xl p-4 text-xs font-mono relative shadow-inner">
                            <pre id="avsecPayloadDisplay" class="text-emerald-300 leading-relaxed text-[11px]">// Memuat JSON AVSEC Screening...</pre>
                        </div>
                    </div>

                    <!-- To-Be Consultant Recommendation Card -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm space-y-3">
                        <div class="flex items-center space-x-2 text-xs font-bold text-[#0D1C42]">
                            <i class="fa-solid fa-graduation-cap text-[#087F8A]"></i>
                            <span>Rekomendasi Konsultan (To-Be AVSEC Protocol):</span>
                        </div>
                        <p class="text-slate-600 text-[11.5px] leading-relaxed">
                            Integrasi otomatis antara X-Ray Dual-View dan CMS menerbitkan sertifikat <strong>CSD (Consignment Security Declaration)</strong> secara digital seketika kargo dinyatakan lolos. Bila terdeteksi anomali (SUSPECT), kargo secara otomatis dikunci di sistem database sehingga <em>secara sistemik mustahil</em> dapat ditimbang atau dimuat ke pesawat.
                        </p>
                        <div class="pt-2 flex items-center gap-2">
                            <button onclick="switchSystemDashboard('cms')" class="flex-1 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-arrow-left text-xs"></i>
                                <span>CMS</span>
                            </button>
                            <button onclick="switchSystemDashboard('load_balance')" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-[#0CA1AF] to-[#087F8A] hover:from-[#087F8A] hover:to-[#014D54] text-white font-bold text-xs shadow-md transition-all flex items-center justify-center gap-1.5">
                                <span>Load &amp; Balance</span>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </button>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <!-- ================================================================================== -->
        <!-- VIEW 5: DASHBOARD LOAD & BALANCE / LOAD PLANNING SYSTEM (TAHAP 5, 6, & 7)          -->
        <!-- ================================================================================== -->
        <div id="view-system-load_balance" class="system-view space-y-6 hidden">
            
            <!-- Load & Balance Header Banner -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-gray-100">
                    <div class="flex items-center space-x-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-teal-50 border border-teal-200 text-[#087F8A] flex items-center justify-center text-2xl shadow-sm">
                            <i class="fa-solid fa-scale-balanced"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h2 class="text-lg lg:text-xl font-extrabold text-[#0D1C42]">Dashboard Load Planning — Weight &amp; Balance &amp; Flight Loading</h2>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-[#087F8A] border border-teal-200">
                                    Tahap 5, 6, &amp; 7 Operasional
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Smart Floor Scale 15T, Konsolidasi Kontainer ULD (AKE/PMC), Kalkulasi Center of Gravity (CoG), &amp; Penerbitan Electronic Loadsheet Penerbangan.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-mono text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-200 font-bold flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            Flight Ops Synced
                        </span>
                    </div>
                </div>

                <!-- 4-Layer Architecture Pill Strip -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-[11px]">
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 1 (Hardware)</span>
                        <span class="font-bold text-[#0D1C42] block mt-0.5">Smart Floor Scale 15T &amp; ULD Station</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 2 (Network)</span>
                        <span class="font-bold text-[#0D1C42] block mt-0.5">Cockpit EFB Link &amp; Industrial Socket</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 3 (Software)</span>
                        <span class="font-bold text-[#0D1C42] block mt-0.5">Weight &amp; Balance / Load Planning</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Layer 4 (Protocol)</span>
                        <span class="font-bold text-[#087F8A] block mt-0.5">OIML R76 &amp; IATA Electronic Loadsheet</span>
                    </div>
                </div>
            </div>

            <!-- Load & Balance Live KPI Strip -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Akurasi Smart Floor Scale</span>
                        <span class="text-2xl font-black text-[#0D1C42] font-mono mt-1 block">&plusmn;1 <span class="text-xs text-slate-500 font-sans font-normal">kg OIML R76</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 text-[#087F8A] flex items-center justify-center text-lg">
                        <i class="fa-solid fa-weight-scale"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Center of Gravity (% MAC)</span>
                        <span class="text-2xl font-black text-emerald-600 font-mono mt-1 block">28.4% <span class="text-xs text-slate-500 font-sans font-normal">Trim Safe</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-crosshairs"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Batas Beban Lantai Kabin</span>
                        <span class="text-2xl font-black text-[#087F8A] font-mono mt-1 block">VALID <span class="text-xs text-emerald-600 font-sans font-bold">Structural</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 text-[#087F8A] flex items-center justify-center text-lg">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Penerbangan Manifes</span>
                        <span class="text-2xl font-black text-emerald-700 font-mono mt-1 block">GA-707 <span class="text-xs text-slate-500 font-sans font-normal">CGK-DPS</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-plane"></i>
                    </div>
                </div>
            </div>

            <!-- Load & Balance Main Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left Column: Workstation & Controls (7 cols) -->
                <div class="lg:col-span-7 space-y-6">
                    
                    <!-- Smart Floor Scale & ULD Build-Up Workstation Card (Menggantikan Timbangan Fisik dengan Data JSON) -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 mb-4 border-b border-gray-100 gap-2">
                            <div class="flex items-center space-x-2">
                                <i class="fa-solid fa-scale-balanced text-[#087F8A] text-base"></i>
                                <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider">
                                    Penimbangan Smart Scale, ULD Consolidation, &amp; Parameter Muat Pesawat
                                </h3>
                            </div>
                            <span class="text-[10px] font-mono text-[#087F8A] bg-teal-50 px-2 py-0.5 rounded border border-teal-200 font-bold">Tahap 5, 6, &amp; 7</span>
                        </div>

                        <!-- Dark JSON Stream Console for Smart Scale -->
                        <div class="p-4 rounded-xl bg-[#0A1628] border border-slate-800 mb-5 shadow-inner relative overflow-hidden">
                            <div class="flex items-center justify-between pb-2.5 mb-2.5 border-b border-slate-800 text-xs font-mono">
                                <div class="flex items-center space-x-2">
                                    <span class="flex h-2.5 w-2.5 relative">
                                        <span id="lb-json-indicator-ping" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                                        <span id="lb-json-indicator-dot" class="relative inline-flex rounded-full h-2.5 w-2.5 bg-teal-500"></span>
                                    </span>
                                    <span class="text-slate-200 font-bold text-[11px] tracking-wide">STREAM: SMART_SCALE_WEIGHT_ACQUISITION.json</span>
                                    <span class="text-[10px] text-teal-400 bg-teal-950/60 px-1.5 py-0.5 rounded border border-teal-800/60">OIML R76 / TCP</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span id="lb-json-status-tag" class="text-[10px] font-mono text-teal-300 bg-teal-950/60 px-2 py-0.5 rounded border border-teal-800/80">SCALE SYNC ACTIVE</span>
                                </div>
                            </div>

                            <!-- JSON Raw Viewer for Scale & Weight -->
                            <pre id="lb-json-stream-display" class="font-mono text-[11px] leading-relaxed text-emerald-400 bg-[#060D1A] p-3.5 rounded-xl border border-slate-800/90 overflow-x-auto max-h-56 scrollbar-thin select-all"><code>// Memuat stream penimbangan Smart Floor Scale...</code></pre>
                        </div>

                        <!-- 3-Col Visual Cards: Parameter & Kriteria Batas Berat -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                            
                            <!-- Scale Visual: Toleransi IATA ±5% & HEA Tag -->
                            <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[9px] uppercase tracking-wider text-slate-500 font-bold">Smart Floor Scale 15T</span>
                                        <span id="lb-hea-badge" class="hidden text-[9px] text-purple-700 font-bold bg-purple-50 px-2 py-0.5 rounded border border-purple-200">
                                            <i class="fa-solid fa-weight-hanging mr-1"></i> HEA
                                        </span>
                                    </div>
                                    <div id="lb-scale-weight" class="text-2xl font-black font-mono text-[#0D1C42] my-1 text-center">- KG</div>
                                    <div class="text-center my-1">
                                        <span id="lb-tolerance-badge" class="text-[9px] text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 inline-block">
                                            Batas Toleransi IATA &plusmn;5%
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-2 text-[10px] text-slate-600 border-t border-gray-200 pt-1.5 space-y-0.5">
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Deklarasi e-AWB:</span>
                                        <strong id="lb-scale-declared" class="text-[#0D1C42] font-mono">-</strong>
                                    </div>
                                    <div class="flex justify-between text-[9px] text-slate-400">
                                        <span>Standar Akurasi:</span>
                                        <span class="font-mono text-slate-600">OIML R76 Class III</span>
                                    </div>
                                </div>
                            </div>

                            <!-- ULD Container Visual: Max Berat ULD AKE & Breakdown Tare/Net/Gross -->
                            <div class="p-4 rounded-xl bg-teal-50/60 border border-teal-200 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[9px] uppercase tracking-wider text-[#087F8A] font-bold">Kontainer ULD (AKE/LD3)</span>
                                        <span id="lb-uld-status-badge" class="text-[9px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                            Layak Dimuat
                                        </span>
                                    </div>
                                    <div id="lb-uld-code" class="text-lg font-black font-mono text-[#0D1C42] my-1 text-center truncate">AKE-12345-GA</div>
                                    <!-- Progress Bar ULD Capacity -->
                                    <div class="my-1">
                                        <div class="flex justify-between text-[9px] text-slate-500 mb-0.5">
                                            <span>Utilisasi Kapasitas</span>
                                            <span id="lb-uld-utilization" class="font-bold text-[#087F8A]">0.0% Terisi</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                            <div id="lb-uld-progress-bar" class="bg-teal-500 h-1.5 rounded-full transition-all duration-500" style="width: 0%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2 text-[10px] text-slate-600 border-t border-teal-200/60 pt-1.5 space-y-0.5">
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Net Kargo:</span>
                                        <span id="lb-cargo-net-wt" class="font-mono font-bold text-slate-700">-</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Tare ULD (AKE):</span>
                                        <span id="lb-uld-tare-wt" class="font-mono text-slate-600">75.0 kg</span>
                                    </div>
                                    <div class="flex justify-between border-t border-teal-200/40 pt-0.5 font-bold">
                                        <span class="text-[#0D1C42]">Total Gross:</span>
                                        <span id="lb-uld-gross-wt" class="font-mono text-emerald-700">- / 1.588 kg</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Aircraft Limit & Center of Gravity (CoG) Visual -->
                            <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[9px] uppercase tracking-wider text-slate-500 font-bold">Trim &amp; Beban Pesawat</span>
                                        <span class="text-[9px] text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 font-mono">
                                            GA-707
                                        </span>
                                    </div>
                                    <div class="text-2xl font-black font-mono text-emerald-600 my-1 text-center">18.500 KG</div>
                                    <div class="text-center my-1">
                                        <span class="text-[9px] text-[#087F8A] font-bold bg-teal-50 px-2 py-0.5 rounded border border-teal-200 inline-block">
                                            Boeing 737-800F Belly Cargo
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-2 text-[10px] text-slate-600 border-t border-gray-200 pt-1.5 space-y-0.5">
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Kompartemen:</span>
                                        <strong class="text-[#0D1C42] font-mono">AFT-LOWER-1</strong>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Center of Gravity:</span>
                                        <strong class="text-emerald-700 font-mono">28.4% MAC (Safe)</strong>
                                    </div>
                                    <div class="flex justify-between text-[9px] text-slate-400">
                                        <span>Envelope Limit:</span>
                                        <span class="font-mono text-slate-600">18.0% - 34.0% MAC</span>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Interactive Action Triggers for Stages 5, 6, 7 -->
                        <div class="pt-1 flex flex-wrap items-center gap-3">
                            <button onclick="executeStageAction(5, 'CLEARED')" class="px-4 py-2.5 rounded-xl bg-white hover:bg-teal-50 text-[#087F8A] hover:text-[#0CA1AF] text-xs font-bold border border-teal-300 shadow-sm transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                                <i class="fa-solid fa-scale-balanced mr-1.5"></i>
                                <span>Timbang di Smart Scale (Tahap 5)</span>
                            </button>
                            <button onclick="openToleranceTestModal()" class="px-3.5 py-2.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-bold border border-amber-300 shadow-sm transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer" title="Simulasikan Uji Toleransi IATA ±5% dan Batas Maksimal ULD">
                                <i class="fa-solid fa-vial mr-1.5 text-amber-600"></i>
                                <span>Uji Toleransi IATA &amp; ULD</span>
                            </button>
                            <button onclick="executeStageAction(6, 'CLEARED')" class="px-4 py-2.5 rounded-xl bg-white hover:bg-teal-50 text-[#087F8A] hover:text-[#0CA1AF] text-xs font-bold border border-teal-300 shadow-sm transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                                <i class="fa-solid fa-boxes-packing mr-1.5"></i>
                                <span>Konsolidasi ke ULD (Tahap 6)</span>
                            </button>
                            <button onclick="executeStageAction(7, 'CLEARED')" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                                <i class="fa-solid fa-plane-departure mr-2"></i>
                                <span>Finalisasi Manifes &amp; Flight Loading (Tahap 7)</span>
                            </button>
                        </div>
                    </div>

                    <!-- ULD Containers & Flight Manifest Table -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100">
                            <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider flex items-center">
                                <i class="fa-solid fa-plane-departure text-[#087F8A] mr-2 text-sm"></i>
                                Status Kontainer ULD &amp; Manifes (Database: uld_containers)
                            </h3>
                            <span class="text-[10px] font-mono text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-gray-200 font-bold"><?= count($ulds) ?> ULD</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-[10px] uppercase font-bold text-slate-400 border-b border-gray-100 bg-slate-50/50">
                                        <th class="py-2.5 px-3">Kode ULD</th>
                                        <th class="py-2.5 px-3">Tipe</th>
                                        <th class="py-2.5 px-3">Berat Aktual</th>
                                        <th class="py-2.5 px-3">Kapasitas Maks</th>
                                        <th class="py-2.5 px-3">Flight &amp; Kompartemen</th>
                                        <th class="py-2.5 px-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-slate-700">
                                    <?php foreach ($ulds as $u): ?>
                                        <tr class="hover:bg-slate-50/70 transition-colors">
                                            <td class="py-2 px-3 font-mono font-bold text-[#0D1C42]"><?= htmlspecialchars($u['uld_code']) ?></td>
                                            <td class="py-2 px-3 font-semibold"><?= htmlspecialchars($u['uld_type']) ?></td>
                                            <td class="py-2 px-3 font-mono text-emerald-700 font-bold"><?= number_format($u['current_weight_kg'], 1) ?> kg</td>
                                            <td class="py-2 px-3 font-mono text-slate-500"><?= number_format($u['max_weight_kg'], 1) ?> kg</td>
                                            <td class="py-2 px-3">
                                                <span class="font-bold text-[#087F8A]"><?= htmlspecialchars($u['flight_number'] ?? 'GA-707') ?></span> &bull; 
                                                <span class="text-slate-500 font-mono"><?= htmlspecialchars($u['compartment']) ?></span>
                                            </td>
                                            <td class="py-2 px-3">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= ($u['status'] === 'LOADED' || $u['status'] === 'FULL') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-teal-50 text-[#087F8A] border border-teal-200' ?>">
                                                    <?= htmlspecialchars($u['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Dedicated Payload & Advisory (5 cols) -->
                <div class="lg:col-span-5 space-y-6">
                    
                    <!-- Load Planning JSON Payload Inspector -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm flex flex-col h-[460px]">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-3">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#087F8A]"></span>
                                <h3 class="text-xs font-bold text-[#0D1C42]">Weight &amp; Balance &amp; Loadsheet Payload</h3>
                            </div>
                            <span class="text-[10px] font-mono text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 font-bold">OIML / IATA Cargo-IMP</span>
                        </div>
                        <div class="flex-grow overflow-auto bg-[#0D1C42] border border-slate-700/80 rounded-xl p-4 text-xs font-mono relative shadow-inner">
                            <pre id="lbPayloadDisplay" class="text-emerald-300 leading-relaxed text-[11px]">// Memuat JSON Weight & Balance / Loadsheet...</pre>
                        </div>
                    </div>

                    <!-- To-Be Consultant Recommendation Card -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm space-y-3">
                        <div class="flex items-center space-x-2 text-xs font-bold text-[#0D1C42]">
                            <i class="fa-solid fa-graduation-cap text-[#087F8A]"></i>
                            <span>Rekomendasi Konsultan (To-Be Flight Safety &amp; Trim):</span>
                        </div>
                        <p class="text-slate-600 text-[11.5px] leading-relaxed">
                            Penghitungan <strong>Center of Gravity (CoG)</strong> dan batas beban lantai kabin secara real-time memastikan pesawat terbang dalam kurva aerodinamis optimal (28.4% MAC). Dokumen <strong>Electronic Loadsheet</strong> resmi langsung terkirim secara nirkabel ke Electronic Flight Bag (EFB) pilot di kokpit, memangkas waktu pushback ramp kargo secara signifikan.
                        </p>
                        <div class="pt-2 flex items-center gap-2">
                            <button onclick="switchSystemDashboard('avsec')" class="flex-1 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-arrow-left text-xs"></i>
                                <span>AVSEC</span>
                            </button>
                            <button onclick="switchSystemDashboard('all')" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-[#0CA1AF] to-[#087F8A] hover:from-[#087F8A] hover:to-[#014D54] text-white font-bold text-xs shadow-md transition-all flex items-center justify-center gap-1.5">
                                <span>Alur Terpadu 7-Tahap</span>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </button>
                        </div>
                    </div>

                </div>
            </div>

        </div>

    </div>

</div>

<!-- Modal Master Cargo JSON Payload (Hardware Replacement Surrogate PoC) -->
<div id="masterCargoPayloadModal" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-4xl rounded-2xl border border-gray-200 p-6 shadow-2xl relative flex flex-col max-h-[90vh]">
        <button onclick="closeMasterCargoPayloadModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-700 text-lg transition-colors cursor-pointer" title="Tutup Modal">
            <i class="fa-solid fa-xmark"></i>
        </button>
        
        <div class="pb-3 border-b border-gray-100 mb-4">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-6 rounded-full bg-gradient-to-b from-[#0CA1AF] to-[#087F8A]"></span>
                <h3 class="text-base font-extrabold text-[#0D1C42]">
                    Master JSON Payload Kargo (Hardware Surrogate PoC)
                </h3>
                <span id="modal-payload-badge" class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-teal-50 text-[#087F8A] border border-teal-200">
                    CMP-...
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-4">
                Satu dokumen JSON terintegrasi per kargo yang merepresentasikan pembacaan 7 perangkat sensor Layer 1 &amp; riwayat perpindahan data di seluruh tahapan terminal.
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 mb-3 text-xs">
            <div class="flex items-center space-x-2 text-[11px] text-slate-600">
                <span class="font-bold">Standar:</span>
                <span class="font-mono bg-emerald-50 text-emerald-700 px-2.5 py-0.5 rounded-lg border border-emerald-200 font-semibold text-[10.5px]">GS1 SSCC-18 / JSON Schema</span>
                <span class="text-slate-300">|</span>
                <span class="text-slate-500">Kargo Aktif: <strong id="modal-cargo-awb" class="text-[#0D1C42] font-mono">-</strong></span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="copyMasterPayloadJson()" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-gray-200 transition-all flex items-center gap-1.5 shadow-sm cursor-pointer">
                    <i class="fa-solid fa-copy text-[#087F8A]"></i>
                    <span>Salin JSON</span>
                </button>
                <button type="button" onclick="downloadMasterPayloadJson()" class="px-3 py-1.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/20 transition-all flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-download"></i>
                    <span>Download .json</span>
                </button>
            </div>
        </div>

        <!-- Scrollable Code Box -->
        <div class="flex-grow overflow-auto bg-[#0D1C42] border border-slate-700 rounded-xl p-4 text-xs font-mono relative shadow-inner min-h-[350px]">
            <pre id="modalJsonDisplay" class="text-emerald-300 leading-relaxed text-[11px] whitespace-pre">// Memuat data Master JSON Payload kargo...</pre>
        </div>

        <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-slate-500">
            <span>Proof of Concept &bull; PT Aerospace Consultant Advisory &bull; ITL Trisakti</span>
            <button type="button" onclick="closeMasterCargoPayloadModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-gray-200 transition-all cursor-pointer">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- Simulation Engine Scripts -->
<script src="assets/js/simulation.js?v=<?= filemtime(__DIR__ . '/assets/js/simulation.js') ?>"></script>
<script>
    // Inisialisasi simulasi dengan kargo terpilih, tahap awal, dan sistem awal
    document.addEventListener('DOMContentLoaded', () => {
        initSimulation('<?= htmlspecialchars($selectedCargoId) ?>', <?= $initialStage > 0 ? $initialStage : 'null' ?>, '<?= htmlspecialchars($activeSystem) ?>');
    });

    function changeCargo(cargoId) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('cargo_id', cargoId);
        window.location.href = 'simulation.php?' + urlParams.toString();
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
