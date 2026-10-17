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

// Ambil daftar kargo terkini untuk dropdown cepat
$cargosQuery = $db->query("
    SELECT c.id, c.awb_number, c.description, c.commodity_type, c.declared_weight_kg, c.quantity,
           t.truck_plate, t.driver_name, s.xray_result
    FROM cargo_shipments c
    LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
    LEFT JOIN security_checks s ON s.cargo_id = c.id
    ORDER BY c.created_at DESC
    LIMIT 20
");
$recentCargos = $cargosQuery->fetchAll();

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

    /* Sidebar Panel Kiri */
    #sidebar-panel {
        width: 390px;
        min-width: 390px;
        max-width: 390px;
        height: 100%;
        background: rgba(8, 18, 42, 0.96);
        border-right: 1px solid rgba(12, 161, 175, 0.32);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        display: flex;
        flex-direction: column;
        z-index: 30;
        box-shadow: 12px 0 40px rgba(0, 0, 0, 0.65);
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }
    #sidebar-panel.collapsed {
        margin-left: -390px;
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
    <div id="sidebar-panel">

        <!-- Tombol Toggle Collapse / Expand Sidebar -->
        <button id="sidebar-toggle-btn" onclick="toggleSidebar()" title="Sembunyikan / Tampilkan Panel Kontrol">
            <i class="fa-solid fa-chevron-left" id="sidebar-toggle-icon"></i>
        </button>

        <!-- Header Panel -->
        <div class="p-4 border-b border-teal-500/25 bg-gradient-to-r from-teal-950/70 to-navy-950/80 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-[#0CA1AF] to-[#014D54] flex items-center justify-center text-white shadow-md shadow-teal-900/40 border border-teal-400/40">
                    <i class="fa-solid fa-gamepad text-xs"></i>
                </div>
                <div>
                    <h2 class="text-sm font-black text-white tracking-tight flex items-center gap-1.5">
                        <span>STUDIO SIMULASI LIVE</span>
                        <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-extrabold border border-emerald-500/40">3D ENGINE</span>
                    </h2>
                    <p class="text-[10px] text-teal-200/80">Input kargo menggerakkan visual 3D real-time</p>
                </div>
            </div>

            <!-- Tombol Toggle Audio -->
            <button onclick="toggleAudio()" id="btn-audio" class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-xs transition-colors" title="Toggle Efek Audio WebAudio API">
                <i class="fa-solid fa-volume-high text-teal-300"></i>
            </button>
        </div>

        <!-- Scrollable Body Content -->
        <div class="sidebar-scroll">

            <!-- PRESET 1-CLICK SKENARIO CEPAT -->
            <div class="card-glass">
                <div class="flex items-center justify-between mb-2">
                    <span class="in-label !mb-0 text-amber-300">⚡ Skenario Cepat (1-Klik)</span>
                    <span class="text-[10px] text-white/50">Siap Uji</span>
                </div>
                <div class="grid grid-cols-3 gap-1.5">
                    <button type="button" onclick="loadScenarioPreset('normal')" class="btn-preset bg-emerald-950/50 border-emerald-500/40 text-emerald-200 hover:border-emerald-400 hover:bg-emerald-900/60 hover:shadow-emerald-900/40 text-left">
                        <i class="fa-solid fa-circle-check text-emerald-400 text-xs shrink-0"></i>
                        <div class="truncate">
                            <span class="block text-[10px] font-black leading-tight text-white">1. Aman</span>
                            <span class="block text-[8.5px] text-emerald-300/80 font-semibold">Lolos Palka</span>
                        </div>
                    </button>
                    <button type="button" onclick="loadScenarioPreset('suspect')" class="btn-preset bg-rose-950/50 border-rose-500/40 text-rose-200 hover:border-rose-400 hover:bg-rose-900/60 hover:shadow-rose-900/40 text-left">
                        <i class="fa-solid fa-triangle-exclamation text-rose-400 text-xs shrink-0"></i>
                        <div class="truncate">
                            <span class="block text-[10px] font-black leading-tight text-white">2. Suspect</span>
                            <span class="block text-[8.5px] text-rose-300/80 font-semibold">Karantina</span>
                        </div>
                    </button>
                    <button type="button" onclick="loadScenarioPreset('overweight')" class="btn-preset bg-amber-950/50 border-amber-500/40 text-amber-200 hover:border-amber-400 hover:bg-amber-900/60 hover:shadow-amber-900/40 text-left">
                        <i class="fa-solid fa-scale-unbalanced text-amber-400 text-xs shrink-0"></i>
                        <div class="truncate">
                            <span class="block text-[10px] font-black leading-tight text-white">3. Over</span>
                            <span class="block text-[8.5px] text-amber-300/80 font-semibold">Hold Scale</span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- FORM INPUT DATA KARGO -->
            <form id="liveCargoForm" onsubmit="event.preventDefault(); startLiveSimulation();" class="card-glass space-y-2.5">
                <div class="flex items-center justify-between border-b border-white/10 pb-2">
                    <span class="text-xs font-black text-white flex items-center gap-1.5">
                        <i class="fa-solid fa-boxes-packing text-teal-400"></i>
                        <span>DATA INPUT KARGO</span>
                    </span>
                    <span id="badge-mode" class="text-[9px] px-2 py-0.5 rounded-full bg-teal-500/20 text-teal-300 font-bold border border-teal-500/40">
                        <?= $initialCargo ? 'DB: ' . htmlspecialchars($initialCargo['awb_number']) : 'Kustom Input' ?>
                    </span>
                </div>

                <!-- Dropdown Pilih Kargo Database (Opsional) -->
                <?php if (!empty($recentCargos)): ?>
                <div>
                    <label class="in-label" for="db_cargo_select">Pilih Kargo Tersimpan di Database</label>
                    <select id="db_cargo_select" onchange="onSelectDbCargo(this)" class="in-field text-xs text-teal-200">
                        <option value="">-- Pilih Kargo Tersimpan (Opsional) --</option>
                        <?php foreach ($recentCargos as $rc): ?>
                            <?php $isSel = ($selectedCargoId === $rc['id']); ?>
                            <option value="<?= htmlspecialchars(json_encode($rc)) ?>" <?= $isSel ? 'selected' : '' ?>>
                                <?= htmlspecialchars($rc['awb_number']) ?> &bull; <?= htmlspecialchars(mb_strimwidth($rc['description'], 0, 20, '...')) ?> (<?= number_format($rc['declared_weight_kg'], 0) ?> kg)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php
                $initName = $initialCargo['description'] ?? 'Vaksin & Peralatan Medis Biofarma';
                $initCommodity = $initialCargo['commodity_type'] ?? 'PHARMA';
                $initQty = $initialCargo['quantity'] ?? 48;
                $initWeight = $initialCargo['declared_weight_kg'] ?? 350;
                $initXray = $initialCargo['xray_result'] ?? 'CLEARED';
                $initPlate = $initialCargo['truck_plate'] ?? 'B 9482 KXA';
                $initDriver = $initialCargo['driver_name'] ?? 'Suryadi Pratama';
                ?>

                <!-- Nama / Deskripsi Kargo -->
                <div>
                    <label class="in-label" for="cargo_name">Nama Barang / Deskripsi</label>
                    <input type="text" id="cargo_name" class="in-field" value="<?= htmlspecialchars($initName) ?>" placeholder="Contoh: Tekstil Ekspor, Baterai Lithium..." required>
                </div>

                <!-- Tipe Komoditas & Jumlah Koli -->
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="in-label" for="commodity_type">Tipe Komoditas</label>
                        <select id="commodity_type" class="in-field text-xs">
                            <option value="PHARMA" <?= $initCommodity === 'PHARMA' ? 'selected' : '' ?>>Pharma / Obat</option>
                            <option value="GENERAL_CARGO" <?= $initCommodity === 'GENERAL_CARGO' ? 'selected' : '' ?>>General Cargo</option>
                            <option value="DANGEROUS_GOODS" <?= $initCommodity === 'DANGEROUS_GOODS' ? 'selected' : '' ?>>Dangerous Goods (DG)</option>
                            <option value="PERISHABLE" <?= $initCommodity === 'PERISHABLE' ? 'selected' : '' ?>>Perishable / Makanan</option>
                            <option value="VALUABLE" <?= $initCommodity === 'VALUABLE' ? 'selected' : '' ?>>Valuable Goods</option>
                        </select>
                    </div>
                    <div>
                        <label class="in-label" for="cargo_quantity">Jumlah Koli</label>
                        <input type="number" id="cargo_quantity" class="in-field" value="<?= (int)$initQty ?>" min="1" max="500">
                    </div>
                </div>

                <!-- Berat Kargo & Batas Timbangan ULD -->
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="in-label" for="cargo_weight">Berat Kargo (kg)</label>
                        <input type="number" id="cargo_weight" class="in-field" value="<?= (float)$initWeight ?>" min="1" max="15000" step="0.5" required>
                    </div>
                    <div>
                        <label class="in-label" for="max_weight">Batas Maks. ULD (kg)</label>
                        <input type="number" id="max_weight" class="in-field" value="1588" min="100" max="15000" step="1" title="Batas Kapasitas Maksimum Kontainer ULD AKE">
                    </div>
                </div>

                <!-- HASIL UJI X-RAY (PERCABANGAN KRUSIAL) -->
                <div>
                    <label class="in-label">Hasil Pemeriksaan Mesin X-Ray</label>
                    <div class="grid grid-cols-2 gap-2" id="xray-toggle-group">
                        <label class="radio-tile <?= $initXray !== 'SUSPECT' ? 'selected-cleared' : '' ?>" id="tile-cleared" onclick="setXrayChoice('CLEARED')">
                            <input type="radio" name="xray_choice" value="CLEARED" <?= $initXray !== 'SUSPECT' ? 'checked' : '' ?> class="hidden">
                            <i class="fa-solid fa-shield-check text-emerald-400 text-sm"></i>
                            <div>
                                <div class="font-bold">Aman (Cleared)</div>
                                <div class="text-[9px] text-emerald-300/70 font-normal">Lolos ke Pesawat</div>
                            </div>
                        </label>
                        <label class="radio-tile <?= $initXray === 'SUSPECT' ? 'selected-suspect' : '' ?>" id="tile-suspect" onclick="setXrayChoice('SUSPECT')">
                            <input type="radio" name="xray_choice" value="SUSPECT" <?= $initXray === 'SUSPECT' ? 'checked' : '' ?> class="hidden">
                            <i class="fa-solid fa-triangle-exclamation text-rose-400 text-sm"></i>
                            <div>
                                <div class="font-bold">Berbahaya (Suspect)</div>
                                <div class="text-[9px] text-rose-300/70 font-normal">Masuk Karantina</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Truk Pengangkut & Pengemudi -->
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="in-label" for="truck_plate">Nomor Plat Truk</label>
                        <input type="text" id="truck_plate" class="in-field font-mono uppercase" value="<?= htmlspecialchars($initPlate) ?>" placeholder="B 1234 XX">
                    </div>
                    <div>
                        <label class="in-label" for="driver_name">Nama Pengemudi</label>
                        <input type="text" id="driver_name" class="in-field" value="<?= htmlspecialchars($initDriver) ?>">
                    </div>
                </div>

                <!-- TOMBOL EKSEKUSI UTAMA (START / RESTART) -->
                <div class="pt-2">
                    <button type="submit" id="btn-start-sim" class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-[#0CA1AF] via-[#087F8A] to-[#014D54] hover:from-[#04AFBF] hover:to-[#087F8A] text-white font-black text-sm tracking-wide shadow-[0_0_24px_rgba(12,161,175,0.45)] hover:shadow-[0_0_32px_rgba(12,161,175,0.7)] border border-teal-300/60 transition-all flex items-center justify-center gap-2 transform active:scale-95 cursor-pointer">
                        <i class="fa-solid fa-play text-xs text-amber-300 animate-pulse"></i>
                        <span id="btn-start-text">JALANKAN SIMULASI LIVE</span>
                    </button>
                </div>

                <!-- KONTROL PLAYBACK (PAUSE, SPEED, RESET) -->
                <div class="flex items-center gap-1.5 pt-0.5">
                    <button type="button" onclick="togglePauseSimulation()" id="btn-pause" class="flex-1 py-1.5 px-2 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-bold border border-white/10 flex items-center justify-center gap-1 transition-all">
                        <i class="fa-solid fa-pause text-[10px]"></i>
                        <span id="btn-pause-text">Pause</span>
                    </button>
                    <button type="button" onclick="resetLiveSimulation()" class="flex-1 py-1.5 px-2 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-bold border border-white/10 flex items-center justify-center gap-1 transition-all">
                        <i class="fa-solid fa-rotate-left text-[10px] text-amber-300"></i>
                        <span>Reset</span>
                    </button>
                    <div class="flex items-center bg-white/10 rounded-lg p-0.5 border border-white/10">
                        <button type="button" onclick="setSpeed(1)" id="spd-1" class="px-2 py-1 rounded text-[10px] font-extrabold bg-[#0CA1AF] text-white">1x</button>
                        <button type="button" onclick="setSpeed(2)" id="spd-2" class="px-2 py-1 rounded text-[10px] font-extrabold text-white/70 hover:text-white">2x</button>
                        <button type="button" onclick="setSpeed(4)" id="spd-4" class="px-2 py-1 rounded text-[10px] font-extrabold text-white/70 hover:text-white">4x</button>
                    </div>
                </div>
            </form>

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
                                <span>Truk Mengantar Kargo</span>
                                <span class="step-status text-[9px] text-white/40 font-mono">STANDBY</span>
                            </div>
                            <p class="text-[10px] text-white/60 truncate">Truk menuju Gerbang Terminal Bandara</p>
                        </div>
                    </div>

                    <div class="step-item" id="step-ui-2">
                        <div class="step-circle" id="circle-2">2</div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-white flex items-center justify-between">
                                <span>Kedatangan &amp; Scan RFID</span>
                                <span class="step-status text-[9px] text-white/40 font-mono">PENDING</span>
                            </div>
                            <p class="text-[10px] text-white/60 truncate">Validasi nirkabel GS1 SSCC-18 di portal</p>
                        </div>
                    </div>

                    <div class="step-item" id="step-ui-3">
                        <div class="step-circle" id="circle-3">3</div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-white flex items-center justify-between">
                                <span>Pemeriksaan X-Ray AVSEC</span>
                                <span class="step-status text-[9px] text-white/40 font-mono">PENDING</span>
                            </div>
                            <p class="text-[10px] text-white/60 truncate">Dual-view laser scan densitas fisik</p>
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
            <button onclick="toggleFullscreen3D()" id="btn-fullscreen-toggle" class="cam-btn text-amber-300 hover:text-white" title="Layar Penuh (Fullscreen 3D)">
                <i class="fa-solid fa-expand text-xs"></i>
            </button>
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
        <div class="absolute bottom-4 left-4 z-20 flex items-center gap-2.5 p-2 rounded-2xl bg-slate-900/90 backdrop-blur-md border border-teal-500/35 shadow-2xl max-w-md w-[calc(100%-380px)] sm:w-auto">
            <button onclick="togglePauseSimulation()" id="scrub-play-btn" class="w-8 h-8 rounded-xl bg-gradient-to-r from-[#0CA1AF] to-[#087F8A] hover:from-[#04AFBF] hover:to-[#0CA1AF] text-white flex items-center justify-center text-xs shadow-md shadow-teal-900/40 hover:scale-105 active:scale-95 transition-all cursor-pointer" title="Play / Pause Simulasi">
                <i class="fa-solid fa-play text-[11px]" id="scrub-play-icon"></i>
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
    name: 'Vaksin & Peralatan Medis Biofarma',
    commodity: 'PHARMA',
    qty: 48,
    weight: 350.0,
    maxWeight: 1588.0,
    xrayResult: 'CLEARED',
    truckPlate: 'B 9482 KXA',
    driver: 'Suryadi Pratama',
    awb: '<?= $initialCargo ? $initialCargo['awb_number'] : '126-88492019' ?>'
};

