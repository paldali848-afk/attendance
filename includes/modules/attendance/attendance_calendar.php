<?php
// ============================================
// ATTENDANCE CALENDAR DISPLAY - WITH OT & HOLIDAYS
// ============================================

// 1. Fetch holidays for the selected month to check locally
$stmt_h = $pdo->prepare("SELECT holiday_date, holiday_name FROM holidays WHERE YEAR(holiday_date) = ? AND MONTH(holiday_date) = ?");
$stmt_h->execute([$selected_year, $selected_month]);
$calendar_holidays = $stmt_h->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

// Fetch all approved rosters for this user for the current month view
$stmt_month_rosters = $pdo->prepare("SELECT start_date, end_date FROM roster_requests 
                                     WHERE person_id = ? AND status = 'approved' 
                                     AND (start_date <= LAST_DAY(?) AND end_date >= ?)");
$cal_start_date = "$selected_year-$selected_month-01";
$stmt_month_rosters->execute([$user['person_id'], $cal_start_date, $cal_start_date]);
$all_rosters = $stmt_month_rosters->fetchAll(PDO::FETCH_ASSOC);
// Fetch all approved exceptions/leaves for this user for the current month view
$stmt_month_exceptions = $pdo->prepare("SELECT date, exception_type FROM late_exception_requests 
                                         WHERE (person_id = ? OR user_id = ?) AND status = 'approved' 
                                         AND YEAR(date) = ? AND MONTH(date) = ?");
$stmt_month_exceptions->execute([$user['person_id'], $user['id'], $selected_year, $selected_month]);
$all_exceptions = $stmt_month_exceptions->fetchAll(PDO::FETCH_KEY_PAIR);

// ============================================
// AUTO-DETECT SANDWICH ABSENCES FOR THIS MONTH
// Office week: Mon-Sat working, Sun off
// Anchor: Saturday off ? Sunday gets A
// ============================================

$calendar_sandwich_dates = [];

$month_start_str = "$selected_year-$selected_month-01";
$month_end_str   = date('Y-m-t', strtotime($month_start_str));

// 1. PH dates in range + 3-day buffer
$ph_dates_cal = [];
$ph_stmt_cal = $pdo->prepare("
    SELECT holiday_date FROM holidays
    WHERE holiday_date BETWEEN DATE_SUB(?, INTERVAL 3 DAY)
                           AND DATE_ADD(?, INTERVAL 3 DAY)
");
$ph_stmt_cal->execute([$month_start_str, $month_end_str]);
while ($row = $ph_stmt_cal->fetch(PDO::FETCH_ASSOC)) {
    $ph_dates_cal[$row['holiday_date']] = true;
}

// 2. Off-days from attendance
$off_days_cal = [];
$off_stmt_cal = $pdo->prepare("
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
$off_stmt_cal->execute([$user['person_id'], $month_start_str, $month_end_str]);
while ($row = $off_stmt_cal->fetch(PDO::FETCH_ASSOC)) {
    $off_days_cal[$row['date']] = true;
}

// 3. Scan each Saturday in the window
$scan_cal     = new DateTime($month_start_str);
$scan_cal->modify('-3 days');
$scan_cal_end = new DateTime($month_end_str);
$scan_cal_end->modify('+3 days');

while ($scan_cal <= $scan_cal_end) {
    if ((int)$scan_cal->format('N') !== 6) {
        $scan_cal->modify('+1 day');
        continue;
    }

    $sat_cal = $scan_cal->format('Y-m-d');
    $sun_cal = date('Y-m-d', strtotime($sat_cal . ' +1 day'));
    $mon_cal = date('Y-m-d', strtotime($sat_cal . ' +2 days'));
    $tue_cal = date('Y-m-d', strtotime($sat_cal . ' +3 days'));

    if (!isset($off_days_cal[$sat_cal])) {
        $scan_cal->modify('+1 day');
        continue;
    }

    if (isset($ph_dates_cal[$sat_cal]) || isset($ph_dates_cal[$sun_cal])) {
        $scan_cal->modify('+1 day');
        continue;
    }

    $mon_is_ph_cal = isset($ph_dates_cal[$mon_cal]);

    if (!$mon_is_ph_cal) {
        if (isset($off_days_cal[$mon_cal])) {
            $calendar_sandwich_dates[$sun_cal] = true;
        }
    } else {
        if (isset($off_days_cal[$tue_cal])) {
            $calendar_sandwich_dates[$sun_cal] = true;
            $calendar_sandwich_dates[$tue_cal] = true;
        }
    }

    $scan_cal->modify('+1 day');
}

// Include OT functions with error handling
$attendance_stats_file = __DIR__ . '/attendance_stats.php';
if (file_exists($attendance_stats_file)) {
    require_once $attendance_stats_file;
} else {
    if (!function_exists('getOvertimeData')) {
        function getOvertimeData($pdo, $person_id, $year, $month, $role) {
            return ['total_ot' => 0, 'details' => []];
        }
    }
    if (!function_exists('getTotalOvertime')) {
        function getTotalOvertime($pdo, $person_id, $year, $month, $role) {
            return 0;
        }
    }
}


if (!function_exists('formatWorkHours')) {
    function formatWorkHours($hours) {
        if ($hours <= 0) {
            return '00:00';
        }
        
        $whole_hours = floor($hours);
        $minutes = round(($hours - $whole_hours) * 60);
        
        if ($minutes >= 60) {
            $whole_hours += 1;
            $minutes = 0;
        }
        
        return sprintf("%02d:%02d", $whole_hours, $minutes);
    }
}

// Check if user is in the IT department
$user_dept = $user['department'] ?? ''; 
$is_it_dept = (strtolower($user_dept) === 'it');

// Get user role for duty hours
$user_role = $user['role'] ?? 'agent';
$duty_hours = ($user_role === 'process_head') ? 8 : 9;

// Get OT data for the user with error handling
try {
    $user_ot_data = function_exists('getOvertimeData') ? 
        getOvertimeData($pdo, $user['person_id'], $selected_year, $selected_month, $user_role) : 
        ['total_ot' => 0, 'details' => []];
    $total_ot = function_exists('getTotalOvertime') ? 
        getTotalOvertime($pdo, $user['person_id'], $selected_year, $selected_month, $user_role) : 
        0;
} catch (Exception $e) {
    $user_ot_data = ['total_ot' => 0, 'details' => []];
    $total_ot = 0;
}

// ===== GET RESIGNATION DATA FOR HIGHLIGHT =====
$resignation_last_working_date = null;
$resignation_status = null;
$resignation_employee_name = '';

if (file_exists(__DIR__ . '/../resignation/resignation_functions.php')) {
    require_once __DIR__ . '/../resignation/resignation_functions.php';
    if (function_exists('getUserResignation')) {
        $resignation = getUserResignation($pdo, $user['id'] ?? 0);
        if ($resignation && $resignation['status'] === 'approved') {
            $resignation_last_working_date = $resignation['last_working_date'];
            $resignation_status = 'approved';
            $resignation_employee_name = $resignation['employee_name'] ?? $user['full_name'] ?? 'Employee';
        }
    }
}

?>
<style>
    /* Status Colors for Calendar */
    .calendar-day.status-ph { background: linear-gradient(135deg, #fef3c7, #fde68a) !important; border: 1px solid #d97706 !important; }
    .calendar-day.status-ph .status-code { color: #b45309 !important; font-weight: 800; }
    
    .calendar-day.status-hpl { background: #ede9fe !important; border: 1px solid #8b5cf6 !important; }
    .calendar-day.status-hpl .status-code { color: #5b21b6 !important; }

    /* Tooltip styling */
    .status-badge.ph { background: #fef3c7; color: #b45309; }

    /* ============================================
       SANDWICH LEAVE STYLES
       ============================================ */
    .calendar-day.status-sw {
        background: linear-gradient(135deg, #fef3c7, #fde68a) !important;
        border: 2px solid #d97706 !important;
        position: relative;
        animation: pulse-sw 2s ease-in-out infinite;
    }

    @keyframes pulse-sw {
        0%, 100% { 
            border-color: #d97706; 
            box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.4);
        }
        50% { 
            border-color: #f59e0b; 
            box-shadow: 0 0 0 6px rgba(217, 119, 6, 0.15);
        }
    }

    .calendar-day.status-sw .status-code {
        color: #b45309 !important;
        font-weight: 800;
        font-size: 10px;
        background: #fef3c7;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #d97706;
    }

    .status-badge.sw {
        background: #fef3c7;
        color: #b45309;
        border: 1px solid #d97706;
        font-weight: 700;
    }

    /* Tooltip for Sandwich Leave */
    .tooltip .sandwich-badge {
        background: #fef3c7;
        color: #b45309;
        border: 1px solid #d97706;
        padding: 2px 10px;
        border-radius: 50px;
        font-size: 10px;
        font-weight: 700;
        display: inline-block;
    }

    .tooltip .sandwich-info {
        background: #fffbeb;
        padding: 4px 8px;
        border-radius: 4px;
        border-left: 3px solid #d97706;
        margin-top: 4px;
        font-size: 10px;
        color: #78350f;
    }
    .calendar-day.status-sw {
    background: linear-gradient(135deg, #fef3c7, #fde68a) !important;
    border: 2px solid #d97706 !important;
    animation: pulse-sw 2s ease-in-out infinite;
}
.calendar-day.status-sw .status-code {
    color: #b45309 !important;
    font-weight: 800;
    background: #fef3c7;
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid #d97706;
}
.status-badge.sw {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #d97706;
    font-weight: 700;
}
</style>

<div class="card-header">
    <h3><i class="fas fa-calendar-alt"></i> MY ATTENDANCE</h3>
    <div class="month-nav">
        <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?><?php echo ($selected_user_id != $user['id']) ? '&user_id=' . $selected_user_id . '&role=' . $selected_role : ''; ?><?php echo $viewing_attendance ? '&view=attendance&agent_id=' . $_GET['agent_id'] : ''; ?>"><i class="fas fa-chevron-left"></i></a>
        <span><?php echo date('M Y', strtotime("$selected_year-$selected_month-01")); ?></span>
        <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?><?php echo ($selected_user_id != $user['id']) ? '&user_id=' . $selected_user_id . '&role=' . $selected_role : ''; ?><?php echo $viewing_attendance ? '&view=attendance&agent_id=' . $_GET['agent_id'] : ''; ?>"><i class="fas fa-chevron-right"></i></a>
    </div>
    <?php if ($resignation_last_working_date && $resignation_status === 'approved'): ?>
        <div class="resignation-badge">
            <i class="fas fa-check-circle"></i>
            Last Working Day: <?php echo date('M d, Y', strtotime($resignation_last_working_date)); ?>
            <span class="resignation-flag">??</span>
        </div>
    <?php endif; ?>
</div>

<div class="calendar-grid">
    <div class="calendar-weekdays">
        <div>Sun</div>
        <div>Mon</div>
        <div>Tue</div>
        <div>Wed</div>
        <div>Thu</div>
        <div>Fri</div>
        <div>Sat</div>
    </div>
    <div class="calendar-days">
        <?php
        // Empty cells for first week
        for ($i = 0; $i < $first_day_of_week; $i++) {
            echo '<div class="calendar-day empty"></div>';
        }

        $today = date('Y-m-d');
        
        for ($day = 1; $day <= $total_days_in_month; $day++) {
            $date_str = "$selected_year-$selected_month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
            $record = isset($attendance_map[$day]) ? $attendance_map[$day] : null;
            
            $raw_work = $record['work'] ?? 0;
            $work_hours = (float)$raw_work;
            
            if ($work_hours > 24) {
                $work_hours = round($work_hours / 60, 2);
            }
            
            if ($work_hours <= 0 && $record && $record['check_in'] && $record['check_in'] !== '00:00:00' && $record['check_out'] && $record['check_out'] !== '00:00:00') {
                try {
                    $in = new DateTime($record['check_in']);
                    $out = new DateTime($record['check_out']);
                    $diff = $in->diff($out);
                    $work_hours = $diff->h + ($diff->i / 60);
                } catch (Exception $e) {
                    // Keep existing
                }
            }
            
            $is_holiday = isset($calendar_holidays[$date_str]);
            $holiday_name = $is_holiday ? $calendar_holidays[$date_str]['holiday_name'] : '';

            // ===== GET OT ELIGIBILITY =====
            $ot_check = ['eligible' => false, 'ot_hours' => 0, 'reason' => 'Function not available'];
            
            if (function_exists('getOTEligibility')) {
                $ot_check = getOTEligibility($pdo, $work_hours, $date_str, $user['role'], true);
            }
            
            $is_eligible = isset($ot_check['eligible']) && $ot_check['eligible'] === true;
            $ot_hours_value = isset($ot_check['ot_hours']) ? $ot_check['ot_hours'] : 0;
            
            $ot_button_html = '';

            if ($ot_check['eligible'] && $is_it_dept){
                try {
                    $stmt_ot = $pdo->prepare("SELECT id, status FROM overtime_requests WHERE user_id = ? AND date = ?");
                    $stmt_ot->execute([$user['id'], $date_str]);
                    $already_applied = $stmt_ot->fetch();
                } catch (Exception $e) {
                    $already_applied = false;
                }

                if (!$already_applied) {
                    $ot_button_html = '<button class="btn-ot-apply" onclick="event.stopPropagation(); openOTModal(\''.$date_str.'\', '.$work_hours.', '.$ot_hours_value.')">
                        <i class="fas fa-clock"></i> Apply OT ('.$ot_hours_value.'h)
                    </button>';
                } else {
                    $status_badge = '';
                    if ($already_applied['status'] === 'pending') {
                        $status_badge = '<span class="ot-status-badge pending">? Pending</span>';
                    } elseif ($already_applied['status'] === 'approved') {
                        $status_badge = '<span class="ot-status-badge approved">? Approved</span>';
                    } else {
                        $status_badge = '<span class="ot-status-badge rejected">? Rejected</span>';
                    }
                    $ot_button_html = $status_badge;
                }
            }

            // Safe trim for PHP 8.1+
            $status_raw = trim((string)($record['status'] ?? ''));
            $status_lower = strtolower($status_raw);
            $current_status = $status_raw;
            $exception_type = $all_exceptions[$date_str] ?? ($record['exception_type'] ?? null);
            $check_in = $record['check_in'] ?? null;
            $check_out = $record['check_out'] ?? null;
            $is_scheduled = false;

            $status_class = 'status-no-data';
            $status_display = '-';
            $can_request_exception = false;
            $day_of_week = date('w', strtotime($date_str));
            $is_sunday = ($day_of_week == 0);
            $is_resignation_date = ($resignation_last_working_date && $date_str === $resignation_last_working_date && $resignation_status === 'approved');
            $is_future = ($date_str > $today);

            // --- Check approved roster for this date ---
            $has_approved_roster = false;
            foreach ($all_rosters as $ros) {
                if ($date_str >= $ros['start_date'] && $date_str <= $ros['end_date']) {
                    $has_approved_roster = true;
                    break;
                }
            }

          // ===== PRIORITY LOGIC =====
// 1. Holiday first — PH (or P if punched in)
if ($is_holiday) {
    if ($check_in && $check_in !== '00:00:00') {
        $status_class = 'status-present';
        $status_display = 'P';
    } else {
        $status_class = 'status-ph';
        $status_display = 'PH';
    }
    $can_request_exception = false;
}
// ? 1.5 Auto-detected sandwich ? show A (only if not already PL/LWP/HPL)
elseif (
    isset($calendar_sandwich_dates[$date_str])
    && !in_array(strtoupper($status_raw), ['PL','LWP','HPL','PLANNED LEAVE','PERSONAL LEAVE','LEAVE WITHOUT PAY','HALF PAID LEAVE'])
) {
    $status_class = 'status-absent';
    $status_display = 'A';
    $is_scheduled = false;
    $can_request_exception = false;
}
// 2. Approved exceptions override everything
elseif ($exception_type) {
    if (in_array($exception_type, ['full_day', 'late', 'traffic', 'regularize'])) {
        $status_display = 'P';
        $status_class = 'status-present';
        $can_request_exception = false;
    } elseif ($exception_type === 'half_day') {
        $status_display = 'HD';
        $status_class = 'status-half-day';
        $can_request_exception = false;
    } elseif (in_array($exception_type, ['pl_adjustment', 'leave'])) {
        $status_display = 'PL';
        $status_class = 'status-pl';
        $can_request_exception = false;
    }
}
// 3. PL from DB
elseif (in_array(strtoupper($status_raw), ['PL', 'PLANNED LEAVE', 'PERSONAL LEAVE'])) {
    $status_class = 'status-pl';
    $status_display = 'PL';
    $is_scheduled = false;
    $can_request_exception = false;
}
// 3b. Absent on Sunday
elseif ($is_sunday && strtoupper($status_raw) === 'A') {
    $status_class = 'status-absent';
    $status_display = 'A';
    $is_scheduled = false;
    $can_request_exception = true;
}

// 3c. Sunday / WO — but show P if punched in
elseif ($is_sunday || strtoupper($status_raw) === 'WO' || strtoupper($status_raw) === 'WEEK OFF' || strtoupper($status_raw) === 'WEEKOFF') {
    if ($check_in && $check_in !== '00:00:00' && $work_hours > 0) {
        if ($work_hours >= 5.5) {
            $status_class = 'status-present';
            $status_display = 'P';
        } else {
            $status_class = 'status-half-day';
            $status_display = 'HD';
        }
        $is_scheduled = false;
        $can_request_exception = false;
    } else {
        $status_class = 'status-weekoff';
        $status_display = 'WO';
        $is_scheduled = false;
    }
}         // 4. Roster applied + no punch-in ? S if future, A if past/today (ONLY if NOT on leave)
            elseif ($has_approved_roster && (!$check_in || $check_in == '00:00:00')) {
                if ($is_future) {
                    $status_class = 'status-scheduled';
                    $status_display = 'S';
                    $is_scheduled = true;
                } else {
                    $status_class = 'status-absent';
                    $status_display = 'A';
                    $is_scheduled = false;
                    $can_request_exception = true;
                }
            }
            // 5. Regular database statuses
            elseif (!empty($status_raw)) {
                // SANDWICH LEAVE
               
                if (in_array($status_raw, ['L', 'Late'])) {
                    $status_class = 'status-late';
                    $status_display = 'L';
                    $can_request_exception = true;
                } 
                elseif (in_array($status_raw, ['A', 'Absent'])) {
                    $status_class = 'status-absent';
                    $status_display = 'A';
                    $can_request_exception = true;
                }
                elseif (in_array($status_raw, ['HD', 'Half Day', 'Halfday'])) {
                    $status_class = 'status-half-day';
                    $status_display = 'HD';
                    $can_request_exception = true;
                }
                elseif (in_array($status_raw, ['PL', 'Personal Leave', 'Planned Leave'])) {
                    $status_class = 'status-pl';
                    $status_display = 'PL';
                } 
                elseif ($status_raw === 'HPL') {
                    $status_class = 'status-hpl';
                    $status_display = 'HPL';
                }
                else {
                    $status_info = getStatusDisplay($status_raw);
                    $status_display = $status_info['code'] ?? $status_raw;
                    $status_class = 'status-' . ($status_info['class'] ?? 'no-data');
                }
            } 
            // 6. No record found
            else {
                if ($has_approved_roster) {
                    // Roster applied but no DB record ? S if future, A if past/today
                    if ($is_future) {
                        $status_class = 'status-scheduled';
                        $status_display = 'S';
                        $is_scheduled = true;
                    } else {
                        $status_class = 'status-absent';
                        $status_display = 'A';
                        $can_request_exception = true;
                    }
                } elseif ($date_str < $today) {
                    // Past date, no roster, no record ? Absent
                    $status_class = 'status-absent';
                    $status_display = 'A';
                    $can_request_exception = true;
                }
                // Future date, no roster ? leave as '-' (upcoming)
            }

            // Check for existing exception request
            if ($can_request_exception && isset($user['id'])) {
                $stmt = $pdo->prepare("SELECT id FROM late_exception_requests WHERE user_id = ? AND date = ? AND status IN ('pending', 'approved')");
                $stmt->execute([$user['id'], $date_str]);
                if ($stmt->fetch()) {
                    $can_request_exception = false;
                }
            }

            $day_class = 'calendar-day';
            if ($is_sunday && $status_class == 'status-no-data') {
                $day_class .= ' weekend';
            }
            $day_class .= ' ' . $status_class;

            if ($is_resignation_date) {
                $day_class .= ' resignation-highlight';
            }

            if ($is_scheduled) {
                $day_class .= ' has-scheduled';
            }

            $is_today_class = ($date_str == $today) ? 'today' : '';
            $onclick = $can_request_exception ? "openExceptionModalForDate('$date_str', '$current_status')" : '';

            // Tooltip content
            $tooltip_content = '';
            if ($record) {
                $status_badge_class = str_replace('status-', '', $status_class);
                $tooltip_content = '<div class="tooltip">';
                $tooltip_content .= '<div class="tooltip-date">' . date('l, M d', strtotime($date_str)) . '</div>';

                if ($is_holiday && $status_display !== 'P') {
                    $tooltip_content .= '<div class="time-row"><span class="label">Holiday:</span><span class="value">' . htmlspecialchars($holiday_name) . '</span></div>';
                }

          

                if ($is_scheduled) {
                    $tooltip_content .= '<div class="time-row" style="color: #6b5200;">';
                    $tooltip_content .= '<span class="label">Status:</span>';
                    $tooltip_content .= '<span class="value">Scheduled - Awaiting Check-in</span>';
                    $tooltip_content .= '</div>';
                    if ($check_in && $check_in !== '00:00:00') {
                        $tooltip_content .= '<div class="time-row"><span class="label">Expected In:</span><span class="value">' . date('h:i A', strtotime($check_in)) . '</span></div>';
                    }
                    if ($check_out && $check_out !== '00:00:00') {
                        $tooltip_content .= '<div class="time-row"><span class="label">Expected Out:</span><span class="value">' . date('h:i A', strtotime($check_out)) . '</span></div>';
                    }
                    if ($status_display === 'P' && $work_hours > 0 && $work_hours <= 24) {
                        $tooltip_content .= '<div class="time-row"><span class="label">Hours:</span><span class="value">' . number_format($work_hours, 1) . 'h</span></div>';
                    }
                } else {
                    // Regular status tooltip
                   if ($status_display !== 'LWP' && $status_display !== 'PL' && $status_display !== 'HPL' && $status_display !== 'PH') {
                        if ($check_in && $check_in !== '00:00:00') {
                            $tooltip_content .= '<div class="time-row"><span class="label">Check In:</span><span class="value">' . date('h:i A', strtotime($check_in)) . '</span></div>';
                        }
                        if ($check_out && $check_out !== '00:00:00') {
                            $tooltip_content .= '<div class="time-row"><span class="label">Check Out:</span><span class="value">' . date('h:i A', strtotime($check_out)) . '</span></div>';
                        }
                        if ($work_hours > 0) {
                            $tooltip_content .= '<div class="time-row"><span class="label">Hours:</span><span class="value">' . formatWorkHours($work_hours) . '</span></div>';
                        }       
                    } else {
                        $leave_type = $status_display;
                        if ($status_display === 'PH') {
                            $leave_type = 'Paid Holiday';
                        } elseif ($status_display === 'HPL') {
                            $leave_type = 'Half Paid Leave';
                        } elseif ($status_display === 'PL') {
                            $leave_type = 'Planned Leave';
                        } elseif ($status_display === 'LWP') {
                            $leave_type = 'Leave Without Pay';
                        }  else {
                            $leave_type = 'Official Leave';
                        }
                        $tooltip_content .= '<div class="time-row"><span class="label">Status:</span><span class="value">' . $leave_type . '</span></div>';
                    }
                }
                $tooltip_content .= '<div style="margin-top:4px;"><span class="status-badge ' . $status_badge_class . '">' . $status_display . '</span></div>';
                $tooltip_content .= '</div>';
            }  
            // Resignation tooltip
            if ($is_resignation_date) {
                $tooltip_content = '<div class="tooltip resignation-tooltip-content">';
                $tooltip_content .= '<div class="tooltip-date" style="color:#16a34a;">?? Last Working Day</div>';
                $tooltip_content .= '<div class="time-row"><span class="label">Employee:</span><span class="value">' . htmlspecialchars($resignation_employee_name) . '</span></div>';
                $tooltip_content .= '<div class="time-row"><span class="label">Date:</span><span class="value">' . date('l, M d, Y', strtotime($date_str)) . '</span></div>';
                $tooltip_content .= '<div style="margin-top:6px; padding-top:6px; border-top:1px solid #e8edf4; text-align:center;">';
                $tooltip_content .= '<span style="background:#16a34a; color:#fff; padding:2px 12px; border-radius:50px; font-size:10px; font-weight:700;">? Resignation Approved</span>';
                $tooltip_content .= '</div>';
                $tooltip_content .= '</div>';
            }
            ?>
            <div class="<?php echo $day_class; ?>" onclick="<?php echo $onclick; ?>">
                <div class="day-number <?php echo $is_today_class; ?>"><?php echo $day; ?></div>
                <span class="status-code <?php echo $is_scheduled ? 'scheduled-status' : ''; ?>">
                    <?php if ($is_resignation_date): ?>
                        <span class="resignation-icon">??</span>
                        <span class="resignation-label">Last Day</span>
                    <?php else: ?>
                        <?php echo $status_display; ?>
                    <?php endif; ?>
                </span>
                <?php echo $tooltip_content; ?>
                <!-- OT button -->
                <?php if ($is_eligible && $is_it_dept): ?>
                    <div style="margin-top: 2px;">
                        <?php echo $ot_button_html; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php } ?>
    </div>
</div>

<!-- Legend -->
<div class="legend">
    <div class="legend-item"><span class="legend-color present"></span> Present</div>
    <div class="legend-item"><span class="legend-color late"></span> Late</div>
    <div class="legend-item"><span class="legend-color absent"></span> Absent</div>
    <div class="legend-item"><span class="legend-color lwp"></span> LWP (Leave Without Pay)</div>
    <div class="legend-item"><span class="legend-color pl"></span> PL (Planned Leave)</div>
    <div class="legend-item"><span class="legend-color comp-off"></span> Comp Off (CO)</div>
    <div class="legend-item"><span class="legend-color scheduled"></span> Scheduled</div>
    <div class="legend-item"><span class="legend-color weekoff"></span> Week Off</div>
    <div class="legend-item"><span class="legend-color exception"></span> Exception</div>
    <div class="legend-item"><span class="legend-color no-data"></span> No Data</div>
    <div class="legend-item"><span class="legend-color" style="background:#fef3c7; border:1px solid #d97706;"></span> PH (Paid Holiday)</div>
    <div class="legend-item"><span class="legend-color" style="background:#f0fff4; border:1px solid #bbf7d0;"></span> FPE (Full Day Exception)</div>
    <div class="legend-item"><span class="legend-color" style="background:#f0fff4; border:1px solid #bbf7d0;"></span> HPE (Half Day Exception)</div>
    <div class="legend-item"><span class="legend-color" style="background:#fef3c7; border:1px solid #d97706;"></span> HPL (Half Paid Leave)</div>
    
    <div class="legend-item resignation-legend">
        <span class="legend-color resignation-color"></span> 
        <span style="font-weight:700; color:#16a34a;">Last Working Day</span>
    </div>
<?php if ($is_it_dept): ?>
<div class="legend-item" style="border: 1px solid #2563eb; border-radius: 50px; padding: 2px 12px 2px 6px; background: #e0f2fe;">
    <span class="legend-color" style="background: linear-gradient(135deg, #2563eb, #3b82f6); width: 16px; height: 16px; border-radius: 4px; display: inline-block;"></span>
    <span style="font-weight:700; color:#2563eb; font-size: 11px;">OT Eligible</span>
</div>
<?php endif; ?>
</div>

<!-- Scheduled count summary -->
<?php
$scheduled_count = 0;
$scheduled_dates = [];
for ($day = 1; $day <= $total_days_in_month; $day++) {
    $date_str = "$selected_year-$selected_month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
    $record = isset($attendance_map[$day]) ? $attendance_map[$day] : null;
    if ($record && strtolower(trim((string)($record['status'] ?? ''))) === 'scheduled') {
        $scheduled_count++;
        $scheduled_dates[] = date('M d', strtotime($date_str));
    }
}
if ($scheduled_count > 0): ?>
    <div class="scheduled-summary" style="margin-top: 10px; padding: 8px 12px; background: #fff8e1; border-radius: 6px; border-left: 3px solid #ffd93d;">
        <span style="font-size: 12px; color: #6b5200;">
            <i class="fas fa-clock"></i>
            <strong><?php echo $scheduled_count; ?> day(s) scheduled</strong>
            (Awaiting check-in on: <?php echo implode(', ', $scheduled_dates); ?>)
        </span>
    </div>
<?php endif; ?>

<!-- ===== ADD RESIGNATION CSS STYLES ===== -->
<style>
.calendar-day.resignation-highlight {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    border: 2px solid #16a34a;
    position: relative;
    animation: pulse-border 2s ease-in-out infinite;
    z-index: 2;
}

@keyframes pulse-border {
    0%, 100% { 
        border-color: #16a34a; 
        box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.4);
    }
    50% { 
        border-color: #4ade80; 
        box-shadow: 0 0 0 8px rgba(22, 163, 74, 0.15);
    }
}

.calendar-day.resignation-highlight .day-number {
    color: #15803d;
    font-weight: 800;
    font-size: 15px;
}

.calendar-day.resignation-highlight .day-number.today {
    background: #16a34a;
    color: #fff;
    border-radius: 50%;
    padding: 2px 6px;
}

.calendar-day.resignation-highlight .status-code {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    background: #16a34a;
    color: #fff;
    padding: 2px 8px;
    border-radius: 50px;
    font-size: 9px;
    font-weight: 700;
    margin-top: 2px;
}

.resignation-icon {
    font-size: 12px;
}

.resignation-label {
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.resignation-tooltip-content {
    background: #0a1628 !important;
    border: 2px solid #16a34a !important;
    min-width: 180px !important;
}

.resignation-tooltip-content .tooltip-date {
    color: #4ade80 !important;
    font-size: 13px !important;
}

.resignation-tooltip-content .time-row .label {
    color: #94a3b8 !important;
}

.resignation-tooltip-content .time-row .value {
    color: #fff !important;
    font-weight: 600 !important;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.resignation-badge {
    background: #dcfce7;
    padding: 4px 14px;
    border-radius: 50px;
    font-size: 12px;
    color: #16a34a;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #16a34a;
    animation: pulse-badge 2s ease-in-out infinite;
}

@keyframes pulse-badge {
    0%, 100% { 
        box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.3);
    }
    50% { 
        box-shadow: 0 0 0 6px rgba(22, 163, 74, 0.1);
    }
}

.resignation-badge .resignation-flag {
    font-size: 14px;
}

.legend-item.resignation-legend {
    border: 1px solid #16a34a;
    border-radius: 50px;
    padding: 2px 12px 2px 6px;
    background: #dcfce7;
}

.legend-color.resignation-color {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    border: 2px solid #16a34a;
    width: 20px;
    height: 20px;
    border-radius: 4px;
    display: inline-block;
    animation: pulse-border 2s ease-in-out infinite;
}

.calendar-day.status-hpl {
    background: linear-gradient(135deg, #fef3c7, #fde68a) !important;
    border: 1px solid #d97706 !important;
}

.calendar-day.status-hpl .status-code {
    color: #b45309 !important;
    font-weight: 800;
    font-size: 10px;
}

.status-badge.hpl {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #d97706;
}

.calendar-day.status-ph {
    background: linear-gradient(135deg, #fef3c7, #fde68a) !important;
    border: 1px solid #d97706 !important;
}

.calendar-day.status-ph .status-code {
    color: #b45309 !important;
    font-weight: 800;
    font-size: 10px;
}

.status-badge.ph {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #d97706;
}

.calendar-day.status-phd {
    background: linear-gradient(135deg, #e0f2fe, #bae6fd) !important;
    border: 1px solid #0ea5e9 !important;
}

.calendar-day.status-phd .status-code {
    color: #0369a1 !important;
    font-weight: 800;
    font-size: 10px;
}

.status-badge.phd {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #0ea5e9;
}

.calendar-day.status-fpe, 
.calendar-day.status-hpe {
    background-color: #f0fff4 !important;
    border: 1px solid #bbf7d0 !important;
}

.calendar-day.status-fpe .status-code, 
.calendar-day.status-hpe .status-code {
    color: #16a34a !important;
    font-weight: 800;
    font-size: 10px;
}

.status-badge.fpe, .status-badge.hpe {
    background: #dcfce7;
    color: #16a34a;
    border: 1px solid #bbf7d0;
}

.btn-ot-apply {
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    color: #fff;
    border: none;
    padding: 2px 8px;
    border-radius: 50px;
    font-size: 8px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.3s;
    margin-top: 2px;
    width: 100%;
    white-space: nowrap;
}

.btn-ot-apply:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 12px rgba(37, 99, 235, 0.4);
}

.btn-ot-apply i {
    font-size: 8px;
    margin-right: 3px;
}

.ot-status-badge {
    font-size: 8px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 50px;
    display: inline-block;
    margin-top: 2px;
    width: 100%;
    text-align: center;
}

.ot-status-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.ot-status-badge.approved {
    background: #dcfce7;
    color: #16a34a;
}

.ot-status-badge.rejected {
    background: #fee2e2;
    color: #dc2626;
}

.ot-not-eligible {
    font-size: 7px;
    color: #94a3b8;
    display: block;
    text-align: center;
    margin-top: 2px;
    cursor: help;
}
</style>

<?php
// ============================================
// EXCEPTION MODAL - WITH WEEK OFF EXCEPTION
// ============================================

$currentPlBalance = isset($user['pl_balance']) ? (float)$user['pl_balance'] : 0;
$currentMonth = date('m');
$currentYear = date('Y');
$today = date('Y-m-d');

// Get all Sundays in current month for reference
$sundays = [];
$date = new DateTime("$currentYear-$currentMonth-01");
$lastDay = new DateTime("$currentYear-$currentMonth-01");
$lastDay->modify('last day of this month');

while ($date <= $lastDay) {
    if ($date->format('w') == 0) {
        $sundays[] = $date->format('Y-m-d');
    }
    $date->modify('+1 day');
}
?>

<!-- ===== LOAD FLATPICKR LIBRARY ===== -->
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

    .modal-overlay.show {
        display: flex !important;
    }

    .modal-box {
        background: #ffffff;
        border-radius: 16px;
        padding: 28px 32px;
        overflow: visible !important;
        max-height: 85vh;
        width: 600px;
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

    .modal-header h3 i {
        color: #d97706;
    }

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

    .selected-date-badge .remove-btn:hover {
        transform: scale(1.2);
    }

    .request-type-select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #e8edf4;
        border-radius: 10px;
        font-size: 14px;
        transition: 0.3s;
        background: #ffffff;
        color: #0a1628;
        font-family: 'Inter', sans-serif;
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
    
    .flatpickr-current-month {
        pointer-events: none !important;
    }
    .flatpickr-current-month .flatpickr-monthDropdown-months,
    .flatpickr-current-month .numInputWrapper {
        pointer-events: auto !important;
    }
    .flatpickr-current-month .numInputWrapper span {
        display: none !important;
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

    .info-box .highlight {
        color: #2563eb;
        font-weight: 700;
    }

    .wo-options {
        display: none;
        background: #f0f7ff;
        padding: 12px;
        border-radius: 10px;
        margin-bottom: 10px;
        border: 1px solid #dbeafe;
    }

    .wo-options.show {
        display: block;
    }

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
            <span id="plBalanceBadge" style="background: #dbeafe; color: #2563eb; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: none; border: 1px solid #2563eb;">
                PL Balance: <?php echo number_format($currentPlBalance, 1); ?>
            </span>
        </div>

        <div class="info-box">
            <p><i class="fas fa-info-circle"></i> <span id="exceptionStatusInfo">Click on a date in the calendar to select it.</span></p>
        </div>

        <form method="POST" id="exceptionForm" action="dashboard.php">
            <input type="hidden" name="action" value="submit_exception">
            <input type="hidden" id="userPlBalance" value="<?php echo $currentPlBalance; ?>">
            <input type="hidden" name="date" id="exceptionDate">
            <input type="hidden" name="current_status" id="exceptionCurrentStatus" value="">
            <input type="hidden" name="selected_dates" id="selectedDatesInput">
            <input type="hidden" name="original_work_date" id="originalWorkDate">
            <input type="hidden" name="weekoff_exception_type" id="weekoffExceptionType" value="">

            <div style="margin-bottom:10px;">
                <label style="font-weight:700;display:block;margin-bottom:4px;color:#64748b;font-size:11px;letter-spacing:0.5px;">EXCEPTION TYPE</label>
                <select name="exception_type" id="exceptionTypeSelect" class="request-type-select" required onchange="handleTypeChange()">
                    <option value="half_day">Half Day</option>
                    <option value="full_day">Full Day</option>
                    <option value="pl_adjustment">PL Adjustment</option>
                    <option value="weekoff_exception">Week Off Exception</option>
                </select>
            </div>

            <!-- Week Off Exception Options -->
            <div id="weekoffOptions" class="wo-options">
                <div class="info-text">
                    <i class="fas fa-calendar-week"></i> 
                    <strong>Week Off Exception:</strong> Select the Sunday you worked and the day you want as Week Off.
                </div>
                
                <div style="margin-bottom:10px;">
                    <label>Sunday You Worked:</label>
                    <select id="workedSunday" name="worked_sunday" class="request-type-select">
                        <option value="">Select Sunday</option>
                        <?php foreach ($sundays as $sunday): ?>
                            <option value="<?php echo $sunday; ?>"><?php echo date('M d, Y', strtotime($sunday)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="weekoff-info">
                        <i class="fas fa-info-circle"></i> 
                        You must have worked on this Sunday. If not worked, this request will be invalid.
                    </div>
                </div>

                <div>
                    <label>Desired Week Off Date:</label>
                    <select id="desiredWeekOff" name="desired_weekoff" class="request-type-select">
                        <option value="">Select Date</option>
                        <?php
                        $date = new DateTime("$currentYear-$currentMonth-01");
                        $lastDay = new DateTime("$currentYear-$currentMonth-01");
                        $lastDay->modify('last day of this month');
                        
                        while ($date <= $lastDay) {
                            if ($date->format('w') != 0) {
                                $dateStr = $date->format('Y-m-d');
                                echo '<option value="' . $dateStr . '">' . date('M d, Y (l)', strtotime($dateStr)) . '</option>';
                            }
                            $date->modify('+1 day');
                        }
                        ?>
                    </select>
                    <div class="weekoff-info">
                        <i class="fas fa-info-circle"></i> 
                        This day will be marked as <strong>WO</strong> (Week Off) upon approval.
                    </div>
                </div>
            </div>

            <!-- PL Date Selector -->
            <div id="plDateSelector" style="display:none; margin-bottom:10px;">
                <label style="font-weight:700;display:block;margin-bottom:4px;color:#64748b;font-size:11px;letter-spacing:0.5px;">
                    SELECT DATES FOR PL ADJUSTMENT
                    <span style="font-weight:400;color:#64748b;font-size:10px;">(Select multiple dates)</span>
                </label>
                
                <div style="display:flex;gap:8px;margin-bottom:8px;">
                    <input type="text" id="plDatePicker" 
                        style="flex:1;padding:8px 12px;border:1px solid #e8edf4;border-radius:10px;font-size:13px;background:#ffffff;color:#0a1628;font-weight:500;">
                    <button type="button" onclick="addSelectedDate()" 
                        style="background:#2563eb;color:#fff;border:none;padding:8px 16px;border-radius:10px;cursor:pointer;font-size:12px;font-weight:700;white-space:nowrap;">
                        <i class="fas fa-plus"></i> ADD
                    </button>
                    <button type="button" onclick="clearAllDates()" 
                        style="background:#fee2e2;color:#dc2626;border:1px solid #dc2626;padding:8px 16px;border-radius:10px;cursor:pointer;font-size:12px;font-weight:700;white-space:nowrap;">
                        <i class="fas fa-trash"></i> CLEAR
                    </button>
                </div>

                <div id="selectedDatesContainer" style="display:flex;flex-wrap:wrap;gap:6px;padding:8px;background:#f4f6fa;border-radius:10px;min-height:40px;border:1px solid #e8edf4;">
                    <span style="color:#64748b;font-size:12px;font-weight:500;" id="emptyDatesMsg">No dates selected. Pick a date and click ADD.</span>
                </div>
                <small style="color:#64748b; font-size:10px; font-weight:500; display:block; margin-top:4px;">
                    <i class="fas fa-info-circle"></i> Total selected: <span id="totalSelectedCount">0</span> days | PL Balance needed: <span id="plNeeded">0.0</span>
                </small>
            </div>

            <div style="margin-bottom:10px;">
                <label style="font-weight:700;display:block;margin-bottom:4px;color:#64748b;font-size:11px;letter-spacing:0.5px;">REASON</label>
                <textarea name="reason" id="exceptionReason" required placeholder="Describe your reason..." style="width:100%;height:70px;padding:8px 12px;border:1px solid #e8edf4;border-radius:10px;font-family:inherit;font-size:13px;resize:vertical;transition:0.3s;background:#ffffff;color:#0a1628;font-weight:500;"></textarea>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="btn-cancel-modal" onclick="closeExceptionModal()" style="background:#f4f6fa;color:#1e293b;border:1px solid #e8edf4;padding:8px 24px;border-radius:50px;cursor:pointer;font-size:12px;font-weight:700;transition:0.3s;">
                    CANCEL
                </button>
                <button type="submit" id="submitBtn" style="background:linear-gradient(135deg, #2563eb, #7c3aed);color:#fff;border:none;padding:8px 24px;border-radius:50px;cursor:pointer;font-size:12px;font-weight:700;transition:0.3s;box-shadow:0 2px 10px rgba(37,99,235,0.2);">
                    <i class="fas fa-paper-plane"></i> SUBMIT
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== OT REQUEST MODAL ===== -->
<div id="otModal" class="modal-overlay">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-clock" style="color: #2563eb;"></i> REQUEST OVERTIME</h3>
            <button class="close-btn" onclick="closeOTModal()">&times;</button>
        </div>
        
        <div style="padding: 5px 0;">
            <div style="background: #e0f2fe; padding: 14px; border-radius: 10px; margin-bottom: 15px; border-left: 4px solid #2563eb;">
                <div style="display: flex; justify-content: space-between; font-size: 14px; flex-wrap: wrap; gap: 8px;">
                    <span><strong>?? Date:</strong> <span id="otDateDisplay"></span></span>
                    <span><strong>?? Work Hours:</strong> <span id="otWorkHoursDisplay"></span></span>
                    <span><strong>?? OT Hours:</strong> <span id="otHoursDisplay" style="color: #2563eb; font-weight: 800; font-size: 16px;"></span></span>
                </div>
                <div style="margin-top: 8px; font-size: 12px; color: #64748b; background: #f0f9ff; padding: 6px 10px; border-radius: 6px;">
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
                    <label style="font-weight: 700; display: block; margin-bottom: 4px; color: #64748b; font-size: 11px; letter-spacing: 0.5px;">
                        <i class="fas fa-pencil-alt"></i> REASON FOR OVERTIME
                    </label>
                    <textarea name="reason" id="otReason" required 
                        placeholder="Describe why you need OT approval (e.g., Project deadline, Extra work, Client call, etc.)..." 
                        style="width: 100%; height: 80px; padding: 10px 12px; border: 1px solid #e8edf4; border-radius: 10px; font-family: inherit; font-size: 13px; resize: vertical; transition: 0.3s; background: #ffffff; color: #0a1628;"></textarea>
                </div>
                
                <div style="background: #fef3c7; padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; border-left: 3px solid #d97706;">
                    <div style="font-size: 12px; color: #92400e; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-clock" style="font-size: 14px;"></i>
                        <span>? You can request OT only until <strong>tomorrow</strong>. After that, the request will expire.</span>
                    </div>
                </div>
                
                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 10px;">
                    <button type="button" onclick="closeOTModal()" 
                        style="background: #f4f6fa; color: #1e293b; border: 1px solid #e8edf4; padding: 8px 24px; border-radius: 50px; cursor: pointer; font-size: 12px; font-weight: 700; transition: 0.3s;">
                        CANCEL
                    </button>
                    <button type="submit" 
                        style="background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; border: none; padding: 8px 24px; border-radius: 50px; cursor: pointer; font-size: 12px; font-weight: 700; transition: 0.3s; box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);">
                        <i class="fas fa-paper-plane"></i> SUBMIT OT REQUEST
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let selectedDates = [];
let fpInstance = null;

function handleTypeChange() {
    const typeSelect = document.getElementById('exceptionTypeSelect');
    const plDateSelector = document.getElementById('plDateSelector');
    const weekoffOptions = document.getElementById('weekoffOptions');
    const balanceBadge = document.getElementById('plBalanceBadge');
    const hiddenDate = document.getElementById('exceptionDate');
    const statusText = document.getElementById('exceptionStatusInfo');
    
    plDateSelector.style.display = 'none';
    weekoffOptions.style.display = 'none';
    balanceBadge.style.display = 'none';
    
    if (typeSelect.value === 'pl_adjustment') {
        plDateSelector.style.display = 'block';
        balanceBadge.style.display = 'inline-block';
        statusText.innerHTML = 'Select one or more dates where you were on leave and want to adjust PL.';
        if (fpInstance) fpInstance.clear();
        hiddenDate.value = '';
    } else if (typeSelect.value === 'weekoff_exception') {
        weekoffOptions.style.display = 'block';
        statusText.innerHTML = '<strong>Week Off Exception</strong> - Select the Sunday you worked and the day you want as Week Off.';
        hiddenDate.value = '';
    } else {
        statusText.innerHTML = 'Click on a date in the calendar to select it.';
        selectedDates = [];
        updateSelectedDatesDisplay();
    }
}

function processDateSelection(dateStr) {
    if (!dateStr) return;
    
    if (selectedDates.includes(dateStr)) {
        alert('This date is already added.');
        return;
    }
    
    const balance = parseFloat(document.getElementById('userPlBalance').value);
    const totalNeeded = selectedDates.length + 1;
    
    if (totalNeeded > balance) {
        alert('Insufficient PL Balance!\n\nYou have ' + balance.toFixed(1) + ' PL days available.');
        if(fpInstance) fpInstance.clear();
        return;
    }
    
    selectedDates.push(dateStr);
    updateSelectedDatesDisplay();
    if(fpInstance) fpInstance.clear();
}

function addSelectedDate() {
    if (!fpInstance || fpInstance.selectedDates.length === 0) {
        alert('Please select a date in the calendar first.');
        return;
    }
    const dateStr = fpInstance.input.value;
    processDateSelection(dateStr);
}

function removeSelectedDate(date) {
    selectedDates = selectedDates.filter(d => d !== date);
    updateSelectedDatesDisplay();
}

function clearAllDates() {
    if (selectedDates.length === 0) return;
    if (confirm('Clear all selected dates?')) {
        selectedDates = [];
        updateSelectedDatesDisplay();
    }
}

function updateSelectedDatesDisplay() {
    const container = document.getElementById('selectedDatesContainer');
    const totalCount = document.getElementById('totalSelectedCount');
    const plNeeded = document.getElementById('plNeeded');
    const hiddenInput = document.getElementById('selectedDatesInput');
    
    hiddenInput.value = selectedDates.join(',');
    totalCount.textContent = selectedDates.length;
    plNeeded.textContent = selectedDates.length.toFixed(1);
    
    if (selectedDates.length === 0) {
        container.innerHTML = '<span style="color:#64748b;font-size:12px;font-weight:500;">No dates selected. Pick a date and click ADD.</span>';
        return;
    }
    
    let html = '';
    const sortedDates = [...selectedDates].sort();
    
    sortedDates.forEach(date => {
        const displayDate = formatDateDisplay(date);
        html += `
            <span class="selected-date-badge">
                ${displayDate}
                <button type="button" class="remove-btn" onclick="removeSelectedDate('${date}')">
                    <i class="fas fa-times"></i>
                </button>
            </span>
        `;
    });
    
    container.innerHTML = html;
}

function formatDateDisplay(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function openExceptionModalForDate(date, status) {
    const modal = document.getElementById('exceptionModal');
    if (!modal) return;
    
    document.getElementById('exceptionDate').value = date;
    document.getElementById('exceptionCurrentStatus').value = status || 'No Data';
    
    selectedDates = [date]; 
    updateSelectedDatesDisplay();
    
    const clickedDate = new Date(date + 'T00:00:00');
    const isSunday = clickedDate.getDay() === 0;
    
    const workedSundayDropdown = document.getElementById('workedSunday');
    const desiredWeekOffDropdown = document.getElementById('desiredWeekOff');
    
    workedSundayDropdown.value = "";
    desiredWeekOffDropdown.value = "";

    if (isSunday) {
        workedSundayDropdown.value = date;
        document.getElementById('exceptionTypeSelect').value = 'weekoff_exception';
    } else {
        desiredWeekOffDropdown.value = date;
    }

    const statusText = document.getElementById('exceptionStatusInfo');
    statusText.innerHTML = `Date Selected: <strong>${date}</strong> | Current Status: <strong>${status || 'No Data'}</strong>`;
    
    handleTypeChange();
    
    modal.classList.add('show');
}

function closeExceptionModal() {
    const modal = document.getElementById('exceptionModal');
    if (modal) modal.classList.remove('show');
}

document.addEventListener('mousedown', function(event) {
    const modal = document.getElementById('exceptionModal');
    if (modal && modal.classList.contains('show')) {
        const modalBox = modal.querySelector('.modal-box');
        const isClickInsideModal = modalBox.contains(event.target);
        const isClickInsideCalendar = event.target.closest('.flatpickr-calendar');
        
        if (!isClickInsideModal && !isClickInsideCalendar) {
            closeExceptionModal();
        }
    }
});

document.getElementById('exceptionForm').addEventListener('submit', function(e) {
    const typeSelect = document.getElementById('exceptionTypeSelect').value;
    const reason = document.getElementById('exceptionReason').value.trim();
    const hiddenDate = document.getElementById('exceptionDate');
    const hiddenSelectedDates = document.getElementById('selectedDatesInput');
    const currentStatusInput = document.getElementById('exceptionCurrentStatus');

    if (!reason) {
        e.preventDefault();
        alert("Please enter a reason.");
        return false;
    }

    if (typeSelect === 'pl_adjustment') {
        if (selectedDates.length === 0) {
            e.preventDefault();
            alert("Please select at least one date for PL Adjustment.");
            return false;
        }
        hiddenDate.value = selectedDates[0];
        hiddenSelectedDates.value = selectedDates.join(',');
        if(currentStatusInput) currentStatusInput.value = 'PL';
    } else if (typeSelect === 'weekoff_exception') {
        const workedSunday = document.getElementById('workedSunday').value;
        const desiredWeekOff = document.getElementById('desiredWeekOff').value;
        
        if (!workedSunday) {
            e.preventDefault();
            alert("Please select the Sunday you worked.");
            return false;
        }
        if (!desiredWeekOff) {
            e.preventDefault();
            alert("Please select the date you want as Week Off.");
            return false;
        }
        
        hiddenDate.value = desiredWeekOff;
        document.getElementById('originalWorkDate').value = workedSunday;
        document.getElementById('weekoffExceptionType').value = 'weekoff_shift';
        
        selectedDates = [workedSunday, desiredWeekOff];
        hiddenSelectedDates.value = selectedDates.join(',');
    } else {
        if (!hiddenDate.value) {
            e.preventDefault();
            alert("Please select a date from the calendar.");
            return false;
        }
        if(currentStatusInput && !currentStatusInput.value) {
            currentStatusInput.value = 'Absent';
        }
    }

    return true;
});

function openOTModal(date, workHours, otHours) {
    const isItDept = <?php echo $is_it_dept ? 'true' : 'false'; ?>;
    if (!isItDept) {
        alert('Access Denied: OT requests are restricted to IT department.');
        return;
    }
    
    const modal = document.getElementById('otModal');
    document.getElementById('otDate').value = date;
    document.getElementById('otWorkHours').value = workHours;
    document.getElementById('otHours').value = otHours;
    
    const d = new Date(date + 'T00:00:00');
    document.getElementById('otDateDisplay').textContent = d.toLocaleDateString('en-US', { 
        weekday: 'short', 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
    
    document.getElementById('otWorkHoursDisplay').textContent = formatWorkHoursDisplay(workHours);
    document.getElementById('otHoursDisplay').textContent = otHours + 'h';
    
    document.getElementById('otReason').value = '';
    modal.classList.add('show');
}

function closeOTModal() {
    const modal = document.getElementById('otModal');
    if (modal) modal.classList.remove('show');
}

function formatWorkHoursDisplay(hours) {
    if (hours <= 0) return '00:00';
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
}

document.addEventListener('DOMContentLoaded', function() {
    const otForm = document.getElementById('otForm');
    if (otForm) {
        otForm.addEventListener('submit', function(e) {
            const reason = document.getElementById('otReason').value.trim();
            if (!reason) {
                e.preventDefault();
                alert('Please enter a reason for OT request.');
                return false;
            }
            return true;
        });
    }
});

document.addEventListener('mousedown', function(event) {
    const modal = document.getElementById('otModal');
    if (modal && modal.classList.contains('show')) {
        const modalBox = modal.querySelector('.modal-box');
        if (modalBox && !modalBox.contains(event.target)) {
            closeOTModal();
        }
    }
});

document.addEventListener('DOMContentLoaded', function() {
    var now = new Date();
    var firstDayOfCurrentMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    var firstDayStr = firstDayOfCurrentMonth.toISOString().split('T')[0];
    
    fpInstance = flatpickr("#plDatePicker", {
        dateFormat: "Y-m-d",
        disableMobile: true,
        minDate: firstDayStr,
        allowInput: false,
        onChange: function(selectedDates, dateStr) {
        }
    });
});

window.openExceptionModalForDate = openExceptionModalForDate;
window.closeExceptionModal = closeExceptionModal;
window.addSelectedDate = addSelectedDate;
window.handleTypeChange = handleTypeChange;
window.removeSelectedDate = removeSelectedDate;
window.clearAllDates = clearAllDates;
window.openOTModal = openOTModal;
window.closeOTModal = closeOTModal;
</script>