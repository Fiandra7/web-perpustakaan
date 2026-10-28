<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
$u = require_login();
$pdo = db();

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nama = trim($_POST['name'] ?? '');
    $lama = $_POST['current_password'] ?? '';
    $baru = $_POST['password'] ?? '';
    $konf = $_POST['password_confirmation'] ?? '';

    if ($nama === '') $err[] = 'Nama wajib diisi.';

    if ($baru !== '') {
        $st = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $st->execute([$u['id']]);
        if (!password_verify($lama, (string) $st->fetchColumn())) $err[] = 'Password saat ini salah.';
        if (strlen($baru) < 8) $err[] = 'Password baru minimal 8 karakter.';
        elseif ($baru !== $konf) $err[] = 'Konfirmasi password baru tidak sama.';
    }

    if (!$err) {
        $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$nama, $u['id']]);
        if ($baru !== '') {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($baru, PASSWORD_DEFAULT), $u['id']]);
        }
        flash('success', 'Profil diperbarui.');
        redirect('profil.php');
    }
}

layout_start('Profil');
?>
<div class="kartu p-3 p-md-4" style="max-width:520px">
    <?php if ($err) : ?>
        <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($err as $m) : ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="name">Nama</label>
            <input type="text" id="name" name="name" class="form-control" value="<?= e($_POST['name'] ?? $u['name']) ?>" required>
        </div>
        <div class="mb-4">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" class="form-control" value="<?= e($u['email']) ?>" disabled>
        </div>
        <h2 class="h6 mb-3">Ganti password</h2>
        <div class="mb-3">
            <label class="form-label" for="current_password">Password saat ini</label>
            <input type="password" id="current_password" name="current_password" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password baru</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Minimal 8 karakter">
        </div>
        <div class="mb-4">
            <label class="form-label" for="password_confirmation">Konfirmasi password baru</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Ulangi password">
        </div>
        <button type="submit" class="btn btn-utama">Simpan profil</button>
    </form>
</div>
<?php layout_end(); ?>