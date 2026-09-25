<?php
// ============================================
// DASHBOARD - MAIN ORCHESTRATOR
// ============================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== 1. BOOTSTRAP =====
require_once __DIR__ . '/includes/modules/config/bootstrap.php';

// ===== 2. AUTHENTICATION =====
require_once __DIR__ . '/includes/modules/auth/authentication.php';

// ===== ADD: formatWorkHours function if not exists =====
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

// ===== 3. ATTENDANCE SYNC =====
// Only run sync if the required table exists
$table_exists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'attendance_daily_summary'");
    if ($check && $check->rowCount() > 0) {
        $table_exists = true;
        require_once __DIR__ . '/includes/modules/attendance/attendance_sync.php';
    }
} catch (Exception $e) {
    // Table doesn't exist, skip sync
}

// ===== 4. GET USER DATA =====
$user = getCurrentUser();
$current_year = date('Y');
$current_month = date('m');

if (isset($user['is_first_login']) && $user['is_first_login'] == 1) {
    header("Location: reset_password.php");
    exit();
}

// ===== 5. HANDLE POST REQUESTS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'submit_leave_request':
            require_once __DIR__ . '/includes/modules/requests/leave_request_handler.php';
            break;
        case 'submit_roster_request':
            require_once __DIR__ . '/includes/modules/requests/roster_request_handler.php';
            break;
        case 'submit_exception':
            require_once __DIR__ . '/includes/modules/requests/exception_request_handler.php';
            break;
        case 'process_approval':
            require_once __DIR__ . '/includes/modules/approvals/approval_process.php';
            break;
        // ===== OT HANDLERS =====
        case 'submit_overtime':
            require_once __DIR__ . '/includes/modules/requests/overtime_handler.php';
            break;
       case 'approve_ot':
    require_once __DIR__ . '/includes/modules/approvals/approval_process.php';
    break;
    }
}

// ===== 6. GET URL PARAMETERS =====
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : $current_month;
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $user['id'];
$selected_role = isset($_GET['role']) ? htmlspecialchars($_GET['role']) : '';
$view_mode = isset($_GET['view']) ? htmlspecialchars($_GET['view']) : 'team';

// ===== 7. MESSAGE HANDLING =====
$message = '';
if (isset($_SESSION['message'])) {
    $msg_type = isset($_SESSION['message']['type']) ? htmlspecialchars($_SESSION['message']['type']) : 'success';
    $msg_text = isset($_SESSION['message']['text']) ? htmlspecialchars($_SESSION['message']['text']) : '';
    if (!empty($msg_text)) {
        $message = '<div class="message message-' . $msg_type . '">' . $msg_text . '</div>';
    }
    unset($_SESSION['message']);
}

// ===== 8. GET SELECTED USER =====
$selected_user = $user;
if ($selected_user_id != $user['id']) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$selected_user_id]);
    $selected_user = $stmt->fetch();
    if (!$selected_user) {
        $selected_user = $user;
        $selected_user_id = $user['id'];
    }
}

// ===== 9. VIEWING ATTENDANCE CHECK =====
$viewing_attendance = (isset($_GET['view']) && $_GET['view'] == 'attendance' && isset($_GET['agent_id']));
$agent_id_for_attendance = $viewing_attendance ? (int)$_GET['agent_id'] : 0;

// ===== 10. LOAD ATTENDANCE DATA =====
require_once __DIR__ . '/includes/modules/attendance/attendance_stats.php';

// ===== DEBUG: Check OT requests =====
try {
    $check_ot_table = $pdo->query("SHOW TABLES LIKE 'overtime_requests'");
    if ($check_ot_table->rowCount() > 0) {
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM overtime_requests WHERE status = 'pending'");
        $stmt_count->execute();
        $pending_ot_count = $stmt_count->fetchColumn();
        error_log("Pending OT requests count: " . $pending_ot_count);
    } else {
        error_log("overtime_requests table does not exist!");
    }
} catch (Exception $e) {
    error_log("Error checking OT table: " . $e->getMessage());
}

$attendance_records = getAttendanceRecords($pdo, $user['person_id'], $selected_year, $selected_month);
$attendance_map = buildAttendanceMap($attendance_records);
$monthly_stats = calculateMonthlyStats($attendance_map, $selected_year, $selected_month);

// ===== 11. LOAD TEAM DATA =====
require_once __DIR__ . '/includes/modules/team/hierarchy_functions.php';
require_once __DIR__ . '/includes/modules/team/team_data_functions.php';

$hierarchy_path = getHierarchyPath($pdo, $user['id'], $selected_user_id);

$team_members = [];
$current_hierarchy_role = '';
$team_attendance_data = [];
$team_pl_balances = [];

if (in_array($user['role'] ?? '', ['hr', 'centre_head', 'process_head', 'manager', 'am', 'tl'])) {
    $team_data = getTeamData($pdo, $selected_user_id, $selected_user['role'] ?? '');
    $team_members = $team_data['members'] ?? [];
    $current_hierarchy_role = $team_data['role'] ?? '';
    
    if (!empty($team_members)) {
        $team_attendance_data = getTeamAttendanceData($pdo, $team_members, $selected_year, $selected_month);
        $team_pl_balances = getTeamPLBalances($pdo, $team_members);
    }
}

