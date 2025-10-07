<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Jakarta');

// Load konfigurasi aplikasi
require_once __DIR__ . '/../../src/config/app.php'; 

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Database.php';
require_once __DIR__ . '/../../src/models/BaseModel.php';
require_once __DIR__ . '/../../src/models/Slider.php';

function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$db = Database::getInstance();
$auth = new AuthController();
$user = $auth->requireRole('admin');
$slider = new Slider();

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        
        if ($action === 'delete') {
            if (empty($id)) {
                throw new Exception('ID slider tidak valid.');
            }
            $slider->delete($id);
            $message = 'Slider berhasil dihapus';
            
        } elseif ($action === 'add' || $action === 'edit') {
            $sliderData = [
                'title' => sanitizeInput($_POST['title'] ?? ''),
                'description' => sanitizeInput($_POST['description'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            if (empty($sliderData['title'])) {
                throw new Exception('Judul slider harus diisi');
            }
            
            if ($action === 'add') {
                if (empty($_FILES['image']['name'])) {
                    throw new Exception('Gambar slider harus diisi');
                }
                $slider->createWithFile($sliderData, $_FILES['image']);
                $message = 'Slider berhasil ditambahkan';
                
            } elseif ($action === 'edit') {
                if (empty($id)) {
                    throw new Exception('ID slider tidak valid');
                }
                $file = !empty($_FILES['image']['name']) ? $_FILES['image'] : null;
                $slider->updateWithFile($id, $sliderData, $file);
                $message = 'Slider berhasil diperbarui';
            }

        } elseif ($action === 'bulk_delete' || $action === 'activate' || $action === 'deactivate') {
            $ids = is_array($_POST['ids'] ?? []) ? $_POST['ids'] : [$_POST['ids']];
            if (empty($ids)) {
                throw new Exception('Pilih setidaknya satu slider');
            }
            
            $successCount = 0;
            foreach ($ids as $sliderId) {
                try {
                    if ($action === 'bulk_delete') {
                        $slider->delete($sliderId);
                    } else {
                        $newStatus = ($action === 'activate') ? 1 : 0;
                        $slider->update($sliderId, ['is_active' => $newStatus]);
                    }
                    $successCount++;
                } catch (Exception $e) {
                    error_log("Error processing slider ID {$sliderId}: " . $e->getMessage());
                }
            }
            
            $actionText = ['bulk_delete' => 'dihapus', 'activate' => 'diaktifkan', 'deactivate' => 'dinonaktifkan'][$action] ?? 'diperbarui';
            $message = "{$successCount} slider berhasil {$actionText}";
            if ($successCount < count($ids)) {
                $message .= ", gagal " . (count($ids) - $successCount) . " slider";
            }
        } else {
            throw new Exception('Aksi tidak valid');
        }
        
        $_SESSION['toast'] = ['type' => 'success', 'message' => $message];

    } catch (Exception $e) {
        error_log('Slider Error: ' . $e->getMessage());
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Terjadi kesalahan: ' . (DEBUG_MODE ? $e->getMessage() : 'Silakan coba lagi')];
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get all sliders with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$slidersData = $slider->all($page, $perPage);
$sliders = $slidersData['data'] ?? [];
$pagination = $slidersData['pagination'] ?? [];

$editMode = isset($_GET['edit']) && is_numeric($_GET['edit']);
$editingSlider = null;
if ($editMode) {
    $editingSlider = $slider->find($_GET['edit']);
    if (!$editingSlider) {
        $editMode = false;
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Slider tidak ditemukan'];
    }
}

$pageTitle = 'Kelola Slider - Siaga Bapok';
ob_end_flush();
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
            <a class="navbar-brand ms-2" href="#">Kelola Slider</a>
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
                            <i class="bi bi-images me-2"></i>
                            Kelola Slider
                        </h1>
                        <p class="mb-0 mt-2 opacity-75">
                            Manajemen slider untuk tampilan carousel di halaman publik.
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

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><?= $editMode ? 'Edit' : 'Tambah' ?> Slider</h5>
                    </div>
                    <div class="card-body">
                        <form id="sliderForm" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="<?= $editMode ? 'edit' : 'add' ?>">
                            <?php if ($editMode): ?>
                                <input type="hidden" name="id" value="<?= $editingSlider['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Judul <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?= htmlspecialchars($editingSlider['title'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Deskripsi</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($editingSlider['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="image" class="form-label">Gambar</label>
                                <input type="file" class="form-control" id="image" name="image" 
                                       accept="image/jpeg, image/png" 
                                       <?= $editMode ? '' : 'required' ?>>
                                <div class="form-text">Format: JPG/PNG, maks. 2MB</div>
                                
                                <?php if ($editMode && !empty($editingSlider['image_path'])): ?>
                                    <div class="mt-2" id="imagePreviewContainer">
                                    <img src="/SIAGABAPOK/Siaga_bapok/public/uploads/sliders/<?= basename(htmlspecialchars($editingSlider['image_path'])) ?>" 
                                        alt="Preview" class="img-thumbnail" id="imagePreview" style="max-width: 100%;">
                                    </div>
                                <?php else: ?>
                                    <div class="mt-2" id="imagePreviewContainer" style="display: none;">
                                        <img src="#" alt="Preview" class="img-thumbnail" id="imagePreview" style="max-width: 100%;">
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= ($editMode && $editingSlider['is_active']) || !$editMode ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">Aktif</label>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <span id="submitBtnText"><?= $editMode ? 'Update' : 'Simpan' ?></span>
                                    <span id="submitSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                </button>
                                
                                <?php if ($editMode): ?>
                                    <a href="slider.php" class="btn btn-outline-secondary">Batal</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                        <h5 class="card-title mb-2 mb-md-0">Daftar Slider (<?= $slidersData['pagination']['total'] ?>)</h5>
                        <form id="bulkActionForm" method="post" action="slider.php" class="d-flex gap-2">
                            <input type="hidden" name="action" id="bulkActionInput">
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
                                    Aksi Massal
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" onclick="performBulkAction('activate')">Aktifkan</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="performBulkAction('deactivate')">Nonaktifkan</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="performBulkAction('bulk_delete')">Hapus</a></li>
                                </ul>
                            </div>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($sliders)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th width="40">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                                </div>
                                            </th>
                                            <th>Gambar</th>
                                            <th>Judul & Deskripsi</th>
                                            <th>Status</th>
                                            <th width="120" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sliders as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input row-checkbox" type="checkbox" name="ids[]" value="<?= $item['id'] ?>">
                                                    </div>
                                                </td>
                                                <td>
                                                <img src="/SIAGABAPOK/Siaga_bapok/public/uploads/sliders/<?= basename(htmlspecialchars($item['image_path'])) ?>"
                                                    alt="<?= htmlspecialchars($item['title']) ?>" 
                                                    class="img-thumbnail">
                                                </td>
                                                <td>
                                                    <div class="fw-medium"><?= htmlspecialchars($item['title']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars(substr($item['description'] ?? '', 0, 50)) . (strlen($item['description'] ?? '') > 50 ? '...' : '') ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $item['is_active'] ? 'success' : 'secondary' ?>">
                                                        <?= $item['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex gap-1 justify-content-center">
                                                        <a href="?edit=<?= $item['id'] ?>" class="btn btn-sm btn-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                                        <button type="button" class="btn btn-sm btn-danger" title="Hapus" onclick="deleteSingleSlider(<?= $item['id'] ?>, '<?= addslashes(htmlspecialchars($item['title'])) ?>')"><i class="bi bi-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <nav class="d-flex justify-content-center mt-3">
                                <ul class="pagination mb-0">
                                    <?php for ($p = 1; $p <= $pagination['last_page']; $p++): ?>
                                        <li class="page-item <?= $p == $pagination['current_page'] ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $p ?>">
                                                <?= $p ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-3 mb-2"></i>
                                <p>Tidak ada slider.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
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

        // Handle bulk and single delete/update actions
        function performBulkAction(action) {
            const selectedIds = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
            if (selectedIds.length === 0) {
                Swal.fire('Pilih Slider', 'Pilih setidaknya satu slider untuk melakukan aksi.', 'warning');
                return;
            }

            let titleText = '';
            let confirmText = '';
            if (action === 'bulk_delete') {
                titleText = 'Hapus Slider?';
                confirmText = `Anda yakin ingin menghapus ${selectedIds.length} slider yang dipilih?`;
            } else if (action === 'activate') {
                titleText = 'Aktifkan Slider?';
                confirmText = `Anda yakin ingin mengaktifkan ${selectedIds.length} slider yang dipilih?`;
            } else if (action === 'deactivate') {
                titleText = 'Nonaktifkan Slider?';
                confirmText = `Anda yakin ingin menonaktifkan ${selectedIds.length} slider yang dipilih?`;
            }

            Swal.fire({
                title: titleText,
                text: confirmText,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Lanjutkan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('bulkActionForm');
                    const actionInput = document.getElementById('bulkActionInput');
                    
                    actionInput.value = action;
                    
                    // Clear previous IDs and append new ones
                    const oldIdInputs = form.querySelectorAll('input[name="ids[]"]');
                    oldIdInputs.forEach(input => input.remove());
                    
                    selectedIds.forEach(id => {
                        const idInput = document.createElement('input');
                        idInput.type = 'hidden';
                        idInput.name = 'ids[]';
                        idInput.value = id;
                        form.appendChild(idInput);
                    });
                    
                    form.submit();
                }
            });
        }

        function deleteSingleSlider(id, title) {
            Swal.fire({
                title: 'Hapus Slider?',
                html: `Anda yakin ingin menghapus slider "<strong>${title}</strong>"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'slider.php';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete';
                    form.appendChild(actionInput);
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = id;
                    form.appendChild(idInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            setupSidebarToggle();
            
            const selectAll = document.getElementById('selectAll');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    rowCheckboxes.forEach(checkbox => checkbox.checked = this.checked);
                });
            }
            
            // Handle toast messages from session
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