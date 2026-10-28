<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require_role('super_admin', 'admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');

    if ($aksi === 'tambah' || $aksi === 'ubah') {
        $st = $pdo->prepare('SELECT id FROM kategoris WHERE nama = ? AND id <> ?');
        $st->execute([$nama, $aksi === 'ubah' ? $id : 0]);
        if ($nama === '' || mb_strlen($nama) > 100) {
            flash('danger', 'Nama kategori wajib diisi (maksimal 100 karakter).');
        } elseif ($st->fetch()) {
            flash('danger', 'Kategori itu sudah ada.');
        } elseif ($aksi === 'tambah') {
            $pdo->prepare('INSERT INTO kategoris (nama) VALUES (?)')->execute([$nama]);
            flash('success', 'Kategori ditambahkan.');
        } else {
            $pdo->beginTransaction();
            $lama = $pdo->prepare('SELECT nama FROM kategoris WHERE id = ?');
            $lama->execute([$id]);
            $namaLama = $lama->fetchColumn();
            if ($namaLama !== false) {
                $pdo->prepare('UPDATE kategoris SET nama = ? WHERE id = ?')->execute([$nama, $id]);
                $pdo->prepare('UPDATE bukus SET kategori = ? WHERE kategori = ?')->execute([$nama, $namaLama]);
            }
            $pdo->commit();
            flash('success', 'Kategori diperbarui.');
        }
    }

    if ($aksi === 'hapus') {
        $lama = $pdo->prepare('SELECT nama FROM kategoris WHERE id = ?');
        $lama->execute([$id]);
        $namaLama = $lama->fetchColumn();
        $pakai = $pdo->prepare('SELECT COUNT(*) FROM bukus WHERE kategori = ?');
        $pakai->execute([$namaLama]);
        if ($namaLama === false) {
            flash('danger', 'Kategori tidak ditemukan.');
        } elseif ((int) $pakai->fetchColumn() > 0) {
            flash('danger', 'Kategori masih dipakai buku dan belum bisa dihapus.');
        } else {
            $pdo->prepare('DELETE FROM kategoris WHERE id = ?')->execute([$id]);
            flash('success', 'Kategori dihapus.');
        }
    }
    redirect('kategori.php');
}

$daftar = $pdo->query('SELECT k.id, k.nama, COUNT(b.id) jumlah FROM kategoris k LEFT JOIN bukus b ON b.kategori = k.nama GROUP BY k.id, k.nama ORDER BY k.nama')->fetchAll();

layout_start('Kategori Buku');
?>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="kartu p-4">
            <h2 class="h6 mb-1">Daftar Kategori</h2>
            <p class="small text-secondary">Mengganti nama kategori ikut memperbarui semua buku di dalamnya.</p>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Nama</th><th>Jumlah judul</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($daftar as $k) : ?>
                        <tr>
                            <td>
                                <form method="post" class="d-flex gap-2" id="ubah-<?= (int) $k['id'] ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="ubah">
                                    <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                                    <input type="text" name="nama" value="<?= e($k['nama']) ?>" class="form-control form-control-sm" aria-label="Nama kategori" required>
                                </form>
                            </td>
                            <td><?= fmt($k['jumlah']) ?></td>
                            <td class="text-end text-nowrap">
                                <button form="ubah-<?= (int) $k['id'] ?>" class="btn btn-sm btn-outline-secondary">Simpan</button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Hapus kategori ini?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" aria-label="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$daftar) : ?><tr><td colspan="3" class="text-center text-secondary py-4">Belum ada kategori. Tambahkan yang pertama.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="kartu p-4">
            <h2 class="h6 mb-3">Tambah Kategori</h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="tambah">
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="nama">Nama kategori</label>
                    <input type="text" id="nama" name="nama" class="form-control" placeholder="Contoh: Teknologi" required>
                </div>
                <button class="btn btn-utama w-100">Simpan kategori</button>
            </form>
        </div>
    </div>
</div>
<?php layout_end(); ?>