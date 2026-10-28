<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require_role('super_admin', 'admin');
$pdo = db();
$hariIni = date('Y-m-d');

/* Unduh laporan CSV (pemisah titik koma, angka memakai koma, cocok untuk Excel Indonesia) */
if (($_GET['export'] ?? '') === '1') {
    $baris = $pdo->query('SELECT p.*, u.name, b.judul FROM peminjamans p JOIN users u ON u.id = p.user_id JOIN bukus b ON b.id = p.buku_id ORDER BY p.id DESC')->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-peminjaman-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Anggota', 'Buku', 'Tanggal pinjam', 'Jatuh tempo', 'Tanggal kembali', 'Status', 'Denda (Rp)'], ';');
    foreach ($baris as $p) {
        $denda = $p['status'] === 'kembali' ? $p['denda'] : hitung_denda($p['jatuh_tempo']);
        fputcsv($out, [$p['name'], $p['judul'], $p['tanggal_pinjam'], $p['jatuh_tempo'], $p['tanggal_kembali'], $p['status'], fmt($denda)], ';');
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'pinjam') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $bid = (int) ($_POST['buku_id'] ?? 0);
        $tgl = $_POST['tanggal_pinjam'] ?? '';
        $d = DateTime::createFromFormat('Y-m-d', $tgl);

        if (!$uid || !$bid || !$d || $d->format('Y-m-d') !== $tgl) {
            flash('danger', 'Lengkapi anggota, buku, dan tanggal pinjam.');
        } else {
            $pdo->beginTransaction();
            try {
                $a = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'member' AND status = 'aktif'");
                $a->execute([$uid]);
                if (!$a->fetch()) throw new RuntimeException('Anggota tidak ditemukan atau sedang diblokir.');
                $aktif = $pdo->prepare("SELECT COUNT(*) FROM peminjamans WHERE user_id = ? AND status = 'dipinjam'");
                $aktif->execute([$uid]);
                if ((int) $aktif->fetchColumn() >= maks_pinjam()) throw new RuntimeException('Anggota sudah mencapai batas ' . maks_pinjam() . ' buku yang sedang dipinjam.');
                if (denda_belum_lunas($uid)[0] > 0) throw new RuntimeException('Anggota masih punya denda yang belum lunas.');

                $b = $pdo->prepare('SELECT stok FROM bukus WHERE id = ? FOR UPDATE');
                $b->execute([$bid]);
                $buku = $b->fetch();
                if (!$buku || (float) $buku['stok'] < 1) throw new RuntimeException('Stok buku habis.');

                $pdo->prepare('UPDATE bukus SET stok = stok - 1 WHERE id = ?')->execute([$bid]);
                $tempo = (clone $d)->modify('+' . lama_pinjam() . ' days')->format('Y-m-d');
                $pdo->prepare('INSERT INTO peminjamans (user_id, buku_id, tanggal_pinjam, jatuh_tempo) VALUES (?,?,?,?)')
                    ->execute([$uid, $bid, $tgl, $tempo]);
                $pdo->commit();
                flash('success', 'Peminjaman disimpan.');
            } catch (RuntimeException $ex) {
                $pdo->rollBack();
                flash('danger', $ex->getMessage());
            }
        }
    }

    if ($aksi === 'kembali') {
        $pid = (int) ($_POST['id'] ?? 0);
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM peminjamans WHERE id = ? FOR UPDATE');
        $st->execute([$pid]);
        $p = $st->fetch();

        if (!$p || $p['status'] === 'kembali') {
            $pdo->rollBack();
            flash('danger', 'Peminjaman tidak ditemukan atau sudah dikembalikan.');
        } else {
            $denda = hitung_denda($p['jatuh_tempo'], $hariIni);
            $pdo->prepare("UPDATE peminjamans SET status = 'kembali', tanggal_kembali = ?, denda = ? WHERE id = ?")
                ->execute([$hariIni, $denda, $pid]);
            $pdo->prepare('UPDATE bukus SET stok = stok + 1 WHERE id = ?')->execute([$p['buku_id']]);
            $pdo->commit();
            flash('success', 'Buku dikembalikan.' . ($denda > 0 ? ' Denda ' . rupiah($denda) . '.' : ''));
        }
    }
    redirect('peminjaman.php');
}

