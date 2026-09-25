<?php
/**
 * Exception Request Handler
 */

function handleExceptionRequest($user, $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'submit_exception') {
        return;
    }
    
    $date = $_POST['date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $exception_type = $_POST['exception_type'] ?? 'late';
    $current_status = $_POST['current_status'] ?? '';

    try {
        // For PL Adjustment, check PL balance first
        if ($exception_type === 'pl_adjustment') {
            // Get current PL balance
            $stmt = $pdo->prepare("SELECT pl_balance FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $current_pl = $stmt->fetchColumn();
            
            // Check if PL balance is sufficient (must be >= 1)
            if ($current_pl === false || $current_pl < 1.0) {
                $_SESSION['message'] = [
                    'type' => 'error', 
                    'text' => 'PL Adjustment failed: Insufficient PL balance. You need at least 1.0 PL days. Current balance: ' . number_format($current_pl, 1)
                ];
                header("Location: dashboard.php");
                exit();
            }
            
            // Check if date is in current month
            $selected_date = new DateTime($date);
            $now = new DateTime();
            if ($selected_date->format('m') !== $now->format('m') || $selected_date->format('Y') !== $now->format('Y')) {
                $_SESSION['message'] = [
                    'type' => 'error', 
                    'text' => 'PL Adjustment can only be requested for dates in the current month.'
                ];
                header("Location: dashboard.php");
                exit();
            }
            
            // Check if date is not in future
            if ($selected_date > $now) {
                $_SESSION['message'] = [
                    'type' => 'error', 
                    'text' => 'You cannot request PL Adjustment for a future date.'
                ];
                header("Location: dashboard.php");
                exit();
            }
        }

        $month_start = date('Y-m-01', strtotime($date));
        $month_end = date('Y-m-t', strtotime($date));

        // Check monthly limit (except for PL Adjustment)
        if ($exception_type !== 'pl_adjustment') {
            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM late_exception_requests 
                                         WHERE user_id = ? 
                                         AND date BETWEEN ? AND ?
                                         AND status = 'approved'
                                         AND exception_type != 'pl_adjustment'");
            $count_stmt->execute([$user['id'], $month_start, $month_end]);
            $monthly_requests = $count_stmt->fetchColumn();

            if ($monthly_requests >= 3) {
                $_SESSION['message'] = [
                    'type' => 'error', 
                    'text' => 'You have already submitted 3 exception requests this month. Maximum limit of 3 per month reached.'
                ];
                header("Location: dashboard.php");
                exit();
            }
        }

        // Check if there's already a pending or approved request for this date
        $check = $pdo->prepare("SELECT id, status FROM late_exception_requests WHERE user_id = ? AND date = ?");
        $check->execute([$user['id'], $date]);
        $existing = $check->fetch();

        if ($existing) {
            if ($existing['status'] === 'pending') {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'An exception request for this date is already pending.'];
            } else if ($existing['status'] === 'approved') {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'An exception request for this date has already been approved.'];
            }
            header("Location: dashboard.php");
            exit();
        }

        // Get approver
        $approver_id = getApproverId($user, $pdo);

        if ($user['role'] === 'centre_head') {
            $approver_id = $user['id'];
        }

        if (!$approver_id) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: No approver found in the hierarchy. Please contact HR.'];
            header("Location: dashboard.php");
            exit();
        }

        // Insert the exception request
        $ins = $pdo->prepare("INSERT INTO late_exception_requests (user_id, person_id, date, reason, exception_type, status, approved_by) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
        $ins->execute([$user['id'], $user['person_id'], $date, $reason, $exception_type, $approver_id]);

        // Send notification
        $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $type_display = ucfirst(str_replace('_', ' ', $exception_type));
        $notif_message = $user['full_name'] . ' has submitted a ' . $exception_type . ' exception request for ' . date('M d, Y', strtotime($date)) . '.';
        $notif_stmt->execute([$approver_id, 'New Exception Request', $notif_message, 'exception']);

        // Auto-approve for Centre Head
        if ($user['role'] === 'centre_head' && $approver_id == $user['id']) {
            $stmt = $pdo->prepare("UPDATE late_exception_requests SET status = 'approved', approved_at = NOW() WHERE user_id = ? AND date = ? AND status = 'pending'");
            $stmt->execute([$user['id'], $date]);

            // For PL Adjustment, deduct from PL balance
            if ($exception_type === 'pl_adjustment') {
                $upd_bal = $pdo->prepare("UPDATE users SET pl_balance = pl_balance - 1 WHERE id = ? AND pl_balance >= 1");
                $upd_bal->execute([$user['id']]);
            }

            // Update attendance
            $stmt = $pdo->prepare("SELECT id FROM attendance WHERE person_id = ? AND date = ?");
            $stmt->execute([$user['person_id'], $date]);
            $attendance = $stmt->fetch();

            $status_to_set = 'Exception';
            if ($exception_type === 'full_day') {
                $status_to_set = 'FPE';
            } elseif ($exception_type === 'half_day') {
                $status_to_set = 'HPE';
            } elseif ($exception_type === 'pl_adjustment') {
                $status_to_set = 'PL';
            }

            if ($attendance) {
                $update_stmt = $pdo->prepare("UPDATE attendance SET status = ?, exception_type = ? WHERE id = ? AND person_id = ? AND date = ?");
                $update_stmt->execute([$status_to_set, $exception_type, $attendance['id'], $user['person_id'], $date]);
            } else {
                $insert_stmt = $pdo->prepare("INSERT INTO attendance (person_id, date, status, exception_type, check_in, check_out, work) VALUES (?, ?, ?, ?, '09:00:00', '18:00:00', '9.0')");
                $insert_stmt->execute([$user['person_id'], $date, $status_to_set, $exception_type]);
            }

            $message_text = 'Exception request auto-approved for ' . date('M d, Y', strtotime($date)) . '.';
            if ($exception_type === 'pl_adjustment') {
                $message_text .= ' PL Balance updated.';
            }
            $_SESSION['message'] = ['type' => 'success', 'text' => $message_text];
        } else {
            $message_text = 'Exception request submitted successfully for ' . date('M d, Y', strtotime($date)) . '.';
            if ($exception_type === 'pl_adjustment') {
                $message_text .= ' PL Adjustment will be applied upon approval.';
            }
            $_SESSION['message'] = ['type' => 'success', 'text' => $message_text];
        }
    } catch (PDOException $e) {
        error_log("Exception Request Error: " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
    }

    header("Location: dashboard.php");
    exit();
}