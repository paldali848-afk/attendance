<?php
// ============================================
// PENDING APPROVALS DISPLAY (WITH OT & RESIGNATION REQUESTS)
// ============================================

// ===== GET CURRENT USER INFO =====
$current_user_id = $user['id'] ?? 0;
$current_person_id = $user['person_id'] ?? '';
$current_role = $user['role'] ?? '';

// ===== FUNCTION TO GET ALL SUBORDINATES (Multi-level Hierarchy) =====
function getAllSubordinateIds($pdo, $manager_ids) {
    if (empty($manager_ids)) return [];
    
    try {
        $placeholders = implode(',', array_fill(0, count($manager_ids), '?'));
        $stmt = $pdo->prepare("SELECT id FROM users WHERE report_to IN ($placeholders)");
        $stmt->execute($manager_ids);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($results)) {
            return array_unique(array_merge($results, getAllSubordinateIds($pdo, $results)));
        }
        return $results;
    } catch (Exception $e) {
        error_log("getAllSubordinateIds Error: " . $e->getMessage());
        return [];
    }
}

// ===== FUNCTION TO GET DIRECT SUBORDINATES =====
function getDirectSubordinates($pdo, $manager_id) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE report_to = ?");
        $stmt->execute([$manager_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("getDirectSubordinates Error: " . $e->getMessage());
        return [];
    }
}

// ===== DEFINE HIERARCHY FILTER =====
$is_admin = in_array($current_role, ['centre_head', 'hr', 'Administrator']);
$team_user_ids = [];

if (!$is_admin) {
    // Get direct subordinates
    $direct_reports = getDirectSubordinates($pdo, $current_user_id);
    
    if (empty($direct_reports) && !empty($current_person_id)) {
        $direct_reports = getDirectSubordinates($pdo, $current_person_id);
    }
    
    if (!empty($direct_reports)) {
        // Get ALL subordinates (direct + indirect)
        $indirect_reports = getAllSubordinateIds($pdo, $direct_reports);
        $team_user_ids = array_unique(array_merge($direct_reports, $indirect_reports));
    }
    
    // If no subordinates, try same process users (fallback)
    if (empty($team_user_ids)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE process = (SELECT process FROM users WHERE id = ?) AND id != ?");
            $stmt->execute([$current_user_id, $current_user_id]);
            $process_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($process_users)) {
                $team_user_ids = $process_users;
            }
        } catch (Exception $e) {
            error_log("Process fallback Error: " . $e->getMessage());
        }
    }
    
    // Safety: If still no users, set to -1
    if (empty($team_user_ids)) {
        $team_user_ids = [-1];
    }
}

// Prepare SQL parameters for hierarchy filter
$placeholders = !$is_admin ? implode(',', array_fill(0, count($team_user_ids), '?')) : '';
$params = !$is_admin ? $team_user_ids : [];

// ===== FETCH PENDING OT REQUESTS (FIXED) =====
$pending_ot_requests = [];
// ONLY allow Process Head and Centre Head to fetch/see OT requests
$is_authorized_for_ot = in_array($current_role, ['process_head']);
try {
    $check_ot_table = $pdo->query("SHOW TABLES LIKE 'overtime_requests'");
    if ($check_ot_table->rowCount() > 0  && $is_authorized_for_ot) {
        // We MUST add the "u.id IN (...)" filter here
        $sql_ot = "SELECT otr.*, u.full_name, u.person_id 
                   FROM overtime_requests otr
                   JOIN users u ON otr.user_id = u.id
                   WHERE otr.status = 'pending'";

        if (!$is_admin) {
            $sql_ot .= " AND u.id IN ($placeholders)";
            $stmt_ot = $pdo->prepare($sql_ot . " ORDER BY otr.created_at ASC");
            $stmt_ot->execute($params);
        } else {
            $stmt_ot = $pdo->prepare($sql_ot . " ORDER BY otr.created_at ASC");
            $stmt_ot->execute();
        }
        
        $pending_ot_requests = $stmt_ot->fetchAll(PDO::FETCH_ASSOC);
        error_log("OT requests found: " . count($pending_ot_requests));
    }
} catch (Exception $e) {
    error_log("Error fetching OT requests: " . $e->getMessage());
}

