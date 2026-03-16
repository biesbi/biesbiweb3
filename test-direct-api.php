<?php
// Test direct API call
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Direct API Test</h1>";
echo "<p>Testing API endpoints directly via PHP...</p>";

// Test 1: Products Summary
echo "<h2>1. /api/products?summary=1</h2>";
$_SERVER['REQUEST_URI'] = '/api/products?summary=1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['summary'] = '1';

ob_start();
try {
    chdir(__DIR__);
    include './api/index.php';
    $output = ob_get_clean();
    echo "<pre style='background:#e8f5e9;padding:10px;border-left:4px solid green;'>";
    echo "SUCCESS:\n";
    echo htmlspecialchars($output);
    echo "</pre>";
} catch (Throwable $e) {
    $output = ob_get_clean();
    echo "<pre style='background:#ffebee;padding:10px;border-left:4px solid red;'>";
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Output so far:\n" . htmlspecialchars($output);
    echo "</pre>";
}
