-- ArtHandler: struktur basis data + data contoh
-- Impor: phpMyAdmin (Import) atau  mysql -u root db_arthandler < arthandler.sql
-- Di hosting (InfinityFree) buat database lewat panel dulu, lalu impor file ini ke database itu.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS pembayaran, invoice, detail_penawaran, penawaran, transaksi, jenis_jasa, klien, pengguna;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE pengguna (
  id_pengguna  INT AUTO_INCREMENT PRIMARY KEY,
  nama         VARCHAR(100) NOT NULL,
  username     VARCHAR(30)  NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,              -- hash bcrypt
  peran        ENUM('admin','pemilik') NOT NULL DEFAULT 'admin',
  aktif        TINYINT(1) NOT NULL DEFAULT 1,
  dibuat_pada  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE klien (
  id_klien    INT AUTO_INCREMENT PRIMARY KEY,
  nama_klien  VARCHAR(100) NOT NULL,
  alamat      VARCHAR(255) NULL,
  no_hp       VARCHAR(20)  NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE jenis_jasa (
  id_jasa     INT AUTO_INCREMENT PRIMARY KEY,
  nama_jasa   VARCHAR(100) NOT NULL,
  keterangan  VARCHAR(255) NULL,
  tarif       DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE penawaran (
  id_penawaran   INT AUTO_INCREMENT PRIMARY KEY,
  no_penawaran   VARCHAR(20) NOT NULL UNIQUE,      -- PNW/YYYY/MM/NNN
  id_klien       INT NOT NULL,
  id_pengguna    INT NOT NULL,
  tgl_penawaran  DATE NOT NULL,
  status         ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  catatan        VARCHAR(255) NULL,
  CONSTRAINT fk_pnw_klien FOREIGN KEY (id_klien) REFERENCES klien(id_klien),
  CONSTRAINT fk_pnw_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE detail_penawaran (
  id_detail     INT AUTO_INCREMENT PRIMARY KEY,
  id_penawaran  INT NOT NULL,
  deskripsi     VARCHAR(150) NOT NULL,             -- jasa atau material
  jumlah        INT NOT NULL,
  harga_satuan  DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_det_pnw FOREIGN KEY (id_penawaran) REFERENCES penawaran(id_penawaran) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoice (
  id_invoice     INT AUTO_INCREMENT PRIMARY KEY,
  no_invoice     VARCHAR(20) NOT NULL UNIQUE,      -- INV/YYYY/MM/NNN
  id_klien       INT NOT NULL,
  id_pengguna    INT NOT NULL,
  tgl_invoice    DATE NOT NULL,
  jatuh_tempo    DATE NOT NULL,
  periode_awal   DATE NOT NULL,
  periode_akhir  DATE NOT NULL,
  total          DECIMAL(14,2) NOT NULL,
  status         ENUM('belum_bayar','sebagian','lunas') NOT NULL DEFAULT 'belum_bayar',
  CONSTRAINT fk_inv_klien FOREIGN KEY (id_klien) REFERENCES klien(id_klien),
  CONSTRAINT fk_inv_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transaksi (
  id_transaksi   INT AUTO_INCREMENT PRIMARY KEY,
  id_klien       INT NOT NULL,
  id_jasa        INT NULL,                         -- kosong untuk transaksi hasil penawaran
  id_penawaran   INT NULL,                         -- kosong untuk transaksi input langsung
  id_invoice     INT NULL,                         -- kosong selama belum ditagih
  tgl_transaksi  DATE NOT NULL,
  deskripsi      VARCHAR(150) NOT NULL,
  jumlah         INT NOT NULL,
  total          DECIMAL(14,2) NOT NULL,
  status         ENUM('proses','selesai') NOT NULL DEFAULT 'selesai',
  CONSTRAINT fk_trx_klien FOREIGN KEY (id_klien) REFERENCES klien(id_klien),
  CONSTRAINT fk_trx_jasa FOREIGN KEY (id_jasa) REFERENCES jenis_jasa(id_jasa),
  CONSTRAINT fk_trx_pnw FOREIGN KEY (id_penawaran) REFERENCES penawaran(id_penawaran),
  CONSTRAINT fk_trx_inv FOREIGN KEY (id_invoice) REFERENCES invoice(id_invoice)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pembayaran (
  id_pembayaran  INT AUTO_INCREMENT PRIMARY KEY,
  id_invoice     INT NOT NULL,
  id_pengguna    INT NOT NULL,
  tgl_bayar      DATE NOT NULL,
  jumlah         DECIMAL(14,2) NOT NULL,
  metode         ENUM('transfer','tunai') NOT NULL,
  keterangan     VARCHAR(255) NULL,
  CONSTRAINT fk_byr_inv FOREIGN KEY (id_invoice) REFERENCES invoice(id_invoice),
  CONSTRAINT fk_byr_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------- data contoh
-- Akun awal: admin / admin123, pemilik / pemilik123  (ganti setelah instalasi)
INSERT INTO pengguna (nama, username, password, peran) VALUES
('Administrator', 'admin',   '$2y$10$6LY/Jq5zbJ2l441Ecyr9.ObRkS0LcbAXpSu3fTH.wauKLaOz5VFhK', 'admin'),
('Pemilik Usaha', 'pemilik', '$2y$10$H6N5mhC5eNKvUXBl83sRCuGl6lf.mzcXHy2cUxYWrFnJV5Nj8ozB2', 'pemilik');

INSERT INTO klien (nama_klien, alamat, no_hp) VALUES
('Srisasanti Gallery',  'Jl. Palagan KM 8',       '081227001122'),
('SAL Gallery',         'Jl. Kemang Timur 15',    '081380002233'),
('PT Gajah Seni Agung', 'Jl. Kebayoran Baru 88',  '081190003344'),
('Sita Maharani',       'Jl. Raya Bogor No. 10',  '081245678900');

INSERT INTO jenis_jasa (nama_jasa, keterangan, tarif) VALUES
('Pasang Lukisan (Standar)', 'Pemasangan lukisan standar galeri', 250000),
('Pasang Lukisan (Premium)', 'Karya besar / instalasi khusus',    450000),
('Packing & Crating',        'Peti kayu standar karya seni',      750000),
('Transport ArtHandler',     'Pengiriman & mobilisasi karya',     100000),
('Instalasi Pameran',        'Hanging & mounting pameran',        500000),
('Pembongkaran',             'Pembongkaran & penataan ulang',     300000);

INSERT INTO transaksi (id_klien, id_jasa, tgl_transaksi, deskripsi, jumlah, total, status) VALUES
(1, 1, '2026-11-05', 'Pasang Lukisan (Standar)', 2, 500000, 'selesai'),
(1, 3, '2026-11-14', 'Packing & Crating',        1, 750000, 'selesai'),
(1, 4, '2026-11-22', 'Transport ArtHandler',     1, 100000, 'selesai');

INSERT INTO penawaran (no_penawaran, id_klien, id_pengguna, tgl_penawaran, status) VALUES
('PNW/2026/11/001', 1, 1, '2026-11-23', 'menunggu');

INSERT INTO detail_penawaran (id_penawaran, deskripsi, jumlah, harga_satuan) VALUES
(1, 'Instalasi & Penataan Pameran',   3, 500000),
(1, 'Material Crating & Bubble Wrap', 2, 350000),
(1, 'Transport ArtHandler & Mobil',   1, 250000);
