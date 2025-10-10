<?php
require_once '../config/session_check.php';
require_once '../config/dbconn.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isAdmin()) {
    header('Location: ../index.php');
    exit();
}

// Handle claim actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['claim_id'])) {
        $claim_id = $_POST['claim_id'];
        $action = $_POST['action'];
        $admin_notes = $_POST['admin_notes'] ?? '';
        
        try {
            if ($action === 'approve') {
                // Update claim status
                $stmt = $conn->prepare("UPDATE claims SET status = 'approved', admin_notes = ?, processed_at = NOW() WHERE claim_id = ?");
                $stmt->bind_param("si", $admin_notes, $claim_id);
                
                if ($stmt->execute()) {
                    // Get the item_id from the claim
                    $item_stmt = $conn->prepare("SELECT item_id FROM claims WHERE claim_id = ?");
                    $item_stmt->bind_param("i", $claim_id);
                    $item_stmt->execute();
                    $item_result = $item_stmt->get_result();
                    $claim_data = $item_result->fetch_assoc();
                    $item_stmt->close();
                    
                    // Update item status to claimed
                    $update_item = $conn->prepare("UPDATE items SET status = 'claimed' WHERE item_id = ?");
                    $update_item->bind_param("i", $claim_data['item_id']);
                    $update_item->execute();
                    $update_item->close();
                    
                    $_SESSION['success'] = "Claim approved successfully";
                }
                $stmt->close();
            } elseif ($action === 'reject') {
                $stmt = $conn->prepare("UPDATE claims SET status = 'rejected', admin_notes = ?, processed_at = NOW() WHERE claim_id = ?");
                $stmt->bind_param("si", $admin_notes, $claim_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Claim rejected successfully";
                }
                $stmt->close();
            } elseif ($action === 'delete') {
                $stmt = $conn->prepare("DELETE FROM claims WHERE claim_id = ?");
                $stmt->bind_param("i", $claim_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Claim deleted successfully";
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error processing request: " . $e->getMessage();
        }
        
        header("Location: claims.php");
        exit();
    }
}

// Get all claims with filters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "SELECT cl.*, i.title, i.description, i.item_type, i.image_path, i.status as item_status,
                 c.category_name, 
                 claimant.first_name as claimant_first, claimant.last_name as claimant_last, claimant.email as claimant_email, claimant.student_number as claimant_student_no,
                 owner.first_name as owner_first, owner.last_name as owner_last, owner.email as owner_email
          FROM claims cl 
          LEFT JOIN items i ON cl.item_id = i.item_id 
          LEFT JOIN categories c ON i.category_id = c.category_id 
          LEFT JOIN users claimant ON cl.claimant_id = claimant.user_id 
          LEFT JOIN users owner ON i.user_id = owner.user_id 
          WHERE 1=1";

$params = [];
$types = "";

