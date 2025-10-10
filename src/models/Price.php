<?php
require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/Database.php';

class Price extends BaseModel {
    protected $table = 'prices';
    protected $fillable = ['commodity_id', 'price', 'market_id', 'uptd_user_id', 'notes', 'status'];
    protected $primaryKey = 'id';

    protected $db;
    
    protected function validateInput($data) {
        $errors = [];
        
        if (empty($data['commodity_id']) || !is_numeric($data['commodity_id'])) {
            $errors[] = 'Komoditas tidak valid';
        }
        
        if (!isset($data['price']) || !is_numeric($data['price']) || $data['price'] <= 0) {
            $errors[] = 'Harga harus berupa angka positif';
        }
        
        if (empty($data['market_id']) || !is_numeric($data['market_id'])) {
            $errors[] = 'Pasar tidak valid';
        }
        
        if (isset($data['uptd_user_id']) && !is_numeric($data['uptd_user_id'])) {
            $errors[] = 'ID Pengguna UPTD tidak valid';
        }
        
        return $errors;
    }

    public function create($data) {
        $errors = $this->validateInput($data);
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
        
        try {
            $sql = "SELECT id FROM {$this->table}
                     WHERE commodity_id = :commodity_id
                     AND market_id = :market_id
                     AND DATE(created_at) = CURDATE()
                     LIMIT 1";
            $params = [':commodity_id' => $data['commodity_id'], ':market_id' => $data['market_id']];
            $stmt = $this->query($sql, $params);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                throw new Exception('Data harga untuk komoditas ini sudah ada hari ini.');
            }
            
            if (!isset($data['status'])) {
                $data['status'] = 'pending';
            }
            
            $data['price'] = number_format((float)$data['price'], 2, '.', '');
            $data['created_at'] = date('Y-m-d H:i:s');
            
            return parent::create($data);
            
        } catch (PDOException $e) {
            error_log("Database error in Price::create: " . $e->getMessage());
            throw new Exception('Terjadi kesalahan database. Silakan coba lagi nanti.');
        } catch (Exception $e) {
            error_log("Error in Price::create: " . $e->getMessage());
            throw $e;
        }
    }
    
    // --- Metode yang Diperbaiki dan Disempurnakan ---

    public function getPrices($filters = []) {
        try {
            $params = [];
            $where = ['1=1'];
            
            if (!empty($filters['id'])) { $where[] = 'p.id = :id'; $params[':id'] = $filters['id']; }
            if (!empty($filters['status'])) { $where[] = 'p.status = :status'; $params[':status'] = $filters['status']; }
            if (!empty($filters['start_date'])) { $where[] = 'DATE(p.created_at) >= :start_date'; $params[':start_date'] = $filters['start_date']; }
            if (!empty($filters['end_date'])) { $where[] = 'DATE(p.created_at) <= :end_date'; $params[':end_date'] = $filters['end_date']; }
            if (!empty($filters['market_id'])) { $where[] = 'p.market_id = :market_id'; $params[':market_id'] = $filters['market_id']; }
            if (!empty($filters['commodity_id'])) { $where[] = 'p.commodity_id = :commodity_id'; $params[':commodity_id'] = $filters['commodity_id']; }
            if (!empty($filters['uptd_user_id'])) { $where[] = 'p.uptd_user_id = :uptd_user_id'; $params[':uptd_user_id'] = $filters['uptd_user_id']; }
            
            $whereClause = implode(' AND ', $where);
            
            $sql = "SELECT p.*,
                           c.name AS commodity_name,
                           c.unit,
                           ps.nama_pasar AS market_name,
                           u.full_name as uptd_name,
                           admin.full_name as approved_by_name,
                           DATE_FORMAT(p.created_at, '%d/%m/%Y %H:%i') AS formatted_date
                    FROM {$this->table} p
                    JOIN commodities c ON p.commodity_id = c.id
                    JOIN pasar ps ON p.market_id = ps.id_pasar
                    JOIN users u ON p.uptd_user_id = u.id
                    LEFT JOIN users admin ON p.approved_by = admin.id
                    WHERE {$whereClause}
                    ORDER BY p.created_at DESC";
            
            if (!empty($filters['limit'])) {
                $sql .= " LIMIT " . (int)$filters['limit'];
            }
            
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error in Price::getPrices: " . $e->getMessage());
            return [];
        }
    }

    public function getById($id) {
        $result = $this->getPrices(['id' => $id]);
        return empty($result) ? null : $result[0];
    }
    
    public function getByUptd($uptdId, $status = null) {
        $filters = ['uptd_user_id' => $uptdId];
        if ($status) {
            $filters['status'] = $status;
        }
        return $this->getPrices($filters);
    }
    
    public function countByUptdAndStatus($uptdId, $status) {
        try {
            $sql = "SELECT COUNT(*) AS count FROM {$this->table} WHERE uptd_user_id = ? AND status = ?";
            $params = [$uptdId, $status];
            $stmt = $this->query($sql, $params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['count'] ?? 0);
        } catch (Exception $e) {
            error_log("Error counting prices by UPTD and status: " . $e->getMessage());
            return 0;
        }
    }
    
    public function approve($id, $adminId) {
        try {
            $this->db->beginTransaction();
            $sql_check = "SELECT id FROM {$this->table} WHERE id = ? AND status = 'pending'";
            $stmt_check = $this->query($sql_check, [$id]);
            $price = $stmt_check->fetch(PDO::FETCH_ASSOC);
            if (!$price) { throw new Exception('Data harga tidak ditemukan atau sudah disetujui/ditolak.'); }
            
            $sql = "UPDATE {$this->table} SET status = 'approved', approved_by = :admin_id, approved_at = NOW() WHERE id = :id";
            $params = [':admin_id' => $adminId, ':id' => $id];
            $stmt = $this->query($sql, $params);
            
            if ($stmt->rowCount() > 0) {
                $this->db->commit();
                return true;
            } else {
                $this->db->rollBack();
                throw new Exception('Gagal menyetujui data harga.');
            }
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error approving price: " . $e->getMessage());
            throw $e;
        }
    }

    public function reject($id, $adminId, $notes = null) {
        try {
            $this->db->beginTransaction();
            $sql_check = "SELECT id FROM {$this->table} WHERE id = ? AND status = 'pending'";
            $stmt_check = $this->query($sql_check, [$id]);
            $price = $stmt_check->fetch(PDO::FETCH_ASSOC);
            if (!$price) { throw new Exception('Data harga tidak ditemukan atau sudah disetujui/ditolak.'); }

            $sql = "UPDATE {$this->table} SET status = 'rejected', approved_by = :admin_id, approved_at = NOW(), notes = :notes WHERE id = :id";
            $params = [':admin_id' => $adminId, ':notes' => $notes, ':id' => $id];
            $stmt = $this->query($sql, $params);

            if ($stmt->rowCount() > 0) {
                $this->db->commit();
                return true;
            } else {
                $this->db->rollBack();
                throw new Exception('Gagal menolak data harga.');
            }
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error rejecting price: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function update($id, $data) {
        try {
            $updates = [];
            foreach ($data as $key => $value) {
                $updates[] = "$key = :$key";
            }
            $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :id";
            $data['id'] = $id;
            
            $stmt = $this->query($sql, $data);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error updating price: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete($id) {
        try {
            $sql = "DELETE FROM {$this->table} WHERE id = :id";
            $stmt = $this->query($sql, ['id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error deleting price: " . $e->getMessage());
            return false;
        }
    }
    
    public function getApprovedPrices($days = 30) {
        try {
            $sql = "SELECT p.*, c.name AS commodity_name, c.unit,
                            ps.nama_pasar AS market_name
                    FROM {$this->table} p
                    JOIN commodities c ON p.commodity_id = c.id
                    JOIN pasar ps ON p.market_id = ps.id_pasar
                    WHERE p.status = 'approved' 
                    AND p.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY) 
                    ORDER BY p.created_at DESC";
            
            $stmt = $this->query($sql, [':days' => $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting approved prices: " . $e->getMessage());
            return [];
        }
    }
    
    public function getPriceTrends($days = 30) {
        try {
            $sql = "SELECT 
                        c.name AS commodity_name,
                        c.unit,
                        DATE(p.created_at) as price_date,
                        AVG(p.price) as avg_price,
                        MIN(p.price) as min_price,
                        MAX(p.price) as max_price,
                        COUNT(*) as market_count
                    FROM {$this->table} p
                    JOIN commodities c ON p.commodity_id = c.id
                    WHERE p.status = 'approved' 
                    AND p.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                    GROUP BY c.name, DATE(p.created_at)
                    ORDER BY c.name, price_date DESC";
            
            $stmt = $this->query($sql, [':days' => $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting price trends: " . $e->getMessage());
            return [];
        }
    }
    
    public function getLatestPricesByCommodity($limit = 10) {
        try {
            $sql = "SELECT 
                        c.id,
                        c.name as commodity_name,
                        c.unit,
                        c.image_path,
                        c.chart_color,
                        p.price as latest_price,
                        p.created_at,
                        m.name as market_name,
                        u.name as uptd_name
                    FROM commodities c
                    JOIN (
                        SELECT 
                            commodity_id,
                            MAX(created_at) as latest_date
                        FROM prices
                        WHERE status = 'approved'
                        GROUP BY commodity_id
                    ) latest ON c.id = latest.commodity_id
                    JOIN prices p ON p.commodity_id = latest.commodity_id 
                                 AND p.created_at = latest.latest_date
                                 AND p.status = 'approved'
                    LEFT JOIN markets m ON p.market_id = m.id
                    LEFT JOIN users u ON p.uptd_user_id = u.id
                    ORDER BY c.name ASC
                    LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error in getLatestPricesByCommodity: " . $e->getMessage());
            return [];
        }
    }
    
    public function getPriceHistory($commodityId, $days = 30) {
        try {
            $startDate = date('Y-m-d', strtotime("-$days days"));
            
            $sql = "SELECT 
                        DATE(p.created_at) as date,
                        AVG(p.price) as average_price,
                        COUNT(DISTINCT p.market_id) as market_count
                    FROM prices p
                    WHERE p.commodity_id = :commodity_id
                    AND p.status = 'approved'
                    AND DATE(p.created_at) >= :start_date
                    GROUP BY DATE(p.created_at)
                    ORDER BY date ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':commodity_id' => $commodityId,
                ':start_date' => $startDate
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error in getPriceHistory: " . $e->getMessage());
            return [];
        }
    }

    public function getPriceComparison($commodityId, $days = 7) {
        try {
            $sql = "SELECT 
                        ps.nama_pasar AS market_name, p.price, p.created_at,
                        c.name AS commodity_name, c.unit
                    FROM {$this->table} p
                    JOIN commodities c ON p.commodity_id = c.id
                    JOIN pasar ps ON p.market_id = ps.id_pasar
                    WHERE p.commodity_id = :commodity_id 
                    AND p.status = 'approved' 
                    AND p.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                    ORDER BY p.created_at DESC, ps.nama_pasar";
            
            $params = [
                ':commodity_id' => $commodityId,
                ':days' => $days
            ];
            
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting price comparison: " . $e->getMessage());
            return [];
        }
    }
    
    public function getTopIncreasingPrices($days = 7, $limit = 5) {
        try {
            $sql = "SELECT 
                        c.id, c.name AS commodity_name, c.unit, c.image,
                        AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL :current_days DAY) THEN p.price END) as current_avg,
                        AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL :prev_end_day DAY) 
                                AND p.created_at < DATE_SUB(NOW(), INTERVAL :prev_start_day DAY) THEN p.price END) as previous_avg,
                        COUNT(*) as data_count
                    FROM {$this->table} p
                    JOIN commodities c ON p.commodity_id = c.id
                    WHERE p.status = 'approved' 
                    AND p.created_at >= DATE_SUB(NOW(), INTERVAL :total_days DAY)
                    GROUP BY c.id, c.name, c.unit, c.image
                    HAVING current_avg IS NOT NULL 
                    AND previous_avg IS NOT NULL
                    AND previous_avg > 0
                    AND (current_avg - previous_avg) / previous_avg > 0.01
                    ORDER BY (current_avg - previous_avg) / previous_avg DESC
                    LIMIT :limit";
            
            $params = [
                ':current_days' => $days,
                ':prev_start_day' => $days,
                ':prev_end_day' => $days * 2,
                ':total_days' => $days * 2,
                ':limit' => (int)$limit
            ];
            
            // Log query and params for debugging
            error_log("getTopIncreasingPrices SQL: " . $sql);
            error_log("getTopIncreasingPrices Params: " . print_r($params, true));
            
            $stmt = $this->query($sql, $params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log result count for debugging
            error_log("getTopIncreasingPrices found " . count($result) . " results");
            
            return $result;
        } catch (Exception $e) {
            error_log("Error getting top increasing prices: " . $e->getMessage());
            return [];
        }
    }
    
    public function getTopDecreasingPrices($days = 7, $limit = 5) {
        try {
            $sql = "SELECT 
                        c.id, c.name AS commodity_name, c.unit, c.image_path as image,
                        AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL :current_days DAY) THEN p.price END) as current_avg,
                        AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL :prev_end_day DAY) 
                                AND p.created_at < DATE_SUB(NOW(), INTERVAL :prev_start_day DAY) THEN p.price END) as previous_avg,
                        COUNT(*) as data_count
                    FROM {$this->table} p
                    JOIN commodities c ON p.commodity_id = c.id
                    WHERE p.status = 'approved' 
                    AND p.created_at >= DATE_SUB(NOW(), INTERVAL :total_days DAY)
                    GROUP BY c.id, c.name, c.unit, c.image
                    HAVING current_avg IS NOT NULL 
                    AND previous_avg IS NOT NULL
                    AND previous_avg > 0
                    AND (current_avg - previous_avg) / previous_avg < -0.01
                    ORDER BY (current_avg - previous_avg) / previous_avg ASC
                    LIMIT :limit";
            
            $params = [
                ':current_days' => $days,
                ':prev_start_day' => $days,
                ':prev_end_day' => $days * 2,
                ':total_days' => $days * 2,
                ':limit' => (int)$limit
            ];
            
            // Log query and params for debugging
            error_log("getTopDecreasingPrices SQL: " . $sql);
            error_log("getTopDecreasingPrices Params: " . print_r($params, true));
            
            $stmt = $this->query($sql, $params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log result count for debugging
            error_log("getTopDecreasingPrices found " . count($result) . " results");
            
            return $result;
        } catch (Exception $e) {
            error_log("Error getting top decreasing prices: " . $e->getMessage());
            return [];
        }
    }
    
    public function getStablePrices($days = 7, $limit = 5) {
        try {
            $sql = "SELECT 
                        c.id, c.name AS commodity_name, c.unit, c.image_path as image,
                        AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL :current_days DAY) THEN p.price END) as current_avg,
                        AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL :prev_end_day DAY) 
                                AND p.created_at < DATE_SUB(NOW(), INTERVAL :prev_start_day DAY) THEN p.price END) as previous_avg,
                        COUNT(*) as data_count
                    FROM {$this->table} p
                    JOIN commodities c ON p.commodity_id = c.id
                    WHERE p.status = 'approved' 
                    AND p.created_at >= DATE_SUB(NOW(), INTERVAL :total_days DAY)
                    GROUP BY c.id, c.name, c.unit
                    HAVING current_avg IS NOT NULL 
                    AND previous_avg IS NOT NULL
                    AND previous_avg > 0
                    AND ABS((current_avg - previous_avg) / previous_avg) <= 0.01
                    ORDER BY ABS((current_avg - previous_avg) / previous_avg) ASC
                    LIMIT :limit";
            
            $params = [
                ':current_days' => $days,
                ':prev_start_day' => $days,
                ':prev_end_day' => $days * 2,
                ':total_days' => $days * 2,
                ':limit' => (int)$limit
            ];
            
            // Log query and params for debugging
            error_log("getStablePrices SQL: " . $sql);
            error_log("getStablePrices Params: " . print_r($params, true));
            
            $stmt = $this->query($sql, $params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log result count for debugging
            error_log("getStablePrices found " . count($result) . " results");
            
            return $result;
        } catch (Exception $e) {
            error_log("Error getting stable prices: " . $e->getMessage());
            return [];
        }
    }
    
    public function getStatistics() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_prices,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
                        COUNT(DISTINCT commodity_id) as total_commodities,
                        COUNT(DISTINCT market_id) as total_markets
                    FROM {$this->table}";
            
            $stmt = $this->query($sql);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting statistics: " . $e->getMessage());
            return [
                'total_prices' => 0,
                'pending_count' => 0,
                'approved_count' => 0,
                'rejected_count' => 0,
                'total_commodities' => 0,
                'total_markets' => 0
            ];
        }
    }
    
    public function getCommodityList() {
        try {
            $sql = "SELECT DISTINCT c.id, c.name AS commodity_name, c.unit
                    FROM {$this->table} p
                    JOIN commodities c ON p.commodity_id = c.id
                    WHERE p.status = 'approved'
                    ORDER BY c.name";
            
            $stmt = $this->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting commodity list: " . $e->getMessage());
            return [];
        }
    }
    
    public function getMarketList() {
        try {
            $sql = "SELECT DISTINCT ps.id_pasar, ps.nama_pasar AS market_name
                    FROM {$this->table} p
                    JOIN pasar ps ON p.market_id = ps.id_pasar
                    WHERE p.status = 'approved' 
                    ORDER BY ps.nama_pasar";
            
            $stmt = $this->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting market list: " . $e->getMessage());
            return [];
        }
    }
    
    public function searchPrices($filters) {
        try {
            $sql = "SELECT p.*, c.name AS commodity_name, c.unit,
                            ps.nama_pasar AS market_name,
                            u.full_name as uptd_name 
                    FROM {$this->table} p 
                    JOIN commodities c ON p.commodity_id = c.id
                    JOIN pasar ps ON p.market_id = ps.id_pasar
                    JOIN users u ON p.uptd_user_id = u.id 
                    WHERE p.status = 'approved'";
            
            $params = [];
            
            if (!empty($filters['commodity_id'])) {
                $sql .= " AND p.commodity_id = :commodity_id";
                $params[':commodity_id'] = $filters['commodity_id'];
            }
            
            if (!empty($filters['market'])) {
                $sql .= " AND ps.nama_pasar = :market";
                $params[':market'] = $filters['market'];
            }
            
            if (!empty($filters['date_from'])) {
                $sql .= " AND DATE(p.created_at) >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND DATE(p.created_at) <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }
            
            $sql .= " ORDER BY p.created_at DESC";
            
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error searching prices: " . $e->getMessage());
            return [];
        }
    }
    
    public function getLatestCommodityPrices($limit = 12) {
        try {
            // First, get the latest approved price for each commodity
            $sql = "SELECT 
                        c.id, 
                        c.name AS commodity_name, 
                        c.unit,
                        c.image_path,
                        (
                            SELECT p1.price 
                            FROM prices p1 
                            WHERE p1.commodity_id = c.id 
                            AND p1.status = 'approved'
                            ORDER BY p1.created_at DESC 
                            LIMIT 1
                        ) as latest_price,
                        (
                            SELECT p1.created_at 
                            FROM prices p1 
                            WHERE p1.commodity_id = c.id 
                            AND p1.status = 'approved'
                            ORDER BY p1.created_at DESC 
                            LIMIT 1
                        ) as created_at
                    FROM commodities c
                    HAVING latest_price IS NOT NULL
                    ORDER BY c.name ASC
                    LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log the results for debugging
            error_log("Latest commodity prices query results: " . print_r($results, true));
            
            return $results;
        } catch (Exception $e) {
            error_log("Error getting latest commodity prices: " . $e->getMessage());
            return [];
        }
    }
    
    public function getMonthlyTrends($months = 6) {
        try {
            $sql = "SELECT 
                        c.name AS commodity_name, c.unit,
                        DATE_FORMAT(p.created_at, '%Y-%m') as month,
                        AVG(p.price) as avg_price,
                        COUNT(*) as data_count
                    FROM {$this->table} p
                    JOIN commodities c ON p.commodity_id = c.id
                    WHERE p.status = 'approved' 
                    AND p.created_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
                    GROUP BY c.name, DATE_FORMAT(p.created_at, '%Y-%m')
                    ORDER BY month ASC, c.name";
            
            $stmt = $this->query($sql, [':months' => $months]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting monthly trends: " . $e->getMessage());
            return [];
        }
    }
    
    public function getSignificantPriceChanges($days = 30) {
        try {
            $sql = "SELECT 
                        c.name AS commodity_name, c.unit,
                        AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL :days1 DAY) THEN p.price END) as current_price,
                        AVG(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL :days2 DAY) 
                                AND p.created_at < DATE_SUB(NOW(), INTERVAL :days3 DAY) THEN p.price END) as previous_price
                    FROM {$this->table} p
                    JOIN commodities c ON p.commodity_id = c.id
                    WHERE p.status = 'approved' 
                    AND p.created_at >= DATE_SUB(NOW(), INTERVAL :days4 DAY)
                    GROUP BY c.name
                    HAVING current_price IS NOT NULL AND previous_price IS NOT NULL
                    AND ABS((current_price - previous_price) / previous_price * 100) >= 5
                    ORDER BY ABS((current_price - previous_price) / previous_price) DESC";
            
            $params = [
                ':days1' => $days,
                ':days2' => $days * 2,
                ':days3' => $days,
                ':days4' => $days * 2
            ];
            
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting significant price changes: " . $e->getMessage());
            return [];
        }
    }
    
    public function getPricesByMarketAndDateRange($date, $range = 7, $uptdId = null) {
        try {
            $startDate = date('Y-m-d', strtotime($date . ' -' . $range . ' days'));
            $endDate = date('Y-m-d', strtotime($date . ' +' . $range . ' days'));
            
            $sql = "SELECT 
                        c.name AS commodity_name, c.unit,
                        ps.nama_pasar AS market_name,
                        AVG(p.price) as avg_price,
                        DATE(p.created_at) as price_date
                    FROM {$this->table} p
                    JOIN commodities c ON p.commodity_id = c.id
                    JOIN pasar ps ON p.market_id = ps.id_pasar
                    WHERE p.status = 'approved'
                    AND DATE(p.created_at) BETWEEN :start_date AND :end_date";
            
            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];
            
            if ($uptdId) {
                $sql .= " AND p.uptd_user_id = :uptd_id";
                $params[':uptd_id'] = $uptdId;
            }
            
            $sql .= " GROUP BY c.name, ps.nama_pasar, DATE(p.created_at)
                      ORDER BY c.name, ps.nama_pasar, MAX(p.created_at)";
            
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting prices by market and date range: " . $e->getMessage());
            return [];
        }
    }
    
    public function getPriceTrendsWithComparison($days = 7, $date = null, $uptdId = null) {
        try {
            if (!$date) {
                $date = date('Y-m-d');
            }
            
            $comparisonDate = date('Y-m-d', strtotime($date . ' -' . $days . ' days'));
            
            $sql = "SELECT 
                        c.name AS commodity_name, c.unit,
                        DATE(p.created_at) as price_date,
                        AVG(p.price) as avg_price,
                        (SELECT AVG(p2.price) FROM {$this->table} p2 WHERE p2.commodity_id = c.id AND DATE(p2.created_at) = :current_date AND p2.status = 'approved') as current_price,
                        (SELECT AVG(p3.price) FROM {$this->table} p3 WHERE p3.commodity_id = c.id AND DATE(p3.created_at) = :comparison_date AND p3.status = 'approved') as previous_price
                    FROM {$this->table} p
                    JOIN commodities c ON p.commodity_id = c.id
                    WHERE p.status = 'approved'
                    AND DATE(p.created_at) BETWEEN DATE_SUB(:start_date, INTERVAL :days_before DAY) 
                    AND DATE_ADD(:end_date, INTERVAL :days_after DAY)";
            
            $params = [
                ':current_date' => $date,
                ':comparison_date' => $comparisonDate,
                ':days_before' => $days,
                ':days_after' => $days,
                ':start_date' => $comparisonDate,
                ':end_date' => $date
            ];
            
            if ($uptdId) {
                $sql .= " AND p.uptd_user_id = :uptd_id";
                $params[':uptd_id'] = $uptdId;
            }
            
            $sql .= " GROUP BY c.name, DATE(p.created_at)
                      ORDER BY c.name, price_date ASC";
            
            $stmt = $this->query($sql, $params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as &$item) {
                if (!empty($item['current_price']) && !empty($item['previous_price']) && $item['previous_price'] > 0) {
                    $item['percentage_change'] = (($item['current_price'] - $item['previous_price']) / $item['previous_price']) * 100;
                    
                    if ($item['percentage_change'] > 5) { $item['change_category'] = 'naik_tinggi'; } 
                    elseif ($item['percentage_change'] > 0) { $item['change_category'] = 'naik_rendah'; } 
                    elseif ($item['percentage_change'] == 0) { $item['change_category'] = 'tetap'; } 
                    elseif ($item['percentage_change'] >= -5) { $item['change_category'] = 'turun_rendah'; } 
                    else { $item['change_category'] = 'turun_tinggi'; }
                } else {
                    $item['percentage_change'] = null;
                    $item['change_category'] = 'tidak_ada_perbandingan';
                }
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Error in getPriceTrendsWithComparison: " . $e->getMessage());
            return [];
        }
    }

    public function getCommodityPriceComparison($selectedDate, $comparisonDays, $uptdId = null) {
        try {
            $comparisonDate = date('Y-m-d', strtotime("$selectedDate - $comparisonDays days"));
            $startDate = date('Y-m-d', strtotime("$comparisonDate - 7 days")); // Get 7 days before comparison date for chart data
            
            // First, get the basic price comparison data
            $sql = "SELECT 
                        c.id,
                        c.name AS commodity_name,
                        c.unit,
                        c.chart_color,
                        c.image_path,
                        (SELECT AVG(p.price)
                         FROM prices p
                         WHERE p.commodity_id = c.id
                         AND DATE(p.created_at) = :selected_date
                         AND p.status = 'approved'
                         GROUP BY p.commodity_id) as selected_date_price,
                        (SELECT AVG(p2.price)
                         FROM prices p2
                         WHERE p2.commodity_id = c.id
                         AND DATE(p2.created_at) = :comparison_date
                         AND p2.status = 'approved'
                         GROUP BY p2.commodity_id) as comparison_date_price
                    FROM commodities c
                    WHERE 1=1";
            
            $params = [
                ':selected_date' => $selectedDate,
                ':comparison_date' => $comparisonDate
            ];
            
            if ($uptdId) {
                $sql .= " AND c.id IN (
                    SELECT DISTINCT commodity_id 
                    FROM prices 
                    WHERE uptd_user_id = :uptd_id 
                    AND status = 'approved'
                )";
                $params[':uptd_id'] = $uptdId;
            }
            
            $sql .= " GROUP BY c.id, c.name, c.unit, c.chart_color
                     ORDER BY c.name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get price history for each commodity for the chart
            $priceHistorySql = "SELECT 
                c.id as commodity_id,
                DATE(p.created_at) as date,
                AVG(p.price) as avg_price
                FROM commodities c
                JOIN prices p ON p.commodity_id = c.id
                WHERE p.status = 'approved'
                AND DATE(p.created_at) BETWEEN :start_date AND :selected_date
                " . ($uptdId ? " AND p.uptd_user_id = :uptd_id" : "") . "
                GROUP BY c.id, DATE(p.created_at)
                ORDER BY c.id, DATE(p.created_at)";
                
            $historyStmt = $this->db->prepare($priceHistorySql);
            $historyParams = [
                ':start_date' => $startDate,
                ':selected_date' => $selectedDate
            ];
            if ($uptdId) {
                $historyParams[':uptd_id'] = $uptdId;
            }
            $historyStmt->execute($historyParams);
            $priceHistory = $historyStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
            
            // Process results and add chart data
            foreach ($results as &$item) {
                $item['selected_date_price'] = $item['selected_date_price'] !== null ? 
                    (float)$item['selected_date_price'] : null;
                $item['comparison_date_price'] = $item['comparison_date_price'] !== null ? 
                    (float)$item['comparison_date_price'] : null;
                    
                if ($item['selected_date_price'] !== null && 
                    $item['comparison_date_price'] !== null && 
                    $item['comparison_date_price'] > 0) {
                    $item['percentage_change'] = (($item['selected_date_price'] - $item['comparison_date_price']) / 
                                               $item['comparison_date_price']) * 100;
                } else {
                    $item['percentage_change'] = null;
                }
                
                // Add chart data if available
                $item['chart_data_formatted'] = [];
                if (isset($priceHistory[$item['id']])) {
                    foreach ($priceHistory[$item['id']] as $history) {
                        $item['chart_data_formatted'][] = [
                            'date' => $history['date'],
                            'price' => (float)$history['avg_price']
                        ];
                    }
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Error in getCommodityPriceComparison: " . $e->getMessage());
            return [];
        }
    }

    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance()->getConnection();
    }
    
    // Pastikan fungsi query dasar berfungsi dengan baik
    public function query($sql, $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database error in Price::query: " . $e->getMessage());
            throw $e;
        }
    }
}