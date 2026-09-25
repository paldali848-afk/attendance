<?php
/**
 * Attendance Calendar Display Module
 */

function getAttendanceMap($person_id, $year, $month, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE person_id = ? AND YEAR(date) = ? AND MONTH(date) = ? ORDER BY date ASC");
    $stmt->execute([$person_id, $year, $month]);
    $records = $stmt->fetchAll();
    
    $attendance_map = [];
    foreach ($records as $record) {
        $day = date('j', strtotime($record['date']));
        $record['exception_type'] = $record['exception_type'] ?? null;
        $attendance_map[$day] = $record;
    }
    
    return $attendance_map;
}

function renderCalendar($user, $attendance_map, $year, $month, $today) {
    $first_day = date('Y-m-01', strtotime("$year-$month-01"));
    $total_days = date('t', strtotime($first_day));
    $first_day_of_week = date('w', strtotime($first_day));
    
    $output = '<div class="calendar-weekdays">';
    foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day) {
        $output .= "<div>$day</div>";
    }
    $output .= '</div><div class="calendar-days">';

    for ($i = 0; $i < $first_day_of_week; $i++) {
        $output .= '<div class="calendar-day empty"></div>';
    }

    for ($day = 1; $day <= $total_days; $day++) {
        $date_str = "$year-$month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
        $record = $attendance_map[$day] ?? null;

        $status_class = 'status-no-data';
        $status_display = '-';
        $can_request = false;
        $day_of_week = date('w', strtotime($date_str));
        $is_sunday = ($day_of_week == 0);
        $current_status = '';

        if ($record) {
            $status_raw = trim($record['status']);
            $exception_type = $record['exception_type'] ?? null;
            $current_status = $status_raw;
            
            if (strpos(strtolower($status_raw), 'exception') !== false && $exception_type) {
                $status_info = getStatusDisplay($status_raw, $exception_type);
            } else {
                $status_info = getStatusDisplay($status_raw);
            }
            $status_display = $status_info['code'];
            $status_class = 'status-' . $status_info['class'];
            
            // Allow exception request for Late or Absent
            if (in_array($status_info['class'], ['late', 'absent'])) {
                $can_request = true;
            }
        } elseif ($is_sunday) {
            $status_class = 'status-weekoff';
            $status_display = 'WO';
        } elseif ($date_str < $today) {
            $status_class = 'status-absent';
            $status_display = 'A';
            $can_request = true;
            $current_status = 'Absent';
        }

        // Check existing exception requests
        if ($can_request) {
            $pdo = $GLOBALS['pdo'];
            $stmt = $pdo->prepare("SELECT id FROM late_exception_requests WHERE user_id = ? AND date = ? AND status IN ('pending','approved')");
            $stmt->execute([$user['id'], $date_str]);
            if ($stmt->fetch()) $can_request = false;
        }

        $is_today = ($date_str == $today) ? 'today' : '';
        
        // ===== FIX: Proper onclick handler =====
        $onclick = '';
        if ($can_request) {
            // Escape the date and status for JavaScript
            $escaped_date = addslashes($date_str);
            $escaped_status = addslashes($status_display);
            $onclick = "onclick=\"openExceptionModalForDate('{$escaped_date}', '{$escaped_status}')\"";
        }
        
        $day_class = "calendar-day $status_class";

        $output .= <<<HTML
        <div class="{$day_class}" {$onclick}>
            <div class="day-number {$is_today}">{$day}</div>
            <span class="status-code">{$status_display}</span>
        </div>
HTML;
    }

    $output .= '</div>';
    return $output;
}

function renderMonthNav($year, $month, $selected_user_id = null, $selected_role = null) {
    $prev_month = $month - 1;
    $prev_year = $year;
    if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
    
    $next_month = $month + 1;
    $next_year = $year;
    if ($next_month > 12) { $next_month = 1; $next_year++; }
    
    $params = '';
    if ($selected_user_id) $params .= "&user_id=$selected_user_id";
    if ($selected_role) $params .= "&role=$selected_role";
    
    return <<<HTML
    <div class="month-nav">
        <a href="?month={$prev_month}&year={$prev_year}{$params}"><i class="fas fa-chevron-left"></i></a>
        <span>{$month}/{$year}</span>
        <a href="?month={$next_month}&year={$next_year}{$params}"><i class="fas fa-chevron-right"></i></a>
    </div>
HTML;
}