<?php
// login.php - Admin Login
require_once 'db_config.php';

session_start();

// If already logged in, redirect to admin
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    header("Location: admin.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Simple authentication based on hardcoded constants
    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $error = "Username atau password salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - WireGuard Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f0f2f5; display: flex; align-items: center; min-height: 100vh; }
        .login-card { max-width: 400px; width: 100%; margin: auto; border: none; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="container">
    <div class="card login-card p-4">
        <div class="text-center mb-4">
            <h3 class="fw-bold">Admin Login</h3>
            <p class="text-muted small">WireGuard Middleman Portal</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger small"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold">Username</label>
                <input type="text" name="username" class="form-control" placeholder="admin" required>
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

</body>
</html>
