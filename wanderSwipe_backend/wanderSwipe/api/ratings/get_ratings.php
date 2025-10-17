<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
include('../../config/config.php');

if (isset($_GET['place_id'])) {
    $place_id = $_GET['place_id'];
    $sql = "SELECT rating_id, place_id, user_id, rating_value, created_at FROM ratings WHERE place_id = ?";
    $params = ['i', $place_id];

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $ratings = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => true,
        'message' => 'Ratings fetched successfully.',
        'data' => $ratings
    ]);

    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'place_id is required.',
        'data' => []
    ]);
}

$conn->close();
?>
