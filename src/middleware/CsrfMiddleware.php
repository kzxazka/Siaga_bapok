<?php
class CsrfMiddleware {
    public static function generateToken() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateToken($token) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        $valid = hash_equals($_SESSION['csrf_token'], $token);
        
        // Regenerate token after validation
        if ($valid) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $valid;
    }
    
    public static function getToken() {
        return self::generateToken();
    }
}