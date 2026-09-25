<?php
// ============================================
// RESIGNATION FUNCTIONS
// ============================================

/**
 * Submit a new resignation request
 */
function submitResignation($pdo, $user_id, $data) {
    try {
        // Check if user already has pending resignation
        if (hasPendingResignation($pdo, $user_id)) {
            return ['success' => false, 'message' => 'You already have a pending resignation request.'];
        }

        // Get user details
        $stmt = $pdo->prepare("SELECT full_name, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        $sql = "INSERT INTO resignation_requests (
            user_id, employee_name, position, company_name, 
            resignation_date, last_working_date, reason, status,
            approval_level
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'process_head')";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $user_id,
            $data['employee_name'],
            $data['position'],
            $data['company_name'],
            $data['resignation_date'],
            $data['last_working_date'],
            $data['reason']
        ]);

        if ($result) {
            $resignation_id = $pdo->lastInsertId();
            
            // Send notifications to Process Head
            sendResignationNotifications($pdo, $resignation_id, $user_id, 'process_head');
            
            return ['success' => true, 'message' => 'Resignation submitted successfully! Waiting for Process Head approval.', 'id' => $resignation_id];
        }

        return ['success' => false, 'message' => 'Failed to submit resignation.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Check if user has pending resignation
 */
function hasPendingResignation($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM resignation_requests WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Get user's resignation details
 */
function getUserResignation($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT r.*, 
            ph.full_name as process_head_name,
            a.full_name as hr_name
            FROM resignation_requests r
            LEFT JOIN users ph ON r.process_head_approved_by = ph.id
            LEFT JOIN users a ON r.approved_by = a.id
            WHERE r.user_id = ? 
            ORDER BY r.id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Check if user is the designated HR approver (FNM5735) - ONLY USERNAME CHECK, NO ROLE CHECK
 */
function isDesignatedHrApprover($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // ONLY check username, NO role check
    if ($user && ($user['username'] === 'FNM5735' || $user['username'] === 'FNM5735')) {
        return true;
    }
    return false;
}

/**
 * Check if user is Process Head (can approve)
 */
function isProcessHead($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    return in_array($user['role'] ?? '', ['process_head', 'manager']);
}

/**
 * Get resignations with filters and approval level check
 */
function getResignations($pdo, $filters = [], $user_role = '', $user_id = 0) {
    // 1. Fetch user details specifically for permission checking
    $stmt_u = $pdo->prepare("SELECT username, department FROM users WHERE id = ?");
    $stmt_u->execute([$user_id]);
    $user_info = $stmt_u->fetch(PDO::FETCH_ASSOC);
    
    $current_username = $user_info['username'] ?? '';
    // Use strtolower to make the check case-insensitive
    $current_dept = strtolower(trim($user_info['department'] ?? ''));

    $sql = "SELECT r.*, u.full_name as user_name, u.role as user_role,
            a.full_name as approved_by_name, 
            rej.full_name as rejected_by_name,
            ph.full_name as process_head_name
            FROM resignation_requests r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN users a ON r.approved_by = a.id
            LEFT JOIN users rej ON r.rejected_by = rej.id
            LEFT JOIN users ph ON r.process_head_approved_by = ph.id
            WHERE 1=1";
    
    $params = [];
    
    // --- SUPER ACCESS CHECK ---
    $is_admin = ($user_role === 'admin');
    $is_fnm_special = ($current_username === 'FNM5735' || $current_username === 'FNM001');
    
    // This line specifically looks for ER or HR
    $is_hr_er_dept = ($current_dept === 'hr' || $current_dept === 'er' || $current_dept === 'human resources' || $current_dept === 'employee relations');

    // If NOT Admin, NOT special FNM, AND NOT in HR/ER department, then RESTRICT to team only
    if (!$is_admin && !$is_fnm_special && !$is_hr_er_dept) {
        $sql .= " AND (
            u.id = ? OR 
            u.reporting_to = ? OR 
            u.reporting_to IN (SELECT id FROM users WHERE reporting_to = ?) OR
            u.reporting_to IN (SELECT id FROM users WHERE reporting_to IN (SELECT id FROM users WHERE reporting_to = ?))
        )";
        $params[] = $user_id;
        $params[] = $user_id;
        $params[] = $user_id;
        $params[] = $user_id;
    }

    // ... rest of the filter and order by code ...
    if (!empty($filters['status'])) { $sql .= " AND r.status = ?"; $params[] = $filters['status']; }
    if (!empty($filters['search'])) { $sql .= " AND (r.employee_name LIKE ? OR r.position LIKE ?)"; $search = '%'.$filters['search'].'%'; $params[] = $search; $params[] = $search; }
    
    $sql .= " ORDER BY CASE WHEN r.status = 'pending' AND r.approval_level = 'process_head' THEN 1 WHEN r.status = 'pending' AND r.approval_level = 'hr' THEN 2 ELSE 3 END, r.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get pending resignations count for badge
 */
function getPendingResignations($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM resignation_requests WHERE status = 'pending'");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get user's pending approval count based on role
 */
function getUserPendingApprovalCount($pdo, $user_role, $user_id) {
    $sql = "SELECT COUNT(*) FROM resignation_requests WHERE status = 'pending'";
    $params = [];
    
    // Check if user is designated HR (FNM5735) - regardless of role
    if (isDesignatedHrApprover($pdo, $user_id)) {
        // FNM5735 can see both process_head and hr level pending
        return 0; // Let's count all pending
    } else if ($user_role === 'process_head' || $user_role === 'manager') {
        $sql .= " AND approval_level = 'process_head'";
    } else if (in_array($user_role, ['hr', 'centre_head', 'admin'])) {
        $sql .= " AND approval_level = 'hr'";
    } else {
        return 0;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/**
 * Update resignation status - Process Head Approve
 */
function processHeadApprove($pdo, $resignation_id, $approver_id, $comment = '') {
    try {
        $pdo->beginTransaction();
        
        // Check if user is Process Head
        if (!isProcessHead($pdo, $approver_id)) {
            throw new Exception('Only Process Head can perform this action.');
        }
        
        // Get current resignation
        $stmt = $pdo->prepare("SELECT * FROM resignation_requests WHERE id = ?");
        $stmt->execute([$resignation_id]);
        $resignation = $stmt->fetch();
        
        if (!$resignation) {
            throw new Exception('Resignation not found');
        }
        
        // Check if already approved by Process Head
        if ($resignation['approval_level'] !== 'process_head') {
            throw new Exception('This resignation is not waiting for Process Head approval.');
        }
        
        // Update - Process Head approved, now move to HR
        $sql = "UPDATE resignation_requests SET 
                status = 'pending',
                approval_level = 'hr',
                process_head_approved_by = ?,
                process_head_approved_date = NOW(),
                process_head_comment = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$approver_id, $comment, $resignation_id]);
        
        $pdo->commit();
        
        // Send notifications to HR
        sendResignationNotifications($pdo, $resignation_id, $resignation['user_id'], 'hr');
        
        // Send notification to employee about Process Head approval
        sendProcessHeadApprovalNotification($pdo, $resignation_id, $resignation['user_id'], $comment);
        
        return ['success' => true, 'message' => 'Resignation approved by Process Head. Now waiting for HR approval.'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Send Process Head approval notification to employee
 */
function sendProcessHeadApprovalNotification($pdo, $resignation_id, $user_id, $comment = '') {
    try {
        // Get employee details
        $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $employee = $stmt->fetch();
        
        // Get Process Head details
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = (SELECT process_head_approved_by FROM resignation_requests WHERE id = ?)");
        $stmt->execute([$resignation_id]);
        $process_head = $stmt->fetch();
        
        // Send email to employee
        $subject = "Resignation Update - Process Head Approval";
        $message = "Dear " . $employee['full_name'] . ",\n\n";
        $message .= "Your resignation has been approved by Process Head (" . ($process_head['full_name'] ?? 'Process Head') . ").\n";
        if (!empty($comment)) {
            $message .= "Comment: " . $comment . "\n";
        }
        $message .= "\nIt is now forwarded to HR for final approval.\n\n";
        $message .= "You can track the status in your dashboard.\n\n";
        $message .= "Regards,\nAttendance Management System";
        
        // mail($employee['email'], $subject, $message);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Update resignation status - HR Approve (Final) - Only for FNM5735 (NO ROLE CHECK)
 */
function hrApprove($pdo, $resignation_id, $approver_id, $comment = '') {
    try {
        $pdo->beginTransaction();
        
        // Check if user is the designated HR approver (FNM5735) - ONLY USERNAME CHECK
        if (!isDesignatedHrApprover($pdo, $approver_id)) {
            throw new Exception('Only the designated HR approver (FNM5735) can perform this action.');
        }
        
        // Get current resignation
        $stmt = $pdo->prepare("SELECT * FROM resignation_requests WHERE id = ?");
        $stmt->execute([$resignation_id]);
        $resignation = $stmt->fetch();
        
        if (!$resignation) {
            throw new Exception('Resignation not found');
        }
        
        // Check if Process Head has approved
        if ($resignation['approval_level'] !== 'hr') {
            throw new Exception('Process Head must approve this resignation first.');
        }
        
        // Final approval by HR
        $sql = "UPDATE resignation_requests SET 
                status = 'approved',
                approval_level = 'completed',
                approved_by = ?,
                approved_date = NOW(),
                hr_comment = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$approver_id, $comment, $resignation_id]);
        
        $pdo->commit();
        
        // Send notifications to employee
        sendFinalApprovalNotification($pdo, $resignation_id, $resignation['user_id'], $comment);
        
        return ['success' => true, 'message' => 'Resignation approved by HR. Employee will be notified.'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Send final approval notification to employee
 */
function sendFinalApprovalNotification($pdo, $resignation_id, $user_id, $comment = '') {
    try {
        // Get employee details
        $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $employee = $stmt->fetch();
        
        // Get HR details
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = (SELECT approved_by FROM resignation_requests WHERE id = ?)");
        $stmt->execute([$resignation_id]);
        $hr = $stmt->fetch();
        
        // Get resignation details
        $stmt = $pdo->prepare("SELECT last_working_date FROM resignation_requests WHERE id = ?");
        $stmt->execute([$resignation_id]);
        $res = $stmt->fetch();
        
        // Send email to employee
        $subject = "Resignation Approved - Final Confirmation";
        $message = "Dear " . $employee['full_name'] . ",\n\n";
        $message .= "Your resignation has been FINALLY APPROVED by HR (" . ($hr['full_name'] ?? 'HR') . ").\n";
        $message .= "Last Working Day: " . date('M d, Y', strtotime($res['last_working_date'])) . "\n";
        if (!empty($comment)) {
            $message .= "HR Comment: " . $comment . "\n";
        }
        $message .= "\nPlease complete your clearance process before the last working day.\n\n";
        $message .= "Regards,\nAttendance Management System";
        
        // mail($employee['email'], $subject, $message);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Reject resignation - Can be done by Process Head or Designated HR (FNM5735)
 */
function rejectResignation($pdo, $resignation_id, $rejecter_id, $reason = '') {
    try {
        $pdo->beginTransaction();
        
        // Get rejecter role and details
        $stmt = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
        $stmt->execute([$rejecter_id]);
        $rejecter = $stmt->fetch();
        $role = $rejecter['role'] ?? '';
        $username = $rejecter['username'] ?? '';
        
        // Get current resignation
        $stmt = $pdo->prepare("SELECT * FROM resignation_requests WHERE id = ?");
        $stmt->execute([$resignation_id]);
        $resignation = $stmt->fetch();
        
        if (!$resignation) {
            throw new Exception('Resignation not found');
        }
        
        // Check permissions for rejection
        $is_process_head = in_array($role, ['process_head', 'manager']);
        $is_designated_hr = ($username === 'FNM5735' || $username === 'FNM5735'); // ONLY username check
        
        // Only Process Head or Designated HR (FNM5735) can reject
        if (!$is_process_head && !$is_designated_hr) {
            throw new Exception('You do not have permission to reject this resignation.');
        }
        
        // Determine who can reject at what stage
        if ($is_process_head) {
            if ($resignation['approval_level'] !== 'process_head') {
                throw new Exception('This resignation is not waiting for Process Head approval.');
            }
            $comment_field = 'process_head_comment';
        } else if ($is_designated_hr) {
            if ($resignation['approval_level'] !== 'hr' && $resignation['approval_level'] !== 'process_head') {
                throw new Exception('This resignation cannot be rejected at this stage.');
            }
            $comment_field = 'hr_comment';
        } else {
            throw new Exception('You do not have permission to reject this resignation.');
        }
        
        $sql = "UPDATE resignation_requests SET 
                status = 'rejected',
                approval_level = 'rejected',
                rejected_by = ?,
                rejected_date = NOW(),
                $comment_field = ?,
                rejection_reason = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rejecter_id, $reason, $reason, $resignation_id]);
        
        $pdo->commit();
        
        // Send rejection notification to employee
        sendRejectionNotification($pdo, $resignation_id, $resignation['user_id'], $reason);
        
        return ['success' => true, 'message' => 'Resignation rejected successfully.'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Send rejection notification to employee
 */
function sendRejectionNotification($pdo, $resignation_id, $user_id, $reason = '') {
    try {
        // Get employee details
        $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $employee = $stmt->fetch();
        
        // Send email to employee
        $subject = "Resignation Update - Rejected";
        $message = "Dear " . $employee['full_name'] . ",\n\n";
        $message .= "Your resignation has been REJECTED.\n";
        if (!empty($reason)) {
            $message .= "Reason: " . $reason . "\n";
        }
        $message .= "\nPlease contact HR for more information.\n\n";
        $message .= "Regards,\nAttendance Management System";
        
        // mail($employee['email'], $subject, $message);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Send notifications based on recipient type
 */
function sendResignationNotifications($pdo, $resignation_id, $user_id, $recipient_type) {
    try {
        // Get employee details
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $employee = $stmt->fetch();
        
        if ($recipient_type === 'process_head') {
            // Send to Process Heads
            $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE role IN ('process_head', 'manager')");
            $stmt->execute();
            $approvers = $stmt->fetchAll();
            foreach ($approvers as $approver) {
                // mail($approver['email'], "New Resignation Request - " . $employee['full_name'], "A new resignation request needs your approval.");
            }
        } else if ($recipient_type === 'hr') {
            // Send to Designated HR (FNM5735) only - No role check
            $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE username = 'FNM5735' OR username = 'FNM5735'");
            $stmt->execute();
            $approvers = $stmt->fetchAll();
            foreach ($approvers as $approver) {
                // mail($approver['email'], "Resignation Ready for HR Approval - " . $employee['full_name'], "Process Head has approved the resignation. Please review and approve.");
            }
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get resignation statistics
 */
function getResignationStats($pdo, $user_id = 0, $user_role = '') {
    $stmt_u = $pdo->prepare("SELECT username, department FROM users WHERE id = ?");
    $stmt_u->execute([$user_id]);
    $user_info = $stmt_u->fetch(PDO::FETCH_ASSOC);
    
    $current_username = $user_info['username'] ?? '';
    $current_dept = strtolower(trim($user_info['department'] ?? ''));

    $is_admin = ($user_role === 'admin');
    $is_fnm_special = ($current_username === 'FNM5735' || $current_username === 'FNM001');
    $is_hr_er_dept = ($current_dept === 'hr' || $current_dept === 'er' || $current_dept === 'human resources' || $current_dept === 'employee relations');
    
    $params = [];
    $join_sql = "";
    $where_sql = "";

    // If NOT Super Access, apply hierarchy filter
    if (!$is_admin && !$is_fnm_special && !$is_hr_er_dept) {
        $join_sql = " JOIN users u ON r.user_id = u.id ";
        $where_sql = " WHERE (u.id = ? OR u.reporting_to = ? OR 
                        u.reporting_to IN (SELECT id FROM users WHERE reporting_to = ?) OR 
                        u.reporting_to IN (SELECT id FROM users WHERE reporting_to IN (SELECT id FROM users WHERE reporting_to = ?)))";
        $params = [$user_id, $user_id, $user_id, $user_id];
    }

    $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN r.status = 'pending' AND r.approval_level = 'process_head' THEN 1 ELSE 0 END) as p_ph,
                SUM(CASE WHEN r.status = 'pending' AND r.approval_level = 'hr' THEN 1 ELSE 0 END) as p_hr
              FROM resignation_requests r" . $join_sql . $where_sql;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'total' => (int)($res['total'] ?? 0),
        'pending' => (int)($res['pending'] ?? 0),
        'approved' => (int)($res['approved'] ?? 0),
        'rejected' => (int)($res['rejected'] ?? 0),
        'pending_process_head' => (int)($res['p_ph'] ?? 0),
        'pending_hr' => (int)($res['p_hr'] ?? 0)
    ];
}

/**
 * Check if user can approve based on role and current approval level
 */
function canApproveResignation($pdo, $resignation_id, $user_role, $user_id) {
    $stmt = $pdo->prepare("SELECT approval_level, status FROM resignation_requests WHERE id = ?");
    $stmt->execute([$resignation_id]);
    $resignation = $stmt->fetch();
    
    if (!$resignation || $resignation['status'] !== 'pending') {
        return ['can_approve' => false, 'message' => 'Resignation is not pending for approval.'];
    }
    
    $approval_level = $resignation['approval_level'];
    
    // Check if user is designated HR (FNM5735) - FIRST priority
    if (isDesignatedHrApprover($pdo, $user_id)) {
        // FNM5735 can approve at HR level
        if ($approval_level === 'hr') {
            return ['can_approve' => true, 'message' => 'HR approval allowed for designated approver (FNM5735).'];
        }
        // FNM5735 can also approve at Process Head level if they have Process Head role
        if (in_array($user_role, ['process_head', 'manager']) && $approval_level === 'process_head') {
            return ['can_approve' => true, 'message' => 'Process Head approval allowed for FNM5735.'];
        }
        if ($approval_level === 'process_head') {
            return ['can_approve' => false, 'message' => 'Process Head must approve this first, even for FNM5735.'];
        }
    }
    
    // Process Head can approve when approval_level is 'process_head'
    if (in_array($user_role, ['process_head', 'manager']) && $approval_level === 'process_head') {
        return ['can_approve' => true, 'message' => 'Process Head approval allowed.'];
    }
    
    return ['can_approve' => false, 'message' => 'You do not have permission to approve this resignation at this stage.'];
}

/**
 * Check user's permission level for resignations
 */
function getUserResignationPermission($pdo, $user_id, $user_role) {
    $result = [
        'role' => $user_role,
        'can_approve' => false,
        'can_reject' => false,
        'can_view_all' => false,
        'message' => ''
    ];
    
    // Check if designated HR approver (FNM5735) - FIRST priority
    if (isDesignatedHrApprover($pdo, $user_id)) {
        $result['can_approve'] = true;
        $result['can_reject'] = true;
        $result['can_view_all'] = true;
        $result['message'] = 'Designated HR Approver (FNM5735) - Can approve/reject resignations at HR level.';
        return $result;
    }
    
    // Process Head / Manager - can approve and reject at process_head level
    if (in_array($user_role, ['process_head', 'manager'])) {
        $result['can_approve'] = true;
        $result['can_reject'] = true;
        $result['can_view_all'] = true;
        $result['message'] = 'Process Head - Can approve/reject resignations at Process Head level.';
        return $result;
    }
    
    // Other HR / Centre Head / Admin - view only
    if (in_array($user_role, ['hr', 'centre_head', 'admin'])) {
        $result['can_view_all'] = true;
        $result['message'] = 'HR (View Only) - You can view all resignations but cannot approve or reject. Only FNM5735 can take action.';
        return $result;
    }
    
    // AM / TL - view only
    if (in_array($user_role, ['am', 'tl'])) {
        $result['can_view_all'] = true;
        $result['message'] = 'View Only - You can view all resignations but cannot take action.';
        return $result;
    }
    
    // Agent or other roles - limited view
    $result['can_view_all'] = false;
    $result['message'] = 'You do not have permission to view resignations.';
    return $result;
}
?>