<?php
// ═══════════════════════════════════════════════
//  Product Images Tablo Kurulum Script'i
//  Kullanım: http://localhost/fix-product-images.php
//  UYARI: Bu dosyayı çalıştırdıktan sonra SİL!
// ═══════════════════════════════════════════════

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Veritabanı bağlantısı
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/helpers.php';

$messages = [];
$success = true;

try {
    $pdo = db();

    // 1. product_images tablosunun var olup olmadığını kontrol et
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'product_images'");
    $tableExists = $tableCheck->rowCount() > 0;

    if ($tableExists) {
        $messages[] = [
            'type' => 'info',
            'text' => 'product_images tablosu zaten mevcut.'
        ];

        // Mevcut kayıt sayısını göster
        $count = $pdo->query("SELECT COUNT(*) FROM product_images")->fetchColumn();
        $messages[] = [
            'type' => 'info',
            'text' => "Tabloda $count adet kayıt var."
        ];

    } else {
        $messages[] = [
            'type' => 'warning',
            'text' => 'product_images tablosu bulunamadı. Oluşturuluyor...'
        ];

        // 2. Tabloyu oluştur
        $sql = "CREATE TABLE `product_images` (
          `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          `product_id` VARCHAR(50) COLLATE utf8mb4_turkish_ci NOT NULL COMMENT 'Ürün ID (products.id FK)',
          `filename` VARCHAR(255) COLLATE utf8mb4_turkish_ci NOT NULL COMMENT 'Dosya adı',
          `storage_driver` VARCHAR(50) COLLATE utf8mb4_turkish_ci NOT NULL DEFAULT 'local' COMMENT 'Depolama sürücüsü',
          `url` TEXT COLLATE utf8mb4_turkish_ci NOT NULL COMMENT 'Görselin tam URL si',
          `mime_type` VARCHAR(50) COLLATE utf8mb4_turkish_ci NOT NULL DEFAULT 'image/jpeg' COMMENT 'MIME tipi',
          `alt_text` VARCHAR(255) COLLATE utf8mb4_turkish_ci DEFAULT NULL COMMENT 'Görsel alternatif metni',
          `is_primary` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ana görsel mi?',
          `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT 'Sıralama numarası',
          `size_bytes` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Dosya boyutu',
          `width` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Genişlik',
          `height` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Yükseklik',
          `uploaded_by` VARCHAR(50) COLLATE utf8mb4_turkish_ci DEFAULT NULL COMMENT 'Yükleyen kullanıcı ID',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Oluşturulma zamanı',
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Güncellenme zamanı',
          PRIMARY KEY (`id`),
          KEY `idx_product_id` (`product_id`),
          KEY `idx_is_primary` (`is_primary`),
          KEY `idx_sort_order` (`sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci COMMENT='Ürün görselleri tablosu'";

        $pdo->exec($sql);

        $messages[] = [
            'type' => 'success',
            'text' => '✅ product_images tablosu başarıyla oluşturuldu!'
        ];
    }

    // 3. products tablosundaki img ve images kolonlarını kontrol et
    $columns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_ASSOC);
    $hasImg = false;
    $hasImages = false;

    foreach ($columns as $column) {
        if ($column['Field'] === 'img') {
            $hasImg = true;
        }
        if ($column['Field'] === 'images') {
            $hasImages = true;
        }
    }

    if (!$hasImg) {
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `img` TEXT COLLATE utf8mb4_turkish_ci DEFAULT NULL COMMENT 'Ana görsel URL'");
        $messages[] = [
            'type' => 'success',
            'text' => '✅ products.img kolonu eklendi.'
        ];
    } else {
        $messages[] = [
            'type' => 'info',
            'text' => 'products.img kolonu zaten mevcut.'
        ];
    }

    if (!$hasImages) {
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `images` TEXT COLLATE utf8mb4_turkish_ci DEFAULT NULL COMMENT 'Galeri görselleri JSON'");
        $messages[] = [
            'type' => 'success',
            'text' => '✅ products.images kolonu eklendi.'
        ];
    } else {
        $messages[] = [
            'type' => 'info',
            'text' => 'products.images kolonu zaten mevcut.'
        ];
    }

    // 4. Upload dizinini kontrol et
    $uploadDir = __DIR__ . '/public/uploads/products';
    if (!is_dir($uploadDir)) {
        if (mkdir($uploadDir, 0755, true)) {
            $messages[] = [
                'type' => 'success',
                'text' => '✅ Upload dizini oluşturuldu: /public/uploads/products'
            ];
        } else {
            $messages[] = [
                'type' => 'error',
                'text' => '❌ Upload dizini oluşturulamadı! Manuel olarak oluşturun: /public/uploads/products'
            ];
            $success = false;
        }
    } else {
        $writable = is_writable($uploadDir);
        $messages[] = [
            'type' => $writable ? 'success' : 'warning',
            'text' => $writable
                ? '✅ Upload dizini yazılabilir.'
                : '⚠️ Upload dizini yazılamıyor! Klasör izinlerini kontrol edin (chmod 755).'
        ];
    }

    $messages[] = [
        'type' => 'success',
        'text' => '🎉 Tüm işlemler tamamlandı! Artık admin panelde fotoğraf yükleyebilirsiniz.'
    ];

} catch (Throwable $e) {
    $success = false;
    $messages[] = [
        'type' => 'error',
        'text' => '❌ HATA: ' . $e->getMessage()
    ];
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Product Images Tablo Kurulumu</title>
<style>
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2rem;
    margin: 0;
  }
  .container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    padding: 2rem;
  }
  h1 {
    color: #667eea;
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
  }
  .subtitle {
    color: #666;
    margin: 0 0 2rem 0;
    font-size: 0.9rem;
  }
  .message {
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    line-height: 1.5;
  }
  .message::before {
    content: '';
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    margin-top: 0.5rem;
    flex-shrink: 0;
  }
  .message.success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
  .message.success::before { background: #28a745; }
  .message.error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
  .message.error::before { background: #dc3545; }
  .message.warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
  .message.warning::before { background: #ffc107; }
  .message.info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
  .message.info::before { background: #17a2b8; }

  .summary {
    background: <?= $success ? '#d4edda' : '#f8d7da' ?>;
    color: <?= $success ? '#155724' : '#721c24' ?>;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    font-size: 1.1rem;
    font-weight: 600;
    text-align: center;
    border: 2px solid <?= $success ? '#28a745' : '#dc3545' ?>;
  }

  .footer {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid #dee2e6;
    color: #6c757d;
    font-size: 0.9rem;
  }
  .warning-box {
    background: #fff3cd;
    border: 2px solid #ffc107;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1.5rem;
  }
  .warning-box strong {
    color: #856404;
    display: block;
    margin-bottom: 0.5rem;
  }
  code {
    background: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    color: #e83e8c;
  }
</style>
</head>
<body>
<div class="container">
  <h1>🖼️ Product Images Tablo Kurulumu</h1>
  <p class="subtitle">Admin panelde fotoğraf yükleme sorunu düzeltme aracı</p>

  <div class="summary">
    <?= $success
      ? '✅ İşlem Başarılı - Sisteminiz hazır!'
      : '❌ İşlem Sırasında Hata Oluştu'
    ?>
  </div>

  <div class="messages">
    <?php foreach ($messages as $msg): ?>
      <div class="message <?= htmlspecialchars($msg['type']) ?>">
        <?= htmlspecialchars($msg['text']) ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($success): ?>
  <div class="footer">
    <h3 style="color: #667eea; margin-top: 0;">✅ Sonraki Adımlar:</h3>
    <ol>
      <li>Admin panele giriş yapın</li>
      <li>Bir ürün seçin ve düzenleme moduna girin</li>
      <li>Fotoğraf yükleme alanını kullanarak görsel ekleyin</li>
      <li>Artık fotoğraflar <code>product_images</code> tablosunda saklanacak!</li>
    </ol>
  </div>
  <?php endif; ?>

  <div class="warning-box">
    <strong>⚠️ GÜVENLİK UYARISI:</strong>
    Bu dosyayı işlem tamamlandıktan sonra <strong>mutlaka silin</strong>!
    <br><br>
    Silmek için: <code>rm /path/to/fix-product-images.php</code>
    veya FTP/dosya yöneticisi ile manuel silin.
  </div>
</div>
</body>
</html>
