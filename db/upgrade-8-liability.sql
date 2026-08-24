-- UPGRADE 8: liability engine — kartu kredit / PayLater / pinjaman
-- Liabilitas = uang milik orang lain yang kita pakai (bukan kas owner).
-- Belanja pakai kartu kredit -> naikkan outstanding, BUKAN kurangi kas.
-- Bayar tagihan kartu -> baru kas berkurang.

CREATE TABLE IF NOT EXISTS liabilities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  kind ENUM('credit_card','paylater','loan') NOT NULL DEFAULT 'loan',
  limit_amount DECIMAL(16,2) NOT NULL DEFAULT 0,      -- 0 = tanpa limit (pinjaman)
  outstanding DECIMAL(16,2) NOT NULL DEFAULT 0,
  statement_day TINYINT UNSIGNED NULL,                -- tgl cetak tagihan (kartu kredit)
  due_day TINYINT UNSIGNED NULL,                      -- tgl jatuh tempo
  min_pay_pct DECIMAL(6,3) NOT NULL DEFAULT 10.000,   -- % minimum payment
  business_id INT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pemakaian liabilitas: belanja/bayar-cicilan per event
CREATE TABLE IF NOT EXISTS liability_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  liability_id INT UNSIGNED NOT NULL,
  tx_date DATE NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  amount DECIMAL(16,2) NOT NULL,
  direction ENUM('charge','payment') NOT NULL DEFAULT 'charge',
  tx_id BIGINT UNSIGNED NULL,                         -- transaksi pembayaran (kalau payment)
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (liability_id) REFERENCES liabilities(id) ON DELETE CASCADE,
  KEY idx_liab_date (liability_id, tx_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
