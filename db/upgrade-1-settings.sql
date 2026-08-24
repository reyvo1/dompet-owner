-- Upgrade 1: settings terpusat (token bot, gemini, chat id, dll)
USE dompet_owner;
CREATE TABLE IF NOT EXISTS app_settings (
  k VARCHAR(60) PRIMARY KEY,
  v TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- seed kosong (diisi lewat halaman Settings)
INSERT IGNORE INTO app_settings (k,v) VALUES
  ('bot_token',''),
  ('bot_username',''),
  ('owner_chat_id',''),
  ('gemini_key',''),
  ('notify_transactions','1'),
  ('notify_bills','1');
