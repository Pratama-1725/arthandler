<?php
require_once __DIR__ . '/inc/cek_sesi.php';
wajib_login('admin');
$db = db();

// ------------------------------------------------------------ simpan / hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';
    $id = (int) ($_POST['id_klien'] ?? 0);

    if ($aksi === 'simpan') {
        $nama = trim($_POST['nama_klien'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $hp = trim($_POST['no_hp'] ?? '');
        $galat = [];
        if ($nama === '') {
            $galat[] = 'Nama klien wajib diisi';
        } elseif (mb_strlen($nama) > 100) {
            $galat[] = 'Nama klien maksimal 100 karakter';
        }
        if (mb_strlen($alamat) > 255) {
            $galat[] = 'Alamat maksimal 255 karakter';
        }
        if ($hp !== '' && !preg_match('/^\+?[0-9]{8,15}$/', $hp)) {
            $galat[] = 'No. HP hanya angka, 8 sampai 15 digit';
        }
        if ($galat) {
            pesan('error', implode('. ', $galat) . '.');
            arahkan('klien.php' . ($id ? "?edit=$id" : '?tambah=1'));
        }
        if ($id) {
            $db->prepare('UPDATE klien SET nama_klien = ?, alamat = ?, no_hp = ? WHERE id_klien = ?')
               ->execute([$nama, $alamat ?: null, $hp ?: null, $id]);
            pesan('sukses', 'Data klien diperbarui.');
        } else {
            $db->prepare('INSERT INTO klien (nama_klien, alamat, no_hp) VALUES (?, ?, ?)')
               ->execute([$nama, $alamat ?: null, $hp ?: null]);
            pesan('sukses', 'Klien baru disimpan.');
        }
    } elseif ($aksi === 'hapus' && $id) {
        $pakai = $db->prepare(
            'SELECT (SELECT COUNT(*) FROM transaksi WHERE id_klien = ?) + (SELECT COUNT(*) FROM penawaran WHERE id_klien = ?)
                  + (SELECT COUNT(*) FROM invoice WHERE id_klien = ?)'
        );
        $pakai->execute([$id, $id, $id]);
        if ($pakai->fetchColumn() > 0) {
            pesan('error', 'Klien tidak dapat dihapus karena sudah memiliki transaksi, penawaran, atau invoice.');
        } else {
            $db->prepare('DELETE FROM klien WHERE id_klien = ?')->execute([$id]);
            pesan('sukses', 'Klien dihapus.');
        }
    }
    arahkan('klien.php');
}

// ------------------------------------------------------------ tampil
$data = $db->query('SELECT * FROM klien ORDER BY id_klien')->fetchAll();
$urut = ($_GET['urut'] ?? '') === 'nama';
$cari = trim($_GET['cari'] ?? '');
if ($urut) {
    $data = bubble_sort($data, fn($a, $b) => strcasecmp($a['nama_klien'], $b['nama_klien']));
}
if ($cari !== '') {
    $data = linear_search($data, 'nama_klien', $cari);
}

$form = null;
if (isset($_GET['edit'])) {
    $st = $db->prepare('SELECT * FROM klien WHERE id_klien = ?');
    $st->execute([(int) $_GET['edit']]);
    $form = $st->fetch() ?: null;
} elseif (isset($_GET['tambah'])) {
    $form = ['id_klien' => 0, 'nama_klien' => '', 'alamat' => '', 'no_hp' => ''];
}

$judul = 'Data Klien';
$aktif = 'klien';
require __DIR__ . '/inc/header.php';
?>
<h1>Data Klien</h1>
<p class="subtitle">Kelola data klien, cari dengan Linear Search, dan urutkan nama dengan Bubble Sort.</p>

<?php if ($form): ?>
<div class="panel">
  <div class="panel-title"><h2><?= $form['id_klien'] ? 'Edit' : 'Tambah' ?> Klien</h2></div>
  <form method="post">
    <?= input_csrf() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id_klien" value="<?= (int) $form['id_klien'] ?>">
    <div class="formgrid">
      <div class="field"><label>Nama Klien *</label><input name="nama_klien" value="<?= e($form['nama_klien']) ?>" maxlength="100" required></div>
      <div class="field"><label>Alamat</label><input name="alamat" value="<?= e($form['alamat']) ?>" maxlength="255"></div>
      <div class="field"><label>No. HP</label><input name="no_hp" value="<?= e($form['no_hp']) ?>" maxlength="16" placeholder="08xxxxxxxxxx"></div>
    </div>
    <div class="actions" style="margin-top:15px">
      <button class="btn" type="submit">Simpan</button>
      <a class="btn secondary" href="klien.php">Batal</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <form class="toolbar" method="get">
    <input name="cari" value="<?= e($cari) ?>" placeholder="Cari klien (Linear Search)…">
    <?php if ($urut): ?><input type="hidden" name="urut" value="nama"><?php endif; ?>
    <button class="btn secondary" type="submit">Cari</button>
    <a class="btn purple" href="klien.php?urut=nama<?= $cari !== '' ? '&cari=' . urlencode($cari) : '' ?>">Urutkan A–Z (Bubble Sort)</a>
    <a class="btn" href="klien.php?tambah=1">+ Tambah Klien</a>
    <?php if ($urut || $cari !== ''): ?><a class="btn secondary" href="klien.php">Reset</a><?php endif; ?>
  </form>
  <table>
    <thead><tr><th>ID</th><th>Nama Klien</th><th>Alamat</th><th>No. HP</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($data as $k): ?>
      <tr>
        <td><?= (int) $k['id_klien'] ?></td><td><b><?= e($k['nama_klien']) ?></b></td>
        <td><?= e($k['alamat']) ?></td><td><?= e($k['no_hp']) ?></td>
        <td>
          <a class="btn secondary sm" href="klien.php?edit=<?= (int) $k['id_klien'] ?>">Edit</a>
          <form class="inline" method="post" onsubmit="return confirm('Hapus data klien?')">
            <?= input_csrf() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_klien" value="<?= (int) $k['id_klien'] ?>">
            <button class="btn red sm" type="submit">Hapus</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$data): ?><tr><td colspan="5" class="empty">Data tidak ditemukan</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/inc/footer.php';
