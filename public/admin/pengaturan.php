<?php
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Database.php';

// Inisialisasi autentikasi
$auth = new AuthController();
$user = $auth->requireRole('admin');
$db = Database::getInstance();

// Path ke file sidebar yang sudah kita konsolidasikan
$sidebarPath = __DIR__ . '/includes/sidebar_admin.php';

// Pastikan tabel `settings` ada
$db->query("CREATE TABLE IF NOT EXISTS settings (
    name VARCHAR(50) PRIMARY KEY,
    value TEXT,
    description VARCHAR(255)
)");

// Data pengaturan default jika belum ada
$defaultSettings = [
    'apps_name' => ['Nama Aplikasi', 'SIAGABAPOK'],
    'apps_tagline' => ['Tagline Aplikasi', 'Sistem Informasi Harga Bahan Pokok'],
    'apps_desc' => ['Deskripsi Aplikasi', 'Menyediakan informasi harga bahan pokok terkini di Bandar Lampung.'],
    'name' => ['Nama Instansi', 'Dinas Perdagangan Bandar Lampung'],
    'logo' => ['Logo Instansi', ''],
    'latitude' => ['Lokasi Koordinat X', '-5.45'],
    'longitude' => ['Lokasi Koordinat Y', '105.2667'],
    'map_zoom' => ['Perbesaran Peta', '12']
];

foreach ($defaultSettings as $key => $values) {
    // Memeriksa apakah data sudah ada di database
    $exists = $db->fetchOne("SELECT name FROM settings WHERE name = ?", [$key]);
    if (!$exists) {
        $db->query("INSERT INTO settings (name, description, value) VALUES (?, ?, ?)", [$key, $values[0], $values[1]]);
    }
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
        try {
            foreach ($_POST as $key => $value) {
                if ($key === 'action') continue;
                if ($key === 'logo_path_old') continue;

                // Mengunggah file logo jika ada
                if ($key === 'logo' && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../../public/assets/images/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $fileName = 'instansi_logo.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                    $filePath = $uploadDir . $fileName;

                    // Hapus logo lama jika ada
                    $oldLogo = $_POST['logo_path_old'];
                    if (!empty($oldLogo) && file_exists($uploadDir . $oldLogo)) {
                        @unlink($uploadDir . $oldLogo);
                    }

                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $filePath)) {
                        $value = $fileName;
                    } else {
                        throw new Exception("Gagal mengunggah file logo.");
                    }
                }
                
                $db->query("UPDATE settings SET value = ? WHERE name = ?", [$value, $key]);
            }
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Pengaturan berhasil disimpan.'];
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal menyimpan pengaturan: ' . $e->getMessage()];
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Ambil semua pengaturan dari database
$settings = $db->fetchAll("SELECT * FROM settings");
$settingsMap = array_column($settings, 'value', 'name');

$pageTitle = 'Pengaturan - Siaga Bapok';
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
            <a class="navbar-brand ms-2" href="#">Pengaturan</a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-white me-3 d-none d-sm-inline">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($user['username'] ?? 'Admin') ?>
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
                            <i class="bi bi-gear me-2"></i>
                            Pengaturan Website
                        </h1>
                        <p class="mb-0 mt-2 opacity-75">
                            Kelola informasi umum dan instansi aplikasi SIAGABAPOK.
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

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Form Pengaturan</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="save_settings">

                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3">Informasi Aplikasi</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Nama Aplikasi</label>
                                        <input type="text" name="apps_name" class="form-control" value="<?= htmlspecialchars($settingsMap['apps_name'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Tagline Aplikasi</label>
                                        <input type="text" name="apps_tagline" class="form-control" value="<?= htmlspecialchars($settingsMap['apps_tagline'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Deskripsi Aplikasi</label>
                                        <textarea name="apps_desc" class="form-control" rows="3"><?= htmlspecialchars($settingsMap['apps_desc'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h6 class="mb-3">Informasi Instansi</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Nama Instansi</label>
                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($settingsMap['name'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Logo Instansi</label>
                                        <input type="file" name="logo" class="form-control" accept="image/*">
                                        <input type="hidden" name="logo_path_old" value="<?= htmlspecialchars($settingsMap['logo'] ?? '') ?>">
                                        <?php if (!empty($settingsMap['logo'])): ?>
                                            <small class="form-text text-muted d-block mt-2">
                                                Logo saat ini: <br>
                                                <img src="../../public/assets/images/<?= htmlspecialchars($settingsMap['logo']) ?>" style="max-height: 50px;">
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Lokasi Koordinat X (Latitude)</label>
                                            <input type="text" name="latitude" class="form-control" value="<?= htmlspecialchars($settingsMap['latitude'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Lokasi Koordinat Y (Longitude)</label>
                                            <input type="text" name="longitude" class="form-control" value="<?= htmlspecialchars($settingsMap['longitude'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Perbesaran Peta</label>
                                        <input type="number" name="map_zoom" class="form-control" value="<?= htmlspecialchars($settingsMap['map_zoom'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save me-2"></i>Simpan Perubahan</button>
                        </form>
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
                    icon: toast.type || 'info',
                    title: toast.message || 'Info',
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