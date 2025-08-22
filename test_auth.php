<?php
session_start();
require_once 'src/controllers/AuthController.php';

$auth = new AuthController();

echo "<h2>Test Authentication</h2>";

// Handle login test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    echo "<h3>Testing Login for: $username</h3>";
    
    // Test credential validation
    $validation = $auth->validateCredentials($username, $password);
    
    if ($validation['valid']) {
        echo "<div style='color: green;'>✅ Credentials are VALID</div>";
        echo "<p>User: " . $validation['user']['full_name'] . "</p>";
        echo "<p>Role: " . $validation['user']['role'] . "</p>";
        echo "<p>Active: " . ($validation['user']['is_active'] ? 'YES' : 'NO') . "</p>";
        
        // Now try actual login
        echo "<h4>Attempting actual login...</h4>";
        $_POST['username'] = $username;
        $_POST['password'] = $password;
        
        ob_start();
        $auth->login();
        $output = ob_get_clean();
        
        if (isset($_SESSION['success'])) {
            echo "<div style='color: green;'>✅ LOGIN SUCCESS: " . $_SESSION['success'] . "</div>";
            echo "<p>Session data:</p>";
            echo "<ul>";
            echo "<li>User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "</li>";
            echo "<li>Username: " . ($_SESSION['username'] ?? 'NOT SET') . "</li>";
            echo "<li>Full Name: " . ($_SESSION['full_name'] ?? 'NOT SET') . "</li>";
            echo "<li>Role: " . ($_SESSION['role'] ?? 'NOT SET') . "</li>";
            echo "<li>Logged In: " . ($_SESSION['is_logged_in'] ?? 'NOT SET') . "</li>";
            echo "</ul>";
        } elseif (isset($_SESSION['error'])) {
            echo "<div style='color: red;'>❌ LOGIN FAILED: " . $_SESSION['error'] . "</div>";
        } else {
            echo "<div style='color: orange;'>⚠️ No response from login function</div>";
        }
        
    } else {
        echo "<div style='color: red;'>❌ Credentials are INVALID</div>";
        echo "<p>Error: " . $validation['message'] . "</p>";
    }
}

// Check current login status
echo "<h3>Current Login Status:</h3>";
if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    echo "<div style='color: green;'>✅ User is LOGGED IN</div>";
    echo "<ul>";
    echo "<li>ID: " . $user['id'] . "</li>";
    echo "<li>Username: " . $user['username'] . "</li>";
    echo "<li>Full Name: " . $user['full_name'] . "</li>";
    echo "<li>Role: " . $user['role'] . "</li>";
    echo "</ul>";
    
    echo "<form method='POST'>";
    echo "<input type='hidden' name='logout' value='1'>";
    echo "<button type='submit' style='background: red; color: white; padding: 10px;'>LOGOUT</button>";
    echo "</form>";
    
} else {
    echo "<div style='color: red;'>❌ User is NOT logged in</div>";
}

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    echo "<h3>Logging out...</h3>";
    $auth->logout();
}

// Clear messages
unset($_SESSION['success'], $_SESSION['error']);
?>

<h3>Test Login Form:</h3>
<form method="POST">
    <table>
        <tr>
            <td>Username:</td>
            <td><input type="text" name="username" value="admin" required></td>
        </tr>
        <tr>
            <td>Password:</td>
            <td><input type="password" name="password" value="password" required></td>
        </tr>
        <tr>
            <td colspan="2">
                <button type="submit" name="test_login" value="1" style="background: green; color: white; padding: 10px;">TEST LOGIN</button>
            </td>
        </tr>
    </table>
</form>

<h3>Available Test Accounts:</h3>
<ul>
    <li><strong>Admin:</strong> admin / password</li>
    <li><strong>UPTD:</strong> uptd_tugu / password</li>
    <li><strong>UPTD:</strong> uptd_bambu / password</li>
    <li><strong>Masyarakat:</strong> masyarakat1 / password</li>
</ul>

<h3>Session Data:</h3>
<pre><?php print_r($_SESSION); ?></pre>

<h3>Cookie Data:</h3>
<pre><?php print_r($_COOKIE); ?></pre>