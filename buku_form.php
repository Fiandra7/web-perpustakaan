<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require_role('super_admin', 'admin');
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$buku = ['judul' => '', 'pengarang' => '', 'penerbit' => '', 'tahun' => '', 'isbn' => '', 'kategori' => 'Umum', 'rak' => '', 'stok' => '1', 'sampul' => null];

if ($id) {
    $st = $pdo->prepare('SELECT * FROM bukus WHERE id = ?');
    $st->execute([$id]);
    $baris = $st->fetch();
    if (!$baris) {
        flash('danger', 'Buku tidak ditemukan.');
        redirect('buku.php');
    }
    $buku = $baris;
    $buku['stok'] = rtrim(rtrim(number_format((float) $baris['stok'], 2, '.', ''), '0'), '.');
}

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['judul', 'pengarang', 'penerbit', 'tahun', 'isbn', 'kategori', 'rak', 'stok'] as $k) {
        $buku[$k] = trim($_POST[$k] ?? '');
    }

    if ($buku['judul'] === '') $err[] = 'Judul wajib diisi.';
    if ($buku['pengarang'] === '') $err[] = 'Pengarang wajib diisi.';
    if ($buku['kategori'] === '') $err[] = 'Kategori wajib diisi.';
    if ($buku['tahun'] !== '' && (!ctype_digit($buku['tahun']) || $buku['tahun'] < 1000 || $buku['tahun'] > (int) date('Y'))) {
        $err[] = 'Tahun terbit tidak valid.';
    }

    $stok = angka_input($buku['stok']);
    if ($stok === null || $stok < 0) {
        $err[] = 'Stok harus berupa angka 0 atau lebih.';
    }

    if ($buku['isbn'] !== '') {
        $st = $pdo->prepare('SELECT id FROM bukus WHERE isbn = ? AND id <> ?');
        $st->execute([$buku['isbn'], $id]);
        if ($st->fetch()) $err[] = 'ISBN sudah dipakai buku lain.';
    }

    $sampul = $buku['sampul'];
    if (!$err && !empty($_FILES['sampul']['name'])) {
        [$baru, $galat] = simpan_sampul($_FILES['sampul']);
        if ($galat) {
            $err[] = $galat;
        } else {
            hapus_sampul($sampul);
            $sampul = $baru;
        }
    }

    if (!$err) {
        $data = [
            $buku['judul'], $buku['pengarang'], $buku['penerbit'] ?: null, $buku['tahun'] !== '' ? (int) $buku['tahun'] : null,
            $buku['isbn'] ?: null, $buku['kategori'], $buku['rak'] ?: null, $stok, $sampul,
        ];
        if ($id) {
            $pdo->prepare('UPDATE bukus SET judul=?, pengarang=?, penerbit=?, tahun=?, isbn=?, kategori=?, rak=?, stok=?, sampul=? WHERE id=?')
                ->execute([...$data, $id]);
            flash('success', 'Data buku diperbarui.');
        } else {
            $pdo->prepare('INSERT INTO bukus (judul, pengarang, penerbit, tahun, isbn, kategori, rak, stok, sampul) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute($data);
            flash('success', 'Buku berhasil ditambahkan.');
        }
        redirect('buku.php');
    }
}

$daftarKategori = $pdo->query('SELECT nama FROM kategoris ORDER BY nama')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($buku['kategori'], $daftarKategori, true)) {
    array_unshift($daftarKategori, $buku['kategori']);
}

layout_start($id ? 'Ubah buku' : 'Tambah buku');
?>
<div class="kartu p-3 p-md-4" style="max-width:760px">
    <?php if ($err) : ?>
        <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($err as $m) : ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="judul">Judul</label>
            <input type="text" id="judul" name="judul" class="form-control" value="<?= e($buku['judul']) ?>" required>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="pengarang">Pengarang</label>
                <input type="text" id="pengarang" name="pengarang" class="form-control" value="<?= e($buku['pengarang']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="penerbit">Penerbit</label>
                <input type="text" id="penerbit" name="penerbit" class="form-control" value="<?= e($buku['penerbit']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="isbn">ISBN</label>
                <input type="text" id="isbn" name="isbn" class="form-control" value="<?= e($buku['isbn']) ?>" placeholder="978-602-...">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="tahun">Tahun terbit</label>
                <input type="text" inputmode="numeric" id="tahun" name="tahun" class="form-control" value="<?= e($buku['tahun']) ?>" placeholder="2008">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="kategori">Kategori</label>
                <select id="kategori" name="kategori" class="form-select" required>
                    <?php foreach ($daftarKategori as $k) : ?>
                        <option value="<?= e($k) ?>" <?= $buku['kategori'] === $k ? 'selected' : '' ?>><?= e($k) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><a href="kategori.php">Kelola kategori</a></div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="rak">Rak</label>
                <input type="text" id="rak" name="rak" class="form-control" value="<?= e($buku['rak']) ?>" placeholder="A2">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="stok">Stok</label>
                <?= input_angka('stok', $buku['stok'], ['langkah' => 1, 'min' => 0, 'wajib' => true]) ?>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label" for="sampul">Sampul</label>
            <input type="file" id="sampul" name="sampul" accept="image/jpeg,image/png,image/webp" class="form-control">
            <?php if ($buku['sampul']) : ?>
                <img src="uploads/<?= e($buku['sampul']) ?>" alt="Sampul saat ini" height="80" class="rounded mt-2">
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <a href="buku.php" class="btn btn-outline-secondary">Batal</a>
            <button type="submit" class="btn btn-utama"><?= $id ? 'Simpan perubahan' : 'Simpan buku' ?></button>
        </div>
    </form>
</div>
<?php layout_end(); ?>