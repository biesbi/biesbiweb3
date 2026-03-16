SET NAMES utf8mb4;

START TRANSACTION;

-- Pieces cleanup for storefront rule
UPDATE products
SET pieces = NULL
WHERE pieces IS NOT NULL AND pieces <= 0;

UPDATE order_items
SET pieces = NULL
WHERE pieces IS NOT NULL AND pieces <= 0;

-- If product has images but no cover, first image becomes cover
UPDATE product_images pi
JOIN (
  SELECT product_id, MIN(sort_order) AS min_sort_order
  FROM product_images
  GROUP BY product_id
) px ON px.product_id = pi.product_id AND px.min_sort_order = pi.sort_order
SET pi.is_primary = 1
WHERE NOT EXISTS (
  SELECT 1
  FROM product_images p2
  WHERE p2.product_id = pi.product_id
    AND p2.is_primary = 1
);

-- Optional backfill of order item product image from cover image
UPDATE order_items oi
JOIN (
  SELECT product_id, url
  FROM product_images
  WHERE is_primary = 1
) cover_image ON cover_image.product_id = oi.product_id
SET oi.product_image = COALESCE(oi.product_image, cover_image.url)
WHERE oi.product_id IS NOT NULL;

COMMIT;
