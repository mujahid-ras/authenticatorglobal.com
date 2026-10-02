-- Run ONCE if you already created the tables with the earlier schema.sql
ALTER TABLE scan_logs
  MODIFY status ENUM('authentic','suspicious','fake','invalid') NOT NULL,
  ADD COLUMN country      VARCHAR(100) NULL AFTER accuracy_m,
  ADD COLUMN country_code CHAR(2)      NULL AFTER country,
  ADD KEY idx_country (country_code),
  ADD KEY idx_status (status);

-- Optional: re-classify old test scans with the new rules (Suspicious > 5, Fake > 10)
UPDATE scan_logs SET status='fake'       WHERE status IN ('authentic','suspicious') AND scan_number > 10;
UPDATE scan_logs SET status='suspicious' WHERE status = 'authentic'                 AND scan_number > 5;
