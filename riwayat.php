<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
$u = require_role('member');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'perpanjang') {
    csrf_check();
    $st = $pdo->prepare('SELECT * FROM peminjamans WHERE id = ? AND user_id = ?');
    $st->execute([(int) ($_POST['id'] ?? 0), $u['id']]);
    $p = $st->fetch();

    if (!$p || $p['status'] !== 'dipinjam') {
        flash('danger', 'Pinjaman tidak ditemukan atau sudah dikembalikan.');
    } elseif ($p['diperpanjang']) {
        flash('danger', 'Pinjaman hanya bisa diperpanjang satu kali.');
    } elseif (hitung_denda($p['jatuh_tempo']) > 0) {
        flash('danger', 'Pinjaman yang terlambat tidak bisa diperpanjang.');
    } else {
        $pdo->prepare('UPDATE peminjamans SET jatuh_tempo = DATE_ADD(jatuh_tempo, INTERVAL ' . lama_pinjam() . ' DAY), diperpanjang = 1 WHERE id = ?')
            ->execute([$p['id']]);
        flash('success', 'Pinjaman diperpanjang ' . lama_pinjam() . ' hari.');
    }
    redirect('riwayat.php');
}

$per = 10;
$hal = halaman();
$st = $pdo->prepare('SELECT COUNT(*) FROM peminjamans WHERE user_id = ?');
$st->execute([$u['id']]);
$total = (int) $st->fetchColumn();
$off = ($hal - 1) * $per;
$st = $pdo->prepare("SELECT p.*, b.judul FROM peminjamans p JOIN bukus b ON b.id = p.buku_id WHERE p.user_id = ? ORDER BY p.id DESC LIMIT $per OFFSET $off");
$st->execute([$u['id']]);
$daftar = $st->fetchAll();

layout_start('Riwayat pinjam');
?>
<div class="kartu p-3 p-md-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Buku</th><th>Tanggal pinjam</th><th>Jatuh tempo</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $p) : $denda = $p['status'] === 'dipinjam' ? hitung_denda($p['jatuh_tempo']) : (float) $p['denda']; ?>
                <tr>
                    <td class="fw-medium"><?= e($p['judul']) ?></td>
                    <td class="text-nowrap"><?= tgl($p['tanggal_pinjam']) ?></td>
                    <td class="text-nowrap"><?= tgl($p['jatuh_tempo']) ?></td>
                    <td>
                        <?php if ($p['status'] === 'kembali') : ?>
                            <span class="badge text-bg-success">Kembali</span>
                        <?php elseif ($denda > 0) : ?>
                            <span class="badge text-bg-danger">Terlambat</span>
                        <?php else : ?>
                            <span class="badge text-bg-primary">Dipinjam</span>
                        <?php endif; ?>
                        <?php if ($denda > 0) : ?><div class="small text-danger">Denda <?= rupiah($denda) ?></div><?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if ($p['status'] === 'dipinjam' && !$p['diperpanjang'] && $denda <= 0) : ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="perpanjang">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary">Perpanjang</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$daftar) : ?><tr><td colspan="5" class="text-center text-secondary py-4">Belum ada riwayat peminjaman.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= pager($total, $per, $hal) ?>
</div>
<?php layout_end(); ?>