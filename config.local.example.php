<?php
// ============================================================
//  Salin file ini menjadi  config.local.php  lalu isi nilainya.
//  config.local.php TIDAK ikut ke Git (lihat .gitignore), jadi key rahasia aman.
//  Upload config.local.php ke hosting bersama file lainnya.
// ============================================================

// Project URL Supabase, contoh: https://abcdefghijkl.supabase.co
define('SUPABASE_URL', 'https://PROJECT-REF.supabase.co');

// Secret key: Project Settings > API Keys > "Secret keys" (diawali sb_secret_)
define('SUPABASE_KEY', 'sb_secret_GANTI_DENGAN_KEY_ANDA');

// Kata sandi kiriman ESP32 (huruf dan angka saja, minimal 16 karakter).
// Harus SAMA dengan API_KEY di sketch ESP32.
define('API_KEY_ESP32', 'ganti-dengan-kata-sandi-panjang');
