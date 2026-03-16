<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/helpers.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Trigger İçeriklerini İncele</h1>";
echo "<pre>";

$pdo = db();

echo "═══════════════════════════════════════\n";
echo "PRODUCT_IMAGES TRIGGER'LARI\n";
echo "═══════════════════════════════════════\n\n";

$stmt = $pdo->query("
    SELECT
        TRIGGER_NAME,
        EVENT_MANIPULATION,
        ACTION_TIMING,
        ACTION_STATEMENT
    FROM information_schema.TRIGGERS
    WHERE TRIGGER_SCHEMA = 'boomeritems'
      AND EVENT_OBJECT_TABLE = 'product_images'
    ORDER BY ACTION_TIMING, EVENT_MANIPULATION
");

$triggers = $stmt->fetchAll();

foreach ($triggers as $trigger) {
    echo "═══════════════════════════════════════\n";
    echo "Trigger: {$trigger['TRIGGER_NAME']}\n";
    echo "Timing: {$trigger['ACTION_TIMING']} {$trigger['EVENT_MANIPULATION']}\n";
    echo "═══════════════════════════════════════\n\n";
    echo "SQL:\n";
    echo "───────────────────────────────────────\n";
    echo $trigger['ACTION_STATEMENT'];
    echo "\n\n\n";
}

echo "═══════════════════════════════════════\n";
echo "ÇÖZÜM ÖNERİSİ:\n";
echo "═══════════════════════════════════════\n\n";
echo "Yukarıdaki trigger'larda string karşılaştırması varsa,\n";
echo "COLLATE utf8mb4_turkish_ci eklememiz gerekiyor.\n\n";
echo "Ya da trigger'ları geçici olarak devre dışı bırakıp\n";
echo "INSERT testini tekrar deneyebiliriz.\n";

echo "</pre>";
?>
