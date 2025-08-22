<?php
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SESSION['role'] === 'admin') {
    include __DIR__ . '/sidebar_admin.php';
} elseif ($_SESSION['role'] === 'uptd') {
    include __DIR__ . '/sidebar_uptd.php';
}

$auth = new AuthController();
$role = $_SESSION['role'] ?? '';
$user = $auth->requireRole($role === 'admin' ? 'admin' : 'uptd');
$db = new Database();

// Filter
$filterCommodity = isset($_GET['commodity']) ? trim($_GET['commodity']) : '';
$filterMarket = isset($_GET['market']) ? trim($_GET['market']) : '';
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : 'approved';

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

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$priceHistory = $db->fetchAll("
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
", $params);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Harga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 1rem; } }
    </style>
</head>
<body>
<div class="main-content">
    <h3><i class="bi bi-clock-history me-2"></i>Riwayat Harga</h3>
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <input type="text" name="commodity" value="<?= htmlspecialchars($filterCommodity) ?>" placeholder="Cari komoditas" class="form-control">
        </div>
        <div class="col-md-3">
            <input type="text" name="market" value="<?= htmlspecialchars($filterMarket) ?>" placeholder="Cari pasar" class="form-control">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">Semua Status</option>
                <option value="approved" <?= $filterStatus=='approved'?'selected':'' ?>>Approved</option>
                <option value="pending" <?= $filterStatus=='pending'?'selected':'' ?>>Pending</option>
                <option value="rejected" <?= $filterStatus=='rejected'?'selected':'' ?>>Rejected</option>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
        </div>
    </form>

    <?php if (!empty($priceHistory)): ?>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
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
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['commodity_name']) ?></td>
                    <td><?= htmlspecialchars($row['market_name']) ?></td>
                    <td>Rp <?= number_format($row['price'], 0, ',', '.') ?></td>
                    <td>
                        <?php if ($row['status'] == 'approved'): ?>
                            <span class="badge bg-success">Approved</span>
                        <?php elseif ($row['status'] == 'pending'): ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td><?= htmlspecialchars($row['uploaded_by']) ?></td>
                    <td><?= htmlspecialchars($row['approved_by'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="alert alert-info">Tidak ada data harga ditemukan.</div>
    <?php endif; ?>
</div>
</body>
</html>
