<?php
// ============================================
// MY RESIGNATION PAGE
// ============================================

require_once __DIR__ . '/includes/modules/config/bootstrap.php';
require_once __DIR__ . '/includes/modules/auth/authentication.php';
require_once __DIR__ . '/includes/modules/resignation/resignation_functions.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: index.php');
    exit;
}

$resignation = getUserResignation($pdo, $user['id']);
$has_pending = hasPendingResignation($pdo, $user['id']);

include __DIR__ . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Resignation - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/includes/modules/styles/main_styles.php'; ?>
    <style>
        .resignation-form-modal {
    display: none; /* Changed to flex in JS below */
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5); /* Dim background */
    z-index: 1000; /* Higher than page content, lower than header */
    backdrop-filter: blur(4px);
    padding: 20px;
    box-sizing: border-box;
    /* Use flex to center the modal content perfectly */
    align-items: center;
    justify-content: center;
}

.resignation-form-modal .modal-dialog {
    width: 100%;
    max-width: 600px;
    margin: 0; /* Centered by flex parent */
    padding: 0;
    animation: slideDown 0.3s ease;
    position: relative;
    box-sizing: border-box;
}

.resignation-form-modal .modal-content {
    background: #ffffff;
    border-radius: 15px;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    border: 1px solid #e8edf4;
    max-height: 70vh; /* Prevents overflow on short screens */
    display: flex;
    flex-direction: column;
}
        
        .resignation-form-modal .modal-header {
            padding: 14px 20px;
            background: #fef2f2;
            border-bottom: 2px solid #fecaca;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: #ffffff;
            border-bottom: 1px solid #e8edf4;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .resignation-form-modal .modal-header h3 {
            margin: 0;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #0a1628;
            font-weight: 700;
        }
        
        .resignation-form-modal .modal-header h3 i {
            color: #dc2626;
        }
        
        .resignation-form-modal .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #94a3b8;
            transition: all 0.3s ease;
            padding: 0 6px;
            line-height: 1;
        }
        
        .resignation-form-modal .close-btn:hover {
            color: #dc2626;
            transform: rotate(90deg);
        }
        
        .resignation-form-modal .modal-body {
            padding: 16px 20px 10px;
            overflow-y: auto;
            flex: 1;
            -webkit-overflow-scrolling: touch;
        }
        
        .resignation-form-modal .modal-body .form-group {
            margin-bottom: 12px;
        }
        
        .resignation-form-modal .modal-body .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 4px;
        }
        
        .resignation-form-modal .modal-body .form-group label .required {
            color: #dc2626;
            font-weight: 700;
        }
        
        .resignation-form-modal .modal-body .form-group .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e8edf4;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            color: #1e293b;
            box-sizing: border-box;
            height: 40px;
        }
        
        .resignation-form-modal .modal-body .form-group .form-control:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .resignation-form-modal .modal-body .form-group .form-control[disabled] {
            background: #f8fafc;
            cursor: not-allowed;
            color: #64748b;
        }
        
        .resignation-form-modal .modal-body .form-group .form-control::placeholder {
            color: #94a3b8;
        }
        
        .resignation-form-modal .modal-body .form-group textarea.form-control {
            height: auto;
            min-height: 60px;
            resize: vertical;
        }
        
        .resignation-form-modal .modal-body .form-group select.form-control {
            appearance: auto;
            height: 40px;
        }
        
        .resignation-form-modal .modal-body .form-group .help-text {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 3px;
            display: block;
        }
        
        .resignation-form-modal .modal-body .form-group .help-text i {
            margin-right: 4px;
        }
        
        .resignation-form-modal .modal-footer {
            padding: 12px 20px;
            border-top: 1px solid #e8edf4;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: #fafbfc;
            flex-shrink: 0;
            flex-wrap: wrap;
            position: sticky;
            bottom: 0;
            z-index: 5;
            background: #ffffff;
        }
        
        .btn-submit-resignation {
            padding: 8px 24px;
            background: #dc2626;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .btn-submit-resignation:hover {
            background: #b91c1c;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        .btn-submit-resignation:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-cancel-resignation {
            padding: 8px 20px;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .btn-cancel-resignation:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        
        /* ============================================
           MAIN PAGE STYLES
           ============================================ */
        .resignation-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            max-width: 900px;
            margin: 0 auto;
        }
        
        .resignation-card .card-header {
            padding: 18px 24px;
            border-bottom: 2px solid #e8edf4;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .resignation-card .card-header h2 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #0a1628;
        }
        
        .resignation-card .card-header h2 i {
            color: #dc2626;
        }
        
        .resignation-card .card-body {
            padding: 24px;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
        }
        
        .status-badge.pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .status-badge.approved {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .status-badge.rejected {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .status-badge.process_head {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .status-badge.hr {
            background: #ede9fe;
            color: #7c3aed;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            color: #64748b;
            margin-bottom: 8px;
            font-size: 18px;
        }
        
        .empty-state p {
            color: #94a3b8;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .btn-new-resignation {
            padding: 10px 28px;
            background: #dc2626;
            color: #fff;
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
        
        .btn-new-resignation:hover {
            background: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
        }
        
        /* Detail View */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
        }
        
        .detail-item {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .detail-item .label {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 3px;
        }
        
        .detail-item .value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .detail-item .value.reason-text {
            font-weight: 400;
            line-height: 1.5;
            color: #475569;
        }
        
        /* Approval History Timeline */
        .approval-timeline {
            margin-top: 8px;
            position: relative;
            padding-left: 30px;
        }
        
        .approval-timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e8edf4;
        }
        
        .timeline-item {
            position: relative;
            padding: 10px 16px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: #f8fafc;
            border-left: 3px solid #94a3b8;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 14px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #94a3b8;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #94a3b8;
        }
        
        .timeline-item.completed::before {
            background: #16a34a;
            box-shadow: 0 0 0 2px #16a34a;
        }
        
        .timeline-item.completed {
            border-left-color: #16a34a;
            background: #dcfce7;
        }
        
        .timeline-item.pending::before {
            background: #d97706;
            box-shadow: 0 0 0 2px #d97706;
        }
        
        .timeline-item.pending {
            border-left-color: #d97706;
            background: #fef3c7;
        }
        
        .timeline-item.rejected::before {
            background: #dc2626;
            box-shadow: 0 0 0 2px #dc2626;
        }
        
        .timeline-item.rejected {
            border-left-color: #dc2626;
            background: #fee2e2;
        }
        
        .timeline-item.process_head::before {
            background: #2563eb;
            box-shadow: 0 0 0 2px #2563eb;
        }
        
        .timeline-item.process_head {
            border-left-color: #2563eb;
            background: #dbeafe;
        }
        
        .timeline-item.hr::before {
            background: #7c3aed;
            box-shadow: 0 0 0 2px #7c3aed;
        }
        
        .timeline-item.hr {
            border-left-color: #7c3aed;
            background: #ede9fe;
        }
        
        .timeline-item .timeline-title {
            font-weight: 600;
            font-size: 14px;
            color: #1e293b;
        }
        
        .timeline-item .timeline-date {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 2px;
        }
        
        .timeline-item .timeline-comment {
            font-size: 13px;
            color: #475569;
            margin-top: 4px;
            padding: 6px 10px;
            background: rgba(255,255,255,0.6);
            border-radius: 4px;
        }
        
        /* Status Banner */
        .status-banner {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .status-banner.pending {
            background: #fef3c7;
            border-left-color: #d97706;
        }
        
        .status-banner.approved {
            background: #dcfce7;
            border-left-color: #16a34a;
        }
        
        .status-banner.rejected {
            background: #fee2e2;
            border-left-color: #dc2626;
        }
        
        .status-banner.process_head {
            background: #dbeafe;
            border-left-color: #2563eb;
        }
        
        .status-banner.hr {
            background: #ede9fe;
            border-left-color: #7c3aed;
        }
        
        /* Animation */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        @media (max-width: 768px) {
            
            .resignation-card .card-header {
                padding: 14px 16px;
            }
            .resignation-card .card-header h2 {
                font-size: 17px;
            }
            .resignation-card .card-body {
                padding: 16px;
            }
            .detail-grid {
                grid-template-columns: 1fr;
                gap: 6px;
            }
            .detail-item {
                padding: 8px 0;
            }
            .resignation-form-modal {
                padding: 5px;
            }
            .resignation-form-modal .modal-dialog {
                margin: 5px auto;
                max-width: 100%;
            }
            .resignation-form-modal .modal-content {
                max-height: 100vh;
                border-radius: 10px;
            }
            .resignation-form-modal .modal-header {
                padding: 12px 16px;
            }
            .resignation-form-modal .modal-header h3 {
                font-size: 16px;
            }
            .resignation-form-modal .modal-body {
                padding: 12px 16px 8px;
                max-height: calc(100vh - 140px);
            }
            .resignation-form-modal .modal-body .form-group {
                margin-bottom: 10px;
            }
            .resignation-form-modal .modal-body .form-group label {
                font-size: 12px;
            }
            .resignation-form-modal .modal-body .form-group .form-control {
                height: 36px;
                padding: 6px 10px;
                font-size: 12px;
            }
            .resignation-form-modal .modal-body .form-group textarea.form-control {
                min-height: 50px;
            }
            .resignation-form-modal .modal-footer {
                padding: 10px 16px;
                flex-direction: row;
                justify-content: flex-end;
            }
            .resignation-form-modal .modal-footer button {
                padding: 6px 16px;
                font-size: 12px;
            }
            .empty-state {
                padding: 30px 15px;
            }
            .empty-state i {
                font-size: 40px;
            }
            .empty-state h3 {
                font-size: 16px;
            }
            .btn-new-resignation {
                padding: 8px 20px;
                font-size: 13px;
            }
            .approval-timeline {
                padding-left: 20px;
            }
            .timeline-item::before {
                left: -20px;
                width: 10px;
                height: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .resignation-card .card-header h2 {
                font-size: 15px;
            }
            .resignation-card .card-header {
                padding: 12px 14px;
            }
            .resignation-card .card-body {
                padding: 12px 14px;
            }
            .resignation-form-modal .modal-header h3 {
                font-size: 14px;
            }
            .resignation-form-modal .modal-header {
                padding: 10px 14px;
            }
            .resignation-form-modal .modal-body {
                padding: 10px 14px 6px;
                max-height: calc(100vh - 120px);
            }
            .resignation-form-modal .modal-body .form-group {
                margin-bottom: 8px;
            }
            .resignation-form-modal .modal-body .form-group .form-control {
                height: 34px;
                padding: 4px 8px;
                font-size: 12px;
            }
            .resignation-form-modal .modal-footer {
                padding: 8px 14px;
                gap: 6px;
            }
            .resignation-form-modal .modal-footer button {
                padding: 5px 14px;
                font-size: 11px;
                flex: 1;
                justify-content: center;
            }
            .status-badge {
                font-size: 11px;
                padding: 3px 10px;
            }
            .detail-item .value {
                font-size: 13px;
            }
            .btn-new-resignation {
                padding: 6px 16px;
                font-size: 12px;
            }
            .approval-timeline {
                padding-left: 16px;
            }
            .timeline-item {
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="resignation-card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-user-slash"></i>
                    My Resignation
                </h2>
                <?php if (!$has_pending && (!$resignation || $resignation['status'] !== 'approved')): ?>
                    <button onclick="openResignationForm()" class="btn-new-resignation">
                        <i class="fas fa-plus"></i> New Resignation
                    </button>
                <?php endif; ?>
            </div>
            
            <div class="card-body">
                <?php if (!$resignation): ?>
                    <!-- No Resignation -->
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h3>No Resignation Submitted</h3>
                        <p>You haven't submitted any resignation request yet.</p>
                        <button onclick="openResignationForm()" class="btn-new-resignation">
                            <i class="fas fa-paper-plane"></i> Submit Resignation
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Resignation Details -->
                    <?php
                    $status_colors = [
                        'pending' => ['bg' => '#fef3c7', 'color' => '#d97706', 'icon' => 'fa-clock', 'text' => 'Pending'],
                        'approved' => ['bg' => '#dcfce7', 'color' => '#16a34a', 'icon' => 'fa-check-circle', 'text' => 'Approved'],
                        'rejected' => ['bg' => '#fee2e2', 'color' => '#dc2626', 'icon' => 'fa-times-circle', 'text' => 'Rejected']
                    ];
                    
                    // Determine which status to show
                    if ($resignation['status'] === 'pending') {
                        if ($resignation['approval_level'] === 'process_head') {
                            $status_color = ['bg' => '#dbeafe', 'color' => '#2563eb', 'icon' => 'fa-clock', 'text' => 'Waiting for Process Head'];
                            $banner_class = 'process_head';
                        } else if ($resignation['approval_level'] === 'hr') {
                            $status_color = ['bg' => '#ede9fe', 'color' => '#7c3aed', 'icon' => 'fa-clock', 'text' => 'Waiting for HR'];
                            $banner_class = 'hr';
                        } else {
                            $status_color = $status_colors['pending'];
                            $banner_class = 'pending';
                        }
                    } else if ($resignation['status'] === 'approved') {
                        $status_color = $status_colors['approved'];
                        $banner_class = 'approved';
                    } else {
                        $status_color = $status_colors['rejected'];
                        $banner_class = 'rejected';
                    }
                    ?>
                    
                    <!-- Status Banner -->
                    <div class="status-banner <?php echo $banner_class; ?>">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas <?php echo $status_color['icon']; ?>" style="color: <?php echo $status_color['color']; ?>; font-size: 24px;"></i>
                            <div>
                                <span style="font-weight: 700; font-size: 16px; color: <?php echo $status_color['color']; ?>;">Status: <?php echo $status_color['text']; ?></span>
                                <?php if ($resignation['status'] === 'approved'): ?>
                                    <br><span style="font-size: 13px; color: #64748b;">Last Working Day: <?php echo date('M d, Y', strtotime($resignation['last_working_date'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="status-badge <?php echo $resignation['status'] === 'pending' ? 'pending' : ($resignation['status'] === 'approved' ? 'approved' : 'rejected'); ?>">
                            <i class="fas <?php echo $status_color['icon']; ?>"></i>
                            <?php echo $status_color['text']; ?>
                        </span>
                    </div>
                    
                    <!-- Detail Grid -->
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="label">Employee Name</span>
                            <span class="value"><?php echo htmlspecialchars($resignation['employee_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Position</span>
                            <span class="value"><?php echo htmlspecialchars($resignation['position']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Company</span>
                            <span class="value"><?php echo htmlspecialchars($resignation['company_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Resignation Date</span>
                            <span class="value"><?php echo date('M d, Y', strtotime($resignation['resignation_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Last Working Day</span>
                            <span class="value" style="color: #dc2626;"><?php echo date('M d, Y', strtotime($resignation['last_working_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Submitted Date</span>
                            <span class="value"><?php echo date('M d, Y h:i A', strtotime($resignation['submitted_date'])); ?></span>
                        </div>
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <span class="label">Reason for Leaving</span>
                            <span class="value reason-text"><?php echo htmlspecialchars($resignation['reason']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Approval History Timeline -->
                    <div style="border-top: 2px solid #e8edf4; padding-top: 20px; margin-top: 15px;">
                        <h4 style="font-size: 15px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; color: #1e293b;">
                            <i class="fas fa-history" style="color: #2563eb;"></i>
                            Approval History
                        </h4>
                        
                        <div class="approval-timeline">
                            <!-- Step 1: Submitted -->
                            <div class="timeline-item completed">
                                <div class="timeline-title">
                                    <i class="fas fa-paper-plane" style="color: #16a34a;"></i>
                                    Resignation Submitted
                                </div>
                                <div class="timeline-date"><?php echo date('M d, Y h:i A', strtotime($resignation['submitted_date'])); ?></div>
                            </div>
                            
                            <!-- Step 2: Process Head Approval -->
                            <?php if ($resignation['process_head_approved_by'] && $resignation['process_head_approved_date']): ?>
                                <div class="timeline-item process_head completed">
                                    <div class="timeline-title">
                                        <i class="fas fa-check-circle" style="color: #2563eb;"></i>
                                        Process Head Approved
                                    </div>
                                    <div class="timeline-date">
                                        By: <?php echo htmlspecialchars($resignation['process_head_name'] ?? 'Process Head'); ?> 
                                        on <?php echo date('M d, Y h:i A', strtotime($resignation['process_head_approved_date'])); ?>
                                    </div>
                                    <?php if (!empty($resignation['process_head_comment'])): ?>
                                        <div class="timeline-comment">
                                            <strong>Comment:</strong> <?php echo htmlspecialchars($resignation['process_head_comment']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($resignation['status'] === 'pending' && $resignation['approval_level'] === 'process_head'): ?>
                                <div class="timeline-item pending">
                                    <div class="timeline-title">
                                        <i class="fas fa-spinner fa-spin" style="color: #d97706;"></i>
                                        Waiting for Process Head Approval
                                    </div>
                                    <div class="timeline-date">Pending</div>
                                </div>
                            <?php elseif ($resignation['status'] === 'pending' && $resignation['approval_level'] === 'hr'): ?>
                                <div class="timeline-item process_head completed">
                                    <div class="timeline-title">
                                        <i class="fas fa-check-circle" style="color: #2563eb;"></i>
                                        Process Head Approved
                                    </div>
                                    <div class="timeline-date">
                                        By: <?php echo htmlspecialchars($resignation['process_head_name'] ?? 'Process Head'); ?> 
                                        on <?php echo date('M d, Y h:i A', strtotime($resignation['process_head_approved_date'])); ?>
                                    </div>
                                    <?php if (!empty($resignation['process_head_comment'])): ?>
                                        <div class="timeline-comment">
                                            <strong>Comment:</strong> <?php echo htmlspecialchars($resignation['process_head_comment']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Step 3: HR Approval -->
                            <?php if ($resignation['status'] === 'approved'): ?>
                                <div class="timeline-item hr completed">
                                    <div class="timeline-title">
                                        <i class="fas fa-check-circle" style="color: #7c3aed;"></i>
                                        HR Final Approval
                                    </div>
                                    <div class="timeline-date">
                                        By: <?php echo htmlspecialchars($resignation['hr_name'] ?? 'HR'); ?> 
                                        on <?php echo date('M d, Y h:i A', strtotime($resignation['approved_date'])); ?>
                                    </div>
                                    <?php if (!empty($resignation['hr_comment'])): ?>
                                        <div class="timeline-comment">
                                            <strong>Comment:</strong> <?php echo htmlspecialchars($resignation['hr_comment']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <!-- Final Status -->
                                <div class="timeline-item completed">
                                    <div class="timeline-title">
                                        <i class="fas fa-flag-checkered" style="color: #16a34a;"></i>
                                        ✅ Resignation Approved - Process Completed
                                    </div>
                                    <div class="timeline-date">Last Working Day: <?php echo date('M d, Y', strtotime($resignation['last_working_date'])); ?></div>
                                </div>
                            <?php elseif ($resignation['status'] === 'pending' && $resignation['approval_level'] === 'hr'): ?>
                                <div class="timeline-item pending">
                                    <div class="timeline-title">
                                        <i class="fas fa-spinner fa-spin" style="color: #d97706;"></i>
                                        Waiting for HR Final Approval
                                    </div>
                                    <div class="timeline-date">Pending</div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Rejected Status -->
                            <?php if ($resignation['status'] === 'rejected'): ?>
                                <div class="timeline-item rejected">
                                    <div class="timeline-title">
                                        <i class="fas fa-times-circle" style="color: #dc2626;"></i>
                                        ❌ Resignation Rejected
                                    </div>
                                    <div class="timeline-date">on <?php echo date('M d, Y h:i A', strtotime($resignation['rejected_date'])); ?></div>
                                    <?php if (!empty($resignation['rejection_reason'])): ?>
                                        <div class="timeline-comment">
                                            <strong>Reason:</strong> <?php echo htmlspecialchars($resignation['rejection_reason']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- RESIGNATION FORM MODAL -->
    <!-- ========================================== -->
    <div class="resignation-form-modal" id="resignationFormModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>
                        <i class="fas fa-user-slash"></i>
                        Resignation Letter
                    </h3>
                    <button class="close-btn" onclick="closeResignationForm()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="resignationForm" method="POST" onsubmit="submitResignation(event)">
                        <input type="hidden" name="action" value="submit_resignation">
                        
                        <div class="form-group">
                            <label>Date <span class="required">*</span></label>
                            <input type="date" name="resignation_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>To HR,</label>
                        </div>
                        
                        <div class="form-group">
                            <label>I <span class="required">*</span></label>
                            <input type="text" name="employee_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                                   placeholder="Your Full Name" required>
                        </div>
                        
                        <div class="form-group">
                            <label>writing to provide you a <span class="required">*</span></label>
                            <input type="number" name="notice_period_days" class="form-control" 
                                   placeholder="Number of days (e.g., 30, 60)" min="1" max="90" required>
                            <span class="help-text"><i class="fas fa-info-circle"></i> Standard notice period is 30 or 60 days</span>
                        </div>
                        
                        <div class="form-group">
                            <label>Days' Notice of my Resignation from my Position as <span class="required">*</span></label>
                            <input type="text" name="position" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['role'] ?? ''); ?>" 
                                   placeholder="Your Position/Designation" required>
                        </div>
                        
                        <div class="form-group">
                            <label>with <span class="required">*</span></label>
                            <input type="text" name="company_name" class="form-control" 
                                   value="<?php echo SITE_NAME ?? 'Company'; ?>" 
                                   placeholder="Company Name" required>
                        </div>
                        
                        <div class="form-group">
                            <label>My last day of work in this Position will be <span class="required">*</span></label>
                            <input type="date" name="last_working_date" class="form-control" id="lastWorkingDate" required>
                            <span class="help-text"><i class="fas fa-info-circle"></i> Please select your last working day (minimum 15 days from today)</span>
                        </div>
                        
                        <div class="form-group">
                            <label>I am leaving due to <span class="required">*</span></label>
                            <select name="reason" class="form-control" required>
                                <option value="">Select Reason</option>
                                <option value="Better Career Opportunity">Better Career Opportunity</option>
                                <option value="Higher Education">Higher Education</option>
                                <option value="Personal Reasons">Personal Reasons</option>
                                <option value="Health Issues">Health Issues</option>
                                <option value="Relocation">Relocation</option>
                                <option value="Salary/Compensation">Salary/Compensation</option>
                                <option value="Work-Life Balance">Work-Life Balance</option>
                                <option value="Company Culture">Company Culture</option>
                                <option value="Career Growth">Career Growth</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Detailed Reason (Optional)</label>
                            <textarea name="reason_detail" class="form-control" rows="3" 
                                      placeholder="Please provide more details about your reason for leaving..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Your Faithfully,</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" disabled>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel-resignation" onclick="closeResignationForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn-submit-resignation" id="submitResignationBtn" form="resignationForm">
                        <i class="fas fa-paper-plane"></i> Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Main Scripts -->
    <?php include __DIR__ . '/includes/modules/scripts/main_scripts.php'; ?>
    
    <script>
    function openResignationForm() {
        var modal = document.getElementById('resignationFormModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        var today = new Date();
        var minDate = new Date();
        minDate.setDate(today.getDate() + 15);
        
        var year = minDate.getFullYear();
        var month = String(minDate.getMonth() + 1).padStart(2, '0');
        var day = String(minDate.getDate()).padStart(2, '0');
        
        var lastWorkingDate = document.getElementById('lastWorkingDate');
        if (lastWorkingDate) {
            lastWorkingDate.min = year + '-' + month + '-' + day;
        }
    }
    
    function closeResignationForm() {
        document.getElementById('resignationFormModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    
    function submitResignation(event) {
        event.preventDefault();
        
        const form = document.getElementById('resignationForm');
        const submitBtn = document.getElementById('submitResignationBtn');
        const formData = new FormData(form);
        
        const lastWorkingDate = new Date(formData.get('last_working_date'));
        const today = new Date();
        const minDate = new Date();
        minDate.setDate(today.getDate() + 15);
        
        if (lastWorkingDate < minDate) {
            showNotification('error', 'Last working day must be at least 15 days from today.');
            return;
        }
        
        const resignationDate = new Date(formData.get('resignation_date'));
        if (resignationDate > today) {
            showNotification('error', 'Resignation date cannot be in the future.');
            return;
        }
        
        const noticePeriod = parseInt(formData.get('notice_period_days'));
        if (noticePeriod < 15) {
            showNotification('error', 'Notice period must be at least 15 days.');
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        fetch('includes/modules/resignation/resignation_form_handler.php', {
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
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit';
        });
    }
    
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeResignationForm();
        }
    });
    
    document.getElementById('resignationFormModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeResignationForm();
        }
    });
    </script>
</body>
</html>