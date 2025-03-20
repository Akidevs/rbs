<?php
require_once 'includes/auth.php';
require_once '../db/db.php';
require_once 'admin_class.php';

// Handle Gadget Approvals and Rejections
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token.";
        header("Location: gadget-confirmation.php");
        exit();
    }

    $admin = new Admin($conn); // Initialize Admin class

    if (isset($_POST['action'], $_POST['gadget_id'])) {
        $gadget_id = intval($_POST['gadget_id']);
        $action = $_POST['action'];

        if ($action === 'approve' && $admin->approveGadget($gadget_id)) {
            $_SESSION['success_message'] = "Gadget approved successfully.";
        } elseif ($action === 'reject' && $admin->rejectGadget($gadget_id)) {
            $_SESSION['success_message'] = "Gadget rejected and removed successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to process the gadget.";
        }
    }
    header("Location: gadget-confirmation.php");
    exit();
}

$admin = new admin($conn);
$admin->checkAdminLogin();
// Fetch pending gadgets
$pendingGadgets = $admin->getPendingGadgets();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gadget Verification - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; }
        .main-content {
            margin-left: 260px;
            padding: 80px 20px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .action-icons i { cursor: pointer; margin-right: 10px; }
        .modal-img { width: 100%; max-width: 300px; height: auto; }
        .modal-content {
            padding: 20px;
        }
        .modal-title {
            font-weight: bold;
        }
        .modal-body p {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<?php include '../includes/admin-navbar.php'; ?>
<div class="main-content">
    <div class="container">
        <h2 class="mb-4">Gadget Verification</h2>

        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Table for Pending Gadgets -->
        <div class="table-container">
            <table class="table table-hover align-middle" id="gadgetsTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Owner</th>
                        <th>Category</th>
                        <th>Applied On</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pendingGadgets)): ?>
                        <?php foreach ($pendingGadgets as $gadget): ?>
                            <tr>
    <td><?= htmlspecialchars($gadget['name'] ?? 'No name available') ?></td>
    <td><?= htmlspecialchars($gadget['owner_name'] ?? 'No owner available') ?></td>
    <td><?= htmlspecialchars($gadget['category'] ?? 'No category available') ?></td>
    <td><?= htmlspecialchars(date('d M, Y', strtotime($gadget['created_at'] ?? ''))) ?></td>
    <td class="action-icons">
        <!-- View Details Button (Eye Icon) -->
        <button type="button" class="btn btn-sm btn-info view-details" data-bs-toggle="modal" data-bs-target="#gadgetModal"
            data-name="<?= htmlspecialchars($gadget['name'] ?? 'No name available') ?>"
            data-owner="<?= htmlspecialchars($gadget['owner_name'] ?? 'No owner available') ?>"
            data-category="<?= htmlspecialchars($gadget['category'] ?? 'No category available') ?>"
            data-image="<?= htmlspecialchars($gadget['image'] ?? 'default_image.jpg') ?>"
            data-applied="<?= htmlspecialchars(date('d M, Y', strtotime($gadget['created_at'] ?? ''))) ?>"
        >
            <i class="fas fa-eye" title="View Gadget Details"></i>
        </button>
        <!-- Approve and Reject Forms -->
        <form method="POST" action="" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="gadget_id" value="<?= htmlspecialchars($gadget['id'] ?? '') ?>">
            <button type="submit" class="btn btn-sm btn-success" title="Approve">
                <i class="fas fa-check"></i>
            </button>
        </form>
        <form method="POST" action="" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="gadget_id" value="<?= htmlspecialchars($gadget['id'] ?? '') ?>">
            <button type="submit" class="btn btn-sm btn-danger" title="Reject" onclick="return confirm('Are you sure you want to reject this gadget?');">
                <i class="fas fa-times"></i>
            </button>
        </form>
    </td>
</tr>


                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No pending gadgets found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Gadget Details Modal -->
<div class="modal fade" id="gadgetModal" tabindex="-1" aria-labelledby="gadgetModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="gadgetModalLabel">Gadget Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><strong>Name:</strong> <span id="modalGadgetName"></span></p>
        <p><strong>Owner:</strong> <span id="modalOwnerName"></span></p>
        <p><strong>Category:</strong> <span id="modalCategory"></span></p>
        <p><strong>Applied On:</strong> <span id="modalAppliedOn"></span></p>
        <p><strong>Product Image:</strong></p>
        <img id="modalProductImage" src="" alt="Product Image" class="modal-img mb-3">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS (with Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // When a "View Details" button is clicked, fill the modal with data attributes.
    document.querySelectorAll('.view-details').forEach(function(button) {
    button.addEventListener('click', function() {
        // Fill in modal details dynamically
        document.getElementById('modalGadgetName').textContent = this.getAttribute('data-name');
        document.getElementById('modalOwnerName').textContent = this.getAttribute('data-owner');
        document.getElementById('modalCategory').textContent = this.getAttribute('data-category');
        document.getElementById('modalAppliedOn').textContent = this.getAttribute('data-applied');
        
        // Set the image source dynamically
        const imagePath = "../img/uploads/" + this.getAttribute('data-image');
        document.getElementById('modalProductImage').src = imagePath;
    });
});
</script>
</body>
</html>
