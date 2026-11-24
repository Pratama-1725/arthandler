<?php
// Laporan per periode untuk Pemilik: transaksi, pendapatan (pembayaran), piutang, rekap per jasa.
require_once __DIR__ . '/inc/cek_sesi.php';
wajib_login('pemilik');
$db = db();

function lap_tgl_valid(string $s): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
}

$awal = $_GET['awal'] ?? date('Y-m-01');
$akhir = $_GET['akhir'] ?? date('Y-m-t');
$galat = '';
if (!lap_tgl_valid($awal) || !lap_tgl_valid($akhir)) {
    $galat = 'Periode tidak valid; ditampilkan bulan berjalan.';
    $awal = date('Y-m-01');
    $akhir = date('Y-m-t');
} elseif ($akhir < $awal) {
    $galat = 'Tanggal akhir tidak boleh lebih awal dari tanggal awal; ditampilkan bulan berjalan.';
    $awal = date('Y-m-01');
    $akhir = date('Y-m-t');
}
$p = [$awal, $akhir];

$st = $db->prepare(
    "SELECT COUNT(*) jml, COALESCE(SUM(total), 0) nilai,
            COALESCE(SUM(status = 'selesai'), 0) jml_selesai
       FROM transaksi WHERE tgl_transaksi BETWEEN ? AND ?"
);
$st->execute($p);
$trx = $st->fetch();

$st = $db->prepare('SELECT COUNT(*) jml, COALESCE(SUM(jumlah), 0) nilai FROM pembayaran WHERE tgl_bayar BETWEEN ? AND ?');
$st->execute($p);
$pendapatan = $st->fetch();

$st = $db->prepare('SELECT COUNT(*) jml, COALESCE(SUM(total), 0) nilai FROM invoice WHERE tgl_invoice BETWEEN ? AND ?');
$st->execute($p);
$inv = $st->fetch();

// Invoice belum lunas yang diterbitkan pada periode, beserta sisa tagihannya (piutang).
$st = $db->prepare(
    "SELECT i.id_invoice, i.no_invoice, i.tgl_invoice, i.jatuh_tempo, i.total, i.status, k.nama_klien,
            COALESCE(b.bayar, 0) terbayar, i.total - COALESCE(b.bayar, 0) sisa
       FROM invoice i
       JOIN klien k ON k.id_klien = i.id_klien
       LEFT JOIN (SELECT id_invoice, SUM(jumlah) bayar FROM pembayaran GROUP BY id_invoice) b ON b.id_invoice = i.id_invoice
      WHERE i.status <> 'lunas' AND i.tgl_invoice BETWEEN ? AND ?
      ORDER BY i.jatuh_tempo, i.id_invoice"
);
$st->execute($p);
$belum_lunas = $st->fetchAll();
$piutang = 0.0;
foreach ($belum_lunas as $b) {
    $piutang += (float) $b['sisa'];
}

// Rekap per jenis jasa; transaksi tanpa jenis jasa (hasil penawaran) dikelompokkan per deskripsi.
$st = $db->prepare(
    'SELECT COALESCE(j.nama_jasa, t.deskripsi) layanan, COUNT(*) jml, SUM(t.jumlah) qty, SUM(t.total) nilai
       FROM transaksi t LEFT JOIN jenis_jasa j ON j.id_jasa = t.id_jasa
      WHERE t.tgl_transaksi BETWEEN ? AND ?
      GROUP BY layanan ORDER BY nilai DESC, layanan'
);
$st->execute($p);
$rekap = $st->fetchAll();

