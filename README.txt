CORNICREZ Inventory Monitoring (PHP + HTML + Supabase)
========================================================

ISI FOLDER
  index.php            halaman dashboard
  config.php           pengaturan (Supabase, kata sandi ESP32, parameter stok)  <- EDIT INI
  lib.php              fungsi bersama (akses Supabase + perhitungan)
  database.sql         struktur tabel Supabase
  api/simpan.php       dipanggil ESP32 (kirim berat)
  api/manual.php       dipanggil dashboard (input manual + simulasi)
  api/data.php         dipanggil dashboard (ambil data tiap 3 detik)
  assets/              style.css dan app.js
  esp32/timbangan_cornicrez/timbangan_cornicrez.ino   sketch ESP32-S3

ALUR DATA
  ESP32-S3 --(WiFi, HTTPS)--> api/simpan.php (di hosting) --> Supabase
  Browser  <-- api/data.php (di hosting) <-- Supabase
  Arduino IDE hanya dipakai untuk memprogram ESP32 (dan Serial Monitor untuk cek).

LANGKAH PASANG
  1. Supabase: buat project, buka SQL Editor, jalankan isi database.sql.
  2. Supabase: Project Settings > API Keys > salin "Project URL" dan "Secret key" (sb_secret_...).
  3. Edit config.php: isi SUPABASE_URL, SUPABASE_KEY, dan ganti API_KEY_ESP32
     dengan kata sandi buatanmu sendiri (huruf dan angka, minimal 16 karakter).
  4. Upload semua file (kecuali folder esp32 dan README) ke hosting PHP (butuh PHP 7.4+ dengan cURL).
     Contoh alamat: https://domainmu.com/cornicrez/
  5. Buka alamat itu. Coba isi "Input Berat" atau centang "Simulasi" untuk mengetes.
  6. ESP32: buka sketch di folder esp32, isi WIFI_SSID, WIFI_PASS, URL_SIMPAN, API_KEY
     (API_KEY harus sama dengan API_KEY_ESP32), lalu upload.

TES LOKAL (opsional, tanpa hosting)
  Jalankan di folder ini:  php -S 0.0.0.0:8080
  Buka http://localhost:8080  (cURL harus aktif di PHP).
  Untuk ESP32 di jaringan WiFi yang sama, pakai http://IP-PC:8080/api/simpan.php
  dan ganti WiFiClientSecure di sketch dengan WiFiClient biasa.

CATATAN KEAMANAN
  - Secret key Supabase hanya ada di config.php (server). Jangan dipasang di JavaScript atau sketch.
  - Tabel memakai RLS tanpa policy, jadi kunci publik Supabase tidak bisa membaca/menulis.
  - Dashboard dan input manual terbuka untuk siapa pun yang tahu alamatnya.
    Hanya kiriman dari ESP32 yang memakai kata sandi. Tambahkan login jika dashboard dipublikasikan luas.
  - Hapus data simulasi:  delete from public.timbangan where sumber = 'simulasi';
