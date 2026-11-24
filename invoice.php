<?php
// Invoice gabungan (AB-08, AB-09, AB-10). Admin: buat, catat pembayaran, batalkan.
// Pemilik: hanya melihat daftar, detail, dan cetak.
require_once __DIR__ . '/inc/cek_sesi.php';
$u = wajib_login('admin', 'pemilik');
$db = db();
$admin = $u['peran'] === 'admin';

$label_status = ['belum_bayar' => 'Belum Bayar', 'sebagian' => 'Sebagian', 'lunas' => 'Lunas'];
$kelas_status = ['belum_bayar' => 'danger', 'sebagian' => 'pending sebagian', 'lunas' => ''];

/** Tanggal Y-m-d yang valid. */
function inv_tgl_valid(string $s): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
}

/** Nilai uang ke sen (bilangan bulat) agar perbandingan tidak terganggu pembulatan float. */
function inv_sen($angka): int
{
    return (int) round((float) $angka * 100);
}

/** Status invoice dari total dan jumlah terbayar (AB-10). */
function inv_status(int $total_sen, int $bayar_sen): string
{
    if ($bayar_sen <= 0) {
        return 'belum_bayar';
    }
    return $bayar_sen < $total_sen ? 'sebagian' : 'lunas';
}

// ------------------------------------------------------------ aksi tulis (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    if (!$admin) {
        pesan('error', 'Pemilik hanya dapat melihat invoice.');
        arahkan('invoice.php');
    }
    $aksi = $_POST['aksi'] ?? '';
    $id = (int) ($_POST['id_invoice'] ?? 0);

    if ($aksi === 'simpan') {
        $klien = (int) ($_POST['id_klien'] ?? 0);
        $awal = $_POST['periode_awal'] ?? '';
        $akhir = $_POST['periode_akhir'] ?? '';
        $tempo = $_POST['jatuh_tempo'] ?? '';
        $pilih = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['trx'] ?? [])))));
        $hari_ini = date('Y-m-d');
        $kembali = 'invoice.php?buat=1&id_klien=' . $klien . '&awal=' . urlencode($awal) . '&akhir=' . urlencode($akhir);

        $galat = [];
        if (!$klien || !inv_tgl_valid($awal) || !inv_tgl_valid($akhir)) {
            $galat[] = 'Klien dan periode wajib diisi dengan benar';
        } elseif ($akhir < $awal) {
            $galat[] = 'Tanggal akhir periode tidak boleh lebih awal dari tanggal awal';
        }
        if (!inv_tgl_valid($tempo)) {
            $galat[] = 'Jatuh tempo wajib diisi';
        } elseif ($tempo < $hari_ini) {
            $galat[] = 'Jatuh tempo tidak boleh lebih awal dari tanggal invoice';
        }
        if (!$pilih) {
            $galat[] = 'Pilih minimal satu transaksi yang akan ditagih';
        }
        if ($galat) {
            pesan('error', implode('. ', $galat) . '.');
            arahkan($kembali);
        }

        try {
            $db->beginTransaction();
            $tanda = implode(',', array_fill(0, count($pilih), '?'));
            $st = $db->prepare(
                "SELECT id_transaksi, total FROM transaksi
                  WHERE id_transaksi IN ($tanda) AND id_klien = ? AND status = 'selesai'
                    AND id_invoice IS NULL AND tgl_transaksi BETWEEN ? AND ?
                  FOR UPDATE"
            );
            $st->execute([...$pilih, $klien, $awal, $akhir]);
            $baris = $st->fetchAll();
            if (count($baris) !== count($pilih)) {
                $db->rollBack();
                pesan('error', 'Sebagian transaksi yang dipilih sudah ditagih atau tidak memenuhi syarat. Tampilkan ulang lalu coba lagi.');
                arahkan($kembali);
            }
            $total_sen = 0;
            foreach ($baris as $b) {
                $total_sen += inv_sen($b['total']);
            }

            // Nomor INV/YYYY/MM/NNN berurut per bulan; MAX dibaca dengan kunci di dalam transaction.
            $awalan = 'INV/' . date('Y/m') . '/';
            $st = $db->prepare('SELECT MAX(no_invoice) FROM invoice WHERE no_invoice LIKE ? FOR UPDATE');
            $st->execute([$awalan . '%']);
            $maks = $st->fetchColumn();
            $nomor = $awalan . str_pad((string) ($maks ? (int) substr($maks, -3) + 1 : 1), 3, '0', STR_PAD_LEFT);

            $db->prepare(
                'INSERT INTO invoice (no_invoice, id_klien, id_pengguna, tgl_invoice, jatuh_tempo, periode_awal, periode_akhir, total, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$nomor, $klien, $u['id'], $hari_ini, $tempo, $awal, $akhir, $total_sen / 100, 'belum_bayar']);
            $id_baru = (int) $db->lastInsertId();
            $db->prepare("UPDATE transaksi SET id_invoice = ? WHERE id_transaksi IN ($tanda) AND id_invoice IS NULL")
               ->execute([$id_baru, ...$pilih]);
            $db->commit();
        } catch (Throwable $ex) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            pesan('error', 'Invoice gagal disimpan. Coba lagi.');
            arahkan($kembali);
        }
        pesan('sukses', "Invoice $nomor disimpan.");
        arahkan('invoice.php?id=' . $id_baru);
    }

    if ($aksi === 'bayar' && $id) {
        $tgl = $_POST['tgl_bayar'] ?? '';
        $jumlah = trim($_POST['jumlah'] ?? '');
        $metode = $_POST['metode'] ?? '';
        $ket = trim($_POST['keterangan'] ?? '');
        $kembali = 'invoice.php?id=' . $id;

        $galat = [];
        if (!inv_tgl_valid($tgl)) {
            $galat[] = 'Tanggal bayar wajib diisi';
        }
        if (!is_numeric($jumlah) || (float) $jumlah <= 0) {
            $galat[] = 'Jumlah bayar harus angka lebih dari 0';
        }
        if (!in_array($metode, ['transfer', 'tunai'], true)) {
            $galat[] = 'Metode pembayaran tidak valid';
        }
        if (mb_strlen($ket) > 255) {
            $galat[] = 'Keterangan maksimal 255 karakter';
        }
        if ($galat) {
            pesan('error', implode('. ', $galat) . '.');
            arahkan($kembali);
        }

        try {
            $db->beginTransaction();
            $st = $db->prepare('SELECT total FROM invoice WHERE id_invoice = ? FOR UPDATE');
            $st->execute([$id]);
            $total = $st->fetchColumn();
            if ($total === false) {
                $db->rollBack();
                pesan('error', 'Invoice tidak ditemukan.');
                arahkan('invoice.php');
            }
            $st = $db->prepare('SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran WHERE id_invoice = ?');
            $st->execute([$id]);
            $total_sen = inv_sen($total);
            $bayar_sen = inv_sen($st->fetchColumn());
            $jumlah_sen = inv_sen($jumlah);
            if ($jumlah_sen > $total_sen - $bayar_sen) {
                $db->rollBack();
                pesan('error', 'Jumlah bayar melebihi sisa tagihan (' . rupiah(($total_sen - $bayar_sen) / 100) . ').');
                arahkan($kembali);
            }
            $db->prepare(
                'INSERT INTO pembayaran (id_invoice, id_pengguna, tgl_bayar, jumlah, metode, keterangan) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$id, $u['id'], $tgl, $jumlah_sen / 100, $metode, $ket ?: null]);
            $status = inv_status($total_sen, $bayar_sen + $jumlah_sen);
            $db->prepare('UPDATE invoice SET status = ? WHERE id_invoice = ?')->execute([$status, $id]);
            $db->commit();
        } catch (Throwable $ex) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            pesan('error', 'Pembayaran gagal disimpan. Coba lagi.');
            arahkan($kembali);
        }
        pesan('sukses', 'Pembayaran dicatat. Status invoice: ' . $label_status[$status] . '.');
        arahkan($kembali);
    }

    if ($aksi === 'batal' && $id) {
        try {
            $db->beginTransaction();
            $st = $db->prepare('SELECT no_invoice FROM invoice WHERE id_invoice = ? FOR UPDATE');
            $st->execute([$id]);
            $no = $st->fetchColumn();
            $st = $db->prepare('SELECT COUNT(*) FROM pembayaran WHERE id_invoice = ?');
            $st->execute([$id]);
            if ($no === false) {
                $db->rollBack();
                pesan('error', 'Invoice tidak ditemukan.');
                arahkan('invoice.php');
            }
            if ($st->fetchColumn() > 0) {
                $db->rollBack();
                pesan('error', 'Invoice tidak dapat dibatalkan karena sudah ada pembayaran.');
                arahkan('invoice.php?id=' . $id);
            }
            $db->prepare('UPDATE transaksi SET id_invoice = NULL WHERE id_invoice = ?')->execute([$id]);
            $db->prepare('DELETE FROM invoice WHERE id_invoice = ?')->execute([$id]);
            $db->commit();
        } catch (Throwable $ex) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            pesan('error', 'Invoice gagal dibatalkan. Coba lagi.');
            arahkan('invoice.php?id=' . $id);
        }
        pesan('sukses', "Invoice $no dibatalkan; transaksinya dapat ditagih kembali.");
        arahkan('invoice.php');
    }
    arahkan('invoice.php');
}

