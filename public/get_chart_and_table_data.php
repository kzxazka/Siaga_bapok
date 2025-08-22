<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/controllers/AuthController.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $auth = new AuthController();
    $currentUser = $auth->getCurrentUser();
    $role = $currentUser ? $currentUser['role'] : 'masyarakat';

    // Ambil parameter
    $range = isset($_GET['range']) ? (int)$_GET['range'] : 7;
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $market = isset($_GET['market']) ? $_GET['market'] : 'all';

    // Validasi range
    if (!in_array($range, [1, 7, 30])) {
        $range = 7;
    }

    // Hitung tanggal mulai
    $startDate = date('Y-m-d', strtotime($date . " -{$range} days"));
    $endDate = $date;

    // Filter market berdasarkan role
    $marketCondition = '';
    $params = [$startDate, $endDate];
    
    if ($role === 'uptd' && $currentUser) {
        $userInfo = $db->fetchOne("SELECT market_assigned FROM users WHERE id = ?", [$currentUser['id']]);
        if ($userInfo && $userInfo['market_assigned']) {
            $marketCondition = ' AND ps.id_pasar = ?';
            $params[] = $userInfo['market_assigned'];
        }
    } elseif ($role === 'admin' && $market !== 'all') {
        $marketCondition = ' AND ps.nama_pasar = ?';
        $params[] = $market;
    }

    // Query untuk data chart - ambil semua komoditas dengan harga rata-rata per hari
    $chartQuery = "
        SELECT 
            c.name AS commodity_name,
            DATE(p.created_at) as price_date,
            AVG(p.price) as avg_price,
            ps.nama_pasar AS market_name
        FROM prices p
        JOIN commodities c ON p.commodity_id = c.id
        JOIN pasar ps ON p.market_id = ps.id_pasar
        WHERE p.status = 'approved' 
        AND DATE(p.created_at) BETWEEN ? AND ?
        {$marketCondition}
        GROUP BY c.name, DATE(p.created_at), ps.nama_pasar
        ORDER BY price_date ASC, c.name ASC
    ";

    $chartData = $db->fetchAll($chartQuery, $params);

    // Query untuk data tabel - ambil semua data mentah
    $tableQuery = "
        SELECT 
            c.name AS commodity_name,
            ps.nama_pasar AS market_name,
            p.price,
            DATE(p.created_at) as price_date,
            p.created_at
        FROM prices p
        JOIN commodities c ON p.commodity_id = c.id
        JOIN pasar ps ON p.market_id = ps.id_pasar
        WHERE p.status = 'approved' 
        AND DATE(p.created_at) BETWEEN ? AND ?
        {$marketCondition}
        ORDER BY p.created_at DESC
    ";

    $tableData = $db->fetchAll($tableQuery, $params);

    // Proses data untuk chart
    $chartLabels = [];
    $commodities = [];
    $datasets = [];

    // Kumpulkan semua tanggal unik
    foreach ($chartData as $row) {
        if (!in_array($row['price_date'], $chartLabels)) {
            $chartLabels[] = $row['price_date'];
        }
        
        // Kelompokkan per komoditas
        if (!isset($commodities[$row['commodity_name']])) {
            $commodities[$row['commodity_name']] = [];
        }
        
        $commodities[$row['commodity_name']][$row['price_date']] = (float)$row['avg_price'];
    }

    // Sort tanggal
    sort($chartLabels);

    // Generate warna untuk setiap komoditas
    $colors = [
        'rgba(255, 99, 132, 0.8)',   // Merah
        'rgba(54, 162, 235, 0.8)',   // Biru
        'rgba(255, 206, 86, 0.8)',   // Kuning
        'rgba(75, 192, 192, 0.8)',   // Hijau tosca
        'rgba(153, 102, 255, 0.8)',  // Ungu
        'rgba(255, 159, 64, 0.8)',   // Oranye
        'rgba(199, 199, 199, 0.8)',  // Abu-abu
        'rgba(83, 102, 255, 0.8)',   // Biru tua
        'rgba(255, 99, 255, 0.8)',   // Pink
        'rgba(99, 255, 132, 0.8)',   // Hijau muda
        'rgba(132, 99, 255, 0.8)',   // Ungu muda
        'rgba(255, 132, 99, 0.8)'    // Merah muda
    ];

    $colorIndex = 0;

    // Buat dataset untuk setiap komoditas
    foreach ($commodities as $commodityName => $pricesByDate) {
        $data = [];
        
        // Isi data untuk setiap tanggal
        foreach ($chartLabels as $date) {
            $data[] = isset($pricesByDate[$date]) ? $pricesByDate[$date] : null;
        }

        $datasets[] = [
            'label' => $commodityName,
            'data' => $data,
            'borderColor' => $colors[$colorIndex % count($colors)],
            'backgroundColor' => str_replace('0.8', '0.2', $colors[$colorIndex % count($colors)]),
            'borderWidth' => 2,
            'fill' => false,
            'tension' => 0.1,
            'pointRadius' => 4,
            'pointHoverRadius' => 6
        ];
        
        $colorIndex++;
    }

    // Format data tabel
    $formattedTableData = [];
    foreach ($tableData as $row) {
        $formattedTableData[] = [
            'commodity_name' => $row['commodity_name'],
            'market_name' => $row['market_name'],
            'price' => (float)$row['price'],
            'price_formatted' => 'Rp ' . number_format($row['price'], 0, ',', '.'),
            'date' => $row['price_date'],
            'datetime' => $row['created_at']
        ];
    }

    // Response
    $response = [
        'success' => true,
        'labels' => $chartLabels,
        'datasets' => $datasets,
        'table' => $formattedTableData,
        'summary' => [
            'total_records' => count($tableData),
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'commodities_count' => count($commodities),
            'market_filter' => $market
        ]
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("Error in get_chart_and_table_data.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'labels' => [],
        'datasets' => [],
        'table' => []
    ]);
}
?>