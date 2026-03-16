<?php
// ═══════════════════════════════════════════════
//  CartService
//  Tüm sepet işlemleri bu servis üzerinden geçer.
//  Guest (session_id) ve Auth (user_id) destekler.
// ═══════════════════════════════════════════════

class CartService
{
    // ─── Kimlik Belirleyici ───────────────────

    /**
     * Hangi kolon üzerinden çalışacağımızı döndür.
     * Auth kullanıcısı için ['user_id', $userId]
     * Guest için ['session_id', $sessionId]
     */
    private static function identity(?string $userId, ?string $sessionId): array
    {
        if ($userId !== null) return ['user_id', $userId];
        if ($sessionId !== null) return ['session_id', $sessionId];
        return [null, null];
    }

    // ─── Sepeti Getir ─────────────────────────

    /**
     * Sepet içeriğini ürün bilgileriyle birlikte döndür.
     */
    public static function get(?string $userId, ?string $sessionId): array
    {
        [$col, $val] = self::identity($userId, $sessionId);
        if ($col === null) return ['items' => [], 'summary' => self::emptySummary()];

        $select = [
            'ci.id',
            'ci.product_id',
            'ci.quantity',
            'ci.added_at',
            'p.name',
            'p.price',
            'p.stock',
            tableHasColumn('products', 'sku') ? 'p.sku' : 'NULL AS sku',
            tableHasColumn('products', 'set_no') ? 'p.set_no' : 'NULL AS set_no',
            tableHasColumn('products', 'condition_tag') ? 'p.condition_tag' : 'NULL AS condition_tag',
            tableHasColumn('products', 'images') ? 'p.images' : 'NULL AS images',
            tableHasColumn('products', 'is_active') ? 'p.is_active' : '1 AS is_active',
            'c.name AS category_name',
            'b.name AS brand_name',
        ];

        $stmt = db()->prepare(
            "SELECT
                " . implode(",\n                ", $select) . "
             FROM cart_items ci
             JOIN products   p ON p.id = ci.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands     b ON b.id = p.brand_id
             WHERE ci.$col = ?
             ORDER BY ci.added_at DESC"
        );
        $stmt->execute([$val]);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            // Ürün pasif veya stokta yoksa işaretle
            $available = (bool) $row['is_active'];
            $stock     = (int) $row['stock'];
            $qty       = (int) $row['quantity'];

            // Stoktan fazla miktar varsa düzelt
            $effectiveQty = min($qty, $stock);

            $items[] = [
                'id'             => (int) $row['id'],
                'product_id'     => (string) $row['product_id'],
                'name'           => $row['name'],
                'price'          => (float) $row['price'],
                'quantity'       => $qty,
                'effective_qty'  => $effectiveQty,   // gerçekte sipariş edilecek miktar
                'subtotal'       => round($row['price'] * $effectiveQty, 2),
                'stock'          => $stock,
                'sku'            => $row['sku'],
                'set_no'         => $row['set_no'],
                'condition_tag'  => $row['condition_tag'],
                'images'         => json_decode($row['images'] ?? '[]', true),
                'category_name'  => $row['category_name'],
                'brand_name'     => $row['brand_name'],
                'is_available'   => $available && $stock > 0,
                'added_at'       => $row['added_at'],
            ];
        }

