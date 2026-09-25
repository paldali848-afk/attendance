<?php
// ============================================
// HOLIDAY CALENDAR PAGE - PROFESSIONAL TABLE VIEW
// ============================================

require_once __DIR__ . '/includes/modules/config/bootstrap.php';
require_once __DIR__ . '/includes/modules/auth/authentication.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();

// Get selected year
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Get previous and next year
$prev_year = $selected_year - 1;
$next_year = $selected_year + 1;

// Get all holidays for the selected year
$all_holidays = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM holidays WHERE YEAR(holiday_date) = ? ORDER BY holiday_date ASC");
    $stmt->execute([$selected_year]);
    $all_holidays = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_holidays = [];
}

// Group holidays by month
$holidays_by_month = [];
$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Initialize all months with empty arrays
for ($m = 1; $m <= 12; $m++) {
    $holidays_by_month[$m] = [];
}

// Populate with actual holidays
foreach ($all_holidays as $holiday) {
    $month = (int)date('n', strtotime($holiday['holiday_date']));
    $holidays_by_month[$month][] = $holiday;
}

// Count total holidays
$total_holidays = count($all_holidays);

// Check if user has permission to upload holidays
$can_upload = in_array($user['role'] ?? '', ['hr', 'centre_head', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holiday Calendar - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           ROOT VARIABLES & RESET
           ============================================ */
        :root {
            --primary: #1a56db;
            --primary-light: #e8effd;
            --primary-dark: #1e3a5f;
            --secondary: #64748b;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --radius: 10px;
            --radius-sm: 6px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.5;
        }
        
        /* ============================================
           MAIN CONTENT
           ============================================ */
        .main-content {
            padding: 12px 24px 20px 24px;
            margin-top: 12px;
        }
        
        .container {
            max-width: 1440px;
            margin: 0 auto;
        }
        
        /* ============================================
           PAGE HEADER - COMPACT
           ============================================ */
        .page-header {
            background: #ffffff;
            border-radius: var(--radius);
            padding: 12px 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border: 1px solid var(--gray-200);
        }
        
        .page-header .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header .header-icon {
            width: 36px;
            height: 36px;
            background: var(--primary-light);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 16px;
        }
        
        .page-header h1 {
            font-size: 18px;
            font-weight: 700;
            color: var(--gray-900);
            letter-spacing: -0.3px;
        }
        
        .page-header h1 small {
            font-weight: 400;
            font-size: 13px;
            color: var(--gray-500);
            margin-left: 6px;
        }
        
        .page-header .header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .btn {
            padding: 6px 16px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            font-family: 'Inter', sans-serif;
        }
        
        .btn-primary {
            background: var(--primary);
            color: #ffffff;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.35);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }
        
        .btn-outline:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
        }
        
        /* ============================================
           YEAR NAVIGATION - COMPACT
           ============================================ */
        .year-nav {
            background: #ffffff;
            border-radius: var(--radius);
            padding: 10px 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border: 1px solid var(--gray-200);
        }
        
        .year-nav .nav-group {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        
        .year-nav .nav-btn {
            padding: 5px 12px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--gray-700);
            font-weight: 500;
            font-size: 12px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .year-nav .nav-btn:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }
        
        .year-nav .nav-btn.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }
        
        .year-nav .current-year {
            font-size: 17px;
            font-weight: 700;
            color: var(--gray-900);
            letter-spacing: -0.3px;
        }
        
        .year-nav .current-year i {
            color: var(--primary);
            margin-right: 8px;
            font-size: 15px;
        }
        
        .year-nav .year-stats {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 12px;
            color: var(--gray-600);
        }
        
        .year-nav .year-stats .stat-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .year-nav .year-stats .stat-item strong {
            color: var(--gray-900);
            font-weight: 700;
        }
        
        .year-nav .year-stats .stat-divider {
            width: 1px;
            height: 18px;
            background: var(--gray-200);
        }
        
        /* ============================================
           QUICK STATS BANNER - COMPACT
           ============================================ */
        .stats-banner {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .stat-card {
            background: #ffffff;
            border-radius: var(--radius);
            padding: 10px 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--gray-500);
            margin-bottom: 2px;
        }
        
        .stat-card .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .stat-card .stat-value .stat-unit {
            font-size: 12px;
            font-weight: 400;
            color: var(--gray-500);
            margin-left: 3px;
        }
        
        .stat-card .stat-icon {
            float: right;
            font-size: 22px;
            opacity: 0.12;
            color: var(--primary);
        }
        
        .stat-card.stat-primary .stat-icon { color: var(--primary); }
        .stat-card.stat-success .stat-icon { color: var(--success); }
        .stat-card.stat-warning .stat-icon { color: var(--warning); }
        .stat-card.stat-danger .stat-icon { color: var(--danger); }
        
        /* ============================================
           HOLIDAYS TABLE - COMPACT
           ============================================ */
        .table-container {
            background: #ffffff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .table-header {
            padding: 10px 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            background: var(--gray-50);
        }
        
        .table-header .table-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-header .table-title i {
            color: var(--primary);
            font-size: 14px;
        }
        
        .table-header .table-tools {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        
        .table-tools .tool-btn {
            padding: 4px 12px;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            background: #ffffff;
            font-size: 11px;
            font-weight: 500;
            color: var(--gray-600);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .table-tools .tool-btn:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
        }
        
        .table-wrapper {
            overflow-x: auto;
            padding: 0;
        }
        
        .holidays-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .holidays-table thead {
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .holidays-table thead th {
            padding: 8px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--gray-600);
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--gray-50);
        }
        
        .holidays-table thead th:first-child {
            padding-left: 20px;
        }
        
        .holidays-table thead th:last-child {
            padding-right: 20px;
        }
        
        .holidays-table tbody tr {
            transition: var(--transition);
            border-bottom: 1px solid var(--gray-100);
        }
        
        .holidays-table tbody tr:last-child {
            border-bottom: none;
        }
        
        .holidays-table tbody tr:hover {
            background: var(--gray-50);
        }
        
        .holidays-table tbody td {
            padding: 8px 16px;
            vertical-align: middle;
        }
        
        .holidays-table tbody td:first-child {
            padding-left: 20px;
        }
        
        .holidays-table tbody td:last-child {
            padding-right: 20px;
        }
        
        /* Month Column */
        .holidays-table .month-cell {
            width: 14%;
            min-width: 110px;
        }
        
        .holidays-table .month-name {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 14px;
        }
        
        .holidays-table .month-count {
            display: block;
            font-size: 11px;
            font-weight: 500;
            color: var(--gray-500);
            margin-top: 1px;
        }
        
        .holidays-table .month-count i {
            margin-right: 3px;
            font-size: 9px;
        }
        
        .holidays-table .month-count .count-badge {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0px 8px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 3px;
        }
        
        .holidays-table .month-count .count-badge.zero {
            background: var(--gray-100);
            color: var(--gray-400);
        }
        
        /* Holiday Items - Compact */
        .holidays-table .holiday-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .holidays-table .holiday-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 10px;
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            transition: var(--transition);
            border-left: 2px solid var(--warning);
        }
        
        .holidays-table .holiday-item:hover {
            background: var(--primary-light);
            border-left-color: var(--primary);
        }
        
        .holidays-table .holiday-item .holiday-date {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 12px;
            min-width: 65px;
            white-space: nowrap;
        }
        
        .holidays-table .holiday-item .holiday-date i {
            color: var(--primary);
            margin-right: 4px;
            font-size: 11px;
        }
        
        .holidays-table .holiday-item .holiday-name {
            font-weight: 500;
            color: var(--gray-800);
            flex: 1;
            font-size: 13px;
        }
        
        .holidays-table .holiday-item .holiday-name i {
            color: var(--warning);
            margin-right: 4px;
            font-size: 11px;
        }
        
        .holidays-table .holiday-item .holiday-desc {
            color: var(--gray-500);
            font-size: 11px;
            padding-left: 6px;
            border-left: 1px solid var(--gray-200);
        }
        
        .holidays-table .holiday-item .holiday-type {
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 1px 8px;
            border-radius: 50px;
            background: #fef3c7;
            color: #78350f;
            white-space: nowrap;
        }
        
        /* No Holidays */
        .holidays-table .no-holidays {
            padding: 4px 10px;
            color: var(--gray-400);
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .holidays-table .no-holidays i {
            font-size: 14px;
        }
        
        /* Empty Month Row */
        .holidays-table tbody tr.month-empty td {
            opacity: 0.6;
        }
        
        .holidays-table tbody tr.month-empty .month-name {
            color: var(--gray-400);
        }
        
        /* ============================================
           FOOTER / LEGEND - COMPACT
           ============================================ */
        .table-footer {
            padding: 8px 20px;
            border-top: 1px solid var(--gray-200);
            background: var(--gray-50);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .table-footer .footer-info {
            font-size: 12px;
            color: var(--gray-500);
        }
        
        .table-footer .footer-info strong {
            color: var(--gray-700);
        }
        
        .legend {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            color: var(--gray-600);
        }
        
        .legend-item .legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 2px;
            display: inline-block;
        }
        
        .legend-item .legend-dot.holiday {
            background: var(--warning);
        }
        
        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 1024px) {
            .holidays-table .holiday-item {
                flex-wrap: wrap;
                gap: 2px 10px;
            }
            
            .holidays-table .holiday-item .holiday-desc {
                padding-left: 0;
                border-left: none;
                width: 100%;
                margin-left: 16px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 10px 12px 16px 12px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: stretch;
                padding: 12px 16px;
            }
            
            .page-header .header-actions {
                flex-wrap: wrap;
            }
            
            .page-header .header-actions .btn {
                flex: 1;
                justify-content: center;
            }
            
            .year-nav {
                flex-direction: column;
                align-items: stretch;
                padding: 10px 16px;
                gap: 8px;
            }
            
            .year-nav .nav-group {
                justify-content: center;
            }
            
            .year-nav .current-year {
                text-align: center;
                font-size: 16px;
            }
            
            .year-nav .year-stats {
                justify-content: center;
                flex-wrap: wrap;
                gap: 6px;
            }
            
            .year-nav .year-stats .stat-divider {
                display: none;
            }
            
            .stats-banner {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            
            .stat-card {
                padding: 8px 12px;
            }
            
            .stat-card .stat-value {
                font-size: 17px;
            }
            
            .stat-card .stat-icon {
                font-size: 18px;
            }
            
            .table-header {
                flex-direction: column;
                align-items: stretch;
                padding: 10px 16px;
            }
            
            .table-header .table-tools {
                flex-wrap: wrap;
            }
            
            .holidays-table thead th,
            .holidays-table tbody td {
                padding: 6px 12px;
            }
            
            .holidays-table thead th:first-child,
            .holidays-table tbody td:first-child {
                padding-left: 12px;
            }
            
            .holidays-table thead th:last-child,
            .holidays-table tbody td:last-child {
                padding-right: 12px;
            }
            
            .holidays-table .month-cell {
                width: 20%;
                min-width: 80px;
            }
            
            .holidays-table .month-name {
                font-size: 13px;
            }
            
            .holidays-table .holiday-item {
                padding: 3px 8px;
                gap: 6px;
                flex-wrap: wrap;
            }
            
            .holidays-table .holiday-item .holiday-date {
                min-width: 55px;
                font-size: 11px;
            }
            
            .holidays-table .holiday-item .holiday-name {
                font-size: 12px;
            }
            
            .holidays-table .holiday-item .holiday-desc {
                font-size: 10px;
                margin-left: 0;
                padding-left: 0;
                border-left: none;
            }
            
            .holidays-table .holiday-item .holiday-type {
                font-size: 8px;
                padding: 0px 6px;
            }
            
            .table-footer {
                flex-direction: column;
                align-items: stretch;
                padding: 8px 16px;
                text-align: center;
            }
            
            .legend {
                justify-content: center;
                gap: 10px;
            }
            
            .page-header h1 {
                font-size: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 6px 8px 12px 8px;
            }
            
            .stats-banner {
                grid-template-columns: 1fr 1fr;
                gap: 6px;
            }
            
            .stat-card {
                padding: 6px 10px;
            }
            
            .stat-card .stat-value {
                font-size: 15px;
            }
            
            .holidays-table {
                font-size: 11px;
            }
            
            .holidays-table thead th {
                font-size: 9px;
                padding: 4px 8px;
            }
            
            .holidays-table tbody td {
                padding: 4px 8px;
            }
            
            .holidays-table .month-name {
                font-size: 12px;
            }
            
            .holidays-table .holiday-item {
                padding: 2px 6px;
                gap: 4px;
            }
            
            .holidays-table .holiday-item .holiday-date {
                min-width: 45px;
                font-size: 10px;
            }
            
            .holidays-table .holiday-item .holiday-name {
                font-size: 10px;
            }
            
            .holidays-table .holiday-item .holiday-desc {
                font-size: 9px;
            }
            
            .holidays-table .month-count .count-badge {
                font-size: 9px;
                padding: 0px 6px;
            }
            
            .page-header h1 {
                font-size: 14px;
            }
            
            .page-header .header-icon {
                width: 30px;
                height: 30px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="main-content">
    <div class="container">
        
        <!-- ===== PAGE HEADER ===== -->
        <div class="page-header">
            <div class="header-left">
                <div class="header-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div>
                    <h1>
                        Holiday Calendar
                        <small><?php echo $selected_year; ?></small>
                    </h1>
                </div>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
        
        <!-- ===== YEAR NAVIGATION ===== -->
        <div class="year-nav">
            <div class="nav-group">
                <a href="?year=<?php echo $prev_year; ?>" class="nav-btn">
                    <i class="fas fa-chevron-left"></i> <?php echo $prev_year; ?>
                </a>
                <a href="?year=<?php echo date('Y'); ?>" class="nav-btn <?php echo $selected_year == date('Y') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar"></i> Current
                </a>
                <a href="?year=<?php echo $next_year; ?>" class="nav-btn">
                    <?php echo $next_year; ?> <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <div class="current-year">
                <i class="fas fa-calendar-alt"></i> <?php echo $selected_year; ?>
            </div>
            
            <div class="year-stats">
                <span class="stat-item">
                    <i class="fas fa-calendar-check" style="color:var(--primary);"></i>
                    <strong><?php echo $total_holidays; ?></strong> Total
                </span>
                <span class="stat-divider"></span>
                <span class="stat-item">
                    <i class="fas fa-calendar-day" style="color:var(--warning);"></i>
                    <strong><?php 
                        $months_with_holidays = 0;
                        for ($m = 1; $m <= 12; $m++) {
                            if (!empty($holidays_by_month[$m])) $months_with_holidays++;
                        }
                        echo $months_with_holidays; 
                    ?></strong> Months
                </span>
            </div>
        </div>
        
      
        
        <!-- ===== HOLIDAYS TABLE ===== -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list"></i>
                    Yearly Holiday Schedule
                </div>
                
            </div>
            
            <div class="table-wrapper">
                <table class="holidays-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Holidays</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $has_any_holidays = false;
                        for ($m = 1; $m <= 12; $m++):
                            $month_holidays = $holidays_by_month[$m];
                            $month_name = $month_names[$m];
                            $count = count($month_holidays);
                            $has_holidays = $count > 0;
                            
                            if ($has_holidays) {
                                $has_any_holidays = true;
                            }
                        ?>
                            <tr class="<?php echo $has_holidays ? '' : 'month-empty'; ?>">
                                <td class="month-cell">
                                    <div class="month-name"><?php echo $month_name; ?></div>
                                    <div class="month-count">
                                        <i class="fas <?php echo $has_holidays ? 'fa-star' : 'fa-circle'; ?>" 
                                           style="color:<?php echo $has_holidays ? 'var(--warning)' : 'var(--gray-300)'; ?>;"></i>
                                        <span class="count-badge <?php echo $has_holidays ? '' : 'zero'; ?>">
                                            <?php echo $count; ?> holiday<?php echo $count != 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($has_holidays): ?>
                                        <div class="holiday-list">
                                            <?php foreach ($month_holidays as $holiday): ?>
                                                <div class="holiday-item">
                                                    <span class="holiday-date">
                                                        <i class="fas fa-calendar-day"></i>
                                                        <?php echo date('d M', strtotime($holiday['holiday_date'])); ?>
                                                    </span>
                                                    <span class="holiday-name">
                                                        <i class="fas fa-gift"></i>
                                                        <?php echo htmlspecialchars($holiday['holiday_name']); ?>
                                                    </span>
                                                    <?php if (!empty($holiday['description'])): ?>
                                                        <span class="holiday-desc">
                                                            <?php echo htmlspecialchars($holiday['description']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="holiday-type">Holiday</span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-holidays">
                                            <i class="fas fa-calendar-minus"></i>
                                            No holidays scheduled
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endfor; ?>
                        
                        <?php if (!$has_any_holidays): ?>
                            <tr>
                                <td colspan="2" style="text-align:center;padding:32px 20px;color:var(--gray-400);">
                                    <i class="fas fa-calendar-times" style="font-size:28px;display:block;margin-bottom:8px;opacity:0.3;"></i>
                                    <p style="font-size:14px;font-weight:500;">No holidays found for <?php echo $selected_year; ?></p>
                                    <p style="font-size:12px;margin-top:2px;">Holidays will appear here once they are added</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="table-footer">
                <div class="footer-info">
                    <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                    Showing <strong><?php echo $total_holidays; ?></strong> holidays for <strong><?php echo $selected_year; ?></strong>
                </div>
                <div class="legend">
                    <span class="legend-item">
                        <span class="legend-dot holiday"></span>
                        Holiday
                    </span>
                    <span class="legend-item">
                        <i class="fas fa-star" style="color:var(--warning);font-size:10px;"></i>
                        Month with holidays
                    </span>
                    <span class="legend-item">
                        <i class="fas fa-calendar-day" style="color:var(--primary);font-size:10px;"></i>
                        Date
                    </span>
                    <span class="legend-item">
                        <i class="fas fa-gift" style="color:var(--warning);font-size:10px;"></i>
                        Holiday name
                    </span>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Holiday Calendar (Professional Table View) loaded successfully!');
});
</script>

</body>
</html>