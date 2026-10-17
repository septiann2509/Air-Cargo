<?php
// login.php — InJourney Airports Design System dengan Motif Batik Kawung
require_once __DIR__ . '/config/database.php';

$error = '';
$message = '';

// Handle quick-login GET parameter or form submit
if (isset($_GET['quick'])) {
    $role = $_GET['quick'];
    if ($role === 'consultant') {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'consultant';
        $_SESSION['full_name'] = 'PT Aerospace Consultant';
        $_SESSION['role'] = 'consultant';
        $_SESSION['organization'] = 'PT Aerospace Consultant Advisory';
        header("Location: dashboard.php");
        exit;
    } elseif ($role === 'client') {
        $_SESSION['user_id'] = 2;
        $_SESSION['username'] = 'client';
        $_SESSION['full_name'] = 'Direktur Operasional Terminal Kargo';
        $_SESSION['role'] = 'client';
        $_SESSION['organization'] = 'PT Bandara Cargo Terminal Internasional';
        header("Location: dashboard.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi!';
    } else {
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Validasi: demo password 'password123' atau password_verify atau bypass khusus demo
        if ($user && ($password === 'password123' || password_verify($password, $user['password']))) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['organization'] = $user['organization'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'Kredensial tidak valid. Silakan gunakan tombol 1-Click Login di bawah untuk kemudahan demo!';
        }
    }
}

$pageTitle = 'Masuk Portal Konsultansi';
$bodyClass = 'bg-batik-canvas text-slate-800 min-h-screen relative overflow-x-hidden';
include __DIR__ . '/includes/header.php';
?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     LOGIN PORTAL — MOTIF BATIK KAWUNG & INJOURNEY COLOR PALETTE
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="relative min-h-[calc(100vh-16rem)] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 overflow-hidden">

    <!-- Ornamen Batik Sudut Resmi InJourney (Top-Right) -->
    <img src="assets/img/injourney/pattern-green-BTZ3yksm.png" 
         alt="Batik Kawung Ornament" 
         class="absolute -top-12 -right-12 w-72 sm:w-96 lg:w-[480px] pointer-events-none opacity-25 mix-blend-screen select-none transform rotate-0 transition-opacity duration-700">

    <!-- Ornamen Batik Sudut Resmi InJourney (Bottom-Left) -->
    <img src="assets/img/injourney/pattern-green-BTZ3yksm.png" 
         alt="Batik Kawung Ornament" 
         class="absolute -bottom-16 -left-16 w-72 sm:w-96 lg:w-[480px] pointer-events-none opacity-20 mix-blend-screen select-none transform rotate-180 transition-opacity duration-700">

    <!-- Floating Glow Orbs (InJourney Teal & Deep Cyan) -->
    <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-[#0CA1AF]/15 rounded-full blur-3xl pointer-events-none animate-pulse"></div>
    <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-[#087F8A]/20 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Centered Login Card -->
    <div class="w-full max-w-lg relative z-10">
        
        <!-- Luxury Card Container with InJourney Batik Styling -->
        <div class="bg-white/95 backdrop-blur-2xl rounded-3xl border border-white/80 shadow-[0_25px_60px_-15px_rgba(1,77,84,0.5)] overflow-hidden transition-all duration-300 hover:shadow-[0_30px_70px_-10px_rgba(1,77,84,0.65)]">
            
            <!-- Card Header: Batik Banner InJourney Gradient -->
            <div class="relative bg-gradient-to-r from-[#014D54] via-[#087F8A] to-[#0CA1AF] text-white p-6 sm:p-7 overflow-hidden border-b border-teal-400/30">
                <!-- Watermark Batik Kawung Pattern di Header -->
                <div class="absolute inset-0 opacity-20 batik-overlay-dark pointer-events-none"></div>
                
                <div class="relative z-10 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <!-- Logo Kawung Resmi -->
                        <div class="w-12 h-12 rounded-2xl bg-white p-2 flex items-center justify-center shadow-lg shadow-black/20 flex-shrink-0 group-hover:scale-105 transition-transform border border-white/50">
                            <img src="assets/img/injourney/kawung-logo-side-CktPU2GK.png" alt="InJourney Kawung" class="w-full h-full object-contain">
                        </div>
                        <div class="leading-tight">
                            <div class="flex items-center gap-1.5">
                                <span class="font-extrabold text-base sm:text-lg tracking-tight text-white"><?= $companyName ?></span>
                                <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-white/20 text-teal-200 font-extrabold uppercase tracking-wider border border-white/30 backdrop-blur-sm">Advisory</span>
                            </div>
                            <span class="text-[10.5px] uppercase tracking-widest text-teal-100/90 font-medium block mt-0.5"><?= $companyTagline ?></span>
                        </div>
                    </div>

                    <!-- Cultural Batik Badge -->
                    <div class="hidden sm:flex flex-col items-end">
                        <span class="text-[9px] uppercase tracking-widest font-extrabold text-teal-200 bg-black/25 px-2.5 py-1 rounded-full border border-teal-300/30">
                            <i class="fa-solid fa-certificate text-amber-300 mr-1"></i> Motif Kawung
                        </span>
                    </div>
                </div>
            </div>

            <!-- Card Body Content -->
            <div class="p-6 sm:p-8 space-y-6">
                
                <!-- Title & Ecosystem Subtitle -->
                <div class="text-center space-y-1.5">
                    <h1 class="text-2xl font-extrabold text-[#0D1C42] tracking-tight">Portal Konsultansi Kargo</h1>
                    <p class="text-xs text-slate-500 font-medium">
                        Arsitektur Digital &bull; standardisasi GS1 SSCC 18D &bull; ICAO CSD
                    </p>
                    <div class="pt-1 flex items-center justify-center gap-2">
                        <span class="inline-block w-8 h-[2px] bg-gradient-to-r from-transparent to-[#0CA1AF]"></span>
                        <span class="text-[10px] uppercase font-bold tracking-widest text-[#087F8A]">InJourney Airports Ecosystem</span>
                        <span class="inline-block w-8 h-[2px] bg-gradient-to-l from-transparent to-[#0CA1AF]"></span>
                    </div>
                </div>

                <!-- Error Alert Box -->
                <?php if ($error): ?>
                    <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center shadow-sm animate-shake">
                        <i class="fa-solid fa-triangle-exclamation mr-2.5 text-base text-rose-500 flex-shrink-0"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- 1-Click Fast Login Section (Batik-Styled Role Cards) -->
                <div class="space-y-3">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider text-center flex items-center justify-center">
                        <span class="border-t border-gray-200 flex-grow mr-2"></span>
                        <span class="text-[#087F8A] flex items-center gap-1.5">
                            <i class="fa-solid fa-bolt text-amber-500 text-xs"></i>
                            Pilih Mode Akses Cepat (1-Click Demo)
                        </span>
                        <span class="border-t border-gray-200 flex-grow ml-2"></span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Consultant Access -->
                        <a href="login.php?quick=consultant" class="group relative flex flex-col justify-between p-4 rounded-2xl bg-gradient-to-b from-teal-50/60 to-white border border-teal-200/80 hover:border-[#0CA1AF] hover:shadow-lg hover:shadow-teal-900/10 transition-all transform hover:-translate-y-0.5">
                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <div class="w-9 h-9 rounded-xl bg-[#087F8A] text-white flex items-center justify-center text-sm shadow-md group-hover:scale-105 transition-transform">
                                        <i class="fa-solid fa-user-tie"></i>
                                    </div>
                                    <span class="text-[9px] font-extrabold px-2 py-0.5 rounded-full bg-teal-100 text-[#087F8A] uppercase tracking-wider">
                                        Advisory
                                    </span>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-[#0D1C42] group-hover:text-[#087F8A] transition-colors">Tim Konsultan</h4>
                                    <p class="text-[10.5px] text-slate-500 font-medium leading-tight mt-0.5">PT Aerospace Consultant</p>
                                </div>
                            </div>
                            <div class="pt-3 mt-3 border-t border-teal-100/80 flex items-center justify-between text-[11px] font-bold text-[#087F8A]">
                                <span>Akses Konsultan</span>
                                <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
                            </div>
                        </a>

                        <!-- Client Access -->
                        <a href="login.php?quick=client" class="group relative flex flex-col justify-between p-4 rounded-2xl bg-gradient-to-b from-slate-50 to-white border border-gray-200 hover:border-[#014D54] hover:shadow-lg hover:shadow-teal-900/10 transition-all transform hover:-translate-y-0.5">
                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <div class="w-9 h-9 rounded-xl bg-[#014D54] text-white flex items-center justify-center text-sm shadow-md group-hover:scale-105 transition-transform">
                                        <i class="fa-solid fa-building-user"></i>
                                    </div>
                                    <span class="text-[9px] font-extrabold px-2 py-0.5 rounded-full bg-slate-200 text-[#014D54] uppercase tracking-wider">
                                        Client
                                    </span>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-[#0D1C42] group-hover:text-[#014D54] transition-colors">Pihak Klien</h4>
                                    <p class="text-[10.5px] text-slate-500 font-medium leading-tight mt-0.5">Direktur Operasional Terminal</p>
                                </div>
                            </div>
                            <div class="pt-3 mt-3 border-t border-gray-100 flex items-center justify-between text-[11px] font-bold text-[#014D54]">
                                <span>Akses Klien</span>
                                <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Divider: Manual Login -->
                <div class="relative my-4 text-center">
                    <span class="bg-white px-3 text-[11px] text-slate-400 relative z-10 font-bold uppercase tracking-wider">atau masuk dengan akun manual</span>
                    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                </div>

                <!-- Manual Form -->
                <form method="POST" action="login.php" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                            <span>Nama Pengguna (Username)</span>
                            <span class="text-[10px] text-slate-400 font-normal">cth: consultant / client</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-xs">
                                <i class="fa-solid fa-user"></i>
                            </span>
                            <input type="text" name="username" value="consultant" required 
                                   class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-gray-200 rounded-xl text-xs text-[#0D1C42] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] font-medium transition-all shadow-inner">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                            <span>Kata Sandi (Password)</span>
                            <span class="text-[10px] text-slate-400 font-normal">default: password123</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-xs">
                                <i class="fa-solid fa-key"></i>
                            </span>
                            <input type="password" name="password" value="password123" required 
                                   class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-gray-200 rounded-xl text-xs text-[#0D1C42] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] font-medium transition-all shadow-inner">
                        </div>
                    </div>

                    <!-- Submit Button with InJourney Gradient -->
                    <button type="submit" class="w-full py-3 px-4 bg-gradient-to-r from-[#04AFBF] via-[#087F8A] to-[#014D54] hover:from-[#0CA1AF] hover:via-[#04AFBF] hover:to-[#087F8A] text-white font-bold rounded-xl text-xs shadow-lg shadow-teal-500/25 transition-all flex items-center justify-center transform hover:-translate-y-0.5">
                        <i class="fa-solid fa-right-to-bracket mr-2 text-xs"></i>
                        <span>Masuk ke Sistem Manajemen Kargo</span>
                    </button>
                </form>

                <!-- Cultural Philosophy Note (Batik Kawung) -->
                <div class="pt-4 border-t border-gray-100 text-center space-y-2">
                    <div class="inline-flex items-center gap-2 text-slate-400 text-[10px] font-semibold">
                        <span class="w-3.5 h-3.5 rounded-full bg-teal-50 border border-teal-300 inline-flex items-center justify-center text-[8px] text-[#087F8A] font-bold">
                            <i class="fa-solid fa-certificate"></i>
                        </span>
                        <span>Filosofi Batik Kawung: Keseimbangan 4 Penjuru &amp; Ketepatan Alur Aviasi</span>
                    </div>
                    <p class="text-[10.5px] text-slate-400 leading-relaxed max-w-sm mx-auto">
                        Mencerminkan integrasi 4 pilar arsitektur kargo udara: TMS Gerbang, CMS e-AWB, Screening AVSEC CSD, dan Penimbangan Presisi ULD.
                    </p>
                    <div class="pt-1">
                        <a href="index.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8A] hover:text-[#0CA1AF] transition-colors">
                            <i class="fa-solid fa-arrow-left text-[10px]"></i>
                            <span>Kembali ke Halaman Beranda</span>
                        </a>
                    </div>
                </div>

            </div>

        </div>

    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
