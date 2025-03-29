<?php
session_start();
require_once '../db/db.php';
require_once 'extend_class.php';
$extendedStaff = new ExtendedStaff($conn);

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}

$extendedStaff->checkStaffLogin();

$productId = $_GET['product_id'] ?? null;
$rentalId = $_GET['rental_id'] ?? null;

if (!$productId || !ctype_digit($productId) || !$rentalId || !ctype_digit($rentalId)) {
    header("Location: gadgets.php");
    exit();
}

$deviceDetails = $extendedStaff->getDeviceDetails($productId);
$specificRental = $extendedStaff->getSpecificRental($rentalId, $_SESSION['id']);
// Updated condition to show financial sections only for ready_for_pickup and pending_return
$showFinancialSections = $specificRental && in_array($specificRental['status'], ['ready_for_pickup', 'pending_return']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        try {
            $extendedStaff->verifyCsrfToken($_POST['csrf_token'] ?? '');
    
            $newStatus = $_POST['status'];
            $rentalId = $_POST['rental_id'];
            $staffId = $_SESSION['id'];
            
            $receipt = $extendedStaff->getRentalReceipt($rentalId);
            if ($receipt && $receipt['payment_status'] === 'pending' && $newStatus !== 'ready_for_pickup') {
                throw new Exception("Cannot update status until payment status is changed from 'pending' in the receipt.");
            }
    
            $extendedStaff->updateRentalStatus($rentalId, $newStatus, $staffId);
    
            if ($newStatus === 'returned') {
                $fees = $extendedStaff->calculateRentalFees($rentalId, $staffId);
                $receiptId = $extendedStaff->generateReceipt($rentalId, $staffId, $fees);
                $_SESSION['success'] = "Status updated and receipt #$receiptId generated!";
            } else {
                $_SESSION['success'] = "Status updated successfully!";
            }
    
            header("Location: pickup-confirmations.php?product_id=$productId&rental_id=$rentalId");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: pickup-confirmations.php?product_id=$productId&rental_id=$rentalId");
            exit();
        }
    }  

    if (isset($_POST['save_receipt'])) {
        try {
            $extendedStaff->verifyCsrfToken($_POST['csrf_token'] ?? '');
    
            $data = [
                'receipt_id' => $_POST['receipt_id'] ?? null,
                'rental_id' => $_POST['rental_id'],
                'total_amount' => (float)($_POST['total_amount'] ?? 0),
                'payment_status' => $_POST['payment_status'],
                'items' => $_POST['items'] ?? []
            ];

            $receiptId = $extendedStaff->saveReceipt($data, $_SESSION['id']);
            $_SESSION['success'] = "Receipt updated successfully!";
    
            header("Location: pickup-confirmations.php?product_id=$productId&rental_id=$rentalId");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: pickup-confirmations.php?product_id=$productId&rental_id=$rentalId");
            exit();
        }
    }
}

