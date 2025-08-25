<?php
echo "Testing basic PHP functionality...<br>";

try {
    // Test basic database connection
    require_once __DIR__ . '/../config/database.php';
    echo "Database config loaded successfully<br>";
    
    // Test Price model
    require_once __DIR__ . '/../src/models/Price.php';
    echo "Price model loaded successfully<br>";
    
    // Test basic instantiation
    $priceModel = new Price();
    echo "Price model instantiated successfully<br>";
    
    echo "<br>All tests passed! No syntax errors found.<br>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>