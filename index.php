<?php
require_once __DIR__ . '/inc/cek_sesi.php';

arahkan(pengguna() ? 'dashboard.php' : 'login.php');