if ($status_filter !== 'all') {
    $query .= " AND cl.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (i.title LIKE ? OR claimant.first_name LIKE ? OR claimant.last_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$query .= " ORDER BY cl.claimed_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$claims_result = $stmt->get_result();

// Get counts for filters
$counts_query = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM claims
");
$counts = $counts_query->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Claims | NCST Lost & Found</title>
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
        
        .claim-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .claim-card:hover {
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
        
        /* Proof of Ownership Styles */
        .proof-content {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            white-space: pre-wrap;
        }
        
        /* Item Image Styles */
        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.5rem;
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
            <a href="claims.php" class="nav-link active">
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
                    <h2 class="mb-2">Manage Claims</h2>
                    <p class="text-muted">Review and process item claims.</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card text-bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Total Claims</h6>
                                    <h2 class="text-white mb-0"><?php echo $counts['total']; ?></h2>
                                </div>
                                <i class="bi bi-clipboard-check text-white"></i>
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
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="statusFilter" onchange="updateFilters()">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Search Claims</label>
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search by item title or claimant name..." id="searchInput" value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-primary" type="button" onclick="updateFilters()">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Claims Table -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">All Claims</h5>
                    <span class="badge bg-primary"><?php echo $claims_result->num_rows; ?> claims</span>
                </div>
                <div class="card-body">
                    <?php if ($claims_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Claimant</th>
                                        <th>Item Owner</th>
                                        <th>Category</th>
                                        <th>Claimed</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($claim = $claims_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($claim['image_path']): ?>
                                                    <img src="../<?php echo htmlspecialchars($claim['image_path']); ?>" 
                                                         alt="Item image" class="item-image me-3">
                                                <?php else: ?>
                                                    <div class="item-image bg-light d-flex align-items-center justify-content-center me-3">
                                                        <i class="bi bi-image text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($claim['title']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo ucfirst($claim['item_type']); ?> • 
                                                        <?php echo htmlspecialchars($claim['category_name']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($claim['claimant_first'] . ' ' . $claim['claimant_last']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($claim['claimant_student_no'] ?? 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($claim['owner_first'] . ' ' . $claim['owner_last']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($claim['owner_email']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($claim['category_name']); ?></td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($claim['claimed_at'])); ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('g:i A', strtotime($claim['claimed_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $claim['status'] == 'pending' ? 'warning' : 
                                                     ($claim['status'] == 'approved' ? 'success' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($claim['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewClaimModal" 
                                                        onclick="viewClaim(<?php echo htmlspecialchars(json_encode($claim)); ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                
                                                <?php if ($claim['status'] == 'pending'): ?>
                                                <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#approveClaimModal" 
                                                        onclick="setClaimAction(<?php echo $claim['claim_id']; ?>, 'approve')">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectClaimModal" 
                                                        onclick="setClaimAction(<?php echo $claim['claim_id']; ?>, 'reject')">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="claim_id" value="<?php echo $claim['claim_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-outline-dark" onclick="return confirm('Delete this claim? This action cannot be undone.')">
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
                            <i class="bi bi-clipboard-check display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No claims found</h4>
                            <p class="text-muted">Try adjusting your filters or search terms.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- View Claim Modal -->
    <div class="modal fade" id="viewClaimModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Claim Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="claimDetails">
                    <!-- Details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Claim Modal -->
    <div class="modal fade" id="approveClaimModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Claim</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="claim_id" id="approveClaimId">
                        <input type="hidden" name="action" value="approve">
                        
                        <div class="mb-3">
                            <label for="approveNotes" class="form-label">Admin Notes (Optional)</label>
                            <textarea class="form-control" id="approveNotes" name="admin_notes" rows="3" placeholder="Add any notes about this approval..."></textarea>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Approving this claim will mark the item as "claimed" and notify both parties.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve Claim</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Claim Modal -->
    <div class="modal fade" id="rejectClaimModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Claim</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="claim_id" id="rejectClaimId">
                        <input type="hidden" name="action" value="reject">
                        
                        <div class="mb-3">
                            <label for="rejectNotes" class="form-label">Reason for Rejection</label>
                            <textarea class="form-control" id="rejectNotes" name="admin_notes" rows="3" placeholder="Please provide the reason for rejecting this claim..." required></textarea>
                        </div>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            The claimant will be notified about the rejection reason.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Claim</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentClaimId = null;
        let currentAction = null;

        function updateFilters() {
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchInput').value;
            
            const params = new URLSearchParams();
            if (status !== 'all') params.append('status', status);
            if (search) params.append('search', search);
            
            window.location.href = 'claims.php?' + params.toString();
        }

        function setClaimAction(claimId, action) {
            if (action === 'approve') {
                document.getElementById('approveClaimId').value = claimId;
            } else if (action === 'reject') {
                document.getElementById('rejectClaimId').value = claimId;
            }
        }

        function viewClaim(claim) {
            const details = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Item Information:</strong>
                        <div class="card mt-2">
                            <div class="card-body">
                                <h6>${claim.title}</h6>
                                <p class="mb-1"><strong>Type:</strong> ${claim.item_type.charAt(0).toUpperCase() + claim.item_type.slice(1)}</p>
                                <p class="mb-1"><strong>Category:</strong> ${claim.category_name}</p>
                                <p class="mb-1"><strong>Item Status:</strong> <span class="badge bg-secondary">${claim.item_status}</span></p>
                                ${claim.description ? `<p class="mb-0"><strong>Description:</strong> ${claim.description}</p>` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <strong>Claim Information:</strong>
                        <div class="card mt-2">
                            <div class="card-body">
                                <p class="mb-1"><strong>Status:</strong> <span class="badge bg-${claim.status === 'pending' ? 'warning' : claim.status === 'approved' ? 'success' : 'danger'}">${claim.status.charAt(0).toUpperCase() + claim.status.slice(1)}</span></p>
                                <p class="mb-1"><strong>Claimed:</strong> ${new Date(claim.claimed_at).toLocaleString()}</p>
                                ${claim.processed_at ? `<p class="mb-1"><strong>Processed:</strong> ${new Date(claim.processed_at).toLocaleString()}</p>` : ''}
                                ${claim.admin_notes ? `<p class="mb-0"><strong>Admin Notes:</strong> ${claim.admin_notes}</p>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <strong>Claimant:</strong>
                        <div class="card mt-2">
                            <div class="card-body">
                                <p class="mb-1"><strong>Name:</strong> ${claim.claimant_first} ${claim.claimant_last}</p>
                                <p class="mb-1"><strong>Email:</strong> ${claim.claimant_email}</p>
                                ${claim.claimant_student_no ? `<p class="mb-0"><strong>Student No:</strong> ${claim.claimant_student_no}</p>` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <strong>Item Owner:</strong>
                        <div class="card mt-2">
                            <div class="card-body">
                                <p class="mb-1"><strong>Name:</strong> ${claim.owner_first} ${claim.owner_last}</p>
                                <p class="mb-0"><strong>Email:</strong> ${claim.owner_email}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <strong>Proof of Ownership:</strong>
                        <div class="proof-content mt-2">
                            ${claim.proof_of_ownership || 'No proof provided'}
                        </div>
                    </div>
                </div>
                
                ${claim.image_path ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <strong>Item Image:</strong>
                        <div class="mt-2">
                            <img src="../${claim.image_path}" alt="Item image" class="img-fluid rounded" style="max-height: 200px;">
                        </div>
                    </div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('claimDetails').innerHTML = details;
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