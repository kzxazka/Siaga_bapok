<?php
session_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Database.php';
require_once __DIR__ . '/../../src/models/Price.php';
require_once __DIR__ . '../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet as ExcelSpreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$auth = new AuthController();
$role = $_SESSION['role'] ?? '';
$user = $auth->requireRole('admin');

$db = Database::getInstance();

// Ambil input user
$selectedMarket = $_GET['market'] ?? 'all';
$selectedDate   = $_GET['date'] ?? date('Y-m-d');
$interval       = intval($_GET['interval'] ?? 7); // default 7 hari

//validasi input
if (!in_array($interval, [7, 14, 30])) {
    $interval = 7;
}

// Create Price model instance
$priceModel = new Price();

// Get price comparison data
$priceData = $priceModel->getCommodityPriceComparison($selectedDate, $interval);

// Path to the consolidated sidebar file
$sidebarPath = __DIR__ . '/includes/sidebar_admin.php';

// Ambil daftar pasar untuk dropdown
$markets = $db->fetchAll("SELECT id_pasar, nama_pasar FROM pasar ORDER BY nama_pasar ASC");

// Hitung range tanggal berdasarkan interval
$dateMinus = date('Y-m-d', strtotime($selectedDate . " -{$interval} days"));
$datePlus  = date('Y-m-d', strtotime($selectedDate . " +{$interval} days"));

// Ambil daftar komoditas (dari tabel commodities, BUKAN prices)
$commodities = $db->fetchAll("SELECT id, name, unit FROM commodities ORDER BY name ASC");

// Fungsi ambil harga
function getPrice($commodityId, $date, $marketId = null) {
    global $db;
    try {
        if ($marketId === null) {
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

function exportToExcel($data, $selectedMarket, $selectedDate, $interval) {
    $spreadsheet = new ExcelSpreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('Siaga Bapok')
        ->setTitle('Laporan Perbandingan Harga')
        ->setDescription('Laporan perbandingan harga komoditas');
    
    // Add headers
    $sheet->setCellValue('A1', 'LAPORAN PERBANDINGAN HARGA KOMODITAS');
    $sheet->mergeCells('A1:E1');
    
    $sheet->setCellValue('A2', 'Pasar: ' . ($selectedMarket === 'all' ? 'Semua Pasar' : $selectedMarket));
    $sheet->setCellValue('A3', 'Periode: ' . date('d/m/Y', strtotime($selectedDate . " -{$interval} days")) . ' s/d ' . date('d/m/Y', strtotime($selectedDate)));
    
    // Add table headers
    $headers = ['No', 'Komoditas', 'Satuan', 'Harga H-'.$interval, 'Harga Terpilih', 'Harga H+'.$interval, 'Perubahan (%)'];
    $sheet->fromArray($headers, NULL, 'A5');
    
    // Add data
    $row = 6;
    $no = 1;
    foreach ($data as $item) {
        $sheet->setCellValue('A' . $row, $no++);
        $sheet->setCellValue('B' . $row, $item['name']);
        $sheet->setCellValue('C' . $row, $item['unit']);
        $sheet->setCellValue('D' . $row, $item['h_minus'] ?? '-');
        $sheet->setCellValue('E' . $row, $item['selected'] ?? '-');
        $sheet->setCellValue('F' . $row, $item['h_plus'] ?? '-');
        $sheet->setCellValue('G' . $row, $item['change1'] !== null ? number_format($item['change1'], 2) . '%' : '-');
        $row++;
    }
    
    // Style the header
    $sheet->getStyle('A5:G5')->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9D9D9']]
    ]);
    
    // Auto size columns
    foreach(range('A','G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="perbandingan_harga_'.date('Y-m-d').'.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function exportToPDF($data, $selectedMarket, $selectedDate, $interval) {
    $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Siaga Bapok');
    $pdf->SetAuthor('Siaga Bapok');
    $pdf->SetTitle('Laporan Perbandingan Harga');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'LAPORAN PERBANDINGAN HARGA KOMODITAS', 
        "Periode: " . date('d/m/Y', strtotime($selectedDate . " -{$interval} days")) . 
        " s/d " . date('d/m/Y', strtotime($selectedDate)) . 
        "\nPasar: " . ($selectedMarket === 'all' ? 'Semua Pasar' : $selectedMarket));
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array('helvetica', '', 10));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    
    // Set margins
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Add a page
    $pdf->AddPage();
    
    // Add content
    $html = '<table border="1" cellpadding="5">
        <thead>
            <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
                <th width="5%">No</th>
                <th width="25%">Komoditas</th>
                <th width="10%">Satuan</th>
                <th width="15%">Harga H-'.$interval.'</th>
                <th width="15%">Harga Terpilih</th>
                <th width="15%">Harga H+'.$interval.'</th>
                <th width="15%">Perubahan (%)</th>
            </tr>
        </thead>
        <tbody>';
    
    $no = 1;
    foreach ($data as $item) {
        $html .= '<tr>
            <td>'.$no++.'</td>
            <td>'.htmlspecialchars($item['name']).'<br><small>'.htmlspecialchars($item['unit']).'</small></td>
            <td>'.htmlspecialchars($item['unit']).'</td>
            <td>'.($item['h_minus'] !== null ? 'Rp '.number_format($item['h_minus'], 0, ',', '.') : '-').'</td>
            <td>'.($item['selected'] !== null ? 'Rp '.number_format($item['selected'], 0, ',', '.') : '-').'</td>
            <td>'.($item['h_plus'] !== null ? 'Rp '.number_format($item['h_plus'], 0, ',', '.') : '-').'</td>
            <td>'.($item['change1'] !== null ? number_format($item['change1'], 2).'%' : '-').'</td>
        </tr>';
    }
    
    $html .= '</tbody></table>';
    
    // Output the HTML content
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Close and output PDF document
    $pdf->Output('perbandingan_harga_'.date('Y-m-d').'.pdf', 'D');
    exit;
}

// Process the price data for the view
$data = [];
if (is_array($priceData)) {
    foreach ($priceData as $item) {
        if (!is_array($item) || !isset($item['id'], $item['commodity_name'], $item['unit'])) {
            error_log('Invalid item format: ' . print_r($item, true));
            continue;
        }
        
        // Get the H+ price using the global getPrice function
        $priceHPlus = getPrice($item['id'], $datePlus, $selectedMarket !== 'all' ? $selectedMarket : null);
        
        // Handle null values
        $selectedPrice = $item['selected_date_price'] ?? null;
        $comparisonPrice = $item['comparison_date_price'] ?? null;
        
        // Calculate percentage change if both prices are available
        $change1 = null;
        if ($selectedPrice !== null && $comparisonPrice !== null && $comparisonPrice > 0) {
            $change1 = (($selectedPrice - $comparisonPrice) / $comparisonPrice) * 100;
        }
        
        $data[] = [
            'id' => $item['id'],
            'name' => $item['commodity_name'],
            'unit' => $item['unit'],
            'h_minus' => $comparisonPrice,
            'selected' => $selectedPrice,
            'h_plus' => $priceHPlus,
            'change1' => $change1
        ];
    }
} else {
    error_log('priceData is not an array: ' . gettype($priceData));
}

// Handle export requests
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    
    if ($exportType === 'excel') {
        exportToExcel($data, $selectedMarket, $selectedDate, $interval);
    } elseif ($exportType === 'pdf') {
        exportToPDF($data, $selectedMarket, $selectedDate, $interval);
    }
}


