<?php
// Bot Telegram Dompet Owner v3 — SERBA TOMBOL (menu ikon inline), AI Gemini terintegrasi.
// Tidak perlu hafal perintah /; semua lewat menu & tombol. Perintah teks lama tetap jalan.
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/ai.php';

const PAGE = 5;

/* ---------- Sesi chat <-> user ---------- */
function find_user_by_chat(string $chatId): ?array {
    $chatId = (string)$chatId;
    $st2 = db()->prepare("SELECT v FROM app_settings WHERE k=?");
    $st2->execute(["chat:$chatId"]);
    if ($row = $st2->fetch()) {
        $st = db()->prepare("SELECT * FROM users WHERE id=? AND active=1");
        $st->execute([$row['v']]);
        return $st->fetch() ?: null;
    }
    return null;
}
function bind_chat(string $chatId, int $userId): void {
    db()->prepare("INSERT INTO app_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)")
        ->execute(["chat:$chatId", (string)$userId]);
}

/* ---------- Onboarding via kode login ---------- */
function try_code_login(string $chatId, string $text): ?string {
    if (!preg_match('/^\/mulai\s+([A-Z0-9]{6})$/i', trim($text), $m)) return null;
    $code = strtoupper($m[1]);
    $uid = cfg("login_code:$code");
    if ($uid === '') return "Kode tidak valid / sudah kedaluwarsa. Minta kode baru ke bos.";
    db()->prepare("DELETE FROM app_settings WHERE k=?")->execute(["login_code:$code"]);
    bind_chat($chatId, (int)$uid);
    $st = db()->prepare("SELECT * FROM users WHERE id=?"); $st->execute([$uid]);
    $u = $st->fetch();
    db()->prepare("UPDATE users SET telegram_verified=1 WHERE id=?")->execute([$uid]);
    return "Halo {$u['display_name']}! 🎉 Login berhasil.\n\n" . main_menu_text($u);
}
function help_text(array $user): string { return main_menu_text($user); }

/* ---------- Keyboard utama ---------- */
function main_menu_kb(array $user): array {
    $kb = [];
    if ($user['role'] === 'owner') {
        $kb[] = [['text'=>'💰 Kas','callback_data'=>'m:kas'],['text'=>'📊 Laporan','callback_data'=>'m:laporan']];
        $kb[] = [['text'=>'❤️ Kesehatan','callback_data'=>'m:health'],['text'=>'🧾 Transaksi','callback_data'=>'m:txlist']];
        $kb[] = [['text'=>'📋 Piutang','callback_data'=>'m:piutang'],['text'=>'🏛️ Pajak','callback_data'=>'m:pajak']];
        $kb[] = [['text'=>'👥 Gaji','callback_data'=>'m:payroll'],['text'=>'🏭 Aset','callback_data'=>'m:aset']];
        $kb[] = [['text'=>'🪙 Kas Kecil','callback_data'=>'m:petty'],['text'=>'🏬 Cabang','callback_data'=>'m:cabang']];
        $kb[] = [['text'=>'🔔 Persetujuan','callback_data'=>'m:appr'],['text'=>'🛡️ Audit','callback_data'=>'m:audit']];
    } else {
        $kb[] = [['text'=>'📊 Hariku','callback_data'=>'m:hariku'],['text'=>'📈 Rekap 7 hari','callback_data'=>'m:rekap']];
        $kb[] = [['text'=>'🧾 Catat Transaksi','callback_data'=>'tx:start'],['text'=>'🏬 Cabangku','callback_data'=>'m:cabangme']];
    }
    $kb[] = [['text'=>'🤖 Tanya AI','callback_data'=>'ai:start'],['text'=>'🔄 Menu Awal','callback_data'=>'m:home']];
    return ['inline_keyboard'=>$kb];
}
function back_kb(string $to='home'): array {
    return ['inline_keyboard'=>[[['text'=>'⬅️ Menu Utama','callback_data'=>'m:home']]]];
}
function main_menu_text(array $user): string {
    if ($user['role'] === 'owner')
        return "👑 *Dompet Owner* — halo {$user['display_name']}!\nPilih menu di bawah 👇 Semua bisa tanpa perintah.";
    return "👋 *Dompet Owner* — halo {$user['display_name']}!\nCatat transaksi, lihat laporan cabangmu, atau tanya AI 👇";
}
function ai_prompt_kb(): array {
    return ['inline_keyboard'=>[
        [['text'=>'💵 Berapa kas sekarang?','callback_data'=>'aiq:kas']],
        [['text'=>'📉 Keuangan bulan ini gimana?','callback_data'=>'aiq:bulan']],
        [['text'=>'💡 Saran buat bisnis saya?','callback_data'=>'aiq:saran']],
        [['text'=>'🔮 Proyeksi kas ke depan?','callback_data'=>'aiq:proyeksi']],
        [['text'=>'✍️ Tulis pertanyaanku sendiri','callback_data'=>'ai:free']],
        [['text'=>'⬅️ Menu Utama','callback_data'=>'m:home']],
    ]];
}
function tx_amount_kb(): array {
    // nominal cepat
    $row1=[]; foreach ([10000,25000,50000,100000] as $v) $row1[]=['text'=>number_format($v,0,',','.'),'callback_data'=>"txa:$v"];
    $row2=[]; foreach ([200000,500000,1000000] as $v) $row2[]=['text'=>number_format($v,0,',','.'),'callback_data'=>"txa:$v"];
    return ['inline_keyboard'=>[$row1,$row2,
        [['text'=>'⬅️ Menu Utama','callback_data'=>'m:home']]]];
}

