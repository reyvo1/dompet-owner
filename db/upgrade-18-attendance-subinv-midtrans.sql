-- ============================================================
-- UPGRADE-18: absensi kariawan, kwitansi langganan, Midtrans
-- ============================================================
USE dompet_owner;

-- Absensi via bot: check-in/check-out, lembur dihitung dari durasi > 8 jam
CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  business_id INT NULL,
  work_date DATE NOT NULL,
  check_in DATETIME NULL,
  check_out DATETIME NULL,
  minutes INT NULL COMMENT 'total menit kerja hari itu',
  overtime_min INT NOT NULL DEFAULT 0 COMMENT 'menit lembur (>8 jam)',
  note VARCHAR(160) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_day (user_id, work_date),
  KEY idx_biz_date (business_id, work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kwitansi langganan (pelanggan tetap): otomatis dibuat tiap jatuh tempo
CREATE TABLE IF NOT EXISTS recurring_invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_id INT NULL,
  customer_name VARCHAR(120) NOT NULL,
  contact_id INT NULL,
  amount DECIMAL(14,2) NOT NULL,
  description VARCHAR(255) NULL,
  frequency ENUM('monthly','weekly') NOT NULL DEFAULT 'monthly',
  next_date DATE NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_created_id INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Midtrans Snap (QRIS dinamis + status otomatis)
ALTER TABLE invoices
  ADD COLUMN snap_token VARCHAR(80) NULL,
  ADD COLUMN snap_url VARCHAR(255) NULL,
  ADD COLUMN paid_via VARCHAR(20) NULL COMMENT 'cash | midtrans';

ALTER TABLE app_settings
  ADD UNIQUE KEY uk_setting (k);

-- Settings baru
INSERT INTO app_settings (k, v) VALUES
  ('midtrans_server_key', ''),
  ('midtrans_client_key', ''),
  ('subinv_notify', '1'),
  ('attendance_overtime_after', '480')
ON DUPLICATE KEY UPDATE k=k;
