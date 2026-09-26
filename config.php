<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'atten');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('SITE_NAME', 'Attendance Management System');
define('SITE_URL', 'http://localhost/attendance_system/');
define('TIMEZONE', 'Asia/Kolkata');
date_default_timezone_set(TIMEZONE);

ob_start(); // Add this line
session_start();

date_default_timezone_set('Asia/Kolkata');
// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Common functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function hasRole($roles) {
    if (!isLoggedIn()) return false;
    $user = getCurrentUser();
    return in_array($user['role'], (array)$roles);
}

function getWeekNumber($date) {
    return date('W', strtotime($date));
}

function getDayOfWeek($date) {
    return date('l', strtotime($date));
}

function calculateWorkingHours($check_in, $check_out) {
    if (!$check_in || !$check_out) return 0;
    
    $in = new DateTime($check_in);
    $out = new DateTime($check_out);
    $diff = $in->diff($out);
    return round($diff->h + ($diff->i / 60), 2);
}

function calculateOT($working_hours, $required_hours = 9) {
    if ($working_hours > $required_hours) {
        return round($working_hours - $required_hours, 2);
    }
    return 0;
}

function getLateMinutes($check_in, $shift_start) {
    if (!$check_in) return 0;
    
    $in = new DateTime($check_in);
    $start = new DateTime($shift_start);
    
    if ($in > $start) {
        $diff = $start->diff($in);
        return round($diff->h * 60 + $diff->i, 2);
    }
    return 0;
}

function getEarlyMinutes($check_out, $shift_end) {
    if (!$check_out) return 0;
    
    $out = new DateTime($check_out);
    $end = new DateTime($shift_end);
    
    if ($out < $end) {
        $diff = $out->diff($end);
        return round($diff->h * 60 + $diff->i, 2);
    }
    return 0;
}

function getHierarchy($userId) {
    global $pdo;

    $hierarchy = [];
    $visited = [];
    $queue = [$userId];

    try {
        $stmt = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'is_active'");
        $hasIsActiveColumn = (bool) $stmt->fetch();
    } catch (PDOException $e) {
        $hasIsActiveColumn = false;
    }

    while (!empty($queue)) {
        $currentId = array_shift($queue);
        if (isset($visited[$currentId])) {
            continue;
        }
        $visited[$currentId] = true;

        $sql = "SELECT id, full_name, role, department, position, reporting_to FROM users WHERE reporting_to = ?";
        if ($hasIsActiveColumn) {
            $sql .= " AND is_active = 1";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentId]);
        $children = $stmt->fetchAll();

        foreach ($children as $child) {
            $hierarchy[] = $child;
            $queue[] = $child['id'];
        }
    }

    return $hierarchy;
}

function tableExists($tableName) {
    global $pdo;

    try {
        $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$tableName' LIMIT 1");
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function columnExists($tableName, $columnName) {
    global $pdo;

    try {
        $stmt = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$tableName' AND column_name = '$columnName' LIMIT 1");
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function serveProtectedPDF($file_path, $file_name) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
    
    // Check if file exists
    if (!file_exists($file_path)) {
        die('File not found');
    }
    
    // Serve the file with headers to prevent download
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $file_name . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Disable caching
    header('Expires: 0');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    
    // Read file
    readfile($file_path);
    exit();
}
?>