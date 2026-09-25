<?php
// ============================================
// LEAVE REQUEST HANDLER - DIRECT TO PROCESS HEAD
// ============================================

$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$leave_type = $_POST['leave_type'] ?? 'leave';
$reason = trim($_POST['reason'] ?? '');

if (empty($start_date) || empty($end_date)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Please select both start and end dates.'];
    header("Location: dashboard.php");
    exit();
}

if (empty($reason)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Please provide a reason for the leave request.'];
    header("Location: dashboard.php");
    exit();
}

try {
    // ============================================
    // DIRECT TO PROCESS HEAD - NO HIERARCHY
    // ============================================
    
    // Get Process Head directly from database
   $approver_id = null;
    $approver_name = '';

    if ($user['role'] === 'centre_head') {
        // 1. Centre Head: Approves their own requests
        $approver_id = $user['id'];
        $approver_name = $user['full_name'];
    } 
    elseif ($user['role'] === 'process_head') {
        // 2. Process Head applies: Must go to Centre Head
        $ch_stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'centre_head' AND is_active = 1 LIMIT 1");
        $ch_stmt->execute();
        $centre_head = $ch_stmt->fetch();
        
        if ($centre_head) {
            $approver_id = $centre_head['id'];
            $approver_name = $centre_head['full_name'];
        }
    } 
    else {
        // 3. Regular Employees/TLs/Managers: Go to the Process Head of their process
        $ph_stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'process_head' AND process = ? AND is_active = 1 LIMIT 1");
        $ph_stmt->execute([$user['process'] ?? '']);
        $process_head = $ph_stmt->fetch();

        if ($process_head) {
            $approver_id = $process_head['id'];
            $approver_name = $process_head['full_name'];
        } else {
            // Fallback: If no Process Head is found for that process, send to Centre Head
            $ch_stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'centre_head' AND is_active = 1 LIMIT 1");
            $ch_stmt->execute();
            $centre_head = $ch_stmt->fetch();
            if ($centre_head) {
                $approver_id = $centre_head['id'];
                $approver_name = $centre_head['full_name'];
            }
        }
    }

    // Safety check: If still no approver found, route to HR or show error
    if (!$approver_id) {
        $hr_stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'hr' AND is_active = 1 LIMIT 1");
        $hr_stmt->execute();
        $hr = $hr_stmt->fetch();
        if ($hr) {
            $approver_id = $hr['id'];
            $approver_name = $hr['full_name'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Routing Error: No Process Head or Centre Head found. Please contact Admin.'];
            header("Location: dashboard.php");
            exit();
        }
    }    
    // ============================================
    // END DIRECT APPROVER
    // ============================================

    $start = new DateTime($start_date);
    $end = new DateTime($end_date);

    if ($start > $end) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Start date must be before or equal to end date.'];
        header("Location: dashboard.php");
        exit();
    }

    $interval = $start->diff($end);
    $days_count = $interval->days + 1;

    // --- PL BALANCE CHECK ---
    if ($leave_type === 'pl') {
        $check_bal = $pdo->prepare("SELECT pl_balance FROM users WHERE id = ?");
        $check_bal->execute([$user['id']]);
        $current_balance = $check_bal->fetchColumn();

        if ($current_balance < $days_count) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Insufficient PL balance. You have ' . $current_balance . ' days left.'];
            header("Location: dashboard.php");
            exit();
        }
    }

    // Check for pending request
    $check_pending = $pdo->prepare("SELECT id FROM leave_requests WHERE user_id = ? AND start_date = ? AND end_date = ? AND status = 'pending'");
    $check_pending->execute([$user['id'], $start_date, $end_date]);
    if ($check_pending->fetch()) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'You already have a pending request for this date range.'];
        header("Location: dashboard.php");
        exit();
    }

    // Check for approved requests
    $current = clone $start;
    while ($current <= $end) {
        $date = $current->format('Y-m-d');
        $check_approved = $pdo->prepare("SELECT id FROM leave_requests WHERE user_id = ? AND date = ? AND status = 'approved'");
        $check_approved->execute([$user['id'], $date]);
        if ($check_approved->fetch()) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Some dates in this range already have approved requests.'];
            header("Location: dashboard.php");
            exit();
        }
        $current->modify('+1 day');
    }

    // ============================================
// SANDWICH LEAVE DETECTION - CORRECTED
// Rules:
//   - Week-off = Sunday only
//   - Sandwich case 1: Fri leave + Sat + Sun + Mon leave  ? Sat, Sun, Mon = sandwich
//   - Sandwich case 2: Fri leave + Sat + Sun + Tue leave (Mon is PH) ? Sat, Sun, Tue = sandwich
//   - If Sat is PH ? NO sandwich (chain broken)
//   - If Sun is PH ? NO sandwich (chain broken)
// ============================================

