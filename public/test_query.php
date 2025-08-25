<?php
echo "<h2>Testing SQL Query</h2>";

try {
    // Load database
    require_once __DIR__ . '/../config/database.php';
    $db = new Database();
    echo "Database connected successfully<br>";
    
    // Test simple query first
    echo "Testing simple query...<br>";
    $testResult = $db->fetchAll("SELECT COUNT(*) as count FROM commodities");
    echo "Commodities count: " . $testResult[0]['count'] . "<br>";
    
    // Test prices table
    echo "Testing prices table...<br>";
    $pricesResult = $db->fetchAll("SELECT COUNT(*) as count FROM prices WHERE status = 'approved'");
    echo "Approved prices count: " . $pricesResult[0]['count'] . "<br>";
    
    // Test the specific query structure
    echo "Testing query structure...<br>";
    $selectedDate = date('Y-m-d');
    $comparisonDate = date('Y-m-d', strtotime($selectedDate . ' -7 days'));
    
    $sql = "SELECT 
                c.id,
                c.name AS commodity_name, 
                c.unit,
                (SELECT AVG(p1.price) 
                 FROM prices p1 
                 WHERE p1.commodity_id = c.id 
                 AND DATE(p1.created_at) = ? 
                 AND p1.status = 'approved') as selected_date_price,
                (SELECT AVG(p2.price) 
                 FROM prices p2 
                 WHERE p2.commodity_id = c.id 
                 AND DATE(p2.created_at) = ? 
                 AND p2.status = 'approved') as comparison_date_price
            FROM commodities c
            WHERE c.id IN (
                SELECT DISTINCT commodity_id 
                FROM prices 
                WHERE status = 'approved'
                AND (DATE(created_at) = ? OR DATE(created_at) = ?)
            )
            ORDER BY c.name ASC
            LIMIT 5";
    
    $params = [$selectedDate, $comparisonDate, $selectedDate, $comparisonDate];
    
    echo "Executing main query...<br>";
    $result = $db->fetchAll($sql, $params);
    echo "Query executed successfully!<br>";
    echo "Result count: " . count($result) . "<br>";
    
    if (!empty($result)) {
        echo "<h3>Sample result:</h3>";
        echo "<pre>";
        print_r($result[0]);
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<h3>Error:</h3>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
}
?>