// echo '<pre>'; print_r($data); echo '</pre>'; exit;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banding Harga - Siaga Bapok</title>
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
                <button class="btn btn-primary w-100"><i class="bi bi-search me-2"></i>Tampilkan</button>
            </div>
        </form>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-table me-2"></i> Perbandingan Harga
                        </div>
                        <div class="btn-group">
                            <a href="?export=excel&market=<?= $selectedMarket ?>&date=<?= $selectedDate ?>&interval=<?= $interval ?>" 
                            class="btn btn-sm btn-success">
                                <i class="bi bi-file-earmark-excel me-1"></i> Excel
                            </a>
                            <a href="?export=pdf&market=<?= $selectedMarket ?>&date=<?= $selectedDate ?>&interval=<?= $interval ?>" 
                            class="btn btn-sm btn-danger">
                                <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                            </a>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-bordered table-hover mb-0 align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Komoditas</th>
                                    <th>Harga H-<?= $interval ?> (<?= date('d/m/Y', strtotime($dateMinus)) ?>)</th>
                                    <th>Harga Terpilih (<?= date('d/m/Y', strtotime($selectedDate)) ?>)</th>
                                    <th>Harga H+<?= $interval ?> (<?= date('d/m/Y', strtotime($datePlus)) ?>)</th>
                                    <th>% Perubahan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($data as $row): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($row['name']) ?></strong>
                                            <div class="small text-muted"><?= htmlspecialchars($row['unit']) ?></div>
                                        </td>
                                        <td><?= $row['h_minus'] !== null ? 'Rp ' . number_format($row['h_minus'], 0, ',', '.') : '-' ?></td>
                                        <td><?= $row['selected'] !== null ? 'Rp ' . number_format($row['selected'], 0, ',', '.') : '-' ?></td>
                                        <td><?= $row['h_plus'] !== null ? 'Rp ' . number_format($row['h_plus'], 0, ',', '.') : '-' ?></td>
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
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            setupSidebarToggle();
        });
    </script>
</body>
</html>