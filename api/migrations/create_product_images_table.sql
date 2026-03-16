-- ═══════════════════════════════════════════════
--  Product Images Table Migration
--  Ürün görselleri için tablo oluşturma
-- ═══════════════════════════════════════════════

-- product_images tablosu zaten varsa sil (dikkatli kullan!)
-- DROP TABLE IF EXISTS `product_images`;

CREATE TABLE IF NOT EXISTS `product_images` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` VARCHAR(50) COLLATE utf8mb4_turkish_ci NOT NULL COMMENT 'Ürün ID (products.id FK)',
  `filename` VARCHAR(255) COLLATE utf8mb4_turkish_ci NOT NULL COMMENT 'Dosya adı (örn: 20260316_a1b2c3.jpg)',
  `storage_driver` VARCHAR(50) COLLATE utf8mb4_turkish_ci NOT NULL DEFAULT 'local' COMMENT 'Depolama sürücüsü (local, s3, vb.)',
  `url` TEXT COLLATE utf8mb4_turkish_ci NOT NULL COMMENT 'Görselin tam URL\'si',
  `mime_type` VARCHAR(50) COLLATE utf8mb4_turkish_ci NOT NULL DEFAULT 'image/jpeg' COMMENT 'MIME tipi (image/jpeg, image/png, vb.)',
  `alt_text` VARCHAR(255) COLLATE utf8mb4_turkish_ci DEFAULT NULL COMMENT 'Görsel alternatif metni (SEO)',
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ana görsel mi? (1=evet, 0=hayır)',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT 'Sıralama numarası',
  `size_bytes` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Dosya boyutu (byte)',
  `width` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Genişlik (px)',
  `height` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Yükseklik (px)',
  `uploaded_by` VARCHAR(50) COLLATE utf8mb4_turkish_ci DEFAULT NULL COMMENT 'Yükleyen kullanıcı ID (users.id FK)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Oluşturulma zamanı',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Güncellenme zamanı',
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_is_primary` (`is_primary`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci COMMENT='Ürün görselleri tablosu';

-- products tablosundaki img ve images sütunlarının NULL olmasına izin ver
-- (Eğer yoksa bu komutlar hata verebilir, o zaman manuel kontrol edin)
ALTER TABLE `products`
  MODIFY COLUMN `img` TEXT COLLATE utf8mb4_turkish_ci DEFAULT NULL COMMENT 'Ana görsel URL (product_images\'tan sync edilir)',
  MODIFY COLUMN `images` TEXT COLLATE utf8mb4_turkish_ci DEFAULT NULL COMMENT 'Galeri görselleri JSON (product_images\'tan sync edilir)';

-- Başarılı mesajı
SELECT 'product_images tablosu başarıyla oluşturuldu!' AS message;
