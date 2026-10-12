<?php
// layout_3d.php
// Denah 3D Terminal Kargo Intermodal — Sesuai Kondisi Operasional Lapangan Nyata
// Properti Realistis, Ruang Karantina Fisik Lengkap, Animasi Kargo Tidak Lolos X-Ray, & Bebas Pusing
require_once __DIR__ . '/config/database.php';
requireAuth();

$user = getCurrentUser();
$pageTitle = 'Denah 3D Terminal Kargo — Sesuai Lapangan & Blueprint';
$bodyClass = 'bg-[#08121E] text-white';
include __DIR__ . '/includes/header.php';
?>

<style>
    body { background: #08121E !important; overflow-x: hidden; }
    main { padding: 0 !important; }

    #sub-toolbar {
        position: fixed;
        top: 64px;
        left: 0;
        right: 0;
        height: 48px;
        z-index: 40;
        background: rgba(8, 18, 38, 0.96);
        border-bottom: 1px solid rgba(12, 161, 175, 0.28);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    }

    #canvas-container {
        position: relative;
        width: 100%;
        height: calc(100vh - 112px);
        overflow: hidden;
        background: #08121E;
        margin-top: 48px;
    }
    #three-canvas {
        width: 100%;
        height: 100%;
        display: block;
        cursor: grab;
    }
    #three-canvas:active { cursor: grabbing; }

    /* Glass Panels */
    .glass-panel {
        background: rgba(8, 18, 38, 0.88);
        border: 1px solid rgba(12, 161, 175, 0.28);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.55);
    }

    /* Step Navigation Chips */
    .step-chip {
        background: rgba(13, 28, 66, 0.7);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: rgba(255, 255, 255, 0.85);
        padding: 7px 14px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .step-chip:hover {
        background: rgba(12, 161, 175, 0.25);
        border-color: #0CA1AF;
        color: #fff;
        transform: translateY(-1px);
    }
    .step-chip.active {
        background: linear-gradient(135deg, #0CA1AF 0%, #087F8A 100%);
        border-color: #04AFBF;
        color: #fff;
        box-shadow: 0 4px 14px rgba(12, 161, 175, 0.4);
    }

    /* Action Buttons */
    .ctrl-btn {
        padding: 6px 12px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.16);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .ctrl-btn:hover {
        background: #0CA1AF;
        border-color: #04AFBF;
    }
    .ctrl-btn.active {
        background: linear-gradient(135deg, #0CA1AF 0%, #087F8A 100%);
        border-color: #04AFBF;
    }

    /* Live Activity Log */
    .log-entry {
        font-family: 'JetBrains Mono', monospace;
        font-size: 10px;
        line-height: 1.4;
        padding: 4px 8px;
        border-left: 2px solid #0CA1AF;
        background: rgba(12, 161, 175, 0.08);
        border-radius: 0 4px 4px 0;
        animation: logAnim 0.3s ease;
    }
    .log-entry.alert {
        border-left-color: #EF4444;
        background: rgba(239, 68, 68, 0.15);
        color: #FCA5A5;
    }
    @keyframes logAnim {
        from { opacity: 0; transform: translateX(-5px); }
        to { opacity: 1; transform: translateX(0); }
    }

    /* Loading Overlay */
    #loading-screen {
        position: absolute;
        inset: 0;
        background: #08121E;
        z-index: 100;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: opacity 0.5s ease;
    }
    .spinner {
        width: 50px;
        height: 50px;
        border: 4px solid rgba(12, 161, 175, 0.2);
        border-top-color: #0CA1AF;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Tooltip */
    #hover-tooltip {
        position: absolute;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.15s ease;
        z-index: 60;
    }
</style>

<!-- SUB-TOOLBAR: 8 TAHAP ALUR OPERASIONAL (LEGA, UTUH, BEBAS TABRAKAN) -->
<div id="sub-toolbar">
    <div class="flex items-center justify-center gap-1.5 sm:gap-2 overflow-x-auto min-w-0 w-full py-1 px-2" style="scrollbar-width: none; -ms-overflow-style: none;">
        <button class="step-chip active flex-shrink-0" id="chip-overview" onclick="goToStep('overview')">
            <i class="fa-solid fa-map text-xs text-teal-300"></i>
            <span>Overview Denah</span>
        </button>
        <button class="step-chip flex-shrink-0" id="chip-dock" onclick="goToStep('dock')">
            <i class="fa-solid fa-truck text-xs text-blue-400"></i>
            <span>1. Truck Docks</span>
        </button>
        <button class="step-chip flex-shrink-0" id="chip-rfid" onclick="goToStep('rfid')">
            <i class="fa-solid fa-wifi text-xs text-teal-300"></i>
            <span>2. Scan RFID</span>
        </button>
        <button class="step-chip flex-shrink-0" id="chip-xray" onclick="goToStep('xray')">
            <i class="fa-solid fa-shield-halved text-xs text-amber-400"></i>
            <span>3. X-Ray AVSEC</span>
        </button>
        <button class="step-chip flex-shrink-0" id="chip-quarantine" onclick="goToStep('quarantine')">
            <i class="fa-solid fa-triangle-exclamation text-xs text-rose-400"></i>
            <span>3B. Ruang Karantina</span>
        </button>
        <button class="step-chip flex-shrink-0" id="chip-storage" onclick="goToStep('storage')">
            <i class="fa-solid fa-boxes-stacked text-xs text-emerald-400"></i>
            <span>4. Storage &amp; WS</span>
        </button>
        <button class="step-chip flex-shrink-0" id="chip-scale" onclick="goToStep('scale')">
            <i class="fa-solid fa-scale-balanced text-xs text-purple-400"></i>
            <span>5. Timbangan ULD</span>
        </button>
        <button class="step-chip flex-shrink-0" id="chip-loading" onclick="goToStep('loading')">
            <i class="fa-solid fa-plane-departure text-xs text-sky-400"></i>
            <span>6. Pemuatan Pesawat</span>
        </button>
    </div>
</div>

<div id="canvas-container">
    
    <!-- 3D WebGL Canvas -->
    <canvas id="three-canvas"></canvas>

    <!-- FLOATING CAMERA PRESET CONTROLS (Top Right of 3D Canvas, Standalone Glass Widget) -->
    <div class="absolute top-3 right-4 z-20 flex items-center gap-1.5 p-1.5 rounded-xl glass-panel shadow-2xl">
        <button onclick="setViewPreset('topdown')" id="btn-topdown" class="ctrl-btn" title="Tampak Atas 2D (Sesuai Blueprint)">
            <i class="fa-solid fa-arrows-to-dot text-xs"></i>
            <span class="hidden sm:inline text-xs">Tampak Atas 2D</span>
        </button>
        <button onclick="setViewPreset('isometric')" id="btn-isometric" class="ctrl-btn active" title="3D Isometrik Sinematik">
            <i class="fa-solid fa-cube text-xs"></i>
            <span class="hidden sm:inline text-xs">3D Isometrik</span>
        </button>
        <button onclick="toggleFollowCamera()" id="btn-follow-cam" class="ctrl-btn" title="Kamera Otomatis Mengikuti Pergerakan Kargo Secara Real-Time">
            <i class="fa-solid fa-video text-teal-300 text-xs"></i>
            <span class="hidden sm:inline text-xs">Ikuti Kargo</span>
        </button>
        <button onclick="toggleTourMode()" id="btn-tour" class="ctrl-btn" title="Tur Otomatis Mengikuti Alur Kargo Secara Halus">
            <i class="fa-solid fa-person-walking-arrow-right text-teal-300 text-xs"></i>
            <span class="hidden sm:inline text-xs">Tur Alur Halus</span>
        </button>
        <button onclick="resetCamera()" class="ctrl-btn" title="Reset Kamera">
            <i class="fa-solid fa-house text-slate-300 text-xs"></i>
        </button>
    </div>

    <!-- LOADING SCREEN -->
    <div id="loading-screen">
        <div class="spinner"></div>
        <p class="mt-4 font-bold text-sm tracking-wider text-teal-300 uppercase">Memuat Arsitektur Lapangan Terminal Kargo...</p>
        <p class="text-xs text-white/50 mt-1">Mengonfigurasi Ruang Karantina, X-Ray Lines, Conveyor ULD &amp; Pemuatan Pesawat</p>
    </div>

    <!-- BOTTOM LEFT: SIMULATION CONTROLS & STAGE DETAILS PANEL -->
    <div class="absolute bottom-5 left-5 z-30 flex flex-col gap-3 max-w-sm w-full max-h-[calc(100vh-130px)] overflow-y-auto pr-1" style="scrollbar-width: thin; scrollbar-color: rgba(12, 161, 175, 0.4) transparent;">
        
        <!-- CARD 1: SIMULASI ALUR KARGO REAL-TIME -->
        <div class="glass-panel p-3.5 rounded-2xl flex-shrink-0 transition-all duration-300">
            <div class="flex items-center justify-between pb-1.5 border-b border-white/10 cursor-pointer select-none" onclick="toggleSimCard()" title="Klik untuk Sembunyikan/Tampilkan Panel Simulasi">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-xs font-black tracking-wider uppercase text-teal-300">Simulasi Alur Kargo</span>
                </div>
                <div class="flex items-center gap-1.5" onclick="event.stopPropagation()">
                    <span id="badge-flow-stage" class="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-500/20 text-teal-300 border border-teal-500/40">
                        TAHAP 1/6: DOCK 2
                    </span>
                    <button onclick="toggleSimCard()" id="btn-toggle-sim" class="text-white/60 hover:text-white p-1 rounded hover:bg-white/10 transition-colors" title="Sembunyikan / Tampilkan Panel Simulasi">
                        <i class="fa-solid fa-chevron-up text-xs transition-transform duration-300" id="icon-toggle-sim"></i>
                    </button>
                </div>
            </div>

            <!-- Collapsible Body for Card 1 -->
            <div id="sim-collapsible-body" class="transition-all duration-300 overflow-hidden" style="max-height: 500px; opacity: 1;">
                <!-- Controls (Play, Reset, Step) -->
                <div class="flex items-center justify-between gap-3 mt-2.5">
                    <div class="flex items-center gap-1.5">
                        <button onclick="toggleFlow()" id="btn-flow-play" class="w-8 h-8 rounded-lg bg-[#0CA1AF] hover:bg-[#087F8A] text-white flex items-center justify-center text-xs font-bold transition-all" title="Play/Pause Aliran Kargo">
                            <i class="fa-solid fa-pause" id="icon-flow-play"></i>
                        </button>
                        <button onclick="restartFlow()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-xs font-bold transition-all" title="Ulangi dari Truk Datang">
                            <i class="fa-solid fa-backward-step"></i>
                        </button>
                        <button onclick="stepNextFlow()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-xs font-bold transition-all" title="Langkah Berikutnya">
                            <i class="fa-solid fa-forward-step"></i>
                        </button>
                    </div>

                    <!-- Speed Selection -->
                    <div class="flex items-center gap-1 bg-white/5 p-1 rounded-lg border border-white/10 text-[10px]">
                        <button onclick="setSpeed(1)" id="spd-1" class="px-2 py-0.5 rounded font-bold bg-[#0CA1AF] text-white">1x</button>
                        <button onclick="setSpeed(2)" id="spd-2" class="px-2 py-0.5 rounded font-bold text-white/70 hover:text-white">2x</button>
                        <button onclick="setSpeed(4)" id="spd-4" class="px-2 py-0.5 rounded font-bold text-white/70 hover:text-white">4x</button>
                    </div>
                </div>

                <!-- SCENARIO TOGGLE BUTTONS (Normal vs Suspect) -->
                <div class="mt-2.5 pt-2 border-t border-white/10 flex items-center gap-1.5">
                    <button onclick="setScenario('normal')" id="btn-scen-normal" class="flex-1 py-1.5 px-2 rounded-lg text-[10px] font-bold bg-[#0CA1AF] text-white flex items-center justify-center gap-1 transition-all shadow-sm">
                        <i class="fa-solid fa-circle-check"></i> Alur Lolos (Normal)
                    </button>
                    <button onclick="setScenario('suspect')" id="btn-scen-suspect" class="flex-1 py-1.5 px-2 rounded-lg text-[10px] font-bold bg-white/10 hover:bg-rose-900/40 text-rose-300 hover:text-white border border-rose-500/30 flex items-center justify-center gap-1 transition-all">
                        <i class="fa-solid fa-triangle-exclamation"></i> Skenario SUSPECT
                    </button>
                </div>

                <!-- DIRECT 6-STAGE STEP BUTTONS -->
                <div class="mt-2.5 pt-2 border-t border-white/10">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[9.5px] font-bold text-teal-300 uppercase tracking-wider">Lompat ke Tahap Alur:</span>
                        <button onclick="toggleFollowCamera()" id="btn-follow-cargo-card" class="text-[9px] px-2 py-0.5 rounded bg-teal-500/20 text-teal-300 border border-teal-500/30 hover:bg-teal-500/40 font-bold transition-all">
                            <i class="fa-solid fa-video mr-1 text-[8px]"></i> Ikuti Kargo
                        </button>
                    </div>
                    <div class="grid grid-cols-3 gap-1 text-[9.5px]">
                        <button onclick="jumpToProcessStep(1)" class="p-1 rounded bg-white/5 hover:bg-blue-600/30 text-white border border-white/10 hover:border-blue-400 font-bold text-center transition-all truncate" title="Tahap 1: Bongkar dari Truk">1. Turun Truk</button>
                        <button onclick="jumpToProcessStep(2)" class="p-1 rounded bg-white/5 hover:bg-teal-600/30 text-white border border-white/10 hover:border-teal-400 font-bold text-center transition-all truncate" title="Tahap 2: Scan RFID Portal">2. Scan RFID</button>
                        <button onclick="jumpToProcessStep(3)" class="p-1 rounded bg-white/5 hover:bg-amber-600/30 text-white border border-white/10 hover:border-amber-400 font-bold text-center transition-all truncate" title="Tahap 3: X-Ray AVSEC">3. X-Ray</button>
                        <button onclick="jumpToProcessStep(4)" class="p-1 rounded bg-white/5 hover:bg-emerald-600/30 text-white border border-white/10 hover:border-emerald-400 font-bold text-center transition-all truncate" title="Tahap 4: Central Storage & Build-Up ULD">4. Storage</button>
                        <button onclick="jumpToProcessStep(5)" class="p-1 rounded bg-white/5 hover:bg-purple-600/30 text-white border border-white/10 hover:border-purple-400 font-bold text-center transition-all truncate" title="Tahap 5: Timbangan ULD 15T">5. Timbangan</button>
                        <button onclick="jumpToProcessStep(6)" class="p-1 rounded bg-white/5 hover:bg-sky-600/30 text-white border border-white/10 hover:border-sky-400 font-bold text-center transition-all truncate" title="Tahap 6: Loading Pesawat">6. Pesawat</button>
                    </div>
                </div>

                <!-- Flow Progress Bar -->
                <div class="mt-2.5">
                    <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                        <div id="bar-flow-progress" class="bg-gradient-to-r from-[#0CA1AF] via-[#04AFBF] to-[#10B981] h-1.5 rounded-full transition-all duration-300" style="width: 15%;"></div>
                    </div>
                    <div class="flex justify-between text-[9px] text-white/50 mt-1 font-mono">
                        <span>1. Truk</span>
                        <span>2. RFID</span>
                        <span>3. X-Ray</span>
                        <span>4. Storage</span>
                        <span>5. Timbang</span>
                        <span id="label-flow-end">6. Pesawat</span>
                    </div>
                </div>

                <!-- Event Feed -->
                <div class="mt-2.5 pt-2 border-t border-white/10">
                    <span class="text-[10px] text-white/60 font-bold uppercase tracking-wider block mb-1">Audit Trail Operasional Lapangan:</span>
                    <div id="live-log-box" class="space-y-1.5 max-h-16 overflow-y-auto pr-1 text-[10px]">
                        <div class="log-entry text-teal-300">[00:00] Truk B 9481 KGA merapat di Truck Dock 2 (TAS Verified).</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 2: PENJELASAN TAHAPAN OPERASIONAL LAPANGAN (BISA DI-HIDE / DILIPAT KE BAWAH) -->
        <div id="inspector-card" class="glass-panel p-3.5 rounded-2xl flex-shrink-0 transition-all duration-300">
            <!-- Header Bar (Selalu Muncul - Berfungsi sebagai Toggle Bar ke Bawah) -->
            <div class="flex items-center justify-between cursor-pointer select-none" onclick="toggleInspectorCard()" title="Klik untuk Sembunyikan / Tampilkan Informasi Tahapan">
                <div class="flex items-center gap-2 min-w-0 pr-2">
                    <span id="ins-stage-tag" class="text-[9px] font-black uppercase tracking-wider text-teal-300 px-2 py-0.5 rounded bg-teal-500/20 border border-teal-500/30 whitespace-nowrap flex-shrink-0">
                        OVERVIEW TERMINAL
                    </span>
                    <h4 id="ins-title-header" class="text-xs font-bold text-white truncate hidden">Denah Terpadu Terminal</h4>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0" onclick="event.stopPropagation()">
                    <button onclick="goToStep('overview')" title="Kembali ke Ringkasan" class="text-white/60 hover:text-white p-1 rounded hover:bg-white/10 transition-colors">
                        <i class="fa-solid fa-rotate-left text-xs"></i>
                    </button>
                    <button onclick="toggleInspectorCard()" id="btn-toggle-ins" title="Sembunyikan / Tampilkan ke Bawah" class="text-white/80 hover:text-white p-1 rounded hover:bg-white/10 transition-colors flex items-center justify-center w-6 h-6">
                        <i class="fa-solid fa-chevron-down text-xs transition-transform duration-300" id="icon-toggle-ins"></i>
                    </button>
                </div>
            </div>

            <!-- Collapsible Body Section (Akan Melipat Mulus Saat Di-Hide) -->
            <div id="ins-collapsible-body" class="transition-all duration-300 overflow-hidden" style="max-height: 600px; opacity: 1;">
                <h3 id="ins-title" class="text-sm font-extrabold text-white mt-2 leading-tight">Denah Terpadu Terminal Kargo Intermodal</h3>
                
                <p id="ins-desc" class="text-[11px] text-white/80 leading-relaxed mt-1 text-justify">
                    Tampilan terpadu seluruh area terminal: dari sisi darat (Landside Truck Docks), area inspeksi keamanan AVSEC, fasilitas ruang karantina kargo suspect, gudang penyimpanan &amp; perakitan ULD, timbangan lantai 15 Ton, hingga pemuatan ke pesawat di sisi udara (Airside).
                </p>

                <!-- Hardware & Software Badges -->
                <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-white/10 text-[10px]">
                    <div class="p-2 rounded-lg bg-white/5 border border-white/10">
                        <span class="text-[8px] uppercase font-bold text-teal-300 block">Layer 1: Perangkat</span>
                        <span id="ins-hw" class="font-bold text-white block mt-0.5 leading-snug">Seluruh Ekosistem Hardware Layer 1</span>
                    </div>
                    <div class="p-2 rounded-lg bg-white/5 border border-white/10">
                        <span class="text-[8px] uppercase font-bold text-blue-300 block">Layer 3: Software</span>
                        <span id="ins-sw" class="font-bold text-white block mt-0.5 leading-snug">CMS Core Integration &amp; TMS Hub</span>
                    </div>
                </div>

                <!-- Data Protocol -->
                <div class="mt-2 p-2 rounded-lg bg-teal-500/10 border border-teal-500/20 text-[10px]">
                    <span class="text-[8px] uppercase font-bold text-teal-300 block">Layer 4: Standar Data</span>
                    <span id="ins-data" class="font-mono text-emerald-300 block mt-0.5 text-[9.5px]">GS1 SSCC, IATA e-AWB, ICAO CSD, OIML R76</span>
                </div>

                <div class="mt-2.5 flex gap-2">
                    <a href="simulation.php" id="ins-sim-link" class="flex-1 text-center py-1.5 rounded-xl bg-gradient-to-r from-[#0CA1AF] to-[#087F8A] hover:from-[#04AFBF] hover:to-[#0CA1AF] text-white text-xs font-bold transition-all shadow-md">
                        <i class="fa-solid fa-play mr-1.5 text-[10px]"></i> Buka Simulator PoC
                    </a>
                    <button onclick="resetCamera()" class="px-2.5 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition-all">
                        Reset
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- HOVER TOOLTIP -->
    <div id="hover-tooltip" class="glass-panel p-2.5 rounded-xl shadow-xl pointer-events-none max-w-xs">
        <span id="tt-tag" class="text-[9px] font-black uppercase text-teal-300 block">ZONA</span>
        <h4 id="tt-name" class="text-xs font-bold text-white">-</h4>
        <p id="tt-sub" class="text-[10px] text-white/70 mt-0.5">-</p>
    </div>

    <!-- BOTTOM CENTER: REAL FIELD WORKFLOW INTERACTIVE STEPPER -->
    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-2xl glass-panel text-[11px] shadow-2xl backdrop-blur-md border border-white/10">
        <button onclick="jumpToProcessStep(1)" class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl hover:bg-white/10 transition-colors text-white font-bold" title="Tahap 1: Turun dari Truk">
            <span class="w-2.5 h-2.5 rounded-full bg-blue-500 shadow-sm shadow-blue-500/50"></span> 1. Truk
        </button>
        <i class="fa-solid fa-chevron-right text-[8px] text-white/30"></i>
        <button onclick="jumpToProcessStep(2)" class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl hover:bg-white/10 transition-colors text-white font-bold" title="Tahap 2: Scan RFID Portal">
            <span class="w-2.5 h-2.5 rounded-full bg-teal-400 shadow-sm shadow-teal-400/50"></span> 2. RFID
        </button>
        <i class="fa-solid fa-chevron-right text-[8px] text-white/30"></i>
        <button onclick="jumpToProcessStep(3)" class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl hover:bg-white/10 transition-colors text-white font-bold" title="Tahap 3: X-Ray AVSEC">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-400 shadow-sm shadow-amber-400/50"></span> 3. X-Ray
        </button>
        <i class="fa-solid fa-chevron-right text-[8px] text-white/30"></i>
        <button onclick="goToStep('quarantine')" class="flex items-center gap-1.5 px-2 py-1 rounded-xl hover:bg-white/10 transition-colors text-rose-300 font-bold" title="Tahap 3B: Ruang Karantina Suspect">
            <span class="w-2 h-2 rounded-full bg-rose-500"></span> 3B. Karantina
        </button>
        <i class="fa-solid fa-chevron-right text-[8px] text-white/30"></i>
        <button onclick="jumpToProcessStep(4)" class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl hover:bg-white/10 transition-colors text-white font-bold" title="Tahap 4: Central Storage & Build-Up ULD">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-sm shadow-emerald-500/50"></span> 4. Central Storage
        </button>
        <i class="fa-solid fa-chevron-right text-[8px] text-white/30"></i>
        <button onclick="jumpToProcessStep(5)" class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl hover:bg-white/10 transition-colors text-white font-bold" title="Tahap 5: Timbangan Lantai ULD 15T">
            <span class="w-2.5 h-2.5 rounded-full bg-purple-500 shadow-sm shadow-purple-500/50"></span> 5. Timbangan ULD
        </button>
        <i class="fa-solid fa-chevron-right text-[8px] text-white/30"></i>
        <button onclick="jumpToProcessStep(6)" class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl hover:bg-white/10 transition-colors text-white font-bold" title="Tahap 6: Cargo High-Loader & Pemuatan Boeing 777F">
            <span class="w-2.5 h-2.5 rounded-full bg-sky-400 shadow-sm shadow-sky-400/50"></span> 6. Loading Pesawat
        </button>
    </div>

</div>

<!-- Three.js + OrbitControls (CDN r128 UMD) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>

<script>
// ─────────────────────────────────────────────────────────────────────────────
// DATA STASIUN KERJA DI LAPANGAN — SUDUT PANDANG KONSISTEN (BEBAS PUSING)
// Seluruh sudut kamera diarahkan ke arah yang sama (Looking Northward)
// ─────────────────────────────────────────────────────────────────────────────
const FIELD_STATIONS = {
    overview: {
        id: 'overview',
        num: 'OVERVIEW TERMINAL',
        title: 'Denah Terpadu Terminal Kargo Intermodal',
        desc: 'Tampilan terpadu seluruh 6 alur proses terminal kargo: (1) Bongkar barang dari truk di sisi darat, (2) Pemindaian nirkabel RFID Portal, (3) Pemeriksaan keamanan X-Ray AVSEC, (4) Central Storage & Perakitan kontainer ULD, (5) Penimbangan timbangan lantai 15 Ton, hingga (6) Pemuatan ke pesawat Boeing 777F di apron sisi udara.',
        hw: 'Seluruh Ekosistem Hardware Layer 1 (Scanner, X-Ray, ETD, Scale, Loader)',
        sw: 'CMS Core Integration & TMS/TAS Hub',
        data: 'GS1 SSCC, IATA e-AWB, ICAO CSD, OIML R76, Electronic Loadsheet',
        camPos: [0, 68, 48],
        camTarget: [0, 2, -4]
    },
    dock: {
        id: 'dock',
        num: 'TAHAP 1: SISI DARAT (LANDSIDE)',
        title: '1. Bongkar Barang dari Truk (Truck Docks 1 - 3)',
        desc: 'Truk kontainer tiba di Truck Dock 2 setelah diverifikasi jadwal dan bookingnya melalui Truck Appointment System (TAS). Koli/palet kargo diturunkan dari dalam bak truk melewati dock leveler hidrolik menuju jalur penerimaan kargo terminal.',
        hw: 'Hydraulic Dock Leveler, Industrial Roller Door, RFID Gate Reader, Electric Forklift',
        sw: 'TMS / TAS (Truck Appointment System) Inbound Gate',
        data: 'Plat Nomor Truk, Slot Dock ID, Driver ID, Surat Jalan / Booking ID',
        camPos: [0, 22, -12],
        camTarget: [0, 2, -32]
    },
    rfid: {
        id: 'rfid',
        num: 'TAHAP 2: PEMERIKSAAN AWAL',
        title: '2. Gerbang Portal High-Speed RFID Scan',
        desc: 'Palet kargo dialirkan di atas konveyor melintasi Portal Gantry RFID dengan kecepatan 2.5 m/s. Antena RFID membaca tag GS1 SSCC 18-digit secara nirkabel dan seketika mencocokkannya ke database CMS untuk memvalidasi manifest & e-AWB.',
        hw: 'High-Speed RFID Tunnel Antenna, Barcode Omnidirectional Scanner, LED Status Gate',
        sw: 'CMS Inbound Ingestion Service & GS1 SSCC Validator',
        data: '(00) SSCC 18-Digit, (01) GTIN-14, (10) Batch, (17) Expiry Date',
        camPos: [-28, 18, -10],
        camTarget: [-28, 2, -27]
    },
    xray: {
        id: 'xray',
        num: 'TAHAP 3: PEMERIKSAAN KEAMANAN',
        title: '3. Mesin Dual-View X-Ray Screening (Lines 1 - 3)',
        desc: '3 Jalur terowongan X-Ray berjejer memeriksa kerapatan fisik isi kargo dari dua sudut pandang (horizontal & vertikal). Kargo yang aman mendapat status CLEARED dan diterbitkan sertifikat Cargo Security Declaration (CSD). Kargo yang dicurigai dialihkan ke Ruang Karantina.',
        hw: 'Dual-View X-Ray Screening (180x180cm), Lead Curtains, Operator Console',
        sw: 'AVSEC Core System & Cargo Security Declaration (CSD) Generator',
        data: 'Status CLEARED/SUSPECT, Density Scan Hash, CSD Digital Certificate',
        camPos: [-5, 18, 3],
        camTarget: [-5, 2, -18]
    },
    quarantine: {
        id: 'quarantine',
        num: 'TAHAP 3B: FASILITAS ISOLASI',
        title: '3B. Ruang Karantina & Uji Lanjutan ETD (Kargo Suspect)',
        desc: 'Ruang tertutup berpartisi fisik khusus kargo suspect yang tidak lolos X-Ray. Kargo diperiksa manual di meja stainless steel dan diuji usapan bahan peledak (Explosive Trace Detector / ETD). Status kargo: SUSPECT & HOLD, DILARANG KERAS ke pesawat.',
        hw: 'Explosive Trace Detector (ETD), Stainless Steel Inspection Table, Diverter Conveyor Spur, Red Strobe Alarm, Hazmat Evidence Vault',
        sw: 'AVSEC Incident Logging & Customs/Police Escalation Protocol',
        data: 'Status: SUSPECT / HOLD, Nomor e-AWB, Alasan Penolakan X-Ray, ID Petugas Pemeriksa, Sertifikat CSD: REJECTED',
        camPos: [22, 18, 4],
        camTarget: [22, 2, -20]
    },
    storage: {
        id: 'storage',
        num: 'TAHAP 4: CENTRAL STORAGE & BUILD-UP',
        title: '4. Central Storage & Perakitan Kontainer ULD',
        desc: 'Kargo yang lolos inspeksi X-Ray disimpan sementara di Central Storage, kemudian disusun dan dirakit (build-up) di area perakitan tengah ke dalam kontainer khusus pesawat (ULD AKE) dengan mematuhi Center of Gravity (CoG). Setelah siap, kontainer dialirkan langsung menuju stasiun penimbangan.',
        hw: 'Industrial Storage Racks, Central ULD Build-Up Bed, Roller Transfer Conveyor, Electric Forklift',
        sw: 'Weight & Balance Engine / CoG Calculation System',
        data: 'Nomor Kontainer ULD (AKE/PMC), Posisi Kompartemen, Center of Gravity (CoG)',
        camPos: [0, 24, 28],
        camTarget: [0, 2, 6]
    },
    scale: {
        id: 'scale',
        num: 'TAHAP 5: PENIMBANGAN SEBELUM TERBANG',
        title: '5. Smart ULD Weighing Station (Timbangan Lantai 15 Ton)',
        desc: 'Kontainer ULD AKE yang telah selesai dirakit meluncur ke atas timbangan lantai beroda rol (Smart ULD Floor Scale 15T). Sistem membaca berat aktual kotor (1,580 kg) dan membandingkannya dengan toleransi berat e-AWB (maks deviasi 0.2%). Setelah dinyatakan PASS, kontainer diizinkan meluncur ke sisi udara (Airside).',
        hw: 'Smart ULD Floor Scale (15 Ton OIML R76), Omnidirectional Roller Bed, Digital Display Tower',
        sw: 'CMS Weight Module & Tolerance Verifier (Maks deviasi 0.2%)',
        data: 'Actual Gross Weight: 1,580 kg, Tare Weight: 120 kg, Status: PASS (+0.06%)',
        camPos: [35, 18, 30],
        camTarget: [35, 2, 8]
    },
    loading: {
        id: 'loading',
        num: 'TAHAP 6: SISI UDARA (AIRSIDE APRON)',
        title: '6. Cargo High-Loader & Pemuatan ke Boeing 777F',
        desc: 'Melalui Gerbang Kargo Airside terbuka, ULD yang telah lolos verifikasi timbangan meluncur langsung ke platform Cargo High-Loader. Platform hidrolik mengangkat kontainer setinggi pintu palka pesawat (3.85m), lalu mendorong ULD masuk ke dalam kompartemen kargo utama Boeing 777F. Status penerbangan resmi terbit menjadi DEPARTED.',
        hw: 'Cargo High-Loader Transporter, Commercial Cargo Freighter (Boeing 777F / Airbus A330F), EFB Tablet',
        sw: 'CMS Flight Ops Integration & Electronic Loadsheet Release',
        data: 'Loadsheet Final, Compartment Pos (e.g. 11L, 22R), Status DEPARTED',
        camPos: [56, 18, 30],
        camTarget: [58, 4, 8]
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// SETUP SCENE, CAMERA, RENDERER
// ─────────────────────────────────────────────────────────────────────────────
const container = document.getElementById('canvas-container');
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
scene.background = new THREE.Color(0x091424);
scene.fog = new THREE.FogExp2(0x091424, 0.0075);

const camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.5, 600);
const DEFAULT_CAM_POS = new THREE.Vector3(0, 68, 48);
const DEFAULT_CAM_TARGET = new THREE.Vector3(0, 2, -4);
camera.position.copy(DEFAULT_CAM_POS);

const controls = new THREE.OrbitControls(camera, renderer.domElement);
controls.target.copy(DEFAULT_CAM_TARGET);
controls.enableDamping = true;
controls.dampingFactor = 0.05;
controls.maxPolarAngle = Math.PI / 2.08;
controls.minDistance = 8;
controls.maxDistance = 160;
controls.update();

// ─────────────────────────────────────────────────────────────────────────────
// PENCAHAYAAN (LIGHTING)
// ─────────────────────────────────────────────────────────────────────────────
const ambientLight = new THREE.AmbientLight(0xdce7f5, 0.75);
scene.add(ambientLight);

const mainSun = new THREE.DirectionalLight(0xffffff, 1.45);
mainSun.position.set(45, 80, -35);
mainSun.castShadow = true;
mainSun.shadow.mapSize.set(2048, 2048);
mainSun.shadow.camera.near = 10;
mainSun.shadow.camera.far = 220;
mainSun.shadow.camera.left = -65;
mainSun.shadow.camera.right = 65;
mainSun.shadow.camera.top = 65;
mainSun.shadow.camera.bottom = -65;
mainSun.shadow.bias = -0.0006;
scene.add(mainSun);

const fillBlue = new THREE.DirectionalLight(0x0CA1AF, 0.45);
fillBlue.position.set(-50, 40, 50);
scene.add(fillBlue);

const hallLight = new THREE.PointLight(0x38bdf8, 1.6, 80);
hallLight.position.set(0, 16, 0);
scene.add(hallLight);

// Lampu Alarm Karantina Khusus (Akan Berkedip Saat Skenario Suspect Aktif)
const quarantineAlarmLight = new THREE.PointLight(0xEF4444, 0, 25);
quarantineAlarmLight.position.set(22, 4.2, -19);
scene.add(quarantineAlarmLight);

// ─────────────────────────────────────────────────────────────────────────────
// MATERIAL REALISTIS & TAHAN SILAU
// ─────────────────────────────────────────────────────────────────────────────
const MAT = {
    floorTerminal: new THREE.MeshStandardMaterial({ color: 0xEEF2F6, roughness: 0.65, metalness: 0.08 }),
    floorTarmac: new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.95, metalness: 0.05 }),
    floorQuarantine: new THREE.MeshStandardMaterial({ color: 0xFEF2F2, roughness: 0.75 }),
    wallBuilding: new THREE.MeshStandardMaterial({ color: 0xE2E8F0, roughness: 0.7 }),
    wallGlass: new THREE.MeshStandardMaterial({ color: 0xBAE6FD, transparent: true, opacity: 0.35, roughness: 0.1 }),
    metalSilver: new THREE.MeshStandardMaterial({ color: 0xD1D5DB, metalness: 0.85, roughness: 0.28 }),
    metalDark: new THREE.MeshStandardMaterial({ color: 0x334155, metalness: 0.7, roughness: 0.4 }),
    yellowSafety: new THREE.MeshStandardMaterial({ color: 0xF59E0B, roughness: 0.5 }),
    redHazard: new THREE.MeshStandardMaterial({ color: 0xEF4444, roughness: 0.4 }),
    injourneyTeal: new THREE.MeshStandardMaterial({ color: 0x0CA1AF, metalness: 0.3, roughness: 0.4 }),
    conveyorRoller: new THREE.MeshStandardMaterial({ color: 0x94A3B8, metalness: 0.85, roughness: 0.3 }),
    palletWood: new THREE.MeshStandardMaterial({ color: 0xB45309, roughness: 0.9 }),
    cardboardBox: new THREE.MeshStandardMaterial({ color: 0xD97706, roughness: 0.9 }),
    cardboardBoxSuspect: new THREE.MeshStandardMaterial({ color: 0xDC2626, roughness: 0.5 }),
    uldAluminum: new THREE.MeshStandardMaterial({ color: 0xE2E8F0, metalness: 0.92, roughness: 0.2 }),
    uldCanvasBlue: new THREE.MeshStandardMaterial({ color: 0x1D4ED8, roughness: 0.7 }),
    truckCabBlue: new THREE.MeshStandardMaterial({ color: 0x0284C7, roughness: 0.3, metalness: 0.5 }),
    truckTire: new THREE.MeshStandardMaterial({ color: 0x111827, roughness: 0.9 }),
    planeWhite: new THREE.MeshStandardMaterial({ color: 0xF8FAFC, roughness: 0.25, metalness: 0.28, side: THREE.DoubleSide }),
    planeNavy: new THREE.MeshStandardMaterial({ color: 0x0A2540, roughness: 0.35, metalness: 0.25 }),
    cockpitGlass: new THREE.MeshStandardMaterial({ color: 0x07111E, roughness: 0.08, metalness: 0.92 }),
    chromeIntake: new THREE.MeshStandardMaterial({ color: 0xE2E8F0, metalness: 0.95, roughness: 0.12 }),
    engineCore: new THREE.MeshStandardMaterial({ color: 0x1E293B, metalness: 0.8, roughness: 0.4 }),
};

const interactiveMeshes = [];
function bindInteractive(mesh, stationKey) {
    mesh.userData = { stationKey, isInteractive: true };
    interactiveMeshes.push(mesh);
}

// ─────────────────────────────────────────────────────────────────────────────
// 3D FLOATING BILLBOARD LABELS
// ─────────────────────────────────────────────────────────────────────────────
function makeLabelSprite(text, subtext, color = '#0CA1AF') {
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 140;
    const ctx = c.getContext('2d');

    ctx.fillStyle = 'rgba(8, 18, 38, 0.92)';
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

// ─────────────────────────────────────────────────────────────────────────────
// TATA RUANG DASAR (LANTAI & DINDING PEMBAGI AREA NYATA)
// ─────────────────────────────────────────────────────────────────────────────
const layoutGroup = new THREE.Group();
scene.add(layoutGroup);

// 1. Aspal Luar (Tarmac / Apron Airside & Landside Road)
const tarmacGeo = new THREE.PlaneGeometry(160, 140);
const tarmac = new THREE.Mesh(tarmacGeo, MAT.floorTarmac);
tarmac.rotation.x = -Math.PI / 2;
tarmac.position.set(0, -0.05, 10);
tarmac.receiveShadow = true;
layoutGroup.add(tarmac);

// 2. Lantai Dalam Terminal Kargo (Indoor Floor Epoxy)
const termGeo = new THREE.PlaneGeometry(88, 74);
const termFloor = new THREE.Mesh(termGeo, MAT.floorTerminal);
termFloor.rotation.x = -Math.PI / 2;
termFloor.position.set(-6, 0.02, -8);
termFloor.receiveShadow = true;
layoutGroup.add(termFloor);

// Garis Batas Luar Gedung
const borderPts = [
    new THREE.Vector3(-50, 0.06, -45),
    new THREE.Vector3(38, 0.06, -45),
    new THREE.Vector3(38, 0.06, 29),
    new THREE.Vector3(-50, 0.06, 29),
    new THREE.Vector3(-50, 0.06, -45)
];
const borderLine = new THREE.Line(new THREE.BufferGeometry().setFromPoints(borderPts), new THREE.LineBasicMaterial({ color: 0xF59E0B }));
layoutGroup.add(borderLine);

// Dinding Pembatas Sisi Darat
const dockWall = new THREE.Mesh(new THREE.BoxGeometry(86, 4.0, 0.6), MAT.wallBuilding);
dockWall.position.set(-6, 2.0, -29);
dockWall.receiveShadow = true;
layoutGroup.add(dockWall);

// Dinding Pembatas Sisi Udara (Terbagi dengan Bukaan Gerbang Kargo Airside Terbuka Lebar)
// Bagian Utara (Z = -27 sampai Z = 2)
const airsideWallNorth = new THREE.Mesh(new THREE.BoxGeometry(0.6, 4.2, 29), MAT.wallBuilding);
airsideWallNorth.position.set(38, 2.1, -12.5);
airsideWallNorth.receiveShadow = true;
layoutGroup.add(airsideWallNorth);

// Bagian Selatan (Z = 14 sampai Z = 29)
const airsideWallSouth = new THREE.Mesh(new THREE.BoxGeometry(0.6, 4.2, 15), MAT.wallBuilding);
airsideWallSouth.position.set(38, 2.1, 21.5);
airsideWallSouth.receiveShadow = true;
layoutGroup.add(airsideWallSouth);

// PINTU GERBANG KARGO SISI UDARA (AIRSIDE CARGO BAY #1 / ROLL-UP HANGAR PORTAL)
// Lebar 12 Meter (Z = 2 sampai Z = 14) Tanpa Halangan Tembok untuk Akses Kontainer ULD ke Pesawat!
const airsideGateGroup = new THREE.Group();
airsideGateGroup.position.set(38, 0, 8);

// Kusen Rangka Baja Portal Gerbang
const gatePostLeft = new THREE.Mesh(new THREE.BoxGeometry(0.8, 4.4, 0.6), MAT.metalDark);
gatePostLeft.position.set(0, 2.2, -6.0);
const gatePostRight = new THREE.Mesh(new THREE.BoxGeometry(0.8, 4.4, 0.6), MAT.metalDark);
gatePostRight.position.set(0, 2.2, 6.0);
const gateLintel = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.8, 12.6), MAT.metalDark);
gateLintel.position.set(0, 4.4, 0);
airsideGateGroup.add(gatePostLeft, gatePostRight, gateLintel);


