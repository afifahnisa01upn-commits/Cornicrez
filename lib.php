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
function supabase($method, $path, $body = null, $timeout = 15)
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
        CURLOPT_CONNECTTIMEOUT => min(6, $timeout),
        CURLOPT_TIMEOUT        => $timeout,
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
    ), 8);
}

/* ================================================================
 *  DATA LIVE (di server) dan PENYIMPANAN PER MENIT (ke Supabase)
 *
 *  Angka yang tampil di dashboard (live) disimpan di file kecil data/state.php,
 *  jadi bisa diperbarui tiap beberapa detik tanpa menyentuh Supabase.
 *  Supabase hanya diisi sekali tiap INTERVAL_SIMPAN_DETIK.
 * ================================================================ */

/** Folder data sementara. Dibuat otomatis. Mengembalikan null bila tidak bisa ditulis. */
function folder_data()
{
    static $dir = false;
    if ($dir !== false) return $dir;

    $kandidat = array(
        __DIR__ . '/data',
        rtrim(sys_get_temp_dir(), '/\\') . '/cornicrez-data',
    );
    foreach ($kandidat as $i => $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
            if ($i === 0 && is_dir($d)) {
                // Larang akses langsung lewat browser (file state.php juga sudah aman sendiri).
                @file_put_contents($d . '/.htaccess',
                    "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
                    "<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
            }
        }
        if (is_dir($d) && is_writable($d)) return $dir = $d;
    }
    return $dir = null;
}

function state_kosong()
{
    return array(
        'live'      => null,   // pembacaan terakhir: berat_kg, sumber, waktu
        'jendela'   => null,   // pembacaan yang sedang dikumpulkan untuk disimpan per menit
        'simpan'    => null,   // penyimpanan Supabase terakhir yang berhasil: waktu, berat_kg
        'gagal'     => null,   // kegagalan simpan terakhir: waktu, pesan
        'ulang'     => 0,      // jangan coba simpan lagi sebelum waktu ini (setelah gagal)
        'pemakaian' => null,   // perkiraan pemakaian per hari (di-cache): nilai, waktu
    );
}

/** Baca state (tanpa lock; file selalu ditulis utuh lewat rename). */
function state_baca()
{
    $dir = folder_data();
    if ($dir === null || !is_file($dir . '/state.php')) return state_kosong();

    for ($i = 0; $i < 2; $i++) {
        $raw = @file_get_contents($dir . '/state.php');
        if ($raw !== false) {
            $nl = strpos($raw, "\n");           // baris pertama = pengaman (php exit), data JSON ada sesudahnya
            $j  = $nl === false ? null : json_decode(substr($raw, $nl + 1), true);
            if (is_array($j)) return array_merge(state_kosong(), $j);
            break;
        }
        usleep(50000);
    }
    return state_kosong();
}

/** Tulis state secara atomik. */
function state_tulis($st)
{
    $dir = folder_data();
    if ($dir === null) return false;
    $f   = $dir . '/state.php';
    $tmp = $f . '.' . getmypid() . '.tmp';
    $ok  = @file_put_contents($tmp, "<?php exit; ?>\n" . json_encode($st)) !== false;
    if ($ok) $ok = @rename($tmp, $f);
    if (!$ok) @unlink($tmp);
    return $ok;
}

/** Hitung ulang perkiraan pemakaian per hari bila cache-nya sudah lebih dari 10 menit. */
function segarkan_pemakaian(&$st, $sekarang)
{
    if (is_array($st['pemakaian']) && $sekarang - $st['pemakaian']['waktu'] < 600) return;

    $sejak = gmdate('Y-m-d\TH:i:s\Z', $sekarang - 7 * 86400);
    $pj = supabase('GET', 'timbangan_per_jam?select=jam,berat_kg&order=jam.asc&limit=200&jam=gte.' . rawurlencode($sejak), null, 6);
    $nilai = ($pj['ok'] && is_array($pj['data'])) ? pemakaian_per_hari($pj['data']) : PEMAKAIAN_DEFAULT_KG_HARI;
    $st['pemakaian'] = array('nilai' => $nilai, 'waktu' => $sekarang);
}

