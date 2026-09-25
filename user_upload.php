<?php
// upload_users.php - Bulk User Upload with Bright White Theme
require_once 'config.php';

if (!isLoggedIn() || !hasRole('centre_head')) {
    redirect('index.php');
}

$user = getCurrentUser();
$message = '';
$uploaded_count = 0;
$errors = [];
$debug_info = [];

// Define valid roles with proper formatting
$valid_roles = ['centre_head', 'process_head', 'manager', 'am', 'tl', 'agent'];

// Role mapping for display names to system values
$role_mapping = [
    'centre_head' => 'centre_head',
    'process_head' => 'process_head',
    'manager' => 'manager',
    'am' => 'am',
    'tl' => 'tl',
    'agent' => 'agent',
    'centre head' => 'centre_head',
    'process head' => 'process_head',
    'manager' => 'manager',
    'team leader' => 'tl',
    'team lead' => 'tl',
    'tl' => 'tl',
    'assistant manager' => 'am',
    'am' => 'am',
    'agent' => 'agent',
    'support' => 'agent',
];

$role_display = [
    'centre_head' => 'Centre Head',
    'process_head' => 'Process Head',
    'manager' => 'Manager',
    'am' => 'Assistant Manager',
    'tl' => 'Team Leader',
    'agent' => 'Agent'
];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['user_file'])) {
    $file = $_FILES['user_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = '<div class="message message-error">Error uploading file. Please try again.</div>';
    } else {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['csv', 'xlsx', 'xls'];
        
        if (!in_array($file_ext, $allowed_ext)) {
            $message = '<div class="message message-error">Invalid file type. Please upload CSV, XLSX, or XLS files only.</div>';
        } else {
            try {
                $data = [];
                
                if ($file_ext === 'csv') {
                    $handle = fopen($file['tmp_name'], 'r');
                    if ($handle !== false) {
                        $headers = fgetcsv($handle);
                        
                        $debug_info[] = "Headers found: " . implode(', ', $headers);
                        
                        if (!$headers || empty($headers[0])) {
                            $message = '<div class="message message-error">The CSV file appears to be empty or has no headers.</div>';
                            fclose($handle);
                        } else {
                            $headers = array_map(function($h) {
                                $h = trim($h);
                                if (strpos($h, "\xEF\xBB\xBF") === 0) {
                                    $h = substr($h, 3);
                                }
                                return $h;
                            }, $headers);
                            
                            $required_headers = ['person_id', 'username', 'password', 'full_name', 'email', 'department', 'position', 'role', 'process', 'reporting_to', 'shift_id', 'is_active'];
                            $header_match = true;
                            $missing_headers = [];
                            foreach ($required_headers as $req) {
                                if (!in_array($req, $headers)) {
                                    $header_match = false;
                                    $missing_headers[] = $req;
                                }
                            }
                            
                            if (!$header_match) {
                                $message = '<div class="message message-error">Invalid CSV format. Missing headers: ' . implode(', ', $missing_headers) . '. Please use the template provided.</div>';
                            } else {
                                $row_count = 0;
                                while (($row = fgetcsv($handle)) !== false) {
                                    $row_count++;
                                    if (empty(array_filter($row))) continue;
                                    
                                    if (count($row) < count($headers)) {
                                        $errors[] = "Row " . ($row_count + 1) . ": Row has " . count($row) . " columns but headers have " . count($headers) . " columns. Skipping.";
                                        continue;
                                    }
                                    
                                    if (count($row) > count($headers)) {
                                        $row = array_slice($row, 0, count($headers));
                                    }
                                    
                                    $data[] = array_combine($headers, $row);
                                }
                                
                                $debug_info[] = "Total rows found: " . $row_count;
                                $debug_info[] = "Valid data rows: " . count($data);
                                
                                fclose($handle);
                            }
                        }
                    } else {
                        $message = '<div class="message message-error">Could not open the CSV file.</div>';
                    }
                } else {
                    $message = '<div class="message message-error">Please upload CSV files only. For Excel files, please use the CSV template.</div>';
                }
                
                if (!empty($data)) {
                    foreach ($data as $index => $row) {
                        $row_num = $index + 2;
                        
                        if ($index < 3) {
                            $debug_info[] = "Row " . $row_num . " data: " . json_encode($row);
                        }
                        
                        if (empty($row['person_id']) || empty($row['full_name']) || empty($row['username'])) {
                            $errors[] = "Row " . $row_num . ": Missing required fields (person_id, full_name, username)";
                            continue;
                        }
                        
                        $check = $pdo->prepare("SELECT id FROM users WHERE person_id = ?");
                        $check->execute([$row['person_id']]);
                        if ($check->fetch()) {
                            $errors[] = "Row " . $row_num . ": Person ID '{$row['person_id']}' already exists.";
                            continue;
                        }
                        
                        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                        $check->execute([$row['username']]);
                        if ($check->fetch()) {
                            $errors[] = "Row " . $row_num . ": Username '{$row['username']}' already exists.";
                            continue;
                        }
                        
                        if (!empty($row['email'])) {
                            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                            $check->execute([$row['email']]);
                            if ($check->fetch()) {
                                $errors[] = "Row " . $row_num . ": Email '{$row['email']}' already exists.";
                                continue;
                            }
                        }
                        
                        $role_input = trim($row['role']);
                        $role_key = strtolower($role_input);
                        
                        $normalized_role = null;
                        if (isset($role_mapping[$role_key])) {
                            $normalized_role = $role_mapping[$role_key];
                        } else {
                            $found = false;
                            foreach ($role_mapping as $key => $value) {
                                if (strpos($role_key, $key) !== false || strpos($key, $role_key) !== false) {
                                    $normalized_role = $value;
                                    $found = true;
                                    break;
                                }
                            }
                            
                            if (!$found) {
                                $errors[] = "Row " . $row_num . ": Invalid role '{$role_input}'. Accepted roles: Centre Head, Process Head, Manager, Assistant Manager, Team Leader, Agent, Support";
                                continue;
                            }
                        }
                        
                        $password = !empty($row['password']) ? $row['password'] : 'password123';
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        $temp_reporting_id = !empty($row['reporting_to']) ? trim($row['reporting_to']) : null;
                        $shift_id = null;
                        if (!empty($row['shift_id'])) {
                            $stmt = $pdo->prepare("SELECT id FROM shifts WHERE id = ?");
                            $stmt->execute([$row['shift_id']]);
                            if ($stmt->fetch()) {
                                $shift_id = $row['shift_id'];
                            } else {
                                $errors[] = "Row " . $row_num . ": Shift ID '{$row['shift_id']}' not found.";
                                continue;
                            }
                        }
                        
                        $is_active = isset($row['is_active']) ? (int)$row['is_active'] : 1;
                        
                        try {
                            $stmt = $pdo->prepare("
                                INSERT INTO users (
                                    person_id, username, password, full_name, email, 
                                    department, position, role, process, temp_reporting_id, 
                                    shift_id, is_active, created_at
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $row['person_id'],
                                $row['username'],
                                $password_hash,
                                $row['full_name'],
                                $row['email'] ?? null,
                                $row['department'] ?? null,
                                $row['position'] ?? null,
                                $normalized_role,
                                $row['process'] ?? null,
                                $temp_reporting_id,
                                $shift_id,
                                $is_active
                            ]);
                            $uploaded_count++;
                        } catch (PDOException $e) {
                            $errors[] = "Row " . $row_num . ": " . $e->getMessage();
                        }
                    }
                }
                
                if ($uploaded_count > 0) {
                    $pdo->query("
                        UPDATE users u
                        INNER JOIN users m ON u.temp_reporting_id = m.person_id
                        SET u.reporting_to = m.id
                        WHERE u.temp_reporting_id IS NOT NULL
                    ");
                    $pdo->query("UPDATE users SET temp_reporting_id = NULL");
                }
                
            } catch (Exception $e) {
                $message = '<div class="message message-error">Error processing file: ' . $e->getMessage() . '</div>';
                $debug_info[] = "Exception: " . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Users - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ===== PROFESSIONAL BRIGHT WHITE THEME ===== */
        :root {
            --white: #ffffff;
            --bg-light: #f4f6fa;
            --bg-card: #ffffff;
            --text-primary: #0a1628;
            --text-secondary: #1e293b;
            --text-muted: #64748b;
            --border-light: #e8edf4;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 8px 28px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.06);
            --radius: 16px;
            --radius-sm: 10px;
            
            --blue: #2563eb;
            --blue-hover: #1d4ed8;
            --blue-light: #dbeafe;
            --blue-bg: rgba(37, 99, 235, 0.06);
            
            --green: #16a34a;
            --green-light: #dcfce7;
            --green-bg: rgba(22, 163, 74, 0.06);
            
            --red: #dc2626;
            --red-light: #fee2e2;
            --red-bg: rgba(220, 38, 38, 0.06);
            
            --yellow: #d97706;
            --yellow-light: #fef3c7;
            --yellow-bg: rgba(217, 119, 6, 0.06);
            
            --purple: #7c3aed;
            --purple-light: #ede9fe;
            --purple-bg: rgba(124, 58, 237, 0.06);
            
            --cyan: #0891b2;
            --cyan-light: #cffafe;
            --cyan-bg: rgba(8, 145, 178, 0.06);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f2f6;
            padding-top: 70px;
            color: var(--text-primary);
            min-height: 100vh;
        }

        .container { 
            max-width: 1200px; 
            margin: 20px auto; 
            padding: 0 24px;
            position: relative;
            z-index: 1;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .anim-fade-up {
            animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }
        .anim-delay-1 { animation-delay: 0.05s; }
        .anim-delay-2 { animation-delay: 0.1s; }
        .anim-delay-3 { animation-delay: 0.15s; }
        .anim-delay-4 { animation-delay: 0.2s; }

        /* ===== HEADER ===== */
        .header-bar {
            background: var(--white);
            border-radius: var(--radius);
            padding: 20px 28px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .header-bar:hover {
            box-shadow: var(--shadow-md);
        }
        
        .header-bar .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .header-bar .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), var(--purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            color: #fff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        
        .header-bar .greeting {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .header-bar h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .header-bar h1 i {
            color: var(--blue);
            margin-right: 8px;
        }
        
        .header-bar .sub-text {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
        }
        
        .header-bar .sub-text i {
            color: var(--blue);
            margin-right: 6px;
        }
        
        .header-bar .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-bar .role-badge {
            font-size: 12px;
            font-weight: 700;
            padding: 6px 18px;
            border-radius: 50px;
            background: linear-gradient(135deg, var(--blue), var(--purple));
            color: #fff;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
        }
        
        .back-btn {
            background: var(--bg-light);
            color: var(--text-secondary);
            border: 1px solid var(--border-light);
            padding: 6px 18px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-btn:hover {
            background: var(--blue-bg);
            border-color: var(--blue);
            color: var(--blue);
        }

        /* ===== CARD ===== */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 24px 28px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-light);
        }
        
        .card-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-header h2 i {
            color: var(--blue);
            font-size: 20px;
        }
        
        .card-header .badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 50px;
            background: var(--blue);
            color: #fff;
            letter-spacing: 0.5px;
        }

        /* ===== MESSAGES ===== */
        .message {
            padding: 14px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-weight: 600;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        
        .message-success { 
            background: var(--green-bg); 
            color: var(--green); 
            border-color: var(--green);
        }
        .message-error { 
            background: var(--red-bg); 
            color: var(--red); 
            border-color: var(--red);
        }
        .message i {
            font-size: 18px;
        }

        /* ===== UPLOAD AREA ===== */
        .upload-area {
            border: 2px dashed var(--border-light);
            padding: 50px 30px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            background: var(--bg-light);
            border-radius: var(--radius-sm);
        }
        
        .upload-area:hover {
            border-color: var(--blue);
            background: var(--blue-bg);
        }
        
        .upload-area i {
            font-size: 48px;
            color: var(--blue);
            margin-bottom: 16px;
            opacity: 0.6;
        }
        
        .upload-area h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        
        .upload-area p {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
        }
        
        .upload-area .file-types {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            margin-top: 20px;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-wrapper .file-label {
            background: linear-gradient(135deg, var(--blue), var(--purple));
            color: #fff;
            padding: 10px 30px;
            display: inline-block;
            cursor: pointer;
            transition: 0.3s;
            font-size: 14px;
            font-weight: 700;
            border-radius: 50px;
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
            letter-spacing: 0.5px;
        }
        
        .file-input-wrapper .file-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
        }
        
        .selected-file {
            margin-top: 20px;
            padding: 12px 20px;
            background: var(--bg-light);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            display: none;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .selected-file.show {
            display: flex;
        }
        
        .selected-file .file-name {
            font-weight: 700;
            color: var(--blue);
            font-size: 14px;
        }
        
        .selected-file .file-size {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 500;
        }
        
        .btn-upload {
            background: linear-gradient(135deg, var(--green), #22c55e);
            color: #fff;
            border: none;
            padding: 12px 40px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            transition: 0.3s;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 10px rgba(22, 163, 74, 0.2);
            margin-top: 15px;
            display: none;
        }
        
        .btn-upload:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 4px 20px rgba(22, 163, 74, 0.3);
        }
        
        .btn-upload.show {
            display: inline-block;
        }

        /* ===== TEMPLATE SECTION ===== */
        .template-section {
            background: var(--bg-light);
            border-radius: var(--radius-sm);
            padding: 20px;
            margin-top: 20px;
            border: 1px solid var(--border-light);
        }
        
        .template-section .template-title {
            color: var(--text-primary);
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .template-section .template-title i {
            color: var(--blue);
        }
        
        .template-section ul {
            list-style: none;
            padding: 0;
        }
        
        .template-section ul li {
            padding: 6px 0;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .template-section ul li:last-child {
            border-bottom: none;
        }
        
        .template-section ul li i {
            width: 16px;
            text-align: center;
            color: var(--blue);
        }
        
        .template-section ul li .required {
            color: var(--red);
            font-weight: 700;
            font-size: 11px;
        }
        
        .template-section ul li .optional {
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 600;
        }
        
        .table-header-info {
            background: var(--blue-bg);
            border: 1px solid var(--border-light);
            padding: 14px 18px;
            margin-bottom: 16px;
            border-radius: var(--radius-sm);
            border-left: 3px solid var(--blue);
        }
        
        .table-header-info code {
            background: rgba(37, 99, 235, 0.1);
            color: var(--blue);
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
        .btn-template {
            display: inline-block;
            background: var(--bg-light);
            border: 1px solid var(--border-light);
            color: var(--text-secondary);
            padding: 10px 25px;
            text-decoration: none;
            border-radius: 50px;
            margin-top: 10px;
            transition: 0.3s;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .btn-template:hover {
            background: var(--blue-bg);
            border-color: var(--blue);
            color: var(--blue);
        }
        
        .btn-template.green {
            border-color: rgba(22, 163, 74, 0.3);
            color: var(--green);
        }
        
        .btn-template.green:hover {
            background: var(--green-bg);
            border-color: var(--green);
        }
        
        .btn-template i {
            margin-right: 8px;
        }
        
        .template-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        /* ===== ERRORS ===== */
        .errors-list {
            margin-top: 16px;
            padding: 16px 20px;
            background: var(--red-bg);
            border: 1px solid rgba(220, 38, 38, 0.15);
            border-radius: var(--radius-sm);
            border-left: 3px solid var(--red);
        }
        
        .errors-list h4 {
            color: var(--red);
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .errors-list ul {
            list-style: none;
            padding: 0;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .errors-list ul::-webkit-scrollbar { width: 4px; }
        .errors-list ul::-webkit-scrollbar-track { background: var(--bg-light); border-radius: 4px; }
        .errors-list ul::-webkit-scrollbar-thumb { background: var(--red); border-radius: 4px; }
        
        .errors-list ul li {
            padding: 4px 0;
            font-size: 13px;
            font-weight: 500;
            color: var(--red);
            border-bottom: 1px solid rgba(220, 38, 38, 0.05);
        }
        
        .errors-list ul li:last-child {
            border-bottom: none;
        }
        
        .success-details {
            margin-top: 16px;
            padding: 16px 20px;
            background: var(--green-bg);
            border: 1px solid rgba(22, 163, 74, 0.15);
            border-radius: var(--radius-sm);
            border-left: 3px solid var(--green);
        }
        
        .success-details h4 {
            color: var(--green);
            margin-bottom: 4px;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .success-details p {
            color: var(--green);
            font-size: 14px;
            font-weight: 600;
        }

        /* ===== DEBUG ===== */
        .debug-info {
            background: var(--cyan-bg);
            border: 1px solid rgba(8, 145, 178, 0.15);
            border-radius: var(--radius-sm);
            padding: 16px 20px;
            margin-top: 16px;
            border-left: 3px solid var(--cyan);
        }
        
        .debug-info h4 {
            color: var(--cyan);
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .debug-info ul {
            list-style: none;
            padding: 0;
        }
        
        .debug-info ul li {
            padding: 4px 0;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
            font-family: 'Courier New', monospace;
            border-bottom: 1px solid rgba(8, 145, 178, 0.05);
        }
        
        .debug-info ul li:last-child {
            border-bottom: none;
        }

        /* ===== SAMPLE DATA ===== */
        .sample-data {
            background: var(--bg-light);
            padding: 16px 20px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-light);
            overflow-x: auto;
            margin-top: 12px;
        }
        
        .sample-data pre {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: var(--text-secondary);
            white-space: pre-wrap;
            line-height: 1.8;
            font-weight: 500;
        }
        
        .sample-data pre .highlight-cyan { color: var(--cyan); font-weight: 700; }
        .sample-data pre .highlight-green { color: var(--green); font-weight: 700; }
        .sample-data pre .highlight-orange { color: var(--yellow); font-weight: 700; }
        
        .sample-note {
            margin-top: 12px;
            padding: 12px 16px;
            background: var(--blue-bg);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            border-left: 3px solid var(--blue);
        }
        
        .sample-note p {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .sample-note code {
            color: var(--blue);
            background: rgba(37, 99, 235, 0.1);
            padding: 1px 8px;
            border-radius: 3px;
            font-weight: 600;
        }
        
        .close-sample-btn {
            margin-top: 0;
            padding: 4px 16px;
            background: var(--bg-light);
            border: 1px solid var(--border-light);
            color: var(--text-secondary);
            border-radius: 50px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .close-sample-btn:hover {
            background: var(--red-bg);
            border-color: var(--red);
            color: var(--red);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .container { padding: 0 16px; }
            .header-bar { flex-direction: column; text-align: center; gap: 12px; padding: 16px 20px; }
            .header-bar .header-left { flex-direction: column; }
            .header-bar .header-right { flex-wrap: wrap; justify-content: center; }
            .card { padding: 16px 18px; }
            .card-header { flex-direction: column; text-align: center; gap: 8px; }
            .card-header h2 { font-size: 16px; }
            .upload-area { padding: 30px 20px; }
            .upload-area i { font-size: 36px; }
            .upload-area h3 { font-size: 16px; }
            .selected-file { flex-direction: column; align-items: flex-start; }
            .template-actions { flex-direction: column; }
            .btn-template { width: 100%; text-align: center; }
            .file-input-wrapper .file-label { width: 100%; text-align: center; }
            .btn-upload { width: 100%; text-align: center; }
            .sample-data pre { font-size: 10px; }
        }
        
        @media (max-width: 480px) {
            .header-bar h1 { font-size: 18px; }
            .header-bar .greeting { font-size: 10px; }
            .card-header h2 { font-size: 14px; }
            .upload-area { padding: 20px 16px; }
            .upload-area i { font-size: 28px; }
            .upload-area h3 { font-size: 14px; }
            .upload-area p { font-size: 12px; }
            .table-header-info code { font-size: 10px; }
            .template-section ul li { font-size: 12px; }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header-bar anim-fade-up">
        <div class="header-left">
            <div class="avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
            <div>
                <div class="greeting"><?php echo strtoupper($user['role'] ?? 'User'); ?></div>
                <h1><i class="fas fa-upload"></i>BULK USER UPLOAD</h1>
                <div class="sub-text"><i class="fas fa-file-csv"></i> Import users in bulk using CSV</div>
            </div>
        </div>
        <div class="header-right">
            <span class="role-badge"><?php echo strtoupper(str_replace('_', ' ', $user['role'] ?? 'User')); ?></span>
            <a href="users.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> BACK TO USERS
            </a>
        </div>
    </div>

    <?php echo $message; ?>
    
    <?php if (!empty($errors)): ?>
    <div class="errors-list anim-fade-up anim-delay-1">
        <h4><i class="fas fa-exclamation-triangle"></i> UPLOAD ERRORS (<?php echo count($errors); ?>)</h4>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li>• <?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($debug_info)): ?>
    <div class="debug-info anim-fade-up anim-delay-1">
        <h4><i class="fas fa-bug"></i> DEBUG INFORMATION</h4>
        <ul>
            <?php foreach ($debug_info as $info): ?>
                <li>• <?php echo htmlspecialchars($info); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if ($uploaded_count > 0): ?>
    <div class="success-details anim-fade-up anim-delay-1">
        <h4><i class="fas fa-check-circle"></i> UPLOAD SUCCESSFUL!</h4>
        <p><?php echo $uploaded_count; ?> users were uploaded successfully.</p>
    </div>
    <?php endif; ?>
    
    <!-- Upload Form -->
    <div class="card anim-fade-up anim-delay-1">
        <div class="card-header">
            <h2><i class="fas fa-file-upload"></i> UPLOAD USERS FILE</h2>
            <span class="badge">CSV ONLY</span>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                <i class="fas fa-cloud-upload-alt"></i>
                <h3>CLICK OR DRAG TO UPLOAD</h3>
                <p>Upload your CSV file with user data</p>
                <div class="file-types">Supported formats: <strong>CSV</strong></div>
                
                <div class="file-input-wrapper">
                    <span class="file-label"><i class="fas fa-folder-open"></i> CHOOSE FILE</span>
                    <input type="file" name="user_file" id="fileInput" accept=".csv" required>
                </div>
            </div>
            
            <div id="selectedFile" class="selected-file">
                <i class="fas fa-file-alt" style="color:var(--blue);font-size:18px;"></i>
                <span class="file-name" id="fileName">No file selected</span>
                <span class="file-size" id="fileSize"></span>
            </div>
            
            <button type="submit" class="btn-upload" id="uploadBtn">
                <i class="fas fa-upload"></i> UPLOAD USERS
            </button>
        </form>
    </div>
    
    <!-- Template Section -->
    <div class="card anim-fade-up anim-delay-2">
        <div class="card-header">
            <h2><i class="fas fa-download"></i> DOWNLOAD TEMPLATE</h2>
            <span class="badge">TEMPLATE</span>
        </div>
        
        <div class="template-section">
            <div class="table-header-info">
                <i class="fas fa-info-circle" style="color:var(--blue);"></i>
                <strong style="color:var(--blue);">Column Headers must match exactly:</strong>
                <code>person_id</code>, <code>username</code>, <code>password</code>, <code>full_name</code>, <code>email</code>, <code>department</code>, <code>position</code>, <code>role</code>, <code>process</code>, <code>reporting_to</code>, <code>shift_id</code>, <code>is_active</code>
            </div>
            
            <div class="template-title">
                <i class="fas fa-list-ul"></i> CSV TEMPLATE FORMAT
            </div>
            <p style="color:var(--text-secondary);font-size:14px;font-weight:500;margin-bottom:12px;">Download the CSV template and fill in your user data. The first row must contain the column headers.</p>
            
            <ul>
                <li><i class="fas fa-asterisk" style="color:var(--red);font-size:10px;"></i> <strong>person_id</strong> <span class="required">(Required)</span> — Unique employee ID</li>
                <li><i class="fas fa-asterisk" style="color:var(--red);font-size:10px;"></i> <strong>username</strong> <span class="required">(Required)</span> — Login username</li>
                <li><i class="fas fa-asterisk" style="color:var(--red);font-size:10px;"></i> <strong>password</strong> <span class="required">(Required)</span> — User password (default: password123)</li>
                <li><i class="fas fa-asterisk" style="color:var(--red);font-size:10px;"></i> <strong>full_name</strong> <span class="required">(Required)</span> — Full name of the user</li>
                <li><i class="fas fa-envelope" style="color:var(--blue);"></i> <strong>email</strong> <span class="optional">(Optional)</span> — Email address</li>
                <li><i class="fas fa-building" style="color:var(--blue);"></i> <strong>department</strong> <span class="optional">(Optional)</span> — Department name</li>
                <li><i class="fas fa-briefcase" style="color:var(--blue);"></i> <strong>position</strong> <span class="optional">(Optional)</span> — Job position</li>
                <li><i class="fas fa-asterisk" style="color:var(--red);font-size:10px;"></i> <strong>role</strong> <span class="required">(Required)</span> — User role: Centre Head, Process Head, Manager, Assistant Manager, Team Leader, Agent, Support (case-insensitive)</li>
                <li><i class="fas fa-cogs" style="color:var(--blue);"></i> <strong>process</strong> <span class="optional">(Optional)</span> — Process name</li>
                <li><i class="fas fa-user-tie" style="color:var(--blue);"></i> <strong>reporting_to</strong> <span class="optional">(Optional)</span> — Person ID of reporting manager</li>
                <li><i class="fas fa-clock" style="color:var(--blue);"></i> <strong>shift_id</strong> <span class="optional">(Optional)</span> — Shift ID (must exist in shifts table)</li>
                <li><i class="fas fa-toggle-on" style="color:var(--blue);"></i> <strong>is_active</strong> <span class="optional">(Optional)</span> — 1 for active, 0 for inactive (default: 1)</li>
            </ul>
            
            <div class="template-actions">
                <a href="?download_template=1" class="btn-template" id="downloadTemplateBtn">
                    <i class="fas fa-file-csv"></i> DOWNLOAD CSV TEMPLATE
                </a>
                <a href="javascript:void(0)" class="btn-template green" onclick="showSampleData()">
                    <i class="fas fa-eye"></i> VIEW SAMPLE DATA
                </a>
            </div>
        </div>
    </div>
    
    <!-- Sample Data -->
    <div class="card anim-fade-up anim-delay-3" id="sampleData" style="display:none;">
        <div class="card-header">
            <h2><i class="fas fa-table"></i> SAMPLE CSV DATA</h2>
            <button class="close-sample-btn" onclick="document.getElementById('sampleData').style.display='none'">
                <i class="fas fa-times"></i> CLOSE
            </button>
        </div>
        
        <div class="sample-data">
            <pre>
<span class="highlight-cyan">person_id</span>,<span class="highlight-cyan">username</span>,<span class="highlight-cyan">password</span>,<span class="highlight-cyan">full_name</span>,<span class="highlight-cyan">email</span>,<span class="highlight-cyan">department</span>,<span class="highlight-cyan">position</span>,<span class="highlight-cyan">role</span>,<span class="highlight-cyan">process</span>,<span class="highlight-cyan">reporting_to</span>,<span class="highlight-cyan">shift_id</span>,<span class="highlight-cyan">is_active</span>
<span class="highlight-green">EMP001</span>,admin,password123,Admin User,admin@company.com,IT,Centre Head,<span class="highlight-orange">Centre Head</span>,Corporate,,1,1
<span class="highlight-green">EMP002</span>,processhead,password123,Process Head,process@company.com,IT,Process Head,<span class="highlight-orange">Process Head</span>,Development,EMP001,1,1
<span class="highlight-green">EMP003</span>,manager,password123,Manager User,manager@company.com,IT,Manager,<span class="highlight-orange">Manager</span>,Development,EMP002,1,1
<span class="highlight-green">EMP004</span>,amuser,password123,AM User,am@company.com,IT,Assistant Manager,<span class="highlight-orange">Assistant Manager</span>,Development,EMP003,1,1
<span class="highlight-green">EMP005</span>,tluser,password123,TL User,tl@company.com,IT,Team Lead,<span class="highlight-orange">Team Leader</span>,Development,EMP004,1,1
<span class="highlight-green">EMP006</span>,agent,password123,Agent User,agent@company.com,IT,Software Engineer,<span class="highlight-orange">Agent</span>,Development,EMP005,1,1
            </pre>
        </div>
        
        <div class="sample-note">
            <p>
                <i class="fas fa-info-circle" style="color:var(--blue);"></i>
                <strong style="color:var(--blue);">Role Mapping:</strong><br>
                • <strong>Team Leader</strong> or <strong>Team Lead</strong> → <code>tl</code><br>
                • <strong>Assistant Manager</strong> → <code>am</code><br>
                • <strong>Agent</strong> → <code>agent</code><br>
                • <strong>Support</strong> → <code>agent</code><br>
                • <strong>Manager</strong> → <code>manager</code><br>
                • <strong>Centre Head</strong> → <code>centre_head</code><br>
                • <strong>Process Head</strong> → <code>process_head</code>
            </p>
        </div>
    </div>
</div>

<script>
    // File input handler
    document.getElementById('fileInput').addEventListener('change', function(e) {
        const file = this.files[0];
        const selectedFileDiv = document.getElementById('selectedFile');
        const fileNameSpan = document.getElementById('fileName');
        const fileSizeSpan = document.getElementById('fileSize');
        const uploadBtn = document.getElementById('uploadBtn');
        
        if (file) {
            fileNameSpan.textContent = file.name;
            fileSizeSpan.textContent = '(' + (file.size / 1024).toFixed(1) + ' KB)';
            selectedFileDiv.classList.add('show');
            uploadBtn.classList.add('show');
            
            const ext = file.name.split('.').pop().toLowerCase();
            if (ext !== 'csv') {
                alert('Please upload a CSV file only.');
                this.value = '';
                selectedFileDiv.classList.remove('show');
                uploadBtn.classList.remove('show');
            }
        }
    });
    
    // Show sample data
    function showSampleData() {
        const sampleDiv = document.getElementById('sampleData');
        if (sampleDiv.style.display === 'none') {
            sampleDiv.style.display = 'block';
            sampleDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            sampleDiv.style.display = 'none';
        }
    }
    
    // Download template
    document.getElementById('downloadTemplateBtn').addEventListener('click', function(e) {
        e.preventDefault();
        generateAndDownloadCSV();
    });
    
    function generateAndDownloadCSV() {
        const headers = ['person_id', 'username', 'password', 'full_name', 'email', 'department', 'position', 'role', 'process', 'reporting_to', 'shift_id', 'is_active'];
        const sampleRow = ['EMP001', 'johndoe', 'password123', 'John Doe', 'john.doe@company.com', 'IT', 'Software Engineer', 'Agent', 'Development', 'EMP005', '1', '1'];
        
        let csvContent = headers.join(',') + '\n';
        csvContent += sampleRow.join(',') + '\n';
        csvContent += ',,,,,,,,,,,';
        
        const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'user_upload_template.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
</script>

</body>
</html>