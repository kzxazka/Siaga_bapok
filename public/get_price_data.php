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
$where = "c.name = ?";
$params[] = $commodity;
$where .= " AND DATE(p.created_at) BETWEEN ? AND ?";
$params[] = $start_date;
$params[] = $end_date;

if ($role === 'uptd') {
    // Filter pasar sesuai market_assigned
    $userId = $currentUser['id'];
    $user = $db->fetchOne("SELECT market_assigned FROM users WHERE id = ?", [$userId]);
    if ($user && $user['market_assigned']) {
        $where .= " AND ps.id_pasar = ?";
        $params[] = $user['market_assigned'];
    }
} elseif ($role === 'admin' && $market !== 'all') {
    $where .= " AND ps.nama_pasar = ?";
    $params[] = $market;
}

// --- Grafik: harga rata-rata per tanggal ---
$chartRows = $db->fetchAll(
    "SELECT DATE(p.created_at) as date, AVG(p.price) as avg_price
     FROM prices p
     JOIN commodities c ON p.commodity_id = c.id
     JOIN pasar ps ON p.market_id = ps.id_pasar
     WHERE $where
     GROUP BY DATE(p.created_at)
     ORDER BY DATE(p.created_at) ASC",
    $params
);

// --- Tabel: semua baris sesuai filter ---
$tableRows = $db->fetchAll(
    "SELECT ps.nama_pasar AS market_name, p.price, DATE(p.created_at) as date
     FROM prices p
     JOIN commodities c ON p.commodity_id = c.id
     JOIN pasar ps ON p.market_id = ps.id_pasar
     WHERE $where
     ORDER BY DATE(p.created_at) ASC, ps.nama_pasar ASC",
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