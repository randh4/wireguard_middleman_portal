<?php
/**
 * db_config.php — Konfigurasi Database & Fungsi Helper
 * 
 * File ini di-include oleh semua halaman. Bertugas:
 * 1. Membuka koneksi ke SQLite (auto-create jika belum ada)
 * 2. Membuat struktur tabel jika belum ada (DDL)
 * 3. Mengisi data awal (seed) untuk tabel settings dan admin
 * 4. Menyediakan fungsi-fungsi helper: keamanan, session, CSRF, flash message
 */

// Menentukan direktori database. 
// Disimpan di subfolder agar bisa dilindungi dengan .htaccess dari akses langsung browser.
$direktori_db = __DIR__ . '/data';
if (!is_dir($direktori_db)) {
    mkdir($direktori_db, 0755, true);
}
$berkas_db = $direktori_db . '/wireguard_portal.db';

try {
    // Membuka koneksi ke database SQLite menggunakan PDO
    $pdo = new PDO("sqlite:" . $berkas_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 1. Membuat tabel settings untuk menyimpan info server MikroTik
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY,
        key TEXT UNIQUE NOT NULL,
        value TEXT NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Membuat tabel users untuk data pendaftar WireGuard
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        ip_tunnel TEXT NOT NULL,
        public_key TEXT NOT NULL,
        status TEXT DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Membuat tabel admin untuk kredensial login dashboard
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
        id INTEGER PRIMARY KEY,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Mengisi data awal (seed) tabel settings jika masih kosong
    // Data ini yang akan tampil sebagai "Pinned Message" di halaman user
    $kueri = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($kueri->fetchColumn() == 0) {
        $data_awal = [
            ['key' => 'public_key', 'value' => '(belum diisi)'],
            ['key' => 'endpoint', 'value' => '(belum diisi)'],
            ['key' => 'allowed_ip', 'value' => '(belum diisi)']
        ];
        $kueri_sisip = $pdo->prepare("INSERT INTO settings (key, value) VALUES (:key, :value)");
        foreach ($data_awal as $baris_awal) {
            $kueri_sisip->execute($baris_awal);
        }
    }

    // Mengisi akun admin default jika tabel masih kosong
    // Username: admin | Password: admin123
    $kueri = $pdo->query("SELECT COUNT(*) FROM admin");
    if ($kueri->fetchColumn() == 0) {
        $pengguna_bawaan = 'admin';
        $sandi_bawaan = password_hash('admin123', PASSWORD_DEFAULT);
        $kueri_sisip = $pdo->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
        $kueri_sisip->execute([$pengguna_bawaan, $sandi_bawaan]);
    }

} catch (PDOException $e) {
    // Sembunyikan detail error database dari user, simpan ke log server untuk debugging
    error_log("Database connection failed: " . $e->getMessage());
    die("Terjadi kesalahan pada sistem. Silakan coba lagi.");
}

/**
 * Memulai session dengan pengaturan keamanan tambahan.
 * httponly mencegah akses script ke cookie.
 */
function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => false, // Set ke true jika menggunakan HTTPS
            'use_only_cookies' => true,
        ]);
    }
}

/**
 * Helper untuk membersihkan output dari potensi serangan XSS.
 * Shortcut untuk htmlspecialchars.
 */
function h($teks) {
    return htmlspecialchars($teks, ENT_QUOTES, 'UTF-8');
}

/**
 * Mekanisme Perlindungan CSRF (Cross-Site Request Forgery).
 * Menghasilkan token unik yang disimpan dalam session.
 */
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

/**
 * Helper Flash Message menggunakan pola PRG (Post-Redirect-Get).
 * Pesan disimpan di session dan hanya ditampilkan sekali.
 */
function set_flash($pesan, $tipe = 'success') {
    start_secure_session();
    $_SESSION['flash_message'] = $pesan;
    $_SESSION['flash_type'] = $tipe;
}

function get_flash() {
    start_secure_session();
    if (isset($_SESSION['flash_message'])) {
        $pesan_kilat = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type']
        ];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $pesan_kilat;
    }
    return null;
}

/**
 * Fungsi Guard untuk halaman yang membutuhkan hak akses admin.
 */
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

/**
 * Helper untuk memformat waktu database ke format tanggal Indonesia.
 * Menggantikan format bawaan PHP yang menggunakan bahasa Inggris.
 */
function indonesian_date($waktu) {
    $tanggal = new DateTime($waktu);
    $daftar_bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return $tanggal->format('d') . ' ' . $daftar_bulan[(int)$tanggal->format('m')] . ' ' . $tanggal->format('Y') . ' ' . $tanggal->format('H:i');
}
?>
