<?php
// Pastikan sesi dimulai jika diperlukan
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../src/models/Database.php';

$auth = new AuthController();
$role = $_SESSION['role'] ?? '';
$user = $auth->requireRole('admin');

$db = Database::getInstance();

// Path to the consolidated sidebar file
$sidebarPath = __DIR__ . '/includes/sidebar_admin.php';

// Ambil daftar pasar untuk dropdown
$markets = $db->fetchAll("SELECT id_pasar, nama_pasar FROM pasar ORDER BY nama_pasar ASC");

// Ambil input user
$selectedMarket = $_POST['pilih_pasar'] ?? 'all';
$selectedMonth = $_POST['pilih_bulan'] ?? date('m');
$selectedYear = date('Y'); // Mengambil tahun saat ini

// Definisikan daftar bulan secara manual
$bulan_list = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Tentukan jumlah hari dalam bulan yang dipilih
$jumlah_hari = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);

// Ambil daftar komoditas (dari tabel commodities, BUKAN prices)
$commodities = $db->fetchAll("SELECT id, name, unit FROM commodities ORDER BY name ASC");

// Ambil data tabel
$data = [];
$data_harian = [];

// Query untuk mengambil data harian
if ($selectedMarket === 'all') {
    $sql_harian = "SELECT 
                    p.commodity_id,
                    DAY(p.created_at) AS hari,
                    AVG(p.price) AS harga
                   FROM prices p
                   WHERE MONTH(p.created_at) = ? AND YEAR(p.created_at) = ?
                   GROUP BY p.commodity_id, DAY(p.created_at)
                   ORDER BY p.commodity_id, hari ASC";
    $result_harian = $db->fetchAll($sql_harian, [$selectedMonth, $selectedYear]);
} else {
    $sql_harian = "SELECT 
                    p.commodity_id,
                    DAY(p.created_at) AS hari,
                    p.price AS harga
                   FROM prices p
                   WHERE p.market_id = ? AND MONTH(p.created_at) = ? AND YEAR(p.created_at) = ?
                   ORDER BY p.commodity_id, p.created_at ASC"; 
    $result_harian = $db->fetchAll($sql_harian, [$selectedMarket, $selectedMonth, $selectedYear]);
}

foreach ($result_harian as $row) {
    $commodity_id = $row['commodity_id'];
    $hari = $row['hari'];
    $harga = $row['harga'];
    $data_harian[$commodity_id][$hari] = $harga;
}

// Query untuk mengambil data rata-rata, min, dan max
if ($selectedMarket === 'all') {
    $sql_ringkasan = "SELECT 
                       p.commodity_id,
                       AVG(p.price) AS harga_rata2,
                       MIN(p.price) AS harga_terendah,
                       MAX(p.price) AS harga_tertinggi
                      FROM prices p
                      WHERE MONTH(p.created_at) = ? AND YEAR(p.created_at) = ?
                      GROUP BY p.commodity_id";
    $result_ringkasan = $db->fetchAll($sql_ringkasan, [$selectedMonth, $selectedYear]);
} else {
    $sql_ringkasan = "SELECT 
                       p.commodity_id,
                       AVG(p.price) AS harga_rata2,
                       MIN(p.price) AS harga_terendah,
                       MAX(p.price) AS harga_tertinggi
                      FROM prices p
                      WHERE p.market_id = ? AND MONTH(p.created_at) = ? AND YEAR(p.created_at) = ?
                      GROUP BY p.commodity_id";
    $result_ringkasan = $db->fetchAll($sql_ringkasan, [$selectedMarket, $selectedMonth, $selectedYear]);
}

