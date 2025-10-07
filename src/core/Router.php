<?php

class Router {
    private $routes = [];
    private $notFoundCallback;
    
    public function __construct() {
        // Enable error reporting in development
        if (getenv('APP_ENV') === 'development') {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
        }
        
        // Set default timezone
        date_default_timezone_set('Asia/Jakarta');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => isset($_SERVER['HTTPS']),
                'cookie_samesite' => 'Lax'
            ]);
        }
    }
    
    public function get($path, $callback) {
        $this->addRoute('GET', $path, $callback);
    }
    
    public function post($path, $callback) {
        $this->addRoute('POST', $path, $callback);
    }
    
    public function put($path, $callback) {
        $this->addRoute('PUT', $path, $callback);
    }
    
    public function delete($path, $callback) {
        $this->addRoute('DELETE', $path, $callback);
    }
    
    public function any($path, $callback) {
        $this->addRoute(['GET', 'POST', 'PUT', 'DELETE'], $path, $callback);
    }
    
    public function group($prefix, $callback) {
        $previousGroupPrefix = $this->currentGroupPrefix ?? '';
        $this->currentGroupPrefix = $previousGroupPrefix . $prefix;
        
        call_user_func($callback, $this);
        
        $this->currentGroupPrefix = $previousGroupPrefix;
    }
    
    public function middleware($middleware, $callback) {
        $previousMiddleware = $this->currentMiddleware ?? [];
        $this->currentMiddleware = array_merge($previousMiddleware, (array)$middleware);
        
        call_user_func($callback, $this);
        
        $this->currentMiddleware = $previousMiddleware;
    }
    
    public function notFound($callback) {
        $this->notFoundCallback = $callback;
    }
    
    private function addRoute($methods, $path, $callback) {
        if (!is_array($methods)) {
            $methods = [$methods];
        }
        
        // Apply group prefix if exists
        $path = ($this->currentGroupPrefix ?? '') . $path;
        
        // Ensure path starts with a slash
        $path = '/' . ltrim($path, '/');
        
        // Store the route with its middleware
        foreach ($methods as $method) {
            $this->routes[$method][$path] = [
                'callback' => $callback,
                'middleware' => $this->currentMiddleware ?? []
            ];
        }
    }
    
    public function run() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->getRequestUri();
        
        // Check for method override
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }
        
        // Try to find a matching route
        $routeFound = false;
        
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $route => $routeData) {
                $pattern = $this->convertToRegex($route);
                
                if (preg_match($pattern, $uri, $matches)) {
                    $routeFound = true;
                    
                    // Extract named parameters
                    $params = [];
                    foreach ($matches as $key => $value) {
                        if (!is_numeric($key)) {
                            $params[$key] = $value;
                        }
                    }
                    
                    // Apply middleware
                    if (!empty($routeData['middleware'])) {
                        foreach ($routeData['middleware'] as $middleware) {
                            $this->applyMiddleware($middleware);
                        }
                    }
                    
                    // Execute the callback
                    $this->executeCallback($routeData['callback'], $params);
                    break;
                }
            }
        }
        
        // No route found
        if (!$routeFound) {
            $this->handleNotFound();
        }
    }
    
    private function convertToRegex($route) {
        // Convert route parameters to regex patterns
        $pattern = preg_replace('/\//', '\/', $route);
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^\/]+)', $pattern);
        $pattern = "/^{$pattern}$/";
        
        return $pattern;
    }
    
    private function getRequestUri() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        
        // Remove base path from URI
        if (strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        
        // Ensure URI starts with a slash
        $uri = '/' . ltrim($uri, '/');
        
        return $uri;
    }
    
    private function applyMiddleware($middleware) {
        if (is_callable($middleware)) {
            call_user_func($middleware);
        } elseif (is_string($middleware) && class_exists($middleware)) {
            $middlewareInstance = new $middleware();
            if (method_exists($middlewareInstance, 'handle')) {
                $middlewareInstance->handle();
            }
        }
    }
    
    private function executeCallback($callback, $params = []) {
        if (is_callable($callback)) {
            call_user_func_array($callback, $params);
        } elseif (is_string($callback) && strpos($callback, '@') !== false) {
            list($controller, $method) = explode('@', $callback, 2);
            $controller = 'App\\Controllers\\' . $controller;
            
            if (class_exists($controller)) {
                $controllerInstance = new $controller();
                
                if (method_exists($controllerInstance, $method)) {
                    call_user_func_array([$controllerInstance, $method], $params);
                    return;
                }
            }
        }
        
        // If we get here, the callback is invalid
        $this->handleNotFound();
    }
    
    private function handleNotFound() {
        if ($this->notFoundCallback) {
            call_user_func($this->notFoundCallback);
        } else {
            header('HTTP/1.0 404 Not Found');
            echo '404 Not Found';
        }
    }
    
    // Helper method to redirect
    public static function redirect($url, $statusCode = 302) {
        header('Location: ' . $url, true, $statusCode);
        exit();
    }
    
    // Helper method to get the current URL
    public static function currentUrl() {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }
    
    // Helper method to get the base URL
    public static function baseUrl($path = '') {
        $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$basePath";
        
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
