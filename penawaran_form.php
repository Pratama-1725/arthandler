<?php
require_once __DIR__ . '/inc/cek_sesi.php';
$u = wajib_login('admin');
require_once __DIR__ . '/penawaran_fungsi.php';
$db = db();

const PNW_MAKS_BARIS = 50;

$id = (int) ($_POST['id_penawaran'] ?? $_GET['id'] ?? 0);
$lama = null;
if ($id) {
    $lama = pnw_ambil($db, $id);
    if (!$lama) {
        pesan('error', 'Penawaran tidak ditemukan.');
        arahkan('penawaran.php');
    }
    if ($lama['status'] === 'disetujui') {
        pesan('error', 'Penawaran yang sudah disetujui terkunci dan tidak dapat diubah.');
        arahkan("penawaran_detail.php?id=$id");
    }
}

$galat = [];
$form = $lama
    ? ['id_klien' => (int) $lama['id_klien'], 'tgl_penawaran' => $lama['tgl_penawaran'], 'catatan' => (string) $lama['catatan'],
       'baris' => array_map(fn($d) => ['deskripsi' => $d['deskripsi'], 'jumlah' => (string) $d['jumlah'], 'harga' => (string) (float) $d['harga_satuan']], pnw_detail($db, $id))]
    : ['id_klien' => 0, 'tgl_penawaran' => date('Y-m-d'), 'catatan' => '', 'baris' => []];

