<?php
/**
 * Run Database Migrations
 */

// Load database configuration
$config = require __DIR__ . '/config/database.php';

// Create database connection
try {
    $dsn = "mysql:host={$config['host']};";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$config['dbname']}`");
    
    echo "Connected to database successfully.\n";
    
    // Run migrations
    $migrationsDir = __DIR__ . '/database/migrations';
    $migrations = glob("$migrationsDir/*.sql");
    
    sort($migrations);
    
    foreach ($migrations as $migration) {
        echo "Running migration: " . basename($migration) . "\n";
        $sql = file_get_contents($migration);
        $pdo->exec($sql);
    }
    
    echo "All migrations completed successfully!\n";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