// ===== FETCH PENDING RESIGNATION REQUESTS (FIXED) =====
$pending_resignation_requests = [];
try {
    $check_res_table = $pdo->query("SHOW TABLES LIKE 'resignation_requests'");
    if ($check_res_table->rowCount() > 0) {
        $sql_res = "SELECT 
                        r.id,
                        r.user_id,
                        r.employee_name,
                        r.position,
                        r.company_name,
                        r.resignation_date,
                        r.last_working_date,
                        r.reason,
                        r.status,
                        r.submitted_date,
                        r.approved_by,
                        r.approved_date,
                        u.person_id,
                        u.role,
                        u.full_name
                    FROM resignation_requests r
                    LEFT JOIN users u ON r.user_id = u.id
                    WHERE r.status = 'pending'";

        if (!$is_admin) {
            $sql_res .= " AND u.id IN ($placeholders)";
            $stmt_res = $pdo->prepare($sql_res . " ORDER BY r.submitted_date ASC");
            $stmt_res->execute($params);
        } else {
            $stmt_res = $pdo->prepare($sql_res . " ORDER BY r.submitted_date ASC");
            $stmt_res->execute();
        }
        
        $pending_resignation_requests = $stmt_res->fetchAll(PDO::FETCH_ASSOC);
        error_log("Resignation requests found: " . count($pending_resignation_requests));
    }
} catch (Exception $e) {
    error_log("Error fetching resignation requests: " . $e->getMessage());
}

// ===== FETCH OTHER PENDING REQUESTS =====
// These should already have hierarchy filter in your code
$pending_exceptions = isset($pending_exceptions) ? $pending_exceptions : [];
$pending_leave_requests = isset($pending_leave_requests) ? $pending_leave_requests : [];
$pending_roster_requests = isset($pending_roster_requests) ? $pending_roster_requests : [];

// ===== CALCULATE TOTAL =====
$total_pending = count($pending_exceptions) + count($pending_leave_requests) + 
                 count($pending_roster_requests) + count($pending_ot_requests) + 
                 count($pending_resignation_requests);
?>

<!-- ============================================
     DISPLAY PENDING APPROVALS
     ============================================ -->
