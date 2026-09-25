<?php
// ============================================
// AGENT ATTENDANCE DETAIL VIEW
// ============================================

// ===== DEBUG: Check if viewing attendance =====
echo "<!-- DEBUG: viewing_attendance = " . ($viewing_attendance ? 'true' : 'false') . " -->";

if ($viewing_attendance):
    echo "<!-- DEBUG: Inside viewing_attendance -->";
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$agent_id_for_attendance]);
    $agent = $stmt->fetch();
    
    echo "<!-- DEBUG: Agent found: " . ($agent ? $agent['full_name'] : 'NO') . " -->";
    
    if ($agent):
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE person_id = ? AND YEAR(date) = ? AND MONTH(date) = ? ORDER BY date DESC");
        $stmt->execute([$agent['person_id'], $selected_year, $selected_month]);
        $agent_attendance = $stmt->fetchAll();
        
        // ===== DEBUG: Check for HPL in attendance data =====
        $hpl_count = 0;
        $hpl_dates = [];
        $all_statuses = [];
        
        foreach ($agent_attendance as $record) {
            $status_clean = trim($record['status']);
            $all_statuses[] = $status_clean . ' (' . $record['date'] . ')';
            
            if ($status_clean === 'HPL') {
                $hpl_count++;
                $hpl_dates[] = $record['date'];
            }
        }
        
        echo "<!-- DEBUG: Total records found: " . count($agent_attendance) . " -->";
        echo "<!-- DEBUG: All statuses: " . implode(', ', $all_statuses) . " -->";
        
        if ($hpl_count > 0) {
            echo "<!-- ✅ HPL found! Count: " . $hpl_count . " on dates: " . implode(', ', $hpl_dates) . " -->";
        } else {
            echo "<!-- ❌ No HPL found in attendance data -->";
        }
