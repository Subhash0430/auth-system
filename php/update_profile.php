<?php
header('Content-Type: application/json');
require '../vendor/autoload.php';

use Predis\Client as RedisClient;
use MongoDB\Client as MongoClient;

// ---- Step 1: Verify session (same check as profile.php) ----
$sessionToken = $_COOKIE['session_token'] ?? '';

if ($sessionToken === '') {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit;
}

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
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit;
}

// ---- Step 2: Get and clean the submitted data ----
$name = trim($_POST['name'] ?? '');
$age  = trim($_POST['age'] ?? '');
$bio  = trim($_POST['bio'] ?? '');

// basic validation
if ($age !== '' && (!is_numeric($age) || $age < 1 || $age > 120)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid age.']);
    exit;
}

// ---- Step 3: Save to MongoDB (create if new, update if exists) ----
try {
    $mongoClient = new MongoClient("mongodb://localhost:27017");
    $collection = $mongoClient->auth_system->profiles;

    $collection->updateOne(
        ['user_id' => (int)$userId],
        ['$set' => [
            'user_id' => (int)$userId,
            'name'    => $name,
            'age'     => $age !== '' ? (int)$age : null,
            'bio'     => $bio
        ]],
        ['upsert' => true] // create the document if it doesn't already exist
    );

    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully.']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update profile.']);
}
?>