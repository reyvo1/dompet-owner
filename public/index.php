<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dompet Owner</title>
<meta name="theme-color" content="#155eef">
<link rel="manifest" href="manifest.json">
<script src="vendor/chart.umd.min.js"></script>
<link rel="stylesheet" href="fonts/fonts-local.css">
<link rel="stylesheet" href="theme.css?v=f3">
</head>
<body>

<!-- ============ LOGIN ============ -->
<div id="loginView" class="login-wrap">
  <div class="auth-story"></div>
  <div class="card">
    <div style="display:flex;gap:13px;align-items:center;margin-bottom:6px">
      <div class="brand-icon" style="width:46px;height:46px;font-size:22px">💰</div>
      <div><h1>Dompet Owner</h1><div class="sub" style="margin:2px 0 0">Kas pribadi + pantau semua cabang</div></div>
    </div>
    <input id="luser" placeholder="Username" autofocus>
    <input id="lpass" type="password" placeholder="Password">
    <button onclick="login()" style="width:100%">Masuk ke Dashboard →</button>
    <div id="lerr" class="small neg" style="margin-top:10px"></div>
  </div>
</div>

<!-- ============ APP SHELL ============ -->
<div id="appView" class="shell" style="display:none">
  <div class="nav-backdrop nav-backdrop-el" onclick="document.body.classList.remove('nav-open')"></div>
  <aside class="sidebar">
    <div class="brand-wrap"><div class="brand-icon">💰</div><div><strong>Dompet Owner</strong><br><span>Konsol Keuangan</span></div></div>
    <button class="nav-item active" data-p="dashboard"><span class="ic">📊</span>Dashboard</button>
    <button class="nav-item" data-p="transaksi"><span class="ic">💳</span>Transaksi</button>
    <button class="nav-item" data-p="tagihan"><span class="ic">🧾</span>Tagihan</button>
    <button class="nav-item" data-p="target"><span class="ic">🎯</span>Target</button>
    <button class="nav-item" data-p="anggaran"><span class="ic">🧮</span>Anggaran</button>
    <button class="nav-item" data-p="berulang"><span class="ic">🔁</span>Berulang</button>
    <button class="nav-item" data-p="kas"><span class="ic">💼</span>Rekening &amp; Kas</button>
    <button class="nav-item" data-p="cabang"><span class="ic">🏪</span>Cabang &amp; Tim</button>
    <button class="nav-item" data-p="laporan"><span class="ic">📈</span>Laporan</button>
    <button class="nav-item" data-p="akuntansi"><span class="ic">📚</span>Akuntansi &amp; Pajak</button>
    <button class="nav-item" data-p="piutang"><span class="ic">📋</span>Piutang</button>
    <button class="nav-item" data-p="liab"><span class="ic">💳</span>Utang &amp; Kartu</button>
    <button class="nav-item owner-only" data-p="payroll"><span class="ic">👥</span>Gaji Staf</button>
    <button class="nav-item owner-only" data-p="pajak"><span class="ic">🏛️</span>Pajak</button>
    <button class="nav-item owner-only" data-p="assets"><span class="ic">🏭</span>Aset</button>
    <button class="nav-item owner-only" data-p="stok"><span class="ic">📦</span>Stok</button>
    <button class="nav-item owner-only" data-p="invoice"><span class="ic">🧾</span>Kwitansi</button>
        <button class="nav-item owner-only" data-p="appr"><span class="ic">🔔</span>Persetujuan</button>
    <button class="nav-item owner-only" data-p="audit"><span class="ic">📜</span>Audit Log</button>
    <button class="nav-item owner-only" data-p="panduan"><span class="ic">🧭</span>Panduan</button>
    <button class="nav-item" data-p="pengaturan"><span class="ic">⚙️</span>Pengaturan</button>
    <div class="sidebar-health">Status sistem<br><b>● Semua normal</b></div>
  </aside>

  <div class="main-area">
    <header class="topbar">
      <h1 id="pageTitle">Dashboard</h1>
      <div style="flex:1;max-width:340px;position:relative">
        <input id="gSearch" placeholder="🔍 Cari transaksi, kategori, cabang..." autocomplete="off"
          style="width:100%" oninput="globalSearch()" onfocus="if(gRes.innerHTML)gRes.style.display='block'">
        <div id="gRes" style="display:none;position:absolute;top:110%;left:0;right:0;background:var(--fd-surface);
          border:1px solid var(--fd-border);border-radius:12px;z-index:50;box-shadow:0 10px 30px rgba(0,0,0,.15);padding:8px"></div>
      </div>
      <div class="topbar-right">
        <select id="bizSwitcher" class="biz-switcher" onchange="setBizProfile(this.value)" title="Profil bisnis">
          <option value="">Semua profil</option></select>
        <button class="menu-btn theme-toggle" onclick="document.body.classList.toggle('nav-open')" title="Menu">☰</button>
        <span class="small" id="whoami"></span>
        <button class="theme-toggle" onclick="toggleTheme()" title="Ganti tema">🌙/☀️</button>
        <div class="avatar" id="avatarEl">R</div>
        <button class="logout" onclick="logout()">Keluar</button>
      </div>
    </header>

    <main class="page-content">

    <!-- DASHBOARD -->
    <section id="p-dashboard">
      <div class="hero-strip">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
          <div><div class="hero-kicker">Total Kas Dompet</div>
            <h2 id="heroKas">—</h2>
            <p id="todayStr"></p></div>
          <div style="display:flex;gap:8px">
            <button class="btn secondary" onclick="window.open('tv.php','_blank')" title="Tampilan monitor toko">📺 TV</button>
            <button class="btn secondary" onclick="openReport()">📄 Laporan PDF</button>
            <button class="btn secondary" onclick="exportCSV()">📥 Export Excel</button>
            <button class="btn" onclick="openAdd()">+ Catat Transaksi</button></div>
        </div>
      </div>
      <div id="insightBox" style="margin-bottom:16px"></div>
      <div id="dueBanner" style="margin-bottom:16px"></div>
      <div id="forecastBox"></div>
      <div id="walletStrip" style="display:flex;gap:10px;overflow-x:auto;margin-bottom:16px;padding-bottom:4px"></div>
      <div class="panel" style="margin-bottom:16px">
        <h3 style="justify-content:space-between">📅 Kalender Keuangan (45 hari)
          <span id="calTotal" class="small"></span></h3>
        <div id="calList"></div></div>
      <div class="kpi-grid" id="stats"></div>
      <div class="grid g2" style="margin-top:16px">
        <div class="panel"><h3>📈 Arus Kas 6 Bulan</h3><canvas id="chartTren"></canvas></div>
        <div class="panel"><h3>🏷️ Komposisi Pengeluaran Bulan Ini</h3>
          <div id="distStrip" style="margin-bottom:14px"></div>
          <div id="rankList"></div></div>
      </div>
      <div class="grid g2" style="margin-top:16px">
        <div class="panel"><h3>🧾 Tagihan bulan ini</h3><div id="dashBills"></div></div>
        <div class="panel"><h3>🏪 Masuk vs Keluar per cabang (bulan ini)</h3><canvas id="chartCabang"></canvas></div>
      </div>
      <div class="panel table-card" style="margin-top:16px">
        <h3 style="padding:16px 18px 0">⏱️ Transaksi Terbaru</h3>
        <div class="data-table-wrap"><table id="tblRecent"></table></div>
      </div>
    </section>

    <!-- TRANSAKSI -->
    <section id="p-transaksi" style="display:none">
      <div class="page-title" style="margin-bottom:18px"><h2>Semua Transaksi</h2><p>Filter, cari, dan kelola riwayat</p></div>
      <div class="panel">
        <div class="row2" style="margin-bottom:12px">
          <select id="fScope" onchange="loadTx()"><option value="">Semua scope</option><option value="pribadi">Pribadi</option><option value="usaha">Usaha</option></select>
          <select id="fBiz" onchange="loadTx()"><option value="">Semua cabang</option></select>
          <select id="fType" onchange="loadTx()"><option value="">Masuk & Keluar</option><option value="masuk">Masuk saja</option><option value="keluar">Keluar saja</option><option value="transfer">Transfer</option></select>
          <input id="fQ" placeholder="🔍 Cari keterangan..." oninput="loadTx()">
          <select id="fCatF" onchange="loadTx()"><option value="">Semua kategori</option></select>
          <input id="fMin" type="number" placeholder="Min Rp" onchange="loadTx()" style="max-width:110px">
          <input id="fMax" type="number" placeholder="Maks Rp" onchange="loadTx()" style="max-width:110px">
          <input id="fFrom" type="date" onchange="loadTx()">
          <input id="fTo" type="date" onchange="loadTx()">
        </div>
        <div class="table-card" style="border:none;box-shadow:none"><div class="data-table-wrap"><table id="tblTx"></table></div></div>
        <div id="txPager" style="display:flex;gap:10px;justify-content:center;margin-top:12px"></div>
      </div>
    </section>

    <!-- TAGIHAN -->
    <section id="p-tagihan" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <div class="page-title"><h2>Tagihan Rutin</h2><p>Autopay checklist — bayar sekali klik</p></div>
        <button class="btn btn-sm" onclick="openBill()">+ Tagihan</button></div>
      <div class="panel table-card"><div class="data-table-wrap"><table id="tblBills"></table></div></div>
    </section>

    <!-- TARGET -->
    <section id="p-target" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <div class="page-title"><h2>Target Tabungan</h2><p>Dana darurat, investasi, rencana beli</p></div>
        <button class="btn btn-sm btn-green" onclick="openGoal()">+ Target</button></div>
      <div class="kpi-grid" id="goalCards"></div>
    </section>

    <!-- ANGGARAN -->
    <section id="p-anggaran" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <div class="page-title"><h2>Anggaran Bulanan</h2><p id="budgetPeriod"></p></div>
        <button class="btn btn-sm" onclick="openBudget()">+ Anggaran</button></div>
      <div class="panel table-card"><div class="data-table-wrap"><table id="tblBudgets"></table></div></div>
    </section>

    <!-- BERULANG -->
    <section id="p-berulang" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <div class="page-title"><h2>Transaksi Berulang</h2><p>Gaji, sewa, langganan — sekali set, tercatat otomatis tiap jatuh tempo (cron jam 06:00)</p></div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-sm secondary" onclick="recRunNow()">⚡ Jalankan Sekarang</button>
          <button class="btn btn-sm" onclick="openRec()">+ Berulang</button></div></div>
      <div class="panel table-card"><div class="data-table-wrap"><table id="tblRec"></table></div></div>
    </section>

    <!-- CABANG -->
    <section id="p-cabang" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <div class="page-title"><h2>Cabang & Tim</h2><p>Kelola usaha dan kariawan</p></div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-sm" onclick="openBiz()">+ Cabang</button>
          <button class="btn btn-sm btn-green" onclick="openUser()">+ Kariawan</button></div></div>
      <div class="kpi-grid" id="bizCards"></div>
      <div class="panel table-card" style="margin-top:16px">
        <h3>👥 Daftar Kariawan</h3>
        <div class="data-table-wrap"><table id="tblUsers"></table></div></div>
    </section>

    <!-- LAPORAN -->
    <section id="p-laporan" style="display:none">
      <div class="page-title" style="margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div><h2>Laporan Owner vs Usaha</h2><p>Ringkasan bulanan per scope</p></div>
        <button class="btn btn-sm secondary print-btn" onclick="window.print()">🖨️ Cetak / PDF</button></div>
      <div class="grid g2" id="reportCards"></div>
      <div class="panel" style="margin-top:16px"><h3>🍩 Komposisi Pengeluaran (bulan ini)</h3><canvas id="chartCat"></canvas></div>
    </section>
    <!-- KAS / REKENING -->
    <section id="p-kas" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <div class="page-title"><h2>Rekening &amp; Kas</h2><p>Semua uangmu: tunai, bank, e-wallet. Total = kekayaan bersih.</p></div>
        <button class="btn btn-sm" onclick="openWallet()">+ Rekening</button></div>
      <div class="kpi-grid" id="walletCards"></div>
    </section>

    <!-- STOK -->
    <section id="p-stok" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <div class="page-title"><h2>Stok Barang</h2><p>Jual/beli barang otomatis mencatat kas &amp; stok</p></div>
        <button class="btn btn-sm" onclick="openProd()">+ Barang</button></div>
      <div class="panel table-card"><div class="data-table-wrap"><table id="tblProd"></table></div></div>
    </section>

    <!-- KWITANSI -->
    <section id="p-invoice" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <div class="page-title"><h2>Kwitansi / Invoice</h2><p>Buat kwitansi digital, cetak atau kirim link ke pelanggan</p></div>
        <button class="btn btn-sm" onclick="openInv()">+ Kwitansi</button></div>
      <div class="panel table-card"><div class="data-table-wrap"><table id="tblInv"></table></div></div>
    </section>

    <!-- PERSETUJUAN -->
    <section id="p-appr" style="display:none">
      <div class="page-title" style="margin-bottom:18px"><h2>Persetujuan Pengeluaran</h2><p>Permintaan kariawan menunggu keputusanmu — belum masuk kas sebelum disetujui</p></div>
      <div class="panel"><div id="apprList"></div></div>
    </section>

    <!-- AUDIT LOG -->
    <section id="p-audit" style="display:none">
      <div class="page-title" style="margin-bottom:18px"><h2>Riwayat Audit</h2><p>Siapa melakukan apa — lengkap dengan nilai sebelum &amp; sesudah</p></div>
      <div class="panel table-card"><div class="data-table-wrap"><table id="tblAudit"></table></div></div>
    </section>

    <!-- PANDUAN -->
    <section id="p-panduan" style="display:none">
      <div class="page-title" style="margin-bottom:18px"><h2>Panduan Cepat</h2><p>Langkah pertama memakai Dompet Owner</p></div>
      <div class="grid g2">
        <div class="panel"><h3 style="margin-bottom:10px">1️⃣ Isi Pengaturan</h3>
          <p class="small" style="line-height:1.8">Buka <strong>Pengaturan</strong> → isi token bot Telegram (dari @BotFather), Chat ID kamu, dan kunci Gemini. Tekan tombol tes sampai hijau.</p></div>
        <div class="panel"><h3 style="margin-bottom:10px">2️⃣ Buat Kas &amp; Kategori</h3>
          <p class="small" style="line-height:1.8">Di <strong>Rekening &amp; Kas</strong>, daftarkan dompet/kas fisik. Kategori pengeluaran bisa diatur di Pengaturan → Kategori.</p></div>
        <div class="panel"><h3 style="margin-bottom:10px">3️⃣ Tambah Cabang &amp; Tim</h3>
          <p class="small" style="line-height:1.8">Di <strong>Cabang &amp; Tim</strong>: tambah cabang, lalu akun untuk kariawan (role <em>kariawan</em>) dan pembantu setia (role <em>manajer</em> — bisa menyetujui pengeluaran cabangnya). Bagikan kode login bot ke mereka.</p></div>
        <div class="panel"><h3 style="margin-bottom:10px">4️⃣ Catat &amp; Bagikan Struk</h3>
          <p class="small" style="line-height:1.8">Setiap transaksi otomatis mendapat <strong>No. Referensi unik</strong>. Klik ikon 🔗 di tabel transaksi untuk menyalin link struk digital — bisa dibuka siapa pun tanpa login, read-only.</p></div>
        <div class="panel"><h3 style="margin-bottom:10px">5️⃣ Pantau Audit</h3>
          <p class="small" style="line-height:1.8">Menu <strong>Audit Log</strong> mencatat siapa input/menghapus apa, lengkap nilai sebelum-sesudah. Transaksi terhapus tidak hilang dari riwayat.</p></div>
        <div class="panel"><h3 style="margin-bottom:10px">6️⃣ Tutup Buku Tiap Bulan</h3>
          <p class="small" style="line-height:1.8">Setelah laporan bulanan dicek, gunakan <strong>Tutup Buku</strong> (di Pengaturan) agar periode terkunci — angka historis tidak bisa diubah orang.</p></div>
      </div>
    </section>

    <!-- PENGATURAN -->
    <section id="p-pengaturan" style="display:none">
      <div class="page-title" style="margin-bottom:18px"><h2>Pengaturan</h2><p>Koneksi Telegram &amp; AI — tersimpan aman di server (ter-mask)</p></div>

      <div class="grid g2">
        <div class="panel">
          <h3>🤖 Bot Telegram</h3>
          <div class="small" style="margin-bottom:12px">1. Buat bot di @BotFather → copy token<br>2. Tempel di bawah → Simpan → Test koneksi</div>
          <input id="sBotToken" placeholder="Token bot (123456:ABC-DEF...)">
          <input id="sChatId" placeholder="Chat ID owner (angka)">
          <input id="sBigTx" type="number" placeholder="Batas transaksi besar Rp (default 1000000)">
          <div class="row2" style="margin-top:4px">
            <label class="small" style="display:flex;gap:7px;align-items:center;padding:8px 0">
              <input type="checkbox" id="sNotifyTx" style="width:auto;margin:0"> Notif tiap transaksi</label>
            <label class="small" style="display:flex;gap:7px;align-items:center;padding:8px 0">
              <input type="checkbox" id="sNotifyBill" style="width:auto;margin:0"> Notif tagihan</label></div>
          <div style="display:flex;gap:8px;margin-top:8px">
            <button class="btn btn-sm" onclick="saveSettings()">💾 Simpan</button>
            <button class="btn btn-sm btn-green" onclick="testTG()">🔌 Test Koneksi Telegram</button></div>
          <div id="tgStatus" class="small" style="margin-top:10px"></div>
        </div>

        <div class="panel">
          <h3>✨ AI Gemini</h3>
          <div class="small" style="margin-bottom:12px">Ambil API key gratis di aistudio.google.com → "Get API key"</div>
          <input id="sGemini" placeholder="API key Gemini (AIza...)">
          <div style="display:flex;gap:8px;margin-top:4px">
            <button class="btn btn-sm" onclick="saveSettings()">💾 Simpan</button>
            <button class="btn btn-sm btn-green" onclick="testGemini()">🔌 Test Koneksi AI</button></div>
          <div id="aiStatus" class="small" style="margin-top:10px"></div>
          <hr style="border:none;border-top:1px solid var(--fd-border);margin:16px 0">
          <div class="small">💡 Panduan cepat:<br>
           • Kariawan login bot: kirim <b>/start username password</b><br>
           • Owner input via tombol + di kanan bawah<br>
           • Semua transaksi langsung menggerakkan kas dompet</div>
        </div>

        <div class="panel">
          <h3>🔒 Ganti Password</h3>
          <div class="small" style="margin-bottom:12px">Password akun owner ini (login web)</div>
          <input id="pwCur" type="password" placeholder="Password sekarang">
          <input id="pwNew" type="password" placeholder="Password baru (min. 6 karakter)">
          <input id="pwNew2" type="password" placeholder="Ulangi password baru">
          <button class="btn btn-sm" style="margin-top:4px" onclick="changePassword()">🔑 Ganti Password</button>
          <div id="pwStatus" class="small" style="margin-top:10px"></div>
        </div>

        <div class="panel">
          <h3>🔒 Tutup Buku Bulanan</h3>
          <div class="small" style="margin-bottom:12px">Bulan yang dikunci tidak bisa ditambah/hapus transaksinya. Bulan berjalan tidak bisa dikunci.</div>
          <div class="row2"><input id="cbPeriod" placeholder="YYYY-MM (misal 2026-07)"><button class="btn btn-sm" onclick="closeBook()">🔒 Kunci</button></div>
          <div id="cbList" class="small" style="margin-top:12px"></div>
          <div id="cbStatus" class="small" style="margin-top:8px"></div>
        </div>
      </div>

      <div class="panel" style="margin-top:16px">
        <h3>🧠 Aturan Cerdas (auto-kategori)</h3>
        <div class="small" style="margin-bottom:12px">Kalau keterangan transaksi mengandung kata kunci, kategorinya terisi otomatis. Contoh: kata "gas" → Operasional Cabang.</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
          <input id="rlPat" placeholder="Kata kunci (misal: token)" style="flex:1;min-width:130px;margin:0">
          <select id="rlCat" style="width:auto;margin:0;max-width:170px">${''}</select>
          <select id="rlBiz" style="width:auto;margin:0"><option value="">Semua</option></select>
          <button class="btn btn-sm" onclick="ruleAdd()">+ Aturan</button></div>
        <div id="ruleList" class="rank-list"></div>
      </div>

      <div class="panel" style="margin-top:16px">
        <h3>🏷️ Kategori</h3>
        <div class="small" style="margin-bottom:12px">Kategori &amp; warnanya dipakai di semua form dan grafik. Warna bisa diganti — langsung tersimpan di database.</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
          <input id="catNewName" placeholder="Nama kategori baru" style="flex:1;min-width:150px;margin:0">
          <select id="catNewKind" style="width:auto;margin:0"><option value="keluar">Pengeluaran</option><option value="masuk">Pemasukan</option><option value="both">Keduanya</option></select>
          <input id="catNewColor" type="color" value="#7f8b99" style="width:52px;padding:4px;height:44px;margin:0;cursor:pointer">
          <button class="btn btn-sm" onclick="catAdd()">+ Tambah</button></div>
        <div id="catList" class="rank-list"></div>
      </div>

      <div class="panel" style="margin-top:16px">
        <h3>🗄️ Backup Database</h3>
        <div class="small" style="margin-bottom:12px">Backup otomatis tiap hari jam 21:30 (disimpan 14 terakhir di folder <b>backups/</b>). Klik tombol untuk backup manual sekarang.</div>
        <button class="btn btn-sm btn-green" onclick="runBackup()">🗄️ Backup Sekarang</button>
        <div id="backupStatus" class="small" style="margin-top:10px"></div>
      </div>
    </section>
    <!-- AKUNTANSI -->
    <section id="p-akuntansi" style="display:none">
      <div class="page-title" style="margin-bottom:18px"><h2>Akuntansi &amp; Pajak</h2><p>Double-entry, laba rugi, neraca, pajak otomatis</p></div>
      <div style="display:flex;gap:8px;margin-bottom:16px">
        <input id="accPeriod" type="month" style="width:auto">
        <button class="btn btn-sm" onclick="renderAkuntansi()">Tampilkan</button></div>
      <div class="grid g2" id="plCards"></div>
      <div class="panel" style="margin-top:16px"><h3>📚 Laba Rugi (bulan terpilih)</h3>
        <table id="tblPL"></table></div>
      <div class="panel" style="margin-top:16px"><h3>⚖️ Neraca (sejauh ini)</h3>
        <table id="tblBS"></table></div>
      <div class="panel" style="margin-top:16px"><h3>💵 Arus Kas (bulan terpilih)</h3>
        <table id="tblCF"></table></div>
      <div class="panel" style="margin-top:16px"><h3>🧾 Pajak 6 Bulan (PBJT &amp; PPh UMKM)
        <span id="taxTotal" class="small"></span></h3><canvas id="chartTax" style="max-height:220px"></canvas></div>
      <div class="panel table-card" style="margin-top:16px"><h3 style="padding:16px 18px 0">📚 Neraca Saldo (percobaan)</h3>
        <div class="data-table-wrap"><table id="tblTrial"></table></div></div>

      <div class="panel" style="margin-top:16px">
        <h3>🧾 Aturan Pajak</h3>
        <div class="small" style="margin-bottom:10px">Transaksi USAHA tanpa aturan spesifik → status UNRESOLVED (perlu review). PBJT dihitung dari harga bruto (sudah termasuk pajak).</div>
        <table id="tblTaxRules"></table>
        <button class="btn btn-sm" style="margin-top:12px" onclick="openTaxRule()">+ Aturan Pajak</button></div>

      <div class="panel" style="margin-top:16px;border-color:#f1b34b66">
        <h3>⚠️ Pajak Belum Terselesaikan (UNRESOLVED)</h3>
        <table id="tblUnres"></table></div>
    </section>

    <!-- PIUTANG -->
    <section id="p-piutang" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <div class="page-title"><h2>Piutang (jualan tempo)</h2><p>Tercatat sebagai piutang dulu — bukan kas. Kas baru masuk saat dilunasi.</p></div>
        <button class="btn btn-sm" onclick="openRecv()">+ Piutang Baru</button></div>
      <div class="panel table-card"><div class="data-table-wrap"><table id="tblRecv"></table></div></div>
      <h3 style="margin:20px 0 10px;font-size:15px">⏳ Umur Piutang (Aging)</h3>
      <div id="recvAging"></div>
      <div class="panel table-card" style="margin-top:12px"><div class="data-table-wrap"><table id="tblAging"></table></div></div>
    </section>

    <!-- LIABILITAS -->
    <section id="p-liab" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <div class="page-title"><h2>Utang &amp; Kartu</h2><p>Kartu kredit, PayLater, pinjaman. Belanja pakai ini TIDAK mengurangi kas — kas baru berkurang saat bayar tagihan.</p></div>
        <button class="btn btn-sm" onclick="openLiab()">+ Utang/Kartu Baru</button></div>
      <div class="kpi-grid" id="liabCards"></div>
    </section>

    <!-- GAJI / PAYROLL -->
    <section id="p-payroll" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <div class="page-title"><h2>Gaji Staf (Payroll)</h2><p>Kelola daftar staf &amp; gajian bulanan. Bayar gaji = kas keluar otomatis tercatat.</p></div>
        <div>
          <button class="btn btn-sm" onclick="openEmp()">+ Staf Baru</button>
          <button class="btn btn-sm btn-green" onclick="payGenerate()">⚡ Buat Gajian Bulan Ini</button>
        </div>
      </div>
      <div class="panel table-card"><div class="data-table-wrap"><table id="tblPayroll"></table></div></div>
    </section>

    <!-- PAJAK -->
    <section id="p-pajak" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <div class="page-title"><h2>Pajak Bulanan</h2><p>Rekap pajak per periode + reminder jatuh tempo. Sinkron = tarik otomatis dari mesin pajak transaksi.</p></div>
        <div>
          <button class="btn btn-sm" onclick="taxSync()">🔄 Sinkron dari Transaksi</button>
          <button class="btn btn-sm" onclick="openTax()">+ Entri Manual</button>
        </div>
      </div>
      <div id="taxDueBanner"></div>
      <div class="panel table-card"><div class="data-table-wrap"><table id="tblTax"></table></div></div>
    </section>

    <!-- ASET & PENYUSUTAN -->
    <section id="p-assets" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <div class="page-title"><h2>Aset &amp; Penyusutan</h2><p>Barang bernilai (kendaraan, mesin, peralatan). Penyusutan otomatis masuk jurnal bulanan — TIDAK mengurangi kas.</p></div>
        <div>
          <button class="btn btn-sm btn-green" onclick="depRun()">⚡ Susutkan Bulan Ini</button>
          <button class="btn btn-sm" onclick="openAsset()">+ Aset Baru</button>
          <button class="btn btn-sm" onclick="openImport()">📥 Import CSV Bank</button>
        </div>
      </div>
      <div class="panel table-card"><div class="data-table-wrap"><table id="tblAssets"></table></div></div>
    </section>
    </main>
  </div>
