<?php
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Database.php';

$auth = new AuthController();
$role = $_SESSION['role'] ?? '';
$user = $auth->requireRole($role === 'admin' ? 'admin' : 'uptd');
$db = Database::getInstance();

// Path to the consolidated sidebar file based on role
$sidebarPath = __DIR__ . '/includes/sidebar_' . $user['role'] . '.php';

// Filter
$filterCommodity = isset($_GET['commodity']) ? trim($_GET['commodity']) : '';
$filterMarket = isset($_GET['market']) ? trim($_GET['market']) : '';
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';

$where = [];
$params = [];

// Hak akses: Admin bisa lihat semua, UPTD hanya lihat miliknya
if ($user['role'] === 'uptd') {
    $where[] = "p.uptd_user_id = ?";
    $params[] = $user['id'];
}

if ($filterCommodity !== '') {
    $where[] = "c.name LIKE ?";
    $params[] = "%$filterCommodity%";
}

if ($filterMarket !== '') {
    $where[] = "ps.nama_pasar LIKE ?";
    $params[] = "%$filterMarket%";
}

if ($filterStatus !== '') {
    $where[] = "p.status = ?";
    $params[] = $filterStatus;
}

if ($filterDate !== '') {
    $where[]  = "DATE(p.created_at) = ?";
    $params[] = $filterDate;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Hitung total data
$total = $db->fetchOne("SELECT COUNT(*) as total FROM prices p 
    JOIN commodities c ON p.commodity_id = c.id
    JOIN pasar ps ON p.market_id = ps.id_pasar
    JOIN users u ON p.uptd_user_id = u.id
    $whereSQL", $params)['total'];

// Konfigurasi pagination
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $perPage;
$pages = ceil($total / $perPage);

// Ambil data dengan pagination
$query = "
    SELECT p.id, c.name AS commodity_name, ps.nama_pasar AS market_name, p.price, p.status, 
           p.created_at, u.full_name AS uploaded_by,
           a.full_name AS approved_by
    FROM prices p
    JOIN commodities c ON p.commodity_id = c.id
    JOIN pasar ps ON p.market_id = ps.id_pasar
    JOIN users u ON p.uptd_user_id = u.id
    LEFT JOIN users a ON p.approved_by = a.id
    $whereSQL
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?";

// Tambahkan parameter limit dan offset
$queryParams = array_merge($params, [$perPage, $offset]);
$priceHistory = $db->fetchAll($query, $queryParams);

$pageTitle = 'Riwayat Harga - Siaga Bapok';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
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
        
        .table th, .table td {
            vertical-align: middle;
        }
        .pagination .page-link {
            color: var(--primary-blue);
        }
        .pagination .page-item.active .page-link {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        .filter-form .form-control, 
        .filter-form .form-select {
            border-radius: 0.5rem;
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
            <a class="navbar-brand ms-2" href="#">Riwayat Harga</a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-white me-3 d-none d-sm-inline">
                    <i class="bi bi-person-circle me-1"></i>
                    <?php echo htmlspecialchars($user['username'] ?? 'Admin'); ?>
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
                            <i class="bi bi-clock-history me-2"></i>
                            Riwayat Harga Komoditas
                        </h1>
                        <p class="mb-0 mt-2 opacity-75">
                            Pantau perubahan harga komoditas dari waktu ke waktu dengan riwayat lengkap kami.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" class="row g-2 mb-3 filter-form">
            <div class="col-md-3">
                <input type="text" name="commodity" value="<?= htmlspecialchars($filterCommodity) ?>" placeholder="Cari komoditas" class="form-control">
            </div>
            <div class="col-md-3">
                <input type="text" name="market" value="<?= htmlspecialchars($filterMarket) ?>" placeholder="Cari pasar" class="form-control">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="approved" <?= $filterStatus=='approved'?'selected':'' ?>>Approved</option>
                    <option value="pending"  <?= $filterStatus=='pending' ?'selected':'' ?>>Pending</option>
                    <option value="rejected" <?= $filterStatus=='rejected'?'selected':'' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100"><i class="bi bi-search me-2"></i> Filter</button>
            </div>
        </form>

        <?php if (!empty($priceHistory)): ?>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered align-middle mb-0">
                        <thead class="table-dark text-center">
                            <tr>
                                <th>No</th>
                                <th>Komoditas</th>
                                <th>Pasar</th>
                                <th>Harga</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Diinput oleh</th>
                                <th>Disetujui oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($priceHistory as $i => $row): ?>
                            <tr>
                                <td class="text-center"><?= $offset + $i + 1 ?></td>
                                <td><?= htmlspecialchars($row['commodity_name']) ?></td>
                                <td><?= htmlspecialchars($row['market_name']) ?></td>
                                <td class="fw-bold text-primary">Rp <?= number_format($row['price'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <?php if ($row['status'] == 'approved'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Approved</span>
                                    <?php elseif ($row['status'] == 'pending'): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d-m-Y H:i', strtotime($row['created_at'])) ?></td>
                                <td><?= htmlspecialchars($row['uploaded_by']) ?></td>
                                <td><?= htmlspecialchars($row['approved_by'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($total > 0): ?>
        <div class="d-flex flex-column flex-lg-row justify-content-center align-items-center gap-3 mt-3">
            <div class="text-muted">
                Menampilkan <?= $total > 0 ? (($page - 1) * $perPage + 1) : 0 ?> - <?= min($page * $perPage, $total) ?> dari <?= $total ?> data
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                &laquo;
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($pages, $page + 2);
                    
                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                            <a class="page-link" 
                               href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                    <?php 
                    endfor;
                    
                    if ($endPage < $pages) {
                        if ($endPage < $pages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $pages])) . '">' . $pages . '</a></li>';
                    }
                    ?>
                    
                    <?php if ($page < $pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                &raquo;
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="alert alert-info text-center py-4">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
            Tidak ada data harga ditemukan.
        </div>
        <?php endif; ?>
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