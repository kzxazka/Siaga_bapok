<?php
/**
 * API Authentication Controller
 * 
 * Menangani autentikasi untuk API dengan JWT token
 */

class ApiAuthController {
    private $db;
    private $secretKey;
    
    public function __construct() {
        $this->db = new Database();
        $this->secretKey = 'siagabapok_jwt_secret_key'; // Idealnya disimpan di environment variable
    }
    
    /**
     * Login user dan return user data
     */
    public function login($username, $password) {
        $sql = "SELECT id, username, email, password, full_name, role, market_assigned 
                FROM users 
                WHERE username = ? AND is_active = 1";
        
        $user = $this->db->fetchOne($sql, [$username]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Username tidak ditemukan'];
        }
        
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Password salah'];
        }
        
        // Remove password from user data
        unset($user['password']);
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * Logout user
     */
    public function logout($userId) {
        // Untuk JWT, tidak perlu invalidate token di server
        // Token akan expire sendiri, client harus menghapus token
        return true;
    }
    
    /**
     * Generate JWT token
     */
    public function generateToken($user) {
        $issuedAt = time();
        $expirationTime = $issuedAt + 3600; // Token valid selama 1 jam
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'data' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ];
        
        return $this->encodeToken($payload);
    }
    
    /**
     * Verify JWT token
     */
    public function verifyToken($token) {
        try {
            $payload = $this->decodeToken($token);
            
            if ($payload === false) {
                return false;
            }
            
            // Check if token is expired
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return false;
            }
            
            // Get user data
            $userId = $payload['data']['id'];
            $sql = "SELECT id, username, email, full_name, role, market_assigned 
                    FROM users 
                    WHERE id = ? AND is_active = 1";
            
            $user = $this->db->fetchOne($sql, [$userId]);
            
            if (!$user) {
                return false;
            }
            
            return $user;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Encode JWT token
     */
    private function encodeToken($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $header = $this->base64UrlEncode($header);
        
        $payload = json_encode($payload);
        $payload = $this->base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', "$header.$payload", $this->secretKey, true);
        $signature = $this->base64UrlEncode($signature);
        
        return "$header.$payload.$signature";
    }
    
    /**
     * Decode JWT token
     */
    private function decodeToken($token) {
        $tokenParts = explode('.', $token);
        
        if (count($tokenParts) !== 3) {
            return false;
        }
        
        list($header, $payload, $signature) = $tokenParts;
        
        $decodedSignature = $this->base64UrlDecode($signature);
        $expectedSignature = hash_hmac('sha256', "$header.$payload", $this->secretKey, true);
        
        if (!hash_equals($decodedSignature, $expectedSignature)) {
            return false;
        }
        
        $payload = json_decode($this->base64UrlDecode($payload), true);
        
        return $payload;
    }
    
    /**
     * Base64 URL encode
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
?>