// Plang Peringatan Batas Ketinggian & Rambu Gerbang Airside
const gateSign = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.65, 9.5), new THREE.MeshStandardMaterial({ color: 0xF59E0B, roughness: 0.4 }));
gateSign.position.set(0.5, 4.4, 0);
airsideGateGroup.add(gateSign);

// Garis Hazard Kuning-Hitam di Lantai Ambang Gerbang
const gateFloorBorder = new THREE.Mesh(new THREE.BoxGeometry(1.2, 0.08, 12.0), new THREE.MeshBasicMaterial({ color: 0xF59E0B }));
gateFloorBorder.position.set(0, 0.05, 0);
airsideGateGroup.add(gateFloorBorder);

// Rel Konveyor Rol Penghubung Timbangan ke Apron Luar (Menembus Gerbang secara Mulus)
const apronTransferTrack = new THREE.Mesh(new THREE.BoxGeometry(14.0, 0.35, 3.4), MAT.conveyorRoller);
apronTransferTrack.position.set(5.0, 0.35, 0);
airsideGateGroup.add(apronTransferTrack);

// Pelindung Samping Rel Konveyor Apron
[-1.8, 1.8].forEach(gz => {
    const rail = new THREE.Mesh(new THREE.BoxGeometry(14.0, 0.25, 0.15), MAT.yellowSafety);
    rail.position.set(5.0, 0.55, gz);
    airsideGateGroup.add(rail);
});