// ===== 12. LOAD APPROVALS =====
require_once __DIR__ . '/includes/modules/approvals/approval_fetch.php';
$pending_all = getPendingApprovals($pdo, $user);
$pending_exceptions = $pending_all['exceptions'] ?? []; 
$pending_leave_requests = $pending_all['leave'] ?? [];
$pending_roster_requests = $pending_all['roster'] ?? [];

// ===== 13. LOAD USER REQUESTS =====
require_once __DIR__ . '/includes/modules/user/user_requests_functions.php';
$my_requests = getUserRequests($pdo, $user['id']);

// ===== 14. CALENDAR VARIABLES =====
$first_day = date('Y-m-01', strtotime("$selected_year-$selected_month-01"));
$total_days_in_month = date('t', strtotime($first_day));
$first_day_of_week = date('w', strtotime($first_day));
$month_name = date('F Y', strtotime($first_day));
$today_str = date('Y-m-d');

$prev_month = $selected_month - 1;
$prev_year = $selected_year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $selected_month + 1;
$next_year = $selected_year;
if ($next_month > 12) { $next_month = 1; $next_year++; }

// ===== 15. BACK USER NAVIGATION =====
$back_user = null;
if (in_array($user['role'] ?? '', ['hr', 'centre_head', 'process_head', 'manager', 'am', 'tl'])) {
    $back_user = getBackUser($pdo, $user, $selected_user_id);
}

// ===== 16. AGENT ATTENDANCE DETAIL =====
$agent_attendance_detail = [];
$agent = null;
if ($viewing_attendance && $agent_id_for_attendance > 0) {
    $stmt = $pdo->prepare("SELECT person_id FROM users WHERE id = ?");
    $stmt->execute([$agent_id_for_attendance]);
    $agent_data = $stmt->fetch();
    if ($agent_data) {
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE person_id = ? AND YEAR(date) = ? AND MONTH(date) = ? ORDER BY date DESC");
        $stmt->execute([$agent_data['person_id'], $selected_year, $selected_month]);
        $agent_attendance_detail = $stmt->fetchAll();
    }
    $agent = $agent_data;
}

// ===== 17. PL BALANCE =====
$stmt_pl = $pdo->prepare("SELECT pl_balance FROM users WHERE id = ?");
$stmt_pl->execute([$user['id']]);
$pl_balance_db = $stmt_pl->fetchColumn();
$pl_cnt = ($pl_balance_db !== false && $pl_balance_db !== null)
    ? rtrim(rtrim(number_format((float)$pl_balance_db, 2, '.', ''), '0'), '.')
    : 0;

