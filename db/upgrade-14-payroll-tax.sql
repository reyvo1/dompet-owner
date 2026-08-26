-- Upgrade 14: Payroll (gaji staf) + rekap pajak bulanan
USE dompet_owner;

CREATE TABLE IF NOT EXISTS employees (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NULL,              -- NULL = pusat/pribadi
  user_id INT UNSIGNED NULL,                  -- opsional link ke akun login
  name VARCHAR(120) NOT NULL,
  position VARCHAR(80) NULL,
  base_salary DECIMAL(16,2) NOT NULL DEFAULT 0,
  pay_day TINYINT UNSIGNED NOT NULL DEFAULT 25,  -- tanggal gajian tiap bulan
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (business_id) REFERENCES businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payrolls (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  period CHAR(7) NOT NULL,                    -- YYYY-MM
  base_amount DECIMAL(16,2) NOT NULL,
  bonus_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
  deduction_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
  net_amount DECIMAL(16,2) NOT NULL,
  status ENUM('paid','pending') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  tx_id BIGINT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_emp_period (employee_id, period),
  FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rekap pajak bulanan per tipe (snapshot hasil tax engine + entri manual)
CREATE TABLE IF NOT EXISTS tax_monthly (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  period CHAR(7) NOT NULL,
  tax_type ENUM('pbjt','pph_umkm','pph21','non_pajak') NOT NULL DEFAULT 'non_pajak',
  amount DECIMAL(16,2) NOT NULL DEFAULT 0,
  due_date DATE NULL,
  status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  note VARCHAR(255) NULL,
  UNIQUE KEY uq_tax_period_type (period, tax_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
