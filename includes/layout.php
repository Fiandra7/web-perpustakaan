<?php
// Kerangka halaman: sidebar gelap bergradasi hijau, menu mengikuti role.

function kartu_stat(string $label, string $angka, string $sub, string $ikon): void
{
    ?>
    <div class="col-6 col-xl-4">
        <div class="kartu stat h-100">
            <div class="ikon"><i class="bi <?= e($ikon) ?>"></i></div>
            <div class="eyebrow"><?= e($label) ?></div>
            <div class="angka"><?= $angka ?></div>
            <div class="sub"><?= e($sub) ?></div>
        </div>
    </div>
    <?php
}

// Isian angka dengan panah naik dan turun di ujung kolom. Panah atas/bawah di keyboard juga bekerja.
// Opsi: langkah (besar perubahan tiap klik), min, maks, bulat (hanya bilangan bulat), wajib, id.
function input_angka(string $nama, $nilai, array $o = []): string
{
    $langkah = $o['langkah'] ?? 1;
    $min = $o['min'] ?? 0;
    $bulat = !empty($o['bulat']);
    $h  = '<div class="stepper">';
    $h .= '<input type="number" inputmode="' . ($bulat ? 'numeric' : 'decimal') . '" id="' . e($o['id'] ?? $nama) . '" name="' . e($nama) . '" value="' . e($nilai) . '"';
    $h .= ' step="' . ($bulat ? '1' : 'any') . '" min="' . e($min) . '"' . (isset($o['maks']) ? ' max="' . e($o['maks']) . '"' : '') . ' data-langkah="' . e($langkah) . '"';
    $h .= ' class="form-control"' . (!empty($o['wajib']) ? ' required' : '') . '>';
    $h .= '<div class="panah">';
    $h .= '<button type="button" data-aksi="tambah" aria-label="Tambah" tabindex="-1"><i class="bi bi-caret-up-fill"></i></button>';
    $h .= '<button type="button" data-aksi="kurang" aria-label="Kurangi" tabindex="-1"><i class="bi bi-caret-down-fill"></i></button>';
    $h .= '</div></div>';
    return $h;
}

