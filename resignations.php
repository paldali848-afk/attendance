<?php
// ============================================
// RESIGNATIONS MANAGEMENT PAGE
// ============================================

require_once __DIR__ . '/includes/modules/config/bootstrap.php';
require_once __DIR__ . '/includes/modules/auth/authentication.php';
require_once __DIR__ . '/includes/modules/resignation/resignation_functions.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: index.php');
    exit;
}

// Get user role and permission
$user_role = $user['role'] ?? '';
$user_id = $user['id'] ?? 0;
$user_permission = getUserResignationPermission($pdo, $user_id, $user_role);

// Check permission - only these roles can access this page
$allowed_roles = ['hr', 'process_head', 'manager', 'centre_head', 'admin', 'am', 'tl'];
if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php');
    exit;
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';
$filters = [];
if (!empty($status_filter)) {
    $filters['status'] = $status_filter;
}
if (!empty($search_filter)) {
    $filters['search'] = $search_filter;
}

$resignations = getResignations($pdo, $filters, $user_role, $user_id);
// 1. Get the basic data
$is_designated_hr = isDesignatedHrApprover($pdo, $user_id);
$is_process_head = isProcessHead($pdo, $user_id);
// --- ADD THESE TWO LINES HERE ---
$user_dept = strtolower($user['department'] ?? '');
$is_hr_er_dept = in_array($user_dept, ['hr', 'er', 'human resources', 'employee relations']);
$stats = getResignationStats($pdo, $user_id, $user_role);

// 2. STRICT FILTERING: If NOT Admin/HR, silo the counts to THIS manager's team only
if ($user_role !== 'admin' && $user_role !== 'hr' && !$is_designated_hr) {
    try {
        // This query finds employees where YOU are the boss at any level (up to 3 deep)
        $stmt_stats = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN r.status = 'pending' AND r.approval_level = 'process_head' THEN 1 ELSE 0 END) as pending_process_head,
                SUM(CASE WHEN r.status = 'pending' AND r.approval_level = 'hr' THEN 1 ELSE 0 END) as pending_hr
            FROM resignations r
            JOIN users u ON r.user_id = u.id
            WHERE (
                u.id = ? OR 
                u.reporting_to = ? OR 
                u.reporting_to IN (SELECT id FROM users WHERE reporting_to = ?) OR
                u.reporting_to IN (SELECT id FROM users WHERE reporting_to IN (SELECT id FROM users WHERE reporting_to = ?))
            )
        ");
        // We pass the logged-in user ID 4 times to fill the hierarchy levels
        $stmt_stats->execute([$user_id, $user_id, $user_id, $user_id]);
        $filtered_stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

        if ($filtered_stats) {
            $stats = [
                'total' => (int)$filtered_stats['total'],
                'pending' => (int)$filtered_stats['pending'],
                'approved' => (int)$filtered_stats['approved'],
                'rejected' => (int)$filtered_stats['rejected'],
                'pending_process_head' => (int)$filtered_stats['pending_process_head'],
                'pending_hr' => (int)$filtered_stats['pending_hr']
            ];
        }
    } catch (Exception $e) {
        // Fallback to global if error occurs
    }
}


include __DIR__ . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Resignations Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/includes/modules/styles/main_styles.php'; ?>
    <style>
        .resignation-detail-modal {
    /* Keep this one - it hides the modal by default */
    display: none; 
    
    position: fixed;
    top: 85px; 
    left: 0;
    width: 100%;
    height: calc(100vh - 85px); 
    background: rgba(0, 0, 0, 0.4);
    z-index: 1000;
    backdrop-filter: blur(4px);
    padding: 20px;
    box-sizing: border-box;
    
    /* REMOVE "display: flex;" FROM HERE */
    
    /* Keep these - they will only work when JavaScript opens the modal as flex */
    justify-content: center;
    align-items: center;
    overflow: hidden;
}

.resignation-detail-modal .modal-dialog {
    margin: 0; /* Centered by flexbox parent */
    max-width: 750px;
    width: 100%;
    padding: 0;
    animation: slideDown 0.3s ease;
    position: relative;
}

