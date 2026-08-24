<?php
// DASHBOARD TV — tampilan monitor toko, angka besar, auto-refresh 60 detik
// /tv.php  (tanpa data sensitif: total kas + ringkasan cabang)
declare(strict_types=1);
require __DIR__ . '/src/config.php';
$kas = total_kas();
$period = date('Y-m');
$cabang = [];
foreach (db()->query("SELECT * FROM businesses WHERE active=1 ORDER BY id") as $b) {
    $l = laporan_bulan($period, (int)$b['id']);
    $cabang[] = ['name'=>$b['name'],'icon'=>$b['icon'],'masuk'=>$l['masuk'],'keluar'=>$l['keluar'],'laba'=>$l['laba']];
}
$in = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='masuk' AND DATE_FORMAT(tx_date,'%Y-%m')='$period'")->fetch()['s'];
$out = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='keluar' AND DATE_FORMAT(tx_date,'%Y-%m')='$period'")->fetch()['s'];
$rp = fn($v) => 'Rp ' . number_format((float)$v, 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Dompet Owner — TV</title>
<meta http-equiv="refresh" content="60">
<style>
 *{box-sizing:border-box;margin:0;padding:0}
 body{font-family:'Segoe UI',Arial,sans-serif;background:#0b1020;color:#eef1ff;padding:40px;min-height:100vh}
 h1{font-size:28px;color:#8ab4ff;font-weight:300;letter-spacing:.12em;text-transform:uppercase}
 .clock{font-size:20px;color:#5a6b9e;margin-top:6px}
 .hero{font-size:min(11vw,120px);font-weight:800;background:linear-gradient(90deg,#6ea8ff,#7dffc3);
   -webkit-background-clip:text;background-clip:text;color:transparent;margin:24px 0 8px}
 .lbl{font-size:22px;color:#5a6b9e}
 .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;margin-top:44px}
 .card{background:#131a33;border-radius:20px;padding:28px;border:1px solid #232c52}
 .card .nm{font-size:24px;font-weight:700}
 .card .big{font-size:44px;font-weight:800;margin-top:10px}
 .pos{color:#4ade80}.neg{color:#f87171}.mut{color:#5a6b9e;font-size:17px;margin-top:6px}
</style>
</head>
<body>
<h1>💰 Dompet Owner — <?= date('d M Y') ?></h1>
<div class="clock"><?= date('H:i') ?> · diperbarui otomatis tiap 60 detik</div>

<div class="hero"><?= $rp($kas) ?></div>
<div class="lbl">Total Kas Dompet · Bulan ini masuk <b style="color:#4ade80"><?= $rp($in) ?></b> / keluar <b style="color:#f87171"><?= $rp($out) ?></b></div>

<div class="grid">
<?php foreach ($cabang as $c): ?>
 <div class="card">
  <div class="nm"><?= htmlspecialchars(($c['icon'] ?: '🏪').' '.$c['name']) ?></div>
  <div class="big <?= $c['laba']>=0?'pos':'neg' ?>"><?= $rp($c['laba']) ?></div>
  <div class="mut">masuk <?= $rp($c['masuk']) ?> · keluar <?= $rp($c['keluar']) ?></div>
 </div>
<?php endforeach; ?>
</div>
</body>
</html>
