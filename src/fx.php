<?php
// ============================================================
// KURS OTOMATIS — tarik kurs harian dari open.er-api.com (gratis, tanpa key)
// Dipakai oleh api.php (action fx_fetch) dan tools/fx-cron.php
// ============================================================
require_once __DIR__ . '/config.php';

function fx_fetch_now(): array {
    if (!function_exists('curl_init')) return ['ok'=>false,'error'=>'curl tidak tersedia di server'];
    $ch = curl_init('https://open.er-api.com/v6/latest/USD');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>25, CURLOPT_FOLLOWLOCATION=>true]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return ['ok'=>false,'error'=>'Koneksi gagal: '.$err];
    $j = json_decode((string)$res, true);
    if (empty($j['rates']) || !is_array($j['rates'])) return ['ok'=>false,'error'=>'Respons server kurs tidak valid'];
    $updated = [];
    $pdo = db();
    foreach ($pdo->query("SELECT code FROM fx_rates WHERE code<>'IDR'") as $r) {
        $code = $r['code'];
        if (!isset($j['rates'][$code])) continue;
        $rate = (float)$j['rates'][$code];
        if ($rate <= 0) continue;
        $pdo->prepare("UPDATE fx_rates SET rate=? WHERE code=?")->execute([$rate, $code]);
        $updated[] = ['code'=>$code, 'rate'=>$rate];
    }
    return ['ok'=>true, 'updated'=>$updated, 'source'=>'open.er-api.com', 'time'=>date('c')];
}
