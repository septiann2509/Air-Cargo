<?php
// simulation_live.php
// Studio Simulasi Kargo Terpadu (All-in-One Live Input & Visual 3D Engine)
// Menggabungkan Form Kendali Kargo, Kinematika Presisi, & Visualisasi 3D Interaktif Realistis
require_once __DIR__ . '/config/database.php';
requireAuth();

$user = getCurrentUser();
$db = getDb();

$selectedCargoId = $_GET['cargo_id'] ?? '';
$initialCargo = null;
if ($selectedCargoId) {
    $cStmt = $db->prepare("
        SELECT c.*, t.truck_plate, t.driver_name, s.xray_result, w.actual_weight_kg
        FROM cargo_shipments c
        LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
        LEFT JOIN security_checks s ON s.cargo_id = c.id
        LEFT JOIN weight_records w ON w.cargo_id = c.id
        WHERE c.id = ?
    ");
    $cStmt->execute([$selectedCargoId]);
    $initialCargo = $cStmt->fetch();
}

// Jika belum ada cargo_id spesifik, ambil data kargo paling terkini dari database
if (!$initialCargo) {
    $cStmt = $db->query("
        SELECT c.*, t.truck_plate, t.driver_name, s.xray_result, w.actual_weight_kg
        FROM cargo_shipments c
        LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
        LEFT JOIN security_checks s ON s.cargo_id = c.id
        LEFT JOIN weight_records w ON w.cargo_id = c.id
        ORDER BY c.created_at DESC
        LIMIT 1
    ");
    $initialCargo = $cStmt->fetch();
}

// Fallback aman jika database belum memiliki entri kargo sama sekali
if (!$initialCargo) {
    $initialCargo = [
        'id' => 'CRG-DEMO',
        'awb_number' => '126-88492019',
        'description' => 'Vaksin & Peralatan Medis Biofarma',
        'commodity_type' => 'PHARMA',
        'quantity' => 48,
        'declared_weight_kg' => 350.0,
        'xray_result' => 'CLEARED',
        'truck_plate' => 'B 9482 KXA',
        'driver_name' => 'Suryadi Pratama'
    ];
}

// Override status X-Ray jika diteruskan via parameter URL (?xray=SUSPECT atau ?xray=CLEARED)
if (!empty($_GET['xray'])) {
    $initialCargo['xray_result'] = strtoupper($_GET['xray']);
}

$pageTitle = 'Simulasi Kargo Live — Studio 3D Interaktif';
$bodyClass = 'bg-[#08121E] text-white overflow-hidden';
include __DIR__ . '/includes/header.php';
?>

<!-- STYLE SHEET STUDIO SIMULASI LIVE -->
<style>
    body {
        background: #08121E !important;
        overflow: hidden;
    }
    main {
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Container Split-Screen */
    #studio-container {
        display: flex;
        height: calc(100vh - 64px);
        width: 100vw;
        overflow: hidden;
        position: relative;
    }

    /* Sidebar Panel Kiri & Toggle Button (Dihapus/Disembunyikan) */
    #sidebar-panel, #sidebar-toggle-btn {
        display: none !important;
    }

    /* Tombol Toggle Collapse Sidebar */
    #sidebar-toggle-btn {
        position: absolute;
        top: 20px;
        right: -36px;
        width: 36px;
        height: 40px;
        background: rgba(8, 18, 42, 0.95);
        border: 1px solid rgba(12, 161, 175, 0.45);
        border-left: none;
        border-radius: 0 12px 12px 0;
        color: #38BDF8;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 35;
        box-shadow: 6px 4px 16px rgba(0, 0, 0, 0.45);
        transition: all 0.2s;
    }
    #sidebar-toggle-btn:hover {
        background: linear-gradient(135deg, #0CA1AF, #087F8A);
        color: #fff;
        box-shadow: 0 0 15px rgba(12, 161, 175, 0.5);
    }

    /* Scrollable Area */
    .sidebar-scroll {
        flex: 1;
        overflow-y: auto;
        padding: 16px 18px;
        scrollbar-width: thin;
        scrollbar-color: #0CA1AF rgba(8, 18, 38, 0.5);
    }
    .sidebar-scroll::-webkit-scrollbar { width: 5px; }
    .sidebar-scroll::-webkit-scrollbar-thumb {
        background: #0CA1AF;
        border-radius: 99px;
    }

    /* Panel Kanan (3D Viewport) */
    #viewport-container {
        flex: 1;
        height: 100%;
        position: relative;
        background: #08121E;
        overflow: hidden;
    }
    #three-canvas {
        width: 100%;
        height: 100%;
        display: block;
        cursor: grab;
    }
    #three-canvas:active { cursor: grabbing; }

    /* Glass Cards */
    .card-glass {
        background: rgba(12, 25, 58, 0.82);
        border: 1px solid rgba(12, 161, 175, 0.28);
        border-radius: 14px;
        padding: 13px 14px;
        margin-bottom: 11px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
        position: relative;
        overflow: hidden;
    }
    .card-glass::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, #0CA1AF 0%, #04AFBF 45%, transparent 100%);
        opacity: 0.85;
    }

    /* Input Fields */
    .in-label {
        font-size: 10.5px;
        font-weight: 800;
        color: #93C5FD;
        display: block;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .in-field {
        width: 100%;
        background: rgba(7, 16, 36, 0.9);
        border: 1px solid rgba(12, 161, 175, 0.4);
        border-radius: 10px;
        color: #FFFFFF;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 600;
        outline: none;
        transition: all 0.2s ease;
    }
    .in-field:focus {
        border-color: #04AFBF;
        box-shadow: 0 0 10px rgba(4, 175, 191, 0.45);
        background: rgba(6, 14, 32, 0.98);
    }

    /* Radio Tiles */
    .radio-tile {
        cursor: pointer;
        border: 1.5px solid rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        padding: 8px 10px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        font-weight: 700;
        transition: all 0.2s;
        background: rgba(7, 16, 36, 0.7);
    }
    .radio-tile:hover {
        border-color: #0CA1AF;
        background: rgba(12, 161, 175, 0.15);
    }
    .radio-tile.selected-cleared {
        border-color: #10B981;
        background: rgba(16, 185, 129, 0.22);
        color: #34D399;
        box-shadow: 0 0 12px rgba(16, 185, 129, 0.35);
    }
    .radio-tile.selected-suspect {
        border-color: #EF4444;
        background: rgba(239, 68, 68, 0.26);
        color: #F87171;
        box-shadow: 0 0 14px rgba(239, 68, 68, 0.45);
    }

    /* Preset Buttons */
    .btn-preset {
        padding: 8px 9px;
        border-radius: 10px;
        font-size: 10.5px;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: rgba(255, 255, 255, 0.05);
        position: relative;
    }
    .btn-preset:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.35);
    }

    /* Stage Stepper */
    .step-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        position: relative;
        padding-bottom: 12px;
    }
    .step-item:last-child { padding-bottom: 0; }
    .step-item:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 13px;
        top: 26px;
        bottom: 2px;
        width: 2px;
        background: rgba(255, 255, 255, 0.12);
    }
    .step-item.active-step:not(:last-child)::after {
        background: linear-gradient(to bottom, #0CA1AF, rgba(255, 255, 255, 0.12));
    }
    .step-item.done-step:not(:last-child)::after {
        background: #10B981;
    }

    .step-circle {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        border: 2px solid rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10.5px;
        font-weight: 800;
        color: #94A3B8;
        flex-shrink: 0;
        transition: all 0.3s;
        z-index: 2;
    }
    .step-item.active-step .step-circle {
        background: linear-gradient(135deg, #0CA1AF 0%, #087F8A 100%);
        border-color: #04AFBF;
        color: #FFFFFF;
        box-shadow: 0 0 14px rgba(4, 175, 191, 0.65);
        animation: pulseStep 1.5s infinite;
    }
    .step-item.done-step .step-circle {
        background: #10B981;
        border-color: #34D399;
        color: #FFFFFF;
    }
    .step-item.alert-step .step-circle {
        background: #EF4444;
        border-color: #F87171;
        color: #FFFFFF;
        animation: pulseAlert 1s infinite;
    }
    @keyframes pulseStep {
        0%, 100% { transform: scale(1); box-shadow: 0 0 10px rgba(4, 175, 191, 0.5); }
        50% { transform: scale(1.1); box-shadow: 0 0 18px rgba(4, 175, 191, 0.9); }
    }
    @keyframes pulseAlert {
        0%, 100% { transform: scale(1); box-shadow: 0 0 10px rgba(239, 68, 68, 0.5); }
        50% { transform: scale(1.12); box-shadow: 0 0 20px rgba(239, 68, 68, 0.9); }
    }

    /* Floating Top HUD */
    .hud-chip {
        background: rgba(8, 18, 42, 0.92);
        border: 1px solid rgba(12, 161, 175, 0.35);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-radius: 12px;
        padding: 6px 14px;
        font-size: 11px;
        font-weight: 700;
        color: #FFFFFF;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
    }

    /* Stage Quick-Nav Chips */
    .stage-pill {
        padding: 5px 11px;
        border-radius: 99px;
        background: rgba(13, 28, 66, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.14);
        color: rgba(255, 255, 255, 0.85);
        font-size: 10.5px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    .stage-pill:hover {
        background: rgba(12, 161, 175, 0.3);
        border-color: #0CA1AF;
        color: #fff;
    }
    .stage-pill.active {
        background: linear-gradient(135deg, #0CA1AF 0%, #087F8A 100%);
        border-color: #04AFBF;
        color: #fff;
        box-shadow: 0 0 12px rgba(12, 161, 175, 0.5);
    }

    /* Camera Switch Buttons */
    .cam-btn {
        padding: 6px 10px;
        border-radius: 9px;
        background: rgba(13, 28, 66, 0.88);
        border: 1px solid rgba(255, 255, 255, 0.16);
        color: #E2E8F0;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .cam-btn:hover {
        background: #0CA1AF;
        border-color: #04AFBF;
        color: #fff;
    }
    .cam-btn.active {
        background: linear-gradient(135deg, #0CA1AF 0%, #087F8A 100%);
        border-color: #04AFBF;
        color: #fff;
        box-shadow: 0 0 10px rgba(12, 161, 175, 0.5);
    }

    /* Milestone Notification Toast */
    #milestone-banner {
        position: absolute;
        top: 68px;
        left: 50%;
        transform: translateX(-50%) translateY(-20px);
        opacity: 0;
        pointer-events: none;
        transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        z-index: 50;
    }
    #milestone-banner.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
</style>

<div id="studio-container">

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- PANEL KIRI: FORM INPUT KARGO & KONTROL SIMULASI INTERAKTIF             -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div id="sidebar-panel" style="display: none !important;">

        <!-- Header Panel -->
        <!-- Header Panel -->
        <div class="p-3.5 border-b border-teal-500/25 bg-gradient-to-r from-teal-950/70 to-navy-950/80 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-xl bg-gradient-to-br from-[#0CA1AF] to-[#014D54] flex items-center justify-center text-white shadow-md shadow-teal-900/40 border border-teal-400/40">
                    <i class="fa-solid fa-gamepad text-xs"></i>
                </div>
                <div>
                    <h2 class="text-xs font-black text-white tracking-tight flex items-center gap-1.5">
                        <span>STUDIO SIMULASI LIVE</span>
                    </h2>
                    <p class="text-[9.5px] text-teal-200/80">Visualisasi 3D Real-Time</p>
                </div>
            </div>

            <div class="flex items-center gap-1.5">
                <a href="cargo_input.php" class="px-2 py-1 rounded-lg bg-teal-500/20 hover:bg-teal-500/30 text-teal-300 hover:text-white border border-teal-500/40 text-[10px] font-bold flex items-center gap-1 transition-colors" title="Input Kargo Baru">
                    <i class="fa-solid fa-plus text-[9px]"></i>
                    <span>Input Kargo</span>
                </a>
                <button onclick="toggleAudio()" id="btn-audio" class="w-6 h-6 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-xs transition-colors" title="Toggle Efek Audio">
                    <i class="fa-solid fa-volume-high text-teal-300 text-[10px]"></i>
                </button>
            </div>
        </div>

        <!-- Scrollable Body Content -->
        <div class="sidebar-scroll">

            <!-- STEPPER 6 TAHAPAN OPERASIONAL LAPANGAN -->
            <div class="card-glass">
                <div class="flex items-center justify-between mb-3 border-b border-white/10 pb-2">
                    <span class="text-xs font-black text-white flex items-center gap-1.5">
                        <i class="fa-solid fa-list-check text-teal-400"></i>
                        <span>PROGRES ALUR TERMINAL</span>
                    </span>
                    <span id="sim-progress-pct" class="text-[10px] font-mono text-teal-300 font-bold">0%</span>
                </div>

                <div class="space-y-1 text-xs">
                    <div class="step-item" id="step-ui-1">
                        <div class="step-circle" id="circle-1">1</div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-white flex items-center justify-between">
                                <span>Truk Mundur ke Dermaga</span>
                                <span class="step-status text-[9px] text-white/40 font-mono">STANDBY</span>
                            </div>
                            <p class="text-[10px] text-white/60 truncate">Truk mundur merapat ke Dermaga Bay 2</p>
                        </div>
                    </div>

                    <div class="step-item" id="step-ui-2">
                        <div class="step-circle" id="circle-2">2</div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-white flex items-center justify-between">
                                <span>Docking Bay 2 &amp; Scan RFID</span>
                                <span class="step-status text-[9px] text-white/40 font-mono">PENDING</span>
                            </div>
                            <p class="text-[10px] text-white/60 truncate">Merapat di dok, validasi RFID &amp; buka pintu</p>
                        </div>
                    </div>

                    <div class="step-item" id="step-ui-3">
                        <div class="step-circle" id="circle-3">3</div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-white flex items-center justify-between">
                                <span>Bongkar Kargo &amp; X-Ray AVSEC</span>
                                <span class="step-status text-[9px] text-white/40 font-mono">PENDING</span>
                            </div>
                            <p class="text-[10px] text-white/60 truncate">Kargo meluncur ke feeder &amp; discan X-Ray</p>
                        </div>
                    </div>

                    <div class="step-item" id="step-ui-4">
                        <div class="step-circle" id="circle-4">4</div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-white flex items-center justify-between">
                                <span id="label-step-4">Karantina / Storage</span>
                                <span class="step-status text-[9px] text-white/40 font-mono">PENDING</span>
                            </div>
                            <p class="text-[10px] text-white/60 truncate" id="desc-step-4">Diverter arm / Central Storage ULD</p>
                        </div>
                    </div>

                    <div class="step-item" id="step-ui-5">
                        <div class="step-circle" id="circle-5">5</div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-white flex items-center justify-between">
                                <span>Timbangan ULD Lantai 15T</span>
                                <span class="step-status text-[9px] text-white/40 font-mono">PENDING</span>
                            </div>
                            <p class="text-[10px] text-white/60 truncate" id="desc-step-5">Uji batas berat e-AWB vs toleransi</p>
                        </div>
                    </div>

                    <div class="step-item" id="step-ui-6">
                        <div class="step-circle" id="circle-6">6</div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-white flex items-center justify-between">
                                <span>Pemuatan ke Boeing 777F</span>
                                <span class="step-status text-[9px] text-white/40 font-mono">PENDING</span>
                            </div>
                            <p class="text-[10px] text-white/60 truncate">High-Loader elevasi 3.85m ke palka</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AUDIT LOG FEED REAL-TIME -->
            <div class="card-glass">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-black text-white flex items-center gap-1.5">
                        <i class="fa-solid fa-terminal text-teal-400"></i>
                        <span>LOG OPERASIONAL SISTEM</span>
                    </span>
                    <button onclick="clearSimLogs()" class="text-[9px] text-teal-300 hover:text-white transition-colors">Hapus</button>
                </div>
                <div id="sim-log-feed" class="h-28 overflow-y-auto space-y-1.5 font-mono text-[10px] pr-1" style="scrollbar-width: thin;">
                    <div class="text-teal-400/80">[SYSTEM READY] Engine Simulasi 3D siap menerima input kargo.</div>
                </div>
            </div>

        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- PANEL KANAN: 3D VIEWPORT CANVAS & HUD OVERLAYS                         -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div id="viewport-container">
        
        <!-- 3D Canvas WebGL -->
        <canvas id="three-canvas"></canvas>

        <!-- FLOATING TOP BAR 1: TAHAPAN QUICK-NAV BAR (INTERAKTIF KLIK LANGSUNG) -->
        <div class="absolute top-3 left-4 z-20 flex items-center gap-1.5 p-1 rounded-2xl bg-slate-900/90 backdrop-blur-md border border-teal-500/30 shadow-2xl max-w-[calc(100vw-500px)] overflow-x-auto" style="scrollbar-width: none;">
            <button class="stage-pill active" id="spill-1" onclick="jumpToProcessStep(1)">
                <i class="fa-solid fa-truck text-xs text-blue-400"></i>
                <span>1. Truk</span>
            </button>
            <button class="stage-pill" id="spill-2" onclick="jumpToProcessStep(2)">
                <i class="fa-solid fa-wifi text-xs text-teal-300"></i>
                <span>2. RFID</span>
            </button>
            <button class="stage-pill" id="spill-3" onclick="jumpToProcessStep(3)">
                <i class="fa-solid fa-shield-halved text-xs text-amber-400"></i>
                <span>3. X-Ray</span>
            </button>
            <button class="stage-pill" id="spill-4" onclick="jumpToProcessStep(4)">
                <i class="fa-solid fa-triangle-exclamation text-xs text-rose-400"></i>
                <span>3B/4. Karantina / Storage</span>
            </button>
            <button class="stage-pill" id="spill-5" onclick="jumpToProcessStep(5)">
                <i class="fa-solid fa-scale-balanced text-xs text-purple-400"></i>
                <span>5. Timbangan</span>
            </button>
            <button class="stage-pill" id="spill-6" onclick="jumpToProcessStep(6)">
                <i class="fa-solid fa-plane-departure text-xs text-sky-400"></i>
                <span>6. Boeing 777F</span>
            </button>
        </div>

        <!-- FLOATING TOP BAR 2: CAMERA PRESETS & FULLSCREEN (POJOK KANAN ATAS) -->
        <div class="absolute top-3 right-4 z-20 flex items-center gap-1 p-1 rounded-xl bg-slate-900/90 backdrop-blur-md border border-teal-500/30 shadow-2xl">
            <a href="cargo_input.php" class="cam-btn text-teal-300 hover:text-white" title="Input Kargo Baru">
                <i class="fa-solid fa-plus text-xs"></i>
                <span class="hidden md:inline">Input Kargo</span>
            </a>
            <button onclick="toggleAudio()" id="btn-audio" class="cam-btn text-white" title="Toggle Efek Audio">
                <i class="fa-solid fa-volume-high text-teal-300 text-xs"></i>
            </button>
            <div class="h-4 w-px bg-white/20 mx-0.5"></div>
            <button onclick="setCameraView('follow')" id="cam-follow" class="cam-btn active" title="Kamera Otomatis Mengikuti Kargo">
                <i class="fa-solid fa-video text-teal-300 text-xs"></i>
                <span class="hidden xl:inline">Ikuti Kargo</span>
            </button>
            <button onclick="setCameraView('iso')" id="cam-iso" class="cam-btn" title="Tampak 3D Isometrik Keseluruhan">
                <i class="fa-solid fa-cube text-xs"></i>
                <span class="hidden xl:inline">Isometrik</span>
            </button>
            <button onclick="setCameraView('dock')" id="cam-dock" class="cam-btn" title="Fokus Area Truk &amp; RFID">
                <i class="fa-solid fa-truck text-xs"></i>
                <span class="hidden 2xl:inline">Truk &amp; Gate</span>
            </button>
            <button onclick="setCameraView('xray')" id="cam-xray" class="cam-btn" title="Fokus X-Ray AVSEC &amp; Karantina">
                <i class="fa-solid fa-shield-halved text-xs"></i>
                <span class="hidden 2xl:inline">X-Ray &amp; Karantina</span>
            </button>
            <button onclick="setCameraView('scale')" id="cam-scale" class="cam-btn" title="Fokus Timbangan ULD">
                <i class="fa-solid fa-scale-balanced text-xs"></i>
                <span class="hidden 2xl:inline">Timbangan</span>
            </button>
            <button onclick="setCameraView('plane')" id="cam-plane" class="cam-btn" title="Fokus High-Loader &amp; Boeing 777F">
                <i class="fa-solid fa-plane-departure text-xs"></i>
                <span class="hidden 2xl:inline">Boeing 777F</span>
            </button>
            <button onclick="resetCameraAngle()" class="cam-btn" title="Reset Sudut Kamera">
                <i class="fa-solid fa-house text-slate-300 text-xs"></i>
            </button>
            <button onclick="toggleVisualLabels()" id="btn-labels-toggle" class="cam-btn text-teal-300 hover:text-white active" title="Tampilkan/Sembunyikan Marka Lantai &amp; Label Arsitektur">
                <i class="fa-solid fa-tags text-xs"></i>
                <span class="hidden 2xl:inline">Marka 3D</span>
            </button>
            <button onclick="toggleFullscreen3D()" id="btn-fullscreen-toggle" class="cam-btn text-amber-300 hover:text-white" title="Layar Penuh (Fullscreen 3D)">
                <i class="fa-solid fa-expand text-xs"></i>
            </button>
        </div>

        <!-- FLOATING PAN & ZOOM D-PAD CONTROLLER (NAVIGASI PRESISI) -->
        <div class="absolute top-16 right-4 z-20 flex flex-col items-center gap-1 p-1.5 rounded-xl bg-slate-900/90 backdrop-blur-md border border-teal-500/30 shadow-2xl text-white">
            <div class="text-[8px] font-black text-teal-300 uppercase tracking-wider mb-0.5 flex items-center gap-1">
                <i class="fa-solid fa-compass text-teal-400 text-[8.5px]"></i>
                <span>Geser &amp; Zoom</span>
            </div>
            <!-- D-pad Pan 2D -->
            <div class="grid grid-cols-3 gap-1">
                <div></div>
                <button onclick="panCamera(0, 8)" class="w-7 h-7 rounded bg-white/10 hover:bg-[#0CA1AF] text-slate-200 hover:text-white flex items-center justify-center text-xs transition-all active:scale-95 cursor-pointer" title="Geser Maju (Arrow Up)"><i class="fa-solid fa-chevron-up"></i></button>
                <div></div>
                <button onclick="panCamera(8, 0)" class="w-7 h-7 rounded bg-white/10 hover:bg-[#0CA1AF] text-slate-200 hover:text-white flex items-center justify-center text-xs transition-all active:scale-95 cursor-pointer" title="Geser Kiri (Arrow Left)"><i class="fa-solid fa-chevron-left"></i></button>
                <button onclick="resetCameraAngle()" class="w-7 h-7 rounded bg-teal-500/25 hover:bg-[#0CA1AF] text-teal-300 hover:text-white flex items-center justify-center text-[10px] transition-all active:scale-95 cursor-pointer" title="Reset Posisi Awal"><i class="fa-solid fa-arrows-to-dot"></i></button>
                <button onclick="panCamera(-8, 0)" class="w-7 h-7 rounded bg-white/10 hover:bg-[#0CA1AF] text-slate-200 hover:text-white flex items-center justify-center text-xs transition-all active:scale-95 cursor-pointer" title="Geser Kanan (Arrow Right)"><i class="fa-solid fa-chevron-right"></i></button>
                <div></div>
                <button onclick="panCamera(0, -8)" class="w-7 h-7 rounded bg-white/10 hover:bg-[#0CA1AF] text-slate-200 hover:text-white flex items-center justify-center text-xs transition-all active:scale-95 cursor-pointer" title="Geser Mundur (Arrow Down)"><i class="fa-solid fa-chevron-down"></i></button>
                <div></div>
            </div>
            <div class="w-full h-px bg-white/15 my-0.5"></div>
            <!-- Zoom In & Out -->
            <div class="flex items-center gap-1 w-full justify-between">
                <button onclick="zoomCamera(6)" class="flex-1 py-1 rounded bg-white/10 hover:bg-[#0CA1AF] text-slate-200 hover:text-white flex items-center justify-center text-xs font-bold transition-all active:scale-95 cursor-pointer" title="Zoom Dekat (+)"><i class="fa-solid fa-plus text-[10px]"></i></button>
                <button onclick="zoomCamera(-6)" class="flex-1 py-1 rounded bg-white/10 hover:bg-[#0CA1AF] text-slate-200 hover:text-white flex items-center justify-center text-xs font-bold transition-all active:scale-95 cursor-pointer" title="Zoom Jauh (-)"><i class="fa-solid fa-minus text-[10px]"></i></button>
            </div>
        </div>

        <!-- FLOATING MOUSE CONTROLS HINT -->
        <div id="mouse-hint-pill" class="absolute bottom-20 left-1/2 -translate-x-1/2 z-20 hidden md:flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-slate-900/85 backdrop-blur-md border border-teal-500/35 text-[10.5px] text-slate-200 shadow-2xl pointer-events-auto transition-opacity">
            <i class="fa-solid fa-computer-mouse text-teal-400"></i>
            <span><strong class="text-white">Klik Kiri:</strong> Putar 360°</span>
            <span class="text-teal-400/50">•</span>
            <span><strong class="text-white">Klik Kanan / Shift+Klik:</strong> Geser (Pan)</span>
            <span class="text-teal-400/50">•</span>
            <span><strong class="text-white">Scroll:</strong> Zoom (2m - 650m)</span>
            <button onclick="document.getElementById('mouse-hint-pill').remove()" class="text-slate-400 hover:text-white ml-1 text-xs cursor-pointer" title="Tutup panduan">&times;</button>
        </div>

        <!-- FIDS FLIGHT & CARGO GLASS BILLBOARD (DI BAWAH NAV BAR ATAS) -->
        <div class="absolute top-14 left-4 z-20 flex flex-col gap-1 pointer-events-none">
            <div class="hud-chip flex items-center gap-3 bg-slate-900/90 border border-teal-500/40">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse" id="hud-pulse-dot"></span>
                <div>
                    <div class="text-[9px] text-teal-300 uppercase font-black tracking-wider flex items-center gap-1.5" id="hud-stage-name">
                        <span>TAHAP 1: KEDATANGAN TRUK</span>
                        <span class="text-[8px] px-1 py-0.2 rounded bg-teal-500/30 text-teal-200">INBOUND</span>
                    </div>
                    <div class="text-xs font-black text-white" id="hud-cargo-title">Menunggu instruksi jalankan simulasi...</div>
                </div>
                <div class="pl-3 border-l border-white/20 text-right">
                    <div class="text-[9px] text-slate-400">FLIGHT / AWB</div>
                    <div class="text-[11px] font-mono text-amber-300 font-extrabold" id="hud-awb-code">GA-777F &bull; 126-88492019</div>
                </div>
            </div>
        </div>

        <!-- FLOATING MILESTONE BANNER TOAST -->
        <div id="milestone-banner" class="hud-chip !px-6 !py-3 bg-gradient-to-r from-teal-950 via-slate-900 to-teal-950 border-2 border-teal-400 shadow-2xl flex items-center gap-3">
            <div id="milestone-icon" class="w-8 h-8 rounded-full bg-teal-500/20 text-teal-300 flex items-center justify-center text-sm font-black border border-teal-400">
                <i class="fa-solid fa-bell"></i>
            </div>
            <div>
                <div id="milestone-title" class="text-xs font-black text-white">CHECKPOINT TERCAPAI</div>
                <div id="milestone-desc" class="text-[11px] text-teal-200">Keterangan checkpoint tampil di sini.</div>
            </div>
        </div>

        <!-- FLOATING TIMELINE SCRUBBER & PLAYBACK CONTROLLER -->
        <div class="absolute bottom-4 left-4 z-20 flex items-center gap-2 p-2 rounded-2xl bg-slate-900/90 backdrop-blur-md border border-teal-500/35 shadow-2xl max-w-lg w-[calc(100%-32px)] sm:w-auto">
            <button onclick="togglePauseSimulation()" id="scrub-play-btn" class="w-8 h-8 rounded-xl bg-gradient-to-r from-[#0CA1AF] to-[#087F8A] hover:from-[#04AFBF] hover:to-[#0CA1AF] text-white flex items-center justify-center text-xs shadow-md shadow-teal-900/40 hover:scale-105 active:scale-95 transition-all cursor-pointer" title="Play / Pause Simulasi">
                <i class="fa-solid fa-play text-[11px]" id="scrub-play-icon"></i>
            </button>
            <button onclick="resetLiveSimulation()" class="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-xs transition-colors cursor-pointer" title="Reset / Ulangi Simulasi">
                <i class="fa-solid fa-rotate-left text-[11px] text-amber-300"></i>
            </button>
            <div class="flex-1 min-w-[170px] flex flex-col justify-center gap-1">
                <div class="flex items-center justify-between text-[9.5px] font-mono">
                    <span id="scrub-stage-label" class="text-teal-300 font-sans font-bold truncate">Tahap 1: Inbound Truk</span>
                    <span id="scrub-pct-label" class="text-amber-300 font-bold ml-2">0%</span>
                </div>
                <div class="relative w-full h-2 bg-slate-800 rounded-full cursor-pointer overflow-hidden border border-white/10" onclick="onTimelineScrubClick(event, this)" title="Klik untuk melompat alur proses">
                    <div id="timeline-progress-fill" class="h-full bg-gradient-to-r from-teal-500 via-teal-400 to-amber-400 rounded-full transition-all duration-150" style="width: 0%;"></div>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button onclick="jumpToProcessStep(Math.max(1, (simPhase || 1) - 1))" class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-xs transition-colors cursor-pointer" title="Tahap Sebelumnya">
                    <i class="fa-solid fa-step-backward text-[10px]"></i>
                </button>
                <button onclick="jumpToProcessStep(Math.min(6, (simPhase || 0) + 1))" class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-xs transition-colors cursor-pointer" title="Tahap Selanjutnya">
                    <i class="fa-solid fa-step-forward text-[10px]"></i>
                </button>
            </div>
            <div class="flex items-center bg-white/10 rounded-lg p-0.5 border border-white/10 ml-0.5">
                <button type="button" onclick="setSpeed(0.5)" id="scrub-spd-05" class="px-1.5 py-0.5 rounded text-[9px] font-extrabold text-white/70 hover:text-white" title="0.5x Mode Detail">0.5x</button>
                <button type="button" onclick="setSpeed(1)" id="scrub-spd-1" class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-[#0CA1AF] text-white shadow" title="1x Realistis">1x</button>
                <button type="button" onclick="setSpeed(2)" id="scrub-spd-2" class="px-1.5 py-0.5 rounded text-[9px] font-extrabold text-white/70 hover:text-white" title="2x Cepat">2x</button>
                <button type="button" onclick="setSpeed(4)" id="scrub-spd-4" class="px-1.5 py-0.5 rounded text-[9px] font-extrabold text-white/70 hover:text-white" title="4x Kilat">4x</button>
            </div>
        </div>

        <!-- BOTTOM RIGHT TELEMETRY READOUT -->
        <div class="absolute bottom-4 right-4 z-20 flex items-center gap-2 pointer-events-none">
            <div class="hud-chip flex items-center gap-3.5 text-xs bg-slate-900/95 border border-teal-500/40 shadow-[0_8px_32px_rgba(0,0,0,0.65)] backdrop-blur-xl">
                <div class="px-1 text-center">
                    <span class="text-[8.5px] text-teal-300 block font-black uppercase tracking-wider">AVSEC X-RAY</span>
                    <span id="telemetry-xray" class="font-black text-emerald-400 font-mono text-xs">CLEARED</span>
                </div>
                <div class="h-6 w-px bg-teal-500/30"></div>
                <div class="px-1 text-center">
                    <span class="text-[8.5px] text-teal-300 block font-black uppercase tracking-wider">SCALE 15T</span>
                    <span id="telemetry-scale" class="font-black text-amber-300 font-mono text-xs">0 kg</span>
                </div>
                <div class="h-6 w-px bg-teal-500/30"></div>
                <div class="px-1 text-center">
                    <span class="text-[8.5px] text-teal-300 block font-black uppercase tracking-wider">ELEVASI LIFT</span>
                    <span id="telemetry-elevator" class="font-black text-sky-400 font-mono text-xs">0.55 m</span>
                </div>
                <div class="h-6 w-px bg-teal-500/30"></div>
                <div class="px-1 text-center">
                    <span class="text-[8.5px] text-teal-300 block font-black uppercase tracking-wider">PINTU PALKA</span>
                    <span id="telemetry-door" class="font-black text-slate-300 font-mono text-xs">STANDBY</span>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Three.js Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>

<script>
// ─────────────────────────────────────────────────────────────────────────────
// AUDIO SYNTHESIZER ENGINE (Web Audio API — Zero Dependencies)
// ─────────────────────────────────────────────────────────────────────────────
let audioCtx = null;
let isAudioEnabled = true;

function initAudio() {
    if (!audioCtx) {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        audioCtx = new AudioContext();
    }
    if (audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
}

function playSound(type) {
    if (!isAudioEnabled) return;
    try {
        initAudio();
        const now = audioCtx.currentTime;

        if (type === 'beep') {
            // Scanner RFID Beep
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(1860, now);
            gain.gain.setValueAtTime(0.14, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.14);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.14);
        } else if (type === 'pass') {
            // Success / Clear Chime (Double bell)
            [1046.5, 1318.5].forEach((freq, idx) => {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, now + idx * 0.1);
                gain.gain.setValueAtTime(0.18, now + idx * 0.1);
                gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.1 + 0.35);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start(now + idx * 0.1);
                osc.stop(now + idx * 0.1 + 0.35);
            });
        } else if (type === 'alert') {
            // Warning / Hazard Siren
            [620, 880, 620, 880].forEach((freq, idx) => {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(freq, now + idx * 0.12);
                gain.gain.setValueAtTime(0.18, now + idx * 0.12);
                gain.gain.exponentialRampToValueAtTime(0.01, now + idx * 0.12 + 0.11);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start(now + idx * 0.12);
                osc.stop(now + idx * 0.12 + 0.11);
            });
        } else if (type === 'hydraulic') {
            // Low hum of hydraulic lift
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(80, now);
            osc.frequency.linearRampToValueAtTime(140, now + 1.2);
            gain.gain.setValueAtTime(0.14, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 1.2);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(now);
            osc.stop(now + 1.2);
        }
    } catch (e) {
        console.warn('Audio FX note:', e);
    }
}

function toggleAudio() {
    isAudioEnabled = !isAudioEnabled;
    const btn = document.getElementById('btn-audio');
    if (isAudioEnabled) {
        btn.innerHTML = '<i class="fa-solid fa-volume-high text-teal-300"></i>';
        playSound('beep');
    } else {
        btn.innerHTML = '<i class="fa-solid fa-volume-xmark text-slate-400"></i>';
    }
}

function toggleSidebar() {
    const sb = document.getElementById('sidebar-panel');
    const icon = document.getElementById('sidebar-toggle-icon');
    sb.classList.toggle('collapsed');
    if (sb.classList.contains('collapsed')) {
        icon.className = 'fa-solid fa-chevron-right';
    } else {
        icon.className = 'fa-solid fa-chevron-left';
    }
    setTimeout(() => {
        camera.aspect = container.clientWidth / container.clientHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(container.clientWidth, container.clientHeight);
    }, 360);
}

function toggleFullscreen3D() {
    const vp = document.getElementById('viewport-container');
    if (!document.fullscreenElement) {
        if (vp.requestFullscreen) {
            vp.requestFullscreen();
        } else if (vp.webkitRequestFullscreen) {
            vp.webkitRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}
document.addEventListener('fullscreenchange', () => {
    const btn = document.getElementById('btn-fullscreen-toggle');
    if (btn) {
        if (document.fullscreenElement) {
            btn.innerHTML = '<i class="fa-solid fa-compress text-xs"></i>';
            btn.title = "Keluar dari Layar Penuh";
        } else {
            btn.innerHTML = '<i class="fa-solid fa-expand text-xs"></i>';
            btn.title = "Layar Penuh (Fullscreen 3D)";
        }
    }
    setTimeout(() => {
        camera.aspect = container.clientWidth / container.clientHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(container.clientWidth, container.clientHeight);
    }, 150);
});

// ─────────────────────────────────────────────────────────────────────────────
// STATE & PRESETS KENDALI SIMULASI
// ─────────────────────────────────────────────────────────────────────────────
let simSpeed = 1.0;
let isSimPaused = false;
let simActive = false;
let simPhase = 0; // 0=idle, 1=truck, 2=rfid, 3=xray, 4=branch, 5=scale, 6=loading
let simProgress = 0;
let simCameraMode = 'follow';

const activeCargoData = {
    name: <?= json_encode($initialCargo['description'] ?? 'Vaksin & Peralatan Medis Biofarma') ?>,
    commodity: <?= json_encode($initialCargo['commodity_type'] ?? 'PHARMA') ?>,
    qty: <?= (int)($initialCargo['quantity'] ?? 48) ?>,
    weight: <?= (float)($initialCargo['declared_weight_kg'] ?? 350.0) ?>,
    maxWeight: 1588.0,
    xrayResult: <?= json_encode($initialCargo['xray_result'] ?? 'CLEARED') ?>,
    truckPlate: <?= json_encode($initialCargo['truck_plate'] ?? 'B 9482 KXA') ?>,
    driver: <?= json_encode($initialCargo['driver_name'] ?? 'Suryadi Pratama') ?>,
    awb: <?= json_encode($initialCargo['awb_number'] ?? '126-88492019') ?>
};

function onSelectDbCargo(selectEl) {
    if (!selectEl || !selectEl.value) return;
    try {
        const item = JSON.parse(selectEl.value);
        activeCargoData.name = item.description || '';
        activeCargoData.commodity = item.commodity_type || 'GENERAL_CARGO';
        activeCargoData.qty = item.quantity || 40;
        activeCargoData.weight = item.declared_weight_kg || 350;
        activeCargoData.truckPlate = item.truck_plate || 'B 9482 KXA';
        activeCargoData.driver = item.driver_name || 'Suryadi Pratama';
        activeCargoData.awb = item.awb_number || '126-88492019';
        setXrayChoice(item.xray_result === 'SUSPECT' ? 'SUSPECT' : 'CLEARED');
        addSimLog(`📥 [LOAD DB] Memuat data kargo ${item.awb_number}: "${item.description}" (${item.declared_weight_kg} kg).`);
    } catch(e) { console.error(e); }
}

function setXrayChoice(choice) {
    activeCargoData.xrayResult = choice;
    const tileC = document.getElementById('tile-cleared');
    const tileS = document.getElementById('tile-suspect');
    if (tileC && tileS) {
        if (choice === 'CLEARED') {
            tileC.className = 'radio-tile selected-cleared';
            tileS.className = 'radio-tile';
        } else {
            tileC.className = 'radio-tile';
            tileS.className = 'radio-tile selected-suspect';
        }
    }
    const telem = document.getElementById('telemetry-xray');
    if (telem) {
        telem.textContent = choice;
        telem.className = choice === 'CLEARED' ? 'font-black text-emerald-400 font-mono text-xs' : 'font-black text-rose-400 font-mono text-xs';
    }
}

function loadScenarioPreset(type) {
    if (type === 'normal') {
        activeCargoData.name = 'Garmen & Tekstil Ekspor Premium';
        activeCargoData.commodity = 'GENERAL_CARGO';
        activeCargoData.qty = 60;
        activeCargoData.weight = 350;
        activeCargoData.maxWeight = 1588;
        activeCargoData.truckPlate = 'B 9102 SXZ';
        activeCargoData.driver = 'Budi Santoso';
        setXrayChoice('CLEARED');
        addSimLog('⚡ [PRESET] Dimuat: Skenario 1 - Barang Aman, lolos hingga Boeing 777F.');
    } else if (type === 'suspect') {
        activeCargoData.name = 'Baterai Lithium & Aerosol Kimia DG';
        activeCargoData.commodity = 'DANGEROUS_GOODS';
        activeCargoData.qty = 24;
        activeCargoData.weight = 480;
        activeCargoData.maxWeight = 1588;
        activeCargoData.truckPlate = 'B 9941 DGX';
        activeCargoData.driver = 'Hendrawan';
        setXrayChoice('SUSPECT');
        addSimLog('⚡ [PRESET] Dimuat: Skenario 2 - Barang Berbahaya (DG) → Masuk Karantina.');
    } else if (type === 'overweight') {
        activeCargoData.name = 'Suku Cadang Kompresor Turbin Berat';
        activeCargoData.commodity = 'GENERAL_CARGO';
        activeCargoData.qty = 8;
        activeCargoData.weight = 2200;
        activeCargoData.maxWeight = 1588;
        activeCargoData.truckPlate = 'B 9044 HVY';
        activeCargoData.driver = 'Agus Suprayogi';
        setXrayChoice('CLEARED');
        addSimLog('⚡ [PRESET] Dimuat: Skenario 3 - Kelebihan Beban (2,200 kg) → Ditolak di Timbangan.');
    }
}

function setSpeed(spd) {
    simSpeed = spd;
    const speedItems = [
        { id: 'scrub-spd-05', val: 0.5 }, { id: 'scrub-spd-1', val: 1 }, { id: 'scrub-spd-2', val: 2 }, { id: 'scrub-spd-4', val: 4 }
    ];
    speedItems.forEach(item => {
        const el = document.getElementById(item.id);
        if (!el) return;
        const isMatch = Math.abs(item.val - spd) < 0.05;
        el.className = isMatch 
            ? 'px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-[#0CA1AF] text-white shadow'
            : 'px-1.5 py-0.5 rounded text-[9px] font-extrabold text-white/70 hover:text-white';
    });
    addSimLog(`⏩ Kecepatan simulasi: ${spd}x.`);
}

function togglePauseSimulation() {
    if (!simActive && simPhase === 0) {
        startLiveSimulation();
        return;
    }
    isSimPaused = !isSimPaused;
    const playIcon = document.getElementById('scrub-play-icon');
    if (playIcon) {
        playIcon.className = isSimPaused ? 'fa-solid fa-play text-[11px]' : 'fa-solid fa-pause text-[11px]';
    }
    const btnText = document.getElementById('btn-pause-text');
    if (btnText) btnText.textContent = isSimPaused ? 'Lanjut' : 'Pause';
    const btnPause = document.getElementById('btn-pause');
    if (btnPause) {
        btnPause.innerHTML = isSimPaused 
            ? '<i class="fa-solid fa-play text-[10px] text-emerald-400"></i> <span>Lanjut</span>' 
            : '<i class="fa-solid fa-pause text-[10px]"></i> <span>Pause</span>';
    }
    addSimLog(isSimPaused ? '⏸️ Simulasi di-pause.' : '▶️ Simulasi dilanjutkan.');
    if (typeof updateTimelineHUD === 'function') updateTimelineHUD();
}

function addSimLog(msg, isAlert = false) {
    const feed = document.getElementById('sim-log-feed');
    if (!feed) return;
    const time = new Date().toTimeString().split(' ')[0];
    const el = document.createElement('div');
    el.className = isAlert ? 'text-rose-400 font-bold' : 'text-slate-300';
    el.innerHTML = `<span class="text-teal-400/80">[${time}]</span> ${msg}`;
    feed.appendChild(el);
    feed.scrollTop = feed.scrollHeight;
}

function clearSimLogs() {
    const feed = document.getElementById('sim-log-feed');
    if (feed) feed.innerHTML = '';
}

function showMilestone(title, desc, iconClass = 'fa-bell', isAlert = false) {
    const banner = document.getElementById('milestone-banner');
    if (!banner) return;
    const tEl = document.getElementById('milestone-title');
    const dEl = document.getElementById('milestone-desc');
    const iEl = document.getElementById('milestone-icon');
    if (tEl) tEl.textContent = title;
    if (dEl) dEl.textContent = desc;
    if (iEl) iEl.innerHTML = `<i class="fa-solid ${iconClass}"></i>`;
    
    if (isAlert) {
        banner.style.borderColor = '#EF4444';
        if (iEl) iEl.style.color = '#EF4444';
        playSound('alert');
    } else {
        banner.style.borderColor = '#0CA1AF';
        if (iEl) iEl.style.color = '#38BDF8';
        playSound('pass');
    }

    banner.classList.add('show');
    clearTimeout(banner._hideTimer);
    banner._hideTimer = setTimeout(() => {
        banner.classList.remove('show');
    }, 4500);
}

function updateStepperUI(currentStep, alertStep = null) {
    for (let i = 1; i <= 6; i++) {
        const item = document.getElementById('step-ui-' + i);
        const pill = document.getElementById('spill-' + i);
        if (item) {
            const statusSpan = item.querySelector('.step-status');
            item.classList.remove('active-step', 'done-step', 'alert-step');
            if (alertStep === i) {
                item.classList.add('alert-step');
                if (statusSpan) {
                    statusSpan.textContent = 'ALERT/HOLD';
                    statusSpan.className = 'step-status text-[9px] text-rose-400 font-mono font-bold animate-pulse';
                }
            } else if (i < currentStep) {
                item.classList.add('done-step');
                if (statusSpan) {
                    statusSpan.textContent = 'DONE';
                    statusSpan.className = 'step-status text-[9px] text-emerald-400 font-mono font-bold';
                }
            } else if (i === currentStep) {
                item.classList.add('active-step');
                if (statusSpan) {
                    statusSpan.textContent = 'ACTIVE';
                    statusSpan.className = 'step-status text-[9px] text-teal-300 font-mono font-bold';
                }
            } else {
                if (statusSpan) {
                    statusSpan.textContent = 'PENDING';
                    statusSpan.className = 'step-status text-[9px] text-white/40 font-mono';
                }
            }
        }
        if (pill) {
            pill.classList.remove('active');
            if (alertStep === i || i === currentStep) {
                pill.classList.add('active');
            }
        }
    }
    const pct = Math.min(100, Math.round(((currentStep - 1) / 5) * 100));
    const pctEl = document.getElementById('sim-progress-pct');
    if (pctEl) pctEl.textContent = pct + '%';
}

// ─────────────────────────────────────────────────────────────────────────────
// 3D SCENE SETUP (THREE.JS)
// ─────────────────────────────────────────────────────────────────────────────
const container = document.getElementById('viewport-container');
const canvas = document.getElementById('three-canvas');

const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: false, powerPreference: 'high-performance' });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.setSize(container.clientWidth, container.clientHeight);
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;

if ('outputColorSpace' in renderer && typeof THREE.SRGBColorSpace !== 'undefined') {
    renderer.outputColorSpace = THREE.SRGBColorSpace;
} else if ('outputEncoding' in renderer && typeof THREE.sRGBEncoding !== 'undefined') {
    renderer.outputEncoding = THREE.sRGBEncoding;
}
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.15;

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0B1528);
scene.fog = new THREE.FogExp2(0x0B1528, 0.0022);

const camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.5, 700);
camera.position.set(-38, 32, -45);

const controls = new THREE.OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.06;
controls.screenSpacePanning = true; // Izinkan pan bebas 2D (kiri, kanan, atas, bawah) ke segala arah
controls.maxPolarAngle = Math.PI * 0.495; // Memungkinkan rotasi vertikal penuh dari atas hingga sejajar tanah
controls.minPolarAngle = 0.01; // Memungkinkan pandangan vertikal 90 derajat lurus ke bawah (bird-eye)
controls.minDistance = 2.0; // Zoom dekat untuk inspeksi kargo dan sensor
controls.maxDistance = 650; // Zoom jauh untuk melihat kawasan bandara secara utuh
controls.rotateSpeed = 0.85; // Kecepatan putar mouse yang presisi dan halus
controls.panSpeed = 0.95; // Kecepatan geser/pan mouse yang responsif
controls.zoomSpeed = 1.15; // Kecepatan zoom mouse wheel yang nyaman
controls.target.set(12, 3, -2);

// Deteksi Interaksi Mouse User — Berikan kendali bebas 100% tanpa hambatan
let isUserInteracting = false;
let isCamTransitioning = false;

controls.addEventListener('start', () => {
    isUserInteracting = true;
    isCamTransitioning = false;
    if (simCameraMode === 'follow') {
        simCameraMode = 'free';
        if (typeof setCameraViewUI === 'function') setCameraViewUI('free');
    }
});

controls.addEventListener('end', () => {
    isUserInteracting = false;
});

// Keyboard Navigation Support (Pan dengan tombol panah keyboard)
controls.listenToKeyEvents(window);
controls.keyPanSpeed = 16.0;

// Switch Mode Drag Mouse (Putar 360° vs Geser / Pan)
let currentMouseDragMode = 'rotate'; // 'rotate' | 'pan'

function setMouseDragMode(mode) {
    currentMouseDragMode = mode;
    const bRot = document.getElementById('btn-mode-rotate');
    const bPan = document.getElementById('btn-mode-pan');
    if (mode === 'pan') {
        controls.mouseButtons.LEFT = THREE.MOUSE.PAN;
        controls.mouseButtons.RIGHT = THREE.MOUSE.ROTATE;
        if (bPan) {
            bPan.className = 'px-2 py-1 rounded text-[10px] font-bold bg-[#0CA1AF] text-white shadow flex items-center gap-1 transition-all cursor-pointer';
        }
        if (bRot) {
            bRot.className = 'px-2 py-1 rounded text-[10px] font-bold text-slate-300 hover:text-white flex items-center gap-1 transition-all cursor-pointer';
        }
        canvas.style.cursor = 'move';
    } else {
        controls.mouseButtons.LEFT = THREE.MOUSE.ROTATE;
        controls.mouseButtons.RIGHT = THREE.MOUSE.PAN;
        if (bRot) {
            bRot.className = 'px-2 py-1 rounded text-[10px] font-bold bg-[#0CA1AF] text-white shadow flex items-center gap-1 transition-all cursor-pointer';
        }
        if (bPan) {
            bPan.className = 'px-2 py-1 rounded text-[10px] font-bold text-slate-300 hover:text-white flex items-center gap-1 transition-all cursor-pointer';
        }
        canvas.style.cursor = 'grab';
    }
}

// Navigasi Kamera Presisi (Pan & Zoom dengan On-Screen Controller)
function panCamera(dx, dz) {
    camera.updateMatrixWorld();
    const right = new THREE.Vector3(camera.matrixWorld.elements[0], 0, camera.matrixWorld.elements[2]).normalize();
    const forward = new THREE.Vector3(-camera.matrixWorld.elements[8], 0, -camera.matrixWorld.elements[10]).normalize();

    const delta = new THREE.Vector3()
        .addScaledVector(right, dx)
        .addScaledVector(forward, dz);

    camera.position.add(delta);
    controls.target.add(delta);
    controls.update();

    isCamTransitioning = false;
    if (simCameraMode === 'follow') {
        simCameraMode = 'free';
        if (typeof setCameraViewUI === 'function') setCameraViewUI('free');
    }
}

function zoomCamera(amount) {
    const offset = new THREE.Vector3().subVectors(controls.target, camera.position);
    const dist = offset.length();
    if (dist > 3.0 || amount < 0) {
        const step = Math.min(amount, dist - 2.5);
        const dir = offset.clone().normalize();
        camera.position.addScaledVector(dir, step);
        controls.update();
    }
    isCamTransitioning = false;
    if (simCameraMode === 'follow') {
        simCameraMode = 'free';
        if (typeof setCameraViewUI === 'function') setCameraViewUI('free');
    }
}

// Lighting — Balanced Atmospheric Contrast (No Overexposure)
const ambientLight = new THREE.AmbientLight(0xdce7f5, 0.85);
scene.add(ambientLight);

const skyHemi = new THREE.HemisphereLight(0x7DD3FC, 0x0F172A, 0.65);
scene.add(skyHemi);

const mainSun = new THREE.DirectionalLight(0xffffff, 1.45);
mainSun.position.set(40, 75, -20);
mainSun.castShadow = true;
mainSun.shadow.mapSize.width = 2048;
mainSun.shadow.mapSize.height = 2048;
mainSun.shadow.camera.near = 10;
mainSun.shadow.camera.far = 280;
mainSun.shadow.camera.left = -75;
mainSun.shadow.camera.right = 85;
mainSun.shadow.camera.top = 75;
mainSun.shadow.camera.bottom = -75;
mainSun.shadow.bias = -0.00005;
mainSun.shadow.normalBias = 0.04;
scene.add(mainSun);

const fillBlue = new THREE.DirectionalLight(0x0CA1AF, 0.45);
fillBlue.position.set(-50, 40, 50);
scene.add(fillBlue);

// Interior Terminal Hall Point Light (Warm/Cyan Skylight Glow)
const hallLight = new THREE.PointLight(0x38bdf8, 1.8, 85);
hallLight.position.set(0, 16, 0);
scene.add(hallLight);

// Strobe Lights
const redAlarmStrobe = new THREE.PointLight(0xEF4444, 0, 40);
redAlarmStrobe.position.set(22, 5.5, -16.5);
scene.add(redAlarmStrobe);

const xrayScanLight = new THREE.PointLight(0x06B6D4, 0, 20);
xrayScanLight.position.set(-5, 2.5, -18.5);
scene.add(xrayScanLight);

// Lampu Sorot Floodlight Menara Apron
const apronSpot = new THREE.SpotLight(0xFFFFFF, 2.8, 90, Math.PI / 4, 0.35);
apronSpot.position.set(40, 32, 35);
apronSpot.target.position.set(55, 3, 8);
scene.add(apronSpot, apronSpot.target);

// ─────────────────────────────────────────────────────────────────────────────
// TEKSTUR PROCEDURAL TINGKAT TINGGI (PROCEDURAL PBR-LIKE SURFACE TEXTURES)
// ─────────────────────────────────────────────────────────────────────────────
function createEpoxyGridTexture() {
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 512;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#E8ECEF';
    ctx.fillRect(0, 0, 512, 512);

    ctx.strokeStyle = '#CBD5E1';
    ctx.lineWidth = 2.5;
    for (let i = 0; i <= 512; i += 128) {
        ctx.beginPath(); ctx.moveTo(i, 0); ctx.lineTo(i, 512); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(0, i); ctx.lineTo(512, i); ctx.stroke();
    }

    for (let i = 0; i < 450; i++) {
        const x = Math.random() * 512;
        const y = Math.random() * 512;
        ctx.fillStyle = Math.random() > 0.4 ? 'rgba(255, 255, 255, 0.4)' : 'rgba(100, 116, 139, 0.15)';
        ctx.fillRect(x, y, 1.5, 1.5);
    }

    const tex = new THREE.CanvasTexture(c);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.RepeatWrapping;
    tex.repeat.set(22, 16);
    return tex;
}

function createTarmacAsphaltTexture() {
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 512;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#1A2230';
    ctx.fillRect(0, 0, 512, 512);

    ctx.strokeStyle = '#0F1622';
    ctx.lineWidth = 3.5;
    ctx.strokeRect(2, 2, 254, 254);
    ctx.strokeRect(258, 2, 254, 254);
    ctx.strokeRect(2, 258, 254, 254);
    ctx.strokeRect(258, 258, 254, 254);

    for (let i = 0; i < 800; i++) {
        const x = Math.random() * 512;
        const y = Math.random() * 512;
        ctx.fillStyle = Math.random() > 0.5 ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.22)';
        ctx.fillRect(x, y, 2, 2);
    }

    const tex = new THREE.CanvasTexture(c);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.RepeatWrapping;
    tex.repeat.set(28, 24);
    return tex;
}

function createFineAsphaltTexture() {
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 512;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#222A35';
    ctx.fillRect(0, 0, 512, 512);

    for (let i = 0; i < 2200; i++) {
        const x = Math.random() * 512;
        const y = Math.random() * 512;
        ctx.fillStyle = Math.random() > 0.5 ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.22)';
        ctx.fillRect(x, y, 1.5, 1.5);
    }

    const tex = new THREE.CanvasTexture(c);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.RepeatWrapping;
    tex.repeat.set(16, 16);
    return tex;
}

function createCorrugatedWallTexture() {
    const c = document.createElement('canvas');
    c.width = 256;
    c.height = 256;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#E2E8F0';
    ctx.fillRect(0, 0, 256, 256);

    for (let y = 0; y < 256; y += 32) {
        ctx.fillStyle = 'rgba(71, 85, 105, 0.28)';
        ctx.fillRect(0, y, 256, 3);
        ctx.fillStyle = 'rgba(255, 255, 255, 0.55)';
        ctx.fillRect(0, y + 3, 256, 2);
    }

    const tex = new THREE.CanvasTexture(c);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.RepeatWrapping;
    tex.repeat.set(6, 4);
    return tex;
}

function createAircraftLiveryTexture() {
    const c = document.createElement('canvas');
    c.width = 1024;
    c.height = 256;
    const ctx = c.getContext('2d');

    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, 1024, 256);

    ctx.fillStyle = '#0D1C42';
    ctx.beginPath();
    ctx.moveTo(0, 155);
    ctx.lineTo(820, 155);
    ctx.quadraticCurveTo(920, 150, 1024, 80);
    ctx.lineTo(1024, 115);
    ctx.quadraticCurveTo(920, 180, 820, 180);
    ctx.lineTo(0, 180);
    ctx.closePath();
    ctx.fill();

    ctx.fillStyle = '#0CA1AF';
    ctx.beginPath();
    ctx.moveTo(0, 183);
    ctx.lineTo(820, 183);
    ctx.quadraticCurveTo(920, 183, 1024, 125);
    ctx.lineTo(1024, 145);
    ctx.quadraticCurveTo(920, 200, 820, 200);
    ctx.lineTo(0, 200);
    ctx.closePath();
    ctx.fill();

    ctx.fillStyle = '#334155';
    ctx.fillRect(0, 228, 1024, 28);

    ctx.fillStyle = '#0D1C42';
    ctx.font = '900 32px Barlow, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText('INJOURNEY', 280, 115);
    ctx.fillStyle = '#0CA1AF';
    ctx.font = 'bold 24px Barlow, sans-serif';
    ctx.fillText('AIR CARGO', 475, 115);

    ctx.fillStyle = '#64748B';
    ctx.font = 'bold 16px monospace';
    ctx.fillText('BOEING 777-200LRF • PK-ACF', 280, 142);

    const fx = 180, fy = 100, fw = 36, fh = 22;
    ctx.fillStyle = '#DC2626';
    ctx.fillRect(fx, fy, fw, fh / 2);
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(fx, fy + fh / 2, fw, fh / 2);
    ctx.strokeStyle = '#94A3B8';
    ctx.lineWidth = 1;
    ctx.strokeRect(fx, fy, fw, fh);

    ctx.fillStyle = '#0F172A';
    ctx.beginPath();
    ctx.moveTo(60, 90);
    ctx.lineTo(140, 95);
    ctx.lineTo(135, 120);
    ctx.lineTo(55, 110);
    ctx.closePath();
    ctx.fill();

    ctx.fillStyle = '#38BDF8';
    for (let w = 0; w < 3; w++) {
        ctx.fillRect(70 + w * 22, 98, 18, 16);
    }

    ctx.strokeStyle = '#F59E0B';
    ctx.lineWidth = 3;
    ctx.strokeRect(400, 70, 90, 80);
    ctx.fillStyle = '#F59E0B';
    ctx.font = 'bold 10px monospace';
    ctx.fillText('MAIN CARGO DOOR', 402, 65);

    ctx.strokeStyle = '#64748B';
    ctx.lineWidth = 2;
    ctx.strokeRect(260, 165, 55, 45);
    ctx.strokeRect(700, 165, 60, 45);

    const tex = new THREE.CanvasTexture(c);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.ClampToEdgeWrapping;
    return tex;
}

function createTailFinTexture() {
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 512;
    const ctx = c.getContext('2d');

    const grad = ctx.createLinearGradient(0, 0, 512, 512);
    grad.addColorStop(0, '#0D1C42');
    grad.addColorStop(0.65, '#071630');
    grad.addColorStop(1, '#0CA1AF');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, 512, 512);

    ctx.strokeStyle = '#FFFFFF';
    ctx.lineWidth = 14;
    ctx.beginPath();
    ctx.arc(256, 230, 130, Math.PI * 0.8, Math.PI * 1.8);
    ctx.stroke();

    ctx.strokeStyle = '#38BDF8';
    ctx.lineWidth = 10;
    ctx.beginPath();
    ctx.arc(256, 250, 95, Math.PI * 0.85, Math.PI * 1.75);
    ctx.stroke();

    ctx.fillStyle = '#FFFFFF';
    ctx.font = 'bold 36px monospace';
    ctx.textAlign = 'center';
    ctx.fillText('PK-ACF', 256, 420);

    const tex = new THREE.CanvasTexture(c);
    return tex;
}

function createAviationSkyDome() {
    const skyGeo = new THREE.SphereGeometry(320, 32, 16);
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 512;
    const ctx = c.getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 512);
    grad.addColorStop(0.0, '#0B1C33');
    grad.addColorStop(0.35, '#123854');
    grad.addColorStop(0.65, '#1E4E6E');
    grad.addColorStop(0.85, '#38BDF8');
    grad.addColorStop(1.0, '#0B1528');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, 512, 512);

    for (let i = 0; i < 90; i++) {
        const sx = Math.random() * 512;
        const sy = Math.random() * 260;
        const r = Math.random() * 1.4;
        ctx.fillStyle = 'rgba(255, 255, 255, ' + (0.35 + Math.random() * 0.65) + ')';
        ctx.beginPath();
        ctx.arc(sx, sy, r, 0, Math.PI * 2);
        ctx.fill();
    }

    const skyTex = new THREE.CanvasTexture(c);
    const skyMat = new THREE.MeshBasicMaterial({ map: skyTex, side: THREE.BackSide, fog: false });
    const skyMesh = new THREE.Mesh(skyGeo, skyMat);
    return skyMesh;
}

scene.add(createAviationSkyDome());

const epoxyTex = createEpoxyGridTexture();
const tarmacTex = createTarmacAsphaltTexture();
const fineAsphaltTex = createFineAsphaltTexture();
const wallTex = createCorrugatedWallTexture();
const planeLiveryTex = createAircraftLiveryTexture();
const tailFinTex = createTailFinTexture();

// ─────────────────────────────────────────────────────────────────────────────
// MATERIALS
// ─────────────────────────────────────────────────────────────────────────────
const MAT = {
    floorTerminal: new THREE.MeshStandardMaterial({ map: epoxyTex, color: 0xF8FAFC, roughness: 0.22, metalness: 0.18 }),
    floorTarmac: new THREE.MeshStandardMaterial({ map: tarmacTex, color: 0x334155, roughness: 0.85, metalness: 0.15 }),
    floorQuarantine: new THREE.MeshStandardMaterial({ color: 0x0F172A, roughness: 0.45, metalness: 0.2 }),
    airfieldGrass: new THREE.MeshStandardMaterial({ color: 0x15803D, roughness: 0.95, metalness: 0.05 }),
    asphaltRoad: new THREE.MeshStandardMaterial({ map: fineAsphaltTex, color: 0x283344, roughness: 0.82, metalness: 0.12 }),
    concreteCurb: new THREE.MeshStandardMaterial({ color: 0x94A3B8, roughness: 0.7, metalness: 0.15 }),
    sidewalkPaver: new THREE.MeshStandardMaterial({ color: 0xCBD5E1, roughness: 0.8, metalness: 0.1 }),
    concreteApron: new THREE.MeshStandardMaterial({ color: 0x475569, roughness: 0.75, metalness: 0.15 }),
    wallBuilding: new THREE.MeshStandardMaterial({ map: wallTex, color: 0xE2E8F0, roughness: 0.55, metalness: 0.25 }),
    wallBasePlinth: new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.8, metalness: 0.2 }),
    wallPanel: new THREE.MeshStandardMaterial({ map: wallTex, color: 0xF1F5F9, roughness: 0.5, metalness: 0.1 }),
    wallAccentTeal: new THREE.MeshStandardMaterial({ color: 0x0CA1AF, roughness: 0.3, metalness: 0.2 }),
    wallGlass: new THREE.MeshStandardMaterial({ color: 0xBAE6FD, transparent: true, opacity: 0.35, roughness: 0.1 }),
    curtainGlass: new THREE.MeshStandardMaterial({ color: 0x93C5FD, transparent: true, opacity: 0.32, roughness: 0.08, metalness: 0.1 }),
    roofTruss: new THREE.MeshStandardMaterial({ color: 0x334155, metalness: 0.75, roughness: 0.35 }),
    safetyGreen: new THREE.MeshStandardMaterial({ color: 0x10B981, roughness: 0.45 }),
    hazardBlack: new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.5 }),
    safetyOrange: new THREE.MeshStandardMaterial({ color: 0xF97316, roughness: 0.4 }),
    metalSilver: new THREE.MeshStandardMaterial({ color: 0xD1D5DB, metalness: 0.85, roughness: 0.25 }),
    metalDark: new THREE.MeshStandardMaterial({ color: 0x334155, metalness: 0.7, roughness: 0.4 }),
    yellowSafety: new THREE.MeshStandardMaterial({ color: 0xF59E0B, roughness: 0.5 }),
    redHazard: new THREE.MeshStandardMaterial({ color: 0xEF4444, roughness: 0.4 }),
    injourneyTeal: new THREE.MeshStandardMaterial({ color: 0x0CA1AF, metalness: 0.4, roughness: 0.3 }),
    conveyorRoller: new THREE.MeshStandardMaterial({ color: 0x94A3B8, metalness: 0.85, roughness: 0.3 }),
    palletWood: new THREE.MeshStandardMaterial({ color: 0xB45309, roughness: 0.9 }),
    cardboardBox: new THREE.MeshStandardMaterial({ color: 0xD97706, roughness: 0.8 }),
    cardboardBoxSuspect: new THREE.MeshStandardMaterial({ color: 0xDC2626, roughness: 0.5 }),
    uldAluminum: new THREE.MeshStandardMaterial({ color: 0xE2E8F0, metalness: 0.92, roughness: 0.2 }),
    uldCanvasBlue: new THREE.MeshStandardMaterial({ color: 0x1D4ED8, roughness: 0.7 }),
    truckCabBlue: new THREE.MeshStandardMaterial({ color: 0x0284C7, roughness: 0.25, metalness: 0.6 }),
    truckTire: new THREE.MeshStandardMaterial({ color: 0x111827, roughness: 0.9 }),
    planeWhite: new THREE.MeshStandardMaterial({ color: 0xF8FAFC, roughness: 0.25, metalness: 0.25, side: THREE.DoubleSide }),
    planeLivery: new THREE.MeshStandardMaterial({ map: planeLiveryTex, roughness: 0.25, metalness: 0.2, side: THREE.DoubleSide }),
    planeTailFin: new THREE.MeshStandardMaterial({ map: tailFinTex, roughness: 0.25, metalness: 0.2, side: THREE.DoubleSide }),
    planeNavy: new THREE.MeshStandardMaterial({ color: 0x0A2540, roughness: 0.35, metalness: 0.25 }),
    cockpitGlass: new THREE.MeshStandardMaterial({ color: 0x07111E, roughness: 0.08, metalness: 0.92 }),
    chromeIntake: new THREE.MeshStandardMaterial({ color: 0xE2E8F0, metalness: 0.95, roughness: 0.12 }),
    engineCore: new THREE.MeshStandardMaterial({ color: 0x1E293B, metalness: 0.8, roughness: 0.4 })
};

// Master Group for All Station Stencils & Architectural Signs
const signsGroup = new THREE.Group();
scene.add(signsGroup);

let visualLabelsVisible = true;
function toggleVisualLabels() {
    visualLabelsVisible = !visualLabelsVisible;
    signsGroup.visible = visualLabelsVisible;
    const btn = document.getElementById('btn-labels-toggle');
    if (btn) {
        if (visualLabelsVisible) {
            btn.classList.add('active');
            btn.classList.remove('opacity-50');
        } else {
            btn.classList.remove('active');
            btn.classList.add('opacity-50');
        }
    }
}

// 1. High-Precision Painted Floor Decal / Zone Stencil (Lies flat on floor, ZERO camera occlusion)
function makeFloorDecal(title, subtitle, width = 6, height = 3, strokeColor = '#FACC15', fillColor = 'rgba(8, 18, 38, 0.65)') {
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 256;
    const ctx = c.getContext('2d');

    // Fill background pad
    ctx.fillStyle = fillColor;
    ctx.fillRect(4, 4, 504, 248);

    // Border
    ctx.strokeStyle = strokeColor;
    ctx.lineWidth = 6;
    ctx.strokeRect(6, 6, 500, 244);

    // Industrial Corner Chevrons
    ctx.fillStyle = strokeColor;
    const cw = 22, ch = 22;
    ctx.fillRect(6, 6, cw, 6); ctx.fillRect(6, 6, 6, ch);
    ctx.fillRect(506 - cw, 6, cw, 6); ctx.fillRect(500, 6, 6, ch);
    ctx.fillRect(6, 244, cw, 6); ctx.fillRect(6, 250 - ch, 6, ch);
    ctx.fillRect(506 - cw, 244, cw, 6); ctx.fillRect(500, 250 - ch, 6, ch);

    // Title
    ctx.fillStyle = strokeColor;
    ctx.font = '900 42px Barlow, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(title, 256, subtitle ? 96 : 128);

    // Subtitle
    if (subtitle) {
        ctx.fillStyle = '#FFFFFF';
        ctx.font = 'bold 24px Barlow, sans-serif';
        ctx.fillText(subtitle, 256, 168);
    }

    const tex = new THREE.CanvasTexture(c);
    const mat = new THREE.MeshBasicMaterial({
        map: tex,
        transparent: true,
        polygonOffset: true,
        polygonOffsetFactor: -1,
        polygonOffsetUnits: -1
    });
    const mesh = new THREE.Mesh(new THREE.PlaneGeometry(width, height), mat);
    mesh.rotation.x = -Math.PI / 2;
    mesh.position.y = 0.035;
    return mesh;
}

// 2. Physical 3D Signboard (Flush on walls, canopy fascia, or portal crossbeams)
function makeMountedSignboard(title, subtitle, width = 8, height = 1.4, accentColor = '#0CA1AF') {
    const group = new THREE.Group();

    const backing = new THREE.Mesh(
        new THREE.BoxGeometry(width, height, 0.08),
        new THREE.MeshStandardMaterial({ color: 0x0B1528, metalness: 0.8, roughness: 0.3 })
    );

    const c = document.createElement('canvas');
    c.width = 1024;
    c.height = 256;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#060D1A';
    ctx.fillRect(0, 0, 1024, 256);

    ctx.strokeStyle = accentColor;
    ctx.lineWidth = 10;
    ctx.strokeRect(8, 8, 1008, 240);

    ctx.fillStyle = accentColor;
    ctx.font = '900 64px Barlow, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(title, 512, 105);

    if (subtitle) {
        ctx.fillStyle = '#E2E8F0';
        ctx.font = 'bold 36px Barlow, sans-serif';
        ctx.fillText(subtitle, 512, 185);
    }

    const tex = new THREE.CanvasTexture(c);
    const faceMat = new THREE.MeshBasicMaterial({ map: tex });
    const face = new THREE.Mesh(new THREE.PlaneGeometry(width * 0.98, height * 0.94), faceMat);
    face.position.z = 0.045;

    group.add(backing, face);
    return group;
}

// 3. Overhead Hanging Aisle Sign (Hung from steel roof trusses with cables)
function makeHangingAisleSign(zoneName, desc, width = 4.2, height = 1.2, color = '#38BDF8') {
    const group = new THREE.Group();

    [-width * 0.38, width * 0.38].forEach(cx => {
        const cable = new THREE.Mesh(
            new THREE.CylinderGeometry(0.015, 0.015, 1.4, 6),
            MAT.metalDark
        );
        cable.position.set(cx, 0.7, 0);
        group.add(cable);
    });

    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 160;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#091326';
    ctx.fillRect(0, 0, 512, 160);
    ctx.strokeStyle = color;
    ctx.lineWidth = 8;
    ctx.strokeRect(6, 6, 500, 148);

    ctx.fillStyle = color;
    ctx.font = '900 48px Barlow, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(zoneName, 256, 68);

    ctx.fillStyle = '#F8FAFC';
    ctx.font = 'bold 24px Barlow, sans-serif';
    ctx.fillText(desc, 256, 122);

    const tex = new THREE.CanvasTexture(c);
    const signGeo = new THREE.BoxGeometry(width, height, 0.06);
    const signMat = new THREE.MeshStandardMaterial({
        map: tex,
        color: 0xFFFFFF,
        roughness: 0.3,
        metalness: 0.4
    });
    const signBoard = new THREE.Mesh(signGeo, signMat);
    group.add(signBoard);

    return group;
}

// 4. Compact Moving Cargo Tracking Badge (Small & Non-Intrusive)
function makeLabelSprite(text, subtext, color = '#0CA1AF') {
    const c = document.createElement('canvas');
    c.width = 384;
    c.height = 110;
    const ctx = c.getContext('2d');

    ctx.fillStyle = 'rgba(6, 13, 26, 0.90)';
    ctx.strokeStyle = color;
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.roundRect(4, 4, 376, 102, 12);
    ctx.fill();
    ctx.stroke();

    ctx.fillStyle = color;
    ctx.font = 'bold 22px Barlow, sans-serif';
    ctx.fillText(text, 18, 40);

    ctx.fillStyle = '#FFFFFF';
    ctx.font = 'bold 26px monospace';
    ctx.fillText(subtext, 18, 82);

    const tex = new THREE.CanvasTexture(c);
    const spriteMat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthWrite: false });
    const sprite = new THREE.Sprite(spriteMat);
    sprite.scale.set(3.2, 0.9, 1);
    return sprite;
}

// 5. Painted Tarmac Text
function makeTarmacText(text, width = 6, height = 3, color = '#FACC15') {
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 256;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, 512, 256);
    ctx.fillStyle = color;
    ctx.font = '900 70px Barlow, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, 256, 128);

    const tex = new THREE.CanvasTexture(c);
    const mat = new THREE.MeshBasicMaterial({ map: tex, transparent: true, polygonOffset: true, polygonOffsetFactor: -1, polygonOffsetUnits: -1 });
    const mesh = new THREE.Mesh(new THREE.PlaneGeometry(width, height), mat);
    mesh.rotation.x = -Math.PI / 2;
    mesh.position.y = 0.025;
    return mesh;
}

// ─────────────────────────────────────────────────────────────────────────────
// ARSITEKTUR LENGKAP TERMINAL KARGO (FULL AIRPORT CARGO TERMINAL LAYOUT)
// ─────────────────────────────────────────────────────────────────────────────
const layoutGroup = new THREE.Group();
scene.add(layoutGroup);

const dockGroup = new THREE.Group();
scene.add(dockGroup);

const rfidGroup = new THREE.Group();
scene.add(rfidGroup);

const xrayGroup = new THREE.Group();
scene.add(xrayGroup);

const storageGroup = new THREE.Group();
scene.add(storageGroup);

const scaleGroup = new THREE.Group();
scene.add(scaleGroup);

const airsideGroup = new THREE.Group();
scene.add(airsideGroup);

const conveyorGroup = new THREE.Group();
scene.add(conveyorGroup);

function bindInteractive(mesh, stationKey) {
    if (mesh) mesh.userData = { stationKey, isInteractive: true };
}

// Global animation array for warehouse HVLS ceiling fans
const hvlsFans = [];

// ─────────────────────────────────────────────────────────────────────────────
// 1. LANTAI INDUSTRI & TARMAC APRON (INDUSTRIAL FLOORS & MARKINGS)
// ─────────────────────────────────────────────────────────────────────────────
function createAsphaltArrowMesh(x, z, rotY = 0) {
    const c = document.createElement('canvas');
    c.width = 256;
    c.height = 512;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, 256, 512);

    ctx.fillStyle = '#FFFFFF';
    ctx.beginPath();
    ctx.moveTo(128, 40);
    ctx.lineTo(220, 220);
    ctx.lineTo(160, 220);
    ctx.lineTo(160, 460);
    ctx.lineTo(96, 460);
    ctx.lineTo(96, 220);
    ctx.lineTo(36, 220);
    ctx.closePath();
    ctx.fill();

    const tex = new THREE.CanvasTexture(c);
    const mat = new THREE.MeshBasicMaterial({
        map: tex,
        transparent: true,
        polygonOffset: true,
        polygonOffsetFactor: -1,
        polygonOffsetUnits: -1
    });
    const mesh = new THREE.Mesh(new THREE.PlaneGeometry(1.8, 3.6), mat);
    mesh.rotation.x = -Math.PI / 2;
    mesh.rotation.z = rotY;
    mesh.position.set(x, 0.018, z);
    return mesh;
}

// 1. Hamparan Rumput Hijau Lapangan Terbang Bandara (Airfield Grass Turf 380m x 320m)
// 1. Hamparan Rumput Hijau Lapangan Terbang Bandara (Airfield Grass Turf 450m x 450m)
// Membentang luas melingkupi seluruh fasilitas terminal & jalan tanpa ada celah hampa
const grassGeo = new THREE.PlaneGeometry(450, 450);
const grassMesh = new THREE.Mesh(grassGeo, MAT.airfieldGrass);
grassMesh.rotation.x = -Math.PI / 2;
grassMesh.position.set(20, -0.08, -30);
grassMesh.receiveShadow = true;
layoutGroup.add(grassMesh);

// 2. Pad Beton Apron Sisi Udara (Airside Commercial Concrete Apron)
// Dimulai dari sisi timur gedung terminal (X = 38 ke timur hingga X = 145, Z = -67 ke +83)
const apronGeo = new THREE.PlaneGeometry(105, 150);
const apronMesh = new THREE.Mesh(apronGeo, MAT.floorTarmac);
apronMesh.rotation.x = -Math.PI / 2;
apronMesh.position.set(90.5, -0.04, 8);
apronMesh.receiveShadow = true;
layoutGroup.add(apronMesh);

// 3. Pelataran Aspal Dermaga Truk Landside (Truck Maneuvering Staging Apron)
// Membentang tepat dari dinding dermaga (Z = -35.75) ke gerbang keamanan (Z = -70.0)
// Lebar X dari -46.0 s.d. +38.0 (Lebar 84m), tanpa tumpang tindih Z dengan jalan
const yardGeo = new THREE.PlaneGeometry(84, 34.25);
const yardMesh = new THREE.Mesh(yardGeo, MAT.asphaltRoad);
yardMesh.rotation.x = -Math.PI / 2;
yardMesh.position.set(-4.0, -0.04, -52.875);
yardMesh.receiveShadow = true;
layoutGroup.add(yardMesh);

// 4. Jalan Raya Akses Truk Masuk & Keluar (Landside Access Highway ke Selatan)
// Membentang tepat dari Z = -70.0 s.d. Z = -140.0 (Panjang 70m, Lebar 22m, X = -15.0 s.d. +7.0, Center Z = -105.0)
// Disesuaikan proporsional, rapi, dan berlabuh kokoh di dalam batas rumput bandara
const roadGeo = new THREE.PlaneGeometry(22, 70);
const roadMesh = new THREE.Mesh(roadGeo, MAT.asphaltRoad);
roadMesh.rotation.x = -Math.PI / 2;
roadMesh.position.set(-4.0, -0.04, -105.0);
roadMesh.receiveShadow = true;
layoutGroup.add(roadMesh);

// ─────────────────────────────────────────────────────────────────────────────
// KANSTIN BETON 3D, TROTOAR PEJALAN KAKI SIMETRIS & PERIMETER PELATARAN
// ─────────────────────────────────────────────────────────────────────────────
const curbGroup = new THREE.Group();

// Kanstin Lurus Sisi Barat Jalan (West Road Curb X = -15.15, Z = -70 s.d. -140)
const westCurb = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.16, 70), MAT.concreteCurb);
westCurb.position.set(-15.15, 0.04, -105.0);
westCurb.receiveShadow = true;
curbGroup.add(westCurb);

// Trotoar Pejalan Kaki Sisi Barat (West Pedestrian Sidewalk di Sisi Barat Jalan)
const westSidewalk = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.12, 70), MAT.sidewalkPaver);
westSidewalk.position.set(-16.5, 0.02, -105.0);
westSidewalk.receiveShadow = true;
curbGroup.add(westSidewalk);

// Kanstin Belokan Sudut Barat Menuju Pelataran (-15.15, -70) ke (-46.15, -55)
const westFlareLength = Math.hypot(31, 15);
const westFlareAngle = Math.atan2(31, 15);
const westFlare = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.16, westFlareLength), MAT.concreteCurb);
westFlare.position.set((-15.15 - 46.15) / 2, 0.04, (-70.0 - 55.0) / 2);
westFlare.rotation.y = westFlareAngle;
westFlare.receiveShadow = true;
curbGroup.add(westFlare);

// Kanstin Sisi Barat Pelataran Dermaga (X = -46.15, Z = -55 s.d. -35.75)
const westYardCurb = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.16, 19.25), MAT.concreteCurb);
westYardCurb.position.set(-46.15, 0.04, -45.375);
westYardCurb.receiveShadow = true;
curbGroup.add(westYardCurb);

// Kanstin Lurus Sisi Timur Jalan (East Road Curb X = +7.15, Z = -70 s.d. -140)
const eastCurb = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.16, 70), MAT.concreteCurb);
eastCurb.position.set(7.15, 0.04, -105.0);
eastCurb.receiveShadow = true;
curbGroup.add(eastCurb);

// Kanstin Belokan Sudut Timur Menuju Pelataran (+7.15, -70) ke (+38.15, -55)
const eastFlareLength = Math.hypot(31, 15);
const eastFlareAngle = -Math.atan2(31, 15);
const eastFlare = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.16, eastFlareLength), MAT.concreteCurb);
eastFlare.position.set((7.15 + 38.15) / 2, 0.04, (-70.0 - 55.0) / 2);
eastFlare.rotation.y = eastFlareAngle;
eastFlare.receiveShadow = true;
curbGroup.add(eastFlare);

// Kanstin Sisi Timur Pelataran Dermaga (X = +38.15, Z = -55 s.d. -35.75)
const eastYardCurb = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.16, 19.25), MAT.concreteCurb);
eastYardCurb.position.set(38.15, 0.04, -45.375);
eastYardCurb.receiveShadow = true;
curbGroup.add(eastYardCurb);

// Trotoar Pejalan Kaki Sisi Timur (East Pedestrian Sidewalk di Sisi Timur Jalan)
const sidewalk = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.12, 70), MAT.sidewalkPaver);
sidewalk.position.set(8.5, 0.02, -105.0);
sidewalk.receiveShadow = true;
curbGroup.add(sidewalk);

layoutGroup.add(curbGroup);

// ─────────────────────────────────────────────────────────────────────────────
// MARKA JALAN RAYA & PELATARAN LOGISTIK (STANDAR MARKA DISHUB / ICAO)
// ─────────────────────────────────────────────────────────────────────────────
const roadMarkGroup = new THREE.Group();

// 1. Garis Tepi Kuning Solid (Yellow Outer Curb Lines)
[-14.6, 6.6].forEach(rx => {
    const yellowEdge = new THREE.Mesh(
        new THREE.PlaneGeometry(0.22, 69.2),
        new THREE.MeshBasicMaterial({ color: 0xFACC15, polygonOffset: true, polygonOffsetFactor: -1, polygonOffsetUnits: -1 })
    );
    yellowEdge.rotation.x = -Math.PI / 2;
    yellowEdge.position.set(rx, 0.012, -105.0);
    roadMarkGroup.add(yellowEdge);
});

// 2. Garis Sumbu Jalan Putus-Putus Putih (Center Dashed Lane Divider di X = -1.0)
for (let rz = -135; rz <= -76; rz += 7.0) {
    const whiteDash = new THREE.Mesh(
        new THREE.PlaneGeometry(0.28, 3.5),
        new THREE.MeshBasicMaterial({ color: 0xFFFFFF, polygonOffset: true, polygonOffsetFactor: -1, polygonOffsetUnits: -1 })
    );
    whiteDash.rotation.x = -Math.PI / 2;
    whiteDash.position.set(-1.0, 0.014, rz);
    roadMarkGroup.add(whiteDash);
}

// 3. Garis Koridor Lajur Masuk Truk Solid Putih (Inbound Corridor Line di X = -9.0)
const inboundLine = new THREE.Mesh(
    new THREE.PlaneGeometry(0.2, 59.5),
    new THREE.MeshBasicMaterial({ color: 0xFFFFFF, polygonOffset: true, polygonOffsetFactor: -1, polygonOffsetUnits: -1 })
);
inboundLine.rotation.x = -Math.PI / 2;
inboundLine.position.set(-9.0, 0.014, -105.5);
roadMarkGroup.add(inboundLine);

// 4. Garis Henti Putih Tebal & Teks "STOP" di Depan Barrier Gerbang (Z = -74.5)
const stopBar = new THREE.Mesh(
    new THREE.PlaneGeometry(8.0, 0.45),
    new THREE.MeshBasicMaterial({ color: 0xFFFFFF, polygonOffset: true, polygonOffsetFactor: -1, polygonOffsetUnits: -1 })
);
stopBar.rotation.x = -Math.PI / 2;
stopBar.position.set(-5.0, 0.016, -74.5);
roadMarkGroup.add(stopBar);

const stopText = makeTarmacText("STOP", 3.2, 1.6, "#FFFFFF");
stopText.position.set(-5.0, 0.018, -78.0);
roadMarkGroup.add(stopText);

// 5. Zebra Cross Penyeberangan Personel di Depan Pos Gerbang (Z = -68.0)
for (let zx = -13.0; zx <= 5.0; zx += 1.4) {
    const zStripe = new THREE.Mesh(
        new THREE.PlaneGeometry(0.55, 3.8),
        new THREE.MeshBasicMaterial({ color: 0xFFFFFF, polygonOffset: true, polygonOffsetFactor: -1, polygonOffsetUnits: -1 })
    );
    zStripe.rotation.x = -Math.PI / 2;
    zStripe.position.set(zx, 0.015, -68.0);
    roadMarkGroup.add(zStripe);
}

// 6. Marka Batas Kecepatan "MAX 20" Stencil di Permukaan Aspal (Z = -112.0)
const speedLimitText = makeTarmacText("MAX 20", 3.4, 1.8, "#FACC15");
speedLimitText.position.set(-5.0, 0.018, -112.0);
roadMarkGroup.add(speedLimitText);

// 7. Marka Panah Pengarah Jalan (Directional Road Arrows)
[-128.0, -95.0].forEach(az => {
    const arrowMesh = createAsphaltArrowMesh(-5.0, az, 0);
    roadMarkGroup.add(arrowMesh);
});

layoutGroup.add(roadMarkGroup);

// ─────────────────────────────────────────────────────────────────────────────
// MONUMEN GERBANG AKSES UTAMA BANDARA & LANDSCAPING (MAIN AIRPORT ENTRANCE PORTAL)
// Berdiri kokoh di ujung selatan jalan akses (Z = -140.0), menutup ujung jalan secara elegan
// ─────────────────────────────────────────────────────────────────────────────
const mainEntranceGroup = new THREE.Group();
mainEntranceGroup.position.set(0, 0, -140.0);

// 1. Portal Rangka Baja Arsitektural (Overhead Gantry Arch)
[-16.2, 8.2].forEach(px => {
    // Pedestal Beton
    const basePylon = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.8, 1.6), MAT.concreteCurb);
    basePylon.position.set(px, 0.4, 0);
    basePylon.castShadow = true;
    basePylon.receiveShadow = true;
    mainEntranceGroup.add(basePylon);

    // Tiang Portal Baja Galvanis
    const colMesh = new THREE.Mesh(new THREE.BoxGeometry(0.7, 7.6, 0.7), MAT.roofTruss);
    colMesh.position.set(px, 4.4, 0);
    colMesh.castShadow = true;
    mainEntranceGroup.add(colMesh);

    // Aksen Brand InJourney Teal di Kolom
    const accentCol = new THREE.Mesh(new THREE.BoxGeometry(0.75, 1.8, 0.75), MAT.injourneyTeal);
    accentCol.position.set(px, 4.4, 0);
    mainEntranceGroup.add(accentCol);
});

// Balok Melintang Portal Utama (Span Lebar 25.4m dari Barat ke Timur)
const mainSpan = new THREE.Mesh(new THREE.BoxGeometry(25.4, 1.4, 1.2), MAT.roofTruss);
mainSpan.position.set(-4.0, 7.8, 0);
mainSpan.castShadow = true;
mainEntranceGroup.add(mainSpan);

// Kanopi Atap Lengkung Futuristik InJourney di Atas Balok
const canopyTop = new THREE.Mesh(new THREE.BoxGeometry(26.2, 0.25, 3.2), MAT.injourneyTeal);
canopyTop.position.set(-4.0, 8.6, 0);
canopyTop.castShadow = true;
mainEntranceGroup.add(canopyTop);

// Array untuk Animasi Kibaran Bendera Realistis di Render Loop
const animatedFlags = [];

// Helper Membuat Tekstur Terbalik Horizontal untuk Sisi Belakang Bendera (Agar Terbaca Normal dari Kedua Arah)
function makeFlippedTexture(srcCanvas) {
    const c = document.createElement('canvas');
    c.width = srcCanvas.width;
    c.height = srcCanvas.height;
    const ctx = c.getContext('2d');
    ctx.translate(c.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(srcCanvas, 0, 0);
    const tex = new THREE.CanvasTexture(c);
    tex.wrapS = THREE.ClampToEdgeWrapping;
    tex.wrapT = THREE.ClampToEdgeWrapping;
    return tex;
}

// Helper Membuat Bendera Kain Dua Sisi (Dual-Sided Non-Mirrored Waved Cloth)
function createDualSidedWavingFlag(texFront, texBack, width = 1.95, height = 1.30, phaseOffset = 0) {
    const flagGroup = new THREE.Group();

    // Sisi Depan (Front Face)
    const geoFront = new THREE.PlaneGeometry(width, height, 24, 12);
    geoFront.translate(width / 2, 0, 0); // Titik pivot di tiang (x = 0)

    const posF = geoFront.attributes.position;
    for (let i = 0; i < posF.count; i++) {
        const u = posF.getX(i) / width;
        const ripple = Math.sin(u * 5.2 + phaseOffset) * (0.12 * u);
        posF.setZ(i, ripple);
    }
    geoFront.computeVertexNormals();

    const matFront = new THREE.MeshStandardMaterial({
        map: texFront,
        side: THREE.FrontSide,
        roughness: 0.65,
        metalness: 0.05
    });
    const meshFront = new THREE.Mesh(geoFront, matFront);
    meshFront.position.z = 0.003;
    meshFront.castShadow = true;
    flagGroup.add(meshFront);

    // Sisi Belakang (Back Face — Menggunakan tekstur pre-flipped sehingga saat dilihat dari belakang teks tetap terbaca normal!)
    const geoBack = geoFront.clone();
    const matBack = new THREE.MeshStandardMaterial({
        map: texBack,
        side: THREE.BackSide,
        roughness: 0.65,
        metalness: 0.05
    });
    const meshBack = new THREE.Mesh(geoBack, matBack);
    meshBack.position.z = -0.003;
    meshBack.castShadow = true;
    flagGroup.add(meshBack);

    animatedFlags.push({
        front: meshFront,
        back: meshBack,
        width: width,
        phase: phaseOffset
    });

    return flagGroup;
}

// ─────────────────────────────────────────────────────────────────────────────
// PAPAN NAMA SIGNBOARD GERBANG "SELAMAT DATANG" (DUAL-VIEW HD CANVAS TEXTURE)
// Menampilkan tulisan SELAMAT DATANG yang megah di KEDUA sisi gerbang
// ─────────────────────────────────────────────────────────────────────────────

// 1. Sisi Luar (Menghadap Lalu Lintas Masuk / Inbound dari Arah Luar -Z)
const signCanvasInbound = document.createElement('canvas');
signCanvasInbound.width = 2048;
signCanvasInbound.height = 256;
const sci = signCanvasInbound.getContext('2d');

const gradIn = sci.createLinearGradient(0, 0, 2048, 0);
gradIn.addColorStop(0, '#013D43');
gradIn.addColorStop(0.2, '#014D54');
gradIn.addColorStop(0.5, '#087F8A');
gradIn.addColorStop(0.8, '#0CA1AF');
gradIn.addColorStop(1, '#013D43');
sci.fillStyle = gradIn;
sci.fillRect(0, 0, 2048, 256);

sci.strokeStyle = '#FACC15';
sci.lineWidth = 10;
sci.strokeRect(10, 10, 2028, 236);
sci.strokeStyle = 'rgba(255, 255, 255, 0.4)';
sci.lineWidth = 3;
sci.strokeRect(22, 22, 2004, 212);

sci.fillStyle = '#FEF08A';
sci.font = 'bold 36px sans-serif';
sci.textAlign = 'center';
sci.fillText('✈ INJOURNEY AIRPORTS • LOGISTICS & INTERMODAL HUB', 1024, 68);

sci.fillStyle = '#FFFFFF';
sci.font = '900 86px sans-serif';
sci.shadowColor = 'rgba(0, 0, 0, 0.7)';
sci.shadowBlur = 14;
sci.shadowOffsetY = 4;
sci.fillText('SELAMAT DATANG', 1024, 156);

sci.shadowColor = 'transparent';
sci.fillStyle = '#E0F2FE';
sci.font = 'bold 40px sans-serif';
sci.fillText('AIR CARGO INTERMODAL TERMINAL', 1024, 216);

const signTexInbound = new THREE.CanvasTexture(signCanvasInbound);
const signMeshInbound = new THREE.Mesh(
    new THREE.PlaneGeometry(21.0, 1.25),
    new THREE.MeshBasicMaterial({ map: signTexInbound })
);
signMeshInbound.rotation.y = Math.PI; // Menghadap arah luar (-Z) ke arah jalan kedatangan truk
signMeshInbound.position.set(-4.0, 7.8, -0.62);
mainEntranceGroup.add(signMeshInbound);

// 2. Sisi Dalam (Menghadap ke Dalam Kawasan Terminal / +Z)
const signCanvasOutbound = document.createElement('canvas');
signCanvasOutbound.width = 2048;
signCanvasOutbound.height = 256;
const sco = signCanvasOutbound.getContext('2d');

const gradOut = sco.createLinearGradient(0, 0, 2048, 0);
gradOut.addColorStop(0, '#0B1736');
gradOut.addColorStop(0.3, '#014D54');
gradOut.addColorStop(0.7, '#087F8A');
gradOut.addColorStop(1, '#0B1736');
sco.fillStyle = gradOut;
sco.fillRect(0, 0, 2048, 256);

sco.strokeStyle = '#FACC15';
sco.lineWidth = 10;
sco.strokeRect(10, 10, 2028, 236);
sco.strokeStyle = 'rgba(255, 255, 255, 0.4)';
sco.lineWidth = 3;
sco.strokeRect(22, 22, 2004, 212);

sco.fillStyle = '#FEF08A';
sco.font = 'bold 36px sans-serif';
sco.textAlign = 'center';
sco.fillText('✈ AIR CARGO INTERMODAL TERMINAL', 1024, 68);

sco.fillStyle = '#FFFFFF';
sco.font = '900 86px sans-serif';
sco.shadowColor = 'rgba(0, 0, 0, 0.7)';
sco.shadowBlur = 14;
sco.shadowOffsetY = 4;
sco.fillText('SELAMAT DATANG', 1024, 156);

sco.shadowColor = 'transparent';
sco.fillStyle = '#E0F2FE';
sco.font = 'bold 40px sans-serif';
sco.fillText('PINTU GERBANG UTAMA • INJOURNEY AIRPORTS', 1024, 216);

const signTexOutbound = new THREE.CanvasTexture(signCanvasOutbound);
const signMeshOutbound = new THREE.Mesh(
    new THREE.PlaneGeometry(21.0, 1.25),
    new THREE.MeshBasicMaterial({ map: signTexOutbound })
);
signMeshOutbound.position.set(-4.0, 7.8, 0.62); // Menghadap arah dalam (+Z)
mainEntranceGroup.add(signMeshOutbound);

// Lampu Sorot LED Portal (Downlights)
const gantryLight = new THREE.PointLight(0xFFFFFF, 1.2, 35);
gantryLight.position.set(-4.0, 7.0, 1.5);
mainEntranceGroup.add(gantryLight);

// ─────────────────────────────────────────────────────────────────────────────
// PLAZA MONUMEN 3 TIANG BENDERA RESMI (INDONESIA, INJOURNEY, BUMN)
// Tertata rapi, megah, proporsional, dan berkibar alami ditiup angin
// ─────────────────────────────────────────────────────────────────────────────

// 1. Texture Bendera Merah Putih (Proporsi Resmi Kenegaraan 3:2)
const idFlagCanvas = document.createElement('canvas');
idFlagCanvas.width = 512;
idFlagCanvas.height = 341;
const idCtx = idFlagCanvas.getContext('2d');
idCtx.fillStyle = '#DC2626'; // Merah Bendera RI
idCtx.fillRect(0, 0, 512, 171);
idCtx.fillStyle = '#FFFFFF'; // Putih Bendera RI
idCtx.fillRect(0, 170, 512, 171);
idCtx.strokeStyle = 'rgba(0, 0, 0, 0.12)';
idCtx.lineWidth = 2;
idCtx.strokeRect(0, 0, 512, 341);
const idFlagTex = new THREE.CanvasTexture(idFlagCanvas);
const idFlagTexRev = makeFlippedTexture(idFlagCanvas);

// 2. Texture Bendera Korporat InJourney Airports
const ijFlagCanvas = document.createElement('canvas');
ijFlagCanvas.width = 512;
ijFlagCanvas.height = 341;
const ijCtx = ijFlagCanvas.getContext('2d');
const ijGrad = ijCtx.createLinearGradient(0, 0, 512, 341);
ijGrad.addColorStop(0, '#014D54');
ijGrad.addColorStop(0.5, '#087F8A');
ijGrad.addColorStop(1, '#0CA1AF');
ijCtx.fillStyle = ijGrad;
ijCtx.fillRect(0, 0, 512, 341);
ijCtx.strokeStyle = '#FACC15';
ijCtx.lineWidth = 8;
ijCtx.strokeRect(8, 8, 496, 325);
ijCtx.fillStyle = '#FFFFFF';
ijCtx.font = 'bold 42px sans-serif';
ijCtx.textAlign = 'center';
ijCtx.fillText('injourney', 256, 145);
ijCtx.font = 'bold 26px sans-serif';
ijCtx.fillStyle = '#E0F2FE';
ijCtx.fillText('AIRPORTS', 256, 192);
ijCtx.font = 'bold 15px sans-serif';
ijCtx.fillStyle = '#FACC15';
ijCtx.fillText('INDONESIA AVIATION & TOURISM', 256, 235);
const ijFlagTex = new THREE.CanvasTexture(ijFlagCanvas);
const ijFlagTexRev = makeFlippedTexture(ijFlagCanvas);

// 3. Texture Bendera BUMN / Kargo Logistik
const bumnFlagCanvas = document.createElement('canvas');
bumnFlagCanvas.width = 512;
bumnFlagCanvas.height = 341;
const bCtx = bumnFlagCanvas.getContext('2d');
bCtx.fillStyle = '#0D1C42';
bCtx.fillRect(0, 0, 512, 341);
bCtx.strokeStyle = '#0CA1AF';
bCtx.lineWidth = 8;
bCtx.strokeRect(8, 8, 496, 325);
bCtx.fillStyle = '#FFFFFF';
bCtx.font = 'bold 44px sans-serif';
bCtx.textAlign = 'center';
bCtx.fillText('BUMN', 256, 140);
bCtx.font = 'bold 22px sans-serif';
bCtx.fillStyle = '#38BDF8';
bCtx.fillText('AIR CARGO LOGISTICS', 256, 185);
bCtx.font = 'bold 15px sans-serif';
bCtx.fillStyle = '#94A3B8';
bCtx.fillText('INTERMODAL TERMINAL', 256, 225);
const bumnFlagTex = new THREE.CanvasTexture(bumnFlagCanvas);
const bumnFlagTexRev = makeFlippedTexture(bumnFlagCanvas);

// Podium Monumental Bertingkat di Sisi Barat Luar Gerbang (X = -21.0, Z = -1.5)
const flagPlazaGroup = new THREE.Group();
flagPlazaGroup.position.set(-21.0, 0, -1.5);

// Tingkat 1: Pedestal Beton Arsitektural
const podT1 = new THREE.Mesh(new THREE.BoxGeometry(6.6, 0.35, 3.4), MAT.concreteCurb);
podT1.position.set(0, 0.175, 0);
podT1.receiveShadow = true;
flagPlazaGroup.add(podT1);

// Tingkat 2: Granit Gelap & Trim Aksen InJourney Teal
const podT2 = new THREE.Mesh(new THREE.BoxGeometry(6.0, 0.25, 2.8), MAT.metalDark);
podT2.position.set(0, 0.45, 0);
podT2.receiveShadow = true;
flagPlazaGroup.add(podT2);

const podTrim = new THREE.Mesh(new THREE.BoxGeometry(6.05, 0.06, 2.85), MAT.injourneyTeal);
podTrim.position.set(0, 0.58, 0);
flagPlazaGroup.add(podTrim);

// Konfigurasi 3 Tiang Bendera:
// - Kiri: InJourney Airports (Tinggi 10.0m)
// - Tengah: Merah Putih Indonesia (Tinggi 11.4m, Tertinggi & Gagah di pusat kehormatan)
// - Kanan: BUMN Kargo Logistik (Tinggi 10.0m)
const flagSpecs = [
    {
        name: 'InJourney',
        relX: -2.2,
        poleHeight: 10.0,
        flagTopY: 8.9,
        texFront: ijFlagTex,
        texBack: ijFlagTexRev,
        phase: 0.5
    },
    {
        name: 'Indonesia',
        relX: 0.0,
        poleHeight: 11.4,
        flagTopY: 10.3,
        texFront: idFlagTex,
        texBack: idFlagTexRev,
        phase: 0.0
    },
    {
        name: 'BUMN',
        relX: 2.2,
        poleHeight: 10.0,
        flagTopY: 8.9,
        texFront: bumnFlagTex,
        texBack: bumnFlagTexRev,
        phase: 1.0
    }
];

const goldBallMat = new THREE.MeshStandardMaterial({ color: 0xF59E0B, metalness: 0.88, roughness: 0.18 });

flagSpecs.forEach(spec => {
    // Dudukan Flange Tiang Stainless Steel
    const fFlange = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.32, 0.18, 16), MAT.metalSilver);
    fFlange.position.set(spec.relX, 0.65, 0);
    fFlange.castShadow = true;
    flagPlazaGroup.add(fFlange);

    // Tiang Bendera Tirus Stainless Steel Berkilau
    const fPole = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.075, spec.poleHeight, 16), MAT.metalSilver);
    fPole.position.set(spec.relX, 0.65 + spec.poleHeight / 2, 0);
    fPole.castShadow = true;
    flagPlazaGroup.add(fPole);

    // Bola Pucuk Tiang Emas (Gold Brass Finial Ball)
    const fBall = new THREE.Mesh(new THREE.SphereGeometry(0.13, 16, 16), goldBallMat);
    fBall.position.set(spec.relX, 0.65 + spec.poleHeight + 0.12, 0);
    flagPlazaGroup.add(fBall);

    // Tali Kerekan Bendera (Halyard)
    const fRope = new THREE.Mesh(new THREE.CylinderGeometry(0.012, 0.012, spec.poleHeight - 0.5, 6), MAT.metalDark);
    fRope.position.set(spec.relX + 0.08, 0.65 + (spec.poleHeight - 0.5) / 2, 0);
    flagPlazaGroup.add(fRope);

    // Bendera Berkibar Alami Dua Sisi (Dual-Sided Non-Mirrored Waved Cloth) — Terbaca Normal dari Kedua Arah
    const flagObj = createDualSidedWavingFlag(spec.texFront, spec.texBack, 1.95, 1.30, spec.phase);
    flagObj.position.set(spec.relX + 0.05, spec.flagTopY, 0);
    flagObj.rotation.y = Math.PI * 0.38; // Berkibar mengarah ke depan (+Z)
    flagPlazaGroup.add(flagObj);

    // Lampu Sorot Bawah Podium (Ground Uplight Fixture)
    const spotFixture = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.14, 0.15, 12), MAT.metalDark);
    spotFixture.position.set(spec.relX, 0.65, 0.6);
    flagPlazaGroup.add(spotFixture);

    const uplight = new THREE.PointLight(0xFFF7ED, 0.85, 14);
    uplight.position.set(spec.relX, 1.2, 0.7);
    flagPlazaGroup.add(uplight);
});

mainEntranceGroup.add(flagPlazaGroup);

// 4. Rambu Delineator Reflektif Penutup Ujung Jalan
[-15.0, 6.8].forEach(dx => {
    const delPost = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 1.2, 8), MAT.metalDark);
    delPost.position.set(dx, 0.6, 0.5);
    mainEntranceGroup.add(delPost);

    const chevron = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.8, 0.05), new THREE.MeshBasicMaterial({ color: 0xFACC15 }));
    chevron.position.set(dx, 0.9, 0.5);
    mainEntranceGroup.add(chevron);
});

// 5. Pohon Palem Tropis Hias di Bahu Jalan
[-19.5, 11.5].forEach(lx => {
    for (let lz = 10; lz <= 60; lz += 15) {
        const palmGroup = new THREE.Group();
        palmGroup.position.set(lx, 0, lz);

        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.26, 5.5, 8), new THREE.MeshStandardMaterial({ color: 0x78350F, roughness: 0.9 }));
        trunk.position.y = 2.75;
        trunk.castShadow = true;
        palmGroup.add(trunk);

        for (let a = 0; a < 6; a++) {
            const frondAngle = (a / 6) * Math.PI * 2;
            const frond = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.08, 2.8), new THREE.MeshStandardMaterial({ color: 0x15803D, roughness: 0.6 }));
            frond.position.set(Math.sin(frondAngle) * 1.2, 5.3, Math.cos(frondAngle) * 1.2);
            frond.rotation.y = frondAngle;
            frond.rotation.x = 0.35;
            palmGroup.add(frond);
        }
        mainEntranceGroup.add(palmGroup);
    }
});

layoutGroup.add(mainEntranceGroup);

// ─────────────────────────────────────────────────────────────────────────────
// POS PEMERIKSAAN KEAMANAN & GERBANG MASUK TERPADU (SECURITY GATEHOUSE COMPLEX)
// ─────────────────────────────────────────────────────────────────────────────
const gateHouse = new THREE.Group();
gateHouse.position.set(0, 0, -72.0);

// 1. Pulau Pengaman Jalan Beton (Traffic Median Island di X = -0.5)
const islandBase = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.22, 10.0), MAT.concreteCurb);
islandBase.position.set(-0.5, 0.11, 0);
islandBase.receiveShadow = true;
gateHouse.add(islandBase);

// Ujung Bulat Pulau dengan Marka Reflektif Kuning
[-5.0, 5.0].forEach(iz => {
    const cap = new THREE.Mesh(new THREE.CylinderGeometry(1.2, 1.2, 0.22, 16), MAT.yellowSafety);
    cap.position.set(-0.5, 0.11, iz);
    gateHouse.add(cap);
});

// 2. Bangunan Pos Jaga Modern (Glass Security Booth)
const ghBuilding = new THREE.Mesh(new THREE.BoxGeometry(2.2, 3.0, 4.4), MAT.wallBuilding);
ghBuilding.position.set(-0.5, 1.6, 0);
ghBuilding.castShadow = true;
ghBuilding.receiveShadow = true;

const ghRoof = new THREE.Mesh(new THREE.BoxGeometry(3.2, 0.3, 5.4), MAT.metalDark);
ghRoof.position.set(-0.5, 3.25, 0);
ghRoof.castShadow = true;

// Jendela Kaca Panoramik
const ghFrontWin = new THREE.Mesh(new THREE.BoxGeometry(1.9, 1.3, 0.08), MAT.wallGlass);
ghFrontWin.position.set(-0.5, 1.8, -2.22);
const ghSideWinL = new THREE.Mesh(new THREE.BoxGeometry(0.08, 1.3, 3.2), MAT.wallGlass);
ghSideWinL.position.set(-1.62, 1.8, 0);
const ghSideWinR = new THREE.Mesh(new THREE.BoxGeometry(0.08, 1.3, 3.2), MAT.wallGlass);
ghSideWinR.position.set(0.62, 1.8, 0);

// Unit AC Outdoor & Kamera CCTV di Atas Pos
const acUnit = new THREE.Mesh(new THREE.BoxGeometry(0.8, 0.6, 0.45), MAT.metalSilver);
acUnit.position.set(-0.5, 3.7, 1.2);
const cctvPost = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 0.5), MAT.metalDark);
cctvPost.position.set(-0.5, 3.65, -2.4);
const cctvDome = new THREE.Mesh(new THREE.SphereGeometry(0.12, 12, 12), MAT.hazardBlack);
cctvDome.position.set(-0.5, 3.35, -2.4);

// 3. Palang Pintu Otomatis Masuk Truk (Inbound Boom Barrier di X = -1.8)
const boomPostIn = new THREE.Mesh(new THREE.BoxGeometry(0.4, 1.2, 0.4), MAT.safetyOrange);
boomPostIn.position.set(-1.8, 0.7, -1.5);
const boomArmIn = new THREE.Mesh(new THREE.BoxGeometry(7.2, 0.12, 0.12), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
boomArmIn.position.set(-5.4, 1.15, -1.5);

// Palang Pintu Otomatis Keluar (Outbound Boom Barrier di X = +0.8)
const boomPostOut = new THREE.Mesh(new THREE.BoxGeometry(0.4, 1.2, 0.4), MAT.safetyOrange);
boomPostOut.position.set(0.8, 0.7, 1.5);
const boomArmOut = new THREE.Mesh(new THREE.BoxGeometry(6.0, 0.12, 0.12), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
boomArmOut.position.set(3.8, 1.15, 1.5);

// 4. Portal Rangka Baja Gantry Informasi Gerbang (Overhead Gantry di Z = -74.0)
const gantryGroup = new THREE.Group();
gantryGroup.position.set(0, 0, -2.0);

[-14.8, 6.8].forEach(gx => {
    const pylon = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.22, 6.4, 12), MAT.roofTruss);
    pylon.position.set(gx, 3.2, 0);
    pylon.castShadow = true;
    gantryGroup.add(pylon);
});

const crossBeam = new THREE.Mesh(new THREE.BoxGeometry(22.0, 0.35, 0.35), MAT.roofTruss);
crossBeam.position.set(-4.0, 6.2, 0);
crossBeam.castShadow = true;
gantryGroup.add(crossBeam);

// Papan Signboard Digital LED Gantry
const signCanvas = document.createElement('canvas');
signCanvas.width = 1024;
signCanvas.height = 128;
const sctx = signCanvas.getContext('2d');
sctx.fillStyle = '#0D1C42';
sctx.fillRect(0, 0, 1024, 128);
sctx.fillStyle = '#0CA1AF';
sctx.fillRect(0, 0, 1024, 8);
sctx.fillStyle = '#38BDF8';
sctx.font = 'bold 36px Barlow, sans-serif';
sctx.textAlign = 'center';
sctx.fillText('INJOURNEY AIR CARGO TERMINAL • SECURITY CHECKPOINT', 512, 50);
sctx.fillStyle = '#FACC15';
sctx.font = 'bold 24px monospace';
sctx.fillText('▼ LANE 1: INBOUND TRUCK [RFID SCAN]     |     ▲ LANE 2: OUTBOUND', 512, 98);

const signTex = new THREE.CanvasTexture(signCanvas);
const signMat = new THREE.MeshBasicMaterial({ map: signTex });
const signBoard = new THREE.Mesh(new THREE.PlaneGeometry(16.0, 2.0), signMat);
signBoard.position.set(-4.0, 5.0, 0.2);
gantryGroup.add(signBoard);

// Lampu Sinyal Hijau/Merah Overhead di Atas Tiap Lajur
const laneSignalIn = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.6, 0.2), MAT.safetyGreen);
laneSignalIn.position.set(-5.0, 5.8, 0.3);
const laneSignalOut = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.6, 0.2), MAT.safetyGreen);
laneSignalOut.position.set(3.0, 5.8, 0.3);
gantryGroup.add(laneSignalIn, laneSignalOut);

// Tiang Sensor Pemindai Kartu RFID (RFID Reader Gantry Post)
const rfidPost = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 2.4), MAT.yellowSafety);
rfidPost.position.set(-9.2, 1.2, 0);
const rfidHead = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.45, 0.2), MAT.injourneyTeal);
rfidHead.position.set(-9.2, 2.2, 0);
gantryGroup.add(rfidPost, rfidHead);

gateHouse.add(ghBuilding, ghRoof, ghFrontWin, ghSideWinL, ghSideWinR, acUnit, cctvPost, cctvDome, boomPostIn, boomArmIn, boomPostOut, boomArmOut, gantryGroup);
layoutGroup.add(gateHouse);

// ─────────────────────────────────────────────────────────────────────────────
// TIANG LAMPU JALAN PJU BANDARA (MODERN AIRPORT LED STREETLIGHTS)
// ─────────────────────────────────────────────────────────────────────────────
function createAirportStreetLight(x, z, armAngle = 0) {
    const lightPole = new THREE.Group();
    lightPole.position.set(x, 0, z);

    // Fondasi Beton Silindris
    const base = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.4, 0.45, 12), MAT.concreteCurb);
    base.position.y = 0.225;
    lightPole.add(base);

    // Tiang Baja Tapered Galvanis
    const mast = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.2, 8.5, 12), MAT.roofTruss);
    mast.position.y = 4.7;
    mast.castShadow = true;
    lightPole.add(mast);

    // Lengan Kantilever Melengkung & Rumah Lampu LED
    const armGroup = new THREE.Group();
    armGroup.position.set(0, 8.8, 0);
    armGroup.rotation.y = armAngle;

    const arm = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.07, 2.4), MAT.roofTruss);
    arm.rotation.z = Math.PI / 4;
    arm.position.set(0.85, 0.4, 0);

    const lampHead = new THREE.Mesh(new THREE.BoxGeometry(1.1, 0.16, 0.45), MAT.metalDark);
    lampHead.position.set(1.85, 0.9, 0);

    const lampGlass = new THREE.Mesh(new THREE.PlaneGeometry(0.9, 0.35), new THREE.MeshBasicMaterial({ color: 0xFEF08A }));
    lampGlass.rotation.x = Math.PI / 2;
    lampGlass.position.set(1.85, 0.81, 0);

    armGroup.add(arm, lampHead, lampGlass);
    lightPole.add(armGroup);

    // Soft Light Glow
    const streetPnt = new THREE.PointLight(0xFEF08A, 0.7, 24);
    streetPnt.position.set(x + Math.sin(armAngle) * 1.8, 8.0, z + Math.cos(armAngle) * 1.8);
    layoutGroup.add(streetPnt);

    return lightPole;
}

[
    { x: -15.8, z: -130.0, a: -Math.PI / 2 },
    { x: -15.8, z: -105.0, a: -Math.PI / 2 },
    { x: -15.8, z: -80.0,  a: -Math.PI / 2 },
    { x: 7.8,   z: -130.0, a: Math.PI / 2 },
    { x: 7.8,   z: -105.0, a: Math.PI / 2 },
    { x: 7.8,   z: -80.0,  a: Math.PI / 2 },
    { x: -46.5, z: -55.0,  a: -Math.PI / 2 },
    { x: 38.5,  z: -55.0,  a: Math.PI / 2 }
].forEach(sp => {
    layoutGroup.add(createAirportStreetLight(sp.x, sp.z, sp.a));
});

// ─────────────────────────────────────────────────────────────────────────────
// GANJALAN RODA DERMAGA MUAT TRUK (RUBBER WHEEL STOPPERS DI BAY 1 - 3)
// ─────────────────────────────────────────────────────────────────────────────
[-26.0, -5.0, 22.0].forEach(bx => {
    [-1.6, 1.6].forEach(ox => {
        const chock = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.16, 1.8), MAT.metalDark);
        chock.position.set(bx + ox, 0.08, -38.5);
        chock.receiveShadow = true;
        layoutGroup.add(chock);

        const chockStripe = new THREE.Mesh(new THREE.BoxGeometry(0.32, 0.08, 0.4), MAT.yellowSafety);
        chockStripe.position.set(bx + ox, 0.08, -38.5);
        layoutGroup.add(chockStripe);
    });
});

// 4. Lantai Epoksi Terminal Kargo Indoor (High-Gloss Epoxy Floor 88m x 64.75m)
const termGeo = new THREE.PlaneGeometry(88, 64.75);
const termFloor = new THREE.Mesh(termGeo, MAT.floorTerminal);
termFloor.rotation.x = -Math.PI / 2;
termFloor.position.set(-6, 0.02, -3.375);
termFloor.receiveShadow = true;
layoutGroup.add(termFloor);

// ─────────────────────────────────────────────────────────────────────────────
// DINDING DERMAGA UTARA MODULAR & PINTU SECTIONAL (NORTH DOCK WALL & SEALS)
// ─────────────────────────────────────────────────────────────────────────────
// Bangunan membentang dari X = -50.0 sampai X = 38.0 (Lebar 88m, Z = -35.75)
// Bukaan Pintu Dermaga (Dock Portals) di Bay 1 (X = -26), Bay 2 (X = -5), Bay 3 (X = 22)
const northDockGroup = new THREE.Group();
northDockGroup.position.set(0, 0, -35.75);

// 1. Dinding Infill Cladding & Base Plinth Antara Bukaan Dermaga
const dockWallSlices = [
    { x: -39.1, w: 21.8 }, // Sisi Barat ke Bay 1 (-50 s.d. -28.2)
    { x: -15.5, w: 16.6 }, // Antara Bay 1 dan Bay 2 (-23.8 s.d. -7.2)
    { x: 8.5,   w: 22.6 }, // Antara Bay 2 dan Bay 3 (-2.8 s.d. 19.8)
    { x: 31.1,  w: 13.8 }  // Sisi Timur setelah Bay 3 (24.2 s.d. 38.0)
];

dockWallSlices.forEach(slice => {
    // Base Plinth Beton Gelap Tahan Benturan
    const plinth = new THREE.Mesh(new THREE.BoxGeometry(slice.w, 0.8, 0.5), MAT.wallBasePlinth);
    plinth.position.set(slice.x, 0.4, 0);
    plinth.receiveShadow = true;

    // Panel Dinding Cladding Berinsulasi
    const panel = new THREE.Mesh(new THREE.BoxGeometry(slice.w, 3.6, 0.4), MAT.wallPanel);
    panel.position.set(slice.x, 2.6, 0);
    panel.receiveShadow = true;

    // Reveal Joint Garis Shadow Horizontal Metalik
    [1.6, 2.8, 4.0].forEach(jy => {
        const joint = new THREE.Mesh(new THREE.BoxGeometry(slice.w + 0.05, 0.04, 0.42), MAT.metalDark);
        joint.position.set(slice.x, jy, 0);
        northDockGroup.add(joint);
    });

    northDockGroup.add(plinth, panel);
});

// 2. Dinding Header Atas Menerus di Atas Semua Dermaga (Y = 4.4 s.d. 7.5, Tinggi 3.1m)
const headerWall = new THREE.Mesh(new THREE.BoxGeometry(88.0, 3.1, 0.4), MAT.wallBuilding);
headerWall.position.set(-6.0, 5.95, 0);
headerWall.receiveShadow = true;

// Parapet Fascia Atas InJourney Navy & Top Coping Trim Cyan
const topFascia = new THREE.Mesh(new THREE.BoxGeometry(88.2, 0.75, 0.46), MAT.planeNavy);
topFascia.position.set(-6.0, 7.15, 0);
const topCornice = new THREE.Mesh(new THREE.BoxGeometry(88.4, 0.14, 0.5), MAT.injourneyTeal);
topCornice.position.set(-6.0, 7.55, 0);
northDockGroup.add(headerWall, topFascia, topCornice);

// 3. Pintu Sectional Industri Overhead & Bantalan Karet Dock Seals
[-26, -5, 22].forEach((dx, idx) => {
    const isBay2Active = (dx === -5);

    // Karet Bantalan Dermaga Samping (Rubber Inflatable Dock Flaps)
    [-2.25, 2.25].forEach(px => {
        const flap = new THREE.Mesh(new THREE.BoxGeometry(0.48, 4.3, 0.38), MAT.hazardBlack);
        flap.position.set(dx + px, 2.2, -0.22);
        const flapStripe = new THREE.Mesh(new THREE.BoxGeometry(0.08, 4.2, 0.4), MAT.yellowSafety);
        flapStripe.position.set(dx + px, 2.2, -0.23);
        northDockGroup.add(flap, flapStripe);
    });

    // Top Drape Canopy Karet Pelindung Hujan
    const topDrape = new THREE.Mesh(new THREE.BoxGeometry(4.9, 0.65, 0.38), MAT.hazardBlack);
    topDrape.position.set(dx, 4.15, -0.22);
    northDockGroup.add(topDrape);

    // Pintu Sectional Industri Roll-Up:
    if (isBay2Active) {
        // Bay 2 (Truk Aktif Simulasi): Pintu tergulung penuh ke atas sehingga terbuka bebas untuk konveyor kargo
        const rollDrum = new THREE.Mesh(new THREE.CylinderGeometry(0.32, 0.32, 4.3, 16), MAT.metalSilver);
        rollDrum.rotateZ(Math.PI / 2);
        rollDrum.position.set(dx, 4.4, 0);
        northDockGroup.add(rollDrum);
    } else {
        // Bay 1 & Bay 3 (Truk Terparkir): Pintu sectional semi-tertutup dengan panel jendela
        const doorLeaf = new THREE.Mesh(new THREE.BoxGeometry(4.3, 3.8, 0.08), MAT.metalSilver);
        doorLeaf.position.set(dx, 2.0, 0);
        const botStripe = new THREE.Mesh(new THREE.BoxGeometry(4.3, 0.3, 0.09), MAT.yellowSafety);
        botStripe.position.set(dx, 0.25, 0);
        const viewSlat = new THREE.Mesh(new THREE.BoxGeometry(3.6, 0.35, 0.1), MAT.wallGlass);
        viewSlat.position.set(dx, 2.6, 0);
        northDockGroup.add(doorLeaf, botStripe, viewSlat);
    }

    // Signboard Papan Nama Dermaga di Atas Tiap Pintu
    const bayLabels = [
        { title: "DOCK BAY 01", sub: "PHARMA & COLD CHAIN", color: "#38BDF8" },
        { title: "DOCK BAY 02", sub: "INBOUND CARGO [ACTIVE]", color: "#10B981" },
        { title: "DOCK BAY 03", sub: "GENERAL EXPORT LOGISTICS", color: "#F59E0B" }
    ];
    const bInfo = bayLabels[idx];
    const baySign = makeMountedSignboard(bInfo.title, bInfo.sub, 5.2, 0.95, bInfo.color);
    baySign.position.set(dx, 5.2, -0.25);
    signsGroup.add(baySign);
});

layoutGroup.add(northDockGroup);

// Kolom Baja Struktural Bangunan Terminal dengan Bumper Kuning & APAR Fire Safety
[-38, -20, 5, 25].forEach((cx, cIdx) => {
    [-20, 0, 18].forEach((cz, rIdx) => {
        const col = new THREE.Mesh(new THREE.BoxGeometry(0.5, 7.5, 0.5), MAT.metalDark);
        col.position.set(cx, 3.75, cz);
        const colBase = new THREE.Mesh(new THREE.BoxGeometry(0.95, 0.65, 0.95), MAT.yellowSafety);
        colBase.position.set(cx, 0.325, cz);

        // Garis Bahaya Hitam pada Bumper Kolom
        const colStripe = new THREE.Mesh(new THREE.BoxGeometry(0.97, 0.18, 0.97), MAT.hazardBlack);
        colStripe.position.set(cx, 0.325, cz);
        layoutGroup.add(col, colBase, colStripe);

        // Tabung APAR (Alat Pemadam Api Ringan) pada Kolom Terpilih
        if ((cIdx + rIdx) % 2 === 0) {
            const aparBox = new THREE.Mesh(new THREE.BoxGeometry(0.24, 0.65, 0.15), MAT.redHazard);
            aparBox.position.set(cx + 0.32, 1.4, cz);
            const aparSign = new THREE.Mesh(new THREE.BoxGeometry(0.28, 0.18, 0.05), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
            aparSign.position.set(cx + 0.32, 1.9, cz);
            layoutGroup.add(aparBox, aparSign);
        }
    });
});

// Menara Lampu Sorot Apron (Apron High-Mast Light Tower di X = 40, Z = 35)
const mastTower = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.48, 28, 12), MAT.metalSilver);
mastTower.position.set(40, 14, 35);
const mastHead = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.6, 1.4), MAT.metalDark);
mastHead.position.set(40, 28, 35);
const floodMesh1 = new THREE.Mesh(new THREE.PlaneGeometry(2.2, 0.5), new THREE.MeshBasicMaterial({ color: 0xFEF08A }));
floodMesh1.position.set(40, 28, 34.25);
floodMesh1.rotation.y = Math.PI;
layoutGroup.add(mastTower, mastHead, floodMesh1);

// ─────────────────────────────────────────────────────────────────────────────
// 2. PINTU AKSES PERSONEL & PORTAL SISI UDARA
// ─────────────────────────────────────────────────────────────────────────────
function createPersonnelDoor(x, z, rotY = 0, label = 'AKSES PETUGAS') {
    const doorGroup = new THREE.Group();
    doorGroup.position.set(x, 0, z);
    doorGroup.rotation.y = rotY;

    const frameWidth = 1.4;
    const frameHeight = 2.25;
    const postMat = MAT.metalDark;
    const leafMat = new THREE.MeshStandardMaterial({ color: 0x1E293B, metalness: 0.6, roughness: 0.4 });

    [-frameWidth / 2, frameWidth / 2].forEach(fx => {
        const jamb = new THREE.Mesh(new THREE.BoxGeometry(0.12, frameHeight, 0.14), postMat);
        jamb.position.set(fx, frameHeight / 2, 0);
        doorGroup.add(jamb);
    });

    const header = new THREE.Mesh(new THREE.BoxGeometry(frameWidth + 0.12, 0.12, 0.14), postMat);
    header.position.set(0, frameHeight + 0.06, 0);
    doorGroup.add(header);

    const leafW = frameWidth - 0.14;
    const leafH = frameHeight - 0.06;
    const leafGroup = new THREE.Group();
    leafGroup.position.set(-frameWidth / 2 + 0.07, 0, 0);
    leafGroup.rotation.y = 0.26;

    const doorLeaf = new THREE.Mesh(new THREE.BoxGeometry(leafW, leafH, 0.05), leafMat);
    doorLeaf.position.set(leafW / 2, leafH / 2, 0);
    leafGroup.add(doorLeaf);

    const glassWin = new THREE.Mesh(new THREE.BoxGeometry(0.28, 0.85, 0.07), MAT.wallGlass);
    glassWin.position.set(leafW * 0.65, leafH * 0.62, 0);
    leafGroup.add(glassWin);

    doorGroup.add(leafGroup);

    const reader = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.18, 0.06), postMat);
    reader.position.set(frameWidth / 2 + 0.14, 1.25, 0.07);
    const ledIndicator = new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.04, 0.07), new THREE.MeshBasicMaterial({ color: 0x10B981 }));
    ledIndicator.position.set(frameWidth / 2 + 0.14, 1.30, 0.07);
    doorGroup.add(reader, ledIndicator);

    const signBox = new THREE.Mesh(new THREE.BoxGeometry(1.2, 0.22, 0.06), new THREE.MeshStandardMaterial({ color: 0x0CA1AF, roughness: 0.35 }));
    signBox.position.set(0, frameHeight + 0.20, 0);
    doorGroup.add(signBox);

    return doorGroup;
}

function createAirsideCargoPortal(x, z, spanLen = 7.0) {
    const portalGroup = new THREE.Group();
    portalGroup.position.set(x, 0, z);

    const postMat = MAT.metalDark;
    const portalH = 4.8;

    [-spanLen / 2, spanLen / 2].forEach(pz => {
        const post = new THREE.Mesh(new THREE.BoxGeometry(0.45, portalH, 0.45), postMat);
        post.position.set(0, portalH / 2, pz);
        const base = new THREE.Mesh(new THREE.BoxGeometry(0.65, 0.6, 0.65), MAT.metalDark);
        base.position.set(0, 0.3, pz);
        portalGroup.add(post, base);
    });

    const lintel = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.5, spanLen + 0.9), postMat);
    lintel.position.set(0, portalH, 0);
    portalGroup.add(lintel);

    const drum = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.28, spanLen - 0.2, 24), MAT.metalSilver);
    drum.rotateX(Math.PI / 2);
    drum.position.set(0, portalH - 0.45, 0);
    portalGroup.add(drum);

    const curtainH = 0.95;
    const curtain = new THREE.Mesh(new THREE.BoxGeometry(0.08, curtainH, spanLen - 0.4), new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.5 }));
    curtain.position.set(0, portalH - 0.45 - curtainH / 2, 0);
    portalGroup.add(curtain);

    const sign = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.6, Math.min(5.5, spanLen - 1.0)), new THREE.MeshStandardMaterial({ color: 0x0CA1AF, roughness: 0.3 }));
    sign.position.set(0.32, portalH, 0);
    portalGroup.add(sign);

    const beacon = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 0.2, 12), new THREE.MeshBasicMaterial({ color: 0x10B981 }));
    beacon.position.set(0, portalH + 0.35, 0);
    portalGroup.add(beacon);

    return portalGroup;
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. PAGAR PENGAMAN KELILING TERMINAL (AIRPORT PERIMETER SECURITY FENCE)
// ─────────────────────────────────────────────────────────────────────────────
function createPerimeterSecurityFence() {
    const fenceGroup = new THREE.Group();
    const curbMat = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.85 });
    const postMat = MAT.metalDark;
    const railMat = MAT.metalSilver;
    const meshMat = new THREE.MeshStandardMaterial({ color: 0x94A3B8, wireframe: true, transparent: true, opacity: 0.35 });

    const fenceHeight = 2.1;
    const postRadius = 0.055;
    const curbWidth = 0.22;
    const curbHeight = 0.12;

    function addFenceSpan(x1, z1, x2, z2, postSpacing = 3.2) {
        const dx = x2 - x1;
        const dz = z2 - z1;
        const len = Math.hypot(dx, dz);
        if (len < 0.2) return;

        const midX = (x1 + x2) / 2;
        const midZ = (z1 + z2) / 2;
        const angle = Math.atan2(dx, dz);

        const spanGroup = new THREE.Group();
        spanGroup.position.set(midX, 0, midZ);
        spanGroup.rotation.y = angle;

        const curb = new THREE.Mesh(new THREE.BoxGeometry(curbWidth, curbHeight, len), curbMat);
        curb.position.y = curbHeight / 2;
        spanGroup.add(curb);

        const topRail = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, len, 8), railMat);
        topRail.rotateX(Math.PI / 2);
        topRail.position.set(0, fenceHeight - 0.06, 0);

        const midRail = new THREE.Mesh(new THREE.CylinderGeometry(0.028, 0.028, len, 8), railMat);
        midRail.rotateX(Math.PI / 2);
        midRail.position.set(0, fenceHeight * 0.52, 0);

        const botRail = new THREE.Mesh(new THREE.CylinderGeometry(0.028, 0.028, len, 8), railMat);
        botRail.rotateX(Math.PI / 2);
        botRail.position.set(0, 0.24, 0);

        const meshPanelHeight = fenceHeight - 0.32;
        const meshPanel = new THREE.Mesh(new THREE.BoxGeometry(0.02, meshPanelHeight, len), meshMat);
        meshPanel.position.set(0, 0.24 + meshPanelHeight / 2, 0);
        spanGroup.add(curb, topRail, midRail, botRail, meshPanel);

        const numPosts = Math.max(1, Math.round(len / postSpacing));
        for (let k = 0; k <= numPosts; k++) {
            const lz = -len / 2 + (k / numPosts) * len;
            const post = new THREE.Mesh(new THREE.CylinderGeometry(postRadius, postRadius, fenceHeight, 10), postMat);
            post.position.set(0, fenceHeight / 2, lz);
            spanGroup.add(post);
        }
        fenceGroup.add(spanGroup);
    }

    addFenceSpan(-50, -35.75, -50, 29.0, 3.4);
    addFenceSpan(-50, 29.0, -8.7, 29.0, 3.4);
    fenceGroup.add(createPersonnelDoor(-8.0, 29.0, Math.PI, 'PINTU DARURAT SELATAN'));
    addFenceSpan(-7.3, 29.0, 33.0, 29.0, 3.4);

    addFenceSpan(33.0, 29.0, 33.0, 11.5, 3.0);
    addFenceSpan(33.0, 11.5, 38.0, 11.5, 2.5);
    fenceGroup.add(createAirsideCargoPortal(38.0, 8.0, 7.0));
    addFenceSpan(38.0, 4.5, 38.0, -35.75, 3.4);

    // Garis Dermaga Utara & Bantalan Karet Penahan Truk
    const rubberMat = new THREE.MeshStandardMaterial({ color: 0x0F172A, roughness: 0.95 });
    [-26, -5, 22].forEach(dx => {
        [-1.85, 1.85].forEach(px => {
            const sidePad = new THREE.Mesh(new THREE.BoxGeometry(0.42, 3.8, 0.45), rubberMat);
            sidePad.position.set(dx + px, 2.3, -36.0);
            fenceGroup.add(sidePad);
        });
        const topPad = new THREE.Mesh(new THREE.BoxGeometry(4.12, 0.55, 0.45), rubberMat);
        topPad.position.set(dx, 4.25, -36.0);
        fenceGroup.add(topPad);
    });

    return fenceGroup;
}
layoutGroup.add(createPerimeterSecurityFence());

// ─────────────────────────────────────────────────────────────────────────────
// ARSITEKTUR LENGKAP GEDUNG TERMINAL KARGO (ARCHITECTURAL TERMINAL STRUCTURE)
// ─────────────────────────────────────────────────────────────────────────────
function createModernTerminalArchitecture() {
    const archGroup = new THREE.Group();

    // 1. KANOPI DERMAGA TRUK (LANDSIDE DOCK CANTILEVER CANOPY)
    // Melindungi Truk di Bay 1, Bay 2, Bay 3 dari cuaca (Z = -35.75 s.d. -47.0, X = -36 s.d. 28)
    const canopyW = 66.0;
    const canopyD = 12.0;
    const canopyH = 7.2;
    const canopyGroup = new THREE.Group();
    canopyGroup.position.set(-4.0, canopyH, -41.75);

    // Atap Kanopi Metal Gelombang / Panel Insulasi
    const canopyRoof = new THREE.Mesh(new THREE.BoxGeometry(canopyW, 0.35, canopyD), MAT.metalDark);
    canopyRoof.castShadow = true;
    canopyRoof.receiveShadow = true;

    // Fascia Depan Kanopi dengan Garis Cyan InJourney
    const fasciaFront = new THREE.Mesh(new THREE.BoxGeometry(canopyW + 0.2, 0.75, 0.25), new THREE.MeshStandardMaterial({ color: 0x0A192F, roughness: 0.3 }));
    fasciaFront.position.set(0, 0, -canopyD / 2);
    const fasciaStripe = new THREE.Mesh(new THREE.BoxGeometry(canopyW + 0.3, 0.12, 0.27), MAT.injourneyTeal);
    fasciaStripe.position.set(0, -0.2, -canopyD / 2);

    // Rangka Baja Cantilever Penopang Kanopi
    for (let bx = -canopyW / 2 + 3.0; bx <= canopyW / 2 - 3.0; bx += 10.0) {
        const rafter = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.45, canopyD), MAT.metalDark);
        rafter.position.set(bx, -0.3, 0);
        // Strut diagonal penopang ke dinding
        const strut = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 5.2, 8), MAT.metalSilver);
        strut.rotation.x = Math.PI / 4;
        strut.position.set(bx, -1.8, canopyD / 2 - 1.8);
        canopyGroup.add(rafter, strut);

        // Lampu Downlight LED Recessed di Bawah Kanopi Dermaga
        const downlight = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.2, 0.08, 12), new THREE.MeshBasicMaterial({ color: 0xFEF08A }));
        downlight.position.set(bx, -0.4, 0);
        canopyGroup.add(downlight);
    }

    // Signboard Arsitektur Fasad Kanopi Luar (Flush Mounted 3D)
    const canopySign = makeMountedSignboard("INJOURNEY CARGO TERMINAL", "INBOUND LOGISTICS DOCKS (BAY 1 - 3)", 18.0, 1.8, "#0CA1AF");
    canopySign.position.set(0, 2.7, -canopyD / 2 - 0.05);
    signsGroup.add(canopySign);

    canopyGroup.add(canopyRoof, fasciaFront, fasciaStripe);
    archGroup.add(canopyGroup);

    // 2. DINDING SISI BARAT BERTEKSTUR CLADDING (WEST EXTERIOR WALL di X = -50.0)
    // Menutup warehouse dari Z = -35.75 sampai Z = 29.0 (Panjang 64.75m, Tinggi 7.6m)
    const westWallGroup = new THREE.Group();
    westWallGroup.position.set(-50.0, 0, -3.375);

    // Base Plinth Beton Gelap Tahan Cuaca
    const westPlinth = new THREE.Mesh(new THREE.BoxGeometry(0.6, 0.8, 64.75), MAT.wallBasePlinth);
    westPlinth.position.set(0, 0.4, 0);
    westPlinth.receiveShadow = true;

    // Panel Sandwich Insulasi Arsitektural
    const westPanel = new THREE.Mesh(new THREE.BoxGeometry(0.4, 5.4, 64.75), MAT.wallPanel);
    westPanel.position.set(0, 3.5, 0);
    westPanel.receiveShadow = true;

    // Reveal Joint Garis Shadow Horizontal Metalik
    [2.0, 3.4, 4.8].forEach(jy => {
        const joint = new THREE.Mesh(new THREE.BoxGeometry(0.44, 0.04, 64.75), MAT.metalDark);
        joint.position.set(0, jy, 0);
        westWallGroup.add(joint);
    });

    // Parapet Atas InJourney Navy & Lis Cornice Teal
    const westParapet = new THREE.Mesh(new THREE.BoxGeometry(0.48, 1.4, 64.75), MAT.planeNavy);
    westParapet.position.set(0, 6.9, 0);
    const westCornice = new THREE.Mesh(new THREE.BoxGeometry(0.52, 0.14, 64.85), MAT.injourneyTeal);
    westCornice.position.set(0, 7.62, 0);
    westWallGroup.add(westPlinth, westPanel, westParapet, westCornice);

    // Pintu Darurat & Akses Mezzanine di Dinding Barat
    archGroup.add(westWallGroup);
    archGroup.add(createPersonnelDoor(-49.8, -12.0, Math.PI / 2, "PINTU DARURAT (EGRESS)"));
    archGroup.add(createPersonnelDoor(-49.8, 14.0, Math.PI / 2, "AKSES MEZZANINE"));

    // Signboard Megah Sisi Luar Dinding Barat Menghadap Akses Masuk
    const westSign = makeMountedSignboard("INJOURNEY LOGISTICS TERMINAL", "INTERNATIONAL AIR CARGO HUB & CUSTOMS", 22.0, 2.0, "#0CA1AF");
    westSign.position.set(-50.35, 5.5, -3.375);
    westSign.rotation.y = -Math.PI / 2;
    signsGroup.add(westSign);

    // 3. DINDING SISI SELATAN DENGAN JENDELA CLERESTORY & SUN LOUVERS (SOUTH WALL di Z = 29.0)
    // Menutup dari X = -50.0 sampai X = 38.0 (Panjang 88m, Tinggi 7.6m)
    const southWallGroup = new THREE.Group();
    southWallGroup.position.set(-6.0, 0, 29.0);

    // Base Plinth Beton Gelap
    const southPlinth = new THREE.Mesh(new THREE.BoxGeometry(88.4, 0.8, 0.6), MAT.wallBasePlinth);
    southPlinth.position.set(0, 0.4, 0);
    southPlinth.receiveShadow = true;

    // Panel Dinding Cladding Bawah
    const southPanel = new THREE.Mesh(new THREE.BoxGeometry(88.0, 4.4, 0.4), MAT.wallPanel);
    southPanel.position.set(0, 3.0, 0);
    southPanel.receiveShadow = true;

    // Reveal Joint Garis Horizontal
    [1.8, 3.2, 4.6].forEach(jy => {
        const joint = new THREE.Mesh(new THREE.BoxGeometry(88.1, 0.04, 0.44), MAT.metalDark);
        joint.position.set(0, jy, 0);
        southWallGroup.add(joint);
    });

    // Jendela Clerestory Kaca Atas Dinding Selatan
    const southWin = new THREE.Mesh(new THREE.BoxGeometry(86.0, 1.6, 0.35), MAT.wallGlass);
    southWin.position.set(0, 6.0, 0);
    southWallGroup.add(southWin);

    // Mullions Vertikal Jendela Clerestory
    for (let wx = -42.0; wx <= 42.0; wx += 4.0) {
        const mullion = new THREE.Mesh(new THREE.BoxGeometry(0.12, 1.6, 0.38), MAT.metalDark);
        mullion.position.set(wx, 6.0, 0);
        southWallGroup.add(mullion);
    }

    // Sirip Kerai Penangkal Surya (Brise-Soleil Architectural Sun Louvers)
    [5.4, 5.8, 6.2, 6.6].forEach(ly => {
        const louverF = new THREE.Mesh(new THREE.BoxGeometry(86.2, 0.05, 0.55), MAT.metalSilver);
        louverF.position.set(0, ly, 0.38);
        southWallGroup.add(louverF);
    });

    // Parapet Atas Navy & Lis Cyan
    const southParapet = new THREE.Mesh(new THREE.BoxGeometry(88.2, 0.8, 0.48), MAT.planeNavy);
    southParapet.position.set(0, 7.2, 0);
    const southCornice = new THREE.Mesh(new THREE.BoxGeometry(88.4, 0.14, 0.52), MAT.injourneyTeal);
    southCornice.position.set(0, 7.62, 0);
    southWallGroup.add(southPlinth, southPanel, southParapet, southCornice);
    archGroup.add(southWallGroup);

    // Lampu Sorot Keamanan Exterior Wall-Pack di Dinding Selatan
    for (let lx = -40.0; lx <= 36.0; lx += 14.0) {
        const pack = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.25, 0.2), MAT.metalDark);
        pack.position.set(lx, 4.8, 29.25);
        const packLight = new THREE.Mesh(new THREE.PlaneGeometry(0.3, 0.12), new THREE.MeshBasicMaterial({ color: 0xFEF08A }));
        packLight.rotation.x = Math.PI / 3;
        packLight.position.set(lx, 4.75, 29.35);
        archGroup.add(pack, packLight);
    }

    // 4. FASAD KACA SISI UDARA / APRON CURTAIN WALL (EAST WALL di X = 38.0)
    // Fasad kaca struktural memisahkan gudang indoor dengan tarmac apron
    const glassWallGroup = new THREE.Group();
    glassWallGroup.position.set(38.0, 0, -3.375);

    for (let gz = -32.0; gz <= 32.0; gz += 4.0) {
        // Jangan tutupi portal ekspor kargo di Z = 8.0 (gz = 11.375 relatif ke center)
        if (Math.abs(gz - 11.375) < 3.4) continue;
        
        const glassPane = new THREE.Mesh(new THREE.PlaneGeometry(3.8, 7.2), MAT.wallGlass);
        glassPane.rotation.y = Math.PI / 2;
        glassPane.position.set(0, 3.6, gz);

        const mullion = new THREE.Mesh(new THREE.BoxGeometry(0.18, 7.5, 0.18), MAT.metalDark);
        mullion.position.set(0, 3.75, gz + 2.0);
        glassWallGroup.add(glassPane, mullion);
    }
    archGroup.add(glassWallGroup);

    // 5. TERMINAL OPERATIONS CENTER (TOC) & AVSEC MEZZANINE (LANTAI 2)
    // Mezzanine kantor pengawas di X = -48 s.d. -26, Z = 16 s.d. 28, Y = 3.6 s.d. 7.2
    const tocGroup = new THREE.Group();
    tocGroup.position.set(-37.0, 0, 22.0);

    const tocFloor = new THREE.Mesh(new THREE.BoxGeometry(22.0, 0.25, 12.0), MAT.metalDark);
    tocFloor.position.set(0, 3.6, 0);

    const tocGlass = new THREE.Mesh(new THREE.BoxGeometry(21.6, 2.8, 0.1), MAT.wallGlass);
    tocGlass.position.set(0, 5.1, -5.95);

    const tocCeil = new THREE.Mesh(new THREE.BoxGeometry(22.0, 0.2, 12.0), MAT.wallBuilding);
    tocCeil.position.set(0, 7.2, 0);

    // Kolom Penyangga Mezzanine
    [[-10.5, -5.5], [10.5, -5.5], [0, -5.5]].forEach(([mx, mz]) => {
        const col = new THREE.Mesh(new THREE.BoxGeometry(0.4, 3.6, 0.4), MAT.metalDark);
        col.position.set(mx, 1.8, mz);
        tocGroup.add(col);
    });

    // Tangga Baja Menuju Lantai 2
    const stairGroup = new THREE.Group();
    stairGroup.position.set(11.5, 0, 0);
    const numSteps = 16;
    for (let s = 0; s < numSteps; s++) {
        const stepY = (s / numSteps) * 3.6;
        const stepZ = -5.5 + (s / numSteps) * 6.5;
        const tread = new THREE.Mesh(new THREE.BoxGeometry(1.2, 0.08, 0.38), MAT.metalSilver);
        tread.position.set(0, stepY, stepZ);
        stairGroup.add(tread);
    }
    const railMat = new THREE.MeshStandardMaterial({ color: 0xF59E0B, metalness: 0.6 });
    const handrail = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 7.8, 8), railMat);
    handrail.position.set(0.55, 2.2, -2.25);
    handrail.rotation.x = -0.68;
    stairGroup.add(handrail);
    tocGroup.add(stairGroup);

    // Signboard TOC (Flush Mounted pada Fasad Kaca Mezzanine)
    const tocSign = makeMountedSignboard("TOC OPERATIONS CENTER", "AVSEC DISPATCH & AIRPORT CARGO CONTROL", 12.0, 1.4, "#38BDF8");
    tocSign.position.set(0, 5.8, -6.05);
    tocGroup.add(tocFloor, tocGlass, tocCeil, tocSign);
    archGroup.add(tocGroup);

    // 6. RANGKA KUDA-KUDA ATAP BAJA (OVERHEAD STEEL ROOF TRUSSES & UTILITIES)
    // Bentang Kuda-kuda Baja di Y = 8.4m
    [-28.0, -14.0, 0.0, 14.0].forEach(tz => {
        const trussGroup = new THREE.Group();
        trussGroup.position.set(-6.0, 8.4, tz);

        const chordT = new THREE.Mesh(new THREE.BoxGeometry(88.0, 0.18, 0.18), MAT.metalDark);
        chordT.position.y = 0.6;
        const chordB = new THREE.Mesh(new THREE.BoxGeometry(88.0, 0.18, 0.18), MAT.metalDark);
        chordB.position.y = -0.6;
        trussGroup.add(chordT, chordB);

        for (let tx = -42.0; tx <= 42.0; tx += 4.0) {
            const diag = new THREE.Mesh(new THREE.BoxGeometry(0.1, 1.4, 0.1), MAT.metalSilver);
            diag.rotation.z = Math.PI / 4;
            diag.position.set(tx, 0, 0);
            trussGroup.add(diag);
        }

        // Lampu Industri Gantung High-Bay UFO LED
        [-28.0, -8.0, 12.0, 28.0].forEach(lx => {
            const cable = new THREE.Mesh(new THREE.CylinderGeometry(0.015, 0.015, 1.2, 6), MAT.metalDark);
            cable.position.set(lx, -1.2, 0);
            const ufo = new THREE.Mesh(new THREE.CylinderGeometry(0.55, 0.35, 0.22, 16), MAT.metalDark);
            ufo.position.set(lx, -1.8, 0);
            const bulb = new THREE.Mesh(new THREE.CircleGeometry(0.48, 16), new THREE.MeshBasicMaterial({ color: 0xF8FAFC }));
            bulb.rotation.x = Math.PI / 2;
            bulb.position.set(lx, -1.92, 0);
            trussGroup.add(cable, ufo, bulb);
        });

        // Pipa Sprinkler Pemadam Kebakaran Merah (Fire Sprinkler Main Pipe)
        const sprinklerPipe = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 88.0, 8), MAT.redHazard);
        sprinklerPipe.rotateZ(Math.PI / 2);
        sprinklerPipe.position.set(0, -0.3, 0.2);
        trussGroup.add(sprinklerPipe);

        archGroup.add(trussGroup);
    });

    // 7. SALURAN VENTILASI UDARA SPIRAL HVAC (SPIRAL AIR DUCTS & DIFFUSERS)
    [-14.0, 7.0].forEach(dz => {
        const ductGroup = new THREE.Group();
        ductGroup.position.set(-6.0, 7.6, dz);

        // Pipa Utama Spiral Duct Perak
        const mainDuct = new THREE.Mesh(new THREE.CylinderGeometry(0.4, 0.4, 82.0, 16), MAT.metalSilver);
        mainDuct.rotateZ(Math.PI / 2);
        ductGroup.add(mainDuct);

        // Register Diffuser Drop Down Setiap 14 Meter
        for (let dx = -35.0; dx <= 35.0; dx += 14.0) {
            const drop = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.18, 0.6, 12), MAT.metalSilver);
            drop.position.set(dx, -0.35, 0);
            const diffuser = new THREE.Mesh(new THREE.BoxGeometry(0.7, 0.08, 0.7), MAT.metalDark);
            diffuser.position.set(dx, -0.68, 0);
            ductGroup.add(drop, diffuser);
        }
        archGroup.add(ductGroup);
    });

    // 8. KIPAS ANGIN INDUSTRI RAKSASA HVLS (HIGH-VOLUME LOW-SPEED CEILING FANS)
    function createIndustrialHVLSFan(fx, fz, fy = 7.9) {
        const fanGroup = new THREE.Group();
        fanGroup.position.set(fx, fy, fz);

        // Batang Gantung & Housing Motor Listrik
        const dropRod = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.05, 1.2, 8), MAT.metalDark);
        dropRod.position.y = 0.6;
        const motorHousing = new THREE.Mesh(new THREE.CylinderGeometry(0.42, 0.42, 0.35, 16), MAT.metalDark);
        motorHousing.position.y = 0;
        const motorCap = new THREE.Mesh(new THREE.CylinderGeometry(0.32, 0.38, 0.12, 16), MAT.injourneyTeal);
        motorCap.position.y = -0.2;
        fanGroup.add(dropRod, motorHousing, motorCap);

        // Rotor Kipas Berputar (6 Bilah Sayap Aerodinamis Diameter 6.4 Meter)
        const rotor = new THREE.Group();
        rotor.position.y = -0.22;
        const numBlades = 6;
        const bladeRadius = 3.2;

        for (let b = 0; b < numBlades; b++) {
            const bAngle = (b / numBlades) * Math.PI * 2;
            const bladeArm = new THREE.Group();
            bladeArm.rotation.y = bAngle;

            // Bilah Aluminium Aerofoil
            const blade = new THREE.Mesh(new THREE.BoxGeometry(bladeRadius, 0.02, 0.24), MAT.metalSilver);
            blade.position.set(bladeRadius / 2 + 0.25, 0, 0);
            blade.rotation.x = 0.14;

            // Winglet Kuning Keselamatan di Ujung Bilah
            const winglet = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.18, 0.25), MAT.yellowSafety);
            winglet.position.set(bladeRadius + 0.28, 0.06, 0);

            bladeArm.add(blade, winglet);
            rotor.add(bladeArm);
        }
        fanGroup.add(rotor);
        hvlsFans.push(rotor);
        return fanGroup;
    }

    // 2 Unit Kipas HVLS di Zona Gudang Barat dan Zona Gudang Timur
    archGroup.add(createIndustrialHVLSFan(-20.0, -10.0));
    archGroup.add(createIndustrialHVLSFan(12.0, 5.0));

    return archGroup;
}
layoutGroup.add(createModernTerminalArchitecture());

// ─────────────────────────────────────────────────────────────────────────────
// DENAH MARKA LANTAI INDUSTRI LENGKAP (EPOXY SAFETY WALKWAYS & HATCHING)
// ─────────────────────────────────────────────────────────────────────────────
// Helper 3D Industrial Equipment Builders
function createSafetyBollard(x, z) {
    const bg = new THREE.Group();
    bg.position.set(x, 0, z);

    const post = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.12, 1.25, 16), MAT.yellowSafety);
    post.position.y = 0.625;

    [0.75, 0.95].forEach(sy => {
        const stripe = new THREE.Mesh(new THREE.CylinderGeometry(0.125, 0.125, 0.1, 16), MAT.hazardBlack);
        stripe.position.y = sy;
        bg.add(stripe);
    });

    const dome = new THREE.Mesh(new THREE.SphereGeometry(0.12, 16, 8, 0, Math.PI * 2, 0, Math.PI / 2), MAT.yellowSafety);
    dome.position.y = 1.25;

    bg.add(post, dome);
    return bg;
}

function createColumnFireStation(colX, colZ) {
    const fg = new THREE.Group();
    fg.position.set(colX, 0, colZ);

    const body = new THREE.Mesh(new THREE.CylinderGeometry(0.09, 0.09, 0.55, 12), new THREE.MeshStandardMaterial({ color: 0xDC2626, metalness: 0.3, roughness: 0.3 }));
    body.position.set(0.32, 1.4, 0);

    const valve = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.12, 8), MAT.metalSilver);
    valve.position.set(0.32, 1.72, 0);
    const hose = new THREE.Mesh(new THREE.CylinderGeometry(0.015, 0.015, 0.45, 6), MAT.metalDark);
    hose.position.set(0.38, 1.45, 0);

    const sign = new THREE.Mesh(new THREE.PlaneGeometry(0.28, 0.28), new THREE.MeshBasicMaterial({ color: 0xDC2626 }));
    sign.position.set(0.33, 1.88, 0);
    sign.rotation.y = Math.PI / 2;

    fg.add(body, valve, hose, sign);
    return fg;
}

function createHandPalletJack(x, z, rotY) {
    const pj = new THREE.Group();
    pj.position.set(x, 0, z);
    pj.rotation.y = rotY;

    [-0.22, 0.22].forEach(fx => {
        const fork = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.06, 1.2), MAT.yellowSafety);
        fork.position.set(fx, 0.08, 0.6);
        pj.add(fork);
    });

    const pump = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.14, 0.5, 12), MAT.metalDark);
    pump.position.set(0, 0.3, 0);

    const handleBar = new THREE.Mesh(new THREE.CylinderGeometry(0.02, 0.02, 0.95, 8), MAT.metalDark);
    handleBar.rotation.x = -0.4;
    handleBar.position.set(0, 0.68, -0.2);
    const tGrip = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, 0.35, 8), MAT.yellowSafety);
    tGrip.rotation.z = Math.PI / 2;
    tGrip.position.set(0, 1.05, -0.4);

    const wGeo = new THREE.CylinderGeometry(0.08, 0.08, 0.06, 12);
    wGeo.rotateZ(Math.PI / 2);
    [[-0.15, -0.05], [0.15, -0.05]].forEach(([wx, wz]) => {
        const w = new THREE.Mesh(wGeo, MAT.truckTire);
        w.position.set(wx, 0.08, wz);
        pj.add(w);
    });

    pj.add(pump, handleBar, tGrip);
    return pj;
}

function createContainerDolly(x, z, rotY) {
    const dolly = new THREE.Group();
    dolly.position.set(x, 0, z);
    dolly.rotation.y = rotY;

    const frame = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.16, 2.2), MAT.metalDark);
    frame.position.y = 0.35;

    const bed = new THREE.Mesh(new THREE.BoxGeometry(2.2, 0.04, 2.0), MAT.metalSilver);
    bed.position.y = 0.44;

    for (let rz = -0.7; rz <= 0.7; rz += 0.35) {
        const roller = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, 2.1, 12), MAT.conveyorRoller);
        roller.rotation.z = Math.PI / 2;
        roller.position.set(0, 0.48, rz);
        dolly.add(roller);
    }

    [[-1.1, -1.0], [1.1, -1.0], [-1.1, 1.0], [1.1, 1.0]].forEach(([cx, cz]) => {
        const stop = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.22, 0.12), MAT.yellowSafety);
        stop.position.set(cx, 0.52, cz);
        dolly.add(stop);
    });

    const wGeo = new THREE.CylinderGeometry(0.18, 0.18, 0.14, 16);
    wGeo.rotateZ(Math.PI / 2);
    [[-1.05, -0.7], [1.05, -0.7], [-1.05, 0.7], [1.05, 0.7]].forEach(([wx, wz]) => {
        const tire = new THREE.Mesh(wGeo, MAT.truckTire);
        tire.position.set(wx, 0.18, wz);
        dolly.add(tire);
    });

    const towbar = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 1.4, 8), MAT.yellowSafety);
    towbar.rotation.x = Math.PI / 2;
    towbar.position.set(0, 0.24, -1.7);
    const hitchEye = new THREE.Mesh(new THREE.TorusGeometry(0.08, 0.025, 8, 16), MAT.metalSilver);
    hitchEye.rotation.x = Math.PI / 2;
    hitchEye.position.set(0, 0.24, -2.4);

    dolly.add(frame, bed, towbar, hitchEye);
    return dolly;
}

function createAirportTowTractor(x, z, rotY) {
    const tug = new THREE.Group();
    tug.position.set(x, 0, z);
    tug.rotation.y = rotY;

    const body = new THREE.Mesh(new THREE.BoxGeometry(2.4, 1.1, 4.2), new THREE.MeshStandardMaterial({ color: 0xF59E0B, metalness: 0.5, roughness: 0.4 }));
    body.position.y = 0.8;

    const cab = new THREE.Mesh(new THREE.BoxGeometry(2.1, 1.2, 1.8), new THREE.MeshStandardMaterial({ color: 0x0F172A, metalness: 0.7, roughness: 0.3 }));
    cab.position.set(0, 1.9, 0.4);

    const cabGlass = new THREE.Mesh(new THREE.BoxGeometry(2.0, 0.7, 0.1), MAT.cockpitGlass);
    cabGlass.position.set(0, 2.0, -0.55);

    const beacon = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.14, 0.22, 12), new THREE.MeshBasicMaterial({ color: 0xF59E0B }));
    beacon.position.set(0, 2.6, 0.4);

    const wGeo = new THREE.CylinderGeometry(0.48, 0.48, 0.36, 16);
    wGeo.rotateZ(Math.PI / 2);
    [[-1.25, -1.2], [1.25, -1.2], [-1.25, 1.2], [1.25, 1.2]].forEach(([wx, wz]) => {
        const tire = new THREE.Mesh(wGeo, MAT.truckTire);
        tire.position.set(wx, 0.48, wz);
        tug.add(tire);
    });

    const frontHitch = new THREE.Mesh(new THREE.BoxGeometry(0.6, 0.4, 0.35), MAT.metalDark);
    frontHitch.position.set(0, 0.6, -2.2);
    const rearHitch = new THREE.Mesh(new THREE.BoxGeometry(0.6, 0.4, 0.35), MAT.metalDark);
    rearHitch.position.set(0, 0.6, 2.2);

    tug.add(body, cab, cabGlass, beacon, frontHitch, rearHitch);
    return tug;
}

function createAviationSafetyCone(x, z) {
    const cg = new THREE.Group();
    cg.position.set(x, 0, z);

    const base = new THREE.Mesh(new THREE.BoxGeometry(0.4, 0.04, 0.4), new THREE.MeshStandardMaterial({ color: 0xEA580C, roughness: 0.5 }));
    base.position.y = 0.02;

    const cone = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.18, 0.75, 16), new THREE.MeshStandardMaterial({ color: 0xEA580C, roughness: 0.5 }));
    cone.position.y = 0.395;

    const band1 = new THREE.Mesh(new THREE.CylinderGeometry(0.09, 0.12, 0.12, 16), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
    band1.position.y = 0.32;
    const band2 = new THREE.Mesh(new THREE.CylinderGeometry(0.055, 0.075, 0.1, 16), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
    band2.position.y = 0.52;

    cg.add(base, cone, band1, band2);
    return cg;
}

// ─────────────────────────────────────────────────────────────────────────────
// DENAH MARKA LANTAI INDUSTRI LENGKAP & DETAIL ARSITEKTUR
// ─────────────────────────────────────────────────────────────────────────────
function createCompleteDenahFloorMarkings() {
    const markGroup = new THREE.Group();

    // 1. TARMAC DOCK BAY GUIDE LINES & STOP LIMITS (LANDSIDE BAY 1 - 3)
    [
        { x: -26.0, bay: 'BAY 01' },
        { x: -5.0,  bay: 'BAY 02' },
        { x: 22.0,  bay: 'BAY 03' }
    ].forEach(dock => {
        [-2.2, 2.2].forEach(ox => {
            const line = new THREE.Mesh(
                new THREE.PlaneGeometry(0.18, 14.0),
                new THREE.MeshBasicMaterial({ color: 0xFACC15, polygonOffset: true, polygonOffsetFactor: -1, polygonOffsetUnits: -1 })
            );
            line.rotation.x = -Math.PI / 2;
            line.position.set(dock.x + ox, 0.015, -45.0);
            markGroup.add(line);
        });
        const stopBar = new THREE.Mesh(
            new THREE.PlaneGeometry(4.4, 0.35),
            new THREE.MeshBasicMaterial({ color: 0xEF4444, polygonOffset: true, polygonOffsetFactor: -1, polygonOffsetUnits: -1 })
        );
        stopBar.rotation.x = -Math.PI / 2;
        stopBar.position.set(dock.x, 0.018, -36.5);
        markGroup.add(stopBar);

        const dockText = makeTarmacText(dock.bay, 3.4, 1.6, '#FACC15');
        dockText.position.set(dock.x, 0.02, -50.0);
        markGroup.add(dockText);
    });

    // 2. JALUR PEJALAN KAKI HIJAU KESELAMATAN (SAFETY GREEN WALKWAY CORRIDORS)
    const walkMat = MAT.safetyGreen;
    const walkBorderMat = new THREE.MeshBasicMaterial({ color: 0xF59E0B });

    function createWalkwayCorridor(x1, z1, x2, z2, width = 1.35) {
        const dx = x2 - x1;
        const dz = z2 - z1;
        const len = Math.hypot(dx, dz);
        const angle = Math.atan2(dx, dz);

        const wGrp = new THREE.Group();
        wGrp.position.set((x1 + x2) / 2, 0.028, (z1 + z2) / 2);
        wGrp.rotation.y = angle;

        const surface = new THREE.Mesh(new THREE.PlaneGeometry(width, len), walkMat);
        surface.rotation.x = -Math.PI / 2;

        [-width / 2, width / 2].forEach(bx => {
            const border = new THREE.Mesh(new THREE.PlaneGeometry(0.08, len), walkBorderMat);
            border.rotation.x = -Math.PI / 2;
            border.position.set(bx, 0.002, 0);
            wGrp.add(border);
        });

        wGrp.add(surface);
        return wGrp;
    }

    // Koridor Pejalan Kaki Lengkap Terkoneksi
    markGroup.add(createWalkwayCorridor(-47.0, -32.0, -47.0, 22.0, 1.4)); // Barat
    markGroup.add(createWalkwayCorridor(-47.0, -12.0, -26.0, -12.0, 1.3)); // Sambungan ke Pintu Egress
    markGroup.add(createWalkwayCorridor(-47.0, 14.0, 16.0, 14.0, 1.3));  // Lintas Tengah ke Timur
    markGroup.add(createWalkwayCorridor(16.0, 14.0, 37.5, 14.0, 1.3));   // Akses ke Pintu Airside

    // Zebra Crosswalk pada persilangan jalan
    for (let zx = -4.5; zx <= -0.5; zx += 0.8) {
        const zebra = new THREE.Mesh(new THREE.PlaneGeometry(0.5, 1.4), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
        zebra.rotation.x = -Math.PI / 2;
        zebra.position.set(zx, 0.035, 14.0);
        markGroup.add(zebra);
    }

    // 3. JALUR FORKLIFT INDUSTRI (YELLOW FORKLIFT HIGHWAYS)
    const forkYellowMat = new THREE.MeshBasicMaterial({ color: 0xF59E0B });
    [
        { x: -3.0, zStart: -32.0, zEnd: 24.0 },
        { x: 4.5,  zStart: -32.0, zEnd: 24.0 },
        { x: -25.0, zStart: -32.0, zEnd: 12.0 }
    ].forEach(lane => {
        const len = lane.zEnd - lane.zStart;
        const lMesh = new THREE.Mesh(new THREE.PlaneGeometry(0.12, len), forkYellowMat);
        lMesh.rotation.x = -Math.PI / 2;
        lMesh.position.set(lane.x, 0.026, (lane.zStart + lane.zEnd) / 2);
        markGroup.add(lMesh);
    });

    // Stensil Panah Forklift di Lantai
    [-22.0, -8.0, 4.0, 18.0].forEach(pz => {
        const arrowMesh = makeTarmacText("▲", 1.8, 1.8, '#F59E0B');
        arrowMesh.position.set(0.75, 0.028, pz);
        markGroup.add(arrowMesh);
    });

    // 4. KOTAK KESELAMATAN ARSIR KUNING-HITAM (SAFETY HATCHING)
    function createHatchedZone(cx, cz, w, h, label = "") {
        const hz = new THREE.Group();
        hz.position.set(cx, 0.028, cz);

        const borderMat = new THREE.MeshBasicMaterial({ color: 0xFACC15 });
        const bTop = new THREE.Mesh(new THREE.PlaneGeometry(w, 0.16), borderMat);
        bTop.rotation.x = -Math.PI / 2;
        bTop.position.set(0, 0, -h / 2);
        const bBtm = new THREE.Mesh(new THREE.PlaneGeometry(w, 0.16), borderMat);
        bBtm.rotation.x = -Math.PI / 2;
        bBtm.position.set(0, 0, h / 2);
        const bL = new THREE.Mesh(new THREE.PlaneGeometry(0.16, h), borderMat);
        bL.rotation.x = -Math.PI / 2;
        bL.position.set(-w / 2, 0, 0);
        const bR = new THREE.Mesh(new THREE.PlaneGeometry(0.16, h), borderMat);
        bR.rotation.x = -Math.PI / 2;
        bR.position.set(w / 2, 0, 0);
        hz.add(bTop, bBtm, bL, bR);

        const numStripes = Math.floor((w + h) / 1.6);
        for (let s = 0; s < numStripes; s++) {
            const stripe = new THREE.Mesh(new THREE.PlaneGeometry(0.16, Math.min(w, h) * 0.95), borderMat);
            stripe.rotation.x = -Math.PI / 2;
            stripe.rotation.z = Math.PI / 4;
            const sx = -w / 2 + (s / numStripes) * w;
            stripe.position.set(sx, 0, 0);
            hz.add(stripe);
        }

        if (label) {
            hz.add(makeTarmacText(label, Math.min(5.5, w * 0.8), 1.2, "#FACC15"));
            hz.children[hz.children.length - 1].position.set(0, 0.005, 0);
        }
        return hz;
    }

    markGroup.add(createHatchedZone(-5.0, -18.5, 14.0, 7.5, "SECURITY X-RAY ZONE"));
    markGroup.add(createHatchedZone(5.0, -16.5, 12.0, 3.4, "PNEUMATIC DIVERTER"));
    markGroup.add(createHatchedZone(35.0, 8.0, 8.5, 5.5, "15T SCALE ZONE"));
    markGroup.add(createHatchedZone(45.0, 8.0, 8.0, 4.5, "AIRSIDE LOADING GATE"));

    // 5. STAGING BAYS OUTLINES PADA LANTAI GUDANG
    function createStagingBayOutline(bx, bz, bw, bd, bayName, accentColor = 0x38BDF8) {
        const bg = new THREE.Group();
        bg.position.set(bx, 0.026, bz);
        const bMat = new THREE.MeshBasicMaterial({ color: accentColor });
        const bTop = new THREE.Mesh(new THREE.PlaneGeometry(bw, 0.12), bMat);
        bTop.rotation.x = -Math.PI / 2;
        bTop.position.set(0, 0, -bd / 2);
        const bBtm = new THREE.Mesh(new THREE.PlaneGeometry(bw, 0.12), bMat);
        bBtm.rotation.x = -Math.PI / 2;
        bBtm.position.set(0, 0, bd / 2);
        const bL = new THREE.Mesh(new THREE.PlaneGeometry(0.12, bd), bMat);
        bL.rotation.x = -Math.PI / 2;
        bL.position.set(-bw / 2, 0, 0);
        const bR = new THREE.Mesh(new THREE.PlaneGeometry(0.12, bd), bMat);
        bR.rotation.x = -Math.PI / 2;
        bR.position.set(bw / 2, 0, 0);
        bg.add(bTop, bBtm, bL, bR);

        bg.add(makeTarmacText(bayName, Math.min(5.0, bw * 0.8), 1.0, '#' + bMat.color.getHexString()));
        bg.children[bg.children.length - 1].position.set(0, 0.005, 0);
        return bg;
    }

    markGroup.add(createStagingBayOutline(-18.0, -18.0, 6.0, 4.2, "BAY 01 : PHARMA", 0x06B6D4));
    markGroup.add(createStagingBayOutline(-18.0, -11.5, 6.0, 4.2, "BAY 02 : DANGEROUS GOODS", 0xF59E0B));
    markGroup.add(createStagingBayOutline(-18.0, 4.0, 6.0, 4.2, "BAY 03 : GENERAL EXPORT", 0x3B82F6));
    markGroup.add(createStagingBayOutline(0.0, 14.0, 7.5, 5.0, "BAY 04 : ULD BUILD-UP", 0x10B981));

    // 6. TIANG PENGAMAN CRASH BOLLARDS
    [
        [-11.5, -22.5], [-11.5, -14.5], [1.5, -22.5], [1.5, -14.5],
        [31.0, 5.0], [31.0, 11.0], [39.0, 5.0], [39.0, 11.0],
        [-25.5, 16.0], [44.5, 5.5], [44.5, 10.5]
    ].forEach(([bx, bz]) => {
        markGroup.add(createSafetyBollard(bx, bz));
    });

    // 7. TABUNG PEMADAM KEBAKARAN (APAR) PADA KOLOM
    [-38, -20, 5, 25].forEach(cx => {
        [-20, 0, 18].forEach(cz => {
            markGroup.add(createColumnFireStation(cx, cz));
        });
    });

    // 8. HYDRAULIC HAND PALLET JACKS
    markGroup.add(createHandPalletJack(-14.5, 4.0, Math.PI / 4));
    markGroup.add(createHandPalletJack(6.5, -2.5, -Math.PI / 3));

    // 9. GROUND SUPPORT EQUIPMENT (GSE) APRON PESAWAT BOEING 777F
    // Kereta Gandeng Kontainer Dolly (3 Unit)
    markGroup.add(createContainerDolly(58.0, 22.0, Math.PI / 2));
    markGroup.add(createContainerDolly(58.0, 26.5, Math.PI / 2));
    markGroup.add(createContainerDolly(58.0, 31.0, Math.PI / 2));

    // Aviation Safety Cones mengelilingi Pesawat Boeing 777F
    [
        [46.5, 25.5], [101.5, 25.5],  // Wingtips Kiri & Kanan (Clearance Stand 12R Bebas Dinding)
        [63.8, 11.5], [84.2, 11.5],   // Mesin GE90 Kiri & Kanan
        [74.0, -18.5], [74.0, 42.0]   // Nose Gear & Tail
    ].forEach(([cx, cz]) => {
        markGroup.add(createAviationSafetyCone(cx, cz));
    });

    return markGroup;
}
layoutGroup.add(createCompleteDenahFloorMarkings());

// ─────────────────────────────────────────────────────────────────────────────
// 4. DERMAGA MUAT TRUK, PARKED TRUCKS & FORKLIFTS (DOCK BAYS 1 - 3)
// ─────────────────────────────────────────────────────────────────────────────
function createParkedRealisticTruck(xPos, dockNum) {
    const truck = new THREE.Group();
    truck.position.set(xPos, 0, -41.5);

    const cab = new THREE.Mesh(new THREE.BoxGeometry(3.0, 3.2, 3.6), MAT.truckCabBlue);
    cab.position.set(0, 2.0, -4.5);
    cab.castShadow = true;

    const spoiler = new THREE.Mesh(new THREE.BoxGeometry(2.9, 0.5, 2.2), MAT.truckCabBlue);
    spoiler.position.set(0, 3.7, -4.3);

    const grill = new THREE.Mesh(new THREE.BoxGeometry(2.4, 1.4, 0.15), MAT.metalDark);
    grill.position.set(0, 1.4, -6.32);

    const bBumper = new THREE.Mesh(new THREE.BoxGeometry(3.1, 0.6, 0.4), MAT.metalSilver);
    bBumper.position.set(0, 0.6, -6.3);

    const winFront = new THREE.Mesh(new THREE.BoxGeometry(2.7, 1.2, 0.15), MAT.cockpitGlass);
    winFront.position.set(0, 2.6, -6.32);

    truck.add(cab, spoiler, grill, bBumper, winFront);

    // Trailer Box
    const box = new THREE.Mesh(new THREE.BoxGeometry(3.2, 3.6, 8.5), new THREE.MeshStandardMaterial({ color: 0xF8FAFC, roughness: 0.45 }));
    box.position.set(0, 2.4, 1.5);
    box.castShadow = true;

    const stripe = new THREE.Mesh(new THREE.BoxGeometry(3.25, 0.6, 7.5), MAT.injourneyTeal);
    stripe.position.set(0, 2.4, 1.5);

    const doorL = new THREE.Mesh(new THREE.BoxGeometry(0.08, 3.4, 1.55), MAT.metalDark);
    doorL.position.set(-1.64, 2.4, 5.8);
    const doorR = new THREE.Mesh(new THREE.BoxGeometry(0.08, 3.4, 1.55), MAT.metalDark);
    doorR.position.set(1.64, 2.4, 5.8);

    truck.add(box, stripe, doorL, doorR);

    // Roda Truk
    const wheelGeo = new THREE.CylinderGeometry(0.55, 0.55, 0.4, 20);
    wheelGeo.rotateZ(Math.PI / 2);
    [[-1.6, -4.5], [1.6, -4.5], [-1.6, 1.2], [1.6, 1.2], [-1.6, 3.2], [1.6, 3.2]].forEach(([wx, wz]) => {
        const tire = new THREE.Mesh(wheelGeo, MAT.truckTire);
        tire.position.set(wx, 0.55, wz);
        truck.add(tire);
    });

    // Pipa Panduan Roda di Aspal (Wheel Guide Curbs)
    [-1.9, 1.9].forEach(gx => {
        const curb = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 8.5, 12), MAT.yellowSafety);
        curb.rotateX(Math.PI / 2);
        curb.position.set(gx, 0.08, 0.5);
        truck.add(curb);
    });

    return truck;
}

// Truk Terparkir di Dermaga 1 (X = -26) dan Dermaga 3 (X = 22)
dockGroup.add(createParkedRealisticTruck(-26.0, 1));
dockGroup.add(createParkedRealisticTruck(22.0, 3));

// Dermaga 2 (X = -5.0) adalah Jalur Aktif Simulasi untuk Truk Inbound
[-26, -5, 22].forEach((dx, idx) => {
    // Leveler Lip Baja Dermaga
    const leveler = new THREE.Mesh(new THREE.BoxGeometry(2.5, 0.06, 1.5), MAT.metalSilver);
    leveler.position.set(dx, 0.36, -36.25);
    leveler.rotation.x = -0.06;
    dockGroup.add(leveler);

    // Rangka Portal Dermaga
    const pColL = new THREE.Mesh(new THREE.BoxGeometry(0.3, 4.6, 0.3), MAT.metalDark);
    pColL.position.set(dx - 2.1, 2.3, -35.75);
    const pColR = new THREE.Mesh(new THREE.BoxGeometry(0.3, 4.6, 0.3), MAT.metalDark);
    pColR.position.set(dx + 2.1, 2.3, -35.75);
    const pLintel = new THREE.Mesh(new THREE.BoxGeometry(4.5, 0.35, 0.35), MAT.metalDark);
    pLintel.position.set(dx, 4.6, -35.75);
    const rollerCan = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.2, 3.9, 16), MAT.metalSilver);
    rollerCan.rotateZ(Math.PI / 2);
    rollerCan.position.set(dx, 4.3, -35.75);
    dockGroup.add(pColL, pColR, pLintel, rollerCan);

    // Lampu Status Dermaga (Hijau)
    const ledB = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.35, 0.1), new THREE.MeshBasicMaterial({ color: 0x10B981 }));
    ledB.position.set(dx + 2.1, 2.6, -35.58);
    dockGroup.add(ledB);
});

// ─────────────────────────────────────────────────────────────────────────────
// PERALATAN MATERIAL HANDLING & LOGISTIK GUDANG LENGKAP (FORKLIFTS & WAREHOUSE FLEET)
// ─────────────────────────────────────────────────────────────────────────────
// 1. Forklift Counterbalance Lapangan Realistis dengan Operator & Lampu
function createDetailedForklift(x, z, rotY, withCargo = false, bodyColorHex = 0xF59E0B, cargoType = 'boxes') {
    const fl = new THREE.Group();
    fl.position.set(x, 0, z);
    fl.rotation.y = rotY;

    const bodyMat = new THREE.MeshStandardMaterial({ color: bodyColorHex, roughness: 0.35, metalness: 0.25 });

    // Sasis Utama
    const chassis = new THREE.Mesh(new THREE.BoxGeometry(1.4, 0.6, 2.0), bodyMat);
    chassis.position.set(0, 0.45, -0.2);
    const cw = new THREE.Mesh(new THREE.BoxGeometry(1.38, 0.55, 0.55), MAT.metalDark);
    cw.position.set(0, 0.5, -1.05);

    // Garis Hazard Hitam-Kuning di Sisi Sasis
    [-0.71, 0.71].forEach(sx => {
        const skirt = new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.12, 1.8), MAT.yellowSafety);
        skirt.position.set(sx, 0.22, -0.2);
        fl.add(skirt);
    });

    // Kursi Operator & Konsol Kemudi
    const seat = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.4, 0.45), MAT.metalDark);
    seat.position.set(0, 1.0, -0.25);
    const steerCol = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.6, 8), MAT.metalSilver);
    steerCol.position.set(0, 1.05, 0.28);
    steerCol.rotation.x = -0.3;
    const steerWheel = new THREE.Mesh(new THREE.TorusGeometry(0.12, 0.02, 8, 16), MAT.metalDark);
    steerWheel.position.set(0, 1.25, 0.2);
    steerWheel.rotation.x = 0.8;

    // Operator Forklift (Stylized Low-Poly Figure)
    const opGroup = new THREE.Group();
    opGroup.position.set(0, 1.0, -0.22);
    const opPants = new THREE.Mesh(new THREE.BoxGeometry(0.42, 0.35, 0.4), new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.7 }));
    opPants.position.y = 0.18;
    const opTorso = new THREE.Mesh(new THREE.BoxGeometry(0.44, 0.45, 0.26), new THREE.MeshStandardMaterial({ color: 0xFACC15, roughness: 0.4 }));
    opTorso.position.set(0, 0.55, 0);
    const opVestStripe = new THREE.Mesh(new THREE.BoxGeometry(0.45, 0.06, 0.27), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
    opVestStripe.position.set(0, 0.55, 0);
    const opHead = new THREE.Mesh(new THREE.SphereGeometry(0.12, 10, 10), new THREE.MeshStandardMaterial({ color: 0xD4A373, roughness: 0.6 }));
    opHead.position.set(0, 0.9, 0);
    const opHelmet = new THREE.Mesh(new THREE.SphereGeometry(0.14, 10, 10), new THREE.MeshStandardMaterial({ color: 0xFFFFFF }));
    opHelmet.position.set(0, 0.95, 0);
    opGroup.add(opPants, opTorso, opVestStripe, opHead, opHelmet);
    fl.add(opGroup);

    // Roll Cage Pelindung Atas (Safety Cage)
    const cage = new THREE.Mesh(new THREE.BoxGeometry(1.2, 1.5, 1.1), new THREE.MeshStandardMaterial({ color: 0x1E293B, wireframe: true }));
    cage.position.set(0, 1.8, -0.15);
    const cageRoof = new THREE.Mesh(new THREE.BoxGeometry(1.25, 0.06, 1.15), MAT.metalDark);
    cageRoof.position.set(0, 2.55, -0.15);

    // Lampu Rotari Oranye di Atap Cage
    const beacon = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 0.12, 10), new THREE.MeshBasicMaterial({ color: 0xF59E0B }));
    beacon.position.set(0, 2.65, -0.5);

    // Lampu Sorot Depan LED di Rangka Atas
    [-0.45, 0.45].forEach(hx => {
        const headL = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.08, 0.08), MAT.metalDark);
        headL.position.set(hx, 2.45, 0.42);
        const bulb = new THREE.Mesh(new THREE.PlaneGeometry(0.08, 0.06), new THREE.MeshBasicMaterial({ color: 0xFFFBEB }));
        bulb.position.set(hx, 2.45, 0.47);
        fl.add(headL, bulb);
    });

    // Tiang Mast & Silinder Hidrolik
    [-0.42, 0.42].forEach(mx => {
        const mast = new THREE.Mesh(new THREE.BoxGeometry(0.09, 2.4, 0.1), MAT.metalDark);
        mast.position.set(mx, 1.35, 0.85);
        fl.add(mast);
    });
    const hydroCyl = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 2.2, 8), MAT.metalSilver);
    hydroCyl.position.set(0, 1.35, 0.85);
    fl.add(hydroCyl);

    // Garpu Angkat Baja (Forks Carriage)
    [-0.3, 0.3].forEach(fx => {
        const forkV = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.6, 0.04), MAT.metalSilver);
        forkV.position.set(fx, 0.48, 0.95);
        const forkH = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.04, 1.15), MAT.metalSilver);
        forkH.position.set(fx, 0.14, 1.5);
        fl.add(forkV, forkH);
    });

    // 4 Roda Karet Industri
    const wGeo = new THREE.CylinderGeometry(0.3, 0.3, 0.2, 16);
    wGeo.rotateZ(Math.PI / 2);
    [[-0.65, 0.5], [0.65, 0.5], [-0.6, -0.8], [0.6, -0.8]].forEach(([wx, wz]) => {
        const w = new THREE.Mesh(wGeo, MAT.truckTire);
        w.position.set(wx, 0.3, wz);
        fl.add(w);
    });

    // Muatan Kargo Opsional (Pallet, Drums, Crate, Boxes)
    if (withCargo) {
        const pal = new THREE.Mesh(new THREE.BoxGeometry(1.2, 0.12, 1.0), MAT.palletWood);
        pal.position.set(0, 0.22, 1.5);
        fl.add(pal);

        if (cargoType === 'drums') {
            [-0.3, 0.3].forEach(dx => {
                const drum = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.24, 0.85, 12), MAT.truckCabBlue);
                drum.position.set(dx, 0.72, 1.5);
                fl.add(drum);
            });
        } else if (cargoType === 'crate') {
            const crate = new THREE.Mesh(new THREE.BoxGeometry(1.05, 0.75, 0.85), new THREE.MeshStandardMaterial({ color: 0xD97706, roughness: 0.8 }));
            crate.position.set(0, 0.66, 1.5);
            const band1 = new THREE.Mesh(new THREE.BoxGeometry(1.07, 0.04, 0.87), MAT.metalDark);
            band1.position.set(0, 0.5, 1.5);
            fl.add(crate, band1);
        } else {
            const b1 = new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.55, 0.85), MAT.cardboardBox);
            b1.position.set(0, 0.56, 1.5);
            const b2 = new THREE.Mesh(new THREE.BoxGeometry(0.7, 0.45, 0.65), MAT.cardboardBox);
            b2.position.set(0.1, 1.05, 1.5);
            fl.add(b1, b2);
        }
    }

    fl.add(chassis, cw, seat, steerCol, steerWheel, cage, cageRoof, beacon);
    return fl;
}

// 2. High-Mast Electric Reach Truck (Forklift Lorong Sempit Rak Tinggi)
function createReachTruck(x, z, rotY, liftHeight = 2.8, cargoType = 'boxes') {
    const rt = new THREE.Group();
    rt.position.set(x, 0, z);
    rt.rotation.y = rotY;

    // Sasis Ramping Reach Truck (Navy & Sky Blue)
    const chassis = new THREE.Mesh(new THREE.BoxGeometry(1.15, 0.55, 1.8), new THREE.MeshStandardMaterial({ color: 0x0284C7, roughness: 0.35 }));
    chassis.position.set(0, 0.4, -0.1);

    // Kaki Penopang Roda Depan (Outrigger Legs)
    [-0.45, 0.45].forEach(ox => {
        const leg = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.28, 1.4), MAT.metalDark);
        leg.position.set(ox, 0.2, 0.6);
        const loadWheel = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.12, 0.14, 12), MAT.truckTire);
        loadWheel.rotateZ(Math.PI / 2);
        loadWheel.position.set(ox, 0.14, 1.2);
        rt.add(leg, loadWheel);
    });

    // Roda Penggerak Belakang
    const driveWheel = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.22, 0.18, 16), MAT.truckTire);
    driveWheel.rotateZ(Math.PI / 2);
    driveWheel.position.set(0, 0.22, -0.75);
    rt.add(driveWheel);

    // Kompartemen Operator Berdiri & Panel Kontrol Joystick
    const cabWall = new THREE.Mesh(new THREE.BoxGeometry(0.95, 1.2, 0.08), MAT.metalDark);
    cabWall.position.set(0, 1.1, -0.85);
    const consoleM = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.35, 0.3), MAT.metalDark);
    consoleM.position.set(0.15, 1.05, -0.4);
    rt.add(cabWall, consoleM);

    // Operator Reach Truck
    const opTorso = new THREE.Mesh(new THREE.BoxGeometry(0.38, 0.48, 0.24), new THREE.MeshStandardMaterial({ color: 0xFACC15 }));
    opTorso.position.set(0, 1.15, -0.4);
    const opHead = new THREE.Mesh(new THREE.SphereGeometry(0.12, 8, 8), new THREE.MeshStandardMaterial({ color: 0xD4A373 }));
    opHead.position.set(0, 1.55, -0.4);
    const opHelm = new THREE.Mesh(new THREE.SphereGeometry(0.14, 8, 8), new THREE.MeshStandardMaterial({ color: 0xFFFFFF }));
    opHelm.position.set(0, 1.6, -0.4);
    rt.add(opTorso, opHead, opHelm);

    // Mast Teleskopik Tinggi Triplex (Mencapai 4.8m)
    const mastH = 4.8;
    [-0.35, 0.35].forEach(mx => {
        const outerMast = new THREE.Mesh(new THREE.BoxGeometry(0.08, mastH, 0.08), MAT.metalDark);
        outerMast.position.set(mx, mastH / 2, 0.45);
        rt.add(outerMast);
    });
    const mastCross = new THREE.Mesh(new THREE.BoxGeometry(0.8, 0.12, 0.06), MAT.metalDark);
    mastCross.position.set(0, mastH - 0.1, 0.45);
    rt.add(mastCross);

    // Carriage Garpu Terangkat Tinggi Sesuai Rak
    const carriage = new THREE.Group();
    carriage.position.set(0, liftHeight, 0.45);
    const cPlate = new THREE.Mesh(new THREE.BoxGeometry(0.65, 0.35, 0.06), MAT.metalSilver);
    cPlate.position.set(0, 0.2, 0);
    [-0.22, 0.22].forEach(fx => {
        const forkV = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.55, 0.04), MAT.metalSilver);
        forkV.position.set(fx, 0.28, 0.05);
        const forkH = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.04, 1.1), MAT.metalSilver);
        forkH.position.set(fx, 0.02, 0.55);
        carriage.add(forkV, forkH);
    });

    // Kargo Terangkat di Atas Garpu
    const pal = new THREE.Mesh(new THREE.BoxGeometry(1.2, 0.12, 1.0), MAT.palletWood);
    pal.position.set(0, 0.1, 0.55);
    const box = new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.65, 0.85), (cargoType === 'drums') ? MAT.truckCabBlue : MAT.cardboardBox);
    box.position.set(0, 0.48, 0.55);
    carriage.add(pal, box);
    rt.add(carriage);

    // Blue Pedestrian Safety Spotlight (Sorot Lampu Keselamatan Biru di Lantai)
    const blueSpot = new THREE.Mesh(new THREE.CircleGeometry(0.45, 16), new THREE.MeshBasicMaterial({ color: 0x38BDF8, transparent: true, opacity: 0.65 }));
    blueSpot.rotation.x = -Math.PI / 2;
    blueSpot.position.set(0, 0.03, 2.8);
    rt.add(chassis, blueSpot);

    return rt;
}

// 3. Electric Industrial Tow Tractor & Cargo Carts (Kereta Tarik Kargo Dalam Gudang)
function createWarehouseTowTractor(x, z, rotY) {
    const train = new THREE.Group();
    train.position.set(x, 0, z);
    train.rotation.y = rotY;

    // Tugger Tractor
    const tug = new THREE.Group();
    const tugBody = new THREE.Mesh(new THREE.BoxGeometry(1.1, 0.55, 1.6), MAT.yellowSafety);
    tugBody.position.set(0, 0.38, 0);
    const bumper = new THREE.Mesh(new THREE.BoxGeometry(1.15, 0.25, 0.15), MAT.metalDark);
    bumper.position.set(0, 0.25, 0.85);
    const tSeat = new THREE.Mesh(new THREE.BoxGeometry(0.45, 0.35, 0.4), MAT.metalDark);
    tSeat.position.set(0, 0.8, -0.2);

    // Operator Pengemudi Tow Tractor
    const drvTorso = new THREE.Mesh(new THREE.BoxGeometry(0.4, 0.45, 0.24), new THREE.MeshStandardMaterial({ color: 0xFACC15 }));
    drvTorso.position.set(0, 1.15, -0.2);
    const drvHead = new THREE.Mesh(new THREE.SphereGeometry(0.12, 8, 8), new THREE.MeshStandardMaterial({ color: 0xD4A373 }));
    drvHead.position.set(0, 1.52, -0.2);
    const drvHelm = new THREE.Mesh(new THREE.SphereGeometry(0.14, 8, 8), new THREE.MeshStandardMaterial({ color: 0xFFFFFF }));
    drvHelm.position.set(0, 1.56, -0.2);
    tug.add(tugBody, bumper, tSeat, drvTorso, drvHead, drvHelm);

    // Roda Tug
    const twGeo = new THREE.CylinderGeometry(0.22, 0.22, 0.15, 12);
    twGeo.rotateZ(Math.PI / 2);
    [[-0.52, 0.45], [0.52, 0.45], [-0.52, -0.45], [0.52, -0.45]].forEach(([wx, wz]) => {
        const tw = new THREE.Mesh(twGeo, MAT.truckTire);
        tw.position.set(wx, 0.22, wz);
        tug.add(tw);
    });
    train.add(tug);

    // Gandengan Gerobak Flatbed Cart 1 & Cart 2
    [-2.2, -4.6].forEach((cz, cIdx) => {
        const cart = new THREE.Group();
        cart.position.set(0, 0, cz);

        const towBar = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.06, 0.9), MAT.metalDark);
        towBar.position.set(0, 0.18, 0.65);
        const cBed = new THREE.Mesh(new THREE.BoxGeometry(1.3, 0.1, 1.5), MAT.metalDark);
        cBed.position.set(0, 0.24, 0);
        const cRim = new THREE.Mesh(new THREE.BoxGeometry(1.34, 0.06, 1.54), MAT.yellowSafety);
        cRim.position.set(0, 0.3, 0);
        cart.add(towBar, cBed, cRim);

        // 4 Roda Kastor Cart
        const cwGeo = new THREE.CylinderGeometry(0.12, 0.12, 0.08, 10);
        cwGeo.rotateZ(Math.PI / 2);
        [[-0.55, 0.5], [0.55, 0.5], [-0.55, -0.5], [0.55, -0.5]].forEach(([wx, wz]) => {
            const cw = new THREE.Mesh(cwGeo, MAT.truckTire);
            cw.position.set(wx, 0.12, wz);
            cart.add(cw);
        });

        // Muatan Cart
        if (cIdx === 0) {
            [-0.28, 0.28].forEach(dx => {
                const drum = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.22, 0.75, 12), MAT.truckCabBlue);
                drum.position.set(dx, 0.68, 0);
                cart.add(drum);
            });
        } else {
            const b1 = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.45, 0.7), MAT.cardboardBox);
            b1.position.set(0, 0.52, -0.15);
            const b2 = new THREE.Mesh(new THREE.BoxGeometry(0.65, 0.35, 0.55), MAT.cardboardBox);
            b2.position.set(0.1, 0.92, -0.1);
            cart.add(b1, b2);
        }
        train.add(cart);
    });

    return train;
}

// 4. Stylized 3D Logistics Worker (Petugas Gudang dengan Rompi & Helm Keselamatan)
function createWarehouseWorker(x, z, rotY, activity = 'checker') {
    const worker = new THREE.Group();
    worker.position.set(x, 0, z);
    worker.rotation.y = rotY;

    const pantsMat = new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.7 });
    const vestMat = new THREE.MeshStandardMaterial({ color: 0xFACC15, roughness: 0.4 });
    const skinMat = new THREE.MeshStandardMaterial({ color: 0xD4A373, roughness: 0.6 });
    const helmetMat = new THREE.MeshStandardMaterial({ color: (activity === 'supervisor') ? 0xFFFFFF : 0xFACC15 });

    // Kaki & Sepatu Safety
    [-0.13, 0.13].forEach(lx => {
        const leg = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.8, 0.17), pantsMat);
        leg.position.set(lx, 0.4, 0);
        const boot = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.14, 0.24), MAT.metalDark);
        boot.position.set(lx, 0.07, 0.03);
        worker.add(leg, boot);
    });

    // Tubuh & Rompi Keselamatan Fluorescent
    const torso = new THREE.Mesh(new THREE.BoxGeometry(0.44, 0.55, 0.26), vestMat);
    torso.position.set(0, 1.08, 0);
    const stripe1 = new THREE.Mesh(new THREE.BoxGeometry(0.45, 0.06, 0.27), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
    stripe1.position.set(0, 1.15, 0);
    const stripe2 = new THREE.Mesh(new THREE.BoxGeometry(0.45, 0.06, 0.27), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
    stripe2.position.set(0, 0.95, 0);
    worker.add(torso, stripe1, stripe2);

    // Kepala & Helm Keselamatan (Hard Hat)
    const head = new THREE.Mesh(new THREE.SphereGeometry(0.12, 10, 10), skinMat);
    head.position.set(0, 1.5, 0);
    const helmet = new THREE.Mesh(new THREE.SphereGeometry(0.14, 10, 10), helmetMat);
    helmet.position.set(0, 1.55, 0);
    worker.add(head, helmet);

    // Lengan & Aksesoris Sesuai Peran
    if (activity === 'checker') {
        const rArm = new THREE.Mesh(new THREE.BoxGeometry(0.11, 0.38, 0.11), vestMat);
        rArm.position.set(0.28, 1.18, 0.18);
        rArm.rotation.x = -0.7;
        const scanner = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.14, 0.16), MAT.metalDark);
        scanner.position.set(0.28, 1.25, 0.38);
        const laser = new THREE.Mesh(new THREE.CylinderGeometry(0.008, 0.008, 1.2, 6), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
        laser.rotateX(Math.PI / 2);
        laser.position.set(0.28, 1.25, 1.0);

        const lArm = new THREE.Mesh(new THREE.BoxGeometry(0.11, 0.38, 0.11), vestMat);
        lArm.position.set(-0.28, 1.12, 0.15);
        lArm.rotation.x = -0.5;
        const tablet = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.16, 0.02), MAT.metalDark);
        tablet.position.set(-0.28, 1.15, 0.28);
        tablet.rotation.x = 0.5;
        const screen = new THREE.Mesh(new THREE.PlaneGeometry(0.19, 0.13), new THREE.MeshBasicMaterial({ color: 0x38BDF8 }));
        screen.position.set(-0.28, 1.15, 0.29);
        screen.rotation.x = 0.5;

        worker.add(rArm, scanner, laser, lArm, tablet, screen);
    } else if (activity === 'clipboard') {
        [-0.26, 0.26].forEach((ax, idx) => {
            const arm = new THREE.Mesh(new THREE.BoxGeometry(0.11, 0.38, 0.11), vestMat);
            arm.position.set(ax, 1.12, 0.14);
            arm.rotation.x = -0.6;
            worker.add(arm);
        });
        const board = new THREE.Mesh(new THREE.BoxGeometry(0.26, 0.34, 0.02), new THREE.MeshStandardMaterial({ color: 0x92400E }));
        board.position.set(0, 1.16, 0.3);
        board.rotation.x = 0.6;
        const paper = new THREE.Mesh(new THREE.PlaneGeometry(0.22, 0.28), new THREE.MeshBasicMaterial({ color: 0xF8FAFC }));
        paper.position.set(0, 1.16, 0.31);
        paper.rotation.x = 0.6;
        worker.add(board, paper);
    } else {
        [-0.28, 0.28].forEach(ax => {
            const arm = new THREE.Mesh(new THREE.BoxGeometry(0.11, 0.44, 0.11), vestMat);
            arm.position.set(ax, 1.05, 0);
            worker.add(arm);
        });
    }

    return worker;
}

// 5. Pallet Staging Stack (Tumpukan Palet Kayu Kosong)
function createEmptyPalletStack(x, z, count = 7) {
    const stack = new THREE.Group();
    stack.position.set(x, 0, z);
    for (let i = 0; i < count; i++) {
        const pal = new THREE.Mesh(new THREE.BoxGeometry(1.4, 0.12, 1.2), MAT.palletWood);
        pal.position.y = 0.06 + i * 0.13;
        stack.add(pal);
    }
    return stack;
}

// 6. Secondary Spill Containment Station (Bantalan Penangkal Tumpahan Bahan Kimia / DG)
function createSpillContainmentStation(x, z) {
    const sc = new THREE.Group();
    sc.position.set(x, 0, z);

    const bund = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.25, 1.6), MAT.yellowSafety);
    bund.position.y = 0.125;
    const grate = new THREE.Mesh(new THREE.BoxGeometry(1.5, 0.04, 1.5), MAT.metalDark);
    grate.position.y = 0.25;
    sc.add(bund, grate);

    [[-0.4, -0.4], [0.4, -0.4], [-0.4, 0.4], [0.4, 0.4]].forEach(([dx, dz], idx) => {
        const drumMat = (idx % 2 === 0) ? new THREE.MeshStandardMaterial({ color: 0xDC2626 }) : MAT.truckCabBlue;
        const drum = new THREE.Mesh(new THREE.CylinderGeometry(0.26, 0.26, 0.9, 14), drumMat);
        drum.position.set(dx, 0.72, dz);
        const label = new THREE.Mesh(new THREE.PlaneGeometry(0.18, 0.18), new THREE.MeshBasicMaterial({ color: 0xFACC15 }));
        label.position.set(dx, 0.8, dz + 0.27);
        sc.add(drum, label);
    });

    const kitDrum = new THREE.Mesh(new THREE.CylinderGeometry(0.25, 0.25, 0.85, 12), MAT.yellowSafety);
    kitDrum.position.set(1.4, 0.45, 0);
    const kitSign = new THREE.Mesh(new THREE.BoxGeometry(0.25, 0.14, 0.05), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
    kitSign.position.set(1.4, 0.65, 0.26);
    sc.add(kitDrum, kitSign);

    return sc;
}

// 7. Meja Kerja Perawatan Gudang & Lemari Alat (Maintenance Workbench & Tool Roll Cab)
function createMaintenanceWorkbench(x, z) {
    const wb = new THREE.Group();
    wb.position.set(x, 0, z);

    const tableTop = new THREE.Mesh(new THREE.BoxGeometry(2.6, 0.1, 1.0), MAT.palletWood);
    tableTop.position.set(0, 0.95, 0);
    const frameL = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.9, 0.9), MAT.metalDark);
    frameL.position.set(-1.2, 0.45, 0);
    const frameR = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.9, 0.9), MAT.metalDark);
    frameR.position.set(1.2, 0.45, 0);
    const vise = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.18, 0.22), MAT.metalSilver);
    vise.position.set(-0.95, 1.1, 0.25);
    wb.add(tableTop, frameL, frameR, vise);

    const pegboard = new THREE.Mesh(new THREE.BoxGeometry(2.6, 1.2, 0.04), MAT.metalDark);
    pegboard.position.set(0, 1.6, -0.45);
    wb.add(pegboard);

    const toolCab = new THREE.Mesh(new THREE.BoxGeometry(0.95, 1.05, 0.65), MAT.redHazard);
    toolCab.position.set(1.8, 0.55, 0);
    wb.add(toolCab);

    return wb;
}

// 8. Stasiun Pembersih Mata & Pancuran Darurat (Emergency Shower & Eyewash)
function createEmergencyEyeWashStation(x, z) {
    const ew = new THREE.Group();
    ew.position.set(x, 0, z);

    const pipeMat = new THREE.MeshStandardMaterial({ color: 0x059669 });
    const bowlMat = MAT.metalSilver;

    const vPipe = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 2.3, 8), pipeMat);
    vPipe.position.set(0, 1.15, 0);
    const bowl = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.14, 0.12, 12), bowlMat);
    bowl.position.set(0, 1.05, 0.15);
    const showerHead = new THREE.Mesh(new THREE.ConeGeometry(0.18, 0.14, 12), bowlMat);
    showerHead.position.set(0, 2.25, 0.25);
    showerHead.rotation.x = Math.PI;

    const sign = new THREE.Mesh(new THREE.BoxGeometry(0.45, 0.3, 0.04), new THREE.MeshBasicMaterial({ color: 0x10B981 }));
    sign.position.set(0, 2.5, 0);
    ew.add(vPipe, bowl, showerHead, sign);

    return ew;
}

// 9. Tempat Sampah Daur Ulang Industri (Industrial 3-Bin Recycling Station)
function createTrashRecyclingStation(x, z) {
    const tr = new THREE.Group();
    tr.position.set(x, 0, z);

    const bins = [
        { color: 0x10B981, label: 'ORGANIC' },
        { color: 0x0284C7, label: 'PAPER/BOX' },
        { color: 0xEF4444, label: 'HAZMAT' }
    ];

    bins.forEach((b, idx) => {
        const binMesh = new THREE.Mesh(new THREE.BoxGeometry(0.48, 0.85, 0.48), new THREE.MeshStandardMaterial({ color: b.color, roughness: 0.5 }));
        binMesh.position.set((idx - 1) * 0.65, 0.425, 0);
        tr.add(binMesh);
    });

    return tr;
}

// 10. Kaca Cembung Keselamatan Persimpangan Lorong (Convex Traffic Safety Mirror)
function createConvexSafetyMirror(x, z, rotY) {
    const cm = new THREE.Group();
    cm.position.set(x, 2.8, z);
    cm.rotation.y = rotY;

    const arm = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, 0.7, 8), MAT.metalDark);
    arm.rotation.x = Math.PI / 4;
    arm.position.set(0, 0, 0.25);
    const rim = new THREE.Mesh(new THREE.CylinderGeometry(0.48, 0.48, 0.08, 16), MAT.yellowSafety);
    rim.rotation.x = Math.PI / 2;
    rim.position.set(0, 0.25, 0.5);
    const mirror = new THREE.Mesh(new THREE.SphereGeometry(0.45, 16, 8, 0, Math.PI * 2, 0, Math.PI / 3), new THREE.MeshStandardMaterial({ color: 0xE2E8F0, metalness: 0.95, roughness: 0.05 }));
    mirror.position.set(0, 0.25, 0.54);
    mirror.rotation.x = Math.PI;

    cm.add(arm, rim, mirror);
    return cm;
}

// FORKLIFT & PETUGAS DERMAGA INBOUND (BAY 1, 2, 3)
// Forklift 1 di Bay 1 Staging (Warna Oranye Linde - Mengangkut Palet Drum Farmasi)
dockGroup.add(createDetailedForklift(-22.0, -26.0, 0, true, 0xEA580C, 'drums'));
dockGroup.add(createWarehouseWorker(-19.5, -26.0, -Math.PI / 2, 'checker'));

// Forklift 2 di Jalur Penghubung Dermaga Inbound (Warna Kuning - Mengangkut Paket Ekspor)
dockGroup.add(createDetailedForklift(-15.0, -26.0, Math.PI / 2, true, 0xF59E0B, 'boxes'));

// Forklift 3 di Bay 3 Staging (Warna Biru Still - Mengangkut Peti Kayu Ekspor)
dockGroup.add(createDetailedForklift(22.0, -26.0, Math.PI, true, 0x0284C7, 'crate'));
dockGroup.add(createWarehouseWorker(18.5, -26.0, Math.PI / 2, 'clipboard'));

// Tumpukan Palet Kosong untuk Staging Cepat
dockGroup.add(createEmptyPalletStack(-30.0, -28.0, 7));
dockGroup.add(createEmptyPalletStack(26.5, -28.0, 7));

// Stasiun Pengisian Baterai Forklift (Battery Charger Bay)
const chargerBay = new THREE.Group();
chargerBay.position.set(15, 0, -25.5);
const padCharger = new THREE.Mesh(new THREE.PlaneGeometry(3.6, 3.8), new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.8 }));
padCharger.rotation.x = -Math.PI / 2;
padCharger.position.y = 0.03;
const chargerUnit = new THREE.Mesh(new THREE.BoxGeometry(0.8, 1.6, 0.6), MAT.injourneyTeal);
chargerUnit.position.set(1.4, 0.8, -1.2);
const chargerScreen = new THREE.Mesh(new THREE.PlaneGeometry(0.4, 0.25), new THREE.MeshBasicMaterial({ color: 0x10B981 }));
chargerScreen.position.set(1.4, 1.25, -0.89);
chargerBay.add(padCharger, chargerUnit, chargerScreen);
chargerBay.add(createDetailedForklift(-0.2, 0, 0, false, 0xF59E0B));
dockGroup.add(chargerBay);

const dockDecal = makeFloorDecal("INBOUND DOCKS", "RECEIVING BAY 1 - 3", 8.0, 3.2, "#38BDF8");
dockDecal.position.set(-5.0, 0.03, -40.0);
signsGroup.add(dockDecal);

// ─────────────────────────────────────────────────────────────────────────────
// 5. GERBANG SCAN RFID AIDC (Z = -27.0)
// ─────────────────────────────────────────────────────────────────────────────
const rfidPortalGroup = new THREE.Group();
rfidPortalGroup.position.set(-5.0, 0, -27.0);

const pPostL = new THREE.Mesh(new THREE.BoxGeometry(0.4, 5.6, 0.4), MAT.injourneyTeal);
pPostL.position.set(-3.2, 2.8, 0);
const pPostR = new THREE.Mesh(new THREE.BoxGeometry(0.4, 5.6, 0.4), MAT.injourneyTeal);
pPostR.position.set(3.2, 2.8, 0);
const pArch = new THREE.Mesh(new THREE.BoxGeometry(6.8, 0.45, 0.45), MAT.injourneyTeal);
pArch.position.set(0, 5.5, 0);

const scanBarMat = new THREE.MeshBasicMaterial({ color: 0x00F5FF });
const scanBarL = new THREE.Mesh(new THREE.BoxGeometry(0.12, 3.8, 0.12), scanBarMat);
scanBarL.position.set(-2.9, 2.8, 0);
const scanBarR = new THREE.Mesh(new THREE.BoxGeometry(0.12, 3.8, 0.12), scanBarMat);
scanBarR.position.set(2.9, 2.8, 0);

const rfidWaveGeo = new THREE.RingGeometry(0.2, 3.4, 32);
const rfidWaveMat = new THREE.MeshBasicMaterial({ color: 0x00F5FF, transparent: true, opacity: 0, side: THREE.DoubleSide });
const rfidWaveMesh = new THREE.Mesh(rfidWaveGeo, rfidWaveMat);
rfidWaveMesh.rotation.x = Math.PI / 2;
rfidWaveMesh.position.set(0, 0.05, 0);

const rfidPlaque = makeMountedSignboard("AIDC RFID SCANNER", "SSCC-18 READER", 5.2, 0.7, "#0CA1AF");
rfidPlaque.position.set(0, 5.5, -0.25);

const rfidFloorDecal = makeFloorDecal("AIDC RFID PORTAL", "AUTOMATED SCANNER", 5.2, 2.2, "#0CA1AF");
rfidFloorDecal.position.set(-5.0, 0.035, -27.0);
signsGroup.add(rfidFloorDecal);

rfidPortalGroup.add(pPostL, pPostR, pArch, scanBarL, scanBarR, rfidWaveMesh, rfidPlaque);
rfidGroup.add(rfidPortalGroup);

// ─────────────────────────────────────────────────────────────────────────────
// 6. JARINGAN KONVEYOR KONTINU & PNEUMATIC DIVERTER ARM
// ─────────────────────────────────────────────────────────────────────────────
const animatedRollers = [];

function createConveyorSegment(x, z, lenX, lenZ, options = {}) {
    const cg = new THREE.Group();
    cg.position.set(x, 0.4, z);

    // Bed Utama Konveyor (Struktur Baja C-Channel Hitam Industri)
    const bed = new THREE.Mesh(new THREE.BoxGeometry(lenX, 0.25, lenZ), MAT.metalDark);
    bed.position.y = -0.12;
    bed.receiveShadow = true;
    cg.add(bed);

    const isZAxis = (lenZ >= lenX);

    // Rel Pengaman Samping Baja & Aksen Teal InJourney
    if (isZAxis) {
        [-lenX / 2 - 0.03, lenX / 2 + 0.03].forEach(px => {
            const sideChannel = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.32, lenZ), MAT.metalDark);
            sideChannel.position.set(px, 0.02, 0);
            const stripe = new THREE.Mesh(new THREE.BoxGeometry(0.065, 0.06, lenZ), MAT.injourneyTeal);
            stripe.position.set(px, 0.12, 0);
            cg.add(sideChannel, stripe);

            if (options.rails !== false) {
                const rail = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.16, lenZ), MAT.yellowSafety);
                rail.position.set(px, 0.26, 0);
                cg.add(rail);
            }
        });
    } else {
        [-lenZ / 2 - 0.03, lenZ / 2 + 0.03].forEach(pz => {
            const sideChannel = new THREE.Mesh(new THREE.BoxGeometry(lenX, 0.32, 0.06), MAT.metalDark);
            sideChannel.position.set(0, 0.02, pz);
            const stripe = new THREE.Mesh(new THREE.BoxGeometry(lenX, 0.06, 0.065), MAT.injourneyTeal);
            stripe.position.set(0, 0.12, pz);
            cg.add(sideChannel, stripe);

            if (options.rails !== false) {
                const rail = new THREE.Mesh(new THREE.BoxGeometry(lenX, 0.16, 0.05), MAT.yellowSafety);
                rail.position.set(0, 0.26, pz);
                cg.add(rail);
            }
        });
    }

    // Kaki Baja Konveyor dengan Plat Landasan Lantai
    const numLegsX = Math.max(2, Math.round(lenX / 3.8));
    const numLegsZ = Math.max(2, Math.round(lenZ / 3.8));
    for (let ix = 0; ix < numLegsX; ix++) {
        for (let iz = 0; iz < numLegsZ; iz++) {
            const lx = -lenX / 2 + 0.25 + (ix / Math.max(1, numLegsX - 1)) * (lenX - 0.5);
            const lz = -lenZ / 2 + 0.25 + (iz / Math.max(1, numLegsZ - 1)) * (lenZ - 0.5);
            const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.055, 0.055, 0.4, 8), MAT.metalDark);
            leg.position.set(lx, -0.2, lz);
            const baseFoot = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.03, 0.18), MAT.metalDark);
            baseFoot.position.set(lx, -0.39, lz);
            cg.add(leg, baseFoot);
        }
    }

    // Rollers Berputar Presisi Stainless Steel
    // Rotasi geometri langsung diterapkan di level BufferGeometry sehingga mesh.rotation selalu murni (0,0,0)
    if (isZAxis) {
        const rollerLen = lenX - 0.16;
        const rollerGeo = new THREE.CylinderGeometry(0.055, 0.055, rollerLen, 14);
        rollerGeo.rotateZ(Math.PI / 2); // Panjang silinder membentang sepanjang sumbu X

        for (let rz = -lenZ / 2 + 0.25; rz <= lenZ / 2 - 0.25; rz += 0.38) {
            const rol = new THREE.Mesh(rollerGeo, MAT.conveyorRoller);
            rol.position.set(0, 0.06, rz);
            cg.add(rol);
            animatedRollers.push({ mesh: rol, axis: 'x', dir: 1 });
        }
    } else {
        const rollerLen = lenZ - 0.16;
        const rollerGeo = new THREE.CylinderGeometry(0.055, 0.055, rollerLen, 14);
        rollerGeo.rotateX(Math.PI / 2); // Panjang silinder membentang sepanjang sumbu Z

        for (let rx = -lenX / 2 + 0.25; rx <= lenX / 2 - 0.25; rx += 0.38) {
            const rol = new THREE.Mesh(rollerGeo, MAT.conveyorRoller);
            rol.position.set(rx, 0.06, 0);
            cg.add(rol);
            animatedRollers.push({ mesh: rol, axis: 'z', dir: -1 });
        }
    }
    return cg;
}

// Meja Belokan Caster 90 Derajat (Omnidirectional Ball Transfer Corner Bed)
function createCornerTransferBed(x, z, size = 2.8) {
    const cbg = new THREE.Group();
    cbg.position.set(x, 0.4, z);

    // Bed Platform
    const bed = new THREE.Mesh(new THREE.BoxGeometry(size, 0.25, size), MAT.metalDark);
    bed.position.y = -0.12;
    bed.receiveShadow = true;
    cbg.add(bed);

    // Plat Atas Kromium
    const plate = new THREE.Mesh(new THREE.BoxGeometry(size - 0.1, 0.02, size - 0.1), MAT.metalSilver);
    plate.position.y = 0.01;
    cbg.add(plate);

    // Grid Bola Transfer Baja Multi-Arah (Omnidirectional Ball Casters)
    const ballGeo = new THREE.SphereGeometry(0.048, 12, 10);
    const spacing = 0.38;
    for (let bx = -size / 2 + 0.35; bx <= size / 2 - 0.35; bx += spacing) {
        for (let bz = -size / 2 + 0.35; bz <= size / 2 - 0.35; bz += spacing) {
            const cup = new THREE.Mesh(new THREE.CylinderGeometry(0.058, 0.058, 0.04, 10), MAT.metalDark);
            cup.position.set(bx, 0.03, bz);
            const ball = new THREE.Mesh(ballGeo, MAT.metalSilver);
            ball.position.set(bx, 0.06, bz);
            cbg.add(cup, ball);
        }
    }

    // Pagar Pengarah Sudut Luar (Chamfer Deflector Guide 45 Derajat di Sudut Barat Daya)
    const outRailW = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.35, size), MAT.metalDark);
    outRailW.position.set(-size / 2 - 0.03, 0.12, 0);
    const outRailS = new THREE.Mesh(new THREE.BoxGeometry(size, 0.35, 0.06), MAT.metalDark);
    outRailS.position.set(0, 0.12, size / 2 + 0.03);
    const deflector = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.38, 1.4), MAT.yellowSafety);
    deflector.rotation.y = Math.PI / 4;
    deflector.position.set(-size / 2 + 0.45, 0.14, size / 2 - 0.45);
    cbg.add(outRailW, outRailS, deflector);

    // Kaki Penopang
    [-size / 2 + 0.3, size / 2 - 0.3].forEach(lx => {
        [-size / 2 + 0.3, size / 2 - 0.3].forEach(lz => {
            const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 0.4, 8), MAT.metalDark);
            leg.position.set(lx, -0.2, lz);
            cbg.add(leg);
        });
    });

    return cbg;
}

// 1. Jalur Infeed Dermaga Muat Bay 2 (-5.0, Z = -35.5 s.d. -21.0)
// Kargo dibongkar dari truk dan langsung mengalir lurus melintasi gerbang scan AIDC RFID (-27.0)
conveyorGroup.add(createConveyorSegment(-5.0, -28.25, 2.4, 14.5));

// 2. Jalur Feeder Dermaga 1 (-26.0) & Dermaga 3 (+22.0) — Rapi & Mandiri tanpa sambungan kusut
conveyorGroup.add(createConveyorSegment(-26.0, -31.5, 2.4, 8.0));
conveyorGroup.add(createConveyorSegment(22.0, -31.5, 2.4, 8.0));

// 3. Jalur Internal Terowongan Mesin X-Ray Screening (Z = -21.0 s.d. -16.0)
conveyorGroup.add(createConveyorSegment(-5.0, -18.5, 2.4, 5.0, { rails: false }));

// 4. Diverter Arm Pneumatik di X = -5.0, Z = -16.5
const diverterGroup = new THREE.Group();
diverterGroup.position.set(-5.0, 0.5, -16.5);
const divBase = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.45, 0.8), MAT.yellowSafety);
divBase.position.set(-1.35, 0, 0);
const divPiston = new THREE.Mesh(new THREE.CylinderGeometry(0.065, 0.065, 1.8, 12), MAT.metalSilver);
divPiston.rotateZ(Math.PI / 2);
divPiston.position.set(-0.45, 0, 0);
const divArm = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.45, 1.8), MAT.redHazard);
divArm.position.set(0.6, 0, 0);
diverterGroup.add(divBase, divPiston, divArm);
scene.add(diverterGroup);

// 5. Jalur Konveyor Isolasi Masuk ke Ruang Karantina (Location 3B di X = 22, Z = -16.5)
// Membentang lurus & presisi ke timur (+X) dari X = -3.8 sampai pintu Ruang Karantina di X = 24.5
conveyorGroup.add(createConveyorSegment(10.35, -16.5, 28.3, 2.4));

// 6. Jalur Utama Kargo Aman (CLEARED) Keluar X-Ray LURUS Menuju Central Storage (Z = -16.0 s.d. Z = 6.6)
// 100% Lurus tanpa zig-zag atau sambungan miring! (Panjang 22.6m, center Z = -4.7)
conveyorGroup.add(createConveyorSegment(-5.0, -4.7, 2.4, 22.6));

// 7. Meja Belokan Caster Bed 90 Derajat di Titik Pertemuan X = -5.0, Z = 8.0
conveyorGroup.add(createCornerTransferBed(-5.0, 8.0, 2.8));

// 8. Segmen Penghubung Masuk ke Meja Perakitan ULD (Dari X = -3.6 ke X = -2.2 pada Z = 8.0)
conveyorGroup.add(createConveyorSegment(-2.9, 8.0, 1.4, 2.4));

// 9. Konveyor Transfer Central Storage ke Smart Floor Scale 15T (Dari X = 2.2 ke X = 35.0 pada Z = 8.0)
// Panjang 32.8m, center X = 18.6
conveyorGroup.add(createConveyorSegment(18.6, 8.0, 32.8, 2.4));

// 10. Rel Transfer Timbangan ke Gerbang Airside & High-Loader (Dari X = 37.0 ke X = 63.0 pada Z = 8.0)
conveyorGroup.add(createConveyorSegment(50.0, 8.0, 26.0, 2.4));

// ─────────────────────────────────────────────────────────────────────────────
// 7. TEROWONGAN X-RAY SCREENING (LINES 1, 2, 3) & STASIUN OPERATOR AVSEC
// ─────────────────────────────────────────────────────────────────────────────
const xrBeaconMat = new THREE.MeshBasicMaterial({ color: 0x10B981 });

function createDetailedXrayTunnel(xPos, lineNum) {
    const xr = new THREE.Group();
    xr.position.set(xPos, 0, -18.5);

    const tunnel = new THREE.Mesh(new THREE.BoxGeometry(3.4, 3.2, 5.0), MAT.metalDark);
    tunnel.position.y = 1.6;
    tunnel.castShadow = true;

    // Aksen Stripe Teal
    [-1.71, 1.71].forEach(px => {
        const sidePanel = new THREE.Mesh(new THREE.BoxGeometry(0.04, 2.2, 4.5), MAT.injourneyTeal);
        sidePanel.position.set(px, 1.6, 0);
        xr.add(sidePanel);
    });

    const core = new THREE.Mesh(new THREE.BoxGeometry(2.3, 2.4, 5.05), new THREE.MeshBasicMaterial({ color: 0x090D16 }));
    core.position.y = 1.5;

    // Tirai Timbal
    [-2.52, 2.52].forEach(cz => {
        const curtain = new THREE.Mesh(new THREE.BoxGeometry(2.15, 2.2, 0.08), new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.9 }));
        curtain.position.set(0, 1.5, cz);
        xr.add(curtain);
    });

    // Generator Dome Oranye di Atas
    const dome = new THREE.Mesh(new THREE.CylinderGeometry(0.65, 0.65, 0.5, 16), new THREE.MeshStandardMaterial({ color: 0xF59E0B, metalness: 0.6 }));
    dome.position.set(0, 3.45, 0);

    // Beacon Status LED
    const beaconMat = (lineNum === 2) ? xrBeaconMat : new THREE.MeshBasicMaterial({ color: 0x10B981 });
    const beacon = new THREE.Mesh(new THREE.SphereGeometry(0.18, 12, 12), beaconMat);
    beacon.position.set(1.5, 3.1, 2.52);

    xr.add(tunnel, core, dome, beacon);
    return xr;
}

// 3 Unit Mesin Dual-View X-Ray Screening
[-13.0, -5.0, 3.0].forEach((x, idx) => {
    xrayGroup.add(createDetailedXrayTunnel(x, idx + 1));
});

// Stasiun Meja Operator AVSEC Lengkap dengan Monitor Dual-Screen
function createAvsecOperatorStation(x, z) {
    const st = new THREE.Group();
    st.position.set(x, 0, z);

    const desk = new THREE.Mesh(new THREE.BoxGeometry(2.2, 0.95, 1.1), MAT.metalSilver);
    desk.position.set(0, 0.475, 0);

    // Dual Screens: Organik (Orange) & Logam (Blue)
    const monL = new THREE.Mesh(new THREE.BoxGeometry(0.85, 0.55, 0.06), MAT.metalDark);
    monL.position.set(-0.48, 1.25, -0.1);
    monL.rotation.y = 0.12;
    const scrL = new THREE.Mesh(new THREE.PlaneGeometry(0.78, 0.48), new THREE.MeshBasicMaterial({ color: 0xF59E0B }));
    scrL.position.set(-0.48, 1.25, -0.06);
    scrL.rotation.y = 0.12;

    const monR = new THREE.Mesh(new THREE.BoxGeometry(0.85, 0.55, 0.06), MAT.metalDark);
    monR.position.set(0.48, 1.25, -0.1);
    monR.rotation.y = -0.12;
    const scrR = new THREE.Mesh(new THREE.PlaneGeometry(0.78, 0.48), new THREE.MeshBasicMaterial({ color: 0x0284C7 }));
    scrR.position.set(0.48, 1.25, -0.06);
    scrR.rotation.y = -0.12;

    const chair = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.55, 0.55), new THREE.MeshStandardMaterial({ color: 0x0F172A }));
    chair.position.set(0, 0.5, 0.85);

    st.add(desk, monL, scrL, monR, scrR, chair);
    return st;
}

xrayGroup.add(createAvsecOperatorStation(-9.0, -12.0));
xrayGroup.add(createAvsecOperatorStation(-1.0, -12.0));

// Gantry Arch "AVSEC CLEARANCE GANTRY" di Z = -10.0
const gantryArch = new THREE.Group();
gantryArch.position.set(-5.0, 0, -10.0);
const gP1 = new THREE.Mesh(new THREE.BoxGeometry(0.35, 4.4, 0.35), MAT.metalDark);
gP1.position.set(-9.8, 2.2, 0);
const gP2 = new THREE.Mesh(new THREE.BoxGeometry(0.35, 4.4, 0.35), MAT.metalDark);
gP2.position.set(9.8, 2.2, 0);
const gBeam = new THREE.Mesh(new THREE.BoxGeometry(20.0, 0.45, 0.4), MAT.metalDark);
gBeam.position.set(0, 4.4, 0);

const gantryCanvas = document.createElement('canvas');
gantryCanvas.width = 512;
gantryCanvas.height = 90;
const gCtx = gantryCanvas.getContext('2d');
gCtx.fillStyle = '#081428';
gCtx.fillRect(0, 0, 512, 90);
gCtx.strokeStyle = '#0CA1AF';
gCtx.lineWidth = 4;
gCtx.strokeRect(4, 4, 504, 82);
gCtx.fillStyle = '#0CA1AF';
gCtx.font = '900 24px Barlow, sans-serif';
gCtx.textAlign = 'center';
gCtx.fillText('AVSEC CLEARANCE GANTRY', 256, 38);
gCtx.fillStyle = '#10B981';
gCtx.font = 'bold 15px monospace';
gCtx.fillText('● LINE 1: READY  ● LINE 2: ACTIVE  ● LINE 3: READY', 256, 66);
const gantrySign = new THREE.Mesh(new THREE.PlaneGeometry(12.0, 0.85), new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(gantryCanvas) }));
gantrySign.position.set(0, 4.4, 0.22);
gantryArch.add(gP1, gP2, gBeam, gantrySign);
xrayGroup.add(gantryArch);

const xrFloorDecal = makeFloorDecal("SECURITY SCREENING", "DUAL-VIEW X-RAY & MATRIX INSPECTION", 10.0, 3.2, "#F59E0B");
xrFloorDecal.position.set(-5.0, 0.035, -18.5);
signsGroup.add(xrFloorDecal);

// ─────────────────────────────────────────────────────────────────────────────
// 8. RUANG KARANTINA RESMI (LOCATION 3B DI X = 22, Z = -16.5)
// ─────────────────────────────────────────────────────────────────────────────
let sirenRotMesh = null;

function createRealisticQuarantineRoom() {
    const q = new THREE.Group();
    q.position.set(22, 0, -16.5);

    // Lantai Epoksi Dark Charcoal Anti-Statik
    const f = new THREE.Mesh(new THREE.PlaneGeometry(16, 10), MAT.floorQuarantine);
    f.rotation.x = -Math.PI / 2;
    f.position.y = 0.04;
    f.receiveShadow = true;
    q.add(f);

    // Garis Hazard Kuning di Tepi Lantai
    const hazMat = new THREE.MeshBasicMaterial({ color: 0xF59E0B });
    for (let hx = -7.5; hx <= 7.5; hx += 1.2) {
        const hStrip = new THREE.Mesh(new THREE.PlaneGeometry(0.6, 0.18), hazMat);
        hStrip.rotation.x = -Math.PI / 2;
        hStrip.position.set(hx, 0.045, -4.85);
        q.add(hStrip);
    }

    // Dinding Partisi & Kaca Balistik
    const backW = new THREE.Mesh(new THREE.BoxGeometry(16, 3.8, 0.4), MAT.wallBuilding);
    backW.position.set(0, 1.9, -5);
    const rightW = new THREE.Mesh(new THREE.BoxGeometry(0.4, 3.8, 10), MAT.wallBuilding);
    rightW.position.set(8, 1.9, 0);

    const leftBack = new THREE.Mesh(new THREE.BoxGeometry(0.4, 3.8, 3.2), MAT.wallBuilding);
    leftBack.position.set(-8, 1.9, -3.4);
    const leftFront = new THREE.Mesh(new THREE.BoxGeometry(0.4, 3.8, 3.2), MAT.wallBuilding);
    leftFront.position.set(-8, 1.9, 3.4);
    const leftLintel = new THREE.Mesh(new THREE.BoxGeometry(0.4, 1.4, 3.6), MAT.metalDark);
    leftLintel.position.set(-8, 3.1, 0);

    const glassW = new THREE.Mesh(new THREE.BoxGeometry(10.5, 3.6, 0.15), MAT.wallGlass);
    glassW.position.set(-2.75, 1.8, 5);

    // Pintu Bulkhead Restricted Sisi Kanan
    const doorLeaf = new THREE.Mesh(new THREE.BoxGeometry(1.6, 2.6, 0.08), MAT.metalDark);
    doorLeaf.position.set(4.5, 1.3, 5);

    q.add(backW, rightW, leftBack, leftFront, leftLintel, glassW, doorLeaf);

    // Meja Stainless Steel Pemeriksaan Sekunder (di X = 2.5, Z = 0 -> World: 24.5, -16.5)
    const examTable = new THREE.Mesh(new THREE.BoxGeometry(4.2, 0.85, 2.6), MAT.metalSilver);
    examTable.position.set(2.5, 0.425, 0);
    const examTop = new THREE.Mesh(new THREE.BoxGeometry(4.4, 0.08, 2.8), new THREE.MeshStandardMaterial({ color: 0xF8FAFC, metalness: 0.95, roughness: 0.1 }));
    examTop.position.set(2.5, 0.88, 0);
    q.add(examTable, examTop);

    // Alat Uji Forensik / Explosive Trace Detector (ETD)
    const etdBody = new THREE.Mesh(new THREE.BoxGeometry(0.85, 0.65, 0.65), MAT.metalDark);
    etdBody.position.set(3.8, 1.25, -0.7);
    const etdSc = new THREE.Mesh(new THREE.PlaneGeometry(0.4, 0.3), new THREE.MeshBasicMaterial({ color: 0x38BDF8 }));
    etdSc.position.set(3.8, 1.35, -0.37);
    q.add(etdBody, etdSc);

    // Tabung Pengaman Bom / Ledakan (Blast Vessel)
    const blastVessel = new THREE.Mesh(new THREE.CylinderGeometry(0.8, 0.8, 1.8, 20), new THREE.MeshStandardMaterial({ color: 0x475569, metalness: 0.8 }));
    blastVessel.position.set(-4.5, 0.9, -2.8);
    const blastDome = new THREE.Mesh(new THREE.SphereGeometry(0.8, 16, 12, 0, Math.PI * 2, 0, Math.PI / 2), MAT.redHazard);
    blastDome.position.set(-4.5, 1.8, -2.8);
    q.add(blastVessel, blastDome);

    // Lemari Bukti Hazmat Merah
    const hazmatCab = new THREE.Mesh(new THREE.BoxGeometry(2.0, 2.4, 0.8), new THREE.MeshStandardMaterial({ color: 0xDC2626 }));
    hazmatCab.position.set(-1.2, 1.2, -4.3);
    q.add(hazmatCab);

    // 2 Figur Petugas AVSEC (Inspector & Specialist)
    function createAvsecOfficer(ox, oz, rotY, role = 'inspector') {
        const off = new THREE.Group();
        off.position.set(ox, 0, oz);
        off.rotation.y = rotY;

        const pants = new THREE.Mesh(new THREE.BoxGeometry(0.48, 0.9, 0.3), new THREE.MeshStandardMaterial({ color: 0x0D1C42 }));
        pants.position.y = 0.55;
        const vestMat = (role === 'inspector') ? new THREE.MeshStandardMaterial({ color: 0xEA580C }) : new THREE.MeshStandardMaterial({ color: 0xFACC15 });
        const torso = new THREE.Mesh(new THREE.BoxGeometry(0.62, 0.75, 0.38), vestMat);
        torso.position.y = 1.35;
        const head = new THREE.Mesh(new THREE.BoxGeometry(0.32, 0.34, 0.32), new THREE.MeshStandardMaterial({ color: 0xFDE047, roughness: 0.8 }));
        head.position.y = 1.9;
        const cap = new THREE.Mesh(new THREE.BoxGeometry(0.36, 0.12, 0.42), new THREE.MeshStandardMaterial({ color: 0x0D1C42 }));
        cap.position.set(0, 2.05, 0.04);
        off.add(pants, torso, head, cap);
        return off;
    }
    q.add(createAvsecOfficer(2.2, 1.4, Math.PI, 'inspector'));
    q.add(createAvsecOfficer(5.8, -1.8, Math.PI / 2, 'specialist'));

    // Suar Sirine Merah Alarm Berputar
    const qSiren = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.25, 0.45, 12), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
    sirenRotMesh = qSiren;
    qSiren.position.set(5.5, 4.1, 5);
    q.add(qSiren);

    const qPlaque = makeMountedSignboard("RESTRICTED QUARANTINE", "ETD FORENSIC & HAZMAT ISOLATION", 8.2, 1.0, "#EF4444");
    qPlaque.position.set(0, 3.4, 5.06);
    q.add(qPlaque);

    const qFloorDecal = makeFloorDecal("QUARANTINE AREA", "AUTHORIZED AVSEC ONLY", 7.0, 3.0, "#EF4444");
    qFloorDecal.position.set(22.0, 0.045, -16.5);
    signsGroup.add(qFloorDecal);

    return q;
}
xrayGroup.add(createRealisticQuarantineRoom());

// ─────────────────────────────────────────────────────────────────────────────
// 9. CENTRAL STORAGE & ULD BUILD-UP WORKSTATION (X = 0, Z = 8.0)
// ─────────────────────────────────────────────────────────────────────────────
function createDetailedStorage() {
    const cs = new THREE.Group();
    cs.position.set(0, 0, 0);

    // Pad Epoksi Gudang Central Storage
    const pad = new THREE.Mesh(new THREE.PlaneGeometry(30, 22), new THREE.MeshStandardMaterial({ color: 0xCBD5E1, roughness: 0.55, metalness: 0.12 }));
    pad.rotation.x = -Math.PI / 2;
    pad.position.set(0, 0.04, 6);
    cs.add(pad);

    // Garis Zebra Hazard Kuning-Hitam di Tepi Gudang
    const zebraMatY = new THREE.MeshBasicMaterial({ color: 0xF59E0B });
    const zebraMatB = new THREE.MeshBasicMaterial({ color: 0x1E293B });
    for (let zx = -14.5; zx < 14.5; zx += 1.2) {
        const isY = (Math.round((zx + 15) / 1.2) % 2 === 0);
        const zN = new THREE.Mesh(new THREE.PlaneGeometry(0.6, 0.22), isY ? zebraMatY : zebraMatB);
        zN.rotation.x = -Math.PI / 2;
        zN.position.set(zx + 0.3, 0.05, -4.85);
        const zS = new THREE.Mesh(new THREE.PlaneGeometry(0.6, 0.22), isY ? zebraMatY : zebraMatB);
        zS.rotation.x = -Math.PI / 2;
        zS.position.set(zx + 0.3, 0.05, 16.85);
        cs.add(zN, zS);
    }

    // Jalur Hijau Pejalan Kaki (Pedestrian Safety Walkway)
    const walkway = new THREE.Mesh(new THREE.PlaneGeometry(1.2, 18.0), new THREE.MeshStandardMaterial({ color: 0x059669, roughness: 0.65 }));
    walkway.rotation.x = -Math.PI / 2;
    walkway.position.set(-4.5, 0.045, 6.0);
    cs.add(walkway);

    // Sistem Rak Gudang Baja Bertiang Tinggi (Heavy-Duty Pallet Racking)
    const rackBlueMat = new THREE.MeshStandardMaterial({ color: 0x1E40AF, metalness: 0.6, roughness: 0.4 });
    const rackOrangeMat = new THREE.MeshStandardMaterial({ color: 0xEA580C, metalness: 0.5, roughness: 0.4 });
    const wireMeshMat = new THREE.MeshStandardMaterial({ color: 0x94A3B8, metalness: 0.7, roughness: 0.3, wireframe: true });

    function createPalletRack(rx, rz, bayCount, bayWidth, bayDepth, height, rotY = 0) {
        const rack = new THREE.Group();
        rack.position.set(rx, 0, rz);
        rack.rotation.y = rotY;

        const totalLength = bayCount * bayWidth;
        const postGeo = new THREE.BoxGeometry(0.1, height, 0.1);
        const beamGeo = new THREE.BoxGeometry(totalLength, 0.12, 0.08);

        for (let i = 0; i <= bayCount; i++) {
            const px = -totalLength / 2 + i * bayWidth;
            [-bayDepth / 2, bayDepth / 2].forEach(pz => {
                const post = new THREE.Mesh(postGeo, rackBlueMat);
                post.position.set(px, height / 2, pz);
                rack.add(post);
            });
        }

        [0.25, 1.6, 2.95].forEach((by, lvlIdx) => {
            [-bayDepth / 2, bayDepth / 2].forEach(bz => {
                const beam = new THREE.Mesh(beamGeo, rackOrangeMat);
                beam.position.set(0, by, bz);
                rack.add(beam);
            });

            for (let b = 0; b < bayCount; b++) {
                const bx = -totalLength / 2 + (b + 0.5) * bayWidth;
                const pal = new THREE.Mesh(new THREE.BoxGeometry(1.4, 0.14, 1.2), MAT.palletWood);
                pal.position.set(bx, by + 0.14, 0);
                rack.add(pal);

                const cType = (b + lvlIdx) % 3;
                if (cType === 0) {
                    [-0.35, 0.35].forEach(dx => {
                        const drum = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.28, 0.85, 12), MAT.truckCabBlue);
                        drum.position.set(bx + dx, by + 0.65, 0);
                        rack.add(drum);
                    });
                } else if (cType === 1) {
                    const wrapBox = new THREE.Mesh(new THREE.BoxGeometry(1.25, 0.9, 1.05), new THREE.MeshStandardMaterial({ color: 0xF1F5F9, roughness: 0.35 }));
                    wrapBox.position.set(bx, by + 0.66, 0);
                    rack.add(wrapBox);
                } else {
                    [-0.3, 0.3].forEach(cx => {
                        [-0.25, 0.25].forEach(cz => {
                            const cBox = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.55, 0.45), MAT.cardboardBox);
                            cBox.position.set(bx + cx, by + 0.48, cz);
                            rack.add(cBox);
                        });
                    });
                }
            }
        });
        return rack;
    }

    // Rak Barat Utama & Rak Paralel Lorong Forklift
    cs.add(createPalletRack(-13.0, 1.3, 4, 2.4, 1.4, 4.2, Math.PI / 2));
    cs.add(createPalletRack(-8.2, 0.1, 3, 2.4, 1.4, 4.2, Math.PI / 2));
    cs.add(createPalletRack(10.0, -3.2, 3, 2.5, 1.4, 4.2, 0));
    cs.add(createPalletRack(10.0, 3.2, 3, 2.5, 1.4, 4.2, 0));

    // Sistem Rak Dinding Barat (High-Bay Perimeter Racking di X = -43.0)
    cs.add(createPalletRack(-43.0, -18.0, 5, 2.4, 1.4, 5.0, Math.PI / 2));
    cs.add(createPalletRack(-43.0, 2.0, 5, 2.4, 1.4, 5.0, Math.PI / 2));

    // Sistem Rak Dinding Selatan & Timur (High-Bay South & East Racking)
    cs.add(createPalletRack(-8.0, 26.0, 4, 2.5, 1.4, 4.2, 0));
    cs.add(createPalletRack(28.0, -18.0, 3, 2.4, 1.4, 4.2, Math.PI / 2));
    cs.add(createPalletRack(28.0, 20.0, 3, 2.4, 1.4, 4.2, Math.PI / 2));

    // Forklift Elektrik Reach Truck di Lorong Gudang
    cs.add(createDetailedForklift(-8.2, 8.5, -Math.PI / 2, true));

    // Staging Palet di Lantai Bay
    function addFloorPallet(px, pz, count = 2) {
        const grp = new THREE.Group();
        grp.position.set(px, 0, pz);
        const p = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.16, 1.4), MAT.palletWood);
        p.position.y = 0.08;
        grp.add(p);
        for (let i = 0; i < count; i++) {
            const b = new THREE.Mesh(new THREE.BoxGeometry(1.4, 0.7, 1.2), MAT.cardboardBox);
            b.position.y = 0.16 + 0.35 + i * 0.72;
            grp.add(b);
        }
        return grp;
    }
    cs.add(addFloorPallet(-11.5, 10.0, 2));
    cs.add(addFloorPallet(-9.5, 14.5, 3));
    cs.add(addFloorPallet(8.5, 2.0, 2));

    // Meja Putar Caster Bed Pusat Perakitan ULD (di X = 0, Z = 8.0)
    const bed = new THREE.Mesh(new THREE.BoxGeometry(4.4, 0.32, 4.4), MAT.conveyorRoller);
    bed.position.set(0, 0.16, 8.0);
    const casterPlate = new THREE.Mesh(new THREE.BoxGeometry(4.2, 0.04, 4.2), MAT.metalSilver);
    casterPlate.position.set(0, 0.34, 8.0);
    cs.add(bed, casterPlate);

    // Grid Caster Balls (Bola-bola transfer baja omnidirectional)
    const ballGeo = new THREE.SphereGeometry(0.06, 8, 8);
    for (let bx = -1.8; bx <= 1.8; bx += 0.6) {
        for (let bz = -1.8; bz <= 1.8; bz += 0.6) {
            const ball = new THREE.Mesh(ballGeo, MAT.metalSilver);
            ball.position.set(bx, 0.38, 8.0 + bz);
            cs.add(ball);
        }
    }

    // Lis Pengaman Pembatas Kuning (Yellow Safety Rim)
    // Memberikan bukaan presisi di sisi barat (X = -2.2) dan timur (X = 2.2) pada sumbu Z = 8.0 agar rel konveyor masuk & keluar mulus
    const rimWestTop = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.25, 0.9), MAT.yellowSafety);
    rimWestTop.position.set(-2.2, 0.44, 6.25);
    const rimWestBottom = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.25, 0.9), MAT.yellowSafety);
    rimWestBottom.position.set(-2.2, 0.44, 9.75);
    const rimSouth = new THREE.Mesh(new THREE.BoxGeometry(4.4, 0.25, 0.15), MAT.yellowSafety);
    rimSouth.position.set(0, 0.44, 10.2);
    const rimNorthLeft = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.25, 0.15), MAT.yellowSafety);
    rimNorthLeft.position.set(-1.75, 0.44, 5.8);
    const rimNorthRight = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.25, 0.15), MAT.yellowSafety);
    rimNorthRight.position.set(1.75, 0.44, 5.8);
    const rimEastTop = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.25, 0.9), MAT.yellowSafety);
    rimEastTop.position.set(2.2, 0.44, 6.25);
    const rimEastBottom = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.25, 0.9), MAT.yellowSafety);
    rimEastBottom.position.set(2.2, 0.44, 9.75);
    cs.add(rimWestTop, rimWestBottom, rimSouth, rimNorthLeft, rimNorthRight, rimEastTop, rimEastBottom);

    // Tiang Sudut Pelindung Bumper Kuning
    [[-2.2, -2.2], [-2.2, 2.2], [2.2, 2.2]].forEach(([cx, cz]) => {
        const post = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.1, 1.2, 12), MAT.yellowSafety);
        post.position.set(cx, 0.6, 8.0 + cz);
        cs.add(post);
    });

    // Rangka Hoist Derek Pengangkat Kontainer ULD (Overhead Assembly Jib Crane)
    const craneMast = new THREE.Mesh(new THREE.BoxGeometry(0.3, 4.8, 0.3), MAT.metalDark);
    craneMast.position.set(-2.0, 2.4, 10.0);
    const craneBoom = new THREE.Mesh(new THREE.BoxGeometry(4.0, 0.3, 0.3), MAT.metalDark);
    craneBoom.position.set(0, 4.7, 10.0);
    const hoist = new THREE.Mesh(new THREE.BoxGeometry(0.6, 0.4, 0.4), new THREE.MeshStandardMaterial({ color: 0xF59E0B }));
    hoist.position.set(0, 4.4, 10.0);
    cs.add(craneMast, craneBoom, hoist);

    // 3D Hanging Signs & Floor Stencils for Storage Zones
    const hangA = makeHangingAisleSign("ZONE A", "PALLET RACKS", 4.0, 1.0, "#3B82F6");
    hangA.position.set(-10.5, 7.2, 1.3);
    const hangB = makeHangingAisleSign("ZONE B", "BUFFER STAGING", 4.0, 1.0, "#F59E0B");
    hangB.position.set(10.0, 7.2, -3.2);
    const hangC = makeHangingAisleSign("ZONE C", "ULD STAGING", 4.0, 1.0, "#10B981");
    hangC.position.set(10.0, 7.2, 13.5);
    signsGroup.add(hangA, hangB, hangC);

    const bayADecal = makeFloorDecal("ZONE A : PALLET RACKS", "BULK STORAGE AISLE", 7.0, 3.2, "#3B82F6");
    bayADecal.position.set(-10.5, 0.045, 1.3);
    const bayBDecal = makeFloorDecal("ZONE B : BUFFER STAGING", "EXPORT READY PALLETS", 6.2, 3.0, "#F59E0B");
    bayBDecal.position.set(10.0, 0.045, -3.2);
    const bayCDecal = makeFloorDecal("ZONE C : ULD ASSEMBLY", "AKE-GA WORKSTATION", 6.2, 3.0, "#10B981");
    bayCDecal.position.set(10.0, 0.045, 13.5);
    signsGroup.add(bayADecal, bayBDecal, bayCDecal);

    return cs;
}
storageGroup.add(createDetailedStorage());

// ─────────────────────────────────────────────────────────────────────────────
// ARMADA FORKLIFT RAK TINGGI & PERALATAN GUDANG PUSAT (CENTRAL WAREHOUSE FLEET)
// ─────────────────────────────────────────────────────────────────────────────
// 1. High-Reach Truck di Rak Tinggi Barat (Terangkat Tinggi Level 3 dengan Palet Boks)
storageGroup.add(createReachTruck(-39.0, -16.0, Math.PI / 2, 3.2, 'boxes'));

// 2. High-Reach Truck di Rak Barat Tengah (Terangkat Level 2 dengan Palet Drum Farmasi)
storageGroup.add(createReachTruck(-39.0, 4.0, -Math.PI / 2, 1.8, 'drums'));

// 3. High-Reach Truck di Rak Timur (Zona Export Buffer)
storageGroup.add(createReachTruck(26.0, 20.0, Math.PI / 2, 2.4, 'boxes'));

// 4. Kereta Tarik Kargo (Industrial Tow Tractor Train dengan Driver & 2 Gerobak Gandeng)
storageGroup.add(createWarehouseTowTractor(-20.0, 4.0, 0));

// 5. Forklift Elektrik Oranye di Lorong Tengah (Dekat Zone B - Export Ready Pallets)
storageGroup.add(createDetailedForklift(18.0, 2.0, Math.PI / 2, true, 0xEA580C, 'boxes'));

// 6. Petugas Supervisor di Dekat Caster Bed ULD (Mengawasi Perakitan Kontainer Pesawat)
storageGroup.add(createWarehouseWorker(-3.2, 7.5, Math.PI / 4, 'supervisor'));

// 7. Petugas Menarik Hand Pallet Jack Berisi Paket Ekspor
storageGroup.add(createWarehouseWorker(4.5, 12.0, -Math.PI / 3, 'checker'));
storageGroup.add(createHandPalletJack(5.2, 12.4, -Math.PI / 3));

// 8. Stasiun Penampung Tumpahan Bahan Berbahaya (Spill Containment & DG)
storageGroup.add(createSpillContainmentStation(-22.0, -11.0));
storageGroup.add(createWarehouseWorker(-19.5, -11.0, -Math.PI / 2, 'clipboard'));

// 9. Bengkel & Meja Perawatan Alat di Bawah Mezzanine TOC
storageGroup.add(createMaintenanceWorkbench(-36.0, 26.0));
storageGroup.add(createEmergencyEyeWashStation(-44.0, 26.0));
storageGroup.add(createTrashRecyclingStation(-30.0, 26.0));

// 10. Kaca Cembung Keselamatan Persimpangan Lorong pada Kolom Struktural
storageGroup.add(createConvexSafetyMirror(-38.0, 0.0, Math.PI / 4));
storageGroup.add(createConvexSafetyMirror(-20.0, 0.0, -Math.PI / 4));

// ─────────────────────────────────────────────────────────────────────────────
// 10. SMART FLOOR SCALE 15 TON & DIGITAL DISPLAY (X = 35.0, Z = 8.0)
// ─────────────────────────────────────────────────────────────────────────────
function createDetailedSmartScale() {
    const sc = new THREE.Group();
    sc.position.set(35.0, 0, 8.0);

    const pitBase = new THREE.Mesh(new THREE.BoxGeometry(5.2, 0.08, 4.8), new THREE.MeshStandardMaterial({ color: 0x09090B }));
    pitBase.position.y = 0.04;
    sc.add(pitBase);

    // 4 Unit Sensor Load Cell
    [[-2.2, -1.9], [2.2, -1.9], [-2.2, 1.9], [2.2, 1.9]].forEach(([cx, cz]) => {
        const cell = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.12, 0.18, 16), MAT.metalDark);
        cell.position.set(cx, 0.12, cz);
        sc.add(cell);
    });

    const deck = new THREE.Mesh(new THREE.BoxGeometry(5.0, 0.24, 4.4), new THREE.MeshStandardMaterial({ color: 0x111827, metalness: 0.5, roughness: 0.4 }));
    deck.position.y = 0.19;

    const topPlate = new THREE.Mesh(new THREE.BoxGeometry(4.9, 0.02, 4.3), new THREE.MeshStandardMaterial({ color: 0x0A0F1D }));
    topPlate.position.y = 0.315;

    // Rol Baja Karbon Hitam di Meja Timbang
    for (let rx = -2.0; rx <= 2.0; rx += 0.4) {
        const rol = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.05, 4.1, 16), MAT.metalDark);
        rol.rotateX(Math.PI / 2);
        rol.position.set(rx, 0.33, 0);
        sc.add(rol);
    }

    // Pagar Pengaman Samping Kuning
    [-2.2, 2.2].forEach(rz => {
        const curb = new THREE.Mesh(new THREE.BoxGeometry(5.0, 0.22, 0.14), MAT.yellowSafety);
        curb.position.set(0, 0.40, rz);
        sc.add(curb);
    });

    // Marka Crosshair CoG
    const crossX = new THREE.Mesh(new THREE.PlaneGeometry(3.6, 0.08), new THREE.MeshBasicMaterial({ color: 0xE2E8F0 }));
    crossX.rotation.x = -Math.PI / 2;
    crossX.position.set(0, 0.335, 0);
    const crossZ = new THREE.Mesh(new THREE.PlaneGeometry(0.08, 3.2), new THREE.MeshBasicMaterial({ color: 0xE2E8F0 }));
    crossZ.rotation.x = -Math.PI / 2;
    crossZ.position.set(0, 0.335, 0);
    sc.add(deck, topPlate, crossX, crossZ);

    // Menara Display Digital LED Timbangan
    const tower = new THREE.Mesh(new THREE.BoxGeometry(0.35, 3.2, 0.35), MAT.metalDark);
    tower.position.set(0, 1.6, -2.8);
    const screenBox = new THREE.Mesh(new THREE.BoxGeometry(2.4, 1.35, 0.22), MAT.metalDark);
    screenBox.position.set(0, 3.3, -2.7);
    screenBox.rotation.x = 0.12;

    const scaleCanvas = document.createElement('canvas');
    scaleCanvas.width = 512;
    scaleCanvas.height = 256;
    const scaleCtx = scaleCanvas.getContext('2d');
    const scaleTex = new THREE.CanvasTexture(scaleCanvas);
    const scaleDisplayMesh = new THREE.Mesh(new THREE.PlaneGeometry(2.2, 1.2), new THREE.MeshBasicMaterial({ map: scaleTex }));
    scaleDisplayMesh.position.set(0, 3.3, -2.58);
    scaleDisplayMesh.rotation.x = 0.12;

    window._updateScaleTex = function(weight, maxWeight, status = 'IDLE') {
        scaleCtx.fillStyle = '#060D1A';
        scaleCtx.fillRect(0, 0, 512, 256);
        scaleCtx.strokeStyle = (status === 'OVERWEIGHT') ? '#EF4444' : ((status === 'PASS') ? '#10B981' : '#0CA1AF');
        scaleCtx.lineWidth = 6;
        scaleCtx.strokeRect(6, 6, 500, 244);

        scaleCtx.fillStyle = '#0CA1AF';
        scaleCtx.font = 'bold 22px monospace';
        scaleCtx.fillText('SMART FLOOR SCALE 15T', 24, 42);

        scaleCtx.fillStyle = (status === 'OVERWEIGHT') ? '#EF4444' : (status === 'PASS' ? '#10B981' : '#FDE047');
        scaleCtx.font = '900 52px monospace';
        scaleCtx.fillText(`${weight.toFixed(1)} KG`, 24, 115);

        scaleCtx.fillStyle = '#94A3B8';
        scaleCtx.font = 'bold 20px monospace';
        scaleCtx.fillText(`MAX RATING : ${maxWeight.toFixed(0)} KG`, 24, 165);

        scaleCtx.fillStyle = (status === 'OVERWEIGHT') ? '#EF4444' : (status === 'PASS' ? '#10B981' : '#38BDF8');
        scaleCtx.font = 'bold 26px monospace';
        scaleCtx.fillText(`STATUS : ${status}`, 24, 215);

        scaleTex.needsUpdate = true;
    };
    window._updateScaleTex(0, 1588, 'IDLE');

    const scaleFloorDecal = makeFloorDecal("15,000 KG ULD SCALE", "CENTRAL WEIGHING & BALANCE", 6.2, 4.0, "#A855F7");
    scaleFloorDecal.position.set(35.0, 0.035, 8.0);
    signsGroup.add(scaleFloorDecal);

    sc.add(tower, screenBox, scaleDisplayMesh);
    return sc;
}

function updateScaleDisplayTexture(weight, maxWeight, status = 'IDLE') {
    if (window._updateScaleTex) {
        window._updateScaleTex(weight, maxWeight, status);
    }
}

scaleGroup.add(createDetailedSmartScale());

// ─────────────────────────────────────────────────────────────────────────────
// 11. AIRSIDE GATE, SHUTTER & CARGO HIGH-LOADER GSE
// ─────────────────────────────────────────────────────────────────────────────
// Portal Gerbang Airside Pemisah Terminal & Apron (Dinding Solid Pemotong Rel Dihilangkan Total)
// Jalur konveyor dari timbangan 15T menuju High-Loader & palka Boeing 777F kini 100% terbuka bersih tanpa hambatan fisik
const airsideGateFrame = new THREE.Group();
const airsideShutter = new THREE.Group();
airsideShutter.position.set(45.0, 2.1, 8.0);
airsideGroup.add(airsideGateFrame, airsideShutter);

let hlElevatorGroup = null;
let updateHighLoaderScissors = null;
let hlStrobeMeshes = [];

function createRealisticHighLoader() {
    const hl = new THREE.Group();
    hl.position.set(63, 0, 8);

    const chas = new THREE.Mesh(new THREE.BoxGeometry(9.4, 0.45, 3.6), new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.6, metalness: 0.4 }));
    chas.position.set(1.4, 0.45, 0);
    hl.add(chas);

    const skirtL = new THREE.Mesh(new THREE.BoxGeometry(9.4, 0.22, 0.08), MAT.yellowSafety);
    skirtL.position.set(1.4, 0.22, -1.82);
    const skirtR = new THREE.Mesh(new THREE.BoxGeometry(9.4, 0.22, 0.08), MAT.yellowSafety);
    skirtR.position.set(1.4, 0.22, 1.82);
    hl.add(skirtL, skirtR);

    // 4 Roda Karet GSE
    const wGeo = new THREE.CylinderGeometry(0.55, 0.55, 0.42, 24);
    wGeo.rotateZ(Math.PI / 2);
    [[-2.2, -1.88], [4.4, -1.88], [-2.2, 1.88], [4.4, 1.88]].forEach(([wx, wz]) => {
        const tire = new THREE.Mesh(wGeo, MAT.truckTire);
        tire.position.set(wx, 0.55, wz);
        hl.add(tire);
    });

    // Jembatan Depan Docking ke Boeing 777F (Ketinggian 3.85m)
    const bridgeGroup = new THREE.Group();
    bridgeGroup.position.set(5.44, 3.85, 0);
    const bridgeDeck = new THREE.Mesh(new THREE.BoxGeometry(4.88, 0.28, 3.4), MAT.conveyorRoller);
    bridgeDeck.position.set(0, -0.14, 0);
    bridgeGroup.add(bridgeDeck);

    for (let rx = -2.2; rx <= 2.2; rx += 0.28) {
        const roller = new THREE.Mesh(new THREE.CylinderGeometry(0.038, 0.038, 3.2, 12), MAT.metalSilver);
        roller.rotateX(Math.PI / 2);
        roller.position.set(rx, 0.01, 0);
        bridgeGroup.add(roller);
    }

    const dockBumper = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.32, 3.42), new THREE.MeshStandardMaterial({ color: 0x0F172A, roughness: 0.9 }));
    dockBumper.position.set(2.44, -0.12, 0);
    bridgeGroup.add(dockBumper);

    [-1.72, 1.72].forEach(bz => {
        const bRailTop = new THREE.Mesh(new THREE.BoxGeometry(4.85, 0.05, 0.05), MAT.yellowSafety);
        bRailTop.position.set(0, 0.85, bz);
        bridgeGroup.add(bRailTop);
    });

    [-1.55, 1.55].forEach(cz => {
        const col1 = new THREE.Mesh(new THREE.BoxGeometry(0.32, 3.1, 0.32), MAT.metalDark);
        col1.position.set(3.6, 2.05, cz);
        const col2 = new THREE.Mesh(new THREE.BoxGeometry(0.32, 3.1, 0.32), MAT.metalDark);
        col2.position.set(7.0, 2.05, cz);
        hl.add(col1, col2);
    });
    hl.add(bridgeGroup);

    // Platform Elevator Pengangkut Dinamis
    hlElevatorGroup = new THREE.Group();
    hlElevatorGroup.position.set(0, 0.55, 0);

    const elevBed = new THREE.Mesh(new THREE.BoxGeometry(6.0, 0.32, 3.4), MAT.conveyorRoller);
    elevBed.position.set(0, 0, 0);
    hlElevatorGroup.add(elevBed);

    for (let ex = -2.8; ex <= 2.8; ex += 0.28) {
        const eRoll = new THREE.Mesh(new THREE.CylinderGeometry(0.038, 0.038, 3.2, 12), MAT.metalSilver);
        eRoll.rotateX(Math.PI / 2);
        eRoll.position.set(ex, 0.165, 0);
        hlElevatorGroup.add(eRoll);
    }

    [-1.72, 1.72].forEach(ez => {
        const eRailTop = new THREE.Mesh(new THREE.BoxGeometry(5.95, 0.05, 0.05), MAT.yellowSafety);
        eRailTop.position.set(0, 0.90, ez);
        hlElevatorGroup.add(eRailTop);
    });

    hlStrobeMeshes = [];
    [[-2.95, -1.72], [2.95, -1.72], [-2.95, 1.72], [2.95, 1.72]].forEach(([sx, sz]) => {
        const strobe = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 0.1, 8), new THREE.MeshBasicMaterial({ color: 0xF59E0B }));
        strobe.position.set(sx, 0.22, sz);
        hlElevatorGroup.add(strobe);
        hlStrobeMeshes.push(strobe);
    });

    hl.add(hlElevatorGroup);

    // Mekanisme Pantograf Gunting Dinamis (Kinematic Scissor Arms)
    const scissorGroup = new THREE.Group();
    hl.add(scissorGroup);

    const SCISSOR_L = 4.8;
    const scissorPairs = [];

    [-1.45, 1.45].forEach(sz => {
        const armGeo = new THREE.BoxGeometry(SCISSOR_L, 0.20, 0.09);
        const armA = new THREE.Mesh(armGeo, MAT.metalSilver);
        const armB = new THREE.Mesh(armGeo, MAT.metalSilver);
        const pin = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.12, 0.22, 16), MAT.metalDark);
        pin.rotateX(Math.PI / 2);
        scissorGroup.add(armA, armB, pin);
        scissorPairs.push({ armA, armB, pin, sz });
    });

    updateHighLoaderScissors = function(elevY) {
        const yBase = 0.55;
        const yPlat = Math.max(yBase + 0.08, elevY);
        const H = yPlat - yBase;
        const yMid = yBase + H * 0.5;
        const sinTheta = THREE.MathUtils.clamp(H / SCISSOR_L, 0.02, 0.75);
        const theta = Math.asin(sinTheta);

        scissorPairs.forEach(({ armA, armB, pin, sz }) => {
            armA.position.set(0, yMid, sz);
            armA.rotation.z = theta;
            armB.position.set(0, yMid, sz);
            armB.rotation.z = -theta;
            pin.position.set(0, yMid, sz);
        });
    };
    updateHighLoaderScissors(0.55);

    return hl;
}
airsideGroup.add(createRealisticHighLoader());

// ─────────────────────────────────────────────────────────────────────────────
// 12. BOEING 777F FREIGHTER, GPU 400Hz & APRON MARKINGS
// ─────────────────────────────────────────────────────────────────────────────
// 12. BOEING 777F FREIGHTER, GPU 400Hz & APRON MARKINGS
// ─────────────────────────────────────────────────────────────────────────────
let planeCargoDoorHatch = null;
let planeFanBlades = [];
let planeAviationLights = null;

// Helper: Model Kontainer Pesawat IATA AKE (LD3) Asli untuk Interior Palka & Airside
function createAuthenticAkeUld(cfg = {}) {
    const {
        code = 'AKE 14922 GA',
        flight = 'GA-882',
        dest = 'AMS',
        tare = '78 KG',
        maxGross = '1588 KG'
    } = cfg;
    const uld = new THREE.Group();

    // 1. Kontur Khas ULD AKE / LD3 (Chamfer 45 Derajat di Sisi Bawah Kanan)
    const shape = new THREE.Shape();
    shape.moveTo(-0.95, 0.0);
    shape.lineTo(0.55, 0.0);
    shape.lineTo(0.95, 0.40);
    shape.lineTo(0.95, 1.60);
    shape.lineTo(-0.95, 1.60);
    shape.closePath();

    const extrudeSettings = {
        steps: 1,
        depth: 1.50,
        bevelEnabled: true,
        bevelThickness: 0.02,
        bevelSize: 0.02,
        bevelSegments: 2
    };
    const bodyGeo = new THREE.ExtrudeGeometry(shape, extrudeSettings);
    bodyGeo.translate(0, 0, -0.75);
    bodyGeo.computeVertexNormals();

    const body = new THREE.Mesh(bodyGeo, MAT.uldAluminum);
    body.castShadow = true;
    body.receiveShadow = true;
    uld.add(body);

    // 2. Pallet Base Tray & Corner Castings (Aluminium Base dengan Kunci Sudut IATA)
    const baseTray = new THREE.Mesh(new THREE.BoxGeometry(1.58, 0.08, 1.54), MAT.metalSilver);
    baseTray.position.set(-0.20, 0.04, 0);
    uld.add(baseTray);

    // 4 Corner Castings (Kunci Restraint Kontainer di Palka Pesawat)
    [[-0.95, -0.74], [0.55, -0.74], [-0.95, 0.74], [0.55, 0.74]].forEach(([cx, cz]) => {
        const cCast = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.12, 0.12), MAT.metalDark);
        cCast.position.set(cx, 0.06, cz);
        uld.add(cCast);
    });

    // 3. Tirai Kanvas Biru Tahan Cuaca dengan Tension Straps & Buckles
    const curtainShape = new THREE.Shape();
    curtainShape.moveTo(-0.92, 0.08);
    curtainShape.lineTo(0.52, 0.08);
    curtainShape.lineTo(0.92, 0.45);
    curtainShape.lineTo(0.92, 1.55);
    curtainShape.lineTo(-0.92, 1.55);
    curtainShape.closePath();

    const curtainGeo = new THREE.ShapeGeometry(curtainShape);
    const curtain = new THREE.Mesh(curtainGeo, MAT.uldCanvasBlue);
    curtain.position.z = 0.765;
    uld.add(curtain);

    // 3 Lashing Webbing Straps Horizontal di Tirai
    [0.45, 0.90, 1.30].forEach(sy => {
        const strap = new THREE.Mesh(new THREE.BoxGeometry(1.78, 0.04, 0.01), MAT.metalDark);
        strap.position.set(0, sy, 0.772);
        const buckle = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.06, 0.02), MAT.metalSilver);
        buckle.position.set(0.15, sy, 0.778);
        uld.add(strap, buckle);
    });

    // Pouch Dokumen Transparan dengan Manifest GA-882
    const pouch = new THREE.Mesh(new THREE.PlaneGeometry(0.40, 0.30), MAT.wallGlass);
    pouch.position.set(-0.45, 1.05, 0.775);
    const manPaper = new THREE.Mesh(new THREE.PlaneGeometry(0.35, 0.25), new THREE.MeshStandardMaterial({ color: 0xF8FAFC, roughness: 0.9 }));
    manPaper.position.set(-0.45, 1.05, 0.772);
    uld.add(pouch, manPaper);

    // 4. Dekal Stensil Resmi IATA di Dinding Samping Aluminium
    const sCanvas = document.createElement('canvas');
    sCanvas.width = 512;
    sCanvas.height = 256;
    const sctx = sCanvas.getContext('2d');
    sctx.fillStyle = '#E2E8F0';
    sctx.fillRect(0, 0, 512, 256);

    // Header Band Biru InJourney
    sctx.fillStyle = '#0A2540';
    sctx.fillRect(0, 0, 512, 44);
    sctx.fillStyle = '#0CA1AF';
    sctx.fillRect(0, 44, 512, 8);

    sctx.fillStyle = '#FFFFFF';
    sctx.font = 'bold 24px Barlow, sans-serif';
    sctx.fillText('INJOURNEY AIR CARGO', 20, 32);

    sctx.fillStyle = '#0A2540';
    sctx.font = '900 38px monospace';
    sctx.fillText(code, 20, 105);

    sctx.fillStyle = '#334155';
    sctx.font = 'bold 20px monospace';
    sctx.fillText(`FLIGHT: ${flight} | DEST: ${dest}`, 20, 142);
    sctx.fillText(`TARE: ${tare} | MAX: ${maxGross}`, 20, 175);
    sctx.font = '16px sans-serif';
    sctx.fillText('IATA CERTIFIED CLASS 2 CONTAINER', 20, 205);

    // Barcode 128
    sctx.fillStyle = '#0F172A';
    for (let bx = 340; bx < 480; bx += 4) {
        if ((bx * 7) % 3 !== 0) sctx.fillRect(bx, 120, 2.5, 60);
    }
    sctx.font = '12px monospace';
    sctx.fillText('SSCC-1890214', 355, 195);

    // Hazard Warning Symbol
    sctx.fillStyle = '#DC2626';
    sctx.fillRect(450, 60, 42, 42);
    sctx.fillStyle = '#FFFFFF';
    sctx.font = 'bold 24px sans-serif';
    sctx.textAlign = 'center';
    sctx.fillText('!', 471, 90);
    sctx.textAlign = 'left';

    const sTex = new THREE.CanvasTexture(sCanvas);
    const sMesh = new THREE.Mesh(new THREE.PlaneGeometry(1.60, 0.80), new THREE.MeshBasicMaterial({ map: sTex }));
    sMesh.position.set(-0.975, 0.95, 0);
    sMesh.rotation.y = -Math.PI / 2;
    uld.add(sMesh);

    return uld;
}

function createDetailedCargoPlane() {
    const plane = new THREE.Group();
    // Posisi pesawat di apron Stand 12R (pusat fuselage di X = 74.0, sumbu Z membujur)
    plane.position.set(74, 0, 0);

    planeFanBlades = [];

    // ─────────────────────────────────────────────────────────────────────────
    // 1. FUSELAGE / BADAN PESAWAT BOEING 777F FREIGHTER
    // ─────────────────────────────────────────────────────────────────────────
    const fuseGroup = new THREE.Group();
    plane.add(fuseGroup);

    // Tabung Utama Fuselage dengan Cut-out Pintu Kargo Port Terbuka Nyata (Open Cargo Doorway Z = 5.8 to 10.2)
    // 1. Bagian Depan (Forward Main Fuselage: Z = -2.0 sampai Z = 5.8, panjang 7.8m)
    const fwdMainFuseGeo = new THREE.CylinderGeometry(3.1, 3.1, 7.8, 48);
    fwdMainFuseGeo.rotateX(Math.PI / 2);
    const fwdMainFuselage = new THREE.Mesh(fwdMainFuseGeo, MAT.planeWhite);
    fwdMainFuselage.position.set(0, 5.2, 1.9);
    fwdMainFuselage.castShadow = true;
    fwdMainFuselage.receiveShadow = true;
    fuseGroup.add(fwdMainFuselage);

    // 2. Bagian Belakang (Aft Main Fuselage: Z = 10.2 sampai Z = 32.0, panjang 21.8m)
    const aftMainFuseGeo = new THREE.CylinderGeometry(3.1, 3.1, 21.8, 48);
    aftMainFuseGeo.rotateX(Math.PI / 2);
    const aftMainFuselage = new THREE.Mesh(aftMainFuseGeo, MAT.planeWhite);
    aftMainFuselage.position.set(0, 5.2, 21.1);
    aftMainFuselage.castShadow = true;
    aftMainFuselage.receiveShadow = true;
    fuseGroup.add(aftMainFuselage);

    // 3. Selubung Sisi Kanan / Starboard di Area Pintu Kargo (Z = 5.8 sampai Z = 10.2, panjang 4.4m, X >= 0 Tertutup Mulus)
    const stbdGeo = new THREE.CylinderGeometry(3.1, 3.1, 4.4, 32, 1, true, 0, Math.PI);
    stbdGeo.rotateX(Math.PI / 2);
    const stbdShell = new THREE.Mesh(stbdGeo, new THREE.MeshStandardMaterial({ color: 0xF8FAFC, roughness: 0.35, metalness: 0.15, side: THREE.DoubleSide }));
    stbdShell.position.set(0, 5.2, 8.0);
    stbdShell.castShadow = true;
    stbdShell.receiveShadow = true;
    fuseGroup.add(stbdShell);

    // 4. Mahkota Atap di Atas Pintu Kargo Port (Crown Roof Shell di Atas Y = 6.75)
    const crownGeo = new THREE.CylinderGeometry(3.1, 3.1, 4.4, 16, 1, true, Math.PI, Math.PI * 0.33);
    crownGeo.rotateX(Math.PI / 2);
    const crownShell = new THREE.Mesh(crownGeo, new THREE.MeshStandardMaterial({ color: 0xF8FAFC, roughness: 0.35, metalness: 0.15, side: THREE.DoubleSide }));
    crownShell.position.set(0, 5.2, 8.0);
    crownShell.castShadow = true;
    crownShell.receiveShadow = true;
    fuseGroup.add(crownShell);

    // 5. Lengkungan Bawah Belly di Bawah Lantai Kargo Port (Belly Keel Shell di Bawah Y = 3.85)
    const bellyGeo = new THREE.CylinderGeometry(3.1, 3.1, 4.4, 16, 1, true, Math.PI * 1.67, Math.PI * 0.33);
    bellyGeo.rotateX(Math.PI / 2);
    const bellyShell = new THREE.Mesh(bellyGeo, new THREE.MeshStandardMaterial({ color: 0xF8FAFC, roughness: 0.35, metalness: 0.15, side: THREE.DoubleSide }));
    bellyShell.position.set(0, 5.2, 8.0);
    bellyShell.castShadow = true;
    bellyShell.receiveShadow = true;
    fuseGroup.add(bellyShell);
    // CATATAN: Sisi KIRI (Port side X < 0) antara Y = 3.85 dan Y = 6.75 dibiarkan TERBUKA LEBAR sebagai Pintu Kargo Masuk Nyata!

    // Transisi Hidung Forward Fuselage (Z = -2 sampai Z = -9)
    const fwdFuseGeo = new THREE.CylinderGeometry(3.1, 2.5, 7, 48);
    fwdFuseGeo.rotateX(Math.PI / 2);
    const fwdFuselage = new THREE.Mesh(fwdFuseGeo, MAT.planeWhite);
    fwdFuselage.position.set(0, 5.1, -5.5);
    fwdFuselage.castShadow = true;
    fuseGroup.add(fwdFuselage);

    // Kerucut Hidung Radome (Z = -9 sampai Z = -16, radius 2.5m ke 0.8m)
    const noseGeo = new THREE.CylinderGeometry(2.5, 0.8, 7, 48);
    noseGeo.rotateX(Math.PI / 2);
    const noseMesh = new THREE.Mesh(noseGeo, MAT.planeWhite);
    noseMesh.position.set(0, 4.85, -12.5);
    noseMesh.castShadow = true;
    fuseGroup.add(noseMesh);

    // Radome Nose Cap Bulat Aerodinamis (Ujung Depan Hidung Pesawat di Z = -16)
    const radomeCap = new THREE.Mesh(new THREE.SphereGeometry(0.82, 32, 24), MAT.metalSilver);
    radomeCap.scale.set(1.0, 0.95, 1.4);
    radomeCap.position.set(0, 4.65, -16.0);
    fuseGroup.add(radomeCap);

    // Aerodynamic Belly Fairing (Wing-to-Body Fairing: Z = 11.5 sampai Z = 24.5, bersih dari area pintu kargo Z = 8.0)
    const bellyFairing = new THREE.Mesh(new THREE.BoxGeometry(6.4, 1.25, 13.0), MAT.planeWhite);
    bellyFairing.position.set(0, 3.75, 18.0);
    bellyFairing.castShadow = true;
    fuseGroup.add(bellyFairing);

    // Aft Fuselage Upswept Tail (Z = 32 sampai Z = 47, radius 3.1m ke 1.1m, belly melengkung ke atas)
    const aftFuseGeo = new THREE.CylinderGeometry(1.1, 3.1, 15, 48);
    aftFuseGeo.rotateX(Math.PI / 2);
    const aftFuselage = new THREE.Mesh(aftFuseGeo, MAT.planeWhite);
    aftFuselage.position.set(0, 5.8, 39.5);
    aftFuselage.castShadow = true;
    fuseGroup.add(aftFuselage);

    // Kerucut Ekor APU Cone (Z = 47 sampai Z = 51.5)
    const apuConeGeo = new THREE.CylinderGeometry(0.4, 1.1, 4.5, 32);
    apuConeGeo.rotateX(Math.PI / 2);
    const apuCone = new THREE.Mesh(apuConeGeo, MAT.planeWhite);
    apuCone.position.set(0, 6.4, 49.2);
    apuCone.castShadow = true;
    fuseGroup.add(apuCone);

    // Saluran Pembuangan APU Exhaust Logam Tahan Panas
    const apuPipe = new THREE.Mesh(new THREE.CylinderGeometry(0.3, 0.38, 1.4, 24), MAT.engineCore);
    apuPipe.rotateX(Math.PI / 2);
    apuPipe.position.set(0, 6.45, 51.6);
    fuseGroup.add(apuPipe);

    // ─────────────────────────────────────────────────────────────────────────
    // 2. KOKPIT & KACA JENDELA AERODINAMIS DENGAN VISOR MASK
    // ─────────────────────────────────────────────────────────────────────────
    // Mask Hitam Anti-Glare di Sekeliling Kaca Kokpit
    const visorMask = new THREE.Mesh(new THREE.BoxGeometry(2.7, 1.1, 0.08), MAT.cockpitGlass);
    visorMask.position.set(0, 6.35, -11.9);
    visorMask.rotation.x = -Math.PI / 6.2;
    fuseGroup.add(visorMask);

    // 2 Kaca Depan Utama (Center Windshields - Angled)
    [-0.65, 0.65].forEach(cx => {
        const frontPane = new THREE.Mesh(new THREE.BoxGeometry(1.05, 0.72, 0.12), MAT.cockpitGlass);
        frontPane.position.set(cx, 6.35, -11.92);
        frontPane.rotation.x = -Math.PI / 6.2;
        frontPane.rotation.y = (cx > 0) ? -0.1 : 0.1;
        fuseGroup.add(frontPane);
    });

    // 2 Kaca Samping Geser (Side Windows)
    [-1.4, 1.4].forEach(sx => {
        const sidePane = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.62, 1.4), MAT.cockpitGlass);
        sidePane.position.set(sx, 6.3, -11.2);
        sidePane.rotation.y = (sx > 0) ? -Math.PI / 5.5 : Math.PI / 5.5;
        fuseGroup.add(sidePane);
    });

    // Wiper Kokpit
    [-0.65, 0.65].forEach(wx => {
        const wiper = new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.65, 0.04), MAT.metalDark);
        wiper.position.set(wx, 6.3, -11.98);
        wiper.rotation.z = (wx > 0) ? 0.35 : -0.35;
        fuseGroup.add(wiper);
    });

    // Probe Sensor Pitot di Hidung Pesawat
    [-1.8, 1.8].forEach(px => {
        const pitot = new THREE.Mesh(new THREE.CylinderGeometry(0.02, 0.02, 0.5), MAT.metalSilver);
        pitot.rotateX(Math.PI / 2);
        pitot.position.set(px, 4.8, -13.5);
        fuseGroup.add(pitot);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // 3. LIVERY & DEKORASI INJOURNEY CARGO (STRIPES & TYPOGRAPHY)
    // ─────────────────────────────────────────────────────────────────────────
    // Garis Cheatline InJourney Teal & Navy di Kedua Sisi Fuselage
    // Sisi Kanan / Starboard: Garis memanjang utuh dari hidung ke ekor
    const tealBandStbd = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.55, 38), MAT.injourneyTeal);
    tealBandStbd.position.set(3.12, 4.6, 15);
    const navyBandStbd = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.22, 36), MAT.planeNavy);
    navyBandStbd.position.set(3.12, 4.18, 15);
    const tailStripeStbd = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.45, 12), MAT.injourneyTeal);
    tailStripeStbd.position.set(3.12 * 0.75, 5.7, 38);
    tailStripeStbd.rotation.x = 0.08;
    fuseGroup.add(tealBandStbd, navyBandStbd, tailStripeStbd);

    // Sisi Kiri / Port: Garis terputus di area pintu kargo (Z = 5.8 sampai 10.2) agar pintu kargo terbuka bersih
    // Garis Depan (Forward Cheatline: Z = -2 sampai Z = 5.8)
    const tealBandPortFwd = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.55, 7.8), MAT.injourneyTeal);
    tealBandPortFwd.position.set(-3.12, 4.6, 1.9);
    const navyBandPortFwd = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.22, 7.8), MAT.planeNavy);
    navyBandPortFwd.position.set(-3.12, 4.18, 1.9);

    // Garis Belakang (Aft Cheatline: Z = 10.2 sampai Z = 32.0)
    const tealBandPortAft = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.55, 21.8), MAT.injourneyTeal);
    tealBandPortAft.position.set(-3.12, 4.6, 21.1);
    const navyBandPortAft = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.22, 21.8), MAT.planeNavy);
    navyBandPortAft.position.set(-3.12, 4.18, 21.1);
    const tailStripePort = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.45, 12), MAT.injourneyTeal);
    tailStripePort.position.set(-3.12 * 0.75, 5.7, 38);
    tailStripePort.rotation.x = 0.08;
    fuseGroup.add(tealBandPortFwd, navyBandPortFwd, tealBandPortAft, navyBandPortAft, tailStripePort);

    // Registrasi Pesawat PK-ACF (Port & Starboard Aft Fuselage)
    const regLabelPort = makeLabelSprite("PK-ACF", "BOEING 777F FREIGHTER", "#0CA1AF");
    regLabelPort.scale.set(4.5, 1.2, 1);
    regLabelPort.position.set(-3.18, 6.0, 32);
    regLabelPort.rotation.y = -Math.PI / 2;
    fuseGroup.add(regLabelPort);

    const regLabelStbd = makeLabelSprite("PK-ACF", "INJOURNEY AIR CARGO", "#0CA1AF");
    regLabelStbd.scale.set(4.5, 1.2, 1);
    regLabelStbd.position.set(3.18, 6.0, 32);
    regLabelStbd.rotation.y = Math.PI / 2;
    fuseGroup.add(regLabelStbd);

    // Papan Nama Livery Tipografi Bold "INJOURNEY AIR CARGO" pada Lambung Kiri (Menghadap Apron/Terminal)
    const liverySignL = makeMountedSignboard("INJOURNEY AIR CARGO", "BOEING 777-200LRF • PK-ACF", 16.0, 1.5, "#0CA1AF");
    liverySignL.position.set(-3.14, 5.85, 20.0);
    liverySignL.rotation.y = -Math.PI / 2;
    fuseGroup.add(liverySignL);

    // Bendera Merah Putih Indonesia di Kedua Sisi
    [-3.12, 3.12].forEach(lx => {
        const isLeft = (lx < 0);
        const flagRed = new THREE.Mesh(new THREE.PlaneGeometry(1.2, 0.4), new THREE.MeshBasicMaterial({ color: 0xDC2626 }));
        flagRed.rotation.y = isLeft ? -Math.PI / 2 : Math.PI / 2;
        flagRed.position.set(lx + (isLeft ? -0.03 : 0.03), 6.4, -2.2);
        const flagWhite = new THREE.Mesh(new THREE.PlaneGeometry(1.2, 0.4), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
        flagWhite.rotation.y = isLeft ? -Math.PI / 2 : Math.PI / 2;
        flagWhite.position.set(lx + (isLeft ? -0.03 : 0.03), 6.0, -2.2);
        fuseGroup.add(flagRed, flagWhite);
    });

    // Pintu Masuk Kru Pesawat (L1 Crew Entry Door di Sisi Port Z = -7.5)
    const crewDoor = new THREE.Mesh(new THREE.BoxGeometry(0.08, 1.9, 1.1), MAT.metalSilver);
    crewDoor.position.set(-3.13, 5.0, -7.5);
    const crewWindow = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.35, 0.25), MAT.cockpitGlass);
    crewWindow.position.set(-3.14, 5.4, -7.5);
    fuseGroup.add(crewDoor, crewWindow);

    // ─────────────────────────────────────────────────────────────────────────
    // 4. PINTU MASUK KARGO UTAMA TERBUKA NYATA & INTERIOR PALKA BOEING 777F
    // Main Deck Cargo Door (MDCD) Terbuka Lebar ke Atas & Palka Kargo Terang Benderang
    // ─────────────────────────────────────────────────────────────────────────
    const cargoHold = new THREE.Group();
    fuseGroup.add(cargoHold);

    // A. LANTAI ROL UTAMA PALKA (MAIN FREIGHT DECK ROLLER FLOOR - SEJAJAR PRESISI Y = 3.85)
    // Permukaan atas lantai tepat di Y = 3.85m, terhubung mulus tanpa celah ke jembatan High-Loader
    const deckFloor = new THREE.Mesh(
        new THREE.BoxGeometry(5.6, 0.12, 17.5),
        new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.6, metalness: 0.4 })
    );
    deckFloor.position.set(0, 3.79, 9.5);
    deckFloor.receiveShadow = true;
    cargoHold.add(deckFloor);

    // Matriks Rol Caster Ball Omnidirectional di Ambang Pintu Masuk Kargo (Ball Transfer Unit Mat)
    const ballMat = new THREE.Mesh(
        new THREE.BoxGeometry(2.6, 0.13, 4.35),
        new THREE.MeshStandardMaterial({ color: 0x475569, roughness: 0.4, metalness: 0.5 })
    );
    ballMat.position.set(-1.45, 3.80, 8.0);
    cargoHold.add(ballMat);

    // Puluhan Bola Caster Baja Berkilau di Lantai Pintu Masuk
    for (let bx = -2.55; bx <= -0.35; bx += 0.44) {
        for (let bz = 6.1; bz <= 9.9; bz += 0.44) {
            const bBall = new THREE.Mesh(new THREE.SphereGeometry(0.038, 8, 8), MAT.metalSilver);
            bBall.position.set(bx, 3.875, bz);
            cargoHold.add(bBall);
        }
    }

    // 4 Jalur Rel Rol Longitudinal Bermotor (PDU Roller Conveyor Tracks)
    [-1.85, -0.65, 0.65, 1.85].forEach(rx => {
        const track = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.035, 17.0), MAT.metalDark);
        track.position.set(rx, 3.865, 9.5);
        cargoHold.add(track);

        // Kunci Penahan Kontainer (Cargo Restraint Floor Latches Kuning Hazard)
        for (let lz = 2.0; lz <= 17.0; lz += 2.8) {
            const latch = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.07, 0.18), MAT.yellowSafety);
            latch.position.set(rx, 3.89, lz);
            cargoHold.add(latch);
        }
    });

    // B. DINDING & BULKHEAD INTERIOR PALKA (ILLUMINATED INTERIOR CABIN)
    // Dinding Kanan Starboard Dalam (White Composite Acoustic Wall Lining)
    const stbdWall = new THREE.Mesh(
        new THREE.BoxGeometry(0.12, 2.92, 17.4),
        new THREE.MeshStandardMaterial({ color: 0xF1F5F9, roughness: 0.7 })
    );
    stbdWall.position.set(2.76, 5.31, 9.5);
    cargoHold.add(stbdWall);

    // Ribs / Formers Vertikal Titanium di Dinding Dalam
    for (let rz = 1.6; rz <= 17.4; rz += 1.8) {
        const rib = new THREE.Mesh(new THREE.BoxGeometry(0.08, 2.92, 0.08), MAT.metalDark);
        rib.position.set(2.70, 5.31, rz);
        cargoHold.add(rib);
    }

    // Bulkhead Depan dengan Jaring Pengaman 9G (Forward Bulkhead Z = 0.8)
    const fwdBulkhead = new THREE.Mesh(
        new THREE.BoxGeometry(5.5, 2.92, 0.12),
        new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.8 })
    );
    fwdBulkhead.position.set(0, 5.31, 0.8);
    cargoHold.add(fwdBulkhead);

    // Bulkhead Belakang Palka (Aft Bulkhead Z = 18.2)
    const aftBulkhead = new THREE.Mesh(
        new THREE.BoxGeometry(5.5, 2.92, 0.12),
        new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.8 })
    );
    aftBulkhead.position.set(0, 5.31, 18.2);
    cargoHold.add(aftBulkhead);

    // Plafon Interior Palka (Ceiling Panel)
    const ceilPanel = new THREE.Mesh(
        new THREE.BoxGeometry(5.5, 0.08, 17.4),
        new THREE.MeshStandardMaterial({ color: 0xE2E8F0, roughness: 0.7 })
    );
    ceilPanel.position.set(0, 6.78, 9.5);
    cargoHold.add(ceilPanel);

    // Lampu Penerangan Interior Palka Terang Benderang (Super Bright Interior Cargo Lighting)
    const holdLightCenter = new THREE.PointLight(0xFFFFFF, 3.5, 14.0);
    holdLightCenter.position.set(-0.5, 6.4, 8.0);
    const holdLightFwd = new THREE.PointLight(0xFEF08A, 2.5, 12.0);
    holdLightFwd.position.set(0.5, 6.4, 13.0);
    const holdLightAft = new THREE.PointLight(0xFEF08A, 2.5, 12.0);
    holdLightAft.position.set(0.5, 6.4, 4.0);

    // Lampu Sorot Palka Menembus Keluar ke Jembatan High-Loader
    const doorSpot = new THREE.SpotLight(0xFFFFFF, 4.0, 16.0, Math.PI / 3.8, 0.3);
    doorSpot.position.set(0.2, 6.4, 8.0);
    doorSpot.target.position.set(-5.5, 3.85, 8.0);
    cargoHold.add(holdLightCenter, holdLightFwd, holdLightAft, doorSpot, doorSpot.target);

    // 2 Kontainer ULD AKE yang Sudah Terkunci Rapi di Palka Depan (Stowed Cargo)
    const stowedUld1 = createAuthenticAkeUld({ code: 'AKE 09144 GA', flight: 'GA-882', dest: 'AMS' });
    stowedUld1.position.set(0.8, 3.85, 13.5);
    const stowedUld2 = createAuthenticAkeUld({ code: 'AKE 05812 GA', flight: 'GA-882', dest: 'AMS' });
    stowedUld2.position.set(-0.8, 3.85, 15.6);
    cargoHold.add(stowedUld1, stowedUld2);

    // C. KUSEN & AMBANG PINTU KARGO AEROSPACE (DOOR SURROUND & THRESHOLD SILL)
    // Ambang Bawah Pintu (Threshold Plate Baja Stainless Steel dengan Bevel & Bumper)
    const doorThreshold = new THREE.Mesh(
        new THREE.BoxGeometry(0.35, 0.10, 4.45),
        new THREE.MeshStandardMaterial({ color: 0x94A3B8, roughness: 0.3, metalness: 0.8 })
    );
    doorThreshold.position.set(-2.85, 3.85, 8.0);
    cargoHold.add(doorThreshold);

    // Garis Zebra Hazard Kuning Ambang Pintu
    const hazardSill = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.02, 4.40), MAT.yellowSafety);
    hazardSill.position.set(-2.75, 3.91, 8.0);
    cargoHold.add(hazardSill);

    // Tiang Kusen Depan (Forward Door Jamb Z = 5.8)
    const jambFwd = new THREE.Mesh(new THREE.BoxGeometry(0.24, 2.92, 0.16), MAT.metalSilver);
    jambFwd.position.set(-2.82, 5.31, 5.8);
    const jambFwdSeal = new THREE.Mesh(new THREE.BoxGeometry(0.06, 2.90, 0.06), new THREE.MeshStandardMaterial({ color: 0x0F172A }));
    jambFwdSeal.position.set(-2.90, 5.31, 5.8);
    cargoHold.add(jambFwd, jambFwdSeal);

    // Tiang Kusen Belakang (Aft Door Jamb Z = 10.2)
    const jambAft = new THREE.Mesh(new THREE.BoxGeometry(0.24, 2.92, 0.16), MAT.metalSilver);
    jambAft.position.set(-2.82, 5.31, 10.2);
    const jambAftSeal = new THREE.Mesh(new THREE.BoxGeometry(0.06, 2.90, 0.06), new THREE.MeshStandardMaterial({ color: 0x0F172A }));
    jambAftSeal.position.set(-2.90, 5.31, 10.2);
    cargoHold.add(jambAft, jambAftSeal);

    // Balok Header Atas Ambang Pintu (Upper Door Header Y = 6.75)
    const headerBeam = new THREE.Mesh(new THREE.BoxGeometry(0.24, 0.16, 4.45), MAT.metalDark);
    headerBeam.position.set(-2.82, 6.75, 8.0);
    cargoHold.add(headerBeam);

    // Lubang Soket Kunci Pin Pengaman (14 Latch Sockets di Kusen Pintu)
    [-1.6, -0.8, 0, 0.8, 1.6].forEach(sz => {
        const sockFwd = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.06, 8), MAT.metalDark);
        sockFwd.position.set(-2.72, 4.2 + (sz + 1.6) * 0.7, 5.8);
        sockFwd.rotateZ(Math.PI / 2);
        const sockAft = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.06, 8), MAT.metalDark);
        sockAft.position.set(-2.72, 4.2 + (sz + 1.6) * 0.7, 10.2);
        sockAft.rotateZ(Math.PI / 2);
        cargoHold.add(sockFwd, sockAft);
    });

    // D. DAUN PINTU KARGO RAKSASA MEMBUKA KE ATAS & KE LUAR (OUTWARD-SWUNG MAIN DECK CARGO DOOR)
    // Engsel Terletak di Puncak Pintu (X = -2.82, Y = 6.75, Z = 8.0)
    const doorHingeGroup = new THREE.Group();
    doorHingeGroup.position.set(-2.82, 6.75, 8.0);

    // Daun Pintu (Tergantung dari engsel ke bawah sepanjang 2.90m)
    const hatchPanel = new THREE.Mesh(
        new THREE.BoxGeometry(0.14, 2.88, 4.38),
        new THREE.MeshStandardMaterial({ color: 0xF8FAFC, roughness: 0.35, metalness: 0.15 })
    );
    hatchPanel.position.set(0, -1.44, 0);
    hatchPanel.castShadow = true;
    doorHingeGroup.add(hatchPanel);

    // Garis Livery InJourney Teal Melintasi Daun Pintu
    const hatchStripe = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.52, 4.38), MAT.injourneyTeal);
    hatchStripe.position.set(0, -2.15, 0);
    doorHingeGroup.add(hatchStripe);

    // Garis Navy Livery Bawah di Daun Pintu
    const hatchNavy = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.20, 4.38), MAT.planeNavy);
    hatchNavy.position.set(0, -2.52, 0);
    doorHingeGroup.add(hatchNavy);

    // Rangka Grid Penguat Struktural Dalam (Internal Waffle Stiffeners Aluminium)
    for (let hy = -0.5; hy >= -2.6; hy -= 0.65) {
        const ribH = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.06, 4.30), MAT.metalSilver);
        ribH.position.set(0.08, hy, 0);
        doorHingeGroup.add(ribH);
    }
    for (let hz = -1.8; hz <= 1.8; hz += 0.9) {
        const ribV = new THREE.Mesh(new THREE.BoxGeometry(0.06, 2.75, 0.06), MAT.metalSilver);
        ribV.position.set(0.08, -1.44, hz);
        doorHingeGroup.add(ribV);
    }

    // Garis Peringatan Hazard Merah/Putih di Bibir Bawah Daun Pintu
    const doorEdgeWarning = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.08, 4.38), new THREE.MeshStandardMaterial({ color: 0xEF4444 }));
    doorEdgeWarning.position.set(0, -2.84, 0);
    doorHingeGroup.add(doorEdgeWarning);

    // Lampu Strobe Amber Peringatan Pintu Terbuka
    const doorBeacon = new THREE.Mesh(new THREE.SphereGeometry(0.08, 12, 12), new THREE.MeshBasicMaterial({ color: 0xF59E0B }));
    doorBeacon.position.set(0, -2.85, 2.1);
    doorHingeGroup.add(doorBeacon);

    // BUKA PINTU KE ATAS & KE LUAR SEBESAR -76 DERAJAT (-Math.PI * 0.42)
    // Menghadap ke luar apron menaungi jembatan High-Loader bagai kanopi pelindung!
    doorHingeGroup.rotation.z = -Math.PI * 0.42;
    cargoHold.add(doorHingeGroup);
    planeCargoDoorHatch = doorHingeGroup;

    // E. 2 SILINDER HIDROLIK RAKSASA PENAHAN PINTU (DUAL TELESCOPIC DOOR ACTUATORS)
    [-1.75, 1.75].forEach(az => {
        const hydBase = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.07, 1.6, 12), MAT.metalDark);
        hydBase.position.set(-2.82, 5.2, 8.0 + az);
        hydBase.rotation.z = -Math.PI * 0.28;

        const hydPiston = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.045, 1.5, 12), MAT.metalSilver);
        hydPiston.position.set(-3.45, 6.2, 8.0 + az);
        hydPiston.rotation.z = -Math.PI * 0.28;

        cargoHold.add(hydBase, hydPiston);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // 5. SAYAP SWEPT-WING AERODINAMIS MULUS (CLEAN AERO AIRFOIL DENGAN SHARKLETS)
    // Pangkal sayap (Wing Root) berawal di Z = 12.8, bersih 2.65m di belakang pintu kargo (Z = 8.0)
    // ─────────────────────────────────────────────────────────────────────────
    let strobeWingL = null;
    let strobeWingR = null;

    function createSmoothAirfoilWing(isLeft) {
        const wingGroup = new THREE.Group();
        const side = isLeft ? -1 : 1;

        const SPAN_STATIONS = 12;
        const positions = [];
        const indices = [];

        for (let i = 0; i <= SPAN_STATIONS; i++) {
            const t = i / SPAN_STATIONS;
            const x = side * (3.15 + t * 24.5);
            const z_le = 12.8 + t * 14.2;
            const chord = 8.8 * (1.0 - 0.72 * t);
            const z_te = z_le + chord;
            const y_c = 4.20 + t * 1.85;
            const th = 0.75 * (1.0 - 0.68 * t);

            positions.push(
                x, y_c, z_le,                             // 0
                x, y_c + th * 0.52, z_le + chord * 0.28,  // 1
                x, y_c + th * 0.45, z_le + chord * 0.70,  // 2
                x, y_c, z_te,                             // 3
                x, y_c - th * 0.42, z_le + chord * 0.70,  // 4
                x, y_c - th * 0.48, z_le + chord * 0.28   // 5
            );

            if (i > 0) {
                const cOff = i * 6;
                const pOff = (i - 1) * 6;

                for (let k = 0; k < 6; k++) {
                    const nextK = (k + 1) % 6;
                    const p1 = pOff + k;
                    const p2 = pOff + nextK;
                    const c1 = cOff + k;
                    const c2 = cOff + nextK;

                    indices.push(p1, p2, c2);
                    indices.push(p1, c2, c1);
                }
            }
        }

        const tipOff = SPAN_STATIONS * 6;
        indices.push(0, 1, 2); indices.push(0, 2, 3); indices.push(0, 3, 4); indices.push(0, 4, 5);
        indices.push(tipOff, tipOff + 2, tipOff + 1); indices.push(tipOff, tipOff + 3, tipOff + 2);
        indices.push(tipOff, tipOff + 4, tipOff + 3); indices.push(tipOff, tipOff + 5, tipOff + 4);

        const wingGeo = new THREE.BufferGeometry();
        wingGeo.setAttribute('position', new THREE.BufferAttribute(new Float32Array(positions), 3));
        wingGeo.setIndex(indices);
        wingGeo.computeVertexNormals();

        const wingMesh = new THREE.Mesh(wingGeo, MAT.planeWhite);
        wingMesh.castShadow = true;
        wingMesh.receiveShadow = true;
        wingGroup.add(wingMesh);

        // Leading Edge Slat (Bilah Tepi Depan Logam Mengkilap)
        const slatGeo = new THREE.BufferGeometry();
        const slatPos = [];
        const slatInd = [];
        for (let i = 0; i <= SPAN_STATIONS; i++) {
            const t = i / SPAN_STATIONS;
            const x = side * (3.15 + t * 24.5);
            const z_le = 12.8 + t * 14.2;
            const y_c = 4.20 + t * 1.85;
            const th = 0.75 * (1.0 - 0.68 * t);
            slatPos.push(
                x, y_c, z_le - 0.05,
                x, y_c + th * 0.35, z_le + 0.55
            );
            if (i > 0) {
                const c = i * 2;
                const p = (i - 1) * 2;
                slatInd.push(p, p + 1, c + 1);
                slatInd.push(p, c + 1, c);
            }
        }
        slatGeo.setAttribute('position', new THREE.BufferAttribute(new Float32Array(slatPos), 3));
        slatGeo.setIndex(slatInd);
        slatGeo.computeVertexNormals();
        const slatMesh = new THREE.Mesh(slatGeo, MAT.metalSilver);
        wingGroup.add(slatMesh);

        // 3 Flap Track Fairings ("Canoe" Pods Aerodinamis di Bawah Sayap)
        [0.24, 0.52, 0.78].forEach(ft => {
            const fx = side * (3.15 + ft * 24.5);
            const fz = (12.8 + ft * 14.2) + (8.8 * (1.0 - 0.72 * ft)) * 0.95;
            const fy = (4.20 + ft * 1.85) - 0.35;

            const canoeGroup = new THREE.Group();
            canoeGroup.position.set(fx, fy, fz);

            const cBody = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.16, 4.2, 16), MAT.metalSilver);
            cBody.rotateX(Math.PI / 2);
            const cSpike = new THREE.Mesh(new THREE.ConeGeometry(0.16, 1.2, 16), MAT.metalDark);
            cSpike.rotateX(-Math.PI / 2);
            cSpike.position.z = 2.7;

            canoeGroup.add(cBody, cSpike);
            wingGroup.add(canoeGroup);
        });

        // Sharklet / Raked Wingtip Aerodinamis di Ujung Sayap (InJourney Teal)
        const tipX = side * (3.15 + 24.5);
        const tipZ = 12.8 + 14.2 + 1.2;
        const tipY = 4.20 + 1.85;

        const sharklet = new THREE.Mesh(new THREE.BoxGeometry(0.14, 2.5, 1.8), MAT.injourneyTeal);
        sharklet.position.set(tipX + side * 0.25, tipY + 1.15, tipZ + 0.8);
        sharklet.rotation.z = side * 0.22;
        sharklet.rotation.x = -0.35;
        sharklet.castShadow = true;
        wingGroup.add(sharklet);

        // Lampu Navigasi Sayap (Kiri = Merah, Kanan = Hijau) & Strobe Putih
        const navCol = isLeft ? 0xEF4444 : 0x10B981;
        const navLight = new THREE.Mesh(new THREE.SphereGeometry(0.2, 12, 12), new THREE.MeshBasicMaterial({ color: navCol }));
        navLight.position.set(tipX + side * 0.35, tipY + 0.6, tipZ);
        const strobeLight = new THREE.Mesh(new THREE.SphereGeometry(0.15, 8, 8), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
        strobeLight.position.set(tipX + side * 0.35, tipY + 2.2, tipZ + 1.4);
        wingGroup.add(navLight, strobeLight);

        if (isLeft) {
            strobeWingL = strobeLight;
        } else {
            strobeWingR = strobeLight;
        }

        return wingGroup;
    }

    plane.add(createSmoothAirfoilWing(true));
    plane.add(createSmoothAirfoilWing(false));

    // ─────────────────────────────────────────────────────────────────────────
    // 6. MESIN JET TURBOFAN GE90 HIGH-BYPASS DENGAN 3D ROTOR FAN BLADES
    // ─────────────────────────────────────────────────────────────────────────
    function createHighBypassEngine(isLeft) {
        const eng = new THREE.Group();
        const side = isLeft ? -1 : 1;
        eng.position.set(side * 9.8, 2.85, 16.8);

        // Pylon Aerodinamis Penyangga Mesin ke Bawah Sayap
        const pylon = new THREE.Mesh(new THREE.BoxGeometry(0.42, 1.6, 5.0), MAT.metalSilver);
        pylon.position.set(0, 1.3, 0.4);
        pylon.rotation.x = 0.08;
        eng.add(pylon);

        // Cowling Nacelle Utama Mesin
        const nacelleGeo = new THREE.CylinderGeometry(1.65, 1.55, 6.4, 32);
        nacelleGeo.rotateX(Math.PI / 2);
        const nacelle = new THREE.Mesh(nacelleGeo, MAT.planeWhite);
        nacelle.castShadow = true;
        eng.add(nacelle);

        // Aksen Livery InJourney Teal di Cowling Mesin
        const nacelleBand = new THREE.Mesh(new THREE.CylinderGeometry(1.66, 1.66, 0.8, 32), MAT.injourneyTeal);
        nacelleBand.rotateX(Math.PI / 2);
        nacelleBand.position.set(0, 0, 0.2);
        eng.add(nacelleBand);

        // Bibir Saluran Masuk Logam Mengkilap (Chrome Inlet Lip)
        const inletLip = new THREE.Mesh(new THREE.TorusGeometry(1.6, 0.14, 20, 36), MAT.chromeIntake);
        inletLip.position.set(0, 0, -3.2);
        eng.add(inletLip);

        // Terowongan Intake Gelap Bagian Dalam
        const duct = new THREE.Mesh(new THREE.CylinderGeometry(1.5, 1.5, 1.6, 32), MAT.engineCore);
        duct.rotateX(Math.PI / 2);
        duct.position.set(0, 0, -2.4);
        eng.add(duct);

        // Rotor Kipas 3D & Spinner Cone Dinamis (Berputar Otomatis di Loop Animasi)
        const fanDisk = new THREE.Group();
        fanDisk.position.set(0, 0, -2.1);

        const spinner = new THREE.Mesh(new THREE.ConeGeometry(0.45, 1.1, 20), MAT.engineCore);
        spinner.rotateX(-Math.PI / 2);
        fanDisk.add(spinner);

        const spiralRing = new THREE.Mesh(new THREE.TorusGeometry(0.22, 0.025, 8, 16), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
        spiralRing.position.set(0, 0, -0.4);
        fanDisk.add(spiralRing);

        const bladeGeo = new THREE.BoxGeometry(0.12, 0.85, 0.04);
        for (let b = 0; b < 18; b++) {
            const angle = (b / 18) * Math.PI * 2;
            const blade = new THREE.Mesh(bladeGeo, MAT.metalSilver);
            blade.position.set(Math.cos(angle) * 0.9, Math.sin(angle) * 0.9, -0.25);
            blade.rotation.z = angle + 0.45;
            fanDisk.add(blade);
        }
        eng.add(fanDisk);
        planeFanBlades.push(fanDisk);

        // Saluran Pembuangan Knalpot Inti (Exhaust Core Nozzle & Center Spike)
        const exhaustCore = new THREE.Mesh(new THREE.CylinderGeometry(1.05, 1.25, 1.4, 32), MAT.engineCore);
        exhaustCore.rotateX(Math.PI / 2);
        exhaustCore.position.set(0, 0, 3.5);
        eng.add(exhaustCore);

        const exhaustSpike = new THREE.Mesh(new THREE.ConeGeometry(0.42, 1.4, 20), MAT.metalSilver);
        exhaustSpike.rotateX(Math.PI / 2);
        exhaustSpike.position.set(0, 0, 4.6);
        eng.add(exhaustSpike);

        return eng;
    }

    plane.add(createHighBypassEngine(true));
    plane.add(createHighBypassEngine(false));

    // ─────────────────────────────────────────────────────────────────────────
    // 7. EKOR PESAWAT (EMPENNAGE: VERTICAL FIN DENGAN LIVERY & HORIZONTAL TAIL)
    // ─────────────────────────────────────────────────────────────────────────
    const empennage = new THREE.Group();
    empennage.position.set(0, 6.2, 37);

    // Dorsal Fin Fairing (Sirip Transisi dari Punggung Fuselage ke Fin)
    const dorsalFin = new THREE.Mesh(new THREE.BoxGeometry(0.35, 1.8, 8.5), MAT.planeWhite);
    dorsalFin.position.set(0, 1.2, -1.8);
    dorsalFin.rotation.x = -0.32;
    empennage.add(dorsalFin);

    // Sirip Tegak Vertikal (Vertical Stabilizer Swept Fin)
    const vFin = new THREE.Mesh(new THREE.BoxGeometry(0.42, 9.4, 7.2), MAT.planeNavy);
    vFin.position.set(0, 5.0, 3.0);
    vFin.rotation.x = Math.PI / 6.4;
    vFin.castShadow = true;
    empennage.add(vFin);

    // Livery Wave Teal di Sirip Tegak
    const vFinTeal = new THREE.Mesh(new THREE.BoxGeometry(0.44, 4.8, 4.2), MAT.injourneyTeal);
    vFinTeal.position.set(0, 3.5, 2.5);
    vFinTeal.rotation.x = Math.PI / 6.4;
    empennage.add(vFinTeal);

    // Rudder Kemudi Putih di Bagian Belakang Sirip
    const rudder = new THREE.Mesh(new THREE.BoxGeometry(0.38, 8.2, 1.4), MAT.planeWhite);
    rudder.position.set(0, 5.2, 5.8);
    rudder.rotation.x = Math.PI / 6.4;
    empennage.add(rudder);

    // Strobe Beacon Putih di Puncak Ekor
    const topStrobe = new THREE.Mesh(new THREE.SphereGeometry(0.18, 12, 12), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
    topStrobe.position.set(0, 9.6, 5.2);
    empennage.add(topStrobe);

    plane.add(empennage);

    // Horizontal Stabilizers (Tailplanes Kiri & Kanan)
    [-1, 1].forEach(side => {
        const hStabGroup = new THREE.Group();
        hStabGroup.position.set(side * 1.0, 6.8, 41);

        const hStab = new THREE.Mesh(new THREE.BoxGeometry(8.4, 0.38, 3.8), MAT.planeWhite);
        hStab.position.set(side * 4.2, 0, 0);
        hStab.rotation.y = -side * 0.22;
        hStab.rotation.z = side * 0.06;
        hStab.castShadow = true;
        hStabGroup.add(hStab);

        // Silver Slat Leading Edge Horizontal Stabilizer
        const hSlat = new THREE.Mesh(new THREE.BoxGeometry(8.4, 0.4, 0.4), MAT.metalSilver);
        hSlat.position.set(side * 4.2, 0, -1.8);
        hSlat.rotation.y = -side * 0.22;
        hStabGroup.add(hSlat);

        plane.add(hStabGroup);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // 8. RODA PENDARAT REALISTIS (AUTHENTIC 6-WHEEL TRIPLE-AXLE BOGIES & NOSE GEAR)
    // ─────────────────────────────────────────────────────────────────────────
    // Roda Depan (Nose Landing Gear - NLG): 2 Roda di Y = 0
    const nlg = new THREE.Group();
    nlg.position.set(0, 0, -6.5);

    const nlgStrut = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.14, 4.3, 16), MAT.metalSilver);
    nlgStrut.position.y = 2.15;
    nlg.add(nlgStrut);

    // Lampu Sorot Taxi & Takeoff Depan (Dual High-Intensity Lights)
    [-0.2, 0.2].forEach(tx => {
        const nLight = new THREE.Mesh(new THREE.BoxGeometry(0.2, 0.2, 0.15), new THREE.MeshBasicMaterial({ color: 0xFFFBEB }));
        nLight.position.set(tx, 1.8, -0.2);
        nlg.add(nLight);
    });

    const nlgAxle = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 1.2, 12), MAT.metalDark);
    nlgAxle.rotateZ(Math.PI / 2);
    nlgAxle.position.y = 0.55;
    nlg.add(nlgAxle);

    [-0.45, 0.45].forEach(nx => {
        const nTire = new THREE.Mesh(new THREE.CylinderGeometry(0.55, 0.55, 0.32, 24), MAT.truckTire);
        nTire.rotateZ(Math.PI / 2);
        nTire.position.set(nx, 0.55, 0);

        const nRim = new THREE.Mesh(new THREE.CylinderGeometry(0.36, 0.36, 0.34, 16), MAT.metalSilver);
        nRim.rotateZ(Math.PI / 2);
        nRim.position.set(nx, 0.55, 0);

        nlg.add(nTire, nRim);
    });
    plane.add(nlg);

    // Roda Pendarat Utama (Main Landing Gear - MLG): TRIPLE-AXLE 6-WHEEL BOGIES!
    // Kiri & Kanan (Port & Starboard) di X = ±4.3m, Z = 18.5m
    [-4.3, 4.3].forEach(gearX => {
        const mlg = new THREE.Group();
        mlg.position.set(gearX, 0, 18.5);

        // Shock Strut Utama Besar
        const mStrut = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.22, 4.4, 16), MAT.metalDark);
        mStrut.position.y = 2.2;
        mlg.add(mStrut);

        // Bogie Beam 3-Axle Panjang
        const bogieBeam = new THREE.Mesh(new THREE.BoxGeometry(1.25, 0.28, 3.8), MAT.metalSilver);
        bogieBeam.position.y = 0.65;
        mlg.add(bogieBeam);

        // 3 Gandar Roda (Depan, Tengah, Belakang) x 2 Roda = 6 Roda per Sisi
        const AXLE_Z_OFFSETS = [-1.3, 0.0, 1.3];
        AXLE_Z_OFFSETS.forEach(az => {
            const axle = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 1.45, 12), MAT.metalDark);
            axle.rotateZ(Math.PI / 2);
            axle.position.set(0, 0.65, az);
            mlg.add(axle);

            [-0.62, 0.62].forEach(wx => {
                const mTire = new THREE.Mesh(new THREE.CylinderGeometry(0.65, 0.65, 0.38, 24), MAT.truckTire);
                mTire.rotateZ(Math.PI / 2);
                mTire.position.set(wx, 0.65, az);

                const mRim = new THREE.Mesh(new THREE.CylinderGeometry(0.42, 0.42, 0.40, 16), MAT.metalSilver);
                mRim.rotateZ(Math.PI / 2);
                mRim.position.set(wx, 0.65, az);

                // Brake Assembly Disk
                const brake = new THREE.Mesh(new THREE.CylinderGeometry(0.32, 0.32, 0.12, 12), MAT.engineCore);
                brake.rotateZ(Math.PI / 2);
                brake.position.set(wx * 0.7, 0.65, az);

                mlg.add(mTire, mRim, brake);
            });
        });

        plane.add(mlg);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // 9. LAMPU NAVIGASI & BEACON KEDIRGANTARAAN (AVIATION LIGHTS)
    // ─────────────────────────────────────────────────────────────────────────
    const bcnTop = new THREE.Mesh(new THREE.SphereGeometry(0.24, 12, 12), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
    bcnTop.position.set(0, 8.4, 8.0);
    const bcnBtm = new THREE.Mesh(new THREE.SphereGeometry(0.24, 12, 12), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
    bcnBtm.position.set(0, 2.2, 10.0);
    plane.add(bcnTop, bcnBtm);

    planeAviationLights = {
        strobeWingL: strobeWingL || new THREE.Mesh(new THREE.SphereGeometry(0.15, 8, 8), new THREE.MeshBasicMaterial({ color: 0xFFFFFF })),
        strobeWingR: strobeWingR || new THREE.Mesh(new THREE.SphereGeometry(0.15, 8, 8), new THREE.MeshBasicMaterial({ color: 0xFFFFFF })),
        bcnTop,
        bcnBtm
    };

    // ─────────────────────────────────────────────────────────────────────────
    // 10. MARKA KESELAMATAN APRON & SAFETY CONES
    // ─────────────────────────────────────────────────────────────────────────
    const CONE_COORDS = [
        [-9.8, 10.5], [9.8, 10.5],
        [-27.6, 24.5], [27.6, 24.5],
        [0, -17.5]
    ];
    CONE_COORDS.forEach(([cx, cz]) => {
        const cone = new THREE.Mesh(new THREE.ConeGeometry(0.24, 0.8, 14), new THREE.MeshStandardMaterial({ color: 0xF97316, roughness: 0.5 }));
        cone.position.set(cx, 0.4, cz);
        const coneStripe = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.18, 0.22, 14), new THREE.MeshStandardMaterial({ color: 0xFFFFFF }));
        coneStripe.position.set(cx, 0.4, cz);
        plane.add(cone, coneStripe);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // 11. GROUND SUPPORT EQUIPMENT (GSE) TAMBAHAN APRON
    // Wheel Chocks
    // ─────────────────────────────────────────────────────────────────────────

    // Wheel Chocks Karet Ganjal Roda Nose Gear
    [-0.55, 0.55].forEach(cwz => {
        const chock = new THREE.Mesh(new THREE.BoxGeometry(0.85, 0.16, 0.22), MAT.yellowSafety);
        chock.position.set(0, 0.08, -6.5 + cwz);
        plane.add(chock);
    });



    // Interaktivitas
    bindInteractive(fwdMainFuselage, 'loading');
    bindInteractive(aftMainFuselage, 'loading');
    bindInteractive(hatchPanel, 'loading');
    bindInteractive(radomeCap, 'loading');

    return plane;
}
airsideGroup.add(createDetailedCargoPlane());

// Label Melayang LOKASI 6: PEMUATAN PESAWAT (AIRSIDE)
const loadingLabel = makeLabelSprite("LOKASI 6", "PEMUATAN PESAWAT (AIRSIDE)", "#38BDF8");
loadingLabel.position.set(66, 10.5, 8);
airsideGroup.add(loadingLabel);



// Garis Berhenti Roda Depan (Nose Wheel Stop Line di Z = -16.0)
const stopLine = new THREE.Mesh(new THREE.BoxGeometry(4.5, 0.02, 0.35), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
stopLine.position.set(74.0, 0.025, -16.0);
airsideGroup.add(stopLine);

// Kotak Zona Merah Batas Keselamatan Pesawat (Aircraft Safety Clearance Envelope)
const envelopePts = [
    new THREE.Vector3(45.0, 0.025, -18.0),
    new THREE.Vector3(103.0, 0.025, -18.0),
    new THREE.Vector3(103.0, 0.025, 48.0),
    new THREE.Vector3(45.0, 0.025, 48.0),
    new THREE.Vector3(45.0, 0.025, -18.0)
];
const envLine = new THREE.Line(new THREE.BufferGeometry().setFromPoints(envelopePts), new THREE.LineBasicMaterial({ color: 0xEF4444 }));
airsideGroup.add(envLine);

// Lampu Tepi Taxiway Apron Biru LED (Taxiway Edge Blue Lights)
[-15, 0, 15, 30, 45].forEach(lz => {
    [43.0, 105.0].forEach(lx => {
        const fixture = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.08, 0.3, 8), MAT.metalDark);
        fixture.position.set(lx, 0.15, lz);
        const lBulb = new THREE.Mesh(new THREE.SphereGeometry(0.08, 8, 8), new THREE.MeshBasicMaterial({ color: 0x38BDF8 }));
        lBulb.position.set(lx, 0.32, lz);
        airsideGroup.add(fixture, lBulb);
    });
});



airsideGroup.add(makeTarmacText("NOSE WHEEL STOP", 6.0, 2.0, "#EF4444"));
airsideGroup.children[airsideGroup.children.length - 1].position.set(74.0, 0.025, -17.2);

airsideGroup.add(makeTarmacText("INJOURNEY CARGO APRON", 10.0, 2.5, "#0CA1AF"));
airsideGroup.children[airsideGroup.children.length - 1].position.set(74.0, 0.025, 6.0);

// Marka Cat Dermaga Truk (Landside Dock Markings)
dockGroup.add(makeTarmacText("BAY 1", 3.2, 1.8, "#38BDF8"));
dockGroup.children[dockGroup.children.length - 1].position.set(-26.0, 0.03, -33.2);

dockGroup.add(makeTarmacText("BAY 2 - LIVE", 4.2, 1.8, "#10B981"));
dockGroup.children[dockGroup.children.length - 1].position.set(-5.0, 0.03, -33.2);

dockGroup.add(makeTarmacText("BAY 3", 3.2, 1.8, "#38BDF8"));
dockGroup.children[dockGroup.children.length - 1].position.set(22.0, 0.03, -33.2);

// Mobil Penarik Kargo Apron & Gandengan ULD Dolly (Airport Cargo Tug & Dollies)
function createAirportCargoTug(x, z, rotY) {
    const tugGroup = new THREE.Group();
    tugGroup.position.set(x, 0, z);
    tugGroup.rotation.y = rotY;

    // Body Tug Tractor
    const tugBody = new THREE.Mesh(new THREE.BoxGeometry(1.8, 1.0, 3.0), MAT.yellowSafety);
    tugBody.position.set(0, 0.6, 0);
    const bumperF = new THREE.Mesh(new THREE.BoxGeometry(1.85, 0.35, 0.25), MAT.metalDark);
    bumperF.position.set(0, 0.35, -1.55);
    const bumperR = new THREE.Mesh(new THREE.BoxGeometry(1.85, 0.35, 0.25), MAT.metalDark);
    bumperR.position.set(0, 0.35, 1.55);

    // Cabin Open Frame
    const cabRoof = new THREE.Mesh(new THREE.BoxGeometry(1.5, 0.06, 1.5), MAT.metalDark);
    cabRoof.position.set(0, 1.95, 0.2);
    [-0.65, 0.65].forEach(cx => {
        [-0.55, 0.55].forEach(cz => {
            const pillar = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, 0.95, 8), MAT.metalSilver);
            pillar.position.set(cx, 1.48, 0.2 + cz);
            tugGroup.add(pillar);
        });
    });

    const seat = new THREE.Mesh(new THREE.BoxGeometry(0.65, 0.45, 0.5), MAT.metalDark);
    seat.position.set(0, 1.25, 0.1);

    // Lampu Peringatan Beacon Kuning Berputar di Atas Kabin
    const beacon = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 0.18, 12), new THREE.MeshBasicMaterial({ color: 0xF59E0B }));
    beacon.position.set(0, 2.08, 0.2);

    // 4 Roda Karet Tug
    const wGeo = new THREE.CylinderGeometry(0.35, 0.35, 0.26, 16);
    wGeo.rotateZ(Math.PI / 2);
    [[-0.95, 0.35, -0.9], [0.95, 0.35, -0.9], [-0.95, 0.35, 0.9], [0.95, 0.35, 0.9]].forEach(([wx, wy, wz]) => {
        const w = new THREE.Mesh(wGeo, MAT.truckTire);
        w.position.set(wx, wy, wz);
        tugGroup.add(w);
    });

    tugGroup.add(tugBody, bumperF, bumperR, cabRoof, seat, beacon);

    // Gandengan Dolly 1 & 2 (Low-Bed ULD Dollies with AKE Containers)
    [4.6, 8.8].forEach((offsetZ, dIdx) => {
        const dolly = new THREE.Group();
        dolly.position.set(0, 0, offsetZ);

        const towBar = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.06, 1.6), MAT.metalDark);
        towBar.position.set(0, 0.22, -0.9);
        dolly.add(towBar);

        const bed = new THREE.Mesh(new THREE.BoxGeometry(2.3, 0.18, 2.2), MAT.conveyorRoller);
        bed.position.set(0, 0.26, 0.1);
        const rim = new THREE.Mesh(new THREE.BoxGeometry(2.35, 0.08, 2.25), MAT.yellowSafety);
        rim.position.set(0, 0.36, 0.1);
        dolly.add(bed, rim);

        // Roda Dolly Kecil
        const dwGeo = new THREE.CylinderGeometry(0.18, 0.18, 0.14, 12);
        dwGeo.rotateZ(Math.PI / 2);
        [[-1.1, 0.18, -0.6], [1.1, 0.18, -0.6], [-1.1, 0.18, 0.8], [1.1, 0.18, 0.8]].forEach(([wx, wy, wz]) => {
            const dw = new THREE.Mesh(dwGeo, MAT.truckTire);
            dw.position.set(wx, wy, wz);
            dolly.add(dw);
        });

        // Kontainer ULD AKE di Atas Dolly
        const uldBox = new THREE.Mesh(new THREE.BoxGeometry(1.9, 1.5, 1.8), MAT.uldAluminum);
        uldBox.position.set(0, 1.15, 0.1);
        const uldCurtain = new THREE.Mesh(new THREE.PlaneGeometry(1.6, 1.3), (dIdx === 0) ? MAT.uldCanvasBlue : new THREE.MeshStandardMaterial({ color: 0x059669 }));
        uldCurtain.position.set(0, 1.15, 1.01);
        dolly.add(uldBox, uldCurtain);

        tugGroup.add(dolly);
    });

    return tugGroup;
}
airsideGroup.add(createAirportCargoTug(54.0, 36.0, -Math.PI / 2));

// ─────────────────────────────────────────────────────────────────────────────
// PERALATAN KESELAMATAN APRON SISI UDARA & MARSHALLER (AIRSIDE GSE & SAFETY)
// ─────────────────────────────────────────────────────────────────────────────
function createApronGroundSafetyEquipment() {
    const gseGroup = new THREE.Group();

    // 1. PETUGAS MARSHALLER LAPANGAN (3D FIGURE DENGAN ROMPI REFLEKTIF & TONGKAT MARSHALLING)
    const marshalGroup = new THREE.Group();
    marshalGroup.position.set(74.0, 0, -13.8);

    const vestMat = new THREE.MeshStandardMaterial({ color: 0xFACC15, roughness: 0.3 });
    const pantsMat = new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.7 });
    const skinMat = new THREE.MeshStandardMaterial({ color: 0xD4A373, roughness: 0.6 });

    // Kaki & Celana
    [-0.14, 0.14].forEach(lx => {
        const leg = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.85, 0.18), pantsMat);
        leg.position.set(lx, 0.425, 0);
        marshalGroup.add(leg);
    });

    // Tubuh & Rompi Keselamatan
    const torso = new THREE.Mesh(new THREE.BoxGeometry(0.48, 0.65, 0.28), vestMat);
    torso.position.set(0, 1.15, 0);
    const stripeR1 = new THREE.Mesh(new THREE.BoxGeometry(0.49, 0.06, 0.29), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
    stripeR1.position.set(0, 1.15, 0);
    const stripeR2 = new THREE.Mesh(new THREE.BoxGeometry(0.49, 0.06, 0.29), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
    stripeR2.position.set(0, 0.95, 0);
    marshalGroup.add(torso, stripeR1, stripeR2);

    // Kepala & Helm Putih
    const head = new THREE.Mesh(new THREE.SphereGeometry(0.14, 12, 12), skinMat);
    head.position.set(0, 1.62, 0);
    const helmet = new THREE.Mesh(new THREE.SphereGeometry(0.16, 12, 12), new THREE.MeshStandardMaterial({ color: 0xFFFFFF }));
    helmet.position.set(0, 1.68, 0);
    marshalGroup.add(head, helmet);

    // Lengan Mengacungkan Tongkat Marshalling Oranye Terang
    [-0.32, 0.32].forEach((ax, idx) => {
        const arm = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.45, 0.12), vestMat);
        arm.position.set(ax, 1.25, 0.2);
        arm.rotation.x = -0.5;

        const wand = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, 0.55, 8), new THREE.MeshBasicMaterial({ color: 0xFF5500 }));
        wand.position.set(ax, 1.48, 0.38);
        wand.rotation.x = -0.2;
        marshalGroup.add(arm, wand);
    });
    gseGroup.add(marshalGroup);

    // 2. KERUCUT KESELAMATAN PENERBANGAN (AVIATION TRAFFIC SAFETY CONES)
    function createSafetyCone(cx, cz) {
        const cone = new THREE.Group();
        cone.position.set(cx, 0, cz);

        const base = new THREE.Mesh(new THREE.BoxGeometry(0.45, 0.04, 0.45), MAT.hazardBlack);
        base.position.y = 0.02;

        const body = new THREE.Mesh(new THREE.ConeGeometry(0.18, 0.75, 16), MAT.safetyOrange);
        body.position.y = 0.38;

        const collar = new THREE.Mesh(new THREE.CylinderGeometry(0.11, 0.14, 0.16, 16), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
        collar.position.y = 0.42;

        cone.add(base, body, collar);
        return cone;
    }

    [
        [61.0, 1.0], [63.5, 7.0],
        [84.5, 1.0], [87.0, 7.0],
        [72.5, -16.0], [75.5, -16.0],
        [44.0, 16.0], [104.0, 16.0]
    ].forEach(([cx, cz]) => {
        gseGroup.add(createSafetyCone(cx, cz));
    });

    // 3. GANJAL RODA PESAWAT (RUBBER WHEEL CHOCKS)
    function createWheelChock(cx, cz) {
        const chock = new THREE.Mesh(new THREE.BoxGeometry(0.7, 0.22, 0.25), new THREE.MeshStandardMaterial({ color: 0xF59E0B, roughness: 0.6 }));
        chock.position.set(cx, 0.11, cz);
        return chock;
    }
    gseGroup.add(createWheelChock(60.5, 0.6));
    gseGroup.add(createWheelChock(60.5, 3.2));
    gseGroup.add(createWheelChock(67.5, 0.6));
    gseGroup.add(createWheelChock(67.5, 3.2));

    // 4. TIANG WINDSOCK BANDARA
    const windGroup = new THREE.Group();
    windGroup.position.set(86.0, 0, 38.0);
    const mast = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.12, 10.0, 8), MAT.metalSilver);
    mast.position.y = 5.0;
    const beaconLight = new THREE.Mesh(new THREE.SphereGeometry(0.12, 8, 8), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
    beaconLight.position.y = 10.15;
    const sock = new THREE.Mesh(new THREE.ConeGeometry(0.35, 2.2, 12, 1, true), MAT.safetyOrange);
    sock.rotation.z = Math.PI / 2;
    sock.position.set(1.1, 9.6, 0);
    windGroup.add(mast, beaconLight, sock);
    gseGroup.add(windGroup);

    return gseGroup;
}
airsideGroup.add(createApronGroundSafetyEquipment());

// ─────────────────────────────────────────────────────────────────────────────
// MOVING TRUCK MODEL (TRUK KONTENER DETAIL DENGAN HEADLIGHT & PINTU BUKA)
// ─────────────────────────────────────────────────────────────────────────────
const truckGroup = new THREE.Group();
truckGroup.rotation.y = Math.PI; // Truk menghadap keluar (arah jalan), siap mundur masuk ke dermaga
truckGroup.position.set(-5.0, 0, -68.0);

const tCab = new THREE.Mesh(new THREE.BoxGeometry(3.0, 3.2, 3.4), MAT.truckCabBlue);
tCab.position.set(0, 2.0, 3.6);
const tWind = new THREE.Mesh(new THREE.BoxGeometry(2.7, 1.2, 0.1), MAT.cockpitGlass);
tWind.position.set(0, 2.6, 5.32);
const tSpoiler = new THREE.Mesh(new THREE.BoxGeometry(2.9, 0.5, 2.0), MAT.truckCabBlue);
tSpoiler.position.set(0, 3.7, 3.4);
const tBumper = new THREE.Mesh(new THREE.BoxGeometry(3.1, 0.5, 0.3), MAT.metalSilver);
tBumper.position.set(0, 0.5, 5.35);

// Lampu Depan Menyala Terang (Headlight Cones & Spotlights)
const headlightL = new THREE.Mesh(new THREE.BoxGeometry(0.4, 0.3, 0.1), new THREE.MeshBasicMaterial({ color: 0xFEF08A }));
headlightL.position.set(-1.1, 0.8, 5.36);
const headlightR = new THREE.Mesh(new THREE.BoxGeometry(0.4, 0.3, 0.1), new THREE.MeshBasicMaterial({ color: 0xFEF08A }));
headlightR.position.set(1.1, 0.8, 5.36);

const truckSpotL = new THREE.SpotLight(0xFEF08A, 3.2, 45, Math.PI / 5, 0.4);
truckSpotL.position.set(-1.1, 0.8, 5.4);
truckSpotL.target.position.set(-1.1, 0.0, 18.0);
const truckSpotR = new THREE.SpotLight(0xFEF08A, 3.2, 45, Math.PI / 5, 0.4);
truckSpotR.position.set(1.1, 0.8, 5.4);
truckSpotR.target.position.set(1.1, 0.0, 18.0);
truckGroup.add(truckSpotL, truckSpotL.target, truckSpotR, truckSpotR.target);

// Trailer Kontainer Truk
const tTrailer = new THREE.Mesh(new THREE.BoxGeometry(3.2, 3.6, 8.0), new THREE.MeshStandardMaterial({ color: 0xF8FAFC, roughness: 0.4 }));
tTrailer.position.set(0, 2.3, -2.4);
const tStripe = new THREE.Mesh(new THREE.BoxGeometry(3.25, 0.6, 7.2), MAT.injourneyTeal);
tStripe.position.set(0, 2.3, -2.4);

// Pintu Belakang Trailer Truk yang Berengsel Asli di Sisi Kiri & Kanan (Z = -6.4)
const tDoorL = new THREE.Group();
tDoorL.position.set(-1.58, 2.3, -6.4);
const leafL = new THREE.Mesh(new THREE.BoxGeometry(1.54, 3.4, 0.08), MAT.metalDark);
leafL.position.set(0.77, 0, 0);
tDoorL.add(leafL);

const tDoorR = new THREE.Group();
tDoorR.position.set(1.58, 2.3, -6.4);
const leafR = new THREE.Mesh(new THREE.BoxGeometry(1.54, 3.4, 0.08), MAT.metalDark);
leafR.position.set(-0.77, 0, 0);
tDoorR.add(leafR);

// Lampu Belakang Trailer (Lampu Rem Merah & Lampu Mundur Putih Terang)
const reverseLightMat = new THREE.MeshBasicMaterial({ color: 0x334155 });
const reverseSpot = new THREE.SpotLight(0xFFFFFF, 0, 28, Math.PI / 4, 0.5);
reverseSpot.position.set(0, 1.2, -6.5);
reverseSpot.target.position.set(0, 0.0, -25.0);
truckGroup.add(reverseSpot, reverseSpot.target);

[-1.42, 1.42].forEach(tx => {
    const bLight = new THREE.Mesh(new THREE.BoxGeometry(0.24, 0.35, 0.06), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
    bLight.position.set(tx, 0.7, -6.43);
    const rLight = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.25, 0.06), reverseLightMat);
    rLight.position.set(tx - Math.sign(tx) * 0.26, 0.7, -6.43);
    truckGroup.add(bLight, rLight);
});

// 6 Roda Truk Berputar
const truckWheels = [];
const wheelGeo = new THREE.CylinderGeometry(0.5, 0.5, 0.35, 16);
wheelGeo.rotateZ(Math.PI / 2);
const wheelLocs = [
    [-1.6, 0.5, 3.6], [1.6, 0.5, 3.6],
    [-1.6, 0.5, -1.8], [1.6, 0.5, -1.8],
    [-1.6, 0.5, -4.2], [1.6, 0.5, -4.2]
];
wheelLocs.forEach(([wx, wy, wz]) => {
    const wheel = new THREE.Mesh(wheelGeo, MAT.truckTire);
    wheel.position.set(wx, wy, wz);
    truckGroup.add(wheel);
    truckWheels.push(wheel);
});

// Palet Kargo Bawaan di Dalam Bak Truk (Siap Diturunkan dari Belakang)
const truckBedPallet = new THREE.Mesh(new THREE.BoxGeometry(1.8, 0.16, 1.8), MAT.palletWood);
truckBedPallet.position.set(0, 0.7, -2.4);
const truckBedBox = new THREE.Mesh(new THREE.BoxGeometry(1.5, 1.1, 1.5), MAT.cardboardBox);
truckBedBox.position.set(0, 1.33, -2.4);

truckGroup.add(tCab, tWind, tSpoiler, tBumper, headlightL, headlightR, tTrailer, tStripe, tDoorL, tDoorR, truckBedPallet, truckBedBox);
scene.add(truckGroup);

// ─────────────────────────────────────────────────────────────────────────────
// ACTIVE CARGO UNIT (PALLET KAYU & KONTAINER ULD AKE KONTUR PESAWAT)
// ─────────────────────────────────────────────────────────────────────────────
const activeCargoGroup = new THREE.Group();
activeCargoGroup.position.set(-5.0, 0.65, -39.75);
activeCargoGroup.visible = false;

// 1. Representasi Palet Kayu (Tahap Awal & Karantina)
const palWoodGroup = new THREE.Group();
const pWood = new THREE.Mesh(new THREE.BoxGeometry(1.8, 0.16, 1.8), MAT.palletWood);
pWood.position.y = 0.08;
const cBox1 = new THREE.Mesh(new THREE.BoxGeometry(1.4, 0.9, 1.4), MAT.cardboardBox);
cBox1.position.y = 0.61;
const cTag = new THREE.Mesh(new THREE.PlaneGeometry(0.4, 0.3), new THREE.MeshBasicMaterial({ color: 0x0CA1AF }));
cTag.position.set(0, 0.7, 0.71);
palWoodGroup.add(pWood, cBox1, cTag);

// 2. Representasi Kontainer Pesawat ULD AKE (Kontur Chamfer 45 Derajat IATA Nyata)
const palUldGroup = new THREE.Group();
palUldGroup.visible = false;

const akeShape = new THREE.Shape();
akeShape.moveTo(-0.95, 0.0);
akeShape.lineTo(0.55, 0.0);
akeShape.lineTo(0.95, 0.40); // Chamfer khas palka pesawat
akeShape.lineTo(0.95, 1.60);
akeShape.lineTo(-0.95, 1.60);
akeShape.closePath();

const akeExtrude = new THREE.ExtrudeGeometry(akeShape, { steps: 1, depth: 1.50, bevelEnabled: true, bevelThickness: 0.02, bevelSize: 0.02 });
const akeMesh = new THREE.Mesh(akeExtrude, MAT.uldAluminum);
akeMesh.position.set(0, 0.08, -0.75);

const uldCurtain = new THREE.Mesh(new THREE.PlaneGeometry(1.6, 1.45), MAT.uldCanvasBlue);
uldCurtain.position.set(0, 0.85, 0.77);
const uldBadge = makeLabelSprite("ULD AKE-GA02", "350 KG • IATA PASS", "#0CA1AF");
uldBadge.scale.set(3.6, 1.0, 1);
uldBadge.position.set(0, 2.3, 0);

palUldGroup.add(akeMesh, uldCurtain, uldBadge);
activeCargoGroup.add(palWoodGroup, palUldGroup);
scene.add(activeCargoGroup);

// ─────────────────────────────────────────────────────────────────────────────
// WAYPOINTS KOORDINAT PROSES
// ─────────────────────────────────────────────────────────────────────────────
const WAYPOINTS = {
    truckStart: new THREE.Vector3(-5.0, 0, -68.0),
    truckDock:  new THREE.Vector3(-5.0, 0, -42.15),
    
    cargoInTrailer: new THREE.Vector3(-5.0, 0.65, -39.75),
    cargoUnload:    new THREE.Vector3(-5.0, 0.65, -33.5),
    cargoRfid:      new THREE.Vector3(-5.0, 0.65, -27.0),
    cargoXrayIn:    new THREE.Vector3(-5.0, 0.65, -21.0),
    cargoXrayOut:   new THREE.Vector3(-5.0, 0.65, -16.5),
    
    cargoQuarantineTable: new THREE.Vector3(24.5, 1.05, -16.5),
    cargoTurnCorner:    new THREE.Vector3(-5.0, 0.65, 8.0),
    cargoStorageCenter: new THREE.Vector3(0.0, 0.65, 8.0),
    cargoScalePlatform: new THREE.Vector3(35.0, 0.65, 8.0),
    cargoHighLoaderUp: new THREE.Vector3(63.0, 3.85, 8.0),
    cargoPlaneHold: new THREE.Vector3(74.0, 3.85, 8.0)
};

// ─────────────────────────────────────────────────────────────────────────────
// EKSEKUSI SIMULASI LIVE
// ─────────────────────────────────────────────────────────────────────────────
function startLiveSimulation() {
    if (!activeCargoData.name) {
        activeCargoData.name = <?= json_encode($initialCargo['description'] ?? 'Vaksin & Peralatan Medis Biofarma') ?>;
        activeCargoData.commodity = <?= json_encode($initialCargo['commodity_type'] ?? 'PHARMA') ?>;
        activeCargoData.qty = <?= (int)($initialCargo['quantity'] ?? 48) ?>;
        activeCargoData.weight = <?= (float)($initialCargo['declared_weight_kg'] ?? 350.0) ?>;
        activeCargoData.maxWeight = 1588.0;
        activeCargoData.xrayResult = <?= json_encode($initialCargo['xray_result'] ?? 'CLEARED') ?>;
        activeCargoData.truckPlate = <?= json_encode($initialCargo['truck_plate'] ?? 'B 9482 KXA') ?>;
        activeCargoData.driver = <?= json_encode($initialCargo['driver_name'] ?? 'Suryadi Pratama') ?>;
        activeCargoData.awb = <?= json_encode($initialCargo['awb_number'] ?? '126-88492019') ?>;
    }

    // Update Top FIDS Billboard
    document.getElementById('hud-awb-code').textContent = `GA-777F • ${activeCargoData.awb}`;
    document.getElementById('hud-cargo-title').textContent = `${activeCargoData.name} (${activeCargoData.weight} kg)`;
    document.getElementById('telemetry-scale').textContent = `${activeCargoData.weight} kg`;
    const telemX = document.getElementById('telemetry-xray');
    if (telemX) {
        telemX.textContent = activeCargoData.xrayResult;
        telemX.className = activeCargoData.xrayResult === 'SUSPECT' ? 'font-black text-rose-400 font-mono text-xs' : 'font-black text-emerald-400 font-mono text-xs';
    }

    // Warna Boks
    if (activeCargoData.xrayResult === 'SUSPECT') {
        cBox1.material = MAT.cardboardBoxSuspect;
        cTag.material.color.setHex(0xEF4444);
    } else {
        cBox1.material = MAT.cardboardBox;
        cTag.material.color.setHex(0x0CA1AF);
    }

    resetLiveSimulation(false);

    simActive = true;
    isSimPaused = false;
    simPhase = 1;
    simProgress = 0;

    saveToDatabaseApi();

    const bStart = document.getElementById('btn-start-text');
    if (bStart) bStart.textContent = 'ULANGI SIMULASI LIVE';
    const bPause = document.getElementById('btn-pause');
    if (bPause) bPause.innerHTML = '<i class="fa-solid fa-pause text-[10px]"></i> <span>Pause</span>';
    const pIcon = document.getElementById('scrub-play-icon');
    if (pIcon) pIcon.className = 'fa-solid fa-pause text-[11px]';

    addSimLog(`🚀 [START] Memulai pengiriman kargo "${activeCargoData.name}" dari truk ${activeCargoData.truckPlate}.`);
    updateStepperUI(1);
    showMilestone('SIMULASI DIMULAI', `Truk ${activeCargoData.truckPlate} melaju ke Terminal Kargo Bandara.`);
}

function saveToDatabaseApi() {
    fetch('api/cargo_input.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            description: activeCargoData.name,
            commodity_type: activeCargoData.commodity,
            declared_weight_kg: activeCargoData.weight,
            quantity: activeCargoData.qty,
            xray_result: activeCargoData.xrayResult,
            truck_plate: activeCargoData.truckPlate,
            driver_name: activeCargoData.driver
        })
    }).then(r => r.json()).then(res => {
        if (res.success) {
            addSimLog(`💾 [DATABASE] Kargo tercatat di sistem: ${res.cargo_id} (SSCC: ${res.sscc}).`);
        }
    }).catch(err => console.warn('API sync background:', err));
}

function resetLiveSimulation(fullReset = true) {
    simActive = false;
    isSimPaused = false;
    simPhase = 0;
    simProgress = 0;

    // Reset Truk (Posisi awal siap mundur ke dermaga)
    truckGroup.rotation.y = Math.PI;
    truckGroup.position.set(-5.0, 0, -68.0);
    truckBedPallet.visible = true;
    truckBedBox.visible = true;
    tDoorL.rotation.y = 0;
    tDoorR.rotation.y = 0;
    if (typeof reverseSpot !== 'undefined') reverseSpot.intensity = 0;
    if (typeof reverseLightMat !== 'undefined') reverseLightMat.color.setHex(0x334155);

    // Reset Kargo (Berada di dalam bak truk)
    activeCargoGroup.visible = false;
    activeCargoGroup.position.copy(WAYPOINTS.cargoInTrailer);
    palWoodGroup.visible = true;
    palUldGroup.visible = false;

    // Reset Diverter Arm
    divArm.position.x = 0.6;

    // Reset Timbangan
    updateScaleDisplayTexture(activeCargoData.weight, activeCargoData.maxWeight, 'IDLE');

    // Reset Pintu Shutter & High-Loader
    airsideShutter.position.y = 2.1;
    if (hlElevatorGroup) hlElevatorGroup.position.y = 0.55;
    if (updateHighLoaderScissors) updateHighLoaderScissors(0.55);
    if (planeCargoDoorHatch) planeCargoDoorHatch.rotation.z = (fullReset ? 0 : -Math.PI * 0.42);

    // Reset Lights
    redAlarmStrobe.intensity = 0;
    xrayScanLight.intensity = 0;
    xrBeaconMat.color.setHex(0x10B981);
    document.getElementById('telemetry-door').textContent = 'STANDBY';
    document.getElementById('telemetry-elevator').textContent = '0.55 m';

    if (fullReset) {
        updateStepperUI(1);
        const progEl = document.getElementById('sim-progress-pct');
        if (progEl) progEl.textContent = '0%';
        const bStart = document.getElementById('btn-start-text');
        if (bStart) bStart.textContent = 'JALANKAN SIMULASI LIVE';
        const pIcon = document.getElementById('scrub-play-icon');
        if (pIcon) pIcon.className = 'fa-solid fa-play text-[11px]';
        document.getElementById('hud-stage-name').textContent = 'STANDBY';
        document.getElementById('hud-cargo-title').textContent = 'Siap menerima instruksi jalankan simulasi.';
        addSimLog('🔄 Seluruh posisi peralatan terminal di-reset ke awal.');
    }
    if (typeof updateTimelineHUD === 'function') updateTimelineHUD();
}

// ─────────────────────────────────────────────────────────────────────────────
// KAMERA PRESETS DENGAN TRANSISI HALUS (SMOOTH LERP)
// ─────────────────────────────────────────────────────────────────────────────
const targetCamPos = new THREE.Vector3(-38, 32, -45);
const targetCamLook = new THREE.Vector3(12, 3, -2);

function setCameraViewUI(mode) {
    ['cam-follow', 'cam-iso', 'cam-dock', 'cam-xray', 'cam-scale', 'cam-plane'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('active');
    });
    const cur = document.getElementById('cam-' + mode);
    if (cur) cur.classList.add('active');
}

function setCameraView(mode) {
    simCameraMode = mode;
    setCameraViewUI(mode);

    if (mode === 'follow' || mode === 'free') {
        isCamTransitioning = false;
        return;
    }

    if (mode === 'iso') {
        smoothCam(-38, 32, -45, 12, 3, -2);
    } else if (mode === 'dock') {
        smoothCam(-22, 14, -50, -5, 2.5, -30);
    } else if (mode === 'xray') {
        smoothCam(-14, 12, -28, 5, 2, -18);
    } else if (mode === 'scale') {
        smoothCam(20, 12, -6, 35, 2, 8);
    } else if (mode === 'plane') {
        smoothCam(46, 15, -12, 74, 4, 8);
    }
}

function resetCameraAngle() {
    setCameraView('iso');
}

function smoothCam(px, py, pz, tx, ty, tz) {
    targetCamPos.set(px, py, pz);
    targetCamLook.set(tx, ty, tz);
    isCamTransitioning = true;
}

function jumpToProcessStep(stepNum) {
    if (!activeCargoData.name) {
        activeCargoData.name = <?= json_encode($initialCargo['description'] ?? 'Vaksin & Peralatan Medis Biofarma') ?>;
        activeCargoData.commodity = <?= json_encode($initialCargo['commodity_type'] ?? 'PHARMA') ?>;
        activeCargoData.qty = <?= (int)($initialCargo['quantity'] ?? 48) ?>;
        activeCargoData.weight = <?= (float)($initialCargo['declared_weight_kg'] ?? 350.0) ?>;
        activeCargoData.maxWeight = 1588.0;
        activeCargoData.xrayResult = <?= json_encode($initialCargo['xray_result'] ?? 'CLEARED') ?>;
        activeCargoData.truckPlate = <?= json_encode($initialCargo['truck_plate'] ?? 'B 9482 KXA') ?>;
        activeCargoData.driver = <?= json_encode($initialCargo['driver_name'] ?? 'Suryadi Pratama') ?>;
        activeCargoData.awb = <?= json_encode($initialCargo['awb_number'] ?? '126-88492019') ?>;
    }

    if (activeCargoData.xrayResult === 'SUSPECT') {
        cBox1.material = MAT.cardboardBoxSuspect;
        cTag.material.color.setHex(0xEF4444);
    } else {
        cBox1.material = MAT.cardboardBox;
        cTag.material.color.setHex(0x0CA1AF);
    }

    simActive = true;
    isSimPaused = false;
    simPhase = stepNum;
    simProgress = 0;

    // Reset base elements
    redAlarmStrobe.intensity = 0;
    xrayScanLight.intensity = 0;
    divArm.position.x = 0.6;
    if (hlElevatorGroup) hlElevatorGroup.position.y = 0.55;
    if (updateHighLoaderScissors) updateHighLoaderScissors(0.55);
    if (planeCargoDoorHatch) planeCargoDoorHatch.rotation.z = 0;
    airsideShutter.position.y = 2.1;

    if (stepNum === 1) {
        truckGroup.rotation.y = Math.PI;
        truckGroup.position.set(-5.0, 0, -68.0);
        truckBedPallet.visible = true;
        truckBedBox.visible = true;
        tDoorL.rotation.y = 0;
        tDoorR.rotation.y = 0;
        activeCargoGroup.visible = false;
        setCameraView('dock');
        addSimLog(`⏩ [JUMP] Melompat ke Tahap 1: Truk Mundur Merapat ke Dermaga.`);
    } else if (stepNum === 2) {
        truckGroup.rotation.y = Math.PI;
        truckGroup.position.set(-5.0, 0, -42.15);
        truckBedPallet.visible = true;
        truckBedBox.visible = true;
        tDoorL.rotation.y = -Math.PI * 0.45;
        tDoorR.rotation.y = Math.PI * 0.45;
        activeCargoGroup.visible = false;
        setCameraView('dock');
        addSimLog(`⏩ [JUMP] Melompat ke Tahap 2: Docking Bay 2 & Scan RFID.`);
    } else if (stepNum === 3) {
        truckGroup.rotation.y = Math.PI;
        truckGroup.position.set(-5.0, 0, -42.15);
        truckBedPallet.visible = false;
        truckBedBox.visible = false;
        tDoorL.rotation.y = -Math.PI * 0.65;
        tDoorR.rotation.y = Math.PI * 0.65;
        activeCargoGroup.visible = true;
        activeCargoGroup.position.set(-5.0, 0.65, -33.5);
        palWoodGroup.visible = true;
        palUldGroup.visible = false;
        setCameraView('dock');
        addSimLog(`⏩ [JUMP] Melompat ke Tahap 3: Pemeriksaan X-Ray AVSEC.`);
    } else if (stepNum === 4) {
        truckGroup.rotation.y = Math.PI;
        truckGroup.position.set(-5.0, 0, -42.15);
        truckBedPallet.visible = false;
        truckBedBox.visible = false;
        tDoorL.rotation.y = -Math.PI * 0.65;
        tDoorR.rotation.y = Math.PI * 0.65;
        activeCargoGroup.visible = true;
        activeCargoGroup.position.set(-5.0, 0.65, -16.5);
        palWoodGroup.visible = true;
        palUldGroup.visible = false;
        setCameraView('xray');
        if (activeCargoData.xrayResult === 'SUSPECT') {
            updateStepperUI(4, 4);
            addSimLog(`⏩ [JUMP] Melompat ke Tahap 4: Pengalihan ke Ruang Karantina.`);
        } else {
            updateStepperUI(4);
            addSimLog(`⏩ [JUMP] Melompat ke Tahap 4: Central Storage & Perakitan ULD.`);
        }
    } else if (stepNum === 5) {
        truckGroup.rotation.y = Math.PI;
        truckGroup.position.set(-5.0, 0, -42.15);
        truckBedPallet.visible = false;
        truckBedBox.visible = false;
        tDoorL.rotation.y = -Math.PI * 0.65;
        tDoorR.rotation.y = Math.PI * 0.65;
        activeCargoGroup.visible = true;
        activeCargoGroup.position.set(0.0, 0.65, 8.0);
        palWoodGroup.visible = false;
        palUldGroup.visible = true;
        updateScaleDisplayTexture(0, activeCargoData.maxWeight, 'WEIGHING');
        setCameraView('scale');
        addSimLog(`⏩ [JUMP] Melompat ke Tahap 5: Penimbangan ULD Lantai 15T.`);
    } else if (stepNum === 6) {
        truckGroup.rotation.y = Math.PI;
        truckGroup.position.set(-5.0, 0, -42.15);
        truckBedPallet.visible = false;
        truckBedBox.visible = false;
        tDoorL.rotation.y = -Math.PI * 0.65;
        tDoorR.rotation.y = Math.PI * 0.65;
        activeCargoGroup.visible = true;
        activeCargoGroup.position.set(35.0, 0.65, 8.0);
        palWoodGroup.visible = false;
        palUldGroup.visible = true;
        airsideShutter.position.y = 5.2;
        updateScaleDisplayTexture(activeCargoData.weight, activeCargoData.maxWeight, 'PASS');
        if (planeCargoDoorHatch) planeCargoDoorHatch.rotation.z = -Math.PI * 0.42;
        setCameraView('plane');
        addSimLog(`⏩ [JUMP] Melompat ke Tahap 6: Elevasi High-Loader & Muat Boeing 777F.`);
    }
    updateStepperUI(stepNum);
    updateTimelineHUD();
    const bStart = document.getElementById('btn-start-text');
    if (bStart) bStart.textContent = 'ULANGI SIMULASI LIVE';
    const bPause = document.getElementById('btn-pause');
    if (bPause) bPause.innerHTML = '<i class="fa-solid fa-pause text-[10px]"></i> <span>Pause</span>';
    const pIcon = document.getElementById('scrub-play-icon');
    if (pIcon) pIcon.className = 'fa-solid fa-pause text-[11px]';
}

function onTimelineScrubClick(event, containerEl) {
    const rect = containerEl.getBoundingClientRect();
    const clickX = event.clientX - rect.left;
    const ratio = Math.max(0, Math.min(1, clickX / rect.width));
    const targetStage = Math.min(6, Math.max(1, Math.floor(ratio * 6) + 1));
    jumpToProcessStep(targetStage);
}

function updateTimelineHUD() {
    const progressFill = document.getElementById('timeline-progress-fill');
    const scrubPct = document.getElementById('scrub-pct-label');
    const scrubStage = document.getElementById('scrub-stage-label');
    const playIcon = document.getElementById('scrub-play-icon');

    if (!simActive && simPhase === 0) {
        if (progressFill) progressFill.style.width = '0%';
        if (scrubPct) scrubPct.textContent = '0%';
        if (scrubStage) scrubStage.textContent = 'Standby - Siap Mulai';
        if (playIcon) playIcon.className = 'fa-solid fa-play text-[11px]';
        return;
    }

    const stageNames = [
        '',
        'Tahap 1: Truk Mundur ke Dermaga',
        'Tahap 2: Docking Bay 2 & Scan RFID',
        'Tahap 3: Bongkar Kargo & X-Ray AVSEC',
        activeCargoData.xrayResult === 'SUSPECT' ? 'Tahap 3B: Isolasi Karantina' : 'Tahap 4: Storage & Build-up ULD',
        'Tahap 5: Penimbangan Smart Scale 15T',
        'Tahap 6: Elevasi High-Loader & Muat 777F'
    ];

    if (scrubStage) scrubStage.textContent = stageNames[simPhase] || 'Proses Terminal';

    const basePct = ((simPhase - 1) / 6) * 100;
    const stepPct = (Math.min(1.0, simProgress) / 6) * 100;
    const totalPct = Math.min(100, Math.round(basePct + stepPct));

    if (progressFill) progressFill.style.width = totalPct + '%';
    if (scrubPct) scrubPct.textContent = totalPct + '%';
    const mainPct = document.getElementById('sim-progress-pct');
    if (mainPct) mainPct.textContent = totalPct + '%';

    if (playIcon) {
        if (isSimPaused || !simActive) {
            playIcon.className = 'fa-solid fa-play text-[11px]';
        } else {
            playIcon.className = 'fa-solid fa-pause text-[11px]';
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// MAIN ANIMATION LOOP
// ─────────────────────────────────────────────────────────────────────────────
const clock = new THREE.Clock();

function animate() {
    requestAnimationFrame(animate);
    const delta = clock.getDelta();
    const dt = Math.min(delta, 0.1) * simSpeed;

    if (simActive && !isSimPaused) {
        updateSimulationPipeline(dt);
    }

    // Kamera Lerp Halus (Hanya saat transisi klik tombol preset, tidak melawan mouse drag user)
    if (isCamTransitioning && !isUserInteracting) {
        camera.position.lerp(targetCamPos, dt * 4.2);
        controls.target.lerp(targetCamLook, dt * 4.2);
        // Selesai transisi saat sudah sangat dekat, berikan kendali mouse 100% bebas kepada user
        if (camera.position.distanceTo(targetCamPos) < 0.25 && controls.target.distanceTo(targetCamLook) < 0.25) {
            isCamTransitioning = false;
        }
    }

    // Putar Roller Konveyor saat kargo sedang bergerak di atasnya
    if (simActive && !isSimPaused && simPhase >= 3 && simPhase <= 6) {
        animatedRollers.forEach(r => {
            if (r.axis === 'x') {
                r.mesh.rotation.x += dt * 6.5 * (r.dir || 1);
            } else if (r.axis === 'z') {
                r.mesh.rotation.z += dt * 6.5 * (r.dir || 1);
            }
        });
    }

    // Putar Sirine Karantina Saat Bahaya
    if (sirenRotMesh && redAlarmStrobe.intensity > 0) {
        sirenRotMesh.rotation.y += dt * 12.0;
    }

    // Putar Kipas Industri Plafon HVLS (High-Volume Low-Speed)
    if (hvlsFans && hvlsFans.length) {
        hvlsFans.forEach(fan => { fan.rotation.y += dt * 1.8; });
    }

    // Putar Kipas Mesin GE90 Turbofan Boeing 777F
    if (planeFanBlades && planeFanBlades.length) {
        planeFanBlades.forEach(f => { f.rotation.z += dt * 15.0; });
    }

    // Animasi Lampu Strobo & Beacon Pesawat Boeing 777F
    if (planeAviationLights) {
        const timeNow = Date.now();
        const bcnOn = (Math.sin(timeNow * 0.005) > 0.3);
        const bcnColor = bcnOn ? 0xFF2222 : 0x440000;
        planeAviationLights.bcnTop.material.color.setHex(bcnColor);
        planeAviationLights.bcnBtm.material.color.setHex(bcnColor);

        const strobeCycle = timeNow % 1400;
        const strobeOn = (strobeCycle < 60 || (strobeCycle > 140 && strobeCycle < 200));
        const strobeColor = strobeOn ? 0xFFFFFF : 0x222222;
        planeAviationLights.strobeWingL.material.color.setHex(strobeColor);
        planeAviationLights.strobeWingR.material.color.setHex(strobeColor);
    }

    // Kibaran Bendera Realistis di Gerbang Utama (Wind Flutter Animation - Dual Sided)
    if (typeof animatedFlags !== 'undefined' && animatedFlags.length) {
        const flagTime = Date.now() * 0.0045;
        animatedFlags.forEach(f => {
            const posF = f.front.geometry.attributes.position;
            const posB = f.back.geometry.attributes.position;
            const w = f.width;
            for (let i = 0; i < posF.count; i++) {
                const u = posF.getX(i) / w; // 0 di tiang, 1 di ujung bebas bendera
                const ripple = Math.sin(u * 5.2 - flagTime * 3.8 + f.phase) * (0.13 * u);
                posF.setZ(i, ripple);
                posB.setZ(i, ripple);
            }
            f.front.geometry.computeVertexNormals();
            f.back.geometry.computeVertexNormals();
            f.front.geometry.attributes.position.needsUpdate = true;
            f.back.geometry.attributes.position.needsUpdate = true;
        });
    }

    controls.update();
    renderer.render(scene, camera);
}
animate();

// Easing Helpers for Cinematic Motion Kinematics
function easeInOutCubic(x) {
    return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2;
}
function easeOutQuad(x) {
    return 1 - (1 - x) * (1 - x);
}
function easeInOutQuad(x) {
    return x < 0.5 ? 2 * x * x : 1 - Math.pow(-2 * x + 2, 2) / 2;
}

function updateSimulationPipeline(dt) {
    if (typeof updateTimelineHUD === 'function') updateTimelineHUD();

    // ═════════════════════════════════════════════════════════════════════════
    // FASE 1: TRUK MUNDUR MERAPAT KE DERMAGA DOCK BAY 2
    // ═════════════════════════════════════════════════════════════════════════
    if (simPhase === 1) {
        document.getElementById('hud-stage-name').innerHTML = 'TAHAP 1: TRUK MUNDUR KE DERMAGA <span class="text-[8px] px-1.5 py-0.5 rounded bg-teal-500/30 text-teal-200 font-extrabold">REVERSING</span>';
        simProgress += dt * 0.088;

        const easedT = easeInOutCubic(Math.min(1.0, simProgress));
        // Truk mundur dari Z = -68.0 ke Z = -42.15 (posisi pantat trailer pas menyentuh karet bumper dok di Z = -35.75)
        const curZ = THREE.MathUtils.lerp(-68.0, -42.15, easedT);
        truckGroup.position.z = curZ;

        // Lampu mundur putih menyala terang & berkedip saat mundur
        if (typeof reverseSpot !== 'undefined') reverseSpot.intensity = 2.8;
        if (typeof reverseLightMat !== 'undefined') {
            reverseLightMat.color.setHex((Math.sin(Date.now() * 0.015) > 0) ? 0xFFFFFF : 0xAAAAAA);
        }

        // Roda berputar mundur saat reversing
        const currentSpeed = (1.0 - Math.abs(easedT - 0.5) * 1.4);
        truckWheels.forEach(w => { w.rotation.x -= dt * 4.5 * Math.max(0.25, currentSpeed); });

        if (simCameraMode === 'follow' && !isUserInteracting) {
            camera.position.lerp(new THREE.Vector3(-14.0, 7.5, curZ - 10.0), dt * 2.8);
            controls.target.lerp(new THREE.Vector3(-5.0, 2.2, curZ + 5.0), dt * 2.8);
        }

        if (simProgress >= 1.0) {
            if (typeof reverseSpot !== 'undefined') reverseSpot.intensity = 0;
            if (typeof reverseLightMat !== 'undefined') reverseLightMat.color.setHex(0x334155);
            simPhase = 2;
            simProgress = 0;
            updateStepperUI(2);
            playSound('beep');
            addSimLog(`📡 [DOCKING BAY 2] Truk ${activeCargoData.truckPlate} berhasil mundur & merapat di Bay 2. Membaca Tag GS1 SSCC-18...`);
            showMilestone('DOCKING BAY 2', `Truk ${activeCargoData.truckPlate} merapat mundur di Bay 2. Membaca RFID Manifest.`);
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FASE 2: SCAN RFID & MERAPAT DI DOCK BAY 2 (BUKA PINTU BELAKANG)
    // ═════════════════════════════════════════════════════════════════════════
    else if (simPhase === 2) {
        document.getElementById('hud-stage-name').innerHTML = 'TAHAP 2: VALIDASI RFID & BUKA PINTU DOK <span class="text-[8px] px-1.5 py-0.5 rounded bg-emerald-500/30 text-emerald-200 font-extrabold">VERIFIED</span>';
        simProgress += dt * 0.085;
        truckGroup.position.z = -42.15;

        if (simProgress < 0.40) {
            // Pulse wave pemindai nirkabel RFID dermaga
            const waveT = simProgress / 0.40;
            rfidWaveMesh.position.set(-5.0, 0.05, -27.0);
            rfidWaveMesh.material.opacity = Math.sin(waveT * Math.PI * 4) * 0.85;
            rfidWaveMesh.scale.setScalar(1 + (waveT * 3.5) % 2.5);
        } else {
            rfidWaveMesh.material.opacity = 0;
        }

        // Pintu belakang trailer truk terbuka mulus ke samping luar (120 derajat)
        const doorT = easeOutQuad(Math.min(1.0, simProgress * 1.4));
        tDoorL.rotation.y = THREE.MathUtils.lerp(0, -Math.PI * 0.65, doorT);
        tDoorR.rotation.y = THREE.MathUtils.lerp(0, Math.PI * 0.65, doorT);

        if (simCameraMode === 'follow' && !isUserInteracting) {
            camera.position.lerp(new THREE.Vector3(7.0, 8.5, -30.0), dt * 2.8);
            controls.target.lerp(new THREE.Vector3(-5.0, 2.5, -36.0), dt * 2.8);
        }

        if (simProgress >= 1.0) {
            tDoorL.rotation.y = -Math.PI * 0.65;
            tDoorR.rotation.y = Math.PI * 0.65;

            truckBedPallet.visible = false;
            truckBedBox.visible = false;
            activeCargoGroup.visible = true;
            activeCargoGroup.position.set(-5.0, 0.65, -39.75); // Kargo mulai dari dalam bak trailer!

            simPhase = 3;
            simProgress = 0;
            updateStepperUI(3);
            addSimLog(`📦 [UNLOAD] Pintu trailer dibuka. Kargo dikeluarkan dari bak truk ke feeder konveyor Bay 2.`);
            showMilestone('BONGKAR KARGO', `Pintu trailer terbuka. Kargo meluncur dari bak truk ke konveyor.`);
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FASE 3: PEMERIKSAAN DUAL-VIEW X-RAY SCREENING (LINE 2)
    // ═════════════════════════════════════════════════════════════════════════
    else if (simPhase === 3) {
        document.getElementById('hud-stage-name').innerHTML = 'TAHAP 3: PEMERIKSAAN X-RAY AVSEC <span class="text-[8px] px-1.5 py-0.5 rounded bg-amber-500/30 text-amber-200 font-extrabold">SCANNING</span>';
        simProgress += dt * 0.082;

        // Kargo meluncur keluar dari dalam bak truk (-39.75), melewati pintu belakang (-35.75),
        // melintasi feeder conveyor (-33.5), melintasi portal RFID (-27.0),
        // masuk mesin X-Ray (-18.5) hingga keluar di (-16.5)!
        const easedZ = THREE.MathUtils.lerp(-39.75, -16.5, easeInOutQuad(Math.min(1.0, simProgress)));
        activeCargoGroup.position.set(-5.0, 0.65, easedZ);

        // AIDC RFID Scanner Beam saat kargo melintasi Z = -28.5 s.d. -25.5
        if (easedZ >= -28.5 && easedZ <= -25.5) {
            rfidWaveMesh.position.set(-5.0, 0.05, -27.0);
            rfidWaveMesh.material.opacity = 0.8 + Math.sin(Date.now() * 0.03) * 0.2;
        } else if (easedZ < -28.5) {
            rfidWaveMesh.material.opacity = 0;
        }

        // Laser scan beam dan lampu terowongan pemindai X-Ray
        if (easedZ >= -21.5 && easedZ <= -16.8) {
            xrayScanLight.intensity = 3.6 + Math.sin(Date.now() * 0.035) * 2.2;
            xrBeaconMat.color.setHex(activeCargoData.xrayResult === 'SUSPECT' ? 0xEF4444 : 0x10B981);
        } else {
            xrayScanLight.intensity = 0;
        }

        if (simCameraMode === 'follow' && !isUserInteracting) {
            camera.position.lerp(new THREE.Vector3(4.5, 6.2, easedZ + 5.5), dt * 2.8);
            controls.target.lerp(new THREE.Vector3(-5.0, 1.6, easedZ), dt * 2.8);
        }

        if (simProgress >= 1.0) {
            simPhase = 4;
            simProgress = 0;

            if (activeCargoData.xrayResult === 'SUSPECT') {
                updateStepperUI(4, 4);
                redAlarmStrobe.intensity = 6.0;
                addSimLog(`🚨 [X-RAY ALERT] Terdeteksi anomali berbahaya (${activeCargoData.name})! Diverter mengalihkan kargo ke Ruang Karantina!`, true);
                showMilestone('BAHAN BERBAHAYA TERDETEKSI', 'Anomali X-Ray! Diverter mendorong kargo ke Ruang Karantina (3B).', 'fa-triangle-exclamation', true);
            } else {
                updateStepperUI(4);
                playSound('pass');
                addSimLog(`✅ [X-RAY PASS] Kargo dinyatakan AMAN (CLEARED). Menerbitkan sertifikat CSD. Mengalir ke Central Storage.`);
                showMilestone('X-RAY CLEARED', 'Kargo aman dan diterbitkan CSD. Meluncur lurus ke Central Storage.');
            }
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FASE 4: PERCABANGAN (RUANG KARANTINA vs CENTRAL STORAGE)
    // ═════════════════════════════════════════════════════════════════════════
    else if (simPhase === 4) {
        if (activeCargoData.xrayResult === 'SUSPECT') {
            // ─── CABANG BERBAHAYA: MASUK RUANG KARANTINA & DITAHAN ───
            document.getElementById('hud-stage-name').innerHTML = 'TAHAP 3B: ISOLASI RUANG KARANTINA <span class="text-[8px] px-1.5 py-0.5 rounded bg-rose-500/30 text-rose-200 font-extrabold">HOLD</span>';
            simProgress += dt * 0.080;

            if (simProgress < 0.22) {
                const pushT = Math.sin((simProgress / 0.22) * Math.PI);
                divArm.position.x = 0.6 + pushT * 1.8;
                if (simProgress > 0.04 && simProgress < 0.08) playSound('hydraulic');
            } else {
                divArm.position.x = 0.6;
            }

            const curX = THREE.MathUtils.lerp(-5.0, 24.5, easeInOutCubic(Math.min(1.0, simProgress)));
            activeCargoGroup.position.set(curX, 0.65, -16.5);

            redAlarmStrobe.intensity = (Math.sin(Date.now() * 0.02) > 0) ? 6.5 : 0.8;

            if (simCameraMode === 'follow' && !isUserInteracting) {
                camera.position.lerp(new THREE.Vector3(curX - 7.0, 7.5, -7.5), dt * 2.8);
                controls.target.lerp(new THREE.Vector3(curX, 1.8, -16.5), dt * 2.8);
            }

            if (simProgress >= 1.0) {
                activeCargoGroup.position.copy(WAYPOINTS.cargoQuarantineTable);
                simActive = false;
                addSimLog(`🛑 [KARANTINA COMPLETE] Kargo ${activeCargoData.name} diisolasi di Ruang Karantina. DILARANG ke Pesawat!`, true);

                Swal.fire({
                    title: 'BARANG DITAHAN DI RUANG KARANTINA!',
                    html: `<div class="text-left text-sm space-y-2">
                        <p class="text-rose-500 font-bold">⚠️ STATUS: SUSPECT &amp; HOLD (LOCATION 3B)</p>
                        <p><strong>Nama Barang:</strong> ${activeCargoData.name}</p>
                        <p><strong>Hasil X-Ray:</strong> Terdeteksi bahan berbahaya / anomali kerapatan fisik.</p>
                        <p><strong>Protokol Lapangan:</strong> Kargo telah dialihkan oleh diverter arm ke Ruang Karantina untuk pemeriksaan sekunder ETD &amp; investigasi kepolisian. <em>Kargo dilarang keras dimuat ke pesawat!</em></p>
                    </div>`,
                    icon: 'error',
                    background: '#0D1C42',
                    color: '#FFFFFF',
                    confirmButtonColor: '#EF4444',
                    confirmButtonText: 'Tutup &amp; Evaluasi'
                });
            }
        } else {
            // ─── CABANG AMAN: CENTRAL STORAGE & PERAKITAN ULD ───
            document.getElementById('hud-stage-name').innerHTML = 'TAHAP 4: STORAGE &amp; PERAKITAN ULD <span class="text-[8px] px-1.5 py-0.5 rounded bg-emerald-500/30 text-emerald-200 font-extrabold">BUILD-UP</span>';
            simProgress += dt * 0.075;

            if (simProgress < 0.55) {
                // Meluncur 100% LURUS di atas konveyor utama dari X-Ray (-16.5) ke Corner Transfer Bed (8.0) di X = -5.0
                const subT = easeInOutQuad(simProgress / 0.55);
                activeCargoGroup.position.set(-5.0, 0.65, THREE.MathUtils.lerp(-16.5, 8.0, subT));
            } else if (simProgress < 0.78) {
                // Berbelok mulus di corner bed dan meluncur ke timur masuk meja ULD di (0.0, 8.0)
                const subT = easeInOutQuad((simProgress - 0.55) / 0.23);
                activeCargoGroup.position.set(THREE.MathUtils.lerp(-5.0, 0.0, subT), 0.65, 8.0);
            } else {
                // Di stasiun perakitan ULD (0.0, 8.0), kargo dirakit ke kontainer ULD AKE
                activeCargoGroup.position.set(0.0, 0.65, 8.0);
                const buildT = (simProgress - 0.78) / 0.22;
                if (buildT > 0.4) {
                    palWoodGroup.visible = false;
                    palUldGroup.visible = true;
                }
            }

            if (simCameraMode === 'follow' && !isUserInteracting) {
                camera.position.lerp(new THREE.Vector3(activeCargoGroup.position.x + 8.5, 8.5, activeCargoGroup.position.z + 9.5), dt * 2.8);
                controls.target.lerp(new THREE.Vector3(activeCargoGroup.position.x, 1.6, activeCargoGroup.position.z), dt * 2.8);
            }

            if (simProgress >= 1.0) {
                palWoodGroup.visible = false;
                palUldGroup.visible = true;
                simPhase = 5;
                simProgress = 0;
                updateStepperUI(5);
                addSimLog(`📦 [BUILD-UP] Kargo dirakit ke Kontainer ULD AKE sesuai Center of Gravity (CoG). Siap ditimbang.`);
                showMilestone('PERAKITAN ULD SELESAI', 'Kontainer ULD AKE siap meluncur ke Smart Scale 15T.');
            }
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FASE 5: PENIMBANGAN ULD LANTAI 15 TON (SMART FLOOR SCALE)
    // ═════════════════════════════════════════════════════════════════════════
    else if (simPhase === 5) {
        document.getElementById('hud-stage-name').innerHTML = 'TAHAP 5: PENIMBANGAN ULD 15T <span class="text-[8px] px-1.5 py-0.5 rounded bg-purple-500/30 text-purple-200 font-extrabold">WEIGHING</span>';
        simProgress += dt * 0.080;

        const curX = THREE.MathUtils.lerp(0.0, 35.0, easeInOutQuad(Math.min(1.0, simProgress)));
        activeCargoGroup.position.x = curX;
        activeCargoGroup.position.z = 8.0;

        // Penghitung berat aktif pada display digital timbangan
        if (curX > 18.0) {
            const countT = (curX - 18.0) / 17.0;
            const dynamicKg = THREE.MathUtils.lerp(0, activeCargoData.weight, Math.min(1.0, countT * 1.25));
            updateScaleDisplayTexture(dynamicKg, activeCargoData.maxWeight, 'WEIGHING');
            document.getElementById('telemetry-scale').textContent = dynamicKg.toFixed(1) + ' kg';
        }

        if (simCameraMode === 'follow' && !isUserInteracting) {
            camera.position.lerp(new THREE.Vector3(curX - 7.5, 7.5, 17.5), dt * 2.8);
            controls.target.lerp(new THREE.Vector3(curX, 1.8, 8.0), dt * 2.8);
        }

        if (simProgress >= 1.0) {
            const isOverweight = (activeCargoData.weight > activeCargoData.maxWeight);

            if (isOverweight) {
                updateScaleDisplayTexture(activeCargoData.weight, activeCargoData.maxWeight, 'OVERWEIGHT');
                updateStepperUI(5, 5);
                playSound('alert');
                simActive = false;
                addSimLog(`⛔ [SCALE REJECT] Berat ${activeCargoData.weight} kg melebihi kapasitas maksimum (${activeCargoData.maxWeight} kg)! TOLAK MUAT!`, true);

                Swal.fire({
                    title: 'TOLAK MUAT: KELEBIHAN BEBAN!',
                    html: `<div class="text-left text-sm space-y-2">
                        <p class="text-rose-500 font-bold">⚠️ STATUS: OVERWEIGHT REJECT (SMART SCALE 15T)</p>
                        <p><strong>Berat Terukur:</strong> <span class="text-rose-500 font-bold">${activeCargoData.weight} kg</span></p>
                        <p><strong>Batas Maksimum ULD:</strong> ${activeCargoData.maxWeight} kg</p>
                        <p><strong>Bahaya:</strong> Melebihi batas toleransi keselamatan Weight &amp; Balance pesawat. Gerbang airside dikunci, kontainer ditahan di stasiun timbangan.</p>
                    </div>`,
                    icon: 'warning',
                    background: '#0D1C42',
                    color: '#FFFFFF',
                    confirmButtonColor: '#EF4444',
                    confirmButtonText: 'Tutup'
                });
            } else {
                updateScaleDisplayTexture(activeCargoData.weight, activeCargoData.maxWeight, 'PASS');
                playSound('pass');
                airsideShutter.position.y = 5.2; // Buka pintu airside

                simPhase = 6;
                simProgress = 0;
                updateStepperUI(6);
                addSimLog(`✅ [SCALE PASS] Berat ${activeCargoData.weight} kg aman (Toleransi OK). Gerbang Airside dibuka!`);
                showMilestone('TIMBANGAN PASS', `Berat ${activeCargoData.weight} kg OK. Gerbang Airside terbuka!`);
            }
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FASE 6: ELEVASI HIGH-LOADER & PEMUATAN KE BOEING 777F
    // ═════════════════════════════════════════════════════════════════════════
    else if (simPhase === 6) {
        document.getElementById('hud-stage-name').innerHTML = 'TAHAP 6: ELEVASI HIGH-LOADER &amp; MUAT 777F <span class="text-[8px] px-1.5 py-0.5 rounded bg-sky-500/30 text-sky-200 font-extrabold">LOADING</span>';
        simProgress += dt * 0.065;

        if (simProgress < 0.28) {
            // Sub-tahap 6A: Meluncur dari timbangan (35.0) ke platform High-Loader (63.0)
            const subT = simProgress / 0.28;
            activeCargoGroup.position.x = THREE.MathUtils.lerp(35.0, 63.0, easeInOutQuad(subT));
            if (planeCargoDoorHatch) {
                const hatchAngle = THREE.MathUtils.lerp(0, -Math.PI * 0.44, easeOutQuad(subT));
                planeCargoDoorHatch.rotation.z = hatchAngle;
                document.getElementById('telemetry-door').textContent = 'OPENING (' + Math.round(subT * 100) + '%)';
                document.getElementById('telemetry-door').className = 'font-black text-amber-300 font-mono';
            }
        } else if (simProgress < 0.68) {
            // Sub-tahap 6B: Platform High-Loader naik hidrolik dari 0.55m ke 3.85m
            const subT = (simProgress - 0.28) / 0.40;
            const curY = THREE.MathUtils.lerp(0.55, 3.85, easeInOutCubic(subT));
            if (hlElevatorGroup) hlElevatorGroup.position.y = curY;
            if (updateHighLoaderScissors) updateHighLoaderScissors(curY);
            activeCargoGroup.position.y = curY + 0.1;
            document.getElementById('telemetry-elevator').textContent = curY.toFixed(2) + ' m';
            document.getElementById('telemetry-door').textContent = 'HOLD OPEN (3.85m)';
            document.getElementById('telemetry-door').className = 'font-black text-emerald-400 font-mono';

            if (hlStrobeMeshes && hlStrobeMeshes.length) {
                const flashOn = (Math.sin(Date.now() * 0.025) > 0);
                hlStrobeMeshes.forEach(s => s.material.color.setHex(flashOn ? 0xFBBF24 : 0x78350F));
            }

            if (subT > 0.05 && subT < 0.12) playSound('hydraulic');
        } else {
            // Sub-tahap 6C: Kontainer meluncur dari High-Loader (63.0) masuk ke palka Boeing 777F (74.0)
            const subT = (simProgress - 0.68) / 0.32;
            activeCargoGroup.position.x = THREE.MathUtils.lerp(63.0, 74.0, easeInOutQuad(subT));
            activeCargoGroup.position.y = 3.95;
            document.getElementById('telemetry-door').textContent = 'HOLD TRANSFER (' + Math.round(subT * 100) + '%)';
        }

        if (simCameraMode === 'follow' && !isUserInteracting) {
            camera.position.lerp(new THREE.Vector3(50.0, 9.5, -4.0), dt * 2.8);
            controls.target.lerp(new THREE.Vector3(68.0, 3.85, 8.0), dt * 2.8);
        }

        if (simProgress >= 1.0) {
            simActive = false;
            if (planeCargoDoorHatch) planeCargoDoorHatch.rotation.z = 0; // Pintu palka menutup rapat
            document.getElementById('telemetry-door').textContent = 'LOCKED & DEPARTED';
            document.getElementById('telemetry-door').className = 'font-black text-emerald-400 font-mono';
            updateStepperUI(7);
            const pFin = document.getElementById('sim-progress-pct');
            if (pFin) pFin.textContent = '100%';
            playSound('pass');

            addSimLog(`🎉 [SUCCESS] Kargo "${activeCargoData.name}" sukses dimuat ke palka Boeing 777F (PK-ACF). Siap terbang!`);
            showMilestone('PEMUATAN SELESAI!', `Kargo berhasil masuk palka Boeing 777F. Status: READY FOR FLIGHT!`);

            Swal.fire({
                title: 'PEMUATAN KE PESAWAT SUKSES!',
                html: `<div class="text-left text-sm space-y-2">
                    <p class="text-emerald-400 font-bold">✈️ STATUS PENERBANGAN: LOADED &amp; DEPARTED</p>
                    <p><strong>Kargo:</strong> ${activeCargoData.name}</p>
                    <p><strong>Berat Aktual:</strong> ${activeCargoData.weight} kg (Batas ULD: ${activeCargoData.maxWeight} kg)</p>
                    <p><strong>Pesawat:</strong> Boeing 777F Freighter (PK-ACF)</p>
                    <p><strong>Kompartemen:</strong> Main Freight Deck - Posisi 12R</p>
                    <div class="p-2.5 rounded-lg bg-teal-950/60 border border-teal-500/30 text-xs text-teal-200 mt-2">
                        Alur simulasi 6 tahap telah berhasil dilalui secara penuh mulai dari kedatangan truk, scan RFID, lolos inspeksi X-Ray, Central Storage, timbangan cerdas, hingga elevasi High-Loader ke pesawat.
                    </div>
                </div>`,
                icon: 'success',
                background: '#0D1C42',
                color: '#FFFFFF',
                confirmButtonColor: '#087F8A',
                confirmButtonText: 'Selesai &amp; Tutup'
            });
        }
    }
}

// Window Resize Handler
window.addEventListener('resize', () => {
    camera.aspect = container.clientWidth / container.clientHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(container.clientWidth, container.clientHeight);
});

// Auto-start check from URL parameter
window.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('autostart') === '1') {
        setTimeout(() => {
            if (typeof startLiveSimulation === 'function') {
                startLiveSimulation();
            }
        }, 800);
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
