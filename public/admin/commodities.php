<?php
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../config/database.php';

// Include sidebar based on user role
if ($_SESSION['role'] === 'admin') {
    include __DIR__ . '/sidebar_admin.php';
} elseif ($_SESSION['role'] === 'uptd') {
    include __DIR__ . '/sidebar_uptd.php';
}

$auth = new AuthController();
$user = $auth->requireRole('admin');
$db = new Database();

// Create commodities table if not exists
$db->query("CREATE TABLE IF NOT EXISTS commodities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'create') {
        $name = sanitizeInput($_POST['name']);
        $unit = sanitizeInput($_POST['unit']);

        try {
            $db->execute("INSERT INTO commodities (name, unit) VALUES (?, ?)", [$name, $unit]);
            $_SESSION['success'] = 'Komoditas berhasil ditambahkan';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal menambah komoditas: ' . $e->getMessage();
        }
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $name = sanitizeInput($_POST['name']);
        $unit = sanitizeInput($_POST['unit']);

        try {
            $db->execute("UPDATE commodities SET name = ?, unit = ? WHERE id = ?", [$name, $unit, $id]);
            $_SESSION['success'] = 'Komoditas berhasil diperbarui';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal memperbarui komoditas: ' . $e->getMessage();
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $db->execute("DELETE FROM commodities WHERE id = ?", [$id]);
            $_SESSION['success'] = 'Komoditas berhasil dihapus';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal menghapus komoditas: ' . $e->getMessage();
        }
    }

    header('Location: commodities.php');
    exit;
}


// Get all commodities (with search & pagination)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$where = $search ? "WHERE name LIKE ?" : '';
$params = $search ? ["%$search%"] : [];
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM commodities $where", $params)['cnt'];
$pages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;
$commodities = $db->fetchAll("SELECT * FROM commodities $where ORDER BY name ASC LIMIT $perPage OFFSET $offset", $params);

// Edit mode
$editCommodity = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editCommodity = $db->fetchOne("SELECT * FROM commodities WHERE id = ?", [$editId]);
}

$pageTitle = 'Kelola Komoditas - Admin Siaga Bapok';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #000080;
            --light-green: #d4edda;
            --dark-green: #3232b9ff;
            --sidebar-width: 250px;
        }
        body { background-color: #f8f9fa; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: var(--sidebar-width); background: linear-gradient(180deg, var(--primary-green) 0%, var(--dark-green) 100%); color: white; z-index: 1000; overflow-y: auto; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 0.75rem 1rem; border-radius: 0.375rem; margin: 0.25rem 0.5rem; transition: all 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255,255,255,0.1); color: white; }
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s; } .sidebar.show { transform: translateX(0); } .main-content { margin-left: 0; padding: 1rem; } }
    </style>
</head>
<body>

<div class="main-content">
    <button class="btn btn-primary d-md-none mb-3" type="button" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h1 class="h3 mb-0"><i class="bi bi-basket me-2"></i>Kelola Data Komoditas</h1>
                    <p class="mb-0 mt-2 opacity-75">Manajemen jenis komoditas bahan pokok di Bandar Lampung</p>
                </div>
            </div>
        </div>
    </div>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-plus-circle me-2"></i><?= $editCommodity ? 'Edit Komoditas' : 'Tambah Komoditas'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $editCommodity ? 'update' : 'create'; ?>">
                        <?php if ($editCommodity): ?>
                            <input type="hidden" name="id" value="<?= $editCommodity['id']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="name" class="form-label"><i class="bi bi-basket me-1"></i>Nama Komoditas *</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= $editCommodity ? htmlspecialchars($editCommodity['name']) : ''; ?>" placeholder="Contoh: Beras, Gula Pasir, Minyak Goreng" required>
                            <div class="form-group">
                                <label for="unit">Satuan</label>
                                <select class="form-control" id="unit" name="unit" required>
                                    <option value="">-- Pilih Satuan --</option>
                                    <option value="KG" <?= isset($editCommodity['unit']) && $editCommodity['unit'] === 'KG' ? 'selected' : '' ?>>Kilogram</option>
                                    <option value="L" <?= isset($editCommodity['unit']) && $editCommodity['unit'] === 'L' ? 'selected' : '' ?>>Liter</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success"><i class="bi bi-save me-2"></i><?= $editCommodity ? 'Update Komoditas' : 'Simpan Komoditas'; ?></button>
                            <?php if ($editCommodity): ?>
                                <a href="commodities.php" class="btn btn-secondary"><i class="bi bi-x-lg me-2"></i>Batal Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-list-ul me-2"></i>Daftar Komoditas (<?= $total ?>)</h5>
                    <form class="d-flex" method="get" action="commodities.php">
                        <input class="form-control form-control-sm me-2" type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari komoditas...">
                        <button class="btn btn-light btn-sm" type="submit"><i class="bi bi-search"></i></button>
                    </form>
                </div>
                <div class="card-body p-0">
    <?php if (!empty($commodities)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th width="5%">No</th>
                        <th>Nama Komoditas</th>
                        <th>Satuan</th>
                        <th width="20%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commodities as $i => $c): ?>
                        <tr>
                            <td><?= ($offset + $i + 1) ?></td>
                            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                            <td>
                                <?= $c['unit'] === 'KG' ? 'Kilogram' : ($c['unit'] === 'L' ? 'Liter' : $c['unit']) ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?= $c['id'] ?>" class="btn btn-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger"
                                            onclick="deleteCommodity(<?= $c['id'] ?>, '<?= addslashes($c['name']) ?>')"
                                            title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
            <div class="d-flex flex-column flex-lg-row justify-content-center align-items-center gap-3 mt-3">
                <div class="text-muted">
                    Menampilkan halaman <?= $page ?> dari <?= $pages ?>
                </div>
                <nav>
                    <ul class="pagination mb-0">
                        <?php for ($p = 1; $p <= $pages; $p++): ?>
                            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $p ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>


    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-basket fs-1 text-muted mb-3"></i>
            <h5 class="text-muted">Belum ada data komoditas</h5>
            <p class="text-muted">Tambahkan data komoditas menggunakan form di sebelah kiri.</p>
        </div>
    <?php endif; ?>
</div>

            </div>
        </div>
    </div>
    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus komoditas <strong id="commodityName"></strong>?</p>
                    <div class="alert alert-warning"><i class="bi bi-warning me-2"></i><strong>Peringatan:</strong> Data yang sudah dihapus tidak dapat dikembalikan.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Batal</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteId">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Ya, Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('show'); }
function deleteCommodity(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('commodityName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('show')) {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 150);
        }
    });
}, 5000);
</script>
</body>
</html>
