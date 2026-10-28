<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require_role('super_admin', 'admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);

    $st = $pdo->prepare("SELECT COUNT(*) FROM peminjamans WHERE buku_id = ? AND status = 'dipinjam'");
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        flash('danger', 'Buku masih dipinjam dan belum bisa dihapus.');
    } else {
        $st = $pdo->prepare('SELECT sampul FROM bukus WHERE id = ?');
        $st->execute([$id]);
        hapus_sampul($st->fetchColumn() ?: null);
        $pdo->prepare('DELETE FROM bukus WHERE id = ?')->execute([$id]);
        flash('success', 'Buku dihapus.');
    }
    redirect('buku.php');
}

$q = trim($_GET['q'] ?? '');
$kat = trim($_GET['kategori'] ?? '');
$where = ['1=1'];
$par = [];
if ($q !== '') {
    $where[] = '(judul LIKE ? OR pengarang LIKE ? OR isbn LIKE ?)';
    array_push($par, "%$q%", "%$q%", "%$q%");
}
if ($kat !== '') {
    $where[] = 'kategori = ?';
    $par[] = $kat;
}
$w = implode(' AND ', $where);

$per = 10;
$hal = halaman();
$st = $pdo->prepare("SELECT COUNT(*) FROM bukus WHERE $w");
$st->execute($par);
$total = (int) $st->fetchColumn();

$off = ($hal - 1) * $per;
$st = $pdo->prepare("SELECT * FROM bukus WHERE $w ORDER BY judul LIMIT $per OFFSET $off");
$st->execute($par);
$daftar = $st->fetchAll();
$kategori = $pdo->query('SELECT DISTINCT kategori FROM bukus ORDER BY kategori')->fetchAll(PDO::FETCH_COLUMN);

layout_start('Data buku');
?>
<div class="kartu p-3 p-md-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
        <form method="get" class="d-flex gap-2 flex-grow-1" style="max-width:560px">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Cari judul, pengarang, atau ISBN">
            <select name="kategori" class="form-select" style="max-width:180px" onchange="this.form.submit()">
                <option value="">Semua kategori</option>
                <?php foreach ($kategori as $k) : ?>
                    <option value="<?= e($k) ?>" <?= $kat === $k ? 'selected' : '' ?>><?= e($k) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-secondary" aria-label="Cari"><i class="bi bi-search"></i></button>
        </form>
        <a href="buku_form.php" class="btn btn-utama"><i class="bi bi-plus-lg me-1"></i>Tambah buku</a>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th style="width:56px"></th><th>Judul</th><th>Kategori</th><th>Rak</th><th>Stok</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $b) : ?>
                <tr>
                    <td>
                        <?php if ($b['sampul']) : ?>
                            <img src="uploads/<?= e($b['sampul']) ?>" alt="Sampul <?= e($b['judul']) ?>" width="40" height="54" class="rounded object-fit-cover">
                        <?php else : ?>
                            <div class="bg-light border rounded" style="width:40px;height:54px"></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="fw-medium"><?= e($b['judul']) ?></div>
                        <div class="small text-secondary"><?= e($b['pengarang']) ?><?= $b['tahun'] ? ', ' . (int) $b['tahun'] : '' ?></div>
                    </td>
                    <td><?= e($b['kategori']) ?></td>
                    <td><?= e($b['rak'] ?? '-') ?></td>
                    <td><span class="badge text-bg-<?= (float) $b['stok'] >= 1 ? 'success' : 'danger' ?>">Stok <?= fmt($b['stok']) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a href="buku_form.php?id=<?= (int) $b['id'] ?>" class="btn btn-sm btn-outline-secondary" aria-label="Ubah"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus buku ini?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" aria-label="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$daftar) : ?>
                <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada buku. Tambahkan buku pertama.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= pager($total, $per, $hal) ?>
</div>
<?php layout_end(); ?>