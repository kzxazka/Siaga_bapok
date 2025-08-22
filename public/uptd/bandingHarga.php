<?php
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../config/database.php';

$auth = new AuthController();
$role = $_SESSION['role'] ?? '';
$user = $auth->requireRole($role === 'admin' ? 'admin' : 'uptd');

$db = new Database();

// Include sidebar sesuai role
if ($role === 'admin') {
    include __DIR__ . '/sidebar_admin.php';
} elseif ($role === 'uptd') {
    include __DIR__ . '/sidebar_uptd.php';
}
if (!isset($_SESSION['role'])) {
    header("Location: /auth/login.php");
    exit;
}

// Ambil daftar pasar untuk dropdown
$markets = $db->fetchAll("SELECT id_pasar, nama_pasar FROM pasar ORDER BY nama_pasar ASC");

// Ambil input user
$selectedMarket = $_GET['market'] ?? 'all';
$selectedDate   = $_GET['date'] ?? date('Y-m-d');
$interval       = intval($_GET['interval'] ?? 30); // default 30 hari

// Hitung range tanggal berdasarkan interval
$dateMinus = date('Y-m-d', strtotime($selectedDate . " -{$interval} days"));
$datePlus  = date('Y-m-d', strtotime($selectedDate . " +{$interval} days"));

// Ambil daftar komoditas (dari tabel commodities, BUKAN prices)
$commodities = $db->fetchAll("SELECT id, name, unit FROM commodities ORDER BY name ASC");

// Fungsi ambil harga
function getPrice($db, $commodityId, $date, $marketId) {
    if ($marketId === 'all') {
        $sql = "SELECT AVG(p.price) as price
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                WHERE p.commodity_id = ? AND DATE(p.created_at) = ?";
        $params = [$commodityId, $date];
    } else {
        $sql = "SELECT p.price
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                WHERE p.commodity_id = ? 
                  AND DATE(p.created_at) = ? 
                  AND p.id_pasar = ?";
        $params = [$commodityId, $date, $marketId];
    }
    $row = $db->fetchOne($sql, $params);
    return $row ? ($row['price'] ?? null) : null;
}

// Ambil data tabel
$data = [];
foreach ($commodities as $c) {
    $commodityId = $c['id'];
    $name        = $c['name'];

    $priceHMinus = getPrice($db, $commodityId, $dateMinus, $selectedMarket);
    $priceH      = getPrice($db, $commodityId, $selectedDate, $selectedMarket);
    $priceHPlus  = getPrice($db, $commodityId, $datePlus, $selectedMarket);

    $change1 = ($priceHMinus && $priceH) ? (($priceH - $priceHMinus) / $priceHMinus * 100) : 0;
    $change2 = ($priceH && $priceHPlus) ? (($priceHPlus - $priceH) / $priceH * 100) : 0;

    $data[] = [
        'name'      => $name,
        'h_minus'   => $priceHMinus,
        'selected'  => $priceH,
        'h_plus'    => $priceHPlus,
        'change1'   => $change1,
        'change2'   => $change2
    ];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <title>Document</title>
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
    <!--main content-->
    <div class="main-content">

        <!-- header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h1 class="h3 mb-0">
                            <i class="bi bi-people me-2"></i>
                            Banding Harga Komoditas
                        </h1>
                        <p class="mb-0 mt-2 opacity-75">
                            Perbandingan harga komoditas antar pasar untuk membantu pedagang dan masyarakat menentukan harga yang adil.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- content -->
        <form class="row g-3 mb-4" method="GET">
            <div class="col-md-3">
                <label class="form-label">Pilih Pasar</label>
                <select name="market" class="form-select">
                    <option value="all" <?= $selectedMarket==='all'?'selected':'' ?>>Semua Pasar</option>
                    <?php foreach($markets as $m): ?>
                        <option value="<?= $m['id_pasar'] ?>" <?= $selectedMarket==$m['id_pasar']?'selected':'' ?>>
                            <?= htmlspecialchars($m['nama_pasar']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Pilih Tanggal</label>
                <input type="date" name="date" value="<?= $selectedDate ?>" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Interval</label>
                <select name="interval" class="form-select">
                    <option value="1" <?= $interval==1?'selected':'' ?>>1 Hari</option>
                    <option value="7" <?= $interval==7?'selected':'' ?>>7 Hari</option>
                    <option value="30" <?= $interval==30?'selected':'' ?>>30 Hari</option>
                </select>
            </div>
            <div class="col-md-3 align-self-end">
                <button class="btn btn-primary w-100">Tampilkan</button>
            </div>
        </form>

        <!-- Tabel Harga Banding -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-table"></i> Daftar Harga <?= $role === 'uptd' ? "Anda" : "Semua UPTD" ?>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Komoditas</th>
                                <th>H-<?= $interval ?> (<?= date('d/m/Y', strtotime($dateMinus)) ?>)</th>
                                <th>Harga (<?= date('d/m/Y', strtotime($selectedDate)) ?>)</th>
                                <th>H+<?= $interval ?> (<?= date('d/m/Y', strtotime($datePlus)) ?>)</th>
                                <th>% H-<?= $interval ?> → Pilih</th>
                                <th>% Pilih → H+<?= $interval ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= $row['h_minus'] ? 'Rp '.number_format($row['h_minus'],0,',','.') : '-' ?></td>
                                <td><?= $row['selected'] ? 'Rp '.number_format($row['selected'],0,',','.') : '-' ?></td>
                                <td><?= $row['h_plus'] ? 'Rp '.number_format($row['h_plus'],0,',','.') : '-' ?></td>
                                <td><?= number_format($row['change1'],2) ?>%</td>
                                <td><?= number_format($row['change2'],2) ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
    
</body>
</html>