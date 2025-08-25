<?php
echo "<h2>Testing getCommodityPriceComparison Method</h2>";

try {
    // Load required files
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/models/Price.php';
    
    echo "Files loaded successfully<br>";
    
    // Create instance
    $priceModel = new Price();
    echo "Price model created successfully<br>";
    
    // Test method call
    echo "Calling getCommodityPriceComparison...<br>";
    
    $result = $priceModel->getCommodityPriceComparison(
        date('Y-m-d'),  // today
        7,               // 7 days comparison
        null             // no UPTD filter
    );
    
    echo "Method executed successfully!<br>";
    echo "Result count: " . count($result) . "<br>";
    
    if (!empty($result)) {
        echo "<h3>Sample data:</h3>";
        echo "<pre>";
        print_r(array_slice($result, 0, 2)); // Show first 2 items
        echo "</pre>";
    }
    
} catch (Error $e) {
    echo "<h3>Fatal Error:</h3>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
} catch (Exception $e) {
    echo "<h3>Exception:</h3>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
}
?>