// ------------------------------------------------------------ simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $form['id_klien'] = (int) ($_POST['id_klien'] ?? 0);
    $form['tgl_penawaran'] = trim($_POST['tgl_penawaran'] ?? '');
    $form['catatan'] = trim($_POST['catatan'] ?? '');
    $des = (array) ($_POST['deskripsi'] ?? []);
    $jml = (array) ($_POST['jumlah'] ?? []);
    $hrg = (array) ($_POST['harga'] ?? []);
    $form['baris'] = [];
    foreach ($des as $i => $d) {
        $b = ['deskripsi' => trim((string) $d), 'jumlah' => trim((string) ($jml[$i] ?? '')), 'harga' => trim((string) ($hrg[$i] ?? ''))];
        if ($b['deskripsi'] === '' && $b['jumlah'] === '' && $b['harga'] === '') {
            continue; // baris kosong diabaikan
        }
        $form['baris'][] = $b;
    }

    $st = $db->prepare('SELECT COUNT(*) FROM klien WHERE id_klien = ?');
    $st->execute([$form['id_klien']]);
    if (!$st->fetchColumn()) {
        $galat[] = 'Klien wajib dipilih dari daftar';
    }
    if (!pnw_tanggal_valid($form['tgl_penawaran'])) {
        $galat[] = 'Tanggal penawaran tidak valid';
    }
    if (mb_strlen($form['catatan']) > 255) {
        $galat[] = 'Catatan maksimal 255 karakter';
    }
    if (!$form['baris']) {
        $galat[] = 'Isi minimal satu baris rincian';
    } elseif (count($form['baris']) > PNW_MAKS_BARIS) {
        $galat[] = 'Rincian maksimal ' . PNW_MAKS_BARIS . ' baris';
    }
    foreach ($form['baris'] as $n => $b) {
        $no = $n + 1;
        if ($b['deskripsi'] === '' || mb_strlen($b['deskripsi']) > 150) {
            $galat[] = "Baris $no: deskripsi wajib diisi, maksimal 150 karakter";
        }
        $j = filter_var($b['jumlah'], FILTER_VALIDATE_INT);
        if ($j === false || $j < 1 || $j > 100000) {
            $galat[] = "Baris $no: jumlah harus bilangan bulat minimal 1";
        }
        if (!is_numeric($b['harga']) || (float) $b['harga'] <= 0 || (float) $b['harga'] > 9999999999) {
            $galat[] = "Baris $no: harga satuan harus angka lebih dari 0";
        }
    }

    if (!$galat) {
        $simpanRinci = function (int $idP) use ($db, $form): void {
            $ins = $db->prepare('INSERT INTO detail_penawaran (id_penawaran, deskripsi, jumlah, harga_satuan) VALUES (?, ?, ?, ?)');
            foreach ($form['baris'] as $b) {
                $ins->execute([$idP, $b['deskripsi'], (int) $b['jumlah'], round((float) $b['harga'], 2)]);
            }
        };
        if ($id) {
            // Revisi: status kembali menunggu (diajukan ulang); nomor penawaran tetap.
            $db->beginTransaction();
            try {
                $st = $db->prepare('SELECT status FROM penawaran WHERE id_penawaran = ? FOR UPDATE');
                $st->execute([$id]);
                if ($st->fetchColumn() === 'disetujui') {
                    $db->rollBack();
                    pesan('error', 'Penawaran yang sudah disetujui terkunci dan tidak dapat diubah.');
                    arahkan("penawaran_detail.php?id=$id");
                }
                $db->prepare("UPDATE penawaran SET id_klien = ?, tgl_penawaran = ?, catatan = ?, status = 'menunggu' WHERE id_penawaran = ?")
                   ->execute([$form['id_klien'], $form['tgl_penawaran'], $form['catatan'] ?: null, $id]);
                $db->prepare('DELETE FROM detail_penawaran WHERE id_penawaran = ?')->execute([$id]);
                $simpanRinci($id);
                $db->commit();
            } catch (Throwable $ex) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $ex;
            }
            pesan('sukses', 'Penawaran ' . $lama['no_penawaran'] . ' disimpan dengan status menunggu.');
        } else {
            // Nomor dihitung di dalam transaksi DB (MAX ... FOR UPDATE); ulangi bila bentrok UNIQUE.
            for ($coba = 1; ; $coba++) {
                $db->beginTransaction();
                try {
                    $no = pnw_nomor_baru($db, $form['tgl_penawaran']);
                    $db->prepare("INSERT INTO penawaran (no_penawaran, id_klien, id_pengguna, tgl_penawaran, status, catatan) VALUES (?, ?, ?, ?, 'menunggu', ?)")
                       ->execute([$no, $form['id_klien'], $u['id'], $form['tgl_penawaran'], $form['catatan'] ?: null]);
                    $id = (int) $db->lastInsertId();
                    $simpanRinci($id);
                    $db->commit();
                    break;
                } catch (PDOException $ex) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    if ($coba >= 3 || (int) ($ex->errorInfo[1] ?? 0) !== 1062) {
                        throw $ex;
                    }
                }
            }
            pesan('sukses', "Penawaran $no disimpan dengan status menunggu.");
        }
        arahkan("penawaran_detail.php?id=$id");
    }
}

if (!$form['baris']) {
    $form['baris'][] = ['deskripsi' => '', 'jumlah' => '1', 'harga' => ''];
}
$klienList = $db->query('SELECT id_klien, nama_klien FROM klien ORDER BY nama_klien')->fetchAll();

$judul = $id ? 'Edit Penawaran' : 'Buat Penawaran';
$aktif = 'penawaran';
require __DIR__ . '/inc/header.php';
?>
<style>
  .rinci td{vertical-align:middle}.rinci input{width:100%}.rinci .w-jml{width:90px}.rinci .w-hrg{width:150px}
</style>
<h1><?= $id ? 'Edit Penawaran ' . e($lama['no_penawaran']) : 'Buat Penawaran Harga' ?></h1>
<p class="subtitle">
  <?php if ($id && $lama['status'] === 'ditolak'): ?>Penawaran ini ditolak; simpan revisi untuk mengajukannya kembali (status menjadi menunggu).
  <?php else: ?>Subtotal = jumlah × harga satuan; total = jumlah semua subtotal. Nomor PNW dibuat otomatis saat disimpan.<?php endif; ?>
</p>

