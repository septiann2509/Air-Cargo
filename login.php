<?php
// login.php — InJourney Airports Design System
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
$bodyClass = 'bg-[#F8FAFC] text-slate-800';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-md mx-auto px-4 py-16 sm:py-20">
    <!-- Card Container (InJourney White Card) -->
    <div class="bg-white rounded-3xl p-8 sm:p-10 border border-gray-200/90 shadow-xl relative overflow-hidden">
        
        <!-- Ambient decorative blur -->
        <div class="absolute -top-12 -right-12 w-36 h-36 bg-[#0CA1AF]/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute -bottom-12 -left-12 w-36 h-36 bg-[#087F8A]/10 rounded-full blur-2xl pointer-events-none"></div>

        <div class="text-center mb-8 relative z-10">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-[#0CA1AF] to-[#014D54] items-center justify-center shadow-lg shadow-teal-500/25 mb-3 border border-teal-300/30">
                <i class="fa-solid fa-lock text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-black text-[#0D1C42] tracking-tight">Portal Konsultansi Kargo</h1>
            <p class="text-xs text-slate-500 mt-1">
                Autentikasi Cepat Akses Dashboard &amp; Demo Simulasi PoC
            </p>
        </div>

        <?php if ($error): ?>
            <div class="mb-5 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center">
                <i class="fa-solid fa-triangle-exclamation mr-2 text-sm text-rose-500"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- 1-Click Fast Login Section (Tidak Mempersulit Klien) -->
        <div class="mb-6 relative z-10">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-3 text-center flex items-center justify-center">
                <span class="border-t border-gray-200 flex-grow mr-2"></span>
                <span>Pilih Mode Akses Cepat (1-Click)</span>
                <span class="border-t border-gray-200 flex-grow ml-2"></span>
            </div>
            <div class="space-y-3">
                <a href="login.php?quick=consultant" class="group flex items-center justify-between p-3.5 rounded-xl bg-slate-50 border border-gray-200 hover:border-[#0CA1AF] hover:bg-teal-50/40 hover:shadow-md transition-all">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-lg bg-teal-50 text-[#087F8A] flex items-center justify-center text-base font-bold border border-teal-200 group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-user-tie"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-xs font-bold text-[#0D1C42] group-hover:text-[#087F8A] transition-colors">Masuk sebagai Tim Konsultan</p>
                            <p class="text-[11px] text-slate-500 font-medium">PT Aerospace Consultant (Advisory)</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-arrow-right text-xs text-[#087F8A] group-hover:translate-x-1 transition-transform"></i>
                </a>

                <a href="login.php?quick=client" class="group flex items-center justify-between p-3.5 rounded-xl bg-slate-50 border border-gray-200 hover:border-[#0CA1AF] hover:bg-teal-50/40 hover:shadow-md transition-all">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-lg bg-teal-50 text-[#014D54] flex items-center justify-center text-base font-bold border border-teal-200 group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-building-user"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-xs font-bold text-[#0D1C42] group-hover:text-[#014D54] transition-colors">Masuk sebagai Pihak Klien</p>
                            <p class="text-[11px] text-slate-500 font-medium">Direktur Operasional Terminal Kargo Bandara</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-arrow-right text-xs text-[#014D54] group-hover:translate-x-1 transition-transform"></i>
                </a>
            </div>
        </div>

        <div class="relative my-6 text-center">
            <span class="bg-white px-3 text-[11px] text-slate-400 relative z-10 font-bold uppercase tracking-wider">atau masuk dengan akun manual</span>
            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
        </div>

        <!-- Form Manual -->
        <form method="POST" action="login.php" class="space-y-4 relative z-10">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1.5">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-xs">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <input type="text" name="username" value="consultant" required class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-gray-200 rounded-xl text-xs text-[#0D1C42] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] font-medium transition-all shadow-inner">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1.5">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-xs">
                        <i class="fa-solid fa-key"></i>
                    </span>
                    <input type="password" name="password" value="password123" required class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-gray-200 rounded-xl text-xs text-[#0D1C42] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] font-medium transition-all shadow-inner">
                </div>
                <p class="text-[10.5px] text-slate-500 mt-1.5 font-medium">Default demo: username <code class="text-[#087F8A] font-bold">consultant</code>, password <code class="text-[#087F8A] font-bold">password123</code></p>
            </div>

            <button type="submit" class="w-full py-2.5 px-4 bg-gradient-to-r from-[#04AFBF] to-[#087F8A] hover:from-[#0CA1AF] hover:to-[#04AFBF] text-white font-bold rounded-xl text-xs shadow-lg shadow-teal-500/25 transition-all flex items-center justify-center transform hover:-translate-y-0.5">
                <span>Login ke Sistem</span>
                <i class="fa-solid fa-arrow-right-to-bracket ml-2"></i>
            </button>
        </form>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
