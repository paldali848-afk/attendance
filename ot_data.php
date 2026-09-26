<?php
// ============================================================
// ot_data.php � OT Report for IT Department
// Access: FNM10824 only
// ============================================================

// config.php already starts the session � do NOT call session_start() here.
require_once 'config.php';

// ===== ENSURE $user IS POPULATED =====
if (!isset($user) && function_exists('getCurrentUser')) {
    $user = getCurrentUser();
}
// Fallback: load from session if helper isn't available
if (!isset($user) && isset($_SESSION['user_id']) && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ===== AUTH CHECK =====
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$has_access = isset($user['person_id']) && trim($user['person_id']) === 'FNM10824';

// ===== CONFIG =====
define('OT_RATE_PER_HOUR', 60); // ? per OT hour � change to your rate

// ===== FILTERS =====
$selected_month = (int)date('m');
$selected_year  = (int)date('Y');

if (!empty($_GET['month'])) {
    if (strpos((string)$_GET['month'], '-') !== false) {
        // Format: "YYYY-MM"
        [$y, $m] = explode('-', $_GET['month']);
        $selected_year  = (int)$y;
        $selected_month = max(1, min(12, (int)$m));
    } else {
        // Format: "M" (+ optional year param)
        $selected_month = max(1, min(12, (int)$_GET['month']));
        if (!empty($_GET['year'])) {
            $selected_year = (int)$_GET['year'];
        }
    }
} elseif (!empty($_GET['year'])) {
    $selected_year = (int)$_GET['year'];
}

$month_label = date('F - Y', strtotime("$selected_year-$selected_month-01"));

// ===== ACCESS DENIED � render inside layout =====
if (!$has_access) {
    if (file_exists(__DIR__ . '/includes/header.php')) {
        include __DIR__ . '/includes/header.php';
    }
    echo "<div style='padding:40px;text-align:center;color:#dc2626;font-weight:600;font-size:16px;'>Access denied.</div>";
    if (file_exists(__DIR__ . '/includes/footer.php')) {
        include __DIR__ . '/includes/footer.php';
    }
    exit;
}

// ===== FETCH IT USERS =====
$stmt = $pdo->prepare("
    SELECT person_id, full_name, department
    FROM users
    WHERE UPPER(TRIM(department)) = 'IT'
      AND is_active = 1
    ORDER BY full_name ASC
");
$stmt->execute();
$it_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== BUILD PLACEHOLDERS + RUN ATTENDANCE/OT QUERY =====
$rows = [];
if (!empty($it_users)) {
    $person_ids   = array_column($it_users, 'person_id');
    $placeholders = implode(',', array_fill(0, count($person_ids), '?'));

  $stmt = $pdo->prepare("
    SELECT
        a.date,
        a.person_id,
        COALESCE(NULLIF(TRIM(a.name), ''), u.full_name, a.person_id) AS name,
        a.check_in,
        a.check_out,
        a.work,
        a.ot_approved,
        a.ot_hours   AS att_ot_hours,
        a.night_shift,
        o.id         AS ot_req_id,
        o.ot_hours   AS ot_hours,
        o.reason     AS activity,
        o.status     AS ot_status
    FROM attendance a
    LEFT JOIN users u
           ON u.person_id = a.person_id COLLATE utf8mb4_unicode_ci
    LEFT JOIN overtime_requests o
           ON o.person_id = a.person_id COLLATE utf8mb4_unicode_ci
          AND o.date      = a.date
          AND o.status    = 'approved'
    WHERE a.person_id IN ($placeholders)
      AND YEAR(a.date)  = ?
      AND MONTH(a.date) = ?
    HAVING o.id IS NOT NULL OR a.ot_approved = 1
    ORDER BY a.date ASC, name ASC
");

    $params = array_merge($person_ids, [$selected_year, $selected_month]);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== STATS =====
$total_ot_hours = 0;
foreach ($rows as $r) {
    $total_ot_hours += (float)($r['ot_hours'] ?? $r['att_ot_hours'] ?? 0);
}

// ===== MONTHLY OT SUMMARY (per employee) =====
$summary = [];
foreach ($rows as $r) {
    $pid  = $r['person_id'];
    $name = $r['name'] ?: $pid;

    if (!isset($summary[$pid])) {
        $summary[$pid] = [
            'name'     => $name,
            'ot_hours' => 0.0,
            'ot_rs'    => 0.0,
            'night'    => 0,
        ];
    }

    $ot_h = (float)($r['ot_hours'] ?? $r['att_ot_hours'] ?? 0);
    $summary[$pid]['ot_hours'] += $ot_h;

    // Night shift detection: use night_shift column from attendance table
    // (out time between 12:00 AM and 1:00 AM)
    if (!empty($r['night_shift']) && (int)$r['night_shift'] === 1) {
        $summary[$pid]['night']++;
    }
}
// Compute ? and sort alphabetically
foreach ($summary as $pid => &$s) {
    $s['ot_rs'] = $s['ot_hours'] * OT_RATE_PER_HOUR;
}
unset($s);

uasort($summary, fn($a, $b) => strcasecmp($a['name'], $b['name']));

// Grand totals
$grand_ot_hours = 0;
$grand_ot_rs    = 0;
$grand_night    = 0;
foreach ($summary as $s) {
    $grand_ot_hours += $s['ot_hours'];
    $grand_ot_rs    += $s['ot_rs'];
    $grand_night    += $s['night'];
}

// ===== HELPERS =====
function formatTimes(array $r): array {
    $in  = ($r['check_in']  && $r['check_in']  !== '00:00:00') ? date('h:i A', strtotime($r['check_in']))  : '-';
    $out = ($r['check_out'] && $r['check_out'] !== '00:00:00') ? date('h:i A', strtotime($r['check_out'])) : '-';
    $ttl = (float)($r['work'] ?? 0) > 0 ? number_format((float)$r['work'], 2) . 'h' : '-';

    // Night shift: based on night_shift column (out time 12:00 AM - 1:00 AM)
    $night = (!empty($r['night_shift']) && (int)$r['night_shift'] === 1) ? 'YES' : '-';
    
    return [$in, $out, $ttl, $night];
}
function calcOtWindow(array $r): array {
    $ot_start = ($r['check_in']  && $r['check_in']  !== '00:00:00') ? date('h:i A', strtotime($r['check_in']))  : '-';
    $ot_end   = ($r['check_out'] && $r['check_out'] !== '00:00:00') ? date('h:i A', strtotime($r['check_out'])) : '-';
    $ot_hours = (float)($r['ot_hours'] ?? $r['att_ot_hours'] ?? 0);
    $diff     = $ot_hours > 0 ? number_format($ot_hours, 2) . 'h' : '-';
    return [$ot_start, $ot_end, $diff];
}

// ===== EXCEL EXPORT =====
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    while (ob_get_level() > 0) ob_end_clean();

    $filename = "IT_OT_Report_" . date('M_Y', strtotime("$selected_year-$selected_month-01")) . ".xls";

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    ?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">

    <!-- Sheet 1: OT Details -->
    <Worksheet ss:Name="OT Details">
        <Table>
            <Row><Cell ss:MergeAcross="11"><Data ss:Type="String">IT Exception [Month - <?php echo date('Y', strtotime("$selected_year-$selected_month-01")); ?>]</Data></Cell></Row>
            <Row>
                <?php foreach (['Date','EMPLOYEE ID','EMPLOYEE NAME','IN TIME','OUT TIME','TOTAL TIME','ACTIVITY','OT START','OT END','DIFF','NIGHT','CENTER'] as $h): ?>
                    <Cell><Data ss:Type="String"><?php echo htmlspecialchars($h); ?></Data></Cell>
                <?php endforeach; ?>
            </Row>
            <?php foreach ($rows as $row):
                [$in_time, $out_time, $total_time, $night] = formatTimes($row);
                [$ot_start, $ot_end, $diff]                = calcOtWindow($row);
            ?>
            <Row>
                <Cell><Data ss:Type="String"><?php echo date('d-M-Y', strtotime($row['date'])); ?></Data></Cell>
                <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['person_id']); ?></Data></Cell>
                <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['name']); ?></Data></Cell>
                <Cell><Data ss:Type="String"><?php echo htmlspecialchars($in_time); ?></Data></Cell>
                <Cell><Data ss:Type="String"><?php echo htmlspecialchars($out_time); ?></Data></Cell>
                <Cell><Data ss:Type="String"><?php echo htmlspecialchars($total_time); ?></Data></Cell>
                <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['activity'] ?? ''); ?></Data></Cell>
                <Cell><Data ss:Type="String"><?php echo htmlspecialchars($ot_start); ?></Data></Cell>
                <Cell><Data ss:Type="String"><?php echo htmlspecialchars($ot_end); ?></Data></Cell>
                <Cell><Data ss:Type="String"><?php echo htmlspecialchars($diff); ?></Data></Cell>
                <Cell><Data ss:Type="String"><?php echo htmlspecialchars($night); ?></Data></Cell>
                <Cell><Data ss:Type="String">IT</Data></Cell>
            </Row>
            <?php endforeach; ?>
            <Row>
                <Cell><Data ss:Type="String">TOTAL</Data></Cell>
                <?php for ($i=0;$i<8;$i++): ?><Cell><Data ss:Type="String"></Data></Cell><?php endfor; ?>
                <Cell><Data ss:Type="String"><?php echo number_format($total_ot_hours, 2) . 'h'; ?></Data></Cell>
                <Cell><Data ss:Type="String"></Data></Cell>
                <Cell><Data ss:Type="String"></Data></Cell>
            </Row>
        </Table>
    </Worksheet>

    <!-- Sheet 2: Monthly Summary -->
    <Worksheet ss:Name="Monthly Summary">
        <Table>
            <Row><Cell ss:MergeAcross="4"><Data ss:Type="String">Monthly OT Summary [Month - <?php echo date('F Y', strtotime("$selected_year-$selected_month-01")); ?>]</Data></Cell></Row>
            <Row>
                <?php foreach (["FNM id's", "Name", "OT in Hrs", "OT in Rs", "Night"] as $h): ?>
                    <Cell><Data ss:Type="String"><?php echo htmlspecialchars($h); ?></Data></Cell>
                <?php endforeach; ?>
            </Row>
            <?php foreach ($summary as $pid => $s): ?>
            <Row>
                <Cell><Data ss:Type="String"><?php echo htmlspecialchars($pid); ?></Data></Cell>
                <Cell><Data ss:Type="String"><?php echo htmlspecialchars($s['name']); ?></Data></Cell>
                <Cell><Data ss:Type="Number"><?php echo round($s['ot_hours'], 2); ?></Data></Cell>
                <Cell><Data ss:Type="Number"><?php echo round($s['ot_rs'], 2); ?></Data></Cell>
                <Cell><Data ss:Type="Number"><?php echo (int)$s['night']; ?></Data></Cell>
            </Row>
            <?php endforeach; ?>
            <Row>
                <Cell><Data ss:Type="String">TOTAL</Data></Cell>
                <Cell><Data ss:Type="String"></Data></Cell>
                <Cell><Data ss:Type="Number"><?php echo round($grand_ot_hours, 2); ?></Data></Cell>
                <Cell><Data ss:Type="Number"><?php echo round($grand_ot_rs, 2); ?></Data></Cell>
                <Cell><Data ss:Type="Number"><?php echo (int)$grand_night; ?></Data></Cell>
            </Row>
        </Table>
    </Worksheet>
</Workbook>
    <?php
    exit;
}