layoutGroup.add(airsideGateGroup);

// ─────────────────────────────────────────────────────────────────────────────
// JARINGAN CONVEYOR UTUH TANPA CELAH (100% KONTINU DARI DOCK TRUK KE PESAWAT)
// Menghubungkan seluruh stasiun: Dock 2 -> RFID -> X-Ray -> Central Storage -> Timbangan -> Apron
// ─────────────────────────────────────────────────────────────────────────────
function createConnectingConveyorSystem() {
    const net = new THREE.Group();
    const cMat = MAT.conveyorRoller;
    const rMat = MAT.yellowSafety;

    function makeSegment(w, h, d, x, y, z, rails = true, railAxis = 'z') {
        const seg = new THREE.Group();
        const conv = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), cMat);
        conv.position.set(x, y, z);
        conv.receiveShadow = true;
        seg.add(conv);

        if (rails) {
            if (railAxis === 'x') {
                [-d / 2, d / 2].forEach(offsetZ => {
                    const r = new THREE.Mesh(new THREE.BoxGeometry(w, 0.22, 0.1), rMat);
                    r.position.set(x, y + h / 2 + 0.11, z + offsetZ);
                    seg.add(r);
                });
            } else {
                [-w / 2, w / 2].forEach(offsetX => {
                    const r = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.22, d), rMat);
                    r.position.set(x + offsetX, y + h / 2 + 0.11, z);
                    seg.add(r);
                });
            }
        }
        return seg;
    }

    // 1. DOCK 2: Ramp Penurunan dari Truk Dock 2 (Z = -36 s.d. Z = -31 di X = 0)
    net.add(makeSegment(2.6, 0.32, 5.0, 0, 0.16, -33.5, true, 'z'));

    // 2. TRUNK PENERIMAAN: Rel Horizontal dari Dock 2 ke RFID (X = 0 s.d. X = -28 di Z = -31)
    net.add(makeSegment(28.0, 0.32, 2.6, -14.0, 0.16, -31.0, true, 'x'));

    // 3. RFID KE X-RAY: Rel Transfer Horizontal dari Portal RFID ke X-Ray Line 1 (X = -28 s.d. X = -13 di Z = -23.5)
    net.add(makeSegment(15.0, 0.32, 2.6, -20.5, 0.16, -23.5, true, 'x'));

    // 4. X-RAY LINE 1 EXIT KE CENTRAL STORAGE:
    // Segmen A: Keluar dari X-Ray Line 1 meluncur ke selatan (Z = -16 s.d. Z = -10 di X = -13)
    net.add(makeSegment(2.6, 0.32, 6.0, -13.0, 0.16, -13.0, true, 'z'));
    // Segmen B: Berbelok ke timur masuk ke koridor utama Central Storage (X = -13 s.d. X = 0 di Z = -10)
    net.add(makeSegment(13.0, 0.32, 2.6, -6.5, 0.16, -10.0, true, 'x'));

    // 5. DIVERTER SPUR KE RUANG KARANTINA:
    // Menghubungkan jalur X-Ray Line 2 (X = -5) ke konveyor ruang karantina (X = 4)
    net.add(makeSegment(9.0, 0.32, 2.2, -0.5, 0.16, -20.0, true, 'x'));

    return net;
}
layoutGroup.add(createConnectingConveyorSystem());

// ─────────────────────────────────────────────────────────────────────────────
// 1. AREA TRUCK DOCK (SISI DARAT - LANDSIDE) DENGAN DETAIL REALISTIS
// ─────────────────────────────────────────────────────────────────────────────
const dockGroup = new THREE.Group();
scene.add(dockGroup);

function createRealisticTruck(xPos, dockNum) {
    const dock = new THREE.Group();
    dock.position.set(xPos, 0, -36);

    // KABIN TRUK
    const cabGroup = new THREE.Group();
    cabGroup.position.set(0, 0, -4.5);

    const cabMain = new THREE.Mesh(new THREE.BoxGeometry(3.0, 3.2, 3.6), MAT.truckCabBlue);
    cabMain.position.y = 2.0;
    cabMain.castShadow = true;
    cabGroup.add(cabMain);

    const spoiler = new THREE.Mesh(new THREE.BoxGeometry(2.9, 0.5, 2.2), MAT.truckCabBlue);
    spoiler.position.set(0, 3.7, 0.2);
    cabGroup.add(spoiler);

    const grill = new THREE.Mesh(new THREE.BoxGeometry(2.4, 1.4, 0.15), MAT.metalDark);
    grill.position.set(0, 1.4, -1.82);
    cabGroup.add(grill);

    const bBumper = new THREE.Mesh(new THREE.BoxGeometry(3.1, 0.6, 0.4), MAT.metalSilver);
    bBumper.position.set(0, 0.6, -1.8);
    cabGroup.add(bBumper);

    const winFront = new THREE.Mesh(new THREE.BoxGeometry(2.7, 1.2, 0.15), new THREE.MeshStandardMaterial({ color: 0x93C5FD, roughness: 0.1 }));
    winFront.position.set(0, 2.6, -1.82);
    cabGroup.add(winFront);

    [-1.65, 1.65].forEach(mx => {
        const mirrorArm = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.05, 0.3), MAT.metalDark);
        mirrorArm.position.set(mx, 2.5, -1.5);
        const mirror = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.6, 0.3), MAT.metalSilver);
        mirror.position.set(mx + (mx > 0 ? 0.15 : -0.15), 2.5, -1.5);
        cabGroup.add(mirrorArm, mirror);
    });

    dock.add(cabGroup);

    // TRAILER KONTENNER
    const trailerGroup = new THREE.Group();
    trailerGroup.position.set(0, 0, 1.5);

    const box = new THREE.Mesh(new THREE.BoxGeometry(3.2, 3.6, 8.5), new THREE.MeshStandardMaterial({ color: 0xF8FAFC, roughness: 0.45 }));
    box.position.y = 2.4;
    box.castShadow = true;
    box.receiveShadow = true;
    trailerGroup.add(box);

    const stripe = new THREE.Mesh(new THREE.BoxGeometry(3.25, 0.6, 7.5), MAT.injourneyTeal);
    stripe.position.y = 2.4;
    trailerGroup.add(stripe);

    const doorFrame = new THREE.Mesh(new THREE.BoxGeometry(3.15, 3.5, 0.1), MAT.metalDark);
    doorFrame.position.set(0, 2.4, 4.26);
    trailerGroup.add(doorFrame);

    [-0.6, 0.6].forEach(bx => {
        const bar = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 3.2), MAT.metalSilver);
        bar.position.set(bx, 2.4, 4.32);
        trailerGroup.add(bar);
    });

    dock.add(trailerGroup);

    // RODA BERVELG
    const wheelGeo = new THREE.CylinderGeometry(0.55, 0.55, 0.4, 20);
    wheelGeo.rotateZ(Math.PI / 2);
    const rimGeo = new THREE.CylinderGeometry(0.35, 0.35, 0.42, 16);
    rimGeo.rotateZ(Math.PI / 2);

    const wheelLocs = [
        [-1.6, 0.55, -4.5], [1.6, 0.55, -4.5],
        [-1.6, 0.55, 2.5], [1.6, 0.55, 2.5],
        [-1.6, 0.55, 4.5], [1.6, 0.55, 4.5]
    ];
    wheelLocs.forEach(([wx, wy, wz]) => {
        const tire = new THREE.Mesh(wheelGeo, MAT.truckTire);
        tire.position.set(wx, wy, wz);
        const rim = new THREE.Mesh(rimGeo, MAT.metalSilver);
        rim.position.set(wx, wy, wz);
        dock.add(tire, rim);
    });

    // PINTU LOADING DOCK DINDING & DOCK SEAL
    const seal = new THREE.Mesh(new THREE.BoxGeometry(4.4, 4.2, 0.6), MAT.metalDark);
    seal.position.set(0, 2.1, 6.8);
    dock.add(seal);

    const parkLine = new THREE.Mesh(new THREE.PlaneGeometry(3.8, 12), new THREE.MeshBasicMaterial({ color: 0xF59E0B, wireframe: true }));
    parkLine.rotation.x = -Math.PI / 2;
    parkLine.position.set(0, 0.05, 0);
    dock.add(parkLine);

    const pal = new THREE.Mesh(new THREE.BoxGeometry(2.0, 0.2, 2.0), MAT.palletWood);
    pal.position.set(3.2, 0.1, 7.5);
    dock.add(pal);

    const cbox = new THREE.Mesh(new THREE.BoxGeometry(1.6, 1.1, 1.6), MAT.cardboardBox);
    cbox.position.set(3.2, 0.75, 7.5);
    cbox.castShadow = true;
    dock.add(cbox);

    bindInteractive(box, 'dock');
    bindInteractive(cabMain, 'dock');
    return dock;
}

