<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Tüm Veritabanı Collation Taraması</title>
<style>
  body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; font-size: 12px; }
  .container { max-width: 1600px; margin: 0 auto; background: #1e293b; padding: 2rem; border-radius: 12px; }
  h1 { color: #38bdf8; font-size: 1.5rem; margin: 0 0 1rem 0; }
  .success { background: #064e3b; color: #10b981; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .error { background: #7f1d1d; color: #fca5a5; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .info { background: #1e3a5f; color: #93c5fd; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .warning { background: #78350f; color: #fbbf24; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  button { background: #dc2626; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; margin: 0.5rem 0; }
  button:hover { background: #b91c1c; }
  code { background: #334155; padding: 0.2rem 0.4rem; border-radius: 4px; color: #38bdf8; font-size: 11px; }
  pre { background: #0f172a; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 11px; max-height: 400px; }
  table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 11px; }
  th { background: #334155; padding: 0.5rem; text-align: left; color: #94a3b8; position: sticky; top: 0; }
  td { padding: 0.5rem; border-top: 1px solid #334155; }
  tr.problem { background: #7f1d1d; }
  tr:hover { background: #1e293b; }
  .badge-problem { background: #7f1d1d; color: #fca5a5; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: bold; }
  .badge-ok { background: #064e3b; color: #10b981; padding: 0.25rem 0.5rem; border-radius: 4px; }
</style>
</head>
<body>
<div class="container">
  <h1>🔍 Tüm Veritabanı Collation Taraması</h1>

  <?php
  require_once __DIR__ . '/api/config.php';
  require_once __DIR__ . '/api/helpers.php';

  $startTime = microtime(true);

  try {
      $pdo = db();
      $dbName = DB_NAME;

      echo "<div class='info'>";
      echo "⏳ Veritabanı taranıyor: <code>$dbName</code>...";
      echo "</div>";

      // Tüm tabloları al
      $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

      $allColumns = [];
      $problemColumns = [];
      $generalCiColumns = [];

      // Her tablonun her kolonunu tara
      foreach ($tables as $table) {
          $columns = $pdo->query("SHOW FULL COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);

          foreach ($columns as $col) {
              if ($col['Collation'] !== null) {
                  $columnInfo = [
                      'table' => $table,
                      'column' => $col['Field'],
                      'type' => $col['Type'],
                      'collation' => $col['Collation'],
                      'null' => $col['Null'],
                      'key' => $col['Key'],
                      'default' => $col['Default']
                  ];

                  $allColumns[] = $columnInfo;

                  // general_ci kullananları bul
                  if ($col['Collation'] === 'utf8mb4_general_ci') {
                      $generalCiColumns[] = $columnInfo;
                      $problemColumns[] = $columnInfo;
                  }
                  // Başka bir collation kullananları da bul
                  elseif ($col['Collation'] !== 'utf8mb4_turkish_ci') {
                      $problemColumns[] = $columnInfo;
                  }
              }
          }
      }

      $duration = round(microtime(true) - $startTime, 2);

      // Özet
      echo "<div class='info'>";
      echo "<strong>📊 Tarama Özeti:</strong><br><br>";
      echo "Taranan Tablo: <code>" . count($tables) . "</code><br>";
      echo "Taranan Kolon: <code>" . count($allColumns) . "</code><br>";
      echo "⏱️ Süre: <code>{$duration}s</code>";
      echo "</div>";

      // SORUNLU KOLONLAR
      if (!empty($generalCiColumns)) {
          echo "<div class='error'>";
          echo "<strong>❌ SORUN BULUNDU!</strong><br><br>";
          echo "<strong>" . count($generalCiColumns) . "</strong> kolon <code>utf8mb4_general_ci</code> kullanıyor!<br>";
          echo "Bu kolonlar fotoğraf yükleme hatasına neden oluyor.";
          echo "</div>";

          echo "<h2>❌ Sorunlu Kolonlar (utf8mb4_general_ci)</h2>";
          echo "<table>";
          echo "<thead><tr>";
          echo "<th>Tablo</th>";
          echo "<th>Kolon</th>";
          echo "<th>Tip</th>";
          echo "<th>Collation</th>";
          echo "<th>Key</th>";
          echo "</tr></thead>";
          echo "<tbody>";

          foreach ($generalCiColumns as $col) {
              echo "<tr class='problem'>";
              echo "<td><code>" . $col['table'] . "</code></td>";
              echo "<td><code>" . $col['column'] . "</code></td>";
              echo "<td>" . $col['type'] . "</td>";
              echo "<td><span class='badge-problem'>" . $col['collation'] . "</span></td>";
              echo "<td>" . ($col['key'] ?: '-') . "</td>";
              echo "</tr>";
          }

          echo "</tbody></table>";

          // SQL Düzeltme Komutları
          echo "<div class='warning'>";
          echo "<strong>🔧 Düzeltme SQL Komutları:</strong><br><br>";
          echo "<pre>";

          foreach ($generalCiColumns as $col) {
              $table = $col['table'];
              $field = $col['column'];
              $type = $col['type'];
              $null = $col['null'] === 'YES' ? 'NULL' : 'NOT NULL';
              $default = '';

              if ($col['default'] !== null) {
                  $default = " DEFAULT '" . addslashes($col['default']) . "'";
              } elseif ($col['null'] === 'YES') {
                  $default = " DEFAULT NULL";
              }

              $key = '';
              if ($col['key'] === 'PRI') {
                  $key = ' PRIMARY KEY';
              }

              echo "ALTER TABLE `$table` MODIFY COLUMN `$field` $type CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci $null$default$key;\n";
          }

          echo "</pre>";
          echo "</div>";

          // Otomatik Düzeltme
          if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_all'])) {
              $fixedCount = 0;
              $errors = [];

              foreach ($generalCiColumns as $col) {
                  try {
                      $table = $col['table'];
                      $field = $col['column'];
                      $type = $col['type'];
                      $null = $col['null'] === 'YES' ? 'NULL' : 'NOT NULL';
                      $default = '';

                      if ($col['default'] !== null) {
                          $default = " DEFAULT " . $pdo->quote($col['default']);
                      } elseif ($col['null'] === 'YES') {
                          $default = " DEFAULT NULL";
                      }

                      $key = '';
                      if ($col['key'] === 'PRI') {
                          $key = ' PRIMARY KEY';
                      }

                      $sql = "ALTER TABLE `$table` MODIFY COLUMN `$field` $type CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci $null$default$key";
                      $pdo->exec($sql);
                      $fixedCount++;

                  } catch (Exception $e) {
                      $errors[] = "$table.$field: " . $e->getMessage();
                  }
              }

              if (empty($errors)) {
                  echo "<div class='success'>";
                  echo "🎉 <strong>TAMAMLANDI!</strong><br><br>";
                  echo "<strong>$fixedCount</strong> kolon başarıyla <code>utf8mb4_turkish_ci</code>'ye çevrildi!<br><br>";
                  echo "<strong style='font-size: 1.2rem;'>Artık fotoğraf yükleyebilirsiniz! 🚀</strong><br><br>";
                  echo "<a href='/debug-gallery.html' style='color: #10b981; font-weight: bold; font-size: 1.1rem;'>→ Hemen Fotoğraf Yüklemeyi Test Et</a>";
                  echo "</div>";
                  echo "<script>setTimeout(() => window.location.reload(), 3000);</script>";
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
              echo "<button type='submit' name='fix_all'>🔧 " . count($generalCiColumns) . " Kolonu Otomatik Düzelt</button>";
              echo "</form>";
          }

      } else {
          echo "<div class='success'>";
          echo "<strong>✅ HİÇBİR SORUN YOK!</strong><br><br>";
          echo "Tüm kolonlar <code>utf8mb4_turkish_ci</code> veya uyumlu collation kullanıyor.<br><br>";
          echo "Collation sorunu yok. Sorun başka bir yerde olmalı.";
          echo "</div>";
      }

      // Diğer sorunlu kolonlar varsa
      $otherProblems = array_diff_key($problemColumns, $generalCiColumns);
      if (!empty($otherProblems)) {
          echo "<div class='warning'>";
          echo "<strong>⚠️ Diğer Collation'lar:</strong><br><br>";
          echo count($otherProblems) . " kolon farklı collation kullanıyor (general_ci değil ama turkish_ci de değil):";
          echo "</div>";

          echo "<table>";
          echo "<thead><tr><th>Tablo</th><th>Kolon</th><th>Collation</th></tr></thead>";
          echo "<tbody>";
          foreach ($otherProblems as $col) {
              echo "<tr>";
              echo "<td><code>" . $col['table'] . "</code></td>";
              echo "<td><code>" . $col['column'] . "</code></td>";
              echo "<td>" . $col['collation'] . "</td>";
              echo "</tr>";
          }
          echo "</tbody></table>";
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
    <code>del c:\xampp\htdocs\scan-all-collations.php</code>
  </div>
</div>
</body>
</html>
