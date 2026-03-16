SET NAMES utf8mb4;

START TRANSACTION;

SET @has_parent_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'categories'
    AND COLUMN_NAME = 'parent_id'
);
SET @sql := IF(
  @has_parent_id = 0,
  "ALTER TABLE categories ADD COLUMN parent_id VARCHAR(36) NULL AFTER slug",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_sort_order := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'categories'
    AND COLUMN_NAME = 'sort_order'
);
SET @sql := IF(
  @has_sort_order = 0,
  "ALTER TABLE categories ADD COLUMN sort_order SMALLINT NOT NULL DEFAULT 0 AFTER parent_id",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_parent_idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'categories'
    AND INDEX_NAME = 'idx_categories_parent'
);
SET @sql := IF(
  @has_parent_idx = 0,
  "ALTER TABLE categories ADD INDEX idx_categories_parent (parent_id)",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_category_idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND INDEX_NAME = 'idx_products_category_id'
);
SET @sql := IF(
  @has_category_idx = 0,
  "ALTER TABLE products ADD INDEX idx_products_category_id (category_id)",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_brand_idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND INDEX_NAME = 'idx_products_brand_id'
);
SET @sql := IF(
  @has_brand_idx = 0,
  "ALTER TABLE products ADD INDEX idx_products_brand_id (brand_id)",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
