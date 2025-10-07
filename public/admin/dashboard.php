<?php
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Price.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/Database.php';

function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

//cek autentikasi dan peran pengguna
$auth = new AuthController();
$user = $auth->requireRole('admin');
$priceModel = new Price();
$userModel = new User();
$db = Database::getInstance();

// Get latest prices
$latestPrices = $priceModel->getLatestPricesByCommodity(10); // 10 is the limit;

// Get top increasing prices (7 days)
$topIncreasing = $priceModel->getTopIncreasingPrices(7, 5);

// Get statistics
$stats = $priceModel->getStatistics();

// Database connection
$totalPasar = $db->fetchOne("SELECT COUNT(*) as count FROM pasar")['count'];
$totalKomoditas = $db->fetchOne("SELECT COUNT(*) as count FROM commodities")['count'];
$totalHarga = $db->fetchOne("SELECT COUNT(*) as count FROM prices")['count'];

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_image' && isset($_FILES['slider_image'])) {
        $uploadDir = __DIR__ . '/../../assets/img/slider/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $file = $_FILES['slider_image'];
        $fileName = time() . '_' . basename($file['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $_SESSION['success'] = 'Gambar berhasil diupload';
        } else {
            $_SESSION['error'] = 'Gagal mengupload gambar';
        }
    }
    
    if ($_POST['action'] === 'delete_image' && isset($_POST['image_name'])) {
        $imagePath = __DIR__ . '/../../assets/img/slider/' . $_POST['image_name'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
            $_SESSION['success'] = 'Gambar berhasil dihapus';
        }
    }
    
    header('Location: dashboard.php');
    exit;
}

// Get monthly price trends (last 6 months)
$monthlyTrends = $priceModel->getMonthlyTrends(6);

// Get most significant price changes
$significantChanges = $priceModel->getSignificantPriceChanges(30);

// Get statistics
$stats = $priceModel->getStatistics();
$userStats = $userModel->getUserStats();

// Default AI insight period
$aiInsightPeriod = isset($_GET['ai_period']) ? sanitizeInput($_GET['ai_period']) : 'weekly';

// Get pending approvals count
$pendingCount = $stats['pending_count'];

// Get slider images
$sliderDir = __DIR__ . '/../../public/assets/img/slider/';
$sliderImages = [];
if (is_dir($sliderDir)) {
    $sliderImages = array_diff(scandir($sliderDir), array('.', '..'));
}

$pageTitle = 'Dashboard Admin - Siaga Bapok';

// Ambil semua daftar komoditas dari database untuk dropdown filter di grafik
$all_commodities = $db->fetchAll("SELECT id, name, unit FROM commodities ORDER BY name ASC");