        return [
            'items'   => $items,
            'summary' => self::summarize($items),
        ];
    }

    // ─── Ürün Ekle / Güncelle ─────────────────

    /**
     * Sepete ürün ekle. Zaten varsa miktarı artır.
     * Stok aşımını engeller.
     */
    public static function add(?string $userId, ?string $sessionId, string $productId, int $qty = 1): array
    {
        [$col, $val] = self::identity($userId, $sessionId);
        if ($col === null) throw new RuntimeException('Kimlik (user_id veya session_id) gerekli.');
        if ($qty < 1) throw new RuntimeException('Miktar en az 1 olmalı.');

        // Ürün var mı ve aktif mi?
        $pStmt = db()->prepare('SELECT id, name, price, stock, is_active FROM products WHERE id = ? LIMIT 1');
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch();

        if (!$product)                  throw new RuntimeException('Ürün bulunamadı.');
        if (tableHasColumn('products', 'is_active') && !$product['is_active']) {
            throw new RuntimeException('Bu ürün artık satışta değil.');
        }
        if ($product['stock'] < 1)      throw new RuntimeException('Bu ürün stokta yok.');

        // Mevcut sepet miktarını bul
        $existStmt = db()->prepare(
            "SELECT id, quantity FROM cart_items WHERE $col = ? AND product_id = ? LIMIT 1"
        );
        $existStmt->execute([$val, $productId]);
        $existing = $existStmt->fetch();

        $newQty = $existing ? ($existing['quantity'] + $qty) : $qty;

        // Stok aşımı kontrolü
        if ($newQty > $product['stock']) {
            $newQty = $product['stock'];
        }

        if ($existing) {
            db()->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?')
                ->execute([$newQty, $existing['id']]);
        } else {
            $insStmt = db()->prepare(
                "INSERT INTO cart_items ($col, product_id, quantity) VALUES (?,?,?)"
            );
            $insStmt->execute([$val, $productId, $newQty]);
        }

        return ['product_id' => $productId, 'quantity' => $newQty];
    }

    // ─── Miktar Güncelle ──────────────────────

    /**
     * Belirli bir cart_item'ın miktarını güncelle.
     * qty = 0 gelirse item silinir.
     */
    public static function update(?string $userId, ?string $sessionId, int $itemId, int $qty): bool
    {
        [$col, $val] = self::identity($userId, $sessionId);
        if ($col === null) return false;

        if ($qty <= 0) {
            return self::remove($userId, $sessionId, $itemId);
        }

        // Önce item'ın bu kullanıcıya/session'a ait olduğunu doğrula (IDOR koruması)
        $chk = db()->prepare(
            "SELECT ci.id, p.stock FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             WHERE ci.id = ? AND ci.$col = ? LIMIT 1"
        );
        $chk->execute([$itemId, $val]);
        $row = $chk->fetch();

        if (!$row) return false;

        // Stok aşımı kontrolü
        $finalQty = min($qty, (int) $row['stock']);

        db()->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?')
            ->execute([$finalQty, $itemId]);

        return true;
    }

    // ─── Ürün Sil ────────────────────────────

    public static function remove(?string $userId, ?string $sessionId, int $itemId): bool
    {
        [$col, $val] = self::identity($userId, $sessionId);
        if ($col === null) return false;

        // IDOR koruması: sadece kendi item'ını silebilir
        $stmt = db()->prepare(
            "DELETE FROM cart_items WHERE id = ? AND $col = ?"
        );
        $stmt->execute([$itemId, $val]);
        return $stmt->rowCount() > 0;
    }

    // ─── Sepeti Temizle ──────────────────────

    public static function clear(?string $userId, ?string $sessionId): void
    {
        [$col, $val] = self::identity($userId, $sessionId);
        if ($col === null) return;

        db()->prepare("DELETE FROM cart_items WHERE $col = ?")->execute([$val]);
    }

    // ─── Guest → User Birleştirme ─────────────

    /**
     * Login sonrası guest sepetini kullanıcı sepetine birleştir.
     * Çakışan ürünlerde miktarlar toplanır, stok aşılmaz.
     */
    public static function merge(string $userId, string $sessionId): array
    {
        $pdo = db();

        // Guest sepetindeki ürünleri al
        $stmt = $pdo->prepare(
            'SELECT product_id, quantity FROM cart_items WHERE session_id = ?'
        );
        $stmt->execute([$sessionId]);
        $guestItems = $stmt->fetchAll();

        if (empty($guestItems)) {
            return ['merged' => 0];
        }

        $merged = 0;
        foreach ($guestItems as $gi) {
            try {
                self::add($userId, null, (string) $gi['product_id'], (int) $gi['quantity']);
                $merged++;
            } catch (RuntimeException) {
                // Pasif ürün veya stok yoksa atla, sessizce devam et
            }
        }

        // Guest sepetini temizle
        $pdo->prepare('DELETE FROM cart_items WHERE session_id = ?')->execute([$sessionId]);

        return ['merged' => $merged];
    }

    // ─── Stok Doğrulama (Checkout öncesi) ────

    /**
     * Tüm sepet ürünlerinin stokta yeterli olup olmadığını kontrol et.
     * Sorunlu ürünleri döndürür.
     */
    public static function validate(?string $userId, ?string $sessionId): array
    {
        $cart   = self::get($userId, $sessionId);
        $issues = [];

        foreach ($cart['items'] as $item) {
            if (!$item['is_available']) {
                $issues[] = [
                    'product_id' => $item['product_id'],
                    'name'       => $item['name'],
                    'reason'     => 'Ürün artık satışta değil.',
                ];
            } elseif ($item['quantity'] > $item['stock']) {
                $issues[] = [
                    'product_id' => $item['product_id'],
                    'name'       => $item['name'],
                    'reason'     => "Yetersiz stok. İstenen: {$item['quantity']}, Mevcut: {$item['stock']}",
                ];
            }
        }

        return [
            'valid'  => empty($issues),
            'issues' => $issues,
            'cart'   => $cart,
        ];
    }

    // ─── Özet Hesapla ────────────────────────

    private static function summarize(array $items): array
    {
        $subtotal  = 0;
        $itemCount = 0;

        foreach ($items as $item) {
            if ($item['is_available']) {
                $subtotal  += $item['subtotal'];
                $itemCount += $item['effective_qty'];
            }
        }

        return [
            'item_count'      => $itemCount,
            'subtotal'        => round($subtotal, 2),
            'total_products'  => count($items),
        ];
    }

    private static function emptySummary(): array
    {
        return ['item_count' => 0, 'subtotal' => 0.0, 'total_products' => 0];
    }

    // ─── Session ID Doğrulama ─────────────────

    /**
     * Gelen session_id'nin geçerli UUID v4 formatında olduğunu doğrula.
     * SQL injection ve manipulation koruması.
     */
    public static function validateSessionId(string $id): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id
        );
    }
}
