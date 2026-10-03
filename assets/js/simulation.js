// assets/js/simulation.js
// Logika Eksekusi Laboratorium Simulasi 7 Tahapan Kargo Udara (InJourney Airports Design System)

let currentCargoData = null;
let currentStageView = 1;
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

function initSimulation(cargoId) {
    if (!cargoId) return;

    fetch(`api/cargo.php?action=detail&id=${encodeURIComponent(cargoId)}`)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                currentCargoData = res.data.cargo;
                renderCargoHeader(currentCargoData);
                renderAuditTimeline(res.data.logs);
                updateStepperState(currentCargoData.current_stage, currentCargoData.status);
                selectStageView(currentCargoData.current_stage || 1);
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
    if (!c) return;

    const currentCargoStage = c.current_stage;
    container.innerHTML = '';

    // If stage 4 (AVSEC), provide 2 choices: Pass or Fail
    if (stage === 4) {
        container.innerHTML = `
            <button onclick="executeStageAction(4, 'CLEARED')" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-md shadow-emerald-500/20 transition-all flex items-center transform hover:-translate-y-0.5">
                <i class="fa-solid fa-shield-halved mr-1.5"></i>
                <span>Simulasi Loloskan X-Ray (CLEARED)</span>
            </button>
            <button onclick="executeStageAction(4, 'SUSPECT')" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white font-bold text-xs shadow-md shadow-rose-500/20 transition-all flex items-center transform hover:-translate-y-0.5">
                <i class="fa-solid fa-triangle-exclamation mr-1.5"></i>
                <span>Simulasi Temuan Bahaya (SUSPECT)</span>
            </button>
        `;
        return;
    }

    // Default button for other stages
    let btnText = `Jalankan Tahap ${stage}`;
    let btnIcon = 'fa-play';
    if (stage === 1) { btnText = 'Daftarkan Truk di Dock (TMS)'; btnIcon = 'fa-truck'; }
    else if (stage === 2) { btnText = 'Pindai Tag RFID & Baca SSCC'; btnIcon = 'fa-barcode'; }
    else if (stage === 3) { btnText = 'Verifikasi e-AWB di CMS'; btnIcon = 'fa-laptop-code'; }
    else if (stage === 5) { btnText = 'Timbang di Smart Floor Scale'; btnIcon = 'fa-scale-balanced'; }
    else if (stage === 6) { btnText = 'Alokasikan ke ULD & Cek CoG'; btnIcon = 'fa-boxes-packing'; }
    else if (stage === 7) { btnText = 'Finalisasi Manifes & Load Pesawat'; btnIcon = 'fa-plane-departure'; }

    const isCurrentOrNext = (stage === currentCargoStage) || (stage === currentCargoStage + 1);
    const disabledAttr = (stage > currentCargoStage + 1 && c.status !== 'SUSPECT') ? 'disabled opacity-50 cursor-not-allowed' : '';

    container.innerHTML = `
        <button onclick="executeStageAction(${stage}, 'CLEARED')" ${disabledAttr} class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5">
            <i class="fa-solid ${btnIcon} mr-2"></i>
            <span>${btnText}</span>
        </button>
        ${stage < 7 ? `
            <button onclick="selectStageView(${stage + 1})" class="px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-[#0D1C42] hover:text-[#087F8A] text-xs font-bold border border-gray-300 shadow-sm transition-colors flex items-center">
                <span>Lihat Tahap ${stage + 1}</span>
                <i class="fa-solid fa-arrow-right ml-1.5 text-xs"></i>
            </button>
        ` : ''}
    `;
}

function executeStageAction(stage, avsecDecision) {
    if (!currentCargoData) return;

    Swal.fire({
        title: `Memproses Tahap ${stage}...`,
        text: 'Mengirim JSON payload ke sistem...',
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
            action: (avsecDecision === 'SUSPECT') ? 'force_suspect' : 'advance',
            avsec_decision: avsecDecision
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire({
                title: 'Tahap Berhasil Diproses!',
                text: res.message,
                icon: (avsecDecision === 'SUSPECT') ? 'warning' : 'success',
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: (avsecDecision === 'SUSPECT') ? '#e11d48' : '#087F8A',
                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
            }).then(() => {
                initSimulation(currentCargoData.id);
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
                        initSimulation(currentCargoData.id);
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
