<?php
require_once __DIR__ . '/inc/cek_sesi.php';
wajib_login('admin');
$db = db();

// ------------------------------------------------------------ simpan / hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';
    $id = (int) ($_POST['id_jasa'] ?? 0);

    if ($aksi === 'simpan') {
        $nama = trim($_POST['nama_jasa'] ?? '');
        $ket = trim($_POST['keterangan'] ?? '');
        $tarif = $_POST['tarif'] ?? '';
        $galat = [];
        if ($nama === '') {
            $galat[] = 'Nama layanan wajib diisi';
        } elseif (mb_strlen($nama) > 100) {
            $galat[] = 'Nama layanan maksimal 100 karakter';
        }
        if (mb_strlen($ket) > 255) {
            $galat[] = 'Keterangan maksimal 255 karakter';
        }
        if (!is_numeric($tarif) || (float) $tarif <= 0) {
            $galat[] = 'Tarif harus angka lebih dari 0';
        }
        if ($galat) {
            pesan('error', implode('. ', $galat) . '.');
            arahkan('jasa.php' . ($id ? "?edit=$id" : '?tambah=1'));
        }
        if ($id) {
            $db->prepare('UPDATE jenis_jasa SET nama_jasa = ?, keterangan = ?, tarif = ? WHERE id_jasa = ?')
               ->execute([$nama, $ket ?: null, $tarif, $id]);
            pesan('sukses', 'Jenis jasa diperbarui.');
        } else {
            $db->prepare('INSERT INTO jenis_jasa (nama_jasa, keterangan, tarif) VALUES (?, ?, ?)')
               ->execute([$nama, $ket ?: null, $tarif]);
            pesan('sukses', 'Jenis jasa baru disimpan.');
        }
    } elseif ($aksi === 'hapus' && $id) {
        $pakai = $db->prepare('SELECT COUNT(*) FROM transaksi WHERE id_jasa = ?');
        $pakai->execute([$id]);
        if ($pakai->fetchColumn() > 0) {
            pesan('error', 'Jenis jasa tidak dapat dihapus karena sudah dipakai di transaksi.');
        } else {
            $db->prepare('DELETE FROM jenis_jasa WHERE id_jasa = ?')->execute([$id]);
            pesan('sukses', 'Jenis jasa dihapus.');
        }
    }
    arahkan('jasa.php');
}

// ------------------------------------------------------------ tampil
$data = $db->query('SELECT * FROM jenis_jasa ORDER BY id_jasa')->fetchAll();
$urut = ($_GET['urut'] ?? '') === 'tarif';
if ($urut) {
    $data = bubble_sort($data, fn($a, $b) => $a['tarif'] <=> $b['tarif']);
}

$form = null;
if (isset($_GET['edit'])) {
    $st = $db->prepare('SELECT * FROM jenis_jasa WHERE id_jasa = ?');
    $st->execute([(int) $_GET['edit']]);
    $form = $st->fetch() ?: null;
} elseif (isset($_GET['tambah'])) {
    $form = ['id_jasa' => 0, 'nama_jasa' => '', 'keterangan' => '', 'tarif' => ''];
}

$judul = 'Jenis Jasa';
$aktif = 'jasa';
require __DIR__ . '/inc/header.php';
?>
<h1>Jenis Jasa &amp; Variasi Tarif</h1>
<p class="subtitle">Kelola layanan dan tarif; tarif otomatis dipakai pada transaksi. Variasi tarif (Standar, Premium, Paket) ditulis di nama layanan.</p>

<?php if ($form): ?>
<div class="panel">
  <div class="panel-title"><h2><?= $form['id_jasa'] ? 'Edit' : 'Tambah' ?> Jenis Jasa</h2></div>
  <form method="post">
    <?= input_csrf() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id_jasa" value="<?= (int) $form['id_jasa'] ?>">
    <div class="formgrid">
      <div class="field"><label>Nama Layanan / Paket *</label><input name="nama_jasa" value="<?= e($form['nama_jasa']) ?>" maxlength="100" required placeholder="Pasang Lukisan (Premium)"></div>
      <div class="field"><label>Keterangan</label><input name="keterangan" value="<?= e($form['keterangan']) ?>" maxlength="255"></div>
      <div class="field"><label>Tarif (Rp) *</label><input name="tarif" type="number" min="1" step="1" value="<?= $form['tarif'] !== '' ? (int) $form['tarif'] : '' ?>" required></div>
    </div>
    <div class="actions" style="margin-top:15px">
      <button class="btn" type="submit">Simpan</button>
      <a class="btn secondary" href="jasa.php">Batal</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <div class="toolbar">
    <a class="btn purple" href="jasa.php?urut=tarif">Urutkan Tarif (Bubble Sort)</a>
    <a class="btn" href="jasa.php?tambah=1">+ Tambah Jasa / Tarif Baru</a>
    <?php if ($urut): ?><a class="btn secondary" href="jasa.php">Reset</a><?php endif; ?>
  </div>
  <table>
    <thead><tr><th>ID</th><th>Nama Layanan / Paket</th><th>Keterangan</th><th class="num">Tarif</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($data as $j): ?>
      <tr>
        <td><?= (int) $j['id_jasa'] ?></td><td><b><?= e($j['nama_jasa']) ?></b></td><td><?= e($j['keterangan']) ?></td>
        <td class="num"><?= rupiah($j['tarif']) ?></td>
        <td>
          <a class="btn secondary sm" href="jasa.php?edit=<?= (int) $j['id_jasa'] ?>">Edit</a>
          <form class="inline" method="post" onsubmit="return confirm('Hapus jenis jasa?')">
            <?= input_csrf() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_jasa" value="<?= (int) $j['id_jasa'] ?>">
            <button class="btn red sm" type="submit">Hapus</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$data): ?><tr><td colspan="5" class="empty">Belum ada data</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/inc/footer.php';
