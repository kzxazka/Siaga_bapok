<?php
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Database.php';

header('Content-Type: application/json');

$auth = new AuthController();
$user = $auth->requireRole('admin');
$db = Database::getInstance();

try {
    $commodities = $db->fetchAll("
        SELECT id, name, unit, chart_color 
        FROM commodities 
        ORDER BY name ASC
    ");
    
    echo json_encode($commodities);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch commodities']);
}
