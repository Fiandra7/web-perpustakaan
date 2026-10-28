<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require_role('member');
$pdo = db();

$q = trim($_GET['q'] ?? '');
$kat = trim($_GET['kategori'] ?? '');
$where = ['1=1'];
$par = [];
if ($q !== '') {
    $where[] = '(judul LIKE ? OR pengarang LIKE ?)';
    array_push($par, "%$q%", "%$q%");
}
if ($kat !== '') {
    $where[] = 'kategori = ?';
    $par[] = $kat;
}
$w = implode(' AND ', $where);

$per = 12;
$hal = halaman();
$st = $pdo->prepare("SELECT COUNT(*) FROM bukus WHERE $w");
$st->execute($par);
$total = (int) $st->fetchColumn();
$off = ($hal - 1) * $per;
$st = $pdo->prepare("SELECT * FROM bukus WHERE $w ORDER BY judul LIMIT $per OFFSET $off");
$st->execute($par);
$daftar = $st->fetchAll();
$kategori = $pdo->query('SELECT DISTINCT kategori FROM bukus ORDER BY kategori')->fetchAll(PDO::FETCH_COLUMN);

layout_start('Katalog buku');
?>
<form method="get" class="d-flex gap-2 mb-3" style="max-width:560px">
    <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Cari judul atau pengarang">
    <select name="kategori" class="form-select" style="max-width:180px" onchange="this.form.submit()">
        <option value="">Semua kategori</option>
        <?php foreach ($kategori as $k) : ?><option value="<?= e($k) ?>" <?= $kat === $k ? 'selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-utama" aria-label="Cari"><i class="bi bi-search"></i></button>
</form>

<div class="row g-3">
    <?php foreach ($daftar as $b) : $ada = (float) $b['stok'] >= 1; ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="kartu p-3 h-100 d-flex gap-3">
                <?php if ($b['sampul']) : ?>
                    <img src="uploads/<?= e($b['sampul']) ?>" alt="Sampul <?= e($b['judul']) ?>" width="64" height="88" class="rounded object-fit-cover">
                <?php else : ?>
                    <div class="bg-light border rounded flex-shrink-0" style="width:64px;height:88px"></div>
                <?php endif; ?>
                <div>
                    <div class="fw-medium"><?= e($b['judul']) ?></div>
                    <div class="small text-secondary mb-2"><?= e($b['pengarang']) ?> · <?= e($b['kategori']) ?></div>
                    <span class="badge text-bg-<?= $ada ? 'success' : 'warning' ?>"><?= $ada ? 'Tersedia ' . fmt($b['stok']) : 'Sedang dipinjam' ?></span>
                    <?php if ($b['rak']) : ?><div class="small text-secondary mt-2">Rak <?= e($b['rak']) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$daftar) : ?><p class="text-secondary">Tidak ada buku yang cocok. Coba kata kunci lain.</p><?php endif; ?>
</div>
<div class="mt-3"><?= pager($total, $per, $hal) ?></div>
<?php layout_end(); ?>