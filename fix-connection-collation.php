<?php
/**
 * PDO Bağlantı Collation Düzeltmesi
 *
 * Bu script veritabanı bağlantısının collation ayarlarını kontrol eder ve düzeltir.
 */

// Bağlantıyı oluştur
$pdo = new PDO(
    'mysql:host=localhost;dbname=boomeritems;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

echo "<h1>🔧 PDO Bağlantı Collation Test & Fix</h1>";
echo "<pre>";

// Mevcut bağlantı collation'ını kontrol et
echo "═══════════════════════════════════════\n";
echo "MEVCUT BAĞLANTI AYARLARI:\n";
echo "═══════════════════════════════════════\n\n";

$stmt = $pdo->query("SHOW VARIABLES LIKE 'character_set%'");
$charsets = $stmt->fetchAll();
foreach ($charsets as $row) {
    echo str_pad($row['Variable_name'], 40) . " = " . $row['Value'] . "\n";
}

echo "\n";

$stmt = $pdo->query("SHOW VARIABLES LIKE 'collation%'");
$collations = $stmt->fetchAll();
foreach ($collations as $row) {
    echo str_pad($row['Variable_name'], 40) . " = " . $row['Value'] . "\n";
}

// Bağlantı collation'ını düzelt
echo "\n";
echo "═══════════════════════════════════════\n";
echo "COLLATION DÜZELTİLİYOR...\n";
echo "═══════════════════════════════════════\n\n";

try {
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci");
    echo "✅ SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci çalıştırıldı\n\n";

    // Tekrar kontrol et
    echo "GÜNCEL BAĞLANTI AYARLARI:\n";
    echo "───────────────────────────────────────\n";

    $stmt = $pdo->query("SELECT @@character_set_client, @@character_set_connection, @@character_set_results, @@collation_connection");
    $current = $stmt->fetch();

    echo "character_set_client      = " . $current['@@character_set_client'] . "\n";
    echo "character_set_connection  = " . $current['@@character_set_connection'] . "\n";
    echo "character_set_results     = " . $current['@@character_set_results'] . "\n";
    echo "collation_connection      = " . $current['@@collation_connection'] . "\n";

    echo "\n";

    if ($current['@@collation_connection'] === 'utf8mb4_turkish_ci') {
        echo "✅ BAŞARILI! Bağlantı collation'ı utf8mb4_turkish_ci olarak ayarlandı.\n";
    } else {
        echo "❌ HATA! Collation hala " . $current['@@collation_connection'] . "\n";
    }

    // Test sorgusu çalıştır
    echo "\n";
    echo "═══════════════════════════════════════\n";
    echo "TEST SORGUSU:\n";
    echo "═══════════════════════════════════════\n\n";

    $testId = 'prd-12888ad6b6eb';
    $stmt = $pdo->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$testId]);
    $product = $stmt->fetch();

    if ($product) {
        echo "✅ Test başarılı! Ürün bulundu: " . $product['id'] . "\n";
        echo "\n";
        echo "🎉 Collation sorunu çözüldü! Artık fotoğraf yükleyebilirsiniz.\n";
    } else {
        echo "⚠️  Ürün bulunamadı (ID: $testId)\n";
        echo "   Farklı bir ürün ID'si ile test edin.\n";
    }

} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
}

echo "\n";
echo "═══════════════════════════════════════\n";
echo "SONUÇ:\n";
echo "═══════════════════════════════════════\n\n";
echo "helpers.php dosyasındaki db() fonksiyonuna şu satır eklendi:\n";
echo "PDO::MYSQL_ATTR_INIT_COMMAND => \"SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci\"\n\n";
echo "Bu ayar sayesinde her yeni bağlantı otomatik olarak doğru collation kullanacak.\n";

echo "</pre>";
?>
