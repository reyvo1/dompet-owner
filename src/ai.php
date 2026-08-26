<?php
// AI Assistant Dompet Owner - pakai Google Gemini API.
// Set GEMINI_KEY di tabel settings (atau env GEMINI_KEY).
require_once __DIR__ . '/config.php';

function gemini_key(): string {
    $k = cfg('gemini_key');
    if ($k !== '') return $k;
    $k = getenv('GEMINI_KEY');
    return (string)$k;
}

// Kumpulkan ringkasan keuangan sebagai konteks AI
function finance_context(): string {
    $period = date('Y-m');
    $kas = total_kas();
    $p = laporan_bulan($period, null);
    $um = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE scope='usaha' AND type='masuk' AND DATE_FORMAT(tx_date,'%Y-%m')='$period'")->fetch()['s'];
    $uk = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE scope='usaha' AND type='keluar' AND DATE_FORMAT(tx_date,'%Y-%m')='$period'")->fetch()['s'];
    $out = "Total kas dompet owner: Rp " . number_format($kas, 0, ',', '.') . "\n";
    $out .= "Bulan ini pribadi: masuk Rp " . number_format($p['masuk'], 0, ',', '.') . ", keluar Rp " . number_format($p['keluar'], 0, ',', '.') . "\n";
    $out .= "Bulan ini usaha: masuk Rp " . number_format($um, 0, ',', '.') . ", keluar Rp " . number_format($uk, 0, ',', '.') . ", laba Rp " . number_format($um - $uk, 0, ',', '.') . "\n";
    foreach (db()->query("SELECT id,name FROM businesses WHERE active=1") as $b) {
        $l = laporan_bulan($period, (int)$b['id']);
        $out .= "Cabang {$b['name']}: masuk Rp " . number_format($l['masuk'], 0, ',', '.') . ", keluar Rp " . number_format($l['keluar'], 0, ',', '.') . ", laba Rp " . number_format($l['laba'], 0, ',', '.') . "\n";
    }
    // 5 pengeluaran terbesar bulan ini
    $st = db()->query("SELECT description, business_id, SUM(amount) s FROM transactions
        WHERE type='keluar' AND DATE_FORMAT(tx_date,'%Y-%m')='$period'
        GROUP BY description, business_id ORDER BY s DESC LIMIT 5");
    foreach ($st as $r) $out .= "Pengeluaran besar: {$r['description']} Rp " . number_format((float)$r['s'], 0, ',', '.') . "\n";
    // tren 6 bulan (untuk pertanyaan arah/tren)
    $out .= "Tren 6 bulan terakhir (bulan: masuk/keluar): ";
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $mi = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='masuk' AND DATE_FORMAT(tx_date,'%Y-%m')='$m'")->fetch()['s'];
        $mo = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='keluar' AND DATE_FORMAT(tx_date,'%Y-%m')='$m'")->fetch()['s'];
        $out .= $m . ": Rp" . number_format($mi,0,',','.') . "/" . number_format($mo,0,',','.') . "; ";
    }
    $out .= "\n";
    // proyeksi 30 hari
    $in90 = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='masuk' AND tx_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)")->fetch()['s'] / 90;
    $out90 = (float)db()->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='keluar' AND tx_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)")->fetch()['s'] / 90;
    $proj = $kas + ($in90 - $out90) * 30;
    $out .= "Proyeksi kas 30 hari ke depan (ritme 90 hari): sekitar Rp " . number_format($proj, 0, ',', '.') . "\n";
    return $out;
}

// AI baca foto struk/nota -> ekstrak JSON {total, merchant, items[], tanggal}
function ai_read_receipt(string $imagePath): ?array {
    $key = gemini_key();
    if ($key === '' || !is_file($imagePath)) return null;
    $mime = ['jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][strtolower(pathinfo($imagePath, PATHINFO_EXTENSION))] ?? 'image/jpeg';
    $b64 = base64_encode(file_get_contents($imagePath));
    $prompt = "Kamu asisten keuangan. Baca struk/nota/foto pembayaran ini dan jawab HANYA json valid tanpa teks lain: "
        . '{"total": angka_nominal_rupiah_tanpa_titik, "merchant": "nama toko", "tanggal": "YYYY-MM-DD atau null", "items": ["item1 @harga", "..."]}. '
        . "Kalau bukan struk/nota, set total=0.";
    $body = json_encode([
        'contents' => [['parts' => [
            ['text' => $prompt],
            ['inline_data' => ['mime_type' => $mime, 'data' => $b64]],
        ]]],
        'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 400],
    ]);
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . urlencode($key));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 45,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if (!$res) return null;
    $j = json_decode($res, true);
    $txt = $j['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!preg_match('/\{.*\}/s', $txt, $m)) return null;
    $d = json_decode($m[0], true);
    if (!is_array($d)) return null;
    return ['total'=>(float)($d['total'] ?? 0), 'merchant'=>(string)($d['merchant'] ?? 'Nota'),
            'tanggal'=>$d['tanggal'] ?? null, 'items'=>$d['items'] ?? []];
}

// riwayat chat per user utk memori konteks (maks 6 putar terakhir)
function ai_history_get(int $userId): array {
    $v = cfg("aimem:$userId");
    return $v !== '' ? (json_decode($v, true) ?: []) : [];
}
function ai_history_push(int $userId, string $q, string $a): void {
    $h = ai_history_get($userId);
    $h[] = ['role'=>'user','text'=>$q];
    $h[] = ['role'=>'model','text'=>mb_substr($a,0,800)];
    $h = array_slice($h, -12); // 6 putar
    set_cfg("aimem:$userId", json_encode($h));
}
function ai_history_clear(int $userId): void {
    db()->prepare("DELETE FROM app_settings WHERE k=?")->execute(["aimem:$userId"]);
}

function ai_answer(string $question): string {
    return ai_answer_ctx(0, $question);
}
function ai_answer_ctx(int $userId, string $question): string {
    $key = gemini_key();
    if ($key === '') return "API key Gemini belum dipasang. Minta bos isi dulu ya.";
    $sys = "Kamu asisten keuangan untuk seorang pemilik usaha (berbahasa Indonesia santai). "
        . "Data keuangan terkini:\n" . finance_context()
        . "\nJawab singkat, jelas, pakai angka dari data. Beri saran bila relevan.";
    $contents = [];
    foreach (ai_history_get($userId) as $h) $contents[] = ['role'=>$h['role'],'parts'=>[['text'=>$h['text']]]];
    $contents[] = ['role'=>'user','parts'=>[['text'=>$question]]];
    $body = json_encode([
        'system_instruction' => ['parts' => [['text' => $sys]]],
        'contents' => $contents,
        'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 500],
    ]);
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . urlencode($key));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 30,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if (!$res) return "AI sedang tidak bisa dihubungi.";
    $j = json_decode($res, true);
    $ans = $j['candidates'][0]['content']['parts'][0]['text']
        ?? "AI tidak memberi jawaban. Coba lagi.";
    if ($userId > 0 && !str_starts_with($ans, 'AI ')) ai_history_push($userId, $question, $ans);
    return $ans;
}
