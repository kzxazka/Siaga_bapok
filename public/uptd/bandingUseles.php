<?php
session_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Database.php';
require_once __DIR__ . '/../../src/models/Price.php';

$auth = new AuthController();
$role = $_SESSION['role'] ?? '';
$user = $auth->requireRole('uptd');

$db = Database::getInstance();

// Path to the consolidated sidebar file
$sidebarPath = __DIR__ . '/includes/sidebar_uptd.php';

// Ambil daftar pasar untuk dropdown
$markets = $db->fetchAll("SELECT id_pasar, nama_pasar FROM pasar ORDER BY nama_pasar ASC");

// Ambil input user
$selectedMarket = $_GET['market'] ?? 'all';
$selectedDate   = $_GET['date'] ?? date('Y-m-d');
$interval       = intval($_GET['interval'] ?? 7); // default 7 hari

// Validasi input
if (!in_array($interval, [7, 14, 30])) {
    $interval = 7;
}

// Hitung range tanggal berdasarkan interval
$dateMinus = date('Y-m-d', strtotime($selectedDate . " -{$interval} days"));
$datePlus  = date('Y-m-d', strtotime($selectedDate . " +{$interval} days"));

// Ambil daftar komoditas (dari tabel commodities)
$commodities = $db->fetchAll("SELECT id, name, unit FROM commodities ORDER BY name ASC");

// Fungsi ambil harga
function getPrice($commodityId, $date, $marketId = null) {
    global $db;
    try {
        if ($marketId === null || $marketId === 'all') {
            $sql = "SELECT AVG(p.harga) as price
                    FROM harga_komoditas p
                    WHERE p.id_komoditas = ? 
                    AND DATE(p.tanggal) = ?
                    GROUP BY p.id_komoditas";
            $params = [$commodityId, $date];
        } else {
            $sql = "SELECT p.harga as price
                    FROM harga_komoditas p
                    WHERE p.id_komoditas = ? 
                    AND DATE(p.tanggal) = ? 
                    AND p.id_pasar = ?
                    ORDER BY p.tanggal DESC
                    LIMIT 1";
            $params = [$commodityId, $date, $marketId];
        }
        
        $result = $db->fetchOne($sql, $params);
        return $result ? (float)$result['price'] : null;
        
    } catch (Exception $e) {
        error_log("Error in getPrice: " . $e->getMessage());
        return null;
    }
}

// Fungsi untuk mengekspor ke CSV dengan format tabel yang rapi
function exportToCSV($data, $selectedMarket, $selectedDate, $interval) {
    // Pastikan tidak ada output sebelum header
    if (ob_get_level()) ob_clean();
    
    // Dapatkan nama pasar
    global $db;
    $marketName = 'Semua Pasar';
    if ($selectedMarket !== 'all') {
        $market = $db->fetchOne("SELECT nama_pasar FROM pasar WHERE id_pasar = ?", [$selectedMarket]);
        $marketName = $market ? $market['nama_pasar'] : 'Pasar Tidak Ditemukan';
    }
    
    // Format tanggal
    $dateMinus = date('d/m/Y', strtotime($selectedDate . " -{$interval} days"));
    $formattedDate = date('d/m/Y', strtotime($selectedDate));
    
    // Set header untuk download file CSV dengan BOM untuk encoding UTF-8
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=perbandingan_harga_' . date('Y-m-d') . '.csv');
    
    // Buat file output
    $output = fopen('php://output', 'w');
    
    // Tambahkan BOM untuk encoding UTF-8 di Excel
    fputs($output, "\xEF\xBB\xBF");
    
    // Header laporan
    fputcsv($output, ['LAPORAN PERBANDINGAN HARGA KOMODITAS'], ';');
    fputcsv($output, [''], ';');
    fputcsv($output, ['Pasar', $marketName], ';');
    fputcsv($output, ['Periode', "$dateMinus s/d $formattedDate"], ';');
    fputcsv($output, ['Tanggal Ekspor', date('d/m/Y H:i:s')], ';');
    fputcsv($output, [''], ';');
    
    // Header tabel
    $headers = [
        'No',
        'Komoditas',
        'Satuan',
        "Harga ($dateMinus)",
        "Harga ($formattedDate)",
        'Perubahan (%)',
        'Keterangan'
    ];
    
    fputcsv($output, $headers, ';');
    
    // Data
    $no = 1;
    foreach ($data as $row) {
        $currentPrice = $row['current_price'] ?? $row['selected'] ?? 0;
        $previousPrice = $row['previous_price'] ?? $row['h_minus'] ?? 0;
        
        $change = $currentPrice - $previousPrice;
        $percentage = $previousPrice > 0 ? (($change / $previousPrice) * 100) : 0;
            
        $status = '';
        if ($change > 0) {
            $status = 'Naik';
        } elseif ($change < 0) {
            $status = 'Turun';
        } else {
            $status = 'Stabil';
        }
        
        $rowData = [
            $no++,
            $row['name'],
            $row['unit'],
            $previousPrice ? 'Rp ' . number_format($previousPrice, 0, ',', '.') : '-',
            $currentPrice ? 'Rp ' . number_format($currentPrice, 0, ',', '.') : '-',
            $percentage !== null ? number_format($percentage, 2, ',', '.') . '%' : '-',
            $status
        ];
        
        fputcsv($output, $rowData, ';');
    }
    
    fclose($output);
    exit;
}

