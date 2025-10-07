<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Price.php';
require_once __DIR__ . '/../models/Commodity.php';

class UptdController extends BaseController {
    protected $requireRole = 'uptd';
    protected $requireMarketAccess = true;
    
    private $marketId;
    
    public function __construct() {
        parent::__construct();
        $this->marketId = $this->user['market_assigned'];
    }
    
    public function dashboard() {
        $priceModel = new Price();
        $commodityModel = new Commodity();
        
        $data = [
            'pendingSubmissions' => $priceModel->getPendingByMarket($this->marketId),
            'recentApprovals' => $priceModel->getRecentApprovals($this->marketId, 5),
            'commodities' => $commodityModel->getAllActive(),
            'pageTitle' => 'UPTD Dashboard'
        ];
        
        $this->view('uptd/dashboard', $data);
    }
    
    public function submitPrice() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            
            $priceModel = new Price();
            $data = $this->getPriceDataFromRequest();
            
            // Validate price data
            $errors = $this->validatePriceData($data);
            
            if (empty($errors)) {
                $data['market_id'] = $this->marketId;
                $data['submitted_by'] = $this->user['id'];
                
                if ($priceId = $priceModel->create($data)) {
                    log_action('price_submitted', [
                        'price_id' => $priceId,
                        'market_id' => $this->marketId,
                        'commodity_id' => $data['commodity_id']
                    ]);
                    
                    // Notify admins
                    notify_admins(
                        'New Price Submission',
                        "New price submitted for market ID: {$this->marketId}"
                    );
                    
                    $_SESSION['success'] = 'Price submitted successfully and is pending approval';
                    $this->redirect('/uptd/dashboard.php');
                } else {
                    $errors[] = 'Failed to submit price. Please try again.';
                }
            }
            
            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            $this->back();
        }
        
        $commodityModel = new Commodity();
        
        $data = [
            'commodities' => $commodityModel->getAllActive(),
            'pageTitle' => 'Submit Price'
        ];
        
        $this->view('uptd/submit_price', $data);
    }
    
    public function priceHistory() {
        $priceModel = new Price();
        $page = max(1, $_GET['page'] ?? 1);
        $perPage = 10;
        
        $data = [
            'prices' => $priceModel->getByMarket($this->marketId, $page, $perPage),
            'totalPages' => ceil($priceModel->countByMarket($this->marketId) / $perPage),
            'currentPage' => $page,
            'pageTitle' => 'Price History'
        ];
        
        $this->view('uptd/price_history', $data);
    }
    
    protected function getPriceDataFromRequest() {
        return [
            'commodity_id' => (int)($_POST['commodity_id'] ?? 0),
            'price' => (float)($_POST['price'] ?? 0),
            'notes' => trim($_POST['notes'] ?? '')
        ];
    }
    
    protected function validatePriceData($data) {
        $errors = [];
        
        if ($data['commodity_id'] <= 0) {
            $errors[] = 'Please select a commodity';
        }
        
        if ($data['price'] <= 0) {
            $errors[] = 'Price must be greater than zero';
        } elseif ($data['price'] > 10000000) { // 10 million
            $errors[] = 'Price is too high';
        }
        
        return $errors;
    }
}
