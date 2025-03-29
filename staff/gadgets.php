<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db/db.php';
require_once 'staff_class.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}

$staff = new Staff($conn);
$staff->checkStaffLogin();

// Handle filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'category' => $_GET['category'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Handle approved products - this will get only staff-approved products
$approvedProducts = $staff->getApprovedProducts($_SESSION['id'], $filters);
$pendingHandovers = $staff->getPendingHandovers($_SESSION['id']);
$responsibleDevices = $staff->getResponsibleDevices($_SESSION['id']); // New method needed
$categories = $staff->getProductCategories();
$csrfToken = $staff->generateCsrfToken();

// Process handover acceptance
if(isset($_POST['accept_handover'])) {
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // CSRF token validation failed
        $_SESSION['error'] = "Invalid security token. Please try again.";
    } else {
        $rentalId = $_POST['rental_id'];
        $assignmentId = $_POST['assignment_id'];
        $result = $staff->acceptHandover($assignmentId, $rentalId, $_SESSION['id']);
        
        if($result) {
            $_SESSION['success'] = "Handover accepted successfully!";
        } else {
            $_SESSION['error'] = "Failed to accept handover. Please try again.";
        }
    }
    
    // Redirect to refresh the page and prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once 'head.php' ?>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f8f9fa; }
        .card { box-shadow: 0 0.15rem 1.75rem 0 rgba(58,59,69,.15); }
        .device-image { width: 60px; height: 60px; object-fit: cover; }
        .badge { font-size: 0.85em; }
        
    </style>
    <!-- In your head.php file -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4">
    <!-- Display session messages if any -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Pending Handovers Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="fas fa-handshake"></i> Pending Handover Requests</h4>
                </div>
                <div class="card-body">
                    <?php 
                    $pendingHandovers = $staff->getPendingHandovers($_SESSION['id']);
                    if (!empty($pendingHandovers)): ?>
                        <div class="row">
                            <?php foreach ($pendingHandovers as $handover): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5><?= htmlspecialchars($handover['product_name']) ?></h5>
                                        <p class="mb-1">Owner: <?= htmlspecialchars($handover['owner_name']) ?></p>
                                        <form method="post" action="">
                                            <input type="hidden" name="assignment_id" value="<?= $handover['assignment_id'] ?>">
                                            <input type="hidden" name="rental_id" value="<?= $handover['rental_id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <button type="submit" name="accept_handover" class="btn btn-sm btn-primary">
                                                <i class="fas fa-check-circle"></i> Accept Handover
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-check-circle fa-2x"></i>
                            <p class="mt-2 mb-0">No pending handover requests</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-shield-alt"></i> Responsible Devices</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Device</th>
                                    <th>Owner</th>
                                    <th>Renter</th>
                                    <th>Status</th>
                                    <th>Rental Period</th>
                                    <th>Assigned At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($responsibleDevices)): ?>
                                    <?php foreach ($responsibleDevices as $device): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($device['image']): ?>
                                                <img src="../img/uploads/<?= htmlspecialchars($device['image']) ?>" 
                                                    class="device-image me-3" alt="Device Image">
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($device['product_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($device['brand']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($device['owner_name']) ?></td>
                                        <td><?= htmlspecialchars($device['renter_name']) ?></td>
                                        <td>
                                            <span class="badge <?= getStatusBadgeClass($device['status']) ?>">
                                                <?= htmlspecialchars($device['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($device['start_date'])): ?>
                                                <?= date('M d, Y', strtotime($device['start_date'])) ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?> - 
                                            <?php if (!empty($device['end_date'])): ?>
                                                <?= date('M d, Y', strtotime($device['end_date'])) ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y H:i', strtotime($device['assigned_at'])) ?></td>
                                        <td>
    <?php 
    $productId = isset($device['product_id']) ? $device['product_id'] : '';
    $rentalId = isset($device['rental_id']) ? $device['rental_id'] : '';
    
    $link = "pickup-confirmations.php?";
    if ($productId) {
        $link .= "product_id=" . $productId;
    }
    if ($rentalId) {
        $link .= ($productId ? "&" : "") . "rental_id=" . $rentalId;
    }
    ?>
    <a href="<?= $link ?>" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-eye"></i>
    </a>
</td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fas fa-box-open fa-2x mb-3"></i>
                                            <p class="mb-0">No devices under your responsibility</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    // Initialize Bootstrap components
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    const tooltipList = tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
</script>
</body>
</html>

<?php
// Add this function at the bottom of the file
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'available': return 'bg-success';
        case 'rented': return 'bg-primary';
        case 'handed_over_to_admin': return 'bg-warning text-dark';
        case 'picked_up': return 'bg-info';
        case 'returned': return 'bg-secondary';
        case 'overdue': return 'bg-danger';
        default: return 'bg-secondary';
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
</script>
</body>
</html>