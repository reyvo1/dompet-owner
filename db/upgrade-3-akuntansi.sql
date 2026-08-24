-- Upgrade 3: AKUNTANSI — Chart of Accounts + Journal double-entry
-- ASCII only (pelajaran IoT/TAMASYA: em-dash merusak parsing)
USE dompet_owner;

-- Chart of Accounts (kode standar Indonesia)
CREATE TABLE IF NOT EXISTS coa_accounts (
  code VARCHAR(10) PRIMARY KEY,             -- 1-1000 dst
  name VARCHAR(120) NOT NULL,
  type ENUM('aset','liabilitas','ekuitas','pendapatan','hpp','beban') NOT NULL,
  normal_balance ENUM('debit','kredit') NOT NULL,
  is_cash TINYINT(1) NOT NULL DEFAULT 0,    -- akun kas/bank (utk arus kas)
  wallet_id INT UNSIGNED NULL,              -- sinkron dgn wallets (kas fisik)
  business_id INT UNSIGNED NULL,            -- NULL = dipakai semua cabang
  active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (wallet_id) REFERENCES wallets(id),
  FOREIGN KEY (business_id) REFERENCES businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Header jurnal (1 transaksi = 1 jurnal)
CREATE TABLE IF NOT EXISTS journal_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tx_id BIGINT UNSIGNED NULL,               -- sumber transaksi (nullable utk jurnal manual)
  entry_date DATE NOT NULL,
  memo VARCHAR(255) NOT NULL DEFAULT '',
  source ENUM('otomatis','manual') NOT NULL DEFAULT 'otomatis',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_je_tx (tx_id),
  INDEX idx_je_date (entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Baris jurnal (debit/kredit; SUM(debit)=SUM(kredit) wajib per entry)
CREATE TABLE IF NOT EXISTS journal_lines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entry_id BIGINT UNSIGNED NOT NULL,
  account_code VARCHAR(10) NOT NULL,
  debit DECIMAL(16,2) NOT NULL DEFAULT 0,
  credit DECIMAL(16,2) NOT NULL DEFAULT 0,
  business_id INT UNSIGNED NULL,            -- dimensi cabang
  memo VARCHAR(255) NULL,
  FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
  FOREIGN KEY (account_code) REFERENCES coa_accounts(code),
  FOREIGN KEY (business_id) REFERENCES businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pajak terpotong/terkumpul per periode (hasil tax engine)
CREATE TABLE IF NOT EXISTS tax_lines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tx_id BIGINT UNSIGNED NOT NULL,
  rule_id INT UNSIGNED NOT NULL,
  tax_type ENUM('pbjt','pph_umkm','non_pajak') NOT NULL,
  base_amount DECIMAL(16,2) NOT NULL,       -- DPP
  rate_pct DECIMAL(6,3) NOT NULL,
  tax_amount DECIMAL(16,2) NOT NULL,
  status ENUM('unresolved','tertagih') NOT NULL DEFAULT 'tertagih',
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tax_tx (tx_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Piutang (jualan tempo): terpisah dari kas
CREATE TABLE IF NOT EXISTS receivables (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NULL,
  debtor_name VARCHAR(120) NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  paid_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
  due_date DATE NULL,
  status ENUM('open','paid','partial') NOT NULL DEFAULT 'open',
  tx_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (business_id) REFERENCES businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
