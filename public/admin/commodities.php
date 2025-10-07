<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Database.php';

function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$db = Database::getInstance();
$auth = new AuthController();
$user = $auth->requireRole('admin');

$sidebarPath = __DIR__ . '/includes/sidebar_admin.php'; 

function handleFileUpload($file, $existingImage = null) {
    // Path penyimpanan yang baru, sesuai dengan struktur yang lo inginkan
    $uploadDir = __DIR__ . '/../../public/uploads/commodities/';
    
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Gagal membuat direktori upload');
        }
    }

    $allowedTypes = ['image/jpeg', 'image/png'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Hanya file JPG dan PNG yang diperbolehkan');
    }

    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new Exception('Ukuran file maksimal 2MB');
    }

    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('commodity_') . '.' . $fileExt;
    $targetPath = $uploadDir . $filename;

    if ($existingImage) {
        $oldFilePath = $uploadDir . $existingImage;
        if (file_exists($oldFilePath) && is_file($oldFilePath)) {
            @unlink($oldFilePath);
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Gagal mengunggah file');
    }

    return $filename; // Yang disimpan di DB cuma nama file-nya aja
}
//check column chart_color
$checkColumn = $db->fetchOne("
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'commodities' 
    AND COLUMN_NAME = 'chart_color'
");
if (!$checkColumn) {
    $db->execute("
        ALTER TABLE commodities 
        ADD COLUMN chart_color VARCHAR(7) DEFAULT '#3498db' 
        COMMENT 'Color code in hex format (e.g., #3498db)'
    ");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
             strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $action = $_POST['action'] ?? '';
        $name = sanitizeInput($_POST['name'] ?? '');
        $unit = sanitizeInput($_POST['unit'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        
        if ($action === 'delete') {
            if (empty($id)) {
                throw new Exception('ID komoditas tidak valid.');
            }

            $commodity = $db->fetchOne("SELECT image_path FROM commodities WHERE id = ?", [$id]);
            if ($commodity) {
                if (!empty($commodity['image_path'])) {
                    $imagePath = __DIR__ . '/../../public/uploads/commodities/' . $commodity['image_path'];
                    if (file_exists($imagePath)) {
                        @unlink($imagePath);
                    }
                }
            }
            $db->execute("DELETE FROM commodities WHERE id = ?", [$id]);
            $message = 'Komoditas berhasil dihapus';
            
        } elseif ($action === 'bulk_delete') {
            $ids = $_POST['ids'] ?? [];
            if (empty($ids)) {
                throw new Exception('Tidak ada komoditas yang dipilih untuk dihapus.');
            } 
             // Hapus file gambar + record
            foreach ($ids as $bulkId) {
                $commodity = $db->fetchOne("SELECT image_path FROM commodities WHERE id = ?", [$bulkId]);
                if ($commodity && !empty($commodity['image_path'])) {
                    $imagePath = __DIR__ . '/../../public/uploads/commodities/' . $commodity['image_path'];
                    if (file_exists($imagePath)) {
                        @unlink($imagePath);
                    }
                }
            }

            $in = str_repeat('?,', count($ids) - 1) . '?';
            $db->execute("DELETE FROM commodities WHERE id IN ($in)", $ids);
            $message = 'Komoditas terpilih berhasil dihapus';
            
        } elseif ($action === 'create' || $action === 'update') {
            if (empty($name) || empty($unit)) {
                throw new Exception('Nama dan satuan komoditas harus diisi');
            }

            $imagePath = null;
            if ($action === 'update') {
                $existingCommodity = $db->fetchOne("SELECT * FROM commodities WHERE id = ?", [$id]);
                $imagePath = $existingCommodity['image_path'] ?? null;
            }
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imagePath = handleFileUpload($_FILES['image'], $imagePath);
            } elseif ($action === 'create') {
                throw new Exception('Gambar komoditas harus diunggah');
            }
            if ($action === 'update') {
                $chartColor = isset($_POST['chart_color']) ? sanitizeInput($_POST['chart_color']) : '#3498db';
                $db->execute(
                    "UPDATE commodities SET name = ?, unit = ?, image_path = ?, chart_color = ? WHERE id = ?",
                    [$name, $unit, $imagePath, $chartColor, $id]
                );
                $message = 'Komoditas berhasil diperbarui';
            } else {
                $chartColor = isset($_POST['chart_color']) ? sanitizeInput($_POST['chart_color']) : '#3498db';
                $db->execute(
                    "INSERT INTO commodities (name, unit, image_path, chart_color) VALUES (?, ?, ?, ?)",
                    [$name, $unit, $imagePath, $chartColor]
                );
                $message = 'Komoditas berhasil ditambahkan';
            }
        } else {
            throw new Exception('Aksi tidak valid');
        }
        
        if ($isAjax) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } else {
            $_SESSION['toast'] = ['type' => 'success', 'message' => $message];
            header('Location: commodities.php');
            exit;
        }

    } catch (Exception $e) {
        error_log('Commodities Error: ' . $e->getMessage());
        
        if (DEBUG_MODE) { // Pastikan DEBUG_MODE sudah didefinisikan di app.php
             ob_clean();
             http_response_code(400);
             echo json_encode(['success' => false, 'message' => $e->getMessage()]);
             exit;
        }

        $_SESSION['toast'] = ['type' => 'error', 'message' => $e->getMessage()];
        header('Location: ' . $_SERVER['HTTP_REFERER'] ?? 'commodities.php');
        exit;
    }
}

$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$where = !empty($search) ? "WHERE name LIKE ?" : '';
$params = !empty($search) ? ["%$search%"] : [];

$total = $db->fetchOne("SELECT COUNT(*) as count FROM commodities $where", $params)['count'] ?? 0;
$pages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$commodities = $db->fetchAll("SELECT * FROM commodities $where ORDER BY name ASC LIMIT $perPage OFFSET $offset", $params);

$editCommodity = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editCommodity = $db->fetchOne("SELECT * FROM commodities WHERE id = ?", [$_GET['edit']]);
    if (!$editCommodity) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Komoditas tidak ditemukan'];
        header('Location: commodities.php');
        exit();
    }
}

