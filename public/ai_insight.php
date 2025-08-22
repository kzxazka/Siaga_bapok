<?php
session_start();
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/models/Price.php';
require_once __DIR__ . '/../config/ai_config.php';

// Endpoint ini dapat diakses oleh semua pengguna (admin, uptd, dan masyarakat)
$auth = new AuthController();
$role = $_SESSION['role'] ?? 'public';

// Jika user login sebagai admin atau uptd, dapatkan informasi user
$user = null;
if (in_array($role, ['admin', 'uptd'])) {
    $user = $auth->getCurrentUser();
}

// Validasi parameter period
$period = isset($_GET['period']) ? sanitizeInput($_GET['period']) : 'weekly';
if (!in_array($period, ['weekly', 'monthly', '6months'])) {
    $period = 'weekly';
}

// Inisialisasi model
$priceModel = new Price();

// Ambil data harga sesuai period
$days = 7; // default: weekly
if ($period === 'monthly') {
    $days = 30;
} elseif ($period === '6months') {
    $days = 180;
}

// Ambil data harga yang sudah diapprove
$priceData = $priceModel->getApprovedPrices($days);

// Jika tidak ada data, kembalikan pesan error
if (empty($priceData)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Tidak ada data harga untuk periode yang dipilih'
    ]);
    exit;
}

// Kirim data ke API Gemini untuk dianalisis
try {
    // Log untuk debugging
    error_log("AI Insight: Memulai analisis untuk periode $period dengan " . count($priceData) . " data harga");
    
    $insight = AIConfig::generateInsight($priceData, $period);
    
    if ($insight === null) {
        error_log("AI Insight: Gagal mendapatkan insight dari API Gemini");
        throw new Exception('Gagal mendapatkan insight dari AI. Silakan coba lagi nanti.');
    }
    
    // Log sukses
    error_log("AI Insight: Berhasil mendapatkan insight untuk periode $period");
    
    // Kembalikan hasil dalam format JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'period' => $period,
        'insight' => $insight,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    error_log("AI Insight Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>