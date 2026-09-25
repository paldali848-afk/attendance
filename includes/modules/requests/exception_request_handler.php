<?php
// ============================================
// EXCEPTION REQUEST HANDLER (FIXED VERSION)
// ============================================

$reason = trim($_POST['reason'] ?? '');
$exception_type = $_POST['exception_type'] ?? 'late';
$selected_dates_raw = $_POST['selected_dates'] ?? '';
$single_date = $_POST['date'] ?? '';

// 1. Validation
if (empty($reason)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Please provide a reason.'];
    header("Location: dashboard.php");
    exit();
}

// 2. Determine Dates
$dates_to_process = [];
if ($exception_type === 'pl_adjustment' && !empty($selected_dates_raw)) {
    $dates_to_process = explode(',', $selected_dates_raw);
} elseif (!empty($single_date)) {
    $dates_to_process = [$single_date];
}

if (empty($dates_to_process)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'No dates selected.'];
    header("Location: dashboard.php");
    exit();
}

try {
    $success_count = 0;
    $errors = [];

    // --- FIX: GET FRESH MANAGER ID FROM DB ---
   // --- UPDATED DYNAMIC APPROVAL ROUTING ---
    $approver_id = null;

    if ($user['role'] === 'centre_head') {
        // 1. Centre Head approves themselves (handled by auto-approval logic below)
        $approver_id = $user['id'];
    } 
    elseif ($user['role'] === 'process_head') {
        // 2. If a Process Head applies, send it to the Centre Head
        $stmt_ch = $pdo->prepare("SELECT id FROM users WHERE role = 'centre_head' LIMIT 1");
        $stmt_ch->execute();
        $approver_id = $stmt_ch->fetchColumn();
    } 
    else {
        // 3. For all other employees, send to the Process Head of THEIR specific process
        $stmt_ph = $pdo->prepare("SELECT id FROM users WHERE role = 'process_head' AND process = ? LIMIT 1");
        $stmt_ph->execute([$user['process'] ?? '']);
        $approver_id = $stmt_ph->fetchColumn();
        
        // Safety Fallback: If no Process Head is assigned to this process, route to Centre Head
        if (!$approver_id) {
            $stmt_fb = $pdo->prepare("SELECT id FROM users WHERE role = 'centre_head' LIMIT 1");
            $stmt_fb->execute();
            $approver_id = $stmt_fb->fetchColumn();
        }
    }

    // Block submission if no approver is found in the database at all
    if (!$approver_id) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Routing Error: No authorized approver (Process Head or Centre Head) found. Please contact Admin.'];
        header("Location: dashboard.php");
        exit();
    }    foreach ($dates_to_process as $current_date) {

    // Initialize extra_data as null for every loop iteration
    $extra_data = null;

    // --- NEW: WEEKOFF SPECIFIC VALIDATION ---
        if ($exception_type === 'weekoff_exception') {
        $worked_sunday = $_POST['worked_sunday'] ?? '';
        $desired_date = $current_date; // This is the date being processed in the loop

        if (empty($worked_sunday)) {
            $errors[] = "No Sunday selected.";
            continue;
        }

        $worked_sunday_ts = strtotime($worked_sunday);
        $desired_date_ts = strtotime($desired_date);

        // 1. RULE: Sunday and Week Off must be in the same month
        if (date('m-Y', $worked_sunday_ts) !== date('m-Y', $desired_date_ts)) {
            $errors[] = "Desired date must be in the same month as the Sunday.";
            continue;
        }

        // 2. RULE: Cannot pick a Sunday as the off-day
        if (date('w', $desired_date_ts) == 0) {
            $errors[] = "You cannot select a Sunday as your Week Off date.";
            continue;
        }

        // 3. RULE: Check if Sunday actually has work hours
        $stmt_check_work = $pdo->prepare("SELECT work FROM attendance WHERE person_id = ? AND date = ?");
        $stmt_check_work->execute([$user['person_id'], $worked_sunday]);
        $work_hours = (float)$stmt_check_work->fetchColumn();

        if ($work_hours <= 0) {
            $errors[] = "No work hours found for Sunday $worked_sunday.";
            continue;
        }

        // 4. RULE: Check if this Sunday was already used for another request
        $stmt_check_used = $pdo->prepare("SELECT id FROM late_exception_requests WHERE user_id = ? AND exception_type = 'weekoff_exception' AND status IN ('approved', 'pending') AND extra_data LIKE ?");
        $stmt_check_used->execute([$user['id'], '%"worked_sunday":"' . $worked_sunday . '"%']);
        if ($stmt_check_used->fetch()) {
            $errors[] = "This Sunday is already used/pending for another request.";
            continue;
        }

        // 5. RULE: Status Validation (Matching your UI logic)
        $stmt_status = $pdo->prepare("SELECT status FROM attendance WHERE person_id = ? AND date = ?");
        $stmt_status->execute([$user['person_id'], $desired_date]);
        $current_att_status = trim($stmt_status->fetchColumn() ?: '');

        $is_before = ($desired_date_ts < $worked_sunday_ts);

        if ($is_before) {
            // Before Sunday: Allows Absent, Late, or Half Day
            $valid_before = ['A', 'Absent', 'L', 'Late', 'HD', 'Half Day', 'HPE', 'FPE', ''];
            if (!in_array($current_att_status, $valid_before)) {
                $errors[] = "Date $desired_date has status '$current_att_status'. Before Sunday, only Absent, Late, or Half Day allowed.";
                continue;
            }
        } else {
            // After Sunday: Strictly allows only Absent
            $valid_after = ['A', 'Absent', ''];
            if (!in_array($current_att_status, $valid_after)) {
                $errors[] = "Date $desired_date has status '$current_att_status'. After Sunday, only Absent status allowed.";
                continue;
            }
        }
        
        $extra_data = json_encode([
            'worked_sunday' => $worked_sunday, 
            'hours' => $work_hours,
            'weekoff_exception_type' => 'weekoff_shift',
            'is_before_sunday' => $is_before
        ]);
    }

        // --- A. Quota Check ---
        $month_start = date('Y-m-01', strtotime($current_date));
        $month_end = date('Y-m-t', strtotime($current_date));

        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM late_exception_requests 
                                     WHERE user_id = ? AND date BETWEEN ? AND ?
                                     AND status = 'approved'
                                     AND exception_type NOT IN ('pl_adjustment', 'weekoff_exception')"); 
        $count_stmt->execute([$user['id'], $month_start, $month_end]);
        if ($count_stmt->fetchColumn() >= 3 && !in_array($exception_type, ['pl_adjustment', 'weekoff_exception'])) {
            $errors[] = "Limit reached for " . date('M d', strtotime($current_date));
            continue; 
        }

        // --- B. Check Existing Request (Correct Logic) ---
        $check = $pdo->prepare("SELECT id FROM late_exception_requests WHERE user_id = ? AND date = ? AND status != 'rejected'");
        $check->execute([$user['id'], $current_date]);
        if ($check->fetch()) {
            $errors[] = "Request exists for " . date('M d', strtotime($current_date));
            continue;
        }

        // --- C. Insert Request ---
       
$ins = $pdo->prepare("INSERT INTO late_exception_requests (user_id, person_id, date, reason, exception_type, status, approved_by, extra_data, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW())");
$ins->execute([$user['id'], $user['person_id'], $current_date, $reason, $exception_type, $approver_id, $extra_data]);        $new_request_id = $pdo->lastInsertId();

        // --- D. Centre Head Auto-Approval ---
     // --- D. Centre Head Auto-Approval ---
        if ($user['role'] === 'centre_head') {
            // 1. Mark the request itself as Approved in the requests table
            $upd = $pdo->prepare("UPDATE late_exception_requests SET status = 'approved', approved_at = NOW() WHERE id = ?");
            $upd->execute([$new_request_id]);

            // 2. Specialized logic for PL vs other Exceptions
            if ($exception_type === 'pl_adjustment') {
                // A. Deduct 1 day from the user's master PL balance
                $deduct = $pdo->prepare("UPDATE users SET pl_balance = pl_balance - 1 WHERE id = ?");
                $deduct->execute([$user['id']]);
                
                // B. Log to PL History (Optional but highly recommended for tracking)
                try {
                    $hist = $pdo->prepare("INSERT INTO pl_history (user_id, action_type, amount_changed, description, created_at) VALUES (?, 'usage', -1, ?, NOW())");
                    $hist->execute([$user['id'], "Auto-approved PL Adjustment for $current_date"]);
                } catch (Exception $e) { /* ignore if table doesn't exist */ }

                // C. Update the Attendance table status to 'PL'
                updateAttendanceStatus($user['person_id'], $current_date, 'PL', $exception_type, $pdo);
                
            } else {
                // For all other types (Late, Full Day, Weekoff, etc.)
                // Map the status: weekoff becomes 'WO', others become 'P' (Present)
                $status_to_set = ($exception_type === 'weekoff_exception') ? 'WO' : 'P';
                
                // Update the Attendance table status
                updateAttendanceStatus($user['person_id'], $current_date, $status_to_set, $exception_type, $pdo);
            }
        }
        $success_count++;
    }

    $_SESSION['message'] = ($success_count > 0) 
        ? ['type' => 'success', 'text' => "$success_count request(s) submitted."] 
        : ['type' => 'error', 'text' => implode(', ', $errors)];

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Database Error.'];
}

