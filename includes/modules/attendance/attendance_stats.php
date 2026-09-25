<?php
// ============================================
// ATTENDANCE STATISTICS FUNCTIONS
// ============================================
date_default_timezone_set('Asia/Kolkata');
function getAttendanceRecords($pdo, $person_id, $year, $month) {
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE person_id = ? AND YEAR(date) = ? AND MONTH(date) = ? ORDER BY date ASC");
    $stmt->execute([$person_id, $year, $month]);
    return $stmt->fetchAll();
}

function buildAttendanceMap($records) {
    $map = [];
    foreach ($records as $record) {
        $day = date('j', strtotime($record['date']));
        $record['exception_type'] = $record['exception_type'] ?? null;
        $map[$day] = $record;
    }
    return $map;
}

/**
 * OT Eligibility Check - FULL HOURS ONLY, 1 DAY VALIDITY
 */
function getOTEligibility($pdo, $work_hours, $date, $role, $check_validity = true) {
    // Safety check: if date is missing or work is 0, they aren't eligible
    if (!$date || !$work_hours || $work_hours <= 0) {
        return ['eligible' => false, 'ot_hours' => 0, 'days_diff' => 0, 'reason' => 'No work hours recorded'];
    }

    // Define threshold based on role (EXISTING LOGIC UNCHANGED)
    $threshold = ($role === 'process_head' || $role === 'centre_head') ? 8 : 9;
    $extra_time = $work_hours - $threshold;
    
    // IMPORTANT: Only full hours count (1, 2, 3, etc.) (EXISTING LOGIC UNCHANGED)
    $full_ot_hours = floor($extra_time);
    $is_eligible_hours = ($full_ot_hours >= 1);
    
    $is_within_time = true;
    $days_diff = 0;
    
    if ($check_validity) {
        try {
            $work_date = new DateTime($date);
            $today = new DateTime(date('Y-m-d'));
            
            // --- START OF SMART VALIDITY LOGIC ---
            
            // We start checking from the day after the work date
            $expiry_check = clone $work_date;
            $expiry_check->modify('+1 day');
            
            // Loop to find the valid "Deadline Day"
            // Condition: Skip if it's a Sunday OR a Paid Holiday
            // Note: If you work Sat, Sunday is skipped, landing on Monday.
            // Note: If you work and tomorrow is PH, PH is skipped, landing on next day.
            for ($i = 0; $i < 7; $i++) { // Limit loop to 7 days safety
                $check_str = $expiry_check->format('Y-m-d');
                $day_of_week = (int)$expiry_check->format('N'); // 7 = Sunday
                
                // Check database for holiday
                $stmt = $pdo->prepare("SELECT id FROM holidays WHERE holiday_date = ?");
                $stmt->execute([$check_str]);
                $is_holiday = $stmt->fetch();

                if ($day_of_week == 7 || $is_holiday) {
                    $expiry_check->modify('+1 day');
                    continue;
                }
                
                // If we reach here, we found the valid working day to set as deadline
                break;
            }
            
            $expiry_date = $expiry_check->format('Y-m-d');
            $today_str = $today->format('Y-m-d');
            
            // Check if work date is in future
            $is_future = ($work_date > $today);
            // Valid if today is less than or equal to the calculated expiry date
            $is_within_time = ($today_str <= $expiry_date && !$is_future);
            
            if ($is_future) {
                return ['eligible' => false, 'ot_hours' => 0, 'reason' => 'Cannot request OT for future dates'];
            }
            
            if (!$is_within_time) {
                return ['eligible' => false, 'ot_hours' => 0, 'reason' => 'OT request expired (was valid until ' . date('M d', strtotime($expiry_date)) . ')'];
            }
            
            // --- END OF SMART VALIDITY LOGIC ---

        } catch (Exception $e) {
            return ['eligible' => false, 'ot_hours' => 0, 'reason' => 'Invalid date'];
        }
    }

    if (!$is_eligible_hours) {
        $reason = 'Need at least 1 full hour of OT (Current: ' . number_format($extra_time, 2) . 'h extra)';
        return ['eligible' => false, 'ot_hours' => 0, 'reason' => $reason];
    }

    return [
        'eligible' => ($is_eligible_hours && $is_within_time),
        'ot_hours' => $full_ot_hours, 
        'extra_minutes' => ($extra_time - $full_ot_hours) * 60,
        'threshold' => $threshold,
        'total_work' => $work_hours,
        'reason' => 'Eligible for ' . $full_ot_hours . ' hour(s) OT'
    ];
}

/**
 * Calculates the expiry date for an OT request based on holidays and weekends.
 */
