<?php
require_once 'config.php';

if (!isLoggedIn() || !hasRole('agent')) {
    redirect('index.php');
}

$user = getCurrentUser();
$today = date('Y-m-d');
$current_time = date('H:i:s');
$message = '';
$error = '';

// Get today's attendance
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE person_id = ? AND date = ?");
$stmt->execute([$user['person_id'], $today]);
$attendance = $stmt->fetch();

// Get user's shift
$stmt = $pdo->prepare("SELECT s.* FROM shifts s WHERE s.id = (SELECT shift_id FROM users WHERE id = ?)");
$stmt->execute([$user['id']]);
$shift = $stmt->fetch();

if (!$shift) {
    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE shift_name = 'Morning Shift'");
    $stmt->execute();
    $shift = $stmt->fetch();
}

// Check-in logic
if (isset($_POST['check_in'])) {
    if ($attendance) {
        $error = 'You have already checked in today.';
    } else {
        $check_in_time = date('H:i:s');
        $week = getWeekNumber($today);
        $day = getDayOfWeek($today);
        $status = 'Present';
        $late_minutes = 0;
        
        // Check if late
        $shift_start = $shift['start_time'];
        $late_threshold = date('H:i:s', strtotime($shift_start . ' + ' . $shift['late_threshold'] . ' minutes'));
        
        if ($check_in_time > $late_threshold) {
            $late_minutes = getLateMinutes($check_in_time, $shift_start);
            $status = 'Late';
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO attendance 
                (person_id, name, department, position, gender, date, week, timetable, 
                 check_in, late, status, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $user['person_id'],
                $user['full_name'],
                $user['department'],
                $user['position'],
                $user['gender'],
                $today,
                $week,
                $shift['shift_name'],
                $check_in_time,
                $late_minutes,
                $status,
                $user['id']
            ]);
            
            if ($status == 'Late') {
                $message = 'You are late. Please submit a late exception request.';
                // You can redirect to late request page
            } else {
                $message = 'Check-in successful! You are on time.';
            }
            redirect('attendance.php');
        } catch (PDOException $e) {
            $error = 'Failed to check in. Please try again.';
        }
    }
}

