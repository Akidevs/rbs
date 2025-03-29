<?php
// Ensure session is started only once
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db/db.php';

if (isset($_SESSION['id'])) {
    $userId = $_SESSION['id'];
    
    try {
        // Get complete user data with verification status
        $query = "
            SELECT 
                users.id,
                users.first_name,
                users.last_name,
                users.email,
                users.role,
                users.profile_picture,
                users.created_at,
                COALESCE(user_verification.verification_status, 'pending') AS verification_status
            FROM users
            LEFT JOIN user_verification ON users.id = user_verification.user_id
            WHERE users.id = :user_id
            LIMIT 1
        ";
        $stmt = $conn->prepare($query);
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Set profile data
            $username = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
            $userRole = htmlspecialchars($user['role']);
            $userEmail = htmlspecialchars($user['email']);
            $joinDate = date('M Y', strtotime($user['created_at']));
            
            // Handle profile picture path
            $profilePic = '/owner/includes/user.png'; // Default
            if (!empty($user['profile_picture'])) {
                $correctedPath = (strpos($user['profile_picture'], '/') === 0) 
                    ? $user['profile_picture'] 
                    : '/' . $user['profile_picture'];
                
                if (file_exists($_SERVER['DOCUMENT_ROOT'] . $correctedPath)) {
                    $profilePic = $correctedPath;
                }
            }
            
            // Set verification status
            $verificationStatus = $user['verification_status'];
            $verificationBadge = $verificationStatus === 'verified' 
                ? '<i class="bi bi-patch-check-fill text-success"></i>' 
                : '<i class="bi bi-x-circle-fill text-danger"></i>';
        }
        
    } catch(PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        $username = 'Guest';
        $userRole = 'renter';
        $profilePic = '/owner/includes/user.png'; // Default profile picture in case of error
    }
} else {
    $username = 'Guest';
    $userRole = 'renter'; // Default if not logged in
    $profilePic = '/owner/includes/user.png'; // Default profile picture if not logged in
}

// Handle the "Become a Renter" button click
if (isset($_POST['become_renter'])) {
    try {
        // Check if the user's verification status is 'verified'
        $query = "
            SELECT verification_status 
            FROM user_verification 
            WHERE user_id = :user_id
            LIMIT 1
        ";
        $stmt = $conn->prepare($query);
        $stmt->execute(['user_id' => $userId]);
        $verification = $stmt->fetch();

        // If verified, update the role to 'renter' and redirect to dashboard
        if ($verification && $verification['verification_status'] === 'verified') {
            $updateQuery = "
                UPDATE users 
                SET role = 'renter' 
                WHERE id = :user_id
            ";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->execute(['user_id' => $userId]);
            
            $_SESSION['role'] = 'renter';
            header('Location: ../renter/browse.php');
            exit;
        } else {
            echo "<script>alert('Your verification is pending. Please complete the verification process to become a renter.');</script>";
        }
    } catch(PDOException $e) {
        error_log("Database Error in becoming renter: " . $e->getMessage());
        echo "<script>alert('An error occurred. Please try again later.');</script>";
    }
}
?>

<!-- HTML code remains the same with modifications below -->

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="includes/owner-style.css">
    <title>RentBox Dashboard</title>
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        .sidebar .active {
            background-color: #e0f7fa; /* Light blue background */
            color: #00695c; /* Dark green text */
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div id="overlay"></div>
    <div class="container-fluid">
        <div class="row">
            <!-- Header -->
            <div class="col-12 header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <!-- Sidebar Toggle Button for small screens -->
                    <button id="toggleSidebarBtn" class="btn btn-outline-secondary me-3 d-md-none" type="button">
                        <i class="fas fa-bars"></i>
                    </button>
                    <!-- Logo -->
                    <img src="/owner/includes/logo.png" alt="RentBox Logo" class="logo" style="width: 50px; height: 50px; object-fit: contain;">
                    <h4 class="m-0">RentBox</h4>
                </div>
                <div class="d-flex align-items-center">
                    <!-- Notifications Dropdown -->
                    <div class="dropdown me-3">
                        <a href="#" class="text-decoration-none d-flex align-items-center" data-bs-toggle="dropdown">
                            <i class="fas fa-bell fs-5"></i> <span class="ms-2">Notifications</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end p-3 shadow notification-drawer" style="width: 280px; height: 500px; overflow-y: auto;">
                            <div class="d-flex justify-content-between mb-3">
                                <a href="#" class="text-decoration-none fw-bold active-tab">All</a>
                                <a href="#" class="text-decoration-none ms-4 text-muted">Unread</a>
                            </div>
                            <li class="text-center mt-3"><a href="#" class="text-decoration-none text-primary fw-bold">View All Notifications</a></li>
                        </ul>
                    </div>
                    <!-- Profile Dropdown -->
                    <div class="dropdown">
                        <a href="#" class="text-decoration-none d-flex align-items-center" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="User Profile" class="profile-img">
                            <div class="d-flex flex-column align-items-start profile-details">
                                <span class="fw-bold"><?= htmlspecialchars($username) ?></span>
                                <span class="badge bg-warning text-dark"><?= htmlspecialchars($userRole) ?></span>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a href="/owner/profile.php" class="dropdown-item">Profile</a></li>
                            <li><a href="#" class="dropdown-item">Settings</a></li>
                            <li><a href="#" class="dropdown-item text-danger">Log Out</a></li>
                        </ul>
                    </div>
                    <!-- Become a Renter Button -->
                    <button class="btn btn-primary ms-3" id="becomeRenterBtn" data-bs-toggle="modal" data-bs-target="#becomeRenterModal">Become a Renter</button>
                </div>
            </div>
        </div>
        <div class="row">
            <!-- Sidebar -->
            <div id="sidebar" class="sidebar">
                <input type="text" class="form-control my-3" placeholder="Search">
                <a href="../owner/dashboard.php" id="dashboardLink"><i class="fas fa-tachometer-alt me-2 text-success"></i> Dashboard</a>
                <a href="../owner/gadget.php" id="gadgetLink"><i class="fas fa-tablet-alt me-2 text-success"></i> Gadgets</a>
                <a href="../owner/rentals.php" id="rentalsLink"><i class="fas fa-sync-alt me-2 text-success"></i> Rentals</a>
                <a href="../owner/all-reports.php" id="reportsLink"><i class="fas fa-file-alt me-2 text-success"></i> All reports</a>
                <a href="../owner/file_dispute.php" id="transactionsLink"><i class="fas fa-coins me-2 text-success"></i> File a Dispute </a>
                <a href="../owner/gadgets_assessment.php" id="assessmentLink"><i class="fas fa-file-alt me-2 text-success"></i> Assess Gadgets</a>
                <a href="../owner/logout.php" class="text-danger" id="logoutLink"><i class="fas fa-sign-out-alt me-2 text-success"></i> Log out</a>
            </div>
        </div>
    </div>

    <!-- Modal for becoming a renter -->
    <div class="modal fade" id="becomeRenterModal" tabindex="-1" aria-labelledby="becomeRenterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="becomeRenterModalLabel">Become a Renter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to switch to Renter mode?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" action="">
                        <button type="submit" name="become_renter" class="btn btn-primary">Switch to Renter</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add the role change functionality for "Become a Renter" button
        document.getElementById('becomeRenterBtn').addEventListener('click', function(e) {
            e.preventDefault();
            // Show the modal to confirm the role change
            $('#becomeRenterModal').modal('show');
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
