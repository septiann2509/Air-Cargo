// assets/js/simulation.js
// Logika Eksekusi Laboratorium Simulasi 7 Tahapan Kargo Udara (InJourney Airports Design System)

let currentCargoData = null;
let currentStageView = 1;
let currentSystemDashboard = 'all';
let activePayloadTab = 'request';
let currentRequestJson = {};
let currentResponseJson = {};

// Deskripsi tiap tahapan
const STAGE_METADATA = {
    1: {
        title: "Kedatangan Truk & Booking Slot (TMS/TAS)",
        hardware: "TMS RFID Gate Reader & Barrier Gate",
        software: "Intermodal TMS / Truck Appointment System (TAS)",
        icon: "fa-truck",
        explanation: "Sistem TMS/TAS mengatur time-slot kedatangan truk dari moda transportasi darat untuk menghindari antrean di gerbang bandara. Truk diverifikasi dan diarahkan otomatis ke dock kargo yang tersedia.",
        standard: "EDIFACT / REST API Gate Ingest"
    },
    2: {
        title: "Scan RFID/Barcode di Gerbang Masuk (High-Speed Scanner)",
        hardware: "High-Speed Barcode/RFID UHF Tunnel Scanner (2.5 m/s, IP65)",
        software: "Cargo Management Software (CMS) Inbound Edge",
        icon: "fa-qrcode",
        explanation: "Kargo dipindai secara massal tanpa sentuh saat melewati konveyor gerbang. Tag GS1-128 dan SSCC 18-digit dibaca otomatis dan diparsing menjadi data digital seketika tanpa entri manual.",
        standard: "GS1 AI (00) SSCC & (01) GTIN Standard"
    },
    3: {
        title: "Registrasi Data & Pencocokan e-AWB di CMS",
        hardware: "CMS Core Application Server & Industrial Network",
        software: "Cargo Management Software (CMS Master)",
        icon: "fa-laptop-code",
        explanation: "CMS menerima paket data hasil pindaian gerbang dan mencocokkannya dengan dokumen Air Waybill elektronik (e-AWB). Sistem menetapkan rute internal kargo menuju area screening keamanan.",
        standard: "IATA e-freight / e-AWB XML/JSON"
    },
    4: {
        title: "Pemeriksaan Keamanan X-Ray (AVSEC System)",
        hardware: "Dual-View / High-Energy Air Cargo X-Ray Scanner (180x180 cm, 320kV)",
        software: "Aviation Security Management System (AVSEC System)",
        icon: "fa-shield-halved",
        explanation: "Kargo dipindai dengan sinar-X sudut ganda untuk mendeteksi barang berbahaya (Dangerous Goods, baterai terlarang, bahan peledak). Jika CLEARED diterbitkan sertifikat CSD digital; jika SUSPECT kargo langsung diblokir otomatis.",
        standard: "ICAO / AVSEC CSD (Consignment Security Declaration)"
    },
    5: {
        title: "Penimbangan Kargo (Smart ULD Weighing Station)",
        hardware: "Smart ULD Floor Scale (Kapasitas 15 Ton, Akurasi ±1 kg, IP68)",
        software: "CMS & Modul Integrasi Weight & Balance",
        icon: "fa-scale-balanced",
        explanation: "Kargo ditimbang di timbangan lantai industri yang terpasang rata dengan lantai gudang. Nilai berat kotor aktual (actual weight) dicatat dan dicocokkan dengan deklarasi e-AWB untuk mencegah anomali muatan.",
        standard: "OIML R76 Weighing Standard / TCP Socket Payload"
    },
    6: {
        title: "Build-Up ULD & Kalkulasi Weight & Balance",
        hardware: "Automated ULD Workstation & Stacking Conveyor",
        software: "Weight & Balance System / Load Planning Software",
        icon: "fa-boxes-packing",
        explanation: "Kargo dikonsolidasikan ke dalam kontainer pesawat (ULD tipe AKE/PMC). Software menghitung distribusi massa pesawat, batas beban lantai kabin (floor load limit), dan titik keseimbangan (Center of Gravity / CoG).",
        standard: "IATA ULD Regulations & Weight & Balance Envelope"
    },
    7: {
        title: "Update Manifes Penerbangan & Pemuatan ke Pesawat",
        hardware: "Airport Ramp Dolly, Cargo High-Loader, & Cockpit EFB",
        software: "CMS & Airline Flight Operations System",
        icon: "fa-plane-departure",
        explanation: "Manifes kargo penerbangan final diperbarui dengan data ULD terpasang. Dokumen Electronic Loadsheet resmi diterbitkan ke kokpit pilot dan kargo diberangkatkan (status DEPARTED).",
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

    // Badge status (InJourney Clean Theme)
    const badge = document.getElementById('card-status-badge');
    badge.textContent = c.status;
    badge.className = 'inline-block px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wide ';
    if (c.status === 'CLEARED' || c.status === 'LOADED') {
        badge.className += 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    } else if (c.status === 'SUSPECT') {
        badge.className += 'bg-rose-50 text-rose-700 border border-rose-300 animate-pulse';
    } else {
        badge.className += 'bg-teal-50 text-[#087F8A] border border-teal-200';
    }

    // e-Label info
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
    document.getElementById('currentStageBadge').textContent = `Tahap Kargo Saat Ini: ${completedStage} (${status})`;

    for (let s = 1; s <= 7; s++) {
        const btn = document.getElementById(`step-btn-${s}`);
        const circle = document.getElementById(`step-circle-${s}`);
        const icon = document.getElementById(`step-icon-${s}`);
        const title = document.getElementById(`step-title-${s}`);

        btn.className = 'stage-step-card text-left p-3.5 rounded-xl border transition-all flex flex-col justify-between ';

        if (s < completedStage) {
            // Sudah lewat (Selesai)
            circle.className = 'w-6 h-6 rounded-full bg-emerald-500 text-white text-xs font-bold flex items-center justify-center font-mono shadow-sm';
            circle.innerHTML = '<i class="fa-solid fa-check text-[10px]"></i>';
            icon.className = `fa-solid ${STAGE_METADATA[s].icon} text-emerald-600 text-xs`;
            title.className = 'text-[11px] font-bold text-emerald-700 leading-snug';
            btn.className += 'border-emerald-200 bg-emerald-50/40 hover:bg-emerald-50/70';
        } else if (s === completedStage) {
            // Tahap saat ini
            if (status === 'SUSPECT' && s === 4) {
                circle.className = 'w-6 h-6 rounded-full bg-rose-600 text-white text-xs font-bold flex items-center justify-center font-mono animate-pulse ring-4 ring-rose-100';
                circle.textContent = '!';
                icon.className = `fa-solid ${STAGE_METADATA[s].icon} text-rose-600 text-xs`;
                title.className = 'text-[11px] font-bold text-rose-700 leading-snug';
                btn.className += 'border-rose-300 bg-rose-50 shadow-sm';
            } else {
                circle.className = 'w-6 h-6 rounded-full bg-gradient-to-tr from-[#0CA1AF] to-[#087F8A] text-white text-xs font-bold flex items-center justify-center font-mono ring-4 ring-teal-100 shadow-sm';
                circle.textContent = s;
                icon.className = `fa-solid ${STAGE_METADATA[s].icon} text-[#087F8A] text-xs`;
                title.className = 'text-[11px] font-extrabold text-[#0D1C42] leading-snug';
                btn.className += 'border-[#0CA1AF] bg-teal-50/40 shadow-sm';
            }
        } else {
            // Belum dicapai
            circle.className = 'w-6 h-6 rounded-full bg-slate-200 text-slate-500 text-xs font-bold flex items-center justify-center font-mono';
            circle.textContent = s;
            icon.className = `fa-solid ${STAGE_METADATA[s].icon} text-slate-400 text-xs`;
            title.className = 'text-[11px] font-bold text-slate-600 leading-snug';
            btn.className += 'border-gray-200 bg-slate-50/70 hover:border-gray-300 hover:bg-slate-100/50';
        }
    }
}

function selectStageView(stage) {
    currentStageView = stage;
    const meta = STAGE_METADATA[stage];

    // Highlight card
    for (let s = 1; s <= 7; s++) {
        const btn = document.getElementById(`step-btn-${s}`);
        if (s === stage) {
            btn.classList.add('ring-2', 'ring-[#087F8A]');
        } else {
            btn.classList.remove('ring-2', 'ring-[#087F8A]');
        }
    }

    // Set stage details
    document.getElementById('activeStageNumber').textContent = `Tahap ${stage} dari 7`;
    document.getElementById('activeStageTitle').textContent = meta.title;
    document.getElementById('activeHardwareName').textContent = meta.hardware;
    document.getElementById('activeSoftwareName').textContent = meta.software;
    document.getElementById('activeStageExplanation').textContent = meta.explanation;
    document.getElementById('payloadStandardBadge').textContent = meta.standard;
    document.getElementById('activeStageIcon').className = `fa-solid ${meta.icon}`;

    // Status badge (InJourney Clean Theme)
    const badge = document.getElementById('stageCompleteBadge');
    const currentCargoStage = currentCargoData ? currentCargoData.current_stage : 1;

    if (stage < currentCargoStage) {
        badge.textContent = 'Status: Selesai Dilalui';
        badge.className = 'text-xs px-3 py-1 rounded-full font-bold bg-emerald-50 text-emerald-700 border border-emerald-200';
    } else if (stage === currentCargoStage) {
        badge.textContent = (currentCargoData && currentCargoData.status === 'SUSPECT') ? 'Status: Kargo Diblokir' : 'Status: Tahap Aktif';
        badge.className = (currentCargoData && currentCargoData.status === 'SUSPECT') 
            ? 'text-xs px-3 py-1 rounded-full font-bold bg-rose-50 text-rose-700 border border-rose-300 animate-pulse'
            : 'text-xs px-3 py-1 rounded-full font-bold bg-teal-50 text-[#087F8A] border border-teal-200';
    } else {
        badge.textContent = 'Status: Menunggu Tahap Sebelumnya';
        badge.className = 'text-xs px-3 py-1 rounded-full font-bold bg-slate-100 text-slate-500 border border-slate-200';
    }

    // Render Stage Visual Graphic & Action Buttons
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
        const wt = c.actual_weight_kg ? parseFloat(c.actual_weight_kg).toFixed(1) : parseFloat(c.declared_weight_kg).toFixed(1);
        wrap.innerHTML = `
            <div class="flex flex-col items-center justify-center">
                <div class="p-4 rounded-2xl bg-[#0D1C42] border border-slate-700 flex items-center space-x-6 shadow-xl">
                    <div class="text-center">
                        <span class="text-[9px] text-teal-300 uppercase tracking-widest block font-bold">Smart Floor Scale</span>
                        <div class="text-3xl font-black text-amber-400 font-mono tracking-wider mt-0.5">
                            ${wt} <span class="text-sm text-slate-300">KG</span>
                        </div>
                        <span class="text-[9px] text-emerald-400 font-mono">Load Cell IP68 &bull; ±1kg</span>
                    </div>
                    <div class="border-l border-slate-700 pl-4 text-left text-[10px] space-y-1">
                        <div class="text-slate-300">Deklarasi AWB: <strong class="text-white">${parseFloat(c.declared_weight_kg).toFixed(1)} kg</strong></div>
                        <div class="text-slate-300">Toleransi: <span class="text-emerald-400 font-bold">VALID (Within Limit)</span></div>
                    </div>
                </div>
            </div>
        `;
    } else if (stage === 6) {
        wrap.innerHTML = `
            <div class="flex items-center justify-center space-x-6">
                <div class="w-16 h-16 rounded-xl bg-white border border-teal-200 flex items-center justify-center text-3xl text-[#087F8A] shadow-md">
                    <i class="fa-solid fa-boxes-packing"></i>
                </div>
                <div class="text-left text-xs">
                    <div class="font-black text-[#0D1C42] font-mono text-sm">${c.uld_code || 'AKE-12345-GA'} (Tipe ${c.uld_type || 'AKE'})</div>
                    <div class="text-[11px] text-[#087F8A] font-bold mt-0.5">Posisi: ${c.compartment || 'AFT-LOWER-1'}</div>
                    <div class="text-[10px] text-slate-500 mt-1">Weight &amp; Balance: Center of Gravity (CoG) 28.4% (Optimal)</div>
                </div>
            </div>
        `;
    } else if (stage === 7) {
        wrap.innerHTML = `
            <div class="flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-[#04AFBF] to-[#087F8A] flex items-center justify-center text-3xl text-white mb-2 shadow-lg shadow-teal-500/25">
                    <i class="fa-solid fa-plane-departure"></i>
                </div>
                <span class="text-sm font-extrabold text-[#0D1C42]">${c.flight_number || 'GA-707'} (Garuda Indonesia Cargo)</span>
                <span class="text-xs text-[#087F8A] font-mono mt-0.5 font-bold">Loadsheet Diterbitkan &amp; Ramp Pushback Ready</span>
            </div>
        `;
    }
}

