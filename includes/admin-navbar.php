<?php
require_once __DIR__ . '/../db/db.php';

// Check admin session
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Fetch admin data
$admin_id = $_SESSION['id'];
$stmt = $conn->prepare("SELECT name, profile_picture FROM users WHERE id = :id AND role = 'admin'");
$stmt->execute([':id' => $admin_id]);
$admin = $stmt->fetch();

// Set default values
$admin_name = 'Administrator';
$profile_image = 'includes/default-profile.jpg';

if ($admin) {
    $admin_name = $admin['name'] ?? $admin_name;
    $profile_image = (!empty($admin['profile_picture'])) 
    ? '../' . $admin['profile_picture']  // Add relative path
    : $profile_image;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>RentBox Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .sidebar {
            height: 100vh;
            background: #f8f9fa;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            padding-top: 60px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        .sidebar a {
            display: block;
            padding: 15px 20px;
            font-size: 14px;
            color: #495057;
            text-decoration: none;
            transition: background 0.3s, color 0.3s;
        }
        .sidebar a.active,
        .sidebar a:hover {
            background: #e9ecef;
            color: #212529;
        }
        .sidebar input {
            margin: 10px 15px;
            border-radius: 20px;
            padding: 5px 15px;
        }
        .main-content {
            margin-left: 260px;
            padding: 20px;
        }
        .header {
            background: white;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .header img.logo {
            height: 40px;
        }
        .header .dropdown-menu {
            width: 280px;
        }
        .notification-drawer,
        .message-drawer {
            max-height: 300px;
            overflow-y: auto;
        }
        .profile-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
        }

        .sidebar {
    height: 100vh;
    background: #f8f9fa;
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    padding-top: 60px;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
}

.sidebar a {
    display: block;
    padding: 15px 20px;
    font-size: 14px;
    color: #495057;
    text-decoration: none;
    transition: background 0.3s, color 0.3s;
}

.sidebar a:hover, .sidebar a.active {
    background: #e9ecef;
    color: #212529;
}

/* Specific styles for Verification Confirmation */
.verification-toggle {
    padding-left: 20px;  /* Add indentation */
    font-weight: bold;  /* Make it stand out more */
    color: #007bff;  /* Make the text blue */
}

.verification-options {
    display: none; /* Hide options by default */
    margin-left: 20px; /* Indent the options under Verification Confirmation */
}

.verification-option {
    padding-left: 30px;  /* More indentation for the sub-options */
    color: #495057;  /* Darker text color */
    font-size: 13px;  /* Smaller text */
    transition: background-color 0.3s, color 0.3s;
}

.verification-option:hover {
    background-color: #e9ecef;  /* Light hover background */
    color: #007bff;  /* Change text color on hover */
}

/* Add active class to the parent when options are visible */
.verification-options.visible {
    display: block;  /* Show the options */
}

    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-secondary d-md-none me-3" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </button>
            <img src="includes/logo.png" alt="RentBox Logo" class="logo">
            <h4 class="ms-2">RentBox</h4>
        </div>
        <div class="d-flex align-items-center">
            <!-- Profile Dropdown -->
            <div class="dropdown">
            <img src="<?= htmlspecialchars($profile_image) ?>" 
     alt="Profile Picture" 
     class="profile-img me-2"
     onerror="this.src='../images/pfp.png'">

<span><?= htmlspecialchars($admin_name) ?></span>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a href="#" class="dropdown-item">Profile</a></li>
                    <li><a href="#" class="dropdown-item">Settings</a></li>
                    <li><a href="/admin/logout.php" class="dropdown-item text-danger">Log Out</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="mx-2">
            <input type="text" class="form-control mx-auto" placeholder="Search">
        </div>
        <a href="../admin/dashboard.php" class=""><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
        <a href="../admin/account-management.php"><i class="fas fa-user me-2"></i> Account Management</a>

        <!-- Verification Confirmation Section -->
        <a href="#" class="verification-toggle" id="verificationConfirmation">
            <i class="fas fa-check-circle me-2"></i> Verification Confirmation
        </a>
        <div class="verification-options" id="verificationOptions" style="display: none;">
            <a href="user_verification.php" class="verification-option"><i class="fas fa-user-check me-2"></i> User Verification</a>
            <a href="gadget_verification.php" class="verification-option"><i class="fas fa-clipboard-check me-2"></i> Gadget Verification</a> <!-- Changed icon here -->
        </div>

        <a href="../admin/gadget-management.php"><i class="fas fa-tablet-alt me-2"></i> Gadget Management</a>
        <a href="../admin/transactions.php"><i class="fas fa-exchange-alt me-2"></i> Transactions</a>
        <a href="../admin/review_disputes.php"><i class="fas fa-gavel me-2"></i> Dispute Management</a>
        <a href="../admin/analytics.php"><i class="fas fa-chart-line me-2"></i> Reports and Analytics</a>
        <a href="../admin/supports.php"><i class="fas fa-headset me-2"></i> Support</a>
        <a href="../admin/logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i> Log Out</a>
    </div>

    <script>
        // Get all sidebar links
        const sidebarLinks = document.querySelectorAll('.sidebar a');

        // Get the current URL path
        const currentPath = window.location.pathname;

        // Loop through each link and check if its href matches the current path
        sidebarLinks.forEach(link => {
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('active'); // Add 'active' class to the current page
            } else {
                link.classList.remove('active'); // Remove 'active' class from other links
            }
        });

        document.getElementById('verificationConfirmation').addEventListener('click', function() {
            const verificationOptions = document.getElementById('verificationOptions');
            const isVisible = verificationOptions.style.display === 'block';

            // Toggle the display of the options
            verificationOptions.style.display = isVisible ? 'none' : 'block';
        });
    </script>

</body>
</html>