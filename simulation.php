<?php
// simulation.php
// Laboratorium Simulasi Interaktif 7 Tahapan Perpindahan Data Kargo
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
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Top Bar: Title & Cargo Selector -->
    <div class="glass-panel p-6 rounded-2xl border border-slate-800 mb-8 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <span class="w-3 h-3 rounded-full bg-emerald-400 animate-ping"></span>
                <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">Laboratorium Simulasi Aliran Data (PoC)</h1>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-950 text-indigo-300 border border-indigo-700/60 uppercase">
                    Interactive Engine
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Visualisasi interaktif bagaimana data berpindah antar <strong>Hardware (Layer 1)</strong> dan <strong>Software (Layer 3)</strong> melalui standar <strong>GS1/JSON (Layer 4)</strong>.
            </p>
        </div>

        <!-- Selector & Global Action Buttons -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center space-x-2 bg-slate-900/90 px-3 py-1.5 rounded-xl border border-slate-700">
                <label for="cargoSelect" class="text-xs text-slate-400 font-medium">Pilih Kargo:</label>
                <select id="cargoSelect" onchange="changeCargo(this.value)" class="bg-transparent text-xs font-semibold text-sky-400 focus:outline-none cursor-pointer">
                    <?php foreach ($cargos as $cg): ?>
                        <option value="<?= htmlspecialchars($cg['id']) ?>" class="bg-slate-900 text-white" <?= $cg['id'] === $selectedCargoId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cg['awb_number']) ?> &bull; <?= htmlspecialchars($cg['description']) ?> (Tahap <?= $cg['current_stage'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Auto-Run Demo Button -->
            <button onclick="triggerAutoRun()" class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-lg shadow-emerald-500/20 transition-all flex items-center">
                <i class="fa-solid fa-wand-magic-sparkles mr-1.5"></i>
                <span>Auto-Run Demo (1-Click)</span>
            </button>

            <!-- Reset Button -->
            <button onclick="confirmResetData()" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-slate-700 transition-colors">
                <i class="fa-solid fa-rotate-left mr-1"></i> Reset
            </button>
        </div>
    </div>

    <!-- Active Cargo Header Banner -->
    <div id="cargoHeaderCard" class="glass-panel p-5 rounded-2xl border border-slate-800 mb-8 relative overflow-hidden">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 text-xs">
            <div>
                <span class="text-slate-500 block text-[10px] uppercase font-semibold">Nomor Air Waybill</span>
                <span id="card-awb" class="text-white font-mono font-bold text-sm">-</span>
            </div>
            <div>
                <span class="text-slate-500 block text-[10px] uppercase font-semibold">GS1 SSCC (18-Digit)</span>
                <span id="card-sscc" class="text-sky-400 font-mono font-bold">-</span>
            </div>
            <div>
                <span class="text-slate-500 block text-[10px] uppercase font-semibold">Shipper & Penerima</span>
                <span id="card-shipper" class="text-slate-200 truncate block font-medium">-</span>
            </div>
            <div>
                <span class="text-slate-500 block text-[10px] uppercase font-semibold">Komoditas & Koli</span>
                <span id="card-commodity" class="text-slate-200 block">-</span>
            </div>
            <div>
                <span class="text-slate-500 block text-[10px] uppercase font-semibold">Berat (Deklarasi / Timbang)</span>
                <span id="card-weight" class="text-slate-200 font-mono block">-</span>
            </div>
            <div>
                <span class="text-slate-500 block text-[10px] uppercase font-semibold">Status Operasional</span>
                <span id="card-status-badge" class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-800 text-slate-300">
                    LOADING...
                </span>
            </div>
        </div>
    </div>

    <!-- 7-Stage Interactive Progress Bar -->
    <div class="glass-panel p-5 rounded-2xl border border-slate-800 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider flex items-center">
                <i class="fa-solid fa-arrows-split-up-and-left text-sky-400 mr-2"></i>
                Progres 7 Tahapan Perpindahan Data Kargo
            </h3>
            <span id="currentStageBadge" class="text-[11px] font-mono px-2 py-0.5 rounded bg-sky-950 text-sky-300 border border-sky-800">
                Tahap Aktif: 1
            </span>
        </div>

        <!-- Visual Stepper -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2" id="stepperContainer">
            <?php
            $stagesConfig = [
                1 => ['icon' => 'fa-truck', 'name' => '1. Kedatangan Truk', 'sub' => 'TMS / TAS'],
                2 => ['icon' => 'fa-qrcode', 'name' => '2. Scan RFID Gate', 'sub' => 'High-Speed AIDC'],
                3 => ['icon' => 'fa-laptop-code', 'name' => '3. Registrasi CMS', 'sub' => 'e-AWB Matching'],
                4 => ['icon' => 'fa-shield-halved', 'name' => '4. X-Ray AVSEC', 'sub' => 'Dual-View Scan'],
                5 => ['icon' => 'fa-scale-balanced', 'name' => '5. Penimbangan', 'sub' => 'Smart ULD Scale'],
                6 => ['icon' => 'fa-boxes-packing', 'name' => '6. Build-Up ULD', 'sub' => 'Weight & Balance'],
                7 => ['icon' => 'fa-plane-departure', 'name' => '7. Flight Loading', 'sub' => 'Manifes Final']
            ];
            foreach ($stagesConfig as $num => $stg):
            ?>
                <button type="button" onclick="selectStageView(<?= $num ?>)" id="step-btn-<?= $num ?>" class="stage-step-card text-left p-3 rounded-xl border border-slate-800 bg-slate-900/60 hover:border-slate-600 transition-all flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-1.5">
                        <span id="step-circle-<?= $num ?>" class="w-6 h-6 rounded-full bg-slate-800 text-slate-400 text-xs font-bold flex items-center justify-center font-mono">
                            <?= $num ?>
                        </span>
                        <i class="fa-solid <?= $stg['icon'] ?> text-slate-500 text-xs" id="step-icon-<?= $num ?>"></i>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold text-slate-300" id="step-title-<?= $num ?>"><?= $stg['name'] ?></div>
                        <div class="text-[10px] text-slate-500"><?= $stg['sub'] ?></div>
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
            <div class="glass-panel p-6 rounded-2xl border border-slate-800">
                
                <!-- Stage Header -->
                <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-800">
                    <div class="flex items-center space-x-3">
                        <div id="activeStageIconWrap" class="w-12 h-12 rounded-xl bg-sky-950/80 border border-sky-500/30 text-sky-400 flex items-center justify-center text-xl">
                            <i id="activeStageIcon" class="fa-solid fa-truck"></i>
                        </div>
                        <div>
                            <span id="activeStageNumber" class="text-[10px] font-mono uppercase tracking-wider text-sky-400 font-bold">Tahap 1 dari 7</span>
                            <h2 id="activeStageTitle" class="text-base font-bold text-white">Kedatangan Truk & Booking Slot (TMS/TAS)</h2>
                        </div>
                    </div>
                    <span id="stageCompleteBadge" class="text-xs px-2.5 py-1 rounded-full font-bold bg-slate-800 text-slate-400 border border-slate-700">
                        Status: Pending
                    </span>
                </div>

                <!-- Hardware & Software Badges -->
                <div class="grid grid-cols-2 gap-3 mb-5 text-xs">
                    <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800">
                        <span class="text-[10px] text-slate-500 font-semibold block uppercase">Layer 1: Hardware Lapangan</span>
                        <span id="activeHardwareName" class="text-slate-200 font-bold block mt-0.5">-</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800">
                        <span class="text-[10px] text-slate-500 font-semibold block uppercase">Layer 3: Software Sistem</span>
                        <span id="activeSoftwareName" class="text-slate-200 font-bold block mt-0.5">-</span>
                    </div>
                </div>

                <!-- Interactive Stage Visual Graphics -->
                <div id="stageVisualGraphic" class="p-6 rounded-xl bg-[#070b14] border border-slate-800 mb-6 flex flex-col items-center justify-center text-center relative overflow-hidden min-h-[170px]">
                    <!-- Dynamic Graphic inserted via JS -->
                </div>

                <!-- Stage Explanation / Advisory Recommendation -->
                <div class="mb-6 p-4 rounded-xl bg-slate-900/90 border border-slate-800 text-xs">
                    <h4 class="text-white font-semibold mb-1 flex items-center text-xs">
                        <i class="fa-solid fa-lightbulb text-amber-400 mr-1.5"></i>
                        Penjelasan Konsultan (To-Be Architecture):
                    </h4>
                    <p id="activeStageExplanation" class="text-slate-300 leading-relaxed text-[11px]">
                        -
                    </p>
                </div>

                <!-- Action Buttons Container -->
                <div id="stageActionButtons" class="pt-2 flex flex-wrap items-center gap-3">
                    <!-- Dynamic Buttons rendered via JS based on current stage -->
                </div>

            </div>

            <!-- GS1 e-Label Anatomy Visualization (Shows up particularly on Stage 2 & 3) -->
            <div class="glass-panel p-5 rounded-2xl border border-slate-800">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold text-white flex items-center">
                        <i class="fa-solid fa-tag text-sky-400 mr-2"></i>
                        Anatomi Standar GS1 e-Label (Pallet & Cargo Unit)
                    </h3>
                    <span class="text-[10px] font-mono text-slate-400">GS1-128 / SSCC-18</span>
                </div>
                
                <div class="bg-white text-slate-900 p-4 rounded-xl font-mono text-[11px] shadow-lg border border-slate-300">
                    <!-- Human Readable Block -->
                    <div class="border-b border-slate-300 pb-2 mb-2 grid grid-cols-2 gap-2 text-[10px]">
                        <div>
                            <strong>SHIP TO:</strong> <span id="label-dest">DPS - DENPASAR</span><br>
                            <strong>CONSIGNEE:</strong> <span id="label-consignee">-</span>
                        </div>
                        <div class="text-right">
                            <strong>AWB:</strong> <span id="label-awb">-</span><br>
                            <strong>PIECES:</strong> <span id="label-qty">-</span> | <strong>WEIGHT:</strong> <span id="label-weight">-</span>
                        </div>
                    </div>
                    <!-- Barcode Item GS1-128 Block -->
                    <div class="border-b border-slate-300 pb-2 mb-2 text-center">
                        <div class="h-8 barcode-stripe rounded w-full mb-1"></div>
                        <div class="text-[9px] text-slate-600">
                            (01) <span id="label-gtin">-</span> (10) <span id="label-batch">-</span> (17) <span id="label-exp">-</span>
                        </div>
                    </div>
                    <!-- SSCC Block -->
                    <div class="text-center pt-1">
                        <div class="h-10 barcode-stripe rounded w-full mb-1"></div>
                        <div class="text-xs font-black tracking-widest text-slate-900">
                            (00) <span id="label-sscc">-</span>
                        </div>
                        <span class="text-[8px] text-slate-500 uppercase tracking-tighter">Serial Shipping Container Code (SSCC) — Primary Key Unit Logistik</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Live JSON Payload Inspector & Transaction Logs (5 cols) -->
        <div class="lg:col-span-5 space-y-6">
            
            <!-- JSON Payload Inspector Card -->
            <div class="glass-panel p-5 rounded-2xl border border-slate-800 flex flex-col h-[520px]">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-3">
                    <div class="flex items-center space-x-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-sky-400"></span>
                        <h3 class="text-xs font-bold text-white">REST API & JSON Payload Inspector</h3>
                    </div>
                    <button onclick="copyPayloadToClipboard()" class="text-[10px] text-slate-400 hover:text-white px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 transition-colors">
                        <i class="fa-solid fa-copy mr-1"></i> Salin JSON
                    </button>
                </div>

                <!-- Sub-Tabs: Request vs Response -->
                <div class="flex space-x-2 mb-3">
                    <button id="tabBtnRequest" onclick="switchPayloadTab('request')" class="flex-1 py-1.5 px-3 rounded-lg text-xs font-semibold bg-sky-600/30 text-sky-300 border border-sky-500/40 transition-all">
                        <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> HTTP Request
                    </button>
                    <button id="tabBtnResponse" onclick="switchPayloadTab('response')" class="flex-1 py-1.5 px-3 rounded-lg text-xs font-semibold bg-slate-900 text-slate-400 hover:text-slate-200 border border-slate-800 transition-all">
                        <i class="fa-solid fa-arrow-down-left-and-up-right-to-center mr-1"></i> System Response
                    </button>
                </div>

                <!-- Code Container with Scrollbar -->
                <div class="flex-grow overflow-auto code-container rounded-xl text-xs font-mono relative">
                    <pre id="jsonPayloadDisplay" class="text-sky-300 leading-relaxed text-[11px]">// Memuat data JSON payload...</pre>
                </div>

                <!-- Standard Compliance Indicator -->
                <div class="mt-3 pt-3 border-t border-slate-800/80 flex items-center justify-between text-[11px]">
                    <span class="text-slate-400">Standar Protokol:</span>
                    <span id="payloadStandardBadge" class="font-mono text-emerald-400 font-semibold bg-emerald-950/60 px-2 py-0.5 rounded border border-emerald-800/60 text-[10px]">
                        GS1 AIDC / JSON Schema
                    </span>
                </div>
            </div>

            <!-- Transaction Audit Trail Logs (Timeline) -->
            <div class="glass-panel p-5 rounded-2xl border border-slate-800">
                <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-800">
                    <h3 class="text-xs font-bold text-white flex items-center">
                        <i class="fa-solid fa-clock-rotate-left text-indigo-400 mr-2"></i>
                        Audit Trail Perpindahan Data (Database Log)
                    </h3>
                    <span id="logCountBadge" class="text-[10px] font-mono text-slate-400">0 Record</span>
                </div>

                <div id="auditTimelineContainer" class="space-y-2.5 max-h-60 overflow-y-auto pr-1">
                    <p class="text-slate-500 text-xs text-center py-4">Belum ada aktivitas terekam.</p>
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
