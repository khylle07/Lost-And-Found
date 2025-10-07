<?php
require_once '../config/session_check.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isAdmin()) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/dbconn.php';

// Get dashboard statistics with error handling
$total_users = 0;
$total_items = 0;
$pending_items = 0;
$pending_claims = 0;

try {
    // Fix: Changed 'role' to 'user_role' to match your database
    $users_result = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_role = 'student'");
    if ($users_result) $total_users = $users_result->fetch_assoc()['count'];
    
    $items_result = $conn->query("SELECT COUNT(*) as count FROM items");
    if ($items_result) $total_items = $items_result->fetch_assoc()['count'];
    
    $pending_items_result = $conn->query("SELECT COUNT(*) as count FROM items WHERE status = 'pending'");
    if ($pending_items_result) $pending_items = $pending_items_result->fetch_assoc()['count'];
    
    $pending_claims_result = $conn->query("SELECT COUNT(*) as count FROM claims WHERE status = 'pending'");
    if ($pending_claims_result) $pending_claims = $pending_claims_result->fetch_assoc()['count'];

    // Get recent items for review
    $recent_items = $conn->query("
        SELECT i.*, c.category_name, u.first_name, u.last_name 
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.category_id 
        LEFT JOIN users u ON i.user_id = u.user_id 
        WHERE i.status = 'pending'
        ORDER BY i.created_at DESC 
        LIMIT 5
    ");

    // Get recent claims for review
    $recent_claims = $conn->query("
        SELECT cl.*, i.title, i.item_type, u.first_name, u.last_name 
        FROM claims cl 
        LEFT JOIN items i ON cl.item_id = i.item_id 
        LEFT JOIN users u ON cl.claimant_id = u.user_id 
        WHERE cl.status = 'pending'
        ORDER BY cl.claimed_at DESC 
        LIMIT 5
    ");
} catch (Exception $e) {
    // Handle database errors
    error_log("Dashboard error: " . $e->getMessage());
    $recent_items = false;
    $recent_claims = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | NCST Lost & Found</title>
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
        
        /* Quick Actions */
        .quick-action-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
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
            
            .action-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
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
        
        /* Badge Styles */
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
            <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
                <img src="../assets/images/ncstlogo.jpg" alt="NCST Logo" class="me-2">
                <span>NCST Lost & Found - Admin</span>
            </a>
            
            <!-- User menu -->
            <div class="dropdown ms-auto">
                <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                    <img src="https://api.dicebear.com/7.x/identicon/svg?seed=<?php echo $_SESSION['email']; ?>" 
                         alt="Profile" width="32" height="32" class="rounded-circle me-2">
                    <span class="d-none d-sm-inline">Admin</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#"><i class="bi bi-person-circle me-2"></i>Admin Profile</a></li>
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
            <a href="items.php" class="nav-link">
                <i class="bi bi-box"></i>
                <span>Manage Items</span>
            </a>
            <a href="claims.php" class="nav-link">
                <i class="bi bi-clipboard-check"></i>
                <span>Manage Claims</span>
            </a>
            <a href="users.php" class="nav-link">
                <i class="bi bi-people"></i>
                <span>Manage Users</span>
            </a>
            <a href="categories.php" class="nav-link">
                <i class="bi bi-tags"></i>
                <span>Categories</span>
            </a>
            <a href="reports.php" class="nav-link">
                <i class="bi bi-graph-up"></i>
                <span>Reports</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <!-- Welcome Message -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="mb-2">Welcome back, Admin! 👋</h2>
                    <p class="text-muted">Here's an overview of the Lost & Found system.</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-5">
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card text-bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Total Students</h6>
                                    <h2 class="text-white mb-0"><?php echo $total_users; ?></h2>
                                </div>
                                <i class="bi bi-people text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card text-bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Total Items</h6>
                                    <h2 class="text-white mb-0"><?php echo $total_items; ?></h2>
                                </div>
                                <i class="bi bi-box text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card text-bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Pending Items</h6>
                                    <h2 class="text-white mb-0"><?php echo $pending_items; ?></h2>
                                </div>
                                <i class="bi bi-clock text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card text-bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Pending Claims</h6>
                                    <h2 class="text-white mb-0"><?php echo $pending_claims; ?></h2>
                                </div>
                                <i class="bi bi-clipboard-check text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-5">
                <div class="col-12">
                    <h4 class="mb-3">Quick Actions</h4>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <a href="items.php" class="card quick-action-card text-decoration-none">
                                <div class="card-body text-center p-3">
                                    <div class="action-icon bg-primary text-white mx-auto mb-2">
                                        <i class="bi bi-box"></i>
                                    </div>
                                    <h6 class="card-title mb-1">Review Items</h6>
                                    <small class="text-muted">Approve/reject submissions</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="claims.php" class="card quick-action-card text-decoration-none">
                                <div class="card-body text-center p-3">
                                    <div class="action-icon bg-success text-white mx-auto mb-2">
                                        <i class="bi bi-clipboard-check"></i>
                                    </div>
                                    <h6 class="card-title mb-1">Process Claims</h6>
                                    <small class="text-muted">Review item claims</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="users.php" class="card quick-action-card text-decoration-none">
                                <div class="card-body text-center p-3">
                                    <div class="action-icon bg-warning text-white mx-auto mb-2">
                                        <i class="bi bi-people"></i>
                                    </div>
                                    <h6 class="card-title mb-1">Manage Users</h6>
                                    <small class="text-muted">View all users</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="reports.php" class="card quick-action-card text-decoration-none">
                                <div class="card-body text-center p-3">
                                    <div class="action-icon bg-info text-white mx-auto mb-2">
                                        <i class="bi bi-graph-up"></i>
                                    </div>
                                    <h6 class="card-title mb-1">View Reports</h6>
                                    <small class="text-muted">System analytics</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <!-- Pending Items -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Items Pending Review</h5>
                            <span class="badge bg-dark"><?php echo $pending_items; ?></span>
                        </div>
                        <div class="card-body">
                            <?php if ($recent_items && $recent_items->num_rows > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php while($item = $recent_items->fetch_assoc()): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($item['title']); ?></h6>
                                            <span class="badge bg-<?php echo $item['item_type'] == 'lost' ? 'danger' : 'success'; ?>">
                                                <?php echo ucfirst($item['item_type']); ?>
                                            </span>
                                        </div>
                                        <p class="text-muted small mb-2">
                                            <?php echo substr(htmlspecialchars($item['description']), 0, 100); ?>...
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                By <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?> • 
                                                <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                                            </small>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-success btn-sm">Approve</button>
                                                <button class="btn btn-outline-danger btn-sm">Reject</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="items.php" class="btn btn-outline-primary btn-sm">View All Items</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-check-circle display-4 text-muted"></i>
                                    <p class="text-muted mt-2 mb-0">No items pending review</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Pending Claims -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Claims Pending Review</h5>
                            <span class="badge bg-dark"><?php echo $pending_claims; ?></span>
                        </div>
                        <div class="card-body">
                            <?php if ($recent_claims && $recent_claims->num_rows > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php while($claim = $recent_claims->fetch_assoc()): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($claim['title']); ?></h6>
                                            <span class="badge bg-warning">Pending</span>
                                        </div>
                                        <p class="text-muted small mb-2">
                                            Claim by <?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($claim['claimed_at'])); ?>
                                            </small>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-success btn-sm">Approve</button>
                                                <button class="btn btn-outline-danger btn-sm">Reject</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="claims.php" class="btn btn-outline-primary btn-sm">View All Claims</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-check-circle display-4 text-muted"></i>
                                    <p class="text-muted mt-2 mb-0">No claims pending review</p>
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