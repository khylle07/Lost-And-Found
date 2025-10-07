<?php
session_start();

require_once 'config/dbconn.php';

$error = '';

// Check if user is already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['user_role'])) {
        if ($_SESSION['user_role'] === 'admin') {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: student/dashboard.php');
        }
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    error_log("=== LOGIN ATTEMPT ===");
    error_log("Email: " . $email);
    
    if (!empty($email) && !empty($password)) {
        try {
            // Check connection
            if (!$conn || $conn->connect_error) {
                throw new Exception("Database connection failed: " . $conn->connect_error);
            }
            
            // FIXED: Check both password and password_hash columns
            $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password_hash, user_role FROM users WHERE email = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            error_log("Database rows found: " . $result->num_rows);
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                error_log("=== USER FOUND ===");
                error_log("User ID: " . $user['user_id']);
                error_log("Name: " . $user['first_name'] . " " . $user['last_name']);
                error_log("Role: " . $user['user_role']);
                error_log("Stored hash: " . $user['password_hash']);
                
                // Check if user_role is NULL
                if ($user['user_role'] === null) {
                    error_log("ERROR: User role is NULL!");
                    $error = "Account configuration error. Please contact administrator.";
                }
                // Check password
                elseif (password_verify($password, $user['password_hash'])) {
                    error_log("PASSWORD VERIFICATION: SUCCESS");
                    
                    // Login successful - SET SESSION
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_role'] = $user['user_role'];
                    
                    error_log("SESSION SET - Role: " . $user['user_role']);
                    
                    // Return JSON response for AJAX
                    $redirect = ($user['user_role'] === 'admin') ? 'admin/admin_dashboard.php' : 'student/dashboard.php';
                    error_log("REDIRECTING TO: " . $redirect);

                    header('Location: ' . $redirect);
                    exit();
                    
                } else {
                    error_log("PASSWORD VERIFICATION: FAILED");
                    $error = "Invalid email or password";
                }
            } else {
                error_log("NO USER FOUND with email: " . $email);
                $error = "Invalid email or password";
            }
            
            $stmt->close();
        } catch (Exception $e) {
            error_log("LOGIN EXCEPTION: " . $e->getMessage());
            $error = "Login failed. Please try again.";
        }
    } else {
        $error = "Please fill in all fields";
    }
    
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | NCST Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            background: linear-gradient(135deg, #007bff, #6610f2);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 1rem;
        }
        .welcome {
            color: #fff;
            text-align: center;
            margin-bottom: 2rem;
        }
        .card {
            border-radius: 1rem;
            padding: 2rem;
            max-width: 420px;
            width: 100%;
            background: #fff;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .logo {
            width: 100px;
            display: block;
            margin: 0 auto 1rem auto;
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .password-container {
            position: relative;
        }
        @media (max-width: 576px) {
            .card {
                max-width: 340px;
                padding: 1.25rem;
            }
            .logo {
                width: 80px;
            }
        }
    </style>
</head>
<body>
    <!-- Welcome Message -->
    <div class="welcome">
        <h2>Welcome to NCST Lost & Found</h2>
        <p>Helping students reconnect with their belongings.</p>
    </div>

    <div class="card text-center">
        <!-- Logo -->
        <img src="assets/images/ncstlogo.jpg" alt="NCST Logo" class="logo" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iIzAwN2JmZiIvPjx0ZXh0IHg9IjUwIiB5PSI1MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5DU1Q8L3RleHQ+PC9zdmc+'">
        <h3>Login To Your Account</h3>

        <!-- Display PHP errors for non-AJAX submissions -->
        <?php if (!empty($error) && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_SERVER['HTTP_X_REQUESTED_WITH'])): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" class="text-start">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" placeholder="Enter email" required value="khyllechester@ncst.edu.ph">
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="password-container">
                    <input type="password" class="form-control" name="password" placeholder="Enter password" required value="khylle123">
                    <span class="password-toggle">
                        <i class="bi bi-eye"></i>
                    </span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100" id="loginBtn">
                <span id="loginText">Login</span>
                <div id="loginSpinner" class="spinner-border spinner-border-sm d-none" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </button>
        </form>

        <p class="text-center mt-3">No account? <a href="auth/register.php">Register here</a></p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        // Keep only the password toggle functionality
        $('.password-toggle').on('click', function() {
            const input = $(this).siblings('input');
            const icon = $(this).find('i');
            
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('bi-eye').addClass('bi-eye-slash');
            } else {
                input.attr('type', 'password');
                icon.removeClass('bi-eye-slash').addClass('bi-eye');
            }
        });
        
        // Remove the entire AJAX form submission
        // The form will now submit normally via PHP
        });
        </script>
    </script>
</body>
</html>