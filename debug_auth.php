<?php
// Debug Authentication Issues
echo "<h2>Debug Authentication - Siaga Bapok</h2>";

// Check if required files exist
$requiredFiles = [
    'config/database.php',
    'src/models/User.php',
    'src/controllers/AuthController.php'
];

echo "<h3>1. File Check:</h3>";
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✅ $file - EXISTS<br>";
    } else {
        echo "❌ $file - MISSING<br>";
    }
}

// Test database connection
echo "<h3>2. Database Connection Test:</h3>";
try {
    require_once 'config/database.php';
    $db = new Database();
    echo "✅ Database connection - SUCCESS<br>";
    
    // Check if users table exists
    $result = $db->query("SHOW TABLES LIKE 'users'");
    if ($result) {
        echo "✅ Users table - EXISTS<br>";
        
        // Check user count
        $userCount = $db->fetchOne("SELECT COUNT(*) as count FROM users");
        echo "📊 Total users: " . $userCount['count'] . "<br>";
        
        // Check admin user
        $adminUser = $db->fetchOne("SELECT username, full_name, role, is_active FROM users WHERE username = 'admin'");
        if ($adminUser) {
            echo "✅ Admin user found:<br>";
            echo "&nbsp;&nbsp;- Username: " . $adminUser['username'] . "<br>";
            echo "&nbsp;&nbsp;- Full Name: " . $adminUser['full_name'] . "<br>";
            echo "&nbsp;&nbsp;- Role: " . $adminUser['role'] . "<br>";
            echo "&nbsp;&nbsp;- Active: " . ($adminUser['is_active'] ? 'YES' : 'NO') . "<br>";
        } else {
            echo "❌ Admin user - NOT FOUND<br>";
        }
        
    } else {
        echo "❌ Users table - NOT FOUND<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test password hash
echo "<h3>3. Password Hash Test:</h3>";
$testPassword = 'password';
$hashFromDB = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

if (password_verify($testPassword, $hashFromDB)) {
    echo "✅ Password verification - SUCCESS<br>";
    echo "&nbsp;&nbsp;- Test password: '$testPassword'<br>";
    echo "&nbsp;&nbsp;- Hash matches<br>";
} else {
    echo "❌ Password verification - FAILED<br>";
}

// Test AuthController instantiation
echo "<h3>4. AuthController Test:</h3>";
try {
    require_once 'src/controllers/AuthController.php';
    $auth = new AuthController();
    echo "✅ AuthController - INSTANTIATED<br>";
    
    // Test credential validation
    $validation = $auth->validateCredentials('admin', 'password');
    if ($validation['valid']) {
        echo "✅ Credential validation - SUCCESS<br>";
        echo "&nbsp;&nbsp;- User: " . $validation['user']['full_name'] . "<br>";
    } else {
        echo "❌ Credential validation - FAILED<br>";
        echo "&nbsp;&nbsp;- Error: " . $validation['message'] . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ AuthController error: " . $e->getMessage() . "<br>";
}

// Session test
echo "<h3>5. Session Test:</h3>";
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
echo "✅ Session started<br>";
echo "&nbsp;&nbsp;- Session ID: " . session_id() . "<br>";

// Login credentials info
echo "<h3>6. Default Login Credentials:</h3>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Role</th><th>Username</th><th>Password</th><th>Full Name</th></tr>";
echo "<tr><td>Admin</td><td>admin</td><td>password</td><td>Administrator</td></tr>";
echo "<tr><td>UPTD</td><td>uptd_tugu</td><td>password</td><td>UPTD Pasar Tugu</td></tr>";
echo "<tr><td>UPTD</td><td>uptd_bambu</td><td>password</td><td>UPTD Pasar Bambu Kuning</td></tr>";
echo "<tr><td>Masyarakat</td><td>masyarakat1</td><td>password</td><td>Masyarakat User</td></tr>";
echo "</table>";

echo "<h3>7. Troubleshooting Steps:</h3>";
echo "<ol>";
echo "<li>Make sure database 'siagabapok_db' exists and is imported</li>";
echo "<li>Check if all users have is_active = 1</li>";
echo "<li>Verify password hashes match the expected format</li>";
echo "<li>Check session configuration in php.ini</li>";
echo "<li>Ensure proper file permissions</li>";
echo "</ol>";
?>