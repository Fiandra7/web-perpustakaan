<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require_role('super_admin');
$pdo = db();

$err = [];
$f = [
    'nama_perpustakaan' => setting('nama_perpustakaan', 'Perpustakaan'),
    'denda_per_hari'    => rtrim(rtrim(number_format(denda_per_hari(), 2, '.', ''), '0'), '.'),
    'lama_pinjam_hari'  => (string) lama_pinjam(),
    'maks_pinjam'       => (string) maks_pinjam(),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($f as $k => $_) {
        $f[$k] = trim($_POST[$k] ?? '');
    }

    if ($f['nama_perpustakaan'] === '' || mb_strlen($f['nama_perpustakaan']) > 100) $err[] = 'Nama perpustakaan wajib diisi (maksimal 100 karakter).';
    $denda = angka_input($f['denda_per_hari']);
    if ($denda === null || $denda < 0) $err[] = 'Denda per hari harus berupa angka 0 atau lebih.';
    if (!ctype_digit($f['lama_pinjam_hari']) || $f['lama_pinjam_hari'] < 1 || $f['lama_pinjam_hari'] > 60) $err[] = 'Lama pinjam harus 1 sampai 60 hari.';
    if (!ctype_digit($f['maks_pinjam']) || $f['maks_pinjam'] < 1 || $f['maks_pinjam'] > 20) $err[] = 'Batas pinjam harus 1 sampai 20 buku.';

    if (!$err) {
        $simpan = $pdo->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)');
        $simpan->execute(['nama_perpustakaan', $f['nama_perpustakaan']]);
        $simpan->execute(['denda_per_hari', number_format($denda, 2, '.', '')]);
        $simpan->execute(['lama_pinjam_hari', $f['lama_pinjam_hari']]);
        $simpan->execute(['maks_pinjam', $f['maks_pinjam']]);
        flash('success', 'Pengaturan disimpan.');
        redirect('pengaturan.php');
    }
}

layout_start('Pengaturan');
?>
<div class="kartu p-4" style="max-width:600px">
    <h2 class="h6 mb-1">Aturan Perpustakaan</h2>
    <p class="small text-secondary">Berlaku untuk peminjaman berikutnya. Transaksi yang sudah berjalan tidak berubah.</p>
    <?php if ($err) : ?>
        <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($err as $m) : ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label small fw-semibold" for="nama_perpustakaan">Nama perpustakaan</label>
            <input type="text" id="nama_perpustakaan" name="nama_perpustakaan" class="form-control" value="<?= e($f['nama_perpustakaan']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold" for="denda_per_hari">Denda per hari (Rp)</label>
            <?= input_angka('denda_per_hari', $f['denda_per_hari'], ['langkah' => 500, 'min' => 0, 'wajib' => true]) ?>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label small fw-semibold" for="lama_pinjam_hari">Lama pinjam (hari)</label>
                <?= input_angka('lama_pinjam_hari', $f['lama_pinjam_hari'], ['min' => 1, 'maks' => 60, 'bulat' => true, 'wajib' => true]) ?>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold" for="maks_pinjam">Batas pinjam per anggota (buku)</label>
                <?= input_angka('maks_pinjam', $f['maks_pinjam'], ['min' => 1, 'maks' => 20, 'bulat' => true, 'wajib' => true]) ?>
            </div>
        </div>
        <button type="submit" class="btn btn-utama">Simpan pengaturan</button>
    </form>
</div>
<?php layout_end(); ?>