<?php
// logout.php

session_start(); // Start the session

// Unset all session variables
$_SESSION = [];
unset($_SESSION['admin_id']);


// Destroy the session
session_destroy();

// Optionally, delete the session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to the login page
header("Location: ../login.php"); // Adjust the path if needed
exit();
?>