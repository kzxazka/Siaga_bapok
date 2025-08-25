<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/models/Price.php';

try {
    // Get parameters
    $selectedDate = $_GET['date'] ?? date('Y-m-d');
    $comparisonDays = (int)($_GET['comparison'] ?? 7);
    $uptdId = $_GET['uptd_id'] ?? null;
    
    // Validate comparison days
    if (!in_array($comparisonDays, [1, 7, 30])) {
        $comparisonDays = 7;
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
        $selectedDate = date('Y-m-d');
    }
    
    // Initialize models
    $priceModel = new Price();
    
    // Get data
    $data = $priceModel->getCommodityPriceComparison($selectedDate, $comparisonDays, $uptdId);
    
    // Format response
    $response = [
        'success' => true,
        'data' => $data,
        'filters' => [
            'selected_date' => $selectedDate,
            'comparison_days' => $comparisonDays,
            'uptd_id' => $uptdId
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>