<?php
// ============================================
// IJP - VIEW APPLICATIONS FOR A JOB
// ============================================

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    redirect('../../../login.php');
}

$user = getCurrentUser();
$role = $user['role'];

// Only HR/ER departments can view applications
$user_dept = strtolower($user['department'] ?? '');
if (!in_array($user_dept, ['hr', 'er', 'human resources', 'employee relations'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'You do not have permission to view applications.'];
    redirect('index.php');
}

$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
if ($job_id <= 0) {
    redirect('manage_jobs.php');
}

// Get job details
$job = getJobDetails($pdo, $job_id);
if (!$job) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Job not found.'];
    redirect('manage_jobs.php');
}

// Get applications
$applications = getJobApplications($pdo, $job_id);

// Get status counts
$status_counts = [
    'total' => count($applications),
    'pending' => 0,
    'shortlisted' => 0,
    'interviewed' => 0,
    'selected' => 0,
    'offered' => 0,
    'accepted' => 0,
    'rejected' => 0,
    'on_hold' => 0
];

$today_counts = [
    'selected' => 0,
    'rejected' => 0,
    'interviewed' => 0
];

$today = date('Y-m-d');

foreach ($applications as $app) {
    $status = strtolower($app['status'] ?? 'pending');
    
    if (array_key_exists($status, $status_counts)) {
        $status_counts[$status]++;
    }

    if (isset($app['updated_at']) && date('Y-m-d', strtotime($app['updated_at'])) == $today) {
        if ($status == 'selected') $today_counts['selected']++;
        if ($status == 'rejected') $today_counts['rejected']++;
        if ($status == 'interviewed') $today_counts['interviewed']++;
    }
}

// Include header
include $_SERVER['DOCUMENT_ROOT'] . '/attendance/includes/header.php';
?>

