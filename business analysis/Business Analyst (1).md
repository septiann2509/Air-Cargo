AIR CARGO &
INTERMODAL
TERMINAL
AIR CARGO &
INTERMODAL
TERMINAL
KELOMPOK 1KELOMPOK 1
NAMA
ANGGOTA
KELOMPOK
RADEN PANJI ATHA FIRJARULLAH
22D507001024
MUHAMMAD FATHIR SEPTIANTO
24D507001002
NESSA AMANDA GHASSANI
24D507001025
RIEPKA TIARA
24D507001016
```
CARGO MANAGEMENT SOFTWARE (CMS) &
```
```
MODUL WMS (INBOUND/OUTBOUND)
```
Hardware Terkait: High-Speed Barcode/RFID Scanner
```
Fitur Software: One-Scan Receiving (ASN Matching) & Directed Putaway
```
```
Pendekatan BA (Business Rule):
```
1.Sistem wajib memverifikasi apakah data SSCC/GS1 dari pemindai
```
(scanner) cocok dengan dokumen Manifes Kedatangan (Advance
```
```
Shipping Notice / ASN).
```
2.Jika kuantitas fisik melebihi data manifes, pemicu status Over-
receipt Alert.
```
3.Sistem mengalokasikan lokasi simpan sementara (binnage)
```
```
berdasarkan matriks dimensi dan jenis barang (Dangerous Goods
```
```
harus dipisah).
```
```
Data Output / Dampak: Lokasi simpan optimal tercatat; status stok
```
```
menjadi Received; visibilitas kargo transit 100%.
```
```
AVIATION SECURITY MANAGEMENT SYSTEM (AVSEC SYSTEM)
```
Hardware Terkait: Dual-View / High-Energy Air Cargo X-Ray Scanner
Fitur Software: Security Screening Logging & Auto-Quarantine
```
Pendekatan BA (Business Rule):
```
```
1.Sistem otomatis mengunci (Blocked/Quarantine) status kargo di dalam
```
database jika hasil pindaian X-Ray di-flag sebagai SUSPECT oleh
petugas AVSEC.
2.Kargo dengan status Blocked tidak boleh teralokasi untuk proses
```
konsolidasi pemuatan pesawat (Build-Up).
```
3.Hanya kargo berstatus CLEARED yang dapat diterbitkan Consignment
```
Security Declaration (CSD) digital.
```
```
Data Output / Dampak: Status stok terkunci (Blocked) atau lolos (Cleared);
```
Penerbitan e-CSD.
```
Hardware Terkait: Smart ULD Weighing Station (Timbangan Lantai ULD) & Automatic
```
```
Dimensioning Weighing System (DWS)
```
Fitur Software: 3D Load Planning, Center of Gravity Calculation, & DWS Integration
```
Pendekatan BA (Business Rule):
```
```
1.Sistem harus mencatat data dimensi (Volumetrik), berat (Scale), dan pindaian
```
secara bersamaan dalam kurun waktu < 500 ms dari DWS.
2.Sistem menghitung otomatis tata letak kubikasi barang dalam ULD untuk
```
memaksimalkan cubic utilization (faktor keterisian).
```
```
3.Sistem memverifikasi batas toleransi berat (floor load limit) dan mendistribusikan
```
beban ULD agar Center of Gravity pesawat tetap dalam batas aman
penerbangan.
```
Data Output / Dampak: Data Volumetrik & Berat tercatat; Rencana pemuatan
```
```
(Loadsheet) yang aman dan optimal: Peringatan Overweight/Imbalance Alert
```
WEIGHT & BALANCE SYSTEM / LOAD PLANNING SOFTWARE
```
INTERMODAL TRANSPORT MANAGEMENT SYSTEM (TMS) &
```
```
TRUCK APPOINTMENT SYSTEM (TAS)
```
```
Hardware Terkait: Gate Control / Boom Gate (terintegrasi YMS/TAS)
```
Fitur Software: Dock Door Scheduling, Gate Management, & Demurrage Alert
```
Pendekatan BA (Business Rule):
```
1.Penjadwalan time-slot kedatangan truk untuk menghindari penumpukan armada di area pelataran
```
(Yard).
```
2.Sistem melakukan pencatatan otomatis waktu masuk/keluar di pintu gerbang dan memberikan
```
notifikasi peringatan jika truk melebihi batas waktu tunggu (dwelling time / demurrage).
```
3.Perhitungan otomatis biaya keterlambatan berdasarkan matriks waktu dari vendor armada.
```
Data Output / Dampak: Alokasi jadwal dock (Dock Assigned): Peringatan Dwelling Time; Log GPS / Waktu
```
Gate In & Out.
Modul dan Hardware
Data Input
```
(Source)
```
Proses Validasi Sistem
```
(Business Rule)
```
```
Data Output (Target)
```
TMS/TAS
```
(Gate Control)
```
Data Waktu Masuk
Truk & Nomor Pelat
Pencatatan otomatis waktu
```
masuk/keluar gerbang; Cek
```
batas dwelling time.
Jadwal Dock Assigned &
Demurrage Alert
CMS/WMS
```
(Barcode Scanner)
```
Data Barcode SSCC
GS1 dari Scanner
Validasi kecocokan dengan
```
dokumen manifes (ASN
```
```
Matching); Cek Over-receipt
```
Status Stok: Received & Lokasi
Putaway.
W&B System
```
(DWS / Smart Scale)
```
```
Dimensi (CubiScan)
```
& Berat Fisik
```
(Timbangan)
```
Cek toleransi selisih berat
```
(lebih dari sama dengan 2%);
```
Kalkulasi tata letak kubikasi
```
ULD (Cubic Utilization).
```
Loadsheet Pesawat &
Peringatan Overweight
MATRIKS INTEGRASI DATA FITUR
```
PERANCANGAN PENANGANAN ANOMALI (EXCEPTION HANDLING)
```
```
Dalam operasional nyata terminal kargo, banyak hal tidak berjalan normal. BA harus membuat aturan bisnis (business rule)
```
jika terjadi error.
```
Skenario Anomali 1: Barcode ULD / Kargo Tidak Terbaca (No-Read)
```
1. Kondisi: High-Speed Scanner gagal memindai label SSCC saat barang berjalan di conveyor.
2. Business Rule (BA): WCS memerintahkan diverter membelokkan paket ke jalur Reject Lane dan memicu Alert ke Handheld
PDA operator untuk melakukan pindaian manual.
Skenario Anomali 2: Keterlambatan Truk Intermodal
1.Kondisi: Truk pembawa kargo mengalami mogok di jalan menuju terminal bandara.
```
2.Business Rule (BA): Sistem TMS memantau GPS dan pemicu notifikasi otomatis (Exception Alert) ke tim Customer Service
```
```
dan modul TAS (YMS) agar time-slot dock bisa dialokasikan untuk truk lain yang sudah antre.
```
1. Booking slot waktu kargo
Pihak freight forwarder atau
agen pengirim memesan slot
```
waktu kedatangan truk (Time-
```
```
Slot Booking System) di terminal
```
kargo.
2. Pre-alert & dokumen kargo
```
Pengiriman pemberitahuan awal (pre-
```
```
alert) beserta kelengkapan dokumen
```
pengiriman kargo secara digital
```
(seperti e-AWB / Air Waybill)
```
3. Truk tiba di terminal kargo
Armada truk pengangkut
barang sampai di area terminal
```
kargo (landside) sesuai dengan
```
slot waktu yang telah dipesan
sebelumnya.
4. Check-in & verifikasi dokumen
Petugas melakukan pendaftaran
```
masuk (check-in) truk serta
```
mengecek kesesuaian dokumen
fisik/sistem dengan kargo yang
dibawa
5. Penimbangan & pemeriksaan
keamanan
Barang dibongkar dari truk lalu
```
ditimbang (weighing) untuk
```
```
menentukan berat aktual (akurasi
```
```
Weight & Balance), serta diperiksa
```
melalui mesin X-Ray / AVSEC
```
(security screening).
```
6. Bongkar muat ke ULD / pallet
Proses penataan dan
```
penyusunan kargo (build-up
```
```
process) ke dalam wadah khusus
```
penerbangan, baik berupa
```
kontainer udara (ULD / Unit Load
```
```
Device) maupun palet kargo.
```
7. Serah terima ke maskapai /
ground handling
ULD atau palet kargo yang sudah
selesai disusun dan disegel
diserahterimakan kepada pihak
maskapai penerbangan atau tim
ground handling terminal.
8. Penerbangan / koneksi intermodal
Kargo dipindahkan ke sisi udara
```
(airside) untuk dimuat (loading) ke
```
dalam lambung pesawat
kargo/penumpang, atau disambungkan
ke moda transportasi intermodal
berikutnya untuk dikirim ke tujuan akhir.
FLOWCHART
THANK
# YOU