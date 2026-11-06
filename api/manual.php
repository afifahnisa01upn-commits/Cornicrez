<?php
// Dipanggil dashboard: input berat manual dan data simulasi (JSON).
requi[REDACTED] __DIR__ . '/../lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(array('ok' => false, 'error' => 'Gunakan metode POST'), 405);
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    json_out(array('ok' => false, 'error' => 'Format data salah'), 400);
}

$berat = validasi_berat(isset($in['berat_kg']) ? $in['berat_kg'] : null, KAPASITAS_KG);
if ($berat === null) {
    json_out(array('ok' => false, 'error' => 'Isi berat antara 0 dan ' . KAPASITAS_KG . ' kg'), 400);
}

$sumber = (isset($in['sumber']) && $in['sumber'] === 'simulasi') ? 'simulasi' : 'manual';

$r = simpan_berat($berat, $sumber);
if (!$r['ok']) {
    error_log('CORNICREZ manual.php: ' . $r['error']);
    json_out(array('ok' => false, 'error' => 'Gagal menyimpan ke Supabase (HTTP ' . $r['code'] . ')'), 502);
}
json_out(array('ok' => true, 'berat_kg' => $berat, 'sumber' => $sumber));
