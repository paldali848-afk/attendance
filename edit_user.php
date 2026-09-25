<?php
require_once 'config.php';

// Only Centre Head can edit users
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'centre_head')) {
    header("Location: dashboard.php?error=unauthorized");
    exit();
}

$user = getCurrentUser();
$error = '';
$success = '';
$user_data = [];

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    redirect('users.php');
}

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        redirect('users.php');
    }
} catch (PDOException $e) {
    redirect('users.php');
}

// Get departments
$departments = [];
try {
    if (tableExists('departments')) {
        $departments = $pdo->query("SELECT * FROM departments ORDER BY dept_name")->fetchAll();
    }
} catch (PDOException $e) {
    $departments = [];
}

// Get positions
$positions = [];
try {
    if (tableExists('positions')) {
        $positions = $pdo->query("SELECT * FROM positions ORDER BY position_name")->fetchAll();
    }
} catch (PDOException $e) {
    $positions = [];
}

// Get reporting managers
$reporting_options = [];
try {
    $sql = "SELECT id, full_name, position, role FROM users WHERE role IN ('centre_head', 'hr', 'process_head', 'manager', 'am', 'tl') AND id != ? ORDER BY full_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $reporting_options = $stmt->fetchAll();
} catch (PDOException $e) {
    $reporting_options = [];
}

