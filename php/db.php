<?php
// Database connection settings
$host = 'localhost';
$db   = 'auth_system';
$user = 'root';
$pass = '';       // XAMPP's default MySQL root password is blank

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed.'
    ]));
}
?>