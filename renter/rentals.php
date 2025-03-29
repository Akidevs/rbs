<?php
// renter/rentals.php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once __DIR__ . '/../db/db.php';
require_once 'renter_class.php';
$renter = new renter($conn);
$renter->authenticateRenter();



// Get the logged-in renter's ID
$renterId = $_SESSION['id'];

// Fetch rentals
$rentals = $renter->getRentals($renterId);

// Example usage in HTML
foreach ($rentals as $rental) {
    $statusColor = $renter->getStatusBadgeColor($rental['status']);
    $daysColor = $renter->getRemainingDaysBadgeColor($rental['remaining_days']);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Rentals</title>
    <link rel="stylesheet" href="../css/renter/browse_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        main {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            height: auto;
            padding-top: 20px;
            background-color: #f8f9fa;
        }
        .card {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            width: 100%;
            max-width: 1200px;
            padding: 2rem;
        }
        .table-responsive {
            max-height: 70vh;
            overflow-y: auto;
        }
        .img-thumbnail {
            height: 100px;
            width: 100px;
            object-fit: cover;
            margin: auto;
        }
        .table th,
        .table td {
            vertical-align: middle;
            text-align: center;
            height: 50px;
        }
    </style>
    
</head>
<body>
    <?php include '../includes/navbarr.php'; ?>

    <main>
        <div class="card">
            <h2 class="text-center mb-4">My Rentals</h2>

            <!-- Success Message -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-striped table-bordered text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>No.</th>
                            <th>Gadget</th>
                            <th>Owner</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Remaining Days</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
    <?php if (!empty($rentals)): ?>
        <?php foreach ($rentals as $index => $rental): ?>
            <tr>
            <td><?= htmlspecialchars($index + 1) ?></td>
<td>
    <div class="d-flex flex-column align-items-center">
        <img src="../img/uploads/<?= htmlspecialchars($rental['image']) ?>" 
             alt="<?= htmlspecialchars($rental['product_name']) ?>" 
             class="img-thumbnail">
        <p class="small mt-1 mb-0"><?= htmlspecialchars($rental['product_name']) ?> (<?= htmlspecialchars($rental['brand']) ?>)</p>
    </div>
</td>
<!-- Corrected column order -->
<td><?= htmlspecialchars($rental['owner_name'] ?? 'N/A') ?></td>
<td><?= htmlspecialchars($rental['display_start']) ?></td>
<td><?= htmlspecialchars($rental['display_end']) ?></td>
<td>
    <span class="badge bg-<?= $renter->getStatusBadgeColor($rental['status']) ?>">
        <?= htmlspecialchars($rental['status']) ?>
    </span>
</td>
<td>
    <span class="badge bg-<?= $renter->getRemainingDaysBadgeColor($rental['remaining_days']) ?>">
        <?= htmlspecialchars($rental['remaining_days']) ?>
    </span>
</td>
<td>
    <a href="rental_details.php?rental_id=<?= htmlspecialchars($rental['id']) ?>" class="btn btn-info btn-sm">View</a>
</td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="8" class="text-center">No rentals found.</td>
        </tr>
    <?php endif; ?>
    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
