<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Price.php';
require_once __DIR__ . '/../models/Market.php';
require_once __DIR__ . '/../models/Commodity.php';

class PublicController extends BaseController {
    protected $requireRole = null; // No login required
    
    public function __construct() {
        // Skip parent constructor to avoid authentication check
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function index() {
        $priceModel = new Price();
        $marketModel = new Market();
        $commodityModel = new Commodity();
        
        // Get filter parameters
        $days = max(1, min(30, (int)($_GET['days'] ?? 1)));
        $marketId = !empty($_GET['market_id']) ? (int)$_GET['market_id'] : null;
        $commodityId = !empty($_GET['commodity_id']) ? (int)$_GET['commodity_id'] : null;
        
        $data = [
            'stats' => [
                'total_commodities' => $commodityModel->countActive(),
                'total_markets' => $marketModel->countActive(),
                'total_prices' => $priceModel->countApproved()
            ],
            'priceTrends' => $priceModel->getPriceTrends($days, $marketId, $commodityId),
            'topCommodities' => $priceModel->getTopCommodities(5, $marketId),
            'markets' => $marketModel->getAllActive(),
            'commodities' => $commodityModel->getAllActive(),
            'selectedMarket' => $marketId,
            'selectedCommodity' => $commodityId,
            'days' => $days,
            'pageTitle' => 'Harga Bahan Pokok',
            'lastUpdated' => $priceModel->getLastUpdateTime()
        ];
        
        $this->view('public/index', $data);
    }
    
    public function getPriceData() {
        if (!is_ajax()) {
            $this->json(['error' => 'Invalid request'], 400);
        }
        
        $priceModel = new Price();
        $days = max(1, min(30, (int)($_GET['days'] ?? 1)));
        $marketId = !empty($_GET['market_id']) ? (int)$_GET['market_id'] : null;
        $commodityId = !empty($_GET['commodity_id']) ? (int)$_GET['commodity_id'] : null;
        
        $data = [
            'trends' => $priceModel->getPriceTrends($days, $marketId, $commodityId),
            'lastUpdated' => $priceModel->getLastUpdateTime()
        ];
        
        $this->json($data);
    }
    
    public function exportData() {
        $format = $_GET['format'] ?? 'csv';
        $marketId = !empty($_GET['market_id']) ? (int)$_GET['market_id'] : null;
        $commodityId = !empty($_GET['commodity_id']) ? (int)$_GET['commodity_id'] : null;
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        $priceModel = new Price();
        $data = $priceModel->getExportData($startDate, $endDate, $marketId, $commodityId);
        
        if (empty($data)) {
            $_SESSION['error'] = 'No data available for the selected criteria';
            $this->back();
        }
        
        if ($format === 'csv') {
            $this->exportToCsv($data);
        } elseif ($format === 'excel') {
            $this->exportToExcel($data);
        } else {
            $_SESSION['error'] = 'Invalid export format';
            $this->back();
        }
    }
    
    protected function exportToCsv($data) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=harga-bapok-' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel compatibility
        fputs($output, "\xEF\xBB\xBF");
        
        // Add headers
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            
            // Add data rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit;
    }
    
    protected function exportToExcel($data) {
        // Simple Excel export using CSV with .xls extension
        // For more advanced Excel features, consider using a library like PhpSpreadsheet
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename=harga-bapok-' . date('Y-m-d') . '.xls');
        
        $output = fopen('php://output', 'w');
        
        // Add headers
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]), "\t");
            
            // Add data rows
            foreach ($data as $row) {
                fputcsv($output, $row, "\t");
            }
        }
        
        fclose($output);
        exit;
    }
}
