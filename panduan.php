<?php
// panduan.php
// Buku Panduan Sistem & Prompting Guide untuk Anggota Kelompok
require_once __DIR__ . '/config/database.php';
$user = getCurrentUser();

$pageTitle = 'Buku Panduan Sistem & Prompting Guide';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <!-- Top Action Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 mb-8 border-b border-slate-800 gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <span class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-amber-950 text-amber-300 border border-amber-700/60 uppercase">
                    Internal Team Guide
                </span>
                <span class="text-xs text-slate-400">&bull; Panduan Standar & Kolaborasi Tim</span>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight mt-1">Buku Panduan Sistem & Prompting AI Guide</h1>
            <p class="text-xs text-slate-400">Panduan terstruktur agar setiap anggota kelompok tetap selaras saat mengeksekusi prompt AI atau mengembangkan sistem.</p>
        </div>

        <div class="flex items-center space-x-2">
            <a href="simulation.php" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 transition-colors flex items-center">
                <i class="fa-solid fa-play mr-1.5 text-sky-400"></i>
                <span>Buka Simulator</span>
            </a>
            <button onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold border border-slate-700 transition-colors">
                <i class="fa-solid fa-print"></i>
            </button>
        </div>
    </div>

    <div class="space-y-8 text-xs sm:text-sm text-slate-300 leading-relaxed">
        
        <!-- Section 1: Ground Rules & Role Mindset -->
        <div class="glass-panel p-6 sm:p-8 rounded-2xl border border-slate-800">
            <div class="flex items-center space-x-3 mb-4 pb-3 border-b border-slate-800">
                <div class="w-10 h-10 rounded-xl bg-sky-950 text-sky-400 border border-sky-500/30 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <div>
                    <span class="text-[10px] text-sky-400 font-mono uppercase font-bold">Aturan Dasar #1</span>
                    <h2 class="text-base font-bold text-white">Mindset Proyek: Kita adalah KONSULTAN</h2>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-sky-950/40 border border-sky-500/30 text-sky-200 text-xs mb-4">
                <strong>PENTING DIPAHAMI SEMUA ANGGOTA:</strong><br>
                Proyek ini <strong>BUKAN</strong> untuk membuat aplikasi operasional nyata dari nol, melainkan kita bertindak sebagai <strong>Tim Konsultan Teknologi Logistik</strong> yang memberikan <strong>saran, rekomendasi arsitektur, dan blueprint alur kerja</strong> kepada pihak klien (Perusahaan Pengelola Terminal Kargo Bandara). Web ini berfungsi sebagai <strong>Alat Peraga / Media Simulasi Bukti Konsep (PoC)</strong> saat presentasi.
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-800">
                    <strong class="text-white block mb-1">1. Fokus Aliran Data</strong>
                    <span class="text-slate-400 text-[11px]">Menjelaskan bagaimana data berpindah antar Hardware (Layer 1) dan Software (Layer 3).</span>
                </div>
                <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-800">
                    <strong class="text-white block mb-1">2. Standar Global GS1</strong>
                    <span class="text-slate-400 text-[11px]">Wajib menggunakan kode SSCC 18-digit, GTIN, Batch, Expiry, dan format e-AWB IATA.</span>
                </div>
                <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-800">
                    <strong class="text-white block mb-1">3. Tech Stack Baku</strong>
                    <span class="text-slate-400 text-[11px]">PHP Native + MySQL (XAMPP localhost) + Tailwind CSS + Vanilla JS.</span>
                </div>
            </div>
        </div>

        <!-- Section 2: Copy-Paste Prompt Prefix Generator -->
        <div class="glass-panel p-6 sm:p-8 rounded-2xl border border-amber-500/30">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-950 text-amber-400 border border-amber-500/30 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div>
                        <span class="text-[10px] text-amber-400 font-mono uppercase font-bold">Wajib Digunakan</span>
                        <h2 class="text-base font-bold text-white">System Prompt Wajib untuk AI (Prompt Prefix)</h2>
                    </div>
                </div>
                <button onclick="copySystemPrompt()" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs flex items-center shadow-lg shadow-amber-500/20 transition-all">
                    <i class="fa-solid fa-copy mr-1.5"></i> Salin Prompt Ini
                </button>
            </div>

            <p class="text-xs text-slate-300 mb-3">
                Sebelum meminta AI (ChatGPT, Claude, Gemini, dll.) membuat dokumen, kode, atau materi apapun, <strong>kopikan teks di bawah ini terlebih dahulu</strong> agar AI tidak menyimpang dari alur yang sudah kita buat:
            </p>

            <div class="code-container font-mono text-[11px] text-amber-200 relative p-4 rounded-xl max-h-60 overflow-y-auto" id="systemPromptText">Halo AI, saya sedang mengerjakan Proyek Akhir Mata Kuliah "Teknologi dan Perangkat Lunak Logistik" di ITL Trisakti.
