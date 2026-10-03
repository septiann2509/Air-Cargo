<?php
// includes/footer.php — 100% Sesuai Layout Footer InJourney & Profil Perusahaan PT Aerospace Consultant
?>
    </main>

    <!-- Footer InJourney Airports Design System -->
    <footer style="background: linear-gradient(to right, #0CA1AF, #087F8A, #014D54) !important;" class="bg-footer-gradient text-white mt-20 pt-14 pb-8 text-xs select-none shadow-2xl border-t border-teal-400/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
            
            <!-- 4-Column Footer Grid (Sesuai Referensi Gambar) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 pb-10 border-b border-white/20">
                
                <!-- Col 1: Profil Perusahaan PT Aerospace Consultant -->
                <div class="space-y-3.5">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-xl bg-white text-[#087F8A] font-extrabold flex items-center justify-center text-sm shadow-md">
                            AC
                        </div>
                        <div>
                            <span class="font-extrabold text-sm text-white tracking-tight block">PT Aerospace Consultant</span>
                            <span class="text-[10px] text-teal-200 block uppercase tracking-wider font-semibold">AIR CARGO &amp; INTERMODAL ADVISORY</span>
                        </div>
                    </div>
                    <p class="text-white/85 text-[11px] leading-relaxed text-justify">
                        Firma konsultan independen spesialis rancang bangun arsitektur teknologi kargo udara, integrasi alur logistik multimoda, dan implementasi otomasi alur fisik-digital bandara berstandar global.
                    </p>
                    <div class="pt-1 space-y-1.5 text-[11px] text-teal-100">
                        <p class="flex items-start">
                            <i class="fa-solid fa-location-dot mr-2 mt-0.5 text-teal-300 text-xs"></i>
                            <span>Treasury Tower Lt. 28, District 8 SCBD, Jakarta Selatan</span>
                        </p>
                        <p class="flex items-center">
                            <i class="fa-solid fa-envelope mr-2 text-teal-300 text-xs"></i>
                            <span>corporate@aerospace-consultant.co.id</span>
                        </p>
                        <p class="flex items-center">
                            <i class="fa-solid fa-phone mr-2 text-teal-300 text-xs"></i>
                            <span>+62 (21) 5088-2890 | Hotline Konsultansi</span>
                        </p>
                    </div>
                </div>

                <!-- Col 2: NAVIGASI UTAMA -->
                <div class="space-y-3">
                    <h4 class="font-bold text-xs uppercase tracking-wider text-teal-200">
                        NAVIGASI UTAMA
                    </h4>
                    <ul class="space-y-2 text-[11px] text-white/85">
                        <li><a href="index.php" class="hover:text-white transition-colors">Beranda Konsultansi</a></li>
                        <li><a href="dashboard.php" class="hover:text-white transition-colors">Dashboard Eksekutif</a></li>
                        <li><a href="simulation.php" class="hover:text-white transition-colors">Simulasi 7-Tahap PoC</a></li>
                        <li><a href="database_viewer.php" class="hover:text-white transition-colors">Database Viewer Live</a></li>
                    </ul>
                </div>

                <!-- Col 3: STANDAR & EKOSISTEM -->
                <div class="space-y-3">
                    <h4 class="font-bold text-xs uppercase tracking-wider text-teal-200">
                        STANDAR &amp; EKOSISTEM
                    </h4>
                    <ul class="space-y-2 text-[11px] text-white/85">
                        <li><span class="text-white/70">GS1 SSCC 18-Digit (AI 00)</span></li>
                        <li><span class="text-white/70">IATA Cargo Services (e-AWB)</span></li>
                        <li><span class="text-white/70">ICAO Security CSD Protocol</span></li>
                        <li><span class="text-white/70">OIML R76 Weighing Standard</span></li>
                        <li><span class="text-white/70">Truck Appointment System (TAS)</span></li>
                        <li><span class="text-white/70">REST API Integration Spec</span></li>
                    </ul>
                </div>

                <!-- Col 4: KONTAK & ALAMAT PERUSAHAAN (BUKAN BANDARA SOEKARNO) -->
                <div class="space-y-3">
                    <h4 class="font-bold text-xs uppercase tracking-wider text-teal-200">
                        KONTAK &amp; ALAMAT
                    </h4>
                    <div class="space-y-1.5 text-[11px] text-white/85">
                        <p class="font-extrabold text-white text-xs">Kantor Pusat PT Aerospace Consultant</p>
                        <p class="text-white/90">Treasury Tower Lt. 28, District 8 SCBD</p>
                        <p class="text-white/70 leading-relaxed">Jl. Jend. Sudirman Kav. 52-53, Senayan, Kebayoran Baru, Kota Jakarta Selatan, DKI Jakarta 12190</p>
                        <div class="pt-2">
                            <span class="block text-white/60 text-[10px] uppercase font-bold tracking-wider">LAYANAN CONTACT CENTER</span>
                            <span class="text-base font-extrabold text-amber-300 block my-0.5">(021) 5088-2890</span>
                            <span class="block text-[10px] text-white/70">(021) 5088-2890 / WA: 0811-9840-2890</span>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 pt-2">
                        <button onclick="confirmResetData()" class="px-3 py-1.5 rounded-lg bg-rose-900/40 hover:bg-rose-900/60 text-rose-200 font-semibold text-[10.5px] border border-rose-400/30 transition-all text-center flex items-center justify-center">
                            <i class="fa-solid fa-rotate-left mr-1.5 text-rose-300"></i> Reset Demo
                        </button>
                    </div>
                </div>

            </div>

            <!-- Bottom Row: Copyright & Navigasi -->
            <div class="flex flex-col sm:flex-row items-center justify-between text-[11px] text-white/70 gap-2">
                <p>&copy; <?= date('Y') ?> <strong class="text-white">PT Aerospace Consultant</strong>. Seluruh Hak Cipta Dilindungi &bull; Air Cargo &amp; Intermodal Terminal Advisory.</p>
                <div class="flex items-center gap-3 text-[11px]">
                    <span>Desain Terinspirasi dari InJourney Airports</span>
                </div>
            </div>

        </div>
    </footer>

    <!-- Global JS Script -->
    <script>
        function confirmResetData() {
            Swal.fire({
                title: 'Reset Data Simulasi?',
                text: 'Semua tabel operasional kargo akan dikembalikan ke kondisi awal demonstrasi.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#087F8A',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Ya, Reset Data Demo',
                cancelButtonText: 'Batal',
                background: '#ffffff',
                color: '#0D1C42',
                customClass: {
                    popup: 'rounded-2xl shadow-2xl border border-gray-200'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Mereset Database...',
                        text: 'Mengembalikan skema dan data benih awal.',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); },
                        background: '#ffffff',
                        color: '#0D1C42',
                        customClass: {
                            popup: 'rounded-2xl shadow-2xl border border-gray-200'
                        }
                    });

                    fetch('api/reset.php', { method: 'POST' })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Berhasil!',
                                    text: data.message,
                                    icon: 'success',
                                    background: '#ffffff',
                                    color: '#0D1C42',
                                    confirmButtonColor: '#087F8A',
                                    customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Gagal',
                                    text: data.message,
                                    icon: 'error',
                                    background: '#ffffff',
                                    color: '#0D1C42',
                                    confirmButtonColor: '#087F8A',
                                    customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
                                });
                            }
                        })
                        .catch(err => {
                            Swal.fire({
                                title: 'Error',
                                text: 'Gagal memproses reset: ' + err,
                                icon: 'error',
                                background: '#ffffff',
                                color: '#0D1C42',
                                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
                            });
                        });
                }
            });
        }
    </script>
</body>
</html>