[-26, 0, 26].forEach((x, idx) => dockGroup.add(createRealisticTruck(x, idx + 1)));

// Forklift Lapangan Realistis
function createDetailedForklift(x, z, rotY) {
    const fl = new THREE.Group();
    fl.position.set(x, 0, z);
    fl.rotation.y = rotY;

    const b = new THREE.Mesh(new THREE.BoxGeometry(1.6, 1.2, 2.2), MAT.yellowSafety);
    b.position.y = 0.8;
    b.castShadow = true;
    fl.add(b);

    const cage = new THREE.Mesh(new THREE.BoxGeometry(1.4, 1.6, 1.4), new THREE.MeshStandardMaterial({ color: 0x111827, wireframe: true }));
    cage.position.set(0, 2.0, -0.2);
    fl.add(cage);

    const mast = new THREE.Mesh(new THREE.BoxGeometry(0.8, 2.2, 0.15), MAT.metalDark);
    mast.position.set(0, 1.2, 1.2);
    fl.add(mast);

    const fork = new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.08, 1.2), MAT.metalSilver);
    fork.position.set(0, 0.15, 1.8);
    fl.add(fork);

    return fl;
}
dockGroup.add(createDetailedForklift(-16, -31, Math.PI / 3));
dockGroup.add(createDetailedForklift(10, -31, -Math.PI / 4));

const dockLabel = makeLabelSprite("LOKASI 1", "TRUCK DOCKS (LANDSIDE)", "#3B82F6");
dockLabel.position.set(0, 8.5, -34);
dockGroup.add(dockLabel);

// ─────────────────────────────────────────────────────────────────────────────
// 2. AREA RFID SCAN PORTAL (GERBANG PENERIMAAN KONTROL)
// ─────────────────────────────────────────────────────────────────────────────
const rfidGroup = new THREE.Group();
scene.add(rfidGroup);

function createDetailedRfidPortal() {
    const portal = new THREE.Group();
    portal.position.set(-28, 0, -27);

    const colGeo = new THREE.BoxGeometry(0.5, 4.2, 0.6);
    const cL = new THREE.Mesh(colGeo, MAT.metalDark);
    cL.position.set(-2.2, 2.1, 0);
    const cR = new THREE.Mesh(colGeo, MAT.metalDark);
    cR.position.set(2.2, 2.1, 0);
    portal.add(cL, cR);

    const beam = new THREE.Mesh(new THREE.BoxGeometry(4.9, 0.6, 0.6), MAT.metalDark);
    beam.position.set(0, 4.2, 0);
    portal.add(beam);

    const antGeo = new THREE.BoxGeometry(0.2, 1.6, 1.2);
    const antMat = new THREE.MeshStandardMaterial({ color: 0x087F8A, emissive: 0x0CA1AF, emissiveIntensity: 0.4 });
    const aL = new THREE.Mesh(antGeo, antMat);
    aL.position.set(-1.9, 2.2, 0);
    const aR = new THREE.Mesh(antGeo, antMat);
    aR.position.set(1.9, 2.2, 0);
    portal.add(aL, aR);

    const beacon = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.18, 0.35, 12), new THREE.MeshBasicMaterial({ color: 0x10B981 }));
    beacon.position.set(0, 4.65, 0);
    portal.add(beacon);

    const scanLight = new THREE.PointLight(0x0CA1AF, 2.8, 10);
    scanLight.position.set(0, 3.4, 0);
    portal.add(scanLight);

    const conv = new THREE.Mesh(new THREE.BoxGeometry(3.0, 0.32, 8.0), MAT.conveyorRoller);
    conv.position.set(0, 0.16, 0);
    portal.add(conv);

    bindInteractive(beam, 'rfid');
    bindInteractive(cL, 'rfid');
    return portal;
}
rfidGroup.add(createDetailedRfidPortal());

const rfidLabel = makeLabelSprite("LOKASI 2", "AIDC RFID SCAN PORTAL", "#0CA1AF");
rfidLabel.position.set(-28, 8.0, -27);
rfidGroup.add(rfidLabel);

// ─────────────────────────────────────────────────────────────────────────────
// 3. AREA X-RAY AVSEC (3 JALUR) & JALUR CONVEYOR DIVERTER KE RUANG KARANTINA
// ─────────────────────────────────────────────────────────────────────────────
const xrayGroup = new THREE.Group();
scene.add(xrayGroup);

const xrayLeds = []; // Array indikator LED X-Ray

function createDetailedXrayTunnel(xPos, lineNum) {
    const xr = new THREE.Group();
    xr.position.set(xPos, 0, -20);

    const tunnel = new THREE.Mesh(new THREE.BoxGeometry(3.4, 3.8, 5.5), new THREE.MeshStandardMaterial({ color: 0x475569, metalness: 0.65, roughness: 0.35 }));
    tunnel.position.set(0, 1.9, 0);
    tunnel.castShadow = true;
    xr.add(tunnel);

    const cavity = new THREE.Mesh(new THREE.BoxGeometry(2.3, 2.7, 5.7), new THREE.MeshBasicMaterial({ color: 0x0F172A }));
    cavity.position.set(0, 1.8, 0);
    xr.add(cavity);

    const curtain = new THREE.Mesh(new THREE.BoxGeometry(2.1, 2.5, 0.1), new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.9 }));
    curtain.position.set(0, 1.8, 2.8);
    xr.add(curtain);

    const dome = new THREE.Mesh(new THREE.CylinderGeometry(0.7, 0.7, 0.6, 16), new THREE.MeshStandardMaterial({ color: 0xF59E0B, metalness: 0.6 }));
    dome.position.set(0, 4.0, 0);
    xr.add(dome);

    const conv = new THREE.Mesh(new THREE.BoxGeometry(2.2, 0.35, 8.0), MAT.conveyorRoller);
    conv.position.set(0, 0.35, 0);
    xr.add(conv);

    // Lampu Status X-Ray (Hijau normal, Merah saat suspect)
    const ledMat = new THREE.MeshBasicMaterial({ color: 0x10B981 });
    const led = new THREE.Mesh(new THREE.SphereGeometry(0.18, 12, 12), ledMat);
    led.position.set(1.5, 3.4, 2.8);
    xr.add(led);
    xrayLeds.push({ mesh: led, lineNum });

    bindInteractive(tunnel, 'xray');
    return xr;
}

[-13, -5, 3].forEach((x, idx) => xrayGroup.add(createDetailedXrayTunnel(x, idx + 1)));

// Bilik Meja Operator AVSEC Lengkap dengan Monitor Dual-Screen
const opDesk = new THREE.Mesh(new THREE.BoxGeometry(3.0, 1.1, 1.6), MAT.metalSilver);
opDesk.position.set(-5, 0.55, -14.5);
xrayGroup.add(opDesk);

const monL = new THREE.Mesh(new THREE.BoxGeometry(0.8, 0.5, 0.05), new THREE.MeshBasicMaterial({ color: 0xF59E0B }));
monL.position.set(-5.5, 1.5, -14.5);
const monR = new THREE.Mesh(new THREE.BoxGeometry(0.8, 0.5, 0.05), new THREE.MeshBasicMaterial({ color: 0x0284C7 }));
monR.position.set(-4.5, 1.5, -14.5);
xrayGroup.add(monL, monR);

const xrayLabel = makeLabelSprite("LOKASI 3", "X-RAY AVSEC (LINES 1 - 3)", "#F59E0B");
xrayLabel.position.set(-5, 8.0, -20);
xrayGroup.add(xrayLabel);

// ─────────────────────────────────────────────────────────────────────────────
// 3B. RUANG FISIK KARANTINA & FASILITAS PENANGANAN LANJUTAN (REAL ENCLOSED ROOM)
// Dinding partisi fisik, kaca inspeksi, pintu restricted, konveyor diverter,
// meja bongkar sekunder, alat uji ETD, dan figur petugas AVSEC
// ─────────────────────────────────────────────────────────────────────────────
function createRealisticQuarantineRoom() {
    const q = new THREE.Group();
    q.position.set(22, 0, -20);

    // Lantai Ruang Karantina dengan Corak Hazard
    const f = new THREE.Mesh(new THREE.PlaneGeometry(16, 10), MAT.floorQuarantine);
    f.rotation.x = -Math.PI / 2;
    f.position.y = 0.04;
    q.add(f);

    // Garis Hazard Kuning-Hitam di Pinggir Lantai
    const bPts = [
        new THREE.Vector3(-8, 0.08, -5),
        new THREE.Vector3(8, 0.08, -5),
        new THREE.Vector3(8, 0.08, 5),
        new THREE.Vector3(-8, 0.08, 5),
        new THREE.Vector3(-8, 0.08, -5)
    ];
    const bLine = new THREE.Line(new THREE.BufferGeometry().setFromPoints(bPts), new THREE.LineBasicMaterial({ color: 0xEF4444 }));
    q.add(bLine);

    // DINDING BELAKANG (Solid Wall: Z = -5)
    const backW = new THREE.Mesh(new THREE.BoxGeometry(16, 3.6, 0.4), MAT.wallBuilding);
    backW.position.set(0, 1.8, -5);
    backW.receiveShadow = true;
    q.add(backW);

    // DINDING KANAN (Solid Wall: X = 8)
    const rightW = new THREE.Mesh(new THREE.BoxGeometry(0.4, 3.6, 10), MAT.wallBuilding);
    rightW.position.set(8, 1.8, 0);
    rightW.receiveShadow = true;
    q.add(rightW);

    // DINDING KIRI (Partisi dengan Bukaan Masuk Konveyor dari X-Ray: X = -8)
    // Bagian Belakang
    const leftBack = new THREE.Mesh(new THREE.BoxGeometry(0.4, 3.6, 3.2), MAT.wallBuilding);
    leftBack.position.set(-8, 1.8, -3.4);
    q.add(leftBack);
    // Bagian Depan
    const leftFront = new THREE.Mesh(new THREE.BoxGeometry(0.4, 3.6, 3.2), MAT.wallBuilding);
    leftFront.position.set(-8, 1.8, 3.4);
    q.add(leftFront);
    // Balok Ambang Atas Bukaan Konveyor
    const leftLintel = new THREE.Mesh(new THREE.BoxGeometry(0.4, 1.2, 3.6), MAT.metalDark);
    leftLintel.position.set(-8, 3.0, 0);
    q.add(leftLintel);
    // Tirai Plastik Industri PVC Kuning di Pintu Konveyor
    const pvcCurtain = new THREE.Mesh(new THREE.PlaneGeometry(0.1, 2.4), new THREE.MeshStandardMaterial({ color: 0xFDE047, transparent: true, opacity: 0.45 }));
    pvcCurtain.position.set(-8, 1.2, 0);
    q.add(pvcCurtain);

    // DINDING DEPAN (Kaca Keamanan Transparan & Pintu Restricted: Z = 5)
    // Kaca Keamanan Kiri (Bisa Dilihat Jelas dari Luar)
    const glassW = new THREE.Mesh(new THREE.BoxGeometry(10.5, 3.6, 0.15), MAT.wallGlass);
    glassW.position.set(-2.75, 1.8, 5);
    q.add(glassW);

    // Rangka Tiang Kaca
    [-8, -2.75, 2.5].forEach(kx => {
        const post = new THREE.Mesh(new THREE.BoxGeometry(0.2, 3.6, 0.25), MAT.metalDark);
        post.position.set(kx, 1.8, 5);
        q.add(post);
    });

    // Pintu Keamanan Akses Khusus (Heavy Steel Restricted Door: X = 5.25)
    const doorFrame = new THREE.Mesh(new THREE.BoxGeometry(4.0, 3.6, 0.3), MAT.metalDark);
    doorFrame.position.set(5.5, 1.8, 5);
    q.add(doorFrame);

    const doorLeaf = new THREE.Mesh(new THREE.BoxGeometry(3.4, 3.2, 0.15), new THREE.MeshStandardMaterial({ color: 0x991B1B, metalness: 0.6, roughness: 0.4 }));
    doorLeaf.position.set(5.5, 1.6, 5);
    q.add(doorLeaf);

    // Kaca Intip Pintu
    const doorVis = new THREE.Mesh(new THREE.BoxGeometry(0.8, 1.2, 0.2), MAT.wallGlass);
    doorVis.position.set(5.5, 2.1, 5);
    q.add(doorVis);

    // RANGKA ATAP TERBUKA (Steel Truss Beams)
    [-3.5, 0, 3.5].forEach(tz => {
        const truss = new THREE.Mesh(new THREE.BoxGeometry(16, 0.15, 0.2), MAT.metalDark);
        truss.position.set(0, 3.6, tz);
        q.add(truss);
    });

    // Lampu Neon Gantung di Dalam Ruangan
    [-1, 1].forEach(lx => {
        const lamp = new THREE.Mesh(new THREE.BoxGeometry(3.0, 0.1, 0.4), new THREE.MeshBasicMaterial({ color: 0xFFFBEB }));
        lamp.position.set(lx * 2.5, 3.3, 0);
        q.add(lamp);
    });

    // SUAR ALARM MERAH (ROOFTOP & OVER-DOOR BEACON)
    const siren = new THREE.Mesh(new THREE.CylinderGeometry(0.25, 0.25, 0.45, 12), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
    siren.position.set(5.5, 3.9, 5);
    q.add(siren);

    // ─────────────────────────────────────────────────────────────────────────
    // INTERIOR PERALATAN DI DALAM RUANG KARANTINA
    // ─────────────────────────────────────────────────────────────────────────

    // 1. Konveyor Rol Pengalihan Masuk (Diverter Spur Conveyor)
    const spurConv = new THREE.Mesh(new THREE.BoxGeometry(10.5, 0.35, 2.2), MAT.conveyorRoller);
    spurConv.position.set(-3.5, 0.2, 0);
    q.add(spurConv);

    // 2. Meja Stainless Steel Pemeriksaan Sekunder Manual
    const examTable = new THREE.Mesh(new THREE.BoxGeometry(4.5, 0.9, 2.4), MAT.metalSilver);
    examTable.position.set(2.5, 0.45, 0);
    examTable.castShadow = true;
    q.add(examTable);

    // 3. Stasiun Detektor Bahan Peledak ETD (Desktop Unit & Wand)
    const etdDesk = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.9, 1.4), MAT.metalDark);
    etdDesk.position.set(5.5, 0.45, -3.2);
    q.add(etdDesk);

    const etdScreen = new THREE.Mesh(new THREE.BoxGeometry(0.8, 0.6, 0.1), new THREE.MeshBasicMaterial({ color: 0xEF4444 }));
    etdScreen.position.set(5.5, 1.3, -3.2);
    q.add(etdScreen);

    // 4. Brankas / Lemari Penyimpanan Barang Bukti Berbahaya (Evidence Vault)
    const vault = new THREE.Mesh(new THREE.BoxGeometry(1.8, 2.4, 1.0), new THREE.MeshStandardMaterial({ color: 0xB91C1C, metalness: 0.7 }));
    vault.position.set(-4.5, 1.2, -3.5);
    q.add(vault);

    // 5. Figur Petugas AVSEC Spesialis Karantina (Avatar 3D)
    const officer = new THREE.Group();
    officer.position.set(2.5, 0, 1.6);
    officer.rotation.y = Math.PI;

    // Kaki & Celana
    const legs = new THREE.Mesh(new THREE.BoxGeometry(0.6, 1.0, 0.4), new THREE.MeshStandardMaterial({ color: 0x0F172A }));
    legs.position.y = 0.5;
    officer.add(legs);

    // Badan & Rompi Oranye Keselamatan
    const torso = new THREE.Mesh(new THREE.BoxGeometry(0.8, 0.9, 0.5), new THREE.MeshStandardMaterial({ color: 0xEA580C }));
    torso.position.y = 1.35;
    officer.add(torso);

    // Kepala & Topi
    const head = new THREE.Mesh(new THREE.BoxGeometry(0.4, 0.4, 0.4), new THREE.MeshStandardMaterial({ color: 0xFDE047 }));
    head.position.y = 2.0;
    officer.add(head);

    // Tangan & Alat Scanner Wand
    const arm = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.6, 0.15), MAT.metalDark);
    arm.position.set(0.45, 1.2, 0.3);
    arm.rotation.x = Math.PI / 4;
    const wand = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 0.5), new THREE.MeshBasicMaterial({ color: 0x0CA1AF }));
    wand.position.set(0.45, 1.4, 0.5);
    officer.add(arm, wand);

    q.add(officer);

    bindInteractive(examTable, 'quarantine');
    bindInteractive(doorLeaf, 'quarantine');
    bindInteractive(backW, 'quarantine');
    return q;
}
xrayGroup.add(createRealisticQuarantineRoom());

