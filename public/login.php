<?php
require_once __DIR__ . '/../src/controllers/AuthController.php';

session_start();

// Include CSRF middleware
require_once __DIR__ . '/../src/middleware/CsrfMiddleware.php';

// Generate CSRF token
$csrfToken = CsrfMiddleware::getToken();

$auth = new AuthController();
$auth->redirectIfLoggedIn();

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->login();
}

$pageTitle = 'Login - Siaga Bapok';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/png" href="assets/images/BANDAR LAMPUNG ICON.png">
    
    <style>
        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #00bfff, #0066cc);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 15px;
        }

        .back-to-home {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
        
        .login-card {
            display: flex;
            border-radius: 20px;
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            animation: fadeIn 0.6s ease-in-out;
            position: relative;
        }
        
        .login-left {
            flex: 1;
            min-height: 500px;
        }

        .login-left img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .login-right {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
        }

        .login-right img.logo {
            max-width: 80px;
            margin-bottom: 20px;
        }

        .login-right h5 {
            text-align: center;
            margin-bottom: 25px;
            font-weight: bold;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.2);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .btn-login {
            background: linear-gradient(90deg, #00c2ff, #0066cc);
            color: white;
            font-weight: bold;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background: linear-gradient(90deg, #0099cc, #004999);
            transform: scale(1.02);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
            }
            .login-left {
                min-height: 200px;
            }
        }
    </style>
</head>
<body>

<a href="index.php" class="btn btn-outline-light back-to-home">
    <i class="bi bi-arrow-left"></i> Kembali ke Beranda
</a>

<div class="login-card">
    <a href="index.php" class="login-left d-none d-md-block">
        <img src="assets/images/komoditasSiagabapok.png" alt="Gambar Komoditas">
    </a>

    <div class="login-right">
        <div class="text-center">
            <a href="index.php">
                <img src="../public/assets/images/BANDAR LAMPUNG ICON.png" alt="Logo Dinas Perdagangan" class="logo">
            </a>
        </div>
        <h5>Login Sistem Informasi<br>Harga Bahan Pokok</h5>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" 
                    required aria-required="true" autocomplete="username">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" 
                        required aria-required="true" autocomplete="current-password"
                        aria-describedby="passwordHelp">
                </div>
            </div>
            <button type="submit" class="btn btn-login w-100 py-2" id="loginButton">
                <span class="spinner-border spinner-border-sm d-none" id="loginSpinner" role="status" aria-hidden="true"></span>
                <span id="loginText">Masuk</span>
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');
        const loginSpinner = document.getElementById('loginSpinner');
        const loginText = document.getElementById('loginText');

        if (form) {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                form.classList.add('was-validated');

                if (form.checkValidity()) {
                    // Show loading state
                    if (loginButton && loginSpinner && loginText) {
                        loginButton.disabled = true;
                        loginSpinner.classList.remove('d-none');
                        loginText.textContent = 'Memproses...';
                    }
                }
            }, false);
        }
        
        // Hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = bootstrap.Alert.getInstance(alert) || new bootstrap.Alert(alert);
                if (bsAlert) {
                    bsAlert.close();
                }
            });
        }, 5000);
    });
</script>
</body>
</html>