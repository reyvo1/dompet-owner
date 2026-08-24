-- Upgrade 2: source transaksi kariawan via web
USE dompet_owner;
ALTER TABLE transactions MODIFY source ENUM('owner','bot_kariawan','kariawan_web') NOT NULL DEFAULT 'owner';
