<?php

header('Content-Type: application/json');

require 'db.php';
require '../vendor/autoload.php';

use Predis\Client as RedisClient;
use MongoDB\Client as MongoClient;

/*
|--------------------------------------------------------------------------
| Get session token
|--------------------------------------------------------------------------
*/

$sessionToken = $_COOKIE['session_token'] ?? '';

if ($sessionToken === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not logged in.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Connect to Redis and get user ID
|--------------------------------------------------------------------------
*/

try {

    $redisHost = getenv('REDIS_HOST');

    if (!$redisHost) {
        throw new Exception('REDIS_HOST is missing.');
    }

    $redis = new RedisClient([
        'scheme'   => getenv('REDIS_SCHEME') ?: 'tcp',
        'host'     => $redisHost,
        'port'     => (int)(getenv('REDIS_PORT') ?: 6379),
        'password' => getenv('REDIS_PASSWORD') ?: null
    ]);

    $userId = $redis->get('session:' . $sessionToken);

} catch (Exception $e) {

    echo json_encode([
        'status' => 'error',
        'message' => 'Session check failed.'
    ]);
    exit;
}

if (!$userId) {

    echo json_encode([
        'status' => 'error',
        'message' => 'Session expired. Please login again.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Get form data
|--------------------------------------------------------------------------
*/

$name = trim($_POST['name'] ?? '');
$age  = trim($_POST['age'] ?? '');
$bio  = trim($_POST['bio'] ?? '');

/*
|--------------------------------------------------------------------------
| Validate age
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| MongoDB connection
|--------------------------------------------------------------------------
*/

try {

    $mongoUri = getenv('MONGO_URI');

    if (!$mongoUri) {
        throw new Exception('MONGO_URI is missing.');
    }

    /*
     * Remove accidental spaces, quotes and backslashes
     * from Render environment variable.
     */
    $mongoUri = trim($mongoUri);

    $mongoUri = trim($mongoUri, "\"'");

    // Fix accidental leading backslash
    $mongoUri = ltrim($mongoUri, '\\');

    /*
     * MongoDB URI must start with mongodb://
     * or mongodb+srv://
     */
    if (
        strpos($mongoUri, 'mongodb://') !== 0 &&
        strpos($mongoUri, 'mongodb+srv://') !== 0
    ) {
        throw new Exception('Invalid MONGO_URI format.');
    }

    /*
     * Create MongoDB client
     */
    $mongoClient = new MongoClient(
        $mongoUri,
        [
            'tls' => true,
            'tlsCAFile' => '/etc/ssl/certs/ca-certificates.crt',
            'serverSelectionTimeoutMS' => 10000,
            'connectTimeoutMS' => 10000
        ]
    );

    /*
     * Database name
     */
    $mongoDatabase = getenv('MONGO_DB_NAME');

    if (!$mongoDatabase) {
        $mongoDatabase = 'auth_system';
    }

    /*
     * Collection name
     */
    $mongoCollection = getenv('MONGO_COLLECTION');

    if (!$mongoCollection) {
        $mongoCollection = 'profiles';
    }

    $collection = $mongoClient
        ->selectDatabase($mongoDatabase)
        ->selectCollection($mongoCollection);

    /*
     * Update profile
     */
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

    /*
     * Success
     */
    echo json_encode([
        'status' => 'success',
        'message' => 'Profile updated successfully.'
    ]);

} catch (Exception $e) {

    /*
     * Log actual error to Render logs
     * without showing sensitive database details
     * to the browser.
     */

    error_log(
        'MongoDB update error: ' . $e->getMessage()
    );

    echo json_encode([
        'status' => 'error',
        'message' => 'MongoDB update failed. Check Render logs.'
    ]);
}

$stmt = null;

if (isset($mysqli) && $mysqli) {
    $mysqli->close();
}

?>