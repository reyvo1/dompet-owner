-- UPGRADE 7: smart rules — auto-kategori dari kata kunci
CREATE TABLE IF NOT EXISTS smart_rules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pattern VARCHAR(160) NOT NULL,          -- kata kunci (cocokkan dgn LIKE %pattern%)
  category_id INT UNSIGNED NOT NULL,
  business_id INT UNSIGNED NULL,          -- NULL = berlaku semua bisnis/pribadi
  priority INT NOT NULL DEFAULT 100,      -- kecil = menang
  hits INT UNSIGNED NOT NULL DEFAULT 0,   -- statistik pemakaian
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pattern_biz (pattern, business_id),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed awal: belajar dari kategori bawaan
INSERT INTO smart_rules (pattern, category_id, priority) VALUES
  ('gas', 13, 100),
  ('sewa', 12, 100),
  ('gaji', 11, 100),
  ('listrik', 9, 100);
