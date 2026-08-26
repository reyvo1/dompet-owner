<?php
// Ekspor XLSX sederhana (SpreadsheetML 2003 — kompatibel Excel & WPS, tanpa library)
// /export-pajak.php?period=YYYY-MM  (khusus owner login)
declare(strict_types=1);
require __DIR__ . '/src/config.php';
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(403); exit('Login dulu.'); }
$u = db()->prepare("SELECT role FROM users WHERE id=? AND active=1");
$u->execute([$_SESSION['user_id']]);
if (($u->fetch()['role'] ?? '') !== 'owner') { http_response_code(403); exit('Khusus owner.'); }

$period = preg_match('/^\d{4}-\d{2}$/', $_GET['period'] ?? '') ? $_GET['period'] : date('Y-m');

$e = function($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8'); };
$n = fn($v) => '<Cell ss:StyleID="num"><Data ss:Type="Number">' . (float)$v . '</Data></Cell>';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="pajak-' . $period . '.xls"');
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Styles>
  <Style ss:ID="head"><Font ss:Bold="1"/><Interior ss:Color="#DDEBF7" ss:Pattern="Solid"/></Style>
  <Style ss:ID="num"><NumberFormat ss:Format="#,##0"/></Style>
  <Style ss:ID="bold"><Font ss:Bold="1"/></Style>
 </Styles>
 <Worksheet ss:Name="Rekap Pajak">
  <Table>
   <Row>
    <Cell ss:StyleID="head"><Data ss:Type="String">Periode</Data></Cell><Cell><Data ss:Type="String"><?= $e($period) ?></Data></Cell>
   </Row>
   <Row/>
   <Row>
    <Cell ss:StyleID="head"><Data ss:Type="String">Jenis Pajak</Data></Cell>
    <Cell ss:StyleID="head"><Data ss:Type="String">Jumlah (Rp)</Data></Cell>
    <Cell ss:StyleID="head"><Data ss:Type="String">Jatuh Tempo</Data></Cell>
    <Cell ss:StyleID="head"><Data ss:Type="String">Status</Data></Cell>
   </Row>
<?php
$total = 0.0;
$q = db()->prepare("SELECT * FROM tax_monthly WHERE period=? ORDER BY tax_type");
$q->execute([$period]);
foreach ($q as $st) {
    $total += (float)$st['amount'];
    echo "   <Row>\n";
    echo '    <Cell><Data ss:Type="String">' . $e($st['tax_type']) . "</Data></Cell>\n";
    echo '    ' . $n($st['amount']) . "\n";
    echo '    <Cell><Data ss:Type="String">' . $e($st['due_date'] ?? '-') . "</Data></Cell>\n";
    echo '    <Cell><Data ss:Type="String">' . $e($st['status'] === 'paid' ? 'LUNAS' : 'BELUM') . "</Data></Cell>\n";
    echo "   </Row>\n";
}
?>
   <Row>
    <Cell ss:StyleID="bold"><Data ss:Type="String">TOTAL</Data></Cell><?= $n($total) ?>
   </Row>
  </Table>
 </Worksheet>
 <Worksheet ss:Name="Rincian Transaksi Berpajak">
  <Table>
   <Row>
    <Cell ss:StyleID="head"><Data ss:Type="String">Tanggal</Data></Cell>
    <Cell ss:StyleID="head"><Data ss:Type="String">Keterangan</Data></Cell>
    <Cell ss:StyleID="head"><Data ss:Type="String">Jenis</Data></Cell>
    <Cell ss:StyleID="head"><Data ss:Type="String">DPP (Rp)</Data></Cell>
    <Cell ss:StyleID="head"><Data ss:Type="String">Pajak (Rp)</Data></Cell>
   </Row>
<?php
$st = db()->prepare("SELECT t.tx_date, t.description, tl.tax_type, tl.base_amount, tl.tax_amount
    FROM tax_lines tl JOIN transactions t ON t.id=tl.tx_id
    WHERE DATE_FORMAT(t.tx_date,'%Y-%m')=? ORDER BY t.tx_date, tl.id");
$st->execute([$period]);
foreach ($st as $r) {
    echo "   <Row>\n";
    echo '    <Cell><Data ss:Type="String">' . $e($r['tx_date']) . "</Data></Cell>\n";
    echo '    <Cell><Data ss:Type="String">' . $e($r['description']) . "</Data></Cell>\n";
    echo '    <Cell><Data ss:Type="String">' . $e($r['tax_type']) . "</Data></Cell>\n";
    echo '    ' . $n($r['base_amount']) . "\n    " . $n($r['tax_amount']) . "\n";
    echo "   </Row>\n";
}
?>
  </Table>
 </Worksheet>
</Workbook>
