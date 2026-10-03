<?php
// panduan.php
header("Location: index.php");
exit;
require_once __DIR__ . '/config/database.php';
$user = getCurrentUser();

$pageTitle = 'Buku Panduan Sistem & Prompting Guide';
$bodyClass = 'bg-[#F8FAFC] text-slate-800';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    
    <!-- Top Action Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-gray-200 gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200 uppercase tracking-wider">
                    Internal Team Guide
                </span>
                <span class="text-xs text-slate-500 font-medium">&bull; Panduan Standar &amp; Kolaborasi Tim</span>
            </div>
            <h1 class="text-2xl lg:text-3xl font-extrabold text-[#0D1C42] tracking-tight mt-1.5">
                Buku Panduan Sistem &amp; Prompting AI Guide
            </h1>
            <p class="text-xs lg:text-sm text-slate-500 mt-1">
                Panduan terstruktur agar setiap anggota konsultan tetap selaras saat mengeksekusi prompt AI atau mempresentasikan sistem.
            </p>
        </div>

        <div class="flex items-center space-x-2.5">
            <a href="simulation.php" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-md shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5">
                <i class="fa-solid fa-play mr-2 text-xs"></i>
                <span>Buka Simulator</span>
            </a>
            <button onclick="window.print()" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-600 hover:text-[#087F8A] text-xs font-semibold border border-gray-300 shadow-sm transition-colors">
                <i class="fa-solid fa-print"></i>
            </button>
        </div>
    </div>

    <div class="space-y-8 text-xs sm:text-sm text-slate-700 leading-relaxed">
        
        <!-- Section 1: Ground Rules & Role Mindset -->
        <div class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-200/90 shadow-sm">
            <div class="flex items-center space-x-3 mb-4 pb-3 border-b border-gray-100">
                <div class="w-10 h-10 rounded-xl bg-teal-50 text-[#087F8A] border border-teal-200 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <div>
                    <span class="text-[10px] text-[#087F8A] font-mono uppercase font-bold tracking-wider">Aturan Dasar #1</span>
                    <h2 class="text-base font-bold text-[#0D1C42]">Mindset Proyek: Kita adalah KONSULTAN</h2>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-teal-50 border border-teal-200 text-[#014D54] text-xs mb-4">
                <strong class="text-[#0D1C42]">PENTING DIPAHAMI SEMUA ANGGOTA:</strong><br>
                Proyek ini <strong>BUKAN</strong> untuk membuat aplikasi operasional nyata dari nol, melainkan kita bertindak sebagai <strong>Tim Konsultan Teknologi Logistik (PT Aerospace Consultant)</strong> yang memberikan <strong>saran, rekomendasi arsitektur, dan blueprint alur kerja</strong> kepada pihak klien (Perusahaan Pengelola Terminal Kargo Bandara). Web ini berfungsi sebagai <strong>Alat Peraga / Media Simulasi Bukti Konsep (PoC)</strong> saat presentasi.
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                <div class="bg-slate-50 p-3.5 rounded-xl border border-gray-200">
                    <strong class="text-[#0D1C42] block mb-1">1. Fokus Aliran Data</strong>
                    <span class="text-slate-600 text-[11px]">Menjelaskan bagaimana data berpindah antar Hardware (Layer 1) dan Software (Layer 3).</span>
                </div>
                <div class="bg-slate-50 p-3.5 rounded-xl border border-gray-200">
                    <strong class="text-[#0D1C42] block mb-1">2. Standar Global GS1</strong>
                    <span class="text-slate-600 text-[11px]">Wajib menggunakan kode SSCC 18-digit, GTIN, Batch, Expiry, dan format e-AWB IATA.</span>
                </div>
                <div class="bg-slate-50 p-3.5 rounded-xl border border-gray-200">
                    <strong class="text-[#0D1C42] block mb-1">3. Tech Stack Baku</strong>
                    <span class="text-slate-600 text-[11px]">PHP Native + MySQL (XAMPP localhost) + Tailwind CSS + Vanilla JS.</span>
                </div>
            </div>
        </div>

        <!-- Section 2: Copy-Paste Prompt Prefix Generator -->
        <div class="bg-white p-6 sm:p-8 rounded-2xl border border-amber-200 shadow-sm">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div>
                        <span class="text-[10px] text-amber-700 font-mono uppercase font-bold tracking-wider">Wajib Digunakan</span>
                        <h2 class="text-base font-bold text-[#0D1C42]">System Prompt Wajib untuk AI (Prompt Prefix)</h2>
                    </div>
                </div>
                <button onclick="copySystemPrompt()" class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-900 font-bold text-xs flex items-center shadow-md shadow-amber-500/20 transition-all">
                    <i class="fa-solid fa-copy mr-1.5"></i> Salin Prompt Ini
                </button>
            </div>

            <p class="text-xs text-slate-600 mb-3">
                Sebelum meminta AI (ChatGPT, Claude, Gemini, dll.) membuat dokumen, kode, atau materi apapun, <strong>kopikan teks di bawah ini terlebih dahulu</strong> agar AI tidak menyimpang dari alur yang sudah kita buat:
            </p>

            <div class="bg-[#0D1C42] font-mono text-[11px] text-amber-200 relative p-4 rounded-xl max-h-60 overflow-y-auto shadow-inner" id="systemPromptText">Halo AI, saya sedang mengerjakan Proyek Akhir Mata Kuliah "Teknologi dan Perangkat Lunak Logistik" di ITL Trisakti.
Topik kami adalah Topik 6: "Air Cargo &amp; Intermodal Terminal (Bandara)".
Dosen Pengampu: Dr. Tigor Franky, S.T., M.T.

PERAN KITA:
Kami adalah PT AEROSPACE CONSULTANT (TIM KONSULTAN TEKNOLOGI LOGISTIK) yang memberikan saran, rekomendasi arsitektur, dan blueprint integrasi kepada pihak KLIEN (Pengelola Terminal Kargo Bandara). Kami BUKAN membuat software komersial skala penuh, melainkan membuat sistem konsultansi dan prototipe simulasi Proof-of-Concept (PoC) aliran perpindahan data.

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
        <div class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-200/90 shadow-sm">
            <h2 class="text-base font-bold text-[#0D1C42] mb-4 pb-3 border-b border-gray-100 flex items-center">
                <i class="fa-solid fa-users text-[#087F8A] mr-2"></i>
                Pembagian Tugas Anggota &amp; Template Prompt Khusus
            </h2>

            <div class="space-y-4">
                
                <!-- Peran 1 -->
                <div class="p-4 rounded-xl bg-slate-50 border border-gray-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-[#087F8A] text-xs">Peran 1: Lead System Architect &amp; Blueprint SRS</span>
                        <span class="text-[10px] font-mono text-slate-500 font-bold bg-white px-2 py-0.5 rounded border border-gray-200">Raden Panji</span>
                    </div>
                    <p class="text-xs text-slate-600 mb-2">Tanggung Jawab: Merancang arsitektur integrasi utama, dokumen Blueprint SRS, dan analisis gap As-Is vs To-Be.</p>
                    <div class="bg-white p-3 rounded-lg font-mono text-[11px] text-slate-700 border border-gray-200">
                        "Saya ingin menyusun Bab 2 SRS mengenai 'Analisis Gap Kondisi As-Is vs Rekomendasi To-Be Terminal Kargo Bandara'. Buatkan perbandingan mendalam untuk 4 aspek (antrean gate, entri manual, AVSEC, weight & balance) dalam format tabel dan narasi konsultan formal."
                    </div>
                </div>

                <!-- Peran 2 -->
                <div class="p-4 rounded-xl bg-slate-50 border border-gray-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-[#087F8A] text-xs">Peran 2: Data Integration Specialist</span>
                        <span class="text-[10px] font-mono text-slate-500 font-bold bg-white px-2 py-0.5 rounded border border-gray-200">Muhammad Fathir</span>
                    </div>
                    <p class="text-xs text-slate-600 mb-2">Tanggung Jawab: Struktur payload JSON, integrasi API, pengujian simulator, dan validasi standar GS1 SSCC 18 digit.</p>
                    <div class="bg-white p-3 rounded-lg font-mono text-[11px] text-slate-700 border border-gray-200">
                        "Saya ingin membuat variasi payload JSON untuk jenis komoditas khusus: 'Produk Segar Tuna Perishable' dan 'Kargo Farmasi Cold Chain'. Tunjukkan data AI (00), (01), (10), dan (17) yang cocok dikirim ke tabel scan_logs di MySQL."
                    </div>
                </div>

                <!-- Peran 3 -->
                <div class="p-4 rounded-xl bg-slate-50 border border-gray-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-[#087F8A] text-xs">Peran 3: Software &amp; ERP Process Specialist</span>
                        <span class="text-[10px] font-mono text-slate-500 font-bold bg-white px-2 py-0.5 rounded border border-gray-200">Riepka Tiara</span>
                    </div>
                    <p class="text-xs text-slate-600 mb-2">Tanggung Jawab: Flowchart proses bisnis CMS, validasi e-AWB, alur isolasi kargo SUSPECT di AVSEC, dan SOP kargo.</p>
                    <div class="bg-white p-3 rounded-lg font-mono text-[11px] text-slate-700 border border-gray-200">
                        "Buatkan flowchart diagram alur proses penanganan kargo berstatus SUSPECT di AVSEC: bagaimana sistem secara otomatis memblokir kargo agar tidak dapat melanjutkan ke penimbangan ULD dan bagaimana alur notifikasinya ke CMS."
                    </div>
                </div>

                <!-- Peran 4 -->
                <div class="p-4 rounded-xl bg-slate-50 border border-gray-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-[#087F8A] text-xs">Peran 4: Hardware &amp; Business Analyst QA</span>
                        <span class="text-[10px] font-mono text-slate-500 font-bold bg-white px-2 py-0.5 rounded border border-gray-200">Nessa Amanda</span>
                    </div>
                    <p class="text-xs text-slate-600 mb-2">Tanggung Jawab: Spesifikasi teknis perangkat keras lapangan, pengujian fungsional sistem, dan kalkulasi manfaat ROI untuk klien.</p>
                    <div class="bg-white p-3 rounded-lg font-mono text-[11px] text-slate-700 border border-gray-200">
                        "Buatkan rincian spesifikasi teknis 3 perangkat keras utama (High-Speed Scanner, Dual-View X-Ray, Smart ULD Scale) beserta analisis kelayakan finansial: estimasi penurunan dwell time sebesar 81% dan penghematan biaya operasional terminal kargo."
                    </div>
                </div>

            </div>
        </div>

        <!-- Section 4: Alur Baku 7 Tahapan Kargo -->
        <div class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-200/90 shadow-sm">
            <h2 class="text-base font-bold text-[#0D1C42] mb-3 pb-3 border-b border-gray-100 flex items-center">
                <i class="fa-solid fa-arrows-turn-to-dots text-[#087F8A] mr-2"></i>
                Tabel Acuan Baku: 7 Tahapan Perpindahan Data Kargo
            </h2>
            <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                <table class="w-full text-left text-xs divide-y divide-gray-200">
                    <thead class="bg-slate-50 text-slate-600 font-bold uppercase text-[10.5px]">
                        <tr>
                            <th class="py-3 px-3">#</th>
                            <th class="py-3 px-3">Nama Tahapan</th>
                            <th class="py-3 px-3">Hardware (Layer 1)</th>
                            <th class="py-3 px-3">Software (Layer 3)</th>
                            <th class="py-3 px-3">Data Kunci yang Berpindah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-[11.5px] bg-white">
                        <tr>
                            <td class="py-3 px-3 font-bold text-[#087F8A]">1</td>
                            <td class="py-3 px-3 font-bold text-[#0D1C42]">Kedatangan Truk</td>
                            <td class="py-3 px-3 text-slate-600">Barrier Gate &amp; RFID Reader</td>
                            <td class="py-3 px-3 text-slate-700 font-semibold">TMS / TAS</td>
                            <td class="py-3 px-3 text-slate-500 font-mono text-[11px]">Plat truk, slot dock, booking code, ETA</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-3 font-bold text-[#087F8A]">2</td>
                            <td class="py-3 px-3 font-bold text-[#0D1C42]">Scan RFID Gate</td>
                            <td class="py-3 px-3 text-slate-600">High-Speed Scanner (2.5 m/s)</td>
                            <td class="py-3 px-3 text-slate-700 font-semibold">CMS Inbound Gate</td>
                            <td class="py-3 px-3 text-slate-500 font-mono text-[11px]">GS1 SSCC (18 digit), GTIN, batch, expiry</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-3 font-bold text-[#087F8A]">3</td>
                            <td class="py-3 px-3 font-bold text-[#0D1C42]">Registrasi di CMS</td>
                            <td class="py-3 px-3 text-slate-600">CMS Core Server</td>
                            <td class="py-3 px-3 text-slate-700 font-semibold">Cargo Management Software</td>
                            <td class="py-3 px-3 text-slate-500 font-mono text-[11px]">Nomor e-AWB, shipper, consignee, routing</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-3 font-bold text-[#087F8A]">4</td>
                            <td class="py-3 px-3 font-bold text-[#0D1C42]">X-Ray AVSEC</td>
                            <td class="py-3 px-3 text-slate-600">Dual-View X-Ray (180x180cm)</td>
                            <td class="py-3 px-3 text-slate-700 font-semibold">AVSEC System</td>
                            <td class="py-3 px-3 text-slate-500 font-mono text-[11px]">Status CLEARED/SUSPECT, sertifikat CSD</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-3 font-bold text-[#087F8A]">5</td>
                            <td class="py-3 px-3 font-bold text-[#0D1C42]">Penimbangan</td>
                            <td class="py-3 px-3 text-slate-600">Smart ULD Floor Scale (15 Ton)</td>
                            <td class="py-3 px-3 text-slate-700 font-semibold">CMS &amp; Weight Module</td>
                            <td class="py-3 px-3 text-slate-500 font-mono text-[11px]">Berat aktual (kg), verifikasi toleransi e-AWB</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-3 font-bold text-[#087F8A]">6</td>
                            <td class="py-3 px-3 font-bold text-[#0D1C42]">Build-Up ULD</td>
                            <td class="py-3 px-3 text-slate-600">Workstation ULD Dock</td>
                            <td class="py-3 px-3 text-slate-700 font-semibold">Weight &amp; Balance / CoG</td>
                            <td class="py-3 px-3 text-slate-500 font-mono text-[11px]">Kode ULD (AKE/PMC), kompartemen, CoG</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-3 font-bold text-[#087F8A]">7</td>
                            <td class="py-3 px-3 font-bold text-[#0D1C42]">Loading Pesawat</td>
                            <td class="py-3 px-3 text-slate-600">Cargo High-Loader / EFB</td>
                            <td class="py-3 px-3 text-slate-700 font-semibold">CMS &amp; Flight Ops</td>
                            <td class="py-3 px-3 text-slate-500 font-mono text-[11px]">Electronic Loadsheet, status DEPARTED</td>
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
                background: '#ffffff',
                color: '#0D1C42',
                customClass: { popup: 'rounded-xl shadow-lg border border-gray-200' }
            });
        });
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
