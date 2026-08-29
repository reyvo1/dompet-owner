<?php
// Cron kwitansi langganan: generate invoice jatuh tempo + notif. Jadwal: subinv-cron.bat
require __DIR__ . '/../src/subinv.php';
$r = subinv_run_all();
echo date('c') . " langganan dibuat: " . ($r['created'] ?? '?') . "\n";
