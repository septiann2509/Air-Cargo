// assets/js/simulation.js
// Logika Eksekusi Laboratorium Simulasi 7 Tahapan Kargo Udara (InJourney Airports Design System)

let currentCargoData = null;
let currentTmsData = null;
let recentTrucksList = [];
let totalTrucksCount = 0;
let currentMasterPayload = null;
let currentAgentDecision = null;
let currentLoadmasterDecision = null;
let currentUldsList = [];
let currentStageView = 1;
let currentSystemDashboard = 'all';
let activePayloadTab = 'request';
let currentRequestJson = {};
let currentResponseJson = {};

// Deskripsi tiap tahapan
const STAGE_METADATA = {
    1: {
        title: "Kedatangan Truk & Booking Slot (TMS/TAS)",
        hardware: "TMS RFID Gate Reader & Barrier Gate (Virtual JSON Stream)",
        software: "Intermodal TMS / Truck Appointment System (TAS)",
        icon: "fa-truck",
        explanation: "Data armada truk (plat, supir, transporter, dock slot, booking code) otomatis diterima TMS dari Single Entry Input Kargo tanpa re-entry. Gerbang TAS memverifikasi armada dan membuka barrier secara otomatis.",
        standard: "EDIFACT / REST API Gate Ingest"
    },
    2: {
        title: "Scan RFID/Barcode di Gerbang Masuk (High-Speed Scanner)",
        hardware: "High-Speed Barcode/RFID UHF Tunnel Scanner (Virtual JSON Stream 2.5 m/s)",
        software: "Cargo Management Software (CMS) Inbound Edge",
        icon: "fa-qrcode",
        explanation: "Hardware tunnel scanner 2.5 m/s digantikan oleh transmisi JSON Payload standar GS1 yang memuat SSCC 18-digit, GTIN, nomor batch, dan tanggal kedaluwarsa secara otomatis.",
        standard: "GS1 AI (00) SSCC & (01) GTIN Standard"
    },
    3: {
        title: "Registrasi Data & Pencocokan e-AWB di CMS",
        hardware: "CMS Core Application Server & Gateway (Virtual JSON Stream)",
        software: "Cargo Management Software (CMS Master e-AWB)",
        icon: "fa-laptop-code",
        explanation: "CMS memproses paket data JSON hasil pindaian dan mencocokkannya dengan Air Waybill elektronik (e-AWB). Sistem menetapkan rute internal kargo menuju area screening keamanan.",
        standard: "IATA e-freight / e-AWB XML/JSON"
    },
    4: {
        title: "Pemeriksaan Keamanan X-Ray (AVSEC System)",
        hardware: "Dual-View High-Energy X-Ray 320kV (Virtual JSON Density Stream)",
        software: "Aviation Security Management System (AVSEC System)",
        icon: "fa-shield-halved",
        explanation: "Hardware scanner sinar-X disimulasikan menggunakan JSON Payload evaluasi densitas atom Z-effective. Jika lolos (CLEARED), diterbitkan sertifikat CSD digital; jika SUSPECT kargo langsung diblokir.",
        standard: "ICAO / AVSEC CSD (Consignment Security Declaration)"
    },
    5: {
        title: "Penimbangan Kargo (Smart ULD Weighing Station)",
        hardware: "Smart ULD Floor Scale 15T (Virtual JSON Load Cell Stream)",
        software: "CMS & Modul Integrasi Weight & Balance",
        icon: "fa-scale-balanced",
        explanation: "Sensor load cell timbangan lantai industri direpresentasikan melalui JSON Payload pembacaan berat aktual dan kalkulasi selisih toleransi bobot (discrepancy) untuk kepatuhan batas beban.",
        standard: "OIML R76 Weighing Standard / TCP Socket Payload"
    },
    6: {
        title: "Build-Up ULD & Kalkulasi Weight & Balance",
        hardware: "Automated ULD Workstation (Virtual JSON Envelope Stream)",
        software: "Weight & Balance System / Load Planning Software",
        icon: "fa-boxes-packing",
        explanation: "Proses workstation perakitan ULD kontainer disimulasikan dengan JSON Payload yang menghitung distribusi muatan, titik keseimbangan Center of Gravity (CoG 28.4%), dan integritas batas lantai kabin.",
        standard: "IATA ULD Regulations & Weight & Balance Envelope"
    },
    7: {
        title: "Update Manifes Penerbangan & Pemuatan ke Pesawat",
        hardware: "Airport Ramp Dolly & Cargo High-Loader (Virtual JSON Dispatch Stream)",
        software: "CMS & Airline Flight Operations System",
        icon: "fa-plane-departure",
        explanation: "Hardware transfer dolly dan ramp high-loader ke perut pesawat dituntaskan melalui JSON Payload penerbitan Electronic Loadsheet resmi ke kokpit pilot dan manifes penerbangan (status DEPARTED).",
        standard: "IATA Cargo-IMP / Flight Loadsheet Standard"
    }
};

function initSimulation(cargoId, targetStageView = null, targetSystem = null) {
    if (!cargoId) return;

    if (targetSystem) {
        currentSystemDashboard = targetSystem;
    } else {
        const urlSys = new URLSearchParams(window.location.search).get('system');
        if (urlSys) currentSystemDashboard = urlSys;
    }

    fetch(`api/cargo.php?action=detail&id=${encodeURIComponent(cargoId)}`)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                currentCargoData = res.data.cargo;
                currentTmsData = res.data.tms_data || null;
                recentTrucksList = res.data.tms_trucks || [];
                totalTrucksCount = res.data.tms_total_trucks || recentTrucksList.length;
                currentMasterPayload = res.data.payload || null;
                currentAgentDecision = res.data.agent || null;
                currentLoadmasterDecision = res.data.loadmaster_agent || null;
                if (Array.isArray(res.data.ulds)) currentUldsList = res.data.ulds;
                renderCargoHeader(currentCargoData);
                renderAuditTimeline(res.data.logs);
                updateStepperState(currentCargoData.current_stage, currentCargoData.status);
                selectStageView(targetStageView || currentCargoData.current_stage || 1);
                switchSystemDashboard(currentSystemDashboard, false);
            } else {
                Swal.fire({
                    title: 'Error',
                    text: res.message,
                    icon: 'error',
                    background: '#ffffff',
                    color: '#0D1C42',
                    confirmButtonColor: '#087F8A',
                    customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
                });
            }
        })
        .catch(err => console.error('Fetch error:', err));
}

function renderCargoHeader(c) {
    document.getElementById('card-awb').textContent = c.awb_number;
    document.getElementById('card-sscc').textContent = c.sscc;
    document.getElementById('card-shipper').innerHTML = `<strong class="text-[#0D1C42]">${c.shipper}</strong> &rarr; <span class="text-slate-600">${c.consignee}</span>`;
    document.getElementById('card-commodity').textContent = `${c.commodity_type} (${c.quantity} koli)`;
    
    const actWt = c.actual_weight_kg ? `${parseFloat(c.actual_weight_kg).toFixed(1)} kg` : 'Belum Ditimbang';
    document.getElementById('card-weight').textContent = `${parseFloat(c.declared_weight_kg).toFixed(1)} kg / ${actWt}`;

    const payloadBadge = document.getElementById('card-payload-id');
    if (payloadBadge) {
        payloadBadge.textContent = 'CMP-' + c.id;
    }

    // Badge status (InJourney Clean Theme)
    const badge = document.getElementById('card-status-badge');
    badge.className = 'inline-block px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wide ';
    if (c.status === 'LOADED') {
        badge.textContent = '✓ LOADED · TERKIRIM & SELESAI';
        badge.className += 'bg-emerald-50 text-emerald-700 border border-emerald-300';
    } else if (c.status === 'CLEARED') {
        badge.textContent = c.status;
        badge.className += 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    } else if (c.status === 'SUSPECT') {
        badge.textContent = c.status;
        badge.className += 'bg-rose-50 text-rose-700 border border-rose-300 animate-pulse';
    } else {
        badge.textContent = c.status;
        badge.className += 'bg-teal-50 text-[#087F8A] border border-teal-200';
    }

    // e-Label info
    const destEl = document.getElementById('label-dest');
    if (destEl) {
        destEl.textContent = c.flight_destination || c.flight_dest || (c.consignee && /singapore/i.test(c.consignee) ? 'SIN - SINGAPORE' : 'DPS - DENPASAR');
    }
    document.getElementById('label-consignee').textContent = c.consignee;
    document.getElementById('label-awb').textContent = c.awb_number;
    document.getElementById('label-qty').textContent = c.quantity + ' BOX';
    document.getElementById('label-weight').textContent = (c.actual_weight_kg || c.declared_weight_kg) + ' KG';
    document.getElementById('label-gtin').textContent = c.gtin;
    document.getElementById('label-batch').textContent = c.batch_no;
    document.getElementById('label-exp').textContent = c.expiry_date;
    document.getElementById('label-sscc').textContent = c.sscc;
}

function updateStepperState(completedStage, status) {
    const currentStage = parseInt(completedStage || 1);
    const isFullyLoaded = (status === 'LOADED' && currentStage === 7);
    const badge = document.getElementById('currentStageBadge');
    if (badge) {
        if (isFullyLoaded) {
            badge.textContent = `✓ Selesai & Terkirim ke Pesawat (LOADED · EFB Sent)`;
            badge.className = 'text-[11px] font-mono px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-300 font-bold';
        } else if (status === 'SUSPECT') {
            badge.textContent = `Tahap Kargo Saat Ini: 4 (SUSPECT - Dikarantina)`;
            badge.className = 'text-[11px] font-mono px-3 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-300 font-bold animate-pulse';
        } else {
            badge.textContent = `Tahap Kargo Saat Ini: ${currentStage} (${status})`;
            badge.className = 'text-[11px] font-mono px-3 py-1 rounded-full bg-teal-50 text-[#087F8A] border border-teal-200 font-bold';
        }
    }

    for (let s = 1; s <= 7; s++) {
        const btn = document.getElementById(`step-btn-${s}`);
        const circle = document.getElementById(`step-circle-${s}`);
        const icon = document.getElementById(`step-icon-${s}`);
        const title = document.getElementById(`step-title-${s}`);

        if (!btn || !circle) continue;

        btn.className = 'stage-step-card text-left p-3.5 rounded-xl border transition-all flex flex-col justify-between ';

        if (isFullyLoaded || s < currentStage) {
            // Sudah lewat (Selesai Dilalui) -> HIJAU DENGAN CHECKMARK
            circle.className = 'w-6 h-6 rounded-full bg-emerald-500 text-white text-xs font-bold flex items-center justify-center font-mono shadow-sm';
            circle.innerHTML = '<i class="fa-solid fa-check text-[10px]"></i>';
            if (icon) icon.className = `fa-solid ${STAGE_METADATA[s].icon} text-emerald-600 text-xs`;
            if (title) title.className = 'text-[11px] font-bold text-emerald-700 leading-snug';
            btn.className += 'border-emerald-200 bg-emerald-50/50 hover:bg-emerald-50';
        } else if (s === currentStage) {
            // Tahap saat ini (Aktif)
            if (status === 'SUSPECT' && s === 4) {
                circle.className = 'w-6 h-6 rounded-full bg-rose-600 text-white text-xs font-bold flex items-center justify-center font-mono animate-pulse ring-4 ring-rose-100 shadow-sm';
                circle.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-[10px]"></i>';
                if (icon) icon.className = `fa-solid ${STAGE_METADATA[s].icon} text-rose-600 text-xs`;
                if (title) title.className = 'text-[11px] font-bold text-rose-700 leading-snug';
                btn.className += 'border-rose-300 bg-rose-50 shadow-sm';
            } else {
                circle.className = 'w-6 h-6 rounded-full bg-gradient-to-tr from-[#0CA1AF] to-[#087F8A] text-white text-xs font-bold flex items-center justify-center font-mono ring-4 ring-teal-100 shadow-sm';
                circle.textContent = s;
                if (icon) icon.className = `fa-solid ${STAGE_METADATA[s].icon} text-[#087F8A] text-xs`;
                if (title) title.className = 'text-[11px] font-extrabold text-[#0D1C42] leading-snug';
                btn.className += 'border-[#0CA1AF] bg-teal-50/40 shadow-sm';
            }
        } else {
            // Belum dicapai (Tahap Mendatang)
            circle.className = 'w-6 h-6 rounded-full bg-slate-200 text-slate-500 text-xs font-bold flex items-center justify-center font-mono';
            circle.textContent = s;
            if (icon) icon.className = `fa-solid ${STAGE_METADATA[s].icon} text-slate-400 text-xs`;
            if (title) title.className = 'text-[11px] font-bold text-slate-600 leading-snug';
            btn.className += 'border-gray-200 bg-slate-50/70 hover:border-gray-300 hover:bg-slate-100/50';
        }
    }
}

function selectStageView(stage) {
    currentStageView = stage;
    const meta = STAGE_METADATA[stage];
    if (!meta) return;

    // Highlight card di visual stepper
    for (let s = 1; s <= 7; s++) {
        const btn = document.getElementById(`step-btn-${s}`);
        if (btn) {
            if (s === stage) {
                btn.classList.add('ring-2', 'ring-[#087F8A]');
            } else {
                btn.classList.remove('ring-2', 'ring-[#087F8A]');
            }
        }
    }

    // Set stage details
    const activeStageNumEl = document.getElementById('activeStageNumber');
    if (activeStageNumEl) activeStageNumEl.textContent = `Tahap ${stage} dari 7`;
    const activeStageTitleEl = document.getElementById('activeStageTitle');
    if (activeStageTitleEl) activeStageTitleEl.textContent = meta.title;
    const activeHardwareEl = document.getElementById('activeHardwareName');
    if (activeHardwareEl) activeHardwareEl.textContent = meta.hardware;
    const activeSoftwareEl = document.getElementById('activeSoftwareName');
    if (activeSoftwareEl) activeSoftwareEl.textContent = meta.software;
    const activeExplEl = document.getElementById('activeStageExplanation');
    if (activeExplEl) activeExplEl.textContent = meta.explanation;
    const payloadStdBadge = document.getElementById('payloadStandardBadge');
    if (payloadStdBadge) payloadStdBadge.textContent = meta.standard;
    const activeIconEl = document.getElementById('activeStageIcon');
    if (activeIconEl) activeIconEl.className = `fa-solid ${meta.icon}`;

    // Status badge (InJourney Clean Theme)
    const badge = document.getElementById('stageCompleteBadge');
    const currentCargoStage = currentCargoData ? parseInt(currentCargoData.current_stage || 1) : 1;
    const isLoaded = (currentCargoData && currentCargoData.status === 'LOADED' && currentCargoStage === 7);

    if (badge) {
        if (isLoaded && stage === 7) {
            badge.textContent = 'Status: Selesai & Terkirim (LOADED)';
            badge.className = 'text-xs px-3 py-1 rounded-full font-bold bg-emerald-100 text-emerald-800 border border-emerald-300';
        } else if (isLoaded || stage < currentCargoStage) {
            badge.textContent = 'Status: Selesai Dilalui';
            badge.className = 'text-xs px-3 py-1 rounded-full font-bold bg-emerald-50 text-emerald-700 border border-emerald-200';
        } else if (stage === currentCargoStage && !isLoaded) {
            badge.textContent = (currentCargoData && currentCargoData.status === 'SUSPECT') ? 'Status: Kargo Diblokir' : 'Status: Tahap Aktif';
            badge.className = (currentCargoData && currentCargoData.status === 'SUSPECT') 
                ? 'text-xs px-3 py-1 rounded-full font-bold bg-rose-50 text-rose-700 border border-rose-300 animate-pulse'
                : 'text-xs px-3 py-1 rounded-full font-bold bg-teal-50 text-[#087F8A] border border-teal-200';
        } else {
            badge.textContent = 'Status: Menunggu Tahap Sebelumnya';
            badge.className = 'text-xs px-3 py-1 rounded-full font-bold bg-slate-100 text-slate-500 border border-slate-200';
        }
    }

    // Render Stage Visual Graphic & Action Buttons & Payload Inspector
    renderStageGraphic(stage);
    renderStageActionButtons(stage);
    updatePayloadInspector(stage);

    // Update quick navigation toolbar if present
    for (let s = 1; s <= 7; s++) {
        const qBtn = document.getElementById(`quick-stage-${s}`);
        if (qBtn) {
            if (s === stage) {
                qBtn.className = 'w-7 h-7 rounded-lg bg-[#087F8A] text-white font-black text-[11px] shadow-sm flex items-center justify-center';
            } else {
                qBtn.className = 'w-7 h-7 rounded-lg border border-gray-200 bg-white hover:bg-teal-50 hover:border-teal-300 hover:text-[#087F8A] font-bold text-slate-700 transition-all flex items-center justify-center text-[11px]';
            }
        }
    }
    const prevBtn = document.getElementById('btnNavPrev');
    if (prevBtn) {
        prevBtn.disabled = (stage <= 1);
        prevBtn.style.opacity = (stage <= 1) ? '0.4' : '1';
        prevBtn.style.pointerEvents = (stage <= 1) ? 'none' : 'auto';
    }
    const nextBtn = document.getElementById('btnNavNext');
    if (nextBtn) {
        nextBtn.disabled = (stage >= 7);
        nextBtn.style.opacity = (stage >= 7) ? '0.4' : '1';
        nextBtn.style.pointerEvents = (stage >= 7) ? 'none' : 'auto';
    }
}

function navigateStep(dir) {
    let target = (currentStageView || 1) + dir;
    if (target < 1) target = 1;
    if (target > 7) target = 7;
    selectStageView(target);
}

