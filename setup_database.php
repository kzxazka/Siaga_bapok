<?php
// Load database configuration
$config = [
    'host' => 'localhost',
    'dbname' => 'siagabapok_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

try {
    // Connect to MySQL server
    $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to MySQL server\n";
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$config['dbname']}`");
    
    echo "✅ Using database: {$config['dbname']}\n";
    
    // Run migrations
    $migrations = glob(__DIR__ . '/database/migrations/*.sql');
    sort($migrations);
    
    if (empty($migrations)) {
        echo "ℹ️ No migration files found in database/migrations/\n";
    } else {
        echo "\n🔄 Running migrations:\n";
        foreach ($migrations as $migration) {
            echo "- " . basename($migration) . "\n";
            $sql = file_get_contents($migration);
            $pdo->exec($sql);
        }
        echo "✅ All migrations completed\n";
    }
    
    // Show tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "\nℹ️ No tables found in the database\n";
    } else {
        echo "\n📋 Database tables:\n";
        foreach ($tables as $table) {
            echo "- $table\n";
        }
    }
    
} catch (PDOException $e) {
    die("\n❌ Error: " . $e->getMessage() . "\n");
}

echo "\n✅ Database setup completed successfully!\n";
