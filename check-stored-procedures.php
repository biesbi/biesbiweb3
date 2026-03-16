<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/helpers.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Stored Procedure İnceleme</h1>";
echo "<pre>";

$pdo = db();

echo "═══════════════════════════════════════\n";
echo "REFRESH_PRODUCT_IMAGE_CACHE PROCEDURE\n";
echo "═══════════════════════════════════════\n\n";

// Stored procedure'ün tanımını al
try {
    $stmt = $pdo->query("SHOW CREATE PROCEDURE refresh_product_image_cache");
    $result = $stmt->fetch();

    if ($result) {
        echo "Procedure bulundu!\n";
        echo "═══════════════════════════════════════\n\n";
        echo $result['Create Procedure'];
        echo "\n\n";
    } else {
        echo "⚠️  Procedure bulunamadı!\n";
    }
} catch (PDOException $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n\n";

    // Alternatif yöntem - information_schema'dan al
    echo "Alternatif yöntem deneniyor...\n";
    echo "───────────────────────────────────────\n";

    try {
        $stmt = $pdo->query("
            SELECT ROUTINE_DEFINITION
            FROM information_schema.ROUTINES
            WHERE ROUTINE_SCHEMA = 'boomeritems'
              AND ROUTINE_NAME = 'refresh_product_image_cache'
              AND ROUTINE_TYPE = 'PROCEDURE'
        ");

        $result = $stmt->fetch();
        if ($result) {
            echo "\nProcedure Definition:\n";
            echo "═══════════════════════════════════════\n\n";
            echo $result['ROUTINE_DEFINITION'];
            echo "\n\n";
        } else {
            echo "⚠️  Procedure bulunamadı!\n";
        }
    } catch (PDOException $e2) {
        echo "❌ HATA: " . $e2->getMessage() . "\n";
    }
}

echo "═══════════════════════════════════════\n";
echo "ÇÖZÜM:\n";
echo "═══════════════════════════════════════\n\n";
echo "Bu procedure içinde product_id karşılaştırması varsa,\n";
echo "COLLATE utf8mb4_turkish_ci eklememiz gerekecek.\n\n";
echo "Ya da procedure'ü DROP edip düzeltilmiş versiyonunu\n";
echo "CREATE edebiliriz.\n";

echo "</pre>";
?>
