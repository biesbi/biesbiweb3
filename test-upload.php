<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Fotoğraf Yükleme Test</title>
<style>
  body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 2rem; }
  .container { max-width: 900px; margin: 0 auto; background: #2a2a2a; padding: 2rem; border-radius: 8px; }
  h1 { color: #00ff00; }
  .test-section { background: #333; padding: 1rem; margin: 1rem 0; border-radius: 4px; border-left: 4px solid #00ff00; }
  .error { color: #ff4444; }
  .success { color: #44ff44; }
  .warning { color: #ffaa00; }
  pre { background: #1a1a1a; padding: 1rem; border-radius: 4px; overflow-x: auto; }
  input[type="file"] { margin: 1rem 0; padding: 0.5rem; background: #444; color: #fff; border: 1px solid #00ff00; }
  button { background: #00ff00; color: #000; padding: 0.75rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
  button:hover { background: #00cc00; }
  .log { font-size: 0.9rem; }
  label { display: block; margin: 0.5rem 0; color: #00ff00; }
  select { background: #444; color: #fff; padding: 0.5rem; border: 1px solid #00ff00; }
</style>
</head>
<body>
<div class="container">
  <h1>📸 Fotoğraf Yükleme Test Aracı</h1>

  <div class="test-section">
    <h3>1️⃣ Veritabanı Kontrolü</h3>
    <?php
    require_once __DIR__ . '/api/config.php';
    require_once __DIR__ . '/api/helpers.php';

    try {
        $pdo = db();
        echo "<p class='success'>✅ Veritabanı bağlantısı başarılı</p>";

        // product_images tablosu var mı?
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'product_images'")->fetch();
        if ($tableCheck) {
            echo "<p class='success'>✅ product_images tablosu mevcut</p>";

            $count = (int) $pdo->query("SELECT COUNT(*) FROM product_images")->fetchColumn();
            echo "<p class='warning'>📊 Tabloda $count adet kayıt var</p>";

            // Son 5 kaydı göster
            $recent = $pdo->query("SELECT id, product_id, filename, is_primary, created_at FROM product_images ORDER BY created_at DESC LIMIT 5")->fetchAll();
            if ($recent) {
                echo "<p class='success'>Son 5 kayıt:</p><pre>";
                print_r($recent);
                echo "</pre>";
            }
        } else {
            echo "<p class='error'>❌ product_images tablosu BULUNAMADI!</p>";
        }

        // Upload dizini var mı?
        $uploadDir = __DIR__ . '/public/uploads/products';
        if (is_dir($uploadDir)) {
            echo "<p class='success'>✅ Upload dizini mevcut: $uploadDir</p>";
            echo "<p class='warning'>Yazılabilir mi? " . (is_writable($uploadDir) ? 'EVET ✅' : 'HAYIR ❌') . "</p>";
        } else {
            echo "<p class='error'>❌ Upload dizini BULUNAMADI: $uploadDir</p>";
        }

        // Ürünleri listele
        $products = $pdo->query("SELECT id, name FROM products LIMIT 10")->fetchAll();
        if ($products) {
            echo "<p class='success'>✅ " . count($products) . " adet ürün bulundu</p>";
        }

    } catch (Exception $e) {
        echo "<p class='error'>❌ HATA: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>
  </div>

  <div class="test-section">
    <h3>2️⃣ Fotoğraf Yükleme Testi</h3>
    <form id="uploadForm" enctype="multipart/form-data">
      <label>Ürün Seçin:</label>
      <select name="product_id" id="product_id" required>
        <option value="">-- Ürün Seçin --</option>
        <?php
        if (!empty($products)) {
            foreach ($products as $p) {
                echo "<option value='" . htmlspecialchars($p['id']) . "'>" . htmlspecialchars($p['name']) . " (ID: " . htmlspecialchars($p['id']) . ")</option>";
            }
        }
        ?>
      </select>

      <label>Fotoğraf Seçin:</label>
      <input type="file" name="image" id="image" accept="image/*" required>

      <label>
        <input type="checkbox" name="is_primary" value="1"> Ana görsel olarak işaretle
      </label>

      <button type="submit">📤 Yükle ve Test Et</button>
    </form>

    <div id="result" style="margin-top: 1rem;"></div>
  </div>

  <div class="test-section">
    <h3>3️⃣ API Log Görüntüleyici</h3>
    <?php
    $logFile = __DIR__ . '/api/logs/error.log';
    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        $lastLines = array_slice(explode("\n", $logs), -20);
        echo "<p class='warning'>Son 20 log satırı:</p>";
        echo "<pre class='log'>" . htmlspecialchars(implode("\n", $lastLines)) . "</pre>";
    } else {
        echo "<p class='warning'>Log dosyası bulunamadı: $logFile</p>";
    }
    ?>
  </div>
</div>

<script>
document.getElementById('uploadForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  const resultDiv = document.getElementById('result');
  resultDiv.innerHTML = '<p class="warning">⏳ Yükleniyor...</p>';

  const formData = new FormData();
  const productId = document.getElementById('product_id').value;
  const imageFile = document.getElementById('image').files[0];
  const isPrimary = document.querySelector('input[name="is_primary"]').checked;

  formData.append('product_id', productId);
  formData.append('image', imageFile);
  formData.append('is_primary', isPrimary ? '1' : '0');

  console.log('=== UPLOAD TEST ===');
  console.log('Product ID:', productId);
  console.log('File:', imageFile);
  console.log('Is Primary:', isPrimary);

  try {
    const response = await fetch('/api/upload/product-image', {
      method: 'POST',
      headers: {
        // Admin token'ı buraya ekleyin
        'Authorization': 'Bearer ' + prompt('Admin JWT token\'ınızı girin (yoksa /api/auth/login ile giriş yapın):')
      },
      body: formData
    });

    console.log('Response Status:', response.status);
    console.log('Response Headers:', [...response.headers.entries()]);

    const responseText = await response.text();
    console.log('Response Text:', responseText);

    let data;
    try {
      data = JSON.parse(responseText);
    } catch (e) {
      resultDiv.innerHTML = `<p class="error">❌ JSON parse hatası!</p><pre>${responseText}</pre>`;
      return;
    }

    if (response.ok) {
      resultDiv.innerHTML = `
        <p class="success">✅ Yükleme başarılı!</p>
        <pre>${JSON.stringify(data, null, 2)}</pre>
        <p class="warning">Şimdi veritabanını kontrol edin:</p>
        <button onclick="location.reload()">🔄 Sayfayı Yenile ve Kontrol Et</button>
      `;
    } else {
      resultDiv.innerHTML = `
        <p class="error">❌ Hata! Status: ${response.status}</p>
        <pre>${JSON.stringify(data, null, 2)}</pre>
      `;
    }

  } catch (error) {
    console.error('Upload error:', error);
    resultDiv.innerHTML = `<p class="error">❌ Network hatası: ${error.message}</p>`;
  }
});
</script>
</body>
</html>