function onSelectDbCargo(selectEl) {
    if (!selectEl.value) return;
    try {
        const item = JSON.parse(selectEl.value);
        document.getElementById('cargo_name').value = item.description || '';
        document.getElementById('commodity_type').value = item.commodity_type || 'GENERAL_CARGO';
        document.getElementById('cargo_quantity').value = item.quantity || 40;
        document.getElementById('cargo_weight').value = item.declared_weight_kg || 350;
        document.getElementById('truck_plate').value = item.truck_plate || 'B 9482 KXA';
        document.getElementById('driver_name').value = item.driver_name || 'Suryadi Pratama';
        setXrayChoice(item.xray_result === 'SUSPECT' ? 'SUSPECT' : 'CLEARED');
        document.getElementById('badge-mode').textContent = 'Kargo DB: ' + item.awb_number;
        addSimLog(`📥 [LOAD DB] Memuat data kargo ${item.awb_number}: "${item.description}" (${item.declared_weight_kg} kg).`);
    } catch(e) { console.error(e); }
}

function setXrayChoice(choice) {
    activeCargoData.xrayResult = choice;
    const tileC = document.getElementById('tile-cleared');
    const tileS = document.getElementById('tile-suspect');
    if (choice === 'CLEARED') {
        tileC.className = 'radio-tile selected-cleared';
        tileS.className = 'radio-tile';
        document.getElementById('telemetry-xray').textContent = 'CLEARED';
        document.getElementById('telemetry-xray').className = 'font-black text-emerald-400 font-mono';
    } else {
        tileC.className = 'radio-tile';
        tileS.className = 'radio-tile selected-suspect';
        document.getElementById('telemetry-xray').textContent = 'SUSPECT';
        document.getElementById('telemetry-xray').className = 'font-black text-rose-400 font-mono';
    }
}

function loadScenarioPreset(type) {
    if (type === 'normal') {
        document.getElementById('cargo_name').value = 'Garmen & Tekstil Ekspor Premium';
        document.getElementById('commodity_type').value = 'GENERAL_CARGO';
        document.getElementById('cargo_quantity').value = 60;
        document.getElementById('cargo_weight').value = 350;
        document.getElementById('max_weight').value = 1588;
        setXrayChoice('CLEARED');
        document.getElementById('truck_plate').value = 'B 9102 SXZ';
        document.getElementById('driver_name').value = 'Budi Santoso';
        document.getElementById('badge-mode').textContent = 'Preset: Lolos Aman';
        addSimLog('⚡ [PRESET] Dimuat: Skenario 1 - Barang Aman, lolos hingga Boeing 777F.');
    } else if (type === 'suspect') {
        document.getElementById('cargo_name').value = 'Baterai Lithium & Aerosol Kimia DG';
        document.getElementById('commodity_type').value = 'DANGEROUS_GOODS';
        document.getElementById('cargo_quantity').value = 24;
        document.getElementById('cargo_weight').value = 480;
        document.getElementById('max_weight').value = 1588;
        setXrayChoice('SUSPECT');
        document.getElementById('truck_plate').value = 'B 9941 DGX';
        document.getElementById('driver_name').value = 'Hendrawan';
        document.getElementById('badge-mode').textContent = 'Preset: Berbahaya';
        addSimLog('⚡ [PRESET] Dimuat: Skenario 2 - Barang Berbahaya (DG) → Masuk Karantina.');
    } else if (type === 'overweight') {
        document.getElementById('cargo_name').value = 'Suku Cadang Kompresor Turbin Berat';
        document.getElementById('commodity_type').value = 'GENERAL_CARGO';
        document.getElementById('cargo_quantity').value = 8;
        document.getElementById('cargo_weight').value = 2200;
        document.getElementById('max_weight').value = 1588;
        setXrayChoice('CLEARED');
        document.getElementById('truck_plate').value = 'B 9044 HVY';
        document.getElementById('driver_name').value = 'Agus Suprayogi';
        document.getElementById('badge-mode').textContent = 'Preset: Overweight';
        addSimLog('⚡ [PRESET] Dimuat: Skenario 3 - Kelebihan Beban (2,200 kg) → Ditolak di Timbangan.');
    }
}

function setSpeed(spd) {
    simSpeed = spd;
    ['spd-1', 'spd-2', 'spd-4'].forEach(id => {
        const el = document.getElementById(id);
        el.className = 'px-2 py-1 rounded text-[10px] font-extrabold text-white/70 hover:text-white';
    });
    const cur = document.getElementById('spd-' + spd);
    if (cur) cur.className = 'px-2 py-1 rounded text-[10px] font-extrabold bg-[#0CA1AF] text-white';
    addSimLog(`⏩ Kecepatan simulasi: ${spd}x.`);
}

function togglePauseSimulation() {
    isSimPaused = !isSimPaused;
    const btnText = document.getElementById('btn-pause-text');
    if (isSimPaused) {
        btnText.textContent = 'Lanjut';
        document.getElementById('btn-pause').innerHTML = '<i class="fa-solid fa-play text-[10px] text-emerald-400"></i> <span>Lanjut</span>';
        addSimLog('⏸️ Simulasi di-pause.');
    } else {
        btnText.textContent = 'Pause';
        document.getElementById('btn-pause').innerHTML = '<i class="fa-solid fa-pause text-[10px]"></i> <span>Pause</span>';
        addSimLog('▶️ Simulasi dilanjutkan.');
    }
    if (typeof updateTimelineHUD === 'function') updateTimelineHUD();
}