function getOTExpiryDate($pdo, $work_date_str) {
    $work_date = new DateTime($work_date_str);
    $check_date = clone $work_date;
    
    // We start looking from the day after the work was performed
    $check_date->modify('+1 day');
    
    // Loop to find the next "Working Day"
    // A working day = Not Saturday, Not Sunday, and Not a Holiday in DB
    $max_iterations = 10; // Safety break
    $i = 0;
    
    while ($i < $max_iterations) {
        $current_str = $check_date->format('Y-m-d');
        $day_of_week = (int)$check_date->format('N'); // 1=Mon, 6=Sat, 7=Sun
        
        // Check if this date is a holiday in the holidays table
        $stmt = $pdo->prepare("SELECT id FROM holidays WHERE holiday_date = ?");
        $stmt->execute([$current_str]);
        $is_holiday = $stmt->fetch();

        // Condition: Skip if it's a weekend (Sat/Sun) OR if it's a holiday
        if ($day_of_week == 6 || $day_of_week == 7 || $is_holiday) {
            $check_date->modify('+1 day');
            $i++;
            continue;
        }
        
        // If we reach here, we found the first valid working day
        break;
    }
    
    return $check_date->format('Y-m-d');
}


function calculateMonthlyStats($attendance_map, $year, $month, $pdo = null) {
    $p_cnt = 0;        
    $l_cnt = 0;        
    $a_cnt = 0;        
    $pl_cnt = 0;       
    $hd_cnt = 0;       
    $ex_cnt = 0;       
    $ph_cnt = 0;       
    $scheduled_cnt = 0; 
    $hours = 0;
    $today_str = date('Y-m-d');
    $total_days_in_month = date('t', strtotime("$year-$month-01"));
    
    $holidays = [];
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT holiday_date FROM holidays WHERE MONTH(holiday_date) = ? AND YEAR(holiday_date) = ?");
            $stmt->execute([$month, $year]);
            $holidays = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}
    }

    for ($d = 1; $d <= $total_days_in_month; $d++) {
        $date_loop = "$year-$month-" . str_pad($d, 2, '0', STR_PAD_LEFT);
        if ($date_loop > $today_str) continue;
        $day_of_week = date('w', strtotime($date_loop));
        $is_sunday = ($day_of_week == 0);
        $is_holiday = in_array($date_loop, $holidays);

        if (isset($attendance_map[$d])) {
            $record = $attendance_map[$d];
            $st = strtoupper(trim((string)($record['status'] ?? '')));
            $ex_type = $record['exception_type'] ?? null;
            $hours += (float)($record['work'] ?? 0);

            // 1. PAID HOLIDAY (Sabse pehle)
            if ($st === 'PH' || $st === 'PHD' || $st === 'HOLIDAY') {
                $ph_cnt++;
            }
            // 2. FPE MAPPING TO PRESENT (Priority Fix)
            // Humein ise general Exception se pehle check karna hoga
            elseif ($st === 'FPE' || $st === 'PRESENT' || $st === 'P') {
                $p_cnt++;
            }
            // 3. LEAVES
            elseif ($st === 'HPL') {
                $pl_cnt += 0.5;
            }
            elseif (strpos($st, 'PL') !== false || strpos($st, 'LEAVE') !== false || strpos($st, 'LV') !== false) {
                $pl_cnt += 1.0;
            }
            // 4. SCHEDULED
            elseif (strpos($st, 'SCHEDULED') !== false || $st === 'SCHEDULED') {
                $scheduled_cnt++;
            }
            // 5. HALF DAY (HPE ko bhi HD ya EX mein rakh sakte hain)
            elseif ($st === 'HD' || $st === 'HPE' || strpos($st, 'HALF') !== false) {
                $hd_cnt++;
            }
            // 6. GENERAL EXCEPTIONS (Sirf wo jo upar filter nahi huye, jaise Late Entry exceptions)
            elseif (!empty($ex_type) || strpos($st, 'EXCEPTION') !== false) {
                $ex_cnt++;
            }
            // 7. LATE
            elseif (strpos($st, 'LATE') !== false || $st === 'CL' || $st === 'L') {
                $l_cnt++;
            }
            // 8. ABSENT
            elseif (($st === 'A' || $st === 'ABSENT' || $st === 'LWP') && $st !== 'PH') {
                $a_cnt++;
            }
        } else {
            if ($is_holiday) {
                $ph_cnt++;
            } elseif (!$is_sunday && $date_loop < $today_str) {
                $a_cnt++;
            }
        }
    }

    return [
        'present_days' => (float)$p_cnt,
        'late_days'    => (float)$l_cnt,
        'absent_days'  => (float)$a_cnt,
        'leave_days'   => (float)$pl_cnt, 
        'half_day_days'=> (float)$hd_cnt,
        'exception_days' => (float)$ex_cnt,
        'holiday_days' => (float)$ph_cnt,
        'scheduled_days' => (float)$scheduled_cnt,
        'total_working_hours' => round($hours, 1)
    ];
}

