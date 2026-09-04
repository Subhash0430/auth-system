<?php
header('Content-Type: application/json');
require '../vendor/autoload.php';

use Predis\Client as RedisClient;

$sessionToken = $_COOKIE['session_token'] ?? '';

if ($sessionToken !== '') {
    try {
        $redis = new RedisClient([
            'scheme' => 'tcp',
            'host'   => '127.0.0.1',
            'port'   => 6379,
        ]);
        $redis->del('session:' . $sessionToken);
    } catch (Exception $e) {
        // even if Redis fails, still clear the cookie below
    }
}

setcookie('session_token', '', time() - 3600, '/');

echo json_encode(['status' => 'success', 'message' => 'Logged out.']);
?>