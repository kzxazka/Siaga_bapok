<?php
echo "Minimal test started...<br>";

// Test 1: Basic PHP
echo "PHP is working<br>";

// Test 2: Include files
try {
    require_once __DIR__ . '/../config/database.php';
    echo "Database config included<br>";
    
    require_once __DIR__ . '/../src/models/Price.php';
    echo "Price model included<br>";
    
    echo "All includes successful<br>";
    
} catch (ParseError $e) {
    echo "Parse Error: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    echo "Error: " . $e->getMessage() . "<br>";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "<br>";
}

echo "Minimal test completed<br>";
?>