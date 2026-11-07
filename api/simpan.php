<?php
// Dipanggil oleh ESP32 (tiap beberapa detik): POST form "key" dan "berat" (dalam kg).
// Angka langsung tampil live di dashboard; ke Supabase hanya disimpan sekali per menit (lihat lib.php).
requi[REDACTED] __DIR__ . '/../lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(array('ok' => false, 'error' => 'Gunakan metode POST'), 405);
}

$key = isset($_POST['key']) ? (string) $_POST['key'] : '';
if (!hash_equals(API_KEY_ESP32, $key)) {
    json_out(array('ok' => false, 'error' => 'Kunci salah'), 403);
}

$berat = validasi_berat(isset($_POST['berat']) ? $_POST['berat'] : null);
if ($berat === null) {
    json_out(array('ok' => false, 'error' => 'Nilai berat tidak valid'), 400);
}

$h = catat_berat($berat, 'esp32');
if (!$h['ok']) {
    json_out(array('ok' => false, 'error' => $h['error']), 500);
}
$out = array('ok' => true, 'berat_kg' => $berat, 'tersimpan' => $h['tersimpan']);
if ($h['peringatan'] !== null) $out['peringatan'] = $h['peringatan'];
json_out($out);
