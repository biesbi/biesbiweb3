<?php
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  UploadService
//  GÃ¼venli dosya yÃ¼kleme â€” tÃ¼m validasyon burada
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class UploadService
{
    private static ?array $productImageColumns = null;
    private const PRODUCT_UPLOAD_ROUTE_PATH = '/api/upload/product-file/';

    // Ä°zin verilen MIME tipleri ve uzantÄ± eÅŸleÅŸmeleri
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    // GÃ¶rsel boyutlarÄ±: [max_width, max_height, quality]
    private const SIZES = [
        'original' => [2000, 2000, 85],   // tam boyut (sÄ±kÄ±ÅŸtÄ±rÄ±lmÄ±ÅŸ)
        'medium'   => [800,  800,  82],   // Ã¼rÃ¼n listesi
        'thumb'    => [300,  300,  80],   // kÃ¼Ã§Ã¼k Ã¶nizleme
    ];

    // â”€â”€â”€ Ana YÃ¼kleme Fonksiyonu â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * $_FILES['image'] gibi bir dosyayÄ± alÄ±r, doÄŸrular ve kaydeder.
     *
     * @param array  $file        $_FILES array elemanÄ±
     * @param string $productId   Ä°lgili Ã¼rÃ¼n ID
     * @param ?string $uploadedBy Admin user ID
     * @param bool   $isPrimary   Ana gÃ¶rsel mi?
     * @return array              Kaydedilen gÃ¶rsel bilgisi
     */
    public static function saveProductImage(
        array $file,
        string $productId,
        ?string $uploadedBy,
        bool  $isPrimary = false
    ): array {
        // 1. PHP upload hatasÄ± kontrolÃ¼
        self::checkUploadError($file['error'] ?? UPLOAD_ERR_NO_FILE);

        // 2. Dosya boyutu kontrolÃ¼
        $maxBytes = (int) env('UPLOAD_MAX_MB', 5) * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            throw new RuntimeException(
                'Dosya boyutu Ã§ok bÃ¼yÃ¼k. Maksimum: ' . env('UPLOAD_MAX_MB', 5) . 'MB'
            );
        }
        if ($file['size'] < 100) {
            throw new RuntimeException('Dosya Ã§ok kÃ¼Ã§Ã¼k veya bozuk.');
        }

        // 3. GerÃ§ek MIME tipi kontrolÃ¼ (finfo ile â€” extension'a gÃ¼venme!)
        $realMime = self::detectMime($file['tmp_name']);
        if (!array_key_exists($realMime, self::ALLOWED)) {
            AuditLog::write('upload.fail', $uploadedBy, 'product', $productId, [
                'reason' => 'invalid_mime',
                'mime'   => $realMime,
            ]);
            throw new RuntimeException(
                'Desteklenmeyen dosya tipi. Sadece JPG, PNG, GIF, WEBP yÃ¼klenebilir.'
            );
        }

        // 4. GÃ¶rsel mi gerÃ§ekten? (image bomb korumasÄ±)
        $imageInfo = @getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            throw new RuntimeException('Dosya geÃ§erli bir gÃ¶rsel deÄŸil.');
        }

        [$origWidth, $origHeight] = $imageInfo;

        // Ã‡ok bÃ¼yÃ¼k Ã§Ã¶zÃ¼nÃ¼rlÃ¼k = image bomb riski
        if ($origWidth > 8000 || $origHeight > 8000) {
            throw new RuntimeException('GÃ¶rsel Ã§Ã¶zÃ¼nÃ¼rlÃ¼ÄŸÃ¼ Ã§ok yÃ¼ksek. Maksimum 8000x8000px.');
        }

        // 5. Benzersiz dosya adÄ± Ã¼ret (path traversal imkansÄ±z)
        $ext      = self::ALLOWED[$realMime];
        $baseName = self::generateFilename($ext);

        // 6. Upload dizinini hazÄ±rla
        $uploadDir = self::getUploadDir();

        // 7. GÃ¶rsel boyutlarÄ±nÄ± Ã¼ret (original, medium, thumb)
        $savedFiles = self::processAndSave($file['tmp_name'], $realMime, $baseName, $uploadDir);

        // 8. DB'ye kaydet
        $publicBaseUrl = self::productUploadBaseUrl();
        $url     = $publicBaseUrl . 'original_' . $baseName;

        // EÄŸer bu primary olacaksa diÄŸerlerini kaldÄ±r
        if ($isPrimary) {
            db()->prepare(
                'UPDATE product_images SET is_primary = 0 WHERE product_id COLLATE utf8mb4_turkish_ci = ?'
            )->execute([$productId]);
        }

        // Mevcut gÃ¶rsel sayÄ±sÄ±nÄ± sort_order iÃ§in al
        $countStmt = db()->prepare('SELECT COUNT(*) FROM product_images WHERE product_id COLLATE utf8mb4_turkish_ci = ?');
        $countStmt->execute([$productId]);
        $count = (int) $countStmt->fetchColumn();

        $insertData = [
            'product_id' => $productId,
            'filename' => $baseName,
            'storage_driver' => 'local',
            'url' => self::hasProductImageColumn('filename')
                ? $url
                : $publicBaseUrl . 'original_' . $baseName,
            'mime_type' => $realMime,
            'alt_text' => null,
            'is_primary' => $isPrimary ? 1 : 0,
            'sort_order' => $count,
            'size_bytes' => $file['size'],
            'width' => $origWidth,
            'height' => $origHeight,
            'uploaded_by' => $uploadedBy,
        ];
        $columns = [];
        $placeholders = [];
        $values = [];
        foreach ($insertData as $column => $value) {
            if (!self::hasProductImageColumn($column)) {
                continue;
            }
            $columns[] = $column;
            $placeholders[] = '?';
            $values[] = $value;
        }
        $stmt = db()->prepare(
            'INSERT INTO product_images (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($values);
        $imageId = (int) db()->lastInsertId();

        AuditLog::write(AuditLog::UPLOAD_SUCCESS, $uploadedBy, 'product_image', $imageId, [
            'product_id' => $productId,
            'filename'   => $baseName,
            'size'       => $file['size'],
        ]);

        self::syncProductImageFields($productId);

        return [
            'id'         => $imageId,
            'url'        => $url,
            'urls'       => [
                'original' => $publicBaseUrl . 'original_' . $baseName,
                'medium'   => $publicBaseUrl . 'medium_'   . $baseName,
                'thumb'    => $publicBaseUrl . 'thumb_'    . $baseName,
            ],
            'width'      => $origWidth,
            'height'     => $origHeight,
            'size_bytes' => $file['size'],
            'is_primary' => $isPrimary,
        ];
    }

    // â”€â”€â”€ GÃ¶rsel Sil â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * GÃ¶rseli DB'den ve diskten sil.
     */
    public static function deleteProductImage(int $imageId, ?string $deletedBy): bool
    {
        $stmt = db()->prepare('SELECT * FROM product_images WHERE id COLLATE utf8mb4_turkish_ci = ? LIMIT 1');
        $stmt->execute([$imageId]);
        $image = $stmt->fetch();

        if (!$image) return false;

        // Disk'ten sil (tÃ¼m boyutlar)
        $uploadDir = self::getUploadDir();
        $filename = self::extractImageFilename($image);
        foreach (['original_', 'medium_', 'thumb_', ''] as $prefix) {
            if ($filename === '') {
                continue;
            }
            $path = $uploadDir . '/' . $prefix . $filename;
            if (file_exists($path)) @unlink($path);
        }

        // DB'den sil
        db()->prepare('DELETE FROM product_images WHERE id COLLATE utf8mb4_turkish_ci = ?')->execute([$imageId]);

        // Primary silinmiÅŸse bir sonrakini primary yap
        if ($image['is_primary']) {
            db()->prepare(
                'UPDATE product_images SET is_primary = 1 WHERE product_id COLLATE utf8mb4_turkish_ci = ? ORDER BY sort_order ASC LIMIT 1'
            )->execute([$image['product_id']]);
        }

        AuditLog::write('upload.delete', $deletedBy, 'product_image', $imageId, [
            'filename'   => $filename,
            'product_id' => $image['product_id'],
        ]);

        self::syncProductImageFields((string) $image['product_id']);

        return true;
    }

    public static function syncProductImageFields(string $productId): void
    {
        $productId = trim($productId);
        if ($productId === '') {
            return;
        }

        $images = self::getProductImages($productId);
        if ($images === []) {
            db()->prepare('UPDATE products SET img = NULL, images = ? WHERE id COLLATE utf8mb4_turkish_ci = ?')
                ->execute([json_encode([], JSON_UNESCAPED_SLASHES), $productId]);
            return;
        }

        usort($images, static function (array $left, array $right): int {
            return [
                (int) (($right['is_primary'] ?? 0) ? 1 : 0),
                -((int) ($left['sort_order'] ?? 0)),
                -((int) ($left['id'] ?? 0)),
            ] <=> [
                (int) (($left['is_primary'] ?? 0) ? 1 : 0),
                -((int) ($right['sort_order'] ?? 0)),
                -((int) ($right['id'] ?? 0)),
            ];
        });

        $galleryUrls = array_values(array_filter(array_map(
            static function (array $image): string {
                $urls = is_array($image['urls'] ?? null) ? $image['urls'] : [];
                $medium = trim((string) ($urls['medium'] ?? ''));
                if ($medium !== '') {
                    return $medium;
                }

                $original = trim((string) ($urls['original'] ?? ''));
                if ($original !== '') {
                    return $original;
                }

                return trim((string) ($image['url'] ?? ''));
            },
            $images
        )));

        $coverImage = $galleryUrls[0] ?? null;
        db()->prepare('UPDATE products SET img = ?, images = ? WHERE id COLLATE utf8mb4_turkish_ci = ?')
            ->execute([
                $coverImage,
                json_encode($galleryUrls, JSON_UNESCAPED_SLASHES),
                $productId,
            ]);
    }

    // â”€â”€â”€ GÃ¶rselleri Getir â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public static function getProductImages(string $productId): array
    {
        $publicBaseUrl = self::productUploadBaseUrl();
        $selectColumns = ['id', 'url', 'alt_text', 'is_primary', 'sort_order'];
        foreach (['filename', 'size_bytes', 'width', 'height'] as $column) {
            if (self::hasProductImageColumn($column)) {
                $selectColumns[] = $column;
            }
        }
        $stmt   = db()->prepare(
            'SELECT ' . implode(', ', $selectColumns) . '
             FROM product_images WHERE product_id COLLATE utf8mb4_turkish_ci = ? ORDER BY sort_order ASC'
        );
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $filename = self::extractImageFilename($row);
            $row['urls'] = [
                'original' => $filename !== '' ? $publicBaseUrl . 'original_' . $filename : ($row['url'] ?? ''),
                'medium'   => $filename !== '' ? $publicBaseUrl . 'medium_'   . $filename : ($row['url'] ?? ''),
                'thumb'    => $filename !== '' ? $publicBaseUrl . 'thumb_'    . $filename : ($row['url'] ?? ''),
            ];
            if (!isset($row['filename'])) {
                $row['filename'] = $filename;
            }
            if ($filename !== '') {
                $row['url'] = $row['urls']['original'];
            }
        }

        return $rows;
    }

    public static function saveCategoryBanner(array $file, string $slot, ?string $uploadedBy): array
    {
        $slot = self::normalizeCategoryBannerSlot($slot);
        self::checkUploadError($file['error'] ?? UPLOAD_ERR_NO_FILE);

        $maxBytes = (int) env('UPLOAD_MAX_MB', 5) * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException('Dosya boyutu cok buyuk.');
        }

        $realMime = self::detectMime($file['tmp_name']);
        if (!array_key_exists($realMime, self::ALLOWED)) {
            throw new RuntimeException('Desteklenmeyen dosya tipi.');
        }

        $ext = self::ALLOWED[$realMime];
        $dir = self::getCategoryBannerDir();
        self::deleteExistingCategoryBannerFiles($dir, $slot);

        $filename = $slot . '.' . $ext;
        $target = $dir . DIRECTORY_SEPARATOR . $filename;

        self::resizeAndSave($file['tmp_name'], $realMime, $target, 1600, 900, 84);

        $appUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
        $url = $appUrl . '/public/category-banners/' . rawurlencode($filename);

        $manifest = self::getCategoryBannerManifest();
        $manifest[$slot] = [
            'slot' => $slot,
            'url' => $url,
            'filename' => $filename,
            'updated_at' => date(DATE_ATOM),
        ];
        self::writeCategoryBannerManifest($manifest);

        AuditLog::write('upload.category_banner', $uploadedBy, 'category_banner', $slot, [
            'slot' => $slot,
            'filename' => $filename,
        ]);

        return $manifest[$slot];
    }

    public static function getCategoryBannerManifest(): array
    {
        $dir = self::getCategoryBannerDir();
        $manifestPath = $dir . DIRECTORY_SEPARATOR . 'manifest.json';
        $manifest = [];

        if (is_file($manifestPath)) {
            $decoded = json_decode((string) file_get_contents($manifestPath), true);
            if (is_array($decoded)) {
                $manifest = $decoded;
            }
        }

        $appUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.*') ?: [] as $path) {
            if (!is_file($path) || basename($path) === 'manifest.json') {
                continue;
            }
            $basename = basename($path);
            $slot = pathinfo($basename, PATHINFO_FILENAME);
            if ($slot === '') {
                continue;
            }
            $manifest[$slot] ??= [
                'slot' => $slot,
                'url' => $appUrl . '/public/category-banners/' . rawurlencode($basename),
                'filename' => $basename,
                'updated_at' => date(DATE_ATOM, filemtime($path) ?: time()),
            ];
        }

        return $manifest;
    }

    // â”€â”€â”€ GÃ¶rsel Ä°ÅŸleme (GD ile resize) â”€â”€â”€â”€â”€â”€â”€

    private static function processAndSave(
        string $tmpPath,
        string $mime,
        string $baseName,
        string $uploadDir
    ): array {
        $saved = [];

        foreach (self::SIZES as $sizeName => [$maxW, $maxH, $quality]) {
            $destName = ($sizeName === 'original' ? 'original_' : $sizeName . '_') . $baseName;
            $destPath = $uploadDir . '/' . $destName;

            self::resizeAndSave($tmpPath, $mime, $destPath, $maxW, $maxH, $quality);
            $saved[$sizeName] = $destName;
        }

        return $saved;
    }

    private static function resizeAndSave(
        string $srcPath,
        string $mime,
        string $destPath,
        int    $maxW,
        int    $maxH,
        int    $quality
    ): void {
        // GD kÃ¼tÃ¼phanesi kontrolÃ¼
        if (!extension_loaded('gd')) {
            // GD yoksa dosyayÄ± olduÄŸu gibi kopyala
            copy($srcPath, $destPath);
            return;
        }

        // Kaynak gÃ¶rseli yÃ¼kle
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($srcPath),
            'image/png'  => @imagecreatefrompng($srcPath),
            'image/gif'  => @imagecreatefromgif($srcPath),
            'image/webp' => @imagecreatefromwebp($srcPath),
            default      => false,
        };

        if (!$src) {
            copy($srcPath, $destPath);
            return;
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        // Boyut hesaplama (orantÄ±lÄ±)
        [$newW, $newH] = self::calcDimensions($origW, $origH, $maxW, $maxH);

        // Hedef canvas oluÅŸtur
        $dst = imagecreatetruecolor($newW, $newH);

        // PNG/GIF iÃ§in ÅŸeffaflÄ±k koru
        if ($mime === 'image/png' || $mime === 'image/gif') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        // Kaydet
        match ($mime) {
            'image/jpeg' => imagejpeg($dst, $destPath, $quality),
            'image/png'  => imagepng($dst, $destPath, (int) round((100 - $quality) / 10)),
            'image/gif'  => imagegif($dst, $destPath),
            'image/webp' => imagewebp($dst, $destPath, $quality),
            default      => copy($srcPath, $destPath),
        };

        imagedestroy($src);
        imagedestroy($dst);
    }

    private static function calcDimensions(int $w, int $h, int $maxW, int $maxH): array
    {
        if ($w <= $maxW && $h <= $maxH) return [$w, $h];

        $ratio  = min($maxW / $w, $maxH / $h);
        return [(int) round($w * $ratio), (int) round($h * $ratio)];
    }

    // â”€â”€â”€ YardÄ±mcÄ± â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private static function detectMime(string $path): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($path) ?: 'application/octet-stream';
    }

    private static function generateFilename(string $ext): string
    {
        return sprintf(
            '%s_%s.%s',
            date('Ymd'),
            bin2hex(random_bytes(12)),   // 24 hex karakter
            $ext
        );
    }

    private static function getUploadDir(): string
    {
        $configured = (string) env('UPLOAD_DIR', '../public/uploads');
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($configured));

        if (
            $normalized === ''
            || preg_match('/^[A-Za-z]:\\\\/', $normalized)
            || str_starts_with($normalized, DIRECTORY_SEPARATOR)
        ) {
            $base = $normalized !== ''
                ? $normalized
                : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
        } else {
            $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . $normalized;
        }

        $base = rtrim($base, DIRECTORY_SEPARATOR);
        $dir  = $base . DIRECTORY_SEPARATOR . 'products';

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new RuntimeException('Upload dizini oluÅŸturulamadÄ±.');
            }
        }

        return $dir;
    }

    private static function getLegacyUploadDirs(): array
    {
        $dirs = [];
        $xamppRootDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
        if (is_dir($xamppRootDir)) {
            $dirs[] = $xamppRootDir;
        }

        return $dirs;
    }

    private static function getCategoryBannerDir(): string
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'category-banners';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException('Kategori banner dizini olusturulamadi.');
        }
        return $dir;
    }

    private static function normalizeCategoryBannerSlot(string $slot): string
    {
        $slot = strtolower(trim($slot));
        $slot = preg_replace('/[^a-z0-9-]+/', '-', $slot);
        $slot = trim((string) $slot, '-');
        if ($slot === '') {
            throw new RuntimeException('Gecersiz kategori banner alani.');
        }
        return $slot;
    }

    private static function getProductImageColumns(): array
    {
        if (self::$productImageColumns !== null) {
            return self::$productImageColumns;
        }

        $columns = [];
        foreach (db()->query('DESCRIBE product_images')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $field = strtolower((string) ($row['Field'] ?? ''));
            if ($field !== '') {
                $columns[$field] = true;
            }
        }

        self::$productImageColumns = $columns;
        return self::$productImageColumns;
    }

    private static function hasProductImageColumn(string $column): bool
    {
        return isset(self::getProductImageColumns()[strtolower($column)]);
    }

    private static function extractImageFilename(array $image): string
    {
        $filename = trim((string) ($image['filename'] ?? ''));
        if ($filename === '') {
            $path = (string) parse_url((string) ($image['url'] ?? ''), PHP_URL_PATH);
            $filename = basename($path);
        }

        foreach (['original_', 'medium_', 'thumb_'] as $prefix) {
            if (str_starts_with($filename, $prefix)) {
                return substr($filename, strlen($prefix));
            }
        }

        return $filename;
    }

    private static function productUploadBaseUrl(): string
    {
        return self::PRODUCT_UPLOAD_ROUTE_PATH;
    }

    public static function outputProductImageFile(string $filename): void
    {
        $filename = trim(rawurldecode($filename));
        $filename = basename($filename);
        if ($filename === '' || !preg_match('/^(original|medium|thumb)_[a-z0-9_]+\.(jpe?g|png|gif|webp)$/i', $filename)) {
            http_response_code(404);
            exit;
        }

        $path = self::findProductImagePath($filename);
        if ($path === null || !is_file($path)) {
            http_response_code(404);
            exit;
        }

        $mime = self::detectMime($path);
        if (!array_key_exists($mime, self::ALLOWED)) {
            $mime = 'application/octet-stream';
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: public, max-age=2592000, immutable');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private static function findProductImagePath(string $filename): ?string
    {
        $searchDirs = array_merge([self::getUploadDir()], self::getLegacyUploadDirs());
        foreach ($searchDirs as $dir) {
            $path = $dir . DIRECTORY_SEPARATOR . $filename;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private static function deleteExistingCategoryBannerFiles(string $dir, string $slot): void
    {
        foreach (glob($dir . DIRECTORY_SEPARATOR . $slot . '.*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private static function writeCategoryBannerManifest(array $manifest): void
    {
        $dir = self::getCategoryBannerDir();
        $manifestPath = $dir . DIRECTORY_SEPARATOR . 'manifest.json';
        file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private static function checkUploadError(int $errorCode): void
    {
        match ($errorCode) {
            UPLOAD_ERR_OK       => null,
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => throw new RuntimeException('Dosya boyutu Ã§ok bÃ¼yÃ¼k.'),
            UPLOAD_ERR_PARTIAL   => throw new RuntimeException('Dosya eksik yÃ¼klendi. Tekrar deneyin.'),
            UPLOAD_ERR_NO_FILE   => throw new RuntimeException('Dosya seÃ§ilmedi.'),
            UPLOAD_ERR_NO_TMP_DIR,
            UPLOAD_ERR_CANT_WRITE => throw new RuntimeException('Sunucu depolama hatasÄ±.'),
            UPLOAD_ERR_EXTENSION  => throw new RuntimeException('Dosya uzantÄ±sÄ± engellendi.'),
            default               => throw new RuntimeException('Bilinmeyen yÃ¼kleme hatasÄ±.'),
        };
    }
}
