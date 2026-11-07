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
  SUPABASE_KEY: 'sb_publishable_Qbf8nowgGQjE21nCI8IN-w_Oc1ck-xN',

  // Kata sandi kiriman ESP32 (huruf dan angka saja, minimal 16 karakter).
  // Harus SAMA dengan API_KEY di sketch ESP32.
  API_KEY_ESP32: '1234'
};
