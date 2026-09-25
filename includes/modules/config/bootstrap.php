<?php
// ============================================
// BOOTSTRAP - Configuration & Database
// ============================================

// Load main config (all core functions are already here)
require_once __DIR__ . '/../../../config.php';

// Session is already started in config.php with session_start()
// No need to start it again

// ============================================
// ADDITIONAL HELPER FUNCTIONS NOT IN CONFIG.PHP
// ============================================

function getVerveConnection() {
    $host = '192.168.8.17'; 
    $port = '5433';      // PostgreSQL default port 5432 hota hai
    $db   = 'verve';  // Dialer DB Name
    $user = 'postgres'; 
    $pass = 'Avis!123';

    try {
        // DSN changed to pgsql
        $dsn = "pgsql:host=$host;port=$port;dbname=$db";
        $pdo_vici = new PDO($dsn, $user, $pass, [
            PDO::ATTR_TIMEOUT => 5, 
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        return $pdo_vici;
    } catch (Exception $e) {
        error_log(" NCA Dialer Connection Error: " . $e->getMessage());
        return null;
    }
}

function getAmazonConnection() {
    try {
        return new PDO("pgsql:host=192.168.8.132;port=5433;dbname=verve", "postgres", "Avis!123");
    } catch (Exception $e) {
        return null; // Return null if Amazon server is down
    }
}
// New connection for the Tata Process Dialer
function getTataConnection() {
    $host = '192.168.8.160'; 
    $port = '5432';         
    $db   = 'verve';     
    $user = 'postgres';         
    $pass = 'Avis!123';         

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$db";
        $pdo_vici = new PDO($dsn, $user, $pass, [
            PDO::ATTR_TIMEOUT => 5, 
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        return $pdo_vici;
    } catch (Exception $e) {
        error_log("tata Dialer Connection Error: " . $e->getMessage());
        return null;
    }
}




// New connection for the Upgrade Process Dialer
function getUpgradeConnection() {
    $host = '172.16.1.76'; 
    $port = '5432';         
    $db   = 'verve';     
    $user = 'postgres';         
    $pass = 'Avis!123';         

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$db";
        $pdo_vici = new PDO($dsn, $user, $pass, [
            PDO::ATTR_TIMEOUT => 5, 
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        return $pdo_vici;
    } catch (Exception $e) {
        error_log("Upgrade Dialer Connection Error: " . $e->getMessage());
        return null;
    }
}
function getDCConnection() {
    try {
        return new PDO("pgsql:host=172.16.1.81;port=5432;dbname=verve", "postgres", "Avis!123");
    } catch (Exception $e) {
        return null; // Return null if DC server is down
    }
}
function getRTOConnection() {
    try {
        return new PDO("mysql:host=172.16.1.16;port=3306;dbname=asterisk", "root", "s@mp@rk@123");
    } catch (Exception $e) {
        return null; 
    }
}

function getPlccConnection() {
    try {
        return new PDO("pgsql:host=172.16.1.82;port=5433;dbname=verve", "postgres", "Avis!123");
    } catch (Exception $e) {
        return null; // Return null if PLCC server is down
    }
}

// Get status display function - NOT in config.php
function getStatusDisplay($status, $exception_type = null) {
    $status = trim($status);
    $status_lower = strtolower($status);

    if ($exception_type) {
        $exception_map = [
            'full_day' => ['code' => 'EFD', 'class' => 'exception-full-day'],
            'half_day' => ['code' => 'EHD', 'class' => 'exception-half-day'],
            'late' => ['code' => 'EL', 'class' => 'exception-late'],
            'pl_adjustment' => ['code' => 'EPL', 'class' => 'exception-pl']
        ];
        if (isset($exception_map[$exception_type])) {
            return $exception_map[$exception_type];
        }
    }

    $display_map = [
        'present' => ['code' => 'P', 'class' => 'present'],
        'p' => ['code' => 'P', 'class' => 'present'],
        'late' => ['code' => 'L', 'class' => 'late'],
        'l' => ['code' => 'L', 'class' => 'late'],
        'absent' => ['code' => 'A', 'class' => 'absent'],
        'a' => ['code' => 'A', 'class' => 'absent'],
        'weekoff' => ['code' => 'WO', 'class' => 'weekoff'],
        'week off' => ['code' => 'WO', 'class' => 'weekoff'],
        'wo' => ['code' => 'WO', 'class' => 'weekoff'],
        'holiday' => ['code' => 'H', 'class' => 'holiday'],
        'h' => ['code' => 'H', 'class' => 'holiday'],
        'leave' => ['code' => 'LV', 'class' => 'leave'],
        'lv' => ['code' => 'LV', 'class' => 'leave'],
        'pl' => ['code' => 'PL', 'class' => 'leave'],
        'personal leave' => ['code' => 'PL', 'class' => 'leave'],
        'half day' => ['code' => 'HD', 'class' => 'half-day'],
        'half-day' => ['code' => 'HD', 'class' => 'half-day'],
        'hd' => ['code' => 'HD', 'class' => 'half-day'],
        'coming late' => ['code' => 'CL', 'class' => 'coming-late'],
        'coming-late' => ['code' => 'CL', 'class' => 'coming-late'],
        'cl' => ['code' => 'CL', 'class' => 'coming-late'],
        'lwp' => ['code' => 'LWP', 'class' => 'absent'],
        'fpe' => ['code' => 'FPE', 'class' => 'present'],
        'hpe' => ['code' => 'HPE', 'class' => 'present'],
        'ph' => ['code' => 'PH', 'class' => 'holiday']
    ];

    if (isset($display_map[$status_lower])) {
        return $display_map[$status_lower];
    }

    foreach ($display_map as $key => $value) {
        if (strpos($status_lower, $key) !== false) {
            return $value;
        }
    }

    return ['code' => strtoupper(substr($status, 0, 2)), 'class' => 'present'];
}


// ============================================
// COMPLETE REPLACEMENT - NO HIERARCHY
// ============================================

function getApproverId($user, $pdo) {
    // ===== DIRECT TO PROCESS HEAD - NO HIERARCHY =====
    // This function ALWAYS returns the Process Head
    
    try {
        // Step 1: Get the main Process Head
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'process_head' AND is_active = 1 LIMIT 1");
        $stmt->execute();
        $process_head = $stmt->fetch();
        
        if ($process_head) {
            return $process_head['id'];
        }
        
        // Step 2: If no Process Head, get Centre Head
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'centre_head' AND is_active = 1 LIMIT 1");
        $stmt->execute();
        $centre_head = $stmt->fetch();
        
        if ($centre_head) {
            return $centre_head['id'];
        }
        
        // Step 3: If no Centre Head, get HR
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'hr' AND is_active = 1 LIMIT 1");
        $stmt->execute();
        $hr = $stmt->fetch();
        
        if ($hr) {
            return $hr['id'];
        }
        
        // Step 4: If no one found, return null
        return null;
        
    } catch (PDOException $e) {
        error_log("Error in getApproverId: " . $e->getMessage());
        return null;
    }
}


function isHR() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'hr');
}

function hasFullAccess() {
    // Standardize input casing
    $role = strtolower($_SESSION['role'] ?? '');
    $person_id = strtoupper($_SESSION['person_id'] ?? '');

    // Allow Admin and HR
    if ($role === 'admin' || $role === 'hr') {
        return true;
    }

    // ONLY allow Centre Head if the ID matches FNM001 exactly
    if ($role === 'centre_head' && $person_id === 'FNM001') {
        return true;
    }

    // Denies all others (e.g., FNM0102)
    return false;
}

/**
 * Logic: Block HR from Attendance/PL uploads.
 * Allow Admin and Centre Head 'FNM001'.
 */
function canUploadAttendance() {
    $role = strtolower($_SESSION['role'] ?? '');
    $person_id = strtoupper($_SESSION['person_id'] ?? '');

    // Restricted for HR
    if ($role === 'hr') {
        return false;
    }

    // Allowed for Admin or the specific Master Centre Head
    if ($role === 'admin' || ($role === 'centre_head' && $person_id === 'FNM001')) {
        return true;
    }

    return false;
}

function isManagementRole() {
    $role = strtolower($_SESSION['role'] ?? '');
    $mgmt_roles = ['hr', 'admin', 'centre_head', 'process_head', 'manager', 'am', 'tl'];
    return in_array($role, $mgmt_roles);
}// Ensure the HR role is integrated into the approver logic
// Modify your existing getApproverId function slightly:

?>