// Jalur Konveyor Penghubung dari X-Ray Line 2 Menembus ke Ruang Karantina
const extSpur = new THREE.Mesh(new THREE.BoxGeometry(10.0, 0.35, 2.2), MAT.conveyorRoller);
extSpur.position.set(9.0, 0.2, -20);
xrayGroup.add(extSpur);

const qLabel = makeLabelSprite("LOKASI 3B", "RUANG KARANTINA & PENANGANAN LANJUTAN", "#EF4444");
qLabel.position.set(22, 7.5, -20);
xrayGroup.add(qLabel);

// ─────────────────────────────────────────────────────────────────────────────
// 4. CENTRAL STORAGE & ULD BUILD-UP (WS 1 - 24)
// ─────────────────────────────────────────────────────────────────────────────
const storageGroup = new THREE.Group();
scene.add(storageGroup);

function createDetailedStorage() {
    const cs = new THREE.Group();
    cs.position.set(0, 0, 6);

    const pad = new THREE.Mesh(new THREE.PlaneGeometry(28, 18), new THREE.MeshStandardMaterial({ color: 0xF1F5F9, roughness: 0.85 }));
    pad.rotation.x = -Math.PI / 2;
    pad.position.y = 0.04;
    cs.add(pad);

    function addStack(x, z, h) {
        const stk = new THREE.Group();
        stk.position.set(x, 0, z);
        const p = new THREE.Mesh(new THREE.BoxGeometry(2.2, 0.25, 2.2), MAT.palletWood);
        p.position.y = 0.13;
        stk.add(p);
        for (let i = 0; i < h; i++) {
            const b = new THREE.Mesh(new THREE.BoxGeometry(1.8, 0.9, 1.8), MAT.cardboardBox);
            b.position.y = 0.25 + 0.45 + i * 0.92;
            b.castShadow = true;
            stk.add(b);
        }
        return stk;
    }

    for (let rx = -10; rx <= 10; rx += 5) {
        for (let rz = -6; rz <= 6; rz += 4.5) {
            // Berikan koridor terbuka di tengah agar kargo bisa melintas langsung ke tengah
            if (Math.abs(rx) <= 2.5) continue;
            const h = (Math.abs(rx * 3 + rz) % 3) + 1;
            cs.add(addStack(rx, rz, h));
        }
    }

    // Jalur Roller Conveyor Utama Masuk ke Tengah Central Storage (X = 0, Z = -10 s.d. Z = 6)
    const centerLaneIn = new THREE.Mesh(new THREE.BoxGeometry(2.6, 0.32, 16.0), MAT.conveyorRoller);
    centerLaneIn.position.set(0, 0.16, -2);

    // Stasiun Meja Kerja Perakitan ULD Utama di Tengah
    const centerBuildBed = new THREE.Mesh(new THREE.BoxGeometry(4.2, 0.32, 4.2), MAT.conveyorRoller);
    centerBuildBed.position.set(0, 0.16, 0);

    const centerRim = new THREE.Mesh(new THREE.BoxGeometry(4.5, 0.08, 4.5), MAT.yellowSafety);
    centerRim.position.set(0, 0.04, 0);

    // Rel Transfer dari Tengah Central Storage Mengalir Langsung ke Timbangan (X = 0 s.d. X = 33 di Z = 8)
    const centerLaneOut = new THREE.Mesh(new THREE.BoxGeometry(33.0, 0.32, 2.6), MAT.conveyorRoller);
    centerLaneOut.position.set(16.5, 0.16, 2);

    // Rel pengaman samping sepanjang rel keluar menuju timbangan
    [-1.35, 1.35].forEach(oz => {
        const outRail = new THREE.Mesh(new THREE.BoxGeometry(33.0, 0.22, 0.1), MAT.yellowSafety);
        outRail.position.set(16.5, 0.38, 2 + oz);
        cs.add(outRail);
    });

    cs.add(centerLaneIn, centerBuildBed, centerRim, centerLaneOut);

    cs.add(createDetailedForklift(-8, 4, Math.PI / 2));
    bindInteractive(pad, 'storage');
    bindInteractive(centerBuildBed, 'storage');
    return cs;
}
storageGroup.add(createDetailedStorage());

const storageLabel = makeLabelSprite("LOKASI 4", "CENTRAL STORAGE & ULD BUILD-UP", "#10B981");
storageLabel.position.set(0, 9.0, 6);
storageGroup.add(storageLabel);

// ─────────────────────────────────────────────────────────────────────────────
// 5. SMART ULD WEIGHING STATION (TIMBANGAN LANTAI 15 TON)
// ─────────────────────────────────────────────────────────────────────────────
// 5. SMART ULD WEIGHING STATION (TIMBANGAN LANTAI 15 TON)
// ─────────────────────────────────────────────────────────────────────────────
const scaleGroup = new THREE.Group();
scene.add(scaleGroup);

let scaleDisplayObj = null;
let scaleStatusRing = null;

function createDetailedSmartScale() {
    const sc = new THREE.Group();
    sc.position.set(35, 0, 8);

    const bed = new THREE.Mesh(new THREE.BoxGeometry(4.6, 0.45, 4.6), new THREE.MeshStandardMaterial({ color: 0x334155, metalness: 0.85, roughness: 0.25 }));
    bed.position.y = 0.22;
    bed.castShadow = true;
    sc.add(bed);

    const rim = new THREE.Mesh(new THREE.BoxGeometry(4.8, 0.1, 4.8), MAT.yellowSafety);
    rim.position.y = 0.05;
    sc.add(rim);

    // Menara Display Digital
    const tower = new THREE.Mesh(new THREE.BoxGeometry(0.5, 3.4, 0.5), MAT.metalDark);
    tower.position.set(2.4, 1.7, -2.0);
    sc.add(tower);

    // Layar LED Digital Dinamis (Canvas Texture)
    const scaleCanvas = document.createElement('canvas');
    scaleCanvas.width = 256;
    scaleCanvas.height = 128;
    const sCtx = scaleCanvas.getContext('2d');
    sCtx.fillStyle = '#064E3B';
    sCtx.fillRect(0, 0, 256, 128);
    sCtx.fillStyle = '#10B981';
    sCtx.font = 'bold 32px monospace';
    sCtx.fillText('READY', 24, 46);
    sCtx.font = 'bold 22px monospace';
    sCtx.fillText('0.00 kg', 24, 88);
    sCtx.font = '14px monospace';
    sCtx.fillText('OIML R76 CERTIFIED', 24, 114);

    const sTex = new THREE.CanvasTexture(scaleCanvas);
    const ledDisp = new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.6, 0.15), new THREE.MeshBasicMaterial({ map: sTex }));
    ledDisp.position.set(2.4, 3.1, -2.0);
    sc.add(ledDisp);
    scaleDisplayObj = { canvas: scaleCanvas, ctx: sCtx, texture: sTex, mesh: ledDisp };

    // Lingkaran Status LED di Lantai
    const ringGeo = new THREE.RingGeometry(2.6, 2.9, 32);
    const ringMat = new THREE.MeshBasicMaterial({ color: 0xA855F7, side: THREE.DoubleSide, transparent: true, opacity: 0.5 });
    scaleStatusRing = new THREE.Mesh(ringGeo, ringMat);
    scaleStatusRing.rotation.x = -Math.PI / 2;
    scaleStatusRing.position.y = 0.06;
    sc.add(scaleStatusRing);

    bindInteractive(bed, 'scale');
    bindInteractive(tower, 'scale');
    return sc;
}
scaleGroup.add(createDetailedSmartScale());

function updateScaleScreen(weightKg = 0, status = 'READY', sub = '0.00 kg') {
    if (!scaleDisplayObj) return;
    const { canvas, ctx, texture } = scaleDisplayObj;
    ctx.clearRect(0, 0, 256, 128);

    if (status === 'PASS') {
        ctx.fillStyle = '#064E3B';
        ctx.fillRect(0, 0, 256, 128);
        ctx.fillStyle = '#34D399';
        ctx.font = 'bold 32px monospace';
        ctx.fillText('1,580 kg', 20, 46);
        ctx.fillStyle = '#10B981';
        ctx.font = 'bold 22px monospace';
        ctx.fillText('STATUS: PASS', 20, 84);
        ctx.fillStyle = '#A7F3D0';
        ctx.font = '13px monospace';
        ctx.fillText('DEV: +0.06% (e-AWB OK)', 20, 112);
        if (scaleStatusRing) scaleStatusRing.material.color.setHex(0x10B981);
    } else {
        ctx.fillStyle = '#064E3B';
        ctx.fillRect(0, 0, 256, 128);
        ctx.fillStyle = '#10B981';
        ctx.font = 'bold 30px monospace';
        ctx.fillText('READY', 24, 46);
        ctx.font = 'bold 22px monospace';
        ctx.fillText('0.00 kg', 24, 88);
        ctx.fillStyle = '#6EE7B7';
        ctx.font = '13px monospace';
        ctx.fillText('OIML R76 15-TON SCALE', 24, 114);
        if (scaleStatusRing) scaleStatusRing.material.color.setHex(0xA855F7);
    }
    texture.needsUpdate = true;
}

const scaleLabel = makeLabelSprite("LOKASI 5", "SMART ULD WEIGHING SCALE (15T)", "#A855F7");
scaleLabel.position.set(35, 8.0, 8);
scaleGroup.add(scaleLabel);

// ─────────────────────────────────────────────────────────────────────────────
// 6. AIRSIDE APRON: CARGO HIGH-LOADER & PESAWAT KARGO
// ─────────────────────────────────────────────────────────────────────────────
const airsideGroup = new THREE.Group();
scene.add(airsideGroup);

let hlElevatorGroup = null;
let hlScissorArm1 = null;
let hlScissorArm2 = null;

function createDetailedHighLoader() {
    const hl = new THREE.Group();
    // Berada di apron antara rel konveyor dan lambung pesawat
    hl.position.set(53, 0, 8);

    // Sasis Kendaraan Heavy-Duty
    const chas = new THREE.Mesh(new THREE.BoxGeometry(5.2, 1.2, 3.8), MAT.yellowSafety);
    chas.position.y = 0.8;
    chas.castShadow = true;
    hl.add(chas);

    // Kabin Operator Samping
    const opCab = new THREE.Mesh(new THREE.BoxGeometry(1.4, 1.4, 1.4), MAT.metalDark);
    opCab.position.set(-1.8, 1.9, 1.1);
    hl.add(opCab);

    // Lampu Sorot & Beacon Kabin
    const cabLight = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.2, 0.4), new THREE.MeshBasicMaterial({ color: 0xFFFBEB }));
    cabLight.position.set(-2.4, 2.0, 1.1);
    const cabBeacon = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.12, 0.25, 12), new THREE.MeshBasicMaterial({ color: 0xF59E0B }));
    cabBeacon.position.set(-1.8, 2.7, 1.1);
    hl.add(cabLight, cabBeacon);

    // 4 Roda Karet Berat (Menyentuh Aspal)
    const wGeo = new THREE.CylinderGeometry(0.65, 0.65, 0.5, 16);
    wGeo.rotateZ(Math.PI / 2);
    [[-1.8, 0.65, -1.9], [1.8, 0.65, -1.9], [-1.8, 0.65, 1.9], [1.8, 0.65, 1.9]].forEach(([wx, wy, wz]) => {
        const w = new THREE.Mesh(wGeo, MAT.truckTire);
        w.position.set(wx, wy, wz);
        hl.add(w);
    });

    // Sub-grup Platform Elevator Bergerak Hidrolik
    hlElevatorGroup = new THREE.Group();
    hlElevatorGroup.position.set(0.6, 0.55, 0); // Ketinggian awal sejajar rel transfer

    // Platform Elevator Pengangkut ULD
    const plat = new THREE.Mesh(new THREE.BoxGeometry(5.6, 0.35, 3.6), MAT.conveyorRoller);
    plat.position.y = 0;
    plat.castShadow = true;
    hlElevatorGroup.add(plat);

    // Pagar Pembatas Keselamatan Kuning di Platform
    [-1.8, 1.8].forEach(pz => {
        const rail = new THREE.Mesh(new THREE.BoxGeometry(5.4, 0.8, 0.08), MAT.yellowSafety);
        rail.position.set(0, 0.45, pz);
        hlElevatorGroup.add(rail);
    });

    // Rangka Scissor-Lift Hidrolik yang Menyesuaikan
    hlScissorArm1 = new THREE.Mesh(new THREE.BoxGeometry(0.15, 3.4, 0.15), MAT.metalSilver);
    hlScissorArm1.position.set(0, -1.2, -1.2);
    hlScissorArm1.rotation.z = Math.PI / 4.5;
    hlScissorArm2 = new THREE.Mesh(new THREE.BoxGeometry(0.15, 3.4, 0.15), MAT.metalSilver);
    hlScissorArm2.position.set(0, -1.2, 1.2);
    hlScissorArm2.rotation.z = -Math.PI / 4.5;
    hlElevatorGroup.add(hlScissorArm1, hlScissorArm2);

    hl.add(hlElevatorGroup);

    bindInteractive(chas, 'loading');
    bindInteractive(plat, 'loading');
    return hl;
}
airsideGroup.add(createDetailedHighLoader());

// Model Kontainer ULD AKE Realistis (Digunakan di dalam palka Boeing 777F)
function createRealisticAkeUld() {
    const uld = new THREE.Group();

    const base = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.22, 2.4), MAT.metalSilver);
    base.position.y = 0.11;
    uld.add(base);

    const ubox = new THREE.Mesh(new THREE.BoxGeometry(2.4, 1.9, 2.4), MAT.uldAluminum);
    ubox.position.y = 1.25;
    ubox.castShadow = true;
    uld.add(ubox);

    const curtain = new THREE.Mesh(new THREE.PlaneGeometry(2.2, 1.7), MAT.uldCanvasBlue);
    curtain.position.set(0, 1.25, 1.22);
    uld.add(curtain);

    return uld;
}

