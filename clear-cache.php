<?php
header('Content-Type: application/json');

$result = [];

// OPcache temizle
if (function_exists('opcache_reset')) {
    opcache_reset();
    $result['opcache'] = 'cleared';
} else {
    $result['opcache'] = 'not enabled';
}

// Realpath cache temizle
clearstatcache(true);
$result['stat_cache'] = 'cleared';

// Test API endpoint
try {
    $url = 'http://localhost/api/products?summary=1';
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    $httpCode = 200;

    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                $httpCode = (int)$matches[1];
            }
        }
    }

    $result['api_test'] = [
        'url' => $url,
        'status' => $httpCode,
        'response' => $response ? substr($response, 0, 200) : 'empty'
    ];
} catch (Exception $e) {
    $result['api_test'] = [
        'error' => $e->getMessage()
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT);
