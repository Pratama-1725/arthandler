<?php
// Kerangka halaman: sidebar sesuai peran + topbar. Variabel yang dipakai: $judul, $aktif.
$u = pengguna();
$menu = [
    'admin' => [
        'dashboard' => ['Dashboard', '⌂', 'Beranda'],
        'klien'     => ['Data Klien', '♙', 'Klien'],
        'jasa'      => ['Jenis Jasa', '✦', 'Jasa'],
        'transaksi' => ['Transaksi', '▣', 'Transaksi'],
        'penawaran' => ['Penawaran Harga', '▤', 'Tawaran'],
        'invoice'   => ['Invoice', '▧', 'Invoice'],
    ],
    'pemilik' => [
        'dashboard' => ['Dashboard', '⌂', 'Beranda'],
        'invoice'   => ['Invoice', '▧', 'Invoice'],
        'laporan'   => ['Laporan', '▥', 'Laporan'],
        'pengguna'  => ['Kelola Pengguna', '♟', 'Pengguna'],
    ],
][$u['peran']];
// Elemen ketiga = label pendek untuk bottom navigation di HP (atribut data-short).
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($judul) ?> · ArtHandler</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
<aside class="sidebar">
  <div class="brand">Art<span>Handler</span></div>
  <nav class="nav">
    <?php foreach ($menu as $hal => [$label, $ikon, $pendek]): ?>
      <a href="<?= $hal ?>.php" data-icon="<?= $ikon ?>" data-short="<?= e($pendek) ?>" class="<?= $aktif === $hal ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <form method="post" action="logout.php"><?= input_csrf() ?><button type="submit" data-icon="⎋" data-short="Logout" class="logout">Logout</button></form>
  </nav>
  <div class="sidebar-bottom">Operational &amp; Billing System</div>
</aside>
<main class="main">
  <div class="topbar">
    <div class="topbrand" aria-hidden="true">Art<span>Handler</span></div>
    <div class="crumb">ArtHandler / <span><?= e($judul) ?></span></div>
    <div class="user"><?= e($u['nama']) ?> · <?= $u['peran'] === 'admin' ? 'Admin' : 'Pemilik' ?></div>
  </div>
  <?= tampil_pesan() ?>
