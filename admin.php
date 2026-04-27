<?php
// admin.php - Admin Dashboard
require_once 'db_config.php';

require_admin();

$flash = get_flash();

// 1. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    // Update Settings
    if (isset($_POST['update_settings'])) {
        $updates = [
            'public_key' => trim($_POST['public_key'] ?? ''),
            'endpoint' => trim($_POST['endpoint'] ?? ''),
            'allowed_ip' => trim($_POST['allowed_ip'] ?? '')
        ];

        try {
            $stmt = $pdo->prepare("UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = ?");
            foreach ($updates as $key => $value) {
                $stmt->execute([$value, $key]);
            }
            set_flash("Informasi server berhasil diperbarui.", "success");
        } catch (PDOException $e) {
            error_log($e->getMessage());
            set_flash("Gagal memperbarui settings.", "danger");
        }
        header("Location: admin.php");
        exit;
    }

    // User Actions (Toggle Status / Delete)
    if (isset($_POST['action']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $action = $_POST['action'];

        try {
            if ($action === 'activate') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $stmt->execute([$id]);
                set_flash("User berhasil diaktifkan.", "success");
            } elseif ($action === 'pending') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'pending' WHERE id = ?");
                $stmt->execute([$id]);
                set_flash("User diubah ke status pending.", "warning");
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                set_flash("User berhasil dihapus.", "info");
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            set_flash("Gagal melakukan aksi.", "danger");
        }
        header("Location: admin.php");
        exit;
    }
}

// Fetch Data
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key']] = $row['value'];
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

// Statistics
$total_users = count($users);
$active_users = count(array_filter($users, fn($u) => $u['status'] === 'active'));
$pending_users = count(array_filter($users, fn($u) => $u['status'] === 'pending'));
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - WireGuard Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .navbar { background-color: var(--card-bg); border-bottom: 1px solid var(--border-color); }
        .stat-card { border-left: 4px solid var(--primary-color); }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg mb-4 py-3">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">Admin Portal</a>
        <div class="ms-auto d-flex align-items-center">
            <button class="theme-toggle me-3" onclick="toggleTheme()">🌓</button>
            <span class="me-3 small text-muted d-none d-md-inline">Signed in as <strong>admin</strong></span>
            <a href="logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <?php if ($flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= h($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card p-3">
                <div class="small text-muted fw-bold">TOTAL USER</div>
                <div class="h3 mb-0 fw-bold"><?= $total_users ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-3 border-success">
                <div class="small text-muted fw-bold text-success">ACTIVE</div>
                <div class="h3 mb-0 fw-bold"><?= $active_users ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-3 border-warning">
                <div class="small text-muted fw-bold text-warning">PENDING</div>
                <div class="h3 mb-0 fw-bold"><?= $pending_users ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Update Info Tersemat -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">⚙️ Settings Server</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Public Key MikroTik</label>
                            <input type="text" name="public_key" class="form-control" value="<?= h($settings['public_key'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Endpoint (IP:Port)</label>
                            <input type="text" name="endpoint" class="form-control" value="<?= h($settings['endpoint'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Allowed IP</label>
                            <input type="text" name="allowed_ip" class="form-control" value="<?= h($settings['allowed_ip'] ?? '') ?>" required>
                        </div>
                        <div class="d-grid pt-2">
                            <button type="submit" name="update_settings" class="btn btn-primary fw-bold">Simpan Perubahan</button>
                        </div>
                    </form>
                    <div class="mt-4 pt-3 border-top">
                        <a href="index.php" target="_blank" class="text-decoration-none small">Lihat Tampilan User ↗</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Management -->
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header border-0 pt-4 px-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">👥 User Management</h5>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-8">
                            <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Cari nama atau IP...">
                        </div>
                        <div class="col-md-4">
                            <select id="statusFilter" class="form-select form-select-sm">
                                <option value="">Semua Status</option>
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="table-responsive">
                        <table class="table" id="userTable">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>User / IP</th>
                                    <th>Public Key</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Belum ada pendaftaran.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $i = 1; foreach ($users as $user): ?>
                                        <tr class="user-row">
                                            <td><?= $i++ ?></td>
                                            <td>
                                                <div class="fw-bold name-cell"><?= h($user['name']) ?></div>
                                                <div class="small text-muted ip-cell"><?= h($user['ip_tunnel']) ?></div>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm" style="min-width: 150px;">
                                                    <input type="text" class="form-control" id="key-<?= $user['id'] ?>" value="<?= h($user['public_key']) ?>" readonly>
                                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('key-<?= $user['id'] ?>', this)">Copy</button>
                                                </div>
                                            </td>
                                            <td class="status-cell">
                                                <span class="badge badge-<?= h($user['status']) ?>">
                                                    <?= ucfirst(h($user['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <form method="POST" style="display:inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                        <?php if ($user['status'] === 'pending'): ?>
                                                            <input type="hidden" name="action" value="activate">
                                                            <button type="submit" class="btn btn-success">Activate</button>
                                                        <?php else: ?>
                                                            <input type="hidden" name="action" value="pending">
                                                            <button type="submit" class="btn btn-warning">Set Pending</button>
                                                        <?php endif; ?>
                                                    </form>
                                                    <button type="button" class="btn btn-danger" onclick="confirmDelete(<?= $user['id'] ?>, '<?= h($user['name']) ?>')">Delete</button>
                                                </div>
                                            </td>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus user <strong id="deleteUserName"></strong>? Tindakan ini tidak dapat dibatalkan.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form method="POST" id="deleteForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="deleteUserId">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-danger fw-bold">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast for clipboard -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="liveToast" class="toast align-items-center text-white bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">Berhasil disalin!</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
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
document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');

// Delete Confirmation
function confirmDelete(id, name) {
    document.getElementById('deleteUserId').value = id;
    document.getElementById('deleteUserName').innerText = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Clipboard
function copyToClipboard(elementId, btn) {
    const copyText = document.getElementById(elementId);
    copyText.select();
    navigator.clipboard.writeText(copyText.value).then(() => {
        new bootstrap.Toast(document.getElementById('liveToast')).show();
    });
}

// Search & Filter
document.getElementById('searchInput').addEventListener('input', filterTable);
document.getElementById('statusFilter').addEventListener('change', filterTable);

function filterTable() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('.user-row');

    rows.forEach(row => {
        const name = row.querySelector('.name-cell').innerText.toLowerCase();
        const ip = row.querySelector('.ip-cell').innerText.toLowerCase();
        const rowStatus = row.querySelector('.status-cell').innerText;
        
        const matchesSearch = name.includes(search) || ip.includes(search);
        const matchesStatus = status === "" || rowStatus === status;

        if (matchesSearch && matchesStatus) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}

// Auto-dismiss Alerts
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    }, 5000);
});
</script>
</body>
</html>
