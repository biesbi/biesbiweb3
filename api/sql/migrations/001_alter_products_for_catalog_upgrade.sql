SET NAMES utf8mb4;

START TRANSACTION;

-- Slug is required for product detail and category-aware URLs
SET @has_slug := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'slug'
);
SET @sql := IF(
  @has_slug = 0,
  "ALTER TABLE products ADD COLUMN slug VARCHAR(255) NULL AFTER name",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Product condition: new / used
SET @has_product_condition := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'product_condition'
);
SET @sql := IF(
  @has_product_condition = 0,
  "ALTER TABLE products ADD COLUMN product_condition ENUM('new','used') NOT NULL DEFAULT 'new' AFTER pieces",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Old price
SET @has_old_price := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'old_price'
);
SET @sql := IF(
  @has_old_price = 0,
  "ALTER TABLE products ADD COLUMN old_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Desi
SET @has_desi := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'desi'
);
SET @sql := IF(
  @has_desi = 0,
  "ALTER TABLE products ADD COLUMN desi INT NOT NULL DEFAULT 1 AFTER dimensions",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Pieces should be nullable and hidden on storefront when null/0
SET @has_pieces := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'pieces'
);
SET @sql := IF(
  @has_pieces = 0,
  "ALTER TABLE products ADD COLUMN pieces INT NULL AFTER stock",
  "ALTER TABLE products MODIFY COLUMN pieces INT NULL"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE products
SET pieces = NULL
WHERE pieces IS NOT NULL AND pieces <= 0;

UPDATE products
SET slug = CONCAT('product-', id)
WHERE slug IS NULL OR TRIM(slug) = '';

-- Backfill new product condition from legacy condition_tag if present
SET @has_condition_tag := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'condition_tag'
);
SET @sql := IF(
  @has_condition_tag = 1,
  "UPDATE products
   SET product_condition = CASE
     WHEN condition_tag = 'mint' THEN 'new'
     ELSE 'used'
   END
   WHERE product_condition IS NULL OR product_condition = '' OR product_condition = 'new'",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Useful indexes for detail page + filtering
SET @has_slug_idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND INDEX_NAME = 'idx_products_slug'
);
SET @sql := IF(
  @has_slug_idx = 0,
  "ALTER TABLE products ADD INDEX idx_products_slug (slug)",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
