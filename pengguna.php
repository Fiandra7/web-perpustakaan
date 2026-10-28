<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
$me = require_role('super_admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $st = $pdo->prepare("SELECT COUNT(*) FROM peminjamans WHERE user_id = ? AND status = 'dipinjam'");
    $st->execute([$id]);

    if ($id === (int) $me['id']) {
        flash('danger', 'Anda tidak bisa menghapus akun sendiri.');
    } elseif ((int) $st->fetchColumn() > 0) {
        flash('danger', 'Pengguna masih punya pinjaman aktif.');
    } else {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        flash('success', 'Pengguna dihapus.');
    }
    redirect('pengguna.php');
}

$per = 10;
$hal = halaman();
$total = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$off = ($hal - 1) * $per;
$daftar = $pdo->query("SELECT * FROM users ORDER BY CASE role WHEN 'super_admin' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END, name LIMIT $per OFFSET $off")->fetchAll();

$namaRole = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'member' => 'Member'];
$warnaRole = ['super_admin' => 'dark', 'admin' => 'primary', 'member' => 'success'];

layout_start('Pengguna');
?>
<div class="kartu p-3 p-md-4">
    <div class="d-flex justify-content-end mb-3">
        <a href="pengguna_form.php" class="btn btn-utama"><i class="bi bi-person-plus me-1"></i>Tambah pengguna</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $p) : ?>
                <tr>
                    <td class="fw-medium"><?= e($p['name']) ?></td>
                    <td><?= e($p['email']) ?></td>
                    <td><span class="badge text-bg-<?= $warnaRole[$p['role']] ?>"><?= $namaRole[$p['role']] ?></span></td>
                    <td><span class="badge text-bg-<?= $p['status'] === 'aktif' ? 'success' : 'danger' ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a href="pengguna_form.php?id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-secondary" aria-label="Ubah"><i class="bi bi-pencil"></i></a>
                        <?php if ((int) $p['id'] !== (int) $me['id']) : ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Hapus pengguna ini?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" aria-label="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pager($total, $per, $hal) ?>
</div>
<?php layout_end(); ?>