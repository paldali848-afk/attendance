<?php
require_once 'config.php';

// Log logout time if the table exists
if (isLoggedIn()) {
    try {
        if (tableExists('login_logs')) {
            $stmt = $pdo->prepare("UPDATE login_logs SET logout_time = NOW() WHERE user_id = ? AND logout_time IS NULL ORDER BY login_time DESC LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
        }
    } catch (PDOException $e) {
        // Ignore if the table or columns are unavailable
    }
}

// Clear session
session_destroy();

// Redirect to login
redirect('index.php');
?>