function renderStageGraphic(stage) {
    const wrap = document.getElementById('stageVisualGraphic');
    const c = currentCargoData;
    if (!c) return;

    if (stage === 1) {
        wrap.innerHTML = `
            <div class="flex items-center justify-center space-x-6">
                <div class="text-center">
                    <div class="w-16 h-16 rounded-2xl bg-white border border-teal-200 flex items-center justify-center text-3xl text-[#087F8A] mb-2 shadow-md">
                        <i class="fa-solid fa-truck"></i>
                    </div>
                    <span class="text-xs font-mono font-bold text-[#0D1C42]">${c.truck_plate || 'B 9482 KXT'}</span>
                    <span class="text-[10px] text-slate-500 block font-medium">${c.driver_name || 'Supir Terdaftar'}</span>
                </div>
                <div class="flex flex-col items-center">
                    <span class="text-[10px] text-emerald-600 font-mono font-bold mb-1">Time-Slot Booked</span>
                    <i class="fa-solid fa-arrow-right-long text-[#087F8A] text-xl animate-pulse"></i>
                    <span class="text-[9px] text-slate-500 font-medium">TAS Auto Gate</span>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 rounded-2xl bg-white border border-teal-200 flex items-center justify-center text-3xl text-[#087F8A] mb-2 shadow-md">
                        <i class="fa-solid fa-warehouse"></i>
                    </div>
                    <span class="text-xs font-mono font-bold text-emerald-700">${c.dock_slot || 'DOCK-04'}</span>
                    <span class="text-[10px] text-slate-500 block font-medium">Dock Alokasi Truk</span>
                </div>
            </div>
        `;
    } else if (stage === 2) {
        wrap.innerHTML = `
            <div class="flex flex-col items-center justify-center">
                <div class="relative w-80 h-16 rounded-xl bg-white border-2 border-[#0CA1AF] flex items-center justify-between px-4 overflow-hidden mb-3 shadow-md">
                    <div class="absolute inset-x-0 h-0.5 bg-[#0CA1AF] shadow-[0_0_10px_#0CA1AF] animate-pulse"></div>
                    <div class="flex items-center space-x-2 text-[#087F8A] text-xs font-bold">
                        <i class="fa-solid fa-barcode text-lg"></i>
                        <span>RFID UHF Antenna (865MHz)</span>
                    </div>
                    <span class="text-[10px] font-mono text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">2.5 m/s TUNNEL</span>
                </div>
                <div class="flex items-center space-x-3 text-xs font-mono">
                    <span class="text-slate-500 font-medium">Captured SSCC:</span>
                    <span class="text-[#087F8A] font-bold bg-white px-2.5 py-1 rounded-lg border border-teal-200 shadow-sm">${c.sscc}</span>
                </div>
            </div>
        `;
    } else if (stage === 3) {
        wrap.innerHTML = `
            <div class="flex items-center justify-center space-x-6">
                <div class="text-center">
                    <div class="w-14 h-14 rounded-2xl bg-white border border-gray-200 flex items-center justify-center text-2xl text-slate-700 mb-1 shadow-sm">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <span class="text-[11px] font-mono text-[#087F8A] font-bold">${c.awb_number}</span>
                    <span class="text-[10px] text-slate-500 block font-medium">Digital e-AWB</span>
                </div>
                <i class="fa-solid fa-arrows-rotate text-[#087F8A] text-xl animate-spin" style="animation-duration: 6s;"></i>
                <div class="text-center">
                    <div class="w-14 h-14 rounded-2xl bg-white border border-teal-200 flex items-center justify-center text-2xl text-[#087F8A] mb-1 shadow-sm">
                        <i class="fa-solid fa-server"></i>
                    </div>
                    <span class="text-[11px] font-bold text-[#0D1C42]">CMS Central</span>
                    <span class="text-[10px] text-emerald-700 font-semibold block">AWB MATCHED</span>
                </div>
            </div>
        `;
    } else if (stage === 4) {
        const isSuspect = c.status === 'SUSPECT';
        wrap.innerHTML = `
            <div class="flex flex-col items-center justify-center">
                <div class="w-80 p-4 rounded-xl border ${isSuspect ? 'border-rose-300 bg-rose-50 shadow-md' : 'border-emerald-200 bg-emerald-50/80 shadow-md'} flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 rounded-lg ${isSuspect ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600'} flex items-center justify-center text-2xl">
                            <i class="fa-solid ${isSuspect ? 'fa-triangle-exclamation animate-bounce' : 'fa-check-double'}"></i>
                        </div>
                        <div class="text-left">
                            <div class="text-xs font-bold text-[#0D1C42]">Dual-View 320kV Sinar-X</div>
                            <div class="text-[10px] ${isSuspect ? 'text-rose-700 font-extrabold' : 'text-emerald-700 font-bold'}">
                                ${isSuspect ? 'SUSPECT: DETEKSI DANGEROUS GOODS' : 'CLEARED: AMAN & LOLOS (CSD ISSUED)'}
                            </div>
                        </div>
                    </div>
                    <span class="text-[10px] font-mono px-2 py-1 rounded ${isSuspect ? 'bg-rose-200 text-rose-800 font-bold' : 'bg-emerald-200 text-emerald-800 font-bold'}">
                        ${isSuspect ? 'BLOCKED' : 'AVSEC PASS'}
                    </span>
                </div>
            </div>
        `;
    } else if (stage === 5) {
        const declared = parseFloat(c.declared_weight_kg || 150.0);
        const actual = c.actual_weight_kg ? parseFloat(c.actual_weight_kg) : declared;
        const diff = actual - declared;
        const diffPct = declared > 0 ? ((diff / declared) * 100).toFixed(2) : '0.00';
        const isWithinTol = declared > 0 ? (Math.abs(diff / declared) <= 0.05) : true;
        const isHea = (declared >= 150.0 || actual >= 150.0);
        const sign = diff >= 0 ? '+' : '';

        wrap.innerHTML = `
            <div class="flex flex-col items-center justify-center">
                <div class="p-4 rounded-2xl bg-[#0D1C42] border border-slate-700 flex items-center space-x-6 shadow-xl">
                    <div class="text-center">
                        <span class="text-[9px] text-teal-300 uppercase tracking-widest block font-bold">Smart Floor Scale 15T</span>
                        <div class="text-3xl font-black text-amber-400 font-mono tracking-wider mt-0.5">
                            ${actual.toFixed(1)} <span class="text-sm text-slate-300">KG</span>
                        </div>
                        <span class="text-[9px] text-emerald-400 font-mono">OIML R76 Class III &bull; &plusmn;1kg</span>
                    </div>
                    <div class="border-l border-slate-700 pl-4 text-left text-[10px] space-y-1">
                        <div class="text-slate-300">Deklarasi e-AWB: <strong class="text-white">${declared.toFixed(1)} kg</strong></div>
                        <div class="text-slate-300">Selisih: <span class="font-mono ${isWithinTol ? 'text-emerald-400' : 'text-amber-400'} font-bold">${sign}${diff.toFixed(1)} kg (${sign}${diffPct}%)</span></div>
                        <div class="flex items-center gap-1.5 pt-0.5">
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded ${isWithinTol ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-700/80' : 'bg-amber-950/80 text-amber-300 border border-amber-700/80'}">
                                ${isWithinTol ? 'Toleransi IATA &plusmn;5% : VALID' : 'Melebihi Toleransi &plusmn;5% (CCA Req)'}
                            </span>
                            ${isHea ? '<span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-purple-950/80 text-purple-300 border border-purple-700/80"><i class="fa-solid fa-weight-hanging mr-1"></i>HEA</span>' : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else if (stage === 6) {
        const netCargo = c.actual_weight_kg ? parseFloat(c.actual_weight_kg) : parseFloat(c.declared_weight_kg || 150.0);
        const tare = 75.0;
        const gross = +(netCargo + tare).toFixed(1);
        const maxGross = parseFloat(c.uld_max_weight || 1588.0);
        const isOverload = gross > maxGross;
        const utilPct = ((gross / maxGross) * 100).toFixed(1);

        wrap.innerHTML = `
            <div class="flex items-center justify-center space-x-6">
                <div class="w-16 h-16 rounded-xl bg-white border border-teal-200 flex items-center justify-center text-3xl ${isOverload ? 'text-rose-600' : 'text-[#087F8A]'} shadow-md">
                    <i class="fa-solid ${isOverload ? 'fa-triangle-exclamation' : 'fa-boxes-packing'}"></i>
                </div>
                <div class="text-left text-xs space-y-0.5">
                    <div class="flex items-center gap-2">
                        <span class="font-black text-[#0D1C42] font-mono text-sm">${c.uld_code || 'AKE-12345-GA'}</span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded ${isOverload ? 'bg-rose-100 text-rose-700 border border-rose-300' : 'bg-teal-50 text-[#087F8A] border border-teal-200'}">
                            ${isOverload ? 'OVERLOAD AKE' : 'AKE / LD3'}
                        </span>
                    </div>
                    <div class="text-[11px] text-slate-700">
                        Net: <strong class="font-mono">${netCargo.toFixed(1)} kg</strong> + Tare: <span class="font-mono text-slate-500">75.0 kg</span> = Gross: <strong class="font-mono ${isOverload ? 'text-rose-600' : 'text-emerald-700'}">${gross} kg</strong>
                    </div>
                    <div class="text-[10px] text-slate-500">
                        Kapasitas: ${maxGross.toFixed(0)} kg (${utilPct}% Terisi) &bull; CoG: <span class="text-emerald-600 font-bold">28.4% MAC (Safe Envelope)</span>
                    </div>
                </div>
            </div>
        `;
    } else if (stage === 7) {
        const isFlightLoaded = (c.status === 'LOADED');
        const flightNum = c.flight_number || 'GA-707';
        const uldName = c.uld_code || 'AKE-12345-GA';
        wrap.innerHTML = `
            <div class="flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 rounded-2xl ${isFlightLoaded ? 'bg-gradient-to-tr from-emerald-500 to-teal-600 shadow-emerald-500/30' : 'bg-gradient-to-tr from-[#04AFBF] to-[#087F8A] shadow-teal-500/25'} flex items-center justify-center text-3xl text-white mb-2 shadow-lg">
                    <i class="fa-solid ${isFlightLoaded ? 'fa-plane-circle-check' : 'fa-plane-departure'}"></i>
                </div>
                <span class="text-sm font-extrabold text-[#0D1C42]">${escapeHtml(flightNum)} (Garuda Indonesia Cargo) &bull; ${escapeHtml(uldName)}</span>
                ${isFlightLoaded
                    ? `<span class="mt-1 inline-flex items-center gap-1.5 text-[11px] text-emerald-800 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-300 font-bold">
                           <i class="fa-solid fa-circle-check text-emerald-600"></i>
                           SUDAH TERKIRIM KE KOKPIT (EFB) &amp; DIMUAT KE PESAWAT (SELESAI)
                       </span>`
                    : `<span class="mt-1 inline-flex items-center gap-1.5 text-[11px] text-amber-800 bg-amber-50 px-3 py-1 rounded-full border border-amber-200 font-bold">
                           <i class="fa-solid fa-clock text-amber-600"></i>
                           MENUNGGU FINALISASI MANIFES &amp; PEMUATAN RAMP
                       </span>`
                }
            </div>
        `;
    }
}

function renderStageActionButtons(stage) {
    const container = document.getElementById('stageActionButtons');
    const c = currentCargoData;
    if (!container || !c) return;

    const currentCargoStage = parseInt(c.current_stage || 1);
    const isLoaded = (c.status === 'LOADED' && currentCargoStage === 7);
    container.innerHTML = '';

    // ── TAHAP 4: SCREENING X-RAY AVSEC (OTONOM AI AGENT) ──
    if (stage === 4) {
        if (c.status === 'CLEARED' || (currentCargoStage > 4 && c.status !== 'SUSPECT' && c.status !== 'RETURNED_TO_TRUCK')) {
            container.innerHTML = `
                <div class="w-full mb-1 p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
                    <span class="font-bold"><i class="fa-solid fa-circle-check text-emerald-600 mr-1.5"></i> AI Agent AVSEC Menyatakan Lolos (CLEARED) &mdash; Sertifikat CSD Diterbitkan</span>
                    <span class="text-[10px] bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-mono font-bold">CLEARED</span>
                </div>
                <div class="w-full mb-1">
                    <label class="inline-flex items-center gap-2 text-[11.5px] text-slate-600 cursor-pointer select-none">
                        <input type="checkbox" id="stage4-inject-anomaly" class="w-3.5 h-3.5 text-rose-600 rounded border-gray-300">
                        <span>Sisipkan anomali berbahaya tersembunyi di sensor X-Ray (jika ingin menguji ulang skenario penyelundupan)</span>
                    </label>
                </div>
                <button onclick="selectStageView(5)" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                    <i class="fa-solid fa-scale-balanced mr-2 text-sm"></i>
                    <span>Lanjut ke Tahap 5 (Penimbangan Smart Scale)</span>
                    <i class="fa-solid fa-arrow-right ml-2 text-xs"></i>
                </button>
                <button onclick="executeStageAction(4, document.getElementById('stage4-inject-anomaly')?.checked ? 'SUSPECT' : 'AUTO')" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-violet-50 text-violet-700 text-xs font-bold border border-violet-300 shadow-sm transition-all flex items-center cursor-pointer">
                    <i class="fa-solid fa-robot mr-1.5 text-xs"></i>
                    <span>Screening Ulang AI Agent AVSEC</span>
                </button>
                <button onclick="selectStageView(3)" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-600 hover:text-[#087F8A] text-xs font-bold border border-gray-300 shadow-sm transition-colors flex items-center cursor-pointer">
                    <i class="fa-solid fa-arrow-left mr-1.5 text-xs"></i>
                    <span>Lihat Tahap 3</span>
                </button>
            `;
            return;
        } else if (c.status === 'SUSPECT') {
            container.innerHTML = `
                <div class="w-full mb-2 p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center justify-between">
                    <span class="font-bold"><i class="fa-solid fa-triangle-exclamation mr-1.5 text-rose-600 animate-pulse"></i> Kargo Berstatus SUSPECT &mdash; Dikunci di Ruang Khusus Karantina AVSEC</span>
                    <span class="text-[10px] bg-rose-200/80 px-2 py-0.5 rounded font-mono font-bold text-rose-800">VAULT LOCKOUT</span>
                </div>
                <button onclick="executeStageAction(4, 'RELEASE')" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-md shadow-emerald-500/20 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                    <i class="fa-solid fa-user-shield mr-1.5"></i>
                    <span>Pemeriksaan Fisik Sekunder (ETD) &rarr; Rilis CLEARED</span>
                </button>
                <button onclick="executeStageAction(4, 'RETURN_TO_TRUCK')" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-black text-white font-bold text-xs shadow-md shadow-slate-900/20 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                    <i class="fa-solid fa-truck-arrow-right mr-1.5 text-amber-400"></i>
                    <span>Tolak &amp; Kembalikan Kargo ke Truk Semula</span>
                </button>
                <button onclick="selectStageView(3)" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-600 hover:text-[#087F8A] text-xs font-bold border border-gray-300 shadow-sm transition-colors flex items-center cursor-pointer">
                    <i class="fa-solid fa-arrow-left mr-1.5 text-xs"></i>
                    <span>Lihat Tahap 3</span>
                </button>
            `;
            return;
        } else if (c.status === 'RETURNED_TO_TRUCK') {
            container.innerHTML = `
                <div class="w-full mb-2 p-2.5 rounded-xl bg-slate-100 border border-slate-300 text-slate-800 text-xs flex items-center justify-between">
                    <span class="font-bold"><i class="fa-solid fa-truck-arrow-right mr-1.5 text-slate-600"></i> Kargo Ditolak di AVSEC &amp; Telah Dikembalikan ke Armada Truk Semula (${escapeHtml(c.truck_plate || 'Truk')})</span>
                    <span class="text-[10px] bg-slate-200 px-2 py-0.5 rounded font-mono font-bold text-slate-700">EXPELLED</span>
                </div>
                <button onclick="switchSystemDashboard('tms'); selectStageView(1);" class="px-4 py-2.5 rounded-xl bg-[#087F8A] hover:bg-[#0CA1AF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center cursor-pointer">
                    <i class="fa-solid fa-truck mr-1.5"></i>
                    <span>Kembali ke TMS &amp; Verifikasi Armada Baru</span>
                </button>
                <button onclick="executeStageAction(4, 'AUTO')" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-violet-50 text-violet-700 text-xs font-bold border border-violet-300 shadow-sm transition-all flex items-center cursor-pointer">
                    <i class="fa-solid fa-robot mr-1.5 text-xs"></i>
                    <span>Screening Ulang AI Agent AVSEC</span>
                </button>
            `;
            return;
        } else {
            // Belum screening X-Ray — diputuskan sepenuhnya oleh AI Agent
            container.innerHTML = `
                <div class="w-full mb-1">
                    <label class="inline-flex items-center gap-2 text-[11.5px] text-slate-600 cursor-pointer select-none">
                        <input type="checkbox" id="stage4-inject-anomaly" class="w-3.5 h-3.5 text-rose-600 rounded border-gray-300">
                        <span>Sisipkan anomali berbahaya tersembunyi di sensor X-Ray (opsional uji penyelundupan)</span>
                    </label>
                </div>
                <button onclick="executeStageAction(4, document.getElementById('stage4-inject-anomaly')?.checked ? 'SUSPECT' : 'AUTO')" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-[#087F8A] hover:from-violet-500 hover:to-[#0CA1AF] text-white font-bold text-xs shadow-md shadow-violet-500/25 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                    <i class="fa-solid fa-robot mr-2"></i>
                    <span>Jalankan Screening Otonom AI Agent AVSEC</span>
                </button>
                <button onclick="selectStageView(5)" class="px-4 py-2.5 rounded-xl bg-white hover:bg-teal-50 text-[#0D1C42] hover:text-[#087F8A] text-xs font-bold border border-teal-300 shadow-sm transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                    <i class="fa-solid fa-scale-balanced mr-1.5 text-xs text-[#087F8A]"></i>
                    <span>Lihat Tahap 5</span>
                    <i class="fa-solid fa-arrow-right ml-1.5 text-xs text-[#087F8A]"></i>
                </button>
                <button onclick="selectStageView(3)" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-600 hover:text-[#087F8A] text-xs font-bold border border-gray-300 shadow-sm transition-colors flex items-center cursor-pointer">
                    <i class="fa-solid fa-arrow-left mr-1.5 text-xs"></i>
                    <span>Lihat Tahap 3</span>
                </button>
            `;
            return;
        }
    }

    // ── TAHAP LAIN (1, 2, 3, 5, 6, 7) ──
    const isStageDone = (isLoaded || stage < currentCargoStage);

    if (isStageDone) {
        // TAHAP SUDAH SELESAI DILALUI
        const isStage7Final = (stage === 7 && isLoaded);
        container.innerHTML = `
            <div class="w-full mb-1 p-3 rounded-xl bg-emerald-50 border ${isStage7Final ? 'border-emerald-300 shadow-sm' : 'border-emerald-200'} text-emerald-900 text-xs flex items-center justify-between gap-2">
                <div>
                    <span class="font-bold block"><i class="fa-solid ${isStage7Final ? 'fa-paper-plane text-emerald-600' : 'fa-circle-check text-emerald-600'} mr-1.5"></i> ${isStage7Final ? 'SELURUH ALUR SELESAI — Kargo Sudah Terkirim &amp; Dimuat ke Pesawat!' : `Tahap ${stage} Telah Selesai Dilalui`}</span>
                    ${isStage7Final ? `<span class="text-[11px] text-emerald-700 block mt-0.5 pl-5">Electronic Loadsheet resmi terkirim ke Kokpit EFB (${escapeHtml(c.flight_number || 'GA-707')}) &bull; ULD ${escapeHtml(c.uld_code || 'AKE-12345-GA')} berada di kompartemen ${escapeHtml(c.compartment || 'AFT-LOWER-1')}.</span>` : ''}
                </div>
                <span class="text-[10px] ${isStage7Final ? 'bg-emerald-600 text-white' : 'bg-emerald-100 text-emerald-800'} px-2.5 py-0.5 rounded font-mono font-bold flex-shrink-0">${isStage7Final ? 'TERKIRIM &amp; SELESAI' : 'COMPLETED'}</span>
            </div>
            ${stage < 7 ? `
                <button onclick="selectStageView(${stage + 1})" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                    <span>Lanjut ke Tahap ${stage + 1}</span>
                    <i class="fa-solid fa-arrow-right ml-2 text-xs"></i>
                </button>
            ` : ''}
            <button onclick="executeStageAction(${stage}, 'CLEARED')" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 hover:text-[#087F8A] text-xs font-bold border border-gray-300 shadow-sm transition-all flex items-center cursor-pointer">
                <i class="fa-solid fa-rotate mr-1.5 text-xs text-slate-400"></i>
                <span>${isStage7Final ? 'Kirim Ulang Sync Loadsheet' : `Jalankan Ulang Tahap ${stage}`}</span>
            </button>
            ${stage > 1 ? `
                <button onclick="selectStageView(${stage - 1})" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-600 hover:text-[#087F8A] text-xs font-bold border border-gray-300 shadow-sm transition-colors flex items-center cursor-pointer">
                    <i class="fa-solid fa-arrow-left mr-1.5 text-xs"></i>
                    <span>Lihat Tahap ${stage - 1}</span>
                </button>
            ` : ''}
        `;
        return;
    }

    if (stage > currentCargoStage) {
        // TAHAP MENDATANG / TERKUNCI
        container.innerHTML = `
            <div class="w-full mb-1 p-2.5 rounded-xl bg-slate-100 border border-slate-200 text-slate-600 text-xs flex items-center justify-between">
                <span class="font-medium"><i class="fa-solid fa-lock text-slate-400 mr-1.5"></i> Tahap ${stage} Terkunci &mdash; Selesaikan Tahap ${currentCargoStage} Terlebih Dahulu</span>
                <span class="text-[10px] bg-slate-200 text-slate-600 px-2 py-0.5 rounded font-mono font-bold">LOCKED</span>
            </div>
            <button onclick="selectStageView(${currentCargoStage})" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                <i class="fa-solid fa-arrow-left mr-2"></i>
                <span>Buka Tahap Aktif (${currentCargoStage})</span>
            </button>
            ${stage > 1 ? `
                <button onclick="selectStageView(${stage - 1})" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-600 hover:text-[#087F8A] text-xs font-bold border border-gray-300 shadow-sm transition-colors flex items-center cursor-pointer">
                    <i class="fa-solid fa-arrow-left mr-1.5 text-xs"></i>
                    <span>Lihat Tahap ${stage - 1}</span>
                </button>
            ` : ''}
        `;
        return;
    }

    // TAHAP SAAT INI (AKTIF)
    let btnText = `Jalankan Tahap ${stage}`;
    let btnIcon = 'fa-play';
    if (stage === 1) { btnText = 'Daftarkan Truk di Dock (TMS)'; btnIcon = 'fa-truck'; }
    else if (stage === 2) { btnText = 'Pindai Tag RFID & Ingest JSON'; btnIcon = 'fa-code'; }
    else if (stage === 3) { btnText = 'Verifikasi e-AWB di CMS'; btnIcon = 'fa-laptop-code'; }
    else if (stage === 5) { btnText = 'Timbang di Smart Floor Scale'; btnIcon = 'fa-scale-balanced'; }
    else if (stage === 6) { btnText = 'Alokasikan ke ULD & Cek CoG'; btnIcon = 'fa-boxes-packing'; }
    else if (stage === 7) { btnText = 'Finalisasi Manifes & Load Pesawat'; btnIcon = 'fa-plane-departure'; }

    container.innerHTML = `
        <button onclick="executeStageAction(${stage}, 'CLEARED')" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
            <i class="fa-solid ${btnIcon} mr-2"></i>
            <span>${btnText}</span>
        </button>
        ${stage < 7 ? `
            <button onclick="selectStageView(${stage + 1})" class="px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-[#0D1C42] hover:text-[#087F8A] text-xs font-bold border border-gray-300 shadow-sm transition-colors flex items-center cursor-pointer">
                <span>Lihat Tahap ${stage + 1}</span>
                <i class="fa-solid fa-arrow-right ml-1.5 text-xs"></i>
            </button>
        ` : ''}
        ${stage > 1 ? `
            <button onclick="selectStageView(${stage - 1})" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-600 hover:text-[#087F8A] text-xs font-bold border border-gray-300 shadow-sm transition-colors flex items-center cursor-pointer">
                <i class="fa-solid fa-arrow-left mr-1.5 text-xs"></i>
                <span>Lihat Tahap ${stage - 1}</span>
            </button>
        ` : ''}
    `;
}

function executeStageAction(stage, avsecDecision) {
    if (!currentCargoData) return;

    // Aksi Khusus AVSEC: Kembalikan Barang ke Truk Semula
    if (stage === 4 && avsecDecision === 'RETURN_TO_TRUCK') {
        Swal.fire({
            title: 'Tolak & Kembalikan Kargo ke Truk?',
            html: `
                <div class="text-xs text-left p-3.5 rounded-xl bg-slate-50 border border-gray-200 space-y-2 mt-2">
                    <p class="font-bold text-rose-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Penolakan Kargo di AVSEC Gate:</p>
                    <p class="text-slate-600 leading-relaxed">
                        Kargo dengan nomor e-AWB <strong>${escapeHtml(currentCargoData.awb_number)}</strong> akan ditolak, dikeluarkan dari ruang karantina, dan <strong>dikembalikan ke armada truk semula (${escapeHtml(currentCargoData.truck_plate || 'Truk Pengangkut')})</strong>.
                    </p>
                    <p class="text-slate-500 text-[11px]">
                        Status manifes penerbangan kargo ini akan dibatalkan otomatis dan log audit keamanan akan dicatat.
                    </p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0f172a',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Ya, Kembalikan ke Truk',
            cancelButtonText: 'Batal',
            background: '#ffffff',
            color: '#0D1C42',
            customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
        }).then(result => {
            if (result.isConfirmed) {
                doExecuteStageAction(stage, 'RETURN_TO_TRUCK');
            }
        });
        return;
    }

    // Aksi Khusus AVSEC: Pemeriksaan Fisik Sekunder (Hand Search / ETD)
    if (stage === 4 && avsecDecision === 'RELEASE') {
        Swal.fire({
            title: 'Pemeriksaan Fisik Sekunder',
            html: `
                <div class="text-xs text-left p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 space-y-2 mt-2">
                    <p class="font-bold text-emerald-800"><i class="fa-solid fa-user-shield mr-1"></i> SOP Ruang Khusus Karantina AVSEC:</p>
                    <p class="text-[11.5px] text-emerald-800 leading-relaxed">
                        Petugas AVSEC bersertifikat melakukan pemeriksaan manual (Hand Search) dan deteksi jejak bahan peledak (Explosive Trace Detector / ETD) pada kargo <strong>${escapeHtml(currentCargoData.awb_number)}</strong> di dalam ruang karantina.
                    </p>
                    <p class="text-[11px] text-emerald-700">
                        Jika hasil bersih, kargo dirilis dari status SUSPECT menjadi <strong>CLEARED</strong> dan sertifikat CSD digital resmi diterbitkan.
                    </p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Verifikasi & Rilis (CLEARED)',
            cancelButtonText: 'Batal',
            background: '#ffffff',
            color: '#0D1C42',
            customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
        }).then(result => {
            if (result.isConfirmed) {
                doExecuteStageAction(stage, 'RELEASE');
            }
        });
        return;
    }

    doExecuteStageAction(stage, avsecDecision);
}

function doExecuteStageAction(stage, avsecDecision) {
    let actionParam = 'advance';
    if (avsecDecision === 'SUSPECT') actionParam = 'force_suspect';

    let loadingTitle = `Memproses Tahap ${stage}...`;
    if (avsecDecision === 'RELEASE') loadingTitle = 'Pemeriksaan Fisik Sekunder di Karantina...';
    if (avsecDecision === 'RETURN_TO_TRUCK') loadingTitle = 'Mengembalikan Kargo ke Truk Semula...';

    Swal.fire({
        title: loadingTitle,
        text: 'Mengirimkan instruksi ke sistem...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#ffffff',
        color: '#0D1C42',
        customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
    });

    fetch('api/simulate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            cargo_id: currentCargoData.id,
            stage: stage,
            action: actionParam,
            avsec_decision: avsecDecision
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (res.cargo) {
                currentCargoData = res.cargo;
                renderCargoHeader(currentCargoData);
            }
            if (res.tms_data) currentTmsData = res.tms_data;
            if (res.tms_trucks) recentTrucksList = res.tms_trucks;
            if (typeof res.tms_total_trucks === 'number') totalTrucksCount = res.tms_total_trucks;
            if (res.payload) {
                currentMasterPayload = res.payload;
            }
            if (res.agent) {
                currentAgentDecision = res.agent;
            }
            if (res.loadmaster_agent) {
                currentLoadmasterDecision = res.loadmaster_agent;
            }
            if (Array.isArray(res.ulds)) {
                currentUldsList = res.ulds;
            }

            const resultIsSuspect = (avsecDecision === 'SUSPECT') || (stage === 4 && res.cargo && res.cargo.status === 'SUSPECT');
            const resultIsCca = (stage === 5 && res.loadmaster_agent && res.loadmaster_agent.decision === 'CCA_AMENDMENT');
            const resultIsStage7 = (stage === 7 && res.cargo && res.cargo.status === 'LOADED');
            let iconType = 'success';
            let btnColor = '#087F8A';
            let alertTitle = 'Tahap Berhasil Diproses!';
            if (resultIsSuspect) {
                iconType = 'warning';
                btnColor = '#e11d48';
                alertTitle = 'Peringatan AVSEC: SUSPECT!';
            } else if (resultIsCca) {
                iconType = 'warning';
                btnColor = '#d97706';
                alertTitle = 'AI Loadmaster: Terbitkan e-CCA!';
            } else if (resultIsStage7) {
                iconType = 'success';
                btnColor = '#059669';
                alertTitle = 'Selesai & Terkirim ke Pesawat!';
            } else if (avsecDecision === 'RETURN_TO_TRUCK') {
                iconType = 'info';
                btnColor = '#0f172a';
            }

            Swal.fire({
                title: alertTitle,
                text: res.message,
                icon: iconType,
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: btnColor,
                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
            }).then(() => {
                let nextView = stage;
                if (!resultIsSuspect && (avsecDecision === 'CLEARED' || avsecDecision === 'RELEASE') && stage < 7) {
                    nextView = stage + 1;
                } else if (avsecDecision === 'RETURN_TO_TRUCK') {
                    nextView = 1;
                }
                initSimulation(currentCargoData.id, nextView, currentSystemDashboard);
            });
        } else {
            if (res.loadmaster_agent) {
                currentLoadmasterDecision = res.loadmaster_agent;
                renderLoadmasterAgentPanelBox(currentLoadmasterDecision);
            }
            Swal.fire({
                title: res.overload ? 'AI Loadmaster: OVERLOAD PREVENTED!' : 'Peringatan Operasional!',
                text: res.message,
                icon: 'error',
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: '#e11d48',
                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
            });
        }
    })
    .catch(err => Swal.fire({
        title: 'Error',
        text: 'Gagal memproses tahap: ' + err,
        icon: 'error',
        background: '#ffffff',
        color: '#0D1C42',
        confirmButtonColor: '#087F8A',
        customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
    }));
}

function triggerAutoRun() {
    if (!currentCargoData) return;

    Swal.fire({
        title: 'Jalankan Auto-Run Demo?',
        text: 'Sistem akan mengeksekusi Tahap 1 hingga Tahap 7 secara berurutan secara otomatis untuk demonstrasi kepada klien.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#087F8A',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Jalankan Demo!',
        cancelButtonText: 'Batal',
        background: '#ffffff',
        color: '#0D1C42',
        customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Menjalankan Aliran Data End-to-End...',
                text: 'Memindahkan data melalui 7 tahapan...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); },
                background: '#ffffff',
                color: '#0D1C42',
                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
            });

            fetch('api/simulate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    cargo_id: currentCargoData.id,
                    action: 'auto_run'
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (res.payload) {
                        currentMasterPayload = res.payload;
                    }
                    if (res.loadmaster_agent) {
                        currentLoadmasterDecision = res.loadmaster_agent;
                    }
                    if (Array.isArray(res.ulds)) {
                        currentUldsList = res.ulds;
                    }
                    Swal.fire({
                        title: 'Selesai & Terkirim ke Pesawat!',
                        text: res.message || 'Semua 7 tahapan kargo telah selesai hingga status LOADED, kargo dimuat ke pesawat, dan Electronic Loadsheet resmi terkirim ke kokpit GA-707.',
                        icon: 'success',
                        background: '#ffffff',
                        color: '#0D1C42',
                        confirmButtonColor: '#059669',
                        customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
                    }).then(() => {
                        initSimulation(currentCargoData.id, 7, currentSystemDashboard);
                    });
                } else {
                    Swal.fire({
                        title: 'Peringatan',
                        text: res.message,
                        icon: 'warning',
                        background: '#ffffff',
                        color: '#0D1C42',
                        confirmButtonColor: '#087F8A',
                        customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
                    });
                }
            })
            .catch(err => Swal.fire({
                title: 'Error',
                text: 'Auto-run error: ' + err,
                icon: 'error',
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: '#087F8A',
                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
            }));
        }
    });
}

function updatePayloadInspector(stage) {
    const c = currentCargoData;
    if (!c) return;

    // Prioritaskan payload terintegrasi dari Master Cargo Document jika tersedia
    if (currentMasterPayload && currentMasterPayload.stages && currentMasterPayload.stages[stage]) {
        currentRequestJson = currentMasterPayload.stages[stage].request_payload;
        currentResponseJson = currentMasterPayload.stages[stage].response_payload;
        displayActivePayload();
        return;
    }

    // Default payload matching stage
    if (stage === 1) {
        currentRequestJson = {
            "event": "TMS_TRUCK_GATE_CHECKIN",
            "data_source": "SINGLE_ENTRY_INPUT_KARGO (ZERO_REENTRY)",
            "filtered_domain": "TMS_TRANSPORT_AND_DOCK_ONLY",
            "booking_code": c.booking_code || "TAS-SLOT-9482",
            "truck": {
                "truck_id": c.truck_arrival_id || "TRK-INBOUND",
                "plate_number": c.truck_plate || "B 9482 KXT",
                "driver": c.driver_name || "Bambang Supriyanto",
                "transporter": c.transporter_company || "PT Indotruck Multimoda Logistik"
            },
            "allocated_dock": c.dock_slot || "DOCK-04",
            "truck_status": c.truck_status || "SCHEDULED",
            "eta_timestamp": c.arrival_time || new Date().toISOString()
        };
        currentResponseJson = {
            "status": "200_OK",
            "message": "Slot dock terkonfirmasi dari Input Kargo dan reservasi aktif di gerbang TMS",
            "gate_access_token": "GATE-CLR-88192"
        };
    } else if (stage === 2) {
        currentRequestJson = {
            "event_type": "RFID_AIDC_GATE_SCAN",
            "device_id": "SCANNER-GATE-CONVEYOR-01",
            "hardware_specs": {
                "type": "High-Speed Barcode/RFID Tunnel Scanner",
                "antenna": "UHF Impinj Indy R2000 865-868MHz",
                "conveyor_speed": "2.5 m/s",
                "ip_rating": "IP65"
            },
            "gs1_data": {
                "ai_00_sscc": c.sscc,
                "ai_01_gtin": c.gtin,
                "ai_10_batch_no": c.batch_no,
                "ai_17_expiry_date": c.expiry_date,
                "quantity_koli": parseInt(c.quantity)
            },
            "scanned_at": new Date().toISOString()
        };
        currentResponseJson = {
            "status": "200_OK",
            "message": "Tag SSCC berhasil diidentifikasi dan dikirim ke CMS",
            "cms_ingest_token": "CMS-TOKEN-84A1C9",
            "next_action": "CMS_REGISTRATION"
        };
    } else if (stage === 3) {
        currentRequestJson = {
            "event_type": "CMS_AWB_MATCHING",
            "action": "VALIDATE_ELECTRONIC_AIR_WAYBILL",
            "e_awb_number": c.awb_number,
            "matched_sscc": c.sscc,
            "shipment_details": {
                "shipper": c.shipper,
                "consignee": c.consignee,
                "commodity": c.description,
                "commodity_class": c.commodity_type,
                "declared_pieces": parseInt(c.quantity),
                "declared_weight_kg": parseFloat(c.declared_weight_kg),
                "origin": "CGK",
                "destination": "DPS"
            },
            "timestamp": new Date().toISOString()
        };
        currentResponseJson = {
            "status": "200_OK",
            "cms_status": "AWB_VERIFIED",
            "routing_instruction": "MOVE_TO_AVSEC_XRAY_LANE_02",
            "priority_level": c.commodity_type === 'PHARMA' ? "URGENT_COLD_CHAIN" : "STANDARD"
        };
    } else if (stage === 4) {
        const isSuspect = c.status === 'SUSPECT';
        currentRequestJson = {
            "event_type": "SECURITY_SCREENING_EVALUATION",
            "cargo_id": c.id,
            "awb_number": c.awb_number,
            "scanner_id": "XRAY-DUALVIEW-01",
            "tunnel_dimensions": "180 cm x 180 cm",
            "penetration_steel": "80 mm",
            "inspection_result": isSuspect ? "SUSPECT" : "CLEARED",
            "inspector_badge": "AVSEC-OPR-07",
            "csd_certificate": isSuspect ? null : "CSD-ID-20260926-4412",
            "timestamp": new Date().toISOString()
        };
        currentResponseJson = {
            "status": isSuspect ? "403_FORBIDDEN" : "200_OK",
            "security_clearance": isSuspect ? "SUSPECT" : "CLEARED",
            "action_required": isSuspect ? "HOLD_AND_ISOLATE_CARGO" : "PROCEED_TO_WEIGHING_STATION",
            "alert_triggered": isSuspect
        };
    } else if (stage === 5) {
        const declared = parseFloat(c.declared_weight_kg || 150.0);
        const actual = c.actual_weight_kg ? parseFloat(c.actual_weight_kg) : declared;
        const diff = +(actual - declared).toFixed(2);
        const diffPct = declared > 0 ? ((diff / declared) * 100).toFixed(2) : '0.00';
        const maxTol = +(declared * 0.05).toFixed(2);
        const isWithinTol = declared > 0 ? (Math.abs(diff / declared) <= 0.05) : true;
        const isHea = (declared >= 150.0 || actual >= 150.0);

        currentRequestJson = {
            "event_type": "CARGO_WEIGHT_ACQUISITION",
            "device_id": "SCALE-FLOOR-ULD-01",
            "device_specs": {
                "platform": "3.5m x 2.5m Steel Floor Scale",
                "max_capacity_ton": 15,
                "accuracy_kg": 1.0,
                "oiml_class": "OIML R76 Class III",
                "sensor_protection": "IP68 Hermetic Load Cells"
            },
            "weight_data": {
                "cargo_id": c.id,
                "awb_number": c.awb_number,
                "declared_gross_weight_kg": declared,
                "actual_scale_weight_kg": actual,
                "discrepancy_kg": diff,
                "discrepancy_percent": `${diffPct}%`,
                "iata_tolerance_limit": `±5.00% (Maks ±${maxTol} kg)`,
                "tolerance_status": isWithinTol ? "PASSED_WITHIN_TOLERANCE" : "EXCEEDED_TOLERANCE_WARNING",
                "iata_special_handling": isHea ? "HEA (Heavy Cargo >=150kg)" : "GEN (General Cargo)"
            },
            "flight_loading_criteria": {
                "max_ake_net_payload_kg": 1513.0,
                "max_uld_gross_weight_kg": 1588.0,
                "aircraft_type": "Boeing 737-800F (GA-707)",
                "criteria_status": actual <= 1513.0 ? "APPROVED_FOR_AKE_LOADING" : "REQUIRES_PMC_OR_BREAKBULK"
            },
            "weighed_at": new Date().toISOString()
        };
        currentResponseJson = {
            "status": "200_OK",
            "message": isWithinTol 
                ? "Data berat aktual terverifikasi dalam batas toleransi IATA (±5%). Siap untuk ULD build-up." 
                : "Peringatan: Selisih berat aktual melebihi batas toleransi IATA (±5%). Amandemen e-AWB (CCA) diterbitkan, berat fisik disinkronkan ke Weight & Balance.",
            "allow_uld_buildup": true,
            "document_amendment_required": !isWithinTol,
            "criteria_result": isWithinTol ? "PASSED_WITHIN_TOLERANCE" : "DISCREPANCY_FLAGGED_PASSED_PHYSICAL"
        };
    } else if (stage === 6) {
        const netCargo = c.actual_weight_kg ? parseFloat(c.actual_weight_kg) : parseFloat(c.declared_weight_kg || 150.0);
        const tareWeight = 75.0;
        const grossWeight = +(netCargo + tareWeight).toFixed(2);
        const maxCapacity = parseFloat(c.uld_max_weight || 1588.0);
        const isOverload = grossWeight > maxCapacity;
        const utilPct = +((grossWeight / maxCapacity) * 100).toFixed(1);

        currentRequestJson = {
            "event_type": "ULD_CONSOLIDATION_AND_LOAD_PLANNING",
            "cargo_id": c.id,
            "target_uld": {
                "uld_code": c.uld_code || "AKE-12345-GA",
                "uld_type": c.uld_type || "AKE",
                "tare_weight_kg": tareWeight,
                "net_cargo_weight_kg": netCargo,
                "gross_uld_weight_kg": grossWeight,
                "max_certified_capacity_kg": maxCapacity,
                "utilization_percent": utilPct,
                "structural_status": isOverload ? "OVERLOAD_EXCEEDS_AKE_LIMIT" : "COMPLIANT_WITHIN_AKE_LIMIT"
            },
            "weight_and_balance_engine": {
                "aircraft": "Boeing 737-800F",
                "assigned_compartment": c.compartment || "AFT-LOWER-1",
                "center_of_gravity_index": 28.4,
                "cg_safety_envelope": "18.0% - 34.0% MAC",
                "within_safety_envelope": true,
                "floor_load_limit_status": "WITHIN_STRUCTURAL_LIMIT"
            },
            "timestamp": new Date().toISOString()
        };
        currentResponseJson = {
            "status": isOverload ? "422_OVERLOAD_WARNING" : "200_OK",
            "message": isOverload 
                ? `Beban gross ULD (${grossWeight} kg) melebihi batas struktural kontainer AKE (1.588 kg). Diperlukan pengalihan ke palet PMC!` 
                : `Kargo berhasil dikonsolidasikan ke dalam kontainer ULD ${c.uld_code || 'AKE-12345-GA'} (Gross: ${grossWeight} kg).`,
            "load_position_locked": !isOverload
        };
    } else if (stage === 7) {
        const isLoaded = (c.status === 'LOADED');
        const cargoWt = parseFloat(c.actual_weight_kg || c.declared_weight_kg || 150.0);
        const grossUld = +(cargoWt + 75.0).toFixed(1);
        const flightNum = c.flight_number || "GA-707";
        const dest = c.flight_destination || "DPS";
        currentRequestJson = {
            "event_type": "FLIGHT_MANIFEST_FINALIZATION",
            "flight_manifest_id": "FL-GA707-2026",
            "flight_number": flightNum,
            "loadsheet_number": `LS-${flightNum.replace(/[^A-Z0-9]/g, '')}-${(new Date()).toISOString().slice(0, 10).replace(/-/g, '')}`,
            "aircraft_type": c.aircraft_type || "Boeing 737-800F",
            "route": `CGK -> ${dest}`,
            "loaded_uld_list": [
                {
                    "uld_code": c.uld_code || "AKE-12345-GA",
                    "final_weight_kg": grossUld,
                    "contains_cargo_awb": c.awb_number,
                    "ramp_loading_status": isLoaded ? "TRANSFERRED_TO_AIRCRAFT_BELLY" : "PENDING_RAMP_LOADING"
                }
            ],
            "total_cargo_payload_kg": grossUld,
            "departure_clearance": isLoaded ? "APPROVED" : "PENDING",
            "timestamp": new Date().toISOString()
        };
        currentResponseJson = {
            "status": isLoaded ? "200_OK_DISPATCHED" : "PENDING_EXECUTION",
            "message": isLoaded
                ? "SELESAI & TERKIRIM: Manifes penerbangan final telah diterbitkan. Kargo sudah dimuat ke pesawat & Loadsheet resmi terkirim ke kokpit & flight ops."
                : "Menunggu eksekusi Tahap 7 untuk penerbitan manifes final & pemuatan ke pesawat.",
            "delivery_status": isLoaded ? "SENT_TO_COCKPIT_AND_LOADED" : "PENDING_DISPATCH",
            "electronic_loadsheet_status": isLoaded ? "SENT_TO_COCKPIT_EFB" : "DRAFT_AWAITING_DISPATCH",
            "aircraft_ramp_status": isLoaded ? "LOADED_AND_READY_FOR_PUSHBACK" : "AWAITING_FINAL_DISPATCH",
            "workflow_completed": isLoaded
        };
    }

    displayActivePayload();
}

function switchPayloadTab(tab) {
    activePayloadTab = tab;
    const reqBtn = document.getElementById('tabBtnRequest');
    const resBtn = document.getElementById('tabBtnResponse');
    const masBtn = document.getElementById('tabBtnMaster');

    const activeClass = 'flex-1 py-1.5 px-2 rounded-lg text-xs font-bold bg-[#087F8A] text-white shadow-sm transition-all flex items-center justify-center cursor-pointer';
    const inactiveClass = 'flex-1 py-1.5 px-2 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:text-slate-800 border border-gray-200 transition-all flex items-center justify-center cursor-pointer';

    if (reqBtn) reqBtn.className = (tab === 'request') ? activeClass : inactiveClass;
    if (resBtn) resBtn.className = (tab === 'response') ? activeClass : inactiveClass;
    if (masBtn) masBtn.className = (tab === 'master') ? activeClass : inactiveClass;

    displayActivePayload();
}

function displayActivePayload() {
    const display = document.getElementById('jsonPayloadDisplay');
    if (!display) return;
    let targetObj = currentRequestJson;
    if (activePayloadTab === 'response') {
        targetObj = currentResponseJson;
    } else if (activePayloadTab === 'master') {
        targetObj = currentMasterPayload || {
            "cargo_payload_id": currentCargoData ? ('CMP-' + currentCargoData.id) : "CMP-CGO",
            "message": "Memuat Master JSON Payload kargo..."
        };
    }
    display.textContent = JSON.stringify(targetObj, null, 2);
}

function copyPayloadToClipboard() {
    const text = document.getElementById('jsonPayloadDisplay').textContent;
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            title: 'JSON berhasil disalin ke clipboard!',
            icon: 'success',
            showConfirmButton: false,
            timer: 2000,
            background: '#ffffff',
            color: '#0D1C42',
            customClass: { popup: 'rounded-xl shadow-lg border border-gray-200' }
        });
    });
}

function renderAuditTimeline(logs) {
    const container = document.getElementById('auditTimelineContainer');
    document.getElementById('logCountBadge').textContent = `${logs ? logs.length : 0} Record`;

    if (!logs || logs.length === 0) {
        container.innerHTML = '<p class="text-slate-400 text-xs text-center py-4">Belum ada log transaksi.</p>';
        return;
    }

    let html = '';
    logs.forEach(log => {
        const timeStr = new Date(log.timestamp).toLocaleTimeString();
        html += `
            <div class="p-3 rounded-xl bg-slate-50 border border-gray-200 text-xs hover:border-[#0CA1AF] transition-colors">
                <div class="flex items-center justify-between mb-1">
                    <span class="font-bold text-[#0D1C42] flex items-center">
                        <span class="w-2 h-2 rounded-full bg-[#087F8A] mr-2"></span>
                        Tahap ${log.stage}: ${log.stage_name}
                    </span>
                    <span class="text-[10px] font-mono text-slate-400 font-semibold">${timeStr}</span>
                </div>
                <p class="text-[11.5px] text-slate-600 pl-4">${log.action}</p>
                <div class="mt-1.5 flex items-center justify-between text-[10px] text-slate-500 pl-4">
                    <span>HW: <strong class="text-slate-700 font-semibold">${log.hardware_device}</strong></span>
                    <span class="font-mono text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">HTTP ${log.http_status}</span>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// =========================================================================
// SYSTEM DASHBOARD SWITCHER & SPECIALIZED OPERATIONAL RENDERERS
// (TMS, CMS, AVSEC System, Load & Balance / Load Planning System)
// =========================================================================

function switchSystemDashboard(systemKey, updateUrl = true) {
    const validKeys = ['all', 'tms', 'cms', 'avsec', 'load_balance'];
    if (!validKeys.includes(systemKey)) systemKey = 'all';
    currentSystemDashboard = systemKey;

    // Toggle button active styles
    validKeys.forEach(k => {
        const btn = document.getElementById(`tabBtn-${k}`);
        if (btn) {
            const pill = btn.querySelector('span:last-child');
            if (k === systemKey) {
                btn.className = 'system-tab-btn px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 bg-[#087F8A] text-white shadow-md shadow-teal-500/20';
                if (pill) pill.className = 'text-[9px] px-2 py-0.5 rounded-full bg-white/20 text-white font-extrabold tracking-wide uppercase';
            } else {
                btn.className = 'system-tab-btn px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 bg-slate-50 text-slate-700 hover:bg-teal-50 hover:text-[#087F8A] border border-gray-200';
                if (pill) pill.className = 'text-[9px] px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 font-extrabold';
            }
        }

        // Toggle view visibility
        const viewEl = document.getElementById(`view-system-${k}`);
        if (viewEl) {
            if (k === systemKey) {
                viewEl.classList.remove('hidden');
            } else {
                viewEl.classList.add('hidden');
            }
        }
    });

    // Update breadcrumb label
    const indicatorText = document.getElementById('activeSystemIndicatorText');
    if (indicatorText) {
        const labelMap = {
            'all': 'Alur Terpadu End-to-End',
            'tms': 'Dashboard TMS (Tahap 1)',
            'cms': 'Dashboard CMS (Tahap 2-3)',
            'avsec': 'Dashboard AVSEC System (Tahap 4)',
            'load_balance': 'Dashboard Load & Balance (Tahap 5-7)'
        };
        indicatorText.textContent = labelMap[systemKey] || 'Alur Terpadu End-to-End';
    }

    // Update URL query string without reloading page
    if (updateUrl && window.history && window.history.replaceState) {
        const url = new URL(window.location);
        url.searchParams.set('system', systemKey);
        window.history.replaceState({}, '', url);
    }

    // Sembunyikan banner komoditas/berat/SSCC saat berada di Dashboard TMS murni (khusus data transportasi)
    const cargoHeader = document.getElementById('cargoHeaderCard');
    if (cargoHeader) {
        if (systemKey === 'tms') {
            cargoHeader.classList.add('hidden');
        } else {
            cargoHeader.classList.remove('hidden');
        }
    }

    // Refresh data in specialized views
    if (currentCargoData) {
        renderTMSDashboard(currentCargoData, currentTmsData, recentTrucksList, totalTrucksCount);
        renderCMSDashboard(currentCargoData);
        renderAVSECDashboard(currentCargoData);
        renderLoadBalanceDashboard(currentCargoData);
    }
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

let dockLiveState = {
    'DOCK-01': { occupied: false, isActiveCargoDock: false, plate: null, driver: null, transporter: null, bookingCode: null, truckId: null, status: 'Standby Leveler', arrivalTime: null },
    'DOCK-02': { occupied: false, isActiveCargoDock: false, plate: null, driver: null, transporter: null, bookingCode: null, truckId: null, status: 'Standby Leveler', arrivalTime: null },
    'DOCK-03': { occupied: false, isActiveCargoDock: false, plate: null, driver: null, transporter: null, bookingCode: null, truckId: null, status: 'Standby Leveler', arrivalTime: null },
    'DOCK-04': { occupied: false, isActiveCargoDock: false, plate: null, driver: null, transporter: null, bookingCode: null, truckId: null, status: 'Standby Leveler', arrivalTime: null }
};

function getTmsStatusBadgeClass(status) {
    switch (String(status || 'SCHEDULED').toUpperCase()) {
        case 'ARRIVED':
            return 'px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-teal-50 text-[#087F8A] border border-teal-200';
        case 'UNLOADING':
            return 'px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-50 text-blue-700 border border-blue-200';
        case 'COMPLETED':
            return 'px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200';
        case 'SCHEDULED':
        default:
            return 'px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-50 text-amber-700 border border-amber-200';
    }
}

function getResolvedTmsData(c = currentCargoData, tmsData = currentTmsData) {
    if (tmsData && typeof tmsData === 'object') {
        return {
            truck_id: tmsData.truck_id || (c ? c.truck_arrival_id : null) || '-',
            truck_plate: tmsData.truck_plate || (c ? c.truck_plate : null) || '-',
            driver_name: tmsData.driver_name || (c ? c.driver_name : null) || '-',
            transporter_company: tmsData.transporter_company || (c ? c.transporter_company : null) || '-',
            dock_slot: tmsData.dock_slot || (c ? c.dock_slot : null) || 'DOCK-02',
            booking_code: tmsData.booking_code || (c ? c.booking_code : null) || '-',
            arrival_time: tmsData.arrival_time || (c ? c.arrival_time : null) || '-',
            status: tmsData.truck_status || tmsData.status || (c ? c.truck_status : null) || ((c && parseInt(c.current_stage || 1) >= 2) ? 'ARRIVED' : 'SCHEDULED')
        };
    }
    if (!c) {
        return {
            truck_id: '-',
            truck_plate: '-',
            driver_name: '-',
            transporter_company: '-',
            dock_slot: 'DOCK-02',
            booking_code: '-',
            arrival_time: '-',
            status: 'SCHEDULED'
        };
    }
    return {
        truck_id: c.truck_arrival_id || '-',
        truck_plate: c.truck_plate || '-',
        driver_name: c.driver_name || '-',
        transporter_company: c.transporter_company || '-',
        dock_slot: c.dock_slot || 'DOCK-02',
        booking_code: c.booking_code || '-',
        arrival_time: c.arrival_time || '-',
        status: c.truck_status || (parseInt(c.current_stage || 1) >= 2 ? 'ARRIVED' : 'SCHEDULED')
    };
}

function buildDockLiveState(trucksList = recentTrucksList, activeTms = null) {
    const docks = ['DOCK-01', 'DOCK-02', 'DOCK-03', 'DOCK-04'];
    const state = {};
    docks.forEach(d => {
        state[d] = {
            occupied: false,
            isActiveCargoDock: false,
            plate: null,
            driver: null,
            transporter: null,
            bookingCode: null,
            truckId: null,
            status: 'Standby Leveler',
            arrivalTime: null
        };
    });

    // Isi dock berdasarkan antrean armada aktif dari database (ARRIVED / UNLOADING)
    if (Array.isArray(trucksList)) {
        trucksList.forEach(trk => {
            const slot = trk.dock_slot;
            if (!slot || !state[slot]) return;
            const st = String(trk.status || '').toUpperCase();
            if ((st === 'ARRIVED' || st === 'UNLOADING') && !state[slot].occupied) {
                state[slot] = {
                    occupied: true,
                    isActiveCargoDock: Boolean(activeTms && (trk.id === activeTms.truck_id || slot === activeTms.dock_slot)),
                    plate: trk.truck_plate || '-',
                    driver: trk.driver_name || '-',
                    transporter: trk.transporter_company || '-',
                    bookingCode: trk.booking_code || '-',
                    truckId: trk.id || '-',
                    status: st,
                    arrivalTime: trk.arrival_time || null
                };
            }
        });
    }

    // Pastikan alokasi dock armada aktif selalu terefleksi
    if (activeTms && activeTms.dock_slot && state[activeTms.dock_slot]) {
        const slot = activeTms.dock_slot;
        const activeStatus = String(activeTms.status || 'SCHEDULED').toUpperCase();
        const isPhysicallyInDock = (activeStatus === 'ARRIVED' || activeStatus === 'UNLOADING');
        state[slot] = {
            occupied: isPhysicallyInDock,
            isActiveCargoDock: true,
            plate: activeTms.truck_plate || '-',
            driver: activeTms.driver_name || '-',
            transporter: activeTms.transporter_company || '-',
            bookingCode: activeTms.booking_code || '-',
            truckId: activeTms.truck_id || '-',
            status: activeStatus,
            arrivalTime: activeTms.arrival_time || null
        };
    }

    dockLiveState = state;
    return state;
}

function renderDockCards(activeTms = null) {
    const tms = activeTms || getResolvedTmsData();
    const state = buildDockLiveState(recentTrucksList, tms);
    const docks = ['DOCK-01', 'DOCK-02', 'DOCK-03', 'DOCK-04'];
    const selectedSlot = tms.dock_slot || 'DOCK-02';

    let occupiedCount = 0;
    let availableCount = 0;

    docks.forEach(d => {
        const info = state[d];
        const card = document.getElementById(`dock-card-${d}`);
        const icon = document.getElementById(`dock-icon-${d}`);
        const plate = document.getElementById(`dock-plate-${d}`);
        const status = document.getElementById(`dock-status-${d}`);
        const badge = document.getElementById(`dock-badge-${d}`);
        const sub = document.getElementById(`dock-sub-${d}`);
        if (!card) return;

        const titleSpan = card.querySelector('div > span:first-child') || card.querySelector('span:first-child');

        if (d === selectedSlot) {
            const st = String(tms.status || 'SCHEDULED').toUpperCase();
            if (st === 'ARRIVED' || st === 'UNLOADING') {
                occupiedCount++;
            } else {
                availableCount++;
            }

            card.className = 'p-3.5 rounded-xl border-2 border-[#0CA1AF] bg-teal-50/70 text-center transition-all shadow-md ring-2 ring-teal-400/30 cursor-pointer relative overflow-hidden group select-none';
            card.title = `Alokasi armada aktif ${tms.truck_plate} (${st}). Klik untuk lihat detail TMS.`;
            if (titleSpan) titleSpan.className = 'text-[10px] font-bold text-[#087F8A] uppercase font-mono';
            if (badge) {
                badge.className = 'text-[8.5px] px-1.5 py-0.5 rounded font-extrabold uppercase bg-teal-100 text-[#087F8A] border border-teal-300';
                badge.textContent = st === 'SCHEDULED' ? 'BOOKED' : (st === 'COMPLETED' ? 'SELESAI' : 'AKTIF');
            }
            if (icon) {
                icon.className = st === 'COMPLETED'
                    ? 'my-2 text-2xl text-emerald-600 group-hover:scale-105 transition-transform'
                    : 'my-2 text-2xl text-[#087F8A] group-hover:scale-105 transition-transform';
                icon.innerHTML = st === 'COMPLETED' ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-truck"></i>';
            }
            if (plate) {
                plate.className = 'text-xs font-black text-[#0D1C42] block font-mono truncate';
                plate.textContent = tms.truck_plate || '-';
            }
            if (status) {
                status.className = 'text-[9px] text-teal-800 font-bold block mt-0.5 truncate';
                status.textContent = st === 'SCHEDULED' ? 'Slot Terjadwal (TAS)' : (st === 'UNLOADING' ? 'Sedang Bongkar' : (st === 'COMPLETED' ? 'Bongkar Selesai' : 'Di Gate / Dock'));
            }
            if (sub) {
                sub.textContent = tms.booking_code || 'Klik Info';
            }
        } else if (info.occupied) {
            occupiedCount++;
            card.className = 'p-3.5 rounded-xl border border-rose-300 bg-rose-50/60 text-center transition-all cursor-pointer hover:border-rose-400 hover:shadow-md relative overflow-hidden group select-none';
            card.title = `TERISI - Digunakan oleh ${info.plate}. Klik untuk inspeksi.`;
            if (titleSpan) titleSpan.className = 'text-[10px] font-bold text-rose-800 uppercase font-mono';
            if (badge) {
                badge.className = 'text-[8.5px] px-1.5 py-0.5 rounded font-extrabold uppercase bg-rose-200 text-rose-800 border border-rose-300';
                badge.textContent = 'TERISI';
            }
            if (icon) {
                icon.className = 'my-2 text-2xl text-rose-500 group-hover:scale-105 transition-transform';
                icon.innerHTML = '<i class="fa-solid fa-truck-ramp-box"></i>';
            }
            if (plate) {
                plate.className = 'text-xs font-bold text-rose-950 block font-mono truncate';
                plate.textContent = info.plate || '-';
            }
            if (status) {
                status.className = 'text-[9px] text-rose-700 font-bold block mt-0.5 truncate';
                status.textContent = info.status === 'UNLOADING' ? 'Sedang Bongkar' : 'Tiba di Dock';
            }
            if (sub) {
                sub.textContent = info.bookingCode || 'Klik Info';
            }
        } else {
            availableCount++;
            card.className = 'p-3.5 rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50/20 text-center transition-all cursor-pointer hover:border-emerald-500 hover:bg-emerald-50 hover:shadow-md relative overflow-hidden group select-none';
            card.title = 'Dock KOSONG - Klik untuk melihat status dock';
            if (titleSpan) titleSpan.className = 'text-[10px] font-bold text-emerald-700 uppercase font-mono';
            if (badge) {
                badge.className = 'text-[8.5px] px-1.5 py-0.5 rounded font-extrabold uppercase bg-emerald-100 text-emerald-700 border border-emerald-200';
                badge.textContent = 'KOSONG';
            }
            if (icon) {
                icon.className = 'my-2 text-2xl text-emerald-500 group-hover:scale-110 transition-transform';
                icon.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
            }
            if (plate) {
                plate.className = 'text-xs font-bold text-emerald-700 block font-mono';
                plate.textContent = 'TERSEDIA';
            }
            if (status) {
                status.className = 'text-[9px] text-emerald-700 font-semibold block mt-0.5';
                status.textContent = 'Standby Leveler';
            }
            if (sub) {
                sub.textContent = 'Siap Alokasi';
            }
        }
    });

    const occBadge = document.getElementById('tms-dock-occupied-badge');
    if (occBadge) occBadge.textContent = `${occupiedCount} Terisi`;
    const availBadge = document.getElementById('tms-dock-available-badge');
    if (availBadge) availBadge.textContent = `${availableCount} Kosong`;
    const sumStat = document.getElementById('tms-dock-summary-stat');
    if (sumStat) sumStat.innerHTML = `${occupiedCount}/4 Terisi <span class="text-xs text-slate-500 font-sans font-normal">${availableCount} Kosong</span>`;
}

function handleDockCardClick(dockSlot) {
    const dockInfo = dockLiveState[dockSlot];
    const tms = getResolvedTmsData();

    if (dockInfo && (dockInfo.occupied || dockInfo.isActiveCargoDock)) {
        Swal.fire({
            title: `Inspeksi ${dockSlot}`,
            html: `
                <div class="text-left text-xs p-3.5 rounded-xl bg-slate-50 border border-gray-200 text-slate-700 space-y-2 mt-2">
                    <div class="flex items-center justify-between pb-1.5 border-b border-gray-200">
                        <span class="text-[#087F8A] font-extrabold uppercase text-[10px]">Telemetry Dock (Database TMS)</span>
                        <span class="${getTmsStatusBadgeClass(dockInfo.status)}">${escapeHtml(dockInfo.status)}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Nomor Plat Truk:</span>
                        <strong class="font-mono text-[#0D1C42]">${escapeHtml(dockInfo.plate || tms.truck_plate)}</strong>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Nama Supir:</span>
                        <span class="font-semibold text-slate-800">${escapeHtml(dockInfo.driver || tms.driver_name)}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Vendor Ekspedisi:</span>
                        <span class="text-slate-700">${escapeHtml(dockInfo.transporter || tms.transporter_company)}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Kode Booking TAS:</span>
                        <span class="font-mono font-bold text-[#087F8A]">${escapeHtml(dockInfo.bookingCode || tms.booking_code)}</span>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-slate-500 text-left">Alokasi dock diatur satu kali saat <strong>Input Kargo</strong> (Zero Re-entry Data).</p>
            `,
            icon: 'info',
            background: '#ffffff',
            color: '#0D1C42',
            confirmButtonColor: '#087F8A',
            confirmButtonText: 'Tutup',
            customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
        });
        return;
    }

    Swal.fire({
        toast: true,
        position: 'top-end',
        title: `${dockSlot} Kosong (Standby)`,
        text: 'Slot dock ini tersedia untuk kedatangan armada baru melalui Input Kargo.',
        icon: 'info',
        showConfirmButton: false,
        timer: 2000,
        background: '#ffffff',
        color: '#0D1C42',
        customClass: { popup: 'rounded-xl shadow-lg border border-gray-200' }
    });
}

function updateTMSJsonPayload(tmsInput = null) {
    if (!currentCargoData) return;
    const tms = tmsInput || getResolvedTmsData();
    const occupiedDocks = [];
    const vacantDocks = [];
    ['DOCK-01', 'DOCK-02', 'DOCK-03', 'DOCK-04'].forEach(d => {
        if (dockLiveState[d] && dockLiveState[d].occupied) {
            occupiedDocks.push(d);
        } else {
            vacantDocks.push(d);
        }
    });

    const tmsJson = {
        "event_type": "TMS_TRUCK_GATE_SYNCHRONIZATION",
        "source": "SINGLE_ENTRY_INPUT_KARGO",
        "truck_id": tms.truck_id,
        "truck_plate": tms.truck_plate,
        "driver_name": tms.driver_name,
        "transporter_company": tms.transporter_company,
        "dock_slot": tms.dock_slot,
        "booking_code": tms.booking_code,
        "arrival_time": tms.arrival_time,
        "tas_booking_status": tms.status,
        "gate_barrier_status": (tms.status && tms.status !== 'SCHEDULED') ? "BARRIER_OPENED" : "STANDBY_AWAITING_GATE",
        "dock_allocation_check": {
            "selected_dock": tms.dock_slot,
            "occupied_docks": occupiedDocks,
            "vacant_docks": vacantDocks
        },
        "hardware_specs": {
            "rfid_gate_reader": "TMS UHF Long-Range RFID Reader (902-928 MHz)",
            "barrier_gate": "High-Speed Boom Barrier (1.2s opening speed)",
            "dock_leveler": "Hydraulic Pit Leveler 10-Ton Capacity"
        },
        "timestamp": new Date().toISOString()
    };
    const tmsDisp = document.getElementById('tmsPayloadDisplay');
    if (tmsDisp) tmsDisp.textContent = JSON.stringify(tmsJson, null, 2);
}

function renderTmsTruckQueueTable(trucksList = [], totalCount = null) {
    const tbody = document.getElementById('tmsTruckArrivalsTbody');
    const countBadge = document.getElementById('tms-truck-count-badge');
    const totalKpi = document.getElementById('tms-total-trucks-kpi');
    const resolvedTotal = typeof totalCount === 'number' ? totalCount : trucksList.length;

    if (countBadge) countBadge.textContent = `${resolvedTotal} Armada`;
    if (totalKpi) {
        totalKpi.innerHTML = `${resolvedTotal} <span class="text-xs text-slate-500 font-sans font-normal">Armada</span>`;
    }

    if (!tbody || !Array.isArray(trucksList)) return;
    if (trucksList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="py-5 text-center text-slate-400 text-xs">Belum ada armada terdaftar di TMS.</td></tr>';
        return;
    }

    tbody.innerHTML = trucksList.map(trk => {
        const isCurrentTruck = currentTmsData && trk.id === currentTmsData.truck_id;
        return `
            <tr class="hover:bg-slate-50/70 transition-colors ${isCurrentTruck ? 'bg-teal-50/50 font-semibold' : ''}">
                <td class="py-2 px-3 font-mono font-bold text-[#0D1C42]">${escapeHtml(trk.truck_plate || '-')}</td>
                <td class="py-2 px-3">${escapeHtml(trk.driver_name || '-')}</td>
                <td class="py-2 px-3 text-slate-500">${escapeHtml(trk.transporter_company || '-')}</td>
                <td class="py-2 px-3 font-mono font-bold text-[#087F8A]">${escapeHtml(trk.dock_slot || '-')}</td>
                <td class="py-2 px-3 font-mono text-[11px] text-slate-500">${escapeHtml(trk.booking_code || '-')}</td>
                <td class="py-2 px-3">
                    <span class="${getTmsStatusBadgeClass(trk.status)}">
                        ${escapeHtml(trk.status || 'SCHEDULED')}
                    </span>
                </td>
            </tr>
        `;
    }).join('');
}

function submitTMSCheckIn() {
    if (!currentCargoData) {
        Swal.fire({
            title: 'Data TMS Belum Dimuat',
            text: 'Silakan pilih kargo aktif atau muat ulang halaman.',
            icon: 'error',
            confirmButtonColor: '#087F8A'
        });
        return;
    }

    const stage = parseInt(currentCargoData.current_stage || 1);
    if (stage >= 2 && currentCargoData.status !== 'REGISTERED') {
        Swal.fire({
            title: 'Gate TMS Sudah Diverifikasi!',
            text: `Armada ini sudah diverifikasi pada Tahap 1 (${currentCargoData.status}). Lanjutkan ke Dashboard CMS untuk proses Tahap 2 & 3.`,
            icon: 'info',
            confirmButtonColor: '#087F8A',
            confirmButtonText: 'Buka Dashboard CMS',
            showCancelButton: true,
            cancelButtonText: 'Tutup'
        }).then((r) => {
            if (r.isConfirmed) switchSystemDashboard('cms');
        });
        return;
    }

    const tms = getResolvedTmsData();
    const dockInfo = dockLiveState[tms.dock_slot];
    if (dockInfo && dockInfo.occupied && dockInfo.truckId !== tms.truck_id && dockInfo.plate !== tms.truck_plate) {
        Swal.fire({
            title: `${tms.dock_slot} Sedang Digunakan!`,
            text: `Slot ${tms.dock_slot} sedang terisi armada ${dockInfo.plate}. Tunggu armada selesai bongkar atau daftarkan slot baru.`,
            icon: 'warning',
            confirmButtonColor: '#087F8A'
        });
        return;
    }

    Swal.fire({
        title: 'Verifikasi Truk di Gate TMS...',
        text: `Memvalidasi kedatangan armada ${tms.truck_plate} di ${tms.dock_slot} (${tms.booking_code})...`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#ffffff',
        color: '#0D1C42',
        customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
    });

    fetch('api/simulate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            cargo_id: currentCargoData.id,
            stage: 1,
            action: 'advance',
            avsec_decision: 'CLEARED'
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (res.cargo) {
                currentCargoData = res.cargo;
                renderCargoHeader(currentCargoData);
            } else {
                currentCargoData.current_stage = 2;
                currentCargoData.status = 'ARRIVED';
            }
            if (res.tms_data) currentTmsData = res.tms_data;
            if (res.tms_trucks) recentTrucksList = res.tms_trucks;
            if (typeof res.tms_total_trucks === 'number') totalTrucksCount = res.tms_total_trucks;
            if (res.payload) currentMasterPayload = res.payload;

            const updatedTms = getResolvedTmsData();
            renderTMSDashboard(currentCargoData, updatedTms, recentTrucksList, totalTrucksCount);

            Swal.fire({
                title: 'Truk Berhasil Diverifikasi di Gate TMS!',
                html: `
                    <div class="text-xs text-left p-3.5 rounded-xl bg-slate-50 border border-gray-200 space-y-1.5 mt-2">
                        <div class="flex justify-between items-center pb-1.5 border-b border-gray-200">
                            <span class="text-slate-500 font-bold uppercase text-[10px]">Armada &amp; Dock</span>
                            <span class="text-[#0D1C42] font-mono font-black text-sm">${escapeHtml(updatedTms.truck_plate)} (${escapeHtml(updatedTms.dock_slot)})</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-500">Nama Supir:</span>
                            <span class="font-bold text-slate-800">${escapeHtml(updatedTms.driver_name)}</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-500">Vendor Transportasi:</span>
                            <span class="text-slate-700">${escapeHtml(updatedTms.transporter_company)}</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-500">Kode Booking TAS:</span>
                            <span class="font-mono font-bold text-[#087F8A]">${escapeHtml(updatedTms.booking_code)}</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px] pt-1 border-t border-dashed border-gray-200">
                            <span class="text-slate-500">Status Barrier &amp; TAS:</span>
                            <span class="font-bold text-emerald-700">${escapeHtml(updatedTms.status)} / BARRIER OPENED</span>
                        </div>
                    </div>
                    <p class="text-xs text-emerald-700 font-bold mt-3">
                        <i class="fa-solid fa-circle-check mr-1 text-emerald-600"></i>
                        Zero Re-entry aktif! Silakan lanjut ke CMS untuk proses AIDC &amp; e-AWB kargo.
                    </p>
                `,
                icon: 'success',
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: '#087F8A',
                confirmButtonText: 'Lanjut ke CMS (Tahap 2 &amp; 3)',
                showCancelButton: true,
                cancelButtonText: 'Tetap di TMS',
                cancelButtonColor: '#94a3b8',
                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
            }).then((result) => {
                initSimulation(currentCargoData.id, 2, result.isConfirmed ? 'cms' : 'tms');
            });
        } else {
            Swal.fire({
                title: 'Gagal Verifikasi!',
                text: res.message || 'Terjadi kesalahan sistem TMS.',
                icon: 'error',
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: '#087F8A'
            });
        }
    })
    .catch(err => {
        Swal.fire({
            title: 'Error Sistem',
            text: 'Gagal memproses verifikasi gate TMS: ' + err,
            icon: 'error',
            background: '#ffffff',
            color: '#0D1C42',
            confirmButtonColor: '#087F8A'
        });
    });
}

function renderTMSDashboard(c, tmsData = currentTmsData, trucksList = recentTrucksList, totalCount = totalTrucksCount) {
    if (!c) return;
    const tms = getResolvedTmsData(c, tmsData);

    const setElText = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val || '-';
    };

    setElText('tms-view-plate', tms.truck_plate);
    setElText('tms-view-driver', tms.driver_name);
    const transEl = document.getElementById('tms-view-transporter');
    if (transEl) {
        transEl.textContent = tms.transporter_company || '-';
        transEl.title = tms.transporter_company || '-';
    }
    setElText('tms-view-dock', tms.dock_slot);
    setElText('tms-view-booking', tms.booking_code);
    setElText('tms-view-truck-id', tms.truck_id);
    setElText('tms-view-arrival', tms.arrival_time);

    const statusEl = document.getElementById('tms-view-status');
    if (statusEl) {
        statusEl.textContent = tms.status;
        statusEl.className = getTmsStatusBadgeClass(tms.status) + ' mt-0.5 inline-block';
    }

    const stage = parseInt(c.current_stage || 1);
    const gateVerified = (stage >= 2 || (tms.status && tms.status !== 'SCHEDULED'));

    const stageBadge = document.getElementById('tms-cargo-stage-badge');
    if (stageBadge) {
        stageBadge.textContent = gateVerified
            ? `Gate Terverifikasi (${tms.status})`
            : `Tahap 1: ${tms.status}`;
    }

    const barrierKpi = document.getElementById('tms-barrier-status-kpi');
    if (barrierKpi) {
        barrierKpi.innerHTML = gateVerified
            ? 'OPENED <span class="text-xs text-slate-500 font-sans font-normal">Truck Passed</span>'
            : 'READY <span class="text-xs text-slate-500 font-sans font-normal">1.2s Boom</span>';
    }

    const verifyBtn = document.getElementById('btn-tms-verify-gate');
    const verifyIcon = document.getElementById('btn-tms-verify-icon');
    const verifyText = document.getElementById('btn-tms-verify-text');
    if (verifyBtn) {
        if (gateVerified) {
            verifyBtn.className = 'px-5 py-2.5 rounded-xl bg-emerald-50 text-emerald-700 font-bold text-xs border border-emerald-200 flex items-center cursor-default';
            if (verifyIcon) verifyIcon.className = 'fa-solid fa-circle-check mr-2 text-emerald-600';
            if (verifyText) verifyText.textContent = 'Gate TMS Terverifikasi (Tahap 1 Selesai)';
        } else {
            verifyBtn.className = 'px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer animate-pulse';
            if (verifyIcon) verifyIcon.className = 'fa-solid fa-torii-gate mr-2';
            if (verifyText) verifyText.textContent = 'Verifikasi Kedatangan Truk di Gate (Tahap 1)';
        }
    }

    if (Array.isArray(trucksList) && trucksList.length > 0) {
        renderTmsTruckQueueTable(trucksList, totalCount);
    }
    renderDockCards(tms);
    updateTMSJsonPayload(tms);
}

function executeStage2Scan() {
    if (!currentCargoData) return;

    // Proteksi alur linear: Tahap 1 (TMS Gate Verification) harus selesai sebelum Ingest JSON ke CMS
    if (parseInt(currentCargoData.current_stage || 1) < 2) {
        Swal.fire({
            title: 'Verifikasi Gerbang TMS Terlebih Dahulu!',
            html: `
                <div class="text-xs text-left p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 space-y-1.5 mt-2">
                    <p class="font-bold"><i class="fa-solid fa-triangle-exclamation text-amber-600 mr-1"></i> SOP Alur Linear (Tahap 1 &rarr; 2):</p>
                    <p class="text-[11.5px] text-amber-800 leading-relaxed">Armada truk masih berstatus <strong>SCHEDULED</strong> di Gate TMS. Lakukan verifikasi kedatangan armada di Dashboard TMS (Tahap 1) sebelum mengirim payload JSON ke CMS.</p>
                </div>
            `,
            icon: 'warning',
            background: '#ffffff',
            color: '#0D1C42',
            confirmButtonColor: '#087F8A',
            confirmButtonText: 'Buka Dashboard TMS (Tahap 1)',
            showCancelButton: true,
            cancelButtonText: 'Tutup',
            cancelButtonColor: '#94a3b8'
        }).then((res) => {
            if (res.isConfirmed) {
                switchSystemDashboard('tms');
            }
        });
        return;
    }

    Swal.fire({
        title: 'Mentransmisikan Payload JSON...',
        html: `
            <div class="p-3.5 bg-[#0B1528] rounded-xl border border-slate-700 text-xs text-left space-y-2 mt-2 font-mono">
                <div class="flex items-center justify-between text-teal-400 font-bold border-b border-slate-700/80 pb-1.5">
                    <span class="flex items-center"><i class="fa-solid fa-code text-sm mr-2 animate-pulse"></i> AIDC Edge JSON Ingest</span>
                    <span class="text-[10px] text-amber-300 bg-amber-950/60 px-2 py-0.5 rounded border border-amber-800">TRANSMITTING</span>
                </div>
                <div class="text-slate-400 text-[11px]">Mengirimkan payload data pemindaian kargo (GS1 SSCC, GTIN, Batch) dalam format JSON ke CMS Engine...</div>
                <div class="text-emerald-400 text-[10.5px] bg-[#060D1A] p-2 rounded border border-slate-800">POST /api/v1/aidc/ingest-scan HTTP/1.1<br>Host: cms.aircargo.local</div>
            </div>
        `,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#ffffff',
        color: '#0D1C42',
        customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
    });

    fetch('api/simulate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            cargo_id: currentCargoData.id,
            stage: 2,
            action: 'advance',
            avsec_decision: 'CLEARED'
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (res.cargo) {
                currentCargoData = res.cargo;
                renderCargoHeader(currentCargoData);
            }
            if (res.tms_data) currentTmsData = res.tms_data;
            if (res.tms_trucks) recentTrucksList = res.tms_trucks;
            if (typeof res.tms_total_trucks === 'number') totalTrucksCount = res.tms_total_trucks;
            if (res.payload) {
                currentMasterPayload = res.payload;
            } else {
                currentCargoData.current_stage = 3;
                currentCargoData.status = 'SCANNED';
            }

            const payloadResult = {
                event_type: "AIDC_INBOUND_INGEST",
                timestamp: new Date().toISOString(),
                device_id: "EDGE-AIDC-SCANNER-01",
                scan_protocol: "GS1_DIGITAL_LINK_JSON",
                payload: {
                    sscc: currentCargoData.sscc || "3899123450007509019",
                    gtin: currentCargoData.gtin || "08997193267836",
                    batch_no: currentCargoData.batch_no || "LOT-GEN-1538",
                    gross_weight_kg: parseFloat(currentCargoData.declared_weight_kg || 350.0),
                    pieces: parseInt(currentCargoData.quantity || 50),
                    commodity: currentCargoData.description || "Pakaian & Tekstil Premium Ekspor",
                    truck_plate: currentCargoData.truck_plate || "B 8888 KGO",
                    dock_slot: currentCargoData.dock_slot || "DOCK-02"
                },
                ingest_status: "SUCCESS_200_OK"
            };

            Swal.fire({
                title: 'Payload JSON Berhasil Diterima!',
                html: `
                    <div class="text-xs text-left p-3.5 rounded-xl bg-[#0B1528] border border-slate-700 space-y-2 mt-2 font-mono">
                        <div class="flex items-center justify-between pb-1.5 border-b border-slate-700/80">
                            <span class="text-slate-400 font-bold text-[10px] uppercase">Payload JSON Stream (HTTP 200 OK)</span>
                            <span class="text-emerald-400 font-bold text-[10px] bg-emerald-950/60 px-2 py-0.5 rounded border border-emerald-800">AIDC-INGEST-SUCCESS</span>
                        </div>
                        <pre class="font-mono text-[10.5px] text-emerald-400 bg-[#060D1A] p-2.5 rounded-lg border border-slate-800 overflow-x-auto max-h-44 leading-relaxed">${escapeHtml(JSON.stringify(payloadResult, null, 2))}</pre>
                    </div>
                    <div class="mt-3 p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-left">
                        <p class="text-emerald-800 font-bold">
                            <i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i>
                            Payload JSON Terverifikasi! Tombol Validasi e-AWB (Tahap 3) kini telah terbuka.
                        </p>
                    </div>
                `,
                icon: 'success',
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: '#087F8A',
                confirmButtonText: 'Lanjut ke Validasi e-AWB',
                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
            }).then(() => {
                initSimulation(currentCargoData.id, 3, 'cms');
            });
        } else {
            Swal.fire({
                title: 'Gagal Ingest JSON!',
                text: res.message || 'Transmisi payload JSON pemindaian gagal diproses.',
                icon: 'error',
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: '#087F8A'
            });
        }
    })
    .catch(err => {
        Swal.fire({
            title: 'Error Sistem',
            text: 'Gagal memproses ingest JSON: ' + err,
            icon: 'error',
            background: '#ffffff',
            color: '#0D1C42',
            confirmButtonColor: '#087F8A'
        });
    });
}

function executeStage3Validate() {
    if (!currentCargoData) return;

    // Proteksi: Kargo harus sudah melewati pindaian JSON di Tahap 2 (current_stage >= 3)
    if (parseInt(currentCargoData.current_stage || 1) < 3) {
        Swal.fire({
            title: 'Lakukan Ingest JSON Pemindaian Terlebih Dahulu!',
            html: `
                <div class="text-xs text-left p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 space-y-1.5 mt-2">
                    <p class="font-bold"><i class="fa-solid fa-triangle-exclamation text-amber-600 mr-1"></i> SOP Gerbang Inbound Bandara:</p>
                    <p class="text-[11.5px] text-amber-800 leading-relaxed">Kargo wajib melewati transmisi payload JSON pemindaian (Tahap 2) untuk menangkap GS1 SSCC 18-digit sebelum dokumen digital e-AWB dapat divalidasi dan diregistrasi di CMS.</p>
                </div>
            `,
            icon: 'warning',
            background: '#ffffff',
            color: '#0D1C42',
            confirmButtonColor: '#087F8A',
            confirmButtonText: parseInt(currentCargoData.current_stage || 1) < 2 ? 'Verifikasi Gate TMS Dulu' : 'Kirim Payload JSON Sekarang',
            showCancelButton: true,
            cancelButtonText: 'Tutup',
            cancelButtonColor: '#94a3b8'
        }).then((res) => {
            if (res.isConfirmed) {
                executeStage2Scan();
            }
        });
        return;
    }

    Swal.fire({
        title: 'Mencocokkan e-AWB di CMS...',
        html: `
            <div class="p-3 bg-slate-50 rounded-xl border border-gray-200 text-xs text-left space-y-1.5 mt-2">
                <div class="flex items-center text-[#087F8A] font-bold">
                    <i class="fa-solid fa-arrows-rotate animate-spin mr-2"></i>
                    <span>IATA e-freight Matcher Engine</span>
                </div>
                <div class="text-slate-500 text-[11px]">Mencocokkan SSCC ${escapeHtml(currentCargoData.sscc)} dengan nomor e-AWB ${escapeHtml(currentCargoData.awb_number)}...</div>
            </div>
        `,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#ffffff',
        color: '#0D1C42',
        customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
    });

    fetch('api/simulate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            cargo_id: currentCargoData.id,
            stage: 3,
            action: 'advance',
            avsec_decision: 'CLEARED'
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (res.cargo) {
                currentCargoData = res.cargo;
                renderCargoHeader(currentCargoData);
            }
            if (res.tms_data) currentTmsData = res.tms_data;
            if (res.tms_trucks) recentTrucksList = res.tms_trucks;
            if (typeof res.tms_total_trucks === 'number') totalTrucksCount = res.tms_total_trucks;
            if (res.payload) {
                currentMasterPayload = res.payload;
            } else {
                currentCargoData.current_stage = 4;
                currentCargoData.status = 'SECURITY_CHECK';
            }

            // Masukkan kargo ke antrean CMS di bawahnya secara real-time!
            const tbody = document.getElementById('cmsCargoQueueTbody');
            if (tbody) {
                const emptyRow = document.getElementById('cms-empty-queue-row');
                if (emptyRow) {
                    emptyRow.remove();
                }

                const existingRow = document.getElementById(`cms-cargo-row-${currentCargoData.id}`);
                if (!existingRow) {
                    const tr = document.createElement('tr');
                    tr.id = `cms-cargo-row-${currentCargoData.id}`;
                    tr.className = 'hover:bg-slate-50/70 transition-colors bg-emerald-50/60 font-medium animate-pulse';
                    tr.innerHTML = `
                        <td class="py-2.5 px-3 font-mono font-bold text-[#0D1C42]">${escapeHtml(currentCargoData.awb_number)}</td>
                        <td class="py-2.5 px-3 font-mono text-[11px] text-[#087F8A] font-bold">${escapeHtml(currentCargoData.sscc)}</td>
                        <td class="py-2.5 px-3 truncate max-w-[120px]">${escapeHtml(currentCargoData.description || 'Pakaian & Tekstil')}</td>
                        <td class="py-2.5 px-3 text-slate-500 truncate max-w-[120px]">${escapeHtml(currentCargoData.shipper || '-')}</td>
                        <td class="py-2.5 px-3 font-bold text-[#087F8A]">Tahap ${escapeHtml(currentCargoData.current_stage || 4)}</td>
                        <td class="py-2.5 px-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-[#087F8A] border border-teal-200">
                                ${escapeHtml(currentCargoData.status || 'SECURITY_CHECK')}
                            </span>
                        </td>
                    `;
                    tbody.insertBefore(tr, tbody.firstChild);

                    setTimeout(() => {
                        tr.classList.remove('bg-emerald-50/60', 'animate-pulse');
                    }, 3000);
                }
            }

            // Update badge jumlah shipment di antrean CMS
            const badge = document.getElementById('cms-cargo-count-badge');
            if (badge && tbody) {
                const totalRows = tbody.querySelectorAll('tr:not(#cms-empty-queue-row)').length;
                badge.textContent = `${totalRows} Shipment`;
            }

            Swal.fire({
                title: 'e-AWB Berhasil Tervalidasi & Terdaftar!',
                html: `
                    <div class="text-xs text-left p-3.5 rounded-xl bg-slate-50 border border-gray-200 space-y-1.5 mt-2">
                        <div class="flex justify-between items-center pb-1 border-b border-gray-200">
                            <span class="text-slate-500 font-bold uppercase text-[10px]">Nomor e-AWB</span>
                            <span class="text-[#0D1C42] font-mono font-black text-sm">${escapeHtml(currentCargoData.awb_number)}</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-500">Kunci SSCC:</span>
                            <span class="font-mono text-[#087F8A] font-bold">${escapeHtml(currentCargoData.sscc)}</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-500">Status Pencocokan:</span>
                            <span class="font-bold text-emerald-700">MATCHED &amp; REGISTERED</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-500">Internal Lane Routing:</span>
                            <span class="font-mono text-slate-800 font-bold">LANE_02_AVSEC_SCREENING</span>
                        </div>
                    </div>
                    <p class="text-xs text-emerald-700 font-bold mt-3">
                        <i class="fa-solid fa-circle-check mr-1 text-emerald-600"></i>
                        Kargo resmi masuk ke antrean CMS dan siap diarahkan ke screening X-Ray AVSEC!
                    </p>
                `,
                icon: 'success',
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: '#087F8A',
                confirmButtonText: 'Lanjut ke AVSEC (Tahap 4)',
                showCancelButton: true,
                cancelButtonText: 'Tetap di CMS',
                cancelButtonColor: '#94a3b8',
                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
            }).then((result) => {
                initSimulation(currentCargoData.id, 4, result.isConfirmed ? 'avsec' : 'cms');
            });
        } else {
            Swal.fire({
                title: 'Gagal Validasi e-AWB!',
                text: res.message || 'Terjadi kesalahan saat mencocokkan dokumen di CMS.',
                icon: 'error',
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: '#087F8A'
            });
        }
    })
    .catch(err => {
        Swal.fire({
            title: 'Error Sistem',
            text: 'Gagal memproses validasi e-AWB: ' + err,
            icon: 'error',
            background: '#ffffff',
            color: '#0D1C42',
            confirmButtonColor: '#087F8A'
        });
    });
}

function renderCMSDashboard(c) {
    if (!c) return;

    const ssccEl = document.getElementById('cms-captured-sscc');
    const badgeEl = document.getElementById('cms-scanner-status-badge');
    const pillStrip = document.getElementById('cms-gs1-pill-strip');
    const jsonDisplay = document.getElementById('cms-json-payload-display');
    const jsonStatusTag = document.getElementById('cms-json-status-tag');
    const jsonPing = document.getElementById('cms-json-indicator-ping');
    const jsonDot = document.getElementById('cms-json-indicator-dot');
    const scanBtn = document.getElementById('btn-cms-scan-rfid');
    const scanText = document.getElementById('btn-cms-scan-text');
    const valBtn = document.getElementById('btn-cms-validate-awb');
    const valText = document.getElementById('btn-cms-validate-text');
    const valIcon = document.getElementById('btn-cms-validate-icon');

    const stage = parseInt(c.current_stage || 1);

    // KASUS 0: MENUNGGU VERIFIKASI GERBANG TMS (Stage < 2 / REGISTERED)
    if (stage < 2) {
        const waitingPayload = {
            "cargo_payload_id": "CMP-" + c.id,
            "master_sscc": c.sscc,
            "event_type": "CMS_PRE_ALERT_STANDBY",
            "protocol": "GS1_DIGITAL_LINK_JSON / REST_MQTT",
            "device_id": "EDGE-AIDC-GATEWAY-01",
            "timestamp": new Date().toISOString(),
            "ingest_status": "AWAITING_TMS_GATE_VERIFICATION",
            "origin_truck_gate": {
                "truck_plate": c.truck_plate || "-",
                "dock_slot": c.dock_slot || "DOCK-02",
                "booking_code": c.booking_code || "-",
                "tas_status": "SCHEDULED"
            },
            "instruction": "Verifikasi kedatangan armada truk di Dashboard TMS (Tahap 1) agar payload AIDC dapat dikirim ke CMS."
        };

        if (jsonDisplay) jsonDisplay.textContent = JSON.stringify(waitingPayload, null, 2);
        if (jsonStatusTag) {
            jsonStatusTag.className = "text-[10px] font-mono text-amber-300 bg-amber-950/60 px-2 py-0.5 rounded border border-amber-800/80";
            jsonStatusTag.textContent = "STANDBY - AWAITING TMS GATE";
        }
        if (jsonPing) jsonPing.className = "animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75";
        if (jsonDot) jsonDot.className = "relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500";
        if (ssccEl) {
            ssccEl.className = 'text-amber-300 font-bold bg-amber-950/70 px-2.5 py-0.5 rounded border border-amber-700 font-mono text-[11px]';
            ssccEl.textContent = c.sscc || '[ Terdaftar di Pre-Alert ]';
        }
        if (badgeEl) {
            badgeEl.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 uppercase tracking-wide';
            badgeEl.innerHTML = `<i class="fa-solid fa-clock mr-1"></i>Menunggu Verifikasi TMS Gate (${escapeHtml(c.truck_plate || '-')})`;
        }
        if (pillStrip) {
            pillStrip.classList.remove('hidden');
            const gGtin = document.getElementById('cms-gs1-gtin');
            if (gGtin) gGtin.textContent = c.gtin || '-';
            const gBatch = document.getElementById('cms-gs1-batch');
            if (gBatch) gBatch.textContent = c.batch_no || '-';
        }
        if (scanBtn) {
            scanBtn.className = 'px-5 py-2.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold text-xs border border-amber-300 transition-all flex items-center cursor-pointer';
        }
        if (scanText) scanText.textContent = 'Menunggu Gate TMS (Klik untuk Cek Tahap 1)';
        if (valBtn) {
            valBtn.className = 'px-5 py-2.5 rounded-xl bg-slate-100 text-slate-400 text-xs font-bold border border-gray-200 cursor-not-allowed shadow-none flex items-center transition-all opacity-70';
        }
        if (valIcon) valIcon.className = 'fa-solid fa-lock mr-2 text-slate-400';
        if (valText) valText.textContent = 'Validasi & Registrasi e-AWB (Terkunci - Gate TMS Dulu)';
    }
    // KASUS 1: BARU TIBA DARI TMS (ARRIVED), SIAP INGEST PAYLOAD JSON (Stage === 2)
    else if (stage === 2) {
        const readyPayload = {
            "cargo_payload_id": "CMP-" + c.id,
            "master_sscc": c.sscc,
            "event_type": "AIDC_INBOUND_INGEST_STREAM",
            "protocol": "GS1_DIGITAL_LINK_JSON / REST_MQTT",
            "device_id": "EDGE-AIDC-GATEWAY-01",
            "timestamp": new Date().toISOString(),
            "origin_truck_gate": {
                "truck_plate": c.truck_plate || "B 8888 KGO",
                "driver_name": c.driver_name || "Bambang Pamungkas",
                "transporter": c.transporter_company || "PT Angkasa Pura Logistik",
                "dock_slot": c.dock_slot || "DOCK-02",
                "booking_code": c.booking_code || "TAS-SLOT-8821"
            },
            "cargo_identifiers": {
                "sscc_18": c.sscc || "3899123450007509019",
                "gtin_14": c.gtin || "08997193267836",
                "batch_no": c.batch_no || "LOT-GEN-1538",
                "expiry_date": c.expiry_date || "2027-12-31"
            },
            "cargo_specifications": {
                "awb_number": c.awb_number,
                "commodity": c.description,
                "commodity_type": c.commodity_type,
                "pieces": parseInt(c.quantity || 50),
                "declared_weight_kg": parseFloat(c.declared_weight_kg || 350.0)
            },
            "ingest_status": "READY_FOR_CMS_INGESTION",
            "instruction": `Klik tombol 'Kirim / Ingest Payload JSON' untuk memindai muatan armada ${c.truck_plate || 'Truk'} ke CMS.`
        };

        if (jsonDisplay) {
            jsonDisplay.textContent = JSON.stringify(readyPayload, null, 2);
        }
        if (jsonStatusTag) {
            jsonStatusTag.className = "text-[10px] font-mono text-teal-300 bg-teal-950/60 px-2 py-0.5 rounded border border-teal-800/80";
            jsonStatusTag.textContent = "PAYLOAD READY - TRUCK ARRIVED";
        }
        if (jsonPing) {
            jsonPing.className = "animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75";
        }
        if (jsonDot) {
            jsonDot.className = "relative inline-flex rounded-full h-2.5 w-2.5 bg-teal-500";
        }
        if (ssccEl) {
            ssccEl.className = 'text-teal-300 font-bold bg-teal-950/80 px-2.5 py-0.5 rounded border border-teal-700 font-mono text-[11px]';
            ssccEl.textContent = c.sscc || '[ Menunggu Ingest JSON ]';
        }
        if (badgeEl) {
            badgeEl.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-[#087F8A] border border-teal-200 uppercase tracking-wide';
            badgeEl.innerHTML = `<i class="fa-solid fa-truck-ramp-box mr-1"></i>Muatan Truk ${escapeHtml(c.truck_plate || '')} Siap Dipindai`;
        }
        if (pillStrip) {
            pillStrip.classList.remove('hidden');
            const gGtin = document.getElementById('cms-gs1-gtin');
            if (gGtin) gGtin.textContent = c.gtin || '-';
            const gBatch = document.getElementById('cms-gs1-batch');
            if (gBatch) gBatch.textContent = c.batch_no || '-';
        }

        if (scanBtn) {
            scanBtn.className = 'px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer animate-pulse';
        }
        if (scanText) scanText.textContent = `Kirim / Ingest Payload JSON ${c.truck_plate || 'Truk'} (Tahap 2)`;

        if (valBtn) {
            valBtn.className = 'px-5 py-2.5 rounded-xl bg-slate-100 text-slate-400 text-xs font-bold border border-gray-200 cursor-not-allowed shadow-none flex items-center transition-all opacity-70';
        }
        if (valIcon) {
            valIcon.className = 'fa-solid fa-lock mr-2 text-slate-400';
        }
        if (valText) {
            valText.textContent = 'Validasi & Registrasi e-AWB (Terkunci - Ingest JSON Dulu)';
        }
    }
    // KASUS 2: SUDAH INGEST DATA JSON (SCANNED), SIAP VALIDASI E-AWB (Stage === 3)
    else if (stage === 3) {
        const scanPayload = {
            "cargo_payload_id": "CMP-" + c.id,
            "master_sscc": c.sscc,
            "event_type": "AIDC_INBOUND_INGEST_STREAM",
            "device_id": "EDGE-AIDC-SCANNER-01",
            "scan_protocol": "GS1_DIGITAL_LINK_JSON",
            "timestamp": new Date().toISOString(),
            "origin_truck": {
                "truck_plate": c.truck_plate || "B 8888 KGO",
                "dock_slot": c.dock_slot || "DOCK-02"
            },
            "cargo_data": {
                "sscc_18": c.sscc || "3899123450007509019",
                "gtin_14": c.gtin || "08997193267836",
                "batch_no": c.batch_no || "LOT-GEN-1538",
                "weight_kg": parseFloat(c.actual_weight_kg || c.declared_weight_kg || 354.0),
                "pieces": parseInt(c.quantity || 50),
                "commodity": c.description || "Pakaian & Tekstil Premium Ekspor",
                "awb_number": c.awb_number
            },
            "signal_metrics": {
                "carrier_freq_mhz": 865.7,
                "rssi_dbm": -41.5,
                "read_confidence": "99.98%"
            },
            "status": "HTTP_200_PAYLOAD_VERIFIED_SUCCESS"
        };

        if (jsonDisplay) {
            jsonDisplay.textContent = JSON.stringify(scanPayload, null, 2);
        }
        if (jsonStatusTag) {
            jsonStatusTag.className = "text-[10px] font-mono text-emerald-300 bg-emerald-950/60 px-2 py-0.5 rounded border border-emerald-800/80";
            jsonStatusTag.textContent = "HTTP 200 OK - JSON PAYLOAD INGESTED";
        }
        if (jsonPing) {
            jsonPing.className = "animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75";
        }
        if (jsonDot) {
            jsonDot.className = "relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500";
        }
        if (ssccEl) {
            ssccEl.className = 'text-emerald-400 font-black bg-emerald-950/70 px-2.5 py-0.5 rounded border border-emerald-700/80 shadow-sm text-xs font-mono tracking-wider animate-pulse';
            ssccEl.textContent = c.sscc || '-';
        }
        if (badgeEl) {
            badgeEl.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase tracking-wide';
            badgeEl.innerHTML = '<i class="fa-solid fa-circle-check mr-1 text-emerald-600"></i>Payload JSON Terverifikasi (HTTP 200)';
        }
        if (pillStrip) {
            pillStrip.classList.remove('hidden');
            const gGtin = document.getElementById('cms-gs1-gtin');
            if (gGtin) gGtin.textContent = c.gtin || '-';
            const gBatch = document.getElementById('cms-gs1-batch');
            if (gBatch) gBatch.textContent = c.batch_no || '-';
        }

        if (scanBtn) {
            scanBtn.className = 'px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-gray-300 shadow-sm transition-all flex items-center cursor-pointer';
        }
        if (scanText) scanText.textContent = 'JSON Terpindai (Kirim Ulang)';

        // Tombol 2 terbuka dan berkedip hijau siap diklik!
        if (valBtn) {
            valBtn.className = 'px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-md shadow-emerald-500/25 transition-all flex items-center transform hover:-translate-y-0.5 animate-pulse cursor-pointer';
        }
        if (valIcon) {
            valIcon.className = 'fa-solid fa-laptop-code mr-2 text-sm';
        }
        if (valText) {
            valText.textContent = 'Validasi & Registrasi e-AWB di CMS (Tahap 3)';
        }
    }
    // KASUS 3: SUDAH TAHAP 4 ATAU LEBIH (Stage >= 4 / e-AWB Registered)
    else {
        const processedPayload = {
            "event_type": "CMS_EAWB_REGISTRATION",
            "timestamp": new Date().toISOString(),
            "registration_status": "EAWB_MATCHED_AND_REGISTERED",
            "e_awb_number": c.awb_number,
            "matched_sscc": c.sscc,
            "truck_origin": c.truck_plate || "B 8888 KGO",
            "cargo_details": {
                "commodity": c.description,
                "commodity_type": c.commodity_type,
                "pieces": parseInt(c.quantity),
                "declared_weight_kg": parseFloat(c.declared_weight_kg),
                "shipper": c.shipper,
                "consignee": c.consignee
            },
            "internal_routing": "LANE_02_AVSEC_XRAY_SCREENING"
        };

        if (jsonDisplay) {
            jsonDisplay.textContent = JSON.stringify(processedPayload, null, 2);
        }
        if (jsonStatusTag) {
            jsonStatusTag.className = "text-[10px] font-mono text-teal-300 bg-teal-950/60 px-2 py-0.5 rounded border border-teal-800/80";
            jsonStatusTag.textContent = "PROCESSED - e-AWB MATCHED";
        }
        if (jsonPing) jsonPing.className = "hidden";
        if (jsonDot) jsonDot.className = "relative inline-flex rounded-full h-2.5 w-2.5 bg-teal-500";

        if (ssccEl) {
            ssccEl.className = 'text-emerald-400 font-bold bg-emerald-950/60 px-2.5 py-0.5 rounded border border-emerald-800 font-mono text-xs';
            ssccEl.textContent = c.sscc || '-';
        }
        if (badgeEl) {
            badgeEl.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-[#087F8A] border border-teal-200 uppercase tracking-wide';
            badgeEl.innerHTML = '<i class="fa-solid fa-file-circle-check mr-1 text-[#087F8A]"></i>e-AWB Validated &amp; Registered';
        }
        if (pillStrip) {
            pillStrip.classList.remove('hidden');
            const gGtin = document.getElementById('cms-gs1-gtin');
            if (gGtin) gGtin.textContent = c.gtin || '-';
            const gBatch = document.getElementById('cms-gs1-batch');
            if (gBatch) gBatch.textContent = c.batch_no || '-';
        }

        if (scanBtn) {
            scanBtn.className = 'px-4 py-2.5 rounded-xl bg-slate-50 text-slate-500 text-xs font-medium border border-gray-200 transition-all flex items-center cursor-pointer';
        }
        if (scanText) scanText.textContent = 'JSON Terverifikasi';

        if (valBtn) {
            valBtn.className = 'px-4 py-2.5 rounded-xl bg-teal-50 text-[#087F8A] text-xs font-bold border border-teal-200 transition-all flex items-center cursor-default';
        }
        if (valIcon) {
            valIcon.className = 'fa-solid fa-circle-check mr-2 text-teal-600';
        }
        if (valText) {
            valText.textContent = 'e-AWB Telah Teregistrasi';
        }
    }

    const cmsJson = {
        "event_type": "CMS_AIDC_AND_AWB_MATCHING",
        "ingest_source": "AIDC Inbound Edge JSON Gateway",
        "ingest_format": "JSON_GS1_EPC_GEN2",
        "json_ingest_status": (stage >= 3) ? "PAYLOAD_INGESTED_SUCCESS" : (stage === 2 ? "READY_FOR_INGESTION" : "AWAITING_TMS_GATE"),
        "gs1_sscc_18": (stage >= 3) ? c.sscc : null,
        "e_awb_number": c.awb_number,
        "shipment": {
            "truck_origin": c.truck_plate || "B 8888 KGO",
            "driver_name": c.driver_name || "Bambang Pamungkas",
            "shipper": c.shipper,
            "consignee": c.consignee,
            "commodity": c.description,
            "pieces": parseInt(c.quantity),
            "declared_weight_kg": parseFloat(c.declared_weight_kg)
        },
        "matching_result": {
            "awb_matched": (stage >= 4),
            "registration_status": (stage >= 4) ? "REGISTERED_IN_CMS" : "AWAITING_VALIDATION",
            "internal_sorting_lane": "LANE_02_AVSEC_SCREENING",
            "cold_chain_priority": c.commodity_type === 'PHARMA' ? "URGENT" : "STANDARD"
        },
        "timestamp": new Date().toISOString()
    };
    const cmsDisp = document.getElementById('cmsPayloadDisplay');
    if (cmsDisp) cmsDisp.textContent = JSON.stringify(cmsJson, null, 2);
}

function renderAVSECDashboard(c) {
    if (!c) return;
    const certWrap = document.getElementById('avsec-certificate-wrap');
    const warnWrap = document.getElementById('avsec-suspect-warning-wrap');
    const statusInd = document.getElementById('avsec-status-indicator');
    const csdNum = document.getElementById('avsec-csd-number');
    const inspId = document.getElementById('avsec-inspector-id');
    const clearTime = document.getElementById('avsec-clearance-time');
    const jsonDisplay = document.getElementById('avsec-json-stream-display');
    const statusTag = document.getElementById('avsec-json-status-tag');
    const pingEl = document.getElementById('avsec-json-indicator-ping');
    const dotEl = document.getElementById('avsec-json-indicator-dot');

    const stage = parseInt(c.current_stage || 1);
    const isSuspect = (c.status === 'SUSPECT');
    const isReturned = (c.status === 'RETURNED_TO_TRUCK');
    const isCleared = (c.status === 'CLEARED' || (stage >= 5 && !isSuspect && !isReturned));

    if (isSuspect) {
        if (certWrap) certWrap.classList.add('hidden');
        if (warnWrap) warnWrap.classList.remove('hidden');
        if (statusInd) {
            statusInd.textContent = 'STATUS: SUSPECT (DITAHAN DI RUANG KARANTINA)';
            statusInd.className = 'text-[10px] font-mono px-3 py-1 rounded-full font-bold bg-rose-50 text-rose-700 border border-rose-300 animate-pulse';
        }
        if (statusTag) {
            statusTag.className = "text-[10px] font-mono text-rose-300 bg-rose-950/60 px-2 py-0.5 rounded border border-rose-800/80";
            statusTag.textContent = "ANOMALY DETECTED - QUARANTINE LOCKOUT";
        }
        if (pingEl) pingEl.className = "animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75";
        if (dotEl) dotEl.className = "relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500";
    } else if (isReturned) {
        if (certWrap) certWrap.classList.add('hidden');
        if (warnWrap) warnWrap.classList.add('hidden');
        if (statusInd) {
            statusInd.textContent = `STATUS: DITOLAK (DIKEMBALIKAN KE TRUK ${c.truck_plate || ''})`;
            statusInd.className = 'text-[10px] font-mono px-3 py-1 rounded-full font-bold bg-slate-100 text-slate-800 border border-slate-300';
        }
        if (statusTag) {
            statusTag.className = "text-[10px] font-mono text-slate-300 bg-slate-900 px-2 py-0.5 rounded border border-slate-700";
            statusTag.textContent = "CARGO REJECTED & EXPELLED";
        }
        if (pingEl) pingEl.className = "hidden";
        if (dotEl) dotEl.className = "relative inline-flex rounded-full h-2.5 w-2.5 bg-slate-500";
    } else if (isCleared) {
        if (certWrap) certWrap.classList.remove('hidden');
        if (warnWrap) warnWrap.classList.add('hidden');
        if (statusInd) {
            statusInd.textContent = 'STATUS: CLEARED (LOLOS - CSD TERBIT)';
            statusInd.className = 'text-[10px] font-mono px-3 py-1 rounded-full font-bold bg-emerald-50 text-emerald-700 border border-emerald-200';
        }
        if (statusTag) {
            statusTag.className = "text-[10px] font-mono text-emerald-300 bg-emerald-950/60 px-2 py-0.5 rounded border border-emerald-800/80";
            statusTag.textContent = "CLEARANCE VALIDATED - CSD ISSUED";
        }
        if (pingEl) pingEl.className = "animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75";
        if (dotEl) dotEl.className = "relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500";
        if (csdNum) csdNum.textContent = c.csd_number || (`CSD-ID-${(c.awb_number || '20261017').replace(/[^0-9]/g, '')}-77`);
        if (inspId) inspId.textContent = c.inspector_id || 'AVSEC-OPR-07';
        if (clearTime) clearTime.textContent = c.check_time || 'CLEARANCE VALIDATED';
    } else {
        if (certWrap) certWrap.classList.add('hidden');
        if (warnWrap) warnWrap.classList.add('hidden');
        if (statusInd) {
            statusInd.textContent = stage < 4 ? 'STATUS: MENUNGGU VALIDASI CMS (TAHAP 1-3)' : 'STATUS: MENUNGGU SCREENING X-RAY';
            statusInd.className = 'text-[10px] font-mono px-3 py-1 rounded-full font-bold bg-amber-50 text-amber-700 border border-amber-200';
        }
        if (statusTag) {
            statusTag.className = "text-[10px] font-mono text-amber-300 bg-amber-950/60 px-2 py-0.5 rounded border border-amber-800/80";
            statusTag.textContent = stage < 4 ? "STANDBY - AWAITING CMS VALIDATION" : "STANDBY - AWAITING X-RAY CONVEYOR";
        }
        if (pingEl) pingEl.className = "animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75";
        if (dotEl) dotEl.className = "relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500";
    }

    const avsecJson = {
        "event_type": "AVSEC_XRAY_SECURITY_EVALUATION",
        "protocol": "ICAO_ANNEX_17_AVSEC_STREAM",
        "timestamp": new Date().toISOString(),
        "cargo_identity": {
            "awb_number": c.awb_number,
            "sscc": c.sscc,
            "commodity": c.description,
            "commodity_class": c.commodity_type,
            "pieces": parseInt(c.quantity),
            "declared_weight_kg": parseFloat(c.declared_weight_kg),
            "truck_origin": c.truck_plate || "B 8888 KGO",
            "driver_name": c.driver_name || "Bambang Pamungkas"
        },
        "virtual_scanner_sensor": {
            "device": "Dual-View High-Energy X-Ray (180x180cm, 320kV)",
            "penetration_steel_mm": 80,
            "density_spectrum_Z": isSuspect ? "HIGH_DENSITY_ANOMALY (Z > 20)" : "NORMAL_ORGANIC_MIXED (Z 7-14)",
            "effective_atomic_number": isSuspect ? 24.8 : 7.6
        },
        "evaluation_outcome": {
            "screening_decision": isSuspect ? "SUSPECT (BLOCKED)" : (isReturned ? "RETURNED_TO_TRUCK (EXPELLED)" : (isCleared ? "CLEARED (PASSED)" : "PENDING_EVALUATION")),
            "quarantine_vault_lockout": isSuspect,
            "csd_certificate": isCleared ? (c.csd_number || `CSD-ID-${(c.awb_number || '20261017').replace(/[^0-9]/g, '')}-77`) : null,
            "inspector_badge": c.inspector_id || "AVSEC-OPR-07",
            "special_treatment_action": isSuspect ? "SECONDARY_PHYSICAL_ETD_OR_REJECT" : (isReturned ? "EXPELLED_TO_ORIGINAL_TRUCK" : (isCleared ? "PROCEED_TO_SMART_SCALE" : "AWAITING_XRAY"))
        }
    };

    if (currentAgentDecision) {
        avsecJson.ai_agent_evaluation = {
            engine: currentAgentDecision.engine,
            decision: currentAgentDecision.decision,
            risk_score: currentAgentDecision.risk_score,
            confidence: currentAgentDecision.confidence,
            mismatch_detected: !!currentAgentDecision.mismatch,
            headline: currentAgentDecision.headline,
            actions: currentAgentDecision.actions
        };
    }
    renderAvsecAgentPanelBox(currentAgentDecision);

    if (jsonDisplay) jsonDisplay.textContent = JSON.stringify(avsecJson, null, 2);
    const avsecDisp = document.getElementById('avsecPayloadDisplay');
    if (avsecDisp) avsecDisp.textContent = JSON.stringify(avsecJson, null, 2);
}

function renderLoadBalanceDashboard(c) {
    if (!c) return;
    const declaredWt = parseFloat(c.declared_weight_kg || 150.0);
    const actualWt = c.actual_weight_kg ? parseFloat(c.actual_weight_kg) : (parseInt(c.current_stage || 1) >= 6 ? declaredWt : 0.0);
    const displayWt = actualWt > 0 ? actualWt : declaredWt;
    const diff = displayWt - declaredWt;
    const diffPercent = declaredWt > 0 ? ((diff / declaredWt) * 100).toFixed(2) : '0.00';
    const maxTolKg = (declaredWt * 0.05).toFixed(1);
    const isWithinTolerance = declaredWt > 0 ? (Math.abs(diff / declaredWt) <= 0.05) : true;
    const isHea = (declaredWt >= 150.0 || displayWt >= 150.0);

    const scaleWt = document.getElementById('lb-scale-weight');
    if (scaleWt) scaleWt.textContent = `${displayWt.toFixed(1)} KG`;
    
    const scaleDec = document.getElementById('lb-scale-declared');
    if (scaleDec) {
        const sign = diff >= 0 ? '+' : '';
        scaleDec.innerHTML = `${declaredWt.toFixed(1)} kg <span class="${isWithinTolerance ? 'text-emerald-600' : 'text-amber-600'} font-semibold">(${sign}${diff.toFixed(1)} kg / ${sign}${diffPercent}%)</span>`;
    }

    const tolBadge = document.getElementById('lb-tolerance-badge');
    if (tolBadge) {
        if (isWithinTolerance) {
            tolBadge.className = 'text-[9px] text-emerald-700 font-bold bg-emerald-50 px-2.5 py-0.5 rounded border border-emerald-200 inline-flex items-center gap-1';
            tolBadge.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-500"></i> Toleransi IATA &plusmn;5% : TERPENUHI (Maks &plusmn;${maxTolKg} kg)`;
        } else {
            tolBadge.className = 'text-[9px] text-amber-700 font-bold bg-amber-50 px-2.5 py-0.5 rounded border border-amber-200 inline-flex items-center gap-1';
            tolBadge.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-amber-500"></i> Melebihi Toleransi IATA &plusmn;5% (Maks &plusmn;${maxTolKg} kg) - CCA Req`;
        }
    }

    const heaBadge = document.getElementById('lb-hea-badge');
    if (heaBadge) {
        if (isHea) {
            heaBadge.classList.remove('hidden');
            heaBadge.innerHTML = `<i class="fa-solid fa-weight-hanging mr-1"></i> HEA (>=150kg)`;
        } else {
            heaBadge.classList.add('hidden');
        }
    }

    const uldCode = document.getElementById('lb-uld-code');
    if (uldCode) uldCode.textContent = c.uld_code || 'AKE-12345-GA';

    const tareWt = 75.0;
    const grossUldWt = displayWt + tareWt;
    const maxUldKg = parseFloat(c.uld_max_weight || 1588.0);
    const maxAircraftPayloadKg = parseFloat(c.flight_max_weight || 18500.0);
    const flightNum = c.flight_number || "GA-707";
    const aircraftType = c.aircraft_type || "Boeing 737-800F";
    const isUldFit = grossUldWt <= maxUldKg;
    const uldUtilization = ((grossUldWt / maxUldKg) * 100).toFixed(1);

    const netWtEl = document.getElementById('lb-cargo-net-wt');
    if (netWtEl) netWtEl.textContent = `${displayWt.toFixed(1)} kg`;

    const tareWtEl = document.getElementById('lb-uld-tare-wt');
    if (tareWtEl) tareWtEl.textContent = `${tareWt.toFixed(1)} kg`;

    const grossWtEl = document.getElementById('lb-uld-gross-wt');
    if (grossWtEl) {
        grossWtEl.textContent = `${grossUldWt.toFixed(1)} kg / ${maxUldKg.toFixed(0)} kg`;
        grossWtEl.className = isUldFit ? 'font-mono text-emerald-700 font-bold' : 'font-mono text-rose-600 font-bold';
    }

    const utilEl = document.getElementById('lb-uld-utilization');
    if (utilEl) utilEl.textContent = `${uldUtilization}% Terisi`;

    const progEl = document.getElementById('lb-uld-progress-bar');
    if (progEl) {
        progEl.style.width = `${Math.min(100, parseFloat(uldUtilization))}%`;
        progEl.className = isUldFit 
            ? 'h-1.5 rounded-full bg-teal-500 transition-all duration-500' 
            : 'h-1.5 rounded-full bg-rose-500 transition-all duration-500';
    }

    const uldStatusBadge = document.getElementById('lb-uld-status-badge');
    if (uldStatusBadge) {
        if (isUldFit) {
            uldStatusBadge.className = 'text-[9px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200';
            uldStatusBadge.textContent = 'Layak Dimuat (Compliant)';
        } else {
            uldStatusBadge.className = 'text-[9px] font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200';
            uldStatusBadge.textContent = 'Overload (Ditolak AKE)';
        }
    }

    const lbJson = {
        "event_type": "SMART_SCALE_WEIGHT_ACQUISITION",
        "protocol": "OIML_R76_SMART_SCALE_TCP_JSON",
        "timestamp": new Date().toISOString(),
        "cargo_shipment": {
            "awb_number": c.awb_number,
            "sscc": c.sscc,
            "commodity": c.description,
            "pieces": parseInt(c.quantity),
            "origin_truck": c.truck_plate || "B 8888 KGO"
        },
        "smart_scale_measurement": {
            "platform": "Smart Floor Scale 15-Ton IP68 (OIML R76 Class III)",
            "declared_weight_kg": declaredWt,
            "actual_weight_kg": parseFloat(displayWt.toFixed(1)),
            "discrepancy_kg": parseFloat(diff.toFixed(1)),
            "discrepancy_percent": `${diffPercent}%`,
            "tolerance_limit": "±5.00%",
            "max_tolerance_allowed_kg": parseFloat(maxTolKg),
            "tolerance_validation": isWithinTolerance ? "PASSED_WITHIN_TOLERANCE" : "EXCEEDED_TOLERANCE_WARNING",
            "iata_special_handling": isHea ? "HEA (Heavy Cargo >=150kg - Forklift Only)" : "GEN (General Cargo)",
            "billing_action": isWithinTolerance ? "STANDARD_INVOICE_CLEARED" : "ISSUE_CCA_CHARGE_CORRECTION"
        },
        "uld_container_criteria": {
            "assigned_container": c.uld_code || "AKE-12345-GA",
            "container_type": "AKE (LD3 Contour)",
            "container_tare_kg": tareWt,
            "net_cargo_payload_kg": parseFloat(displayWt.toFixed(1)),
            "total_gross_weight_kg": parseFloat(grossUldWt.toFixed(1)),
            "max_gross_weight_capacity_kg": maxUldKg,
            "capacity_utilization_percent": parseFloat(uldUtilization),
            "container_payload_status": isUldFit ? "COMPLIANT_WITHIN_ULD_LIMIT" : "OVERLOAD_EXCEEDS_ULD_LIMIT",
            "assigned_compartment": c.compartment || "AFT-LOWER-1"
        },
        "aircraft_flight_criteria": {
            "aircraft_model": aircraftType,
            "flight_number": flightNum,
            "max_aircraft_payload_capacity_kg": maxAircraftPayloadKg,
            "cumulative_flight_payload_kg": parseFloat((5420.0 + grossUldWt).toFixed(1)),
            "flight_payload_fit": "SAFE_WITHIN_AIRCRAFT_CAPACITY",
            "center_of_gravity_mac": (currentLoadmasterDecision && currentLoadmasterDecision.cg_mac) ? currentLoadmasterDecision.cg_mac : 28.4,
            "safe_cg_envelope": "18.0% - 34.0% MAC",
            "flight_trim_status": "OPTIMAL_SAFETY_TRIM",
            "electronic_loadsheet": (c.status === 'LOADED') ? "ISSUED_TO_EFB_COCKPIT" : "PENDING_FINAL_DISPATCH"
        }
    };

    if (currentLoadmasterDecision) {
        lbJson.ai_agent_loadmaster = {
            engine: currentLoadmasterDecision.engine,
            decision: currentLoadmasterDecision.decision,
            risk_score: currentLoadmasterDecision.risk_score,
            confidence: currentLoadmasterDecision.confidence,
            cca_required: !!currentLoadmasterDecision.cca_required,
            is_hea: !!currentLoadmasterDecision.is_hea,
            uld_overload: !!currentLoadmasterDecision.uld_overload,
            cg_mac: currentLoadmasterDecision.cg_mac,
            headline: currentLoadmasterDecision.headline,
            actions: currentLoadmasterDecision.actions
        };
    }

    const cgEl = document.getElementById('lb-cg-mac');
    if (cgEl) {
        const cgMac = (currentLoadmasterDecision && currentLoadmasterDecision.cg_mac) ? currentLoadmasterDecision.cg_mac : 28.4;
        cgEl.textContent = `${cgMac}% MAC (Safe)`;
    }

    const isLoaded = (c.status === 'LOADED');
    const stageNum = parseInt(c.current_stage || 1);

    const fOpsBadge = document.getElementById('lb-flight-ops-badge');
    const fOpsDot = document.getElementById('lb-flight-ops-dot');
    const fOpsText = document.getElementById('lb-flight-ops-text');
    if (fOpsBadge && fOpsDot && fOpsText) {
        if (isLoaded) {
            fOpsBadge.className = 'text-xs font-mono text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-300 font-bold flex items-center gap-1.5';
            fOpsDot.className = 'w-2 h-2 rounded-full bg-emerald-500';
            fOpsText.textContent = '✓ Terkirim ke Kokpit & Flight Ops (Selesai)';
        } else if (stageNum >= 5) {
            fOpsBadge.className = 'text-xs font-mono text-[#087F8A] bg-teal-50 px-3 py-1.5 rounded-xl border border-teal-200 font-bold flex items-center gap-1.5';
            fOpsDot.className = 'w-2 h-2 rounded-full bg-teal-500 animate-ping';
            fOpsText.textContent = stageNum === 7 ? 'Siap Kirim Manifes (Tahap 7)' : 'Proses Load & Balance Aktif';
        } else {
            fOpsBadge.className = 'text-xs font-mono text-slate-600 bg-slate-100 px-3 py-1.5 rounded-xl border border-gray-200 font-bold flex items-center gap-1.5';
            fOpsDot.className = 'w-2 h-2 rounded-full bg-slate-400';
            fOpsText.textContent = 'Menunggu Penimbangan & ULD';
        }
    }

    const lbStatusTag = document.getElementById('lb-json-status-tag');
    if (lbStatusTag) {
        if (isLoaded) {
            lbStatusTag.className = 'text-[10px] font-mono text-emerald-300 bg-emerald-950/70 px-2 py-0.5 rounded border border-emerald-700/80 font-bold';
            lbStatusTag.textContent = 'DISPATCHED · SENT TO COCKPIT (LOADED)';
        } else {
            lbStatusTag.className = 'text-[10px] font-mono text-teal-300 bg-teal-950/60 px-2 py-0.5 rounded border border-teal-800/80';
            lbStatusTag.textContent = 'SCALE SYNC ACTIVE';
        }
    }

    const lsEl = document.getElementById('lb-loadsheet-status');
    if (lsEl) {
        if (isLoaded) {
            lsEl.textContent = 'Terkirim ke Kokpit (SENT)';
            lsEl.className = 'text-emerald-700 font-mono font-bold';
        } else {
            lsEl.textContent = stageNum >= 7 ? 'Siap Dikirim' : 'Menunggu Finalisasi';
            lsEl.className = 'text-amber-600 font-mono';
        }
    }

    const s7Banner = document.getElementById('lb-stage7-completed-banner');
    const s7Detail = document.getElementById('lb-stage7-completed-detail');
    if (s7Banner) {
        if (isLoaded) {
            s7Banner.classList.remove('hidden');
            if (s7Detail) {
                s7Detail.textContent = `Electronic Loadsheet resmi telah terkirim ke Kokpit (EFB) ${flightNum} & ULD ${c.uld_code || 'AKE-12345-GA'} (${grossUldWt.toFixed(1)} kg) sudah dimuat ke pesawat.`;
            }
        } else {
            s7Banner.classList.add('hidden');
        }
    }

    const btnStage7 = document.getElementById('lb-btn-finalize-stage7');
    if (btnStage7) {
        if (isLoaded) {
            btnStage7.className = 'px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer';
            btnStage7.innerHTML = '<i class="fa-solid fa-circle-check mr-1.5"></i><span>Sudah Terkirim &amp; Dimuat (Selesai)</span>';
        } else {
            btnStage7.className = 'px-4 py-2.5 rounded-xl bg-[#0D1C42] hover:bg-slate-800 text-white font-bold text-xs shadow-md transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer';
            btnStage7.innerHTML = '<i class="fa-solid fa-plane-departure mr-1.5"></i><span>Finalisasi Manifes (Tahap 7)</span>';
        }
    }

    if (Array.isArray(currentUldsList) && currentUldsList.length) {
        renderUldContainersTable(currentUldsList);
    }

    renderLoadmasterAgentPanelBox(currentLoadmasterDecision);

    const streamDisp = document.getElementById('lb-json-stream-display');
    if (streamDisp) streamDisp.textContent = JSON.stringify(lbJson, null, 2);
    const lbDisp = document.getElementById('lbPayloadDisplay');
    if (lbDisp) lbDisp.textContent = JSON.stringify(lbJson, null, 2);
}

function renderUldContainersTable(ulds) {
    const tbody = document.getElementById('lb-uld-table-body');
    if (!tbody || !Array.isArray(ulds)) return;
    tbody.innerHTML = ulds.map(u => {
        const isDone = (u.status === 'LOADED' || u.status === 'FULL');
        const badgeCls = isDone
            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
            : 'bg-teal-50 text-[#087F8A] border border-teal-200';
        const statusText = u.status === 'LOADED' ? 'LOADED (Terkirim)' : u.status;
        return `
            <tr class="hover:bg-slate-50/70 transition-colors">
                <td class="py-2 px-3 font-mono font-bold text-[#0D1C42]">${escapeHtml(u.uld_code)}</td>
                <td class="py-2 px-3 font-semibold">${escapeHtml(u.uld_type)}</td>
                <td class="py-2 px-3 font-mono text-emerald-700 font-bold">${parseFloat(u.current_weight_kg || 0).toFixed(1)} kg</td>
                <td class="py-2 px-3 font-mono text-slate-500">${parseFloat(u.max_weight_kg || 1588).toFixed(1)} kg</td>
                <td class="py-2 px-3">
                    <span class="font-bold text-[#087F8A]">${escapeHtml(u.flight_number || 'GA-707')}</span> &bull;
                    <span class="text-slate-500 font-mono">${escapeHtml(u.compartment)}</span>
                </td>
                <td class="py-2 px-3">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${badgeCls}">
                        ${escapeHtml(statusText)}
                    </span>
                </td>
            </tr>
        `;
    }).join('');
}

// ═══════════════════════════════════════════════════════════════════════════════
// PENGUJIAN INTERAKTIF TOLERANSI IATA ±5% & PROTEKSI OVERLOAD ULD AKE
// ═══════════════════════════════════════════════════════════════════════════════

function openToleranceTestModal() {
    if (!currentCargoData) return;
    const declared = parseFloat(currentCargoData.declared_weight_kg || 150.0);
    const maxTol = (declared * 0.05).toFixed(1);
    const exWithin = +(declared * 1.04).toFixed(1); // +4.0% (misal 150 -> 156 kg)
    const exExceeded = +(declared * 1.075).toFixed(1); // +7.5% (misal 150 -> 161.3 kg)

    Swal.fire({
        title: 'Uji Toleransi IATA & Batas ULD',
        html: `
            <div class="text-xs text-left p-3.5 rounded-xl bg-slate-50 border border-gray-200 space-y-3 mt-2">
                <div class="pb-2 border-b border-gray-200">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Kargo Terpilih</span>
                    <strong class="text-[#0D1C42]">${escapeHtml(currentCargoData.awb_number)}</strong> &bull; 
                    Deklarasi Berat: <strong class="text-emerald-700 font-mono">${declared.toFixed(1)} kg</strong>
                    <div class="text-[11px] text-slate-500 mt-0.5">Batas Toleransi IATA &plusmn;5%: <strong>&plusmn;${maxTol} kg</strong> (${(declared - parseFloat(maxTol)).toFixed(1)} s/d ${(declared + parseFloat(maxTol)).toFixed(1)} kg)</div>
                </div>

                <p class="font-bold text-[#0D1C42] text-[11px]">Pilih Skenario Pengujian Tahap 5 &amp; 6:</p>

                <div class="space-y-2">
                    <button type="button" onclick="executeScaleToleranceTest('within', ${declared})" class="w-full text-left p-2.5 rounded-xl border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 transition-all cursor-pointer">
                        <div class="flex items-center justify-between">
                            <strong class="text-emerald-800 text-xs"><i class="fa-solid fa-circle-check mr-1.5"></i>1. Uji Lolos Toleransi (&plusmn;5% IATA)</strong>
                            <span class="text-[10px] font-mono font-bold text-emerald-700 bg-emerald-200/60 px-1.5 py-0.5 rounded">+4.0% (${exWithin} kg)</span>
                        </div>
                        <p class="text-[10.5px] text-emerald-700 mt-1">Aktual ${exWithin} kg vs Deklarasi ${declared} kg (selisih +${(exWithin - declared).toFixed(1)} kg). Sesuai IATA CSC Res. 600b, kargo lolos langsung ke ULD build-up.</p>
                    </button>

                    <button type="button" onclick="executeScaleToleranceTest('exceeded', ${declared})" class="w-full text-left p-2.5 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 transition-all cursor-pointer">
                        <div class="flex items-center justify-between">
                            <strong class="text-amber-800 text-xs"><i class="fa-solid fa-triangle-exclamation mr-1.5"></i>2. Uji Melebihi Toleransi (&gt;5% Discrepancy)</strong>
                            <span class="text-[10px] font-mono font-bold text-amber-700 bg-amber-200/60 px-1.5 py-0.5 rounded">+7.5% (${exExceeded} kg)</span>
                        </div>
                        <p class="text-[10.5px] text-amber-700 mt-1">Aktual ${exExceeded} kg (selisih +${(exExceeded - declared).toFixed(1)} kg). Men-trigger peringatan CCA (Cargo Charges Correction Advice) &amp; sinkronisasi berat fisik ke Load Planning.</p>
                    </button>

                    <button type="button" onclick="executeScaleToleranceTest('overload', ${declared})" class="w-full text-left p-2.5 rounded-xl border border-rose-300 bg-rose-50 hover:bg-rose-100 transition-all cursor-pointer">
                        <div class="flex items-center justify-between">
                            <strong class="text-rose-800 text-xs"><i class="fa-solid fa-ban mr-1.5"></i>3. Uji Proteksi Overload Kontainer ULD AKE</strong>
                            <span class="text-[10px] font-mono font-bold text-rose-700 bg-rose-200/60 px-1.5 py-0.5 rounded">&gt; 1.588 kg (Overload)</span>
                        </div>
                        <p class="text-[10.5px] text-rose-700 mt-1">Mensimulasikan kargo berat 1.550 kg (Gross ULD 1.625 kg &gt; 1.588 kg batas AKE). Sistem menolak muatan masuk kontainer AKE untuk mencegah kerusakan struktural pesawat.</p>
                    </button>
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Tutup',
        cancelButtonColor: '#94a3b8',
        background: '#ffffff',
        color: '#0D1C42',
        customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200 max-w-lg' }
    });
}

function executeScaleToleranceTest(scenario, declared) {
    Swal.close();
    let payloadData = {
        cargo_id: currentCargoData.id,
        stage: 5,
        action: 'advance'
    };

    if (scenario === 'within') {
        payloadData.actual_weight = +(declared * 1.04).toFixed(1);
    } else if (scenario === 'exceeded') {
        payloadData.simulate_exceeded_tolerance = true;
    } else if (scenario === 'overload') {
        payloadData.actual_weight = 1550.0;
    }

    Swal.fire({
        title: 'AI Loadmaster Menimbang di Smart Scale...',
        text: 'Membaca telemetri sensor load cell & mengevaluasi standar IATA...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#ffffff',
        color: '#0D1C42',
        customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
    });

    fetch('api/simulate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payloadData)
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            if (res.loadmaster_agent) {
                currentLoadmasterDecision = res.loadmaster_agent;
                renderLoadmasterAgentPanelBox(currentLoadmasterDecision);
            }
            Swal.fire({
                title: 'Peringatan Operasional!',
                text: res.message,
                icon: 'error',
                confirmButtonColor: '#087F8A',
                background: '#ffffff',
                color: '#0D1C42',
                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
            });
            return;
        }

        if (res.cargo) {
            currentCargoData = res.cargo;
            renderCargoHeader(currentCargoData);
        }
        if (res.payload) currentMasterPayload = res.payload;
        if (res.loadmaster_agent) currentLoadmasterDecision = res.loadmaster_agent;

        if (scenario === 'overload') {
            // Lanjutkan langsung ke Tahap 6 untuk menguji AI Agent Overload Blocker pada kontainer AKE (1.588 kg)
            return fetch('api/simulate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    cargo_id: currentCargoData.id,
                    stage: 6,
                    action: 'advance',
                    target_uld_type: 'AKE'
                })
            })
            .then(r2 => r2.json())
            .then(res6 => {
                if (res6.loadmaster_agent) {
                    currentLoadmasterDecision = res6.loadmaster_agent;
                }
                Swal.fire({
                    title: res6.overload ? 'AI Loadmaster: OVERLOAD PREVENTED!' : 'Hasil Konsolidasi ULD',
                    text: res6.message,
                    icon: res6.overload ? 'error' : 'success',
                    confirmButtonColor: res6.overload ? '#e11d48' : '#087F8A',
                    background: '#ffffff',
                    color: '#0D1C42',
                    customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
                }).then(() => {
                    initSimulation(currentCargoData.id, 6, currentSystemDashboard);
                });
            });
        }

        const isExceeded = scenario === 'exceeded';
        Swal.fire({
            title: isExceeded ? 'AI Loadmaster: Terbitkan e-CCA!' : 'AI Loadmaster: Terverifikasi!',
            text: res.message,
            icon: isExceeded ? 'warning' : 'success',
            confirmButtonColor: isExceeded ? '#d97706' : '#087F8A',
            background: '#ffffff',
            color: '#0D1C42',
            customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
        }).then(() => {
            initSimulation(currentCargoData.id, 5, currentSystemDashboard);
        });
    })
    .catch(err => {
        Swal.fire({
            title: 'Error',
            text: 'Gagal melakukan uji penimbangan: ' + err,
            icon: 'error',
            confirmButtonColor: '#087F8A',
            background: '#ffffff',
            color: '#0D1C42'
        });
    });
}


// ═══════════════════════════════════════════════════════════════════════════════
// MASTER CARGO JSON PAYLOAD MODAL & EXPORT HANDLERS (HARDWARE REPLACEMENT PoC)
// ═══════════════════════════════════════════════════════════════════════════════

function openMasterCargoPayloadModal() {
    if (!currentCargoData) return;
    const modal = document.getElementById('masterCargoPayloadModal');
    const modalBadge = document.getElementById('modal-payload-badge');
    const modalAwb = document.getElementById('modal-cargo-awb');
    const modalDisplay = document.getElementById('modalJsonDisplay');

    const payloadId = 'CMP-' + currentCargoData.id;
    if (modalBadge) modalBadge.textContent = payloadId;
    if (modalAwb) modalAwb.textContent = `${currentCargoData.awb_number} (${currentCargoData.description})`;

    const targetPayload = currentMasterPayload || {
        "cargo_payload_id": payloadId,
        "identity": currentCargoData,
        "note": "Memuat master payload dari server..."
    };

    if (modalDisplay) {
        modalDisplay.textContent = JSON.stringify(targetPayload, null, 2);
    }
    if (modal) modal.classList.remove('hidden');
}

function closeMasterCargoPayloadModal() {
    const modal = document.getElementById('masterCargoPayloadModal');
    if (modal) modal.classList.add('hidden');
}

function copyMasterPayloadJson() {
    if (!currentCargoData) return;
    const targetPayload = currentMasterPayload || currentCargoData;
    const text = JSON.stringify(targetPayload, null, 2);
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            title: `Master JSON Payload (CMP-${currentCargoData.id}) berhasil disalin!`,
            icon: 'success',
            showConfirmButton: false,
            timer: 2000,
            background: '#ffffff',
            color: '#0D1C42',
            customClass: { popup: 'rounded-xl shadow-lg border border-gray-200' }
        });
    });
}

function downloadMasterPayloadJson() {
    if (!currentCargoData) return;
    const payloadId = 'CMP-' + currentCargoData.id;
    const targetPayload = currentMasterPayload || currentCargoData;
    const text = JSON.stringify(targetPayload, null, 2);
    const blob = new Blob([text], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${payloadId}-Master-Payload.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function renderAvsecAgentPanelBox(agent) {
    const box = document.getElementById('avsec-ai-agent-box');
    if (!box) return;
    if (!agent || !agent.decision) {
        box.classList.add('hidden');
        return;
    }
    box.classList.remove('hidden');
    const isSus = agent.decision === 'SUSPECT';
    box.className = 'p-4 rounded-2xl border shadow-sm mb-5 space-y-2.5 ' +
        (isSus ? 'border-rose-300 bg-rose-50/70' : 'border-violet-200 bg-violet-50/75');

    const badge = document.getElementById('avsec-agent-engine-badge');
    if (badge) {
        badge.textContent = agent.engine || 'langgraph-js';
    }
    const metrics = document.getElementById('avsec-agent-metrics');
    if (metrics) {
        metrics.textContent = 'Risiko ' + Math.round((agent.risk_score || 0) * 100) + '% · Keyakinan ' + Math.round((agent.confidence || 0) * 100) + '%';
    }
    const headline = document.getElementById('avsec-agent-headline');
    if (headline) {
        headline.textContent = agent.headline || agent.decision;
        headline.className = 'text-xs font-bold px-3 py-2 rounded-xl border ' +
            (isSus ? 'bg-white text-rose-800 border-rose-200' : 'bg-white text-emerald-800 border-emerald-200');
    }
    const traceEl = document.getElementById('avsec-agent-trace');
    if (traceEl && Array.isArray(agent.trace)) {
        traceEl.innerHTML = agent.trace.map(t => `
            <div class="p-2.5 rounded-xl bg-white border border-slate-200/80">
                <div class="font-extrabold text-[10px] uppercase tracking-wider ${t.node === 'act' && isSus ? 'text-rose-700' : 'text-[#087F8A]'}">${escapeHtml(t.title || t.node)}</div>
                <div class="text-slate-600 mt-1 leading-snug">${escapeHtml(t.detail || '')}</div>
            </div>
        `).join('');
    }
}

function renderLoadmasterAgentPanelBox(agent) {
    const box = document.getElementById('loadmaster-ai-agent-box');
    if (!box) return;
    if (!agent || !agent.decision) {
        box.classList.add('hidden');
        return;
    }
    box.classList.remove('hidden');
    const isOverload = agent.decision === 'OVERLOAD_REJECTED' || !!agent.uld_overload;
    const isCca = agent.decision === 'CCA_AMENDMENT' || !!agent.cca_required;

    let boxBorder = 'border-teal-200 bg-teal-50/75';
    let headlineStyle = 'bg-white text-emerald-800 border-emerald-200';
    if (isOverload) {
        boxBorder = 'border-rose-300 bg-rose-50/70';
        headlineStyle = 'bg-white text-rose-800 border-rose-200';
    } else if (isCca) {
        boxBorder = 'border-amber-300 bg-amber-50/75';
        headlineStyle = 'bg-white text-amber-800 border-amber-200';
    }
    box.className = 'p-4 rounded-2xl border shadow-sm mb-5 space-y-2.5 ' + boxBorder;

    const badge = document.getElementById('lm-agent-engine-badge');
    if (badge) {
        badge.textContent = agent.engine || 'langgraph-js';
    }
    const metrics = document.getElementById('lm-agent-metrics');
    if (metrics) {
        metrics.textContent = 'Risiko ' + Math.round((agent.risk_score || 0) * 100) + '% · Keyakinan ' + Math.round((agent.confidence || 0) * 100) + '%';
    }
    const headline = document.getElementById('lm-agent-headline');
    if (headline) {
        headline.textContent = agent.headline || agent.decision;
        headline.className = 'text-xs font-bold px-3 py-2 rounded-xl border ' + headlineStyle;
    }

    const chipsEl = document.getElementById('lm-agent-chips');
    if (chipsEl) {
        const chips = [];
        chips.push(agent.is_within_tolerance !== false
            ? '<span class="px-2 py-0.5 rounded-full font-bold bg-emerald-100 text-emerald-800 border border-emerald-200"><i class="fa-solid fa-circle-check mr-1"></i>IATA &plusmn;5% OK</span>'
            : '<span class="px-2 py-0.5 rounded-full font-bold bg-amber-100 text-amber-800 border border-amber-200"><i class="fa-solid fa-file-invoice-dollar mr-1"></i>e-CCA Diterbitkan</span>');
        if (agent.is_hea) {
            chips.push('<span class="px-2 py-0.5 rounded-full font-bold bg-purple-100 text-purple-800 border border-purple-200"><i class="fa-solid fa-weight-hanging mr-1"></i>HEA Forklift</span>');
        }
        chips.push(agent.uld_overload
            ? '<span class="px-2 py-0.5 rounded-full font-bold bg-rose-100 text-rose-800 border border-rose-200"><i class="fa-solid fa-ban mr-1"></i>Overload ULD (Alihkan PMC)</span>'
            : `<span class="px-2 py-0.5 rounded-full font-bold bg-white text-[#087F8A] border border-teal-200"><i class="fa-solid fa-crosshairs mr-1"></i>CoG ${escapeHtml(String(agent.cg_mac ?? 28.4))}% MAC</span>`);
        chipsEl.innerHTML = chips.join('');
    }

    const traceEl = document.getElementById('lm-agent-trace');
    if (traceEl && Array.isArray(agent.trace)) {
        traceEl.innerHTML = agent.trace.map(t => `
            <div class="p-2.5 rounded-xl bg-white border border-slate-200/80">
                <div class="font-extrabold text-[10px] uppercase tracking-wider ${t.node === 'act' && isOverload ? 'text-rose-700' : 'text-[#087F8A]'}">${escapeHtml(t.title || t.node)}</div>
                <div class="text-slate-600 mt-1 leading-snug">${escapeHtml(t.detail || '')}</div>
            </div>
        `).join('');
    }
}
