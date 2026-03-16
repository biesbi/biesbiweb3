SET NAMES utf8mb4;

ALTER TABLE product_images
  ADD COLUMN IF NOT EXISTS filename VARCHAR(255) NULL AFTER product_id,
  ADD COLUMN IF NOT EXISTS storage_driver VARCHAR(30) NOT NULL DEFAULT 'local' AFTER filename,
  ADD COLUMN IF NOT EXISTS mime_type VARCHAR(120) NULL AFTER url,
  ADD COLUMN IF NOT EXISTS size_bytes INT UNSIGNED NOT NULL DEFAULT 0 AFTER alt_text,
  ADD COLUMN IF NOT EXISTS width INT UNSIGNED NULL AFTER size_bytes,
  ADD COLUMN IF NOT EXISTS height INT UNSIGNED NULL AFTER width,
  ADD COLUMN IF NOT EXISTS uploaded_by VARCHAR(36) NULL AFTER sort_order,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE product_images
  ADD INDEX IF NOT EXISTS idx_product_images_primary (product_id, is_primary),
  ADD INDEX IF NOT EXISTS idx_product_images_sort (product_id, sort_order);

UPDATE product_images
SET filename = CASE
  WHEN filename IS NULL OR filename = '' THEN
    CASE
      WHEN LOCATE('/', url) > 0 THEN SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(url, '?', 1), '#', 1), '/', -1)
      ELSE url
    END
  ELSE filename
END;

UPDATE product_images
SET filename = REPLACE(filename, 'original_', '')
WHERE filename LIKE 'original_%';

UPDATE product_images
SET filename = REPLACE(filename, 'medium_', '')
WHERE filename LIKE 'medium_%';

UPDATE product_images
SET filename = REPLACE(filename, 'thumb_', '')
WHERE filename LIKE 'thumb_%';

UPDATE product_images
SET storage_driver = 'local'
WHERE storage_driver IS NULL OR storage_driver = '';

UPDATE product_images
SET mime_type = CASE
  WHEN mime_type IS NOT NULL AND mime_type <> '' THEN mime_type
  WHEN LOWER(filename) LIKE '%.jpg' OR LOWER(filename) LIKE '%.jpeg' THEN 'image/jpeg'
  WHEN LOWER(filename) LIKE '%.png' THEN 'image/png'
  WHEN LOWER(filename) LIKE '%.gif' THEN 'image/gif'
  WHEN LOWER(filename) LIKE '%.webp' THEN 'image/webp'
  ELSE mime_type
END
WHERE (mime_type IS NULL OR mime_type = '')
  AND filename IS NOT NULL
  AND filename <> '';
