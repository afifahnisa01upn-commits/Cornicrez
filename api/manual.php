<?php
// Dipanggil dashboard: input berat manual dan data simulasi (JSON).
// Manual langsung disimpan ke Supabase; simulasi diperlakukan seperti ESP32 (live + simpan per menit).
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

$h = catat_berat($berat, $sumber);
if (!$h['ok']) {
    json_out(array('ok' => false, 'error' => $h['error']), 500);
}
if ($h['peringatan'] !== null && $sumber === 'manual') {
    // Input manual memang harus tersimpan; beri tahu pengguna kalau gagal.
    json_out(array('ok' => false, 'error' => $h['peringatan']), 502);
}
json_out(array('ok' => true, 'berat_kg' => $berat, 'sumber' => $sumber, 'tersimpan' => $h['tersimpan']));
