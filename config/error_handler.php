<?php
function errorHandler($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Standards',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated',
    ];

    $errorType = $errorTypes[$errno] ?? 'Unknown Error';
    $errorMessage = "$errorType: $errstr in $errfile on line $errline";
    
    // Log the error
    error_log($errorMessage);
    
    // Don't execute PHP internal error handler
    return true;
}

function exceptionHandler($exception) {
    error_log("Uncaught exception: " . $exception->getMessage() . 
              " in " . $exception->getFile() . ":" . $exception->getLine());
    
    // Show user-friendly error page
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
    }
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        include __DIR__ . '/../views/errors/500.php';
    } else {
        echo "<h1>An error occurred</h1>";
        echo "<p>Please try again later.</p>";
    }
    exit;
}

// Set error and exception handlers
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $logMessage = date('[Y-m-d H:i:s]') . " Error [$errno] $errstr in $errfile on line $errline" . PHP_EOL;
    error_log($logMessage, 3, __DIR__ . '/../logs/error.log');
    
    if (ini_get('display_errors')) {
        echo "<div class='alert alert-danger'>Terjadi kesalahan. Silakan coba lagi nanti.</div>";
    }
    
    return true;
});

set_exception_handler(function($e) {
    $logMessage = date('[Y-m-d H:i:s]') . " Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . PHP_EOL;
    error_log($logMessage, 3, __DIR__ . '/../logs/error.log');
    
    http_response_code(500);
    if (ini_get('display_errors')) {
        echo "<div class='alert alert-danger'>Terjadi kesalahan. Silakan coba lagi nanti.</div>";
    }
});

// Display errors only in development
ini_set('display_errors', defined('ENVIRONMENT') && ENVIRONMENT === 'development' ? 1 : 0);
error_reporting(E_ALL);