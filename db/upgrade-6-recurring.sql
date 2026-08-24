-- UPGRADE 6: transaksi berulang (ala FinDompet recurring_transactions)
CREATE TABLE IF NOT EXISTS recurring_transactions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  amount DECIMAL(16,2) NOT NULL DEFAULT 0,
  type ENUM('masuk','keluar') NOT NULL DEFAULT 'keluar',
  category_id INT UNSIGNED NULL,
  business_id INT UNSIGNED NULL,               -- NULL = pribadi owner
  scope ENUM('pribadi','usaha') NOT NULL DEFAULT 'pribadi',
  wallet_id INT UNSIGNED NOT NULL,
  frequency ENUM('weekly','monthly','yearly') NOT NULL DEFAULT 'monthly',
  next_date DATE NOT NULL,
  anchor_day TINYINT UNSIGNED NULL,            -- utk bulan pendek: 31 -> akhir bulan
  active TINYINT(1) NOT NULL DEFAULT 1,
  auto_post TINYINT(1) NOT NULL DEFAULT 0,     -- 1 = cron posting otomatis
  last_processed_date DATE NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rec_due (active,auto_post,next_date),
  FOREIGN KEY (wallet_id) REFERENCES wallets(id),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
