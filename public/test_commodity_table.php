<?php
// Test file untuk method getCommodityPriceComparison
require_once __DIR__ . '/../src/models/Price.php';

try {
    echo "<h2>Test Method getCommodityPriceComparison</h2>";
    
    $priceModel = new Price();
    
    // Test dengan parameter default
    echo "<h3>Test 1: Default parameters</h3>";
    $result1 = $priceModel->getCommodityPriceComparison(date('Y-m-d'), 7, null);
    echo "<pre>";
    print_r($result1);
    echo "</pre>";
    
    // Test dengan tanggal spesifik
    echo "<h3>Test 2: Specific date (2024-01-01)</h3>";
    $result2 = $priceModel->getCommodityPriceComparison('2024-01-01', 7, null);
    echo "<pre>";
    print_r($result2);
    echo "</pre>";
    
    // Test dengan periode berbeda
    echo "<h3>Test 3: 30 days comparison</h3>";
    $result3 = $priceModel->getCommodityPriceComparison(date('Y-m-d'), 30, null);
    echo "<pre>";
    print_r($result3);
    echo "</pre>";
    
    echo "<h3>Test completed successfully!</h3>";
    
} catch (Exception $e) {
    echo "<h3>Error occurred:</h3>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<p><strong>Trace:</strong></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>