.resignation-detail-modal .modal-content {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
    border: 1px solid #e8edf4;
    /* Fit the white box within the 85% of available space */
    max-height: 90vh; 
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
        
        .resignation-detail-modal .modal-header {
            padding: 18px 25px;
            border-bottom: 2px solid #e8edf4;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: #ffffff;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .resignation-detail-modal .modal-header h3 {
            margin: 0;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #0a1628;
            font-weight: 700;
        }
        
        .resignation-detail-modal .modal-header h3 i {
            color: #dc2626;
        }
        
        .resignation-detail-modal .close-btn {
            background: none;
            border: none;
            font-size: 32px;
            cursor: pointer;
            color: #94a3b8;
            transition: all 0.3s ease;
            padding: 0 8px;
            line-height: 1;
        }
        
        .resignation-detail-modal .close-btn:hover {
            color: #dc2626;
            transform: rotate(90deg);
        }
        
        .resignation-detail-modal .modal-body {
            padding: 25px 25px 20px;
            overflow-y: auto;
            flex: 1;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 30px;
        }
        
        .detail-item {
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .detail-item .label {
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 4px;
        }
        
        .detail-item .value {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .detail-item .value.reason-text {
            font-weight: 400;
            line-height: 1.6;
            color: #475569;
        }
        
        .status-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 18px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
        }
        
        .status-badge-large.pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .status-badge-large.approved {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .status-badge-large.rejected {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .history-box {
            padding: 14px 18px;
            border-radius: 10px;
            margin-top: 8px;
        }
        
        .history-box.pending {
            background: #fef3c7;
            border-left: 4px solid #d97706;
        }
        
        .history-box.approved {
            background: #dcfce7;
            border-left: 4px solid #16a34a;
        }
        
        .history-box.rejected {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
        }
        
        .history-box.process_head {
            background: #dbeafe;
            border-left: 4px solid #2563eb;
        }
        
        .history-box.hr {
            background: #ede9fe;
            border-left: 4px solid #7c3aed;
        }
        
        .history-box .history-text {
            font-size: 14px;
        }
        
        .history-box .history-text i {
            margin-right: 8px;
        }
        
        .comment-box {
            margin-top: 20px;
            padding: 18px;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 4px solid #2563eb;
        }
        
        .comment-box label {
            font-weight: 600;
            font-size: 14px;
            display: block;
            margin-bottom: 8px;
            color: #1e293b;
        }
        
        .comment-box textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e8edf4;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            resize: vertical;
            min-height: 80px;
            transition: all 0.3s ease;
            background: #ffffff;
        }
        
        .comment-box textarea:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .detail-actions {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #e8edf4;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn-approve {
            background: #16a34a;
            color: #fff;
            padding: 10px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-approve:hover {
            background: #15803d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }
        
        .btn-approve:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-reject {
            background: #dc2626;
            color: #fff;
            padding: 10px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-reject:hover {
            background: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        .btn-reject:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-close-modal {
            padding: 10px 24px;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            margin-left: auto;
        }
        
        .btn-close-modal:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        
        .btn-view-detail {
            background: #2563eb;
            color: #fff;
            padding: 6px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-view-detail:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }
        
        .clickable-row {
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .clickable-row:hover {
            background: #f1f5f9 !important;
        }
        
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border-top: 3px solid #2563eb;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .approval-level-badge {
            font-size: 10px;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 50px;
            display: inline-block;
            margin-top: 4px;
        }
        
        .approval-level-badge.process_head {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .approval-level-badge.hr {
            background: #ede9fe;
            color: #7c3aed;
        }
        
        .approval-level-badge.completed {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .approval-level-badge.rejected {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .role-badge {
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 50px;
            display: inline-block;
            background: #f1f5f9;
            color: #64748b;
        }
        
        .role-badge.can-action {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .role-badge.view-only {
            background: #fef3c7;
            color: #d97706;
        }
        
        .role-badge.designated-hr {
            background: #ede9fe;
            color: #7c3aed;
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header .page-title {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .page-header .page-title h1 {
            font-size: 24px;
            font-weight: 700;
            color: #0a1628;
            margin: 0;
        }
        
        .page-header .page-title i {
            color: #dc2626;
            font-size: 28px;
        }
        
        .btn-back-dashboard {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: #f1f5f9;
            color: #1e293b;
            border: 2px solid #e8edf4;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-back-dashboard:hover {
            background: #e2e8f0;
            border-color: #2563eb;
            color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .btn-back-dashboard i {
            font-size: 16px;
        }
        
        .permission-banner {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .permission-banner.info {
            background: #dbeafe;
            border-left: 4px solid #2563eb;
        }
        
        .permission-banner.success {
            background: #dcfce7;
            border-left: 4px solid #16a34a;
        }
        
        .permission-banner.warning {
            background: #fef3c7;
            border-left: 4px solid #d97706;
        }
        
        .permission-banner.purple {
            background: #ede9fe;
            border-left: 4px solid #7c3aed;
        }
        
        .permission-banner .permission-icon {
            font-size: 20px;
        }
        
        .permission-banner .permission-text {
            font-size: 14px;
            color: #1e293b;
        }
        
        .permission-banner .permission-text strong {
            font-weight: 700;
        }
        
        @media (max-width: 768px) {
            .page-header .page-title h1 {
                font-size: 20px;
            }
            
            .btn-back-dashboard {
                padding: 8px 16px;
                font-size: 13px;
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .resignation-detail-modal .modal-dialog {
                margin: 10px;
                max-width: 100%;
            }
            .resignation-detail-modal .modal-body {
                padding: 18px 15px;
            }
            .detail-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-close-modal {
                margin-left: 0;
            }
            .stat-card {
                padding: 14px;
            }
            .permission-banner {
                padding: 10px 16px;
            }
        }
        
        @media (max-width: 480px) {
            .page-header .page-title h1 {
                font-size: 17px;
            }
            
            .btn-back-dashboard {
                padding: 6px 14px;
                font-size: 12px;
            }
            
            .resignation-detail-modal {
                padding: 8px;
            }
            .resignation-detail-modal .modal-dialog {
                margin: 5px;
            }
            .detail-item .value {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ========================================== -->
        <!-- PAGE HEADER WITH BACK BUTTON -->
        <!-- ========================================== -->
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-user-slash"></i>
                <h1>Resignations Management</h1>
                <span style="background: #f1f5f9; padding: 2px 14px; border-radius: 50px; font-size: 12px; font-weight: 600; color: #64748b;">
                    <?php echo count($resignations); ?> records
                </span>
                <?php if ($is_designated_hr): ?>
                    <span class="role-badge designated-hr">
                        <i class="fas fa-star"></i> Designated HR Approver (FNM5735)
                    </span>
                <?php elseif ($is_process_head): ?>
                    <span class="role-badge can-action">
                        <i class="fas fa-check-circle"></i> Process Head
                    </span>
                <?php elseif (in_array($user_role, ['hr', 'centre_head', 'admin'])): ?>
                    <span class="role-badge view-only">
                        <i class="fas fa-eye"></i> HR (View Only)
                    </span>
                <?php else: ?>
                    <span class="role-badge view-only">
                        <i class="fas fa-eye"></i> View Only
                    </span>
                <?php endif; ?>
            </div>
            <a href="dashboard.php" class="btn-back-dashboard">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>
        
        <?php if ($is_hr_er_dept && !$is_designated_hr): ?>
            <div class="permission-banner info">
                <i class="fas fa-id-badge permission-icon" style="color: #2563eb;"></i>
                <div class="permission-text">
                    <strong><?php echo strtoupper($user_dept); ?> Department</strong> - You have full visibility of all resignations for reporting and tracking purposes.
                </div>
            </div>
        <?php endif; ?>

        <!-- Permission Banner -->
        <?php if ($is_designated_hr): ?>
            <div class="permission-banner purple">
                <i class="fas fa-star permission-icon" style="color: #7c3aed;"></i>
                <div class="permission-text">
                    <strong>Designated HR Approver (FNM5735)</strong> - You can approve and reject resignations at the HR level.
                </div>
            </div>
        <?php elseif ($is_process_head): ?>
            <div class="permission-banner success">
                <i class="fas fa-check-circle permission-icon" style="color: #16a34a;"></i>
                <div class="permission-text">
                    <strong>Process Head</strong> - You can approve and reject resignations at the Process Head level.
                </div>
            </div>
        <?php elseif (in_array($user_role, ['hr', 'centre_head', 'admin'])): ?>
            <div class="permission-banner warning">
                <i class="fas fa-eye permission-icon" style="color: #d97706;"></i>
                <div class="permission-text">
                    <strong>HR (View Only)</strong> - You can view all resignations. Only <strong>FNM5735</strong> can approve or reject.
                </div>
            </div>
        <?php elseif (in_array($user_role, ['am', 'tl'])): ?>
            <div class="permission-banner info">
                <i class="fas fa-eye permission-icon" style="color: #2563eb;"></i>
                <div class="permission-text">
                    <strong>View Only</strong> - You can view all resignations but cannot take action.
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <div class="stat-card" style="border-top-color: #2563eb;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-users" style="color: #2563eb; font-size: 22px;"></i>
                    <div>
                        <div style="font-size: 22px; font-weight: 700;"><?php echo $stats['total']; ?></div>
                        <div style="font-size: 12px; color: #64748b;">Total</div>
                    </div>
                </div>
            </div>
            <div class="stat-card" style="border-top-color: #d97706;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-clock" style="color: #d97706; font-size: 22px;"></i>
                    <div>
                        <div style="font-size: 22px; font-weight: 700; color: #d97706;"><?php echo $stats['pending']; ?></div>
                        <div style="font-size: 12px; color: #64748b;">Pending</div>
                        <div style="font-size: 10px; color: #94a3b8;">
                            PH: <?php echo $stats['pending_process_head']; ?> | HR: <?php echo $stats['pending_hr']; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="stat-card" style="border-top-color: #16a34a;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-check-circle" style="color: #16a34a; font-size: 22px;"></i>
                    <div>
                        <div style="font-size: 22px; font-weight: 700; color: #16a34a;"><?php echo $stats['approved']; ?></div>
                        <div style="font-size: 12px; color: #64748b;">Approved</div>
                    </div>
                </div>
            </div>
            <div class="stat-card" style="border-top-color: #dc2626;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-times-circle" style="color: #dc2626; font-size: 22px;"></i>
                    <div>
                        <div style="font-size: 22px; font-weight: 700; color: #dc2626;"><?php echo $stats['rejected']; ?></div>
                        <div style="font-size: 12px; color: #64748b;">Rejected</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div style="background: #fff; padding: 15px 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <label style="font-weight: 600; font-size: 14px; color: #1e293b;">Status:</label>
                <select onchange="window.location.href='?status='+this.value+'&search=<?php echo urlencode($search_filter); ?>'" style="padding: 8px 14px; border-radius: 8px; border: 2px solid #e8edf4; font-size: 14px; background: #fff; cursor: pointer;">
                    <option value="">All</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px; display: flex; gap: 8px;">
                <input type="text" id="searchInput" placeholder="Search by name..." 
                       style="flex: 1; padding: 8px 14px; border-radius: 8px; border: 2px solid #e8edf4; font-size: 14px;"
                       value="<?php echo htmlspecialchars($search_filter); ?>"
                       onkeypress="if(event.key==='Enter') searchResignations()">
                <button onclick="searchResignations()" style="padding: 8px 18px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-search"></i>
                </button>
                <button onclick="clearSearch()" style="padding: 8px 14px; background: #f1f5f9; color: #64748b; border: none; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div style="font-size: 14px; color: #64748b; margin-left: auto;">
                <i class="fas fa-file-alt"></i> <?php echo count($resignations); ?> records
            </div>
        </div>
        
        <!-- Resignations Table -->
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f8fafc;">
                        <tr>
                            <th style="padding: 14px 16px; text-align: left; font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase;">#</th>
                            <th style="padding: 14px 16px; text-align: left; font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase;">Employee</th>
                            <th style="padding: 14px 16px; text-align: left; font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase;">Position</th>
                            <th style="padding: 14px 16px; text-align: left; font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase;">Last Working Day</th>
                            <th style="padding: 14px 16px; text-align: left; font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase;">Status</th>
                            <th style="padding: 14px 16px; text-align: left; font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase;">Approval Level</th>
                            <th style="padding: 14px 16px; text-align: center; font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resignations)): ?>
                            <tr>
                                <td colspan="7" style="padding: 60px; text-align: center; color: #94a3b8;">
                                    <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 15px; color: #cbd5e1;"></i>
                                    <div style="font-size: 18px; font-weight: 600; color: #64748b;">No resignations found</div>
                                    <div style="font-size: 14px; margin-top: 5px;">Try adjusting your filters</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($resignations as $index => $res): ?>
                                <?php
                                $status_badge = [
                                    'pending' => ['bg' => '#fef3c7', 'color' => '#d97706', 'icon' => 'fa-clock', 'text' => 'Pending'],
                                    'approved' => ['bg' => '#dcfce7', 'color' => '#16a34a', 'icon' => 'fa-check', 'text' => 'Approved'],
                                    'rejected' => ['bg' => '#fee2e2', 'color' => '#dc2626', 'icon' => 'fa-times', 'text' => 'Rejected']
                                ][$res['status']] ?? ['bg' => '#f1f5f9', 'color' => '#64748b', 'icon' => 'fa-circle', 'text' => $res['status']];
                                
                                $level_badge = [
                                    'process_head' => ['bg' => '#dbeafe', 'color' => '#2563eb', 'text' => 'Process Head'],
                                    'hr' => ['bg' => '#ede9fe', 'color' => '#7c3aed', 'text' => 'HR'],
                                    'completed' => ['bg' => '#dcfce7', 'color' => '#16a34a', 'text' => 'Completed'],
                                    'rejected' => ['bg' => '#fee2e2', 'color' => '#dc2626', 'text' => 'Rejected']
                                ];
                                $level = $level_badge[$res['approval_level'] ?? 'process_head'] ?? $level_badge['process_head'];
                                ?>
                                <tr class="clickable-row" onclick="viewResignationDetail(<?php echo $res['id']; ?>)">
                                    <td style="padding: 14px 16px; font-weight: 600; color: #64748b;"><?php echo $index + 1; ?></td>
                                    <td style="padding: 14px 16px;">
                                        <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($res['employee_name']); ?></div>
                                        <div style="font-size: 12px; color: #94a3b8;"><?php echo htmlspecialchars($res['user_role'] ?? ''); ?></div>
                                    </td>
                                    <td style="padding: 14px 16px; color: #475569;"><?php echo htmlspecialchars($res['position']); ?></td>
                                    <td style="padding: 14px 16px; color: #475569; font-weight: 600;"><?php echo date('M d, Y', strtotime($res['last_working_date'])); ?></td>
                                    <td style="padding: 14px 16px;">
                                        <span style="background: <?php echo $status_badge['bg']; ?>; color: <?php echo $status_badge['color']; ?>; padding: 4px 14px; border-radius: 50px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                                            <i class="fas <?php echo $status_badge['icon']; ?>"></i>
                                            <?php echo $status_badge['text']; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <span class="approval-level-badge <?php echo $res['approval_level'] ?? 'process_head'; ?>">
                                            <?php echo $level['text']; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 14px 16px; text-align: center;">
                                        <button onclick="event.stopPropagation(); viewResignationDetail(<?php echo $res['id']; ?>)" 
                                                class="btn-view-detail">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- RESIGNATION DETAIL MODAL -->
    <!-- ========================================== -->
    <div class="resignation-detail-modal" id="resignationDetailModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>
                        <i class="fas fa-user-slash"></i>
                        Resignation Details
                        <span id="detailStatusBadge"></span>
                    </h3>
                    <button class="close-btn" onclick="closeResignationDetail()">&times;</button>
                </div>
                <div class="modal-body" id="resignationDetailBody">
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 36px; color: #2563eb;"></i>
                        <p style="margin-top: 15px; color: #64748b;">Loading details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Main Scripts -->
    <?php include __DIR__ . '/includes/modules/scripts/main_scripts.php'; ?>
    
    <script>
    // Pass PHP variables to JavaScript
    const userRole = '<?php echo $user_role; ?>';
    const isDesignatedHr = <?php echo $is_designated_hr ? 'true' : 'false'; ?>;
    const isProcessHead = <?php echo $is_process_head ? 'true' : 'false'; ?>;
    const canTakeAction = <?php echo ($is_designated_hr || $is_process_head) ? 'true' : 'false'; ?>;
    
    function searchResignations() {
        const search = document.getElementById('searchInput').value;
        const status = '<?php echo $status_filter; ?>';
        window.location.href = '?status=' + status + '&search=' + encodeURIComponent(search);
    }
    
    function clearSearch() {
        document.getElementById('searchInput').value = '';
        searchResignations();
    }
    
    function viewResignationDetail(id) {
        const modal = document.getElementById('resignationDetailModal');
        const body = document.getElementById('resignationDetailBody');
        
        body.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 36px; color: #2563eb;"></i>
                <p style="margin-top: 15px; color: #64748b;">Loading resignation details...</p>
            </div>
        `;
        
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        fetch('includes/modules/resignation/get_resignation_detail.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderResignationDetail(data.resignation);
                } else {
                    body.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #dc2626;">
                            <i class="fas fa-exclamation-circle" style="font-size: 36px;"></i>
                            <p style="margin-top: 15px;">${data.message || 'Failed to load details'}</p>
                            <button onclick="closeResignationDetail()" style="margin-top: 15px; padding: 8px 24px; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Close</button>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                body.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #dc2626;">
                        <i class="fas fa-exclamation-circle" style="font-size: 36px;"></i>
                        <p style="margin-top: 15px;">An error occurred. Please try again.</p>
                        <button onclick="closeResignationDetail()" style="margin-top: 15px; padding: 8px 24px; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Close</button>
                    </div>
                `;
            });
    }
    
    function renderResignationDetail(res) {
        const body = document.getElementById('resignationDetailBody');
        
        const statusColors = {
            pending: { bg: '#fef3c7', color: '#d97706', icon: 'fa-clock', text: 'Pending' },
            approved: { bg: '#dcfce7', color: '#16a34a', icon: 'fa-check-circle', text: 'Approved' },
            rejected: { bg: '#fee2e2', color: '#dc2626', icon: 'fa-times-circle', text: 'Rejected' }
        };
        
        const status = statusColors[res.status] || statusColors.pending;
        
        const levelColors = {
            process_head: { bg: '#dbeafe', color: '#2563eb', text: 'Waiting for Process Head Approval' },
            hr: { bg: '#ede9fe', color: '#7c3aed', text: 'Waiting for HR Approval' },
            completed: { bg: '#dcfce7', color: '#16a34a', text: 'Completed' },
            rejected: { bg: '#fee2e2', color: '#dc2626', text: 'Rejected' }
        };
        
        const level = levelColors[res.approval_level] || levelColors.process_head;
        
        document.getElementById('detailStatusBadge').innerHTML = `
            <span style="background: ${status.bg}; color: ${status.color}; padding: 4px 16px; border-radius: 50px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; margin-left: 12px;">
                <i class="fas ${status.icon}"></i>
                ${status.text}
            </span>
        `;
        
        let html = `
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="label">Employee Name</span>
                    <span class="value">${res.employee_name || 'N/A'}</span>
                </div>
                <div class="detail-item">
                    <span class="label">Position</span>
                    <span class="value">${res.position || 'N/A'}</span>
                </div>
                <div class="detail-item">
                    <span class="label">Company</span>
                    <span class="value">${res.company_name || 'N/A'}</span>
                </div>
                <div class="detail-item">
                    <span class="label">Resignation Date</span>
                    <span class="value">${formatDate(res.resignation_date)}</span>
                </div>
                <div class="detail-item">
                    <span class="label">Last Working Day</span>
                    <span class="value" style="color: #dc2626;">${formatDate(res.last_working_date)}</span>
                </div>
                <div class="detail-item">
                    <span class="label">Submitted Date</span>
                    <span class="value">${formatDateTime(res.submitted_date)}</span>
                </div>
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <span class="label">Reason for Leaving</span>
                    <span class="value reason-text">${res.reason || 'Not specified'}</span>
                </div>
                <div class="detail-item" style="grid-column: 1 / -1; border-bottom: none;">
                    <span class="label">Approval Status</span>
                    <div style="margin-top: 8px;">
                        <div class="history-box ${res.approval_level || 'process_head'}">
                            <span class="history-text" style="color: ${level.color};">
                                <i class="fas ${res.status === 'pending' ? 'fa-spinner fa-spin' : res.status === 'approved' ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                                ${level.text}
                            </span>
                        </div>
        `;
        
        // Show Process Head approval info if approved
        if (res.process_head_approved_by && res.process_head_approved_date) {
            html += `
                <div class="history-box process_head" style="margin-top: 8px;">
                    <span class="history-text" style="color: #2563eb;">
                        <i class="fas fa-check-circle"></i>
                        Process Head approved on ${formatDateTime(res.process_head_approved_date)}
                        ${res.process_head_comment ? '<br><small style="color: #64748b;">Comment: ' + res.process_head_comment + '</small>' : ''}
                    </span>
                </div>
            `;
        }
        
        // Show HR approval info if approved
        if (res.status === 'approved' && res.approved_by) {
            html += `
                <div class="history-box approved" style="margin-top: 8px;">
                    <span class="history-text approved-text">
                        <i class="fas fa-check-circle"></i>
                        HR approved on ${formatDateTime(res.approved_date)}
                        ${res.hr_comment ? '<br><small style="color: #64748b;">Comment: ' + res.hr_comment + '</small>' : ''}
                    </span>
                </div>
            `;
        }
        
        // Show rejection info
        if (res.status === 'rejected') {
            html += `
                <div class="history-box rejected" style="margin-top: 8px;">
                    <span class="history-text rejected-text">
                        <i class="fas fa-times-circle"></i>
                        Rejected on ${formatDateTime(res.rejected_date)}
                        ${res.rejection_reason ? '<br><small style="color: #64748b;">Reason: ' + res.rejection_reason + '</small>' : ''}
                    </span>
                </div>
            `;
        }
        
        html += `
                    </div>
                </div>
            </div>
        `;
        
        // Comments section
        html += `
            <div class="comment-box">
                <label><i class="fas fa-comment"></i> Comments</label>
                <textarea id="approvalComment" placeholder="Add your comments here...">${res.hr_comment || ''}</textarea>
            </div>
        `;
        
        // Actions based on role and approval level
        const canApprove = res.status === 'pending';
        const isProcessHeadUser = isProcessHead;
        const isDesignatedHrUser = isDesignatedHr;
        const isProcessHeadLevel = res.approval_level === 'process_head';
        const isHRLevel = res.approval_level === 'hr';
        
        let showApprove = false;
        let showReject = false;
        let actionMessage = '';
        
        if (canApprove) {
            if (isProcessHeadUser && isProcessHeadLevel) {
                showApprove = true;
                showReject = true;
                actionMessage = 'Process Head approval required';
            } else if (isDesignatedHrUser && isHRLevel) {
                showApprove = true;
                showReject = true;
                actionMessage = 'HR final approval required (FNM5735)';
            } else if (isDesignatedHrUser && isProcessHeadLevel) {
                showReject = true;
                actionMessage = 'Waiting for Process Head approval first';
            } else if (isProcessHeadUser && isHRLevel) {
                actionMessage = 'Process Head has already approved. Waiting for HR.';
            } else if (isDesignatedHrUser && !isHRLevel && !isProcessHeadLevel) {
                actionMessage = 'This resignation is not pending for approval.';
            } else if (!isDesignatedHrUser && !isProcessHeadUser) {
                actionMessage = 'You do not have permission to take action. Only Process Head or Designated HR (FNM5735) can take action.';
            } else {
                actionMessage = 'You do not have permission to take action at this stage.';
            }
        } else {
            if (res.status === 'approved') {
                actionMessage = 'This resignation has been approved.';
            } else if (res.status === 'rejected') {
                actionMessage = 'This resignation has been rejected.';
            } else {
                actionMessage = 'This resignation is already processed.';
            }
        }
        
        html += `
            <div class="detail-actions">
                ${showApprove ? `<button class="btn-approve" onclick="handleApproval(${res.id}, 'approve')">
                    <i class="fas fa-check"></i> Approve
                </button>` : ''}
                ${showReject ? `<button class="btn-reject" onclick="handleApproval(${res.id}, 'reject')">
                    <i class="fas fa-times"></i> Reject
                </button>` : ''}
                ${actionMessage ? `<span style="font-size: 13px; color: #64748b; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-info-circle"></i> ${actionMessage}
                </span>` : ''}
                <button class="btn-close-modal" onclick="closeResignationDetail()" ${showApprove || showReject ? '' : 'style="margin-left: 0;"'}>
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        `;
        
        body.innerHTML = html;
    }
    
    function handleApproval(id, action) {
        const comment = document.getElementById('approvalComment')?.value || '';
        
        if (!confirm(`Are you sure you want to ${action} this resignation?`)) {
            return;
        }
        
        const btn = action === 'approve' ? document.querySelector('.btn-approve') : document.querySelector('.btn-reject');
        if (!btn) return;
        
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        const formData = new FormData();
        formData.append('action', action);
        formData.append('resignation_id', id);
        formData.append('comment', comment);
        
        fetch('includes/modules/resignation/resignation_approval_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message);
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showNotification('error', data.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'An error occurred. Please try again.');
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }
    
    function formatDate(date) {
        if (!date) return 'N/A';
        return new Date(date).toLocaleDateString('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric'
        });
    }
    
    function formatDateTime(date) {
        if (!date) return 'N/A';
        return new Date(date).toLocaleDateString('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    function closeResignationDetail() {
        document.getElementById('resignationDetailModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeResignationDetail();
        }
    });
    
    document.getElementById('resignationDetailModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeResignationDetail();
        }
    });
    </script>
</body>
</html>