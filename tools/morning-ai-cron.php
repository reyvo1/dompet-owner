<?php
// CRON PAGI (06:00): laporan pagi + ANALISIS AI + pengingat interaktif (tombol aksi)
// Dipanggil tools/morning-cron.bat
declare(strict_types=1);
require __DIR__ . '/../src/config.php';
// gunakan versi AI cron (laporan pagi + analisis + pengingat interaktif)
require_once __DIR__ . '/../src/ai.php';

$today = date('Y-m-d');
$period = date('Y-m');

/* ===== 1. RINGKASAN ANGKA ===== */
function sumq(string $sql, array $a = []): float {
    $st = db()->prepare($sql); $st->execute($a);
    return (float)$st->fetch()['s'];
}
$in  = sumq("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='masuk' AND tx_date=?", [$today]);
$out = sumq("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='keluar' AND tx_date=?", [$today]);
$n   = sumq("SELECT COUNT(*) s FROM transactions WHERE tx_date=?", [$today]);
$mIn  = sumq("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='masuk' AND DATE_FORMAT(tx_date,'%Y-%m')=?", [$period]);
$mOut = sumq("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='keluar' AND DATE_FORMAT(tx_date,'%Y-%m')=?", [$period]);
$kas = total_kas();

/* ===== 2. ANALISIS AI ===== */
$aiPart = "\n\n🤖 belum tersedia (API key AI belum dipasang)";
if (gemini_key() !== '') {
    $prompt = "Buat laporan pagi singkat untuk pemilik usaha. Data:\n"
        . "Hari ini: masuk Rp" . number_format($in,0,'.','.') . ", keluar Rp" . number_format($out,0,'.','.') . ", $n transaksi.\n"
        . "Bulan ini: masuk Rp" . number_format($mIn,0,'.','.') . ", keluar Rp" . number_format($mOut,0,'.','.') . ".\n"
        . "Total kas sekarang: Rp" . number_format($kas,0,'.','.') . ".\n"
        . finance_context()
        . "\nTulis maksimal 4 baris: 1 insight terpenting + 1 saran konkret hari ini. Bahasa Indonesia santai, tanpa salam.";
    $ai = ai_answer_ctx(0, $prompt); // tanpa memori utk cron
    if ($ai && !str_starts_with($ai, 'AI ')) $aiPart = "\n\n🤖 *Analisis AI*\n$ai";
}

$msg = "🌅 *Selamat pagi! Laporan {$today}*\n\n"
    . "📥 Kemarin masuk: Rp " . number_format($in,0,',','.') . "\n"
    . "📤 Keluar: Rp " . number_format($out,0,',','.') . "\n"
    . "🔢 Transaksi: " . (int)$n . "\n"
    . "💰 Total kas: Rp " . number_format($kas,0,',','.') . "\n"
    . "📊 Bulan ini laba: Rp " . number_format($mIn - $mOut,0,',','.')
    . $aiPart;

notify_owner_force($msg);

/* ===== 3. PENGINGAT INTERAKTIF (dengan tombol aksi) ===== */
$day = (int)date('j');
$kb = [];
$lines = [];

// gajian hari ini
foreach (db()->query("SELECT e.*, p.id pid FROM employees e
    LEFT JOIN payrolls p ON p.employee_id=e.id AND p.period='$period' AND p.status='paid'
    WHERE e.active=1 AND e.pay_day=$day AND p.id IS NULL") as $e) {
    // pastikan draft payroll ada supaya tombol Bayar bisa jalan
    db()->prepare("INSERT INTO payrolls (employee_id,period,base_amount,net_amount,status)
        SELECT ?, ?, ?, ?, 'pending' FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM payrolls WHERE employee_id=? AND period=?)")
        ->execute([$e['id'], $period, (float)$e['base_salary'], (float)$e['base_salary'], $e['id'], $period]);
    $pid = db()->query("SELECT id FROM payrolls WHERE employee_id={$e['id']} AND period='$period'")->fetch()['id'];
    $kb[] = [['text'=>"💸 Bayar gaji {$e['name']}", 'callback_data'=>"act:payroll:$pid"]];
    $lines[] = "💼 Gajian hari ini: {$e['name']}";
}

// pajak jatuh tempo <=3 hari
foreach (db()->query("SELECT * FROM tax_monthly WHERE status='unpaid' AND due_date IS NOT NULL
    AND due_date <= DATE_ADD('$today', INTERVAL 3 DAY)") as $t) {
    $lbl = ['pbjt'=>'PBJT','pph_umkm'=>'PPh UMKM','pph21'=>'PPh 21','non_pajak'=>'Pajak'][$t['tax_type']] ?? $t['tax_type'];
    $kb[] = [['text'=>"🏛️ Bayar {$lbl} {$t['period']}", 'callback_data'=>"act:tax:{$t['id']}"]];
    $lines[] = "🏛️ {$lbl} ({$t['period']}) tempo {$t['due_date']} — Rp " . number_format((float)$t['amount'],0,',','.');
}

// piutang telat >7 hari
foreach (db()->query("SELECT r.*, (r.amount - r.paid_amount) sisa FROM receivables r
    WHERE r.status<>'paid' AND r.due_date IS NOT NULL
    AND r.due_date < DATE_SUB('$today', INTERVAL 7 DAY) LIMIT 5") as $r) {
    $kb[] = [['text'=>"📋 Tandai tagih {$r['debtor_name']}", 'callback_data'=>"act:recv:{$r['id']}"]];
    $lines[] = "📋 Piutang {$r['debtor_name']} telat — sisa Rp " . number_format((float)$r['sisa'],0,',','.');
}

if ($kb) {
    notify_owner_force("⏰ *Perlu tindakanmu:*\n\n" . implode("\n", $lines),
        ['inline_keyboard' => array_slice($kb, 0, 8)]);
    echo "[$today] Pengingat interaktif: " . count($kb) . " aksi\n";
} else {
    echo "[$today] Tidak ada pengingat\n";
}
echo "[$today] Laporan pagi terkirim\n";
