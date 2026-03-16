<?php

final class MailService {
    public static function ensureSchema(): void {
        if (!tableExists('email_queue')) {
            db()->exec(
                'CREATE TABLE IF NOT EXISTS email_queue (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    to_email VARCHAR(180) NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    body TEXT NOT NULL,
                    status ENUM("pending","sent","failed") NOT NULL DEFAULT "pending",
                    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    sent_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_email_queue_status (status, scheduled_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci'
            );
        }

        if (tableExists('users')) {
            if (!tableHasColumn('users', 'email_verification_token')) {
                db()->exec('ALTER TABLE users ADD COLUMN email_verification_token VARCHAR(128) NULL');
            }
            if (!tableHasColumn('users', 'email_verification_sent_at')) {
                db()->exec('ALTER TABLE users ADD COLUMN email_verification_sent_at DATETIME NULL');
            }
            if (!tableHasColumn('users', 'email_verification_expires_at')) {
                db()->exec('ALTER TABLE users ADD COLUMN email_verification_expires_at DATETIME NULL');
            }
            if (!tableHasColumn('users', 'email_verified_at')) {
                db()->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL');
            }
        }
    }

    public static function sendVerificationEmail(string $email, string $name, string $token): void {
        $verifyUrl = rtrim((string) env('APP_URL', ''), '/') . '/verify-email.html?token=' . urlencode($token);
        $subject = 'BoomerItems e-posta doğrulama';
        $html = self::wrapTemplate(
            'E-posta adresinizi doğrulayın',
            $name,
            '<p>Hesabınızı doğrulamak için aşağıdaki butona tıklayın.</p>
             <p style="margin:24px 0;">
               <a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '" style="background:#dd101f;color:#ffffff;text-decoration:none;padding:14px 22px;border-radius:10px;display:inline-block;font-weight:700;">E-posta adresimi doğrula</a>
             </p>
             <p>Buton çalışmazsa bu bağlantıyı tarayıcıya yapıştırabilirsiniz:</p>
             <p style="word-break:break-all;color:#475569;">' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '</p>'
        );

        self::queueAndAttempt($email, $subject, $html);
    }

    public static function sendOrderReceivedEmail(string $email, string $name, array $order): void {
        $orderId = (string) ($order['id'] ?? '');
        $total = number_format((float) ($order['total'] ?? 0), 2, ',', '.');
        $city = (string) (($order['shippingAddress']['city'] ?? $order['shipping_address']['city'] ?? ''));
        $district = (string) (($order['shippingAddress']['district'] ?? $order['shipping_address']['district'] ?? ''));
        $subject = 'BoomerItems siparişiniz alındı';
        $html = self::wrapTemplate(
            'Siparişiniz alınmıştır',
            $name,
            '<p>Siparişiniz BoomerItems tarafından alınmıştır. Ekibimiz siparişinizi kontrol edip hazırlamaya başlayacaktır.</p>
             <table style="width:100%;border-collapse:collapse;margin:24px 0;background:#f8fafc;border-radius:12px;overflow:hidden;">
               <tr><td style="padding:12px 16px;font-weight:700;">Sipariş No</td><td style="padding:12px 16px;">' . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . '</td></tr>
               <tr><td style="padding:12px 16px;font-weight:700;">Toplam</td><td style="padding:12px 16px;">' . $total . ' TL</td></tr>
               <tr><td style="padding:12px 16px;font-weight:700;">Teslimat</td><td style="padding:12px 16px;">' . htmlspecialchars(trim($district . ' / ' . $city), ENT_QUOTES, 'UTF-8') . '</td></tr>
             </table>
             <p>Sipariş durumunuzu e-posta adresiniz ve sipariş numaranız ile takip edebilirsiniz.</p>'
        );

        self::queueAndAttempt($email, $subject, $html);
    }

    public static function wrapTemplate(string $title, string $name, string $bodyHtml): string {
        $appUrl = rtrim((string) env('APP_URL', ''), '/');
        $logoUrl = $appUrl !== '' ? $appUrl . '/logo_full.png' : '';
        $safeName = htmlspecialchars($name !== '' ? $name : 'BoomerItems kullanıcısı', ENT_QUOTES, 'UTF-8');

        return '<!doctype html>
<html lang="tr">
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <div style="max-width:640px;margin:0 auto;padding:32px 16px;">
    <div style="background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 18px 48px rgba(15,23,42,0.12);">
      <div style="background:#0f172a;padding:28px 32px;text-align:center;">
        ' . ($logoUrl !== '' ? '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="BoomerItems" style="max-width:220px;width:100%;height:auto;">' : '<div style="font-size:28px;font-weight:800;color:#ffcc00;">BOOMERITEMS</div>') . '
      </div>
      <div style="padding:32px;">
        <h1 style="margin:0 0 12px;font-size:28px;line-height:1.2;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>
        <p style="margin:0 0 20px;color:#334155;">Merhaba ' . $safeName . ',</p>
        ' . $bodyHtml . '
      </div>
    </div>
  </div>
</body>
</html>';
    }

    public static function sendTestEmail(string $toEmail): array {
        self::ensureSchema();
        $enabled = filter_var(env('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
        $html    = self::wrapTemplate(
            'Test E-postası',
            'Admin',
            '<p>Bu bir test e-postasıdır. SMTP yapılandırmanız doğru çalışıyor.</p>
             <table style="width:100%;border-collapse:collapse;margin:20px 0;background:#f8fafc;border-radius:10px;overflow:hidden;">
               <tr><td style="padding:10px 14px;font-weight:700;">MAIL_ENABLED</td><td style="padding:10px 14px;">' . ($enabled ? 'true ✓' : 'false ✗') . '</td></tr>
               <tr><td style="padding:10px 14px;font-weight:700;">MAIL_HOST</td><td style="padding:10px 14px;">' . htmlspecialchars((string) env('MAIL_HOST', '(boş)'), ENT_QUOTES, 'UTF-8') . '</td></tr>
               <tr><td style="padding:10px 14px;font-weight:700;">Gönderim Zamanı</td><td style="padding:10px 14px;">' . date('Y-m-d H:i:s') . '</td></tr>
             </table>'
        );
        $subject = 'BoomerItems — Test E-postası';

        $stmt = db()->prepare(
            'INSERT INTO email_queue (to_email, subject, body, status) VALUES (?,?,?,?)'
        );
        $stmt->execute([$toEmail, $subject, $html, 'pending']);
        $queueId = (int) db()->lastInsertId();

        $sent = false;
        if ($enabled) {
            $sent = self::deliver($toEmail, $subject, $html);
            db()->prepare(
                'UPDATE email_queue
                 SET status = ?, attempts = attempts + 1,
                     sent_at = CASE WHEN ? = "sent" THEN CURRENT_TIMESTAMP ELSE sent_at END
                 WHERE id = ?'
            )->execute([$sent ? 'sent' : 'failed', $sent ? 'sent' : 'failed', $queueId]);
        }

        return [
            'sent'         => $sent,
            'queued'       => true,
            'queue_id'     => $queueId,
            'mail_enabled' => $enabled,
            'smtp_host'    => (string) env('MAIL_HOST', ''),
        ];
    }

    private static function queueAndAttempt(string $toEmail, string $subject, string $html): void {
        self::ensureSchema();

        $stmt = db()->prepare('INSERT INTO email_queue (to_email, subject, body, status) VALUES (?,?,?,?)');
        $stmt->execute([$toEmail, $subject, $html, 'pending']);
        $queueId = (int) db()->lastInsertId();

        if (!filter_var(env('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $sent = self::deliver($toEmail, $subject, $html);
        db()->prepare(
            'UPDATE email_queue
             SET status = ?, attempts = attempts + 1, sent_at = CASE WHEN ? = "sent" THEN CURRENT_TIMESTAMP ELSE sent_at END
             WHERE id = ?'
        )->execute([$sent ? 'sent' : 'failed', $sent ? 'sent' : 'failed', $queueId]);
    }

    private static function deliver(string $toEmail, string $subject, string $html): bool {
        $vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
        if (!file_exists($vendorAutoload)) {
            $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
        }
        if (!file_exists($vendorAutoload)) {
            error_log('[MailService] vendor/autoload.php bulunamadı. "composer install" çalıştırın.');
            return false;
        }
        require_once $vendorAutoload;

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = (string) env('MAIL_HOST', 'localhost');
            $mail->SMTPAuth   = true;
            $mail->Username   = (string) env('MAIL_USER', '');
            $mail->Password   = (string) env('MAIL_PASS', '');
            $secure           = strtolower((string) env('MAIL_SECURE', 'tls'));
            $mail->SMTPSecure = $secure === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) env('MAIL_PORT', 587);
            $mail->CharSet    = 'UTF-8';

            $fromEmail = trim((string) env('MAIL_FROM_EMAIL', 'noreply@boomeritems.com'));
            $fromName  = trim((string) env('MAIL_FROM_NAME', 'BoomerItems'));
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</tr>'], "\n", $html));

            $mail->send();
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log('[MailService] SMTP hatası: ' . $e->getMessage());
            return false;
        }
    }
}
