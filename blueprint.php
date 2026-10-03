<?php
// blueprint.php
header("Location: dashboard.php");
exit;
require_once __DIR__ . '/config/database.php';
$user = getCurrentUser();

$pageTitle = 'Blueprint Rekomendasi Konsultansi & SRS — Air Cargo';
$bodyClass = 'bg-[#F8FAFC] text-slate-800';
include __DIR__ . '/includes/header.php';
?>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: #ffffff !important; color: #0f172a !important; }
    .shadow-xl, .shadow-2xl, .shadow-sm, .shadow-md { box-shadow: none !important; }
    header, footer { display: none !important; }
    .blueprint-card { border: 1px solid #cbd5e1 !important; padding: 20px !important; }
}
</style>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    
    <!-- Top Action Toolbar (No Print) -->
    <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-gray-200 gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#087F8A]/10 text-[#087F8A] border border-[#087F8A]/30 uppercase tracking-wider">
                    Consulting Deliverable
                </span>
                <span class="text-xs text-slate-500 font-medium">&bull; Topik 6: Air Cargo &amp; Intermodal Terminal</span>
            </div>
            <h1 class="text-2xl lg:text-3xl font-extrabold text-[#0D1C42] tracking-tight mt-1.5">
                Dokumen Blueprint &amp; Rekomendasi Sistem (SRS)
            </h1>
        </div>

        <div class="flex items-center space-x-2.5">
            <button onclick="window.print()" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold text-xs shadow-lg shadow-teal-500/25 transition-all flex items-center transform hover:-translate-y-0.5">
                <i class="fa-solid fa-print mr-2 text-xs"></i>
                <span>Cetak / Simpan PDF</span>
            </button>
            <a href="simulation.php" class="px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-[#0D1C42] hover:text-[#087F8A] font-bold text-xs border border-gray-300 shadow-sm transition-all flex items-center transform hover:-translate-y-0.5">
                <i class="fa-solid fa-play mr-2 text-xs text-[#087F8A]"></i>
                <span>Uji Coba PoC</span>
            </a>
        </div>
    </div>

    <!-- Formal Consulting Document Canvas (InJourney Clean White Paper) -->
    <div class="blueprint-card bg-white p-8 sm:p-14 rounded-3xl border border-gray-200/90 shadow-sm text-slate-700 leading-relaxed text-xs sm:text-sm space-y-12">
        
        <!-- Document Title Block -->
        <div class="text-center pb-8 border-b border-gray-200">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-[#0CA1AF] to-[#014D54] text-white flex items-center justify-center text-3xl mx-auto mb-4 shadow-xl shadow-teal-500/25 border border-teal-300/30">
                <i class="fa-solid fa-plane-departure"></i>
            </div>
            <span class="text-xs uppercase tracking-widest text-[#087F8A] font-bold block mb-1.5">
                Laporan Rekomendasi Konsultansi Teknologi &amp; Perangkat Lunak Logistik
            </span>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-[#0D1C42] tracking-tight leading-snug">
                BLUEPRINT ARSITEKTUR INTEGRASI SISTEM &amp; STANDARISASI DATA AIR CARGO &amp; INTERMODAL TERMINAL
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 max-w-2xl mx-auto mt-3">
                Dokumen Spesifikasi Kebutuhan Sistem (SRS) dan Rekomendasi Transformasi Alur Kargo Udara bagi Klien Pengelola Terminal Kargo Bandara
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-8 pt-6 border-t border-gray-200 text-left text-xs">
                <div>
                    <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider block mb-0.5">Klasifikasi Dokumen</span>
                    <strong class="text-[#0D1C42] font-bold">Client Advisory (Confidential)</strong>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider block mb-0.5">Praktik Konsultansi</span>
                    <strong class="text-[#0D1C42] font-bold">Air Cargo &amp; Intermodal Terminal</strong>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider block mb-0.5">Standar Kepatuhan</span>
                    <strong class="text-[#0D1C42] font-bold">IATA e-AWB, ICAO CSD, GS1 SSCC</strong>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider block mb-0.5">Firma Konsultan</span>
                    <strong class="text-[#087F8A] font-extrabold">PT Aerospace Consultant</strong>
                </div>
            </div>
        </div>

        <!-- Bab 1: Executive Summary -->
        <section class="space-y-3">
            <h2 class="text-lg lg:text-xl font-extrabold text-[#0D1C42] flex items-center pb-3 border-b border-gray-200">
                <span class="text-[#087F8A] font-mono mr-2.5">01.</span>
                Ringkasan Eksekutif &amp; Latar Belakang (Executive Summary)
            </h2>
            <p>
                Terminal Kargo Udara (<em>Air Cargo &amp; Intermodal Terminal</em>) merupakan simpul kritis yang menjembatani moda transportasi darat (truk kontainer/armada logistik) dengan moda transportasi udara (pesawat kargo atau belly-hold pesawat penumpang). Dalam era Logistik 4.0, tantangan terbesar pengelola terminal bukan semata-mata pemindahan barang fisik (<em>Physical Flow</em>), melainkan bagaimana menyinkronkan <strong>tiga aliran utama rantai pasok</strong>:
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                <div class="bg-slate-50 p-4 rounded-xl border border-gray-200">
                    <strong class="text-[#087F8A] block mb-1">1. Physical Flow (Barang)</strong>
                    <span class="text-slate-600 text-xs">Pergerakan fisik kargo dari origin (truk) menuju tujuan (kompartemen kargo pesawat).</span>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-gray-200">
                    <strong class="text-[#014D54] block mb-1">2. Information Flow (Data)</strong>
                    <span class="text-slate-600 text-xs">Pertukaran data status, nomor AWB, identitas SSCC, berat timbangan, dan keamanan AVSEC secara real-time.</span>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-gray-200">
                    <strong class="text-emerald-700 block mb-1">3. Financial Flow (Keuangan)</strong>
                    <span class="text-slate-600 text-xs">Penerbitan billing sewa gudang, bea handling, dokumen kepabeanan, dan freight fee.</span>
                </div>
            </div>
            <p class="pt-2 text-slate-600">
                Sebagai konsultan, tim kami menyarankan adopsi arsitektur 4-layer dan standar penomoran global <strong>GS1 SSCC (18-digit)</strong> agar perpindahan data kargo dari gerbang darat hingga apron pesawat terjadi secara instan, tanpa entri manual, dan bebas dari anomali manifes.
            </p>
        </section>

        <!-- Bab 2: Problem Statement & Analisis As-Is vs To-Be -->
        <section class="space-y-4">
            <h2 class="text-lg lg:text-xl font-extrabold text-[#0D1C42] flex items-center pb-3 border-b border-gray-200">
                <span class="text-[#087F8A] font-mono mr-2.5">02.</span>
                Analisis Kebutuhan Klien: As-Is vs Rekomendasi To-Be
            </h2>
            <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                <table class="w-full text-left text-xs divide-y divide-gray-200">
                    <thead class="bg-slate-50 text-slate-600 font-bold uppercase text-[10.5px] tracking-wider">
                        <tr>
                            <th class="py-3 px-4 w-1/4">Aspek Operasional</th>
                            <th class="py-3 px-4 w-3/8 text-rose-700 bg-rose-50/70">Kondisi Saat Ini (As-Is / Problem)</th>
                            <th class="py-3 px-4 w-3/8 text-emerald-700 bg-emerald-50/70">Rekomendasi Konsultan (To-Be)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-[11.5px] bg-white">
                        <tr>
                            <td class="py-3 px-4 font-bold text-[#0D1C42]">Kedatangan Truk &amp; Gate</td>
                            <td class="py-3 px-4 text-slate-600 bg-rose-50/20">Truk datang acak; antrean panjang hingga 3-4 jam di gate masuk terminal kargo.</td>
                            <td class="py-3 px-4 text-slate-700 bg-emerald-50/20">Integrasi <strong>TMS/TAS (Truck Appointment System)</strong> dengan reservasi time-slot dan alokasi dock otomatis.</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 font-bold text-[#0D1C42]">Pencatatan Kargo Masuk</td>
                            <td class="py-3 px-4 text-slate-600 bg-rose-50/20">Pencatatan manual satu-per-satu per koli; rawan salah ketik nomor AWB dan tanggal kadaluwarsa.</td>
                            <td class="py-3 px-4 text-slate-700 bg-emerald-50/20">Otomasi <strong>High-Speed RFID UHF &amp; Barcode Scanner</strong> membaca label GS1 SSCC sekali pindai (1-scan inbound).</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 font-bold text-[#0D1C42]">Keamanan AVSEC</td>
                            <td class="py-3 px-4 text-slate-600 bg-rose-50/20">Status screening dicatat terpisah di buku log AVSEC; konfirmasi lambat ke tim build-up.</td>
                            <td class="py-3 px-4 text-slate-700 bg-emerald-50/20">Integrasi mesin <strong>Dual-View X-Ray</strong> langsung ke CMS; penerbitan sertifikat keamanan CSD digital otomatis.</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 font-bold text-[#0D1C42]">Weight &amp; Balance Pesawat</td>
                            <td class="py-3 px-4 text-slate-600 bg-rose-50/20">Perhitungan manual titik berat; selisih berat fisik dan deklarasi membahayakan Center of Gravity (CoG).</td>
                            <td class="py-3 px-4 text-slate-700 bg-emerald-50/20"><strong>Smart ULD Floor Scale</strong> langsung mengirim data aktual ke Load Planning Software untuk validasi load limit.</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 font-bold text-[#0D1C42]">Finalisasi Manifes</td>
                            <td class="py-3 px-4 text-slate-600 bg-rose-50/20">Dokumen manifes fisik rawan revisi menit terakhir menjelang jam lepas landas pesawat.</td>
                            <td class="py-3 px-4 text-slate-700 bg-emerald-50/20">Penerbitan <strong>Electronic Loadsheet</strong> langsung tersinkron ke Electronic Flight Bag (EFB) kokpit pilot.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Bab 3: Taksonomi 4 Layers IT Logistik -->
        <section class="space-y-4">
            <h2 class="text-lg lg:text-xl font-extrabold text-[#0D1C42] flex items-center pb-3 border-b border-gray-200">
                <span class="text-[#087F8A] font-mono mr-2.5">03.</span>
                Desain Arsitektur Sistem 4-Layer IT Logistik
            </h2>
            <p>
                Rancangan arsitektur yang kami sarankan disusun secara modular dalam 4 lapisan yang saling terhubung:
            </p>

            <div class="space-y-3">
                <div class="p-4 rounded-xl bg-slate-50 border-l-4 border-[#087F8A] border border-gray-200">
                    <strong class="text-[#0D1C42] font-bold text-xs block mb-1">Layer 1: Sensing &amp; Data Capture (Hardware Lapangan)</strong>
                    <p class="text-slate-600 text-xs">
                        Bertugas mengumpulkan data fisik kargo secara nirkabel dan otomatis. Terdiri dari <em>High-Speed Barcode/RFID UHF Tunnel Scanner</em> pada jalur konveyor gerbang, <em>Dual-View High-Energy X-Ray Scanner</em> (lorong 180×180 cm, 320kV) di ruang steril AVSEC, dan <em>Smart ULD Floor Scale</em> kapasitas 15 ton dengan load cell IP68 hermetis.
                    </p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border-l-4 border-teal-500 border border-gray-200">
                    <strong class="text-[#0D1C42] font-bold text-xs block mb-1">Layer 2: Network &amp; Connectivity Protocols</strong>
                    <p class="text-slate-600 text-xs">
                        Menghubungkan sensor dan perangkat lapangan ke server gudang dan komputasi awan. Menggunakan protokol <em>Industrial Ethernet &amp; Wi-Fi 6 (802.11ax)</em> untuk area ramp/gudang, komunikasi serial <em>RS-232 / TCP Socket</em> untuk timbangan lantai, serta koneksi seluler <em>4G/5G</em> untuk telematika armada truk.
                    </p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border-l-4 border-emerald-500 border border-gray-200">
                    <strong class="text-[#0D1C42] font-bold text-xs block mb-1">Layer 3: Application &amp; Software Systems</strong>
                    <p class="text-slate-600 text-xs">
                        Modul-modul aplikasi inti pengambil keputusan: <strong>Intermodal TMS/TAS</strong> (manajemen antrean truk), <strong>Cargo Management Software (CMS)</strong> (pengelola master e-AWB dan mutasi rak kargo), <strong>AVSEC Management System</strong> (audit keamanan dan penerbitan CSD), serta <strong>Weight &amp; Balance / Load Planning</strong> (alokasi kontainer ULD di belly-hold pesawat).
                    </p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border-l-4 border-amber-500 border border-gray-200">
                    <strong class="text-[#0D1C42] font-bold text-xs block mb-1">Layer 4: Data Integration &amp; Communication Protocol</strong>
                    <p class="text-slate-600 text-xs">
                        Bahasa integrasi antar perangkat: Standarisasi penomoran <strong>GS1 AI (Application Identifiers)</strong> dan <strong>SSCC (00) 18-digit</strong>, didukung oleh pertukaran pesan via <strong>RESTful API dengan format JSON Payload</strong> terenkripsi dan Webhooks otomatis.
                    </p>
                </div>
            </div>
        </section>

        <!-- Bab 4: Desain Standar GS1 & SSCC 18-Digit -->
        <section class="space-y-4">
            <h2 class="text-lg lg:text-xl font-extrabold text-[#0D1C42] flex items-center pb-3 border-b border-gray-200">
                <span class="text-[#087F8A] font-mono mr-2.5">04.</span>
                Standarisasi Global: GS1 Application Identifiers &amp; SSCC 18-Digit
            </h2>
            <p>
                Kunci keberhasilan otomatisasi kargo udara tanpa sentuh adalah penggunaan <strong>SSCC (Serial Shipping Container Code)</strong> sebagai paspor unit logistik (palet/koli). SSCC bukanlah ID produk, melainkan identitas unik kontainer pengiriman yang menghubungkan muatan fisik dengan data manifes di CMS:
            </p>

            <div class="bg-[#0D1C42] text-white p-4 rounded-xl border border-slate-700/80 font-mono text-xs shadow-inner">
                <div class="text-teal-300 text-[10px] mb-2 font-sans font-bold uppercase tracking-wider">Anatomi Format 18-Digit SSCC:</div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2 py-1 bg-teal-900/60 text-teal-300 rounded border border-teal-500/40 font-bold">(00) AI</span>
                    <span class="text-slate-400">+</span>
                    <span class="px-2 py-1 bg-slate-800 text-slate-200 rounded">Digit Ekstensi [1 digit]</span>
                    <span class="text-slate-400">+</span>
                    <span class="px-2 py-1 bg-teal-800/80 text-teal-200 rounded border border-teal-600">GS1 Company Prefix (89912345) [7-10 digit]</span>
                    <span class="text-slate-400">+</span>
                    <span class="px-2 py-1 bg-slate-800 text-slate-200 rounded">Referensi Serial [5-8 digit]</span>
                    <span class="text-slate-400">+</span>
                    <span class="px-2 py-1 bg-emerald-900/60 text-emerald-300 rounded border border-emerald-500/40 font-bold">Check Digit [Modulo 10]</span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs pt-2">
                <div class="p-3.5 bg-slate-50 rounded-xl border border-gray-200">
                    <strong class="text-[#087F8A] block mb-1">AI (01) — GTIN</strong>
                    <span class="text-slate-600">Pengidentifikasi unik varian produk barang ekspor/impor.</span>
                </div>
                <div class="p-3.5 bg-slate-50 rounded-xl border border-gray-200">
                    <strong class="text-[#014D54] block mb-1">AI (10) — Batch / Lot Number</strong>
                    <span class="text-slate-600">Ketertelusuran kualitas produk medis/pharma jika terjadi penarikan (recall).</span>
                </div>
                <div class="p-3.5 bg-slate-50 rounded-xl border border-gray-200">
                    <strong class="text-emerald-700 block mb-1">AI (17) — Tanggal Kedaluwarsa</strong>
                    <span class="text-slate-600">Pengendalian masa berlaku barang peka waktu (<em>perishable / cold chain</em>).</span>
                </div>
                <div class="p-3.5 bg-slate-50 rounded-xl border border-gray-200">
                    <strong class="text-amber-700 block mb-1">AI (00) — SSCC</strong>
                    <span class="text-slate-600">Kunci utama (<em>Primary Key</em>) yang menghubungkan kargo fisik ke e-AWB di CMS.</span>
                </div>
            </div>
        </section>

        <!-- Bab 5: Alur 7 Tahapan Perpindahan Data -->
        <section class="space-y-4">
            <h2 class="text-lg lg:text-xl font-extrabold text-[#0D1C42] flex items-center pb-3 border-b border-gray-200">
                <span class="text-[#087F8A] font-mono mr-2.5">05.</span>
                SOP Alur Rekomendasi: 7 Tahapan Perpindahan Data Kargo
            </h2>
            <div class="space-y-3 text-xs">
                <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 hover:border-[#0CA1AF] transition-colors">
                    <strong class="text-[#0D1C42] font-bold block mb-1 text-xs">Tahap 1: Kedatangan Truk &amp; Booking Slot (TMS/TAS)</strong>
                    <span class="text-slate-600 leading-relaxed text-[11.5px]">Truk darat tiba di gerbang terminal kargo bandara. Sistem TMS/TAS mencocokkan kode reservasi dock, memvalidasi nomor plat via OCR, dan mengarahkan supir ke dock parkir yang sudah ditentukan.</span>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 hover:border-[#0CA1AF] transition-colors">
                    <strong class="text-[#0D1C42] font-bold block mb-1 text-xs">Tahap 2: Scan RFID/Barcode Gerbang Masuk (High-Speed Scanner ke CMS)</strong>
                    <span class="text-slate-600 leading-relaxed text-[11.5px]">Koli kargo dibongkar ke konveyor penerimaan. Sensor pemicu optik mengaktifkan pembaca RFID UHF dan kamera multi-sudut untuk merekam kode SSCC 18-digit secara instan tanpa membuka palet.</span>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 hover:border-[#0CA1AF] transition-colors">
                    <strong class="text-[#0D1C42] font-bold block mb-1 text-xs">Tahap 3: Registrasi Data di CMS &amp; e-AWB Matching</strong>
                    <span class="text-slate-600 leading-relaxed text-[11.5px]">CMS mencocokkan nomor SSCC dengan manifes inbound digital (e-AWB). Kargo resmi berstatus terdaftar di gudang dan diteruskan ke ruang inspeksi keamanan.</span>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 hover:border-[#0CA1AF] transition-colors">
                    <strong class="text-[#0D1C42] font-bold block mb-1 text-xs">Tahap 4: Pemeriksaan Keamanan X-Ray (AVSEC System)</strong>
                    <span class="text-slate-600 leading-relaxed text-[11.5px]">Kargo melewati lorong sinar-X Dual-View (320kV). Algoritma kerapatan material menganalisis ancaman. Jika CLEARED diterbitkan sertifikat CSD; jika SUSPECT kargo terkunci otomatis di area karantina.</span>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 hover:border-[#0CA1AF] transition-colors">
                    <strong class="text-[#0D1C42] font-bold block mb-1 text-xs">Tahap 5: Penimbangan Kargo (Smart ULD Weighing Station)</strong>
                    <span class="text-slate-600 leading-relaxed text-[11.5px]">Kargo ditimbang pada timbangan lantai baja (kapasitas 15 ton). Berat kotor aktual dikirim melalui TCP/IP ke CMS untuk memverifikasi toleransi berat e-AWB.</span>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 hover:border-[#0CA1AF] transition-colors">
                    <strong class="text-[#0D1C42] font-bold block mb-1 text-xs">Tahap 6: Build-Up ULD &amp; Kalkulasi Weight &amp; Balance</strong>
                    <span class="text-slate-600 leading-relaxed text-[11.5px]">Petugas mengonsolidasikan kargo ke dalam kontainer ULD (misal AKE-12345-GA). Software Weight &amp; Balance menghitung posisi kompartemen pesawat agar Center of Gravity (CoG) tetap dalam amplop keselamatan.</span>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 hover:border-[#0CA1AF] transition-colors">
                    <strong class="text-[#0D1C42] font-bold block mb-1 text-xs">Tahap 7: Update Manifes Penerbangan &amp; Pemuatan ke Pesawat</strong>
                    <span class="text-slate-600 leading-relaxed text-[11.5px]">Manifes penerbangan GA-707 ditutup. Dokumen Electronic Loadsheet resmi diterbitkan ke pilot, kontainer ULD dimuat ke pesawat, dan status penerbangan menjadi DEPARTED.</span>
                </div>
            </div>
        </section>

        <!-- Bab 6: Skema Relasi Database & API Payload -->
        <section class="space-y-4">
            <h2 class="text-lg lg:text-xl font-extrabold text-[#0D1C42] flex items-center pb-3 border-b border-gray-200">
                <span class="text-[#087F8A] font-mono mr-2.5">06.</span>
                Kamus Data &amp; Spesifikasi API Payload JSON
            </h2>
            <p>
                Untuk memudahkan tim developer internal klien mengimplementasikan sistem ini, kami telah menyusun skema 7 tabel basis data relasional MySQL:
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-[11px] font-mono">
                <div class="bg-slate-50 p-3.5 rounded-xl border border-gray-200">
                    <strong class="text-[#087F8A] font-sans font-bold block mb-0.5">1. truck_arrivals</strong>
                    <span class="text-slate-500">PK: id &bull; plate &bull; dock &bull; booking</span>
                </div>
                <div class="bg-slate-50 p-3.5 rounded-xl border border-gray-200">
                    <strong class="text-[#087F8A] font-sans font-bold block mb-0.5">2. cargo_shipments</strong>
                    <span class="text-slate-500">PK: id &bull; awb &bull; sscc &bull; gtin &bull; stage</span>
                </div>
                <div class="bg-slate-50 p-3.5 rounded-xl border border-gray-200">
                    <strong class="text-[#087F8A] font-sans font-bold block mb-0.5">3. security_checks</strong>
                    <span class="text-slate-500">PK: id &bull; FK: cargo_id &bull; xray_res &bull; csd</span>
                </div>
                <div class="bg-slate-50 p-3.5 rounded-xl border border-gray-200">
                    <strong class="text-[#087F8A] font-sans font-bold block mb-0.5">4. weight_records</strong>
                    <span class="text-slate-500">PK: id &bull; FK: cargo_id &bull; actual_kg</span>
                </div>
                <div class="bg-slate-50 p-3.5 rounded-xl border border-gray-200">
                    <strong class="text-[#087F8A] font-sans font-bold block mb-0.5">5. uld_containers</strong>
                    <span class="text-slate-500">PK: id &bull; code &bull; type &bull; weight &bull; comp</span>
                </div>
                <div class="bg-slate-50 p-3.5 rounded-xl border border-gray-200">
                    <strong class="text-[#087F8A] font-sans font-bold block mb-0.5">6. flight_manifests</strong>
                    <span class="text-slate-500">PK: id &bull; flight_no &bull; dest &bull; total_kg</span>
                </div>
            </div>

            <div class="p-3.5 bg-slate-50 rounded-xl border border-teal-200 font-mono text-[11px]">
                <strong class="text-[#0D1C42] font-sans font-bold block mb-1">7. scan_logs (Audit Trail Perpindahan Data)</strong>
                <span class="text-slate-600">Menyimpan id, cargo_id, stage, hardware_device, software_system, json_payload, response_payload, timestamp.</span>
            </div>
        </section>

        <!-- Bab 7: Analisis Nilai Bisnis & Estimasi ROI -->
        <section class="space-y-4">
            <h2 class="text-lg lg:text-xl font-extrabold text-[#0D1C42] flex items-center pb-3 border-b border-gray-200">
                <span class="text-[#087F8A] font-mono mr-2.5">07.</span>
                Estimasi Manfaat Bisnis &amp; Kelayakan ROI bagi Klien
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
                <div class="bg-white p-5 rounded-2xl border border-gray-200/90 shadow-sm">
                    <div class="text-3xl font-black text-emerald-600 font-mono mb-1">-81%</div>
                    <strong class="text-[#0D1C42] text-xs font-bold block">Pengurangan Dwell Time</strong>
                    <p class="text-[10.5px] text-slate-500 mt-1">Waktu tunggu dari 4 jam menjadi &lt; 45 menit.</p>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-gray-200/90 shadow-sm">
                    <div class="text-3xl font-black text-[#087F8A] font-mono mb-1">99.8%</div>
                    <strong class="text-[#0D1C42] text-xs font-bold block">Akurasi Pembacaan Kargo</strong>
                    <p class="text-[10.5px] text-slate-500 mt-1">Eliminasi human error pada transkripsi lot &amp; AWB.</p>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-gray-200/90 shadow-sm">
                    <div class="text-3xl font-black text-[#014D54] font-mono mb-1">100%</div>
                    <strong class="text-[#0D1C42] text-xs font-bold block">Kepatuhan Weight &amp; Balance</strong>
                    <p class="text-[10.5px] text-slate-500 mt-1">Zero delay penerbitan loadsheet di apron bandara.</p>
                </div>
            </div>
        </section>

        <!-- Signature Block (PT Aerospace Consultant, Strictly NO NIM) -->
        <div class="pt-8 border-t border-gray-200 text-xs">
            <p class="text-slate-500 text-center mb-6">Disusun dan Direkomendasikan oleh Tim Konsultan <strong class="text-[#0D1C42]">PT Aerospace Consultant</strong>:</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                <div class="p-3.5 bg-slate-50 rounded-xl border border-gray-200">
                    <strong class="text-[#0D1C42] font-bold block text-xs">Raden Panji Atha Firjatullah</strong>
                    <span class="text-[#087F8A] text-[10.5px] font-semibold block mt-1">Lead System Architect</span>
                </div>
                <div class="p-3.5 bg-slate-50 rounded-xl border border-gray-200">
                    <strong class="text-[#0D1C42] font-bold block text-xs">Muhammad Fathir Septianto</strong>
                    <span class="text-[#087F8A] text-[10.5px] font-semibold block mt-1">Data Integration Specialist</span>
                </div>
                <div class="p-3.5 bg-slate-50 rounded-xl border border-gray-200">
                    <strong class="text-[#0D1C42] font-bold block text-xs">Riepka Tiara</strong>
                    <span class="text-[#087F8A] text-[10.5px] font-semibold block mt-1">Software Process Specialist</span>
                </div>
                <div class="p-3.5 bg-slate-50 rounded-xl border border-gray-200">
                    <strong class="text-[#0D1C42] font-bold block text-xs">Nessa Amanda Ghassani</strong>
                    <span class="text-[#087F8A] text-[10.5px] font-semibold block mt-1">Hardware &amp; QA Specialist</span>
                </div>
            </div>
        </div>

    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
