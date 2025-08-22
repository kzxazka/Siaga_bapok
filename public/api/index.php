<?php
/**
 * API Entry Point untuk Siaga Bapok
 * 
 * File ini berfungsi sebagai entry point untuk semua request API
 * dan menangani routing ke controller yang sesuai
 */

// Set header untuk API
header('Content-Type: application/json');

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load dependencies
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/controllers/AuthController.php';

// Parse request URI
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api';

// Extract path from URI
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace($basePath, '', $path);
$path = trim($path, '/');

// Parse query parameters
$queryParams = [];
if (isset($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $queryParams);
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Parse JSON body for POST, PUT requests
$body = null;
if ($method === 'POST' || $method === 'PUT') {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $body = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendResponse(400, false, 'Invalid JSON payload');
        }
    }
}

// Initialize database connection
try {
    $db = new Database();
} catch (Exception $e) {
    sendResponse(500, false, 'Database connection error: ' . $e->getMessage());
}

// Initialize auth controller
$auth = new AuthController();

// Define routes
$routes = [
    // Auth routes
    'auth/login' => [
        'POST' => 'handleLogin',
    ],
    'auth/logout' => [
        'POST' => 'handleLogout',
    ],
    
    // Commodities routes
    'commodities' => [
        'GET' => 'getCommodities',
    ],
    
    // Markets routes
    'markets' => [
        'GET' => 'getMarkets',
    ],
    
    // Prices routes
    'prices' => [
        'GET' => 'getPrices',
        'POST' => 'createPrice',
    ],
    
    // Dashboard routes
    'dashboard/stats' => [
        'GET' => 'getDashboardStats',
    ],
    
    // Charts routes
    'charts/price-trends' => [
        'GET' => 'getPriceTrends',
    ],
];

// Handle dynamic routes with parameters
if (preg_match('#^prices/(\d+)/approve$#', $path, $matches)) {
    $path = 'prices/{id}/approve';
    $queryParams['id'] = $matches[1];
} elseif (preg_match('#^prices/(\d+)/reject$#', $path, $matches)) {
    $path = 'prices/{id}/reject';
    $queryParams['id'] = $matches[1];
}

// Route the request
if (isset($routes[$path]) && isset($routes[$path][$method])) {
    $handlerFunction = $routes[$path][$method];
    if (function_exists($handlerFunction)) {
        call_user_func($handlerFunction, $queryParams, $body, $db, $auth);
    } else {
        sendResponse(500, false, 'Handler function not implemented');
    }
} else {
    sendResponse(404, false, 'Endpoint not found');
}

/**
 * Helper function to send JSON response
 */
function sendResponse($statusCode, $success, $message = null, $data = null) {
    http_response_code($statusCode);
    
    $response = [
        'success' => $success
    ];
    
    if ($message !== null) {
        if ($success) {
            $response['message'] = $message;
        } else {
            $response['error'] = $message;
        }
    }
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Verify JWT token from Authorization header
 */
function verifyToken($auth) {
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
        sendResponse(401, false, 'Unauthorized: Token required');
    }
    
    $token = $matches[1];
    $user = $auth->verifyToken($token);
    
    if (!$user) {
        sendResponse(401, false, 'Unauthorized: Invalid token');
    }
    
    return $user;
}

/**
 * Check if user has required role
 */
function checkRole($user, $roles) {
    if (!in_array($user['role'], (array)$roles)) {
        sendResponse(403, false, 'Forbidden: Insufficient permissions');
    }
}

/**
 * Handler for login endpoint
 */
function handleLogin($params, $body, $db, $auth) {
    if (!isset($body['username']) || !isset($body['password'])) {
        sendResponse(400, false, 'Username and password are required');
    }
    
    $username = $body['username'];
    $password = $body['password'];
    
    $result = $auth->login($username, $password);
    
    if ($result['success']) {
        // Generate JWT token
        $token = $auth->generateToken($result['user']);
        
        sendResponse(200, true, 'Login successful', [
            'token' => $token,
            'user' => [
                'id' => $result['user']['id'],
                'username' => $result['user']['username'],
                'full_name' => $result['user']['full_name'],
                'role' => $result['user']['role'],
                'market_assigned' => $result['user']['market_assigned'] ?? null
            ]
        ]);
    } else {
        sendResponse(401, false, $result['message']);
    }
}

/**
 * Handler for logout endpoint
 */
function handleLogout($params, $body, $db, $auth) {
    $user = verifyToken($auth);
    $auth->logout($user['id']);
    sendResponse(200, true, 'Logout successful');
}

/**
 * Handler for commodities endpoint
 */