function addSimLog(msg, isAlert = false) {
    const feed = document.getElementById('sim-log-feed');
    const time = new Date().toTimeString().split(' ')[0];
    const el = document.createElement('div');
    el.className = isAlert ? 'text-rose-400 font-bold' : 'text-slate-300';
    el.innerHTML = `<span class="text-teal-400/80">[${time}]</span> ${msg}`;
    feed.appendChild(el);
    feed.scrollTop = feed.scrollHeight;
}

function clearSimLogs() {
    document.getElementById('sim-log-feed').innerHTML = '';
}

function showMilestone(title, desc, iconClass = 'fa-bell', isAlert = false) {
    const banner = document.getElementById('milestone-banner');
    document.getElementById('milestone-title').textContent = title;
    document.getElementById('milestone-desc').textContent = desc;
    document.getElementById('milestone-icon').innerHTML = `<i class="fa-solid ${iconClass}"></i>`;
    
    if (isAlert) {
        banner.style.borderColor = '#EF4444';
        document.getElementById('milestone-icon').style.color = '#EF4444';
        playSound('alert');
    } else {
        banner.style.borderColor = '#0CA1AF';
        document.getElementById('milestone-icon').style.color = '#38BDF8';
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
        const statusSpan = item.querySelector('.step-status');
        item.classList.remove('active-step', 'done-step', 'alert-step');
        if (pill) pill.classList.remove('active');

        if (alertStep === i) {
            item.classList.add('alert-step');
            if (pill) pill.classList.add('active');
            statusSpan.textContent = 'ALERT/HOLD';
            statusSpan.className = 'step-status text-[9px] text-rose-400 font-mono font-bold animate-pulse';
        } else if (i < currentStep) {
            item.classList.add('done-step');
            statusSpan.textContent = 'DONE';
            statusSpan.className = 'step-status text-[9px] text-emerald-400 font-mono font-bold';
        } else if (i === currentStep) {
            item.classList.add('active-step');
            if (pill) pill.classList.add('active');
            statusSpan.textContent = 'ACTIVE';
            statusSpan.className = 'step-status text-[9px] text-teal-300 font-mono font-bold';
        } else {
            statusSpan.textContent = 'PENDING';
            statusSpan.className = 'step-status text-[9px] text-white/40 font-mono';
        }
    }
    const pct = Math.min(100, Math.round(((currentStep - 1) / 5) * 100));
    document.getElementById('sim-progress-pct').textContent = pct + '%';
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

const camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.5, 600);
camera.position.set(0, 48, 55);

const controls = new THREE.OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.08;
controls.maxPolarAngle = Math.PI / 2 - 0.02;
controls.minDistance = 5;
controls.maxDistance = 260;
controls.target.set(0, 2, -10);

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
mainSun.shadow.bias = -0.0004;
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
// MATERIALS
// ─────────────────────────────────────────────────────────────────────────────
const MAT = {
    floorTerminal: new THREE.MeshStandardMaterial({ color: 0xEEF2F6, roughness: 0.35, metalness: 0.12 }),
    floorTarmac: new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.95, metalness: 0.05 }),
    floorQuarantine: new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.45, metalness: 0.2 }),
    wallBuilding: new THREE.MeshStandardMaterial({ color: 0xE2E8F0, roughness: 0.7 }),
    wallPanel: new THREE.MeshStandardMaterial({ color: 0xE2E8F0, roughness: 0.5, metalness: 0.1 }),
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
    planeNavy: new THREE.MeshStandardMaterial({ color: 0x0A2540, roughness: 0.35, metalness: 0.25 }),
    cockpitGlass: new THREE.MeshStandardMaterial({ color: 0x07111E, roughness: 0.08, metalness: 0.92 }),
    chromeIntake: new THREE.MeshStandardMaterial({ color: 0xE2E8F0, metalness: 0.95, roughness: 0.12 }),
    engineCore: new THREE.MeshStandardMaterial({ color: 0x1E293B, metalness: 0.8, roughness: 0.4 })
};

// Billboard Generator
function makeLabelSprite(text, subtext, color = '#0CA1AF') {
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 140;
    const ctx = c.getContext('2d');
    ctx.fillStyle = 'rgba(8, 18, 38, 0.94)';
    ctx.strokeStyle = color;
    ctx.lineWidth = 4;
    ctx.beginPath();
    ctx.roundRect(8, 8, 496, 124, 20);
    ctx.fill();
    ctx.stroke();

    ctx.fillStyle = color;
    ctx.font = 'bold 24px Barlow, sans-serif';
    ctx.fillText(text, 24, 46);

    ctx.fillStyle = '#FFFFFF';
    ctx.font = 'bold 28px Barlow, sans-serif';
    ctx.fillText(subtext, 24, 94);

    const tex = new THREE.CanvasTexture(c);
    const spriteMat = new THREE.SpriteMaterial({ map: tex, transparent: true });
    const sprite = new THREE.Sprite(spriteMat);
    sprite.scale.set(12, 3.3, 1);
    return sprite;
}

// Painted Ground Markings Text Generator
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
    const mat = new THREE.MeshBasicMaterial({ map: tex, transparent: true });
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

// ─────────────────────────────────────────────────────────────────────────────
// 1. LANTAI INDUSTRI & TARMAC APRON (INDUSTRIAL FLOORS & MARKINGS)
// ─────────────────────────────────────────────────────────────────────────────
// Aspal Tarmac Landside & Apron Airside (180m x 160m)
const tarmacGeo = new THREE.PlaneGeometry(180, 160);
const tarmac = new THREE.Mesh(tarmacGeo, MAT.floorTarmac);
tarmac.rotation.x = -Math.PI / 2;
tarmac.position.set(0, -0.05, 0);
tarmac.receiveShadow = true;
layoutGroup.add(tarmac);

// Marka Garis Kuning Akses Jalan Truk Masuk (Landside Access Road X = -5.0)
for (let rz = -65; rz <= -35; rz += 4.5) {
    const rLine = new THREE.Mesh(new THREE.PlaneGeometry(0.35, 2.6), new THREE.MeshBasicMaterial({ color: 0xFDE047 }));
    rLine.rotation.x = -Math.PI / 2;
    rLine.position.set(-5.0, 0.01, rz);
    layoutGroup.add(rLine);
}

// Lantai Epoksi Terminal Kargo Indoor (Epoxy Floor 88m x 64.75m)
const termGeo = new THREE.PlaneGeometry(88, 64.75);
const termFloor = new THREE.Mesh(termGeo, MAT.floorTerminal);
termFloor.rotation.x = -Math.PI / 2;
termFloor.position.set(-6, 0.02, -3.375);
termFloor.receiveShadow = true;
layoutGroup.add(termFloor);

// Dinding Dermaga Belakang (Dock Wall di Z = -35.75)
const dockWall = new THREE.Mesh(new THREE.BoxGeometry(88, 7.5, 0.4), MAT.wallBuilding);
dockWall.position.set(-6, 3.75, -35.75);
dockWall.receiveShadow = true;
layoutGroup.add(dockWall);

