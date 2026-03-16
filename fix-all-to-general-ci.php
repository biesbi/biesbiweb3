<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Tüm Veritabanını utf8mb4_general_ci'ye Çevir</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem; }
  .container { max-width: 900px; margin: 0 auto; background: #1e293b; padding: 2rem; border-radius: 12px; }
  h1 { color: #38bdf8; }
  .success { background: #064e3b; color: #10b981; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .error { background: #7f1d1d; color: #fca5a5; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .info { background: #1e3a5f; color: #93c5fd; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .warning { background: #78350f; color: #fbbf24; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  button { background: #38bdf8; color: #0f172a; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; margin: 0.5rem 0.5rem 0 0; }
  button:hover { background: #0ea5e9; }
  button.danger { background: #dc2626; color: white; }
  code { background: #334155; padding: 0.2rem 0.5rem; border-radius: 4px; color: #38bdf8; }
  ul { line-height: 1.8; }
</style>
</head>
<body>
<div class="container">
  <h1>🔧 Tüm Veritabanını utf8mb4_general_ci'ye Çevir</h1>

  <div class="info">
    <strong>📚 Türkçe Karakterler Hakkında Bilgi:</strong><br><br>

    <strong>utf8mb4_general_ci:</strong>
    <ul>
      <li>✅ Türkçe karakterleri (ğ, ü, ş, ı, ö, ç, Ğ, Ü, Ş, İ, Ö, Ç) <strong>saklar ve görüntüler</strong></li>
      <li>✅ Tüm emoji ve özel karakterleri destekler</li>
      <li>⚠️ Sıralamada Türkçe alfabesini dikkate almaz (örn: "ç" ile "c" aynı kabul edilir)</li>
      <li>✅ Daha hızlı performans</li>
    </ul>

    <strong>utf8mb4_turkish_ci:</strong>
    <ul>
      <li>✅ Türkçe karakterleri saklar ve görüntüler</li>
      <li>✅ Türkçe alfabetik sıralamayı doğru yapar (ç → d arasında)</li>
      <li>⚠️約간 daha yavaş performans</li>
    </ul>

    <strong>🎯 Sonuç:</strong> E-ticaret sitesi için <code>utf8mb4_general_ci</code> <strong>tamamen yeterli</strong>!<br>
    Türkçe karakterler sorunsuz çalışır. Tek fark alfabetik sıralamada.
  </div>

  <?php
  require_once __DIR__ . '/api/config.php';
  require_once __DIR__ . '/api/helpers.php';

  $messages = [];

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert'])) {
      try {
          $pdo = db();
          $targetCollation = 'utf8mb4_general_ci';

          $messages[] = [
              'type' => 'info',
              'text' => "🎯 Hedef collation: <code>$targetCollation</code>"
          ];

          // 1. Veritabanının kendisini değiştir
          $dbName = DB_NAME;
          $pdo->exec("ALTER DATABASE `$dbName`
              CHARACTER SET utf8mb4 COLLATE $targetCollation");

          $messages[] = [
              'type' => 'success',
              'text' => "✅ Veritabanı <code>$dbName</code> default collation güncellendi"
          ];

          // 2. Tüm tabloları bul ve dönüştür
          $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

          foreach ($tables as $table) {
              try {
                  $pdo->exec("ALTER TABLE `$table`
                      CONVERT TO CHARACTER SET utf8mb4 COLLATE $targetCollation");

                  $messages[] = [
                      'type' => 'success',
                      'text' => "✅ <code>$table</code> tablosu dönüştürüldü"
                  ];
              } catch (Exception $e) {
                  $messages[] = [
                      'type' => 'error',
                      'text' => "❌ <code>$table</code> hatası: " . htmlspecialchars($e->getMessage())
                  ];
              }
          }

          $messages[] = [
              'type' => 'success',
              'text' => "🎉 Tüm işlemler tamamlandı! " . count($tables) . " tablo güncellendi.<br><br>" .
                       "Artık fotoğraf yükleyebilirsiniz!"
          ];

      } catch (Exception $e) {
          $messages[] = [
              'type' => 'error',
              'text' => "❌ HATA: " . htmlspecialchars($e->getMessage())
          ];
      }
  } else {
      // Mevcut durumu göster
      try {
          $pdo = db();

          // Veritabanı collation
          $dbName = DB_NAME;
          $stmt = $pdo->query("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
              FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$dbName'");
          $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);

          $messages[] = [
              'type' => 'info',
              'text' => "<strong>Veritabanı:</strong> <code>$dbName</code><br>" .
                       "Default Collation: <code>" . ($dbInfo['DEFAULT_COLLATION_NAME'] ?? 'N/A') . "</code>"
          ];

          // Tabloları ve collation'larını listele
          $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
          $collations = [];

          foreach ($tables as $table) {
              $stmt = $pdo->query("SHOW TABLE STATUS WHERE Name = '$table'");
              $info = $stmt->fetch(PDO::FETCH_ASSOC);
              $collations[$info['Collation'] ?? 'unknown'][] = $table;
          }

          $tableList = "<strong>Mevcut Tablolar:</strong><br><br>";
          foreach ($collations as $collation => $tableNames) {
              $tableList .= "<code>$collation</code>: " . implode(', ', $tableNames) . "<br>";
          }

          $messages[] = [
              'type' => 'info',
              'text' => $tableList
          ];

      } catch (Exception $e) {
          $messages[] = [
              'type' => 'error',
              'text' => "❌ Kontrol hatası: " . htmlspecialchars($e->getMessage())
          ];
      }
  }

  // Mesajları göster
  foreach ($messages as $msg) {
      echo "<div class='{$msg['type']}'>{$msg['text']}</div>";
  }
  ?>

  <?php if (!isset($_POST['convert'])): ?>
  <form method="POST">
    <button type="submit" name="convert" class="danger">
      🔄 Tüm Veritabanını utf8mb4_general_ci'ye Çevir
    </button>
  </form>

  <div class="warning">
    <strong>⚠️ UYARI:</strong><br>
    Bu işlem geri alınamaz! Ama sorun çıkarmaz, sadece collation değişir.<br>
    Verileriniz kaybolmaz, Türkçe karakterler sorunsuz çalışır.
  </div>
  <?php else: ?>
  <div class="success">
    <strong>✅ İşlem Tamamlandı!</strong><br><br>
    Şimdi <a href="/debug-gallery.html" style="color: #10b981; font-weight: bold;">debug-gallery.html</a>
    sayfasına gidip fotoğraf yüklemeyi deneyin.<br><br>
    <a href="/list-products.php" style="color: #10b981; font-weight: bold;">Ürün listesine göz atın</a>
  </div>
  <?php endif; ?>

  <div class="warning" style="margin-top: 2rem;">
    <strong>⚠️ GÜVENLİK:</strong><br>
    Bu dosyayı kullandıktan sonra <strong>mutlaka silin</strong>!<br>
    <code>del c:\xampp\htdocs\fix-all-to-general-ci.php</code>
  </div>
</div>
</body>
</html>
