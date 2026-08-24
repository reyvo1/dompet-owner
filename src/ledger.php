<?php
// ============================================================
// MESIN AKUNTANSI Dompet Owner
// - Chart of Accounts + double-entry journal
// - Tax engine (rule matching ala TAMASYA PBJT)
// - Mapping kategori transaksi -> akun COA (dapat ditimpa via UI)
// ============================================================
require_once __DIR__ . '/config.php';

/* ---------- Posting jurnal double-entry ---------- */
// $lines = [ ['account'=>'4-1000','debit'=>0,'credit'=>500000,'memo'=>...], ... ]
function post_journal(string $date, string $memo, array $lines, ?int $txId = null): int {
    $sumD = 0; $sumC = 0;
    foreach ($lines as $l) { $sumD += (float)($l['debit']??0); $sumC += (float)($l['credit']??0); }
    if (abs($sumD - $sumC) > 0.001)
        throw new InvalidArgumentException("Jurnal tidak seimbang: debit $sumD vs kredit $sumC");
    if ($sumD == 0) throw new InvalidArgumentException('Jurnal kosong');

    db()->prepare("INSERT INTO journal_entries (tx_id,entry_date,memo,source) VALUES (?,?,?,'otomatis')")
        ->execute([$txId,$date,mb_substr($memo,0,255)]);
    $eid = (int)db()->lastInsertId();
    $st = db()->prepare("INSERT INTO journal_lines (entry_id,account_code,debit,credit,business_id,memo)
        VALUES (?,?,?,?,?,?)");
    foreach ($lines as $l) {
        $st->execute([$eid,$l['account'],(float)($l['debit']??0),(float)($l['credit']??0),
            $l['business_id']??null, mb_substr($l['memo']??'',0,255)]);
    }
    return $eid;
}

/* ---------- Mapping kategori -> akun COA ----------
   Dapat ditimpa lewat tabel mapping (UI). Fallback by keyword. */
function coa_for_category(?string $categoryName, string $type): array {
    // default: masuk=pendapatan penjualan, keluar=bab beban lain-lain
    $n = mb_strtolower(trim((string)$categoryName));
    $map = [
        'penjualan'          => ['4-1000','pendapatan'],
        'modal/setoran'      => ['3-1000','ekuitas'],
        'gaji/laba'          => ['4-2000','pendapatan'],
        'lain-lain masuk'    => ['4-2000','pendapatan'],
        'beli barang usaha'  => ['6-1000','beban'],
        'gaji kariawan'      => ['6-1100','beban'],
        'sewa tempat'        => ['6-1200','beban'],
        'operasional cabang' => ['6-1300','beban'],
        'makan & minum'      => ['3-1100','prive'],     // pribadi owner -> prive
        'transport'          => ['3-1100','prive'],
        'belanja pribadi'    => ['3-1100','prive'],
        'hiburan'            => ['3-1100','prive'],
        'tagihan rumah'      => ['3-1100','prive'],
        'lain-lain keluar'   => ['6-9000','beban'],
    ];
    foreach ($map as $k=>$v) {
        if ($n !== '' && str_contains($n, mb_strtolower($k))) {
            if ($type==='masuk') return [$v[0],$v[1]];
            // keluar dgn kategori pendapatan tidak logis -> beban lain
            return ($v[1]==='pendapatan') ? ['6-9000','beban'] : [$v[0],$v[1]];
        }
    }
    return ($type==='masuk') ? ['4-1000','pendapatan'] : ['6-9000','beban'];
}

/* Akun kas sesuai wallet */
function coa_for_wallet(int $walletId): string {
    $st = db()->prepare("SELECT code FROM coa_accounts WHERE wallet_id=? AND is_cash=1 LIMIT 1");
    $st->execute([$walletId]);
    return $st->fetch()['code'] ?? '1-1000';
}

/* ---------- TAX ENGINE: rule matching ala TAMASYA ---------- */
function match_tax_rule(int $businessId, string $type, ?string $categoryName, string $date): ?array {
    $st = db()->prepare("SELECT * FROM tax_rules WHERE is_active=1 AND tx_kind=?");
    $st->execute([$type]);
    $candidates = []; $hasSpecific = false;
    foreach ($st as $r) {
        // cocokkan cabang
        if ($r['business_id'] !== null && (int)$r['business_id'] !== $businessId) continue;
        // cocokkan tanggal
        if ($r['valid_from'] && $date < $r['valid_from']) continue;
        if ($r['valid_to'] && $date > $r['valid_to']) continue;
        // cocokkan kategori: rule punya category_name -> harus match LIKE
        if ($r['category_name'] !== null) {
            $cat = mb_strtolower(trim((string)$categoryName));
            if ($cat==='' || !str_contains($cat, mb_strtolower($r['category_name']))) continue;
        }
        // skor spesifisitas: rule paling spesifik menang
        $score = 0;
        if ($r['business_id'] !== null) $score += 2;
        if ($r['category_name'] !== null) $score += 2;
        if ($r['valid_from'] !== null || $r['valid_to'] !== null) $score += 1;
        $r['_score'] = $score;
        if ($r['business_id'] !== null || $r['category_name'] !== null) $hasSpecific = true;
        $candidates[] = $r;
    }
    if (!$candidates) return null;
    // PRINSIP PAJAK AMAN: transaksi USAHA harus punya aturan SPESIFIK (per cabang/kategori).
    // Kalau hanya kena rule generik "non_pajak default" -> UNRESOLVED supaya owner review dulu.
    if (!$hasSpecific) {
        usort($candidates, fn($a,$b)=>$b['_score']<=>$a['_score']);
        $top = $candidates[0];
        if ($top['tax_type']==='non_pajak') return null; // sinyal unresolved ke apply_tax
        return $top;
    }
    usort($candidates, fn($a,$b)=>$b['_score']<=>$a['_score']);
    return $candidates[0];
}

/* Terapkan pajak ke sebuah transaksi masuk; simpan tax_lines.
   Return: ['tax_amount'=>x,'tax_type'=>..,'status'=>..] atau null utk non-pajak */
function apply_tax(int $txId, int $businessId, float $baseAmount, string $type, ?string $category, string $date): ?array {
    if ($type !== 'masuk') return null; // sementara pajak hanya di pendapatan
    $rule = match_tax_rule($businessId, $type, $category, $date);
    if (!$rule) {
        // unresolved: tidak ada aturan yang cocok & bukan non-pajak default
        db()->prepare("INSERT INTO tax_lines (tx_id,rule_id,tax_type,base_amount,rate_pct,tax_amount,status,note)
            VALUES (?,0,'non_pajak',?,0,0,'unresolved','Tidak ada aturan pajak yang cocok')")->execute([$txId,$baseAmount]);
        return null;
    }
    if ($rule['tax_type']==='non_pajak' || (float)$rule['rate_pct']==0) {
        db()->prepare("INSERT INTO tax_lines (tx_id,rule_id,tax_type,base_amount,rate_pct,tax_amount,status)
            VALUES (?,?, 'non_pajak',?,?,0,'tertagih')")->execute([$txId,$rule['id'],$baseAmount,$rule['rate_pct']]);
        return null;
    }
    $tax = 0.0; $dpp = $baseAmount;
    $rate = (float)$rule['rate_pct'];
    if ($rule['tax_type']==='pbjt') {
        // Konvensi restoran/hotel: harga jual BRUTO sudah termasuk PBJT.
        // DPP = bruto / (1 + rate), pajak = bruto - DPP
        $dpp = round($baseAmount / (1 + $rate/100), 2);
        $tax = round($baseAmount - $dpp, 2);
    } else {
        // PPh UMKM: 0.5% DARI OMZET bruto (potong dari omzet)
        $tax = round($baseAmount * ($rate/100), 2);
    }
    db()->prepare("INSERT INTO tax_lines (tx_id,rule_id,tax_type,base_amount,rate_pct,tax_amount,status)
        VALUES (?,?,?,?,?,?,'tertagih')")->execute([$txId,$rule['id'],$rule['tax_type'],$dpp,$rate,$tax]);
    return ['tax_amount'=>$tax,'tax_type'=>$rule['tax_type'],'rate'=>$rate,'dpp'=>$dpp];
}

/* ---------- POSTING OTOMATIS: transaksi -> jurnal + pajak ---------- */
function post_transaction_to_ledger(array $t): void {
    // $t = row dari tabel transactions (sudah tersimpan)
    $txId = (int)$t['id'];
    $date = $t['tx_date'];
    $amount = (float)$t['amount'];
    $biz = $t['business_id'] ? (int)$t['business_id'] : null;
    $scope = $t['scope'];

    // cegah dobel posting
    $c = db()->prepare("SELECT COUNT(*) c FROM journal_entries WHERE tx_id=?");
    $c->execute([$txId]);
    if ((int)$c->fetch()['c']>0) return;

    $catStmt = db()->prepare("SELECT name FROM categories WHERE id=?");
    $catStmt->execute([$t['category_id']]);
    $catName = $catStmt->fetch()['name'] ?? null;
    $cashAcc = coa_for_wallet((int)$t['wallet_id']);

    if ($t['type'] === 'transfer') {
        $destAcc = coa_for_wallet((int)$t['wallet_dest_id']);
        post_journal($date, "Transfer kas: {$t['description']}", [
            ['account'=>$destAcc,'debit'=>$amount],
            ['account'=>$cashAcc,'credit'=>$amount],
        ], $txId);
        return;
    }

    [$pnlAcc,$kind] = coa_for_category($catName, $t['type']);
    // PENTING: transaksi pribadi owner SELALU Prive (bukan beban usaha),
    // apa pun kategorinya — ini yang membuat laba usaha tidak tercemar belanja pribadi.
    $isPrive = ($scope === 'pribadi') || ($kind==='prive');

    if ($t['type'] === 'masuk') {
        $lines = [ ['account'=>$cashAcc,'debit'=>$amount] ];
        // pajak di pendapatan usaha
        $taxInfo = null;
        if ($scope==='usaha') {
            $taxInfo = apply_tax($txId, $biz, $amount, 'masuk', $catName, $date);
            if ($taxInfo && $taxInfo['tax_amount']>0) {
                $utangAcc = $taxInfo['tax_type']==='pbjt' ? '2-1100' : '2-1200';
                // omzet dicatat neto, pajak ke hutang pajak
                $neto = $amount - $taxInfo['tax_amount'];
                $lines[0]['debit'] = $amount; // kas masuk bruto
                $lines[] = ['account'=>'4-1000','credit'=>$neto];
                $lines[] = ['account'=>$utangAcc,'credit'=>$taxInfo['tax_amount'],'memo'=>'Pajak '.$taxInfo['tax_type'].' '.$taxInfo['rate'].'%'];
            } else {
                $lines[] = ['account'=>$pnlAcc,'credit'=>$amount];
            }
        } else {
            $lines[] = ['account'=>$pnlAcc,'credit'=>$amount]; // pribadi = setoran/modal dsb
        }
        post_journal($date, "Masuk: {$t['description']}", $lines, $txId);
    }

    if ($t['type'] === 'keluar') {
        $lines = [];
        if ($isPrive) {
            // pengeluaran pribadi owner -> Prive (bukan beban usaha!)
            $lines[] = ['account'=>'3-1100','debit'=>$amount];
        } else {
            $lines[] = ['account'=>$pnlAcc,'debit'=>$amount];
        }
        $lines[] = ['account'=>$cashAcc,'credit'=>$amount];
        post_journal($date, "Keluar: {$t['description']}", $lines, $txId);
    }
}

/* ---------- LAPORAN AKUNTANSI ---------- */
function trial_balance(?string $from=null, ?string $to=null): array {
    $w = " WHERE 1=1";
    $a = [];
    if ($from) { $w .= " AND je.entry_date>=?"; $a[]=$from; }
    if ($to)   { $w .= " AND je.entry_date<=?"; $a[]=$to; }
    $st = db()->prepare("SELECT jl.account_code, ca.name, ca.type, ca.normal_balance,
        SUM(jl.debit) tot_debit, SUM(jl.credit) tot_credit
        FROM journal_lines jl JOIN journal_entries je ON je.id=jl.entry_id
        JOIN coa_accounts ca ON ca.code=jl.account_code $w
        GROUP BY jl.account_code ORDER BY jl.account_code");
    $st->execute($a);
    return $st->fetchAll();
}
function profit_loss(string $period): array {
    $rows = trial_balance($period.'-01', $period.'-31');
    $out=['pendapatan'=>0.0,'hpp'=>0.0,'beban'=>0.0,'detail'=>[]];
    foreach ($rows as $r) {
        $net = (float)$r['tot_debit'] - (float)$r['tot_credit'];
        if ($r['type']==='pendapatan') { $val = -$net; $out['pendapatan']+=$val; }
        elseif ($r['type']==='hpp') { $val=$net; $out['hpp']+=$val; }
        elseif ($r['type']==='beban') { $val=$net; $out['beban']+=$val; }
        else continue;
        $out['detail'][]=['code'=>$r['account_code'],'name'=>$r['name'],'type'=>$r['type'],'value'=>$val];
    }
    $out['laba_kotor'] = $out['pendapatan'] - $out['hpp'];
    $out['laba_bersih'] = $out['laba_kotor'] - $out['beban'];
    return $out;
}
function balance_sheet(): array {
    $rows = trial_balance(); // semua waktu
    $out=['aset'=>0.0,'liabilitas'=>0.0,'ekuitas'=>0.0,'pendapatan'=>0.0,'hpp'=>0.0,'beban'=>0.0,'detail'=>[]];
    foreach ($rows as $r) {
        $net=(float)$r['tot_debit']-(float)$r['tot_credit'];
        $t=$r['type'];
        if (isset($out[$t])) $out[$t]+=($t==='aset'||$t==='hpp'||$t==='beban')?$net:-$net;
        $out['detail'][]=['code'=>$r['account_code'],'name'=>$r['name'],'type'=>$t,
            'balance'=>($t==='aset'||$t==='hpp'||$t==='beban'||$t==='ekuitas'&&false)?$net:(-$net)];
    }
    // laba periode berjalan masuk ekuitas
    $out['laba_berjalan'] = $out['pendapatan']-$out['hpp']-$out['beban'];
    $out['total_aset_net'] = $out['aset'];
    $out['total_liab_ekuitas'] = $out['liabilitas']+$out['ekuitas']+$out['laba_berjalan'];
    return $out;
}
function cash_flow(string $period): array {
    $st = db()->prepare("SELECT ca.name, SUM(jl.debit)-SUM(jl.credit) net
        FROM journal_lines jl JOIN journal_entries je ON je.id=jl.entry_id
        JOIN coa_accounts ca ON ca.code=jl.account_code
        WHERE ca.is_cash=1 AND DATE_FORMAT(je.entry_date,'%Y-%m')=?
        GROUP BY ca.code ORDER BY ca.code");
    $st->execute([$period]);
    $rows=$st->fetchAll();
    $in=0;$outC=0;
    foreach($rows as &$r){ $r['net']=(float)$r['net']; if($r['net']>=0)$in+=$r['net']; else $outC+=-$r['net']; }
    return ['per_kas'=>$rows,'masuk'=>$in,'keluar'=>$outC,'bersih'=>$in-$outC];
}
