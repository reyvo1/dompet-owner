<?php
// ============================================================
// KWITANSI LANGGANAN — generate invoice berulang + notifikasi
// Dipakai api.php (rinv_run) dan tools/subinv-cron.php
// ============================================================
require_once __DIR__ . '/config.php';

function subinv_run_all(): array {
    $today = date('Y-m-d');
    $created = 0;
    $rows = db()->query("SELECT * FROM recurring_invoices WHERE active=1 AND next_date<='$today'")->fetchAll();
    foreach ($rows as $r) {
        $number = 'INV/'.date('Ymd').'/'.strtoupper(bin2hex(random_bytes(2)));
        db()->prepare("INSERT INTO invoices (business_id,number,customer_name,contact_id,amount,description)
            VALUES (?,?,?,?,?,?)")
            ->execute([$r['business_id'] ? (int)$r['business_id'] : null, $number,
                $r['customer_name'], $r['contact_id'] ? (int)$r['contact_id'] : null,
                (float)$r['amount'],
                '[langganan] '.($r['description'] ?: 'Langganan '.$r['frequency'])]);
        $invId = (int)db()->lastInsertId();
        // Midtrans aktif? ikutkan link bayar
        require_once __DIR__ . '/midtrans.php';
        $snap = midtrans_snap_create(['number'=>$number,'amount'=>$r['amount'],'customer'=>$r['customer_name'],
            'description'=>$r['description'],'inv_id'=>$invId]);
        if ($snap) db()->prepare("UPDATE invoices SET snap_token=?, snap_url=? WHERE id=?")
            ->execute([$snap['token'],$snap['url'],$invId]);
        // jadwal berikut
        $next = $r['frequency'] === 'weekly' ? date('Y-m-d', strtotime($r['next_date'].' +7 day'))
                                             : date('Y-m-d', strtotime($r['next_date'].' +1 month'));
        db()->prepare("UPDATE recurring_invoices SET next_date=?, last_created_id=? WHERE id=?")
            ->execute([$next, $invId, (int)$r['id']]);
        $created++;
        notify_owner_force("🧾 Kwitansi langganan dibuat\nNo: {$number}\nKepada: {$r['customer_name']}\nJumlah: Rp ".number_format((float)$r['amount'],0,',','.')
            .($snap ? "\nBayar: {$snap['url']}" : ""));
    }
    return ['ok'=>true,'created'=>$created,'total'=>count($rows)];
}
