<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Products ID Collation Düzeltme</title>
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
  pre { background: #0f172a; padding: 1rem; border-radius: 4px; overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
  th { background: #334155; padding: 0.75rem; text-align: left; color: #94a3b8; }
  td { padding: 0.75rem; border-top: 1px solid #334155; }
</style>
</head>
<body>
<div class="container">
  <h1>🔧 Products Tablosu ID Kolonu Düzeltme</h1>

  <div class="info">
    <strong>🎯 Problem:</strong><br>
    <code>products.id</code> kolonu ile <code>product_images.product_id</code> kolonu farklı collation kullanıyor olabilir.<br>
    Bu JOIN ve WHERE sorgularında collation hatası yaratıyor!
  </div>

  <?php
  require_once __DIR__ . '/api/config.php';
  require_once __DIR__ . '/api/helpers.php';

  try {
      $pdo = db();

      // products tablosundaki TÜM VARCHAR/TEXT kolonları kontrol et
      $stmt = $pdo->query("SHOW FULL COLUMNS FROM products");
      $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

      echo "<h2>📊 Products Tablosu Kolonları</h2>";
      echo "<table>";
      echo "<thead><tr><th>Kolon</th><th>Tip</th><th>Collation</th><th>Durum</th></tr></thead>";
      echo "<tbody>";

      $problemColumns = [];
      $targetCollation = 'utf8mb4_turkish_ci';

      foreach ($columns as $col) {
          $field = $col['Field'];
          $type = $col['Type'];
          $collation = $col['Collation'];

          // Sadece string kolonları göster
          if ($collation !== null) {
              $match = $collation === $targetCollation;

              echo "<tr" . (!$match ? " style='background: #7f1d1d;'" : "") . ">";
              echo "<td><code>$field</code></td>";
              echo "<td>$type</td>";
              echo "<td><code>$collation</code></td>";
              echo "<td>" . ($match ? '✅' : '❌ FARKLI!') . "</td>";
              echo "</tr>";

              if (!$match) {
                  $problemColumns[] = $col;
              }
          }
      }

      echo "</tbody></table>";

      // Sorun varsa
      if (!empty($problemColumns)) {
          echo "<div class='error'>";
          echo "<strong>❌ SORUN BULUNDU!</strong><br><br>";
          echo count($problemColumns) . " kolon <code>$targetCollation</code> kullanmıyor:<br><br>";

          foreach ($problemColumns as $col) {
              echo "- <code>" . $col['Field'] . "</code>: " . $col['Collation'] . "<br>";
          }

          echo "</div>";

          // SQL düzeltme komutları
          echo "<div class='info'>";
          echo "<strong>🔧 Düzeltme SQL Komutları:</strong><br><br>";
          echo "<pre>";

          foreach ($problemColumns as $col) {
              $field = $col['Field'];
              $type = $col['Type'];
              $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
              $default = '';

              if ($col['Default'] !== null) {
                  $default = " DEFAULT '" . addslashes($col['Default']) . "'";
              } elseif ($col['Null'] === 'YES') {
                  $default = " DEFAULT NULL";
              }

              // PRIMARY KEY kontrolü
              $key = '';
              if ($col['Key'] === 'PRI') {
                  $key = ' PRIMARY KEY';
              }

              echo "ALTER TABLE products MODIFY COLUMN `$field` $type CHARACTER SET utf8mb4 COLLATE $targetCollation $null$default$key;\n";
          }

          echo "</pre>";
          echo "</div>";

          // Otomatik düzeltme
          if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
              $fixedCount = 0;
              $errors = [];

              foreach ($problemColumns as $col) {
                  try {
                      $field = $col['Field'];
                      $type = $col['Type'];
                      $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
                      $default = '';

                      if ($col['Default'] !== null) {
                          $default = " DEFAULT " . $pdo->quote($col['Default']);
                      } elseif ($col['Null'] === 'YES') {
                          $default = " DEFAULT NULL";
                      }

                      // PRIMARY KEY kontrolü
                      $key = '';
                      if ($col['Key'] === 'PRI') {
                          $key = ' PRIMARY KEY';
                      }

                      $sql = "ALTER TABLE products MODIFY COLUMN `$field` $type CHARACTER SET utf8mb4 COLLATE $targetCollation $null$default$key";
                      $pdo->exec($sql);
                      $fixedCount++;

                  } catch (Exception $e) {
                      $errors[] = "$field: " . $e->getMessage();
                  }
              }

              if (empty($errors)) {
                  echo "<div class='success'>";
                  echo "🎉 <strong>$fixedCount</strong> kolon başarıyla düzeltildi!<br><br>";
                  echo "Artık fotoğraf yükleme çalışmalı!<br><br>";
                  echo "<a href='/debug-gallery.html' style='color: #10b981; font-weight: bold;'>→ Fotoğraf yüklemeyi test et</a>";
                  echo "</div>";
                  echo "<script>setTimeout(() => window.location.reload(), 2000);</script>";
              } else {
                  echo "<div class='error'>";
                  echo "⚠️ $fixedCount kolon düzeltildi, " . count($errors) . " hatada sorun oluştu:<br><br>";
                  foreach ($errors as $err) {
                      echo "- " . htmlspecialchars($err) . "<br>";
                  }
                  echo "</div>";
              }
          } else {
              echo "<form method='POST'>";
              echo "<button type='submit' name='fix'>🔧 " . count($problemColumns) . " Kolonu Otomatik Düzelt</button>";
              echo "</form>";
          }

      } else {
          echo "<div class='success'>";
          echo "✅ <strong>HİÇBİR SORUN YOK!</strong><br><br>";
          echo "Tüm kolonlar zaten <code>$targetCollation</code> kullanıyor.<br><br>";
          echo "Collation sorunu products tablosunda değil. Sorun başka bir yerde olmalı.";
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
    <code>del c:\xampp\htdocs\fix-products-id-collation.php</code>
  </div>
</div>
</body>
</html>
