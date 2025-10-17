<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require('../../config/config.php');

header('Content-Type: application/json');

// Get POST data
$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Check for missing fields
if (!$username || !$email || !$password) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'All fields are required',
        'data' => []
    ]);
    exit;
}

// Prepare SQL statement
try {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $password);
    $stmt->execute();

    // Get the inserted user ID
    $user_id = $stmt->insert_id;

    echo json_encode([
        'status' => true,
        'message' => 'User registered successfully',
        'data' => [
            [
                'id' => $user_id,
                'username' => $username,
                'email' => $email
            ]
        ]
    ]);

    $stmt->close();
} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        http_response_code(409); // Conflict
        echo json_encode([
            'status' => false,
            'message' => 'Email already exists',
            'data' => []
        ]);
    } else {
        http_response_code(500); // Server error
        echo json_encode([
            'status' => false,
            'message' => 'Registration failed: ' . $e->getMessage(),
            'data' => []
        ]);
    }
}

$conn->close();
?>
