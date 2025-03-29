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

$receiptId = $_GET['id'] ?? 0;
$csrfToken = $staff->generateCsrfToken();

// Get receipt details
$query = "SELECT r.*, 
                 p.name AS product_name,
                 renter.name AS renter_name,
                 owner.name AS owner_name,
                 staff.name AS staff_name
          FROM receipts r
          JOIN rentals rnt ON r.rental_id = rnt.id
          JOIN products p ON rnt.product_id = p.id
          JOIN users renter ON rnt.renter_id = renter.id
          JOIN users owner ON rnt.owner_id = owner.id
          JOIN users staff ON r.issued_by = staff.id
          WHERE r.id = ?";

$stmt = $conn->prepare($query);
$stmt->execute([$receiptId]);
$receipt = $stmt->fetch();

// Get receipt items
$itemsQuery = "SELECT * FROM receipt_items WHERE receipt_id = ?";
$itemsStmt = $conn->prepare($itemsQuery);
$itemsStmt->execute([$receiptId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get platform fees
$feesQuery = "SELECT * FROM platform_fees WHERE rental_id = 
              (SELECT rental_id FROM receipts WHERE id = ?)";
$feesStmt = $conn->prepare($feesQuery);
$feesStmt->execute([$receiptId]);
$fees = $feesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get owner earnings
$earningsQuery = "SELECT * FROM owner_earnings WHERE rental_id = 
                 (SELECT rental_id FROM receipts WHERE id = ?)";
$earningsStmt = $conn->prepare($earningsQuery);
$earningsStmt->execute([$receiptId]);
$earnings = $earningsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once 'head.php' ?>
    <title>Receipt Details</title>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">
                <i class="fas fa-receipt me-2"></i>
                Receipt #<?= $receipt['id'] ?>
            </h4>
        </div>
        
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5>Rental Details</h5>
                    <p class="mb-1">
                        <strong>Product:</strong> <?= htmlspecialchars($receipt['product_name']) ?><br>
                        <strong>Renter:</strong> <?= htmlspecialchars($receipt['renter_name']) ?><br>
                        <strong>Owner:</strong> <?= htmlspecialchars($receipt['owner_name']) ?>
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <h5>Payment Information</h5>
                    <p class="mb-1">
                        <strong>Issued By:</strong> <?= htmlspecialchars($receipt['staff_name']) ?><br>
                        <strong>Date:</strong> <?= date('M d, Y H:i', strtotime($receipt['created_at'])) ?><br>
                        <strong>Status:</strong> 
                        <span class="badge <?= $receipt['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning' ?>">
                            <?= ucfirst($receipt['payment_status']) ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="table-responsive mb-4">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Item Type</th>
                            <th>Description</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= ucfirst(str_replace('_', ' ', $item['item_type'])) ?></td>
                            <td><?= htmlspecialchars($item['description']) ?></td>
                            <td>₱<?= number_format($item['amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-secondary">
                            <td colspan="2" class="text-end"><strong>Total:</strong></td>
                            <td><strong>₱<?= number_format($receipt['total_amount'], 2) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            Platform Fees
                        </div>
                        <div class="card-body">
                            <?php foreach ($fees as $fee): ?>
                            <p class="mb-1">
                                <?= ucfirst(str_replace('_', ' ', $fee['fee_type'])) ?>:
                                ₱<?= number_format($fee['fee_amount'], 2) ?>
                            </p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            Owner Earnings
                        </div>
                        <div class="card-body">
                            <?php if (!empty($earnings)): ?>
                            <p class="mb-1">
                                Total Earnings: ₱<?= number_format($earnings[0]['net_earning'], 2) ?><br>
                                Payment Status: <span class="badge bg-<?= $earnings[0]['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($earnings[0]['payment_status']) ?>
                                </span>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-footer text-end">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-2"></i>Print Receipt
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>