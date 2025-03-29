<?php
session_start();
require_once '../db/db.php';
require_once 'staff_class.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}

$staff = new Staff($conn);
$staff->checkStaffLogin();
$csrfToken = $staff->generateCsrfToken();

// Get receipts for current staff
$query = "SELECT r.*, p.name AS product_name, renter.name AS renter_name 
          FROM receipts r
          JOIN rentals rnt ON r.rental_id = rnt.id
          JOIN products p ON rnt.product_id = p.id
          JOIN users renter ON rnt.renter_id = renter.id
          WHERE r.issued_by = ?
          ORDER BY r.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['id']]);
$receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once 'head.php' ?>
    <title>Receipts</title>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-receipt me-2"></i>Generated Receipts</h4>
        </div>
        
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Receipt ID</th>
                            <th>Product</th>
                            <th>Renter</th>
                            <th>Total Amount</th>
                            <th>Payment Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receipts as $receipt): ?>
                        <tr>
                            <td>#<?= $receipt['id'] ?></td>
                            <td><?= htmlspecialchars($receipt['product_name']) ?></td>
                            <td><?= htmlspecialchars($receipt['renter_name']) ?></td>
                            <td>₱<?= number_format($receipt['total_amount'], 2) ?></td>
                            <td>
                                <span class="badge <?= $receipt['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $receipt['payment_status'])) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($receipt['created_at'])) ?></td>
                            <td>
                                <a href="receipt-details.php?id=<?= $receipt['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>