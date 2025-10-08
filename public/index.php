<?php
// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set lokasi log kustom
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Buat direktori logs jika belum ada
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/models/Database.php';
require_once __DIR__ . '/../src/models/Price.php';
require_once __DIR__ . '/../src/models/Commodity.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/models/Settings.php';
require_once __DIR__ . '/../src/models/Slider.php';
require_once __DIR__ . '/../src/config/app.php';

// Inisialisasi model
try {
    $db = Database::getInstance();
    
    // Debug: Periksa koneksi database
    $db->query("SELECT 1");
    error_log("Koneksi database berhasil");
    
    // Debug: Periksa tabel prices
    $pricesCount = $db->fetchOne("SELECT COUNT(*) as count FROM prices");
    error_log("Jumlah data di tabel prices: " . $pricesCount['count']);
    
    // Debug: Periksa tabel commodities
    $commoditiesCount = $db->fetchOne("SELECT COUNT(*) as count FROM commodities");
    error_log("Jumlah data di tabel commodities: " . $commoditiesCount['count']);
    
    $auth = new AuthController();
    $priceModel = new Price();
    $settingsModel = new Settings();
    $sliderModel = new Slider();
    
    // Debug: Periksa method getTopIncreasingPrices
    $testResult = $priceModel->getTopIncreasingPrices(7, 2);
    error_log("Hasil getTopIncreasingPrices: " . print_r($testResult, true));
    
} catch (Exception $e) {
    error_log("Error in index.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    die("Terjadi kesalahan. Silakan periksa log untuk detailnya.");
}

$currentUser = $auth->getCurrentUser();
$role = $currentUser ? $currentUser['role'] : 'masyarakat';

// Dapatkan pengaturan website
$settings = $settingsModel->getSettingsMap();

// Dapatkan data untuk chart dan tabel
$referenceDate = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : date('Y-m-d');
$comparisonPeriod = isset($_GET['comparison']) ? (int)$_GET['comparison'] : 7;
$comparisonPeriod = in_array($comparisonPeriod, [1, 7, 30]) ? $comparisonPeriod : 7;

$uptdFilter = ($currentUser && $currentUser['role'] === 'uptd') ? $currentUser['id'] : null;

// Dapatkan harga komoditas terbaru
$latestCommodityPrices = $priceModel->getLatestCommodityPrices(12); // Ambil 12 komoditas terbaru

// Debug: Tampilkan data komoditas
if (empty($latestCommodityPrices)) {
    error_log("Tidak ada data komoditas yang ditemukan");
    // Cek apakah ada data di tabel commodities
    $testCommodities = $db->fetchAll("SELECT * FROM commodities LIMIT 5");
    error_log("Sample data from commodities table: " . print_r($testCommodities, true));
    
    // Cek apakah ada data di tabel prices
    $testPrices = $db->fetchAll("SELECT * FROM prices ORDER BY created_at DESC LIMIT 5");
    error_log("Sample data from prices table: " . print_r($testPrices, true));
} else {
    error_log("Data komoditas ditemukan: " . count($latestCommodityPrices) . " item");
    error_log("Sample data: " . print_r(array_slice($latestCommodityPrices, 0, 1), true));
}

$commodityPriceComparison = $priceModel->getCommodityPriceComparison($referenceDate, $comparisonPeriod, $uptdFilter);
$topIncreasing = $priceModel->getTopIncreasingPrices(7, 5);
$topDecreasing = $priceModel->getTopDecreasingPrices(7, 5);
$stablePrices = $priceModel->getStablePrices(7, 5);
$stats = $priceModel->getStatistics();

// Debug: Tampilkan isi variabel
//echo '<div style="background: #f8f9fa; padding: 15px; margin: 15px 0; border: 1px solid #dee2e6; border-radius: 5px;">';
//echo '<h4>Data Debug:</h4>';
//echo '<pre>';
//echo 'Top Increasing: ' . print_r($topIncreasing, true) . "\n\n";
//echo 'Top Decreasing: ' . print_r($topDecreasing, true) . "\n\n";
//echo 'Stable Prices: ' . print_r($stablePrices, true) . "\n\n";
//echo '</pre>';
// echo '</div>';

$totalPasar = $db->fetchOne("SELECT COUNT(*) as count FROM pasar")['count'];
$totalKomoditas = $db->fetchOne("SELECT COUNT(*) as count FROM commodities")['count'];
// In the PHP section, fetch commodities with colors
$commodities = $db->fetchAll("
    SELECT c.id, c.name, c.unit, c.chart_color 
    FROM commodities c
    ORDER BY c.name ASC
");

// Ambil data slider yang aktif dari database
$sliders = $sliderModel->getActiveSliders();

$pageTitle = htmlspecialchars($settings['apps_name'] ?? 'Siagabapok');
$appTagline = htmlspecialchars($settings['apps_tagline'] ?? 'Sistem Informasi Harga Bahan Pokok');
$appDesc = htmlspecialchars($settings['apps_desc'] ?? 'Menyediakan informasi harga bahan pokok terkini di Bandar Lampung.');

// Di awal file index.php, sebelum kode HTML
if (isset($_GET['api']) && $_GET['api'] === 'commodity-prices') {
    header('Content-Type: application/json');
    try {
        $referenceDate = $_GET['date'] ?? date('Y-m-d');
        $comparisonPeriod = (int)($_GET['comparison'] ?? 7);
        $uptdFilter = ($currentUser && $currentUser['role'] === 'uptd') ? $currentUser['id'] : null;
        
        $data = $priceModel->getCommodityPriceComparison($referenceDate, $comparisonPeriod, $uptdFilter);
        
        // Pastikan data yang dikembalikan adalah array
        if (!is_array($data)) {
            throw new Exception('Data tidak valid');
        }
        
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/trend-cards.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/Siaga_bapok/public/assets/images/<?= htmlspecialchars($settings['logo'] ?? 'BANDAR LAMPUNG ICON.png') ?>">
    
    <style>
        html { scroll-behavior: smooth; }
{{ ... }}
        
        :root {
            --primary-blue: #000080;
            --dark-blue: #3232b9ff;
            --success: #198754;
            --danger: #dc3545;
        }

        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        /* Card Hover Effects */
        .card {
            border-radius: 12px !important;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
        
        .card:hover img {
            transform: scale(1.05);
        }
        
        /* Price Tag Styling */
        .card .badge {
            font-weight: 500;
            letter-spacing: 0.3px;
            padding: 5px 10px;
            border-radius: 6px;
        }
        
        /* Responsive Image Container */
        .card-img-container {
            height: 180px;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        /* Truncate long text */
        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
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

        .trend-cards-container {
            display: flex;
            overflow-x: auto;
            overflow-y: hidden;
            gap: 1.5rem;
            padding: 1rem 0.5rem;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: rgba(0, 0, 0, 0.1) transparent;
            margin: 0 -0.5rem;
        }
        
        .trend-cards-container > .d-flex {
            padding: 0.5rem;
            margin: -0.5rem 0;
        }

        /* Style untuk card tren harga */
        .trend-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border-radius: 0.75rem;
            width: 280px;
            flex: 0 0 auto;
            background: white;
            box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .trend-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1);
            border-color: rgba(0, 0, 0, 0.1);
        }
        
        .trend-card .card {
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            border-radius: 0.75rem;
        }
        
        .trend-card:hover {
            transform: translateY(-5px);
        }
        
        .trend-card:hover .card {
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1) !important;
            border-color: rgba(0, 0, 0, 0.1);
        }
        
        .trend-card .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 1.25rem;
        }
        
        .trend-card .price-change {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-weight: 600;
        }
        
        .trend-card .price-change.increase {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .trend-card .price-change.decrease {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        
        .trend-card .price-change.stable {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        
        .trend-card .commodity-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.5rem;
            transition: transform 0.3s ease;
        }
        
        .trend-card .commodity-image-placeholder {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            color: #6c757d;
            font-size: 1.5rem;
        }
        
        .trend-card .commodity-name {
            font-weight: 600;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .trend-card .price-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2c3e50;
            margin-top: 0.5rem;
        }
        
        .trend-card .price-diff {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .trend-card .unit-badge {
            background-color: #f8f9fa;
            color: #6c757d;
            font-weight: 500;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            border: 1px solid #dee2e6;
        }
        
        .trend-card .detail-btn {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 0.375rem 1rem;
        }

        .trend-cards-container::-webkit-scrollbar {
            height: 6px;
        }

        .trend-cards-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .trend-cards-container::-webkit-scrollbar-thumb {
            background-color: #888;
            border-radius: 10px;
        }

        .trend-cards-container::-webkit-scrollbar-thumb:hover {
            background-color: #555;
        }

        .trend-card {
            flex: 0 0 280px;
            min-width: 280px;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .trend-card.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .trend-card .card {
            height: 100%;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .trend-card .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        .trend-cards-container:hover + .scroll-indicator,
        .scroll-indicator:hover {
            opacity: 1;
        }

        /* Custom scrollbar for webkit browsers */
    .commodity-carousel-container::-webkit-scrollbar {
        height: 8px;
    }
    
    .commodity-carousel-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .commodity-carousel-container::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }
    
    .commodity-carousel-container::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    
    /* Hide scrollbar for Firefox */
    .commodity-carousel-container {
        scrollbar-width: thin;
        scrollbar-color: #888 #f1f1f1;
        -ms-overflow-style: none;  /* IE and Edge */
    }
    
    /* Hide scrollbar for IE, Edge and Firefox */
    .commodity-carousel-container {
        -ms-overflow-style: none;  /* IE and Edge */
        scrollbar-width: none;  /* Firefox */
    }
    
    /* Hide scrollbar for Chrome, Safari and Opera */
    .commodity-carousel-container::-webkit-scrollbar {
        display: none;
    }
    
    /* Navigation buttons */
    #prevBtn, #nextBtn {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        padding: 0;
    }

        .hover-shadow{
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .hover-shadow:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }
        .hover-shadow-sm:hover {
            background-color: #f8f9fa;
        }
        .scroll-indicator {
            transition: opacity 0.3s ease;
        }

        .price-change-up { color: var(--danger); font-weight: bold; }
        .price-change-down { color: var(--success); font-weight: bold; }
        .price-change-stable { color: #6c757d; }
        .change-up { color: var(--danger); }
        .change-down { color: var(--success); }
        .change-zero { color: #6c757d; }
        
        /* Style untuk sparkline container */
        #commodityPriceTable td:last-child {
            width: 120px;
            min-width: 120px;
        }

        /* Style untuk perubahan harga */
        .text-success {
            color: #198754 !important;
        }
        .text-danger {
            color: #dc3545 !important;
        }

        /* Style untuk tabel responsif */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Style untuk sel tabel */
        #commodityPriceTable td, #commodityPriceTable th {
            vertical-align: middle;
            white-space: nowrap;
        }

        /* Style untuk gambar komoditas */
        .commodity-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 10px;
        }
        @media print {
            .btn, .form-select, .form-control, .navbar, footer, .hero-section { display: none !important; }
            .card-header { -webkit-print-color-adjust: exact; background-color: #0d6efd !important; color: white !important; }
            .table th, .table td { font-size: 12px !important; padding: 4px !important; }
            body { background-color: #fff; }
        }
        @media (max-width: 768px) {
            .trend-card {
                flex: 0 0 240px;
                min-width: 240px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-graph-up-arrow me-2"></i>
                <?= htmlspecialchars($settings['apps_name'] ?? 'SIAGABAPOK') ?>
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
                                <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($currentUser['full_name']); ?>
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
    
    <?php if (!empty($sliders)): ?>
    <div id="mainCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <?php foreach ($sliders as $index => $slide): ?>
                <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="<?= $index ?>" class="<?= $index == 0 ? 'active' : '' ?>" aria-current="<?= $index == 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $index + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
        <div class="carousel-inner">
            <?php foreach ($sliders as $index => $slide): ?>
            <div class="carousel-item <?= $index == 0 ? 'active' : '' ?>">
                <img src="<?= htmlspecialchars($slide['image_path']) ?>" class="d-block w-100" alt="<?= htmlspecialchars($slide['title']) ?>">
                <div class="carousel-caption d-none d-md-block">
                    <h5><?= htmlspecialchars($slide['title']) ?></h5>
                    <p><?= htmlspecialchars($slide['description']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
    <?php endif; ?>

    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-4">
                        <?= htmlspecialchars($settings['apps_name'] ?? 'Sistem Informasi Harga Bahan Pokok') ?>
                    </h1>
                    <p class="lead mb-4">
                        <?= htmlspecialchars($settings['apps_tagline'] ?? 'Pantau pergerakan harga komoditas bahan pokok di Kota Bandar Lampung secara real-time. Data terpercaya untuk kebutuhan sehari-hari Anda.') ?>
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        <button class="btn btn-light btn-lg" onclick="window.location.href='#monitoringSection'">
                            <i class="bi bi-graph-up me-2"></i>Lihat Grafik
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

    <!-- Komoditas Terbaru Section -->
    <div class="container-fluid px-3 px-md-4 px-lg-5 my-4 my-lg-5">
        <div class="card shadow-sm border-0 rounded-4 mb-5 overflow-hidden">
            <div class="card-header bg-white border-0 py-3">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <div class="mb-3 mb-md-0">
                        <h2 class="h4 mb-1 fw-bold text-dark">
                            <i class="bi bi-box-seam text-primary me-2"></i>Daftar Harga Komoditas Terbaru
                        </h2>
                        <p class="text-muted mb-0 small">Harga terkini komoditas pangan terupdate</p>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="btn-group" role="group">
                            <button class="btn btn-outline-primary px-3 py-2" id="prevBtn">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button class="btn btn-outline-primary px-3 py-2" id="nextBtn">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="position-relative">
                    <div class="commodity-carousel-container" style="overflow: hidden;">
                        <div class="commodity-carousel d-flex p-3" style="transition: transform 0.5s ease; width: max-content; gap: 1.25rem;">
                            <?php 
                            $displayItems = array_slice($latestCommodityPrices, 0, 12);
                            if (!empty($displayItems)): 
                                foreach ($displayItems as $item): 
                                    $priceChange = isset($item['price_change']) ? (float)$item['price_change'] : 0;
                                    $isPriceUp = $priceChange > 0;
                                    $isPriceDown = $priceChange < 0;
                            ?>
                                <div class="commodity-card" style="width: 280px; flex-shrink: 0; transition: all 0.3s ease;">
                                    <div class="card h-100 border-0 shadow-sm hover-shadow h-100" style="border-radius: 12px; overflow: hidden;">
                                        <div style="height: 120px; overflow: hidden; position: relative; background: #f8f9fa;" class="d-flex align-items-center justify-content-center">
                                            <?php if (!empty($item['image_path'])): ?>
                                                <img src="/SIAGABAPOK/Siaga_bapok/public/uploads/commodities/<?php echo htmlspecialchars($item['image_path']); ?>" 
                                                    class="img-fluid" 
                                                    style="max-height: 100%; max-width: 100%; object-fit: contain; transition: all 0.3s ease;"
                                                    onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,<svg width=\'100%\' height=\'100%\' viewBox=\'0 0 100 100\' xmlns=\'http://www.w3.org/2000/svg\'><rect width=\'100%\' height=\'100%\' fill=\'%23f8f9fa\'/><text x=\'50%\' y=\'50%\' font-family=\'sans-serif\' font-size=\'12\' text-anchor=\'middle\' dominant-baseline=\'middle\'>No Image</text></svg>';"
                                                    alt="<?php echo htmlspecialchars($item['commodity_name']); ?>"
                                                    onmouseover="this.style.transform='scale(1.05)'"
                                                    onmouseout="this.style.transform='scale(1)'">
                                            <?php else: ?>
                                                <div class="d-flex align-items-center justify-content-center h-100 w-100">
                                                    <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($isPriceUp || $isPriceDown): ?>
                                                <span class="position-absolute top-2 end-2 badge <?php echo $isPriceUp ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success'; ?> px-2 py-1 rounded-pill fw-medium small">
                                                    <i class="bi <?php echo $isPriceUp ? 'bi-arrow-up' : 'bi-arrow-down'; ?> me-1"></i>
                                                    <?php echo abs($priceChange); ?>% (7 hari)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="card-body p-4">
                                            <div class="mb-2">
                                                <h5 class="card-title mb-1 fw-semibold text-dark" style="font-size: 1.05rem;" title="<?php echo htmlspecialchars($item['commodity_name']); ?>">
                                                    <?php echo htmlspecialchars($item['commodity_name']); ?>
                                                </h5>
                                                <p class="text-muted small mb-0"><?php echo htmlspecialchars($item['unit']); ?></p>
                                            </div>
                                            
                                            <div class="mt-3 pt-2 border-top">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <p class="text-muted small mb-1">Harga</p>
                                                        <h4 class="text-primary fw-bold mb-0">
                                                            Rp<?php echo number_format($item['latest_price'], 0, ',', '.'); ?>
                                                        </h4>
                                                    </div>
                                                    <div class="text-end">
                                                        <p class="text-muted small mb-1">Perubahan 7 Hari</p>
                                                        <span class="badge <?php echo $isPriceUp ? 'bg-danger bg-opacity-10 text-danger' : ($isPriceDown ? 'bg-success bg-opacity-10 text-success' : 'bg-light text-muted'); ?> px-2 py-1">
                                                            <?php 
                                                            if ($priceChange > 0) {
                                                                echo '<i class="bi bi-arrow-up me-1"></i>';
                                                            } elseif ($priceChange < 0) {
                                                                echo '<i class="bi bi-arrow-down me-1"></i>';
                                                            } else {
                                                                echo '<i class="bi bi-dash me-1"></i>';
                                                            }
                                                            echo abs($priceChange) . '%';
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <a href="/SIAGABAPOK/Siaga_bapok/public/commodity/<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                                    <i class="bi bi-eye me-1"></i> Lihat Detail
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                endforeach; 
                            else: 
                            ?>
                                <div class="col-12 text-center py-5">
                                    <div class="py-4">
                                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                        <p class="text-muted mt-3 mb-0">Belum ada data harga komoditas yang tersedia.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-footer bg-white border-0 py-3">
                <div class="d-flex justify-content-center">
                    <div class="btn-group" role="group">
                        <button class="btn btn-outline-primary px-4 py-2" id="viewAllBtn">
                            <i class="bi bi-grid me-2"></i>Lihat Semua Komoditas
                        </button>
                        <button class="btn btn-primary px-4 py-2" id="compareBtn">
                            <i class="bi bi-graph-up me-2"></i>Bandingkan Harga
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tren Harga Komoditas Section
    <div class="container-fluid px-3 px-md-4 px-lg-5 my-4 my-lg-5">
        <div class="card shadow-sm border-0 rounded-3 mb-5">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                    <div class="mb-3 mb-md-0">
                        <h2 class="h4 mb-1">
                            <i class="bi bi-graph-up-arrow text-primary me-2"></i>
                            Tren Harga Komoditas
                        </h2>
                        <p class="text-muted mb-0 small">
                            Perubahan harga komoditas dalam 7 hari terakhir. Geser untuk melihat lebih banyak.
                        </p>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="text-muted small d-none d-md-block">Geser untuk melihat lebih banyak</span>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary prev-btn" aria-label="Previous" disabled>
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary next-btn" aria-label="Next">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>

                < Scroll Indicator
                <div class="scroll-indicator mb-2 d-none d-md-block">
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
                
                < Horizontal Scrollable Cards 
                <div class="trend-cards-container mb-4" 
                    style="overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; padding-bottom: 1rem;"
                    id="trendCardsContainer">
                    <div class="d-flex flex-nowrap gap-4" style="min-width: max-content; padding-bottom: 1rem;">
                    
                    Meningkat
                    <?php if (!empty($topIncreasing)): ?>
                        <?php foreach ($topIncreasing as $item): ?>
                            <?php 
                            $percentage = (($item['current_avg'] - $item['previous_avg']) / $item['previous_avg']) * 100;
                            $priceDiff = $item['current_avg'] - $item['previous_avg'];
                            $formattedPrice = 'Rp' . number_format($item['current_avg'], 0, ',', '.');
                            $formattedDiff = ($priceDiff >= 0 ? '+' : '') . number_format($priceDiff, 0, ',', '.');
                            ?>
                            <div class="trend-card">
                                <div class="card h-100 border-0">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-start mb-3">
                                            <div class="flex-shrink-0 me-3">
                                                <?php if (!empty($item['image'])): ?>
                                                    <img src="<?= htmlspecialchars($item['image']) ?>" 
                                                         alt="<?= htmlspecialchars($item['commodity_name']) ?>" 
                                                         class="commodity-image">
                                                <?php else: ?>
                                                    <div class="commodity-image-placeholder">
                                                        <i class="bi bi-box-seam"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="commodity-name mb-1" title="<?= htmlspecialchars($item['commodity_name']) ?>">
                                                    <?= htmlspecialchars($item['commodity_name']) ?>
                                                </h5>
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="badge bg-light text-muted border-0 px-2 py-1">
                                                        <?= htmlspecialchars($item['unit']) ?>
                                                    </span>
                                                </div>
                                                <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1">
                                                    <i class="bi bi-arrow-up me-1"></i>
                                                    Naik <?= number_format(abs($percentage), 1) ?>%
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3 pt-3 border-top">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <div class="text-muted small mb-1">Harga Sekarang</div>
                                                    <div class="price-value"><?= $formattedPrice ?></div>
                                                </div>
                                                <div class="text-end">
                                                    <div class="text-muted small mb-1">Perubahan</div>
                                                    <div class="price-diff text-danger fw-bold">
                                                        <?= $formattedDiff ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <a href="detail-komoditas.php?id=<?= $item['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary w-100">
                                                <i class="bi bi-arrow-right me-1"></i> Lihat Detail
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                     < Menurun 
                    <?php if (!empty($topDecreasing)): ?>
                        <?php foreach ($topDecreasing as $item): ?>
                            <?php 
                            $percentage = (($item['current_avg'] - $item['previous_avg']) / $item['previous_avg']) * 100;
                            $priceDiff = $item['current_avg'] - $item['previous_avg'];
                            ?>
                            <div class="trend-card">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center mb-3">
                                            <?php if (!empty($item['image'])): ?>
                                                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['commodity_name']) ?>" class="rounded me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
                                                    <i class="bi bi-box-seam text-muted" style="font-size: 1.5rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 fw-bold text-truncate" style="max-width: 150px;"><?= htmlspecialchars($item['commodity_name']) ?></h6>
                                                <span class="badge bg-success bg-opacity-10 text-success">
                                                    <i class="bi bi-arrow-down me-1"></i> Turun <?= number_format(abs($percentage), 1) ?>%
                                                </span>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small text-muted">Harga Sekarang</span>
                                            <span class="fw-bold">Rp<?= number_format($item['current_avg'], 0, ',', '.') ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small text-muted">Perubahan</span>
                                            <span class="text-success fw-bold">-Rp<?= number_format(abs($priceDiff), 0, ',', '.') ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="small text-muted">Stok</span>
                                            <span class="badge bg-success bg-opacity-10 text-success">
                                                <i class="bi bi-check-circle me-1"></i> Tersedia
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                     Stabil
                    <?php if (!empty($stablePrices)): ?>
                        <?php foreach ($stablePrices as $item): ?>
                            <div class="trend-card">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center mb-3">
                                            <?php if (!empty($item['image'])): ?>
                                                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['commodity_name']) ?>" class="rounded me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
                                                    <i class="bi bi-box-seam text-muted" style="font-size: 1.5rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 fw-bold text-truncate" style="max-width: 150px;"><?= htmlspecialchars($item['commodity_name']) ?></h6>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                                    <i class="bi bi-dash-lg me-1"></i> Stabil
                                                </span>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small text-muted">Harga Sekarang</span>
                                            <span class="fw-bold">Rp<?= number_format($item['current_avg'], 0, ',', '.') ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small text-muted">Perubahan</span>
                                            <span class="text-muted">Rp0</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="small text-muted">Stok</span>
                                            <span class="badge bg-success bg-opacity-10 text-success">
                                                <i class="bi bi-check-circle me-1"></i> Tersedia
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
                
                <?php if (empty($topIncreasing) && empty($topDecreasing) && empty($stablePrices)): ?>
                    <div class="text-center py-5 bg-light rounded">
                        <i class="bi bi-inbox text-muted" style="font-size: 2.5rem;"></i>
                        <p class="mt-3 text-muted">Tidak ada data harga komoditas yang tersedia</p>
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <a href="#monitoringSection" class="btn btn-primary px-4">
                        <i class="bi bi-table me-2"></i>Lihat Tabel Lengkap
                    </a>
                </div>
            </div>
        </div>
            </div>
        </div>
    </div> -->

    <!-- Monitoring Harga Section -->
    <div class="container-fluid px-3 px-md-4 px-lg-5 mb-5">
        <div class="row" id="monitoringSection">
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-3 overflow-hidden">
                    <div class="card-header bg-gradient bg-primary text-white border-0">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4 class="h5 mb-1 fw-bold">
                                    <i class="bi bi-bar-chart-line me-2"></i>
                                    Monitoring Harga Komoditas
                                </h4>
                                <p class="small mb-0 opacity-75">Data harga terupdate per <?= date('d M Y') ?></p>
                            </div>
                        <div class="col-md-6">
                            <div class="d-flex flex-column flex-sm-row justify-content-md-end align-items-stretch align-items-sm-center gap-2 mt-3 mt-md-0">
                                <div class="d-flex flex-grow-1 flex-sm-grow-0">
                                    <div class="input-group input-group-sm">
                                        <select id="comparisonSelect" class="form-select form-select-sm shadow-sm" style="min-width: 70px;">
                                            <option value="1" <?= $comparisonPeriod == 1 ? 'selected' : '' ?>>H-1</option>
                                            <option value="7" <?= $comparisonPeriod == 7 ? 'selected' : '' ?>>H-7</option>
                                            <option value="30" <?= $comparisonPeriod == 30 ? 'selected' : '' ?>>H-30</option>
                                        </select>
                                        <input type="date" id="selectedDatePicker" class="form-control form-control-sm shadow-sm" value="<?= $referenceDate ?>">
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-light shadow-sm d-flex align-items-center" onclick="exportToExcel()" data-bs-toggle="tooltip" data-bs-placement="top" title="Unduh Excel">
                                        <i class="bi bi-file-earmark-excel text-success"></i>
                                        <span class="ms-1 d-none d-sm-inline">Excel</span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light shadow-sm d-flex align-items-center" onclick="exportToPDF()" data-bs-toggle="tooltip" data-bs-placement="top" title="Unduh PDF">
                                        <i class="bi bi-file-earmark-pdf text-danger"></i>
                                        <span class="ms-1 d-none d-sm-inline">PDF</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="commodityPriceTable">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th class="text-start ps-4">Komoditas</th>
                                    <th class="text-nowrap">Satuan</th>
                                    <th class="text-nowrap">Harga<br><small class="text-muted fw-normal" id="currentDateDisplay"><?= date('d/m/Y', strtotime($referenceDate)) ?></small></th>
                                    <th class="text-nowrap">Harga Sebelumnya<br><small class="text-muted fw-normal" id="comparisonDateDisplay">(H-<?= $comparisonPeriod ?>)</small></th>
                                    <th class="text-nowrap">Perubahan<br><small class="text-muted fw-normal">(Harga & Persentase)</small></th>
                                    <th class="text-nowrap">Grafik<br><small class="text-muted fw-normal">7 Hari Terakhir</small></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($commodityPriceComparison)): ?>
                                    <?php foreach ($commodityPriceComparison as $item): ?>
                                        <?php
                                        $change = $item['percentage_change'] !== null ? round($item['percentage_change'], 1) : null;
                                        $changeClass = $change > 0 ? 'text-danger' : ($change < 0 ? 'text-success' : 'text-muted');
                                        $changeIcon = $change > 0 ? 'bi-arrow-up' : ($change < 0 ? 'bi-arrow-down' : 'bi-dash');
                                        $priceDiff = $item['selected_date_price'] - $item['comparison_date_price'];
                                        ?>
                                        <tr class="hover-shadow-sm">
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <strong><?= htmlspecialchars($item['commodity_name']) ?></strong>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark"><?= htmlspecialchars($item['unit']) ?></span>
                                            </td>
                                            <td class="text-end fw-bold">
                                                <?= $item['selected_date_price'] ? 'Rp' . number_format($item['selected_date_price'], 0, ',', '.') : '<span class="text-muted">-</span>' ?>
                                            </td>
                                            <td class="text-end">
                                                <?= $item['comparison_date_price'] ? 'Rp' . number_format($item['comparison_date_price'], 0, ',', '.') : '<span class="text-muted">-</span>' ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($change !== null): ?>
                                                    <div class="d-flex flex-column">
                                                        <span class="<?= $changeClass ?> fw-bold">
                                                            <i class="bi <?= $changeIcon ?> me-1"></i><?= number_format(abs($change), 1) ?>%
                                                        </span>
                                                        <small class="text-muted">
                                                            <?= $priceDiff >= 0 ? '+' : '' ?><?= number_format($priceDiff, 0, ',', '.') ?>
                                                        </small>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center" style="width: 200px;">
                                                <?php if (!empty($item['chart_data_formatted'])): ?>
                                                    <div class="sparkline-container mx-auto" style="height: 50px;">
                                                        <canvas id="sparkline-<?= $item['id'] ?>" 
                                                                width="180" height="50" 
                                                                data-chart-data='<?= json_encode($item['chart_data_formatted']) ?>'>
                                                        </canvas>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">Data tidak tersedia</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <div class="py-4">
                                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                                <p class="mt-3 text-muted">Tidak ada data harga komoditas yang tersedia</p>
                                                <button class="btn btn-sm btn-outline-primary mt-2" onclick="location.reload()">
                                                    <i class="bi bi-arrow-clockwise me-1"></i>Muat Ulang
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php if (!empty($commodityPriceComparison)): ?>
                <div class="card-footer bg-light py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Menampilkan <?= count($commodityPriceComparison) ?> dari <?= $totalKomoditas ?> komoditas
                        </small>
                        <small class="text-muted">
                            Terakhir diperbarui: <?= date('d M Y H:i') ?> WIB
                        </small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<footer class="bg-dark text-light py-4">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h5><i class="bi bi-graph-up-arrow me-2"></i>SiagaBapok</h5>
                <p class="mb-0">Sistem Informasi Harga Bahan Pokok Kota Bandar Lampung</p>
            </div>
            <div class="col-md-6 text-md-end">
                <small>&copy; <?php echo date('Y'); ?> SiagaBapok. All rights reserved.</small><br>
                <small>Data terakhir diperbarui: <?php echo date('d M Y, H:i'); ?> WIB</small>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
// Enhanced Carousel Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize carousel if element exists
    const carousel = document.querySelector('.commodity-carousel');
    const carouselContainer = document.querySelector('.commodity-carousel-container');
    
    if (carousel && carouselContainer) {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const items = Array.from(carousel.children);
        
        if (items.length === 0) return;
        
        // Calculate item width including margin
        const itemStyle = window.getComputedStyle(items[0]);
        const itemWidth = items[0].offsetWidth + 
                         parseFloat(itemStyle.marginRight) + 
                         parseFloat(itemStyle.marginLeft);
        
        let currentPosition = 0;
        let isAnimating = false;
        let startX, scrollLeft, isDragging = false;
        let animationId;
        
        // Calculate how many items can fit in the viewport
        function calculateItemsPerView() {
            const containerWidth = carouselContainer.offsetWidth;
            return Math.min(Math.floor(containerWidth / itemWidth), 4);
        }
        
        let itemsPerView = calculateItemsPerView();
        let maxPosition = Math.max(0, items.length - itemsPerView);
        let startPos = 0;
        let currentTranslate = 0;
        let prevTranslate = 0;
        
        // Touch event handlers for mobile
        carousel.addEventListener('touchstart', touchStart);
        carousel.addEventListener('touchend', touchEnd);
        carousel.addEventListener('touchmove', touchMove);
        
        // Mouse event handlers for desktop
        carousel.addEventListener('mousedown', touchStart);
        carousel.addEventListener('mouseup', touchEnd);
        carousel.addEventListener('mouseleave', touchEnd);
        carousel.addEventListener('mousemove', touchMove);
        
        function touchStart(e) {
            if (e.type === 'touchstart') {
                startPos = e.touches[0].clientX;
            } else {
                startPos = e.clientX;
                e.preventDefault();
            }
            
            isDragging = true;
            carousel.style.cursor = 'grabbing';
            carousel.style.transition = 'none';
            
            // Clear any existing animation frame
            cancelAnimationFrame(animationId);
        }
        
        function touchMove(e) {
            if (!isDragging) return;
            
            const currentPosition = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
            const diff = currentPosition - startPos;
            
            // Prevent carousel from bouncing when dragging past boundaries
            if ((currentPosition < startPos && currentTranslate <= -maxPosition * itemWidth) || 
                (currentPosition > startPos && currentTranslate >= 0)) {
                return;
            }
            
            currentTranslate = prevTranslate + diff;
            carousel.style.transform = `translateX(${currentTranslate}px)`;
        }
        
        function touchEnd() {
            if (!isDragging) return;
            
            isDragging = false;
            carousel.style.cursor = 'grab';
            
            // Calculate the closest slide based on the current position
            const threshold = itemWidth / 4;
            const draggedSlides = Math.round(-currentTranslate / itemWidth);
            
            // Snap to the closest slide
            currentPosition = Math.min(Math.max(0, draggedSlides), maxPosition);
            
            // Update the carousel position with smooth animation
            updateCarousel();
        }
        
        // Update carousel position with smooth animation
        function updateCarousel(instant = false) {
            if (isAnimating && !instant) return;
            
            isAnimating = true;
            
            // Ensure position is within bounds
            currentPosition = Math.min(Math.max(0, currentPosition), maxPosition);
            
            // Calculate new position
            const newPosition = -currentPosition * itemWidth;
            
            if (instant) {
                carousel.style.transition = 'none';
                carousel.style.transform = `translateX(${newPosition}px)`;
                // Force reflow
                void carousel.offsetWidth;
                carousel.style.transition = 'transform 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
            } else {
                carousel.style.transition = 'transform 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                carousel.style.transform = `translateX(${newPosition}px)`;
            }
            
            // Update prevTranslate for touch events
            prevTranslate = newPosition;
            
            // Update button states
            updateButtonStates();
            
            // Reset animation flag after transition ends
            carousel.addEventListener('transitionend', function onEnd() {
                isAnimating = false;
                carousel.removeEventListener('transitionend', onEnd);
            }, { once: true });
        }
        
        // Update button states based on current position
        function updateButtonStates() {
            if (!prevBtn || !nextBtn) return;
            
            prevBtn.disabled = currentPosition === 0;
            nextBtn.disabled = currentPosition >= maxPosition;
            
            // Visual feedback
            prevBtn.style.opacity = currentPosition === 0 ? '0.5' : '1';
            nextBtn.style.opacity = currentPosition >= maxPosition ? '0.5' : '1';
            
            // Disable pointer events when button is disabled
            prevBtn.style.pointerEvents = currentPosition === 0 ? 'none' : 'auto';
            nextBtn.style.pointerEvents = currentPosition >= maxPosition ? 'none' : 'auto';
        }
        
        // Navigation functions
        function goToNext() {
            if (currentPosition < maxPosition) {
                currentPosition++;
                updateCarousel();
            }
        }
        
        function goToPrev() {
            if (currentPosition > 0) {
                currentPosition--;
                updateCarousel();
            }
        }
        
        // Event Listeners for navigation buttons
        if (prevBtn) prevBtn.addEventListener('click', goToPrev);
        if (nextBtn) nextBtn.addEventListener('click', goToNext);
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') return;
            
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                goToPrev();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                goToNext();
            }
        });
        
        // Touch and drag support
        carouselContainer.addEventListener('mousedown', startDrag);
        carouselContainer.addEventListener('touchstart', startDrag, { passive: false });
        
        function startDrag(e) {
            if (isAnimating) return;
            
            isDragging = true;
            startX = (e.pageX || e.touches[0].pageX) - carousel.offsetLeft;
            scrollLeft = -parseInt(carousel.style.transform.replace('translateX(', '').replace('px)', '')) || 0;
            
            carousel.style.cursor = 'grabbing';
            carousel.style.transition = 'none';
            
            document.addEventListener('mousemove', drag);
            document.addEventListener('touchmove', drag, { passive: false });
            document.addEventListener('mouseup', endDrag);
            document.addEventListener('touchend', endDrag);
            
            e.preventDefault();
        }
        
        function drag(e) {
            if (!isDragging) return;
            
            const x = (e.pageX || e.touches[0].pageX) - carousel.offsetLeft;
            const walk = (x - startX) * 1.5; // Adjust sensitivity
            
            // Calculate new position with boundaries
            const newScroll = Math.max(-maxPosition * itemWidth, Math.min(scrollLeft - walk, 0));
            
            carousel.style.transform = `translateX(${newScroll}px)`;
            
            // Prevent page scroll when dragging
            if (e.cancelable) {
                e.preventDefault();
            }
        }
        
        function endDrag(e) {
            if (!isDragging) return;
            
            isDragging = false;
            carousel.style.cursor = 'grab';
            
            // Calculate new position based on drag end
            const currentTransform = window.getComputedStyle(carousel).transform;
            const matrix = new DOMMatrixReadOnly(currentTransform);
            const currentScroll = -matrix.m41; // Get current translateX value
            
            // Snap to nearest item
            currentPosition = Math.round(currentScroll / itemWidth);
            
            // Ensure position is within bounds
            currentPosition = Math.max(0, Math.min(currentPosition, maxPosition));
            
            // Update carousel to snapped position
            updateCarousel();
            
            // Remove event listeners
            document.removeEventListener('mousemove', drag);
            document.removeEventListener('touchmove', drag);
            document.removeEventListener('mouseup', endDrag);
            document.removeEventListener('touchend', endDrag);
        }
        
        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                const newItemsPerView = calculateItemsPerView();
                if (newItemsPerView !== itemsPerView) {
                    itemsPerView = newItemsPerView;
                    maxPosition = Math.max(0, items.length - itemsPerView);
                    currentPosition = Math.min(currentPosition, maxPosition);
                    updateCarousel(true);
                }
            }, 100);
        });
        
        // Initialize carousel
        updateCarousel(true);
        
        // Add CSS for better touch feedback
        const style = document.createElement('style');
        style.textContent = `
            .commodity-carousel {
                cursor: grab;
                user-select: none;
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
            }
            
            .commodity-carousel:active {
                cursor: grabbing;
            }
            
            .commodity-carousel-container {
                overflow: hidden;
                position: relative;
                -webkit-overflow-scrolling: touch;
            }
            
            .commodity-carousel-container::-webkit-scrollbar {
                display: none;
            }
            
            #prevBtn, #nextBtn {
                transition: opacity 0.2s ease;
            }
            
            #prevBtn:disabled, #nextBtn:disabled {
                cursor: not-allowed;
                opacity: 0.5 !important;
            }
        `;
        document.head.appendChild(style);
    }

    // Initialize other components
    initializeCommoditySparklines();
    initTrendCardsScroll();
});

