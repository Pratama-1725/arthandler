<?php
// Dimuat di awal setiap halaman: memulai sesi, memuat koneksi dan fungsi, serta memeriksa login.

require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/fungsi.php';

// Header keamanan: tidak boleh dibingkai situs lain, tipe berkas tidak ditebak, referer tidak bocor.
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('ARTHANDLER');
    session_start();
}

function pengguna(): ?array
{
    return $_SESSION['pengguna'] ?? null;
}

/** Mengakhiri sesi: kosongkan data, hapus cookie sesi, lalu hancurkan sesi. */
function akhiri_sesi(): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'] ?: 'Lax',
    ]);
    session_destroy();
}

/**
 * Wajib login; bila $peran diisi, hanya peran tersebut yang boleh membuka halaman.
 * Status akun dibaca ulang dari database setiap permintaan, sehingga akun yang dinonaktifkan
 * atau diganti perannya langsung berlaku tanpa menunggu logout.
 */
function wajib_login(string ...$peran): array
{
    $u = pengguna();
    if (!$u) {
        arahkan('login.php');
    }
    $st = db()->prepare('SELECT nama, peran, aktif FROM pengguna WHERE id_pengguna = ?');
    $st->execute([$u['id']]);
    $akun = $st->fetch();
    if (!$akun || !$akun['aktif']) {
        akhiri_sesi();
        session_start();
        pesan('error', 'Sesi berakhir. Silakan login kembali.');
        arahkan('login.php');
    }
    $u['nama'] = $akun['nama'];
    $u['peran'] = $akun['peran'];
    $_SESSION['pengguna'] = $u;
    if ($peran && !in_array($u['peran'], $peran, true)) {
        pesan('error', 'Anda tidak memiliki akses ke halaman tersebut.');
        arahkan('dashboard.php');
    }
    return $u;
}
