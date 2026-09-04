<?php

header('Content-Type: application/json');

require 'db.php';
require '../vendor/autoload.php';

use Predis\Client as RedisClient;
use MongoDB\Client as MongoClient;

try {

    /* =========================
       GET SESSION TOKEN
       ========================= */

    $sessionToken = $_COOKIE['session_token'] ?? '';

    if ($sessionToken === '') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Not logged in.'
        ]);
        exit;
    }


    /* =========================
       CONNECT TO REDIS
       ========================= */

    $redisHost = getenv('REDIS_HOST');

    if (!$redisHost) {
        throw new Exception('REDIS_HOST is missing.');
    }

    $redis = new RedisClient([
        'scheme'   => getenv('REDIS_SCHEME') ?: 'tcp',
        'host'     => $redisHost,
        'port'     => (int)(getenv('REDIS_PORT') ?: 6379),
        'password' => getenv('REDIS_PASSWORD') ?: null,
    ]);

    $userId = $redis->get('session:' . $sessionToken);

    if (!$userId) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Session expired. Please login again.'
        ]);
        exit;
    }


    /* =========================
       GET FORM DATA
       ========================= */

    $name = trim($_POST['name'] ?? '');
    $age  = trim($_POST['age'] ?? '');
    $bio  = trim($_POST['bio'] ?? '');


    /* =========================
       VALIDATE AGE
       ========================= */

    if ($age !== '') {

        if (!is_numeric($age)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Age must be a number.'
            ]);
            exit;
        }

        $age = (int)$age;

        if ($age < 1 || $age > 120) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Please enter a valid age between 1 and 120.'
            ]);
            exit;
        }

    } else {
        $age = null;
    }


    /* =========================
       MONGODB URI
       ========================= */

    $mongoUri = getenv('MONGO_URI');

    if (!$mongoUri) {
        throw new Exception('MONGO_URI is missing in Render environment variables.');
    }

    /*
     * Remove accidental spaces and quotes
     * from Render environment variable.
     */
    $mongoUri = trim($mongoUri);
    $mongoUri = trim($mongoUri, "\"'");
    $mongoUri = trim($mongoUri);


    /*
     * Make sure URI starts correctly.
     */
    if (
        !str_starts_with($mongoUri, 'mongodb://') &&
        !str_starts_with($mongoUri, 'mongodb+srv://')
    ) {
        throw new Exception(
            'Invalid MONGO_URI. It must start with mongodb:// or mongodb+srv://'
        );
    }


    /* =========================
       CONNECT TO MONGODB
       ========================= */

    $mongoClient = new MongoClient(
        $mongoUri,
        [
            'tls' => true,
            'serverSelectionTimeoutMS' => 10000,
            'connectTimeoutMS' => 10000
        ]
    );


    /* =========================
       DATABASE
       ========================= */

    $mongoDatabase = getenv('MONGO_DB_NAME');

    if (!$mongoDatabase) {
        $mongoDatabase = 'auth_system';
    }

    $mongoCollection = getenv('MONGO_COLLECTION');

    if (!$mongoCollection) {
        $mongoCollection = 'profiles';
    }

    $collection = $mongoClient
        ->selectDatabase($mongoDatabase)
        ->selectCollection($mongoCollection);


    /* =========================
       UPDATE PROFILE
       ========================= */

    $result = $collection->updateOne(

        [
            'user_id' => (int)$userId
        ],

        [
            '$set' => [
                'user_id' => (int)$userId,
                'name'    => $name,
                'age'     => $age,
                'bio'     => $bio,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ],

        [
            'upsert' => true
        ]

    );


    /* =========================
       SUCCESS
       ========================= */

    echo json_encode([
        'status' => 'success',
        'message' => 'Profile updated successfully.'
    ]);

} catch (Throwable $e) {

    /*
     * Log complete error to Render logs
     */
    error_log(
        'PROFILE UPDATE ERROR: ' .
        $e->getMessage()
    );

    /*
     * Send useful error to browser
     */
    echo json_encode([
        'status' => 'error',
        'message' => 'MongoDB update failed: ' . $e->getMessage()
    ]);
}
?>