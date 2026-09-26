<?php
// =========================================================
// ATTENDANCE SYNC - NO SCHEMA CHANGE VERSION
// Roster type inferred at runtime from date + holidays table
// + 'S' status treated like WO (Sunday roster handling)
// + PL/LWP/HPL seeded from leaves before punch import
// =========================================================
if (php_sapi_name() === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '=') !== false) {
            list($key, $val) = explode('=', $arg);
            $_GET[$key] = $val;
        }
    }
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/bootstrap.php';

// Policy toggles
$strict_ph_roster = false; // PH + roster + no punch = PH (false) or A (true)
$mark_wo_absent   = true;  // WO/S + roster + no punch = A

try {
    $pdo->exec("SET time_zone = '+05:30'"); 
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 60);

    if (isset($_GET['target_month'])) {
        $date_limit = date('Y-m-01', strtotime($_GET['target_month']));
        $date_end   = date('Y-m-t', strtotime($_GET['target_month']));
    } else {
        $date_limit = date('Y-m-d', strtotime('-2 days'));
        $date_end   = date('Y-m-d');
    }

    echo "--- Syncing Biometric Data ($date_limit to $date_end) ---\n";

    // =========================================================
    // STEP 0: SEED PH ROWS
    // =========================================================
    try {
        $pdo->exec("
            INSERT INTO atten.attendance (person_id, date, status)
            SELECT u.person_id, h.holiday_date, 'PH'
            FROM atten.users u
            CROSS JOIN atten.holidays h
            WHERE u.is_active = 1
              AND h.holiday_date BETWEEN '$date_limit' AND '$date_end'
            ON DUPLICATE KEY UPDATE 
                status = CASE 
                    WHEN atten.attendance.check_in IS NOT NULL 
                         AND atten.attendance.check_in != '00:00:00' 
                         AND atten.attendance.status IN ('P','HD','L','HPL','PL')
                        THEN atten.attendance.status
                    WHEN atten.attendance.status IN ('WO','CO','LWP','PL','S')
                        THEN atten.attendance.status
                    ELSE 'PH'
                END
        ");
        echo "--- Step 0: PH seeding completed ---\n";
    } catch (Exception $e) {
        echo "--- Step 0 skipped: " . $e->getMessage() . " ---\n";
    }

 // =========================================================
// STEP 0.5: SEED APPROVED LEAVES (PL / LWP / HPL / SL / CL)
// Source table: atten.leave_requests
// =========================================================
try {
    $pdo->exec("
        INSERT INTO atten.attendance (person_id, date, status)
        SELECT 
            lv.person_id, 
            lv.date,
            CASE 
                WHEN UPPER(lv.leave_type) IN ('PL','PERSONAL LEAVE','PLANNED LEAVE') THEN 'PL'
                WHEN UPPER(lv.leave_type) IN ('LWP','LEAVE WITHOUT PAY')             THEN 'LWP'
                WHEN UPPER(lv.leave_type) IN ('HPL','HALF PAID LEAVE')               THEN 'HPL'
                WHEN UPPER(lv.leave_type) IN ('SL','SICK LEAVE')                     THEN 'PL'
                WHEN UPPER(lv.leave_type) IN ('CL','CASUAL LEAVE')                   THEN 'PL'
                ELSE 'PL'
            END
        FROM atten.leave_requests lv
        WHERE lv.status = 'approved'
          AND lv.date BETWEEN '$date_limit' AND '$date_end'
        ON DUPLICATE KEY UPDATE 
            status = CASE
                -- Never downgrade an actual punch
                WHEN atten.attendance.check_in IS NOT NULL 
                     AND atten.attendance.check_in != '00:00:00'
                     AND atten.attendance.status IN ('P','HD','L','HPL')
                    THEN atten.attendance.status
                -- Never downgrade an existing leave
                WHEN atten.attendance.status IN ('PL','LWP','HPL','CO')
                    THEN atten.attendance.status
                -- Otherwise apply the leave
                ELSE VALUES(status)
            END
    ");
    echo "--- Step 0.5: Leave seeding completed ---\n";
} catch (Exception $e) {
    echo "--- Step 0.5 skipped: " . $e->getMessage() . " ---\n";
}
    // =========================================================
    // STEP 1: IMPORT PUNCHES
    // =========================================================
    $pdo->exec("INSERT INTO atten.attendance (person_id, date, check_in, check_out, records)
                SELECT TRIM(employee_id), attendance_date, check_in, check_out, all_punch_times
                FROM attendance_db.attendance_daily_summary
                WHERE attendance_date BETWEEN '$date_limit' AND '$date_end'
                ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), check_out=VALUES(check_out), records=VALUES(records)");

    // =========================================================
    // STEP 2: WORK HOURS
    // =========================================================
    $pdo->exec("UPDATE atten.attendance a 
                JOIN atten.users u ON a.person_id COLLATE utf8mb4_unicode_ci = u.person_id COLLATE utf8mb4_unicode_ci
                SET a.work = ROUND(IFNULL(TIME_TO_SEC(TIMEDIFF(check_out, check_in)) / 3600, 0), 2) 
                WHERE a.date BETWEEN '$date_limit' AND '$date_end' 
                AND TRIM(UPPER(u.position)) NOT LIKE '%CSR%' 
                AND TRIM(UPPER(u.position)) NOT LIKE '%AGENT%'");

    // =========================================================
    // STEP 3: MAIN STATUS LOGIC
    // day_type is INFERRED:
    //   - work_on_wo ? DAYOFWEEK(a.date) = 1 (Sunday), status in WO/S
    //   - work_on_ph ? a.date exists in atten.holidays
    // =========================================================
    $sync_logic_sql = "
        UPDATE atten.attendance a 
        JOIN atten.users u ON a.person_id COLLATE utf8mb4_unicode_ci = u.person_id COLLATE utf8mb4_unicode_ci
        LEFT JOIN atten.roster_requests rr ON a.person_id COLLATE utf8mb4_unicode_ci = rr.person_id COLLATE utf8mb4_unicode_ci 
            AND a.date BETWEEN rr.start_date AND rr.end_date 
            AND rr.status = 'approved'
        SET a.status = CASE 
            -- Case A: WO/S + approved roster + Sunday ? treat as working day
            WHEN a.status IN ('WO','S')
                 AND rr.id IS NOT NULL 
                 AND DAYOFWEEK(a.date) = 1 
                 AND rr.start_time IS NOT NULL 
            THEN
                CASE 
                    WHEN a.work >= IFNULL(rr.working_hours, 9.0) THEN 
                        CASE 
                            WHEN rr.start_time != '' AND rr.start_time != '0' THEN
                                IF(TIME(a.check_in) > (TIME(STR_TO_DATE(rr.start_time, '%h:%i %p')) + INTERVAL 15 MINUTE), 'L', 'P')
                            ELSE
                                IF(TIME(a.check_in) > '09:45:00', 'L', 'P')
                        END
                    WHEN a.work >= 4.5 THEN 'HD'
                    ELSE 'A'
                END
            -- Case B: Currently PH
            WHEN a.status = 'PH' THEN
                CASE 
                    WHEN a.check_in IS NULL OR a.check_in = '00:00:00' THEN 'PH'
                    WHEN a.work >= IFNULL(rr.working_hours, IF(u.role IN ('process_head','centre_head'), 8.0, 9.0)) THEN 'P'
                    WHEN a.work >= 4.5 THEN 'HD'
                    ELSE 'PH'
                END
            -- Case C: Normal working day
            WHEN a.work >= IFNULL(rr.working_hours, IF(u.role IN ('process_head', 'centre_head'), 8.0, 9.0)) THEN
                CASE 
                    WHEN rr.start_time IS NOT NULL AND rr.start_time != '' AND rr.start_time != '0' THEN
                        IF(TIME(a.check_in) > (TIME(STR_TO_DATE(rr.start_time, '%h:%i %p')) + INTERVAL 15 MINUTE), 'L', 'P')
                    ELSE
                        IF(TIME(a.check_in) > '09:45:00', 'L', 'P')
                END
            WHEN a.work >= 4.5 THEN 'HD'
            ELSE 'A'
        END
        WHERE a.date BETWEEN '$date_limit' AND '$date_end'
        AND a.check_in IS NOT NULL 
        AND a.check_in != '00:00:00'
        AND TRIM(UPPER(u.position)) NOT LIKE '%CSR%' 
        AND TRIM(UPPER(u.position)) NOT LIKE '%AGENT%'
        AND a.status NOT IN ('PL','CO','HPL','LWP')
        -- Allow WO/S override ONLY when there's a roster AND it's Sunday
        AND NOT (a.status IN ('WO','S') 
                 AND (rr.id IS NULL OR DAYOFWEEK(a.date) != 1))
    ";
    
    $pdo->exec($sync_logic_sql);

    // =========================================================
    // STEP 3b: WO/S + Sunday + roster + no punch ? A
    // (Guarded so it does NOT overwrite PL/LWP/HPL/CO)
    // =========================================================
    if ($mark_wo_absent) {
        $pdo->exec("
            UPDATE atten.attendance a 
            JOIN atten.roster_requests rr 
                ON a.person_id COLLATE utf8mb4_unicode_ci = rr.person_id COLLATE utf8mb4_unicode_ci
                AND a.date BETWEEN rr.start_date AND rr.end_date 
                AND rr.status = 'approved'
            SET a.status = 'A'
            WHERE a.date BETWEEN '$date_limit' AND '$date_end'
              AND a.status IN ('WO','S')
              AND DAYOFWEEK(a.date) = 1
              AND rr.start_time IS NOT NULL
              AND (a.check_in IS NULL OR a.check_in = '00:00:00')
              AND a.status NOT IN ('PL','LWP','HPL','CO')
        ");
         
        echo "--- Step 3b: WO/S-absent marking completed ---\n";
    }

    // =========================================================
    // STEP 3c: PH + roster + no punch ? A (only if strict)
    // (Guarded so it does NOT overwrite PL/LWP/HPL/CO)
    // =========================================================
    if ($strict_ph_roster) {
        $pdo->exec("
            UPDATE atten.attendance a 
            JOIN atten.roster_requests rr 
                ON a.person_id COLLATE utf8mb4_unicode_ci = rr.person_id COLLATE utf8mb4_unicode_ci
                AND a.date BETWEEN rr.start_date AND rr.end_date 
                AND rr.status = 'approved'
            JOIN atten.holidays h ON h.holiday_date = a.date
            SET a.status = 'A'
            WHERE a.date BETWEEN '$date_limit' AND '$date_end'
              AND a.status = 'PH'
              AND rr.start_time IS NOT NULL
              AND (a.check_in IS NULL OR a.check_in = '00:00:00')
              AND a.status NOT IN ('PL','LWP','HPL','CO')
        ");
        echo "--- Step 3c: PH-absent marking completed (strict mode) ---\n";
    }

// =========================================================
// STEP 4: NIGHT SHIFT DETECTION (IT Department only)
// Night shift = out_time between 12:00 AM and 1:00 AM
// =========================================================
try {
    $stmt = $pdo->exec("
        UPDATE atten.attendance a 
        JOIN atten.users u 
            ON a.person_id COLLATE utf8mb4_unicode_ci = u.person_id COLLATE utf8mb4_unicode_ci
        SET a.night_shift = 1
        WHERE a.date BETWEEN '$date_limit' AND '$date_end'
          AND UPPER(TRIM(u.department)) = 'IT'
          AND a.check_out IS NOT NULL 
          AND a.check_out != '00:00:00'
          AND TIME(a.check_out) >= '00:00:00' 
          AND TIME(a.check_out) <= '01:00:00'
    ");
    echo "--- Step 4: Night shift detection completed (" . $stmt . " rows) ---\n";
} catch (Exception $e) {
    echo "--- Step 4 FAILED: " . $e->getMessage() . " ---\n";
}

    // =========================================================
    // STEP 5: EXCEPTIONS
    // =========================================================
    $pdo->exec("UPDATE atten.attendance a 
                JOIN atten.late_exception_requests ler ON a.person_id COLLATE utf8mb4_unicode_ci = ler.person_id COLLATE utf8mb4_unicode_ci 
                AND a.date = ler.date
                SET a.status = CASE 
                    WHEN ler.exception_type = 'full_day' THEN 'P'
                    WHEN ler.exception_type = 'half_day' THEN 'HD'
                    WHEN ler.exception_type = 'pl_adjustment' THEN IF(a.work BETWEEN 4.0 AND 7.9, 'HPL', 'PL')
                    ELSE 'P' 
                END,
                a.exception_type = ler.exception_type
                WHERE a.date BETWEEN '$date_limit' AND '$date_end' 
                AND ler.status = 'approved'
                AND a.status != 'PH'");   

    echo "--- Sync Completed at " . date('Y-m-d H:i:s') . " ---\n";
} catch (Exception $e) { 
    echo "ERROR: " . $e->getMessage() . "\n"; 
}