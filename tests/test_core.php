<?php
// Tes fungsional inti: transaksi owner pribadi, usaha via bot, transfer kas
require __DIR__ . '/../src/config.php';
$pdo = db();
$pass = true;
function check(bool $ok, string $label): void { global $pass; echo ($ok ? 'PASS' : 'FAIL') . " - $label\n"; if (!$ok) $pass = false; }

// reset
$pdo->exec("DELETE FROM transactions; UPDATE wallets SET balance=0;");
$b1 = (int)$pdo->query("SELECT id FROM businesses ORDER BY id LIMIT 1")->fetch()['id'];

// 1. Owner beli barang pribadi 100rb dari Kas Utama
add_transaction(['type'=>'keluar','amount'=>100000,'description'=>'Beli sepatu (pribadi)','scope'=>'pribadi']);
check(total_kas() == -0 + (-100000), 'kas owner berkurang 100rb setelah belanja pribadi');

// 2. Kariawan cabang 1 laku penjualan 500rb -> masuk kas owner, catatan di cabang 1
$idTx = add_transaction(['type'=>'masuk','amount'=>500000,'description'=>'Penjualan pagi','source'=>'bot_kariawan','business_id'=>$b1]);
check(total_kas() == 400000, 'kas owner bertambah 500rb dari penjualan cabang');

// 3. Owner keluarin 150rb buat beli stok cabang 1 (uang dompet owner, catatan ke cabang)
add_transaction(['type'=>'keluar','amount'=>150000,'description'=>'Beli stok cabang','business_id'=>$b1,'scope'=>'usaha']);
check(total_kas() == 250000, 'kas owner berkurang 150rb utk pengeluaran usaha');

// 4. Transfer Kas Utama -> Bank 250rb
$w = wallets_all();
add_transaction(['type'=>'transfer','amount'=>250000,'description'=>'Nabung ke bank','wallet_id'=>$w[0]['id'],'wallet_dest_id'=>$w[1]['id']]);
check(abs(total_kas() - 250000) < 0.001, 'transfer antar kas tidak mengubah total');

// 5. Laporan terpisah
$p = laporan_bulan(date('Y-m'), null);
$c = laporan_bulan(date('Y-m'), $b1);
check($p['keluar'] == 100000, 'laporan pribadi: keluar 100rb saja');
check($c['masuk'] == 500000 && $c['keluar'] == 150000, 'laporan cabang: masuk 500rb, keluar 150rb');
echo $pass ? "\nSEMUA TES LULUS\n" : "\nADA TES GAGAL\n";
exit($pass ? 0 : 1);
