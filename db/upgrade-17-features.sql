-- ============================================================
-- UPGRADE 17: Paket fitur lengkap
--  1. Kontak pelanggan & pemasok (CRM lite)
--  2. Kasbon kariawan (employee cash advance)
--  3. Komisi penjualan otomatis (rule % -> bonus payroll)
--  4. Rekonsiliasi kas & bank (opname + penyesuaian otomatis)
--  5. Cicilan terjadwal utk liabilitas pinjaman (tenor + bunga)
--  6. Keamanan login: throttle percobaan + 2FA OTP via Telegram
--  7. Laporan laba per produk (harga jual tercatat di stock_moves)
--  8. Settings baru: WhatsApp (Fonnte), QRIS, kurs otomatis, backup cloud
-- ============================================================
USE dompet_owner;

-- ---------- 1) KONTAK ----------
CREATE TABLE IF NOT EXISTS contacts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('customer','supplier','both') NOT NULL DEFAULT 'customer',
  name VARCHAR(128) NOT NULL,
  phone VARCHAR(32) NULL,
  note VARCHAR(255) NULL,
  business_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_contact_name (name),
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE invoices
  ADD COLUMN contact_id INT UNSIGNED NULL AFTER customer_name,
  ADD CONSTRAINT fk_inv_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL;

-- ---------- 2) KASBON KARIAWAN ----------
CREATE TABLE IF NOT EXISTS employee_advances (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  advance_date DATE NOT NULL,
  reason VARCHAR(255) NULL,
  status ENUM('open','payroll','settled') NOT NULL DEFAULT 'open',
  settled_via VARCHAR(40) NULL,          -- 'cash' | 'payroll YYYY-MM' | '#id payroll'
  tx_id BIGINT UNSIGNED NULL,            -- transaksi kas keluar (pemberian) / masuk (pelunasan cash)
  payroll_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_adv_emp (employee_id, status),
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- 3) KOMISI PENJUALAN ----------
CREATE TABLE IF NOT EXISTS commission_rules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NULL,         -- NULL = semua cabang
  category_id INT UNSIGNED NULL,         -- NULL = semua kategori
  pct DECIMAL(6,2) NOT NULL DEFAULT 0,   -- % dari omzet transaksi
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tx_id BIGINT UNSIGNED NOT NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NULL,         -- link ke tabel employees (via nama) bila ada
  business_id INT UNSIGNED NULL,
  base_amount DECIMAL(16,2) NOT NULL,
  pct DECIMAL(6,2) NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  period CHAR(7) NULL,
  payroll_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_comm_period (status, period),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- 4) REKONSILIASI KAS ----------
CREATE TABLE IF NOT EXISTS cash_reconciliations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  wallet_id INT UNSIGNED NOT NULL,
  recon_date DATE NOT NULL,
  book_balance DECIMAL(16,2) NOT NULL,
  actual_balance DECIMAL(16,2) NOT NULL,
  diff DECIMAL(16,2) NOT NULL,
  note VARCHAR(255) NULL,
  adjusted TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- 5) LIABILITAS: cicilan terjadwal ----------
ALTER TABLE liabilities
  ADD COLUMN tenor_months INT UNSIGNED NULL AFTER min_pay_pct,
  ADD COLUMN interest_pct DECIMAL(6,3) NULL AFTER tenor_months,
  ADD COLUMN installments_paid INT UNSIGNED NOT NULL DEFAULT 0 AFTER interest_pct;

-- ---------- 6) LOGIN: 2FA + throttle ----------
ALTER TABLE users ADD COLUMN twofa TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  username VARCHAR(60) NULL,
  ok TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_la_ip (ip, created_at),
  KEY idx_la_user (username, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- 7) STOCK MOVES: harga jual/beli tercatat per mutasi ----------
ALTER TABLE stock_moves ADD COLUMN unit_price DECIMAL(16,2) NULL AFTER qty;

-- ---------- 8) SEED SETTINGS BARU ----------
INSERT IGNORE INTO app_settings (k,v) VALUES
  ('wa_enabled','0'),
  ('wa_token',''),
  ('wa_target',''),
  ('wa_endpoint','https://api.fonnte.com/send'),
  ('qris_text',''),
  ('fx_auto','0'),
  ('cloud_backup_cmd',''),
  ('login_max_fail','5');
