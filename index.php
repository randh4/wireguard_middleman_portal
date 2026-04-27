<?php
// index.php - User Page
require_once 'db_config.php';

$flash = get_flash();

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    verify_csrf();
    
    $name = trim($_POST['name'] ?? '');
    $ip_tunnel = trim($_POST['ip_tunnel'] ?? '');
    $public_key = trim($_POST['public_key'] ?? '');

    $errors = [];

    // Validation
    if (empty($name) || strlen($name) < 2) {
        $errors[] = "Nama minimal 2 karakter.";
    }
    if (!preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(\/\d{1,2})?$/', $ip_tunnel)) {
        $errors[] = "Format IP Tunnel tidak valid (contoh: 10.0.0.2/32).";
    }
    if (strlen($public_key) < 40) {
        $errors[] = "Public Key terlalu pendek.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, ip_tunnel, public_key) VALUES (?, ?, ?)");
            $stmt->execute([$name, $ip_tunnel, $public_key]);
            set_flash("Pendaftaran berhasil! Silakan hubungi admin untuk aktivasi.", "success");
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            set_flash("Gagal mendaftar. Terjadi kesalahan pada sistem.", "danger");
        }
    } else {
        set_flash(implode("<br>", $errors), "warning");
    }
}

// Fetch Settings
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key']] = $row['value'];
}

// Fetch Registered Users
$users = $pdo->query("SELECT name, status, created_at FROM users ORDER BY created_at DESC LIMIT 20")->fetchAll();
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
    <?php if ($flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Pinned Message Area -->
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
                                <input type="text" class="form-control" id="pubkey" value="<?= h($settings['public_key'] ?? '') ?>" readonly>
                                <button class="btn btn-outline-primary btn-copy" onclick="copyToClipboard('pubkey', this)">Copy</button>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label text-muted small fw-bold">ENDPOINT (IP:PORT)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="endpoint" value="<?= h($settings['endpoint'] ?? '') ?>" readonly>
                                <button class="btn btn-outline-primary btn-copy" onclick="copyToClipboard('endpoint', this)">Copy</button>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label text-muted small fw-bold">ALLOWED IP</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="allowedip" value="<?= h($settings['allowed_ip'] ?? '') ?>" readonly>
                                <button class="btn btn-outline-primary btn-copy" onclick="copyToClipboard('allowedip', this)">Copy</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Registrasi -->
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

        <!-- Daftar Terdaftar -->
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
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Belum ada pendaftar.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $i = 1; foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= $i++ ?></td>
                                            <td class="fw-semibold"><?= h($user['name']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= h($user['status']) ?>">
                                                    <?= ucfirst(h($user['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted"><?= indonesian_date($user['created_at']) ?></td>
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

<!-- Toast Notification -->
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
// Theme Management
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

// Clipboard with Fallback
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

// Disable Double Submit
document.getElementById('regForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.querySelector('.btn-text').innerText = "Memproses...";
    btn.querySelector('.spinner-border').classList.remove('d-none');
});

// Auto-dismiss Alerts
document.querySelectorAll('.alert-success, .alert-info').forEach(alert => {
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    }, 5000);
});
</script>
</body>
</html>
