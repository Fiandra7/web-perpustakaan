<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require_role('super_admin', 'admin');
$pdo = db();
$hariIni = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'lunas') {
    csrf_check();
    $st = $pdo->prepare("UPDATE peminjamans SET denda_lunas = 1 WHERE id = ? AND status = 'kembali' AND denda > 0 AND denda_lunas = 0");
    $st->execute([(int) ($_POST['id'] ?? 0)]);
    flash($st->rowCount() ? 'success' : 'danger', $st->rowCount() ? 'Denda ditandai lunas.' : 'Denda tidak ditemukan atau sudah lunas.');
    redirect('denda.php');
}

$st = $pdo->prepare("SELECT p.*, u.name, u.no_anggota, b.judul FROM peminjamans p JOIN users u ON u.id = p.user_id JOIN bukus b ON b.id = p.buku_id
    WHERE (p.status = 'kembali' AND p.denda > 0) OR (p.status = 'dipinjam' AND p.jatuh_tempo < ?) ORDER BY p.id DESC");
$st->execute([$hariIni]);

$semua = [];
$belum = 0.0;
$lunas = 0.0;
foreach ($st->fetchAll() as $r) {
    $r['jumlah'] = $r['status'] === 'kembali' ? (float) $r['denda'] : hitung_denda($r['jatuh_tempo']);
    $r['sudah'] = $r['status'] === 'kembali' && (int) $r['denda_lunas'] === 1;
    if ($r['sudah']) {
        $lunas += $r['jumlah'];
    } else {
        $belum += $r['jumlah'];
    }
    $semua[] = $r;
}

$filter = $_GET['status'] ?? 'belum';
$tampil = array_values(array_filter($semua, fn ($r) => $filter === 'semua' || ($filter === 'lunas' ? $r['sudah'] : !$r['sudah'])));

$per = 10;
$hal = halaman();
$daftar = array_slice($tampil, ($hal - 1) * $per, $per);

layout_start('Denda Keterlambatan');
?>
<div class="row g-3 mb-3">
    <?php
    kartu_stat('Belum lunas', rupiah($belum), 'termasuk yang belum dikembalikan', 'bi-hourglass-split');
    kartu_stat('Sudah lunas', rupiah($lunas), 'tercatat di sistem', 'bi-check2-circle');
    kartu_stat('Tarif denda', rupiah(denda_per_hari()), 'per hari, ubah di Pengaturan', 'bi-cash-coin');
    ?>
</div>
<div class="kartu p-4">
    <div class="btn-group mb-3" role="group" aria-label="Filter denda">
        <?php foreach (['belum' => 'Belum lunas', 'lunas' => 'Lunas', 'semua' => 'Semua'] as $nilai => $label) : ?>
            <a href="?status=<?= $nilai ?>" class="btn btn-sm <?= $filter === $nilai ? 'btn-utama' : 'btn-outline-secondary' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Anggota</th><th>Buku</th><th>Jatuh tempo</th><th>Denda</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $r) : ?>
                <tr>
                    <td><div class="fw-semibold"><?= e($r['name']) ?></div><div class="small text-secondary"><?= e($r['no_anggota'] ?? '-') ?></div></td>
                    <td><?= e($r['judul']) ?></td>
                    <td class="text-nowrap"><?= tgl($r['jatuh_tempo']) ?></td>
                    <td class="text-nowrap fw-semibold"><?= rupiah($r['jumlah']) ?></td>
                    <td>
                        <?php if ($r['sudah']) : ?><span class="badge text-bg-success">Lunas</span>
                        <?php elseif ($r['status'] === 'dipinjam') : ?><span class="badge text-bg-danger">Belum dikembalikan</span>
                        <?php else : ?><span class="badge text-bg-warning">Belum dibayar</span><?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if ($r['status'] === 'kembali' && !$r['sudah']) : ?>
                            <form method="post" onsubmit="return confirm('Tandai denda ini sudah dibayar?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="lunas">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary">Tandai lunas</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$daftar) : ?><tr><td colspan="6" class="text-center text-secondary py-4">Tidak ada denda pada kategori ini.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= pager(count($tampil), $per, $hal) ?>
</div>
<?php layout_end(); ?>