$pageTitle = 'Kelola Komoditas - Siaga Bapok';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
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
        
        .img-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
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
            <a class="navbar-brand ms-2" href="#">Kelola Komoditas</a>
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
    
    <?php include __DIR__ . '/includes/sidebar_admin.php'; ?>

    <main class="main-content">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h1 class="h3 mb-0">
                            <i class="bi bi-basket me-2"></i>
                            Kelola Data Komoditas
                        </h1>
                        <p class="mb-0 mt-2 opacity-75">
                            Manajemen jenis komoditas bahan pokok di Bandar Lampung
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="alert alert-<?= $_SESSION['toast']['type'] == 'error' ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
                <?= $_SESSION['toast']['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><?= $editCommodity ? 'Edit' : 'Tambah' ?> Komoditas</h5>
                    </div>
                    <div class="card-body">
                        <form id="commodityForm" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="<?= $editCommodity ? 'update' : 'create' ?>">
                            <?php if ($editCommodity): ?>
                                <input type="hidden" name="id" value="<?= $editCommodity['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Nama Komoditas <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= htmlspecialchars($editCommodity['name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="unit" class="form-label">Satuan <span class="text-danger">*</span></label>
                                <select class="form-select" id="unit" name="unit" required>
                                    <option value="">Pilih Satuan</option>
                                    <option value="KG" <?= (isset($editCommodity['unit']) && $editCommodity['unit'] === 'KG') ? 'selected' : '' ?>>Kilogram (KG)</option>
                                    <option value="L" <?= (isset($editCommodity['unit']) && $editCommodity['unit'] === 'L') ? 'selected' : '' ?>>Liter (L)</option>
                                </select>
                            </div>
                            
                            <!--color pick-->
                            <div class="mb-3">
                                <label for="chart_color" class="form-label">Warna Grafik</label>
                                <input type="color" class="form-control form-control-color" id="chart_color" 
                                    name="chart_color" 
                                    value="<?= htmlspecialchars($editCommodity['chart_color'] ?? '#3498db') ?>">
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">Gambar</label>
                                <input type="file" class="form-control" id="image" name="image" 
                                       accept="image/jpeg, image/png" 
                                       <?= $editCommodity ? '' : 'required' ?>>
                                <div class="form-text">Format: JPG/PNG, maks. 2MB</div>
                                
                                <?php if ($editCommodity && !empty($editCommodity['image_path'])): ?>
                                    <div class="mt-2" id="imagePreviewContainer">
                                        <img src="/SIAGABAPOK/Siaga_bapok/public/uploads/commodities/<?= htmlspecialchars($editCommodity['image_path']) ?>" 
                                             alt="Preview" class="img-thumbnail" id="imagePreview" style="max-width: 100%;">
                                    </div>
                                <?php else: ?>
                                    <div class="mt-2" id="imagePreviewContainer" style="display: none;">
                                        <img src="#" alt="Preview" class="img-thumbnail" id="imagePreview" style="max-width: 100%;">
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <span id="submitBtnText"><?= $editCommodity ? 'Update' : 'Simpan' ?></span>
                                    <span id="submitSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                </button>
                                
                                <?php if ($editCommodity): ?>
                                    <a href="commodities.php" class="btn btn-outline-secondary">Batal</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card mb-4">
                <div class="card-header bg-info text-white d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <h5 class="card-title mb-2 mb-md-0">Daftar Komoditas (<?= $total ?>)</h5>
                
                <div class="d-flex flex-column flex-md-row gap-2 mt-2 mt-md-0">
                    <!-- Bulk Action -->
                    <form id="bulkActionForm" method="post" action="commodities.php" class="d-flex">
                        <input type="hidden" name="action" id="bulkActionInput">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
                                Aksi Massal
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item text-danger" href="#" onclick="performBulkAction('bulk_delete')">Hapus</a></li>
                            </ul>
                        </div>
                    </form>

                    <!-- Search Form -->
                    <form class="d-flex" method="get" action="commodities.php">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" 
                                placeholder="Cari komoditas..." 
                                name="search" 
                                value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-light" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

                    <div class="card-body p-0">
                        <?php if (!empty($commodities)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="40"><input type="checkbox" id="selectAll"></th>
                                        <th width="50">#</th>
                                        <th width="80">Gambar</th>
                                        <th>Nama</th>
                                        <th>Satuan</th>
                                        <th>Warna Grafik</th>
                                        <th width="120" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($commodities as $index => $item): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="ids[]" value="<?= $item['id'] ?>" class="rowCheckbox">
                                            </td>
                                            <td><?= $offset + $index + 1 ?></td>
                                            <td>
                                                <?php if (!empty($item['image_path'])): ?>
                                                    <img src="/SIAGABAPOK/Siaga_bapok/public/uploads/commodities/<?= htmlspecialchars($item['image_path']) ?>" 
                                                        alt="<?= htmlspecialchars($item['name']) ?>" 
                                                        class="img-thumbnail">
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($item['name']) ?></td>
                                            <td><?= htmlspecialchars($item['unit']) ?></td>
                                            <td style="text-align: center;"> <span style="display: block; width: 25px; height: 25px; background-color: <?php echo htmlspecialchars($item['chart_color']); ?>; border-radius: 5px; margin: 0 auto;"></span></td>
                                            <td class="action-buttons text-nowrap text-center">
                                                <a href="?edit=<?= $item['id'] ?>" 
                                                class="btn btn-sm btn-warning" 
                                                title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button onclick="confirmDelete(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>')" 
                                                        class="btn btn-sm btn-danger" 
                                                        title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                </table>
                            </div>
                            <nav class="d-flex justify-content-center mt-3">
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
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-3 mb-2"></i>
                                <p>Tidak ada data komoditas.</p>
                            </div>
                        <?php endif; ?>
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
                    <p>Apakah Anda yakin ingin menghapus komoditas <strong id="delete-name"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Batal</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn"><i class="bi bi-trash me-1"></i>Ya, Hapus</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Setup sidebar toggle
        function setupSidebarToggle() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (!sidebar || !sidebarToggle) { return; }

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
        
        // Image preview for form
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const removeImageCheckbox = document.getElementById('removeImage');

        if (imageInput && imagePreview && imagePreviewContainer) {
            imageInput.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreviewContainer.style.display = 'block';
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
        if (removeImageCheckbox) {
            removeImageCheckbox.addEventListener('change', function() {
                imagePreview.style.opacity = this.checked ? '0.5' : '1';
                if (this.checked) imageInput.value = '';
            });
        }

        function performBulkAction(action) {
            const checkboxes = document.querySelectorAll('.rowCheckbox:checked');
            if (checkboxes.length === 0) {
                alert("Pilih minimal satu komoditas untuk dihapus!");
                return;
            }
            if (!confirm("Apakah Anda yakin ingin menghapus data terpilih?")) return;

            const form = document.getElementById('bulkActionForm');
            document.getElementById('bulkActionInput').value = action;

            // Bikin hidden input ids[] ke form
            checkboxes.forEach(cb => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'ids[]';
                hidden.value = cb.value;
                form.appendChild(hidden);
            });

            form.submit();
        }

        document.getElementById('selectAll').addEventListener('change', function() {
            document.querySelectorAll('.rowCheckbox').forEach(cb => cb.checked = this.checked);
        });

        // Handle delete via modal
        function confirmDelete(id, name) {
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            document.getElementById('delete-name').textContent = name;
            document.getElementById('confirmDeleteBtn').onclick = function() {
                deleteCommodity(id);
            };
            deleteModal.show();
        }

        async function deleteCommodity(id) {
            try {
                const response = await fetch('commodities.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: `action=delete&id=${id}`
                });
                const result = await response.json();
                if (result.success) {
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: result.message, timer: 1500, showConfirmButton: false }).then(() => window.location.reload());
                } else {
                    throw new Error(result.message || 'Gagal menghapus komoditas');
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: error.message || 'Terjadi kesalahan saat menghapus komoditas' });
            }
        }
        
        // Form submission with AJAX
        document.addEventListener('DOMContentLoaded', function() {
            setupSidebarToggle();
            
            const form = document.getElementById('commodityForm');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const submitBtn = document.getElementById('submitBtn');
                    const submitBtnText = document.getElementById('submitBtnText');
                    const submitSpinner = document.getElementById('submitSpinner');
                    
                    submitBtn.disabled = true;
                    submitBtnText.textContent = 'Menyimpan...';
                    submitSpinner.classList.remove('d-none');
                    
                    try {
                        const formData = new FormData(form);
                        const response = await fetch('commodities.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        const contentType = response.headers.get('content-type');
                        
                        if (!contentType || !contentType.includes('application/json')) {
                            const text = await response.text();
                            console.error('Response is not JSON:', text);
                            throw new Error('Terjadi kesalahan pada server. Silakan coba lagi.');
                        }
                        
                        const result = await response.json();
                        if (result.success) {
                            Swal.fire({ icon: 'success', title: 'Berhasil', text: result.message, timer: 1500, showConfirmButton: false }).then(() => window.location.href = result.redirect || 'commodities.php');
                        } else {
                            throw new Error(result.message || 'Terjadi kesalahan');
                        }
                    } catch (error) {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: error.message || 'Terjadi kesalahan saat menyimpan data' });
                    } finally {
                        submitBtn.disabled = false;
                        submitBtnText.textContent = form.querySelector('input[name="action"]').value === 'update' ? 'Update' : 'Simpan';
                        submitSpinner.classList.add('d-none');
                    }
                });
            }
            
            <?php if (isset($_SESSION['toast'])): ?>
                Swal.fire({
                    icon: '<?= $_SESSION['toast']['type'] ?>',
                    title: '<?= addslashes($_SESSION['toast']['message']) ?>',
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