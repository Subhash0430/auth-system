<?php
header('Content-Type: application/json');
require 'db.php';
require '../vendor/autoload.php'; // loads Composer libraries (predis)

use Predis\Client as RedisClient;

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// ---- Validation ----
if ($email === '' || $password === '') {
    echo json_encode(['status' => 'error', 'message' => 'Email and password are required.']);
    exit;
}

// ---- Look up the user ----
$stmt = $mysqli->prepare("SELECT id, username, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
    exit;
}

$user = $result->fetch_assoc();

// ---- Verify the password against the stored hash ----
if (!password_verify($password, $user['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
    exit;
}

// ---- Password correct: create a session token ----
$sessionToken = bin2hex(random_bytes(32)); // random, unguessable string

try {
    $redis = new RedisClient([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ]);

    // store session in Redis: key = token, value = user id, expires in 1 hour
    $redis->setex('session:' . $sessionToken, 3600, $user['id']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Session storage failed.']);
    exit;
}

// ---- Send the session token to the browser as a cookie ----
setcookie('session_token', $sessionToken, time() + 3600, '/');

echo json_encode([
    'status' => 'success',
    'message' => 'Login successful! Redirecting...',
    'username' => $user['username']
]);

$stmt->close();
$mysqli->close();
?>