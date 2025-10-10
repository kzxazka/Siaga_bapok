<?php
// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set lokasi log kustom
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Buat direktori logs jika belum ada
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

// Periksa dan include file yang diperlukan
$requiredFiles = [
    '../src/controllers/AuthController.php',
    '../src/models/Database.php',
    '../src/models/Price.php',
    '../src/models/Commodity.php',
    '../src/models/User.php',
    '../src/models/Settings.php',
    '../src/models/Slider.php',
    '../src/config/app.php'
];

foreach ($requiredFiles as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        die("Error: File not found: " . __DIR__ . '/' . $file);
    }
    require_once __DIR__ . '/' . $file;
}

// Tambahan untuk PHPSpreadsheet
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet as ExcelSpreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Inisialisasi model
try {
    $db = Database::getInstance();
    $auth = new AuthController();
    $priceModel = new Price();
    $settingsModel = new Settings();
    $sliderModel = new Slider();
} catch (Exception $e) {
    error_log("Error in index.php: " . $e->getMessage());
    die("Terjadi kesalahan. Silakan periksa log untuk detailnya.");
}

$currentUser = $auth->getCurrentUser();
$role = $currentUser ? $currentUser['role'] : 'masyarakat';

$settings = $settingsModel->getSettingsMap();

$basePath = '/Siaga_bapok/public/'; // Sesuaikan dengan struktur folder Anda
$uploadPath = __DIR__ . '/uploads/commodities/'; // Path absolut ke folder uploads

