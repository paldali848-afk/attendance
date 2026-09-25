<?php
// ============================================
// BIOMETRIC CHECK-IN PROCESSOR
// ============================================

function processBiometricCheckin($pdo, $person_id, $attendance_date, $check_in_time) {
    // 1. Get the roster for this person
    $stmt = $pdo->prepare("SELECT rr.start_time, rr.end_time, rr.working_hours 
                           FROM roster_requests rr 
                           JOIN users u ON rr.user_id = u.id 
                           WHERE u.person_id = ? 
                           AND rr.status = 'approved' 
                           AND ? BETWEEN rr.start_date AND rr.end_date
                           ORDER BY rr.created_at DESC LIMIT 1");
    $stmt->execute([$person_id, $attendance_date]);
    $roster = $stmt->fetch();
    
    $check_in_time_24 = date('H:i:s', strtotime($check_in_time));
    
    // 2. Check if there is an EXISTING approved status (PL, WO, Exception)
    // We don't want the biometric machine to wipe out an approved leave
    $stmt_exist = $pdo->prepare("SELECT status FROM attendance WHERE person_id = ? AND date = ?");
    $stmt_exist->execute([$person_id, $attendance_date]);
    $existing_record = $stmt_exist->fetch();
    $existing_status = $existing_record ? $existing_record['status'] : '';

   
   // List of statuses we NEVER want to overwrite with a simple punch
$protected_statuses = ['PL', 'WO', 'LV', 'FPE', 'Holiday', 'H', 'HPL', 'LWP'];

    if ($roster) {
        $start_time_24 = date('H:i:s', strtotime($roster['start_time']));
        
        // Calculate lateness
        $start_obj = new DateTime($start_time_24);
        $check_in_obj = new DateTime($check_in_time_24);
        $diff_minutes = ($check_in_obj->getTimestamp() - $start_obj->getTimestamp()) / 60;
        
        // INITIAL Status determination (Subject to change by sync script later)
        if ($diff_minutes <= 15) {
            $status = 'Present';
        } else {
            $status = 'Late';
        }

        // If the current status in DB is protected (like PL), keep it protected!
        if (in_array($existing_status, $protected_statuses)) {
            $status = $existing_status;
        }
        
        if ($existing_record) {
            // Update existing record
            $update_stmt = $pdo->prepare("UPDATE attendance 
                                          SET status = ?, check_in = ? 
                                          WHERE person_id = ? AND date = ?");
            $update_stmt->execute([$status, $check_in_time, $person_id, $attendance_date]);
        } else {
            // Create new record
            $insert_stmt = $pdo->prepare("INSERT INTO attendance (person_id, date, status, check_in, check_out, work) 
                                          VALUES (?, ?, ?, ?, ?, ?)");
            // Note: work is set to 0 initially. Sync script will calculate actual work later.
            $insert_stmt->execute([$person_id, $attendance_date, $status, $check_in_time, null, 0]);
        }
        
        return [
            'status' => $status, 
            'message' => "Punch recorded. Final status calculated after check-out.",
            'check_in' => $check_in_time,
            'diff_minutes' => round($diff_minutes)
        ];
    } else {
        // No roster found - treat as General Shift
        // Compare times using strtotime instead of PHP time() which accepts no arguments
        $status = (strtotime($check_in_time_24) > strtotime('09:45:00')) ? 'Late' : 'Present';
        
        if (in_array($existing_status, $protected_statuses)) { $status = $existing_status; }

        if ($existing_record) {
            $pdo->prepare("UPDATE attendance SET status = ?, check_in = ? WHERE person_id = ? AND date = ?")
                ->execute([$status, $check_in_time, $person_id, $attendance_date]);
        } else {
            $pdo->prepare("INSERT INTO attendance (person_id, date, status, check_in, work) VALUES (?, ?, ?, ?, 0)")
                ->execute([$person_id, $attendance_date, $status, $check_in_time]);
        }
        
        return ['status' => $status, 'message' => 'Regular punch updated'];
    }
}
// Handle AJAX/biometric device POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'biometric_checkin') {
    require_once __DIR__ . '/../../config/bootstrap.php';
    require_once __DIR__ . '/../../auth/authentication.php';
    
    $person_id = $_POST['person_id'] ?? '';
    $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
    $check_in_time = $_POST['check_in_time'] ?? date('H:i:s');
    
    if (empty($person_id)) {
        echo json_encode(['success' => false, 'message' => 'Person ID required']);
        exit();
    }
    
    $result = processBiometricCheckin($pdo, $person_id, $attendance_date, $check_in_time);
    echo json_encode(['success' => true, 'data' => $result]);
    exit();
}
?>