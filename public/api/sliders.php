<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/models/Database.php';
require_once __DIR__ . '/../../src/models/Slider.php';

// Set JSON content type
header('Content-Type: application/json');

// Handle CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Initialize database connection
    $database = new Database();
    
    // Get active sliders
    $slider = new Slider();
    $sliders = $slider->getActiveSliders();
    
    // Prepare response
    $response = [
        'status' => 'success',
        'data' => $sliders
    ];
    
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('Slider API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan saat mengambil data slider',
        'error' => DEBUG_MODE ? $e->getMessage() : null
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