function getPendingOTRequests($pdo, $person_id = null) {
    try {
        // First check if the table exists
        $check_table = $pdo->query("SHOW TABLES LIKE 'overtime_requests'");
        if ($check_table->rowCount() == 0) {
            return []; // Table doesn't exist yet
        }
        
        $sql = "SELECT or.*, u.full_name, u.person_id 
                FROM overtime_requests or
                JOIN users u ON or.user_id = u.id
                WHERE or.status = 'pending'
                ORDER BY or.created_at ASC";
        
        if ($person_id) {
            $sql .= " AND u.person_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$person_id]);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getUserOTRequests($pdo, $user_id) {
    try {
        $check_table = $pdo->query("SHOW TABLES LIKE 'overtime_requests'");
        if ($check_table->rowCount() == 0) {
            return [];
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM overtime_requests 
            WHERE user_id = ? 
            ORDER BY date DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function hasOTRequestForDate($pdo, $user_id, $date) {
    try {
        $check_table = $pdo->query("SHOW TABLES LIKE 'overtime_requests'");
        if ($check_table->rowCount() == 0) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            SELECT id, status FROM overtime_requests 
            WHERE user_id = ? AND date = ?
        ");
        $stmt->execute([$user_id, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

function calculateApprovedOT($pdo, $user_id, $year, $month) {
    try {
        $check_table = $pdo->query("SHOW TABLES LIKE 'overtime_requests'");
        if ($check_table->rowCount() == 0) {
            return 0;
        }
        
        $stmt = $pdo->prepare("
            SELECT SUM(ot_hours) as total 
            FROM overtime_requests 
            WHERE user_id = ? 
            AND YEAR(date) = ? 
            AND MONTH(date) = ? 
            AND status = 'approved'
        ");
        $stmt->execute([$user_id, $year, $month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)($result['total'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Create the overtime_requests table if it doesn't exist
 */
function createOTRequestsTable($pdo) {
    $sql = "
    CREATE TABLE IF NOT EXISTS `overtime_requests` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `person_id` varchar(50) NOT NULL,
        `date` date NOT NULL,
        `work_hours` decimal(5,2) NOT NULL,
        `ot_hours` int(11) NOT NULL COMMENT 'Only full hours (1,2,3...)',
        `reason` text NOT NULL,
        `status` enum('pending','approved','rejected') DEFAULT 'pending',
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `approved_at` datetime DEFAULT NULL,
        `rejected_at` datetime DEFAULT NULL,
        `approved_by` int(11) DEFAULT NULL,
        `rejection_reason` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_ot_request` (`user_id`, `date`),
        KEY `idx_user_date` (`user_id`, `date`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $pdo->exec($sql);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Format hours to HH:MM format - Only define if not already defined
 */
if (!function_exists('formatWorkHours')) {
    function formatWorkHours($hours) {
        if ($hours <= 0) {
            return '00:00';
        }
        
        $whole_hours = floor($hours);
        $minutes = round(($hours - $whole_hours) * 60);
        
        if ($minutes >= 60) {
            $whole_hours += 1;
            $minutes = 0;
        }
        
        return sprintf("%02d:%02d", $whole_hours, $minutes);
    }
}

/**
 * Get overtime data for a user
 */
function getOvertimeData($pdo, $person_id, $year, $month, $role) {
    try {
        // Check table exists
        $check_table = $pdo->query("SHOW TABLES LIKE 'overtime_requests'");
        if ($check_table->rowCount() == 0) {
            return ['total_ot' => 0, 'details' => []];
        }
        
        // Get user_id from person_id
        $user_stmt = $pdo->prepare("SELECT id FROM users WHERE person_id = ?");
        $user_stmt->execute([$person_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['total_ot' => 0, 'details' => []];
        }
        
        $user_id = $user['id'];
        
        // Get approved OT requests
        $stmt = $pdo->prepare("
            SELECT * FROM overtime_requests 
            WHERE user_id = ? 
            AND YEAR(date) = ? 
            AND MONTH(date) = ? 
            AND status = 'approved'
            ORDER BY date ASC
        ");
        $stmt->execute([$user_id, $year, $month]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate total
        $total_ot = 0;
        foreach ($details as $detail) {
            $total_ot += (float)($detail['ot_hours'] ?? 0);
        }
        
        return [
            'total_ot' => $total_ot,
            'details' => $details
        ];
    } catch (Exception $e) {
        return ['total_ot' => 0, 'details' => []];
    }
}

/**
 * Get total overtime hours for a user
 */
function getTotalOvertime($pdo, $person_id, $year, $month, $role) {
    $data = getOvertimeData($pdo, $person_id, $year, $month, $role);
    return $data['total_ot'];
}
?>