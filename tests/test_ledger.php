<?php
// Tes mesin akuntansi: double-entry balance, pajak, prive, laporan
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
require_once __DIR__ . '/../src/config.php';
$pass = true;
function check(bool $ok, string $label): void { global $pass; echo ($ok?'PASS':'FAIL')." - $label\n"; if(!$ok) $pass=false; }

// reset
db()->exec("DELETE FROM tax_lines; DELETE FROM journal_lines; DELETE FROM journal_entries;
  DELETE FROM transactions; UPDATE wallets SET balance=0;
  DELETE FROM budgets; DELETE FROM tax_rules WHERE name LIKE 'TEST%';");

$b1 = (int)db()->query("SELECT id FROM businesses ORDER BY id LIMIT 1")->fetch()['id'];

// rule PBJT 10% khusus cabang 1 utk penjualan
db()->prepare("INSERT INTO tax_rules (name,tax_type,rate_pct,business_id,category_name,tx_kind) VALUES
  ('TEST PBJT 10%','pbjt',10,?,'Penjualan','masuk')")->execute([$b1]);

// 1. Penjualan cabang 1 dengan kategori "Penjualan": 1.100.000 (PBJT 10% -> neto 1jt, pajak 110rb)
$catPenjualan = (int)db()->query("SELECT id FROM categories WHERE name LIKE '%Penjualan%' LIMIT 1")->fetch()['id'];
$id1 = add_transaction(['type'=>'masuk','amount'=>1100000,'description'=>'Penjualan hari 1',
    'category_id'=>$catPenjualan,
    'source'=>'bot_kariawan','business_id'=>$b1]);
$bal = db()->query("SELECT SUM(jl.debit)=SUM(jl.credit) ok FROM journal_lines jl
    JOIN journal_entries je ON je.id=jl.entry_id WHERE je.tx_id=$id1")->fetch()['ok'];
check($bal==1, 'jurnal penjualan seimbang (debit=kredit)');
$tl = db()->query("SELECT * FROM tax_lines WHERE tx_id=$id1")->fetch();
check($tl && $tl['tax_amount']==100000.0 && $tl['tax_type']=='pbjt' && $tl['base_amount']==1000000.0,
    'PBJT 10% dari bruto 1.1jt: DPP 1jt, pajak 100rb (harga menu termasuk pajak)');
$neto = db()->query("SELECT SUM(jl.credit) c FROM journal_lines jl JOIN journal_entries je ON je.id=jl.entry_id
    WHERE je.tx_id=$id1 AND jl.account_code='4-1000'")->fetch()['c'];
check((float)$neto==1000000.0, 'pendapatan dicatat NETO 1jt (bukan bruto)');
$utang = db()->query("SELECT SUM(jl.credit) c FROM journal_lines jl JOIN journal_entries je ON je.id=jl.entry_id
    WHERE je.tx_id=$id1 AND jl.account_code='2-1100'")->fetch()['c'];
check((float)$utang==100000.0, 'utang pajak PBJT terpisah dari pendapatan (100rb)');

// 2. Pengeluaran pribadi owner: makan 50rb -> PRIVE bukan beban usaha
$id2 = add_transaction(['type'=>'keluar','amount'=>50000,'description'=>'Makan malam',
    'scope'=>'pribadi']);
$acc = db()->query("SELECT jl.account_code FROM journal_lines jl JOIN journal_entries je ON je.id=jl.entry_id
    WHERE je.tx_id=$id2 AND jl.debit>0")->fetch()['account_code'];
check($acc=='3-1100', 'pengeluaran pribadi masuk Prive (3-1100), bukan beban usaha');

// 3. Beli barang usaha cabang 1 (pakai kategori): 200rb -> beban
$catBarang = (int)db()->query("SELECT id FROM categories WHERE name LIKE '%Beli Barang%' LIMIT 1")->fetch()['id'];
$id3 = add_transaction(['type'=>'keluar','amount'=>200000,'description'=>'Beli stok',
    'category_id'=>$catBarang,
    'business_id'=>$b1]);
$acc3 = db()->query("SELECT jl.account_code FROM journal_lines jl JOIN journal_entries je ON je.id=jl.entry_id
    WHERE je.tx_id=$id3 AND jl.debit>0")->fetch()['account_code'];
check($acc3=='6-1000', 'beli barang usaha masuk Beban Barang Usaha (6-1000)');

// 4. Transfer kas tidak mengubah total & jurnal seimbang
add_transaction(['type'=>'transfer','amount'=>300000,'description'=>'pindah bank',
    'wallet_id'=>1,'wallet_dest_id'=>2]);

// 5. Laporan laba rugi: usaha = 1jt - 200rb = 800rb; pajak bukan beban (sudah dipotong omzet)
$pl = profit_loss(date('Y-m'));
check(abs($pl['pendapatan']-1000000)<0.01, "laba rugi: pendapatan neto 1jt (dapat ".number_format($pl['pendapatan']).")");
check(abs($pl['beban']-200000)<0.01, "laba rugi: beban 200rb");
check(abs($pl['laba_bersih']-800000)<0.01, "laba bersih 800rb (dapat ".number_format($pl['laba_bersih']).")");

// 6. Neraca seimbang: aset = liab+ekuitas+laba berjalan
$bs = balance_sheet();
check(abs($bs['total_aset_net']-$bs['total_liab_ekuitas'])<0.01,
    "neraca seimbang: aset ".number_format($bs['total_aset_net'])." = liab+ekuitas+lababerjalan ".number_format($bs['total_liab_ekuitas']));

// 7. Arus kas
$cf = cash_flow(date('Y-m'));
check(abs($cf['bersih']-850000)<0.01, "arus kas bersih 850rb (1.1jt masuk - 50rb - 200rb, transfer net 0) (dapat ".number_format($cf['bersih']).")");

// 8. Unresolved: transaksi masuk tanpa aturan cocok di cabang lain
$b2 = (int)db()->query("SELECT id FROM businesses WHERE id<>$b1 LIMIT 1")->fetch()['id'];
$id4 = add_transaction(['type'=>'masuk','amount'=>50000,'description'=>'jualan cabang lain',
    'source'=>'bot_kariawan','business_id'=>$b2]);
$unres = db()->query("SELECT status FROM tax_lines WHERE tx_id=$id4")->fetch();
check($unres && $unres['status']=='unresolved', 'cabang tanpa aturan -> pajak status UNRESOLVED (escape mode TAMASYA)');

echo $pass ? "\nSEMUA TES AKUNTANSI LULUS\n" : "\nADA TES GAGAL\n";
exit($pass?0:1);
