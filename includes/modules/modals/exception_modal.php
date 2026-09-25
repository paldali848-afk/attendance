<?php
// ============================================
// EXCEPTION MODAL - WITH WEEK OFF FUNCTIONALITY (FULL FIXED CODE)
// ============================================

// ===== ADD THIS DEBUG CODE =====
error_log("=== EXCEPTION MODAL DEBUG ===");
error_log("User ID: " . ($user['id'] ?? 'NOT SET'));
error_log("Person ID: " . ($user['person_id'] ?? 'NOT SET'));
error_log("Current Month: " . date('m'));
error_log("Current Year: " . date('Y'));

// Check if attendance table has data for this user
try {
    $check_sql = "SELECT COUNT(*) FROM attendance WHERE person_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$user['person_id'], date('m'), date('Y')]);
    $total_attendance = $check_stmt->fetchColumn();
    error_log("Total attendance records for this month: " . $total_attendance);
    
    // Check Sundays specifically
    $sunday_sql = "SELECT date, check_in, work FROM attendance WHERE person_id = ? AND DAYOFWEEK(date) = 1 AND MONTH(date) = ? AND YEAR(date) = ?";
    $sunday_stmt = $pdo->prepare($sunday_sql);
    $sunday_stmt->execute([$user['person_id'], date('m'), date('Y')]);
    $sundays = $sunday_stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Sundays found in DB: " . count($sundays));
    foreach($sundays as $s) {
        error_log("  Sunday: " . $s['date'] . " - check_in: " . ($s['check_in'] ?? 'NULL') . " - work: " . ($s['work'] ?? '0'));
    }
} catch (Exception $e) {
    error_log("Debug query error: " . $e->getMessage());
}
// ===== END DEBUG CODE =====

// Check if $user is defined
if (!isset($user) || !isset($user['id'])) {
    if (isset($GLOBALS['user'])) {
        $user = $GLOBALS['user'];
    } else {
        if (function_exists('getCurrentUser')) {
            $user = getCurrentUser();
        }
    }
}

$currentPlBalance = isset($user['pl_balance']) ? (float)$user['pl_balance'] : 0;
$currentMonth = date('m');
$currentYear = date('Y');
$today = date('Y-m-d');

// ============================================
// Get ONLY Sundays where user actually worked (has check-in/out)
// ============================================
// Update the worked_sundays query to also check if the Sunday itself has been used
// Find this section in the second file and update:

// ============================================
// Get ONLY Sundays where user actually worked (has check-in/out)
// AND also check if this Sunday has been used for a WO request
// ============================================
// ============================================
// Get ONLY Sundays where user actually worked (has check-in/out)
// AND also check if this Sunday has been used for a WO request
// ============================================
$worked_sundays = [];

// Get all Sundays in current month
$date = new DateTime("$currentYear-$currentMonth-01");
$lastDay = new DateTime("$currentYear-$currentMonth-01");
$lastDay->modify('last day of this month');

