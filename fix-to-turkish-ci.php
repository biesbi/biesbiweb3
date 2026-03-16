<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Veritabanını utf8mb4_turkish_ci'ye Çevir</title>
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
  button.turkish { background: #dc2626; color: white; }
  code { background: #334155; padding: 0.2rem 0.5rem; border-radius: 4px; color: #38bdf8; }
  ul { line-height: 1.8; }
  .progress { margin: 1rem 0; }
  .progress-item { padding: 0.5rem; margin: 0.25rem 0; border-radius: 4px; background: #334155; }
</style>
</head>
<body>
<div class="container">
  <h1>🇹🇷 Veritabanını utf8mb4_turkish_ci'ye Çevir</h1>

  <div class="info">
    <strong>🎯 utf8mb4_turkish_ci Avantajları:</strong><br><br>

    <ul>
      <li>✅ Türkçe karakterleri doğru sıralar (a, b, c, ç, d, e, f, g, ğ, h, ...)</li>
      <li>✅ Türkçe alfabetik aramalar doğru çalışır</li>
      <li>✅ "İ" ve "i" ayrımını yapar (büyük İ ≠ küçük i)</li>
      <li>✅ Tüm emoji ve özel karakterleri destekler</li>
      <li>✅ <strong>Türk kullanıcılar için en uygun seçim!</strong></li>
    </ul>

    <strong style="color: #10b981;">Bu, Türk e-ticaret siteleri için standart ve önerilen collation'dır!</strong>
  </div>

  <?php
  require_once __DIR__ . '/api/config.php';
  require_once __DIR__ . '/api/helpers.php';

  $messages = [];
  $startTime = microtime(true);

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert'])) {
      try {
          $pdo = db();
          $targetCollation = 'utf8mb4_turkish_ci';

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
              'text' => "✅ Veritabanı <code>$dbName</code> default collation: <code>$targetCollation</code>"
          ];

          // 2. Tüm tabloları bul ve dönüştür
          $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
          $successCount = 0;
          $errorCount = 0;

          $messages[] = [
              'type' => 'info',
              'text' => "📊 Toplam " . count($tables) . " tablo bulundu. Dönüştürülüyor..."
          ];

          foreach ($tables as $table) {
              try {
                  $pdo->exec("ALTER TABLE `$table`
                      CONVERT TO CHARACTER SET utf8mb4 COLLATE $targetCollation");

                  $messages[] = [
                      'type' => 'success',
                      'text' => "✅ <code>$table</code>"
                  ];
                  $successCount++;

              } catch (Exception $e) {
                  $messages[] = [
                      'type' => 'error',
                      'text' => "❌ <code>$table</code>: " . htmlspecialchars($e->getMessage())
                  ];
                  $errorCount++;
              }
          }

          $duration = round(microtime(true) - $startTime, 2);

          $messages[] = [
              'type' => 'success',
              'text' => "🎉 İşlem tamamlandı!<br><br>" .
                       "✅ Başarılı: <strong>$successCount</strong> tablo<br>" .
                       ($errorCount > 0 ? "❌ Hatalı: <strong>$errorCount</strong> tablo<br>" : "") .
                       "⏱️ Süre: <strong>{$duration}s</strong><br><br>" .
                       "<strong>Artık tüm veritabanınız Türkçe karakterleri tam destekliyor!</strong>"
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

          $currentCollation = $dbInfo['DEFAULT_COLLATION_NAME'] ?? 'N/A';
          $isTurkish = $currentCollation === 'utf8mb4_turkish_ci';

          $messages[] = [
              'type' => $isTurkish ? 'success' : 'warning',
              'text' => "<strong>Veritabanı:</strong> <code>$dbName</code><br>" .
                       "Şu anki Collation: <code>$currentCollation</code>" .
                       ($isTurkish ? " ✅" : " ⚠️")
          ];

          // Tabloları ve collation'larını listele
          $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
          $collations = [];

          foreach ($tables as $table) {
              $stmt = $pdo->query("SHOW TABLE STATUS WHERE Name = '$table'");
              $info = $stmt->fetch(PDO::FETCH_ASSOC);
              $tableCollation = $info['Collation'] ?? 'unknown';
              $collations[$tableCollation][] = $table;
          }

          $tableList = "<strong>Mevcut Tablolar ve Collation'ları:</strong><br><br>";
          foreach ($collations as $collation => $tableNames) {
              $isTurkishTable = $collation === 'utf8mb4_turkish_ci';
              $icon = $isTurkishTable ? '✅' : '⚠️';
              $tableList .= "$icon <code>$collation</code> (" . count($tableNames) . " tablo): " .
                           implode(', ', array_map(fn($t) => "<code>$t</code>", $tableNames)) . "<br><br>";
          }

          $messages[] = [
              'type' => 'info',
              'text' => $tableList
          ];

          // Özet
          $turkishCount = count($collations['utf8mb4_turkish_ci'] ?? []);
          $otherCount = array_sum(array_map('count', $collations)) - $turkishCount;

          if ($otherCount > 0) {
              $messages[] = [
                  'type' => 'warning',
                  'text' => "⚠️ <strong>$otherCount</strong> tablo farklı collation kullanıyor.<br>" .
                           "Tümünü <code>utf8mb4_turkish_ci</code>'ye çevirmek için aşağıdaki butona basın."
              ];
          }

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
    <button type="submit" name="convert" class="turkish">
      🇹🇷 Tüm Veritabanını utf8mb4_turkish_ci'ye Çevir
    </button>
  </form>

  <div class="info">
    <strong>ℹ️ Bu İşlem:</strong><br><br>
    1. Veritabanının default collation'ını değiştirecek<br>
    2. Tüm tabloları tek tek <code>utf8mb4_turkish_ci</code>'ye çevirecek<br>
    3. Türkçe alfabetik sıralama ve aramalar artık doğru çalışacak<br>
    4. <strong>Verileriniz kaybolmayacak, sadece collation değişecek</strong>
  </div>
  <?php else: ?>
  <div class="success">
    <strong>✅ Harika! Artık veritabanınız tamamen Türkçe destekli!</strong><br><br>
    Şimdi <a href="/debug-gallery.html" style="color: #10b981; font-weight: bold;">debug-gallery.html</a>
    sayfasına gidip fotoğraf yüklemeyi deneyin.<br><br>
    <a href="/list-products.php" style="color: #10b981; font-weight: bold;">→ Ürün Listesi</a><br>
    <a href="/check-password.php" style="color: #10b981; font-weight: bold;">→ Admin Şifresi Kontrolü</a>
  </div>
  <?php endif; ?>

  <div class="warning" style="margin-top: 2rem;">
    <strong>⚠️ GÜVENLİK:</strong><br>
    Bu dosyayı kullandıktan sonra <strong>mutlaka silin</strong>!<br>
    <code>del c:\xampp\htdocs\fix-to-turkish-ci.php</code>
  </div>
</div>
</body>
</html>
