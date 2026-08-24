<?php
// Tes logika bot TANPA Telegram asli.
error_reporting(E_ALL & ~E_NOTICE);
define('BOT_TEST', true);
// mock tg(): config memakai function_exists guard, jadi kita definisikan SEBELUM require
function tg(string $method, array $params = []): ?array {
    $GLOBALS['tg_calls'][] = ['method' => $method] + $params;
    return ['ok' => true];
}
require_once __DIR__ . '/../src/config.php';
require __DIR__ . '/../bot/telegram.php';

$pass = true;
function check(bool $ok, string $label): void { global $pass; echo ($ok?'PASS':'FAIL')." - $label\n"; if(!$ok) $pass=false; }
function msg(string $chatId, string $text): string {
    return handle_message(['chat'=>['id'=>$chatId],'text'=>$text]);
}

// reset transaksi, kas 0, sesi chat (tabel settings lama sudah jadi app_settings)
db()->exec("DELETE FROM transactions; UPDATE wallets SET balance=0;
  DELETE FROM app_settings WHERE k LIKE 'chat:%' OR k LIKE 'login_code:%'; UPDATE users SET telegram_verified=0;");
$GLOBALS['tg_calls'] = [];

// 1. chat belum dikenal -> disuruh hubungkan via /mulai atau /start
$r = msg('111', 'masuk 10000 tes');
check(str_contains($r, 'mulai') || str_contains($r, '/start'), 'chat tak dikenal diminta login');

// 2. login salah password
$r = msg('111', '/start budi wrongpass');
check(str_contains($r, 'gagal'), 'login salah password ditolak');

// 3. login benar
$r = msg('111', '/start budi kariawan123');
check(str_contains($r, 'berhasil'), 'login budi berhasil');

// 4. input penjualan via bot
$r = msg('111', 'masuk 300000 jual nasi goreng');
check(str_contains($r, 'Tercatat') && str_contains($r, '300.000'), 'input masuk 300rb tercatat');

// 5. notif owner: chat id owner belum diisi -> tg() tidak dipanggil (by design),
//    jadi kita set dulu chat id owner lalu tes ulang alurnya
db()->exec("UPDATE owner_profile SET telegram_chat_id='ownerchat' WHERE id=1");
$GLOBALS['tg_calls'] = [];
msg('111', 'masuk 300000 jual nasi goreng');
$notifs = array_values(array_filter($GLOBALS['tg_calls'], fn($c) => $c['method'] === 'sendMessage' && str_contains($c['text'] ?? '', '[Cabang Contoh 1]')));
check(count($notifs) >= 1 && str_contains($notifs[0]['text'], '300.000'),
    'notif owner berisi cabang & nominal');

// 6. pengeluaran usaha via bot
msg('111', 'keluar 75000 beli gas');
$t = (float)db()->query("SELECT COALESCE(SUM(balance),0) s FROM wallets")->fetch()['s'];
check($t == 525000.0, 'kas owner = 600rb - 75rb = 525rb');

// 7. laporan harian bot
$r = msg('111', '/hariini');
check(str_contains($r, 'Masuk : Rp 600.000') && str_contains($r, 'Keluar: Rp 75.000'), '/hariini benar');

// 8. laporan web tetap terpisah
$p = laporan_bulan(date('Y-m'), null);
check($p['keluar'] == 0.0, 'transaksi bot tidak bocor ke laporan pribadi owner');

echo $pass ? "\nSEMUA TES BOT LULUS\n" : "\nADA TES GAGAL\n";
// debug dump
foreach ($GLOBALS['tg_calls'] as $c) echo "DBG ", $c['method'], " | ", mb_substr($c['text'] ?? '(none)', 0, 100), PHP_EOL;
exit($pass?0:1);
