<?php
// ============================================
// IJP - CREATE NEW JOB POSTING (SIMPLIFIED)
// ============================================

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    redirect('../../../login.php');
}

$user = getCurrentUser();
$role = $user['role'];

// Only HR/ER departments can create jobs
$user_dept = strtolower($user['department'] ?? '');
if (!in_array($user_dept, ['hr', 'er', 'human resources', 'employee relations'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'You do not have permission to create jobs.'];
    redirect('index.php');
}

// Get processes for dropdown
$processes = getProcesses($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $process = $_POST['process'] ?? '';
    $designation = $_POST['designation'] ?? '';
    $vacancies = (int)($_POST['vacancies'] ?? 1);
    $hiring_criteria = $_POST['hiring_criteria'] ?? '';
    
    // Validate
    if (empty($process)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please select a process.'];
    } elseif (empty($designation)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please enter designation.'];
    } elseif (empty($hiring_criteria)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please enter hiring criteria.'];
    } else {
        // Get department from process
        $stmt = $pdo->prepare("SELECT department FROM users WHERE process = ? AND role = 'process_head' LIMIT 1");
        $stmt->execute([$process]);
        $dept_data = $stmt->fetch();
        $department = $dept_data ? $dept_data['department'] : 'General';
        
        // Create job posting
        $data = [
            'title' => $designation . ' - ' . $process,
            'department' => $department,
            'position' => $designation,
            'process' => $process,
            'description' => $hiring_criteria,
            'responsibilities' => $hiring_criteria,
            'vacancies' => $vacancies,
            'posted_by' => $user['id'],
            'status' => 'published',
            'employment_type' => 'full_time',
            'experience_required' => 'Not specified',
            'education_required' => 'Not specified',
            'skills_required' => $hiring_criteria,
            'closing_date' => date('Y-m-d', strtotime('+30 days'))
        ];
        
        if (createJobPosting($pdo, $data)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Job opening posted successfully!'];
            redirect('manage_jobs.php');
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to create job posting. Please try again.'];
        }
    }
}

include __DIR__ . '/../../header.php';
?>

<style>
    /* ============================================
       IJP CREATE JOB - DASHBOARD STYLES
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
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 16px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.3px;
        height: 40px;
    }

    .status-badge.draft {
        background: var(--yellow-bg);
        color: var(--yellow);
        border: 1px solid rgba(217, 119, 6, 0.2);
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

    /* ===== FORM CARD ===== */
    .form-card {
        background: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
    }

    .form-card:hover {
        box-shadow: var(--shadow-md);
    }

    .form-card .card-header {
        padding: 18px 24px;
        background: linear-gradient(135deg, var(--blue), var(--purple));
        border-bottom: none;
    }

    .form-card .card-header h4 {
        color: #fff;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
    }

    .form-card .card-header h4 i {
        font-size: 20px;
    }

    .form-card .card-body {
        padding: 28px 32px;
    }

    /* ===== FORM ELEMENTS ===== */
    .form-group {
        margin-bottom: 24px;
    }

    .form-group .form-label {
        font-size: 14px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .form-group .form-label i {
        color: var(--blue);
        width: 18px;
    }

    .form-group .form-label .required {
        color: var(--red);
        font-size: 16px;
    }

    .form-group .form-control,
    .form-group .form-select {
        padding: 12px 16px;
        border: 2px solid var(--border-light);
        border-radius: var(--radius-sm);
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        font-weight: 500;
        color: var(--text-primary);
        background: var(--white);
        transition: all 0.3s ease;
        width: 100%;
    }

    .form-group .form-control:focus,
    .form-group .form-select:focus {
        outline: none;
        border-color: var(--blue);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .form-group .form-control::placeholder {
        color: var(--text-muted);
        font-weight: 400;
    }

    .form-group .form-control-lg {
        padding: 14px 18px;
        font-size: 16px;
    }

    .form-group .form-select-lg {
        padding: 14px 18px;
        font-size: 16px;
        height: auto;
    }

    .form-group .help-text {
        display: block;
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 4px;
        font-weight: 500;
    }

    .form-group .help-text i {
        color: var(--blue);
        margin-right: 4px;
    }

    /* ===== FORM ACTIONS ===== */
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 8px;
    }

    .btn-submit {
        background: linear-gradient(135deg, var(--green), #22c55e);
        color: #fff;
        border: none;
        padding: 14px 32px;
        border-radius: 50px;
        font-size: 15px;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(22, 163, 74, 0.2);
        letter-spacing: 0.3px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        justify-content: center;
        cursor: pointer;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(22, 163, 74, 0.3);
    }

    .btn-submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-cancel {
        background: var(--bg-light);
        color: var(--text-secondary);
        border: 2px solid var(--border-light);
        padding: 14px 32px;
        border-radius: 50px;
        font-size: 15px;
        font-weight: 700;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex: 1;
        justify-content: center;
    }

    .btn-cancel:hover {
        background: var(--border-light);
        transform: translateY(-2px);
    }

    /* ===== INFO CARD ===== */
    .info-card {
        background: var(--bg-light);
        border-radius: var(--radius);
        border: 1px solid var(--border-light);
        padding: 20px 24px;
        margin-top: 20px;
        animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
        animation-delay: 0.2s;
    }

    .info-card .info-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-card .info-title i {
        color: var(--blue);
    }

    .info-card .info-text {
        font-size: 13px;
        color: var(--text-secondary);
        line-height: 1.6;
        margin: 0;
    }

    .info-card .info-text .highlight {
        color: var(--blue);
        font-weight: 600;
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

        .status-badge {
            height: 34px;
            font-size: 11px;
            padding: 4px 12px;
        }

        .form-card .card-body {
            padding: 20px 18px;
        }

        .form-card .card-header {
            padding: 14px 18px;
        }

        .form-card .card-header h4 {
            font-size: 16px;
        }

        .form-group .form-label {
            font-size: 13px;
        }

        .form-group .form-control,
        .form-group .form-select {
            font-size: 13px;
            padding: 10px 14px;
        }

        .form-group .form-control-lg,
        .form-group .form-select-lg {
            font-size: 14px;
            padding: 12px 16px;
        }

        .form-actions {
            flex-direction: column;
        }

        .btn-submit,
        .btn-cancel {
            width: 100%;
            padding: 12px 24px;
            font-size: 14px;
        }

        .info-card {
            padding: 16px 18px;
        }
    }

    @media (max-width: 480px) {
        .page-header {
            padding: 14px 14px;
        }

        .page-header h1 {
            font-size: 16px;
        }

        .form-card .card-body {
            padding: 16px 14px;
        }

        .form-card .card-header h4 {
            font-size: 14px;
        }

        .form-group .form-label {
            font-size: 12px;
        }

        .form-group .form-control,
        .form-group .form-select {
            font-size: 12px;
            padding: 8px 12px;
        }

        .form-group .form-control-lg,
        .form-group .form-select-lg {
            font-size: 13px;
            padding: 10px 14px;
        }

        .btn-submit,
        .btn-cancel {
            font-size: 13px;
            padding: 10px 16px;
        }

        .info-card .info-text {
            font-size: 12px;
        }
    }
</style>

<div class="container">
    <!-- ===== PAGE HEADER ===== -->
    <div class="page-header anim-fade-up anim-delay-1">
        <div class="header-left">
            <div class="header-icon">
                <i class="fas fa-plus-circle"></i>
            </div>
            <div>
                <h1>Post New Opening</h1>
                <div class="sub-text">
                    <i class="fas fa-map-pin"></i> Create a new job posting for employees
                </div>
            </div>
        </div>
        <div class="header-right">
            <a href="manage_jobs.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Manage
            </a>
            <span class="status-badge draft">
                <i class="fas fa-circle" style="font-size: 6px;"></i> Draft
            </span>
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

    <!-- ===== FORM ===== -->
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="form-card anim-fade-up anim-delay-2">
                <div class="card-header">
                    <h4>
                        <i class="fas fa-briefcase"></i> Job Opening Details
                    </h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <!-- Process Name -->
                        <div class="form-group">
                            <label for="process" class="form-label">
                                <i class="fas fa-building"></i> Process Name 
                                <span class="required">*</span>
                            </label>
                            <select name="process" id="process" class="form-select form-select-lg" required>
                                <option value="">Select Process</option>
                                <?php foreach ($processes as $proc): ?>
                                    <option value="<?= htmlspecialchars($proc) ?>" <?= ($_POST['process'] ?? '') == $proc ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($proc) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="help-text">
                                <i class="fas fa-info-circle"></i> Select the process for this job opening
                            </span>
                        </div>

                        <!-- Designation -->
                        <div class="form-group">
                            <label for="designation" class="form-label">
                                <i class="fas fa-user-tag"></i> Designation 
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="designation" id="designation" class="form-control form-control-lg" 
                                   placeholder="e.g., Team Lead, Manager, Senior Developer" 
                                   value="<?= htmlspecialchars($_POST['designation'] ?? '') ?>" required>
                            <span class="help-text">
                                <i class="fas fa-info-circle"></i> Enter the job title/designation
                            </span>
                        </div>

                        <!-- Vacancies -->
                        <div class="form-group">
                            <label for="vacancies" class="form-label">
                                <i class="fas fa-users"></i> Vacancies 
                                <span class="required">*</span>
                            </label>
                            <input type="number" name="vacancies" id="vacancies" class="form-control form-control-lg" 
                                   placeholder="Number of openings" value="<?= $_POST['vacancies'] ?? 1 ?>" min="1" required>
                            <span class="help-text">
                                <i class="fas fa-info-circle"></i> Number of positions available
                            </span>
                        </div>

                        <!-- Hiring Criteria -->
                        <div class="form-group">
                            <label for="hiring_criteria" class="form-label">
                                <i class="fas fa-list-check"></i> Hiring Criteria 
                                <span class="required">*</span>
                            </label>
                            <textarea name="hiring_criteria" id="hiring_criteria" class="form-control form-control-lg" 
                                      rows="6" placeholder="Enter the hiring criteria, qualifications, experience required, skills needed..." required><?= htmlspecialchars($_POST['hiring_criteria'] ?? '') ?></textarea>
                            <span class="help-text">
                                <i class="fas fa-info-circle"></i> Describe the qualifications, experience, skills, and other requirements
                            </span>
                        </div>

                        <!-- Submit Button -->
                        <div class="form-actions">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-paper-plane"></i> Post Opening
                            </button>
                            <a href="manage_jobs.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ===== INFO CARD ===== -->
            <div class="info-card anim-fade-up anim-delay-3">
                <div class="info-title">
                    <i class="fas fa-info-circle"></i> Important Note
                </div>
                <p class="info-text">
                    This will create a new job posting that will be visible to all employees.
                    Only <span class="highlight">HR</span>, <span class="highlight">ER</span>, 
                    <span class="highlight">Centre Head</span>, and <span class="highlight">Process Head</span> 
                    can post new openings. Once posted, employees can view and apply for this position.
                </p>
            </div>
        </div>
    </div>
</div>