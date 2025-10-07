<?php
require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel {
    protected $table = 'users';
    protected $fillable = ['username', 'email', 'password', 'full_name', 'role', 'is_active'];
    
    public function __construct() {
        parent::__construct();
    }
    
    public function authenticate($username, $password) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE username = :username AND is_active = 1";
            $stmt = $this->query($sql, [':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
            if ($user && password_verify($password, $user['password'])) {
                return $user;
            }
            return false;
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    public function createSession($userId) {
        try {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $sql = "INSERT INTO user_sessions (user_id, session_token, expires_at) 
                    VALUES (:user_id, :token, :expires_at)";
                    
            $this->query($sql, [
                ':user_id' => $userId,
                ':token' => $token,
                ':expires_at' => $expiresAt
            ]);
            
            return $token;
        } catch (Exception $e) {
            error_log("Session creation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getBySession($token) {
        try {
            $sql = "SELECT u.* FROM {$this->table} u 
                    JOIN user_sessions s ON u.id = s.user_id 
                    WHERE s.session_token = :token 
                    AND s.expires_at > NOW() 
                    AND u.is_active = 1";
                    
            $stmt = $this->query($sql, [':token' => $token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function destroySession($token) {
        try {
            $sql = "DELETE FROM user_sessions WHERE session_token = :token";
            $stmt = $this->query($sql, [':token' => $token]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Session destruction error: " . $e->getMessage());
            return false;
        }
    }
    
    public function cleanExpiredSessions() {
        try {
            $sql = "DELETE FROM user_sessions WHERE expires_at < NOW()";
            $stmt = $this->query($sql);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Error cleaning expired sessions: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Find user by username
     */
    public function findByUsername($username) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE username = :username";
            $stmt = $this->query($sql, [':username' => $username]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error finding user by username: " . $e->getMessage());
            return false;
        }
    }
    
    public function create($data) {
        try {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $userData = [
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $hashedPassword,
                'full_name' => $data['full_name'],
                'role' => $data['role'],
                'market_assigned' => $data['market_assigned'] ?? null,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $fields = [];
            $placeholders = [];
            $values = [];
            
            foreach ($userData as $key => $value) {
                $fields[] = $key;
                $placeholders[] = ":$key";
                $values[":$key"] = $value;
            }
            
            $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") " .
                   "VALUES (" . implode(', ', $placeholders) . ")";
            
            $this->query($sql, $values);
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAll($role = null) {
        try {
            $sql = "SELECT u.id, u.username, u.email, u.full_name, u.role, 
                           u.market_assigned, u.is_active, u.created_at,
                           ps.nama_pasar AS market_name
                    FROM {$this->table} u
                    LEFT JOIN pasar ps ON u.market_assigned = ps.id_pasar";
            $params = [];
            
            if ($role) {
                $sql .= " WHERE u.role = :role";
                $params[':role'] = $role;
            }
            
            $sql .= " ORDER BY u.created_at DESC";
            
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting users: " . $e->getMessage());
            return [];
        }
    }
    
    public function getById($id) {
        try {
            $sql = "SELECT u.id, u.username, u.email, u.full_name, u.role, 
                           u.market_assigned, u.is_active, u.created_at,
                           ps.nama_pasar AS market_name
                    FROM {$this->table} u
                    LEFT JOIN pasar ps ON u.market_assigned = ps.id_pasar
                    WHERE u.id = :id";
                    
            $stmt = $this->query($sql, [':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting user by ID: " . $e->getMessage());
            return false;
        }
    }
    
    public function update($id, $data) {
        try {
            // Don't update password here, use updatePassword method instead
            unset($data['password']);
            
            $updates = [];
            $values = [':id' => $id];
            
            foreach ($data as $key => $value) {
                $updates[] = "$key = :$key";
                $values[":$key"] = $value;
            }
            
            $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->query($sql, $values);
            
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            error_log("Error updating user: " . $e->getMessage());
            return false;
        }
    }
    
    public function updatePassword($id, $newPassword) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql = "UPDATE {$this->table} SET password = :password WHERE id = :id";
            
            $stmt = $this->query($sql, [
                ':password' => $hashedPassword,
                ':id' => $id
            ]);
            
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            error_log("Error updating password: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete($id) {
        try {
            $sql = "UPDATE {$this->table} SET is_active = 0 WHERE id = :id";
            $stmt = $this->query($sql, [':id' => $id]);
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            error_log("Error deactivating user: " . $e->getMessage());
            return false;
        }
    }
    
    public function isUsernameExists($username, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE username = :username";
            $params = [':username' => $username];
            
            if ($excludeId) {
                $sql .= " AND id != :exclude_id";
                $params[':exclude_id'] = $excludeId;
            }
            
            $stmt = $this->query($sql, $params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
            
        } catch (Exception $e) {
            error_log("Error checking username existence: " . $e->getMessage());
            return false;
        }
    }
    
    public function isEmailExists($email, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE email = :email";
            $params = [':email' => $email];
            
            if ($excludeId) {
                $sql .= " AND id != :exclude_id";
                $params[':exclude_id'] = $excludeId;
            }
            
            $stmt = $this->query($sql, $params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
            
        } catch (Exception $e) {
            error_log("Error checking email existence: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUptdUsers() {
        try {
            $sql = "SELECT u.id, u.username, u.full_name, u.market_assigned,
                           ps.nama_pasar AS market_name
                    FROM {$this->table} u
                    LEFT JOIN pasar ps ON u.market_assigned = ps.id_pasar
                    WHERE u.role = 'uptd' AND u.is_active = 1
                    ORDER BY ps.nama_pasar";
                    
            $stmt = $this->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting UPTD users: " . $e->getMessage());
            return [];
        }
    }
    
    public function getUserStats() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_users,
                        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
                        SUM(CASE WHEN role = 'uptd' THEN 1 ELSE 0 END) as uptd_count,
                        SUM(CASE WHEN role = 'pengawas' THEN 1 ELSE 0 END) as pengawas_count,
                        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
                    FROM {$this->table}";
                    
            $stmt = $this->query($sql);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting user stats: " . $e->getMessage());
            return [
                'total_users' => 0,
                'admin_count' => 0,
                'uptd_count' => 0,
                'pengawas_count' => 0,
                'active_count' => 0
            ];
        }
    }
    
    public function getMarketList() {
        try {
            $sql = "SELECT id_pasar as id, nama_pasar as name FROM pasar WHERE is_active = 1 ORDER BY nama_pasar";
            $stmt = $this->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting market list: " . $e->getMessage());
            return [];
        }
    }
}
?>