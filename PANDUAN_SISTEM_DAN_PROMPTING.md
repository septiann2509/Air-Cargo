# BUKU PANDUAN PENGEMBANGAN SISTEM & PROMPTING GUIDE
## Proyek: Air Cargo & Intermodal Terminal (Bandara) — Topik 6
### Mata Kuliah: Teknologi dan Perangkat Lunak Logistik — ITL Trisakti
**Dosen Pengampu:** Dr. Tigor Franky, S.T., M.T.  
**Tim Konsultan (Kelompok 1):**
1. Raden Panji Atha Firjatullah (22D507001024) — *Lead System Architect*
2. Muhammad Fathir Septianto (24D507001002) — *Data Integration & Simulation Specialist*
3. Riepka Tiara (24D507001016) — *Software & ERP Process Specialist*
4. Nessa Amanda Ghassani (24D507001025) — *Hardware & QA Specialist*

---

## 1. ATURAN UTAMA & MINDSET PROYEK (WAJIB DIBACA SEMUA ANGGOTA)

> [!IMPORTANT]
> **PERAN KITA ADALAH SEBAGAI KONSULTAN TEKNOLOGI LOGISTIK, BUKAN MEMBUAT SOFTWARE PRODUKSI KOMERSIAL.**
>
> Kita bertindak sebagai tim konsultan yang memberikan **saran, rekomendasi arsitektur (*To-Be Flow*), dan blueprint integrasi** kepada pihak klien (Perusahaan Pengelola Terminal Kargo Bandara).
> Web aplikasi yang kita bangun ini berfungsi sebagai **Alat Peraga / Media Simulasi Bukti Konsep (*Proof-of-Concept / PoC*)** untuk mendemonstrasikan ke klien bahwa: *"Jika rekomendasi arsitektur kami diterapkan di terminal Anda, alur perpindahan datanya berjalan seperti ini loh!"*

### 3 Prinsip Dasar Konsultansi Kita:
1. **Fokus pada Aliran Informasi & Integrasi Data:** Menunjukkan bagaimana data berpindah dari darat (truk) &rarr; gerbang bandara &rarr; screening AVSEC &rarr; timbangan &rarr; kontainer ULD &rarr; pesawat terbang.
2. **Kepatuhan Standar Internasional:** Selalu mengacu pada standar global **GS1 SSCC 18-digit**, kode identifikasi GS1 (GTIN, Batch, Expiry), standar IATA e-AWB, sertifikat keamanan CSD AVSEC, dan IATA Weight & Balance.
3. **Teknologi Baku (Tech Stack):**
   * **Bahasa Backend:** Native **PHP 8.x** (menggunakan PDO MySQL).
   * **Database:** **MySQL** (nama database: `air_cargo_db`).
   * **Server Lokal:** **XAMPP** (Apache di Port 80, MySQL di Port 3306).
   * **Frontend:** Tailwind CSS (CDN), FontAwesome 6, Chart.js, SweetAlert2, Vanilla JavaScript ES6.
   * **Lokasi Folder:** `c:\xampp\htdocs\Air_Cargo_Intermodal_Terminal\`
   * **URL Akses:** `http://localhost/Air_Cargo_Intermodal_Terminal/`

---

## 2. ARSITEKTUR BAKU 4-LAYER IT LOGISTIK

Jika ada anggota yang ingin menambahkan fitur atau materi, pastikan selalu berada di dalam taksonomi 4 lapisan ini:

