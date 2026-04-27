<?php
// db_config.php - Database configuration and initialization

$db_dir = __DIR__ . '/data';
if (!is_dir($db_dir)) {
    mkdir($db_dir, 0755, true);
}
$db_file = $db_dir . '/wireguard_portal.db';

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

    // 3. Create admin table
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
        id INTEGER PRIMARY KEY,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
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

    // Seed admin table if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin");
    if ($stmt->fetchColumn() == 0) {
        $default_user = 'admin';
        $default_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
        $insert->execute([$default_user, $default_pass]);
    }

} catch (PDOException $e) {
    // Hide DB errors from users, log to server error log
    error_log("Database connection failed: " . $e->getMessage());
    die("Terjadi kesalahan pada sistem. Silakan coba lagi.");
}

// Start session securely
function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => false, // Set to true if using HTTPS
            'use_only_cookies' => true,
        ]);
    }
}

// Helper function for HTML escaping
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// CSRF Helpers
function csrf_token() {
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf() {
    start_secure_session();
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }
}

// Flash Message Helpers
function set_flash($message, $type = 'success') {
    start_secure_session();
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function get_flash() {
    start_secure_session();
    if (isset($_SESSION['flash_message'])) {
        $flash = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type']
        ];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $flash;
    }
    return null;
}

// Admin Check Helpers
function is_admin() {
    start_secure_session();
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function require_admin() {
    if (!is_admin()) {
        header("Location: login.php");
        exit;
    }
}

// Indonesian Date Helper
function indonesian_date($timestamp) {
    $date = new DateTime($timestamp);
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return $date->format('d') . ' ' . $months[(int)$date->format('m')] . ' ' . $date->format('Y') . ' ' . $date->format('H:i');
}
?>
