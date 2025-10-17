<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require('../../config/config.php');

$response = ['status' => false, 'message' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Use user_id from POST, not hardcoded
    $user_id = isset($_POST['user_id']) ? filter_var($_POST['user_id'], FILTER_VALIDATE_INT) : false;
    
    $first_name = isset($_POST['first_name']) ? $_POST['first_name'] : '';
    $last_name = isset($_POST['last_name']) ? $_POST['last_name'] : '';
    $bio = isset($_POST['bio']) ? $_POST['bio'] : '';
    $location = isset($_POST['location']) ? $_POST['location'] : '';
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';

    if ($user_id !== false && !empty($first_name) && !empty($last_name)) {
        
        $profile_picture_path = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
            $relative_file_path = 'uploads/' . $file_name;
            $absolute_file_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $absolute_file_path)) {
                $profile_picture_path = $relative_file_path;
            } else {
                $response['message'] = 'Failed to upload file.';
                echo json_encode($response);
                exit;
            }
        }

        // Check if a profile already exists
        $check_sql = "SELECT id, profile_picture FROM user_profile WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $existing_profile = $check_result->fetch_assoc();
            // If no new picture is uploaded, keep the old one
            if ($profile_picture_path === null) {
                $profile_picture_path = $existing_profile['profile_picture'];
            }

            // Profile exists, so UPDATE it. Use the consistent table name 'user_profile'
            $sql = "UPDATE user_profile SET first_name = ?, last_name = ?, bio = ?, location = ?, gender = ?, profile_picture = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $first_name, $last_name, $bio, $location, $gender, $profile_picture_path, $user_id);
            
            if ($stmt->execute()) {
                $response = ['status' => true, 'message' => 'Profile updated successfully', 'data' => []];
            } else {
                $response = ['status' => false, 'message' => 'Database update failed: ' . $stmt->error];
            }
            $stmt->close();
        } else {
            // This case is less likely if profile_setup runs first, but good to have as a fallback.
            // Use the consistent table name 'user_profile'
            $sql = "INSERT INTO user_profile (user_id, first_name, last_name, bio, location, gender, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssss", $user_id, $first_name, $last_name, $bio, $location, $gender, $profile_picture_path);

            if ($stmt->execute()) {
                $response = ['status' => true, 'message' => 'Profile created successfully'];
            } else {
                $response = ['status' => false, 'message' => 'Database insert failed: ' . $stmt->error];
            }
            $stmt->close();
        }
        $check_stmt->close();
    } else {
        $response['message'] = 'Required fields are missing or user ID was not provided.';
    }
}

echo json_encode($response);
$conn->close();
?>
