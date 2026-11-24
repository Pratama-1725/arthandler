<?php
require_once __DIR__ . '/inc/cek_sesi.php';
wajib_login('admin');
$db = db();

/** Tanggal format Y-m-d yang benar-benar ada di kalender. */
function trx_tanggal_valid(string $ymd): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $ymd);
    return $d !== false && $d->format('Y-m-d') === $ymd;
}

// ------------------------------------------------------------ simpan / selesai / hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';
    $id = (int) ($_POST['id_transaksi'] ?? 0);

    // Transaksi lama (untuk edit, ubah status, hapus) wajib ada dan belum ditagih (AB-09).
    $lama = null;
    if ($id) {
        $st = $db->prepare('SELECT * FROM transaksi WHERE id_transaksi = ?');
        $st->execute([$id]);
        $lama = $st->fetch() ?: null;
        if (!$lama) {
            pesan('error', 'Transaksi tidak ditemukan.');
            arahkan('transaksi.php');
        }
        if ($lama['id_invoice'] !== null) {
            pesan('error', 'Transaksi sudah masuk invoice sehingga tidak dapat diubah, diubah statusnya, atau dihapus.');
            arahkan('transaksi.php');
        }
    }

    if ($aksi === 'simpan') {
        $idKlien = (int) ($_POST['id_klien'] ?? 0);
        $idJasa = (int) ($_POST['id_jasa'] ?? 0);
        $tgl = trim($_POST['tgl_transaksi'] ?? '');
        $jumlah = filter_var($_POST['jumlah'] ?? '', FILTER_VALIDATE_INT);
        $nominal = trim($_POST['nominal'] ?? '');
        $status = $_POST['status'] ?? 'proses';
        $galat = [];

        $st = $db->prepare('SELECT COUNT(*) FROM klien WHERE id_klien = ?');
        $st->execute([$idKlien]);
        if (!$st->fetchColumn()) {
            $galat[] = 'Klien wajib dipilih dari daftar';
        }
        $jasa = null;
        if ($idJasa) {
            $st = $db->prepare('SELECT nama_jasa, tarif FROM jenis_jasa WHERE id_jasa = ?');
            $st->execute([$idJasa]);
            $jasa = $st->fetch() ?: null;
            if (!$jasa) {
                $galat[] = 'Jenis jasa tidak ditemukan';
            }
        } elseif (!$lama || $lama['id_jasa'] !== null) {
            // Tanpa jenis jasa hanya untuk transaksi hasil penawaran yang sudah ada.
            $galat[] = 'Jenis jasa wajib dipilih';
        }
        if (!trx_tanggal_valid($tgl)) {
            $galat[] = 'Tanggal transaksi tidak valid';
        }
        if ($jumlah === false || $jumlah < 1 || $jumlah > 100000) {
            $galat[] = 'Jumlah harus bilangan bulat minimal 1';
        }
        if ($nominal !== '' && (!is_numeric($nominal) || (float) $nominal <= 0 || (float) $nominal > 999999999999)) {
            $galat[] = 'Nominal custom harus angka lebih dari 0 (kosongkan untuk total otomatis)';
        }
        if (!in_array($status, ['proses', 'selesai'], true)) {
            $galat[] = 'Status tidak valid';
        }
        if ($galat) {
            pesan('error', implode('. ', $galat) . '.');
            arahkan('transaksi.php' . ($id ? "?edit=$id" : '?tambah=1'));
        }

        // Total dihitung server (AB-04): nominal custom, atau jumlah x tarif.
        if ($jasa) {
            $deskripsi = $jasa['nama_jasa'];
            $total = $nominal !== '' ? (float) $nominal : $jumlah * (float) $jasa['tarif'];
        } else {
            $deskripsi = $lama['deskripsi'];
            $hargaSatuan = (float) $lama['total'] / max(1, (int) $lama['jumlah']);
            $total = $nominal !== '' ? (float) $nominal : $jumlah * $hargaSatuan;
        }
        $total = round($total, 2);

        if ($id) {
            $st = $db->prepare(
                'UPDATE transaksi SET id_klien = ?, id_jasa = ?, tgl_transaksi = ?, deskripsi = ?, jumlah = ?, total = ?, status = ?
                  WHERE id_transaksi = ? AND id_invoice IS NULL'
            );
            $st->execute([$idKlien, $jasa ? $idJasa : null, $tgl, $deskripsi, $jumlah, $total, $status, $id]);
            pesan('sukses', 'Transaksi diperbarui. Total ' . rupiah($total) . '.');
        } else {
            $db->prepare(
                'INSERT INTO transaksi (id_klien, id_jasa, tgl_transaksi, deskripsi, jumlah, total, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$idKlien, $idJasa, $tgl, $deskripsi, $jumlah, $total, $status]);
            pesan('sukses', 'Transaksi baru disimpan. Total ' . rupiah($total) . '.');
        }
    } elseif ($aksi === 'selesai' && $lama) {
        $st = $db->prepare("UPDATE transaksi SET status = 'selesai' WHERE id_transaksi = ? AND status = 'proses' AND id_invoice IS NULL");
        $st->execute([$id]);
        pesan($st->rowCount() ? 'sukses' : 'error', $st->rowCount() ? 'Status transaksi diubah menjadi selesai.' : 'Status transaksi tidak dapat diubah.');
    } elseif ($aksi === 'hapus' && $lama) {
        $st = $db->prepare('DELETE FROM transaksi WHERE id_transaksi = ? AND id_invoice IS NULL');
        $st->execute([$id]);
        pesan('sukses', 'Transaksi dihapus.');
    }
    arahkan('transaksi.php');
}

