<?php
// Public Routes
$router->get('/', 'PublicController@index');
$router->get('/prices', 'PublicController@prices');
$router->get('/prices/export', 'PublicController@exportPrices');
$router->get('/api/prices', 'PublicController@apiPrices');

// Authentication Routes
$router->group('/auth', function($router) {
    $router->get('/login', 'AuthController@showLoginForm');
    $router->post('/login', 'AuthController@login');
    $router->post('/logout', 'AuthController@logout');
    $router->get('/forgot-password', 'AuthController@showForgotPasswordForm');
    $router->post('/forgot-password', 'AuthController@sendResetLink');
    $router->get('/reset-password/{token}', 'AuthController@showResetForm');
    $router->post('/reset-password', 'AuthController@reset');
});

// Admin Routes
$router->group('/admin', function($router) {
    // Dashboard
    $router->get('/dashboard', 'Admin\DashboardController@index');
    
    // Users Management
    $router->get('/users', 'Admin\UserController@index');
    $router->get('/users/create', 'Admin\UserController@create');
    $router->post('/users', 'Admin\UserController@store');
    $router->get('/users/{id}/edit', 'Admin\UserController@edit');
    $router->put('/users/{id}', 'Admin\UserController@update');
    $router->delete('/users/{id}', 'Admin\UserController@destroy');
    
    // Markets Management
    $router->get('/markets', 'Admin\MarketController@index');
    $router->get('/markets/create', 'Admin\MarketController@create');
    $router->post('/markets', 'Admin\MarketController@store');
    $router->get('/markets/{id}/edit', 'Admin\MarketController@edit');
    $router->put('/markets/{id}', 'Admin\MarketController@update');
    $router->delete('/markets/{id}', 'Admin\MarketController@destroy');
    
    // Commodities Management
    $router->get('/commodities', 'Admin\CommodityController@index');
    $router->get('/commodities/create', 'Admin\CommodityController@create');
    $router->post('/commodities', 'Admin\CommodityController@store');
    $router->get('/commodities/{id}/edit', 'Admin\CommodityController@edit');
    $router->put('/commodities/{id}', 'Admin\CommodityController@update');
    $router->delete('/commodities/{id}', 'Admin\CommodityController@destroy');
    
    // Price Approvals
    $router->get('/approvals', 'Admin\ApprovalController@index');
    $router->post('/approvals/{id}/approve', 'Admin\ApprovalController@approve');
    $router->post('/approvals/{id}/reject', 'Admin\ApprovalController@reject');
    
    // Reports
    $router->get('/reports', 'Admin\ReportController@index');
    $router->get('/reports/export', 'Admin\ReportController@export');
    
    // Settings
    $router->get('/settings', 'Admin\SettingController@index');
    $router->post('/settings', 'Admin\SettingController@update');
})->middleware(['auth', 'role:admin']);

// UPTD Routes
$router->group('/uptd', function($router) {
    // Dashboard
    $router->get('/dashboard', 'Uptd\DashboardController@index');
    
    // Price Submission
    $router->get('/prices', 'Uptd\PriceController@index');
    $router->get('/prices/create', 'Uptd\PriceController@create');
    $router->post('/prices', 'Uptd\PriceController@store');
    $router->get('/prices/{id}/edit', 'Uptd\PriceController@edit');
    $router->put('/prices/{id}', 'Uptd\PriceController@update');
    $router->delete('/prices/{id}', 'Uptd\PriceController@destroy');
    
    // Profile
    $router->get('/profile', 'Uptd\ProfileController@edit');
    $router->put('/profile', 'Uptd\ProfileController@update');
    $router->get('/change-password', 'Uptd\ProfileController@showChangePasswordForm');
    $router->post('/change-password', 'Uptd\ProfileController@changePassword');
})->middleware(['auth', 'role:uptd']);

// API Routes
$router->group('/api', function($router) {
    // Public API
    $router->get('/prices', 'Api\PriceController@index');
    $router->get('/prices/{id}', 'Api\PriceController@show');
    $router->get('/markets', 'Api\MarketController@index');
    $router->get('/commodities', 'Api\CommodityController@index');
    
    // Protected API (requires authentication)
    $router->group('', function($router) {
        // Price submissions
        $router->post('/prices', 'Api\PriceController@store');
        $router->put('/prices/{id}', 'Api\PriceController@update');
        $router->delete('/prices/{id}', 'Api\PriceController@destroy');
        
        // Profile
        $router->get('/profile', 'Api\ProfileController@show');
        $router->put('/profile', 'Api\ProfileController@update');
    })->middleware(['auth:api']);
});

// 404 Not Found
$router->notFound(function() {
    http_response_code(404);
    include APP_PATH . '/views/errors/404.php';
    exit;
});

// 500 Server Error
$router->error(function() {
    http_response_code(500);
    include APP_PATH . '/views/errors/500.php';
    exit;
});