| Layer | Nama Layer | Komponen Baku Proyek Kita |
|---|---|---|
| **Layer 1** | **Sensing & Data Capture (Hardware)** | • High-Speed RFID UHF & Barcode Tunnel Scanner (2.5 m/s, IP65)<br>• Dual-View High-Energy X-Ray (180×180 cm, 320kV)<br>• Smart ULD Floor Weighing Station (15 Ton, akurasi ±1 kg, load cell IP68) |
| **Layer 2** | **Network & Connectivity** | Industrial Ethernet, Wi-Fi 6 (802.11ax), RS-232 / TCP Socket, 4G/5G Cellular |
| **Layer 3** | **Application & Software Systems** | • Intermodal TMS / TAS (*Truck Appointment System*)<br>• Cargo Management Software (CMS Master e-AWB)<br>• Aviation Security Management System (AVSEC System)<br>• Weight & Balance System / Load Planning Software |
| **Layer 4** | **Data Integration & Protocol** | • Standar GS1 AI: (00) SSCC 18 digit, (01) GTIN, (10) Batch, (17) Expiry<br>• Protokol: RESTful API Gateway, JSON Payloads, Webhooks |

---

## 3. ALUR BAKU 7 TAHAPAN PERPINDAHAN DATA (THE UNBREAKABLE 7 STAGES)

Setiap simulasi, dokumentasi SRS, flowchart, atau kode baru **HARUS** mengikuti urutan 7 tahapan ini:

```
[Tahap 1: Truk Tiba & Booking Slot (TMS/TAS)]
      ↓
[Tahap 2: Scan RFID/Barcode di Gerbang Masuk (High-Speed Scanner ke CMS)]
      ↓
[Tahap 3: Registrasi Data & Pencocokan e-AWB di CMS]
      ↓
[Tahap 4: Pemeriksaan Keamanan X-Ray (AVSEC: Status CLEARED / SUSPECT)]
      ↓  (Jika SUSPECT: Kargo diblokir otomatis & investigasi)
      ↓  (Jika CLEARED: Terbit sertifikat CSD digital)
[Tahap 5: Penimbangan Kargo (Smart ULD Floor Scale)]
      ↓
[Tahap 6: Build-Up ULD & Kalkulasi Weight & Balance (Center of Gravity)]
      ↓
[Tahap 7: Update Manifes Penerbangan & Loading ke Pesawat (DEPARTED)]
```

---

## 4. SKEMA DATABASE RESMI (7 TABEL RELASIONAL + USERS)

> [!WARNING]
> **JANGAN MENGUBAH NAMA TABEL ATAU PRIMARY KEY SECARA SEPIHAK!** Jika database error, cukup klik tombol **"Reset Demo"** di navbar web atau jalankan `api/reset.php`.

1. **`users`**: Akun login (`consultant` / `client`, password: `password123`).
2. **`truck_arrivals`**: Data kedatangan truk (`id`, `truck_plate`, `driver_name`, `dock_slot`, `booking_code`, `status`).
3. **`cargo_shipments`**: Master kargo (`id`, `awb_number`, `sscc`, `gtin`, `batch_no`, `expiry_date`, `shipper`, `consignee`, `quantity`, `declared_weight_kg`, `actual_weight_kg`, `current_stage`, `status`).
   * *Status Enum:* `REGISTERED`, `SCANNED`, `SECURITY_CHECK`, `CLEARED`, `SUSPECT`, `WEIGHED`, `ALLOCATED`, `LOADED`.
4. **`security_checks`**: Hasil AVSEC (`id`, `cargo_id`, `scanner_type`, `xray_result` [CLEARED/SUSPECT], `inspector_id`, `csd_number`, `notes`).
5. **`weight_records`**: Hasil timbangan (`id`, `cargo_id`, `declared_weight_kg`, `actual_weight_kg`, `weight_discrepancy_kg`, `scale_device_id`, `tolerance_status`).
6. **`uld_containers`**: Kontainer pesawat (`id`, `uld_code` [AKE/PMC], `max_weight_kg`, `current_weight_kg`, `flight_id`, `compartment`, `status`).
7. **`flight_manifests`**: Manifes penerbangan (`id`, `flight_number` [GA-707, SQ-801], `destination`, `max_cargo_weight_kg`, `current_total_weight_kg`, `status`).
8. **`scan_logs`**: Audit trail JSON payload transaksi (`id`, `cargo_id`, `stage`, `hardware_device`, `software_system`, `action`, `json_payload`, `response_payload`, `timestamp`).