<style>
    /* ============================================
       IJP VIEW APPLICATIONS - COMPLETE STYLES
       ============================================ */

    .ijp-applications-wrapper {
        padding: 0;
        margin: 0;
    }

    /* ===== PAGE HEADER ===== */
    .ijp-page-header {
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
        flex-wrap: wrap;
        gap: 12px;
    }

    .ijp-page-header:hover {
        box-shadow: var(--shadow-md);
    }

    .ijp-page-header .header-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .ijp-page-header .header-icon {
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
        flex-shrink: 0;
    }

    .ijp-page-header h1 {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .ijp-page-header .sub-text {
        font-size: 12px;
        font-weight: 500;
        color: var(--text-muted);
    }

    .ijp-page-header .sub-text i {
        color: var(--blue);
        margin-right: 4px;
    }

    .ijp-page-header .header-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ijp-btn {
        padding: 6px 16px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 38px;
        border: 1px solid var(--border-light);
        cursor: pointer;
    }

    .ijp-btn:hover {
        transform: translateY(-2px);
    }

    .ijp-btn-back {
        background: var(--bg-light);
        color: var(--text-secondary);
    }

    .ijp-btn-back:hover {
        background: var(--blue-light);
        border-color: var(--blue);
        color: var(--blue);
    }

    .ijp-btn-view-job {
        background: var(--blue-bg);
        color: var(--blue);
        border-color: rgba(37, 99, 235, 0.15);
    }

    .ijp-btn-view-job:hover {
        background: var(--blue);
        color: #fff;
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    }

    /* ===== JOB INFO ===== */
    .ijp-job-info {
        background: var(--white);
        border-radius: var(--radius);
        padding: 16px 24px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
    }

    .ijp-job-info .job-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .ijp-job-info .job-meta {
        font-size: 13px;
        color: var(--text-muted);
        margin-top: 4px;
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        align-items: center;
    }

    .ijp-job-info .job-meta i {
        color: var(--blue);
        width: 16px;
        margin-right: 2px;
    }

    .ijp-job-info .job-meta .separator {
        margin: 0 8px;
        color: var(--border-light);
    }

    /* ===== STATS CARDS ===== */
    .ijp-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .ijp-stat-card {
        border-radius: var(--radius);
        padding: 16px 20px;
        border: 1px solid var(--border-light);
        background: var(--white);
        box-shadow: var(--shadow-sm);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        overflow: hidden;
    }

    .ijp-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
    }

    .ijp-stat-card .stat-icon {
        font-size: 28px;
        opacity: 0.25;
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
    }

    .ijp-stat-card .stat-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.8;
        margin-bottom: 2px;
        position: relative;
        z-index: 1;
    }

    .ijp-stat-card .stat-number {
        font-size: 28px;
        font-weight: 800;
        position: relative;
        z-index: 1;
    }

    .ijp-stat-card .stat-today {
        font-size: 11px;
        font-weight: 600;
        opacity: 0.8;
        margin-top: 2px;
        position: relative;
        z-index: 1;
    }

    .ijp-stat-card.primary {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff;
    }

    .ijp-stat-card.success {
        background: linear-gradient(135deg, #16a34a, #15803d);
        color: #fff;
    }

    .ijp-stat-card.info {
        background: linear-gradient(135deg, #0891b2, #0e7490);
        color: #fff;
    }

    .ijp-stat-card.danger {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: #fff;
    }

    /* ===== ALERT BOXES ===== */
    .ijp-alert {
        padding: 14px 18px;
        border-radius: var(--radius-sm);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        font-size: 13px;
        border-left: 4px solid;
        animation: fadeIn 0.3s ease;
    }

    .ijp-alert.success {
        background: var(--green-bg);
        color: var(--green);
        border-color: var(--green);
    }

    .ijp-alert.error {
        background: var(--red-bg);
        color: var(--red);
        border-color: var(--red);
    }

    .ijp-alert .close-alert {
        margin-left: auto;
        background: none;
        border: none;
        color: inherit;
        opacity: 0.6;
        cursor: pointer;
        font-size: 18px;
        padding: 0 4px;
        transition: opacity 0.3s ease;
    }

    .ijp-alert .close-alert:hover {
        opacity: 1;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ===== APPLICATIONS CARD ===== */
    .ijp-applications-card {
        background: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        margin-bottom: 24px;
    }

    .ijp-applications-card:hover {
        box-shadow: var(--shadow-md);
    }

    .ijp-applications-card .card-header {
        padding: 14px 20px;
        background: var(--bg-light);
        border-bottom: 2px solid var(--border-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .ijp-applications-card .card-header h6 {
        font-size: 14px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ijp-applications-card .card-header h6 i {
        color: var(--blue);
    }

    .ijp-applications-card .card-body {
        padding: 0;
        overflow-x: auto;
    }

    .ijp-applications-card .card-footer {
        padding: 12px 20px;
        background: var(--bg-light);
        border-top: 1px solid var(--border-light);
        font-size: 13px;
        color: var(--text-muted);
        font-weight: 600;
        text-align: center;
    }

    /* ===== FILTER BUTTONS ===== */
    .ijp-filter-group {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }

    .ijp-filter-btn {
        padding: 4px 14px;
        border-radius: 50px;
        border: 1px solid var(--border-light);
        background: var(--white);
        color: var(--text-secondary);
        font-size: 10px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: 'Inter', sans-serif;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }

    .ijp-filter-btn:hover {
        background: var(--blue-bg);
        border-color: var(--blue);
        color: var(--blue);
    }

    .ijp-filter-btn.active {
        background: var(--blue);
        border-color: var(--blue);
        color: #fff;
    }

    /* ===== TABLE STYLES ===== */
    .ijp-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        min-width: 900px;
    }

    .ijp-table thead {
        background: var(--bg-light);
    }

    .ijp-table th {
        padding: 10px 14px;
        text-align: left;
        font-size: 10px;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--border-light);
        white-space: nowrap;
    }

    .ijp-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-light);
        vertical-align: middle;
    }

    .ijp-table tbody tr {
        transition: background 0.2s ease;
    }

    .ijp-table tbody tr:hover {
        background: var(--blue-bg);
    }

    .ijp-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* ===== APPLICANT INFO ===== */
    .ijp-applicant-info {
        display: flex;
        flex-direction: column;
    }

    .ijp-applicant-info .name {
        font-weight: 700;
        color: var(--text-primary);
        font-size: 14px;
    }

    .ijp-applicant-info .email {
        font-size: 11px;
        color: var(--text-muted);
    }

    .ijp-applicant-info .email i {
        color: var(--blue);
        margin-right: 2px;
    }

    /* ===== ROLE BADGE ===== */
    .ijp-role-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 50px;
        font-size: 10px;
        font-weight: 700;
        background: var(--bg-light);
        color: var(--text-secondary);
        border: 1px solid var(--border-light);
    }

    /* ===== DATE CELL ===== */
    .ijp-date-cell {
        font-weight: 500;
        color: var(--text-secondary);
        white-space: nowrap;
    }

    .ijp-date-cell i {
        color: var(--blue);
        margin-right: 4px;
    }

    /* ===== STATUS BADGES ===== */
    .ijp-status-badge {
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

    .ijp-status-badge.pending {
        background: var(--yellow-bg);
        color: var(--yellow);
        border: 1px solid rgba(217, 119, 6, 0.2);
    }

    .ijp-status-badge.shortlisted {
        background: var(--cyan-bg);
        color: var(--cyan);
        border: 1px solid rgba(8, 145, 178, 0.2);
    }

    .ijp-status-badge.interviewed {
        background: var(--purple-bg);
        color: var(--purple);
        border: 1px solid rgba(124, 58, 237, 0.2);
    }

    .ijp-status-badge.selected {
        background: var(--green-bg);
        color: var(--green);
        border: 1px solid rgba(22, 163, 74, 0.2);
    }

    .ijp-status-badge.offered {
        background: var(--green-bg);
        color: var(--green);
        border: 1px solid rgba(22, 163, 74, 0.3);
    }

    .ijp-status-badge.accepted {
        background: var(--green-bg);
        color: var(--green);
        border: 1px solid rgba(22, 163, 74, 0.4);
    }

    .ijp-status-badge.rejected {
        background: var(--red-bg);
        color: var(--red);
        border: 1px solid rgba(220, 38, 38, 0.2);
    }

    .ijp-status-badge.on_hold {
        background: var(--orange-bg);
        color: var(--orange);
        border: 1px solid rgba(234, 88, 12, 0.2);
    }

    /* ===== INTERVIEW INFO ===== */
    .ijp-interview-info {
        font-size: 11px;
        display: flex;
        flex-wrap: wrap;
        gap: 3px;
        align-items: center;
    }

    .ijp-interview-info .badge-sm {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 50px;
        font-size: 9px;
        font-weight: 700;
    }

    .badge-sm.primary {
        background: var(--blue-bg);
        color: var(--blue);
        border: 1px solid rgba(37, 99, 235, 0.15);
    }

    .badge-sm.info {
        background: var(--cyan-bg);
        color: var(--cyan);
        border: 1px solid rgba(8, 145, 178, 0.15);
    }

    .badge-sm.success {
        background: var(--green-bg);
        color: var(--green);
        border: 1px solid rgba(22, 163, 74, 0.15);
    }

    .badge-sm.warning {
        background: var(--yellow-bg);
        color: var(--yellow);
        border: 1px solid rgba(217, 119, 6, 0.15);
    }

    .badge-sm.danger {
        background: var(--red-bg);
        color: var(--red);
        border: 1px solid rgba(220, 38, 38, 0.15);
    }

    .ijp-no-interview {
        font-size: 11px;
        color: var(--text-muted);
    }

    /* ===== ACTION BUTTONS ===== */
    .ijp-action-group {
        display: flex;
        gap: 3px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .ijp-btn-action {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        font-size: 12px;
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

    .ijp-btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .ijp-btn-action.view {
        background: var(--blue-bg);
        color: var(--blue);
        border-color: rgba(37, 99, 235, 0.15);
    }
    .ijp-btn-action.view:hover {
        background: var(--blue);
        color: #fff;
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    }

    .ijp-btn-action.success {
        background: var(--green-bg);
        color: var(--green);
        border-color: rgba(22, 163, 74, 0.15);
    }
    .ijp-btn-action.success:hover {
        background: var(--green);
        color: #fff;
        box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3);
    }

    .ijp-btn-action.danger {
        background: var(--red-bg);
        color: var(--red);
        border-color: rgba(220, 38, 38, 0.15);
    }
    .ijp-btn-action.danger:hover {
        background: var(--red);
        color: #fff;
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }

    .ijp-btn-action.primary {
        background: var(--cyan-bg);
        color: var(--cyan);
        border-color: rgba(8, 145, 178, 0.15);
    }
    .ijp-btn-action.primary:hover {
        background: var(--cyan);
        color: #fff;
        box-shadow: 0 4px 15px rgba(8, 145, 178, 0.3);
    }

    .ijp-btn-action.warning {
        background: var(--yellow-bg);
        color: var(--yellow);
        border-color: rgba(217, 119, 6, 0.15);
    }
    .ijp-btn-action.warning:hover {
        background: var(--yellow);
        color: #fff;
        box-shadow: 0 4px 15px rgba(217, 119, 6, 0.3);
    }

    .ijp-btn-action.info {
        background: var(--purple-bg);
        color: var(--purple);
        border-color: rgba(124, 58, 237, 0.15);
    }
    .ijp-btn-action.info:hover {
        background: var(--purple);
        color: #fff;
        box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
    }

    /* ===== EMPTY STATE ===== */
    .ijp-empty-state {
        text-align: center;
        padding: 60px 20px;
        background: var(--white);
        border-radius: var(--radius);
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-sm);
    }

    .ijp-empty-state .empty-icon {
        font-size: 64px;
        color: var(--text-muted);
        opacity: 0.3;
        margin-bottom: 16px;
    }

    .ijp-empty-state h4 {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .ijp-empty-state p {
        color: var(--text-muted);
        font-size: 14px;
        margin-bottom: 20px;
    }

    .ijp-empty-state .btn-primary {
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

    .ijp-empty-state .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
    }

    /* ============================================
       MODAL STYLES
       ============================================ */
    .modal-header-gradient {
        background: linear-gradient(135deg, var(--blue), var(--purple));
        color: #fff;
        border-radius: var(--radius) var(--radius) 0 0;
        padding: 18px 24px;
    }

    .modal-header-gradient .modal-title {
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-header-gradient .btn-close-white {
        filter: brightness(0) invert(1);
    }

    .modal-header-success {
        background: linear-gradient(135deg, var(--green), #15803d);
        color: #fff;
        border-radius: var(--radius) var(--radius) 0 0;
        padding: 18px 24px;
    }

    .modal-header-success .modal-title {
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-header-danger {
        background: linear-gradient(135deg, var(--red), #b91c1c);
        color: #fff;
        border-radius: var(--radius) var(--radius) 0 0;
        padding: 18px 24px;
    }

    .modal-header-danger .modal-title {
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-header-info {
        background: linear-gradient(135deg, var(--cyan), #0e7490);
        color: #fff;
        border-radius: var(--radius) var(--radius) 0 0;
        padding: 18px 24px;
    }

    .modal-header-info .modal-title {
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-body {
        padding: 24px;
    }

    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--border-light);
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }

    .modal .form-label {
        font-size: 13px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .modal .form-control,
    .modal .form-select {
        padding: 10px 14px;
        border: 2px solid var(--border-light);
        border-radius: var(--radius-sm);
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        font-weight: 500;
        color: var(--text-primary);
        background: var(--white);
        transition: all 0.3s ease;
        width: 100%;
    }

    .modal .form-control:focus,
    .modal .form-select:focus {
        outline: none;
        border-color: var(--blue);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .modal .form-control::placeholder {
        color: var(--text-muted);
        font-weight: 400;
    }

    .modal .btn-secondary {
        background: var(--bg-light);
        color: var(--text-secondary);
        border: 1px solid var(--border-light);
        padding: 8px 20px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.3s ease;
    }

    .modal .btn-secondary:hover {
        background: var(--border-light);
    }

    .modal .btn-primary-modal {
        background: linear-gradient(135deg, var(--blue), var(--purple));
        color: #fff;
        border: none;
        padding: 8px 20px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
    }

    .modal .btn-primary-modal:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
    }

    .modal .btn-success-modal {
        background: linear-gradient(135deg, var(--green), #15803d);
        color: #fff;
        border: none;
        padding: 8px 20px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(22, 163, 74, 0.2);
    }

    .modal .btn-success-modal:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(22, 163, 74, 0.3);
    }

    .rating-input {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        padding: 4px 0;
    }

    .rating-input .form-check {
        padding-left: 0;
    }

    .rating-input .form-check-input {
        margin-right: 4px;
    }

    .rating-input .form-check-label {
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
    }

    /* ============================================
       RESPONSIVE
       ============================================ */

    @media (max-width: 992px) {
        .ijp-stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .ijp-page-header {
            flex-direction: column;
            text-align: center;
            padding: 18px 20px;
        }

        .ijp-page-header .header-left {
            flex-direction: column;
        }

        .ijp-page-header .header-right {
            justify-content: center;
        }
    }

    @media (max-width: 768px) {
        .ijp-page-header h1 {
            font-size: 18px;
        }

        .ijp-page-header .header-icon {
            width: 44px;
            height: 44px;
            font-size: 18px;
        }

        .ijp-btn {
            height: 34px;
            font-size: 11px;
            padding: 4px 12px;
        }

        .ijp-stats-grid {
            gap: 10px;
        }

        .ijp-stat-card .stat-number {
            font-size: 22px;
        }

        .ijp-stat-card {
            padding: 12px 16px;
        }

        .ijp-stat-card .stat-icon {
            font-size: 22px;
        }

        .ijp-table {
            font-size: 12px;
            min-width: 700px;
        }

        .ijp-table th,
        .ijp-table td {
            padding: 8px 10px;
        }

        .ijp-filter-btn {
            font-size: 9px;
            padding: 3px 10px;
        }

        .ijp-btn-action {
            width: 28px;
            height: 28px;
            font-size: 11px;
        }

        .ijp-applications-card .card-header {
            padding: 12px 16px;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
        }

        .ijp-applications-card .card-header h6 {
            font-size: 13px;
            justify-content: center;
        }

        .ijp-filter-group {
            justify-content: center;
        }

        .ijp-job-info {
            padding: 14px 18px;
        }

        .ijp-job-info .job-title {
            font-size: 16px;
        }

        .ijp-job-info .job-meta {
            font-size: 12px;
        }

        .ijp-job-info .job-meta .separator {
            margin: 0 4px;
        }
    }

    @media (max-width: 480px) {
        .ijp-stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .ijp-stat-card .stat-number {
            font-size: 18px;
        }

        .ijp-stat-card .stat-label {
            font-size: 9px;
        }

        .ijp-stat-card .stat-today {
            font-size: 9px;
        }

        .ijp-stat-card .stat-icon {
            font-size: 18px;
            right: 10px;
        }

        .ijp-page-header {
            padding: 14px;
        }

        .ijp-page-header h1 {
            font-size: 16px;
        }

        .ijp-table {
            font-size: 11px;
            min-width: 600px;
        }

        .ijp-table th,
        .ijp-table td {
            padding: 6px 8px;
        }

        .ijp-filter-group {
            gap: 3px;
        }

        .ijp-filter-btn {
            font-size: 8px;
            padding: 2px 8px;
        }

        .ijp-btn-action {
            width: 24px;
            height: 24px;
            font-size: 10px;
            border-radius: 4px;
        }

        .ijp-action-group {
            gap: 2px;
        }

        .ijp-status-badge {
            font-size: 9px;
            padding: 2px 8px;
        }

        .ijp-interview-info {
            font-size: 9px;
        }

        .ijp-interview-info .badge-sm {
            font-size: 8px;
            padding: 1px 6px;
        }

        .ijp-alert {
            font-size: 12px;
            padding: 10px 14px;
        }

        .ijp-applications-card .card-footer {
            font-size: 12px;
            padding: 10px 16px;
        }

        .modal-body {
            padding: 16px;
        }

        .rating-input .form-check-label {
            font-size: 12px;
        }
    }

    /* ============================================
       SCROLLBAR STYLING
       ============================================ */
    .ijp-table::-webkit-scrollbar {
        height: 6px;
    }

    .ijp-table::-webkit-scrollbar-track {
        background: var(--bg-light);
        border-radius: 4px;
    }

    .ijp-table::-webkit-scrollbar-thumb {
        background: var(--blue);
        border-radius: 4px;
    }

    .ijp-table::-webkit-scrollbar-thumb:hover {
        background: var(--blue-hover);
    }
</style>

<div class="container ijp-applications-wrapper">
    <!-- ===== PAGE HEADER ===== -->
    <div class="ijp-page-header">
        <div class="header-left">
            <div class="header-icon">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <h1>Applications</h1>
                <div class="sub-text">
                    <i class="fas fa-map-pin"></i> Manage applications for this position
                </div>
            </div>
        </div>
        <div class="header-right">
            <a href="manage_jobs.php" class="ijp-btn ijp-btn-back">
                <i class="fas fa-arrow-left"></i> Back to Jobs
            </a>
            <a href="job_details.php?id=<?= $job_id ?>" class="ijp-btn ijp-btn-view-job">
                <i class="fas fa-eye"></i> View Job
            </a>
        </div>
    </div>

    <!-- ===== JOB INFO ===== -->
    <div class="ijp-job-info">
        <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
        <div class="job-meta">
            <i class="fas fa-building"></i> <?= htmlspecialchars($job['department']) ?>
            <span class="separator">|</span>
            <i class="fas fa-user-tag"></i> <?= htmlspecialchars($job['position']) ?>
            <span class="separator">|</span>
            <i class="fas fa-users"></i> <?= $status_counts['total'] ?> Application<?= $status_counts['total'] != 1 ? 's' : '' ?>
        </div>
    </div>

    <!-- ===== STATS CARDS ===== -->
    <div class="ijp-stats-grid">
        <div class="ijp-stat-card primary">
            <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
            <div class="stat-label">Total Applications</div>
            <div class="stat-number"><?= $status_counts['total'] ?></div>
        </div>

        <div class="ijp-stat-card success">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Selected / Shortlist</div>
            <div class="stat-number"><?= ($status_counts['selected'] + $status_counts['shortlisted']) ?></div>
            <?php if ($today_counts['selected'] > 0): ?>
                <div class="stat-today">+<?= $today_counts['selected'] ?> today</div>
            <?php endif; ?>
        </div>

        <div class="ijp-stat-card info">
            <div class="stat-icon"><i class="fas fa-video"></i></div>
            <div class="stat-label">Interviewed</div>
            <div class="stat-number"><?= $status_counts['interviewed'] ?></div>
            <?php if ($today_counts['interviewed'] > 0): ?>
                <div class="stat-today"><?= $today_counts['interviewed'] ?> today</div>
            <?php endif; ?>
        </div>

        <div class="ijp-stat-card danger">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-label">Rejected</div>
            <div class="stat-number"><?= $status_counts['rejected'] ?></div>
            <?php if ($today_counts['rejected'] > 0): ?>
                <div class="stat-today">+<?= $today_counts['rejected'] ?> today</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== SESSION MESSAGES ===== -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="ijp-alert <?= $_SESSION['message']['type'] === 'success' ? 'success' : 'error' ?>">
            <i class="fas fa-<?= $_SESSION['message']['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($_SESSION['message']['text']) ?>
            <button type="button" class="close-alert" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- ===== APPLICATIONS TABLE ===== -->
    <?php if (!empty($applications)): ?>
        <div class="ijp-applications-card">
            <div class="card-header">
                <h6>
                    <i class="fas fa-list"></i> All Applications
                </h6>
                <div class="ijp-filter-group">
                    <button class="ijp-filter-btn active" onclick="filterApplications('all')">All</button>
                    <button class="ijp-filter-btn" onclick="filterApplications('pending')">Pending</button>
                    <button class="ijp-filter-btn" onclick="filterApplications('shortlisted')">Shortlisted</button>
                    <button class="ijp-filter-btn" onclick="filterApplications('interviewed')">Interviewed</button>
                    <button class="ijp-filter-btn" onclick="filterApplications('selected')">Selected</button>
                    <button class="ijp-filter-btn" onclick="filterApplications('rejected')">Rejected</button>
                    <button class="ijp-filter-btn" onclick="filterApplications('on_hold')">On Hold</button>
                </div>
            </div>
            <div class="card-body">
                <table class="ijp-table" id="applicationsTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Applicant</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Applied On</th>
                            <th>Status</th>
                            <th>Interview</th>
                            <th style="text-align: center; min-width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($applications as $app): ?>
                            <tr data-status="<?= $app['status'] ?? 'pending' ?>" id="app-row-<?= $app['id'] ?>">
                                <td><?= $counter++ ?></td>
                                <td>
                                    <div class="ijp-applicant-info">
                                        <span class="name"><?= htmlspecialchars($app['full_name']) ?></span>
                                        <span class="email">
                                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($app['email'] ?? 'N/A') ?>
                                        </span>
                                    </div>
                                </td>
                                <td><span class="ijp-role-badge"><?= ucfirst($app['role'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($app['department'] ?? 'N/A') ?></td>
                                <td class="ijp-date-cell">
                                    <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($app['created_at'])) ?>
                                </td>
                                <td>
                                    <span class="ijp-status-badge <?= $app['status'] ?? 'pending' ?>">
                                        <i class="fas fa-circle" style="font-size: 6px;"></i>
                                        <?= ucfirst($app['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $interview = getInterviewDetails($pdo, $app['id']);
                                    if ($interview): 
                                    ?>
                                        <div class="ijp-interview-info">
                                            <span class="badge-sm primary"><?= date('M d', strtotime($interview['interview_date'])) ?></span>
                                            <span class="badge-sm info"><?= ucfirst($interview['interview_type']) ?></span>
                                            <?php if ($interview['status'] === 'completed'): ?>
                                                <span class="badge-sm success">Completed</span>
                                                <?php if ($interview['rating'] > 0): ?>
                                                    <span class="badge-sm warning">⭐ <?= $interview['rating'] ?>/5</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge-sm warning">Scheduled</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="ijp-no-interview">Not scheduled</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div class="ijp-action-group">
                                        <!-- <a href="application_details.php?id=<?= $app['id'] ?>" class="ijp-btn-action view" title="View Application">
                                            <i class="fas fa-eye"></i>
                                        </a> -->
                                        
                                        <?php if (($app['status'] ?? 'pending') === 'pending'): ?>
                                            <button class="ijp-btn-action success" onclick="updateApplicationStatus(<?= $app['id'] ?>, 'shortlisted', '<?= htmlspecialchars($app['full_name']) ?>')" title="Shortlist">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="ijp-btn-action danger" onclick="updateApplicationStatus(<?= $app['id'] ?>, 'rejected', '<?= htmlspecialchars($app['full_name']) ?>')" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (($app['status'] ?? '') === 'shortlisted'): ?>
                                            <button class="ijp-btn-action primary" onclick="openScheduleInterview(<?= $app['id'] ?>, '<?= htmlspecialchars($app['full_name']) ?>')" title="Schedule Interview">
                                                <i class="fas fa-calendar-plus"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (($app['status'] ?? '') === 'interviewed'): ?>
                                            <button class="ijp-btn-action success" onclick="openFeedbackModal(<?= $app['id'] ?>, 'selected', '<?= htmlspecialchars($app['full_name']) ?>')" title="Select Candidate">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                            <button class="ijp-btn-action danger" onclick="openFeedbackModal(<?= $app['id'] ?>, 'rejected', '<?= htmlspecialchars($app['full_name']) ?>')" title="Reject Candidate">
                                                <i class="fas fa-times-circle"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if (in_array($app['status'], ['interviewed', 'selected', 'rejected'])): 
                                            $interview = getInterviewDetails($pdo, $app['id']);
                                            if ($interview): ?>
                                                <button class="ijp-btn-action info" onclick="showInterviewDetails(<?= $app['id'] ?>)" title="View Interview Info">
                                                    <i class="fas fa-calendar-check"></i>
                                                </button>
                                            <?php endif; 
                                        endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                Showing <strong><?= count($applications) ?></strong> application(s)
            </div>
        </div>
    <?php else: ?>
        <!-- ===== EMPTY STATE ===== -->
        <div class="ijp-empty-state">
            <div class="empty-icon">
                <i class="fas fa-users"></i>
            </div>
            <h4>No Applications</h4>
            <p>No applications received for this position yet.</p>
            <a href="jobs.php" class="btn-primary">
                <i class="fas fa-search me-1"></i> Browse Jobs
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================
   MODALS
   ============================================ -->

<!-- Schedule Interview Modal -->
<div class="modal fade" id="interviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header-success">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-plus"></i> Schedule Interview
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="interviewForm" method="POST" action="schedule_interview.php">
                    <input type="hidden" name="application_id" id="interview_application_id">
                    <input type="hidden" name="job_id" value="<?= $job_id ?>">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Scheduling interview for: <strong id="interview_candidate_name"></strong>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="interview_date" class="form-label">
                                <i class="fas fa-calendar-day"></i> Interview Date <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local" name="interview_date" id="interview_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="interview_type" class="form-label">
                                <i class="fas fa-video"></i> Interview Type <span class="text-danger">*</span>
                            </label>
                            <select name="interview_type" id="interview_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="online">Online</option>
                                <option value="in_person">In-Person</option>
                                <option value="phone">Phone Call</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="interview_location" class="form-label">
                                <i class="fas fa-map-marker-alt"></i> Location / Meeting Link
                            </label>
                            <input type="text" name="interview_location" id="interview_location" 
                                   class="form-control" placeholder="e.g., Zoom link or Office address">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="interviewer_id" class="form-label">
                                <i class="fas fa-user-tie"></i> Interviewer
                            </label>
                            <select name="interviewer_id" id="interviewer_id" class="form-select">
                                <option value="">Select Interviewer</option>
                                <?php
                                $stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE role IN ('process_head', 'centre_head', 'hr', 'manager') ORDER BY full_name");
                                $stmt->execute();
                                $interviewers = $stmt->fetchAll();
                                foreach ($interviewers as $interviewer):
                                ?>
                                    <option value="<?= $interviewer['id'] ?>">
                                        <?= htmlspecialchars($interviewer['full_name']) ?> (<?= ucfirst($interviewer['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="interview_notes" class="form-label">
                            <i class="fas fa-sticky-note"></i> Additional Notes
                        </label>
                        <textarea name="interview_notes" id="interview_notes" class="form-control" rows="3" 
                                  placeholder="Any special instructions or notes for the interview..."></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success-modal" id="scheduleSubmitBtn">
                            <i class="fas fa-paper-plane"></i> Schedule Interview
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header-gradient">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Update Application Status
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="statusForm" method="POST" action="update_application_status.php">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="status_application_id">
                    <input type="hidden" name="status" id="status_value">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        You are about to update status for <strong id="status_candidate_name"></strong> to 
                        <strong id="status_display"></strong>.
                    </div>
                    
                    <div class="mb-3">
                        <label for="status_remarks" class="form-label">
                            <i class="fas fa-comment"></i> Remarks / Reason <span class="text-danger">*</span>
                        </label>
                        <textarea name="status_remarks" id="status_remarks" class="form-control" rows="3" 
                                  placeholder="Enter remarks or reason for this status change..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-modal" id="statusSubmitBtn">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header-gradient">
                <h5 class="modal-title">
                    <i class="fas fa-comment"></i> Interview Feedback
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="feedbackForm" method="POST" action="provide_interview_feedback.php">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="feedback_application_id">
                    <input type="hidden" name="decision" id="feedback_decision">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        You are about to <strong id="feedback_action_text"></strong> 
                        <strong id="feedback_candidate_name"></strong> for this position.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-star text-warning"></i> Rating <span class="text-danger">*</span>
                        </label>
                        <div class="rating-input">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="rating" id="rating_<?= $i ?>" value="<?= $i ?>" required>
                                    <label class="form-check-label" for="rating_<?= $i ?>"><?= $i ?> ⭐</label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="feedback_text" class="form-label">
                            <i class="fas fa-comment"></i> Detailed Feedback <span class="text-danger">*</span>
                        </label>
                        <textarea name="feedback" id="feedback_text" class="form-control" rows="4" 
                                  placeholder="Provide detailed feedback about the candidate's interview performance..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-modal" id="feedbackSubmitBtn">
                        <i class="fas fa-save"></i> Submit Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Interview Details Modal -->
<div class="modal fade" id="interviewDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header-info">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-check"></i> Interview Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="interviewDetailsContent">
                <div id="interviewDetailsLoading" class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">Loading interview details...</p>
                </div>
                <div id="interviewDetailsData" style="display:none;">
                    <dl class="row">
                        <dt class="col-sm-4">Date & Time</dt>
                        <dd class="col-sm-8" id="detail_date"></dd>
                        <dt class="col-sm-4">Type</dt>
                        <dd class="col-sm-8" id="detail_type"></dd>
                        <dt class="col-sm-4">Location</dt>
                        <dd class="col-sm-8" id="detail_location"></dd>
                        <dt class="col-sm-4">Interviewer</dt>
                        <dd class="col-sm-8" id="detail_interviewer"></dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8" id="detail_status"></dd>
                        <dt class="col-sm-4">Feedback</dt>
                        <dd class="col-sm-8" id="detail_feedback"></dd>
                        <dt class="col-sm-4">Rating</dt>
                        <dd class="col-sm-8" id="detail_rating"></dd>
                    </dl>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// FILTER APPLICATIONS
// ============================================
function filterApplications(status) {
    const rows = document.querySelectorAll('#applicationsTable tbody tr');
    rows.forEach(row => {
        if (status === 'all') {
            row.style.display = '';
        } else {
            row.style.display = row.dataset.status === status ? '' : 'none';
        }
    });
    
    // Update active button
    document.querySelectorAll('.ijp-filter-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.toLowerCase() === status || (status === 'all' && btn.textContent === 'All')) {
            btn.classList.add('active');
        }
    });
}

// ============================================
// UPDATE APPLICATION STATUS
// ============================================
function updateApplicationStatus(id, status, name) {
    document.getElementById('status_application_id').value = id;
    document.getElementById('status_value').value = status;
    document.getElementById('status_candidate_name').textContent = name;
    document.getElementById('status_display').textContent = status.toUpperCase();
    
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

// ============================================
// SCHEDULE INTERVIEW
// ============================================
function openScheduleInterview(id, name) {
    document.getElementById('interview_application_id').value = id;
    document.getElementById('interview_candidate_name').textContent = name;
    
    // Set default date to tomorrow 10:00 AM
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(10, 0, 0, 0);
    document.getElementById('interview_date').value = tomorrow.toISOString().slice(0, 16);
    
    // Reset form
    document.getElementById('interview_type').value = '';
    document.getElementById('interview_location').value = '';
    document.getElementById('interview_notes').value = '';
    document.getElementById('interviewer_id').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('interviewModal'));
    modal.show();
}

// ============================================
// OPEN FEEDBACK MODAL
// ============================================
function openFeedbackModal(id, decision, name) {
    document.getElementById('feedback_application_id').value = id;
    document.getElementById('feedback_decision').value = decision;
    document.getElementById('feedback_candidate_name').textContent = name;
    document.getElementById('feedback_action_text').textContent = decision === 'selected' ? 'Select' : 'Reject';
    
    // Reset form
    document.getElementById('feedbackForm').reset();
    
    const modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
    modal.show();
}

// ============================================
// SHOW INTERVIEW DETAILS
// ============================================
function showInterviewDetails(applicationId) {
    const modal = new bootstrap.Modal(document.getElementById('interviewDetailsModal'));
    modal.show();
    
    // Show loading
    document.getElementById('interviewDetailsLoading').style.display = 'block';
    document.getElementById('interviewDetailsData').style.display = 'none';
    
    fetch('get_interview_details.php?application_id=' + applicationId)
        .then(res => res.json())
        .then(data => {
            document.getElementById('interviewDetailsLoading').style.display = 'none';
            
            if (data.success && data.interview) {
                const i = data.interview;
                document.getElementById('detail_date').textContent = i.interview_date || 'N/A';
                document.getElementById('detail_type').textContent = i.interview_type || 'N/A';
                document.getElementById('detail_location').textContent = i.interview_location || 'N/A';
                document.getElementById('detail_interviewer').textContent = i.interviewer_name || 'Not assigned';
                document.getElementById('detail_status').textContent = i.status || 'Scheduled';
                document.getElementById('detail_feedback').textContent = i.feedback || 'No feedback yet';
                document.getElementById('detail_rating').textContent = (i.rating && i.rating > 0) ? i.rating + ' / 5' : 'Not rated';
                document.getElementById('interviewDetailsData').style.display = 'block';
            } else {
                document.getElementById('interviewDetailsData').style.display = 'block';
                document.getElementById('detail_date').textContent = 'No interview found';
                document.getElementById('detail_type').textContent = '-';
                document.getElementById('detail_location').textContent = '-';
                document.getElementById('detail_interviewer').textContent = '-';
                document.getElementById('detail_status').textContent = '-';
                document.getElementById('detail_feedback').textContent = '-';
                document.getElementById('detail_rating').textContent = '-';
            }
        })
        .catch(() => {
            document.getElementById('interviewDetailsLoading').style.display = 'none';
            document.getElementById('interviewDetailsData').style.display = 'block';
            document.getElementById('detail_date').textContent = 'Error loading data';
        });
}

// ============================================
// FORM SUBMISSIONS WITH AJAX
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Status Form
    document.getElementById('statusForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('statusSubmitBtn');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
        btn.disabled = true;
        
        fetch('update_application_status.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
                location.reload();
            } else {
                alert('Error: ' + data.message);
                btn.innerHTML = '<i class="fas fa-save"></i> Update Status';
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Connection error. Please try again.');
            btn.innerHTML = '<i class="fas fa-save"></i> Update Status';
            btn.disabled = false;
        });
    });
    
    // Interview Form
    document.getElementById('interviewForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('scheduleSubmitBtn');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Scheduling...';
        btn.disabled = true;
        
        fetch('schedule_interview.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('interviewModal')).hide();
                location.reload();
            } else {
                alert('Error: ' + data.message);
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Schedule Interview';
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Connection error. Please try again.');
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Schedule Interview';
            btn.disabled = false;
        });
    });
    
    // Feedback Form
    document.getElementById('feedbackForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('feedbackSubmitBtn');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
        btn.disabled = true;
        
        fetch('provide_interview_feedback.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('feedbackModal')).hide();
                location.reload();
            } else {
                alert('Error: ' + data.message);
                btn.innerHTML = '<i class="fas fa-save"></i> Submit Feedback';
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Connection error. Please try again.');
            btn.innerHTML = '<i class="fas fa-save"></i> Submit Feedback';
            btn.disabled = false;
        });
    });
});

// Close alerts
document.querySelectorAll('.ijp-alert .close-alert').forEach(btn => {
    btn.addEventListener('click', function() {
        this.parentElement.remove();
    });
});
</script>