<?php
// REST API Dompet Owner (dipakai dashboard web)
require __DIR__ . '/src/config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

function out($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function require_login(): array {
    if (empty($_SESSION['user_id'])) out(['error' => 'Belum login'], 401);
    $st = db()->prepare("SELECT * FROM users WHERE id=? AND active=1");
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();
    if (!$u) out(['error' => 'User tidak valid'], 401);
    return $u;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: [];

try {
switch ($action) {

case 'login':
    $st = db()->prepare("SELECT * FROM users WHERE username=? AND active=1");
    $st->execute([trim($input['username'] ?? '')]);
    $u = $st->fetch();
    if (!$u || !password_verify($input['password'] ?? '', $u['password_hash'])) {
        out(['error' => 'Username / password salah'], 401);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $u['id'];
    out(['ok' => true, 'user' => ['name' => $u['display_name'], 'role' => $u['role']]]);

case 'logout':
    session_destroy();
    out(['ok' => true]);

case 'me':
    $u = require_login();
    $bizName = null;
    if ($u['business_id']) {
        $b = db()->prepare("SELECT name FROM businesses WHERE id=?"); $b->execute([$u['business_id']]);
        $bizName = $b->fetch()['name'] ?? null;
    }
    out(['user' => ['name' => $u['display_name'], 'role' => $u['role'],
        'business_id' => $u['business_id'] ? (int)$u['business_id'] : null, 'business_name' => $bizName]]);

/* ---------- RINGKASAN: khusus kariawan (cabangnya sendiri) ---------- */
case 'summary_branch':
    $u = require_login();
    if (!$u['business_id']) out(['error' => 'Akun belum dipasangkan ke cabang'], 422);
    $bid = (int)$u['business_id'];
    $biz = db()->query("SELECT name FROM businesses WHERE id=$bid")->fetch();
    $period = date('Y-m');
    $today = laporan_hari($bid, date('Y-m-d'));
    $bulan = laporan_bulan($period, $bid);
    // tren 6 bulan cabang
    $tren = [];
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $t = laporan_bulan($m, $bid);
        $tren[] = ['bulan'=>$m,'masuk'=>$t['masuk'],'keluar'=>$t['keluar']];
    }
    // transaksi terbaru cabang ini
    $st = db()->prepare("SELECT t.*, u.display_name, b.name biz, c.name cat, w.name wallet
        FROM transactions t
        LEFT JOIN users u ON u.id=t.user_id LEFT JOIN businesses b ON b.id=t.business_id
        LEFT JOIN categories c ON c.id=t.category_id LEFT JOIN wallets w ON w.id=t.wallet_id
        WHERE t.business_id=? ORDER BY t.tx_date DESC, t.id DESC LIMIT 15");
    $st->execute([$bid]);
    out([
        'branch'=>['id'=>$bid,'name'=>$biz['name']],
        'today'=>$today,
        'bulan_bulan_ini'=>$bulan,
        'tren'=>$tren,
        'recent'=>$st->fetchAll(),
        'period'=>$period,
    ]);

/* ---------- TRANSAKSI KARIAWAN via web (masuk ke cabangnya) ---------- */
case 'tx_add_branch':
    $u = require_login();
    if (!$u['business_id']) out(['error' => 'Akun belum dipasangkan ke cabang'], 422);
    $type = ($input['type'] ?? '') === 'masuk' ? 'masuk' : 'keluar';
    $id = add_transaction([
        'type'=>$type,
        'amount'=>(float)($input['amount']??0),
        'description'=>trim($input['description']??'')." (via {$u['display_name']})",
        'source'=>'kariawan_web',
        'user_id'=>$u['id'],
        'business_id'=>(int)$u['business_id'],
    ]);
    $biz = db()->query("SELECT name FROM businesses WHERE id={$u['business_id']}")->fetch()['name'];
    notify_owner((($type==='masuk')?'🟢 MASUK':'🔴 KELUAR')." [{$biz}]\nRp ".number_format((float)($input['amount']??0),0,',','.')."\n".trim($input['description']??'')."\nOleh: {$u['display_name']} (web)");
    out(['ok'=>true,'id'=>$id]);

/* ---------- RINGKASAN DASHBOARD OWNER ---------- */
case 'summary':
    require_login();
    $period = date('Y-m');
    $kas = wallets_all();
    $pribadi = laporan_bulan($period, null);
    // filter profil bisnis (multi-profil): biz=ID -> hanya bisnis tsb; kosong = semua
    $bizFilter = isset($_GET['biz']) && $_GET['biz'] !== '' ? (int)$_GET['biz'] : null;
    if ($bizFilter !== null) {
        $stB = db()->prepare("SELECT COALESCE(SUM(amount),0) s FROM transactions
            WHERE scope='usaha' AND type='masuk' AND business_id=? AND DATE_FORMAT(tx_date,'%Y-%m')='$period'");
        $stB->execute([$bizFilter]);
        $usahaMasuk = (float)$stB->fetch()['s'];
        $stB = db()->prepare("SELECT COALESCE(SUM(amount),0) s FROM transactions
            WHERE scope='usaha' AND type='keluar' AND business_id=? AND DATE_FORMAT(tx_date,'%Y-%m')='$period'");
        $stB->execute([$bizFilter]);
        $usahaKeluar = (float)$stB->fetch()['s'];
    } else {
        $usahaMasuk = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions
            WHERE scope='usaha' AND type='masuk' AND DATE_FORMAT(tx_date,'%Y-%m')='$period'")->fetch()['s'];
        $usahaKeluar = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions
            WHERE scope='usaha' AND type='keluar' AND DATE_FORMAT(tx_date,'%Y-%m')='$period'")->fetch()['s'];
    }
    $cabang = [];
    foreach (db()->query("SELECT * FROM businesses WHERE active=1 ORDER BY id") as $b) {
        if ($bizFilter !== null && (int)$b['id'] !== $bizFilter) continue;
        $l = laporan_bulan($period, (int)$b['id']);
        $l['id'] = (int)$b['id']; $l['name'] = $b['name']; $l['icon'] = $b['icon']; $l['color'] = $b['color'];
        $kariawan = db()->prepare("SELECT COUNT(*) c FROM users WHERE business_id=? AND active=1");
        $kariawan->execute([$b['id']]);
        $l['kariawan'] = (int)$kariawan->fetch()['c'];
        $cabang[] = $l;
    }
    // tren 6 bulan terakhir (owner: pribadi + usaha)
    $tren = [];
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $in = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions
            WHERE type='masuk' AND DATE_FORMAT(tx_date,'%Y-%m')='$m'")->fetch()['s'];
        $out = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions
            WHERE type='keluar' AND DATE_FORMAT(tx_date,'%Y-%m')='$m'")->fetch()['s'];
        $tren[] = ['bulan' => $m, 'masuk' => $in, 'keluar' => $out];
    }
    $recentWhere = $bizFilter !== null ? "WHERE t.business_id=$bizFilter" : '';
    $recent = db()->query("SELECT t.*, u.display_name, b.name biz, c.name cat, w.name wallet
        FROM transactions t
        LEFT JOIN users u ON u.id=t.user_id LEFT JOIN businesses b ON b.id=t.business_id
        LEFT JOIN categories c ON c.id=t.category_id LEFT JOIN wallets w ON w.id=t.wallet_id
        $recentWhere ORDER BY t.id DESC LIMIT 15")->fetchAll();
    foreach ($recent as &$r) { unset($r['user_id']); }
    out([
        'total_kas' => total_kas(),
        'wallets' => $kas,
        'pribadi' => $pribadi,
        'usaha' => ['masuk' => $usahaMasuk, 'keluar' => $usahaKeluar, 'laba' => $usahaMasuk - $usahaKeluar],
        'cabang' => $cabang,
        'tren' => $tren,
        'recent' => $recent,
        'period' => $period,
    ]);

/* ---------- TRANSAKSI BARU (oleh owner via web) ---------- */
case 'tx_add':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $biz = !empty($input['business_id']) ? (int)$input['business_id'] : null;
    $cat = null;
    if (!empty($input['category'])) {
        $st = db()->prepare("SELECT id FROM categories WHERE name=?");
        $st->execute([$input['category']]);
        $cat = $st->fetch()['id'] ?? null;
    }
    // SMART RULES: kategori kosong -> tebak dari kata kunci
    $smartHit = null;
    if ($cat === null && !empty($input['description'])) {
        $smartHit = smart_match_category((string)$input['description'], $biz);
        if ($smartHit) {
            $st = db()->prepare("SELECT id FROM categories WHERE name=?");
            $st->execute([$smartHit['category']]);
            $cat = $st->fetch()['id'] ?? null;
        }
    }
    $id = add_transaction([
        'type' => $input['type'] === 'transfer' ? 'transfer' : ($input['type'] ?? 'keluar'),
        'amount' => (float)($input['amount'] ?? 0),
        'description' => $input['description'] ?? '',
        'source' => 'owner',
        'user_id' => $u['id'],
        'business_id' => $biz,
        'scope' => $biz ? 'usaha' : 'pribadi',
        'category_id' => $cat,
        'wallet_id' => !empty($input['wallet_id']) ? (int)$input['wallet_id'] : null,
        'wallet_dest_id' => !empty($input['wallet_dest_id']) ? (int)$input['wallet_dest_id'] : null,
    ]);
    out(['ok' => true, 'id' => $id, 'smart' => $smartHit ? $smartHit['category'] : null]);

/* ---------- SMART RULES ---------- */
case 'rule_list':
    require_login();
    $rows = db()->query("SELECT r.*, c.name cat, b.name biz FROM smart_rules r
        LEFT JOIN categories c ON c.id=r.category_id
        LEFT JOIN businesses b ON b.id=r.business_id
        ORDER BY r.priority, r.hits DESC")->fetchAll();
    out(['rows' => $rows]);

case 'rule_add':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $pattern = trim($input['pattern'] ?? '');
    if ($pattern === '') out(['error' => 'Kata kunci kosong'], 422);
    $st = db()->prepare("SELECT id FROM categories WHERE name=?");
    $st->execute([trim($input['category'] ?? '')]);
    $catId = $st->fetch()['id'] ?? null;
    if (!$catId) out(['error' => 'Kategori tidak dikenal'], 422);
    $biz = !empty($input['business_id']) ? (int)$input['business_id'] : null;
    try {
        db()->prepare("INSERT INTO smart_rules (pattern,category_id,business_id,priority)
            VALUES (?,?,?,?)")->execute([$pattern, $catId, $biz, (int)($input['priority'] ?? 100)]);
    } catch (PDOException $e) {
        out(['error' => 'Aturan itu sudah ada'], 422);
    }
    out(['ok' => true, 'id' => (int)db()->lastInsertId()]);

case 'rule_delete':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("DELETE FROM smart_rules WHERE id=?")->execute([(int)($input['id'] ?? 0)]);
    out(['ok' => true]);

/* ---------- LIABILITY ENGINE (kartu kredit/PayLater/pinjaman) ---------- */
case 'liab_list':
    require_login();
    $rows = db()->query("SELECT l.*, b.name biz FROM liabilities l
        LEFT JOIN businesses b ON b.id=l.business_id
        WHERE l.active=1 ORDER BY l.id")->fetchAll();
    foreach ($rows as &$r) {
        $r['available'] = $r['limit_amount'] > 0
            ? max(0, (float)$r['limit_amount'] - (float)$r['outstanding'])
            : null;
        // tagihan berjalan (charge sejak statement terakhir) — sederhana: outstanding penuh utk kartu
        if ($r['kind'] === 'credit_card' && $r['statement_day']) {
            $day = min((int)$r['statement_day'], 28);
            $mStart = (int)date('d') >= $day ? date('Y-m-').str_pad($day,2,'0',STR_PAD_LEFT)
                     : date('Y-m-', strtotime('-1 month')).str_pad($day,2,'0',STR_PAD_LEFT);
            $stC = db()->prepare("SELECT COALESCE(SUM(amount),0) s FROM liability_events
                WHERE liability_id=? AND direction='charge' AND tx_date>=?");
            $stC->execute([$r['id'], $mStart]);
            $r['current_bill'] = (float)$stC->fetch()['s'];
            $dueDay = min((int)($r['due_day'] ?? 1), 28);
            $billMonth = (int)date('d') >= $day ? date('Y-m', strtotime('+1 month')) : date('Y-m');
            $lastD = (int)(new DateTime($billMonth.'-01'))->format('t');
            $r['bill_due'] = $billMonth.'-'.str_pad(min($dueDay,$lastD),2,'0',STR_PAD_LEFT);
            $r['min_payment'] = round($r['current_bill'] * (float)$r['min_pay_pct'] / 100, 2);
        }
        unset($r['note']);
    }
    unset($r);
    out(['rows' => $rows]);

case 'liab_add':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $name = trim($input['name'] ?? '');
    if ($name === '') out(['error' => 'Nama kosong'], 422);
    if (!in_array($input['kind'] ?? '', ['credit_card','paylater','loan'])) out(['error'=>'Jenis tidak valid'],422);
    db()->prepare("INSERT INTO liabilities (name,kind,limit_amount,outstanding,statement_day,due_day,min_pay_pct,business_id,note)
        VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$name, $input['kind'], (float)($input['limit_amount'] ?? 0),
                   (float)($input['outstanding'] ?? 0),
                   !empty($input['statement_day']) ? min(28,(int)$input['statement_day']) : null,
                   !empty($input['due_day']) ? min(28,(int)$input['due_day']) : null,
                   (float)($input['min_pay_pct'] ?? 10), 
                   !empty($input['business_id']) ? (int)$input['business_id'] : null,
                   trim($input['note'] ?? '') ?: null]);
    out(['ok' => true, 'id' => (int)db()->lastInsertId()]);

case 'liab_delete':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("UPDATE liabilities SET active=0 WHERE id=?")->execute([(int)($input['id'] ?? 0)]);
    out(['ok' => true]);

case 'liab_charge':
    // Belanja pakai liabilitas -> outstanding naik, KAS TIDAK BERGERAK
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $lid = (int)($input['liability_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $desc = trim($input['description'] ?? '');
    if (!$lid || $amount <= 0 || $desc === '') out(['error' => 'Data kurang'], 422);
    $pdo = db(); $pdo->beginTransaction();
    try {
        $stL = $pdo->prepare("SELECT * FROM liabilities WHERE id=? AND active=1 FOR UPDATE");
        $stL->execute([$lid]);
        $l = $stL->fetch();
        if (!$l) throw new RuntimeException('Liabilitas tidak ditemukan');
        if ($l['limit_amount'] > 0 && (float)$l['outstanding'] + $amount > (float)$l['limit_amount'])
            throw new InvalidArgumentException('Limit tidak cukup! Tersisa '.number_format($l['limit_amount']-$l['outstanding'],0,',','.'));
        $pdo->prepare("INSERT INTO liability_events (liability_id,tx_date,description,amount,direction)
            VALUES (?,CURDATE(),?,?,'charge')")->execute([$lid, $desc, $amount]);
        $pdo->prepare("UPDATE liabilities SET outstanding=outstanding+? WHERE id=?")->execute([$amount, $lid]);
        $pdo->commit();
        out(['ok' => true]);
    } catch (Throwable $e) { $pdo->rollBack(); out(['error' => $e->getMessage()], 422); }

case 'liab_pay':
    // Bayar tagihan/cicilan -> kas owner berkurang + outstanding turun + jurnal akuntansi
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $lid = (int)($input['liability_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    if (!$lid || $amount <= 0) out(['error' => 'Data kurang'], 422);
    // baca di luar transaksi (add_transaction punya beginTransaction sendiri)
    $stL = db()->prepare("SELECT * FROM liabilities WHERE id=? AND active=1");
    $stL->execute([$lid]);
    $l = $stL->fetch();
    if (!$l) out(['error' => 'Liabilitas tidak ditemukan'], 404);
    $pay = min($amount, (float)$l['outstanding']);
    if ($pay <= 0) out(['error' => 'Outstanding sudah nol'], 422);
    // transaksi kas keluar biasa (jurnal otomatis via add_transaction)
    $catPay = db()->query("SELECT id FROM categories WHERE name='Lain-lain Keluar'")->fetch()['id'] ?? null;
    $txId = add_transaction([
        'type' => 'keluar', 'amount' => $pay,
        'description' => '[LIAB] Bayar '.$l['name'],
        'source' => 'owner', 'user_id' => $u['id'],
        'business_id' => $l['business_id'] ? (int)$l['business_id'] : null,
        'category_id' => $catPay,
        'wallet_id' => !empty($input['wallet_id']) ? (int)$input['wallet_id'] : null,
    ]);
    db()->prepare("INSERT INTO liability_events (liability_id,tx_date,description,amount,direction,tx_id)
        VALUES (?,CURDATE(),?,?,'payment',?)")->execute([$lid, 'Bayar '.$l['name'], $pay, $txId]);
    db()->prepare("UPDATE liabilities SET outstanding=outstanding-? WHERE id=?")->execute([$pay, $lid]);
    out(['ok' => true, 'tx_id' => $txId, 'paid' => $pay,
         'remaining' => max(0, $amount - $pay)]);


case 'rec_list':
    require_login();
    $rows = db()->query("SELECT r.*, c.name cat, b.name biz, w.name wallet
        FROM recurring_transactions r
        LEFT JOIN categories c ON c.id=r.category_id
        LEFT JOIN businesses b ON b.id=r.business_id
        LEFT JOIN wallets w ON w.id=r.wallet_id
        ORDER BY r.active DESC, r.next_date")->fetchAll();
    out(['rows' => $rows, 'today' => date('Y-m-d')]);

case 'rec_add':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $name = trim($input['name'] ?? '');
    $amount = (float)($input['amount'] ?? 0);
    if ($name === '' || $amount <= 0) out(['error' => 'Nama & jumlah wajib diisi'], 422);
    if (!in_array($input['type'] ?? '', ['masuk', 'keluar'])) out(['error' => 'Jenis tidak valid'], 422);
    if (!in_array($input['frequency'] ?? '', ['weekly', 'monthly', 'yearly'])) out(['error' => 'Frekuensi tidak valid'], 422);
    $nextDate = $input['next_date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextDate)) out(['error' => 'Tanggal mulai tidak valid'], 422);
    $walletId = !empty($input['wallet_id']) ? (int)$input['wallet_id'] : (int)wallet_default()['id'];
    $cat = null;
    if (!empty($input['category'])) {
        $st = db()->prepare("SELECT id FROM categories WHERE name=?");
        $st->execute([$input['category']]);
        $cat = $st->fetch()['id'] ?? null;
    }
    $biz = !empty($input['business_id']) ? (int)$input['business_id'] : null;
    db()->prepare("INSERT INTO recurring_transactions
        (name,amount,type,category_id,business_id,scope,wallet_id,frequency,next_date,anchor_day,auto_post,note)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$name, $amount, $input['type'], $cat, $biz, $biz ? 'usaha' : 'pribadi',
                   $walletId, $input['frequency'], $nextDate, (int)substr($nextDate, 8, 2),
                   !empty($input['auto_post']) ? 1 : 0, trim($input['note'] ?? '') ?: null]);
    out(['ok' => true, 'id' => (int)db()->lastInsertId()]);

case 'rec_toggle':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id = (int)($input['id'] ?? 0);
    db()->prepare("UPDATE recurring_transactions SET active=1-active WHERE id=?")->execute([$id]);
    out(['ok' => true]);

case 'rec_delete':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("DELETE FROM recurring_transactions WHERE id=?")->execute([(int)($input['id'] ?? 0)]);
    out(['ok' => true]);

case 'rec_run':
    // Posting manual semua yang jatuh tempo sampai hari ini (cron juga pakai ini via CLI)
    $n = rec_post_due(date('Y-m-d'));
    out(['ok' => true, 'posted' => $n]);

case 'calendar':
    // Kalender keuangan: semua kewajiban & komitmen X hari ke depan
    require_login();
    $days = min(max((int)($_GET['days'] ?? 45), 1), 90);
    $today = new DateTimeImmutable('today');
    $end = $today->modify("+$days days")->format('Y-m-d');
    $rows = [];

    // 1) tagihan belum dibayar bulan ini & bulan depan (day_of_month)
    $st = db()->query("SELECT * FROM bills WHERE active=1");
    foreach ($st as $b) {
        foreach ([0, 1] as $addMonth) {
            $m = $today->modify("+{$addMonth} month");
            $y = (int)$m->format('Y'); $mo = (int)$m->format('n');
            $last = (int)$m->format('t');
            $day = min((int)$b['day_of_month'], $last);
            $dateStr = sprintf('%04d-%02d-%02d', $y, $mo, $day);
            if ($dateStr < $today->format('Y-m-d') || $dateStr > $end) continue;
            if ($addMonth === 0) {
                // cek sudah dibayar bln ini?
                $paid = db()->query("SELECT COUNT(*) c FROM transactions
                    WHERE description LIKE CONCAT('[BILL#{$b['id']}]%')
                    AND DATE_FORMAT(tx_date,'%Y-%m')='".$today->format('Y-m')."'")->fetch()['c'];
                if ($paid) continue;
            }
            $rows[] = ['date'=>$dateStr,'type'=>'bill','icon'=>'🧾',
                'title'=>$b['name'],'amount'=>(float)$b['amount'],'flow'=>'keluar',
                'overdue'=>$dateStr < $today->format('Y-m-d'),
                'business_id'=>$b['business_id']?(int)$b['business_id']:null];
        }
    }

    // 2) transaksi berulang aktif dalam rentang
    foreach (db()->query("SELECT r.*, b.icon biz_icon FROM recurring_transactions r
        LEFT JOIN businesses b ON b.id=r.business_id WHERE r.active=1") as $r) {
        $d = $r['next_date']; $guard = 0;
        while ($d <= $end && $guard++ < 90) {
            if ($d >= $today->format('Y-m-d')) {
                $rows[] = ['date'=>$d,'type'=>'recurring','icon'=>'🔁',
                    'title'=>$r['name'],'amount'=>(float)$r['amount'],
                    'flow'=>$r['type'],
                    'business_id'=>$r['business_id']?(int)$r['business_id']:null];
            }
            $d = rec_next_date($d, $r['frequency'], (int)($r['anchor_day'] ?? 1));
        }
    }

    // 3) target tabungan dengan deadline dalam rentang & belum tercapai
    foreach (db()->query("SELECT * FROM goals WHERE done=0 AND deadline IS NOT NULL") as $g) {
        if ($g['deadline'] < $today->format('Y-m-d') || $g['deadline'] > $end) continue;
        $remaining = max(0, (float)$g['target_amount'] - (float)$g['saved_amount']);
        if ($remaining <= 0) continue;
        $rows[] = ['date'=>$g['deadline'],'type'=>'goal','icon'=>'🎯',
            'title'=>$g['name'].' (sisa target)','amount'=>$remaining,'flow'=>'goal',
            'overdue'=>false,'business_id'=>null];
    }

    usort($rows, fn($a, $b) => strcmp($a['date'], $b['date']));
    $totalKeluar = array_sum(array_map(fn($r) => in_array($r['flow'], ['keluar'], true) ? $r['amount'] : 0, $rows));
    out([
        'rows' => $rows,
        'days' => $days,
        'total_keluar' => $totalKeluar,
        'count' => count($rows),
        'today' => $today->format('Y-m-d'),
    ]);

/* ---------- MASTER DATA ---------- */
case 'meta':
    require_login();
    out([
        'businesses' => db()->query("SELECT id,name,icon,color,note FROM businesses WHERE active=1")->fetchAll(),
        'categories' => db()->query("SELECT id,name,kind,scope_hint,color FROM categories ORDER BY id")->fetchAll(),
        'users' => db()->query("SELECT u.id,u.username,u.display_name,u.role,u.business_id,b.name biz
            FROM users u LEFT JOIN businesses b ON b.id=u.business_id WHERE u.active=1")->fetchAll(),
    ]);

/* ---------- TRANSAKSI: daftar dengan filter ---------- */
case 'tx_list':
    require_login();
    $w = []; $a = [];
    $sql = "SELECT t.*, u.display_name, b.name biz, c.name cat, w.name wallet
        FROM transactions t
        LEFT JOIN users u ON u.id=t.user_id LEFT JOIN businesses b ON b.id=t.business_id
        LEFT JOIN categories c ON c.id=t.category_id LEFT JOIN wallets w ON w.id=t.wallet_id WHERE 1=1";
    if (!empty($input['scope'])) { $sql .= " AND t.scope=?"; $a[] = $input['scope']; }
    if (!empty($input['business_id'])) { $sql .= " AND t.business_id=?"; $a[] = (int)$input['business_id']; }
    if (!empty($input['type'])) { $sql .= " AND t.type=?"; $a[] = $input['type']; }
    if (!empty($input['from'])) { $sql .= " AND t.tx_date>=?"; $a[] = $input['from']; }
    if (!empty($input['to'])) { $sql .= " AND t.tx_date<=?"; $a[] = $input['to']; }
    if (!empty($input['q'])) { $sql .= " AND t.description LIKE ?"; $a[] = '%'.$input['q'].'%'; }
    $limit = min(max((int)($input['limit'] ?? 100), 1), 500);
    $sql .= " ORDER BY t.tx_date DESC, t.id DESC LIMIT $limit";
    $st = db()->prepare($sql); $st->execute($a);
    out(['rows' => $st->fetchAll()]);

/* ---------- BILLS (tagihan rutin + autopay) ---------- */
case 'bills':
    require_login();
    $period = date('Y-m');
    $rows = db()->query("SELECT b.*, biz.name biz FROM bills b LEFT JOIN businesses biz ON biz.id=b.business_id
        WHERE b.active=1 ORDER BY b.day_of_month")->fetchAll();
    foreach ($rows as &$r) {
        $r['paid_this_month'] = (bool)db()->query("SELECT COUNT(*) c FROM transactions
            WHERE description LIKE CONCAT('[BILL#{$r['id']}]%') AND DATE_FORMAT(tx_date,'%Y-%m')='$period'")->fetch()['c'];
        unset($r['last_paid_period']);
    }
    out(['bills' => $rows, 'period' => $period]);

case 'bill_add':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $st = db()->prepare("INSERT INTO bills (name,amount,day_of_month,business_id) VALUES (?,?,?,?)");
    $st->execute([trim($input['name']??''), (float)($input['amount']??0),
        max(1,min(28,(int)($input['day']??1))), !empty($input['business_id'])?(int)$input['business_id']:null]);
    out(['ok'=>true,'id'=>(int)db()->lastInsertId()]);

case 'bill_pay':
    // Bayar tagihan -> jadi transaksi keluar otomatis, kas owner terpotong
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id = (int)($input['id'] ?? 0);
    $st = db()->prepare("SELECT * FROM bills WHERE id=?"); $st->execute([$id]);
    if (!$b = $st->fetch()) out(['error'=>'Tagihan tidak ada'],404);
    $bizId = $b['business_id'] ? (int)$b['business_id'] : null;
    add_transaction([
        'type'=>'keluar','amount'=>(float)$b['amount'],
        'description'=>'[BILL#'.$b['id'].'] '.$b['name'],
        'source'=>'owner','user_id'=>$u['id'],'business_id'=>$bizId,
        'scope'=>$bizId?'usaha':'pribadi',
    ]);
    out(['ok'=>true]);

case 'bill_delete':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("UPDATE bills SET active=0 WHERE id=?")->execute([(int)($input['id']??0)]);
    out(['ok'=>true]);

/* ---------- GOALS (target tabungan / dana darurat) ---------- */
case 'goals':
    require_login();
    out(['goals' => db()->query("SELECT * FROM goals ORDER BY done,id")->fetchAll()]);

case 'goal_add':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("INSERT INTO goals (name,target_amount,deadline) VALUES (?,?,?)")
        ->execute([trim($input['name']??''), (float)($input['target']??0), $input['deadline'] ?: null]);
    out(['ok'=>true]);

case 'goal_deposit':
    // Setor ke target: uang pindah antar dompet TIDAK berkurang total kas,
    // tapi progress target naik. Catat sebagai transfer internal.
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id=(int)($input['id']??0); $amt=(float)($input['amount']??0);
    if ($amt<=0) out(['error'=>'Nominal harus > 0'],422);
    $st=db()->prepare("UPDATE goals SET saved_amount=saved_amount+? WHERE id=?"); $st->execute([$amt,$id]);
    $g=db()->prepare("SELECT saved_amount,target_amount FROM goals WHERE id=?"); $g->execute([$id]);
    $row=$g->fetch();
    if ((float)$row['saved']>=(float)$row['target_amount']) db()->prepare("UPDATE goals SET done=1 WHERE id=?")->execute([$id]);
    out(['ok'=>true,'saved'=>(float)$row['saved_amount']]);

case 'goal_delete':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("DELETE FROM goals WHERE id=?")->execute([(int)($input['id']??0)]);
    out(['ok'=>true]);

/* ---------- BUDGETS (anggaran bulanan) ---------- */
case 'budgets':
    require_login();
    $period = date('Y-m');
    $rows = db()->query("SELECT bu.*, c.name cat FROM budgets bu JOIN categories c ON c.id=bu.category_id
        WHERE bu.period='$period'")->fetchAll();
    foreach ($rows as &$r) {
        if ($r['business_id']) {
            $s = laporan_bulan($period, (int)$r['business_id']);
            $r['spent'] = $s['keluar'];
        } else {
            $p = laporan_bulan($period, null);
            $r['spent'] = $p['keluar'];
        }
        unset($r['period']);
    }
    out(['budgets'=>$rows,'period'=>$period]);

case 'budget_set':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $catName = trim($input['category']??''); $period=date('Y-m');
    $st=db()->prepare("SELECT id FROM categories WHERE name=?"); $st->execute([$catName]);
    if (!$cid=$st->fetch()['id']??null) out(['error'=>'Kategori tidak ada'],404);
    $bid = !empty($input['business_id'])?(int)$input['business_id']:null;
    db()->prepare("INSERT INTO budgets (business_id,category_id,period,amount) VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE amount=VALUES(amount)")
        ->execute([$bid,$cid,$period,(float)($input['amount']??0)]);
    out(['ok'=>true]);

case 'budget_delete':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("DELETE FROM budgets WHERE id=?")->execute([(int)($input['id']??0)]);
    out(['ok'=>true]);

/* ---------- KELOLA CABANG & KARIAWAN ---------- */
case 'biz_add':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $name = trim($input['name']??'');
    if ($name==='') out(['error'=>'Nama kosong'],422);
    $icon = trim($input['icon'] ?? '') ?: '🏪';
    $color = strtolower(trim($input['color'] ?? ''));
    if (!preg_match('/^#[0-9a-f]{6}$/', $color)) $color = '#155eef';
    db()->prepare("INSERT INTO businesses (name,icon,color,note) VALUES (?,?,?,?)")
        ->execute([$name, $icon, $color, trim($input['note'] ?? '') ?: null]);
    out(['ok'=>true,'id'=>(int)db()->lastInsertId()]);

case 'biz_update':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id = (int)($input['id'] ?? 0);
    if (!$id || trim($input['name'] ?? '') === '') out(['error' => 'Data kurang'], 422);
    $color = strtolower(trim($input['color'] ?? ''));
    if (!preg_match('/^#[0-9a-f]{6}$/', $color)) $color = '#155eef';
    db()->prepare("UPDATE businesses SET name=?, icon=?, color=?, note=? WHERE id=?")
        ->execute([trim($input['name']), trim($input['icon'] ?? '') ?: '🏪', $color,
                   trim($input['note'] ?? '') ?: null, $id]);
    out(['ok' => true]);

case 'biz_delete':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("UPDATE businesses SET active=0 WHERE id=?")->execute([(int)($input['id']??0)]);
    out(['ok'=>true]);

case 'user_add':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $username = trim($input['username']??''); $pass=(string)($input['password']??'');
    if ($username===''||strlen($pass)<4) out(['error'=>'Username kosong / password minimal 4 karakter'],422);
    $st=db()->prepare("SELECT id FROM users WHERE username=?"); $st->execute([$username]);
    if ($st->fetch()) out(['error'=>'Username sudah dipakai'],422);
    db()->prepare("INSERT INTO users (username,password_hash,display_name,role,business_id) VALUES (?,?,?,?,?)")
        ->execute([$username, password_hash($pass,PASSWORD_DEFAULT),
            trim($input['display_name']?:$username), 'kariawan',
            !empty($input['business_id'])?(int)$input['business_id']:null]);
    out(['ok'=>true,'id'=>(int)db()->lastInsertId(),
        'bot_hint'=>"Kariawan kirim: /start $username $pass"]);

case 'user_login_code':
    // Buat kode login 6 digit untuk kariawan (dipakai /mulai KODE di bot)
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $uid = (int)($input['id'] ?? 0);
    $st = db()->prepare("SELECT username,display_name FROM users WHERE id=?"); $st->execute([$uid]);
    if (!$row = $st->fetch()) out(['error'=>'User tidak ada'],404);
    $code = gen_login_code($uid);
    out(['ok'=>true,'code'=>$code,'username'=>$row['username'],
         'hint'=>"Kariawan kirim ke bot: /mulai $code"]);

case 'user_toggle':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("UPDATE users SET active=1-active WHERE id=?")->execute([(int)($input['id']??0)]);
    out(['ok'=>true]);

/* ---------- SETTINGS ---------- */
case 'settings_get':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $mask = function(string $v): string {
        if ($v === '') return '';
        return strlen($v) > 8 ? substr($v,0,4).'••••••••'.substr($v,-4) : '••••';
    };
    out([
        'bot_token_masked' => $mask(cfg('bot_token')),
        'bot_token_set' => cfg('bot_token') !== '',
        'bot_username' => cfg('bot_username'),
        'owner_chat_id' => cfg('owner_chat_id'),
        'gemini_key_masked' => $mask(cfg('gemini_key')),
        'gemini_key_set' => cfg('gemini_key') !== '',
        'notify_transactions' => cfg('notify_transactions','1'),
        'notify_bills' => cfg('notify_bills','1'),
    ]);

case 'settings_set':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $allowed = ['bot_token','owner_chat_id','gemini_key','notify_transactions','notify_bills'];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $input)) {
            if ($input[$k] === '' || strpos((string)$input[$k],'••••') !== false) continue; // jangan timpa dgn mask
            set_cfg($k, (string)$input[$k]);
        }
    }
    out(['ok'=>true]);

case 'tg_test':
    // Test koneksi bot: getMe + kirim pesan tes kalau chat id ada
    require_login();
    require_once __DIR__ . '/src/config.php';
    $token = bot_token();
    if ($token === '') out(['ok'=>false,'msg'=>'Token bot belum diisi']);
    $me = tg('getMe');
    if (!$me || empty($me['ok'])) out(['ok'=>false,'msg'=>'Token tidak valid / tidak bisa hubungi Telegram']);
    set_cfg('bot_username', $me['result']['username'] ?? '');
    $chatId = cfg('owner_chat_id');
    $sent = false;
    if ($chatId) {
        $r = tg('sendMessage',['chat_id'=>$chatId,'text'=>'✅ Koneksi Dompet Owner OK! Bot siap dipakai.']);
        $sent = !empty($r['ok']);
    }
    out(['ok'=>true,'bot_name'=>($me['result']['first_name']??'').' (@'.($me['result']['username']??'').')',
         'message_sent'=>$sent,
         'msg'=>$sent ? 'Terhubung & pesan tes terkirim!' : 'Bot valid: @'.($me['result']['username']??'').'. Isi chat ID lalu test lagi untuk kirim pesan.']);

case 'gemini_test':
    require_login();
    require_once __DIR__ . '/src/ai.php';
    if (gemini_key()==='') out(['ok'=>false,'msg'=>'API key Gemini belum diisi']);
    $ans = ai_answer('Jawab satu kata: siap?');
    $bad = str_contains($ans,'belum dipasang')||str_contains($ans,'tidak bisa dihubungi')||str_contains($ans,'tidak memberi');
    out(['ok'=>!$bad,'msg'=>$bad?'Key ditolak Gemini / koneksi gagal':'AI merespon: '.$ans]);

case 'wallet_add':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $name=trim($input['name']??''); if($name==='') out(['error'=>'Nama kosong'],422);
    $st=db()->prepare("INSERT INTO wallets (name,kind,balance,is_default) VALUES (?,?,?,?)");
    $st->execute([$name, in_array($input['kind'],['cash','bank','ewallet','other'])?$input['kind']:'cash',
        (float)($input['balance']??0), 0]);
    out(['ok'=>true,'id'=>(int)db()->lastInsertId()]);

case 'wallet_rename':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("UPDATE wallets SET name=?, kind=? WHERE id=?")
        ->execute([trim($input['name']??''), $input['kind']??'cash', (int)($input['id']??0)]);
    out(['ok'=>true]);

case 'wallet_delete':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id=(int)($input['id']??0);
    $n=db()->prepare("SELECT COUNT(*) c FROM transactions WHERE wallet_id=? OR wallet_dest_id=?");
    $n->execute([$id,$id]);
    if ((int)$n->fetch()['c']>0) out(['error'=>'Kas ini punya riwayat transaksi, tidak bisa dihapus. Nonaktifkan saja / kosongkan namanya.'],422);
    db()->prepare("DELETE FROM wallets WHERE id=?")->execute([$id]);
    out(['ok'=>true]);

case 'wallet_default':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $pdo=db(); $pdo->beginTransaction();
    $pdo->exec("UPDATE wallets SET is_default=0");
    $pdo->prepare("UPDATE wallets SET is_default=1 WHERE id=?")->execute([(int)($input['id']??0)]);
    $pdo->commit();
    out(['ok'=>true]);

/* ---------- EXPORT CSV (Excel-compatible) ---------- */
case 'export_csv':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $sql = "SELECT t.tx_date, t.type, t.amount, t.description, t.scope, b.name biz, c.name cat, w.name wallet, u.display_name
        FROM transactions t
        LEFT JOIN businesses b ON b.id=t.business_id LEFT JOIN categories c ON c.id=t.category_id
        LEFT JOIN wallets w ON w.id=t.wallet_id LEFT JOIN users u ON u.id=t.user_id WHERE 1=1";
    $a = [];
    if (!empty($input['from'])) { $sql .= " AND t.tx_date>=?"; $a[]=$input['from']; }
    if (!empty($input['to'])) { $sql .= " AND t.tx_date<=?"; $a[]=$input['to']; }
    if (!empty($input['scope'])) { $sql .= " AND t.scope=?"; $a[]=$input['scope']; }
    $sql .= " ORDER BY t.tx_date, t.id";
    $st = db()->prepare($sql); $st->execute($a);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=dompet-owner-'.date('Ymd').'.csv');
    echo "\xEF\xBB\xBF"; // BOM agar Excel baca UTF-8
    $out = fopen('php://output','w');
    $hdr = ['Tanggal','Jenis','Nominal','Keterangan','Scope','Cabang','Kategori','Kas','Oleh'];
    fputcsv($out, $hdr, escape: '\\');
    foreach ($st as $r) fputcsv($out, [$r['tx_date'],$r['type'],$r['amount'],$r['description'],
        $r['scope'],$r['biz'],$r['cat'],$r['wallet'],$r['display_name']], escape: '\\');
    fclose($out);
    exit;

/* ---------- SAFE TO SPEND & INSIGHT ---------- */
case 'insight':
    require_login();
    $period = date('Y-m');
    $daysInMonth = (int)date('t');
    $dayNow = (int)date('j');
    $daysLeft = max(1, $daysInMonth - $dayNow + 1);
    $p = laporan_bulan($period, null);
    // rata-rata pengeluaran harian bulan ini
    $avgDaily = round($p['keluar'] / $dayNow, 0);
    // proyeksi akhir bulan
    $projection = $avgDaily * $daysInMonth;
    // safe to spend: kalau ada anggaran pribadi total, pakai itu; else pakai pola pemasukan
    $budgetTotal = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM budgets WHERE business_id IS NULL AND period='$period'")->fetch()['s'];
    $safeToSpend = null;
    if ($budgetTotal > 0) {
        $safeToSpend = max(0, $budgetTotal - $p['keluar']);
    }
    out([
        'pribadi' => $p,
        'days_left' => $daysLeft,
        'avg_daily' => (float)$avgDaily,
        'projection' => (float)$projection,
        'budget_total' => $budgetTotal,
        'safe_to_spend' => $safeToSpend,
    ]);

/* ---------- AKUNTANSI ---------- */
case 'acc_pl':
    require_login();
    require_once __DIR__ . '/src/ledger.php';
    out(['pl'=>profit_loss($input['period'] ?? date('Y-m'))]);

case 'acc_balance':
    require_login();
    require_once __DIR__ . '/src/ledger.php';
    out(['bs'=>balance_sheet()]);

case 'acc_trial':
    require_login();
    require_once __DIR__ . '/src/ledger.php';
    out(['trial'=>trial_balance($input['from']??null,$input['to']??null)]);

case 'acc_cashflow':
    require_login();
    require_once __DIR__ . '/src/ledger.php';
    out(['cf'=>cash_flow($input['period'] ?? date('Y-m'))]);

case 'tax_rules':
    require_login();
    out(['rules'=>db()->query("SELECT tr.*, b.name biz FROM tax_rules tr
        LEFT JOIN businesses b ON b.id=tr.business_id WHERE tr.name NOT LIKE 'TEST%' ORDER BY tr.id")->fetchAll()]);
case 'tax_rule_add':
    $u=require_login(); if($u['role']!=='owner')out(['error'=>'Khusus owner'],403);
    db()->prepare("INSERT INTO tax_rules (name,tax_type,rate_pct,business_id,category_name,tx_kind,valid_from,valid_to)
        VALUES (?,?,?,?,?,?,?,?)")->execute([
        trim($input['name']??''), $input['tax_type']??'non_pajak', (float)($input['rate_pct']??0),
        !empty($input['business_id'])?(int)$input['business_id']:null,
        !empty($input['category_name'])?$input['category_name']:null,
        $input['tx_kind']??'masuk', $input['valid_from']?:null, $input['valid_to']?:null]);
    out(['ok'=>true,'id'=>(int)db()->lastInsertId()]);
case 'tax_rule_delete':
    $u=require_login(); if($u['role']!=='owner')out(['error'=>'Khusus owner'],403);
    db()->prepare("UPDATE tax_rules SET is_active=0 WHERE id=?")->execute([(int)($input['id']??0)]);
    out(['ok'=>true]);
case 'tax_unresolved':
    require_login();
    out(['rows'=>db()->query("SELECT tl.*, t.tx_date,t.description,t.amount FROM tax_lines tl
        JOIN transactions t ON t.id=tl.tx_id WHERE tl.status='unresolved' ORDER BY tl.id DESC LIMIT 100")->fetchAll()]);

/* ---------- PIUTANG ---------- */
case 'receivables':
    require_login();
    out(['rows'=>db()->query("SELECT r.*, b.name biz FROM receivables r LEFT JOIN businesses b ON b.id=r.business_id
        WHERE r.status<>'paid' ORDER BY r.due_date IS NULL, r.due_date, r.id DESC")->fetchAll()]);
case 'receivable_add':
    $u=require_login(); if($u['role']!=='owner')out(['error'=>'Khusus owner'],403);
    // piutang = penjualan tempo: jurnal Piutang(D) Pendapatan(K), TANPA menggerakkan kas
    require_once __DIR__.'/src/ledger.php';
    $amt=(float)($input['amount']??0); $biz=!empty($input['business_id'])?(int)$input['business_id']:null;
    if($amt<=0)out(['error'=>'Nominal > 0'],422);
    $eid=post_journal(date('Y-m-d'),"Piutang: ".trim($input['debtor']??''),[
        ['account'=>'1-1300','debit'=>$amt],
        ['account'=>'4-1000','credit'=>$amt]],null);
    db()->prepare("INSERT INTO receivables (business_id,debtor_name,amount,due_date,tx_id) VALUES (?,?,?,?,NULL)")
        ->execute([$biz,trim($input['debtor']??'Kustomer'),$amt,$input['due_date']?:null]);
    out(['ok'=>true,'id'=>(int)db()->lastInsertId(),'entry_id'=>$eid]);
case 'receivable_pay':
    // bayar piutang: kas masuk, piutang berkurang (bukan pendapatan baru!)
    $u=require_login(); if($u['role']!=='owner')out(['error'=>'Khusus owner'],403);
    $id=(int)($input['id']??0); $pay=(float)($input['amount']??0);
    $st=db()->prepare("SELECT * FROM receivables WHERE id=?"); $st->execute([$id]);
    if(!$r=$st->fetch())out(['error'=>'Piutang tidak ada'],404);
    require_once __DIR__.'/src/ledger.php';
    $w = wallet_default()['id'];
    post_journal(date('Y-m-d'),"Pelunasan piutang {$r['debtor_name']}",[
        ['account'=>coa_for_wallet((int)$w),'debit'=>$pay],
        ['account'=>'1-1300','credit'=>$pay]],null);
    db()->prepare("UPDATE wallets SET balance=balance+? WHERE id=?")->execute([$pay,$w]);
    $paid=$r['paid_amount']+$pay;
    db()->prepare("UPDATE receivables SET paid_amount=?, status=? WHERE id=?")
        ->execute([$paid, $paid>=(float)$r['amount']?'paid':'partial', $id]);
    out(['ok'=>true]);

/* ---------- AI ---------- */
case 'ai_ask':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    require_once __DIR__ . '/src/ai.php';
    $q = trim($input['question'] ?? '');
    if ($q === '') out(['error' => 'Pertanyaan kosong'], 422);
    out(['answer' => ai_answer($q)]);

case 'tx_delete':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id = (int)($input['id'] ?? 0);
    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare("SELECT * FROM transactions WHERE id=? FOR UPDATE");
    $st->execute([$id]);
    if (!$t = $st->fetch()) { $pdo->rollBack(); out(['error' => 'Tidak ketemu'], 404); }
    $sign = ['masuk' => -1, 'keluar' => 1][$t['type']] ?? 0;
    if ($sign !== 0) {
        $pdo->prepare("UPDATE wallets SET balance=balance+? WHERE id=?")
            ->execute([$sign * $t['amount'], $t['wallet_id']]);
    } else {
        $pdo->prepare("UPDATE wallets SET balance=balance-? WHERE id=?")->execute([$t['amount'], $t['wallet_dest_id']]);
        $pdo->prepare("UPDATE wallets SET balance=balance+? WHERE id=?")->execute([$t['amount'], $t['wallet_id']]);
    }
    $pdo->prepare("DELETE FROM transactions WHERE id=?")->execute([$id]);
    $pdo->commit();
    out(['ok' => true]);

case 'backup_run':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $bash = 'C:\\Program Files\\Git\\bin\\bash.exe';
    $script = 'C:\\Users\\IVO\\dompet-owner\\tools\\backup-db.sh';
    if (!is_file($bash) || !is_file($script)) out(['error' => 'Tools backup tidak ditemukan'], 500);
    $cmd = '"' . $bash . '" "' . $script . '" 2>&1';
    $outRaw = shell_exec($cmd);
    $ok = stripos((string)$outRaw, 'OK:') !== false;
    out(array_merge(
        $ok ? ['ok' => true] : ['error' => 'Backup gagal'],
        ['detail' => trim((string)$outRaw)]
    ));

case 'cat_add':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $name = trim($input['name'] ?? '');
    if ($name === '') out(['error' => 'Nama kategori kosong'], 422);
    $color = strtolower(trim($input['color'] ?? ''));
    if (!preg_match('/^#[0-9a-f]{6}$/', $color)) $color = null;
    $kind = in_array($input['kind'] ?? '', ['masuk', 'keluar', 'both']) ? $input['kind'] : 'both';
    $st = db()->prepare("SELECT id FROM categories WHERE name=?");
    $st->execute([$name]);
    if ($st->fetch()) out(['error' => 'Kategori sudah ada'], 422);
    db()->prepare("INSERT INTO categories (name,kind,color) VALUES (?,?,?)")->execute([$name, $kind, $color]);
    out(['ok' => true, 'id' => (int)db()->lastInsertId()]);

case 'cat_rename':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id = (int)($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    if (!$id || $name === '') out(['error' => 'Data kurang'], 422);
    db()->prepare("UPDATE categories SET name=? WHERE id=?")->execute([$name, $id]);
    out(['ok' => true]);

case 'cat_color':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id = (int)($input['id'] ?? 0);
    $color = strtolower(trim($input['color'] ?? ''));
    if (!$id || !preg_match('/^#[0-9a-f]{6}$/', $color)) out(['error' => 'Warna tidak valid (#rrggbb)'], 422);
    db()->prepare("UPDATE categories SET color=? WHERE id=?")->execute([$color, $id]);
    out(['ok' => true]);

case 'cat_delete':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id = (int)($input['id'] ?? 0);
    if (!$id) out(['error' => 'ID kurang'], 422);
    try {
        // transaksi lama tetap utuh: category_id jadi NULL (ON DELETE SET NULL)
        db()->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
    } catch (PDOException $e) {
        out(['error' => 'Kategori dipakai anggaran — hapus anggarannya dulu'], 422);
    }
    out(['ok' => true]);

default:
    out(['error' => 'Action tidak dikenal'], 400);
}
} catch (InvalidArgumentException $e) {
    out(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    out(['error' => 'Server error: ' . $e->getMessage()], 500);
}
