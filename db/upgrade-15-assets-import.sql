-- Upgrade 15: Aset tetap & penyusutan garis lurus
USE dompet_owner;

CREATE TABLE IF NOT EXISTS fixed_assets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  acquired_at DATE NOT NULL,
  cost DECIMAL(16,2) NOT NULL,
  salvage DECIMAL(16,2) NOT NULL DEFAULT 0,      -- nilai sisa akhir
  life_months SMALLINT UNSIGNED NOT NULL DEFAULT 48,
  dep_account VARCHAR(20) NOT NULL DEFAULT '6-6000',   -- beban penyusutan
  asset_account VARCHAR(20) NOT NULL DEFAULT '1-1500', -- akumulasi penyusutan
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (business_id) REFERENCES businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asset_deps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  asset_id INT UNSIGNED NOT NULL,
  period CHAR(7) NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  entry_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_asset_period (asset_id, period),
  FOREIGN KEY (asset_id) REFERENCES fixed_assets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- riwayat import CSV mutasi bank
CREATE TABLE IF NOT EXISTS import_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(160) NULL,
  total_rows INT NOT NULL DEFAULT 0,
  ok_rows INT NOT NULL DEFAULT 0,
  skip_rows INT NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
