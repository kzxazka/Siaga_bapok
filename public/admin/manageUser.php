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

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'create') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];
        $market_assigned = ($role === 'uptd') ? trim($_POST['market_assigned']) : null;
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        try {
            $db->execute(
                "INSERT INTO users (username, email, password, full_name, role, market_assigned, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$username, $email, $password, $full_name, $role, $market_assigned, $is_active]
            );
            $_SESSION['success'] = 'User berhasil ditambahkan';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal menambah user: ' . $e->getMessage();
        }
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];
        $market_assigned = ($role === 'uptd') ? trim($_POST['market_assigned']) : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $params = [$username, $email, $full_name, $role, $market_assigned, $is_active, $id];

        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $params = [$username, $email, $password, $full_name, $role, $market_assigned, $is_active, $id];
            $sql = "UPDATE users SET username=?, email=?, password=?, full_name=?, role=?, market_assigned=?, is_active=? WHERE id=?";
        } else {
            $sql = "UPDATE users SET username=?, email=?, full_name=?, role=?, market_assigned=?, is_active=? WHERE id=?";
        }

        try {
            $db->execute($sql, $params);
            $_SESSION['success'] = 'User berhasil diperbarui';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal memperbarui user: ' . $e->getMessage();
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $db->execute("DELETE FROM users WHERE id = ? AND role IN ('admin','uptd')", [$id]);
            $_SESSION['success'] = 'User berhasil dihapus';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal menghapus user: ' . $e->getMessage();
        }
    }

    header('Location: manageUser.php');
    exit;
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

// Edit mode
$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$editId]);
}

$pageTitle = 'Manajemen User - Admin Siaga Bapok';
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

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

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
                            <input type="text" name="market_assigned" class="form-control" value="<?= $editUser['market_assigned'] ?? ''; ?>">
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
                                    <td><?= htmlspecialchars($u['role']); ?></td>
                                    <td><?= htmlspecialchars($u['market_assigned'] ?? '-'); ?></td>
                                    <td><?= $u['is_active'] ? 'Aktif' : 'Nonaktif'; ?></td>
                                    <td>
                                        <a href="?edit=<?= $u['id']; ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $u['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Hapus user ini?')"><i class="bi bi-trash"></i></button>
                                        </form>
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
</div>

<script>
function toggleMarketAssigned(select) {
    document.getElementById('marketAssignedField').style.display = select.value === 'uptd' ? 'block' : 'none';
}
</script>
</body>
</html>