$data_ringkasan = [];
foreach ($result_ringkasan as $row) {
    $data_ringkasan[$row['commodity_id']] = [
        'rata2' => $row['harga_rata2'],
        'terendah' => $row['harga_terendah'],
        'tertinggi' => $row['harga_tertinggi']
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Harga Bulanan - Siaga Bapok</title>
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

        .table-responsive {
            overflow-x: auto;
        }

        /* Media Queries untuk responsivitas */
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

        /* Styling for print output */
        @media print {
            body {
                padding-top: 0;
                margin: 0;
            }
            .navbar, .sidebar, .sidebar-backdrop, .form-container, .card-header, .btn-print, .card-body h1, .card-body p {
                display: none !important;
            }
            .main-content {
                margin-left: 0;
                padding: 10px;
                width: 100%;
            }
            .card {
                box-shadow: none;
                border: 1px solid #dee2e6;
            }
            h2, h3 {
                display: block !important;
                text-align: center;
            }
            table {
                width: 100%;
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
            <a class="navbar-brand ms-2" href="#">Harga Bulanan</a>
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
                            <i class="bi bi-calendar-check me-2"></i>
                            Harga Bulanan Komoditas
                        </h1>
                        <p class="mb-0 mt-2 opacity-75">
                            Data harga komoditas per hari dalam satu bulan.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <form class="row g-3 mb-4 form-container" method="POST" action="">
            <div class="col-md-3">
                <label class="form-label">Pilih Pasar</label>
                <select name="pilih_pasar" class="form-select" required>
                    <option value="all" <?= $selectedMarket === 'all' ? 'selected' : '' ?>>Semua Pasar</option>
                    <?php foreach ($markets as $m): ?>
                        <option value="<?= htmlspecialchars($m['id_pasar']) ?>" <?= $selectedMarket == $m['id_pasar'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['nama_pasar']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Pilih Bulan</label>
                <select name="pilih_bulan" class="form-select" required>
                    <?php foreach ($bulan_list as $nomor_bulan => $nama_bulan): ?>
                        <option value="<?= $nomor_bulan ?>" <?= $nomor_bulan == $selectedMonth ? 'selected' : '' ?>>
                            <?= $nama_bulan ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" name="tampilkan" class="btn btn-primary w-100"><i class="bi bi-search me-2"></i>Tampilkan</button>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="dropdown w-100">
                    <button class="btn btn-secondary dropdown-toggle w-100" type="button" id="dropdownPrint" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-printer me-2"></i>Cetak
                    </button>
                    <ul class="dropdown-menu w-100" aria-labelledby="dropdownPrint">
                        <li><button class="dropdown-item" type="button" onclick="cetakPDF()">PDF</button></li>
                        <li><button class="dropdown-item" type="button" onclick="cetakPNG()">PNG</button></li>
                    </ul>
                </div>
            </div>
        </form>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <i class="bi bi-table me-2"></i> Data Harga Bulanan (<?= $bulan_list[intval($selectedMonth)] . " " . $selectedYear ?>)
                    </div>
                    <div class="card-body table-responsive p-0" id="tabelData">
                        <table class="table table-bordered table-hover mb-0 align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Komoditas</th>
                                    <?php for ($i = 1; $i <= $jumlah_hari; $i++): ?>
                                        <th>Tgl <?= $i ?></th>
                                    <?php endfor; ?>
                                    <th>Harga Rata-rata</th>
                                    <th>Harga Terendah</th>
                                    <th>Harga Tertinggi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($commodities)): ?>
                                    <tr>
                                        <td colspan="<?= 4 + $jumlah_hari ?>" class="text-center">Tidak ada data komoditas.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($commodities as $c): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($c['name']) ?></strong>
                                            <div class="small text-muted"><?= htmlspecialchars($c['unit']) ?></div>
                                        </td>
                                        <?php for ($i = 1; $i <= $jumlah_hari; $i++): 
                                            $harga = $data_harian[$c['id']][$i] ?? '-';
                                        ?>
                                            <td><?= $harga !== '-' ? 'Rp ' . number_format($harga, 0, ',', '.') : '-' ?></td>
                                        <?php endfor; ?>
                                        <td>
                                            <?php
                                            $ringkasan = $data_ringkasan[$c['id']] ?? null;
                                            if ($ringkasan):
                                                echo 'Rp ' . number_format($ringkasan['rata2'], 0, ',', '.');
                                            else:
                                                echo '-';
                                            endif;
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($ringkasan):
                                                echo 'Rp ' . number_format($ringkasan['terendah'], 0, ',', '.');
                                            else:
                                                echo '-';
                                            endif;
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($ringkasan):
                                                echo 'Rp ' . number_format($ringkasan['tertinggi'], 0, ',', '.');
                                            else:
                                                echo '-';
                                            endif;
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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

        // --- FUNGSI CETAK ---
        function cetakPDF() {
            const element = document.getElementById('tabelData');
            const filename = `Harga_Bulanan_Pasar_<?= $selectedMarket === 'all' ? 'Semua' : htmlspecialchars($markets[array_search($selectedMarket, array_column($markets, 'id_pasar'))]['nama_pasar']) ?>_<?= $bulan_list[intval($selectedMonth)] ?>_<?= $selectedYear ?>.pdf`;
            
            // Konfigurasi jspdf untuk orientasi landscape (karena tabelnya lebar)
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'mm', 'a4'); // 'l' for landscape, 'mm' for millimeters, 'a4'
            
            // Atur properti untuk konversi HTML ke PDF
            const options = {
                filename: filename,
                html2canvas: {
                    scale: 0.8 // Mengatur skala agar pas di A4
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'landscape'
                },
                // Tambahkan judul di atas tabel
                callback: function (doc) {
                    doc.setFontSize(18);
                    doc.text('Data Harga Bulanan Komoditas', 148.5, 15, null, null, 'center');
                    doc.setFontSize(12);
                    doc.text(`Pasar: <?= $selectedMarket === 'all' ? 'Semua Pasar' : htmlspecialchars($markets[array_search($selectedMarket, array_column($markets, 'id_pasar'))]['nama_pasar']) ?>`, 148.5, 22, null, null, 'center');
                    doc.text(`Bulan: <?= $bulan_list[intval($selectedMonth)] ?> <?= $selectedYear ?>`, 148.5, 29, null, null, 'center');
                }
            };
            
            doc.html(element, {
                x: 10,
                y: 35,
                html2canvas: {
                    scale: 0.5 // Skala berbeda untuk memastikan muat di halaman
                },
                callback: function (doc) {
                    doc.save(filename);
                }
            });
        }
        
        function cetakPNG() {
            const element = document.getElementById('tabelData');
            const filename = `Harga_Bulanan_Pasar_<?= $selectedMarket === 'all' ? 'Semua' : htmlspecialchars($markets[array_search($selectedMarket, array_column($markets, 'id_pasar'))]['nama_pasar']) ?>_<?= $bulan_list[intval($selectedMonth)] ?>_<?= $selectedYear ?>.png`;
            
            html2canvas(element, {
                scale: 1.5 // Skala lebih tinggi untuk kualitas gambar yang lebih baik
            }).then(canvas => {
                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = filename;
                link.click();
            });
        }
    </script>
</body>
</html>