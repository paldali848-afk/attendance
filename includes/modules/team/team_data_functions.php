<?php
// ============================================
// TEAM DATA FUNCTIONS
// ============================================

function getTeamAttendanceData($pdo, $team_members, $year, $month) {
    $team_attendance_data = [];
    $today_str = date('Y-m-d');
    
    foreach ($team_members as $member) {
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE person_id = ? AND YEAR(date) = ? AND MONTH(date) = ? ORDER BY date ASC");
        $stmt->execute([$member['person_id'], $year, $month]);
        $records = $stmt->fetchAll();

        $member_attendance = [];
        $member_stats = [
            'present' => 0, 
            'absent' => 0, 
            'late' => 0, 
            'weekoff' => 0, 
            'holiday' => 0, 
            'leave' => 0,      // PL (Full Day)
            'half_leave' => 0, // HPL (Half Day Leave)
            'half_day' => 0,   // HD (Half Day Work)
            'exception' => 0,
            'lwp' => 0,
            'comp_off' => 0,
            'ot' => 0          // <-- ADDED: OT count
        ];

        foreach ($records as $record) {
            $day = date('j', strtotime($record['date']));
            $record['exception_type'] = $record['exception_type'] ?? null;
            $member_attendance[$day] = $record;

            // FIX: Handle null status before trim
            $status = isset($record['status']) ? trim($record['status']) : '';
            $status_lower = strtolower($status);
            
            // Skip empty status
            if (empty($status)) {
                continue;
            }
            
            // ===== OT - Overtime Approved =====
            // Check if OT is approved for this date
            $ot_approved = isset($record['ot_approved']) ? (int)$record['ot_approved'] : 0;
            if ($ot_approved == 1) {
                $ot_hours = isset($record['ot_hours']) ? (float)$record['ot_hours'] : 0;
                $member_stats['ot'] += $ot_hours; // Add OT hours
            }
            
            // ===== HPL - Half Paid Leave (Highest Priority) =====
            if ($status === 'HPL') {
                $member_stats['half_leave'] += 0.5; // Half day leave
                $member_stats['leave'] += 0.5;      // Also count towards total leave
            }
            // ===== PL - Paid Leave (Full Day) =====
            elseif (in_array($status, ['PL', 'Personal Leave', 'Planned Leave'])) {
                $member_stats['leave']++;
            }
            // ===== PH - Paid Holiday =====
            elseif ($status === 'PH') {
                $member_stats['holiday']++;
            }
            // ===== LWP - Leave Without Pay =====
            elseif (in_array($status, ['LWP', 'LV', 'Leave'])) {
                $member_stats['lwp']++;
                $member_stats['absent']++;
            }
            // ===== WO - Week Off =====
            elseif (in_array($status, ['WO', 'Week Off', 'Weekoff'])) {
                $member_stats['weekoff']++;
            }
            // ===== HD - Half Day =====
            elseif (in_array($status, ['HD', 'Half Day'])) {
                $member_stats['half_day']++;
            }
            // ===== CO - Comp Off =====
            elseif ($status === 'CO') {
                $member_stats['comp_off']++;
            }
            // ===== HPE - Half Paid Exception =====
            elseif ($status === 'HPE') {
                $member_stats['exception']++;
                $member_stats['half_day']++;
            }
            // ===== FPE - Full Paid Exception =====
            elseif ($status === 'FPE') {
                $member_stats['exception']++;
                $member_stats['present']++;
            }
            // ===== Holiday =====
            elseif (in_array($status, ['H', 'Holiday', 'PH'])) {
                $member_stats['holiday']++;
            }
            // ===== Exception =====
            elseif (strpos($status_lower, 'exception') !== false) {
                $member_stats['exception']++;
            }
            // ===== Late =====
            elseif (in_array($status, ['L', 'Late', 'CL', 'Coming Late'])) {
                $member_stats['late']++;
            }
            // ===== Absent =====
            elseif (in_array($status, ['A', 'Absent'])) {
                $member_stats['absent']++;
            }
            // ===== Present =====
            elseif (in_array($status, ['P', 'Present'])) {
                $member_stats['present']++;
            }
            // ===== Scheduled =====
            elseif (strpos($status_lower, 'scheduled') !== false) {
                // Scheduled doesn't count as present until checked in
            }
            // ===== Default - treat as present if status exists =====
            else {
                $member_stats['present']++;
            }
        }

        // Calculate absent days for dates with no record
        $total_days_in_month = date('t', strtotime("$year-$month-01"));
        for ($d = 1; $d <= $total_days_in_month; $d++) {
            $date_str = "$year-$month-" . str_pad($d, 2, '0', STR_PAD_LEFT);
            if ($date_str > $today_str) break;
            
            if (!isset($member_attendance[$d])) {
                $day_of_week = date('w', strtotime($date_str));
                if ($day_of_week != 0) { // Not Sunday
                    $member_stats['absent']++;
                } else {
                    $member_stats['weekoff']++;
                }
            }
        }

        $team_attendance_data[$member['id']] = [
            'attendance' => $member_attendance,
            'stats' => $member_stats
        ];
    }
    
    return $team_attendance_data;
}

function getTeamPLBalances($pdo, $team_members) {
    $balances = [];
    foreach ($team_members as $member) {
        $stmt = $pdo->prepare("SELECT pl_balance FROM users WHERE id = ?");
        $stmt->execute([$member['id']]);
        $pl_balance = $stmt->fetchColumn();
        $balances[$member['id']] = ($pl_balance !== false && $pl_balance !== null) ? (float)$pl_balance : 0;
    }
    return $balances;
}

/**
 * Get team attendance for calendar view with proper status display
 */
function getTeamAttendanceMap($pdo, $team_members, $year, $month) {
    $team_map = [];
    $today_str = date('Y-m-d');
    
    foreach ($team_members as $member) {
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE person_id = ? AND YEAR(date) = ? AND MONTH(date) = ? ORDER BY date ASC");
        $stmt->execute([$member['person_id'], $year, $month]);
        $records = $stmt->fetchAll();
        
        $attendance_map = [];
        foreach ($records as $record) {
            $day = date('j', strtotime($record['date']));
            $record['exception_type'] = $record['exception_type'] ?? null;
            $attendance_map[$day] = $record;
        }
        
        // Fill in missing dates with appropriate status
        $total_days_in_month = date('t', strtotime("$year-$month-01"));
        for ($d = 1; $d <= $total_days_in_month; $d++) {
            $date_str = "$year-$month-" . str_pad($d, 2, '0', STR_PAD_LEFT);
            if ($date_str > $today_str) break;
            
            if (!isset($attendance_map[$d])) {
                $day_of_week = date('w', strtotime($date_str));
                if ($day_of_week == 0) {
                    // Sunday
                    $attendance_map[$d] = ['status' => 'WO', 'check_in' => null, 'check_out' => null, 'work' => 0];
                } else {
                    // Absent
                    $attendance_map[$d] = ['status' => 'A', 'check_in' => null, 'check_out' => null, 'work' => 0];
                }
            }
        }
        
        // Sort by day
        ksort($attendance_map);
        
        $team_map[$member['id']] = [
            'member' => $member,
            'attendance' => $attendance_map
        ];
    }
    
    return $team_map;
}
?>