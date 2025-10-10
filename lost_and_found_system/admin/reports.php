<?php
require_once '../config/session_check.php';
require_once '../config/dbconn.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isAdmin()) {
    header('Location: ../index.php');
    exit();
}

// Get date range filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month

// Validate dates
if (!strtotime($start_date)) $start_date = date('Y-m-01');
if (!strtotime($end_date)) $end_date = date('Y-m-t');

// Get overall statistics
$stats_query = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM items) as total_items,
        (SELECT COUNT(*) FROM claims) as total_claims,
        (SELECT COUNT(*) FROM items WHERE status = 'pending') as pending_items,
        (SELECT COUNT(*) FROM claims WHERE status = 'pending') as pending_claims,
        (SELECT COUNT(*) FROM items WHERE item_type = 'lost') as lost_items,
        (SELECT COUNT(*) FROM items WHERE item_type = 'found') as found_items,
        (SELECT COUNT(*) FROM items WHERE status = 'claimed') as claimed_items,
        (SELECT COUNT(*) FROM items WHERE status = 'returned') as returned_items
");
$stats = $stats_query->fetch_assoc();

// Get items by category
$categories_query = $conn->query("
    SELECT c.category_name, COUNT(i.item_id) as item_count
    FROM categories c 
    LEFT JOIN items i ON c.category_id = i.category_id 
    GROUP BY c.category_id, c.category_name 
    ORDER BY item_count DESC
");

// Get items by status
$status_query = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM items 
    GROUP BY status 
    ORDER BY count DESC
");

// Get claims by status
$claims_status_query = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM claims 
    GROUP BY status 
    ORDER BY count DESC
");

// Get recent activity (last 30 days)
$recent_activity_query = $conn->query("
    (SELECT 'item' as type, title, created_at as date, user_id 
     FROM items 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     ORDER BY created_at DESC 
     LIMIT 10)
    UNION ALL
    (SELECT 'claim' as type, 'Claim submitted' as title, claimed_at as date, claimant_id as user_id 
     FROM claims 
     WHERE claimed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     ORDER BY claimed_at DESC 
     LIMIT 10)
    ORDER BY date DESC 
    LIMIT 15
");

// Get monthly trends for the current year
$monthly_trends_query = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as items_count,
        SUM(CASE WHEN item_type = 'lost' THEN 1 ELSE 0 END) as lost_count,
        SUM(CASE WHEN item_type = 'found' THEN 1 ELSE 0 END) as found_count
    FROM items 
    WHERE YEAR(created_at) = YEAR(CURDATE())
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");

// Get top categories with lost/found breakdown
$top_categories_query = $conn->query("
    SELECT 
        c.category_name,
        COUNT(i.item_id) as total_items,
        SUM(CASE WHEN i.item_type = 'lost' THEN 1 ELSE 0 END) as lost_count,
        SUM(CASE WHEN i.item_type = 'found' THEN 1 ELSE 0 END) as found_count
    FROM categories c 
    LEFT JOIN items i ON c.category_id = i.category_id 
    GROUP BY c.category_id, c.category_name 
    HAVING total_items > 0
    ORDER BY total_items DESC 
    LIMIT 10
");

// Get user activity stats
$user_activity_query = $conn->query("
    SELECT 
        u.user_id,
        u.first_name,
        u.last_name,
        u.email,
        COUNT(i.item_id) as items_posted,
        COUNT(cl.claim_id) as claims_made
    FROM users u 
    LEFT JOIN items i ON u.user_id = i.user_id 
    LEFT JOIN claims cl ON u.user_id = cl.claimant_id 
    GROUP BY u.user_id, u.first_name, u.last_name, u.email 
    ORDER BY items_posted DESC, claims_made DESC 
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | NCST Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* Badge Styles */
        .badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        
        /* Chart Containers */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .small-chart {
            height: 200px;
        }
        
        /* Filter Styles */
        .filter-card {
            background: #fff;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        /* Progress Bars */
        .progress {
            height: 8px;
            margin-top: 5px;
        }
        
        /* Activity Timeline */
        .activity-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        
        .activity-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #1976d2;
            border: 2px solid #fff;
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
            
            .chart-container {
                height: 250px;
            }
        }
        
        /* Export Button */
        .export-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        
        .export-btn:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-dark bg-primary navbar-expand-lg fixed-top">
        <div class="container-fluid">
            <button class="navbar-toggler me-2" type="button" id="mobileToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <button class="btn btn-outline-light d-none d-lg-inline-flex me-3" id="desktopToggle">
                <i class="bi bi-list"></i>
            </button>
            
            <a class="navbar-brand fw-bold d-flex align-items-center" href="admin_dashboard.php">
                <img src="../assets/images/ncstlogo.jpg" alt="NCST Logo" class="me-2">
                <span>NCST Lost & Found - Admin</span>
            </a>
            
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
            <a href="admin_dashboard.php" class="nav-link">
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
            <a href="reports.php" class="nav-link active">
                <i class="bi bi-graph-up"></i>
                <span>Reports</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2">Reports & Analytics</h2>
                            <p class="text-muted">Comprehensive insights and system statistics.</p>
                        </div>
                        <button class="btn export-btn" onclick="exportReports()">
                            <i class="bi bi-download me-2"></i>Export Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Date Filters -->
            <div class="card filter-card">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="startDate" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" id="endDate" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-primary w-100" onclick="applyDateFilter()">
                            <i class="bi bi-filter me-2"></i>Apply Filter
                        </button>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-outline-secondary w-100" onclick="resetDateFilter()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card text-bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Total Users</h6>
                                    <h2 class="text-white mb-0"><?php echo $stats['total_users']; ?></h2>
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
                                    <h2 class="text-white mb-0"><?php echo $stats['total_items']; ?></h2>
                                </div>
                                <i class="bi bi-box text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card text-bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Total Claims</h6>
                                    <h2 class="text-white mb-0"><?php echo $stats['total_claims']; ?></h2>
                                </div>
                                <i class="bi bi-clipboard-check text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card text-bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Pending Actions</h6>
                                    <h2 class="text-white mb-0"><?php echo $stats['pending_items'] + $stats['pending_claims']; ?></h2>
                                    <small class="text-white-50">
                                        <?php echo $stats['pending_items']; ?> items + <?php echo $stats['pending_claims']; ?> claims
                                    </small>
                                </div>
                                <i class="bi bi-clock text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row g-3 mb-4">
                <!-- Items by Category -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Items by Category</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items by Status -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Items by Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row g-3 mb-4">
                <!-- Monthly Trends -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Monthly Trends (<?php echo date('Y'); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="monthlyTrendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Claims Distribution -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Claims Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="claimsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Tables Row -->
            <div class="row g-3">
                <!-- Top Categories -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Top Categories</h5>
                            <span class="badge bg-primary">Top 10</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Total Items</th>
                                            <th>Lost</th>
                                            <th>Found</th>
                                            <th>Distribution</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($category = $top_categories_query->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                            <td><?php echo $category['total_items']; ?></td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo $category['lost_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $category['found_count']; ?></span>
                                            </td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-danger" style="width: <?php echo $category['total_items'] > 0 ? ($category['lost_count'] / $category['total_items'] * 100) : 0; ?>%"></div>
                                                    <div class="progress-bar bg-success" style="width: <?php echo $category['total_items'] > 0 ? ($category['found_count'] / $category['total_items'] * 100) : 0; ?>%"></div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Recent Activity (Last 30 Days)</h5>
                            <span class="badge bg-primary">Latest 15</span>
                        </div>
                        <div class="card-body">
                            <div class="activity-timeline">
                                <?php while($activity = $recent_activity_query->fetch_assoc()): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between">
                                        <strong>
                                            <?php echo $activity['type'] == 'item' ? 'Item Posted' : 'Claim Submitted'; ?>
                                        </strong>
                                        <small class="text-muted">
                                            <?php echo date('M j, g:i A', strtotime($activity['date'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></p>
                                    <small class="text-muted">User ID: <?php echo $activity['user_id']; ?></small>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Activity -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Top Active Users</h5>
                            <span class="badge bg-primary">Top 10</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Email</th>
                                            <th>Items Posted</th>
                                            <th>Claims Made</th>
                                            <th>Total Activity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($user = $user_activity_query->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $user['items_posted']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning"><?php echo $user['claims_made']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $user['items_posted'] + $user['claims_made']; ?></span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
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
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Items by Category Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            new Chart(categoryCtx, {
                type: 'bar',
                data: {
                    labels: [<?php 
                        $categories_query->data_seek(0);
                        while($cat = $categories_query->fetch_assoc()) {
                            echo "'" . addslashes($cat['category_name']) . "',";
                        }
                    ?>],
                    datasets: [{
                        label: 'Items',
                        data: [<?php 
                            $categories_query->data_seek(0);
                            while($cat = $categories_query->fetch_assoc()) {
                                echo $cat['item_count'] . ",";
                            }
                        ?>],
                        backgroundColor: 'rgba(54, 162, 235, 0.8)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Items by Status Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: [<?php 
                        $status_query->data_seek(0);
                        while($status = $status_query->fetch_assoc()) {
                            echo "'" . ucfirst($status['status']) . "',";
                        }
                    ?>],
                    datasets: [{
                        data: [<?php 
                            $status_query->data_seek(0);
                            while($status = $status_query->fetch_assoc()) {
                                echo $status['count'] . ",";
                            }
                        ?>],
                        backgroundColor: [
                            '#ffc107', // pending - yellow
                            '#198754', // approved - green
                            '#dc3545', // rejected - red
                            '#0dcaf0', // claimed - info
                            '#6c757d'  // returned - secondary
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Monthly Trends Chart
            const trendsCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
            new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: [<?php 
                        $monthly_trends_query->data_seek(0);
                        while($trend = $monthly_trends_query->fetch_assoc()) {
                            echo "'" . date('M', strtotime($trend['month'] . '-01')) . "',";
                        }
                    ?>],
                    datasets: [{
                        label: 'Lost Items',
                        data: [<?php 
                            $monthly_trends_query->data_seek(0);
                            while($trend = $monthly_trends_query->fetch_assoc()) {
                                echo $trend['lost_count'] . ",";
                            }
                        ?>],
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Found Items',
                        data: [<?php 
                            $monthly_trends_query->data_seek(0);
                            while($trend = $monthly_trends_query->fetch_assoc()) {
                                echo $trend['found_count'] . ",";
                            }
                        ?>],
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Claims Distribution Chart
            const claimsCtx = document.getElementById('claimsChart').getContext('2d');
            new Chart(claimsCtx, {
                type: 'pie',
                data: {
                    labels: [<?php 
                        $claims_status_query->data_seek(0);
                        while($claim = $claims_status_query->fetch_assoc()) {
                            echo "'" . ucfirst($claim['status']) . "',";
                        }
                    ?>],
                    datasets: [{
                        data: [<?php 
                            $claims_status_query->data_seek(0);
                            while($claim = $claims_status_query->fetch_assoc()) {
                                echo $claim['count'] . ",";
                            }
                        ?>],
                        backgroundColor: [
                            '#ffc107', // pending
                            '#198754', // approved
                            '#dc3545'  // rejected
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        });

        // Filter Functions
        function applyDateFilter() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (startDate && endDate) {
                window.location.href = `reports.php?start_date=${startDate}&end_date=${endDate}`;
            }
        }

        function resetDateFilter() {
            window.location.href = 'reports.php';
        }

        function exportReports() {
            Swal.fire({
                title: 'Export Report',
                text: 'This feature will generate a comprehensive PDF report of all analytics.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Generate PDF',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Simulate PDF generation
                    Swal.fire({
                        title: 'Report Generated!',
                        text: 'Your analytics report has been prepared for download.',
                        icon: 'success',
                        timer: 2000
                    });
                }
            });
        }

        // Sidebar functionality
        $(document).ready(function() {
            const sidebar = $('#sidebar');
            const mainContent = $('#mainContent');
            const desktopToggle = $('#desktopToggle');
            const mobileToggle = $('#mobileToggle');
            let isCollapsed = false;

            desktopToggle.on('click', function() {
                isCollapsed = !isCollapsed;
                sidebar.toggleClass('collapsed', isCollapsed);
                mainContent.toggleClass('expanded', isCollapsed);
                
                const icon = $(this).find('i');
                icon.toggleClass('bi-list', !isCollapsed);
                icon.toggleClass('bi-layout-sidebar', isCollapsed);
            });

            mobileToggle.on('click', function() {
                sidebar.toggleClass('mobile-open');
            });

            $(document).on('click', function(e) {
                if ($(window).width() <= 768) {
                    if (!sidebar.is(e.target) && sidebar.has(e.target).length === 0 && 
                        !mobileToggle.is(e.target) && mobileToggle.has(e.target).length === 0) {
                        sidebar.removeClass('mobile-open');
                    }
                }
            });

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