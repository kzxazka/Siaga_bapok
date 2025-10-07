<?php
// sidebar_uptd.php
?>
<style>
/* Responsive sidebar: hide on mobile, show with toggle */
@media (max-width: 991.98px) {
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        width: 240px;
        z-index: 1040;
        background: #1e2a56;
        transform: translateX(-100%);
        transition: transform 0.3s;
        box-shadow: 2px 0 8px rgba(0,0,0,0.08);
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .sidebar-backdrop {
        display: none;
        position: fixed;
        z-index: 1039;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.2);
    }
    .sidebar.show + .sidebar-backdrop {
        display: block;
    }
    .sidebar-toggle-btn {
        display: block !important;
    }
}
.sidebar-toggle-btn {
    display: none;
    position: fixed;
    top: 16px;
    left: 16px;
    z-index: 1050;
    background: #0d6efd;
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 44px;
    height: 44px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
</style>

<!-- Toggle Button (visible on mobile) -->
<button class="sidebar-toggle-btn" id="sidebarToggleBtn" type="button" aria-label="Tampilkan Menu">
    <i class="bi bi-list" style="font-size:1.5rem;"></i>
</button>

<nav class="sidebar" id="sidebarUptd">
    <div class="p-3">
        <h4 class="text-center mb-4"><i class="bi bi-graph-up-arrow me-2"></i>SIAGABAPOK</h4>
        <small class="text-center d-block mb-3 opacity-75">UPTD Panel</small>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'uploadHarga.php' ? 'active' : '' ?>" href="uploadHarga.php"><i class="bi bi-upload me-2"></i>Upload Harga</a></li>
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'riwayatHarga.php' ? 'active' : '' ?>" href="riwayatHarga.php"><i class="bi bi-clock-history me-2"></i>Riwayat Harga</a></li>
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'bandingHarga.php' ? 'active' : '' ?>" href="bandingHarga.php"><i class="bi bi-tags me-2"></i>Perbandingan Harga</a></li>
        <li class="nav-item"><small class="text-uppercase text-white px-3 mt-3 mb-2">Lainnya</small></li>
        <li class="nav-item"><a class="nav-link" href="../index.php"><i class="bi bi-house me-2"></i>Lihat Website</a></li>
        <li class="nav-item"><a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
    </ul>
</nav>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebarUptd');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const backdrop = document.getElementById('sidebarBackdrop');

    function showSidebar() {
        sidebar.classList.add('show');
        backdrop.style.display = 'block';
    }
    function hideSidebar() {
        sidebar.classList.remove('show');
        backdrop.style.display = 'none';
    }

    toggleBtn.addEventListener('click', function() {
        if (sidebar.classList.contains('show')) {
            hideSidebar();
        } else {
            showSidebar();
        }
    });
    backdrop.addEventListener('click', hideSidebar);

    // Optional: hide sidebar on resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 991.98) {
            hideSidebar();
        }
    });
});
</script>
