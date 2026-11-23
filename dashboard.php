<?php
require_once __DIR__ . '/inc/cek_sesi.php';
$u = wajib_login();

$db = db();
$jml = fn(string $sql) => (int) $db->query($sql)->fetchColumn();
$kartu = [
    ['Total Klien', $jml('SELECT COUNT(*) FROM klien'), 'Galeri & kolektor'],
    ['Jenis Layanan', $jml('SELECT COUNT(*) FROM jenis_jasa'), 'Tarif beragam'],
    ['Transaksi', $jml('SELECT COUNT(*) FROM transaksi'), 'Multi-tanggal'],
    ['Invoice', $jml('SELECT COUNT(*) FROM invoice'), 'Gabungan bulanan'],
];
$tagihan = (float) $db->query(
    "SELECT COALESCE(SUM(i.total - COALESCE(b.bayar, 0)), 0)
       FROM invoice i
       LEFT JOIN (SELECT id_invoice, SUM(jumlah) bayar FROM pembayaran GROUP BY id_invoice) b
              ON b.id_invoice = i.id_invoice
      WHERE i.status <> 'lunas'"
)->fetchColumn();
$terbaru = $db->query(
    'SELECT t.tgl_transaksi, k.nama_klien, t.deskripsi, t.jumlah, t.total, t.status
       FROM transaksi t JOIN klien k ON k.id_klien = t.id_klien
      ORDER BY t.tgl_transaksi DESC, t.id_transaksi DESC LIMIT 5'
)->fetchAll();

$akses = $u['peran'] === 'admin'
    ? ['klien' => 'Data Klien', 'jasa' => 'Jenis Jasa', 'transaksi' => 'Transaksi', 'penawaran' => 'Penawaran Harga', 'invoice' => 'Invoice']
    : ['invoice' => 'Invoice', 'laporan' => 'Laporan', 'pengguna' => 'Kelola Pengguna'];

$judul = 'Dashboard';
$aktif = 'dashboard';
require __DIR__ . '/inc/header.php';
?>
<h1>Dashboard Utama</h1>
<p class="subtitle">Ringkasan operasional dan akses cepat seluruh modul. Tagihan belum dibayar: <b><?= rupiah($tagihan) ?></b></p>

<div class="cards">
  <?php foreach ($kartu as [$label, $nilai, $tag]): ?>
    <div class="card"><small><?= e($label) ?></small><strong><?= $nilai ?></strong><span class="tag"><?= e($tag) ?></span></div>
  <?php endforeach; ?>
</div>

<div class="panel">
  <div class="panel-title"><h2>Akses Cepat</h2></div>
  <div class="actions">
    <?php foreach ($akses as $hal => $label): ?>
      <a class="btn dark" href="<?= $hal ?>.php"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <div class="panel-title"><h2>Aktivitas Transaksi Terbaru</h2></div>
  <table>
    <thead><tr><th>Tanggal</th><th>Klien</th><th>Jasa</th><th class="num">Jumlah</th><th class="num">Total</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($terbaru as $t): ?>
      <tr>
        <td><?= tanggal($t['tgl_transaksi']) ?></td><td><?= e($t['nama_klien']) ?></td><td><?= e($t['deskripsi']) ?></td>
        <td class="num"><?= (int) $t['jumlah'] ?></td><td class="num"><?= rupiah($t['total']) ?></td>
        <td><span class="badge <?= $t['status'] === 'proses' ? 'pending' : '' ?>"><?= $t['status'] === 'proses' ? 'Proses' : 'Selesai' ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$terbaru): ?><tr><td colspan="6" class="empty">Belum ada transaksi</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/inc/footer.php';
