<?php
// Geçici kontrol dosyası - shipping_groups tablosunu kontrol edelim

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/helpers.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== SHIPPING GROUPS TABLOSU ===\n\n";

try {
    $groups = db()->query(
        'SELECT id, name, carrier, min_desi, max_desi, base_fee, free_above, is_active
         FROM shipping_groups
         ORDER BY id'
    )->fetchAll();

    if (empty($groups)) {
        echo "Tablo boş!\n";
    } else {
        foreach ($groups as $group) {
            echo "ID: {$group['id']}\n";
            echo "  Name: {$group['name']}\n";
            echo "  Carrier: {$group['carrier']}\n";
            echo "  Min Desi: {$group['min_desi']}\n";
            echo "  Max Desi: " . ($group['max_desi'] ?? 'NULL') . "\n";
            echo "  Base Fee: {$group['base_fee']}\n";
            echo "  Free Above: " . ($group['free_above'] ?? 'NULL') . "\n";
            echo "  Is Active: {$group['is_active']}\n";
            echo "\n";
        }
    }

    echo "\n=== FREE SHIPPING THRESHOLD ===\n";
    echo "Global Threshold: " . freeShippingThreshold() . " TL\n\n";

    echo "\n=== TEST: 5200 TL SİPARİŞ İÇİN KARGO ÜCRETİ ===\n";
    $result = calculateShippingFeeByDesi(5, 5200);
    echo "Order Total: 5200 TL\n";
    echo "Total Desi: 5\n";
    echo "Calculated Fee: {$result['fee']} TL\n";
    echo "Free Shipping Applied: " . ($result['free_shipping_applied'] ? 'YES' : 'NO') . "\n";
    echo "Free Shipping Threshold: {$result['free_shipping_threshold']} TL\n";

} catch (Exception $e) {
    echo "HATA: " . $e->getMessage() . "\n";
}
