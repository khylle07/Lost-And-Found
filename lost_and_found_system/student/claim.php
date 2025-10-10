<?php
require_once '../config/session_check.php';
if (!isStudent()) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/dbconn.php';

$user_id = $_SESSION['user_id'];
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

// Get item details if item_id is provided
$item = null;
if ($item_id > 0) {
    $stmt = $conn->prepare("SELECT i.*, c.category_name, u.first_name, u.last_name 
                           FROM items i 
                           JOIN categories c ON i.category_id = c.category_id 
                           JOIN users u ON i.user_id = u.user_id 
                           WHERE i.item_id = ? AND i.status = 'approved' AND i.item_type = 'found'");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
}

// Handle claim submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $item_id = intval($_POST['item_id']);
    $proof = $conn->real_escape_string(trim($_POST['proof_of_ownership']));
    
    // Validate proof of ownership
    if (empty($proof) || strlen($proof) < 10) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide detailed proof of ownership (at least 10 characters).']);
        exit();
    }
    
    // Check if item exists and is available for claim
    $check_item = $conn->prepare("SELECT item_id FROM items WHERE item_id = ? AND status = 'approved' AND item_type = 'found'");
    $check_item->bind_param("i", $item_id);
    $check_item->execute();
    $check_item->store_result();
    
    if ($check_item->num_rows == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Item not found or not available for claim.']);
        $check_item->close();
        exit();
    }
    $check_item->close();
    
    // Check if user already claimed this item
    $check_stmt = $conn->prepare("SELECT claim_id FROM claims WHERE item_id = ? AND claimant_id = ?");
    $check_stmt->bind_param("ii", $item_id, $user_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'You have already submitted a claim for this item.']);
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO claims (item_id, claimant_id, proof_of_ownership) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $item_id, $user_id, $proof);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Claim submitted successfully! The admin will review your claim.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error submitting claim: ' . $stmt->error]);
    }
    $stmt->close();
    exit();
}