// Get shifts
$shifts = [];
if (tableExists('shifts')) {
    $shifts = $pdo->query("SELECT * FROM shifts ORDER BY shift_name")->fetchAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $form_data = $_POST;

    // Validate required fields
    $required_fields = ['full_name', 'email', 'role', 'department', 'position'];
    $errors = [];

    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }

    // Validate email
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    // Check if email exists (excluding current user)
    if (!empty($_POST['email']) && $_POST['email'] != $user_data['email']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$_POST['email'], $user_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already exists. Please use another email.';
        }
    }

    // Check if username exists (excluding current user)
    if (!empty($_POST['username']) && $_POST['username'] != $user_data['username']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$_POST['username'], $user_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already exists. Please choose another.';
        }
    }

    // Validate password if provided
    if (!empty($_POST['password'])) {
        if (strlen($_POST['password']) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
        if ($_POST['password'] != $_POST['confirm_password']) {
            $errors[] = 'Passwords do not match.';
        }
    }

    // If no errors, proceed with update
    if (empty($errors)) {
        try {
            // Prepare update data
            $update_data = [
                'full_name' => $_POST['full_name'],
                'email' => $_POST['email'],
                'department' => $_POST['department'],
                'position' => $_POST['position'],
                'role' => $_POST['role'],
                'process' => $_POST['process'] ?? null,
                'reporting_to' => !empty($_POST['reporting_to']) ? $_POST['reporting_to'] : null,
                'shift_id' => !empty($_POST['shift_id']) ? $_POST['shift_id'] : null
            ];

            // Add username if changed
            if ($_POST['username'] != $user_data['username']) {
                $update_data['username'] = $_POST['username'];
            }

            // Add password if provided
            if (!empty($_POST['password'])) {
                $update_data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }

            // Build SQL query
            $update_fields = [];
            $update_values = [];
            foreach ($update_data as $key => $value) {
                $update_fields[] = "$key = ?";
                $update_values[] = $value;
            }
            $update_values[] = $user_id;

            $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($update_values);

            // Log the action
            try {
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, details, ip_address) 
                    VALUES (?, ?, ?, ?)
                ");
                $log_stmt->execute([
                    $user['id'],
                    'edit_user',
                    "Updated user: {$_POST['full_name']} (ID: $user_id)",
                    $_SERVER['REMOTE_ADDR']
                ]);
            } catch (PDOException $e) {
                // Ignore logging errors
            }

            $success = "User updated successfully!";

            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch();
        } catch (PDOException $e) {
            $error = 'Failed to update user: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - <?php echo SITE_NAME; ?></title>
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
            max-width: 900px;
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

        /* ===== HEADER ===== */
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
            gap: 2px;
        }

        .header .system-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 2px;
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

            0%,
            100% {
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
        }

        .header h1 i {
            color: var(--blue);
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

        .header-actions .btn-back i {
            color: var(--blue);
        }

        .header-actions .btn-dashboard i {
            color: var(--blue);
        }

        .header-actions .btn-back:hover i,
        .header-actions .btn-dashboard:hover i {
            color: var(--blue);
        }

        /* ===== MESSAGES ===== */
        .message {
            padding: 14px 22px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid transparent;
            font-size: 14px;
            font-weight: 600;
            animation: fadeUp 0.4s ease;
        }

        .message-success {
            background: var(--green-bg);
            color: var(--green);
            border-color: rgba(22, 163, 74, 0.15);
        }

        .message-success i {
            color: var(--green);
            font-size: 20px;
        }

        .message-error {
            background: var(--red-bg);
            color: var(--red);
            border-color: rgba(220, 38, 38, 0.15);
        }

        .message-error i {
            color: var(--red);
            font-size: 20px;
        }

        /* ===== INFO BOX ===== */
        .info-box {
            background: var(--bg-light);
            border-radius: var(--radius-sm);
            padding: 16px 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-light);
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            align-items: center;
        }

        .info-box p {
            margin: 0;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box strong {
            color: var(--text-primary);
            font-weight: 700;
        }

        .info-box i {
            color: var(--blue);
            font-size: 14px;
        }

        /* ===== CARD ===== */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 32px 36px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card h2 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 28px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.3px;
        }

        .card h2 i {
            color: var(--blue);
        }

        .card .field-note {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 500;
        }

        /* ===== FORM ===== */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .form-grid .full-width {
            grid-column: 1 / -1;
        }

        .form-group {
            margin-bottom: 4px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-secondary);
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .form-group label .required {
            color: var(--red);
            margin-left: 3px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 14px;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-weight: 500;
        }

        .form-group input::placeholder {
            color: var(--text-muted);
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
        }

        .form-group input[readonly] {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--bg-light);
        }

        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
            cursor: pointer;
        }

        .form-group select option {
            background: var(--white);
            color: var(--text-primary);
        }

        .form-group .input-group {
            position: relative;
        }

        .form-group .input-group i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group .input-group input {
            padding-left: 44px;
        }

        .form-group .input-group input:focus~i {
            color: var(--blue);
        }

        /* ===== PASSWORD SECTION ===== */
        .password-section {
            background: var(--bg-light);
            padding: 24px 28px;
            border-radius: var(--radius-sm);
            margin-top: 20px;
            border: 1px solid var(--border-light);
        }

        .password-section h4 {
            color: var(--text-primary);
            margin-bottom: 18px;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.3px;
        }

        .password-section h4 i {
            color: var(--blue);
        }

        /* ===== PASSWORD STRENGTH ===== */
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: var(--border-light);
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength .bar {
            height: 100%;
            width: 0%;
            transition: width 0.4s ease;
            border-radius: 2px;
        }

        .password-strength .bar.weak {
            background: var(--red);
            width: 33%;
        }

        .password-strength .bar.medium {
            background: var(--yellow);
            width: 66%;
        }

        .password-strength .bar.strong {
            background: var(--green);
            width: 100%;
        }

        .password-strength-text {
            font-size: 11px;
            margin-top: 4px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        /* ===== ACTIONS ===== */
        .actions {
            display: flex;
            gap: 14px;
            margin-top: 28px;
            flex-wrap: wrap;
        }

        .actions .btn {
            padding: 12px 32px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--blue), var(--purple));
            color: #fff;
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--bg-light);
            color: var(--text-secondary);
            border: 1px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--blue-bg);
            border-color: var(--blue);
            color: var(--blue);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--red-bg);
            color: var(--red);
            border: 1px solid rgba(220, 38, 38, 0.1);
        }

        .btn-danger:hover {
            background: var(--red);
            color: #fff;
            transform: translateY(-2px);
        }

        /* ===== RESPONSIVE ===== */
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
                justify-content: stretch;
            }

            .header-actions a {
                flex: 1;
                justify-content: center;
                font-size: 12px;
                padding: 8px 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-grid .full-width {
                grid-column: 1;
            }

            .card {
                padding: 20px 24px;
            }

            .card h2 {
                font-size: 16px;
            }

            .actions {
                flex-direction: column;
            }

            .actions .btn {
                justify-content: center;
                width: 100%;
            }

            .info-box {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
                padding: 14px 18px;
            }

            .password-section {
                padding: 16px 18px;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 18px;
            }

            .header .system-label {
                font-size: 9px;
            }

            .form-group label {
                font-size: 10px;
            }

            .form-group input,
            .form-group select {
                font-size: 13px;
                padding: 10px 14px;
            }

            .form-group .input-group i {
                font-size: 14px;
                left: 12px;
            }

            .form-group .input-group input {
                padding-left: 38px;
            }

            .info-box p {
                font-size: 12px;
            }

            .card {
                padding: 16px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header anim-fade-up">
            <div class="header-left">
                <div class="system-label">
                    <span class="dot"></span>
                    USER EDIT
                </div>
                <h1>
                    <i class="fas fa-user-edit"></i>
                    EDIT <span class="highlight">USER</span>
                </h1>
            </div>
            <div class="header-actions">
                <a href="users.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> BACK
                </a>
                <a href="dashboard.php" class="btn-dashboard">
                    <i class="fas fa-th-large"></i> DASHBOARD
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success): ?>
            <div class="message message-success anim-fade-up anim-delay-1">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message message-error anim-fade-up anim-delay-1">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- User Info -->
        <div class="info-box anim-fade-up anim-delay-1">
            <p><i class="fas fa-id-card"></i> <strong>PERSON ID:</strong> <?php echo htmlspecialchars($user_data['person_id']); ?></p>
            <p><i class="fas fa-clock"></i> <strong>CREATED:</strong> <?php echo date('M d, Y H:i:s', strtotime($user_data['created_at'])); ?></p>
        </div>

        <!-- Edit Form -->
        <div class="card anim-fade-up anim-delay-2">
            <h2><i class="fas fa-user-cog"></i> EMPLOYEE INFORMATION</h2>

            <form method="POST" action="" id="userForm">
                <div class="form-grid">
                    <!-- Person ID (Read Only) -->
                    <div class="form-group">
                        <label>Person ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($user_data['person_id']); ?>" readonly>
                        <div class="field-note">Auto-generated, cannot be changed</div>
                    </div>

                    <!-- Username -->
                    <div class="form-group">
                        <label>Username <span class="required">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" name="username" required
                                value="<?php echo htmlspecialchars($user_data['username']); ?>"
                                placeholder="Enter username">
                        </div>
                        <div class="field-note">Must be unique. Used for login.</div>
                    </div>

                    <!-- Full Name -->
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" required
                            value="<?php echo htmlspecialchars($user_data['full_name']); ?>"
                            placeholder="Enter full name">
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" required
                            value="<?php echo htmlspecialchars($user_data['email']); ?>"
                            placeholder="Enter email address">
                    </div>

                    <!-- Department -->
                    <div class="form-group">
                        <label>Department <span class="required">*</span></label>
                        <?php if (!empty($departments)): ?>
                            <select name="department" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['dept_name']); ?>"
                                        <?php echo ($user_data['department'] == $dept['dept_name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="department" required
                                value="<?php echo htmlspecialchars($user_data['department']); ?>"
                                placeholder="Enter department">
                        <?php endif; ?>
                    </div>

                    <!-- Position -->
                    <div class="form-group">
                        <label>Position <span class="required">*</span></label>
                        <?php if (!empty($positions)): ?>
                            <select name="position" required id="positionSelect">
                                <option value="">Select Position</option>
                                <?php foreach ($positions as $pos): ?>
                                    <option value="<?php echo htmlspecialchars($pos['position_name']); ?>"
                                        data-role="<?php echo $pos['role']; ?>"
                                        <?php echo ($user_data['position'] == $pos['position_name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pos['position_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="position" required
                                value="<?php echo htmlspecialchars($user_data['position']); ?>"
                                placeholder="Enter position">
                        <?php endif; ?>
                        <div class="field-note">Designation / Job Title</div>
                    </div>

                    <!-- Role -->
                    <div class="form-group">
                        <label>Role <span class="required">*</span></label>
                        <select name="role" required id="roleSelect">
                            <option value="">Select Role</option>
                            <option value="centre_head" <?php echo ($user_data['role'] == 'centre_head') ? 'selected' : ''; ?>>Centre Head</option>

                            <!-- ADD THIS LINE HERE -->
                            <option value="hr" <?php echo ($user_data['role'] == 'hr') ? 'selected' : ''; ?>>HR</option>

                            <option value="process_head" <?php echo ($user_data['role'] == 'process_head') ? 'selected' : ''; ?>>Process Head</option>
                            <option value="manager" <?php echo ($user_data['role'] == 'manager') ? 'selected' : ''; ?>>Manager</option>
                            <option value="am" <?php echo ($user_data['role'] == 'am') ? 'selected' : ''; ?>>Assistant Manager</option>
                            <option value="tl" <?php echo ($user_data['role'] == 'tl') ? 'selected' : ''; ?>>Team Lead</option>
                            <option value="agent" <?php echo ($user_data['role'] == 'agent') ? 'selected' : ''; ?>>Agent</option>
                        </select>
                    </div>

                    <!-- Process -->
                    <div class="form-group">
                        <label>Process</label>
                        <input type="text" name="process"
                            value="<?php echo htmlspecialchars($user_data['process']); ?>"
                            placeholder="e.g., Sales, Support, Collection">
                        <div class="field-note">Required for Process Heads and Managers</div>
                    </div>

                    <!-- Reporting To -->
                    <div class="form-group">
                        <label>Reports To</label>
                        <select name="reporting_to">
                            <option value="">None</option>
                            <?php foreach ($reporting_options as $ro): ?>
                                <option value="<?php echo $ro['id']; ?>"
                                    <?php echo ($user_data['reporting_to'] == $ro['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ro['full_name'] . ' (' . $ro['position'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-note">Select the supervisor this person reports to</div>
                    </div>

                    <!-- Shift -->
                    <div class="form-group">
                        <label>Shift</label>
                        <select name="shift_id">
                            <option value="">Select Shift</option>
                            <?php foreach ($shifts as $shift): ?>
                                <option value="<?php echo $shift['id']; ?>"
                                    <?php echo ($user_data['shift_id'] == $shift['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($shift['shift_name']); ?>
                                    (<?php echo date('h:i A', strtotime($shift['start_time'])); ?> -
                                    <?php echo date('h:i A', strtotime($shift['end_time'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Password Section -->
                <div class="password-section">
                    <h4><i class="fas fa-lock"></i> CHANGE PASSWORD (LEAVE BLANK TO KEEP CURRENT)</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>New Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" id="password"
                                    placeholder="Enter new password (min 6 characters)">
                            </div>
                            <div class="password-strength">
                                <div class="bar" id="passwordStrengthBar"></div>
                            </div>
                            <div class="password-strength-text" id="passwordStrengthText"></div>
                        </div>

                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <div class="input-group">
                                <i class="fas fa-check"></i>
                                <input type="password" name="confirm_password" id="confirmPassword"
                                    placeholder="Confirm new password">
                            </div>
                            <div class="field-note" id="passwordMatchHint"></div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> UPDATE USER
                    </button>
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> CANCEL
                    </a>
                    <?php if ($user_data['id'] != $user['id']): ?>
                        <a href="delete_user.php?id=<?php echo $user_data['id']; ?>" class="btn btn-danger"
                            onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                            <i class="fas fa-trash"></i> DELETE USER
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-set role based on position
        document.getElementById('positionSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const role = selectedOption.getAttribute('data-role');
            if (role) {
                document.getElementById('roleSelect').value = role;
            }
        });

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const bar = document.getElementById('passwordStrengthBar');
            const text = document.getElementById('passwordStrengthText');

            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            bar.className = 'bar';
            if (strength <= 2) {
                bar.classList.add('weak');
                text.textContent = 'WEAK PASSWORD';
                text.style.color = '#dc2626';
            } else if (strength <= 4) {
                bar.classList.add('medium');
                text.textContent = 'MEDIUM PASSWORD';
                text.style.color = '#d97706';
            } else if (strength >= 5) {
                bar.classList.add('strong');
                text.textContent = 'STRONG PASSWORD';
                text.style.color = '#16a34a';
            }

            if (password.length === 0) {
                bar.style.width = '0%';
                text.textContent = '';
            }
        });

        // Password match checker
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const hint = document.getElementById('passwordMatchHint');

            if (confirm.length === 0) {
                hint.textContent = '';
                return;
            }

            if (password === confirm) {
                hint.textContent = '✓ Passwords match';
                hint.style.color = '#16a34a';
            } else {
                hint.textContent = '✗ Passwords do not match';
                hint.style.color = '#dc2626';
            }
        });

        // Form validation
        document.getElementById('userForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirmPassword').value;

            if (password !== confirm && password !== '') {
                e.preventDefault();
                alert('PASSWORDS DO NOT MATCH!');
                return false;
            }
        });
    </script>
</body>

</html>