function createDetailedCargoPlane() {
    const plane = new THREE.Group();
    // Posisi pesawat di apron airside (fuselage membujur sepanjang sumbu Z, pusat di X = 64)
    plane.position.set(64, 0, 0);

    // ─────────────────────────────────────────────────────────────────────────
    // 1. FUSELAGE / BADAN PESAWAT BOEING 777F FREIGHTER
    // ─────────────────────────────────────────────────────────────────────────
    const fuseGroup = new THREE.Group();
    plane.add(fuseGroup);

    // Tabung Utama Fuselage (Z = -2 sampai Z = 32, panjang 34m, radius 3.1m)
    const mainFuseGeo = new THREE.CylinderGeometry(3.1, 3.1, 34, 48);
    mainFuseGeo.rotateX(Math.PI / 2);
    const mainFuselage = new THREE.Mesh(mainFuseGeo, MAT.planeWhite);
    mainFuselage.position.set(0, 5.2, 15);
    mainFuselage.castShadow = true;
    mainFuselage.receiveShadow = true;
    fuseGroup.add(mainFuselage);

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

    // Aerodynamic Belly Fairing (Wing-to-Body Belly Fairing: Z = 5 sampai Z = 23)
    const bellyFairing = new THREE.Mesh(new THREE.BoxGeometry(6.6, 1.3, 18), MAT.planeWhite);
    bellyFairing.position.set(0, 3.8, 14);
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
    [-3.12, 3.12].forEach(lx => {
        const tealBand = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.55, 38), MAT.injourneyTeal);
        tealBand.position.set(lx, 4.6, 15);
        fuseGroup.add(tealBand);

        const navyBand = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.22, 36), MAT.planeNavy);
        navyBand.position.set(lx, 4.18, 15);
        fuseGroup.add(navyBand);

        // Garis Aksen Ekor
        const tailStripe = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.45, 12), MAT.injourneyTeal);
        tailStripe.position.set(lx * 0.75, 5.7, 38);
        tailStripe.rotation.x = 0.08;
        fuseGroup.add(tailStripe);
    });

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

    // Pintu Masuk Kru Pesawat (L1 Crew Entry Door di Sisi Port Z = -7.5)
    const crewDoor = new THREE.Mesh(new THREE.BoxGeometry(0.08, 1.9, 1.1), MAT.metalSilver);
    crewDoor.position.set(-3.13, 5.0, -7.5);
    const crewWindow = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.35, 0.25), MAT.cockpitGlass);
    crewWindow.position.set(-3.14, 5.4, -7.5);
    fuseGroup.add(crewDoor, crewWindow);

    // ─────────────────────────────────────────────────────────────────────────
    // 4. PINTU KARGO UTAMA TERBUKA (MAIN DECK CARGO DOOR) & INTERIOR HOLD
    // ─────────────────────────────────────────────────────────────────────────
    // Terletak di sisi KIRI pesawat (X = -3.1, Y = 4.15, Z = 8) menghadap langsung High-Loader!
    const cargoDoorHole = new THREE.Group();
    cargoDoorHole.position.set(-3.1, 4.15, 8);

    // Bukaan Kompartemen Kargo Interior (Illuminated Cargo Hold Interior)
    const holdBox = new THREE.Mesh(new THREE.BoxGeometry(3.6, 2.7, 4.4), new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.85 }));
    holdBox.position.set(1.8, 0, 0);
    cargoDoorHole.add(holdBox);

    // Lantai Rol Kompartemen Kargo (Internal Ball Mat / Roller Bed Floor)
    const holdFloor = new THREE.Mesh(new THREE.BoxGeometry(3.4, 0.12, 4.2), MAT.conveyorRoller);
    holdFloor.position.set(1.8, -1.25, 0);
    cargoDoorHole.add(holdFloor);

    // Kontainer ULD AKE yang Sudah Berada di Dalam Hold Pesawat
    const innerUld = createRealisticAkeUld();
    innerUld.position.set(2.4, -1.15, 0);
    innerUld.scale.set(0.9, 0.9, 0.9);
    cargoDoorHole.add(innerUld);

    // Lampu Penerangan Interior Kargo (Warm High-Intensity LED Light)
    const holdLight = new THREE.PointLight(0xFEF08A, 2.2, 7.5);
    holdLight.position.set(1.2, 0.9, 0);
    cargoDoorHole.add(holdLight);

    // Daun Pintu Kargo Membuka ke Atas (Upward-Swung Main Deck Cargo Door Hatch)
    const doorHatch = new THREE.Mesh(new THREE.BoxGeometry(0.12, 2.6, 4.3), MAT.planeWhite);
    doorHatch.position.set(0, 1.7, 0);
    doorHatch.rotation.z = Math.PI / 2.8;
    cargoDoorHole.add(doorHatch);

    // 2 Batang Silinder Hidrolik Pengangkat Pintu (Dual Hydraulic Actuators)
    [-1.7, 1.7].forEach(hz => {
        const hydCyl = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.045, 1.8), MAT.metalDark);
        hydCyl.position.set(0.35, 0.85, hz);
        hydCyl.rotation.z = Math.PI / 3.5;

        const hydRod = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, 1.2), MAT.metalSilver);
        hydRod.position.set(0.55, 1.25, hz);
        hydRod.rotation.z = Math.PI / 3.5;

        cargoDoorHole.add(hydCyl, hydRod);
    });

    // Ambang Pintu & Frame Pengaman Kuning (Safety Door Rim)
    const doorFrameRim = new THREE.Mesh(new THREE.BoxGeometry(0.16, 2.85, 4.5), MAT.yellowSafety);
    doorFrameRim.position.set(0.06, 0, 0);
    cargoDoorHole.add(doorFrameRim);

    // Jembatan Rol Docking Antara High-Loader dan Pintu Kargo (Connecting Bridge)
    const dockBridge = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.25, 3.4), MAT.conveyorRoller);
    dockBridge.position.set(-1.25, -1.25, 0);
    cargoDoorHole.add(dockBridge);

    [-1.6, 1.6].forEach(bz => {
        const bRail = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.7, 0.06), MAT.yellowSafety);
        bRail.position.set(-1.25, -0.9, bz);
        cargoDoorHole.add(bRail);
    });

    fuseGroup.add(cargoDoorHole);

    // ─────────────────────────────────────────────────────────────────────────
    // 5. SAYAP SWEPT-WING AERODINAMIS MULUS (CONTINUOUS BUFFERGEOMETRY)
    // ─────────────────────────────────────────────────────────────────────────
    function createSmoothAirfoilWing(isLeft) {
        const wingGroup = new THREE.Group();
        const side = isLeft ? -1 : 1;

        const SPAN_STATIONS = 12;
        const positions = [];
        const indices = [];

        for (let i = 0; i <= SPAN_STATIONS; i++) {
            const t = i / SPAN_STATIONS;
            const x = side * (3.15 + t * 24.5);
            const z_le = 8.5 + t * 15.0;
            const chord = 8.6 * (1.0 - 0.72 * t);
            const z_te = z_le + chord;
            const y_c = 4.25 + t * 1.85;
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
            const z_le = 8.5 + t * 15.0;
            const y_c = 4.25 + t * 1.85;
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
            const fz = (8.5 + ft * 15.0) + (8.6 * (1.0 - 0.72 * ft)) * 0.95;
            const fy = (4.25 + ft * 1.85) - 0.35;

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
        const tipZ = 8.5 + 15.0 + 1.2;
        const tipY = 4.25 + 1.85;

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
        eng.position.set(side * 9.8, 2.9, 15.2);

        // Pylon Aerodinamis Penyangga Mesin ke Bawah Sayap
        const pylon = new THREE.Mesh(new THREE.BoxGeometry(0.42, 1.6, 5.0), MAT.metalSilver);
        pylon.position.set(0, 1.3, 0.2);
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

        // Fan Hub Spinner Kerucut Titanium dengan Garis Putih Spiral
        const spinner = new THREE.Mesh(new THREE.ConeGeometry(0.45, 1.1, 20), MAT.engineCore);
        spinner.rotateX(-Math.PI / 2);
        spinner.position.set(0, 0, -2.1);
        eng.add(spinner);

        // Rotor Kipas 3D (18 Bilah Titanium Fan Blades)
        const bladeGeo = new THREE.BoxGeometry(0.12, 0.85, 0.04);
        for (let b = 0; b < 18; b++) {
            const angle = (b / 18) * Math.PI * 2;
            const blade = new THREE.Mesh(bladeGeo, MAT.metalSilver);
            blade.position.set(Math.cos(angle) * 0.9, Math.sin(angle) * 0.9, -2.35);
            blade.rotation.z = angle + 0.45;
            eng.add(blade);
        }

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
    // 8. RODA PENDARAT REALISTIS (AUTHENTIC 6-WHEEL TRIPLE-AXLE BOGIES)
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
    // 9. MARKA KESELAMATAN APRON & SAFETY CONES
    // ─────────────────────────────────────────────────────────────────────────
    // Busur Peringatan Bahaya Hisap Mesin Jet (Turbine Hazard Red Arc)
    [-9.8, 9.8].forEach(mx => {
        const hazArc = new THREE.Mesh(new THREE.RingGeometry(2.6, 2.9, 32, 1, 0, Math.PI), new THREE.MeshBasicMaterial({ color: 0xEF4444, side: THREE.DoubleSide }));
        hazArc.rotation.x = -Math.PI / 2;
        hazArc.position.set(mx, 0.05, 12.8);
        plane.add(hazArc);
    });

    // Kerucut Keselamatan Apron Oranye Bergaris Putih (Safety Cones)
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

    // Interaktivitas: Mengklik badan atau pintu pesawat mengaktifkan stage loading
    bindInteractive(mainFuselage, 'loading');
    bindInteractive(doorHatch, 'loading');
    bindInteractive(radomeCap, 'loading');

    return plane;
}
airsideGroup.add(createDetailedCargoPlane());

const loadingLabel = makeLabelSprite("LOKASI 6", "PEMUATAN PESAWAT (AIRSIDE)", "#38BDF8");
loadingLabel.position.set(56, 10.5, 8);
airsideGroup.add(loadingLabel);

// ─────────────────────────────────────────────────────────────────────────────
// SIMULASI ALUR KARGO REALISTIS 6-TAHAP (TRUK -> RFID -> X-RAY -> WS -> TIMBANGAN -> PESAWAT)
// ─────────────────────────────────────────────────────────────────────────────
const flowSimGroup = new THREE.Group();
scene.add(flowSimGroup);

const movingCargo = new THREE.Group();

// 1. PALLET MODE: Digunakan saat di Truk, RFID, dan X-Ray
const palletModeGroup = new THREE.Group();
const pWood = new THREE.Mesh(new THREE.BoxGeometry(2.0, 0.2, 2.0), MAT.palletWood);
pWood.position.y = 0.1;

const cBox1 = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.9, 1.6), MAT.cardboardBox);
cBox1.position.y = 0.65;
cBox1.castShadow = true;

const cBox2 = new THREE.Mesh(new THREE.BoxGeometry(0.85, 0.65, 0.85), MAT.cardboardBox);
cBox2.position.set(-0.35, 1.42, 0.35);
cBox2.castShadow = true;

const cBox3 = new THREE.Mesh(new THREE.BoxGeometry(0.75, 0.65, 0.75), new THREE.MeshStandardMaterial({ color: 0xC26A05, roughness: 0.85 }));
cBox3.position.set(0.38, 1.42, -0.35);
cBox3.castShadow = true;

const strapMat = new THREE.MeshStandardMaterial({ color: 0x1E293B, roughness: 0.5 });
const strap1 = new THREE.Mesh(new THREE.BoxGeometry(1.62, 0.92, 0.04), strapMat);
strap1.position.y = 0.65;
const strap2 = new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.92, 1.62), strapMat);
strap2.position.y = 0.65;

const mTag = new THREE.Mesh(new THREE.SphereGeometry(0.18, 12, 12), new THREE.MeshBasicMaterial({ color: 0x0CA1AF }));
mTag.position.set(0.82, 0.75, 0);

palletModeGroup.add(pWood, cBox1, cBox2, cBox3, strap1, strap2, mTag);
movingCargo.add(palletModeGroup);

// 2. ULD MODE: Kontainer Pesawat AKE Nyata (Digunakan setelah dirakit di Workstation, lalu ditimbang & dimuat ke pesawat)
const uldModeGroup = new THREE.Group();
function createMovingAkeUld() {
    const uld = new THREE.Group();

    const base = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.22, 2.4), MAT.metalSilver);
    base.position.y = 0.11;
    uld.add(base);

    const ubox = new THREE.Mesh(new THREE.BoxGeometry(2.4, 1.9, 2.4), MAT.uldAluminum);
    ubox.position.y = 1.25;
    ubox.castShadow = true;
    uld.add(ubox);

    const curtain = new THREE.Mesh(new THREE.PlaneGeometry(2.2, 1.7), MAT.uldCanvasBlue);
    curtain.position.set(0, 1.25, 1.22);
    uld.add(curtain);

    // Stensil Kode Kontainer ULD
    const stencCanvas = document.createElement('canvas');
    stencCanvas.width = 256;
    stencCanvas.height = 128;
    const sctx = stencCanvas.getContext('2d');
    sctx.fillStyle = '#E2E8F0';
    sctx.fillRect(0, 0, 256, 128);
    sctx.fillStyle = '#0A2540';
    sctx.font = 'bold 24px monospace';
    sctx.fillText('AKE 78219 GA', 15, 36);
    sctx.fillStyle = '#087F8A';
    sctx.font = 'bold 16px sans-serif';
    sctx.fillText('INJOURNEY CARGO', 15, 68);
    sctx.fillStyle = '#475569';
    sctx.font = '13px monospace';
    sctx.fillText('TARE: 120 KG  MAX: 1588 KG', 15, 100);

    const stencTex = new THREE.CanvasTexture(stencCanvas);
    const stencMesh = new THREE.Mesh(new THREE.PlaneGeometry(1.6, 0.8), new THREE.MeshBasicMaterial({ map: stencTex }));
    stencMesh.position.set(1.22, 1.35, 0);
    stencMesh.rotation.y = Math.PI / 2;
    uld.add(stencMesh);

    return uld;
}
uldModeGroup.add(createMovingAkeUld());
uldModeGroup.visible = false;
movingCargo.add(uldModeGroup);

// 3. FLOATING 3D HUD BILLBOARD: Melayang tepat di atas kargo menjelaskan setiap proses secara nyata
function makeCargoHudSprite(title, sub, color = '#3B82F6') {
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 160;
    const ctx = c.getContext('2d');

    ctx.fillStyle = 'rgba(8, 20, 42, 0.94)';
    ctx.strokeStyle = color;
    ctx.lineWidth = 5;
    ctx.beginPath();
    ctx.roundRect(10, 10, 492, 140, 24);
    ctx.fill();
    ctx.stroke();

    ctx.fillStyle = color;
    ctx.font = '900 24px Barlow, sans-serif';
    ctx.fillText(title, 28, 52);

    ctx.fillStyle = '#FFFFFF';
    ctx.font = 'bold 22px Barlow, sans-serif';
    ctx.fillText(sub, 28, 96);

    ctx.fillStyle = 'rgba(255, 255, 255, 0.12)';
    ctx.beginPath();
    ctx.roundRect(28, 114, 456, 26, 8);
    ctx.fill();
    ctx.fillStyle = color;
    ctx.font = 'bold 15px monospace';
    ctx.fillText('● REAL-TIME OPERATIONAL STAGE', 38, 132);

    const tex = new THREE.CanvasTexture(c);
    const spriteMat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false });
    const sprite = new THREE.Sprite(spriteMat);
    sprite.scale.set(7.8, 2.45, 1);
    sprite.position.y = 3.6;
    return { sprite, canvas: c, ctx, texture: tex };
}
const cargoHud = makeCargoHudSprite("1. BONGKAR DARI TRUK", "Menurunkan palet dari Dock 2", "#3B82F6");
movingCargo.add(cargoHud.sprite);

flowSimGroup.add(movingCargo);

function updateCargoHudText(title, sub, color = '#0CA1AF') {
    if (!cargoHud) return;
    const { canvas, ctx, texture } = cargoHud;
    ctx.clearRect(0, 0, 512, 160);

    ctx.fillStyle = 'rgba(8, 20, 42, 0.94)';
    ctx.strokeStyle = color;
    ctx.lineWidth = 5;
    ctx.beginPath();
    ctx.roundRect(10, 10, 492, 140, 24);
    ctx.fill();
    ctx.stroke();

    ctx.fillStyle = color;
    ctx.font = '900 24px Barlow, sans-serif';
    ctx.fillText(title, 28, 52);

    ctx.fillStyle = '#FFFFFF';
    ctx.font = 'bold 22px Barlow, sans-serif';
    ctx.fillText(sub, 28, 96);

    ctx.fillStyle = 'rgba(255, 255, 255, 0.12)';
    ctx.beginPath();
    ctx.roundRect(28, 114, 456, 26, 8);
    ctx.fill();
    ctx.fillStyle = color;
    ctx.font = 'bold 15px monospace';
    ctx.fillText('● REAL-TIME OPERATIONAL STAGE', 38, 132);

    texture.needsUpdate = true;
}

