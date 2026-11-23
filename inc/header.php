<?php
// Kerangka halaman: sidebar sesuai peran + topbar. Variabel yang dipakai: $judul, $aktif.
$u = pengguna();
$menu = [
    'admin' => [
        'dashboard' => ['Dashboard', '⌂'],
        'klien'     => ['Data Klien', '♙'],
        'jasa'      => ['Jenis Jasa', '✦'],
        'transaksi' => ['Transaksi', '▣'],
        'penawaran' => ['Penawaran Harga', '▤'],
        'invoice'   => ['Invoice', '▧'],
    ],
    'pemilik' => [
        'dashboard' => ['Dashboard', '⌂'],
        'invoice'   => ['Invoice', '▧'],
        'laporan'   => ['Laporan', '▥'],
        'pengguna'  => ['Kelola Pengguna', '♟'],
    ],
][$u['peran']];
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
    <?php foreach ($menu as $hal => [$label, $ikon]): ?>
      <a href="<?= $hal ?>.php" data-icon="<?= $ikon ?>" class="<?= $aktif === $hal ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <a href="logout.php" data-icon="⎋" class="logout">Logout</a>
  </nav>
  <div class="sidebar-bottom">Operational &amp; Billing System</div>
</aside>
<main class="main">
  <div class="topbar">
    <div class="crumb">ArtHandler / <span><?= e($judul) ?></span></div>
    <div class="user"><?= e($u['nama']) ?> · <?= $u['peran'] === 'admin' ? 'Admin' : 'Pemilik' ?></div>
  </div>
  <?= tampil_pesan() ?>
