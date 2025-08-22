<?php
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/models/Price.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// --- Ambil parameter ---
$commodity = isset($_GET['commodity']) ? $_GET['commodity'] : '';
$comparison = isset($_GET['comparison']) ? $_GET['comparison'] : 'H-7';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$market = isset($_GET['market']) ? $_GET['market'] : 'all';

// --- Hitung rentang tanggal ---
$center = strtotime($date);
$range = ($comparison === 'H-30') ? 30 : 7;
$start_date = date('Y-m-d', $center - ($range * 86400));
$end_date   = date('Y-m-d', $center + ($range * 86400));

// --- Role logic ---
$auth = new AuthController();
$currentUser = $auth->getCurrentUser();
$role = $currentUser ? $currentUser['role'] : 'masyarakat';

// --- Query ---
$db = new Database();
$params = [];
$where = "commodity_name = ?";
$params[] = $commodity;
$where .= " AND DATE(created_at) BETWEEN ? AND ?";
$params[] = $start_date;
$params[] = $end_date;

if ($role === 'uptd') {
    // Filter pasar sesuai market_assigned
    $userId = $currentUser['id'];
    $user = $db->fetchOne("SELECT market_assigned FROM users WHERE id = ?", [$userId]);
    if ($user && $user['market_assigned']) {
        $where .= " AND market_name = ?";
        $params[] = $user['market_assigned'];
    }
} elseif ($role === 'admin' && $market !== 'all') {
    $where .= " AND market_name = ?";
    $params[] = $market;
}

// --- Grafik: harga rata-rata per tanggal ---
$chartRows = $db->fetchAll(
    "SELECT DATE(created_at) as date, AVG(price) as avg_price
     FROM prices
     WHERE $where
     GROUP BY DATE(created_at)
     ORDER BY DATE(created_at) ASC",
    $params
);

// --- Tabel: semua baris sesuai filter ---
$tableRows = $db->fetchAll(
    "SELECT market_name, price, DATE(created_at) as date
     FROM prices
     WHERE $where
     ORDER BY DATE(created_at) ASC, market_name ASC",
    $params
);

// --- Format output ---
$labels = [];
$prices = [];
foreach ($chartRows as $row) {
    $labels[] = $row['date'];
    $prices[] = round($row['avg_price']);
}

$tableData = [];
foreach ($tableRows as $row) {
    $tableData[] = [
        'market_name' => $row['market_name'],
        'price' => $row['price'],
        'date' => $row['date']
    ];
}

echo json_encode([
    'labels' => $labels,
    'prices' => $prices,
    'tableData' => $tableData
]);
?>