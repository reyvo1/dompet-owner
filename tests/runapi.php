<?php
// runner terpisah: php runapi.php "action=xxx" '<json-body>'
// api.php selalu exit() di akhir, jadi tiap action dijalankan di proses sendiri.
// Kita matikan warning session_start ganda supaya output JSON bersih (2>/dev/null).
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', '0');
class MockPhpStream {
    public $context; private $i = 0;
    public function stream_open($p, $m, $o, &$op) { return true; }
    public function stream_read($c) { $d = $GLOBALS['body']; $r = substr($d, $this->i, $c); $this->i += strlen($r); return $r; }
    public function stream_eof() { return $this->i >= strlen($GLOBALS['body']); }
    public function stream_stat() { return []; }
    public function stream_write($d) { return strlen($d); }
}
$GLOBALS['body'] = $argv[2] ?? '{}';
$_SERVER['REQUEST_METHOD'] = 'POST';
parse_str($argv[1] ?? 'action=me', $_GET);
session_start();
$_SESSION["user_id"] = 1; // owner
stream_wrapper_unregister('php');
stream_wrapper_register('php', 'MockPhpStream');
chdir('C:/Users/IVO/dompet-owner');
require 'C:/Users/IVO/dompet-owner/api.php';
