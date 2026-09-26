<?php
// includes/footer.php
?>
    </main>

    <!-- Footer -->
    <footer class="glass-panel border-t border-slate-800 mt-16 py-8 text-slate-400 text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-6">
                <!-- Col 1: Context -->
                <div>
                    <div class="flex items-center space-x-2 text-white font-bold text-sm mb-2">
                        <i class="fa-solid fa-graduation-cap text-sky-400"></i>
                        <span>Institut Transportasi & Logistik (ITL) Trisakti</span>
                    </div>
                    <p class="text-slate-400 text-[11px] leading-relaxed mb-2">
                        Mata Kuliah: <strong>Teknologi dan Perangkat Lunak Logistik</strong><br>
                        Dosen Pengampu: <strong>Dr. Tigor Franky, S.T., M.T.</strong><br>
                        Fakultas Sistem Transportasi dan Logistik — Program Studi S1 Logistik
                    </p>
                    <span class="inline-block px-2 py-0.5 rounded bg-sky-950 text-sky-300 border border-sky-800 text-[10px] font-semibold">
                        Topik 6: Air Cargo & Intermodal Terminal
                    </span>
                </div>

                <!-- Col 2: Tim Konsultan Kelompok 1 -->
                <div>
                    <h4 class="text-white font-semibold text-xs mb-3 flex items-center">
                        <i class="fa-solid fa-users text-indigo-400 mr-2"></i> Tim Konsultan Kelompok 1
                    </h4>
                    <ul class="space-y-1.5 text-[11px]">
                        <li class="flex items-center justify-between bg-slate-900/60 px-2 py-1 rounded border border-slate-800">
                            <span class="text-slate-200">Raden Panji Atha Firjatullah</span>
                            <span class="text-sky-400 font-mono text-[10px]">22D507001024</span>
                        </li>
                        <li class="flex items-center justify-between bg-slate-900/60 px-2 py-1 rounded border border-slate-800">
                            <span class="text-slate-200">Muhammad Fathir Septianto</span>
                            <span class="text-sky-400 font-mono text-[10px]">24D507001002</span>
                        </li>
                        <li class="flex items-center justify-between bg-slate-900/60 px-2 py-1 rounded border border-slate-800">
                            <span class="text-slate-200">Riepka Tiara</span>
                            <span class="text-sky-400 font-mono text-[10px]">24D507001016</span>
                        </li>
                        <li class="flex items-center justify-between bg-slate-900/60 px-2 py-1 rounded border border-slate-800">
                            <span class="text-slate-200">Nessa Amanda Ghassani</span>
                            <span class="text-sky-400 font-mono text-[10px]">24D507001025</span>
                        </li>
                    </ul>
                </div>

                <!-- Col 3: Role Konsultan & Quick Links -->
                <div>
                    <h4 class="text-white font-semibold text-xs mb-2 flex items-center">
                        <i class="fa-solid fa-briefcase text-emerald-400 mr-2"></i> Peran Penasihat Konsultansi
                    </h4>
                    <p class="text-slate-400 text-[11px] leading-relaxed mb-3">
                        Portal ini dirancang sebagai platform rekomendasi & pembuktian konsep (PoC) bagi pengelola terminal kargo bandara dalam mengotomatiskan alur kargo udara dan intermodal.
                    </p>
                    <div class="flex space-x-2">
                        <a href="blueprint.php" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-sky-300 text-[11px] border border-slate-700">
                            <i class="fa-solid fa-file-lines mr-1"></i> Baca Blueprint SRS
                        </a>
                        <a href="simulation.php" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-emerald-300 text-[11px] border border-slate-700">
                            <i class="fa-solid fa-play mr-1"></i> Demo Simulasi
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-800 pt-4 flex flex-col sm:flex-row items-center justify-between text-[11px] text-slate-500">
                <p>&copy; 2026 Tim Konsultan Kelompok 1 — Air Cargo & Intermodal Terminal Advisory System.</p>
                <p class="mt-2 sm:mt-0 font-mono text-[10px] text-slate-400">
                    Stack: PHP 8.2 &bull; MySQL 8 &bull; GS1 SSCC Standard &bull; IATA e-AWB
                </p>
            </div>
        </div>
    </footer>

    <!-- Global JS Script -->
    <script>
        function confirmResetData() {
            Swal.fire({
                title: 'Reset Data Simulasi?',
                text: 'Semua tabel akan dikembalikan ke kondisi awal demonstrasi.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0284c7',
                cancelButtonColor: '#475569',
                confirmButtonText: 'Ya, Reset Data Demo',
                cancelButtonText: 'Batal',
                background: '#111e38',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Mereset Database...',
                        text: 'Mengembalikan skema dan data benih awal.',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); },
                        background: '#111e38',
                        color: '#fff'
                    });

                    fetch('api/reset.php', { method: 'POST' })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Berhasil!',
                                    text: data.message,
                                    icon: 'success',
                                    background: '#111e38',
                                    color: '#fff'
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Gagal',
                                    text: data.message,
                                    icon: 'error',
                                    background: '#111e38',
                                    color: '#fff'
                                });
                            }
                        })
                        .catch(err => {
                            Swal.fire('Error', 'Gagal memproses reset: ' + err, 'error');
                        });
                }
            });
        }
    </script>
</body>
</html>
