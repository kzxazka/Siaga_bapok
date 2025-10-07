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

// Path to the consolidated sidebar file
// Ensure the path is always set, regardless of role.
// We assume the admin sidebar is the default one to include.
$sidebarPath = __DIR__ . '/includes/sidebar_admin.php'; 

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

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

    if ($action === 'create') {
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $full_name = sanitizeInput($_POST['full_name']);
        $role = sanitizeInput($_POST['role']);
        $market_assigned = ($role === 'uptd') ? sanitizeInput($_POST['market_assigned']) : null;
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        try {
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
        } catch (Exception $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'title' => 'Gagal!',
                'message' => 'Gagal menambahkan user: ' . $e->getMessage(),
                'icon' => 'error'
            ];
            $redirect = false;
        }
    } 
    
    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $full_name = sanitizeInput($_POST['full_name']);
        $role = sanitizeInput($_POST['role']);
        $market_assigned = ($role === 'uptd') ? sanitizeInput($_POST['market_assigned']) : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $params = [$username, $email, $full_name, $role, $market_assigned, $is_active, $id];
        $sql = "UPDATE users SET username=?, email=?, full_name=?, role=?, market_assigned=?, is_active=? WHERE id=?";

        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            array_splice($params, 2, 0, $password);
            $sql = "UPDATE users SET username=?, email=?, password=?, full_name=?, role=?, market_assigned=?, is_active=? WHERE id=?";
        }

        try {
            $db->execute($sql, $params);
            $_SESSION['toast'] = [
                'type' => 'success',
                'title' => 'Berhasil!',
                'message' => 'User berhasil diperbarui',
                'icon' => 'success'
            ];
        } catch (Exception $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'title' => 'Gagal!',
                'message' => 'Gagal memperbarui user: ' . $e->getMessage(),
                'icon' => 'error'
            ];
            $redirect = false;
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $db->execute("DELETE FROM users WHERE id = ? AND role IN ('admin','uptd')", [$id]);
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
$users = $db->fetchAll("SELECT u.*, p.nama_pasar FROM users u LEFT JOIN pasar p ON u.market_assigned = p.id_pasar $where ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset", $params);
$markets = $db->fetchAll("SELECT id_pasar, nama_pasar FROM pasar ORDER BY nama_pasar");

// Clear any previous output
if (ob_get_level() > 0) {
    ob_clean();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" id="sidebarToggle" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list" style="font-size: 1.5rem;"></i>
            </button>
            <a class="navbar-brand ms-2" href="#">Manajemen User</a>
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
            
        <?php if (isset($_SESSION['toast'])): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const toast = <?php echo json_encode($_SESSION['toast']); ?>;
                Swal.fire({
                    icon: toast.icon || 'info',
                    title: toast.title || 'Info',
                    text: toast.message,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 5000,
                    timerProgressBar: true
                });
            });
            </script>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
        
        <div class="row">
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
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($editUser['username'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editUser['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label>Nama Lengkap</label>
                                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($editUser['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label>Password <?= $editUser ? '(Kosongkan jika tidak diubah)' : ''; ?></label>
                                <input type="password" name="password" class="form-control">
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
                            <button type="submit" class="btn btn-success"><i class="bi bi-save me-2"></i><?= $editUser ? 'Update' : 'Simpan'; ?></button>
                            <?php if ($editUser): ?>
                                <a href="manageUser.php" class="btn btn-secondary"><i class="bi bi-x-lg me-2"></i>Batal</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

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
                                        <td><?= htmlspecialchars($u['nama_pasar'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($u['is_active']): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?edit=<?= $u['id']; ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $u['id']; ?>, '<?= addslashes($u['username']); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

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
    </main>

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus pengguna <strong id="delete-username"></strong>?</p>
                    <div class="alert alert-warning"><i class="bi bi-warning me-2"></i><strong>Peringatan:</strong> Data yang sudah dihapus tidak dapat dikembalikan.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Batal</button>
                    <form method="POST" id="deleteForm" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteId">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Ya, Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleMarketAssigned(select) {
            document.getElementById('marketAssignedField').style.display = select.value === 'uptd' ? 'block' : 'none';
        }

        function confirmDelete(id, username) {
            document.getElementById('deleteId').value = id;
            document.getElementById('delete-username').textContent = username;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

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
            
            <?php if (isset($_SESSION['toast'])): ?>
                const toast = <?php echo json_encode($_SESSION['toast']); ?>;
                Swal.fire({
                    icon: toast.icon || 'info',
                    title: toast.title || 'Info',
                    text: toast.message,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 5000,
                    timerProgressBar: true
                });
            <?php unset($_SESSION['toast']); endif; ?>
        });
    </script>
</body>
</html>
<?php
// Flush the output buffer
ob_end_flush();
?>