<?php
// history_functions.php

function getDetailedHistory($pdo, $uid, $pid) {
    $results = [];

    $tables = [
        [
            'name' => 'late_exception_requests', 
            'type' => "COALESCE(NULLIF(t.exception_type, ''), 'Exception')",
            'date_col' => "t.date",
            'approver_logic' => "COALESCE(t.approved_by, t.rejected_by)"
        ],
        [
            'name' => 'leave_requests', 
            'type' => "COALESCE(NULLIF(t.leave_type, ''), NULLIF(t.request_type, ''), 'Leave')",
            'date_col' => "t.date",
            'approver_logic' => "COALESCE(t.approved_by, t.rejected_by)"
        ],
        [
            'name' => 'roster_requests', 
            'type' => "'Roster'",
            'date_col' => "t.date",
            'approver_logic' => "COALESCE(t.approved_by, t.rejected_by)"
        ],
        [
            'name' => 'early_leave_requests', 
            'type' => "'Early Leave'",
            'date_col' => "t.date",
            'approver_logic' => "t.approved_by" 
        ],
        [
            'name' => 'overtime_requests', 
            // This will show "OVERTIME (2h)" if ot_hours is 2
            'type' => "CONCAT('Overtime (', t.ot_hours, 'h)')", 
            'date_col' => "t.date", // Matches your DB screenshot #4
            'approver_logic' => "t.approved_by" // You confirmed this exists
        ]
    ];

    foreach ($tables as $t) {
        $tableName = $t['name'];
        $typeCol = $t['type'];
        $dateCol = $t['date_col'];
        $approverLogic = $t['approver_logic'];
        
        try {
            $sql = "SELECT t.*, 
                           $typeCol AS display_type, 
                           t.status as final_status,
                           -- Use date from table, fallback to created_at
                           COALESCE($dateCol, t.created_at) as action_date,
                           app.full_name as action_by_name
                    FROM $tableName t 
                    LEFT JOIN users app ON ($approverLogic = app.id)
                    WHERE (t.user_id = :uid OR t.person_id = :pid) 
                    AND (LOWER(t.status) = 'approved' OR LOWER(t.status) = 'rejected')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid' => $uid, ':pid' => $pid]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            error_log("Error in $tableName: " . $e->getMessage());
        }
    }

    // Sort: Newest Date First
    usort($results, function($a, $b) {
        return strtotime($b['action_date'] ?? '0') - strtotime($a['action_date'] ?? '0');
    });

    return $results;
}