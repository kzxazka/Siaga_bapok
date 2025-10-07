<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/middleware/CsrfMiddleware.php';

class SecurityTest extends PHPUnit\Framework\TestCase {
    private $auth;
    private $db;
    
    protected function setUp(): void {
        $this->auth = new AuthController();
        $this->db = new Database();
        $_SESSION = [];
    }
    
    public function testCsrfTokenGeneration() {
        $token1 = CsrfMiddleware::generateToken();
        $token2 = CsrfMiddleware::generateToken();
        $this->assertEquals($token1, $token2, 'CSRF token should remain the same in the same session');
        $this->assertNotEmpty($token1, 'CSRF token should not be empty');
        $this->assertEquals(64, strlen($token1), 'CSRF token should be 64 characters long');
    }
    
    public function testCsrfTokenValidation() {
        $token = CsrfMiddleware::generateToken();
        $this->assertTrue(CsrfMiddleware::validateToken($token), 'Valid token should pass validation');
        $this->assertFalse(CsrfMiddleware::validateToken('invalid_token'), 'Invalid token should fail validation');
    }
    
    public function testPasswordHashing() {
        $password = 'Test@1234';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->assertTrue(password_verify($password, $hash), 'Password should verify against its hash');
        $this->assertFalse(password_verify('wrongpassword', $hash), 'Wrong password should not verify');
    }
    
    public function testSqlInjectionProtection() {
        $maliciousInput = "' OR '1'='1";
        $sql = "SELECT * FROM users WHERE username = ?";
        $result = $this->db->fetchOne($sql, [$maliciousInput]);
        $this->assertFalse($result, 'Should not find user with malicious input');
    }
    
    public function testXssProtection() {
        $xssPayload = '<script>alert("XSS")</script>';
        $sanitized = htmlspecialchars($xssPayload, ENT_QUOTES, 'UTF-8');
        $this->assertNotEquals($xssPayload, $sanitized, 'XSS payload should be sanitized');
        $this->assertStringNotContainsString('<script>', $sanitized, 'Script tags should be escaped');
    }
    
    public function testRateLimiting() {
        $ip = '127.0.0.1';
        $key = 'login_attempts_' . $ip;
        
        // Reset attempts
        $_SESSION[$key] = ['count' => 4, 'time' => time()];
        
        // Should allow 5th attempt
        $this->assertFalse($this->isRateLimited($ip), 'Should allow 5th attempt');
        
        // Should block 6th attempt
        $_SESSION[$key]['count'] = 5;
        $this->assertTrue($this->isRateLimited($ip), 'Should block 6th attempt');
    }
    
    private function isRateLimited($ip) {
        $key = 'login_attempts_' . $ip;
        $maxAttempts = 5;
        $lockoutDuration = 900; // 15 minutes
        
        $now = time();
        $attempts = $_SESSION[$key] ?? ['count' => 0, 'time' => $now];
        
        if (($now - $attempts['time']) > $lockoutDuration) {
            return false;
        }
        
        return $attempts['count'] >= $maxAttempts;
    }
}
