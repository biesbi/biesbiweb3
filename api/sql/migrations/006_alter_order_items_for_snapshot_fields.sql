SET NAMES utf8mb4;

START TRANSACTION;

SET @has_product_slug := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'order_items'
    AND COLUMN_NAME = 'product_slug'
);
SET @sql := IF(
  @has_product_slug = 0,
  "ALTER TABLE order_items ADD COLUMN product_slug VARCHAR(255) NULL AFTER product_name",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_product_image := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'order_items'
    AND COLUMN_NAME = 'product_image'
);
SET @sql := IF(
  @has_product_image = 0,
  "ALTER TABLE order_items ADD COLUMN product_image VARCHAR(1000) NULL AFTER product_slug",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_product_condition := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'order_items'
    AND COLUMN_NAME = 'product_condition'
);
SET @sql := IF(
  @has_product_condition = 0,
  "ALTER TABLE order_items ADD COLUMN product_condition ENUM('new','used') NULL AFTER product_image",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_pieces := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'order_items'
    AND COLUMN_NAME = 'pieces'
);
SET @sql := IF(
  @has_pieces = 0,
  "ALTER TABLE order_items ADD COLUMN pieces INT NULL AFTER quantity",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_category_name := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'order_items'
    AND COLUMN_NAME = 'category_name'
);
SET @sql := IF(
  @has_category_name = 0,
  "ALTER TABLE order_items ADD COLUMN category_name VARCHAR(120) NULL AFTER pieces",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_brand_name := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'order_items'
    AND COLUMN_NAME = 'brand_name'
);
SET @sql := IF(
  @has_brand_name = 0,
  "ALTER TABLE order_items ADD COLUMN brand_name VARCHAR(120) NULL AFTER category_name",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE order_items oi
LEFT JOIN products p ON p.id = oi.product_id
LEFT JOIN categories c ON c.id = p.category_id
LEFT JOIN brands b ON b.id = p.brand_id
SET
  oi.product_slug = COALESCE(oi.product_slug, p.slug),
  oi.product_condition = COALESCE(oi.product_condition, p.product_condition),
  oi.pieces = CASE
    WHEN oi.pieces IS NOT NULL THEN oi.pieces
    WHEN p.pieces IS NOT NULL AND p.pieces > 0 THEN p.pieces
    ELSE NULL
  END,
  oi.category_name = COALESCE(oi.category_name, c.name),
  oi.brand_name = COALESCE(oi.brand_name, b.name)
WHERE oi.product_id IS NOT NULL;

COMMIT;
