<?php
// ============================================================
// PAYROLL EXCEL EXPORT - WITH OT & DYNAMIC APR LOGIC
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 1); // Turn on for debugging

require_once 'includes/modules/config/bootstrap.php'; 

if (!isset($user)) { $user = getCurrentUser(); }

// 1. Security Check - Allow both FNM1924 and Process Heads
$allowed_ids = ['FNM0109']; 
$is_process_head = ($user['role'] ?? '') === 'process_head';
$is_allowed = false;

if ($user && isset($user['person_id'])) {
    if (in_array($user['person_id'], $allowed_ids) || $is_process_head) {
        $is_allowed = true;
    }
}

if (!$is_allowed) {
    die("Access Denied. Only Administrators and Process Heads can access this report.");
}

require_once 'SimpleXLSXGen.php'; 
use Shuchkin\SimpleXLSXGen;

// 2. Parameters from Dropdown
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$cat_filter = isset($_GET['category']) ? $_GET['category'] : 'all'; 

$days_in_month = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
$month_name = date('F', mktime(0, 0, 0, $month, 1));

// 3. Helper Functions
function formatHMS($time) {
    if (!$time || $time == '00:00:00' || $time == '0' || $time == '--') return '00:00:00';
    if (strlen($time) == 5) return $time . ':00';
    return $time;
}

function time_to_sec($time) {
    if (!$time || $time == '00:00:00') return 0;
    list($h, $m, $s) = array_pad(explode(':', $time), 3, '00');
    return ($h * 3600) + ($m * 60) + $s;
}

