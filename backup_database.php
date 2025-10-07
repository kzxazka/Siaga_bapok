<?php
// backup_database.php
require_once __DIR__ . '/../src/config/Database.php';

// Hanya admin yang bisa akses
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die('Unauthorized access');
}

$db = Database::getInstance();
$backupFile = 'backup/database_' . date("Y-m-d_H-i-s") . '.sql';

// Create backup directory if not exists
if (!file_exists('backup')) {
    mkdir('backup', 0755, true);
}

// Get all tables
$tables = $db->fetchAll("SHOW TABLES");
$return = '';

foreach ($tables as $table) {
    $table = array_values(get_object_vars($table))[0];
    $result = $db->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch(PDO::FETCH_NUM);
    $return .= "\n\n" . $row[1] . ";\n\n";
    
    $result = $db->query("SELECT * FROM `$table`");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $return .= "INSERT INTO `$table` VALUES(";
        $values = array_map([$db->getPdo(), 'quote'], $row);
        $return .= implode(',', $values);
        $return .= ");\n";
    }
}

// Save file
file_put_contents($backupFile, $return);

// Download file
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($backupFile) . '"');
readfile($backupFile);