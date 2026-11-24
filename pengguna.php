<?php
// Kelola pengguna (Pemilik): tambah, edit nama/peran, aktif/nonaktif, reset password, hapus.
require_once __DIR__ . '/inc/cek_sesi.php';
$u = wajib_login('pemilik');
$db = db();
$peran_valid = ['admin' => 'Admin', 'pemilik' => 'Pemilik'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';
    $id = (int) ($_POST['id_pengguna'] ?? 0);
    $diri = $id === $u['id'];

    $target = null;
    if ($id) {
        $st = $db->prepare('SELECT * FROM pengguna WHERE id_pengguna = ?');
        $st->execute([$id]);
        $target = $st->fetch() ?: null;
        if (!$target) {
            pesan('error', 'Pengguna tidak ditemukan.');
            arahkan('pengguna.php');
        }
    }

    if ($aksi === 'simpan') {
        $nama = trim($_POST['nama'] ?? '');
        $peran = $_POST['peran'] ?? '';
        $galat = [];
        if ($nama === '') {
            $galat[] = 'Nama wajib diisi';
        } elseif (mb_strlen($nama) > 100) {
            $galat[] = 'Nama maksimal 100 karakter';
        }
        if (!isset($peran_valid[$peran])) {
            $galat[] = 'Peran tidak valid';
        }
        if ($target) {
            if ($diri && $peran !== $target['peran']) {
                $galat[] = 'Anda tidak dapat mengubah peran akun Anda sendiri';
            }
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
                $galat[] = 'Username 3 sampai 30 karakter (huruf, angka, titik, garis bawah)';
            } else {
                $st = $db->prepare('SELECT COUNT(*) FROM pengguna WHERE username = ?');
                $st->execute([$username]);
                if ($st->fetchColumn() > 0) {
                    $galat[] = 'Username sudah dipakai';
                }
            }
            if (strlen($password) < 6) {
                $galat[] = 'Password minimal 6 karakter';
            }
        }
        if ($galat) {
            pesan('error', implode('. ', $galat) . '.');
            arahkan('pengguna.php' . ($target ? "?edit=$id" : '?tambah=1'));
        }
        if ($target) {
            $db->prepare('UPDATE pengguna SET nama = ?, peran = ? WHERE id_pengguna = ?')->execute([$nama, $peran, $id]);
            if ($diri) {
                $_SESSION['pengguna']['nama'] = $nama;
            }
            pesan('sukses', 'Data pengguna diperbarui.');
        } else {
            try {
                $db->prepare('INSERT INTO pengguna (nama, username, password, peran) VALUES (?, ?, ?, ?)')
                   ->execute([$nama, $username, password_hash($password, PASSWORD_DEFAULT), $peran]);
            } catch (PDOException $ex) {
                pesan('error', 'Username sudah dipakai.');
                arahkan('pengguna.php?tambah=1');
            }
            pesan('sukses', 'Pengguna baru disimpan.');
        }
    } elseif ($aksi === 'aktif' && $target) {
        if ($diri) {
            pesan('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        } else {
            $baru = $target['aktif'] ? 0 : 1;
            $db->prepare('UPDATE pengguna SET aktif = ? WHERE id_pengguna = ?')->execute([$baru, $id]);
            pesan('sukses', 'Akun ' . $target['username'] . ($baru ? ' diaktifkan.' : ' dinonaktifkan.'));
        }
    } elseif ($aksi === 'reset' && $target) {
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) {
            pesan('error', 'Password baru minimal 6 karakter.');
            arahkan("pengguna.php?reset=$id");
        }
        $db->prepare('UPDATE pengguna SET password = ? WHERE id_pengguna = ?')
           ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        pesan('sukses', 'Password ' . $target['username'] . ' berhasil diatur ulang.');
    } elseif ($aksi === 'hapus' && $target) {
        if ($diri) {
            pesan('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        } else {
            $st = $db->prepare(
                'SELECT (SELECT COUNT(*) FROM penawaran WHERE id_pengguna = ?) + (SELECT COUNT(*) FROM invoice WHERE id_pengguna = ?)
                      + (SELECT COUNT(*) FROM pembayaran WHERE id_pengguna = ?)'
            );
            $st->execute([$id, $id, $id]);
            if ($st->fetchColumn() > 0) {
                pesan('error', 'Pengguna tidak dapat dihapus karena sudah tercatat di penawaran, invoice, atau pembayaran. Nonaktifkan saja akunnya.');
            } else {
                $db->prepare('DELETE FROM pengguna WHERE id_pengguna = ?')->execute([$id]);
                pesan('sukses', 'Pengguna ' . $target['username'] . ' dihapus.');
            }
        }
    }
    arahkan('pengguna.php');
}

// ------------------------------------------------------------ tampil
$data = $db->query('SELECT id_pengguna, nama, username, peran, aktif, dibuat_pada FROM pengguna ORDER BY id_pengguna')->fetchAll();
$cari = trim($_GET['cari'] ?? '');
if ($cari !== '') {
    $data = linear_search($data, 'username', $cari);
}

