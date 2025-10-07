<?php
/**
 * Database Configuration
 * Siaga Bapok - Sistem Informasi Harga Bahan Pokok
 */

// Include helper functions
require_once __DIR__ . '/../src/helpers.php';

return [
    'host' => 'localhost',
    'dbname' => 'siagabapok_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
