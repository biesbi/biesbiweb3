<?php

function productColumns(string $table, array $columns): array {
    return array_values(array_filter($columns, fn(string $column) => tableHasColumn($table, $column)));
}

function productRequestIsAdmin(): bool {
    $authHeader = function_exists('authHeaderValue')
        ? authHeaderValue()
        : ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (!str_starts_with($authHeader, 'Bearer ')) {
        return false;
    }

    $payload = jwtDecode(substr($authHeader, 7));
    return is_array($payload) && (($payload['role'] ?? '') === 'admin');
}

function productNullableForeignKey(mixed $value): ?string {
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function productResolveForeignKey(string $table, mixed $value): ?string {
    $value = productNullableForeignKey($value);
    if ($value === null) {
        return null;
    }

    $stmt = db()->prepare("SELECT id FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([$value]);
    $row = $stmt->fetch();
    if ($row) {
        return (string) $row['id'];
    }

    $checks = [];
    if (tableHasColumn($table, 'slug')) {
        $checks[] = ['sql' => "SELECT id FROM {$table} WHERE slug = ? LIMIT 1", 'value' => slugify($value)];
    }
    if (tableHasColumn($table, 'name')) {
        $checks[] = ['sql' => "SELECT id FROM {$table} WHERE name = ? LIMIT 1", 'value' => $value];
    }

    foreach ($checks as $check) {
        $stmt = db()->prepare($check['sql']);
        $stmt->execute([$check['value']]);
        $row = $stmt->fetch();
        if ($row) {
            return (string) $row['id'];
        }
    }

    return $value;
}

function productPayloadValue(string $column, string $slug, mixed $current = null): mixed {
    $requestBody = body();
    $imagesInput = input('images', input('imageList', $current ?? []));
    $imgInput = input('img', input('image', $current));
    $hasExplicitImage = array_key_exists('img', $requestBody) || array_key_exists('image', $requestBody);

    $normalizeInlineImage = static function (mixed $value): mixed {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return $value;
    };

    $imgInput = $normalizeInlineImage($imgInput);
    $imagesInput = array_values(array_filter(array_map($normalizeInlineImage, normalizeImages($imagesInput))));

    if ($hasExplicitImage && is_string($imgInput) && $imgInput !== '') {
        $imagesInput = [$imgInput];
    } elseif (is_string($imgInput) && $imgInput !== '' && empty(normalizeImages($imagesInput))) {
        $imagesInput = [$imgInput];
    }

    return match ($column) {
        'slug' => $slug,
        'images' => json_encode(normalizeImages($imagesInput), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'img' => $imgInput,
        'desi' => (int) input('desi', $current ?? 1),
        'pieces' => ProductCatalogService::normalizePieces(input('pieces', $current ?? 0)),
        'product_condition' => ProductCatalogService::normalizeCondition(input('product_condition', input('productCondition', $current ?? 'new'))),
        'price' => (float) input('price', $current ?? 0),
        'old_price' => (float) input('old_price', input('oldPrice', $current ?? 0)),
        'stock' => (int) input('stock', $current ?? 0),
        'is_active' => (int) input('is_active', input('isActive', $current ?? 1)),
        'category_id' => productNullableForeignKey(input('category_id', input('categoryId', $current))),
        'brand_id' => productNullableForeignKey(input('brand_id', input('brandId', $current))),
        default => input($column, input(match ($column) {
            'old_price' => 'oldPrice',
            'category_id' => 'categoryId',
            'brand_id' => 'brandId',
            'is_active' => 'isActive',
            default => $column,
        }, $current)),
    };
}

function productSelectSql(string $whereSql): string {
    $categorySlugSelect = tableHasColumn('categories', 'slug')
        ? 'c.slug AS category_slug'
        : 'NULL AS category_slug';
    $brandSlugSelect = tableHasColumn('brands', 'slug')
        ? 'b.slug AS brand_slug'
        : 'NULL AS brand_slug';

    return 'SELECT p.*, c.name AS category_name, ' . $categorySlugSelect . ', b.name AS brand_name, ' . $brandSlugSelect . '
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN brands b ON b.id = p.brand_id
            WHERE ' . $whereSql;
}

if ($id === 'categories') {
    $categorySelect = productColumns('categories', ['id', 'name', 'slug', 'parent_id', 'sort_order']);

    switch (true) {
        case $method === 'GET' && $sub === 'list':
            $orderBy = tableHasColumn('categories', 'sort_order') ? 'sort_order, name' : 'name';
            $rows = db()->query(
                'SELECT ' . implode(', ', $categorySelect) . " FROM categories ORDER BY $orderBy"
            )->fetchAll();
            ok($rows);

        case $method === 'POST' && $sub === null:
            adminRequired();
            $name = trim((string) input('name', ''));
            if ($name === '') error('Kategori adı gerekli.');

            $slug = slugify($name);
            $insertColumns = ['id', 'name', 'slug'];
            $insertValues = [
                'cat-' . bin2hex(random_bytes(6)),
                $name,
                $slug,
            ];

            if (tableHasColumn('categories', 'parent_id')) {
                $insertColumns[] = 'parent_id';
                $insertValues[] = input('parent_id');
            }
            if (tableHasColumn('categories', 'sort_order')) {
                $insertColumns[] = 'sort_order';
                $insertValues[] = (int) input('sort_order', 0);
            }

            $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
            db()->prepare(
                'INSERT INTO categories (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')'
            )->execute($insertValues);

            ok(['id' => $insertValues[0], 'name' => $name, 'slug' => $slug]);

        case $method === 'DELETE' && $sub !== null:
            adminRequired();
            $categoryId = (string) $sub;
            $stmt = db()->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->execute([$categoryId]);
            if ($stmt->rowCount() === 0) {
                error('Kategori bulunamadı.', 404);
            }
            ok(['success' => true]);

        default:
            error('Kategori endpoint bulunamadı.', 404);
    }
}

if ($id === 'brands') {
    switch (true) {
        case $method === 'GET' && $sub === 'list':
            $rows = db()->query('SELECT id, name, slug, logo_url FROM brands ORDER BY name')->fetchAll();
            ok($rows);

        case $method === 'POST' && $sub === null:
            adminRequired();
            $name = trim((string) input('name', ''));
            if ($name === '') error('Marka adı gerekli.');

            $slug = slugify($name);
            $brandId = 'brand-' . bin2hex(random_bytes(6));
            db()->prepare('INSERT INTO brands (id, name, slug, logo_url) VALUES (?,?,?,?)')
                ->execute([$brandId, $name, $slug, input('logo_url')]);
            ok(['id' => $brandId, 'name' => $name, 'slug' => $slug]);

        case $method === 'DELETE' && $sub !== null:
            adminRequired();
            $brandId = (string) $sub;
            $stmt = db()->prepare('DELETE FROM brands WHERE id = ?');
            $stmt->execute([$brandId]);
            if ($stmt->rowCount() === 0) {
                error('Marka bulunamadı.', 404);
            }
            ok(['success' => true]);

        default:
            error('Marka endpoint bulunamadı.', 404);
    }
}

if ($id === null) {
    switch ($method) {
        case 'GET':
            $isAdmin = productRequestIsAdmin();
            $where = [];
            if (tableHasColumn('products', 'is_active') && !$isAdmin && empty($_GET['include_inactive'])) {
                $where[] = 'p.is_active = 1';
            }
            if ($where === []) {
                $where[] = '1=1';
            }
            $params = [];

            if (!empty($_GET['category'])) {
                $categoryFilter = trim($_GET['category']);
                $where[] = '(p.category_id IS NOT NULL AND (c.slug = ? OR c.id = ? OR c.name = ? OR p.category_id = ?))';
                $params[] = $categoryFilter;
                $params[] = $categoryFilter;
                $params[] = $categoryFilter;
                $params[] = $categoryFilter;
            }
            if (!empty($_GET['brand'])) {
                $brandFilter = trim($_GET['brand']);
                $where[] = '(p.brand_id IS NOT NULL AND (b.slug = ? OR b.id = ? OR b.name = ? OR p.brand_id = ?))';
                $params[] = $brandFilter;
                $params[] = $brandFilter;
                $params[] = $brandFilter;
                $params[] = $brandFilter;
            }
            if (!empty($_GET['search'])) {
                $searchTerm = trim($_GET['search']);
                $searchable = ['p.name LIKE ?'];

                // Ürün açıklamasında ara
                if (tableHasColumn('products', 'description')) {
                    $searchable[] = 'p.description LIKE ?';
                }

                // Set numarasında ara
                if (tableHasColumn('products', 'set_no')) {
                    $searchable[] = 'p.set_no LIKE ?';
                }

                // SKU'da ara
                if (tableHasColumn('products', 'sku')) {
                    $searchable[] = 'p.sku LIKE ?';
                }

                // Kategori adında ara
                $searchable[] = 'c.name LIKE ?';

                // Marka adında ara
                $searchable[] = 'b.name LIKE ?';

                $like = '%' . $searchTerm . '%';
                $where[] = '(' . implode(' OR ', $searchable) . ')';
                foreach ($searchable as $ignored) {
                    $params[] = $like;
                }
            }

            $page = max(1, (int) ($_GET['page'] ?? 1));
            $limitParam = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;
            $limit = $limitParam > 0 ? max(1, min(250, $limitParam)) : 0;
            $offset = $limit > 0 ? ($page - 1) * $limit : 0;
            $summary = !empty($_GET['summary']);
            $queryLimit = $limit > 0 ? $limit + 1 : 0;
            $limitSql = $queryLimit > 0 ? " LIMIT $queryLimit OFFSET $offset" : '';

            $stmt = db()->prepare(
                productSelectSql(implode(' AND ', $where)) . "
                ORDER BY p.created_at DESC{$limitSql}"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            $hasMore = false;
            if ($limit > 0 && count($rows) > $limit) {
                $hasMore = true;
                $rows = array_slice($rows, 0, $limit);
            }

            if ($limit > 0) {
                header('X-Page: ' . $page);
                header('X-Limit: ' . $limit);
                header('X-Has-More: ' . ($hasMore ? '1' : '0'));
            }

            // PERFORMANS: Liste görünümünde gallery yükleme - sadece primary image
            $includeFullGallery = !empty($_GET['include_gallery']);
            $payload = ProductCatalogService::enrichProducts($rows, $includeFullGallery);

            if ($summary) {
                $payload = array_map(static function (array $product): array {
                    return [
                        'id' => $product['id'] ?? null,
                        'slug' => $product['slug'] ?? null,
                        'title' => $product['title'] ?? ($product['name'] ?? ''),
                        'name' => $product['name'] ?? ($product['title'] ?? ''),
                        'category' => $product['category'] ?? null,
                        'categorySlug' => $product['categorySlug'] ?? null,
                        'brand' => $product['brand'] ?? null,
                        'brandSlug' => $product['brandSlug'] ?? null,
                        'stock' => $product['stock'] ?? 0,
                        'in_stock' => $product['in_stock'] ?? false,
                        'isActive' => $product['isActive'] ?? true,
                        'is_active' => $product['is_active'] ?? 1,
                    ];
                }, $payload);
            }

            ok($payload);

        case 'POST':
            adminRequired();
            $name = trim((string) input('name', ''));
            if ($name === '') error('Ürün adı gerekli.');
            if ((float) input('price', 0) < 0) error('Fiyat negatif olamaz.');
            if ((int) input('stock', 0) < 0) error('Stok negatif olamaz.');
            if ((int) input('desi', 1) <= 0) error('Desi 0\'dan büyük olmalı.');

            try {
                $productId = 'prd-' . bin2hex(random_bytes(6));
                $slug = uniqueSlug('products', $name);
                $insertColumns = ['id', 'category_id', 'brand_id', 'name', 'description', 'price', 'stock'];
                $insertValues = [
                    $productId,
                    productResolveForeignKey('categories', input('category_id', input('categoryId'))),
                    productResolveForeignKey('brands', input('brand_id', input('brandId'))),
                    $name,
                    input('description'),
                    (float) input('price', 0),
                    (int) input('stock', 0),
                ];

                foreach (['slug', 'sku', 'set_no', 'condition_tag', 'product_condition', 'desi', 'pieces', 'old_price', 'is_active'] as $column) {
                    if (!tableHasColumn('products', $column)) {
                        continue;
                    }

                    $insertColumns[] = $column;
                    $insertValues[] = productPayloadValue($column, $slug);
                }

                // img ve images alanlarını sadece request body'de açıkça verilmişse ekle
                // Aksi halde NULL bırak, UploadService.syncProductImageFields tarafından set edilecek
                foreach (['images', 'img'] as $column) {
                    if (!tableHasColumn('products', $column)) {
                        continue;
                    }
                    $requestBody = body();
                    $hasExplicitValue = array_key_exists($column, $requestBody)
                        || array_key_exists($column === 'img' ? 'image' : 'imageList', $requestBody);
                    if ($hasExplicitValue) {
                        $insertColumns[] = $column;
                        $insertValues[] = productPayloadValue($column, $slug);
                    }
                }

                $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
                db()->prepare(
                    'INSERT INTO products (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')'
                )->execute($insertValues);

                $stmt = db()->prepare(productSelectSql('p.id = ?'));
                $stmt->execute([$productId]);
                ok(legacyProduct($stmt->fetch() ?: ['id' => $productId, 'name' => $name]));
            } catch (Throwable $e) {
                AuditLog::write(AuditLog::PRODUCT_CREATE, null, 'product', null, [
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                    'post_keys' => array_keys($_POST),
                    'body_keys' => array_keys(body()),
                    'file_keys' => array_keys($_FILES),
                    'category_input' => input('category_id', input('categoryId')),
                    'brand_input' => input('brand_id', input('brandId')),
                    'name' => $name,
                ]);
                throw $e;
            }

        default:
            error('Method not allowed.', 405);
    }
}

if ($id !== null && $sub === null) {
    $productId = (string) $id;

    switch ($method) {
        case 'GET':
            $whereSql = tableHasColumn('products', 'slug')
                ? 'p.id = ? OR p.slug = ?'
                : 'p.id = ?';
            $stmt = db()->prepare(productSelectSql($whereSql) . ' LIMIT 1');
            $stmt->execute(tableHasColumn('products', 'slug') ? [$productId, $productId] : [$productId]);
            $product = $stmt->fetch();
            if (!$product) error('Ürün bulunamadı.', 404);
            ok(legacyProduct($product));

        case 'PATCH':
        case 'PUT':
            adminRequired();
            $find = db()->prepare('SELECT * FROM products WHERE id COLLATE utf8mb4_turkish_ci = ? LIMIT 1');
            $find->execute([$productId]);
            $current = $find->fetch();
            if (!$current) error('Ürün bulunamadı.', 404);

            $fields = [];
            $values = [];
            $name = trim((string) input('name', (string) ($current['name'] ?? '')));
            $slug = uniqueSlug('products', $name, $productId);
            if ((float) input('price', $current['price'] ?? 0) < 0) error('Fiyat negatif olamaz.');
            if ((int) input('stock', $current['stock'] ?? 0) < 0) error('Stok negatif olamaz.');

            foreach (['category_id', 'brand_id', 'name', 'description', 'price', 'old_price', 'stock', 'images', 'img', 'desi', 'pieces', 'product_condition', 'is_active'] as $column) {
                if (!tableHasColumn('products', $column)) {
                    continue;
                }
                $aliases = match ($column) {
                    'category_id' => ['category_id', 'categoryId'],
                    'brand_id' => ['brand_id', 'brandId'],
                    'old_price' => ['old_price', 'oldPrice'],
                    'is_active' => ['is_active', 'isActive'],
                    'product_condition' => ['product_condition', 'productCondition'],
                    'images' => ['images', 'imageList', 'img', 'image'],
                    'img' => ['img', 'image'],
                    default => [$column],
                };
                $hasValue = false;
                foreach ($aliases as $alias) {
                    if (array_key_exists($alias, body())) {
                        $hasValue = true;
                        break;
                    }
                }
                if (!$hasValue && !($column === 'name' && array_key_exists('name', body()))) {
                    continue;
                }

                $fields[] = $column . ' = ?';
                $values[] = productPayloadValue($column, $slug, $current[$column] ?? null);
            }

            if (tableHasColumn('products', 'slug') && array_key_exists('name', body())) {
                $fields[] = 'slug = ?';
                $values[] = $slug;
            }

            if ($fields === []) {
                error('Güncellenecek alan yok.');
            }

            $values[] = $productId;
            db()->prepare('UPDATE products SET ' . implode(', ', $fields) . ' WHERE id COLLATE utf8mb4_turkish_ci = ?')->execute($values);

            $find->execute([$productId]);
            ok(legacyProduct($find->fetch() ?: $current));

        case 'DELETE':
            adminRequired();
            $stmt = db()->prepare('DELETE FROM products WHERE id COLLATE utf8mb4_turkish_ci = ?');
            $stmt->execute([$productId]);
            if ($stmt->rowCount() === 0) error('Ürün bulunamadı.', 404);
            ok(['success' => true]);

        default:
            error('Method not allowed.', 405);
    }
}

error('Ürün endpoint bulunamadı.', 404);