$form = null;
$reset = null;
if (isset($_GET['edit'])) {
    $st = $db->prepare('SELECT id_pengguna, nama, username, peran FROM pengguna WHERE id_pengguna = ?');
    $st->execute([(int) $_GET['edit']]);
    $form = $st->fetch() ?: null;
} elseif (isset($_GET['tambah'])) {
    $form = ['id_pengguna' => 0, 'nama' => '', 'username' => '', 'peran' => 'admin'];
} elseif (isset($_GET['reset'])) {
    $st = $db->prepare('SELECT id_pengguna, nama, username FROM pengguna WHERE id_pengguna = ?');
    $st->execute([(int) $_GET['reset']]);
    $reset = $st->fetch() ?: null;
}

$judul = 'Kelola Pengguna';
$aktif = 'pengguna';
require __DIR__ . '/inc/header.php';
?>
<h1>Kelola Pengguna</h1>
<p class="subtitle">Tambah akun, ubah peran, aktif/nonaktifkan akun, dan atur ulang password.</p>

<?php if ($form): $diri = (int) $form['id_pengguna'] === $u['id']; ?>
<div class="panel">
  <div class="panel-title"><h2><?= $form['id_pengguna'] ? 'Edit' : 'Tambah' ?> Pengguna</h2></div>
  <form method="post" autocomplete="off">
    <?= input_csrf() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id_pengguna" value="<?= (int) $form['id_pengguna'] ?>">
    <div class="formgrid">
      <div class="field"><label>Nama *</label><input name="nama" value="<?= e($form['nama']) ?>" maxlength="100" required></div>
      <?php if ($form['id_pengguna']): ?>
        <div class="field"><label>Username</label><input value="<?= e($form['username']) ?>" disabled></div>
      <?php else: ?>
        <div class="field"><label>Username *</label><input name="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_.]{3,30}" required></div>
        <div class="field"><label>Password * (min. 6 karakter)</label><input name="password" type="password" minlength="6" required></div>
      <?php endif; ?>
      <div class="field"><label>Peran *</label>
        <?php if ($diri): ?>
          <input type="hidden" name="peran" value="<?= e($form['peran']) ?>">
          <input value="<?= e($peran_valid[$form['peran']]) ?>" disabled><span class="hint">Peran akun sendiri tidak dapat diubah.</span>
        <?php else: ?>
          <select name="peran">
            <?php foreach ($peran_valid as $kode => $lbl): ?>
              <option value="<?= $kode ?>" <?= $form['peran'] === $kode ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>
    </div>
    <div class="actions" style="margin-top:15px">
      <button class="btn" type="submit">Simpan</button>
      <a class="btn secondary" href="pengguna.php">Batal</a>
    </div>
  </form>
</div>
<?php elseif ($reset): ?>
<div class="panel">
  <div class="panel-title"><h2>Reset Password: <?= e($reset['username']) ?></h2></div>
  <form method="post" autocomplete="off">
    <?= input_csrf() ?>
    <input type="hidden" name="aksi" value="reset">
    <input type="hidden" name="id_pengguna" value="<?= (int) $reset['id_pengguna'] ?>">
    <div class="formgrid">
      <div class="field"><label>Password Baru * (min. 6 karakter)</label><input name="password" type="password" minlength="6" required></div>
    </div>
    <div class="actions" style="margin-top:15px">
      <button class="btn orange" type="submit">Simpan Password</button>
      <a class="btn secondary" href="pengguna.php">Batal</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <form class="toolbar" method="get">
    <input name="cari" value="<?= e($cari) ?>" placeholder="Cari username (Linear Search)…">
    <button class="btn secondary" type="submit">Cari</button>
    <a class="btn" href="pengguna.php?tambah=1">+ Tambah Pengguna</a>
    <?php if ($cari !== ''): ?><a class="btn secondary" href="pengguna.php">Reset</a><?php endif; ?>
  </form>
  <table>
    <thead><tr><th>ID</th><th>Nama</th><th>Username</th><th>Peran</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($data as $p): $diri = (int) $p['id_pengguna'] === $u['id']; ?>
      <tr>
        <td><?= (int) $p['id_pengguna'] ?></td>
        <td><b><?= e($p['nama']) ?></b><?= $diri ? ' <span class="hint">(Anda)</span>' : '' ?></td>
        <td><?= e($p['username']) ?></td>
        <td><span class="badge info"><?= e($peran_valid[$p['peran']]) ?></span></td>
        <td><span class="badge <?= $p['aktif'] ? '' : 'danger' ?>"><?= $p['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
        <td>
          <a class="btn secondary sm" href="pengguna.php?edit=<?= (int) $p['id_pengguna'] ?>">Edit</a>
          <a class="btn orange sm" href="pengguna.php?reset=<?= (int) $p['id_pengguna'] ?>">Reset Password</a>
          <?php if (!$diri): ?>
            <form class="inline" method="post">
              <?= input_csrf() ?><input type="hidden" name="aksi" value="aktif"><input type="hidden" name="id_pengguna" value="<?= (int) $p['id_pengguna'] ?>">
              <button class="btn <?= $p['aktif'] ? 'purple' : 'green' ?> sm" type="submit"><?= $p['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
            </form>
            <form class="inline" method="post" onsubmit="return confirm('Hapus pengguna ini?')">
              <?= input_csrf() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_pengguna" value="<?= (int) $p['id_pengguna'] ?>">
              <button class="btn red sm" type="submit">Hapus</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$data): ?><tr><td colspan="6" class="empty">Data tidak ditemukan</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/inc/footer.php';
