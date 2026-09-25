<?php
// ============================================
// APPROVAL FETCH FUNCTIONS - UPDATED
// ============================================

function getPendingApprovals($pdo, $user) {
    $user_id = $user['id'] ?? 0;
    $role = strtolower(trim($user['role'] ?? ''));
    $process = trim($user['process'] ?? '');

    // Initialize all categories
    $pending = [
        'exceptions' => [], 
        'leave' => [], 
        'roster' => [], 
        'overtime' => [], 
        'resignation' => []
    ];

    if (!$user_id) return $pending;

    $params = [];
    $where = "";

    // 1. CENTRE HEAD: Sees Process Heads
    if ($role === 'centre_head') {
        $where = "WHERE u.role = 'process_head' AND t.status = 'pending'";
        $params = [];
    } 
    // 2. PROCESS HEAD / HR: Sees ALL users in their process
    elseif ($role === 'process_head' || $role === 'hr') {
        $where = "WHERE TRIM(LOWER(u.process)) = TRIM(LOWER(:process)) AND u.id != :uid AND t.status = 'pending'";
        $params = [':process' => $process, ':uid' => $user_id];
    } 
    // 3. MANAGERS / TLs: Sees only their reports
    elseif (in_array($role, ['manager', 'am', 'tl'])) {
        $where = "WHERE u.reporting_to = :uid AND u.id != :uid AND t.status = 'pending'";
        $params = [':uid' => $user_id];
    } else {
        return $pending;
    }

    try {
        // Fetch Exceptions
        $sql1 = "SELECT t.*, u.full_name as requester_name, u.role as requester_role, 
                 t.exception_type AS req_type 
                 FROM late_exception_requests t JOIN users u ON t.user_id = u.id $where";
        $stmt1 = $pdo->prepare($sql1);
        $stmt1->execute($params);
        $pending['exceptions'] = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Leaves
        $sql2 = "SELECT t.*, u.full_name as requester_name, u.role as requester_role, 
                 t.leave_type AS req_type 
                 FROM leave_requests t JOIN users u ON t.user_id = u.id $where";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute($params);
        $pending['leave'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Rosters
        $sql3 = "SELECT t.*, u.full_name as requester_name, u.role as requester_role, 
                 'roster' AS req_type 
                 FROM roster_requests t JOIN users u ON t.user_id = u.id $where";
        $stmt3 = $pdo->prepare($sql3);
        $stmt3->execute($params);
        $pending['roster'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Overtime (Integrated into the same logic)
        $sql4 = "SELECT t.*, u.full_name as requester_name, u.role as requester_role, 
                 'overtime' AS req_type 
                 FROM overtime_requests t JOIN users u ON t.user_id = u.id $where";
        $stmt4 = $pdo->prepare($sql4);
        $stmt4->execute($params);
        $pending['overtime'] = $stmt4->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Resignations (Integrated into the same logic)
        $sql5 = "SELECT t.*, u.full_name as requester_name, u.role as requester_role, 
                 'resignation' AS req_type 
                 FROM resignation_requests t JOIN users u ON t.user_id = u.id $where";
        $stmt5 = $pdo->prepare($sql5);
        $stmt5->execute($params);
        $pending['resignation'] = $stmt5->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Fetch Error: " . $e->getMessage());
    }

    return $pending; // Return happens only once at the end
}
?>