<div class="card anim-fade-up anim-delay-4" style="border-left:4px solid var(--blue);">
    <div class="card-header">
        <h3><i class="fas fa-check-circle"></i> PENDING APPROVALS (<?php echo $total_pending; ?>)</h3>
        <span style="font-size:11px;color:var(--text-muted);font-weight:600;">
            <?php if ($is_admin): ?>
                <i class="fas fa-building"></i> ALL PROCESSES (<?php echo ucfirst($current_role); ?>)
            <?php else: ?>
                <i class="fas fa-users"></i> YOUR TEAM
                <?php if (!empty($user['process'])): ?>
                    | <?php echo htmlspecialchars($user['process']); ?>
                <?php endif; ?>
            <?php endif; ?>
        </span>
    </div>

    <?php if ($total_pending === 0): ?>
        <div style="text-align:center;padding:20px 10px;">
            <i class="fas fa-check-circle" style="font-size:28px;color:var(--green);opacity:0.2;"></i>
            <p style="color:var(--text-muted);margin-top:6px;font-weight:700;font-size:14px;">ALL CLEAR</p>
            <p style="color:var(--text-muted);font-size:12px;font-weight:600;">
                <?php if ($is_admin): ?>
                    No pending approval requests from any team.
                <?php else: ?>
                    No pending approval requests from your team members.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div style="max-height:400px;overflow-y:auto;overflow-x:hidden;">
            
            <!-- ===== RESIGNATION REQUESTS (Show first as they are important) ===== -->
            <?php if (count($pending_resignation_requests) > 0): ?>
                <?php foreach ($pending_resignation_requests as $res): 
                    $name = !empty($res['employee_name']) ? $res['employee_name'] : ($res['full_name'] ?? 'Unknown');
                    $person_id = !empty($res['person_id']) ? $res['person_id'] : 'N/A';
                    $role = !empty($res['role']) ? $res['role'] : 'agent';
                    $position = !empty($res['position']) ? $res['position'] : 'N/A';
                    $reason = !empty($res['reason']) ? $res['reason'] : 'No reason provided';
                    $last_working_date = !empty($res['last_working_date']) ? $res['last_working_date'] : 'Not specified';
                    $resignation_date = !empty($res['resignation_date']) ? $res['resignation_date'] : date('Y-m-d');
                    $submitted_date = !empty($res['submitted_date']) ? $res['submitted_date'] : date('Y-m-d H:i:s');
                    $res_id = !empty($res['id']) ? $res['id'] : 0;
                    $company = !empty($res['company_name']) ? $res['company_name'] : 'N/A';
                ?>
                    <div class="approval-item" style="border-left-color: #dc2626; background: #fef2f2; margin-bottom: 8px; border-radius: 8px; padding: 10px;">
                        <div class="info">
                            <div class="date-text">
                                <strong><?php echo htmlspecialchars($name); ?></strong>
                                <span class="role-badge-sm" style="background:#dc2626;color:#fff;padding:2px 8px;border-radius:12px;font-size:9px;">
                                    <?php echo htmlspecialchars($person_id); ?>
                                </span>
                                <span class="status-badge-sm pending" style="background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:12px;font-size:9px;font-weight:700;">PENDING</span>
                            </div>
                            <div style="font-size:11px;color:#dc2626;margin-top:2px;font-weight:600;">
                                <i class="fas fa-user-slash"></i> 
                                <strong>?? RESIGNATION REQUEST</strong>
                                <span style="background:#dc2626;color:#fff;padding:1px 8px;border-radius:10px;font-size:9px;margin-left:4px;">
                                    <?php echo ucfirst($position); ?>
                                </span>
                                <span style="background:#6b7280;color:#fff;padding:1px 8px;border-radius:10px;font-size:9px;margin-left:4px;">
                                    <?php echo htmlspecialchars($company); ?>
                                </span>
                            </div>
                            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">
                                Resignation Date: <strong><?php echo date('M d, Y', strtotime($resignation_date)); ?></strong> | 
                                Last Working Day: <strong><?php echo date('M d, Y', strtotime($last_working_date)); ?></strong> | 
                                Submitted: <?php echo date('M d, H:i', strtotime($submitted_date)); ?>
                            </div>
                            <div class="reason-text" style="font-size:11px;color:#1e293b;margin-top:3px;">
                                <strong>Reason:</strong> <?php echo htmlspecialchars($reason); ?>
                            </div>
                        </div>
                        <div class="approval-actions" style="display:flex;gap:4px;margin-top:6px;">
                            <form method="POST" action="approval_process.php" style="display:inline-block;margin:0;">
                                <input type="hidden" name="request_id" value="<?php echo $res_id; ?>">
                                <input type="hidden" name="request_type" value="resignation">
                                <input type="hidden" name="status" value="approved">
                                <input type="hidden" name="remarks" value="Resignation approved">
                                <button type="submit" class="btn-approve" style="background:#16a34a;color:#fff;border:none;padding:4px 14px;border-radius:20px;font-size:10px;font-weight:700;cursor:pointer;" onclick="return confirm('Approve this resignation?')">
                                    <i class="fas fa-check"></i> APPROVE
                                </button>
                            </form>
                            <form method="POST" action="approval_process.php" style="display:inline-block;margin:0;">
                                <input type="hidden" name="request_id" value="<?php echo $res_id; ?>">
                                <input type="hidden" name="request_type" value="resignation">
                                <input type="hidden" name="status" value="rejected">
                                <input type="hidden" name="remarks" value="Resignation rejected">
                                <button type="submit" class="btn-reject" style="background:#dc2626;color:#fff;border:none;padding:4px 14px;border-radius:20px;font-size:10px;font-weight:700;cursor:pointer;" onclick="return confirm('Reject this resignation?')">
                                    <i class="fas fa-times"></i> REJECT
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- ===== OT REQUESTS ===== -->
            <?php if (count($pending_ot_requests) > 0 && in_array($current_role, ['process_head'])): ?>
                <?php foreach ($pending_ot_requests as $ot): 
                    $name = !empty($ot['full_name']) ? $ot['full_name'] : 'Unknown';
                    $person_id = !empty($ot['person_id']) ? $ot['person_id'] : '';
                    $ot_hours = !empty($ot['ot_hours']) ? $ot['ot_hours'] : 0;
                    $work_hours = !empty($ot['work_hours']) ? $ot['work_hours'] : 0;
                    $reason = !empty($ot['reason']) ? $ot['reason'] : 'No reason provided';
                    $date = !empty($ot['date']) ? $ot['date'] : date('Y-m-d');
                    $created_at = !empty($ot['created_at']) ? $ot['created_at'] : date('Y-m-d H:i:s');
                ?>
                    <div class="approval-item" style="border-left-color: #2563eb; background: #f0f9ff; margin-bottom: 8px; border-radius: 8px; padding: 10px;">
                        <div class="info">
                            <div class="date-text">
                                <strong><?php echo htmlspecialchars($name); ?></strong>
                                <span class="role-badge-sm" style="background:#2563eb;color:#fff;padding:2px 8px;border-radius:12px;font-size:9px;">
                                    <?php echo htmlspecialchars($person_id); ?>
                                </span>
                                <span class="status-badge-sm pending" style="background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:12px;font-size:9px;font-weight:700;">PENDING</span>
                            </div>
                            <div style="font-size:11px;color:#2563eb;margin-top:2px;font-weight:600;">
                                <i class="fas fa-clock"></i> 
                                <strong>?? OT REQUEST</strong> - <?php echo date('M d, Y', strtotime($date)); ?>
                                <span style="background:#2563eb;color:#fff;padding:1px 8px;border-radius:10px;font-size:9px;margin-left:4px;">
                                    <?php echo $ot_hours; ?>h OT
                                </span>
                            </div>
                            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">
                                Work Hours: <?php echo $work_hours; ?> | Requested: <?php echo date('M d, H:i', strtotime($created_at)); ?>
                            </div>
                            <div class="reason-text" style="font-size:11px;color:#1e293b;margin-top:3px;">
                                <strong>Reason:</strong> <?php echo htmlspecialchars($reason); ?>
                            </div>
                        </div>
                        <div class="approval-actions" style="display:flex;gap:4px;margin-top:6px;">
                            <!-- Line 223: Approve OT -->
