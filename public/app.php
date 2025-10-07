<?php
/**
 * SIAGABAPOK - Main Application Entry Point
 */

// Define application paths
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/src');
define('PUBLIC_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . '/storage');

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set default timezone
date_default_timezone_set('Asia/Jakarta');

// Load Composer autoloader if exists
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

// Load environment variables
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

// Load configuration
$config = require APP_PATH . '/config/app.php';

// Register error handler
if ($config['debug']) {
    $whoops = new \Whoops\Run;
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
    $whoops->register();
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    
    // Register production error handler
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        error_log("Error [$errno] $errstr in $errfile on line $errline");
        http_response_code(500);
        include APP_PATH . '/views/errors/500.php';
        exit;
    });
    
    set_exception_handler(function($exception) {
        error_log("Uncaught exception: " . $exception->getMessage());
        http_response_code(500);
        include APP_PATH . '/views/errors/500.php';
        exit;
    });
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'name' => 'siagabapok_session',
        'cookie_lifetime' => 86400, // 24 hours
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
        'use_cookies' => true,
        'use_only_cookies' => true
    ]);
}

// Initialize database connection
require_once APP_PATH . '/config/database.php';

// Load helpers
require_once APP_PATH . '/helpers/security.php';
require_once APP_PATH . '/helpers/logger.php';
require_once APP_PATH . '/helpers/functions.php';

// Initialize router
require_once APP_PATH . '/core/Router.php';
$router = new \App\Core\Router();

// Load routes
require APP_PATH . '/routes/web.php';

// Handle the request
$router->run();
