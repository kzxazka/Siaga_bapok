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
$user = $auth->requireRole('admin');
$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        $db->execute(
            "UPDATE prices SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?",
            [$user['id'], $id] // simpan id admin, bukan username
        );
        $_SESSION['success'] = "Data harga berhasil di-approve.";
    } elseif ($action === 'reject') {
        $db->execute(
            "UPDATE prices SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=?",
            [$user['id'], $id]
        );
        $_SESSION['success'] = "Data harga berhasil ditolak.";
    }

    header('Location: approve.php');
    exit;
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan Data Harga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #000080;
            --dark-green: #3232b9ff;
            --sidebar-width: 250px;
        }
        body { background-color: #f8f9fa; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: var(--sidebar-width); background: linear-gradient(180deg, var(--primary-green) 0%, var(--dark-green) 100%); color: white; overflow-y: auto; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 0.75rem 1rem; border-radius: 0.375rem; margin: 0.25rem 0.5rem; transition: all 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255,255,255,0.1); color: white; }
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
    </style>
</head>
<body>
<div class="main-content">
    <h3><i class="bi bi-check-circle me-2"></i>Persetujuan Data Harga</h3>
    <p class="text-muted">Hanya menampilkan data harga yang diinput UPTD dan belum di-approve.</p>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (!empty($pendingData)): ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>No</th>
                    <th>Komoditas</th>
                    <th>Pasar</th>
                    <th>Harga</th>
                    <th>Tanggal</th>
                    <th>Diinput oleh</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingData as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['commodity_name']) ?></td>
                    <td><?= htmlspecialchars($row['market_name']) ?></td>
                    <td>Rp <?= number_format($row['price'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td><?= htmlspecialchars($row['uploaded_by']) ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button name="action" value="approve" class="btn btn-success btn-sm">
                                <i class="bi bi-check"></i> Approve
                            </button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button name="action" value="reject" class="btn btn-danger btn-sm">
                                <i class="bi bi-x"></i> Tolak
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="alert alert-info">Tidak ada data harga pending untuk disetujui.</div>
    <?php endif; ?>
</div>
</body>
</html>
