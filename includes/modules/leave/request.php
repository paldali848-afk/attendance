<?php
/**
 * Leave Request Handler with Attendance Check
 */

function handleLeaveRequest($user, $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'submit_leave_request') {
        return;
    }
    
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
        // Get current PL balance
        $stmt = $pdo->prepare("SELECT pl_balance FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $current_pl_balance = (float)$stmt->fetchColumn();
        
        $approver_id = getApproverId($user, $pdo);
         if ($user['role'] === 'centre_head') {
            $approver_id = $user['id'];
        }

        if (!$approver_id) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: No approver found in the hierarchy. Please contact HR.'];
            header("Location: dashboard.php");
            exit();
        }

        $start = new DateTime($start_date);
        $end = new DateTime($end_date);

        if ($start > $end) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Start date must be before or equal to end date.'];
            header("Location: dashboard.php");
            exit();
        }

        // ============================================
        // STEP 1: CHECK ATTENDANCE FOR EACH DAY
        // ============================================
        $working_days = [];
        $weekend_days = [];
        $already_present_days = [];
        $already_absent_days = [];
        $already_on_leave_days = [];
        
        $current = clone $start;
        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $day_of_week = $current->format('w'); // 0=Sunday, 6=Saturday
            $is_weekend = ($day_of_week == 0); // Only Sunday as weekend
            
            // Check attendance for this date
            $stmt = $pdo->prepare("SELECT id, status, check_in, check_out FROM attendance WHERE person_id = ? AND date = ?");
            $stmt->execute([$user['person_id'], $date]);
            $attendance = $stmt->fetch();
            
            if ($attendance) {
                $status = strtoupper(trim($attendance['status']));
                
                // Check if already present (has check-in time)
                if (!empty($attendance['check_in']) && $attendance['check_in'] != '00:00:00') {
                    $already_present_days[] = $date;
                } 
                // Check if already on leave
                elseif (in_array($status, ['LV', 'PL', 'HD', 'LWP', 'Leave', 'Paid Leave', 'Half Day'])) {
                    $already_on_leave_days[] = $date;
                }
                // Check if absent
                elseif (in_array($status, ['A', 'ABSENT', 'LWP'])) {
                    $already_absent_days[] = $date;
                }
                // If no check-in and not leave, it's a working day
                else {
                    $working_days[] = $date;
                }
            } else {
                // No attendance record - check if it's a weekend
                if ($is_weekend) {
                    $weekend_days[] = $date;
                } else {
                    $working_days[] = $date;
                }
            }
            
            $current->modify('+1 day');
        }

        // ============================================
        // STEP 2: CALCULATE ACTUAL WORKING DAYS
        // ============================================
        $total_working_days = count($working_days);
        $total_weekend_days = count($weekend_days);
        $total_present_days = count($already_present_days);
        $total_leave_days = count($already_on_leave_days);
        $total_absent_days = count($already_absent_days);
        
        // Total days that need leave (only working days that are not already present/leave)
        $days_to_apply = $total_working_days;

        // If no working days to apply leave for
        if ($days_to_apply == 0) {
            if ($total_present_days > 0) {
                $_SESSION['message'] = [
                    'type' => 'warning', 
                    'text' => 'You are already marked present on all selected dates. No leave needed.'
                ];
            } elseif ($total_leave_days > 0) {
                $_SESSION['message'] = [
                    'type' => 'warning', 
                    'text' => 'You already have leave applied on all selected dates.'
                ];
            } elseif ($total_weekend_days > 0 && count($weekend_days) == ($end->diff($start)->days + 1)) {
                $_SESSION['message'] = [
                    'type' => 'warning', 
                    'text' => 'All selected dates are weekends (Sundays). No leave needed.'
                ];
            } else {
                $_SESSION['message'] = [
                    'type' => 'warning', 
                    'text' => 'No working days found in the selected range to apply leave.'
                ];
            }
            header("Location: dashboard.php");
            exit();
        }

        // Check for pending request
        $check_pending = $pdo->prepare("SELECT id FROM leave_requests WHERE user_id = ? AND start_date = ? AND end_date = ? AND status = 'pending'");
        $check_pending->execute([$user['id'], $start_date, $end_date]);
        if ($check_pending->fetch()) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'You already have a pending request for this date range.'];
            header("Location: dashboard.php");
            exit();
        }

        // ============================================
        // STEP 3: INTELLIGENT PL/LWP CALCULATION
        // ============================================
        $pl_days_to_use = 0;
        $lwp_days = 0;
        $status_to_set = 'LV';
        $leave_type_to_save = $leave_type;
        $days_to_deduct = 0;
        $pl_balance_after = $current_pl_balance;
        
        // Only apply PL/LWP logic for 'leave' and 'pl' types
        if ($leave_type === 'leave' || $leave_type === 'pl') {
            $pl_available = $current_pl_balance;
            
            if ($pl_available >= $days_to_apply) {
                // Enough PL balance for all days
                $pl_days_to_use = $days_to_apply;
                $lwp_days = 0;
                $status_to_set = 'PL';
                $days_to_deduct = $days_to_apply;
                $leave_type_to_save = 'pl';
            } else {
                // Not enough PL, use available PL + LWP for remaining
                $pl_days_to_use = floor($pl_available);
                $lwp_days = $days_to_apply - $pl_days_to_use;
                $status_to_set = 'PL';
                $days_to_deduct = $pl_days_to_use;
                $leave_type_to_save = 'pl';
                
                $reason = $reason . " (PL: {$pl_days_to_use} days, LWP: {$lwp_days} days)";
            }
            
            $pl_balance_after = $current_pl_balance - $days_to_deduct;
        } else {
            // For other leave types (half_day, coming_late, early_leave)
            if ($leave_type === 'half_day') {
                $status_to_set = 'HD';
                $days_to_deduct = $days_to_apply * 0.5;
            } elseif ($leave_type === 'coming_late') {
                $status_to_set = 'Coming Late';
                $days_to_deduct = 0;
            } elseif ($leave_type === 'early_leave') {
                $status_to_set = 'Early Leave';
                $days_to_deduct = 0;
            }
            $leave_type_to_save = $leave_type;
        }

        // ============================================
        // STEP 4: INSERT LEAVE REQUEST
        // ============================================
        $ins = $pdo->prepare("INSERT INTO leave_requests 
            (user_id, person_id, start_date, end_date, date, reason, request_type, status, approved_by, 
             pl_days_used, lwp_days, total_days, pl_balance_before, pl_balance_after,
             weekend_days, present_days, leave_days, absent_days) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $ins->execute([
            $user['id'], 
            $user['person_id'], 
            $start_date, 
            $end_date, 
            $start_date, 
            $reason, 
            $leave_type_to_save, 
            $approver_id,
            $pl_days_to_use,
            $lwp_days,
            $days_to_apply,
            $current_pl_balance,
            $pl_balance_after,
            $total_weekend_days,
            $total_present_days,
            $total_leave_days,
            $total_absent_days
        ]);
        
        $request_id = $pdo->lastInsertId();

        // ============================================
        // STEP 5: SEND NOTIFICATION
        // ============================================
        $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $type_display = ucfirst(str_replace('_', ' ', $leave_type_to_save));
        $date_range_display = date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date));
        
        $notif_message = $user['full_name'] . ' has submitted a ' . $type_display . ' request for ' . $days_to_apply . ' working day(s) from ' . $date_range_display . '.';
        $notif_message .= "\nTotal Days: " . ($end->diff($start)->days + 1) . " | Working Days: {$days_to_apply} | Weekends: {$total_weekend_days}";
        if ($pl_days_to_use > 0) {
            $notif_message .= " | PL Used: {$pl_days_to_use} days";
        }
        if ($lwp_days > 0) {
            $notif_message .= " | LWP: {$lwp_days} days";
        }
        $notif_stmt->execute([$approver_id, 'New ' . $type_display . ' Request', $notif_message, 'leave']);

        // ============================================
        // STEP 6: AUTO-APPROVE IF CENTRE HEAD
        // ============================================
        if ($user['role'] === 'centre_head' && $approver_id == $user['id']) {
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'approved', approved_at = NOW() WHERE id = ?");
            $stmt->execute([$request_id]);

            // Deduct PL if applicable
            if ($days_to_deduct > 0 && $pl_days_to_use > 0) {
                $upd = $pdo->prepare("UPDATE users SET pl_balance = pl_balance - ? WHERE id = ? AND pl_balance >= ?");
                $upd->execute([$days_to_deduct, $user['id'], $days_to_deduct]);
            }

            // ============================================
            // STEP 7: UPDATE ATTENDANCE FOR EACH DAY
            // ============================================
            $current = clone $start;
            $day_counter = 0;
            
            while ($current <= $end) {
                $date = $current->format('Y-m-d');
                $day_counter++;
                $day_of_week = $current->format('w');
                $is_weekend = ($day_of_week == 0);
                
                // Check current attendance
                $stmt = $pdo->prepare("SELECT id, status, check_in FROM attendance WHERE person_id = ? AND date = ?");
                $stmt->execute([$user['person_id'], $date]);
                $attendance = $stmt->fetch();
                
                // Skip if already present (has check-in)
                if ($attendance && !empty($attendance['check_in']) && $attendance['check_in'] != '00:00:00') {
                    $current->modify('+1 day');
                    continue;
                }
                
                // Skip weekends
                if ($is_weekend) {
                    $current->modify('+1 day');
                    continue;
                }
                
                // Determine status for this specific day
                $day_status = $status_to_set;
                
                // For mixed PL/LWP, assign LWP after PL days are exhausted
                if ($lwp_days > 0 && $day_counter > $pl_days_to_use) {
                    $day_status = 'LWP';
                }
                
                // Update or insert attendance
                if ($attendance) {
                    $update_stmt = $pdo->prepare("UPDATE attendance SET status = ? WHERE id = ? AND person_id = ? AND date = ?");
                    $update_stmt->execute([$day_status, $attendance['id'], $user['person_id'], $date]);
                } else {
                    $insert_stmt = $pdo->prepare("INSERT INTO attendance (person_id, date, status, check_in, check_out, work) VALUES (?, ?, ?, '09:00:00', '18:00:00', '9.0')");
                    $insert_stmt->execute([$user['person_id'], $date, $day_status]);
                }
                
                $current->modify('+1 day');
            }

            // Build success message
            $message_text = $type_display . ' request auto-approved for ' . $days_to_apply . ' working day(s).';
            $message_text .= "\nTotal Days: " . ($end->diff($start)->days + 1) . " | Weekends: {$total_weekend_days}";
            if ($pl_days_to_use > 0) {
                $message_text .= " | PL Used: {$pl_days_to_use} days";
            }
            if ($lwp_days > 0) {
                $message_text .= " | LWP: {$lwp_days} days";
            }
            $_SESSION['message'] = ['type' => 'success', 'text' => $message_text];
        } else {
            // Build success message for pending request
            $message_text = $type_display . ' request submitted successfully for ' . $days_to_apply . ' working day(s).';
            $message_text .= "\nTotal Days: " . ($end->diff($start)->days + 1) . " | Weekends: {$total_weekend_days}";
            if ($pl_days_to_use > 0) {
                $message_text .= " | PL Used: {$pl_days_to_use} days";
            }
            if ($lwp_days > 0) {
                $message_text .= " | LWP: {$lwp_days} days (pending approval)";
            }
            $_SESSION['message'] = ['type' => 'success', 'text' => $message_text];
        }
    } catch (PDOException $e) {
        error_log("Leave Request Error: " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
    }

    header("Location: dashboard.php");
    exit();
}