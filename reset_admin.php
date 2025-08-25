<?php
require_once __DIR__ . '/config/database.php';

$db = new Database();

// bikin hash baru
$hash = password_hash("password", PASSWORD_DEFAULT);

// update admin
$db->execute("UPDATE users SET password = ? WHERE username = 'uptd_tugu'", [$hash]);

echo "uptd_tugu password reset ke 'password' dengan hash: $hash";
