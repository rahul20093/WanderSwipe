<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

include('../../config/config.php');

// Set charset for the connection
mysqli_set_charset($conn, "utf8mb4");

// --- Read and Validate Parameters ---
$budget_str = $_POST['budget'] ?? '';
$vibes_str = $_POST['vibe'] ?? '';
$time_tag = $_POST['time_tag'] ?? '';

if (empty($budget_str) || empty($vibes_str) || empty($time_tag)) {
    echo json_encode(['status' => false, 'message' => 'Missing required parameters']);
    exit;
}

// --- Prepare Data and Parameters for SQL ---
$params = [];
$param_types = '';

// 1. Time Tag Parameter
$params[] = $time_tag;
$param_types .= 's';

// 2. Vibe Parameters (with data cleaning)
$vibes_array = array_map('trim', explode(',', strtolower($vibes_str)));
$vibe_conditions = [];
foreach ($vibes_array as $vibe) {
    // Handle known misspellings and inconsistencies
    $clean_vibe = str_replace('-', '', $vibe);
    $clean_vibe = str_replace(' ', '', $clean_vibe);
    
    // Build a robust check for the database column
    $db_vibe_check = "REPLACE(REPLACE(LOWER(vibe), 'arsty', 'artsy'), 'energitic', 'energetic')";
    $vibe_conditions[] = "{$db_vibe_check} LIKE ?";
    $params[] = "%{$clean_vibe}%";
    $param_types .= 's';
}

// 3. Budget Parameters
$budget_array = array_map('intval', explode(',', $budget_str));
$budget_conditions = [];
foreach ($budget_array as $budget_level) {
    $budget_conditions[] = "CHAR_LENGTH(budget) = ?";
    $params[] = $budget_level;
    $param_types .= 'i';
}

// --- Build the Final SQL Query ---
$sql = "SELECT id, place_name, location, description, budget, vibe, time_tag FROM places_1 WHERE ";
$sql .= "LOWER(time_tag) = LOWER(?) ";
$sql .= "AND (" . implode(" OR ", $vibe_conditions) . ") ";
$sql .= "AND (" . implode(" OR ", $budget_conditions) . ") ";
$sql .= "ORDER BY RAND() LIMIT 25";

// --- Prepare and Execute ---
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo json_encode(['status' => false, 'message' => 'SQL prepare failed: ' . $conn->error, 'sql' => $sql]);
    exit;
}

$stmt->bind_param($param_types, ...$params);

if (!$stmt->execute()) {
    echo json_encode(['status' => false, 'message' => 'SQL execute failed: ' . $stmt->error]);
    exit;
}

// --- Fetch and Format Results ---
$result = $stmt->get_result();
$places = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $places[] = $row;
    }
}

$stmt->close();
$conn->close();

// --- Send Final Response ---
echo json_encode([
    "status" => true,
    "message" => "Query executed successfully. Found " . count($places) . " places.",
    "data" => $places
]);
?>