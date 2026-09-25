<?php
// ============================================
// IJP - MANAGE JOBS (HR/ER DEPARTMENT)
// ============================================

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    redirect('../../../login.php');
}

$user = getCurrentUser();
$role = $user['role'];
$process = $user['process'] ?? null;

// Only HR/ER departments can manage jobs
$user_dept = strtolower($user['department'] ?? '');
if (!in_array($user_dept, ['hr', 'er', 'human resources', 'employee relations'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'You do not have permission to manage jobs.'];
    redirect('index.php');
}

$jobs = getManagedJobs($pdo, $user['id'], $role, $process);

// Include header
include $_SERVER['DOCUMENT_ROOT'] . '/attendance/includes/header.php';
?>

<style>
    /* ============================================
       IJP MANAGE JOBS - DASHBOARD STYLES
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

    .job-count-badge {
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

    .btn-primary-header {
        background: linear-gradient(135deg, var(--blue), var(--purple));
        color: #fff;
        border: none;
        padding: 8px 20px;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 40px;
    }

    .btn-primary-header:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
        color: #fff;
    }

    /* ===== ALERT BOXES ===== */
    .alert-box {
        padding: 14px 18px;
        border-radius: var(--radius-sm);
        margin-bottom: 20px;
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

    /* ===== JOBS TABLE ===== */
    .jobs-card {
        background: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
    }

    .jobs-card:hover {
        box-shadow: var(--shadow-md);
    }

    .jobs-card .card-header {
        padding: 16px 24px;
        background: var(--bg-light);
        border-bottom: 2px solid var(--border-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .jobs-card .card-header h5 {
        font-size: 15px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .jobs-card .card-header h5 i {
        color: var(--blue);
    }

    .jobs-card .card-body {
        padding: 0;
        overflow-x: auto;
    }

    /* ===== TABLE STYLES ===== */
    .jobs-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        min-width: 800px;
    }

    .jobs-table thead {
        background: var(--bg-light);
    }

    .jobs-table th {
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

    .jobs-table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-light);
        vertical-align: middle;
        transition: background 0.2s ease;
    }

    .jobs-table tbody tr {
        transition: background 0.2s ease;
    }

    .jobs-table tbody tr:hover {
        background: var(--blue-bg);
    }

    .jobs-table tbody tr:last-child td {
        border-bottom: none;
    }

    .jobs-table .job-info {
        display: flex;
        flex-direction: column;
    }

    .jobs-table .job-info .job-title {
        font-weight: 700;
        color: var(--text-primary);
        font-size: 14px;
    }

    .jobs-table .job-info .job-title a {
        color: var(--text-primary);
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .jobs-table .job-info .job-title a:hover {
        color: var(--blue);
    }

    .jobs-table .job-info .job-meta {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .jobs-table .job-info .job-meta i {
        color: var(--blue);
        width: 14px;
    }

    .jobs-table .department-cell {
        font-weight: 600;
        color: var(--text-secondary);
    }

    .jobs-table .department-cell i {
        color: var(--blue);
        margin-right: 4px;
    }

    .jobs-table .vacancies-cell {
        font-weight: 700;
        text-align: center;
        color: var(--text-primary);
    }

    .jobs-table .date-cell {
        font-weight: 500;
        color: var(--text-secondary);
        white-space: nowrap;
    }

    .jobs-table .date-cell i {
        color: var(--blue);
        margin-right: 4px;
    }

    /* ===== STATUS BADGES ===== */
    .status-badge-mgmt {
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

    .status-badge-mgmt.published {
        background: var(--green-bg);
        color: var(--green);
        border: 1px solid rgba(22, 163, 74, 0.2);
    }

    .status-badge-mgmt.draft {
        background: var(--yellow-bg);
        color: var(--yellow);
        border: 1px solid rgba(217, 119, 6, 0.2);
    }

    .status-badge-mgmt.closed {
        background: var(--red-bg);
        color: var(--red);
        border: 1px solid rgba(220, 38, 38, 0.2);
    }

    .status-badge-mgmt.archived {
        background: var(--bg-light);
        color: var(--text-muted);
        border: 1px solid var(--border-light);
    }

    /* ===== APPLICATION BADGES ===== */
    .app-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 700;
        margin-right: 4px;
    }

    .app-badge.total {
        background: var(--blue-bg);
        color: var(--blue);
        border: 1px solid rgba(37, 99, 235, 0.15);
    }

    .app-badge.pending {
        background: var(--yellow-bg);
        color: var(--yellow);
        border: 1px solid rgba(217, 119, 6, 0.2);
    }

    .app-badge.reviewed {
        background: var(--cyan-bg);
        color: var(--cyan);
        border: 1px solid rgba(8, 145, 178, 0.2);
    }

    /* ===== ACTION BUTTONS ===== */
    .action-group {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }

    .btn-action-mgmt {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--border-light);
        background: var(--white);
        color: var(--text-secondary);
        cursor: pointer;
    }

    .btn-action-mgmt:hover {
        transform: translateY(-2px);
    }

    .btn-action-mgmt.view-apps {
        background: var(--blue-bg);
        color: var(--blue);
        border-color: rgba(37, 99, 235, 0.15);
    }

    .btn-action-mgmt.view-apps:hover {
        background: var(--blue);
        color: #fff;
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    }

    .btn-action-mgmt.schedule {
        background: var(--purple-bg);
        color: var(--purple);
        border-color: rgba(124, 58, 237, 0.15);
    }

    .btn-action-mgmt.schedule:hover {
        background: var(--purple);
        color: #fff;
        box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
    }

    .btn-action-mgmt.close {
        background: var(--yellow-bg);
        color: var(--yellow);
        border-color: rgba(217, 119, 6, 0.15);
    }

    .btn-action-mgmt.close:hover {
        background: var(--yellow);
        color: #fff;
        box-shadow: 0 4px 15px rgba(217, 119, 6, 0.3);
    }

    .btn-action-mgmt.delete {
        background: var(--red-bg);
        color: var(--red);
        border-color: rgba(220, 38, 38, 0.15);
    }

    .btn-action-mgmt.delete:hover {
        background: var(--red);
        color: #fff;
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }

    .btn-action-mgmt.edit {
        background: var(--cyan-bg);
        color: var(--cyan);
        border-color: rgba(8, 145, 178, 0.15);
    }

    .btn-action-mgmt.edit:hover {
        background: var(--cyan);
        color: #fff;
        box-shadow: 0 4px 15px rgba(8, 145, 178, 0.3);
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

        .btn-primary-header {
            height: 34px;
            font-size: 12px;
            padding: 6px 14px;
        }

        .job-count-badge {
            font-size: 11px;
            padding: 4px 12px;
        }

        .jobs-table {
            font-size: 12px;
            min-width: 600px;
        }

        .jobs-table th,
        .jobs-table td {
            padding: 10px 12px;
        }

        .status-badge-mgmt {
            font-size: 10px;
            padding: 3px 10px;
        }

        .btn-action-mgmt {
            width: 28px;
            height: 28px;
            font-size: 11px;
        }

        .jobs-card .card-header {
            padding: 14px 18px;
        }

        .jobs-card .card-header h5 {
            font-size: 14px;
        }

        .app-badge {
            font-size: 10px;
            padding: 2px 8px;
        }
    }

    @media (max-width: 480px) {
        .page-header {
            padding: 14px 14px;
        }

        .page-header h1 {
            font-size: 16px;
        }

        .jobs-table {
            font-size: 11px;
            min-width: 500px;
        }

        .jobs-table th,
        .jobs-table td {
            padding: 8px 10px;
        }

        .jobs-table .job-info .job-title {
            font-size: 12px;
        }

        .jobs-table .job-info .job-meta {
            font-size: 10px;
        }

        .status-badge-mgmt {
            font-size: 9px;
            padding: 2px 8px;
        }

        .btn-action-mgmt {
            width: 24px;
            height: 24px;
            font-size: 10px;
            border-radius: 6px;
        }

        .action-group {
            gap: 3px;
        }

        .app-badge {
            font-size: 9px;
            padding: 2px 6px;
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
                <i class="fas fa-list"></i>
            </div>
            <div>
                <h1>Manage Jobs</h1>
                <div class="sub-text">
                    <i class="fas fa-map-pin"></i> Create and manage job postings
                </div>
            </div>
        </div>
        <div class="header-right">
            <span class="job-count-badge">
                <i class="fas fa-briefcase me-1"></i> <?= count($jobs) ?> Job<?= count($jobs) != 1 ? 's' : '' ?>
            </span>
            <a href="create_job.php" class="btn-primary-header">
                <i class="fas fa-plus"></i> Post New Job
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

    <!-- ===== JOBS LIST ===== -->
    <?php if (!empty($jobs)): ?>
        <div class="jobs-card anim-fade-up anim-delay-3">
            <div class="card-header">
                <h5>
                    <i class="fas fa-list"></i> All Job Postings
                </h5>
                <span style="font-size: 12px; color: var(--text-muted);">
                    <i class="fas fa-clock"></i> Last updated: <?= date('M d, Y') ?>
                </span>
            </div>
            <div class="card-body">
                <table class="jobs-table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Department</th>
                            <th style="text-align: center;">Vacancies</th>
                            <th>Status</th>
                            <th>Applications</th>
                            <th>Posted</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td>
                                    <div class="job-info">
                                        <span class="job-title">
                                            <a href="job_details.php?id=<?= $job['id'] ?>">
                                                <?= htmlspecialchars($job['title']) ?>
                                            </a>
                                        </span>
                                        <span class="job-meta">
                                            <i class="fas fa-user-tag"></i> <?= htmlspecialchars($job['position']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="department-cell">
                                    <i class="fas fa-building"></i>
                                    <?= htmlspecialchars($job['department']) ?>
                                </td>
                                <td class="vacancies-cell">
                                    <?= $job['vacancies'] ?? 1 ?>
                                </td>
                                <td>
                                    <span class="status-badge-mgmt <?= $job['status'] ?>">
                                        <i class="fas fa-circle" style="font-size: 6px;"></i>
                                        <?= ucfirst($job['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="app-badge total">
                                        <i class="fas fa-users"></i> <?= $job['total_applications'] ?? 0 ?>
                                    </span>
                                    <?php if (($job['pending_applications'] ?? 0) > 0): ?>
                                        <span class="app-badge pending">
                                            <i class="fas fa-clock"></i> <?= $job['pending_applications'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="date-cell">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?= date('M d, Y', strtotime($job['created_at'])) ?>
                                </td>
                                <td style="text-align: center;">
                                    <div class="action-group" style="justify-content: center;">
                                        <!-- View Applications -->
                                        <a href="view_applications.php?job_id=<?= $job['id'] ?>" 
                                           class="btn-action-mgmt view-apps" 
                                           title="View Applications">
                                            <i class="fas fa-users"></i>
                                        </a>
                                        
                                        <!-- Schedule Interview -->
                                        <!-- <?php if ($job['status'] === 'published'): ?>
                                            <a href="view_applications.php?job_id=<?= $job['id'] ?>" 
                                               class="btn-action-mgmt schedule" 
                                               title="Schedule Interviews">
                                                <i class="fas fa-calendar-plus"></i>
                                            </a>
                                        <?php endif; ?> -->
                                        
                                        <!-- Edit Job -->
                                        <!-- <a href="edit_job.php?id=<?= $job['id'] ?>" 
                                           class="btn-action-mgmt edit" 
                                           title="Edit Job">
                                            <i class="fas fa-edit"></i>
                                        </a> -->
                                        
                                        <!-- Close Job -->
                                        <!-- <?php if ($job['status'] === 'published'): ?>
                                            <a href="close_job.php?id=<?= $job['id'] ?>" 
                                               class="btn-action-mgmt close" 
                                               title="Close Job"
                                               onclick="return confirm('Are you sure you want to close this job posting?')">
                                                <i class="fas fa-times-circle"></i>
                                            </a>
                                        <?php endif; ?> -->
                                        
                                        <!-- Delete Job -->
                                        <!-- <a href="delete_job.php?id=<?= $job['id'] ?>" 
                                           class="btn-action-mgmt delete" 
                                           title="Delete Job"
                                           onclick="return confirm('Are you sure you want to delete this job permanently? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i>
                                        </a> -->
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
                <i class="fas fa-briefcase"></i>
            </div>
            <h4>No Job Postings</h4>
            <p>You haven't created any job postings yet. Start posting new opportunities!</p>
            <a href="create_job.php" class="btn-primary">
                <i class="fas fa-plus me-1"></i> Post New Job
            </a>
        </div>
    <?php endif; ?>
</div>