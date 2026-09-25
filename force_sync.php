<?php
// Increase processing time and memory
set_time_limit(600); 
ini_set('memory_limit', '512M');

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/modules/config/bootstrap.php';

try {
    echo "<h3>Starting Fast Repair Sync...</h3>";
    $pdo->exec("SET time_zone = '+05:30'");

    // 1. IMPORT DATA (Fastest way)
    $sql = "INSERT INTO atten.attendance (person_id, date, check_in, check_out, records)
            SELECT TRIM(employee_id), attendance_date, check_in, check_out, all_punch_times
            FROM attendance_db.attendance_daily_summary
            WHERE attendance_date >= '2026-08-01'
            ON DUPLICATE KEY UPDATE 
            check_in = VALUES(check_in), 
            check_out = VALUES(check_out), 
            records = VALUES(records)";
    
    $inserted = $pdo->exec($sql);
    echo "Step 1: Data sync finished ($inserted rows affected).<br>";

    // 2. RESTORE NAMES (Only for rows where name is missing - MUCH FASTER)
    // Note: No COLLATE needed here if you ran the SQL in Step 1
    $name_sql = "UPDATE atten.attendance a
                 INNER JOIN atten.users u ON a.person_id = u.person_id
                 SET a.name = u.full_name
                 WHERE a.date >= '2026-08-01' AND (a.name IS NULL OR a.name = '')";
    
    $names = $pdo->exec($name_sql);
    echo "Step 2: Names restored ($names rows updated).<br>";

    // 3. WORK HOURS CALCULATION (Filtered by date for speed)
    $work_sql = "UPDATE atten.attendance 
                 SET work = ROUND(IFNULL(TIME_TO_SEC(TIMEDIFF(check_out, check_in)) / 3600, 0), 2)
                 WHERE date >= '2026-08-01' AND check_in != '00:00:00' AND check_out != '00:00:00'";
    $work = $pdo->exec($work_sql);
    echo "Step 3: Work hours calculated ($work rows updated).<br>";

    // 4. STATUS UPDATES
    $pdo->exec("UPDATE atten.attendance SET status = 'P' WHERE date >= '2026-08-01' AND check_in != '00:00:00' AND (status = 'A' OR status = 'Absent')");
    $pdo->exec("UPDATE atten.attendance SET status = 'L' WHERE date >= '2026-08-01' AND TIME(check_in) > '09:45:00' AND status = 'P'");
    
    echo "<h3>Done! Everything is restored.</h3>";
    echo "<a href='dashboard.php'>Go to Dashboard</a>";

} catch (Exception $e) {
    echo "<b style='color:red;'>Error:</b> " . $e->getMessage();
}
?>