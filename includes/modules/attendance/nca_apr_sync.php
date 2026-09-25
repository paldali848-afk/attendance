<?php
/**
 * MASTER HYBRID SYNC ENGINE - VERSION 18
 * FIXED: Matching PostgreSQL Final Login calculation logic
 * Final Login = Total Idle + Total ACW + Total Talk + Total Meeting + Total General
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

    if (isset($_GET['target_month'])) {
        $date_start = date('Y-m-01', strtotime($_GET['target_month']));
        $date_end   = date('Y-m-t', strtotime($_GET['target_month']));
    } else {
        $date_start = date('Y-m-d', strtotime('-1 days'));
        $date_end   = date('Y-m-d');
    }
    
    echo ">>> HYBRID SYNC STARTED: $date_start to $date_end <<<\n";
    echo ">>> USING POSTGRESQL FINAL LOGIN CALCULATION FOR CSR/AGENT <<<\n";

    $dialer_servers = [
        'Main'    => getVerveConnection(),
        'Amazon'  => getAmazonConnection()
    ];

    $merged_dialer_data = [];

    foreach ($dialer_servers as $name => $db) {
        if (!$db) { echo "SKIPPING $name: Connection failed.\n"; continue; }
        echo "Fetching from $name... ";

        // 1. Fetch ALL Activity Data (Matches PostgreSQL query)
        $sql_activity = "SELECT 
                    u.name as dialer_id, 
                    l.userid, 
                    l.eventdate::date as log_date,
                    SUM(COALESCE(l.idleduration,0)) as idel,
                    SUM(COALESCE(l.previewidleduration,0)) as preview_idle,
                    SUM(COALESCE(l.manualidleduration,0)) as manual_idle,
                    SUM(COALESCE(l.followupidleduration,0)) as followup_idle,
                    SUM(l.acwduration) as acw, 
                    SUM(l.interactionduration) as tlk,
                    SUM(l.ringingduration) as ring_duration,
                    SUM(l.holdduration) as hold,
                    SUM(l.auxduration) as aux,
                    SUM(l.setupduration) as setup
                FROM cr_user_log l 
                JOIN ct_user u ON u.id = l.userid
                WHERE l.eventdate >= '$date_start 00:00:00' AND l.eventdate <= '$date_end 23:59:59'
                GROUP BY u.name, l.userid, log_date";

        $rows = $db->query($sql_activity)->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch Pause/Login Info
        $sql_pause = "SELECT 
                    userid, 
                    eventdate::date as log_date,
                    SUM(loginduration) as login_secs,
                    MIN(eventdate)::time as first_in,
                    MAX(eventdate)::time as last_out,
                    SUM(duration) as total_break,
                    SUM(CASE WHEN UPPER(TRIM(pausecode)) = 'AUX' THEN duration ELSE 0 END) as break_aux,
                    SUM(CASE WHEN UPPER(TRIM(pausecode)) = 'TL BRIEFING' THEN duration ELSE 0 END) as tl_briefing
                FROM cr_pause_details_log 
                WHERE eventdate >= '$date_start 00:00:00' AND eventdate <= '$date_end 23:59:59'
                GROUP BY userid, log_date";
        
        $pause_rows = $db->query($sql_pause)->fetchAll(PDO::FETCH_ASSOC);
        $pause_map = [];
        foreach($pause_rows as $p) { 
            $pause_map[$p['userid'].'_'.$p['log_date']] = $p; 
        }

        // 3. Merge in PHP
        $count = 0;
        foreach ($rows as $r) {
            $key = $r['userid'] . '_' . $r['log_date'];
            $p_info = $pause_map[$key] ?? [
                'login_secs' => 0, 
                'first_in' => '00:00:00', 
                'last_out' => '00:00:00',
                'total_break' => 0,
                'break_aux' => 0,
                'tl_briefing' => 0
            ];
            
            $final_key = strtoupper(trim($r['dialer_id'])) . '_' . $r['log_date'];
            $merged_dialer_data[$final_key] = array_merge($r, $p_info);
            $count++;
        }
        echo "Found $count records.\n";
    }

    if (!empty($merged_dialer_data)) {
        // Build User Map - ONLY CSR/AGENT
        $stmt_users = $pdo->query("
            SELECT person_id FROM users 
            WHERE person_id IS NOT NULL 
            AND (TRIM(UPPER(position)) LIKE '%CSR%' OR TRIM(UPPER(position)) LIKE '%AGENT%')
        ");
        $user_map = [];
        while ($u = $stmt_users->fetch()) {
            $num = (int)preg_replace('/[^0-9]/', '', $u['person_id']);
            if ($num > 0) $user_map[$num] = $u['person_id'];
        }

        $pdo->beginTransaction();
        
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
        $fixed_count = 0;
        
        foreach ($merged_dialer_data as $row) {
            $dialer_num = (int)preg_replace('/[^0-9]/', '', $row['dialer_id']);
            
            if (isset($user_map[$dialer_num])) {
                $person_id = $user_map[$dialer_num];
                $log_date = $row['log_date'];
                
                // ============================================
                // POSTGRESQL FINAL LOGIN CALCULATION
                // ============================================
                
                // 1. Total Idle (capped at 12600 seconds = 3.5 hours)
                $total_idle = (int)$row['idel'] 
                            + (int)$row['preview_idle'] 
                            + (int)$row['manual_idle'] 
                            + (int)$row['followup_idle'];
                $total_idle = min($total_idle, 12600);
                
                // 2. Total ACW
                $total_acw = (int)$row['acw'];
                
                // 3. Total Talk
                $total_talk = (int)$row['tlk'];
                
                // 4. Total Meeting (TL Briefing)
                $total_meeting = (int)($row['tl_briefing'] ?? 0);
                
                // 5. Total General (ring_duration + hold + aux + break_aux + setup)
                $total_general = (int)$row['ring_duration'] 
                               + (int)$row['hold'] 
                               + (int)$row['aux'] 
                               + (int)($row['break_aux'] ?? 0)
                               + (int)$row['setup'];
                
                // 6. FINAL LOGIN = Total_Idle + Total_ACW + Total_Talk + Total_Meeting + Total_General
                $final_login_secs = $total_idle 
                                  + $total_acw 
                                  + $total_talk 
                                  + $total_meeting 
                                  + $total_general;
                
                // Cap at 8 hours (28800 seconds) for CSR/AGENT
                $final_work_secs = min($final_login_secs, 28800);
                $wrk_hrs = round($final_work_secs / 3600, 2);
                
                if ($wrk_hrs <= 0.1) continue;

                // Status based on work hours
                $status = ($wrk_hrs >= 7.99) ? 'P' : ($wrk_hrs >= 5.50 ? 'HD' : 'A');
                
                // Check existing record
                $check_stmt = $pdo->prepare("SELECT work FROM attendance WHERE person_id = ? AND date = ?");
                $check_stmt->execute([$person_id, $log_date]);
                $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                $is_overwritten = ($existing && $existing['work'] > 8.0);
                
                if ($is_overwritten || $wrk_hrs > 8.0) {
                    $fixed_count++;
                    echo "  FIXING: $person_id on $log_date: " . ($existing['work'] ?? 'new') . "hrs ? {$wrk_hrs}hrs (PostgreSQL logic)\n";
                    
                    // Debug info for first few fixes
                    if ($fixed_count <= 10) {
                        echo "    Components: Idle={$total_idle}s, ACW={$total_acw}s, Talk={$total_talk}s, Meeting={$total_meeting}s, General={$total_general}s\n";
                    }
                }
                
                $upsert->execute([
                    $person_id, 
                    $log_date, 
                    $wrk_hrs, 
                    gmdate("H:i:s", (int)$final_work_secs), 
                    $status,
                    $row['first_in'],
                    $row['last_out']
                ]);
                $sync_count++;
            }
        }
        $pdo->commit();
        
        echo "\n>>> SYNC RESULTS <<<\n";
        echo "Total records synced: $sync_count\n";
        echo "Fixed/Updated records: $fixed_count\n";
        
        // Double check for any CSR with > 8 hours
        $verify_sql = "
            SELECT COUNT(*) as remaining
            FROM attendance a
            JOIN users u ON a.person_id = u.person_id
            WHERE a.date BETWEEN '$date_start' AND '$date_end'
            AND a.work > 8.0
            AND (TRIM(UPPER(u.position)) LIKE '%CSR%' OR TRIM(UPPER(u.position)) LIKE '%AGENT%')
        ";
        $remaining = $pdo->query($verify_sql)->fetchColumn();
        
        if ($remaining > 0) {
            echo "\nWARNING: $remaining CSR records still have work > 8 hours.\n";
        } else {
            echo "\n? ALL CSR records verified with correct work hours (<= 8 hours)\n";
        }
    }

    echo "\n>>> Sync Complete <<<\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}