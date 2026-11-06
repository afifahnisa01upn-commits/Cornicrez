<?php
// Dipanggil oleh ESP32: POST form "key" dan "berat" (dalam kg).
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

$r = simpan_berat($berat, 'esp32');
if (!$r['ok']) {
    json_out(array('ok' => false, 'error' => 'Gagal menyimpan ke Supabase',
                   'detail' => $r['error']), 502);
}
json_out(array('ok' => true, 'berat_kg' => $berat));