// Kolom Baja Struktural Bangunan Terminal dengan Bumper Kuning Pengaman
[-38, -20, 5, 25].forEach(cx => {
    [-20, 0, 18].forEach(cz => {
        const col = new THREE.Mesh(new THREE.BoxGeometry(0.5, 7.5, 0.5), MAT.metalDark);
        col.position.set(cx, 3.75, cz);
        const colBase = new THREE.Mesh(new THREE.BoxGeometry(0.95, 0.65, 0.95), MAT.yellowSafety);
        colBase.position.set(cx, 0.325, cz);
        layoutGroup.add(col, colBase);
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

    // Signboard Billboard Kanopi Luar
    const canopySign = makeLabelSprite("TERMINAL KARGO BANDARA", "INJOURNEY INBOUND DOCKS (BAY 1 - 3)", "#0CA1AF");
    canopySign.scale.set(16, 4.4, 1);
    canopySign.position.set(0, 2.8, -canopyD / 2);

    canopyGroup.add(canopyRoof, fasciaFront, fasciaStripe, canopySign);
    archGroup.add(canopyGroup);

    // 2. DINDING SISI BARAT (WEST EXTERIOR WALL di X = -50.0)
    // Menutup warehouse dari Z = -35.75 sampai Z = 29.0 (Panjang 64.75m, Tinggi 7.5m)
    const westWallGeo = new THREE.BoxGeometry(0.4, 7.5, 64.75);
    const westWall = new THREE.Mesh(westWallGeo, MAT.wallBuilding);
    westWall.position.set(-50.0, 3.75, -3.375);
    westWall.receiveShadow = true;

    // Aksen Garis Teal InJourney pada Dinding Barat
    const westStripe = new THREE.Mesh(new THREE.BoxGeometry(0.45, 0.35, 64.75), MAT.injourneyTeal);
    westStripe.position.set(-50.0, 3.2, -3.375);

    // Pintu Darurat & Akses Mezzanine di Dinding Barat
    archGroup.add(westWall, westStripe);
    archGroup.add(createPersonnelDoor(-49.8, -12.0, Math.PI / 2, "PINTU DARURAT (EGRESS)"));
    archGroup.add(createPersonnelDoor(-49.8, 14.0, Math.PI / 2, "AKSES MEZZANINE"));

    // 3. DINDING SISI SELATAN (SOUTH WAREHOUSE WALL di Z = 29.0)
    // Menutup dari X = -50.0 sampai X = 38.0 (Panjang 88m, Tinggi 7.5m)
    const southWallGeo = new THREE.BoxGeometry(88.0, 7.5, 0.4);
    const southWall = new THREE.Mesh(southWallGeo, MAT.wallBuilding);
    southWall.position.set(-6.0, 3.75, 29.0);
    southWall.receiveShadow = true;

    // Jendela Clerestory Kaca Atas Dinding Selatan
    const winGeo = new THREE.BoxGeometry(86.0, 1.4, 0.45);
    const southWin = new THREE.Mesh(winGeo, MAT.wallGlass);
    southWin.position.set(-6.0, 6.2, 29.0);
    archGroup.add(southWall, southWin);

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

    // Signboard TOC
    const tocSign = makeLabelSprite("TOC OPERATIONS CENTER", "AVSEC DISPATCH & CARGO CONTROL", "#38BDF8");
    tocSign.scale.set(10.0, 2.8, 1);
    tocSign.position.set(0, 6.6, -6.1);
    tocGroup.add(tocFloor, tocGlass, tocCeil, tocSign);
    archGroup.add(tocGroup);

    // 6. RANGKA KUDA-KUDA ATAP BAJA RUANG TERBUKA (OVERHEAD STEEL ROOF TRUSSES)
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

        archGroup.add(trussGroup);
    });

    return archGroup;
}
layoutGroup.add(createModernTerminalArchitecture());

// ─────────────────────────────────────────────────────────────────────────────
// DENAH MARKA LANTAI INDUSTRI LENGKAP (EPOXY SAFETY WALKWAYS & HATCHING)
// ─────────────────────────────────────────────────────────────────────────────
function createCompleteDenahFloorMarkings() {
    const markGroup = new THREE.Group();

    // 1. JALUR PEJALAN KAKI HIJAU KESELAMATAN (SAFETY WALKWAY - 1.6m WIDE)
    const walkMat = MAT.safetyGreen;
    const walkLineMat = new THREE.MeshBasicMaterial({ color: 0xFFFFFF });

    const w1 = new THREE.Mesh(new THREE.PlaneGeometry(1.6, 52.0), walkMat);
    w1.rotation.x = -Math.PI / 2;
    w1.position.set(-46.0, 0.025, -6.0);
    markGroup.add(w1);

    for (let wz = -31.0; wz <= 19.0; wz += 2.5) {
        const wl = new THREE.Mesh(new THREE.PlaneGeometry(0.12, 1.4), walkLineMat);
        wl.rotation.x = -Math.PI / 2;
        wl.position.set(-45.1, 0.027, wz);
        markGroup.add(wl);
    }

    const wCross = new THREE.Mesh(new THREE.PlaneGeometry(36.0, 1.4), walkMat);
    wCross.rotation.x = -Math.PI / 2;
    wCross.position.set(-27.0, 0.025, -23.0);
    markGroup.add(wCross);

    // 2. KOTAK KESELAMATAN ARSIR KUNING-HITAM (YELLOW/BLACK SAFETY HATCHING)
    function createHatchedZone(cx, cz, w, h, label = "KEEP CLEAR") {
        const hz = new THREE.Group();
        hz.position.set(cx, 0.026, cz);

        const borderMat = new THREE.MeshBasicMaterial({ color: 0xFACC15 });
        const bTop = new THREE.Mesh(new THREE.PlaneGeometry(w, 0.18), borderMat);
        bTop.rotation.x = -Math.PI / 2;
        bTop.position.set(0, 0, -h / 2);
        const bBtm = new THREE.Mesh(new THREE.PlaneGeometry(w, 0.18), borderMat);
        bBtm.rotation.x = -Math.PI / 2;
        bBtm.position.set(0, 0, h / 2);
        const bL = new THREE.Mesh(new THREE.PlaneGeometry(0.18, h), borderMat);
        bL.rotation.x = -Math.PI / 2;
        bL.position.set(-w / 2, 0, 0);
        const bR = new THREE.Mesh(new THREE.PlaneGeometry(0.18, h), borderMat);
        bR.rotation.x = -Math.PI / 2;
        bR.position.set(w / 2, 0, 0);
        hz.add(bTop, bBtm, bL, bR);

        const numStripes = Math.floor((w + h) / 1.8);
        for (let s = 0; s < numStripes; s++) {
            const stripe = new THREE.Mesh(new THREE.PlaneGeometry(0.18, Math.min(w, h) * 0.95), borderMat);
            stripe.rotation.x = -Math.PI / 2;
            stripe.rotation.z = Math.PI / 4;
            const sx = -w / 2 + (s / numStripes) * w;
            stripe.position.set(sx, 0, 0);
            hz.add(stripe);
        }

        if (label) {
            hz.add(makeTarmacText(label, Math.min(5.0, w * 0.8), 1.2, "#FACC15"));
            hz.children[hz.children.length - 1].position.set(0, 0.005, 0);
        }
        return hz;
    }

    markGroup.add(createHatchedZone(5.0, -16.5, 12.0, 3.4, "KEEP CLEAR - DIVERTER"));
    markGroup.add(createHatchedZone(35.0, 8.0, 8.5, 4.5, "SCALE LOAD ZONE"));

    // 3. KOTAK STAGING BARANG PADA LANTAI GUDANG (STAGING BAYS)
    function createStagingBayOutline(bx, bz, bw, bd, bayName) {
        const bg = new THREE.Group();
        bg.position.set(bx, 0.026, bz);
        const bMat = new THREE.MeshBasicMaterial({ color: 0x38BDF8 });
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

        bg.add(makeTarmacText(bayName, Math.min(4.5, bw * 0.8), 1.0, "#38BDF8"));
        bg.children[bg.children.length - 1].position.set(0, 0.005, 0);
        return bg;
    }

    markGroup.add(createStagingBayOutline(-18.0, -18.0, 5.5, 4.0, "BUFFER INFEED 01"));
    markGroup.add(createStagingBayOutline(-18.0, -12.0, 5.5, 4.0, "BUFFER INFEED 02"));
    markGroup.add(createStagingBayOutline(0.0, 14.0, 7.5, 5.0, "ULD BUILD-UP BAY"));

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

// Forklift Lapangan Realistis
function createDetailedForklift(x, z, rotY, withCargo = false) {
    const fl = new THREE.Group();
    fl.position.set(x, 0, z);
    fl.rotation.y = rotY;

    const chassis = new THREE.Mesh(new THREE.BoxGeometry(1.4, 0.6, 2.0), MAT.yellowSafety);
    chassis.position.set(0, 0.45, -0.2);
    const cw = new THREE.Mesh(new THREE.BoxGeometry(1.38, 0.55, 0.55), MAT.metalDark);
    cw.position.set(0, 0.5, -1.05);

    const seat = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.4, 0.45), MAT.metalDark);
    seat.position.set(0, 1.0, -0.25);
    const steerCol = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.6, 8), MAT.metalSilver);
    steerCol.position.set(0, 1.05, 0.28);
    steerCol.rotation.x = -0.3;

    const cage = new THREE.Mesh(new THREE.BoxGeometry(1.2, 1.5, 1.1), new THREE.MeshStandardMaterial({ color: 0x1E293B, wireframe: true }));
    cage.position.set(0, 1.8, -0.15);
    const cageRoof = new THREE.Mesh(new THREE.BoxGeometry(1.25, 0.05, 1.15), MAT.metalDark);
    cageRoof.position.set(0, 2.55, -0.15);

    [-0.42, 0.42].forEach(mx => {
        const mast = new THREE.Mesh(new THREE.BoxGeometry(0.09, 2.2, 0.1), MAT.metalDark);
        mast.position.set(mx, 1.25, 0.85);
        fl.add(mast);
    });

    [-0.3, 0.3].forEach(fx => {
        const forkV = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.55, 0.04), MAT.metalSilver);
        forkV.position.set(fx, 0.45, 0.95);
        const forkH = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.04, 1.1), MAT.metalSilver);
        forkH.position.set(fx, 0.14, 1.48);
        fl.add(forkV, forkH);
    });

    const wGeo = new THREE.CylinderGeometry(0.3, 0.3, 0.2, 16);
    wGeo.rotateZ(Math.PI / 2);
    [[-0.65, 0.5], [0.65, 0.5], [-0.6, -0.8], [0.6, -0.8]].forEach(([wx, wz]) => {
        const w = new THREE.Mesh(wGeo, MAT.truckTire);
        w.position.set(wx, 0.3, wz);
        fl.add(w);
    });

    if (withCargo) {
        const pal = new THREE.Mesh(new THREE.BoxGeometry(1.2, 0.12, 1.0), MAT.palletWood);
        pal.position.set(0, 0.22, 1.48);
        const b1 = new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.55, 0.85), MAT.cardboardBox);
        b1.position.set(0, 0.56, 1.48);
        fl.add(pal, b1);
    }

    fl.add(chassis, cw, seat, steerCol, cage, cageRoof);
    return fl;
}

dockGroup.add(createDetailedForklift(-15.0, -26.0, Math.PI / 2, true));

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
chargerBay.add(createDetailedForklift(-0.2, 0, 0, false));
dockGroup.add(chargerBay);

const dockSign = makeLabelSprite("LOKASI 1", "TRUCK INBOUND DOCKS (BAY 1 - 3)", "#38BDF8");
dockSign.position.set(0, 7.8, -35.0);
dockGroup.add(dockSign);

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

const rfidSign = makeLabelSprite("LOKASI 2: AIDC PORTAL", "RFID SSCC-18 SCANNER", "#0CA1AF");
rfidSign.position.set(0, 7.2, 0);

rfidPortalGroup.add(pPostL, pPostR, pArch, scanBarL, scanBarR, rfidWaveMesh, rfidSign);
rfidGroup.add(rfidPortalGroup);

// ─────────────────────────────────────────────────────────────────────────────
// 6. JARINGAN KONVEYOR KONTINU & PNEUMATIC DIVERTER ARM
// ─────────────────────────────────────────────────────────────────────────────
const animatedRollers = [];

function createConveyorSegment(x, z, lenX, lenZ) {
    const cg = new THREE.Group();
    cg.position.set(x, 0.4, z);
    const bed = new THREE.Mesh(new THREE.BoxGeometry(lenX, 0.25, lenZ), MAT.metalDark);
    bed.position.y = -0.12;
    bed.receiveShadow = true;
    cg.add(bed);

    // Kaki Baja Konveyor
    const numLegsX = Math.max(2, Math.round(lenX / 4.0));
    const numLegsZ = Math.max(2, Math.round(lenZ / 4.0));
    for (let ix = 0; ix < numLegsX; ix++) {
        for (let iz = 0; iz < numLegsZ; iz++) {
            const lx = -lenX / 2 + 0.3 + (ix / Math.max(1, numLegsX - 1)) * (lenX - 0.6);
            const lz = -lenZ / 2 + 0.3 + (iz / Math.max(1, numLegsZ - 1)) * (lenZ - 0.6);
            const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.05, 0.4, 8), MAT.metalDark);
            leg.position.set(lx, -0.2, lz);
            cg.add(leg);
        }
    }

    // Rollers Berputar
    const isZAxis = (lenZ >= lenX);
    if (isZAxis) {
        for (let rz = -lenZ / 2 + 0.3; rz <= lenZ / 2 - 0.3; rz += 0.5) {
            const rol = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, lenX - 0.1, 12), MAT.conveyorRoller);
            rol.rotateZ(Math.PI / 2);
            rol.position.set(0, 0.06, rz);
            cg.add(rol);
            animatedRollers.push({ mesh: rol, axis: 'x' });
        }
    } else {
        for (let rx = -lenX / 2 + 0.3; rx <= lenX / 2 - 0.3; rx += 0.5) {
            const rol = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, lenZ - 0.1, 12), MAT.conveyorRoller);
            rol.rotateX(Math.PI / 2);
            rol.position.set(rx, 0.06, 0);
            cg.add(rol);
            animatedRollers.push({ mesh: rol, axis: 'z' });
        }
    }
    return cg;
}

