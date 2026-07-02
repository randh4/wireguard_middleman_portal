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

$form_lama = [
    'name' => '',
    'ip_tunnel' => '',
    'public_key' => '',
];

/**
 * Alur penanganan registrasi:
 * Validasi input -> Insert ke DB -> Redirect ke halaman yang sama (PRG pattern)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $nama = trim($_POST['name'] ?? '');
    $ip_terowongan = trim($_POST['ip_tunnel'] ?? '');
    $kunci_publik = preg_replace('/\s+/', '', $_POST['public_key'] ?? '');

    $form_lama = [
        'name' => $nama,
        'ip_tunnel' => $ip_terowongan,
        'public_key' => $_POST['public_key'] ?? '',
    ];

    $daftar_error = [];

    // Validasi input di sisi server
    if (empty($nama) || strlen($nama) < 2) {
        $daftar_error[] = "Nama minimal 2 karakter.";
    }

    // Validasi IP Tunnel yang lebih ketat (Fase 2 & 3)
    $parts = explode('/', $ip_terowongan);
    $ip = $parts[0];
    $cidr = $parts[1] ?? '32';
    
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $daftar_error[] = "Format IP Tunnel tidak valid (contoh: 10.0.0.2/32).";
    } elseif ((int)$cidr < 0 || (int)$cidr > 32) {
        $daftar_error[] = "CIDR prefix harus antara 0-32.";
    }

    // Validasi Public Key format Base64 (Fase 2)
    if (strlen($kunci_publik) !== 44) {
        $daftar_error[] = "Public Key harus tepat 44 karakter.";
    } elseif (!preg_match('/^[A-Za-z0-9+\/]{42,43}={1,2}$/', $kunci_publik)) {
        $daftar_error[] = "Format Public Key tidak valid (harus Base64 44 karakter).";
    }

    if (empty($daftar_error)) {
        try {
            // Cek duplikasi IP Tunnel (Fase 2)
            $cek_ip = $pdo->prepare("SELECT COUNT(*) FROM users WHERE ip_tunnel = ?");
            $cek_ip->execute([$ip_terowongan]);
            if ($cek_ip->fetchColumn() > 0) {
                $daftar_error[] = "IP Tunnel ini sudah terdaftar oleh user lain.";
            }

            // Cek duplikasi Public Key (Fase 2)
            $cek_key = $pdo->prepare("SELECT COUNT(*) FROM users WHERE public_key = ?");
            $cek_key->execute([$kunci_publik]);
            if ($cek_key->fetchColumn() > 0) {
                $daftar_error[] = "Public Key ini sudah terdaftar oleh user lain.";
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $daftar_error[] = "Gagal memproses validasi database.";
        }
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
            $pesan_kilat = [
                'message' => "Gagal mendaftar. Terjadi kesalahan pada sistem.",
                'type' => 'danger'
            ];
        }
    } else {
        $pesan_kilat = [
            'message' => implode("<br>", $daftar_error),
            'type' => 'warning'
        ];
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
 * Mengambil daftar pendaftar terbaru untuk ditampilkan di antrean publik dengan pagination.
 */
$limit = 10;
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_pages = ceil($total_users / $limit);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$daftar_pengguna = $pdo->prepare("SELECT name, status, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
$daftar_pengguna->bindValue(1, $limit, PDO::PARAM_INT);
$daftar_pengguna->bindValue(2, $offset, PDO::PARAM_INT);
$daftar_pengguna->execute();
$daftar_pengguna = $daftar_pengguna->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WireGuard Middleman Portal</title>
    <script>
        document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
    </script>
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
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                showToast("<?= addslashes($pesan_kilat['message']) ?>", "<?= $pesan_kilat['type'] ?>");
            });
        </script>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Area Informasi Server (Pinned Message) -->
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">📌 Informasi Server MikroTik</h5>
                    <!-- Tombol Copy Semua Info (Fase 3) -->
                    <button class="btn btn-sm btn-outline-primary fw-bold" onclick="copyAllServerInfo()">📋 Copy Semua Info</button>
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
                            <input type="text" name="name" id="regName" class="form-control" placeholder="Contoh: Laptop-Budi" value="<?= h($form_lama['name'] ?? '') ?>" required>
                            <div class="invalid-feedback">Nama minimal 2 karakter.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">IP Tunnel yang Diinginkan</label>
                            <input type="text" name="ip_tunnel" id="regIp" class="form-control" placeholder="Contoh: 10.0.0.2/32" value="<?= h($form_lama['ip_tunnel'] ?? '') ?>" required>
                            <div class="invalid-feedback">Format IP Tunnel tidak valid (contoh: 10.0.0.2/32).</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Public Key Perangkat Anda</label>
                            <textarea name="public_key" id="regPubKey" class="form-control" rows="3" placeholder="Contoh: nXpjoWhp3J57jcSeQX/cmozi2AFfmN0mTw6sNCvQCl0=" required><?= h($form_lama['public_key'] ?? '') ?></textarea>
                            <div class="invalid-feedback">Format Public Key tidak valid (harus Base64 44 karakter).</div>
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
                            <tbody id="queueTableBody">
                                <?php if (empty($daftar_pengguna)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Belum ada pendaftar.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $nomor = $offset + 1; foreach ($daftar_pengguna as $pengguna): ?>
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
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-3">
                            <ul class="pagination pagination-sm justify-content-center m-0">
                                <li class="page-item <?= $page === 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>">Prev</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $page === $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
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

/**
 * Helper global untuk menampilkan notifikasi Toast.
 */
function showToast(msg, type = 'success') {
    const toastEl = document.getElementById('liveToast');
    const toastMessage = document.getElementById('toastMessage');
    const toastHeader = toastEl.querySelector('.toast-header');
    
    if (!toastEl || !toastMessage) return;
    
    toastMessage.innerHTML = msg;
    toastEl.className = 'toast';
    
    if (type === 'success') {
        toastEl.classList.add('bg-success', 'text-white');
    } else if (type === 'danger' || type === 'error') {
        toastEl.classList.add('bg-danger', 'text-white');
    } else if (type === 'warning') {
        toastEl.classList.add('bg-warning', 'text-dark');
    } else if (type === 'info') {
        toastEl.classList.add('bg-info', 'text-white');
    }
    
    const toast = new bootstrap.Toast(toastEl, {
        autohide: (type !== 'danger' && type !== 'warning'),
        delay: 5000
    });
    toast.show();
}

/**
 * Fungsi Copy to Clipboard dengan Mekanisme Fallback.
 */
function copyToClipboard(elementId, btn) {
    const copyText = document.getElementById(elementId);
    const textToCopy = copyText.value;
    
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
        else callback("Gagal menyalin.", 'danger');
    } catch (err) {
        callback("Error saat menyalin.", 'danger');
    }
    document.body.removeChild(textArea);
}

