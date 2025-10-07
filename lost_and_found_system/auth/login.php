<?php
session_start();
require_once '../config/dbconn.php';

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
    
    if (!empty($email) && !empty($password)) {
        try {
            $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password_hash, user_role FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if (password_verify($password, $user['password'])) {
                    // Login successful
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_role'] = $user['user_role'];
                    
                    // Return JSON response for AJAX
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        $redirect = $user['user_role'] === 'admin' ? 'admin/admin_dashboard.php' : 'student/dashboard.php';
                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Login successful!',
                            'redirect' => $redirect
                        ]);
                        exit();
                    } else {
                        // Regular form submission
                        if ($user['user_role'] === 'admin') {
                            header('Location: admin/admin_dashboard.php');
                        } else {
                            header('Location: ../student/dashboard.php');
                        }
                        exit();
                    }
                } else {
                    $error = "Invalid email or password";
                }
            } else {
                $error = "Invalid email or password";
            }
        } catch (Exception $e) {
            $error = "Login failed. Please try again.";
        }
    } else {
        $error = "Please fill in all fields";
    }
    
    // Return error for AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode([
            'status' => 'error',
            'message' => $error
        ]);
        exit();
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
        /* Background gradient */
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

        /* Welcome text */
        .welcome {
            color: #fff;
            text-align: center;
            margin-bottom: 2rem;
        }

        .welcome h2 {
            font-weight: 700;
            font-size: 1.8rem;
        }

        .welcome p {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* Card styling */
        .card {
            border-radius: 1rem;
            padding: 2rem;
            max-width: 420px;
            width: 100%;
            background: #fff;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .card h3 {
            font-weight: 600;
            margin-bottom: 1rem;
        }

        /* Logo */
        .logo {
            width: 100px;
            display: block;
            margin: 0 auto 1rem auto;
        }

        /* Inputs */
        .form-control {
            padding: 0.75rem;
            border-radius: 0.75rem;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem;
            border-radius: 0.75rem;
            font-weight: 500;
        }

        .btn-primary {
            background: #007bff;
            border: none;
        }

        .btn-primary:hover {
            background: #0056b3;
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

        /* Register link */
        .card p {
            margin-top: 1.5rem;
            font-size: 0.95rem;
        }

        .card a {
            text-decoration: none;
            font-weight: 500;
            color: #007bff;
        }

        .card a:hover {
            text-decoration: underline;
        }

        /* 🔹 Mobile adjustments */
        @media (max-width: 576px) {
            .card {
                max-width: 340px;
                padding: 1.25rem;
            }

            .logo {
                width: 80px;
            }

            .card h3 {
                font-size: 1.2rem;
            }

            .btn {
                padding: 0.6rem;
                font-size: 0.9rem;
            }

            .form-control {
                padding: 0.6rem;
                font-size: 0.9rem;
            }

            .welcome h2 {
                font-size: 1.4rem;
            }

            .welcome p {
                font-size: 0.9rem;
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
        <?php if (!empty($error) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form id="loginForm" method="POST" class="text-start">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" placeholder="Enter email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="password-container">
                    <input type="password" class="form-control" name="password" placeholder="Enter password" required>
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

        <p class="text-center mt-3">No account? <a href="register.php">Register here</a></p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // Password toggle
            $('.password-toggle').on('click', function() {
                const input = $(this).prev();
                const icon = $(this).find('i');
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('bi-eye').addClass('bi-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('bi-eye-slash').addClass('bi-eye');
                }
            });

            $('#loginForm').on('submit', function(e) {
                e.preventDefault();
                const btn = $('#loginBtn');
                const text = $('#loginText');
                const spinner = $('#loginSpinner');
                
                text.text('Logging in...');
                spinner.removeClass('d-none');
                btn.prop('disabled', true);
                
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        console.log('Login Response:', response);
                        
                        if (response.status === 'success') {
                            // Redirect to the appropriate dashboard
                            window.location.href = response.redirect;
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Login Failed',
                                text: response.message
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error, xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Login failed. Please try again.'
                        });
                    },
                    complete: function() {
                        text.text('Login');
                        spinner.addClass('d-none');
                        btn.prop('disabled', false);
                    }
                });
            });
        });
    </script>
</body>
</html>