<?php
require_once "dbconn.php"; // adjust path if needed

$email = "admin@ncst.edu.ph";  // user you want to fix
$newPlainPassword = "admin123"; // their real password

$hashedPassword = password_hash($newPlainPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$stmt->bind_param("ss", $hashedPassword, $email);

if ($stmt->execute()) {
    echo "Password rehashed successfully for $email";
} else {
    echo "Error: " . $conn->error;
}