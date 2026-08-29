<?php
// Webhook Midtrans (POST notification). URL: https://DOMAIN/midtrans.php
// Pastikan IP whitelist Midtrans & HTTPS. Response JSON kecil.
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/midtrans.php';

header('Content-Type: application/json');
$raw = file_get_contents('php://input');
$res = midtrans_handle_notification($raw);
file_put_contents(__DIR__ . '/../tools/midtrans-webhook.log',
    date('c')." ".substr($raw, 0, 500)." => ".json_encode($res)."\n", FILE_APPEND);
echo json_encode($res);
