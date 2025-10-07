<?php
session_start();
if(session_status() === PHP_SESSION_NONE) {
    session_start();
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
$db = Database::getInstance();
$auth = new AuthController();
$priceModel = new Price();
$settingsModel = new Settings();
$sliderModel = new Slider();

$currentUser = $auth->getCurrentUser();
$role = $currentUser ? $currentUser['role'] : 'masyarakat';

// Dapatkan pengaturan website
$settings = $settingsModel->getSettingsMap();

// Dapatkan data untuk chart dan tabel
$referenceDate = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : date('Y-m-d');
$comparisonPeriod = isset($_GET['comparison']) ? (int)$_GET['comparison'] : 7;
$comparisonPeriod = in_array($comparisonPeriod, [1, 7, 30]) ? $comparisonPeriod : 7;

$uptdFilter = ($currentUser && $currentUser['role'] === 'uptd') ? $currentUser['id'] : null;

$commodityPriceComparison = $priceModel->getCommodityPriceComparison($referenceDate, $comparisonPeriod, $uptdFilter);
$topIncreasing = $priceModel->getTopIncreasingPrices(7, 5);
$topDecreasing = $priceModel->getTopDecreasingPrices(7, 5);
$stablePrices = $priceModel->getStablePrices(7, 5);
$stats = $priceModel->getStatistics();

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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/<?= htmlspecialchars($settings['logo'] ?? 'BANDAR LAMPUNG ICON.png') ?>">
    
    <style>
        html { scroll-behavior: smooth; }
        
        :root {
            --primary-blue: #000080;
            --dark-blue: #3232b9ff;
            --success: #198754;
            --danger: #dc3545;
        }

        body { background-color: #f8f9fa; }

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

        .trend-cards-container::{
            display: flex;
            overflow-x: auto;
            overflow-y: hidden;
            gap: 1rem;
            padding: 1rem 0;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: rgba(0, 0, 0, 0.2) transparent;
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

        .prev-btn, .next-btn {
            border-radius: 50%;
            width: 36px;
            height: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
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

    <div class="container-fluid px-3 px-md-4 px-lg-5 my-4 my-lg-5">
        <!-- Tren Harga Komoditas Section -->
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

                <!-- Scroll Indicator -->
                <div class="scroll-indicator mb-2 d-none d-md-block">
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
                
                <!-- Horizontal Scrollable Cards -->
                <div class="trend-cards-container mb-3" 
                    style="overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; padding-bottom: 10px;"
                    id="trendCardsContainer">
                    <div class="d-flex flex-nowrap gap-3" style="min-width: max-content; padding-bottom: 5px;">
                    
                    <!-- Meningkat -->
                    <?php if (!empty($topIncreasing)): ?>
                        <?php foreach ($topIncreasing as $item): ?>
                            <?php 
                            $percentage = (($item['current_avg'] - $item['previous_avg']) / $item['previous_avg']) * 100;
                            $priceDiff = $item['current_avg'] - $item['previous_avg'];
                            $formattedPrice = 'Rp' . number_format($item['current_avg'], 0, ',', '.');
                            $formattedDiff = ($priceDiff >= 0 ? '+' : '') . number_format($priceDiff, 0, ',', '.');
                            ?>
                            <div class="trend-card" style="width: 280px; flex: 0 0 auto;">
                            <div class="card h-100 border-0 shadow-sm h-100 hover-shadow transition-all">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="flex-shrink-0">
                                            <?php if (!empty($item['image'])): ?>
                                                <img src="<?= htmlspecialchars($item['image']) ?>" 
                                                     alt="<?= htmlspecialchars($item['commodity_name']) ?>" 
                                                     class="rounded" 
                                                     style="width: 60px; height: 60px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                     style="width: 60px; height: 60px;">
                                                    <i class="bi bi-box-seam text-muted" style="font-size: 1.5rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1 fw-bold text-truncate" 
                                                style="max-width: 150px;" 
                                                title="<?= htmlspecialchars($item['commodity_name']) ?>"
                                                data-bs-toggle="tooltip" 
                                                data-bs-placement="top">
                                                <?= htmlspecialchars($item['commodity_name']) ?>
                                            </h6>
                                            <span class="badge bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center">
                                                <i class="bi bi-arrow-up me-1"></i> 
                                                Naik <?= number_format($percentage, 1) ?>%
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <span class="text-muted small">Harga Sekarang</span>
                                            <div class="fw-bold"><?= $formattedPrice ?></div>
                                        </div>
                                        <div class="text-end">
                                            <span class="text-muted small">Perubahan</span>
                                            <div class="fw-bold text-danger">
                                                <?= $formattedDiff ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="text-muted small"><?= $item['unit'] ?></span>
                                        <a href="detail-komoditas.php?id=<?= $item['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-arrow-right me-1"></i> Detail
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Menurun -->
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

                    <!-- Stabil -->
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
    </div>

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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('trendCardsContainer');
    if (!container) return;

    const scrollContent = container.firstElementChild;
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    const progressBar = document.querySelector('.progress-bar');
    
    if (!scrollContent || !prevBtn || !nextBtn || !progressBar) {
        console.warn('Salah satu elemen tidak ditemukan');
        return;
    }

    let scrollPosition = 0;
    const cardWidth = 280; // Sesuaikan dengan lebar card + gap
    const gap = 16; // Sesuaikan dengan gap yang digunakan
    
    // Inisialisasi tooltip
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Fungsi untuk update tombol navigasi
    function updateNavButtons() {
        prevBtn.disabled = scrollPosition <= 0;
        nextBtn.disabled = scrollPosition >= (scrollContent.scrollWidth - container.clientWidth);
        
        // Update progress bar
        const maxScroll = scrollContent.scrollWidth - container.clientWidth;
        const progress = (scrollPosition / maxScroll) * 100;
        progressBar.style.width = `${progress}%`;
    }
    
    // Event listener untuk tombol navigasi
    prevBtn.addEventListener('click', () => {
        scrollPosition = Math.max(0, scrollPosition - (cardWidth + gap));
        container.scrollTo({
            left: scrollPosition,
            behavior: 'smooth'
        });
    });
    
    nextBtn.addEventListener('click', () => {
        const maxScroll = scrollContent.scrollWidth - container.clientWidth;
        scrollPosition = Math.min(maxScroll, scrollPosition + (cardWidth + gap));
        container.scrollTo({
            left: scrollPosition,
            behavior: 'smooth'
        });
    });
    
    // Update tombol saat scroll
    container.addEventListener('scroll', () => {
        scrollPosition = container.scrollLeft;
        updateNavButtons();
    });
    
    // Inisialisasi awal
    updateNavButtons();
    
    // Handle resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            updateNavButtons();
        }, 250);
    });
    
    // Tambahkan class saat hover card
    document.querySelectorAll('.trend-card .card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('shadow');
        });
        card.addEventListener('mouseleave', function() {
            this.classList.remove('shadow');
        });
    });
});
// Pass PHP data to JavaScript
const commodities = <?= json_encode($commodities) ?>;
const commodityPriceComparison = <?= json_encode($commodityPriceComparison) ?>;

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
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false }
                    }
                }
            });
        } else {
            canvas.closest('td').textContent = 'Tidak ada data';
        }
    });
}

