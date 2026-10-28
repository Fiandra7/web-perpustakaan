<?php
// Jalankan sekali untuk membuat tiga akun awal, lalu HAPUS file ini.
require __DIR__ . '/config.php';

if ((int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    exit('Sudah terpasang. Hapus file install.php.');
}

$akun = [
    ['Super Admin', 'superadmin@perpus.test', 'super_admin'],
    ['Admin',       'admin@perpus.test',      'admin'],
    ['Member Uji',  'member@perpus.test',     'member'],
];
$st = db()->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
foreach ($akun as [$nama, $email, $role]) {
    $st->execute([$nama, $email, password_hash('password123', PASSWORD_DEFAULT), $role]);
}
db()->exec("UPDATE users SET no_anggota = CONCAT('A-', LPAD(id, 4, '0')) WHERE role = 'member' AND no_anggota IS NULL");
echo 'Selesai. Tiga akun dibuat dengan password password123. Hapus file install.php sekarang, lalu buka login.php.';