<?php requi[REDACTED] __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CORNICREZ Inventory Monitoring</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<aside class="sidebar">
  <div class="brand">
    <div class="brand-logo">🌽</div>
    <div>
      <div class="brand-name">CORNICREZ</div>
      <div class="brand-sub">Inventory Monitoring</div>
    </div>
  </div>

  <nav class="nav">
    <a href="#dashboard" class="active" data-sec="dashboard"><span class="ico">📊</span>Dashboard</a>
    <a href="#perhitungan" data-sec="perhitungan"><span class="ico">🧮</span>Perhitungan Persediaan</a>
    <a href="#riwayat" data-sec="riwayat"><span class="ico">🗂️</span>Riwayat Data</a>
  </nav>

  <div class="side-stock" id="sideStock">
    <small>Stok saat ini</small>
    <strong id="sideKg">–</strong>
    <span class="badge kosong" id="sideBadge">MEMUAT</span>
  </div>

  <div class="side-foot">Load Cell + HX711 + ESP32</div>
</aside>

<main class="main">

  <div id="alert" class="alert" hidden></div>

  <!-- ===================== DASHBOARD ===================== -->
  <section id="dashboard">
    <header class="banner">
      <span class="corn l">🌽</span>
      <h1>Cornicrez Inventory Monitoring</h1>
      <p>Sistem Monitoring Persediaan Emping Jagung</p>
      <span class="corn r">🌽</span>
    </header>

    <div class="stats">
      <div class="stat">
        <div><div class="lbl">Load Cell</div><div class="val"><?= (float) LOAD_CELL_KG ?> kg</div></div>
        <div class="ico">⚖️</div>
      </div>
      <div class="stat red">
        <div><div class="lbl">Safety Stock</div><div class="val"><?= number_format(SAFETY_STOCK_KG, 2, '.', '') ?> kg</div></div>
        <div class="ico">🛡️</div>
      </div>
      <div class="stat orange">
        <div><div class="lbl">Reorder Point</div><div class="val"><?= number_format(ROP_KG, 2, '.', '') ?> kg</div></div>
        <div class="ico">🔔</div>
      </div>
      <div class="stat yellow">
        <div><div class="lbl">Status Sistem</div><div class="val sys sys-kosong" id="sysVal">Memuat…</div></div>
        <div class="ico">📡</div>
      </div>
    </div>

    <div class="grid2">
      <div class="card">
        <h2>⚖️ Berat Stok Saat Ini</h2>
        <div class="berat" id="berat"><span id="beratAngka">–</span><small>kg</small></div>
        <div class="bar-head"><span>Kapasitas Penyimpanan</span><span id="persen">0%</span></div>
        <div class="bar" id="bar"><i id="barIsi"></i></div>
        <div class="bar-foot"><span>0 kg</span><span><?= (float) KAPASITAS_KG ?> kg</span></div>
      </div>

      <div class="card">
        <h2>🚦 Status Persediaan</h2>
        <div class="pill kosong" id="pill">–</div>
        <div class="msg kosong" id="pesan">Menunggu data dari timbangan…</div>
        <p class="sub" id="detail"></p>
      </div>
    </div>
  </section>

  <!-- ===================== PERHITUNGAN ===================== -->
  <section id="perhitungan">
    <div class="card">
      <h2>✍️ Input Berat</h2>
      <form id="formBerat" class="input-row" autocomplete="off">
        <div class="field">
          <label for="inputBerat">Berat dari timbangan (kg)</label>
          <input type="number" id="inputBerat" step="0.01" min="0" max="<?= (float) KAPASITAS_KG ?>" placeholder="contoh: 3.25">
        </div>
        <button type="submit" class="btn" id="btnHitung">Hitung &amp; Perbarui</button>
        <label class="check"><input type="checkbox" id="chkSim"> Simulasi data Wokwi (tiap 3 detik)</label>
      </form>
      <div class="note-err" id="errInput"></div>
    </div>

    <div class="card">
      <h2>🧮 Hasil Perhitungan</h2>
      <div class="res">
        <div class="box r"><div class="t">Sisa di atas Safety Stock</div><div class="v" id="rSisa">–</div></div>
        <div class="box o"><div class="t">Selisih ke Reorder Point</div><div class="v" id="rRop">–</div></div>
        <div class="box g"><div class="t">Kebutuhan pengadaan (sampai penuh)</div><div class="v" id="rButuh">–</div></div>
        <div class="box b"><div class="t">Estimasi hari sebelum Safety Stock</div><div class="v" id="rHari">–</div></div>
      </div>
    </div>

    <div class="card">
      <h2>📐 Parameter Persediaan <span class="deco">🌽</span></h2>
      <div class="params">
        <div class="box r"><div class="t">Safety Stock</div><div class="v"><?= number_format(SAFETY_STOCK_KG, 2, '.', '') ?> kg</div></div>
        <div class="box o"><div class="t">Reorder Point</div><div class="v"><?= number_format(ROP_KG, 2, '.', '') ?> kg</div></div>
        <div class="box g"><div class="t">Kapasitas Maksimum</div><div class="v"><?= number_format(KAPASITAS_KG, 2, '.', '') ?> kg</div></div>
      </div>
    </div>
  </section>

  <!-- ===================== RIWAYAT ===================== -->
  <section id="riwayat">
    <div class="card">
      <h2>📈 Riwayat Berat Stok</h2>
      <div class="chart-wrap" id="chart"><div class="empty">Memuat grafik…</div></div>
    </div>

    <div class="card">
      <h2>📋 Monitoring Terakhir</h2>
      <pre class="mono" id="monitoring">Menunggu data…</pre>
    </div>

    <div class="card">
      <h2>🗒️ Data Terakhir</h2>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Waktu</th><th>Berat</th><th>Status</th><th>Sumber</th></tr></thead>
          <tbody id="tabel"><tr><td colspan="4" class="empty">Belum ada data.</td></tr></tbody>
        </table>
      </div>
    </div>
  </section>

</main>

<script>
window.CFG = <?= json_encode(array(
    'loadCell'  => LOAD_CELL_KG,
    'kapasitas' => KAPASITAS_KG,
    'safety'    => SAFETY_STOCK_KG,
    'rop'       => ROP_KG,
    'refreshMs' => 3000,
)) ?>;
</script>
<script src="assets/app.js"></script>
</body>
</html>
