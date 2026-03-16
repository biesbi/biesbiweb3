SET NAMES utf8mb4;

START TRANSACTION;

-- 1) Single legacy image field
INSERT INTO product_images (
  product_id,
  url,
  alt_text,
  is_primary,
  sort_order
)
SELECT
  p.id,
  TRIM(p.img),
  p.name,
  1,
  0
FROM products p
WHERE p.img IS NOT NULL
  AND TRIM(p.img) <> ''
  AND NOT EXISTS (
    SELECT 1
    FROM product_images pi
    WHERE pi.product_id = p.id
      AND pi.url = TRIM(p.img)
  );

DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_legacy_product_images $$
CREATE PROCEDURE migrate_legacy_product_images()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_product_id VARCHAR(36);
  DECLARE v_product_name VARCHAR(500);
  DECLARE v_images LONGTEXT;
  DECLARE v_index INT DEFAULT 0;
  DECLARE v_length INT DEFAULT 0;
  DECLARE v_url VARCHAR(1000);

  DECLARE image_cursor CURSOR FOR
    SELECT id, name, images
    FROM products
    WHERE images IS NOT NULL
      AND TRIM(images) <> ''
      AND JSON_VALID(images) = 1;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  OPEN image_cursor;

  read_loop: LOOP
    FETCH image_cursor INTO v_product_id, v_product_name, v_images;
    IF done = 1 THEN
      LEAVE read_loop;
    END IF;

    SET v_index = 0;
    SET v_length = COALESCE(JSON_LENGTH(v_images), 0);

    WHILE v_index < v_length DO
      SET v_url = JSON_UNQUOTE(JSON_EXTRACT(v_images, CONCAT('$[', v_index, ']')));

      IF v_url IS NOT NULL AND TRIM(v_url) <> '' THEN
        INSERT INTO product_images (
          product_id,
          url,
          alt_text,
          is_primary,
          sort_order
        )
        SELECT
          v_product_id,
          TRIM(v_url),
          v_product_name,
          CASE
            WHEN v_index = 0 AND NOT EXISTS (
              SELECT 1 FROM product_images px
              WHERE px.product_id = v_product_id
                AND px.is_primary = 1
            ) THEN 1
            ELSE 0
          END,
          v_index
        FROM DUAL
        WHERE NOT EXISTS (
          SELECT 1
          FROM product_images pi
          WHERE pi.product_id = v_product_id
            AND pi.url = TRIM(v_url)
        );
      END IF;

      SET v_index = v_index + 1;
    END WHILE;
  END LOOP;

  CLOSE image_cursor;
END $$

CALL migrate_legacy_product_images() $$
DROP PROCEDURE IF EXISTS migrate_legacy_product_images $$

DELIMITER ;

-- 3) Guarantee a single cover image when images exist
UPDATE product_images pi
JOIN (
  SELECT product_id, MIN(id) AS primary_id
  FROM product_images
  GROUP BY product_id
) first_image ON first_image.product_id = pi.product_id
SET pi.is_primary = CASE WHEN pi.id = first_image.primary_id THEN 1 ELSE 0 END
WHERE NOT EXISTS (
  SELECT 1
  FROM product_images px
  WHERE px.product_id = pi.product_id
    AND px.is_primary = 1
);

COMMIT;
