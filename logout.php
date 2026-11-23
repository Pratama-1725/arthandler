<?php
require_once __DIR__ . '/inc/cek_sesi.php';

$_SESSION = [];
session_destroy();
arahkan('login.php');
