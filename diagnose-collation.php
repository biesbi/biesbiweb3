<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Collation Teşhis</title>
<style>
  body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; font-size: 13px; }
  .container { max-width: 1400px; margin: 0 auto; background: #1e293b; padding: 2rem; border-radius: 12px; }
  h1 { color: #38bdf8; margin: 0 0 1rem 0; }
  table { width: 100%; border-collapse: collapse; margin: 1rem 0; background: #0f172a; font-size: 12px; }
  th { background: #334155; padding: 0.5rem; text-align: left; color: #94a3b8; font-weight: 600; position: sticky; top: 0; }
  td { padding: 0.5rem; border-top: 1px solid #334155; }
  tr:hover { background: #1e293b; }
  .error { background: #7f1d1d; color: #fca5a5; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .warning { background: #78350f; color: #fbbf24; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .success { background: #064e3b; color: #10b981; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .info { background: #1e3a5f; color: #93c5fd; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  code { background: #334155; padding: 0.2rem 0.4rem; border-radius: 4px; color: #38bdf8; }
  .badge-turkish { background: #064e3b; color: #10b981; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 11px; }
  .badge-general { background: #7f1d1d; color: #fca5a5; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 11px; }
  .badge-other { background: #78350f; color: #fbbf24; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 11px; }
  button { background: #dc2626; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; margin: 0.5rem 0.5rem 0.5rem 0; }
  button:hover { background: #b91c1c; }
  pre { background: #0f172a; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 11px; }
</style>
</head>
<body>
<div class="container">
  <h1>🔍 Collation Teşhis Aracı</h1>

  <?php
  require_once __DIR__ . '/api/config.php';
  require_once __DIR__ . '/api/helpers.php';

  try {
      $pdo = db();
      $dbName = DB_NAME;

      echo "<div class='info'>";
      echo "<strong>Veritabanı:</strong> <code>$dbName</code>";
      echo "</div>";

      // Her tablonun her kolonunun collation'ını kontrol et
      $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

      $problemTables = [];
      $allColumns = [];

      foreach ($tables as $table) {
          $columns = $pdo->query("SHOW FULL COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);

          foreach ($columns as $col) {
              if ($col['Collation'] !== null) {
                  $allColumns[] = [
                      'table' => $table,
                      'column' => $col['Field'],
                      'type' => $col['Type'],
                      'collation' => $col['Collation']
                  ];

                  if ($col['Collation'] !== 'utf8mb4_turkish_ci') {
                      $problemTables[$table][] = $col;
                  }
              }
          }
      }

      // Önemli tabloları vurgula
      $criticalTables = ['products', 'product_images', 'users', 'categories', 'brands'];
      $criticalProblems = array_intersect_key($problemTables, array_flip($criticalTables));

      if (!empty($criticalProblems)) {
          echo "<div class='error'>";
          echo "<strong>❌ KRİTİK SORUN BULUNDU!</strong><br><br>";
          echo "Şu önemli tablolarda turkish_ci olmayan kolonlar var:<br><br>";

          foreach ($criticalProblems as $table => $cols) {
              echo "<strong><code>$table</code></strong>:<br>";
              foreach ($cols as $col) {
                  echo "  └─ <code>" . $col['Field'] . "</code>: " . $col['Collation'] . "<br>";
              }
              echo "<br>";
          }

          echo "<strong>Bu sorun fotoğraf yüklemeyi engelliyor!</strong>";
          echo "</div>";
      } else {
          echo "<div class='success'>";
          echo "✅ Kritik tablolarda collation problemi yok!";
          echo "</div>";
      }

      // Tüm kolonları göster
      echo "<h2>📊 Tüm Kolonlar ve Collation'ları</h2>";
      echo "<table>";
      echo "<thead><tr>";
      echo "<th>Tablo</th>";
      echo "<th>Kolon</th>";
      echo "<th>Tip</th>";
      echo "<th>Collation</th>";
      echo "<th>Durum</th>";
      echo "</tr></thead>";
      echo "<tbody>";

      $turkishCount = 0;
      $generalCount = 0;
      $otherCount = 0;

      foreach ($allColumns as $col) {
          $isCritical = in_array($col['table'], $criticalTables);
          $rowStyle = $isCritical ? "style='background: #1e3a5f;'" : "";

          echo "<tr $rowStyle>";
          echo "<td><code>" . $col['table'] . "</code></td>";
          echo "<td><code>" . $col['column'] . "</code></td>";
          echo "<td>" . $col['type'] . "</td>";
          echo "<td>" . $col['collation'] . "</td>";

          if ($col['collation'] === 'utf8mb4_turkish_ci') {
              echo "<td><span class='badge-turkish'>✅ TURKISH</span></td>";
              $turkishCount++;
          } elseif ($col['collation'] === 'utf8mb4_general_ci') {
              echo "<td><span class='badge-general'>❌ GENERAL</span></td>";
              $generalCount++;
          } else {
              echo "<td><span class='badge-other'>⚠️ DİĞER</span></td>";
              $otherCount++;
          }

          echo "</tr>";
      }

      echo "</tbody></table>";

      // Özet
      $total = count($allColumns);
      echo "<div class='info'>";
      echo "<strong>📊 Özet:</strong><br><br>";
      echo "Toplam Kolon: <code>$total</code><br>";
      echo "✅ Turkish CI: <code>$turkishCount</code> (" . round($turkishCount/$total*100) . "%)<br>";
      echo "❌ General CI: <code>$generalCount</code> (" . round($generalCount/$total*100) . "%)<br>";
      if ($otherCount > 0) {
          echo "⚠️ Diğer: <code>$otherCount</code> (" . round($otherCount/$total*100) . "%)<br>";
      }
      echo "</div>";

      // SQL Fix scripti oluştur
      if (!empty($problemTables)) {
          echo "<div class='warning'>";
          echo "<strong>🔧 Düzeltme SQL Komutları:</strong><br><br>";
          echo "<pre style='max-height: 400px; overflow-y: auto;'>";

          foreach ($problemTables as $table => $cols) {
              foreach ($cols as $col) {
                  $type = $col['Type'];
                  $field = $col['Field'];
                  $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
                  $default = '';

                  if ($col['Default'] !== null) {
                      $default = " DEFAULT '" . addslashes($col['Default']) . "'";
                  } elseif ($col['Null'] === 'YES') {
                      $default = " DEFAULT NULL";
                  }

                  echo "ALTER TABLE `$table` MODIFY COLUMN `$field` $type CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci $null$default;\n";
              }
          }

          echo "</pre>";
          echo "</div>";

          // Otomatik düzeltme butonu
          if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['autofix'])) {
              echo "<div class='info'><strong>⏳ Düzeltiliyor...</strong></div>";

              $fixedCount = 0;
              $errors = [];

              foreach ($problemTables as $table => $cols) {
                  foreach ($cols as $col) {
                      try {
                          $type = $col['Type'];
                          $field = $col['Field'];
                          $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
                          $default = '';

                          if ($col['Default'] !== null) {
                              $default = " DEFAULT " . $pdo->quote($col['Default']);
                          } elseif ($col['Null'] === 'YES') {
                              $default = " DEFAULT NULL";
                          }

                          $sql = "ALTER TABLE `$table` MODIFY COLUMN `$field` $type CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci $null$default";
                          $pdo->exec($sql);
                          $fixedCount++;

                      } catch (Exception $e) {
                          $errors[] = "$table.$field: " . $e->getMessage();
                      }
                  }
              }

              if (empty($errors)) {
                  echo "<div class='success'>";
                  echo "🎉 <strong>$fixedCount</strong> kolon başarıyla düzeltildi!<br><br>";
                  echo "<a href='/debug-gallery.html' style='color: #10b981; font-weight: bold;'>→ Şimdi fotoğraf yüklemeyi dene</a>";
                  echo "</div>";
              } else {
                  echo "<div class='error'>";
                  echo "⚠️ $fixedCount kolon düzeltildi, " . count($errors) . " hatada sorun oluştu:<br><br>";
                  foreach ($errors as $err) {
                      echo "- " . htmlspecialchars($err) . "<br>";
                  }
                  echo "</div>";
              }

              echo "<script>setTimeout(() => window.location.reload(), 2000);</script>";
          }

          if (!isset($_POST['autofix'])) {
              echo "<form method='POST'>";
              echo "<button type='submit' name='autofix'>🔧 Tüm Sorunları Otomatik Düzelt</button>";
              echo "</form>";
          }
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
    <code>del c:\xampp\htdocs\diagnose-collation.php</code>
  </div>
</div>
</body>
</html>