---

## 5. PANDUAN DAN TEMPLATE PROMPT UNTUK ANGGOTA TIM

Ketika Anda atau rekan kelompok ingin menggunakan AI (ChatGPT, Claude, Gemini, Antigravity, dll.) untuk mengerjakan tugas atau menambahkan modul, **WAJIB MENYERTAKAN "SYSTEM PROMPT PENGAWAL"** di bawah ini agar AI tidak menyimpang.

### A. Template Wajib: Prefix / Konteks Proyek (Kopikan di Awal Setiap Chat dengan AI)

```text
Halo AI, saya sedang mengerjakan Proyek Akhir Mata Kuliah "Teknologi dan Perangkat Lunak Logistik" di ITL Trisakti.
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
[TULISKAN PERMINTAAN SPESIFIK ANDA DI SINI]
```

---

### B. Contoh Prompt Sesuai Pembagian Peran Anggota

#### 1. Peran: Lead System Architect & Blueprint SRS (Raden Panji)
```text
[Gunakan Prefix di atas]
Saya bertugas sebagai Lead System Architect. Saya ingin menyusun Dokumen Blueprint Arsitektur Sistem (SRS) bagian Bab 3 mengenai "Analisis Gap Kondisi As-Is vs Rekomendasi To-Be pada Terminal Kargo Bandara".
Tolong buatkan narasi profesional tingkat konsultan yang membandingkan:
1. Dwell time truk di darat (sebelum ada TAS vs sesudah ada TAS).
2. Human error pencatatan manual vs pemindaian 1 kali label GS1 SSCC 18 digit.
3. Keterlambatan screening AVSEC vs verifikasi otomatis CSD digital.
4. Risiko ketidakseimbangan beban pesawat (Center of Gravity) vs modul Weight & Balance otomatis.
Sajikan dalam bahasa Indonesia formal akademik dengan tabel komparasi yang rapi.
```

#### 2. Peran: Data Integration & Simulator Engine Specialist (Muhammad Fathir)
```text
[Gunakan Prefix di atas]
Saya bertugas sebagai Data Integration Specialist. Saya ingin membuat contoh payload JSON baru untuk skenario khusus: "Kargo Dingin Vaksin Farmasi (Cold Chain Pharma)".
Tolong buatkan:
1. JSON Request Payload pada Tahap 2 (Scan RFID) yang memuat SSCC 18 digit, GTIN, batch number vaksin, tanggal kadaluwarsa, dan batas suhu (°C).
2. JSON Response dari CMS pada Tahap 3 yang mengindikasikan prioritas kargo URGENT_COLD_CHAIN.
3. Struktur API ini harus kompatibel dengan file api/simulate.php dan tabel scan_logs yang sudah ada di database MySQL.
```

#### 3. Peran: Software & ERP Process Specialist (Riepka Tiara)
```text
[Gunakan Prefix di atas]
Saya bertugas sebagai Software & ERP Process Specialist. Saya ingin membuat Flowchart SOP Standar Operasional Prosedur penanganan kargo berstatus "SUSPECT" pada Tahap 4 (Pemeriksaan X-Ray AVSEC).
Jelaskan alur logikanya:
1. Bagaimana sistem AVSEC mengirimkan sinyal bahaya (HTTP 403 / alert) ke CMS.
2. Mengapa kargo yang SUSPECT secara otomatis dikunci oleh sistem sehingga tidak dapat melanjutkan ke Tahap 5 (Penimbangan) dan Tahap 6 (Build-Up ULD).
3. SOP pelepasan kargo karantina jika petugas AVSEC telah melakukan pemeriksaan fisik sekunder.
Buatkan dalam bentuk diagram alur Mermaid dan penjelasan teks SOP langkah-demi-langkah.
```

