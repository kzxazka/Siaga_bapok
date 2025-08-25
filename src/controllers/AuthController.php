<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../../config/database.php';

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
    public function login() {
        // If already logged in, redirect to appropriate dashboard
        if ($this->isLoggedIn()) {
            $this->redirectToDashboard();
            return;
        }
        
        // Handle POST request (login form submission)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = sanitizeInput($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Validate input
            if (empty($username) || empty($password)) {
                $_SESSION['error'] = 'Username dan password harus diisi.';
                return;
            }
            
            // Check if user exists and is active
            $sql = "SELECT * FROM users WHERE username = ?";
            $db = new Database();
            $user = $db->fetchOne($sql, [$username]);
            
            if (!$user) {
                $_SESSION['error'] = 'Username atau password salah.';
                return;
            }
            
            // Check if user is active
            if (!$user['is_active']) {
                $_SESSION['error'] = 'Akun Anda belum disetujui oleh Admin.';
                return;
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                $_SESSION['error'] = 'Username atau password salah.';
                return;
            }
            
            // Authentication successful - create session
            $sessionToken = $this->userModel->createSession($user['id']);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['market_assigned'] = $user['market_assigned'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['is_logged_in'] = true;
            
            // DEBUG sementara
            error_log("LOGIN SUCCESS: " . print_r($_SESSION, true));

            // Set cookie for persistent login (24 hours)
            setcookie('session_token', $sessionToken, time() + (24 * 60 * 60), '/', '', false, true);
            
            $_SESSION['success'] = 'Login berhasil! Selamat datang, ' . $user['full_name'];
            
            // Redirect based on role
            $this->redirectToDashboard();
        }
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
}
?>