Topik Kelompok kami adalah Topik 6: "Air Cargo & Intermodal Terminal (Bandara)".
Dosen Pengampu: Dr. Tigor Franky, S.T., M.T.

PERAN KITA:
Kami adalah TIM KONSULTAN TEKNOLOGI LOGISTIK yang memberikan saran, rekomendasi arsitektur, dan blueprint integrasi kepada pihak KLIEN (Pengelola Terminal Kargo Bandara). Kami BUKAN membuat software komersial skala penuh, melainkan membuat sistem konsultansi dan prototipe simulasi Proof-of-Concept (PoC) aliran perpindahan data.

SPESIFIKASI SISTEM YANG SUDAH BERJALAN:
- Lokasi Web: c:\xampp\htdocs\Air_Cargo_Intermodal_Terminal\ (PHP 8.2 + MySQL air_cargo_db + Tailwind CSS)
- Arsitektur: 4-Layer IT Logistik (Hardware, Network, Software, Data Protocol GS1/SSCC)
- Alur Baku 7 Tahap:
  1. Kedatangan Truk & Booking Slot (TMS/TAS)
  2. Scan RFID/Barcode di Gerbang Masuk (High-Speed Scanner -> CMS)
  3. Registrasi Data & e-AWB Matching di CMS
  4. Pemeriksaan Keamanan X-Ray (AVSEC: CLEARED/SUSPECT & CSD)
  5. Penimbangan Kargo (Smart ULD Floor Scale 15T)
  6. Build-Up ULD & Kalkulasi Weight & Balance (Center of Gravity / CoG)
  7. Update Manifes Penerbangan & Pemuatan ke Pesawat (DEPARTED)
- Database MySQL: Tabel cargo_shipments, truck_arrivals, security_checks, weight_records, uld_containers, flight_manifests, scan_logs.

