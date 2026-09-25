<?php
// ============================================
// APPROVAL PROCESS HANDLER - WITH SMART PL DEDUCTION
// File: approvals/approval_process.php
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// DATABASE CONNECTION
// ============================================
$root_path = dirname(dirname(dirname(__DIR__)));
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/config/db_connection.php',
    dirname(__DIR__) . '/config.php',
    dirname(__DIR__) . '/config/db_connection.php',
    dirname(dirname(__DIR__)) . '/config.php',
    dirname(dirname(__DIR__)) . '/config/db_connection.php',
    $root_path . '/config.php',
    $root_path . '/config/db_connection.php',
    $_SERVER['DOCUMENT_ROOT'] . '/attendance/config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/attendance/config/db_connection.php',
    $_SERVER['DOCUMENT_ROOT'] . '/config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/config/db_connection.php',
];

$db_found = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_found = true;
        error_log("Database connection loaded from: $path");
        break;
    }
}

if (!$db_found) {
    die("FATAL ERROR: Database configuration file not found.");
}

if (!isset($pdo)) {
    die("FATAL ERROR: Database connection not established.");
}

// ============================================
// LOGGING
// ============================================
$log_file = __DIR__ . '/approval_debug.log';
function writeLog($msg)
{
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

writeLog("========== START APPROVAL PROCESS ==========");
writeLog("POST data: " . print_r($_POST, true));

// ============================================
// GET AND VALIDATE POST DATA
// ============================================
$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$request_type = isset($_POST['request_type']) ? trim($_POST['request_type']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : '';
$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

writeLog("Request ID: $request_id, Type: $request_type, Status: $status");

if ($request_id <= 0) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid request ID.'];
    header("Location: dashboard.php");
    exit();
}

if (empty($remarks)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Please enter remarks before submitting.'];
    header("Location: dashboard.php");
    exit();
}

if (empty($status) || !in_array($status, ['approved', 'rejected'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid status.'];
    header("Location: dashboard.php");
    exit();
}

try {
    // ===== STEP 1: GET REQUEST DATA =====
    $table = '';
    $is_roster = false;
    $is_exception = false;
    $is_ot = false;
    $is_resignation = false;
    $is_leave = false;

    if ($request_type === 'roster') {
        $table = 'roster_requests';
        $is_roster = true;
        writeLog("Roster request detected");
    } elseif ($request_type === 'ot' || $request_type === 'overtime' || $request_type === 'OT') {
        $table = 'overtime_requests';
        $is_ot = true;
        writeLog("OT request detected");
    } elseif ($request_type === 'resignation' || $request_type === 'Resignation') {
        $table = 'resignation_requests';
        $is_resignation = true;
        writeLog("Resignation request detected");
    } elseif (in_array($request_type, ['late', 'exception', 'full_day', 'half_day_exception', 'weekoff_shift', 'weekoff_exception', 'pl_adjustment', 'half_day'])) {
        $table = 'late_exception_requests';
        $is_exception = true;
        writeLog("Exception request detected");
    } else {
        $table = 'leave_requests';
        $is_leave = true;
        writeLog("Leave request detected");
    }

    writeLog("Targeting table: $table for ID: $request_id");

    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$request_id]);
    $request_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request_data) {
        throw new Exception("Request not found.");
    }

    $user_id = $request_data['user_id'];
    $person_id = isset($request_data['person_id']) ? $request_data['person_id'] : $user_id;

    writeLog("User ID: $user_id, Person ID: $person_id");

    // ===== STEP 2: DETERMINE LEAVE TYPE =====
    $leave_type = strtolower($request_type);

    if (empty($leave_type)) {
        $leave_type = isset($request_data['request_type']) ? strtolower($request_data['request_type']) : '';
    }

    if (empty($leave_type) && isset($request_data['leave_type'])) {
        $leave_type = strtolower($request_data['leave_type']);
    }

    if (empty($leave_type) && isset($request_data['exception_type'])) {
        $leave_type = strtolower($request_data['exception_type']);
    }

    writeLog("Leave Type: '$leave_type'");

    // ===== STEP 3: FETCH CURRENT ATTENDANCE CONTEXT =====
    $days_to_deduct = 0;
    $status_to_set = 'PL';

    $target_date = $is_exception ? $request_data['date'] : ($request_data['start_date'] ?? date('Y-m-d'));
    writeLog("Target date for context: $target_date");

    $stmt_curr = $pdo->prepare("SELECT status, check_out, work, check_in FROM attendance WHERE person_id = ? AND date = ?");
    $stmt_curr->execute([$person_id, $target_date]);
    $current_attendance = $stmt_curr->fetch(PDO::FETCH_ASSOC);

    $current_st = $current_attendance['status'] ?? 'A';
    $current_out = $current_attendance['check_out'] ?? '00:00:00';
    $current_work = $current_attendance['work'] ?? 0;

    writeLog("Current Status: $current_st, Check Out: $current_out, Work Hours: $current_work");

    // =========================================================
    // STEP 4: SMART PL DEDUCTION LOGIC
    // =========================================================
    if ($status === 'approved') {

        if ($leave_type === 'pl' || $leave_type === 'pl_adjustment') {
            writeLog("Processing PL/PL Adjustment with smart logic");

            // Determine if this is a range-based leave (multi-day leave_requests row)
            $is_range_leave = !$is_exception
                              && !empty($request_data['start_date'])
                              && !empty($request_data['end_date']);

            if ($is_range_leave) {
                // ===== RANGE-BASED: use the entire start_date ? end_date span =====
                $start = new DateTime($request_data['start_date']);
                $end   = new DateTime($request_data['end_date']);
                $days_to_deduct = $start->diff($end)->days + 1;
                $status_to_set = 'PL';

                // Add external sandwich days (outside the leave range)
                $sw_extra = 0;
                if (!empty($request_data['sandwich_dates'])) {
                    $sw_list = array_filter(array_map('trim', explode(',', $request_data['sandwich_dates'])));
                    foreach ($sw_list as $sw_d) {
                        if ($sw_d < $request_data['start_date'] || $sw_d > $request_data['end_date']) {
                            $sw_extra++;
                        }
                    }
                }
                if ($sw_extra > 0) {
                    $days_to_deduct += $sw_extra;
                    writeLog("Sandwich adds $sw_extra external days. Total: $days_to_deduct");
                }

                writeLog("Range-based PL leave: $days_to_deduct days");
            } else {
                // ===== SINGLE-DAY (PL Adjustment exception) =====
                if ($current_st === 'HD' || $current_st === 'Half Day') {
                    $days_to_deduct = 0.5;
                    $status_to_set = 'HPL';
                }
                elseif ($current_st === 'A' || $current_st === 'Absent' || empty($current_out) || $current_out === '00:00:00') {
                    $days_to_deduct = 1.0;
                    $status_to_set = 'PL';
                }
                elseif ($current_work > 0 && $current_work < 4.5) {
                    $days_to_deduct = 0.5;
                    $status_to_set = 'HPL';
                }
                elseif ($current_work >= 4.5 && $current_work < 8.0) {
                    $days_to_deduct = 0.5;
                    $status_to_set = 'HPL';
                }
                elseif ($current_work >= 8.0) {
                    $days_to_deduct = 1.0;
                    $status_to_set = 'PL';
                }
                else {
                    $days_to_deduct = 1.0;
                    $status_to_set = 'PL';
                }
                writeLog("Single-day PL/Adjustment: $status_to_set, deduct $days_to_deduct");
            }
        }
        // ===== COMP OFF =====
        elseif ($leave_type === 'comp_off' || $leave_type === 'co') {
            $status_to_set = 'CO';
            $days_to_deduct = 0;
            writeLog("Comp Off - No PL deduction");
        }
        // ===== LWP =====
        elseif ($leave_type === 'lwp' || $leave_type === 'leave' || $leave_type === 'lv') {
            $status_to_set = 'LWP';
            $days_to_deduct = 0;
            writeLog("LWP - No PL deduction");
        }
        // ===== HALF DAY =====
        elseif ($leave_type === 'half_day') {
            $status_to_set = 'HD';
            $days_to_deduct = 0;
            writeLog("Half Day - No PL deduction");
        }
        // ===== OTHER TYPES =====
        else {
            $status_to_set = strtoupper($leave_type);
            $days_to_deduct = 0;
            writeLog("Other type: $status_to_set - No PL deduction");
        }

        writeLog("Final decision: Status: $status_to_set, Days to deduct: $days_to_deduct");
    }

    // ===== STEP 5: CHECK PL BALANCE =====
    if ($days_to_deduct > 0) {
        $check_col = $pdo->query("SHOW COLUMNS FROM users LIKE 'pl_balance'");
        if ($check_col->rowCount() == 0) {
            writeLog("pl_balance column not found! Adding it...");
            $pdo->exec("ALTER TABLE users ADD COLUMN pl_balance DECIMAL(10,2) DEFAULT 0");
        }

        $bal_stmt = $pdo->prepare("SELECT pl_balance FROM users WHERE id = ?");
        $bal_stmt->execute([$user_id]);
        $current_pl_balance = (float)$bal_stmt->fetchColumn();
        writeLog("Current PL Balance: $current_pl_balance");

        if ($current_pl_balance < $days_to_deduct) {
            throw new Exception("Insufficient PL balance. Available: $current_pl_balance, Required: $days_to_deduct");
        }
    }

    // ===== STEP 6: UPDATE REQUEST STATUS =====
    if ($is_resignation) {
        $update_stmt = $pdo->prepare("UPDATE $table SET status = ?, approved_by = ?, approved_date = NOW(), remarks = ? WHERE id = ?");
        $update_stmt->execute([$status, $_SESSION['user_id'] ?? 0, $remarks, $request_id]);
        writeLog("Resignation request status updated");
    } elseif ($is_ot) {
        $update_stmt = $pdo->prepare("UPDATE $table SET status = ?, approved_by = ?, approved_at = NOW(), remarks = ? WHERE id = ?");
        $update_stmt->execute([$status, $_SESSION['user_id'] ?? 0, $remarks, $request_id]);
        writeLog("OT request status updated");
    } else {
        $update_stmt = $pdo->prepare("UPDATE $table SET status = ?, approved_by = ?, approved_at = NOW(), remarks = ? WHERE id = ?");
        $update_stmt->execute([$status, $_SESSION['user_id'] ?? 0, $remarks, $request_id]);
        writeLog("Request status updated to: $status");
    }

    // ===== STEP 7: PROCESS APPROVED REQUESTS =====
    if ($status === 'approved') {

        // ===== 7a: DEDUCT PL BALANCE =====
        if ($days_to_deduct > 0) {
            writeLog("========== DEDUCTING PL BALANCE ==========");
            writeLog("Deducting $days_to_deduct days from user $user_id");

            try {
                $pdo->beginTransaction();

                $bal_stmt = $pdo->prepare("SELECT pl_balance FROM users WHERE id = ? FOR UPDATE");
                $bal_stmt->execute([$user_id]);
                $current_balance = (float)$bal_stmt->fetchColumn();

                $new_balance = $current_balance - $days_to_deduct;
                writeLog("Balance: $current_balance - $days_to_deduct = $new_balance");

                $update_bal = $pdo->prepare("UPDATE users SET pl_balance = ? WHERE id = ?");
                $update_bal->execute([$new_balance, $user_id]);

                try {
                    $check_hist = $pdo->query("SHOW TABLES LIKE 'pl_history'");
                    if ($check_hist->rowCount() > 0) {
                        $log_hist = $pdo->prepare("INSERT INTO pl_history (user_id, action_type, amount_changed, description, created_at) VALUES (?, 'usage', ?, ?, NOW())");
                        $description = "Approved $leave_type request (ID: $request_id) on $target_date";
                        $log_hist->execute([$user_id, -$days_to_deduct, $description]);
                        writeLog("PL history logged");
                    }
                } catch (Exception $e) {
                    writeLog("Could not log to pl_history: " . $e->getMessage());
                }

                $pdo->commit();
                writeLog("PL DEDUCTION SUCCESSFUL! New balance: $new_balance");
            } catch (Exception $e) {
                $pdo->rollBack();
                writeLog("PL deduction failed: " . $e->getMessage());
                throw new Exception("PL deduction failed: " . $e->getMessage());
            }
        }

        // ===== 7b: UPDATE ATTENDANCE =====
        $notif_message = '';

        // ===== LEAVE REQUEST =====
        if ($is_leave && !empty($request_data['start_date']) && !empty($request_data['end_date']) && !empty($status_to_set)) {
            writeLog("========== UPDATING LEAVE ATTENDANCE ==========");
            writeLog("Status to set: $status_to_set");

            $updated_count = 0;

            // Sandwich data from submission
            $is_sandwich_leave = isset($request_data['is_sandwich_leave']) ? (int)$request_data['is_sandwich_leave'] : 0;
            $sandwich_dates = [];

            if ($is_sandwich_leave == 1 && !empty($request_data['sandwich_dates'])) {
                writeLog("========== PROCESSING SANDWICH LEAVE ==========");
                $sandwich_dates = array_filter(array_map('trim', explode(',', $request_data['sandwich_dates'])));
                writeLog("Sandwich dates: " . implode(', ', $sandwich_dates));
            }

            $start = new DateTime($request_data['start_date']);
            $end = new DateTime($request_data['end_date']);

            while ($start <= $end) {
                $loop_date = $start->format('Y-m-d');
                $day_status = $status_to_set;

                if ($is_sandwich_leave == 1 && !empty($sandwich_dates) && in_array($loop_date, $sandwich_dates)) {
                    $day_status = 'SW';
                    writeLog("Marking $loop_date as SW (Sandwich Leave)");
                }

                $check_att = $pdo->prepare("SELECT id FROM attendance WHERE person_id = ? AND date = ?");
                $check_att->execute([$person_id, $loop_date]);

                if ($check_att->fetch()) {
                    $update_att = $pdo->prepare("UPDATE attendance SET status = ? WHERE person_id = ? AND date = ?");
                    $update_att->execute([$day_status, $person_id, $loop_date]);
                    writeLog("Updated attendance for $loop_date to $day_status");
                } else {
                    $insert_att = $pdo->prepare("INSERT INTO attendance (person_id, date, status, check_in, check_out, work) VALUES (?, ?, ?, '00:00:00', '00:00:00', '0.0')");
                    $insert_att->execute([$person_id, $loop_date, $day_status]);
                    writeLog("Inserted attendance for $loop_date with status $day_status");
                }
                $updated_count++;
                $start->modify('+1 day');
            }

            writeLog("Attendance updated for $updated_count days.");

            // Mark EXTERNAL sandwich days (outside the request range)
            if ($is_sandwich_leave == 1 && !empty($sandwich_dates)) {
                foreach ($sandwich_dates as $sw_date) {
                    if ($sw_date >= $request_data['start_date'] && $sw_date <= $request_data['end_date']) {
                        continue;
                    }

                    $check_att = $pdo->prepare("SELECT id FROM attendance WHERE person_id = ? AND date = ?");
                    $check_att->execute([$person_id, $sw_date]);

                    if ($check_att->fetch()) {
                        $pdo->prepare("UPDATE attendance SET status = 'SW' WHERE person_id = ? AND date = ?")
                            ->execute([$person_id, $sw_date]);
                    } else {
                        $pdo->prepare("
                            INSERT INTO attendance (person_id, date, status, check_in, check_out, work) 
                            VALUES (?, ?, 'SW', '00:00:00', '00:00:00', '0.0')
                        ")->execute([$person_id, $sw_date]);
                    }
                    writeLog("External sandwich: $sw_date marked SW");
                }
            }

            $sandwich_text = ($is_sandwich_leave == 1 && !empty($sandwich_dates)) ? ' (Includes Sandwich Leave on Sat-Mon)' : '';
            $notif_message = 'Your ' . str_replace('_', ' ', $leave_type) . ' request has been approved as ' . $status_to_set . '.' . $sandwich_text;
        }

        // ===== EXCEPTION REQUEST =====
        if ($is_exception) {
            writeLog("========== UPDATING EXCEPTION ATTENDANCE ==========");

            $ex_date = $request_data['date'];
            $ex_type = $request_data['exception_type'];

            writeLog("Exception Date: $ex_date, Type: $ex_type");

            if ($ex_type === 'pl_adjustment') {
                $new_status = $status_to_set;
                writeLog("PL Adjustment - Setting status to: $new_status");
            } elseif ($ex_type === 'weekoff_exception' || $ex_type === 'weekoff_shift') {
                $new_status = 'WO';
            } elseif ($ex_type === 'half_day' || $ex_type === 'half_day_exception' || $ex_type === 'hpe') {
                $new_status = 'HD';
            } elseif ($ex_type === 'full_day' || $ex_type === 'full_day_exception' || $ex_type === 'fpe') {
                $new_status = 'P';
            } elseif ($ex_type === 'late') {
                $new_status = 'L';
            } else {
                $new_status = 'P';
            }

            $check_att = $pdo->prepare("SELECT id FROM attendance WHERE person_id = ? AND date = ?");
            $check_att->execute([$person_id, $ex_date]);

            if ($check_att->fetch()) {
                $update_att = $pdo->prepare("UPDATE attendance SET status = ? WHERE person_id = ? AND date = ?");
                $update_att->execute([$new_status, $person_id, $ex_date]);
                writeLog("Updated attendance for $ex_date to status: $new_status");
            } else {
                $insert_att = $pdo->prepare("INSERT INTO attendance (person_id, date, status, check_in, check_out, work) VALUES (?, ?, ?, '00:00:00', '00:00:00', '0.0')");
                $insert_att->execute([$person_id, $ex_date, $new_status]);
                writeLog("Inserted attendance for $ex_date with status: $new_status");
            }

            $notif_message = "Your exception request for " . date('M d, Y', strtotime($ex_date)) . " has been approved. Status: $new_status";
        }

        // ===== OT REQUEST =====
        if ($is_ot) {
            writeLog("========== UPDATING OT ATTENDANCE ==========");

            $ot_date = $request_data['date'];
            $ot_hours = $request_data['ot_hours'] ?? 0;

            writeLog("OT Date: $ot_date, OT Hours: $ot_hours");

            try {
                $check_col = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'ot_approved'");
                if ($check_col->rowCount() > 0) {
                    $stmt_att = $pdo->prepare("UPDATE attendance SET ot_approved = 1, ot_hours = ? WHERE person_id = ? AND date = ?");
                    $stmt_att->execute([$ot_hours, $person_id, $ot_date]);
                    writeLog("Updated attendance OT for $ot_date: $ot_hours hours");
                } else {
                    try {
                        $pdo->exec("ALTER TABLE attendance ADD COLUMN ot_approved TINYINT(1) DEFAULT 0, ADD COLUMN ot_hours DECIMAL(5,2) DEFAULT 0");
                        $stmt_att = $pdo->prepare("UPDATE attendance SET ot_approved = 1, ot_hours = ? WHERE person_id = ? AND date = ?");
                        $stmt_att->execute([$ot_hours, $person_id, $ot_date]);
                        writeLog("Added OT columns and updated attendance");
                    } catch (Exception $e) {
                        writeLog("Could not add OT columns: " . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                writeLog("Error updating attendance OT: " . $e->getMessage());
            }

            $notif_message = '? Your Overtime request for ' . date('M d, Y', strtotime($ot_date)) . ' has been approved. (' . $ot_hours . ' hours OT)';
        }

        // ===== RESIGNATION REQUEST =====
        if ($is_resignation) {
            writeLog("========== PROCESSING RESIGNATION APPROVAL ==========");

            $last_working_date = $request_data['last_working_date'] ?? date('Y-m-d');

            try {
                $check_col = $pdo->query("SHOW COLUMNS FROM users LIKE 'resignation_status'");
                if ($check_col->rowCount() > 0) {
                    $update_user = $pdo->prepare("UPDATE users SET resignation_status = 'approved', last_working_date = ? WHERE id = ?");
                    $update_user->execute([$last_working_date, $user_id]);
                    writeLog("Updated user resignation status");
                }

                $stmt_res = $pdo->prepare("INSERT INTO attendance (person_id, date, status) VALUES (?, ?, 'RESIGNED') ON DUPLICATE KEY UPDATE status = 'RESIGNED'");
                $stmt_res->execute([$person_id, $last_working_date]);
                writeLog("Marked last working day in attendance");

                $notif_message = '? Your resignation request has been approved. Last working day: ' . date('M d, Y', strtotime($last_working_date));
            } catch (Exception $e) {
                writeLog("Resignation processing error: " . $e->getMessage());
            }
        }

        // ===== ROSTER REQUEST =====
        if ($is_roster) {
            writeLog("========== UPDATING ROSTER ATTENDANCE ==========");

            $start_date = $request_data['start_date'] ?? '';
            $end_date = $request_data['end_date'] ?? '';

            if (!empty($start_date) && !empty($end_date)) {
                $current = new DateTime($start_date);
                $end = new DateTime($end_date);
                $updated_count = 0;

                while ($current <= $end) {
                    $loop_date = $current->format('Y-m-d');

                    if ($loop_date >= date('Y-m-d')) {
                        $check_att = $pdo->prepare("SELECT id FROM attendance WHERE person_id = ? AND date = ?");
                        $check_att->execute([$person_id, $loop_date]);

                        if ($check_att->fetch()) {
                            $update_att = $pdo->prepare("UPDATE attendance SET status = 'Scheduled' WHERE person_id = ? AND date = ?");
                            $update_att->execute([$person_id, $loop_date]);
                        } else {
                            $insert_att = $pdo->prepare("INSERT INTO attendance (person_id, date, status, check_in, check_out, work) VALUES (?, ?, 'Scheduled', '00:00:00', '00:00:00', '0.0')");
                            $insert_att->execute([$person_id, $loop_date]);
                        }
                        $updated_count++;
                    }
                    $current->modify('+1 day');
                }
                writeLog("Roster attendance updated for $updated_count days.");
            }
            $notif_message = 'Your roster request has been approved.';
        }

        // ===== SEND NOTIFICATION =====
        if (empty($notif_message)) {
            $notif_message = "? Your request has been approved. Status: $status_to_set";
        }

        $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, 'Request Approved', ?, 'approval', 0, NOW())");
        $notif_stmt->execute([$user_id, $notif_message]);
        writeLog("Notification sent: $notif_message");

    } else {
        // ===== REJECTED =====
        $notif_message = "? Your request has been rejected. Reason: $remarks";
        $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, 'Request Rejected', ?, 'approval', 0, NOW())");
        $notif_stmt->execute([$user_id, $notif_message]);
        writeLog("Rejection sent: $notif_message");
    }

    $_SESSION['message'] = ['type' => 'success', 'text' => "Request processed successfully"];

} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
}

header("Location: dashboard.php");
exit();
?>