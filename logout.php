<?php
require_once __DIR__ . '/inc/cek_sesi.php';

// Logout hanya lewat POST + token CSRF, supaya tautan dari situs lain tidak bisa mengeluarkan pengguna.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    arahkan(pengguna() ? 'dashboard.php' : 'login.php');
}
cek_csrf();

akhiri_sesi();
arahkan('login.php');
