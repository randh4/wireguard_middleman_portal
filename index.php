<?php
/**
 * index.php — Halaman Publik User
 * 
 * Halaman ini bisa diakses tanpa login. Fungsinya:
 * 1. Menampilkan informasi server MikroTik (Public Key, Endpoint, Allowed IP)
 *    yang bisa di-copy ke clipboard
 * 2. Menyediakan form registrasi bagi user yang ingin mendaftarkan
 *    perangkatnya ke jaringan WireGuard
 * 3. Menampilkan daftar antrean pendaftar (tanpa menunjukkan Public Key mereka)
 */
require_once 'db_config.php';

// Mengambil pesan kilat jika ada (misal setelah berhasil daftar)
$pesan_kilat = get_flash();

/**
 * Alur penanganan registrasi:
 * Validasi input -> Insert ke DB -> Redirect ke halaman yang sama (PRG pattern)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    verify_csrf();
    
    $nama = trim($_POST['name'] ?? '');
    $ip_terowongan = trim($_POST['ip_tunnel'] ?? '');
    $kunci_publik = trim($_POST['public_key'] ?? '');

    $daftar_error = [];

    // Validasi input di sisi server
    if (empty($nama) || strlen($nama) < 2) {
        $daftar_error[] = "Nama minimal 2 karakter.";
    }
    // Regex untuk memastikan format IP/CIDR yang valid
    if (!preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(\/\d{1,2})?$/', $ip_terowongan)) {
        $daftar_error[] = "Format IP Tunnel tidak valid (contoh: 10.0.0.2/32).";
    }
    if (strlen($kunci_publik) < 40) {
        $daftar_error[] = "Public Key terlalu pendek.";
    }

    if (empty($daftar_error)) {
        try {
            $kueri = $pdo->prepare("INSERT INTO users (name, ip_tunnel, public_key) VALUES (?, ?, ?)");
            $kueri->execute([$nama, $ip_terowongan, $kunci_publik]);
            
            set_flash("Pendaftaran berhasil! Silakan hubungi admin untuk aktivasi.", "success");
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            set_flash("Gagal mendaftar. Terjadi kesalahan pada sistem.", "danger");
        }
    } else {
        set_flash(implode("<br>", $daftar_error), "warning");
    }
}

/**
 * Mengambil informasi server yang akan ditampilkan di area Pinned Message.
 * Data ini diatur oleh admin melalui dashboard.
 */
$pengaturan = [];
$kueri = $pdo->query("SELECT * FROM settings");
while ($baris = $kueri->fetch()) {
    $pengaturan[$baris['key']] = $baris['value'];
}

/**
 * Mengambil daftar pendaftar terbaru untuk ditampilkan di antrean publik.
 * Hanya menampilkan nama, status, dan waktu untuk menjaga privasi data sensitif.
 */