// Initialize sparkline charts for table
function initializeCommoditySparklines() {
    const sparklineCanvases = document.querySelectorAll('[id^="sparkline-"]');
    
    sparklineCanvases.forEach(canvas => {
        const commodityId = canvas.id.replace('sparkline-', '');
        const chartData = JSON.parse(canvas.dataset.chartData || '[]');
        
        if (chartData.length > 0) {
            const dates = chartData.map(item => item.date);
            const prices = chartData.map(item => parseFloat(item.price));
            
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        data: prices,
                        borderColor: '#0d6efd',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { x: { display: false }, y: { display: false } }
                }
            });
        } else {
            const td = canvas.closest('td');
            if (td) td.textContent = 'Tidak ada data';
        }
    });
}

// Initialize horizontal scroll for trend cards
function initTrendCardsScroll() {
    const container = document.querySelector('.trend-cards-container');
    if (!container) return;

    const scrollContent = container.firstElementChild;
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    const progressBar = document.querySelector('.progress-bar');
    
    if (!scrollContent || !prevBtn || !nextBtn || !progressBar) return;

    let scrollPosition = 0;
    const cardWidth = 280;
    const gap = 16;
    
    function updateNavButtons() {
        const maxScroll = scrollContent.scrollWidth - container.clientWidth;
        prevBtn.disabled = scrollPosition <= 0;
        nextBtn.disabled = scrollPosition >= maxScroll;
        const progress = (scrollPosition / (maxScroll || 1)) * 100;
        progressBar.style.width = `${progress}%`;
    }
    
    if (prevBtn && nextBtn) {
        prevBtn.addEventListener('click', () => {
            scrollPosition = Math.max(0, scrollPosition - (cardWidth + gap));
            container.scrollTo({ left: scrollPosition, behavior: 'smooth' });
        });
        
        nextBtn.addEventListener('click', () => {
            const maxScroll = scrollContent.scrollWidth - container.clientWidth;
            scrollPosition = Math.min(maxScroll, scrollPosition + (cardWidth + gap));
            container.scrollTo({ left: scrollPosition, behavior: 'smooth' });
        });
    }
    
    if (container) {
        container.addEventListener('scroll', () => {
            scrollPosition = container.scrollLeft;
            updateNavButtons();
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initial update
    updateNavButtons();
    
    // Handle resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(updateNavButtons, 250);
    });
}

// Handle popstate for back/forward navigation
window.addEventListener('popstate', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const date = urlParams.get('date');
    const comparison = urlParams.get('comparison');
    
    if (date && comparison) {
        const datePicker = document.getElementById('selectedDatePicker');
        const comparisonSelect = document.getElementById('comparisonSelect');
        if (datePicker && comparisonSelect) {
            datePicker.value = date;
            comparisonSelect.value = comparison;
            if (typeof updateCommodityTable === 'function') {
                updateCommodityTable();
            }
        }
    }
});
</script>
</body>
</html>
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        data: prices,
                        borderColor: '#0d6efd',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { x: { display: false }, y: { display: false } }
                }
            });
        } else {
            const td = canvas.closest('td');
            if (td) td.textContent = 'Tidak ada data';
        }
    });
}

