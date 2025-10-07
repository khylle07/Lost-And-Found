<?php
// Include the header configuration
require_once 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'NCST Lost & Found'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        .navbar-brand img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }
        
        .navbar-nav .nav-link {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }
        
        .navbar-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .navbar-nav .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
        }
        
        .dropdown-item {
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        
        .notification-badge {
            font-size: 0.7rem;
            padding: 0.2em 0.5em;
        }
        
        @media (max-width: 991.98px) {
            .navbar-collapse {
                margin-top: 1rem;
            }
            
            .navbar-nav .nav-link {
                margin: 0.125rem 0;
            }
            
            .dropdown-menu {
                border: 1px solid rgba(0, 0, 0, 0.1);
                margin: 0.5rem 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow-sm" style="z-index: 1030;">
        <div class="container-fluid">
            <!-- Mobile toggle button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Brand/Logo -->
            <a class="navbar-brand fw-bold d-flex align-items-center me-4" href="<?php echo $dashboard_url; ?>">
                <img src="../assets/images/ncstlogo.jpg" alt="NCST Logo" class="me-2">
                <span class="d-none d-sm-inline">NCST Lost & Found</span>
                <?php if ($user_role === 'admin'): ?>
                    <span class="badge bg-warning ms-2 d-none d-md-inline">Admin</span>
                <?php endif; ?>
            </a>

            <!-- Main Navigation -->
            <div class="collapse navbar-collapse" id="mainNavbar">
                <?php if ($is_logged_in): ?>
                    <!-- Student Navigation -->
                    <?php if ($user_role === 'student'): ?>
                        <ul class="navbar-nav me-auto">
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                                   href="dashboard.php">
                                    <i class="bi bi-speedometer2 me-1"></i>Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'report_lost.php' ? 'active' : ''; ?>" 
                                   href="report_lost.php">
                                    <i class="bi bi-plus-circle me-1"></i>Report Lost
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'report_found.php' ? 'active' : ''; ?>" 
                                   href="report_found.php">
                                    <i class="bi bi-plus-circle-dotted me-1"></i>Report Found
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" 
                                   href="reports.php">
                                    <i class="bi bi-search me-1"></i>Browse Items
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'claim.php' ? 'active' : ''; ?>" 
                                   href="claim.php">
                                    <i class="bi bi-box-seam me-1"></i>Claim Item
                                </a>
                            </li>
                        </ul>
                    
                    <!-- Admin Navigation -->
                    <?php elseif ($user_role === 'admin'): ?>
                        <ul class="navbar-nav me-auto">
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                                   href="dashboard.php">
                                    <i class="bi bi-speedometer2 me-1"></i>Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'items.php' ? 'active' : ''; ?>" 
                                   href="items.php">
                                    <i class="bi bi-box me-1"></i>Manage Items
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'claims.php' ? 'active' : ''; ?>" 
                                   href="claims.php">
                                    <i class="bi bi-clipboard-check me-1"></i>Manage Claims
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" 
                                   href="users.php">
                                    <i class="bi bi-people me-1"></i>Manage Users
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" 
                                   href="categories.php">
                                    <i class="bi bi-tags me-1"></i>Categories
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" 
                                   href="reports.php">
                                    <i class="bi bi-graph-up me-1"></i>Reports
                                </a>
                            </li>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Right-side Navigation -->
                <ul class="navbar-nav ms-auto">
                    <?php if ($is_logged_in): ?>
                        <!-- Notifications -->
                        <li class="nav-item dropdown">
                            <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-bell fs-5"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                                    3
                                    <span class="visually-hidden">unread notifications</span>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <li><a class="dropdown-item" href="#"><i class="bi bi-exclamation-circle text-warning me-2"></i>New item reported</a></li>
                                <li><a class="dropdown-item" href="#"><i class="bi bi-check-circle text-success me-2"></i>Claim approved</a></li>
                                <li><a class="dropdown-item" href="#"><i class="bi bi-info-circle text-primary me-2"></i>System update</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
                            </ul>
                        </li>

                        <!-- User Menu -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <img src="<?php echo $profile_pic_url; ?>" 
                                     alt="Profile" 
                                     width="32" 
                                     height="32" 
                                     class="rounded-circle me-2 border border-2 border-white">
                                <span class="d-none d-md-inline">
                                    <?php echo htmlspecialchars($user_name); ?>
                                    <?php if ($user_role === 'admin'): ?>
                                        <small class="text-warning">(Admin)</small>
                                    <?php endif; ?>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="<?php echo $user_role === 'admin' ? 'profile.php' : 'student/account.php'; ?>">
                                        <i class="bi bi-person-circle me-2"></i>My Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo $user_role === 'admin' ? 'settings.php' : 'student/settings.php'; ?>">
                                        <i class="bi bi-gear me-2"></i>Settings
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="../auth/logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Login/Register buttons for guests -->
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../auth/register.php">
                                <i class="bi bi-person-plus me-1"></i>Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Add spacing for fixed navbar -->
    <div style="height: 76px;"></div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Handle notifications
        function markNotificationAsRead(notificationId) {
            // AJAX call to mark notification as read
            console.log('Marking notification as read:', notificationId);
        }

        // Handle logout confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const logoutLinks = document.querySelectorAll('a[href*="logout.php"]');
            logoutLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Logout?',
                        text: 'Are you sure you want to logout?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, logout!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = this.href;
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>