function getCommodities($params, $body, $db, $auth) {
    $sql = "SELECT id, name, unit, category FROM commodities ORDER BY name";
    $commodities = $db->fetchAll($sql);
    
    sendResponse(200, true, null, $commodities);
}

/**
 * Handler for markets endpoint
 */
function getMarkets($params, $body, $db, $auth) {
    $sql = "SELECT id_pasar as id, nama_pasar as name, alamat as address FROM pasar ORDER BY nama_pasar";
    $markets = $db->fetchAll($sql);
    
    sendResponse(200, true, null, $markets);
}

/**
 * Handler for prices endpoint (GET)
 */
function getPrices($params, $body, $db, $auth) {
    $sql = "SELECT 
                p.id, p.commodity_id, c.name as commodity_name, c.unit,
                p.price, p.market_id, m.nama_pasar as id,
                p.uptd_user_id, u.full_name as uptd_name,
                p.status, p.approved_by, a.full_name as approved_by_name,
                p.approved_at, p.notes, p.created_at, p.updated_at
            FROM prices p
            JOIN commodities c ON p.commodity_id = c.id
            JOIN pasar m ON p.market_id = m.id_pasar
            JOIN users u ON p.uptd_user_id = u.id
            LEFT JOIN users a ON p.approved_by = a.id
            WHERE 1=1";
    
    $sqlParams = [];
    
    // Apply filters
    if (!empty($params['commodity_id'])) {
        $sql .= " AND p.commodity_id = ?";
        $sqlParams[] = $params['commodity_id'];
    }
    
    if (!empty($params['market_id'])) {
        $sql .= " AND p.market_id = ?";
        $sqlParams[] = $params['market_id'];
    }
    
    if (!empty($params['date_from'])) {
        $sql .= " AND DATE(p.created_at) >= ?";
        $sqlParams[] = $params['date_from'];
    }
    
    if (!empty($params['date_to'])) {
        $sql .= " AND DATE(p.created_at) <= ?";
        $sqlParams[] = $params['date_to'];
    }
    
    if (!empty($params['status'])) {
        $sql .= " AND p.status = ?";
        $sqlParams[] = $params['status'];
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    // Add limit and offset for pagination
    $limit = isset($params['limit']) ? (int)$params['limit'] : 50;
    $offset = isset($params['offset']) ? (int)$params['offset'] : 0;
    
    $sql .= " LIMIT ? OFFSET ?";
    $sqlParams[] = $limit;
    $sqlParams[] = $offset;
    
    $prices = $db->fetchAll($sql, $sqlParams);
    
    // Format prices
    foreach ($prices as &$price) {
        $price['price'] = (float)$price['price'];
        $price['price_formatted'] = 'Rp ' . number_format($price['price'], 0, ',', '.');
    }
    
    sendResponse(200, true, null, $prices);
}

/**
 * Handler for prices endpoint (POST)
 */
function createPrice($params, $body, $db, $auth) {
    // Verify token and check role
    $user = verifyToken($auth);
    checkRole($user, ['uptd']);
    
    // Validate required fields
    if (!isset($body['commodity_id']) || !isset($body['price'])) {
        sendResponse(400, false, 'Commodity ID and price are required');
    }
    
    // Get market ID based on UPTD's assigned market
    $marketSql = "SELECT id_pasar FROM pasar WHERE nama_pasar = ?";
    $market = $db->fetchOne($marketSql, [$user['market_assigned']]);
    
    if (!$market) {
        sendResponse(400, false, 'Market not found for this UPTD');
    }
    
    // Insert new price
    $sql = "INSERT INTO prices (commodity_id, price, market_id, uptd_user_id, notes, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')";
    
    $params = [
        $body['commodity_id'],
        $body['price'],
        $market['id_pasar'],
        $user['id'],
        $body['notes'] ?? null
    ];
    
    try {
        $db->execute($sql, $params);
        $priceId = $db->lastInsertId();
        
        // Get the inserted price
        $newPrice = $db->fetchOne("SELECT 
                                    p.id, p.commodity_id, c.name as commodity_name, c.unit,
                                    p.price, p.market_id, ps.nama_pasar as market_name,
                                    p.uptd_user_id, u.full_name as uptd_name,
                                    p.status, p.notes, p.created_at, p.updated_at
                                FROM prices p
                                JOIN commodities c ON p.commodity_id = c.id
                                JOIN pasar ps ON p.market_id = ps.id_pasar
                                JOIN users u ON p.uptd_user_id = u.id
                                WHERE p.id = ?", [$priceId]);
        
        $newPrice['price'] = (float)$newPrice['price'];
        $newPrice['price_formatted'] = 'Rp ' . number_format($newPrice['price'], 0, ',', '.');
        
        sendResponse(201, true, 'Price added successfully', $newPrice);
    } catch (Exception $e) {
        sendResponse(500, false, 'Failed to add price: ' . $e->getMessage());
    }
}

/**
 * Handler for dashboard stats endpoint
 */
function getDashboardStats($params, $body, $db, $auth) {
    // Verify token
    $user = verifyToken($auth);
    
    // Get statistics
    $stats = [];
    
    // Total prices
    $stats['total_prices'] = $db->fetchOne("SELECT COUNT(*) as count FROM prices")['count'];
    
    // Prices by status
    $stats['pending_prices'] = $db->fetchOne("SELECT COUNT(*) as count FROM prices WHERE status = 'pending'")['count'];
    $stats['approved_prices'] = $db->fetchOne("SELECT COUNT(*) as count FROM prices WHERE status = 'approved'")['count'];
    $stats['rejected_prices'] = $db->fetchOne("SELECT COUNT(*) as count FROM prices WHERE status = 'rejected'")['count'];
    
    // Total commodities and markets
    $stats['total_commodities'] = $db->fetchOne("SELECT COUNT(*) as count FROM commodities")['count'];
    $stats['total_markets'] = $db->fetchOne("SELECT COUNT(*) as count FROM pasar")['count'];
    
    // Top increasing prices
    $topIncreasingSql = "SELECT 
                            c.name AS commodity_name, c.unit,
                            AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN p.price END) as current_price,
                            AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) 
                                    AND p.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN p.price END) as previous_price
                        FROM prices p
                        JOIN commodities c ON p.commodity_id = c.id
                        WHERE p.status = 'approved' 
                        AND p.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                        GROUP BY c.name, c.unit
                        HAVING current_price IS NOT NULL AND previous_price IS NOT NULL
                        ORDER BY (current_price - previous_price) / previous_price DESC
                        LIMIT 5";
    
    $topIncreasing = $db->fetchAll($topIncreasingSql);
    
    foreach ($topIncreasing as &$item) {
        $item['current_price'] = (float)$item['current_price'];
        $item['previous_price'] = (float)$item['previous_price'];
        $item['percentage_change'] = (($item['current_price'] - $item['previous_price']) / $item['previous_price']) * 100;
    }
    
    $stats['top_increasing'] = $topIncreasing;
    
    sendResponse(200, true, null, $stats);
}

