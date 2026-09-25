<?php
// delete_user.php
require_once 'config.php';

// Enable error reporting to see the actual error if it fails again
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Security Check
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'centre_head')) {
    header("Location: dashboard.php?error=unauthorized");
    exit();
}

if (isset($_GET['id'])) {
    $target_id = (int)$_GET['id'];
    $current_user = getCurrentUser();

    // 2. Prevent self-deletion
    if ($target_id === (int)$current_user['id']) {
        header("Location: user_management.php?error=You cannot delete your own account.");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 3. Get person_id before deleting anything (needed for attendance table)
        $stmt = $pdo->prepare("SELECT person_id FROM users WHERE id = ?");
        $stmt->execute([$target_id]);
        $user_data = $stmt->fetch();
        $person_id = $user_data['person_id'] ?? null;

        // 4. DEEP CLEAN: Delete from all related tables
        // These are the tables likely causing the Foreign Key Constraint error
        
        // Delete Leave Requests
        $pdo->prepare("DELETE FROM leave_requests WHERE user_id = ?")->execute([$target_id]);
        
        // Delete OT Requests
        $pdo->prepare("DELETE FROM overtime_requests WHERE user_id = ?")->execute([$target_id]);
        
        // Delete Roster Requests
        $pdo->prepare("DELETE FROM roster_requests WHERE user_id = ?")->execute([$target_id]);
        
        // Delete Exception Requests
        $pdo->prepare("DELETE FROM late_exception_requests WHERE user_id = ?")->execute([$target_id]);
        
        // Delete Notifications
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$target_id]);
        
        // Delete Login Logs
        $pdo->prepare("DELETE FROM login_logs WHERE user_id = ?")->execute([$target_id]);

        // 5. Delete Attendance (Usually linked via person_id)
        if ($person_id) {
            $pdo->prepare("DELETE FROM attendance WHERE person_id = ?")->execute([$person_id]);
        }

        // 6. Update Hierarchy (Set reports to NULL for subordinates)
        $pdo->prepare("UPDATE users SET reporting_to = NULL WHERE reporting_to = ?")->execute([$target_id]);

        // 7. Finally, delete the user from the users table
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$target_id]);

        $pdo->commit();
        
        // Redirect back to your User Management page
        header("Location: users.php?message=User and all associated data deleted successfully.");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // This will print the actual error if it fails
        die("Fatal Error: " . $e->getMessage());
    }
} else {
    header("Location: users.php");
    exit();
}