function layout_start(string $judul): void
{
    $u = require_login();
    $self = basename($_SERVER['SCRIPT_NAME']);
    $namaRole = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'member' => 'Anggota'][$u['role']];
    $adm = ['super_admin', 'admin'];
    $perpus = setting('nama_perpustakaan', 'Perpustakaan');

    // [file, awalan untuk penanda aktif, ikon, nama menu, role yang boleh]
    $menu = [
        ['dashboard.php',  'dashboard',  'bi-grid-1x2',         'Ringkasan',     $adm],
        ['dashboard.php',  'dashboard',  'bi-house',            'Beranda',       ['member']],
        ['buku.php',       'buku',       'bi-journal-bookmark', 'Koleksi Buku',  $adm],
        ['kategori.php',   'kategori',   'bi-tags',             'Kategori',      $adm],
        ['anggota.php',    'anggota',    'bi-people',           'Anggota',       $adm],
        ['peminjaman.php', 'peminjaman', 'bi-arrow-left-right', 'Peminjaman',    $adm],
        ['denda.php',      'denda',      'bi-cash-coin',        'Denda',         $adm],
        ['laporan.php',    'laporan',    'bi-bar-chart-line',   'Laporan',       $adm],
        ['pengumuman.php', 'pengumuman', 'bi-megaphone',        'Pengumuman',    $adm],
        ['pengguna.php',   'pengguna',   'bi-person-gear',      'Akun Pengguna', ['super_admin']],
        ['pengaturan.php', 'pengaturan', 'bi-sliders',          'Pengaturan',    ['super_admin']],
        ['katalog.php',    'katalog',    'bi-search',           'Katalog Buku',  ['member']],
        ['riwayat.php',    'riwayat',    'bi-clock-history',    'Pinjaman Saya', ['member']],
    ];
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> | <?= e($perpus) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --hijau: #14532d; --hijau-gelap: #052e16; --aksen: #7fdc9f; --aksen-tua: #14532d; --aksen-muda: #dcf5e4; --latar: #f3f7f4; --garis: #e1ebe4; --lembut: #66766b; }
        body { font-family: 'Montserrat', system-ui, sans-serif; background: var(--latar); color: #1b2a21; font-size: .9rem; }
        .sidebar { width: 264px; flex: none; background: linear-gradient(180deg, var(--hijau) 0%, var(--hijau-gelap) 100%); color: #fff; padding: 1.25rem 1rem; }
        @media (min-width: 768px) { .sidebar { position: sticky; top: 0; height: 100vh; overflow-y: auto; display: flex; flex-direction: column; } }
        @media (max-width: 767px) { .sidebar { width: 100%; } }
        .logo { width: 40px; height: 40px; border-radius: 12px; background: var(--aksen); color: var(--hijau-gelap); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex: none; }
        .pill-role { font-size: .62rem; font-weight: 700; padding: .15rem .6rem; border-radius: 999px; background: rgba(127,220,159,.18); color: #a7f0c0; border: 1px solid rgba(127,220,159,.45); }
        .nav-link-p { color: #dcebe1; padding: .68rem 1rem; border-radius: 12px; display: flex; gap: .75rem; align-items: center; text-decoration: none; font-weight: 500; font-size: .82rem; }
        .nav-link-p:hover { background: rgba(255,255,255,.09); color: #fff; }
        .nav-link-p.active { background: var(--aksen); color: var(--hijau-gelap); font-weight: 700; }
        .kotak-user { border-top: 1px solid rgba(255,255,255,.14); padding-top: 1rem; margin-top: 1rem; }
        .kotak-user .email { font-size: .72rem; color: #b5cdbd; word-break: break-all; }
        .btn-keluar { width: 100%; background: rgba(255,255,255,.12); color: #fff; border: 0; border-radius: 10px; padding: .55rem; font-size: .78rem; font-weight: 600; }
        .btn-keluar:hover { background: rgba(255,255,255,.2); }
        .eyebrow { font-size: .66rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--lembut); }
        .judul-halaman { font-weight: 800; font-size: 1.35rem; margin: 0; }
        .kartu { background: #fff; border: 1px solid var(--garis); border-radius: 18px; box-shadow: 0 6px 20px rgba(10,50,30,.06); }
        .kartu h2 { font-weight: 700; }
        .sambutan { background: var(--aksen-muda); color: var(--aksen-tua); border-radius: 14px; padding: .8rem 1.1rem; font-weight: 500; font-size: .82rem; }
        .stat { padding: 1.1rem 1.25rem; position: relative; }
        .stat .angka { font-size: 1.85rem; font-weight: 700; line-height: 1.2; margin-top: .35rem; }
        .stat .sub { font-size: .72rem; color: var(--lembut); }
        .stat .ikon { position: absolute; top: 1rem; right: 1rem; width: 38px; height: 38px; border-radius: 12px; background: var(--aksen-muda); color: var(--aksen-tua); display: flex; align-items: center; justify-content: center; font-size: 1.05rem; }
        .btn-utama { background: var(--hijau); border-color: var(--hijau); color: #fff; font-weight: 600; border-radius: 10px; }
        .btn-utama:hover, .btn-utama:focus { background: var(--hijau-gelap); border-color: var(--hijau-gelap); color: #fff; }
        .btn-outline-secondary, .btn-outline-danger { border-radius: 10px; }
        .form-control, .form-select { border-radius: 10px; font-size: .85rem; }
        .form-control:focus, .form-select:focus { border-color: var(--hijau); box-shadow: 0 0 0 .2rem rgba(20,83,45,.18); }
        .table > :not(caption) > * > * { padding-top: .75rem; padding-bottom: .75rem; }
        .table thead th { font-size: .66rem; text-transform: uppercase; letter-spacing: .09em; color: var(--lembut); font-weight: 700; }
        .text-bg-primary { background-color: #1e6091 !important; }
        .text-bg-success { background-color: #2f7d5b !important; }
        .page-link { color: var(--hijau); }
        .page-item.active .page-link { background: var(--hijau); border-color: var(--hijau); color: #fff; }
        a { color: var(--hijau); }
        .stepper { position: relative; }
        .stepper input[type=number] { -moz-appearance: textfield; padding-right: 2.4rem; }
        .stepper input::-webkit-outer-spin-button, .stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .stepper .panah { position: absolute; top: 1px; right: 1px; bottom: 1px; width: 2rem; display: flex; flex-direction: column; border-left: 1px solid #dfe8e2; }
        .stepper .panah button { flex: 1; border: 0; background: transparent; color: var(--lembut); padding: 0; line-height: 1; font-size: .62rem; cursor: pointer; }
        .stepper .panah button:first-child { border-radius: 0 9px 0 0; border-bottom: 1px solid #dfe8e2; }
        .stepper .panah button:last-child { border-radius: 0 0 9px 0; }
        .stepper .panah button:hover { background: var(--aksen-muda); color: var(--hijau); }
        @media print { .sidebar, .tanpa-cetak { display: none !important; } body { background: #fff; } .kartu { box-shadow: none; } }
    </style>
</head>
<body>
<div class="d-md-flex min-vh-100">
    <aside class="sidebar">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="logo"><i class="bi bi-book-half"></i></div>
                <div>
                    <div class="fw-bold" style="line-height:1.1"><?= e($perpus) ?></div>
                    <span class="pill-role"><?= $namaRole ?></span>
                </div>
            </div>
            <button class="btn btn-sm text-white d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#menuSamping" aria-label="Buka menu"><i class="bi bi-list fs-3"></i></button>
        </div>

        <div id="menuSamping" class="collapse d-md-flex flex-column flex-grow-1 mt-4">
            <nav class="vstack gap-1 flex-grow-1">
                <?php foreach ($menu as [$file, $awalan, $ikon, $nama, $boleh]) : ?>
                    <?php if (in_array($u['role'], $boleh, true)) : ?>
                        <a class="nav-link-p <?= strpos($self, $awalan) === 0 ? 'active' : '' ?>" href="<?= $file ?>"><i class="bi <?= $ikon ?>"></i><?= $nama ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <div class="kotak-user">
                <div class="fw-bold"><?= e($u['name']) ?></div>
                <div class="email mb-2"><?= e($u['email']) ?></div>
                <a class="nav-link-p px-0 py-1 <?= $self === 'profil.php' ? 'fw-bold' : '' ?>" href="profil.php" style="font-size:.75rem"><i class="bi bi-person-circle"></i>Profil saya</a>
                <form method="post" action="logout.php" class="mt-2">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-keluar"><i class="bi bi-box-arrow-right me-1"></i>Keluar</button>
                </form>
            </div>
        </div>
    </aside>

    <main class="flex-grow-1 p-3 p-md-4" style="min-width:0">
        <div class="mb-4">
            <div class="eyebrow">Portal <?= $namaRole ?></div>
            <h1 class="judul-halaman"><?= e($judul) ?></h1>
        </div>
        <?php foreach ($_SESSION['flash'] ?? [] as [$jenis, $pesan]) : ?>
            <div class="alert alert-<?= e($jenis) ?> alert-dismissible fade show" role="alert" style="border-radius:14px">
                <?= e($pesan) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endforeach; unset($_SESSION['flash']); ?>
    <?php
}

function layout_end(): void
{
    ?>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.stepper').forEach(function (grup) {
    var isi = grup.querySelector('input');
    function ubah(arah) {
        var langkah = parseFloat(isi.dataset.langkah) || 1;
        var nilai = parseFloat(isi.value);
        if (isNaN(nilai)) nilai = 0;
        nilai = Math.round((nilai + arah * langkah) * 100) / 100;
        if (isi.min !== '' && nilai < parseFloat(isi.min)) nilai = parseFloat(isi.min);
        if (isi.max !== '' && nilai > parseFloat(isi.max)) nilai = parseFloat(isi.max);
        isi.value = nilai;
        isi.dispatchEvent(new Event('input', { bubbles: true }));
    }
    grup.querySelector('[data-aksi="kurang"]').addEventListener('click', function () { ubah(-1); });
    grup.querySelector('[data-aksi="tambah"]').addEventListener('click', function () { ubah(1); });
    isi.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowUp') { e.preventDefault(); ubah(1); }
        if (e.key === 'ArrowDown') { e.preventDefault(); ubah(-1); }
    });
});
</script>
</body>
</html>
    <?php
}