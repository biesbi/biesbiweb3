SET NAMES utf8mb4;

START TRANSACTION;

SET @has_shipping_total_desi := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'shipping_total_desi'
);
SET @sql := IF(
  @has_shipping_total_desi = 0,
  "ALTER TABLE orders ADD COLUMN shipping_total_desi INT NOT NULL DEFAULT 0 AFTER shipping_cost",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_free_shipping := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'free_shipping_applied'
);
SET @sql := IF(
  @has_free_shipping = 0,
  "ALTER TABLE orders ADD COLUMN free_shipping_applied TINYINT(1) NOT NULL DEFAULT 0 AFTER shipping_total_desi",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_payment_provider := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'payment_provider'
);
SET @sql := IF(
  @has_payment_provider = 0,
  "ALTER TABLE orders ADD COLUMN payment_provider VARCHAR(50) NULL AFTER payment_method",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_payment_reference := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'payment_reference'
);
SET @sql := IF(
  @has_payment_reference = 0,
  "ALTER TABLE orders ADD COLUMN payment_reference VARCHAR(120) NULL AFTER payment_provider",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_currency := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'currency'
);
SET @sql := IF(
  @has_currency = 0,
  "ALTER TABLE orders ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'TRY' AFTER total",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_paid_at := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'paid_at'
);
SET @sql := IF(
  @has_paid_at = 0,
  "ALTER TABLE orders ADD COLUMN paid_at DATETIME NULL AFTER payment_status",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_failed_at := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'failed_at'
);
SET @sql := IF(
  @has_failed_at = 0,
  "ALTER TABLE orders ADD COLUMN failed_at DATETIME NULL AFTER paid_at",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_shipping_rule_snapshot := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'shipping_rule_snapshot'
);
SET @sql := IF(
  @has_shipping_rule_snapshot = 0,
  "ALTER TABLE orders ADD COLUMN shipping_rule_snapshot JSON NULL AFTER free_shipping_applied",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
