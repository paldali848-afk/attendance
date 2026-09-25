<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0); 
ini_set('memory_limit', '1G');

require_once __DIR__ . '/../config/bootstrap.php'; 

try {
    $pdo->exec("SET time_zone = '+05:30'"); 
    $pdo->exec("SET SESSION innodb_lock_wait_timeout = 500");
    $pdo->exec("SET SESSION wait_timeout = 3600");

    echo ">>> Multi-Server Hybrid Sync Engine Started <<<\n";

    $isFullMode = (isset($_SERVER['argv']) && in_array('mode=full', $_SERVER['argv'])) || (isset($_GET['mode']) && $_GET['mode'] == 'full');
    $date_start = $isFullMode ? date('Y-m-01') : date('Y-m-d', strtotime('-1 days'));
    $date_end   = date('Y-m-d'); 

    $win_start = "10:00:00";
    $win_end   = "19:00:00";

    $dialer_servers = [
        'Main'    => getVerveConnection(),   // Postgres
        'Amazon'  => getAmazonConnection(),  // Postgres
        'Upgrade' => getUpgradeConnection(), // Postgres
        'DC'      => getDCConnection(),      // Postgres
        'Plcc'    => getPlccConnection(),    // Postgres
        'RTO'     => getRTOConnection()      // MariaDB/MySQL
    ];

    $final_merged_data = [];

    foreach ($dialer_servers as $server_name => $db_conn) {
        if (!$db_conn) {
            echo "WARNING: Skipping $server_name Server (Connection Failed).\n";
            continue;
        }

        echo "Fetching from $server_name Dialer... ";

        // --- NEW LOGIC: DETECT DB TYPE ---
        $driver = $db_conn->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver == 'pgsql') {
            // Postgres Syntax
            $sql_date     = "eventdate::date";
            $sql_time     = "eventdate::time";
            $sql_min_time = "MIN(eventdate)::time";
            $sql_max_time = "MAX(eventdate + make_interval(secs => loginduration))::time";
            $sql_act_min  = "MIN(l.eventdate)::time";
            $sql_act_max  = "MAX(l.eventdate)::time";
        } else {
            // MySQL / MariaDB Syntax (For RTO)
            $sql_date     = "DATE(eventdate)";
            $sql_time     = "TIME(eventdate)";
            $sql_min_time = "TIME(MIN(eventdate))";
            $sql_max_time = "TIME(MAX(DATE_ADD(eventdate, INTERVAL loginduration SECOND)))";
            $sql_act_min  = "TIME(MIN(l.eventdate))";
            $sql_act_max  = "TIME(MAX(l.eventdate))";
        }

        $vici_sql = "
            WITH pause_data AS (
                SELECT userid, $sql_date as log_date,
                    SUM(loginduration) as t_login_raw,
                    $sql_min_time as first_login,
                    $sql_max_time as last_logout,
                    SUM(CASE WHEN pausecode IN ('LUNCH BREAK', 'TEA BREAK', 'REST ROOM', 'RESTROOM', 'BIO BREAK') THEN duration ELSE 0 END) as p_break_secs
                FROM cr_pause_details_log 
                WHERE eventdate >= '$date_start $win_start' AND eventdate <= '$date_end $win_end'
                  AND $sql_time >= '$win_start' AND $sql_time < '$win_end'
                GROUP BY userid, log_date
            ),
            activity_data AS (
                SELECT l.userid, u.name as dialer_id, $sql_date as log_date,
                    COUNT(DISTINCT l.accountcode) as call_count,
                    $sql_act_min as d_in, $sql_act_max as d_out,
                    SUM(COALESCE(l.idleduration,0) + COALESCE(l.previewidleduration,0) + COALESCE(l.manualidleduration,0) + COALESCE(l.followupidleduration,0)) as raw_idle,
                    SUM(l.acwduration) as t_acw, SUM(l.interactionduration) as t_talk, SUM(l.holdduration) as t_hold,
                    SUM(l.ringingduration + l.setupduration + l.auxduration) as t_gen_tele
                FROM cr_user_log l JOIN ct_user u ON u.id = l.userid
                WHERE l.eventdate >= '$date_start 00:00:00' AND l.eventdate <= '$date_end 23:59:59'
                GROUP BY l.userid, u.name, log_date
            )
            SELECT 
                TRIM(a.dialer_id) as id, a.log_date, p.t_login_raw, p.p_break_secs, 
                p.first_login, p.last_logout, a.raw_idle, a.t_acw, a.t_talk, a.t_hold, a.t_gen_tele, a.call_count
            FROM activity_data a
            JOIN pause_data p ON p.userid = a.userid AND p.log_date = a.log_date
        ";
        
        $stmt = $db_conn->query($vici_sql);
        if (!$stmt) {
            $err = $db_conn->errorInfo();
            echo "FAILED SQL: " . $err[2] . "\n";
            continue;
        }

        $server_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($server_data as $row) {
            $key = strtoupper(trim($row['id'])) . '_' . $row['log_date'];
            if (!isset($final_merged_data[$key])) {
                $final_merged_data[$key] = $row;
            } else {
                $final_merged_data[$key]['t_login_raw'] += $row['t_login_raw'];
                $final_merged_data[$key]['p_break_secs'] += $row['p_break_secs'];
                $final_merged_data[$key]['raw_idle'] += $row['raw_idle'];
                $final_merged_data[$key]['t_acw'] += $row['t_acw'];
                $final_merged_data[$key]['t_talk'] += $row['t_talk'];
                $final_merged_data[$key]['call_count'] += $row['call_count'];
                if ($row['first_login'] < $final_merged_data[$key]['first_login']) $final_merged_data[$key]['first_login'] = $row['first_login'];
                if ($row['last_logout'] > $final_merged_data[$key]['last_logout']) $final_merged_data[$key]['last_logout'] = $row['last_logout'];
            }
        }
        echo "Done (" . count($server_data) . " rows).\n";
    }

    // --- STEPS 3, 4, 5 REMAIN THE SAME AS PER YOUR WORKING VERSION ---
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_dialer_multi");
    $pdo->exec("CREATE TEMPORARY TABLE temp_dialer_multi (
        person_id VARCHAR(50), numeric_id VARCHAR(50), log_date DATE, work_dec DECIMAL(10,2),
        t_calls INT, t_login TIME, t_idle TIME, t_acw TIME, t_talk TIME, t_gen TIME, p_break TIME, f_login TIME,
        d_in TIME, d_out TIME,
        INDEX (person_id, log_date)
    ) DEFAULT CHARSET=utf8mb4");

    $insert = $pdo->prepare("INSERT INTO temp_dialer_multi VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $pdo->beginTransaction();
    foreach ($final_merged_data as $row) {
        $net_secs = (int)$row['t_login_raw'] - (int)$row['p_break_secs'];
        $raw_id = strtoupper(trim($row['id']));
        $insert->execute([
            $raw_id, preg_replace('/[^0-9]/', '', $raw_id), $row['log_date'],
            round($net_secs / 3600, 2), (int)$row['call_count'],
            gmdate("H:i:s", (int)$row['t_login_raw']), gmdate("H:i:s", min(12600, (int)$row['raw_idle'])),
            gmdate("H:i:s", (int)$row['t_acw']), gmdate("H:i:s", (int)$row['t_talk']),
            gmdate("H:i:s", (int)$row['t_gen_tele'] + (int)$row['t_hold']),
            gmdate("H:i:s", (int)$row['p_break_secs']), gmdate("H:i:s", max(0, $net_secs)),
            $row['first_login'], $row['last_logout']
        ]);
    }
    $pdo->commit();

    // BULK UPDATE
    $pdo->exec("
        UPDATE atten.attendance a
        INNER JOIN temp_dialer_multi t ON a.date = t.log_date
        INNER JOIN atten.users u ON a.person_id COLLATE utf8mb4_unicode_ci = u.person_id COLLATE utf8mb4_unicode_ci
        SET a.total_calls = t.t_calls, a.work = t.work_dec, a.check_in = t.d_in, a.check_out = t.d_out,
            a.total_login = t.t_login, a.total_idle = t.t_idle, a.total_acw = t.t_acw,
            a.total_talk = t.t_talk, a.total_general = t.t_gen, a.personal_break = t.p_break, a.final_login = t.f_login
        WHERE (a.person_id COLLATE utf8mb4_unicode_ci = t.person_id COLLATE utf8mb4_unicode_ci 
               OR a.person_id COLLATE utf8mb4_unicode_ci = CONCAT('FNM', t.numeric_id) COLLATE utf8mb4_unicode_ci)
        AND (TRIM(UPPER(u.position)) LIKE '%CSR%' OR TRIM(UPPER(u.position)) LIKE '%AGENT%')
    ");

    // RECALCULATE STATUS
    $pdo->exec("
        UPDATE atten.attendance a 
        JOIN atten.users u ON a.person_id COLLATE utf8mb4_unicode_ci = u.person_id COLLATE utf8mb4_unicode_ci
        SET a.status = CASE 
            WHEN TRIM(UPPER(u.process)) = 'UPGRADE' THEN
                CASE WHEN a.work >= 8.00 THEN 'P' WHEN a.work >= 4.50 THEN 'HD' ELSE 'A' END
            ELSE
                CASE WHEN a.work >= 8.00 THEN 'P' WHEN a.work >= 5.50 THEN 'HD' ELSE 'A' END
        END
        WHERE a.date >= '$date_start' 
        AND (TRIM(UPPER(u.position)) LIKE '%CSR%' OR TRIM(UPPER(u.position)) LIKE '%AGENT%') 
        AND a.check_in IS NOT NULL 
        AND a.status NOT IN ('PL', 'HPL', 'LWP', 'CO', 'WO', 'PH') 
        AND (a.exception_type IS NULL OR a.exception_type = '')
    ");

    echo "--- All Hybrid Dialers Synced Successfully ---\n";
} catch (Exception $e) { 
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n"; 
}
?>