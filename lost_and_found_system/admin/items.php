<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/dbconn.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isAdmin()) {
    header('Location: ../index.php');
    exit();
}

// Handle item actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['item_id'])) {
        $item_id = $_POST['item_id'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE items SET status = 'approved' WHERE item_id = ?");
                $stmt->bind_param("i", $item_id);
                $message = "Item approved successfully";
            } elseif ($action === 'reject') {
                $stmt = $conn->prepare("UPDATE items SET status = 'rejected' WHERE item_id = ?");
                $stmt->bind_param("i", $item_id);
                $message = "Item rejected successfully";
            } elseif ($action === 'delete') {
                $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ?");
                $stmt->bind_param("i", $item_id);
                $message = "Item deleted successfully";
            }
            
            if ($stmt->execute()) {
                $_SESSION['success'] = $message;
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error processing request: " . $e->getMessage();
        }
        
        header("Location: items.php");
        exit();
    }
}

// Get all items with filters
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "SELECT i.*, c.category_name, u.first_name, u.last_name, u.email 
          FROM items i 
          LEFT JOIN categories c ON i.category_id = c.category_id 
          LEFT JOIN users u ON i.user_id = u.user_id 
          WHERE 1=1";

$params = [];
$types = "";

if ($status_filter !== 'all') {
    $query .= " AND i.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($type_filter !== 'all') {
    $query .= " AND i.item_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (i.title LIKE ? OR i.description LIKE ? OR i.location_found LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$query .= " ORDER BY i.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items_result = $stmt->get_result();

// Get counts for filters
$counts_query = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM items
");
$counts = $counts_query->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items | NCST Lost & Found</title>
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
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .item-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* Badge Styles */
        .badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        
        /* Filter Styles */
        .filter-card {
            background: #fff;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
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
            
            <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
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
            <a href="items.php" class="nav-link active">
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
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="mb-2">Manage Items</h2>
                    <p class="text-muted">Review and manage all lost and found items.</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card text-bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Total Items</h6>
                                    <h2 class="text-white mb-0"><?php echo $counts['total']; ?></h2>
                                </div>
                                <i class="bi bi-box text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card text-bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Pending</h6>
                                    <h2 class="text-white mb-0"><?php echo $counts['pending']; ?></h2>
                                </div>
                                <i class="bi bi-clock text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card text-bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Approved</h6>
                                    <h2 class="text-white mb-0"><?php echo $counts['approved']; ?></h2>
                                </div>
                                <i class="bi bi-check-circle text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card text-bg-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Rejected</h6>
                                    <h2 class="text-white mb-0"><?php echo $counts['rejected']; ?></h2>
                                </div>
                                <i class="bi bi-x-circle text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card filter-card">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="statusFilter" onchange="updateFilters()">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Item Type</label>
                        <select class="form-select" id="typeFilter" onchange="updateFilters()">
                            <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="lost" <?php echo $type_filter === 'lost' ? 'selected' : ''; ?>>Lost</option>
                            <option value="found" <?php echo $type_filter === 'found' ? 'selected' : ''; ?>>Found</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search items..." id="searchInput" value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-primary" type="button" onclick="updateFilters()">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">All Items</h5>
                    <span class="badge bg-primary"><?php echo $items_result->num_rows; ?> items</span>
                </div>
                <div class="card-body">
                    <?php if ($items_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Submitted By</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($item = $items_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo substr(htmlspecialchars($item['description']), 0, 50); ?>...</small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $item['item_type'] == 'lost' ? 'danger' : 'success'; ?>">
                                                <?php echo ucfirst($item['item_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($item['email']); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($item['created_at'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $item['status'] == 'pending' ? 'warning' : 
                                                     ($item['status'] == 'approved' ? 'success' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewItemModal" 
                                                        onclick="viewItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if ($item['status'] == 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-outline-success" onclick="return confirm('Approve this item?')">
                                                        <i class="bi bi-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Reject this item?')">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-outline-dark" onclick="return confirm('Delete this item?')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No items found</h4>
                            <p class="text-muted">Try adjusting your filters or search terms.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- View Item Modal -->
    <div class="modal fade" id="viewItemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="itemTitle">Item Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="itemDetails">
                    <!-- Details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function updateFilters() {
            const status = document.getElementById('statusFilter').value;
            const type = document.getElementById('typeFilter').value;
            const search = document.getElementById('searchInput').value;
            
            const params = new URLSearchParams();
            if (status !== 'all') params.append('status', status);
            if (type !== 'all') params.append('type', type);
            if (search) params.append('search', search);
            
            window.location.href = 'items.php?' + params.toString();
        }

        function viewItem(item) {
            document.getElementById('itemTitle').textContent = item.title;
            
            const details = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Description:</strong>
                        <p>${item.description}</p>
                    </div>
                    <div class="col-md-6">
                        <strong>Location:</strong>
                        <p>${item.location_found || 'Not specified'}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <strong>Type:</strong>
                        <span class="badge bg-${item.item_type === 'lost' ? 'danger' : 'success'}">
                            ${item.item_type.charAt(0).toUpperCase() + item.item_type.slice(1)}
                        </span>
                    </div>
                    <div class="col-md-4">
                        <strong>Category:</strong>
                        <p>${item.category_name}</p>
                    </div>
                    <div class="col-md-4">
                        <strong>Status:</strong>
                        <span class="badge bg-${item.status === 'pending' ? 'warning' : item.status === 'approved' ? 'success' : 'danger'}">
                            ${item.status.charAt(0).toUpperCase() + item.status.slice(1)}
                        </span>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <strong>Submitted By:</strong>
                        <p>${item.first_name} ${item.last_name}<br>
                        <small class="text-muted">${item.email}</small></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Date Submitted:</strong>
                        <p>${new Date(item.created_at).toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric' 
                        })}</p>
                    </div>
                </div>
            `;
            
            document.getElementById('itemDetails').innerHTML = details;
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