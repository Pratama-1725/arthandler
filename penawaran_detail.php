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
$trx = [];
if ($p['status'] === 'disetujui') {
    $st = $db->prepare('SELECT id_transaksi, tgl_transaksi, deskripsi, jumlah, total, status, id_invoice FROM transaksi WHERE id_penawaran = ? ORDER BY id_transaksi');
    $st->execute([$id]);
    $trx = $st->fetchAll();
}

$judul = 'Detail Penawaran';
$aktif = 'penawaran';
require __DIR__ . '/inc/header.php';
?>
<h1>Penawaran <?= e($p['no_penawaran']) ?> <?= pnw_badge($p['status']) ?></h1>
<p class="subtitle">Dibuat oleh <?= e($p['nama_pembuat']) ?><?= $p['status'] === 'disetujui' ? ' · penawaran terkunci karena sudah disetujui' : '' ?>.</p>

<div class="panel">
  <div class="formgrid">
    <div class="field"><label>Klien</label><div><b><?= e($p['nama_klien']) ?></b><br><span class="hint"><?= e($p['alamat']) ?> <?= e($p['no_hp']) ?></span></div></div>
    <div class="field"><label>Tanggal Penawaran</label><div><?= e(tanggal($p['tgl_penawaran'])) ?></div></div>
    <div class="field"><label>Catatan</label><div><?= $p['catatan'] !== null && $p['catatan'] !== '' ? e($p['catatan']) : '-' ?></div></div>
  </div>
</div>

<div class="panel">
  <div class="panel-title"><h2>Rincian</h2></div>
  <table>
    <thead><tr><th>No</th><th>Deskripsi</th><th class="num">Jumlah</th><th class="num">Harga Satuan</th><th class="num">Subtotal</th></tr></thead>
    <tbody>
    <?php foreach ($rinci as $n => $d): ?>
      <tr><td><?= $n + 1 ?></td><td><?= e($d['deskripsi']) ?></td><td class="num"><?= (int) $d['jumlah'] ?></td>
        <td class="num"><?= rupiah($d['harga_satuan']) ?></td><td class="num"><?= rupiah($d['jumlah'] * $d['harga_satuan']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rinci): ?><tr><td colspan="5" class="empty">Belum ada rincian</td></tr><?php endif; ?>
    </tbody>
  </table>
  <div class="totalbox" style="margin-top:15px">Total Penawaran: <?= rupiah($p['total']) ?></div>
  <div class="actions" style="margin-top:15px">
    <a class="btn secondary" href="penawaran.php">Kembali</a>
    <a class="btn dark" href="penawaran_cetak.php?id=<?= $id ?>&amp;auto=1" target="_blank">Cetak</a>
    <?php if ($p['status'] === 'menunggu'): ?>
      <form class="inline" method="post" action="penawaran.php" onsubmit="return confirm('Setujui penawaran? Rincian akan dicatat sebagai transaksi.')">
        <?= input_csrf() ?><input type="hidden" name="aksi" value="setujui"><input type="hidden" name="dari" value="detail"><input type="hidden" name="id_penawaran" value="<?= $id ?>">
        <button class="btn green" type="submit">Setujui</button>
      </form>
      <form class="inline" method="post" action="penawaran.php" onsubmit="return confirm('Tolak penawaran?')">
        <?= input_csrf() ?><input type="hidden" name="aksi" value="tolak"><input type="hidden" name="dari" value="detail"><input type="hidden" name="id_penawaran" value="<?= $id ?>">
        <button class="btn orange" type="submit">Tolak</button>
      </form>
    <?php endif; ?>
    <?php if ($p['status'] !== 'disetujui'): ?>
      <a class="btn" href="penawaran_form.php?id=<?= $id ?>"><?= $p['status'] === 'ditolak' ? 'Revisi &amp; Ajukan Ulang' : 'Edit' ?></a>
      <form class="inline" method="post" action="penawaran.php" onsubmit="return confirm('Hapus penawaran?')">
        <?= input_csrf() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_penawaran" value="<?= $id ?>">
        <button class="btn red" type="submit">Hapus</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($trx): ?>
<div class="panel">
  <div class="panel-title"><h2>Transaksi dari Penawaran Ini</h2><a class="btn secondary sm" href="transaksi.php?klien=<?= (int) $p['id_klien'] ?>">Buka Transaksi</a></div>
  <table>
    <thead><tr><th>ID</th><th>Tanggal</th><th>Deskripsi</th><th class="num">Jumlah</th><th class="num">Total</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($trx as $t): ?>
      <tr><td><?= (int) $t['id_transaksi'] ?></td><td><?= e(tanggal($t['tgl_transaksi'])) ?></td><td><?= e($t['deskripsi']) ?></td>
        <td class="num"><?= (int) $t['jumlah'] ?></td><td class="num"><?= rupiah($t['total']) ?></td>
        <td><span class="badge <?= $t['status'] === 'proses' ? 'pending' : '' ?>"><?= $t['status'] === 'proses' ? 'Proses' : 'Selesai' ?></span>
          <?php if ($t['id_invoice'] !== null): ?><span class="badge info">Ditagih</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/inc/footer.php';