// Initialize horizontal scroll for trend cards
function initTrendCardsScroll() {
    const container = document.querySelector('.trend-cards-container');
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    
    if (!container || !prevBtn || !nextBtn) return;
    
    const scrollAmount = 300;
    
    prevBtn.addEventListener('click', () => {
        container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    });
    
    nextBtn.addEventListener('click', () => {
        container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    });
    
    // Update nav buttons visibility
    const updateNavButtons = () => {
        prevBtn.style.visibility = container.scrollLeft > 0 ? 'visible' : 'hidden';
        nextBtn.style.visibility = 
            container.scrollLeft < (container.scrollWidth - container.clientWidth - 10) ? 'visible' : 'hidden';
    };
    
    container.addEventListener('scroll', updateNavButtons);
    window.addEventListener('resize', updateNavButtons);
    updateNavButtons();
}

// Update table with AJAX
function updateCommodityTable(data) {
    const tbody = document.querySelector('#commodityPriceTable tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <p class="mt-3">Tidak ada data harga yang tersedia</p>
                </td>
            </tr>
        `;
        return;
    }

    data.forEach((item, index) => {
        const change = item.percentage_change !== null ? parseFloat(item.percentage_change) : null;
        const changeClass = change > 0 ? 'text-danger' : (change < 0 ? 'text-success' : 'text-muted');
        const changeIcon = change > 0 ? 'bi-arrow-up' : (change < 0 ? 'bi-arrow-down' : 'bi-dash');
        const priceDiff = item.selected_date_price - item.comparison_date_price;
        
        const row = document.createElement('tr');
        row.className = 'text-center';
        row.innerHTML = `
            <td class="text-start ps-4">
                <div class="d-flex align-items-center">
                    ${item.image ? `<img src="${item.image}" alt="${item.commodity_name}" class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">` : ''}
                    <span>${item.commodity_name}</span>
                </div>
            </td>
            <td>${item.unit || '-'}</td>
            <td class="fw-bold">${formatCurrency(item.selected_date_price)}</td>
            <td>${item.comparison_date_price ? formatCurrency(item.comparison_date_price) : '-'}</td>
            <td class="${changeClass}">
                ${change !== null ? `
                    <div class="d-flex align-items-center justify-content-center">
                        <i class="bi ${changeIcon} me-1"></i>
                        ${Math.abs(change).toFixed(2)}%
                        <small class="text-muted ms-2">(${formatCurrency(Math.abs(priceDiff))})</small>
                    </div>
                ` : '-'}
            </td>
            <td>
                <canvas id="sparkline-${item.id}" 
                        data-chart-data='${JSON.stringify(item.chart_data_formatted || [])}'
                        width="100" height="30"></canvas>
            </td>
        `;
        tbody.appendChild(row);
    });

    // Inisialisasi ulang sparkline setelah update tabel
    setTimeout(initializeCommoditySparklines, 100);
}

// Fungsi untuk memformat mata uang
function formatCurrency(amount) {
    if (amount === null || amount === undefined) return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

// Fungsi untuk memuat data komoditas
function loadCommodityData() {
    const selectedDate = document.getElementById('selectedDatePicker').value;
    const comparisonPeriod = document.getElementById('comparisonSelect').value;
    
    // Tampilkan loading state
    const tbody = document.querySelector('#commodityPriceTable tbody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Memuat...</span>
                    </div>
                    <p class="mt-2">Memuat data...</p>
                </td>
            </tr>
        `;
    }

    // Kirim permintaan AJAX
    fetch(`?api=commodity-prices&date=${selectedDate}&comparison=${comparisonPeriod}`)
        .then(async response => {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Expected JSON, got:', text);
                throw new Error('Respon tidak valid dari server');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateCommodityTable(data.data);
            } else {
                throw new Error(data.message || 'Terjadi kesalahan saat memuat data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Tampilkan pesan error ke pengguna
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-danger py-4">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <p class="mt-2">${error.message || 'Gagal memuat data. Silakan coba lagi.'}</p>
                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadCommodityData()">
                                <i class="bi bi-arrow-clockwise"></i> Coba Lagi
                            </button>
                        </td>
                    </tr>
                `;
            }
        });
    }

// Fungsi untuk mengekspor ke Excel
function exportToExcel() {
    try {
        // Dapatkan parameter filter
        const selectedDate = document.getElementById('selectedDatePicker').value;
        const comparisonPeriod = document.getElementById('comparisonSelect').value;
        
        // Redirect ke endpoint ekspor
        window.location.href = `/export/commodity-prices?date=${selectedDate}&comparison=${comparisonPeriod}&format=excel`;
    } catch (error) {
        console.error('Export error:', error);
        alert('Gagal mengekspor data. Silakan coba lagi.');
    }
}

// Fungsi untuk mengekspor ke PDF
function exportToPDF() {
    try {
        // Dapatkan parameter filter
        const selectedDate = document.getElementById('selectedDatePicker').value;
        const comparisonPeriod = document.getElementById('comparisonSelect').value;
        
        // Redirect ke endpoint ekspor PDF
        window.location.href = `/export/commodity-prices?date=${selectedDate}&comparison=${comparisonPeriod}&format=pdf`;
    } catch (error) {
        console.error('PDF export error:', error);
        alert('Gagal membuat PDF. Silakan coba lagi.');
    }
}

// Initialize everything
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sparklines
    initializeCommoditySparklines();
    
    // Initialize trend cards
    initTrendCardsScroll();
    
    // Animate trend cards on view
    const trendCards = document.querySelectorAll('.trend-card');
    trendCards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('visible');
        }, index * 100);
    });
    const datePicker = document.getElementById('selectedDatePicker');
    if (datePicker) {
        datePicker.max = new Date().toISOString().split('T')[0];
        datePicker.addEventListener('change', updateCommodityTable);
    }
    
    // Tambahkan di dalam event listener DOMContentLoaded
    document.getElementById('selectedDatePicker').addEventListener('change', function() {
        const date = new Date(this.value);
        document.getElementById('currentDateDisplay').textContent = date.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    });

    document.getElementById('comparisonSelect').addEventListener('change', function() {
        document.getElementById('comparisonDateDisplay').textContent = `(H-${this.value})`;
    });

    // Event listeners
    const comparisonSelect = document.getElementById('comparisonSelect');
    
    if (comparisonSelect) {
        comparisonSelect.addEventListener('change', loadCommodityData);
    }
    
    // Inisialisasi tooltip
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    //muat data awal
    loadCommodityData();
});

// Handle browser back/forward
window.addEventListener('popstate', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const date = urlParams.get('date');
    const comparison = urlParams.get('comparison');
    
    if (date && comparison) {
        document.getElementById('selectedDatePicker').value = date;
        document.getElementById('comparisonSelect').value = comparison;
        updateCommodityTable();
    }
});
</script>
</body>
</html>