</div>

<div class="fabrow">
  <div class="fab2" onclick="openAI()" title="Tanya AI">🤖</div>
  <div class="fab" onclick="openAdd()" title="Transaksi baru">+</div>
</div>
<div id="modal"></div>
<div id="toast"></div>

<script>
let meta=null, summary=null, view='dashboard', charts={};
const $=id=>document.getElementById(id);
const fmt=n=>'Rp '+Number(n).toLocaleString('id-ID');
function toast(m,err){const t=$('toast');t.textContent=m;t.classList.toggle('err',!!err);
  t.style.display='block';setTimeout(()=>t.style.display='none',2500);}
async function api(a,b,qs){
  let url='api.php?action='+a;
  if(qs)url+='&'+new URLSearchParams(qs).toString();
  const r=await fetch(url,{method:'POST',
    headers:{'Content-Type':'application/json'},body:JSON.stringify(b||{})});return r.json();}
const esc=s=>String(s??'').replace(/[<>&"]/g,c=>({'<':'&lt','>':'&gt','&':'&amp','"':'&quot'}[c]||c));
/* warna identitas kategori — DINAMIS dari DB (categories.color) */
function catColor(name){
  const c=(meta?.categories||[]).find(x=>x.name===name);
  if(c?.color)return c.color;
  return 'var(--fd-cat-lainnya)';
}
/* palet utk scope/cabang (bukan kategori DB) — konsisten dgn tema */
const SCOPE_PALETTE=['#155eef','#16836f','#b86b00','#6675d8','#0891b2','#c54c5a','#8568d8','#38a58c','#d76aa8','#4ba8c7'];
const scopeColor=i=>SCOPE_PALETTE[i%SCOPE_PALETTE.length];
function toggleTheme(){document.documentElement.classList.toggle('dark');renderPage();}

async function login(){const d=await api('login',{username:luser.value,password:lpass.value});
  if(d.error){$('lerr').textContent=d.error;return;} boot();}
async function logout(){await api('logout');location.reload();}

let ME=null; // profil user yang login (owner/kariawan)
async function boot(){
  const me=await api('me');
  if(me.error)return;
  ME=me.user;
  $('whoami').textContent=me.user.name+' • '+me.user.role.toUpperCase();
  $('avatarEl').textContent=(me.user.name[0]||'R').toUpperCase();
  $('loginView').style.display='none';$('appView').style.display='flex';
  $('todayStr').textContent=new Date().toLocaleDateString('id-ID',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
  if(ME.role==='kariawan'){ setupKariawan(); return; }
  if(ME.role==='manajer'){ setupManajer(); return; }
  [meta,summary]=await Promise.all([api('meta'),api('summary',null,summaryQ())]);
  if(summary.error)return;
  fillBizSelect();fillBizSwitcher();applyThemeCharts();renderPage();
}
/* ===== MODE KARIAWAN: hanya lihat cabangnya sendiri ===== */
let BR=null;
function setupKariawan(){
  // sembunyikan menu khusus owner
  ['tagihan','target','anggaran','berulang','kas','cabang','laporan','pengaturan'].forEach(p=>{
    const b=document.querySelector(`.nav-item[data-p="${p}"]`); if(b)b.style.display='none';});
  document.querySelector('.nav-item[data-p="transaksi"] .ic').textContent='🏪';
  document.querySelector('.nav-item[data-p="transaksi"]').lastChild.textContent=' Cabang Saya';
  applyThemeCharts();
  renderPage();
}
/* ===== MODE MANAJER: cabangnya + approval + audit ringan ===== */
function setupManajer(){
  // tanpa akses owner-only & pengaturan
  ['stok','invoice','audit','cabang','pengaturan','kas'].forEach(p=>{
    const b=document.querySelector(`.nav-item[data-p="${p}"]`); if(b)b.style.display='none';});
  // tampilkan approval (sudah difilter per cabang di backend)
  document.querySelector('.nav-item[data-p="appr"]').classList.remove('owner-only');
  document.querySelector('.nav-item[data-p="appr"]').style.display='';
  document.querySelector('.nav-item[data-p="transaksi"] .ic').textContent='🏪';
  document.querySelector('.nav-item[data-p="transaksi"]').lastChild.textContent=' Cabang Saya';
  applyThemeCharts();
  renderPage();
}
async function renderBranch(){
  const d=await api('summary_branch');
  if(d.error){$('tblRecent').innerHTML=`<tr><td class=empty>${esc(d.error)}</td></tr>`;return;}
  BR=d;
  $('heroKas').textContent=fmt(d.today.selisih);
  $('pageTitle').textContent='Cabang: '+(d.branch?.name||'');
  // kartu ringkas
  $('stats').innerHTML=`
   <div class="kpi-card"><div class="kpi-head"><div class="kpi-label">Hari Ini — Masuk</div><div class="kpi-icon green">📥</div></div>
     <div class="kpi-value pos">${fmt(d.today.masuk)}</div></div>
   <div class="kpi-card"><div class="kpi-head"><div class="kpi-label">Hari Ini — Keluar</div><div class="kpi-icon red">📤</div></div>
     <div class="kpi-value neg">${fmt(d.today.keluar)}</div></div>
   <div class="kpi-card"><div class="kpi-head"><div class="kpi-label">Bulan Ini — Masuk</div><div class="kpi-icon blue">📈</div></div>
     <div class="kpi-value brandc">${fmt(d.bulan_bulan_ini.masuk)}</div></div>
   <div class="kpi-card"><div class="kpi-head"><div class="kpi-label">Bulan Ini — Laba</div><div class="kpi-icon violet">💰</div></div>
     <div class="kpi-value ${d.bulan_bulan_ini.laba>=0?'pos':'neg'}">${fmt(d.bulan_bulan_ini.laba)}</div></div>`;
  renderCharts(d.tren,[]);
  charts.c?.destroy();
  // tabel recent
  let h='<thead><tr><th>Tanggal</th><th>Jenis</th><th>Rincian</th><th style="text-align:right">Jumlah</th></tr></thead><tbody>';
  for(const r of d.recent){
    h+=`<tr><td>${r.tx_date}</td><td><span class="badge b-${r.type}">${r.type}</span></td>
      <td>${esc(r.description)}</td>
      <td style="text-align:right;font-weight:700" class="${r.type==='masuk'?'pos':'neg'}">${r.type==='masuk'?'+':'−'}${fmt(r.amount).slice(3)}</td></tr>`;
  }
  $('tblRecent').innerHTML=h+'</tbody>';
}
function fillBizSelect(){
  $('fBiz').innerHTML='<option value="">Semua cabang</option>'+meta.businesses.map(b=>`<option value="${b.id}">${b.icon?esc(b.icon)+' ':''}${esc(b.name)}</option>`).join('');
}
/* ===== PENCARIAN GLOBAL ===== */
let gsTimer;
function globalSearch(){
  clearTimeout(gsTimer);
  gsTimer=setTimeout(async()=>{
    const q=$('gSearch').value.trim();
    const el=$('gRes');
    if(q.length<2){el.style.display='none';return;}
    const d=await api('global_search',null,{q});
    if(d.error){el.style.display='none';return;}
    let h='';
    if(d.transactions.length)h+='<div class="small" style="font-weight:700;padding:4px 6px">Transaksi</div>'+d.transactions.map(t=>
      `<div style="padding:6px;display:flex;justify-content:space-between;gap:8px;cursor:pointer;border-radius:8px" onclick="gotoPage('transaksi');fQ.value='${esc(t.description).replace(/'/g,'')}';loadTx();gSearch.value='';gRes.style.display='none'">
        <span>${t.tx_date} • ${esc((t.description||'').slice(0,34))}</span>
        <b class="${t.type==='masuk'?'pos':'neg'}">${fmt(t.amount)}</b></div>`).join('');
    if(d.categories.length)h+='<div class="small" style="font-weight:700;padding:4px 6px;margin-top:4px">Kategori</div>'+d.categories.map(c=>
      `<div style="padding:6px;cursor:pointer;border-radius:8px" onclick="gotoPage('transaksi');fCatF.value='${esc(c.name)}';loadTx();gSearch.value='';gRes.style.display='none'">🏷️ ${esc(c.name)}</div>`).join('');
    if(d.businesses.length)h+='<div class="small" style="font-weight:700;padding:4px 6px;margin-top:4px">Cabang</div>'+d.businesses.map(b=>
      `<div style="padding:6px;cursor:pointer;border-radius:8px" onclick="setBizProfile('${b.id}');gSearch.value='';gRes.style.display='none'">${b.icon||'🏪'} ${esc(b.name)}</div>`).join('');
    el.innerHTML=h||'<div style="padding:8px" class="small">Tidak ada hasil</div>';
    el.style.display='block';
  },250);
}
document.addEventListener('click',e=>{const g=$('gRes');if(g&&!e.target.closest('#gSearch')&&!e.target.closest('#gRes'))g.style.display='none';});

/* ===== MULTI-PROFIL BISNIS: switcher di topbar ===== */
let BIZ_PROFILE=''; // '' = semua profil
function fillBizSwitcher(){
  const el=$('bizSwitcher');if(!el)return;
  el.innerHTML='<option value="">🏬 Semua profil (usaha+pribadi)</option><option value="pribadi">👤 Buku Pribadi</option>'+meta.businesses.map(b=>
    `<option value="${b.id}">${b.icon?esc(b.icon):'🏪'} ${esc(b.name)}</option>`).join('');
  el.value=BIZ_PROFILE;
}
function gotoPage(p){
  const btn=document.querySelector('.nav-item[data-p="'+p+'"]');
  if(btn)btn.click();else{view=p;renderPage();}
}
function setBizProfile(v){
  BIZ_PROFILE=v||'';
  // selaraskan filter cabang di halaman transaksi
  if($('fBiz'))$('fBiz').value=BIZ_PROFILE;
  meta && renderPage();
}
function summaryQ(){return BIZ_PROFILE?{biz:BIZ_PROFILE}:null;}
function applyThemeCharts(){
  const cs=getComputedStyle(document.documentElement);
  Chart.defaults.color=cs.getPropertyValue('--fd-muted').trim()||'#566579';
  Chart.defaults.borderColor=cs.getPropertyValue('--fd-border').trim()+'88';
  Chart.defaults.font.family="'Plus Jakarta Sans',sans-serif";
}

/* ===== NAV ===== */
document.querySelectorAll('.nav-item').forEach(b=>b.addEventListener('click',()=>{
  document.querySelectorAll('.nav-item').forEach(x=>x.classList.remove('active'));
  b.classList.add('active');view=b.dataset.p;
  document.body.classList.remove('nav-open');
  $('pageTitle').textContent=b.textContent.trim();
  renderPage();
}));
function renderPage(){
  document.querySelectorAll('.nav-item.owner-only').forEach(b=>b.style.display=(ME&&(ME.role==='owner'||(ME.role==='manajer'&&b.dataset.p==='appr')))?'':'none');
  ['dashboard','transaksi','tagihan','target','anggaran','berulang','kas','cabang','laporan','akuntansi','piutang','liab','payroll','pajak','assets','appr','audit','stok','invoice','pengaturan','panduan'].forEach(p=>{
    const el=$('p-'+p);if(el)el.style.display=p===view?'':'none';});
if($('fCatF')&&!$('fCatF').options.length){$('fCatF').innerHTML='<option value="">Semua kategori</option>'+(meta?.categories||[]).map(c=>`<option>${esc(c.name)}</option>`).join('');}
    ({dashboard:(ME&&(ME.role==='kariawan'||ME.role==='manajer'))?renderBranch:renderDash,transaksi:loadTx,tagihan:loadBills,target:loadGoals,
    anggaran:loadBudgets,berulang:loadRec,kas:renderWallets,cabang:renderCabang,laporan:renderReport,
    akuntansi:renderAkuntansi,piutang:()=>{loadRecv();loadAging();},liab:loadLiab,payroll:loadPayroll,pajak:loadTax,assets:loadAssets,appr:loadAppr,audit:loadAudit,stok:loadProd,invoice:loadInv,
    pengaturan:()=>{loadSettings();renderCatManager();loadRuleManager();loadClosedPeriods();},panduan:()=>{}}[view]||(()=>{}))();
}

/* ===== ATURAN CERDAS (smart rules) ===== */
async function loadRuleManager(){
  const d=await api('rule_list');const el=$('ruleList');if(!el||d.error)return;
  // isi dropdown kategori & bisnis
  if($('rlCat')&&!$('rlCat').options.length){
    $('rlCat').innerHTML=meta.categories.map(c=>`<option>${esc(c.name)}</option>`).join('');
    $('rlBiz').innerHTML='<option value="">Semua</option>'+meta.businesses.map(b=>`<option value="${b.id}">${esc(b.name)}</option>`).join('');
  }
  el.innerHTML=d.rows.length?d.rows.map(r=>`
    <div class="rank-row"><div class="rank-copy">
      <div class="rank-index">🔑</div>
      <div class="rank-name"><strong>"${esc(r.pattern)}" → ${esc(r.cat)}</strong>
        <span>${r.biz?esc(r.biz):'semua profil'} • dipakai ${r.hits}x</span></div>
      <button class="btn btn-sm btn-red" onclick="ruleDel(${r.id})">🗑️</button>
    </div></div>`).join('')
    :'<div class="empty">Belum ada aturan — tambahkan kata kunci di atas</div>';
}
async function ruleAdd(){
  const pattern=rlPat.value.trim();if(!pattern)return toast('Isi kata kuncinya dulu',1);
  const d=await api('rule_add',{pattern,category:rlCat.value,business_id:rlBiz.value});
  if(d.ok){toast('Aturan tersimpan 🧠');rlPat.value='';loadRuleManager();}else toast(d.error,1);
}
async function ruleDel(id){if(!confirm('Hapus aturan ini?'))return;
  const d=await api('rule_delete',{id});d.ok?(toast('Terhapus'),loadRuleManager()):toast(d.error);}

/* ===== LIABILITAS (kartu kredit/PayLater/pinjaman) ===== */
let LIAB=[];
async function loadLiab(){
  const d=await api('liab_list');if(d.error)return;LIAB=d.rows;
  const KIND={credit_card:'💳 Kartu Kredit',paylater:'🛍️ PayLater',loan:'🏦 Pinjaman'};
  $('liabCards').innerHTML=d.rows.length?d.rows.map(l=>{
    const pct=l.limit_amount>0?Math.min(100,(l.outstanding/l.limit_amount*100)):0;
    return `<div class="kpi-card">
     <div class="kpi-head"><div class="kpi-label">${KIND[l.kind]||l.kind} • ${esc(l.name)}</div>
       <div class="kpi-icon red">💳</div></div>
     <div class="kpi-value neg">${fmt(l.outstanding)}</div>
     <div class="kpi-note">${l.biz?esc(l.biz)+' • ':''}terpakai ${pct.toFixed(0)}%${l.available!==null?' • sisa limit '+fmt(l.available):''}</div>
     ${l.limit_amount>0?`<div class="pbar ${pct>80?'over':''}"><div style="width:${pct}%"></div></div>`:''}
     ${l.current_bill!==undefined?`<div class="kpi-note" style="margin-top:8px">Tagihan berjalan <b>${fmt(l.current_bill)}</b> (min. ${fmt(l.min_payment)})<br>jatuh tempo ${l.bill_due}</div>`:''}
     <div style="display:flex;gap:6px;margin-top:10px">
       <button class="btn btn-sm btn-red" onclick="openCharge(${l.id})">💸 Pakai</button>
       ${l.outstanding>0?`<button class="btn btn-sm btn-green" onclick="openPay(${l.id})">✅ Bayar</button>`:''}
       <button class="btn btn-sm secondary" onclick="liabDel(${l.id})">🗑️</button></div>
    </div>`;
  }).join('')
  :'<div class="panel empty">Belum ada — klik "+ Utang/Kartu Baru". Ingat: belanja pakai kartu kredit TIDAK mengurangi kas dompet.</div>';
}
function openLiab(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">💳 Utang / Kartu Baru</h1>
   <input id="lbname" placeholder="Nama (misal: Kartu Kredit BCA)">
   <select id="lbkind"><option value="credit_card">Kartu Kredit</option><option value="paylater">PayLater / BNPL</option><option value="loan">Pinjaman / Kredit</option></select>
   <div class="row2"><input id="lblimit" type="number" placeholder="Limit (Rp, 0=none)">
   <input id="lbout" type="number" placeholder="Outstanding awal (Rp)"></div>
   <div class="row2"><input id="lbstmt" type="number" min="1" max="28" placeholder="Tgl statement (1-28)">
   <input id="lbdue" type="number" min="1" max="28" placeholder="Tgl jatuh tempo"></div>
   <button onclick="saveLiab()">Simpan</button><div id="lberr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function saveLiab(){
  const d=await api('liab_add',{name:lbname.value,kind:lbkind.value,
    limit_amount:+(lblimit.value||0),outstanding:+(lbout.value||0),
    statement_day:lbstmt.value||null,due_day:lbdue.value||null,min_pay_pct:10});
  if(d.error){lberr.textContent=d.error;return;}
  closeModal();toast('Tersimpan 💳');loadLiab();
}
function openCharge(id){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">💸 Pakai ${(LIAB.find(x=>x.id===id)||{}).name||''}</h1>
   <input id="lcamt" type="number" placeholder="Jumlah (Rp)">
   <input id="lcdesc" placeholder="Untuk apa? (misal: stok bulanan)">
   <button onclick="doCharge(${id})">Catat Pemakaian</button>
   <div class="small" style="margin-top:10px">Kas dompet TIDAK berkurang. Outstanding bertambah.</div></div></div>`;
}
async function doCharge(id){
  const d=await api('liab_charge',{liability_id:id,amount:+lcamt.value,description:lcdesc.value});
  if(d.error){toast(d.error,1);return;}
  closeModal();toast('Terpakai — outstanding naik');loadLiab();
}
function openPay(id){
  const l=LIAB.find(x=>x.id===id)||{};
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">✅ Bayar ${esc(l.name||'')}</h1>
   <div class="small" style="margin-bottom:10px">Outstanding: <b>${fmt(l.outstanding)}</b>${l.min_payment?` • minimum: <b>${fmt(l.min_payment)}</b>`:''}</div>
   <input id="lpamt" type="number" value="${l.outstanding||''}" placeholder="Jumlah bayar (Rp)">
   <select id="lpwallet">${(summary?.wallets||[]).map(w=>`<option value="${w.id}" ${w.is_default?'selected':''}>${esc(w.name)}</option>`).join('')}</select>
   <button onclick="doPay(${id})">Bayar dari Kas</button>
   <div class="small" style="margin-top:10px">Kas dompet berkurang & outstanding turun. Masuk jurnal akuntansi otomatis.</div></div></div>`;
}
async function doPay(id){
  const d=await api('liab_pay',{liability_id:id,amount:+lpamt.value,wallet_id:lpwallet.value});
  if(d.error){toast(d.error,1);return;}
  closeModal();toast(d.remaining>0?('Dibayar sebagian ('+fmt(d.paid)+')'):'Lunas dibayar ✅');
  loadLiab();
}
async function liabDel(id){if(!confirm('Tutup/hapus kartu ini dari daftar?'))return;
  const d=await api('liab_delete',{id});d.ok?(toast('Dihapus'),loadLiab()):toast(d.error);}

/* ===== KALENDER KEUANGAN 45 HARI ===== */
async function loadCalendar(){
  const d=await api('calendar',null,summaryQ());const el=$('calList');if(!el||d.error)return;
  $('calTotal').innerHTML=d.count?`${d.count} kewajiban • total ${fmt(d.total_keluar)}`:'';
  if(!d.rows.length){el.innerHTML='<div class="empty">Aman! Tidak ada kewajiban dalam 45 hari ke depan 🎉</div>';return;}
  const today=d.today;
  el.innerHTML='<div class="rank-list">'+d.rows.map(r=>{
    const dt=new Date(r.date+'T00:00:00');
    const tgl=dt.toLocaleDateString('id-ID',{weekday:'short',day:'numeric',month:'short'});
    const days=Math.round((new Date(r.date)-new Date(today))/86400000);
    const when=r.overdue?'<span class="badge b-keluar">TERLAMBAT</span>'
      :days===0?'<span class="badge b-wait">HARI INI</span>'
      :`<span class="small">${days} hari lagi</span>`;
    return `<div class="rank-row"><div class="rank-copy">
      <div class="rank-index" style="font-size:13px;background:var(--fd-surface-2)">${r.icon}</div>
      <div class="rank-name"><strong>${esc(r.title)} <span class="small" style="font-weight:400">• ${tgl}</span></strong>
        <span>${when}</span></div>
      <div class="rank-value ${r.flow==='masuk'?'pos':r.flow==='goal'?'brandc':'neg'}">${r.flow==='masuk'?'+':''}${fmt(r.amount)}</div>
    </div></div>`;
  }).join('')+'</div>';
}


/* ===== STOK BARANG ===== */
async function loadProd(){
  const [d,m]=await Promise.all([api('prod_list'),api('meta')]);
  if(d.error)return;
  PRODBIZ=m.businesses||[];
  let h='<thead><tr><th>Barang</th><th>Cabang</th><th style="text-align:right">H. Beli</th><th style="text-align:right">H. Jual</th><th style="text-align:right">Stok</th><th>Aksi</th></tr></thead><tbody>';
  for(const r of d.rows){
    const low=(float(r.stock)<=float(r.min_stock))&&float(r.min_stock)>0;
    h+=`<tr><td><b>${esc(r.name)}</b>${r.sku?`<br><span class="small">${esc(r.sku)}</span>`:''}</td>
     <td class="small">${esc(r.biz||'pribadi')}</td>
     <td style="text-align:right" class="small">${fmt(r.cost_price)}</td>
     <td style="text-align:right" class="small">${fmt(r.sell_price)}</td>
     <td style="text-align:right"><b class="${low?'neg':''}">${r.stock} ${esc(r.unit)}</b>${low?'<br><span class="small neg">menipis!</span>':''}</td>
     <td style="white-space:nowrap">
       <button class="btn btn-sm btn-green" onclick="moveStock(${r.id},'in','${esc(r.name)}')">📥 Masuk</button>
       <button class="btn btn-sm" onclick="moveStock(${r.id},'out','${esc(r.name)}')">📤 Keluar</button>
       <button class="btn btn-sm btn-red" onclick="delProd(${r.id})">✕</button></td></tr>`;
  }
  $('tblProd').innerHTML=h+'</tbody>'||'<tr><td colspan=6 class=empty>Belum ada barang</td></tr>';
}
const float=v=>parseFloat(v)||0;
function openProd(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">📦 Barang Baru</h1>
    <input id="pname" placeholder="Nama barang">
    <div class="row2"><input id="psku" placeholder="SKU (opsional)"><input id="punit" placeholder="satuan" value="pcs"></div>
    <div class="row2"><input id="pcost" type="number" placeholder="Harga beli"><input id="psell" type="number" placeholder="Harga jual"></div>
    <div class="row2"><input id="pstock" type="number" placeholder="Stok awal"><input id="pmin" type="number" placeholder="Stok minimum"></div>
    <select id="pbiz"><option value="">— Pribadi —</option>${(PRODBIZ||meta.businesses).map(b=>`<option value="${b.id}">${esc(b.name)}</option>`).join('')}</select>
    <button onclick="saveProd()">Simpan</button><div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function saveProd(){
  const d=await api('prod_save',{name:pname.value,sku:psku.value,unit:punit.value||'pcs',
    cost_price:+(pcost.value||0),sell_price:+(psell.value||0),
    stock:+(pstock.value||0),min_stock:+(pmin.value||0),business_id:pbiz.value||null});
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Barang tersimpan ✅');loadProd();
}
async function delProd(id){if(!confirm('Hapus barang ini?'))return;
  const d=await api('prod_delete',{id});if(d.ok){toast('Dihapus');loadProd();}else toast(d.error);}
function moveStock(id,dir,name){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">${dir==='in'?'📥 Stok Masuk':'📤 Stok Keluar'} — ${esc(name)}</h1>
    <input id="sqty" type="number" placeholder="Jumlah">
    ${dir==='out'?'<label class="small" style="display:flex;gap:7px;align-items:center;padding:6px 0"><input type="checkbox" id="smktx" checked style="width:auto"> Catat juga sebagai penjualan (masuk kas)</label>':'<label class="small" style="display:flex;gap:7px;align-items:center;padding:6px 0"><input type="checkbox" id="smktx" checked style="width:auto"> Catat juga sebagai pembelian (keluar kas)</label>'}
    <button onclick="doMove(${id},'${dir}')">Simpan</button><div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function doMove(id,dir){
  if(!sqty.value){merr.textContent='Isi jumlah';return;}
  const d=await api('stock_move',{product_id:id,direction:dir,qty:+sqty.value,make_transaction:smktx.checked});
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Stok diperbarui ✅');loadProd();renderPage();
}

/* ===== KWITANSI ===== */
async function loadInv(){
  const d=await api('inv_list');if(d.error)return;
  let h='<thead><tr><th>No.</th><th>Pelanggan</th><th style="text-align:right">Jumlah</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
  for(const r of d.rows){
    h+=`<tr><td class="small"><b>${esc(r.number)}</b><br>${(r.created_at||'').slice(0,10)}</td>
     <td><b>${esc(r.customer_name)}</b>${r.biz?`<br><span class="small">${esc(r.biz)}</span>`:''}</td>
     <td style="text-align:right;font-weight:700">${fmt(r.amount)}</td>
     <td>${r.status==='paid'?'<span class="badge b-done">LUNAS</span>':r.status==='void'?'<span class="badge b-keluar">BATAL</span>':'<span class="badge b-wait">BELUM</span>'}</td>
     <td style="white-space:nowrap"><a href="invoice.php?id=${r.id}" target="_blank" class="btn btn-sm secondary" style="text-decoration:none">🖨️ Cetak</a>
       ${r.status==='unpaid'?`<button class="btn btn-sm btn-green" onclick="payInv(${r.id})">💵 Lunasi</button>`:''}</td></tr>`;
  }
  $('tblInv').innerHTML=h+'</tbody>'||'<tr><td colspan=5 class=empty>Belum ada kwitansi</td></tr>';
}
function openInv(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">🧾 Kwitansi Baru</h1>
    <input id="icust" placeholder="Nama pelanggan">
    <input id="iamt" type="number" placeholder="Nominal (contoh 250000)">
    <textarea id="idesc" rows="3" placeholder="Keterangan (untuk pembayaran apa)"></textarea>
    <button onclick="saveInv()">Buat Kwitansi</button><div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function saveInv(){
  const d=await api('inv_create',{customer_name:icust.value,amount:+(iamt.value||0),description:idesc.value});
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Kwitansi dibuat ✅');loadInv();
}
async function payInv(id){
  if(!confirm('Lunasi kwitansi ini? Kas otomatis bertambah.'))return;
  const d=await api('inv_pay',{id});
  if(d.ok){toast('Pembayaran dicatat ✅');loadInv();renderPage();}else toast(d.error);
}

/* ===== PERSETUJUAN & AUDIT ===== */
async function loadAppr(){
  const d=await api('appr_list');if(d.error)return;
  const el=$('apprList');
  el.innerHTML=d.rows.length?d.rows.map(a=>`<div class="rank-row"><div class="rank-copy">
    <div class="rank-index" style="font-size:13px;background:var(--fd-surface-2)">🔔</div>
    <div class="rank-name"><strong>${esc(a.requester||'?')} — ${fmt(a.amount)}</strong>
      <span>${esc(a.description)} • ${a.biz?esc(a.biz):'pribadi'} • ${a.created_at.slice(0,16)}</span></div>
    <div style="display:flex;gap:6px">
      <button class="btn btn-sm btn-green" onclick="decideAppr(${a.id},'approve')">✓ Setujui</button>
      <button class="btn btn-sm btn-red" onclick="decideAppr(${a.id},'reject')">✕ Tolak</button></div>
    </div></div>`).join(''):'<div class="empty">Tidak ada permintaan menunggu 🎉</div>';
}
async function decideAppr(id,decision){
  const d=await api('appr_decide',{id,decision});
  if(d.ok){toast(decision==='approve'?'Disetujui & dicatat ✅':'Ditolak');loadAppr();}else toast(d.error);
}
async function loadAudit(){
  const d=await api('audit_log_list');if(d.error)return;
  let h='<thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Detail</th></tr></thead><tbody>';
  const j=s=>{try{return JSON.parse(s);}catch(e){return null;}};
  for(const r of d.rows){
    let det=esc(r.detail||'');
    const ov=j(r.old_values),nv=j(r.new_values);
    if(ov||nv){
      det+='<div class="small" style="margin-top:4px;color:#b45309">';
      if(ov)det+='Sebelum: '+esc(Object.entries(ov).map(([k,v])=>k+'='+v).join(', '))+'<br>';
      if(nv)det+='Sesudah: '+esc(Object.entries(nv).map(([k,v])=>k+'='+v).join(', '));
      det+='</div>';}
    h+=`<tr><td style="white-space:nowrap">${(r.created_at||'').slice(0,16)}</td>
    <td><b>${esc(r.username||'—')}</b></td><td><span class="badge b-transfer">${esc(r.action)}</span></td>
    <td class="small">${det}</td></tr>`;}
  $('tblAudit').innerHTML=h+'</tbody>'||'<tr><td colspan=4 class=empty>Belum ada aktivitas</td></tr>';
}

/* ===== BANNER JATUH TEMPO ===== */
async function loadDueBanner(){
  const el=$('dueBanner');if(!el)return;
  const d=await api('calendar',null,{days:3});
  if(d.error||!d.rows.length){el.innerHTML='';return;}
  const due=d.rows.filter(r=>r.flow==='keluar');
  if(!due.length){el.innerHTML='';return;}
  el.innerHTML=`<div class="panel" style="border-left:4px solid var(--fd-danger,#c0392b)">
    <h3>⏰ Jatuh tempo ≤3 hari — ${due.length} kewajiban • total ${fmt(due.reduce((a,r)=>a+r.amount,0))}</h3>
    <div class="rank-list">${due.map(r=>{
      const days=Math.round((new Date(r.date)-new Date(d.today))/86400000);
      const when=days<0?'<span class="badge b-keluar">TERLAMBAT</span>':days===0?'<span class="badge b-wait">HARI INI</span>':`<span class="small">${days} hari lagi</span>`;
      return `<div class="rank-row"><div class="rank-copy">
        <div class="rank-index" style="background:var(--fd-surface-2);font-size:13px">${r.icon}</div>
        <div class="rank-name"><strong>${esc(r.title)}</strong><span>${when}</span></div>
        <div class="rank-value neg">${fmt(r.amount)}</div></div></div>`;
    }).join('')}</div>
    <button class="btn btn-sm" style="margin-top:10px" onclick="gotoPage('tagihan')">Bayar sekarang →</button></div>`;
}

/* ===== DASHBOARD ===== */
async function renderDash(){
  const s=(await api('summary',null,summaryQ()));if(s.error)return;summary=s;
  $('heroKas').textContent=fmt(s.total_kas);
  loadInsight();loadCalendar();loadDueBanner();loadBudgetWarn();
  // proyeksi arus kas + anomali
  const [fc,an]=await Promise.all([api('cashflow_forecast'),api('anomalies')]);
  const fcEl=$('forecastBox');
  if(fcEl&&!fc.error){const neg=fc.proj_30d<fc.kas_now*0.5;
    fcEl.innerHTML=`<div class="panel" style="margin-bottom:16px;border-left:4px solid var(--fd-brand,#155eef)">
      <h3>🔮 Proyeksi 30 Hari <span class="small">(ritme 90 hari terakhir)</span></h3>
      <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:6px">
        <div><div class="small">Kas sekarang</div><b>${fmt(fc.kas_now)}</b></div>
        <div><div class="small">Proyeksi realistis</div><b class="${fc.proj_30d>=fc.kas_now?'pos':'neg'}">${fmt(fc.proj_30d)}</b></div>
        <div><div class="small">Skenario optimis</div><b class="pos">${fmt(fc.optimis_30d)}</b></div>
        <div><div class="small">Skenario pesimis</div><b class="neg">${fmt(fc.pesimis_30d)}</b></div>
        <div><div class="small">Tagihan rutin/bln</div><b>${fmt(fc.monthly_bills)}</b></div>
      </div></div>`;}
  if(an&&!an.error&&an.rows.length){
    $('dueBanner').insertAdjacentHTML('beforeend',`<div class="panel" style="border-left:4px solid #b86b00;margin-top:12px">
      <h3>⚠️ Anomali pengeluaran bulan ini</h3>
      ${an.rows.map(r=>`<div class="rank-row" style="padding:6px 0"><div class="rank-copy">
        <div class="rank-name"><strong>${esc(r.cat)} — ${fmt(r.amount)}</strong>
          <span>${r.multiple}x lipat rata-rata biasanya • ${esc((r.description||'').slice(0,40))}</span></div></div></div>`).join('')}
      </div>`);
  }
  const ki={cash:'💵',bank:'🏦',ewallet:'📱',other:'💼'};
  $('walletStrip').innerHTML=s.wallets.map(w=>`<div class="kpi-card" style="min-width:150px;padding:12px 14px">
    <div class="kpi-label">${ki[w.kind]||'💼'} ${esc(w.name)}${w.is_default?' ⭐':''}</div>
    <div style="font-size:17px;font-weight:800;margin-top:3px">${fmt(w.balance)}</div></div>`).join('');
  const totIn=s.usaha.masuk+s.pribadi.masuk, totOut=s.usaha.keluar+s.pribadi.keluar;
  const icon=(cl,i)=>`<div class="kpi-icon ${cl}">${i}</div>`;
  $('stats').innerHTML=`
   <div class="kpi-card"><div class="kpi-head"><div class="kpi-label">Pemasukan Bulan Ini</div>${icon('green','📥')}</div>
     <div class="kpi-value pos">${fmt(totIn)}</div>
     <div class="kpi-note">usaha ${fmt(s.usaha.masuk)} • pribadi ${fmt(s.pribadi.masuk)}</div></div>
   <div class="kpi-card"><div class="kpi-head"><div class="kpi-label">Pengeluaran Bulan Ini</div>${icon('red','📤')}</div>
     <div class="kpi-value neg">${fmt(totOut)}</div>
     <div class="kpi-note">usaha ${fmt(s.usaha.keluar)} • pribadi ${fmt(s.pribadi.keluar)}</div></div>
   <div class="kpi-card"><div class="kpi-head"><div class="kpi-label">Laba Usaha Bulan Ini</div>${icon('blue','🏪')}</div>
     <div class="kpi-value ${s.usaha.laba>=0?'pos':'neg'}">${fmt(s.usaha.laba)}</div>
     <div class="kpi-note">${s.cabang.length} cabang aktif</div></div>
   <div class="kpi-card"><div class="kpi-head"><div class="kpi-label">Sisa Pribadi (bln ini)</div>${icon('violet','👤')}</div>
     <div class="kpi-value brandc">${fmt(s.pribadi.masuk-s.pribadi.keluar)}</div>
     <div class="kpi-note">kas dompet: ${s.wallets.map(w=>esc(w.name)).join(', ')}</div></div>`;
  renderRecent(s.recent);renderCharts(s.tren,s.cabang);renderDistribution(s);
  loadBillsInto('dashBills',true);loadGoalsInto(null,true);
}
function renderRecent(rows){
  let h='<thead><tr><th>Tanggal</th><th>Jenis</th><th>Rincian</th><th>Scope</th><th style="text-align:right">Jumlah</th></tr></thead><tbody>';
  for(const r of rows.slice(0,10)){
    h+=`<tr><td style="white-space:nowrap">${r.tx_date}</td>
      <td><span class="badge b-${r.type}">${r.type}</span></td>
      <td>${esc(r.description)}<br><span class="small">${r.biz?esc(r.biz)+' • ':''}${esc(r.cat||'')}</span></td>
      <td><span class="badge ${r.biz?'b-usaha':'b-pribadi'}">${r.biz?esc(r.biz):'pribadi'}</span></td>
      <td style="text-align:right;font-weight:700" class="${r.type==='masuk'?'pos':'neg'}">${r.type==='masuk'?'+':'−'}${fmt(r.amount).slice(3)}</td></tr>`;
  }
  $('tblRecent').innerHTML=h+'</tbody>'||'<tr><td colspan=5 class=empty>Belum ada transaksi</td></tr>';
}
function renderCharts(tren,cabang){
  Object.values(charts).forEach(c=>{try{c?.destroy()}catch(e){}});charts={};
  /* canvas TIDAK mengerti var(--x) / color-mix() -> resolusi manual ke nilai aslinya */
  const cs=getComputedStyle(document.documentElement);
  const cssVar=n=>cs.getPropertyValue(n).trim();
  const mix=(hex,pct)=>{ // campur hex dgn transparansi -> rgba
    const m=hex.replace('#','').match(/^([0-9a-f]{6})$/i);
    if(!m)return hex;
    const r=parseInt(m[1].slice(0,2),16),g=parseInt(m[1].slice(2,4),16),b=parseInt(m[1].slice(4,6),16);
    return `rgba(${r},${g},${b},${pct})`;
  };
  const suc=cssVar('--fd-success')||'#16836f', dan=cssVar('--fd-danger')||'#c54c5a';
  charts.t=new Chart($('chartTren'),{type:'line',data:{labels:tren.map(x=>x.bulan),
    datasets:[{label:'Masuk',data:tren.map(x=>x.masuk),borderColor:suc,backgroundColor:mix(suc,.14),fill:true,tension:.4},
              {label:'Keluar',data:tren.map(x=>x.keluar),borderColor:dan,backgroundColor:mix(dan,.12),fill:true,tension:.4}]},
    options:{plugins:{legend:{labels:{usePointStyle:true}}},scales:{y:{ticks:{callback:v=>(v/1000)+'k'}}},maintainAspectRatio:true}});
  charts.c=new Chart($('chartCabang'),{type:'bar',data:{labels:cabang.map(c=>(c.icon?c.icon+' ':'')+c.name),
    datasets:[
      {label:'Masuk',data:cabang.map(c=>c.masuk),backgroundColor:mix(suc,.75),borderRadius:7},
      {label:'Keluar',data:cabang.map(c=>c.keluar),backgroundColor:mix(dan,.75),borderRadius:7},
      {label:'Laba',data:cabang.map(c=>c.laba),type:'line',borderColor:mix('#8568d8',.9),
        backgroundColor:'rgba(133,104,216,.15)',fill:true,tension:.35,pointRadius:4,borderWidth:2.5}]},
    options:{plugins:{legend:{labels:{usePointStyle:true}}},
      scales:{y:{ticks:{callback:v=>(v/1000)+'k'}}}}});
}
/* distribution strip + rank list ala FinDompet */
function renderDistribution(s){
  // pengeluaran bulan ini per scope/cabang
  const items=[['Pribadi',s.pribadi.keluar,'var(--fd-brand)'],
    ...s.cabang.map((c,i)=>[c.name,c.keluar,scopeColor(i+1)])].filter(x=>x[1]>0);
  if(!items.length){$('distStrip').innerHTML='<div class="empty">Belum ada pengeluaran bulan ini 🎉</div>';$('rankList').innerHTML='';return;}
  const total=items.reduce((a,x)=>a+x[1],0);
  $('distStrip').innerHTML='<div class="modern-distribution-strip">'+items.map(x=>
    `<div class="modern-distribution-segment" style="flex-grow:${x[1]};background:${x[2]}" title="${esc(x[0])}: ${fmt(x[1])}"></div>`).join('')+'</div>';
  const sorted=[...items].sort((a,b)=>b[1]-a[1]);
  $('rankList').innerHTML='<div class="rank-list">'+sorted.map((x,i)=>{
    const pct=(x[1]/total*100);
    return `<div class="rank-row"><div class="rank-copy">
      <div class="rank-index">#${i+1}</div>
      <div class="rank-name"><strong><span class="rank-dot" style="background:${x[2]}"></span>${esc(x[0])}</strong><span>${pct.toFixed(1)}% dari total pengeluaran</span></div>
      <div class="rank-value">${fmt(x[1])}</div></div>
      <div class="rank-track"><span class="rank-fill" style="width:${pct}%;background:${x[2]}"></span></div></div>`;
  }).join('')+'</div>';
}

/* ===== INSIGHT / SAFE TO SPEND ===== */
async function loadInsight(){
  const d=await api('insight');if(d.error){$('insightBox').innerHTML='';return;}
  let chips='';
  if(d.safe_to_spend!==null){
    const perDay=fmt(Math.floor(d.safe_to_spend/d.days_left));
    chips+=`<div class="panel" style="flex:1;min-width:220px;border-color:color-mix(in srgb,var(--fd-success) 35%,var(--fd-border))">
      <div class="kpi-label" style="color:var(--fd-success)">🟢 SAFE TO SPEND (pribadi)</div>
      <div class="kpi-value pos">${fmt(d.safe_to_spend)}</div>
      <div class="kpi-note">≈ ${perDay}/hari untuk ${d.days_left} hari lagi</div></div>`;
  }
  if(d.avg_daily>0){
    chips+=`<div class="panel" style="flex:1;min-width:220px">
      <div class="kpi-label">📊 Rata-rata Harian (pribadi)</div>
      <div class="kpi-value">${fmt(d.avg_daily)}</div>
      <div class="kpi-note">proyeksi akhir bulan: ${fmt(d.projection)}</div></div>`;
  }
  $('insightBox').innerHTML=chips?`<div style="display:flex;gap:14px;flex-wrap:wrap">${chips}</div>`:'';
}
function exportCSV(scope,biz){
  const p=new URLSearchParams({action:'export_csv',
    from:$('fFrom')?.value||'',to:$('fTo')?.value||''});
  if(biz)p.set('business_id',biz);
  window.open('api.php?'+p.toString(),'_blank');
}

/* ===== TRANSAKSI ===== */
let txTimer,sortState={col:'date',dir:'desc'},TXPAGE=null;
function setSort(c){if(sortState.col===c)sortState.dir=sortState.dir==='desc'?'asc':'desc';else sortState={col:c,dir:'desc'};loadTx();}
function txGo(p){TXPAGE=p;window.scrollTo({top:0,behavior:'smooth'});loadTx();}
async function loadTx(){
  clearTimeout(txTimer);
  txTimer=setTimeout(async()=>{
    const d=await api('tx_list',{scope:fScope.value,business_id:fBiz.value,type:fType.value,
      q:fQ.value,from:fFrom.value,to:fTo.value,
      category:fCatF?.value||'',min_amount:fMin?.value||'',max_amount:fMax?.value||'',
      limit:50,page:TXPAGE||1,sort:sortState.col,dir:sortState.dir});
    if(d.error)return;
    TXPAGE=d;const arrow=c=>(sortState.col===c)?(sortState.dir==='desc'?' ▼':' ▲'):'';
    let h='<thead><tr><th onclick="setSort(\'date\')" style="cursor:pointer">Tanggal'+arrow('date')+'</th><th>Jenis</th><th>Rincian</th><th>Scope/Cabang</th><th>Kas</th><th onclick="setSort(\'amount\')" style="cursor:pointer;text-align:right">Jumlah'+arrow('amount')+'</th><th></th></tr></thead><tbody>';
    for(const r of d.rows){
      h+=`<tr><td style="white-space:nowrap">${r.tx_date}</td>
        <td><span class="badge b-${r.type}">${r.type}</span></td>
        <td>${esc(r.description)}<br><span class="small">${r.tx_no?'No. '+esc(r.tx_no)+' • ':''}${esc(r.cat||'')}${r.display_name?' • oleh '+esc(r.display_name):''}</span></td>
        <td>${r.biz?`<span class="badge b-usaha">${esc(r.biz)}</span>`:'<span class="badge b-pribadi">pribadi</span>'}</td>
        <td class="small">${esc(r.wallet||'')}</td>
        <td style="text-align:right;font-weight:700" class="${r.type==='masuk'?'pos':'neg'}">${r.type==='masuk'?'+':'−'}${fmt(r.amount).slice(3)}</td>
        <td><span style="cursor:pointer" title="lampiran nota" onclick="openAtt(${r.id},${JSON.stringify(r.description)})">📎</span>
            <span style="cursor:pointer" title="salin link struk digital" onclick="copyReceipt(${r.id})">🔗</span>
            <span style="cursor:pointer" title="hapus" onclick="delTx(${r.id})">🗑️</span></td></tr>`;
    }
    $('tblTx').innerHTML=h+'</tbody>';
    const pg=$('txPager');
    if(pg){pg.innerHTML=d.pages>1?`<button class="btn btn-sm secondary" ${d.page<=1?'disabled':''} onclick="txGo(${d.page-1})">← Sebelumnya</button>
      <span class="small" style="align-self:center">Halaman ${d.page}/${d.pages} • ${d.total} transaksi</span>
      <button class="btn btn-sm secondary" ${d.page>=d.pages?'disabled':''} onclick="txGo(${d.page+1})">Berikutnya →</button>`
      :`<span class="small">${d.total} transaksi</span>`;}
  },200);
}
async function delTx(id){
  if(!confirm('Hapus transaksi ini? Kas otomatis dikembalikan.'))return;
  const d=await api('tx_delete',{id});
  if(d.ok){toast('Terhapus');renderPage();}else toast(d.error);
}
async function copyReceipt(id){
  const d=await api('receipt_link',{id});
  if(d.error)return toast(d.error);
  const url=location.origin+'/'+d.url;
  try{await navigator.clipboard.writeText(url);toast('Link struk disalin ✅');}catch(e){prompt('Salin link struk:',url);}
}

/* ===== MODAL TRANSAKSI ===== */
function openAdd(){
  const isK = ME && ME.role==='kariawan';
  const bizField = isK ? '' :
   `<select id="fbiz"><option value="">— Pribadi owner —</option>${meta.businesses.map(b=>`<option value="${b.id}">${esc(b.name)}</option>`).join('')}</select>`;
  const catWallet = isK ? '' :
   `<div class="row2">
     <select id="fcat"><option value="">— tanpa kategori —</option>${meta.categories.map(c=>`<option>${esc(c.name)}</option>`).join('')}</select>
     <select id="fwallet">${summary.wallets.map(w=>`<option value="${w.id}" ${w.is_default?'selected':''}>${esc(w.name)} (${fmt(w.balance)})</option>`).join('')}</select></div>`;
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">➕ ${isK?'Transaksi Cabang':'Transaksi Baru'}</h1>
    <div class="sub">${isK?('Masuk ke laporan '+esc(ME.business_name||'cabangmu')+' • bos langsung dapat notif'):'Langsung menggerakkan kas dompetmu'}</div>
    ${isK?'':`<div class="row2">
     <select id="ftype"><option value="keluar">🔴 Keluar</option><option value="masuk">🟢 Masuk</option><option value="transfer">🔁 Transfer kas</option></select>
     ${bizField}</div>`}
    ${isK?`<div class="row2">
     <select id="ftype2"><option value="keluar">🔴 Keluar</option><option value="masuk">🟢 Masuk</option></select>
     <div></div></div>`:''}
    <input id="famt" type="number" placeholder="Nominal (contoh 150000)">
    <input id="fdesc" placeholder="Keterangan">
    <div id="fdestWrap" style="display:none"><select id="fdest"><option value="">— Ke rekening —</option>${summary.wallets.map(w=>`<option value="${w.id}">→ ${esc(w.name)}</option>`).join('')}</select></div>
    ${catWallet}
    <div class="row2" style="gap:8px">
      <button onclick="saveTx()">Simpan</button>
      ${isK?'<button class="btn secondary" onclick="requestAppr()" title="Kirim ke bos dulu untuk disetujui">🔔 Minta Setujui</button>':''}
    </div>
    ${isK?'<div class="small" style="margin-top:6px">Pakai 🔔 untuk pengeluaran besar — masuk kas setelah bos setujui</div>':''}
    <div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
document.addEventListener('change',e=>{if(e.target&&e.target.id==='ftype'){const w=$('fdestWrap');if(w)w.style.display=e.target.value==='transfer'?'block':'none';}});
async function saveTx(){
  const isK = ME && ME.role==='kariawan';
  let type, body;
  if(isK){
    type = ftype2.value; // select sederhana utk kariawan
    body = {type,amount:+famt.value,description:fdesc.value};
  }else{
    type = ftype.value;
    body = {type:ftype.value==='transfer'?'transfer':ftype.value,amount:+famt.value,description:fdesc.value,
      business_id:fbiz.value||null,category:fcat.value||null,wallet_id:fwallet.value||null,
      wallet_dest_id:type==='transfer'?(fdest?.value||null):null};
  }
  const d=await api(isK?'tx_add_branch':'tx_add',body);
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Tersimpan ✅ Bos sudah dapat notif');renderPage();
}
async function requestAppr(){
  const isK = ME && ME.role==='kariawan';
  if(!famt.value||!fdesc.value){merr.textContent='Isi nominal & keterangan dulu';return;}
  const d=await api('appr_request',{type:(ftype2?.value||'keluar'),amount:+famt.value,description:fdesc.value});
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Permintaan terkirim ke bos ⏳');renderPage();
}


/* ===== LAMPIRAN FOTO NOTA ===== */
let attDesc='';
async function openAtt(txId,desc){
  attDesc=desc||'';
  const d=await api('att_list',null,{tx_id:txId});
  if(d.error){toast(d.error);return;}
  const list=d.rows.map(a=>`<div style="display:flex;gap:10px;align-items:center;padding:6px 0;border-bottom:1px solid var(--fd-border)">
     <img src="uploads/${a.filename}" style="width:56px;height:56px;object-fit:cover;border-radius:8px" onclick="viewAtt('uploads/${a.filename}')">
     <div class="small">${esc(a.original_name||a.filename)}</div></div>`).join('');
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">📎 Lampiran Nota</h1>
    <div class="sub">${esc(desc||'')}</div>
    ${list||'<div class="empty">Belum ada foto nota</div>'}
    <input id="attFile" type="file" accept="image/jpeg,image/png,image/webp" style="margin-top:12px">
    <button onclick="upAtt(${txId})">⬆️ Upload Foto</button>
    <div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function upAtt(txId){
  const f=$('attFile').files[0];
  if(!f){merr.textContent='Pilih foto dulu';return;}
  const fd=new FormData();fd.append('tx_id',txId);fd.append('file',f);
  const r=await fetch('api.php?action=att_upload',{method:'POST',body:fd,credentials:'same-origin'});
  const d=await r.json();
  if(d.error){merr.textContent=d.error;return;}
  toast('Foto tersimpan ✅');openAtt(txId,attDesc);
}
function viewAtt(src){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()" style="background:rgba(0,0,0,.85)">
   <img src="${src}" style="max-width:92vw;max-height:90vh;border-radius:12px"></div>`;
}

/* ===== TAGIHAN ===== */
async function loadBills(into,mini){loadBillsInto(into||'tblBills',mini);}
async function loadBillsInto(el,mini){
  const d=await api('bills');if(d.error)return;
  const rows=d.bills;
  if(mini){
    $(el).innerHTML=rows.length?rows.slice(0,4).map(b=>`
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--fd-border)">
       <div>${b.paid_this_month?'✅':'⏳'} <b>${esc(b.name)}</b> <span class="small">(tgl ${b.day_of_month})</span><br>
        <span class="small">${b.biz?esc(b.biz):'pribadi'}</span></div>
       <div style="text-align:right"><b>${fmt(b.amount)}</b><br>
        ${b.paid_this_month?'<span class="badge b-done">LUNAS</span>':`<button class="btn btn-sm btn-green" onclick="payBill(${b.id})">Bayar</button>`}</div></div>`).join('')
     :'<div class="empty">Belum ada tagihan rutin</div>';
    return;
  }
  let h='<thead><tr><th>Nama</th><th>Tgl</th><th>Cabang</th><th style="text-align:right">Nominal</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
  for(const b of rows){
    h+=`<tr><td><b>${esc(b.name)}</b></td><td>tgl ${b.day_of_month}</td>
     <td>${b.biz?esc(b.biz):'<span class="badge b-pribadi">pribadi</span>'}</td>
     <td style="text-align:right"><b>${fmt(b.amount)}</b></td>
     <td>${b.paid_this_month?'<span class="badge b-done">LUNAS</span>':'<span class="badge b-wait">BELUM</span>'}</td>
     <td>${b.paid_this_month?'':`<button class="btn btn-sm btn-green" onclick="payBill(${b.id})">Bayar</button>`}
        <button class="btn btn-sm btn-red" onclick="delBill(${b.id})">✕</button></td></tr>`;
  }
  $(el).innerHTML=h+'</tbody>'||'<tr><td colspan=6 class=empty>Belum ada tagihan.</td></tr>';
}
async function payBill(id){const d=await api('bill_pay',{id});
  if(d.ok){toast('Tagihan dibayar — kas terpotong ✅');renderPage();}else toast(d.error);}
async function delBill(id){if(!confirm('Nonaktifkan tagihan ini?'))return;
  const d=await api('bill_delete',{id});if(d.ok){toast('Dihapus');renderPage();}}
function openBill(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">🧾 Tagihan Rutin Baru</h1>
   <div class="sub">Contoh: listrik, sewa, gaji kariawan</div>
   <input id="bname" placeholder="Nama tagihan">
   <input id="bamt" type="number" placeholder="Nominal per bulan">
   <div class="row2"><input id="bday" type="number" min=1 max=28 placeholder="Tgl jatuh tempo (1-28)">
   <select id="bbiz"><option value="">— Pribadi —</option>${meta.businesses.map(b=>`<option value="${b.id}">${esc(b.name)}</option>`).join('')}</select></div>
   <button onclick="saveBill()">Simpan</button><div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function saveBill(){
  const d=await api('bill_add',{name:bname.value,amount:+bamt.value,day:+bday.value,business_id:bbiz.value||null});
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Tagihan ditambahkan ✅');renderPage();
}

/* ===== GOALS ===== */
async function loadGoals(){loadGoalsInto('goalCards');}
async function loadGoalsInto(el,mini){
  const d=await api('goals');if(d.error)return;
  if(mini){
    $(el).innerHTML=d.goals.length?d.goals.slice(0,3).map(g=>{
      const pct=Math.min(100,(g.saved_amount/g.target_amount*100)).toFixed(1);
      return `<div style="margin-bottom:12px"><b>${g.done?'🏆':''} ${esc(g.name)}</b> <span class="small">${pct}%</span>
       <div class="pbar ${pct>=100?'over':''}"><div style="width:${pct}%"></div></div>
       <span class="small">${fmt(g.saved_amount)} / ${fmt(g.target_amount)}</span></div>`;
    }).join(''):'<div class="empty">Belum ada target 🎯</div>';
    return;
  }
  $('goalCards').innerHTML=d.goals.length?d.goals.map(g=>{
    const pct=Math.min(100,(g.saved_amount/g.target_amount*100)).toFixed(1);
    return `<div class="kpi-card">
     <div class="kpi-head"><div class="kpi-label">${g.done?'🏆 SELESAI':'🎯 TARGET'}</div>
       <div class="kpi-icon blue">🎯</div></div>
     <div class="kpi-value" style="font-size:19px">${fmt(g.saved_amount)} <span class="small">/ ${fmt(g.target_amount)}</span></div>
     <div class="kpi-label" style="margin-top:6px">${esc(g.name)}</div>
     <div class="pbar ${pct>=100?'over':''}"><div style="width:${pct}%"></div></div>
     <div class="kpi-note">${pct}% tercapai${g.deadline?' • target '+g.deadline:''}</div>
     ${g.done?'':`<div style="display:flex;gap:8px;margin-top:10px">
       <input id="dep${g.id}" type="number" placeholder="Setor..." style="margin:0;padding:9px">
       <button class="btn btn-sm btn-green" onclick="deposit(${g.id})">Setor</button>
       <button class="btn btn-sm btn-red" onclick="delGoal(${g.id})">✕</button></div>`}</div>`;
  }).join(''):'<div class="panel empty" style="grid-column:1/-1">Belum ada target. Buat target pertamamu! 🎯</div>';
}
async function deposit(id){
  const amt=+$('dep'+id).value;
  const d=await api('goal_deposit',{id,amount:amt});
  if(d.ok){toast('Setoran masuk 🎉');renderPage();}else toast(d.error);
}
async function delGoal(id){if(!confirm('Hapus target ini?'))return;
  const d=await api('goal_delete',{id});if(d.ok){toast('Dihapus');renderPage();}}
function openGoal(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">🎯 Target Baru</h1>
   <input id="gname" placeholder="Nama target (misal: Dana Darurat)">
   <input id="gtarget" type="number" placeholder="Nominal target">
   <input id="gdline" type="date">
   <button onclick="saveGoal()">Simpan</button><div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function saveGoal(){
  const d=await api('goal_add',{name:gname.value,target:+gtarget.value,deadline:gdline.value});
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Target dibuat 🎯');renderPage();
}

/* ===== BUDGETS ===== */
function openReport(){
  const per=window.prompt('Periode laporan (YYYY-MM):',new Date().toISOString().slice(0,7));
  if(per&&/^\d{4}-\d{2}$/.test(per))window.open('report.php?period='+per,'_blank');
}
/* ===== PERINGATAN ANGGARAN (banner dashboard) ===== */
async function loadBudgetWarn(){
  const el=$('dueBanner');if(!el)return;
  const d=await api('budgets');if(d.error)return;
  const warn=d.budgets.filter(b=>b.spent/b.amount>=0.85);
  if(!warn.length)return;
  el.insertAdjacentHTML('beforeend',`<div class="panel" style="border-left:4px solid #b86b00;margin-top:${el.innerHTML.trim()?12:0}px">
    <h3>🧮 Anggaran hampir habis / lewat batas</h3>
    <div class="rank-list">${warn.map(b=>{
      const pct=Math.round(b.spent/b.amount*100);
      return `<div class="rank-row"><div class="rank-copy">
        <div class="rank-name"><strong>${esc(b.cat)} <span class="small" style="font-weight:400">• ${b.business_id?'usaha':'pribadi'}</span></strong>
          <span>${fmt(b.spent)} / ${fmt(b.amount)}</span></div>
        <div class="rank-value ${pct>100?'neg':''}" style="font-weight:800">${pct}%</div></div></div>`;
    }).join('')}</div></div>`);
}
async function loadBudgets(){
  const d=await api('budgets');if(d.error)return;
  $('budgetPeriod').textContent='Periode '+d.period;
  let h='<thead><tr><th>Kategori</th><th>Scope</th><th style="text-align:right">Anggaran</th><th style="text-align:right">Terpakai</th><th style="width:32%">Progres</th><th></th></tr></thead><tbody>';
  for(const b of d.budgets){
    const pct=Math.min(999,(b.spent/b.amount*100));
    h+=`<tr><td><b>${esc(b.cat)}</b></td>
     <td>${b.business_id?'usaha':'pribadi'}</td>
     <td style="text-align:right">${fmt(b.amount)}</td>
     <td style="text-align:right" class="${pct>100?'neg':''}">${fmt(b.spent)}</td>
     <td><div class="pbar ${pct>100?'over':''}"><div style="width:${Math.min(100,pct)}%"></div></div>
         <span class="small">${pct.toFixed(0)}% terpakai</span></td>
     <td><button class="btn btn-sm btn-red" onclick="delBudget(${b.id})">✕</button></td></tr>`;
  }
  $('tblBudgets').innerHTML=h+'</tbody>'||'<tr><td colspan=6 class=empty>Belum ada anggaran.</td></tr>';
}
async function delBudget(id){const d=await api('budget_delete',{id});if(d.ok){toast('Dihapus');renderPage();}}
function openBudget(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">🧮 Anggaran Bulan Ini</h1>
   <select id="bucat">${meta.categories.filter(c=>c.kind!=='masuk').map(c=>`<option>${esc(c.name)}</option>`).join('')}</select>
   <div class="row2"><input id="buamt" type="number" placeholder="Batas pengeluaran">
   <select id="bubiz"><option value="">— Pribadi —</option>${meta.businesses.map(b=>`<option value="${b.id}">${esc(b.name)}</option>`).join('')}</select></div>
   <button onclick="saveBudget()">Simpan</button><div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function saveBudget(){
  const d=await api('budget_set',{category:bucat.value,amount:+buamt.value,business_id:bubiz.value||null});
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Anggaran disimpan ✅');renderPage();
}

/* ===== CABANG & TIM ===== */
async function renderCabang(){
  const s=await api('summary',null,summaryQ());if(s.error)return;summary=s;
  $('bizCards').innerHTML=s.cabang.map(c=>`
   <div class="kpi-card">
    <div class="kpi-head"><div class="kpi-label">${c.icon?esc(c.icon):'🏪'} ${esc(c.name).toUpperCase()}</div>
      <div class="kpi-icon green">🏪</div></div>
    <div class="kpi-value ${c.laba>=0?'pos':'neg'}" style="${c.color?'color:'+c.color:''}">${fmt(c.laba)}</div>
    <div class="kpi-note">masuk ${fmt(c.masuk)} • keluar ${fmt(c.keluar)} • ${c.kariawan} kariawan${c.masuk>0?` • margin <b>${Math.round(c.laba/c.masuk*100)}%</b>`:''}</div>
    <div style="display:flex;gap:6px;margin-top:10px">
      <button class="btn btn-sm secondary" onclick="openBizEdit(${c.id})">✏️ Edit</button>
      <button class="btn btn-sm secondary" onclick="exportCSV(null,${c.id})" title="Export Excel transaksi cabang ini">📥 Excel</button>
      <button class="btn btn-sm btn-red" onclick="delBiz(${c.id},'${esc(c.name)}')">Tutup</button></div></div>`).join('')
   +`<div class="kpi-card"><div class="kpi-head"><div class="kpi-label">👤 PRIBADI OWNER</div>
     <div class="kpi-icon violet">👤</div></div>
     <div class="kpi-value ${summary.pribadi.laba>=0?'pos':'neg'}">${fmt(summary.pribadi.laba)}</div>
     <div class="kpi-note">masuk ${fmt(summary.pribadi.masuk)} • keluar ${fmt(summary.pribadi.keluar)}</div></div>`;
  $('tblUsers').innerHTML='<thead><tr><th>Nama</th><th>Username</th><th>Cabang</th><th>Aksi</th></tr></thead><tbody>'+
   meta.users.map(u=>`<tr><td><b>${esc(u.display_name)}</b>${u.role==='owner'?' 👑':''}</td>
    <td><span class="kbd" style="background:var(--fd-surface-2);border:1px solid var(--fd-border);border-radius:7px;padding:2px 8px;font-size:11.5px">${esc(u.username)}</span></td>
    <td>${u.biz?esc(u.biz):'—'}</td>
    <td>${u.role!=='owner'?`<button class="btn btn-sm btn-green" onclick="genCode(${u.id},'${esc(u.display_name)}')">Kode Login</button>
        <button class="btn btn-sm btn-red" onclick="toggleUser(${u.id})">Nonaktif</button>`:'—'}</td></tr>`).join('')+'</tbody>';
}
async function delBiz(id,name){if(!confirm('Tutup cabang "'+name+'"? Data transaksinya tetap tersimpan.'))return;
  const d=await api('biz_delete',{id});if(d.ok){toast('Cabang ditutup');meta=await api('meta');fillBizSelect();renderPage();}}
async function toggleUser(id){const d=await api('user_toggle',{id});if(d.ok){toast('Status diubah');meta=await api('meta');renderPage();}}
async function genCode(id,name){
  const d=await api('user_login_code',{id});
  if(d.error)return toast(d.error,true);
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal" style="text-align:center">
    <h1 style="font-size:17px">🔑 Kode Login — ${esc(name)}</h1>
    <div style="font-size:44px;font-weight:800;letter-spacing:8px;color:var(--fd-brand);margin:18px 0;
      background:var(--fd-surface-2);border-radius:14px;padding:16px">${d.code}</div>
    <div class="small">Kariawan kirim pesan ini ke bot Telegram:<br>
     <b>/mulai ${d.code}</b></div>
    <button class="btn btn-sm secondary" style="margin-top:16px" onclick="navigator.clipboard.writeText('/mulai ${d.code}');toast('Tersalin')">📋 Salin perintah</button>
   </div></div>`;
}
function openBiz(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">🏪 Bisnis / Cabang Baru</h1>
   <input id="bzname" placeholder="Nama usaha/cabang">
   <div class="row2"><input id="bzicon" placeholder="Ikon emoji (misal ☕)" maxlength="4">
   <input id="bzcolor" type="color" value="#155eef" style="height:44px;cursor:pointer"></div>
   <input id="bznote" placeholder="Catatan (opsional, misal: alamat)">
   <button onclick="saveBiz()">Simpan</button></div></div>`;
}
function openBizEdit(id){
  const b=(meta.businesses||[]).find(x=>x.id===id);if(!b)return;
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">✏️ Edit Profil Bisnis</h1>
   <input id="bzname" value="${esc(b.name)}" placeholder="Nama usaha/cabang">
   <div class="row2"><input id="bzicon" value="${esc(b.icon||'')}" placeholder="Ikon emoji" maxlength="4">
   <input id="bzcolor" type="color" value="${esc(b.color||'#155eef')}" style="height:44px;cursor:pointer"></div>
   <input id="bznote" value="${esc(b.note||'')}" placeholder="Catatan (opsional)">
   <button onclick="saveBiz(${id})">Simpan Perubahan</button></div></div>`;
}
async function saveBiz(id){
  const body={name:bzname.value,icon:bzicon.value,color:bzcolor.value,note:bznote.value};
  const d=id?await api('biz_update',{...body,id}):await api('biz_add',body);
  if(d.error)return toast(d.error);
  closeModal();toast(id?'Profil bisnis diperbarui ✅':'Bisnis dibuat 🏪');
  meta=await api('meta');fillBizSelect();fillBizSwitcher();renderPage();
}
function openUser(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">👥 Kariawan Baru</h1>
   <input id="udisp" placeholder="Nama tampil (misal: Budi - Cabang 1)">
   <div class="row2"><input id="uname" placeholder="Username login">
   <input id="upass" placeholder="Password (min 4)"></div>
   <select id="ubiz">${meta.businesses.map(b=>`<option value="${b.id}">${esc(b.name)}</option>`).join('')}</select>
   <select id="urole"><option value="kariawan">Kariawan — input & lihat cabangnya</option><option value="manajer">Manajer — + setujui pengeluaran cabang</option></select>
   <button onclick="saveUser()">Simpan</button><div id="merr" class="small neg" style="margin-top:8px"></div>
   <div id="uhint" class="small pos" style="margin-top:8px"></div></div></div>`;
}
async function saveUser(){
  const d=await api('user_add',{username:uname.value,password:upass.value,
    display_name:udisp.value,business_id:ubiz.value,role:urole.value});
  if(d.error){merr.textContent=d.error;return;}
  uhint.innerHTML='✅ Berhasil!<br>Instruksi untuk kariawan:<br><b>Kirim ke bot:</b> '+esc(d.bot_hint);
  meta=await api('meta');
}

/* ===== KAS / REKENING ===== */
async function renderWallets(){
  const s=await api('summary',null,summaryQ());if(s.error)return;summary=s;
  const kindIco={cash:'💵',bank:'🏦',ewallet:'📱',other:'💼'};
  const kindLbl={cash:'Tunai',bank:'Bank',ewallet:'E-Wallet',other:'Lainnya'};
  $('walletCards').innerHTML=s.wallets.map(w=>`
   <div class="kpi-card">
    <div class="kpi-head"><div class="kpi-label">${kindIco[w.kind]||'💼'} ${esc(w.name).toUpperCase()}${w.is_default?' ⭐':''}</div>
      <span class="badge b-transfer">${kindLbl[w.kind]||w.kind}</span></div>
    <div class="kpi-value">${fmt(w.balance)}</div>
    <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap">
     <button class="btn btn-sm secondary" onclick="renameWallet(${w.id},'${esc(w.name)}','${w.kind}')">✏️ Ubah</button>
     ${w.is_default?'<span class="small pos" style="align-self:center">Kas utama</span>':`<button class="btn btn-sm" onclick="setDefault(${w.id})">⭐ Jadikan utama</button>`}
     <button class="btn btn-sm btn-red" onclick="delWallet(${w.id})">🗑️</button></div></div>`).join('')
   +`<div class="kpi-card" style="display:flex;flex-direction:column;justify-content:center;cursor:pointer;border-style:dashed;text-align:center"
       onclick="openWallet()"><div style="font-size:30px">＋</div><div class="kpi-note">Tambah rekening baru</div></div>`;
}
function openWallet(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">💼 Rekening Baru</h1>
   <input id="wname" placeholder="Nama (misal: BCA, GoPay)">
   <select id="wkind"><option value="cash">💵 Tunai</option><option value="bank">🏦 Bank</option>
    <option value="ewallet">📱 E-Wallet</option><option value="other">💼 Lainnya</option></select>
   <input id="wbal" type="number" placeholder="Saldo awal">
   <button onclick="saveWallet()">Simpan</button><div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function saveWallet(){
  const d=await api('wallet_add',{name:wname.value,kind:wkind.value,balance:+(wbal.value||0)});
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Rekening ditambahkan ✅');renderPage();
}
function renameWallet(id,name,kind){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">✏️ Ubah Rekening</h1>
   <input id="wname2" value="${esc(name)}">
   <select id="wkind2">
    <option value="cash" ${kind==='cash'?'selected':''}>💵 Tunai</option>
    <option value="bank" ${kind==='bank'?'selected':''}>🏦 Bank</option>
    <option value="ewallet" ${kind==='ewallet'?'selected':''}>📱 E-Wallet</option>
    <option value="other" ${kind==='other'?'selected':''}>💼 Lainnya</option></select>
   <button onclick="(async()=>{const d=await api('wallet_rename',{id:${id},name:wname2.value,kind:wkind2.value});
     if(d.ok){closeModal();toast('Tersimpan');renderPage();}})()">Simpan</button></div></div>`;
}
async function setDefault(id){const d=await api('wallet_default',{id});
  if(d.ok){toast('Jadi kas utama ⭐');renderPage();}else toast(d.error);}
async function delWallet(id){if(!confirm('Hapus rekening ini?'))return;
  const d=await api('wallet_delete',{id});d.ok?(toast('Terhapus'),renderPage()):toast(d.error);}

/* ===== TRANSAKSI BERULANG ===== */
async function loadRec(){
  const d=await api('rec_list');if(d.error)return;
  const FREQ={weekly:'mingguan',monthly:'bulanan',yearly:'tahunan'};
  let h='<thead><tr><th>Nama</th><th>Jenis</th><th>Kategori/Cabang</th><th>Kas</th><th>Frekuensi</th><th>Jatuh Tempo Berikut</th><th>Otomatis</th><th>Status</th><th></th></tr></thead><tbody>';
  if(!d.rows.length)h+='<tr><td colspan=9 class="empty">Belum ada — klik "+ Berulang" buat set gaji/sewa/langganan</td></tr>';
  for(const r of d.rows){
    const due=r.next_date, soon=due<=d.today;
    h+=`<tr style="${r.active?'':'opacity:.45'}">
     <td><b>${esc(r.name)}</b>${r.note?`<div class="small">${esc(r.note)}</div>`:''}</td>
     <td><span class="badge ${r.type==='masuk'?'b-masuk':'b-keluar'}">${r.type}</span></td>
     <td>${esc(r.cat||'—')}${r.biz?` • ${esc(r.biz)}`:''}</td>
     <td>${esc(r.wallet||'—')}</td>
     <td>${FREQ[r.frequency]||r.frequency}</td>
     <td>${due}${soon&&r.active?' <span class="badge b-wait">jatuhtempo</span>':''}</td>
     <td>${r.auto_post?'🤖 ya':'✋ manual'}</td>
     <td>${r.active?'<span class="badge b-done">aktif</span>':'<span class="badge">nonaktif</span>'}</td>
     <td style="white-space:nowrap">
       <button class="btn btn-sm secondary" onclick="recToggle(${r.id})">${r.active?'⏸️':'▶️'}</button>
       <button class="btn btn-sm btn-red" onclick="recDel(${r.id})">🗑️</button></td></tr>`;
  }
  $('tblRec').innerHTML=h+'</tbody>';
}
function openRec(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">🔁 Transaksi Berulang Baru</h1>
   <input id="rcname" placeholder="Nama (misal: Gaji Budi / Sewa Toko)">
   <div class="row2"><input id="rcamt" type="number" placeholder="Jumlah (Rp)">
   <select id="rctype"><option value="keluar">Pengeluaran</option><option value="masuk">Pemasukan</option></select></div>
   <div class="row2">
    <select id="rccat"><option value="">— tanpa kategori —</option>${meta.categories.map(c=>`<option>${esc(c.name)}</option>`).join('')}</select>
    <select id="rcbiz"><option value="">Pribadi owner</option>${meta.businesses.map(b=>`<option value="${b.id}">${b.icon?esc(b.icon)+' ':''}${esc(b.name)}</option>`).join('')}</select></div>
   <div class="row2">
    <select id="rcwallet">${(summary?.wallets||[]).map(w=>`<option value="${w.id}" ${w.is_default?'selected':''}>${esc(w.name)}</option>`).join('')}</select>
    <select id="rcfreq"><option value="monthly">Bulanan</option><option value="weekly">Mingguan</option><option value="yearly">Tahunan</option></select></div>
   <input id="rcdate" type="date" value="${new Date().toISOString().slice(0,10)}">
   <input id="rcnote" placeholder="Catatan (opsional)">
   <label class="small" style="display:flex;gap:7px;align-items:center;padding:4px 0">
     <input type="checkbox" id="rcauto" checked style="width:auto;margin:0"> Posting otomatis oleh sistem tiap jatuh tempo</label>
   <button onclick="saveRec()">Simpan</button><div id="rcerr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function saveRec(){
  const body={name:rcname.value,amount:+rcamt.value,type:rctype.value,category:rccat.value,
    business_id:rcbiz.value,wallet_id:rcwallet.value,frequency:rcfreq.value,
    next_date:rcdate.value,note:rcnote.value,auto_post:rcauto.checked?1:0};
  const d=await api('rec_add',body);
  if(d.error){rcerr.textContent=d.error;return;}
  closeModal();toast('Berulang tersimpan 🔁');loadRec();
}
async function recToggle(id){const d=await api('rec_toggle',{id});d.ok?loadRec():toast(d.error);}
async function recDel(id){if(!confirm('Hapus transaksi berulang ini?'))return;
  const d=await api('rec_delete',{id});d.ok?(toast('Terhapus'),loadRec()):toast(d.error);}
async function recRunNow(){const d=await api('rec_run');
  toast(d.ok?('Selesai — '+d.posted+' transaksi diposting'):d.error);}

/* ===== PENGATURAN ===== */
async function loadSettings(){
  const d=await api('settings_get');if(d.error)return;
  sBotToken.placeholder=d.bot_token_set?('Tersimpan: '+d.bot_token_masked):'Token bot (123456:ABC-DEF...)';
  sGemini.placeholder=d.gemini_key_set?('Tersimpan: '+d.gemini_key_masked):'API key Gemini (AIza...)';
  sChatId.value=d.owner_chat_id||'';
  if(d.big_tx_limit)sBigTx.placeholder='Batas sekarang: Rp '+(+d.big_tx_limit).toLocaleString('id-ID');
  sNotifyTx.checked=d.notify_transactions==='1';
  sNotifyBill.checked=d.notify_bills==='1';
  tgStatus.textContent=d.bot_token_set?'🟢 Bot token sudah terpasang'+(d.bot_username?' (@'+d.bot_username+')':''):'⚪ Token belum diisi';
  aiStatus.textContent=d.gemini_key_set?'🟢 API key Gemini terpasang':'⚪ Gemini key belum diisi';
}
async function saveSettings(){
  const d=await api('settings_set',{
    bot_token:(sBotToken.value&&sBotToken.value.includes('••'))?undefined:sBotToken.value,
    owner_chat_id:sChatId.value,
    gemini_key:(sGemini.value&&sGemini.value.includes('••'))?undefined:sGemini.value,
    notify_transactions:sNotifyTx.checked?'1':'0',
    notify_bills:sNotifyBill.checked?'1':'0'});
  if(d.ok)toast('Pengaturan tersimpan ✅');else toast(d.error);
  loadSettings();
}
async function testTG(){
  await saveSettings();
  tgStatus.textContent='⏳ Menguji koneksi...';
  const d=await api('tg_test');
  tgStatus.innerHTML=(d.ok?'🟢 ':'🔴 ')+esc(d.msg||d.error||'');
  if(d.bot_name)loadSettings();
}
async function runBackup(){
  const st=$('backupStatus');st.textContent='⏳ Menjalankan backup...';
  const d=await api('backup_run');
  st.innerHTML=d.ok?'🟢 '+esc(d.detail||'Backup selesai'):'🔴 '+esc(d.detail||d.error||'Gagal');
}

/* ===== KELOLA KATEGORI (dinamis dari DB) ===== */
function renderCatManager(){
  const el=$('catList');if(!el)return;
  el.innerHTML=(meta.categories||[]).map(c=>`
    <div class="rank-row"><div class="rank-copy">
      <input type="color" value="${esc(c.color||'#7f8b99')}" onchange="catSetColor(${c.id},this.value)"
        style="width:34px;height:30px;padding:2px;margin:0;border-radius:8px;cursor:pointer;border:1px solid var(--fd-border)">
      <div class="rank-name"><strong>${esc(c.name)}</strong>
        <span>${c.kind==='masuk'?'pemasukan':c.kind==='keluar'?'pengeluaran':'masuk & keluar'}${c.scope_hint!=='both'?' • '+c.scope_hint:''}</span></div>
      <div style="display:flex;gap:6px">
        <button class="btn btn-sm secondary" onclick="catRename(${c.id},'${esc(c.name).replace(/'/g,"\\'")}')">✏️</button>
        <button class="btn btn-sm btn-red" onclick="catDel(${c.id})">🗑️</button></div>
    </div></div>`).join('');
}
async function catAdd(){
  const name=catNewName.value.trim();if(!name)return toast('Isi nama kategorinya dulu',1);
  const d=await api('cat_add',{name,kind:catNewKind.value,color:catNewColor.value});
  if(d.ok){toast('Kategori ditambah');meta=await api('meta');catNewName.value='';renderCatManager();}
  else toast(d.error,1);
}
async function catRename(id,old){
  const name=prompt('Ubah nama kategori:',old);
  if(!name||name.trim()===old)return;
  const d=await api('cat_rename',{id,name:name.trim()});
  if(d.ok){toast('Nama diubah');meta=await api('meta');renderCatManager();}else toast(d.error,1);
}
async function catSetColor(id,color){
  const d=await api('cat_color',{id,color});
  if(d.ok)toast('Warna disimpan');else toast(d.error,1);
}
async function catDel(id){
  if(!confirm('Hapus kategori ini? Transaksi lama tidak ikut terhapus (kategorinya jadi kosong).'))return;
  const d=await api('cat_delete',{id});
  if(d.ok){toast('Terhapus');meta=await api('meta');renderCatManager();}else toast(d.error,1);
}
async function loadClosedPeriods(){
  const d=await api('closed_periods');if(d.error)return;
  $('cbList').innerHTML=d.rows.length?'Terkunci: '+d.rows.map(r=>`<span class="kbd" style="background:var(--fd-surface-2);border:1px solid var(--fd-border);border-radius:7px;padding:2px 8px;margin-right:6px">${r.period} <a href="#" onclick="reopenBook('${r.period}');return false" style="color:var(--fd-danger)">✕</a></span>`).join(''):'Belum ada bulan yang dikunci.';
}
async function closeBook(){
  const per=(cbPeriod.value||'').trim();
  const d=await api('close_book',{period:per});
  cbStatus.textContent=d.ok?'🟢 Periode '+per+' terkunci':'🔴 '+(d.error||'Gagal');
  if(d.ok){cbPeriod.value='';loadClosedPeriods();}
}
async function reopenBook(per){
  if(!confirm('Buka kembali periode '+per+'?'))return;
  await api('reopen_book',{period:per});loadClosedPeriods();
}
async function changePassword(){
  if(pwNew.value!==pwNew2.value){pwStatus.textContent='🔴 Password baru tidak sama';return;}
  const d=await api('change_password',{current:pwCur.value,new:pwNew.value});
  pwStatus.textContent=d.ok?'🟢 Password berhasil diganti':'🔴 '+(d.error||'Gagal');
  if(d.ok){pwCur.value=pwNew.value=pwNew2.value='';}
}
async function testGemini(){
  await saveSettings();
  aiStatus.textContent='⏳ Menguji AI...';
  const d=await api('gemini_test');
  aiStatus.innerHTML=(d.ok?'🟢 ':'🔴 ')+esc(d.msg||d.error||'');
}

/* ===== AKUNTANSI ===== */
async function renderAkuntansi(){
  if(!$('accPeriod').value) $('accPeriod').value=new Date().toISOString().slice(0,7);
  const per=$('accPeriod').value;
  const [plD,bsD,cfD,trD,unres,txD,trD2]=await Promise.all([
    api('acc_pl',{period:per}),api('acc_balance'),api('acc_cashflow',{period:per}),
    api('tax_rules'),api('tax_unresolved'),api('tax_summary'),api('acc_trial')]);
  if(plD.error)return;

  const pl=plD.pl;
  $('plCards').innerHTML=`
   <div class="kpi-card"><div class="kpi-head"><div class="kpi-label">Pendapatan Neto</div><div class="kpi-icon green">💵</div></div>
     <div class="kpi-value pos">${fmt(pl.pendapatan)}</div>
     <div class="kpi-note">setelah dipotong pajak otomatis</div></div>
   <div class="kpi-card"><div class="kpi-head"><div class="kpi-label">Laba Bersih</div><div class="kpi-icon blue">💰</div></div>
     <div class="kpi-value ${pl.laba_bersih>=0?'pos':'neg'}">${fmt(pl.laba_bersih)}</div>
     <div class="kpi-note">HPP: ${fmt(pl.hpp)} • Beban: ${fmt(pl.beban)}</div></div>`;

  let h='<thead><tr><th>Akun</th><th style="text-align:right">Nilai</th></tr></thead><tbody>';
  for(const r of pl.detail)
    h+=`<tr><td>${esc(r.name)}<br><span class="small kbd" style="background:var(--fd-surface-2);border:1px solid var(--fd-border);border-radius:6px;padding:1px 7px;font-size:10.5px">${r.code}</span></td>
      <td style="text-align:right;font-weight:700" class="${r.type==='pendapatan'?'pos':(r.type==='beban'||r.type==='hpp')?'neg':''}">${fmt(r.value)}</td></tr>`;
  h+=`<tr><td><b>LABA BERSIH</b></td><td style="text-align:right;font-weight:800" class="${pl.laba_bersih>=0?'pos':'neg'}">${fmt(pl.laba_bersih)}</td></tr>`;
  $('tblPL').innerHTML=h+'</tbody>';

  const bs=bsD.bs;
  let b='<thead><tr><th>Akun</th><th>Tipe</th><th style="text-align:right">Saldo</th></tr></thead><tbody>';
  for(const r of bs.detail)
    b+=`<tr><td>${esc(r.name)}<br><span class="small kbd" style="background:var(--fd-surface-2);border:1px solid var(--fd-border);border-radius:6px;padding:1px 7px;font-size:10.5px">${r.code}</span></td>
      <td>${r.type}</td><td style="text-align:right">${fmt(r.balance)}</td></tr>`;
  b+=`<tr><td colspan=2><b>ASET</b></td><td style="text-align:right;font-weight:800">${fmt(bs.aset)}</td></tr>
      <tr><td colspan=2><b>LIABILITAS + EKUITAS + LABA BERJALAN</b></td><td style="text-align:right;font-weight:800">${fmt(bs.total_liab_ekuitas)}</td></tr>`;
  $('tblBS').innerHTML=b+'</tbody>';

  let c='<thead><tr><th>Kas</th><th style="text-align:right">Masuk/Keluar (net)</th></tr></thead><tbody>';
  for(const r of cfD.cf.per_kas)
    c+=`<tr><td>${esc(r.name)}</td><td style="text-align:right" class="${r.net>=0?'pos':'neg'}">${fmt(r.net)}</td></tr>`;
  c+=`<tr><td><b>BERSIH</b></td><td style="text-align:right;font-weight:800">${fmt(cfD.cf.bersih)}</td></tr>`;
  $('tblCF').innerHTML=c+'</tbody>';

  // tax rules
  let t='<thead><tr><th>Nama</th><th>Jenis</th><th>Rate</th><th>Cabang</th><th>Kategori</th><th></th></tr></thead><tbody>';
  for(const r of trD.rules){
    t+=`<tr><td><b>${esc(r.name)}</b></td><td><span class="badge ${r.tax_type==='pbjt'?'b-keluar':r.tax_type==='pph_umkm'?'b-transfer':'b-pribadi'}">${r.tax_type}</span></td>
     <td>${r.rate_pct}%</td><td>${r.biz?esc(r.biz):'semua'}</td><td>${esc(r.category_name||'semua')}</td>
     <td>${r.name!=='Non-pajak default'?`<button class="btn btn-sm btn-red" onclick="delTaxRule(${r.id})">✕</button>`:''}</td></tr>`;
  }
  $('tblTaxRules').innerHTML=t+'</tbody>';

  // unresolved
  let u='<thead><tr><th>Tanggal</th><th>Rincian</th><th style="text-align:right">Nominal</th></tr></thead><tbody>';
  u+=(unres.rows.length?unres.rows.map(r=>`<tr><td>${r.tx_date}</td><td>${esc(r.description)}</td>
    <td style="text-align:right">${fmt(r.amount)}</td></tr>`).join(''):
    '<tr><td colspan=3 class="empty">Tidak ada — semua transaksi usaha sudah sesuai aturan ✅</td></tr>');
  $('tblUnres').innerHTML=u+'</tbody>';
}
function openTaxRule(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">🧾 Aturan Pajak Baru</h1>
   <input id="txname" placeholder="Nama aturan (misal: PBJT Resto Cabang 1)">
   <div class="row2">
    <select id="txtype"><option value="pbjt">PBJT</option><option value="pph_umkm">PPh UMKM</option><option value="non_pajak">Non-pajak</option></select>
    <input id="txrate" type="number" step="0.1" placeholder="Rate % (misal 10)"></div>
   <div class="row2">
    <select id="txbiz"><option value="">Semua cabang</option>${meta.businesses.map(b=>`<option value="${b.id}">${esc(b.name)}</option>`).join('')}</select>
    <select id="txcat"><option value="">Semua kategori</option>${meta.categories.map(c=>`<option>${esc(c.name)}</option>`).join('')}</select></div>
   <div class="row2"><input id="txfrom" type="date" title="Berlaku dari"><input id="txto" type="date" title="Berlaku sampai"></div>
   <button onclick="saveTaxRule()">Simpan</button><div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
  // grafik pajak 6 bulan
  if(txD&&!txD.error){
    const lbl=txD.months.map(m=>m.slice(5)+'/'+m.slice(2,4));
    $('taxTotal').textContent=`total PBJT ${fmt(txD.total_pbjt)} • PPh ${fmt(txD.total_pph)}`;
    charts.tax?.destroy();
    const cv=$('chartTax');
    if(cv&&window.Chart)charts.tax=new Chart(cv,{type:'bar',
      data:{labels:lbl,datasets:[
        {label:'PBJT',data:txD.months.map(m=>txD.data[m].pbjt),backgroundColor:'rgba(184,107,0,.75)',borderRadius:6},
        {label:'PPh UMKM',data:txD.months.map(m=>txD.data[m].pph_umkm),backgroundColor:'rgba(21,94,239,.7)',borderRadius:6}]},
      options:{plugins:{legend:{position:'bottom',labels:{usePointStyle:true,padding:12}}},scales:{y:{ticks:{callback:v=>' '+(v/1000)+'k'}}}}});
  }

  // neraca saldo
  if(trD2&&!trD2.error){
    let t='<thead><tr><th>Kode</th><th>Akun</th><th style="text-align:right">Debit</th><th style="text-align:right">Kredit</th></tr></thead><tbody>';
    for(const r of trD2.trial)
      t+=`<tr><td><span class="kbd" style="background:var(--fd-surface-2);border:1px solid var(--fd-border);border-radius:6px;padding:1px 7px;font-size:10.5px">${r.account_code}</span></td>
        <td>${esc(r.name)}</td>
        <td style="text-align:right">${fmt(r.tot_debit)}</td>
        <td style="text-align:right">${fmt(r.tot_credit)}</td></tr>`;
    $('tblTrial').innerHTML=t+'</tbody>';
  }

async function saveTaxRule(){
  const d=await api('tax_rule_add',{name:txname.value,tax_type:txtype.value,rate_pct:+(txrate.value||0),
    business_id:txbiz.value||null,category_name:txcat.value||null,
    valid_from:txfrom.value||null,valid_to:txto.value||null});
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Aturan pajak disimpan');renderAkuntansi();
}
async function delTaxRule(id){if(!confirm('Nonaktifkan aturan ini?'))return;
  const d=await api('tax_rule_delete',{id});if(d.ok){toast('Dinonaktifkan');renderAkuntansi();}}

/* ===== PIUTANG ===== */
async function loadRecv(){
  const d=await api('receivables');if(d.error)return;
  let h='<thead><tr><th>Piutang</th><th>Cabang</th><th>Jatuh Tempo</th><th style="text-align:right">Sisa</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
  for(const r of d.rows){
    const sisa=parseFloat(r.amount)-parseFloat(r.paid_amount);
    const late=r.due_date&&r.due_date<new Date().toISOString().slice(0,10);
    h+=`<tr><td><b>${esc(r.debtor_name)}</b><br><span class="small">dibayar ${fmt(r.paid_amount)} / ${fmt(r.amount)}</span></td>
     <td>${r.biz?esc(r.biz):'—'}</td>
     <td>${r.due_date||'—'}${late?' <span class="badge b-keluar">lewat!</span>':''}</td>
     <td style="text-align:right"><b>${fmt(sisa)}</b></td>
     <td><span class="badge ${r.status==='paid'?'b-done':'b-wait'}">${r.status}</span></td>
     <td><button class="btn btn-sm btn-green" onclick="payRecv(${r.id},${sisa})">Terima Bayar</button></td></tr>`;
  }
  $('tblRecv').innerHTML=h+'</tbody>'||'<tr><td class=empty>Tidak ada piutang</td></tr>';
}
function openRecv(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">📋 Piutang Baru (jualan tempo)</h1>
   <div class="sub">Dicatat sebagai piutang & pendapatan — TIDAK langsung menambah kas</div>
   <input id="rvdebtor" placeholder="Nama pelanggan">
   <div class="row2"><input id="rvamt" type="number" placeholder="Nominal">
   <input id="rvdue" type="date"></div>
   <select id="rvbiz">${meta.businesses.map(b=>`<option value="${b.id}">${esc(b.name)}</option>`).join('')}</select>
   <button onclick="saveRecv()">Simpan</button><div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function saveRecv(){
  const d=await api('receivable_add',{debtor:rvdebtor.value,amount:+rvamt.value,due_date:rvdue.value,business_id:rvbiz.value});
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Piutang tercatat 📋');renderPage();
}
async function payRecv(id,sisa){
  const v=prompt('Jumlah diterima:',sisa); if(v===null)return;
  const d=await api('receivable_pay',{id,amount:+v});
  if(d.ok){toast('Kas masuk + piutang berkurang ✅');loadRecv();}else toast(d.error,true);
}

/* ===== PAYROLL (gaji staf) ===== */
async function loadPayroll(){
  const [d,e]=await Promise.all([api('payroll_list'),api('emp_list')]);
  if(d.error)return;
  let h='<thead><tr><th>Staf</th><th>Cabang</th><th style="text-align:right">Pokok</th><th style="text-align:right">Bonus</th><th style="text-align:right">Potongan</th><th style="text-align:right">Dibayar</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
  for(const r of d.rows){
    const paid=r.status==='paid';
    h+=`<tr><td><b>${esc(r.emp)}</b>${r.pos?`<br><span class="small">${esc(r.pos)}</span>`:''}</td>
     <td>${r.biz?esc(r.biz):'—'}</td>
     <td style="text-align:right">${fmt(r.base_amount)}</td>
     <td style="text-align:right" class="pos">${+r.bonus_amount?'+'+fmt(r.bonus_amount):'—'}</td>
     <td style="text-align:right" class="neg">${+r.deduction_amount?'-'+fmt(r.deduction_amount):'—'}</td>
     <td style="text-align:right"><b>${fmt(r.net_amount)}</b></td>
     <td><span class="badge ${paid?'b-done':'b-wait'}">${paid?'lunas':'pending'}</span></td>
     <td>${paid?`✅ <button class="btn btn-sm" onclick="slipLink(${r.id})">🧾 Slip</button>`:`<button class="btn btn-sm btn-green" onclick="payRun(${r.id})">Bayar</button>
        <button class="btn btn-sm" onclick="payAdjust(${r.id},${r.base_amount},${r.bonus_amount},${r.deduction_amount})">±</button>`}</td></tr>`;
  }
  $('tblPayroll').innerHTML=h+(d.rows.length?'':'<tr><td colspan=8 class=empty>Belum ada gajian — klik "Buat Gajian Bulan Ini"</td></tr>')+'</tbody>'
    +`<tfoot><tr><td colspan=3><button class="btn btn-sm" onclick="payGenerate()">${e.rows&&!e.rows.length?'Tambah staf dulu →':'⚡ Generate ulang (staf belum masuk)'}</button></td></tr>`;
}
function openEmp(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">👥 Staf Baru</h1>
   <input id="emname" placeholder="Nama staf">
   <div class="row2"><input id="empos" placeholder="Jabatan">
   <select id="embiz"><option value="">Pusat / pribadi</option>${meta.businesses.map(b=>`<option value="${b.id}">${esc(b.name)}</option>`).join('')}</select></div>
   <div class="row2"><input id="emsal" type="number" placeholder="Gaji pokok/bulan">
   <input id="empay" type="number" value="25" min="1" max="28" title="Tanggal gajian"></div>
   <button onclick="saveEmp()">Simpan</button><div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function saveEmp(){
  const d=await api('emp_save',{name:emname.value,position:empos.value,business_id:embiz.value||null,
    base_salary:+(emsal.value||0),pay_day:+(empay.value||25)});
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Staf ditambahkan 👥');loadPayroll();
}
async function payGenerate(){
  const d=await api('payroll_generate',{});
  if(d.ok){toast(d.created?`${d.created} draft gajian dibuat ⚡`:'Semua staf sudah ada di daftar gajian');loadPayroll();}
  else toast(d.error,true);
}
async function payRun(id){
  if(!confirm('Bayar gaji ini? Kas akan otomatis berkurang.'))return;
  const d=await api('payroll_run',{id});
  if(d.ok){toast('Gaji dibayar ✅');loadPayroll();}else toast(d.error,true);
}
async function payAdjust(id,base,bonus,ded){
  const b=prompt(`Bonus untuk bulan ini: (sekarang ${bonus})`,bonus);if(b===null)return;
  const c=prompt(`Potongan untuk bulan ini: (sekarang ${ded})`,ded);if(c===null)return;
  const d=await api('payroll_adjust',{id,bonus:+(b||0),deduction:+(c||0)});
  if(d.ok){toast('Gaji diperbarui');loadPayroll();}else toast(d.error,true);
}
async function slipLink(id){
  const d=await api('payslip_link',{id});
  if(d.ok){window.open(d.url.replace(/^/,'/'),'_blank');}
  else toast(d.error,true);
}

/* ===== UMUR PIUTANG (aging) ===== */
async function loadAging(){
  const d=await api('recv_aging');if(d.error)return;
  const B=[['belum_tempo','Belum tempo'],['1_30','Lewat 1-30 hr'],['31_60','31-60 hr'],['61_90','61-90 hr'],['90plus','>90 hr ⚠️']];
  const el=$('recvAging');if(!el)return;
  el.innerHTML=`<div class="kpi-grid">`+B.map(([k,l])=>{
    const hot=k==='90plus'&&d.buckets[k]>0;
    return `<div class="panel" style="${hot?'border-left:4px solid #dc2626':''}">
      <h3 style="font-size:12.5px;color:var(--fd-text-muted,#6b7280)">${l}</h3>
      <div style="font-size:19px;font-weight:800;margin-top:4px">${fmt(d.buckets[k]||0)}</div></div>`;
  }).join('')+`</div>`;
  let h='<thead><tr><th>Piutang</th><th>Jatuh Tempo</th><th style="text-align:right">Telat</th><th style="text-align:right">Sisa</th><th>Aksi</th></tr></thead><tbody>';
  for(const r of d.rows){
    h+=`<tr><td><b>${esc(r.debtor_name)}</b>${r.biz?` <span class="small">${esc(r.biz)}</span>`:''}</td>
     <td>${r.due_date||'—'}</td>
     <td style="text-align:right">${r.days_late!=null?`<span class="badge ${r.days_late>60?'b-keluar':'b-wait'}">${r.days_late} hari</span>`:'—'}</td>
     <td style="text-align:right"><b>${fmt(r.sisa)}</b></td>
     <td><button class="btn btn-sm btn-green" onclick="payRecv(${r.id},${r.sisa})">Terima</button></td></tr>`;
  }
  $('tblAging').innerHTML=h+(d.rows.length?'':'<tr><td colspan=5 class=empty>Semua piutang lunas 🎉</td></tr>')+'</tbody>';
}

/* ===== PAJAK BULANAN ===== */
const TAXLBL={pbjt:'PBJT',pph_umkm:'PPh UMKM',pph21:'PPh 21',non_pajak:'Non-Pajak'};
async function loadTax(){
  const d=await api('tax_monthly');if(d.error)return;
  const today=new Date().toISOString().slice(0,10);
  const due=d.rows.filter(r=>r.status==='unpaid'&&r.due_date&&r.due_date<=today);
  $('taxDueBanner').innerHTML=due.length?due.map(r=>`
    <div class="panel" style="border-left:4px solid var(--fd-danger,#dc2626);margin-bottom:10px;padding:12px 16px">
      <b>⏰ Jatuh tempo!</b> ${TAXLBL[r.tax_type]||r.tax_type} periode ${r.period} — ${fmt(r.amount)}
      (tempo ${r.due_date}) <button class="btn btn-sm btn-green" onclick="taxPay(${r.id})">Bayar Sekarang</button>
    </div>`).join():'';
  // hanya tampilkan periode berjalan & terbaru dulu
  let h='<thead><tr><th>Periode</th><th>Jenis Pajak</th><th style="text-align:right">Jumlah</th><th>Jatuh Tempo</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
  for(const r of d.rows){
    h+=`<tr><td><b>${r.period}</b></td><td>${TAXLBL[r.tax_type]||esc(r.tax_type)}</td>
     <td style="text-align:right">${fmt(r.amount)}</td>
     <td>${r.due_date||'—'}</td>
     <td><span class="badge ${r.status==='paid'?'b-done':'b-wait'}">${r.status==='paid'?'lunas':'belum'}</span></td>
     <td>${r.status==='paid'?'✅':`<button class="btn btn-sm btn-green" onclick="taxPay(${r.id})">Bayar</button>`}</td></tr>`;
  }
  $('tblTax').innerHTML=h+(d.rows.length?'':'<tr><td colspan=6 class=empty>Belum ada rekap — klik "Sinkron dari Transaksi"</td></tr>')+'</tbody>';
}
async function taxSync(){
  const d=await api('tax_monthly_sync',{});
  if(d.ok){toast(d.synced?`Rekap tersinkron (${d.synced} jenis) 🔄`:'Tidak ada pajak dari transaksi bulan ini');loadTax();}
  else toast(d.error,true);
}
function openTax(){
  const now=new Date().toISOString().slice(0,7);
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">🏛️ Entri Pajak Manual</h1>
   <div class="row2"><input id="txper" placeholder="${now}" value="${now}" title="Periode YYYY-MM">
   <select id="txkind"><option value="pbjt">PBJT</option><option value="pph_umkm">PPh UMKM</option>
   <option value="pph21">PPh 21</option><option value="non_pajak">Lainnya</option></select></div>
   <div class="row2"><input id="txamt" type="number" placeholder="Jumlah pajak">
   <input id="txdue" type="date" title="Jatuh tempo"></div>
   <button onclick="saveTax()">Simpan</button><div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function saveTax(){
  const d=await api('tax_monthly_set',{period:txper.value,tax_type:txkind.value,amount:+(txamt.value||0),due_date:txdue.value||null});
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Rekap pajak disimpan 🏛️');loadTax();
}
async function taxPay(id){
  if(!confirm('Bayar pajak ini? Kas akan otomatis berkurang.'))return;
  const d=await api('tax_monthly_pay',{id});
  if(d.ok){toast('Pajak dibayar ✅');loadTax();}else toast(d.error,true);
}

/* ===== ASET & PENYUSUTAN ===== */
async function loadAssets(){
  const d=await api('assets');if(d.error)return;
  let h='<thead><tr><th>Aset</th><th>Cabang</th><th style="text-align:right">Harga</th><th style="text-align:right">/Bulan</th><th style="text-align:right">Tersusut</th><th style="text-align:right">Nilai Buku</th><th>Status</th><th></th></tr></thead><tbody>';
  for(const r of d.rows){
    h+=`<tr><td><b>${esc(r.name)}</b><br><span class="small">sejak ${r.acquired_at} • ${r.life_months} bln</span></td>
     <td>${r.biz?esc(r.biz):'—'}</td>
     <td style="text-align:right">${fmt(r.cost)}</td>
     <td style="text-align:right">${fmt(r.monthly)}</td>
     <td style="text-align:right" class="neg">-${fmt(r.dep_total)}</td>
     <td style="text-align:right"><b>${fmt(r.book_value)}</b></td>
     <td><span class="badge ${r.dep_this_month?'b-done':'b-wait'}">${r.dep_this_month?'tersusut':'belum'}</span></td>
     <td><button class="btn btn-sm btn-red" onclick="assetDel(${r.id},'${esc(r.name).replace(/'/g,'')}')">🗑️</button></td></tr>`;
  }
  const tv=d.rows.reduce((s,r)=>s+ +r.book_value,0);
  $('tblAssets').innerHTML=h+(d.rows.length
    ?`<tfoot><tr><td colspan=5><b>Total nilai buku semua aset</b></td><td style="text-align:right"><b>${fmt(tv)}</b></td><td colspan=2></td></tr>`
    :'<tr><td colspan=8 class=empty>Belum ada aset — klik "+ Aset Baru"</td></tr>')+'</tbody>';
}
function openAsset(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">🏭 Aset Baru</h1>
   <input id="asname" placeholder="Nama aset (mis. Motor Delivery)">
   <div class="row2"><input id="ascost" type="number" placeholder="Harga beli">
   <input id="assalv" type="number" placeholder="Nilai sisa (0 boleh)"></div>
   <div class="row2"><input id="asdate" type="date" value="${new Date().toISOString().slice(0,10)}">
   <input id="aslife" type="number" value="48" min="1" title="Masa pakai (bulan)"></div>
   <select id="asbiz"><option value="">Pusat / pribadi</option>${meta.businesses.map(b=>`<option value="${b.id}">${esc(b.name)}</option>`).join('')}</select>
   <button onclick="saveAsset()">Simpan</button><div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function saveAsset(){
  const d=await api('asset_add',{name:asname.value,cost:+(ascost.value||0),salvage:+(assalv.value||0),
    acquired_at:asdate.value,life_months:+(aslife.value||48),business_id:asbiz.value||null});
  if(d.error){merr.textContent=d.error;return;}
  closeModal();toast('Aset tercatat 🏭');loadAssets();
}
async function assetDel(id,name){
  if(!confirm(`Jadikan aset "${name}" tidak aktif?`))return;
  const d=await api('asset_delete',{id});
  if(d.ok){toast('Aset diarsipkan');loadAssets();}
}
async function depRun(){
  const d=await api('asset_dep_run',{});
  if(d.ok){toast(d.run?`${d.run} aset tersusutkan — total ${fmt(d.total)} ⚡`:'Semua aset sudah tersusutkan bulan ini');renderAkuntansi&&loadAssets();}
  else toast(d.error,true);
}

/* ===== IMPORT CSV MUTASI BANK ===== */
function openImport(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal"><h1 style="font-size:17px">📥 Import Mutasi Bank (CSV)</h1>
   <div class="sub">Format: <b>tanggal;keterangan;jumlah</b> (negatif = keluar) ATAU <b>tanggal;keterangan;masuk;keluar</b>. Baris dobel & periode tutup buku otomatis dilewati.</div>
   <select id="imwall">${(summary?.wallets||[]).map(w=>`<option value="${w.id}" ${w.is_default?'selected':''}>${esc(w.name)} (${w.kind})</option>`).join('')}</select>
   <div class="row2"><select id="imscope"><option value="usaha">Usaha</option><option value="pribadi">Pribadi</option></select>
   <select id="imbiz"><option value="">Semua cabang / —</option>${meta.businesses.map(b=>`<option value="${b.id}">${esc(b.name)}</option>`).join('')}</select></div>
   <input id="imfile" type="file" accept=".csv,text/csv">
   <button onclick="doImport()">Import Sekarang</button><div id="merr" class="small neg" style="margin-top:8px"></div></div></div>`;
}
async function doImport(){
  const f=$('imfile').files[0];
  if(!f){merr.textContent='Pilih file CSV-nya dulu';return;}
  const fd=new FormData();
  fd.append('file',f);fd.append('wallet_id',imwall.value);fd.append('scope',imscope.value);fd.append('business_id',imbiz.value||'');
  try{
    const res=await fetch('/api.php?action=import_csv',{method:'POST',body:fd,credentials:'same-origin'});
    const d=await res.json();
    if(d.error){merr.textContent=d.error;return;}
    closeModal();
    toast(`Import selesai ✅ ${d.imported} masuk, ${d.skipped} dilewati`);
    renderPage();
  }catch(e){merr.textContent='Gagal upload: '+e.message;}
}

/* ===== LAPORAN ===== */
async function renderReport(){
  const s=await api('summary',null,summaryQ());if(s.error)return;summary=s;
  $('reportCards').innerHTML=`
   <div class="panel"><h3>👤 Laporan Pribadi (${s.period})</h3>
    <table style="min-width:0"><tbody>
    <tr><td>Pemasukan pribadi</td><td style="text-align:right" class="pos">${fmt(s.pribadi.masuk)}</td></tr>
    <tr><td>Pengeluaran pribadi</td><td style="text-align:right" class="neg">${fmt(s.pribadi.keluar)}</td></tr>
    <tr><td><b>Sisa untuk tabungan</b></td><td style="text-align:right"><b>${fmt(s.pribadi.masuk-s.pribadi.keluar)}</b></td></tr></tbody></table></div>
   <div class="panel"><h3>🏪 Laporan Usaha (${s.period})</h3>
    <table style="min-width:0"><tbody>
    <tr><td>Total omzet semua cabang</td><td style="text-align:right" class="pos">${fmt(s.usaha.masuk)}</td></tr>
    <tr><td>Total beban semua cabang</td><td style="text-align:right" class="neg">${fmt(s.usaha.keluar)}</td></tr>
    <tr><td><b>Laba bersih usaha</b></td><td style="text-align:right"><b class="${s.usaha.laba>=0?'pos':'neg'}">${fmt(s.usaha.laba)}</b></td></tr></tbody></table></div>`;
  // tren 12 bulan + perbandingan bulan ini vs lalu
  const ty=await api('trend_year');
  if(!ty.error){
    const yc=await api('year_compare');
    const c=ty.compare, arrow=v=>v==null?'—':(v>=0?'📈 +':'📉 ')+v+'%';
    const arrowY=v=>v==null?'(belum ada data tahun lalu)':(v>=0?'📈 +':'📉 ')+v+'% vs tahun lalu';
    const lbl12=ty.months.map(m=>m.bulan.slice(5)+'/'+m.bulan.slice(2,4));
    charts.ty?.destroy();
    let htmlTy=`<div class="panel" style="grid-column:1/-1"><h3>📊 Tren 12 Bulan</h3>
     <table style="min-width:0"><tbody>
      <tr><td>Masuk bulan ini vs lalu</td><td style="text-align:right" class="${(c.delta_masuk_pct??0)>=0?'pos':'neg'}">${fmt(c.bulan_ini.masuk)} ${arrow(c.delta_masuk_pct)}</td></tr>
      <tr><td>Keluar bulan ini vs lalu</td><td style="text-align:right" class="${(c.delta_keluar_pct??1)>0?'neg':'pos'}">${fmt(c.bulan_ini.keluar)} ${arrow(c.delta_keluar_pct)}</td></tr>
      <tr><td>vs bulan sama tahun lalu (${yc.tahun_lalu.bulan})</td>
        <td style="text-align:right" class="small">masuk ${fmt(yc.ini.masuk)} ${arrowY(yc.delta_masuk_pct)}<br>keluar ${fmt(yc.ini.keluar)} ${arrowY(yc.delta_keluar_pct)}</td></tr></tbody></table>
     <canvas id="chartYear" style="margin-top:10px"></canvas></div>`;
    $('reportCards').insertAdjacentHTML('afterend','');
    document.getElementById('reportCards').parentElement.insertAdjacentHTML('beforeend',htmlTy);
    const cv=document.getElementById('chartYear'); if(cv&&window.Chart){
      charts.ty=new Chart(cv,{type:'bar',
        data:{labels:lbl12,datasets:[
          {label:'Masuk',data:ty.months.map(m=>m.masuk),backgroundColor:'rgba(22,131,111,.75)',borderRadius:6},
          {label:'Keluar',data:ty.months.map(m=>m.keluar),backgroundColor:'rgba(192,57,43,.7)',borderRadius:6}]},
        options:{plugins:{legend:{position:'bottom',labels:{usePointStyle:true,padding:14}}},scales:{x:{stacked:false},y:{ticks:{callback:v=>' '+(v/1000)+'k'}}}}});
    }
  }
  const labels=['Pribadi',...s.cabang.map(c=>c.name)];
  const vals=[s.pribadi.keluar,...s.cabang.map(c=>c.keluar)];
  charts.r?.destroy();
  charts.r=new Chart($('chartCat'),{type:'doughnut',
    data:{labels,datasets:[{data:vals,backgroundColor:labels.map((l,i)=>scopeColor(i)),borderWidth:3,borderColor:getComputedStyle(document.documentElement).getPropertyValue('--fd-surface')}]},
    options:{plugins:{legend:{position:'bottom',labels:{usePointStyle:true,padding:16}}},cutout:'62%'}});
}

/* ===== AI ===== */
function openAI(){
  $('modal').innerHTML=`<div class="modal-bg" onclick="if(event.target===this)closeModal()">
   <div class="modal" style="width:min(500px,94vw)">
    <h1 style="font-size:17px">🤖 Tanya Asisten AI</h1>
    <div class="sub">Contoh: "cabang mana paling untung?" atau "pengeluaran terbesarku apa?"</div>
    <input id="aiq" placeholder="Tulis pertanyaanmu...">
    <button onclick="askAI()">Tanya</button>
    <div id="aians" class="small" style="margin-top:12px;white-space:pre-wrap"></div></div></div>`;
  aiq.focus();
}
async function askAI(){
  aians.textContent='Berpikir...';
  const d=await api('ai_ask',{question:aiq.value});
  aians.textContent=d.answer||d.error;
}

function closeModal(){$('modal').innerHTML='';}
$('lpass').addEventListener('keydown',e=>{if(e.key==='Enter')login();});
if('serviceWorker' in navigator)navigator.serviceWorker.register('sw.js').catch(()=>{});
boot();
</script>
</body>
</html>
