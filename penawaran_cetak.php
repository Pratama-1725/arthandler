<?php
require_once __DIR__ . '/inc/cek_sesi.php';
wajib_login('admin');
require_once __DIR__ . '/penawaran_fungsi.php';
$db = db();

$id = (int) ($_GET['id'] ?? 0);
$p = pnw_ambil($db, $id);
if (!$p) {
    pesan('error', 'Penawaran tidak ditemukan.');
    arahkan('penawaran.php');
}
$rinci = pnw_detail($db, $id);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Penawaran <?= e($p['no_penawaran']) ?> · ArtHandler</title>
<style>
  @font-face{font-family:"Playfair Display";font-style:normal;font-weight:700;font-display:swap;src:url("assets/fonts/PlayfairDisplay-700.woff2") format("woff2")}
  @font-face{font-family:"Playfair Display";font-style:italic;font-weight:700;font-display:swap;src:url("assets/fonts/PlayfairDisplay-700i.woff2") format("woff2")}
  @font-face{font-family:"Poppins";font-style:normal;font-weight:400;font-display:swap;src:url("assets/fonts/Poppins-400.woff2") format("woff2")}
  @font-face{font-family:"Poppins";font-style:normal;font-weight:600;font-display:swap;src:url("assets/fonts/Poppins-600.woff2") format("woff2")}
  *{box-sizing:border-box}
  body{margin:0;background:#F6EEE3;font-family:Poppins,system-ui,"Segoe UI",Arial,sans-serif;color:#3F3A35;font-size:13px}
  .kertas{background:#fff;width:210mm;max-width:100%;min-height:297mm;margin:20px auto;padding:18mm 16mm;box-shadow:0 10px 28px rgba(78,72,65,.12)}
  .kop{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;border-bottom:1.5px solid #4E4841;padding-bottom:12px;margin-bottom:18px;box-shadow:0 3px 0 -2px #4E4841}
  .kop .brand{font-family:"Playfair Display",Georgia,serif;font-size:28px;font-weight:700;font-style:italic;color:#CF5931}.kop .brand span{font-style:normal;color:#4E4841}.kop small{color:#6B6560}
  .kop .dok{text-align:right}.kop .dok h1{margin:0;font-family:"Playfair Display",Georgia,serif;font-size:20px;letter-spacing:2px;color:#4E4841}
  .info{display:flex;justify-content:space-between;gap:20px;margin-bottom:16px}.info td{padding:2px 8px 2px 0;vertical-align:top}
  table.rinci{width:100%;border-collapse:collapse}
  table.rinci th{background:#F6EEE3;color:#4E4841;text-align:left;padding:8px;border-top:1.5px solid #4E4841;border-bottom:1.5px solid #4E4841;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  table.rinci td{padding:8px;border-bottom:.5px solid #CFC3B5}.num{text-align:right}
  table.rinci tfoot td{font-family:"Playfair Display",Georgia,serif;font-weight:700;font-size:15px;border-top:1.5px solid #4E4841;border-bottom:1.5px solid #4E4841}
  .catatan{margin-top:14px}.ttd{margin-top:50px;display:flex;justify-content:flex-end}.ttd div{text-align:center;width:220px}
  .ttd .nama{margin-top:70px;border-top:1px solid #4E4841;padding-top:4px;font-weight:600}
  .alat{text-align:center;margin:16px}.alat button,.alat a{display:inline-block;border:0;border-radius:30px;padding:11px 20px;background:linear-gradient(135deg,#B04420,#C4532C);color:#fff;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;margin:4px}
  .alat a{background:#fff;color:#1F6A83;box-shadow:inset 0 0 0 1.5px #3797B8}
  @media print{body{background:#fff;color:#000}.kertas{margin:0;width:auto;min-height:0;box-shadow:none;padding:0}.kop .brand,.kop .brand span,.kop .dok h1{color:#000}table.rinci th{background:#eee}.alat{display:none}@page{size:A4;margin:15mm}}
</style>
</head>
<body>
<div class="alat">
  <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
  <a href="penawaran_detail.php?id=<?= (int) $id ?>">Kembali</a>
</div>
<div class="kertas">
  <div class="kop">
    <div><div class="brand">Art<span>Handler</span></div><small>Jasa Art Handling, Packing, Transport &amp; Instalasi Karya Seni</small></div>
    <div class="dok"><h1>PENAWARAN HARGA</h1><div>No. <b><?= e($p['no_penawaran']) ?></b></div><div>Tanggal: <?= e(tanggal($p['tgl_penawaran'])) ?></div></div>
  </div>
  <div class="info">
    <table>
      <tr><td>Kepada</td><td>: <b><?= e($p['nama_klien']) ?></b></td></tr>
      <tr><td>Alamat</td><td>: <?= e($p['alamat'] ?? '-') ?></td></tr>
      <tr><td>No. HP</td><td>: <?= e($p['no_hp'] ?? '-') ?></td></tr>
    </table>
    <table>
      <tr><td>Status</td><td>: <?= e(PNW_STATUS[$p['status']][0] ?? $p['status']) ?></td></tr>
    </table>
  </div>
  <p>Dengan hormat, bersama ini kami sampaikan penawaran harga sebagai berikut:</p>
  <table class="rinci">
    <thead><tr><th style="width:40px">No</th><th>Deskripsi</th><th class="num" style="width:70px">Jumlah</th><th class="num" style="width:130px">Harga Satuan</th><th class="num" style="width:140px">Subtotal</th></tr></thead>
    <tbody>
    <?php foreach ($rinci as $n => $d): ?>
      <tr><td><?= $n + 1 ?></td><td><?= e($d['deskripsi']) ?></td><td class="num"><?= (int) $d['jumlah'] ?></td>
        <td class="num"><?= rupiah($d['harga_satuan']) ?></td><td class="num"><?= rupiah($d['jumlah'] * $d['harga_satuan']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="4" class="num">TOTAL</td><td class="num"><?= rupiah($p['total']) ?></td></tr></tfoot>
  </table>
  <?php if ($p['catatan'] !== null && $p['catatan'] !== ''): ?><div class="catatan"><b>Catatan:</b> <?= e($p['catatan']) ?></div><?php endif; ?>
  <div class="ttd"><div>Hormat kami,<div class="nama"><?= e($p['nama_pembuat']) ?></div>ArtHandler</div></div>
</div>
<?php if (isset($_GET['auto'])): ?><script>window.addEventListener('load', function () { window.print(); });</script><?php endif; ?>
</body>
</html>
