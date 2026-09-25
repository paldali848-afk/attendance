<?php
// ============================================
// UPDATED ROSTER REQUEST HANDLER
// ============================================

$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$start_time_input = $_POST['start_time'] ?? ''; // e.g., "10:00 AM"
$reason = trim($_POST['reason'] ?? '');

if (empty($start_date) || empty($end_date) || empty($start_time_input) || empty($reason)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Please fill in all required fields.'];
    header("Location: dashboard.php");
    exit();
}

try {
    $approver_id = getApproverId($user, $pdo);
    if ($user['role'] === 'centre_head') { $approver_id = $user['id']; }

    if (!$approver_id) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: No approver found. Please contact HR.'];
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

    // 1. DYNAMIC WORKING HOURS LOGIC (Matches Display Table)
    $member_pos = strtoupper($user['position'] ?? '');
    $is_csr_agent = (strpos($member_pos, 'CSR') !== false || strpos($member_pos, 'AGENT') !== false);
    
    if ($is_csr_agent || $user['role'] === 'process_head' || $user['role'] === 'centre_head') {
        $working_hours = 8;
    } else {
        $working_hours = 9;
    }

    // 2. CALCULATE END TIME SAFELY
    $time_obj = new DateTime($start_time_input);
    $start_time_24 = $time_obj->format('H:i:s'); // For DB consistency
    
    $end_time_obj = clone $time_obj;
    $end_time_obj->modify("+$working_hours hours");
    $end_time_formatted = $end_time_obj->format('h:i A');

    // 3. DUPLICATE CHECKS
    $check_pending = $pdo->prepare("SELECT id FROM roster_requests WHERE user_id = ? AND start_date = ? AND end_date = ? AND status = 'pending'");
    $check_pending->execute([$user['id'], $start_date, $end_date]);
    if ($check_pending->fetch()) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'You already have a pending roster request for these dates.'];
        header("Location: dashboard.php");
        exit();
    }

    // 4. INSERT REQUEST
    $ins = $pdo->prepare("INSERT INTO roster_requests (user_id, person_id, start_date, end_date, date, start_time, end_time, working_hours, reason, status, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
    $ins->execute([$user['id'], $user['person_id'], $start_date, $end_date, $start_date, $start_time_input, $end_time_formatted, $working_hours, $reason, $approver_id]);
    $request_id = $pdo->lastInsertId();

    // 5. NOTIFICATION
    $notif_message = $user['full_name'] . " roster: $start_date to $end_date. Shift: $start_time_input to $end_time_formatted.";
    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'roster', 0, NOW())");
    $notif_stmt->execute([$approver_id, 'New Roster Request', $notif_message]);

    // 6. AUTO-APPROVE LOGIC (For Centre Head)
    if ($user['role'] === 'centre_head' && $approver_id == $user['id']) {
        $pdo->prepare("UPDATE roster_requests SET status = 'approved', approved_at = NOW() WHERE id = ?")->execute([$request_id]);

        $current = clone $start;
        $today = new DateTime();
        
        while ($current <= $end) {
            $date_str = $current->format('Y-m-d');
            
            // Only update attendance table if the date is Today or in the Future
            if ($current >= $today->modify('midnight')) {
                $stmt = $pdo->prepare("SELECT id FROM attendance WHERE person_id = ? AND date = ?");
                $stmt->execute([$user['person_id'], $date_str]);
                $exists = $stmt->fetch();

                if ($exists) {
                    // Update to 'Scheduled' so Sync scripts can handle the actual work later
                    $pdo->prepare("UPDATE attendance SET status = 'Scheduled' WHERE id = ?")->execute([$exists['id']]);
                } else {
                    $pdo->prepare("INSERT INTO attendance (person_id, date, status, work) VALUES (?, ?, 'Scheduled', 0)")
                        ->execute([$user['person_id'], $date_str]);
                }
            }
            $current->modify('+1 day');
        }
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Roster auto-approved and set to Scheduled.'];
    } else {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Roster request submitted successfully.'];
    }

} catch (Exception $e) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
}

header("Location: dashboard.php");
exit();