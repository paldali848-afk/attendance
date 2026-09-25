<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user_id exists in session
if (!isset($_SESSION['user_id'])) {
    // If not, the session has timed out or user never logged in
    header("Location: index.php"); // Change this to your actual login filename if different
    exit();
}

// OPTIONAL: Active Session Timeout (e.g., logout after 30 mins of inactivity)
$timeout_duration = 1800; // 1800 seconds = 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    session_unset();
    session_destroy();
    header("Location: index.php?reason=timeout");
    exit();
}
$_SESSION['last_activity'] = time(); // Update activity timestamp