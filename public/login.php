<?php
require_once __DIR__ . '/../src/controllers/AuthController.php';

$auth = new AuthController();
$auth->redirectIfLoggedIn();

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->login();
}

$pageTitle = 'Login - Siaga Bapok';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIAGA BAPOK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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

<div class="login-card">
    <!-- Gambar Komoditas -->
    <div class="login-left">
        <img src="assets/images/komoditasSiagabapok.png" alt="Gambar Komoditas" href="index.php">
    </div>

    <!-- Form Login -->
    <div class="login-right">
        <div class="text-center">
            <img src="assets/images/BANDAR LAMPUNG ICON.png" alt="Logo Dinas Perdagangan" class="logo" href="index.php">
        </div>
        <h5>Login Sistem Informasi<br>Harga Bahan Pokok</h5>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <input type="text" class="form-control" name="username" placeholder="Username" required>
            </div>
            <div class="mb-3">
                <input type="password" class="form-control" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-login w-100 py-2">LOGIN</button>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                }
            });
        }, 5000);
    </script>
</body>
</html>
