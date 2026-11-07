<?php
// Dipanggil dashboard tiap 3 detik: angka LIVE + hasil perhitungan.
// Data diambil dari file di server (diisi ESP32), bukan dari Supabase, jadi ringan dan cepat.
requi[REDACTED] __DIR__ . '/../lib.php';

if (folder_data() === null) {
    json_out(array('ok' => false,
        'error' => 'Folder "data" di server tidak bisa ditulis. Buat folder bernama data di samping index.php dan beri izin tulis (chmod 755 atau 775).'), 500);
}

$param = array(
    'loadCell'  => LOAD_CELL_KG,
    'kapasitas' => KAPASITAS_KG,
    'safety'    => SAFETY_STOCK_KG,
    'rop'       => ROP_KG,
);

$st   = state_baca();
$live = $st['live'];

// Server baru dijalankan / folder data baru dibuat: pakai data terakhir di Supabase.
if (!is_array($live)) {
    $r = supabase('GET', 'timbangan?select=berat_kg,sumber,created_at&order=created_at.desc&limit=1', null, 8);
    if (!$r['ok'] || !is_array($r['data'])) {
        error_log('CORNICREZ live.php: ' . $r['error']);
        json_out(array('ok' => false,
            'error' => 'Tidak bisa membaca Supabase (HTTP ' . $r['code'] . '). Periksa SUPABASE_URL dan SUPABASE_KEY di config.'), 502);
    }
    if (count($r['data']) > 0) {
        $live = array('berat_kg' => (float) $r['data'][0]['berat_kg'],
                      'sumber'   => $r['data'][0]['sumber'],
                      'waktu'    => strtotime($r['data'][0]['created_at']));
    }
}

$simpan = array(
    'interval' => INTERVAL_SIMPAN_DETIK,
    'terakhir' => is_array($st['simpan']) ? (int) $st['simpan']['waktu'] : null,
    'gagal'    => is_array($st['gagal']) ? $st['gagal']['pesan'] : null,
);

if (!is_array($live)) {
    json_out(array('ok' => true, 'terbaru' => null, 'status' => null, 'hitung' => null,
                   'sistem' => array('kode' => 'kosong', 'label' => 'Belum ada data'),
                   'param' => $param, 'simpan' => $simpan));
}

$kg   = (float) $live['berat_kg'];
$umur = max(0, time() - (int) $live['waktu']);

if ($live['sumber'] === 'manual')         $sistem = array('kode' => 'manual',   'label' => 'Input manual');
elseif ($umur > BATAS_OFFLINE_DETIK)      $sistem = array('kode' => 'offline',  'label' => 'Offline');
elseif ($live['sumber'] === 'simulasi')   $sistem = array('kode' => 'simulasi', 'label' => 'Simulasi');
else                                      $sistem = array('kode' => 'online',   'label' => 'Online');

$pemakaian = is_array($st['pemakaian']) ? (float) $st['pemakaian']['nilai'] : PEMAKAIAN_DEFAULT_KG_HARI;

json_out(array(
    'ok'      => true,
    'terbaru' => array('berat_kg' => $kg, 'sumber' => $live['sumber'],
                       'waktu' => gmdate('Y-m-d\TH:i:s\Z', (int) $live['waktu']), 'umur_detik' => $umur),
    'status'  => status_stok($kg),
    'hitung'  => hitung($kg, $pemakaian),
    'sistem'  => $sistem,
    'param'   => $param,
    'simpan'  => $simpan,
));
