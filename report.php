<?php
// LAPORAN BULANAN SIAP CETAK/PDF — dibuka di browser, print -> save as PDF
// URL: /report.php?period=2026-09
declare(strict_types=1);
require __DIR__ . '/src/config.php';
session_start();
$u = null;
if (!empty($_SESSION['user_id'])) {
    $st = db()->prepare("SELECT * FROM users WHERE id=? AND active=1");
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();
}
if (!$u || $u['role'] !== 'owner') { http_response_code(403); exit('Khusus owner — login dulu di dashboard.'); }

$period = preg_match('/^\d{4}-\d{2}$/', $_GET['period'] ?? '') ? $_GET['period'] : date('Y-m');
$bulanNama = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][(int)substr($period,5,2)] . ' ' . substr($period,0,4);

require_once __DIR__ . '/src/ledger.php';
$pl = profit_loss($period);
$bs = balance_sheet();
$cf = cash_flow($period);

function sumq(string $sql, array $a=[]): float {
    $st = db()->prepare($sql); $st->execute($a);
    return (float)$st->fetch()['s'];
}

$pribadiIn  = sumq("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE scope='pribadi' AND type='masuk' AND DATE_FORMAT(tx_date,'%Y-%m')=?", [$period]);
$pribadiOut = sumq("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE scope='pribadi' AND type='keluar' AND DATE_FORMAT(tx_date,'%Y-%m')=?", [$period]);

$cabang = [];
foreach (db()->query("SELECT * FROM businesses WHERE active=1 ORDER BY id") as $b) {
    $bid = (int)$b['id'];
    $cabang[] = [
        'name'=>$b['name'], 'icon'=>$b['icon'],
        'masuk'=>sumq("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE business_id=? AND type='masuk' AND DATE_FORMAT(tx_date,'%Y-%m')=?", [$bid,$period]),
        'keluar'=>sumq("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE business_id=? AND type='keluar' AND DATE_FORMAT(tx_date,'%Y-%m')=?", [$bid,$period]),
        'n_tx'=>sumq("SELECT COUNT(*) s FROM transactions WHERE business_id=? AND DATE_FORMAT(tx_date,'%Y-%m')=?", [$bid,$period]),
    ];
}
$totalIn  = array_sum(array_column($cabang,'masuk')) + $pribadiIn;
$totalOut = array_sum(array_column($cabang,'keluar')) + $pribadiOut;

