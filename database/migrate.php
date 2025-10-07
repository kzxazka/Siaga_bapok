<?php
// Load configuration and database
require_once __DIR__ . '/../src/config/database.php';

// Get all migration files
$migrations = glob(__DIR__ . '/migrations/*.sql');
sort($migrations);

// Create migrations table if not exists
$db->execute("CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    batch INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Get already run migrations
$migrated = $db->fetchAll("SELECT migration FROM migrations");
$migrated = array_column($migrated, 'migration');

$batch = $db->fetchOne("SELECT MAX(batch) as max_batch FROM migrations")['max_batch'] ?? 0;
$batch++;

// Run new migrations
$count = 0;
foreach ($migrations as $migration) {
    $migrationName = basename($migration);
    
    if (!in_array($migrationName, $migrated)) {
        echo "Running migration: $migrationName\n";
        
        // Read SQL file
        $sql = file_get_contents($migration);
        
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Execute SQL
            $db->execute($sql);
            
            // Record migration
            $db->execute(
                "INSERT INTO migrations (migration, batch) VALUES (?, ?)",
                [$migrationName, $batch]
            );
            
            // Commit transaction
            $db->commit();
            
            echo "Migration successful: $migrationName\n";
            $count++;
            
        } catch (Exception $e) {
            // Rollback on error
            $db->rollBack();
            echo "Error running migration $migrationName: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

if ($count === 0) {
    echo "No new migrations to run.\n";
} else {
    echo "Successfully ran $count migration(s).\n";
}

// Ask to run seeder
if (count($migrations) > 0) {
    echo "\nDo you want to run the database seeder? (yes/no) [no]: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    
    if (strtolower($line) === 'yes') {
        echo "Running database seeder...\n";
        require_once __DIR__ . '/seeders/DatabaseSeeder.php';
    } else {
        echo "Skipping database seeder.\n";
    }
}

echo "Database migration completed.\n";