// Initialize horizontal scroll for trend cards
function initTrendCardsScroll() {
    const container = document.querySelector('.trend-cards-container');
    if (!container) return;

    const scrollContent = container.firstElementChild;
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    const progressBar = document.querySelector('.progress-bar');
    
    if (!scrollContent || !prevBtn || !nextBtn || !progressBar) return;

    let scrollPosition = 0;
    const cardWidth = 280;
    const gap = 16;
    
    function updateNavButtons() {
        const maxScroll = scrollContent.scrollWidth - container.clientWidth;
        prevBtn.disabled = scrollPosition <= 0;
        nextBtn.disabled = scrollPosition >= maxScroll;
        const progress = (scrollPosition / (maxScroll || 1)) * 100;
        progressBar.style.width = `${progress}%`;
    }
    
    if (prevBtn && nextBtn) {
        prevBtn.addEventListener('click', () => {
            scrollPosition = Math.max(0, scrollPosition - (cardWidth + gap));
            container.scrollTo({ left: scrollPosition, behavior: 'smooth' });
        });
        
        nextBtn.addEventListener('click', () => {
            const maxScroll = scrollContent.scrollWidth - container.clientWidth;
            scrollPosition = Math.min(maxScroll, scrollPosition + (cardWidth + gap));
            container.scrollTo({ left: scrollPosition, behavior: 'smooth' });
        });
    }
    
    if (container) {
        container.addEventListener('scroll', () => {
            scrollPosition = container.scrollLeft;
            updateNavButtons();
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initial update
    updateNavButtons();
    
    // Handle resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(updateNavButtons, 250);
    });
}

// Handle popstate for back/forward navigation
window.addEventListener('popstate', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const date = urlParams.get('date');
    const comparison = urlParams.get('comparison');
    
    if (date && comparison) {
        const datePicker = document.getElementById('selectedDatePicker');
        const comparisonSelect = document.getElementById('comparisonSelect');
        if (datePicker && comparisonSelect) {
            datePicker.value = date;
            comparisonSelect.value = comparison;
            if (typeof updateCommodityTable === 'function') {
                updateCommodityTable();
            }
        }
    }
});
</script>
</body>
</html>
                cursor: grab;
                user-select: none;
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
            }
            
            .commodity-carousel:active {
                cursor: grabbing;
            }
            
            .commodity-carousel-container {
                overflow: hidden;
                position: relative;
                -webkit-overflow-scrolling: touch;
            }
            
            .commodity-carousel-container::-webkit-scrollbar {
                display: none;
            }
            
            #prevBtn, #nextBtn {
                transition: opacity 0.2s ease;
            }
            
            #prevBtn:disabled, #nextBtn:disabled {
                cursor: not-allowed;
                opacity: 0.5 !important;
            }
        `;
        document.head.appendChild(style);
    }

    // Initialize other components
    initializeCommoditySparklines();
    initTrendCardsScroll();
});

// Initialize sparkline charts for table
function initializeCommoditySparklines() {
    const sparklineCanvases = document.querySelectorAll('[id^="sparkline-"]');
    
    sparklineCanvases.forEach(canvas => {
        const commodityId = canvas.id.replace('sparkline-', '');
        const chartData = JSON.parse(canvas.dataset.chartData || '[]');
        
        if (chartData.length > 0) {
            const dates = chartData.map(item => item.date);
            const prices = chartData.map(item => parseFloat(item.price));
            
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        data: prices,
                        borderColor: '#0d6efd',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { x: { display: false }, y: { display: false } }
                }
            });
        } else {
            const td = canvas.closest('td');
            if (td) td.textContent = 'Tidak ada data';
        }
    });
}

// Initialize horizontal scroll for trend cards
function initTrendCardsScroll() {
    const container = document.querySelector('.trend-cards-container');
    if (!container) return;

    const scrollContent = container.firstElementChild;
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    const progressBar = document.querySelector('.progress-bar');
    
    if (!scrollContent || !prevBtn || !nextBtn || !progressBar) return;

    let scrollPosition = 0;
    const cardWidth = 280;
    const gap = 16;
    
    function updateNavButtons() {
        const maxScroll = scrollContent.scrollWidth - container.clientWidth;
        prevBtn.disabled = scrollPosition <= 0;
        nextBtn.disabled = scrollPosition >= maxScroll;
        const progress = (scrollPosition / (maxScroll || 1)) * 100;
        progressBar.style.width = `${progress}%`;
    }
    
    if (prevBtn && nextBtn) {
        prevBtn.addEventListener('click', () => {
            scrollPosition = Math.max(0, scrollPosition - (cardWidth + gap));
            container.scrollTo({ left: scrollPosition, behavior: 'smooth' });
        });
        
        nextBtn.addEventListener('click', () => {
            const maxScroll = scrollContent.scrollWidth - container.clientWidth;
            scrollPosition = Math.min(maxScroll, scrollPosition + (cardWidth + gap));
            container.scrollTo({ left: scrollPosition, behavior: 'smooth' });
        });
    }
    
    if (container) {
        container.addEventListener('scroll', () => {
            scrollPosition = container.scrollLeft;
            updateNavButtons();
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initial update
    updateNavButtons();
    
    // Handle resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(updateNavButtons, 250);
    });
}

// Handle popstate for back/forward navigation
window.addEventListener('popstate', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const date = urlParams.get('date');
    const comparison = urlParams.get('comparison');
    
    if (date && comparison) {
        const datePicker = document.getElementById('selectedDatePicker');
        const comparisonSelect = document.getElementById('comparisonSelect');
        if (datePicker && comparisonSelect) {
            datePicker.value = date;
            comparisonSelect.value = comparison;
            if (typeof updateCommodityTable === 'function') {
                updateCommodityTable();
            }
        }
    }
});
</script>
</body>
</html>
            
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        data: prices,
                        borderColor: '#0d6efd',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { x: { display: false }, y: { display: false } }
                }
            });
        } else {
            const td = canvas.closest('td');
            if (td) td.textContent = 'Tidak ada data';
        }
    });
}

// Initialize horizontal scroll for trend cards
function initTrendCardsScroll() {
    const container = document.querySelector('.trend-cards-container');
    if (!container) return;

    const scrollContent = container.firstElementChild;
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    const progressBar = document.querySelector('.progress-bar');
    
    if (!scrollContent || !prevBtn || !nextBtn || !progressBar) return;

    let scrollPosition = 0;
    const cardWidth = 280;
    const gap = 16;
    
    function updateNavButtons() {
        const maxScroll = scrollContent.scrollWidth - container.clientWidth;
        prevBtn.disabled = scrollPosition <= 0;
        nextBtn.disabled = scrollPosition >= maxScroll;
        const progress = (scrollPosition / (maxScroll || 1)) * 100;
        progressBar.style.width = `${progress}%`;
    }
    
    if (prevBtn && nextBtn) {
        prevBtn.addEventListener('click', () => {
            scrollPosition = Math.max(0, scrollPosition - (cardWidth + gap));
            container.scrollTo({ left: scrollPosition, behavior: 'smooth' });
        });
        
        nextBtn.addEventListener('click', () => {
            const maxScroll = scrollContent.scrollWidth - container.clientWidth;
            scrollPosition = Math.min(maxScroll, scrollPosition + (cardWidth + gap));
            container.scrollTo({ left: scrollPosition, behavior: 'smooth' });
        });
    }
    
    if (container) {
        container.addEventListener('scroll', () => {
            scrollPosition = container.scrollLeft;
            updateNavButtons();
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initial update
    updateNavButtons();
    
    // Handle resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(updateNavButtons, 250);
    });
}

// Handle popstate for back/forward navigation
window.addEventListener('popstate', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const date = urlParams.get('date');
    const comparison = urlParams.get('comparison');
    
    if (date && comparison) {
        const datePicker = document.getElementById('selectedDatePicker');
        const comparisonSelect = document.getElementById('comparisonSelect');
        if (datePicker && comparisonSelect) {
            datePicker.value = date;
            comparisonSelect.value = comparison;
            if (typeof updateCommodityTable === 'function') {
                updateCommodityTable();
            }
        }
    }
});
</script>
</body>
</html>