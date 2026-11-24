<?php
require_once __DIR__ . '/inc/cek_sesi.php';

if (pengguna()) {
    arahkan('dashboard.php');
}

const MAKS_GAGAL = 5;     // batas percobaan gagal berturut-turut
const LAMA_KUNCI = 300;   // lama penguncian (detik) = 5 menit

$galat = '';
$username = '';
$sisa_kunci = ($_SESSION['kunci_sampai'] ?? 0) - time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $u = null;
    $berhasil = false;
    if ($sisa_kunci <= 0) {
        $st = db()->prepare('SELECT id_pengguna, nama, password, peran, aktif FROM pengguna WHERE username = ?');
        $st->execute([$username]);
        $u = $st->fetch();
        $berhasil = $u && $u['aktif'] && password_verify($password, $u['password']);
    }

    if ($sisa_kunci > 0) {
        $galat = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ceil($sisa_kunci / 60) . ' menit.';
    } elseif (!$berhasil) {
        // Pesan sama untuk username salah, password salah, maupun akun nonaktif.
        $_SESSION['gagal_login'] = ($_SESSION['gagal_login'] ?? 0) + 1;
        if ($_SESSION['gagal_login'] >= MAKS_GAGAL) {
            $_SESSION['kunci_sampai'] = time() + LAMA_KUNCI;
            $_SESSION['gagal_login'] = 0;
            $galat = 'Terlalu banyak percobaan gagal. Login dikunci 5 menit.';
        } else {
            $galat = 'Username atau password salah.';
        }
    } else {
        unset($_SESSION['gagal_login'], $_SESSION['kunci_sampai']);
        session_regenerate_id(true);
        $_SESSION['pengguna'] = [
            'id'    => (int) $u['id_pengguna'],
            'nama'  => $u['nama'],
            'peran' => $u['peran'],
        ];
        arahkan('dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login · ArtHandler</title>
<link rel="preload" href="assets/fonts/PlayfairDisplay-700.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="assets/fonts/Poppins-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body class="login">
<form class="login-box" method="post" autocomplete="off">
  <div class="brand">Art<span>Handler</span></div>
  <p class="tagline">Handle the art, we handle the work.</p>
  <p>Sistem Informasi Manajemen Order dan Invoice</p>
  <?= tampil_pesan() ?>
  <?php if ($galat): ?><div class="alert error"><?= e($galat) ?></div><?php endif; ?>
  <?= input_csrf() ?>
  <div class="field">
    <label for="username">Username</label>
    <input id="username" name="username" value="<?= e($username) ?>" required maxlength="30" autofocus>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required>
  </div>
  <button class="btn" type="submit">Login</button>
</form>
</body>
</html>