$daftar_pengguna = $pdo->query("SELECT name, status, created_at FROM users ORDER BY created_at DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WireGuard Middleman Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="hero-section">
    <div class="container text-center position-relative">
        <div class="position-absolute top-0 end-0">
            <button class="theme-toggle" onclick="toggleTheme()">🌓 Tema</button>
        </div>
        <h1 class="fw-bold">WireGuard Middleman Portal</h1>
        <p class="lead">Pertukaran Public Key untuk Koneksi MikroTik</p>
    </div>
</div>

<div class="container pb-5">
    <?php if ($pesan_kilat): ?>
        <div class="alert alert-<?= h($pesan_kilat['type']) ?> alert-dismissible fade show" role="alert">
            <?= $pesan_kilat['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Area Informasi Server (Pinned Message) -->
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">📌 Informasi Server MikroTik</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="row row-cols-1 row-cols-md-3 g-3">
                        <div class="col">
                            <label class="form-label text-muted small fw-bold">PUBLIC KEY MIKROTIK</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="pubkey" value="<?= h($pengaturan['public_key'] ?? '') ?>" readonly>
                                <button class="btn btn-outline-primary btn-copy" onclick="copyToClipboard('pubkey', this)">Copy</button>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label text-muted small fw-bold">ENDPOINT (IP:PORT)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="endpoint" value="<?= h($pengaturan['endpoint'] ?? '') ?>" readonly>
                                <button class="btn btn-outline-primary btn-copy" onclick="copyToClipboard('endpoint', this)">Copy</button>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label text-muted small fw-bold">ALLOWED IP</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="allowedip" value="<?= h($pengaturan['allowed_ip'] ?? '') ?>" readonly>
                                <button class="btn btn-outline-primary btn-copy" onclick="copyToClipboard('allowedip', this)">Copy</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Registrasi User Baru -->
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">📝 Form Registrasi User</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <form method="POST" id="regForm">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Nama Perangkat/User</label>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: Laptop-Budi" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">IP Tunnel yang Diinginkan</label>
                            <input type="text" name="ip_tunnel" class="form-control" placeholder="Contoh: 10.0.0.2/32" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Public Key Perangkat Anda</label>
                            <textarea name="public_key" class="form-control" rows="3" placeholder="Paste Public Key Anda di sini..." required></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="register" class="btn btn-primary fw-bold" id="submitBtn">
                                <span class="btn-text">Daftar Sekarang</span>
                                <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tabel Antrean Pendaftar -->
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">👥 Antrean Pendaftar</h5>
                    <a href="login.php" class="btn btn-sm btn-outline-secondary">Admin Login</a>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Status</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($daftar_pengguna)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Belum ada pendaftar.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $nomor = 1; foreach ($daftar_pengguna as $pengguna): ?>
                                        <tr>
                                            <td><?= $nomor++ ?></td>
                                            <td class="fw-semibold"><?= h($pengguna['name']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= h($pengguna['status']) ?>">
                                                    <?= ucfirst(h($pengguna['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted"><?= indonesian_date($pengguna['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="text-center">
    <div class="container">
        <p class="mb-0">© <?= date('Y') ?> WireGuard Middleman Portal. Dirancang untuk kemudahan administrasi IT.</p>
    </div>
</footer>

<!-- Wadah untuk Notifikasi Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto">Notifikasi</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/**
 * Pengaturan Tema (Terang/Gelap).
 * Menyimpan preferensi user ke localStorage agar awet saat refresh.
 */
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
}

document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
});

/**
 * Fungsi Copy to Clipboard dengan Mekanisme Fallback.
 * navigator.clipboard membutuhkan koneksi aman (HTTPS). 
 * Di lingkungan internal HTTP, fungsi ini akan beralih ke metode textarea manual.
 */
function copyToClipboard(elementId, btn) {
    const copyText = document.getElementById(elementId);
    const textToCopy = copyText.value;
    
    const showToast = (msg, success = true) => {
        const toastEl = document.getElementById('liveToast');
        document.getElementById('toastMessage').innerText = msg;
        const toast = new bootstrap.Toast(toastEl);
        toastEl.classList.remove('bg-success', 'bg-danger', 'text-white');
        if (success) toastEl.classList.add('bg-success', 'text-white');
        else toastEl.classList.add('bg-danger', 'text-white');
        toast.show();
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(textToCopy).then(() => {
            showToast("Berhasil disalin ke clipboard!");
        }).catch(err => {
            fallbackCopy(textToCopy, showToast);
        });
    } else {
        fallbackCopy(textToCopy, showToast);
    }
}

/**
 * Metode alternatif menyalin teks jika API clipboard tidak tersedia.
 */
function fallbackCopy(text, callback) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-9999px";
    textArea.style.top = "0";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        const successful = document.execCommand('copy');
        if (successful) callback("Berhasil disalin! (fallback)");
        else callback("Gagal menyalin.", false);
    } catch (err) {
        callback("Error saat menyalin.", false);
    }
    document.body.removeChild(textArea);
}

/**
 * Mencegah pengiriman formulir ganda (double submit).
 * Menonaktifkan tombol setelah klik pertama dan memberikan indikasi loading.
 */
document.getElementById('regForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.querySelector('.btn-text').innerText = "Memproses...";
    btn.querySelector('.spinner-border').classList.remove('d-none');
});

/**
 * Menghilangkan pesan sukses/info secara otomatis setelah 5 detik.
 */
document.querySelectorAll('.alert-success, .alert-info').forEach(alert => {
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    }, 5000);
});
</script>
</body>
</html>