Tolong bantu saya untuk:
[TULISKAN PERMINTAAN SPESIFIK ANDA DI SINI]</div>
        </div>

        <!-- Section 3: Pembagian Tugas & Contoh Prompt Spesifik -->
        <div class="glass-panel p-6 sm:p-8 rounded-2xl border border-slate-800">
            <h2 class="text-base font-bold text-white mb-4 pb-3 border-b border-slate-800 flex items-center">
                <i class="fa-solid fa-users text-indigo-400 mr-2"></i>
                Pembagian Tugas Anggota & Template Prompt Khusus
            </h2>

            <div class="space-y-6">
                
                <!-- Peran 1 -->
                <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-sky-400 text-xs">Peran 1: Lead System Architect & Blueprint SRS</span>
                        <span class="text-[10px] font-mono text-slate-500">Raden Panji</span>
                    </div>
                    <p class="text-xs text-slate-400 mb-3">Tanggung Jawab: Merancang arsitektur integrasi utama, dokumen Blueprint SRS, dan analisis gap As-Is vs To-Be.</p>
                    <div class="bg-black/60 p-3 rounded-lg font-mono text-[11px] text-slate-300">
                        "Saya ingin menyusun Bab 2 SRS mengenai 'Analisis Gap Kondisi As-Is vs Rekomendasi To-Be Terminal Kargo Bandara'. Buatkan perbandingan mendalam untuk 4 aspek (antrean gate, entri manual, AVSEC, weight & balance) dalam format tabel dan narasi konsultan formal."
                    </div>
                </div>

                <!-- Peran 2 -->
                <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-indigo-400 text-xs">Peran 2: Data Integration Specialist</span>
                        <span class="text-[10px] font-mono text-slate-500">Muhammad Fathir</span>
                    </div>
                    <p class="text-xs text-slate-400 mb-3">Tanggung Jawab: Struktur payload JSON, integrasi API, pengujian simulator, dan validasi standar GS1 SSCC 18 digit.</p>
                    <div class="bg-black/60 p-3 rounded-lg font-mono text-[11px] text-slate-300">
                        "Saya ingin membuat variasi payload JSON untuk jenis komoditas khusus: 'Produk Segar Tuna Perishable' dan 'Kargo Farmasi Cold Chain'. Tunjukkan data AI (00), (01), (10), dan (17) yang cocok dikirim ke tabel scan_logs di MySQL."
                    </div>
                </div>

                <!-- Peran 3 -->
                <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-emerald-400 text-xs">Peran 3: Software & ERP Process Specialist</span>
                        <span class="text-[10px] font-mono text-slate-500">Riepka Tiara</span>
                    </div>
                    <p class="text-xs text-slate-400 mb-3">Tanggung Jawab: Flowchart proses bisnis CMS, validasi e-AWB, alur isolasi kargo SUSPECT di AVSEC, dan SOP kargo.</p>
                    <div class="bg-black/60 p-3 rounded-lg font-mono text-[11px] text-slate-300">
                        "Buatkan flowchart diagram alur proses penanganan kargo berstatus SUSPECT di AVSEC: bagaimana sistem secara otomatis memblokir kargo agar tidak dapat melanjutkan ke penimbangan ULD dan bagaimana alur notifikasinya ke CMS."
                    </div>
                </div>

                <!-- Peran 4 -->
                <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-amber-400 text-xs">Peran 4: Hardware & Business Analyst QA</span>
                        <span class="text-[10px] font-mono text-slate-500">Nessa Amanda</span>
                    </div>
                    <p class="text-xs text-slate-400 mb-3">Tanggung Jawab: Spesifikasi teknis perangkat keras lapangan, pengujian fungsional sistem, dan kalkulasi manfaat ROI untuk klien.</p>
                    <div class="bg-black/60 p-3 rounded-lg font-mono text-[11px] text-slate-300">
                        "Buatkan rincian spesifikasi teknis 3 perangkat keras utama (High-Speed Scanner, Dual-View X-Ray, Smart ULD Scale) beserta analisis kelayakan finansial: estimasi penurunan dwell time sebesar 81% dan penghematan biaya operasional terminal kargo."
                    </div>
                </div>

            </div>
        </div>

        <!-- Section 4: Alur Baku 7 Tahapan Kargo -->
        <div class="glass-panel p-6 sm:p-8 rounded-2xl border border-slate-800">
            <h2 class="text-base font-bold text-white mb-3 pb-3 border-b border-slate-800 flex items-center">
                <i class="fa-solid fa-arrows-turn-to-dots text-emerald-400 mr-2"></i>
                Tabel Acuan Baku: 7 Tahapan Perpindahan Data Kargo
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border border-slate-800 rounded-xl overflow-hidden">
                    <thead class="bg-slate-900 text-slate-300 font-semibold uppercase text-[10px]">
                        <tr>
                            <th class="py-2.5 px-3">#</th>
                            <th class="py-2.5 px-3">Nama Tahapan</th>
                            <th class="py-2.5 px-3">Hardware (Layer 1)</th>
                            <th class="py-2.5 px-3">Software (Layer 3)</th>
                            <th class="py-2.5 px-3">Data Kunci yang Berpindah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 text-[11px]">
                        <tr>
                            <td class="py-2.5 px-3 font-bold text-sky-400">1</td>
                            <td class="py-2.5 px-3 font-semibold text-white">Kedatangan Truk</td>
                            <td class="py-2.5 px-3 text-slate-400">Barrier Gate & RFID Reader</td>
                            <td class="py-2.5 px-3 text-slate-300">TMS / TAS</td>
                            <td class="py-2.5 px-3 text-slate-400">Plat truk, slot dock, booking code, ETA</td>
                        </tr>
                        <tr>
                            <td class="py-2.5 px-3 font-bold text-sky-400">2</td>
                            <td class="py-2.5 px-3 font-semibold text-white">Scan RFID Gate</td>
                            <td class="py-2.5 px-3 text-slate-400">High-Speed Scanner (2.5 m/s)</td>
                            <td class="py-2.5 px-3 text-slate-300">CMS Inbound Gate</td>
                            <td class="py-2.5 px-3 text-slate-400">GS1 SSCC (18 digit), GTIN, batch, expiry</td>
                        </tr>
                        <tr>
                            <td class="py-2.5 px-3 font-bold text-sky-400">3</td>
                            <td class="py-2.5 px-3 font-semibold text-white">Registrasi di CMS</td>
                            <td class="py-2.5 px-3 text-slate-400">CMS Core Server</td>
                            <td class="py-2.5 px-3 text-slate-300">Cargo Management Software</td>
                            <td class="py-2.5 px-3 text-slate-400">Nomor e-AWB, shipper, consignee, routing</td>
                        </tr>
                        <tr>
                            <td class="py-2.5 px-3 font-bold text-sky-400">4</td>
                            <td class="py-2.5 px-3 font-semibold text-white">X-Ray AVSEC</td>
                            <td class="py-2.5 px-3 text-slate-400">Dual-View X-Ray (180x180cm)</td>
                            <td class="py-2.5 px-3 text-slate-300">AVSEC System</td>
                            <td class="py-2.5 px-3 text-slate-400">Status CLEARED/SUSPECT, sertifikat CSD</td>
                        </tr>
                        <tr>
                            <td class="py-2.5 px-3 font-bold text-sky-400">5</td>
                            <td class="py-2.5 px-3 font-semibold text-white">Penimbangan</td>
                            <td class="py-2.5 px-3 text-slate-400">Smart ULD Floor Scale (15 Ton)</td>
                            <td class="py-2.5 px-3 text-slate-300">CMS & Weight Module</td>
                            <td class="py-2.5 px-3 text-slate-400">Berat aktual (kg), verifikasi toleransi e-AWB</td>
                        </tr>
                        <tr>
                            <td class="py-2.5 px-3 font-bold text-sky-400">6</td>
                            <td class="py-2.5 px-3 font-semibold text-white">Build-Up ULD</td>
                            <td class="py-2.5 px-3 text-slate-400">Workstation ULD Dock</td>
                            <td class="py-2.5 px-3 text-slate-300">Weight & Balance / CoG</td>
                            <td class="py-2.5 px-3 text-slate-400">Kode ULD (AKE/PMC), kompartemen, CoG</td>
                        </tr>
                        <tr>
                            <td class="py-2.5 px-3 font-bold text-sky-400">7</td>
                            <td class="py-2.5 px-3 font-semibold text-white">Loading Pesawat</td>
                            <td class="py-2.5 px-3 text-slate-400">Cargo High-Loader / EFB</td>
                            <td class="py-2.5 px-3 text-slate-300">CMS & Flight Ops</td>
                            <td class="py-2.5 px-3 text-slate-400">Electronic Loadsheet, status DEPARTED</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<script>
    function copySystemPrompt() {
        const text = document.getElementById('systemPromptText').innerText;
        navigator.clipboard.writeText(text).then(() => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                title: 'System Prompt berhasil disalin!',
                text: 'Silakan tempelkan di awal obrolan AI Anda.',
                icon: 'success',
                showConfirmButton: false,
                timer: 3000,
                background: '#111e38',
                color: '#fff'
            });
        });
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
