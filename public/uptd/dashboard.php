<?php
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Price.php';
require_once __DIR__ . '/../../src/models/Database.php';

// Inisialisasi autentikasi
$auth = new AuthController();
$user = $auth->requireRole('uptd');
$priceModel = new Price();
$db = Database::getInstance();

// Path to the consolidated sidebar file
$sidebarPath = __DIR__ . '/includes/sidebar_uptd.php';

// Function to sanitize user input
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Function to validate price (contoh sederhana)
function validatePrice($price) {
    return is_numeric($price) && $price >= 1 && $price <= 99999;
}

// Ambil data pasar UPTD
$marketName = 'Pasar Tidak Diketahui';
$marketId = null;
if (!empty($user['market_assigned'])) {
    $market = $db->fetchOne("SELECT id_pasar, nama_pasar FROM pasar WHERE id_pasar = ?", [$user['market_assigned']]);
    if ($market) {
        $marketId = $market['id_pasar'];
        $marketName = $market['nama_pasar'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commodity_id = (int) $_POST['commodity_id'];
    $price = sanitizeInput($_POST['price']);
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($commodity_id)) {
        $errors[] = 'Komoditas harus dipilih';
    }
    
    if (empty($price)) {
        $errors[] = 'Harga harus diisi';
    } elseif (!validatePrice($price)) {
        $errors[] = 'Harga harus berupa angka maksimal 5 digit (1-99999)';
    }
    
    if (empty($errors)) {
        $data = [
            'commodity_id' => $commodity_id,
            'price' => $price,
            'market_id' => $marketId,
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
    WHERE p.uptd_user_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
", [$user['id']]);

// Get statistics for UPTD
$pendingCount = $priceModel->countByUptdAndStatus($user['id'], 'pending');
$approvedCount = $priceModel->countByUptdAndStatus($user['id'], 'approved');
$rejectedCount = $priceModel->countByUptdAndStatus($user['id'], 'rejected');

$pageTitle = 'Dashboard UPTD - Siaga Bapok';
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
    
    <style>
        :root {
            --primary-blue: #000080;
            --dark-blue: #3232b9ff;
            --sidebar-width: 250px;
            --navbar-height: 56px;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding-top: var(--navbar-height);
            transition: margin 0.3s ease-in-out;
        }

        .navbar {
            height: var(--navbar-height);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1030;
            padding: 0.5rem 1rem;
        }

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

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: margin 0.3s ease-in-out;
        }

        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

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
            <a class="navbar-brand ms-2" href="#">Dashboard UPTD</a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-white me-3 d-none d-sm-inline">
                    <i class="bi bi-person-circle me-1"></i>
                    <?php echo htmlspecialchars($user['username'] ?? 'UPTD'); ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <?php include $sidebarPath; ?>

    <main class="main-content">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h4 class="mb-0">
                            <i class="bi bi-person-circle me-2"></i>
                            Selamat datang, <?= htmlspecialchars($user['full_name']) ?>👋
                        </h4>
                        <p class="mb-0 mt-2 opacity-75">
                            Pasar yang Anda kelola: <strong><?= htmlspecialchars($marketName) ?></strong>
                        </p>
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
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ul me-2"></i>10 Riwayat Input Harga Terakhir Anda
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($myPrices)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
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
                                                <td>Rp <?= number_format($row['price'] ?? 0, 0, ',', '.') ?> / <?= htmlspecialchars($row['unit']) ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    $statusIcon = '';
                                                    $statusText = '';
                                                    switch ($row['status']) {
                                                        case 'pending': $statusClass = 'bg-warning'; $statusIcon = 'bi-clock-history'; $statusText = 'Menunggu'; break;
                                                        case 'approved': $statusClass = 'bg-success'; $statusIcon = 'bi-check-circle'; $statusText = 'Disetujui'; break;
                                                        case 'rejected': $statusClass = 'bg-danger'; $statusIcon = 'bi-x-circle'; $statusText = 'Ditolak'; break;
                                                    }
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>">
                                                        <i class="bi <?= $statusIcon ?> me-1"></i>
                                                        <?= $statusText ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($row['notes'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada data harga</h5>
                                <p class="text-muted">Mulai input data harga melalui menu "Upload Harga".</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function setupSidebarToggle() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (!sidebar || !sidebarToggle) {
                console.error("Sidebar or toggle button not found. Please check your HTML IDs.");
                return;
            }

            let backdrop = document.querySelector('.sidebar-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.className = 'sidebar-backdrop';
                document.body.appendChild(backdrop);
            }
            
            const toggleSidebar = () => {
                const isShown = sidebar.classList.toggle('show');
                document.body.classList.toggle('sidebar-open');
                
                if (isShown) {
                    backdrop.classList.add('show');
                } else {
                    backdrop.classList.remove('show');
                }
            };
            
            sidebarToggle.addEventListener('click', toggleSidebar);
            backdrop.addEventListener('click', toggleSidebar);

            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('show');
                    document.body.classList.remove('sidebar-open');
                    backdrop.classList.remove('show');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            setupSidebarToggle();
            
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = bootstrap.Alert.getInstance(alert) || new bootstrap.Alert(alert);
                    if (bsAlert) {
                        bsAlert.close();
                    }
                });
            }, 5000);
        });
    </script>
</body>
</html>