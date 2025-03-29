<?php
// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: /rb/login.php");
    exit();
}

// Get staff information from session
$staff_name = $_SESSION['username'] ?? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$staff_role = $_SESSION['role'];
?>

<div class="px-3 d-flex py-4 shadow align-items-center justify-content-between" style="background-color: #1F4529;">
    <div class="d-flex align-items-center gap-2">
        <img class="" src="../images/rb logo white.png" alt="Logo" height="50px">
        <h1 class="fs-2 fw-bold text-white me-5 p-0 my-0" id="">RentBox POS</h1>
    </div> 
    
    <nav role="navigation" class="mx-auto">
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a href="staff_dashboard.php" class="nav-link fs-6" aria-current="page">
                    <i class="bi bi-clipboard2-data me-2"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="gadgets.php" class="nav-link link-light fs-6">
                    <i class="bi bi-gift me-2"></i>
                    Gadgets
                </a>
            </li>
            <li>
                <a href="customers.php" class="nav-link link-light fs-6">
                    <i class="bi bi-person-rolodex me-2"></i>
                    Customers
                </a>
            </li>
            <li>
                <a href="transactions.php" class="nav-link link-light fs-6">
                    <i class="bi bi-calendar-heart me-2"></i>
                    Transactions
                </a>
            </li>
            <li>
                <a href="receipts.php" class="nav-link link-light fs-6">
                    <i class="bi bi-calendar-heart me-2"></i>
                    Transactions
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="dropdown">
        <button class="btn btn-outline-light d-flex align-items-center justify-content-center text-decoration-none gap-2" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
            <p class="m-0 p-0"><?php echo htmlspecialchars($staff_name); ?></p>
            <img src="../uploads/profile_pictures/" width="40" height="40" class="rounded-circle border shadow-sm">
        </button>
        <ul class="dropdown-menu dropdown-menu text-small shadow" aria-labelledby="dropdownUser">
            <li><p class="dropdown-item m-0"><?php echo htmlspecialchars($staff_role); ?></p></li>
            <li>
                <hr class="dropdown-divider">
            </li>
            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
            <li><a class="dropdown-item" href="contact_admin.php">Contact Admin</a></li>
            <li>
                <hr class="dropdown-divider">
            </li>
            <li><a class="dropdown-item" href="report_issue.php">Report Issue</a></li>
            <li><a class="dropdown-item" href="../includes/logout.php">Sign Out</a></li>
        </ul>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            const linkPath = new URL(link.href).pathname;
            if (currentPage === linkPath) {
                link.classList.add('bg-body', 'fw-bold', 'link-success');
                link.classList.remove('link-light');
            } else {
                link.classList.remove('link-success', 'fw-bold');
                link.classList.add('link-light');
            }
        });
    });
</script>