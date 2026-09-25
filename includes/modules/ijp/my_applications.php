<?php
// ============================================
// IJP - MY APPLICATIONS
// ============================================

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    redirect('../../../login.php');
}

$user = getCurrentUser();
$user_id = $user['id'];

$applications = getUserApplications($pdo, $user_id);

// Include header
include __DIR__ . '/../../header.php';
?>

<style>
    /* ============================================
       IJP MY APPLICATIONS - DASHBOARD STYLES
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
        --green-bg: rgba(22, 163, 74, 0.06);
        --red: #dc2626;
        --red-bg: rgba(220, 38, 38, 0.06);
        --yellow: #d97706;
        --yellow-bg: rgba(217, 119, 6, 0.06);
        --purple: #7c3aed;
        --purple-bg: rgba(124, 58, 237, 0.06);
        --cyan: #0891b2;
        --cyan-bg: rgba(8, 145, 178, 0.06);
        --orange: #ea580c;
        --orange-bg: rgba(234, 88, 12, 0.06);
        --pink: #db2777;
        --pink-bg: rgba(219, 39, 119, 0.06);
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

    .anim-delay-1 { animation-delay: 0.05s; }
    .anim-delay-2 { animation-delay: 0.1s; }
    .anim-delay-3 { animation-delay: 0.15s; }
    .anim-delay-4 { animation-delay: 0.2s; }

    /* ===== PAGE HEADER ===== */
    .page-header {
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

    .page-header:hover {
        box-shadow: var(--shadow-md);
    }

    .page-header .header-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .page-header .header-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--blue), var(--purple));
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 20px;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }

    .page-header h1 {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .page-header .sub-text {
        font-size: 12px;
        font-weight: 500;
        color: var(--text-muted);
    }

    .page-header .sub-text i {
        color: var(--blue);
        margin-right: 4px;
    }

    .page-header .header-right {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .app-count-badge {
        background: var(--blue-bg);
        color: var(--blue);
        padding: 5px 14px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid rgba(37, 99, 235, 0.15);
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .back-btn {
        background: var(--bg-light);
        color: var(--text-secondary);
        border: 1px solid var(--border-light);
        padding: 6px 16px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 40px;
    }

    .back-btn:hover {
        background: var(--blue-light);
        border-color: var(--blue);
        color: var(--blue);
        transform: translateY(-2px);
    }

    /* ===== ALERT BOXES ===== */
    .alert-box {
        padding: 14px 18px;
        border-radius: var(--radius-sm);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        font-size: 13px;
        border-left: 4px solid;
        animation: fadeUp 0.4s ease;
    }

    .alert-box.success {
        background: var(--green-bg);
        color: var(--green);
        border-color: var(--green);
    }

    .alert-box.error {
        background: var(--red-bg);
        color: var(--red);
        border-color: var(--red);
    }

    .alert-box.info {
        background: var(--blue-bg);
        color: var(--blue);
        border-color: var(--blue);
    }

    .alert-box i {
        font-size: 18px;
    }

    .alert-box .close-alert {
        margin-left: auto;
        background: none;
        border: none;
        color: inherit;
        opacity: 0.6;
        cursor: pointer;
        font-size: 18px;
        transition: opacity 0.3s ease;
        padding: 0 4px;
    }

    .alert-box .close-alert:hover {
        opacity: 1;
    }

    /* ===== APPLICATIONS TABLE ===== */
    .applications-card {
        background: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
    }

    .applications-card:hover {
        box-shadow: var(--shadow-md);
    }

    .applications-card .card-header {
        padding: 16px 24px;
        background: var(--bg-light);
        border-bottom: 2px solid var(--border-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .applications-card .card-header h5 {
        font-size: 15px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .applications-card .card-header h5 i {
        color: var(--blue);
    }

    .applications-card .card-body {
        padding: 0;
        overflow-x: auto;
    }

    /* ===== TABLE STYLES ===== */
    .applications-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        min-width: 600px;
    }

    .applications-table thead {
        background: var(--bg-light);
    }

    .applications-table th {
        padding: 12px 16px;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--border-light);
        white-space: nowrap;
    }

    .applications-table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-light);
        vertical-align: middle;
        transition: background 0.2s ease;
    }

    .applications-table tbody tr {
        transition: background 0.2s ease;
    }

    .applications-table tbody tr:hover {
        background: var(--blue-bg);
    }

    .applications-table tbody tr:last-child td {
        border-bottom: none;
    }

    .applications-table .job-info {
        display: flex;
        flex-direction: column;
    }

    .applications-table .job-info .job-title {
        font-weight: 700;
        color: var(--text-primary);
        font-size: 14px;
    }

    .applications-table .job-info .job-title a {
        color: var(--text-primary);
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .applications-table .job-info .job-title a:hover {
        color: var(--blue);
    }

    .applications-table .job-info .job-meta {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .applications-table .job-info .job-meta i {
        color: var(--blue);
        width: 14px;
    }

    .applications-table .department-cell {
        font-weight: 600;
        color: var(--text-secondary);
    }

    .applications-table .date-cell {
        font-weight: 500;
        color: var(--text-secondary);
        white-space: nowrap;
    }

    .applications-table .date-cell i {
        color: var(--blue);
        margin-right: 4px;
    }

    /* ===== STATUS BADGES ===== */
    .status-badge-app {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 14px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }

    .status-badge-app.pending {
        background: var(--yellow-bg);
        color: var(--yellow);
        border: 1px solid rgba(217, 119, 6, 0.2);
    }

    .status-badge-app.reviewed {
        background: var(--blue-bg);
        color: var(--blue);
        border: 1px solid rgba(37, 99, 235, 0.2);
    }

    .status-badge-app.shortlisted {
        background: var(--cyan-bg);
        color: var(--cyan);
        border: 1px solid rgba(8, 145, 178, 0.2);
    }

    .status-badge-app.interviewed {
        background: var(--purple-bg);
        color: var(--purple);
        border: 1px solid rgba(124, 58, 237, 0.2);
    }

    .status-badge-app.offered {
        background: var(--green-bg);
        color: var(--green);
        border: 1px solid rgba(22, 163, 74, 0.2);
    }

    .status-badge-app.accepted {
        background: var(--green-bg);
        color: var(--green);
        border: 1px solid rgba(22, 163, 74, 0.3);
    }

    .status-badge-app.rejected {
        background: var(--red-bg);
        color: var(--red);
        border: 1px solid rgba(220, 38, 38, 0.2);
    }

    .status-badge-app.on_hold {
        background: var(--orange-bg);
        color: var(--orange);
        border: 1px solid rgba(234, 88, 12, 0.2);
    }

    .status-badge-app.withdrawn {
        background: var(--bg-light);
        color: var(--text-muted);
        border: 1px solid var(--border-light);
    }

    /* ===== ACTION BUTTONS ===== */
    .action-group {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .btn-action {
        padding: 5px 14px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 700;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: none;
        cursor: pointer;
    }

    .btn-action.view {
        background: var(--blue-bg);
        color: var(--blue);
        border: 1px solid rgba(37, 99, 235, 0.15);
    }

    .btn-action.view:hover {
        background: var(--blue);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    }

    .btn-action.withdraw {
        background: var(--red-bg);
        color: var(--red);
        border: 1px solid rgba(220, 38, 38, 0.15);
    }

    .btn-action.withdraw:hover {
        background: var(--red);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }

    .btn-action.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* ===== EMPTY STATE ===== */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: var(--white);
        border-radius: var(--radius);
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-sm);
        animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
    }

    .empty-state .empty-icon {
        font-size: 64px;
        color: var(--text-muted);
        opacity: 0.3;
        margin-bottom: 16px;
    }

    .empty-state h4 {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .empty-state p {
        color: var(--text-muted);
        font-size: 14px;
        margin-bottom: 20px;
    }

    .empty-state .btn-primary {
        background: linear-gradient(135deg, var(--blue), var(--purple));
        color: #fff;
        border: none;
        padding: 10px 28px;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .empty-state .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
    }

    /* ============================================
       RESPONSIVE
       ============================================ */

    @media (max-width: 992px) {
        .page-header {
            flex-direction: column;
            text-align: center;
            gap: 12px;
            padding: 18px 20px;
        }

        .page-header .header-left {
            flex-direction: column;
        }

        .page-header .header-right {
            flex-wrap: wrap;
            justify-content: center;
        }
    }

    @media (max-width: 768px) {
        .page-header h1 {
            font-size: 18px;
        }

        .page-header .header-icon {
            width: 44px;
            height: 44px;
            font-size: 18px;
        }

        .back-btn {
            height: 34px;
            font-size: 11px;
            padding: 4px 12px;
        }

        .app-count-badge {
            font-size: 11px;
            padding: 4px 12px;
        }

        .applications-table {
            font-size: 12px;
            min-width: 500px;
        }

        .applications-table th,
        .applications-table td {
            padding: 10px 12px;
        }

        .status-badge-app {
            font-size: 10px;
            padding: 3px 10px;
        }

        .btn-action {
            font-size: 10px;
            padding: 4px 10px;
        }

        .applications-card .card-header {
            padding: 14px 18px;
        }

        .applications-card .card-header h5 {
            font-size: 14px;
        }
    }

    @media (max-width: 480px) {
        .page-header {
            padding: 14px 14px;
        }

        .page-header h1 {
            font-size: 16px;
        }

        .applications-table {
            font-size: 11px;
            min-width: 400px;
        }

        .applications-table th,
        .applications-table td {
            padding: 8px 10px;
        }

        .applications-table .job-info .job-title {
            font-size: 12px;
        }

        .applications-table .job-info .job-meta {
            font-size: 10px;
        }

        .status-badge-app {
            font-size: 9px;
            padding: 2px 8px;
        }

        .btn-action {
            font-size: 9px;
            padding: 3px 8px;
        }

        .action-group {
            gap: 4px;
        }

        .alert-box {
            font-size: 12px;
            padding: 10px 14px;
        }
    }
</style>

<div class="container">
    <!-- ===== PAGE HEADER ===== -->
    <div class="page-header anim-fade-up anim-delay-1">
        <div class="header-left">
            <div class="header-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div>
                <h1>My Applications</h1>
                <div class="sub-text">
                    <i class="fas fa-map-pin"></i> Track all your job applications
                </div>
            </div>
        </div>
        <div class="header-right">
            <span class="app-count-badge">
                <i class="fas fa-list me-1"></i> <?= count($applications) ?> Application<?= count($applications) != 1 ? 's' : '' ?>
            </span>
            <a href="jobs.php" class="back-btn">
                <i class="fas fa-briefcase"></i> Browse Jobs
            </a>
        </div>
    </div>

    <!-- ===== SESSION MESSAGES ===== -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert-box <?= $_SESSION['message']['type'] === 'success' ? 'success' : 'error' ?> anim-fade-up anim-delay-2">
            <i class="fas fa-<?= $_SESSION['message']['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($_SESSION['message']['text']) ?>
            <button type="button" class="close-alert" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- ===== APPLICATIONS LIST ===== -->
    <?php if (!empty($applications)): ?>
        <div class="applications-card anim-fade-up anim-delay-3">
            <div class="card-header">
                <h5>
                    <i class="fas fa-list"></i> Your Applications
                </h5>
                <span style="font-size: 12px; color: var(--text-muted);">
                    <i class="fas fa-clock"></i> Last updated: <?= date('M d, Y') ?>
                </span>
            </div>
            <div class="card-body">
                <table class="applications-table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Department</th>
                            <th>Applied On</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <div class="job-info">
                                        <span class="job-title">
                                            <a href="job_details.php?id=<?= $app['job_id'] ?>">
                                                <?= htmlspecialchars($app['title']) ?>
                                            </a>
                                        </span>
                                        <span class="job-meta">
                                            <i class="fas fa-user-tag"></i> <?= htmlspecialchars($app['position']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="department-cell">
                                    <i class="fas fa-building" style="color: var(--blue); width: 14px;"></i>
                                    <?= htmlspecialchars($app['department']) ?>
                                </td>
                                <td class="date-cell">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?= date('M d, Y', strtotime($app['created_at'])) ?>
                                </td>
                                <td>
                                    <span class="status-badge-app <?= $app['status'] ?>">
                                        <i class="fas fa-circle" style="font-size: 6px;"></i>
                                        <?= ucfirst($app['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="action-group" style="justify-content: flex-end;">
                                        <a href="application_details.php?id=<?= $app['id'] ?>" 
                                           class="btn-action view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($app['status'] === 'pending'): ?>
                                            <a href="withdraw_application.php?id=<?= $app['id'] ?>" 
                                               class="btn-action withdraw" 
                                               onclick="return confirm('Are you sure you want to withdraw this application?')">
                                                <i class="fas fa-times"></i> Withdraw
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <!-- ===== EMPTY STATE ===== -->
        <div class="empty-state anim-fade-up anim-delay-3">
            <div class="empty-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <h4>No Applications Yet</h4>
            <p>You haven't applied for any positions yet. Start your job search now!</p>
            <a href="jobs.php" class="btn-primary">
                <i class="fas fa-search me-1"></i> Browse Jobs
            </a>
        </div>
    <?php endif; ?>
</div>