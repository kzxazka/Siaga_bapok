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


// Ambil data pasar & komoditas
$markets = $db->fetchAll("SELECT nama_pasar FROM pasar ORDER BY nama_pasar");
$commodities = $db->fetchAll("SELECT id, name, unit FROM commodities ORDER BY name");

// Handle input harga (untuk UPTD saja)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'uptd') {
    $commodityId = (int) $_POST['commodity_id'];
    $price = (float) $_POST['price'];
    $market = trim($_POST['market_name']);
    $notes = trim($_POST['notes'] ?? '');
    $uptdId = $_SESSION['user_id'];

    try {
        $db->execute(
                "INSERT INTO prices (commodity_id, price, market_name, uptd_user_id, notes, status)
                VALUES (?, ?, ?, ?, ?, 'pending')",
                [$commodityId, $price, $market, $uptdId, $notes]
            );
        $_SESSION['success'] = "Harga berhasil diinput dan menunggu persetujuan admin.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal menginput harga: " . $e->getMessage();
    }
    header("Location: uploadHarga.php");
    exit;
}

// Query tabel harga
// Ambil data pasar & komoditas
if ($role === 'uptd') {
    $markets = $db->fetchAll("
        SELECT id_pasar, nama_pasar 
        FROM pasar 
        WHERE id_pasar = (SELECT market_assigned FROM users WHERE id = ?)
    ", [$_SESSION['user_id']]);
} else {
    $markets = $db->fetchAll("SELECT id_pasar, nama_pasar FROM pasar ORDER BY nama_pasar");
}
$commodities = $db->fetchAll("SELECT id, name, unit FROM commodities ORDER BY name");
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
                            Upload Harga
                        </h1>
                        <p class="mb-0 mt-2 opacity-75">
                            Upload harga komoditas pasar untuk persetujuan admin. Pastikan data yang diinput sudah benar.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    <div class="row g-4">
        <?php if ($role === 'uptd'): ?>
        <!-- Form Input Harga -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-plus-circle"></i> Input Harga Baru
                </div>
                <div class="card-body">
                    <?php if (!empty($_SESSION['success'])): ?>
                        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                    <?php elseif (!empty($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>

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
                                <input type="number" name="price" class="form-control" min="1" max="99999" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pilih Pasar</label>
                            <select name="market_name" class="form-select" required>
                            <option value="" selected disabled>-- Pilih --</option>
                            <?php foreach ($markets as $m): ?>
                                <option value="<?= htmlspecialchars($m['nama_pasar']) ?>">
                                    <?= htmlspecialchars($m['nama_pasar']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan (Opsional)</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <button class="btn btn-primary w-100"><i class="bi bi-send"></i> Simpan</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabel Harga -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-table"></i> Daftar Harga <?= $role === 'uptd' ? "Anda" : "Semua UPTD" ?>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <?php if ($role === 'admin'): ?><th>UPTD</th><?php endif; ?>
                                <th>Komoditas</th>
                                <th>Harga</th>
                                <th>Pasar</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <?php if ($role === 'admin'): ?><th>Aksi</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($prices)): ?>
                            <?php foreach ($prices as $p): ?>
                            <tr>
                                <?php if ($role === 'admin'): ?><td><?= htmlspecialchars($p['uptd_name']) ?></td><?php endif; ?>
                                <td><?= htmlspecialchars($p['commodity_name']) ?> (<?= htmlspecialchars($p['unit']) ?>)</td>
                                <td>Rp <?= number_format($p['price'], 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars($p['market_name']) ?></td>
                                <td><?= date('d-m-Y', strtotime($p['created_at'])) ?></td>
                                <td class="status-<?= strtolower($p['status']) ?>"><?= ucfirst($p['status']) ?></td>
                                <?php if ($role === 'admin'): ?>
                                <td>
                                    <a href="approve.php?id=<?= $p['id'] ?>&status=approved" class="btn btn-success btn-sm"><i class="bi bi-check"></i></a>
                                    <a href="approve.php?id=<?= $p['id'] ?>&status=rejected" class="btn btn-danger btn-sm"><i class="bi bi-x"></i></a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="<?= $role === 'admin' ? 7 : 6 ?>" class="text-center">Belum ada data harga</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
    
</body>
</html>