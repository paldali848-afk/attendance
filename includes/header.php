<?php
// header.php - Professional Header with Hidden Sidebar
if (!isset($user)) {
    $user = getCurrentUser();
}

// Get user initials
$user_initials = '';
if ($user && isset($user['full_name'])) {
    $name_parts = explode(' ', $user['full_name']);
    foreach ($name_parts as $part) {
        if (!empty($part)) {
            $user_initials .= strtoupper($part[0]);
        }
    }
    $user_initials = substr($user_initials, 0, 2);
} else {
    $user_initials = 'U';
}

$user_role = $user['role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);

// Check if current page is in IJP module
$is_ijp_page = strpos($_SERVER['REQUEST_URI'], 'modules/ijp') !== false;

// HR/ER permissions
$user_dept = strtolower($user['department'] ?? '');
$is_hr_er = in_array($user_dept, ['hr', 'er', 'human resources', 'employee relations']);

// ===== OT PERMISSION - only for FNM10824 =====
$can_view_ot = false;
if (isset($user['person_id']) && trim($user['person_id']) === 'FNM10824') {
    $can_view_ot = true;
}

// ===== PAYROLL EXPORT PERMISSION =====
$can_export_payroll = false;
if (isset($user['person_id'])) {
    if (trim($user['person_id']) === 'FNM0109' || ($user['role'] ?? '') === 'process_head') {
        $can_export_payroll = true;
    }
}

// ===== PENDING APPROVALS COUNT =====
$pending_approvals_count = 0;
if (in_array($user_role, ['hr', 'process_head', 'manager', 'centre_head', 'admin'])) {
    if (file_exists(__DIR__ . '/modules/approvals/approval_fetch.php')) {
        require_once __DIR__ . '/modules/approvals/approval_fetch.php';
        if (function_exists('getPendingApprovalsCount')) {
            $pending_approvals_count = getPendingApprovalsCount($pdo, $user);
        } else {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM late_exception_requests WHERE status = 'pending'");
                $stmt->execute();
                $pending_approvals_count = (int)$stmt->fetchColumn();
            } catch (Exception $e) {
                $pending_approvals_count = 0;
            }
        }
    }
}

// ===== PENDING RESIGNATIONS =====
$pending_resignations_count = 0;
if (in_array($user_role, ['hr', 'process_head', 'manager', 'admin', 'centre_head'])) {
    try {
        $tableCheck = $pdo->prepare("SHOW TABLES LIKE 'resignations'");
        $tableCheck->execute();
        if ($tableCheck->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM resignations WHERE status = 'pending'");
            $stmt->execute();
            $pending_resignations_count = (int)$stmt->fetchColumn();
        } else {
            $tableCheck = $pdo->prepare("SHOW TABLES LIKE 'resignation_requests'");
            $tableCheck->execute();
            if ($tableCheck->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM resignation_requests WHERE status = 'pending'");
                $stmt->execute();
                $pending_resignations_count = (int)$stmt->fetchColumn();
            }
        }
    } catch (Exception $e) {
        $pending_resignations_count = 0;
    }
}

// Open jobs count for IJP badge
$open_jobs_count = 0;
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE status = 'published' AND (closing_date IS NULL OR closing_date >= CURDATE())");
        $stmt->execute();
        $open_jobs_count = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    $open_jobs_count = 0;
}

