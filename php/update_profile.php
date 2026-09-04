<?php

header('Content-Type: application/json');

require 'db.php';
require '../vendor/autoload.php';

use Predis\Client as RedisClient;
use MongoDB\Client as MongoClient;


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
   CHECK SESSION USING REDIS
========================= */

try {

    $redisHost = getenv('REDIS_HOST');

    if (!$redisHost) {
        throw new RuntimeException('Redis configuration is incomplete.');
    }

    $redis = new RedisClient([
        'scheme'   => getenv('REDIS_SCHEME') ?: 'tcp',
        'host'     => $redisHost,
        'port'     => (int)(getenv('REDIS_PORT') ?: 6379),
        'password' => getenv('REDIS_PASSWORD') ?: null
    ]);

    $userId = $redis->get('session:' . $sessionToken);

} catch (Exception $e) {

    error_log('Redis error: ' . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Session check failed.'
    ]);
    exit;
}


/* =========================
   CHECK USER ID
========================= */

if (!$userId) {

    echo json_encode([
        'status' => 'error',
        'message' => 'Session expired. Please log in again.'
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

    if (!is_numeric($age) || $age < 1 || $age > 120) {

        echo json_encode([
            'status' => 'error',
            'message' => 'Please enter a valid age.'
        ]);

        exit;
    }
}


/* =========================
   CONNECT TO MONGODB
========================= */

try {

    $mongoUri = getenv('MONGO_URI');

    if (!$mongoUri) {
        throw new RuntimeException(
            'MongoDB configuration is incomplete.'
        );
    }


    $mongoClient = new MongoClient(
        $mongoUri,
        [
            'tls' => true,
            'tlsDisableOCSPEndpointCheck' => true,
            'serverSelectionTimeoutMS' => 10000
        ]
    );


    /* =========================
       DATABASE & COLLECTION
    ========================= */

    $mongoDatabase =
        getenv('MONGO_DB_NAME') ?: 'auth_system';

    $mongoCollection =
        getenv('MONGO_COLLECTION') ?: 'profiles';


    $collection = $mongoClient
        ->selectDatabase($mongoDatabase)
        ->selectCollection($mongoCollection);


    /* =========================
       UPDATE PROFILE
    ========================= */

    $collection->updateOne(

        [
            'user_id' => (int)$userId
        ],

        [
            '$set' => [

                'user_id' => (int)$userId,

                'name' => $name,

                'age' => $age !== ''
                    ? (int)$age
                    : null,

                'bio' => $bio

            ]
        ],

        [
            'upsert' => true
        ]
    );


    /* =========================
       SUCCESS RESPONSE
    ========================= */

    echo json_encode([
        'status' => 'success',
        'message' => 'Profile updated successfully.'
    ]);

} catch (Exception $e) {

    /* =========================
       ERROR LOG
    ========================= */

    error_log(
        'MongoDB update error: ' .
        $e->getMessage()
    );


    echo json_encode([
        'status' => 'error',
        'message' => 'MongoDB update failed: ' .
                     $e->getMessage()
    ]);

    exit;
}


/* =========================
   CLOSE MYSQL
========================= */

$mysqli->close();

?>