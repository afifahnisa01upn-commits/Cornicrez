<?php
// Riwayat yang TERSIMPAN di Supabase (satu baris per menit), untuk grafik dan tabel.
// Dashboard memanggilnya sekali saat dibuka, lalu lagi hanya ketika ada data baru tersimpan.
requi[REDACTED] __DIR__ . '/../lib.php';

$r = supabase('GET', 'timbangan?select=berat_kg,sumber,created_at&order=created_at.desc&limit=60', null, 10);
if (!$r['ok'] || !is_array($r['data'])) {
    error_log('CORNICREZ data.php: ' . $r['error']);
    json_out(array('ok' => false,
        'error' => 'Tidak bisa membaca Supabase (HTTP ' . $r['code'] . '). Periksa SUPABASE_URL dan SUPABASE_KEY di config.'), 502);
}

json_out(array('ok' => true, 'riwayat' => array_reverse($r['data'])));   // urut naik untuk grafik
