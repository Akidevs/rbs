<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../db/db.php';
require_once 'admin_class.php';
require_once 'admin_auth.php';
$admin = new admin($conn);


$admin->checkAdminLogin();
$stats = $admin->getKeyStatistics();
$recentRentals = $admin->getRecentRentals();
$recentSupport = $admin->getRecentSupportRequests();
$monthlyRentals = $admin->getMonthlyRentals();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Rentbox</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Custom styles for better visualization */
        .mt{
            margin-top: 80px;
        }
        .card-icon {
            font-size: 2.5rem;
            opacity: 0.7;
        }
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
    </style>
</head>
<body>
<?php include '../includes/admin-navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <h2 class="mt">Admin Dashboard</h2>

                <!-- Statistics Cards -->
                <div class="row mt-4">
                    <!-- Total Users -->
                    <div class="col-md-3">
                        <div class="card text-white bg-primary bg-gradient mb-3">
                            <div class="card-body d-flex align-items-center">
                                <i class="bi bi-people-fill card-icon"></i>
                                <div class="ms-3">
                                    <h5 class="card-title">Total Users</h5>
                                    <p class="card-text display-6"><?= htmlspecialchars($stats['total_users']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Total Rentals -->
                    <div class="col-md-3">
                        <div class="card text-white bg-success bg-gradient mb-3">
                            <div class="card-body d-flex align-items-center">
                                <i class="bi bi-cart-fill card-icon"></i>
                                <div class="ms-3">
                                    <h5 class="card-title">Total Rentals</h5>
                                    <p class="card-text display-6"><?= htmlspecialchars($stats['total_rentals']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Pending Gadgets -->
                    <div class="col-md-3">
                        <div class="card text-white bg-warning bg-gradient mb-3">
                            <div class="card-body d-flex align-items-center">
                                <i class="bi bi-hourglass-split card-icon"></i>
                                <div class="ms-3">
                                    <h5 class="card-title">Pending Gadgets</h5>
                                    <p class="card-text display-6"><?= htmlspecialchars($stats['pending_gadgets']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Open Support Requests -->
                    <div class="col-md-3">
                        <div class="card text-white bg-danger bg-gradient mb-3">
                            <div class="card-body d-flex align-items-center">
                                <i class="bi bi-chat-dots-fill card-icon"></i>
                                <div class="ms-3">
                                    <h5 class="card-title">Open Support Requests</h5>
                                    <p class="card-text display-6"><?= htmlspecialchars($stats['open_support_requests']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Statistics -->
                <div class="row">
                    <!-- Total Gadgets -->
                    <div class="col-md-3">
                        <div class="card text-white bg-info bg-gradient mb-3">
                            <div class="card-body d-flex align-items-center">
                                <i class="bi bi-box-seam card-icon"></i>
                                <div class="ms-3">
                                    <h5 class="card-title">Total Gadgets</h5>
                                    <p class="card-text display-6"><?= htmlspecialchars($stats['total_gadgets']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Total Owners -->
                    <div class="col-md-3">
                        <div class="card text-white bg-secondary bg-gradient mb-3">
                            <div class="card-body d-flex align-items-center">
                                <i class="bi bi-person-workspace card-icon"></i>
                                <div class="ms-3">
                                    <h5 class="card-title">Total Owners</h5>
                                    <p class="card-text display-6"><?= htmlspecialchars($stats['total_owners']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Total Renters -->
                    <div class="col-md-3">
                        <div class="card text-white bg-dark bg-gradient mb-3">
                            <div class="card-body d-flex align-items-center">
                                <i class="bi bi-person-check-fill card-icon"></i>
                                <div class="ms-3">
                                    <h5 class="card-title">Total Renters</h5>
                                    <p class="card-text display-6"><?= htmlspecialchars($stats['total_renters']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Total Disputes -->
                    <div class="col-md-3">
                        <div class="card text-white bg-secondary bg-gradient mb-3">
                            <div class="card-body d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle-fill card-icon"></i>
                                <div class="ms-3">
                                    <h5 class="card-title">Total Disputes</h5>
                                    <p class="card-text display-6"><?= htmlspecialchars($stats['total_disputes']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Rentals and Support Requests -->
                <div class="row">
                    <!-- Recent Rentals -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Recent Rentals</span>
                                <a href="transactions.php" class="btn btn-sm btn-outline-secondary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th>Renter</th>
                                            <th>Start Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recentRentals)): ?>
                                            <?php foreach ($recentRentals as $rental): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($rental['product_name']) ?></td>
                                                    <td><?= htmlspecialchars($rental['renter_name']) ?></td>
                                                    <td><?= htmlspecialchars(date('d M, Y', strtotime($rental['start_date']))) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= 
                                                            $rental['status'] === 'pending_confirmation' ? 'warning' : 
                                                            ($rental['status'] === 'approved' ? 'primary' : 
                                                            ($rental['status'] === 'completed' ? 'success' : 'secondary'))
                                                        ?>">
                                                            <?= ucfirst($rental['status']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No recent rentals.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- Recent Support Requests -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Recent Support Requests</span>
                                <a href="supports.php" class="btn btn-sm btn-outline-secondary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>User</th>
                                            <th>Subject</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recentSupport)): ?>
                                            <?php foreach ($recentSupport as $support): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($support['user_name']) ?></td>
                                                    <td><?= htmlspecialchars($support['subject']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= 
                                                            $support['status'] === 'open' ? 'warning' : 
                                                            ($support['status'] === 'in_progress' ? 'info' : 'success')
                                                        ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $support['status'])) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center">No recent support requests.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Rentals Chart -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card mb-3">
                            <div class="card-header">
                                Monthly Rentals
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyRentalsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Analytics and Reports -->
                <!-- You can add more charts or tables here as needed -->
            </main>
        </div>
    </div>

    <!-- Chart.js Script -->
    <script>
        const ctx = document.getElementById('monthlyRentalsChart').getContext('2d');
        const labels = [<?php foreach ($monthlyRentals as $mr) echo '"' . htmlspecialchars($mr['month']) . '",'; ?>];
        const data = [<?php foreach ($monthlyRentals as $mr) echo htmlspecialchars($mr['rentals']) . ','; ?>];

        const monthlyRentalsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Number of Rentals',
                    data: data,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        precision: 0
                    }
                }
            }
        });
    </script>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>