// top 10 pengeluaran bulan itu
$stT = db()->prepare("SELECT t.tx_date,t.description,t.amount,c.name cat,b.name biz FROM transactions t
    LEFT JOIN categories c ON c.id=t.category_id LEFT JOIN businesses b ON b.id=t.business_id
    WHERE t.type='keluar' AND DATE_FORMAT(t.tx_date,'%Y-%m')=? ORDER BY t.amount DESC LIMIT 10");
$stT->execute([$period]);
$top = $stT->fetchAll();

$nTx = sumq("SELECT COUNT(*) s FROM transactions WHERE DATE_FORMAT(tx_date,'%Y-%m')=?", [$period]);
$rp = fn($v) => 'Rp ' . number_format((float)$v, 0, ',', '.');

// pajak & piutang ringkas
// pajak terutang & piutang ringkas (tabel: tax_lines)
$pajakTerutang = sumq("SELECT COALESCE(SUM(l.tax_amount),0) s FROM tax_lines l JOIN transactions t ON t.id=l.tx_id
    WHERE l.tax_type<>'non_pajak' AND DATE_FORMAT(t.tx_date,'%Y-%m')=?", [$period]);
$piutangOpen   = (float)(db()->query("SELECT COALESCE(SUM(amount-paid_amount),0) s FROM receivables WHERE status='open'")->fetch()['s'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan <?= htmlspecialchars($bulanNama) ?> — Dompet Owner</title>
<style>
 :root{--ink:#111827;--mut:#6b7280;--line:#e5e7eb;--green:#16836f;--red:#c0392b;--brand:#155eef;}
 *{box-sizing:border-box;margin:0;padding:0}
 body{font-family:'Segoe UI',Arial,sans-serif;color:var(--ink);padding:32px;max-width:900px;margin:auto;background:#fff}
 h1{font-size:22px} h2{font-size:15px;margin:26px 0 10px;padding-bottom:6px;border-bottom:2px solid var(--ink)}
 .sub{color:var(--mut);font-size:13px}
 table{width:100%;border-collapse:collapse;font-size:13px;margin-top:8px}
 th{text-align:left;color:var(--mut);font-size:11px;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid var(--line);padding:6px 8px}
 td{padding:7px 8px;border-bottom:1px solid var(--line)}
 .r{text-align:right;font-variant-numeric:tabular-nums}
 .pos{color:var(--green);font-weight:700}.neg{color:var(--red);font-weight:700}
 .cards{display:flex;gap:12px;flex-wrap:wrap;margin-top:14px}
 .card{flex:1;min-width:150px;border:1px solid var(--line);border-radius:12px;padding:14px}
 .card .l{font-size:11px;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}
 .card .v{font-size:19px;font-weight:800;margin-top:4px}
 .foot{margin-top:34px;color:var(--mut);font-size:11px;display:flex;justify-content:space-between}
 .tot td{font-weight:800;border-top:2px solid var(--ink)}
 @media print{body{padding:10mm}.card{break-inside:avoid}}
 @page{margin:14mm}
</style>
</head>
<body>
<div style="display:flex;justify-content:space-between;align-items:flex-start">
 <div><h1>📒 Laporan Keuangan Dompet Owner</h1>
      <div class="sub">Periode: <b><?= $bulanNama ?></b></div></div>
 <button onclick="window.print()" style="border:none;background:#155eef;color:#fff;padding:10px 18px;border-radius:9px;font-weight:700;cursor:pointer">🖨️ Cetak / Simpan PDF</button>
</div>

<div class="cards">
 <div class="card"><div class="l">Total Masuk</div><div class="v pos"><?= $rp($totalIn) ?></div></div>
 <div class="card"><div class="l">Total Keluar</div><div class="v neg"><?= $rp($totalOut) ?></div></div>
 <div class="card"><div class="l">Surplus/Bulan</div><div class="v" style="color:<?= $totalIn-$totalOut>=0?'var(--green)':'var(--red)' ?>"><?= $rp($totalIn-$totalOut) ?></div></div>
 <div class="card"><div class="l">Total Kas Sekarang</div><div class="v"><?= $rp(total_kas()) ?></div></div>
</div>
<div class="sub" style="margin-top:8px"><?= $nTx ?> transaksi tercatat periode ini • Pajak terutang: <b><?= $rp($pajakTerutang) ?></b> • Piutang belum tertagih: <b><?= $rp($piutangOpen) ?></b></div>

<h2>Pemasukan vs Pengeluaran per Scope</h2>
<table>
<tr><th>Scope</th><th class="r">Masuk</th><th class="r">Keluar</th><th class="r">Selisih</th></tr>
<?php foreach ($cabang as $c): if ($c['n_tx']==0 && $c['masuk']==0) continue; ?>
<tr><td><?= htmlspecialchars($c['icon'].' '.$c['name']) ?></td>
 <td class="r pos"><?= $rp($c['masuk']) ?></td><td class="r neg"><?= $rp($c['keluar']) ?></td>
 <td class="r" style="font-weight:700"><?= $rp($c['masuk']-$c['keluar']) ?></td></tr>
<?php endforeach; ?>
<tr><td>👤 Pribadi Owner</td><td class="r pos"><?= $rp($pribadiIn) ?></td><td class="r neg"><?= $rp($pribadiOut) ?></td>
 <td class="r" style="font-weight:700"><?= $rp($pribadiIn-$pribadiOut) ?></td></tr>
<tr class="tot"><td>TOTAL</td><td class="r"><?= $rp($totalIn) ?></td><td class="r"><?= $rp($totalOut) ?></td><td class="r"><?= $rp($totalIn-$totalOut) ?></td></tr>
</table>

<h2>Laba Rugi (Akuntansi)</h2>
<table>
<?php foreach ($pl as $k => $v): if(!is_string($k) || !in_array($k,['pendapatan','hpp','beban','laba_bersih'])) continue;
 $lbl=['pendapatan'=>'Pendapatan neto','hpp'=>'Harga Pokok Penjualan','beban'=>'Beban operasional','laba_bersih'=>'LABA BERSIH'][$k]; ?>
<tr class="<?= $k==='laba_bersih'?'tot':'' ?>"><td><?= $lbl ?></td><td class="r <?= $k==='laba_bersih'?((float)$v>=0?'pos':'neg'):'' ?>"><?= $rp($v) ?></td></tr>
<?php endforeach; ?>
</table>

<h2>Neraca (per hari ini)</h2>
<table>
<?php $isTot=false; foreach ($bs as $k => $v):
  if(!is_string($k)) continue;
  $kl=(string)$k; $isTot = str_contains($kl,'total') || str_contains($kl,'seimbang'); ?>
<tr class="<?= $isTot?'tot':'' ?>"><td><?= ucfirst(str_replace('_',' ',$kl)) ?></td><td class="r"><?= $rp($v) ?></td></tr>
<?php endforeach; ?>
</table>

<h2>Arus Kas (<?= $bulanNama ?>)</h2>
<table>
<?php foreach ($cf['per_kas'] as $r): ?>
<tr><td>💵 <?= htmlspecialchars($r['name']) ?></td><td class="r <?= $r['net']>=0?'pos':'neg' ?>"><?= $rp($r['net']) ?></td></tr>
<?php endforeach; ?>
<tr class="tot"><td>Kas masuk</td><td class="r"><?= $rp($cf['masuk']) ?></td></tr>
<tr class="tot"><td>Kas keluar</td><td class="r"><?= $rp($cf['keluar']) ?></td></tr>
<tr class="tot"><td>ARUS KAS BERSIH</td><td class="r <?= $cf['bersih']>=0?'pos':'neg' ?>"><?= $rp($cf['bersih']) ?></td></tr>
</table>

<h2>10 Pengeluaran Terbesar</h2>
<table>
<tr><th>Tanggal</th><th>Keterangan</th><th>Kategori</th><th>Cabang</th><th class="r">Jumlah</th></tr>
<?php foreach ($top as $t): ?>
<tr><td><?= htmlspecialchars($t['tx_date']) ?></td><td><?= htmlspecialchars($t['description']) ?></td>
 <td><?= htmlspecialchars($t['cat'] ?? '—') ?></td><td><?= htmlspecialchars($t['biz'] ?? 'pribadi') ?></td>
 <td class="r neg"><?= $rp($t['amount']) ?></td></tr>
<?php endforeach; if(!$top): ?>
<tr><td colspan="5" style="color:var(--mut)">Tidak ada pengeluaran periode ini.</td></tr>
<?php endif; ?>
</table>

<div class="foot"><span>Dompet Owner — dicetak <?= date('d/m/Y H:i') ?></span><span>Dokumen internal</span></div>
</body>
</html>
