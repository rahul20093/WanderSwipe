<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include Composer's autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Include the database connection and Cloudinary config
require_once __DIR__ . '/../../config/config.php';
$cloudinaryConfig = require_once __DIR__ . '/../../config/cloudinary_config.php';

use Cloudinary\Cloudinary;

// Set charset for the connection
mysqli_set_charset($conn, "utf8mb4");

// --- Read and Validate Parameters ---
$budget = isset($_POST['budget']) ? explode(',', $_POST['budget']) : [];
$vibes = isset($_POST['vibes']) ? explode(',', $_POST['vibes']) : [];
$time_slot = isset($_POST['time_slot']) ? $_POST['time_slot'] : '';

// --- Build the Dynamic SQL Query ---
$sql = "SELECT id, place_name, location, description, budget, vibe, time_tag, image_src, latitude, longitude FROM places WHERE 1=1";
$params = [];
$param_types = '';

// Add budget conditions
if (!empty($budget)) {
    $budget_placeholders = implode(',', array_fill(0, count($budget), '?'));
    $sql .= " AND budget IN ($budget_placeholders)";
    foreach ($budget as $b) {
        $params[] = $b;
        $param_types .= 's';
    }
}

// Add vibe conditions
if (!empty($vibes)) {
    $vibe_conditions = [];
    foreach ($vibes as $vibe) {
        $vibe_conditions[] = "FIND_IN_SET(?, vibe)";
        $params[] = $vibe;
        $param_types .= 's';
    }
    $sql .= " AND (" . implode(' OR ', $vibe_conditions) . ")";
}

// Add time_slot condition
if (!empty($time_slot)) {
    $sql .= " AND time_tag = ?";
    $params[] = $time_slot;
    $param_types .= 's';
}

// Add randomization
$sql .= " ORDER BY RAND()";

// --- Prepare and Execute ---
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(['status' => false, 'message' => 'SQL prepare failed: ' . $conn->error, 'sql' => $sql]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

if (!$stmt->execute()) {
    echo json_encode(['status' => false, 'message' => 'SQL execute failed: ' . $stmt->error]);
    exit;
}

// --- Fetch and Format Results ---
$result = $stmt->get_result();
$places = [];
$cloudinary = new Cloudinary($cloudinaryConfig); // Initialize Cloudinary with the config

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Ensure numeric types are correct
        $row['id'] = (int)$row['id'];
        $row['latitude'] = (float)$row['latitude'];
        $row['longitude'] = (float)$row['longitude'];

        // --- Generate Cloudinary URL ---
        // The 'image_src' from the database holds the public ID with a "wanderSwipe/" prefix.
        $publicIdWithPrefix = $row['image_src'];
        
        // Remove the "wanderSwipe/" prefix to get the correct public ID for the URL.
        $correctPublicId = str_replace('wanderSwipe/', '', $publicIdWithPrefix);
        
        // Manually build the URL to ensure it has the .jpg extension.
        $row['image_src'] = "https://res.cloudinary.com/ds3pi8j40/image/upload/" . $correctPublicId . ".jpg";

        $places[] = $row;
    }
}

$stmt->close();
$conn->close();

// --- Send Final Response ---
$response = [
    'status' => true,
    'message' => 'Places fetched successfully from database',
    'data' => $places
];

echo json_encode($response);
?>