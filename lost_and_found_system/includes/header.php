<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Determine user role and name for display
$user_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'Guest';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Generate profile picture URL
$profile_pic_url = "https://api.dicebear.com/7.x/identicon/svg?seed=" . urlencode($user_email ?: 'default');

// Determine dashboard URL based on role
$dashboard_url = 'index.php';
if ($is_logged_in) {
    $dashboard_url = ($user_role === 'admin') ? 'admin/dashboard.php' : 'student/dashboard.php';
}
?>