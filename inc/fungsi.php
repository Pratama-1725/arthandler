<?php
// Fungsi bantu umum: keluaran aman, format rupiah, pesan kilat, CSRF, dan algoritma
// Bubble Sort serta Linear Search (AB-02, AB-03).

function e($teks): string
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function tanggal(string $ymd): string
{
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    [$y, $m, $d] = explode('-', $ymd);
    return (int) $d . ' ' . $bulan[(int) $m] . ' ' . $y;
}

function arahkan(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function pesan(string $jenis, string $isi): void
{
    $_SESSION['pesan'] = ['jenis' => $jenis, 'isi' => $isi];
}

function tampil_pesan(): string
{
    if (empty($_SESSION['pesan'])) {
        return '';
    }
    $p = $_SESSION['pesan'];
    unset($_SESSION['pesan']);
    return '<div class="alert ' . e($p['jenis']) . '">' . e($p['isi']) . '</div>';
}

function token_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function input_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(token_csrf()) . '">';
}

function cek_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Permintaan tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

/**
 * Bubble Sort: bandingkan dua elemen bersebelahan dan tukar bila urutannya salah.
 * Berhenti lebih awal bila satu putaran tidak ada pertukaran.
 * $banding($a, $b) > 0 berarti $a harus berada setelah $b.
 */
function bubble_sort(array $data, callable $banding): array
{
    $n = count($data);
    for ($i = 0; $i < $n - 1; $i++) {
        $tukar = false;
        for ($j = 0; $j < $n - $i - 1; $j++) {
            if ($banding($data[$j], $data[$j + 1]) > 0) {
                [$data[$j], $data[$j + 1]] = [$data[$j + 1], $data[$j]];
                $tukar = true;
            }
        }
        if (!$tukar) {
            break;
        }
    }
    return $data;
}

/**
 * Linear Search: periksa elemen satu per satu; kembalikan semua yang kolomnya memuat kata kunci
 * (tidak membedakan huruf besar dan kecil).
 */
function linear_search(array $data, string $kolom, string $kunci): array
{
    $kunci = mb_strtolower(trim($kunci));
    $hasil = [];
    for ($i = 0; $i < count($data); $i++) {
        if (str_contains(mb_strtolower($data[$i][$kolom]), $kunci)) {
            $hasil[] = $data[$i];
        }
    }
    return $hasil;
}
