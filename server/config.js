// ============================================================
//  CORNICREZ - ISI TIGA NILAI DI BAWAH INI, lalu simpan.
//  File ini dibaca server (Vercel Functions), tidak dikirim ke browser.
//  Environment variable Vercel dengan nama yang sama (kalau ada) dipakai lebih dulu.
// ============================================================

module.exports = {
  // Project URL: Supabase > Project Settings > API, contoh https://abcdefghijkl.supabase.co
  SUPABASE_URL: 'https://gzeaevcvvjnwvnvmtprb.supabase.co',

  // anon key / publishable key: Supabase > Project Settings > API Keys
  // (anon "eyJ..." atau publishable "sb_publishable_..."). JANGAN isi dengan secret / service_role key.
  SUPABASE_KEY: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd6ZWFldmN2dmpud3Zudm10cHJiIiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTM5NjM3NTEsImV4cCI6MjEwOTUzOTc1MX0.d6vBFoi9UsWYMX1DgolJCFL9TaDOmosYwA9HXnL82YY',

  // Kata sandi kiriman ESP32 (huruf dan angka saja, minimal 16 karakter).
  // Harus SAMA dengan API_KEY di sketch ESP32.
  API_KEY_ESP32: '1234'
};
