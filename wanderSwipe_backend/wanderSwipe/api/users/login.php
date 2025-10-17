<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include the database configuration
require ('../../config/config.php');

// Fetch values from form-data (POST)
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Validate inputs
if (!$email || !$password) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Email and password are required',
        'data' => [] // empty array
    ]);
    exit;
}

// Prepare and execute the query
$stmt = $conn->prepare("SELECT id, username, email, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Verify password
if ($user && $password === $user['password']) {
    echo json_encode([
        'status' => true,
        'message' => 'Login successful',
        'data' => [
            [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ]
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'status' => false,
        'message' => 'Invalid credentials',
        'data' => []
    ]);
}

$stmt->close();
?>
