<?php
// Router tunggal Dompet Owner (Fase 2)
// /api.php* -> API JSON; lainnya -> dashboard
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($uri === '/api.php' || str_starts_with($uri, '/api.php?')) {
    chdir(dirname(__DIR__));
    require __DIR__ . '/../api.php';
    return true;
}
if ($uri === '/' || $uri === '/index.php') {
    chdir(__DIR__);
    include __DIR__ . '/index.php';
    return true;
}
if ($uri === '/report.php' || str_starts_with($uri, '/report.php?')) {
    chdir(dirname(__DIR__));
    require __DIR__ . '/../report.php';
    return true;
}
if ($uri === '/invoice.php' || str_starts_with($uri, '/invoice.php?')) {
    chdir(dirname(__DIR__));
    require __DIR__ . '/../invoice.php';
    return true;
}
if ($uri === '/receipt.php' || str_starts_with($uri, '/receipt.php?')) {
    chdir(dirname(__DIR__));
    require __DIR__ . '/../receipt.php';
    return true;
}
if ($uri === '/tv.php') {
    chdir(dirname(__DIR__));
    require __DIR__ . '/../tv.php';
    return true;
}
if (is_file(__DIR__ . $uri)) {
    return false;
}
http_response_code(404);
echo 'Not found';
return true;
