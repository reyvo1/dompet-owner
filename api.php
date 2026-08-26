<?php
// REST API Dompet Owner (dipakai dashboard web)
require __DIR__ . '/src/config.php';
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
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

/* ---------- LAMPIRAN FOTO NOTA (upload multipart, di luar switch JSON) ---------- */
if ($method === 'POST' && $action === 'att_upload') {
    $u = require_login();
    if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK)
        out(['error' => 'File tidak terkirim'], 422);
    $txId = (int)($_POST['tx_id'] ?? 0);
    if (!$txId) out(['error' => 'tx_id kurang'], 422);
    $st = db()->prepare("SELECT id FROM transactions WHERE id=?"); $st->execute([$txId]);
    if (!$st->fetch()) out(['error' => 'Transaksi tidak ada'], 404);
    // validasi: hanya gambar, maks 5MB
    if ($_FILES['file']['size'] > 5 * 1024 * 1024) out(['error' => 'Maksimal 5MB'], 422);
    $info = @getimagesize($_FILES['file']['tmp_name']);
    if (!$info) out(['error' => 'Hanya file gambar (jpg/png/webp)'], 422);
    $ext = image_type_to_extension($info[2], false); // jpeg,png,webp,...
    if (!in_array($ext, ['jpeg','png','webp'])) out(['error' => 'Format harus jpg/png/webp'], 422);
    $dir = __DIR__ . '/public/uploads';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $name = 'tx' . $txId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $name))
        out(['error' => 'Gagal simpan file'], 500);
    db()->prepare("INSERT INTO tx_attachments (tx_id,filename,original_name) VALUES (?,?,?)")
        ->execute([$txId, $name, substr((string)($_FILES['file']['name'] ?? ''), 0, 255)]);
    out(['ok' => true, 'filename' => $name]);
}
if ($action === 'att_list') {
    require_login();
    $txId = (int)($input['tx_id'] ?? $_GET['tx_id'] ?? 0);
    $st = db()->prepare("SELECT id,filename,original_name FROM tx_attachments WHERE tx_id=? ORDER BY id");
    $st->execute([$txId]);
    out(['rows' => $st->fetchAll()]);
}

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
    $bigLimit = (float)(cfg('big_tx_limit') ?: 1000000);
    if ((float)($input['amount'] ?? 0) >= $bigLimit && $bigLimit > 0) {
        notify_owner_force("⚠️ TRANSAKSI BESAR di {$biz}\nRp ".number_format((float)$input['amount'],0,',','.')."\n".
            trim($input['description'] ?? '')." ({$u['display_name']})");
    }
    out(['ok'=>true,'id'=>$id]);

