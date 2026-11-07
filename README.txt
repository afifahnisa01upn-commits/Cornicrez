CORNICREZ Inventory Monitoring (Vercel + Supabase)
===================================================

APLIKASI
  public/index.html   dashboard statis (satu-satunya yang disajikan sebagai file biasa)
  public/assets/      CSS dan JavaScript dashboard
  api/                Vercel Functions: data, live, manual, simpan (alamat /api/NAMA)
  server/lib.js       fungsi backend bersama (tidak menjadi endpoint)
  vercel.json         pengaturan Vercel (folder output public, batas waktu fungsi)
  package.json        versi Node.js untuk Vercel
  database.sql        tabel, view, dan fungsi Supabase yang diperlukan
  esp32/              sketch ESP32 (tidak ikut di-upload ke Vercel; lihat .vercelignore)

DEPLOY KE VERCEL
  1. Di Supabase, buka SQL Editor dan jalankan seluruh isi database.sql.
     Jika tabel timbangan sudah ada, jalankan lagi file ini untuk menambahkan
     tabel state dan fungsi pencatatan untuk Vercel.
  2. Push folder proyek ke repository Git dan import repository itu di Vercel.
  3. Buka server/config.js dan isi tiga nilai:
       SUPABASE_URL   URL project Supabase (contoh https://xxxx.supabase.co)
       SUPABASE_KEY   anon key / publishable key (Project Settings > API Keys)
       API_KEY_ESP32  kata sandi acak minimal 16 karakter
     Tidak perlu mengisi Environment Variables di Vercel. (Bila variabel dengan nama
     yang sama diisi di Vercel, nilainya dipakai lebih dulu daripada config.js.)
  4. Buka URL deployment Vercel. Uji form manual atau aktifkan Simulasi.

KIRIM DATA DARI ESP32
  Arahkan request POST form-urlencoded ke:
       https://NAMA-PROYEK.vercel.app/api/simpan
  Field yang dikirim: key (sama dengan API_KEY_ESP32) dan berat (kg).
  URL lama yang berakhiran /api/simpan.php harus diganti menjadi /api/simpan.

KONFIGURASI STOK
  Nilai awal (kapasitas 5 kg, Safety Stock 1 kg, ROP 2 kg, simpan tiap 60 detik)
  ada di server/lib.js dan public/index.html. Jika mengubah parameter, samakan nilainya
  di kedua file tersebut.

BATAS PAKET GRATIS (HOBBY)
  ESP32 yang mengirim tiap 3 detik = sekitar 864.000 pemanggilan fungsi per bulan,
  belum termasuk dashboard yang memanggil /api/live tiap 3 detik selama dibuka.
  Bila mendekati batas, perbesar INTERVAL_KIRIM_MS di sketch (mis. 10000) dan
  refreshMs di public/index.html. Cek batas terbaru di halaman Pricing Vercel.

CATATAN
  - Vercel tidak menjalankan PHP sebagai aplikasi web biasa. Dashboard sekarang
    memakai index.html dan endpoint JavaScript di api/.
  - State live disimpan di Supabase, bukan filesystem Vercel yang sementara.
  - server/config.js berisi anon key dan API_KEY_ESP32 dan ikut ke repository.
    Anon key memang boleh publik, tetapi siapa pun yang memilikinya bisa membaca tabel dan
    memanggil fungsi cornicrez_record_reading() (menambah data palsu) tanpa lewat
    API_KEY_ESP32. Karena itu API_KEY_ESP32 di sini hanya penghalang ringan.
    Pakai repository PRIVATE bila data ini penting. Jangan pernah mengisi SUPABASE_KEY
    dengan secret / service_role key di file ini.
  - Dashboard dan input manual terbuka bagi siapa pun yang mengetahui URL.
    Proteksi login diperlukan sebelum dashboard dipublikasikan secara luas.
  - Untuk menghapus data simulasi: delete from public.timbangan where sumber = 'simulasi';
