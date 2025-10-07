<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Price.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Market.php';
require_once __DIR__ . '/../models/Commodity.php';

class AdminController extends BaseController {
    protected $requireRole = 'admin';
    
    public function __construct() {
        parent::__construct();
    }
    
    public function dashboard() {
        $priceModel = new Price();
        $userModel = new User();
        $marketModel = new Market();
        $commodityModel = new Commodity();
        
        $data = [
            'pendingApprovals' => $priceModel->getPendingApprovals(),
            'recentActivity' => $this->getRecentActivity(),
            'userStats' => $userModel->getUserStats(),
            'marketStats' => $marketModel->getStats(),
            'commodityStats' => $commodityModel->getStats(),
            'pageTitle' => 'Admin Dashboard'
        ];
        
        $this->view('admin/dashboard', $data);
    }
    
    public function approvePrice($priceId) {
        $this->validateCsrf();
        
        $priceModel = new Price();
        $price = $priceModel->findById($priceId);
        
        if (!$price) {
            $this->notFound('Price record not found');
        }
        
        if ($priceModel->approve($priceId, $this->user['id'])) {
            log_action('price_approved', [
                'price_id' => $priceId,
                'market_id' => $price['market_id'],
                'commodity_id' => $price['commodity_id']
            ]);
            
            // Notify UPTD user who submitted the price
            notify($price['submitted_by'], 
                   'Price Approved', 
                   "Your price submission for {$price['commodity_name']} has been approved.");
            
            $_SESSION['success'] = 'Price approved successfully';
        } else {
            $_SESSION['error'] = 'Failed to approve price';
        }
        
        $this->back();
    }
    
    public function rejectPrice($priceId) {
        $this->validateCsrf();
        
        $priceModel = new Price();
        $price = $priceModel->findById($priceId);
        
        if (!$price) {
            $this->notFound('Price record not found');
        }
        
        $reason = $_POST['reason'] ?? 'No reason provided';
        
        if ($priceModel->reject($priceId, $this->user['id'], $reason)) {
            log_action('price_rejected', [
                'price_id' => $priceId,
                'market_id' => $price['market_id'],
                'commodity_id' => $price['commodity_id'],
                'reason' => $reason
            ]);
            
            // Notify UPTD user who submitted the price
            notify($price['submitted_by'], 
                   'Price Rejected', 
                   "Your price submission for {$price['commodity_name']} was rejected. Reason: {$reason}");
            
            $_SESSION['success'] = 'Price rejected successfully';
        } else {
            $_SESSION['error'] = 'Failed to reject price';
        }
        
        $this->back();
    }
    
    public function manageUsers() {
        $userModel = new User();
        
        $data = [
            'users' => $userModel->getAll(),
            'pageTitle' => 'Manage Users'
        ];
        
        $this->view('admin/users/index', $data);
    }
    
    public function createUser() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            
            $userModel = new User();
            $data = $this->getUserDataFromRequest();
            
            // Generate a random password
            $password = generate_password();
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            
            if ($userId = $userModel->create($data)) {
                log_action('user_created', ['user_id' => $userId]);
                
                // Send welcome email with credentials (implement this function)
                // $this->sendWelcomeEmail($data['email'], $data['username'], $password);
                
                $_SESSION['success'] = 'User created successfully';
                $this->redirect('/admin/users');
            } else {
                $_SESSION['error'] = 'Failed to create user';
                $_SESSION['form_data'] = $_POST;
                $this->back();
            }
        }
        
        $marketModel = new Market();
        
        $data = [
            'markets' => $marketModel->getAll(),
            'pageTitle' => 'Create User'
        ];
        
        $this->view('admin/users/create', $data);
    }
    
    protected function getUserDataFromRequest() {
        return [
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
            'full_name' => $_POST['full_name'] ?? '',
            'role' => $_POST['role'] ?? 'uptd',
            'market_assigned' => $_POST['role'] === 'uptd' ? ($_POST['market_assigned'] ?? null) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
    }
    
    // Add more admin methods as needed...
    
    protected function getRecentActivity($limit = 10) {
        global $db;
        
        $sql = "SELECT l.*, u.username, u.full_name 
                FROM logs l 
                LEFT JOIN users u ON l.user_id = u.id 
                ORDER BY l.created_at DESC 
                LIMIT ?";
                
        return $db->fetchAll($sql, [$limit]);
    }
}
