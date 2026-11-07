const {
  CONFIG,
  environment,
  hitung,
  reportError,
  requireMethod,
  send,
  statusStok,
  supabase
} = require('../server/lib');

module.exports = async function handler(req, res) {
  if (!requireMethod(req, res, 'GET')) return;

  try {
    environment();
    const rows = await supabase('cornicrez_state?select=state&id=eq.true&limit=1');
    const state = rows?.[0]?.state || {};
    let live = state.live;

    if (!live) {
      const latest = await supabase('timbangan?select=berat_kg,sumber,created_at&order=created_at.desc&limit=1');
      if (latest?.length) {
        live = {
          berat_kg: Number(latest[0].berat_kg),
          sumber: latest[0].sumber,
          waktu: Math.floor(new Date(latest[0].created_at).getTime() / 1000)
        };
      }
    }

    const simpan = {
      interval: CONFIG.intervalSimpan,
      terakhir: state.simpan?.waktu ?? null,
      gagal: state.gagal?.pesan ?? null
    };
    const param = {
      loadCell: CONFIG.loadCell,
      kapasitas: CONFIG.kapasitas,
      safety: CONFIG.safety,
      rop: CONFIG.rop
    };

    if (!live) {
      return send(res, 200, {
        ok: true,
        terbaru: null,
        status: null,
        hitung: null,
        sistem: { kode: 'kosong', label: 'Belum ada data' },
        param,
        simpan
      });
    }

    const kg = Number(live.berat_kg);
    const umur = Math.max(0, Math.floor(Date.now() / 1000) - Number(live.waktu));
    let sistem;
    if (live.sumber === 'manual') sistem = { kode: 'manual', label: 'Input manual' };
    else if (umur > CONFIG.batasOffline) sistem = { kode: 'offline', label: 'Offline' };
    else if (live.sumber === 'simulasi') sistem = { kode: 'simulasi', label: 'Simulasi' };
    else sistem = { kode: 'online', label: 'Online' };

    const pemakaian = Number(state.pemakaian?.nilai) || CONFIG.pemakaianDefault;
    return send(res, 200, {
      ok: true,
      terbaru: {
        berat_kg: kg,
        sumber: live.sumber,
        waktu: new Date(Number(live.waktu) * 1000).toISOString(),
        umur_detik: umur
      },
      status: statusStok(kg),
      hitung: hitung(kg, pemakaian),
      sistem,
      param,
      simpan
    });
  } catch (error) {
    reportError('live', error, res);
  }
};
