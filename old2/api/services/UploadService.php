<?php
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  UploadService
//  GÃ¼venli dosya yÃ¼kleme â€” tÃ¼m validasyon burada
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class UploadService
{
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
        $appUrl  = rtrim(env('APP_URL', 'http://localhost'), '/');
        $url     = $appUrl . '/uploads/products/' . $baseName;

        // EÄŸer bu primary olacaksa diÄŸerlerini kaldÄ±r
        if ($isPrimary) {
            db()->prepare(
                'UPDATE product_images SET is_primary = 0 WHERE product_id = ?'
            )->execute([$productId]);
        }

        // Mevcut gÃ¶rsel sayÄ±sÄ±nÄ± sort_order iÃ§in al
        $countStmt = db()->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ?');
        $countStmt->execute([$productId]);
        $count = (int) $countStmt->fetchColumn();

        $stmt = db()->prepare(
            'INSERT INTO product_images
             (product_id, filename, url, alt_text, size_bytes, width, height, is_primary, sort_order, uploaded_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $productId,
            $baseName,
            $url,
            null,          // alt_text sonradan gÃ¼ncellenebilir
            $file['size'],
            $origWidth,
            $origHeight,
            $isPrimary ? 1 : 0,
            $count,
            $uploadedBy,
        ]);
        $imageId = (int) db()->lastInsertId();

        AuditLog::write(AuditLog::UPLOAD_SUCCESS, $uploadedBy, 'product_image', $imageId, [
            'product_id' => $productId,
            'filename'   => $baseName,
            'size'       => $file['size'],
        ]);

        return [
            'id'         => $imageId,
            'url'        => $url,
            'urls'       => [
                'original' => $appUrl . '/uploads/products/original_' . $baseName,
                'medium'   => $appUrl . '/uploads/products/medium_'   . $baseName,
                'thumb'    => $appUrl . '/uploads/products/thumb_'    . $baseName,
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
        $stmt = db()->prepare('SELECT * FROM product_images WHERE id = ? LIMIT 1');
        $stmt->execute([$imageId]);
        $image = $stmt->fetch();

        if (!$image) return false;

        // Disk'ten sil (tÃ¼m boyutlar)
        $uploadDir = self::getUploadDir();
        foreach (['original_', 'medium_', 'thumb_', ''] as $prefix) {
            $path = $uploadDir . '/' . $prefix . $image['filename'];
            if (file_exists($path)) @unlink($path);
        }

        // DB'den sil
        db()->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imageId]);

        // Primary silinmiÅŸse bir sonrakini primary yap
        if ($image['is_primary']) {
            db()->prepare(
                'UPDATE product_images SET is_primary = 1 WHERE product_id = ? ORDER BY sort_order ASC LIMIT 1'
            )->execute([$image['product_id']]);
        }

        AuditLog::write('upload.delete', $deletedBy, 'product_image', $imageId, [
            'filename'   => $image['filename'],
            'product_id' => $image['product_id'],
        ]);

        return true;
    }

    // â”€â”€â”€ GÃ¶rselleri Getir â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public static function getProductImages(string $productId): array
    {
        $appUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
        $stmt   = db()->prepare(
            'SELECT id, filename, url, alt_text, size_bytes, width, height, is_primary, sort_order
             FROM product_images WHERE product_id = ? ORDER BY sort_order ASC'
        );
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['urls'] = [
                'original' => $appUrl . '/uploads/products/original_' . $row['filename'],
                'medium'   => $appUrl . '/uploads/products/medium_'   . $row['filename'],
                'thumb'    => $appUrl . '/uploads/products/thumb_'    . $row['filename'],
            ];
        }

        return $rows;
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
            $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $normalized;
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