/* ---------- RINGKASAN DASHBOARD OWNER ---------- */
case 'summary':
    require_login();
    $period = date('Y-m');
    $kas = wallets_all();
    $pribadi = laporan_bulan($period, null);
    // filter profil bisnis (multi-profil): biz=ID -> hanya bisnis tsb; biz=pribadi -> hanya pribadi; kosong = semua
    $bizFilter = isset($_GET['biz']) && $_GET['biz'] !== '' && $_GET['biz'] !== 'pribadi' ? (int)$_GET['biz'] : null;
    $pribadiOnly = (($_GET['biz'] ?? '') === 'pribadi');
    if ($pribadiOnly) {
        $usahaMasuk = 0.0; $usahaKeluar = 0.0;
    } else if ($bizFilter !== null) {
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
    // tutup buku: transaksi baru tidak boleh masuk periode terkunci
    if (!empty($input['tx_date'])) {
        $lockP = substr(preg_replace('/[^0-9-]/', '', (string)$input['tx_date']), 0, 7);
            $chk = db()->prepare("SELECT COUNT(*) c FROM closed_periods WHERE period=?");
            $chk->execute([$lockP]);
            if ($chk->fetch()['c'])
            out(['error'=>"Periode {$lockP} sudah ditutup (terkunci)"],422);
    }
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
    // transaksi besar -> notif khusus ke owner
    $bigLimit = (float)(cfg('big_tx_limit') ?: 1000000);
    if ((float)($input['amount'] ?? 0) >= $bigLimit && $bigLimit > 0) {
        notify_owner_force("⚠️ TRANSAKSI BESAR\nRp ".number_format((float)$input['amount'],0,',','.')."\n".
            ($input['description'] ?? '')."\n(Oleh: {$u['display_name']})");
    }
    out(['ok' => true, 'id' => $id, 'smart' => $smartHit ? $smartHit['category'] : null]);

/* ---------- GANTI PASSWORD ---------- */
case 'change_password':
    $u = require_login();
    $cur = (string)($input['current'] ?? '');
    $new = (string)($input['new'] ?? '');
    if (!password_verify($cur, $u['password_hash'])) out(['error' => 'Password sekarang salah'], 422);
    if (strlen($new) < 6) out(['error' => 'Password baru minimal 6 karakter'], 422);
    db()->prepare("UPDATE users SET password_hash=? WHERE id=?")
        ->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
    out(['ok' => true]);

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

/* ---------- DETEKSI ANOMALI PENGELUARAN ---------- */
case 'anomalies':
    require_login();
    $rows = [];
    $st = db()->query("SELECT c.name cat, AVG(t.tot) avg3
        FROM (SELECT category_id, SUM(amount) tot FROM transactions
              WHERE type='keluar' AND category_id IS NOT NULL
              AND DATE_FORMAT(tx_date,'%Y-%m') BETWEEN DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'%Y-%m')
                  AND DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m')
              GROUP BY category_id, DATE_FORMAT(tx_date,'%Y-%m')) t
        JOIN categories c ON c.id=t.category_id
        GROUP BY c.name HAVING avg3 > 0");
    $avg = [];
    foreach ($st as $r) $avg[$r['cat']] = (float)$r['avg3'];
    if ($avg) {
        $in = implode(',', array_fill(0, count($avg), '?'));
        $st3 = db()->prepare("SELECT c.name cat, t.tx_date, t.amount, t.description FROM transactions t
            JOIN categories c ON c.id=t.category_id
            WHERE t.type='keluar' AND c.name IN ($in)
            AND DATE_FORMAT(t.tx_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')
            ORDER BY t.amount DESC LIMIT 100");
        $st3->execute(array_keys($avg));
        foreach ($st3 as $r) {
            $base = $avg[$r['cat']];
            if ((float)$r['amount'] >= $base * 2 && (float)$r['amount'] >= 50000) {
                $rows[] = ['cat'=>$r['cat'],'tx_date'=>$r['tx_date'],'amount'=>(float)$r['amount'],
                    'description'=>$r['description'],'multiple'=>round((float)$r['amount']/$base,1)];
            }
        }
    }
    out(['rows' => array_slice($rows, 0, 5)]);

/* ---------- PROYEKSI ARUS KAS 30 HARI ---------- */
case 'cashflow_forecast':
    require_login();
    $kas = total_kas();
    $in = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='masuk'
        AND tx_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)")->fetch()['s'] / 90;
    $out = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='keluar'
        AND tx_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)")->fetch()['s'] / 90;
    $bills = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM bills WHERE active=1")->fetch()['s'];
    $net = $in - $out;
    out(['kas_now'=>$kas,
         'daily_in'=>round($in), 'daily_out'=>round($out),
         'proj_30d'=>round($kas + $net*30 - min($bills, max(0,$net*30))),
         'optimis_30d'=>round($kas + max(0,$net)*30),
         'pesimis_30d'=>round($kas - ($out*30) + ($in*30*0.6)),
         'monthly_bills'=>$bills]);

/* ---------- HEAD-TO-HEAD ANTAR CABANG ---------- */
case 'branch_rank':
    require_login();
    $period = date('Y-m');
    $list = [];
    foreach (db()->query("SELECT * FROM businesses WHERE active=1") as $b) {
        $l = laporan_bulan($period, (int)$b['id']);
        $l['id']=(int)$b['id']; $l['name']=$b['name']; $l['icon']=$b['icon'];
        $l['margin'] = $l['masuk']>0 ? round($l['laba']/$l['masuk']*100) : null;
        $list[] = $l;
    }
    usort($list, fn($a,$b2) => $b2['laba'] <=> $a['laba']);
    out(['period'=>$period,'ranking'=>$list]);

/* ---------- APPROVAL FLOW ---------- */
case 'appr_list':
    require_login();
    $u = require_login();
    if ($u['business_id']) { // manajer/kariawan cabang: hanya cabangnya
        $st = db()->prepare("SELECT a.*, u.display_name requester, b.name biz FROM approvals a
            LEFT JOIN users u ON u.id=a.user_id LEFT JOIN businesses b ON b.id=a.business_id
            WHERE a.status='pending' AND a.business_id=? ORDER BY a.created_at DESC");
        $st->execute([(int)$u['business_id']]);
    } else {
        $st = db()->prepare("SELECT a.*, u.display_name requester, b.name biz FROM approvals a
            LEFT JOIN users u ON u.id=a.user_id LEFT JOIN businesses b ON b.id=a.business_id
            WHERE a.status='pending' ORDER BY a.created_at DESC");
        $st->execute();
    }
    out(['rows'=>$st->fetchAll()]);

case 'appr_request':
    $u = require_login();
    if (($u['role'] ?? '') === 'owner' || !$u['business_id'])
        out(['error'=>'Approval khusus kariawan/manajer cabang'],422);
    $id = db()->prepare("INSERT INTO approvals (user_id,business_id,type,amount,description) VALUES (?,?,?,?,?)");
    $id->execute([$u['id'],$u['business_id'],
        ($input['type']??'')==='masuk'?'masuk':'keluar',
        (float)($input['amount']??0), trim($input['description']??'')]);
    notify_owner_force("PERMINTAAN PERSETUJUAN\nRp ".number_format((float)($input['amount']??0),0,',','.').
        "\n".trim($input['description']??'')."\nOleh: {$u['display_name']}\nBuka dashboard -> Persetujuan");
    audit_log($u,'appr_request','Rp '.($input['amount']??0).' '.($input['description']??''),'approval',$id,null,['amount'=>(float)($input['amount']??0),'description'=>$input['description']??'']);
    out(['ok'=>true,'id'=>(int)db()->lastInsertId()]);

case 'appr_decide':
    $u = require_login();
    require_role($u, ['owner', 'manajer']);
    $id=(int)($input['id']??0); $ok=($input['decision']??'')==='approve';
    $st=db()->prepare("SELECT a.*, us.display_name FROM approvals a LEFT JOIN users us ON us.id=a.user_id WHERE a.id=? AND a.status='pending'");
    $st->execute([$id]);
    if(!$ap=$st->fetch()) out(['error'=>'Permintaan tidak ada / sudah diputuskan'],404);
    if($ok){
        $bizId=$ap['business_id']?(int)$ap['business_id']:null;
        $txId=add_transaction(['type'=>$ap['type'],'amount'=>(float)$ap['amount'],
            'description'=>$ap['description'].' [disetujui]','source'=>'approval',
            'user_id'=>$ap['user_id'],'business_id'=>$bizId,
            'scope'=>$bizId?'usaha':'pribadi']);
        db()->prepare("UPDATE approvals SET status='approved',decided_by=?,decided_at=NOW(),tx_id=? WHERE id=?")
            ->execute([$u['id'],$txId,$id]);
    } else {
        db()->prepare("UPDATE approvals SET status='rejected',decided_by=?,decided_at=NOW() WHERE id=?")
            ->execute([$u['id'],$id]);
    }
    audit_log($u,'appr_decide',"#{$id} ".($ok?'approved':'rejected'),'approval',$id,['status'=>'pending'],['status'=>$ok?'approved':'rejected']);
    out(['ok'=>true]);

/* ---------- STRUK DIGITAL (share link tanpa login) ---------- */
case 'receipt_link':
    require_login();
    $id = (int)($input['id'] ?? 0);
    $st = db()->prepare("SELECT tx_no FROM transactions WHERE id=?");
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r || empty($r['tx_no'])) out(['error' => 'Struk tidak ada'], 404);
    $salt = cfg('receipt_salt', 'dompet-owner-receipt-v1');
    $token = hash('sha256', $r['tx_no'] . '|' . $id . '|' . $salt);
    out(['ok' => true, 'url' => 'receipt.php?no=' . urlencode($r['tx_no']) . '&t=' . $token]);

/* ---------- AUDIT LOG ---------- */
case 'audit_log_list':
    require_login();
    $u = require_login();
    require_role($u, ['owner']);
    $limit = min(max((int)($_GET['limit'] ?? 50), 1), 200);
    if (!empty($_GET['q'])) {
        $st = db()->prepare("SELECT * FROM audit_log WHERE action LIKE ? OR username LIKE ? OR detail LIKE ?
            ORDER BY id DESC LIMIT $limit");
        $q = '%' . $_GET['q'] . '%';
        $st->execute([$q, $q, $q]);
    } else {
        $st = db()->query("SELECT * FROM audit_log ORDER BY id DESC LIMIT $limit");
    }
    out(['rows'=>$st->fetchAll()]);

/* ---------- KOMPARASI ANTAR TAHUN ---------- */
case 'year_compare':
    require_login();
    $cur = date('Y-m');
    $prev = date('Y-m', strtotime('-1 year'));
    $get = function($m) {
        $in = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='masuk' AND DATE_FORMAT(tx_date,'%Y-%m')='$m'")->fetch()['s'];
        $out = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='keluar' AND DATE_FORMAT(tx_date,'%Y-%m')='$m'")->fetch()['s'];
        $n = (int)db()->query("SELECT COUNT(*) c FROM transactions WHERE DATE_FORMAT(tx_date,'%Y-%m')='$m'")->fetch()['c'];
        return ['bulan'=>$m,'masuk'=>$in,'keluar'=>$out,'n'=>$n];
    };
    $a=$get($cur); $b=$get($prev);
    out(['ini'=>$a,'tahun_lalu'=>$b,
         'delta_masuk_pct'=>$b['masuk']>0?round(($a['masuk']-$b['masuk'])/$b['masuk']*100):null,
         'delta_keluar_pct'=>$b['keluar']>0?round(($a['keluar']-$b['keluar'])/$b['keluar']*100):null]);

/* ---------- TUTUP BUKU BULANAN ---------- */
case 'closed_periods':
    require_login();
    out(['rows'=>db()->query("SELECT * FROM closed_periods ORDER BY period DESC")->fetchAll()]);

case 'close_book':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $period = trim($input['period'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $period)) out(['error'=>'Format periode YYYY-MM'],422);
    if ($period === date('Y-m')) out(['error'=>'Bulan berjalan belum bisa ditutup'],422);
    try {
        db()->prepare("INSERT INTO closed_periods (period,closed_by) VALUES (?,?)")->execute([$period,$u['id']]);
        audit_log($u,'close_book',$period);
        out(['ok'=>true]);
    } catch (PDOException $e) {
        out(['error'=>'Periode itu sudah dikunci'],422);
    }

case 'reopen_book':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("DELETE FROM closed_periods WHERE period=?")->execute([trim($input['period']??'')]);
    audit_log($u,'reopen_book',(string)($input['period']??''));
    out(['ok'=>true]);

/* ---------- STOK BARANG ---------- */
case 'prod_list':
    require_login();
    $biz = !empty($_GET['business_id']) ? (int)$_GET['business_id'] : null;
    if ($biz) {
        $st = db()->prepare("SELECT p.*, b.name biz FROM products p LEFT JOIN businesses b ON b.id=p.business_id
            WHERE p.active=1 AND p.business_id=? ORDER BY p.name");
        $st->execute([$biz]);
    } else {
        $st = db()->prepare("SELECT p.*, b.name biz FROM products p LEFT JOIN businesses b ON b.id=p.business_id
            WHERE p.active=1 ORDER BY p.name");
        $st->execute();
    }
    out(['rows'=>$st->fetchAll()]);

case 'prod_save':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $name=trim($input['name']??'');
    if($name==='') out(['error'=>'Nama barang wajib'],422);
    $vals=[trim($input['sku']??'') ?: null, trim($input['unit']??'pcs'),
        (float)($input['cost_price']??0),(float)($input['sell_price']??0),
        (float)($input['stock']??0),(float)($input['min_stock']??0),
        !empty($input['business_id'])?(int)$input['business_id']:null,$name];
    if(!empty($input['id'])){
        db()->prepare("UPDATE products SET sku=?,unit=?,cost_price=?,sell_price=?,stock=?,min_stock=?,business_id=? WHERE id=?")
            ->execute([...$vals, (int)$input['id']]);
        audit_log($u,'prod_update',$name);
        out(['ok'=>true,'id'=>(int)$input['id']]);
    }
    db()->prepare("INSERT INTO products (sku,unit,cost_price,sell_price,stock,min_stock,business_id,name) VALUES (?,?,?,?,?,?,?,?)")
        ->execute($vals);
    audit_log($u,'prod_add',$name);
    out(['ok'=>true,'id'=>(int)db()->lastInsertId()]);

case 'prod_delete':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("UPDATE products SET active=0 WHERE id=?")->execute([(int)($input['id']??0)]);
    audit_log($u,'prod_delete','product #'.(int)($input['id']??0));
    out(['ok'=>true]);

// stok masuk/keluar manual; keluar otomatis jadi transaksi keluar (HPP)
case 'stock_move':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $pid=(int)($input['product_id']??0);
    $qty=(float)($input['qty']??0);
    if(!$pid||$qty<=0) out(['error'=>'Data kurang'],422);
    $dir=($input['direction']??'in')==='out'?'out':'in';
    $st=db()->prepare("SELECT * FROM products WHERE id=?");$st->execute([$pid]);
    if(!$pr=$st->fetch()) out(['error'=>'Barang tidak ada'],404);
    if($dir==='out' && (float)$pr['stock'] < $qty) out(['error'=>'Stok tidak cukup ('.$pr['stock'].' '.$pr['unit'].')'],422);
    $delta=$dir==='in'?$qty:-$qty;
    db()->prepare("UPDATE products SET stock=stock+? WHERE id=?")->execute([$delta,$pid]);
    $txId=null;
    if(!empty($input['make_transaction'])){
        $amount = $dir==='in' ? $qty*(float)$pr['cost_price'] : $qty*(float)$pr['sell_price'];
        if($amount>0){
            $txId=add_transaction(['type'=>($dir==='in'?'keluar':'masuk'),'amount'=>$amount,
                'description'=>(($dir==='in'?'Beli stok: ':'Jual: ').$pr['name']." x$qty"),
                'source'=>'owner','user_id'=>$u['id'],
                'business_id'=>$pr['business_id']?(int)$pr['business_id']:null,
                'scope'=>$pr['business_id']?'usaha':'pribadi']);
            db()->prepare("UPDATE stock_moves SET tx_id=? WHERE id=?")->execute([$txId, db()->lastInsertId()]);
        }
    }
    db()->prepare("INSERT INTO stock_moves (product_id,tx_id,direction,qty,note) VALUES (?,?,?,?,?)")
        ->execute([$pid,$txId,$dir,$qty,trim($input['note']??'')]);
    audit_log($u,'stock_move',"$dir $qty {$pr['name']}");
    out(['ok'=>true]);

/* ---------- INVOICE / KWITANSI ---------- */
case 'inv_list':
    require_login();
    out(['rows'=>db()->query("SELECT i.*, b.name biz FROM invoices i LEFT JOIN businesses b ON b.id=i.business_id
        ORDER BY i.id DESC LIMIT 100")->fetchAll()]);

case 'inv_create':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $cust=trim($input['customer_name']??'');
    $amt=(float)($input['amount']??0);
    if($cust===''||$amt<=0) out(['error'=>'Nama pelanggan & nominal wajib'],422);
    $number='INV/'.date('Ymd').'/'.strtoupper(bin2hex(random_bytes(2)));
    db()->prepare("INSERT INTO invoices (business_id,number,customer_name,amount,description) VALUES (?,?,?,?,?)")
        ->execute([!empty($input['business_id'])?(int)$input['business_id']:null,
            $number,$cust,$amt,trim($input['description']??'')]);
    audit_log($u,'inv_create',$number.' '.$cust.' Rp'.$amt);
    out(['ok'=>true,'id'=>(int)db()->lastInsertId(),'number'=>$number]);

case 'inv_pay':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id=(int)($input['id']??0);
    $st=db()->prepare("SELECT * FROM invoices WHERE id=? AND status='unpaid'");$st->execute([$id]);
    if(!$inv=$st->fetch()) out(['error'=>'Invoice tidak ada / sudah lunas'],404);
    $bizId=$inv['business_id']?(int)$inv['business_id']:null;
    $txId=add_transaction(['type'=>'masuk','amount'=>(float)$inv['amount'],
        'description'=>'[INV '.$inv['number'].'] Pelunasan '.$inv['customer_name'],
        'source'=>'owner','user_id'=>$u['id'],'business_id'=>$bizId,
        'scope'=>$bizId?'usaha':'pribadi']);
    db()->prepare("UPDATE invoices SET status='paid',paid_at=NOW(),tx_id=? WHERE id=?")->execute([$txId,$id]);
    audit_log($u,'inv_pay',$inv['number']);
    notify_owner_force("PEMBAYARAN DITERIMA\n".$inv['number']." - ".$inv['customer_name']."\nRp ".number_format((float)$inv['amount'],0,',','.'));
    out(['ok'=>true]);

/* ---------- HEALTH CHECK (tanpa login, tanpa data sensitif) ---------- */
case 'health':
    try {
        db()->query("SELECT 1");
        $nTx = (int)db()->query("SELECT COUNT(*) c FROM transactions")->fetch()['c'];
        out(['ok'=>true,'db'=>'up','transactions'=>$nTx,'time'=>date('c')]);
    } catch (Throwable $e) {
        out(['ok'=>false,'db'=>'down'], 500);
    }

/* ---------- PENCARIAN GLOBAL ---------- */
case 'global_search':
    require_login();
    $q = trim((string)($input['q'] ?? $_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) out(['transactions'=>[],'categories'=>[],'businesses'=>[]]);
    $like = '%'.$q.'%';
    $st = db()->prepare("SELECT t.id,t.tx_date,t.type,t.amount,t.description,b.name biz
        FROM transactions t LEFT JOIN businesses b ON b.id=t.business_id
        WHERE t.description LIKE ? ORDER BY t.tx_date DESC LIMIT 8");
    $st->execute([$like]);
    $txs = $st->fetchAll();
    $cats = db()->prepare("SELECT id,name,color FROM categories WHERE name LIKE ? LIMIT 5");
    $cats->execute([$like]); 
    $cRows=[]; foreach ($cats->fetchAll() as $r) { $cRows[]=['id'=>$r['id'],'name'=>$r['name'],'color'=>$r['color']]; }
    $biz = db()->prepare("SELECT id,name,icon FROM businesses WHERE name LIKE ? AND active=1 LIMIT 5");
    $biz->execute([$like]);
    out(['transactions'=>$txs,'categories'=>$cRows,
         'businesses'=>$biz->fetchAll()]);

/* ---------- RINGKASAN PAJAK (grafik) ---------- */
case 'tax_summary':
    require_login();
    $months = [];
    for ($i = 5; $i >= 0; $i--) $months[] = date('Y-m', strtotime("-$i months"));
    $rows = [];
    foreach ($months as $m) {
        foreach (['pbjt','pph_umkm'] as $t) {
            $st = db()->prepare("SELECT COALESCE(SUM(l.tax_amount),0) s FROM tax_lines l
                JOIN transactions t2 ON t2.id=l.tx_id WHERE l.tax_type=? AND DATE_FORMAT(t2.tx_date,'%Y-%m')=?");
            $st->execute([$t, $m]);
            $rows[$m][$t] = (float)$st->fetch()['s'];
        }
    }
    out(['months' => $months, 'data' => $rows,
         'total_pbjt' => array_sum(array_column($rows,'pbjt')),
         'total_pph' => array_sum(array_column($rows,'pph_umkm'))]);

/* ---------- TREN 12 BULAN + PERBANDINGAN ---------- */
case 'trend_year':
    require_login();
    $months = [];
    for ($i = 11; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $in = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='masuk' AND DATE_FORMAT(tx_date,'%Y-%m')='$m'")->fetch()['s'];
        $out = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='keluar' AND DATE_FORMAT(tx_date,'%Y-%m')='$m'")->fetch()['s'];
        $months[] = ['bulan'=>$m,'masuk'=>$in,'keluar'=>$out];
    }
    $cur = $months[11]; $prev = $months[10] ?? ['bulan'=>'','masuk'=>0,'keluar'=>0];
    out(['months'=>$months,
         'compare'=>['bulan_ini'=>$cur,'bulan_lalu'=>$prev,
            'delta_masuk_pct'=>$prev['masuk']>0?round(($cur['masuk']-$prev['masuk'])/$prev['masuk']*100):null,
            'delta_keluar_pct'=>$prev['keluar']>0?round(($cur['keluar']-$prev['keluar'])/$prev['keluar']*100):null]]);

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
    if (!empty($input['source'])) { $sql .= " AND t.source=?"; $a[] = $input['source']; }
    if (!empty($input['category'])) { $sql .= " AND c.name=?"; $a[] = $input['category']; }
    if (!empty($input['min_amount'])) { $sql .= " AND t.amount>=?"; $a[] = (float)$input['min_amount']; }
    if (!empty($input['max_amount'])) { $sql .= " AND t.amount<=?"; $a[] = (float)$input['max_amount']; }
    if (!empty($input['wallet_id'])) { $sql .= " AND (t.wallet_id=? OR t.wallet_dest_id=?)"; $a[] = (int)$input['wallet_id']; $a[] = (int)$input['wallet_id']; }
    // sorting
    $sortMap = ['date'=>'t.tx_date','amount'=>'t.amount'];
    $dirMap  = ['asc'=>'ASC','desc'=>'DESC'];
    $sortCol = $sortMap[$input['sort'] ?? 'date'] ?? 't.tx_date';
    $sortDir = $dirMap[strtolower($input['dir'] ?? 'desc')] ?? 'DESC';
    // pagination
    $page = max(1, (int)($input['page'] ?? 1));
    $limit = min(max((int)($input['limit'] ?? 100), 1), 500);
    $offset = ($page - 1) * $limit;
    // total count utk info halaman (sebelum LIMIT)
    // total count utk info halaman: bangun WHERE clause terpisah
    $where = '';
    $aw = [];
    if (!empty($input['scope'])) { $where .= " AND t.scope=?"; $aw[] = $input['scope']; }
    if (!empty($input['business_id'])) { $where .= " AND t.business_id=?"; $aw[] = (int)$input['business_id']; }
    if (!empty($input['type'])) { $where .= " AND t.type=?"; $aw[] = $input['type']; }
    if (!empty($input['from'])) { $where .= " AND t.tx_date>=?"; $aw[] = $input['from']; }
    if (!empty($input['to'])) { $where .= " AND t.tx_date<=?"; $aw[] = $input['to']; }
    if (!empty($input['q'])) { $where .= " AND t.description LIKE ?"; $aw[] = '%'.$input['q'].'%'; }
    if (!empty($input['category'])) { $where .= " AND c.name=?"; $aw[] = $input['category']; }
    if (!empty($input['min_amount'])) { $where .= " AND t.amount>=?"; $aw[] = (float)$input['min_amount']; }
    if (!empty($input['max_amount'])) { $where .= " AND t.amount<=?"; $aw[] = (float)$input['max_amount']; }
    if (!empty($input['wallet_id'])) { $where .= " AND (t.wallet_id=? OR t.wallet_dest_id=?)"; $aw[] = (int)$input['wallet_id']; $aw[] = (int)$input['wallet_id']; }
    $stC = db()->prepare("SELECT COUNT(*) c FROM transactions t
        LEFT JOIN categories c ON c.id=t.category_id WHERE 1=1$where");
    $stC->execute($aw);
    $total = (int)$stC->fetch()['c'];
    $sql .= " ORDER BY $sortCol $sortDir, t.id DESC LIMIT $limit OFFSET $offset";
    $st = db()->prepare($sql); $st->execute($a);
    out(['rows' => $st->fetchAll(), 'total' => $total, 'page' => $page,
         'pages' => (int)ceil($total / $limit), 'limit' => $limit]);

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
    audit_log($u,'bill_delete','bill #'.(int)($input['id']??0).' dinonaktifkan');
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
    $role = in_array($input['role'] ?? '', ['kariawan','manajer'], true) ? $input['role'] : 'kariawan';
    db()->prepare("INSERT INTO users (username,password_hash,display_name,role,business_id) VALUES (?,?,?,?,?)")
        ->execute([$username, password_hash($pass,PASSWORD_DEFAULT),
            trim($input['display_name']?:$username), $role,
            !empty($input['business_id'])?(int)$input['business_id']:null]);
    audit_log($u,'user_add',$username.' ('.$role.')','user',(int)db()->lastInsertId(),null,['username'=>$username,'role'=>$role]);
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
        'big_tx_limit' => cfg('big_tx_limit','1000000'),
    ]);

case 'settings_set':
    $u = require_login();
    if (($u['role'] ?? '') !== 'owner') out(['error' => 'Khusus owner'], 403);
    $allowed = ['bot_token','owner_chat_id','gemini_key','notify_transactions','notify_bills','big_tx_limit'];
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
    if (!empty($input['business_id'])) { $sql .= " AND t.business_id=?"; $a[]=(int)$input['business_id']; }
    $sql .= " ORDER BY t.tx_date, t.id";
    $st = db()->prepare($sql); $st->execute($a);
    header('Content-Type: text/csv; charset=UTF-8');
    $suffix = !empty($input['business_id']) ? '-cabang'.(int)$input['business_id'] : '';
    header('Content-Disposition: attachment; filename=dompet-owner-'.date('Ymd').$suffix.'.csv');
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
    // larang hapus transaksi dari periode terkunci
    $stL = db()->prepare("SELECT COUNT(*) c FROM closed_periods WHERE period=(SELECT DATE_FORMAT(tx_date,'%Y-%m') FROM transactions WHERE id=?)");
    $stL->execute([$id]);
    if ($stL->fetch()['c']) out(['error'=>'Periode itu sudah ditutup - transaksi terkunci'],422);
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
    audit_log($u,'tx_delete',"tx #{$id} ({$t['type']} {$t['amount']}) '{$t['description']}'",
        'transaction', $id,
        ['tanggal'=>$t['tx_date'],'type'=>$t['type'],'amount'=>(float)$t['amount'],
         'description'=>$t['description'],'source'=>$t['source'],'tx_no'=>$t['tx_no'] ?? null],
        null);
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

/* ---------- PAYROLL (gaji staf) ---------- */
case 'emp_list':
    require_login();
    out(['rows' => db()->query("SELECT e.*, b.name biz FROM employees e LEFT JOIN businesses b ON b.id=e.business_id
        WHERE e.active=1 ORDER BY e.name")->fetchAll()]);

case 'emp_save':
    $u = require_login(); if ($u['role'] !== 'owner') out(['error' => 'Khusus owner'], 403);
    $name = trim($input['name'] ?? '');
    if ($name === '') out(['error' => 'Nama staf wajib diisi'], 422);
    $biz = !empty($input['business_id']) ? (int)$input['business_id'] : null;
    $st = db()->prepare("INSERT INTO employees (business_id,name,position,base_salary,pay_day) VALUES (?,?,?,?,?)");
    $st->execute([$biz, $name, trim($input['position'] ?? '') ?: null,
        (float)($input['base_salary'] ?? 0), max(1, min(28, (int)($input['pay_day'] ?? 25)))]);
    out(['ok' => true, 'id' => (int)db()->lastInsertId()]);

case 'emp_delete':
    $u = require_login(); if ($u['role'] !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("UPDATE employees SET active=0 WHERE id=?")->execute([(int)($input['id'] ?? 0)]);
    out(['ok' => true]);

case 'payroll_list':
    require_login();
    $period = date('Y-m');
    $st = db()->prepare("SELECT p.*, e.name emp, e.position pos, b.name biz FROM payrolls p
         JOIN employees e ON e.id=p.employee_id LEFT JOIN businesses b ON b.id=e.business_id
         WHERE p.period=? ORDER BY e.name");
    $st->execute([$period]);
    out(['period' => $period, 'rows' => $st->fetchAll()]);

case 'payroll_run':
    // jalankan gajian: catat pengeluaran kas + tandai lunak
    $u = require_login(); if ($u['role'] !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id = (int)($input['id'] ?? 0);
    $st = db()->prepare("SELECT p.*, e.name emp FROM payrolls p JOIN employees e ON e.id=p.employee_id WHERE p.id=?");
    $st->execute([$id]);
    if (!$p = $st->fetch()) out(['error' => 'Data gaji tidak ada'], 404);
    if ($p['status'] === 'paid') out(['error' => 'Sudah dibayar'], 422);
    $net = (float)$p['net_amount'];
    if ($net <= 0) out(['error' => 'Nominal gaji tidak valid'], 422);
    $w = wallet_default();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("INSERT INTO transactions (tx_date,type,amount,description,scope,wallet_id,source,user_id)
            VALUES (?,?,?,?,?,?,?,?)");
        $ins->execute([date('Y-m-d'), 'keluar', $net, "Gaji {$p['emp']} ({$p['period']})",
            'usaha', $w['id'], 'owner', $u['id']]);
        $txId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE wallets SET balance=balance-? WHERE id=?")->execute([$net, $w['id']]);
        $pdo->prepare("UPDATE payrolls SET status='paid', paid_at=NOW(), tx_id=? WHERE id=?")->execute([$txId, $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        out(['error' => 'Gagal proses gaji: ' . $e->getMessage()], 500);
    }
    out(['ok' => true]);

case 'payroll_generate':
    // buat draft gaji bulan ini untuk semua staf aktif yang belum ada
    $u = require_login(); if ($u['role'] !== 'owner') out(['error' => 'Khusus owner'], 403);
    $period = date('Y-m');
    $n = 0;
    foreach (db()->query("SELECT * FROM employees WHERE active=1") as $e) {
        $chk = db()->prepare("SELECT id FROM payrolls WHERE employee_id=? AND period=?");
        $chk->execute([$e['id'], $period]);
        if ($chk->fetch()) continue;
        db()->prepare("INSERT INTO payrolls (employee_id,period,base_amount,net_amount,status)
            VALUES (?,?,?,?, 'pending')")->execute([$e['id'], $period, (float)$e['base_salary'], (float)$e['base_salary']]);
        $n++;
    }
    out(['ok' => true, 'created' => $n]);

case 'payroll_adjust':
    $u = require_login(); if ($u['role'] !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id = (int)($input['id'] ?? 0);
    $bonus = max(0, (float)($input['bonus'] ?? 0));
    $ded = max(0, (float)($input['deduction'] ?? 0));
    $st = db()->prepare("SELECT base_amount,status FROM payrolls WHERE id=?"); $st->execute([$id]);
    if (!$p = $st->fetch()) out(['error' => 'Data gaji tidak ada'], 404);
    if ($p['status'] === 'paid') out(['error' => 'Sudah dibayar, tidak bisa diubah'], 422);
    db()->prepare("UPDATE payrolls SET bonus_amount=?, deduction_amount=?, net_amount=GREATEST(0,base_amount+?-?) WHERE id=?")
        ->execute([$bonus, $ded, $bonus, $ded, $id]);
    out(['ok' => true]);

/* ---------- REKAP PAJAK BULANAN ---------- */
case 'tax_monthly':
    require_login();
    $rows = db()->query("SELECT * FROM tax_monthly ORDER BY period DESC, tax_type LIMIT 36")->fetchAll();
    out(['rows' => $rows]);

case 'tax_monthly_sync':
    // tarik total dari tax_lines (hasil tax engine transaksi) ke rekap bulan berjalan
    $u = require_login(); if ($u['role'] !== 'owner') out(['error' => 'Khusus owner'], 403);
    $period = date('Y-m');
    $n = 0;
    $stT = db()->query("SELECT tl.tax_type, SUM(tl.tax_amount) total
        FROM tax_lines tl JOIN transactions t ON t.id=tl.tx_id
        WHERE DATE_FORMAT(t.tx_date,'%Y-%m')='$period'
        GROUP BY tl.tax_type");
    foreach ($stT as $r) {
        if ((float)$r['total'] <= 0) continue;
        db()->prepare("INSERT INTO tax_monthly (period,tax_type,amount) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE amount=VALUES(amount)")
            ->execute([$period, $r['tax_type'], (float)$r['total']]);
        $n++;
    }
    out(['ok' => true, 'synced' => $n, 'period' => $period]);

case 'tax_monthly_set':
    $u = require_login(); if ($u['role'] !== 'owner') out(['error' => 'Khusus owner'], 403);
    $period = preg_replace('/[^0-9-]/', '', (string)($input['period'] ?? date('Y-m')));
    $type = in_array($input['tax_type'] ?? '', ['pbjt','pph_umkm','pph21','non_pajak'], true) ? $input['tax_type'] : 'non_pajak';
    $due = !empty($input['due_date']) ? substr(preg_replace('/[^0-9-]/', '', (string)$input['due_date']), 0, 10) : null;
    db()->prepare("INSERT INTO tax_monthly (period,tax_type,amount,due_date) VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE amount=VALUES(amount), due_date=VALUES(due_date)")
        ->execute([$period, $type, (float)($input['amount'] ?? 0), $due]);
    out(['ok' => true]);

case 'tax_monthly_pay':
    // bayar pajak: keluar kas + tandai lunas
    $u = require_login(); if ($u['role'] !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id = (int)($input['id'] ?? 0);
    $st = db()->prepare("SELECT * FROM tax_monthly WHERE id=?"); $st->execute([$id]);
    if (!$t = $st->fetch()) out(['error' => 'Data pajak tidak ada'], 404);
    if ($t['status'] === 'paid') out(['error' => 'Pajak sudah dibayar'], 422);
    $amt = (float)$t['amount'];
    if ($amt <= 0) out(['error' => 'Nominal pajak 0 — tidak perlu dibayar'], 422);
    $w = wallet_default();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("INSERT INTO transactions (tx_date,type,amount,description,scope,wallet_id,source,user_id)
            VALUES (?,?,?,?,?,?,?,?)");
        $ins->execute([date('Y-m-d'), 'keluar', $amt, "Bayar pajak {$t['tax_type']} ({$t['period']})",
            'usaha', $w['id'], 'owner', $u['id']]);
        $txId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE wallets SET balance=balance-? WHERE id=?")->execute([$amt, $w['id']]);
        $pdo->prepare("UPDATE tax_monthly SET status='paid', note=CONCAT_WS(' | ', note, ?) WHERE id=?")
            ->execute(['dibayar tx#'.$txId, $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        out(['error' => 'Gagal bayar pajak: ' . $e->getMessage()], 500);
    }
    out(['ok' => true]);

case 'payslip_link':
    $u = require_login(); if ($u['role'] !== 'owner') out(['error' => 'Khusus owner'], 403);
    $id = (int)($input['id'] ?? 0);
    $st = db()->prepare("SELECT p.*, e.name emp FROM payrolls p JOIN employees e ON e.id=p.employee_id WHERE p.id=?");
    $st->execute([$id]);
    if (!$p = $st->fetch()) out(['error' => 'Slip tidak ada'], 404);
    if ($p['status'] !== 'paid') out(['error' => 'Belum dibayar — bayar dulu sebelum bagikan slip'], 422);
    $salt = cfg('receipt_salt', 'dompet-owner-receipt-v1');
    $tok = hash('sha256', 'payslip|' . $p['id'] . '|' . $p['period'] . '|' . $salt);
    out(['ok' => true, 'url' => 'payslip.php?id=' . $p['id'] . '&t=' . $tok, 'emp' => $p['emp']]);

/* ---------- UMUR PIUTANG (aging) ---------- */
case 'recv_aging':
    require_login();
    $rows = db()->query("SELECT r.*, b.name biz FROM receivables r
        LEFT JOIN businesses b ON b.id=r.business_id WHERE r.status<>'paid'")->fetchAll();
    $today = new DateTimeImmutable();
    $buckets = ['belum_tempo'=>0,'1_30'=>0,'31_60'=>0,'61_90'=>0,'90plus'=>0];
    foreach ($rows as &$r) {
        $sisa = (float)$r['amount'] - (float)$r['paid_amount'];
        $r['sisa'] = $sisa;
        $r['days_late'] = null;
        $buk = 'belum_tempo';
        if (!empty($r['due_date'])) {
            $due = new DateTimeImmutable($r['due_date']);
            if ($due < $today) {
                $days = (int)$today->diff($due)->days;
                $r['days_late'] = $days;
                $buk = $days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '90plus'));
            }
        }
        $buckets[$buk] += $sisa;
    }
    unset($r);
    usort($rows, fn($a, $b2) => ($b2['days_late'] ?? -1) <=> ($a['days_late'] ?? -1));
    out(['rows' => $rows, 'buckets' => $buckets]);

/* ---------- ASET TETAP & PENYUSUTAN ---------- */
case 'assets':
    require_login();
    $rows = db()->query("SELECT a.*, b.name biz FROM fixed_assets a LEFT JOIN businesses b ON b.id=a.business_id
        WHERE a.active=1 ORDER BY a.id")->fetchAll();
    foreach ($rows as &$r) {
        // total penyusutan tercatat + nilai buku sekarang
        $d = db()->prepare("SELECT COALESCE(SUM(amount),0) s FROM asset_deps WHERE asset_id=?");
        $d->execute([$r['id']]);
        $dep = (float)$d->fetch()['s'];
        $r['dep_total'] = $dep;
        $r['book_value'] = max((float)$r['salvage'], (float)$r['cost'] - $dep);
        $r['monthly'] = ((float)$r['cost'] - (float)$r['salvage']) / max(1, (int)$r['life_months']);
        // periode berjalan sudah disusutkan?
        $c = db()->prepare("SELECT COUNT(*) c FROM asset_deps WHERE asset_id=? AND period=?");
        $c->execute([$r['id'], date('Y-m')]);
        $r['dep_this_month'] = (bool)$c->fetch()['c'];
    }
    unset($r);
    out(['rows' => $rows]);

case 'asset_add':
    $u = require_login(); if ($u['role'] !== 'owner') out(['error' => 'Khusus owner'], 403);
    $name = trim($input['name'] ?? '');
    if ($name === '' || (float)($input['cost'] ?? 0) <= 0) out(['error' => 'Nama & harga beli wajib'], 422);
    db()->prepare("INSERT INTO fixed_assets (business_id,name,acquired_at,cost,salvage,life_months)
        VALUES (?,?,?,?,?,?)")->execute([
        !empty($input['business_id']) ? (int)$input['business_id'] : null,
        $name,
        substr(preg_replace('/[^0-9-]/','',(string)($input['acquired_at'] ?? date('Y-m-d'))),0,10),
        (float)$input['cost'],
        (float)($input['salvage'] ?? 0),
        max(1, (int)($input['life_months'] ?? 48))]);
    out(['ok' => true, 'id' => (int)db()->lastInsertId()]);

case 'asset_delete':
    $u = require_login(); if ($u['role'] !== 'owner') out(['error' => 'Khusus owner'], 403);
    db()->prepare("UPDATE fixed_assets SET active=0 WHERE id=?")->execute([(int)($input['id'] ?? 0)]);
    out(['ok' => true]);

case 'asset_dep_run':
    // jalankan penyusutan bulan berjalan utk semua aset yang belum
    $u = require_login(); if ($u['role'] !== 'owner') out(['error' => 'Khusus owner'], 403);
    require_once __DIR__ . '/src/ledger.php';
    $period = date('Y-m');
    $done = 0; $total = 0.0;
    foreach (db()->query("SELECT * FROM fixed_assets WHERE active=1") as $a) {
        $chk = db()->prepare("SELECT COUNT(*) c FROM asset_deps WHERE asset_id=? AND period=?");
        $chk->execute([$a['id'], $period]);
        if ($chk->fetch()['c']) continue;
        // jangan susut melebihi nilai sisa
        $d = db()->prepare("SELECT COALESCE(SUM(amount),0) s FROM asset_deps WHERE asset_id=?");
        $d->execute([$a['id']]);
        $book = (float)$a['cost'] - (float)$d->fetch()['s'];
        $amt = min($book - (float)$a['salvage'], ((float)$a['cost'] - (float)$a['salvage']) / max(1,(int)$a['life_months']));
        if ($amt <= 0.005) continue;
        $amt = round($amt, 2);
        $eid = post_journal(date('Y-m-d'), "Penyusutan {$a['name']} ($period)", [
            ['account'=>$a['dep_account'],'debit'=>$amt],
            ['account'=>$a['asset_account'],'credit'=>$amt]], null);
        db()->prepare("INSERT INTO asset_deps (asset_id,period,amount,entry_id) VALUES (?,?,?,?)")
            ->execute([$a['id'], $period, $amt, $eid]);
        $done++; $total += $amt;
    }
    out(['ok' => true, 'run' => $done, 'total' => $total, 'period' => $period]);

/* ---------- IMPORT CSV MUTASI BANK ---------- */
case 'import_csv':
    $u = require_login(); if ($u['role'] !== 'owner') out(['error' => 'Khusus owner'], 403);
    if (empty($_FILES['file']['tmp_name'])) out(['error' => 'File CSV tidak terkirim'], 422);
    $handle = fopen($_FILES['file']['tmp_name'], 'r');
    if (!$handle) out(['error' => 'File tidak bisa dibaca'], 422);
    $walletId = (int)($_POST['wallet_id'] ?? 0);
    $st = db()->prepare("SELECT * FROM wallets WHERE id=?"); $st->execute([$walletId]);
    if (!$w = $st->fetch()) out(['error' => 'Pilih rekening tujuan dulu'], 422);
    $scope = ($_POST['scope'] ?? 'usaha') === 'pribadi' ? 'pribadi' : 'usaha';
    $biz = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
    // auto-kategori: pakai smart rules kalau ada kecocokan
    $rules = [];
    foreach (db()->query("SELECT pattern, category_id FROM smart_rules") as $rl) $rules[strtolower($rl['pattern'])] = (int)$rl['category_id'];
    $catStmt = db()->prepare("SELECT name FROM categories WHERE id=?");
    $rowNum = 0; $okRows = 0; $skipRows = 0;
    $pdo = db();
    while (($csv = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $rowNum++;
        if ($rowNum === 1 && stripos(implode(',', $csv), 'tanggal') !== false) continue; // header
        // format fleksibel: tanggal;keterangan;jumlah ATAU tanggal;keterangan;masuk;keluar
        if (count($csv) < 3) { $skipRows++; continue; }
        $tgl = trim($csv[0]); $ket = trim($csv[1]);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
            $ts = strtotime(str_replace('/', '-', $tgl));
            if (!$ts) { $skipRows++; continue; }
            $tgl = date('Y-m-d', $ts);
        }
        if ($ket === '') { $skipRows++; continue; }
        $amount = 0.0; $type = '';
        if (count($csv) >= 4) {
            $in = (float)str_replace([',','.00'], ['','.'], preg_replace('/[^\d,.]/','',trim($csv[2]) ?: '0'));
            $out = (float)str_replace([',','.00'], ['','.'], preg_replace('/[^\d,.]/','',trim($csv[3]) ?: '0'));
            if ($in > 0)      { $amount = $in;  $type = 'masuk'; }
            elseif ($out > 0) { $amount = $out; $type = 'keluar'; }
        } else {
            $raw = str_replace(['Rp',' '], '', trim($csv[2]));
            $neg = str_starts_with($raw, '-') || str_ends_with($raw, '-');
            $amount = (float)str_replace([',','.00'], ['','.'], preg_replace('/[^\d,.]/','',$raw));
            $type = $neg ? 'keluar' : 'masuk';
        }
        if ($amount <= 0 || !in_array($type, ['masuk','keluar'], true)) { $skipRows++; continue; }
        // tutup buku check
        $lockP = substr($tgl, 0, 7);
        $lk = db()->prepare("SELECT COUNT(*) c FROM closed_periods WHERE period=?"); $lk->execute([$lockP]);
        if ($lk->fetch()['c']) { $skipRows++; continue; }
        // kategori otomatis dari smart rules
        $cid = null;
        foreach ($rules as $pat => $rid) {
            if (stripos($ket, $pat) !== false) { $cid = $rid; break; }
        }
        $dup = db()->prepare("SELECT COUNT(*) c FROM transactions WHERE tx_date=? AND amount=? AND description=? AND wallet_id=?");
        $dup->execute([$tgl, $amount, $ket, $walletId]);
        if ($dup->fetch()['c']) { $skipRows++; continue; } // anti dobel
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("INSERT INTO transactions (tx_date,type,amount,description,category_id,scope,business_id,wallet_id,source,user_id)
                VALUES (?,?,?,?,?,?,?,?, 'import', ?)");
            $ins->execute([$tgl, $type, $amount, $ket, $cid, $scope, $biz, $walletId, $u['id']]);
            $sign = $type === 'masuk' ? 1 : -1;
            $pdo->prepare("UPDATE wallets SET balance=balance+? WHERE id=?")->execute([$sign * $amount, $walletId]);
            $pdo->commit();
            $okRows++;
        } catch (Throwable $e) {
            $pdo->rollBack(); $skipRows++;
        }
    }
    fclose($handle);
    db()->prepare("INSERT INTO import_logs (filename,total_rows,ok_rows,skip_rows,created_by) VALUES (?,?,?,?,?)")
        ->execute([substr((string)($_FILES['file']['name'] ?? ''), 0, 160), $okRows + $skipRows, $okRows, $skipRows, $u['id']]);
    out(['ok' => true, 'imported' => $okRows, 'skipped' => $skipRows]);

default:
    out(['error' => 'Action tidak dikenal'], 400);
}

} catch (InvalidArgumentException $e) {
    out(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    out(['error' => 'Server error: ' . $e->getMessage()], 500);
}
