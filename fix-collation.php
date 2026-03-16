<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Collation Düzeltme</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem; }
  .container { max-width: 900px; margin: 0 auto; background: #1e293b; padding: 2rem; border-radius: 12px; }
  h1 { color: #38bdf8; }
  .success { background: #064e3b; color: #10b981; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .error { background: #7f1d1d; color: #fca5a5; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .info { background: #1e3a5f; color: #93c5fd; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .warning { background: #78350f; color: #fbbf24; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  pre { background: #0f172a; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.85rem; }
  button { background: #38bdf8; color: #0f172a; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; margin: 0.5rem 0.5rem 0 0; }
  button:hover { background: #0ea5e9; }
  button.danger { background: #dc2626; color: white; }
</style>
</head>
<body>
<div class="container">
  <h1>🔧 Collation Uyumsuzluğu Düzeltme</h1>

  <div class="warning">
    <strong>⚠️ SORUN:</strong><br>
    <code>product_images.product_id</code> ve <code>products.id</code> farklı collation'lar kullanıyor.<br>
    Bu yüzden JOIN işlemi başarısız oluyor.
  </div>

  <?php
  require_once __DIR__ . '/api/config.php';
  require_once __DIR__ . '/api/helpers.php';

  $messages = [];
  $hasError = false;

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
      try {
          $pdo = db();

          // 1. products tablosunun collation'ını kontrol et
          $stmt = $pdo->query("SHOW CREATE TABLE products");
          $productsCreate = $stmt->fetch(PDO::FETCH_ASSOC);
          $productsCharset = 'utf8mb4_general_ci';

          if (preg_match('/DEFAULT CHARSET=utf8mb4 COLLATE=(utf8mb4_\w+)/i', $productsCreate['Create Table'], $matches)) {
              $productsCharset = $matches[1];
          }

          $messages[] = [
              'type' => 'info',
              'text' => "products tablosu collation: <code>$productsCharset</code>"
          ];

          // 2. product_images tablosunu düzelt
          $pdo->exec("ALTER TABLE product_images
              MODIFY COLUMN product_id VARCHAR(50)
              CHARACTER SET utf8mb4 COLLATE $productsCharset NOT NULL");

          $messages[] = [
              'type' => 'success',
              'text' => "✅ product_images.product_id collation güncellendi: <code>$productsCharset</code>"
          ];

          // 3. Tüm product_images tablosunun collation'ını ayarla
          $pdo->exec("ALTER TABLE product_images
              CONVERT TO CHARACTER SET utf8mb4 COLLATE $productsCharset");

          $messages[] = [
              'type' => 'success',
              'text' => "✅ product_images tablosu tamamen <code>$productsCharset</code> collation'a çevrildi"
          ];

          // 4. products.id kolonu da aynı collation'da mı kontrol et
          $stmt = $pdo->query("SHOW FULL COLUMNS FROM products WHERE Field = 'id'");
          $idColumn = $stmt->fetch(PDO::FETCH_ASSOC);

          if ($idColumn && $idColumn['Collation'] !== $productsCharset) {
              $pdo->exec("ALTER TABLE products
                  MODIFY COLUMN id VARCHAR(50)
                  CHARACTER SET utf8mb4 COLLATE $productsCharset NOT NULL");

              $messages[] = [
                  'type' => 'success',
                  'text' => "✅ products.id collation güncellendi"
              ];
          }

          $messages[] = [
              'type' => 'success',
              'text' => "🎉 Tüm düzeltmeler başarıyla tamamlandı! Artık fotoğraf yükleyebilirsiniz."
          ];

      } catch (Exception $e) {
          $hasError = true;
          $messages[] = [
              'type' => 'error',
              'text' => "❌ HATA: " . htmlspecialchars($e->getMessage())
          ];
      }
  } else {
      // Mevcut durumu göster
      try {
          $pdo = db();

          // products.id collation
          $stmt = $pdo->query("SHOW FULL COLUMNS FROM products WHERE Field = 'id'");
          $productsId = $stmt->fetch(PDO::FETCH_ASSOC);

          // product_images.product_id collation
          $stmt = $pdo->query("SHOW FULL COLUMNS FROM product_images WHERE Field = 'product_id'");
          $imagesProductId = $stmt->fetch(PDO::FETCH_ASSOC);

          $messages[] = [
              'type' => 'info',
              'text' => "<strong>Mevcut Durum:</strong><br><br>" .
                       "products.id: <code>" . ($productsId['Collation'] ?? 'N/A') . "</code><br>" .
                       "product_images.product_id: <code>" . ($imagesProductId['Collation'] ?? 'N/A') . "</code>"
          ];

          if ($productsId['Collation'] !== $imagesProductId['Collation']) {
              $messages[] = [
                  'type' => 'warning',
                  'text' => "⚠️ Collation'lar farklı! Düzeltme gerekli."
              ];
          } else {
              $messages[] = [
                  'type' => 'success',
                  'text' => "✅ Collation'lar eşleşiyor. Sorun başka bir yerde olabilir."
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

  <?php if (!isset($_POST['fix'])): ?>
  <form method="POST">
    <button type="submit" name="fix" class="danger">🔧 Collation'ı Düzelt</button>
  </form>

  <div class="info">
    <strong>ℹ️ Bu İşlem Ne Yapacak?</strong><br><br>
    1. <code>products</code> tablosunun collation'ını tespit edecek<br>
    2. <code>product_images</code> tablosunu aynı collation'a çevirecek<br>
    3. Böylece JOIN işlemleri çalışacak ve fotoğraf yüklenebilecek
  </div>
  <?php else: ?>
  <div class="success">
    <strong>✅ İşlem Tamamlandı!</strong><br><br>
    Şimdi <a href="/debug-gallery.html" style="color: #10b981; font-weight: bold;">debug-gallery.html</a>
    sayfasına gidip tekrar fotoğraf yüklemeyi deneyin.
  </div>
  <?php endif; ?>

  <div class="warning" style="margin-top: 2rem;">
    <strong>⚠️ GÜVENLİK:</strong><br>
    Bu dosyayı kullandıktan sonra <strong>mutlaka silin</strong>!<br>
    <code>rm c:\xampp\htdocs\fix-collation.php</code>
  </div>
</div>
</body>
</html>