// Feeder Dermaga 2 (-5, -34 s.d. -20)
conveyorGroup.add(createConveyorSegment(-5.0, -27.0, 2.2, 14.0));

// Feeder Dermaga 1 (-26) & Feeder Dermaga 3 (22)
conveyorGroup.add(createConveyorSegment(-26.0, -30.0, 2.2, 8.0));
conveyorGroup.add(createConveyorSegment(22.0, -30.0, 2.2, 8.0));

// Master Receiving Trunk Conveyor (Z = -31.0)
conveyorGroup.add(createConveyorSegment(-2.0, -31.0, 48.0, 2.2));

// Diverter Arm Pneumatik di X = -5.0, Z = -16.5
const diverterGroup = new THREE.Group();
diverterGroup.position.set(-5.0, 0.5, -16.5);
const divBase = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.45, 0.8), MAT.yellowSafety);
divBase.position.set(-1.25, 0, 0);
const divPiston = new THREE.Mesh(new THREE.CylinderGeometry(0.065, 0.065, 1.8, 12), MAT.metalSilver);
divPiston.rotateZ(Math.PI / 2);
divPiston.position.set(-0.35, 0, 0);
const divArm = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.45, 1.8), MAT.redHazard);
divArm.position.set(0.6, 0, 0);
diverterGroup.add(divBase, divPiston, divArm);
scene.add(diverterGroup);

// Jalur Konveyor Isolasi Masuk ke Ruang Karantina (Location 3B di X = 22, Z = -16.5)
conveyorGroup.add(createConveyorSegment(9.5, -16.5, 27.0, 2.2));

// Konveyor Keluar X-Ray Menuju Central Storage
conveyorGroup.add(createConveyorSegment(-5.0, -8.0, 2.2, 14.0));
conveyorGroup.add(createConveyorSegment(-2.5, 0.0, 5.0, 2.2));
conveyorGroup.add(createConveyorSegment(0.0, 4.0, 2.2, 8.0));

// Konveyor Transfer Central Storage ke Timbangan (Dari X = 0 ke X = 35.0, Z = 8.0)
conveyorGroup.add(createConveyorSegment(17.5, 8.0, 35.0, 2.4));

// Rel Transfer Timbangan ke Gerbang Airside & High-Loader (Dari X = 37.0 ke X = 53.0, Z = 8.0)
conveyorGroup.add(createConveyorSegment(45.0, 8.0, 16.0, 2.4));

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

const xrLabel = makeLabelSprite("LOKASI 3: INSPEKSI", "DUAL-VIEW X-RAY LINES", "#F59E0B");
xrLabel.position.set(-5.0, 6.8, -18.5);
xrayGroup.add(xrLabel);

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

    const qLabel = makeLabelSprite("LOKASI 3B: ISOLASI", "RUANG KARANTINA & ETD", "#EF4444");
    qLabel.position.set(0, 6.2, 0);
    q.add(qLabel);

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
    const rimWest = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.25, 4.4), MAT.yellowSafety);
    rimWest.position.set(-2.2, 0.44, 8.0);
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
    cs.add(rimWest, rimSouth, rimNorthLeft, rimNorthRight, rimEastTop, rimEastBottom);

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

    // 3D Zone Banners
    cs.add(makeLabelSprite("ZONE A", "RACK STORAGE", "#3B82F6"));
    cs.children[cs.children.length - 1].position.set(-10.5, 5.2, 1.3);

    cs.add(makeLabelSprite("ZONE B", "BUFFER STAGING", "#F59E0B"));
    cs.children[cs.children.length - 1].position.set(10.0, 5.2, -3.2);

    cs.add(makeLabelSprite("ZONE C", "ULD STAGING", "#10B981"));
    cs.children[cs.children.length - 1].position.set(10.0, 5.2, 13.5);

    const stSign = makeLabelSprite("LOKASI 4", "CENTRAL STORAGE & ULD BUILD-UP", "#10B981");
    stSign.position.set(0, 7.2, 8.0);
    cs.add(stSign);

    return cs;
}
storageGroup.add(createDetailedStorage());

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

    const scSign = makeLabelSprite("LOKASI 5: TIMBANGAN", "SMART ULD FLOOR SCALE 15T", "#A855F7");
    scSign.position.set(0, 6.8, 0);

    sc.add(tower, screenBox, scaleDisplayMesh, scSign);
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
// Dinding Gerbang Airside Pemisah Terminal & Apron di X = 45.0
const airsideWall = new THREE.Mesh(new THREE.BoxGeometry(0.4, 8, 40), MAT.wallBuilding);
airsideWall.position.set(45.0, 4, 8.0);
const airsideGateFrame = new THREE.Mesh(new THREE.BoxGeometry(0.6, 4.8, 4.4), MAT.metalDark);
airsideGateFrame.position.set(45.0, 2.4, 8.0);
const airsideShutter = new THREE.Mesh(new THREE.BoxGeometry(0.2, 4.2, 3.8), new THREE.MeshStandardMaterial({ color: 0x475569, metalness: 0.8 }));
airsideShutter.position.set(45.0, 2.1, 8.0);
airsideGroup.add(airsideWall, airsideGateFrame, airsideShutter);

let hlElevatorGroup = null;
let updateHighLoaderScissors = null;
let hlStrobeMeshes = [];

