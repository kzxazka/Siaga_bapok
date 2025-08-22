<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/controllers/AuthController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $db = new Database();
    $auth = new AuthController();
    $currentUser = $auth->getCurrentUser();
    $role = $currentUser ? $currentUser['role'] : 'masyarakat';

    // Ambil parameter
    $period = isset($_GET['period']) ? (int)$_GET['period'] : 7;
    $referenceDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    // Validasi parameter
    if (!in_array($period, [7, 14, 30])) {
        $period = 7;
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDate)) {
        $referenceDate = date('Y-m-d');
    }

    // Hitung tanggal range
    $endDate = $referenceDate;
    $startDate = date('Y-m-d', strtotime($referenceDate . " -{$period} days"));
    $previousEndDate = date('Y-m-d', strtotime($startDate . " -1 day"));
    $previousStartDate = date('Y-m-d', strtotime($previousEndDate . " -{$period} days"));

    // Filter market berdasarkan role
    $marketCondition = '';
    $params = [];
    
    if ($role === 'uptd' && $currentUser) {
        $userInfo = $db->fetchOne("SELECT market_assigned FROM users WHERE id = ?", [$currentUser['id']]);
        if ($userInfo && $userInfo['market_assigned']) {
            $marketCondition = ' AND ps.id_pasar = ?';
            $params[] = $userInfo['market_assigned'];
        }
    }

    // Query untuk mendapatkan daftar komoditas unik
    $commoditiesQuery = "
        SELECT DISTINCT c.name AS commodity_name 
        FROM prices p
        JOIN commodities c ON p.commodity_id = c.id
        JOIN pasar ps ON p.market_id = ps.id_pasar
        WHERE p.status = 'approved' 
        {$marketCondition}
        ORDER BY c.name ASC
    ";
    
    $commodityList = $db->fetchAll($commoditiesQuery, $params);
    
    if (empty($commodityList)) {
        throw new Exception('Tidak ada data komoditas yang tersedia');
    }

    // Hitung total pasar
    $marketCountQuery = "
        SELECT COUNT(DISTINCT ps.id_pasar) as total 
        FROM prices p
        JOIN pasar ps ON p.market_id = ps.id_pasar
        WHERE p.status = 'approved'
        {$marketCondition}
    ";
    $marketCount = $db->fetchOne($marketCountQuery, $params);

    $dashboardData = [
        'success' => true,
        'period' => $period,
        'reference_date' => $referenceDate,
        'total_markets' => $marketCount['total'],
        'commodities' => []
    ];

    // Proses setiap komoditas
    foreach ($commodityList as $commodity) {
        $commodityName = $commodity['commodity_name'];
        
        // Parameters untuk query komoditas ini
        $commodityParams = array_merge([$commodityName], $params);
        
        // 1. Harga rata-rata periode saat ini
        $currentQuery = "
            SELECT AVG(p.price) as avg_price
            FROM prices p
            JOIN commodities c ON p.commodity_id = c.id
            JOIN pasar ps ON p.market_id = ps.id_pasar
            WHERE c.name = ? 
            AND p.status = 'approved'
            AND DATE(p.created_at) BETWEEN '{$startDate}' AND '{$endDate}'
            {$marketCondition}
        ";
        
        $currentData = $db->fetchOne($currentQuery, $commodityParams);
        $currentPrice = $currentData ? (float)$currentData['avg_price'] : 0;

        // 2. Harga rata-rata periode sebelumnya
        $previousQuery = "
            SELECT AVG(p.price) as avg_price
            FROM prices p
            JOIN commodities c ON p.commodity_id = c.id
            JOIN pasar ps ON p.market_id = ps.id_pasar
            WHERE c.name = ? 
            AND p.status = 'approved'
            AND DATE(p.created_at) BETWEEN '{$previousStartDate}' AND '{$previousEndDate}'
            {$marketCondition}
        ";
        
        $previousData = $db->fetchOne($previousQuery, $commodityParams);
        $previousPrice = $previousData ? (float)$previousData['avg_price'] : 0;

        // 3. Hitung persentase perubahan
        $percentageChange = 0;
        if ($previousPrice > 0) {
            $percentageChange = (($currentPrice - $previousPrice) / $previousPrice) * 100;
        }

        // 4. Data tren untuk sparkline (harian dalam periode)
        $trendQuery = "
            SELECT 
                DATE(p.created_at) as date,
                AVG(p.price) as price
            FROM prices p
            JOIN commodities c ON p.commodity_id = c.id
            JOIN pasar ps ON p.market_id = ps.id_pasar
            WHERE c.name = ? 
            AND p.status = 'approved'
            AND DATE(p.created_at) BETWEEN '{$startDate}' AND '{$endDate}'
            {$marketCondition}
            GROUP BY DATE(p.created_at)
            ORDER BY DATE(p.created_at) ASC
        ";
        
        $trendData = $db->fetchAll($trendQuery, $commodityParams);

        // Format data trend untuk sparkline
        $formattedTrend = [];
        foreach ($trendData as $trend) {
            $formattedTrend[] = [
                'date' => $trend['date'],
                'price' => (float)$trend['price']
            ];
        }

        // Tambahkan ke hasil
        $dashboardData['commodities'][] = [
            'name' => $commodityName,
            'current_price' => $currentPrice,
            'previous_price' => $previousPrice,
            'percentage_change' => round($percentageChange, 2),
            'trend_data' => $formattedTrend,
            'data_points' => count($formattedTrend)
        ];
    }

    // Urutkan berdasarkan persentase perubahan (tertinggi dulu)
    usort($dashboardData['commodities'], function($a, $b) {
        return $b['percentage_change'] <=> $a['percentage_change'];
    });

    echo json_encode($dashboardData, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("Error in get_sp2kp_dashboard_data.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'commodities' => []
    ]);
}