function renderStageActionButtons(stage) {
    const container = document.getElementById('stageActionButtons');
    const c = currentCargoData;
    if (!container || !c) return;

    const currentCargoStage = c.current_stage;
    container.innerHTML = '';

    // ── TAHAP 4: SCREENING X-RAY AVSEC ──
    if (stage === 4) {
        if (c.status === 'CLEARED') {
            // Status kargo sudah CLEARED: Tonjolkan tombol lanjut ke Tahap 5
            container.innerHTML = `
                <button onclick="selectStageView(5)" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5 animate-pulse cursor-pointer">
                    <i class="fa-solid fa-scale-balanced mr-2 text-sm"></i>
                    <span>Lihat &amp; Lanjut ke Tahap 5 (Penimbangan Smart Scale)</span>
                    <i class="fa-solid fa-arrow-right ml-2 text-xs"></i>
                </button>
                <button onclick="executeStageAction(4, 'CLEARED')" class="px-3.5 py-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-300 shadow-sm transition-all flex items-center cursor-pointer">
                    <i class="fa-solid fa-rotate mr-1.5 text-xs text-emerald-600"></i>
                    <span>Simulasi Ulang Lolos (CLEARED)</span>
                </button>
                <button onclick="executeStageAction(4, 'SUSPECT')" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-rose-50 text-rose-700 hover:text-rose-800 text-xs font-bold border border-rose-300 shadow-sm transition-all flex items-center cursor-pointer">
                    <i class="fa-solid fa-triangle-exclamation mr-1.5 text-xs text-rose-500"></i>
                    <span>Simulasi Temuan Bahaya (SUSPECT)</span>
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
                    <span class="font-bold"><i class="fa-solid fa-triangle-exclamation mr-1.5 text-rose-600 animate-pulse"></i> Kargo Berstatus SUSPECT — Dikunci di Ruang Khusus Karantina AVSEC</span>
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
                <button onclick="executeStageAction(4, 'CLEARED')" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-300 shadow-sm transition-all flex items-center cursor-pointer">
                    <i class="fa-solid fa-rotate mr-1.5 text-xs text-emerald-600"></i>
                    <span>Simulasi Ulang Lolos (CLEARED)</span>
                </button>
            `;
            return;
        } else {
            // Belum screening X-Ray
            container.innerHTML = `
                <button onclick="executeStageAction(4, 'CLEARED')" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-md shadow-emerald-500/20 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                    <i class="fa-solid fa-shield-halved mr-1.5"></i>
                    <span>Simulasi Loloskan X-Ray (CLEARED)</span>
                </button>
                <button onclick="executeStageAction(4, 'SUSPECT')" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white font-bold text-xs shadow-md shadow-rose-500/20 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
                    <i class="fa-solid fa-triangle-exclamation mr-1.5"></i>
                    <span>Simulasi Temuan Bahaya (SUSPECT)</span>
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
    let btnText = `Jalankan Tahap ${stage}`;
    let btnIcon = 'fa-play';
    if (stage === 1) { btnText = 'Daftarkan Truk di Dock (TMS)'; btnIcon = 'fa-truck'; }
    else if (stage === 2) { btnText = 'Pindai Tag RFID & Ingest JSON'; btnIcon = 'fa-code'; }
    else if (stage === 3) { btnText = 'Verifikasi e-AWB di CMS'; btnIcon = 'fa-laptop-code'; }
    else if (stage === 5) { btnText = 'Timbang di Smart Floor Scale'; btnIcon = 'fa-scale-balanced'; }
    else if (stage === 6) { btnText = 'Alokasikan ke ULD & Cek CoG'; btnIcon = 'fa-boxes-packing'; }
    else if (stage === 7) { btnText = 'Finalisasi Manifes & Load Pesawat'; btnIcon = 'fa-plane-departure'; }

    const disabledAttr = (stage > currentCargoStage + 1 && c.status !== 'SUSPECT') ? 'disabled style="opacity:0.45;cursor:not-allowed;"' : '';

    container.innerHTML = `
        <button onclick="executeStageAction(${stage}, 'CLEARED')" ${disabledAttr} class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5 cursor-pointer">
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

            let iconType = 'success';
            let btnColor = '#087F8A';
            if (avsecDecision === 'SUSPECT') {
                iconType = 'warning';
                btnColor = '#e11d48';
            } else if (avsecDecision === 'RETURN_TO_TRUCK') {
                iconType = 'info';
                btnColor = '#0f172a';
            }

            Swal.fire({
                title: 'Tahap Berhasil Diproses!',
                text: res.message,
                icon: iconType,
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: btnColor,
                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
            }).then(() => {
                let nextView = stage;
                if ((avsecDecision === 'CLEARED' || avsecDecision === 'RELEASE') && stage < 7) {
                    nextView = stage + 1;
                } else if (avsecDecision === 'RETURN_TO_TRUCK') {
                    nextView = 1;
                }
                initSimulation(currentCargoData.id, nextView, currentSystemDashboard);
            });
        } else {
            Swal.fire({
                title: 'Peringatan Operasional!',
                text: res.message,
                icon: 'error',
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: '#087F8A',
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
                    Swal.fire({
                        title: 'Simulasi Lengkap Berhasil!',
                        text: 'Semua 7 tahapan kargo telah disimulasikan hingga status LOADED dan manifes penerbangan GA-707 ditutup.',
                        icon: 'success',
                        background: '#ffffff',
                        color: '#0D1C42',
                        confirmButtonColor: '#087F8A',
                        customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
                    }).then(() => {
                        initSimulation(currentCargoData.id, null, currentSystemDashboard);
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

    // Default payload matching stage
    if (stage === 1) {
        currentRequestJson = {
            "event": "TRUCK_SLOT_BOOKING_INGEST",
            "booking_code": c.booking_code || "TAS-SLOT-9482",
            "truck": {
                "plate_number": c.truck_plate || "B 9482 KXT",
                "driver": c.driver_name || "Bambang Supriyanto",
                "transporter": "PT Indotruck Multimoda Logistik"
            },
            "allocated_dock": c.dock_slot || "DOCK-04",
            "reference_awb": c.awb_number,
            "eta_timestamp": new Date().toISOString()
        };
        currentResponseJson = {
            "status": "200_OK",
            "message": "Slot dock terkonfirmasi dan reservasi aktif di gerbang bandara",
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
        const declared = parseFloat(c.declared_weight_kg);
        const actual = c.actual_weight_kg ? parseFloat(c.actual_weight_kg) : (declared + 2.0);
        currentRequestJson = {
            "event_type": "CARGO_WEIGHT_ACQUISITION",
            "device_id": "SCALE-FLOOR-ULD-01",
            "device_specs": {
                "platform": "3.5m x 2.5m Steel Floor Scale",
                "max_capacity_ton": 15,
                "accuracy_kg": 1.0,
                "sensor_protection": "IP68 Hermetic"
            },
            "weight_data": {
                "cargo_id": c.id,
                "declared_gross_weight_kg": declared,
                "actual_scale_weight_kg": actual,
                "discrepancy_kg": actual - declared,
                "status": "WITHIN_TOLERANCE"
            },
            "weighed_at": new Date().toISOString()
        };
        currentResponseJson = {
            "status": "200_OK",
            "message": "Data berat aktual berhasil disinkronkan ke CMS & Modul Weight & Balance",
            "allow_uld_buildup": true
        };
    } else if (stage === 6) {
        currentRequestJson = {
            "event_type": "ULD_CONSOLIDATION_AND_LOAD_PLANNING",
            "cargo_id": c.id,
            "target_uld": {
                "uld_code": c.uld_code || "AKE-12345-GA",
                "uld_type": "AKE",
                "tare_weight_kg": 75.0,
                "cargo_weight_added_kg": parseFloat(c.actual_weight_kg || c.declared_weight_kg),
                "new_gross_uld_weight_kg": 850.0,
                "max_capacity_kg": 1588.0,
                "utilization_percent": 53.5
            },
            "weight_and_balance_engine": {
                "aircraft": "Boeing 737-800F",
                "assigned_compartment": "AFT-LOWER-1",
                "center_of_gravity_index": 28.4,
                "within_safety_envelope": true,
                "floor_load_limit_status": "WITHIN_STRUCTURAL_LIMIT"
            },
            "timestamp": new Date().toISOString()
        };
        currentResponseJson = {
            "status": "200_OK",
            "message": "Kargo berhasil dikonsolidasikan ke dalam kontainer ULD AKE-12345-GA",
            "load_position_locked": true
        };
    } else if (stage === 7) {
        currentRequestJson = {
            "event_type": "FLIGHT_MANIFEST_FINALIZATION",
            "flight_manifest_id": "FL-GA707-2026",
            "flight_number": "GA-707",
            "loadsheet_number": "LS-GA707-20260926",
            "aircraft_type": "Boeing 737-800F",
            "route": "CGK -> DPS",
            "loaded_uld_list": [
                {
                    "uld_code": "AKE-12345-GA",
                    "final_weight_kg": 850.0,
                    "contains_cargo_awb": c.awb_number,
                    "ramp_loading_status": "TRANSFERRED_TO_AIRCRAFT_BELLY"
                }
            ],
            "total_cargo_payload_kg": 1250.0,
            "departure_clearance": "APPROVED",
            "timestamp": new Date().toISOString()
        };
        currentResponseJson = {
            "status": "200_OK",
            "message": "Manifes penerbangan final telah diterbitkan. Loadsheet resmi terkirim ke kokpit & flight ops.",
            "aircraft_ramp_status": "READY_FOR_PUSHBACK"
        };
    }

    displayActivePayload();
}

function switchPayloadTab(tab) {
    activePayloadTab = tab;
    const reqBtn = document.getElementById('tabBtnRequest');
    const resBtn = document.getElementById('tabBtnResponse');

    if (tab === 'request') {
        reqBtn.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-bold bg-[#087F8A] text-white shadow-sm transition-all flex items-center justify-center';
        resBtn.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:text-slate-800 border border-gray-200 transition-all flex items-center justify-center';
    } else {
        resBtn.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-bold bg-[#087F8A] text-white shadow-sm transition-all flex items-center justify-center';
        reqBtn.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:text-slate-800 border border-gray-200 transition-all flex items-center justify-center';
    }

    displayActivePayload();
}

function displayActivePayload() {
    const display = document.getElementById('jsonPayloadDisplay');
    const targetObj = (activePayloadTab === 'request') ? currentRequestJson : currentResponseJson;
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

    // Refresh data in specialized views
    if (currentCargoData) {
        renderTMSDashboard(currentCargoData);
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

function generateRandomTASBooking() {
    const randomNum = Math.floor(1000 + Math.random() * 9000);
    const code = `TAS-BOOK-${randomNum}`;
    const el = document.getElementById('input-tms-booking');
    if (el) el.value = code;
}

// State okupansi operasional 4 dock terminal kargo inbound (TMS live state)
const defaultDockLiveState = {
    'DOCK-01': {
        occupied: true,
        plate: 'B 9182 KXA',
        driver: 'Agus Setiawan',
        transporter: 'PT Multi Lintas Express',
        status: 'Sedang Bongkar Pallet',
        timeRemaining: '12 mnt'
    },
    'DOCK-02': {
        occupied: false,
        plate: null,
        driver: null,
        transporter: null,
        status: 'Standby / Siap Digunakan',
        timeRemaining: null
    },
    'DOCK-03': {
        occupied: true,
        plate: 'B 4412 PQL',
        driver: 'Hendra Wijaya',
        transporter: 'PT Cipta Sarana Logistik',
        status: 'Sedang Bongkar Pallet',
        timeRemaining: '25 mnt'
    },
    'DOCK-04': {
        occupied: false,
        plate: null,
        driver: null,
        transporter: null,
        status: 'Standby / Siap Digunakan',
        timeRemaining: null
    }
};

let dockLiveState = JSON.parse(JSON.stringify(defaultDockLiveState));

function renderDockCards(selectedSlot = null, customPlate = null) {
    const dockSelect = document.getElementById('input-tms-dock');
    if (!selectedSlot) {
        selectedSlot = dockSelect ? dockSelect.value : 'DOCK-02';
    }
    const currentPlate = customPlate || document.getElementById('input-tms-plate')?.value.trim() || 'B 9482 XX';
    const docks = ['DOCK-01', 'DOCK-02', 'DOCK-03', 'DOCK-04'];

    let occupiedCount = 0;
    let availableCount = 0;

    docks.forEach(d => {
        const info = dockLiveState[d];
        const card = document.getElementById(`dock-card-${d}`);
        const icon = document.getElementById(`dock-icon-${d}`);
        const plate = document.getElementById(`dock-plate-${d}`);
        const status = document.getElementById(`dock-status-${d}`);
        const badge = document.getElementById(`dock-badge-${d}`);
        if (!card) return;

        const titleSpan = card.querySelector('div > span:first-child') || card.querySelector('span:first-child');

        // KASUS 1: Dock yang saat ini dipilih oleh staff untuk kargo ini
        if (d === selectedSlot) {
            card.className = 'p-3.5 rounded-xl border-2 border-[#0CA1AF] bg-teal-50/70 text-center transition-all shadow-md ring-2 ring-teal-400/30 cursor-pointer relative overflow-hidden group select-none';
            card.title = 'Alokasi dock terpilih kargo saat ini';
            if (titleSpan) titleSpan.className = 'text-[10px] font-bold text-[#087F8A] uppercase font-mono';
            if (badge) {
                badge.className = 'text-[8.5px] px-1.5 py-0.5 rounded font-extrabold uppercase bg-teal-100 text-[#087F8A] border border-teal-300';
                badge.textContent = 'TERPILIH';
            }
            if (icon) {
                icon.className = 'my-2 text-2xl text-[#087F8A] group-hover:scale-105 transition-transform animate-pulse';
                icon.innerHTML = '<i class="fa-solid fa-truck"></i>';
            }
            if (plate) {
                plate.className = 'text-xs font-black text-[#0D1C42] block font-mono truncate';
                plate.textContent = currentPlate || 'B 9482 XX';
            }
            if (status) {
                status.className = 'text-[9px] text-teal-800 font-bold block mt-0.5';
                status.innerHTML = '<i class="fa-solid fa-circle-check mr-1 text-emerald-600"></i>Alokasi Bongkar Anda';
            }
            availableCount++;
        }
        // KASUS 2: Dock sedang penuh terisi oleh armada lain
        else if (info.occupied) {
            occupiedCount++;
            card.className = 'p-3.5 rounded-xl border border-rose-300 bg-rose-50/60 text-center transition-all cursor-pointer hover:border-rose-400 hover:shadow-md relative overflow-hidden group select-none';
            card.title = `PENUH - Digunakan oleh ${info.plate}. Klik untuk lihat rincian.`;
            if (titleSpan) titleSpan.className = 'text-[10px] font-bold text-rose-800 uppercase font-mono';
            if (badge) {
                badge.className = 'text-[8.5px] px-1.5 py-0.5 rounded font-extrabold uppercase bg-rose-200 text-rose-800 border border-rose-300';
                badge.textContent = 'PENUH';
            }
            if (icon) {
                icon.className = 'my-2 text-2xl text-rose-500 group-hover:scale-105 transition-transform';
                icon.innerHTML = '<i class="fa-solid fa-truck-ramp-box"></i>';
            }
            if (plate) {
                plate.className = 'text-xs font-bold text-rose-950 block font-mono truncate';
                plate.textContent = info.plate;
            }
            if (status) {
                status.className = 'text-[9px] text-rose-700 font-bold block mt-0.5';
                status.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i>Sedang Bongkar';
            }
        }
        // KASUS 3: Dock kosong dan standby, siap diklik untuk dipilih
        else {
            availableCount++;
            card.className = 'p-3.5 rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50/20 text-center transition-all cursor-pointer hover:border-emerald-500 hover:bg-emerald-50 hover:shadow-md relative overflow-hidden group select-none';
            card.title = 'Dock KOSONG - Klik langsung untuk memilih dock ini';
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
        }
    });

    // Update badges dan stat summary
    const occBadge = document.getElementById('tms-dock-occupied-badge');
    if (occBadge) occBadge.textContent = `${occupiedCount} Penuh`;
    const availBadge = document.getElementById('tms-dock-available-badge');
    if (availBadge) availBadge.textContent = `${availableCount} Kosong`;
    const sumStat = document.getElementById('tms-dock-summary-stat');
    if (sumStat) sumStat.innerHTML = `${occupiedCount}/4 Terisi <span class="text-xs text-slate-500 font-sans font-normal">${availableCount} Kosong</span>`;

    // Sinkronkan pilihan dropdown
    if (dockSelect && dockSelect.value !== selectedSlot) {
        dockSelect.value = selectedSlot;
    }
}

function handleDockCardClick(dockSlot) {
    const dockInfo = dockLiveState[dockSlot];
    const currentSelected = document.getElementById('input-tms-dock')?.value;

    // Jika dock sedang penuh terisi armada lain
    if (dockInfo && dockInfo.occupied && dockSlot !== currentSelected) {
        Swal.fire({
            title: `${dockSlot} SEDANG PENUH!`,
            html: `
                <div class="text-left text-xs p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-slate-700 space-y-2 mt-2">
                    <div class="flex items-center justify-between pb-1.5 border-b border-rose-200">
                        <span class="text-rose-700 font-extrabold uppercase text-[10px]">Status Operasional Dock</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-200 text-rose-800">PENUH / OCCUPIED</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Truk di Dock:</span>
                        <strong class="font-mono text-rose-900">${dockInfo.plate}</strong>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Supir Terdaftar:</span>
                        <span class="font-semibold text-slate-800">${dockInfo.driver || '-'}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Vendor Ekspedisi:</span>
                        <span class="text-slate-700">${dockInfo.transporter || '-'}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Aktivitas Saat Ini:</span>
                        <span class="font-semibold text-rose-800">${dockInfo.status}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Estimasi Selesai:</span>
                        <span class="font-mono font-bold text-emerald-700">±${dockInfo.timeRemaining || '15 mnt'}</span>
                    </div>
                </div>
                <div class="mt-3 p-2.5 rounded-xl bg-teal-50 border border-teal-200 text-xs text-left">
                    <span class="font-bold text-[#087F8A] block mb-1"><i class="fa-solid fa-lightbulb mr-1"></i> Rekomendasi Alokasi:</span>
                    <p class="text-slate-600 text-[11px]">Silakan klik dock bertanda <strong class="text-emerald-700">KOSONG</strong> (misalnya <strong>DOCK 02</strong> atau <strong>DOCK 04</strong>) yang memiliki hidrolik siap bongkar.</p>
                </div>
            `,
            icon: 'warning',
            background: '#ffffff',
            color: '#0D1C42',
            confirmButtonColor: '#087F8A',
            confirmButtonText: 'Pilih Dock yang Kosong',
            customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
        });
        return;
    }

    if (dockSlot === currentSelected) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            title: `${dockSlot} Sedang Terpilih`,
            text: 'Ini adalah dock yang saat ini dialokasikan untuk kargo Anda.',
            icon: 'info',
            showConfirmButton: false,
            timer: 1500,
            background: '#ffffff',
            color: '#0D1C42',
            customClass: { popup: 'rounded-xl shadow-lg border border-gray-200' }
        });
        return;
    }

    // Dock kosong berhasil dipilih langsung dari kartu!
    const dockSelect = document.getElementById('input-tms-dock');
    if (dockSelect) {
        dockSelect.value = dockSlot;
    }

    renderDockCards(dockSlot);
    updateTMSJsonPayload(dockSlot);

    Swal.fire({
        toast: true,
        position: 'top-end',
        title: `${dockSlot} Berhasil Dipilih!`,
        text: `Alokasi bongkar kargo dialihkan ke ${dockSlot}`,
        icon: 'success',
        showConfirmButton: false,
        timer: 1800,
        background: '#ffffff',
        color: '#0D1C42',
        customClass: { popup: 'rounded-xl shadow-lg border border-gray-200' }
    });
}

function handleDockSelectChange(dockSlot) {
    if (dockLiveState[dockSlot] && dockLiveState[dockSlot].occupied) {
        Swal.fire({
            title: `${dockSlot} Sedang Penuh!`,
            text: `Dock ini sedang digunakan oleh armada ${dockLiveState[dockSlot].plate}. Silakan pilih dock yang bertanda KOSONG.`,
            icon: 'warning',
            confirmButtonColor: '#087F8A'
        });
        const firstAvailable = Object.keys(dockLiveState).find(k => !dockLiveState[k].occupied) || 'DOCK-02';
        document.getElementById('input-tms-dock').value = firstAvailable;
        renderDockCards(firstAvailable);
        return;
    }
    renderDockCards(dockSlot);
    updateTMSJsonPayload(dockSlot);
}

function handlePlateInput(plateValue) {
    const dockSelect = document.getElementById('input-tms-dock');
    const selectedSlot = dockSelect ? dockSelect.value : 'DOCK-02';
    renderDockCards(selectedSlot, plateValue);
    updateTMSJsonPayload(selectedSlot, plateValue);
}

function updateTMSJsonPayload(assignedDock = null, customPlate = null) {
    if (!currentCargoData) return;
    const plateInput = document.getElementById('input-tms-plate');
    const driverInput = document.getElementById('input-tms-driver');
    const transInput = document.getElementById('input-tms-transporter');
    const dockInput = document.getElementById('input-tms-dock');
    const bookingInput = document.getElementById('input-tms-booking');

    const activeDock = assignedDock || (dockInput ? dockInput.value : 'DOCK-02');
    const activePlate = customPlate || (plateInput ? plateInput.value : currentCargoData.truck_plate) || 'B 9482 XX';

    const tmsJson = {
        "event_type": "TMS_TRUCK_GATE_CHECKIN",
        "truck_id": currentCargoData.truck_arrival_id || "TRK-INBOUND-01",
        "truck_plate": activePlate,
        "driver_name": (driverInput ? driverInput.value : currentCargoData.driver_name) || "Suryadi Pratama",
        "transporter": (transInput ? transInput.value : currentCargoData.transporter_company) || "PT Intermodal Logistik Sejahtera",
        "assigned_dock": activeDock,
        "booking_code": (bookingInput ? bookingInput.value : currentCargoData.booking_code) || "TAS-BOOK-9482",
        "tas_booking_status": "CONFIRMED_VALID_TIMESLOT",
        "gate_barrier_status": "BARRIER_OPENED",
        "dock_allocation_check": {
            "selected_dock": activeDock,
            "dock_status": "AVAILABLE_ASSIGNED",
            "full_docks": ["DOCK-01", "DOCK-03"],
            "vacant_docks": ["DOCK-02", "DOCK-04"]
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

// Kompatibilitas fungsi lama
function highlightSelectedDock(dockSlot, customPlate = null) {
    renderDockCards(dockSlot, customPlate);
}

// Pilih Preset Truk Cepat (Menghasilkan kargo dan JSON berbeda secara otomatis)
function selectTruckPreset(plate) {
    const presets = {
        'B 8888 KGO': {
            driver: 'Bambang Pamungkas',
            transporter: 'PT Angkasa Pura Logistik',
            dock: 'DOCK-02',
            booking: 'TAS-SLOT-8821',
            desc: 'Tekstil & Garmen Ekspor (50 koli, 350 kg)'
        },
        'B 9182 KXA': {
            driver: 'Ahmad Dahlan',
            transporter: 'PT Bio Farma Express',
            dock: 'DOCK-02',
            booking: 'TAS-SLOT-9182',
            desc: 'Farmasi & Vaksin (30 koli, 185 kg)'
        },
        'B 4412 PQL': {
            driver: 'Hendra Gunawan',
            transporter: 'PT Sat Nusapersada Cargo',
            dock: 'DOCK-04',
            booking: 'TAS-SLOT-4412',
            desc: 'Elektronik & Semikonduktor (60 koli, 420 kg)'
        },
        'B 7777 WXY': {
            driver: 'Made Suardana',
            transporter: 'PT Bali Fresh Logistik',
            dock: 'DOCK-04',
            booking: 'TAS-SLOT-7777',
            desc: 'Buah Segar Perishable (45 koli, 260 kg)'
        }
    };
    const p = presets[plate] || {
        driver: 'Suryadi Pratama',
        transporter: 'PT Intermodal Logistik Sejahtera',
        dock: 'DOCK-02',
        booking: 'TAS-SLOT-' + Math.floor(1000 + Math.random() * 9000),
        desc: 'Muatan Kargo Terjadwal'
    };

    const plateInput = document.getElementById('input-tms-plate');
    const driverInput = document.getElementById('input-tms-driver');
    const transInput = document.getElementById('input-tms-transporter');
    const dockInput = document.getElementById('input-tms-dock');
    const bookingInput = document.getElementById('input-tms-booking');

    if (plateInput) plateInput.value = plate;
    if (driverInput) driverInput.value = p.driver;
    if (transInput) transInput.value = p.transporter;
    if (dockInput) dockInput.value = p.dock;
    if (bookingInput) bookingInput.value = p.booking;

    renderDockCards(p.dock, plate);
    updateTMSJsonPayload(p.dock, plate);

    Swal.fire({
        toast: true,
        position: 'top-end',
        title: `Armada ${plate} Dipilih!`,
        text: `${p.desc} | ${p.transporter}`,
        icon: 'info',
        showConfirmButton: false,
        timer: 1800,
        background: '#ffffff',
        color: '#0D1C42',
        customClass: { popup: 'rounded-xl shadow-lg border border-gray-200' }
    });
}

function presetTMSTruckData() {
    selectTruckPreset('B 8888 KGO');
}

function submitTMSCheckIn() {
    if (!currentCargoData) {
        Swal.fire({
            title: 'Kargo Tidak Ditemukan',
            text: 'Data kargo aktif belum dimuat. Silakan muat ulang halaman.',
            icon: 'error',
            confirmButtonColor: '#087F8A'
        });
        return;
    }

    const plateInput = document.getElementById('input-tms-plate');
    const driverInput = document.getElementById('input-tms-driver');
    const transInput = document.getElementById('input-tms-transporter');
    const dockInput = document.getElementById('input-tms-dock');
    const bookingInput = document.getElementById('input-tms-booking');
    const statusInput = document.getElementById('input-tms-status');

    const truckPlate = plateInput ? plateInput.value.trim().toUpperCase() : 'B 8888 KGO';
    const driverName = driverInput ? driverInput.value.trim() : 'Bambang Pamungkas';
    const transporter = transInput ? transInput.value.trim() : 'PT Angkasa Pura Logistik';
    const dockSlot = dockInput ? dockInput.value : 'DOCK-02';
    const bookingCode = bookingInput ? bookingInput.value.trim() : ('TAS-BOOK-' + Math.floor(1000 + Math.random() * 9000));
    const truckStatus = statusInput ? statusInput.value : 'ARRIVED';

    if (!truckPlate) {
        Swal.fire({
            title: 'Nomor Plat Wajib Diisi',
            text: 'Silakan masukkan nomor plat truk kargo sebelum verifikasi.',
            icon: 'warning',
            confirmButtonColor: '#087F8A'
        });
        if (plateInput) plateInput.focus();
        return;
    }

    if (!driverName) {
        Swal.fire({
            title: 'Nama Supir Wajib Diisi',
            text: 'Silakan masukkan nama supir kargo sebelum verifikasi.',
            icon: 'warning',
            confirmButtonColor: '#087F8A'
        });
        if (driverInput) driverInput.focus();
        return;
    }

    // Validasi apakah dock slot yang dipilih sedang penuh oleh armada lain
    if (dockLiveState[dockSlot] && dockLiveState[dockSlot].occupied && dockLiveState[dockSlot].plate !== truckPlate) {
        Swal.fire({
            title: `${dockSlot} Sedang Penuh!`,
            text: `Alokasi gagal: ${dockSlot} sedang digunakan oleh armada ${dockLiveState[dockSlot].plate}. Silakan pilih dock yang kosong (DOCK-02 atau DOCK-04).`,
            icon: 'error',
            confirmButtonColor: '#087F8A'
        });
        return;
    }

    Swal.fire({
        title: 'Verifikasi Truk di Gate TMS...',
        text: `Memverifikasi kedatangan armada ${truckPlate} di ${dockSlot}...`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#ffffff',
        color: '#0D1C42',
        customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
    });

    const truckPayload = {
        truck_plate: truckPlate,
        driver_name: driverName,
        transporter_company: transporter,
        dock_slot: dockSlot,
        booking_code: bookingCode,
        status: truckStatus
    };

    fetch('api/simulate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            cargo_id: currentCargoData.id,
            stage: 1,
            action: 'advance',
            avsec_decision: 'CLEARED',
            truck_data: truckPayload
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const truck = res.truck || truckPayload;

            // Masukkan data baru ke baris pertama tabel antrean truk secara real-time
            const tbody = document.getElementById('tmsTruckArrivalsTbody');
            if (tbody) {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50/70 transition-colors bg-emerald-50/60 font-medium animate-pulse';
                tr.innerHTML = `
                    <td class="py-2.5 px-3 font-mono font-bold text-[#0D1C42]">${escapeHtml(truck.truck_plate)}</td>
                    <td class="py-2.5 px-3 font-medium text-slate-800">${escapeHtml(truck.driver_name)}</td>
                    <td class="py-2.5 px-3 text-slate-500">${escapeHtml(truck.transporter_company)}</td>
                    <td class="py-2.5 px-3 font-mono font-bold text-[#087F8A]">${escapeHtml(truck.dock_slot)}</td>
                    <td class="py-2.5 px-3 font-mono text-[11px] text-slate-500">${escapeHtml(truck.booking_code)}</td>
                    <td class="py-2.5 px-3">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                            ${escapeHtml(truck.status)}
                        </span>
                    </td>
                `;
                tbody.insertBefore(tr, tbody.firstChild);

                setTimeout(() => {
                    tr.classList.remove('bg-emerald-50/60', 'animate-pulse');
                }, 3000);
            }

            // Update badge total armada
            const countBadge = document.getElementById('tms-truck-count-badge');
            if (countBadge && tbody) {
                const totalRows = tbody.querySelectorAll('tr').length;
                countBadge.textContent = `${totalRows} Armada`;
            }

            // Tandai dock terpilih sekarang aktif digunakan oleh truk ini
            if (dockLiveState[dockSlot]) {
                dockLiveState[dockSlot].occupied = true;
                dockLiveState[dockSlot].plate = truckPlate;
                dockLiveState[dockSlot].driver = driverName;
                dockLiveState[dockSlot].transporter = transporter;
                dockLiveState[dockSlot].status = 'Sedang Bongkar (Baru Tiba)';
                dockLiveState[dockSlot].timeRemaining = '30 mnt';
            }

            // Re-render dock cards
            renderDockCards(dockSlot, truckPlate);

            // Update local state currentCargoData dengan data muatan spesifik truk dari backend
            if (res.cargo) {
                currentCargoData = res.cargo;
                renderCargoHeader(currentCargoData);
            } else {
                currentCargoData.current_stage = 1;
                currentCargoData.status = 'REGISTERED';
                currentCargoData.truck_plate = truckPlate;
                currentCargoData.driver_name = driverName;
                currentCargoData.transporter_company = transporter;
                currentCargoData.dock_slot = dockSlot;
                currentCargoData.booking_code = bookingCode;
                if (truck.id) {
                    currentCargoData.truck_arrival_id = truck.id;
                }
            }

            // Update JSON Inspector di kolom kanan
            updateTMSJsonPayload(dockSlot, truckPlate);

            const stageBadge = document.getElementById('tms-cargo-stage-badge');
            if (stageBadge) stageBadge.textContent = `Tahap 1: REGISTERED`;

            Swal.fire({
                title: 'Truk Berhasil Diverifikasi di TMS!',
                html: `
                    <div class="text-xs text-left p-3.5 rounded-xl bg-slate-50 border border-gray-200 space-y-1.5 mt-2">
                        <div class="flex justify-between items-center pb-1.5 border-b border-gray-200">
                            <span class="text-slate-500 font-bold uppercase text-[10px]">Armada &amp; Dock</span>
                            <span class="text-[#0D1C42] font-mono font-black text-sm">${escapeHtml(truckPlate)} (${escapeHtml(dockSlot)})</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-500">Nama Supir:</span>
                            <span class="font-bold text-slate-800">${escapeHtml(driverName)}</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-500">Vendor Logistik:</span>
                            <span class="text-slate-700">${escapeHtml(transporter)}</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px] pt-1 border-t border-dashed border-gray-200">
                            <span class="text-[#087F8A] font-bold">e-AWB Baru:</span>
                            <span class="font-mono font-black text-[#0D1C42]">${escapeHtml(currentCargoData.awb_number || '')}</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-500">Muatan Kargo:</span>
                            <span class="font-bold text-emerald-700 truncate max-w-[200px]">${escapeHtml(currentCargoData.description || '')} (${escapeHtml(currentCargoData.quantity || '0')} Koli)</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-500">Kunci GS1 SSCC:</span>
                            <span class="font-mono text-[#087F8A] font-bold text-[10.5px]">${escapeHtml(currentCargoData.sscc || '')}</span>
                        </div>
                    </div>
                    <p class="text-xs text-emerald-700 font-bold mt-3">
                        <i class="fa-solid fa-circle-check mr-1 text-emerald-600"></i>
                        Data kargo telah disinkronkan! Sistem siap meng-generate stream JSON spesifik di CMS (Tahap 2).
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
                // Refresh stepper and timeline
                initSimulation(currentCargoData.id, 1, result.isConfirmed ? 'cms' : 'tms');
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
            text: 'Gagal memproses kedatangan truk: ' + err,
            icon: 'error',
            background: '#ffffff',
            color: '#0D1C42',
            confirmButtonColor: '#087F8A'
        });
    });
}

function renderTMSDashboard(c) {
    if (!c) return;

    // Sinkronisasi data kargo ke input jika tersedia
    const plateInput = document.getElementById('input-tms-plate');
    if (plateInput && c.truck_plate) {
        plateInput.value = c.truck_plate;
    }
    const driverInput = document.getElementById('input-tms-driver');
    if (driverInput && c.driver_name) {
        driverInput.value = c.driver_name;
    }
    const transInput = document.getElementById('input-tms-transporter');
    if (transInput && c.transporter_company) {
        transInput.value = c.transporter_company;
    }
    const dockInput = document.getElementById('input-tms-dock');
    if (dockInput) {
        // Jika dock kargo kosong atau sudah teralokasi, jika kargo punya dock sendiri gunakan, jika tidak default ke dock kosong
        let targetSlot = c.dock_slot || 'DOCK-02';
        if (dockLiveState[targetSlot] && dockLiveState[targetSlot].occupied && dockLiveState[targetSlot].plate !== c.truck_plate) {
            targetSlot = 'DOCK-02';
        }
        dockInput.value = targetSlot;
    }
    const bookingInput = document.getElementById('input-tms-booking');
    if (bookingInput && c.booking_code) {
        bookingInput.value = c.booking_code;
    }

    const stageBadge = document.getElementById('tms-cargo-stage-badge');
    if (stageBadge) stageBadge.textContent = `Tahap ${c.current_stage}: ${c.status}`;

    const activeDock = (dockInput ? dockInput.value : c.dock_slot) || 'DOCK-02';
    const activePlate = (plateInput ? plateInput.value : c.truck_plate) || 'B 9482 XX';
    renderDockCards(activeDock, activePlate);
    updateTMSJsonPayload(activeDock, activePlate);
}

function executeStage2Scan() {
    if (!currentCargoData) return;

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
            } else {
                currentCargoData.current_stage = 2;
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
                initSimulation(currentCargoData.id, 2, 'cms');
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

    // Proteksi: Kargo harus sudah melewati pindaian JSON di Tahap 2
    if (parseInt(currentCargoData.current_stage || 1) < 2) {
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
            confirmButtonText: 'Kirim Payload JSON Sekarang',
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
            } else {
                currentCargoData.current_stage = 3;
                currentCargoData.status = 'REGISTERED';
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
                        <td class="py-2.5 px-3 font-bold text-[#087F8A]">Tahap 3</td>
                        <td class="py-2.5 px-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-[#087F8A] border border-teal-200">
                                REGISTERED
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
                initSimulation(currentCargoData.id, 3, result.isConfirmed ? 'avsec' : 'cms');
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

    // KASUS 1: BARU TIBA DARI TMS, MUATAN TRUK SIAP DI-INGEST KE CMS (Stage < 2)
    if (stage < 2) {
        const readyPayload = {
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
    // KASUS 2: SUDAH INGEST DATA JSON, SIAP VALIDASI E-AWB (Stage == 2)
    else if (stage === 2) {
        const scanPayload = {
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
    // KASUS 3: SUDAH TAHAP 3 ATAU LEBIH (Stage >= 3)
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
        "json_ingest_status": (stage >= 2) ? "PAYLOAD_INGESTED_SUCCESS" : "READY_AWAITING_INGESTION",
        "gs1_sscc_18": (stage >= 2) ? c.sscc : null,
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
            "awb_matched": (stage >= 3),
            "registration_status": (stage >= 3) ? "REGISTERED_IN_CMS" : "AWAITING_VALIDATION",
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

    const isSuspect = (c.status === 'SUSPECT');
    const isReturned = (c.status === 'RETURNED_TO_TRUCK');
    const isCleared = (c.status === 'CLEARED' || (parseInt(c.current_stage || 1) >= 4 && !isSuspect && !isReturned));

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
            statusInd.textContent = 'STATUS: MENUNGGU SCREENING X-RAY';
            statusInd.className = 'text-[10px] font-mono px-3 py-1 rounded-full font-bold bg-amber-50 text-amber-700 border border-amber-200';
        }
        if (statusTag) {
            statusTag.className = "text-[10px] font-mono text-amber-300 bg-amber-950/60 px-2 py-0.5 rounded border border-amber-800/80";
            statusTag.textContent = "STANDBY - AWAITING X-RAY CONVEYOR";
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

    if (jsonDisplay) jsonDisplay.textContent = JSON.stringify(avsecJson, null, 2);
    const avsecDisp = document.getElementById('avsecPayloadDisplay');
    if (avsecDisp) avsecDisp.textContent = JSON.stringify(avsecJson, null, 2);
}

function renderLoadBalanceDashboard(c) {
    if (!c) return;
    const declaredWt = parseFloat(c.declared_weight_kg || 350.0);
    const actualWt = c.actual_weight_kg ? parseFloat(c.actual_weight_kg) : (parseInt(c.current_stage || 1) >= 5 ? declaredWt + 4.0 : 0.0);
    const displayWt = actualWt > 0 ? actualWt : declaredWt;
    const diff = displayWt - declaredWt;
    const diffPercent = ((diff / declaredWt) * 100).toFixed(2);
    const isWithinTolerance = Math.abs(diff / declaredWt) <= 0.05;

    const scaleWt = document.getElementById('lb-scale-weight');
    if (scaleWt) scaleWt.textContent = `${displayWt.toFixed(1)} KG`;
    const scaleDec = document.getElementById('lb-scale-declared');
    if (scaleDec) {
        const sign = diff >= 0 ? '+' : '';
        scaleDec.textContent = `${declaredWt.toFixed(1)} kg (${sign}${diff.toFixed(1)} kg / ${diffPercent}%)`;
    }
    const uldCode = document.getElementById('lb-uld-code');
    if (uldCode) uldCode.textContent = c.uld_code || 'AKE-12345-GA';

    const maxUldKg = 1588.0;
    const maxAircraftPayloadKg = 18500.0;
    const flightNum = c.flight_number || "GA-707";
    const aircraftType = "Boeing 737-800F";
    const isUldFit = displayWt <= maxUldKg;

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
            "tolerance_validation": isWithinTolerance ? "PASSED_WITHIN_TOLERANCE" : "EXCEEDED_TOLERANCE"
        },
        "uld_container_criteria": {
            "assigned_container": c.uld_code || "AKE-12345-GA",
            "container_type": "AKE (LD3 Contour)",
            "max_gross_weight_capacity_kg": maxUldKg,
            "container_tare_kg": 75.0,
            "container_payload_status": isUldFit ? "COMPLIANT_WITHIN_ULD_LIMIT" : "OVERLOAD_EXCEEDS_ULD_LIMIT",
            "assigned_compartment": c.compartment || "AFT-LOWER-1"
        },
        "aircraft_flight_criteria": {
            "aircraft_model": aircraftType,
            "flight_number": flightNum,
            "max_aircraft_payload_capacity_kg": maxAircraftPayloadKg,
            "cumulative_flight_payload_kg": parseFloat((5420.0 + displayWt).toFixed(1)),
            "flight_payload_fit": "SAFE_WITHIN_AIRCRAFT_CAPACITY",
            "center_of_gravity_mac": 28.4,
            "safe_cg_envelope": "18.0% - 34.0% MAC",
            "flight_trim_status": "OPTIMAL_SAFETY_TRIM",
            "electronic_loadsheet": parseInt(c.current_stage || 1) >= 7 ? "ISSUED_TO_EFB_COCKPIT" : "PENDING_FINAL_DISPATCH"
        }
    };

    const streamDisp = document.getElementById('lb-json-stream-display');
    if (streamDisp) streamDisp.textContent = JSON.stringify(lbJson, null, 2);
    const lbDisp = document.getElementById('lbPayloadDisplay');
    if (lbDisp) lbDisp.textContent = JSON.stringify(lbJson, null, 2);
}
