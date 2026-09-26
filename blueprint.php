<?php
// blueprint.php
// Dokumen Blueprint Rekomendasi Konsultansi & SRS untuk Pihak Klien
require_once __DIR__ . '/config/database.php';
$user = getCurrentUser();

$pageTitle = 'Blueprint Rekomendasi Konsultansi & SRS — Air Cargo';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <!-- Top Action Toolbar (No Print) -->
    <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between pb-6 mb-8 border-b border-slate-800 gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <span class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-indigo-950 text-indigo-300 border border-indigo-700/60 uppercase">
                    Consulting Deliverable
                </span>
                <span class="text-xs text-slate-400">&bull; Topik 6: Air Cargo & Intermodal</span>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight mt-1">Dokumen Blueprint & Rekomendasi Sistem (SRS)</h1>
        </div>

        <div class="flex items-center space-x-2">
            <button onclick="window.print()" class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-semibold text-xs shadow-lg shadow-sky-500/25 transition-all flex items-center">
                <i class="fa-solid fa-print mr-2"></i>
                <span>Cetak / Simpan PDF</span>
            </button>
            <a href="simulation.php" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold border border-slate-700 transition-colors flex items-center">
                <i class="fa-solid fa-play mr-1.5 text-sky-400"></i>
                <span>Uji Coba PoC</span>
            </a>
        </div>
    </div>

    <!-- Formal Consulting Document Canvas -->
    <div class="glass-panel p-8 sm:p-12 rounded-3xl border border-slate-800 shadow-2xl text-slate-200 leading-relaxed text-xs sm:text-sm space-y-12">
        
        <!-- Document Title Block -->
        <div class="text-center pb-8 border-b border-slate-700/80">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-sky-600 to-indigo-600 text-white flex items-center justify-center text-3xl mx-auto mb-4 shadow-xl shadow-sky-500/25">
                <i class="fa-solid fa-plane-departure"></i>
            </div>
            <span class="text-xs uppercase tracking-widest text-sky-400 font-bold block mb-1">
                Laporan Rekomendasi Konsultansi Teknologi & Perangkat Lunak Logistik
            </span>
            <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-snug">
                BLUEPRINT ARSITEKTUR INTEGRASI SISTEM & STANDARISASI DATA AIR CARGO & INTERMODAL TERMINAL
            </h1>
            <p class="text-xs text-slate-400 max-w-2xl mx-auto mt-3">
                Dokumen Spesifikasi Kebutuhan Sistem (SRS) dan Rekomendasi Transformasi Alur Kargo Udara bagi Klien Pengelola Terminal Kargo Bandara
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-8 pt-6 border-t border-slate-800 text-left text-xs">
                <div>
                    <span class="text-slate-500 text-[10px] uppercase font-semibold block">Institusi</span>
                    <strong class="text-white">ITL Trisakti</strong>
                </div>
                <div>
                    <span class="text-slate-500 text-[10px] uppercase font-semibold block">Mata Kuliah</span>
                    <strong class="text-white">Teknologi & PL Logistik</strong>
                </div>
                <div>
                    <span class="text-slate-500 text-[10px] uppercase font-semibold block">Dosen Pengampu</span>
                    <strong class="text-white">Dr. Tigor Franky, S.T., M.T.</strong>
                </div>
                <div>
                    <span class="text-slate-500 text-[10px] uppercase font-semibold block">Tim Konsultan</span>
                    <strong class="text-sky-400">Kelompok 1</strong>
                </div>
            </div>
        </div>

        <!-- Bab 1: Executive Summary -->
        <section class="space-y-3">
            <h2 class="text-lg font-bold text-white flex items-center pb-2 border-b border-slate-800">
                <span class="text-sky-400 font-mono mr-2">01.</span>
                Ringkasan Eksekutif & Latar Belakang (Executive Summary)
            </h2>
            <p>
                Terminal Kargo Udara (*Air Cargo & Intermodal Terminal*) merupakan simpul kritis yang menjembatani moda transportasi darat (truk kontainer/armada logistik) dengan moda transportasi udara (pesawat kargo atau belly-hold pesawat penumpang). Dalam era Logistik 4.0, tantangan terbesar pengelola terminal bukan semata-mata pemindahan barang fisik (*Physical Flow*), melainkan bagaimana menyinkronkan <strong>tiga aliran utama rantai pasok</strong>:
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                <div class="bg-slate-900/80 p-4 rounded-xl border border-slate-800">
                    <strong class="text-sky-400 block mb-1">1. Physical Flow (Barang)</strong>
                    <span class="text-slate-400 text-xs">Pergerakan fisik kargo dari origin (truk) menuju tujuan (kompartemen kargo pesawat).</span>
                </div>
                <div class="bg-slate-900/80 p-4 rounded-xl border border-slate-800">
                    <strong class="text-indigo-400 block mb-1">2. Information Flow (Data)</strong>
                    <span class="text-slate-400 text-xs">Pertukaran data status, nomor AWB, identitas SSCC, berat timbangan, dan keamanan AVSEC secara real-time.</span>
                </div>
                <div class="bg-slate-900/80 p-4 rounded-xl border border-slate-800">
                    <strong class="text-emerald-400 block mb-1">3. Financial Flow (Keuangan)</strong>
                    <span class="text-slate-400 text-xs">Penerbitan billing sewa gudang, bea handling, dokumen kepabeanan, dan freight fee.</span>
                </div>
            </div>
            <p class="pt-2 text-slate-300">
                Sebagai konsultan, tim kami menyarankan adopsi arsitektur 4-layer dan standar penomoran global <strong>GS1 SSCC (18-digit)</strong> agar perpindahan data kargo dari gerbang darat hingga apron pesawat terjadi secara instan, tanpa entri manual, dan bebas dari anomali manifes.
            </p>
        </section>

        <!-- Bab 2: Problem Statement & Analisis As-Is vs To-Be -->
        <section class="space-y-4">
            <h2 class="text-lg font-bold text-white flex items-center pb-2 border-b border-slate-800">
                <span class="text-sky-400 font-mono mr-2">02.</span>
                Analisis Kebutuhan Klien: As-Is vs Rekomendasi To-Be
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border border-slate-800 rounded-xl overflow-hidden">
                    <thead class="bg-slate-900 text-slate-300 font-semibold uppercase text-[10px]">
                        <tr>
                            <th class="py-3 px-4 w-1/4">Aspek Operasional</th>
                            <th class="py-3 px-4 w-3/8 text-rose-300 bg-rose-950/20">Kondisi Saat Ini (As-Is / Problem)</th>
                            <th class="py-3 px-4 w-3/8 text-emerald-300 bg-emerald-950/20">Rekomendasi Konsultan (To-Be)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 text-[11px]">
                        <tr>
                            <td class="py-3 px-4 font-bold text-white">Kedatangan Truk & Gate</td>
                            <td class="py-3 px-4 text-slate-400 bg-rose-950/10">Truk datang acak; antrean panjang hingga 3-4 jam di gate masuk terminal kargo.</td>
                            <td class="py-3 px-4 text-slate-300 bg-emerald-950/10">Integrasi <strong>TMS/TAS (Truck Appointment System)</strong> dengan reservasi time-slot dan alokasi dock otomatis.</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 font-bold text-white">Pencatatan Kargo Masuk</td>
                            <td class="py-3 px-4 text-slate-400 bg-rose-950/10">Pencatatan manual satu-per-satu per koli; rawan salah ketik nomor AWB dan tanggal kadaluwarsa.</td>
                            <td class="py-3 px-4 text-slate-300 bg-emerald-950/10">Otomasi <strong>High-Speed RFID UHF & Barcode Scanner</strong> membaca label GS1 SSCC sekali pindai (1-scan inbound).</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 font-bold text-white">Keamanan AVSEC</td>
                            <td class="py-3 px-4 text-slate-400 bg-rose-950/10">Status screening dicatat terpisah di buku log AVSEC; konfirmasi lambat ke tim build-up.</td>
                            <td class="py-3 px-4 text-slate-300 bg-emerald-950/10">Integrasi mesin <strong>Dual-View X-Ray</strong> langsung ke CMS; penerbitan sertifikat keamanan CSD digital otomatis.</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 font-bold text-white">Weight & Balance Pesawat</td>
                            <td class="py-3 px-4 text-slate-400 bg-rose-950/10">Perhitungan manual titik berat; selisih berat fisik dan deklarasi membahayakan Center of Gravity (CoG).</td>
                            <td class="py-3 px-4 text-slate-300 bg-emerald-950/10"><strong>Smart ULD Floor Scale</strong> langsung mengirim data aktual ke Load Planning Software untuk validasi load limit.</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 font-bold text-white">Finalisasi Manifes</td>
                            <td class="py-3 px-4 text-slate-400 bg-rose-950/10">Dokumen manifes fisik rawan revisi menit terakhir menjelang jam lepas landas pesawat.</td>
                            <td class="py-3 px-4 text-slate-300 bg-emerald-950/10">Penerbitan <strong>Electronic Loadsheet</strong> langsung tersinkron ke Electronic Flight Bag (EFB) kokpit pilot.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Bab 3: Taksonomi 4 Layers IT Logistik -->
        <section class="space-y-4">
            <h2 class="text-lg font-bold text-white flex items-center pb-2 border-b border-slate-800">
                <span class="text-sky-400 font-mono mr-2">03.</span>
                Desain Arsitektur Sistem 4-Layer IT Logistik
            </h2>
            <p>
                Rancangan arsitektur yang kami sarankan disusun secara modular dalam 4 lapisan yang saling terhubung:
            </p>

            <div class="space-y-3">
                <div class="p-4 rounded-xl bg-slate-900/80 border-l-4 border-sky-500">
                    <strong class="text-white text-xs block mb-1">Layer 1: Sensing & Data Capture (Hardware Lapangan)</strong>
                    <p class="text-slate-400 text-xs">
                        Bertugas mengumpulkan data fisik kargo secara nirkabel dan otomatis. Terdiri dari <em>High-Speed Barcode/RFID UHF Tunnel Scanner</em> pada jalur konveyor gerbang, <em>Dual-View High-Energy X-Ray Scanner</em> (lorong 180×180 cm, 320kV) di ruang steril AVSEC, dan <em>Smart ULD Floor Scale</em> kapasitas 15 ton dengan load cell IP68 hermetis.
                    </p>
                </div>
                <div class="p-4 rounded-xl bg-slate-900/80 border-l-4 border-indigo-500">
                    <strong class="text-white text-xs block mb-1">Layer 2: Network & Connectivity Protocols</strong>
                    <p class="text-slate-400 text-xs">
                        Menghubungkan sensor dan perangkat lapangan ke server gudang dan komputasi awan. Menggunakan protokol <em>Industrial Ethernet & Wi-Fi 6 (802.11ax)</em> untuk area ramp/gudang, komunikasi serial <em>RS-232 / TCP Socket</em> untuk timbangan lantai, serta koneksi seluler <em>4G/5G</em> untuk telematika armada truk.
                    </p>
                </div>
                <div class="p-4 rounded-xl bg-slate-900/80 border-l-4 border-emerald-500">
                    <strong class="text-white text-xs block mb-1">Layer 3: Application & Software Systems</strong>
                    <p class="text-slate-400 text-xs">
                        Modul-modul aplikasi inti pengambil keputusan: <strong>Intermodal TMS/TAS</strong> (manajemen antrean truk), <strong>Cargo Management Software (CMS)</strong> (pengelola master e-AWB dan mutasi rak kargo), <strong>AVSEC Management System</strong> (audit keamanan dan penerbitan CSD), serta <strong>Weight & Balance / Load Planning</strong> (alokasi kontainer ULD di belly-hold pesawat).
                    </p>
                </div>
                <div class="p-4 rounded-xl bg-slate-900/80 border-l-4 border-amber-500">
                    <strong class="text-white text-xs block mb-1">Layer 4: Data Integration & Communication Protocol</strong>
                    <p class="text-slate-400 text-xs">
                        Bahasa integrasi antar perangkat: Standarisasi penomoran <strong>GS1 AI (Application Identifiers)</strong> dan <strong>SSCC (00) 18-digit</strong>, didukung oleh pertukaran pesan via <strong>RESTful API dengan format JSON Payload</strong> terenkripsi dan Webhooks otomatis.
                    </p>
                </div>
            </div>
        </section>

        <!-- Bab 4: Desain Standar GS1 & SSCC 18-Digit -->
        <section class="space-y-4">
            <h2 class="text-lg font-bold text-white flex items-center pb-2 border-b border-slate-800">
                <span class="text-sky-400 font-mono mr-2">04.</span>
                Standarisasi Global: GS1 Application Identifiers & SSCC 18-Digit
            </h2>
            <p>
                Kunci keberhasilan otomatisasi kargo udara tanpa sentuh adalah penggunaan <strong>SSCC (Serial Shipping Container Code)</strong> sebagai paspor unit logistik (palet/koli). SSCC bukanlah ID produk, melainkan identitas unik kontainer pengiriman yang menghubungkan muatan fisik dengan data manifes di CMS:
            </p>

            <div class="bg-black/50 p-4 rounded-xl border border-slate-800 font-mono text-xs">
                <div class="text-slate-400 text-[10px] mb-2 font-sans font-semibold uppercase">Anatomi Format 18-Digit SSCC:</div>
                <div class="flex flex-wrap items-center gap-2 text-white">
                    <span class="px-2 py-1 bg-sky-950 text-sky-300 rounded border border-sky-800">(00) AI</span>
                    <span>+</span>
                    <span class="px-2 py-1 bg-slate-800 text-slate-300 rounded">Digit Ekstensi [1 digit]</span>
                    <span>+</span>
                    <span class="px-2 py-1 bg-indigo-950 text-indigo-300 rounded border border-indigo-800">GS1 Company Prefix (89912345) [7-10 digit]</span>
                    <span>+</span>
                    <span class="px-2 py-1 bg-slate-800 text-slate-300 rounded">Referensi Serial [5-8 digit]</span>
                    <span>+</span>
                    <span class="px-2 py-1 bg-emerald-950 text-emerald-300 rounded border border-emerald-800">Check Digit [Modulo 10]</span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs pt-2">
                <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
                    <strong class="text-sky-400 block mb-1">AI (01) — GTIN</strong>
                    <span class="text-slate-400">Pengidentifikasi unik varian produk barang ekspor/impor.</span>
                </div>
                <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
                    <strong class="text-indigo-400 block mb-1">AI (10) — Batch / Lot Number</strong>
                    <span class="text-slate-400">Ketertelusuran kualitas produk medis/pharma jika terjadi penarikan (recall).</span>
                </div>
                <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
                    <strong class="text-emerald-400 block mb-1">AI (17) — Tanggal Kedaluwarsa</strong>
                    <span class="text-slate-400">Pengendalian masa berlaku barang peka waktu (*perishable / cold chain*).</span>
                </div>
                <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
                    <strong class="text-amber-400 block mb-1">AI (00) — SSCC</strong>
                    <span class="text-slate-400">Kunci utama (*Primary Key*) yang menghubungkan kargo fisik ke e-AWB di CMS.</span>
                </div>
            </div>
        </section>

        <!-- Bab 5: Alur 7 Tahapan Perpindahan Data -->
        <section class="space-y-4">
            <h2 class="text-lg font-bold text-white flex items-center pb-2 border-b border-slate-800">
                <span class="text-sky-400 font-mono mr-2">05.</span>
                SOP Alur Rekomendasi: 7 Tahapan Perpindahan Data Kargo
            </h2>
            <div class="space-y-3 text-xs">
                <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800">
                    <strong class="text-white font-bold block mb-1">Tahap 1: Kedatangan Truk & Booking Slot (TMS/TAS)</strong>
                    <span class="text-slate-400">Truk darat tiba di gerbang terminal kargo bandara. Sistem TMS/TAS mencocokkan kode reservasi dock, memvalidasi nomor plat via OCR, dan mengarahkan supir ke dock parkir yang sudah ditentukan.</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800">
                    <strong class="text-white font-bold block mb-1">Tahap 2: Scan RFID/Barcode Gerbang Masuk (High-Speed Scanner ke CMS)</strong>
                    <span class="text-slate-400">Koli kargo dibongkar ke konveyor penerimaan. Sensor pemicu optik mengaktifkan pembaca RFID UHF dan kamera multi-sudut untuk merekam kode SSCC 18-digit secara instan tanpa membuka palet.</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800">
                    <strong class="text-white font-bold block mb-1">Tahap 3: Registrasi Data di CMS & e-AWB Matching</strong>
                    <span class="text-slate-400">CMS mencocokkan nomor SSCC dengan manifes inbound digital (e-AWB). Kargo resmi berstatus terdaftar di gudang dan diteruskan ke ruang inspeksi keamanan.</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800">
                    <strong class="text-white font-bold block mb-1">Tahap 4: Pemeriksaan Keamanan X-Ray (AVSEC System)</strong>
                    <span class="text-slate-400">Kargo melewati lorong sinar-X Dual-View (320kV). Algoritma kerapatan material menganalisis ancaman. Jika CLEARED diterbitkan sertifikat CSD; jika SUSPECT kargo terkunci otomatis di area karantina.</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800">
                    <strong class="text-white font-bold block mb-1">Tahap 5: Penimbangan Kargo (Smart ULD Weighing Station)</strong>
                    <span class="text-slate-400">Kargo ditimbang pada timbangan lantai baja (kapasitas 15 ton). Berat kotor aktual dikirim melalui TCP/IP ke CMS untuk memverifikasi toleransi berat e-AWB.</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800">
                    <strong class="text-white font-bold block mb-1">Tahap 6: Build-Up ULD & Kalkulasi Weight & Balance</strong>
                    <span class="text-slate-400">Petugas mengonsolidasikan kargo ke dalam kontainer ULD (misal AKE-12345-GA). Software Weight & Balance menghitung posisi kompartemen pesawat agar Center of Gravity (CoG) tetap dalam amplop keselamatan.</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800">
                    <strong class="text-white font-bold block mb-1">Tahap 7: Update Manifes Penerbangan & Pemuatan ke Pesawat</strong>
                    <span class="text-slate-400">Manifes penerbangan GA-707 ditutup. Dokumen Electronic Loadsheet resmi diterbitkan ke pilot, kontainer ULD dimuat ke pesawat, dan status penerbangan menjadi DEPARTED.</span>
                </div>
            </div>
        </section>

        <!-- Bab 6: Skema Relasi Database & API Payload -->
        <section class="space-y-4">
            <h2 class="text-lg font-bold text-white flex items-center pb-2 border-b border-slate-800">
                <span class="text-sky-400 font-mono mr-2">06.</span>
                Kamus Data & Spesifikasi API Payload JSON
            </h2>
            <p>
                Untuk memudahkan tim developer internal klien mengimplementasikan sistem ini, kami telah menyusun skema 7 tabel basis data relasional MySQL:
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-[11px] font-mono">
                <div class="bg-slate-900 p-3 rounded-lg border border-slate-800">
                    <strong class="text-sky-400 font-sans block">1. truck_arrivals</strong>
                    <span class="text-slate-400">PK: id &bull; plate &bull; dock &bull; booking</span>
                </div>
                <div class="bg-slate-900 p-3 rounded-lg border border-slate-800">
                    <strong class="text-sky-400 font-sans block">2. cargo_shipments</strong>
                    <span class="text-slate-400">PK: id &bull; awb &bull; sscc &bull; gtin &bull; stage</span>
                </div>
                <div class="bg-slate-900 p-3 rounded-lg border border-slate-800">
                    <strong class="text-sky-400 font-sans block">3. security_checks</strong>
                    <span class="text-slate-400">PK: id &bull; FK: cargo_id &bull; xray_res &bull; csd</span>
                </div>
                <div class="bg-slate-900 p-3 rounded-lg border border-slate-800">
                    <strong class="text-sky-400 font-sans block">4. weight_records</strong>
                    <span class="text-slate-400">PK: id &bull; FK: cargo_id &bull; actual_kg</span>
                </div>
                <div class="bg-slate-900 p-3 rounded-lg border border-slate-800">
                    <strong class="text-sky-400 font-sans block">5. uld_containers</strong>
                    <span class="text-slate-400">PK: id &bull; code &bull; type &bull; weight &bull; comp</span>
                </div>
                <div class="bg-slate-900 p-3 rounded-lg border border-slate-800">
                    <strong class="text-sky-400 font-sans block">6. flight_manifests</strong>
                    <span class="text-slate-400">PK: id &bull; flight_no &bull; dest &bull; total_kg</span>
                </div>
            </div>

            <div class="p-3 bg-slate-900 rounded-lg border border-slate-800 font-mono text-[11px]">
                <strong class="text-indigo-400 font-sans block mb-1">7. scan_logs (Audit Trail Perpindahan Data)</strong>
                <span class="text-slate-400">Menyimpan id, cargo_id, stage, hardware_device, software_system, json_payload, response_payload, timestamp.</span>
            </div>
        </section>

        <!-- Bab 7: Analisis Nilai Bisnis & Estimasi ROI -->
        <section class="space-y-4">
            <h2 class="text-lg font-bold text-white flex items-center pb-2 border-b border-slate-800">
                <span class="text-sky-400 font-mono mr-2">07.</span>
                Estimasi Manfaat Bisnis & Kelayakan ROI bagi Klien
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
                <div class="glass-panel p-4 rounded-xl border border-slate-700">
                    <div class="text-3xl font-black text-emerald-400 font-mono mb-1">-81%</div>
                    <strong class="text-white text-xs block">Pengurangan Dwell Time</strong>
                    <p class="text-[10px] text-slate-400 mt-1">Waktu tunggu dari 4 jam menjadi &lt; 45 menit.</p>
                </div>
                <div class="glass-panel p-4 rounded-xl border border-slate-700">
                    <div class="text-3xl font-black text-sky-400 font-mono mb-1">99.8%</div>
                    <strong class="text-white text-xs block">Akurasi Pembacaan Kargo</strong>
                    <p class="text-[10px] text-slate-400 mt-1">Eliminasi human error pada transkripsi lot & AWB.</p>
                </div>
                <div class="glass-panel p-4 rounded-xl border border-slate-700">
                    <div class="text-3xl font-black text-indigo-400 font-mono mb-1">100%</div>
                    <strong class="text-white text-xs block">Kepatuhan Weight & Balance</strong>
                    <p class="text-[10px] text-slate-400 mt-1">Zero delay penerbitan loadsheet di apron bandara.</p>
                </div>
            </div>
        </section>

        <!-- Signature Block -->
        <div class="pt-8 border-t border-slate-700 text-xs">
            <p class="text-slate-400 text-center mb-6">Disusun dan Direkomendasikan oleh Tim Konsultan Kelompok 1:</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
                    <strong class="text-white block text-xs">Raden Panji Atha F.</strong>
                    <span class="text-sky-400 text-[10px] font-mono block">22D507001024</span>
                    <span class="text-[9px] text-slate-500">Lead System Architect</span>
                </div>
                <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
                    <strong class="text-white block text-xs">Muhammad Fathir S.</strong>
                    <span class="text-indigo-400 text-[10px] font-mono block">24D507001002</span>
                    <span class="text-[9px] text-slate-500">Data Integration Specialist</span>
                </div>
                <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
                    <strong class="text-white block text-xs">Riepka Tiara</strong>
                    <span class="text-emerald-400 text-[10px] font-mono block">24D507001016</span>
                    <span class="text-[9px] text-slate-500">Software Process Specialist</span>
                </div>
                <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
                    <strong class="text-white block text-xs">Nessa Amanda G.</strong>
                    <span class="text-amber-400 text-[10px] font-mono block">24D507001025</span>
                    <span class="text-[9px] text-slate-500">Hardware & QA Specialist</span>
                </div>
            </div>
        </div>

    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
