const { timingSafeEqual } = require('node:crypto');
const config = require('./config');
// KONFIGURASI EMAIL CORNICREZ
const EMAIL_CONFIG = {
  apiKey: process.env.RESEND_API_KEY || '',
  from: 'onboarding@resend.dev',
  to: 'cacornicrez@gmail.com'
};



const CONFIG = {
  loadCell: 5,
  kapasitas: 5,
  safety: 1,
  rop: 2,
  refreshMs: 3000,
  intervalSimpan: 60,
  batasOffline: 20,
  pemakaianDefault: 0.5
};

function send(res, status, data) {
  res.setHeader('Cache-Control', 'no-store');
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.status(status).json(data);
}

function requireMethod(req, res, method) {
  if (req.method === method) return true;
  send(res, 405, { ok: false, error: `Gunakan metode ${method}` });
  return false;
}

// Nilai dibaca dari server/config.js; environment variable Vercel (bila ada) didahulukan.
const PLACEHOLDER = /PROJECT-REF|GANTI_DENGAN|ganti-dengan/i;

function environment() {
  const url = process.env.SUPABASE_URL || config.SUPABASE_URL;
  const key = process.env.SUPABASE_KEY || config.SUPABASE_KEY;
  const esp32Key = process.env.API_KEY_ESP32 || config.API_KEY_ESP32;
  if (![url, key, esp32Key].every((v) => v && !PLACEHOLDER.test(v))) {
    throw new Error('SUPABASE_URL, SUPABASE_KEY, dan API_KEY_ESP32 belum diisi di server/config.js.');
  }
  return { url: url.replace(/\/+$/, ''), key, esp32Key };
}

async function supabase(path, options = {}) {
  const env = environment();
  const headers = {
    apikey: env.key,
    Accept: 'application/json',
    ...(options.body === undefined ? {} : {
      'Content-Type': 'application/json',
      ...(options.prefer ? { Prefer: options.prefer } : {})
    })
  };
  if (env.key.startsWith('eyJ')) headers.Authorization = `Bearer ${env.key}`;

  let response;
  try {
    response = await fetch(`${env.url}/rest/v1/${path}`, {
      method: options.method || 'GET',
      headers,
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      signal: AbortSignal.timeout(10000)
    });
  } catch (error) {
    throw new Error(`Koneksi ke Supabase gagal: ${error.message}`);
  }

  const text = await response.text();
  let data = null;
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      throw new Error(`Supabase mengembalikan respons yang tidak valid (HTTP ${response.status}).`);
    }
  }
  if (!response.ok) {
    throw new Error(`Supabase menolak permintaan (HTTP ${response.status}): ${data?.message || response.statusText}`);
  }
  return data;
}

function validasiBerat(value, max = 100) {
  if (value === null || value === undefined || typeof value === 'object') return null;
  const normalized = String(value).trim().replace(',', '.');
  if (!normalized || !Number.isFinite(Number(normalized))) return null;
  const berat = Number(normalized);
  if (berat > max) return null;
  return Math.max(0, berat);
}

function statusStok(kg) {
  if (kg <= CONFIG.safety) {
    return {
      kode: 'kritis',
      label: 'KRITIS',
      pesan: '✖ Persediaan berada di bawah atau sama dengan Safety Stock.',
      detail: 'Segera lakukan pengadaan!'
    };
  }
  if (kg <= CONFIG.rop) {
    return {
      kode: 'menipis',
      label: 'MENIPIS',
      pesan: '⚠ Persediaan sudah mencapai Reorder Point.',
      detail: 'Saatnya melakukan pemesanan ulang.'
    };
  }
  return {
    kode: 'aman',
    label: 'AMAN',
    pesan: '✓ Persediaan berada di atas ROP.',
    detail: 'Stok masih dalam kondisi aman.'
  };
}

function hitung(kg, pemakaian) {
  const sisa = Math.max(0, kg - CONFIG.safety);
  const diAtasRop = kg > CONFIG.rop;
  const hari = kg <= CONFIG.safety ? 0 : (pemakaian > 0 ? sisa / pemakaian : null);
  return {
    sisa_di_atas_safety: Number(sisa.toFixed(2)),
    di_atas_rop: diAtasRop,
    selisih_ke_rop: Number((diAtasRop ? kg - CONFIG.rop : 0).toFixed(2)),
    kebutuhan_pengadaan: Number(Math.max(0, CONFIG.kapasitas - kg).toFixed(2)),
    estimasi_hari: hari === null ? null : Number(hari.toFixed(1)),
    pemakaian_per_hari: Number(pemakaian.toFixed(2)),
    kapasitas_persen: Math.min(100, Math.max(0, Math.round(kg / CONFIG.kapasitas * 100)))
  };
}


