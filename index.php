<?php
// index.php - User Page
require_once 'db_config.php';

$message = '';
$message_type = '';

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = $_POST['name'] ?? '';
    $ip_tunnel = $_POST['ip_tunnel'] ?? '';
    $public_key = $_POST['public_key'] ?? '';

    if (!empty($name) && !empty($ip_tunnel) && !empty($public_key)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, ip_tunnel, public_key) VALUES (?, ?, ?)");
            $stmt->execute([$name, $ip_tunnel, $public_key]);
            $message = "Pendaftaran berhasil! Silakan hubungi admin untuk aktivasi.";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Gagal mendaftar: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "Semua field harus diisi.";
        $message_type = "warning";
    }
}

// Fetch Settings
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key']] = $row['value'];
}

// Fetch Registered Users
$users = $pdo->query("SELECT name, status, created_at FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WireGuard Middleman Portal</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .hero-section { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); color: white; padding: 40px 0; margin-bottom: 30px; border-radius: 0 0 20px 20px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .btn-copy { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .badge-pending { background-color: #ffc107; color: #000; }
        .badge-active { background-color: #198754; color: #fff; }
    </style>
</head>
<body>

<div class="hero-section">
    <div class="container text-center">
        <h1 class="fw-bold">WireGuard Middleman Portal</h1>
        <p class="lead">Pertukaran Public Key untuk Koneksi MikroTik</p>
    </div>
</div>

<div class="container pb-5">
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= h($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Pinned Message Area -->
        <div class="col-md-12">
            <div class="card bg-white">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">📌 Informasi Server MikroTik</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="row row-cols-1 row-cols-md-3 g-3">
                        <div class="col">
                            <label class="form-label text-muted small fw-bold">PUBLIC KEY MIKROTIK</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-light" id="pubkey" value="<?= h($settings['public_key'] ?? '') ?>" readonly>
                                <button class="btn btn-outline-primary btn-copy" onclick="copyToClipboard('pubkey', this)">Copy</button>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label text-muted small fw-bold">ENDPOINT (IP:PORT)</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-light" id="endpoint" value="<?= h($settings['endpoint'] ?? '') ?>" readonly>
                                <button class="btn btn-outline-primary btn-copy" onclick="copyToClipboard('endpoint', this)">Copy</button>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label text-muted small fw-bold">ALLOWED IP</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-light" id="allowedip" value="<?= h($settings['allowed_ip'] ?? '') ?>" readonly>
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
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">📝 Form Registrasi User</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <form method="POST">
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
                            <button type="submit" name="register" class="btn btn-primary fw-bold">Daftar Sekarang</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Daftar Terdaftar -->
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">👥 Antrean Pendaftar</h5>
                    <a href="login.php" class="btn btn-sm btn-outline-secondary">Admin Login</a>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Status</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Belum ada pendaftar.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= h($user['name']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= h($user['status']) ?>">
                                                    <?= ucfirst(h($user['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted"><?= date('d M Y H:i', strtotime($user['created_at'])) ?></td>
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

<script>
function copyToClipboard(elementId, btn) {
    const copyText = document.getElementById(elementId);
    copyText.select();
    copyText.setSelectionRange(0, 99999); // For mobile devices
    navigator.clipboard.writeText(copyText.value).then(() => {
        const originalText = btn.innerText;
        btn.innerText = "Copied!";
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        setTimeout(() => {
            btn.innerText = originalText;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
        }, 2000);
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
