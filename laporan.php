<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require_role('super_admin', 'admin');
$pdo = db();
$hariIni = date('Y-m-d');

function tanggal_valid(?string $t, string $default): string
{
    $d = DateTime::createFromFormat('Y-m-d', (string) $t);
    return ($d && $d->format('Y-m-d') === $t) ? $t : $default;
}

$dari = tanggal_valid($_GET['dari'] ?? null, date('Y-m-01'));
$sampai = tanggal_valid($_GET['sampai'] ?? null, $hariIni);
if ($dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}

$st = $pdo->prepare('SELECT p.*, u.name, u.no_anggota, b.judul FROM peminjamans p JOIN users u ON u.id = p.user_id JOIN bukus b ON b.id = p.buku_id
    WHERE p.tanggal_pinjam BETWEEN ? AND ? ORDER BY p.tanggal_pinjam, p.id');
$st->execute([$dari, $sampai]);
$baris = $st->fetchAll();

$ringkas = ['total' => count($baris), 'kembali' => 0, 'aktif' => 0, 'terlambat' => 0, 'denda' => 0.0];
foreach ($baris as &$r) {
    $r['jumlah_denda'] = $r['status'] === 'kembali' ? (float) $r['denda'] : hitung_denda($r['jatuh_tempo']);
    $r['label'] = $r['status'] === 'kembali' ? 'Kembali' : ($r['jumlah_denda'] > 0 ? 'Terlambat' : 'Dipinjam');
    $ringkas[$r['status'] === 'kembali' ? 'kembali' : 'aktif']++;
    if ($r['jumlah_denda'] > 0) {
        $ringkas['terlambat']++;
        $ringkas['denda'] += $r['jumlah_denda'];
    }
}
unset($r);

if (($_GET['export'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-' . $dari . '-sd-' . $sampai . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['No. anggota', 'Anggota', 'Buku', 'Tanggal pinjam', 'Jatuh tempo', 'Tanggal kembali', 'Status', 'Denda (Rp)'], ';');
    foreach ($baris as $r) {
        fputcsv($out, [$r['no_anggota'], $r['name'], $r['judul'], $r['tanggal_pinjam'], $r['jatuh_tempo'], $r['tanggal_kembali'], $r['label'], fmt($r['jumlah_denda'])], ';');
    }
    fclose($out);
    exit;
}

layout_start('Laporan Sirkulasi');
?>
<div class="kartu p-4 mb-3 tanpa-cetak">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small fw-semibold" for="dari">Dari tanggal</label>
            <input type="date" id="dari" name="dari" value="<?= e($dari) ?>" class="form-control">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-semibold" for="sampai">Sampai tanggal</label>
            <input type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>" class="form-control">
        </div>
        <div class="col-12 col-md-6 d-flex flex-wrap gap-2">
            <button class="btn btn-utama">Tampilkan</button>
            <a class="btn btn-outline-secondary" href="?<?= e(http_build_query(['dari' => $dari, 'sampai' => $sampai, 'export' => 1])) ?>"><i class="bi bi-download me-1"></i>Unduh CSV</a>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
        </div>
    </form>
</div>

<div class="kartu p-4">
    <div class="eyebrow">Periode</div>
    <h2 class="h5 mb-3"><?= tgl($dari) ?> sampai <?= tgl($sampai) ?></h2>
    <div class="row g-3 mb-4">
        <?php
        kartu_stat('Total peminjaman', fmt($ringkas['total']), 'pada periode ini', 'bi-arrow-left-right');
        kartu_stat('Sudah kembali', fmt($ringkas['kembali']), fmt($ringkas['aktif']) . ' masih dipinjam', 'bi-check2-circle');
        kartu_stat('Total denda', rupiah($ringkas['denda']), fmt($ringkas['terlambat']) . ' transaksi terlambat', 'bi-cash-coin');
        ?>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Tanggal pinjam</th><th>Anggota</th><th>Buku</th><th>Jatuh tempo</th><th>Kembali</th><th>Status</th><th class="text-end">Denda</th></tr></thead>
            <tbody>
            <?php foreach ($baris as $r) : ?>
                <tr>
                    <td class="text-nowrap"><?= tgl($r['tanggal_pinjam']) ?></td>
                    <td><?= e($r['name']) ?><div class="small text-secondary"><?= e($r['no_anggota'] ?? '') ?></div></td>
                    <td><?= e($r['judul']) ?></td>
                    <td class="text-nowrap"><?= tgl($r['jatuh_tempo']) ?></td>
                    <td class="text-nowrap"><?= tgl($r['tanggal_kembali']) ?></td>
                    <td><?= e($r['label']) ?></td>
                    <td class="text-end text-nowrap"><?= $r['jumlah_denda'] > 0 ? rupiah($r['jumlah_denda']) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$baris) : ?><tr><td colspan="7" class="text-center text-secondary py-4">Tidak ada peminjaman pada periode ini.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php layout_end(); ?>