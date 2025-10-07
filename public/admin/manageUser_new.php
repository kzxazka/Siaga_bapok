<?php
// Pastikan tidak ada output sebelum session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Database.php';

$auth = new AuthController();
$user = $auth->requireRole('admin');
$db = Database::getInstance();

// Edit mode
$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$editId]);
}

$pageTitle = 'Manajemen User - Admin Siaga Bapok';

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $redirect = true;

    if ($action === 'create' || $action === 'update') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];
        $market_assigned = ($role === 'uptd') ? trim($_POST['market_assigned']) : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            if ($action === 'create') {
                $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $db->execute(
                    "INSERT INTO users (username, email, password, full_name, role, market_assigned, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$username, $email, $password, $full_name, $role, $market_assigned, $is_active]
                );
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'title' => 'Berhasil!',
                    'message' => 'User berhasil ditambahkan',
                    'icon' => 'success'
                ];
            } else {
                // Update existing user
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
                    $db->execute(
                        "UPDATE users SET username=?, email=?, password=?, full_name=?, role=?, market_assigned=?, is_active=? WHERE id=?",
                        [$username, $email, $password, $full_name, $role, $market_assigned, $is_active, $_POST['id']]
                    );
                } else {
                    $db->execute(
                        "UPDATE users SET username=?, email=?, full_name=?, role=?, market_assigned=?, is_active=? WHERE id=?",
                        [$username, $email, $full_name, $role, $market_assigned, $is_active, $_POST['id']]
                    );
                }
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'title' => 'Berhasil!',
                    'message' => 'User berhasil diperbarui',
                    'icon' => 'success'
                ];
            }
        } catch (Exception $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'title' => 'Gagal!',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'icon' => 'error'
            ];
            $redirect = false;
        }
    } elseif ($action === 'delete' && isset($_POST['id'])) {
        try {
            $db->execute("DELETE FROM users WHERE id = ?", [$_POST['id']]);
            $_SESSION['toast'] = [
                'type' => 'success',
                'title' => 'Berhasil!',
                'message' => 'User berhasil dihapus',
                'icon' => 'success'
            ];
        } catch (Exception $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'title' => 'Gagal!',
                'message' => 'Gagal menghapus user: ' . $e->getMessage(),
                'icon' => 'error'
            ];
            $redirect = false;
        }
    }

    if ($redirect) {
        header('Location: manageUser.php');
        exit;
    }
}

