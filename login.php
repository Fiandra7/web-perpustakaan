<?php
require __DIR__ . '/config.php';

if (!empty($_SESSION['uid'])) {
    redirect('dashboard.php');
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();

    if (!$u || !password_verify($_POST['password'] ?? '', $u['password'])) {
        $error = 'Email atau password salah.';
    } elseif ($u['status'] !== 'aktif') {
        $error = 'Akun Anda diblokir. Hubungi admin perpustakaan.';
    } else {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];   // role dibaca dari database di setiap halaman
        redirect('dashboard.php');
    }
}
$perpus = setting('nama_perpustakaan', 'Perpustakaan');
$pesanFlash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk | <?= e($perpus) ?></title>
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
<body class="d-flex align-items-center min-vh-100">
<div class="container" style="max-width:430px">
    <div class="card kartu">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="logo"><i class="bi bi-book-half"></i></div>
                <div class="eyebrow mt-3">Sistem inventaris</div>
                <h1 class="h4 fw-bold mb-1"><?= e($perpus) ?></h1>
                <p class="text-secondary mb-0 small">Masuk dengan akun Anda</p>
            </div>
            <?php foreach ($pesanFlash as [$jenis, $pesan]) : ?><div class="alert alert-<?= e($jenis) ?> py-2 small"><?= e($pesan) ?></div><?php endforeach; ?>
            <?php if ($error) : ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="email" class="form-label small fw-semibold">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" class="form-control" placeholder="name@company.com" required autofocus>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label small fw-semibold">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-utama w-100">Masuk</button>
            </form>
            <p class="text-center small text-secondary mt-4 mb-0">Belum daftar? <a href="register.php">Daftar di sini</a></p>
        </div>
    </div>
</div>
</body>
</html>