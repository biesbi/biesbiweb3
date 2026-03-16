<?php
/**
 * Product Images Migration Script
 *
 * Bu script products tablosundaki img ve images kolonlarından
 * product_images tablosuna görselleri kopyalar.
 */

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/helpers.php';

echo "🔄 Ürün görselleri migrate ediliyor...\n\n";

try {
    $db = db();

    // 1. Product_images tablosu var mı kontrol et
    $tables = $db->query("SHOW TABLES LIKE 'product_images'")->fetchAll();
    if (empty($tables)) {
        echo "❌ product_images tablosu bulunamadı!\n";
        exit(1);
    }

    // 2. Tüm ürünleri getir
    $products = $db->query("SELECT id, name, img, images FROM products WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

    echo "📦 Toplam " . count($products) . " ürün bulundu\n\n";

    $migratedCount = 0;
    $skippedCount = 0;

    foreach ($products as $product) {
        $productId = $product['id'];
        $productName = $product['name'];

        // Mevcut görselleri kontrol et
        $existingImages = $db->prepare("SELECT COUNT(*) as count FROM product_images WHERE product_id = ?");
        $existingImages->execute([$productId]);
        $exists = $existingImages->fetch(PDO::FETCH_ASSOC);

        if ($exists['count'] > 0) {
            echo "⏭️  Atlanıyor: $productName (Zaten görseller var)\n";
            $skippedCount++;
            continue;
        }

        $imagesToInsert = [];

        // img kolonundan görsel al
        if (!empty($product['img']) && is_string($product['img'])) {
            $imagesToInsert[] = [
                'url' => trim($product['img']),
                'isPrimary' => true,
                'sortOrder' => 0
            ];
        }

        // images kolonundan görselleri al
        if (!empty($product['images'])) {
            $imagesArray = normalizeImages($product['images']);

            foreach ($imagesArray as $index => $imageUrl) {
                // img ile aynı görsel varsa atlama
                $isDuplicate = false;
                $trimmedUrl = trim($imageUrl);

                foreach ($imagesToInsert as $existing) {
                    // String karşılaştırmayı binary yap
                    if (strcmp($existing['url'], $trimmedUrl) === 0) {
                        $isDuplicate = true;
                        break;
                    }
                }

                if (!$isDuplicate && $trimmedUrl !== '') {
                    $imagesToInsert[] = [
                        'url' => $trimmedUrl,
                        'isPrimary' => empty($imagesToInsert), // İlk görsel primary
                        'sortOrder' => count($imagesToInsert)
                    ];
                }
            }
        }

        // Görselleri veritabanına ekle
        if (!empty($imagesToInsert)) {
            $stmt = $db->prepare(
                "INSERT INTO product_images (product_id, url, alt_text, is_primary, sort_order)
                 VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($imagesToInsert as $image) {
                try {
                    $stmt->execute([
                        $productId,
                        $image['url'],
                        $productName,
                        $image['isPrimary'] ? 1 : 0,
                        $image['sortOrder']
                    ]);
                } catch (Exception $e) {
                    echo "⚠️  Hata (atlandi): {$productName} - " . $e->getMessage() . "\n";
                    continue;
                }
            }

            echo "✅ Migrate edildi: $productName (" . count($imagesToInsert) . " görsel)\n";
            $migratedCount++;
        } else {
            echo "⚠️  Görsel yok: $productName\n";
            $skippedCount++;
        }
    }

    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🎉 Migration tamamlandı!\n";
    echo "✅ Migrate edilen ürün: $migratedCount\n";
    echo "⏭️  Atlanan ürün: $skippedCount\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

} catch (Exception $e) {
    echo "\n❌ HATA: " . $e->getMessage() . "\n";
    echo "Dosya: " . $e->getFile() . "\n";
    echo "Satır: " . $e->getLine() . "\n";
    exit(1);
}
