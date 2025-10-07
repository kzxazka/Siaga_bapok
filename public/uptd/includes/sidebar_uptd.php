<?php
// File: public/admin/includes/sidebar_uptd.php

// Ambil data user dari session untuk info di sidebar
$user = $_SESSION['user'] ?? ['full_name' => 'UPTD User', 'role' => 'uptd'];
?>
<style>
    /* Styling for sidebar */
    .sidebar {
        /*
        The sidebar CSS is already defined in the main dashboard.php file for simplicity.
        We'll define the specific styling here that pertains to the sidebar's visual look.
        */
        color: white; /* Text color in sidebar */
        background: linear-gradient(180deg, #000080 0%, #3232b9ff 100%);
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

    .sidebar .badge {
        background-color: rgba(255, 255, 255, 0.25) !important;
        color: white !important;
    }

    /* Additional styling for header and user info */
    .sidebar-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
</style>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header p-3 d-flex align-items-center">
        <img src="../assets/images/BANDAR LAMPUNG ICON.png" 
             alt="Logo Bandar Lampung" 
             class="img-fluid me-2" 
             style="max-height: 40px;">
        <h5 class="mb-0">SIAGABAPOK</h5>
    </div>

    <nav class="mt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'uploadHarga.php' ? 'active' : '' ?>" href="uploadHarga.php">
                    <i class="bi bi-upload me-2"></i> Upload Harga
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'riwayatHarga.php' ? 'active' : '' ?>" href="riwayatHarga.php">
                    <i class="bi bi-clock-history me-2"></i> Riwayat Harga
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'bandingHargaUptd.php' ? 'active' : '' ?>" href="bandingHargaUptd.php">
                    <i class="bi bi-tags me-2"></i> Perbandingan Harga
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../index.php">
                    <i class="bi bi-house me-2"></i> Lihat Website
                </a>
            </li>
        </ul>
    </nav>
</aside>