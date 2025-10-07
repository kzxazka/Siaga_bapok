<?php
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Database.php';
require_once __DIR__ . '/../../src/models/Price.php';
require_once __DIR__ . '/../../src/models/Commodity.php';

$auth = new AuthController();
$role = $_SESSION['role'] ?? '';
$user = $auth->requireRole('uptd');

$db = Database::getInstance();
$priceModel = new Price();
$commodityModel = new Commodity();

$assignedMarket = null;
if ($role === 'uptd') {
    $assignedMarket = $db->fetchOne("
        SELECT ps.id_pasar, ps.nama_pasar
        FROM users u
        JOIN pasar ps ON u.market_assigned = ps.id_pasar
        WHERE u.id = ?
    ", [$_SESSION['user_id']]);
}

// Path to the consolidated sidebar file
$sidebarPath = __DIR__ . '/includes/sidebar_uptd.php';

function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Handle input harga (untuk UPTD saja)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'uptd') {
    $commodityId = (int) $_POST['commodity_id'];
    $price = sanitizeInput($_POST['price']);
    $marketId = (int) $_POST['market_id'];
    $notes = trim($_POST['notes'] ?? '');
    $uptdId = $_SESSION['user_id'];
    
    try {
        if (empty($commodityId)) {
            throw new Exception('Komoditas harus dipilih.');
        }
        if (empty($price) || !is_numeric($price) || $price <= 0) {
            throw new Exception('Harga harus berupa angka positif.');
        }
        
        $data = [
            'commodity_id' => $commodityId,
            'price' => $price,
            'market_id' => $marketId,
            'uptd_user_id' => $uptdId,
            'notes' => $notes
        ];
        
        $priceModel->create($data);
        $_SESSION['success'] = "Harga berhasil diinput dan menunggu persetujuan admin.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal menginput harga: " . $e->getMessage();
    }
    header("Location: uploadHarga.php");
    exit;
}

// Ambil data pasar & komoditas
$commodities = $commodityModel->getAll();

// Ambil data harga yang sudah diinput
$prices = $priceModel->getByUptd($user['id']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Harga - Siaga Bapok</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
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
        
        .status-pending { color: #ffc107; }
        .status-approved { color: #28a745; }
        .status-rejected { color: #dc3545; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" id="sidebarToggle" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list" style="font-size: 1.5rem;"></i>
            </button>
            <a class="navbar-brand ms-2" href="#">Upload Harga</a>
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
                        <h1 class="h3 mb-0">
                            <i class="bi bi-upload me-2"></i>
                            Upload Harga
                        </h1>
                        <p class="mb-0 mt-2 opacity-75">
                            Upload harga komoditas pasar untuk persetujuan admin. Pastikan data yang diinput sudah benar.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-plus-circle"></i> Input Harga Baru
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Pilih Komoditas</label>
                                <select name="commodity_id" class="form-select" required>
                                    <option value="" disabled selected>-- Pilih Komoditas --</option>
                                    <?php foreach ($commodities as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['unit']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Harga per Satuan</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" name="price" id="price" class="form-control" min="1" max="99999" required>
                                </div>
                            </div>
                            <input type="hidden" name="market_id" value="<?= $assignedMarket['id_pasar'] ?>">
                            <div class="mb-3">
                                <label class="form-label">Pasar</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($assignedMarket['nama_pasar']) ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Catatan (Opsional)</label>
                                <textarea name="notes" class="form-control" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send"></i> Simpan</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-table me-2"></i> Daftar Harga Terakhir Anda</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered align-middle mb-0">
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
                                    <?php if (!empty($prices)): ?>
                                        <?php foreach ($prices as $p): ?>
                                            <tr>
                                                <td><?= htmlspecialchars(date('d M Y', strtotime($p['created_at']))) ?></td>
                                                <td><?= htmlspecialchars($p['commodity_name']) ?> <span class="text-muted">(<?= htmlspecialchars($p['unit']) ?>)</span></td>
                                                <td class="fw-bold text-primary">Rp <?= number_format($p['price'], 0, ',', '.') ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    $statusIcon = '';
                                                    $statusText = '';
                                                    switch ($p['status']) {
                                                        case 'pending': $statusClass = 'bg-warning text-dark'; $statusIcon = 'bi-clock-history'; $statusText = 'Menunggu'; break;
                                                        case 'approved': $statusClass = 'bg-success'; $statusIcon = 'bi-check-circle'; $statusText = 'Disetujui'; break;
                                                        case 'rejected': $statusClass = 'bg-danger'; $statusIcon = 'bi-x-circle'; $statusText = 'Ditolak'; break;
                                                    }
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>">
                                                        <i class="bi <?= $statusIcon ?> me-1"></i>
                                                        <?= $statusText ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($p['notes'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                                Belum ada data harga.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            
            // Auto-hide alerts
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