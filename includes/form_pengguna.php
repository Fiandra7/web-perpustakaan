<?php
// Form tambah/ubah akun. Dipakai oleh pengguna_form.php (semua role) dan anggota_form.php (khusus member).
// Variabel dari pemanggil: $IZIN (role yang boleh), $ROLE_TETAP (null atau 'member'), $KEMBALI (halaman daftar).
require __DIR__ . '/../config.php';
require __DIR__ . '/layout.php';
$me = require_role(...$IZIN);
$pdo = db();

$anggotaSaja = $ROLE_TETAP !== null;
$noun = $anggotaSaja ? 'anggota' : 'pengguna';

$id = (int) ($_GET['id'] ?? 0);
$f = ['name' => '', 'email' => '', 'role' => $ROLE_TETAP ?? 'member', 'status' => 'aktif'];

if ($id) {
    $st = $pdo->prepare('SELECT name, email, role, status FROM users WHERE id = ?');
    $st->execute([$id]);
    $baris = $st->fetch();
    if (!$baris || ($anggotaSaja && $baris['role'] !== $ROLE_TETAP)) {
        flash('danger', ucfirst($noun) . ' tidak ditemukan.');
        redirect($KEMBALI);
    }
    $f = $baris;
}

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f['name'] = trim($_POST['name'] ?? '');
    $f['email'] = trim($_POST['email'] ?? '');
    $f['role'] = $ROLE_TETAP ?? ($_POST['role'] ?? '');
    $f['status'] = $id ? ($_POST['status'] ?? '') : 'aktif';
    $pass = $_POST['password'] ?? '';
    $konf = $_POST['password_confirmation'] ?? '';

    if ($f['name'] === '') $err[] = 'Nama wajib diisi.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Email tidak valid.';
    // Nilai harus sama dengan value di dropdown role.
    if (!in_array($f['role'], ['super_admin', 'admin', 'member'], true)) $err[] = 'Role yang dipilih tidak valid.';
    if (!in_array($f['status'], ['aktif', 'diblokir'], true)) $err[] = 'Status tidak valid.';

    $st = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
    $st->execute([$f['email'], $id]);
    if ($st->fetch()) $err[] = 'Email sudah terdaftar.';

    if (!$id || $pass !== '') {
        if (strlen($pass) < 8) $err[] = 'Password minimal 8 karakter.';
        elseif ($pass !== $konf) $err[] = 'Konfirmasi password tidak sama.';
    }
    if ($id === (int) $me['id'] && !$err) {
        $lama = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $lama->execute([$id]);
        if ($f['role'] !== $lama->fetchColumn() || $f['status'] !== 'aktif') {
            $err[] = 'Anda tidak bisa mengubah role atau memblokir akun sendiri.';
        }
    }

    if (!$err) {
        if ($id) {
            $pdo->prepare('UPDATE users SET name=?, email=?, role=?, status=? WHERE id=?')->execute([$f['name'], $f['email'], $f['role'], $f['status'], $id]);
            if ($pass !== '') {
                $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
            }
            $akhir = $id;
            flash('success', 'Data ' . $noun . ' diperbarui.');
        } else {
            $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)')
                ->execute([$f['name'], $f['email'], password_hash($pass, PASSWORD_DEFAULT), $f['role']]);
            $akhir = (int) $pdo->lastInsertId();
            flash('success', 'Akun ' . $noun . ' berhasil dibuat.');
        }
        if ($f['role'] === 'member') {
            buat_no_anggota($akhir);
        }
        redirect($KEMBALI);
    }
}

layout_start(($id ? 'Ubah ' : 'Tambah ') . $noun);
?>
<div class="kartu p-4" style="max-width:560px">
    <?php if ($err) : ?>
        <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($err as $m) : ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label small fw-semibold" for="name">Nama</label>
            <input type="text" id="name" name="name" class="form-control" value="<?= e($f['name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold" for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control" value="<?= e($f['email']) ?>" required>
        </div>
        <?php if (!$anggotaSaja) : ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold" for="role">Role</label>
                <select id="role" name="role" class="form-select" required>
                    <?php foreach (['super_admin' => 'Super Admin', 'admin' => 'Admin', 'member' => 'Member'] as $nilai => $label) : ?>
                        <option value="<?= $nilai ?>" <?= $f['role'] === $nilai ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($id) : ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <?php foreach (['aktif' => 'Aktif', 'diblokir' => 'Diblokir'] as $nilai => $label) : ?>
                        <option value="<?= $nilai ?>" <?= $f['status'] === $nilai ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label small fw-semibold" for="password">Password<?= $id ? ' baru (kosongkan jika tidak diubah)' : '' ?></label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Minimal 8 karakter" <?= $id ? '' : 'required' ?>>
        </div>
        <div class="mb-4">
            <label class="form-label small fw-semibold" for="password_confirmation">Konfirmasi password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Ulangi password">
        </div>
        <div class="d-flex gap-2">
            <a href="<?= e($KEMBALI) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
            <button type="submit" class="btn btn-utama"><?= $id ? 'Simpan perubahan' : 'Buat akun' ?></button>
        </div>
    </form>
</div>
<?php layout_end(); ?>