/**
 * Fungsi helper untuk format mata uang
 */
function formatCurrency($value) {
    return 'Rp ' . number_format($value, 0, ',', '.');
}

/**
 * Fungsi helper untuk menentukan status perubahan
 */
function getChangeStatus($percentage) {
    if ($percentage > 5) return 'naik_tinggi';
    if ($percentage > 0) return 'naik_rendah';
    if ($percentage < -5) return 'turun_tinggi';
    if ($percentage < 0) return 'turun_rendah';
    return 'tetap';
}
?>
<style>
/* SP2KP Dashboard Styling */
.sp2kp-dashboard {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.sp2kp-header {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.commodity-table {
    border-collapse: separate;
    border-spacing: 0;
}

.commodity-table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 8px;
    color: #495057;
}

.commodity-table tbody tr {
    transition: all 0.2s ease;
    border-bottom: 1px solid #f1f3f4;
}

.commodity-table tbody tr:hover {
    background-color: #f8f9fa !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.commodity-table tbody td {
    padding: 12px 8px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f3f4;
    font-size: 0.9rem;
}

.commodity-name {
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.95rem;
}

.market-count {
    font-size: 0.8rem;
    color: #6c757d;
    font-style: italic;
}

.price-current {
    font-weight: 700;
    color: #2c3e50;
    font-size: 1rem;
}

.price-previous {
    color: #6c757d;
    font-size: 0.9rem;
}

.change-badge {
    font-size: 0.8rem;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.change-badge.positive-high {
    background-color: #dc3545;
    color: white;
}

.change-badge.positive-low {
    background-color: #fd7e14;
    color: white;
}

.change-badge.stable {
    background-color: #6c757d;
    color: white;
}

.change-badge.negative-low {
    background-color: #20c997;
    color: white;
}

.change-badge.negative-high {
    background-color: #198754;
    color: white;
}

.sparkline-wrapper {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 50px;
}

.sparkline-canvas {
    border: 1px solid #e9ecef;
    border-radius: 6px;
    background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
}

.unit-badge {
    background-color: #e9ecef !important;
    color: #495057 !important;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 6px;
    border-radius: 4px;
}

.dashboard-legend {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-top: 1px solid #dee2e6;
    padding: 16px 20px;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-right: 16px;
    margin-bottom: 8px;
    font-size: 0.8rem;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 2px;
    display: inline-block;
}

.dashboard-footer {
    color: #6c757d;
    font-size: 0.8rem;
    line-height: 1.4;
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10;
}

.control-group {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.control-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #ffffff;
    white-space: nowrap;
}

.form-control-custom {
    border: 1px solid rgba(255, 255, 255, 0.3);
    background-color: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    font-size: 0.9rem;
    padding: 6px 10px;
    border-radius: 4px;
}

.form-control-custom:focus {
    border-color: rgba(255, 255, 255, 0.6);
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
    background-color: rgba(255, 255, 255, 0.15);
}

.form-control-custom option {
    background-color: #ffffff;
    color: #000000;
}

.btn-print {
    background-color: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #ffffff;
    padding: 6px 12px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.btn-print:hover {
    background-color: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    color: #ffffff;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .commodity-table {
        font-size: 0.8rem;
    }
    
    .commodity-table thead th,
    .commodity-table tbody td {
        padding: 8px 6px;
    }
    
    .sparkline-wrapper {
        height: 40px;
    }
    
    .sparkline-canvas {
        width: 120px;
        height: 35px;
    }
    
    .control-group {
        justify-content: center;
    }
}

/* Print styles */
@media print {
    .sp2kp-header {
        background-color: #1e3c72 !important;
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
    }
    
    .commodity-table {
        font-size: 11px !important;
    }
    
    .commodity-table thead th,
    .commodity-table tbody td {
        padding: 4px !important;
    }
    
    .change-badge {
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
    }
    
    .sparkline-canvas {
        width: 100px !important;
        height: 30px !important;
    }
    
    .dashboard-legend {
        page-break-inside: avoid;
    }
    
    .control-group,
    .btn-print {
        display: none !important;
    }
}

/* Animation for loading states */
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.loading-pulse {
    animation: pulse 1.5s ease-in-out infinite;
}

/* Custom scrollbar for table */
.table-responsive::-webkit-scrollbar {
    height: 8px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

/* Tooltip styling for sparklines */
.chartjs-tooltip {
    opacity: 1;
    position: absolute;
    background: rgba(0, 0, 0, .8);
    color: white;
    border-radius: 3px;
    -webkit-transition: all .1s ease;
    transition: all .1s ease;
    pointer-events: none;
    -webkit-transform: translate(-50%, 0);
    transform: translate(-50%, 0);
    padding: 4px 8px;
    font-size: 12px;
}

/* Error state styling */
.error-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    color: #6c757d;
}

.error-state .error-icon {
    font-size: 48px;
    color: #dc3545;
    margin-bottom: 16px;
}

.error-state .error-message {
    font-size: 16px;
    font-weight: 500;
    margin-bottom: 8px;
}

.error-state .error-detail {
    font-size: 14px;
    color: #8e9296;
}

/* Success/Update indicators */
.update-indicator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    background-color: #d4edda;
    color: #155724;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.update-indicator .update-dot {
    width: 6px;
    height: 6px;
    background-color: #28a745;
    border-radius: 50%;
    animation: pulse 2s ease-in-out infinite;
}

/* Card shadow enhancement */
.sp2kp-card {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07), 0 1px 3px rgba(0, 0, 0, 0.12);
    border: none;
    border-radius: 8px;
    overflow: hidden;
}

/* Header gradient enhancement */
.sp2kp-header-enhanced {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}

.sp2kp-header-enhanced::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 50%, rgba(255,255,255,0.1) 100%);
    pointer-events: none;
}

/* Table row hover effect enhancement */
.commodity-table tbody tr:hover {
    background: linear-gradient(90deg, #f8f9fa 0%, #ffffff 50%, #f8f9fa 100%) !important;
    border-left: 4px solid #007bff;
    padding-left: 16px;
}

/* Sparkline container enhancement */
.sparkline-enhanced {
    position: relative;
    background: linear-gradient(45deg, #f8f9fa 0%, #ffffff 50%, #f8f9fa 100%);
    border: 1px solid #e3e6f0;
    border-radius: 6px;
    padding: 4px;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
}

/* Data quality indicators */
.data-quality-high {
    border-left: 3px solid #28a745;
}

.data-quality-medium {
    border-left: 3px solid #ffc107;
}

.data-quality-low {
    border-left: 3px solid #dc3545;
}
</style>