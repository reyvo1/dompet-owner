<?php
// KWITANSI / INVOICE SIAP CETAK — /invoice.php?id=N
declare(strict_types=1);
require __DIR__ . '/src/config.php';
session_start();
$u = null;
if (!empty($_SESSION['user_id'])) {
    $st = db()->prepare("SELECT * FROM users WHERE id=? AND active=1");
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();
}
if (!$u) { http_response_code(403); exit('Login dulu di dashboard.'); }

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT i.*, b.name biz, b.icon biz_icon FROM invoices i
    LEFT JOIN businesses b ON b.id=i.business_id WHERE i.id=?");
$st->execute([$id]);
if (!$inv = $st->fetch()) { http_response_code(404); exit('Invoice tidak ada.'); }

$rp = fn($v) => 'Rp ' . number_format((float)$v, 0, ',', '.');
$owner = db()->query("SELECT display_name FROM users WHERE role='owner' LIMIT 1")->fetch()['display_name'] ?? 'Owner';
$statusBadge = ['unpaid'=>'⏳ BELUM LUNAS','paid'=>'✅ LUNAS','void'=>'✖ DIBATALKAN'][$inv['status']];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Kwitansi <?= htmlspecialchars($inv['number']) ?></title>
<style>
 :root{--ink:#111827;--mut:#6b7280;--line:#e5e7eb}
 *{box-sizing:border-box;margin:0;padding:0}
 body{font-family:'Segoe UI',Arial,sans-serif;color:var(--ink);padding:32px;max-width:720px;margin:auto;background:#fff}
 .head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #155eef;padding-bottom:16px}
 h1{font-size:20px}.sub{color:var(--mut);font-size:13px}
 .badge{display:inline-block;background:#fef3c7;color:#92400e;font-weight:800;font-size:12px;padding:5px 12px;border-radius:99px;margin-top:6px}
 .badge.paid{background:#d1fae5;color:#065f46}
 table{width:100%;border-collapse:collapse;margin-top:22px;font-size:14px}
 td{padding:10px 8px;border-bottom:1px solid var(--line)}
 .r{text-align:right}
 .tot td{font-size:17px;font-weight:800;border-top:2px solid var(--ink);border-bottom:none}
 .note{margin-top:18px;color:var(--mut);font-size:12px;line-height:1.6}
 .btn{margin-top:24px;border:none;background:#155eef;color:#fff;padding:11px 22px;border-radius:9px;font-weight:700;cursor:pointer}
 @media print{.btn{display:none}body{padding:10mm}}
 @page{margin:14mm}
</style>
</head>
<body>
<div class="head">
 <div>
  <h1><?= htmlspecialchars(($inv['biz_icon'] ?? '🧾').' '.($inv['biz'] ?? 'Dompet Owner')) ?></h1>
  <div class="sub">Kwitansi No. <b><?= htmlspecialchars($inv['number']) ?></b><br>
   Tanggal: <?= date('d/m/Y', strtotime($inv['created_at'])) ?></div>
 </div>
 <div style="text-align:right"><span class="badge <?= $inv['status']=='paid'?'paid':'' ?>"><?= $statusBadge ?></span></div>
</div>

<table>
<tr><td>Diterima dari</td><td class="r"><b><?= htmlspecialchars($inv['customer_name']) ?></b></td></tr>
<tr><td>Untuk pembayaran</td><td class="r"><?= nl2br(htmlspecialchars($inv['description'] ?? '-')) ?></td></tr>
<tr class="tot"><td>TOTAL</td><td class="r"><?= $rp($inv['amount']) ?></td></tr>
<?php if ($inv['paid_at']): ?>
<tr><td>Dibayar pada</td><td class="r"><?= date('d/m/Y H:i', strtotime($inv['paid_at'])) ?></td></tr>
<?php endif; ?>
</table>

<?php if ($inv['status'] === 'unpaid' && trim((string)cfg('qris_text')) !== ''): ?>
<div style="margin-top:22px;display:flex;gap:16px;align-items:center">
 <img src="https://api.qrserver.com/v1/create-qr-code/?size=170x170&data=<?= urlencode(cfg('qris_text')) ?>"
      alt="QR Bayar" width="170" height="170" style="border:1px solid #e5e7eb;border-radius:10px">
 <div style="font-size:13px;color:#374151;line-height:1.6">
  <b>Scan untuk bayar</b><br>QRIS / transfer sesuai data di QR.<br>
  <span style="color:#6b7280">Konfirmasi pembayaran ke penjual setelah transfer.</span></div>
</div>
<?php endif; ?>

<div class="note">
 Kwitansi ini sah tanpa tanda tangan basah (transaksi tercatat digital).<br>
 Dicetak oleh <?= htmlspecialchars($owner) ?> — Dompet Owner.
</div>

<button class="btn" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
</body>
</html>
