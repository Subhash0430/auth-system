<?php
header('Content-Type: application/json');
require 'db.php';
require '../vendor/autoload.php';

use Predis\Client as RedisClient;

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    echo json_encode(['status' => 'error', 'message' => 'Email and password are required.']);
    exit;
}

$stmt = $mysqli->prepare("SELECT id, username, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
    exit;
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
    exit;
}

$sessionToken = bin2hex(random_bytes(32));

try {
    $redis = new RedisClient([
        'scheme' => 'tcp',
        'host'   => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port'   => getenv('REDIS_PORT') ?: 6379,
    ]);

    $redis->setex('session:' . $sessionToken, 3600, $user['id']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Session storage failed.']);
    exit;
}

setcookie('session_token', $sessionToken, time() + 3600, '/');

echo json_encode([
    'status' => 'success',
    'message' => 'Login successful! Redirecting...',
    'username' => $user['username']
]);

$stmt->close();
$mysqli->close();
?>