<?php
// --- Tambahan untuk filter komoditas dan pasar ---
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/models/Price.php';
require_once __DIR__ . '/../config/ai_config.php';

$auth = new AuthController();
$currentUser = $auth->getCurrentUser();
$role = $currentUser ? $currentUser['role'] : 'masyarakat';
$priceModel = new Price();

// Ambil daftar komoditas unik
$db = new Database();
$commodities = $db->fetchAll("
    SELECT DISTINCT c.id, c.name, c.unit
    FROM commodities c
    ORDER BY c.name ASC
");

// Ambil daftar pasar (untuk admin)
$markets = $db->fetchAll("SELECT id_pasar, nama_pasar FROM pasar ORDER BY nama_pasar ASC");

// Untuk UPTD, ambil pasar yang ditugaskan
$marketAssigned = null;
if ($role === 'uptd') {
    $userId = $currentUser['id'];
    $user = $db->fetchOne("SELECT market_assigned FROM users WHERE id = ?", [$userId]);
    $marketAssigned = $user ? $user['market_assigned'] : null;
}

// Get current user if logged in (optional for public page)
$currentUser = $auth->getCurrentUser();

// Tentukan tanggal referensi (default: hari ini)
$referenceDate = isset($_GET['date']) ? sanitizeInput($_GET['date']) : date('Y-m-d');

// Tentukan periode perbandingan (default: 7 hari)
$comparisonPeriod = isset($_GET['comparison']) ? (int)sanitizeInput($_GET['comparison']) : 7;
// Validasi periode perbandingan
if (!in_array($comparisonPeriod, [7, 30, 90])) {
    $comparisonPeriod = 7;
}

// Filter UPTD jika user adalah UPTD
$uptdFilter = null;
if ($currentUser && $currentUser['role'] === 'uptd') {
    $uptdFilter = $currentUser['id'];
}

// Ambil data tren harga dengan perbandingan untuk grafik SP2KP-style
$priceTrendsWithComparison = $priceModel->getPriceTrendsWithComparison($comparisonPeriod, $referenceDate, $uptdFilter);

// Ambil data harga per pasar untuk tabel
$pricesByMarket = $priceModel->getPricesByMarketAndDateRange($referenceDate, $comparisonPeriod, $uptdFilter);

// Get latest prices
$latestPrices = $db->fetchAll("
    SELECT p.id, p.price, ps.nama_pasar AS market_name, p.created_at, c.name AS commodity_name, c.unit
    FROM prices p
    JOIN commodities c ON p.commodity_id = c.id
    JOIN pasar ps ON p.market_id = ps.id_pasar
    ORDER BY p.created_at DESC
    LIMIT 50
");

// Get top increasing prices (7 days)
$topIncreasing = $priceModel->getTopIncreasingPrices(7, 5);

// Get statistics
$stats = $priceModel->getStatistics();

$db = new Database();
$totalPasar = $db->fetchOne("SELECT COUNT(*) as count FROM pasar")['count'];
$totalKomoditas = $db->fetchOne("SELECT COUNT(*) as count FROM commodities")['count'];

// Default AI insight period
$aiInsightPeriod = isset($_GET['ai_period']) ? sanitizeInput($_GET['ai_period']) : 'weekly';
if (!in_array($aiInsightPeriod, ['weekly', 'monthly', '6months'])) {
    $aiInsightPeriod = 'weekly';
}

$pageTitle = 'Beranda - Siaga Bapok';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
    <!-- SP2KP Style -->
    <link rel="stylesheet" href="assets/css/sp2kp-style.css">
    
    <style>
        :root {
            --primary-green: #000080;
            --light-green: #d4edda;
            --dark-green: #3232b9ff;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            color: white;
            padding: 4rem 0;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 2rem;
        }
        
        .btn-primary {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
        }
        
        .btn-primary:hover {
            background-color: var(--dark-green);
            border-color: var(--dark-green);
        }
        
        .period-buttons .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .sparkline-container {
    height: 40px;
    width: 150px;
    position: relative;
}

.price-change-up {
    color: #dc3545 !important;
}

.price-change-down {
    color: #28a745 !important;
}

.price-change-stable {
    color: #6c757d !important;
}

.commodity-row:hover {
    background-color: #f8f9fa !important;
}

.trend-chart {
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background-color: #ffffff;
}

@media print {
    .btn, .form-select, .form-control {
        display: none !important;
    }
    
    .card-header {
        background-color: #0d6efd !important;
        -webkit-print-color-adjust: exact;
    }
    
    .table th, .table td {
        font-size: 12px !important;
        padding: 4px !important;
    }
}
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
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-graph-up-arrow me-2"></i>
                SIAGA BAPOK
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="bi bi-house me-1"></i>Beranda
                        </a>
                    </li>
                    <?php if ($currentUser): ?>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin/dashboard.php">
                                    <i class="bi bi-speedometer2 me-1"></i>Dashboard Admin
                                </a>
                            </li>
                        <?php elseif ($currentUser['role'] === 'uptd'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="uptd/dashboard.php">
                                    <i class="bi bi-clipboard-data me-1"></i>Dashboard UPTD
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i><?php echo $currentUser['full_name']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Login
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-4">
                        Sistem Informasi Harga Bahan Pokok
                    </h1>
                    <p class="lead mb-4">
                        Pantau pergerakan harga komoditas bahan pokok di Kota Bandar Lampung secara real-time. 
                        Data terpercaya untuk kebutuhan sehari-hari Anda.
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        <button class="btn btn-light btn-lg" onclick="scrollToChart()">
                            <i class="bi bi-graph-up me-2"></i>Lihat Grafik
                        </button>
                        <button class="btn btn-outline-light btn-lg" onclick="scrollToLatestPrices()">
                            <i class="bi bi-list-ul me-2"></i>Harga Terbaru
                        </button>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <div class="bg-white bg-opacity-10 rounded-3 p-4">
                        <h3 class="mb-3">Data Terupdate</h3>
                        <p class="h5 mb-0"><?php echo date('d M Y'); ?></p>
                        <small><?php echo date('H:i'); ?> WIB</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container my-5">
        <!-- Statistics Cards -->
        <div class="row mb-5">
            <div class="col-md-3 mb-3">
                <div class="card stats-card text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-basket text-primary fs-1 mb-2"></i>
                        <h3 class="text-primary"><?= $totalKomoditas ?></h3>
                        <p class="mb-0">Jenis Komoditas</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-shop text-primary fs-1 mb-2"></i>
                        <h3 class="text-primary"><?= $totalPasar ?></h3>
                        <p class="mb-0">Pasar Terpantau</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-check-circle text-info fs-1 mb-2"></i>
                        <h3 class="text-info"><?php echo $stats['approved_count']; ?></h3>
                        <p class="mb-0">Data Terverifikasi</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-clock text-warning fs-1 mb-2"></i>
                        <h3 class="text-warning">Real-time</h3>
                        <p class="mb-0">Update Data</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Insight Section -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">
                                <i class="bi bi-robot me-2"></i>
                                Analisis AI - Insight Harga Komoditas
                            </h4>
                            <div class="btn-group btn-group-sm">
                                <a href="?ai_period=weekly" class="btn btn-light <?php echo $aiInsightPeriod === 'weekly' ? 'active' : ''; ?>">
                                    Mingguan
                                </a>
                                <a href="?ai_period=monthly" class="btn btn-light <?php echo $aiInsightPeriod === 'monthly' ? 'active' : ''; ?>">
                                    Bulanan
                                </a>
                                <a href="?ai_period=6months" class="btn btn-light <?php echo $aiInsightPeriod === '6months' ? 'active' : ''; ?>">
                                    6 Bulan
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="ai-insight-container">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Memuat analisis AI...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Increasing Prices -->
        <?php if (!empty($topIncreasing)): ?>
        <div class="row mb-5">
            <div class="col-12">
                <h2 class="text-center mb-4">
                    <i class="bi bi-trending-up text-danger me-2"></i>
                    Komoditas dengan Kenaikan Harga Tertinggi (7 Hari)
                </h2>
            </div>
            <?php foreach (array_slice($topIncreasing, 0, 3) as $index => $item): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <?php
                                $icons = ['bi-trophy-fill text-warning', 'bi-award-fill text-secondary', 'bi-star-fill text-success'];
                                ?>
                                <i class="bi <?php echo $icons[$index]; ?> fs-1"></i>
                            </div>
                            <h5 class="card-title"><?php echo htmlspecialchars($item['commodity_name']); ?></h5>
                            <?php if ($item['current_avg'] && $item['previous_avg']): ?>
                                <?php $percentage = (($item['current_avg'] - $item['previous_avg']) / $item['previous_avg']) * 100; ?>
                                <p class="card-text">
                                    <span class="badge bg-danger fs-6">
                                        +<?php echo number_format($percentage, 1); ?>%
                                    </span>
                                </p>
                                <p class="text-muted mb-2">Rata-rata saat ini:</p>
                                <h4 class="text-primary"><?php echo formatRupiah($item['current_avg']); ?>/kg</h4>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="container-fluid" id="sp2kpSection">
    <div class="row mb-5">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-0">
                                <i class="bi bi-graph-up me-2"></i>
                                Dashboard Harga Komoditas - SP2KP Style
                            </h4>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex justify-content-end align-items-center gap-2">
                                <select id="periodSelect" class="form-select form-select-sm" style="width: auto;">
                                    <option value="7">7 Hari Terakhir</option>
                                    <option value="14">14 Hari Terakhir</option>
                                    <option value="30">30 Hari Terakhir</option>
                                </select>
                                <input type="date" id="referenceDatePicker" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width: auto;">
                                <button class="btn btn-sm btn-light" onclick="window.print()">
                                    <i class="bi bi-printer"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- Loading State -->
                    <div id="dashboardLoading" class="text-center py-5">
                        <div class="spinner-border text-primary me-2"></div>
                        <span>Memuat data dashboard...</span>
                    </div>
                    
                    <!-- Dashboard Table -->
                    <div id="dashboardContent" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="commodityDashboard">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 25%;">Komoditas</th>
                                        <th style="width: 10%;">Satuan</th>
                                        <th style="width: 15%;" class="text-end">Harga Saat Ini</th>
                                        <th style="width: 15%;" class="text-end">Harga Sebelumnya</th>
                                        <th style="width: 10%;" class="text-center">Perubahan</th>
                                        <th style="width: 25%;" class="text-center">Grafik Trend (<?= date('M Y') ?>)</th>
                                    </tr>
                                </thead>
                                <tbody id="commodityTableBody">
                                    <!-- Data akan diisi oleh JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Legend -->
                        <div class="card-footer bg-light">
                            <div class="row">
                                <div class="col-12">
                                    <small class="text-muted"><strong>Keterangan:</strong></small>
                                    <div class="d-flex flex-wrap gap-3 mt-2">
                                        <span class="badge bg-danger">Naik >5%</span>
                                        <span class="badge bg-warning">Naik 0-5%</span>
                                        <span class="badge bg-secondary">Tetap</span>
                                        <span class="badge bg-info">Turun 0-5%</span>
                                        <span class="badge bg-success">Turun >5%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <small class="text-muted">
                                        Sumber: SP2KP, diolah Pusat Data dan Sistem Informasi <?= date('Y') ?> | 
                                        Data terakhir diperbarui: <span id="lastUpdated"><?= date('d M Y, H:i') ?> WIB</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Call to Action -->
        <div class="row">
            <div class="col-12 text-center">
                <div class="card bg-light">
                    <div class="card-body py-5">
                        <h3 class="mb-3">Butuh Akses Lebih Lengkap?</h3>
                        <p class="lead mb-4">
                            Login sebagai UPTD untuk input data harga atau sebagai Admin untuk mengelola sistem
                        </p>
                        <div class="d-flex justify-content-center gap-3 flex-wrap">
                            <a href="login.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-graph-up-arrow me-2"></i>Siaga Bapok</h5>
                    <p class="mb-0">Sistem Informasi Harga Bahan Pokok Kota Bandar Lampung</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>&copy; <?php echo date('Y'); ?> Siaga Bapok. All rights reserved.</small><br>
                    <small>Data terakhir diperbarui: <?php echo date('d M Y, H:i'); ?> WIB</small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment"></script>
    <!-- jQuery for DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    
    <script>
        let chartInstance = null;
let dataTable = null;

// Initialize chart and table data loading
function loadChartAndTableData() {
    // Show loading states
    showChartLoading(true);
    document.getElementById('priceTableBody').innerHTML = '<tr><td colspan="4" class="text-center"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Memuat data...</td></tr>';
    
    // Get current settings
    const range = document.querySelector('.comparison-btn.active').dataset.range;
    const date = document.getElementById('datePicker').value;
    let market = 'all';
    
    <?php if ($role === 'admin'): ?>
    market = document.getElementById('marketSelect').value;
    <?php endif; ?>
    
    // Build API URL
    const apiUrl = `get_chart_and_table_data.php?range=${range}&date=${date}&market=${encodeURIComponent(market)}`;
    
    console.log('Loading data from:', apiUrl);
    
    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            
            if (!data.success) {
                throw new Error(data.error || 'Unknown error occurred');
            }
            
            // Update chart
            updateChart(data);
            
            // Update table
            updateTable(data.table);
            
            showChartLoading(false);
        })
        .catch(error => {
            console.error('Error loading data:', error);
            showChartError(error.message);
            showChartLoading(false);
            
            document.getElementById('priceTableBody').innerHTML = 
                `<tr><td colspan="4" class="text-center text-danger">Error: ${error.message}</td></tr>`;
        });
}

// Show/hide chart loading
function showChartLoading(show) {
    const loading = document.getElementById('chartLoading');
    const canvas = document.getElementById('priceChart');
    
    if (show) {
        loading.style.display = 'block';
        canvas.style.opacity = '0.3';
    } else {
        loading.style.display = 'none';
        canvas.style.opacity = '1';
    }
}

// Show chart error
function showChartError(message) {
    const loading = document.getElementById('chartLoading');
    loading.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error: ${message}</div>`;
    loading.style.display = 'block';
}

// Update chart with new data
function updateChart(data) {
    const ctx = document.getElementById('priceChart').getContext('2d');
    
    // Destroy existing chart
    if (chartInstance) {
        chartInstance.destroy();
    }
    
    // Create new chart
    chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: data.datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                title: {
                    display: true,
                    text: `Grafik Harga Komoditas (${data.summary.date_range.start} s/d ${data.summary.date_range.end})`,
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            return 'Tanggal: ' + context[0].label;
                        },
                        label: function(context) {
                            const value = context.parsed.y;
                            if (value !== null) {
                                return context.dataset.label + ': Rp ' + value.toLocaleString('id-ID');
                            }
                            return context.dataset.label + ': Tidak ada data';
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Tanggal'
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Harga (Rp)'
                    },
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0,0,0,0.1)'
                    }
                }
            }
        }
    });
}

// Update table with new data
function updateTable(tableData) {
    const tbody = document.getElementById('priceTableBody');
    
    if (!tableData || tableData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Tidak ada data untuk periode yang dipilih</td></tr>';
        return;
    }
    
    let html = '';
    tableData.forEach(row => {
        html += `
            <tr>
                <td><strong>${row.commodity_name}</strong></td>
                <td>${row.id_pasar}</td>
                <td class="text-end"><strong>${row.price_formatted}</strong></td>
                <td>${row.date}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    
    // Initialize or update DataTable
    if (dataTable) {
        dataTable.destroy();
    }
    
    dataTable = $('#priceTable').DataTable({
        "pageLength": 10,
        "order": [[3, "desc"]],
        "columnDefs": [
            { "orderable": false, "targets": 2 }
        ],
        "language": {
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "zeroRecords": "Tidak ada data yang ditemukan",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
            "infoFiltered": "(disaring dari _MAX_ total data)",
            "search": "Cari:",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            }
        }
    });
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Load initial data
    loadChartAndTableData();
    
    // Period buttons
    document.querySelectorAll('.comparison-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.comparison-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            loadChartAndTableData();
        });
    });
    
    // Date picker
    document.getElementById('datePicker').addEventListener('change', loadChartAndTableData);
    
    // Market selector (for admin)
    <?php if ($role === 'admin'): ?>
    document.getElementById('marketSelect').addEventListener('change', loadChartAndTableData);
    <?php endif; ?>
});