// 1. Get PH dates in range + 3 days buffer
$ph_dates = [];
$ph_stmt = $pdo->prepare("
    SELECT holiday_date FROM holidays 
    WHERE holiday_date BETWEEN DATE_SUB(?, INTERVAL 3 DAY) 
                           AND DATE_ADD(?, INTERVAL 3 DAY)
");
$ph_stmt->execute([$start_date, $end_date]);
while ($row = $ph_stmt->fetch()) {
    $ph_dates[$row['holiday_date']] = true;
}

// 2. Build set of leave dates from THIS request
$leave_dates = [];
$iter = clone $start;
while ($iter <= $end) {
    $leave_dates[$iter->format('Y-m-d')] = true;
    $iter->modify('+1 day');
}

// 3. Pull existing absent/leave days from attendance (merged with this request)
$off_days = $leave_dates;
$att_stmt = $pdo->prepare("
    SELECT date FROM attendance 
    WHERE person_id = ? 
      AND date BETWEEN DATE_SUB(?, INTERVAL 3 DAY) 
                    AND DATE_ADD(?, INTERVAL 3 DAY)
      AND (
          status IN ('A','PL','HPL','LWP','SW') 
          OR check_in IS NULL 
          OR check_in = '00:00:00'
      )
");
$att_stmt->execute([$user['person_id'], $start_date, $end_date]);
while ($row = $att_stmt->fetch()) {
    $off_days[$row['date']] = true;
}

$sandwich_dates = [];  // associative: date => true
$sandwich_leave_detected = false;

// 4. Scan each Saturday around the leave range
$scan = clone $start;
$scan->modify('-3 days');
$scan_end = clone $end;
$scan_end->modify('+3 days');

while ($scan <= $scan_end) {
    $dow = (int)date('N', strtotime($scan->format('Y-m-d')));

    if ($dow !== 6) { // 6 = Saturday
        $scan->modify('+1 day');
        continue;
    }

    $sat = $scan->format('Y-m-d');
    $sun = date('Y-m-d', strtotime($sat . ' +1 day'));
    $mon = date('Y-m-d', strtotime($sat . ' +2 days'));
    $fri = date('Y-m-d', strtotime($sat . ' -1 day'));
    $tue = date('Y-m-d', strtotime($sat . ' +3 days'));

    // Must have leave on Friday (before Sat) ? otherwise no sandwich
    if (!isset($off_days[$fri])) {
        $scan->modify('+1 day');
        continue;
    }

    $sat_is_ph = isset($ph_dates[$sat]);
    $sun_is_ph = isset($ph_dates[$sun]);
    $mon_is_ph = isset($ph_dates[$mon]);

    // RULE: If Sat is PH ? chain broken ? no sandwich
    if ($sat_is_ph) {
        $scan->modify('+1 day');
        continue;
    }

    // RULE: If Sun is PH ? chain broken ? no sandwich
    if ($sun_is_ph) {
        $scan->modify('+1 day');
        continue;
    }

    if (!$mon_is_ph) {
        // --- Case 1: Normal sandwich (Fri + Sat + Sun + Mon leave) ---
        if (isset($off_days[$mon])) {
            $sandwich_dates[$sat] = true;
            $sandwich_dates[$sun] = true;
            $sandwich_dates[$mon] = true;
            $sandwich_leave_detected = true;
        }
    } else {
        // --- Case 2: Monday is PH ? check Tuesday bridge ---
        // Only sandwich if Tuesday is also leave
        if (isset($off_days[$tue])) {
            $sandwich_dates[$sat] = true;
            $sandwich_dates[$sun] = true;
            $sandwich_dates[$tue] = true;
            // Mon stays as PH — NOT included
            $sandwich_leave_detected = true;
        }
        // If Tue is NOT leave ? no sandwich ? ignore (matches your requirement)
    }

    $scan->modify('+1 day');
}

// Convert to simple list for in_array() checks later
$sandwich_dates_list = array_keys($sandwich_dates);

    // ============================================
    // PL DEDUCTION WITH SANDWICH
    // ============================================
    $total_deduction = 0;
    if ($leave_type === 'pl') {
        $total_deduction = $days_count;
        foreach ($sandwich_dates_list as $sw_date) {
    if ($sw_date < $start_date || $sw_date > $end_date) {
        $total_deduction++;
    }
}

        $check_bal = $pdo->prepare("SELECT pl_balance FROM users WHERE id = ?");
        $check_bal->execute([$user['id']]);
        $current_balance = (float)$check_bal->fetchColumn();

        if ($current_balance < $total_deduction) {
            $_SESSION['message'] = [
                'type' => 'error',
                'text' => 'Insufficient PL balance. You need ' . $total_deduction 
                        . ' day(s) including sandwich, but have ' . $current_balance . '.'
            ];
            header("Location: dashboard.php");
            exit();
        }
    }

    // Insert request with the determined approver
// ============================================
// BUILD NOTIFICATION MESSAGE
// ============================================
$type_display       = ucfirst(str_replace('_', ' ', $leave_type));
$date_range_display = date('M d, Y', strtotime($start_date)) 
                    . ' to ' 
                    . date('M d, Y', strtotime($end_date));

$notif_message = $user['full_name'] 
               . ' has requested ' . $type_display 
               . ' from ' . $date_range_display 
               . ' (' . $days_count . ' day' . ($days_count > 1 ? 's' : '') . ').'
               . ($sandwich_leave_detected ? ' Note: Sandwich leave detected.' : '')
               . ' Reason: ' . $reason;

// ============================================
// INSERT LEAVE REQUEST
// ============================================
$ins = $pdo->prepare("
    INSERT INTO leave_requests 
        (user_id, person_id, start_date, end_date, date, reason, 
         request_type, status, approved_by, is_sandwich_leave, sandwich_dates) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
");

$ins->execute([
    $user['id'],
    $user['person_id'],
    $start_date,
    $end_date,
    $start_date,                          // 'date' column — single date field
    $reason,
    $leave_type,                          // request_type
    $approver_id,                         // approved_by
    $sandwich_leave_detected ? 1 : 0,     // is_sandwich_leave
    !empty($sandwich_dates_list) 
        ? implode(',', $sandwich_dates_list) 
        : null                            // sandwich_dates
]);

$request_id = $pdo->lastInsertId();

// ============================================
// SEND NOTIFICATION TO APPROVER
// ============================================
$notif_stmt = $pdo->prepare("
    INSERT INTO notifications 
        (user_id, title, message, type, is_read, created_at) 
    VALUES (?, ?, ?, ?, 0, NOW())
");

$notif_stmt->execute([
    $approver_id,
    'New ' . $type_display . ' Request',
    $notif_message,
    'leave'
]);

    // Auto-approve if Centre Head (their own requests)
    if ($user['role'] === 'centre_head' && $approver_id == $user['id']) {
        $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'approved', approved_at = NOW() WHERE id = ?");
        $stmt->execute([$request_id]);

        $status_to_set = '';
        $days_to_deduct = 0;

        if ($leave_type === 'leave') {
            $status_to_set = 'LWP';
            $days_to_deduct = 0;
         } elseif ($leave_type === 'pl') {
            $status_to_set = 'PL';
            $days_to_deduct = $total_deduction;   // ? Was $days_count
        } 
        // DEDUCT PL BALANCE ONLY FOR PL
        if ($days_to_deduct > 0) {
            $upd = $pdo->prepare("UPDATE users SET pl_balance = pl_balance - ? WHERE id = ?");
            $upd->execute([$days_to_deduct, $user['id']]);
        }
        
        // Update attendance for each day
        $current = clone $start;
        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $day_status = $status_to_set;
            
            // Check if this date is a sandwich date
           if (isset($sandwich_dates[$date])) {
    $day_status = 'SW'; // Sandwich Leave
}
                    // Mark external sandwich days (outside the range)
        foreach ($sandwich_dates_list as $sw_date) {
            if ($sw_date >= $start_date && $sw_date <= $end_date) continue;
            
            $stmt = $pdo->prepare("
                INSERT INTO attendance (person_id, date, status, check_in, check_out, work) 
                VALUES (?, ?, 'SW', '00:00:00', '00:00:00', '0.0') 
                ON DUPLICATE KEY UPDATE status = 'SW'
            ");
            $stmt->execute([$user['person_id'], $sw_date]);
        }
            $stmt = $pdo->prepare("INSERT INTO attendance (person_id, date, status, check_in, check_out, work) 
                                   VALUES (?, ?, ?, '00:00:00', '00:00:00', '0.0') 
                                   ON DUPLICATE KEY UPDATE status = ?");
            $stmt->execute([$user['person_id'], $date, $day_status, $day_status]);
            $current->modify('+1 day');
        }

        $_SESSION['message'] = ['type' => 'success', 'text' => $type_display . ' request auto-approved for ' . $days_count . ' day(s).' . ($sandwich_leave_detected ? ' Sandwich leave detected (Sat-Mon).' : '')];
    } else {
        // Show who it was sent to
        if ($user['role'] === 'centre_head') {
            $_SESSION['message'] = ['type' => 'success', 'text' => $type_display . ' request submitted successfully.' . ($sandwich_leave_detected ? ' (Sandwich leave detected on Sat-Mon)' : '')];
        } else {
            $_SESSION['message'] = ['type' => 'success', 'text' => $type_display . ' request submitted successfully to ' . $approver_name . ' for approval.' . ($sandwich_leave_detected ? ' (Sandwich leave detected on Sat-Mon)' : '')];
        }
    }
    
} catch (PDOException $e) {
    error_log("Leave Request Error: " . $e->getMessage());
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
}

header("Location: dashboard.php");
exit();
?>