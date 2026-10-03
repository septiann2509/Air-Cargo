<?php
// login.php
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
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-md mx-auto px-4 py-16 sm:py-24">
    <!-- Card Container -->
    <div class="glass-panel rounded-2xl p-8 border border-slate-700/80 shadow-2xl relative overflow-hidden">
        
        <!-- Ambient decorative blur -->
        <div class="absolute -top-12 -right-12 w-40 h-40 bg-sky-500/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>

        <div class="text-center mb-8">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-sky-600 to-indigo-600 items-center justify-center shadow-lg shadow-sky-500/30 mb-4">
                <i class="fa-solid fa-lock text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Portal Konsultansi Kargo</h1>
            <p class="text-xs text-slate-400 mt-1.5">
                Autentikasi Cepat Akses Blueprint & Demo Simulasi PoC
            </p>
        </div>

        <?php if ($error): ?>
            <div class="mb-5 p-3 rounded-xl bg-rose-950/80 border border-rose-500/40 text-rose-300 text-xs flex items-center">
                <i class="fa-solid fa-triangle-exclamation mr-2 text-sm text-rose-400"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- 1-Click Fast Login Section (Tidak Mempersulit Klien) -->
        <div class="mb-6">
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2.5 text-center flex items-center justify-center">
                <span class="border-t border-slate-700 flex-grow mr-2"></span>
                <span>Pilih Mode Akses Cepat (1-Click)</span>
                <span class="border-t border-slate-700 flex-grow ml-2"></span>
            </div>
            <div class="space-y-2.5">
                <a href="login.php?quick=consultant" class="group flex items-center justify-between p-3 rounded-xl bg-gradient-to-r from-sky-950/80 to-slate-900 border border-sky-600/40 hover:border-sky-400 hover:shadow-lg hover:shadow-sky-500/10 transition-all">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-lg bg-sky-600/20 text-sky-400 flex items-center justify-center text-sm font-bold border border-sky-500/30">
                            <i class="fa-solid fa-user-tie"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-xs font-bold text-white group-hover:text-sky-300 transition-colors">Masuk sebagai Tim Konsultan</p>
                            <p class="text-[11px] text-slate-400">Kelompok 1 (Lead System Architect & Specialist)</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-arrow-right text-xs text-sky-400 group-hover:translate-x-1 transition-transform"></i>
                </a>

                <a href="login.php?quick=client" class="group flex items-center justify-between p-3 rounded-xl bg-gradient-to-r from-indigo-950/80 to-slate-900 border border-indigo-600/40 hover:border-indigo-400 hover:shadow-lg hover:shadow-indigo-500/10 transition-all">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-lg bg-indigo-600/20 text-indigo-400 flex items-center justify-center text-sm font-bold border border-indigo-500/30">
                            <i class="fa-solid fa-building-user"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-xs font-bold text-white group-hover:text-indigo-300 transition-colors">Masuk sebagai Pihak Klien</p>
                            <p class="text-[11px] text-slate-400">Direktur Operasional Terminal Kargo Bandara</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-arrow-right text-xs text-indigo-400 group-hover:translate-x-1 transition-transform"></i>
                </a>
            </div>
        </div>

        <div class="relative my-6 text-center">
            <span class="bg-[#111e38] px-3 text-[11px] text-slate-500 relative z-10 font-medium">atau masuk dengan akun manual</span>
            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-700"></div></div>
        </div>

        <!-- Form Manual -->
        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1.5">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 text-xs">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <input type="text" name="username" value="consultant" required class="w-full pl-9 pr-3 py-2 bg-slate-900/90 border border-slate-700 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1.5">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 text-xs">
                        <i class="fa-solid fa-key"></i>
                    </span>
                    <input type="password" name="password" value="password123" required class="w-full pl-9 pr-3 py-2 bg-slate-900/90 border border-slate-700 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition-all">
                </div>
                <p class="text-[10px] text-slate-500 mt-1">Default demo: username <code class="text-sky-400">consultant</code>, password <code class="text-sky-400">password123</code></p>
            </div>

            <button type="submit" class="w-full py-2.5 px-4 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-semibold rounded-xl text-xs shadow-lg shadow-sky-500/25 transition-all flex items-center justify-center">
                <span>Login ke Sistem</span>
                <i class="fa-solid fa-arrow-right-to-bracket ml-2"></i>
            </button>
        </form>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
