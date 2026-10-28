<?php
// Konfigurasi dan fungsi bantu. Butuh PHP 8.0 ke atas dan MySQL/MariaDB.

const DB_HOST = 'localhost';
const DB_NAME = 'perpus';
const DB_USER = 'root';
const DB_PASS = '';

date_default_timezone_set('Asia/Jakarta');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $jenis, string $pesan): void
{
    $_SESSION['flash'][] = [$jenis, $pesan];
}

/* ---------- Login, role, CSRF ---------- */

function require_login(): array
{
    static $user = null;
    if (empty($_SESSION['uid'])) {
        redirect('login.php');
    }
    if ($user === null) {
        $st = db()->prepare('SELECT id, name, email, no_anggota, role, status FROM users WHERE id = ?');
        $st->execute([$_SESSION['uid']]);
        $user = $st->fetch() ?: false;
    }
    if (!$user || $user['status'] !== 'aktif') {
        $_SESSION = [];
        session_destroy();
        redirect('login.php');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('403: Anda tidak punya akses ke halaman ini.');
    }
    return $user;
}

function csrf_field(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

/* ---------- Angka desimal dengan koma ---------- */

// Terima "2,5", "1.234,5", "2.5", "1.234", atau "3". Hasil null jika bukan angka.
function angka(?string $v): ?float
{
    $v = str_replace(' ', '', trim((string) $v));
    if ($v === '') {
        return null;
    }
    if (strpos($v, ',') !== false) {
        $v = str_replace(',', '.', str_replace('.', '', $v));
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) {
        $v = str_replace('.', '', $v);
    }
    return preg_match('/^-?\d+(\.\d+)?$/', $v) ? (float) $v : null;
}

// Untuk isian type="number": browser sudah mengirim titik sebagai desimal, jadi dibaca apa adanya.
// Teks berkoma (misalnya "2,5") tetap dibaca lewat angka().
function angka_input(?string $v): ?float
{
    $v = trim((string) $v);
    return is_numeric($v) ? (float) $v : angka($v);
}

// 1234.5 menjadi "1.234,5" dan 3.0 menjadi "3".
function fmt($n, int $maks = 2): string
{
    $s = number_format((float) $n, $maks, ',', '.');
    if ($maks > 0 && strpos($s, ',') !== false) {
        $s = rtrim(rtrim($s, '0'), ',');
    }
    return $s;
}

function rupiah($n): string
{
    return 'Rp ' . fmt($n);
}

/* ---------- Tanggal, denda, halaman ---------- */

function tgl(?string $d): string
{
    if (!$d) {
        return '-';
    }
    static $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($d);
    return date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
}

function hitung_denda(string $jatuhTempo, ?string $kembali = null): float
{
    $tempo = new DateTime($jatuhTempo);
    $akhir = new DateTime($kembali ?: 'today');
    if ($akhir <= $tempo) {
        return 0.0;
    }
    return (float) $tempo->diff($akhir)->days * denda_per_hari();
}

function halaman(): int
{
    return max(1, (int) ($_GET['hal'] ?? 1));
}

function pager(int $total, int $per, int $hal): string
{
    $jumlah = (int) ceil($total / $per);
    if ($jumlah <= 1) {
        return '';
    }
    $q = $_GET;
    $h = '<nav aria-label="Halaman"><ul class="pagination pagination-sm mb-0">';
    for ($i = 1; $i <= $jumlah; $i++) {
        $q['hal'] = $i;
        $h .= '<li class="page-item' . ($i === $hal ? ' active' : '') . '"><a class="page-link" href="?' . e(http_build_query($q)) . '">' . $i . '</a></li>';
    }
    return $h . '</ul></nav>';
}

/* ---------- Unggah sampul ---------- */

// Mengembalikan [nama_file, pesan_error].
function simpan_sampul(array $f): array
{
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Gambar sampul gagal diunggah.'];
    }
    if ($f['size'] > 2 * 1024 * 1024) {
        return [null, 'Ukuran sampul maksimal 2 MB.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) {
        return [null, 'Sampul harus berupa gambar JPG, PNG, atau WEBP.'];
    }
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $nama = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $nama)) {
        return [null, 'Gagal menyimpan gambar sampul.'];
    }
    return [$nama, null];
}

function hapus_sampul(?string $nama): void
{
    if ($nama && preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', $nama)) {
        @unlink(__DIR__ . '/uploads/' . $nama);
    }
}

/* ---------- Pengaturan perpustakaan (disimpan di tabel pengaturan) ---------- */

function setting(string $kunci, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = db()->query('SELECT kunci, nilai FROM pengaturan')->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            $cache = [];
        }
    }
    return $cache[$kunci] ?? $default;
}

function denda_per_hari(): float
{
    return (float) setting('denda_per_hari', 1000);
}

function lama_pinjam(): int
{
    return (int) setting('lama_pinjam_hari', 7);
}

function maks_pinjam(): int
{
    return (int) setting('maks_pinjam', 3);
}

/* ---------- Anggota dan denda ---------- */

function buat_no_anggota(int $id): void
{
    db()->prepare("UPDATE users SET no_anggota = CONCAT('A-', LPAD(id, 4, '0')) WHERE id = ? AND no_anggota IS NULL")->execute([$id]);
}

// Denda yang belum lunas: sudah dikembalikan tapi belum dibayar, ditambah yang masih berjalan dan terlambat.
// Mengembalikan [total_rupiah, jumlah_transaksi]. Isi $uid untuk satu anggota saja.
function denda_belum_lunas(?int $uid = null): array
{
    $sql = "SELECT jatuh_tempo, status, denda FROM peminjamans
            WHERE ((status = 'kembali' AND denda > 0 AND denda_lunas = 0) OR (status = 'dipinjam' AND jatuh_tempo < ?))";
    $par = [date('Y-m-d')];
    if ($uid) {
        $sql .= ' AND user_id = ?';
        $par[] = $uid;
    }
    $st = db()->prepare($sql);
    $st->execute($par);

    $total = 0.0;
    $jumlah = 0;
    foreach ($st->fetchAll() as $r) {
        $total += $r['status'] === 'kembali' ? (float) $r['denda'] : hitung_denda($r['jatuh_tempo']);
        $jumlah++;
    }
    return [$total, $jumlah];
}