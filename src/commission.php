<?php
// ============================================================
// MESIN KOMISI PENJUALAN Dompet Owner
// - Rule: % komisi per cabang dan/atau per kategori (paling spesifik menang)
// - Terpicu otomatis oleh add_transaction() utk transaksi masuk usaha
//   yang punya user_id (kariawan via bot/web/approval)
// - Komisi bisa diaplikasikan ke payroll sebagai bonus
// ============================================================
require_once __DIR__ . '/config.php';

/* Cari rule komisi paling spesifik utk cabang+kategori. NULL jika tidak ada. */
function commission_rule_for(?int $businessId, ?int $categoryId): ?array {
    $st = db()->prepare("SELECT * FROM commission_rules
        WHERE active=1 AND (business_id IS NULL OR business_id=?)
        AND (category_id IS NULL OR category_id=?)
        ORDER BY (business_id IS NOT NULL) DESC, (category_id IS NOT NULL) DESC, pct DESC
        LIMIT 1");
    $st->execute([$businessId, $categoryId]);
    return $st->fetch() ?: null;
}

/* Catat komisi utk satu transaksi (dipanggil otomatis dari add_transaction).
   Idempoten: UNIQUE(tx_id) membuat dobel posting tidak mungkin. */
function maybe_record_commission(array $t): ?int {
    if (($t['type'] ?? '') !== 'masuk') return null;
    if (($t['scope'] ?? '') !== 'usaha') return null;
    if (empty($t['user_id'])) return null;
    // jangan beri komisi utk input sistem/import/owner sendiri
    if (!in_array($t['source'] ?? '', ['bot_kariawan', 'kariawan_web', 'approval'], true)) return null;
    $rule = commission_rule_for($t['business_id'] ? (int)$t['business_id'] : null,
                                $t['category_id'] ? (int)$t['category_id'] : null);
    if (!$rule || (float)$rule['pct'] <= 0) return null;
    $amount = round((float)$t['amount'] * (float)$rule['pct'] / 100, 2);
    if ($amount <= 0) return null;
    // user kariawan jadi employee (kalau namanya cocok) — utk integrasi payroll
    $empId = null;
    $stU = db()->prepare("SELECT display_name FROM users WHERE id=?");
    $stU->execute([(int)$t['user_id']]);
    $disp = $stU->fetch()['display_name'] ?? '';
    if ($disp !== '') {
        $stE = db()->prepare("SELECT id FROM employees WHERE name LIKE CONCAT('%', ?, '%') AND active=1 ORDER BY id LIMIT 1");
        $stE->execute([mb_substr($disp, 0, 40)]);
        $empId = $stE->fetch()['id'] ?? null;
    }
    try {
        db()->prepare("INSERT INTO commissions (tx_id,user_id,employee_id,business_id,base_amount,pct,amount,status,period)
            VALUES (?,?,?,?,?,?,?,'pending',?)")
            ->execute([$t['id'], (int)$t['user_id'], $empId,
                $t['business_id'] ? (int)$t['business_id'] : null,
                (float)$t['amount'], (float)$rule['pct'], $amount,
                substr($t['tx_date'], 0, 7)]);
        return (int)db()->lastInsertId();
    } catch (PDOException $e) {
        return null; // duplikat tx atau data tidak lengkap — abaikan
    }
}

/* Rekap komisi pending per user utk satu periode (YYYY-MM). */
function commission_pending_by_user(string $period): array {
    $st = db()->prepare("SELECT c.user_id, u.display_name, e.id emp_id, e.name emp_name,
            COUNT(*) n_tx, SUM(c.amount) total
        FROM commissions c
        JOIN users u ON u.id=c.user_id
        LEFT JOIN employees e ON e.id=c.employee_id
        WHERE c.status='pending' AND c.period=?
        GROUP BY c.user_id, u.display_name, e.id, e.name ORDER BY total DESC");
    $st->execute([$period]);
    return $st->fetchAll();
}

/* Aplikasikan semua komisi pending periode ini ke payroll staf
   (bonus_amount += komisi). Return jumlah staf yang diupdate. */
function commission_apply_to_payroll(string $period): int {
    $n = 0;
    foreach (commission_pending_by_user($period) as $r) {
        if (!$r['emp_id']) continue; // staf belum terdaftar di payroll
        $chk = db()->prepare("SELECT id,bonus_amount,status FROM payrolls WHERE employee_id=? AND period=?");
        $chk->execute([(int)$r['emp_id'], $period]);
        $p = $chk->fetch();
        if (!$p || $p['status'] === 'paid') continue;
        db()->prepare("UPDATE payrolls SET bonus_amount=bonus_amount+?,
            net_amount=GREATEST(0,base_amount+bonus_amount+?-deduction_amount) WHERE id=?")
            ->execute([(float)$r['total'], (float)$r['total'], (int)$p['id']]);
        db()->prepare("UPDATE commissions SET status='paid', payroll_id=? WHERE status='pending' AND user_id=? AND period=?")
            ->execute([(int)$p['id'], (int)$r['user_id'], $period]);
        $n++;
    }
    return $n;
}
