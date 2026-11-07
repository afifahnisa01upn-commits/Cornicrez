const { CONFIG, catatBerat, reportError, requireMethod, send, validasiBerat } = require('../server/lib');

module.exports = async function handler(req, res) {
  if (!requireMethod(req, res, 'POST')) return;

  const input = req.body;
  if (!input || typeof input !== 'object' || Array.isArray(input)) {
    return send(res, 400, { ok: false, error: 'Format data salah' });
  }

  const berat = validasiBerat(input.berat_kg, CONFIG.kapasitas);
  if (berat === null) {
    return send(res, 400, { ok: false, error: `Isi berat antara 0 dan ${CONFIG.kapasitas} kg` });
  }
  const sumber = input.sumber === 'simulasi' ? 'simulasi' : 'manual';

  try {
    const result = await catatBerat(berat, sumber);
    if (result.peringatan && sumber === 'manual') {
      return send(res, 502, { ok: false, error: result.peringatan });
    }
    send(res, 200, {
      ok: true,
      berat_kg: berat,
      sumber,
      tersimpan: result.tersimpan
    });
  } catch (error) {
    reportError('manual', error, res);
  }
};