// Get user's pending claims with proper prepared statement
$claim_stmt = $conn->prepare("
    SELECT c.*, i.title, i.item_type 
    FROM claims c 
    JOIN items i ON c.item_id = i.item_id 
    WHERE c.claimant_id = ? 
    ORDER BY c.claimed_at DESC
");
$claim_stmt->bind_param("i", $user_id);
$claim_stmt->execute();
$pending_claims = $claim_stmt->get_result();
$claim_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Item | NCST Lost & Found</title>
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
        
        /* Card Styles */
        .claim-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .item-card {
            border-left: 4px solid #28a745;
            background: #f8fff9;
        }
        
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
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
            
            .item-card {
                margin-bottom: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 0.75rem;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .btn-group .btn {
                font-size: 0.875rem;
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
            <a href="claim.php" class="nav-link active">
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
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0"><i class="bi bi-box-seam me-2"></i>Claim Item</h2>
                        <a href="reports.php" class="btn btn-outline-primary">
                            <i class="bi bi-search me-1"></i>Browse Items
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Claim Form Section -->
                <div class="col-lg-6 mb-4">
                    <div class="card claim-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-pencil-square me-2"></i>
                                <?php echo $item ? 'Claim This Item' : 'Submit a Claim'; ?>
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($item): ?>
                                <!-- Item Details -->
                                <div class="card item-card mb-4">
                                    <div class="card-body">
                                        <h6 class="card-title text-success">Item to Claim</h6>
                                        <h5 class="mb-2"><?php echo htmlspecialchars($item['title']); ?></h5>
                                        <p class="text-muted mb-2"><?php echo htmlspecialchars($item['description']); ?></p>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                            <?php if ($item['color']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($item['color']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($item['brand']): ?>
                                                <span class="badge bg-warning"><?php echo htmlspecialchars($item['brand']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small">
                                            <div><i class="bi bi-geo-alt me-1"></i> Found at: <?php echo htmlspecialchars($item['location']); ?></div>
                                            <div><i class="bi bi-calendar me-1"></i> Date found: <?php echo date('M j, Y', strtotime($item['date_lost_found'])); ?></div>
                                            <div><i class="bi bi-person me-1"></i> Reported by: <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Claim Form -->
                                <form id="claimForm">
                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Proof of Ownership *</label>
                                        <textarea class="form-control" name="proof_of_ownership" rows="6" 
                                                  placeholder="Please provide detailed information that proves this item belongs to you. Include:
- Specific identifying features
- Contents of the item (if applicable)
- When and where you lost it
- Any other information that can help verify your ownership"
                                                  required></textarea>
                                        <div class="form-text">
                                            Be as detailed as possible. The more specific information you provide, the easier it will be to verify your claim.
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <h6><i class="bi bi-info-circle me-2"></i>Important Information</h6>
                                        <ul class="mb-0 small">
                                            <li>Your claim will be reviewed by administrators</li>
                                            <li>You will be notified once your claim is processed</li>
                                            <li>Provide accurate information to avoid claim rejection</li>
                                            <li>False claims may result in account suspension</li>
                                        </ul>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                            <i class="bi bi-send me-1"></i>
                                            <span id="submitText">Submit Claim</span>
                                            <div id="submitSpinner" class="spinner-border spinner-border-sm d-none" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <!-- No Item Selected -->
                                <div class="text-center py-4">
                                    <i class="bi bi-box-seam display-1 text-muted"></i>
                                    <h4 class="text-muted mt-3">No Item Selected</h4>
                                    <p class="text-muted mb-4">Please select an item from the browse page to claim it.</p>
                                    <a href="reports.php" class="btn btn-primary">
                                        <i class="bi bi-search me-1"></i>Browse Found Items
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Pending Claims Section -->
                <div class="col-lg-6">
                    <div class="card claim-card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clock me-2"></i>
                                Your Pending Claims
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($pending_claims->num_rows > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php while($claim = $pending_claims->fetch_assoc()): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($claim['title']); ?></h6>
                                            <span class="badge status-badge bg-warning">Pending</span>
                                        </div>
                                        <p class="text-muted small mb-2">
                                            <?php echo substr(htmlspecialchars($claim['proof_of_ownership']), 0, 100); ?>...
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                Submitted: <?php echo date('M j, Y g:i A', strtotime($claim['claimed_at'])); ?>
                                            </small>
                                            <button class="btn btn-sm btn-outline-secondary" 
                                                    data-bs-toggle="tooltip" 
                                                    title="View claim details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-inbox display-1 text-muted"></i>
                                    <h5 class="text-muted mt-3">No Pending Claims</h5>
                                    <p class="text-muted">You haven't submitted any claims yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Claim Process Info -->
                    <div class="card claim-card mt-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-question-circle me-2"></i>
                                How Claiming Works
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-2" 
                                         style="width: 60px; height: 60px;">
                                        <i class="bi bi-search text-primary fs-4"></i>
                                    </div>
                                    <h6>1. Find Item</h6>
                                    <small class="text-muted">Browse found items</small>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-2" 
                                         style="width: 60px; height: 60px;">
                                        <i class="bi bi-pencil text-primary fs-4"></i>
                                    </div>
                                    <h6>2. Submit Claim</h6>
                                    <small class="text-muted">Provide proof of ownership</small>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-2" 
                                         style="width: 60px; height: 60px;">
                                        <i class="bi bi-check-circle text-primary fs-4"></i>
                                    </div>
                                    <h6>3. Get Approved</h6>
                                    <small class="text-muted">Admin reviews your claim</small>
                                </div>
                            </div>
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

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Claim form submission
            $('#claimForm').on('submit', function(e) {
                e.preventDefault();
                
                const btn = $('#submitBtn');
                const text = $('#submitText');
                const spinner = $('#submitSpinner');
                
                text.text('Submitting...');
                spinner.removeClass('d-none');
                btn.prop('disabled', true);
                
                $.ajax({
                    url: 'claim.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Claim Submitted!',
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
                            text: 'An error occurred while submitting your claim. Please try again.'
                        });
                    },
                    complete: function() {
                        text.text('Submit Claim');
                        spinner.addClass('d-none');
                        btn.prop('disabled', false);
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>