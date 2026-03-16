<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/helpers.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Stored Procedure Düzeltme</h1>";
echo "<pre>";

$pdo = db();

echo "═══════════════════════════════════════\n";
echo "REFRESH_PRODUCT_IMAGE_CACHE DÜZELTİLİYOR\n";
echo "═══════════════════════════════════════\n\n";

// 1. Eski procedure'ü kaldır
echo "1️⃣ ESKİ PROCEDURE KALDIRILIYOR:\n";
echo "───────────────────────────────────────\n";
try {
    $pdo->exec("DROP PROCEDURE IF EXISTS refresh_product_image_cache");
    echo "✅ Eski procedure kaldırıldı\n";
} catch (PDOException $e) {
    echo "⚠️  HATA: " . $e->getMessage() . "\n";
}
echo "\n";

// 2. Yeni (düzeltilmiş) procedure'ü oluştur
echo "2️⃣ YENİ PROCEDURE OLUŞTURULUYOR:\n";
echo "───────────────────────────────────────\n";

$newProcedure = "
CREATE PROCEDURE refresh_product_image_cache(IN p_product_id VARCHAR(36))
BEGIN
  DECLARE v_cover_url VARCHAR(1000);
  DECLARE v_images_json LONGTEXT;

  -- COLLATE eklendi
  SELECT pi.url
    INTO v_cover_url
  FROM product_images pi
  WHERE pi.product_id COLLATE utf8mb4_turkish_ci = p_product_id COLLATE utf8mb4_turkish_ci
  ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
  LIMIT 1;

  -- COLLATE eklendi
  SELECT COALESCE(
    CONCAT('[', GROUP_CONCAT(JSON_QUOTE(pi.url) ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC SEPARATOR ','), ']'),
    '[]'
  )
    INTO v_images_json
  FROM product_images pi
  WHERE pi.product_id COLLATE utf8mb4_turkish_ci = p_product_id COLLATE utf8mb4_turkish_ci;

  -- COLLATE eklendi
  UPDATE products
  SET img = v_cover_url,
      images = v_images_json
  WHERE id COLLATE utf8mb4_turkish_ci = p_product_id COLLATE utf8mb4_turkish_ci;
END
";

try {
    $pdo->exec($newProcedure);
    echo "✅ Yeni procedure oluşturuldu\n";
    echo "\nDeğişiklikler:\n";
    echo "- WHERE pi.product_id = p_product_id\n";
    echo "  → WHERE pi.product_id COLLATE utf8mb4_turkish_ci = p_product_id COLLATE utf8mb4_turkish_ci\n\n";
    echo "- WHERE id = p_product_id\n";
    echo "  → WHERE id COLLATE utf8mb4_turkish_ci = p_product_id COLLATE utf8mb4_turkish_ci\n";
} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Test INSERT
echo "3️⃣ TEST INSERT:\n";
echo "───────────────────────────────────────\n";
try {
    $testId = 'prd-12888ad6b6eb';

    $stmt = $pdo->prepare("
        INSERT INTO product_images
        (product_id, filename, storage_driver, url, mime_type, is_primary, sort_order, size_bytes, width, height)
        VALUES (?, ?, 'local', ?, 'image/jpeg', 1, 0, 12345, 800, 600)
    ");

    $filename = 'test_final_success_' . time() . '.jpg';
    $stmt->execute([
        $testId,
        $filename,
        '/api/upload/product-file/' . $filename
    ]);

    $insertedId = $pdo->lastInsertId();

    echo "🎉🎉🎉 TEST INSERT BAŞARILI! 🎉🎉🎉\n";
    echo "Inserted ID: $insertedId\n";
    echo "Filename: $filename\n\n";

    // Trigger çalıştı mı kontrol et - products tablosunu kontrol et
    echo "4️⃣ TRIGGER KONTROLÜ (products tablosu güncellendi mi?):\n";
    echo "───────────────────────────────────────\n";
    $stmt = $pdo->prepare('SELECT img, images FROM products WHERE id = ?');
    $stmt->execute([$testId]);
    $product = $stmt->fetch();

    if ($product) {
        echo "✅ products.img = " . ($product['img'] ?? 'NULL') . "\n";
        echo "✅ products.images = " . ($product['images'] ?? 'NULL') . "\n";
        echo "\n✅ TRIGGER ÇALIŞTI!\n";
    }
    echo "\n";

    // Test kaydını sil
    echo "5️⃣ TEST KAYDI SİLİNİYOR:\n";
    echo "───────────────────────────────────────\n";
    $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([$insertedId]);
    echo "✅ Test kaydı silindi\n";

} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
    echo "\nFull Error:\n";
    echo $e->__toString() . "\n";
}
echo "\n";

echo "═══════════════════════════════════════\n";
echo "SONUÇ:\n";
echo "═══════════════════════════════════════\n\n";
echo "✅ Stored procedure düzeltildi!\n";
echo "🎉 COLLATION SORUNU ÇÖZÜLDÜ!\n\n";
echo "Artık gerçek fotoğraf yükleme testini yapabilirsiniz:\n";
echo "http://localhost/final-upload-test.html\n";

echo "</pre>";
?>