// ------------------------------------------------------------ tampil
$klienList = $db->query('SELECT id_klien, nama_klien FROM klien ORDER BY nama_klien')->fetchAll();
$jasaList = $db->query('SELECT id_jasa, nama_jasa, tarif FROM jenis_jasa ORDER BY nama_jasa')->fetchAll();

$fKlien = (int) ($_GET['klien'] ?? 0);
$fStatus = $_GET['status'] ?? '';
$fStatus = in_array($fStatus, ['proses', 'selesai'], true) ? $fStatus : '';
$urut = $_GET['urut'] ?? '';
$urut = in_array($urut, ['tanggal', 'total'], true) ? $urut : '';
$cari = trim($_GET['cari'] ?? '');

$where = [];
$param = [];
if ($fKlien) {
    $where[] = 't.id_klien = ?';
    $param[] = $fKlien;
}
if ($fStatus !== '') {
    $where[] = 't.status = ?';
    $param[] = $fStatus;
}
$st = $db->prepare(
    'SELECT t.*, k.nama_klien, p.no_penawaran
       FROM transaksi t
       JOIN klien k ON k.id_klien = t.id_klien
       LEFT JOIN penawaran p ON p.id_penawaran = t.id_penawaran'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY t.tgl_transaksi DESC, t.id_transaksi DESC'
);
$st->execute($param);
$data = $st->fetchAll();
foreach ($data as &$baris) {
    $baris['teks_cari'] = $baris['nama_klien'] . ' ' . $baris['deskripsi'];
}
unset($baris);

if ($urut === 'tanggal') {
    $data = bubble_sort($data, fn($a, $b) => [$a['tgl_transaksi'], $a['id_transaksi']] <=> [$b['tgl_transaksi'], $b['id_transaksi']]);
} elseif ($urut === 'total') {
    $data = bubble_sort($data, fn($a, $b) => (float) $b['total'] <=> (float) $a['total']);
}
if ($cari !== '') {
    $data = linear_search($data, 'teks_cari', $cari);
}

$form = null;
if (isset($_GET['edit'])) {
    $st = $db->prepare('SELECT * FROM transaksi WHERE id_transaksi = ?');
    $st->execute([(int) $_GET['edit']]);
    $form = $st->fetch() ?: null;
    if ($form && $form['id_invoice'] !== null) {
        pesan('error', 'Transaksi sudah masuk invoice sehingga tidak dapat diubah.');
        arahkan('transaksi.php');
    }
    if ($form) {
        // Nominal custom ditampilkan bila total berbeda dari jumlah x tarif.
        $form['nominal'] = '';
        foreach ($jasaList as $j) {
            if ((int) $j['id_jasa'] === (int) $form['id_jasa'] && round($j['tarif'] * $form['jumlah'], 2) != round($form['total'], 2)) {
                $form['nominal'] = (int) $form['total'];
            }
        }
    }
} elseif (isset($_GET['tambah'])) {
    $form = ['id_transaksi' => 0, 'id_klien' => 0, 'id_jasa' => 0, 'tgl_transaksi' => date('Y-m-d'), 'jumlah' => 1,
             'nominal' => '', 'status' => 'proses', 'deskripsi' => '', 'id_penawaran' => null, 'total' => 0];
}

