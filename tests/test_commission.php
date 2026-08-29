<?php
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/commission.php';
$id = add_transaction(['type'=>'masuk','amount'=>200000,'description'=>'tes komisi e2e','source'=>'bot_kariawan','scope'=>'usaha','user_id'=>2,'business_id'=>1]);
echo "tx=$id\n";
foreach (db()->query("SELECT tx_id,base_amount,pct,amount,status,period FROM commissions") as $r) print_r($r);
echo "rekap: "; print_r(commission_pending_by_user(date('Y-m')));
