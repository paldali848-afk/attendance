<?php
// ============================================
// SAFE OVERTIME REQUEST HANDLER
// ============================================

// ============================================
// DIAGNOSTIC OVERTIME REQUEST HANDLER
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_overtime') {
    
    // Ensure the functions file is loaded
    // Check if the file exists first to avoid crash
     $stats_file = '/srv/www/htdocs/attendance/includes/modules/attendance/attendance_stats.php';
    if (file_exists($stats_file)) {
        require_once $stats_file;
    } else {
        die("Error: attendance_stats.php not found at " . $stats_file);
    }

    $u_id = $_SESSION['user_id'] ?? 0;
    $p_id = $_SESSION['person_id'] ?? '';
    $u_role = $_SESSION['role'] ?? 'agent'; 
    $date = $_POST['date'] ?? '';
    $w_hours = (float)($_POST['work_hours'] ?? 0);
    $o_hours = (int)($_POST['ot_hours'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    try {
        // 1. Verify eligibility function exists
        if (!function_exists('getOTEligibility')) {
            throw new Exception("Function getOTEligibility is missing. Check attendance_stats.php");
        }

        $check = getOTEligibility($pdo, $w_hours, $date, $u_role, true);

        if (!$check['eligible']) {
            throw new Exception($check['reason']);
        }

        // 2. Check for existing record
        $stmt = $pdo->prepare("SELECT id FROM overtime_requests WHERE user_id = ? AND date = ?");
        $stmt->execute([$u_id, $date]);
        if ($stmt->fetch()) {
            throw new Exception("OT request already submitted for this date.");
        }

        // 3. Calculate expiry
        $v_until = getOTExpiryDate($pdo, $date);

        // 4. Safe Insert (Checks if column exists first)
        // Check if 'valid_until' column exists in your table
        $column_check = $pdo->query("SHOW COLUMNS FROM overtime_requests LIKE 'valid_until'");
        $has_column = $column_check->fetch();

        if ($has_column) {
            $sql = "INSERT INTO overtime_requests (user_id, person_id, date, work_hours, ot_hours, reason, status, valid_until, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())";
            $params = [$u_id, $p_id, $date, $w_hours, $o_hours, $reason, $v_until];
        } else {
            // Fallback if you haven't added the column yet
            $sql = "INSERT INTO overtime_requests (user_id, person_id, date, work_hours, ot_hours, reason, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
            $params = [$u_id, $p_id, $date, $w_hours, $o_hours, $reason];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $_SESSION['message'] = ['type' => 'success', 'text' => '? OT request submitted successfully!'];

    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Submission Error: ' . $e->getMessage()];
    }

    header('Location: dashboard.php');
    exit;
}
?>