// Inisialisasi data untuk tampilan
try {
    // Ambil data harga untuk setiap komoditas
    $tableData = [];
    
    foreach ($commodities as $c) {
        $currentPrice = getPrice($c['id'], $selectedDate, $selectedMarket === 'all' ? null : $selectedMarket);
        $previousPrice = getPrice($c['id'], $dateMinus, $selectedMarket === 'all' ? null : $selectedMarket);
        
        // Hitung perubahan
        $change = null;
        $percentageChange = null;
        
        if ($currentPrice !== null && $previousPrice !== null && $previousPrice > 0) {
            $change = $currentPrice - $previousPrice;
            $percentageChange = ($change / $previousPrice) * 100;
        }
        
        $tableData[] = [
            'id' => $c['id'],
            'name' => $c['name'],
            'unit' => $c['unit'],
            'current_price' => $currentPrice,
            'previous_price' => $previousPrice,
            'price_change' => $change,
            'percentage_change' => $percentageChange,
            'h_minus' => $previousPrice,
            'selected' => $currentPrice,
            'change1' => $percentageChange
        ];
    }
    
} catch (Exception $e) {
    error_log("Error processing data: " . $e->getMessage());
    die("Terjadi kesalahan saat memproses data. Silakan coba lagi.");
}

// Cek apakah ada permintaan ekspor
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        // Pastikan tidak ada output sebelum header
        while (ob_get_level()) ob_end_clean();
        
        // Format data untuk ekspor
        $exportData = [];
        foreach ($tableData as $row) {
            $exportData[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'unit' => $row['unit'],
                'current_price' => $row['current_price'] ?? 0,
                'previous_price' => $row['previous_price'] ?? 0,
                'price_change' => $row['price_change'],
                'percentage_change' => $row['percentage_change']
            ];
        }
        
        // Panggil fungsi ekspor
        exportToCSV($exportData, $selectedMarket, $selectedDate, $interval);
        exit();
        
    } catch (Exception $e) {
        error_log("Export error: " . $e->getMessage());
        die("Terjadi kesalahan saat mengekspor data. Silakan coba lagi.");
    }
}
// Process the price data for the view
$data = [];
foreach ($priceData as $item) {
    // Get the H+ price using the global getPrice function
    $priceHPlus = getPrice($item['id'], $datePlus, $selectedMarket !== 'all' ? $selectedMarket : null);
    
    $data[] = [
        'id' => $item['id'],
        'name' => $item['commodity_name'],
        'unit' => $item['unit'],
        'h_minus' => $item['comparison_date_price'],
        'selected' => $item['selected_date_price'],
        'h_plus' => $priceHPlus,
        'change1' => $item['selected_date_price'] && $item['comparison_date_price'] ? 
            (($item['selected_date_price'] - $item['comparison_date_price']) / $item['comparison_date_price']) * 100 : 
            null
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../public/assets/images/BANDAR LAMPUNG ICON.png">
    
    <style>
        :root {
            --primary-blue: #000080;
            --dark-blue: #3232b9ff;
            --sidebar-width: 250px;
            --navbar-height: 56px;
            --success: #198754;
            --danger: #dc3545;
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
        
        .change-up { color: var(--danger); font-weight: bold; }
        .change-down { color: var(--success); font-weight: bold; }
        .change-zero { color: #6c757d; }

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
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" id="sidebarToggle" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list" style="font-size: 1.5rem;"></i>
            </button>
            <a class="navbar-brand ms-2" href="#">Banding Harga</a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-white me-3 d-none d-sm-inline">
                    <i class="bi bi-person-circle me-1"></i>
                    <?php echo htmlspecialchars($user['username'] ?? 'UPTD'); ?>
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
                            <i class="bi bi-tags me-2"></i>
                            Banding Harga Komoditas
                        </h1>
                        <p class="mb-0 mt-2 opacity-75">
                            Perbandingan harga komoditas antar pasar untuk membantu pedagang dan masyarakat menentukan harga yang adil.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <form class="row g-3 mb-4" method="GET">
            <div class="col-md-3">
                <label class="form-label">Pilih Pasar</label>
                <select name="market" class="form-select">
                    <option value="all" <?= $selectedMarket==='all'?'selected':'' ?>>Semua Pasar</option>
                    <?php foreach($markets as $m): ?>
                        <option value="<?= $m['id_pasar'] ?>" <?= $selectedMarket==$m['id_pasar']?'selected':'' ?>>
                            <?= htmlspecialchars($m['nama_pasar']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Pilih Tanggal</label>
                <input type="date" name="date" value="<?= $selectedDate ?>" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Interval</label>
                <select name="interval" class="form-select">
                    <option value="1" <?= $interval==1?'selected':'' ?>>1 Hari</option>
                    <option value="7" <?= $interval==7?'selected':'' ?>>7 Hari</option>
                    <option value="30" <?= $interval==30?'selected':'' ?>>30 Hari</option>
                </select>
            </div>
            <div class="col-md-3 align-self-end">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-2"></i>Tampilkan</button>
            </div>
        </form>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-table me-2"></i> Perbandingan Harga
                        </div>
                        <?php if (isset($_GET['export']) && $_GET['export'] === 'csv'): ?>
                            <?php
                                header('Content-Type: application/csv');
                                header('Content-Disposition: attachment; filename="perbandingan_harga_'.date('YmdHis').'.csv"');
                                $fp = fopen('php://output', 'w');
                                fputcsv($fp, array('Kode Komoditas', 'Nama Komoditas', 'Harga H-'.$interval, 'Harga Terpilih', 'Perubahan % (vs H-'.$interval.')'));
                                foreach ($data as $row) {
                                    fputcsv($fp, array($row['code'], $row['name'], $row['h_minus'] !== null ? 'Rp '.number_format($row['h_minus'],0,',','.') : '-', $row['selected'] !== null ? 'Rp '.number_format($row['selected'],0,',','.') : '-', $row['change1'] !== null ? number_format(abs($row['change1']), 2).'%': '-'));
                                }
                                fclose($fp);
                            ?>
                        <?php else: ?>
                            <a href="?export=csv&market=<?= $selectedMarket ?>&date=<?= $selectedDate ?>&interval=<?= $interval ?>" class="btn btn-sm btn-light">
                                <i class="bi bi-download me-1"></i> Ekspor ke Excel
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($data)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0 align-middle">
                                        <tr>
                                            <th>Komoditas</th>
                                            <th>Harga H-<?= $interval ?> (<?= date('d/m/Y', strtotime($dateMinus)) ?>)</th>
                                            <th>Harga Terpilih (<?= date('d/m/Y', strtotime($selectedDate)) ?>)</th>
                                            <th>Perubahan % (vs H-<?= $interval ?>)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($data as $row): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                                                    <div class="small text-muted"><?= htmlspecialchars($row['unit']) ?></div>
                                                </td>
                                                <td><?= $row['h_minus'] !== null ? 'Rp '.number_format($row['h_minus'],0,',','.') : '-' ?></td>
                                                <td><?= $row['selected'] !== null ? 'Rp '.number_format($row['selected'],0,',','.') : '-' ?></td>
                                                <td>
                                                    <?php if ($row['change1'] !== null): ?>
                                                        <span class="change-<?= $row['change1'] > 0 ? 'up' : ($row['change1'] < 0 ? 'down' : 'zero') ?>">
                                                            <i class="bi bi-caret-<?= $row['change1'] > 0 ? 'up-fill' : ($row['change1'] < 0 ? 'down-fill' : 'right-fill') ?>"></i>
                                                            <?= number_format(abs($row['change1']), 2) ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="change-zero">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-5 text-center text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Tidak ada data harga yang ditemukan.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
        });
    </script>
</body>
</html>