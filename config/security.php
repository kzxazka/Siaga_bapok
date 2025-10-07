<?php
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
    return true;
}
function e($string, $flags = ENT_QUOTES | ENT_HTML5, $encoding = 'UTF-8') {
    return htmlspecialchars($string, $flags, $encoding);
}

function safe_json_encode($value, $options = 0, $depth = 512) {
    $json = json_encode($value, $options, $depth);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \RuntimeException('JSON encode error: ' . json_last_error_msg());
    }
    return $json;
}