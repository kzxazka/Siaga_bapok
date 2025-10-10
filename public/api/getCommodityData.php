<?php
require_once __DIR__ . '/../../src/models/Database.php';
require_once __DIR__ . '/../../src/models/Price.php';

header('Content-Type: application/json');

try {
    // Ambil parameter
    $period = isset($_GET['period']) ? (int)$_GET['period'] : 7;
    $selectedDate = $_GET['date'] ?? date('Y-m-d');
    $marketId = $_GET['market'] ?? null;

    // Inisialisasi model
    $priceModel = new Price();
    
    // Dapatkan data perbandingan harga
    $data = $priceModel->getCommodityPriceComparison($selectedDate, $period, $marketId);

    if (empty($data)) {
        throw new Exception('Tidak ada data yang ditemukan untuk periode yang dipilih');
    }

    // Kembalikan respons JSON
    echo json_encode([
        'success' => true,
        'data' => $data,
        'period' => $period,
        'date' => $selectedDate
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}