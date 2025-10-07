// File: src/controllers/PriceController.php
<?php
require_once __DIR__ . '/../models/Price.php';
require_once __DIR__ . '/../models/Commodity.php';
require_once __DIR__ . '/../models/Market.php';

class PriceController {
    private $priceModel;
    private $commodityModel;
    private $marketModel;
    
    public function __construct() {
        $this->priceModel = new Price();
        $this->commodityModel = new Commodity();
        $this->marketModel = new Market();
    }
    
    // FIXED for release - Handle price submission from UPTD
    public function submitPrice() {
        try {
            // Check if user is logged in as UPTD
            if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'uptd') {
                throw new Exception('Unauthorized access');
            }
            
            // Validate input
            $required = ['commodity_id', 'price', 'market_id'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Field {$field} harus diisi");
                }
            }
            
            // Prepare data
            $data = [
                'commodity_id' => (int)$_POST['commodity_id'],
                'price' => (float)str_replace(['.', ','], ['', '.'], $_POST['price']),
                'market_id' => (int)$_POST['market_id'],
                'uptd_user_id' => $_SESSION['user']['id'],
                'notes' => $_POST['notes'] ?? null
            ];
            
            // Create new price entry
            $priceId = $this->priceModel->create($data);
            
            if (!$priceId) {
                throw new Exception('Gagal menyimpan data harga');
            }
            
            // Return success response
            return [
                'success' => true,
                'message' => 'Data harga berhasil disimpan dan menunggu persetujuan admin',
                'data' => ['id' => $priceId]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    // FIXED for release - Get prices for public view
    public function getPublicPrices($filters = []) {
        try {
            $filters['status'] = 'approved'; // Only show approved prices
            
            // Default to today if no date range provided
            if (empty($filters['start_date']) && empty($filters['end_date'])) {
                $filters['start_date'] = date('Y-m-d');
                $filters['end_date'] = date('Y-m-d');
            }
            
            return $this->priceModel->getPrices($filters);
            
        } catch (Exception $e) {
            error_log("Error in PriceController::getPublicPrices: " . $e->getMessage());
            return [];
        }
    }
    
    // FIXED for release - Get pending prices for admin approval
    public function getPendingPrices() {
        try {
            return $this->priceModel->getPrices([
                'status' => 'pending',
                'include_pending' => true
            ]);
        } catch (Exception $e) {
            error_log("Error in PriceController::getPendingPrices: " . $e->getMessage());
            return [];
        }
    }
    
    // FIXED for release - Approve price
    public function approvePrice($priceId) {
        try {
            if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
                throw new Exception('Unauthorized access');
            }
            
            $result = $this->priceModel->approve($priceId, $_SESSION['user']['id']);
            
            if (!$result) {
                throw new Exception('Gagal menyetujui data harga');
            }
            
            return [
                'success' => true,
                'message' => 'Data harga berhasil disetujui'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}