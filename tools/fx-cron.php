<?php
// Cron harian: tarik kurs otomatis jika fx_auto aktif. Jadwal: fx-cron.bat
require __DIR__ . '/../src/fx.php';
if (cfg('fx_auto') !== '1') { exit("fx_auto off\n"); }
$r = fx_fetch_now();
if (!empty($r['ok'])) {
    $parts = [];
    foreach ($r['updated'] as $u) $parts[] = $u['code'] . '=' . $u['rate'];
    echo date('c') . ' kurs updated: ' . implode(', ', $parts) . "\n";
} else {
    echo date('c') . ' FX FAIL: ' . ($r['error'] ?? '?') . "\n";
}
