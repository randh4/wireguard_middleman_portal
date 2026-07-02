<?php
/**
 * login.php — Halaman Login Admin
 * 
 * Hanya admin yang bisa mengakses dashboard. Halaman ini:
 * 1. Menampilkan form login (username + password)
 * 2. Memverifikasi kredensial terhadap tabel admin di database
 * 3. Menggunakan password_verify() untuk mencocokkan hash
 * 4. Meregenerasi session ID setelah login berhasil (anti session fixation)
 */
require_once 'db_config.php';

start_secure_session();

/**
 * Jika admin sudah dalam kondisi login, langsung arahkan ke dashboard
 * untuk menghindari login ulang yang tidak perlu.
 */
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    header("Location: admin.php");
    exit;
}

$pesan_error = '';

// Bersihkan attempt yang sudah expired (> 15 menit)
if (isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = array_filter(
        $_SESSION['login_attempts'],
        fn($t) => $t > time() - 900
    );
} else {
    $_SESSION['login_attempts'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $nama_pengguna = trim($_POST['username'] ?? '');
    $kata_sandi = $_POST['password'] ?? '';

    if (count($_SESSION['login_attempts']) >= 5) {
        $pesan_error = "Terlalu banyak percobaan. Coba lagi dalam 15 menit.";
    } elseif (!empty($nama_pengguna) && !empty($kata_sandi)) {
        try {
            // Mencari data admin berdasarkan username
            $kueri = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
            $kueri->execute([$nama_pengguna]);
            $data_admin = $kueri->fetch();

            /**
              * Verifikasi password menggunakan hashing standar PHP.
              * Tidak menggunakan perbandingan string biasa demi keamanan data.
              */
            if ($data_admin && password_verify($kata_sandi, $data_admin['password'])) {
                // Anti Session Fixation: regenerasi ID session setelah login berhasil
                session_regenerate_id(true);
                $_SESSION['admin'] = true;
                $_SESSION['admin_id'] = $data_admin['id'];
                $_SESSION['admin_username'] = $data_admin['username'];
                
                // Reset counter rate limit
                $_SESSION['login_attempts'] = [];
                
                header("Location: admin.php");
                exit;
            } else {
                $_SESSION['login_attempts'][] = time();
                $pesan_error = "Username atau password salah.";
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $pesan_error = "Terjadi kesalahan sistem.";
        }
    } else {
        $pesan_error = "Username dan password wajib diisi.";
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - WireGuard Portal</title>
    <script>
        document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { display: flex; align-items: center; min-height: 100vh; }
        .login-card { max-width: 400px; width: 100%; margin: auto; }
    </style>
</head>
<body>

<div class="container">
    <div class="card login-card p-4">
        <div class="text-center mb-4">
            <h3 class="fw-bold">Admin Login</h3>
            <p class="text-muted small">WireGuard Middleman Portal</p>
        </div>

        <?php if ($pesan_error): ?>
            <div class="alert alert-danger small"><?= h($pesan_error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label small fw-bold">Username</label>
                <input type="text" name="username" class="form-control" placeholder="admin" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary fw-bold">Masuk</button>
            </div>
            <div class="text-center mt-3">
                <a href="index.php" class="text-decoration-none small text-muted">← Kembali ke Halaman Utama</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
