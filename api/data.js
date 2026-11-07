const { reportError, requireMethod, send, supabase } = require('../server/lib');

module.exports = async function handler(req, res) {
  if (!requireMethod(req, res, 'GET')) return;

  try {
    const rows = await supabase('timbangan?select=berat_kg,sumber,created_at&order=created_at.desc&limit=60');
    if (!Array.isArray(rows)) throw new Error('Respons riwayat Supabase tidak valid.');
    send(res, 200, { ok: true, riwayat: rows.reverse() });
  } catch (error) {
    reportError('data', error, res);
  }
};
