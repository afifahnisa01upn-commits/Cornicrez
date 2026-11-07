<?php
// ============================================================
//  CORNICREZ Inventory Monitoring - KONFIGURASI
//  Ubah nilai di bawah ini, lalu simpan.
//  File ini hanya dibaca server (PHP), tidak dikirim ke browser.
// ============================================================

// Data rahasia (Supabase + kata sandi ESP32) sebaiknya ditaruh di config.local.php
// (salin dari config.local.example.php). File itu tidak ikut ke Git, jadi aman bila
// proyek ini di-push ke GitHub. Bila file itu ada, nilainya dipakai lebih dulu.
if (is_file(__DIR__ . '/config.local.php')) {
    requi[REDACTED] __DIR__ . '/config.local.php';
}

// --- Supabase -------------------------------------------------
// Project URL, contoh: https://abcdefghijkl.supabase.co
if (!defined('SUPABASE_URL')) define('SUPABASE_URL', 'https://PROJECT-REF.supabase.co');

// Secret key dari Supabase: Project Settings > API Keys > "Secret keys" (diawali sb_secret_).
// Key lama "service_role" juga masih bisa dipakai.
// JANGAN pernah memakai key ini di JavaScript / sketch ESP32.
if (!defined('SUPABASE_KEY')) define('SUPABASE_KEY', 'sb_secret_GANTI_DENGAN_KEY_ANDA');

// --- Keamanan kiriman dari ESP32 -------------------------------
// Kata sandi ini harus SAMA dengan API_KEY di sketch ESP32.
// Pakai huruf dan angka saja (tanpa spasi / simbol), panjang minimal 16 karakter.
if (!defined('API_KEY_ESP32')) define('API_KEY_ESP32', 'ganti-dengan-kata-sandi-panjang');

// --- Parameter persediaan (satuan kg) --------------------------
define('LOAD_CELL_KG',    5.0);   // kapasitas load cell
define('KAPASITAS_KG',    5.0);   // kapasitas maksimum penyimpanan
define('SAFETY_STOCK_KG', 1.0);   // safety stock
define('ROP_KG',          2.0);   // reorder point

// Perkiraan pemakaian per hari (kg). Dipakai untuk "Estimasi hari sebelum Safety Stock"
// selama riwayat belum cukup untuk dihitung otomatis.
define('PEMAKAIAN_DEFAULT_KG_HARI', 0.5);

// --- Waktu -------------------------------------------------------
// Angka berat di dashboard tampil LIVE (diperbarui terus oleh ESP32 tiap beberapa detik),
// tetapi database Supabase hanya diisi sekali tiap N detik (rata-rata pembacaan selama itu).
define('INTERVAL_SIMPAN_DETIK', 60);

// ESP32 dianggap "Offline" bila tidak mengirim data lebih dari N detik.
define('BATAS_OFFLINE_DETIK', 20);

date_default_timezone_set('Asia/Jakarta');
