<?php
// Start output buffering at the very beginning
ob_start();

// Start session and include necessary files
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Database.php';

// Initialize auth and check user role
$auth = new AuthController();
$user = $auth->requireRole('admin');
$db = Database::getInstance();

// Include sidebar based on role before any output
$sidebarPath = __DIR__ . '/includes/sidebar_admin.php';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'approve_all') {
        // Approve all pending prices
        $db->execute(
            "UPDATE prices SET status='approved', approved_by=?, approved_at=NOW() WHERE status='pending'",
            [$user['id']]
        );
        $_SESSION['success'] = "Semua data harga berhasil di-approve.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        ob_end_flush();
        exit;
    } elseif (isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $action = $_POST['action'];

        if ($action === 'approve') {
            $db->execute(
                "UPDATE prices SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?",
                [$user['id'], $id]
            );
            $_SESSION['success'] = "Data harga berhasil di-approve.";
        } elseif ($action === 'reject') {
            $db->execute(
                "UPDATE prices SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=?",
                [$user['id'], $id]
            );
            $_SESSION['success'] = "Data harga berhasil ditolak.";
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        ob_end_flush();
        exit;
    }
}


// Ambil semua harga pending dari UPTD
$pendingData = $db->fetchAll("
    SELECT 
        p.id, 
        c.name AS commodity_name, 
        c.unit,
        p.price, 
        ps.nama_pasar AS market_name, 
        p.uptd_user_id, 
        p.created_at, 
        u.username AS uploaded_by
    FROM prices p
    JOIN commodities c ON p.commodity_id = c.id
    JOIN pasar ps ON p.market_id = ps.id_pasar
    LEFT JOIN users u ON p.uptd_user_id = u.id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC
");
// Clear any previous output
if (ob_get_level() > 0) {
    ob_clean();
}

?>
<?php 
// Clear any previous output
if (ob_get_level() > 0) {
    ob_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan Data Harga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../public/assets/images/BANDAR LAMPUNG ICON.png">
    <style>
        :root {
            --primary-blue: #000080;
            --dark-blue: #3232b9ff;
            --sidebar-width: 250px;
            --navbar-height: 56px;
            --success: #198754;
            --danger: #dc3545;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding-top: var(--navbar-height);
            transition: margin 0.3s ease-in-out;
        }

        /* Navbar Styles */
        .navbar {
            height: var(--navbar-height);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1030;
            padding: 0.5rem 1rem;
        }
        
        /* Sidebar Styles */
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
        
        .sidebar .badge {
            background-color: rgba(255, 255, 255, 0.25) !important;
            color: white !important;
        }

        /* Sidebar Backdrop */
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

        /* Mobile Responsive */
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
        
         /* Your existing card, button, and other styles from approval.php */
        .card { 
            border: none; 
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            height: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
            padding: 1.25rem 1.5rem;
            font-size: 1.1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .status-badge {
            padding: 0.4em 0.8em;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 50rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .btn-approve {
            background-color: var(--success);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-approve:hover {
            background-color: #157347;
            transform: translateY(-1px);
        }
        
        .btn-reject {
            background-color: var(--danger);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-reject:hover {
            background-color: #bb2d3b;
            transform: translateY(-1px);
        }
        
        .btn-approve-all {
            background-color: var(--primary-blue);
            color: white;
            border: none;
            padding: 0.6rem 1.25rem;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-approve-all:hover {
            background-color: #000066;
            transform: translateY(-1px);
        }
        
        .price-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin: 0.5rem 0;
        }
        
        .commodity-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .market-name {
            color: #6c757d;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--primary-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
        
        .empty-state h4 {
            margin-bottom: 0.5rem;
            color: #343a40;
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" id="sidebarToggle" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list" style="font-size: 1.5rem;"></i>
            </button>
            <a class="navbar-brand ms-2" href="#">Persetujuan Harga</a>
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
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1 class="h3 mb-1">
                                    <i class="bi bi-check-circle me-2"></i>
                                    Persetujuan Data Harga
                                </h1>
                                <p class="mb-0 opacity-75">Halaman ini menampilkan data harga yang diinput oleh UPTD dan menunggu persetujuan Anda.</p>
                            </div>
                            <?php if (!empty($pendingData)): ?>
                                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                    <form method="POST" class="d-inline" id="approveAllForm">
                                        <input type="hidden" name="action" value="approve_all">
                                        <button type="button" class="btn btn-light text-primary" id="btnApproveAll">
                                            <i class="bi bi-check2-all me-1"></i> Setujui Semua
                                        </button>
                                    </form>
                                    <script>
                                    document.getElementById('btnApproveAll').addEventListener('click', function() {
                                        Swal.fire({
                                            title: 'Konfirmasi',
                                            text: 'Apakah Anda yakin ingin menyetujui semua data?',
                                            icon: 'question',
                                            showCancelButton: true,
                                            confirmButtonColor: '#198754',
                                            cancelButtonColor: '#dc3545',
                                            confirmButtonText: 'Ya, Setujui Semua',
                                            cancelButtonText: 'Batal',
                                            reverseButtons: true
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                document.getElementById('approveAllForm').submit();
                                            }
                                        });
                                    });
                                    </script>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="row g-4">
            <?php if (!empty($pendingData)): ?>
                <?php foreach ($pendingData as $row): 
                    // Format date
                    $date = new DateTime($row['created_at']);
                    $formattedDate = $date->format('d M Y');
                    
                    // Get first letter of uploaded_by for avatar
                    $avatarText = strtoupper(substr($row['uploaded_by'], 0, 1));
                ?>
                <div class="col-12 col-sm-6 col-xxl-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Data Harga Baru</span>
                            <span class="status-badge status-pending">Menunggu</span>
                        </div>
                        <div class="card-body">
                            <div class="commodity-name"><?= htmlspecialchars($row['commodity_name']) ?></div>
                            <div class="market-name">
                                <i class="bi bi-shop"></i>
                                <?= htmlspecialchars($row['market_name']) ?>
                            </div>
                            <div class="price-value">
                                Rp <?= number_format($row['price'], 0, ',', '.') ?>
                                <small class="text-muted">/<?= htmlspecialchars($row['unit']) ?></small>
                            </div>
                            
                            <div class="user-info">
                                <div class="user-avatar"><?= $avatarText ?></div>
                                <div>
                                    <div class="fw-medium"><?= htmlspecialchars($row['uploaded_by']) ?></div>
                                    <div class="text-muted small"><?= $formattedDate ?></div>
                                </div>
                            </div>
                            
                            <div class="action-buttons mt-auto">
                                <form method="POST" class="w-100">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="action" value="approve" class="btn-approve">
                                            <i class="bi bi-check-lg"></i> Setujui
                                        </button>
                                    </div>
                                </form>
                                <form method="POST" class="w-100">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="action" value="reject" class="btn-reject">
                                            <i class="bi bi-x-lg"></i> Tolak
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="card">
                        <div class="empty-state">
                            <i class="bi bi-check2-circle"></i>
                            <h4>Tidak ada data yang perlu disetujui</h4>
                            <p>Semua data harga telah disetujui atau belum ada data yang masuk.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            let backdrop = document.querySelector('.sidebar-backdrop');
            
            if (!sidebar || !sidebarToggle) {
                console.error("Sidebar or toggle button not found. Please check your HTML IDs.");
                return;
            }

            // Create backdrop if it doesn't exist
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.className = 'sidebar-backdrop';
                document.body.appendChild(backdrop);
            }
            
            // Function to toggle sidebar
            const toggleSidebar = () => {
                const isShown = sidebar.classList.toggle('show');
                document.body.classList.toggle('sidebar-open');
                
                if (isShown) {
                    backdrop.classList.add('show');
                } else {
                    backdrop.classList.remove('show');
                }
            };
            
            // Add event listeners
            sidebarToggle.addEventListener('click', toggleSidebar);
            backdrop.addEventListener('click', toggleSidebar);

            // Handle window resize to desktop
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('show');
                    document.body.classList.remove('sidebar-open');
                    backdrop.classList.remove('show');
                }
            });

            // Handle mobile sidebar on initial load
            if (window.innerWidth < 992) {
                sidebar.classList.remove('show');
            }
            
            // Automatically close alerts
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
<?php
// Include the sidebar before ending output buffering
if (isset($sidebarPath) && file_exists($sidebarPath)) {
    include $sidebarPath;
}

// Flush the output buffer
ob_end_flush();
?>