// ------------------------------------------------------------ data tampilan
$sql_inv = 'SELECT i.*, k.nama_klien, k.alamat, k.no_hp, p.nama AS nama_pembuat, COALESCE(b.bayar, 0) AS terbayar
              FROM invoice i
              JOIN klien k ON k.id_klien = i.id_klien
              JOIN pengguna p ON p.id_pengguna = i.id_pengguna
              LEFT JOIN (SELECT id_invoice, SUM(jumlah) bayar FROM pembayaran GROUP BY id_invoice) b
                     ON b.id_invoice = i.id_invoice';

$detail = null;
$buat = null;
if (isset($_GET['id'])) {
    $st = $db->prepare("$sql_inv WHERE i.id_invoice = ?");
    $st->execute([(int) $_GET['id']]);
    $detail = $st->fetch() ?: null;
    if (!$detail) {
        pesan('error', 'Invoice tidak ditemukan.');
        arahkan('invoice.php');
    }
    $st = $db->prepare('SELECT * FROM transaksi WHERE id_invoice = ? ORDER BY tgl_transaksi, id_transaksi');
    $st->execute([$detail['id_invoice']]);
    $rincian = $st->fetchAll();
    $st = $db->prepare(
        'SELECT b.*, p.nama AS nama_pencatat FROM pembayaran b JOIN pengguna p ON p.id_pengguna = b.id_pengguna
          WHERE b.id_invoice = ? ORDER BY b.tgl_bayar, b.id_pembayaran'
    );
    $st->execute([$detail['id_invoice']]);
    $riwayat = $st->fetchAll();
    $sisa = (inv_sen($detail['total']) - inv_sen($detail['terbayar'])) / 100;
    $cetak = isset($_GET['cetak']);
} elseif (isset($_GET['buat']) && $admin) {
    $buat = [
        'id_klien' => (int) ($_GET['id_klien'] ?? 0),
        'awal'     => $_GET['awal'] ?? date('Y-m-01'),
        'akhir'    => $_GET['akhir'] ?? date('Y-m-t'),
        'tempo'    => date('Y-m-d', strtotime('+14 days')),
        'trx'      => null,
        'galat'    => '',
    ];
    $daftar_klien = $db->query('SELECT id_klien, nama_klien FROM klien ORDER BY nama_klien')->fetchAll();
    if ($buat['id_klien'] && isset($_GET['awal'])) {
        if (!inv_tgl_valid($buat['awal']) || !inv_tgl_valid($buat['akhir'])) {
            $buat['galat'] = 'Periode tidak valid.';
        } elseif ($buat['akhir'] < $buat['awal']) {
            $buat['galat'] = 'Tanggal akhir periode tidak boleh lebih awal dari tanggal awal.';
        } else {
            $st = $db->prepare(
                "SELECT * FROM transaksi WHERE id_klien = ? AND status = 'selesai' AND id_invoice IS NULL
                    AND tgl_transaksi BETWEEN ? AND ? ORDER BY tgl_transaksi, id_transaksi"
            );
            $st->execute([$buat['id_klien'], $buat['awal'], $buat['akhir']]);
            $buat['trx'] = $st->fetchAll();
        }
    }
} else {
    $f_status = $_GET['status'] ?? '';
    $cari = trim($_GET['cari'] ?? '');
    $where = '';
    $param = [];
    if (isset($label_status[$f_status])) {
        $where = ' WHERE i.status = ?';
        $param[] = $f_status;
    } else {
        $f_status = '';
    }
    $st = $db->prepare("$sql_inv$where ORDER BY i.tgl_invoice DESC, i.id_invoice DESC");
    $st->execute($param);
    $data = $st->fetchAll();
    if ($cari !== '') {
        // Linear Search pada gabungan nomor invoice dan nama klien.
        foreach ($data as &$d) {
            $d['kunci_cari'] = $d['no_invoice'] . ' ' . $d['nama_klien'];
        }
        unset($d);
        $data = linear_search($data, 'kunci_cari', $cari);
    }
}

