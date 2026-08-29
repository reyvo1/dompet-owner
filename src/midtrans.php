<?php
// ============================================================
// MIDTRANS SNAP — QRIS dinamis + status bayar otomatis (webhook)
// Setting: midtrans_server_key (sk-sandbox-... / sk-prod-...)
// Webhook URL: https://DOMAIN-mu/midtrans.php
// ============================================================
require_once __DIR__ . '/config.php';

function midtrans_enabled(): bool {
    return strpos(cfg('midtrans_server_key',''), 'sk-') === 0;
}

function midtrans_is_production(): bool {
    return strpos(cfg('midtrans_server_key',''), 'sk-prod-') === 0;
}

function midtrans_base(): string {
    return midtrans_is_production()
        ? 'https://app.midtrans.com/snap/v1/transactions'
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
}

/* Buat Snap transaction; return ['token'=>..,'url'=>..] atau null kalau tidak aktif/gagal */
function midtrans_snap_create(array $i): ?array {
    if (!midtrans_enabled() || !function_exists('curl_init')) return null;
    $payload = [
        'transaction_details' => [
            'order_id' => $i['number'].'-'.time(),
            'gross_amount' => (int)round((float)$i['amount']),
        ],
        'item_details' => [[
            'id' => 'INV'.$i['inv_id'], 'price' => (int)round((float)$i['amount']),
            'quantity' => 1, 'name' => mb_substr($i['description'] ?: 'Pembayaran', 0, 50),
        ]],
        'customer_details' => ['first_name' => mb_substr($i['customer'], 0, 60)],
        'enabled_payments' => ['qris','gopay','shopeepay','bank_transfer','credit_card'],
        'expiry' => ['unit' => 'day', 'duration' => 3],
    ];
    $ch = curl_init(midtrans_base());
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic '.base64_encode(cfg('midtrans_server_key').':'),
        ],
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return null;
    $j = json_decode((string)$res, true);
    if (empty($j['token'])) return null;
    return ['token'=>$j['token'], 'url'=>$j['redirect_url'] ?? ('https://app.sandbox.midtrans.com/snap/v2/vtweb/'.$j['token'])];
}

/* Webhook: panggil dari public/midtrans.php. Return array status utk debug/log. */
function midtrans_handle_notification(string $rawBody): array {
    $n = json_decode($rawBody, true);
    if (!$n || empty($n['order_id'])) return ['ok'=>false,'msg'=>'payload tidak valid'];
    $sk = cfg('midtrans_server_key');
    $expect = hash('sha512', ($n['order_id'] ?? '').($n['status_code'] ?? '').($n['gross_amount'] ?? '').$sk);
    if (!hash_equals($expect, (string)($n['signature_key'] ?? '')))
        return ['ok'=>false,'msg'=>'signature tidak valid'];
    $status = $n['transaction_status'] ?? '';
    if (!in_array($status, ['settlement','capture'], true)) return ['ok'=>true,'msg'=>'status '.$status.' (diabaikan)'];
    // order_id = NUMBER-timestamp; nomor kwitansi = bagian sebelum '-'
    $number = preg_replace('/-\d+$/', '', (string)$n['order_id']);
    $st = db()->prepare("SELECT * FROM invoices WHERE number=? AND status='unpaid'");
    $st->execute([$number]);
    if (!$inv = $st->fetch()) return ['ok'=>true,'msg'=>'kwitansi tidak ada / sudah lunas'];
    // catat pembayaran seperti tombol "Lunasi"
    require_once __DIR__ . '/ledger.php';
    $catIn = db()->query("SELECT id FROM categories WHERE name LIKE '%Penjualan%' LIMIT 1")->fetch()['id'] ?? null;
    $txId = add_transaction([
        'type'=>'masuk','amount'=>(float)$inv['amount'],
        'description'=>'[INV '.$inv['number'].'] Lunas via Midtrans ('.($n['payment_type'] ?? '?').')',
        'source'=>'midtrans','user_id'=>null,
        'business_id'=>$inv['business_id'] ? (int)$inv['business_id'] : null,
        'scope'=>$inv['business_id'] ? 'usaha' : 'pribadi', 'category_id'=>$catIn,
    ]);
    db()->prepare("UPDATE invoices SET status='paid', paid_at=NOW(), tx_id=?, paid_via='midtrans' WHERE id=?")
        ->execute([$txId, (int)$inv['id']]);
    notify_owner_force("✅ Kwitansi {$inv['number']} LUNAS via Midtrans\nDari: {$inv['customer_name']}\nJumlah: Rp ".number_format((float)$inv['amount'],0,',','.'));
    return ['ok'=>true,'msg'=>'kwitansi '.$number.' dilunasi'];
}
