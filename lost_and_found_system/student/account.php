<?php
require_once '../config/session_check.php';
if (!isStudent()) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/dbconn.php';

$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $course = $conn->real_escape_string($_POST['course']);
    $contact_number = $conn->real_escape_string($_POST['contact_number']);
    $address = $conn->real_escape_string($_POST['address']);
    
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, course = ?, contact_number = ?, address = ?, updated_at = NOW() WHERE user_id = ?");
    $stmt->bind_param("sssssi", $first_name, $last_name, $course, $contact_number, $address, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error updating profile: ' . $stmt->error]);
    }
    $stmt->close();
    exit();
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account | NCST Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root { 
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
        }
        
        body { 
            background: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand img {
            width: 32px;
            height: 32px;
        }
        
        /* Sidebar Styles */
        .sidebar {
            background: #fff;
            border-right: 1px solid #e0e0e0;
            width: var(--sidebar-width);
            min-height: calc(100vh - 56px);
            position: fixed;
            top: 56px;
            left: 0;
            transition: all 0.3s ease;
            z-index: 1000;
            padding: 1rem 0;
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            color: #333;
            padding: 0.75rem 1.5rem;
            margin: 0.25rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
        }
        
        .sidebar.collapsed .nav-link span {
            display: none;
        }
        
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }
        
        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 0.75rem;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 56px);
        }
        
        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        /* Account Card */
        .account-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .account-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 1rem 1rem 0 0;
            padding: 2rem;
            text-align: center;
            color: white;
        }
        
        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 1rem;
        }
        
        /* Form Styles */
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* Mobile Styles */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 1rem;
            }
            
            .account-header {
                padding: 1.5rem;
            }
            
            .profile-pic {
                width: 100px;
                height: 100px;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 0.75rem;
            }
            
            .account-header {
                padding: 1rem;
            }
            
            .profile-pic {
                width: 80px;
                height: 80px;
            }
            
            h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-dark bg-primary navbar-expand-lg fixed-top">
        <div class="container-fluid">
            <!-- Mobile toggle button -->
            <button class="navbar-toggler me-2" type="button" id="mobileToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Desktop toggle button -->
            <button class="btn btn-outline-light d-none d-lg-inline-flex me-3" id="desktopToggle">
                <i class="bi bi-list"></i>
            </button>
            
            <!-- Brand -->
            <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
                <img src="../assets/images/ncstlogo.jpg" alt="NCST Logo" class="me-2">
                <span>NCST Lost & Found</span>
            </a>
            
            <!-- User menu -->
            <div class="dropdown ms-auto">
                <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                    <img src="https://api.dicebear.com/7.x/identicon/svg?seed=<?php echo $_SESSION['email']; ?>" 
                         alt="Profile" width="32" height="32" class="rounded-circle me-2">
                    <span class="d-none d-sm-inline"><?php echo $_SESSION['first_name']; ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="account.php"><i class="bi bi-person-circle me-2"></i>My Account</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="nav flex-column">
            <a href="dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="report_lost.php" class="nav-link">
                <i class="bi bi-plus-circle"></i>
                <span>Report Lost Item</span>
            </a>
            <a href="report_found.php" class="nav-link">
                <i class="bi bi-plus-circle-dotted"></i>
                <span>Report Found Item</span>
            </a>
            <a href="reports.php" class="nav-link">
                <i class="bi bi-search"></i>
                <span>Browse Items</span>
            </a>
            <a href="claim.php" class="nav-link">
                <i class="bi bi-box-seam"></i>
                <span>Claim Item</span>
            </a>
            <a href="account.php" class="nav-link active">
                <i class="bi bi-person-circle"></i>
                <span>My Account</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-10 col-xl-8">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0"><i class="bi bi-person-circle me-2"></i>My Account</h2>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>

                    <div class="card account-card">
                        <div class="account-header">
                            <img src="https://api.dicebear.com/7.x/identicon/svg?seed=<?php echo $user['email']; ?>" 
                                 alt="Profile Picture" class="profile-pic">
                            <h4 class="fw-bold mb-2"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                            <p class="mb-0 opacity-75"><?php echo htmlspecialchars($user['course'] ?: 'No course specified'); ?></p>
                        </div>
                        
                        <div class="card-body p-4">
                            <form id="accountForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">First Name</label>
                                        <input type="text" class="form-control" name="first_name" 
                                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Last Name</label>
                                        <input type="text" class="form-control" name="last_name" 
                                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Student Number</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['student_number']); ?>" readonly>
                                        <div class="form-text">Student number cannot be changed</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Course</label>
                                        <select class="form-control" name="course" required>
                                            <option value="">Select your course</option>
                                            <option value="BSIT" <?php echo $user['course'] == 'BSIT' ? 'selected' : ''; ?>>BS Information Technology</option>
                                            <option value="BSCS" <?php echo $user['course'] == 'BSCS' ? 'selected' : ''; ?>>BS Computer Science</option>
                                            <option value="BSIS" <?php echo $user['course'] == 'BSIS' ? 'selected' : ''; ?>>BS Information Systems</option>
                                            <option value="BSEE" <?php echo $user['course'] == 'BSEE' ? 'selected' : ''; ?>>BS Electrical Engineering</option>
                                            <option value="BSME" <?php echo $user['course'] == 'BSME' ? 'selected' : ''; ?>>BS Mechanical Engineering</option>
                                            <option value="BSBA" <?php echo $user['course'] == 'BSBA' ? 'selected' : ''; ?>>BS Business Administration</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Email Address</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                    <div class="form-text">Email address cannot be changed</div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Contact Number</label>
                                        <input type="tel" class="form-control" name="contact_number" 
                                               value="<?php echo htmlspecialchars($user['contact_number']); ?>" required
                                               pattern="^09\d{9}$" title="Please enter a valid 11-digit Philippine mobile number starting with 09">
                                        <div class="form-text">Format: 09123456789</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Address</label>
                                        <input type="text" class="form-control" name="address" 
                                               value="<?php echo htmlspecialchars($user['address']); ?>" required
                                               placeholder="Enter your complete address">
                                    </div>
                                </div>
                                
                                <div class="mb-4 p-3 bg-light rounded">
                                    <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle me-2"></i>Account Information</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <small class="text-muted">Member since:</small>
                                            <p class="mb-1 fw-semibold"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted">Last updated:</small>
                                            <p class="mb-1 fw-semibold"><?php echo date('F j, Y g:i A', strtotime($user['updated_at'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                    <a href="dashboard.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle me-1"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary" id="updateBtn">
                                        <i class="bi bi-check-circle me-1"></i>
                                        <span id="updateText">Update Profile</span>
                                        <div id="updateSpinner" class="spinner-border spinner-border-sm d-none" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            const sidebar = $('#sidebar');
            const mainContent = $('#mainContent');
            const desktopToggle = $('#desktopToggle');
            const mobileToggle = $('#mobileToggle');
            let isCollapsed = false;

            // Desktop toggle
            desktopToggle.on('click', function() {
                isCollapsed = !isCollapsed;
                sidebar.toggleClass('collapsed', isCollapsed);
                mainContent.toggleClass('expanded', isCollapsed);
                
                // Update icon
                const icon = $(this).find('i');
                icon.toggleClass('bi-list', !isCollapsed);
                icon.toggleClass('bi-layout-sidebar', isCollapsed);
            });

            // Mobile toggle
            mobileToggle.on('click', function() {
                sidebar.toggleClass('mobile-open');
            });

            // Close sidebar when clicking outside on mobile
            $(document).on('click', function(e) {
                if ($(window).width() <= 768) {
                    if (!sidebar.is(e.target) && sidebar.has(e.target).length === 0 && 
                        !mobileToggle.is(e.target) && mobileToggle.has(e.target).length === 0) {
                        sidebar.removeClass('mobile-open');
                    }
                }
            });

            // Handle window resize
            $(window).on('resize', function() {
                if ($(window).width() > 768) {
                    sidebar.removeClass('mobile-open');
                }
            });

            // Form submission
            $('#accountForm').on('submit', function(e) {
                e.preventDefault();
                
                const btn = $('#updateBtn');
                const text = $('#updateText');
                const spinner = $('#updateSpinner');
                
                text.text('Updating...');
                spinner.removeClass('d-none');
                btn.prop('disabled', true);
                
                $.ajax({
                    url: 'account.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                // Refresh the page to show updated data
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Update Failed',
                                text: response.message
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while updating your profile. Please try again.'
                        });
                    },
                    complete: function() {
                        text.text('Update Profile');
                        spinner.addClass('d-none');
                        btn.prop('disabled', false);
                    }
                });
            });

            // Contact number validation
            $('input[name="contact_number"]').on('input', function() {
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
<?php $conn->close(); ?>