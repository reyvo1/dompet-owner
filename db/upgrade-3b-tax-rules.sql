-- Upgrade 3b: Tax Rules (ala TAMASYA PBJT rule matching)
USE dompet_owner;
CREATE TABLE IF NOT EXISTS tax_rules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  tax_type ENUM('pbjt','pph_umkm','non_pajak') NOT NULL DEFAULT 'non_pajak',
  rate_pct DECIMAL(6,3) NOT NULL DEFAULT 0,   -- 10 = 10%
  business_id INT UNSIGNED NULL,              -- NULL = semua cabang
  category_name VARCHAR(80) NULL,             -- cocokkan nama kategori (LIKE %x%), NULL = semua
  tx_kind ENUM('masuk','keluar') NOT NULL DEFAULT 'masuk',
  valid_from DATE NULL,
  valid_to DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (business_id) REFERENCES businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- seed contoh (Rey bisa ubah via UI Pengaturan > Pajak)
INSERT INTO tax_rules (name,tax_type,rate_pct,tx_kind) VALUES
  ('Non-pajak default','non_pajak',0,'masuk'),
  ('PPh UMKM 0.5% omzet','pph_umkm',0.5,'masuk');
