const {
  catatBerat,
  environment,
  reportError,
  requireMethod,
  safeEqual,
  send,
  validasiBerat
} = require('../server/lib');

function parseFormBody(req) {
  if (req.body && typeof req.body === 'object' && !Buffer.isBuffer(req.body)) return req.body;
  const raw = Buffer.isBuffer(req.body) ? req.body.toString('utf8') : String(req.body || '');
  return Object.fromEntries(new URLSearchParams(raw));
}

module.exports = async function handler(req, res) {
  if (!requireMethod(req, res, 'POST')) return;

  try {
    const env = environment();
    const input = parseFormBody(req);
    if (!safeEqual(input.key || '', env.esp32Key)) {
      return send(res, 403, { ok: false, error: 'Kunci salah' });
    }

    const berat = validasiBerat(input.berat);
    if (berat === null) {
      return send(res, 400, { ok: false, error: 'Nilai berat tidak valid' });
    }

    const result = await catatBerat(berat, 'esp32');
    const output = { ok: true, berat_kg: berat, tersimpan: result.tersimpan };
    if (result.peringatan) output.peringatan = result.peringatan;
    send(res, 200, output);
  } catch (error) {
    reportError('simpan', error, res);
  }
};
