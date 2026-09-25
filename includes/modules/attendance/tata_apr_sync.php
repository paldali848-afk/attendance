<?php
/**
 * TATA & BT CSR DIALER ATTENDANCE SYNC - VERSION 2.1
 * Uses getTataConnection() from bootstrap.php
 * Fetches TATA dialer data and updates attendance for TATA & BT CSR users
 */

if (php_sapi_name() === 'cli') {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
ini_set('memory_limit', '1G');
require_once __DIR__ . '/../config/bootstrap.php';

try {
    $pdo->exec("SET time_zone = '+05:30'");

    // Date range
    if (isset($_GET['target_month'])) {
        $date_start = date('Y-m-01', strtotime($_GET['target_month']));
        $date_end   = date('Y-m-t', strtotime($_GET['target_month']));
    } else {
        $date_start = date('Y-m-d', strtotime('-1 days'));
        $date_end   = date('Y-m-d');
    }

    echo ">>> TATA & BT CSR SYNC STARTED: $date_start to $date_end <<<\n";
    echo ">>> Using TATA Dialer Connection (getTataConnection) <<<\n";

    // ============================================================
    // STEP 1: Get TATA & BT CSR Users from attendance DB
    // ============================================================
    $stmt_users = $pdo->query("
        SELECT person_id, TRIM(person_id) as dialer_id
        FROM users 
        WHERE person_id IS NOT NULL 
        AND TRIM(UPPER(position)) LIKE '%CSR%'
        AND TRIM(process) = 'TATA & BT'
    ");
    
    $user_map = [];
    $user_names = [];
    while ($u = $stmt_users->fetch(PDO::FETCH_ASSOC)) {
        $person_id = trim($u['person_id']);
        $dialer_id = trim($u['dialer_id']);
        $user_map[$dialer_id] = $person_id;
        $user_names[] = $dialer_id;
    }
    
    echo "Found " . count($user_map) . " TATA/BT CSR users in attendance DB.\n";
    
    if (empty($user_map)) {
        echo "No TATA/BT CSR users found. Exiting.\n";
        exit(0);
    }

    // Show first 10 user names
    echo "First 10 user names: " . implode(', ', array_slice($user_names, 0, 10)) . "\n\n";

    // ============================================================
    // STEP 2: Connect to TATA Dialer
    // ============================================================
    $db = getTataConnection();
    
    if (!$db) {
        echo "ERROR: Could not connect to TATA dialer!\n";
        exit(1);
    }
    
    echo "Connected to TATA dialer successfully.\n";

    // Detect database type
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $is_pgsql = ($driver == 'pgsql');
    echo "Database type: $driver\n";

    // Find the correct column for dialer ID
    if ($is_pgsql) {
        $columns = $db->query("
            SELECT column_name FROM information_schema.columns 
            WHERE table_name = 'ct_user'
        ")->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $columns = $db->query("SHOW COLUMNS FROM ct_user")->fetchAll(PDO::FETCH_COLUMN);
    }
    
    $dialer_id_column = null;
    $possible_columns = ['user', 'username', 'name', 'login', 'userid', 'agent_id', 'extension'];
    
    foreach ($possible_columns as $col) {
        if (in_array($col, $columns)) {
            $dialer_id_column = $col;
            break;
        }
    }
    
    if ($dialer_id_column === null) {
        $dialer_id_column = 'name';
    }
    
    echo "Using column: '$dialer_id_column'\n\n";

    // ============================================================
    // STEP 3: Fetch TATA Dialer Data
    // ============================================================
    
    if ($is_pgsql) {
        // PostgreSQL Queries
        $sql_activity = "SELECT 
                    u.$dialer_id_column as dialer_id,
                    u.$dialer_id_column as dialer_name,
                    l.userid,
                    l.eventdate::date as log_date,
                    SUM(COALESCE(l.idleduration, 0)) as idel,
                    SUM(COALESCE(l.previewidleduration, 0)) as preview_idle,
                    SUM(COALESCE(l.manualidleduration, 0)) as manual_idle,
                    SUM(COALESCE(l.followupidleduration, 0)) as followup_idle,
                    SUM(COALESCE(l.acwduration, 0)) as total_acw,
                    SUM(COALESCE(l.interactionduration, 0)) as total_talk,
                    SUM(COALESCE(l.ringingduration, 0)) as ring_duration,
                    SUM(COALESCE(l.holdduration, 0)) as hold,
                    SUM(COALESCE(l.auxduration, 0)) as aux_duration,
                    SUM(COALESCE(l.setupduration, 0)) as setup,
                    COUNT(DISTINCT l.accountcode) as total_calls
                FROM cr_user_log l 
                JOIN ct_user u ON u.id = l.userid
                WHERE l.eventdate >= '$date_start 00:00:00' 
                  AND l.eventdate <= '$date_end 23:59:59'
                GROUP BY u.$dialer_id_column, l.userid, log_date";

        $sql_pause = "SELECT 
                    p.userid,
                    p.eventdate::date as log_date,
                    SUM(COALESCE(p.loginduration, 0)) as total_login,
                    SUM(COALESCE(p.duration, 0)) as total_break,
                    MIN(p.eventdate) as first_in,
                    MAX(p.eventdate) as last_out,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) IN ('TL BRIEFING', 'BREIFING') 
                        THEN COALESCE(p.duration, 0) ELSE 0 END) as tl_briefing,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) IN ('QA_FEEDBACK', 'QA FEEDBACK') 
                        THEN COALESCE(p.duration, 0) ELSE 0 END) as qa_feedback,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) = 'TRAINING' 
                        THEN COALESCE(p.duration, 0) ELSE 0 END) as training,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) = 'AUX' 
                        THEN COALESCE(p.duration, 0) ELSE 0 END) as aux_pause,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) = 'LUNCH' 
                        THEN COALESCE(p.duration, 0) ELSE 0 END) as lunch,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) IN ('TEA_BREAK', 'TEA BREAK') 
                        THEN COALESCE(p.duration, 0) ELSE 0 END) as tea_break,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) = 'BIO' 
                        THEN COALESCE(p.duration, 0) ELSE 0 END) as bio,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) = 'IT' 
                        THEN COALESCE(p.duration, 0) ELSE 0 END) as it_pause,
                    u.$dialer_id_column as dialer_id
                FROM cr_pause_details_log p
                JOIN ct_user u ON u.id = p.userid
                WHERE p.eventdate >= '$date_start 00:00:00' 
                  AND p.eventdate <= '$date_end 23:59:59'
                GROUP BY p.userid, log_date, u.$dialer_id_column";
    } else {
        // MySQL Queries
        $sql_activity = "SELECT 
                    u.$dialer_id_column as dialer_id,
                    u.$dialer_id_column as dialer_name,
                    l.userid,
                    DATE(l.eventdate) as log_date,
                    SUM(IFNULL(l.idleduration, 0)) as idel,
                    SUM(IFNULL(l.previewidleduration, 0)) as preview_idle,
                    SUM(IFNULL(l.manualidleduration, 0)) as manual_idle,
                    SUM(IFNULL(l.followupidleduration, 0)) as followup_idle,
                    SUM(IFNULL(l.acwduration, 0)) as total_acw,
                    SUM(IFNULL(l.interactionduration, 0)) as total_talk,
                    SUM(IFNULL(l.ringingduration, 0)) as ring_duration,
                    SUM(IFNULL(l.holdduration, 0)) as hold,
                    SUM(IFNULL(l.auxduration, 0)) as aux_duration,
                    SUM(IFNULL(l.setupduration, 0)) as setup,
                    COUNT(DISTINCT l.accountcode) as total_calls
                FROM cr_user_log l 
                JOIN ct_user u ON u.id = l.userid
                WHERE l.eventdate >= '$date_start 00:00:00' 
                  AND l.eventdate <= '$date_end 23:59:59'
                GROUP BY u.$dialer_id_column, l.userid, DATE(l.eventdate)";

        $sql_pause = "SELECT 
                    p.userid,
                    DATE(p.eventdate) as log_date,
                    SUM(IFNULL(p.loginduration, 0)) as total_login,
                    SUM(IFNULL(p.duration, 0)) as total_break,
                    MIN(p.eventdate) as first_in,
                    MAX(p.eventdate) as last_out,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) IN ('TL BRIEFING', 'BREIFING') 
                        THEN IFNULL(p.duration, 0) ELSE 0 END) as tl_briefing,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) IN ('QA_FEEDBACK', 'QA FEEDBACK') 
                        THEN IFNULL(p.duration, 0) ELSE 0 END) as qa_feedback,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) = 'TRAINING' 
                        THEN IFNULL(p.duration, 0) ELSE 0 END) as training,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) = 'AUX' 
                        THEN IFNULL(p.duration, 0) ELSE 0 END) as aux_pause,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) = 'LUNCH' 
                        THEN IFNULL(p.duration, 0) ELSE 0 END) as lunch,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) IN ('TEA_BREAK', 'TEA BREAK') 
                        THEN IFNULL(p.duration, 0) ELSE 0 END) as tea_break,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) = 'BIO' 
                        THEN IFNULL(p.duration, 0) ELSE 0 END) as bio,
                    SUM(CASE WHEN UPPER(TRIM(p.pausecode)) = 'IT' 
                        THEN IFNULL(p.duration, 0) ELSE 0 END) as it_pause,
                    u.$dialer_id_column as dialer_id
                FROM cr_pause_details_log p
                JOIN ct_user u ON u.id = p.userid
                WHERE p.eventdate >= '$date_start 00:00:00' 
                  AND p.eventdate <= '$date_end 23:59:59'
                GROUP BY p.userid, DATE(p.eventdate), u.$dialer_id_column";
    }

    // Execute queries
    echo "Fetching activity data...\n";
    $rows = $db->query($sql_activity)->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Fetching pause data...\n";
    $pause_rows = $db->query($sql_pause)->fetchAll(PDO::FETCH_ASSOC);
    
    // Map pause data
    $pause_map = [];
    foreach($pause_rows as $p) { 
        $pause_map[$p['userid'] . '_' . $p['log_date']] = $p; 
    }

    // Merge Data and filter for TATA & BT CSR users only
    $filtered_data = [];
    $total_count = 0;
    $matched_count = 0;
    $sample_dialer_names = [];
    
    foreach ($rows as $r) {
        $key = $r['userid'] . '_' . $r['log_date'];
        $p_info = $pause_map[$key] ?? [
            'total_login' => 0,
            'total_break' => 0,
            'first_in' => '00:00:00',
            'last_out' => '00:00:00',
            'tl_briefing' => 0,
            'qa_feedback' => 0,
            'training' => 0,
            'aux_pause' => 0,
            'lunch' => 0,
            'tea_break' => 0,
            'bio' => 0,
            'it_pause' => 0,
            'dialer_id' => $r['dialer_id']
        ];
        
        // Use dialer_id from pause data if available, otherwise from activity
        $dialer_id = trim($p_info['dialer_id'] ?? $r['dialer_id']);
        
        $total_count++;
        
        // Collect sample dialer names
        if (!in_array($dialer_id, $sample_dialer_names) && count($sample_dialer_names) < 20) {
            $sample_dialer_names[] = $dialer_id;
        }
        
        // Only keep records that match TATA & BT CSR users
        if (isset($user_map[$dialer_id])) {
            $final_key = strtoupper($dialer_id) . '_' . $r['log_date'];
            $filtered_data[$final_key] = array_merge($r, $p_info, ['dialer_name' => $dialer_id]);
            $matched_count++;
        }
    }
    
    echo "Found $total_count total records in TATA dialer\n";
    echo "Found $matched_count records matching TATA/BT CSR users\n";
    echo "Sample dialer IDs from TATA dialer: " . implode(', ', array_slice($sample_dialer_names, 0, 10)) . "\n\n";

    if (empty($filtered_data)) {
        echo "No matching records found for TATA/BT CSR users.\n";
        echo "\nPossible reasons:\n";
        echo "1. TATA/BT CSR users don't have dialer credentials\n";
        echo "2. Person_id doesn't match dialer ID format\n";
        echo "3. No data for the selected date range\n";
        exit(0);
    }

    // ============================================================
    // STEP 4: Update Attendance Table
    // ============================================================
    echo "Updating attendance for " . count($filtered_data) . " records...\n";

    $pdo->beginTransaction();

    $upsert = $pdo->prepare("
        INSERT INTO attendance (
            person_id, date, work, final_login, status, 
            check_in, check_out, total_calls,
            total_login, total_idle, total_acw, total_talk, 
            total_meeting, total_general, personal_break
        ) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            work = VALUES(work), 
            final_login = VALUES(final_login), 
            status = VALUES(status),
            check_in = VALUES(check_in),
            check_out = VALUES(check_out),
            total_calls = VALUES(total_calls),
            total_login = VALUES(total_login),
            total_idle = VALUES(total_idle),
            total_acw = VALUES(total_acw),
            total_talk = VALUES(total_talk),
            total_meeting = VALUES(total_meeting),
            total_general = VALUES(total_general),
            personal_break = VALUES(personal_break)
    ");

    $sync_count = 0;
    $fixed_count = 0;
    $debug_samples = [];

    foreach ($filtered_data as $row) {
        $person_id = trim($row['dialer_name']);
        
        if (!isset($user_map[$person_id])) {
            continue;
        }

        $person_id = $user_map[$person_id];
        $log_date = $row['log_date'];

        // ============================================================
        // POSTGRESQL FINAL LOGIN CALCULATION
        // ============================================================

        // 1. TOTAL IDLE (capped at 12600 seconds = 3.5 hours)
        $total_idle = (int)$row['idel'] 
                    + (int)$row['preview_idle'] 
                    + (int)$row['manual_idle'] 
                    + (int)$row['followup_idle'];
        $total_idle = min($total_idle, 12600);

        // 2. TOTAL MEETING (TL Briefing + QA Feedback + Training)
        $total_meeting = (int)$row['tl_briefing'] 
                       + (int)$row['qa_feedback'] 
                       + (int)$row['training'];

        // 3. TOTAL GENERAL (Ring + Talk + Hold + Aux + Setup)
        $total_general = (int)$row['ring_duration'] 
                       + (int)$row['total_talk'] 
                       + (int)$row['hold'] 
                       + (int)$row['aux_duration'] 
                       + (int)$row['setup'];

        // 4. FINAL LOGIN = Idle + ACW + Meeting + General
        $final_login_secs = $total_idle 
                          + (int)$row['total_acw'] 
                          + $total_meeting 
                          + $total_general;

        // 5. ACTUAL BREAK = Total Break - Meeting Time (Meeting is productive)
        $actual_break = (int)$row['total_break'] - $total_meeting;
        if ($actual_break < 0) $actual_break = 0;

        // 6. Cap at 8 hours (28800 seconds) for CSR
        $final_work_secs = min($final_login_secs, 28800);
        $wrk_hrs = round($final_work_secs / 3600, 2);

        if ($wrk_hrs <= 0.1) continue;

        // 7. Status based on work hours
        if ($wrk_hrs >= 7.99) {
            $status = 'P';
        } elseif ($wrk_hrs >= 5.50) {
            $status = 'HD';
        } else {
            $status = 'A';
        }

        // 8. Format times
        $format_time = function($secs) {
            $hours = floor($secs / 3600);
            $minutes = floor(($secs % 3600) / 60);
            $seconds = $secs % 60;
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        };

        $final_login_time = $format_time($final_work_secs);
        $total_login_time = $format_time((int)$row['total_login']);
        $total_idle_time = $format_time($total_idle);
        $total_acw_time = $format_time((int)$row['total_acw']);
        $total_talk_time = $format_time((int)$row['total_talk']);
        $total_meeting_time = $format_time($total_meeting);
        $total_general_time = $format_time($total_general);
        $personal_break_time = $format_time($actual_break);

        $check_in = date("H:i:s", strtotime($row['first_in']));
        $check_out = date("H:i:s", strtotime($row['last_out']));

        // Check existing record
        $check_stmt = $pdo->prepare("SELECT work FROM attendance WHERE person_id = ? AND date = ?");
        $check_stmt->execute([$person_id, $log_date]);
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing && $existing['work'] > 8.0) {
            $fixed_count++;
            echo "  FIXING: $person_id on $log_date: {$existing['work']}hrs ? {$wrk_hrs}hrs\n";
        }

        // Store debug samples
        if (count($debug_samples) < 5) {
            $debug_samples[] = [
                'person' => $person_id,
                'date' => $log_date,
                'dialer_id' => $row['dialer_id'],
                'idle' => $total_idle,
                'acw' => $row['total_acw'],
                'talk' => $row['total_talk'],
                'meeting' => $total_meeting,
                'general' => $total_general,
                'final_secs' => $final_login_secs,
                'work_hrs' => $wrk_hrs,
                'status' => $status,
                'total_break' => $row['total_break'],
                'actual_break' => $actual_break,
                'total_login' => $row['total_login']
            ];
        }

        // Execute UPSERT
        $upsert->execute([
            $person_id,
            $log_date,
            $wrk_hrs,
            $final_login_time,
            $status,
            $check_in,
            $check_out,
            (int)$row['total_calls'],
            $total_login_time,
            $total_idle_time,
            $total_acw_time,
            $total_talk_time,
            $total_meeting_time,
            $total_general_time,
            $personal_break_time
        ]);
        $sync_count++;
    }

    $pdo->commit();

    // Display debug info
    echo "\n--- DEBUG SAMPLES (First 5 matched records) ---\n";
    if (empty($debug_samples)) {
        echo "No records matched!\n";
    } else {
        foreach ($debug_samples as $d) {
            echo "Person: {$d['person']}, Dialer: {$d['dialer_id']}, Date: {$d['date']}\n";
            echo "  Total Login: {$d['total_login']}s, Total Break: {$d['total_break']}s\n";
            echo "  Idle: {$d['idle']}s, ACW: {$d['acw']}s, Talk: {$d['talk']}s\n";
            echo "  Meeting: {$d['meeting']}s, General: {$d['general']}s\n";
            echo "  Final Login: {$d['final_secs']}s = {$d['work_hrs']}hrs\n";
            echo "  Status: {$d['status']}\n\n";
        }
    }

    echo "\n>>> SYNC RESULTS <<<\n";
    echo "Total records synced: $sync_count\n";
    echo "Fixed overwritten records: $fixed_count\n";

    // Verify no CSR records > 8 hours
    $verify_sql = "
        SELECT COUNT(*) as remaining
        FROM attendance a
        JOIN users u ON a.person_id = u.person_id
        WHERE a.date BETWEEN '$date_start' AND '$date_end'
        AND a.work > 8.0
        AND TRIM(UPPER(u.position)) LIKE '%CSR%'
        AND TRIM(process) = 'TATA & BT'
    ";
    $remaining = $pdo->query($verify_sql)->fetchColumn();

    if ($remaining > 0) {
        echo "\nWARNING: $remaining TATA/BT CSR records still have work > 8 hours.\n";
    } else {
        echo "\n? ALL TATA/BT CSR records verified with correct work hours (<= 8 hours)\n";
    }

    echo "\n>>> Sync Complete <<<\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}