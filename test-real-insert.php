<?php
/**
 * Gerçek INSERT Testi - Upload sırasında ne oluyor?
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/helpers.php';
require_once __DIR__ . '/api/services/UploadService.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Gerçek INSERT Testi</h1>";
echo "<pre>";

$productId = 'prd-12888ad6b6eb';

echo "═══════════════════════════════════════\n";
echo "GERÇEK UPLOAD FLOW SİMÜLASYONU\n";
echo "═══════════════════════════════════════\n\n";

$pdo = db();

// Bağlantı durumunu kontrol et
echo "1️⃣ Bağlantı Collation:\n";
$stmt = $pdo->query("SELECT @@collation_connection");
echo "   " . $stmt->fetchColumn() . "\n\n";

// UploadService'in kullandığı sorguları test et
echo "2️⃣ DESCRIBE product_images sorgusu:\n";
echo "───────────────────────────────────────\n";
try {
    $stmt = $pdo->query('DESCRIBE product_images');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ BAŞARILI: " . count($columns) . " kolon bulundu\n";
    foreach ($columns as $col) {
        echo "   - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
}
echo "\n";

// Primary güncelleme sorgusu
echo "3️⃣ UPDATE product_images SET is_primary = 0 WHERE product_id = ?\n";
echo "───────────────────────────────────────\n";
try {
    $stmt = $pdo->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id COLLATE utf8mb4_turkish_ci = ?');
    $stmt->execute([$productId]);
    echo "✅ BAŞARILI: " . $stmt->rowCount() . " satır güncellendi\n";
} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
}
echo "\n";

// Count sorgusu
echo "4️⃣ SELECT COUNT(*) FROM product_images WHERE product_id = ?\n";
echo "───────────────────────────────────────\n";
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id COLLATE utf8mb4_turkish_ci = ?');
    $stmt->execute([$productId]);
    $count = $stmt->fetchColumn();
    echo "✅ BAŞARILI: $count görsel var\n";
} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
}
echo "\n";

// Gerçek INSERT sorgusu
echo "5️⃣ GERÇEK INSERT SORGUSU (test verisi ile):\n";
echo "───────────────────────────────────────\n";
try {
    $insertData = [
        'product_id' => $productId,
        'filename' => 'test_' . time() . '.jpg',
        'storage_driver' => 'local',
        'url' => '/api/upload/product-file/test_' . time() . '.jpg',
        'mime_type' => 'image/jpeg',
        'alt_text' => null,
        'is_primary' => 1,
        'sort_order' => 0,
        'size_bytes' => 12345,
        'width' => 800,
        'height' => 600,
        'uploaded_by' => null,
    ];

    $columns = [];
    $placeholders = [];
    $values = [];

    foreach ($insertData as $column => $value) {
        $columns[] = $column;
        $placeholders[] = '?';
        $values[] = $value;
    }

    $sql = 'INSERT INTO product_images (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

    echo "SQL: $sql\n";
    echo "Values:\n";
    foreach ($values as $i => $v) {
        echo "   [$i] = " . var_export($v, true) . "\n";
    }
    echo "\n";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    $insertedId = $pdo->lastInsertId();

    echo "✅ BAŞARILI: Görsel eklendi! ID: $insertedId\n\n";

    // Eklenen görseli kontrol et
    echo "6️⃣ Eklenen görseli kontrol et:\n";
    echo "───────────────────────────────────────\n";
    $stmt = $pdo->prepare('SELECT * FROM product_images WHERE id = ?');
    $stmt->execute([$insertedId]);
    $image = $stmt->fetch();

    if ($image) {
        echo "✅ Görsel bulundu:\n";
        echo "   ID: " . $image['id'] . "\n";
        echo "   Product ID: " . $image['product_id'] . "\n";
        echo "   Filename: " . $image['filename'] . "\n";
        echo "   URL: " . $image['url'] . "\n";

        // Test görseli sil
        echo "\n7️⃣ Test görseli siliniyor...\n";
        $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([$insertedId]);
        echo "✅ Test görseli silindi\n";
    }

} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "\nFull Exception:\n";
    echo $e->__toString() . "\n";
}

echo "\n═══════════════════════════════════════\n";
echo "SONUÇ:\n";
echo "═══════════════════════════════════════\n\n";
echo "Eğer yukarıdaki INSERT başarılıysa,\n";
echo "sorun UploadService içindeki dosya işleme kısmında olabilir.\n";

echo "</pre>";
?>