// ─────────────────────────────────────────────────────────────────────────────
// WAYPOINTS 6-TAHAP ALUR OPERASIONAL LENGKAP
// ─────────────────────────────────────────────────────────────────────────────
const WAYPOINTS_NORMAL = [
    // 1. BONGKAR TRUK (DOCK 2)
    {
        pos: new THREE.Vector3(0, 0.45, -36),
        step: 1,
        stage: 'dock',
        title: '1. BONGKAR DARI TRUK DOCK 2',
        text: 'Truk tiba & palet disiapkan di Dock 2 (TAS Verified)',
        subtext: 'Koli diverifikasi jadwal & dibongkar dari bak truk',
        isUld: false,
        color: '#3B82F6'
    },
    {
        pos: new THREE.Vector3(0, 0.35, -31),
        step: 1,
        stage: 'dock',
        title: '1. TURUN KE REL PENERIMAAN',
        text: 'Palet diturunkan melalui Dock Leveler ke rel konveyor utama',
        subtext: 'Memasuki jalur konveyor penerimaan dermaga muat',
        isUld: false,
        color: '#3B82F6'
    },
    {
        pos: new THREE.Vector3(-14, 0.35, -31),
        step: 1,
        stage: 'dock',
        title: '1. MELUNCUR DI REL PENERIMAAN',
        text: 'Palet dialirkan di atas konveyor penerimaan menuju RFID',
        subtext: 'Menuju gerbang portal identifikasi nirkabel otomatis',
        isUld: false,
        color: '#3B82F6'
    },
    {
        pos: new THREE.Vector3(-28, 0.35, -31),
        step: 1,
        stage: 'dock',
        title: '1. TIBA DI BELOKAN RFID',
        text: 'Palet bersiap membelok memasuki lorong pemindaian RFID',
        subtext: 'Posisi koli lurus sejajar antena portal identifikasi',
        isUld: false,
        color: '#3B82F6'
    },

    // 2. SCAN RFID PORTAL
    {
        pos: new THREE.Vector3(-28, 0.35, -27),
        step: 2,
        stage: 'rfid',
        title: '2. GERBANG RFID SCAN PORTAL',
        text: 'Palet memasuki lorong High-Speed RFID Portal',
        subtext: 'Antena memindai tag tanpa sentuh kecepatan 2.5 m/s',
        isUld: false,
        color: '#0CA1AF'
    },
    {
        pos: new THREE.Vector3(-28, 0.35, -23.5),
        step: 2,
        stage: 'rfid',
        title: '2. RFID MATCHED: GS1 SSCC VALID',
        text: 'Tag GS1 SSCC 18-digit terdeteksi & cocok dengan e-AWB',
        subtext: 'Nomor SSCC terverifikasi di CMS: (00) 08901234567890128',
        isUld: false,
        color: '#0CA1AF'
    },
    {
        pos: new THREE.Vector3(-20, 0.35, -23.5),
        step: 2,
        stage: 'rfid',
        title: '2. REL TRANSFER MENUJU X-RAY',
        text: 'Palet meluncur di rel transfer penghubung menuju AVSEC',
        subtext: 'Diarahkan ke stasiun Dual-View X-Ray Screening Line 1',
        isUld: false,
        color: '#0CA1AF'
    },

    // 3. INSPEKSI X-RAY AVSEC
    {
        pos: new THREE.Vector3(-13, 0.35, -23.5),
        step: 3,
        stage: 'xray',
        title: '3. MASUK TEROWONGAN X-RAY LINE 1',
        text: 'Masuk terowongan Dual-View X-Ray Screening Line 1',
        subtext: 'Pemindaian keamanan fisik sudut horizontal & vertikal',
        isUld: false,
        color: '#F59E0B'
    },
    {
        pos: new THREE.Vector3(-13, 0.35, -16),
        step: 3,
        stage: 'xray',
        title: '3. X-RAY CLEARED: CSD RESMI TERBIT',
        text: 'Inspeksi keamanan lolos: Status CLEARED (CSD terbit)',
        subtext: 'Cargo Security Declaration (CSD) resmi diterbitkan',
        isUld: false,
        color: '#F59E0B'
    },

    // 4. CENTRAL STORAGE & ULD BUILD-UP (LANGSUNG KE TENGAH)
    {
        pos: new THREE.Vector3(-13, 0.35, -10),
        step: 4,
        stage: 'storage',
        title: '4. REL PENGHUBUNG CENTRAL STORAGE',
        text: 'Palet meluncur keluar dari X-Ray menuju Central Storage',
        subtext: 'Mengalir di atas rel konveyor transfer gudang',
        isUld: false,
        color: '#10B981'
    },
    {
        pos: new THREE.Vector3(0, 0.35, -10),
        step: 4,
        stage: 'storage',
        title: '4. MASUK KORIDOR UTAMA GUDANG',
        text: 'Palet berbelok masuk ke koridor utama Central Storage',
        subtext: 'Menuju stasiun perakitan kontainer di bagian tengah',
        isUld: false,
        color: '#10B981'
    },
    {
        pos: new THREE.Vector3(0, 0.35, -2),
        step: 4,
        stage: 'storage',
        title: '4. STAGING DI CENTRAL STORAGE',
        text: 'Transit penataan koli di area staging Central Storage',
        subtext: 'Penataan kargo sesuai jadwal penerbangan Flight GA-882',
        isUld: false,
        color: '#10B981'
    },
    {
        pos: new THREE.Vector3(0, 0.55, 6),
        step: 4,
        stage: 'storage',
        title: '4. BUILD-UP: DIRAKIT KE ULD AKE',
        text: 'Koli disusun & dikunci ke Kontainer ULD AKE #78219 di tengah',
        subtext: 'Transformasi: Kargo selesai dirakit menjadi Kontainer ULD Pesawat',
        isUld: true,
        color: '#10B981'
    },
    {
        pos: new THREE.Vector3(16, 0.55, 8),
        step: 4,
        stage: 'storage',
        title: '4. ULD MENUJU TIMBANGAN LANTAI',
        text: 'Kontainer ULD meluncur di rel transfer tengah ke Timbangan',
        subtext: 'Keluar dari Central Storage menuju Smart Weighing Station',
        isUld: true,
        color: '#10B981'
    },

    // 5. TIMBANGAN ULD (15 TON)
    {
        pos: new THREE.Vector3(35, 0.45, 8),
        step: 5,
        stage: 'scale',
        title: '5. PENIMBANGAN ULD LANTAI 15T',
        text: 'Ditimbang di Smart Scale: 1,580 kg (Toleransi PASS)',
        subtext: 'Deviasi +0.06% (Toleransi e-AWB PASS) -> Izin Airside OK',
        isUld: true,
        isWeighing: true,
        color: '#A855F7'
    },
    {
        pos: new THREE.Vector3(42, 0.55, 8),
        step: 5,
        stage: 'scale',
        title: '5. KELUAR GERBANG AIRSIDE #1',
        text: 'Meluncur keluar melewati Gerbang Kargo Airside #1',
        subtext: 'Pintu gerbang terbuka menghubungkan terminal ke apron',
        isUld: true,
        color: '#A855F7'
    },

    // 6. LOADING KE PESAWAT (HIGH-LOADER & BOEING 777F)
    {
        pos: new THREE.Vector3(53, 0.55, 8),
        step: 6,
        stage: 'loading',
        title: '6. MASUK PLATFORM HIGH-LOADER',
        text: 'Kontainer ULD berpindah ke platform Cargo High-Loader',
        subtext: 'Platform menerima ULD dari rel transfer sisi gerbang',
        isUld: true,
        isHighLoader: true,
        color: '#38BDF8'
    },
    {
        pos: new THREE.Vector3(53, 3.85, 8),
        step: 6,
        stage: 'loading',
        title: '6. HIGH-LOADER TERANGKAT KE ATAS',
        text: 'Scissor-lift hidrolis mengangkat ULD setinggi pintu pesawat (3.85m)',
        subtext: 'Platform elevator naik presisi sejajar pintu kargo utama',
        isUld: true,
        isElevating: true,
        color: '#38BDF8'
    },
    {
        pos: new THREE.Vector3(61, 3.85, 8),
        step: 6,
        stage: 'loading',
        title: '6. DIMUAT KE BOEING 777F (DEPARTED)',
        text: 'ULD meluncur masuk ke kompartemen kargo (DEPARTED Ready)',
        subtext: 'ULD terkunci di rel pesawat. Manifes final e-Loadsheet sukses!',
        isUld: true,
        isLoaded: true,
        color: '#38BDF8'
    }
];

// Skenario 2: Alur Kargo SUSPECT (TIDAK LOLOS X-RAY & DIISOLASI KE RUANG KARANTINA)
const WAYPOINTS_SUSPECT = [
    {
        pos: new THREE.Vector3(0, 0.45, -36),
        step: 1,
        stage: 'dock',
        title: '1. BONGKAR DARI TRUK DOCK 2',
        text: 'Truk tiba & palet dibongkar di Dock 2 (TAS Verified)',
        subtext: 'Koli diverifikasi jadwal & dibongkar dari bak truk',
        isUld: false,
        color: '#3B82F6'
    },
    {
        pos: new THREE.Vector3(0, 0.35, -31),
        step: 1,
        stage: 'dock',
        title: '1. TURUN KE REL PENERIMAAN',
        text: 'Palet diturunkan dari bak truk ke rel konveyor utama',
        subtext: 'Palet dialirkan ke area penerimaan kargo terminal',
        isUld: false,
        color: '#3B82F6'
    },
    {
        pos: new THREE.Vector3(-14, 0.35, -31),
        step: 1,
        stage: 'dock',
        title: '1. MELUNCUR DI REL PENERIMAAN',
        text: 'Palet dialirkan ke jalur penerimaan kargo',
        subtext: 'Menuju gerbang portal identifikasi otomatis',
        isUld: false,
        color: '#3B82F6'
    },
    {
        pos: new THREE.Vector3(-28, 0.35, -31),
        step: 1,
        stage: 'dock',
        title: '1. TIBA DI BELOKAN RFID',
        text: 'Palet bersiap membelok memasuki lorong pemindaian RFID',
        subtext: 'Posisi koli lurus sejajar antena portal identifikasi',
        isUld: false,
        color: '#3B82F6'
    },
    {
        pos: new THREE.Vector3(-28, 0.35, -27),
        step: 2,
        stage: 'rfid',
        title: '2. GERBANG RFID SCAN PORTAL',
        text: 'Memasuki gerbang Portal RFID Scanner',
        subtext: 'Antena memindai tag tanpa sentuh kecepatan 2.5 m/s',
        isUld: false,
        color: '#0CA1AF'
    },
    {
        pos: new THREE.Vector3(-28, 0.35, -23.5),
        step: 2,
        stage: 'rfid',
        title: '2. RFID VERIFIKASI AWAL',
        text: 'Label GS1 SSCC 18-digit terverifikasi awal di CMS',
        subtext: 'Data awal kargo dicocokkan sebelum masuk AVSEC',
        isUld: false,
        color: '#0CA1AF'
    },
    {
        pos: new THREE.Vector3(-14, 0.35, -23.5),
        step: 2,
        stage: 'rfid',
        title: '2. REL TRANSFER MENUJU X-RAY LINE 2',
        text: 'Palet dialirkan di rel transfer menuju X-Ray Line 2',
        subtext: 'Diarahkan ke stasiun inspeksi keamanan Line 2',
        isUld: false,
        color: '#0CA1AF'
    },
    {
        pos: new THREE.Vector3(-5, 0.35, -23.5),
        step: 3,
        stage: 'xray',
        title: '3. MASUK X-RAY LINE 2',
        text: 'Masuk terowongan Dual-View X-Ray Line 2',
        subtext: 'Pemindaian sensor mendeteksi ketidaksesuaian densitas',
        isUld: false,
        color: '#EF4444'
    },
    {
        pos: new THREE.Vector3(-5, 0.35, -20),
        step: 3,
        stage: 'xray',
        title: '3. ⚠️ ANOMALI TERDETEKSI!',
        text: '⚠️ PERINGATAN X-RAY: Terdeteksi Anomali Kerapatan Mencurigakan!',
        subtext: 'Sensor AVSEC membunyikan alarm: Status SUSPECT',
        isUld: false,
        color: '#EF4444'
    },
    {
        pos: new THREE.Vector3(5, 0.35, -20),
        step: 3,
        stage: 'quarantine',
        title: '3B. DIVERTER SPUR AKTIF',
        text: '🚨 DIVERTER SPUR AKTIF: Palet otomatis dialihkan ke Karantina',
        subtext: 'Lengan pengalih mekanis membelokkan palet dari jalur utama',
        isUld: false,
        color: '#EF4444'
    },
    {
        pos: new THREE.Vector3(14, 0.35, -20),
        step: 3,
        stage: 'quarantine',
        title: '3B. MASUK RUANG KARANTINA',
        text: 'Palet meluncur melewati pintu masuk Ruang Karantina (3B)',
        subtext: 'Memasuki ruangan terisolasi khusus penanganan lanjutan',
        isUld: false,
        color: '#EF4444'
    },
    {
        pos: new THREE.Vector3(21, 0.45, -20),
        step: 3,
        stage: 'quarantine',
        title: '3B. MEJA PEMERIKSAAN SEKUNDER',
        text: 'Palet tiba di Meja Pemeriksaan Fisik Sekunder',
        subtext: 'Petugas AVSEC melakukan inspeksi manual & uji usap ETD',
        isUld: false,
        color: '#EF4444'
    },
    {
        pos: new THREE.Vector3(22, 0.7, -20),
        step: 3,
        stage: 'quarantine',
        title: '3B. 🛑 STATUS: SUSPECT & HOLD',
        text: '🛑 STATUS: SUSPECT & HOLD! DILARANG KERAS ke Pesawat!',
        subtext: 'Kargo ditahan dalam brankas bukti. Laporan insiden diterbitkan.',
        isUld: false,
        color: '#EF4444'
    }
];

let currentScenario = 'normal';
let activeWaypoints = WAYPOINTS_NORMAL;
let isFlowActive = true;
let flowVelocity = 1.0;
let wpIdx = 0;
let wpT = 0;

function setScenario(scen) {
    currentScenario = scen;
    const btnNorm = document.getElementById('btn-scen-normal');
    const btnSusp = document.getElementById('btn-scen-suspect');
    const lblEnd = document.getElementById('label-flow-end');

    if (scen === 'suspect') {
        activeWaypoints = WAYPOINTS_SUSPECT;
        if (btnSusp) {
            btnSusp.classList.remove('bg-white/10', 'text-rose-300');
            btnSusp.classList.add('bg-rose-600', 'text-white', 'shadow-lg');
        }
        if (btnNorm) {
            btnNorm.classList.remove('bg-[#0CA1AF]', 'text-white');
            btnNorm.classList.add('bg-white/10', 'text-slate-300');
        }
        if (lblEnd) lblEnd.textContent = 'Karantina';

        addLogEntry('⚠️ Skenario Kargo SUSPECT (Tidak Lolos X-Ray) diaktifkan!');
        restartFlow();
        flyCameraSmooth(new THREE.Vector3(12, 22, 12), new THREE.Vector3(10, 2, -20), 1300);
    } else {
        activeWaypoints = WAYPOINTS_NORMAL;
        if (btnNorm) {
            btnNorm.classList.add('bg-[#0CA1AF]', 'text-white');
            btnNorm.classList.remove('bg-white/10', 'text-slate-300');
        }
        if (btnSusp) {
            btnSusp.classList.add('bg-white/10', 'text-rose-300');
            btnSusp.classList.remove('bg-rose-600', 'text-white', 'shadow-lg');
        }
        if (lblEnd) lblEnd.textContent = 'Pesawat';

        cBox1.material.color.setHex(0xD97706);
        cBox2.material.color.setHex(0xD97706);
        mTag.material.color.setHex(0x0CA1AF);
        quarantineAlarmLight.intensity = 0;

        addLogEntry('Alur kargo normal (CLEARED Lolos ke Pesawat) diaktifkan.');
        restartFlow();
    }
}

