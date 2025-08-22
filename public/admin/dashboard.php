<?php
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Price.php';
require_once __DIR__ . '/../../src/models/User.php';

// Include sidebar based on user role
if ($_SESSION['role'] === 'admin') {
    include __DIR__ . '/sidebar_admin.php';
} elseif ($_SESSION['role'] === 'uptd') {
    include __DIR__ . '/sidebar_uptd.php';
}

$auth = new AuthController();
$user = $auth->requireRole('admin');
$priceModel = new Price();
$userModel = new User();

$trends1Day = $priceModel->getPriceTrends(1);
$trends7Days = $priceModel->getPriceTrends(7);
$trends30Days = $priceModel->getPriceTrends(30);

// Get latest prices
$latestPrices = $priceModel->getLatestPrices();

// Get top increasing prices (7 days)
$topIncreasing = $priceModel->getTopIncreasingPrices(7, 5);

// Get statistics
$stats = $priceModel->getStatistics();

// Database connection
$db = new Database();
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
$pendingCount = count($priceModel->getAll('pending'));

// Get slider images
$sliderDir = __DIR__ . '/../../assets/img/slider/';
$sliderImages = [];
if (is_dir($sliderDir)) {
    $sliderImages = array_diff(scandir($sliderDir), array('.', '..'));
}

