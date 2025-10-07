<?php
session_start();
require_once '../config/dbconn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $student_number = $conn->real_escape_string($_POST['student_number']);
    $course = $conn->real_escape_string($_POST['course']);
    $contact_number = $conn->real_escape_string($_POST['contact']);
    $address = $conn->real_escape_string($_POST['address']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    $check_email->store_result();
    
    if ($check_email->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already registered. Please use a different email.']);
        exit();
    } else {
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password_hash, student_number, course, contact_number, address, user_role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'student')");
        $stmt->bind_param("ssssssss", $first_name, $last_name, $email, $password, $student_number, $course, $contact_number, $address);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Registration successful! You can now login.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $stmt->error]);
        }
        
        $stmt->close();
    }
    
    $check_email->close();
    $conn->close();
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | NCST Lost & Found</title>
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
            margin-bottom: 1rem;
        }
        .card {
            border-radius: 1rem;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
            background: #fff;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .logo {
            width: 90px;
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
                padding: 1.5rem;
            }
            .logo {
                width: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="welcome">
        <h2 class="mb-3">Join NCST Lost & Found</h2>
        <p class="mb-0">Create an account to report or claim items easily.</p>
    </div>

    <div class="card">
        <img href="C:\xampp\htdocs\lost_and_found_system\assets\logo.png" alt="NCST Logo" class="logo">
        <h3 class="text-center mb-4">Create Your Account</h3>

        <form method="POST" id="registrationForm" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" class="form-control" name="first_name" placeholder="Enter first name" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input type="text" class="form-control" name="last_name" placeholder="Enter last name" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Student Number</label>
                <input type="text" class="form-control" name="student_number" placeholder="e.g. 2025-0001" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Course</label>
                <select class="form-control" name="course" required>
                    <option value="" disabled selected>Select your course</option>
                    <option value="BSIT">BS Information Technology</option>
                    <option value="BSCS">BS Computer Science</option>
                    <option value="BSIS">BS Information Systems</option>
                    <option value="BSEE">BS Electrical Engineering</option>
                    <option value="BSME">BS Mechanical Engineering</option>
                    <option value="BSBA">BS Business Administration</option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Contact Number</label>
                <input type="text" class="form-control" name="contact" placeholder="e.g. 09123456789" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Address</label>
                <input type="text" class="form-control" name="address" placeholder="Enter address" required>
            </div>

            <div class="col-12">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" placeholder="Enter student email" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Password</label>
                <div class="password-container">
                    <input type="password" class="form-control" name="password" placeholder="Enter password" required>
                    <span class="password-toggle">
                        <i class="bi bi-eye"></i>
                    </span>
                </div>
                <div class="form-text">Must be at least 8 characters</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Confirm Password</label>
                <div class="password-container">
                    <input type="password" class="form-control" name="confirm_password" placeholder="Confirm password" required>
                    <span class="password-toggle">
                        <i class="bi bi-eye"></i>
                    </span>
                </div>
                <div id="passwordMatch" class="form-text"></div>
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="#">Terms and Conditions</a>
                    </label>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-success w-100" id="registerBtn">
                    <span id="registerText">Register</span>
                    <div id="registerSpinner" class="spinner-border spinner-border-sm d-none" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </button>
            </div>
        </form>

        <p class="text-center mt-3">Already have an account? <a href="../index.php">Login here</a></p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
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

            function validatePassword() {
                const password = $('input[name="password"]').val();
                const confirmPassword = $('input[name="confirm_password"]').val();
                const passwordMatch = $('#passwordMatch');
                
                if (confirmPassword === '') {
                    passwordMatch.text('');
                } else if (password !== confirmPassword) {
                    passwordMatch.css('color', 'red').text('Passwords do not match');
                } else {
                    passwordMatch.css('color', 'green').text('Passwords match');
                }
            }

            $('input[name="password"], input[name="confirm_password"]').on('input', validatePassword);

            $('#registrationForm').on('submit', function(e) {
                e.preventDefault();
                
                if ($('input[name="password"]').val() !== $('input[name="confirm_password"]').val()) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Passwords do not match!'
                    });
                    return;
                }
                
                const btn = $('#registerBtn');
                const text = $('#registerText');
                const spinner = $('#registerSpinner');
                
                text.text('Registering...');
                spinner.removeClass('d-none');
                btn.prop('disabled', true);
                
                $.ajax({
                    url: 'register.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message,
                                confirmButtonText: 'Go to Login'
                            }).then(() => {
                                window.location.href = '../index.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Registration Failed',
                                text: response.message
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred. Please try again.'
                        });
                    },
                    complete: function() {
                        text.text('Register');
                        spinner.addClass('d-none');
                        btn.prop('disabled', false);
                    }
                });
            });

            // Student number validation
            $('input[name="student_number"]').on('input', function() {
                const pattern = /^\d{4}-\d{4}$/;
                if (!pattern.test(this.value)) {
                    this.setCustomValidity("Please use format: YYYY-NNNN (e.g., 2025-0001)");
                } else {
                    this.setCustomValidity("");
                }
            });

            // Contact number validation
            $('input[name="contact"]').on('input', function() {
                const pattern = /^09\d{9}$/;
                if (!pattern.test(this.value)) {
                    this.setCustomValidity("Please enter a valid 11-digit Philippine mobile number starting with 09");
                } else {
                    this.setCustomValidity("");
                }
            });
        });
    </script>
</body>
</html>