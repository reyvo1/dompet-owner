<?php
// CRON PAGI (06:00): pengingat gajian hari ini + pajak jatuh tempo ke Telegram owner
// Dipanggil oleh Scheduled Task yang sama dengan daily-report (tools/morning-cron.bat)
declare(strict_types=1);
require __DIR__ . '/../src/config.php';

$today = date('Y-m-d');
$day = (int)date('j');
$lines = [];

// 1. Gajian staf hari ini (yang belum dibayar bulan ini)
foreach (db()->query("SELECT e.*, p.id pid FROM employees e
    LEFT JOIN payrolls p ON p.employee_id=e.id AND p.period='" . date('Y-m') . "' AND p.status='paid'
    WHERE e.active=1 AND e.pay_day=$day AND p.id IS NULL") as $e) {
    $lines[] = "💼 Gajian HARI INI: {$e['name']} (" . ($e['position'] ?: 'staf') . ") — Rp " .
        number_format((float)$e['base_salary'], 0, ',', '.');
}

// 2. Pajak jatuh tempo <= 3 hari / sudah lewat & belum dibayar
foreach (db()->query("SELECT * FROM tax_monthly WHERE status='unpaid' AND due_date IS NOT NULL
    AND due_date <= DATE_ADD('$today', INTERVAL 3 DAY)") as $t) {
    $lbl = ['pbjt'=>'PBJT','pph_umkm'=>'PPh UMKM','pph21'=>'PPh 21','non_pajak'=>'Pajak'][$t['tax_type']] ?? $t['tax_type'];
    $late = $t['due_date'] < $today ? ' ⚠️ LEWAT TEMPO' : '';
    $lines[] = "🏛️ {$lbl} ({$t['period']}) tempo {$t['due_date']} — Rp " .
        number_format((float)$t['amount'], 0, ',', '.') . $late;
}

// 3. Piutang lewat tempo >7 hari belum lunas (pengingat tagih)
foreach (db()->query("SELECT r.*, (r.amount - r.paid_amount) sisa FROM receivables r
    WHERE r.status<>'paid' AND r.due_date IS NOT NULL
    AND r.due_date < DATE_SUB('$today', INTERVAL 7 DAY)") as $r) {
    $lines[] = "📋 Piutang {$r['debtor_name']} lewat tempo — sisa Rp " .
        number_format((float)$r['sisa'], 0, ',', '.');
}

if (!$lines) {
    echo "[$today] Tidak ada pengingat gaji/pajak/piutang\n";
    exit; // diam: jangan spam owner
}

notify_owner_force("⏰ PENGINGAT DOMPET OWNER\n\n" . implode("\n", array_slice($lines, 0, 15)));
echo "[$today] Pengingat terkirim: " . count($lines) . " item\n";