function updateCargoFlow(dt) {
    if (!isFlowActive) return;

    wpT += dt * 0.45 * flowVelocity;
    if (wpT >= 1.0) {
        wpT = 0;
        wpIdx = (wpIdx + 1) % activeWaypoints.length;

        const current = activeWaypoints[wpIdx];
        const isAlert = current.text.includes('⚠️') || current.text.includes('🚨') || current.text.includes('🛑');
        addLogEntry(`[TAHAP ${current.step}: ${current.stage.toUpperCase()}] ${current.text}`, isAlert);

        // Update progress bar & badge
        const pct = Math.round(((wpIdx + 1) / activeWaypoints.length) * 100);
        const bar = document.getElementById('bar-flow-progress');
        if (bar) bar.style.width = pct + '%';
        const badge = document.getElementById('badge-flow-stage');
        if (badge) badge.textContent = `TAHAP ${current.step}/6: ${current.stage.toUpperCase()} (${pct}%)`;

        // Update floating 3D HUD billboard
        updateCargoHudText(current.title, current.subtext, current.color);

        // Update active bottom bar buttons & top chips
        document.querySelectorAll('.step-chip').forEach(c => c.classList.remove('active'));
        const chip = document.getElementById(`chip-${current.stage}`);
        if (chip) chip.classList.add('active');

        // Buka otomatis informasi stasiun kerja terkait jika tidak sedang di-close pengguna
        const st = FIELD_STATIONS[current.stage];
        if (st && !tourActive) {
            openInspector(st);
        }

        // Logika Reaktif Skenario Suspect
        if (currentScenario === 'suspect') {
            if (wpIdx >= 8) {
                cBox1.material.color.setHex(0xDC2626);
                cBox2.material.color.setHex(0xDC2626);
                mTag.material.color.setHex(0xFF0000);
                quarantineAlarmLight.intensity = 2.8;

                const l2 = xrayLeds.find(l => l.lineNum === 2);
                if (l2) l2.mesh.material.color.setHex(0xEF4444);
            }
            if (wpIdx === activeWaypoints.length - 1) {
                openInspector(FIELD_STATIONS['quarantine']);
            }
        } else {
            cBox1.material.color.setHex(0xD97706);
            cBox2.material.color.setHex(0xD97706);
            mTag.material.color.setHex(0x0CA1AF);
            quarantineAlarmLight.intensity = 0;
            const l2 = xrayLeds.find(l => l.lineNum === 2);
            if (l2) l2.mesh.material.color.setHex(0x10B981);
        }
    }

    const cur = activeWaypoints[wpIdx];
    const nxt = activeWaypoints[(wpIdx + 1) % activeWaypoints.length];
    const pA = cur.pos;
    const pB = nxt.pos;
    movingCargo.position.lerpVectors(pA, pB, wpT);

    // Transformasi Tampilan: Pallet Kayu vs Kontainer ULD Pesawat
    if (cur.isUld) {
        palletModeGroup.visible = false;
        uldModeGroup.visible = true;
    } else {
        palletModeGroup.visible = true;
        uldModeGroup.visible = false;
    }

    // Animasi Gerak Elevator High-Loader & Palka Pesawat
    if (hlElevatorGroup) {
        if (cur.stage === 'loading') {
            if (cur.isElevating) {
                hlElevatorGroup.position.y = THREE.MathUtils.lerp(0.55, 3.85, wpT);
            } else if (cur.isLoaded) {
                hlElevatorGroup.position.y = 3.85;
            } else {
                hlElevatorGroup.position.y = 0.55;
            }
        } else {
            hlElevatorGroup.position.y = 0.55;
        }
    }

    // Animasi Penimbangan Smart ULD Scale
    if (cur.stage === 'scale') {
        updateScaleScreen(1580, 'PASS');
    } else {
        updateScaleScreen(0, 'READY');
    }

    // Efek Pemindaian RFID
    if (cur.stage === 'rfid') {
        mTag.material.color.setHex(0x10B981);
    }

    mTag.scale.setScalar(1 + Math.sin(Date.now() * 0.009) * 0.35);

    // Kedipan suar alarm di ruang karantina saat suspect aktif
    if (currentScenario === 'suspect' && wpIdx >= 8) {
        quarantineAlarmLight.intensity = (Math.sin(Date.now() * 0.015) > 0) ? 3.5 : 0.2;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// TRANSISI KAMERA ULTRA SMOOTH & MODE IKUTI KARGO (FOLLOW CAM)
// ─────────────────────────────────────────────────────────────────────────────
let tweenAnimId = null;
let followCargoCam = false;

function flyCameraSmooth(destPos, destTarget, duration = 1400) {
    if (tweenAnimId) cancelAnimationFrame(tweenAnimId);
    
    controls.enabled = false;

    const startPos = camera.position.clone();
    const startTarget = controls.target.clone();
    const startTime = performance.now();

    function step() {
        const elapsed = performance.now() - startTime;
        const p = Math.min(elapsed / duration, 1);

        const ease = p < 0.5 
            ? 16 * p * p * p * p * p 
            : 1 - Math.pow(-2 * p + 2, 5) / 2;

        camera.position.lerpVectors(startPos, destPos, ease);
        controls.target.lerpVectors(startTarget, destTarget, ease);
        controls.update();

        if (p < 1) {
            tweenAnimId = requestAnimationFrame(step);
        } else {
            if (!followCargoCam) controls.enabled = true;
        }
    }
    step();
}

const STAGE_TO_WP_INDEX = {
    dock: 0,
    rfid: 4,
    xray: 7,
    quarantine: 9,
    storage: 9,
    scale: 14,
    loading: 16
};

function goToStep(key) {
    document.querySelectorAll('.step-chip').forEach(c => c.classList.remove('active'));
    const chip = document.getElementById(`chip-${key}`);
    if (chip) chip.classList.add('active');

    if (key === 'overview') {
        followCargoCam = false;
        const btn1 = document.getElementById('btn-follow-cam');
        if (btn1) btn1.classList.remove('active');
        controls.enabled = true;
        resetCamera();
        return;
    }

    if (key === 'quarantine' && currentScenario !== 'suspect') {
        setScenario('suspect');
    } else if (key !== 'quarantine' && currentScenario === 'suspect') {
        setScenario('normal');
    }

    // Singkronkan kargo ke awal stasiun yang dipilih
    if (STAGE_TO_WP_INDEX[key] !== undefined) {
        wpIdx = STAGE_TO_WP_INDEX[key];
        wpT = 0;
        const cur = activeWaypoints[wpIdx];
        if (cur) {
            updateCargoHudText(cur.title, cur.subtext, cur.color);
            addLogEntry(`[PILIH TAHAP] Melompat ke ${cur.title}`);
        }
    }

    const st = FIELD_STATIONS[key];
    if (st) {
        flyCameraSmooth(new THREE.Vector3(...st.camPos), new THREE.Vector3(...st.camTarget));
        openInspector(st);
    }
}

function jumpToProcessStep(stepNum) {
    const stepMap = {
        1: 'dock',
        2: 'rfid',
        3: 'xray',
        4: 'storage',
        5: 'scale',
        6: 'loading'
    };
    const key = stepMap[stepNum] || 'dock';
    goToStep(key);
}

function toggleFollowCamera() {
    followCargoCam = !followCargoCam;
    const btn1 = document.getElementById('btn-follow-cam');
    if (btn1) btn1.classList.toggle('active', followCargoCam);
    const btn2 = document.getElementById('btn-follow-cargo-card');
    if (btn2) {
        btn2.classList.toggle('bg-[#0CA1AF]', followCargoCam);
        btn2.classList.toggle('text-white', followCargoCam);
    }
    if (followCargoCam) {
        controls.enabled = false;
        addLogEntry('🎥 Mode Kamera Mengikuti Kargo aktif.');
    } else {
        controls.enabled = true;
        addLogEntry('Mode Kamera Mengikuti Kargo dinonaktifkan.');
    }
}

function setViewPreset(preset) {
    document.getElementById('btn-topdown').classList.toggle('active', preset === 'topdown');
    document.getElementById('btn-isometric').classList.toggle('active', preset === 'isometric');

    if (preset === 'topdown') {
        flyCameraSmooth(new THREE.Vector3(0, 95, 0.01), new THREE.Vector3(0, 0, 0), 1500);
    } else {
        flyCameraSmooth(DEFAULT_CAM_POS.clone(), DEFAULT_CAM_TARGET.clone(), 1400);
    }
}

function resetCamera() {
    flyCameraSmooth(DEFAULT_CAM_POS.clone(), DEFAULT_CAM_TARGET.clone(), 1400);
    document.querySelectorAll('.step-chip').forEach(c => c.classList.remove('active'));
    const chip = document.getElementById('chip-overview');
    if (chip) chip.classList.add('active');
    openInspector(FIELD_STATIONS['overview']);
}

// Mode Tur Lapangan Halus Otomatis
let tourActive = false;
let tourStepIdx = 0;
const tourSequence = ['dock', 'rfid', 'xray', 'quarantine', 'storage', 'scale', 'loading'];
let tourTimer = null;

function toggleTourMode() {
    tourActive = !tourActive;
    const btn = document.getElementById('btn-tour');
    btn.classList.toggle('active', tourActive);

    if (tourActive) {
        tourStepIdx = 0;
        runTourStep();
    } else {
        clearTimeout(tourTimer);
        resetCamera();
    }
}

function runTourStep() {
    if (!tourActive) return;
    const stageKey = tourSequence[tourStepIdx];
    goToStep(stageKey);

    tourTimer = setTimeout(() => {
        if (!tourActive) return;
        tourStepIdx = (tourStepIdx + 1) % tourSequence.length;
        runTourStep();
    }, 5200);
}

// ─────────────────────────────────────────────────────────────────────────────
// RAYCASTING HOVER & CLICK INSPECTOR
// ─────────────────────────────────────────────────────────────────────────────
const raycaster = new THREE.Raycaster();
const mouse = new THREE.Vector2(-999, -999);
const tooltip = document.getElementById('hover-tooltip');

canvas.addEventListener('mousemove', e => {
    const rect = canvas.getBoundingClientRect();
    mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
    mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

    raycaster.setFromCamera(mouse, camera);
    const hits = raycaster.intersectObjects(interactiveMeshes, true);

    if (hits.length > 0) {
        const top = hits.find(h => h.object.userData && h.object.userData.stationKey);
        if (top) {
            const st = FIELD_STATIONS[top.object.userData.stationKey];
            if (st) {
                tooltip.style.left = (e.clientX + 14) + 'px';
                tooltip.style.top = (e.clientY + 14) + 'px';
                tooltip.style.opacity = '1';
                document.getElementById('tt-tag').textContent = st.num;
                document.getElementById('tt-name').textContent = st.title;
                document.getElementById('tt-sub').textContent = st.hw;
                canvas.style.cursor = 'pointer';
                return;
            }
        }
    }
    tooltip.style.opacity = '0';
    canvas.style.cursor = 'grab';
});

canvas.addEventListener('click', e => {
    raycaster.setFromCamera(mouse, camera);
    const hits = raycaster.intersectObjects(interactiveMeshes, true);
    if (hits.length > 0) {
        const top = hits.find(h => h.object.userData && h.object.userData.stationKey);
        if (top) {
            goToStep(top.object.userData.stationKey);
        }
    }
});

let isInspectorCollapsed = (localStorage.getItem('aircargo_ins_collapsed') === 'true');

function toggleInspectorCard() {
    isInspectorCollapsed = !isInspectorCollapsed;
    try { localStorage.setItem('aircargo_ins_collapsed', isInspectorCollapsed); } catch (e) {}
    updateInspectorCollapseState();
}

function expandInspector() {
    isInspectorCollapsed = false;
    try { localStorage.setItem('aircargo_ins_collapsed', isInspectorCollapsed); } catch (e) {}
    updateInspectorCollapseState();
}

function collapseInspector() {
    isInspectorCollapsed = true;
    try { localStorage.setItem('aircargo_ins_collapsed', isInspectorCollapsed); } catch (e) {}
    updateInspectorCollapseState();
}

function updateInspectorCollapseState() {
    const body = document.getElementById('ins-collapsible-body');
    const icon = document.getElementById('icon-toggle-ins');
    const headerTitle = document.getElementById('ins-title-header');
    const btn = document.getElementById('btn-toggle-ins');

    if (!body) return;

    if (isInspectorCollapsed) {
        body.style.maxHeight = '0px';
        body.style.opacity = '0';
        body.style.marginTop = '0px';
        body.style.pointerEvents = 'none';
        if (icon) {
            icon.className = 'fa-solid fa-chevron-up text-xs transition-transform duration-300';
        }
        if (btn) btn.title = 'Tampilkan Detail Tahapan';
        if (headerTitle) headerTitle.classList.remove('hidden');
    } else {
        body.style.maxHeight = '600px';
        body.style.opacity = '1';
        body.style.marginTop = '0.5rem';
        body.style.pointerEvents = 'auto';
        if (icon) {
            icon.className = 'fa-solid fa-chevron-down text-xs transition-transform duration-300';
        }
        if (btn) btn.title = 'Sembunyikan Detail Tahapan ke Bawah';
        if (headerTitle) headerTitle.classList.add('hidden');
    }
}

let isSimCollapsed = (localStorage.getItem('aircargo_sim_collapsed') === 'true');

function toggleSimCard() {
    isSimCollapsed = !isSimCollapsed;
    try { localStorage.setItem('aircargo_sim_collapsed', isSimCollapsed); } catch (e) {}
    updateSimCollapseState();
}

function updateSimCollapseState() {
    const body = document.getElementById('sim-collapsible-body');
    const icon = document.getElementById('icon-toggle-sim');
    const btn = document.getElementById('btn-toggle-sim');
    if (!body) return;

    if (isSimCollapsed) {
        body.style.maxHeight = '0px';
        body.style.opacity = '0';
        body.style.marginTop = '0px';
        body.style.pointerEvents = 'none';
        if (icon) icon.className = 'fa-solid fa-chevron-down text-xs transition-transform duration-300';
        if (btn) btn.title = 'Tampilkan Panel Simulasi';
    } else {
        body.style.maxHeight = '500px';
        body.style.opacity = '1';
        body.style.marginTop = '0.625rem';
        body.style.pointerEvents = 'auto';
        if (icon) icon.className = 'fa-solid fa-chevron-up text-xs transition-transform duration-300';
        if (btn) btn.title = 'Sembunyikan Panel Simulasi';
    }
}

function openInspector(st) {
    if (!st) return;
    const card = document.getElementById('inspector-card');
    if (!card) return;

    const elTag = document.getElementById('ins-stage-tag');
    const elTitle = document.getElementById('ins-title');
    const elHeaderTitle = document.getElementById('ins-title-header');
    const elDesc = document.getElementById('ins-desc');
    const elHw = document.getElementById('ins-hw');
    const elSw = document.getElementById('ins-sw');
    const elData = document.getElementById('ins-data');
    const elSimLink = document.getElementById('ins-sim-link');

    if (elTag) elTag.textContent = st.num;
    if (elTitle) elTitle.textContent = st.title;
    if (elHeaderTitle) elHeaderTitle.textContent = st.title;
    if (elDesc) elDesc.textContent = st.desc;
    if (elHw) elHw.textContent = st.hw;
    if (elSw) elSw.textContent = st.sw;
    if (elData) elData.textContent = st.data;

    if (elSimLink) {
        const stageMap = { dock: 1, rfid: 2, xray: 4, quarantine: 4, storage: 4, scale: 5, loading: 7 };
        const stageNum = stageMap[st.id] || 1;
        if (st.id === 'overview') {
            elSimLink.href = 'simulation.php';
            elSimLink.innerHTML = `<i class="fa-solid fa-play mr-1.5 text-[10px]"></i> Buka Simulator PoC`;
        } else {
            elSimLink.href = `simulation.php?stage=${stageNum}`;
            elSimLink.innerHTML = `<i class="fa-solid fa-play mr-1.5 text-[10px]"></i> Buka Tahap ${stageNum} di Simulator`;
        }
    }

    card.classList.remove('opacity-0', 'translate-y-3', 'pointer-events-none');
    card.classList.add('opacity-100', 'translate-y-0');

    // Pertahankan status collapse yang dipilih secara manual oleh pengguna (jangan otomatis terbuka jika sudah di-hide)
    updateInspectorCollapseState();

    // Feedback visual halus saat konten tahap diperbarui
    card.style.borderColor = 'rgba(12, 161, 175, 0.8)';
    card.style.boxShadow = '0 0 16px rgba(12, 161, 175, 0.35)';
    setTimeout(() => {
        card.style.borderColor = '';
        card.style.boxShadow = '';
    }, 500);
}

function closeInspector() {
    collapseInspector();
}

// ─────────────────────────────────────────────────────────────────────────────
// KONTROL PLAYBACK & FEED LOG
// ─────────────────────────────────────────────────────────────────────────────
function toggleFlow() {
    isFlowActive = !isFlowActive;
    const icon = document.getElementById('icon-flow-play');
    icon.className = isFlowActive ? 'fa-solid fa-pause' : 'fa-solid fa-play';
    addLogEntry(isFlowActive ? 'Simulasi dilanjutkan.' : 'Simulasi dijeda.');
}

function restartFlow() {
    wpIdx = 0;
    wpT = 0;
    isFlowActive = true;
    document.getElementById('icon-flow-play').className = 'fa-solid fa-pause';
    addLogEntry('Aliran kargo diulang kembali dari Truck Dock.');
}

function stepNextFlow() {
    wpIdx = (wpIdx + 1) % activeWaypoints.length;
    wpT = 0;
    const cur = activeWaypoints[wpIdx];
    const isAlert = cur.text.includes('⚠️') || cur.text.includes('🚨') || cur.text.includes('🛑');
    addLogEntry(`[Lompat Manual] ${cur.text}`, isAlert);
    goToStep(cur.stage);
}

function setSpeed(spd) {
    flowVelocity = spd;
    ['spd-1', 'spd-2', 'spd-4'].forEach(id => {
        const btn = document.getElementById(id);
        btn.classList.remove('bg-[#0CA1AF]', 'text-white');
        btn.classList.add('text-white/70');
    });
    const act = document.getElementById(`spd-${spd}`);
    if (act) {
        act.classList.add('bg-[#0CA1AF]', 'text-white');
        act.classList.remove('text-white/70');
    }
}

function addLogEntry(msg, isAlert = false) {
    const box = document.getElementById('live-log-box');
    const row = document.createElement('div');
    row.className = 'log-entry' + (isAlert ? ' alert' : '');
    const t = new Date().toLocaleTimeString('id-ID', { hour12: false });
    row.textContent = `[${t}] ${msg}`;
    box.insertBefore(row, box.firstChild);
    while (box.children.length > 5) box.removeChild(box.lastChild);
}

// ─────────────────────────────────────────────────────────────────────────────
// RENDER LOOP & RESIZE
// ─────────────────────────────────────────────────────────────────────────────
function handleResize() {
    const w = container.clientWidth;
    const h = container.clientHeight;
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderer.setSize(w, h);
}
window.addEventListener('resize', handleResize);

let prevTime = performance.now();
function animate() {
    requestAnimationFrame(animate);
    const now = performance.now();
    const dt = (now - prevTime) / 1000;
    prevTime = now;

    if (followCargoCam && isFlowActive) {
        const targetPos = movingCargo.position.clone();
        const camOffset = new THREE.Vector3(14, 11, 14);
        camera.position.lerp(targetPos.clone().add(camOffset), dt * 2.8);
        controls.target.lerp(targetPos, dt * 2.8);
    }

    controls.update();
    updateCargoFlow(dt);
    renderer.render(scene, camera);
}
animate();

// Sembunyikan Loading Screen & Inisialisasi Kartu Tahap Overview
function hideLoadingScreen() {
    openInspector(FIELD_STATIONS['overview']);
    updateInspectorCollapseState();
    updateSimCollapseState();
    setTimeout(() => {
        const scr = document.getElementById('loading-screen');
        if (scr && scr.style.display !== 'none') {
            scr.style.opacity = '0';
            setTimeout(() => { scr.style.display = 'none'; }, 400);
        }
    }, 300);
}

if (document.readyState === 'complete') {
    hideLoadingScreen();
} else {
    window.addEventListener('load', hideLoadingScreen);
    document.addEventListener('DOMContentLoaded', hideLoadingScreen);
}
// Fallback keselamatan agar loading tidak pernah menggantung
setTimeout(hideLoadingScreen, 2000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