/**
 * Terima satu pembacaan berat.
 *  - Angka live SELALU diperbarui (disimpan di file server, bukan di Supabase).
 *  - ESP32: pembacaan dikumpulkan; rata-ratanya disimpan ke Supabase sekali tiap
 *    INTERVAL_SIMPAN_DETIK. Simulasi: nilai terakhir yang disimpan (supaya grafik contoh tetap bervariasi).
 *  - Manual: dilakukan dengan sengaja oleh pengguna, jadi langsung disimpan.
 * Mengembalikan: ok (bool), tersimpan (bool), error (string|null), peringatan (string|null).
 */
function catat_berat($kg, $sumber)
{
    $dir = folder_data();
    if ($dir === null) {
        return array('ok' => false, 'tersimpan' => false, 'peringatan' => null,
            'error' => 'Folder "data" tidak bisa ditulis. Buat folder bernama data di samping index.php dan beri izin tulis (chmod 755 atau 775).');
    }
    $lock = @fopen($dir . '/state.lock', 'c');
    if (!$lock) {
        return array('ok' => false, 'tersimpan' => false, 'peringatan' => null,
                     'error' => 'Gagal membuka file kunci di folder data.');
    }
    flock($lock, LOCK_EX);          // satu permintaan sekali jalan, supaya hitungan tidak bentrok

    $now = time();
    $st  = state_baca();
    $kg  = round($kg, 3);
    $st['live'] = array('berat_kg' => $kg, 'sumber' => $sumber, 'waktu' => $now);

    $tersimpan  = false;
    $peringatan = null;
    $simpanNilai = null;

    if ($sumber === 'manual') {
        $simpanNilai = $kg;
    } else {
        $j = $st['jendela'];
        // Mulai jendela baru bila belum ada, sumbernya berbeda, atau ada jeda panjang (alat sempat mati).
        if (!is_array($j) || $j['sumber'] !== $sumber || $now - $j['akhir'] > INTERVAL_SIMPAN_DETIK) {
            $j = array('sumber' => $sumber, 'mulai' => $now, 'akhir' => $now, 'n' => 0, 'jumlah' => 0.0);
        }
        $j['n']++;
        $j['jumlah'] += $kg;
        $j['akhir']   = $now;

        if ($now - $j['mulai'] >= INTERVAL_SIMPAN_DETIK && $now >= $st['ulang']) {
            $simpanNilai = ($sumber === 'simulasi') ? $kg : $j['jumlah'] / $j['n'];
        }
        $st['jendela'] = $j;
    }

    if ($simpanNilai !== null) {
        $r = simpan_berat($simpanNilai, $sumber);
        if ($r['ok']) {
            $tersimpan = true;
            $st['simpan'] = array('waktu' => $now, 'berat_kg' => round($simpanNilai, 3));
            $st['gagal']  = null;
            $st['ulang']  = 0;
            if ($sumber !== 'manual') {   // mulai jendela berikutnya dari sekarang
                $st['jendela'] = array('sumber' => $sumber, 'mulai' => $now, 'akhir' => $now, 'n' => 0, 'jumlah' => 0.0);
            }
            segarkan_pemakaian($st, $now);
        } else {
            error_log('CORNICREZ simpan ke Supabase gagal: ' . $r['error']);
            $peringatan = 'Gagal menyimpan ke Supabase (HTTP ' . $r['code'] . '). Periksa SUPABASE_URL dan SUPABASE_KEY di config.';
            $st['gagal'] = array('waktu' => $now, 'pesan' => $peringatan);
            $st['ulang'] = $now + 15;     // beri jeda sebelum mencoba lagi
        }
    }

    $ok = state_tulis($st);
    flock($lock, LOCK_UN);
    fclose($lock);

    if (!$ok) {
        return array('ok' => false, 'tersimpan' => $tersimpan, 'peringatan' => $peringatan,
                     'error' => 'Gagal menulis data sementara di folder data.');
    }
    return array('ok' => true, 'tersimpan' => $tersimpan, 'peringatan' => $peringatan, 'error' => null);
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