// 4. PRE-FETCH DATA
try {
    // Holidays
    $holiday_stmt = $pdo->prepare("SELECT holiday_date FROM holidays WHERE MONTH(holiday_date) = ? AND YEAR(holiday_date) = ?");
    $holiday_stmt->execute([$month, $year]);
    $holiday_list = $holiday_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Attendance
    $att_map = [];
    $att_stmt = $pdo->prepare("SELECT person_id, date, status, work, check_in, check_out, records, 
        total_login, total_idle, total_acw, total_talk, total_meeting, total_general, final_login 
        FROM attendance WHERE MONTH(date) = ? AND YEAR(date) = ?");
    $att_stmt->execute([$month, $year]);
    while($a = $att_stmt->fetch(PDO::FETCH_ASSOC)) { 
        $att_map[$a['person_id']][$a['date']] = $a; 
    }

    // Approved Exceptions (Leave/PL/CO)
    $leave_map = [];
    $l_stmt = $pdo->prepare("SELECT person_id, date, exception_type FROM late_exception_requests WHERE status = 'approved' AND MONTH(date) = ? AND YEAR(date) = ?");
    $l_stmt->execute([$month, $year]);
    while($l = $l_stmt->fetch(PDO::FETCH_ASSOC)) { 
        $leave_map[$l['person_id']][$l['date']] = $l['exception_type']; 
    }

    // Approved Overtime (OT)
    $ot_map = [];
    $ot_stmt = $pdo->prepare("SELECT user_id, date, ot_hours FROM overtime_requests WHERE status = 'approved' AND MONTH(date) = ? AND YEAR(date) = ?");
    $ot_stmt->execute([$month, $year]);
    while($ot = $ot_stmt->fetch(PDO::FETCH_ASSOC)) {
        $ot_map[$ot['user_id']][$ot['date']] = (float)$ot['ot_hours'];
    }

    // Users - Modified to filter by process head's team
    $all_users_query = "SELECT u.*, r.full_name as manager_name FROM users u 
                        LEFT JOIN users r ON u.reporting_to = r.id 
                        WHERE u.role NOT IN ('admin','Administrator')";
    
    // If user is a process head, filter to show only their team members
    if ($is_process_head && !in_array($user['person_id'], $allowed_ids)) {
        $process_head_id = $user['id'];
        $all_users_query .= " AND (u.reporting_to = $process_head_id OR u.process = '" . addslashes($user['process'] ?? '') . "')";
    }
    
    $all_users_query .= " ORDER BY u.position ASC, u.full_name ASC";
    $all_users = $pdo->query($all_users_query)->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) { 
    die("DB Error: " . $e->getMessage()); 
}

// 5. CATEGORIZATION
$csr_group = []; $support_group = [];
foreach ($all_users as $u) {
    $pos = strtoupper($u['position'] ?? '');
    if (strpos($pos, 'CSR') !== false || strpos($pos, 'AGENT') !== false) { 
        $csr_group[] = $u; 
    } else { 
        $support_group[] = $u; 
    }
}

// 6. FUNCTION: CSR DIALER REPORT
function generateCSRDialerReport($userList, $att_map, $ot_map, $days, $month, $year) {
    $header = ['No.', 'Person ID', 'Name', 'Dept', 'Position', 'Date', 'Total Login', 'Total Talk', 'Final Login', 'Status', 'OT Hours'];
    $data = [$header]; $idx = 1;

    foreach ($userList as $u) {
        for ($d = 1; $d <= $days; $d++) {
            $dt = sprintf("%04d-%02d-%02d", $year, $month, $d);
            $att = $att_map[$u['person_id']][$dt] ?? null;
            if (!$att) continue; 
            
            $daily_ot = $ot_map[$u['id']][$dt] ?? 0;

            $data[] = [
                $idx++, $u['person_id'], $u['full_name'], $u['department'], $u['position'], date('d-m-Y', strtotime($dt)),
                formatHMS($att['total_login']), formatHMS($att['total_talk']), 
                formatHMS($att['final_login']), $att['status'], $daily_ot
            ];
        }
    }
    return $data;
}

// 7. FUNCTION: BIOMETRIC REPORT (SUPPORT)
function generateBiometricReport($userList, $att_map, $leave_map, $days, $month, $year) {
    $header = ['No.', 'Person ID', 'Name', 'Date', 'Check-in', 'Check-out', 'Work', 'Status'];
    $data = [$header]; $idx = 1;

    foreach ($userList as $u) {
        for ($d = 1; $d <= $days; $d++) {
            $dt = sprintf("%04d-%02d-%02d", $year, $month, $d);
            $att = $att_map[$u['person_id']][$dt] ?? null;
            if (!$att) continue; 

            $data[] = [
                $idx++, $u['person_id'], $u['full_name'], date('d-m-Y', strtotime($dt)),
                formatHMS($att['check_in'] ?? '00:00:00'), formatHMS($att['check_out'] ?? '00:00:00'),
                $att['work'] ?? 0, $att['status'] ?? 'A'
            ];
        }
    }
    return $data;
}

// 8. MAIN GRID VIEW (WITH APR LOGIC AND OT)
function generateGrid($userList, $att_map, $leave_map, $holiday_list, $ot_map, $days, $month, $year, $month_name) {
    $header = ['No.', 'Emp Code', 'Emp Name', 'Process', 'Department', 'Position'];
    for ($d = 1; $d <= $days; $d++) { $header[] = sprintf("%02d-%s", $d, substr($month_name, 0, 3)); }
    
    $summary_headers = ['P', 'L', 'WO', 'HD', 'LWP', 'A', 'PH', 'PL', 'HPL', 'CO', 'OT Hours', 'Payable Days'];
    $header = array_merge($header, $summary_headers);
    $data = [$header]; $idx = 1;

    foreach ($userList as $u) {
        $p_id = $u['person_id'];
        $u_id = $u['id'];
        $role = strtolower($u['role'] ?? '');
        $process = strtoupper($u['process'] ?? '');
        $pos = strtoupper($u['position'] ?? '');
        
        $row = [$idx++, $p_id, $u['full_name'], $process, $u['department'], $u['position']];
        $c = ['P'=>0, 'L'=>0, 'WO'=>0, 'HD'=>0, 'LWP'=>0, 'A'=>0, 'PH'=>0, 'PL'=>0, 'HPL'=>0, 'CO'=>0];
        $total_ot_hours = 0;

        for ($d = 1; $d <= $days; $d++) {
            $dt = sprintf("%04d-%02d-%02d", $year, $month, $d);
            $dw = date('w', strtotime($dt)); 
            $st = 'A';
            
            $att = $att_map[$p_id][$dt] ?? null;
            $leave = $leave_map[$p_id][$dt] ?? null;
            $is_holiday = in_array($dt, $holiday_list);
            
            // Sum OT Hours
            if (isset($ot_map[$u_id][$dt])) {
                $total_ot_hours += $ot_map[$u_id][$dt];
            }

            if ($att) {
                $hours = (float)($att['work'] ?? 0);
                $is_late = (time_to_sec($att['check_in'] ?? '00:00:00') > time_to_sec('09:45:00'));

                // --- APR LOGIC ---
                if ($process === 'UPGRADE' && $pos === 'CSR') {
                    if ($hours >= 9.0) { $st = 'P'; } 
                    elseif ($hours >= 4.5) { $st = 'HD'; } 
                    else { $st = 'A'; }
                }
                elseif ($pos === 'CSR' || $pos === 'AGENT') {
                    if ($hours >= 8.0) { $st = 'P'; } 
                    elseif ($hours >= 4.0) { $st = 'HD'; } 
                    else { $st = 'A'; }
                }
                else {
                    $required = ($role === 'process_head' || $role === 'centre_head') ? 8.0 : 9.0;
                    if ($hours >= $required) { $st = $is_late ? 'L' : 'P'; } 
                    elseif ($hours >= 4.5) { $st = 'HD'; } 
                    else { $st = 'A'; }
                }
            } 

            // Priority Overwrites
            if ($leave) {
                if ($leave == 'comp_off') { $st = 'CO'; } 
                elseif ($leave == 'half_day') { $st = 'HPL'; } 
                elseif (in_array($leave, ['pl_adjustment', 'pl', 'PL'])) { $st = 'PL'; } 
                elseif ($leave == 'lwp') { $st = 'LWP'; } 
                elseif ($leave == 'weekoff_exception') { $st = 'WO'; } 
                else { $st = 'P'; } 
            } 
            elseif ($is_holiday && ($st == 'A' || empty($att['check_in']))) { $st = 'PH'; } 
            elseif ($dw == 0 && ($st == 'A' || empty($att['check_in']))) { $st = 'WO'; }
            
            if (isset($c[$st])) { $c[$st]++; }
            $row[] = $st;
        }

        $payable = $c['P'] + $c['L'] + $c['WO'] + $c['PH'] + $c['PL'] + $c['CO'] + ($c['HPL'] * 0.5);
        
        $data[] = array_merge($row, [
            $c['P'], $c['L'], $c['WO'], $c['HD'], $c['LWP'], 
            $c['A'], $c['PH'], $c['PL'], $c['HPL'], $c['CO'], 
            $total_ot_hours, round($payable, 2)
        ]);
    }
    return $data;
}

// 9. FINAL EXCEL GENERATION
while (ob_get_level()) { ob_end_clean(); }
$xlsx = new SimpleXLSXGen();

if ($cat_filter === 'csr' || $cat_filter === 'all') {
    $xlsx->addSheet(generateGrid($csr_group, $att_map, $leave_map, $holiday_list, $ot_map, $days_in_month, $month, $year, $month_name), 'CSR Attendance');
    $xlsx->addSheet(generateCSRDialerReport($csr_group, $att_map, $ot_map, $days_in_month, $month, $year), 'CSR Dialer Report');
}

if ($cat_filter === 'support' || $cat_filter === 'all') {
    $xlsx->addSheet(generateGrid($support_group, $att_map, $leave_map, $holiday_list, $ot_map, $days_in_month, $month, $year, $month_name), 'MGMT Attendance');
    $xlsx->addSheet(generateBiometricReport($support_group, $att_map, $leave_map, $days_in_month, $month, $year), 'Biometric Report');
}

$xlsx->downloadAs("Payroll_Full_Report_{$month}_{$year}.xlsx");
exit;