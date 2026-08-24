<?php
// Bot Telegram Dompet Owner v2 - polling ATAU webhook.
// Konfigurasi token dari tabel app_settings (halaman Pengaturan web).
require_once __DIR__ . '/../src/config.php';

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
    return "Halo {$u['display_name']}! 🎉 Login berhasil.\n\n" . help_text($u);
}
function help_text(array $user): string {
    $h = "📖 Perintah yang bisa dipakai:\n"
       . "• `masuk 50000 jual nasi goreng` — catat pemasukan\n"
       . "• `keluar 25000 beli beras` — catat pengeluaran\n"
       . "/hariini — laporan cabangmu hari ini\n"
       . "/rekap — rekap 7 hari\n"
       . "/bantuan — tampilkan menu ini";
    if ($user['role'] === 'owner') {
        $h .= "\n\n👑 Khusus owner:\n/kas — saldo semua kas dompet\n/laporan — laporan bulan ini semua scope";
    }
    return $h;
}

/* ---------- Laporan ---------- */
function laporan_hari_ini(array $user): string {
    $b = (int)$user['business_id'];
    $st = db()->prepare("SELECT type, COALESCE(SUM(amount),0) t FROM transactions
        WHERE tx_date=CURDATE() AND business_id=? GROUP BY type");
    $st->execute([$b]);
    $d = ['masuk'=>0,'keluar'=>0];
    foreach ($st as $r) $d[$r['type']] = (float)$r['t'];
    $biz = db()->query("SELECT name FROM businesses WHERE id=$b")->fetch()['name'];
    return "📊 *Laporan hari ini — {$biz}*\nMasuk : Rp ".number_format($d['masuk'],0,',','.')
        ."\nKeluar: Rp ".number_format($d['keluar'],0,',','.')
        ."\nSelisih: Rp ".number_format($d['masuk']-$d['keluar'],0,',','.');
}
function rekap_7_hari(array $user): string {
    $b = (int)$user['business_id'];
    $st = db()->prepare("SELECT type, COALESCE(SUM(amount),0) t FROM transactions
        WHERE tx_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND business_id=? GROUP BY type");
    $st->execute([$b]);
    $d = ['masuk'=>0,'keluar'=>0];
    foreach ($st as $r) $d[$r['type']] = (float)$r['t'];
    $biz = db()->query("SELECT name FROM businesses WHERE id=$b")->fetch()['name'];
    return "📈 *Rekap 7 hari — {$biz}*\nMasuk : Rp ".number_format($d['masuk'],0,',','.')
        ."\nKeluar: Rp ".number_format($d['keluar'],0,',','.')
        ."\nLaba  : Rp ".number_format($d['masuk']-$d['keluar'],0,',','.');
}
function laporan_bulan_owner(): string {
    $p = date('Y-m');
    $pr = laporan_bulan($p, null);
    $out = "📋 *Laporan bulan ini*\n\n👤 Pribadi:\n masuk Rp ".number_format($pr['masuk'],0,',','.')
        .", keluar Rp ".number_format($pr['keluar'],0,',','.')."\n\n🏪 Usaha:";
    foreach (db()->query("SELECT * FROM businesses WHERE active=1") as $b) {
        $l = laporan_bulan($p, (int)$b['id']);
        $out .= "\n {$b['name']}: laba Rp ".number_format($l['laba'],0,',','.');
    }
    $um=(float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE scope='usaha' AND type='masuk' AND DATE_FORMAT(tx_date,'%Y-%m')='$p'")->fetch()['s'];
    $uk=(float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE scope='usaha' AND type='keluar' AND DATE_FORMAT(tx_date,'%Y-%m')='$p'")->fetch()['s'];
    $out .= "\n\nTotal laba usaha: Rp ".number_format($um-$uk,0,',','.');
    return $out;
}
function saldo_kas(): string {
    $out = "💼 *Saldo Kas Dompet*\n";
    foreach (wallets_all() as $w)
        $out .= "\n".($w['is_default']?'⭐':'•')." {$w['name']}: Rp ".number_format((float)$w['balance'],0,',','.');
    $out .= "\n\n*TOTAL: Rp ".number_format(total_kas(),0,',','').'*';
    return str_replace(',','', $out); // markdown bold aman
}

/* ---------- Handler utama ---------- */
function handle_message(array $msg): string {
    $chatId = (string)$msg['chat']['id'];
    $text = trim($msg['text'] ?? '');
    db()->prepare("INSERT INTO bot_log (chat_id,message) VALUES (?,?)")->execute([$chatId, $text]);

    // onboarding tanpa sesi
    if (preg_match('/^\/mulai\s+[A-Za-z0-9]{6}$/i', $text)) {
        if ($r = try_code_login($chatId, $text)) return $r;
    }
    if (preg_match('/^\/start\s+(\S+)\s+(\S+)/i', $text, $m)) {
        // login klasik username+password masih didukung
        $st = db()->prepare("SELECT * FROM users WHERE username=? AND active=1");
        $st->execute([$m[1]]);
        $u = $st->fetch();
        if (!$u || !password_verify($m[2], $u['password_hash']))
            return "Login gagal. Cek username/password dari bos ya.\nAtau minta *kode login* (lebih aman).";
        bind_chat($chatId, (int)$u['id']);
        db()->prepare("UPDATE users SET telegram_verified=1 WHERE id=?")->execute([$u['id']]);
        return "Halo {$u['display_name']}! 🎉 Login berhasil.\n\n".help_text($u);
    }

    $user = find_user_by_chat($chatId);
    if (!$user) {
        return "👋 Halo! Kamu belum terhubung ke sistem Dompet Owner.\n\n"
             . "Minta *kode login* ke bos, lalu kirim di sini:\n/mulai KODE123\n\n"
             . "Atau login lama: `/start username password`";
    }

    if (preg_match('/^\/(start|bantuan|help)$/i', $text)) return help_text($user);
    if (preg_match('/^\/hariini/i', $text)) return laporan_hari_ini($user);
    if (preg_match('/^\/rekap/i', $text)) return rekap_7_hari($user);
    if (preg_match('/^\/kas/i', $text)) {
        if ($user['role'] !== 'owner') return "Perintah ini khusus owner 👑";
        return saldo_kas();
    }
    if (preg_match('/^\/laporan/i', $text)) {
        if ($user['role'] !== 'owner') return "Perintah ini khusus owner 👑";
        return laporan_bulan_owner();
    }

    // masuk/keluar <jumlah> <keterangan>
    if (preg_match('/^(masuk|keluar)\s+([\d.,]+)\s+(.+)$/iu', $text, $m)) {
        $type = mb_strtolower($m[1]) === 'masuk' ? 'masuk' : 'keluar';
        $amount = (float)str_replace(['.', ','], ['', ''], $m[2]);
        $desc = trim($m[3]);
        if (!$user['business_id']) return "Akun kamu belum dipasangkan ke cabang usaha. Hubungi bos.";
        add_transaction([
            'type' => $type,
            'amount' => $amount,
            'description' => "$desc (via {$user['display_name']})",
            'source' => 'bot_kariawan',
            'user_id' => $user['id'],
            'business_id' => (int)$user['business_id'],
        ]);
        $biz = db()->query("SELECT name FROM businesses WHERE id={$user['business_id']}")->fetch()['name'];
        $emoji = $type === 'masuk' ? '🟢 MASUK' : '🔴 KELUAR';
        notify_owner("{$emoji} [{$biz}]\nRp " . number_format($amount, 0, ',', '.') . "\n{$desc}\nOleh: {$user['display_name']}\nTotal kas sekarang: Rp " . number_format(total_kas(), 0, ',', '.'));
        return "Tercatat ✅ {$emoji} Rp " . number_format($amount, 0, ',', '.') . " - {$desc}";
    }
    return "🤔 Perintah tidak dikenal.\n" . help_text($user);
}

/* ---------- Runner ---------- */
function process_update(array $update): void {
    if (!isset($update['message'])) return;
    try {
        $reply = handle_message($update['message']);
    } catch (Throwable $e) {
        $reply = "Terjadi kesalahan: " . $e->getMessage();
    }
    tg('sendMessage', [
        'chat_id' => $update['message']['chat']['id'],
        'text' => $reply,
        'parse_mode' => 'Markdown',
    ]);
}

if (PHP_SAPI === 'cli' && !defined('BOT_TEST')) {
    echo "Bot Dompet Owner mulai (polling)...\n";
    if (bot_token() === '') { echo "Token belum diisi! Isi lewat web: Pengaturan → Bot Telegram.\n"; exit(1); }
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
