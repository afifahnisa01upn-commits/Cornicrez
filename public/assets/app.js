(function () {
  'use strict';

  var CFG = window.CFG;
    // muat Safety Stock & ROP yang tersimpan dari kalkulator
  try {
    var tersimpan = JSON.parse(localStorage.getItem('cfg') || 'null');
    if (tersimpan) {
      if (typeof tersimpan.safety === 'number') CFG.safety = tersimpan.safety;
      if (typeof tersimpan.rop === 'number') CFG.rop = tersimpan.rop;
    }
  } catch (e) {}
  var terakhirLive = null, terakhirRiwayat = null;
  var WARNA = { aman: '#2e9c4f', menipis: '#f08a00', kritis: '#d93025' };
  var SUMBER = { esp32: 'ESP32', manual: 'Manual', simulasi: 'Simulasi' };

  function $(id) { return document.getElementById(id); }
  function fmt(n, d) { return Number(n).toFixed(d === undefined ? 2 : d); }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function jam(iso) {
    var d = new Date(iso);
    if (isNaN(d)) return '-';
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: 'Asia/Jakarta' });
  }
  function tanggal(iso) {
    var d = new Date(iso);
    if (isNaN(d)) return '-';
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', timeZone: 'Asia/Jakarta' });
  }
    function kodeStatus(kg) {
    return kg <= CFG.safety ? 'kritis' : (kg <= CFG.rop ? 'menipis' : 'aman');
  }
  // Pasang class dasar + kode status (aman = tanpa class tambahan)
  function kelas(el, dasar, kode) {
    el.className = dasar + (kode && kode !== 'aman' ? ' ' + kode : '');
  }

  // Status dihitung di browser, batasnya dari CFG (hasil kalkulator ROP)
  function statusBaru(kg) {
    var kode = kodeStatus(kg);
    if (kode === 'kritis') return {
      kode: kode, label: 'KRITIS',
      pesan: '🚨 Stok di bawah Safety Stock!',
      detail: 'Stok ≤ ' + fmt(CFG.safety) + ' kg. Segera lakukan pengadaan.'
    };
    if (kode === 'menipis') return {
      kode: kode, label: 'MENIPIS',
      pesan: '⚠ Peringatan: Stok mencapai ROP!',
      detail: 'Stok ≤ ' + fmt(CFG.rop) + ' kg. Perlu dilakukan pengadaan kembali.'
    };
    return {
      kode: kode, label: 'AMAN',
      pesan: 'Stok dalam kondisi aman.',
      detail: 'Stok di atas Reorder Point (' + fmt(CFG.rop) + ' kg). Belum perlu pengadaan.'
    };
  }

  function hitungBaru(kg, h) {
    var sisa = Math.max(0, kg - CFG.safety);
    var pakai = h.pemakaian_per_hari;
    return {
      sisa_di_atas_safety: sisa,
      di_atas_rop: kg > CFG.rop,
      selisih_ke_rop: kg - CFG.rop,
      kebutuhan_pengadaan: Math.max(0, CFG.kapasitas - kg),
      estimasi_hari: pakai > 0 ? sisa / pakai : null,
      pemakaian_per_hari: pakai || 0,
      kapasitas_persen: Math.round(Math.min(100, Math.max(0, kg / CFG.kapasitas * 100)))
    };
  }
  

  /* ------------------------------------------------------------ */
  /*  Tampilan                                                    */
  /* ------------------------------------------------------------ */
  // Pesan error dari beberapa sumber digabung: yang pertama berisi yang ditampilkan.
  var galat = { live: '', riwayat: '', simpan: '' };
  function setGalat(sumber, pesan) {
    galat[sumber] = pesan || '';
    var teks = galat.live || galat.riwayat || galat.simpan;
    var el = $('alert');
    el.hidden = !teks;
    el.textContent = teks;
  }

  // 60 -> "1 menit", 90 -> "90 detik"
  function teksInterval(detik) {
    return detik % 60 === 0 ? (detik / 60) + ' menit' : detik + ' detik';
  }

  // Keterangan di bawah angka berat: status live + kapan terakhir tersimpan ke database
  function infoLive(d) {
    var dot = document.querySelector('#infoLive .live-dot');
    var teks = $('infoLiveTeks');
    if (!d.terbaru) {
      dot.className = 'live-dot kosong';
      teks.textContent = 'Menunggu data dari timbangan…';
      return;
    }
    var hidup = d.sistem.kode === 'online' || d.sistem.kode === 'simulasi';
    var t = d.sistem.kode === 'manual' ? 'Input manual ' + jam(d.terbaru.waktu)
          : hidup ? 'Live · data masuk ' + jam(d.terbaru.waktu)
          : 'Terputus · data terakhir ' + jam(d.terbaru.waktu);
    t += ' · disimpan ke database tiap ' + teksInterval(CFG.intervalSimpan);
    if (d.simpan && d.simpan.terakhir) t += ' (terakhir ' + jam(new Date(d.simpan.terakhir * 1000).toISOString()) + ')';
    dot.className = 'live-dot ' + (hidup ? 'on' : (d.sistem.kode === 'manual' ? 'kosong' : 'off'));
    teks.textContent = t;
  }

  function renderLive(d) {
    terakhirLive = d;
    var sys = d.sistem || { kode: 'kosong', label: 'Belum ada data' };
    $('sysVal').className = 'val sys sys-' + sys.kode;
    $('sysVal').textContent = sys.label;

    if (!d.terbaru) {            // belum ada data sama sekali
      kelas($('sideStock'), 'side-stock', 'kosong');
      $('sideKg').textContent = '–';
      $('sideBadge').className = 'badge kosong';
      $('sideBadge').textContent = 'BELUM ADA DATA';
      $('beratAngka').textContent = '–';
      kelas($('berat'), 'berat', 'kosong');
      $('persen').textContent = '0%';
      $('barIsi').style.width = '0%';
      kelas($('pill'), 'pill', 'kosong');
      $('pill').textContent = '–';
      kelas($('pesan'), 'msg', 'kosong');
      $('pesan').textContent = 'Belum ada data dari timbangan.';
      $('detail').textContent = 'Kirim data dari ESP32, atau isi berat manual di bawah.';
      ['rSisa', 'rRop', 'rButuh', 'rHari'].forEach(function (id) { $(id).textContent = '–'; });
      $('monitoring').textContent = 'Menunggu data…';
      infoLive(d);
      return;
    }

    var kg = d.terbaru.berat_kg, st = statusBaru(kg), h = hitungBaru(kg, d.hitung || {}), kode = st.kode;

    // Sidebar
    kelas($('sideStock'), 'side-stock', kode);
    $('sideKg').textContent = fmt(kg) + ' kg';
    $('sideBadge').className = 'badge' + (kode !== 'aman' ? ' ' + kode : '');
    $('sideBadge').textContent = st.label;

    // Berat + kapasitas
    $('beratAngka').textContent = fmt(kg);
    kelas($('berat'), 'berat', kode);
    $('persen').textContent = h.kapasitas_persen + '%';
    $('barIsi').style.width = h.kapasitas_persen + '%';
    kelas($('bar'), 'bar', kode);

    // Status
    kelas($('pill'), 'pill', kode);
    $('pill').textContent = st.label;
    kelas($('pesan'), 'msg', kode);
    $('pesan').textContent = st.pesan;
    $('detail').textContent = st.detail;

    // Hasil perhitungan
    $('rSisa').textContent = fmt(h.sisa_di_atas_safety) + ' kg';
    $('rRop').textContent = h.di_atas_rop ? fmt(h.selisih_ke_rop) + ' kg' : 'Sudah di bawah ROP';
    $('rButuh').textContent = fmt(h.kebutuhan_pengadaan) + ' kg';
    $('rHari').textContent = h.estimasi_hari === null ? '–' : fmt(h.estimasi_hari, 1) + ' hari';

    teksMonitoring(d);
    infoLive(d);
  }

  function renderRiwayat(rows) {
    terakhirRiwayat = rows;
    gambarGrafik(rows);
    isiTabel(rows);
  }

  function teksMonitoring(d) {
    var st = statusBaru(d.terbaru.berat_kg), h = hitungBaru(d.terbaru.berat_kg, d.hitung || {}), p = CFG;
    function baris(label, nilai) { return (label + '                    ').slice(0, 21) + ': ' + nilai; }
    var t = [
      'HASIL MONITORING PERSEDIAAN (' + jam(d.terbaru.waktu) + ')',
      '',
      baris('Stok saat ini', fmt(d.terbaru.berat_kg) + ' kg'),
      baris('Status', st.label),
      baris('Safety stock', fmt(p.safety) + ' kg'),
      baris('Reorder point', fmt(p.rop) + ' kg'),
      baris('Kapasitas maksimum', fmt(p.kapasitas) + ' kg'),
      baris('Sisa di atas SS', fmt(h.sisa_di_atas_safety) + ' kg'),
      baris('Kebutuhan pengadaan', fmt(h.kebutuhan_pengadaan) + ' kg'),
      baris('Estimasi hari', h.estimasi_hari === null ? '-' : fmt(h.estimasi_hari, 1) + ' hari sebelum Safety Stock'),
      baris('Perkiraan pemakaian', fmt(h.pemakaian_per_hari) + ' kg/hari'),
      baris('Rekomendasi', st.detail)
    ];
    $('monitoring').textContent = t.join('\n');
  }

  function isiTabel(rows) {
    var body = $('tabel');
    body.innerHTML = '';
    if (!rows || !rows.length) {
      body.innerHTML = '<tr><td colspan="4" class="empty">Belum ada data.</td></tr>';
      return;
    }
    rows.slice(-15).reverse().forEach(function (r) {
      var kode = kodeStatus(r.berat_kg);
      var tr = document.createElement('tr');
      var c1 = document.createElement('td'); c1.textContent = tanggal(r.created_at) + ' ' + jam(r.created_at);
      var c2 = document.createElement('td'); c2.textContent = fmt(r.berat_kg) + ' kg';
      var c3 = document.createElement('td');
      var tag = document.createElement('span');
      tag.className = 'tag' + (kode !== 'aman' ? ' ' + kode : '');
      tag.textContent = kode.toUpperCase();
      c3.appendChild(tag);
      var c4 = document.createElement('td'); c4.textContent = SUMBER[r.sumber] || r.sumber;
      tr.appendChild(c1); tr.appendChild(c2); tr.appendChild(c3); tr.appendChild(c4);
      body.appendChild(tr);
    });
  }

  /* ------------------------------------------------------------ */
  /*  Grafik riwayat (SVG buatan sendiri, tanpa library)          */
  /* ------------------------------------------------------------ */
  function gambarGrafik(rows) {
    var box = $('chart');
    if (!rows || !rows.length) {
      box.innerHTML = '<div class="empty">Belum ada data riwayat.</div>';
      return;
    }
    var W = 760, H = 320, L = 46, R = 18, T = 20, B = 34;
    var iw = W - L - R, ih = H - T - B, ymax = CFG.kapasitas, n = rows.length;

    function X(i) { return n === 1 ? L + iw / 2 : L + i * iw / (n - 1); }
    function Y(v) { v = Math.max(0, Math.min(ymax, v)); return T + ih * (1 - v / ymax); }

    var s = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Grafik riwayat berat stok">';
    s += '<defs><linearGradient id="gArea" x1="0" y1="0" x2="0" y2="1">' +
         '<stop offset="0" stop-color="#ffd93a" stop-opacity=".55"/>' +
         '<stop offset="1" stop-color="#ffd93a" stop-opacity=".05"/></linearGradient></defs>';

    // zona warna: aman / menipis / kritis
    s += '<rect x="' + L + '" y="' + Y(ymax) + '" width="' + iw + '" height="' + (Y(CFG.rop) - Y(ymax)) + '" fill="#e3f3e3" opacity=".7"/>';
    s += '<rect x="' + L + '" y="' + Y(CFG.rop) + '" width="' + iw + '" height="' + (Y(CFG.safety) - Y(CFG.rop)) + '" fill="#fdeccf" opacity=".6"/>';
    s += '<rect x="' + L + '" y="' + Y(CFG.safety) + '" width="' + iw + '" height="' + (Y(0) - Y(CFG.safety)) + '" fill="#fbdedb" opacity=".6"/>';

    // sumbu Y
    [0, ymax / 2, ymax].forEach(function (v) {
      s += '<line x1="' + L + '" x2="' + (W - R) + '" y1="' + Y(v) + '" y2="' + Y(v) + '" stroke="#d9dfda" stroke-width="1"/>';
      s += '<text x="' + (L - 8) + '" y="' + (Y(v) + 4) + '" text-anchor="end" font-size="12" fill="#6b7a70">' + fmt(v, 1) + '</text>';
    });

    // garis ROP & Safety Stock
    s += '<line x1="' + L + '" x2="' + (W - R) + '" y1="' + Y(CFG.rop) + '" y2="' + Y(CFG.rop) + '" stroke="#f08a00" stroke-width="1.5" stroke-dasharray="6 5"/>';
    var halo = ' stroke="#fdf7e4" stroke-width="3" paint-order="stroke"';
    var etiket = '<text x="' + (L + 6) + '" y="' + (Y(CFG.rop) - 6) + '" font-size="11" fill="#f08a00"' + halo + '>ROP</text>';
    s += '<line x1="' + L + '" x2="' + (W - R) + '" y1="' + Y(CFG.safety) + '" y2="' + Y(CFG.safety) + '" stroke="#d93025" stroke-width="1.5" stroke-dasharray="6 5"/>';
    etiket += '<text x="' + (L + 6) + '" y="' + (Y(CFG.safety) - 6) + '" font-size="11" fill="#d93025"' + halo + '>Safety Stock</text>';

    // area + garis
    var pts = rows.map(function (r, i) { return X(i).toFixed(1) + ',' + Y(r.berat_kg).toFixed(1); });
    s += '<polygon points="' + X(0).toFixed(1) + ',' + Y(0) + ' ' + pts.join(' ') + ' ' + X(n - 1).toFixed(1) + ',' + Y(0) + '" fill="url(#gArea)"/>';
    s += '<polyline points="' + pts.join(' ') + '" fill="none" stroke="#1f7a3d" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>';

    // titik data
    var r0 = n > 40 ? 3 : (n > 20 ? 4 : 5.5);
    rows.forEach(function (r, i) {
      var w = WARNA[kodeStatus(r.berat_kg)];
      s += '<circle cx="' + X(i).toFixed(1) + '" cy="' + Y(r.berat_kg).toFixed(1) + '" r="' + r0 + '" fill="' + w + '" stroke="#fff" stroke-width="2">' +
           '<title>' + esc(fmt(r.berat_kg) + ' kg - ' + jam(r.created_at)) + '</title></circle>';
    });

    s += etiket;   // label ROP / Safety Stock digambar paling atas supaya terbaca

    // label waktu (awal, tengah, akhir)
    var idx = n === 1 ? [0] : (n === 2 ? [0, 1] : [0, Math.floor((n - 1) / 2), n - 1]);
    idx.forEach(function (i, k) {
      var anchor = (k === 0 && idx.length > 1) ? 'start' : (k === idx.length - 1 && idx.length > 1 ? 'end' : 'middle');
      s += '<text x="' + X(i).toFixed(1) + '" y="' + (H - 10) + '" text-anchor="' + anchor + '" font-size="11" fill="#6b7a70">' + esc(jam(rows[i].created_at)) + '</text>';
    });

    box.innerHTML = s + '</svg>';
  }

  /* ------------------------------------------------------------ */
  /*  Ambil data dari server                                      */
  /*   - live    : tiap 3 detik, dari file di server (ringan)     */
  /*   - riwayat : dari Supabase, hanya saat dibuka dan ketika    */
  /*               ada data baru yang tersimpan (sekali per menit) */
  /* ------------------------------------------------------------ */
  function ambilJson(url) {
    return fetch(url + (url.indexOf('?') < 0 ? '?' : '&') + '_=' + Date.now(), { cache: 'no-store' })
      .then(function (r) {
        return r.json().catch(function () {
          throw new Error('Respons server bukan JSON. Periksa konfigurasi Vercel dan Supabase.');
        });
      })
      .then(function (d) {
        if (!d.ok) throw new Error(d.error || 'Gagal memuat data.');
        return d;
      });
  }

  var sedangMuat = false;
  var simpanTerakhir;            // penanda: kapan terakhir tersimpan ke database (dari server)
  var perluRiwayat = true;       // true = grafik/tabel perlu dimuat ulang
  var riwayatBoleh = 0;          // jeda sebelum mencoba lagi bila gagal

  function muatLive() {
    if (sedangMuat) return;
    sedangMuat = true;
    ambilJson('/api/live')
      .then(function (d) {
        setGalat('live', '');
        setGalat('simpan', d.simpan && d.simpan.gagal ? d.simpan.gagal : '');
        renderLive(d);
        var t = d.simpan ? d.simpan.terakhir : null;
        if (t !== simpanTerakhir) { simpanTerakhir = t; perluRiwayat = true; }
        if (perluRiwayat) muatRiwayat();
      })
      .catch(function (e) { setGalat('live', e.message); })
      .then(function () { sedangMuat = false; });
  }

  var sedangRiwayat = false;
  function muatRiwayat() {
    if (sedangRiwayat || Date.now() < riwayatBoleh) return;
    sedangRiwayat = true;
    perluRiwayat = false;        // bila ada data baru selagi memuat, penanda ini menyala lagi
    ambilJson('/api/data')
      .then(function (d) {
        setGalat('riwayat', '');
        renderRiwayat(d.riwayat);
      })
      .catch(function (e) {
        setGalat('riwayat', e.message);
        perluRiwayat = true;
        riwayatBoleh = Date.now() + 15000;   // coba lagi 15 detik kemudian
      })
      .then(function () { sedangRiwayat = false; });
  }

  /* ------------------------------------------------------------ */
  /*  Input manual + simulasi                                     */
  /* ------------------------------------------------------------ */
  function kirimBerat(kg, sumber) {
    return fetch('/api/manual', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ berat_kg: kg, sumber: sumber })
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) throw new Error(d.error || 'Gagal menyimpan data.');
        $('errInput').textContent = '';
        return true;
      })
      .catch(function (e) { $('errInput').textContent = e.message; return false; });
  }

  $('formBerat').addEventListener('submit', function (e) {
    e.preventDefault();
    var v = parseFloat(String($('inputBerat').value).replace(',', '.'));
    if (isNaN(v) || v < 0 || v > CFG.kapasitas) {
      $('errInput').textContent = 'Isi berat antara 0 dan ' + CFG.kapasitas + ' kg.';
      return;
    }
    $('btnHitung').disabled = true;
    kirimBerat(v, 'manual').then(function (ok) {
      $('btnHitung').disabled = false;
      if (ok) { $('inputBerat').value = ''; muatLive(); }
    });
  });

  var simTimer = null, simIdx = 0;
  var simUrut = [0, 0.5, 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5];
  function simTick() {
    kirimBerat(simUrut[simIdx], 'simulasi').then(function (ok) { if (ok) muatLive(); });
    simIdx = (simIdx + 1) % simUrut.length;
  }
  $('chkSim').addEventListener('change', function () {
    if (this.checked) { simTick(); simTimer = setInterval(simTick, 3000); }
    else { clearInterval(simTimer); simTimer = null; }
  });


  /* ------------------------------------------------------------ */
  /*  Menu samping: tampil bergantian                              */
  /* ------------------------------------------------------------ */
  var navLinks = document.querySelectorAll('.nav a');
  var sections = document.querySelectorAll('main > section');

  function bukaMenu(id) {
    if (!$(id)) id = 'dashboard';
    sections.forEach(function (s) { s.classList.toggle('active', s.id === id); });
    navLinks.forEach(function (a) { a.classList.toggle('active', a.getAttribute('data-sec') === id); });
    window.scrollTo(0, 0);
  }
  navLinks.forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      history.pushState(null, '', '#' + a.getAttribute('data-sec'));
      bukaMenu(a.getAttribute('data-sec'));
    });
  });
  window.addEventListener('popstate', function () { bukaMenu(location.hash.slice(1)); });
  bukaMenu(location.hash.slice(1) || 'dashboard');

  /* ------------------------------------------------------------ */
  /*  Kalkulator ROP:  ROP = (d × L) + SS                          */
  /* ------------------------------------------------------------ */
  function angka(id) { return parseFloat(String($(id).value).replace(',', '.')) || 0; }
  var hasilROP = null;

  function hitungROP() {
    var d = angka('k-d'), L = angka('k-L'), ss = angka('k-ss');
    var rop = d * L + ss;
    hasilROP = { ss: ss, rop: rop, d: d, L: L };

    $('o-ss').textContent = fmt(ss) + ' kg';
    $('o-rop').textContent = fmt(rop) + ' kg';
    $('o-langkah').textContent =
      'ROP = (d × L) + SS\n' +
      'ROP = (' + d + ' × ' + L + ') + ' + ss + '\n' +
      'ROP = ' + fmt(d * L) + ' + ' + fmt(ss) + '\n' +
      'ROP = ' + fmt(rop) + ' kg';

    var w = '';
    if (rop > CFG.kapasitas) w = '⚠ ROP (' + fmt(rop) + ' kg) lebih besar dari kapasitas ' + fmt(CFG.kapasitas) + ' kg.';
    else if (rop <= ss) w = '⚠ ROP harus lebih besar dari Safety Stock. Isi permintaan (d) dan lead time (L) lebih dari 0.';
    $('o-warn').textContent = w;
  }

  function tampilkanParameter() {
    $('sSS').textContent = fmt(CFG.safety) + ' kg';
    $('sROP').textContent = fmt(CFG.rop) + ' kg';
    $('pMax').textContent = fmt(CFG.kapasitas) + ' kg';
  }

  ['k-d', 'k-L', 'k-ss'].forEach(function (id) { $(id).addEventListener('input', hitungROP); });

  // isi form dengan nilai yang tersimpan, kalau ada
  try {
    var sv = JSON.parse(localStorage.getItem('cfg') || 'null');
    if (sv && sv.d !== undefined) { $('k-d').value = sv.d; $('k-L').value = sv.L; $('k-ss').value = sv.safety; }
    else { $('k-ss').value = CFG.safety; }
  } catch (e) {}
  hitungROP();
  tampilkanParameter();

  $('btn-apply').addEventListener('click', function () {
    if (!hasilROP || hasilROP.rop <= hasilROP.ss) {
      $('o-warn').textContent = '⚠ Perhitungan belum valid, ROP harus lebih besar dari Safety Stock.';
      return;
    }
    CFG.safety = hasilROP.ss;
    CFG.rop = hasilROP.rop;
    try {
      localStorage.setItem('cfg', JSON.stringify({ safety: CFG.safety, rop: CFG.rop, d: hasilROP.d, L: hasilROP.L }));
    } catch (e) {}
    tampilkanParameter();
    if (terakhirLive) renderLive(terakhirLive);          // status langsung ikut batas baru
    if (terakhirRiwayat) renderRiwayat(terakhirRiwayat); // zona di grafik ikut berubah
    history.pushState(null, '', '#dashboard');
    bukaMenu('dashboard');
  });

  $('infoRiwayat').textContent = 'Data tersimpan di database, satu titik tiap ' + teksInterval(CFG.intervalSimpan) + ' (rata-rata pembacaan selama itu).';
  muatLive();
  setInterval(muatLive, CFG.refreshMs);
})();
