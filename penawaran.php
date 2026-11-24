<?php
require_once __DIR__ . '/inc/cek_sesi.php';
wajib_login('admin');
require_once __DIR__ . '/penawaran_fungsi.php';
$db = db();

// ------------------------------------------------------------ setujui / tolak / hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';
    $id = (int) ($_POST['id_penawaran'] ?? 0);
    $kembali = ($_POST['dari'] ?? '') === 'detail' && $aksi !== 'hapus' ? "penawaran_detail.php?id=$id" : 'penawaran.php';

    if ($aksi === 'setujui' && $id) {
        // AB-07: satu transaksi DB — status disetujui dan setiap rincian menjadi transaksi berstatus proses.
        $db->beginTransaction();
        try {
            $st = $db->prepare('SELECT * FROM penawaran WHERE id_penawaran = ? FOR UPDATE');
            $st->execute([$id]);
            $p = $st->fetch();
            $rinci = $p ? pnw_detail($db, $id) : [];
            if (!$p || $p['status'] !== 'menunggu') {
                $db->rollBack();
                pesan('error', 'Hanya penawaran berstatus menunggu yang dapat disetujui.');
                arahkan($kembali);
            }
            if (!$rinci) {
                $db->rollBack();
                pesan('error', 'Penawaran tidak memiliki rincian.');
                arahkan($kembali);
            }
            $db->prepare("UPDATE penawaran SET status = 'disetujui' WHERE id_penawaran = ?")->execute([$id]);
            $ins = $db->prepare(
                "INSERT INTO transaksi (id_klien, id_jasa, id_penawaran, tgl_transaksi, deskripsi, jumlah, total, status)
                 VALUES (?, NULL, ?, ?, ?, ?, ?, 'proses')"
            );
            $hariIni = date('Y-m-d');
            foreach ($rinci as $d) {
                $ins->execute([$p['id_klien'], $id, $hariIni, $d['deskripsi'], $d['jumlah'], round($d['jumlah'] * $d['harga_satuan'], 2)]);
            }
            $db->commit();
        } catch (Throwable $ex) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $ex;
        }
        pesan('sukses', 'Penawaran ' . $p['no_penawaran'] . ' disetujui; ' . count($rinci) . ' rincian dicatat sebagai transaksi berstatus proses.');
    } elseif ($aksi === 'tolak' && $id) {
        $st = $db->prepare("UPDATE penawaran SET status = 'ditolak' WHERE id_penawaran = ? AND status = 'menunggu'");
        $st->execute([$id]);
        $st->rowCount()
            ? pesan('sukses', 'Penawaran ditolak. Penawaran dapat direvisi lalu diajukan kembali.')
            : pesan('error', 'Hanya penawaran berstatus menunggu yang dapat ditolak.');
    } elseif ($aksi === 'hapus' && $id) {
        $st = $db->prepare("DELETE FROM penawaran WHERE id_penawaran = ? AND status IN ('menunggu', 'ditolak')");
        $st->execute([$id]);
        $st->rowCount()
            ? pesan('sukses', 'Penawaran dihapus.')
            : pesan('error', 'Penawaran yang sudah disetujui tidak dapat dihapus.');
    }
    arahkan($kembali);
}

// ------------------------------------------------------------ tampil
$data = $db->query(
    'SELECT p.id_penawaran, p.no_penawaran, p.tgl_penawaran, p.status, k.nama_klien,
            COUNT(d.id_detail) AS jml_item, COALESCE(SUM(d.jumlah * d.harga_satuan), 0) AS total
       FROM penawaran p
       JOIN klien k ON k.id_klien = p.id_klien
       LEFT JOIN detail_penawaran d ON d.id_penawaran = p.id_penawaran
      GROUP BY p.id_penawaran, p.no_penawaran, p.tgl_penawaran, p.status, k.nama_klien
      ORDER BY p.tgl_penawaran DESC, p.id_penawaran DESC'
)->fetchAll();

$judul = 'Penawaran Harga';
$aktif = 'penawaran';
require __DIR__ . '/inc/header.php';
?>
<h1>Penawaran Harga</h1>
<p class="subtitle">Susun penawaran berisi rincian jasa dan material. Penawaran disetujui menjadi transaksi berstatus proses; penawaran ditolak dapat direvisi dan diajukan kembali.</p>

<div class="panel">
  <div class="toolbar">
    <a class="btn" href="penawaran_form.php">+ Buat Penawaran</a>
  </div>
  <table>
    <thead><tr><th>No. Penawaran</th><th>Klien</th><th>Tanggal</th><th class="num">Item</th><th class="num">Total</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($data as $p): $id = (int) $p['id_penawaran']; ?>
      <tr>
        <td><b><?= e($p['no_penawaran']) ?></b></td>
        <td><?= e($p['nama_klien']) ?></td>
        <td><?= e(tanggal($p['tgl_penawaran'])) ?></td>
        <td class="num"><?= (int) $p['jml_item'] ?></td>
        <td class="num"><?= rupiah($p['total']) ?></td>
        <td><?= pnw_badge($p['status']) ?></td>
        <td>
          <a class="btn secondary sm" href="penawaran_detail.php?id=<?= $id ?>">Detail</a>
          <a class="btn dark sm" href="penawaran_cetak.php?id=<?= $id ?>&amp;auto=1" target="_blank">Cetak</a>
          <?php if ($p['status'] === 'menunggu'): ?>
            <form class="inline" method="post" onsubmit="return confirm('Setujui penawaran? Rincian akan dicatat sebagai transaksi.')">
              <?= input_csrf() ?><input type="hidden" name="aksi" value="setujui"><input type="hidden" name="id_penawaran" value="<?= $id ?>">
              <button class="btn green sm" type="submit">Setujui</button>
            </form>
            <form class="inline" method="post" onsubmit="return confirm('Tolak penawaran?')">
              <?= input_csrf() ?><input type="hidden" name="aksi" value="tolak"><input type="hidden" name="id_penawaran" value="<?= $id ?>">
              <button class="btn orange sm" type="submit">Tolak</button>
            </form>
          <?php endif; ?>
          <?php if ($p['status'] !== 'disetujui'): ?>
            <a class="btn sm" href="penawaran_form.php?id=<?= $id ?>"><?= $p['status'] === 'ditolak' ? 'Revisi' : 'Edit' ?></a>
            <form class="inline" method="post" onsubmit="return confirm('Hapus penawaran?')">
              <?= input_csrf() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_penawaran" value="<?= $id ?>">
              <button class="btn red sm" type="submit">Hapus</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$data): ?><tr><td colspan="7" class="empty">Belum ada penawaran</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/inc/footer.php';