$judul = 'Invoice';
$aktif = 'invoice';
require __DIR__ . '/inc/header.php';
?>
<style>
.inv-head{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:16px;font-size:13px}
.inv-head table{width:auto}.inv-head td{border:0;padding:3px 10px 3px 0;background:none!important}
.inv-sum{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:14px 0}
.inv-sum div{background:var(--cream);border:1px solid var(--line);border-top:3px solid var(--oker);border-radius:12px;padding:12px 14px}
.inv-sum small{color:var(--muted);font-size:12px}.inv-sum b{display:block;font-family:var(--serif);font-size:20px;color:var(--brown);margin-top:4px}
.kop{border-bottom:2px solid #000;padding-bottom:10px;margin-bottom:16px}.kop b{font-family:var(--serif);font-size:24px}
@media(max-width:620px){.inv-sum{grid-template-columns:1fr}}
@media print{.inv-sum div{background:none;border:1px solid #999}.inv-sum b{color:#000}}
</style>

<?php if ($detail): ?>
  <div class="no-print">
    <h1>Invoice <?= e($detail['no_invoice']) ?></h1>
    <p class="subtitle">Rincian transaksi dan riwayat pembayaran.</p>
  </div>
  <div class="panel">
    <div class="only-print kop"><b>ArtHandler</b><br>Jasa Art Handling · Invoice</div>
    <div class="panel-title">
      <h2>INVOICE <?= e($detail['no_invoice']) ?></h2>
      <span class="badge <?= $kelas_status[$detail['status']] ?>"><?= $label_status[$detail['status']] ?></span>
    </div>
    <div class="inv-head">
      <table>
        <tr><td>Kepada</td><td><b><?= e($detail['nama_klien']) ?></b></td></tr>
        <tr><td>Alamat</td><td><?= e($detail['alamat']) ?></td></tr>
        <tr><td>No. HP</td><td><?= e($detail['no_hp']) ?></td></tr>
      </table>
      <table>
        <tr><td>Tanggal</td><td><?= tanggal($detail['tgl_invoice']) ?></td></tr>
        <tr><td>Jatuh Tempo</td><td><?= tanggal($detail['jatuh_tempo']) ?></td></tr>
        <tr><td>Periode</td><td><?= tanggal($detail['periode_awal']) ?> – <?= tanggal($detail['periode_akhir']) ?></td></tr>
      </table>
    </div>
    <table>
      <thead><tr><th>No</th><th>Tanggal</th><th>Deskripsi</th><th class="num">Jumlah</th><th class="num">Total</th></tr></thead>
      <tbody>
      <?php foreach ($rincian as $n => $t): ?>
        <tr><td><?= $n + 1 ?></td><td><?= tanggal($t['tgl_transaksi']) ?></td><td><?= e($t['deskripsi']) ?></td>
            <td class="num"><?= (int) $t['jumlah'] ?></td><td class="num"><?= rupiah($t['total']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$rincian): ?><tr><td colspan="5" class="empty">Tidak ada rincian</td></tr><?php endif; ?>
      </tbody>
    </table>
    <div class="inv-sum">
      <div><small>Total Tagihan</small><b><?= rupiah($detail['total']) ?></b></div>
      <div><small>Terbayar</small><b><?= rupiah($detail['terbayar']) ?></b></div>
      <div><small>Sisa Tagihan</small><b><?= rupiah($sisa) ?></b></div>
    </div>

    <h2 style="font-size:15px;margin:18px 0 10px">Riwayat Pembayaran</h2>
    <table>
      <thead><tr><th>Tanggal</th><th class="num">Jumlah</th><th>Metode</th><th>Keterangan</th><th>Dicatat oleh</th></tr></thead>
      <tbody>
      <?php foreach ($riwayat as $r): ?>
        <tr><td><?= tanggal($r['tgl_bayar']) ?></td><td class="num"><?= rupiah($r['jumlah']) ?></td>
            <td><?= $r['metode'] === 'transfer' ? 'Transfer' : 'Tunai' ?></td><td><?= e($r['keterangan']) ?></td><td><?= e($r['nama_pencatat']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$riwayat): ?><tr><td colspan="5" class="empty">Belum ada pembayaran</td></tr><?php endif; ?>
      </tbody>
    </table>
    <p class="hint">Dibuat oleh <?= e($detail['nama_pembuat']) ?>.</p>

    <div class="actions no-print" style="margin-top:15px">
      <?php if ($cetak): ?>
        <button class="btn" type="button" onclick="window.print()">Cetak</button>
        <a class="btn secondary" href="invoice.php?id=<?= (int) $detail['id_invoice'] ?>">Kembali</a>
      <?php else: ?>
        <a class="btn" href="invoice.php?id=<?= (int) $detail['id_invoice'] ?>&amp;cetak=1">Cetak</a>
        <a class="btn secondary" href="invoice.php">Kembali ke Daftar</a>
        <?php if ($admin && !$riwayat): ?>
          <form class="inline" method="post" onsubmit="return confirm('Batalkan invoice ini? Transaksinya akan dapat ditagih kembali.')">
            <?= input_csrf() ?><input type="hidden" name="aksi" value="batal"><input type="hidden" name="id_invoice" value="<?= (int) $detail['id_invoice'] ?>">
            <button class="btn red" type="submit">Batalkan Invoice</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($admin && !$cetak && $detail['status'] !== 'lunas'): ?>
  <div class="panel no-print">
    <div class="panel-title"><h2>Catat Pembayaran</h2><span class="hint">Sisa tagihan: <b><?= rupiah($sisa) ?></b></span></div>
    <form method="post">
      <?= input_csrf() ?>
      <input type="hidden" name="aksi" value="bayar">
      <input type="hidden" name="id_invoice" value="<?= (int) $detail['id_invoice'] ?>">
      <div class="formgrid">
        <div class="field"><label>Tanggal Bayar *</label><input type="date" name="tgl_bayar" value="<?= date('Y-m-d') ?>" required></div>
        <div class="field"><label>Jumlah (Rp) *</label><input type="number" name="jumlah" min="1" step="1" max="<?= (int) ceil($sisa) ?>" value="<?= (int) $sisa ?>" required></div>
        <div class="field"><label>Metode *</label>
          <select name="metode"><option value="transfer">Transfer</option><option value="tunai">Tunai</option></select></div>
        <div class="field full"><label>Keterangan</label><input name="keterangan" maxlength="255" placeholder="mis. Transfer BCA a.n. klien"></div>
      </div>
      <div class="actions" style="margin-top:15px"><button class="btn green" type="submit">Simpan Pembayaran</button></div>
    </form>
  </div>
  <?php endif; ?>

<?php elseif ($buat): ?>
  <h1>Buat Invoice Gabungan</h1>
  <p class="subtitle">Pilih klien dan periode; transaksi selesai yang belum ditagih akan ditampilkan.</p>
  <?php if ($buat['galat']): ?><div class="alert error"><?= e($buat['galat']) ?></div><?php endif; ?>
  <div class="panel">
    <form method="get" class="formgrid">
      <input type="hidden" name="buat" value="1">
      <div class="field"><label>Klien *</label>
        <select name="id_klien" required>
          <option value="">— pilih klien —</option>
          <?php foreach ($daftar_klien as $k): ?>
            <option value="<?= (int) $k['id_klien'] ?>" <?= $k['id_klien'] == $buat['id_klien'] ? 'selected' : '' ?>><?= e($k['nama_klien']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label>Periode Awal *</label><input type="date" name="awal" value="<?= e($buat['awal']) ?>" required></div>
      <div class="field"><label>Periode Akhir *</label><input type="date" name="akhir" value="<?= e($buat['akhir']) ?>" required></div>
      <div class="actions field full">
        <button class="btn secondary" type="submit">Tampilkan Transaksi</button>
        <a class="btn secondary" href="invoice.php">Kembali</a>
      </div>
    </form>
  </div>

  <?php if ($buat['trx'] !== null): ?>
  <div class="panel">
    <?php if (!$buat['trx']): ?>
      <div class="empty">Tidak ada transaksi yang dapat ditagih pada periode ini.</div>
    <?php else: ?>
    <form method="post">
      <?= input_csrf() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id_klien" value="<?= (int) $buat['id_klien'] ?>">
      <input type="hidden" name="periode_awal" value="<?= e($buat['awal']) ?>">
      <input type="hidden" name="periode_akhir" value="<?= e($buat['akhir']) ?>">
      <table>
        <thead><tr><th><input type="checkbox" checked onclick="document.querySelectorAll('.cek-trx').forEach(c=>c.checked=this.checked);hitungTotal()"></th>
          <th>Tanggal</th><th>Deskripsi</th><th class="num">Jumlah</th><th class="num">Total</th></tr></thead>
        <tbody>
        <?php $sum = 0; foreach ($buat['trx'] as $t): $sum += (float) $t['total']; ?>
          <tr>
            <td><input type="checkbox" class="cek-trx" name="trx[]" value="<?= (int) $t['id_transaksi'] ?>" data-total="<?= e($t['total']) ?>" checked onchange="hitungTotal()"></td>
            <td><?= tanggal($t['tgl_transaksi']) ?></td><td><?= e($t['deskripsi']) ?></td>
            <td class="num"><?= (int) $t['jumlah'] ?></td><td class="num"><?= rupiah($t['total']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="totalbox" style="margin-top:14px">Total Tagihan: <span id="total-inv"><?= rupiah($sum) ?></span></div>
      <div class="formgrid" style="margin-top:14px">
        <div class="field"><label>Tanggal Invoice</label><input type="date" value="<?= date('Y-m-d') ?>" disabled></div>
        <div class="field"><label>Jatuh Tempo *</label><input type="date" name="jatuh_tempo" value="<?= e($buat['tempo']) ?>" min="<?= date('Y-m-d') ?>" required></div>
      </div>
      <div class="actions" style="margin-top:15px"><button class="btn" type="submit">Simpan Invoice</button></div>
    </form>
    <script>
    function hitungTotal() {
      let t = 0;
      document.querySelectorAll('.cek-trx:checked').forEach(c => t += parseFloat(c.dataset.total));
      document.getElementById('total-inv').textContent = 'Rp ' + Math.round(t).toLocaleString('id-ID');
    }
    </script>
    <?php endif; ?>
  </div>
  <?php endif; ?>

<?php else: ?>
  <h1>Invoice</h1>
  <p class="subtitle"><?= $admin ? 'Buat invoice gabungan per klien dan periode, catat pembayaran, dan cetak.' : 'Daftar invoice beserta status pembayarannya.' ?></p>
  <div class="panel">
    <form class="toolbar" method="get">
      <input name="cari" value="<?= e($cari) ?>" placeholder="Cari no. invoice / klien (Linear Search)…">
      <select name="status">
        <option value="">Semua status</option>
        <?php foreach ($label_status as $kode => $lbl): ?>
          <option value="<?= $kode ?>" <?= $f_status === $kode ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn secondary" type="submit">Tampilkan</button>
      <?php if ($admin): ?><a class="btn" href="invoice.php?buat=1">+ Buat Invoice</a><?php endif; ?>
      <?php if ($f_status !== '' || $cari !== ''): ?><a class="btn secondary" href="invoice.php">Reset</a><?php endif; ?>
    </form>
    <table>
      <thead><tr><th>No. Invoice</th><th>Klien</th><th>Tanggal</th><th>Jatuh Tempo</th><th class="num">Total</th><th class="num">Terbayar</th><th class="num">Sisa</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($data as $i): ?>
        <tr>
          <td><b><?= e($i['no_invoice']) ?></b></td><td><?= e($i['nama_klien']) ?></td>
          <td><?= tanggal($i['tgl_invoice']) ?></td><td><?= tanggal($i['jatuh_tempo']) ?></td>
          <td class="num"><?= rupiah($i['total']) ?></td><td class="num"><?= rupiah($i['terbayar']) ?></td>
          <td class="num"><?= rupiah((inv_sen($i['total']) - inv_sen($i['terbayar'])) / 100) ?></td>
          <td><span class="badge <?= $kelas_status[$i['status']] ?>"><?= $label_status[$i['status']] ?></span></td>
          <td><a class="btn secondary sm" href="invoice.php?id=<?= (int) $i['id_invoice'] ?>">Detail</a>
              <a class="btn sm" href="invoice.php?id=<?= (int) $i['id_invoice'] ?>&amp;cetak=1">Cetak</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="9" class="empty">Data tidak ditemukan</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/inc/footer.php';
