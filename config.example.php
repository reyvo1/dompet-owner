<?php
/* ============================================================
   DOMPET OWNER — Konfigurasi (contoh)
   Salin file ini ke src/config.php lalu isi nilai aslinya.
   src/config.php TIDAK di-commit (.gitignore) karena berisi sandi.
   ============================================================ */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3308);
define('DB_NAME', 'dompet_owner');
define('DB_USER', 'root');
define('DB_PASS', 'GANTI_PASSWORD_INI');

date_default_timezone_set('Asia/Jakarta');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}
