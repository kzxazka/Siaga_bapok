<?php
echo "Quick test started...<br>";

try {
    // Test 1: Basic PHP
    echo "PHP works<br>";
    
    // Test 2: Include files
    require_once __DIR__ . '/../config/database.php';
    echo "Database config OK<br>";
    
    require_once __DIR__ . '/../src/models/Price.php';
    echo "Price model OK<br>";
    
    // Test 3: Create instance
    $priceModel = new Price();
    echo "Price model instance OK<br>";
    
    echo "Quick test completed successfully!<br>";
    
} catch (Error $e) {
    echo "Error: " . $e->getMessage() . "<br>";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "<br>";
}
?>