<form method="POST" action="dashboard.php" style="display:inline-block;"> <!-- Change this -->
    <input type="hidden" name="action" value="approve_ot"> <!-- Matches switch in dashboard.php -->
    <input type="hidden" name="request_id" value="<?php echo $ot['id']; ?>">
    <input type="hidden" name="request_type" value="overtime">
    <input type="hidden" name="status" value="approved">
   <input type="hidden" name="remarks" value="Approved via Dashboard">
    <button type="submit" class="btn-approve">APPROVE</button>
</form>

<!-- Line 231: Reject OT -->
<form method="POST" action="dashboard.php"> <!-- Change this -->
    <input type="hidden" name="action" value="approve_ot"> <!-- Matches switch in dashboard.php -->
    <input type="hidden" name="request_id" value="<?php echo $ot['id']; ?>">
    <input type="hidden" name="request_type" value="overtime">
    <input type="hidden" name="status" value="rejected">
    <input type="hidden" name="remarks" value="Rejected">
    <button type="submit" class="btn-reject">REJECT</button>
</form>                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- ===== EXCEPTION REQUESTS ===== -->
            <?php foreach ($pending_exceptions as $request): 
                $name = !empty($request['requester_name']) ? $request['requester_name'] : ($request['full_name'] ?? 'Unknown');
                $role = !empty($request['requester_role']) ? $request['requester_role'] : 'agent';
                $type = !empty($request['exception_type']) ? $request['exception_type'] : 'late';
            ?>
                <div class="approval-item">
                    <div class="info">
                        <div class="date-text">
                            <strong><?php echo htmlspecialchars($name); ?></strong>
                            <span class="role-badge-sm <?php echo $role; ?>">
                                <?php echo strtoupper(str_replace('_', ' ', $role)); ?>
                            </span>
                            <span class="status-badge-sm pending">PENDING</span>
                        </div>
                        <div style="font-size:11px;color:var(--yellow);margin-top:2px;font-weight:600;">
                            <i class="fas fa-clock"></i> <?php echo ucfirst(str_replace('_', ' ', $type)); ?> EXCEPTION - <?php echo date('M d', strtotime($request['date'])); ?>
                        </div>
                        <div class="reason-text"><?php echo htmlspecialchars($request['reason'] ?? 'No reason provided'); ?></div>
                    </div>
                    <div class="approval-actions">
                        <button class="btn-approve" onclick="openApprovalModal(<?php echo $request['id']; ?>, '<?php echo $type; ?>', 'approved', '<?php echo htmlspecialchars($name); ?>', '<?php echo date('M d, Y', strtotime($request['date'])); ?>', '<?php echo htmlspecialchars(addslashes($request['reason'] ?? '')); ?>')">
                            <i class="fas fa-check"></i> APPROVE
                        </button>
                        <button class="btn-reject" onclick="openApprovalModal(<?php echo $request['id']; ?>, '<?php echo $type; ?>', 'rejected', '<?php echo htmlspecialchars($name); ?>', '<?php echo date('M d, Y', strtotime($request['date'])); ?>', '<?php echo htmlspecialchars(addslashes($request['reason'] ?? '')); ?>')">
                            <i class="fas fa-times"></i> REJECT
                        </button>
                    </div>
                </div>
            <?php endforeach; ?><!-- ===== LEAVE REQUESTS ===== -->
            <?php foreach ($pending_leave_requests as $request):
                // FIXED: Use 'request_type' as seen in your database screenshot
                $type_code = $request['request_type'] ?? $request['req_type'] ?? 'leave';

                $req_type_display = ucfirst(str_replace('_', ' ', $type_code));
                $border_color = ($type_code === 'holiday') ? 'var(--purple)' : (($type_code === 'coming_late') ? 'var(--cyan)' : (($type_code === 'half_day') ? 'var(--yellow)' : (($type_code === 'early_leave') ? 'var(--purple)' : 'var(--red)')));
                $icon = ($type_code === 'holiday') ? 'fa-calendar-day' : (($type_code === 'coming_late') ? 'fa-clock' : (($type_code === 'half_day') ? 'fa-sun' : (($type_code === 'early_leave') ? 'fa-sign-out-alt' : 'fa-calendar-minus')));

                $date_display = date('M d, Y', strtotime($request['date']));
                if (!empty($request['start_date']) && !empty($request['end_date']) && $request['start_date'] != $request['end_date']) {
                    $date_display = date('M d', strtotime($request['start_date'])) . ' - ' . date('M d, Y', strtotime($request['end_date']));
                }
            ?>
                <div class="approval-item" style="border-left-color: <?php echo $border_color; ?>;">
                    <div class="info">
                        <div class="date-text">
                            <strong><?php echo htmlspecialchars($request['requester_name']); ?></strong>
                            <span class="role-badge-sm <?php echo $request['requester_role'] ?? 'agent'; ?>">
                                <?php echo strtoupper(str_replace('_', ' ', $request['requester_role'] ?? 'Agent')); ?>
                            </span>
                            <span class="status-badge-sm pending">PENDING</span>
                        </div>
                        <div style="font-size:11px;color:<?php echo $border_color; ?>;margin-top:2px;font-weight:600;">
                            <i class="fas <?php echo $icon; ?>"></i> <?php echo strtoupper($req_type_display); ?> REQUEST - <?php echo $date_display; ?>
                        </div>
                        <div class="reason-text"><?php echo htmlspecialchars($request['reason']); ?></div>
                    </div>
                    <div class="approval-actions">
                        <!-- Pass the fixed $type_code variable here -->
                        <button class="btn-approve" onclick="openApprovalModal(<?php echo $request['id']; ?>, '<?php echo $type_code; ?>', 'approved', '<?php echo htmlspecialchars($request['requester_name']); ?>', '<?php echo $date_display; ?>', '<?php echo htmlspecialchars(addslashes($request['reason'])); ?>')">
                            <i class="fas fa-check"></i> APPROVE
                        </button>
                        <button class="btn-reject" onclick="openApprovalModal(<?php echo $request['id']; ?>, '<?php echo $type_code; ?>', 'rejected', '<?php echo htmlspecialchars($request['requester_name']); ?>', '<?php echo $date_display; ?>', '<?php echo htmlspecialchars(addslashes($request['reason'])); ?>')">
                            <i class="fas fa-times"></i> REJECT
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            <!-- ===== ROSTER REQUESTS ===== -->
            <?php foreach ($pending_roster_requests as $request):
                $date_display = date('M d, Y', strtotime($request['date']));
                if (!empty($request['start_date']) && !empty($request['end_date']) && $request['start_date'] != $request['end_date']) {
                    $date_display = date('M d', strtotime($request['start_date'])) . ' - ' . date('M d, Y', strtotime($request['end_date']));
                }
            ?>
                <div class="approval-item" style="border-left-color: var(--blue);">
                    <div class="info">
                        <div class="date-text">
                            <strong><?php echo htmlspecialchars($request['requester_name']); ?></strong>
                            <span class="role-badge-sm <?php echo $request['requester_role'] ?? 'agent'; ?>">
                                <?php echo strtoupper(str_replace('_', ' ', $request['requester_role'] ?? 'Agent')); ?>
                            </span>
                            <span class="status-badge-sm pending">PENDING</span>
                        </div>
                        <div style="font-size:11px;color:var(--blue);margin-top:2px;font-weight:600;">
                            <i class="fas fa-clock"></i> ROSTER REQUEST - <?php echo $date_display; ?> | <?php echo $request['start_time'] ?? '--'; ?> to <?php echo $request['end_time'] ?? '--'; ?> (<?php echo $request['working_hours'] ?? 9; ?> hrs)
                        </div>
                        <div class="reason-text"><?php echo htmlspecialchars($request['reason']); ?></div>
                    </div>
                    <div class="approval-actions">
                        <button class="btn-approve" onclick="openApprovalModal(<?php echo $request['id']; ?>, 'roster', 'approved', '<?php echo htmlspecialchars($request['requester_name']); ?>', '<?php echo $date_display; ?>', '<?php echo htmlspecialchars(addslashes($request['reason'])); ?>')">
                            <i class="fas fa-check"></i> APPROVE
                        </button>
                        <button class="btn-reject" onclick="openApprovalModal(<?php echo $request['id']; ?>, 'roster', 'rejected', '<?php echo htmlspecialchars($request['requester_name']); ?>', '<?php echo $date_display; ?>', '<?php echo htmlspecialchars(addslashes($request['reason'])); ?>')">
                            <i class="fas fa-times"></i> REJECT
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>