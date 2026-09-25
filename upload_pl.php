<?php
// pl_upload.php - Upload PL Balance from CSV
// ============================================
// MATCHING UI DESIGN WITH USER.PHP
// ============================================

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Check if user has admin access
$user_role = $_SESSION['role'] ?? 'employee';
$person_id = $_SESSION['person_id'] ?? '';
$is_admin = (
    in_array($user_role, ['admin', 'super_admin']) ||
    $person_id === 'FNM001' ||
    $user_role === 'centre_head'
);

if (!$is_admin) {
    header('Location: dashboard.php?error=unauthorized');
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pl_file'])) {
    $filename = $_FILES['pl_file']['tmp_name'];
    
    if ($_FILES['pl_file']['size'] > 0) {
        $file = fopen($filename, "r");
        
        // Skip header row
        fgetcsv($file);
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];

        while (($column = fgetcsv($file, 1000, ",")) !== FALSE) {
            // Skip empty rows
            if (empty($column[0]) || empty($column[2])) {
                $error_count++;
                $errors[] = "Empty row skipped";
                continue;
            }
            
            $person_id = trim($column[0]);
            $pl_balance = floatval(trim($column[2]));

            // Check if user exists
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE person_id = ?");
            $check_stmt->execute([$person_id]);
            $user_exists = $check_stmt->fetch();

            if ($user_exists) {
                // Update PL balance
                $stmt = $pdo->prepare("UPDATE users SET pl_balance = ? WHERE person_id = ?");
                if ($stmt->execute([$pl_balance, $person_id])) {
                    $success_count++;
                } else {
                    $error_count++;
                    $errors[] = "Failed to update Person ID: $person_id";
                }
            } else {
                $error_count++;
                $errors[] = "User not found: $person_id";
            }
        }
        fclose($file);
        
        if ($success_count > 0 && $error_count == 0) {
            $message = "? Successfully updated $success_count users' PL balance.";
            $message_type = 'success';
        } elseif ($success_count > 0 && $error_count > 0) {
            $message = "?? Partially completed: $success_count updated, $error_count errors.";
            $message_type = 'warning';
        } else {
            $message = "? Failed to update any users. Please check the file format.";
            $message_type = 'error';
        }
        
        // Store errors in session for display
        if (!empty($errors)) {
            $_SESSION['upload_errors'] = $errors;
        }
    } else {
        $message = "? Please select a valid CSV file.";
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload PL Balance - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           MATCHING UI DESIGN FROM USER.PHP
           ============================================ */
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

            --orange: #ea580c;
            --orange-light: #ffedd5;
            --orange-bg: rgba(234, 88, 12, 0.06);

            --pink: #db2777;
            --pink-light: #fce7f3;
            --pink-bg: rgba(219, 39, 119, 0.06);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f2f6;
            padding: 20px;
            min-height: 100vh;
            color: var(--text-primary);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .anim-fade-up {
            animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }

        .anim-delay-1 {
            animation-delay: 0.05s;
        }
        .anim-delay-2 {
            animation-delay: 0.1s;
        }
        .anim-delay-3 {
            animation-delay: 0.15s;
        }

        /* ============================================
           HEADER - MATCHING USER.PHP
           ============================================ */
        .header {
            background: var(--white);
            border-radius: var(--radius);
            padding: 22px 30px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            transition: all 0.3s ease;
        }

        .header:hover {
            box-shadow: var(--shadow-md);
        }

        .header .header-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .header .system-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 2.5px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header .system-label .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
            animation: pulseDot 2s ease-in-out infinite;
            display: inline-block;
        }

        @keyframes pulseDot {
            0%, 100% {
                opacity: 0.6;
                transform: scale(1);
            }
            50% {
                opacity: 1;
                transform: scale(1.3);
            }
        }

        .header h1 {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-primary);
            flex-wrap: wrap;
        }

        .header h1 i {
            color: var(--purple);
            font-size: 26px;
        }

        .header h1 .highlight {
            color: var(--blue);
            font-weight: 700;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .header-actions a {
            color: var(--text-secondary);
            text-decoration: none;
            padding: 10px 22px;
            border-radius: var(--radius-sm);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border-light);
            background: var(--bg-light);
        }

        .header-actions a:hover {
            background: var(--blue-bg);
            border-color: var(--blue);
            color: var(--blue);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .header-actions a i {
            color: var(--blue);
        }

        .header-actions a:hover i {
            color: var(--blue);
        }

        /* ============================================
           ALERT MESSAGES
           ============================================ */
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
        }

        .alert-success {
            background: var(--green-light);
            color: #065f46;
            border-color: #a7f3d0;
        }

        .alert-error {
            background: var(--red-light);
            color: #991b1b;
            border-color: #fca5a5;
        }

        .alert-warning {
            background: var(--yellow-light);
            color: #92400e;
            border-color: #fcd34d;
        }

        .alert i {
            font-size: 18px;
        }

        /* ============================================
           CARD - MATCHING USER.PHP
           ============================================ */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 32px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-light);
        }

        .card-header h2 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.3px;
        }

        .card-header h2 i {
            color: var(--purple);
            font-size: 18px;
        }

        .card-header .badge {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            padding: 4px 14px;
            border: 1px solid var(--border-light);
            border-radius: 50px;
            background: var(--bg-light);
        }

        /* ============================================
           FORM STYLES
           ============================================ */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .form-group label .required {
            color: var(--red);
        }

        .form-group .help-text {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
            font-weight: 500;
        }

        .file-upload-wrapper {
            position: relative;
            margin-top: 4px;
        }

        .file-upload-wrapper .file-label {
            display: block;
            padding: 20px;
            background: var(--bg-light);
            border: 2px dashed var(--border-light);
            border-radius: var(--radius-sm);
            text-align: center;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 14px;
        }

        .file-upload-wrapper .file-label:hover {
            border-color: var(--blue);
            background: var(--blue-bg);
            color: var(--blue);
        }

        .file-upload-wrapper .file-label i {
            font-size: 28px;
            display: block;
            margin-bottom: 10px;
            color: var(--blue);
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-name-display {
            font-size: 13px;
            color: var(--blue);
            margin-top: 8px;
            font-weight: 600;
            text-align: center;
        }

        .file-name-display i {
            margin-right: 6px;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--blue), var(--purple));
            color: #fff;
            border: none;
            padding: 12px 32px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-submit i {
            color: #fff;
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 8px;
            padding-top: 16px;
            border-top: 1px solid var(--border-light);
        }

        .form-footer .info-text {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .form-footer .info-text i {
            color: var(--blue);
            margin-right: 4px;
        }

        /* ============================================
           ERRORS LIST
           ============================================ */
        .error-list {
            margin-top: 16px;
            padding: 14px 18px;
            background: var(--red-light);
            border-radius: var(--radius-sm);
            border: 1px solid #fca5a5;
            max-height: 200px;
            overflow-y: auto;
        }

        .error-list h4 {
            color: #991b1b;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .error-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .error-list ul li {
            font-size: 12px;
            color: #991b1b;
            padding: 2px 0;
            font-weight: 500;
        }

        .error-list ul li::before {
            content: "• ";
            color: #991b1b;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 768px) {
            body {
                padding: 12px;
            }

            .header {
                padding: 20px 24px;
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }

            .header h1 {
                font-size: 22px;
            }

            .header-actions {
                flex-direction: column;
                width: 100%;
            }

            .header-actions a {
                width: 100%;
                justify-content: center;
            }

            .card {
                padding: 20px 18px;
                border-radius: var(--radius-sm);
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .card-header h2 {
                font-size: 16px;
            }

            .form-footer {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .btn-submit {
                width: 100%;
                justify-content: center;
            }

            .file-upload-wrapper .file-label {
                padding: 16px;
                font-size: 13px;
            }

            .file-upload-wrapper .file-label i {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 8px;
            }

            .header {
                padding: 14px 16px;
                border-radius: var(--radius-sm);
            }

            .header h1 {
                font-size: 18px;
            }

            .header .system-label {
                font-size: 9px;
            }

            .card {
                padding: 14px 12px;
            }

            .card-header h2 {
                font-size: 14px;
            }

            .card-header .badge {
                font-size: 10px;
                padding: 2px 10px;
            }

            .form-group label {
                font-size: 12px;
            }

            .btn-submit {
                font-size: 13px;
                padding: 10px 20px;
            }

            .file-upload-wrapper .file-label {
                padding: 14px;
                font-size: 12px;
            }

            .file-upload-wrapper .file-label i {
                font-size: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- ============================================
        HEADER - MATCHING USER.PHP
        ============================================ -->
        <div class="header anim-fade-up">
            <div class="header-left">
                <div class="system-label">
                    <span class="dot"></span>
                    PL BALANCE MANAGEMENT
                </div>
                <h1>
                    <i class="fas fa-cloud-upload-alt"></i>
                    UPLOAD <span class="highlight">PL BALANCE</span>
                </h1>
            </div>
            <div class="header-actions">
                <a href="dashboard.php">
                    <i class="fas fa-th-large"></i> DASHBOARD
                </a>
                <a href="users.php">
                    <i class="fas fa-users"></i> USERS
                </a>
            </div>
        </div>

        <!-- ============================================
        ALERT MESSAGES
        ============================================ -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> anim-fade-up anim-delay-1">
                <i class="fas <?php echo ($message_type === 'success') ? 'fa-check-circle' : (($message_type === 'warning') ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- ============================================
        UPLOAD CARD - MATCHING USER.PHP
        ============================================ -->
        <div class="card anim-fade-up anim-delay-2">
            <div class="card-header">
                <h2>
                    <i class="fas fa-file-csv"></i> CSV UPLOAD
                </h2>
                <span class="badge">
                    <i class="fas fa-info-circle"></i> CSV Format Required
                </span>
            </div>

            <form action="" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Select CSV File <span class="required">*</span></label>
                    <div class="file-upload-wrapper">
                        <div class="file-label" id="fileLabel">
                            <i class="fas fa-cloud-upload-alt"></i>
                            Click to select CSV file
                            <div style="font-weight: 400; font-size: 12px; color: #94a3b8; margin-top: 4px;">
                                or drag and drop here
                            </div>
                        </div>
                        <input type="file" name="pl_file" accept=".csv" required onchange="updateFileLabel(this)">
                    </div>
                    <div class="file-name-display" id="fileNameDisplay"></div>
                    <div class="help-text">
                        <i class="fas fa-info-circle"></i>
                        File should contain columns: person_id, name, pl_balance
                    </div>
                </div>

                <div class="form-footer">
                    <div class="info-text">
                        <i class="fas fa-shield-alt"></i>
                        Only <strong>Admin</strong> users can upload PL balance
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-upload"></i> UPLOAD & UPDATE PL
                    </button>
                </div>
            </form>

            <?php if (isset($_SESSION['upload_errors']) && !empty($_SESSION['upload_errors'])): ?>
                <div class="error-list">
                    <h4><i class="fas fa-exclamation-circle"></i> Errors Details</h4>
                    <ul>
                        <?php foreach ($_SESSION['upload_errors'] as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php unset($_SESSION['upload_errors']); ?>
            <?php endif; ?>
        </div>

        <!-- ============================================
        INFO CARD - SAMPLE FORMAT
        ============================================ -->
        <div class="card anim-fade-up anim-delay-3" style="margin-top: 20px;">
            <div class="card-header">
                <h2>
                    <i class="fas fa-table"></i> SAMPLE CSV FORMAT
                </h2>
                <span class="badge">
                    <i class="fas fa-download"></i> Download Template
                </span>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: var(--bg-light);">
                            <th style="padding: 10px 14px; text-align: left; font-weight: 700; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-light);">person_id</th>
                            <th style="padding: 10px 14px; text-align: left; font-weight: 700; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-light);">name</th>
                            <th style="padding: 10px 14px; text-align: left; font-weight: 700; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-light);">pl_balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 10px 14px; border-bottom: 1px solid var(--border-light); font-weight: 500; color: var(--text-secondary);"><code style="background: var(--bg-light); padding: 2px 8px; border-radius: 4px;">FNM001</code></td>
                            <td style="padding: 10px 14px; border-bottom: 1px solid var(--border-light); color: var(--text-secondary);">John Doe</td>
                            <td style="padding: 10px 14px; border-bottom: 1px solid var(--border-light); font-weight: 700; color: var(--blue);">12.5</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 14px; border-bottom: 1px solid var(--border-light); font-weight: 500; color: var(--text-secondary);"><code style="background: var(--bg-light); padding: 2px 8px; border-radius: 4px;">FNM002</code></td>
                            <td style="padding: 10px 14px; border-bottom: 1px solid var(--border-light); color: var(--text-secondary);">Jane Smith</td>
                            <td style="padding: 10px 14px; border-bottom: 1px solid var(--border-light); font-weight: 700; color: var(--blue);">8.0</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 12px; font-size: 12px; color: var(--text-muted);">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> The <code style="background: var(--bg-light); padding: 2px 6px; border-radius: 4px;">person_id</code> must match an existing user in the system.
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // FILE LABEL UPDATE
        // ============================================
        function updateFileLabel(input) {
            const label = document.getElementById('fileLabel');
            const display = document.getElementById('fileNameDisplay');

            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                const fileSize = (input.files[0].size / 1024).toFixed(1);
                
                label.innerHTML = `
                    <i class="fas fa-file-csv" style="color: #dc2626; font-size: 28px; display: block; margin-bottom: 10px;"></i>
                    <strong>${fileName}</strong>
                    <div style="font-weight: 400; font-size: 12px; color: #94a3b8; margin-top: 4px;">
                        ${fileSize} KB • Click to change file
                    </div>
                `;
                label.style.borderColor = '#16a34a';
                label.style.background = 'var(--green-bg)';
                label.style.color = '#065f46';
                
                display.innerHTML = `
                    <i class="fas fa-check-circle" style="color: var(--green);"></i>
                    Selected: ${fileName} (${fileSize} KB)
                `;
            } else {
                label.innerHTML = `
                    <i class="fas fa-cloud-upload-alt"></i>
                    Click to select CSV file
                    <div style="font-weight: 400; font-size: 12px; color: #94a3b8; margin-top: 4px;">
                        or drag and drop here
                    </div>
                `;
                label.style.borderColor = 'var(--border-light)';
                label.style.background = 'var(--bg-light)';
                label.style.color = 'var(--text-muted)';
                display.innerHTML = '';
            }
        }

        // ============================================
        // DRAG AND DROP SUPPORT
        // ============================================
        const dropZone = document.querySelector('.file-upload-wrapper');
        
        if (dropZone) {
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                const label = document.getElementById('fileLabel');
                label.style.borderColor = 'var(--blue)';
                label.style.background = 'var(--blue-bg)';
                label.style.color = 'var(--blue)';
            });

            dropZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                const label = document.getElementById('fileLabel');
                const input = document.querySelector('input[type="file"]');
                if (!input.files || !input.files[0]) {
                    label.style.borderColor = 'var(--border-light)';
                    label.style.background = 'var(--bg-light)';
                    label.style.color = 'var(--text-muted)';
                }
            });

            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                const input = document.querySelector('input[type="file"]');
                const files = e.dataTransfer.files;
                if (files.length) {
                    input.files = files;
                    updateFileLabel(input);
                }
            });
        }
    </script>
</body>

</html>