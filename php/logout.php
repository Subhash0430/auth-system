<?php
header('Content-Type: application/json');
require '../vendor/autoload.php';

use Predis\Client as RedisClient;

$sessionToken = $_COOKIE['session_token'] ?? '';

if ($sessionToken !== '') {
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
        $redis->del('session:' . $sessionToken);
    } catch (Exception $e) {
        // even if Redis fails, still clear the cookie below
    }
}

setcookie('session_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || getenv('COOKIE_SECURE') === 'true'),
    'httponly' => true,
    'samesite' => getenv('COOKIE_SAMESITE') ?: 'Lax',
]);

echo json_encode(['status' => 'success', 'message' => 'Logged out.']);
?>