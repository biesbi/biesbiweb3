<?php
/**
 * Detaylı Upload Test - Hangi sorgu hata veriyor?
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/helpers.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Detaylı Upload Sorgu Testi</h1>";
echo "<pre>";

$productId = 'prd-12888ad6b6eb';

echo "═══════════════════════════════════════\n";
echo "TEST EDİLECEK ÜRÜN ID: $productId\n";
echo "═══════════════════════════════════════\n\n";

// PDO bağlantısını al
$pdo = db();

// Bağlantı collation'ını kontrol et
echo "1️⃣ BAĞLANTI COLLATION KONTROLÜ:\n";
echo "───────────────────────────────────────\n";
$stmt = $pdo->query("SELECT @@collation_connection");
$collation = $stmt->fetchColumn();
echo "Bağlantı Collation: $collation\n";

if ($collation !== 'utf8mb4_turkish_ci') {
    echo "⚠️  WARNING: Bağlantı collation'ı turkish_ci değil!\n";
    echo "Düzeltiliyor...\n";
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci");
    $stmt = $pdo->query("SELECT @@collation_connection");
    $collation = $stmt->fetchColumn();
    echo "Yeni Collation: $collation\n";
}
echo "\n";

// products tablosu ID collation kontrolü
echo "2️⃣ PRODUCTS TABLOSU ID COLLATION:\n";
echo "───────────────────────────────────────\n";
$stmt = $pdo->query("
    SELECT COLUMN_NAME, COLLATION_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'boomeritems'
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'id'
");
$col = $stmt->fetch();
echo "Column: " . $col['COLUMN_NAME'] . "\n";
echo "Collation: " . $col['COLLATION_NAME'] . "\n\n";

// product_images tablosu product_id collation kontrolü
echo "3️⃣ PRODUCT_IMAGES TABLOSU PRODUCT_ID COLLATION:\n";
echo "───────────────────────────────────────\n";
$stmt = $pdo->query("
    SELECT COLUMN_NAME, COLLATION_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'boomeritems'
      AND TABLE_NAME = 'product_images'
      AND COLUMN_NAME = 'product_id'
");
$col = $stmt->fetch();
echo "Column: " . $col['COLUMN_NAME'] . "\n";
echo "Collation: " . $col['COLLATION_NAME'] . "\n\n";

// Test sorguları
echo "4️⃣ TEST SORGULARI:\n";
echo "═══════════════════════════════════════\n\n";

// Sorgu 1: Ürün kontrolü (upload.php satır 30)
echo "Test 1: SELECT id FROM products WHERE id = ?\n";
echo "───────────────────────────────────────\n";
try {
    $stmt = $pdo->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$productId]);
    $result = $stmt->fetch();
    if ($result) {
        echo "✅ BAŞARILI: Ürün bulundu: " . $result['id'] . "\n";
    } else {
        echo "⚠️  Ürün bulunamadı\n";
    }
} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
}
echo "\n";

// Sorgu 2: COLLATE ile ürün kontrolü
echo "Test 2: SELECT id FROM products WHERE id COLLATE utf8mb4_turkish_ci = ?\n";
echo "───────────────────────────────────────\n";
try {
    $stmt = $pdo->prepare('SELECT id FROM products WHERE id COLLATE utf8mb4_turkish_ci = ? LIMIT 1');
    $stmt->execute([$productId]);
    $result = $stmt->fetch();
    if ($result) {
        echo "✅ BAŞARILI: Ürün bulundu: " . $result['id'] . "\n";
    } else {
        echo "⚠️  Ürün bulunamadı\n";
    }
} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
}
echo "\n";

// Sorgu 3: Product images sayısı
echo "Test 3: SELECT COUNT(*) FROM product_images WHERE product_id = ?\n";
echo "───────────────────────────────────────\n";
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ?');
    $stmt->execute([$productId]);
    $count = $stmt->fetchColumn();
    echo "✅ BAŞARILI: $count adet görsel bulundu\n";
} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
}
echo "\n";

// Sorgu 4: Product images sayısı COLLATE ile
echo "Test 4: SELECT COUNT(*) FROM product_images WHERE product_id COLLATE utf8mb4_turkish_ci = ?\n";
echo "───────────────────────────────────────\n";
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id COLLATE utf8mb4_turkish_ci = ?');
    $stmt->execute([$productId]);
    $count = $stmt->fetchColumn();
    echo "✅ BAŞARILI: $count adet görsel bulundu\n";
} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
}
echo "\n";

// Sorgu 5: INSERT simülasyonu (gerçek INSERT yapmadan önce test)
echo "Test 5: INSERT INTO product_images (product_id, filename, ...) VALUES (?, ?, ...)\n";
echo "───────────────────────────────────────\n";
try {
    // Sadece validasyon, gerçek INSERT yapılmayacak
    $stmt = $pdo->prepare('
        INSERT INTO product_images
        (product_id, filename, storage_driver, url, mime_type, is_primary, sort_order, size_bytes, width, height)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    // Gerçek çalıştırma yapmıyoruz, sadece prepare ediyoruz
    echo "✅ BAŞARILI: INSERT sorgusu prepare edildi\n";
} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
}
echo "\n";

echo "═══════════════════════════════════════\n";
echo "SONUÇ:\n";
echo "═══════════════════════════════════════\n\n";
echo "Yukarıdaki testlerde hangi sorgu HATA veriyorsa,\n";
echo "o sorguda collation sorunu var demektir.\n\n";
echo "Eğer TÜM testler başarılıysa, sorun başka bir yerde.\n";

echo "</pre>";
?>
