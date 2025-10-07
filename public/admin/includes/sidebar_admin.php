<?php
// File: public/admin/includes/sidebar_admin.php
// Pastikan file ini di-include dari halaman utama (misalnya dashboard.php)

// CSS untuk styling dan responsif sidebar
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

<aside id="sidebar" class="sidebar">
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
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'approve.php' ? 'active' : '' ?>" href="approve.php">
                    <i class="bi bi-check-circle me-2"></i> Persetujuan Data
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'manageUser.php' ? 'active' : '' ?>" href="manageUser.php">
                    <i class="bi bi-people me-2"></i> Manajemen User
                </a>
            </li>
            
            <li class="nav-item text-uppercase text-white px-3 mt-3 mb-2 small-title">
                Data Master
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'markets.php' ? 'active' : '' ?>" href="markets.php">
                    <i class="bi bi-shop me-2"></i> Pasar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'commodities.php' ? 'active' : '' ?>" href="commodities.php">
                    <i class="bi bi-basket me-2"></i> Komoditas
                </a>
            </li>
            
            <li class="nav-item text-uppercase text-white px-3 mt-3 mb-2 small-title">
                Lainnya
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'bandingHargaAdmin.php' ? 'active' : '' ?>" href="bandingHargaAdmin.php">
                    <i class="bi bi-tags me-2"></i> Banding Harga
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'hargaBulanan.php' ? 'active' : '' ?>" href="hargaBulanan.php">
                    <i class="bi bi-calendar me-2"></i> Harga Bulanan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'slider.php' ? 'active' : '' ?>" href="slider.php">
                    <i class="bi bi-images me-2"></i> Kelola Slider
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'pengaturan.php' ? 'active' : '' ?>" href="pengaturan.php">
                    <i class="bi bi-gear me-2"></i> Pengaturan
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