-- Upgrade 16: Kas kecil per cabang + kurs mata uang
USE dompet_owner;

CREATE TABLE IF NOT EXISTS petty_cash (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NULL,
  name VARCHAR(80) NOT NULL,
  custodian VARCHAR(120) NULL,               -- penanggung jawab
  fund DECIMAL(16,2) NOT NULL DEFAULT 0,     -- saldo dana saat ini
  replenish_to DECIMAL(16,2) NULL,           -- target imprest (opsional)
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (business_id) REFERENCES businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS petty_tx (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  petty_id INT UNSIGNED NOT NULL,
  tx_date DATE NOT NULL,
  direction ENUM('topup','spend','return') NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  description VARCHAR(255) NULL,
  category_id INT UNSIGNED NULL,
  receipt_no VARCHAR(60) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (petty_id) REFERENCES petty_cash(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- kurs mata uang terhadap IDR
CREATE TABLE IF NOT EXISTS fx_rates (
  code CHAR(3) PRIMARY KEY,                  -- USD, SGD, ...
  name VARCHAR(40) NOT NULL,
  rate DECIMAL(16,4) NOT NULL DEFAULT 1,     -- 1 unit = rate IDR
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO fx_rates (code,name,rate) VALUES ('IDR','Rupiah',1);
