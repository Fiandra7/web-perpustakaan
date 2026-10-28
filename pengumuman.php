<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require_role('super_admin', 'admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $judul = trim($_POST['judul'] ?? '');
        $isi = trim($_POST['isi'] ?? '');
        if ($judul === '' || $isi === '' || mb_strlen($judul) > 200) {
            flash('danger', 'Judul dan isi pengumuman wajib diisi (judul maksimal 200 karakter).');
        } else {
            $pdo->prepare('INSERT INTO pengumumans (judul, isi) VALUES (?, ?)')->execute([$judul, $isi]);
            flash('success', 'Pengumuman diterbitkan.');
        }
    }
    if ($aksi === 'hapus') {
        $pdo->prepare('DELETE FROM pengumumans WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('success', 'Pengumuman dihapus.');
    }
    redirect('pengumuman.php');
}

$daftar = $pdo->query('SELECT * FROM pengumumans ORDER BY id DESC LIMIT 30')->fetchAll();

layout_start('Pengumuman');
?>
<div class="row g-3">
    <div class="col-xl-7">
        <div class="kartu p-4">
            <h2 class="h6 mb-1">Pengumuman Terbit</h2>
            <p class="small text-secondary">Tiga yang terbaru tampil di beranda anggota.</p>
            <?php foreach ($daftar as $a) : ?>
                <div class="d-flex justify-content-between gap-3 py-3 border-top">
                    <div>
                        <div class="fw-semibold"><?= e($a['judul']) ?></div>
                        <div class="small text-secondary mb-1"><?= tgl($a['created_at']) ?></div>
                        <div class="small"><?= nl2br(e($a['isi'])) ?></div>
                    </div>
                    <form method="post" onsubmit="return confirm('Hapus pengumuman ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" aria-label="Hapus"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!$daftar) : ?><p class="text-secondary border-top pt-3 mb-0">Belum ada pengumuman. Tulis yang pertama di samping.</p><?php endif; ?>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="kartu p-4">
            <h2 class="h6 mb-3">Tulis Pengumuman</h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="tambah">
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="judul">Judul</label>
                    <input type="text" id="judul" name="judul" class="form-control" maxlength="200" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="isi">Isi</label>
                    <textarea id="isi" name="isi" rows="4" class="form-control" required></textarea>
                </div>
                <button class="btn btn-utama w-100">Terbitkan</button>
            </form>
        </div>
    </div>
</div>
<?php layout_end(); ?>