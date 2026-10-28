<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require_role('super_admin', 'admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $st = $pdo->prepare("SELECT COUNT(*) FROM peminjamans WHERE user_id = ? AND status = 'dipinjam'");
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        flash('danger', 'Anggota masih punya pinjaman aktif.');
    } else {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'member'")->execute([$id]);
        flash('success', 'Anggota dihapus.');
    }
    redirect('anggota.php');
}

$q = trim($_GET['q'] ?? '');
$where = "u.role = 'member'";
$par = [];
if ($q !== '') {
    $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.no_anggota LIKE ?)';
    array_push($par, "%$q%", "%$q%", "%$q%");
}

$per = 10;
$hal = halaman();
$st = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where");
$st->execute($par);
$total = (int) $st->fetchColumn();
$off = ($hal - 1) * $per;
$st = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM peminjamans p WHERE p.user_id = u.id AND p.status = 'dipinjam') aktif FROM users u WHERE $where ORDER BY u.name LIMIT $per OFFSET $off");
$st->execute($par);
$daftar = $st->fetchAll();

layout_start('Manajemen Anggota');
?>
<div class="kartu p-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
        <form method="get" class="d-flex gap-2 flex-grow-1" style="max-width:460px">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Cari nama, email, atau nomor anggota">
            <button class="btn btn-outline-secondary" aria-label="Cari"><i class="bi bi-search"></i></button>
        </form>
        <a href="anggota_form.php" class="btn btn-utama"><i class="bi bi-person-plus me-1"></i>Tambah anggota</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>No. anggota</th><th>Nama</th><th>Pinjaman aktif</th><th>Denda</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $a) : [$denda] = denda_belum_lunas((int) $a['id']); ?>
                <tr>
                    <td class="text-nowrap"><?= e($a['no_anggota'] ?? '-') ?></td>
                    <td><div class="fw-semibold"><?= e($a['name']) ?></div><div class="small text-secondary"><?= e($a['email']) ?></div></td>
                    <td><?= fmt($a['aktif']) ?> / <?= maks_pinjam() ?></td>
                    <td><?= $denda > 0 ? '<span class="text-danger">' . rupiah($denda) . '</span>' : '-' ?></td>
                    <td><span class="badge text-bg-<?= $a['status'] === 'aktif' ? 'success' : 'danger' ?>"><?= ucfirst($a['status']) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a href="anggota_form.php?id=<?= (int) $a['id'] ?>" class="btn btn-sm btn-outline-secondary" aria-label="Ubah"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus anggota ini?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" aria-label="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$daftar) : ?><tr><td colspan="6" class="text-center text-secondary py-4">Belum ada anggota yang cocok.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= pager($total, $per, $hal) ?>
</div>
<?php layout_end(); ?>