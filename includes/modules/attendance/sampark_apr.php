<?php
/**
 * UPGRADE, DC & PLCC PROCESS SYNC - VERSION 23 (SP-based, fixed)
 * Source: sp_agentwise_perform160(start, end, campaign, user, options)
 * Logic: Final Login = Login - (Break - Meeting)
 *        Status P / HD / A based on 8h / 4h thresholds
 *        Skip if already 'P'
 *        Upsert into attendance
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

    $date_start = isset($_GET['target_month'])
        ? date('Y-m-01', strtotime($_GET['target_month']))
        : date('Y-m-d', strtotime('-1 days'));
    $date_end   = isset($_GET['target_month'])
        ? date('Y-m-t', strtotime($_GET['target_month']))
        : date('Y-m-d');

    // SP requires full datetime strings
    $sp_from = $date_start . ' 00:00:00';
    $sp_to   = $date_end   . ' 23:59:59';

    echo ">>> UPGRADE/DC/PLCC SYNC (SP) STARTED: $sp_from to $sp_to <<<\n";

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
        $user_metadata[trim($u['person_id'])] = $u;
    }

    echo "Processing " . count($user_metadata) . " CSR users with UPGRADE process.\n";

    // 2. Dialer connections
    $dialer_servers = [
        'Sampark' => getRTOConnection(),
    ];

    $merged = [];

    // Helpers (defined once, outside the loop)
    $pick = fn(array $row, string $key) => $row[$key] ?? null;

    $toSecs = function ($t) {
        if ($t === null || $t === '' || $t === '00:00:00') return 0;
        $parts = array_map('intval', explode(':', (string)$t));
        while (count($parts) < 3) $parts[] = 0;
        return $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
    };

    // 3. Fetch from each dialer via STORED PROCEDURE
    foreach ($dialer_servers as $name => $db) {
        if (!$db) {
            echo "SKIPPING $name: Connection Failed.\n";
            continue;
        }

        echo "Fetching from $name via sp_agentwise_perform160... ";

        try {
            $stmt = $db->prepare("CALL sp_agentwise_perform160(?, ?, ?, ?, ?)");
            $stmt->execute([
                $sp_from,
                $sp_to,
                'all',
                'all',
                'SUMMARY,HOURLY'
            ]);

            $spRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // MySQL: drain extra result sets
            $dr = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($dr === 'mysql') {
                while ($stmt->nextRowset()) { /* drain */ }
            }
        } catch (Exception $e) {
            echo "SP ERROR on $name: " . $e->getMessage() . "\n";
            continue;
        }

        echo "Found " . count($spRows) . " rows.\n";
        if (empty($spRows)) continue;

        // ---------------------------------------------------------------
        // 4. COLUMN MAPPING — matches the actual SP output
        //    Columns: Date | Emp Code | Agent Full Name | Total Login Time |
        //             Total Talk Time | Total Wait Time | Total ACW Time |
        //             Total Ringing Time | Total Hold Duration |
        //             Production_break | Non Production_Break | Autule Login
        // ---------------------------------------------------------------
        foreach ($spRows as $raw) {

            $empCode = trim((string)$pick($raw, 'Emp Code'));
            $logDate = $pick($raw, 'Date');

            if ($empCode === '' || empty($logDate)) continue;

            $logDate = date('Y-m-d', strtotime($logDate));

            // Convert HH:MM:SS fields to seconds
            $loginSecs  = $toSecs($pick($raw, 'Total Login Time'));
            $breakProd  = $toSecs($pick($raw, 'Production_break'));
            $breakNon   = $toSecs($pick($raw, 'Non Production_Break'));
            $totalBreak = $breakProd + $breakNon;

            // SP has "Autule Login" but no logout column
            $firstLogin   = $pick($raw, 'Autule Login');
            $lastActivity = null;

            $r = [
                'dialer_id'        => strtoupper($empCode),
                'log_date'         => $logDate,
                't_login_secs'     => $loginSecs,
                'total_break_secs' => $totalBreak,
                'p_meeting_secs'   => $breakProd,   // Production_break = productive/meeting
                'first_login'      => $firstLogin,
                'last_activity'    => $lastActivity,
            ];

            $key = $r['dialer_id'] . '_' . $r['log_date'];

            if (isset($merged[$key])) {
                $merged[$key]['t_login_secs']     += $r['t_login_secs'];
                $merged[$key]['total_break_secs'] += $r['total_break_secs'];
                $merged[$key]['p_meeting_secs']   += $r['p_meeting_secs'];

                if ($r['first_login']
                    && ($merged[$key]['first_login'] === null
                        || strtotime($r['first_login']) < strtotime($merged[$key]['first_login']))) {
                    $merged[$key]['first_login'] = $r['first_login'];
                }
                if ($r['last_activity']
                    && ($merged[$key]['last_activity'] === null
                        || strtotime($r['last_activity']) > strtotime($merged[$key]['last_activity']))) {
                    $merged[$key]['last_activity'] = $r['last_activity'];
                }
            } else {
                $merged[$key] = $r;
            }
        }
    }

    // 5. Filter merged to only users in our metadata
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

    // 6. Upsert
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

    $sync_count    = 0;
    $skipped_count = 0;
    $updated_count = 0;
    $debug_records = [];

    foreach ($filtered_merged as $row) {
        $dialer_id = strtoupper(trim($row['dialer_id']));
        $p_id = is_numeric($dialer_id) ? "FNM" . $dialer_id : $dialer_id;

        if (!isset($user_metadata[$p_id])) continue;

        // --- ORIGINAL LOGIC ---
        $total_login   = (int)$row['t_login_secs'];
        $total_break   = (int)$row['total_break_secs'];
        $total_meeting = (int)$row['p_meeting_secs'];

        $actual_break_secs = $total_break - $total_meeting;   // = non-productive break
        $final_login_secs  = $total_login - $actual_break_secs;
        $wrk_hrs           = $final_login_secs / 3600;

        if ($wrk_hrs <= 0.1) continue;

        $required = 8.0;
        $half     = 4.0;

        if ($wrk_hrs >= $required - 0.001) {
            $status = 'P';
        } elseif ($wrk_hrs >= $half - 0.001) {
            $status = 'HD';
        } else {
            $status = 'A';
        }

        if (count($debug_records) < 5) {
            $debug_records[] = [
                'person_id'        => $p_id,
                'date'             => $row['log_date'],
                'total_login'      => $total_login,
                'total_break'      => $total_break,
                'total_meeting'    => $total_meeting,
                'actual_break'     => $actual_break_secs,
                'final_login_secs' => $final_login_secs,
                'wrk_hrs'          => $wrk_hrs,
                'status'           => $status,
                'required'         => $required,
                'half'             => $half,
            ];
        }

        $hours   = floor($final_login_secs / 3600);
        $minutes = floor(($final_login_secs % 3600) / 60);
        $seconds = $final_login_secs % 60;
        $f_in    = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

        $c_in  = $row['first_login']
            ? date("H:i:s", strtotime($row['first_login']))
            : null;
        $c_out = $row['last_activity']
            ? date("H:i:s", strtotime($row['last_activity']))
            : null;

        $check_stmt = $pdo->prepare("SELECT status, work FROM attendance WHERE person_id = ? AND date = ?");
        $check_stmt->execute([$p_id, $row['log_date']]);
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing && $existing['status'] == 'P') {
            $skipped_count++;
            continue;
        }
        if ($existing && $existing['status'] != 'P' && $status == 'P') {
            $updated_count++;
        }

        $upsert->execute([$p_id, $row['log_date'], round($wrk_hrs, 2), $f_in, $status, $c_in, $c_out]);
        $sync_count++;
    }

    $pdo->commit();

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