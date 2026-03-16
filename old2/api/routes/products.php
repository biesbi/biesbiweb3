<?php

function productColumns(string $table, array $columns): array {
    return array_values(array_filter($columns, fn(string $column) => tableHasColumn($table, $column)));
}

function productRequestIsAdmin(): bool {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
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

function productPayloadValue(string $column, string $slug, mixed $current = null): mixed {
    $requestBody = body();
    $imagesInput = input('images', input('imageList', $current ?? []));
    $imgInput = input('img', input('image', $current));
    $hasExplicitImage = array_key_exists('img', $requestBody) || array_key_exists('image', $requestBody);

    if ($hasExplicitImage && is_string($imgInput) && $imgInput !== '') {
        $imagesInput = [$imgInput];
    } elseif (is_string($imgInput) && str_starts_with($imgInput, 'data:image/')) {
        $imagesInput = [$imgInput];
    } elseif (is_string($imgInput) && $imgInput !== '' && empty(normalizeImages($imagesInput))) {
        $imagesInput = [$imgInput];
    }

    return match ($column) {
        'slug' => $slug,
        'images' => json_encode(normalizeImages($imagesInput), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'img' => $imgInput,
        'desi' => (int) input('desi', $current ?? 1),
        'pieces' => (int) input('pieces', $current ?? 0),
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
            if ($name === '') error('Kategori adi gerekli.');

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
                error('Kategori bulunamadi.', 404);
            }
            ok(['success' => true]);

        default:
            error('Kategori endpoint bulunamadi.', 404);
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
            if ($name === '') error('Marka adi gerekli.');

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
                error('Marka bulunamadi.', 404);
            }
            ok(['success' => true]);

        default:
            error('Marka endpoint bulunamadi.', 404);
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
                $where[] = '(c.slug = ? OR c.id = ? OR c.name = ?)';
                $params[] = $_GET['category'];
                $params[] = $_GET['category'];
                $params[] = $_GET['category'];
            }
            if (!empty($_GET['brand'])) {
                $where[] = '(b.slug = ? OR b.id = ? OR b.name = ?)';
                $params[] = $_GET['brand'];
                $params[] = $_GET['brand'];
                $params[] = $_GET['brand'];
            }
            if (!empty($_GET['search'])) {
                $searchable = ['p.name LIKE ?'];
                if (tableHasColumn('products', 'set_no')) {
                    $searchable[] = 'p.set_no LIKE ?';
                }
                if (tableHasColumn('products', 'sku')) {
                    $searchable[] = 'p.sku LIKE ?';
                }

                $like = '%' . $_GET['search'] . '%';
                $where[] = '(' . implode(' OR ', $searchable) . ')';
                foreach ($searchable as $ignored) {
                    $params[] = $like;
                }
            }

            $page = max(1, (int) ($_GET['page'] ?? 1));
            $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            $stmt = db()->prepare(
                'SELECT p.*, c.name AS category_name, b.name AS brand_name
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 LEFT JOIN brands b ON b.id = p.brand_id
                 WHERE ' . implode(' AND ', $where) . "
                 ORDER BY p.created_at DESC
                 LIMIT $limit OFFSET $offset"
            );
            $stmt->execute($params);
            ok(array_map(fn(array $row) => legacyProduct($row), $stmt->fetchAll()));

        case 'POST':
            adminRequired();
            $name = trim((string) input('name', ''));
            if ($name === '') error('Urun adi gerekli.');

            $productId = 'prd-' . bin2hex(random_bytes(6));
            $slug = slugify($name);
            $insertColumns = ['id', 'category_id', 'brand_id', 'name', 'description', 'price', 'stock'];
            $insertValues = [
                $productId,
                productNullableForeignKey(input('category_id', input('categoryId'))),
                productNullableForeignKey(input('brand_id', input('brandId'))),
                $name,
                input('description'),
                (float) input('price', 0),
                (int) input('stock', 0),
            ];

            foreach (['slug', 'sku', 'set_no', 'condition_tag', 'images', 'img', 'desi', 'pieces', 'old_price', 'is_active'] as $column) {
                if (!tableHasColumn('products', $column)) {
                    continue;
                }

                $insertColumns[] = $column;
                $insertValues[] = productPayloadValue($column, $slug);
            }

            $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
            db()->prepare(
                'INSERT INTO products (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')'
            )->execute($insertValues);

            $stmt = db()->prepare(
                'SELECT p.*, c.name AS category_name, b.name AS brand_name
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 LEFT JOIN brands b ON b.id = p.brand_id
                 WHERE p.id = ?'
            );
            $stmt->execute([$productId]);
            ok(legacyProduct($stmt->fetch() ?: ['id' => $productId, 'name' => $name]));

        default:
            error('Method not allowed.', 405);
    }
}

if ($id !== null && $sub === null) {
    $productId = (string) $id;

    switch ($method) {
        case 'GET':
            $stmt = db()->prepare(
                'SELECT p.*, c.name AS category_name, b.name AS brand_name
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 LEFT JOIN brands b ON b.id = p.brand_id
                 WHERE p.id = ?'
            );
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if (!$product) error('Urun bulunamadi.', 404);
            ok(legacyProduct($product));

        case 'PATCH':
        case 'PUT':
            adminRequired();
            $find = db()->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
            $find->execute([$productId]);
            $current = $find->fetch();
            if (!$current) error('Urun bulunamadi.', 404);

            $fields = [];
            $values = [];
            $name = trim((string) input('name', (string) ($current['name'] ?? '')));
            $slug = slugify($name);

            foreach (['category_id', 'brand_id', 'name', 'description', 'price', 'old_price', 'stock', 'images', 'img', 'desi', 'pieces', 'is_active'] as $column) {
                if (!tableHasColumn('products', $column)) {
                    continue;
                }
                $aliases = match ($column) {
                    'category_id' => ['category_id', 'categoryId'],
                    'brand_id' => ['brand_id', 'brandId'],
                    'old_price' => ['old_price', 'oldPrice'],
                    'is_active' => ['is_active', 'isActive'],
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
                error('Guncellenecek alan yok.');
            }

            $values[] = $productId;
            db()->prepare('UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);

            $find->execute([$productId]);
            ok(legacyProduct($find->fetch() ?: $current));

        case 'DELETE':
            adminRequired();
            $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
            $stmt->execute([$productId]);
            if ($stmt->rowCount() === 0) error('Urun bulunamadi.', 404);
            ok(['success' => true]);

        default:
            error('Method not allowed.', 405);
    }
}

error('Urun endpoint bulunamadi.', 404);
