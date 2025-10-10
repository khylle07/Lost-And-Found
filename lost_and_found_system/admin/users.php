<?php
require_once '../config/session_check.php';
require_once '../config/dbconn.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isAdmin()) {
    header('Location: ../index.php');
    exit();
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = $_POST['user_id'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'delete') {
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $message = "User deleted successfully";
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = $message;
                }
                $stmt->close();
            } elseif ($action === 'make_admin') {
                $stmt = $conn->prepare("UPDATE users SET user_role = 'admin' WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $message = "User promoted to admin successfully";
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = $message;
                }
                $stmt->close();
            } elseif ($action === 'make_student') {
                $stmt = $conn->prepare("UPDATE users SET user_role = 'student' WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $message = "User changed to student role successfully";
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = $message;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error processing request: " . $e->getMessage();
        }
        
        header("Location: users.php");
        exit();
    }
}

// Get all users with filters
$role_filter = $_GET['role'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "SELECT user_id, first_name, last_name, email, user_role, student_number, course, contact_number, created_at 
          FROM users WHERE 1=1";

$params = [];
$types = "";

if ($role_filter !== 'all') {
    $query .= " AND user_role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR student_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();

// Get counts for filters
$counts_query = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN user_role = 'student' THEN 1 ELSE 0 END) as students,
        SUM(CASE WHEN user_role = 'admin' THEN 1 ELSE 0 END) as admins
    FROM users
");
$counts = $counts_query->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | NCST Lost & Found</title>
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
        
        .user-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .user-card:hover {
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
        
        /* Avatar Styles */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e3f2fd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #1976d2;
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
            
            .btn-group .btn {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
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
            <a href="users.php" class="nav-link active">
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
                    <h2 class="mb-2">Manage Users</h2>
                    <p class="text-muted">View and manage all system users.</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-4 col-md-6">
                    <div class="card text-bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Total Users</h6>
                                    <h2 class="text-white mb-0"><?php echo $counts['total']; ?></h2>
                                </div>
                                <i class="bi bi-people text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="card text-bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Students</h6>
                                    <h2 class="text-white mb-0"><?php echo $counts['students']; ?></h2>
                                </div>
                                <i class="bi bi-person text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="card text-bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Admins</h6>
                                    <h2 class="text-white mb-0"><?php echo $counts['admins']; ?></h2>
                                </div>
                                <i class="bi bi-shield-check text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card filter-card">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <select class="form-select" id="roleFilter" onchange="updateFilters()">
                            <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Search Users</label>
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search by name, email, or student number..." id="searchInput" value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-primary" type="button" onclick="updateFilters()">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">All Users</h5>
                    <span class="badge bg-primary"><?php echo $users_result->num_rows; ?> users</span>
                </div>
                <div class="card-body">
                    <?php if ($users_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Student Number</th>
                                        <th>Course</th>
                                        <th>Role</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($user = $users_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3">
                                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">ID: <?php echo $user['user_id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['student_number'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($user['course'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['user_role'] == 'admin' ? 'warning' : 'success'; ?>">
                                                <?php echo ucfirst($user['user_role'] ?? 'student'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewUserModal" 
                                                        onclick="viewUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                
                                                <?php if (($user['user_role'] ?? 'student') == 'student'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="make_admin">
                                                    <button type="submit" class="btn btn-outline-warning" onclick="return confirm('Make this user an admin?')">
                                                        <i class="bi bi-shield-plus"></i>
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="make_student">
                                                    <button type="submit" class="btn btn-outline-info" onclick="return confirm('Change this user to student role?')">
                                                        <i class="bi bi-person"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Delete this user? This action cannot be undone.')">
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
                            <i class="bi bi-people display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No users found</h4>
                            <p class="text-muted">Try adjusting your filters or search terms.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userName">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetails">
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
            const role = document.getElementById('roleFilter').value;
            const search = document.getElementById('searchInput').value;
            
            const params = new URLSearchParams();
            if (role !== 'all') params.append('role', role);
            if (search) params.append('search', search);
            
            window.location.href = 'users.php?' + params.toString();
        }

        function viewUser(user) {
            document.getElementById('userName').textContent = user.first_name + ' ' + user.last_name;
            
            const details = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>User ID:</strong>
                        <p>${user.user_id}</p>
                    </div>
                    <div class="col-md-6">
                        <strong>Email:</strong>
                        <p>${user.email}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <strong>Student Number:</strong>
                        <p>${user.student_number || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <strong>Course:</strong>
                        <p>${user.course || 'N/A'}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <strong>Contact Number:</strong>
                        <p>${user.contact_number || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <strong>Role:</strong>
                        <span class="badge bg-${(user.user_role || 'student') === 'admin' ? 'warning' : 'success'}">
                            ${(user.user_role || 'student').charAt(0).toUpperCase() + (user.user_role || 'student').slice(1)}
                        </span>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <strong>Member Since:</strong>
                        <p>${new Date(user.created_at).toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric' 
                        })}</p>
                    </div>
                </div>
            `;
            
            document.getElementById('userDetails').innerHTML = details;
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

            // Show success/error messages
            <?php if (isset($_SESSION['success'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: '<?php echo $_SESSION['success']; ?>',
                    timer: 3000
                });
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?php echo $_SESSION['error']; ?>'
                });
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>