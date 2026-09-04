<?php

header('Content-Type: application/json');

require 'db.php';
require '../vendor/autoload.php';

use Predis\Client as RedisClient;
use MongoDB\Client as MongoClient;


// ==========================================
// 1. GET SESSION TOKEN
// ==========================================

$sessionToken = $_COOKIE['session_token'] ?? '';

if ($sessionToken === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not logged in.'
    ]);
    exit;
}


// ==========================================
// 2. CONNECT TO REDIS AND GET USER ID
// ==========================================

try {

    $redisHost = getenv('REDIS_HOST');

    if (!$redisHost) {
        throw new RuntimeException(
            'Redis configuration is incomplete.'
        );
    }

    $redis = new RedisClient([
        'scheme'   => getenv('REDIS_SCHEME') ?: 'tcp',
        'host'     => $redisHost,
        'port'     => (int)(getenv('REDIS_PORT') ?: 6379),
        'password' => getenv('REDIS_PASSWORD') ?: null,
    ]);

    $userId = $redis->get('session:' . $sessionToken);

} catch (Exception $e) {

    echo json_encode([
        'status' => 'error',
        'message' => 'Session check failed: ' . $e->getMessage()
    ]);

    exit;
}


// ==========================================
// 3. CHECK USER ID
// ==========================================

if (!$userId) {

    echo json_encode([
        'status' => 'error',
        'message' => 'Session expired. Please log in again.'
    ]);

    exit;
}


// ==========================================
// 4. GET FORM DATA
// ==========================================

$name = trim($_POST['name'] ?? '');
$age  = trim($_POST['age'] ?? '');
$bio  = trim($_POST['bio'] ?? '');


// ==========================================
// 5. VALIDATE AGE
// ==========================================

if ($age !== '') {

    if (!is_numeric($age) || $age < 1 || $age > 120) {

        echo json_encode([
            'status' => 'error',
            'message' => 'Please enter a valid age.'
        ]);

        exit;
    }
}


// ==========================================
// 6. CONNECT TO MONGODB
// ==========================================

try {

    $mongoUri = getenv('MONGO_URI');

    if (!$mongoUri) {

        throw new RuntimeException(
            'MongoDB configuration is incomplete. MONGO_URI is missing.'
        );
    }


    // Create MongoDB client
    $mongoClient = new MongoClient($mongoUri);


    // Database name
    $mongoDatabase = getenv('MONGO_DB_NAME') ?: 'auth_system';


    // Collection name
    $mongoCollection = getenv('MONGO_COLLECTION') ?: 'profiles';


    // Select collection
    $collection = $mongoClient
        ->selectDatabase($mongoDatabase)
        ->selectCollection($mongoCollection);


    // ==========================================
    // 7. UPDATE USER PROFILE
    // ==========================================

    $result = $collection->updateOne(

        // Find profile using logged-in user's ID
        [
            'user_id' => (int)$userId
        ],

        // Update profile information
        [
            '$set' => [
                'user_id' => (int)$userId,
                'name'    => $name,
                'age'     => $age !== '' ? (int)$age : null,
                'bio'     => $bio
            ]
        ],

        // Create profile if it doesn't already exist
        [
            'upsert' => true
        ]
    );


    // ==========================================
    // 8. SUCCESS RESPONSE
    // ==========================================

    echo json_encode([
        'status' => 'success',
        'message' => 'Profile updated successfully.'
    ]);

} catch (Exception $e) {


    // ==========================================
    // 9. SHOW ACTUAL ERROR FOR DEBUGGING
    // ==========================================

    echo json_encode([
        'status' => 'error',
        'message' => 'MongoDB update failed: ' . $e->getMessage()
    ]);

}

?>