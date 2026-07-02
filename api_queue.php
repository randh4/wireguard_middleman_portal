<?php
/**
 * api_queue.php — Endpoint Fragment untuk Tabel Antrean Publik
 * 
 * Mengembalikan fragmen HTML berisi daftar pendaftar terbaru.
 * Dipanggil via AJAX polling oleh index.php.
 */
require_once 'db_config.php';

try {
    $daftar_pengguna = $pdo->query("SELECT name, status, created_at FROM users ORDER BY created_at DESC LIMIT 20")->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch queue in api: " . $e->getMessage());
    echo '<tr><td colspan="4" class="text-center text-danger py-4">Gagal memuat antrean.</td></tr>';
    exit;
}

if (empty($daftar_pengguna)): ?>
    <tr>
        <td colspan="4" class="text-center text-muted py-4">Belum ada pendaftar.</td>
    </tr>
<?php else: ?>
    <?php $nomor = 1; foreach ($daftar_pengguna as $pengguna): ?>
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