/**
 * Chart Handler for SiagaBapok
 * Handles price chart visualization and table updates
 */

class SP2KPDashboard {
    constructor() {
        this.data = null;
        this.sparklineCharts = {};
        this.initializeEventListeners();
    }
    
    initializeEventListeners() {
        document.getElementById('periodSelect').addEventListener('change', () => {
            this.loadDashboardData();
        });
        
        document.getElementById('referenceDatePicker').addEventListener('change', () => {
            this.loadDashboardData();
        });
    }
    
    async loadDashboardData() {
        this.showLoading(true);
        
        try {
            const period = document.getElementById('periodSelect').value;
            const date = document.getElementById('referenceDatePicker').value;
            
            const response = await fetch(`get_sp2kp_dashboard_data.php?period=${period}&date=${date}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Terjadi kesalahan saat memuat data');
            }
            
            this.data = data;
            this.renderDashboard();
            
        } catch (error) {
            console.error('Error loading dashboard data:', error);
            this.showError(error.message);
        } finally {
            this.showLoading(false);
        }
    }
    
    showLoading(show) {
        const loading = document.getElementById('dashboardLoading');
        const content = document.getElementById('dashboardContent');
        
        if (show) {
            loading.style.display = 'block';
            content.style.display = 'none';
        } else {
            loading.style.display = 'none';
            content.style.display = 'block';
        }
    }
    
    showError(message) {
        const loading = document.getElementById('dashboardLoading');
        loading.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Error: ${message}
            </div>
        `;
    }
    
    renderDashboard() {
        const tbody = document.getElementById('commodityTableBody');
        let html = '';
        
        this.data.commodities.forEach((commodity, index) => {
            const changeClass = this.getChangeClass(commodity.percentage_change);
            const changeIcon = this.getChangeIcon(commodity.percentage_change);
            const changeBadge = this.getChangeBadge(commodity.percentage_change);
            
            html += `
                <tr class="commodity-row">
                    <td>
                        <div class="fw-bold">${commodity.name}</div>
                        <small class="text-muted">Rata-rata ${this.data.total_markets} pasar</small>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark">Rp/Kg</span>
                    </td>
                    <td class="text-end">
                        <div class="fw-bold text-primary">
                            ${this.formatCurrency(commodity.current_price)}
                        </div>
                    </td>
                    <td class="text-end">
                        <div class="text-muted">
                            ${this.formatCurrency(commodity.previous_price)}
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge ${changeBadge}">
                            ${changeIcon} ${Math.abs(commodity.percentage_change).toFixed(2)}%
                        </span>
                    </td>
                    <td class="text-center">
                        <div class="sparkline-container mx-auto">
                            <canvas id="sparkline-${index}" class="trend-chart" width="150" height="40"></canvas>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        tbody.innerHTML = html;
        
        // Render sparkline charts
        this.renderSparklines();
        
        // Update last updated time
        document.getElementById('lastUpdated').textContent = new Date().toLocaleString('id-ID');
    }
    
    renderSparklines() {
        // Destroy existing charts
        Object.values(this.sparklineCharts).forEach(chart => {
            if (chart) chart.destroy();
        });
        this.sparklineCharts = {};
        
        this.data.commodities.forEach((commodity, index) => {
            const canvas = document.getElementById(`sparkline-${index}`);
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            
            // Prepare data
            const labels = commodity.trend_data.map(item => item.date);
            const prices = commodity.trend_data.map(item => parseFloat(item.price));
            
            // Determine line color based on trend
            const lineColor = commodity.percentage_change > 5 ? '#dc3545' : 
                             commodity.percentage_change > 0 ? '#fd7e14' : 
                             commodity.percentage_change < -5 ? '#198754' : 
                             commodity.percentage_change < 0 ? '#20c997' : '#6c757d';
            
            this.sparklineCharts[`sparkline-${index}`] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        data: prices,
                        borderColor: lineColor,
                        backgroundColor: lineColor.replace(')', ', 0.1)').replace('rgb', 'rgba'),
                        borderWidth: 2,
                        fill: true,
                        tension: 0.2,
                        pointRadius: 0,
                        pointHoverRadius: 3,
                        pointBackgroundColor: lineColor,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: true,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            callbacks: {
                                title: function(context) {
                                    const date = new Date(context[0].label);
                                    return date.toLocaleDateString('id-ID');
                                },
                                label: function(context) {
                                    return `Harga: Rp ${context.parsed.y.toLocaleString('id-ID')}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false }
                    },
                    elements: {
                        point: { radius: 0, hoverRadius: 4 }
                    }
                }
            });
        });
    }
    
    getChangeClass(percentage) {
        if (percentage > 5) return 'price-change-up';
        if (percentage > 0) return 'price-change-up';
        if (percentage < -5) return 'price-change-down';
        if (percentage < 0) return 'price-change-down';
        return 'price-change-stable';
    }
    
    getChangeIcon(percentage) {
        if (percentage > 0) return '↑';
        if (percentage < 0) return '↓';
        return '→';
    }
    
    getChangeBadge(percentage) {
        if (percentage > 5) return 'bg-danger';
        if (percentage > 0) return 'bg-warning';
        if (percentage < -5) return 'bg-success';
        if (percentage < 0) return 'bg-info';
        return 'bg-secondary';
    }
    
    formatCurrency(value) {
        if (!value) return 'Rp 0';
        return 'Rp ' + Math.round(value).toLocaleString('id-ID');
    }
    
    init() {
        this.loadDashboardData();
    }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const dashboard = new SP2KPDashboard();
    dashboard.init();
});

// Expose globally for manual refresh
window.refreshChart = function() {
    priceChartHandler.refresh();
};
    </script>
</body>
</html>