<?php
require_once __DIR__ . '/../../config/database.php';

class Price {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($data) {
        $sql = "INSERT INTO prices (commodity_id, price, market_id, uptd_user_id, notes) 
                VALUES (?, ?, ?, ?, ?)";
        
        return $this->db->execute($sql, [
            $data['commodity_id'],
            $data['price'],
            $data['market_id'],
            $data['uptd_user_id'],
            $data['notes'] ?? null
        ]);
    }
    
    public function getAll($status = null, $limit = null) {
        $sql = "SELECT p.*, c.name AS commodity_name, c.unit,
                       ps.nama_pasar AS market_name,
                       u.full_name as uptd_name, admin.full_name as approved_by_name 
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                JOIN pasar ps ON p.market_id = ps.id_pasar
                JOIN users u ON p.uptd_user_id = u.id 
                LEFT JOIN users admin ON p.approved_by = admin.id";
        
        $params = [];
        
        if ($status) {
            $sql .= " WHERE p.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getById($id) {
        $sql = "SELECT p.*, c.name AS commodity_name, c.unit,
                       ps.nama_pasar AS market_name,
                       u.full_name as uptd_name, admin.full_name as approved_by_name 
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                JOIN pasar ps ON p.market_id = ps.id_pasar
                JOIN users u ON p.uptd_user_id = u.id 
                LEFT JOIN users admin ON p.approved_by = admin.id 
                WHERE p.id = ?";
        
        return $this->db->fetchOne($sql, [$id]);
    }
    
    public function getByUptd($uptdId, $status = null) {
        $sql = "SELECT p.*, c.name AS commodity_name, c.unit,
                       ps.nama_pasar AS market_name,
                       u.full_name as uptd_name 
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                JOIN pasar ps ON p.market_id = ps.id_pasar
                JOIN users u ON p.uptd_user_id = u.id 
                WHERE p.uptd_user_id = ?";
        
        $params = [$uptdId];
        
        if ($status) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function approve($id, $adminId, $notes = null) {
        $sql = "UPDATE prices SET status = 'approved', approved_by = ?, approved_at = NOW(), notes = ? WHERE id = ?";
        return $this->db->execute($sql, [$adminId, $notes, $id]);
    }
    
    public function reject($id, $adminId, $notes = null) {
        $sql = "UPDATE prices SET status = 'rejected', approved_by = ?, approved_at = NOW(), notes = ? WHERE id = ?";
        return $this->db->execute($sql, [$adminId, $notes, $id]);
    }
    
    public function update($id, $data) {
        $sql = "UPDATE prices 
                SET commodity_id = ?, price = ?, market_id = ?, notes = ? 
                WHERE id = ?";
        
        return $this->db->execute($sql, [
            $data['commodity_id'],
            $data['price'],
            $data['market_id'],
            $data['notes'] ?? null,
            $id
        ]);
    }
    
    public function delete($id) {
        $sql = "DELETE FROM prices WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    public function getApprovedPrices($days = 30) {
        $sql = "SELECT p.*, c.name AS commodity_name, c.unit,
                       ps.nama_pasar AS market_name
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                JOIN pasar ps ON p.market_id = ps.id_pasar
                WHERE p.status = 'approved' 
                AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                ORDER BY p.created_at DESC";
        
        return $this->db->fetchAll($sql, [$days]);
    }
    
    public function getPriceTrends($days = 30) {
        $sql = "SELECT 
                    c.name AS commodity_name,
                    c.unit,
                    DATE(p.created_at) as price_date,
                    AVG(p.price) as avg_price,
                    MIN(p.price) as min_price,
                    MAX(p.price) as max_price,
                    COUNT(*) as market_count
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                WHERE p.status = 'approved' 
                AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY c.name, DATE(p.created_at)
                ORDER BY c.name, price_date DESC";
        
        return $this->db->fetchAll($sql, [$days]);
    }
    
    public function getLatestPrices() {
        $sql = "SELECT c.name AS commodity_name, c.unit,
                       ps.nama_pasar AS market_name, p.price, p.created_at
                FROM (
                    SELECT *,
                           ROW_NUMBER() OVER (PARTITION BY commodity_id, market_id ORDER BY created_at DESC) as rn
                    FROM prices 
                    WHERE status = 'approved'
                ) p
                JOIN commodities c ON p.commodity_id = c.id
                JOIN pasar ps ON p.market_id = ps.id_pasar
                WHERE p.rn = 1
                ORDER BY c.name, ps.nama_pasar";
        
        return $this->db->fetchAll($sql);
    }
    
    public function getPriceComparison($commodityId, $days = 7) {
        $sql = "SELECT 
                    ps.nama_pasar AS market_name, p.price, p.created_at,
                    c.name AS commodity_name, c.unit
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                JOIN pasar ps ON p.market_id = ps.id_pasar
                WHERE p.commodity_id = ? AND p.status = 'approved' 
                AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY p.created_at DESC, ps.nama_pasar";
        
        return $this->db->fetchAll($sql, [$commodityId, $days]);
    }
    
    public function getTopIncreasingPrices($days = 7, $limit = 5) {
        $sql = "SELECT 
                    c.name AS commodity_name, c.unit,
                    AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN p.price END) as current_avg,
                    AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                             AND p.created_at < DATE_SUB(NOW(), INTERVAL ? DAY) THEN p.price END) as previous_avg,
                    COUNT(*) as data_count
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                WHERE p.status = 'approved' 
                AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY c.name, c.unit
                HAVING current_avg IS NOT NULL AND previous_avg IS NOT NULL
                ORDER BY (current_avg - previous_avg) / previous_avg DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$days, $days * 2, $days, $days * 2, $limit]);
    }
    
    public function getStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_prices,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
                    COUNT(DISTINCT commodity_id) as total_commodities,
                    COUNT(DISTINCT market_id) as total_markets
                FROM prices";
        
        return $this->db->fetchOne($sql);
    }
    
    public function getCommodityList() {
        $sql = "SELECT DISTINCT c.id, c.name AS commodity_name, c.unit
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                WHERE p.status = 'approved'
                ORDER BY c.name";
        return $this->db->fetchAll($sql);
    }
    
    public function getMarketList() {
        $sql = "SELECT DISTINCT ps.id_pasar, ps.nama_pasar AS market_name
                FROM prices p
                JOIN pasar ps ON p.market_id = ps.id_pasar
                WHERE p.status = 'approved' 
                ORDER BY ps.nama_pasar";
        return $this->db->fetchAll($sql);
    }
    
    public function searchPrices($filters) {
        $sql = "SELECT p.*, c.name AS commodity_name, c.unit,
                       ps.nama_pasar AS market_name,
                       u.full_name as uptd_name 
                FROM prices p 
                JOIN commodities c ON p.commodity_id = c.id
                JOIN pasar ps ON p.market_id = ps.id_pasar
                JOIN users u ON p.uptd_user_id = u.id 
                WHERE p.status = 'approved'";
        
        $params = [];
        
        if (!empty($filters['commodity_id'])) {
            $sql .= " AND p.commodity_id = ?";
            $params[] = $filters['commodity_id'];
        }
        
        if (!empty($filters['market'])) {
            $sql .= " AND ps.nama_pasar = ?";
            $params[] = $filters['market'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(p.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(p.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getMonthlyTrends($months = 6) {
        $sql = "SELECT 
                    c.name AS commodity_name, c.unit,
                    DATE_FORMAT(p.created_at, '%Y-%m') as month,
                    AVG(p.price) as avg_price,
                    COUNT(*) as data_count
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                WHERE p.status = 'approved' 
                AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY c.name, DATE_FORMAT(p.created_at, '%Y-%m')
                ORDER BY month ASC, c.name";
        
        return $this->db->fetchAll($sql, [$months]);
    }
    
    public function getSignificantPriceChanges($days = 30) {
        $sql = "SELECT 
                    c.name AS commodity_name, c.unit,
                    AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN p.price END) as current_price,
                    AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                             AND p.created_at < DATE_SUB(NOW(), INTERVAL ? DAY) THEN p.price END) as previous_price
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                WHERE p.status = 'approved' 
                AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY c.name
                HAVING current_price IS NOT NULL AND previous_price IS NOT NULL
                AND ABS((current_price - previous_price) / previous_price * 100) >= 5
                ORDER BY ABS((current_price - previous_price) / previous_price) DESC";
        
        return $this->db->fetchAll($sql, [$days, $days * 2, $days, $days * 2]);
    }
    
    public function getPricesByMarketAndDateRange($date, $range = 7, $uptdId = null) {
        $startDate = date('Y-m-d', strtotime($date . ' -' . $range . ' days'));
        $endDate = date('Y-m-d', strtotime($date . ' +' . $range . ' days'));
        
        $sql = "SELECT 
                    c.name AS commodity_name, c.unit,
                    ps.nama_pasar AS market_name,
                    AVG(p.price) as avg_price,
                    DATE(p.created_at) as price_date
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                JOIN pasar ps ON p.market_id = ps.id_pasar
                WHERE p.status = 'approved'
                AND DATE(p.created_at) BETWEEN ? AND ?";
        
        $params = [$startDate, $endDate];
        
        if ($uptdId) {
            $sql .= " AND p.uptd_user_id = ?";
            $params[] = $uptdId;
        }
        
        $sql .= " GROUP BY c.name, ps.nama_pasar, DATE(p.created_at)
          ORDER BY c.name, ps.nama_pasar, MAX(p.created_at)";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getPriceTrendsWithComparison($days = 7, $date = null, $uptdId = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $sql = "SELECT 
                    c.name AS commodity_name, c.unit,
                    DATE(p.created_at) as price_date,
                    AVG(p.price) as avg_price,
                    
                    (SELECT AVG(p2.price) 
                     FROM prices p2 
                     WHERE p2.commodity_id = p.commodity_id 
                     AND DATE(p2.created_at) = ? 
                     AND p2.status = 'approved') as current_price,
                    
                    (SELECT AVG(p3.price) 
                     FROM prices p3 
                     WHERE p3.commodity_id = p.commodity_id 
                     AND DATE(p3.created_at) = DATE_SUB(?, INTERVAL ? DAY) 
                     AND p3.status = 'approved') as previous_price
                FROM prices p
                JOIN commodities c ON p.commodity_id = c.id
                WHERE p.status = 'approved'
                AND DATE(p.created_at) BETWEEN DATE_SUB(?, INTERVAL ? DAY) AND DATE_ADD(?, INTERVAL ? DAY)";
        
        $params = [$date, $date, $days, $date, $days, $date, $days];
        
        if ($uptdId) {
            $sql .= " AND p.uptd_user_id = ?";
            $params[] = $uptdId;
        }
        
        $sql .= " GROUP BY c.name, DATE(p.created_at)
                  ORDER BY c.name, price_date ASC";
        
        $results = $this->db->fetchAll($sql, $params);
        
        foreach ($results as &$item) {
            if (!empty($item['current_price']) && !empty($item['previous_price']) && $item['previous_price'] > 0) {
                $item['percentage_change'] = (($item['current_price'] - $item['previous_price']) / $item['previous_price']) * 100;
                
                if ($item['percentage_change'] > 5) {
                    $item['change_category'] = 'naik_tinggi';
                } elseif ($item['percentage_change'] > 0) {
                    $item['change_category'] = 'naik_rendah';
                } elseif ($item['percentage_change'] == 0) {
                    $item['change_category'] = 'tetap';
                } elseif ($item['percentage_change'] >= -5) {
                    $item['change_category'] = 'turun_rendah';
                } else {
                    $item['change_category'] = 'turun_tinggi';
                }
            } else {
                $item['percentage_change'] = null;
                $item['change_category'] = 'tidak_ada_perbandingan';
            }
        }
        
        return $results;
    }

    public function getCommodityPriceComparison($selectedDate, $comparisonDays, $uptdId = null) {
        if (!$selectedDate) {
            $selectedDate = date('Y-m-d');
        }
        
        // Hitung tanggal perbandingan (H-N)
        $comparisonDate = date('Y-m-d', strtotime($selectedDate . ' -' . $comparisonDays . ' days'));
        
        // Query utama untuk data komoditas dan harga
        $sql = "SELECT 
                    c.id,
                    c.name AS commodity_name, 
                    c.unit,
                    -- Harga pada tanggal yang dipilih
                    (SELECT AVG(p1.price) 
                     FROM prices p1 
                     WHERE p1.commodity_id = c.id 
                     AND DATE(p1.created_at) = ? 
                     AND p1.status = 'approved') as selected_date_price,
                    
                    -- Harga pada H-N
                    (SELECT AVG(p2.price) 
                     FROM prices p2 
                     WHERE p2.commodity_id = c.id 
                     AND DATE(p2.created_at) = ? 
                     AND p2.status = 'approved') as comparison_date_price
                    
                FROM commodities c
                WHERE c.id IN (
                    SELECT DISTINCT commodity_id 
                    FROM prices 
                    WHERE status = 'approved'
                    AND (DATE(created_at) = ? OR DATE(created_at) = ?)
                )";
        
        $params = [$selectedDate, $comparisonDate, $selectedDate, $comparisonDate];
        
        if ($uptdId) {
            $sql .= " AND c.id IN (
                SELECT DISTINCT commodity_id 
                FROM prices 
                WHERE uptd_user_id = ? AND status = 'approved'
                AND (DATE(created_at) = ? OR DATE(created_at) = ?)
            )";
            $params[] = $uptdId;
            $params[] = $selectedDate;
            $params[] = $comparisonDate;
        }
        
        $sql .= " ORDER BY c.name ASC";
        
        $results = $this->db->fetchAll($sql, $params);
        
        // Ambil data chart secara terpisah untuk setiap komoditas
        foreach ($results as &$item) {
            // Hitung persentase perubahan
            if (!empty($item['selected_date_price']) && !empty($item['comparison_date_price']) && $item['comparison_date_price'] > 0) {
                $item['percentage_change'] = (($item['selected_date_price'] - $item['comparison_date_price']) / $item['comparison_date_price']) * 100;
            } else {
                $item['percentage_change'] = null;
            }
            
            // Ambil data chart untuk komoditas ini
            $chartSql = "SELECT 
                            DATE(p.created_at) as price_date,
                            AVG(p.price) as avg_price
                         FROM prices p
                         WHERE p.commodity_id = ? 
                         AND DATE(p.created_at) BETWEEN ? AND ? 
                         AND p.status = 'approved'
                         GROUP BY DATE(p.created_at)
                         ORDER BY price_date ASC";
            
            $chartParams = [$item['id'], $comparisonDate, $selectedDate];
            $chartData = $this->db->fetchAll($chartSql, $chartParams);
            
            // Format chart data untuk Chart.js
            $item['chart_data_formatted'] = [];
            foreach ($chartData as $chartItem) {
                $item['chart_data_formatted'][] = [
                    'date' => $chartItem['price_date'],
                    'price' => (float)$chartItem['avg_price']
                ];
            }
        }
        
        return $results;
    }
}
?>
