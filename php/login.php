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
    $redisHost = getenv('REDIS_HOST');
    if (!$redisHost) {
        throw new RuntimeException('Redis configuration is incomplete.');
    }

    $redis = new RedisClient([
        'scheme'   => getenv('REDIS_SCHEME') ?: 'tcp',
        'host'     => $redisHost,
        'port'     => (int)(getenv('REDIS_PORT') ?: 6379),
        'password' => getenv('REDIS_PASSWORD') ?: null,
    ]);

    $redis->setex('session:' . $sessionToken, 3600, $user['id']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Session storage failed.']);
    exit;
}

setcookie('session_token', $sessionToken, [
    'expires' => time() + 3600,
    'path' => '/',
    'secure' => ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || getenv('COOKIE_SECURE') === 'true'),
    'httponly' => true,
    'samesite' => getenv('COOKIE_SAMESITE') ?: 'Lax',
]);

echo json_encode([
    'status' => 'success',
    'message' => 'Login successful! Redirecting...',
    'username' => $user['username']
]);

$stmt->close();
$mysqli->close();
?>