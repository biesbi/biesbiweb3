<?php

switch ($id) {
    case 'login':
        if ($method !== 'POST') error('Method not allowed.', 405);

        RateLimit::check('auth_login', (int) env('RATE_LIMIT_LOGIN', 5), (int) env('RATE_LIMIT_WINDOW', 300));
        if (!tableHasColumn('users', 'last_login')) {
            db()->exec('ALTER TABLE users ADD COLUMN last_login DATETIME NULL');
        }
        $passwordColumn = tableHasColumn('users', 'password_hash') ? 'password_hash' : 'password';

        $identifier = strtolower(trim(input('email', input('username', ''))));
        $password = (string) input('password', '');

        if ($identifier === '' || $password === '') {
            error('E-posta ve sifre gerekli.');
        }

        $hasUsername = tableHasColumn('users', 'username');
        $userSql = $hasUsername
            ? 'SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1'
            : 'SELECT * FROM users WHERE email = ? LIMIT 1';
        $stmt = db()->prepare($userSql);
        $stmt->execute($hasUsername ? [$identifier, $identifier] : [$identifier]);
        $user = $stmt->fetch();

        $storedPassword = $user[$passwordColumn] ?? null;
        $isValidPassword = is_string($storedPassword) && (
            password_verify($password, $storedPassword) || hash_equals($storedPassword, $password)
        );

        if (!$user || !$isValidPassword) {
            error('Kullanici adi veya sifre hatali.', 401);
        }

        if (
            tableHasColumn('users', 'email_verified_at')
            && (($user['role'] ?? 'user') !== 'admin')
            && empty($user['email_verified_at'])
            && !empty($user['email_verification_sent_at'])
        ) {
            error('Giris yapmadan once e-posta adresinizi dogrulayin.', 403);
        }

        db()->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?')->execute([$user['id']]);
        $user['last_login'] = date('Y-m-d H:i:s');

        $token = jwtEncode([
            'sub' => $user['id'],
            'user' => $user['username'] ?? $user['email'],
            'role' => (($user['role'] ?? 'user') === 'admin' ? 'admin' : 'customer'),
        ]);

        $orders = db()->prepare(
            'SELECT id, status, total, subtotal, discount, shipping_address, cargo_number, cargo_company, created_at
             FROM orders
             WHERE user_id = ?
             ORDER BY created_at DESC'
        );
        $orders->execute([$user['id']]);

        ok([
            'token' => $token,
            'user' => legacyUser([
                'id' => $user['id'],
                'username' => $user['username'] ?? $user['email'],
                'display_name' => $user['display_name'] ?? $user['name'] ?? '',
                'email' => $user['email'],
                'role' => (($user['role'] ?? 'user') === 'admin' ? 'admin' : 'customer'),
                'orders' => array_map(fn(array $order) => legacyOrder($order), $orders->fetchAll()),
            ]),
        ]);

    case 'register':
        if ($method !== 'POST') error('Method not allowed.', 405);
        $passwordColumn = tableHasColumn('users', 'password_hash') ? 'password_hash' : 'password';

        $displayName = trim(input('name', input('display_name', '')));
        $email = strtolower(trim(input('email', '')));
        $password = (string) input('password', '');

        if ($displayName === '' || $email === '' || $password === '') {
            error('Tum alanlar zorunlu.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error('Gecerli bir e-posta girin.');
        }
        if (strlen($password) < 6) {
            error('Sifre en az 6 karakter olmali.');
        }

        $hasUsername = tableHasColumn('users', 'username');
        $hasDisplayName = tableHasColumn('users', 'display_name');
        $baseUsername = strtolower(preg_replace('/[^a-z0-9]+/', '', strstr($email, '@', true) ?: $displayName));
        $username = $baseUsername !== '' ? $baseUsername : 'user' . time();
        if ($hasUsername) {
            $suffix = 1;
            $exists = db()->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            while (true) {
                $exists->execute([$username, $email]);
                $taken = $exists->fetch();
                if (!$taken) {
                    break;
                }

                $emailExists = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $emailExists->execute([$email]);
                if ($emailExists->fetch()) {
                    error('Bu e-posta zaten kayitli.', 409);
                }

                $username = $baseUsername . $suffix;
                $suffix++;
            }
        } else {
            $emailExists = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $emailExists->execute([$email]);
            if ($emailExists->fetch()) {
                error('Bu e-posta zaten kayitli.', 409);
            }
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $userIdType = tableColumnType('users', 'id') ?? '';
        $userId = str_contains($userIdType, 'char') || str_contains($userIdType, 'varchar')
            ? substr(generatePublicId(18), 0, 36)
            : null;
        if ($hasUsername && $hasDisplayName) {
            if ($userId !== null) {
                $stmt = db()->prepare(
                    "INSERT INTO users (id, username, display_name, email, $passwordColumn, role, email_verified_at)
                     VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP)"
                );
                $stmt->execute([$userId, $username, $displayName, $email, $hash, 'customer']);
            } else {
                $stmt = db()->prepare(
                    "INSERT INTO users (username, display_name, email, $passwordColumn, role, email_verified_at)
                     VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)"
                );
                $stmt->execute([$username, $displayName, $email, $hash, 'customer']);
            }
        } else {
            if ($userId !== null) {
                $stmt = db()->prepare(
                    "INSERT INTO users (id, email, name, $passwordColumn, role, is_active, email_verified_at)
                     VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP)"
                );
                $stmt->execute([$userId, $email, $displayName, $hash, 'user', 1]);
            } else {
                $stmt = db()->prepare(
                    "INSERT INTO users (email, name, $passwordColumn, role, is_active, email_verified_at)
                     VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)"
                );
                $stmt->execute([$email, $displayName, $hash, 'user', 1]);
            }
        }

        $createdId = $userId ?? db()->lastInsertId();
        $token = jwtEncode(['sub' => $createdId, 'user' => ($hasUsername ? $username : $email), 'role' => 'customer']);

        ok([
            'token' => $token,
            'user' => legacyUser([
                'id' => $createdId,
                'username' => $hasUsername ? $username : $email,
                'display_name' => $displayName,
                'email' => $email,
                'role' => 'customer',
                'orders' => [],
            ]),
            'verification_required' => false,
            'message' => 'Kayit tamamlandi.',
        ]);

    case 'verify-email':
        if ($method !== 'GET') error('Method not allowed.', 405);
        MailService::ensureSchema();

        $token = trim((string) ($sub ?? ($_GET['token'] ?? '')));
        if ($token === '') {
            error('Dogrulama tokeni gerekli.');
        }

        $nameColumn = tableHasColumn('users', 'display_name') ? 'display_name' : 'name';
        $stmt = db()->prepare(
            "SELECT id, email_verified_at, email_verification_expires_at, $nameColumn AS display_name
             FROM users
             WHERE email_verification_token = ?
             LIMIT 1"
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            error('Dogrulama baglantisi gecersiz veya kullanilmis.', 404);
        }

        if (!empty($user['email_verified_at'])) {
            ok(['success' => true, 'already_verified' => true, 'message' => 'E-posta adresiniz zaten dogrulanmis.']);
        }

        if (!empty($user['email_verification_expires_at']) && strtotime((string) $user['email_verification_expires_at']) < time()) {
            error('Dogrulama baglantisinin suresi dolmus.', 410);
        }

        $pdo = db();
        $pdo->prepare(
            'UPDATE users
             SET email_verified_at = CURRENT_TIMESTAMP,
                 email_verification_token = NULL,
                 email_verification_expires_at = NULL
             WHERE id = ?'
        )->execute([$user['id']]);

        ok([
            'success' => true,
            'message' => 'E-posta adresiniz dogrulandi. Artik giris yapabilirsiniz.',
        ]);

    case 'logout':
        if ($method !== 'POST') error('Method not allowed.', 405);
        $token = Auth::extractToken();
        if ($token) Auth::blacklist($token);
        ok(['success' => true]);

    case 'me':
        if ($method !== 'GET') error('Method not allowed.', 405);
        $payload = Auth::require();

        $select = [
            'id',
            tableHasColumn('users', 'username') ? 'username' : 'NULL AS username',
            tableHasColumn('users', 'display_name')
                ? 'display_name'
                : (tableHasColumn('users', 'name') ? 'name AS display_name' : 'NULL AS display_name'),
            'email',
            'role',
        ];
        $stmt = db()->prepare('SELECT ' . implode(', ', $select) . ' FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$payload['sub']]);
        $user = $stmt->fetch();
        if (!$user) error('Kullanici bulunamadi.', 404);

        $orders = db()->prepare(
            'SELECT id, status, total, city, district, tracking_no, cargo_carrier, created_at
             FROM orders
             WHERE user_id = ?
             ORDER BY created_at DESC'
        );
        $orders->execute([$user['id']]);
        $user['orders'] = array_map(fn(array $order) => legacyOrder($order), $orders->fetchAll());

        ok(legacyUser($user));

    case 'users':
        if ($method !== 'GET') error('Method not allowed.', 405);
        Auth::requireAdmin();

        $rows = db()->query(
            'SELECT id, '
            . (tableHasColumn('users', 'username') ? 'username' : 'NULL AS username') . ', '
            . (tableHasColumn('users', 'display_name')
                ? 'display_name'
                : (tableHasColumn('users', 'name') ? 'name AS display_name' : 'NULL AS display_name'))
            . ', email, role, created_at FROM users ORDER BY created_at DESC'
        )->fetchAll();

        ok(array_map(fn(array $user) => legacyUser($user), $rows));

    default:
        error('Auth endpoint bulunamadi.', 404);
}