$judul = 'Laporan';
$aktif = 'laporan';
require __DIR__ . '/inc/header.php';
?>
<style>@media print{.cards{grid-template-columns:repeat(4,1fr)}.card{border:1px solid #999}}</style>
<div class="only-print" style="border-bottom:2px solid #000;padding-bottom:10px;margin-bottom:16px">
  <b style="font-size:22px">ArtHandler</b><br>Laporan Periode <?= tanggal($awal) ?> – <?= tanggal($akhir) ?>
</div>
<h1>Laporan</h1>
<p class="subtitle">Rekap periode <b><?= tanggal($awal) ?> – <?= tanggal($akhir) ?></b>: transaksi, pendapatan, piutang, dan rekap per jenis jasa.</p>
<?php if ($galat): ?><div class="alert error"><?= e($galat) ?></div><?php endif; ?>

<div class="panel no-print">
  <form class="toolbar" method="get" style="margin:0">
    <label class="hint">Dari</label><input type="date" name="awal" value="<?= e($awal) ?>" required>
    <label class="hint">Sampai</label><input type="date" name="akhir" value="<?= e($akhir) ?>" required>
    <button class="btn secondary" type="submit">Tampilkan</button>
    <a class="btn secondary" href="laporan.php">Bulan Ini</a>
    <button class="btn" type="button" onclick="window.print()">Cetak</button>
  </form>
</div>

<div class="cards">
  <div class="card"><small>Transaksi</small><strong><?= (int) $trx['jml'] ?></strong><span class="tag"><?= (int) $trx['jml_selesai'] ?> selesai · <?= rupiah($trx['nilai']) ?></span></div>
  <div class="card"><small>Invoice Diterbitkan</small><strong><?= (int) $inv['jml'] ?></strong><span class="tag"><?= rupiah($inv['nilai']) ?></span></div>
  <div class="card"><small>Pendapatan (Pembayaran)</small><strong><?= rupiah($pendapatan['nilai']) ?></strong><span class="tag"><?= (int) $pendapatan['jml'] ?> pembayaran</span></div>
  <div class="card"><small>Piutang</small><strong><?= rupiah($piutang) ?></strong><span class="tag"><?= count($belum_lunas) ?> invoice belum lunas</span></div>
</div>

<div class="panel">
  <div class="panel-title"><h2>Rekap per Jenis Jasa</h2></div>
  <table>
    <thead><tr><th>Jenis Jasa / Deskripsi</th><th class="num">Transaksi</th><th class="num">Jumlah</th><th class="num">Total</th></tr></thead>
    <tbody>
    <?php foreach ($rekap as $r): ?>
      <tr><td><?= e($r['layanan']) ?></td><td class="num"><?= (int) $r['jml'] ?></td><td class="num"><?= (int) $r['qty'] ?></td><td class="num"><?= rupiah($r['nilai']) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($rekap): ?>
      <tr><td><b>Total</b></td><td class="num"><b><?= (int) $trx['jml'] ?></b></td><td></td><td class="num"><b><?= rupiah($trx['nilai']) ?></b></td></tr>
    <?php else: ?>
      <tr><td colspan="4" class="empty">Tidak ada transaksi pada periode ini</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <div class="panel-title"><h2>Invoice Belum Lunas</h2></div>
  <table>
    <thead><tr><th>No. Invoice</th><th>Klien</th><th>Tanggal</th><th>Jatuh Tempo</th><th class="num">Total</th><th class="num">Terbayar</th><th class="num">Sisa</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($belum_lunas as $b): ?>
      <tr>
        <td><a href="invoice.php?id=<?= (int) $b['id_invoice'] ?>"><?= e($b['no_invoice']) ?></a></td><td><?= e($b['nama_klien']) ?></td>
        <td><?= tanggal($b['tgl_invoice']) ?></td><td><?= tanggal($b['jatuh_tempo']) ?></td>
        <td class="num"><?= rupiah($b['total']) ?></td><td class="num"><?= rupiah($b['terbayar']) ?></td><td class="num"><?= rupiah($b['sisa']) ?></td>
        <td><span class="badge <?= $b['status'] === 'sebagian' ? 'pending' : 'danger' ?>"><?= $b['status'] === 'sebagian' ? 'Sebagian' : 'Belum Bayar' ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$belum_lunas): ?><tr><td colspan="8" class="empty">Tidak ada invoice belum lunas</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/inc/footer.php';
