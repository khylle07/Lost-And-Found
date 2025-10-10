<?php
require_once '../config/session_check.php';
if (!isStudent()) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/dbconn.php';
$user_id = $_SESSION['user_id'];

// Get user stats
$lost_items = 0;
$found_items = 0;
$pending_claims = 0;

try {
    // Lost items count
    $lost_stmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE user_id = ? AND item_type = 'lost'");
    $lost_stmt->bind_param("i", $user_id);
    $lost_stmt->execute();
    $lost_result = $lost_stmt->get_result();
    $lost_items = $lost_result->fetch_assoc()['count'];
    $lost_stmt->close();
    
    // Found items count
    $found_stmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE user_id = ? AND item_type = 'found'");
    $found_stmt->bind_param("i", $user_id);
    $found_stmt->execute();
    $found_result = $found_stmt->get_result();
    $found_items = $found_result->fetch_assoc()['count'];
    $found_stmt->close();
    
    // Pending claims count
    $claims_stmt = $conn->prepare("SELECT COUNT(*) as count FROM claims WHERE claimant_id = ? AND status = 'pending'");
    $claims_stmt->bind_param("i", $user_id);
    $claims_stmt->execute();
    $claims_result = $claims_stmt->get_result();
    $pending_claims = $claims_result->fetch_assoc()['count'];
    $claims_stmt->close();

    // Get recent items
    $recent_stmt = $conn->prepare("
        SELECT i.*, c.category_name 
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.category_id 
        WHERE i.user_id = ? 
        ORDER BY i.created_at DESC 
        LIMIT 5
    ");
    $recent_stmt->bind_param("i", $user_id);
    $recent_stmt->execute();
    $recent_items = $recent_stmt->get_result();
    $recent_stmt->close();
    
} catch (Exception $e) {
    error_log("Student dashboard error: " . $e->getMessage());
    $recent_items = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | NCST Lost & Found</title>
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
        
        /* Stats Cards */
        .stat-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card .card-body {
            padding: 1.5rem;
        }
        
        .stat-card i {
            font-size: 2rem;
            opacity: 0.8;
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
            
            .stat-card .card-body {
                padding: 1rem;
            }
            
            .navbar-brand span {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 0.75rem;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .stat-card i {
                font-size: 1.5rem;
            }
        }
        
        /* Table Responsive */
        .table-responsive {
            border-radius: 0.5rem;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
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
            <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
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
            <a href="dashboard.php" class="nav-link active">
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
            <a href="account.php" class="nav-link">
                <i class="bi bi-person-circle"></i>
                <span>My Account</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <!-- Welcome Message -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="mb-2">Welcome back, <?php echo $_SESSION['first_name']; ?>! 👋</h2>
                    <p class="text-muted">Here's your lost and found activity summary.</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-5">
                <div class="col-xl-4 col-md-6">
                    <div class="card stat-card text-bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Lost Items Reported</h6>
                                    <h2 class="text-white mb-0"><?php echo $lost_items; ?></h2>
                                </div>
                                <i class="bi bi-search text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="card stat-card text-bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Found Items Reported</h6>
                                    <h2 class="text-white mb-0"><?php echo $found_items; ?></h2>
                                </div>
                                <i class="bi bi-check-circle text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="card stat-card text-bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Pending Claims</h6>
                                    <h2 class="text-white mb-0"><?php echo $pending_claims; ?></h2>
                                </div>
                                <i class="bi bi-clock text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reports -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-transparent">
                            <h5 class="card-title mb-0">Recent Reports</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($recent_items->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Item</th>
                                                <th>Type</th>
                                                <th>Category</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($item = $recent_items->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                                    <?php if ($item['description']): ?>
                                                        <br><small class="text-muted"><?php echo substr(htmlspecialchars($item['description']), 0, 50); ?>...</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $item['item_type'] == 'lost' ? 'bg-danger' : 'bg-success'; ?>">
                                                        <?php echo ucfirst($item['item_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($item['date_lost_found'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        switch($item['status']) {
                                                            case 'approved': echo 'success'; break;
                                                            case 'pending': echo 'warning'; break;
                                                            case 'rejected': echo 'danger'; break;
                                                            case 'claimed': echo 'info'; break;
                                                            case 'returned': echo 'secondary'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst($item['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-inbox display-1 text-muted"></i>
                                    <h5 class="text-muted mt-3">No reports yet</h5>
                                    <p class="text-muted">Start by reporting a lost or found item.</p>
                                    <div class="mt-3">
                                        <a href="report_lost.php" class="btn btn-primary me-2">
                                            <i class="bi bi-plus-circle me-1"></i>Report Lost Item
                                        </a>
                                        <a href="report_found.php" class="btn btn-outline-primary">
                                            <i class="bi bi-plus-circle-dotted me-1"></i>Report Found Item
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
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
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>