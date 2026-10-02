-- Run ONCE in phpMyAdmin if you already imported the first schema.sql.
-- (New installs do not need this; schema.sql already includes it.)

ALTER TABLE drc_admins
  MODIFY password_hash VARCHAR(255) NULL,
  ADD COLUMN status ENUM('pending','active','disabled') NOT NULL DEFAULT 'active' AFTER password_hash,
  ADD COLUMN invite_token CHAR(64) NULL AFTER status,
  ADD COLUMN invite_expires DATETIME NULL AFTER invite_token,
  ADD COLUMN invited_by INT UNSIGNED NULL AFTER invite_expires;

ALTER TABLE drc_customers
  MODIFY contact_number VARCHAR(30) NULL;