while ($date <= $lastDay) {
    if ($date->format('w') == 0) { // Sunday
        $sunday_date = $date->format('Y-m-d');
        
        try {
            // Check if user has check-in on this Sunday
            $stmt = $pdo->prepare("SELECT id, check_in, check_out, work FROM attendance 
                                   WHERE person_id = ? AND date = ?");
            $stmt->execute([$user['person_id'], $sunday_date]);
            $sunday_work = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $has_checkin = false;
            
            if ($sunday_work) {
                $check_in = trim($sunday_work['check_in'] ?? '');
                $work_hours = floatval($sunday_work['work'] ?? 0);
                
                if ($check_in !== '' && $check_in !== '00:00:00' && $check_in !== 'NULL') {
                    $has_checkin = true;
                }
                
                if ($work_hours > 0) {
                    $has_checkin = true;
                }
            }
            
            if ($has_checkin) {
                // ===== CHECK IF THIS SUNDAY HAS BEEN USED FOR WEEK OFF =====
                $stmt_check = $pdo->prepare("
                    SELECT id, status FROM late_exception_requests 
                    WHERE user_id = ? 
                    AND extra_data LIKE ? 
                    AND exception_type = 'weekoff_exception'
                ");
                $stmt_check->execute([$user['id'], '%"worked_sunday":"' . $sunday_date . '"%']);
                $existing_requests = $stmt_check->fetchAll(PDO::FETCH_ASSOC);
                
                $is_approved = false;
                $is_pending = false;
                foreach ($existing_requests as $req) {
                    if ($req['status'] === 'approved') {
                        $is_approved = true;
                    } elseif ($req['status'] === 'pending') {
                        $is_pending = true;
                    }
                }
                
                $worked_sundays[] = [
                    'date' => $sunday_date,
                    'work_hours' => $sunday_work['work'] ?? 0,
                    'check_in' => $sunday_work['check_in'] ?? '00:00:00',
                    'check_out' => $sunday_work['check_out'] ?? '00:00:00',
                    'already_used' => $is_approved || $is_pending,
                    'status' => $is_approved ? 'approved' : ($is_pending ? 'pending' : 'available')
                ];
            }
        } catch (Exception $e) {
            // Log error to system log instead of local file to avoid permission issues
            error_log("WO Exception Logic Error: " . $e->getMessage());
        }
    }
    $date->modify('+1 day');
}
// ============================================
// GET USER ATTENDANCE FOR CURRENT MONTH
// ============================================
$attendance_statuses = [];
try {
    $stmt = $pdo->prepare("SELECT date, status FROM attendance WHERE person_id = ? AND MONTH(date) = ? AND YEAR(date) = ?");
    $stmt->execute([$user['person_id'], $currentMonth, $currentYear]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $attendance_statuses[$row['date']] = $row['status'];
    }
} catch (Exception $e) {
    // Ignore
}

// ============================================
// PROCESS WEEK OFF EXCEPTION REQUEST
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_exception') {
    $exception_type = $_POST['exception_type'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $selected_dates_raw = $_POST['selected_dates'] ?? '';
    $single_date = $_POST['date'] ?? '';

    try {
        $pdo->beginTransaction();
        
        if ($exception_type === 'weekoff_exception') {
            $worked_sunday = $_POST['worked_sunday'] ?? '';
            $desired_weekoff = $_POST['desired_weekoff'] ?? '';
            
            if ($worked_sunday && $desired_weekoff) {
                $sunday_check = date('w', strtotime($worked_sunday));
                if ($sunday_check != 0) {
                    throw new Exception('Selected date is not a Sunday.');
                }
                
                $stmt = $pdo->prepare("SELECT id, check_in, check_out, work FROM attendance 
                                       WHERE person_id = ? AND date = ?");
                $stmt->execute([$user['person_id'], $worked_sunday]);
                $sunday_work = $stmt->fetch();
                
                $has_work = false;
                if ($sunday_work) {
                    $check_in = $sunday_work['check_in'] ?? '';
                    $work_hours = (float)($sunday_work['work'] ?? 0);
                    
                    if (!empty($check_in) && $check_in !== '00:00:00' && $check_in !== '') {
                        $has_work = true;
                    }
                    if ($work_hours > 0) {
                        $has_work = true;
                    }
                }
                
                if (!$has_work) {
                    throw new Exception('You were not marked as working on the selected Sunday.');
                }
                
                $desired_check = date('w', strtotime($desired_weekoff));
                if ($desired_check == 0) {
                    throw new Exception('Cannot select Sunday as Week Off.');
                }
                
                $desired_month = date('m', strtotime($desired_weekoff));
                if ($desired_month != $currentMonth) {
                    throw new Exception('Week Off must be taken in the same month.');
                }
                
                $stmt = $pdo->prepare("SELECT id FROM late_exception_requests 
                                       WHERE user_id = ? AND date = ? AND exception_type = 'weekoff_exception' 
                                       AND status != 'rejected'");
                $stmt->execute([$user['id'], $desired_weekoff]);
                if ($stmt->fetch()) {
                    throw new Exception('A Week Off exception request already exists for this date.');
                }
                
                $stmt = $pdo->prepare("SELECT id FROM late_exception_requests 
                                       WHERE user_id = ? AND extra_data LIKE ? 
                                       AND exception_type = 'weekoff_exception' AND status = 'approved'");
                $stmt->execute([$user['id'], '%"worked_sunday":"' . $worked_sunday . '"%']);
                if ($stmt->fetch()) {
                    throw new Exception('This Sunday has already been used for a Week Off exception.');
                }
                
                $existing_status = $attendance_statuses[$desired_weekoff] ?? null;
                $is_before_sunday = ($desired_weekoff < $worked_sunday);
                
                if ($is_before_sunday) {
                    $valid_statuses = ['A', 'Absent', 'L', 'Late', 'HD', 'Half Day', 'HPE', 'FPE', ''];
                    if (!empty($existing_status) && !in_array($existing_status, $valid_statuses)) {
                        throw new Exception('This date has status "' . $existing_status . '". Cannot apply Week Off exception.');
                    }
                } else {
                    $valid_statuses = ['A', 'Absent', ''];
                    if (!empty($existing_status) && !in_array($existing_status, $valid_statuses)) {
                        throw new Exception('This date has status "' . $existing_status . '". Cannot apply Week Off exception.');
                    }
                }
                
                $approver_id = $user['reporting_to'] ?? 1;
                $insert = $pdo->prepare("INSERT INTO late_exception_requests 
                                         (user_id, person_id, date, exception_type, status, reason, approved_by, created_at) 
                                         VALUES (?, ?, ?, 'weekoff_shift', 'pending', ?, ?, NOW())");
                $insert->execute([$user['id'], $user['person_id'], $desired_weekoff, $reason, $approver_id]);
                
                $request_id = $pdo->lastInsertId();
                
                $extra_data = json_encode([
                    'worked_sunday' => $worked_sunday,
                    'weekoff_exception_type' => 'weekoff_shift',
                    'is_before_sunday' => $is_before_sunday,
                    'current_status' => $existing_status,
                    'sunday_work_hours' => $sunday_work['work'] ?? 0
                ]);
                
                $update = $pdo->prepare("UPDATE late_exception_requests SET extra_data = ? WHERE id = ?");
                $update->execute([$extra_data, $request_id]);
                
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Week Off Exception request submitted for approval.'];
            }
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
    }
    
    header("Location: dashboard.php");
    exit();
}
?>
<!-- ===== LOAD FLATPICKR LIBRARY (CSS & JS) ===== -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 99999;
        align-items: center;
        justify-content: center;
    }
    .modal-overlay.show { display: flex !important; }
    .modal-box {
        background: #ffffff;
        border-radius: 16px;
        padding: 28px 32px;
        overflow: visible !important;
        max-height: 85vh;
        width: 560px;
        max-width: 95%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: fadeUp 0.3s ease;
    }
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e8edf4;
    }
    .modal-header h3 {
        font-size: 17px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #0a1628;
    }
    .modal-header h3 i { color: #d97706; }
    .close-btn {
        background: #f4f6fa;
        border: 1px solid #e8edf4;
        font-size: 18px;
        cursor: pointer;
        color: #1e293b;
        transition: 0.3s;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .close-btn:hover {
        background: #fee2e2;
        color: #dc2626;
        border-color: #dc2626;
        transform: rotate(90deg);
    }
    .selected-date-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #dbeafe;
        color: #2563eb;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        border: 1px solid #2563eb;
    }
    .selected-date-badge .remove-btn {
        background: none;
        border: none;
        color: #dc2626;
        cursor: pointer;
        font-size: 12px;
        padding: 0 2px;
    }
    .selected-date-badge .remove-btn:hover { transform: scale(1.2); }
    .request-type-select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #e8edf4;
        border-radius: 10px;
        font-size: 14px;
        transition: 0.3s;
        background: #ffffff;
        color: #0a1628;
        font-weight: 600;
    }
    .request-type-select:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    .flatpickr-calendar {
        position: fixed !important;
        z-index: 999999 !important;
        margin-top: 10px !important;
    }
    .flatpickr-current-month { pointer-events: none !important; }
    .flatpickr-current-month .flatpickr-monthDropdown-months,
    .flatpickr-current-month .numInputWrapper { pointer-events: auto !important; }
    .flatpickr-current-month .numInputWrapper span { display: none !important; }
    .wo-options {
        display: none;
        background: #f0f7ff;
        padding: 12px;
        border-radius: 10px;
        margin-bottom: 10px;
        border: 1px solid #dbeafe;
    }
    .wo-options.show { display: block; }
    .wo-options label {
        display: block;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 6px;
        font-size: 12px;
    }
    .wo-options .info-text {
        font-size: 11px;
        color: #64748b;
        margin-bottom: 8px;
        font-weight: 500;
    }
    .wo-options select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #e8edf4;
        border-radius: 8px;
        font-size: 13px;
        background: #ffffff;
    }
    .weekoff-info {
        background: #dbeafe;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 11px;
        color: #1e40af;
        font-weight: 600;
        margin-top: 6px;
    }
    .info-box {
        background: #fef3c7;
        padding: 10px 14px;
        border-radius: 10px;
        margin-bottom: 14px;
        border-left: 3px solid #d97706;
    }
    .info-box p {
        font-size: 12px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }
    .btn-ot-apply {
        background: linear-gradient(135deg, #2563eb, #3b82f6);
        color: #fff;
        border: none;
        padding: 3px 10px;
        border-radius: 50px;
        font-size: 9px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
        margin-top: 4px;
        width: 100%;
        white-space: nowrap;
    }
    .btn-ot-apply:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 12px rgba(37, 99, 235, 0.3);
    }
    .btn-ot-apply i { font-size: 8px; margin-right: 3px; }
    .ot-status-badge {
        font-size: 9px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 50px;
        display: inline-block;
        margin-top: 2px;
        width: 100%;
        text-align: center;
    }
    .ot-status-badge.pending { background: #fef3c7; color: #92400e; }
    .ot-status-badge.approved { background: #dcfce7; color: #16a34a; }
    .ot-status-badge.rejected { background: #fee2e2; color: #dc2626; }
    .ot-not-eligible {
        font-size: 8px;
        color: #94a3b8;
        display: block;
        text-align: center;
        margin-top: 2px;
        cursor: help;
    }


.status-badge.weekoff {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #2563eb;
}

.legend-color.weekoff {
    background: #dbeafe;
    border: 2px solid #2563eb;
}
</style>

<!-- ===== EXCEPTION MODAL HTML ===== -->
<div id="exceptionModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-circle"></i> REQUEST EXCEPTION</h3>
            <button class="close-btn" onclick="closeExceptionModal()">&times;</button>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <p style="color:#1e293b;font-size:13px;font-weight:600;margin:0;">Request an exception for this date.</p>
            <span id="finalPlBadge" style="background: #dbeafe; color: #2563eb; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: none; border: 1px solid #2563eb;">
                PL Balance: <?php echo number_format($currentPlBalance, 1); ?>
            </span>
        </div>

        <form method="POST" id="final_conflict_free_form" action="dashboard.php">
            <input type="hidden" name="action" value="submit_exception">
            <input type="hidden" name="date" id="final_hidden_date">
            <input type="hidden" name="current_status" id="final_hidden_status">
            <input type="hidden" name="selected_dates" id="final_hidden_selected_dates">
            <input type="hidden" id="final_user_pl_balance" value="<?php echo $currentPlBalance; ?>">
            <input type="hidden" name="original_work_date" id="final_original_work_date">
            <input type="hidden" name="weekoff_exception_type" id="final_weekoff_type">

            <div style="margin-bottom:10px;">
                <label style="font-weight:700;display:block;margin-bottom:4px;color:#64748b;font-size:11px;">EXCEPTION TYPE</label>
                <select name="exception_type" id="final_type_select" class="request-type-select" required onchange="handleTypeChange()">
                     <option value="half_day">Half Day</option>
                    <option value="full_day">Full Day</option>
                    <option value="pl_adjustment">PL Adjustment</option>
                    <option value="weekoff_exception">Week Off Exception</option>
                </select>
            </div>

            <!-- PL Selector -->
            <div id="final_pl_selector" style="display:none; margin-bottom:10px;">
                <div style="display:flex;gap:8px;margin-bottom:8px;">
                    <input type="text" id="final_pl_picker" placeholder="Pick Date" style="flex:1;padding:8px 12px;border:1px solid #e8edf4;border-radius:10px;">
                    <button type="button" onclick="addSelectedDate()" style="background:#2563eb;color:#fff;border:none;padding:8px 16px;border-radius:10px;cursor:pointer;font-weight:700;">ADD</button>
                    <button type="button" onclick="clearAllDates()" style="background:#fee2e2;color:#dc2626;border:1px solid #dc2626;padding:8px 16px;border-radius:10px;cursor:pointer;font-weight:700;">CLEAR</button>
                </div>
                <div id="final_badge_container" style="padding:8px;background:#f4f6fa;border-radius:10px;min-height:40px;border:1px solid #e8edf4;margin-bottom:5px;"></div>
                <small style="font-size:10px;color:#64748b;">Selected: <span id="final_count_display">0</span> | Needed: <span id="final_needed_display">0.0</span></small>
            </div>

            <!-- ===== WEEK OFF SELECTOR ===== -->
            <div id="final_wo_selector" style="display:none; margin-bottom:10px; background:#f0f7ff; padding:12px; border-radius:10px; border:1px solid #dbeafe;">
                <div style="margin-bottom:8px;">
                    <label style="font-size:11px;font-weight:700;color:#1e293b;display:block;margin-bottom:4px;">
                        <i class="fas fa-calendar-check" style="color:#2563eb;"></i> SUNDAY YOU WORKED
                    </label>
                    <select id="final_worked_sunday" name="worked_sunday" class="request-type-select" onchange="updateAvailableWeekOffDates()" style="width:100%;padding:8px 12px;border:1px solid #e8edf4;border-radius:10px;font-size:14px;background:#ffffff;">
                        <option value="">-- Select Sunday --</option>
                       // Update the dropdown options in the week off selector (around line 300):
<?php if (count($worked_sundays) > 0): ?>
    <?php foreach ($worked_sundays as $sunday_data): ?>
        <option value="<?php echo $sunday_data['date']; ?>" 
                <?php 
                $disabled = $sunday_data['already_used'] || $sunday_data['status'] === 'approved' || $sunday_data['status'] === 'pending';
                echo $disabled ? 'disabled style="color:#94a3b8;"' : 'style="color:#16a34a;font-weight:600;"'; 
                ?>>
            <?php echo date('M d, Y (l)', strtotime($sunday_data['date'])); ?>
            - <?php echo number_format($sunday_data['work_hours'], 1); ?> hrs
            <?php if ($sunday_data['status'] === 'approved'): ?>
                ? APPROVED - Used
            <?php elseif ($sunday_data['status'] === 'pending'): ?>
                ? PENDING - Wait for approval
            <?php elseif ($sunday_data['already_used']): ?>
                ? Already Used
            <?php else: ?>
                ? Available
            <?php endif; ?>
        </option>
    <?php endforeach; ?>
<?php else: ?>
    <option value="" disabled style="color:#dc2626;font-weight:600;">
        ? No Sundays found where you worked this month
    </option>
<?php endif; ?>
                    </select>
                    
                    <?php if (count($worked_sundays) == 0): ?>
                        <div style="margin-top:8px; padding:10px 14px; border-radius:8px; background:#fef3c7; border:1px solid #f59e0b; font-size:12px; color:#92400e;">
                            <i class="fas fa-exclamation-triangle" style="color:#d97706;"></i> 
                            <strong>No worked Sundays found!</strong><br>
                            <span style="font-size:11px;">You haven't worked on any Sunday this month.</span>
                        </div>
                    <?php else: ?>
                        <div style="margin-top:8px; padding:8px 12px; border-radius:8px; background:#dbeafe; border:1px solid #93c5fd; font-size:11px; color:#1e40af; font-weight:600;">
                            <i class="fas fa-info-circle"></i> 
                            Showing <strong><?php echo count($worked_sundays); ?></strong> Sunday(s) where you worked.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label style="font-size:11px;font-weight:700;color:#1e293b;display:block;margin-bottom:4px;">
                        <i class="fas fa-calendar-day" style="color:#2563eb;"></i> DESIRED WEEK OFF DATE
                    </label>
                    <select id="final_desired_off" name="desired_weekoff" class="request-type-select" style="width:100%;padding:8px 12px;border:1px solid #e8edf4;border-radius:10px;font-size:14px;background:#ffffff;">
                        <option value="">-- Select Date --</option>
                        <?php
                        $date = new DateTime("$currentYear-$currentMonth-01");
                        $lastDay = new DateTime("$currentYear-$currentMonth-01");
                        $lastDay->modify('last day of this month');
                        while ($date <= $lastDay) {
                            if ($date->format('w') != 0) {
                                $dStr = $date->format('Y-m-d');
                                $status = $attendance_statuses[$dStr] ?? '';
                                $status_text = '';
                                if (empty($status)) $status_text = '?? No Data';
                                elseif (in_array($status, ['A', 'Absent'])) $status_text = '? Absent';
                                elseif (in_array($status, ['L', 'Late'])) $status_text = '?? Late';
                                elseif (in_array($status, ['HD', 'Half Day', 'HPE'])) $status_text = '?? Half Day';
                                elseif (in_array($status, ['FPE'])) $status_text = '?? Full Day Exception';
                                elseif (in_array($status, ['P', 'Present'])) $status_text = '? Present';
                                else $status_text = '? ' . $status;
                                echo '<option value="'.$dStr.'" data-status="'.$status.'">';
                                echo date('M d, Y (l)', strtotime($dStr)) . ' - ' . $status_text;
                                echo '</option>';
                            }
                            $date->modify('+1 day');
                        }
                        ?>
                    </select>
                    <div id="wo_status_warning" style="display:none; margin-top:8px; padding:8px 12px; border-radius:8px; font-size:11px; font-weight:600;"></div>
                </div>
            </div>
            
            <!-- ===== HIDDEN FIELDS - MUST BE OUTSIDE THE WEEK OFF SELECTOR DIV ===== -->
            <input type="hidden" name="worked_sunday" id="final_worked_sunday_hidden" value="">
            <input type="hidden" name="desired_weekoff" id="final_desired_weekoff_hidden" value="">

            <div style="margin-bottom:10px;">
                <label style="font-weight:700;display:block;margin-bottom:4px;color:#64748b;font-size:11px;">REASON</label>
                <textarea name="reason" id="final_reason_box" required placeholder="Describe your reason..." style="width:100%;height:60px;padding:8px 12px;border:1px solid #e8edf4;border-radius:10px;"></textarea>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="closeExceptionModal()" style="padding:8px 24px;border-radius:50px;cursor:pointer;border:1px solid #e2e8f0;background:#fff;font-weight:600;">CANCEL</button>
                <button type="button" id="force_final_submit_btn" style="background:linear-gradient(135deg, #2563eb, #7c3aed);color:#fff;padding:8px 24px;border-radius:50px;cursor:pointer;font-weight:700;border:none;">SUBMIT</button>
            </div>

        </form>

        <!-- ===== OT REQUEST MODAL ===== -->
        <div id="otModal" class="modal-overlay">
            <div class="modal-box">
                <div class="modal-header">
                    <h3><i class="fas fa-clock" style="color:#2563eb;"></i> REQUEST OVERTIME</h3>
                    <button class="close-btn" onclick="closeOTModal()">&times;</button>
                </div>
                
                <div style="padding: 10px 0;">
                    <div style="background: #e0f2fe; padding: 12px; border-radius: 10px; margin-bottom: 15px; border-left: 4px solid #2563eb;">
                        <div style="display: flex; justify-content: space-between; font-size: 14px;">
                            <span><strong>Date:</strong> <span id="otDateDisplay"></span></span>
                            <span><strong>Work Hours:</strong> <span id="otWorkHoursDisplay"></span></span>
                            <span><strong>OT Hours:</strong> <span id="otHoursDisplay" style="color:#2563eb; font-weight:800;"></span></span>
                        </div>
                        <div style="margin-top: 6px; font-size: 12px; color: #64748b;">
                            <i class="fas fa-info-circle"></i> 
                            OT is calculated only for <strong>full hours</strong> (1h, 2h, 3h...). 
                            Extra minutes are not counted.
                        </div>
                    </div>
                    
                    <form method="POST" id="otForm" action="dashboard.php">
                        <input type="hidden" name="action" value="submit_overtime">
                        <input type="hidden" id="otDate" name="date">
                        <input type="hidden" id="otWorkHours" name="work_hours">
                        <input type="hidden" id="otHours" name="ot_hours">
                        
                        <div style="margin-bottom: 12px;">
                            <label style="font-weight:700;display:block;margin-bottom:4px;color:#64748b;font-size:11px;letter-spacing:0.5px;">
                                REASON FOR OVERTIME
                            </label>
                            <textarea name="reason" id="otReason" required 
                                placeholder="Describe why you need OT approval..." 
                                style="width:100%;height:80px;padding:10px 12px;border:1px solid #e8edf4;border-radius:10px;font-family:inherit;font-size:13px;resize:vertical;transition:0.3s;background:#ffffff;color:#0a1628;font-weight:500;"></textarea>
                        </div>
                        
                        <div style="background: #fef3c7; padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; border-left: 3px solid #d97706;">
                            <div style="font-size: 12px; color: #92400e; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-clock"></i>
                                <span>You can request OT only until <strong>tomorrow</strong>. After that, the request will expire.</span>
                            </div>
                        </div>
                        
                        <div style="display:flex;gap:8px;justify-content:flex-end;">
                            <button type="button" class="btn-cancel-modal" onclick="closeOTModal()" 
                                style="background:#f4f6fa;color:#1e293b;border:1px solid #e8edf4;padding:8px 24px;border-radius:50px;cursor:pointer;font-size:12px;font-weight:700;transition:0.3s;">
                                CANCEL
                            </button>
                            <button type="submit" id="otSubmitBtn" 
                                style="background:linear-gradient(135deg, #2563eb, #7c3aed);color:#fff;border:none;padding:8px 24px;border-radius:50px;cursor:pointer;font-size:12px;font-weight:700;transition:0.3s;box-shadow:0 2px 10px rgba(37,99,235,0.2);">
                                <i class="fas fa-paper-plane"></i> SUBMIT OT REQUEST
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// WEEK OFF DATE VALIDATION
// ============================================

const workedSundaysData = <?php echo json_encode($worked_sundays); ?>;

console.log('=== WO Exception Debug ===');
console.log('Worked Sundays Data:', workedSundaysData);
console.log('Total worked Sundays:', workedSundaysData.length);

function updateAvailableWeekOffDates() {
    const workedSunday = document.getElementById('final_worked_sunday');
    const desiredOff = document.getElementById('final_desired_off');
    const warningDiv = document.getElementById('wo_status_warning');
    
    warningDiv.style.display = 'none';
    warningDiv.className = '';
    
    if (!workedSunday || !workedSunday.value) {
        warningDiv.style.display = 'block';
        warningDiv.style.background = '#fef3c7';
        warningDiv.style.color = '#92400e';
        warningDiv.style.border = '1px solid #d97706';
        warningDiv.innerHTML = '?? Please select a Sunday you worked on first.';
        return;
    }
    
    const selectedValue = workedSunday.value;
    const selectedSunday = workedSundaysData.find(s => s.date === selectedValue);
    
    if (selectedSunday && selectedSunday.already_used) {
        warningDiv.style.display = 'block';
        warningDiv.style.background = '#fee2e2';
        warningDiv.style.color = '#dc2626';
        warningDiv.style.border = '1px solid #dc2626';
        warningDiv.innerHTML = '? This Sunday has already been used for a Week Off exception.';
        return;
    }
    
    const options = desiredOff.options;
    let sundayDate = new Date(selectedValue + 'T00:00:00');
    let sundayTimestamp = sundayDate.getTime();
    let hasValidOptions = false;
    let validBeforeCount = 0;
    let validAfterCount = 0;
    
    for (let i = 0; i < options.length; i++) {
        const option = options[i];
        if (!option.value) continue;
        
        const optionDate = new Date(option.value + 'T00:00:00');
        const optionTimestamp = optionDate.getTime();
        const status = option.getAttribute('data-status') || '';
        
        const sameMonth = (optionDate.getMonth() === sundayDate.getMonth() && 
                          optionDate.getFullYear() === sundayDate.getFullYear());
        const isSunday = optionDate.getDay() === 0;
        const isBefore = optionTimestamp < sundayTimestamp;
        const isAfter = optionTimestamp > sundayTimestamp;
        
        let isValid = false;
        let reason = '';
        
        if (!sameMonth) {
            reason = '? Different month';
        } else if (isSunday) {
            reason = '? Cannot select Sunday';
        } else if (isBefore) {
            const validBeforeStatuses = ['A', 'Absent', 'L', 'Late', 'HD', 'Half Day', 'HPE', 'FPE', ''];
            if (validBeforeStatuses.includes(status)) {
                isValid = true;
                validBeforeCount++;
                reason = '? Valid (before Sunday)';
            } else {
                reason = '? Status: ' + (status || 'No Data');
            }
        } else if (isAfter) {
            const validAfterStatuses = ['A', 'Absent', ''];
            if (validAfterStatuses.includes(status)) {
                isValid = true;
                validAfterCount++;
                reason = '? Valid (after Sunday)';
            } else {
                reason = '? Status: ' + (status || 'No Data');
            }
        }
        
        if (isValid) {
            option.style.color = '#16a34a';
            option.style.fontWeight = '600';
            hasValidOptions = true;
        } else {
            option.style.color = '#94a3b8';
            option.style.fontWeight = '400';
        }
        
        let cleanText = option.textContent.split(' - ')[0] || option.textContent;
        if (cleanText.includes('?') || cleanText.includes('?')) {
            cleanText = cleanText.replace(/?|?/g, '').trim();
        }
        option.textContent = cleanText + ' - ' + reason;
    }
    
    if (!hasValidOptions) {
        warningDiv.style.display = 'block';
        warningDiv.style.background = '#fee2e2';
        warningDiv.style.color = '#dc2626';
        warningDiv.style.border = '1px solid #dc2626';
        warningDiv.innerHTML = '?? No valid dates available. Valid dates must have status: A, L, HD (before Sunday) or A (after Sunday).';
    } else {
        warningDiv.style.display = 'block';
        warningDiv.style.background = '#dcfce7';
        warningDiv.style.color = '#16a34a';
        warningDiv.style.border = '1px solid #16a34a';
        warningDiv.innerHTML = '? ' + validBeforeCount + ' date(s) BEFORE Sunday, ' + validAfterCount + ' date(s) AFTER Sunday.';
    }
    
    desiredOff.value = '';
}

// ============================================
// MODAL FUNCTIONS
// ============================================
function closeExceptionModal() {
    const modal = document.getElementById('exceptionModal');
    if (modal) modal.classList.remove('show');
}

function openExceptionModalForDate(date, status) {
    const modal = document.getElementById('exceptionModal');
    if (!modal) return;
    
    document.getElementById('final_hidden_date').value = date;
    document.getElementById('final_hidden_status').value = status || 'No Data';
    document.getElementById('final_reason_box').value = '';
    document.getElementById('final_type_select').value = 'late';
    document.getElementById('final_pl_selector').style.display = 'none';
    document.getElementById('final_wo_selector').style.display = 'none';
    modal.classList.add('show');
}

function handleTypeChange() {
    const type = document.getElementById('final_type_select').value;
    const plSelector = document.getElementById('final_pl_selector');
    const woSelector = document.getElementById('final_wo_selector');
    const plBadge = document.getElementById('finalPlBadge');
    
    plSelector.style.display = 'none';
    woSelector.style.display = 'none';
    plBadge.style.display = 'none';
    
    if (type === 'pl_adjustment') {
        plSelector.style.display = 'block';
        plBadge.style.display = 'inline-block';
    } else if (type === 'weekoff_exception') {
        woSelector.style.display = 'block';
        const workedSunday = document.getElementById('final_worked_sunday');
        if (workedSunday && workedSunday.value) {
            setTimeout(updateAvailableWeekOffDates, 100);
        }
    }
}

// ============================================
// SUBMIT BUTTON HANDLER - FIXED
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.getElementById('force_final_submit_btn');
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const exceptionType = document.getElementById('final_type_select').value;
            
            if (exceptionType === 'weekoff_exception') {
                const workedSunday = document.getElementById('final_worked_sunday').value;
                const desiredOff = document.getElementById('final_desired_off').value;
                const reason = document.getElementById('final_reason_box').value.trim();
                
                if (!workedSunday) {
                    alert('Please select the Sunday you worked.');
                    return;
                }
                
                if (!desiredOff) {
                    alert('Please select the date you want as Week Off.');
                    return;
                }
                
                if (!reason) {
                    alert('Please provide a reason for the Week Off exception.');
                    return;
                }
                
                // Set the hidden fields
                document.getElementById('final_worked_sunday_hidden').value = workedSunday;
                document.getElementById('final_desired_weekoff_hidden').value = desiredOff;
                
                console.log('Submitting Week Off Exception:');
                console.log('Worked Sunday:', workedSunday);
                console.log('Desired Week Off:', desiredOff);
                console.log('Reason:', reason);
            }
            
            document.getElementById('final_conflict_free_form').submit();
        });
    }
});

// ============================================
// PL ADJUSTMENT FUNCTIONS
// ============================================
let selectedDates = [];
let fpInstance = null;

function addSelectedDate() {
    if (!fpInstance || fpInstance.selectedDates.length === 0) {
        alert('Please select a date first.');
        return;
    }
    const date = fpInstance.selectedDates[0];
    const dateStr = date.toISOString().split('T')[0];
    
    if (selectedDates.includes(dateStr)) {
        alert('Date already added.');
        return;
    }
    
    const balance = parseFloat(document.getElementById('final_user_pl_balance').value);
    if (selectedDates.length + 1 > balance) {
        alert('Insufficient PL balance.');
        return;
    }
    
    selectedDates.push(dateStr);
    updateBadgeContainer();
    fpInstance.clear();
}

function removeSelectedDate(date) {
    selectedDates = selectedDates.filter(d => d !== date);
    updateBadgeContainer();
}

function clearAllDates() {
    if (selectedDates.length === 0) return;
    if (confirm('Clear all selected dates?')) {
        selectedDates = [];
        updateBadgeContainer();
    }
}

function updateBadgeContainer() {
    const container = document.getElementById('final_badge_container');
    document.getElementById('final_count_display').textContent = selectedDates.length;
    document.getElementById('final_needed_display').textContent = selectedDates.length.toFixed(1);
    document.getElementById('final_hidden_selected_dates').value = selectedDates.join(',');
    
    if (selectedDates.length === 0) {
        container.innerHTML = '<span style="color:#64748b;font-size:12px;">No dates selected.</span>';
        return;
    }
    
    let html = '';
    selectedDates.sort().forEach(date => {
        const d = new Date(date + 'T00:00:00');
        const display = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        html += `<span class="selected-date-badge">${display}<button type="button" class="remove-btn" onclick="removeSelectedDate('${date}')">&times;</button></span>`;
    });
    container.innerHTML = html;
}

// ============================================
// OT MODAL FUNCTIONS
// ============================================
function openOTModal(date, workHours, otHours) {
    const modal = document.getElementById('otModal');
    document.getElementById('otDate').value = date;
    document.getElementById('otWorkHours').value = workHours;
    document.getElementById('otHours').value = otHours;
    
    const d = new Date(date + 'T00:00:00');
    document.getElementById('otDateDisplay').textContent = d.toLocaleDateString('en-US', { 
        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' 
    });
    document.getElementById('otWorkHoursDisplay').textContent = formatWorkHoursDisplay(workHours);
    document.getElementById('otHoursDisplay').textContent = otHours + 'h';
    document.getElementById('otReason').value = '';
    modal.classList.add('show');
}

function closeOTModal() {
    document.getElementById('otModal').classList.remove('show');
}

function formatWorkHoursDisplay(hours) {
    if (hours <= 0) return '00:00';
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
}

// ============================================
// INITIALIZE
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    fpInstance = flatpickr("#final_pl_picker", {
        dateFormat: "Y-m-d",
        disableMobile: true,
        minDate: "today",
        allowInput: false
    });
    
    const workedSunday = document.getElementById('final_worked_sunday');
    if (workedSunday) {
        workedSunday.addEventListener('change', updateAvailableWeekOffDates);
        if (workedSunday.value) setTimeout(updateAvailableWeekOffDates, 100);
    }
});

// Expose functions globally
window.closeExceptionModal = closeExceptionModal;
window.openExceptionModalForDate = openExceptionModalForDate;
window.handleTypeChange = handleTypeChange;
window.addSelectedDate = addSelectedDate;
window.removeSelectedDate = removeSelectedDate;
window.clearAllDates = clearAllDates;
window.updateAvailableWeekOffDates = updateAvailableWeekOffDates;
window.openOTModal = openOTModal;
window.closeOTModal = closeOTModal;
</script>