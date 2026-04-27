<?php
// db_config.php - Database configuration and initialization

$db_file = __DIR__ . '/wireguard_portal.db';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 1. Create settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY,
        key TEXT UNIQUE NOT NULL,
        value TEXT NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        ip_tunnel TEXT NOT NULL,
        public_key TEXT NOT NULL,
        status TEXT DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed settings table if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $seeds = [
            ['key' => 'public_key', 'value' => '(belum diisi)'],
            ['key' => 'endpoint', 'value' => '(belum diisi)'],
            ['key' => 'allowed_ip', 'value' => '(belum diisi)']
        ];
        $insert = $pdo->prepare("INSERT INTO settings (key, value) VALUES (:key, :value)");
        foreach ($seeds as $seed) {
            $insert->execute($seed);
        }
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Admin Credentials (Hardcoded for simplicity as per plan)
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin123'); // In production, use password_hash

// Helper function for HTML escaping
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Helper function for session check
function is_admin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function require_admin() {
    if (!is_admin()) {
        header("Location: login.php");
        exit;
    }
}
?>
