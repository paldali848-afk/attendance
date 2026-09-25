<?php
// ============================================
// USER REQUESTS FUNCTIONS
// ============================================

if (!function_exists('getUserRequests')) {
    function getUserRequests($pdo, $user_id) {
        $requests = [];
        
        // Get late exception requests
        try {
            $stmt = $pdo->prepare("SELECT id, user_id, date, reason, status, remarks, exception_type as request_type, approved_at, approved_by, created_at FROM late_exception_requests WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $late_requests = $stmt->fetchAll();
            if (!empty($late_requests)) {
                $requests = array_merge($requests, $late_requests);
            }
        } catch (PDOException $e) {
            // Table might not exist, ignore
        }
        
        // Get leave requests
        try {
            $stmt = $pdo->prepare("SELECT id, user_id, start_date, end_date, date, reason, status, remarks, request_type, approved_at, approved_by, created_at FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $leave_requests = $stmt->fetchAll();
            if (!empty($leave_requests)) {
                $requests = array_merge($requests, $leave_requests);
            }
        } catch (PDOException $e) {
            // Table might not exist, ignore
        }
        
        // Get roster requests
        try {
            $stmt = $pdo->prepare("SELECT id, user_id, start_date, end_date, date, start_time, end_time, working_hours, reason, status, remarks, approved_at, approved_by, created_at FROM roster_requests WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $roster_requests = $stmt->fetchAll();
            if (!empty($roster_requests)) {
                $requests = array_merge($requests, $roster_requests);
            }
        } catch (PDOException $e) {
            // Table might not exist, ignore
        }
      
        // 4. ADD THIS: Get Overtime requests
    try {
        $stmt = $pdo->prepare("SELECT id, user_id, date, reason, status, ot_hours, 'overtime' as request_type, created_at FROM overtime_requests WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $ot_requests = $stmt->fetchAll();
        
        // Custom formatting for OT to show hours in the type label
        foreach ($ot_requests as &$ot) {
            $ot['request_type'] = 'Overtime (' . $ot['ot_hours'] . 'h)';
        }
        $requests = array_merge($requests, $ot_requests);
    } catch (PDOException $e) {}


        // Sort by created_at or date
        usort($requests, function ($a, $b) {
            $timeA = strtotime($a['created_at'] ?? $a['date'] ?? 'now');
            $timeB = strtotime($b['created_at'] ?? $b['date'] ?? 'now');
            return $timeB - $timeA;
        });

        return $requests;
    }
}
?>