<?php
/**
 * api_queue.php — Endpoint Fragment untuk Tabel Antrean Publik
 * 
 * Mengembalikan fragmen HTML berisi daftar pendaftar terbaru.
 * Dipanggil via AJAX polling oleh index.php.
 */
require_once 'db_config.php';

$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

try {
    $daftar_pengguna = $pdo->prepare("SELECT name, status, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $daftar_pengguna->bindValue(1, $limit, PDO::PARAM_INT);
    $daftar_pengguna->bindValue(2, $offset, PDO::PARAM_INT);
    $daftar_pengguna->execute();
    $daftar_pengguna = $daftar_pengguna->fetchAll();
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
