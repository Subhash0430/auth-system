<?php
header('Content-Type: application/json');
require '../vendor/autoload.php';

use Predis\Client as RedisClient;
use MongoDB\Client as MongoClient;

$sessionToken = $_COOKIE['session_token'] ?? '';

if ($sessionToken === '') {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit;
}

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
    $userId = $redis->get('session:' . $sessionToken);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Session check failed.']);
    exit;
}

if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$age  = trim($_POST['age'] ?? '');
$bio  = trim($_POST['bio'] ?? '');

if ($age !== '' && (!is_numeric($age) || $age < 1 || $age > 120)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid age.']);
    exit;
}

try {
    $mongoUri = getenv('MONGO_URI');
    if (!$mongoUri) {
        throw new RuntimeException('MongoDB configuration is incomplete.');
    }
    $mongoClient = new MongoClient($mongoUri);
    $mongoDatabase = getenv('MONGO_DB_NAME') ?: 'auth_system';
    $mongoCollection = getenv('MONGO_COLLECTION') ?: 'profiles';
    $collection = $mongoClient->{$mongoDatabase}->{$mongoCollection};

    $collection->updateOne(
        ['user_id' => (int)$userId],
        ['$set' => [
            'user_id' => (int)$userId,
            'name'    => $name,
            'age'     => $age !== '' ? (int)$age : null,
            'bio'     => $bio
        ]],
        ['upsert' => true]
    );

    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully.']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update profile.']);
}
?>