// Search & Pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$where = $search ? "WHERE role IN ('admin','uptd') AND username LIKE ?" : "WHERE role IN ('admin','uptd')";
$params = $search ? ["%$search%"] : [];
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM users $where", $params)['cnt'];
$pages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;
$users = $db->fetchAll("SELECT * FROM users $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);
$markets = $db->fetchAll("SELECT id_pasar, nama_pasar FROM pasar ORDER BY nama_pasar");

// Mulai output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../public/assets/images/BANDAR LAMPUNG ICON.png">
    <style>
        :root {
            --primary-color: #1e2a56;
            --secondary-color: #f8f9fa;
            --accent-color: #0d6efd;
        }
        
        body {
            background-color: #f5f7fa;
        }
        
        .sidebar {
            min-height: 100vh;
            background: var(--primary-color);
            color: white;
            padding: 0;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            margin: 0.25rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            transition: all 0.3s;
        }
        
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem 0.75rem 0 0 !important;
        }
        
        .table th {
            font-weight: 600;
            border-top: none;
            padding: 1rem;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
        }
        
        .btn-primary {
            background-color: var(--accent-color);
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
        }
        
        .btn-outline-primary {
            color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .form-control, .form-select {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        /* Responsive */
        @media (max-width: 991.98px) {
            .sidebar {
                margin-left: -250px;
                position: fixed;
                z-index: 1000;
                width: 250px;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <?php include __DIR__ . '/sidebar_admin.php'; ?>
        <?php elseif ($_SESSION['role'] === 'uptd'): ?>
            <?php include __DIR__ . '/sidebar_uptd.php'; ?>
        <?php endif; ?>
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <!-- Notifikasi Toast -->
            <?php if (isset($_SESSION['toast'])): ?>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const toast = <?php echo json_encode($_SESSION['toast']); ?>;
                    
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 5000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer);
                            toast.addEventListener('mouseleave', Swal.resumeTimer);
                        }
                    });

                    Toast.fire({
                        icon: toast.icon || 'info',
                        title: toast.title || 'Info',
                        text: toast.message,
                        showConfirmButton: !!toast.showButton,
                        confirmButtonText: toast.buttonText || 'OK',
                        confirmButtonColor: toast.type === 'error' ? '#d33' : '#3085d6'
                    });
                });
                </script>
                <?php unset($_SESSION['toast']); ?>
            <?php endif; ?>
            
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h1 class="h3 mb-0">
                                <i class="bi bi-people me-2"></i>
                                Kelola Data Pengguna
                            </h1>
                            <p class="mb-0 mt-2 opacity-75">
                                Manajemen data Pengguna SIAGABAPOK
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Form -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white"><?= $editUser ? 'Edit Pengguna' : 'Tambah Pengguna'; ?></div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create'; ?>">
                                <?php if ($editUser): ?>
                                    <input type="hidden" name="id" value="<?= $editUser['id']; ?>">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label>Username</label>
                                    <input type="text" name="username" class="form-control" value="<?= $editUser['username'] ?? ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= $editUser['email'] ?? ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label>Nama Lengkap</label>
                                    <input type="text" name="full_name" class="form-control" value="<?= $editUser['full_name'] ?? ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label>Password <?= $editUser ? '(Kosongkan jika tidak diubah)' : ''; ?></label>
                                    <input type="password" name="password" class="form-control" <?= !$editUser ? 'required' : ''; ?>>
                                </div>
                                <div class="mb-3">
                                    <label>Role</label>
                                    <select name="role" class="form-control" onchange="toggleMarketAssigned(this)">
                                        <option value="admin" <?= isset($editUser['role']) && $editUser['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="uptd" <?= isset($editUser['role']) && $editUser['role'] === 'uptd' ? 'selected' : ''; ?>>UPTD</option>
                                    </select>
                                </div>
                                <div class="mb-3" id="marketAssignedField" style="<?= isset($editUser['role']) && $editUser['role'] === 'uptd' ? '' : 'display:none;'; ?>">
                                    <label>Pasar Ditugaskan</label>
                                    <select name="market_assigned" class="form-select">
                                        <option value="" disabled selected>-- Pilih Pasar --</option>
                                        <?php foreach ($markets as $m): ?>
                                            <option value="<?= $m['id_pasar'] ?>" 
                                                <?= isset($editUser['market_assigned']) && $editUser['market_assigned'] == $m['id_pasar'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($m['nama_pasar']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" name="is_active" class="form-check-input" id="isActive" <?= isset($editUser['is_active']) && $editUser['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isActive">Aktif</label>
                                </div>
                                <button type="submit" class="btn btn-success"><?= $editUser ? 'Update' : 'Simpan'; ?></button>
                                <?php if ($editUser): ?>
                                    <a href="manageUser.php" class="btn btn-secondary">Batal</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-info text-white d-flex justify-content-between">
                            <span>Daftar Pengguna (<?= $total ?>)</span>
                            <form method="get" class="d-flex">
                                <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Cari username..." value="<?= htmlspecialchars($search); ?>">
                                <button class="btn btn-light btn-sm" type="submit"><i class="bi bi-search"></i></button>
                            </form>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Nama Lengkap</th>
                                        <th>Role</th>
                                        <th>Pasar</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $i => $u): ?>
                                        <tr>
                                            <td><?= $offset + $i + 1; ?></td>
                                            <td><?= htmlspecialchars($u['username']); ?></td>
                                            <td><?= htmlspecialchars($u['email']); ?></td>
                                            <td><?= htmlspecialchars($u['full_name']); ?></td>
                                            <td><?= htmlspecialchars(ucfirst($u['role'])); ?></td>
                                            <td><?= $u['market_assigned'] ? htmlspecialchars($u['market_assigned']) : '-' ; ?></td>
                                            <td>
                                                <span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?= $u['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?edit=<?= $u['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus user ini?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $u['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($pages > 1): ?>
                            <div class="card-footer">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $page - 1; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $i; ?>">
                                                    <?= $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $page + 1; ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function toggleMarketAssigned(select) {
    document.getElementById('marketAssignedField').style.display = select.value === 'uptd' ? 'block' : 'none';
}

// Inisialisasi tampilan market assigned saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.querySelector('select[name="role"]');
    if (roleSelect) {
        toggleMarketAssigned(roleSelect);
    }
});
</script>

</body>
</html>
<?php
// Akhir output buffering dan kirim output
ob_end_flush();
?>
