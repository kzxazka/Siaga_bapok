<?php
require_once __DIR__ . '/config/database.php';

echo "<h3>Generating Dummy Data for SiagaBapok</h3>";

try {
    $db = new Database();
    
    // Clear existing data first
    echo "Clearing existing price data...<br>";
    $db->query("DELETE FROM prices");
    
    // Get UPTD users
    $uptdUsers = $db->fetchAll("SELECT id, market_assigned FROM users WHERE role = 'uptd' AND market_assigned IS NOT NULL");
    
    if (empty($uptdUsers)) {
        echo "No UPTD users found. Please check your users table.<br>";
        exit;
    }
    
    // Commodity price ranges (in Rupiah)
    $commodityPrices = [
        'Beras Premium' => ['min' => 13000, 'max' => 18000],
        'Beras Medium' => ['min' => 10000, 'max' => 14000],
        'Cabai Merah' => ['min' => 20000, 'max' => 50000],
        'Cabai Rawit' => ['min' => 25000, 'max' => 60000],
        'Bawang Merah' => ['min' => 15000, 'max' => 30000],
        'Bawang Putih' => ['min' => 18000, 'max' => 35000],
        'Minyak Goreng' => ['min' => 14000, 'max' => 19000],
        'Gula Pasir' => ['min' => 12000, 'max' => 16000],
        'Daging Sapi' => ['min' => 110000, 'max' => 150000],
        'Daging Ayam' => ['min' => 25000, 'max' => 35000],
        'Telur Ayam' => ['min' => 22000, 'max' => 28000],
        'Ikan Tongkol' => ['min' => 18000, 'max' => 25000]
    ];
    
    echo "Found " . count($uptdUsers) . " UPTD users<br>";
    echo "Generating data for " . count($commodityPrices) . " commodities<br><br>";
    
    $totalInserted = 0;
    
    // Generate data for the last 45 days
    for ($dayOffset = 45; $dayOffset >= 0; $dayOffset--) {
        $currentDate = date('Y-m-d', strtotime("-{$dayOffset} days"));
        $currentDateTime = date('Y-m-d H:i:s', strtotime("-{$dayOffset} days") + rand(8*3600, 16*3600)); // Random time between 8 AM - 4 PM
        
        foreach ($uptdUsers as $uptd) {
            foreach ($commodityPrices as $commodityName => $priceRange) {
                // Add some randomness - not all commodities reported every day
                if (rand(1, 100) <= 85) { // 85% chance of reporting
                    
                    // Calculate base price with trend and volatility
                    $basePrice = ($priceRange['min'] + $priceRange['max']) / 2;
                    
                    // Add seasonal trend (some commodities more expensive in certain periods)
                    $seasonalFactor = 1;
                    if (in_array($commodityName, ['Cabai Merah', 'Cabai Rawit'])) {
                        // Cabai more expensive during rainy season (simulate)
                        $seasonalFactor = 1 + (sin(($dayOffset * 2 * pi()) / 365) * 0.3);
                    }
                    
                    // Add weekly volatility
                    $weeklyFactor = 1 + (sin(($dayOffset * 2 * pi()) / 7) * 0.1);
                    
                    // Add random daily fluctuation
                    $randomFactor = 1 + (rand(-15, 15) / 100); // ±15% random
                    
                    $finalPrice = $basePrice * $seasonalFactor * $weeklyFactor * $randomFactor;
                    
                    // Ensure price stays within reasonable bounds
                    $finalPrice = max($priceRange['min'] * 0.8, min($priceRange['max'] * 1.2, $finalPrice));
                    $finalPrice = round($finalPrice, 0);
                    
                    // Insert the price
                    $insertQuery = "INSERT INTO prices (commodity_name, price, market_name, uptd_user_id, status, approved_by, approved_at, created_at, updated_at) VALUES (?, ?, ?, ?, 'approved', 1, ?, ?, ?)";
                    
                    $db->query($insertQuery, [
                        $commodityName,
                        $finalPrice,
                        $uptd['market_assigned'],
                        $uptd['id'],
                        $currentDateTime,
                        $currentDateTime,
                        $currentDateTime
                    ]);
                    
                    $totalInserted++;
                }
            }
        }
        
        if ($dayOffset % 5 == 0) {
            echo "Generated data for " . $currentDate . "...<br>";
        }
    }
    
    // Add some pending data for testing
    echo "<br>Adding some pending data for testing...<br>";
    foreach ($uptdUsers as $uptd) {
        $commodities = array_keys($commodityPrices);
        $selectedCommodities = array_slice($commodities, 0, 3); // Take first 3 commodities
        
        foreach ($selectedCommodities as $commodity) {
            $priceRange = $commodityPrices[$commodity];
            $price = rand($priceRange['min'], $priceRange['max']);
            
            $db->query(
                "INSERT INTO prices (commodity_name, price, market_name, uptd_user_id, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())",
                [$commodity, $price, $uptd['market_assigned'], $uptd['id']]
            );
        }
    }
    
    echo "<br><strong>Data Generation Complete!</strong><br>";
    echo "Total records inserted: " . $totalInserted . "<br>";
    
    // Show statistics
    $stats = $db->fetchOne("SELECT 
        COUNT(*) as total_prices,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_prices,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_prices,
        COUNT(DISTINCT commodity_name) as total_commodities,
        COUNT(DISTINCT market_name) as total_markets,
        MIN(DATE(created_at)) as earliest_date,
        MAX(DATE(created_at)) as latest_date
    FROM prices");
    
    echo "<br><strong>Database Statistics:</strong><br>";
    echo "Total prices: " . $stats['total_prices'] . "<br>";
    echo "Approved prices: " . $stats['approved_prices'] . "<br>";
    echo "Pending prices: " . $stats['pending_prices'] . "<br>";
    echo "Total commodities: " . $stats['total_commodities'] . "<br>";
    echo "Total markets: " . $stats['total_markets'] . "<br>";
    echo "Date range: " . $stats['earliest_date'] . " to " . $stats['latest_date'] . "<br>";
    
    // Show sample data
    echo "<br><strong>Sample Data (Latest 10 records):</strong><br>";
    $samples = $db->fetchAll("SELECT commodity_name, market_name, price, DATE(created_at) as date, status FROM prices ORDER BY created_at DESC LIMIT 10");
    
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Commodity</th><th>Market</th><th>Price</th><th>Date</th><th>Status</th></tr>";
    foreach ($samples as $sample) {
        echo "<tr>";
        echo "<td>" . $sample['commodity_name'] . "</td>";
        echo "<td>" . $sample['market_name'] . "</td>";
        echo "<td>Rp " . number_format($sample['price'], 0, ',', '.') . "</td>";
        echo "<td>" . $sample['date'] . "</td>";
        echo "<td>" . $sample['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><strong>You can now test the chart functionality!</strong><br>";
    echo "<a href='public/index.php'>Go to Main Page</a>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>