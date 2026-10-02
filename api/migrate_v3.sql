-- Run ONCE on an existing database (after migrate_v2.sql).
-- 1) Package type on products (existing products become 'Pack' — change them in the dashboard if needed)
ALTER TABLE products
  ADD COLUMN package_type ENUM('Pack','Outer','Master','Pallet') NOT NULL DEFAULT 'Pack' AFTER gtin;

-- 2) Suspicious-result reports from the website (stored only, nothing is emailed)
CREATE TABLE IF NOT EXISTS suspicious_reports (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code_id    BIGINT UNSIGNED NULL,
  code       VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  scan_count INT UNSIGNED NULL,
  email      VARCHAR(255) NOT NULL,
  mobile     VARCHAR(30)  NOT NULL,
  country    VARCHAR(100) NOT NULL,
  shop_name  VARCHAR(255) NOT NULL,
  address    VARCHAR(500) NULL,
  remarks    TEXT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_code (code),
  KEY idx_created (created_at),
  KEY idx_ip (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
