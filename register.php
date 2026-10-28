<?php
require __DIR__ . '/config.php';

if (!empty($_SESSION['uid'])) {
    redirect('dashboard.php');
}

$f = ['name' => '', 'email' => ''];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Kolom jebakan untuk bot: manusia tidak melihatnya, jadi harus kosong.
    if (($_POST['website'] ?? '') !== '') {
        redirect('login.php');
    }

    $f['name'] = trim($_POST['name'] ?? '');
    $f['email'] = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $konf = $_POST['password_confirmation'] ?? '';

    if ($f['name'] === '' || mb_strlen($f['name']) > 255) $err[] = 'Nama lengkap wajib diisi (maksimal 255 karakter).';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 255) $err[] = 'Email tidak valid.';
    if (strlen($pass) < 8) $err[] = 'Password minimal 8 karakter.';
    elseif ($pass !== $konf) $err[] = 'Konfirmasi password tidak sama.';

    if (!$err) {
        $st = db()->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$f['email']]);
        if ($st->fetch()) $err[] = 'Email sudah terdaftar. Silakan masuk.';
    }

    if (!$err) {
        try {
            // Role selalu member. Pendaftar tidak bisa memilih role sendiri.
            db()->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'member', 'aktif')")
                ->execute([$f['name'], $f['email'], password_hash($pass, PASSWORD_DEFAULT)]);
            buat_no_anggota((int) db()->lastInsertId());
            flash('success', 'Akun berhasil dibuat. Silakan masuk.');
            redirect('login.php');
        } catch (PDOException $ex) {
            $err[] = $ex->getCode() === '23000' ? 'Email sudah terdaftar. Silakan masuk.' : 'Pendaftaran gagal. Coba lagi sebentar lagi.';
        }
    }
}
$perpus = setting('nama_perpustakaan', 'Perpustakaan');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar | <?= e($perpus) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', system-ui, sans-serif; background: linear-gradient(160deg, #14532d 0%, #052e16 100%); font-size: .9rem; }
        .logo { width: 52px; height: 52px; border-radius: 16px; background: #7fdc9f; color: #052e16; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .kartu { border: 0; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,.28); }
        .eyebrow { font-size: .66rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #66766b; }
        .form-control { border-radius: 10px; }
        .form-control:focus { border-color: #14532d; box-shadow: 0 0 0 .2rem rgba(20,83,45,.18); }
        .btn-utama { background: #14532d; border-color: #14532d; color: #fff; font-weight: 600; border-radius: 10px; padding: .6rem; }
        .btn-utama:hover { background: #052e16; color: #fff; }
        a { color: #14532d; font-weight: 600; }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100 py-4">
<div class="container" style="max-width:430px">
    <div class="card kartu">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="logo"><i class="bi bi-book-half"></i></div>
                <div class="eyebrow mt-3">Pendaftaran anggota</div>
                <h1 class="h4 fw-bold mb-1"><?= e($perpus) ?></h1>
                <p class="text-secondary mb-0 small">Buat akun untuk meminjam buku</p>
            </div>
            <?php if ($err) : ?>
                <div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3"><?php foreach ($err as $m) : ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <div style="position:absolute;left:-9999px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                <div class="mb-3">
                    <label for="name" class="form-label small fw-semibold">Nama lengkap</label>
                    <input type="text" id="name" name="name" value="<?= e($f['name']) ?>" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label small fw-semibold">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($f['email']) ?>" class="form-control" placeholder="name@company.com" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label small fw-semibold">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Minimal 8 karakter" required>
                </div>
                <div class="mb-4">
                    <label for="password_confirmation" class="form-label small fw-semibold">Konfirmasi password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Ulangi password" required>
                </div>
                <button type="submit" class="btn btn-utama w-100">Daftar</button>
            </form>
            <p class="text-center small text-secondary mt-4 mb-0">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
        </div>
    </div>
</div>
</body>
</html>