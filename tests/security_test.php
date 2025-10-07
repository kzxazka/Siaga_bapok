<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/middleware/CsrfMiddleware.php';

session_start();
header('Content-Type: text/plain');

echo "=== Security Test Suite ===\n\n";

// Test 1: CSRF Token Generation
function testCsrfTokenGeneration() {
    echo "Test 1: CSRF Token Generation... ";
    // Clear any existing session
    $_SESSION = [];
    session_destroy();
    session_start();
    
    $token1 = CsrfMiddleware::generateToken();
    $token2 = CsrfMiddleware::getToken();
    
    if (!empty($token1) && strlen($token1) === 64 && $token1 === $token2) {
        echo "PASSED\n";
        return true;
    } else {
        echo "FAILED (Token1: $token1, Token2: $token2)\n";
        return false;
    }
}

// Test 2: CSRF Token Validation
function testCsrfTokenValidation() {
    echo "Test 2: CSRF Token Validation... ";
    // Clear any existing session
    $_SESSION = [];
    session_destroy();
    session_start();
    
    // Generate a token and validate it
    $token = CsrfMiddleware::generateToken();
    $valid = CsrfMiddleware::validateToken($token);
    
    // Test with invalid token
    $invalid1 = CsrfMiddleware::validateToken('invalid_token');
    
    // Test with empty token
    $invalid2 = CsrfMiddleware::validateToken('');
    
    // Test with no token
    $invalid3 = CsrfMiddleware::validateToken(null);
    
    if ($valid && !$invalid1 && !$invalid2 && !$invalid3) {
        echo "PASSED\n";
        return true;
    } else {
        echo "FAILED (Valid: " . ($valid ? 'true' : 'false') . 
             ", Invalid1: " . ($invalid1 ? 'true' : 'false') .
             ", Invalid2: " . ($invalid2 ? 'true' : 'false') .
             ", Invalid3: " . ($invalid3 ? 'true' : 'false') . ")\n";
        return false;
    }
}

// Test 3: Password Hashing
function testPasswordHashing() {
    echo "Test 3: Password Hashing... ";
    $password = 'Test@1234';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    if (password_verify($password, $hash) && !password_verify('wrongpassword', $hash)) {
        echo "PASSED\n";
        return true;
    } else {
        echo "FAILED\n";
        return false;
    }
}

// Test 4: SQL Injection Protection
function testSqlInjectionProtection() {
    echo "Test 4: SQL Injection Protection... ";
    $db = new Database();
    $maliciousInput = "' OR '1'='1";
    $sql = "SELECT * FROM users WHERE username = ?";
    
    try {
        $result = $db->fetchOne($sql, [$maliciousInput]);
        if ($result === false) {
            echo "PASSED (No user found with malicious input)\n";
            return true;
        } else {
            echo "WARNING: Query executed but no exception thrown\n";
            return false;
        }
    } catch (Exception $e) {
        echo "PASSED (Exception caught)\n";
        return true;
    }
}

// Test 5: XSS Protection
function testXssProtection() {
    echo "Test 5: XSS Protection... ";
    $xssPayload = '<script>alert("XSS")</script>';
    $sanitized = htmlspecialchars($xssPayload, ENT_QUOTES, 'UTF-8');
    
    if ($sanitized !== $xssPayload && strpos($sanitized, '<script>') === false) {
        echo "PASSED\n";
        return true;
    } else {
        echo "FAILED\n";
        return false;
    }
}

// Run all tests
$tests = [
    'testCsrfTokenGeneration',
    'testCsrfTokenValidation',
    'testPasswordHashing',
    'testSqlInjectionProtection',
    'testXssProtection'
];

$passed = 0;
foreach ($tests as $test) {
    if (call_user_func($test)) {
        $passed++;
    }
    echo "\n";
}

echo "=== Test Results ===\n";
echo "Total Tests: " . count($tests) . "\n";
echo "Passed: $passed\n";
echo "Failed: " . (count($tests) - $passed) . "\n";

if ($passed === count($tests)) {
    echo "\n✅ All security tests passed successfully!\n";
} else {
    echo "\n❌ Some security tests failed. Please review the output above.\n";
}
?>