$filter = $_GET['status'] ?? 'semua';
$where = '1=1';
$par = [];
if ($filter === 'dipinjam') {
    $where = "p.status = 'dipinjam'";
} elseif ($filter === 'terlambat') {
    $where = "p.status = 'dipinjam' AND p.jatuh_tempo < ?";
    $par[] = $hariIni;
}

$per = 10;
$hal = halaman();
$st = $pdo->prepare("SELECT COUNT(*) FROM peminjamans p WHERE $where");
$st->execute($par);
$total = (int) $st->fetchColumn();

$off = ($hal - 1) * $per;
$st = $pdo->prepare("SELECT p.*, u.name, b.judul FROM peminjamans p JOIN users u ON u.id = p.user_id JOIN bukus b ON b.id = p.buku_id WHERE $where ORDER BY p.id DESC LIMIT $per OFFSET $off");
$st->execute($par);
$daftar = $st->fetchAll();

$anggota = $pdo->query("SELECT id, name, email, no_anggota FROM users WHERE role = 'member' AND status = 'aktif' ORDER BY name")->fetchAll();
$bukuTersedia = $pdo->query('SELECT id, judul, stok FROM bukus WHERE stok >= 1 ORDER BY judul')->fetchAll();

layout_start('Peminjaman');
?>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="kartu p-3 p-md-4">
            <div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
                <div class="btn-group" role="group" aria-label="Filter status">
                    <?php foreach (['semua' => 'Semua', 'dipinjam' => 'Dipinjam', 'terlambat' => 'Terlambat'] as $nilai => $label) : ?>
                        <a href="?status=<?= $nilai ?>" class="btn btn-sm <?= $filter === $nilai ? 'btn-utama' : 'btn-outline-secondary' ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
                <a href="?export=1" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Unduh laporan CSV</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Anggota</th><th>Buku</th><th>Jatuh tempo</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($daftar as $p) : $dendaSkrg = $p['status'] === 'dipinjam' ? hitung_denda($p['jatuh_tempo']) : (float) $p['denda']; ?>
                        <tr>
                            <td><?= e($p['name']) ?></td>
                            <td><?= e($p['judul']) ?></td>
                            <td class="text-nowrap"><?= tgl($p['jatuh_tempo']) ?></td>
                            <td>
                                <?php if ($p['status'] === 'kembali') : ?>
                                    <span class="badge text-bg-success">Kembali</span>
                                <?php elseif ($dendaSkrg > 0) : ?>
                                    <span class="badge text-bg-danger">Terlambat</span>
                                <?php else : ?>
                                    <span class="badge text-bg-primary">Dipinjam</span>
                                <?php endif; ?>
                                <?php if ($dendaSkrg > 0) : ?><div class="small text-danger">Denda <?= rupiah($dendaSkrg) ?></div><?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($p['status'] === 'dipinjam') : ?>
                                    <form method="post" onsubmit="return confirm('Tandai buku sudah dikembalikan?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="aksi" value="kembali">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary">Kembalikan</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$daftar) : ?>
                        <tr><td colspan="5" class="text-center text-secondary py-4">Belum ada transaksi peminjaman.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= pager($total, $per, $hal) ?>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="kartu p-3 p-md-4">
            <h2 class="h6 mb-3">Pinjam buku</h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="pinjam">
                <div class="mb-3">
                    <label class="form-label" for="user_id">Anggota</label>
                    <select id="user_id" name="user_id" class="form-select" required>
                        <option value="">Pilih anggota</option>
                        <?php foreach ($anggota as $a) : ?><option value="<?= (int) $a['id'] ?>"><?= e($a['no_anggota'] ?? '-') ?> · <?= e($a['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="buku_id">Buku</label>
                    <select id="buku_id" name="buku_id" class="form-select" required>
                        <option value="">Pilih buku</option>
                        <?php foreach ($bukuTersedia as $b) : ?><option value="<?= (int) $b['id'] ?>"><?= e($b['judul']) ?> (stok <?= fmt($b['stok']) ?>)</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="tanggal_pinjam">Tanggal pinjam</label>
                    <input type="date" id="tanggal_pinjam" name="tanggal_pinjam" class="form-control" value="<?= $hariIni ?>" required>
                </div>
                <p class="small text-secondary">Jatuh tempo otomatis <?= lama_pinjam() ?> hari. Denda <?= rupiah(denda_per_hari()) ?> per hari.</p>
                <button type="submit" class="btn btn-utama w-100">Simpan peminjaman</button>
            </form>
        </div>
    </div>
</div>
<?php layout_end(); ?>