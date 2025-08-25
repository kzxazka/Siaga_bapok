<?php
echo "<h2>Testing Fixed getCommodityPriceComparison Method</h2>";

try {
    // Load required files
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/models/Price.php';
    
    echo "Files loaded successfully<br>";
    
    // Create instance
    $priceModel = new Price();
    echo "Price model created successfully<br>";
    
    // Test with minimal data
    echo "Testing method with minimal parameters...<br>";
    
    $today = date('Y-m-d');
    $result = $priceModel->getCommodityPriceComparison($today, 7, null);
    
    echo "Method executed successfully!<br>";
    echo "Result count: " . count($result) . "<br>";
    
    if (!empty($result)) {
        echo "<h3>First result structure:</h3>";
        echo "<pre>";
        $firstItem = $result[0];
        echo "ID: " . $firstItem['id'] . "\n";
        echo "Name: " . $firstItem['commodity_name'] . "\n";
        echo "Unit: " . $firstItem['unit'] . "\n";
        echo "Selected Date Price: " . ($firstItem['selected_date_price'] ?? 'NULL') . "\n";
        echo "Comparison Date Price: " . ($firstItem['comparison_date_price'] ?? 'NULL') . "\n";
        echo "Percentage Change: " . ($firstItem['percentage_change'] ?? 'NULL') . "\n";
        echo "Chart Data Count: " . count($firstItem['chart_data_formatted']) . "\n";
        echo "</pre>";
        
        if (!empty($firstItem['chart_data_formatted'])) {
            echo "<h3>Sample chart data:</h3>";
            echo "<pre>";
            print_r(array_slice($firstItem['chart_data_formatted'], 0, 3));
            echo "</pre>";
        }
    } else {
        echo "<p>No results returned. This might be normal if there's no data.</p>";
    }
    
    echo "<h3>Test completed successfully!</h3>";
    
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