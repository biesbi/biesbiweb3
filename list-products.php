<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Ürün Listesi</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem; }
  .container { max-width: 1200px; margin: 0 auto; background: #1e293b; padding: 2rem; border-radius: 12px; }
  h1 { color: #38bdf8; margin: 0 0 1rem 0; }
  table { width: 100%; border-collapse: collapse; margin: 1rem 0; background: #0f172a; border-radius: 8px; overflow: hidden; }
  th { background: #334155; padding: 0.75rem; text-align: left; color: #94a3b8; font-weight: 600; font-size: 0.85rem; }
  td { padding: 0.75rem; border-top: 1px solid #334155; }
  tr:hover { background: #1e293b; }
  code { background: #334155; padding: 0.25rem 0.5rem; border-radius: 4px; color: #38bdf8; font-size: 0.85rem; }
  .copy-btn { background: #38bdf8; color: #0f172a; border: none; padding: 0.4rem 0.8rem; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600; }
  .copy-btn:hover { background: #0ea5e9; }
  .success { background: #064e3b; color: #10b981; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .warning { background: #7f1d1d; color: #fca5a5; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
  .info { background: #1e3a5f; color: #93c5fd; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
</style>
</head>
<body>
<div class="container">
  <h1>📦 Ürün Listesi</h1>

  <?php
  require_once __DIR__ . '/api/config.php';
  require_once __DIR__ . '/api/helpers.php';

  try {
      $pdo = db();

      // Ürünleri getir
      $stmt = $pdo->query("SELECT id, name, price, stock FROM products ORDER BY created_at DESC LIMIT 20");
      $products = $stmt->fetchAll();

      if (empty($products)) {
          echo "<div class='warning'>";
          echo "❌ Veritabanında hiç ürün yok!<br><br>";
          echo "Önce admin panelden ürün ekleyin.";
          echo "</div>";
      } else {
          echo "<div class='success'>";
          echo "✅ " . count($products) . " adet ürün bulundu.<br>";
          echo "Aşağıdaki ürünlerden birinin ID'sini kopyalayıp debug aracında kullanabilirsiniz.";
          echo "</div>";

          echo "<table>";
          echo "<thead><tr>";
          echo "<th>ID</th>";
          echo "<th>Ürün Adı</th>";
          echo "<th>Fiyat</th>";
          echo "<th>Stok</th>";
          echo "<th>İşlem</th>";
          echo "</tr></thead>";
          echo "<tbody>";

          foreach ($products as $product) {
              $id = htmlspecialchars($product['id']);
              $name = htmlspecialchars($product['name']);
              $price = number_format($product['price'], 2);
              $stock = $product['stock'];

              echo "<tr>";
              echo "<td><code id='id-$id'>$id</code></td>";
              echo "<td>$name</td>";
              echo "<td>{$price} TL</td>";
              echo "<td>$stock</td>";
              echo "<td><button class='copy-btn' onclick='copyId(\"$id\")'>Kopyala</button></td>";
              echo "</tr>";
          }

          echo "</tbody></table>";

          echo "<div class='info'>";
          echo "<strong>💡 Nasıl Kullanılır?</strong><br><br>";
          echo "1. Yukarıdaki tablodan bir ürünün <strong>Kopyala</strong> butonuna tıklayın<br>";
          echo "2. <a href='/debug-gallery.html' style='color: #38bdf8;'>debug-gallery.html</a> sayfasını açın<br>";
          echo "3. Kopyaladığınız ID'yi 'Ürün ID' alanına yapıştırın<br>";
          echo "4. Fotoğraf seçip yükleyin<br>";
          echo "</div>";
      }

  } catch (Exception $e) {
      echo "<div class='warning'>";
      echo "❌ HATA: " . htmlspecialchars($e->getMessage());
      echo "</div>";
  }
  ?>
</div>

<script>
function copyId(id) {
  navigator.clipboard.writeText(id).then(() => {
    alert('✅ ID kopyalandı: ' + id + '\n\nŞimdi debug-gallery.html sayfasına gidin ve Ürün ID alanına yapıştırın.');
  }).catch(() => {
    // Fallback
    const textarea = document.createElement('textarea');
    textarea.value = id;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    alert('✅ ID kopyalandı: ' + id);
  });
}
</script>
</body>
</html>
