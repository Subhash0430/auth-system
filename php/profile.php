<?php
header('Content-Type: application/json');
require 'db.php';
require '../vendor/autoload.php';

use Predis\Client as RedisClient;
use MongoDB\Client as MongoClient;

// ---- Step 1: Get the session token from the cookie ----
$sessionToken = $_COOKIE['session_token'] ?? '';

if ($sessionToken === '') {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit;
}

// ---- Step 2: Check Redis for this session ----
try {
    $redis = new RedisClient([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ]);

    $userId = $redis->get('session:' . $sessionToken);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Session check failed.']);
    exit;
}

if (!$userId) {
    // no matching session in Redis = expired or invalid
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit;
}

// ---- Step 3: Get username/email from MySQL ----
$stmt = $mysqli->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit;
}

$userRow = $result->fetch_assoc();

// ---- Step 4: Get name/age/bio from MongoDB ----
$profileData = [
    'name' => null,
    'age'  => null,
    'bio'  => null
];

try {
    $mongoClient = new MongoClient("mongodb://localhost:27017");
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

// ---- Step 5: Send everything back ----
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