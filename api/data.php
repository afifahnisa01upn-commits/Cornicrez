<?php
// Dipanggil dashboard (tiap 3 detik): ambil data terbaru + hasil perhitungan.
requi[REDACTED] __DIR__ . '/../lib.php';

// 30 data terakhir (untuk grafik, tabel, dan nilai terbaru)
$r = supabase('GET', 'timbangan?select=berat_kg,sumber,created_at&order=created_at.desc&limit=30');
if (!$r['ok'] || !is_array($r['data'])) {
    error_log('CORNICREZ data.php: ' . $r['error']);
    json_out(array('ok' => false,
        'error' => 'Tidak bisa membaca Supabase (HTTP ' . $r['code'] . '). Periksa SUPABASE_URL dan SUPABASE_KEY di config.php.'), 502);
}

$param = array(
    'loadCell'  => LOAD_CELL_KG,
    'kapasitas' => KAPASITAS_KG,
    'safety'    => SAFETY_STOCK_KG,
    'rop'       => ROP_KG,
);

if (count($r['data']) === 0) {
    json_out(array('ok' => true, 'terbaru' => null, 'status' => null, 'hitung' => null,
                   'sistem' => array('kode' => 'kosong', 'label' => 'Belum ada data'),
                   'param' => $param, 'riwayat' => array()));
}

$riwayat = array_reverse($r['data']);          // urut naik untuk grafik
$last    = $r['data'][0];
$kg      = (float) $last['berat_kg'];
$umur    = max(0, time() - strtotime($last['created_at']));

// Perkiraan pemakaian dari rata-rata per jam, 7 hari terakhir.
// Bila view timbangan_per_jam belum dibuat, pakai nilai default dari config.php.
$sejak = gmdate('Y-m-d\TH:i:s\Z', time() - 7 * 86400);
$pj = supabase('GET', 'timbangan_per_jam?select=jam,berat_kg&order=jam.asc&limit=200&jam=gte.' . rawurlencode($sejak));
$pemakaian = ($pj['ok'] && is_array($pj['data'])) ? pemakaian_per_hari($pj['data']) : PEMAKAIAN_DEFAULT_KG_HARI;

// Status sistem
if ($last['sumber'] === 'simulasi')      $sistem = array('kode' => 'simulasi', 'label' => 'Simulasi');
elseif ($last['sumber'] === 'manual')    $sistem = array('kode' => 'manual',   'label' => 'Input manual');
elseif ($umur <= BATAS_OFFLINE_DETIK)    $sistem = array('kode' => 'online',   'label' => 'Online');
else                                     $sistem = array('kode' => 'offline',  'label' => 'Offline');

json_out(array(
    'ok'      => true,
    'terbaru' => array('berat_kg' => $kg, 'sumber' => $last['sumber'],
                       'waktu' => $last['created_at'], 'umur_detik' => $umur),
    'status'  => status_stok($kg),
    'hitung'  => hitung($kg, $pemakaian),
    'sistem'  => $sistem,
    'param'   => $param,
    'riwayat' => $riwayat,
));
