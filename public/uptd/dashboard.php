<?php
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Price.php';

// Include sidebar based on user role
if ($_SESSION['role'] === 'admin') {
    include __DIR__ . '/sidebar_admin.php';
} elseif ($_SESSION['role'] === 'uptd') {
    include __DIR__ . '/sidebar_uptd.php';
}

$auth = new AuthController();
$user = $auth->requireRole('uptd');
$priceModel = new Price();

$db = new Database();
$pdo = $db->getConnection();
$totalPasar = $db->fetchOne("SELECT COUNT(*) as count FROM pasar")['count'];
$totalKomoditas = $db->fetchOne("SELECT COUNT(*) as count FROM commodities")['count'];
$totalHarga = $db->fetchOne("SELECT COUNT(*) as count FROM prices")['count'];

$marketName = 'Pasar Tidak Diketahui';
$marketId = null; // ✅ simpan ID pasar juga

if (!empty($user['market_assigned'])) {
    $market = $db->fetchOne("SELECT id_pasar, nama_pasar FROM pasar WHERE id_pasar = ?", [$user['market_assigned']]);
    if ($market) {
        $marketId = $market['id_pasar'];   // ✅ simpan ID pasar
        $marketName = $market['nama_pasar']; // ✅ simpan Nama pasar
    }
}



// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commodity_name = sanitizeInput($_POST['commodity_name']);
    $price = sanitizeInput($_POST['price']);
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($commodity_name)) {
        $errors[] = 'Nama komoditas harus diisi';
    }
    
    if (empty($price)) {
        $errors[] = 'Harga harus diisi';
    } elseif (!validatePrice($price)) {
        $errors[] = 'Harga harus berupa angka maksimal 5 digit (1-99999)';
    }
    
    if (empty($errors)) {
        $data = [
            'commodity_name' => $commodity_name,
            'price' => $price,
            'market_id' => $marketId, // ✅ simpan ID pasar, lebih aman
            'uptd_user_id' => $user['id'],
            'notes' => $notes
        ];
        
        try {
            $priceModel->create($data);
            $_SESSION['success'] = 'Data harga berhasil diinput dan menunggu persetujuan admin';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal menyimpan data: ' . $e->getMessage();
        }
        
        header('Location: dashboard.php');
        exit;
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

// Get UPTD's price data
$myPrices = $db->fetchAll("
    SELECT p.*, c.name AS commodity_name, c.unit, u.full_name AS uptd_name
    FROM prices p
    JOIN commodities c ON p.commodity_id = c.id
    JOIN users u ON p.uptd_user_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 10
");

$pendingCount = count($priceModel->getByUptd($user['id'], 'pending'));
$approvedCount = count($priceModel->getByUptd($user['id'], 'approved'));
$rejectedCount = count($priceModel->getByUptd($user['id'], 'rejected'));

// Default AI insight period
$aiInsightPeriod = isset($_GET['ai_period']) ? sanitizeInput($_GET['ai_period']) : 'weekly';

$pageTitle = 'Dashboard UPTD - Siaga Bapok';
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
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
        }
        
        .btn-primary:hover {
            background-color: var(--dark-green);
            border-color: var(--dark-green);
        }
        
        .status-pending {
            color: #ffc107;
        }
        
        .status-approved {
            color: #28a745;
        }
        
        .status-rejected {
            color: #dc3545;
        }
    </style>
</head>
<body class="bg-light">
    <!-- main content-->
    <div class="main-content">
        <div class="container my-4">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h4 class="mb-0">
                                <i class="bi bi-person-circle me-2"></i>
                                Selamat datang, <?= htmlspecialchars($user['full_name']) ?>👋
                            </h4>
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
                <div class="col-md-3 mb-3">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <i class="bi bi-clock-history text-warning fs-2 mb-2"></i>
                            <h3 class="status-pending"><?php echo $pendingCount; ?></h3>
                            <p class="mb-0">Menunggu Persetujuan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <i class="bi bi-check-circle text-success fs-2 mb-2"></i>
                            <h3 class="status-approved"><?php echo $approvedCount; ?></h3>
                            <p class="mb-0">Disetujui</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <i class="bi bi-x-circle text-danger fs-2 mb-2"></i>
                            <h3 class="status-rejected"><?php echo $rejectedCount; ?></h3>
                            <p class="mb-0">Ditolak</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <i class="bi bi-list-ul text-info fs-2 mb-2"></i>
                            <h3 class="text-info"><?php echo count($myPrices); ?></h3>
                            <p class="mb-0">Total Input</p>
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

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-graph-up me-2"></i>
                            Grafik Kenaikan/Penurunan Harga Komoditas per Bulan
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyTrendsChart"></canvas>
                        </div>
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Data berdasarkan harga yang telah disetujui admin (6 bulan terakhir)
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <!-- Significant Price Changes -->
            <div class="col-12 mb-4">
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
                                                    <strong><?php echo htmlspecialchars($change['commodity_name']); ?></strong>
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
        </div>
        
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                                Perubahan Komoditas
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($myPrices)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Komoditas</th>
                                            <th>Harga</th>
                                            <th>Status</th>
                                            <th>Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($myPrices as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars(date('d M Y', strtotime($row['created_at']))) ?></td>
                                                <td><?= htmlspecialchars($row['commodity_name']) ?></td>
                                                <td>Rp <?= number_format($row['price'] ?? 0, 0, ',', '.') ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    $statusIcon = '';
                                                    $statusText = '';

                                                    switch ($row['status']) {
                                                        case 'pending':
                                                            $statusClass = 'bg-warning';
                                                            $statusIcon = 'bi-clock-history';
                                                            $statusText = 'Menunggu';
                                                            break;
                                                        case 'approved':
                                                            $statusClass = 'bg-success';
                                                            $statusIcon = 'bi-check-circle';
                                                            $statusText = 'Disetujui';
                                                            break;
                                                        case 'rejected':
                                                            $statusClass = 'bg-danger';
                                                            $statusIcon = 'bi-x-circle';
                                                            $statusText = 'Ditolak';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>">
                                                        <i class="bi <?= $statusIcon ?> me-1"></i>
                                                        <?= $statusText ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($row['catatan'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada data</h5>
                                <p class="text-muted">Mulai input data harga menggunakan form di sebelah kiri.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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

        // Load AI insight when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadAIInsight('<?= $aiInsightPeriod ?>');
        });
        
        // Form validation
        document.getElementById('priceForm').addEventListener('submit', function(e) {
            const price = document.getElementById('price').value;
            const commodity = document.getElementById('commodity_name').value;
            
            if (!commodity) {
                e.preventDefault();
                alert('Silakan pilih komoditas terlebih dahulu');
                return;
            }
            
            if (!price || price < 1 || price > 99999) {
                e.preventDefault();
                alert('Harga harus berupa angka antara 1 - 99999');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Mengirim...';
            submitBtn.disabled = true;
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                }
            });
        }, 5000);
        
        // Price input formatting
        document.getElementById('price').addEventListener('input', function() {
            let value = this.value.replace(/[^0-9]/g, '');
            if (value.length > 5) {
                value = value.substring(0, 5);
            }
            this.value = value;
        });
    </script>
</body>
</html>