$pageTitle = 'Dashboard Admin - Siaga Bapok';
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
    
    <style>
        :root {
            --primary-green: #000080;
            --light-green: #d4edda;
            --dark-green: #3232b9ff;
            --sidebar-width: 250px;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-green) 0%, var(--dark-green) 100%);
            color: white;
            z-index: 1000;
            overflow-y: auto;
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
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
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
            margin-bottom: 2rem;
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
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <div class="main-content">
        <!-- Mobile Menu Toggle -->
        <button class="btn btn-primary d-md-none mb-3" type="button" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        
        <!-- Page Header -->
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

        <!-- AI Insight Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-robot me-2"></i> Analisis AI - Insight Harga Komoditas
                        </h5>
                        <div class="btn-group">
                            <a href="?ai_period=weekly" class="btn btn-sm <?= $aiInsightPeriod === 'weekly' ? 'btn-light' : 'btn-outline-light' ?>">Mingguan</a>
                            <a href="?ai_period=monthly" class="btn btn-sm <?= $aiInsightPeriod === 'monthly' ? 'btn-light' : 'btn-outline-light' ?>">Bulanan</a>
                            <a href="?ai_period=6months" class="btn btn-sm <?= $aiInsightPeriod === '6months' ? 'btn-light' : 'btn-outline-light' ?>">6 Bulan</a>
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

        <!-- Alerts -->
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

        <!-- Statistics Cards -->
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

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-clipboard-data text-warning fs-1 mb-2"></i>
                        <h3 class="text-warning"><?= $totalHarga ?></h3>
                        <p class="mb-0">Total Input Harga</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- Monthly Price Trends Chart -->
        <div class="row mb-5" id="chartSection">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">
                            <i class="bi bi-graph-up me-2"></i>
                            Grafik Pergerakan Harga Komoditas
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Period Selection -->
                        <div class="period-buttons mb-3">
                            <h6>Pilih Periode:</h6>
                            <button class="btn btn-outline-primary active" data-period="1" onclick="changePeriod(1)">
                                1 Hari Terakhir
                            </button>
                            <button class="btn btn-outline-primary" data-period="7" onclick="changePeriod(7)">
                                7 Hari Terakhir
                            </button>
                            <button class="btn btn-outline-primary" data-period="30" onclick="changePeriod(30)">
                                30 Hari Terakhir
                            </button>
                        </div>
                        
                        <!-- Chart Container -->
                        <div class="chart-container">
                            <canvas id="priceChart"></canvas>
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Grafik menampilkan rata-rata harga per hari dari semua pasar
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <!-- Significant Price Changes -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Komoditas dengan Perubahan Harga Paling Signifikan
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($significantChanges)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Komoditas</th>
                                            <th>Harga Sebelum</th>
                                            <th>Harga Sekarang</th>
                                            <th>Perubahan</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($significantChanges, 0, 8) as $change): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($change['name']); ?></strong>
                                                </td>
                                                <td><?php echo formatRupiah($change['previous_price']); ?></td>
                                                <td><?php echo formatRupiah($change['current_price']); ?></td>
                                                <td>
                                                    <?php
                                                    $percentage = (($change['current_price'] - $change['previous_price']) / $change['previous_price']) * 100;
                                                    $badgeClass = $percentage > 0 ? 'bg-danger' : 'bg-success';
                                                    $icon = $percentage > 0 ? 'bi-arrow-up' : 'bi-arrow-down';
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?>">
                                                        <i class="bi <?php echo $icon; ?> me-1"></i>
                                                        <?php echo ($percentage > 0 ? '+' : '') . number_format($percentage, 1); ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (abs($percentage) > 20): ?>
                                                        <span class="badge bg-danger">Sangat Signifikan</span>
                                                    <?php elseif (abs($percentage) > 10): ?>
                                                        <span class="badge bg-warning text-dark">Signifikan</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">Normal</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-graph-up fs-1 text-muted mb-3"></i>
                                <h5 class="text-muted">Tidak ada perubahan signifikan</h5>
                                <p class="text-muted">Semua harga komoditas dalam kondisi stabil.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Image Slider Management -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-images me-2"></i>
                            Kelola Gambar Slider
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Upload Area -->
                        <form method="POST" enctype="multipart/form-data" class="mb-3">
                            <input type="hidden" name="action" value="upload_image">
                            <div class="upload-area" onclick="document.getElementById('slider_image').click()">
                                <i class="bi bi-cloud-upload fs-1 text-muted mb-2"></i>
                                <p class="mb-0">Klik untuk upload gambar</p>
                                <small class="text-muted">JPG, PNG, max 2MB</small>
                            </div>
                            <input type="file" id="slider_image" name="slider_image" 
                                   accept="image/*" style="display: none;" onchange="this.form.submit()">
                        </form>
                        
                        <!-- Current Images -->
                        <div class="row g-2">
                            <?php foreach ($sliderImages as $image): ?>
                                <div class="col-6">
                                    <div class="position-relative">
                                        <img src="../../assets/img/slider/<?php echo $image; ?>" 
                                             class="slider-image w-100" alt="Slider Image">
                                        <form method="POST" class="position-absolute top-0 end-0 m-1">
                                            <input type="hidden" name="action" value="delete_image">
                                            <input type="hidden" name="image_name" value="<?php echo $image; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Hapus gambar ini?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($sliderImages)): ?>
                                <div class="col-12 text-center py-3">
                                    <i class="bi bi-images text-muted fs-1 mb-2"></i>
                                    <p class="text-muted mb-0">Belum ada gambar slider</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-lightning me-2"></i>
                            Aksi Cepat
                        </h5>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <a href="approve.php" class="btn btn-warning w-100">
                                    <i class="bi bi-check-circle me-2"></i>
                                    Approve Data
                                    <?php if ($pendingCount > 0): ?>
                                        <span class="badge bg-dark ms-2"><?php echo $pendingCount; ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="admin/manageUser.php?action=create" class="btn btn-primary w-100">
                                    <i class="bi bi-person-plus me-2"></i>
                                    Tambah User
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="markets.php" class="btn btn-success w-100">
                                    <i class="bi bi-shop me-2"></i>
                                    Kelola Pasar
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="commodities.php" class="btn btn-info w-100">
                                    <i class="bi bi-basket me-2"></i>
                                    Kelola Komoditas
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Monthly trends data
        const priceData = {
            1: <?php echo json_encode($trends1Day); ?>,
            7: <?php echo json_encode($trends7Days); ?>,
            30: <?php echo json_encode($trends30Days); ?>
        };
        
        let currentChart = null;
        let currentPeriod = 1;
        
        // Initialize chart
        function initChart() {
            const ctx = document.getElementById('priceChart').getContext('2d');
            updateChart(1);
        }
        
        // Update chart based on period
        function updateChart(period) {
            const data = priceData[period];
            
            if (!data || data.length === 0) {
                showNoDataMessage();
                return;
            }
            
            // Group data by commodity
            const commodityData = {};
            data.forEach(item => {
                if (!commodityData[item.commodity_name]) {
                    commodityData[item.commodity_name] = [];
                }
                commodityData[item.commodity_name].push({
                    date: item.price_date,
                    price: parseFloat(item.avg_price)
                });
            });
            
            // Get unique dates and sort them
            const dates = [...new Set(data.map(item => item.price_date))].sort();
            
            // Create datasets for each commodity
            const datasets = Object.keys(commodityData).slice(0, 6).map((commodity, index) => {
                const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
                
                const commodityPrices = dates.map(date => {
                    const item = commodityData[commodity].find(d => d.date === date);
                    return item ? item.price : null;
                });
                
                return {
                    label: commodity,
                    data: commodityPrices,
                    borderColor: colors[index],
                    backgroundColor: colors[index] + '20',
                    tension: 0.1,
                    fill: false
                };
            });
            
            // Destroy existing chart
            if (currentChart) {
                currentChart.destroy();
            }
            
            // Create new chart
            const ctx = document.getElementById('priceChart').getContext('2d');
            currentChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates.map(date => {
                        const d = new Date(date);
                        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
                    }),
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: `Pergerakan Harga ${period} Hari Terakhir`
                        },
                        legend: {
                            display: true,
                            position: 'top'
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
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        }
        
        // Change period
        function changePeriod(period) {
            currentPeriod = period;
            
            // Update button states
            document.querySelectorAll('.period-buttons .btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-period="${period}"]`).classList.add('active');
            
            // Update chart
            updateChart(period);
        }
        
        // Show no data message
        function showNoDataMessage() {
            const ctx = document.getElementById('priceChart').getContext('2d');
            if (currentChart) {
                currentChart.destroy();
            }
            
            ctx.font = '16px Arial';
            ctx.fillStyle = '#6c757d';
            ctx.textAlign = 'center';
            ctx.fillText('Tidak ada data untuk periode ini', ctx.canvas.width / 2, ctx.canvas.height / 2);
        }
        
        // Scroll functions
        function scrollToChart() {
            document.getElementById('chartSection').scrollIntoView({ behavior: 'smooth' });
        }
        
        function scrollToLatestPrices() {
            document.getElementById('latestPricesSection').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initChart();
        });
        
        // Toggle sidebar for mobile
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }
        
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                }
            });
        }, 5000);
        
        // Load AI Insight
        function loadAIInsight(period = 'weekly') {
            const container = document.getElementById('ai-insight-container');
            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Memuat analisis AI...</p>
                </div>
            `;
            
            fetch(`../ai_insight.php?period=${period}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        container.innerHTML = `
                            <div class="ai-insight">
                                ${data.insight}
                                <div class="text-muted small mt-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Analisis dibuat oleh AI pada ${new Date(data.generated_at).toLocaleString('id-ID')}
                                </div>
                            </div>
                        `;
                    } else {
                        container.innerHTML = `
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                ${data.message || 'Gagal memuat analisis AI. Silakan coba lagi nanti.'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching AI insight:', error);
                    container.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Terjadi kesalahan saat memuat analisis AI. Silakan coba lagi nanti.
                        </div>
                    `;
                });
        }
        
        // Initialize chart when page loads
        document.addEventListener('DOMContentLoaded', function() {
            createMonthlyTrendsChart();
            loadAIInsight('<?= $aiInsightPeriod ?>');
        });
        
        // Drag and drop for image upload
        const uploadArea = document.querySelector('.upload-area');
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('slider_image').files = files;
                document.querySelector('form[enctype="multipart/form-data"]').submit();
            }
        });
    </script>
</body>
</html>