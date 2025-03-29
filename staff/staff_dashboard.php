<?php
session_start();
require_once '../db/db.php';
require_once 'staff_class.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}

$staff = new Staff($conn);
$rentalQueue = $staff->getRentalQueue();
$returnQueue = $staff->getReturnQueue();
$overdueRentals = $staff->getOverdueRentals();
$transactionCount = $staff->countTransactions($_SESSION['id']);
$dailyRevenue = $staff->getDailyRevenue();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once 'head.php' ?>
    <link rel="stylesheet" href="style.css">
</head>
<body class="container-fluid bg-dark-subtle m-0 p-0">
    <?php require_once 'navbar.php' ?>

    <!-- System Alerts -->
    <div class="container mt-3">
        <?php if(count($overdueRentals) > 0): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-octagon me-2"></i>
            <strong>Attention:</strong> You have <?= count($overdueRentals) ?> overdue rentals needing immediate action!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if(count($returnQueue) > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="bi bi-box-seam me-2"></i>
            <strong>Pending Returns:</strong> <?= count($returnQueue) ?> items waiting to be processed
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
    </div>

    <div class="container-fluid min-vh-100 p-3">
        <!-- Quick Action Menu -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                            <div class="btn-group">
                                <a href="gadgets.php" class="btn btn-outline-primary">
                                    <i class="bi bi-laptop"></i> Manage Gadgets
                                </a>
                                <a href="customers.php" class="btn btn-outline-success">
                                    <i class="bi bi-people"></i> View Customers
                                </a>
                                <a href="transactions.php" class="btn btn-outline-info">
                                    <i class="bi bi-cash-stack"></i> Financial Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-hourglass-top text-warning fs-3"></i>
                        <h3 class="mt-2"><?= count($rentalQueue) + count($returnQueue) ?></h3>
                        <p class="text-muted mb-0">Pending Actions</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-exclamation-triangle text-danger fs-3"></i>
                        <h3 class="mt-2"><?= count($overdueRentals) ?></h3>
                        <p class="text-muted mb-0">Overdue Rentals</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-cash-coin text-success fs-3"></i>
                        <h3 class="mt-2">₱<?= number_format($dailyRevenue, 2) ?></h3>
                        <p class="text-muted mb-0">Today's Revenue</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center gap-3">
                            <a href="checkin.php" class="btn bg-success bg-gradient btn-lg text-white">
                                <i class="bi bi-box-arrow-in-down"></i> Check In
                            </a>
                            <a href="checkout.php" class="btn bg-success bg-gradient btn-lg text-white">
                                <i class="bi bi-box-arrow-up"></i> Check Out
                            </a>
                            <a href="new_rental.php" class="btn bg-success bg-gradient btn-lg text-white">
                                <i class="bi bi-cart-plus"></i> New Rental
                            </a>
                            <a href="active_rentals.php" class="btn bg-success bg-gradient btn-lg text-white">
                                <i class="bi bi-list-task"></i> Active Rentals
                            </a>

                            <a href="active_rentals.php" class="btn bg-success bg-gradient btn-lg text-white">
                                <i class="bi bi-list-task"></i> Report Issues/Contact Admin
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Tables -->
        <div class="row">
            <!-- Rental Queue -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Rental Queue</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Rental ID</th>
                                        <th>Product</th>
                                        <th>Renter</th>
                                        <th>Start Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                        <?php foreach ($rentalQueue as $rental): ?>
                                        <tr>
                                            <td><?= $rental['id'] ?></td>
                                            <td><?= htmlspecialchars($rental['product_name']) ?></td>
                                            <td><?= htmlspecialchars($rental['renter_name']) ?></td>
                                            <td>
                                                <?php if (!empty($rental['start_date'])): ?>
                                                    <?= date('M d, Y', strtotime($rental['start_date'])) ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $staff->getStatusBadgeClass($rental['status']) ?>">
                                                    <?= $staff->formatStatus($rental['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Return Queue -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-arrow-return-left me-2"></i>Return Queue</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Rental ID</th>
                                        <th>Product</th>
                                        <th>Renter</th>
                                        <th>Due Date</th>
                                        <th>Days Overdue</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($returnQueue as $return): ?>
                                        <tr>
                                            <td><?= $return['id'] ?></td>
                                            <td><?= htmlspecialchars($return['product_name']) ?></td>
                                            <td><?= htmlspecialchars($return['renter_name']) ?></td>
                                            <td>
                                                <?php if (!empty($return['end_date'])): ?>
                                                    <?= date('M d, Y', strtotime($return['end_date'])) ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                        <td>
                                            <a href="process_return.php?id=<?= $return['id'] ?>" 
                                               class="btn btn-sm btn-warning">Process Return</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overdue Rentals -->
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Overdue Rentals</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Rental ID</th>
                                        <th>Product</th>
                                        <th>Renter</th>
                                        <th>Due Date</th>
                                        <th>Overdue Fee</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overdueRentals as $overdue): ?>
                                    <tr>
                                        <td><?= $overdue['id'] ?></td>
                                        <td><?= htmlspecialchars($overdue['product_name']) ?></td>
                                        <td><?= htmlspecialchars($overdue['renter_name']) ?></td>
                                        <td><?= date('M d, Y', strtotime($overdue['end_date'])) ?></td>
                                        <td>₱<?= number_format($staff->calculateOverdueFee($overdue['id']), 2) ?></td>
                                        <td>
                                            <a href="resolve_overdue.php?id=<?= $overdue['id'] ?>" 
                                               class="btn btn-sm btn-danger">Resolve</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once 'footer.php'; ?>
    <script src="../vendor/bootstrap-5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>