$total_tampil = array_sum(array_column($data, 'total'));
$qs = fn(array $ubah) => 'transaksi.php?' . http_build_query(array_filter(
    array_merge(['cari' => $cari, 'klien' => $fKlien ?: '', 'status' => $fStatus, 'urut' => $urut], $ubah),
    fn($v) => $v !== '' && $v !== null
));

$judul = 'Transaksi';
$aktif = 'transaksi';
require __DIR__ . '/inc/header.php';
?>
<h1>Transaksi</h1>
<p class="subtitle">Catat transaksi klien per tanggal; total otomatis dari tarif × jumlah atau nominal custom. Transaksi yang sudah ditagih di invoice terkunci.</p>

<?php if ($form): ?>
<div class="panel">
  <div class="panel-title"><h2><?= $form['id_transaksi'] ? 'Edit' : 'Tambah' ?> Transaksi</h2></div>
  <form method="post" id="form-trx">
    <?= input_csrf() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id_transaksi" value="<?= (int) $form['id_transaksi'] ?>">
    <div class="formgrid">
      <div class="field"><label>Klien *</label>
        <select name="id_klien" required>
          <option value="">— pilih klien —</option>
          <?php foreach ($klienList as $k): ?>
            <option value="<?= (int) $k['id_klien'] ?>" <?= (int) $k['id_klien'] === (int) $form['id_klien'] ? 'selected' : '' ?>><?= e($k['nama_klien']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Jenis Jasa *</label>
        <select name="id_jasa" id="trx-jasa" <?= $form['id_transaksi'] && $form['id_jasa'] === null ? '' : 'required' ?>>
          <?php if ($form['id_transaksi'] && $form['id_jasa'] === null): ?>
            <option value="" data-tarif="<?= e((float) $form['total'] / max(1, (int) $form['jumlah'])) ?>" selected><?= e($form['deskripsi']) ?> (rincian penawaran)</option>
          <?php else: ?>
            <option value="">— pilih jenis jasa —</option>
          <?php endif; ?>
          <?php foreach ($jasaList as $j): ?>
            <option value="<?= (int) $j['id_jasa'] ?>" data-tarif="<?= e($j['tarif']) ?>" <?= (int) $j['id_jasa'] === (int) $form['id_jasa'] ? 'selected' : '' ?>><?= e($j['nama_jasa']) ?> — <?= rupiah($j['tarif']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Tanggal *</label><input type="date" name="tgl_transaksi" value="<?= e($form['tgl_transaksi']) ?>" required></div>
      <div class="field"><label>Jumlah (orang/unit) *</label><input type="number" name="jumlah" id="trx-jumlah" min="1" step="1" value="<?= (int) $form['jumlah'] ?>" required></div>
      <div class="field"><label>Nominal Custom (Rp)</label><input type="number" name="nominal" id="trx-nominal" min="1" step="1" value="<?= e($form['nominal']) ?>" placeholder="kosongkan = tarif × jumlah"></div>
      <div class="field"><label>Status *</label>
        <select name="status">
          <option value="proses" <?= $form['status'] === 'proses' ? 'selected' : '' ?>>Proses</option>
          <option value="selesai" <?= $form['status'] === 'selesai' ? 'selected' : '' ?>>Selesai</option>
        </select>
      </div>
    </div>
    <div class="totalbox" style="margin-top:15px">Total: <span id="trx-total">Rp 0</span></div>
    <div class="actions" style="margin-top:15px">
      <button class="btn" type="submit">Simpan</button>
      <a class="btn secondary" href="transaksi.php">Batal</a>
    </div>
  </form>
</div>
<script>
(function () {
  var jasa = document.getElementById('trx-jasa'), jml = document.getElementById('trx-jumlah'),
      nom = document.getElementById('trx-nominal'), out = document.getElementById('trx-total');
  function hitung() {
    var opt = jasa.options[jasa.selectedIndex], tarif = parseFloat(opt ? opt.getAttribute('data-tarif') : 0) || 0;
    var total = nom.value !== '' ? (parseFloat(nom.value) || 0) : tarif * (parseInt(jml.value, 10) || 0);
    out.textContent = 'Rp ' + Math.round(total).toLocaleString('id-ID');
  }
  [jasa, jml, nom].forEach(function (el) { el.addEventListener('input', hitung); el.addEventListener('change', hitung); });
  hitung();
})();
</script>
<?php endif; ?>

<div class="panel">
  <form class="toolbar" method="get">
    <input name="cari" value="<?= e($cari) ?>" placeholder="Cari klien / deskripsi (Linear Search)…">
    <select name="klien">
      <option value="">Semua klien</option>
      <?php foreach ($klienList as $k): ?>
        <option value="<?= (int) $k['id_klien'] ?>" <?= (int) $k['id_klien'] === $fKlien ? 'selected' : '' ?>><?= e($k['nama_klien']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">Semua status</option>
      <option value="proses" <?= $fStatus === 'proses' ? 'selected' : '' ?>>Proses</option>
      <option value="selesai" <?= $fStatus === 'selesai' ? 'selected' : '' ?>>Selesai</option>
    </select>
    <?php if ($urut !== ''): ?><input type="hidden" name="urut" value="<?= e($urut) ?>"><?php endif; ?>
    <button class="btn secondary" type="submit">Terapkan</button>
    <a class="btn purple" href="<?= e($qs(['urut' => 'tanggal'])) ?>">Urutkan Tanggal (Bubble Sort)</a>
    <a class="btn purple" href="<?= e($qs(['urut' => 'total'])) ?>">Urutkan Total Terbesar (Bubble Sort)</a>
    <a class="btn" href="transaksi.php?tambah=1">+ Tambah Transaksi</a>
    <?php if ($urut !== '' || $cari !== '' || $fKlien || $fStatus !== ''): ?><a class="btn secondary" href="transaksi.php">Reset</a><?php endif; ?>
  </form>
  <table>
    <thead><tr><th>ID</th><th>Tanggal</th><th>Klien</th><th>Deskripsi</th><th class="num">Jumlah</th><th class="num">Total</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($data as $t): $kunci = $t['id_invoice'] !== null; ?>
      <tr>
        <td><?= (int) $t['id_transaksi'] ?></td>
        <td><?= e(tanggal($t['tgl_transaksi'])) ?></td>
        <td><b><?= e($t['nama_klien']) ?></b></td>
        <td><?= e($t['deskripsi']) ?><?php if ($t['no_penawaran']): ?><br><span class="hint">dari <?= e($t['no_penawaran']) ?></span><?php endif; ?></td>
        <td class="num"><?= (int) $t['jumlah'] ?></td>
        <td class="num"><?= rupiah($t['total']) ?></td>
        <td>
          <span class="badge <?= $t['status'] === 'proses' ? 'pending' : '' ?>"><?= $t['status'] === 'proses' ? 'Proses' : 'Selesai' ?></span>
          <?php if ($kunci): ?><span class="badge info">Ditagih</span><?php endif; ?>
        </td>
        <td>
          <?php if ($kunci): ?>
            <span class="hint">Terkunci (sudah di invoice)</span>
          <?php else: ?>
            <?php if ($t['status'] === 'proses'): ?>
              <form class="inline" method="post" onsubmit="return confirm('Tandai transaksi selesai?')">
                <?= input_csrf() ?><input type="hidden" name="aksi" value="selesai"><input type="hidden" name="id_transaksi" value="<?= (int) $t['id_transaksi'] ?>">
                <button class="btn green sm" type="submit">Selesai</button>
              </form>
            <?php endif; ?>
            <a class="btn secondary sm" href="transaksi.php?edit=<?= (int) $t['id_transaksi'] ?>">Edit</a>
            <form class="inline" method="post" onsubmit="return confirm('Hapus transaksi?')">
              <?= input_csrf() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_transaksi" value="<?= (int) $t['id_transaksi'] ?>">
              <button class="btn red sm" type="submit">Hapus</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$data): ?><tr><td colspan="8" class="empty">Data tidak ditemukan</td></tr><?php endif; ?>
    </tbody>
    <?php if ($data): ?>
    <tfoot><tr><td colspan="5" class="num"><b>Total (<?= count($data) ?> transaksi)</b></td><td class="num"><b><?= rupiah($total_tampil) ?></b></td><td colspan="2"></td></tr></tfoot>
    <?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/inc/footer.php';