// Check-out logic
if (isset($_POST['check_out'])) {
    if (!$attendance) {
        $error = 'You need to check in first.';
    } elseif ($attendance['check_out']) {
        $error = 'You have already checked out today.';
    } else {
        $check_out_time = date('H:i:s');
        $shift_end = $shift['end_time'];
        
        // Calculate working hours
        $working_hours = calculateWorkingHours($attendance['check_in'], $check_out_time);
        $ot_hours = calculateOT($working_hours, $shift['working_hours']);
        $early_minutes = getEarlyMinutes($check_out_time, $shift_end);
        
        // Update attendance
        try {
            $stmt = $pdo->prepare("UPDATE attendance SET 
                check_out = ?, 
                work = ?, 
                ot = ?, 
                attended = ?,
                early = ?,
                status = CASE 
                    WHEN ? > 0 THEN 'Half Day'
                    ELSE status 
                END
                WHERE id = ?");
            
            $stmt->execute([
                $check_out_time,
                $working_hours,
                $ot_hours,
                $working_hours,
                $early_minutes,
                $early_minutes,
                $attendance['id']
            ]);
            
            if ($working_hours < $shift['working_hours']) {
                $message = 'You are leaving early. Please submit an early leave request.';
            } else {
                $message = 'Check-out successful! You have completed your working hours.';
            }
            redirect('attendance.php');
        } catch (PDOException $e) {
            $error = 'Failed to check out. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 24px; }
        .header-actions a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .attendance-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .status-item {
            text-align: center;
        }
        .status-item .label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-item .value {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-top: 5px;
        }
        .status-item .value.late { color: #e53e3e; }
        .status-item .value.present { color: #38a169; }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #48bb78;
            color: white;
        }
        .btn-success:hover {
            background: #38a169;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #fc8181;
            color: white;
        }
        .btn-danger:hover {
            background: #fc5a5a;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #333;
        }
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .message-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        .message-error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
        .shift-info {
            background: #f0f4ff;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #667eea;
        }
        .shift-info p {
            margin: 5px 0;
            color: #555;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .table-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e2e8f0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        .status-Present { background: #c6f6d5; color: #22543d; }
        .status-Late { background: #feebc8; color: #7b3f00; }
        .status-Absent { background: #fed7d7; color: #742a2a; }
        .status-Half { background: #e9d8fd; color: #44337a; }
        .status-Holiday { background: #bee3f8; color: #2a69ac; }
        .status-Weekly { background: #e2e8f0; color: #2d3748; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📝 Attendance</h1>
        <div class="header-actions">
            <span>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
            <a href="dashboard.php">← Dashboard</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message message-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message message-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Today's Attendance - <?php echo date('F j, Y'); ?></h2>
            
            <div class="attendance-status">
                <div class="status-item">
                    <div class="label">Status</div>
                    <div class="value <?php echo strtolower($attendance['status'] ?? ''); ?>">
                        <?php echo $attendance ? $attendance['status'] : 'Not Checked In'; ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="label">Check In</div>
                    <div class="value"><?php echo $attendance && $attendance['check_in'] ? date('h:i A', strtotime($attendance['check_in'])) : '--:--'; ?></div>
                </div>
                <div class="status-item">
                    <div class="label">Check Out</div>
                    <div class="value"><?php echo $attendance && $attendance['check_out'] ? date('h:i A', strtotime($attendance['check_out'])) : '--:--'; ?></div>
                </div>
                <div class="status-item">
                    <div class="label">Working Hours</div>
                    <div class="value"><?php echo $attendance && $attendance['work'] ? $attendance['work'] . ' hrs' : '0 hrs'; ?></div>
                </div>
                <div class="status-item">
                    <div class="label">OT</div>
                    <div class="value"><?php echo $attendance && $attendance['ot'] ? $attendance['ot'] . ' hrs' : '0 hrs'; ?></div>
                </div>
                <div class="status-item">
                    <div class="label">Late (mins)</div>
                    <div class="value late"><?php echo $attendance && $attendance['late'] ? $attendance['late'] : '0'; ?></div>
                </div>
            </div>
            
            <div class="shift-info">
                <p><strong>Employee:</strong> <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo $user['person_id']; ?>)</p>
                <p><strong>Department:</strong> <?php echo htmlspecialchars($user['department']); ?></p>
                <p><strong>Position:</strong> <?php echo htmlspecialchars($user['position']); ?></p>
                <p><strong>Shift:</strong> <?php echo htmlspecialchars($shift['shift_name']); ?> (<?php echo date('h:i A', strtotime($shift['start_time'])); ?> - <?php echo date('h:i A', strtotime($shift['end_time'])); ?>)</p>
                <p><strong>Working Hours:</strong> <?php echo $shift['working_hours']; ?> hours</p>
            </div>
            
            <div class="actions">
                <?php if (!$attendance): ?>
                    <form method="POST" action="">
                        <button type="submit" name="check_in" class="btn btn-primary">🟢 Check In</button>
                    </form>
                <?php elseif (!$attendance['check_out']): ?>
                    <form method="POST" action="">
                        <button type="submit" name="check_out" class="btn btn-success">🔴 Check Out</button>
                    </form>
                <?php else: ?>
                    <div class="btn btn-secondary" style="cursor: default;">✅ Completed for Today</div>
                <?php endif; ?>
            </div>
            
            <?php if ($attendance && $attendance['status'] == 'Late'): ?>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="late_request.php" class="btn btn-danger">⏰ Request Late Exception</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Attendance History -->
        <div class="card">
            <h2>Recent Attendance History</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Week</th>
                            <th>Timetable</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Work</th>
                            <th>OT</th>
                            <th>Late</th>
                            <th>Early</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE person_id = ? ORDER BY date DESC LIMIT 10");
                        $stmt->execute([$user['person_id']]);
                        $history = $stmt->fetchAll();
                        
                        foreach ($history as $record):
                        ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                            <td>Week <?php echo $record['week']; ?></td>
                            <td><?php echo htmlspecialchars($record['timetable']); ?></td>
                            <td><?php echo $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : '-'; ?></td>
                            <td><?php echo $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '-'; ?></td>
                            <td><?php echo $record['work'] ? $record['work'] . 'h' : '-'; ?></td>
                            <td><?php echo $record['ot'] ? $record['ot'] . 'h' : '-'; ?></td>
                            <td><?php echo $record['late'] ? $record['late'] . 'm' : '-'; ?></td>
                            <td><?php echo $record['early'] ? $record['early'] . 'm' : '-'; ?></td>
                            <td><span class="status-badge status-<?php echo $record['status']; ?>"><?php echo $record['status']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</body>
</html>