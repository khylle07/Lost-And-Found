<?php
require_once '../config/dbconn.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND is_active = TRUE");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows === 1) {
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $insert_stmt = $conn->prepare("INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("sss", $email, $token, $expires_at);
        
        if ($insert_stmt->execute()) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'Password reset link has been sent to your email.'
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to generate reset token.']);
        }
        
        $insert_stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Email not found or account is inactive.']);
    }
    
    $stmt->close();
    $conn->close();
    exit();
}
?>