<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
include('../../config/config.php');

// Hardcoded user_id for now
$user_id = 1;

if (isset($_POST['place_id']) && isset($_POST['rating_value'])) {
    $place_id = $_POST['place_id'];
    $rating_value = $_POST['rating_value'];

    // Basic validation
    if (!is_numeric($place_id) || !is_numeric($rating_value)) {
        http_response_code(400);
        echo json_encode([
            'status' => false,
            'message' => 'Invalid input data.'
        ]);
        exit;
    }

    $sql = "INSERT INTO ratings (place_id, user_id, rating_value) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $place_id, $user_id, $rating_value);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => true,
            'message' => 'Rating submitted successfully.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => false,
            'message' => 'Failed to submit rating.'
        ]);
    }

    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Required fields are missing.'
    ]);
}

$conn->close();
?>
