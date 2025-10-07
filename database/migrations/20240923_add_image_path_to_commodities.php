<?php
/**
 * Migration to add image_path column to commodities table
 */

// Include database configuration
require_once __DIR__ . '/../../src/models/Database.php';

try {
    $db = Database::getInstance();
    
    // Check if column already exists
    $check = $db->fetchAll("SHOW COLUMNS FROM commodities LIKE 'image_path'");
    
    if (empty($check)) {
        // Add the image_path column
        $db->query("ALTER TABLE commodities ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER unit");
        echo "Successfully added image_path column to commodities table.\n";
    } else {
        echo "image_path column already exists in commodities table.\n";
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/../../public/uploads/commodities';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        echo "Created uploads directory at: $uploadDir\n";
    } else {
        echo "Uploads directory already exists.\n";
    }
    
    echo "Migration completed successfully.\n";
    
} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
