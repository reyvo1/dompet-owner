-- ============================================================
-- DOMPET OWNER - Super-App Keuangan Owner + Multi Cabang Usaha
-- Konsep:
--  1. Owner punya SATU kas dompet pribadi.
--  2. Setiap transaksi (dari owner sendiri ATAU kariawan via bot)
--     selalu mempengaruhi arus kas dompet owner.
--  3. scope = 'pribadi'  -> catatan pengeluaran/pemasukan pribadi owner
--     scope = 'usaha'    -> catatan menyempil di cabang terkait
--                            (laporan per-cabang tetap terpisah)
--  4. Kariawan login via Telegram (user masing-masing per cabang).
-- Import: mysql -P3308 -uroot -p < schema.sql
-- ============================================================
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS dompet_owner CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dompet_owner;

-- Profil & kas owner
CREATE TABLE IF NOT EXISTS owner_profile (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  name VARCHAR(100) NOT NULL DEFAULT 'Owner',
  telegram_chat_id VARCHAR(64) NULL,           -- notif otomatis ke bos
  ai_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kas dompet (bisa beberapa: Cash, Bank, E-Wallet)
CREATE TABLE IF NOT EXISTS wallets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  kind ENUM('cash','bank','ewallet','other') NOT NULL DEFAULT 'cash',
  balance DECIMAL(16,2) NOT NULL DEFAULT 0,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cabang usaha
CREATE TABLE IF NOT EXISTS businesses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User: role owner / kariawan. Kariawan terhubung telegram + cabang.
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) NOT NULL,
  role ENUM('owner','kariawan') NOT NULL DEFAULT 'kariawan',
  business_id INT UNSIGNED NULL,
  telegram_username VARCHAR(80) NULL,
  telegram_verified TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kategori (global; dipakai lintas pribadi & usaha)
CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  kind ENUM('masuk','keluar','both') NOT NULL DEFAULT 'both',
  scope_hint ENUM('pribadi','usaha','both') NOT NULL DEFAULT 'both'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Transaksi inti. SEMUA transaksi mempengaruhi wallet owner.
CREATE TABLE IF NOT EXISTS transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tx_date DATE NOT NULL,
  type ENUM('masuk','keluar','transfer') NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  category_id INT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  -- DARI MANA asalnya:
  source ENUM('owner','bot_kariawan') NOT NULL DEFAULT 'owner',
  user_id INT UNSIGNED NULL,                   -- siapa yang input
  business_id INT UNSIGNED NULL,               -- NULL utk pribadi/transfer internal
  scope ENUM('pribadi','usaha') NOT NULL DEFAULT 'pribadi',
  wallet_id INT UNSIGNED NOT NULL,             -- kas dompet yang terdampak
  wallet_dest_id INT UNSIGNED NULL,            -- utk transfer antar kas
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tx_date (tx_date),
  INDEX idx_scope (scope),
  INDEX idx_business (business_id),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
  FOREIGN KEY (wallet_id) REFERENCES wallets(id),
  FOREIGN KEY (wallet_dest_id) REFERENCES wallets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Anggaran bulanan (per scope/cabang/kategori)
CREATE TABLE IF NOT EXISTS budgets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NULL,               -- NULL = pribadi owner
  category_id INT UNSIGNED NOT NULL,
  period CHAR(7) NOT NULL,                     -- YYYY-MM
  amount DECIMAL(16,2) NOT NULL,
  UNIQUE KEY uq_budget (business_id, category_id, period),
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Target tabungan / dana darurat owner
CREATE TABLE IF NOT EXISTS goals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  target_amount DECIMAL(16,2) NOT NULL,
  saved_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
  deadline DATE NULL,
  done TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tagihan rutin (autopay checklist bulanan)
CREATE TABLE IF NOT EXISTS bills (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  day_of_month TINYINT NOT NULL DEFAULT 1,
  business_id INT UNSIGNED NULL,               -- NULL = tagihan pribadi
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_paid_period CHAR(7) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Log chat bot (audit sederhana)
CREATE TABLE IF NOT EXISTS bot_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chat_id VARCHAR(64) NOT NULL,
  message TEXT,
  reply MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_botlog_chat (chat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Session web
CREATE TABLE IF NOT EXISTS app_sessions (
  token_hash CHAR(64) NOT NULL PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================= DATA AWAL =================
INSERT INTO owner_profile (id, name) VALUES (1, 'Owner')
  ON DUPLICATE KEY UPDATE name=name;

INSERT INTO wallets (name, kind, balance, is_default) VALUES
  ('Kas Utama', 'cash', 0, 1),
  ('Bank', 'bank', 0, 0),
  ('E-Wallet', 'ewallet', 0, 0);

INSERT INTO categories (name, kind, scope_hint) VALUES
  ('Gaji/Laba Usaha', 'masuk', 'pribadi'),
  ('Penjualan', 'masuk', 'usaha'),
  ('Modal/Setoran Owner', 'masuk', 'usaha'),
  ('Lain-lain Masuk', 'masuk', 'both'),
  ('Makan & Minum', 'keluar', 'pribadi'),
  ('Transport', 'keluar', 'pribadi'),
  ('Belanja Pribadi', 'keluar', 'pribadi'),
  ('Hiburan', 'keluar', 'pribadi'),
  ('Tagihan Rumah', 'keluar', 'pribadi'),
  ('Beli Barang Usaha', 'keluar', 'usaha'),
  ('Gaji Kariawan', 'keluar', 'usaha'),
  ('Sewa Tempat', 'keluar', 'usaha'),
  ('Operasional Cabang', 'keluar', 'usaha'),
  ('Lain-lain Keluar', 'keluar', 'both');

-- Contoh cabang (hapus/ubah sesuai usaha Rey)
INSERT INTO businesses (name) VALUES ('Cabang Contoh 1'), ('Cabang Contoh 2');

-- Akun owner web: rey/admin123 (hash digenerate PHP saat install bila kosong)
INSERT INTO users (username, password_hash, display_name, role)
VALUES ('rey', '$2y$10$eImiTXuWVxfM37uY4JANjQ=$DUMMY', 'Rey (Owner)', 'owner')
ON DUPLICATE KEY UPDATE display_name=display_name;
