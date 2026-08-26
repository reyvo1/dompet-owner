<?php
// SLIP GAJI SIAP CETAK/PDF — /payslip.php?id=<payroll_id>
// Akses owner via session, ATAU token hash (dibagikan ke staf tanpa login)
declare(strict_types=1);
require __DIR__ . '/src/config.php';
session_start();

$id = (int)($_GET['id'] ?? 0);
$tok = trim($_GET['t'] ?? '');

$st = db()->prepare("SELECT p.*, e.name emp, e.position pos, b.name biz
    FROM payrolls p JOIN employees e ON e.id=p.employee_id
    LEFT JOIN businesses b ON b.id=e.business_id WHERE p.id=?");
$st->execute([$id]);
if (!$r = $st->fetch()) { http_response_code(404); exit('Slip gaji tidak ditemukan.'); }

// otorisasi: owner login ATAU token valid
$salt = cfg('receipt_salt', 'dompet-owner-receipt-v1');
$valid = hash('sha256', 'payslip|' . $r['id'] . '|' . $r['period'] . '|' . $salt);
$isOwner = false;
if (!empty($_SESSION['user_id'])) {
    $u = db()->prepare("SELECT role FROM users WHERE id=? AND active=1");
    $u->execute([$_SESSION['user_id']]);
    $isOwner = ($u->fetch()['role'] ?? '') === 'owner';
}
if (!$isOwner && !hash_equals($valid, $tok)) {
    http_response_code(403);
    exit('Akses ditolak — minta link slip dari owner atau login sebagai owner.');
}

$rp = fn($v) => 'Rp ' . number_format((float)$v, 0, ',', '.');
$bulanNama = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][(int)substr($r['period'],5,2)] . ' ' . substr($r['period'],0,4);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Slip Gaji <?= htmlspecialchars($r['emp']) ?> — <?= htmlspecialchars($bulanNama) ?></title>
<style>
 :root{--ink:#111827;--mut:#6b7280;--line:#e5e7eb}
 *{box-sizing:border-box;margin:0;padding:0}
 body{font-family:'Segoe UI',Arial,sans-serif;color:var(--ink);padding:28px;max-width:600px;margin:auto;background:#f8fafc}
 .card{background:#fff;border-radius:14px;padding:32px;box-shadow:0 2px 12px rgba(0,0,0,.07)}
 .head{border-bottom:3px solid #155eef;padding-bottom:14px;margin-bottom:8px;text-align:center}
 h1{font-size:18px}.sub{color:var(--mut);font-size:13px;margin-top:3px}
 table{width:100%;border-collapse:collapse;margin-top:20px;font-size:14px}
 td{padding:9px 4px;border-bottom:1px solid var(--line)}
 td:first-child{color:var(--mut)}
 td:last-child{text-align:right;font-weight:600}
 tr.net td{border-bottom:none;padding-top:16px;font-size:16px}
 tr.net td:last-child{font-size:22px;font-weight:800;color:#065f46}
 .badge{display:inline-block;font-weight:800;font-size:11px;color:#fff;background:#059669;padding:4px 12px;border-radius:99px;margin-top:10px}
 .badge.pending{background:#d97706}
 .foot{margin-top:22px;color:var(--mut);font-size:11.5px;line-height:1.7;border-top:1px dashed var(--line);padding-top:14px;text-align:center}
 .btns{text-align:center;margin-bottom:14px}
 .btns button{background:#155eef;color:#fff;border:0;border-radius:9px;padding:9px 20px;font-weight:700;cursor:pointer;font-size:13px}
 @media print{body{background:#fff;padding:6mm}.card{box-shadow:none}.btns{display:none}}
</style>
</head>
<body>
<div class="btns"><button onclick="window.print()">🖨️ Cetak / Simpan PDF</button></div>
<div class="card">
 <div class="head">
  <h1>💼 Slip Gaji Karyawan</h1>
  <div class="sub">Periode <?= htmlspecialchars($bulanNama) ?><?= $r['biz'] ? ' • ' . htmlspecialchars($r['biz']) : '' ?></div>
  <span class="badge <?= $r['status']==='paid'?'':'pending' ?>"><?= $r['status']==='paid'?'DIBAYAR':'BELUM DIBAYAR' ?></span>
 </div>
 <table>
  <tr><td>Nama Karyawan</td><td><?= htmlspecialchars($r['emp']) ?></td></tr>
  <?php if ($r['pos']): ?><tr><td>Jabatan</td><td><?= htmlspecialchars($r['pos']) ?></td></tr><?php endif; ?>
  <tr><td>Gaji Pokok</td><td><?= $rp($r['base_amount']) ?></td></tr>
  <?php if ((float)$r['bonus_amount'] > 0): ?><tr><td>Bonus / Tambahan</td><td style="color:#065f46">+ <?= $rp($r['bonus_amount']) ?></td></tr><?php endif; ?>
  <?php if ((float)$r['deduction_amount'] > 0): ?><tr><td>Potongan</td><td style="color:#991b1b">- <?= $rp($r['deduction_amount']) ?></td></tr><?php endif; ?>
  <tr class="net"><td><strong>Diterima</strong></td><td><?= $rp($r['net_amount']) ?></td></tr>
  <?php if ($r['paid_at']): ?><tr><td>Tanggal Bayar</td><td><?= htmlspecialchars(substr($r['paid_at'],0,10)) ?></td></tr><?php endif; ?>
 </table>
 <div class="foot">
  Dokumen ini dibuat otomatis oleh sistem Dompet Owner sebagai bukti pembayaran gaji resmi.
 </div>
</div>
</body>
</html>
