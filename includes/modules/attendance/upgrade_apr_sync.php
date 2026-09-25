<?php
/**
 * UPGRADE, DC & PLCC PROCESS SYNC - VERSION 21
 * Logic: Integrated SQL Final Login calculation: login - (break - meeting)
 * FIXED: Only skip if status is already 'P' (Present) to prevent overwriting
 *        Otherwise update with correct status based on hours
 */

if (php_sapi_name() === 'cli') {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0); 
require_once __DIR__ . '/../config/bootstrap.php'; 

try {
    $pdo->exec("SET time_zone = '+05:30'"); 

    $date_start = isset($_GET['target_month']) ? date('Y-m-01', strtotime($_GET['target_month'])) : date('Y-m-d', strtotime('-1 days'));
    $date_end   = isset($_GET['target_month']) ? date('Y-m-t', strtotime($_GET['target_month'])) : date('Y-m-d');

    echo ">>> UPGRADE/DC/PLCC SYNC STARTED: $date_start to $date_end <<<\n";

    // 1. Fetch User Metadata - ONLY CSR with UPGRADE process
    $stmt_users = $pdo->query("
        SELECT person_id, role, position, process 
        FROM users 
        WHERE person_id IS NOT NULL 
        AND TRIM(UPPER(position)) LIKE '%CSR%'
        AND TRIM(UPPER(process)) LIKE '%UPGRADE%'
    ");
    $user_metadata = [];
    while ($u = $stmt_users->fetch(PDO::FETCH_ASSOC)) {
        $user_metadata[$u['person_id']] = $u;
    }
    
    echo "Processing " . count($user_metadata) . " CSR users with UPGRADE process.\n";

    $dialer_servers = [
        'CC'   => getUpgradeConnection(), 
        'DC'   => getDCConnection(),
        'Plcc' => getPlccConnection() 
    ];

    $merged = [];

    // 2. Fetch Data from servers
    foreach ($dialer_servers as $name => $db) {
        if (!$db) { 
            echo "SKIPPING $name: Connection Failed.\n"; 
            continue; 
        }
        
        echo "Fetching from $name... ";
        
        $dr = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sDate = ($dr == 'pgsql') ? "eventdate::date" : "DATE(eventdate)";
        
        // Updated SQL to capture Total Break and Meeting durations specifically
        $sql = "SELECT 
                    u.name as dialer_id, 
                    $sDate as log_date, 
                    SUM(loginduration) as t_login_secs, 
                    SUM(duration) as total_break_secs,
                    SUM(CASE WHEN UPPER(TRIM(pausecode)) IN ('TL BRIEFING', 'QA_FEEDBACK', 'QA FEEDBACK', 'TRAINING') THEN duration ELSE 0 END) as p_meeting_secs,
                    MIN(eventdate) as first_login,
                    MAX(eventdate) as last_activity
                FROM cr_pause_details_log 
                JOIN ct_user u ON u.id = userid
                WHERE eventdate >= '$date_start 00:00:00' AND eventdate <= '$date_end 23:59:59'
                GROUP BY u.name, log_date";
        
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        echo "Found " . count($rows) . " rows.\n";

        foreach ($rows as $r) { 
            $key = strtoupper(trim($r['dialer_id'])) . '_' . $r['log_date'];
            if (isset($merged[$key])) {
                $merged[$key]['t_login_secs']     += (int)$r['t_login_secs'];
                $merged[$key]['total_break_secs'] += (int)$r['total_break_secs'];
                $merged[$key]['p_meeting_secs']   += (int)$r['p_meeting_secs'];
                if (strtotime($r['first_login']) < strtotime($merged[$key]['first_login'])) $merged[$key]['first_login'] = $r['first_login'];
                if (strtotime($r['last_activity']) > strtotime($merged[$key]['last_activity'])) $merged[$key]['last_activity'] = $r['last_activity'];
            } else {
                $merged[$key] = $r; 
            }
        }
    }

    // Filter merged data to only include users in our metadata
    $filtered_merged = [];
    foreach ($merged as $key => $row) {
        $dialer_id = strtoupper(trim($row['dialer_id']));
        $p_id = is_numeric($dialer_id) ? "FNM" . $dialer_id : $dialer_id;
        
        if (isset($user_metadata[$p_id])) {
            $filtered_merged[$key] = $row;
        }
    }
    
    echo "Processing " . count($filtered_merged) . " records for CSR-UPGRADE users.\n";

    $pdo->beginTransaction();
    
    // FIXED: INSERT with ON DUPLICATE KEY UPDATE - Always update with new values
    $upsert = $pdo->prepare("
        INSERT INTO attendance (person_id, date, work, final_login, status, check_in, check_out) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            work = VALUES(work), 
            final_login = VALUES(final_login), 
            status = VALUES(status),
            check_in = VALUES(check_in),
            check_out = VALUES(check_out)
    ");

    $sync_count = 0;
    $skipped_count = 0;
    $updated_count = 0;
    $debug_records = [];
    
    foreach ($filtered_merged as $row) {
        $dialer_id = strtoupper(trim($row['dialer_id']));
        $p_id = is_numeric($dialer_id) ? "FNM" . $dialer_id : $dialer_id;

        if (isset($user_metadata[$p_id])) {
            $meta = $user_metadata[$p_id];
            
            // --- NEW LOGIC INTEGRATION ---
            $total_login   = (int)$row['t_login_secs'];
            $total_break   = (int)$row['total_break_secs'];
            $total_meeting = (int)$row['p_meeting_secs'];

            // Actual Break = Total Break - Meeting (Meeting time is considered productive)
            $actual_break_secs = $total_break - $total_meeting;
            
            // Final Login = Login Duration - Actual Break
            $final_login_secs = $total_login - $actual_break_secs;
            
            // For 'work' column (decimal hours) - Keep full precision
            $wrk_hrs = $final_login_secs / 3600;
            
            if ($wrk_hrs <= 0.1) continue;

            // All users here are CSR with UPGRADE process
            $required = 8.0;
            $half = 4.0;

            // Calculate status based on hours
            if ($wrk_hrs >= $required - 0.001) {
                $status = 'P';
            } elseif ($wrk_hrs >= $half - 0.001) {
                $status = 'HD';
            } else {
                $status = 'A';
            }

            // Store debug info for first 5 records
            if (count($debug_records) < 5) {
                $debug_records[] = [
                    'person_id' => $p_id,
                    'date' => $row['log_date'],
                    'total_login' => $total_login,
                    'total_break' => $total_break,
                    'total_meeting' => $total_meeting,
                    'actual_break' => $actual_break_secs,
                    'final_login_secs' => $final_login_secs,
                    'wrk_hrs' => $wrk_hrs,
                    'status' => $status,
                    'required' => $required,
                    'half' => $half
                ];
            }

            // Format Final Login as HH:MM:SS
            $hours = floor($final_login_secs / 3600);
            $minutes = floor(($final_login_secs % 3600) / 60);
            $seconds = $final_login_secs % 60;
            $f_in = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
            
            $c_in = date("H:i:s", strtotime($row['first_login']));
            $c_out = date("H:i:s", strtotime($row['last_activity']));

            // Check if record already exists
            $check_stmt = $pdo->prepare("SELECT status, work FROM attendance WHERE person_id = ? AND date = ?");
            $check_stmt->execute([$p_id, $row['log_date']]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // MODIFIED: Only skip if status is already 'P' (Present)
            // This prevents overwriting 'P' but allows updating HD -> P when hours >= 8
            if ($existing && $existing['status'] == 'P') {
                $skipped_count++;
                continue;
            }
            
            // If record exists with HD/A and new status is P, update it
            if ($existing && $existing['status'] != 'P' && $status == 'P') {
                $updated_count++;
            }
            
            $upsert->execute([$p_id, $row['log_date'], round($wrk_hrs, 2), $f_in, $status, $c_in, $c_out]);
            $sync_count++;
        }
    }
    $pdo->commit();
    
    // Show debug information
    echo "\n--- DEBUG INFO (First 5 records) ---\n";
    foreach ($debug_records as $debug) {
        echo "Person: {$debug['person_id']}, Date: {$debug['date']}\n";
        echo "  Login: {$debug['total_login']}s, Break: {$debug['total_break']}s, Meeting: {$debug['total_meeting']}s\n";
        echo "  Actual Break: {$debug['actual_break']}s, Final Login: {$debug['final_login_secs']}s\n";
        echo "  Work Hours: " . number_format($debug['wrk_hrs'], 4) . "hrs, Required: {$debug['required']}hrs, Status: {$debug['status']}\n";
        echo "  Comparison: " . ($debug['wrk_hrs'] >= $debug['required'] - 0.001 ? ">= 8.0 (P)" : "< 8.0") . "\n\n";
    }
    
    echo "Successfully synced $sync_count CSR-UPGRADE records.\n";
    echo "  - Updated HD/A to P: $updated_count records\n";
    echo "  - Skipped (already P): $skipped_count records\n";

} catch (Exception $e) { 
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo "FATAL ERROR: " . $e->getMessage() . "\n"; 
}