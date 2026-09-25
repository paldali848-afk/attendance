<?php
// ============================================
// IJP - BROWSE ALL JOBS
// ============================================

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    redirect('../../../login.php');
}

$user = getCurrentUser();
$user_id = $user['id'];

// Get filters
$filters = [];
if (isset($_GET['department'])) {
    $filters['department'] = $_GET['department'];
}
if (isset($_GET['process'])) {
    $filters['process'] = $_GET['process'];
}
if (isset($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

$jobs = getPublishedJobs($pdo, $filters);
$departments = getDepartments($pdo);
$processes = getProcesses($pdo);

// Include header
include __DIR__ . '/../../header.php';

?>

<style>
    /* ============================================
       IJP JOBS PAGE - GRID STYLES
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
    .anim-delay-5 { animation-delay: 0.25s; }

    /* ===== PAGE HEADER ===== */
    .page-header {
        background: var(--white);
        border-radius: var(--radius);
        padding: 20px 28px;
        margin-bottom: 20px;
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
    }

    /* ===== FILTER SECTION - COMPACT ONE LINE ===== */
    .filter-section {
        background: var(--white);
        border-radius: var(--radius);
        padding: 14px 20px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .filter-section:hover {
        box-shadow: var(--shadow-md);
    }

    .filter-section .filter-label {
        font-size: 12px;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .filter-section .filter-label i {
        color: var(--blue);
    }

    .filter-section .filter-group {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        flex-wrap: wrap;
    }

    .filter-section .filter-group .form-control,
    .filter-section .filter-group .form-select {
        padding: 8px 14px;
        border: 2px solid var(--border-light);
        border-radius: var(--radius-sm);
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        font-weight: 500;
        color: var(--text-primary);
        background: var(--white);
        transition: all 0.3s ease;
        min-width: 140px;
        height: 40px;
    }

    .filter-section .filter-group .form-control:focus,
    .filter-section .filter-group .form-select:focus {
        outline: none;
        border-color: var(--blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .filter-section .filter-group .form-control::placeholder {
        color: var(--text-muted);
        font-weight: 400;
    }

    .filter-section .filter-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }

    .filter-section .btn-filter {
        background: linear-gradient(135deg, var(--blue), var(--purple));
        color: #fff;
        border: none;
        padding: 8px 20px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
        letter-spacing: 0.3px;
        height: 40px;
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .filter-section .btn-filter:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
    }

    .filter-section .btn-clear-filter {
        background: var(--bg-light);
        color: var(--text-secondary);
        border: 1px solid var(--border-light);
        padding: 8px 16px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.3s ease;
        height: 40px;
        display: flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
        text-decoration: none;
    }

    .filter-section .btn-clear-filter:hover {
        background: var(--red-bg);
        border-color: var(--red);
        color: var(--red);
        transform: translateY(-2px);
    }

    .filter-section .filter-divider {
        width: 1px;
        height: 30px;
        background: var(--border-light);
        flex-shrink: 0;
    }

    /* ===== JOB CARDS - GRID LAYOUT ===== */
    .jobs-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }

    .job-card {
        background: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
        height: 100%;
        min-height: 300px;
    }

    .job-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--shadow-md);
        border-color: var(--blue);
    }

    .job-card .card-body {
        padding: 20px 22px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .job-card .job-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
        gap: 8px;
    }

    .job-card .job-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        line-height: 1.3;
        flex: 1;
    }

    .job-card .job-title a {
        color: var(--text-primary);
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .job-card .job-title a:hover {
        color: var(--blue);
    }

    .job-card .job-meta {
        font-size: 12px;
        color: var(--text-muted);
        margin-bottom: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .job-card .job-meta i {
        color: var(--blue);
        width: 14px;
    }

    .job-card .job-description {
        font-size: 13px;
        color: var(--text-secondary);
        line-height: 1.6;
        flex: 1;
        margin-bottom: 12px;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .job-card .job-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-bottom: 12px;
    }

    .job-card .job-tags .tag {
        font-size: 10px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 50px;
        background: var(--bg-light);
        color: var(--text-secondary);
        border: 1px solid var(--border-light);
        letter-spacing: 0.3px;
    }

    .job-card .job-tags .tag.primary {
        background: var(--blue-bg);
        color: var(--blue);
        border-color: rgba(37, 99, 235, 0.2);
    }

    .job-card .job-tags .tag.success {
        background: var(--green-bg);
        color: var(--green);
        border-color: rgba(22, 163, 74, 0.2);
    }

    .job-card .job-tags .tag.warning {
        background: var(--yellow-bg);
        color: var(--yellow);
        border-color: rgba(217, 119, 6, 0.2);
    }

    .job-card .job-tags .tag.info {
        background: var(--cyan-bg);
        color: var(--cyan);
        border-color: rgba(8, 145, 178, 0.2);
    }

    .job-card .job-tags .tag.danger {
        background: var(--red-bg);
        color: var(--red);
        border-color: rgba(220, 38, 38, 0.2);
    }

    .job-card .job-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 22px;
        background: var(--bg-light);
        border-top: 1px solid var(--border-light);
        margin-top: auto;
    }

    .job-card .job-footer .job-date {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
    }

    .job-card .job-footer .job-date i {
        margin-right: 4px;
        color: var(--blue);
    }

    .job-card .btn-view {
        background: var(--blue-bg);
        color: var(--blue);
        border: 1px solid rgba(37, 99, 235, 0.15);
        padding: 5px 16px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 700;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }

    .job-card .btn-view:hover {
        background: var(--blue);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    }

    .job-card .btn-applied {
        background: var(--green-bg);
        color: var(--green);
        border: 1px solid rgba(22, 163, 74, 0.2);
        padding: 5px 16px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        cursor: default;
        white-space: nowrap;
    }

    .job-card .badge-expired {
        background: var(--red-bg);
        color: var(--red);
        padding: 3px 10px;
        border-radius: 50px;
        font-size: 9px;
        font-weight: 700;
        border: 1px solid rgba(220, 38, 38, 0.2);
        white-space: nowrap;
    }

    /* ===== EMPTY STATE ===== */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: var(--white);
        border-radius: var(--radius);
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-sm);
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
        margin-bottom: 16px;
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

    /* ===== RESULTS COUNT ===== */
    .results-count {
        text-align: center;
        padding: 14px 0;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-muted);
        background: var(--white);
        border-radius: var(--radius);
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-sm);
    }

    .results-count strong {
        color: var(--text-primary);
    }

    /* ============================================
       RESPONSIVE GRID
       ============================================ */

    /* Large screens - 3 columns */
    @media (min-width: 1200px) {
        .jobs-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    /* Medium screens - 2 columns */
    @media (min-width: 768px) and (max-width: 1199px) {
        .jobs-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    /* Small screens - 1 column */
    @media (max-width: 767px) {
        .jobs-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .job-card {
            min-height: auto;
        }

        .job-card .job-title {
            font-size: 15px;
        }
    }

    /* ===== RESPONSIVE FILTER ===== */
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
        .filter-section {
            flex-direction: column;
            align-items: stretch;
            padding: 16px;
            gap: 10px;
        }

        .filter-section .filter-label {
            width: 100%;
        }

        .filter-section .filter-group {
            flex-direction: column;
            width: 100%;
        }

        .filter-section .filter-group .form-control,
        .filter-section .filter-group .form-select {
            width: 100%;
            min-width: auto;
        }

        .filter-section .filter-actions {
            width: 100%;
            justify-content: stretch;
        }

        .filter-section .btn-filter,
        .filter-section .btn-clear-filter {
            flex: 1;
            justify-content: center;
        }

        .filter-section .filter-divider {
            display: none;
        }

        .page-header h1 {
            font-size: 18px;
        }

        .page-header .header-icon {
            width: 44px;
            height: 44px;
            font-size: 18px;
        }

        .job-card .card-body {
            padding: 16px 18px;
        }

        .job-card .job-footer {
            padding: 10px 18px;
            flex-direction: column;
            gap: 8px;
            align-items: stretch;
        }

        .job-card .job-footer .job-date {
            text-align: center;
        }

        .job-card .btn-view,
        .job-card .btn-applied {
            text-align: center;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .page-header {
            padding: 14px 14px;
        }

        .page-header h1 {
            font-size: 16px;
        }

        .filter-section {
            padding: 12px 14px;
        }

        .filter-section .filter-group .form-control,
        .filter-section .filter-group .form-select {
            font-size: 13px;
            padding: 7px 12px;
            height: 36px;
        }

        .filter-section .btn-filter,
        .filter-section .btn-clear-filter {
            height: 36px;
            font-size: 11px;
            padding: 6px 14px;
        }

        .job-card .job-meta {
            font-size: 11px;
            gap: 6px;
        }

        .job-card .job-description {
            font-size: 12px;
        }

        .job-card .job-tags .tag {
            font-size: 9px;
            padding: 2px 8px;
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
                <h1>Browse Jobs</h1>
                <div class="sub-text">
                    <i class="fas fa-map-pin"></i> Find your next internal opportunity
                </div>
            </div>
        </div>
        <div class="header-right">
            <span class="job-count-badge">
                <i class="fas fa-list me-1"></i> <?= count($jobs) ?> Jobs
            </span>
        </div>
    </div>

    <!-- ===== FILTER SECTION - COMPACT ONE LINE ===== -->
    <div class="filter-section anim-fade-up anim-delay-2">
        <div class="filter-label">
            <i class="fas fa-sliders-h"></i> Filters
        </div>
        
        <form method="GET" action="" class="filter-group">
            <input type="text" name="search" class="form-control" placeholder="🔍 Search jobs..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            
            <select name="department" class="form-select">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept) ?>" <?= ($_GET['department'] ?? '') == $dept ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dept) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="process" class="form-select">
                <option value="">All Processes</option>
                <?php foreach ($processes as $proc): ?>
                    <option value="<?= htmlspecialchars($proc) ?>" <?= ($_GET['process'] ?? '') == $proc ? 'selected' : '' ?>>
                        <?= htmlspecialchars($proc) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <div class="filter-actions">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if (!empty($_GET['search']) || !empty($_GET['department']) || !empty($_GET['process'])): ?>
                    <a href="jobs.php" class="btn-clear-filter">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ===== JOB LISTINGS - GRID ===== -->
    <?php if (!empty($jobs)): ?>
        <div class="jobs-grid">
            <?php foreach ($jobs as $index => $job): 
                $delay = ($index % 5) + 1;
            ?>
                <div class="job-card anim-fade-up anim-delay-<?= $delay ?>">
                    <div class="card-body">
                        <div class="job-header">
                            <h5 class="job-title">
                                <a href="job_details.php?id=<?= $job['id'] ?>">
                                    <?= htmlspecialchars($job['title']) ?>
                                </a>
                            </h5>
                            <?php if ($job['closing_date'] && strtotime($job['closing_date']) < time()): ?>
                                <span class="badge-expired">
                                    <i class="fas fa-exclamation-circle me-1"></i> Expired
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="job-meta">
                            <span><i class="fas fa-building"></i> <?= htmlspecialchars($job['department']) ?></span>
                            <span><i class="fas fa-user-tag"></i> <?= htmlspecialchars($job['position']) ?></span>
                        </div>
                        
                        <div class="job-description">
                            <?= substr(htmlspecialchars(strip_tags($job['description'])), 0, 120) ?>...
                        </div>
                        
                        <div class="job-tags">
                            <span class="tag primary">
                                <?= ucfirst(str_replace('_', ' ', $job['employment_type'])) ?>
                            </span>
                            <?php if ($job['location']): ?>
                                <span class="tag info">
                                    <i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($job['location']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($job['vacancies'] && $job['vacancies'] > 1): ?>
                                <span class="tag warning">
                                    <i class="fas fa-users me-1"></i> <?= $job['vacancies'] ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($job['experience_required']): ?>
                                <span class="tag">
                                    <i class="fas fa-clock me-1"></i> <?= htmlspecialchars($job['experience_required']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="job-footer">
                        <span class="job-date">
                            <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($job['created_at'])) ?>
                        </span>
                        
                        <?php if (hasApplied($pdo, $job['id'], $user_id)): ?>
                            <span class="btn-applied">
                                <i class="fas fa-check-circle"></i> Applied
                            </span>
                        <?php else: ?>
                            <a href="job_details.php?id=<?= $job['id'] ?>" class="btn-view">
                                <i class="fas fa-eye"></i> View
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="results-count">
            Showing <strong><?= count($jobs) ?></strong> job(s)
        </div>
    <?php else: ?>
        <!-- ===== EMPTY STATE ===== -->
        <div class="empty-state anim-fade-up anim-delay-3">
            <div class="empty-icon">
                <i class="fas fa-briefcase"></i>
            </div>
            <h4>No jobs found</h4>
            <p>No jobs match your search criteria. Try adjusting your filters.</p>
            <a href="jobs.php" class="btn-primary">
                <i class="fas fa-times me-1"></i> Clear Filters
            </a>
        </div>
    <?php endif; ?>
</div>