// ===== INCLUDE HEADER (renders sidebar + top bar) =====
include __DIR__ . '/includes/header.php';
?>

<!-- ========================================== -->
<!-- PAGE CONTENT (header.php already opened <html><body>) -->
<!-- ========================================== -->
<div style="max-width:1500px;margin:0 auto;padding:0 0 24px 0;">

    <h1 style="font-size:22px;font-weight:800;margin:0 0 4px;color:#0f172a;">OT Details � IT Department</h1>
    <div style="color:#64748b;font-size:13px;margin-bottom:20px;">
        Overtime report for <?php echo $month_label; ?>
    </div>

    <!-- ===== FILTER TOOLBAR ===== -->
    <form method="get" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;background:#fff;padding:14px 18px;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:18px;">
        <label style="font-size:13px;font-weight:600;color:#334155;">Month:</label>
        <select name="month" style="padding:7px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;outline:none;">
            <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?php echo $m;?>" <?php echo $m==$selected_month?'selected':'';?>>
                    <?php echo date('F', mktime(0,0,0,$m,1));?>
                </option>
            <?php endfor; ?>
        </select>
        <label style="font-size:13px;font-weight:600;color:#334155;">Year:</label>
        <select name="year" style="padding:7px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;outline:none;">
            <?php for ($y=date('Y')-2;$y<=date('Y')+1;$y++): ?>
                <option value="<?php echo $y;?>" <?php echo $y==$selected_year?'selected':'';?>><?php echo $y;?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" style="padding:8px 16px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;background:#2563eb;color:#fff;border:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fas fa-search"></i> Filter
        </button>

        <a href="?month=<?php echo $selected_month;?>&year=<?php echo $selected_year;?>&export=excel"
           style="padding:8px 16px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;background:#16a34a;color:#fff;margin-left:auto;display:inline-flex;align-items:center;gap:6px;">
            <i class="fas fa-file-excel"></i> Export Excel (2 Sheets)
        </a>
    </form>

    <!-- ===== STAT CARD ===== -->
    <div style="background:#fff;padding:14px 18px;border-radius:12px;display:inline-block;box-shadow:0 1px 3px rgba(0,0,0,.06);border-left:4px solid #d97706;margin-bottom:18px;min-width:220px;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#64748b;font-weight:700;margin-bottom:4px;">Total OT Hours</div>
        <div style="font-size:22px;font-weight:800;color:#0f172a;"><?php echo number_format($total_ot_hours,2);?>h</div>
    </div>

    <!-- ===== MONTHLY SUMMARY ===== -->
    <div style="font-size:15px;font-weight:800;color:#1e293b;margin:24px 0 10px;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-users"></i> Monthly OT Summary
    </div>
    <div style="background:#fff;border-radius:12px;overflow:auto;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:24px;">
        <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
            <thead>
                <tr style="background:#1e293b;color:#fff;">
                    <th style="padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">FNM id's</th>
                    <th style="padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">Name</th>
                    <th style="padding:10px 8px;text-align:right;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">OT in Hrs</th>
                    <th style="padding:10px 8px;text-align:right;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">OT in Rs</th>
                    <th style="padding:10px 8px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">Night</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($summary)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:60px 20px;color:#94a3b8;">
                        <i class="fas fa-clock" style="font-size:36px;display:block;margin-bottom:12px;opacity:.5;"></i>
                        No OT data for <?php echo $month_label; ?>.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($summary as $pid => $s): ?>
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <td style="padding:8px;"><strong><?php echo htmlspecialchars($pid); ?></strong></td>
                            <td style="padding:8px;"><?php echo htmlspecialchars($s['name']); ?></td>
                            <td style="padding:8px;text-align:right;"><?php echo number_format($s['ot_hours'], 2); ?></td>
                            <td style="padding:8px;text-align:right;"><?php echo number_format($s['ot_rs'], 2); ?></td>
                            <td style="padding:8px;text-align:center;">
                                <?php if ($s['night'] > 0): ?>
                                    <span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:10.5px;font-weight:800;background:#ede9fe;color:#6d28d9;"><?php echo $s['night']; ?></span>
                                <?php else: ?>
                                    <span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:10.5px;font-weight:800;background:#dcfce7;color:#15803d;">0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($summary)): ?>
            <tfoot>
                <tr style="background:#f1f5f9;font-weight:800;border-top:2px solid #cbd5e1;">
                    <td colspan="2" style="padding:10px 8px;">TOTAL</td>
                    <td style="padding:10px 8px;text-align:right;"><?php echo number_format($grand_ot_hours, 2); ?></td>
                    <td style="padding:10px 8px;text-align:right;"><?php echo number_format($grand_ot_rs, 2); ?></td>
                    <td style="padding:10px 8px;text-align:center;"><?php echo $grand_night; ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <!-- ===== DETAILED TABLE ===== -->
    <div style="font-size:15px;font-weight:800;color:#1e293b;margin:24px 0 10px;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-list"></i> Detailed OT Records
    </div>
    <div style="background:#fff;border-radius:12px;overflow:auto;box-shadow:0 1px 3px rgba(0,0,0,.06);max-height:72vh;">
        <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
            <thead>
                <tr style="background:#1e293b;color:#fff;">
                    <th style="position:sticky;top:0;background:#1e293b;color:#fff;padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">Date</th>
                    <th style="position:sticky;top:0;background:#1e293b;color:#fff;padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">Employee ID</th>
                    <th style="position:sticky;top:0;background:#1e293b;color:#fff;padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">Employee Name</th>
                    <th style="position:sticky;top:0;background:#1e293b;color:#fff;padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">In Time</th>
                    <th style="position:sticky;top:0;background:#1e293b;color:#fff;padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">Out Time</th>
                    <th style="position:sticky;top:0;background:#1e293b;color:#fff;padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">Total Time</th>
                    <th style="position:sticky;top:0;background:#1e293b;color:#fff;padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">Activity</th>
                    <th style="position:sticky;top:0;background:#1e293b;color:#fff;padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">OT Start</th>
                    <th style="position:sticky;top:0;background:#1e293b;color:#fff;padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">OT End</th>
                    <th style="position:sticky;top:0;background:#1e293b;color:#fff;padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">Diff</th>
                    <th style="position:sticky;top:0;background:#1e293b;color:#fff;padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">Night</th>
                    <th style="position:sticky;top:0;background:#1e293b;color:#fff;padding:10px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;">Center</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="12" style="text-align:center;padding:60px 20px;color:#94a3b8;">
                        <i class="fas fa-clock" style="font-size:36px;display:block;margin-bottom:12px;opacity:.5;"></i>
                        No OT records found for <?php echo $month_label;?>.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                            [$in_time,$out_time,$total_time,$night] = formatTimes($r);
                            [$ot_start,$ot_end,$diff]                = calcOtWindow($r);
                        ?>
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <td style="padding:8px;"><?php echo date('d-M-Y', strtotime($r['date']));?></td>
                            <td style="padding:8px;"><?php echo htmlspecialchars($r['person_id']);?></td>
                            <td style="padding:8px;"><?php echo htmlspecialchars($r['name']);?></td>
                            <td style="padding:8px;"><?php echo $in_time;?></td>
                            <td style="padding:8px;"><?php echo $out_time;?></td>
                            <td style="padding:8px;"><?php echo $total_time;?></td>
                            <td style="padding:8px;" title="<?php echo htmlspecialchars($r['activity'] ?? '');?>">
                                <?php echo htmlspecialchars(mb_strimwidth((string)($r['activity'] ?? ''),0,40,'�'));?>
                            </td>
                            <td style="padding:8px;"><?php echo $ot_start;?></td>
                            <td style="padding:8px;"><?php echo $ot_end;?></td>
                            <td style="padding:8px;"><span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:10.5px;font-weight:800;background:#dbeafe;color:#2563eb;"><?php echo $diff;?></span></td>
                            <td style="padding:8px;"><?php echo $night==='YES'?'<span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:10.5px;font-weight:800;background:#ede9fe;color:#6d28d9;">YES</span>':'-';?></td>
                            <td style="padding:8px;">IT</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($rows)): ?>
            <tfoot>
                <tr style="background:#f1f5f9;font-weight:800;border-top:2px solid #cbd5e1;">
                    <td colspan="9" style="padding:10px 8px;">TOTAL</td>
                    <td style="padding:10px 8px;"><?php echo number_format($total_ot_hours,2);?>h</td>
                    <td colspan="2" style="padding:10px 8px;"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

