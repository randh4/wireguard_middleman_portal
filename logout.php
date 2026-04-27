<?php
/**
 * logout.php — Mengakhiri Sesi Admin
 * 
 * Menghapus semua data session dan mengarahkan kembali ke halaman login.
 * Dipanggil saat admin menekan tombol "Logout" di dashboard.
 */
session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
