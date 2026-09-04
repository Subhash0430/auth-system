<?php
mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv('DB_HOST');
$db = getenv('DB_NAME') ?: 'auth_system';
$user = getenv('DB_USER');
$pass = getenv('DB_PASS') ?: '';
$port = (int)(getenv('DB_PORT') ?: 3306);

if (!$host || !$user) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database configuration is incomplete.']);
    exit;
}

$mysqli = mysqli_init();
$sslMode = strtolower((string)(getenv('DB_SSL') ?: 'required'));
$useSsl = !in_array($sslMode, ['disabled', 'false', '0', 'off'], true);

if ($useSsl) {
    $mysqli->ssl_set(
        getenv('DB_SSL_KEY') ?: null,
        getenv('DB_SSL_CERT') ?: null,
        getenv('DB_SSL_CA') ?: null,
        null,
        null
    );
}

$flags = $useSsl ? MYSQLI_CLIENT_SSL : 0;
$mysqli->real_connect($host, $user, $pass, $db, $port, null, $flags);

if ($mysqli->connect_errno) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}
?>