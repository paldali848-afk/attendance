<?php
require_once 'includes/modules/config/bootstrap.php';

$test_user = 'FNM1234'; // Put the ID you want to check here
$test_date = '2026-08-27';

$pdo_vici = getVerveConnection(); 
if ($pdo_vici) {
    $vici_sql = "
        WITH user_metrics AS (
            SELECT
                l.userid,
                SUM(l.interactionduration) as talk,
                SUM(l.acwduration) as acw,
                SUM(l.ringingduration) as ring,
                SUM(l.holdduration) as hold,
                SUM(COALESCE(l.idleduration,0) + COALESCE(l.previewidleduration,0) + 
                    COALESCE(l.manualidleduration,0) + COALESCE(l.followupidleduration,0)) as raw_idle
            FROM cr_user_log l
            WHERE l.userid = '$test_user' AND l.eventdate::date = '$test_date'
            GROUP BY l.userid
        ),
        pause_metrics AS (
            SELECT
                userid,
                SUM(CASE WHEN pausecode = 'TL BRIEFING' THEN duration ELSE 0 END) as meeting
            FROM cr_pause_details_log
            WHERE userid = '$test_user' AND eventdate::date = '$test_date'
            GROUP BY userid
        )
        SELECT u.*, COALESCE(p.meeting, 0) as meeting FROM user_metrics u 
        LEFT JOIN pause_metrics p ON u.userid = p.userid";

    $data = $pdo_vici->query($vici_sql)->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $capped_idle = ($data['raw_idle'] > 12600) ? 12600 : $data['raw_idle'];
        $total_seconds = $data['talk'] + $data['acw'] + $data['ring'] + $data['hold'] + $data['meeting'] + $capped_idle;
        $total_hours = round($total_seconds / 3600, 2);

        echo "<h2>Calculation Audit for $test_user on $test_date</h2>";
        echo "Talk Time: " . ($data['talk']/60) . " mins<br>";
        echo "ACW Time: " . ($data['acw']/60) . " mins<br>";
        echo "Meeting (Briefing): " . ($data['meeting']/60) . " mins<br>";
        echo "Raw Idle: " . ($data['raw_idle']/3600) . " hours<br>";
        echo "Capped Idle (Max 3.5h): " . ($capped_idle/3600) . " hours<br>";
        echo "-----------------------------------<br>";
        echo "<b>Total Hours Calculated: $total_hours hours</b><br>";
    } else {
        echo "No dialer data found for this user/date.";
    }
}
?>