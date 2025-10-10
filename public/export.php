<?php
session_start();
require_once __DIR__ . '/../src/models/Database.php';
require_once __DIR__ . '/../src/models/Price.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    die('Anda harus login terlebih dahulu');
}

// Ambil parameter
$type = $_GET['type'] ?? '';
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$comparisonPeriod = (int)($_GET['period'] ?? 7);
$selectedMarket = $_GET['market'] ?? 'all';

// Validasi parameter
if (!in_array($type, ['excel', 'pdf'])) {
    die('Tipe ekspor tidak valid');
}

// Inisialisasi model
try {
    $db = Database::getInstance();
    $priceModel = new Price();
    
    // Dapatkan data untuk diekspor
    $data = $priceModel->getCommodityPriceComparison($selectedDate, $comparisonPeriod, $selectedMarket);
    
    if (empty($data)) {
        die('Tidak ada data yang dapat diekspor');
    }
    
    // Panggil fungsi ekspor yang sesuai
    if ($type === 'excel') {
        exportToExcel($data, $selectedMarket, $selectedDate, $comparisonPeriod);
    } else {
        exportToPDF($data, $selectedMarket, $selectedDate, $comparisonPeriod);
    }
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

// Fungsi-fungsi exportToExcel dan exportToPDF yang sudah ada
// ... (copy-paste fungsi exportToExcel dan exportToPDF dari index.php ke sini)