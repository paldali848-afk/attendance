<?php
// ============================================
// IJP - JOB DETAILS & APPLICATION
// ============================================

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    redirect('../../../login.php');
}

$user = getCurrentUser();
$user_id = $user['id'];
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($job_id <= 0) {
    redirect('jobs.php');
}

$job = getJobDetails($pdo, $job_id);
if (!$job) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Job not found or expired.'];
    redirect('jobs.php');
}

$already_applied = hasApplied($pdo, $job_id, $user_id);

include __DIR__ . '/../../header.php';
?>

<style>
    /* ============================================
       IJP JOB DETAILS - DASHBOARD STYLES
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

    .status-badge {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.3px;
        height: 40px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-badge.open {
        background: var(--green-bg);
        color: var(--green);
        border: 1px solid rgba(22, 163, 74, 0.2);
    }

    .status-badge.closed {
        background: var(--red-bg);
        color: var(--red);
        border: 1px solid rgba(220, 38, 38, 0.2);
    }

    .status-badge.expired {
        background: var(--yellow-bg);
        color: var(--yellow);
        border: 1px solid rgba(217, 119, 6, 0.2);
    }

    /* ===== MAIN CONTENT ===== */
    .job-detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
    }

    /* ===== JOB DETAIL CARD ===== */
    .detail-card {
        background: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
    }

    .detail-card:hover {
        box-shadow: var(--shadow-md);
    }

    .detail-card .card-header {
        padding: 20px 24px;
        background: var(--bg-light);
        border-bottom: 2px solid var(--border-light);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 10px;
    }

    .detail-card .card-header .job-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .detail-card .card-header .job-subtitle {
        font-size: 14px;
        color: var(--text-muted);
        margin-top: 4px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .detail-card .card-header .job-subtitle i {
        color: var(--blue);
        width: 16px;
    }

    .detail-card .card-body {
        padding: 24px;
    }

    .detail-card .card-footer {
        padding: 14px 24px;
        background: var(--bg-light);
        border-top: 1px solid var(--border-light);
        font-size: 13px;
        color: var(--text-muted);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    /* ===== INFO ITEMS ===== */
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 20px;
    }

    .info-item {
        padding: 10px 14px;
        background: var(--bg-light);
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-light);
        transition: all 0.3s ease;
    }

    .info-item:hover {
        background: var(--blue-bg);
        border-color: var(--blue);
    }

    .info-item .info-label {
        font-size: 11px;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: block;
        margin-bottom: 2px;
    }

    .info-item .info-value {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .info-item .info-value i {
        width: 18px;
        color: var(--blue);
    }

    /* ===== SECTION TITLES ===== */
    .section-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
        margin-top: 24px;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--border-light);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .section-title i {
        color: var(--blue);
    }

    .section-title:first-of-type {
        margin-top: 0;
    }

    .section-content {
        font-size: 14px;
        color: var(--text-secondary);
        line-height: 1.7;
    }

    .section-content p {
        margin-bottom: 8px;
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
    }

    .alert-box.info {
        background: var(--blue-bg);
        color: var(--blue);
        border-color: var(--blue);
    }

    .alert-box.success {
        background: var(--green-bg);
        color: var(--green);
        border-color: var(--green);
    }

    .alert-box.warning {
        background: var(--yellow-bg);
        color: var(--yellow);
        border-color: var(--yellow);
    }

    .alert-box.danger {
        background: var(--red-bg);
        color: var(--red);
        border-color: var(--red);
    }

    .alert-box i {
        font-size: 18px;
    }

    /* ===== SIDEBAR ===== */
    .sidebar-card {
        background: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
    }

    .sidebar-card:hover {
        box-shadow: var(--shadow-md);
    }

    .sidebar-card .card-header {
        padding: 16px 20px;
        background: linear-gradient(135deg, var(--blue), var(--purple));
        border-bottom: none;
    }

    .sidebar-card .card-header h5 {
        color: #fff;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 15px;
    }

    .sidebar-card .card-body {
        padding: 20px;
    }

    .sidebar-card .card-body .form-label {
        font-size: 13px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .sidebar-card .card-body .form-control {
        padding: 10px 14px;
        border: 2px solid var(--border-light);
        border-radius: var(--radius-sm);
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        font-weight: 500;
        color: var(--text-primary);
        background: var(--white);
        transition: all 0.3s ease;
    }

    .sidebar-card .card-body .form-control:focus {
        outline: none;
        border-color: var(--blue);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .sidebar-card .card-body .form-control::placeholder {
        color: var(--text-muted);
        font-weight: 400;
    }

    .sidebar-card .btn-submit {
        background: linear-gradient(135deg, var(--green), #22c55e);
        color: #fff;
        border: none;
        padding: 10px 24px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(22, 163, 74, 0.2);
        letter-spacing: 0.3px;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .sidebar-card .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(22, 163, 74, 0.3);
    }

    .sidebar-card .btn-submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .sidebar-card .already-applied {
        text-align: center;
        padding: 10px 0;
    }

    .sidebar-card .already-applied .check-icon {
        font-size: 48px;
        color: var(--green);
        margin-bottom: 8px;
    }

    .sidebar-card .already-applied .message {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .sidebar-card .already-applied .sub-message {
        font-size: 12px;
        color: var(--text-muted);
    }

    .sidebar-card .btn-view-applications {
        background: var(--blue-bg);
        color: var(--blue);
        border: 1px solid rgba(37, 99, 235, 0.15);
        padding: 8px 20px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        width: 100%;
        justify-content: center;
    }

    .sidebar-card .btn-view-applications:hover {
        background: var(--blue);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    }

    /* ===== SIDEBAR STATS ===== */
    .stats-card {
        background: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
        margin-top: 20px;
    }

    .stats-card:hover {
        box-shadow: var(--shadow-md);
    }

    .stats-card .card-header {
        padding: 14px 20px;
        background: var(--bg-light);
        border-bottom: 1px solid var(--border-light);
    }

    .stats-card .card-header h6 {
        font-size: 13px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .stats-card .card-header h6 i {
        color: var(--blue);
    }

    .stats-card .card-body {
        padding: 16px 20px;
    }

    .stats-card .stat-item {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 13px;
        border-bottom: 1px solid var(--border-light);
    }

    .stats-card .stat-item:last-child {
        border-bottom: none;
    }

    .stats-card .stat-item .stat-label {
        color: var(--text-muted);
        font-weight: 500;
    }

    .stats-card .stat-item .stat-value {
        color: var(--text-primary);
        font-weight: 700;
    }

    /* ============================================
       RESPONSIVE
       ============================================ */

    @media (max-width: 992px) {
        .job-detail-grid {
            grid-template-columns: 1fr;
        }

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
        .info-grid {
            grid-template-columns: 1fr;
        }

        .detail-card .card-header {
            padding: 16px 18px;
        }

        .detail-card .card-header .job-title {
            font-size: 18px;
        }

        .detail-card .card-body {
            padding: 18px;
        }

        .detail-card .card-footer {
            flex-direction: column;
            text-align: center;
            gap: 6px;
        }

        .sidebar-card .card-body {
            padding: 16px;
        }

        .page-header h1 {
            font-size: 18px;
        }

        .page-header .header-icon {
            width: 44px;
            height: 44px;
            font-size: 18px;
        }

        .status-badge {
            height: 34px;
            font-size: 11px;
            padding: 4px 12px;
        }

        .back-btn {
            height: 34px;
            font-size: 11px;
            padding: 4px 12px;
        }
    }

    @media (max-width: 480px) {
        .page-header {
            padding: 14px 14px;
        }

        .page-header h1 {
            font-size: 16px;
        }

        .detail-card .card-header .job-title {
            font-size: 16px;
        }

        .detail-card .card-header .job-subtitle {
            font-size: 12px;
            gap: 8px;
        }

        .detail-card .card-body {
            padding: 14px;
        }

        .info-item {
            padding: 8px 12px;
        }

        .info-item .info-value {
            font-size: 13px;
        }

        .section-title {
            font-size: 14px;
        }

        .section-content {
            font-size: 13px;
        }

        .sidebar-card .card-body .form-control {
            font-size: 13px;
            padding: 8px 12px;
        }

        .sidebar-card .btn-submit {
            font-size: 13px;
            padding: 8px 16px;
        }
    }
</style>

<div class="container">
    <!-- ===== PAGE HEADER ===== -->
    <div class="page-header anim-fade-up anim-delay-1">
        <div class="header-left">
            <div class="header-icon">
                <i class="fas fa-briefcase"></i>
            </div>
            <div>
                <h1>Job Details</h1>
                <div class="sub-text">
                    <i class="fas fa-map-pin"></i> Review and apply for this position
                </div>
            </div>
        </div>
        <div class="header-right">
            <a href="jobs.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Jobs
            </a>
            <?php if ($job['status'] === 'open'): ?>
                <span class="status-badge open">
                    <i class="fas fa-circle"></i> Open
                </span>
            <?php elseif (strtotime($job['closing_date']) < time()): ?>
                <span class="status-badge expired">
                    <i class="fas fa-clock"></i> Expired
                </span>
            <?php else: ?>
                <span class="status-badge closed">
                    <i class="fas fa-lock"></i> Closed
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="job-detail-grid">
        <!-- ===== LEFT COLUMN - JOB DETAILS ===== -->
        <div class="detail-card anim-fade-up anim-delay-2">
            <div class="card-header">
                <div>
                    <h5 class="job-title"><?= htmlspecialchars($job['title']) ?></h5>
                    <div class="job-subtitle">
                        <span><i class="fas fa-building"></i> <?= htmlspecialchars($job['department']) ?></span>
                        <span><i class="fas fa-user-tag"></i> <?= htmlspecialchars($job['position']) ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($job['location'] ?? 'Remote') ?></span>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <!-- Status Alert -->
                <?php if ($job['status'] === 'closed'): ?>
                    <div class="alert-box danger">
                        <i class="fas fa-exclamation-circle"></i> This job posting is closed.
                    </div>
                <?php endif; ?>

                <!-- Info Grid -->
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Employment Type</span>
                        <span class="info-value">
                            <i class="fas fa-briefcase"></i> <?= ucwords(str_replace('_', ' ', $job['employment_type'])) ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Vacancies</span>
                        <span class="info-value">
                            <i class="fas fa-users"></i> <?= $job['vacancies'] ?? 1 ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Posted Date</span>
                        <span class="info-value">
                            <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($job['created_at'])) ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Process</span>
                        <span class="info-value">
                            <i class="fas fa-cogs"></i> <?= htmlspecialchars($job['process'] ?? 'N/A') ?>
                        </span>
                    </div>
                    <?php if ($job['experience_required']): ?>
                        <div class="info-item">
                            <span class="info-label">Experience Required</span>
                            <span class="info-value">
                                <i class="fas fa-star"></i> <?= htmlspecialchars($job['experience_required']) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ($job['education_required']): ?>
                        <div class="info-item">
                            <span class="info-label">Education Required</span>
                            <span class="info-value">
                                <i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($job['education_required']) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Closing Date Alert -->
                <?php if ($job['closing_date']): ?>
                    <div class="alert-box <?= strtotime($job['closing_date']) < time() ? 'danger' : 'info' ?>">
                        <i class="fas fa-clock"></i> 
                        Applications close on <strong><?= date('M d, Y', strtotime($job['closing_date'])) ?></strong>
                        <?php if (strtotime($job['closing_date']) < time()): ?>
                            <span style="background: var(--red-bg); color: var(--red); padding: 2px 10px; border-radius: 50px; font-size: 11px; margin-left: 8px;">Expired</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Job Description -->
                <h6 class="section-title">
                    <i class="fas fa-align-left"></i> Job Description
                </h6>
                <div class="section-content">
                    <?= nl2br(htmlspecialchars($job['description'])) ?>
                </div>

                <!-- Responsibilities -->
                <?php if ($job['responsibilities']): ?>
                    <h6 class="section-title">
                        <i class="fas fa-tasks"></i> Key Responsibilities
                    </h6>
                    <div class="section-content">
                        <?= nl2br(htmlspecialchars($job['responsibilities'])) ?>
                    </div>
                <?php endif; ?>

                <!-- Required Skills -->
                <?php if ($job['skills_required']): ?>
                    <h6 class="section-title">
                        <i class="fas fa-code"></i> Required Skills
                    </h6>
                    <div class="section-content">
                        <?= nl2br(htmlspecialchars($job['skills_required'])) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card-footer">
                <span>
                    <i class="fas fa-user me-1"></i> Posted by: <strong><?= htmlspecialchars($job['posted_by_name']) ?></strong>
                </span>
                <?php if ($job['updated_at'] && $job['updated_at'] != $job['created_at']): ?>
                    <span>
                        <i class="fas fa-edit me-1"></i> Updated: <?= date('M d, Y', strtotime($job['updated_at'])) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== RIGHT COLUMN - SIDEBAR ===== -->
        <div class="sidebar-card anim-fade-up anim-delay-3">
            <div class="card-header">
                <h5>
                    <i class="fas fa-paper-plane"></i> Apply Now
                </h5>
            </div>
            <div class="card-body">
                <?php if ($already_applied): ?>
                    <div class="already-applied">
                        <div class="check-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="message">You have already applied!</div>
                        <div class="sub-message">Your application is being reviewed.</div>
                        <hr>
                        <a href="my_applications.php" class="btn-view-applications">
                            <i class="fas fa-file-alt"></i> View My Applications
                        </a>
                    </div>
                <?php elseif ($job['status'] === 'closed' || (strtotime($job['closing_date']) < time())): ?>
                    <div class="alert-box danger" style="margin-bottom: 0;">
                        <i class="fas fa-exclamation-circle"></i> This position is no longer accepting applications.
                    </div>
                <?php else: ?>
                    <form action="apply.php" method="POST">
                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                        
                        <div class="mb-3">
                            <label for="cover_letter" class="form-label">
                                Cover Letter <span style="color: var(--red);">*</span>
                            </label>
                            <textarea name="cover_letter" id="cover_letter" 
                                      class="form-control" rows="5" 
                                      placeholder="Why are you interested in this position? What makes you a good fit?" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="expected_salary" class="form-label">Expected Salary</label>
                            <input type="text" name="expected_salary" id="expected_salary" 
                                   class="form-control" placeholder="e.g., ₹50,000 - ₹60,000">
                        </div>
                        
                        <div class="mb-3">
                            <label for="availability_date" class="form-label">Availability Date</label>
                            <input type="date" name="availability_date" id="availability_date" 
                                   class="form-control">
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Application
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== STATS CARD ===== -->
        <div class="stats-card anim-fade-up anim-delay-4" style="grid-column: 2;">
            <div class="card-header">
                <h6>
                    <i class="fas fa-chart-bar"></i> Position Details
                </h6>
            </div>
            <div class="card-body">
                <div class="stat-item">
                    <span class="stat-label">Job ID</span>
                    <span class="stat-value">#<?= str_pad($job['id'], 4, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Department</span>
                    <span class="stat-value"><?= htmlspecialchars($job['department']) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Position</span>
                    <span class="stat-value"><?= htmlspecialchars($job['position']) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Employment Type</span>
                    <span class="stat-value"><?= ucwords(str_replace('_', ' ', $job['employment_type'])) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Vacancies</span>
                    <span class="stat-value"><?= $job['vacancies'] ?? 1 ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Posted By</span>
                    <span class="stat-value"><?= htmlspecialchars($job['posted_by_name']) ?></span>
                </div>
                <?php if ($job['process']): ?>
                    <div class="stat-item">
                        <span class="stat-label">Process</span>
                        <span class="stat-value"><?= htmlspecialchars($job['process']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>