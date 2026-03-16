<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/helpers.php';

header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);

echo "<h1>🔧 TÜM Foreign Key Collation Düzeltme</h1>";
echo "<pre>";

$pdo = db();

echo "═══════════════════════════════════════\n";
echo "TÜM FOREIGN KEY'LERİ DÜZELT\n";
echo "═══════════════════════════════════════\n\n";

// 1. products.id ile ilgili tüm foreign key'leri bul
echo "1️⃣ PRODUCTS.ID İLE İLGİLİ FOREIGN KEY'LER:\n";
echo "───────────────────────────────────────\n";

$stmt = $pdo->query("
    SELECT
        TABLE_NAME,
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'boomeritems'
      AND REFERENCED_TABLE_NAME = 'products'
      AND REFERENCED_COLUMN_NAME = 'id'
");

$foreignKeys = $stmt->fetchAll();
echo "Toplam " . count($foreignKeys) . " foreign key bulundu:\n\n";

foreach ($foreignKeys as $fk) {
    echo "- " . $fk['TABLE_NAME'] . "." . $fk['COLUMN_NAME'];
    echo " (FK: " . $fk['CONSTRAINT_NAME'] . ")\n";
}
echo "\n";

// 2. Tüm foreign key'leri kaldır
echo "2️⃣ TÜM FOREIGN KEY'LER KALDIRILIYOR:\n";
echo "───────────────────────────────────────\n";

$droppedFks = [];
foreach ($foreignKeys as $fk) {
    try {
        $sql = "ALTER TABLE `{$fk['TABLE_NAME']}` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`";
        $pdo->exec($sql);
        echo "✅ {$fk['TABLE_NAME']}.{$fk['CONSTRAINT_NAME']} kaldırıldı\n";
        $droppedFks[] = $fk;
    } catch (PDOException $e) {
        echo "⚠️  {$fk['TABLE_NAME']}.{$fk['CONSTRAINT_NAME']} HATA: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// 3. products.id kolonunu düzelt
echo "3️⃣ PRODUCTS.ID KOLONU DÜZELTİLİYOR:\n";
echo "───────────────────────────────────────\n";
try {
    $pdo->exec("
        ALTER TABLE products
        MODIFY COLUMN id VARCHAR(50)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_turkish_ci
        NOT NULL
    ");
    echo "✅ products.id → utf8mb4_turkish_ci\n";
} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Her bir referans kolonunu düzelt
echo "4️⃣ REFERANS KOLONLARI DÜZELTİLİYOR:\n";
echo "───────────────────────────────────────\n";

foreach ($droppedFks as $fk) {
    try {
        // Kolon tipini al
        $stmt = $pdo->query("
            SELECT COLUMN_TYPE, IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = 'boomeritems'
              AND TABLE_NAME = '{$fk['TABLE_NAME']}'
              AND COLUMN_NAME = '{$fk['COLUMN_NAME']}'
        ");
        $colInfo = $stmt->fetch();

        $nullable = $colInfo['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL';

        // VARCHAR tipini extract et
        preg_match('/varchar\((\d+)\)/i', $colInfo['COLUMN_TYPE'], $matches);
        $length = $matches[1] ?? '50';

        $sql = "
            ALTER TABLE `{$fk['TABLE_NAME']}`
            MODIFY COLUMN `{$fk['COLUMN_NAME']}` VARCHAR($length)
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_turkish_ci
            $nullable
        ";

        $pdo->exec($sql);
        echo "✅ {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} → utf8mb4_turkish_ci\n";
    } catch (PDOException $e) {
        echo "⚠️  {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} HATA: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// 5. Foreign key'leri yeniden ekle
echo "5️⃣ FOREIGN KEY'LER YENİDEN EKLENİYOR:\n";
echo "───────────────────────────────────────\n";

foreach ($droppedFks as $fk) {
    try {
        $sql = "
            ALTER TABLE `{$fk['TABLE_NAME']}`
            ADD CONSTRAINT `{$fk['CONSTRAINT_NAME']}`
            FOREIGN KEY (`{$fk['COLUMN_NAME']}`)
            REFERENCES `{$fk['REFERENCED_TABLE_NAME']}`(`{$fk['REFERENCED_COLUMN_NAME']}`)
            ON DELETE CASCADE
        ";

        $pdo->exec($sql);
        echo "✅ {$fk['CONSTRAINT_NAME']} yeniden eklendi\n";
    } catch (PDOException $e) {
        echo "⚠️  {$fk['CONSTRAINT_NAME']} HATA: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// 6. Kontrol et
echo "6️⃣ KONTROL:\n";
echo "───────────────────────────────────────\n";

$stmt = $pdo->query("
    SELECT
        TABLE_NAME,
        COLUMN_NAME,
        COLLATION_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'boomeritems'
      AND TABLE_NAME IN ('products', 'product_images', 'cart_items', 'order_items')
      AND COLUMN_NAME IN ('id', 'product_id')
    ORDER BY TABLE_NAME, COLUMN_NAME
");

$collations = $stmt->fetchAll();
$allMatch = true;
foreach ($collations as $col) {
    $match = $col['COLLATION_NAME'] === 'utf8mb4_turkish_ci' ? '✅' : '❌';
    echo "$match " . str_pad($col['TABLE_NAME'] . "." . $col['COLUMN_NAME'], 35) . " = " . $col['COLLATION_NAME'] . "\n";
    if ($col['COLLATION_NAME'] !== 'utf8mb4_turkish_ci') {
        $allMatch = false;
    }
}
echo "\n";

// 7. Test INSERT
echo "7️⃣ TEST INSERT:\n";
echo "───────────────────────────────────────\n";
try {
    $testId = 'prd-12888ad6b6eb';

    $stmt = $pdo->prepare("
        INSERT INTO product_images
        (product_id, filename, storage_driver, url, mime_type, is_primary, sort_order, size_bytes, width, height)
        VALUES (?, ?, 'local', ?, 'image/jpeg', 1, 0, 12345, 800, 600)
    ");

    $filename = 'test_success_' . time() . '.jpg';
    $stmt->execute([
        $testId,
        $filename,
        '/api/upload/product-file/' . $filename
    ]);

    $insertedId = $pdo->lastInsertId();

    echo "🎉 TEST INSERT BAŞARILI! ID: $insertedId\n";
    echo "✅ COLLATION SORUNU ÇÖZÜLDÜ!\n\n";

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
    echo "✅ TÜM COLLATION'LAR DÜZELTILDI!\n";
    echo "🎉 Artık fotoğraf yükleyebilirsiniz!\n\n";
    echo "Test için:\n";
    echo "http://localhost/final-upload-test.html\n";
} else {
    echo "⚠️  Bazı collation'lar hala uyuşmuyor.\n";
}

echo "</pre>";
?>
