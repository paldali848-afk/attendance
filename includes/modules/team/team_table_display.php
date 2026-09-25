<?php


?>
<style>
    /* Add this CSS to your stylesheet or <style> block */
    .month-picker-trigger {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        padding: 6px 14px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
        user-select: none;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }
/* Back Button Styling */
.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 6px 14px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    user-select: none;
}

.back-btn:hover {
    background: #f8fafc;
    border-color: #3b82f6;
    color: #2563eb;
    transform: translateX(-3px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.back-btn i {
    font-size: 12px;
    transition: transform 0.2s ease;
}

.back-btn:hover i {
    animation: arrowPulse 0.6s infinite alternate;
}

@keyframes arrowPulse {
    from { transform: translateX(0); }
    to { transform: translateX(-2px); }
}
    .month-picker-trigger:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    .month-picker-trigger i {
        color: #64748b;
        font-size: 14px;
    }

    .month-picker-trigger span {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
    }

    /* Paid Holiday dots */
    .day-dot.ph {
        background: #fef3c7 !important;
        color: #b45309 !important;
        border: 1px solid #d97706 !important;
    }

    .status-badge.ph {
        background: #fef3c7 !important;
        color: #b45309 !important;
    }

    /* OT styling */
    .day-dot.ot {
        background: #dbeafe !important;
        color: #2563eb !important;
        border: 1px solid #2563eb !important;
        font-weight: 800;
    }

    .status-badge.ot {
        background: #dbeafe !important;
        color: #2563eb !important;
    }

    .day-dot.has-ot {
        position: relative;
    }

    .day-dot.has-ot::after {
        content: '';
        position: absolute;
        top: -4px;
        right: -4px;
        font-size: 6px;
        color: #2563eb;
    }

    .ot-badge {
        background: #dbeafe;
        color: #2563eb;
        padding: 1px 6px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 700;
    }

    /* Scheduled (Roster) dots */
    .day-dot.scheduled {
        background: #fff8e1 !important;
        color: #b45309 !important;
        border: 1px solid #ffd93d !important;
        font-weight: 800;
    }

    .status-badge.scheduled {
        background: #fff8e1;
        color: #b45309;
        border: 1px solid #ffd93d;
    }

    /* Sandwich Leave */
  


    /* LWP dot - RED */
    .day-dot.lwp {
        background: #fee2e2 !important;
        color: #dc2626 !important;
        border: 1px solid #dc2626 !important;
        font-weight: 800;
    }
    .status-badge.lwp {
        background: #fee2e2;
        color: #dc2626;
        border: 1px solid #dc2626;
    }

    /* PL dot - PURPLE */
    .day-dot.pl {
        background: #f3e8ff !important;
        color: #7c3aed !important;
        border: 1px solid #7c3aed !important;
        font-weight: 800;
    }
    .status-badge.pl {
        background: #f3e8ff;
        color: #7c3aed;
        border: 1px solid #7c3aed;
    }
</style>

<div class="card anim-fade-up anim-delay-2" style="margin-bottom:24px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; padding: 15px 20px;">
        <h3 style="margin:0;">
            <i class="fas fa-users"></i>
            <?php
            if ($viewing_attendance) {
                $stmt = $pdo->prepare("SELECT reporting_to FROM users WHERE id = ?");
                $stmt->execute([$agent_id_for_attendance]);
                $parent_id = $stmt->fetchColumn();
                if ($parent_id) {
                    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                    $stmt->execute([$parent_id]);
                    $parent_name = $stmt->fetchColumn();
                    echo htmlspecialchars($parent_name) . "'s TEAM";
                } else {
                    echo "TEAM";
                }
            } else {
                $display_name = ($selected_user_id != $user['id']) ? $selected_user['full_name'] . "'s " : "MY ";
                $role_display = !empty($current_hierarchy_role) ? ucfirst(str_replace('_', ' ', $current_hierarchy_role)) . 's' : 'TEAM';
                echo $display_name . $role_display;
            }
            ?>
        </h3>

        <div class="header-actions" style="display:flex; align-items:center; gap:12px;">

            <div class="month-picker-trigger" onclick="document.getElementById('hidden_month_picker').showPicker()">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo date('M Y', strtotime("$selected_year-$selected_month-01")); ?></span>
            </div>

            <input type="month" id="hidden_month_picker" style="position:absolute; opacity:0; pointer-events:none;"
                value="<?php echo "$selected_year-" . str_pad($selected_month, 2, '0', STR_PAD_LEFT); ?>"
                onchange="const [y, m] = this.value.split('-'); window.location.href='dashboard.php?month=' + m + '&year=' + y + (new URLSearchParams(window.location.search).has('user_id') ? '&user_id=' + new URLSearchParams(window.location.search).get('user_id') : '')">
        </div>
    </div>

    <div class="card anim-fade-up anim-delay-2" style="margin-bottom:24px;">
        <div class="card-header" style="flex-direction: column; align-items: flex-start; gap: 12px;">
            <div style="display:flex; justify-content: space-between; width: 100%; align-items: center;">
                <h3><i class="fas fa-sitemap"></i> Team Hierarchy </h3>
                <div style="display:flex;align-items:center;gap:8px;">
                    <?php if ($back_user): ?>
                        <a href="dashboard.php?user_id=<?php echo $back_user['id']; ?>&role=<?php echo $back_user['role']; ?>" class="back-btn">
                            <i class="fas fa-arrow-left"></i> BACK
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="breadcrumb-trail">
                <?php foreach ($hierarchy_path as $index => $crumb): ?>
                    <div class="breadcrumb-item">
                        <?php if ($index < count($hierarchy_path) - 1): ?>
                            <a href="dashboard.php?user_id=<?php echo $crumb['id']; ?>&role=<?php echo $crumb['role']; ?>" class="breadcrumb-link">
                                <?php echo htmlspecialchars($crumb['full_name']); ?>
                            </a>
                            <i class="fas fa-chevron-right breadcrumb-separator"></i>
                        <?php else: ?>
                            <span class="breadcrumb-current"><?php echo htmlspecialchars($crumb['full_name']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="team-table-wrapper">
            <table class="team-table">
                <thead>
                    <tr>
                        <th style="text-align:left;padding-left:12px;">Name</th>
                        <?php for ($d = 1; $d <= $total_days_in_month; $d++): ?>
                            <th style="width:30px;font-size:10px;padding:4px 2px;">
                                <?php
                                $date_str = "$selected_year-$selected_month-" . str_pad($d, 2, '0', STR_PAD_LEFT);
                                $day_of_week = date('w', strtotime($date_str));
                                $is_weekend = ($day_of_week == 0);
                                ?>
                                <span style="<?php echo $is_weekend ? 'color:var(--text-muted);' : ''; ?>">
                                    <?php echo $d; ?>
                                </span>
                            </th>
                        <?php endfor; ?>
                        <th style="min-width:40px;color:var(--green);font-size:11px;">P</th>
                        <th style="min-width:40px;color:var(--red);font-size:11px;">A</th>
                        <th style="min-width:40px;color:var(--yellow);font-size:11px;">L</th>
                        <th style="min-width:40px;color:var(--exception-color);font-size:11px;">EX</th>
                        <th style="min-width:40px;color:#2563eb;font-size:11px;">OT</th>
                        <th style="min-width:40px;color:#9d174d;font-size:11px;">PL</th>
                        <th style="min-width:60px;color:var(--blue);font-size:11px;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_p = $total_a = $total_l = $total_ex = $total_ot = $total_pl = $total_balance = 0;
                    $today_str = date('Y-m-d');

                    // ===== FETCH ALL HOLIDAYS FOR THIS MONTH (once) =====
                    $stmt_hol = $pdo->prepare("SELECT holiday_date FROM holidays WHERE YEAR(holiday_date) = ? AND MONTH(holiday_date) = ?");
                    $stmt_hol->execute([$selected_year, $selected_month]);
                    $team_holidays = [];
                    foreach ($stmt_hol->fetchAll(PDO::FETCH_COLUMN) as $hdate) {
                        $team_holidays[$hdate] = true;
                    }

                    if (empty($team_members)): ?>
                        <tr>
                            <td colspan="<?php echo $total_days_in_month + 8; ?>" style="text-align:center;padding:50px 20px;color:var(--text-muted);">
                                <i class="fas fa-users-slash" style="font-size:24px;display:block;margin-bottom:10px;opacity:0.3;"></i>
                                No team members found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        // =====================================================
                        // PRE-FETCH: rosters, exceptions, leaves, sandwich
                        // (all done ONCE, outside the member loop)
                        // =====================================================
                  $team_person_ids = array_column($team_members, 'person_id');
$roster_map = [];
$team_exceptions_map = [];
$team_leaves_map = [];
$team_sandwich_map = [];

if (!empty($team_person_ids)) {
    $placeholders = implode(',', array_fill(0, count($team_person_ids), '?'));
    $month_start = "$selected_year-$selected_month-01";
    $month_end   = date('Y-m-t', strtotime($month_start));

    // 1. Approved rosters
    $stmt_r = $pdo->prepare("SELECT person_id, start_date, end_date FROM roster_requests 
                             WHERE person_id IN ($placeholders) AND status = 'approved' 
                             AND (start_date <= LAST_DAY(?) AND end_date >= ?)");
    $stmt_r->execute(array_merge($team_person_ids, [$month_start, $month_start]));
    foreach ($stmt_r->fetchAll() as $r) {
        $roster_map[$r['person_id']][] = ['start' => $r['start_date'], 'end' => $r['end_date']];
    }

    // 2. Approved exceptions
    $stmt_team_exc = $pdo->prepare("SELECT person_id, date, exception_type FROM late_exception_requests 
                                    WHERE person_id IN ($placeholders) AND status = 'approved' 
                                    AND YEAR(date) = ? AND MONTH(date) = ?");
    $stmt_team_exc->execute(array_merge($team_person_ids, [$selected_year, $selected_month]));
    while ($row = $stmt_team_exc->fetch(PDO::FETCH_ASSOC)) {
        $team_exceptions_map[$row['person_id']][$row['date']] = $row['exception_type'];
    }

    // 3. Approved leaves
    $stmt_team_lv = $pdo->prepare("SELECT person_id, start_date, end_date, request_type 
                                   FROM leave_requests 
                                   WHERE person_id IN ($placeholders) AND status = 'approved' 
                                   AND (start_date <= LAST_DAY(?) AND end_date >= ?)");
    $stmt_team_lv->execute(array_merge($team_person_ids, [$month_start, $month_start]));
    while ($row = $stmt_team_lv->fetch(PDO::FETCH_ASSOC)) {
        $s = new DateTime($row['start_date']);
        $e = new DateTime($row['end_date']);
        $e->modify('+1 day');
        $period = new DatePeriod($s, new DateInterval('P1D'), $e);
        foreach ($period as $dt) {
            $team_leaves_map[$row['person_id']][$dt->format('Y-m-d')] = $row['request_type'];
        }
    }

    // 4. AUTO-DETECT SANDWICH (Saturday off + Mon off ? Sun = A)
    // PH dates in range + buffer
    $ph_dates_team = [];
    $ph_stmt_team = $pdo->prepare("
        SELECT holiday_date FROM holidays
        WHERE holiday_date BETWEEN DATE_SUB(?, INTERVAL 3 DAY)
                               AND DATE_ADD(?, INTERVAL 3 DAY)
    ");
    $ph_stmt_team->execute([$month_start, $month_end]);
    while ($row = $ph_stmt_team->fetch(PDO::FETCH_ASSOC)) {
        $ph_dates_team[$row['holiday_date']] = true;
    }

    // Off-days for all team members
    $off_by_person = [];
    $off_stmt_team = $pdo->prepare("
        SELECT person_id, date FROM attendance
        WHERE person_id IN ($placeholders)
          AND date BETWEEN DATE_SUB(?, INTERVAL 3 DAY)
                        AND DATE_ADD(?, INTERVAL 3 DAY)
          AND (
              status IN ('A','PL','HPL','LWP','SW')
              OR check_in IS NULL
              OR check_in = '00:00:00'
          )
    ");
    $off_stmt_team->execute(array_merge($team_person_ids, [$month_start, $month_end]));
    while ($row = $off_stmt_team->fetch(PDO::FETCH_ASSOC)) {
        $off_by_person[$row['person_id']][$row['date']] = true;
    }

    // Collect all Saturdays in the window
    $saturdays = [];
    $tmp = new DateTime($month_start);
    $tmp->modify('-3 days');
    $scan_end_dt = new DateTime($month_end);
    $scan_end_dt->modify('+3 days');
    while ($tmp <= $scan_end_dt) {
        if ((int)$tmp->format('N') === 6) {
            $saturdays[] = $tmp->format('Y-m-d');
        }
        $tmp->modify('+1 day');
    }

    // For each person × each Saturday
    foreach ($team_person_ids as $pid) {
        $off_days_p = $off_by_person[$pid] ?? [];
        foreach ($saturdays as $sat) {
            $sun = date('Y-m-d', strtotime($sat . ' +1 day'));
            $mon = date('Y-m-d', strtotime($sat . ' +2 days'));
            $tue = date('Y-m-d', strtotime($sat . ' +3 days'));

            if (!isset($off_days_p[$sat])) continue;
            if (isset($ph_dates_team[$sat]) || isset($ph_dates_team[$sun])) continue;

            if (!isset($ph_dates_team[$mon])) {
                if (isset($off_days_p[$mon])) {
                    $team_sandwich_map[$pid][$sun] = true;
                }
            } else {
                if (isset($off_days_p[$tue])) {
                    $team_sandwich_map[$pid][$sun] = true;
                    $team_sandwich_map[$pid][$tue] = true;
                }
            }
        }
    }
}
                        foreach ($team_members as $member):
                            $member_role = $member['role'] ?? 'agent';
                            $member_pos = strtoupper($member['position'] ?? '');
                            $member_process = strtoupper($member['process'] ?? '');
                            $is_csr_agent = (strpos($member_pos, 'CSR') !== false || strpos($member_pos, 'AGENT') !== false);

                            $ot_threshold = ($is_csr_agent || in_array($member_role, ['process_head', 'centre_head'])) ? 8 : 9;
                            $hd_threshold = ($member_process === 'UPGRADE') ? 4.5 : 5.5;

                            // Fetch attendance for this member (once)
                            try {
                                $stmt = $pdo->prepare("
                                    SELECT a.*, rr.start_time as roster_start, ler.exception_type as approved_leave
                                    FROM attendance a 
                                    LEFT JOIN late_exception_requests ler 
                                        ON a.person_id COLLATE utf8mb4_unicode_ci = ler.person_id COLLATE utf8mb4_unicode_ci 
                                        AND a.date = ler.date 
                                        AND ler.status = 'approved'
                                    LEFT JOIN roster_requests rr 
                                        ON a.person_id COLLATE utf8mb4_unicode_ci = rr.person_id COLLATE utf8mb4_unicode_ci 
                                        AND a.date BETWEEN rr.start_date AND rr.end_date 
                                        AND rr.status = 'approved'
                                    WHERE a.person_id = ? AND YEAR(a.date) = ? AND MONTH(a.date) = ?
                                    ORDER BY a.date ASC
                                ");
                                $stmt->execute([$member['person_id'], $selected_year, $selected_month]);
                                $full_attendance_records = $stmt->fetchAll();
                            } catch (PDOException $e) {
                                error_log("Query Error: " . $e->getMessage());
                                $full_attendance_records = [];
                            }

                            $full_attendance_map = [];
                            foreach ($full_attendance_records as $rec) {
                                $day = date('j', strtotime($rec['date']));
                                $full_attendance_map[$day] = $rec;
                            }

                            // Counters
                            $row_p = $row_a = $row_l = $row_ex = $row_pl = $row_ot = $row_ph = 0;
                            $row_html = '';

                            for ($d = 1; $d <= $total_days_in_month; $d++) {
                                $date_str = "$selected_year-$selected_month-" . str_pad($d, 2, '0', STR_PAD_LEFT);
                                $is_sun = (date('w', strtotime($date_str)) == 0);
                                $is_holiday = isset($team_holidays[$date_str]);
                                $full_record = $full_attendance_map[$d] ?? null;

                                $status_code = '-';
                                $status_class = 'no-data';
                                $tooltip_content = '';
                                $has_ot = false;
                                $ot_hours = 0;

                                // Roster check
                                $has_approved_roster = false;
                                if (isset($roster_map[$member['person_id']])) {
                                    foreach ($roster_map[$member['person_id']] as $r_range) {
                                        if ($date_str >= $r_range['start'] && $date_str <= $r_range['end']) {
                                            $has_approved_roster = true;
                                            break;
                                        }
                                    }
                                }

                                // ? Leave type for this specific day
                                $leave_type_for_day = $team_leaves_map[$member['person_id']][$date_str] ?? null;

                                // ? Sandwich day flag
                                $is_sandwich_day = isset($team_sandwich_map[$member['person_id']][$date_str]);

                                // Extract attendance vars
                                $st_db = '';
                                $approved_leave_type = null;
                                $check_in = null;
                                $check_out = null;
                                $work_hours = 0;
                                $display_hours = '0.00';

                                if ($full_record) {
                                    $st_db = strtoupper(trim((string)($full_record['status'] ?? '')));
                                    $approved_leave_type = $team_exceptions_map[$member['person_id']][$date_str] ?? ($full_record['approved_leave'] ?? null);
                                    $check_in = $full_record['check_in'] ?? null;
                                    $check_out = $full_record['check_out'] ?? null;
                                    $work_hours = (float)($full_record['work'] ?? 0);
                                    $display_hours = number_format($work_hours, 2);
                                }

                                // =====================================================
                                // PRIORITY LOGIC
                                // =====================================================

                                // 1. Approved exception (late_exception_requests)
                                if (!empty($approved_leave_type)) {
                                    if (in_array($approved_leave_type, ['full_day', 'late', 'traffic', 'regularize'])) {
                                        $status_code = 'P'; $status_class = 'present'; $row_p++;
                                    } elseif ($approved_leave_type === 'half_day') {
                                        $status_code = 'HD'; $status_class = 'halfday';
                                        $row_p += 0.5; $row_a += 0.5;
                                    } elseif (in_array($approved_leave_type, ['pl_adjustment', 'leave'])) {
                                        $status_code = 'PL'; $status_class = 'pl'; $row_pl++;
                                    }
                                }
                                // 2. Holiday
                                elseif ($is_holiday) {
                                    if (!empty($check_in) && $check_in !== '00:00:00') {
                                        $status_code = 'P'; $status_class = 'present'; $row_p++;
                                    } else {
                                        $status_code = 'PH'; $status_class = 'ph'; $row_ph++;
                                    }
                                }
                                // 2.5 ? Approved leave (leave_requests) OR DB status already PL/LWP/HPL
                                elseif (
                                    !empty($leave_type_for_day)
                                    || in_array($st_db, ['PL','LWP','HPL','PLANNED LEAVE','PERSONAL LEAVE','LEAVE WITHOUT PAY','HALF PAID LEAVE'])
                                ) {
                                    $lv_code = in_array($st_db, ['PL','LWP','HPL'])
                                        ? $st_db
                                        : strtoupper(trim((string)$leave_type_for_day));

                                    if (in_array($lv_code, ['LWP', 'LEAVE WITHOUT PAY'])) {
                                        $status_code = 'LWP'; $status_class = 'lwp';
                                    } elseif (in_array($lv_code, ['HPL', 'HALF PAID LEAVE'])) {
                                        $status_code = 'HPL'; $status_class = 'hpl';
                                        $row_p += 0.5;
                                    } else {
                                        $status_code = 'PL'; $status_class = 'pl';
                                        $row_pl++;
                                    }
                                }
                                // 2.7 ? Sandwich Leave (from DB or sandwich_dates column)
                              elseif ($is_sandwich_day 
        && !in_array($st_db, ['PL','LWP','HPL','PLANNED LEAVE','PERSONAL LEAVE','LEAVE WITHOUT PAY','HALF PAID LEAVE'])) {
    $status_code = 'A'; $status_class = 'absent'; $row_a++;
}
                               // 3. Sunday / WO
elseif ($is_sun || $st_db === 'WO' || $st_db === 'WEEK OFF' || $st_db === 'WEEKOFF') {

    $has_punch = (!empty($check_in) && $check_in !== '00:00:00' && $work_hours > 0);

    if ($has_punch) {
        // ===== Punched in on Sunday =====
        if ($work_hours >= $hd_threshold) {
            $status_code = 'P'; $status_class = 'present'; $row_p++;
        } else {
            $status_code = 'HD'; $status_class = 'halfday';
            $row_p += 0.5; $row_a += 0.5;
        }
        if ($full_record && ($full_record['ot_approved'] ?? 0) == 1) {
            $has_ot = true;
            $ot_hours = (float)($full_record['ot_hours'] ?? 0);
            $row_ot += $ot_hours;
        }
    } else {
        // ===== No punch-in on Sunday =====
        // Sandwich case is the ONLY time we show A
        if ($is_sandwich_day) {
            $status_code = 'A'; $status_class = 'absent'; $row_a++;
        } else {
            // Regardless of DB status (even if DB says 'A'), show WO
            $status_code = 'WO'; $status_class = 'weekoff';
        }
    }
}                                // 4. Has an actual DB record
                                elseif ($full_record) {
                                    if ($has_approved_roster && (empty($check_in) || $check_in === '00:00:00')) {
                                        if ($date_str > $today_str) {
                                            $status_code = 'S'; $status_class = 'scheduled';
                                        } else {
                                            $status_code = 'A'; $status_class = 'absent'; $row_a++;
                                        }
                                    } else {
                                        $status_info = getStatusDisplay($st_db, null);
                                        $status_code = $status_info['code'] ?? $st_db;
                                        $status_class = $status_info['class'] ?? 'no-data';

                                        if ($st_db === 'P' || $st_db === 'PRESENT') { $row_p++; }
                                        elseif ($st_db === 'L' || $st_db === 'LATE') { $row_p++; $row_l++; }
                                        elseif ($st_db === 'HD' || $st_db === 'HALF DAY') { $row_p += 0.5; $row_a += 0.5; }
                                        elseif ($st_db === 'A' || $st_db === 'ABSENT') { $row_a++; }
                                    }
                                }
                                // 5. No DB record
                                else {
                                    if ($has_approved_roster) {
                                        if ($date_str > $today_str) {
                                            $status_code = 'S'; $status_class = 'scheduled';
                                        } else {
                                            $status_code = 'A'; $status_class = 'absent'; $row_a++;
                                        }
                                    } elseif ($date_str <= $today_str) {
                                        $status_code = 'A'; $status_class = 'absent'; $row_a++;
                                    } else {
                                        $status_code = '-'; $status_class = 'no-data';
                                    }
                                }

                                // OT calculation (if not already added)
                                if ($full_record && ($full_record['ot_approved'] ?? 0) == 1 && !$has_ot) {
                                    $ot_hours = (float)($full_record['ot_hours'] ?? 0);
                                    $has_ot = true;
                                    $row_ot += $ot_hours;
                                }

                                // ===== BUILD TOOLTIP =====
                                $tooltip_content = '<div class="tooltip"><div class="tooltip-date">' . date('l, M d', strtotime($date_str)) . '</div>';
                                if ($check_in && $check_in !== '00:00:00') {
                                    $tooltip_content .= '<div class="time-row"><span class="label">In:</span><span class="value">' . date('h:i A', strtotime($check_in)) . '</span></div>';
                                }
                                if ($check_out && $check_out !== '00:00:00') {
                                    $tooltip_content .= '<div class="time-row"><span class="label">Out:</span><span class="value">' . date('h:i A', strtotime($check_out)) . '</span></div>';
                                }
                                if ($work_hours > 0) {
                                    $tooltip_content .= '<div class="time-row"><span class="label">Hours:</span><span class="value">' . $display_hours . 'h</span></div>';
                                }
                                if ($has_ot) {
                                    $tooltip_content .= '<div class="time-row"><span class="label" style="color:#2563eb;">OT:</span><span class="value" style="color:#2563eb;font-weight:700;">' . $ot_hours . 'h</span></div>';
                                }
                                if ($is_holiday) {
                                    $tooltip_content .= '<div class="time-row"><span class="label">Holiday:</span><span class="value">Yes</span></div>';
                                }
                                if ($has_approved_roster) {
                                    $tooltip_content .= '<div class="time-row"><span class="label">Roster:</span><span class="value">Approved</span></div>';
                                }
                                if (!empty($leave_type_for_day)) {
                                    $tooltip_content .= '<div class="time-row"><span class="label">Leave:</span><span class="value">' . htmlspecialchars($leave_type_for_day) . '</span></div>';
                                }
                                if ($is_sandwich_day) {
                                    $tooltip_content .= '<div class="time-row"><span class="label">Sandwich:</span><span class="value">Yes</span></div>';
                                }

                                $ot_badge = ($has_ot) ? ' <span class="ot-badge">+' . $ot_hours . 'h</span>' : '';
                                $tooltip_content .= '<div style="margin-top:4px;"><span class="status-badge ' . $status_class . '">' . $status_code . '</span>' . $ot_badge . '</div></div>';

                                $row_html .= '<td class="day-cell"><span class="day-dot ' . $status_class . ($has_ot ? ' has-ot' : '') . '">' . $status_code . '</span>' . $tooltip_content . '</td>';
                            }

                            $pl_bal = number_format((float)($team_pl_balances[$member['id']] ?? 0), 1);
                            $total_p += $row_p;
                            $total_a += $row_a;
                            $total_l += $row_l;
                            $total_ex += $row_ex;
                            $total_ot += $row_ot;
                            $total_pl += $row_pl;
                            $total_balance += (float)$pl_bal;
                        ?>
                            <tr class="<?php echo ($member['role'] == 'agent' ? 'row-static' : 'row-clickable') . (($viewing_attendance && $member['id'] == $agent_id_for_attendance) ? ' active-row' : ''); ?>"
                                <?php if ($member['role'] != 'agent'): ?> onclick="navigateToTeam(<?php echo $member['id']; ?>, '<?php echo $member['role']; ?>')" <?php endif; ?>>
                                <td class="name-cell"><?php echo htmlspecialchars($member['full_name']); ?></td>
                                <?php echo $row_html; ?>
                                <td class="stat-cell present"><?php echo $row_p; ?></td>
                                <td class="stat-cell absent"><?php echo $row_a; ?></td>
                                <td class="stat-cell late"><?php echo $row_l; ?></td>
                                <td class="stat-cell exception"><?php echo $row_ex; ?></td>
                                <td class="stat-cell" style="color:#2563eb;font-weight:800;"><?php echo number_format($row_ot, 1); ?></td>
                                <td class="stat-cell" style="color:#9d174d;font-weight:800;"><?php echo $row_pl; ?></td>
                                <td class="stat-cell" style="color:var(--blue);font-weight:800;"><?php echo $pl_bal; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- ===== TOTAL ROW ===== -->
                        <tr style="background:var(--blue-bg);border-top:3px solid var(--blue);font-weight:800;">
                            <td class="name-cell" style="font-weight:800;color:var(--blue);font-size:14px;border-top:3px solid var(--blue);">
                                <i class="fas fa-calculator"></i> TOTAL
                            </td>
                            <?php for ($d = 1; $d <= $total_days_in_month; $d++): ?>
                                <td style="text-align:center;font-size:10px;color:var(--text-muted);border-top:3px solid var(--blue);">-</td>
                            <?php endfor; ?>
                            <td class="stat-cell present" style="font-size:16px;border-top:3px solid var(--blue);"><?php echo $total_p; ?></td>
                            <td class="stat-cell absent" style="font-size:16px;border-top:3px solid var(--blue);"><?php echo $total_a; ?></td>
                            <td class="stat-cell late" style="font-size:16px;border-top:3px solid var(--blue);"><?php echo $total_l; ?></td>
                            <td class="stat-cell exception" style="font-size:16px;border-top:3px solid var(--blue);"><?php echo $total_ex; ?></td>
                            <td class="stat-cell" style="font-size:16px;color:#2563eb;font-weight:800;border-top:3px solid var(--blue);"><?php echo number_format($total_ot, 1); ?></td>
                            <td class="stat-cell" style="font-size:16px;color:#9d174d;font-weight:800;border-top:3px solid var(--blue);"><?php echo $total_pl; ?></td>
                            <td class="stat-cell" style="font-size:16px;color:var(--blue);font-weight:800;border-top:3px solid var(--blue);"><?php echo number_format($total_balance, 1); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        function toggleMonthPicker() {
            document.getElementById('hidden_month_picker').showPicker();
        }
    </script>