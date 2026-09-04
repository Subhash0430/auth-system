<?php
header('Content-Type: application/json');
require 'db.php';
require '../vendor/autoload.php';

use Predis\Client as RedisClient;
use MongoDB\Client as MongoClient;

$sessionToken = $_COOKIE['session_token'] ?? '';

if ($sessionToken === '') {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit;
}

try {
    $redis = new RedisClient([
    'scheme'   => 'tcp',
    'host'     => getenv('REDIS_HOST') ?: '127.0.0.1',
    'port'     => getenv('REDIS_PORT') ?: 6379,
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

$stmt = $mysqli->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit;
}

$userRow = $result->fetch_assoc();

$profileData = [
    'name' => null,
    'age'  => null,
    'bio'  => null
];

try {
    $mongoClient = new MongoClient(getenv('MONGO_URI') ?: "mongodb://localhost:27017");
    $collection = $mongoClient->auth_system->profiles;

    $profileDoc = $collection->findOne(['user_id' => (int)$userId]);

    if ($profileDoc) {
        $profileData['name'] = $profileDoc['name'] ?? null;
        $profileData['age']  = $profileDoc['age'] ?? null;
        $profileData['bio']  = $profileDoc['bio'] ?? null;
    }
} catch (Exception $e) {
    // If Mongo fails, we still show the MySQL data — profile is just incomplete
}

echo json_encode([
    'status' => 'success',
    'data' => [
        'username' => $userRow['username'],
        'email'    => $userRow['email'],
        'name'     => $profileData['name'],
        'age'      => $profileData['age'],
        'bio'      => $profileData['bio']
    ]
]);

$stmt->close();
$mysqli->close();
?>