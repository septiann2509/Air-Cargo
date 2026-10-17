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
$trucks = $db->query("SELECT * FROM truck_arrivals ORDER BY arrival_time DESC LIMIT 6")->fetchAll();
$ulds = $db->query("SELECT u.*, f.flight_number, f.destination FROM uld_containers u LEFT JOIN flight_manifests f ON u.flight_id = f.id ORDER BY u.uld_code ASC")->fetchAll();
$flights = $db->query("SELECT * FROM flight_manifests ORDER BY departure_time ASC")->fetchAll();
$secChecks = $db->query("SELECT s.*, c.awb_number, c.commodity_type, c.sscc FROM security_checks s JOIN cargo_shipments c ON s.cargo_id = c.id ORDER BY s.check_time DESC LIMIT 6")->fetchAll();
$weightRecords = $db->query("SELECT w.*, c.awb_number FROM weight_records w JOIN cargo_shipments c ON w.cargo_id = c.id ORDER BY w.weigh_time DESC LIMIT 6")->fetchAll();

$pageTitle = 'Laboratorium Simulasi 7-Tahap & Dedicated System Dashboards';
$bodyClass = 'bg-batik-canvas text-slate-800';
include __DIR__ . '/includes/header.php';
?>

<div class="relative w-full overflow-x-hidden min-h-screen">

    <!-- SISI KIRI: Motif Batik InJourney HANYA di margin samping kiri -->
    <div class="fixed left-0 top-16 bottom-0 pointer-events-none select-none z-0 overflow-hidden hidden xl:block"
         style="width: max(0px, calc((100vw - 1280px) / 2));" aria-hidden="true">
        <img src="assets/img/injourney/pattern-green-BTZ3yksm.png" 
             alt="" 
             class="absolute top-6 left-0 w-[420px] max-w-none opacity-80 transform -scale-x-100">
    </div>

    <!-- SISI KANAN: Motif Batik InJourney HANYA di margin samping kanan -->
    <div class="fixed right-0 top-16 bottom-0 pointer-events-none select-none z-0 overflow-hidden hidden xl:block"
         style="width: max(0px, calc((100vw - 1280px) / 2));" aria-hidden="true">
        <img src="assets/img/injourney/pattern-green-BTZ3yksm.png" 
             alt="" 
             class="absolute top-6 right-0 w-[420px] max-w-none opacity-80">
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

                <!-- Denah 3D Terminal Link Button -->
                <a href="layout_3d.php" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-teal-50 text-[#087F8A] hover:text-[#0CA1AF] text-xs font-bold border border-teal-200 shadow-sm transition-all flex items-center">
                    <i class="fa-solid fa-cubes mr-1.5 text-xs text-[#0CA1AF]"></i>
                    <span>Denah 3D</span>
                </a>

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

                        <!-- Hardware & Software Badges -->
                        <div class="grid grid-cols-2 gap-3 mb-5 text-xs">
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200">
                                <span class="text-[10px] text-slate-500 font-bold block uppercase tracking-wider">Layer 1: Hardware Lapangan</span>
                                <span id="activeHardwareName" class="text-[#0D1C42] font-bold block mt-0.5">-</span>
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

                        <!-- Sub-Tabs: Request vs Response -->
                        <div class="flex space-x-2 mb-3">
                            <button id="tabBtnRequest" onclick="switchPayloadTab('request')" class="flex-1 py-1.5 px-3 rounded-lg text-xs font-bold bg-[#087F8A] text-white shadow-sm transition-all flex items-center justify-center">
                                <i class="fa-solid fa-arrow-up-right-from-square mr-1.5"></i> HTTP Request
                            </button>
                            <button id="tabBtnResponse" onclick="switchPayloadTab('response')" class="flex-1 py-1.5 px-3 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:text-slate-800 border border-gray-200 transition-all flex items-center justify-center">
                                <i class="fa-solid fa-arrow-down-left-and-up-right-to-center mr-1.5"></i> System Response
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
                        <span class="text-2xl font-black text-[#0D1C42] font-mono mt-1 block"><?= count($trucks) ?> <span class="text-xs text-slate-500 font-sans font-normal">Armada</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 text-[#087F8A] flex items-center justify-center text-lg">
                        <i class="fa-solid fa-truck-moving"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/90 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Alokasi Dock Slot</span>
                        <span id="tms-dock-summary-stat" class="text-2xl font-black text-emerald-600 font-mono mt-1 block">2/4 Terisi <span class="text-xs text-slate-500 font-sans font-normal">2 Kosong</span></span>
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
                        <span class="text-2xl font-black text-emerald-700 font-mono mt-1 block">READY <span class="text-xs text-slate-500 font-sans font-normal">1.2s Boom</span></span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-torii-gate"></i>
                    </div>
                </div>
            </div>

            <!-- TMS Main Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left Column: Dock Management & Active Truck Console (7 cols) -->
                <div class="lg:col-span-7 space-y-6">
                    
                    <!-- Visual Dock Allocation Cards -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 mb-4 border-b border-gray-100 gap-2">
                            <div>
                                <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider flex items-center">
                                    <i class="fa-solid fa-dolly text-[#087F8A] mr-2 text-sm"></i>
                                    Status Live Terminal Cargo Docks (Inbound Unloading Bay)
                                </h3>
                                <p class="text-[10px] text-slate-500 mt-0.5">Klik langsung kartu dock bertanda <strong class="text-emerald-700">KOSONG</strong> untuk memilih alokasi bongkar</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span id="tms-dock-occupied-badge" class="text-[10px] font-mono text-rose-700 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-200 font-bold">2 Penuh</span>
                                <span id="tms-dock-available-badge" class="text-[10px] font-mono text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200 font-bold">2 Kosong</span>
                            </div>
                        </div>

                        <!-- 4 Interactive Dock Cards -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" id="tmsDockCardsContainer">
                            <!-- DOCK 01: PENUH -->
                            <div id="dock-card-DOCK-01" onclick="handleDockCardClick('DOCK-01')" class="p-3.5 rounded-xl border border-rose-300 bg-rose-50/60 text-center transition-all cursor-pointer hover:border-rose-400 hover:shadow-md relative overflow-hidden group select-none" title="Klik untuk lihat rincian armada">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-rose-800 uppercase font-mono">DOCK 01</span>
                                    <span id="dock-badge-DOCK-01" class="text-[8.5px] px-1.5 py-0.5 rounded font-extrabold uppercase bg-rose-200 text-rose-800 border border-rose-300">PENUH</span>
                                </div>
                                <div id="dock-icon-DOCK-01" class="my-2 text-2xl text-rose-500 group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-truck-ramp-box"></i>
                                </div>
                                <span id="dock-plate-DOCK-01" class="text-xs font-bold text-rose-950 block font-mono">B 9182 KXA</span>
                                <span id="dock-status-DOCK-01" class="text-[9px] text-rose-700 font-bold block mt-0.5">
                                    <i class="fa-solid fa-circle-exclamation mr-1"></i>Sedang Bongkar
                                </span>
                                <span class="text-[8.5px] text-rose-500 font-semibold block mt-1">Sisa ±12 mnt &bull; Klik Info</span>
                            </div>

                            <!-- DOCK 02: KOSONG / TERPILIH -->
                            <div id="dock-card-DOCK-02" onclick="handleDockCardClick('DOCK-02')" class="p-3.5 rounded-xl border-2 border-[#0CA1AF] bg-teal-50/70 text-center transition-all shadow-md ring-2 ring-teal-400/30 cursor-pointer relative overflow-hidden group select-none" title="Alokasi terpilih kargo saat ini">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-[#087F8A] uppercase font-mono">DOCK 02</span>
                                    <span id="dock-badge-DOCK-02" class="text-[8.5px] px-1.5 py-0.5 rounded font-extrabold uppercase bg-teal-100 text-[#087F8A] border border-teal-300">TERPILIH</span>
                                </div>
                                <div id="dock-icon-DOCK-02" class="my-2 text-2xl text-[#087F8A] group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-truck"></i>
                                </div>
                                <span id="dock-plate-DOCK-02" class="text-xs font-black text-[#0D1C42] block font-mono">B 9482 XX</span>
                                <span id="dock-status-DOCK-02" class="text-[9px] text-teal-800 font-bold block mt-0.5">
                                    <i class="fa-solid fa-circle-check mr-1 text-emerald-600"></i>Alokasi Bongkar Anda
                                </span>
                                <span class="text-[8.5px] text-[#087F8A] font-semibold block mt-1">Dock Aktif Kargo</span>
                            </div>

                            <!-- DOCK 03: PENUH -->
                            <div id="dock-card-DOCK-03" onclick="handleDockCardClick('DOCK-03')" class="p-3.5 rounded-xl border border-rose-300 bg-rose-50/60 text-center transition-all cursor-pointer hover:border-rose-400 hover:shadow-md relative overflow-hidden group select-none" title="Klik untuk lihat rincian armada">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-rose-800 uppercase font-mono">DOCK 03</span>
                                    <span id="dock-badge-DOCK-03" class="text-[8.5px] px-1.5 py-0.5 rounded font-extrabold uppercase bg-rose-200 text-rose-800 border border-rose-300">PENUH</span>
                                </div>
                                <div id="dock-icon-DOCK-03" class="my-2 text-2xl text-rose-500 group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-truck-ramp-box"></i>
                                </div>
                                <span id="dock-plate-DOCK-03" class="text-xs font-bold text-rose-950 block font-mono">B 4412 PQL</span>
                                <span id="dock-status-DOCK-03" class="text-[9px] text-rose-700 font-bold block mt-0.5">
                                    <i class="fa-solid fa-circle-exclamation mr-1"></i>Sedang Bongkar
                                </span>
                                <span class="text-[8.5px] text-rose-500 font-semibold block mt-1">Sisa ±25 mnt &bull; Klik Info</span>
                            </div>

                            <!-- DOCK 04: KOSONG / TERSEDIA -->
                            <div id="dock-card-DOCK-04" onclick="handleDockCardClick('DOCK-04')" class="p-3.5 rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50/20 text-center transition-all cursor-pointer hover:border-emerald-500 hover:bg-emerald-50 hover:shadow-md relative overflow-hidden group select-none" title="Klik untuk memilih dock ini">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-emerald-700 uppercase font-mono">DOCK 04</span>
                                    <span id="dock-badge-DOCK-04" class="text-[8.5px] px-1.5 py-0.5 rounded font-extrabold uppercase bg-emerald-100 text-emerald-700 border border-emerald-200">KOSONG</span>
                                </div>
                                <div id="dock-icon-DOCK-04" class="my-2 text-2xl text-emerald-500 group-hover:scale-110 transition-transform">
                                    <i class="fa-solid fa-circle-check"></i>
                                </div>
                                <span id="dock-plate-DOCK-04" class="text-xs font-bold text-emerald-700 block font-mono">TERSEDIA</span>
                                <span id="dock-status-DOCK-04" class="text-[9px] text-emerald-700 font-semibold block mt-0.5">Standby Leveler</span>
                                <span class="text-[8.5px] text-emerald-600 font-bold block mt-1 group-hover:underline">Klik untuk Pilih &rarr;</span>
                            </div>
                        </div>
                    </div>

                    <!-- Active Cargo's Truck Check-In & Action Console (Input Mandiri Staff) -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm relative overflow-hidden">
                        
                        <!-- Header with Input Badge & Quick Preset Button -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 mb-4 border-b border-gray-100 gap-2">
                            <div class="flex items-center space-x-2">
                                <i class="fa-solid fa-id-card-clip text-[#087F8A] text-base"></i>
                                <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider">
                                    Registrasi Kedatangan Truk &amp; Muatan Kargo (TMS)
                                </h3>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-50 text-amber-700 border border-amber-200 uppercase tracking-wide">
                                    Input Staff Gate
                                </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button type="button" onclick="generateRandomCargoManifest()" class="px-2.5 py-1 rounded-lg text-[10px] font-bold text-[#087F8A] bg-teal-50 hover:bg-teal-100 border border-teal-200 transition-all flex items-center gap-1 shadow-sm" title="Generate Kargo Baru Acak">
                                    <i class="fa-solid fa-dice text-[10px]"></i>
                                    <span>Acak Muatan Kargo</span>
                                </button>
                                <button type="button" onclick="presetTMSTruckData()" class="px-2.5 py-1 rounded-lg text-[10px] font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 transition-all flex items-center gap-1 shadow-sm" title="Isi Preset Standar">
                                    <i class="fa-solid fa-wand-magic-sparkles text-[10px]"></i>
                                    <span>Preset Standar</span>
                                </button>
                                <span id="tms-cargo-stage-badge" class="text-[10px] font-mono text-[#087F8A] bg-teal-50 px-2.5 py-1 rounded-full border border-teal-200 font-bold">
                                    Tahap 1
                                </span>
                            </div>
                        </div>

                        <!-- SECTION A: Data Armada Truk -->
                        <div class="mb-4">
                            <div class="flex items-center space-x-2 mb-2.5">
                                <span class="w-1.5 h-3.5 bg-[#087F8A] rounded-full"></span>
                                <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-700">
                                    A. Data Armada Truk &amp; Alokasi Dock (Gate TAS)
                                </h4>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                                <!-- Input 1: Nomor Plat Polisi -->
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200 focus-within:border-[#0CA1AF] focus-within:bg-teal-50/20 transition-all">
                                    <label for="input-tms-plate" class="text-[10px] text-slate-500 block uppercase font-bold tracking-wider mb-1">
                                        <i class="fa-solid fa-car text-[#087F8A] mr-1"></i> Nomor Plat Polisi
                                    </label>
                                    <input type="text" id="input-tms-plate" oninput="handlePlateInput(this.value)" class="w-full bg-white border border-gray-300 focus:border-[#087F8A] focus:ring-1 focus:ring-[#087F8A] rounded-lg px-2.5 py-1.5 text-xs font-mono font-black text-[#0D1C42] uppercase focus:outline-none transition-all shadow-sm" placeholder="Contoh: B 8888 KGO" value="B 8888 KGO">
                                </div>

                                <!-- Input 2: Nama Supir Terdaftar -->
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200 focus-within:border-[#0CA1AF] focus-within:bg-teal-50/20 transition-all">
                                    <label for="input-tms-driver" class="text-[10px] text-slate-500 block uppercase font-bold tracking-wider mb-1">
                                        <i class="fa-solid fa-user text-[#087F8A] mr-1"></i> Nama Supir Terdaftar
                                    </label>
                                    <input type="text" id="input-tms-driver" class="w-full bg-white border border-gray-300 focus:border-[#087F8A] focus:ring-1 focus:ring-[#087F8A] rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:outline-none transition-all shadow-sm" placeholder="Contoh: Bambang Pamungkas" value="Bambang Pamungkas">
                                </div>

                                <!-- Input 3: Vendor Transportasi -->
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200 focus-within:border-[#0CA1AF] focus-within:bg-teal-50/20 transition-all">
                                    <label for="input-tms-transporter" class="text-[10px] text-slate-500 block uppercase font-bold tracking-wider mb-1">
                                        <i class="fa-solid fa-building text-[#087F8A] mr-1"></i> Vendor Transportasi
                                    </label>
                                    <input type="text" id="input-tms-transporter" class="w-full bg-white border border-gray-300 focus:border-[#087F8A] focus:ring-1 focus:ring-[#087F8A] rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-800 focus:outline-none transition-all shadow-sm" placeholder="Contoh: PT Angkasa Pura Logistik" value="PT Angkasa Pura Logistik">
                                </div>

                                <!-- Input 4: Alokasi Dock Unloading -->
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200 focus-within:border-[#0CA1AF] focus-within:bg-teal-50/20 transition-all">
                                    <div class="flex items-center justify-between mb-1">
                                        <label for="input-tms-dock" class="text-[10px] text-slate-500 block uppercase font-bold tracking-wider">
                                            <i class="fa-solid fa-warehouse text-[#087F8A] mr-1"></i> Alokasi Dock Unloading
                                        </label>
                                        <span class="text-[9px] font-bold text-slate-400">Pilih / Klik Kartu</span>
                                    </div>
                                    <select id="input-tms-dock" onchange="handleDockSelectChange(this.value)" class="w-full bg-white border border-gray-300 focus:border-[#087F8A] focus:ring-1 focus:ring-[#087F8A] rounded-lg px-2.5 py-1.5 text-xs font-mono font-bold text-[#087F8A] focus:outline-none cursor-pointer transition-all shadow-sm">
                                        <option value="DOCK-01" disabled class="text-rose-600 bg-rose-50 font-semibold">DOCK-01 (PENUH - B 9182 KXA Sedang Bongkar)</option>
                                        <option value="DOCK-02" selected class="text-emerald-700 font-bold">DOCK-02 (KOSONG - Siap Digunakan)</option>
                                        <option value="DOCK-03" disabled class="text-rose-600 bg-rose-50 font-semibold">DOCK-03 (PENUH - B 4412 PQL Sedang Bongkar)</option>
                                        <option value="DOCK-04" class="text-emerald-700 font-bold">DOCK-04 (KOSONG - Siap Digunakan)</option>
                                    </select>
                                </div>

                                <!-- Input 5: Kode Booking TAS -->
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200 focus-within:border-[#0CA1AF] focus-within:bg-teal-50/20 transition-all">
                                    <div class="flex items-center justify-between mb-1">
                                        <label for="input-tms-booking" class="text-[10px] text-slate-500 block uppercase font-bold tracking-wider">
                                            <i class="fa-solid fa-barcode text-[#087F8A] mr-1"></i> Kode Booking TAS
                                        </label>
                                        <button type="button" onclick="generateRandomTASBooking()" class="text-[9px] text-[#087F8A] hover:underline font-bold flex items-center gap-1" title="Generate Kode Acak">
                                            <i class="fa-solid fa-dice text-[10px]"></i> Acak
                                        </button>
                                    </div>
                                    <input type="text" id="input-tms-booking" class="w-full bg-white border border-gray-300 focus:border-[#087F8A] focus:ring-1 focus:ring-[#087F8A] rounded-lg px-2.5 py-1.5 text-xs font-mono font-bold text-[#087F8A] focus:outline-none transition-all shadow-sm" placeholder="Contoh: TAS-BOOK-9482" value="TAS-BOOK-9482">
                                </div>

                                <!-- Input 6: Status TAS Gate -->
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200 focus-within:border-[#0CA1AF] focus-within:bg-teal-50/20 transition-all">
                                    <label for="input-tms-status" class="text-[10px] text-slate-500 block uppercase font-bold tracking-wider mb-1">
                                        <i class="fa-solid fa-shield text-[#087F8A] mr-1"></i> Status TAS Gate
                                    </label>
                                    <select id="input-tms-status" class="w-full bg-white border border-gray-300 focus:border-[#087F8A] focus:ring-1 focus:ring-[#087F8A] rounded-lg px-2.5 py-1.5 text-xs font-extrabold text-teal-800 focus:outline-none cursor-pointer transition-all shadow-sm">
                                        <option value="ARRIVED" selected>ARRIVED (Tiba di Gerbang)</option>
                                        <option value="SCHEDULED">SCHEDULED (Terjadwal)</option>
                                        <option value="UNLOADING">UNLOADING (Sedang Bongkar)</option>
                                        <option value="COMPLETED">COMPLETED (Selesai Bongkar)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION B: Data Muatan Kargo yang Diangkut Truk (Manifest / Delivery Order) -->
                        <div class="mb-5 pt-3 border-t border-gray-100">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-2.5 gap-2">
                                <div class="flex items-center space-x-2">
                                    <span class="w-1.5 h-3.5 bg-emerald-600 rounded-full"></span>
                                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-700">
                                        B. Data Muatan Kargo yang Diangkut Truk (Manifest / Delivery Order)
                                    </h4>
                                </div>
                                <label class="flex items-center space-x-1.5 text-[11px] font-bold text-[#087F8A] cursor-pointer">
                                    <input type="checkbox" id="input-tms-new-cargo" checked class="rounded border-gray-300 text-[#087F8A] focus:ring-[#087F8A]">
                                    <span>Buat Kargo Baru (Generate SSCC-18 Otomatis)</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                                <!-- Input 7: Nomor Air Waybill (e-AWB) -->
                                <div class="p-3 rounded-xl bg-emerald-50/50 border border-emerald-200/90 focus-within:border-[#0CA1AF] transition-all">
                                    <div class="flex items-center justify-between mb-1">
                                        <label for="input-tms-awb" class="text-[10px] text-emerald-800 block uppercase font-bold tracking-wider">
                                            <i class="fa-solid fa-file-invoice text-emerald-600 mr-1"></i> Nomor e-AWB Kargo
                                        </label>
                                        <button type="button" onclick="generateRandomAWBNumber()" class="text-[9px] text-[#087F8A] hover:underline font-bold flex items-center gap-1" title="Generate AWB Baru">
                                            <i class="fa-solid fa-dice text-[10px]"></i> Acak AWB
                                        </button>
                                    </div>
                                    <input type="text" id="input-tms-awb" class="w-full bg-white border border-emerald-300 focus:border-[#087F8A] focus:ring-1 focus:ring-[#087F8A] rounded-lg px-2.5 py-1.5 text-xs font-mono font-black text-[#0D1C42] focus:outline-none transition-all shadow-sm" placeholder="Contoh: 126-85258013" value="126-85258013">
                                </div>

                                <!-- Input 8: Komoditas / Deskripsi Barang -->
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200 focus-within:border-[#0CA1AF] focus-within:bg-teal-50/20 transition-all">
                                    <label for="input-tms-desc" class="text-[10px] text-slate-500 block uppercase font-bold tracking-wider mb-1">
                                        <i class="fa-solid fa-box-open text-[#087F8A] mr-1"></i> Deskripsi Komoditas
                                    </label>
                                    <input type="text" id="input-tms-desc" class="w-full bg-white border border-gray-300 focus:border-[#087F8A] focus:ring-1 focus:ring-[#087F8A] rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-800 focus:outline-none transition-all shadow-sm" placeholder="Contoh: Pakaian & Tekstil Premium Ekspor" value="Pakaian & Tekstil Premium Ekspor">
                                </div>

                                <!-- Input 9: Kategori Komoditas (IATA Type) -->
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200 focus-within:border-[#0CA1AF] focus-within:bg-teal-50/20 transition-all">
                                    <label for="input-tms-comm-type" class="text-[10px] text-slate-500 block uppercase font-bold tracking-wider mb-1">
                                        <i class="fa-solid fa-tags text-[#087F8A] mr-1"></i> Kategori IATA
                                    </label>
                                    <select id="input-tms-comm-type" class="w-full bg-white border border-gray-300 focus:border-[#087F8A] focus:ring-1 focus:ring-[#087F8A] rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:outline-none cursor-pointer transition-all shadow-sm">
                                        <option value="GENERAL_CARGO" selected>GENERAL_CARGO (Umum / Garmen)</option>
                                        <option value="PHARMA">PHARMA (Farmasi &amp; Vaksin)</option>
                                        <option value="PERISHABLE">PERISHABLE (Makanan / Buah Segar)</option>
                                        <option value="VALUABLE">VALUABLE (Barang Berharga)</option>
                                        <option value="DANGEROUS_GOODS">DANGEROUS_GOODS (DG)</option>
                                    </select>
                                </div>

                                <!-- Input 10: Jumlah Koli (Pieces) -->
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200 focus-within:border-[#0CA1AF] focus-within:bg-teal-50/20 transition-all">
                                    <label for="input-tms-pieces" class="text-[10px] text-slate-500 block uppercase font-bold tracking-wider mb-1">
                                        <i class="fa-solid fa-boxes-stacked text-[#087F8A] mr-1"></i> Jumlah Koli (Pcs)
                                    </label>
                                    <input type="number" id="input-tms-pieces" min="1" max="1000" class="w-full bg-white border border-gray-300 focus:border-[#087F8A] focus:ring-1 focus:ring-[#087F8A] rounded-lg px-2.5 py-1.5 text-xs font-mono font-bold text-slate-800 focus:outline-none transition-all shadow-sm" value="50">
                                </div>

                                <!-- Input 11: Berat Muatan Deklarasi (Kg) -->
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200 focus-within:border-[#0CA1AF] focus-within:bg-teal-50/20 transition-all">
                                    <label for="input-tms-weight" class="text-[10px] text-slate-500 block uppercase font-bold tracking-wider mb-1">
                                        <i class="fa-solid fa-weight-hanging text-[#087F8A] mr-1"></i> Berat Bruto Muatan (Kg)
                                    </label>
                                    <input type="number" id="input-tms-weight" min="1" step="0.5" class="w-full bg-white border border-gray-300 focus:border-[#087F8A] focus:ring-1 focus:ring-[#087F8A] rounded-lg px-2.5 py-1.5 text-xs font-mono font-bold text-[#0D1C42] focus:outline-none transition-all shadow-sm" value="350.00">
                                </div>

                                <!-- Input 12: Shipper / Pengirim -->
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200 focus-within:border-[#0CA1AF] focus-within:bg-teal-50/20 transition-all">
                                    <label for="input-tms-shipper" class="text-[10px] text-slate-500 block uppercase font-bold tracking-wider mb-1">
                                        <i class="fa-solid fa-paper-plane text-[#087F8A] mr-1"></i> Shipper / Pengirim
                                    </label>
                                    <input type="text" id="input-tms-shipper" class="w-full bg-white border border-gray-300 focus:border-[#087F8A] focus:ring-1 focus:ring-[#087F8A] rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-800 focus:outline-none transition-all shadow-sm" placeholder="Contoh: PT Logistik Nusantara" value="PT Logistik Nusantara">
                                </div>
                            </div>
                        </div>

                        <!-- TMS Action Buttons -->
                        <div class="pt-2 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <button type="button" onclick="submitTMSCheckIn()" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5">
                                    <i class="fa-solid fa-truck mr-2"></i>
                                    <span>Verifikasi Kedatangan Truk di Gate (Tahap 1)</span>
                                </button>
                                <button type="button" onclick="presetTMSTruckData()" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-600 hover:text-[#087F8A] text-xs font-bold border border-gray-300 shadow-sm transition-colors flex items-center">
                                    <i class="fa-solid fa-rotate-left mr-1.5 text-xs"></i>
                                    <span>Reset Form</span>
                                </button>
                            </div>
                            <button type="button" onclick="switchSystemDashboard('cms')" class="px-4 py-2.5 rounded-xl bg-teal-50 hover:bg-teal-100 text-[#087F8A] text-xs font-bold border border-teal-200 shadow-sm transition-all flex items-center">
                                <span>Lanjut ke Dashboard CMS (Tahap 2 &amp; 3)</span>
                                <i class="fa-solid fa-arrow-right ml-1.5 text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Inbound Trucks Feed Table -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100">
                            <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider flex items-center">
                                <i class="fa-solid fa-list-check text-[#087F8A] mr-2 text-sm"></i>
                                Log Antrean Kedatangan Truk (Database: truck_arrivals)
                            </h3>
                            <span id="tms-truck-count-badge" class="text-[10px] font-mono text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full border border-gray-200 font-bold"><?= count($trucks) ?> Armada</span>
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
                            <span class="text-[10px] font-mono text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 font-bold">EDIFACT / REST</span>
                        </div>
                        <div class="flex-grow overflow-auto bg-[#0D1C42] border border-slate-700/80 rounded-xl p-4 text-xs font-mono relative shadow-inner">
                            <pre id="tmsPayloadDisplay" class="text-emerald-300 leading-relaxed text-[11px]">// Memuat JSON TMS Gate Ingest...</pre>
                        </div>
                    </div>

                    <!-- To-Be Consultant Recommendation Card -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm space-y-3">
                        <div class="flex items-center space-x-2 text-xs font-bold text-[#0D1C42]">
                            <i class="fa-solid fa-graduation-cap text-[#087F8A]"></i>
                            <span>Rekomendasi Konsultan (To-Be TMS/TAS):</span>
                        </div>
                        <p class="text-slate-600 text-[11.5px] leading-relaxed">
                            Penerapan <strong>Truck Appointment System (TAS)</strong> memotong waktu antrean truk di gerbang bandara dari <span class="text-rose-600 font-bold">78 menit</span> menjadi rata-rata <span class="text-emerald-600 font-bold">15 menit (-81%)</span>. Setiap truk terverifikasi secara nir-sentuh melalui RFID gate barrier dan langsung diarahkan ke hydraulic dock leveler yang ditentukan.
                        </p>
                        <div class="pt-2">
                            <button onclick="switchSystemDashboard('cms')" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-[#0CA1AF] to-[#087F8A] hover:from-[#087F8A] hover:to-[#014D54] text-white font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2">
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
                    // Filter cargos yang telah tervalidasi e-AWB dan teregistrasi di CMS (Stage >= 3)
                    $cmsRegisteredCargos = array_filter($cargos, function($cg) {
                        return (int)$cg['current_stage'] >= 3;
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
                                            <tr class="hover:bg-slate-50/70 transition-colors">
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
                    
                    <!-- Dual-View Visual Screening Console -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-100">
                            <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider flex items-center">
                                <i class="fa-solid fa-x-ray text-[#087F8A] mr-2 text-sm"></i>
                                Visual Konsol Sinar-X Dual-View (320kV Spectrum)
                            </h3>
                            <span id="avsec-status-indicator" class="text-[10px] font-mono px-3 py-1 rounded-full font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                STATUS: CLEARED
                            </span>
                        </div>

                        <!-- Density Color Spectrum Indicator -->
                        <div class="grid grid-cols-3 gap-2 mb-4 text-[11px] text-center">
                            <div class="p-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-900">
                                <span class="font-bold block text-xs">Organik (Oranye)</span>
                                <span class="text-[9px] text-amber-700">Makanan, Tekstil, Farmasi (Z=7-8)</span>
                            </div>
                            <div class="p-2 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900">
                                <span class="font-bold block text-xs">Campuran (Hijau)</span>
                                <span class="text-[9px] text-emerald-700">Aluminium, Kaca, Baterai Ringan</span>
                            </div>
                            <div class="p-2 rounded-lg bg-blue-50 border border-blue-200 text-blue-900">
                                <span class="font-bold block text-xs">Anorganik (Biru)</span>
                                <span class="text-[9px] text-blue-700">Baja, Tembaga, Kepadatan Tinggi (Z>18)</span>
                            </div>
                        </div>

                        <!-- Interactive CSD Certificate Card / Suspect Vault Warning -->
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
                                <div><span class="text-slate-400 block text-[9px] font-bold">METODE SCREENING:</span> <strong>XRY (Dual-View)</strong></div>
                                <div><span class="text-slate-400 block text-[9px] font-bold">WAKTU CLEARANCE:</span> <strong id="avsec-clearance-time">LIVE VALIDATED</strong></div>
                            </div>
                        </div>

                        <!-- Suspect Warning Box (Hidden by default, shown when SUSPECT) -->
                        <div id="avsec-suspect-warning-wrap" class="p-5 rounded-2xl border-2 border-rose-400 bg-rose-50 shadow-md mb-5 hidden animate-pulse">
                            <div class="flex items-center space-x-3 text-rose-700">
                                <i class="fa-solid fa-triangle-exclamation text-3xl"></i>
                                <div>
                                    <h4 class="text-sm font-black text-rose-800">PERINGATAN AVSEC: KARGO DITAHAN DI RUANG KARANTINA</h4>
                                    <p class="text-xs text-rose-600 mt-0.5">Terdeteksi anomali kepadatan tinggi mencurigakan. Diverter spur aktif, kargo terkunci otomatis!</p>
                                </div>
                            </div>
                        </div>

                        <!-- AVSEC Action Triggers -->
                        <div class="pt-1 flex flex-wrap items-center gap-3">
                            <button onclick="executeStageAction(4, 'CLEARED')" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-md shadow-emerald-500/25 transition-all flex items-center transform hover:-translate-y-0.5">
                                <i class="fa-solid fa-shield-check mr-2"></i>
                                <span>Simulasi Loloskan X-Ray (CLEARED &amp; Terbit CSD)</span>
                            </button>
                            <button onclick="executeStageAction(4, 'SUSPECT')" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white font-bold text-xs shadow-md shadow-rose-500/25 transition-all flex items-center transform hover:-translate-y-0.5">
                                <i class="fa-solid fa-triangle-exclamation mr-1.5"></i>
                                <span>Simulasi Temuan Bahaya (SUSPECT)</span>
                            </button>
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
                    
                    <!-- Smart Floor Scale & ULD Build-Up Workstation Card -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                        <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-100">
                            <h3 class="text-xs font-bold text-[#0D1C42] uppercase tracking-wider flex items-center">
                                <i class="fa-solid fa-boxes-packing text-[#087F8A] mr-2 text-sm"></i>
                                Penimbangan Smart Scale, ULD Consolidation, &amp; Flight Loading
                            </h3>
                            <span class="text-[10px] font-mono text-[#087F8A] bg-teal-50 px-2 py-0.5 rounded border border-teal-200 font-bold">Tahap 5, 6, &amp; 7</span>
                        </div>

                        <!-- 3-Col Visual Cards: Scale, ULD, and CoG -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                            
                            <!-- Scale Visual -->
                            <div class="p-4 rounded-xl bg-[#0D1C42] text-white text-center shadow-md">
                                <span class="text-[9px] uppercase tracking-wider text-teal-300 font-bold block">15T Smart Floor Scale</span>
                                <div id="lb-scale-weight" class="text-2xl font-black font-mono text-amber-400 my-1">- KG</div>
                                <span class="text-[9px] text-emerald-400 font-mono">Load Cell IP68 &bull; &plusmn;1kg</span>
                                <div class="mt-2 text-[10px] text-slate-300 border-t border-slate-700 pt-1.5">
                                    Deklarasi: <strong id="lb-scale-declared" class="text-white">-</strong>
                                </div>
                            </div>

                            <!-- ULD Container Visual -->
                            <div class="p-4 rounded-xl bg-teal-50/60 border border-teal-200 text-center shadow-sm">
                                <span class="text-[9px] uppercase tracking-wider text-[#087F8A] font-bold block">Kontainer Pesawat ULD</span>
                                <div id="lb-uld-code" class="text-base font-black font-mono text-[#0D1C42] my-1 truncate">AKE-12345-GA</div>
                                <span class="text-[10px] font-semibold text-[#087F8A] block">Tipe AKE &bull; Komp: AFT-LOWER-1</span>
                                <div class="mt-2 text-[10px] text-slate-600 border-t border-teal-200/60 pt-1.5">
                                    Maks: <strong class="text-[#0D1C42]">1588.0 kg</strong>
                                </div>
                            </div>

                            <!-- Center of Gravity (CoG) Visual -->
                            <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 text-center shadow-sm">
                                <span class="text-[9px] uppercase tracking-wider text-slate-500 font-bold block">Center of Gravity (CoG)</span>
                                <div class="text-2xl font-black font-mono text-emerald-600 my-1">28.4%</div>
                                <span class="text-[9px] text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Optimal Flight Trim</span>
                                <div class="mt-2 text-[10px] text-slate-500 border-t border-gray-200 pt-1.5">
                                    Batas Aman: 18% - 34% MAC
                                </div>
                            </div>

                        </div>

                        <!-- Interactive Action Triggers for Stages 5, 6, 7 -->
                        <div class="pt-1 flex flex-wrap items-center gap-3">
                            <button onclick="executeStageAction(5, 'CLEARED')" class="px-4 py-2.5 rounded-xl bg-white hover:bg-teal-50 text-[#087F8A] hover:text-[#0CA1AF] text-xs font-bold border border-teal-300 shadow-sm transition-all flex items-center transform hover:-translate-y-0.5">
                                <i class="fa-solid fa-scale-balanced mr-1.5"></i>
                                <span>Timbang di Smart Scale (Tahap 5)</span>
                            </button>
                            <button onclick="executeStageAction(6, 'CLEARED')" class="px-4 py-2.5 rounded-xl bg-white hover:bg-teal-50 text-[#087F8A] hover:text-[#0CA1AF] text-xs font-bold border border-teal-300 shadow-sm transition-all flex items-center transform hover:-translate-y-0.5">
                                <i class="fa-solid fa-boxes-packing mr-1.5"></i>
                                <span>Konsolidasi ke ULD (Tahap 6)</span>
                            </button>
                            <button onclick="executeStageAction(7, 'CLEARED')" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5">
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
