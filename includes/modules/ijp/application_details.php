<?php
// ============================================
// IJP - APPLICATION DETAILS
// ============================================

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    redirect('../../../login.php');
}

$user = getCurrentUser();
$user_id = $user['id'];
$role = $user['role'];
$user_dept = strtolower($user['department'] ?? '');

$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($application_id <= 0) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid application ID.'];
    redirect('my_applications.php');
}

// Get application details
$application = getApplicationDetails($pdo, $application_id);
if (!$application) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Application not found.'];
    redirect('my_applications.php');
}

// Check if user has permission to view this application
$is_hr = in_array($user_dept, ['hr', 'er', 'human resources', 'employee relations']);
$is_owner = ($application['user_id'] == $user_id);

if (!$is_hr && !$is_owner) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'You do not have permission to view this application.'];
    redirect('index.php');
}

// Get interview details
$interview = getInterviewDetails($pdo, $application_id);

// Get status history
$status_history = getApplicationStatusHistory($pdo, $application_id);

// Get job details
$job = getJobDetails($pdo, $application['job_id']);

// Include header
include $_SERVER['DOCUMENT_ROOT'] . '/attendance/includes/header.php';
?>

<div class="container mt-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
            <?php if ($is_hr): ?>
                <li class="breadcrumb-item"><a href="manage_jobs.php">Manage Jobs</a></li>
                <li class="breadcrumb-item"><a href="view_applications.php?job_id=<?= $application['job_id'] ?>">Applications</a></li>
            <?php else: ?>
                <li class="breadcrumb-item"><a href="my_applications.php">My Applications</a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active">Application Details</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">
                <i class="fas fa-file-alt text-primary"></i> Application Details
            </h1>
            <p class="text-muted mt-1">
                <?= htmlspecialchars($application['full_name']) ?> 
                | <?= htmlspecialchars($application['title']) ?>
            </p>
        </div>
        <div>
            <?php if ($is_hr): ?>
                <a href="view_applications.php?job_id=<?= $application['job_id'] ?>" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i> Back to Applications
                </a>
            <?php else: ?>
                <a href="my_applications.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i> My Applications
                </a>
            <?php endif; ?>
            <a href="job_details.php?id=<?= $application['job_id'] ?>" class="btn btn-info">
                <i class="fas fa-eye me-1"></i> View Job
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message']['type'] ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Main Content - Left Column -->
        <div class="col-md-8">
            <!-- Application Info -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle text-primary me-1"></i> Application Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-5 text-muted">Application ID</dt>
                                <dd class="col-sm-7">#<?= $application['id'] ?></dd>
                                
                                <dt class="col-sm-5 text-muted">Applicant</dt>
                                <dd class="col-sm-7">
                                    <strong><?= htmlspecialchars($application['full_name']) ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($application['email'] ?? 'N/A') ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-user-tag me-1"></i> <?= ucfirst($application['role'] ?? 'N/A') ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-building me-1"></i> <?= htmlspecialchars($application['department'] ?? 'N/A') ?>
                                    </small>
                                </dd>
                                
                                <dt class="col-sm-5 text-muted">Applied On</dt>
                                <dd class="col-sm-7"><?= date('M d, Y h:i A', strtotime($application['created_at'])) ?></dd>
                                
                                <dt class="col-sm-5 text-muted">Status</dt>
                                <dd class="col-sm-7">
                                    <span class="badge bg-<?= getStatusBadge($application['status']) ?>">
                                        <?= ucfirst($application['status'] ?? 'Pending') ?>
                                    </span>
                                </dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-5 text-muted">Job Title</dt>
                                <dd class="col-sm-7">
                                    <a href="job_details.php?id=<?= $application['job_id'] ?>">
                                        <?= htmlspecialchars($application['title']) ?>
                                    </a>
                                </dd>
                                
                                <dt class="col-sm-5 text-muted">Department</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($application['job_department'] ?? 'N/A') ?></dd>
                                
                                <dt class="col-sm-5 text-muted">Position</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($application['job_position'] ?? 'N/A') ?></dd>
                                
                                <dt class="col-sm-5 text-muted">Location</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($application['job_location'] ?? 'Remote') ?></dd>
                                
                                <?php if ($application['expected_salary']): ?>
                                    <dt class="col-sm-5 text-muted">Expected Salary</dt>
                                    <dd class="col-sm-7"><?= htmlspecialchars($application['expected_salary']) ?></dd>
                                <?php endif; ?>
                                
                                <?php if ($application['availability_date']): ?>
                                    <dt class="col-sm-5 text-muted">Availability Date</dt>
                                    <dd class="col-sm-7"><?= date('M d, Y', strtotime($application['availability_date'])) ?></dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cover Letter -->
            <?php if ($application['cover_letter']): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-envelope-open-text text-primary me-1"></i> Cover Letter
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="cover-letter-content p-3 bg-light rounded">
                            <?= nl2br(htmlspecialchars($application['cover_letter'])) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Interview Details -->
            <?php if ($interview): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-check text-success me-1"></i> Interview Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="row mb-0">
                                    <dt class="col-sm-4 text-muted">Date & Time</dt>
                                    <dd class="col-sm-8">
                                        <span class="badge bg-primary">
                                            <?= date('M d, Y h:i A', strtotime($interview['interview_date'])) ?>
                                        </span>
                                    </dd>
                                    
                                    <dt class="col-sm-4 text-muted">Type</dt>
                                    <dd class="col-sm-8">
                                        <span class="badge bg-info">
                                            <?= ucfirst($interview['interview_type'] ?? 'N/A') ?>
                                        </span>
                                    </dd>
                                    
                                    <dt class="col-sm-4 text-muted">Status</dt>
                                    <dd class="col-sm-8">
                                        <span class="badge bg-<?= getInterviewStatusBadge($interview['status']) ?>">
                                            <?= ucfirst($interview['status'] ?? 'Scheduled') ?>
                                        </span>
                                    </dd>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="row mb-0">
                                    <dt class="col-sm-4 text-muted">Location</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($interview['interview_location'] ?? 'N/A') ?></dd>
                                    
                                    <dt class="col-sm-4 text-muted">Interviewer</dt>
                                    <dd class="col-sm-8">
                                        <?php if ($interview['interviewer_name']): ?>
                                            <?= htmlspecialchars($interview['interviewer_name']) ?>
                                            <br>
                                            <small class="text-muted"><?= ucfirst($interview['interviewer_role'] ?? '') ?></small>
                                        <?php else: ?>
                                            Not assigned
                                        <?php endif; ?>
                                    </dd>
                                    
                                    <?php if ($interview['notes']): ?>
                                        <dt class="col-sm-4 text-muted">Notes</dt>
                                        <dd class="col-sm-8"><?= nl2br(htmlspecialchars($interview['notes'])) ?></dd>
                                    <?php endif; ?>
                                </dl>
                            </div>
                        </div>
                        
                        <?php if ($interview['status'] === 'completed'): ?>
                            <hr>
                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <h6 class="mb-2">Feedback</h6>
                                    <?php if ($interview['rating'] > 0): ?>
                                        <div class="mb-2">
                                            <strong>Rating:</strong>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $interview['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                            <?php endfor; ?>
                                            (<?= $interview['rating'] ?>/5)
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($interview['feedback']): ?>
                                        <div class="feedback-content p-3 bg-light rounded">
                                            <?= nl2br(htmlspecialchars($interview['feedback'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Sidebar -->
        <div class="col-md-4">
            <!-- Status History -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-history text-primary me-1"></i> Status History
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($status_history)): ?>
                        <div class="timeline">
                            <?php foreach ($status_history as $history): ?>
                                <div class="timeline-item mb-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="badge bg-<?= getStatusBadge($history['status']) ?> mb-1">
                                                <?= ucfirst($history['status']) ?>
                                            </span>
                                            <?php if ($history['remarks']): ?>
                                                <br>
                                                <small class="text-muted"><?= nl2br(htmlspecialchars($history['remarks'])) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted">
                                                <?= date('M d, Y', strtotime($history['created_at'])) ?>
                                                <br>
                                                <?= date('h:i A', strtotime($history['created_at'])) ?>
                                            </small>
                                            <?php if ($history['updated_by_name']): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?= htmlspecialchars($history['updated_by_name']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center mb-0">No status history available.</p>
                    <?php endif; ?>
                </div>
            </div>

         

            <!-- Candidate Info -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user-circle text-primary me-1"></i> Candidate Profile
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="avatar-circle mx-auto mb-2" style="width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, #2563eb, #7c3aed); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 28px; font-weight: 700;">
                            <?= strtoupper(substr($application['full_name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <h6 class="mb-0"><?= htmlspecialchars($application['full_name']) ?></h6>
                        <small class="text-muted"><?= ucfirst($application['role'] ?? 'N/A') ?></small>
                    </div>
                    <hr>
                    <dl class="row mb-0">
                        <dt class="col-sm-6 text-muted">Email</dt>
                        <dd class="col-sm-6 text-truncate">
                            <a href="mailto:<?= htmlspecialchars($application['email'] ?? '') ?>">
                                <?= htmlspecialchars($application['email'] ?? 'N/A') ?>
                            </a>
                        </dd>
                        
                        <dt class="col-sm-6 text-muted">Department</dt>
                        <dd class="col-sm-6"><?= htmlspecialchars($application['department'] ?? 'N/A') ?></dd>
                        
                        <dt class="col-sm-6 text-muted">Process</dt>
                        <dd class="col-sm-6"><?= htmlspecialchars($application['process'] ?? 'N/A') ?></dd>
                        
                       <?php if (!empty($application['person_id'])): ?>
                          <dt class="col-sm-6 text-muted">Employee ID</dt>
                         <dd class="col-sm-6"><?= htmlspecialchars($application['person_id']) ?></dd>
                     <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Timeline Styles */
.timeline {
    position: relative;
    padding-left: 0;
}

.timeline-item {
    position: relative;
    padding-left: 20px;
    border-left: 2px solid #e2e8f0;
}

.timeline-item:last-child {
    border-left-color: transparent;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 4px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #2563eb;
    border: 2px solid #fff;
}

.timeline-item:last-child::before {
    background: #16a34a;
}

/* Avatar Circle */
.avatar-circle {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 28px;
    font-weight: 700;
}

/* Cover Letter */
.cover-letter-content {
    font-size: 14px;
    line-height: 1.8;
    color: #1e293b;
    background: #f8fafc !important;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.feedback-content {
    font-size: 14px;
    line-height: 1.8;
    color: #1e293b;
    background: #f8fafc !important;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

/* Cards */
.card {
    border: none;
    border-radius: 12px;
    overflow: hidden;
}

.card-header {
    padding: 14px 20px;
    border-bottom: 2px solid #f1f5f9;
}

.card-body {
    padding: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .container {
        padding: 0 12px;
    }
    
    .card-body {
        padding: 14px;
    }
    
    .timeline-item {
        padding-left: 14px;
    }
}
</style>

<!-- Include the same modals and JavaScript from view_applications.php -->
<!-- ===== UPDATE STATUS MODAL ===== -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="statusModalHeader">
                <h5 class="modal-title" id="statusModalLabel">
                    <i class="fas fa-edit me-2"></i> Update Application Status
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="statusForm" method="POST" action="update_application_status.php">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="status_application_id">
                    <input type="hidden" name="status" id="status_value">
                    
                    <div class="alert" id="statusAlert">
                        <i class="fas fa-info-circle me-1"></i>
                        You are about to update status for <strong id="status_candidate_name"></strong> to 
                        <strong id="status_display"></strong>.
                    </div>
                    
                    <div class="mb-3">
                        <label for="status_remarks" class="form-label fw-bold">
                            <i class="fas fa-comment me-1"></i> Remarks / Reason <span class="text-danger">*</span>
                        </label>
                        <textarea name="status_remarks" id="status_remarks" class="form-control" rows="3" 
                                  placeholder="Enter remarks or reason for this status change..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="statusSubmitBtn">
                        <i class="fas fa-save me-1"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== SCHEDULE INTERVIEW MODAL ===== -->
<div class="modal fade" id="interviewModal" tabindex="-1" aria-labelledby="interviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="interviewModalLabel">
                    <i class="fas fa-calendar-plus me-2"></i> Schedule Interview
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="interviewForm" method="POST" action="schedule_interview.php">
                    <input type="hidden" name="application_id" id="interview_application_id">
                    <input type="hidden" name="job_id" value="<?= $application['job_id'] ?? 0 ?>">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        Scheduling interview for: <strong id="interview_candidate_name"></strong>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="interview_date" class="form-label fw-bold">
                                <i class="fas fa-calendar-day me-1"></i> Interview Date <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local" name="interview_date" id="interview_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="interview_type" class="form-label fw-bold">
                                <i class="fas fa-video me-1"></i> Interview Type <span class="text-danger">*</span>
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
                            <label for="interview_location" class="form-label fw-bold">
                                <i class="fas fa-map-marker-alt me-1"></i> Location / Meeting Link
                            </label>
                            <input type="text" name="interview_location" id="interview_location" 
                                   class="form-control" placeholder="e.g., Zoom link or Office address">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="interviewer_id" class="form-label fw-bold">
                                <i class="fas fa-user-tie me-1"></i> Interviewer
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
                        <label for="interview_notes" class="form-label fw-bold">
                            <i class="fas fa-sticky-note me-1"></i> Additional Notes
                        </label>
                        <textarea name="interview_notes" id="interview_notes" class="form-control" rows="3" 
                                  placeholder="Any special instructions or notes for the interview..."></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="scheduleSubmitBtn">
                            <i class="fas fa-paper-plane me-1"></i> Schedule Interview
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ===== INTERVIEW FEEDBACK MODAL ===== -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="feedbackModalHeader">
                <h5 class="modal-title" id="feedbackModalLabel">
                    <i class="fas fa-comment me-2"></i> Interview Feedback
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="feedbackForm" method="POST" action="provide_interview_feedback.php">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="feedback_application_id">
                    <input type="hidden" name="decision" id="feedback_decision">
                    
                    <div class="alert" id="feedbackAlert">
                        <i class="fas fa-info-circle me-1"></i>
                        You are about to <strong id="feedback_action_text"></strong> 
                        <strong id="feedback_candidate_name"></strong> for this position.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-star text-warning me-1"></i> Rating <span class="text-danger">*</span>
                        </label>
                        <div class="rating-input">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="rating" id="rating_<?= $i ?>" value="<?= $i ?>" required>
                                    <label class="form-check-label" for="rating_<?= $i ?>"><?= $i ?> ★</label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="feedback_text" class="form-label fw-bold">
                            <i class="fas fa-comment me-1"></i> Detailed Feedback <span class="text-danger">*</span>
                        </label>
                        <textarea name="feedback" id="feedback_text" class="form-control" rows="4" 
                                  placeholder="Provide detailed feedback about the candidate's interview performance..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="feedbackSubmitBtn">
                        <i class="fas fa-save me-1"></i> Submit Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ============================================
// JavaScript Functions (same as view_applications.php)
// ============================================

function updateApplicationStatus(id, status, name) {
    document.getElementById('status_application_id').value = id;
    document.getElementById('status_value').value = status;
    document.getElementById('status_candidate_name').textContent = name;
    document.getElementById('status_display').textContent = status.toUpperCase();
    
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

function openScheduleInterview(id, name) {
    document.getElementById('interview_application_id').value = id;
    document.getElementById('interview_candidate_name').textContent = name;
    
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(10, 0, 0, 0);
    document.getElementById('interview_date').value = tomorrow.toISOString().slice(0, 16);
    
    const modal = new bootstrap.Modal(document.getElementById('interviewModal'));
    modal.show();
}

function openFeedbackModal(id, decision, name) {
    document.getElementById('feedback_application_id').value = id;
    document.getElementById('feedback_decision').value = decision;
    document.getElementById('feedback_candidate_name').textContent = name;
    document.getElementById('feedback_action_text').textContent = (decision === 'selected' ? 'Select' : 'Reject');
    
    const modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
    modal.show();
}

// Form submissions
document.addEventListener('DOMContentLoaded', function() {
    // Status Form
    document.getElementById('statusForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('statusSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
        
        fetch('update_application_status.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
                alert('✅ Status updated successfully!');
                location.reload();
            } else {
                alert('❌ Error: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = 'Update Status';
            }
        })
        .catch(err => {
            alert('❌ Connection error');
            btn.disabled = false;
            btn.innerHTML = 'Update Status';
        });
    });
    
    // Interview Form
    document.getElementById('interviewForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('scheduleSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Scheduling...';
        
        fetch('schedule_interview.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('interviewModal')).hide();
                alert('✅ Interview scheduled successfully!');
                location.reload();
            } else {
                alert('❌ Error: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = 'Schedule Interview';
            }
        })
        .catch(err => {
            alert('❌ Connection error');
            btn.disabled = false;
            btn.innerHTML = 'Schedule Interview';
        });
    });
    
    // Feedback Form
    document.getElementById('feedbackForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('feedbackSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
        
        fetch('provide_interview_feedback.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('feedbackModal')).hide();
                alert('✅ Feedback submitted successfully!');
                location.reload();
            } else {
                alert('❌ Error: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = 'Submit Feedback';
            }
        })
        .catch(err => {
            alert('❌ Connection error');
            btn.disabled = false;
            btn.innerHTML = 'Submit Feedback';
        });
    });
});
</script>

<?php
// Include footer
include $_SERVER['DOCUMENT_ROOT'] . '/attendance/includes/footer.php';
?>