// Pending applications count for user
$pending_apps = 0;
try {
    if (isset($pdo) && isset($user['id'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$user['id']]);
        $pending_apps = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    $pending_apps = 0;
}

// Base path
$current_script_path = dirname($_SERVER['SCRIPT_NAME']);
$base_path = str_replace(['/includes/modules/ijp', '/includes/modules', '/includes'], '', $current_script_path);
$base_path = rtrim($base_path, '/');

// IJP base path
$ijp_base = $base_path . '/includes/modules/ijp/';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <?php include __DIR__ . '/modules/styles/main_styles.php'; ?>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --white: #ffffff;
            --bg-light: #f4f6fa;
            --text-primary: #0a1628;
            --text-secondary: #1e293b;
            --text-muted: #64748b;
            --border-light: #e8edf4;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 8px 28px rgba(0, 0, 0, 0.08);
            --radius: 12px;
            --radius-sm: 8px;
            --blue: #2563eb;
            --blue-hover: #1d4ed8;
            --blue-bg: rgba(37, 99, 235, 0.06);
            --green: #16a34a;
            --green-bg: rgba(22, 163, 74, 0.08);
            --red: #dc2626;
            --red-bg: rgba(220, 38, 38, 0.08);
            --purple: #7c3aed;
            --sidebar-width: 280px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f2f6;
            color: var(--text-primary);
            min-height: 100vh;
        }

        /* ============================================
           SIDEBAR
           ============================================ */
        .sidebar-overlay {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99998;
            display: none;
            backdrop-filter: blur(4px);
        }
        .sidebar-overlay.active { display: block; }

        .sidebar {
            position: fixed;
            top: 0;
            left: -280px;
            width: var(--sidebar-width);
            height: 100%;
            background: #0a1628;
            z-index: 99999;
            transition: left 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .sidebar.open { left: 0; }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            min-height: 70px;
        }
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fff;
            font-weight: 700;
            font-size: 18px;
        }
        .sidebar-brand i { color: #2563eb; font-size: 24px; }

        .sidebar-close {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 24px;
            cursor: pointer;
            padding: 4px 8px;
            transition: all 0.3s ease;
        }
        .sidebar-close:hover { color: #fff; transform: rotate(90deg); }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .sidebar-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 18px;
            flex-shrink: 0;
        }
        .sidebar-user-name { color: #fff; font-weight: 600; font-size: 14px; }
        .sidebar-user-role { color: #94a3b8; font-size: 12px; font-weight: 500; }

        .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-item { margin-bottom: 2px; }

        .sidebar-item .sidebar-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-radius: 10px;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }
        .sidebar-item .sidebar-link:hover {
            background: rgba(37, 99, 235, 0.1);
            color: #ffffff;
        }
        .sidebar-item.active .sidebar-link {
            background: #2563eb;
            color: #fff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .sidebar-item .sidebar-link i { width: 20px; text-align: center; font-size: 16px; }
        .sidebar-item .sidebar-link span { flex: 1; }

        .sidebar-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            background: #2563eb;
            color: #fff;
        }
        .sidebar-badge.badge-warning { background: #d97706; }
        .sidebar-badge.badge-danger  { background: #dc2626; }

        .sidebar-arrow { transition: transform 0.3s ease; font-size: 12px; }
        .sidebar-item.open .sidebar-arrow { transform: rotate(90deg); }

        .sidebar-submenu {
            list-style: none;
            padding: 0;
            margin: 0;
            display: none;
            padding-left: 20px;
        }
        .sidebar-submenu.open { display: block; }
        .sidebar-submenu li .sidebar-link { padding: 8px 14px; font-size: 13px; }

        .sidebar-divider {
            padding: 12px 14px 8px;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.5;
            list-style: none;
        }

        .sidebar-footer {
            padding: 16px 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .sidebar-footer .sidebar-link {
            color: #94a3b8;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }
        .sidebar-footer .sidebar-link:hover { background: rgba(220, 38, 38, 0.1); color: #dc2626; }
        .sidebar-footer .sidebar-link i { width: 20px; text-align: center; }

        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: #2563eb; border-radius: 4px; }

        /* ============================================
           TOP HEADER
           ============================================ */
        .header {
            background: var(--white);
            border-bottom: 1px solid var(--border-light);
            height: 64px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            box-shadow: var(--shadow-sm);
            border-radius: var(--radius);
            margin: 12px 16px;
            position: sticky;
            top: 12px;
            z-index: 1100;
            gap: 8px;
        }
        .header .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            flex-shrink: 1;
            min-width: 0;
        }
        .header .logo .logo-image { height: 70px; width: auto; }
        .header .logo .logo-divider { width: 1px; height: 24px; background: var(--border-light); }
        .header .logo .logo-highlight {
            color: var(--blue);
            font-size: 20px;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .header-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ============================================
           HEADER BUTTONS
           ============================================ */
        .header-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 50px;
            background: var(--blue-bg);
            color: var(--blue);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            font-size: 13px;
            font-weight: 600;
        }
        .header-btn:hover {
            background: var(--blue);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }
        .header-btn i { font-size: 16px; }

        .header-btn .header-badge {
            background: #dc2626;
            color: #fff;
            border-radius: 50%;
            padding: 1px 7px;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
            animation: pulse-badge 2s ease-in-out infinite;
        }

        .header-btn .btn-text { display: inline; }

        .header-btn.approvals-btn { background: var(--green-bg); color: var(--green); }
        .header-btn.approvals-btn:hover { background: var(--green); color: #fff; box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3); }

        .header-btn.workforce-btn { background: #fef3c7; color: #d97706; }
        .header-btn.workforce-btn:hover { background: #d97706; color: #fff; box-shadow: 0 4px 15px rgba(217, 119, 6, 0.3); }

        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }

        /* ============================================
           HAMBURGER
           ============================================ */
        .hamburger-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
            color: var(--text-primary);
            margin-right: 4px;
        }
        .hamburger-btn:hover { background: var(--bg-light); }

        .hamburger-btn .hamburger-icon {
            display: flex;
            flex-direction: column;
            gap: 5px;
            width: 24px;
        }
        .hamburger-btn .hamburger-icon span {
            display: block;
            height: 2.5px;
            background: currentColor;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        .hamburger-btn.active .hamburger-icon span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
        .hamburger-btn.active .hamburger-icon span:nth-child(2) { opacity: 0; }
        .hamburger-btn.active .hamburger-icon span:nth-child(3) { transform: rotate(-45deg) translate(5px, -5px); }

        /* ============================================
           USER PROFILE
           ============================================ */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 7px;
            border-radius: 50px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
            background: var(--bg-light);
        }
        .user-profile:hover { border-color: var(--blue); background: var(--blue-bg); }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), var(--purple));
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .user-avatar .avatar-inner {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            color: var(--blue);
        }

        .user-info .name { font-size: 13px; font-weight: 700; color: var(--text-primary); }
        .user-info .username { font-size: 11px; color: var(--text-muted); font-weight: 500; }
        .user-profile .chevron { color: var(--text-muted); font-size: 12px; transition: all 0.3s ease; }

        .profile-dropdown-content {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: var(--white);
            min-width: 180px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            border-radius: var(--radius);
            padding: 6px 0;
            z-index: 99999;
            border: 1px solid var(--border-light);
        }
        .profile-dropdown-content.show { display: block; animation: dropdownSlide 0.3s ease; }

        @keyframes dropdownSlide {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-dropdown-content a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 18px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 13px;
            font-weight: 600;
        }
        .profile-dropdown-content a:hover { background: var(--blue-bg); color: var(--blue); }
        .profile-dropdown-content a i { width: 30px; color: var(--blue); font-size: 16px; }
        .profile-dropdown-content a.danger { color: var(--red); }
        .profile-dropdown-content a.danger i { color: var(--red); }
        .profile-dropdown-content a.danger:hover { background: var(--red-bg); }

        /* ============================================
           MAIN CONTENT
           ============================================ */
        .main-content { padding: 0 20px 20px 20px; }
        .container { max-width: 1400px; margin: 0 auto; }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 992px) {
            .header-btn .btn-text { display: none; }
            .header-btn { padding: 8px 12px; }
        }
        @media (max-width: 768px) {
            .header { padding: 0 16px; height: 56px; margin: 8px 12px 12px 12px; }
            .header .logo .logo-image { height: 32px; }
            .header .logo .logo-highlight { font-size: 16px; }
            .header .logo .logo-divider { height: 24px; }
            .user-info .name { font-size: 12px; }
            .user-avatar { width: 32px; height: 32px; }
            .user-avatar .avatar-inner { font-size: 11px; }
            .user-profile { padding: 3px 12px 3px 3px; gap: 8px; }
            .main-content { padding: 0 12px 12px 12px; }
            .header-btn .btn-text { display: none; }
            .header-btn { padding: 6px 10px; }
        }
        @media (max-width: 480px) {
            .user-info .name { font-size: 10px; max-width: 60px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .user-info .username { display: none; }
            .user-profile { padding: 2px 8px 2px 2px; }
            .user-avatar { width: 28px; height: 28px; }
            .header .logo .logo-highlight { font-size: 13px; }
            .header .logo .logo-image { height: 26px; }
            .header .logo .logo-divider { height: 20px; }
            .hamburger-btn { padding: 4px 8px; }
            .hamburger-btn .hamburger-icon { width: 20px; gap: 4px; }
            .header-btn { padding: 4px 8px; font-size: 12px; }
            .header-btn i { font-size: 14px; }
        }
    </style>
</head>

<body>

    <!-- ========================================== -->
    <!-- SIDEBAR -->
    <!-- ========================================== -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <span>ATTENDANCE</span>
            </div>
            <button class="sidebar-close" onclick="closeSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="sidebar-user">
            <div class="sidebar-avatar"><?php echo $user_initials; ?></div>
            <div>
                <div class="sidebar-user-name"><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></div>
                <div class="sidebar-user-role"></div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <ul class="sidebar-menu">
                <!-- Dashboard -->
                <li class="sidebar-item <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>/dashboard.php" class="sidebar-link">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <!-- Holiday Calendar -->
                <li class="sidebar-item <?php echo $current_page === 'holidays.php' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>/holidays.php" class="sidebar-link">
                        <i class="fas fa-calendar-alt" style="color: #d97706;"></i>
                        <span>Holiday Calendar</span>
                    </a>
                </li>

                <!-- OT (only for FNM10824) -->
                <?php if ($can_view_ot): ?>
                <li class="sidebar-item <?php echo $current_page === 'ot_data.php' ? 'active' : ''; ?>">
                    <a href="javascript:void(0)" class="sidebar-link" onclick="openOTReportModal()">
                        <i class="fas fa-clock" style="color: #d97706;"></i>
                        <span>Overtime (OT)</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Approval History -->
                <li class="sidebar-item <?php echo $current_page === 'request_history.php' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>/request_history.php" class="sidebar-link">
                        <i class="fas fa-history"></i>
                        <span>Approval History</span>
                    </a>
                </li>

                <!-- IJP -->
                <li class="sidebar-item <?php echo $is_ijp_page ? 'active' : ''; ?>" id="ijpMenu">
                    <a href="#" class="sidebar-link" onclick="toggleSubMenu(event)">
                        <i class="fas fa-briefcase" style="color: #4e73df;"></i>
                        <span>Internal Jobs</span>
                        <?php if ($open_jobs_count > 0): ?>
                            <span class="sidebar-badge"><?php echo $open_jobs_count; ?></span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-right sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu <?php echo $is_ijp_page ? 'open' : ''; ?>" id="ijpSubmenu">
                        <li>
                            <a href="<?php echo $ijp_base; ?>jobs.php" class="sidebar-link">
                                <i class="fas fa-search"></i>
                                <span>IJP</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $ijp_base; ?>my_applications.php" class="sidebar-link">
                                <i class="fas fa-file-alt"></i>
                                <span>Applied Jobs</span>
                                <?php if ($pending_apps > 0): ?>
                                    <span class="sidebar-badge badge-warning"><?php echo $pending_apps; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php if ($is_hr_er): ?>
                            <li style="list-style: none; padding: 4px 14px; color: #94a3b8; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.5; margin-top: 4px;">
                                --- Management ---
                            </li>
                            <li>
                                <a href="<?php echo $ijp_base; ?>manage_jobs.php" class="sidebar-link">
                                    <i class="fas fa-list"></i>
                                    <span>Manage Jobs</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo $ijp_base; ?>create_job.php" class="sidebar-link">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Post New Job</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </li>

                <!-- My Resignation -->
                <li class="sidebar-item <?php echo $current_page === 'my_resignation.php' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>/my_resignation.php" class="sidebar-link">
                        <i class="fas fa-user-slash"></i>
                        <span>My Resignation</span>
                        <?php
                        if (file_exists(__DIR__ . '/modules/resignation/resignation_functions.php')) {
                            require_once __DIR__ . '/modules/resignation/resignation_functions.php';
                            if (function_exists('hasPendingResignation') && hasPendingResignation($pdo, $user['id'] ?? 0)) {
                                echo '<span class="sidebar-badge badge-warning">Pending</span>';
                            }
                        }
                        ?>
                    </a>
                </li>

                <!-- All Resignations -->
                <?php if (in_array($user_role, ['hr', 'process_head', 'manager', 'centre_head', 'admin'])): ?>
                    <li class="sidebar-item <?php echo $current_page === 'resignations.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>/resignations.php" class="sidebar-link">
                            <i class="fas fa-users"></i>
                            <span>All Resignations</span>
                            <?php if ($pending_resignations_count > 0): ?>
                                <span class="sidebar-badge badge-danger"><?php echo $pending_resignations_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Payroll Export -->
                <?php if ($can_export_payroll): ?>
                <li class="sidebar-item">
                    <a href="javascript:void(0)" class="sidebar-link" onclick="openExportModal()">
                        <i class="fas fa-file-excel" style="color: #16a34a;"></i>
                        <span>Payroll Export</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Company Policy -->
                <li class="sidebar-item <?php echo $current_page === 'policy.php' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>/policy.php" class="sidebar-link">
                        <i class="fas fa-file-contract" style="color: #7c3aed;"></i>
                        <span>Company Policy</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- ========================================== -->
    <!-- TOP HEADER BAR -->
    <!-- ========================================== -->
    <div class="main-content">
        <header class="header">
            <a href="<?php echo $base_path; ?>/dashboard.php" class="logo">
                <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar(event)" aria-label="Toggle Sidebar">
                    <div class="hamburger-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </button>
                <img src="<?php echo $base_path; ?>/Blue.png" alt="<?php echo SITE_NAME; ?>" class="logo-image">
                <span class="logo-divider"></span>
                <span class="logo-highlight">ATTENDANCE</span>
            </a>

            <div class="header-right">
                <!-- OT Header Button (only for FNM10824) -->
                <?php if ($can_view_ot): ?>
                <a href="javascript:void(0)" class="header-btn workforce-btn" onclick="openOTReportModal()" title="Overtime">
                    <i class="fas fa-clock"></i>
                    <span class="btn-text">OT</span>
                </a>
                <?php endif; ?>

                <!-- IJP Button -->
                <a href="<?php echo $ijp_base; ?>jobs.php" class="header-btn" title="Internal Job Posting">
                    <i class="fas fa-briefcase"></i>
                    <span class="btn-text">Internal Jobs</span>
                    <?php if ($open_jobs_count > 0): ?>
                        <span class="header-badge"><?php echo $open_jobs_count; ?></span>
                    <?php endif; ?>
                </a>

                <!-- User Profile -->
                <div class="user-profile" onclick="toggleProfileDropdown(event)">
                    <div class="user-avatar">
                        <div class="avatar-inner"><?php echo $user_initials; ?></div>
                    </div>
                    <div class="user-info">
                        <span class="name"><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></span>
                        <span class="username"><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></span>
                    </div>
                    <i class="fas fa-chevron-down chevron" id="profileChevron"></i>

                    <div class="profile-dropdown-content" id="userDropdown">
                        <a href="<?php echo $base_path; ?>/reset_password.php">
                            <i class="fas fa-key"></i> RESET PASSWORD
                        </a>
                        <hr style="margin: 4px 16px; border-color: var(--border-light);">
                        <a href="<?php echo $base_path; ?>/logout.php" class="danger" onclick="return confirmLogout();">
                            <i class="fas fa-sign-out-alt"></i> LOGOUT
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Payroll Export Modal -->
        <?php if ($can_export_payroll): ?>
        <div id="exportModal" class="sidebar-overlay" style="display:none; justify-content: center; align-items: center;">
            <div style="background: white; padding: 25px; border-radius: 15px; width: 380px; position: relative; box-shadow: var(--shadow-md);">
                <h3 style="margin-bottom: 15px; font-size: 18px; color: #0a1628;">
                    <i class="fas fa-file-invoice-dollar" style="color:#16a34a; margin-right:10px;"></i> Export Payroll
                </h3>

                <label style="display:block; margin-bottom: 5px; font-size: 12px; font-weight: 700; color:#64748b;">SELECT PERIOD:</label>
                <select id="exportRange" onchange="toggleCustomDate()" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 15px; font-weight:600;">
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="custom">Specific Month</option>
                </select>

                <div id="customDateGroup" style="display:none; margin-bottom: 15px;">
                    <label style="display:block; margin-bottom: 5px; font-size: 12px; font-weight: 700; color:#64748b;">SELECT MONTH/YEAR:</label>
                    <input type="month" id="exportMonthYear" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                </div>

                <label style="display:block; margin-bottom: 5px; font-size: 12px; font-weight: 700; color:#64748b;">STAFF CATEGORY:</label>
                <select id="exportCategory" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; font-weight:600;">
                    <option value="all">All Employees (Full Report)</option>
                    <option value="csr">CSR / Agents Only</option>
                    <option value="support">Support / Management Only</option>
                </select>

                <div style="display: flex; gap: 10px;">
                    <button onclick="processExport()" style="flex:1; background: linear-gradient(135deg, #16a34a, #22c55e); color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: 700; box-shadow: 0 4px 12px rgba(22,163,74,0.2);">
                        <i class="fas fa-download"></i> DOWNLOAD
                    </button>
                    <button onclick="closeExportModal()" style="flex:1; background: #f1f5f9; color: #64748b; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: 700;">CANCEL</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========================================== -->
        <!-- PAGE CONTENT STARTS HERE -->
        <!-- ========================================== -->
        <div class="container">
            <script>
                // ============================================
                // SIDEBAR FUNCTIONS
                // ============================================
                function openSidebar() {
                    document.getElementById('sidebar').classList.add('open');
                    document.getElementById('sidebarOverlay').classList.add('active');
                    document.body.style.overflow = 'hidden';
                    document.getElementById('hamburgerBtn').classList.add('active');
                }

                function closeSidebar() {
                    document.getElementById('sidebar').classList.remove('open');
                    document.getElementById('sidebarOverlay').classList.remove('active');
                    document.body.style.overflow = '';
                    document.getElementById('hamburgerBtn').classList.remove('active');
                }

                function toggleSidebar(event) {
                    if (event) { event.preventDefault(); event.stopPropagation(); }
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar.classList.contains('open')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                }

                function toggleSubMenu(event) {
                    if (event) { event.preventDefault(); }
                    const parent = event.currentTarget.closest('.sidebar-item');
                    if (parent) {
                        parent.classList.toggle('open');
                        const submenu = parent.querySelector('.sidebar-submenu');
                        if (submenu) { submenu.classList.toggle('open'); }
                    }
                }

                // ============================================
                // PROFILE DROPDOWN
                // ============================================
                function toggleProfileDropdown(event) {
                    if (event) { event.stopPropagation(); }
                    var dropdown = document.getElementById('userDropdown');
                    var chevron = document.getElementById('profileChevron');
                    dropdown.classList.toggle('show');
                    if (chevron) {
                        chevron.style.transform = dropdown.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
                    }
                }

                function confirmLogout() {
                    return confirm('Are you sure you want to logout?');
                }

                // Close dropdown / sidebar on outside click
                document.addEventListener('click', function(event) {
                    var dropdown = document.getElementById('userDropdown');
                    var profile = document.querySelector('.user-profile');
                    if (profile && !profile.contains(event.target)) {
                        dropdown.classList.remove('show');
                        var chevron = document.getElementById('profileChevron');
                        if (chevron) { chevron.style.transform = 'rotate(0deg)'; }
                    }

                    var sidebar = document.getElementById('sidebar');
                    var hamburger = document.getElementById('hamburgerBtn');
                    if (sidebar && sidebar.classList.contains('open')) {
                        if (!sidebar.contains(event.target) && !hamburger.contains(event.target)) {
                            closeSidebar();
                        }
                    }
                });

                // Escape key closes everything
                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        var dropdown = document.getElementById('userDropdown');
                        var chevron = document.getElementById('profileChevron');
                        if (dropdown) dropdown.classList.remove('show');
                        if (chevron) chevron.style.transform = 'rotate(0deg)';
                        closeSidebar();
                        if (typeof closeOTReportModal === 'function') closeOTReportModal();
                        if (typeof closeExportModal === 'function')    closeExportModal();
                    }
                });

                // Prevent dropdown closing on internal clicks
                document.getElementById('userDropdown').addEventListener('click', function(event) {
                    event.stopPropagation();
                });

                // Auto-open IJP submenu if on IJP page
                document.addEventListener('DOMContentLoaded', function() {
                    if (window.location.pathname.includes('modules/ijp')) {
                        var ijpMenu = document.getElementById('ijpMenu');
                        if (ijpMenu) {
                            ijpMenu.classList.add('open');
                            var submenu = document.getElementById('ijpSubmenu');
                            if (submenu) { submenu.classList.add('open'); }
                        }
                    }
                });

                // ============================================
                // PAYROLL EXPORT MODAL
                // ============================================
                function openExportModal() {
                    var modal = document.getElementById('exportModal');
                    if (!modal) return;
                    modal.style.display = 'flex';
                    document.getElementById('sidebarOverlay').classList.add('active');
                }

                function closeExportModal() {
                    var modal = document.getElementById('exportModal');
                    if (!modal) return;
                    modal.style.display = 'none';
                    document.getElementById('sidebarOverlay').classList.remove('active');
                }

                function toggleCustomDate() {
                    var range = document.getElementById('exportRange');
                    var group = document.getElementById('customDateGroup');
                    if (!range || !group) return;
                    group.style.display = (range.value === 'custom') ? 'block' : 'none';
                }

                function processExport() {
                    var rangeEl = document.getElementById('exportRange');
                    var catEl = document.getElementById('exportCategory');
                    if (!rangeEl || !catEl) return;

                    var range = rangeEl.value;
                    var category = catEl.value;
                    var d = new Date();

                    if (range === 'yesterday') {
                        d.setDate(d.getDate() - 1);
                    } else if (range === 'custom') {
                        var val = document.getElementById('exportMonthYear').value;
                        if (!val) { alert('Please select a month'); return; }
                        d = new Date(val + "-01");
                    }

                    var m = d.getMonth() + 1;
                    var y = d.getFullYear();

                    window.location.href = '<?php echo $base_path; ?>/export.php?month=' + m + '&year=' + y + '&category=' + category;
                }
            </script>

            <!-- OT Modals (submit form + report viewer) -->
            <?php
            $ot_modal_file = __DIR__ . '/modules/modals/ot_modal.php';
            if (file_exists($ot_modal_file)) {
                include $ot_modal_file;
            }
            ?>

            <!-- Main JS — loaded on every page -->
            <?php
            $main_js_file = __DIR__ . '/modules/scripts/main_scripts.php';
            if (file_exists($main_js_file)) {
                include $main_js_file;
            }
            ?>