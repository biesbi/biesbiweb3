<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Veritabanı Collation Kontrolü</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem; }
  .container { max-width: 900px; margin: 0 auto; background: #1e293b; padding: 2rem; border-radius: 12px; }
  h1 { color: #38bdf8; }
  .success { background: #064e3b; color: #10b981; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .error { background: #7f1d1d; color: #fca5a5; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .info { background: #1e3a5f; color: #93c5fd; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .warning { background: #78350f; color: #fbbf24; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  button { background: #dc2626; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; margin: 0.5rem 0; }
  button:hover { background: #b91c1c; }
  code { background: #334155; padding: 0.2rem 0.5rem; border-radius: 4px; color: #38bdf8; }
  pre { background: #0f172a; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.9rem; }
  table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
  th { background: #334155; padding: 0.75rem; text-align: left; color: #94a3b8; }
  td { padding: 0.75rem; border-top: 1px solid #334155; }
  tr:hover { background: #1e293b; }
</style>
</head>
<body>
<div class="container">
  <h1>🔍 Veritabanı Collation Kontrolü</h1>

  <?php
  require_once __DIR__ . '/api/config.php';
  require_once __DIR__ . '/api/helpers.php';

  try {
      $pdo = db();
      $dbName = DB_NAME;

      // 1. Veritabanının kendisinin collation'ı
      $stmt = $pdo->query("
          SELECT SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
          FROM information_schema.SCHEMATA
          WHERE SCHEMA_NAME = '$dbName'
      ");
      $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);

      $dbCollation = $dbInfo['DEFAULT_COLLATION_NAME'];
      $isTurkish = $dbCollation === 'utf8mb4_turkish_ci';

      echo "<div class='" . ($isTurkish ? 'success' : 'warning') . "'>";
      echo "<strong>🗄️ VERİTABANI:</strong> <code>$dbName</code><br><br>";
      echo "Default Character Set: <code>" . $dbInfo['DEFAULT_CHARACTER_SET_NAME'] . "</code><br>";
      echo "Default Collation: <code>$dbCollation</code> " . ($isTurkish ? '✅' : '⚠️');
      echo "</div>";

      // 2. Tüm tabloların collation'ı
      $stmt = $pdo->query("
          SELECT TABLE_NAME, TABLE_COLLATION
          FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = '$dbName'
          ORDER BY TABLE_NAME
      ");
      $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $turkishTables = [];
      $generalTables = [];
      $otherTables = [];

      foreach ($tables as $table) {
          if ($table['TABLE_COLLATION'] === 'utf8mb4_turkish_ci') {
              $turkishTables[] = $table['TABLE_NAME'];
          } elseif ($table['TABLE_COLLATION'] === 'utf8mb4_general_ci') {
              $generalTables[] = $table['TABLE_NAME'];
          } else {
              $otherTables[] = $table['TABLE_NAME'];
          }
      }

      // Sonuç
      echo "<h2>📊 Tablo Collation'ları</h2>";

      echo "<table>";
      echo "<thead><tr><th>Tablo</th><th>Collation</th><th>Durum</th></tr></thead>";
      echo "<tbody>";

      foreach ($tables as $table) {
          $tableName = $table['TABLE_NAME'];
          $tableCollation = $table['TABLE_COLLATION'];
          $match = $tableCollation === $dbCollation;

          echo "<tr>";
          echo "<td><code>$tableName</code></td>";
          echo "<td><code>$tableCollation</code></td>";
          echo "<td>" . ($match ? '✅ Eşleşiyor' : '⚠️ Farklı') . "</td>";
          echo "</tr>";
      }

      echo "</tbody></table>";

      // Özet
      echo "<div class='info'>";
      echo "<strong>📊 Özet:</strong><br><br>";
      echo "✅ Turkish CI: <code>" . count($turkishTables) . "</code> tablo<br>";
      echo "⚠️ General CI: <code>" . count($generalTables) . "</code> tablo<br>";
      if (!empty($otherTables)) {
          echo "❓ Diğer: <code>" . count($otherTables) . "</code> tablo<br>";
      }
      echo "</div>";

      // Sorun tespiti
      if (!$isTurkish) {
          echo "<div class='error'>";
          echo "<strong>❌ SORUN BULUNDU!</strong><br><br>";
          echo "Veritabanı <code>$dbCollation</code> kullanıyor ama tablolar farklı collation kullanıyor.<br>";
          echo "Bu JOIN işlemlerinde sorun yaratıyor!<br><br>";
          echo "<strong>ÇÖZÜM:</strong> Veritabanını <code>utf8mb4_turkish_ci</code>'ye çevirin.";
          echo "</div>";

          // Düzeltme formu
          if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_database'])) {
              try {
                  // Veritabanını değiştir
                  $pdo->exec("ALTER DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci");

                  echo "<div class='success'>";
                  echo "✅ Veritabanı collation başarıyla <code>utf8mb4_turkish_ci</code>'ye çevrildi!<br><br>";
                  echo "Sayfa yenileniyor...";
                  echo "</div>";
                  echo "<script>setTimeout(() => window.location.reload(), 1500);</script>";

              } catch (Exception $e) {
                  echo "<div class='error'>";
                  echo "❌ Hata: " . htmlspecialchars($e->getMessage());
                  echo "</div>";
              }
          } elseif (!empty($generalTables)) {
              echo "<form method='POST'>";
              echo "<button type='submit' name='fix_database'>🔧 Veritabanını utf8mb4_turkish_ci'ye Çevir</button>";
              echo "</form>";
          }
      } elseif (!empty($generalTables)) {
          echo "<div class='warning'>";
          echo "<strong>⚠️ KISMEN SORUN VAR!</strong><br><br>";
          echo "Veritabanı <code>utf8mb4_turkish_ci</code> kullanıyor ama " . count($generalTables) . " tablo <code>utf8mb4_general_ci</code> kullanıyor:<br><br>";
          echo "<code>" . implode('</code>, <code>', $generalTables) . "</code><br><br>";
          echo "Bu tablolar düzeltilmeli!";
          echo "</div>";

          // Tabloları düzelt
          if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_tables'])) {
              $fixedCount = 0;
              $errors = [];

              foreach ($generalTables as $tableName) {
                  try {
                      $pdo->exec("ALTER TABLE `$tableName` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci");
                      $fixedCount++;
                  } catch (Exception $e) {
                      $errors[] = "$tableName: " . $e->getMessage();
                  }
              }

              if (empty($errors)) {
                  echo "<div class='success'>";
                  echo "🎉 <strong>$fixedCount</strong> tablo başarıyla düzeltildi!<br><br>";
                  echo "<a href='/debug-gallery.html' style='color: #10b981; font-weight: bold;'>→ Şimdi fotoğraf yüklemeyi dene</a>";
                  echo "</div>";
                  echo "<script>setTimeout(() => window.location.reload(), 2000);</script>";
              } else {
                  echo "<div class='error'>";
                  echo "⚠️ $fixedCount tablo düzeltildi, " . count($errors) . " hatada sorun oluştu:<br><br>";
                  foreach ($errors as $err) {
                      echo "- " . htmlspecialchars($err) . "<br>";
                  }
                  echo "</div>";
              }
          } else {
              echo "<form method='POST'>";
              echo "<button type='submit' name='fix_tables'>🔧 " . count($generalTables) . " Tabloyu Düzelt</button>";
              echo "</form>";
          }
      } else {
          echo "<div class='success'>";
          echo "<strong>✅ HER ŞEY TAMAM!</strong><br><br>";
          echo "Hem veritabanı hem de tüm tablolar <code>utf8mb4_turkish_ci</code> kullanıyor.<br><br>";
          echo "Collation sorunu yok. Sorun başka bir yerde olabilir.<br><br>";
          echo "<a href='/debug-gallery.html' style='color: #10b981; font-weight: bold;'>→ Fotoğraf yüklemeyi test et</a>";
          echo "</div>";
      }

  } catch (Exception $e) {
      echo "<div class='error'>";
      echo "❌ HATA: " . htmlspecialchars($e->getMessage());
      echo "</div>";
  }
  ?>

  <div class='warning' style='margin-top: 2rem;'>
    <strong>⚠️ GÜVENLİK:</strong><br>
    Bu dosyayı kullandıktan sonra <strong>mutlaka silin</strong>!<br>
    <code>del c:\xampp\htdocs\check-database-collation.php</code>
  </div>
</div>
</body>
</html>
