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
    $title = $conn->real_escape_string(trim($_POST['title']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $category_id = intval($_POST['category_id']);
    $location = $conn->real_escape_string(trim($_POST['location']));
    $date_found = $conn->real_escape_string($_POST['date_found']);
    $color = $conn->real_escape_string(trim($_POST['color']));
    $brand = $conn->real_escape_string(trim($_POST['brand']));
    $contact_info = $conn->real_escape_string(trim($_POST['contact_info']));
    
    // Basic validation
    if (empty($title) || empty($description) || empty($location) || empty($date_found) || $category_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit();
    }
    
    // Validate date is not in the future
    if (strtotime($date_found) > time()) {
        echo json_encode(['status' => 'error', 'message' => 'Date found cannot be in the future.']);
        exit();
    }
    
    $stmt = $conn->prepare("INSERT INTO items (user_id, category_id, title, description, item_type, location, date_lost_found, color, brand, contact_info) VALUES (?, ?, ?, ?, 'found', ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssssss", $user_id, $category_id, $title, $description, $location, $date_found, $color, $brand, $contact_info);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Found item reported successfully! It will be reviewed by admin.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error reporting found item: ' . $stmt->error]);
    }
    $stmt->close();
    exit();
}

// Get categories for dropdown
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Found Item | NCST Lost & Found</title>
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
        
        /* Form Styles */
        .report-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
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
            
            .form-section {
                padding: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 0.75rem;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .form-section {
                padding: 0.75rem;
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
            <a href="report_found.php" class="nav-link active">
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
            <a href="account.php" class="nav-link">
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
                        <h2 class="mb-0"><i class="bi bi-plus-circle-dotted me-2"></i>Report Found Item</h2>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>

                    <div class="card report-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0"><i class="bi bi-info-circle me-2"></i>Found Item Information</h5>
                        </div>
                        <div class="card-body p-4">
                            <form id="reportForm">
                                <!-- Basic Information Section -->
                                <div class="form-section">
                                    <h6 class="section-title"><i class="bi bi-card-text me-2"></i>Basic Information</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Item Title *</label>
                                            <input type="text" class="form-control" name="title" 
                                                   placeholder="e.g., Black Wallet, Samsung Phone, etc." required
                                                   maxlength="100">
                                            <div class="form-text">Brief description of the found item</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Category *</label>
                                            <select class="form-control" name="category_id" required>
                                                <option value="">Select a category</option>
                                                <?php 
                                                $categories->data_seek(0); // Reset pointer
                                                while($category = $categories->fetch_assoc()): ?>
                                                    <option value="<?php echo $category['category_id']; ?>">
                                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Description *</label>
                                        <textarea class="form-control" name="description" rows="4" 
                                                  placeholder="Provide detailed description of the item, including any distinctive features, contents, condition, etc."
                                                  required></textarea>
                                        <div class="form-text">Be as detailed as possible to help the owner identify their item</div>
                                    </div>
                                </div>

                                <!-- Location & Date Section -->
                                <div class="form-section">
                                    <h6 class="section-title"><i class="bi bi-geo-alt me-2"></i>Location & Date</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Where did you find it? *</label>
                                            <input type="text" class="form-control" name="location" 
                                                   placeholder="e.g., NCST Library, Cafeteria, Building A, etc."
                                                   required>
                                            <div class="form-text">Specific location where you found the item</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">When did you find it? *</label>
                                            <input type="date" class="form-control" name="date_found" 
                                                   max="<?php echo date('Y-m-d'); ?>" required>
                                            <div class="form-text">Date when you found the item</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Item Details Section -->
                                <div class="form-section">
                                    <h6 class="section-title"><i class="bi bi-tags me-2"></i>Item Details</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Color</label>
                                            <input type="text" class="form-control" name="color" 
                                                   placeholder="e.g., Black, Red, Blue, etc."
                                                   maxlength="30">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Brand</label>
                                            <input type="text" class="form-control" name="brand" 
                                                   placeholder="e.g., Samsung, Nike, etc."
                                                   maxlength="50">
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact Information -->
                                <div class="form-section">
                                    <h6 class="section-title"><i class="bi bi-telephone me-2"></i>Contact Information</h6>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">How to Contact You</label>
                                        <input type="text" class="form-control" name="contact_info" 
                                               placeholder="e.g., Alternative phone number, specific instructions for contact"
                                               value="<?php echo $_SESSION['contact_number'] ?? ''; ?>">
                                        <div class="form-text">How should the owner contact you to claim their item? (Your registered contact number will be used if left blank)</div>
                                    </div>
                                </div>

                                <!-- Important Notes -->
                                <div class="alert alert-success">
                                    <h6><i class="bi bi-exclamation-circle me-2"></i>Important Notes</h6>
                                    <ul class="mb-0">
                                        <li>All reports will be reviewed by administrators before being published</li>
                                        <li>Provide accurate information to help the rightful owner identify their item</li>
                                        <li>Do not include sensitive personal information in the description</li>
                                        <li>You will be contacted when someone claims the item</li>
                                        <li>Keep the item safe until it is claimed by the rightful owner</li>
                                    </ul>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                    <a href="dashboard.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle me-1"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-success" id="submitBtn">
                                        <i class="bi bi-send me-1"></i>
                                        <span id="submitText">Submit Report</span>
                                        <div id="submitSpinner" class="spinner-border spinner-border-sm d-none" role="status">
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
            $('#reportForm').on('submit', function(e) {
                e.preventDefault();
                
                const btn = $('#submitBtn');
                const text = $('#submitText');
                const spinner = $('#submitSpinner');
                
                text.text('Submitting...');
                spinner.removeClass('d-none');
                btn.prop('disabled', true);
                
                $.ajax({
                    url: 'report_found.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Report Submitted!',
                                text: response.message,
                                confirmButtonText: 'OK'
                            }).then(() => {
                                window.location.href = 'dashboard.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Submission Failed',
                                text: response.message
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while submitting your report. Please try again.'
                        });
                    },
                    complete: function() {
                        text.text('Submit Report');
                        spinner.addClass('d-none');
                        btn.prop('disabled', false);
                    }
                });
            });

            // Set max date to today
            $('input[name="date_found"]').attr('max', new Date().toISOString().split('T')[0]);
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>