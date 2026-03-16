SET NAMES utf8mb4;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS product_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id VARCHAR(36) NOT NULL,
  filename VARCHAR(255) NULL,
  storage_driver VARCHAR(30) NOT NULL DEFAULT 'local',
  url VARCHAR(1000) NOT NULL,
  mime_type VARCHAR(120) NULL,
  alt_text VARCHAR(255) NULL,
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  width INT UNSIGNED NULL,
  height INT UNSIGNED NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  uploaded_by VARCHAR(36) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_product_images_product (product_id),
  KEY idx_product_images_primary (product_id, is_primary),
  KEY idx_product_images_sort (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

SET @has_filename := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_images'
    AND COLUMN_NAME = 'filename'
);
SET @sql := IF(
  @has_filename = 0,
  "ALTER TABLE product_images ADD COLUMN filename VARCHAR(255) NULL AFTER product_id",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_storage_driver := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_images'
    AND COLUMN_NAME = 'storage_driver'
);
SET @sql := IF(
  @has_storage_driver = 0,
  "ALTER TABLE product_images ADD COLUMN storage_driver VARCHAR(30) NOT NULL DEFAULT 'local' AFTER filename",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mime_type := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_images'
    AND COLUMN_NAME = 'mime_type'
);
SET @sql := IF(
  @has_mime_type = 0,
  "ALTER TABLE product_images ADD COLUMN mime_type VARCHAR(120) NULL AFTER url",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_size_bytes := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_images'
    AND COLUMN_NAME = 'size_bytes'
);
SET @sql := IF(
  @has_size_bytes = 0,
  "ALTER TABLE product_images ADD COLUMN size_bytes INT UNSIGNED NOT NULL DEFAULT 0 AFTER alt_text",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_width := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_images'
    AND COLUMN_NAME = 'width'
);
SET @sql := IF(
  @has_width = 0,
  "ALTER TABLE product_images ADD COLUMN width INT UNSIGNED NULL AFTER size_bytes",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_height := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_images'
    AND COLUMN_NAME = 'height'
);
SET @sql := IF(
  @has_height = 0,
  "ALTER TABLE product_images ADD COLUMN height INT UNSIGNED NULL AFTER width",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_uploaded_by := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_images'
    AND COLUMN_NAME = 'uploaded_by'
);
SET @sql := IF(
  @has_uploaded_by = 0,
  "ALTER TABLE product_images ADD COLUMN uploaded_by VARCHAR(36) NULL AFTER sort_order",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_updated_at := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_images'
    AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(
  @has_updated_at = 0,
  "ALTER TABLE product_images ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
