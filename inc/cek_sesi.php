<?php
// Dimuat di awal setiap halaman: memulai sesi, memuat koneksi dan fungsi, serta memeriksa login.

require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/fungsi.php';

if (session_status() === PHP_SESSION_NONE) {
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

/** Wajib login; bila $peran diisi, hanya peran tersebut yang boleh membuka halaman. */
function wajib_login(string ...$peran): array
{
    $u = pengguna();
    if (!$u) {
        arahkan('login.php');
    }
    if ($peran && !in_array($u['peran'], $peran, true)) {
        pesan('error', 'Anda tidak memiliki akses ke halaman tersebut.');
        arahkan('dashboard.php');
    }
    return $u;
}
