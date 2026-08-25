-- UPGRADE 13: Profesional & berkelas
-- 1) role 'manajer' (approval + laporan cabang, tanpa akses owner-only)
ALTER TABLE users MODIFY role ENUM('owner','manajer','kariawan') NOT NULL DEFAULT 'kariawan';
-- 2) nomor referensi kwitansi tiap transaksi (DO-YYYYMMDD-XXXX)
ALTER TABLE transactions ADD COLUMN tx_no VARCHAR(32) NULL AFTER id, ADD UNIQUE KEY uk_tx_no (tx_no);
-- 3) audit trail diperkaya: nilai sebelum/sesudah
ALTER TABLE audit_log
  ADD COLUMN entity VARCHAR(40) NULL AFTER action,
  ADD COLUMN entity_id BIGINT UNSIGNED NULL AFTER entity,
  ADD COLUMN old_values TEXT NULL AFTER detail,
  ADD COLUMN new_values TEXT NULL AFTER old_values;
-- 4) counter nomor dokumen (kwitansi & transaksi)
CREATE TABLE IF NOT EXISTS doc_counters (
  name VARCHAR(30) NOT NULL PRIMARY KEY,
  seq BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;
INSERT IGNORE INTO doc_counters (name,seq) VALUES ('tx',0);
-- 5) isi tx_no untuk transaksi lama (biar rapi semua)
UPDATE transactions SET tx_no = CONCAT('DO-', DATE_FORMAT(tx_date,'%Y%m%d'), '-', LPAD(id,4,'0'))
WHERE tx_no IS NULL ORDER BY id;
