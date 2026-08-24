-- UPGRADE 4: full dinamis
-- 1) Warna identitas utk tiap kategori (dipakai semua grafik)
ALTER TABLE categories ADD COLUMN color VARCHAR(16) NULL AFTER scope_hint;

UPDATE categories SET color='#f0bd68' WHERE id=1;
UPDATE categories SET color='#64c9b0' WHERE id=2;
UPDATE categories SET color='#78a8f2' WHERE id=3;
UPDATE categories SET color='#a0aab7' WHERE id=4;
UPDATE categories SET color='#f0bd68' WHERE id=5;
UPDATE categories SET color='#78a8f2' WHERE id=6;
UPDATE categories SET color='#a48af0' WHERE id=7;
UPDATE categories SET color='#ea91c4' WHERE id=8;
UPDATE categories SET color='#df6670' WHERE id=9;
UPDATE categories SET color='#5576c9' WHERE id=10;
UPDATE categories SET color='#66c6af' WHERE id=11;
UPDATE categories SET color='#8c75c9' WHERE id=12;
UPDATE categories SET color='#77bdd5' WHERE id=13;
UPDATE categories SET color='#a0aab7' WHERE id=14;

-- 2) Judul aplikasi bisa diubah owner (Pengaturan)
INSERT INTO app_settings (k,v) VALUES ('app_title','Dompet Owner')
ON DUPLICATE KEY UPDATE v=VALUES(v);
