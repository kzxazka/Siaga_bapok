<?php
require_once __DIR__ . '/../../config/database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function authenticate($username, $password) {
    $sql = "SELECT * FROM users WHERE username = ? AND is_active = 1";
    $user = $this->db->fetchOne($sql, [$username]);

    if ($user) {
        var_dump("Input password:", $password);
        var_dump("Hash in DB:", $user['password']);
        var_dump("Verify result:", password_verify($password, $user['password']));
        exit;
    }

    return false;
}
    
    public function createSession($userId) {
        $token = generateSessionToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $sql = "INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)";
        $this->db->execute($sql, [$userId, $token, $expiresAt]);
        
        return $token;
    }
    
    public function getBySession($token) {
        $sql = "SELECT u.* FROM users u 
                JOIN user_sessions s ON u.id = s.user_id 
                WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = 1";
        return $this->db->fetchOne($sql, [$token]);
    }
    
    public function destroySession($token) {
        $sql = "DELETE FROM user_sessions WHERE session_token = ?";
        return $this->db->execute($sql, [$token]);
    }
    
    public function cleanExpiredSessions() {
        $sql = "DELETE FROM user_sessions WHERE expires_at < NOW()";
        return $this->db->execute($sql);
    }
    
    public function create($data) {
        $sql = "INSERT INTO users (username, email, password, full_name, role, market_assigned) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        return $this->db->execute($sql, [
            $data['username'],
            $data['email'],
            $hashedPassword,
            $data['full_name'],
            $data['role'],
            $data['market_assigned'] ?? null
        ]);
    }
    
    public function getAll($role = null) {
        $sql = "SELECT u.id, u.username, u.email, u.full_name, u.role, 
                       u.market_assigned, u.is_active, u.created_at,
                       ps.nama_pasar AS market_name
                FROM users u
                LEFT JOIN pasar ps ON u.market_assigned = ps.id_pasar";
        $params = [];
        
        if ($role) {
            $sql .= " WHERE u.role = ?";
            $params[] = $role;
        }
        
        $sql .= " ORDER BY u.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getById($id) {
        $sql = "SELECT u.id, u.username, u.email, u.full_name, u.role, 
                       u.market_assigned, u.is_active, u.created_at,
                       ps.nama_pasar AS market_name
                FROM users u
                LEFT JOIN pasar ps ON u.market_assigned = ps.id_pasar
                WHERE u.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    public function update($id, $data) {
        $sql = "UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, market_assigned = ?, is_active = ? WHERE id = ?";
        
        return $this->db->execute($sql, [
            $data['username'],
            $data['email'],
            $data['full_name'],
            $data['role'],
            $data['market_assigned'] ?? null,
            $data['is_active'],
            $id
        ]);
    }
    
    public function updatePassword($id, $newPassword) {
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        return $this->db->execute($sql, [$hashedPassword, $id]);
    }
    
    public function delete($id) {
        $sql = "UPDATE users SET is_active = 0 WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    public function isUsernameExists($username, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE username = ?";
        $params = [$username];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result['count'] > 0;
    }
    
    public function isEmailExists($email, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result['count'] > 0;
    }
    
    public function getUptdUsers() {
        $sql = "SELECT u.id, u.username, u.full_name, u.market_assigned,
                       ps.nama_pasar AS market_name
                FROM users u
                LEFT JOIN pasar ps ON u.market_assigned = ps.id_pasar
                WHERE u.role = 'uptd' AND u.is_active = 1 
                ORDER BY u.full_name";
        return $this->db->fetchAll($sql);
    }
    
    public function getUserStats() {
        $sql = "SELECT 
                    role,
                    COUNT(*) as count,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
                FROM users 
                GROUP BY role";
        return $this->db->fetchAll($sql);
    }
    
    public function getMarketList() {
        $sql = "SELECT id_pasar, nama_pasar FROM pasar ORDER BY nama_pasar";
        return $this->db->fetchAll($sql);
    }
}
?>