// Handle API requests
if (isset($_GET['api']) && $_GET['api'] === 'commodity-prices') {
    header('Content-Type: application/json');
    try {
        $referenceDate = $_GET['date'] ?? date('Y-m-d');
        $comparisonPeriod = (int)($_GET['period'] ?? 7);
        $uptdFilter = ($currentUser && $currentUser['role'] === 'uptd') ? $currentUser['id'] : null;
        
        $data = $priceModel->getCommodityPriceComparison($referenceDate, $comparisonPeriod, $uptdFilter);
        
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle Export requests
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    $referenceDate = $_GET['date'] ?? date('Y-m-d');
    $comparisonPeriod = (int)($_GET['period'] ?? 7);
    $selectedMarket = $_GET['market'] ?? 'all';
    $uptdFilter = ($currentUser && $currentUser['role'] === 'uptd') ? $currentUser['id'] : null;

    $dataToExport = $priceModel->getCommodityPriceComparison($referenceDate, $comparisonPeriod, $uptdFilter);

    if (empty($dataToExport)) {
        die("Tidak ada data untuk diekspor.");
    }

    if ($exportType === 'excel') {
        exportToExcel($dataToExport, $selectedMarket, $referenceDate, $comparisonPeriod);
    } elseif ($exportType === 'pdf') {
        exportToPDF($dataToExport, $selectedMarket, $referenceDate, $comparisonPeriod);
    }
}

// Dapatkan data untuk chart dan tabel (untuk tampilan awal)
$referenceDate = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : date('Y-m-d');
$comparisonPeriod = isset($_GET['comparison']) ? (int)$_GET['comparison'] : 7;
$comparisonPeriod = in_array($comparisonPeriod, [1, 7, 30]) ? $comparisonPeriod : 7;

$uptdFilter = ($currentUser && $currentUser['role'] === 'uptd') ? $currentUser['id'] : null;

// PERBAIKAN: Gunakan fungsi yang sama untuk tren dan tabel monitoring
// Ini akan memastikan data tren selalu benar
$commodityTrends = $priceModel->getCommodityPriceComparison($referenceDate, 7, $uptdFilter); 
$commodityPriceComparison = $priceModel->getCommodityPriceComparison($referenceDate, $comparisonPeriod, $uptdFilter);
$topIncreasing = $priceModel->getTopIncreasingPrices($comparisonPeriod, 5); 
$topDecreasing = $priceModel->getTopDecreasingPrices($comparisonPeriod, 5);
$stablePrices = $priceModel->getStablePrices($comparisonPeriod, 5);

$totalPasar = $db->fetchOne("SELECT COUNT(*) as count FROM pasar")['count'];
$totalKomoditas = $db->fetchOne("SELECT COUNT(*) as count FROM commodities")['count'];
$commodities = $db->fetchAll("
    SELECT c.id, c.name, c.unit, c.chart_color 
    FROM commodities c
    ORDER BY c.name ASC
");
$sliders = $sliderModel->getActiveSliders();

$pageTitle = htmlspecialchars($settings['apps_name'] ?? 'Siagabapok');
$appTagline = htmlspecialchars($settings['apps_tagline'] ?? 'Sistem Informasi Harga Bahan Pokok');
$appDesc = htmlspecialchars($settings['apps_desc'] ?? 'Menyediakan informasi harga bahan pokok terkini di Bandar Lampung.');

// --- FUNGSI PHP EXPORT ---
function exportToExcel($data, $selectedMarket, $selectedDate, $interval) {
    try {
        if (empty($data)) {
            die('Tidak ada data yang dapat diekspor');
        }

        $spreadsheet = new ExcelSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $sheet->setCellValue('A1', 'LAPORAN PERBANDINGAN HARGA KOMODITAS');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
        
        $sheet->setCellValue('A2', 'Pasar: ' . ($selectedMarket === 'all' ? 'Semua Pasar' : $selectedMarket));
        $sheet->setCellValue('A3', 'Periode: ' . date('d/m/Y', strtotime($selectedDate . " -{$interval} days")) . ' s/d ' . date('d/m/Y', strtotime($selectedDate)));
        $sheet->mergeCells('A3:G3');
        
        $headers = ['No', 'Komoditas', 'Satuan', 'Harga H-'.$interval, 'Harga Terpilih', 'Harga H+'.$interval, 'Perubahan (%)'];
        $sheet->fromArray($headers, NULL, 'A5');
        
        $row = 6;
        $no = 1;
        foreach ($data as $item) {
            $change = $item['percentage_change'] ?? null;
            
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $item['commodity_name'] ?? '-');
            $sheet->setCellValue('C' . $row, $item['unit'] ?? '-');
            $sheet->setCellValue('D' . $row, $item['comparison_date_price'] ?? '-');
            $sheet->setCellValue('E' . $row, $item['selected_date_price'] ?? '-');
            $sheet->setCellValue('F' . $row, $item['h_plus'] ?? '-');
            $sheet->setCellValue('G' . $row, $change !== null ? number_format($change, 2) . '%' : '-');
            
            $row++;
        }
        
        foreach(range('A','G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $headerStyle = [
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => 'thin']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFD9D9D9']]
        ];
        $sheet->getStyle('A5:G5')->applyFromArray($headerStyle);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="perbandingan_harga_'.date('Y-m-d').'.xlsx"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        
    } catch (\Exception $e) {
        die('Error generating Excel: ' . $e->getMessage());
    }
}

function exportToPDF($data, $selectedMarket, $selectedDate, $interval) {
    try {
        if (empty($data)) {
            die('Tidak ada data yang dapat diekspor');
        }

        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        
        $pdf->SetCreator('Siaga Bapok');
        $pdf->SetAuthor('Siaga Bapok');
        $pdf->SetTitle('Laporan Perbandingan Harga');
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pdf->AddPage();
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'LAPORAN PERBANDINGAN HARGA KOMODITAS', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'Pasar: ' . ($selectedMarket === 'all' ? 'Semua Pasar' : $selectedMarket), 0, 1);
        $pdf->Cell(0, 6, 'Periode: ' . date('d/m/Y', strtotime($selectedDate . " -{$interval} days")) . ' s/d ' . date('d/m/Y', strtotime($selectedDate)), 0, 1);
        $pdf->Ln(5);
        
        $pdf->SetFont('helvetica', 'B', 9);
        $header = array('No', 'Komoditas', 'Satuan', 'Harga H-'.$interval, 'Harga Terpilih', 'Harga H+'.$interval, 'Perubahan (%)');
        $w = array(10, 60, 20, 30, 30, 30, 30);
        
        foreach($header as $i => $col) {
            $pdf->Cell($w[$i], 7, $col, 1, 0, 'C');
        }
        $pdf->Ln();
        
        $pdf->SetFont('helvetica', '', 9);
        $no = 1;
        
        foreach($data as $item) {
            $change = $item['percentage_change'] ?? null;
            $changeText = $change !== null ? number_format($change, 2) . '%' : '-';
            
            $pdf->Cell($w[0], 6, $no++, 'LR', 0, 'C');
            $pdf->Cell($w[1], 6, $item['commodity_name'] ?? '-', 'LR', 0, 'L');
            $pdf->Cell($w[2], 6, $item['unit'] ?? '-', 'LR', 0, 'C');
            $pdf->Cell($w[3], 6, $item['comparison_date_price'] ?? '-', 'LR', 0, 'R');
            $pdf->Cell($w[4], 6, $item['selected_date_price'] ?? '-', 'LR', 0, 'R');
            $pdf->Cell($w[5], 6, $item['h_plus'] ?? '-', 'LR', 0, 'R');
            $pdf->Cell($w[6], 6, $changeText, 'LR', 0, 'R');
            $pdf->Ln();
        }
        
        $pdf->Cell(array_sum($w), 0, '', 'T');
        
        $pdf->Output('perbandingan_harga_'.date('Y-m-d').'.pdf', 'D');
        exit;
        
    } catch (\Exception $e) {
        die('Error generating PDF: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/trend-cards.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/SIAGABAPOK/Siaga_bapok/public/assets/images/BANDAR LAMPUNG ICON.png">
    
    <style>
        html { scroll-behavior: smooth; }
        .text-nowrap { white-space: nowrap; }
        :root {
            --primary-blue: #000080;
            --dark-blue: #3232b9ff;
            --success: #198754;
            --danger: #dc3545;
        }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .card { border-radius: 12px !important; overflow: hidden; transition: all 0.3s ease; border: 1px solid rgba(0,0,0,0.05); }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
        .card:hover img { transform: scale(1.05); }
        .card .badge { font-weight: 500; letter-spacing: 0.3px; padding: 5px 10px; border-radius: 6px; }
        .card-img-container { height: 180px; overflow: hidden; background: #f8f9fa; }
        .text-truncate-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; }
        .navbar-custom { background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%); }
        .hero-section { background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%); color: white; padding: 4rem 0; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); transition: all 0.3s ease; }
        .card:hover { box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); transform: translateY(-2px); }
        .chart-container { position: relative; height: 400px; margin-bottom: 2rem; }
        .trend-cards-container { display: flex; overflow-x: auto; overflow-y: hidden; gap: 1.5rem; padding: 1rem 0.5rem; scroll-behavior: smooth; -webkit-overflow-scrolling: touch; scrollbar-width: thin; scrollbar-color: rgba(0, 0, 0, 0.1) transparent; margin: 0 -0.5rem; }
        .trend-cards-container > .d-flex { padding: 0.5rem; margin: -0.5rem 0; }
        .trend-card { transition: all 0.3s ease; position: relative; overflow: hidden; border-radius: 0.75rem; width: 280px; flex: 0 0 auto; background: white; box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.05); border: 1px solid rgba(0, 0, 0, 0.05); }
        .trend-card:hover { transform: translateY(-3px); box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1); border-color: rgba(0, 0, 0, 0.1); }
        .trend-card .card { border: 1px solid rgba(0,0,0,0.05); transition: all 0.3s ease; height: 100%; display: flex; flex-direction: column; border-radius: 0.75rem; }
        .trend-card:hover { transform: translateY(-5px); }
        .trend-card:hover .card { box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1) !important; border-color: rgba(0, 0, 0, 0.1); }
        .trend-card .card-body { flex: 1; display: flex; flex-direction: column; padding: 1.25rem; }
        .trend-card .price-change { position: absolute; top: 0.75rem; right: 0.75rem; font-size: 0.75rem; padding: 0.25rem 0.75rem; border-radius: 1rem; font-weight: 600; }
        .trend-card .price-change.increase { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .trend-card .price-change.decrease { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
        .trend-card .price-change.stable { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; }
        .trend-card .commodity-image { width: 60px; height: 60px; object-fit: cover; border-radius: 0.5rem; transition: transform 0.3s ease; }
        .trend-card .commodity-image-placeholder { width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; border-radius: 0.5rem; color: #6c757d; font-size: 1.5rem; }
        .trend-card .commodity-name { font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 0.5rem; color: #2c3e50; }
        .trend-card .price-value { font-size: 1.1rem; font-weight: 700; color: #2c3e50; margin-top: 0.5rem; }
        .trend-card .price-diff { font-size: 0.9rem; font-weight: 500; }
        .trend-card .unit-badge { background-color: #f8f9fa; color: #6c757d; font-weight: 500; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; border: 1px solid #dee2e6; }
        .trend-card .detail-btn { border-radius: 0.5rem; font-weight: 500; padding: 0.375rem 1rem; }
        .trend-cards-container::-webkit-scrollbar { height: 6px; }
        .trend-cards-container::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .trend-cards-container::-webkit-scrollbar-thumb { background-color: #888; border-radius: 10px; }
        .trend-cards-container::-webkit-scrollbar-thumb:hover { background-color: #555; }
        .trend-card { flex: 0 0 280px; min-width: 280px; opacity: 0; transform: translateY(20px); transition: opacity 0.5s ease, transform 0.5s ease; }
        .trend-card.visible { opacity: 1; transform: translateY(0); }
        .trend-card .card { height: 100%; border-radius: 12px; overflow: hidden; transition: all 0.3s ease; }
        .trend-card .card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12); }
        .trend-cards-container:hover + .scroll-indicator, .scroll-indicator:hover { opacity: 1; }
        .commodity-carousel-container::-webkit-scrollbar { height: 8px; }
        .commodity-carousel-container::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .commodity-carousel-container::-webkit-scrollbar-thumb { background: #888; border-radius: 10px; }
        .commodity-carousel-container::-webkit-scrollbar-thumb:hover { background: #555; }
        .commodity-carousel-container { scrollbar-width: thin; scrollbar-color: #888 #f1f1f1; -ms-overflow-style: none; scrollbar-width: none; }
        .commodity-carousel-container::-webkit-scrollbar { display: none; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .spinner-border { display: inline-block; width: 1rem; height: 1rem; vertical-align: -0.125em; border: 0.2em solid currentColor; border-right-color: transparent; border-radius: 50%; animation: .75s linear infinite spin; }
        #prevBtn, #nextBtn { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; padding: 0; }
        .hover-shadow { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .hover-shadow:hover { transform: translateY(-2px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important; }
        .hover-shadow-sm:hover { background-color: #f8f9fa; }
        .scroll-indicator { transition: opacity 0.3s ease; }
        .price-change-up { color: var(--danger); font-weight: bold; }
        .price-change-down { color: var(--success); font-weight: bold; }
        .price-change-stable { color: #6c757d; }
        .change-up { color: var(--danger); }
        .change-down { color: var(--success); }
        .change-zero { color: #6c757d; }
        #commodityPriceTable td:last-child { width: 120px; min-width: 120px; }
        .text-success { color: #198754 !important; }
        .text-danger { color: #dc3545 !important; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-responsive { -ms-overflow-style: none; scrollbar-width: none; }
        .table-responsive::-webkit-scrollbar { display: none; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        #commodityPriceTable td, #commodityPriceTable th { vertical-align: middle; white-space: nowrap; }
        .commodity-img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 10px; }
        @media print { .btn, .form-select, .form-control, .navbar, footer, .hero-section { display: none !important; } .card-header { -webkit-print-color-adjust: exact; background-color: #0d6efd !important; color: white !important; } .table th, .table td { font-size: 12px !important; padding: 4px !important; } body { background-color: #fff; } }
        @media (max-width: 768px) { .trend-card { flex: 0 0 240px; min-width: 240px; } }
        .loading-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.7); display: flex; justify-content: center; align-items: center; z-index: 9999; }
        .trend-indicator { margin-right: 5px; font-weight: bold; }
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-stable { color: #6c757d; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-graph-up-arrow me-2"></i>
                <?= htmlspecialchars($settings['apps_name'] ?? 'SIAGABAPOK') ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="bi bi-house me-1"></i>Beranda
                        </a>
                    </li>
                    <?php if ($currentUser): ?>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin/dashboard.php">
                                    <i class="bi bi-speedometer2 me-1"></i>Dashboard Admin
                                </a>
                            </li>
                        <?php elseif ($currentUser['role'] === 'uptd'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="uptd/dashboard.php">
                                    <i class="bi bi-clipboard-data me-1"></i>Dashboard UPTD
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($currentUser['full_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Login
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <?php if (!empty($sliders)): ?>
    <div id="mainCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <?php foreach ($sliders as $index => $slide): ?>
                <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="<?= $index ?>" class="<?= $index == 0 ? 'active' : '' ?>" aria-current="<?= $index == 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $index + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
        <div class="carousel-inner">
            <?php foreach ($sliders as $index => $slide): ?>
            <div class="carousel-item <?= $index == 0 ? 'active' : '' ?>">
                <img src="<?= htmlspecialchars($slide['image_path']) ?>" class="d-block w-100" alt="<?= htmlspecialchars($slide['title']) ?>">
                <div class="carousel-caption d-none d-md-block">
                    <h5><?= htmlspecialchars($slide['title']) ?></h5>
                    <p><?= htmlspecialchars($slide['description']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
    <?php endif; ?>

    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-4">
                        <?= htmlspecialchars($settings['apps_name'] ?? 'Sistem Informasi Harga Bahan Pokok') ?>
                    </h1>
                    <p class="lead mb-4">
                        <?= htmlspecialchars($settings['apps_tagline'] ?? 'Pantau pergerakan harga komoditas bahan pokok di Kota Bandar Lampung secara real-time. Data terpercaya untuk kebutuhan sehari-hari Anda.') ?>
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        <button class="btn btn-light btn-lg" onclick="window.location.href='#monitoringSection'">
                            <i class="bi bi-graph-up me-2"></i>Lihat Grafik
                        </button>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <div class="bg-white bg-opacity-10 rounded-3 p-4">
                        <h3 class="mb-3">Data Terupdate</h3>
                        <p class="h5 mb-0"><?= date('d M Y') ?></p>
                        <small><?= date('H:i') ?> WIB</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container-fluid px-3 px-md-4 px-lg-5 my-4 my-lg-5">
        <div class="card shadow-sm border-0 rounded-4 mb-5 overflow-hidden">
            <div class="card-header bg-white border-0 py-3">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <div class="mb-3 mb-md-0">
                        <h2 class="h4 mb-1 fw-bold text-dark">
                            <i class="bi bi-graph-up-arrow text-primary me-2"></i>Tren Harga Komoditas
                        </h2>
                        <p class="text-muted mb-0 small">
                            Perbandingan harga komoditas dalam 7 hari terakhir.
                        </p>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="btn-group" role="group">
                            <button class="btn btn-outline-primary px-3 py-2" id="prevBtn">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button class="btn btn-outline-primary px-3 py-2" id="nextBtn">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="position-relative">
                    <div class="commodity-carousel-container" style="overflow: hidden;">
                        <div class="commodity-carousel d-flex p-3" style="transition: transform 0.5s ease; width: max-content; gap: 1.25rem;">
                            <?php 
                            if (!empty($commodityTrends)): 
                                foreach ($commodityTrends as $item):
                                    // --- LOGIKA PERHITUNGAN TREN ---
                                    $currentPrice = (float)($item['selected_date_price'] ?? 0);
                                    $prevPrice = (float)($item['comparison_date_price'] ?? 0);
                                    
                                    $priceDiff = $currentPrice - $prevPrice;
                                    
                                    // Mencegah pembagian dengan nol
                                    $priceChange = ($prevPrice > 0) 
                                        ? ($priceDiff / $prevPrice) * 100 
                                        : ($priceDiff > 0 ? 100 : 0); // Jika harga sebelumnya 0, dan sekarang naik, anggap 100%
                                    
                                    $isPriceUp = $priceDiff > 0;
                                    $isPriceDown = $priceDiff < 0;
                                    
                                    // Tentukan kelas warna (Merah = Naik/Bahaya, Hijau = Turun/Success)
                                    $trendClass = $isPriceUp ? 'bg-danger bg-opacity-10 text-danger' : 
                                                ($isPriceDown ? 'bg-success bg-opacity-10 text-success' : 'bg-light text-muted');
                                    $trendIcon = $isPriceUp ? 'bi-arrow-up' : ($isPriceDown ? 'bi-arrow-down' : 'bi-dash');
                            ?>
                                <div class="commodity-card" style="width: 280px; flex-shrink: 0; transition: all 0.3s ease;">
                                    <div class="card h-100 border-0 shadow-sm hover-shadow h-100" style="border-radius: 12px; overflow: hidden;">
                                        <div style="height: 120px; overflow: hidden; position: relative; background: #f8f9fa;" class="d-flex align-items-center justify-content-center">
                                        <?php
// === FIXED PATH HANDLER ===
$imagePath = '';
if (!empty($item['image_path'])) {
    // Remove any leading slashes or backslashes and ensure forward slashes
    $cleanPath = ltrim(str_replace(['\\', '/'], '/', $item['image_path']), '/');
    
    // Check if the path already contains 'uploads/commodities/'
    if (strpos($cleanPath, 'uploads/commodities/') === 0) {
        $imagePath = '/SIAGABAPOK/Siaga_bapok/public/' . $cleanPath;  // Full path from web root
    } else {
        $imagePath = '/SIAGABAPOK/Siaga_bapok/public/uploads/commodities/' . basename($cleanPath);  // Full path from web root
    }
    
    // Debug output
    $fullPath = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/' . ltrim($imagePath, '/');
    $fileExists = file_exists($fullPath);
    
    echo "<!-- Debug: " . htmlspecialchars($imagePath) . " -->\n";
    echo "<!-- File exists: " . ($fileExists ? 'Yes' : 'No') . " -->\n";
    if (!$fileExists) {
        echo "<!-- Debug: Looking in: " . htmlspecialchars($fullPath) . " -->\n";
        echo "<!-- Document Root: " . htmlspecialchars($_SERVER['DOCUMENT_ROOT']) . " -->\n";
    }
}
?>

<?php if (!empty($imagePath)): ?>
    <img src="<?= htmlspecialchars($imagePath) ?>" 
         class="img-fluid" 
         style="max-height: 100%; max-width: 100%; object-fit: contain;"
         onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,<svg width=\'100%\' height=\'100%\' viewBox=\'0 0 100 100\' xmlns=\'http://www.w3.org/2000/svg\'><rect width=\'100%\' height=\'100%\' fill=\'%23f8f9fa\'/><text x=\'50%\' y=\'50%\' font-family=\'sans-serif\' font-size=\'12\' text-anchor=\'middle\' dominant-baseline=\'middle\'>No Image</text></svg>';"
         alt="<?= htmlspecialchars($item['commodity_name'] ?? '') ?>">
<?php else: ?>
    <div class="d-flex align-items-center justify-content-center h-100 w-100 bg-light">
        <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
    </div>
<?php endif; ?>
                                            
                                            <?php if ($priceChange !== 0): ?>
                                                <span class="position-absolute top-2 end-2 badge <?= $trendClass ?> px-2 py-1 rounded-pill fw-medium small">
                                                    <i class="bi <?= $trendIcon ?> me-1"></i>
                                                    <?= abs(round($priceChange, 1)) ?>%
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="card-body p-4">
                                            <div class="mb-3">
                                                <h5 class="card-title mb-1 fw-semibold text-dark" style="font-size: 1.05rem;" title="<?= htmlspecialchars($item['commodity_name'] ?? '') ?>">
                                                    <?= htmlspecialchars($item['commodity_name'] ?? 'Komoditas') ?>
                                                </h5>
                                                <p class="text-muted small mb-0"><?= htmlspecialchars($item['unit'] ?? '') ?></p>
                                            </div>
                                            
                                            <div class="mt-3 pt-2 border-top">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <div class="text-start">
                                                        <p class="text-muted small mb-1">Harga Saat Ini</p>
                                                        <h4 class="text-primary fw-bold mb-0">
                                                            Rp<?= number_format($currentPrice, 0, ',', '.') ?>
                                                        </h4>
                                                    </div>
                                                    <div class="text-end">
                                                        <p class="text-muted small mb-1">Perubahan</p>
                                                        <span class="badge <?= $trendClass ?> px-2 py-1">
                                                            <i class="bi <?= $trendIcon ?> me-1"></i>
                                                            <?= ($priceDiff >= 0 ? '+' : '') . number_format($priceDiff, 0, ',', '.') ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                                    <div class="text-start">
                                                        <p class="text-muted small mb-1">Harga Sebelumnya (H-7)</p>
                                                        <div class="price-value fw-semibold">
                                                            Rp<?= number_format($prevPrice, 0, ',', '.') ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                endforeach; 
                            else: 
                            ?>
                                <div class="col-12 text-center py-5">
                                    <div class="py-4">
                                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                        <p class="text-muted mt-3 mb-0">Belum ada data harga komoditas yang tersedia.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-footer bg-white border-0 py-3">
                <div class="d-flex justify-content-center">
                    <div class="btn-group" role="group">
                        <button class="btn btn-primary px-4 py-2" onclick="window.location.href='#monitoringSection'">
                            <i class="bi bi-graph-up me-2"></i>Lihat Tabel Lengkap
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid px-3 px-md-4 px-lg-5 mb-5">
        <div class="row" id="monitoringSection">
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-3 overflow-hidden">
                    <div class="card-header bg-gradient bg-primary text-white border-0">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4 class="h5 mb-1 fw-bold">
                                    <i class="bi bi-bar-chart-line me-2"></i>
                                    Monitoring Harga Komoditas
                                </h4>
                                <p class="small mb-0 opacity-75">Data harga terupdate per <?= date('d M Y') ?></p>
                            </div>
                        <div class="col-md-6">
                            <div class="d-flex flex-column flex-sm-row justify-content-md-end align-items-stretch align-items-sm-center gap-2 mt-3 mt-md-0">
                                <div class="d-flex flex-grow-1 flex-sm-grow-0">
                                    <div class="input-group input-group-sm">
                                    <select id="comparisonSelect" class="form-select form-select-sm">
                                        <option value="1">H-1</option>
                                        <option value="7" selected>H-7</option>
                                        <option value="30">H-30</option>
                                    </select>
                                    <input type="date" id="selectedDatePicker" class="form-control">
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                <button type="button" id="exportExcelBtn" class="btn btn-sm btn-light shadow-sm d-flex align-items-center" onclick="handleExport('excel')" data-bs-toggle="tooltip" data-bs-placement="top" title="Unduh Excel">
                                    <i class="bi bi-file-earmark-excel text-success"></i>
                                    <span class="ms-1 d-none d-sm-inline">Excel</span>
                                </button>
                                <button type="button" id="exportPdfBtn" class="btn btn-sm btn-light shadow-sm d-flex align-items-center" onclick="handleExport('pdf')" data-bs-toggle="tooltip" data-bs-placement="top" title="Unduh PDF">
                                    <i class="bi bi-file-earmark-pdf text-danger"></i>
                                    <span class="ms-1 d-none d-sm-inline">PDF</span>
                                </button>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="commodityPriceTable">
                                <thead class="table-light">
                                    <tr class="text-center">
                                        <th class="text-start ps-4">Komoditas</th>
                                        <th class="text-nowrap">Satuan</th>
                                        <th class="text-nowrap">Harga<br><small class="text-muted fw-normal" id="currentDateDisplay"><?= date('d/m/Y', strtotime($referenceDate)) ?></small></th>
                                        <th class="text-nowrap">Harga Sebelumnya<br><small class="text-muted fw-normal" id="comparisonDateDisplay">(H-<?= $comparisonPeriod ?>)</small></th>
                                        <th class="text-nowrap">Perubahan<br><small class="text-muted fw-normal">(Harga & Persentase)</small></th>
                                        <th class="text-nowrap">Grafik<br><small class="text-muted fw-normal">7 Hari Terakhir</small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($commodityPriceComparison)): ?>
                                        <?php foreach ($commodityPriceComparison as $item): ?>
                                            <?php
                                            $change = $item['percentage_change'] !== null ? round($item['percentage_change'], 1) : null;
                                            $changeClass = $change > 0 ? 'text-danger' : ($change < 0 ? 'text-success' : 'text-muted');
                                            $changeIcon = $change > 0 ? 'bi-arrow-up' : ($change < 0 ? 'bi-arrow-down' : 'bi-dash');
                                            $priceDiff = ($item['selected_date_price'] ?? 0) - ($item['comparison_date_price'] ?? 0);
                                            ?>
                                            <tr class="hover-shadow-sm">
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center">
                                                        <strong><?= htmlspecialchars($item['commodity_name']) ?></strong>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($item['unit']) ?></span>
                                                </td>
                                                <td class="text-end fw-bold">
                                                    <?= $item['selected_date_price'] ? 'Rp' . number_format($item['selected_date_price'], 0, ',', '.') : '<span class="text-muted">-</span>' ?>
                                                </td>
                                                <td class="text-end">
                                                    <?= $item['comparison_date_price'] ? 'Rp' . number_format($item['comparison_date_price'], 0, ',', '.') : '<span class="text-muted">-</span>' ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($change !== null): ?>
                                                        <div class="d-flex flex-column">
                                                            <span class="<?= $changeClass ?> fw-bold">
                                                                <i class="bi <?= $changeIcon ?> me-1"></i><?= number_format(abs($change), 1) ?>%
                                                            </span>
                                                            <small class="text-muted">
                                                                <?= $priceDiff >= 0 ? '+' : '' ?><?= number_format($priceDiff, 0, ',', '.') ?>
                                                            </small>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center" style="width: 200px;">
                                                    <?php if (!empty($item['chart_data_formatted'])): ?>
                                                        <div class="sparkline-container mx-auto" style="height: 50px;">
                                                            <canvas id="sparkline-<?= $item['id'] ?>" 
                                                                    width="180" height="50" 
                                                                    data-chart-data='<?= json_encode($item['chart_data_formatted']) ?>'>
                                                            </canvas>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Data tidak tersedia</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <div class="py-4">
                                                    <i class="bi bi-inbox fs-1 text-muted"></i>
                                                    <p class="mt-3 text-muted">Tidak ada data harga komoditas yang tersedia</p>
                                                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="location.reload()">
                                                        <i class="bi bi-arrow-clockwise me-1"></i>Muat Ulang
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!empty($commodityPriceComparison)): ?>
                    <div class="card-footer bg-light py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                Menampilkan <?= count($commodityPriceComparison) ?> dari <?= $totalKomoditas ?> komoditas
                            </small>
                            <small class="text-muted">
                                Terakhir diperbarui: <?= date('d M Y, H:i') ?> WIB
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="bg-dark text-light py-4">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h5><i class="bi bi-graph-up-arrow me-2"></i>SiagaBapok</h5>
                <p class="mb-0">Sistem Informasi Harga Bahan Pokok Kota Bandar Lampung</p>
            </div>
            <div class="col-md-6 text-md-end">
                <small>&copy; <?= date('Y') ?> SiagaBapok. All rights reserved.</small><br>
                <small>Data terakhir diperbarui: <?= date('d M Y, H:i') ?> WIB</small>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // ==========================================================
        // 1. FUNGSI UTILITY (PEMFORMATAN)
        // ==========================================================
        function formatCurrency(value) {
            if (value === null || value === undefined || isNaN(value)) return '-';
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(value);
        }

        function formatPercentage(value) {
            if (value === null || value === undefined || isNaN(value)) return '-';
            return `${parseFloat(value).toFixed(2)}%`;
        }

        function getTrendClass(change) {
            if (change > 0) return 'trend-up';
            if (change < 0) return 'trend-down';
            return 'trend-stable';
        }

        function getTrendIcon(change) {
            if (change > 0) return '↑';
            if (change < 0) return '↓';
            return '→';
        }
        
        // ==========================================================
        // 2. FUNGSI DATA & UI
        // ==========================================================
        async function fetchAndRenderData(period, date) {
            const loading = showLoadingIndicator();
            try {
                console.log(`Fetching data for period=${period}, date=${date}`);
                const response = await fetch(`index.php?api=commodity-prices&period=${period}&date=${date}`);
                if (!response.ok) throw new Error('Network response was not ok');
                
                const data = await response.json();
                console.log('API Response:', data);
                
                if (!data.success) throw new Error(data.error || 'Failed to load data');
                
                // Log first item's structure for debugging
                if (data.data && data.data.length > 0) {
                    console.log('First item structure:', {
                        id: data.data[0].id,
                        has_chart_data: !!data.data[0].chart_data_formatted,
                        chart_data_length: data.data[0].chart_data_formatted ? data.data[0].chart_data_formatted.length : 0,
                        chart_data_sample: data.data[0].chart_data_formatted ? data.data[0].chart_data_formatted[0] : null
                    });
                }
                
                updateCommodityTable(data.data);
                
                // Update tanggal di header tabel monitoring
                const referenceDate = new Date(date).toLocaleDateString('id-ID', {day: '2-digit', month: 'long', year: 'numeric'});
                document.getElementById('currentDateDisplay').textContent = `(Per ${referenceDate})`;
                document.getElementById('comparisonDateDisplay').textContent = `(H-${period})`;

            } catch (error) {
                console.error('Error fetching data:', error);
                showError('Gagal memuat data: ' + error.message);
            } finally {
                hideLoadingIndicator(loading);
            }
        }
        
        function updateCommodityTable(data) {
            const tbody = document.querySelector('#commodityPriceTable tbody');
            if (!tbody) return;
            
            // Hapus isi tabel lama
            tbody.innerHTML = '';
            
            // Render ulang tabel dengan data baru
            const htmlContent = data.map(item => {
                const change = item.percentage_change !== null ? parseFloat(item.percentage_change) : null;
                const priceDiff = (item.selected_date_price ?? 0) - (item.comparison_date_price ?? 0);
                const priceDiffFormatted = priceDiff >= 0 ? `+${formatCurrency(priceDiff)}` : formatCurrency(priceDiff);

                const changeDisplay = change !== null 
                    ? `<div class="d-flex flex-column">
                        <span class="${change > 0 ? 'text-danger' : (change < 0 ? 'text-success' : 'text-muted')} fw-bold">
                            <i class="bi ${change > 0 ? 'bi-arrow-up' : (change < 0 ? 'bi-arrow-down' : 'bi-dash')} me-1"></i>${formatPercentage(change)}
                        </span>
                        <small class="text-muted">${priceDiffFormatted}</small>
                       </div>`
                    : `<span class="text-muted">-</span>`;

                // Check if we have enough data points for a meaningful chart (at least 2 points)
                const hasChartData = item.chart_data_formatted && 
                                  Array.isArray(item.chart_data_formatted) && 
                                  item.chart_data_formatted.length >= 2 &&
                                  item.chart_data_formatted.some(d => d && d.price !== undefined && d.date);
                
                const chartDisplay = hasChartData 
                    ? `<div class="sparkline-container mx-auto" style="height: 50px;">
                           <canvas id="sparkline-${item.id}" width="180" height="50"></canvas>
                       </div>`
                    : `<span class="text-muted small">Data tidak tersedia</span>`;

                return `
                <tr class="hover-shadow-sm">
                    <td class="ps-4"><strong>${item.commodity_name || '-'}</strong></td>
                    <td class="text-center"><span class="badge bg-light text-dark">${item.unit || '-'}</span></td>
                    <td class="text-end fw-bold">${item.selected_date_price ? formatCurrency(item.selected_date_price) : '<span class="text-muted">-</span>'}</td>
                    <td class="text-end">${item.comparison_date_price ? formatCurrency(item.comparison_date_price) : '<span class="text-muted">-</span>'}</td>
                    <td class="text-center">${changeDisplay}</td>
                    <td class="text-center" style="width: 200px;">${chartDisplay}</td>
                </tr>
                `;
            }).join('');
            
            tbody.innerHTML = htmlContent;
            initializeCommoditySparklines(data);
        }
        
        function initializeCommoditySparklines(data) {
            document.querySelectorAll('canvas[id^="sparkline-"]').forEach(canvas => {
                const chart = Chart.getChart(canvas);
                if (chart) chart.destroy();
            });

            data.forEach(item => {
                const canvas = document.getElementById(`sparkline-${item.id}`);
                if (!canvas || !item.chart_data_formatted) return;

                const ctx = canvas.getContext('2d');
                const chartData = item.chart_data_formatted;
                // Check if chart data is available
                if (!chartData || !Array.isArray(chartData) || chartData.length === 0) {
                    console.log('No chart data available for commodity', item.id);
                    return;
                }

                // Ensure all data points have required properties
                const validData = chartData.filter(d => d && d.price !== undefined && d.date);
                
                if (validData.length === 0) {
                    console.log('No valid chart data points for commodity', item.id);
                    return;
                }

                // Sort data by date to ensure correct order
                validData.sort((a, b) => new Date(a.date) - new Date(b.date));
                
                const prices = validData.map(d => parseFloat(d.price) || 0);
                const labels = validData.map(d => {
                    const date = new Date(d.date);
                    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
                });
                
                console.log('Chart data for commodity', item.id, ':', { prices, labels });
                
                // Check if canvas context is valid
                if (!ctx) {
                    console.error('Could not get 2D context for canvas');
                    return;
                }
                
                // Destroy existing chart if it exists
                const existingChart = Chart.getChart(canvas);
                if (existingChart) {
                    existingChart.destroy();
                }
                
                // Set canvas size for better rendering
                canvas.width = canvas.parentElement.offsetWidth;
                canvas.height = 50;
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: prices,
                            borderColor: '#0d6efd',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            pointRadius: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { enabled: false }
                        },
                        scales: {
                            x: { display: false },
                            y: { display: false }
                        }
                    }
                });
            });
        }
        
        function showLoadingIndicator() {
            const div = document.createElement('div');
            div.className = 'loading-overlay';
            div.innerHTML = '<div class="spinner-border text-primary" role="status"></div>';
            document.body.appendChild(div);
            return div;
        }

        function hideLoadingIndicator(element) {
            if (element && element.parentNode) {
                element.parentNode.removeChild(element);
            }
        }

        function showError(message) {
            console.error(message);
            alert(message);
        }

        // ==========================================================
        // 3. FUNGSI EKSPOR
        // ==========================================================
        function handleExport(type) {
            const selectedDate = document.getElementById('selectedDatePicker')?.value || '<?= date('Y-m-d') ?>';
            const comparisonPeriod = document.getElementById('comparisonSelect')?.value;
            const selectedMarket = 'all'; 
            
            const btn = document.querySelector(`[onclick*="handleExport('${type}')"]`);
            if (btn) {
                const originalContent = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';
                btn.disabled = true;

                window.location.href = `index.php?export=${type}&date=${selectedDate}&period=${comparisonPeriod}&market=${selectedMarket}`;

                setTimeout(() => {
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                }, 3000);
            }
        }
        
        window.handleExport = handleExport;

        // ==========================================================
        // 4. FUNGSI CAROUSEL & SCROLL
        // ==========================================================
        function initializeCarousel() {
            const carousel = document.querySelector('.commodity-carousel');
            const carouselContainer = document.querySelector('.commodity-carousel-container');
            if (!carousel || !carouselContainer) return;

            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const items = Array.from(carousel.children);
            
            if (items.length === 0) return;
            
            const itemStyle = window.getComputedStyle(items[0]);
            const itemWidth = items[0].offsetWidth + parseFloat(itemStyle.marginRight) + parseFloat(itemStyle.marginLeft);
            
            let currentPosition = 0;
            let isAnimating = false;
            let startPos = 0;
            let currentTranslate = 0;
            let prevTranslate = 0;
            let isDragging = false;
            
            function calculateItemsPerView() {
                const containerWidth = carouselContainer.offsetWidth;
                return Math.min(Math.floor(containerWidth / itemWidth), 4);
            }
            
            let itemsPerView = calculateItemsPerView();
            let maxPosition = Math.max(0, items.length - itemsPerView);

            function touchStart(e) {
                if (e.type === 'touchstart') {
                    startPos = e.touches[0].clientX;
                } else {
                    startPos = e.clientX;
                    e.preventDefault();
                }
                
                isDragging = true;
                carousel.style.cursor = 'grabbing';
                carousel.style.transition = 'none';
            }

            function touchMove(e) {
                if (!isDragging) return;
                
                const currentClientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
                const diff = currentClientX - startPos;
                
                currentTranslate = prevTranslate + diff;
                carousel.style.transform = `translateX(${currentTranslate}px)`;
            }

            function touchEnd() {
                if (!isDragging) return;
                
                isDragging = false;
                carousel.style.cursor = 'grab';
                
                const draggedSlides = Math.round(currentTranslate / -itemWidth);
                currentPosition = Math.min(Math.max(0, draggedSlides), maxPosition);
                
                updateCarousel();
            }

            function updateCarousel(instant = false) {
                if (isAnimating && !instant) return;
                isAnimating = true;
                
                currentPosition = Math.min(Math.max(0, currentPosition), maxPosition);
                const newPosition = -currentPosition * itemWidth;
                
                carousel.style.transition = instant ? 'none' : 'transform 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                carousel.style.transform = `translateX(${newPosition}px)`;
                
                if (instant) {
                    void carousel.offsetWidth;
                }

                prevTranslate = newPosition;
                updateButtonStates();
                
                carousel.addEventListener('transitionend', function onEnd() {
                    isAnimating = false;
                    carousel.removeEventListener('transitionend', onEnd);
                }, { once: true });
            }

            function updateButtonStates() {
                if (!prevBtn || !nextBtn) return;
                prevBtn.disabled = currentPosition === 0;
                nextBtn.disabled = currentPosition >= maxPosition;
            }

            function goToNext() {
                if (currentPosition < maxPosition) {
                    currentPosition++;
                    updateCarousel();
                }
            }

            function goToPrev() {
                if (currentPosition > 0) {
                    currentPosition--;
                    updateCarousel();
                }
            }
            carousel.addEventListener('touchstart', touchStart, { passive: false });
            carousel.addEventListener('touchend', touchEnd, { passive: false });
            carousel.addEventListener('touchmove', touchMove, { passive: false });
            carousel.addEventListener('mousedown', touchStart, { passive: false });
            carousel.addEventListener('mouseup', touchEnd, { passive: false });
            carousel.addEventListener('mouseleave', touchEnd, { passive: false });
            carousel.addEventListener('mousemove', touchMove, { passive: false });
            
            if (prevBtn) prevBtn.addEventListener('click', goToPrev);
            if (nextBtn) nextBtn.addEventListener('click', goToNext);

            window.addEventListener('resize', () => {
                const newItemsPerView = calculateItemsPerView();
                if (newItemsPerView !== itemsPerView) {
                    itemsPerView = newItemsPerView;
                    maxPosition = Math.max(0, items.length - itemsPerView);
                    currentPosition = Math.min(currentPosition, maxPosition);
                    updateCarousel(true);
                }
            });

            updateCarousel(true);
        }
        
        // ==========================================================
        // 5. INISIALISASI HALAMAN
        // ==========================================================
        function initializePage() {
            const comparisonSelect = document.getElementById('comparisonSelect');
            const selectedDatePicker = document.getElementById('selectedDatePicker');
            
            selectedDatePicker.value = '<?= date('Y-m-d') ?>';

            if (comparisonSelect && selectedDatePicker) {
                const handleDataChange = () => {
                    const period = comparisonSelect.value;
                    const date = selectedDatePicker.value;
                    fetchAndRenderData(period, date);
                };

                comparisonSelect.addEventListener('change', handleDataChange);
                selectedDatePicker.addEventListener('change', handleDataChange);
            }
            
            const initialPeriod = comparisonSelect ? comparisonSelect.value : 7;
            const initialDate = selectedDatePicker ? selectedDatePicker.value : '<?= date('Y-m-d') ?>';
            fetchAndRenderData(initialPeriod, initialDate);
            
            initializeCarousel();
            
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        initializePage();
    });
</script>
</body>
</html>