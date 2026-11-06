<?php
// Fungsi bersama: akses Supabase + perhitungan persediaan.
requi[REDACTED] __DIR__ . '/config.php';

/** Kirim JSON lalu hentikan skrip. */
function json_out($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Panggil REST API Supabase (PostgREST).
 * Mengembalikan array: ok, code, data, error.
 */
function supabase($method, $path, $body = null)
{
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($path, '/');

    $headers = array('apikey: ' . SUPABASE_KEY, 'Accept: application/json');
    // Key lama (JWT, diawali "eyJ") juga harus dikirim lewat Authorization.
    // Key baru (sb_secret_...) HANYA boleh lewat header apikey.
    if (strpos(SUPABASE_KEY, 'eyJ') === 0) {
        $headers[] = 'Authorization: Bearer ' . SUPABASE_KEY;
    }
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Prefer: return=minimal';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
    ));
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) {
        return array('ok' => false, 'code' => 0, 'data' => null,
                     'error' => 'Koneksi ke Supabase gagal: ' . $err);
    }
    $ok = ($code >= 200 && $code < 300);
    return array('ok' => $ok, 'code' => $code,
                 'data' => json_decode($res, true),
                 'error' => $ok ? null : $res);
}

/** Simpan satu data berat (kg) ke tabel timbangan. */
function simpan_berat($berat_kg, $sumber)
{
    return supabase('POST', 'timbangan', array(
        'berat_kg' => round($berat_kg, 3),
        'sumber'   => $sumber,
    ));
}

/** Validasi angka berat. Mengembalikan float (kg) atau null jika tidak valid. */
function validasi_berat($v, $maks = 100.0)
{
    if ($v === null || is_array($v)) return null;
    $v = str_replace(',', '.', trim((string) $v));
    if ($v === '' || !is_numeric($v)) return null;
    $v = (float) $v;
    if ($v < 0) $v = 0.0;           // timbangan kadang membaca sedikit negatif
    if ($v > $maks) return null;
    return $v;
}

/** Status persediaan: AMAN / MENIPIS / KRITIS. */
function status_stok($kg)
{
    if ($kg <= SAFETY_STOCK_KG) {
        return array('kode' => 'kritis', 'label' => 'KRITIS',
            'pesan'  => '✖ Persediaan berada di bawah atau sama dengan Safety Stock.',
            'detail' => 'Segera lakukan pengadaan!');
    }
    if ($kg <= ROP_KG) {
        return array('kode' => 'menipis', 'label' => 'MENIPIS',
            'pesan'  => '⚠ Persediaan sudah mencapai Reorder Point.',
            'detail' => 'Saatnya melakukan pemesanan ulang.');
    }
    return array('kode' => 'aman', 'label' => 'AMAN',
        'pesan'  => '✓ Persediaan berada di atas ROP.',
        'detail' => 'Stok masih dalam kondisi aman.');
}

/**
 * Perkiraan pemakaian per hari (kg) dari rata-rata per jam.
 * $rows: array urut naik dengan kunci 'jam' dan 'berat_kg'.
 * Rata-rata per jam sudah menghaluskan noise sensor; selisih <= 2 g per jam diabaikan.
 * Kenaikan (isi ulang stok) tidak dihitung sebagai pemakaian.
 */
function pemakaian_per_hari($rows)
{
    $n = count($rows);
    if ($n < 2) return PEMAKAIAN_DEFAULT_KG_HARI;

    $turun = 0.0;
    for ($i = 1; $i < $n; $i++) {
        $d = (float) $rows[$i - 1]['berat_kg'] - (float) $rows[$i]['berat_kg'];
        if ($d > 0.002) $turun += $d;
    }
    $jam  = (strtotime($rows[$n - 1]['jam']) - strtotime($rows[0]['jam'])) / 3600.0 + 1;
    $hari = max(1.0, $jam / 24.0);

    return $turun > 0 ? $turun / $hari : PEMAKAIAN_DEFAULT_KG_HARI;
}

/** Hitung semua angka yang tampil di dashboard. */
function hitung($kg, $pemakaian)
{
    $sisa      = max(0.0, $kg - SAFETY_STOCK_KG);
    $diAtasRop = $kg > ROP_KG;
    $kebutuhan = max(0.0, KAPASITAS_KG - $kg);
    if ($kg <= SAFETY_STOCK_KG)      $hari = 0.0;
    elseif ($pemakaian > 0)          $hari = $sisa / $pemakaian;
    else                             $hari = null;

    return array(
        'sisa_di_atas_safety' => round($sisa, 2),
        'di_atas_rop'         => $diAtasRop,
        'selisih_ke_rop'      => round($diAtasRop ? $kg - ROP_KG : 0.0, 2),
        'kebutuhan_pengadaan' => round($kebutuhan, 2),
        'estimasi_hari'       => $hari === null ? null : round($hari, 1),
        'pemakaian_per_hari'  => round($pemakaian, 2),
        'kapasitas_persen'    => (int) min(100, max(0, round($kg / KAPASITAS_KG * 100))),
    );
}
