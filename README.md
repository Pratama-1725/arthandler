# ArtHandler — Aplikasi Web (PHP terstruktur + MySQL)

Dasar: `../source/arthandler_ui_prototype-6.html` (tampilan dan alur) + rancangan Bab IV (`../skripsi/`).

## Menjalankan di Lokal

1. Buat database `db_arthandler`, lalu impor `database/arthandler.sql` (phpMyAdmin → Import).
2. Salin `.env.example` menjadi `.env` bila kredensial berbeda dari bawaan XAMPP (`localhost`, `root`, tanpa password).
3. Taruh folder `app/` di `htdocs`, buka `http://localhost/app/` (atau `php -S 127.0.0.1:8099` dari folder ini).

Akun awal: `admin` / `admin123` (Admin), `pemilik` / `pemilik123` (Pemilik). **Ganti setelah instalasi.**

## Struktur

| Berkas | Isi |
|---|---|
| `inc/koneksi.php` | PDO MySQL, baca `.env` |
| `inc/cek_sesi.php` | sesi, `wajib_login(peran)` |
| `inc/fungsi.php` | `e()`, `rupiah()`, CSRF, pesan, `bubble_sort()`, `linear_search()` |
| `inc/header.php`, `inc/footer.php` | sidebar per peran |
| `login.php`, `logout.php`, `dashboard.php` | |
| `klien.php`, `jasa.php` | master data (urut, cari, validasi, tolak hapus bila dipakai) |
| `transaksi.php`, `penawaran.php`, `invoice.php`, `laporan.php`, `pengguna.php` | sementara "dikerjakan sesi berikutnya" |
| `database/arthandler.sql` | 8 tabel + data contoh prototipe |
| `assets/style.css` | CSS prototipe + login, pesan, cetak |

## Progres

| Sesi | Isi | Status |
|---|---|---|
| 1 | DB, login + peran, layout, Data Klien, Jenis Jasa, dashboard | ✅ 23 Nov 2026 |
| 2 | Transaksi, Penawaran (rincian, edit, setuju/tolak, cetak) | ⬜ |
| 3 | Invoice + nomor + pembayaran, laporan, kelola pengguna | ⬜ |
| 4 | Uji blackbox, deploy InfinityFree, sinkron Bab IV | ⬜ |