#### 4. Peran: Hardware & Business Analyst QA Specialist (Nessa Amanda)
```text
[Gunakan Prefix di atas]
Saya bertugas sebagai Hardware & QA Specialist. Saya ingin menyusun rincian spesifikasi perangkat keras (Layer 1) dan estimasi Return on Investment (ROI) untuk dipresentasikan ke direksi klien pengelola terminal kargo.
Tolong buatkan:
1. Spesifikasi teknis 3 hardware utama (High-Speed Scanner, Dual-View X-Ray, Smart ULD Weighing Station) lengkap dengan throughput, daya, akurasi, dan standar sertifikasi industri.
2. Analisis efisiensi biaya (CAPEX vs OPEX): Penghematan waktu bongkar muat (-81%), pencegahan denda maskapai akibat delay loadsheet, dan eliminasi biaya salah kirim kargo.
```

---

## 6. STRUKTUR FILE SISTEM (JANGAN MERUSAK FILE INI)

```
Air_Cargo_Intermodal_Terminal/
├── Materi/                 <-- PDF ringkasan materi kuliah & tugas
├── config/
│   └── database.php        <-- Koneksi PDO MySQL & session helper
├── api/
│   ├── stats.php           <-- API statistik KPI Dashboard
│   ├── cargo.php           <-- API data kargo & registrasi baru
│   ├── simulate.php        <-- API eksekutor simulasi 7 tahapan & auto-run
│   ├── tables.php          <-- API penjelajah tabel database viewer
│   └── reset.php           <-- API reset data demo awal
├── assets/
│   ├── css/style.css       <-- Custom style & styling print
│   └── js/simulation.js    <-- Engine visualisasi simulasi & payload
├── includes/
│   ├── header.php          <-- Navigasi atas & session badge
│   └── footer.php          <-- Identitas ITL Trisakti & nama anggota
├── index.php               <-- Landing Page Konsultan & Rekomendasi
├── login.php               <-- Halaman login praktis (1-Click Login)
├── logout.php              <-- Logout session
├── dashboard.php           <-- Dashboard KPI & status 7 tahapan
├── simulation.php          <-- Laboratorium Simulasi Interaktif 7 Tahap (PoC)
├── database_viewer.php     <-- Penjelajah 7 tabel database MySQL
├── blueprint.php           <-- Dokumen Rekomendasi Konsultansi & SRS
├── schema.sql              <-- Skema DDL & DML MySQL
└── PANDUAN_SISTEM_DAN_PROMPTING.md <-- File panduan ini
```

---

## 7. PANDUAN MENGATASI MASALAH (TROUBLESHOOTING)

1. **Website tidak bisa dibuka di browser (`http://localhost/Air_Cargo_Intermodal_Terminal/`):**
   * Buka aplikasi **XAMPP Control Panel**.
   * Pastikan modul **Apache** dan **MySQL** berstatus warna hijau (*Running*).
2. **Data simulasi berantakan setelah banyak dicoba:**
   * Cukup klik tombol **"Reset Demo"** di bilah navigasi kanan atas, atau buka URL: `http://localhost/Air_Cargo_Intermodal_Terminal/api/reset.php` (via POST / tombol web).
3. **Database hilang atau corrupt:**
   * Buka CMD/Terminal XAMPP dan jalankan:
     `"C:\xampp\mysql\bin\mysql.exe" -u root -e "source c:/xampp/htdocs/Air_Cargo_Intermodal_Terminal/schema.sql"`
4. **Login lupa password:**
   * Di halaman `login.php`, cukup klik salah satu tombol biru: **"Masuk sebagai Tim Konsultan"** atau **"Masuk sebagai Pihak Klien"** (1-Click langsung masuk tanpa perlu mengetik).

---
*Buku Panduan ini disusun untuk menjaga keselarasan pengembangan proyek oleh seluruh anggota Tim Konsultan Kelompok 1.*