function set_bot_commands(): void {
    tg('setMyCommands', ['commands' => json_encode([
        ['command'=>'start','description'=>'🏠 Buka menu utama'],
        ['command'=>'bantuan','description'=>'📖 Bantuan & daftar tombol'],
    ])]);
}

/* ---------- Laporan (dipakai tombol) ---------- */
function laporan_hari_ini(array $user): string {
    $b = (int)$user['business_id'];
    $st = db()->prepare("SELECT type, COALESCE(SUM(amount),0) t FROM transactions
        WHERE tx_date=CURDATE() AND business_id=? GROUP BY type");
    $st->execute([$b]);
    $d = ['masuk'=>0,'keluar'=>0];
    foreach ($st as $r) $d[$r['type']] = (float)$r['t'];
    $biz = db()->query("SELECT name FROM businesses WHERE id=$b")->fetch()['name'] ?? 'Cabang';
    return "📊 *Laporan hari ini — {$biz}*\nMasuk : Rp ".number_format($d['masuk'],0,'.','.')
        ."\nKeluar: Rp ".number_format($d['keluar'],0,'.','.')
        ."\nSelisih: Rp ".number_format($d['masuk']-$d['keluar'],0,'.','.');
}
function rekap_7_hari(array $user): string {
    $b = (int)$user['business_id'];
    $st = db()->prepare("SELECT type, COALESCE(SUM(amount),0) t FROM transactions
        WHERE tx_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND business_id=? GROUP BY type");
    $st->execute([$b]);
    $d = ['masuk'=>0,'keluar'=>0];
    foreach ($st as $r) $d[$r['type']] = (float)$r['t'];
    $biz = db()->query("SELECT name FROM businesses WHERE id=$b")->fetch()['name'] ?? 'Cabang';
    return "📈 *Rekap 7 hari — {$biz}*\nMasuk : Rp ".number_format($d['masuk'],0,'.','.')
        ."\nKeluar: Rp ".number_format($d['keluar'],0,'.','.')
        ."\nLaba  : Rp ".number_format($d['masuk']-$d['keluar'],0,'.','.');
}
function saldo_kas(): string {
    $out = "💼 *Saldo Kas Dompet*\n";
    foreach (wallets_all() as $w)
        $out .= "\n".($w['is_default']?'⭐':'•')." {$w['name']}: Rp ".number_format((float)$w['balance'],0,',','.');
    $out .= "\n\nTOTAL: Rp ".number_format(total_kas(),0,',','.');
    return $out;
}
function piutang_text(): string {
    $rows = db()->query("SELECT r.*, b.name biz FROM receivables r LEFT JOIN businesses b ON b.id=r.business_id
        WHERE r.status<>'paid' ORDER BY r.due_date IS NULL, r.due_date LIMIT 10")->fetchAll();
    if (!$rows) return "📋 Tidak ada piutang aktif. Semua lunas! 🎉";
    $today = date('Y-m-d');
    $out = "📋 *Piutang Aktif*\n";
    foreach ($rows as $r) {
        $sisa = (float)$r['amount'] - (float)$r['paid_amount'];
        $late = ($r['due_date'] && $r['due_date'] < $today) ? ' ⚠️TELAT' : '';
        $out .= "\n• {$r['debtor_name']}{$late}\n  sisa Rp ".number_format($sisa,0,',','.').($r['due_date']?" (tempo {$r['due_date']})":'');
    }
    return $out;
}
function pajak_text(): string {
    $rows = db()->query("SELECT * FROM tax_monthly ORDER BY period DESC, tax_type LIMIT 8")->fetchAll();
    if (!$rows) return "🏛️ Belum ada rekap pajak. Sinkron dari dashboard ya.";
    $L = ['pbjt'=>'PBJT','pph_umkm'=>'PPh UMKM','pph21'=>'PPh 21','non_pajak'=>'Lainnya'];
    $today = date('Y-m-d');
    $out = "🏛️ *Rekap Pajak*\n";
    foreach ($rows as $r) {
        $st = $r['status']==='paid' ? '✅' : (($r['due_date'] && $r['due_date'] < $today) ? '⚠️ TELAT' : '⏳');
        $lbl = $L[$r['tax_type']] ?? $r['tax_type'];
        $out .= "\n{$st} {$lbl} {$r['period']} — Rp ".number_format((float)$r['amount'],0,',','.');
    }
    return $out;
}
function payroll_text(): string {
    $period = date('Y-m');
    $st = db()->prepare("SELECT p.*, e.name emp FROM payrolls p JOIN employees e ON e.id=p.employee_id
        WHERE p.period=? ORDER BY e.name"); $st->execute([$period]);
    $rows = $st->fetchAll();
    if (!$rows) return "👥 Belum ada gajian bulan ini. Jalankan dari dashboard → Gaji Staf.";
    $out = "👥 *Gajian $period*\n";
    $tot = 0;
    foreach ($rows as $r) {
        $tot += (float)$r['net_amount'];
        $out .= "\n".($r['status']==='paid'?'✅':'⏳')." {$r['emp']}: Rp ".number_format((float)$r['net_amount'],0,',','.');
    }
    $out .= "\n\nTotal: Rp ".number_format($tot,0,',','.');
    return $out;
}
function aset_text(): string {
    $rows = db()->query("SELECT a.*, COALESCE((SELECT SUM(amount) FROM asset_deps d WHERE d.asset_id=a.id),0) dep
        FROM fixed_assets a WHERE a.active=1")->fetchAll();
    if (!$rows) return "🏭 Belum ada aset tercatat.";
    $out = "🏭 *Aset & Nilai Buku*\n";
    foreach ($rows as $r)
        $out .= "\n• {$r['name']}: Rp ".number_format(max((float)$r['salvage'],(float)$r['cost']-(float)$r['dep']),0,',','.')
            ." (tersusut Rp ".number_format((float)$r['dep'],0,',','.').")";
    return $out;
}
function petty_text(): string {
    $rows = db()->query("SELECT pc.*, b.name biz FROM petty_cash pc LEFT JOIN businesses b ON b.id=pc.business_id
        WHERE pc.active=1")->fetchAll();
    if (!$rows) return "🪙 Belum ada kas kecil. Buat dari dashboard ya.";
    $out = "🪙 *Kas Kecil*\n";
    foreach ($rows as $r)
        $out .= "\n• {$r['name']}".($r['custodian']?" ({$r['custodian']})":'').": Rp ".number_format((float)$r['fund'],0,',','.');
    return $out;
}
function appr_text(int $userId): string {
    $st = db()->prepare("SELECT a.*, u.display_name requester FROM approvals a LEFT JOIN users u ON u.id=a.user_id
        WHERE a.status='pending' ORDER BY a.created_at DESC LIMIT 10");
    $st->execute();
    $rows = $st->fetchAll();
    if (!$rows) return "🔔 Tidak ada permintaan persetujuan menunggu. ✨";
    return "🔔 *Menunggu Persetujuan*\n" . implode("\n", array_map(fn($r) =>
        "\n• #{$r['id']} {$r['requester']}: Rp ".number_format((float)$r['amount'],0,',','.')."\n  {$r['description']}", $rows))
        . "\n\nPutuskan lewat tombol di notifikasi Telegram, atau dashboard → Persetujuan.";
}
function audit_text(): string {
    $rows = db()->query("SELECT action, detail, created_at FROM audit_log ORDER BY id DESC LIMIT 10")->fetchAll();
    if (!$rows) return "🛡️ Audit log kosong.";
    $out = "🛡️ *Audit Terakhir*\n";
    foreach ($rows as $r)
        $out .= "\n• ".substr((string)$r['created_at'],5,11)." {$r['action']}: ".mb_substr((string)$r['detail'],0,60);
    return $out;
}
function health_text(): string {
    $period = date('Y-m');
    $in  = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='masuk' AND DATE_FORMAT(tx_date,'%Y-%m')='$period'")->fetch()['s'];
    $out = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='keluar' AND DATE_FORMAT(tx_date,'%Y-%m')='$period'")->fetch()['s'];
    $kas = total_kas();
    $liab = (float)db()->query("SELECT COALESCE(SUM(outstanding),0) s FROM liabilities WHERE active=1")->fetch()['s'];
    $overdueRecv = (int)db()->query("SELECT COUNT(*) c FROM receivables WHERE status<>'paid' AND due_date IS NOT NULL AND due_date < CURDATE()")->fetch()['c'];
    $unpaidTax = (int)db()->query("SELECT COUNT(*) c FROM tax_monthly WHERE status='unpaid' AND due_date IS NOT NULL AND due_date < CURDATE()")->fetch()['c'];
    $saveRatio = $in > 0 ? ($in - $out) / $in : null;
    $runway = $out > 0 ? $kas / $out : null;
    $score = 0;
    $score += ($saveRatio === null) ? 15 : (($saveRatio >= .2) ? 30 : (($saveRatio >= .1) ? 20 : 8));
    $score += ($runway === null || $runway >= 3) ? 25 : (($runway >= 1) ? 15 : 5);
    $score += ($liab <= 0) ? 20 : (($in > 0 && $liab <= $in*2) ? 12 : 4);
    $score += ($overdueRecv === 0) ? 15 : max(3, 15 - $overdueRecv * 4);
    $score += max(0, 10 - $unpaidTax * 5);
    $label = $score >= 80 ? 'Sehat 💪' : ($score >= 60 ? 'Cukup 🙂' : ($score >= 40 ? 'Waspada 😐' : 'Butuh Perhatian ⚠️'));
    $tips = [];
    if ($runway !== null && $runway < 3) $tips[] = 'Siapkan kas darurat 3x pengeluaran bulanan.';
    if ($overdueRecv > 0) $tips[] = "$overdueRecv piutang lewat tempo — saatnya ditagih.";
    if ($unpaidTax > 0) $tips[] = "$unpaidTax pajak telat bayar — hindari denda.";
    return "❤️ *Skor Kesehatan: $score/100 — $label*\n"
        . "\nMasuk: Rp ".number_format($in,0,',','.')
        . "\nKeluar: Rp ".number_format($out,0,',','.')
        . "\nKas: Rp ".number_format($kas,0,',','.')
        . ($tips ? "\n\n💡 ".implode("\n💡 ", $tips) : '');
}
function tx_recent_text(): string {
    $rows = db()->query("SELECT tx_date,type,amount,description FROM transactions ORDER BY id DESC LIMIT 8")->fetchAll();
    if (!$rows) return "🧾 Belum ada transaksi.";
    $out = "🧾 *Transaksi Terakhir*\n";
    foreach ($rows as $r)
        $out .= "\n".($r['type']==='masuk'?'🟢':'🔴')." Rp ".number_format((float)$r['amount'],0,',','.')
            ." — ".mb_substr((string)$r['description'],0,40);
    return $out;
}
function cabang_list_text(): string {
    $out = "🏬 *Daftar Cabang*\n";
    foreach (db()->query("SELECT id,name FROM businesses WHERE active=1 ORDER BY id") as $b)
        $out .= "\n• {$b['name']}";
    return $out;
}

/* ---------- Alur catat transaksi pakai tombol ---------- */
function bot_session_get(string $chatId): array {
    $v = cfg("botflow:$chatId");
    return $v !== '' ? (json_decode($v, true) ?: []) : [];
}
function bot_session_set(string $chatId, array $data): void {
    db()->prepare("INSERT INTO app_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)")
        ->execute(["botflow:$chatId", json_encode($data)]);
}
function bot_session_clear(string $chatId): void {
    db()->prepare("DELETE FROM app_settings WHERE k=?")->execute(["botflow:$chatId"]);
}

/* ---------- AI ---------- */
function ai_quick_answer(string $key): ?string {
    switch ($key) {
        case 'kas': return saldo_kas() . "\n\n🤖 Itu data persis dari sistemmu. Mau analisis lebih dalam? Tekan 🤖 Tanya AI lagi.";
        case 'bulan': return laporan_bulan_owner() . "\n\n🤖 Mau saran konkret? Coba tombol 'Saran buat bisnis saya'.";
        default: return null;
    }
}
function handle_ai_question(array $user, string $q): string {
    if ($user['role'] !== 'owner') {
        // kariawan: konteks dibatasi cabangnya
        $q = "[Kamu membantu kariawan cabang bernama {$user['display_name']}. Jangan ungkap data owner/pribadi.] " . $q;
    }
    return ai_answer($q);
}

/* ---------- FOTO STRUK: AI baca -> tombol konfirmasi ---------- */
function handle_photo(array $msg): void {
    $chatId = (string)$msg['chat']['id'];
    $user = find_user_by_chat($chatId);
    if (!$user) {
        tg('sendMessage', ['chat_id'=>$chatId,'text'=>"👋 Login dulu ya: /mulai KODE123"]);
        return;
    }
    tg('sendChatAction', ['chat_id'=>$chatId, 'action'=>'typing']);
    $photos = $msg['photo'] ?? [];
    if (!$photos) return;
    $best = end($photos); // resolusi terbesar
    $token = bot_token();
    // ambil path file dari Telegram
    $ch = curl_init("https://api.telegram.org/bot{$token}/getFile?file_id=" . urlencode($best['file_id']));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $tgPath = $res['result']['file_path'] ?? null;
    if (!$tgPath) {
        tg('sendMessage', ['chat_id'=>$chatId,'text'=>"😅 Gagal mengambil fotonya. Coba kirim ulang."]);
        return;
    }
    $dir = __DIR__ . '/../public/uploads';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $local = $dir . '/tg_' . bin2hex(random_bytes(6)) . '.jpg';
    copy("https://api.telegram.org/file/bot{$token}/{$tgPath}", $local);
    if (!is_file($local)) {
        tg('sendMessage', ['chat_id'=>$chatId,'text'=>"😅 Gagal mengunduh foto. Coba lagi ya."]);
        return;
    }
    tg('sendChatAction', ['chat_id'=>$chatId, 'action'=>'typing']);
    $d = ai_read_receipt($local);
    if (!$d || (float)$d['total'] <= 0) {
        unlink($local);
        // fallback: biarkan AI menjelaskan foto sebagai pertanyaan
        tg('sendMessage', ['chat_id'=>$chatId,
            'text'=>"🤔 Aku belum bisa membaca total di foto itu.\nCoba ketik manual: `keluar 25000 " . mb_substr($d['merchant'] ?: 'belanja', 0, 40) . "`",
            'parse_mode'=>'Markdown']);
        return;
    }
    // simpan draft di sesi bot
    bot_session_set($chatId, ['step'=>'rcpt','amount'=>$d['total'],'desc'=>$d['merchant'],'file'=>basename($local)]);
    $items = implode("\n", array_map(fn($i)=>"  • ".mb_substr((string)$i,0,40), array_slice($d['items'] ?? [], 0, 5)));
    tg('sendMessage', [
        'chat_id'=>$chatId,
        'text'=>"🧾 *Struk terbaca!*\n\n🏪 {$d['merchant']}\n💰 Total: Rp ".number_format($d['total'],0,',','.')
            .($d['tanggal'] ? "\n📅 {$d['tanggal']}" : '')
            .($items ? "\n\nRincian:\n$items" : '')
            ."\n\nCatat sebagai apa?",
        'parse_mode'=>'Markdown',
        'reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>'🟢 Uang Masuk','callback_data'=>'rcpt:masuk'],['text'=>'🔴 Uang Keluar','callback_data'=>'rcpt:keluar']],
            [['text'=>'❌ Batal','callback_data'=>'tx:cancel']],
        ]]),
    ]);
}
function rcpt_confirm(array $user, string $chatId, string $type): void {
    $f = bot_session_get($chatId);
    $amt = (float)($f['amount'] ?? 0);
    if ($amt <= 0) { tg('sendMessage',['chat_id'=>$chatId,'text'=>"Draft struk sudah tidak ada — kirim fotonya lagi ya."]); return; }
    $desc = ($f['desc'] ?: 'Belanja') . ' [struk]';
    $bizId = $user['business_id'] ? (int)$user['business_id'] : null;
    add_transaction(['type'=>$type,'amount'=>$amt,'description'=>"$desc (via {$user['display_name']})",
        'source'=>'bot_kariawan','user_id'=>$user['id'],'business_id'=>$bizId]);
    $txId = (int)db()->lastInsertId();
    // lampirkan foto struk ke transaksi
    if (!empty($f['file']) && is_file(__DIR__ . '/../public/uploads/' . $f['file'])) {
        db()->prepare("INSERT INTO tx_attachments (tx_id,filename,original_name) VALUES (?,?,?)")
            ->execute([$txId, $f['file'], 'struk-telegram.jpg']);
    } else {
        bot_session_clear($chatId);
    }
    bot_session_clear($chatId);
    $emoji = $type==='masuk'?'🟢 MASUK':'🔴 KELUAR';
    notify_owner("{$emoji} [struk]\nRp ".number_format($amt,0,',','.')."\n{$desc}\nOleh: {$user['display_name']} (via foto Telegram)");
    tg('sendMessage', ['chat_id'=>$chatId,
        'text'=>"✅ Tercatat! {$emoji} Rp ".number_format($amt,0,',','.')." — {$desc}",
        'reply_markup'=>json_encode(main_menu_kb($user))]);
}

/* ---------- Handler pesan teks ---------- */
function handle_message(array $msg) {
    $chatId = (string)$msg['chat']['id'];
    $text = trim($msg['text'] ?? '');
    db()->prepare("INSERT INTO bot_log (chat_id,message) VALUES (?,?)")->execute([$chatId, $text]);

    if (preg_match('/^\/mulai\s+[A-Za-z0-9]{6}$/i', $text)) {
        if ($r = try_code_login($chatId, $text)) return ['text'=>$r,'kb'=>null];
    }
    if (preg_match('/^\/start\s+(\S+)\s+(\S+)/i', $text, $m)) {
        $st = db()->prepare("SELECT * FROM users WHERE username=? AND active=1");
        $st->execute([$m[1]]);
        $u = $st->fetch();
        if (!$u || !password_verify($m[2], $u['password_hash']))
            return ['text'=>"Login gagal. Cek username/password dari bos ya.\nAtau minta *kode login* (lebih aman).",'kb'=>null];
        bind_chat($chatId, (int)$u['id']);
        db()->prepare("UPDATE users SET telegram_verified=1 WHERE id=?")->execute([$u['id']]);
        return ['text'=>"Halo {$u['display_name']}! 🎉 Login berhasil.",'kb'=>main_menu_kb($u)];
    }

    $user = find_user_by_chat($chatId);
    if (!$user) {
        return ['text'=>"👋 Halo! Kamu belum terhubung ke Dompet Owner.\n\nMinta *kode login* ke bos, lalu kirim di sini:\n/mulai KODE123", 'kb'=>null];
    }

    if (preg_match('/^\/(start|bantuan|help)$/i', $text)) return ['text'=>main_menu_text($user),'kb'=>main_menu_kb($user)];

    // alur catat transaksi via tombol: user sedang menunggu input nominal/keterangan?
    $flow = bot_session_get($chatId);
    if (!empty($flow['step']) && !preg_match('/^\//', $text)) {
        if ($flow['step'] === 'amount') {
            $amtRaw = str_replace(['rp','.',' '], ['', '', ''], strtolower($text));
            $amt = (float)str_replace(',', '.', preg_replace('/[^\d,.]/', '', $amtRaw));
            if ($amt <= 0) return ['text'=>"Nominalnya belum tepat coy 😅 Contoh: 50000 atau 1.5jt format biasa juga boleh.",'kb'=>tx_amount_kb()];
            $flow['step'] = 'desc'; $flow['amount'] = $amt;
            bot_session_set($chatId, $flow);
            return ['text'=>"✍️ Untuk apa *{$flow['type']}* Rp ".number_format($amt,0,',','')."?\nTulis keterangannya (mis. beli beras)",'kb'=>['inline_keyboard'=>[[['text'=>'❌ Batal','callback_data'=>'tx:cancel']]]]];
        }
        if ($flow['step'] === 'desc') {
            $desc = mb_substr(trim($text), 0, 120);
            if ($desc === '') return ['text'=>"Keterangannya kosong — tulis dulu ya.",'kb'=>null];
            if (!$user['business_id']) { bot_session_clear($chatId); return ['text'=>"Akun kamu belum dipasangkan ke cabang. Hubungi bos.",'kb'=>null]; }
            add_transaction([
                'type'=>$flow['type'],'amount'=>$flow['amount'],
                'description'=>"$desc (via {$user['display_name']})",
                'source'=>'bot_kariawan','user_id'=>$user['id'],
                'business_id'=>(int)$user['business_id']]);
            bot_session_clear($chatId);
            $biz = db()->query("SELECT name FROM businesses WHERE id={$user['business_id']}")->fetch()['name'] ?? '';
            $emoji = $flow['type'] === 'masuk' ? '🟢 MASUK' : '🔴 KELUAR';
            notify_owner("{$emoji} [{$biz}]\nRp ".number_format($flow['amount'],0,',','.')."\n$desc\nOleh: {$user['display_name']}");
            return ['text'=>"✅ Tercatat! {$emoji} Rp ".number_format($flow['amount'],0,',','.')." — $desc\n\nAda lagi? 👇",'kb'=>main_menu_kb($user)];
        }
        if ($flow['step'] === 'aiwait') {
            bot_session_clear($chatId);
            return ['text'=>"🤖 ...\n".handle_ai_question($user, $text),'kb'=>ai_prompt_kb()];
        }
    }
    // jalur lama: masuk/keluar <jumlah> <keterangan>
    if (preg_match('/^(masuk|keluar)\s+([\d.,]+)\s+(.+)$/iu', $text, $m)) {
        $type = mb_strtolower($m[1]) === 'masuk' ? 'masuk' : 'keluar';
        $amount = (float)str_replace(['.', ','], ['', ''], $m[2]);
        $desc = trim($m[3]);
        if (!$user['business_id']) return ['text'=>"Akun kamu belum dipasangkan ke cabang usaha. Hubungi bos.",'kb'=>null];
        add_transaction(['type'=>$type,'amount'=>$amount,'description'=>"$desc (via {$user['display_name']})",
            'source'=>'bot_kariawan','user_id'=>$user['id'],'business_id'=>(int)$user['business_id']]);
        $biz = db()->query("SELECT name FROM businesses WHERE id={$user['business_id']}")->fetch()['name'];
        $emoji = $type === 'masuk' ? '🟢 MASUK' : '🔴 KELUAR';
        notify_owner("{$emoji} [{$biz}]\nRp ".number_format($amount,0,',','.')."\n{$desc}\nOleh: {$user['display_name']}\nTotal kas sekarang: Rp ".number_format(total_kas(),0,',','.'));
        return ['text'=>"Tercatat ✅ {$emoji} Rp ".number_format($amount,0,',','.')." - {$desc}",'kb'=>main_menu_kb($user)];
    }
    if (preg_match('/^\/(hariini|kas|laporan|rekap|daftarcabang|cabang)/i', $text)) {
        $t = match (true) {
            (bool)preg_match('/hariini/i', $text) => laporan_hari_ini($user),
            (bool)preg_match('/rekap/i', $text) => rekap_7_hari($user),
            default => "Perintah lama masih jalan, tapi sekarang serba tombol 😉",
        };
        return ['text'=>$t,'kb'=>main_menu_kb($user)];
    }

    // fallback: anggap pertanyaan AI kalau teks cukup panjang / tanda tanya
    if (mb_strlen($text) >= 4) {
        return ['text'=>"🤖 ...\n".handle_ai_question($user, $text),'kb'=>ai_prompt_kb()];
    }
    return ['text'=>main_menu_text($user),'kb'=>main_menu_kb($user)];
}

/* ---------- Handler callback tombol ---------- */
function handle_callback(array $cb): void {
    $chatId = (string)($cb['message']['chat']['id'] ?? '');
    $msgId = $cb['message']['message_id'] ?? null;
    $data = $cb['data'] ?? '';
    $answer = fn(string $t = '') => tg('answerCallbackQuery', ['callback_query_id'=>$cb['id'], 'text'=>$t] + ($t!==''?[]:[]));

    $user = find_user_by_chat($chatId);
    if (!$user) { $answer('Login dulu: /mulai KODE123'); return; }

    $send = function(string $text, ?array $kb=null) use ($chatId) {
        $p = ['chat_id'=>$chatId,'text'=>$text,'parse_mode'=>'Markdown'];
        if ($kb) $p['reply_markup'] = json_encode($kb);
        tg('sendMessage', $p);
    };
    $edit = function(string $text, ?array $kb=null) use ($chatId,$msgId) {
        if ($msgId===null) return;
        $p = ['chat_id'=>$chatId,'message_id'=>$msgId,'text'=>$text,'parse_mode'=>'Markdown'];
        if ($kb) $p['reply_markup'] = json_encode($kb);
        tg('editMessageText', $p);
    };

    [$ns, $arg] = array_pad(explode(':', $data, 2), 2, '');

    switch ($ns) {
        case 'm': // menu
            $map = [
                'home'     => [fn()=>main_menu_text($user), fn()=>main_menu_kb($user)],
                'kas'      => [fn()=>saldo_kas(), fn()=>back_kb()],
                'laporan'  => [fn()=>laporan_bulan_owner(), fn()=>back_kb()],
                'health'   => [fn()=>health_text(), fn()=>back_kb()],
                'txlist'   => [fn()=>tx_recent_text(), fn()=>back_kb()],
                'piutang'  => [fn()=>piutang_text(), fn()=>back_kb()],
                'pajak'    => [fn()=>pajak_text(), fn()=>back_kb()],
                'payroll'  => [fn()=>payroll_text(), fn()=>back_kb()],
                'aset'     => [fn()=>aset_text(), fn()=>back_kb()],
                'petty'    => [fn()=>petty_text(), fn()=>back_kb()],
                'cabang'   => [fn()=>cabang_list_text()."\n\nKelola kariawan dari dashboard ya.", fn()=>back_kb()],
                'appr'     => [fn()=>appr_text((int)$user['id']), fn()=>back_kb()],
                'audit'    => [fn()=>audit_text(), fn()=>back_kb()],
                'hariku'   => [fn()=>laporan_hari_ini($user), fn()=>back_kb()],
                'rekap'    => [fn()=>rekap_7_hari($user), fn()=>back_kb()],
                'cabangme' => [function() use ($user) {
                    $bn = null;
                    if ($user['business_id']) {
                        $s = db()->prepare("SELECT name FROM businesses WHERE id=?"); $s->execute([(int)$user['business_id']]);
                        $bn = $s->fetch()['name'] ?? null;
                    }
                    return "🏬 Kamu di: ".($bn ? "*$bn*" : '(belum dipasang)');
                }, fn()=>back_kb()],
            ];
            if (isset($map[$arg])) {
                $edit(($map[$arg][0])(), ($map[$arg][1])());
                $answer();
            } else $answer('?');
            break;

        case 'tx': // alur catat transaksi
            if ($arg === 'start') {
                $edit("🧾 *Catat Transaksi*\nPilih jenisnya:", ['inline_keyboard'=>[
                    [['text'=>'🟢 Uang Masuk','callback_data'=>'tx:type:masuk'],['text'=>'🔴 Uang Keluar','callback_data'=>'tx:type:keluar']],
                    [['text'=>'❌ Batal','callback_data'=>'tx:cancel']],
                ]]); $answer();
            } elseif ($arg === 'cancel') {
                bot_session_clear($chatId);
                $edit(main_menu_text($user), main_menu_kb($user)); $answer('Dibatalin');
            } elseif (str_starts_with($arg, 'type:')) {
                $type = explode(':', $arg)[1] === 'masuk' ? 'masuk' : 'keluar';
                bot_session_set($chatId, ['step'=>'amount','type'=>$type]);
                $edit("💵 {$type}: berapa nominalnya? Ketik angka, atau pakai tombol cepat:", tx_amount_kb());
                $answer();
            }
            break;

        case 'txa': // nominal cepat
            bot_session_set($chatId, ['step'=>'desc','type'=>bot_session_get($chatId)['type']??'keluar','amount'=>(float)$arg]);
            $edit("✍️ Untuk apa *".(bot_session_get($chatId)['type'])."* Rp ".number_format((float)$arg,0,',','')."?\nTulis keterangannya:",
                ['inline_keyboard'=>[[['text'=>'❌ Batal','callback_data'=>'tx:cancel']]]]);
            $answer();
            break;

        case 'rcpt': // konfirmasi struk dari foto
            if (in_array($arg, ['masuk','keluar'], true)) { rcpt_confirm($user, $chatId, $arg); $answer(); }
            else $answer();
            break;

        case 'ai':
            if ($arg === 'start' || $arg === 'free') {
                bot_session_set($chatId, ['step'=>'aiwait']);
                $edit("🤖 *AI Asisten Keuangan*\nSilakan tanya apa saja soal keuanganmu — ketik pertanyaannya sekarang:", ai_prompt_kb());
                $answer();
            }
            break;

        case 'aiq': // pertanyaan cepat AI
            $qMap = [
                'kas'=>'Berapa total kas saya sekarang dan komposisinya?',
                'bulan'=>'Bagaimana kondisi keuangan bulan ini? Beri ringkasan masuk, keluar, laba.',
                'saran'=>'Beri 3 saran konkret untuk memperbaiki keuangan bisnis saya berdasarkan data.',
                'proyeksi'=>'Bagaimana proyeksi kas 30 hari ke depan? Apakah aman?',
            ];
            $answer('🤖 mikir dulu...');
            $resp = handle_ai_question($user, $qMap[$arg] ?? $arg);
            $send("🤖 {$resp}", ai_prompt_kb());
            break;

        case 'appr':
            // approve/reject dari tombol notifikasi (owner only)
            if ($user['role'] !== 'owner') { $answer('Khusus owner'); return; }
            if (!preg_match('/^(\d+):(approve|reject)$/', $arg, $mm)) { $answer(); return; }
            $id = (int)$mm[1]; $ok = $mm[2] === 'approve';
            $st = db()->prepare("SELECT a.*, us.display_name FROM approvals a LEFT JOIN users us ON us.id=a.user_id WHERE a.id=? AND a.status='pending'");
            $st->execute([$id]);
            if (!$ap = $st->fetch()) { $answer('Sudah diputuskan'); return; }
            if ($ok) {
                $bizId = $ap['business_id'] ? (int)$ap['business_id'] : null;
                $txId = add_transaction(['type'=>$ap['type'],'amount'=>(float)$ap['amount'],
                    'description'=>$ap['description'].' [disetujui via Telegram]','source'=>'approval',
                    'user_id'=>$ap['user_id'],'business_id'=>$bizId,'scope'=>$bizId?'usaha':'pribadi']);
                db()->prepare("UPDATE approvals SET status='approved',decided_by=?,decided_at=NOW(),tx_id=? WHERE id=?")
                    ->execute([$user['id'],$txId,$id]);
            } else {
                db()->prepare("UPDATE approvals SET status='rejected',decided_by=?,decided_at=NOW() WHERE id=?")
                    ->execute([$user['id'],$id]);
            }
            audit_log($user,'appr_decide',"#{$id} ".($ok?'approved':'rejected').' via Telegram','approval',$id,['status'=>'pending'],['status'=>$ok?'approved':'rejected']);
            $edit(($ok?'✅ DISETUJUI':'❌ DITOLAK')." — #{$id} {$ap['display_name']}\nRp "
                . number_format((float)$ap['amount'],0,',','.')." {$ap['description']}", null);
            $answer($ok ? 'Disetujui ✅' : 'Ditolak ❌');
            break;

        default:
            $answer();
    }
}

/* ---------- Runner ---------- */
function process_update(array $update): void {
    try {
        if (isset($update['callback_query'])) { handle_callback($update['callback_query']); return; }
        if (!empty($update['message']['photo'])) { handle_photo($update['message']); return; }
        // foto dikirim sebagai dokumen juga ditangani
        if (str_starts_with((string)($update['message']['document']['mime_type'] ?? ''), 'image/')) {
            $update['message']['photo'] = [['file_id' => $update['message']['document']['file_id']]];
            handle_photo($update['message']);
            return;
        }
        if (!isset($update['message'])) return;
        $res = handle_message($update['message']);
        $p = ['chat_id'=>$update['message']['chat']['id'],'text'=>$res['text'],'parse_mode'=>'Markdown'];
        if (!empty($res['kb'])) $p['reply_markup'] = json_encode($res['kb']);
        tg('sendMessage', $p);
    } catch (Throwable $e) {
        if (isset($update['message']))
            tg('sendMessage', ['chat_id'=>$update['message']['chat']['id'], 'text'=>"Terjadi kesalahan: ".$e->getMessage()]);
    }
}

if (PHP_SAPI === 'cli' && !defined('BOT_TEST')) {
    echo "Bot Dompet Owner v3 mulai (serba tombol + AI)...\n";
    if (bot_token() === '') { echo "Token belum diisi! Isi lewat web: Pengaturan → Bot Telegram.\n"; exit(1); }
    set_bot_commands();
    $offset = 0;
    while (true) {
        $updates = tg('getUpdates', ['offset' => $offset, 'timeout' => 25]);
        foreach (($updates['result'] ?? []) as $u) {
            $offset = $u['update_id'] + 1;
            process_update($u);
            echo "handled update {$u['update_id']}\n";
        }
        if (!isset($updates['result'])) sleep(3);
    }
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) { process_update($input); http_response_code(200); }
}
