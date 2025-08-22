<?php
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Price.php';
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

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $nama_pasar = sanitizeInput($_POST['nama_pasar']);
        $alamat = sanitizeInput($_POST['alamat']);
        $keterangan = sanitizeInput($_POST['keterangan'] ?? '');
        
        $sql = "INSERT INTO pasar (nama_pasar, alamat, keterangan) VALUES (?, ?, ?)";
        try {
            $db->execute($sql, [$nama_pasar, $alamat, $keterangan]);
            $_SESSION['success'] = 'Pasar berhasil ditambahkan';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal menambahkan pasar: ' . $e->getMessage();
        }
    }
    
    if ($action === 'update') {
        $id = (int)$_POST['id_pasar'];
        $nama_pasar = sanitizeInput($_POST['nama_pasar']);
        $alamat = sanitizeInput($_POST['alamat']);
        $keterangan = sanitizeInput($_POST['keterangan'] ?? '');
        
        $sql = "UPDATE pasar SET nama_pasar = ?, alamat = ?, keterangan = ? WHERE id_pasar = ?";
        try {
            $db->execute($sql, [$nama_pasar, $alamat, $keterangan, $id]);
            $_SESSION['success'] = 'Pasar berhasil diperbarui';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal memperbarui pasar: ' . $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id_pasar'];
        
        $sql = "DELETE FROM pasar WHERE id_pasar = ?";
        try {
            $db->execute($sql, [$id]);
            $_SESSION['success'] = 'Pasar berhasil dihapus';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal menghapus pasar: ' . $e->getMessage();
        }
    }
    
    header('Location: markets.php');
    exit;
}

// Create markets table if not exists
$createTableSql = "CREATE TABLE IF NOT EXISTS pasar (
    id_pasar INT PRIMARY KEY AUTO_INCREMENT,
    nama_pasar VARCHAR(100) NOT NULL,
    alamat TEXT,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$db->query($createTableSql);

// Get all markets
// Search & Pagination setup
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;

$where = $search ? "WHERE nama_pasar LIKE ?" : "";
$params = $search ? ["%$search%"] : [];

// Hitung total data
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM pasar $where", $params)['cnt'];
$pages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

// Ambil data sesuai paging
$markets = $db->fetchAll("SELECT * FROM pasar $where ORDER BY nama_pasar ASC LIMIT $perPage OFFSET $offset", $params);

// Get market being edited
$editMarket = null;
if (!empty($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editMarket = $db->fetchOne("SELECT * FROM pasar WHERE id_pasar = ?", [$editId]);
}

$pageTitle = 'Kelola Pasar - Admin Siaga Bapok';
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
        
        body {
            background-color: #f8f9fa;
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
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <div class="main-content">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h1 class="h3 mb-0"><i class="bi bi-shop me-2"></i>Kelola Data Pasar</h1>
                    <p class="mb-0 mt-2 opacity-75">Manajemen data pasar tradisional di Bandar Lampung</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white"><?php echo $editMarket ? 'Edit Pasar' : 'Tambah Pasar Baru'; ?></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?php echo $editMarket ? 'update' : 'create'; ?>">
                        <?php if ($editMarket): ?>
                            <input type="hidden" name="id_pasar" value="<?php echo $editMarket['id_pasar']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="nama_pasar">Nama Pasar *</label>
                            <input type="text" class="form-control" name="nama_pasar" value="<?php echo $editMarket['nama_pasar'] ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="alamat">Alamat *</label>
                            <textarea class="form-control" name="alamat" required><?php echo $editMarket['alamat'] ?? ''; ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="keterangan">Keterangan</label>
                            <textarea class="form-control" name="keterangan"><?php echo $editMarket['keterangan'] ?? ''; ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><?php echo $editMarket ? 'Update' : 'Simpan'; ?></button>
                        <?php if ($editMarket): ?>
                            <a href="markets.php" class="btn btn-secondary">Batal</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between">
                    <span>Daftar Pasar (<?php echo $total; ?>)</span>
                    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($markets)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Pasar</th>
                                        <th>Alamat</th>
                                        <th>Keterangan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($markets as $index => $m): ?>
                                        <tr>
                                            <td><?= $offset + $index + 1; ?></td>
                                            <td><strong><?= htmlspecialchars($m['nama_pasar']); ?></strong></td>
                                            <td><small><?= htmlspecialchars($m['alamat']); ?></small></td>
                                            <td><small class="text-muted"><?= $m['keterangan'] ? htmlspecialchars($m['keterangan']) : '-'; ?></small></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?edit=<?= $m['id_pasar']; ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a>
                                                    <button type="button" class="btn btn-danger btn-sm"
                                                            onclick="deleteMarket(<?= $m['id_pasar']; ?>, '<?= addslashes($m['nama_pasar']); ?>')">
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
                    <p>Apakah Anda yakin ingin menghapus pasar <strong id="nama_pasar"></strong>?</p>
                    <div class="alert alert-warning"><i class="bi bi-warning me-2"></i><strong>Peringatan:</strong> Data yang sudah dihapus tidak dapat dikembalikan.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Batal</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_pasar" id="deleteId">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Ya, Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
                    <?php else: ?>
                        <div class="p-3">Belum ada data pasar</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle sidebar for mobile
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }
        
        // Delete market function
        function deleteMarket(id, name) {
            document.getElementById('deleteId').value = id;
            document.getElementById('nama_pasar').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                }
            });
        }, 5000);
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const namaParar = document.getElementById('nama_pasar').value.trim();
            const alamat = document.getElementById('alamat').value.trim();
            
            if (!namaParar || !alamat) {
                e.preventDefault();
                alert('Nama pasar dan alamat harus diisi!');
                return;
            }
        });
    </script>
</body>
</html>
