<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Admin Şifre Kontrol</title>
<style>
  body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 2rem; }
  .container { max-width: 600px; margin: 0 auto; background: #2a2a2a; padding: 2rem; border-radius: 8px; }
  h1 { color: #00ff00; }
  .info { background: #333; padding: 1rem; margin: 1rem 0; border-radius: 4px; border-left: 4px solid #00ff00; }
  .warning { background: #553300; padding: 1rem; margin: 1rem 0; border-radius: 4px; border-left: 4px solid #ffaa00; color: #ffaa00; }
  .success { background: #003322; padding: 1rem; margin: 1rem 0; border-radius: 4px; border-left: 4px solid #00ff00; }
  pre { background: #1a1a1a; padding: 1rem; border-radius: 4px; overflow-x: auto; }
  code { background: #1a1a1a; padding: 0.2rem 0.5rem; border-radius: 4px; color: #ffaa00; }
</style>
</head>
<body>
<div class="container">
  <h1>🔐 Admin Şifre Kontrol</h1>

  <?php
  require_once __DIR__ . '/api/config.php';
  require_once __DIR__ . '/api/helpers.php';

  try {
      $pdo = db();

      // Kolonları kontrol et
      $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
      $passwordColumn = null;
      $hasUsername = false;

      foreach ($columns as $column) {
          if ($column['Field'] === 'password_hash') {
              $passwordColumn = 'password_hash';
              break;
          }
          if ($column['Field'] === 'password') {
              $passwordColumn = 'password';
          }
          if ($column['Field'] === 'username') {
              $hasUsername = true;
          }
      }

      echo "<div class='info'>";
      echo "<strong>✅ Veritabanı Bilgileri:</strong><br>";
      echo "Şifre kolonu: <code>$passwordColumn</code><br>";
      echo "Username kolonu var mı? " . ($hasUsername ? 'EVET' : 'HAYIR') . "<br>";
      echo "</div>";

      // Admin kullanıcıyı bul
      $adminWhere = $hasUsername
          ? "username = 'admin' OR email = 'admin@boomeritems.com'"
          : "email = 'admin@boomeritems.com'";

      $stmt = $pdo->prepare("SELECT id, email, $passwordColumn AS password, role FROM users WHERE $adminWhere LIMIT 1");
      $stmt->execute();
      $admin = $stmt->fetch();

      if ($admin) {
          echo "<div class='success'>";
          echo "<strong>✅ Admin Kullanıcı Bulundu:</strong><br>";
          echo "Email: <code>" . htmlspecialchars($admin['email']) . "</code><br>";
          echo "Role: <code>" . htmlspecialchars($admin['role']) . "</code><br>";
          echo "</div>";

          // Doğru şifreyi belirle
          $correctPassword = ($passwordColumn === 'password_hash') ? 'boomeritemsbaran!' : 'admin123';

          echo "<div class='warning'>";
          echo "<strong>🔑 GİRİŞ BİLGİLERİ:</strong><br><br>";

          if ($hasUsername) {
              echo "Kullanıcı Adı: <code>admin</code><br>";
          }
          echo "Email: <code>admin@boomeritems.com</code><br>";
          echo "Şifre: <code style='font-size:1.2rem;font-weight:bold;color:#ffff00;'>$correctPassword</code><br><br>";

          echo "Ya da bu bilgilerle:<br>";
          echo "Email: <code>admin@boomeritems.com</code><br>";
          echo "Şifre: <code style='font-size:1.2rem;font-weight:bold;color:#ffff00;'>$correctPassword</code>";
          echo "</div>";

          // Şifreyi test et
          echo "<div class='info'>";
          echo "<strong>🧪 Şifre Test Sonuçları:</strong><br><br>";

          $testPasswords = ['admin123', 'boomeritemsbaran!'];
          foreach ($testPasswords as $testPass) {
              $isValid = false;

              if ($passwordColumn === 'password_hash') {
                  $isValid = password_verify($testPass, $admin['password']);
              } else {
                  $isValid = hash_equals($admin['password'], $testPass);
              }

              $status = $isValid ? '✅ ÇALIŞIYOR' : '❌ Çalışmıyor';
              $color = $isValid ? '#00ff00' : '#ff4444';
              echo "<span style='color:$color'>$status</span> - Şifre: <code>$testPass</code><br>";
          }
          echo "</div>";

      } else {
          echo "<div class='warning'>";
          echo "❌ Admin kullanıcı bulunamadı!<br>";
          echo "setup.php dosyasını çalıştırın: <code>http://localhost/api/setup.php</code>";
          echo "</div>";
      }

  } catch (Exception $e) {
      echo "<div class='warning'>";
      echo "❌ HATA: " . htmlspecialchars($e->getMessage());
      echo "</div>";
  }
  ?>

  <div class="info" style="margin-top: 2rem;">
    <strong>ℹ️ Not:</strong><br>
    Bu dosyayı kullandıktan sonra <strong>mutlaka silin</strong>!<br>
    <code>rm c:\xampp\htdocs\check-password.php</code>
  </div>
</div>
</body>
</html>
