<?php
// ============================================================
// ABSENSI KARIAWAN — satu tombol check-in/out (bot & web)
// ============================================================
require_once __DIR__ . '/config.php';

function attendance_toggle(array $u): array {
    $today = date('Y-m-d');
    $st = db()->prepare("SELECT * FROM attendance WHERE user_id=? AND work_date=?");
    $st->execute([(int)$u['id'], $today]);
    $a = $st->fetch();
    $bizId = !empty($u['business_id']) ? (int)$u['business_id'] : null;
    if (!$a) {
        db()->prepare("INSERT INTO attendance (user_id,business_id,work_date,check_in) VALUES (?,?,?,NOW())")
            ->execute([(int)$u['id'], $bizId, $today]);
        return ['ok'=>true,'state'=>'in','msg'=>'Check-in tercatat jam '.db_now_hm().' ✅'];
    }
    if (empty($a['check_in'])) {
        db()->prepare("UPDATE attendance SET check_in=NOW() WHERE id=?")->execute([(int)$a['id']]);
        return ['ok'=>true,'state'=>'in','msg'=>'Check-in tercatat jam '.db_now_hm().' ✅'];
    }
    if (!empty($a['check_out']))
        return ['ok'=>true,'state'=>'done','msg'=>'Absensi hari ini lengkap: '.substr($a['check_in'],11,5).' - '.substr($a['check_out'],11,5)];
    // pakai jam MySQL utk dua-duanya — aman dari beda timezone PHP vs DB
    $mins = (int)db()->query("SELECT TIMESTAMPDIFF(MINUTE, '".$a['check_in']."', NOW()) d")->fetch()['d'];
    $otAfter = (int)cfg('attendance_overtime_after','480');
    $ot = max(0, $mins - $otAfter);
    db()->prepare("UPDATE attendance SET check_out=NOW(), minutes=?, overtime_min=? WHERE id=?")
        ->execute([$mins, $ot, (int)$a['id']]);
    return ['ok'=>true,'state'=>'out','msg'=>'Check-out tercatat jam '.db_now_hm()." ✅ Kerja {$mins} mnt".($ot>0?", lembur {$ot} mnt 🔥":'')];
}

/* Rekap bulan berjalan utk 1 user */
function attendance_month_stats(int $userId): array {
    $st = db()->prepare("SELECT COUNT(*) hari, COALESCE(SUM(overtime_min),0) lembur
        FROM attendance WHERE user_id=? AND DATE_FORMAT(work_date,'%Y-%m')=?");
    $st->execute([$userId, date('Y-m')]);
    $s = $st->fetch();
    return ['hari'=>(int)$s['hari'], 'lembur_min'=>(int)$s['lembur'], 'period'=>date('Y-m')];
}

/* Jam sesuai server DB (konsisten dgn NOW()) */
function db_now_hm(): string {
    return db()->query("SELECT DATE_FORMAT(NOW(),'%H:%i') t")->fetch()['t'];
}
