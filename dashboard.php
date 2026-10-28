<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
$u = require_login();
$pdo = db();
$hariIni = date('Y-m-d');
$namaRole = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'member' => 'Anggota'][$u['role']];
$depan = explode(' ', trim($u['name']))[0];

$pengumuman = $pdo->query('SELECT judul, isi, created_at FROM pengumumans ORDER BY id DESC LIMIT 3')->fetchAll();

layout_start($u['role'] === 'member' ? 'Beranda Anggota' : 'Ringkasan Eksekutif');
?>
<div class="sambutan mb-3"><i class="bi bi-stars me-1"></i> Selamat datang di Portal <?= $namaRole ?>, <?= e($depan) ?>.</div>
<?php

/* ---------------- Tampilan anggota ---------------- */
if ($u['role'] === 'member') {
    $st = $pdo->prepare("SELECT p.*, b.judul FROM peminjamans p JOIN bukus b ON b.id = p.buku_id WHERE p.user_id = ? AND p.status = 'dipinjam' ORDER BY p.jatuh_tempo");
    $st->execute([$u['id']]);
    $pinjaman = $st->fetchAll();
    [$totalDenda] = denda_belum_lunas((int) $u['id']);
    $terdekat = $pinjaman ? tgl($pinjaman[0]['jatuh_tempo']) : '-';
    ?>
    <div class="kartu p-4 mb-3">
        <div class="eyebrow">Kartu anggota · <?= tgl($hariIni) ?></div>
        <h2 class="h4 mt-1 mb-1"><?= e($u['name']) ?></h2>
        <p class="text-secondary mb-0">Nomor anggota <?= e($u['no_anggota'] ?? '-') ?>. Batas pinjam <?= maks_pinjam() ?> buku selama <?= lama_pinjam() ?> hari.</p>
    </div>
    <div class="row g-3 mb-3">
        <?php
        kartu_stat('Sedang dipinjam', fmt(count($pinjaman)), 'dari batas ' . maks_pinjam() . ' buku', 'bi-journal-bookmark');
        kartu_stat('Jatuh tempo terdekat', $terdekat, 'perpanjang dari menu Pinjaman Saya', 'bi-calendar-event');
        kartu_stat('Denda belum lunas', rupiah($totalDenda), $totalDenda > 0 ? 'lunasi di meja petugas' : 'tidak ada denda', 'bi-cash-coin');
        ?>
    </div>
    <div class="row g-3">
        <div class="col-xl-7">
            <div class="kartu p-4 h-100">
                <h2 class="h6 mb-1">Pinjaman Saya yang Aktif</h2>
                <p class="small text-secondary">Buku yang sedang kamu bawa pulang.</p>
                <?php foreach ($pinjaman as $p) : $denda = hitung_denda($p['jatuh_tempo']); ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-top">
                        <div>
                            <div class="fw-semibold"><?= e($p['judul']) ?></div>
                            <div class="small text-secondary">Jatuh tempo <?= tgl($p['jatuh_tempo']) ?></div>
                        </div>
                        <?php if ($denda > 0) : ?><span class="badge text-bg-danger">Terlambat · <?= rupiah($denda) ?></span>
                        <?php else : ?><span class="badge text-bg-primary">Dipinjam</span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$pinjaman) : ?>
                    <p class="text-secondary mb-3 border-top pt-3">Kamu belum meminjam buku. Cari buku yang ingin dibaca di katalog.</p>
                    <a href="katalog.php" class="btn btn-utama btn-sm">Buka katalog</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="kartu p-4 h-100">
                <h2 class="h6 mb-1">Pengumuman</h2>
                <p class="small text-secondary">Kabar terbaru dari perpustakaan.</p>
                <?php foreach ($pengumuman as $a) : ?>
                    <div class="py-2 border-top">
                        <div class="fw-semibold"><?= e($a['judul']) ?></div>
                        <div class="small text-secondary"><?= tgl($a['created_at']) ?></div>
                        <div class="small mt-1"><?= nl2br(e($a['isi'])) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$pengumuman) : ?><p class="text-secondary border-top pt-3 mb-0">Belum ada pengumuman.</p><?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    layout_end();
    exit;
}

/* ---------------- Tampilan Super Admin dan Admin ---------------- */
$judul    = (int) $pdo->query('SELECT COUNT(*) FROM bukus')->fetchColumn();
$kategori = (int) $pdo->query('SELECT COUNT(*) FROM kategoris')->fetchColumn();
$stok     = (float) $pdo->query('SELECT COALESCE(SUM(stok), 0) FROM bukus')->fetchColumn();
$dipinjam = (int) $pdo->query("SELECT COUNT(*) FROM peminjamans WHERE status = 'dipinjam'")->fetchColumn();
$aktif    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'aktif'")->fetchColumn();
$diblokir = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'diblokir'")->fetchColumn();
$st = $pdo->prepare("SELECT COUNT(*) FROM peminjamans WHERE status = 'dipinjam' AND jatuh_tempo < ?");
$st->execute([$hariIni]);
$terlambat = (int) $st->fetchColumn();
[$totalDenda, $jmlDenda] = denda_belum_lunas();

$terbaru = $pdo->query('SELECT p.*, u.name, b.judul FROM peminjamans p JOIN users u ON u.id = p.user_id JOIN bukus b ON b.id = p.buku_id ORDER BY p.id DESC LIMIT 5')->fetchAll();

