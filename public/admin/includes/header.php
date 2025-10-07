<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin Panel - Siaga Bapok' ?></title>
    <!-- Favicon -->
    <link rel="icon" href="../../assets/images/BANDAR LAMPUNG ICON.png" type="image/png">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --sidebar-width: 250px;
            --primary-blue: #1e2a56;
            --dark-blue: #0d1a3f;
            --light-blue: #e9f0ff;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
            --border-radius: 0.5rem;
            --transition: all 0.3s ease;
        }
        
        body { 
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar { 
            position: fixed; 
            top: 0; 
            left: 0; 
            height: 100vh; 
            width: var(--sidebar-width); 
            background: linear-gradient(180deg, var(--primary-blue) 0%, var(--dark-blue) 100%); 
            color: white; 
            overflow-y: auto; 
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .sidebar-header img {
            height: 40px;
            margin-bottom: 0.5rem;
        }
        
        .sidebar-header h4 {
            color: white;
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .user-profile {
            padding: 1.5rem 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
        
        .user-name {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .user-role {
            font-size: 0.8rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            display: inline-block;
        }
        
        .nav-menu {
            padding: 1rem 0.5rem;
        }
        
        .nav-section-title {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.5rem 1rem;
            margin: 1rem 0 0.5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            margin: 0.25rem 0;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }
        
        .nav-link:hover, 
        .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            font-weight: 500;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: var(--transition);
            background-color: #f8f9fa;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .page-title {
            margin: 0;
            color: var(--primary-blue);
            font-weight: 600;
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            transition: var(--transition);
            background: white;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-blue);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Table Styles */
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            color: var(--dark-blue);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #f1f3f7;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(30, 42, 86, 0.03);
        }
        
        /* Button Styles */
        .btn {
            padding: 0.5rem 1.25rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn i {
            margin-right: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        .btn-primary:hover {
            background-color: var(--dark-blue);
            border-color: var(--dark-blue);
            transform: translateY(-1px);
        }
        
        .btn-outline-primary {
            color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        /* Form Styles */
        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control, 
        .form-select,
        .form-control:focus,
        .form-select:focus {
            border-radius: var(--border-radius);
            padding: 0.6rem 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: none;
        }
        
        .form-control:focus, 
        .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.25rem rgba(30, 42, 86, 0.15);
        }
        
        /* Badge Styles */
        .badge {
            padding: 0.4em 0.75em;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        /* Alert Styles */
        .alert {
            border: none;
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
        }
        
        /* Responsive Styles */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: var(--transition);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .sidebar-backdrop {
                display: none;
                position: fixed;
                z-index: 999;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(2px);
            }
            
            .sidebar.show + .sidebar-backdrop {
                display: block;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
        
        /* Sidebar Toggle Button */
        .sidebar-toggle-btn {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1050;
            background: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
            transition: var(--transition);
            align-items: center;
            justify-content: center;
        }
        
        .sidebar-toggle-btn:hover {
            background: var(--dark-blue);
            transform: translateY(-1px);
        }
        
        .sidebar-toggle-btn i {
            font-size: 1.25rem;
        }
        
        /* Custom Utilities */
        .cursor-pointer {
            cursor: pointer;
        }
        
        .text-underline-hover {
            text-decoration: none;
        }
        
        .text-underline-hover:hover {
            text-decoration: underline;
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>
    <!-- Sidebar Toggle Button (Mobile) -->
    <button class="sidebar-toggle-btn" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>
    
    <!-- Include Sidebar -->
    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'uptd'])): ?>
        <?php include __DIR__ . '/../sidebar_' . $_SESSION['role'] . '.php'; ?>
    <?php endif; ?>
    
    <!-- Backdrop for Mobile Sidebar -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="container-fluid px-0">