/**
 * Menyalin semua info server sekaligus.
 */
function copyAllServerInfo() {
    const pubKey = document.getElementById('pubkey').value;
    const endpoint = document.getElementById('endpoint').value;
    const allowedIp = document.getElementById('allowedip').value;
    
    const textToCopy = `Public Key: ${pubKey}\nEndpoint: ${endpoint}\nAllowed IPs: ${allowedIp}`;
    
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(textToCopy).then(() => {
            showToast("Semua informasi server berhasil disalin!");
        }).catch(err => {
            fallbackCopy(textToCopy, showToast);
        });
    } else {
        fallbackCopy(textToCopy, showToast);
    }
}

/**
 * Validasi Input Client-Side & Inline Feedback
 */
const regName = document.getElementById('regName');
const regIp = document.getElementById('regIp');
const regPubKey = document.getElementById('regPubKey');

function validateName() {
    if (regName.value.trim().length >= 2) {
        regName.classList.remove('is-invalid');
        regName.classList.add('is-valid');
        return true;
    } else {
        regName.classList.remove('is-valid');
        regName.classList.add('is-invalid');
        return false;
    }
}

function validateIp() {
    const value = regIp.value.trim();
    const parts = value.split('/');
    const ip = parts[0];
    const cidr = parts[1];
    
    const ipPattern = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
    const isValidIp = ipPattern.test(ip);
    const isValidCidr = cidr !== undefined && !isNaN(cidr) && parseInt(cidr) >= 0 && parseInt(cidr) <= 32;
    
    if (isValidIp && isValidCidr) {
        regIp.classList.remove('is-invalid');
        regIp.classList.add('is-valid');
        return true;
    } else {
        regIp.classList.remove('is-valid');
        regIp.classList.add('is-invalid');
        return false;
    }
}

function validatePubKey() {
    const value = regPubKey.value.replace(/\s+/g, '');
    const pubKeyPattern = /^[A-Za-z0-9+\/]{42,43}={1,2}$/;
    
    if (value.length === 44 && pubKeyPattern.test(value)) {
        regPubKey.classList.remove('is-invalid');
        regPubKey.classList.add('is-valid');
        return true;
    } else {
        regPubKey.classList.remove('is-valid');
        regPubKey.classList.add('is-invalid');
        return false;
    }
}

if (regName) regName.addEventListener('input', validateName);
if (regIp) regIp.addEventListener('input', validateIp);
if (regPubKey) regPubKey.addEventListener('input', validatePubKey);

/**
 * Mencegah pengiriman formulir ganda (double submit).
 */
document.getElementById('regForm').addEventListener('submit', function(e) {
    const isNameValid = validateName();
    const isIpValid = validateIp();
    const isPubKeyValid = validatePubKey();
    
    if (!isNameValid || !isIpValid || !isPubKeyValid) {
        e.preventDefault();
        showToast('Pastikan semua input sudah valid sebelum mengirim.', 'warning');
        return;
    }

    const btn = document.getElementById('submitBtn');
    setTimeout(() => {
        btn.disabled = true;
    }, 0);
    btn.querySelector('.btn-text').innerText = "Memproses...";
    btn.querySelector('.spinner-border').classList.remove('d-none');
});

// Auto-refresh hanya tabel antrean setiap 30 detik
setInterval(() => {
    fetch('api_queue.php')
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
        })
        .then(html => {
            const tableBody = document.getElementById('queueTableBody');
            if (tableBody) tableBody.innerHTML = html;
        })
        .catch(err => console.error('Gagal refresh antrean:', err));
}, 30000);
</script>
</body>
</html>
