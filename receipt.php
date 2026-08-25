<?php
// STRUK DIGITAL — /receipt.php?no=DO-YYYYMMDD-XXXX&t=TOKEN
// Bisa dibagikan ke pihak luar TANPA login; token = hash rahasia per transaksi.
declare(strict_types=1);
require __DIR__ . '/src/config.php';

$no = trim($_GET['no'] ?? '');
$t  = trim($_GET['t'] ?? '');
if ($no === '' || $t === '') { http_response_code(400); exit('Nomor struk tidak valid.'); }

$st = db()->prepare("SELECT t.*, u.display_name, b.name biz, c.name cat, w.name wallet
    FROM transactions t
    LEFT JOIN users u ON u.id=t.user_id LEFT JOIN businesses b ON b.id=t.business_id
    LEFT JOIN categories c ON c.id=t.category_id LEFT JOIN wallets w ON w.id=t.wallet_id
    WHERE t.tx_no=?");
$st->execute([$no]);
if (!$r = $st->fetch()) { http_response_code(404); exit('Struk tidak ditemukan.'); }

// token valid = sha256(tx_no . id . salt aplikasi)
$salt = cfg('receipt_salt', 'dompet-owner-receipt-v1');
$valid = hash('sha256', $r['tx_no'] . '|' . $r['id'] . '|' . $salt);
if (!hash_equals($valid, $t)) { http_response_code(403); exit('Link struk tidak sah.'); }

$rp = fn($v) => 'Rp ' . number_format((float)$v, 0, ',', '.');
$owner = db()->query("SELECT display_name FROM users WHERE role='owner' LIMIT 1")->fetch()['display_name'] ?? 'Owner';
$typeLabel = ['masuk'=>'UANG MASUK','keluar'=>'UANG KELUAR','transfer'=>'TRANSFER KAS'][$r['type']] ?? $r['type'];
$cls = $r['type']==='masuk' ? '#065f46' : ($r['type']==='keluar' ? '#991b1b' : '#1e40af');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Struk <?= htmlspecialchars($r['tx_no']) ?></title>
<style>
 :root{--ink:#111827;--mut:#6b7280;--line:#e5e7eb}
 *{box-sizing:border-box;margin:0;padding:0}
 body{font-family:'Segoe UI',Arial,sans-serif;color:var(--ink);padding:28px;max-width:560px;margin:auto;background:#f8fafc}
 .card{background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(0,0,0,.07)}
 .head{border-bottom:3px solid #155eef;padding-bottom:14px;margin-bottom:6px}
 h1{font-size:19px}.sub{color:var(--mut);font-size:13px;margin-top:3px}
 .type{display:inline-block;font-weight:800;font-size:12px;color:#fff;background:<?= $cls ?>;padding:5px 14px;border-radius:99px;margin-top:10px}
 table{width:100%;border-collapse:collapse;margin-top:20px;font-size:14px}
 td{padding:9px 4px;border-bottom:1px solid var(--line)}
 td:first-child{color:var(--mut);width:38%}
 .amt{font-size:26px;font-weight:800;text-align:center;margin-top:18px;color:<?= $cls ?>}
 .foot{margin-top:20px;color:var(--mut);font-size:11.5px;line-height:1.7;border-top:1px dashed var(--line);padding-top:14px}
 @media print{body{background:#fff;padding:6mm}.card{box-shadow:none}}
</style>
</head>
<body>
<div class="card">
 <div class="head">
  <h1>🧾 Struk Digital Dompet Owner</h1>
  <div class="sub"><?= htmlspecialchars($owner) ?><?= $r['biz'] ? ' — ' . htmlspecialchars($r['biz']) : '' ?></div>
  <span class="type"><?= $typeLabel ?></span>
 </div>
 <div class="amt"><?= $rp($r['amount']) ?></div>
 <table>
  <tr><td>No. Referensi</td><td><strong><?= htmlspecialchars($r['tx_no']) ?></strong></td></tr>
  <tr><td>Tanggal</td><td><?= htmlspecialchars($r['tx_date']) ?></td></tr>
  <tr><td>Keterangan</td><td><?= htmlspecialchars($r['description']) ?></td></tr>
  <?php if ($r['cat']): ?><tr><td>Kategori</td><td><?= htmlspecialchars($r['cat']) ?></td></tr><?php endif; ?>
  <tr><td>Kas / Rekening</td><td><?= htmlspecialchars($r['wallet'] ?? '-') ?></td></tr>
  <?php if ($r['display_name']): ?><tr><td>Dicatat oleh</td><td><?= htmlspecialchars($r['display_name']) ?></td></tr><?php endif; ?>
 </table>
 <div class="foot">
  Dokumen ini adalah bukti pencatatan resmi yang dibuat otomatis oleh sistem Dompet Owner.
  Nomor referensi di atas bersifat unik dan dapat diverifikasi. Halaman ini read-only dan tidak menampilkan data keuangan lain.
 </div>
</div>
</body>
</html>
