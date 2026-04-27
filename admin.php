<?php
// admin.php - Admin Dashboard
require_once 'db_config.php';

require_admin();

$message = '';
$message_type = '';

// 1. Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $updates = [
        'public_key' => $_POST['public_key'] ?? '',
        'endpoint' => $_POST['endpoint'] ?? '',
        'allowed_ip' => $_POST['allowed_ip'] ?? ''
    ];

    try {
        $stmt = $pdo->prepare("UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = ?");
        foreach ($updates as $key => $value) {
            $stmt->execute([$value, $key]);
        }
        $message = "Informasi server berhasil diperbarui.";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Gagal memperbarui settings: " . $e->getMessage();
        $message_type = "danger";
    }
}

// 2. Handle User Actions (Toggle Status / Delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    try {
        if ($action === 'activate') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$id]);
            $message = "User berhasil diaktifkan.";
            $message_type = "success";
        } elseif ($action === 'pending') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'pending' WHERE id = ?");
            $stmt->execute([$id]);
            $message = "User diubah ke status pending.";
            $message_type = "warning";
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $message = "User berhasil dihapus.";
            $message_type = "info";
        }
    } catch (PDOException $e) {
        $message = "Gagal melakukan aksi: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Fetch Data
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key']] = $row['value'];
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - WireGuard Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .navbar { background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .badge-pending { background-color: #ffc107; color: #000; }
        .badge-active { background-color: #198754; color: #fff; }
        .table { vertical-align: middle; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light mb-4 py-3">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">Admin Portal</a>
        <div class="ms-auto d-flex align-items-center">
            <span class="me-3 small text-muted">Signed in as <strong>admin</strong></span>
            <a href="logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= h($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Update Info Tersemat -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">⚙️ Settings Server</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <form method="POST">
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
                            <button type="submit" name="update_settings" class="btn btn-primary">Simpan Perubahan</button>
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
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">👥 User Management</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User / IP</th>
                                    <th>Public Key</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Belum ada pendaftaran.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= h($user['name']) ?></div>
                                                <div class="small text-muted"><?= h($user['ip_tunnel']) ?></div>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm" style="max-width: 200px;">
                                                    <input type="text" class="form-control" id="key-<?= $user['id'] ?>" value="<?= h($user['public_key']) ?>" readonly>
                                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('key-<?= $user['id'] ?>', this)">Copy</button>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= h($user['status']) ?>">
                                                    <?= ucfirst(h($user['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($user['status'] === 'pending'): ?>
                                                        <a href="?action=activate&id=<?= $user['id'] ?>" class="btn btn-success">Activate</a>
                                                    <?php else: ?>
                                                        <a href="?action=pending&id=<?= $user['id'] ?>" class="btn btn-warning">Set Pending</a>
                                                    <?php endif; ?>
                                                    <a href="?action=delete&id=<?= $user['id'] ?>" class="btn btn-danger" onclick="return confirm('Hapus user ini?')">Delete</a>
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

<script>
function copyToClipboard(elementId, btn) {
    const copyText = document.getElementById(elementId);
    copyText.select();
    navigator.clipboard.writeText(copyText.value).then(() => {
        const originalText = btn.innerText;
        btn.innerText = "✓";
        setTimeout(() => { btn.innerText = originalText; }, 1500);
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
