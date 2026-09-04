<?php
header('Content-Type: application/json');
require 'db.php';

// Get the data sent from register.js
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// ---- Validation ----
if ($username === '' || $email === '' || $password === '') {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters.']);
    exit;
}

// ---- Check if username or email already exists ----
$checkStmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
$checkStmt->bind_param("ss", $username, $email);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Username or email already taken.']);
    exit;
}
$checkStmt->close();

// ---- Hash the password securely ----
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// ---- Insert the new user ----
$insertStmt = $mysqli->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
$insertStmt->bind_param("sss", $username, $email, $hashedPassword);

if ($insertStmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Registration successful! Redirecting to login...']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Something went wrong. Please try again.']);
}

$insertStmt->close();
$mysqli->close();
?>