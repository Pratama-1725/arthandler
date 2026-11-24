<?php
// Koneksi basis data (PDO MySQL). Kredensial dibaca dari file .env di folder app/;
// bila .env tidak ada, dipakai nilai bawaan XAMPP lokal.

function baca_env(string $path): array
{
    $hasil = [];
    if (!is_file($path)) {
        return $hasil;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $baris) {
        $baris = trim($baris);
        if ($baris === '' || $baris[0] === '#' || !str_contains($baris, '=')) {
            continue;
        }
        [$kunci, $nilai] = explode('=', $baris, 2);
        $hasil[trim($kunci)] = trim($nilai, " \t\"'");
    }
    return $hasil;
}

$ENV = baca_env(__DIR__ . '/../.env');

// Galat PHP tidak ditampilkan ke pengguna (bisa membocorkan path dan query); cukup dicatat di log.
// Untuk pengembangan lokal isi APP_DEBUG=1 di .env.
ini_set('display_errors', ($ENV['APP_DEBUG'] ?? '0') === '1' ? '1' : '0');
ini_set('log_errors', '1');

// Galat yang tidak tertangkap (mis. query gagal) diganti pesan umum, detailnya ke log.
set_exception_handler(function (Throwable $ex): void {
    error_log('ArtHandler galat: ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo 'Terjadi kesalahan pada sistem. Silakan kembali dan coba lagi.';
});

function db(): PDO
{
    static $pdo = null;
    global $ENV;
    if ($pdo === null) {
        $host = $ENV['DB_HOST'] ?? 'localhost';
        $port = $ENV['DB_PORT'] ?? '3306';
        $nama = $ENV['DB_NAME'] ?? 'db_arthandler';
        $user = $ENV['DB_USER'] ?? 'root';
        $pass = $ENV['DB_PASS'] ?? '';
        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$nama;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $ex) {
            // Detail galat (host, user, pesan MySQL) hanya ke log server, tidak ke pengguna.
            error_log('ArtHandler koneksi DB gagal: ' . $ex->getMessage());
            http_response_code(503);
            exit('Layanan sedang tidak tersedia. Silakan coba beberapa saat lagi.');
        }
    }
    return $pdo;
}