?>
<style>
    /* Status Badge Styles - Matching Calendar */
    .status-badge-sm {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.3px;
        text-transform: uppercase;
        min-width: 40px;
        text-align: center;
    }

    /* HPL - Half Paid Leave (Purple) - MATCHES CALENDAR */
    .status-badge-sm.hpl-status {
        background: linear-gradient(135deg, #ede9fe 0%, #c4b5fd 100%) !important;
        color: #5b21b6 !important;
        border: 2px solid #8b5cf6 !important;
        font-weight: 800 !important;
    }

    /* PL - Paid Leave (Blue) */
    .status-badge-sm.pl-status {
        background: #3b82f6 !important;
        color: #fff !important;
    }

    /* LWP - Leave Without Pay (Red) */
    .status-badge-sm.lwp-status {
        background: #ef4444 !important;
        color: #fff !important;
    }

    /* WO - Week Off (Orange) */
    .status-badge-sm.wo-status {
        background: #f59e0b !important;
        color: #fff !important;
    }

    /* HD - Half Day (Yellow) */
    .status-badge-sm.hd-status {
        background: #fef9c3 !important;
        color: #000 !important;
        border: 2px solid #eab308 !important;
    }

    /* CO - Comp Off (Cyan) */
    .status-badge-sm.co-status {
        background: #06b6d4 !important;
        color: #fff !important;
    }

    /* Present (Green) */
    .status-badge-sm.approved {
        background: #22c55e !important;
        color: #fff !important;
    }

    /* Late (Orange) */
    .status-badge-sm.pending {
        background: #f59e0b !important;
        color: #fff !important;
    }

    /* Absent (Red) */
    .status-badge-sm.rejected {
        background: #ef4444 !important;
        color: #fff !important;
    }

    /* Exception (Purple) */
    .status-badge-sm.exception-status {
        background: #8b5cf6 !important;
        color: #fff !important;
    }

    /* Default */
    .status-badge-sm.default-status {
        background: #94a3b8 !important;
        color: #fff !important;
    }

    /* Holiday */
    .status-badge-sm.holiday-status {
        background: #ec4899 !important;
        color: #fff !important;
    }

    /* Scheduled */
    .status-badge-sm.scheduled-status {
        background: #ffd93d !important;
        color: #6b5200 !important;
    }

    /* No Data */
    .status-badge-sm.no-data-status {
        background: #f1f5f9 !important;
        color: #94a3b8 !important;
    }
</style>
    <div style="margin-top:16px;border-top:1px solid var(--border-light);padding-top:16px;">
        <div class="dashboard-grid full-width">
            <div class="card anim-fade-up anim-delay-4">
                <div class="card-header">
                    <h3><i class="fas fa-user-clock"></i> <?php echo htmlspecialchars($agent['full_name']); ?>'s ATTENDANCE LOG</h3>
                    <a href="dashboard.php?user_id=<?php echo $selected_user_id; ?>&role=<?php echo $selected_role; ?>" class="back-btn">
                        <i class="fas fa-arrow-left"></i> CLEAR
                    </a>
                </div>
                <div style="overflow-x:auto;">
                    <?php if (empty($agent_attendance)): ?>
                        <div style="text-align:center;padding:30px 20px;color:var(--text-muted);">
                            <i class="fas fa-calendar-times" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.3;"></i>
                            <p style="font-weight:700;font-size:15px;">NO RECORDS FOUND</p>
                            <p style="font-size:13px;font-weight:600;margin-top:4px;">No attendance records for this month.</p>
                        </div>
                    <?php else: ?>
                        <table style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr style="background:var(--bg-light);border-bottom:2px solid var(--border-light);">
                                    <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Date</th>
                                    <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Status</th>
                                    <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Exception Type</th>
                                    <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Check In</th>
                                    <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Check Out</th>
                                    <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agent_attendance as $record): 
                                    $status = trim($record['status']);
                                    $work_hours = (float)($record['work'] ?? 0);
                                    
                                    // ===== DEBUG: Show raw status for each record =====
                                    echo "<!-- DEBUG: Record date: " . $record['date'] . ", Raw status: '" . $status . "', Length: " . strlen($status) . " -->";
                                    
                                    // ===== DETERMINE STATUS CLASS - HPL FIRST =====
                                    $status_class = 'default-status';
                                    $display_status = $status;
                                    $status_style = '';
                                    
                                    // HPL - Highest Priority (Half Paid Leave) - MATCHES CALENDAR
                                    if ($status === 'HPL') {
                                        $status_class = 'hpl-status';
                                        $display_status = 'HPL';
                                        $status_style = 'background: linear-gradient(135deg, #ede9fe 0%, #c4b5fd 100%) !important; color: #5b21b6 !important; border: 2px solid #8b5cf6 !important; font-weight: 800 !important;';
                                        echo "<!-- ✅ HPL detected in loop for date: " . $record['date'] . " -->";
                                    }
                                    // PL - Paid Leave
                                    elseif (in_array($status, ['PL', 'Personal Leave', 'Planned Leave'])) {
                                        $status_class = 'pl-status';
                                        $display_status = 'PL';
                                    }
                                    // LWP - Leave Without Pay
                                    elseif (in_array($status, ['LWP', 'LV', 'Leave'])) {
                                        $status_class = 'lwp-status';
                                        $display_status = 'LWP';
                                    }
                                    // WO - Week Off
                                    elseif (in_array($status, ['WO', 'Week Off', 'Weekoff'])) {
                                        $status_class = 'wo-status';
                                        $display_status = 'WO';
                                    }
                                    // HD - Half Day
                                    elseif (in_array($status, ['HD', 'Half Day'])) {
                                        $status_class = 'hd-status';
                                        $display_status = 'HD';
                                    }
                                    // CO - Comp Off
                                    elseif ($status === 'CO') {
                                        $status_class = 'co-status';
                                        $display_status = 'CO';
                                    }
                                    // FPE - Full Paid Exception
                                    elseif ($status === 'FPE') {
                                        $status_class = 'exception-status';
                                        $display_status = 'FPE';
                                    }
                                    // HPE - Half Paid Exception
                                    elseif ($status === 'HPE') {
                                        $status_class = 'exception-status';
                                        $display_status = 'HPE';
                                    }
                                    // Holiday
                                    elseif (in_array($status, ['H', 'Holiday', 'PH'])) {
                                        $status_class = 'holiday-status';
                                        $display_status = 'H';
                                    }
                                    // Scheduled
                                    elseif (strpos(strtolower($status), 'scheduled') !== false || $status === 'Scheduled') {
                                        $status_class = 'scheduled-status';
                                        $display_status = 'S';
                                    }
                                    // Exception
                                    elseif (strpos(strtolower($status), 'exception') !== false) {
                                        $status_class = 'exception-status';
                                        $display_status = 'EX';
                                    }
                                    // Late
                                    elseif (in_array($status, ['L', 'Late', 'CL', 'Coming Late'])) {
                                        $status_class = 'pending';
                                        $display_status = 'L';
                                    }
                                    // Absent
                                    elseif (in_array($status, ['A', 'Absent'])) {
                                        $status_class = 'rejected';
                                        $display_status = 'A';
                                    }
                                    // Present
                                    elseif (in_array($status, ['P', 'Present'])) {
                                        $status_class = 'approved';
                                        $display_status = 'P';
                                    }
                                    // No Data
                                    elseif ($status === '-' || $status === 'No Data') {
                                        $status_class = 'no-data-status';
                                        $display_status = '-';
                                    }
                                    // Default - show as is
                                    else {
                                        $status_class = 'default-status';
                                        $display_status = $status;
                                    }
                                    
                                    // Fix hours display - if work is 0 but there's check-in/out, calculate
                                    if ($work_hours == 0 && $record['check_in'] && $record['check_out']) {
                                        $check_in = strtotime($record['check_in']);
                                        $check_out = strtotime($record['check_out']);
                                        if ($check_out > $check_in) {
                                            $work_hours = ($check_out - $check_in) / 3600;
                                        }
                                    }
                                    
                                    // Format hours
                                    $hours_display = '--';
                                    if ($work_hours > 0) {
                                        $whole = floor($work_hours);
                                        $minutes = round(($work_hours - $whole) * 60);
                                        if ($minutes >= 60) {
                                            $whole += 1;
                                            $minutes = 0;
                                        }
                                        $hours_display = sprintf("%d:%02d", $whole, $minutes);
                                    }
                                    
                                    // Exception type display
                                    $exception_type = $record['exception_type'] ?? '';
                                    if (empty($exception_type) && in_array($status, ['HPL', 'PL'])) {
                                        $exception_display = 'pl_adjustment';
                                    } elseif (empty($exception_type) && $status === 'WO') {
                                        $exception_display = 'week_off';
                                    } elseif (empty($exception_type)) {
                                        $exception_display = '--';
                                    } else {
                                        $exception_display = htmlspecialchars($exception_type);
                                    }
                                    
                                    // Check in/out display
                                    $check_in_display = ($record['check_in'] && $record['check_in'] !== '00:00:00') ? date('h:i A', strtotime($record['check_in'])) : '--';
                                    $check_out_display = ($record['check_out'] && $record['check_out'] !== '00:00:00') ? date('h:i A', strtotime($record['check_out'])) : '--';
                                ?>
                                    <tr style="border-bottom:1px solid var(--border-light);">
                                        <td style="padding:10px 14px;font-weight:600;font-size:13px;"><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                        <td style="padding:10px 14px;">
                                            <span class="status-badge-sm <?php echo $status_class; ?>" style="<?php echo $status_style; ?>">
                                                <?php echo htmlspecialchars($display_status); ?>
                                            </span>
                                        </td>
                                        <td style="padding:10px 14px;font-size:12px;font-weight:600;color:var(--text-muted);">
                                            <?php echo $exception_display; ?>
                                        </td>
                                        <td style="padding:10px 14px;font-size:13px;font-weight:600;color:var(--text-primary);">
                                            <?php echo $check_in_display; ?>
                                        </td>
                                        <td style="padding:10px 14px;font-size:13px;font-weight:600;color:var(--text-primary);">
                                            <?php echo $check_out_display; ?>
                                        </td>
                                        <td style="padding:10px 14px;font-weight:800;font-size:14px; <?php echo ($work_hours > 0) ? 'color:#22c55e;' : 'color:#94a3b8;'; ?>">
                                            <?php echo $hours_display; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; endif; ?>