<?php if ($galat): ?><div class="alert error"><?= e(implode('. ', $galat) . '.') ?></div><?php endif; ?>

<form method="post" class="panel" id="form-pnw">
  <?= input_csrf() ?>
  <input type="hidden" name="id_penawaran" value="<?= (int) $id ?>">
  <div class="formgrid">
    <div class="field"><label>Klien *</label>
      <select name="id_klien" required>
        <option value="">— pilih klien —</option>
        <?php foreach ($klienList as $k): ?>
          <option value="<?= (int) $k['id_klien'] ?>" <?= (int) $k['id_klien'] === $form['id_klien'] ? 'selected' : '' ?>><?= e($k['nama_klien']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Tanggal Penawaran *</label><input type="date" name="tgl_penawaran" value="<?= e($form['tgl_penawaran']) ?>" required></div>
    <div class="field"><label>Catatan</label><input name="catatan" value="<?= e($form['catatan']) ?>" maxlength="255"></div>
  </div>

  <div class="panel-title" style="margin-top:20px"><h2>Rincian Jasa / Material</h2>
    <button type="button" class="btn purple sm" id="tambah-baris">+ Tambah Rincian</button></div>
  <table class="rinci">
    <thead><tr><th>Deskripsi *</th><th class="num">Jumlah *</th><th class="num">Harga Satuan (Rp) *</th><th class="num">Subtotal</th><th></th></tr></thead>
    <tbody id="baris-rinci">
    <?php foreach ($form['baris'] as $b): ?>
      <tr>
        <td><input name="deskripsi[]" value="<?= e($b['deskripsi']) ?>" maxlength="150" placeholder="Jasa atau material"></td>
        <td class="w-jml"><input name="jumlah[]" type="number" min="1" step="1" value="<?= e($b['jumlah']) ?>"></td>
        <td class="w-hrg"><input name="harga[]" type="number" min="1" step="any" value="<?= e($b['harga']) ?>"></td>
        <td class="num subtotal">Rp 0</td>
        <td><button type="button" class="btn red sm hapus-baris" title="Hapus baris">×</button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="totalbox" style="margin-top:15px">Total Penawaran: <span id="pnw-total">Rp 0</span></div>
  <div class="actions" style="margin-top:15px">
    <button class="btn" type="submit"><?= $id && $lama['status'] === 'ditolak' ? 'Simpan &amp; Ajukan Ulang' : 'Simpan' ?></button>
    <a class="btn secondary" href="<?= $id ? 'penawaran_detail.php?id=' . (int) $id : 'penawaran.php' ?>">Batal</a>
  </div>
</form>
<script>
(function () {
  var body = document.getElementById('baris-rinci'), totalEl = document.getElementById('pnw-total');
  function rp(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }
  function hitung() {
    var total = 0;
    Array.prototype.forEach.call(body.rows, function (tr) {
      var j = parseInt(tr.querySelector('[name="jumlah[]"]').value, 10) || 0,
          h = parseFloat(tr.querySelector('[name="harga[]"]').value) || 0;
      tr.querySelector('.subtotal').textContent = rp(j * h);
      total += j * h;
    });
    totalEl.textContent = rp(total);
  }
  document.getElementById('tambah-baris').addEventListener('click', function () {
    var baru = body.rows[0].cloneNode(true);
    baru.querySelectorAll('input').forEach(function (el) { el.value = el.name === 'jumlah[]' ? '1' : ''; });
    body.appendChild(baru);
    baru.querySelector('input').focus();
    hitung();
  });
  body.addEventListener('click', function (ev) {
    if (!ev.target.classList.contains('hapus-baris')) return;
    var tr = ev.target.closest('tr');
    if (body.rows.length > 1) { tr.remove(); } else { tr.querySelectorAll('input').forEach(function (el) { el.value = ''; }); }
    hitung();
  });
  body.addEventListener('input', hitung);
  hitung();
})();
</script>
<?php require __DIR__ . '/inc/footer.php';