function createRealisticHighLoader() {
    const hl = new THREE.Group();
    hl.position.set(53, 0, 8);

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
let planeCargoDoorHatch = null;
let planeFanBlades = [];
let planeAviationLights = null;

function createDetailedCargoPlane() {
    const plane = new THREE.Group();
    plane.position.set(64, 0, 0);

    const fuseGroup = new THREE.Group();
    plane.add(fuseGroup);

    // Tabung Fuselage Depan & Belakang dengan Cut-out Pintu Masuk Nyata
    const fwdMainFuseGeo = new THREE.CylinderGeometry(3.1, 3.1, 7.8, 48);
    fwdMainFuseGeo.rotateX(Math.PI / 2);
    const fwdMainFuselage = new THREE.Mesh(fwdMainFuseGeo, MAT.planeWhite);
    fwdMainFuselage.position.set(0, 5.2, 1.9);
    fuseGroup.add(fwdMainFuselage);

    const aftMainFuseGeo = new THREE.CylinderGeometry(3.1, 3.1, 21.8, 48);
    aftMainFuseGeo.rotateX(Math.PI / 2);
    const aftMainFuselage = new THREE.Mesh(aftMainFuseGeo, MAT.planeWhite);
    aftMainFuselage.position.set(0, 5.2, 21.1);
    fuseGroup.add(aftMainFuselage);

    const stbdGeo = new THREE.CylinderGeometry(3.1, 3.1, 4.4, 32, 1, true, 0, Math.PI);
    stbdGeo.rotateX(Math.PI / 2);
    const stbdShell = new THREE.Mesh(stbdGeo, new THREE.MeshStandardMaterial({ color: 0xF8FAFC, roughness: 0.35, metalness: 0.15, side: THREE.DoubleSide }));
    stbdShell.position.set(0, 5.2, 8.0);
    fuseGroup.add(stbdShell);

    const crownGeo = new THREE.CylinderGeometry(3.1, 3.1, 4.4, 16, 1, true, Math.PI, Math.PI * 0.33);
    crownGeo.rotateX(Math.PI / 2);
    const crownShell = new THREE.Mesh(crownGeo, new THREE.MeshStandardMaterial({ color: 0xF8FAFC, roughness: 0.35, metalness: 0.15, side: THREE.DoubleSide }));
    crownShell.position.set(0, 5.2, 8.0);
    fuseGroup.add(crownShell);

    const bellyGeo = new THREE.CylinderGeometry(3.1, 3.1, 4.4, 16, 1, true, Math.PI * 1.67, Math.PI * 0.33);
    bellyGeo.rotateX(Math.PI / 2);
    const bellyShell = new THREE.Mesh(bellyGeo, new THREE.MeshStandardMaterial({ color: 0xF8FAFC, roughness: 0.35, metalness: 0.15, side: THREE.DoubleSide }));
    bellyShell.position.set(0, 5.2, 8.0);
    fuseGroup.add(bellyShell);

    const fwdFuseGeo = new THREE.CylinderGeometry(3.1, 2.5, 7, 48);
    fwdFuseGeo.rotateX(Math.PI / 2);
    const fwdFuselage = new THREE.Mesh(fwdFuseGeo, MAT.planeWhite);
    fwdFuselage.position.set(0, 5.1, -5.5);
    fuseGroup.add(fwdFuselage);

    const noseGeo = new THREE.CylinderGeometry(2.5, 0.8, 7, 48);
    noseGeo.rotateX(Math.PI / 2);
    const noseMesh = new THREE.Mesh(noseGeo, MAT.planeWhite);
    noseMesh.position.set(0, 4.85, -12.5);
    fuseGroup.add(noseMesh);

    const radomeCap = new THREE.Mesh(new THREE.SphereGeometry(0.82, 32, 24), MAT.metalSilver);
    radomeCap.scale.set(1.0, 0.95, 1.4);
    radomeCap.position.set(0, 4.65, -16.0);
    fuseGroup.add(radomeCap);

    // Kaca Kokpit & Visor Mask
    const visorMask = new THREE.Mesh(new THREE.BoxGeometry(2.7, 1.1, 0.08), MAT.cockpitGlass);
    visorMask.position.set(0, 6.35, -11.9);
    visorMask.rotation.x = -Math.PI / 6.2;
    fuseGroup.add(visorMask);

    // Lantai Palka Kargo Bercahaya & Roller Tracks di Dalam Pesawat
    const holdFloor = new THREE.Mesh(new THREE.BoxGeometry(4.8, 0.15, 28.0), MAT.metalDark);
    holdFloor.position.set(0, 3.82, 16.0);
    fuseGroup.add(holdFloor);

    for (let hrz = 3.0; hrz <= 28.0; hrz += 0.8) {
        const hRoller = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 4.2, 12), MAT.conveyorRoller);
        hRoller.rotateX(Math.PI / 2);
        hRoller.position.set(0, 3.91, hrz);
        fuseGroup.add(hRoller);
    }

    const holdLight = new THREE.PointLight(0xFFFBEB, 2.8, 16);
    holdLight.position.set(0, 6.2, 8.0);
    fuseGroup.add(holdLight);

    // PINTU MASUK PALKA UTAMA DENGAN ENGSEL ATAS (SWINGS UPWARD)
    const doorHinge = new THREE.Group();
    doorHinge.position.set(-2.85, 6.75, 8.0);

    const hatchPanel = new THREE.Mesh(new THREE.BoxGeometry(0.12, 2.9, 4.2), new THREE.MeshStandardMaterial({ color: 0xF8FAFC, metalness: 0.35, roughness: 0.25 }));
    hatchPanel.position.set(0, -1.45, 0);

    const hatchBorder = new THREE.Mesh(new THREE.BoxGeometry(0.16, 2.92, 4.22), MAT.yellowSafety);
    hatchBorder.position.set(0, -1.45, 0);

    doorHinge.add(hatchPanel, hatchBorder);
    planeCargoDoorHatch = doorHinge;
    fuseGroup.add(doorHinge);

    // Sayap Kiri & Kanan Boeing 777F
    const wingGeo = new THREE.BoxGeometry(42.0, 0.65, 8.5);
    const wingMesh = new THREE.Mesh(wingGeo, MAT.planeWhite);
    wingMesh.position.set(0, 4.8, 14.0);
    wingMesh.rotateY(0.12);
    plane.add(wingMesh);

    // 2 Mesin GE90 Turbofan Raksasa dengan Kipas Berputar Dinamis
    planeFanBlades = [];
    [-9.0, 9.0].forEach(ex => {
        const eng = new THREE.Mesh(new THREE.CylinderGeometry(1.6, 1.4, 5.8, 20), MAT.metalDark);
        eng.rotateX(Math.PI / 2);
        eng.position.set(ex, 2.4, 15.0);
        const engCore = new THREE.Mesh(new THREE.CylinderGeometry(1.5, 1.5, 0.4, 20), MAT.chromeIntake);
        engCore.rotateX(Math.PI / 2);
        engCore.position.set(ex, 2.4, 17.8);

        // Piringan Kipas Turbofan Berputar
        const fanDisk = new THREE.Group();
        fanDisk.position.set(ex, 2.4, 17.6);
        const spinnerCone = new THREE.Mesh(new THREE.ConeGeometry(0.35, 0.6, 16), MAT.metalDark);
        spinnerCone.rotateX(Math.PI / 2);
        fanDisk.add(spinnerCone);
        for (let b = 0; b < 14; b++) {
            const blade = new THREE.Mesh(new THREE.BoxGeometry(0.12, 1.25, 0.04), MAT.metalSilver);
            blade.rotation.z = (b / 14) * Math.PI * 2;
            fanDisk.add(blade);
        }
        plane.add(eng, engCore, fanDisk);
        planeFanBlades.push(fanDisk);
    });

    // Lampu Navigasi & Strobo Kedirgantaraan (Aviation Lights)
    const strobeWingL = new THREE.Mesh(new THREE.SphereGeometry(0.18, 8, 8), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
    strobeWingL.position.set(-21.0, 4.8, 14.0);
    const strobeWingR = new THREE.Mesh(new THREE.SphereGeometry(0.18, 8, 8), new THREE.MeshBasicMaterial({ color: 0xFFFFFF }));
    strobeWingR.position.set(21.0, 4.8, 14.0);
    const navRedL = new THREE.Mesh(new THREE.SphereGeometry(0.14, 8, 8), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
    navRedL.position.set(-21.0, 4.8, 13.6);
    const navGrnR = new THREE.Mesh(new THREE.SphereGeometry(0.14, 8, 8), new THREE.MeshBasicMaterial({ color: 0x10B981 }));
    navGrnR.position.set(21.0, 4.8, 13.6);

    const bcnTop = new THREE.Mesh(new THREE.SphereGeometry(0.22, 12, 12), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
    bcnTop.position.set(0, 8.4, 6.0);
    const bcnBtm = new THREE.Mesh(new THREE.SphereGeometry(0.22, 12, 12), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
    bcnBtm.position.set(0, 2.0, 7.5);

    plane.add(strobeWingL, strobeWingR, navRedL, navGrnR, bcnTop, bcnBtm);
    planeAviationLights = { strobeWingL, strobeWingR, bcnTop, bcnBtm };

    // Ekor Vertikal InJourney Navy
    const bTail = new THREE.Mesh(new THREE.BoxGeometry(0.35, 9.8, 7.5), MAT.planeNavy);
    bTail.position.set(0, 11.2, 40.0);
    plane.add(bTail);

    // GPU Cart Trailer (Mobile 400Hz Ground Power Unit)
    const gpuCart = new THREE.Group();
    gpuCart.position.set(-4.0, 0, -11.0);
    const gpuBody = new THREE.Mesh(new THREE.BoxGeometry(2.2, 1.2, 1.4), new THREE.MeshStandardMaterial({ color: 0x334155 }));
    gpuBody.position.y = 0.8;
    const gpuStripe = new THREE.Mesh(new THREE.BoxGeometry(2.22, 0.16, 1.42), MAT.injourneyTeal);
    gpuStripe.position.y = 0.95;
    gpuCart.add(gpuBody, gpuStripe);

    const cableCurve = new THREE.CatmullRomCurve3([
        new THREE.Vector3(0.9, 0.6, 0.6),
        new THREE.Vector3(2.0, 0.1, 1.2),
        new THREE.Vector3(3.2, 0.15, 2.5),
        new THREE.Vector3(3.8, 2.4, 4.2)
    ]);
    const cableMesh = new THREE.Mesh(new THREE.TubeGeometry(cableCurve, 20, 0.04, 8, false), MAT.yellowSafety);
    gpuCart.add(cableMesh);
    plane.add(gpuCart);

    // Wheel Chocks Karet Ganjal Roda Nose Gear
    [-0.55, 0.55].forEach(cwz => {
        const chock = new THREE.Mesh(new THREE.BoxGeometry(0.85, 0.16, 0.22), MAT.yellowSafety);
        chock.position.set(0, 0.08, -6.5 + cwz);
        plane.add(chock);
    });

    const planeSign = makeLabelSprite("LOKASI 6", "BOEING 777F FREIGHTER (PK-ACF)", "#38BDF8");
    planeSign.position.set(-4.0, 10.5, 8.0);
    plane.add(planeSign);

    return plane;
}
airsideGroup.add(createDetailedCargoPlane());

// Marka Taxiway Kuning Terang Apron (Lead-in Line ke Nose Gear 777F)
for (let tz = -25; tz <= 45; tz += 3.5) {
    const tLine = new THREE.Mesh(new THREE.PlaneGeometry(0.35, 2.2), new THREE.MeshBasicMaterial({ color: 0xFACC15 }));
    tLine.rotation.x = -Math.PI / 2;
    tLine.position.set(64.0, 0.02, tz);
    airsideGroup.add(tLine);
}

// Garis Berhenti Roda Depan (Nose Wheel Stop Line di Z = -16.0)
const stopLine = new THREE.Mesh(new THREE.BoxGeometry(4.5, 0.02, 0.35), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
stopLine.position.set(64.0, 0.025, -16.0);
airsideGroup.add(stopLine);

// Kotak Zona Merah Batas Keselamatan Pesawat (Aircraft Safety Clearance Envelope)
const envelopePts = [
    new THREE.Vector3(42.0, 0.025, -18.0),
    new THREE.Vector3(86.0, 0.025, -18.0),
    new THREE.Vector3(86.0, 0.025, 48.0),
    new THREE.Vector3(42.0, 0.025, 48.0),
    new THREE.Vector3(42.0, 0.025, -18.0)
];
const envLine = new THREE.Line(new THREE.BufferGeometry().setFromPoints(envelopePts), new THREE.LineBasicMaterial({ color: 0xEF4444 }));
airsideGroup.add(envLine);

// Lampu Tepi Taxiway Apron Biru LED (Taxiway Edge Blue Lights)
[-15, 0, 15, 30, 45].forEach(lz => {
    [40.0, 88.0].forEach(lx => {
        const fixture = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.08, 0.3, 8), MAT.metalDark);
        fixture.position.set(lx, 0.15, lz);
        const lBulb = new THREE.Mesh(new THREE.SphereGeometry(0.08, 8, 8), new THREE.MeshBasicMaterial({ color: 0x38BDF8 }));
        lBulb.position.set(lx, 0.32, lz);
        airsideGroup.add(fixture, lBulb);
    });
});

// Marka Cat Tarmac Apron & Parkir Pesawat
airsideGroup.add(makeTarmacText("STAND 12R", 6.8, 3.2, "#FACC15"));
airsideGroup.children[airsideGroup.children.length - 1].position.set(64.0, 0.025, -20.5);

airsideGroup.add(makeTarmacText("NOSE WHEEL STOP", 6.0, 2.0, "#EF4444"));
airsideGroup.children[airsideGroup.children.length - 1].position.set(64.0, 0.025, -17.2);

airsideGroup.add(makeTarmacText("INJOURNEY CARGO APRON", 10.0, 2.5, "#0CA1AF"));
airsideGroup.children[airsideGroup.children.length - 1].position.set(64.0, 0.025, 6.0);

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
airsideGroup.add(createAirportCargoTug(44.0, 36.0, -Math.PI / 2));

// ─────────────────────────────────────────────────────────────────────────────
// PERALATAN KESELAMATAN APRON SISI UDARA & MARSHALLER (AIRSIDE GSE & SAFETY)
// ─────────────────────────────────────────────────────────────────────────────
function createApronGroundSafetyEquipment() {
    const gseGroup = new THREE.Group();

    // 1. PETUGAS MARSHALLER LAPANGAN (3D FIGURE DENGAN ROMPI REFLEKTIF & TONGKAT MARSHALLING)
    const marshalGroup = new THREE.Group();
    marshalGroup.position.set(64.0, 0, -13.8);

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
        [51.0, 1.0], [53.5, 7.0],
        [74.5, 1.0], [77.0, 7.0],
        [62.5, -16.0], [65.5, -16.0],
        [32.0, 16.0], [96.0, 16.0]
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

    // 5. JET BLAST DEFLECTOR FENCE
    const blastGroup = new THREE.Group();
    blastGroup.position.set(92.0, 0, 10.0);
    const blastPanel = new THREE.Mesh(new THREE.BoxGeometry(0.18, 3.2, 56.0), new THREE.MeshStandardMaterial({ color: 0x94A3B8, metalness: 0.8, roughness: 0.3 }));
    blastPanel.position.y = 1.6;
    blastPanel.rotation.z = 0.22;
    blastGroup.add(blastPanel);
    gseGroup.add(blastGroup);

    return gseGroup;
}
airsideGroup.add(createApronGroundSafetyEquipment());

// ─────────────────────────────────────────────────────────────────────────────
// MOVING TRUCK MODEL (TRUK KONTENER DETAIL DENGAN HEADLIGHT & PINTU BUKA)
// ─────────────────────────────────────────────────────────────────────────────
const truckGroup = new THREE.Group();
truckGroup.position.set(-5.0, 0, -65.0);

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

// Pintu Belakang Trailer Truk yang Berengsel
const tDoorL = new THREE.Mesh(new THREE.BoxGeometry(0.08, 3.4, 1.5), MAT.metalDark);
tDoorL.position.set(-1.62, 2.3, -6.4);
const tDoorR = new THREE.Mesh(new THREE.BoxGeometry(0.08, 3.4, 1.5), MAT.metalDark);
tDoorR.position.set(1.62, 2.3, -6.4);

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

// Palet Kargo Bawaan di Dalam Bak Truk
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
activeCargoGroup.position.set(-5.0, 0.65, -30.0);
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
    truckStart: new THREE.Vector3(-5.0, 0, -65.0),
    truckRfid:  new THREE.Vector3(-5.0, 0, -27.0),
    truckDock:  new THREE.Vector3(-5.0, 0, -35.75),
    
    cargoUnload: new THREE.Vector3(-5.0, 0.65, -30.0),
    cargoXrayIn: new THREE.Vector3(-5.0, 0.65, -21.0),
    cargoXrayOut: new THREE.Vector3(-5.0, 0.65, -16.5),
    
    cargoQuarantineTable: new THREE.Vector3(24.5, 1.05, -16.5),
    cargoStorageCenter: new THREE.Vector3(0.0, 0.65, 8.0),
    cargoScalePlatform: new THREE.Vector3(35.0, 0.65, 8.0),
    cargoHighLoaderUp: new THREE.Vector3(53.0, 3.85, 8.0),
    cargoPlaneHold: new THREE.Vector3(64.0, 3.85, 8.0)
};

// ─────────────────────────────────────────────────────────────────────────────
// EKSEKUSI SIMULASI LIVE
// ─────────────────────────────────────────────────────────────────────────────
function startLiveSimulation() {
    activeCargoData.name = document.getElementById('cargo_name').value || 'Kargo Intermodal';
    activeCargoData.commodity = document.getElementById('commodity_type').value;
    activeCargoData.qty = parseInt(document.getElementById('cargo_quantity').value) || 40;
    activeCargoData.weight = parseFloat(document.getElementById('cargo_weight').value) || 350.0;
    activeCargoData.maxWeight = parseFloat(document.getElementById('max_weight').value) || 1588.0;
    activeCargoData.xrayResult = document.querySelector('input[name="xray_choice"]:checked')?.value || 'CLEARED';
    activeCargoData.truckPlate = document.getElementById('truck_plate').value || 'B 9482 KXA';
    activeCargoData.driver = document.getElementById('driver_name').value || 'Suryadi Pratama';
    activeCargoData.awb = '126-' + Math.floor(10000000 + Math.random() * 90000000);

    // Update Top FIDS Billboard
    document.getElementById('hud-awb-code').textContent = `GA-777F • ${activeCargoData.awb}`;
    document.getElementById('hud-cargo-title').textContent = `${activeCargoData.name} (${activeCargoData.weight} kg)`;
    document.getElementById('telemetry-scale').textContent = `${activeCargoData.weight} kg`;

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

    document.getElementById('btn-start-text').textContent = 'ULANGI SIMULASI LIVE';
    document.getElementById('btn-pause').innerHTML = '<i class="fa-solid fa-pause text-[10px]"></i> <span>Pause</span>';

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

    // Reset Truk
    truckGroup.position.set(-5.0, 0, -65.0);
    truckBedPallet.visible = true;
    truckBedBox.visible = true;
    tDoorL.rotation.y = 0;
    tDoorR.rotation.y = 0;

    // Reset Kargo
    activeCargoGroup.visible = false;
    activeCargoGroup.position.copy(WAYPOINTS.cargoUnload);
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
    if (planeCargoDoorHatch) planeCargoDoorHatch.rotation.z = 0;

    // Reset Lights
    redAlarmStrobe.intensity = 0;
    xrayScanLight.intensity = 0;
    xrBeaconMat.color.setHex(0x10B981);
    document.getElementById('telemetry-door').textContent = 'STANDBY';
    document.getElementById('telemetry-elevator').textContent = '0.55 m';

    if (fullReset) {
        updateStepperUI(1);
        document.getElementById('sim-progress-pct').textContent = '0%';
        document.getElementById('btn-start-text').textContent = 'JALANKAN SIMULASI LIVE';
        document.getElementById('hud-stage-name').textContent = 'STANDBY';
        document.getElementById('hud-cargo-title').textContent = 'Siap menerima instruksi jalankan simulasi.';
        addSimLog('🔄 Seluruh posisi peralatan terminal di-reset ke awal.');
    }
    if (typeof updateTimelineHUD === 'function') updateTimelineHUD();
}

// ─────────────────────────────────────────────────────────────────────────────
// KAMERA PRESETS DENGAN TRANSISI HALUS (SMOOTH LERP)
// ─────────────────────────────────────────────────────────────────────────────
const targetCamPos = new THREE.Vector3(0, 48, 55);
const targetCamLook = new THREE.Vector3(0, 2, -10);

function setCameraView(mode) {
    simCameraMode = mode;
    ['cam-follow', 'cam-iso', 'cam-dock', 'cam-xray', 'cam-scale', 'cam-plane'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('active');
    });
    const cur = document.getElementById('cam-' + mode);
    if (cur) cur.classList.add('active');

    if (mode === 'iso') {
        smoothCam(0, 48, 55, 0, 2, -10);
    } else if (mode === 'dock') {
        smoothCam(-5, 14, -45, -5, 2, -32);
    } else if (mode === 'xray') {
        smoothCam(8, 16, -2, 8, 2, -16.5);
    } else if (mode === 'scale') {
        smoothCam(35, 14, 26, 35, 2, 8);
    } else if (mode === 'plane') {
        smoothCam(48, 10, 24, 60, 4.2, 8);
    }
}

