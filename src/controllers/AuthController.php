<?php
// Load required files
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../models/BaseModel.php';
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $userModel;
    
    public function __construct() {
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->userModel = new User();
        
        // Clean expired sessions
        $this->userModel->cleanExpiredSessions();
    }
    
    /**
     * Handle user login
     */
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes
    private $lockoutKeyPrefix = 'login_attempts_';

    public function login() {
        // If already logged in, redirect
        if ($this->isLoggedIn()) {
            $this->redirectToDashboard();
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $now = time();
        $lockoutKey = $this->lockoutKeyPrefix . $ip;
        
        // Initialize or get login attempts
        $attempts = $_SESSION[$lockoutKey] ?? ['count' => 0, 'time' => $now];
        
        // Reset counter if last attempt was more than lockout time ago
        if (($now - $attempts['time']) > $this->lockoutDuration) {
            $attempts = ['count' => 0, 'time' => $now];
        }
        
        // Check if max attempts reached
        if ($attempts['count'] >= $this->maxLoginAttempts) {
            $timeLeft = $this->lockoutDuration - ($now - $attempts['time']);
            if ($timeLeft > 0) {
                $_SESSION['error'] = "Terlalu banyak percobaan login. Silakan coba lagi dalam " . ceil($timeLeft/60) . " menit.";
                return;
            } else {
                // Reset counter if lockout time has passed
                $attempts = ['count' => 0, 'time' => $now];
            }
        }
        
        // Only process login on POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Validate CSRF token
        if (empty($_POST['csrf_token']) || !CsrfMiddleware::validateToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'Invalid request. Please refresh the page and try again.';
            error_log('CSRF token validation failed');
            return;
        }

        // Get and sanitize input
        $username = trim(htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'));
        $password = $_POST['password'] ?? '';
        
        // Validate input
        if (empty($username) || empty($password)) {
            $_SESSION['error'] = 'Username dan password harus diisi.';
            return;
        }
        
        // Check if user exists and is active
        $user = $this->userModel->findByUsername($username);
        
        $loginFailed = false;
        if (!$user) {
            $loginFailed = true;
        } elseif (!$user['is_active']) {
            $_SESSION['error'] = 'Akun Anda belum disetujui oleh Admin.';
            $loginFailed = true;
        } elseif (!password_verify($password, $user['password'])) {
            $loginFailed = true;
        }
        
        // Handle failed login
        if ($loginFailed) {
            $attempts['count']++;
            $attempts['time'] = $now;
            $_SESSION[$lockoutKey] = $attempts;
            
            $attemptsLeft = $this->maxLoginAttempts - $attempts['count'];
            if ($attemptsLeft > 0) {
                $_SESSION['error'] = "Username atau password salah. Sisa percobaan: $attemptsLeft";
            } else {
                $_SESSION['error'] = "Anda telah melebihi batas percobaan login. Silakan coba lagi dalam " . ceil($this->lockoutDuration/60) . " menit.";
            }
            return;
        }
        
        // Login successful - create session
        $sessionToken = $this->userModel->createSession($user['id']);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['market_assigned'] = $user['market_assigned'] ?? null;
        $_SESSION['role'] = $user['role'];
        $_SESSION['is_logged_in'] = true;
        
        // Reset login attempts on success
        unset($_SESSION[$lockoutKey]);
        
        // Set secure cookie for persistent login (24 hours)
        $cookieParams = session_get_cookie_params();
        setcookie(
            'session_token',
            $sessionToken,
            [
                'expires' => time() + (24 * 60 * 60),
                'path' => '/',
                'domain' => $cookieParams['domain'],
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
        
        $_SESSION['success'] = 'Login berhasil! Selamat datang, ' . $user['full_name'];
        $this->redirectToDashboard();
    }
    
    /**
     * Handle user logout
     */
    public function logout() {
        // Destroy session token from database
        if (isset($_COOKIE['session_token'])) {
            $this->userModel->destroySession($_COOKIE['session_token']);
            // Clear cookie
            setcookie('session_token', '', time() - 3600, '/', '', false, true);
        }
        
        // Clear all session data
        $_SESSION = array();
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
        
        // Start new session for flash message
        session_start();
        $_SESSION['success'] = 'Anda telah berhasil logout.';
        
                 // Redirect to login page
         header('Location: login.php');
        exit;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        // Check session first
        if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
            return true;
        }
        
        // Check cookie-based session
        if (isset($_COOKIE['session_token'])) {
            $user = $this->userModel->getBySession($_COOKIE['session_token']);
            if ($user) {
                // Restore session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['is_logged_in'] = true;
                return true;
            } else {
                // Invalid or expired token, clear cookie
                setcookie('session_token', '', time() - 3600, '/', '', false, true);
            }
        }
        
        return false;
    }
    
    /**
     * Get current logged in user
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role']
        ];
    }
    
    /**
     * Require authentication - redirect to login if not logged in
     */
    public function requireAuth($allowedRoles = []) {
                 if (!$this->isLoggedIn()) {
             $_SESSION['error'] = 'Anda harus login terlebih dahulu.';
             header('Location: login.php');
             exit;
         }
        
        // Check role if specified
        if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles)) {
            $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini.';
            $this->redirectToDashboard();
            exit;
        }
        
        return $this->getCurrentUser();
    }
    
    /**
     * Require specific role
     */
    public function requireRole($role) {
        $user = $this->requireAuth([$role]);
        return $user;
    }
    
    /**
     * Redirect to login if already logged in (for login page)
     */
    public function redirectIfLoggedIn() {
        if ($this->isLoggedIn()) {
            $this->redirectToDashboard();
            exit;
        }
    }
    
         /**
      * Redirect user to appropriate dashboard based on role
      */
     private function redirectToDashboard() {
         if (!isset($_SESSION['role'])) {
             header('Location: login.php');
             exit;
         }
         
         switch ($_SESSION['role']) {
             case 'admin':
                 header('Location: admin/dashboard.php');
                 break;
             case 'uptd':
                 header('Location: uptd/dashboard.php');
                 break;
             case 'masyarakat':
             default:
                 header('Location: index.php');
                 break;
         }
         exit;
     }
    
    /**
     * Check authentication and return user data (for API-like usage)
     */
    public function checkAuth() {
        if ($this->isLoggedIn()) {
            return [
                'authenticated' => true,
                'user' => $this->getCurrentUser()
            ];
        }
        
        return [
            'authenticated' => false,
            'user' => null
        ];
    }
    
    /**
     * Validate user credentials (without creating session)
     */
    public function validateCredentials($username, $password) {
        $sql = "SELECT * FROM users WHERE username = ?";
        $db = new Database();
        $user = $db->fetchOne($sql, [$username]);
        
        if (!$user) {
            return ['valid' => false, 'message' => 'Username atau password salah.'];
        }
        
        if (!$user['is_active']) {
            return ['valid' => false, 'message' => 'Akun Anda belum disetujui oleh Admin.'];
        }
        
        if (!password_verify($password, $user['password'])) {
            return ['valid' => false, 'message' => 'Username atau password salah.'];
        }
        
        return ['valid' => true, 'user' => $user];
    }

    private function validatePassword($password) {
        if (strlen($password) < 8) {
            return 'Password minimal 8 karakter';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password harus mengandung minimal 1 huruf besar';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password harus mengandung minimal 1 huruf kecil';
        }
        if (!preg_match('/\d/', $password)) {
            return 'Password harus mengandung minimal 1 angka';
        }
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            return 'Password harus mengandung minimal 1 karakter khusus';
        }
        return true;
    }
}