async function kirimNotifikasiStok(
  
  kg,
  sumber,
  statusSebelumnya,
  statusSekarang
) {
  const statusLama = String(statusSebelumnya || '').toLowerCase();
  const statusBaru = String(statusSekarang || '').toLowerCase();
console.log('DEBUG EMAIL:', {
  sumber,
  statusSebelumnya,
  statusSekarang,
  statusLama,
  statusBaru
});
  // Data simulasi tidak mengirim email.
  if (sumber === 'simulasi') return;

  // Email hanya dikirim jika status berubah menjadi MENIPIS atau KRITIS.
  if (
    !['menipis', 'kritis'].includes(statusBaru) ||
    statusLama === statusBaru
  ) {
    return;
  }

  const label =
    statusBaru === 'kritis' ? 'KRITIS' : 'MENIPIS';

  const pesan =
    statusBaru === 'kritis'
      ? 'Persediaan telah mencapai atau berada di bawah Safety Stock. Segera lakukan pengadaan bahan baku.'
      : 'Persediaan telah mencapai Reorder Point. Segera lakukan pemesanan ulang.';


  const waktu = new Date().toLocaleString('id-ID', {
    timeZone: 'Asia/Jakarta',
    dateStyle: 'full',
    timeStyle: 'medium'
  });

  try {
    if (!EMAIL_CONFIG.apiKey) {
      throw new Error('RESEND_API_KEY belum diatur.');
    }
    const response = await fetch(
      'https://api.resend.com/emails',
      {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${EMAIL_CONFIG.apiKey}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          from: `CORNICREZ Monitoring <${EMAIL_CONFIG.from}>`,
          to: [EMAIL_CONFIG.to],
          subject: `[CORNICREZ] Peringatan Stok ${label}`,
          html: `
            <div style="font-family:Arial,sans-serif;color:#333;line-height:1.6">
              <h2>Peringatan Persediaan CORNICREZ</h2>

              <p>Sistem monitoring mendeteksi perubahan kondisi persediaan.</p>

              <table style="border-collapse:collapse">
                <tr>
                  <td style="padding:8px"><b>Status</b></td>
                  <td style="padding:8px">${label}</td>
                </tr>
                <tr>
                  <td style="padding:8px"><b>Berat stok</b></td>
                  <td style="padding:8px">${Number(kg).toFixed(3)} kg</td>
                </tr>
                <tr>
                  <td style="padding:8px"><b>Status sebelumnya</b></td>
                  <td style="padding:8px">${statusSebelumnya || 'Belum tersedia'}</td>
                </tr>
                <tr>
                  <td style="padding:8px"><b>Sumber data</b></td>
                  <td style="padding:8px">${sumber}</td>
                </tr>
                <tr>
                  <td style="padding:8px"><b>Waktu</b></td>
                  <td style="padding:8px">${waktu}</td>
                </tr>
              </table>

              <p>${pesan}</p>

              <p>
                Email ini dikirim secara otomatis oleh
                sistem monitoring persediaan CORNICREZ.
              </p>
            </div>
          `
        }),
        signal: AbortSignal.timeout(10000)
      }
    );

    const hasil = await response.json();

    if (!response.ok) {
      throw new Error(
        hasil.message || `Resend HTTP ${response.status}`
      );
    }

    console.log(
      `CORNICREZ: Email ${label} berhasil dikirim. ID: ${hasil.id}`
    );

  } catch (error) {
    // Kegagalan email tidak membatalkan pencatatan stok.
    console.error(
      'CORNICREZ: Gagal mengirim email:',
      error.message
    );
  }
}

async function catatBerat(kg, sumber) {
  const result = await supabase('rpc/cornicrez_record_reading', {
    method: 'POST',
    body: {
      p_berat_kg: Number(kg.toFixed(3)),
      p_sumber: sumber,
      p_interval: CONFIG.intervalSimpan
    }
  });

  if (!result || !result.state) {
    throw new Error(
      'Respons pencatatan dari Supabase tidak lengkap.'
    );
  }

  await kirimNotifikasiStok(
    kg,
    sumber,
    result.status_sebelumnya,
    result.status_sekarang
  );

  return result;
}

function safeEqual(left, right) {
  const a = Buffer.from(String(left));
  const b = Buffer.from(String(right));
  return a.length === b.length && timingSafeEqual(a, b);
}

function reportError(name, error, res) {
  console.error(`CORNICREZ ${name}:`, error);
  send(res, 502, {
    ok: false,
    error: 'Layanan Supabase gagal. Periksa server/config.js dan jalankan pembaruan database.sql.'
  });
}

module.exports = {
  CONFIG,
  catatBerat,
  environment,
  hitung,
  reportError,
  requireMethod,
  safeEqual,
  send,
  statusStok,
  supabase,
  validasiBerat
};
