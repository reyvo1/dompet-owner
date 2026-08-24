-- Upgrade 3c: Seed Chart of Accounts standar Indonesia
USE dompet_owner;
INSERT INTO coa_accounts (code,name,type,normal_balance,is_cash,wallet_id) VALUES
  ('1-1000','Kas Utama','aset','debit',1,1),
  ('1-1100','Bank','aset','debit',1,2),
  ('1-1200','E-Wallet','aset','debit',1,3),
  ('1-1300','Piutang Usaha','aset','debit',0,NULL),
  ('1-1400','Persediaan Barang','aset','debit',0,NULL),
  ('2-1000','Utang Usaha','liabilitas','kredit',0,NULL),
  ('2-1100','Utang Pajak PBJT','liabilitas','kredit',0,NULL),
  ('2-1200','Utang Pajak PPh UMKM','liabilitas','kredit',0,NULL),
  ('3-1000','Modal Owner','ekuitas','kredit',0,NULL),
  ('3-1100','Prive Owner','ekuitas','debit',0,NULL),
  ('4-1000','Pendapatan Penjualan','pendapatan','kredit',0,NULL),
  ('4-2000','Pendapatan Lain-lain','pendapatan','kredit',0,NULL),
  ('5-1000','HPP (Harga Pokok Penjualan)','hpp','debit',0,NULL),
  ('6-1000','Beban Barang Usaha','beban','debit',0,NULL),
  ('6-1100','Beban Gaji Kariawan','beban','debit',0,NULL),
  ('6-1200','Beban Sewa Tempat','beban','debit',0,NULL),
  ('6-1300','Beban Operasional','beban','debit',0,NULL),
  ('6-9000','Beban Lain-lain','beban','debit',0,NULL)
ON DUPLICATE KEY UPDATE name=VALUES(name);
