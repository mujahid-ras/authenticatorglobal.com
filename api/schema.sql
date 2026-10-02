-- Authenticator Global — Verify feature schema (run once in phpMyAdmin)

CREATE TABLE IF NOT EXISTS products (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(255) NOT NULL,
  gtin       VARCHAR(14)  NOT NULL,
  package_type ENUM('Pack','Outer','Master','Pallet') NOT NULL DEFAULT 'Pack',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_gtin (gtin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_codes (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id       INT UNSIGNED NOT NULL,
  code             VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,  -- case-sensitive
  scan_count       INT UNSIGNED NOT NULL DEFAULT 0,
  first_scanned_at DATETIME NULL,
  last_scanned_at  DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_code (code),
  KEY idx_product (product_id),
  CONSTRAINT fk_code_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per verification attempt (valid or invalid) with GPS
CREATE TABLE IF NOT EXISTS scan_logs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code_id     BIGINT UNSIGNED NULL,            -- NULL when the code was not found
  code        VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,  -- case-sensitive
  status      ENUM('authentic','suspicious','fake','invalid') NOT NULL,
  scan_number INT UNSIGNED NULL,               -- scan count at the moment of this scan
  latitude    DECIMAL(9,6) NULL,
  longitude   DECIMAL(9,6) NULL,
  accuracy_m  INT UNSIGNED NULL,
  country      VARCHAR(100) NULL,
  country_code CHAR(2) NULL,
  ip_address  VARCHAR(45) NULL,
  user_agent  VARCHAR(255) NULL,
  scanned_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_code_id (code_id),
  KEY idx_code (code),
  KEY idx_scanned (scanned_at),
  KEY idx_country (country_code),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reports sent by users from the website when a result is "Suspicious" (stored only, nothing is emailed)
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