// ===== 17.5 COMP OFF BALANCE CALCULATION =====
$stmt_earned = $pdo->prepare("
    SELECT COUNT(*) FROM attendance 
    WHERE person_id = ? AND DAYOFWEEK(date) = 1 AND (work > 0 OR status IN ('P', 'Present', 'Late', 'L'))
");
$stmt_earned->execute([$user['person_id']]);
$earned_co = $stmt_earned->fetchColumn();

$stmt_used = $pdo->prepare("
    SELECT COUNT(*) FROM leave_requests 
    WHERE user_id = ? AND leave_type = 'comp_off' AND status = 'approved'
");
$stmt_used->execute([$user['id']]);
$used_co = $stmt_used->fetchColumn();

$available_co = max(0, $earned_co - $used_co);

// ===== 17.6 RESIGNATION DATA =====
if (file_exists(__DIR__ . '/includes/modules/resignation/resignation_functions.php')) {
    require_once __DIR__ . '/includes/modules/resignation/resignation_functions.php';
    $has_pending_resignation = hasPendingResignation($pdo, $user['id']);
    $existing_resignation = getUserResignation($pdo, $user['id']);
} else {
    $has_pending_resignation = false;
    $existing_resignation = null;
}

// ===== 17.7 OT SUMMARY FOR USER =====
$user_ot_total = 0;
$user_ot_details = [];
try {
    if (function_exists('calculateApprovedOT')) {
        $user_ot_total = calculateApprovedOT($pdo, $user['id'], $selected_year, $selected_month);
    }
    if (function_exists('getUserOTRequests')) {
        $user_ot_details = getUserOTRequests($pdo, $user['id']);
    }
} catch (Exception $e) {
    // Silent fail
}

// ===== OT REQUESTS LIST (For Process Head) =====
if ($user['role'] === 'process_head' || $user['role'] === 'centre_head') {
    $pending_ot = [];
    if (function_exists('getPendingOTRequests')) {
        $pending_ot = getPendingOTRequests($pdo);
    }
    
    if ($pending_ot && count($pending_ot) > 0) {
        ?>
        <div class="card mt-4" style="border-left: 4px solid #2563eb;">
            <div class="card-header" style="background: #f0f9ff;">
                <h4 style="color: #2563eb;"><i class="fas fa-clock"></i> ? PENDING OT REQUESTS</h4>
                <span style="background: #2563eb; color: #fff; padding: 2px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                    <?php echo count($pending_ot); ?> pending
                </span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" style="font-size: 13px;">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Work Hours</th>
                                <th>OT Hours</th>
                                <th>Reason</th>
                                <th>Requested</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_ot as $ot): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($ot['full_name'] ?? 'Unknown'); ?></strong>
                                    <br><small><?php echo htmlspecialchars($ot['person_id'] ?? ''); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($ot['date'])); ?></td>
                                <td><?php echo formatWorkHours($ot['work_hours'] ?? 0); ?></td>
                                <td><span style="background: #2563eb; color: #fff; padding: 2px 10px; border-radius: 12px; font-weight: 700; font-size: 12px;"><?php echo $ot['ot_hours']; ?>h</span></td>
                                <td><?php echo htmlspecialchars($ot['reason'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, H:i', strtotime($ot['created_at'])); ?></td>
                                <td>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="action" value="approve_ot">
                                        <input type="hidden" name="ot_id" value="<?php echo $ot['id']; ?>">
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="btn btn-success btn-sm" style="background:#16a34a;color:#fff;border:none;padding:4px 12px;border-radius:4px;cursor:pointer;font-weight:700;" onclick="return confirm('Approve this OT request?')">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="action" value="approve_ot">
                                        <input type="hidden" name="ot_id" value="<?php echo $ot['id']; ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" class="btn btn-danger btn-sm" style="background:#dc2626;color:#fff;border:none;padding:4px 12px;border-radius:4px;cursor:pointer;font-weight:700;" onclick="return confirm('Reject this OT request?')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}

// ===== 18. INCLUDE HEADER =====
include __DIR__ . '/includes/header.php';

// ===== 19. INCLUDE STYLES AND HTML =====
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- MAIN STYLES -->
    <?php include __DIR__ . '/includes/modules/styles/main_styles.php'; ?>
</head>
<body>
    <div class="container">
        <!-- HEADER BAR -->
        <?php include __DIR__ . '/includes/modules/header/header_bar.php'; ?>
        
        <!-- MESSAGES -->
        <?php echo $message; ?>

        <!-- ===== MAIN DASHBOARD GRID ===== -->
        <div class="dashboard-grid <?php echo (isManagementRole()) ? '' : 'full-width'; ?>">
            <!-- ===== LEFT COLUMN: ATTENDANCE CALENDAR ===== -->
            <div class="card anim-fade-up anim-delay-3">
                <!-- Attendance Calendar -->
                <?php include __DIR__ . '/includes/modules/attendance/attendance_calendar.php'; ?>
                
                <!-- Dropdown Button -->
                <?php include __DIR__ . '/includes/modules/requests/dropdown_button.php'; ?>
                
                <!-- Leave Request Form -->
                <?php include __DIR__ . '/includes/modules/requests/leave_form.php'; ?>
                
                <!-- Roster Form -->
                <?php include __DIR__ . '/includes/modules/requests/roster_form.php'; ?>
            </div>

            <!-- ===== RIGHT PANEL ===== -->
            <div class="right-panel">
             
                <!-- ===== PENDING APPROVALS ===== -->
                <?php if (in_array($user['role'] ?? '', ['hr', 'centre_head', 'process_head', 'manager', 'am', 'tl'])): ?>
                    <?php include __DIR__ . '/includes/modules/approvals/pending_approvals_display.php'; ?>
                <?php endif; ?>

                <!-- ===== MY REQUESTS ===== -->
                <?php include __DIR__ . '/includes/modules/user/my_requests_display.php'; ?>
                
            </div>
        </div>

        <!-- ===== TEAM TABLE ===== -->
        <?php if (in_array($user['role'] ?? '', ['hr', 'centre_head', 'process_head', 'manager', 'am', 'tl'])): ?>
            <?php include __DIR__ . '/includes/modules/team/team_table_display.php'; ?>
        <?php endif; ?>

        <!-- Agent Attendance Detail View -->
        <?php if ($viewing_attendance && !empty($agent_attendance_detail)): ?>
            <?php include __DIR__ . '/includes/modules/attendance/agent_attendance_detail.php'; ?>
        <?php endif; ?>
    </div>

    <!-- Floating Action Button -->
    <?php if (hasFullAccess()): ?>
        <?php include __DIR__ . '/includes/modules/fab/fab_button.php'; ?>
    <?php endif; ?>

    <!-- Modals -->
    <?php include __DIR__ . '/includes/modules/modals/approval_modal.php'; ?>
    <?php include __DIR__ . '/includes/modules/modals/exception_modal.php'; ?>
    <?php include __DIR__ . '/includes/modules/modals/ot_modal.php'; ?>

    <!-- Resignation Modal -->
    <?php if (file_exists(__DIR__ . '/includes/modules/modals/resignation_modal.php')): ?>
        <?php include __DIR__ . '/includes/modules/modals/resignation_modal.php'; ?>
    <?php endif; ?>

    <!-- Main JavaScript -->
    
</body>
</html>