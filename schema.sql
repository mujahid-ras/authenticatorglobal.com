-- DRC portal schema. Run once in phpMyAdmin (select your database first).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS drc_customers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  country VARCHAR(100) NOT NULL,
  contact_person VARCHAR(100) NOT NULL,
  contact_number VARCHAR(30) NULL,
  address VARCHAR(255) NOT NULL,
  account_type ENUM('Manufacturer','Importer') NOT NULL,
  gln CHAR(13) NULL,
  status ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  activated_at DATETIME NULL,
  last_login DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drc_admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(60) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NULL,
  status ENUM('pending','active','disabled') NOT NULL DEFAULT 'active',
  invite_token CHAR(64) NULL,
  invite_expires DATETIME NULL,
  invited_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drc_lines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  line_name VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_line (customer_id, line_name),
  CONSTRAINT fk_lines_customer FOREIGN KEY (customer_id) REFERENCES drc_customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drc_partners (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  partner_type ENUM('Manufacturer','Importer') NOT NULL,
  name VARCHAR(150) NOT NULL,
  gln CHAR(13) NOT NULL,
  address VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_partner (customer_id, partner_type, gln),
  CONSTRAINT fk_partners_customer FOREIGN KEY (customer_id) REFERENCES drc_customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drc_products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  product_name VARCHAR(150) NOT NULL,
  gtin_unit VARCHAR(14) NOT NULL,
  gtin_outer VARCHAR(14) NULL,
  gtin_master VARCHAR(14) NULL,
  gtin_pallet VARCHAR(14) NULL,
  quantity BIGINT UNSIGNED NOT NULL,
  period ENUM('Monthly','Quarterly','Half-yearly','Yearly') NOT NULL DEFAULT 'Monthly',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_gtin (customer_id, gtin_unit),
  CONSTRAINT fk_products_customer FOREIGN KEY (customer_id) REFERENCES drc_customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drc_login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip VARCHAR(45) NOT NULL,
  login_key VARCHAR(190) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_key (login_key, attempted_at),
  KEY idx_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