// --- FIX: REPAIRED HELPER FUNCTIONS ---
function updateAttendanceStatus($person_id, $date, $status, $type, $pdo) {
    // Check if record exists first
    $stmt = $pdo->prepare("SELECT id FROM attendance WHERE person_id = ? AND date = ?");
    $stmt->execute([$person_id, $date]);
    
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE attendance SET status = ?, exception_type = ? WHERE person_id = ? AND date = ?")
            ->execute([$status, $type, $person_id, $date]);
    } else {
        $pdo->prepare("INSERT INTO attendance (person_id, date, status, exception_type, work) VALUES (?, ?, ?, ?, '0.0')")
            ->execute([$person_id, $date, $status, $type]);
    }
}

// ===== HANDLE OT REQUEST SUBMISSION =====
if (isset($_POST['action']) && $_POST['action'] === 'submit_overtime') {
    $user_id = $user['id'] ?? 0;
    $person_id = $user['person_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $work_hours = (float)($_POST['work_hours'] ?? 0);
    $ot_hours = (int)($_POST['ot_hours'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    // Validation
    if (!$user_id || !$person_id || !$date || !$ot_hours || !$reason) {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: dashboard.php');
        exit;
    }
    
    // Check if OT request already exists
    $stmt = $pdo->prepare("SELECT id FROM overtime_requests WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $date]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'OT request already submitted for this date.';
        header('Location: dashboard.php');
        exit;
    }
    
    // Check if date is within 1 day (validity check)
    $work_date = new DateTime($date);
    $today = new DateTime(date('Y-m-d'));
    $diff = $today->diff($work_date);
    if ($work_date > $today || $diff->days > 1) {
        $_SESSION['error'] = 'OT request is no longer valid. Must be requested within 1 day.';
        header('Location: dashboard.php');
        exit;
    }
    
    // Insert OT request
    try {
        $stmt = $pdo->prepare("
            INSERT INTO overtime_requests 
            (user_id, person_id, date, work_hours, ot_hours, reason, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$user_id, $person_id, $date, $work_hours, $ot_hours, $reason]);
        
        $_SESSION['success'] = 'OT request submitted successfully! Waiting for approval.';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error submitting OT request: ' . $e->getMessage();
    }
    
    header('Location: dashboard.php');
    exit;
}

// ===== HANDLE OT APPROVAL (For Process Head) =====
if (isset($_POST['action']) && $_POST['action'] === 'approve_ot') {
    // Only process head can approve
    if ($user['role'] !== 'process_head' && $user['role'] !== 'centre_head') {
        $_SESSION['error'] = 'Unauthorized! Only Process Head can approve OT.';
        header('Location: dashboard.php');
        exit;
    }
    
    $ot_id = (int)($_POST['ot_id'] ?? 0);
    $status = $_POST['status'] ?? ''; // 'approved' or 'rejected'
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if (!$ot_id || !in_array($status, ['approved', 'rejected'])) {
        $_SESSION['error'] = 'Invalid request.';
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
            $_SESSION['success'] = 'OT request approved successfully!';
        } else {
            $stmt = $pdo->prepare("
                UPDATE overtime_requests 
                SET status = 'rejected', rejected_at = NOW(), rejection_reason = ? 
                WHERE id = ?
            ");
            $stmt->execute([$rejection_reason, $ot_id]);
            $_SESSION['success'] = 'OT request rejected.';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    
    header('Location: dashboard.php');
    exit;
}

// ===== FETCH PENDING OT REQUESTS FOR APPROVAL =====
function getPendingOTRequests($pdo, $person_id = null) {
    $sql = "SELECT or.*, u.full_name, u.person_id 
            FROM overtime_requests or
            JOIN users u ON or.user_id = u.id
            WHERE or.status = 'pending'";
    
    if ($person_id) {
        $sql .= " AND or.person_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$person_id]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


header("Location: dashboard.php");
exit();
?>