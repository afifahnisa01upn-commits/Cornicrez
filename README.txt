CORNICREZ Inventory Monitoring (PHP + HTML + Supabase)
========================================================

ISI FOLDER
  index.php                 halaman dashboard
  config.php                parameter stok dan interval (Safety Stock, ROP, interval simpan, dll.)
  config.local.example.php  contoh isi RAHASIA: salin menjadi config.local.php lalu isi  <- EDIT INI
  lib.php                   fungsi bersama (Supabase, data live, perhitungan)
  database.sql              struktur tabel Supabase
  api/simpan.php            dipanggil ESP32 tiap 3 detik (kirim berat)
  api/manual.php            dipanggil dashboard (input manual + simulasi)
  api/live.php              dipanggil dashboard tiap 3 detik (angka LIVE, dari server)
  api/data.php              dipanggil dashboard saat ada data baru tersimpan (riwayat dari Supabase)
  assets/                   style.css dan app.js
  data/                     dibuat otomatis oleh server (angka live sementara). Jangan di-upload / di-commit.
  esp32/timbangan_cornicrez/timbangan_cornicrez.ino   sketch ESP32-S3

ALUR DATA
  ESP32-S3 --(WiFi, HTTPS, tiap 3 detik)--> api/simpan.php (di hosting)
        |-- angka LIVE disimpan di file server (data/)  --> api/live.php --> dashboard (tiap 3 detik)
        '-- tiap 1 menit: rata-rata pembacaan --> Supabase --> api/data.php --> grafik & tabel riwayat
  Arduino IDE hanya dipakai untuk memprogram ESP32 (dan Serial Monitor untuk cek).

  Jadi: angka di dashboard bergerak terus, tetapi Supabase hanya bertambah 1 baris per menit
  (sekitar 1.440 baris per hari). Ubah INTERVAL_SIMPAN_DETIK di config.php bila ingin lain.
  Input manual ("Hitung & Perbarui") dilakukan dengan sengaja, jadi langsung tersimpan.

LANGKAH PASANG
  1. Supabase: buat project, buka SQL Editor, jalankan isi database.sql.
  2. Supabase: Project Settings > API Keys > salin "Project URL" dan "Secret key" (sb_secret_...).
  3. Salin config.local.example.php menjadi config.local.php, lalu isi SUPABASE_URL, SUPABASE_KEY,
     dan ganti API_KEY_ESP32 dengan kata sandi buatanmu sendiri (huruf dan angka, minimal 16 karakter).
  4. Upload semua file (kecuali folder esp32, README, dan data) ke hosting PHP
     (butuh PHP 7.4+ dengan cURL). config.local.php ikut di-upload.
     Folder tempat index.php berada harus boleh ditulis server, supaya folder data/ bisa dibuat.
     Bila tidak bisa, buat sendiri folder bernama data lalu beri izin tulis (chmod 755 atau 775).
  5. Buka alamat hosting. Coba isi "Input Berat" atau centang "Simulasi" untuk mengetes.
  6. ESP32: buka sketch di folder esp32, isi WIFI_SSID, WIFI_PASS, URL_SIMPAN, API_KEY
     (API_KEY harus sama dengan API_KEY_ESP32), lalu upload.

TES LOKAL (opsional, tanpa hosting)
  Jalankan di folder ini:  php -S 0.0.0.0:8080
  Buka http://localhost:8080  (cURL harus aktif di PHP).
  Untuk ESP32 di jaringan WiFi yang sama, pakai http://IP-PC:8080/api/simpan.php
  dan ganti WiFiClientSecure di sketch dengan WiFiClient biasa.

CATATAN
  - Dashboard menunjukkan "Online" bila ESP32 mengirim data dalam 20 detik terakhir
    (BATAS_OFFLINE_DETIK di config.php), selebihnya "Offline".
  - Bila penyimpanan ke Supabase gagal, angka live tetap jalan dan dashboard menampilkan peringatan merah.
    Pembacaan terus dikumpulkan dan disimpan setelah Supabase kembali normal.
  - Bila hosting dipindah/di-deploy ulang dan folder data/ terhapus, dashboard otomatis memakai
    data terakhir di Supabase sampai ESP32 mengirim lagi.

CATATAN KEAMANAN
  - Secret key Supabase hanya ada di config.local.php (server). Jangan dipasang di JavaScript atau sketch.
  - config.local.php dan data/ sudah ada di .gitignore, jadi tidak ikut ke GitHub.
    Sketch .ino berisi password WiFi dan API_KEY: jangan di-push ke GitHub setelah diisi.
  - Tabel memakai RLS tanpa policy, jadi kunci publik Supabase tidak bisa membaca/menulis.
  - Dashboard dan input manual terbuka untuk siapa pun yang tahu alamatnya.
    Hanya kiriman dari ESP32 yang memakai kata sandi. Tambahkan login jika dashboard dipublikasikan luas.
  - Hapus data simulasi:  delete from public.timbangan where sumber = 'simulasi';
