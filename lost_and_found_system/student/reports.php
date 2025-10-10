<?php
require_once '../config/session_check.php';
if (!isStudent()) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/dbconn.php';

// Get filter parameters with validation
$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
$status = 'approved'; // Only show approved items

// Build query with prepared statements for security
$query = "SELECT i.*, c.category_name, u.first_name, u.last_name 
          FROM items i 
          JOIN categories c ON i.category_id = c.category_id 
          JOIN users u ON i.user_id = u.user_id 
          WHERE i.status = ?";

$params = [$status];
$types = "s";

if (!empty($search)) {
    $query .= " AND (i.title LIKE ? OR i.description LIKE ? OR i.location LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if (!empty($category) && $category > 0) {
    $query .= " AND i.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

if (!empty($type) && in_array($type, ['lost', 'found'])) {
    $query .= " AND i.item_type = ?";
    $params[] = $type;
    $types .= "s";
}

$query .= " ORDER BY i.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// Get total counts for badges
$total_stmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE status = 'approved'");
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_items = $total_result->fetch_assoc()['count'];
$total_stmt->close();

$lost_stmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE status = 'approved' AND item_type = 'lost'");
$lost_stmt->execute();
$lost_result = $lost_stmt->get_result();
$lost_count = $lost_result->fetch_assoc()['count'];
$lost_stmt->close();

$found_stmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE status = 'approved' AND item_type = 'found'");
$found_stmt->execute();
$found_result = $found_stmt->get_result();
$found_count = $found_result->fetch_assoc()['count'];
$found_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Items | NCST Lost & Found</title>
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
        
        /* Filter Styles */
        .filter-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            background: #fff;
        }
        
        .stats-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        
        /* Item Cards */
        .item-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .item-card .card-body {
            padding: 1.5rem;
        }
        
        .item-type-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 0.75rem;
        }
        
        .item-meta {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .item-meta i {
            width: 16px;
        }
        
        .item-description {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
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
            
            .item-card .card-body {
                padding: 1rem;
            }
            
            .filter-card .row > div {
                margin-bottom: 0.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 0.75rem;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .empty-state {
                padding: 2rem 1rem;
            }
            
            .empty-state i {
                font-size: 3rem;
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
            <a href="reports.php" class="nav-link active">
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
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1"><i class="bi bi-search me-2"></i>Browse Items</h2>
                    <p class="text-muted mb-0">Search through lost and found items in the NCST community</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-primary stats-badge">Total: <?php echo $total_items; ?></span>
                    <span class="badge bg-danger stats-badge">Lost: <?php echo $lost_count; ?></span>
                    <span class="badge bg-success stats-badge">Found: <?php echo $found_count; ?></span>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="card filter-card mb-4">
                <div class="card-body">
                    <form id="filterForm" method="GET">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Search</label>
                                <input type="text" class="form-control" name="search" placeholder="Search by title, description, or location..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Category</label>
                                <select class="form-select" name="category">
                                    <option value="">All Categories</option>
                                    <?php 
                                    $categories->data_seek(0); // Reset pointer
                                    while($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?php echo $cat['category_id']; ?>" 
                                                <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Item Type</label>
                                <select class="form-select" name="type">
                                    <option value="">All Types</option>
                                    <option value="lost" <?php echo $type == 'lost' ? 'selected' : ''; ?>>Lost Items</option>
                                    <option value="found" <?php echo $type == 'found' ? 'selected' : ''; ?>>Found Items</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel me-1"></i>Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results -->
            <div class="row">
                <?php if ($items->num_rows > 0): ?>
                    <?php while($item = $items->fetch_assoc()): ?>
                    <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
                        <div class="card item-card">
                            <div class="card-body position-relative">
                                <!-- Item Type Badge -->
                                <span class="badge item-type-badge <?php echo $item['item_type'] == 'lost' ? 'bg-danger' : 'bg-success'; ?>">
                                    <?php echo ucfirst($item['item_type']); ?>
                                </span>
                                
                                <!-- Item Title -->
                                <h5 class="card-title mb-2"><?php echo htmlspecialchars($item['title']); ?></h5>
                                
                                <!-- Category -->
                                <span class="badge bg-light text-dark mb-3"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                
                                <!-- Description -->
                                <p class="card-text item-description text-muted mb-3">
                                    <?php echo htmlspecialchars($item['description']); ?>
                                </p>
                                
                                <!-- Metadata -->
                                <div class="item-meta mb-3">
                                    <div class="mb-1">
                                        <i class="bi bi-geo-alt"></i>
                                        <span><?php echo htmlspecialchars($item['location']); ?></span>
                                    </div>
                                    <div class="mb-1">
                                        <i class="bi bi-calendar"></i>
                                        <span><?php echo date('M j, Y', strtotime($item['date_lost_found'])); ?></span>
                                    </div>
                                    <div>
                                        <i class="bi bi-person"></i>
                                        <span>Reported by <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Additional Details -->
                                <?php if (!empty($item['color']) || !empty($item['brand'])): ?>
                                <div class="mb-3">
                                    <?php if (!empty($item['color'])): ?>
                                        <span class="badge bg-info me-1"><?php echo htmlspecialchars($item['color']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['brand'])): ?>
                                        <span class="badge bg-warning"><?php echo htmlspecialchars($item['brand']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Action Buttons -->
                                <div class="d-flex gap-2">
                                    <?php if ($item['item_type'] == 'found'): ?>
                                        <a href="claim.php?item_id=<?php echo $item['item_id']; ?>" class="btn btn-primary btn-sm flex-fill">
                                            <i class="bi bi-box-seam me-1"></i>Claim Item
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-outline-primary btn-sm flex-fill" disabled>
                                            <i class="bi bi-eye me-1"></i>View Details
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-secondary btn-sm" 
                                            data-bs-toggle="tooltip" 
                                            title="Reported <?php echo date('M j, Y', strtotime($item['created_at'])); ?>">
                                        <i class="bi bi-info-circle"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <h4 class="text-muted">No items found</h4>
                            <p class="text-muted mb-4">
                                <?php if (!empty($search) || !empty($category) || !empty($type)): ?>
                                    Try adjusting your search filters or 
                                <?php endif; ?>
                                There are currently no items matching your criteria.
                            </p>
                            <?php if (!empty($search) || !empty($category) || !empty($type)): ?>
                                <a href="reports.php" class="btn btn-primary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Clear Filters
                                </a>
                            <?php else: ?>
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="report_lost.php" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-1"></i>Report Lost Item
                                    </a>
                                    <a href="report_found.php" class="btn btn-success">
                                        <i class="bi bi-plus-circle-dotted me-1"></i>Report Found Item
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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

            // Auto-submit form when filters change (optional)
            $('select[name="category"], select[name="type"]').on('change', function() {
                $('#filterForm').submit();
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>