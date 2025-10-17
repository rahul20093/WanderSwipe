<?php
require_once '../../config/config.php';
require_once '../../vendor/autoload.php'; // Assuming you use Composer for dependencies

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$identityToken = $data['identityToken'] ?? '';
$userIdentifier = $data['userIdentifier'] ?? '';
$givenName = $data['givenName'] ?? '';
$familyName = $data['familyName'] ?? '';
$email = $data['email'] ?? '';

if (empty($identityToken) || empty($userIdentifier)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Identity token and user identifier are required.']);
    exit;
}

// 1. Fetch Apple's public keys
$applePublicKeys = json_decode(file_get_contents('https://appleid.apple.com/auth/keys'), true);

// 2. Decode the token
try {
    $decodedToken = null;
    foreach ($applePublicKeys['keys'] as $key) {
        try {
            $decodedToken = JWT::decode($identityToken, new Key($key['kty'], $key['alg'], $key['n'], $key['e']));
            // If decode succeeds, break the loop
            break;
        } catch (Exception $e) {
            // Try next key
            continue;
        }
    }

    if (!$decodedToken) {
        throw new Exception('Could not decode token with any of Apple\'s public keys.');
    }

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Invalid token: ' . $e->getMessage()]);
    exit;
}


// 3. Verify the token's claims
if ($decodedToken->iss !== 'https://appleid.apple.com' || $decodedToken->aud !== 'com.sail01.wanderSwipe' || $decodedToken->sub !== $userIdentifier) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Invalid token claims.']);
    exit;
}


// 4. Check user in database
try {
    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE apple_user_id = ?");
    $stmt->bind_param("s", $userIdentifier);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // User exists, log them in (generate your app's token)
        echo json_encode([
            'status' => true,
            'message' => 'User logged in successfully',
            'data' => [['id' => $user['id'], 'username' => $user['username'], 'email' => $user['email']]]
        ]);
    } else {
        // User does not exist, create a new user
        $username = trim($givenName . ' ' . $familyName);
        if (empty($username)) {
            $username = 'User' . time(); // Fallback username
        }
        
        // Use the email from Apple if available, otherwise generate a placeholder
        $userEmail = !empty($email) ? $email : $userIdentifier . '@privaterelay.appleid.com';

        $insertStmt = $conn->prepare("INSERT INTO users (username, email, apple_user_id) VALUES (?, ?, ?)");
        $insertStmt->bind_param("sss", $username, $userEmail, $userIdentifier);
        $insertStmt->execute();
        
        $newUserId = $insertStmt->insert_id;
        
        echo json_encode([
            'status' => true,
            'message' => 'User registered and logged in successfully',
            'data' => [['id' => $newUserId, 'username' => $username, 'email' => $userEmail]]
        ]);
        $insertStmt->close();
    }
    
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>