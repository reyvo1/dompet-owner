<?php
// CRON HARIAN (06:00, bareng rec-cron): notif tagihan jatuh tempo ke Telegram owner
// Jalankan: php bill-notify.php   (dari Task Scheduler)
declare(strict_types=1);
require __DIR__ . '/../src/config.php';

$today = new DateTimeImmutable('today');
$todayStr = $today->format('Y-m-d');
$rows = [];

foreach (db()->query("SELECT b.*, biz.name biz FROM bills b LEFT JOIN businesses biz ON biz.id=b.business_id WHERE b.active=1") as $b) {
    if ((int)$b['day_of_month'] !== (int)$today->format('j')) continue;
    // sudah dibayar bulan ini?
    $paid = db()->query("SELECT COUNT(*) c FROM transactions
        WHERE description LIKE CONCAT('[BILL#{$b['id']}]%')
        AND DATE_FORMAT(tx_date,'%Y-%m')='".$today->format('Y-m')."'")->fetch()['c'];
    if ($paid) continue;
    $rows[] = '🧾 '.$b['name'].' — Rp '.number_format((float)$b['amount'],0,',','.').($b['biz'] ? ' ['.$b['biz'].']' : '');
}

// piutang pelanggan jatuh tempo <= 3 hari (belum lunas)
foreach (db()->query("SELECT r.*, b.name biz FROM receivables r LEFT JOIN businesses b ON b.id=r.business_id
    WHERE r.status<>'paid' AND r.due_date IS NOT NULL AND r.due_date <= DATE_ADD('{$todayStr}', INTERVAL 3 DAY)") as $r) {
    $sisa = (float)$r['amount'] - (float)($r['paid_amount'] ?? 0);
    if ($sisa <= 0) continue;
    $late = $r['due_date'] < $todayStr ? ' TERLAMBAT!' : '';
    $rows[] = '📋 Piutang '.$r['debtor_name'].' — Rp '.number_format($sisa,0,',','.').
        ' (jatuh tempo '.$r['due_date'].$late.')'.($r['biz'] ? ' ['.$r['biz'].']' : '');
}

// stok menipis (<= min_stock)
foreach (db()->query("SELECT p.name, p.stock, p.unit, p.min_stock, b.name biz FROM products p
    LEFT JOIN businesses b ON b.id=p.business_id
    WHERE p.active=1 AND p.min_stock > 0 AND p.stock <= p.min_stock") as $pr) {
    $rows[] = '📦 STOK MENIPIS '.$pr['name'].' - sisa '.$pr['stock'].' '.$pr['unit'].
        ' (minimum '.$pr['min_stock'].')'.($pr['biz'] ? ' ['.$pr['biz'].']' : '');
}

// kartu kredit / utang jatuh tempo hari ini
foreach (db()->query("SELECT * FROM liabilities WHERE outstanding > 0 AND due_day = ".(int)$today->format('j')) as $l) {
    $min = max((float)$l['outstanding'] * (float)($l['min_pay_pct'] ?: 0) / 100, 50000);
    $rows[] = '💳 '.$l['name'].' — minimum Rp '.number_format($min,0,',','.').' (outstanding Rp '.number_format((float)$l['outstanding'],0,',','.').')';
}

if (!$rows) {
    echo "[$todayStr] Tidak ada tagihan jatuh tempo\n";
    exit;
}

$msg = "⏰ TAGIHAN HARI INI ($todayStr)\n\n".implode("\n", $rows)."\n\nTotal ".count($rows)." kewajiban — buka Dompet Owner untuk bayar.";
notify_owner_force($msg);
echo "[$todayStr] Notif terkirim: ".count($rows)." tagihan\n";
