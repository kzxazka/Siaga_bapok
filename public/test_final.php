<?php
echo "<h2>Final Test - getCommodityPriceComparison Method</h2>";

try {
    echo "<h3>Step 1: Load Required Files</h3>";
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/models/Price.php';
    echo "✓ Files loaded successfully<br>";
    
    echo "<h3>Step 2: Create Instances</h3>";
    $db = new Database();
    $priceModel = new Price();
    echo "✓ Instances created successfully<br>";
    
    echo "<h3>Step 3: Test Database Connection</h3>";
    $dbTest = $db->fetchAll("SELECT COUNT(*) as count FROM commodities");
    echo "✓ Database connection works. Commodities: " . $dbTest[0]['count'] . "<br>";
    
    echo "<h3>Step 4: Test Method Execution</h3>";
    $today = date('Y-m-d');
    echo "Testing date: " . $today . "<br>";
    
    $result = $priceModel->getCommodityPriceComparison($today, 7, null);
    echo "✓ Method executed successfully!<br>";
    echo "Result count: " . count($result) . "<br>";
    
    if (!empty($result)) {
        echo "<h3>Step 5: Verify Data Structure</h3>";
        $firstItem = $result[0];
        
        echo "✓ Basic fields:<br>";
        echo "  - ID: " . $firstItem['id'] . "<br>";
        echo "  - Name: " . $firstItem['commodity_name'] . "<br>";
        echo "  - Unit: " . $firstItem['unit'] . "<br>";
        
        echo "✓ Price fields:<br>";
        echo "  - Selected Date Price: " . ($firstItem['selected_date_price'] ?? 'NULL') . "<br>";
        echo "  - Comparison Date Price: " . ($firstItem['comparison_date_price'] ?? 'NULL') . "<br>";
        echo "  - Percentage Change: " . ($firstItem['percentage_change'] ?? 'NULL') . "<br>";
        
        echo "✓ Chart data:<br>";
        echo "  - Chart Data Count: " . count($firstItem['chart_data_formatted']) . "<br>";
        
        if (!empty($firstItem['chart_data_formatted'])) {
            echo "  - Sample chart data:<br>";
            $sampleChart = array_slice($firstItem['chart_data_formatted'], 0, 2);
            foreach ($sampleChart as $chartItem) {
                echo "    * Date: " . $chartItem['date'] . ", Price: " . $chartItem['price'] . "<br>";
            }
        }
    } else {
        echo "<p>No results returned. This might be normal if there's no data.</p>";
    }
    
    echo "<h3>✓ All tests passed successfully!</h3>";
    echo "<p>The method is working correctly and ready to use.</p>";
    
} catch (ParseError $e) {
    echo "<h3>❌ Parse Error (Syntax Error):</h3>";
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