/**
 * Handler for price trends endpoint
 */
function getPriceTrends($params, $body, $db, $auth) {
    // Get parameters
    $range = isset($params['range']) ? (int)$params['range'] : 30;
    $date = isset($params['date']) ? $params['date'] : date('Y-m-d');
    $market = isset($params['market']) ? $params['market'] : 'all';
    
    // Validate range
    if (!in_array($range, [7, 30, 90, 180])) {
        $range = 30;
    }
    
    // Calculate date range
    $startDate = date('Y-m-d', strtotime($date . " -{$range} days"));
    $endDate = $date;
    
    // Build query
    $sql = "SELECT 
                commodity_name,
                DATE(created_at) as price_date,
                AVG(price) as avg_price,
                market_name
            FROM prices 
            WHERE status = 'approved' 
            AND DATE(created_at) BETWEEN ? AND ?";
    
    $params = [$startDate, $endDate];
    
    if ($market !== 'all') {
        $sql .= " AND market_name = ?";
        $params[] = $market;
    }
    
    $sql .= " GROUP BY commodity_name, DATE(created_at), market_name
              ORDER BY price_date ASC, commodity_name ASC";
    
    $chartData = $db->fetchAll($sql, $params);
    
    // Process data for chart
    $chartLabels = [];
    $commodities = [];
    $datasets = [];
    
    // Collect unique dates
    foreach ($chartData as $row) {
        if (!in_array($row['price_date'], $chartLabels)) {
            $chartLabels[] = $row['price_date'];
        }
        
        // Group by commodity
        if (!isset($commodities[$row['commodity_name']])) {
            $commodities[$row['commodity_name']] = [];
        }
        
        $commodities[$row['commodity_name']][$row['price_date']] = (float)$row['avg_price'];
    }
    
    // Sort dates
    sort($chartLabels);
    
    // Generate colors
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
    
    // Create dataset for each commodity
    foreach ($commodities as $commodityName => $pricesByDate) {
        $data = [];
        
        // Fill data for each date
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
    
    // Response
    $response = [
        'success' => true,
        'labels' => $chartLabels,
        'datasets' => $datasets,
        'summary' => [
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'commodities_count' => count($commodities),
            'market_filter' => $market
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
?>