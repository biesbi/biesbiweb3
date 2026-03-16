<?php

final class ProductCatalogService
{
    private const DEFAULT_PRODUCT_IMAGE = '/logo_v2_full.png';
    private const PRODUCT_UPLOAD_ROUTE_PATH = '/api/upload/product-file/';

    public static function fetchProductImagesByProductIds(array $productIds): array
    {
        if ($productIds === [] || !tableExists('product_images')) {
            return [];
        }

        $productIds = array_values(array_unique(array_filter(array_map(
            static fn($value) => trim((string) $value),
            $productIds
        ))));

        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = db()->prepare(
            "SELECT id, product_id, url, alt_text, is_primary, sort_order
             FROM product_images
             WHERE product_id IN ($placeholders)
             ORDER BY product_id ASC, is_primary DESC, sort_order ASC, id ASC"
        );
        $stmt->execute($productIds);

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $productId = (string) ($row['product_id'] ?? '');
            if ($productId === '') {
                continue;
            }

            $url = self::normalizeProductImageUrl((string) ($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $grouped[$productId][] = [
                'id' => (int) ($row['id'] ?? 0),
                'url' => $url,
                'thumb' => $url,
                'alt' => $row['alt_text'] ?? null,
                'isPrimary' => (int) ($row['is_primary'] ?? 0) === 1,
                'sortOrder' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        return $grouped;
    }

    public static function fetchPrimaryProductImagesByProductIds(array $productIds): array
    {
        $grouped = self::fetchProductImagesByProductIds($productIds);
        $primary = [];

        foreach ($grouped as $productId => $images) {
            if (!empty($images[0])) {
                $primary[$productId] = [$images[0]];
            }
        }

        return $primary;
    }

    private static function normalizeProductImageUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $path = (string) parse_url($value, PHP_URL_PATH);
        $filename = basename($path !== '' ? $path : $value);
        foreach (['original_', 'medium_', 'thumb_'] as $prefix) {
            if (str_starts_with($filename, $prefix)) {
                $filename = substr($filename, strlen($prefix));
                break;
            }
        }

        if ($filename !== '' && preg_match('/\.(jpe?g|png|gif|webp)$/i', $filename)) {
            return self::PRODUCT_UPLOAD_ROUTE_PATH . 'original_' . rawurlencode($filename);
        }

        return $value;
    }

    public static function normalizeCondition(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = str_replace(['ı', 'İ'], ['i', 'i'], $value);
        return match ($value) {
            'used', 'second_hand', 'second-hand', 'second hand', 'ikinci el', 'ikinci-el', '2.el', '2. el', '2el', '2 el', 'excellent', 'good', 'fair' => 'used',
            default => 'new',
        };
    }

    public static function conditionLabel(?string $value): string
    {
        return self::normalizeCondition($value) === 'used' ? '2. El' : '0';
    }

    public static function normalizePieces(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $pieces = (int) $value;
        return $pieces > 0 ? $pieces : 0;
    }

    public static function enrichProduct(array $product, ?array $images = null, bool $includeGallery = true): array
    {
        $gallery = is_array($images) ? $images : [];

        if ($gallery === []) {
            $fallbackImages = normalizeImages($product['images'] ?? []);
            if ($fallbackImages === [] && !empty($product['img']) && is_string($product['img'])) {
                $fallbackImages = [$product['img']];
            }

            foreach ($fallbackImages as $index => $url) {
                $gallery[] = [
                    'id' => 0,
                    'url' => $url,
                    'thumb' => $url,
                    'alt' => $product['name'] ?? null,
                    'isPrimary' => $index === 0,
                    'sortOrder' => $index,
                ];
            }
        }

        usort($gallery, static function (array $left, array $right): int {
            return [$right['isPrimary'] ? 1 : 0, -((int) $left['sortOrder'])] <=> [$left['isPrimary'] ? 1 : 0, -((int) $right['sortOrder'])];
        });

        $galleryUrls = array_values(array_unique(array_map(
            static fn(array $image) => (string) ($image['url'] ?? ''),
            array_filter($gallery, static fn(array $image) => !empty($image['url']))
        )));

        $coverImage = $gallery[0]['url'] ?? ($product['img'] ?? ($galleryUrls[0] ?? self::DEFAULT_PRODUCT_IMAGE));
        $stock = isset($product['stock']) ? (int) $product['stock'] : 0;
        $isActive = isset($product['is_active']) ? (int) $product['is_active'] : 1;
        $condition = self::normalizeCondition($product['product_condition'] ?? ($product['condition_tag'] ?? 'new'));
        $pieces = self::normalizePieces($product['pieces'] ?? 0);

        $listCoverImage = $coverImage;

        $payload = [
            ...$product,
            'title' => $product['name'] ?? ($product['title'] ?? ''),
            'slug' => $product['slug'] ?? null,
            'description' => $product['description'] ?? '',
            'img' => $listCoverImage,
            'coverImage' => $listCoverImage,
            'primaryImage' => $listCoverImage,
            'category' => $product['category_name'] ?? ($product['category'] ?? null),
            'categorySlug' => $product['category_slug'] ?? null,
            'brand' => $product['brand_name'] ?? ($product['brand'] ?? null),
            'brandSlug' => $product['brand_slug'] ?? null,
            'categoryId' => $product['category_id'] ?? ($product['categoryId'] ?? null),
            'brandId' => $product['brand_id'] ?? ($product['brandId'] ?? null),
            'desi' => isset($product['desi']) ? (float) $product['desi'] : 1.0,
            'pieces' => $pieces,
            'price' => isset($product['price']) ? (float) $product['price'] : 0.0,
            'oldPrice' => isset($product['old_price']) ? (float) $product['old_price'] : (isset($product['oldPrice']) ? (float) $product['oldPrice'] : 0.0),
            'productCondition' => $condition,
            'productConditionLabel' => self::conditionLabel($condition),
            'condition' => $condition,
            'conditionLabel' => self::conditionLabel($condition),
            'isActive' => $isActive === 1,
            'in_stock' => $isActive === 1 && $stock > 0,
            'stock_status' => ($isActive === 1 && $stock > 0) ? 'in_stock' : 'out_of_stock',
            'stockLabel' => $stock > 0 ? 'Stokta' : 'Tükendi',
            'images' => [],
            'gallery' => [],
        ];

        if ($includeGallery) {
            $payload['images'] = $galleryUrls;
            $payload['gallery'] = $gallery;
        }

        return $payload;
    }

    public static function enrichProducts(array $rows, bool $includeGallery = true): array
    {
        $imagesByProductId = $includeGallery
            ? self::fetchProductImagesByProductIds(array_column($rows, 'id'))
            : self::fetchPrimaryProductImagesByProductIds(array_column($rows, 'id'));

        return array_map(
            static fn(array $row) => self::enrichProduct(
                $row,
                $imagesByProductId[(string) ($row['id'] ?? '')] ?? [],
                $includeGallery
            ),
            $rows
        );
    }
}
