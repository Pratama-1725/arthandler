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
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$nama;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
