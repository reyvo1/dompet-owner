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

function ai_answer(string $question): string {
    $key = gemini_key();
    if ($key === '') return "API key Gemini belum dipasang. Minta bos isi dulu ya.";
    $prompt = "Kamu asisten keuangan untuk seorang pemilik usaha (berbahasa Indonesia santai). "
        . "Data keuangan terkini:\n" . finance_context()
        . "\nPertanyaan bos: $question\nJawab singkat, jelas, pakai angka dari data. Beri saran bila relevan.";
    $body = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
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
    return $j['candidates'][0]['content']['parts'][0]['text']
        ?? "AI tidak memberi jawaban. Coba lagi.";
}
