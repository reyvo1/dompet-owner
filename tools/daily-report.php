<?php
// CRON HARIAN (20:00): laporan harian ringkas ke Telegram owner
declare(strict_types=1);
require __DIR__ . '/../src/config.php';

$today = date('Y-m-d');
function sumq(string $sql, array $a = []): float {
    $st = db()->prepare($sql); $st->execute($a);
    return (float)$st->fetch()['s'];
}
$in  = sumq("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='masuk' AND tx_date=?", [$today]);
$out = sumq("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='keluar' AND tx_date=?", [$today]);
$n   = sumq("SELECT COUNT(*) s FROM transactions WHERE tx_date=?", [$today]);

// kewajiban 3 hari ke depan
$end = date('Y-m-d', strtotime('+3 days'));
$soon = 0; $soonList = [];
foreach (db()->query("SELECT b.*, biz.name biz FROM bills b LEFT JOIN businesses biz ON biz.id=b.business_id WHERE b.active=1") as $b) {
    for ($i = 0; $i <= 3; $i++) {
        $d = strtotime("+$i days");
        if ((int)date('j', $d) === (int)$b['day_of_month']) {
            $paid = db()->query("SELECT COUNT(*) c FROM transactions WHERE description LIKE CONCAT('[BILL#{$b['id']}]%') AND tx_date='".date('Y-m-d', $d)."'")->fetch()['c'];
            if (!$paid) { $soon += (float)$b['amount']; $soonList[] = date('d/m', $d).' '.$b['name']; }
        }
    }
}

$rp = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
$msg = "🌙 Laporan Hari Ini ($today)\n\n".
    "📥 Masuk: ".$rp($in)."\n📤 Keluar: ".$rp($out)."\n".
    "📊 Selisih: ".$rp($in - $out)."\n🔢 Transaksi: ".(int)$n."\n".
    "💰 Total kas: ".$rp(total_kas());
if ($soon > 0) $msg .= "\n\n⏰ Tagihan ≤3 hari: ".$rp($soon)."\n".implode("\n", array_slice($soonList, 0, 5));

notify_owner_force($msg);
echo "[$today] Laporan harian terkirim (masuk $in / keluar $out)\n";
