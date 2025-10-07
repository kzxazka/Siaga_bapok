<?php
// Simple PHP test
$phpVersion = phpversion();
$extensions = get_loaded_extensions();

// Check if PDO and PDO_MySQL are loaded
$pdoLoaded = extension_loaded('pdo');
$pdoMysqlLoaded = extension_loaded('pdo_mysql');

echo "PHP Version: $phpVersion\n\n";
echo "PDO Extension: " . ($pdoLoaded ? "✅ Loaded" : "❌ Not loaded") . "\n";
echo "PDO MySQL: " . ($pdoMysqlLoaded ? "✅ Loaded" : "❌ Not loaded") . "\n\n";

echo "Loaded Extensions:\n";
echo implode("\n", $extensions);