$st = $pdo->prepare("SELECT p.jatuh_tempo, u.name, b.judul FROM peminjamans p JOIN users u ON u.id = p.user_id JOIN bukus b ON b.id = p.buku_id WHERE p.status = 'dipinjam' AND p.jatuh_tempo BETWEEN ? AND ? ORDER BY p.jatuh_tempo LIMIT 5");
$st->execute([$hariIni, date('Y-m-d', strtotime('+3 days'))]);
$segera = $st->fetchAll();

$awal = date('Y-m-01', strtotime('-5 months'));
$st = $pdo->prepare("SELECT DATE_FORMAT(tanggal_pinjam, '%Y-%m') ym, COUNT(*) c FROM peminjamans WHERE tanggal_pinjam >= ? GROUP BY ym");
$st->execute([$awal]);
$perBulan = array_column($st->fetchAll(), 'c', 'ym');
$namaBulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$grafik = [];
for ($i = 5; $i >= 0; $i--) {
    $t = strtotime("first day of -$i months");
    $grafik[] = [$namaBulan[(int) date('n', $t)], (int) ($perBulan[date('Y-m', $t)] ?? 0)];
}
$maks = max(1, max(array_column($grafik, 1)));
?>
<div class="kartu p-4 mb-3">
    <div class="eyebrow">Live monitoring · <?= tgl($hariIni) ?></div>
    <h2 class="h4 mt-1 mb-1">Ringkasan Perpustakaan</h2>
    <p class="text-secondary mb-0">Pantau koleksi, sirkulasi, dan hal yang perlu segera ditindaklanjuti.</p>
</div>

<div class="row g-3 mb-3">
    <?php
    kartu_stat('Judul buku', fmt($judul), fmt($kategori) . ' kategori', 'bi-journal-bookmark');
    kartu_stat('Total stok', fmt($stok), 'eksemplar tersedia di rak', 'bi-box-seam');
    kartu_stat('Sedang dipinjam', fmt($dipinjam), 'transaksi aktif', 'bi-arrow-left-right');
    kartu_stat('Terlambat', fmt($terlambat), 'belum dikembalikan', 'bi-exclamation-triangle');
    kartu_stat('Anggota aktif', fmt($aktif), fmt($diblokir) . ' diblokir', 'bi-people');
    kartu_stat('Denda belum lunas', rupiah($totalDenda), fmt($jmlDenda) . ' transaksi', 'bi-cash-coin');
    ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="kartu p-4 h-100">
            <h2 class="h6 mb-1">Peminjaman Terbaru</h2>
            <p class="small text-secondary">Data aktual dari seluruh anggota.</p>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Anggota</th><th>Buku</th><th>Jatuh tempo</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($terbaru as $p) : $d = $p['status'] === 'dipinjam' ? hitung_denda($p['jatuh_tempo']) : 0; ?>
                        <tr>
                            <td><?= e($p['name']) ?></td>
                            <td><?= e($p['judul']) ?></td>
                            <td class="text-nowrap"><?= tgl($p['jatuh_tempo']) ?></td>
                            <td>
                                <?php if ($p['status'] === 'kembali') : ?><span class="badge text-bg-success">Kembali</span>
                                <?php elseif ($d > 0) : ?><span class="badge text-bg-danger">Terlambat</span>
                                <?php else : ?><span class="badge text-bg-primary">Dipinjam</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$terbaru) : ?><tr><td colspan="4" class="text-center text-secondary py-4">Belum ada transaksi.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="kartu p-4 h-100">
            <h2 class="h6 mb-1">Segera Jatuh Tempo</h2>
            <p class="small text-secondary">Dalam 3 hari ke depan.</p>
            <?php foreach ($segera as $s) : ?>
                <div class="d-flex justify-content-between gap-2 py-2 border-top">
                    <div><div class="fw-semibold"><?= e($s['judul']) ?></div><div class="small text-secondary"><?= e($s['name']) ?></div></div>
                    <div class="small text-nowrap"><?= tgl($s['jatuh_tempo']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$segera) : ?><p class="text-secondary border-top pt-3 mb-0">Tidak ada pinjaman yang jatuh tempo dalam 3 hari.</p><?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="kartu p-4 h-100">
            <h2 class="h6 mb-3">Peminjaman 6 Bulan Terakhir</h2>
            <div class="d-flex align-items-end gap-3" style="height:160px">
                <?php foreach ($grafik as $i => [$bulan, $jumlah]) : ?>
                    <div class="flex-fill text-center">
                        <div class="small mb-1"><?= $jumlah ?></div>
                        <div style="height:<?= max(4, round($jumlah / $maks * 110)) ?>px;border-radius:8px 8px 0 0;background:<?= $i === 5 ? '#14532d' : '#bfe3cc' ?>"></div>
                        <div class="small text-secondary mt-1"><?= $bulan ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="kartu p-4 h-100">
            <div class="d-flex justify-content-between"><h2 class="h6 mb-1">Pengumuman</h2><a class="small" href="pengumuman.php">Kelola</a></div>
            <p class="small text-secondary">Tampil di beranda anggota.</p>
            <?php foreach ($pengumuman as $a) : ?>
                <div class="py-2 border-top"><div class="fw-semibold"><?= e($a['judul']) ?></div><div class="small text-secondary"><?= tgl($a['created_at']) ?></div></div>
            <?php endforeach; ?>
            <?php if (!$pengumuman) : ?><p class="text-secondary border-top pt-3 mb-0">Belum ada pengumuman.</p><?php endif; ?>
        </div>
    </div>
</div>
<?php layout_end(); ?>