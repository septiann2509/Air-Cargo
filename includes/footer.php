<?php
// includes/footer.php
?>
    </main>

    <!-- Footer InJourney Airports Design System -->
    <footer class="bg-footer-gradient text-white mt-20 pt-14 pb-8 text-xs select-none shadow-2xl border-t border-teal-400/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 pb-8 border-b border-white/20">
                <!-- Col 1: Context & Brand -->
                <div class="space-y-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-xl bg-white text-[#087F8A] font-extrabold flex items-center justify-center text-sm shadow-md">
                            AC
                        </div>
                        <div>
                            <span class="font-extrabold text-sm text-white tracking-tight block">PT Aerospace Consultant</span>
                            <span class="text-[10.5px] text-teal-200 block font-medium">Air Cargo &amp; Intermodal Advisory</span>
                        </div>
                    </div>
                    <p class="text-white/80 text-[11px] leading-relaxed text-justify">
                        Proyek konsultansi rancang bangun sistem otomasi terminal kargo bandara dan integrasi logistik multimoda di bawah bimbingan ITL Trisakti.
                    </p>
                    <div class="pt-1 space-y-1 text-[11px] text-teal-100">
                        <p><i class="fa-solid fa-graduation-cap mr-1.5 text-teal-300"></i> ITL Trisakti &bull; S1 Logistik</p>
                        <p><i class="fa-solid fa-user-tie mr-1.5 text-teal-300"></i> Dosen: <strong>Dr. Tigor Franky, S.T., M.T.</strong></p>
                    </div>
                </div>

                <!-- Col 2: Tim Konsultan (NO NIM) -->
                <div>
                    <h4 class="text-white font-bold text-xs mb-3 uppercase tracking-wider text-teal-200 flex items-center">
                        <i class="fa-solid fa-users text-teal-300 mr-2"></i> Tim Konsultan
                    </h4>
                    <ul class="space-y-1.5 text-[11px]">
                        <li class="flex items-center justify-between bg-black/20 backdrop-blur-sm px-2.5 py-1.5 rounded-lg border border-white/10">
                            <span class="text-white font-medium">Raden Panji Atha Firjatullah</span>
                            <span class="text-teal-200 font-semibold text-[10px]">Lead Architect</span>
                        </li>
                        <li class="flex items-center justify-between bg-black/20 backdrop-blur-sm px-2.5 py-1.5 rounded-lg border border-white/10">
                            <span class="text-white font-medium">Muhammad Fathir Septianto</span>
                            <span class="text-teal-200 font-semibold text-[10px]">Data Integration</span>
                        </li>
                        <li class="flex items-center justify-between bg-black/20 backdrop-blur-sm px-2.5 py-1.5 rounded-lg border border-white/10">
                            <span class="text-white font-medium">Riepka Tiara</span>
                            <span class="text-teal-200 font-semibold text-[10px]">Software Process</span>
                        </li>
                        <li class="flex items-center justify-between bg-black/20 backdrop-blur-sm px-2.5 py-1.5 rounded-lg border border-white/10">
                            <span class="text-white font-medium">Nessa Amanda Ghassani</span>
                            <span class="text-teal-200 font-semibold text-[10px]">Hardware &amp; QA</span>
                        </li>
                    </ul>
                </div>

                <!-- Col 3: Standar & Ekosistem -->
                <div>
                    <h4 class="text-white font-bold text-xs mb-3 uppercase tracking-wider text-teal-200 flex items-center">
                        <i class="fa-solid fa-cubes-stacked text-teal-300 mr-2"></i> Standar Operasi
                    </h4>
                    <ul class="space-y-2 text-[11px] text-white/85">
                        <li class="flex items-center space-x-2">
                            <i class="fa-solid fa-check text-teal-300 text-[10px]"></i>
                            <span>GS1 SSCC 18-Digit Barcode</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i class="fa-solid fa-check text-teal-300 text-[10px]"></i>
                            <span>IATA Cargo-XML &amp; e-AWB Standard</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i class="fa-solid fa-check text-teal-300 text-[10px]"></i>
                            <span>ICAO Security CSD Digital Protocol</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i class="fa-solid fa-check text-teal-300 text-[10px]"></i>
                            <span>Truck Appointment System (TAS) Slot</span>
                        </li>
                    </ul>
                </div>

                <!-- Col 4: Quick Action & Kontak -->
                <div class="space-y-3">
                    <h4 class="text-white font-bold text-xs uppercase tracking-wider text-teal-200 flex items-center">
                        <i class="fa-solid fa-headset text-teal-300 mr-2"></i> Hub InJourney Airports
                    </h4>
                    <p class="text-white/80 text-[11px] leading-relaxed">
                        Bandar Udara Internasional Soekarno-Hatta<br>
                        Call Center Layanan: <strong class="text-amber-300 font-extrabold text-sm">172</strong>
                    </p>
                    <div class="flex flex-col space-y-2 pt-2">
                        <a href="blueprint.php" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white font-semibold text-[11px] border border-white/20 transition-all text-center flex items-center justify-center">
                            <i class="fa-solid fa-file-contract mr-1.5 text-teal-300"></i> Blueprint Dokumen SRS
                        </a>
                        <button onclick="confirmResetData()" class="px-3 py-1.5 rounded-lg bg-rose-900/40 hover:bg-rose-900/60 text-rose-200 font-semibold text-[11px] border border-rose-400/30 transition-all text-center flex items-center justify-center">
                            <i class="fa-solid fa-rotate-left mr-1.5 text-rose-300"></i> Reset Database Demo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bottom Row -->
            <div class="flex flex-col sm:flex-row items-center justify-between text-[11px] text-white/70">
                <p>&copy; <?= date('Y') ?> <strong class="text-white">PT Aerospace Consultant</strong> &bull; Hak Cipta Dilindungi.</p>
                <div class="mt-2 sm:mt-0 flex items-center space-x-3 font-mono text-[10px] text-teal-100">
                    <span>Stack: PHP 8.2 &bull; MySQL 8 &bull; GS1 SSCC &bull; IATA e-AWB</span>
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
                cancelButtonColor: '#475569',
                confirmButtonText: 'Ya, Reset Data Demo',
                cancelButtonText: 'Batal',
                background: '#0D1C42',
                color: '#fff',
                customClass: {
                    popup: 'border border-teal-500/30 shadow-2xl'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Mereset Database...',
                        text: 'Mengembalikan skema dan data benih awal.',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); },
                        background: '#0D1C42',
                        color: '#fff',
                        customClass: {
                            popup: 'border border-teal-500/30 shadow-2xl'
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
                                    background: '#0D1C42',
                                    color: '#fff',
                                    confirmButtonColor: '#087F8A'
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Gagal',
                                    text: data.message,
                                    icon: 'error',
                                    background: '#0D1C42',
                                    color: '#fff',
                                    confirmButtonColor: '#087F8A'
                                });
                            }
                        })
                        .catch(err => {
                            Swal.fire({
                                title: 'Error',
                                text: 'Gagal memproses reset: ' + err,
                                icon: 'error',
                                background: '#0D1C42',
                                color: '#fff'
                            });
                        });
                }
            });
        }
    </script>
</body>
</html>