function resetCameraAngle() {
    setCameraView('iso');
}

function smoothCam(px, py, pz, tx, ty, tz) {
    targetCamPos.set(px, py, pz);
    targetCamLook.set(tx, ty, tz);
}

function jumpToProcessStep(stepNum) {
    if (!activeCargoData.name) {
        activeCargoData.name = document.getElementById('cargo_name')?.value || 'Kargo Intermodal';
        activeCargoData.commodity = document.getElementById('commodity_type')?.value || 'GENERAL_CARGO';
        activeCargoData.qty = parseInt(document.getElementById('cargo_quantity')?.value) || 40;
        activeCargoData.weight = parseFloat(document.getElementById('cargo_weight')?.value) || 350.0;
        activeCargoData.maxWeight = parseFloat(document.getElementById('max_weight')?.value) || 1588.0;
        activeCargoData.xrayResult = document.querySelector('input[name="xray_choice"]:checked')?.value || 'CLEARED';
        activeCargoData.truckPlate = document.getElementById('truck_plate')?.value || 'B 9482 KXA';
        activeCargoData.driver = document.getElementById('driver_name')?.value || 'Suryadi Pratama';
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
        truckGroup.position.set(-5.0, 0, -65.0);
        truckBedPallet.visible = true;
        truckBedBox.visible = true;
        tDoorL.rotation.y = 0;
        tDoorR.rotation.y = 0;
        activeCargoGroup.visible = false;
        setCameraView('dock');
        addSimLog(`⏩ [JUMP] Melompat ke Tahap 1: Truk Mengantar Kargo.`);
    } else if (stepNum === 2) {
        truckGroup.position.set(-5.0, 0, -27.0);
        truckBedPallet.visible = true;
        truckBedBox.visible = true;
        tDoorL.rotation.y = 0;
        tDoorR.rotation.y = 0;
        activeCargoGroup.visible = false;
        setCameraView('dock');
        addSimLog(`⏩ [JUMP] Melompat ke Tahap 2: Kedatangan & Scan RFID.`);
    } else if (stepNum === 3) {
        truckGroup.position.set(-5.0, 0, -35.75);
        truckBedPallet.visible = false;
        truckBedBox.visible = false;
        tDoorL.rotation.y = -Math.PI * 0.48;
        tDoorR.rotation.y = Math.PI * 0.48;
        activeCargoGroup.visible = true;
        activeCargoGroup.position.set(-5.0, 0.65, -30.0);
        palWoodGroup.visible = true;
        palUldGroup.visible = false;
        setCameraView('xray');
        addSimLog(`⏩ [JUMP] Melompat ke Tahap 3: Pemeriksaan X-Ray AVSEC.`);
    } else if (stepNum === 4) {
        truckGroup.position.set(-5.0, 0, -35.75);
        truckBedPallet.visible = false;
        truckBedBox.visible = false;
        tDoorL.rotation.y = -Math.PI * 0.48;
        tDoorR.rotation.y = Math.PI * 0.48;
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
        truckGroup.position.set(-5.0, 0, -35.75);
        truckBedPallet.visible = false;
        truckBedBox.visible = false;
        tDoorL.rotation.y = -Math.PI * 0.48;
        tDoorR.rotation.y = Math.PI * 0.48;
        activeCargoGroup.visible = true;
        activeCargoGroup.position.set(0.0, 0.65, 8.0);
        palWoodGroup.visible = false;
        palUldGroup.visible = true;
        updateScaleDisplayTexture(0, activeCargoData.maxWeight, 'WEIGHING');
        setCameraView('scale');
        addSimLog(`⏩ [JUMP] Melompat ke Tahap 5: Penimbangan ULD Lantai 15T.`);
    } else if (stepNum === 6) {
        truckGroup.position.set(-5.0, 0, -35.75);
        truckBedPallet.visible = false;
        truckBedBox.visible = false;
        tDoorL.rotation.y = -Math.PI * 0.48;
        tDoorR.rotation.y = Math.PI * 0.48;
        activeCargoGroup.visible = true;
        activeCargoGroup.position.set(35.0, 0.65, 8.0);
        palWoodGroup.visible = false;
        palUldGroup.visible = true;
        airsideShutter.position.y = 5.2;
        updateScaleDisplayTexture(activeCargoData.weight, activeCargoData.maxWeight, 'PASS');
        setCameraView('plane');
        addSimLog(`⏩ [JUMP] Melompat ke Tahap 6: Elevasi High-Loader & Muat Boeing 777F.`);
    }
    updateStepperUI(stepNum);
    updateTimelineHUD();
    document.getElementById('btn-start-text').textContent = 'ULANGI SIMULASI LIVE';
    document.getElementById('btn-pause').innerHTML = '<i class="fa-solid fa-pause text-[10px]"></i> <span>Pause</span>';
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
        'Tahap 1: Inbound Truk ke Gerbang',
        'Tahap 2: Scan RFID & Docking Bay 2',
        'Tahap 3: Pemeriksaan X-Ray AVSEC',
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

    // Kamera Lerp Halus
    if (simCameraMode !== 'follow') {
        camera.position.lerp(targetCamPos, dt * 3.5);
        controls.target.lerp(targetCamLook, dt * 3.5);
    }

    // Putar Roller Konveyor saat kargo sedang bergerak di atasnya
    if (simActive && !isSimPaused && simPhase >= 3 && simPhase <= 6) {
        animatedRollers.forEach(r => {
            r.mesh.rotation[r.axis] += dt * 6.5;
        });
    }

    // Putar Sirine Karantina Saat Bahaya
    if (sirenRotMesh && redAlarmStrobe.intensity > 0) {
        sirenRotMesh.rotation.y += dt * 12.0;
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
    // FASE 1: TRUK MENGANTAR BARANG KE GERBANG RFID TERMINAL
    // ═════════════════════════════════════════════════════════════════════════
    if (simPhase === 1) {
        document.getElementById('hud-stage-name').innerHTML = 'TAHAP 1: TRUK MENGANTAR KARGO <span class="text-[8px] px-1.5 py-0.5 rounded bg-teal-500/30 text-teal-200 font-extrabold">INBOUND</span>';
        simProgress += dt * 0.26;

        const easedT = easeInOutCubic(Math.min(1.0, simProgress));
        const curZ = THREE.MathUtils.lerp(-65.0, -27.0, easedT);
        truckGroup.position.z = curZ;

        const currentSpeed = (1.0 - Math.abs(easedT - 0.5) * 1.4);
        truckWheels.forEach(w => { w.rotation.x += dt * 10.0 * Math.max(0.25, currentSpeed); });

        if (simCameraMode === 'follow') {
            camera.position.lerp(new THREE.Vector3(-14.0, 7.5, curZ - 14.0), dt * 4.0);
            controls.target.lerp(new THREE.Vector3(-5.0, 2.2, curZ + 3.0), dt * 4.0);
        }

        if (simProgress >= 1.0) {
            simPhase = 2;
            simProgress = 0;
            updateStepperUI(2);
            playSound('beep');
            addSimLog(`📡 [RFID GATE] Truk ${activeCargoData.truckPlate} tiba di Gerbang AIDC. Membaca Tag GS1 SSCC-18...`);
            showMilestone('RFID TAG TERDETEKSI', `Plat: ${activeCargoData.truckPlate} • Tag SSCC Valid • Inbound Verified`);
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FASE 2: SCAN RFID & TRUK MUNDUR PARKIR KE DOCK BONGKAR (BAY 2)
    // ═════════════════════════════════════════════════════════════════════════
    else if (simPhase === 2) {
        document.getElementById('hud-stage-name').innerHTML = 'TAHAP 2: VALIDASI RFID & DOCKING <span class="text-[8px] px-1.5 py-0.5 rounded bg-emerald-500/30 text-emerald-200 font-extrabold">VERIFIED</span>';
        simProgress += dt * 0.32;

        if (simProgress < 0.35) {
            // Pulse wave pemindai nirkabel RFID
            const waveT = simProgress / 0.35;
            rfidWaveMesh.material.opacity = Math.sin(waveT * Math.PI * 4) * 0.85;
            rfidWaveMesh.scale.setScalar(1 + (waveT * 3.5) % 2.5);
        } else {
            rfidWaveMesh.material.opacity = 0;
            const dockT = (simProgress - 0.35) / 0.65;
            const easedDock = easeInOutQuad(Math.min(1.0, dockT));
            truckGroup.position.z = THREE.MathUtils.lerp(-27.0, -35.75, easedDock);
            truckWheels.forEach(w => { w.rotation.x -= dt * 6.5; });

            // Pintu belakang trailer truk terbuka mulus saat mulai merapat ke dok
            if (dockT > 0.65) {
                const doorT = easeOutQuad((dockT - 0.65) / 0.35);
                tDoorL.rotation.y = THREE.MathUtils.lerp(0, -Math.PI * 0.48, doorT);
                tDoorR.rotation.y = THREE.MathUtils.lerp(0, Math.PI * 0.48, doorT);
            }
        }

        if (simCameraMode === 'follow') {
            camera.position.lerp(new THREE.Vector3(8.0, 9.5, -26.0), dt * 3.5);
            controls.target.lerp(new THREE.Vector3(-5.0, 2.5, -32.5), dt * 3.5);
        }

        if (simProgress >= 1.0) {
            tDoorL.rotation.y = -Math.PI * 0.48;
            tDoorR.rotation.y = Math.PI * 0.48;

            truckBedPallet.visible = false;
            truckBedBox.visible = false;
            activeCargoGroup.visible = true;
            activeCargoGroup.position.set(-5.0, 0.65, -30.0);

            simPhase = 3;
            simProgress = 0;
            updateStepperUI(3);
            addSimLog(`📦 [UNLOAD] Kargo diturunkan dari truk ke jalur konveyor menuju Mesin X-Ray AVSEC.`);
            showMilestone('BONGKAR KARGO', `Kargo diturunkan ke konveyor menuju Mesin X-Ray.`);
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FASE 3: PEMERIKSAAN DUAL-VIEW X-RAY SCREENING (LINE 2)
    // ═════════════════════════════════════════════════════════════════════════
    else if (simPhase === 3) {
        document.getElementById('hud-stage-name').innerHTML = 'TAHAP 3: PEMERIKSAAN X-RAY AVSEC <span class="text-[8px] px-1.5 py-0.5 rounded bg-amber-500/30 text-amber-200 font-extrabold">SCANNING</span>';
        simProgress += dt * 0.28;

        const easedZ = THREE.MathUtils.lerp(-30.0, -16.5, easeInOutQuad(Math.min(1.0, simProgress)));
        activeCargoGroup.position.z = easedZ;

        // Laser scan beam dan lampu terowongan pemindai
        if (easedZ >= -21.5 && easedZ <= -16.8) {
            xrayScanLight.intensity = 3.6 + Math.sin(Date.now() * 0.035) * 2.2;
            xrBeaconMat.color.setHex(activeCargoData.xrayResult === 'SUSPECT' ? 0xEF4444 : 0x10B981);
        } else {
            xrayScanLight.intensity = 0;
        }

        if (simCameraMode === 'follow') {
            camera.position.lerp(new THREE.Vector3(4.5, 6.2, easedZ + 5.5), dt * 4.0);
            controls.target.lerp(new THREE.Vector3(-5.0, 1.6, easedZ), dt * 4.0);
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
                showMilestone('X-RAY CLEARED', 'Kargo aman dan diterbitkan CSD. Meluncur ke Central Storage.');
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
            simProgress += dt * 0.22;

            if (simProgress < 0.22) {
                const pushT = Math.sin((simProgress / 0.22) * Math.PI);
                divArm.position.x = 0.6 + pushT * 1.8;
                if (simProgress > 0.04 && simProgress < 0.08) playSound('hydraulic');
            } else {
                divArm.position.x = 0.6;
            }

            const curX = THREE.MathUtils.lerp(-5.0, 24.5, easeInOutCubic(Math.min(1.0, simProgress)));
            activeCargoGroup.position.x = curX;
            activeCargoGroup.position.z = -16.5;

            redAlarmStrobe.intensity = (Math.sin(Date.now() * 0.02) > 0) ? 6.5 : 0.8;

            if (simCameraMode === 'follow') {
                camera.position.lerp(new THREE.Vector3(curX - 7.0, 7.5, -7.5), dt * 4.0);
                controls.target.lerp(new THREE.Vector3(curX, 1.8, -16.5), dt * 4.0);
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
            simProgress += dt * 0.25;

            if (simProgress < 0.45) {
                const subT = simProgress / 0.45;
                activeCargoGroup.position.z = THREE.MathUtils.lerp(-16.5, 0.0, easeInOutQuad(subT));
                activeCargoGroup.position.x = -5.0;
            } else if (simProgress < 0.75) {
                const subT = (simProgress - 0.45) / 0.30;
                activeCargoGroup.position.x = THREE.MathUtils.lerp(-5.0, 0.0, easeInOutQuad(subT));
                activeCargoGroup.position.z = THREE.MathUtils.lerp(0.0, 8.0, easeInOutQuad(subT));
            } else {
                activeCargoGroup.position.set(0.0, 0.65, 8.0);
                const buildT = (simProgress - 0.75) / 0.25;
                if (buildT > 0.5) {
                    palWoodGroup.visible = false;
                    palUldGroup.visible = true;
                }
            }

            if (simCameraMode === 'follow') {
                camera.position.lerp(new THREE.Vector3(activeCargoGroup.position.x + 8.5, 8.5, activeCargoGroup.position.z + 9.5), dt * 3.5);
                controls.target.lerp(new THREE.Vector3(activeCargoGroup.position.x, 1.6, activeCargoGroup.position.z), dt * 3.5);
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
        simProgress += dt * 0.26;

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

        if (simCameraMode === 'follow') {
            camera.position.lerp(new THREE.Vector3(curX - 7.5, 7.5, 17.5), dt * 4.0);
            controls.target.lerp(new THREE.Vector3(curX, 1.8, 8.0), dt * 4.0);
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
        simProgress += dt * 0.17;

        if (simProgress < 0.28) {
            // Sub-tahap 6A: Meluncur dari timbangan (35.0) ke platform High-Loader (53.0)
            const subT = simProgress / 0.28;
            activeCargoGroup.position.x = THREE.MathUtils.lerp(35.0, 53.0, easeInOutQuad(subT));
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
            // Sub-tahap 6C: Kontainer meluncur dari High-Loader (53.0) masuk ke palka Boeing 777F (64.0)
            const subT = (simProgress - 0.68) / 0.32;
            activeCargoGroup.position.x = THREE.MathUtils.lerp(53.0, 64.0, easeInOutQuad(subT));
            activeCargoGroup.position.y = 3.95;
            document.getElementById('telemetry-door').textContent = 'HOLD TRANSFER (' + Math.round(subT * 100) + '%)';
        }

        if (simCameraMode === 'follow') {
            camera.position.lerp(new THREE.Vector3(44.0, 8.5, 20.0), dt * 3.8);
            controls.target.lerp(new THREE.Vector3(58.0, 3.85, 8.0), dt * 3.8);
        }

        if (simProgress >= 1.0) {
            simActive = false;
            if (planeCargoDoorHatch) planeCargoDoorHatch.rotation.z = 0; // Pintu palka menutup rapat
            document.getElementById('telemetry-door').textContent = 'LOCKED & DEPARTED';
            document.getElementById('telemetry-door').className = 'font-black text-emerald-400 font-mono';
            updateStepperUI(7);
            document.getElementById('sim-progress-pct').textContent = '100%';
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
