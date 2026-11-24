<?php
// Fungsi bantu modul Penawaran Harga. Dimuat oleh penawaran*.php, tidak dibuka langsung.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

const PNW_STATUS = [
    'menunggu'  => ['Menunggu', 'pending'],
    'disetujui' => ['Disetujui', ''],
    'ditolak'   => ['Ditolak', 'danger'],
];

function pnw_badge(string $status): string
{
    [$label, $kelas] = PNW_STATUS[$status] ?? [$status, 'info'];
    return '<span class="badge ' . e($kelas) . '">' . e($label) . '</span>';
}

/** Tanggal format Y-m-d yang benar-benar ada di kalender. */
function pnw_tanggal_valid(string $ymd): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $ymd);
    return $d !== false && $d->format('Y-m-d') === $ymd;
}

/**
 * Nomor penawaran berikutnya PNW/YYYY/MM/NNN, berurut per bulan tanggal penawaran.
 * Wajib dipanggil di dalam transaksi DB: FOR UPDATE mengunci rentang nomor bulan itu
 * sampai commit, sehingga dua penyimpanan bersamaan tidak mendapat nomor yang sama.
 */
function pnw_nomor_baru(PDO $db, string $tgl): string
{
    $awalan = 'PNW/' . substr($tgl, 0, 4) . '/' . substr($tgl, 5, 2) . '/';
    $st = $db->prepare(
        'SELECT MAX(CAST(SUBSTRING(no_penawaran, ?) AS UNSIGNED)) FROM penawaran WHERE no_penawaran LIKE ? FOR UPDATE'
    );
    $st->execute([strlen($awalan) + 1, $awalan . '%']);
    $urut = (int) $st->fetchColumn() + 1;
    return $awalan . str_pad((string) $urut, 3, '0', STR_PAD_LEFT);
}

/** Header penawaran + nama klien, pembuat, dan total (Σ jumlah × harga satuan). */
function pnw_ambil(PDO $db, int $id): ?array
{
    $st = $db->prepare(
        'SELECT p.*, k.nama_klien, k.alamat, k.no_hp, u.nama AS nama_pembuat,
                (SELECT COALESCE(SUM(d.jumlah * d.harga_satuan), 0) FROM detail_penawaran d WHERE d.id_penawaran = p.id_penawaran) AS total
           FROM penawaran p
           JOIN klien k ON k.id_klien = p.id_klien
           JOIN pengguna u ON u.id_pengguna = p.id_pengguna
          WHERE p.id_penawaran = ?'
    );
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function pnw_detail(PDO $db, int $id): array
{
    $st = $db->prepare('SELECT * FROM detail_penawaran WHERE id_penawaran = ? ORDER BY id_detail');
    $st->execute([$id]);
    return $st->fetchAll();
}
