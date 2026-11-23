<?php
require_once __DIR__ . '/inc/cek_sesi.php';

if (pengguna()) {
    arahkan('dashboard.php');
}

$galat = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $st = db()->prepare('SELECT * FROM pengguna WHERE username = ?');
    $st->execute([$username]);
    $u = $st->fetch();

    if (!$u || !password_verify($password, $u['password'])) {
        $galat = 'Username atau password salah.';
    } elseif (!$u['aktif']) {
        $galat = 'Akun tidak aktif. Hubungi pemilik.';
    } else {
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
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login">
<form class="login-box" method="post" autocomplete="off">
  <div class="brand">Art<span>Handler</span></div>
  <p>Sistem Informasi Manajemen Order dan Invoice</p>
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
