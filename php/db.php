<?php
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'auth_system';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$port = getenv('DB_PORT') ?: 3306;

$mysqli = mysqli_init();

// Use SSL if connecting to a cloud host (Aiven requires it)
if (getenv('DB_HOST')) {
    $mysqli->ssl_set(null, null, null, null, null);
    $mysqli->real_connect($host, $user, $pass, $db, $port, null, MYSQLI_CLIENT_SSL);
} else {
    $mysqli = new mysqli($host, $user, $pass, $db, $port);
}

if ($mysqli->connect_error) {
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed.'
    ]));
}
?>