$csrfToken = $extendedStaff->generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickup Confirmations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge { padding: 0.25rem 0.5rem; font-size: 0.875rem; border-radius: 0.25rem; }
        .remaining-days { min-width: 120px; }
        .status-select { max-width: 150px; }
        .main-content {
            max-width: 100%;
            padding: 20px;
            margin: 0 auto;
        }
        .rental-calculations {
            background-color: rgba(245, 245, 245, 0.9);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .calculation-item {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
        }
        body {
            padding-top: 70px;
        }
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: 1px solid #e3e6f0;
            border-radius: 0.35rem;
        }
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        .border-left-primary {
            border-left: 0.25rem solid #4e73df !important;
        }
        .border-left-success {
            border-left: 0.25rem solid #1cc88a !important;
        }
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-left-primary">
                    <div class="card-header py-3">
                        <h5 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-box-open fa-fw mr-2"></i>
                            Pickup Details for: <?= htmlspecialchars($specificRental['product_name'] ?? 'Unknown Device') ?>
                        </h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card border-left-success">
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Renter</th>
                                        <th>Status</th>
                                        <th class="remaining-days">Remaining Days</th>
                                        <th>Rental Period</th>
                                        <th>Total Cost</th>
                                        <th>Handover Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if ($specificRental): 
                                    date_default_timezone_set('Asia/Manila');
                                    $handoverDate = $specificRental['handover_date'] ?? null;
                                    $numberOfPeriods = (int)($specificRental['number_of_periods'] ?? 0);
                                    $adjustedEndDate = null;
                                    $remainingDays = null;
                                    $isOverdue = false;

                                    if ($specificRental['status'] === 'picked_up' && $handoverDate) {
                                        $startDate = strtotime($handoverDate);
                                        $adjustedEndDate = strtotime("+$numberOfPeriods days", $startDate);
                                        $currentTime = time();
                                        $remainingDays = (int) ceil(($adjustedEndDate - $currentTime) / (60 * 60 * 24));
                                        $isOverdue = $remainingDays < 0;
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="../img/uploads/<?= htmlspecialchars($specificRental['image'] ?? 'default.jpg') ?>" 
                                                     class="me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                                <div>
                                                    <div class="font-weight-bold"><?= htmlspecialchars($specificRental['product_name']) ?></div>
                                                    <small class="text-muted">Owner: <?= htmlspecialchars($specificRental['owner_name']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($specificRental['renter_name']) ?></td>
                                        <td>
                                            <span class="badge 
                                                <?= match($specificRental['status']) {
                                                    'handed_over_to_admin' => 'bg-info',
                                                    'ready_for_pickup' => 'bg-info',
                                                    'picked_up' => 'bg-primary',
                                                    'pending_return' => 'bg-warning',
                                                    'returned' => 'bg-secondary',
                                                    'overdue' => 'bg-danger',
                                                    default => 'bg-secondary'
                                                } ?>">
                                                <?= ucfirst(str_replace('_', ' ', $specificRental['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($specificRental['status'] === 'picked_up' && $handoverDate): ?>
                                                <?php if ($isOverdue): ?>
                                                    <span class="badge bg-danger">
                                                        Overdue by <?= abs($remainingDays) ?> days
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">
                                                        <?= $remainingDays ?> days left
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">NA</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($specificRental['status'] === 'picked_up' && $handoverDate && $adjustedEndDate): ?>
                                                <?= date('M d, Y', strtotime($handoverDate)) ?> - 
                                                <?= date('M d, Y', $adjustedEndDate) ?>
                                                <br><small class="text-muted">Rental period: <?= $numberOfPeriods ?> days</small>
                                            <?php else: ?>
                                                <span class="text-muted">NA</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>₱<?= number_format($specificRental['total_cost'], 2) ?></td>
                                        <td>
                                            <?= $specificRental['handover_date'] 
                                                ? date('M d, Y H:i', strtotime($specificRental['handover_date'])) 
                                                : '<span class="text-muted">Not recorded</span>' ?>
                                        </td>
                                        <td>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="rental_id" value="<?= $specificRental['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <div class="input-group input-group-sm">
                                                    <select name="status" class="form-select form-select-sm" required>
                                                        <option value="">Change status</option>
                                                        <?php 
                                                        $currentStatus = $specificRental['status'];
                                                        $allowedNextStatuses = match ($currentStatus) {
                                                            'handed_over_to_admin' => ['ready_for_pickup'],
                                                            'ready_for_pickup' => ['picked_up'],
                                                            'picked_up' => $remainingDays < 0 ? ['overdue'] : ['pending_return'],
                                                            'pending_return' => ['returned'],
                                                            'overdue' => ['returned'],
                                                            'returned' => ['completed'],
                                                            default => []
                                                        };
                                                        foreach ($allowedNextStatuses as $status):
                                                        ?>
                                                        <option value="<?= $status ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $status)) ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-primary">Update</button>
                                                </div>
                                            </form>

                                            <?php 
                                            $proofTypeMap = [
                                                'ready_for_pickup' => 'handed_over_to_admin',
                                                'picked_up' => 'picked_up',
                                                'pending_return' => 'returned'
                                            ];
                                            if (array_key_exists($currentStatus, $proofTypeMap)):
                                            ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-success mt-2" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#proofModal"
                                                        data-rental-id="<?= $specificRental['id'] ?>"
                                                        data-proof-type="<?= $proofTypeMap[$currentStatus] ?>">
                                                    <i class="fas fa-upload me-1"></i>Add Proofs
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="fas fa-box-open fa-2x mb-3"></i>
                                            <p class="mb-0">No rental found for this device</p>
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
</div>
<?php if ($showFinancialSections): ?>
<div class="rental-calculations card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-calculator"></i> Rental Calculations</h5>
    </div>
    <div class="card-body">
    <?php
    if ($specificRental) {
        $fees = $extendedStaff->calculateRentalFees(
            $specificRental['id'], 
            $_SESSION['id']
        );
        $dailyRate = $fees['daily_rate'] ?? 0;
    } else {
        $fees = [
            'daily_rate' => 0,
            'number_of_periods' => 0,
            'total_cost' => 0,
            'total_days_rented' => 0,
            'overdue_days' => 0,
            'overdue_fee' => 0,
            'total_calculated' => 0
        ];
        $dailyRate = 0;
    }
    $receipt = $extendedStaff->getRentalReceipt($rentalId);
    ?>

    <div class="calculation-item">
        <span>Daily Rate:</span>
        <span>₱<?= number_format($dailyRate, 2) ?></span>
    </div>
    <div class="calculation-item">
        <span>Number of Periods:</span>
        <span><?= $fees['number_of_periods'] ?></span>
    </div>
    <div class="calculation-item">
        <span>Rent Cost per Day:</span>
        <span>₱<?= number_format($fees['rent_cost_per_day'], 2) ?></span>
    </div>

    <?php if ($specificRental['status'] === 'ready_for_pickup'): ?>
        <div class="calculation-item fw-bold">
            <span>Total Cost:</span>
            <span>₱<?= number_format($fees['total_cost'], 2) ?></span>
        </div>
        <?php if ($receipt && $receipt['payment_status'] === 'paid'): ?>
            <div class="calculation-item text-success fw-bold">
                <span>Status:</span>
                <span>PAID</span>
            </div>
        <?php endif; ?>
    <?php elseif ($specificRental['status'] === 'pending_return'): ?>
        <div class="calculation-item">
            <span>Total Days Rented:</span>
            <span><?= $fees['total_days_rented'] ?> days</span>
        </div>
        <div class="calculation-item">
            <span>Total Cost:</span>
            <span>₱<?= number_format($fees['total_cost'], 2) ?></span>
        </div>
        <div class="calculation-item text-danger">
            <span>Overdue Days (150% fee):</span>
            <span><?= $fees['overdue_days'] ?> days</span>
        </div>
        <div class="calculation-item text-danger">
            <span>Overdue Fees:</span>
            <span>₱<?= number_format($fees['overdue_fee'], 2) ?></span>
        </div>
        <hr>
        <div class="calculation-item fw-bold">
            <span>Total Calculated:</span>
            <span>₱<?= number_format($fees['total_calculated'], 2) ?></span>
        </div>
        <?php if ($receipt && $receipt['payment_status'] === 'paid' && $fees['overdue_fee'] == 0): ?>
            <div class="calculation-item text-success fw-bold">
                <span>Status:</span>
                <span>PAID</span>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Rental Receipt Section -->
    <div class="card mt-4 border-success">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-receipt"></i> Rental Receipt</h5>
        </div>
        <div class="card-body">
            <?php
            $receiptItems = $receipt ? $extendedStaff->getReceiptItems($receipt['id']) : [];
            $displayTotal = $receipt ? 
                ($receipt['payment_status'] === 'paid' && $fees['overdue_fee'] == 0 ? 0 : $fees['total_calculated']) : 
                $fees['total_cost'] ?? 0;
            ?>
            
            <form method="post" id="receiptForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="rental_id" value="<?= $rentalId ?>">
                <?php if ($receipt): ?>
                    <input type="hidden" name="receipt_id" value="<?= $receipt['id'] ?>">
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" class="form-select" required>
                            <?php foreach (['pending', 'paid', 'partially_paid'] as $status): ?>
                                <option value="<?= $status ?>" <?= ($receipt['payment_status'] ?? 'pending') === $status ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $status)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Total Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" class="form-control" name="total_amount" 
                                   value="<?= number_format($displayTotal, 2, '.', '') ?>" 
                                   step="0.01" 
                                   <?= $specificRental['status'] === 'ready_for_pickup' || ($specificRental['status'] === 'returned' && $receipt && $receipt['payment_status'] === 'paid' && $fees['overdue_fee'] == 0) ? 'readonly' : '' ?> 
                                   required>
                            <?php if ($receipt && $receipt['payment_status'] === 'paid' && $fees['overdue_fee'] == 0): ?>
                                <span class="input-group-text bg-success text-white">PAID</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Receipt Items -->
                <div class="table-responsive mb-3">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Item Type</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th style="width:40px"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsTable">
                            <?php foreach ($receiptItems as $item): ?>
                            <tr>
                                <td>
                                    <select name="items[][type]" class="form-select">
                                        <?php foreach ($extendedStaff->getReceiptItemTypes() as $type): ?>
                                            <option value="<?= $type ?>" <?= $item['item_type'] === $type ? 'selected' : '' ?>>
                                                <?= ucfirst(str_replace('_', ' ', $type)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="items[][desc]" class="form-control" 
                                         value="<?= htmlspecialchars($item['description']) ?>"></td>
                                <td><input type="number" name="items[][amount]" class="form-control" 
                                         value="<?= $item['amount'] ?>" step="0.01"></td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-item"><i class="fas fa-times"></i></button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" id="addItem">
                        <i class="fas fa-plus me-2"></i>Add Item
                    </button>
                    <div>
                        <button type="submit" name="save_receipt" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Receipt
                        </button>
                        <?php if($receipt): ?>
                            <a href="receipts.php" class="btn btn-success">
                                <i class="fas fa-list me-2"></i>View All Receipts
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    </div>
</div>
<?php endif; ?>
<div class="modal fade" id="proofModal" tabindex="-1" aria-labelledby="proofModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="proofModalLabel">Upload Rental Proofs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" enctype="multipart/form-data" id="proofUploadForm">
                <div class="modal-body">
                    <input type="hidden" name="rental_id" id="modalRentalId">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="proof_type" id="modalProofType">
                    
                    <div id="proofEntries">
                        <div class="proof-entry mb-3">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="text" 
                                           class="form-control" 
                                           name="descriptions[]" 
                                           placeholder="Proof description (optional)"
                                           maxlength="100">
                                </div>
                                <div class="col-md-5">
                                    <input type="file" 
                                           class="form-control" 
                                           name="proof_files[]" 
                                           accept="image/*,.pdf,.doc,.docx"
                                           required>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" 
                                            class="btn btn-danger btn-remove-entry" 
                                            disabled>
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-secondary btn-sm" id="addProofEntry">
                        <i class="fas fa-plus me-1"></i>Add Another Proof
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="upload_proof" class="btn btn-primary">Upload Proofs</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const proofModal = document.getElementById('proofModal');
    const proofEntries = document.getElementById('proofEntries');
    const addEntryBtn = document.getElementById('addProofEntry');
    let entryCount = 1;

    addEntryBtn.addEventListener('click', function() {
        if(entryCount >= 5) return;
        entryCount++;
        const newEntry = document.createElement('div');
        newEntry.className = 'proof-entry mb-3';
        newEntry.innerHTML = `
            <div class="row g-2">
                <div class="col-md-6">
                    <input type="text" 
                           class="form-control" 
                           name="descriptions[]" 
                           placeholder="Proof description (optional)"
                           maxlength="100">
                </div>
                <div class="col-md-5">
                    <input type="file" 
                           class="form-control" 
                           name="proof_files[]" 
                           accept="image/*,.pdf,.doc,.docx"
                           required>
                </div>
                <div class="col-md-1">
                    <button type="button" 
                            class="btn btn-danger btn-remove-entry">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        proofEntries.appendChild(newEntry);
        updateRemoveButtons();
    });

    document.getElementById('addItem').addEventListener('click', function() {
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td>
                <select name="items[][type]" class="form-select">
                    ${itemTypes.map(type => `
                        <option value="${type}">${type.replace(/_/g, ' ')}</option>
                    `).join('')}
                </select>
            </td>
            <td><input type="text" name="items[][desc]" class="form-control"></td>
            <td><input type="number" name="items[][amount]" class="form-control" step="0.01"></td>
            <td><button type="button" class="btn btn-danger btn-sm remove-item"><i class="fas fa-times"></i></button></td>
        `;
        document.getElementById('itemsTable').appendChild(newRow);
    });

    function updateRemoveButtons() {
        const entries = proofEntries.querySelectorAll('.proof-entry');
        entries.forEach((entry, index) => {
            const removeBtn = entry.querySelector('.btn-remove-entry');
            removeBtn.disabled = entries.length === 1;
        });
    }

    proofModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        document.getElementById('modalRentalId').value = button.getAttribute('data-rental-id');
        document.getElementById('modalProofType').value = button.getAttribute('data-proof-type');
        proofEntries.innerHTML = `
            <div class="proof-entry mb-3">
                <div class="row g-2">
                    <div class="col-md-6">
                        <input type="text" 
                               class="form-control" 
                               name="descriptions[]" 
                               placeholder="Proof description (optional)"
                               maxlength="100">
                    </div>
                    <div class="col-md-5">
                        <input type="file" 
                               class="form-control" 
                               name="proof_files[]" 
                               accept="image/*,.pdf,.doc,.docx"
                               required>
                    </div>
                    <div class="col-md-1">
                        <button type="button" 
                                class="btn btn-danger btn-remove-entry" 
                                disabled>
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        entryCount = 1;
        updateRemoveButtons();
    });
});
</script>
</body>
</html>