<?php
// Set session cookie parameters
session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,     // Only send over HTTPS
    'httponly' => true,   // Prevent JavaScript access
    'samesite' => 'Lax'   // CSRF protection
]);

// Start session
session_start();