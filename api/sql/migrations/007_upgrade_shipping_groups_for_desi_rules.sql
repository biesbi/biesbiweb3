SET NAMES utf8mb4;

START TRANSACTION;

SET @has_min_desi := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'shipping_groups'
    AND COLUMN_NAME = 'min_desi'
);
SET @sql := IF(
  @has_min_desi = 0,
  "ALTER TABLE shipping_groups ADD COLUMN min_desi INT UNSIGNED NOT NULL DEFAULT 1 AFTER carrier",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_max_desi := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'shipping_groups'
    AND COLUMN_NAME = 'max_desi'
);
SET @sql := IF(
  @has_max_desi = 0,
  "ALTER TABLE shipping_groups ADD COLUMN max_desi INT UNSIGNED NULL AFTER min_desi",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_sort_order := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'shipping_groups'
    AND COLUMN_NAME = 'sort_order'
);
SET @sql := IF(
  @has_sort_order = 0,
  "ALTER TABLE shipping_groups ADD COLUMN sort_order SMALLINT NOT NULL DEFAULT 0 AFTER free_above",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE shipping_groups
SET min_desi = 1, max_desi = 2, sort_order = 10, free_above = COALESCE(free_above, 2500.00)
WHERE carrier = 'DHL Express' AND name LIKE '%1-2%';

UPDATE shipping_groups
SET min_desi = 3, max_desi = 5, sort_order = 20, free_above = COALESCE(free_above, 2500.00)
WHERE carrier = 'DHL Express' AND name LIKE '%3-5%';

UPDATE shipping_groups
SET min_desi = 6, max_desi = 9, sort_order = 30, free_above = COALESCE(free_above, 2500.00)
WHERE carrier = 'DHL Express' AND name LIKE '%6-9%';

UPDATE shipping_groups
SET min_desi = 10, max_desi = 14, sort_order = 40, free_above = COALESCE(free_above, 2500.00)
WHERE carrier = 'DHL Express' AND name LIKE '%10-14%';

UPDATE shipping_groups
SET min_desi = 15, max_desi = NULL, sort_order = 50, free_above = COALESCE(free_above, 2500.00)
WHERE carrier = 'DHL Express' AND name LIKE '%15+%';

INSERT INTO shipping_groups (name, carrier, min_desi, max_desi, base_fee, free_above, sort_order, is_active)
SELECT seed.name, seed.carrier, seed.min_desi, seed.max_desi, seed.base_fee, seed.free_above, seed.sort_order, seed.is_active
FROM (
  SELECT
    'DHL Express 1-2 Desi' AS name,
    'DHL Express' AS carrier,
    1 AS min_desi,
    2 AS max_desi,
    79.90 AS base_fee,
    2500.00 AS free_above,
    10 AS sort_order,
    1 AS is_active
) AS seed
WHERE NOT EXISTS (
  SELECT 1 FROM shipping_groups WHERE carrier = 'DHL Express' AND min_desi = 1 AND max_desi = 2
);

INSERT INTO shipping_groups (name, carrier, min_desi, max_desi, base_fee, free_above, sort_order, is_active)
SELECT seed.name, seed.carrier, seed.min_desi, seed.max_desi, seed.base_fee, seed.free_above, seed.sort_order, seed.is_active
FROM (
  SELECT
    'DHL Express 3-5 Desi' AS name,
    'DHL Express' AS carrier,
    3 AS min_desi,
    5 AS max_desi,
    119.90 AS base_fee,
    2500.00 AS free_above,
    20 AS sort_order,
    1 AS is_active
) AS seed
WHERE NOT EXISTS (
  SELECT 1 FROM shipping_groups WHERE carrier = 'DHL Express' AND min_desi = 3 AND max_desi = 5
);

INSERT INTO shipping_groups (name, carrier, min_desi, max_desi, base_fee, free_above, sort_order, is_active)
SELECT seed.name, seed.carrier, seed.min_desi, seed.max_desi, seed.base_fee, seed.free_above, seed.sort_order, seed.is_active
FROM (
  SELECT
    'DHL Express 6-9 Desi' AS name,
    'DHL Express' AS carrier,
    6 AS min_desi,
    9 AS max_desi,
    159.90 AS base_fee,
    2500.00 AS free_above,
    30 AS sort_order,
    1 AS is_active
) AS seed
WHERE NOT EXISTS (
  SELECT 1 FROM shipping_groups WHERE carrier = 'DHL Express' AND min_desi = 6 AND max_desi = 9
);

INSERT INTO shipping_groups (name, carrier, min_desi, max_desi, base_fee, free_above, sort_order, is_active)
SELECT seed.name, seed.carrier, seed.min_desi, seed.max_desi, seed.base_fee, seed.free_above, seed.sort_order, seed.is_active
FROM (
  SELECT
    'DHL Express 10-14 Desi' AS name,
    'DHL Express' AS carrier,
    10 AS min_desi,
    14 AS max_desi,
    219.90 AS base_fee,
    2500.00 AS free_above,
    40 AS sort_order,
    1 AS is_active
) AS seed
WHERE NOT EXISTS (
  SELECT 1 FROM shipping_groups WHERE carrier = 'DHL Express' AND min_desi = 10 AND max_desi = 14
);

INSERT INTO shipping_groups (name, carrier, min_desi, max_desi, base_fee, free_above, sort_order, is_active)
SELECT seed.name, seed.carrier, seed.min_desi, seed.max_desi, seed.base_fee, seed.free_above, seed.sort_order, seed.is_active
FROM (
  SELECT
    'DHL Express 15+ Desi' AS name,
    'DHL Express' AS carrier,
    15 AS min_desi,
    NULL AS max_desi,
    279.90 AS base_fee,
    2500.00 AS free_above,
    50 AS sort_order,
    1 AS is_active
) AS seed
WHERE NOT EXISTS (
  SELECT 1 FROM shipping_groups WHERE carrier = 'DHL Express' AND min_desi = 15 AND max_desi IS NULL
);

COMMIT;
