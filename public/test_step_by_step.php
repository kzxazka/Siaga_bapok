<?php
echo "<h2>Step by Step Test</h2>";

try {
    echo "<h3>Step 1: Load Database Config</h3>";
    require_once __DIR__ . '/../config/database.php';
    echo "✓ Database config loaded<br>";
    
    echo "<h3>Step 2: Create Database Instance</h3>";
    $db = new Database();
    echo "✓ Database instance created<br>";
    
    echo "<h3>Step 3: Test Basic Query</h3>";
    $testQuery = "SELECT COUNT(*) as count FROM commodities";
    $testResult = $db->fetchAll($testQuery);
    echo "✓ Basic query executed. Commodities count: " . $testResult[0]['count'] . "<br>";
    
    echo "<h3>Step 4: Load Price Model</h3>";
    require_once __DIR__ . '/../src/models/Price.php';
    echo "✓ Price model loaded<br>";
    
    echo "<h3>Step 5: Create Price Model Instance</h3>";
    $priceModel = new Price();
    echo "✓ Price model instance created<br>";
    
    echo "<h3>Step 6: Test Method Call</h3>";
    $today = date('Y-m-d');
    echo "Testing with date: " . $today . "<br>";
    
    $result = $priceModel->getCommodityPriceComparison($today, 7, null);
    echo "✓ Method executed successfully!<br>";
    echo "Result count: " . count($result) . "<br>";
    
    if (!empty($result)) {
        echo "<h3>Step 7: Verify Result Structure</h3>";
        $firstItem = $result[0];
        echo "✓ ID: " . $firstItem['id'] . "<br>";
        echo "✓ Name: " . $firstItem['commodity_name'] . "<br>";
        echo "✓ Unit: " . $firstItem['unit'] . "<br>";
        echo "✓ Selected Price: " . ($firstItem['selected_date_price'] ?? 'NULL') . "<br>";
        echo "✓ Comparison Price: " . ($firstItem['comparison_date_price'] ?? 'NULL') . "<br>";
        echo "✓ Percentage: " . ($firstItem['percentage_change'] ?? 'NULL') . "<br>";
        echo "✓ Chart Data Count: " . count($firstItem['chart_data_formatted']) . "<br>";
    }
    
    echo "<h3>✓ All tests passed successfully!</h3>";
    
} catch (ParseError $e) {
    echo "<h3>❌ Parse Error:</h3>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
} catch (Error $e) {
    echo "<h3>❌ Fatal Error:</h3>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
} catch (Exception $e) {
    echo "<h3>❌ Exception:</h3>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
}
?>