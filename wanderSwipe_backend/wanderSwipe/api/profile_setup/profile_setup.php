<?php
header("Content-Type: application/json");
require_once '../../config/config.php';

$response = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $age = isset($_POST['age']) ? $_POST['age'] : '';
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    $interests = isset($_POST['interests']) ? $_POST['interests'] : '';
    $user_id = isset($_POST['user_id']) ? filter_var($_POST['user_id'], FILTER_VALIDATE_INT) : false;

    if (!empty($name) && !empty($age) && !empty($gender) && !empty($interests) && $user_id !== false) {
        
        $check_sql = "SELECT id FROM user_profile WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // Profile exists, so UPDATE it
            $update_sql = "UPDATE user_profile SET name = ?, age = ?, gender = ?, interests = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssssi", $name, $age, $gender, $interests, $user_id);

            if ($update_stmt->execute()) {
                $response['status'] = true;
                $response['message'] = "Profile updated successfully.";
                $response['data'] = [];
            } else {
                $response['status'] = false;
                $response['message'] = "Error updating profile: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            // Profile does not exist, so INSERT a new one
            $insert_sql = "INSERT INTO user_profile (name, age, gender, interests, user_id) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ssssi", $name, $age, $gender, $interests, $user_id);

            if ($insert_stmt->execute()) {
                $response['status'] = true;
                $response['message'] = "Profile created successfully.";
            } else {
                $response['status'] = false;
                $response['message'] = "Error creating profile: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    } else {
        $response['status'] = false;
        $response['message'] = "Required fields are missing or invalid.";
    }
} else {
    $response['status'] = false;
    $response['message'] = "Invalid request method.";
}

echo json_encode($response);
$conn->close();
?>
