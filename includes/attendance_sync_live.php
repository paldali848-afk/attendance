<?php
// =========================================================
// ATTENDANCE SYNC - OPTIMIZED FOR 2-MINUTE CRON
// =========================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/bootstrap.php';

try {
    $pdo->exec("SET time_zone = '+05:30'"); 
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 60);

    // Optimization: Sirf pichle 2 din ka data process karein
    $date_limit = date('Y-m-d', strtotime('-2 days'));
    
    echo "--- Sync Started at " . date('Y-m-d H:i:s') . " ---\n";

    // --- STEP 1: IMPORT RAW PUNCH TIMES ---
    // COLLATE hata diya gaya hai taaki Unique Index (uk_person_date) sahi se trigger ho
    $import_sql = "
        INSERT INTO atten.attendance (person_id, date, check_in, check_out, records)
        SELECT TRIM(employee_id), attendance_date, check_in, check_out, all_punch_times
        FROM attendance_db.attendance_daily_summary
        WHERE attendance_date >= '$date_limit'
        ON DUPLICATE KEY UPDATE 
            check_in = VALUES(check_in), 
            check_out = VALUES(check_out), 
            records = VALUES(records)
    ";
    $pdo->exec($import_sql);

    // --- STEP 2: CALCULATE WORK HOURS (Sirf 2 din ke liye) ---
    $pdo->exec("UPDATE atten.attendance 
                SET work = ROUND(IFNULL(TIME_TO_SEC(TIMEDIFF(check_out, check_in)) / 3600, 0), 2) 
                WHERE date >= '$date_limit'");

    // --- STEP 3: SEQUENTIAL STATUS UPDATES ---

    // A. Default Past to Absent (Only for last 2 days to save speed)
    $pdo->exec("UPDATE atten.attendance 
                SET status = 'A' 
                WHERE date >= '$date_limit' AND date < CURDATE() 
                AND (status IS NULL OR status = '' OR status = 'Absent')");

    // B. Set Week Offs (Sundays)
    $pdo->exec("UPDATE atten.attendance 
                SET status = 'WO' 
                WHERE date >= '$date_limit' AND DAYOFWEEK(date) = 1 
                AND (check_in IS NULL OR check_in = '00:00:00')");

    // C. Set Paid Holidays (PH)
    $pdo->exec("UPDATE atten.attendance a 
                JOIN atten.holidays h ON a.date = h.holiday_date 
                SET a.status = 'PH' 
                WHERE a.date >= '$date_limit' AND (a.check_in IS NULL OR a.check_in = '00:00:00')");

    // D. Set Present/Late (Har 2 minute mein checkout ke baad status refresh hoga)
    $pdo->exec("UPDATE atten.attendance 
                SET status = CASE 
                    WHEN TIME(check_in) > '09:45:00' THEN 'L' 
                    ELSE 'P' 
                END 
                WHERE date >= '$date_limit' AND check_in IS NOT NULL AND check_in != '00:00:00'");

    // E. Apply Approved Roster
    $pdo->exec("UPDATE atten.attendance a 
                JOIN atten.users u ON a.person_id = u.person_id
                JOIN atten.roster_requests rr ON rr.user_id = u.id
                SET a.status = 'Scheduled'
                WHERE a.date >= CURDATE() AND a.date BETWEEN rr.start_date AND rr.end_date 
                AND rr.status = 'approved' AND (a.check_in IS NULL OR a.check_in = '00:00:00')");

    // F. Apply Approved Exceptions
    $pdo->exec("UPDATE atten.attendance a 
                JOIN atten.late_exception_requests ler ON ler.person_id = a.person_id AND ler.date = a.date
                SET a.status = CASE 
                    WHEN ler.exception_type = 'pl_adjustment' THEN IF(a.work BETWEEN 4.0 AND 7.9, 'HPL', 'PL')
                    ELSE 'FPE' 
                END
                WHERE a.date >= '$date_limit' AND ler.status = 'approved'");

    echo "";
   
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>