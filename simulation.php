<?php
// simulation.php
// Laboratorium Simulasi Interaktif 7 Tahapan Perpindahan Data Kargo (InJourney Airports Design System)
require_once __DIR__ . '/config/database.php';
requireAuth();

$user = getCurrentUser();
$db = getDb();

// Dapatkan daftar kargo
$cargosStmt = $db->query("SELECT id, awb_number, sscc, shipper, description, current_stage, status FROM cargo_shipments ORDER BY created_at DESC");
$cargos = $cargosStmt->fetchAll();

// Kargo terpilih
$selectedCargoId = $_GET['cargo_id'] ?? ($cargos[0]['id'] ?? '');

$pageTitle = 'Simulasi 7-Tahap PoC — Air Cargo Data Transfer';
$bodyClass = 'bg-[#F8FAFC] text-slate-800';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    
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
                Visualisasi interaktif perpindahan data antara <strong class="text-[#0D1C42] font-semibold">Hardware Lapangan (Layer 1)</strong> dan <strong class="text-[#0D1C42] font-semibold">Sistem CMS (Layer 3)</strong> melalui standar <strong class="text-[#087F8A] font-semibold">GS1 SSCC &amp; REST API (Layer 4)</strong>.
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
    <div id="cargoHeaderCard" class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm relative overflow-hidden">
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
                2 => ['icon' => 'fa-qrcode', 'name' => '2. Scan RFID Gate', 'sub' => 'High-Speed AIDC'],
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
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left Column: Stage Visualizer & Interactive Triggers (7 cols) -->
        <div class="lg:col-span-7 space-y-8">
            
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

            </div>

            <!-- GS1 e-Label Anatomy Visualization (Shows up particularly on Stage 2 & 3) -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200/90 shadow-sm">
                <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-100">
                    <h3 class="text-xs font-bold text-[#0D1C42] flex items-center">
                        <i class="fa-solid fa-tag text-[#087F8A] mr-2"></i>
                        Anatomi Standar GS1 e-Label (Pallet &amp; Cargo Unit)
                    </h3>
                    <span class="text-[10px] font-mono text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-gray-200 font-semibold">GS1-128 / SSCC-18</span>
                </div>
                
                <div class="bg-white text-slate-900 p-4 rounded-xl font-mono text-[11px] shadow-sm border-2 border-dashed border-gray-300">
                    <!-- Human Readable Block -->
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
                    <!-- Barcode Item GS1-128 Block -->
                    <div class="border-b border-gray-200 pb-2 mb-2 text-center">
                        <div class="h-8 barcode-stripe rounded w-full mb-1"></div>
                        <div class="text-[9px] text-slate-600 font-bold">
                            (01) <span id="label-gtin">-</span> (10) <span id="label-batch">-</span> (17) <span id="label-exp">-</span>
                        </div>
                    </div>
                    <!-- SSCC Block -->
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
        <div class="lg:col-span-5 space-y-8">
            
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

<!-- Simulation Engine Scripts -->
<script src="assets/js/simulation.js"></script>
<script>
    // Initialize simulation page with current cargo
    document.addEventListener('DOMContentLoaded', () => {
        initSimulation('<?= htmlspecialchars($selectedCargoId) ?>');
    });

    function changeCargo(cargoId) {
        window.location.href = 'simulation.php?cargo_id=' + encodeURIComponent(cargoId);
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
