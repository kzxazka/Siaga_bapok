<?php
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/../helpers/logger.php';
require_once __DIR__ . '/../models/User.php';

class BaseController {
    protected $user;
    protected $requireRole = null;
    protected $requireMarketAccess = false;
    
    public function __construct() {
        // Check authentication
        $this->checkAuthentication();
        
        // Load user data
        $this->loadUser();
        
        // Check role if specified
        $this->checkRole();
        
        // Check market access for UPTD
        $this->checkMarketAccess();
    }
    
    protected function checkAuthentication() {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /login.php');
            exit;
        }
    }
    
    protected function loadUser() {
        $userModel = new User();
        $this->user = $userModel->findById($_SESSION['user_id']);
        
        if (!$this->user) {
            // User not found in database, log them out
            session_destroy();
            header('Location: /login.php?error=session_expired');
            exit;
        }
        
        // Update last activity
        $this->updateLastActivity();
    }
    
    protected function checkRole() {
        if ($this->requireRole && $this->user['role'] !== $this->requireRole) {
            $this->forbidden();
        }
    }
    
    protected function checkMarketAccess() {
        if ($this->requireMarketAccess && $this->user['role'] === 'uptd' && !$this->user['market_assigned']) {
            $this->forbidden('No market assigned to this UPTD account');
        }
    }
    
    protected function updateLastActivity() {
        // Update last activity timestamp
        if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity'] > 300)) {
            // Update every 5 minutes
            $userModel = new User();
            $userModel->updateLastActivity($this->user['id']);
            $_SESSION['last_activity'] = time();
        }
    }
    
    protected function json($data, $statusCode = 200) {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
    
    protected function view($view, $data = []) {
        // Make data available to the view
        extract($data);
        
        // Include header
        $role = $this->user['role'] ?? 'guest';
        $header = __DIR__ . "/../views/layouts/header.php";
        $sidebar = __DIR__ . "/../views/layouts/sidebar_{$role}.php";
        
        if (file_exists($header)) {
            include $header;
        }
        
        // Include sidebar if exists
        if (file_exists($sidebar)) {
            include $sidebar;
        }
        
        // Include the view file
        $viewFile = __DIR__ . "/../views/{$view}.php";
        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            $this->notFound();
        }
        
        // Include footer
        $footer = __DIR__ . "/../views/layouts/footer.php";
        if (file_exists($footer)) {
            include $footer;
        }
    }
    
    protected function redirect($url, $statusCode = 302) {
        header("Location: {$url}", true, $statusCode);
        exit;
    }
    
    protected function back() {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }
    
    protected function notFound($message = 'Page not found') {
        http_response_code(404);
        include __DIR__ . '/../views/errors/404.php';
        exit;
    }
    
    protected function forbidden($message = 'Access denied') {
        http_response_code(403);
        include __DIR__ . '/../views/errors/403.php';
        exit;
    }
    
    protected function validateCsrf() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!validate_csrf($token)) {
                if (is_ajax()) {
                    $this->json(['error' => 'Invalid CSRF token'], 419);
                } else {
                    $_SESSION['error'] = 'Invalid or expired form submission. Please try again.';
                    $this->back();
                }
            }
        }
    }
}
