<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');

try {
    // Include required files
    require_once __DIR__ . '/../../src/controllers/AuthController.php';
    require_once __DIR__ . '/../../src/models/Database.php';
    require_once __DIR__ . '/../../src/models/Price.php';

    // Initialize database and models
    $db = Database::getInstance();
    $priceModel = new Price();
    
    // Set SQL mode to disable ONLY_FULL_GROUP_BY
    $db->execute("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

    // Get parameters from request
    $period = isset($_GET['period']) ? (int)$_GET['period'] : 7;
    $commodityId = isset($_GET['id']) ? $_GET['id'] : 'all';
    
    // Validate period
    $allowedPeriods = [1, 7, 30, 90, 180, 365];
    if (!in_array($period, $allowedPeriods)) {
        $period = 7;
    }
    
    // Calculate date range
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime("-$period days"));
    
    // If specific commodity is selected
    if ($commodityId !== 'all' && is_numeric($commodityId)) {
        $query = "
            SELECT 
                c.id as commodity_id,
                c.name as commodity_name,
                c.unit,
                c.chart_color,
                DATE(p.created_at) as price_date,
                AVG(p.price) as avg_price,
                MIN(p.created_at) as created_at
            FROM prices p
            INNER JOIN commodities c ON p.commodity_id = c.id
            WHERE p.commodity_id = ?
                AND DATE(p.created_at) BETWEEN ? AND ?
                AND p.status = 'approved'
            GROUP BY DATE(p.created_at), c.id, c.name, c.unit, c.chart_color
            ORDER BY price_date ASC
        ";
        
        $data = $db->fetchAll($query, [$commodityId, $startDate, $endDate]);
        
        if (empty($data)) {
            echo json_encode([]);
        }
        
        echo json_encode($data);
        
    } else {
        // Get top 5 commodities with most recent data for "all"
        $topCommoditiesQuery = "
            SELECT 
                c.id, 
                c.name, 
                c.unit, 
                c.chart_color
            FROM (
                SELECT 
                    p.commodity_id,
                    MAX(p.created_at) as latest_date
                FROM 
                    prices p
                WHERE 
                    DATE(p.created_at) BETWEEN ? AND ?
                    AND p.status = 'approved'
                GROUP BY 
                    p.commodity_id
                ORDER BY 
                    MAX(p.created_at) DESC
                LIMIT 5
            ) as latest_prices
            JOIN 
                commodities c ON latest_prices.commodity_id = c.id
            ORDER BY 
                latest_prices.latest_date DESC
        ";
        
        $topCommodities = $db->fetchAll($topCommoditiesQuery, [$startDate, $endDate]);
        
        if (empty($topCommodities)) {
            echo json_encode([]);
            exit;
        }
        
        $commodityIds = array_column($topCommodities, 'id');
        $placeholders = implode(',', array_fill(0, count($commodityIds), '?'));
        
        // Get data for top commodities
        $query = "
            SELECT 
                c.id as commodity_id,
                c.name as commodity_name,
                c.unit,
                COALESCE(c.chart_color, '#0d6efd') as chart_color,
                DATE(p.created_at) as price_date,
                AVG(p.price) as avg_price
            FROM 
                prices p
            INNER JOIN 
                commodities c ON p.commodity_id = c.id
            WHERE 
                c.id IN ($placeholders)
                AND DATE(p.created_at) BETWEEN ? AND ?
                AND p.status = 'approved'
            GROUP BY 
                DATE(p.created_at), 
                c.id, 
                c.name, 
                c.unit, 
                c.chart_color,
                p.created_at
            ORDER BY 
                c.name ASC, 
                p.created_at ASC
        ";
        
        $params = array_merge($commodityIds, [$startDate, $endDate]);
        $data = $db->fetchAll($query, $params);
        
        if (empty($data)) {
            echo json_encode([]);
            exit;
        }
        
        // Get all unique dates
        $dates = array_unique(array_column($data, 'price_date'));
        sort($dates);
        
        // Format labels
        $labels = array_map(function($date) {
            return date('d M', strtotime($date));
        }, $dates);
        
        // Group by commodity
        $commodities = [];
        foreach ($data as $row) {
            $cid = $row['commodity_id'];
            if (!isset($commodities[$cid])) {
                $commodities[$cid] = [
                    'id' => $row['commodity_id'],
                    'name' => $row['commodity_name'],
                    'unit' => $row['unit'],
                    'chart_color' => $row['chart_color'],
                    'data' => []
                ];
            }
            $commodities[$cid]['data'][$row['price_date']] = (float)$row['avg_price'];
        }
        
        // Build datasets
        $datasets = [];
        foreach ($commodities as $commodity) {
            $dataPoints = [];
            foreach ($dates as $date) {
                $dataPoints[] = isset($commodity['data'][$date]) ? $commodity['data'][$date] : null;
            }
            
            $datasets[] = [
                'label' => $commodity['name'] . ' (' . $commodity['unit'] . ')',
                'data' => $dataPoints,
                'borderColor' => $commodity['chart_color'],
                'backgroundColor' => 'transparent',
                'borderWidth' => 2,
                'tension' => 0.2,
                'fill' => false,
                'pointRadius' => 3,
                'pointHoverRadius' => 5
            ];
        }
        
        // Return Chart.js format
        echo json_encode([
            'labels' => $labels,
            'datasets' => $datasets
        ]);
    }
    
} catch (Exception $e) {
    // Log the error
    error_log('Chart data error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Failed to fetch chart data',
        'details' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>