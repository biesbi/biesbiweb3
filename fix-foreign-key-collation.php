<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/helpers.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Foreign Key Collation Düzeltme</h1>";
echo "<pre>";

$pdo = db();

echo "═══════════════════════════════════════\n";
echo "FOREIGN KEY COLLATION SORUNU ÇÖZÜMÜ\n";
echo "═══════════════════════════════════════\n\n";

// 1. Mevcut collation'ları kontrol et
echo "1️⃣ MEVCUT COLLATION'LARI KONTROL ET:\n";
echo "───────────────────────────────────────\n";

$stmt = $pdo->query("
    SELECT
        TABLE_NAME,
        COLUMN_NAME,
        COLLATION_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'boomeritems'
      AND ((TABLE_NAME = 'products' AND COLUMN_NAME = 'id')
        OR (TABLE_NAME = 'product_images' AND COLUMN_NAME = 'product_id'))
");

$collations = $stmt->fetchAll();
foreach ($collations as $col) {
    echo $col['TABLE_NAME'] . "." . $col['COLUMN_NAME'] . " = " . $col['COLLATION_NAME'] . "\n";
}
echo "\n";

// 2. Foreign key'i kaldır
echo "2️⃣ FOREIGN KEY KALDIRILIYOR:\n";
echo "───────────────────────────────────────\n";
try {
    $pdo->exec("ALTER TABLE product_images DROP FOREIGN KEY fk_product_images_product");
    echo "✅ Foreign key kaldırıldı\n";
} catch (PDOException $e) {
    echo "⚠️  Hata: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Her iki kolonu da açıkça utf8mb4_turkish_ci yap
echo "3️⃣ KOLONLARI AYNI COLLATION'A AYARLA:\n";
echo "───────────────────────────────────────\n";

try {
    // products.id kolonunu utf8mb4_turkish_ci yap
    $pdo->exec("
        ALTER TABLE products
        MODIFY COLUMN id VARCHAR(50)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_turkish_ci
        NOT NULL
    ");
    echo "✅ products.id → utf8mb4_turkish_ci\n";
} catch (PDOException $e) {
    echo "⚠️  products.id hatası: " . $e->getMessage() . "\n";
}

try {
    // product_images.product_id kolonunu utf8mb4_turkish_ci yap
    $pdo->exec("
        ALTER TABLE product_images
        MODIFY COLUMN product_id VARCHAR(36)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_turkish_ci
        NOT NULL
    ");
    echo "✅ product_images.product_id → utf8mb4_turkish_ci\n";
} catch (PDOException $e) {
    echo "⚠️  product_images.product_id hatası: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Foreign key'i tekrar ekle
echo "4️⃣ FOREIGN KEY YENİDEN EKLENIYOR:\n";
echo "───────────────────────────────────────\n";
try {
    $pdo->exec("
        ALTER TABLE product_images
        ADD CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE CASCADE
    ");
    echo "✅ Foreign key yeniden eklendi\n";
} catch (PDOException $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Kontrol et
echo "5️⃣ KONTROL:\n";
echo "───────────────────────────────────────\n";

$stmt = $pdo->query("
    SELECT
        TABLE_NAME,
        COLUMN_NAME,
        COLLATION_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'boomeritems'
      AND ((TABLE_NAME = 'products' AND COLUMN_NAME = 'id')
        OR (TABLE_NAME = 'product_images' AND COLUMN_NAME = 'product_id'))
");

$collations = $stmt->fetchAll();
$allMatch = true;
foreach ($collations as $col) {
    $match = $col['COLLATION_NAME'] === 'utf8mb4_turkish_ci' ? '✅' : '❌';
    echo "$match " . $col['TABLE_NAME'] . "." . $col['COLUMN_NAME'] . " = " . $col['COLLATION_NAME'] . "\n";
    if ($col['COLLATION_NAME'] !== 'utf8mb4_turkish_ci') {
        $allMatch = false;
    }
}
echo "\n";

// 6. Test INSERT
echo "6️⃣ TEST INSERT:\n";
echo "───────────────────────────────────────\n";
try {
    $testId = 'prd-12888ad6b6eb';

    $stmt = $pdo->prepare("
        INSERT INTO product_images
        (product_id, filename, storage_driver, url, mime_type, is_primary, sort_order, size_bytes, width, height)
        VALUES (?, ?, 'local', ?, 'image/jpeg', 1, 0, 12345, 800, 600)
    ");

    $stmt->execute([
        $testId,
        'test_final_' . time() . '.jpg',
        '/api/upload/product-file/test_final_' . time() . '.jpg'
    ]);

    $insertedId = $pdo->lastInsertId();

    echo "✅ TEST INSERT BAŞARILI! ID: $insertedId\n";

    // Test kaydını sil
    $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([$insertedId]);
    echo "✅ Test kaydı silindi\n";

} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
}
echo "\n";

echo "═══════════════════════════════════════\n";
echo "SONUÇ:\n";
echo "═══════════════════════════════════════\n\n";

if ($allMatch) {
    echo "✅ TÜM COLLATION'LAR EŞLEŞİYOR!\n";
    echo "🎉 Artık fotoğraf yükleyebilirsiniz!\n\n";
    echo "http://localhost/final-upload-test.html adresinden\n";
    echo "gerçek fotoğraf yükleme testini yapın.\n";
} else {
    echo "❌ Hala collation uyuşmazlığı var.\n";
    echo "Manuel düzeltme gerekebilir.\n";
}

echo "</pre>";
?>
