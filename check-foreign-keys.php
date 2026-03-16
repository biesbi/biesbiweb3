<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/helpers.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Foreign Key ve Constraint Kontrolü</h1>";
echo "<pre>";

$pdo = db();

echo "═══════════════════════════════════════\n";
echo "PRODUCT_IMAGES TABLOSU YAPISAL KONTROL\n";
echo "═══════════════════════════════════════\n\n";

// Foreign key kontrolü
echo "1️⃣ FOREIGN KEY CONSTRAINTS:\n";
echo "───────────────────────────────────────\n";
$stmt = $pdo->query("
    SELECT
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'boomeritems'
      AND TABLE_NAME = 'product_images'
      AND REFERENCED_TABLE_NAME IS NOT NULL
");
$fks = $stmt->fetchAll();

if (count($fks) > 0) {
    echo "⚠️  FOREIGN KEY BULUNDU:\n\n";
    foreach ($fks as $fk) {
        echo "Constraint: " . $fk['CONSTRAINT_NAME'] . "\n";
        echo "Column: " . $fk['COLUMN_NAME'] . "\n";
        echo "References: " . $fk['REFERENCED_TABLE_NAME'] . "." . $fk['REFERENCED_COLUMN_NAME'] . "\n";
        echo "\n";
    }
} else {
    echo "✅ Foreign key yok\n";
}
echo "\n";

// Trigger kontrolü
echo "2️⃣ TRIGGERS:\n";
echo "───────────────────────────────────────\n";
$stmt = $pdo->query("
    SELECT TRIGGER_NAME, EVENT_MANIPULATION, ACTION_TIMING
    FROM information_schema.TRIGGERS
    WHERE TRIGGER_SCHEMA = 'boomeritems'
      AND EVENT_OBJECT_TABLE = 'product_images'
");
$triggers = $stmt->fetchAll();

if (count($triggers) > 0) {
    echo "⚠️  TRIGGER BULUNDU:\n\n";
    foreach ($triggers as $trigger) {
        echo $trigger['ACTION_TIMING'] . " " . $trigger['EVENT_MANIPULATION'] . ": " . $trigger['TRIGGER_NAME'] . "\n";
    }
} else {
    echo "✅ Trigger yok\n";
}
echo "\n";

// Tablo CREATE statement'ını al
echo "3️⃣ TABLO CREATE STATEMENT:\n";
echo "═══════════════════════════════════════\n";
$stmt = $pdo->query("SHOW CREATE TABLE product_images");
$create = $stmt->fetch();
echo $create['Create Table'];
echo "\n\n";

echo "═══════════════════════════════════════\n";
echo "ÇÖZÜM:\n";
echo "═══════════════════════════════════════\n\n";
echo "Eğer yukarıda FOREIGN KEY varsa,\n";
echo "referenced table'ın collation'ı ile conflict oluyor olabilir.\n\n";
echo "Foreign key'i geçici olarak kaldırıp tekrar ekleyebiliriz.\n";

echo "</pre>";
?>
