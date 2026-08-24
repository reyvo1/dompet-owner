-- UPGRADE 5: multi-profil bisnis
-- Bisnis = level paling atas; cabang tetap dipisah lewat entri bisnis lain
-- jika memang usaha berbeda. Tambahkan identitas visual + catatan.
ALTER TABLE businesses ADD COLUMN icon VARCHAR(8) NULL AFTER name;
ALTER TABLE businesses ADD COLUMN color VARCHAR(16) NULL AFTER icon;
ALTER TABLE businesses ADD COLUMN note VARCHAR(255) NULL AFTER color;

UPDATE businesses SET icon='🏪', color='#155eef' WHERE id=1;
UPDATE businesses SET icon='🏪', color='#16836f' WHERE id=2;
UPDATE businesses SET icon='☕', color='#b86b00' WHERE id=3;
