<?php
// ============================================
// OVERTIME APPROVAL HANDLER (Process Head Only)
// ============================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'approve_ot') {
    header('Location: dashboard.php');
    exit;
}

// Only process head can approve
if (!in_array($user['role'] ?? '', ['process_head', 'centre_head'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Unauthorized! Only Process Head can approve OT.'];
    header('Location: dashboard.php');
    exit;
}

$ot_id = (int)($_POST['ot_id'] ?? 0);
$status = $_POST['status'] ?? '';
$rejection_reason = trim($_POST['rejection_reason'] ?? '');

if (!$ot_id || !in_array($status, ['approved', 'rejected'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid request.'];
    header('Location: dashboard.php');
    exit;
}

try {
    if ($status === 'approved') {
        $stmt = $pdo->prepare("
            UPDATE overtime_requests 
            SET status = 'approved', approved_at = NOW(), approved_by = ? 
            WHERE id = ?
        ");
        $stmt->execute([$user['id'], $ot_id]);
        
        // Get the OT request details for notification
        $stmt_get = $pdo->prepare("SELECT user_id, date, ot_hours FROM overtime_requests WHERE id = ?");
        $stmt_get->execute([$ot_id]);
        $ot_data = $stmt_get->fetch();
        
        if ($ot_data) {
            // Check if ot_approved column exists
            try {
                $check_col = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'ot_approved'");
                if ($check_col->rowCount() > 0) {
                    // Update attendance with OT approval
                    $stmt_att = $pdo->prepare("UPDATE attendance SET ot_approved = 1, ot_hours = ? WHERE person_id = ? AND date = ?");
                    $stmt_att->execute([$ot_data['ot_hours'], $user['person_id'], $ot_data['date']]);
                } else {
                    // Column doesn't exist, skip attendance update
                    error_log("ot_approved column not found in attendance table");
                }
            } catch (Exception $e) {
                error_log("Error updating attendance OT: " . $e->getMessage());
            }
            
            // Send notification
            $msg = '✅ Your Overtime request for ' . date('M d, Y', strtotime($ot_data['date'])) . ' has been approved. (' . $ot_data['ot_hours'] . ' hours OT)';
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, 'OT Approved', ?, 'ot', 0, NOW())");
            $stmt_notif->execute([$ot_data['user_id'], $msg]);
        }
        
        $_SESSION['message'] = ['type' => 'success', 'text' => '✅ OT request approved successfully!'];
    } else {
        $stmt = $pdo->prepare("
            UPDATE overtime_requests 
            SET status = 'rejected', rejected_at = NOW(), rejection_reason = ? 
            WHERE id = ?
        ");
        $stmt->execute([$rejection_reason, $ot_id]);
        $_SESSION['message'] = ['type' => 'success', 'text' => '❌ OT request rejected.'];
    }
} catch (Exception $e) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
}

header('Location: dashboard.php');
exit;
?>