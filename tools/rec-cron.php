<?php
// CRON HARIAN: posting transaksi berulang yang jatuh tempo
// Jalankan: php rec-cron.php  (dari Task Scheduler jam 06:00)
declare(strict_types=1);
require __DIR__ . '/../src/config.php';

$today = date('Y-m-d');
$n = rec_post_due($today);
if ($n > 0) {
    echo "[$today] Recurring diposting: $n transaksi\n";
} else {
    echo "[$today] Tidak ada recurring jatuh tempo\n";
}
