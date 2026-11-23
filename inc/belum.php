<?php
// Halaman sementara untuk modul yang dikerjakan di sesi berikutnya. Variabel: $judul, $aktif, $sesi.
require __DIR__ . '/header.php';
?>
<h1><?= e($judul) ?></h1>
<div class="panel"><div class="empty">Modul ini dikerjakan pada sesi <?= (int) $sesi ?>.</div></div>
<?php require __DIR__ . '/footer.php';
