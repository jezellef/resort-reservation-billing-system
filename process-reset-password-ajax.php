<?php
header('Content-Type: application/json');

try {
    $token = $_POST["token"];
    $token_hash = hash("sha256", $token);
    $mysqli = require __DIR__ . "/database.php";  // Note: Fixed the __DIR__ syntax
    
    $sql = "SELECT * FROM user WHERE reset_token_hash = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if($user === null){
        echo json_encode(['success' => false, 'message' => 'Token not found']);
        exit;
    }
    
    if(strtotime($user["reset_token_expires_at"]) <= time()){
        echo json_encode(['success' => false, 'message' => 'Token has expired']);
        exit;
    }
    
    if (empty($_POST['password'])) {
        echo json_encode(['success' => false, 'message' => 'Please input password']);
        exit;
    } elseif (strlen($_POST['password']) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password too short']);
        exit;
    } elseif (!preg_match('/[a-z]/i', $_POST['password'])) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one letter']);
        exit;
    } elseif (!preg_match('/[0-9]/', $_POST['password'])) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one number']);
        exit;
    } elseif ($_POST['password'] !== $_POST['password_confirmation']) {
        echo json_encode(['success' => false, 'message' => 'Passwords don\'t match']);
        exit;
    }
    
    // If there are no errors, proceed with registration
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $sql = "UPDATE user SET password_hash = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ss", $password_hash, $user["id"]);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $mysqli->error]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>