// Ambil data chart untuk inisialisasi
$trends1Day = $priceModel->getPriceTrends(1);
$trends7Days = $priceModel->getPriceTrends(7);
$trends30Days = $priceModel->getPriceTrends(30);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../public/assets/images/BANDAR LAMPUNG ICON.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/admin-sidebar.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        :root {
            --primary-blue: #000080;
            --dark-blue: #3232b9ff;
            --sidebar-width: 250px;
            --navbar-height: 56px;
        }
        
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            padding-top: var(--navbar-height);
            transition: margin 0.3s ease-in-out;
        }

        /*NAVBAR*/
        .navbar {
            height: var(--navbar-height);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1030;
            padding: 0.5rem 1rem;
        }
        
        /*SIDEBAR*/
        .sidebar {
            width: var(--sidebar-width);
            height: calc(100vh - var(--navbar-height));
            position: fixed;
            left: 0;
            top: var(--navbar-height);
            z-index: 1020;
            transition: transform 0.3s ease-in-out;
            overflow-y: auto;
            background: linear-gradient(180deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin: 0.25rem 0.5rem;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        /*MAIN CONTENT*/
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: margin 0.3s ease-in-out;
        }

        /*backdrop for mobile*/
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: var(--navbar-height);
            left: 0;
            width: 100%;
            height: calc(100% - var(--navbar-height));
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1010;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        
        .sidebar-backdrop.show {
            display: block;
            opacity: 1;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }

        #noDataMessage {
            display: none;
            padding: 2rem;
            text-align: center;
            color: #6c757d;
            background-color: #f8f9fa;
            border-radius: 0.25rem;
            margin-top: 1rem;
        }

        #noDataMessage i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        #chartSection {
            position: relative;
            height: 400px; /* tinggi tetap biar chart gak menjulang */
            width: 100%;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        }
        
        .slider-image {
            width: 100px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.375rem;
        }
        
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 0.375rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            border-color: var(--primary-green);
            background-color: var(--light-green);
        }
        
        .upload-area.dragover {
            border-color: var(--primary-green);
            background-color: var(--light-green);
        }

        /* Styles for the new commodity legends */
        .commodity-legends {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .commodity-legend-item {
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 20px;
            border: 1px solid #ccc;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .commodity-legend-item.active {
            background-color: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        /* Desktop Styles (lg and up) */
        @media (min-width: 992px) {
            .sidebar {
                transform: translateX(0) !important;
            }
            .main-content {
                margin-left: var(--sidebar-width);
            }
            .sidebar-backdrop {
                display: none !important;
            }
        }

        /* Mobile Styles (below lg) */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            body.sidebar-open {
                overflow: hidden;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" id="sidebarToggle" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list" style="font-size: 1.5rem;"></i>
            </button>
            <a class="navbar-brand ms-2" href="#">Dashboard Admin</a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-white me-3 d-none d-sm-inline">
                    <i class="bi bi-person-circle me-1"></i>
                    <?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'Admin'); ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <?php include __DIR__ . '/includes/sidebar_admin.php'; ?>
    
    <main class="main-content">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1 class="h3 mb-0">
                                    <i class="bi bi-speedometer2 me-2"></i>
                                    Dashboard Admin
                                </h1>
                                <p class="mb-0 mt-2 opacity-75">
                                    Sistem Informasi Harga Bahan Pokok - Panel Administrasi
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <div class="mt-3 mt-md-0">
                                    <small>Terakhir login:</small><br>
                                    <strong><?php echo date('d M Y, H:i'); ?> WIB</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stats-card text-center h-100">
                <div class="card-body">
                    <i class="bi bi-check-circle text-success fs-1 mb-2"></i>
                    <h3 class="text-success"><?php echo $stats['approved_count']; ?></h3>
                    <p class="mb-0">Data Disetujui</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stats-card text-center h-100">
                <div class="card-body">
                    <i class="bi bi-clock-history text-warning fs-1 mb-2"></i>
                    <h3 class="text-warning"><?php echo $stats['pending_count']; ?></h3>
                    <p class="mb-0">Menunggu Approval</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stats-card text-center h-100">
                <div class="card-body">
                    <i class="bi bi-box text-success fs-1 mb-2"></i>
                    <h3 class="text-success"><?= $totalKomoditas ?></h3>
                    <p class="mb-0">Total Komoditas</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stats-card text-center h-100">
                <div class="card-body">
                    <i class="bi bi-shop text-primary fs-1 mb-2"></i>
                    <h3 class="text-primary"><?= $totalPasar ?></h3>
                    <p class="mb-0">Total Pasar</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafik Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Grafik Pergerakan Harga Komoditas</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label for="periode" class="form-label">Periode</label>
                            <select id="periode" class="form-select">
                                <option value="1">1 Hari Terakhir</option>
                                <option value="7" selected>7 Hari Terakhir</option>
                                <option value="30">30 Hari Terakhir</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="commodity_id" class="form-label">Komoditas</label>
                            <select name="commodity_id" id="commodity_id" class="form-select" required>
                                <option value="all" selected>Semua Komoditas</option>
                                <?php 
                                $commodities = $db->fetchAll("SELECT id, name, unit FROM commodities ORDER BY name");
                                foreach ($commodities as $c): 
                                ?>
                                    <option value="<?= $c['id'] ?>">
                                        <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['unit']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button id="btnTampil" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-funnel me-1"></i> Tampilkan
                            </button>
                            <button id="btnPrintChart" class="btn btn-outline-secondary" title="Cetak Grafik">
                                <i class="bi bi-printer"></i>
                            </button>
                        </div>
                    </div>

                    <div class="chart-container" style="position: relative; height: 400px; width: 100%;">
                        <canvas id="chartHarga" style="width: 100%; height: 100%;"></canvas>
                    </div>
                    <div id="noDataMessage" class="text-center py-5">
                        <i class="bi bi-graph-up" style="font-size: 3rem; color: #6c757d;"></i>
                        <p class="mt-3">Tidak ada data yang tersedia untuk periode yang dipilih</p>
                    </div>
                    
                    <small class="text-muted d-block mt-2">
                        <i class="bi bi-info-circle me-1"></i> Grafik menampilkan rata-rata harga per hari
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Aksi Cepat Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning me-2 text-warning"></i>
                        Aksi Cepat
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="approve.php" class="btn btn-warning w-100 text-start">
                                <i class="bi bi-check-circle me-2"></i>
                                Approve Data
                                <?php if ($pendingCount > 0): ?>
                                    <span class="badge bg-dark float-end mt-1"><?= $pendingCount ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="manageUser.php" class="btn btn-primary w-100 text-start">
                                <i class="bi bi-people me-2"></i>
                                Kelola User
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="markets.php" class="btn btn-success w-100 text-start">
                                <i class="bi bi-shop me-2"></i>
                                Kelola Pasar
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="commodities.php" class="btn btn-info w-100 text-start">
                                <i class="bi bi-basket me-2"></i>
                                Kelola Komoditas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chart Controls
    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Grafik Harga Komoditas</h5>
                <div class="d-flex gap-2">
                    <select id="periodSelect" class="form-select form-select-sm" style="width: auto;">
                        <option value="1">1 Hari Terakhir</option>
                        <option value="7" selected>7 Hari Terakhir</option>
                        <option value="30">30 Hari Terakhir</option>
                    </select>
                    <button id="scaleToggle" class="btn btn-sm btn-outline-secondary">
                        Skala Logaritmik
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <select id="commoditySelect" class="form-select" multiple="multiple">
                        <!-- Options will be populated by JavaScript 
                    </select>
                </div>
                <div id="chartContainer">
                    <canvas id="chartHarga" height="400"></canvas>
                </div>
            </div>
        </div>
    </div> -->
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let myChart;
    
    const formatCurrency = (value) => {
        return 'Rp ' + value.toLocaleString('id-ID');
    };

    async function fetchChartData(period, commodityId) {
        try {
            const url = `get_chart_data.php?period=${period}&id=${commodityId}`;
            console.log('Fetching from:', url);
            
            const response = await fetch(url);
            const text = await response.text();
            console.log('Raw response:', text);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Handle empty or invalid JSON responses
            if (!text || text.trim() === '') {
                console.log('Empty response, returning empty array');
                return [];
            }
            
            try {
                // Handle case where response is just '[]' or '[][]'
                const trimmedText = text.trim();
                if (trimmedText === '[]' || trimmedText === '[][]') {
                    console.log('Empty array response');
                    return [];
                }
                
                const data = JSON.parse(trimmedText);
                console.log('Parsed data:', data);
                return data;
            } catch (jsonError) {
                console.warn('Invalid JSON response, treating as no data');
                console.warn('Response text:', text);
                return [];
            }
        } catch (error) {
            console.error('Error fetching chart data:', error);
            throw error; // Re-throw to be handled by the caller
        }
    }

    function renderChart(data) {
        console.log('Data received in renderChart:', data);
        
        const ctx = document.getElementById('chartHarga');
        if (!ctx) {
            console.error('Canvas element not found');
            return;
        }
        
        const canvas = ctx.getContext('2d');
        const noDataMessage = document.getElementById('noDataMessage');
        
        // Hapus chart lama jika ada
        if (window.myChart) {
            window.myChart.destroy();
        }
        
        // Clear canvas
        canvas.clearRect(0, 0, ctx.width, ctx.height);
        
        // Sembunyikan pesan no data
        if (noDataMessage) {
            noDataMessage.classList.add('d-none');
        }
        
        // Tampilkan canvas
        ctx.style.display = 'block';
        
        // Check if there's data to display
        if (!data || !data.labels || data.labels.length === 0 || !data.datasets || data.datasets.length === 0 || 
            !data.datasets.some(ds => ds.data && ds.data.some(val => val !== null && val !== undefined))) {
            
            // Draw "No Data" message on canvas
            canvas.font = '16px Arial';
            canvas.textAlign = 'center';
            canvas.textBaseline = 'middle';
            canvas.fillStyle = '#666';
            canvas.fillText('Tidak ada data yang tersedia', ctx.width / 2, ctx.height / 2);
            return;
        }
        
        // Format data untuk Chart.js
        const chartData = {
            labels: data.labels,
            datasets: data.datasets.map(dataset => ({
                label: dataset.label,
                data: dataset.data,
                borderColor: dataset.borderColor || '#3498db',
                backgroundColor: dataset.backgroundColor || 'rgba(52, 152, 219, 0.1)',
                borderWidth: 2,
                tension: 0.2,
                fill: true,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: dataset.borderColor || '#3498db',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }))
        };
        
        // Buat chart baru
        window.myChart = new Chart(canvas, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: Rp ${context.parsed.y.toLocaleString('id-ID')}`;
                            }
                        }
                    },
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    }

    function processChartData(data) {
        console.log('Processing chart data:', data);
        
        // If data is already in the correct format (has labels and datasets)
        if (data.labels && data.datasets) {
            return data;
        }
        
        // If data is an array (single commodity data)
        if (Array.isArray(data) && data.length > 0) {
            // Extract unique dates and sort them
            const dates = [...new Set(data.map(item => item.price_date))].sort();
            
            // Group data by commodity
            const datasets = [];
            const commodityMap = {};
            
            data.forEach(item => {
                if (!commodityMap[item.commodity_name]) {
                    commodityMap[item.commodity_name] = {
                        label: `${item.commodity_name} (${item.unit || ''})`,
                        data: new Array(dates.length).fill(null),
                        borderColor: `#${Math.floor(Math.random()*16777215).toString(16)}`,
                        backgroundColor: 'rgba(0, 0, 0, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    };
                }
                
                const dateIndex = dates.indexOf(item.price_date);
                if (dateIndex !== -1) {
                    commodityMap[item.commodity_name].data[dateIndex] = parseFloat(item.avg_price);
                }
            });
            
            return {
                labels: dates,
                datasets: Object.values(commodityMap)
            };
        }
        
        // Return empty data if format is not recognized
        return { labels: [], datasets: [] };
    }

    function showNoDataMessage(message = 'Tidak ada data yang tersedia untuk kriteria yang dipilih.') {
        const noDataMessage = document.getElementById('noDataMessage');
        const canvas = document.getElementById('chartHarga');
        
        if (noDataMessage) {
            noDataMessage.classList.add('d-none');
        }
        
        if (canvas) {
            canvas.style.display = 'block';
            const ctx = canvas.getContext('2d');
            
            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Draw message on canvas
            ctx.font = '16px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#666';
            
            // Split message into lines if needed
            const maxWidth = canvas.width * 0.8; // 80% of canvas width
            const lineHeight = 24;
            const lines = [];
            let currentLine = '';
            const words = message.split(' ');
            
            words.forEach(word => {
                const testLine = currentLine + word + ' ';
                const metrics = ctx.measureText(testLine);
                if (metrics.width > maxWidth && currentLine !== '') {
                    lines.push(currentLine);
                    currentLine = word + ' ';
                } else {
                    currentLine = testLine;
                }
            });
            if (currentLine) lines.push(currentLine);
            
            // Draw each line
            const startY = (canvas.height - (lines.length * lineHeight)) / 2;
            lines.forEach((line, i) => {
                ctx.fillText(line.trim(), canvas.width / 2, startY + (i * lineHeight));
            });
        }
        
        if (window.myChart) {
            window.myChart.destroy();
            window.myChart = null;
        }
    }

    async function updateChart() {
        const period = document.getElementById('periode')?.value || 7;
        const commoditySelect = document.getElementById('commodity_id');
        const commodityName = commoditySelect?.options[commoditySelect?.selectedIndex]?.text || 'komoditas yang dipilih';
        const commodityId = commoditySelect?.value || 'all';
        
        console.log('Updating chart with:', { period, commodityId });
        
        const canvas = document.getElementById('chartHarga');
        const noDataMessage = document.getElementById('noDataMessage');
        
        if (canvas) canvas.style.display = 'none';
        if (noDataMessage) {
            noDataMessage.innerHTML = `
                <div class="d-flex flex-column align-items-center justify-content-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Memuat...</span>
                    </div>
                    <p class="mt-2 mb-0">Sedang memuat data grafik...</p>
                    <small class="text-muted">${commodityId === 'all' ? 'Semua komoditas' : commodityName}</small>
                </div>
            `;
            noDataMessage.classList.remove('d-none');
        }
        
        try {
            const rawData = await fetchChartData(period, commodityId);
            console.log('Raw data from server:', rawData);
            
            if (!rawData || (Array.isArray(rawData) && rawData.length === 0)) {
                showNoDataMessage(`Tidak ada data harga untuk ${commodityId === 'all' ? 'semua komoditas' : commodityName} dalam periode ini.`);
                return;
            }
            
            // Process the data into the correct format
            const processedData = processChartData(rawData);
            console.log('Processed chart data:', processedData);
            
            if (!processedData || !processedData.labels || processedData.labels.length === 0 || 
                !processedData.datasets || processedData.datasets.length === 0) {
                showNoDataMessage(`Tidak ada data grafik yang valid untuk ${commodityId === 'all' ? 'semua komoditas' : commodityName}.`);
                return;
            }
            
            // Check if all data points are null/undefined
            const hasValidData = processedData.datasets.some(dataset => 
                dataset.data.some(value => value !== null && value !== undefined)
            );
            
            if (!hasValidData) {
                showNoDataMessage(`Data harga untuk ${commodityId === 'all' ? 'semua komoditas' : commodityName} tidak tersedia.`);
                return;
            }
            
            renderChart(processedData);
            
            if (canvas) {
                canvas.style.display = 'block';
            }
            if (noDataMessage) {
                noDataMessage.classList.add('d-none');
            }
            
        } catch (error) {
            console.error('Error updating chart:', error);
            showNoDataMessage(error.message || 'Terjadi kesalahan saat memuat data grafik. Silakan coba lagi.');
            if (window.myChart) {
                window.myChart.destroy();
                window.myChart = null;
            }
        }
    }

    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarBackdrop = document.querySelector('.sidebar-backdrop');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('show');
            if (sidebarBackdrop) sidebarBackdrop.classList.toggle('show');
            document.body.classList.toggle('sidebar-open');
        });
    }
    
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarBackdrop.classList.remove('show');
            document.body.classList.remove('sidebar-open');
        });
    }
    
    document.addEventListener('click', function(event) {
        if (sidebar && sidebarBackdrop) {
            if (!event.target.closest('.sidebar') && !event.target.closest('#sidebarToggle')) {
                sidebar.classList.remove('show');
                sidebarBackdrop.classList.remove('show');
                document.body.classList.remove('sidebar-open');
            }
        }
    });

    // Event listeners
    const periodeSelect = document.getElementById('periode');
    const commoditySelect = document.getElementById('commodity_id');
    const btnTampil = document.getElementById('btnTampil');
    const btnLinearScale = document.getElementById('btnLinearScale');
    const btnLogScale = document.getElementById('btnLogScale');
    const btnPrintChart = document.getElementById('btnPrintChart');
    
    if (periodeSelect) periodeSelect.addEventListener('change', updateChart);
    if (commoditySelect) commoditySelect.addEventListener('change', updateChart);
    if (btnTampil) btnTampil.addEventListener('click', updateChart);
    if (btnLinearScale) btnLinearScale.addEventListener('click', () => { if (useLogScale) toggleScale(); });
    if (btnLogScale) btnLogScale.addEventListener('click', () => { if (!useLogScale) toggleScale(); });
    
    if (btnPrintChart) {
        btnPrintChart.addEventListener('click', function() {
            const chartElement = document.getElementById('chartHarga');
            if (!myChart || !chartElement) {
                alert('Silakan tampilkan grafik terlebih dahulu dengan menekan tombol "Tampilkan"');
                return;
            }
            
            const printWindow = window.open('', '_blank');
            const chartImage = chartElement.toDataURL('image/png');
            const commoditySelect = document.getElementById('commodity_id');
            const periodSelect = document.getElementById('periode');
            const commodityName = commoditySelect?.options[commoditySelect.selectedIndex]?.text || 'Semua Komoditas';
            const period = periodSelect?.options[periodSelect.selectedIndex]?.text || '7 Hari Terakhir';
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                    <head>
                        <title>Cetak Grafik Harga</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; margin: 0; }
                            .print-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px; }
                            .print-header h2 { margin: 0 0 10px 0; color: #000080; }
                            .print-header p { margin: 5px 0; color: #666; }
                            .chart-container { text-align: center; margin: 20px 0; }
                            img { max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 8px; }
                            .print-footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
                            @media print { body { padding: 0; } .print-header { border-color: #000; } }
                        </style>
                    </head>
                    <body>
                        <div class="print-header">
                            <h2>Laporan Harga Komoditas</h2>
                            <p><strong>Komoditas:</strong> ${commodityName}</p>
                            <p><strong>Periode:</strong> ${period}</p>
                        </div>
                        <div class="chart-container">
                            <img src="${chartImage}" alt="Grafik Harga">
                        </div>
                        <div class="print-footer">
                            <p>Dicetak pada: ${new Date().toLocaleString('id-ID', { 
                                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'
                            })} WIB</p>
                            <p>Sistem Informasi Harga Bahan Pokok - Bandar Lampung</p>
                        </div>
                        <script>
                            window.onload = function() {
                                setTimeout(function() { window.print(); }, 500);
                            };
                        <\/script>
                    </body>
                </html>
            `);
            printWindow.document.close();
        });
    }

    // Initial load
    updateChart();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</body>
</html>