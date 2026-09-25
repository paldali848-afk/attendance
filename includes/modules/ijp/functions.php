<?php
// ============================================
// IJP (Internal Job Posting) FUNCTIONS
// ============================================

// ============================================
// JOB FUNCTIONS
// ============================================

// Get all published jobs
function getPublishedJobs($pdo, $filters = []) {
    $sql = "SELECT j.*, u.full_name as posted_by_name 
            FROM job_postings j 
            JOIN users u ON j.posted_by = u.id 
            WHERE j.status = 'published'";
    
    $params = [];
    
    // Apply filters
    if (!empty($filters['department'])) {
        $sql .= " AND j.department = ?";
        $params[] = $filters['department'];
    }
    
    if (!empty($filters['process'])) {
        $sql .= " AND j.process = ?";
        $params[] = $filters['process'];
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (j.title LIKE ? OR j.description LIKE ?)";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
    }
    
    $sql .= " AND (j.closing_date IS NULL OR j.closing_date >= CURDATE())";
    $sql .= " ORDER BY j.created_at DESC";
    
    if (isset($filters['limit'])) {
        $sql .= " LIMIT " . intval($filters['limit']);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get job details (including all statuses for management)
function getJobDetails($pdo, $job_id, $include_all = false) {
    $sql = "SELECT j.*, u.full_name as posted_by_name, u.role as posted_by_role
            FROM job_postings j 
            JOIN users u ON j.posted_by = u.id 
            WHERE j.id = ?";
    
    if (!$include_all) {
        $sql .= " AND j.status = 'published'";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$job_id]);
    return $stmt->fetch();
}

// Create new job posting
function createJobPosting($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO job_postings 
                          (title, department, position, process, experience_required, 
                           education_required, skills_required, description, responsibilities,
                           salary_range, location, employment_type, min_experience_years,
                           max_experience_years, posted_by, status, closing_date, vacancies)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([
        $data['title'],
        $data['department'],
        $data['position'],
        $data['process'] ?? null,
        $data['experience_required'] ?? null,
        $data['education_required'] ?? null,
        $data['skills_required'] ?? null,
        $data['description'],
        $data['responsibilities'] ?? null,
        $data['salary_range'] ?? null,
        $data['location'] ?? null,
        $data['employment_type'] ?? 'full_time',
        $data['min_experience_years'] ?? 0,
        $data['max_experience_years'] ?? 99,
        $data['posted_by'],
        $data['status'] ?? 'draft',
        $data['closing_date'] ?? null,
        $data['vacancies'] ?? 1
    ]);
}

// Update job posting
function updateJobPosting($pdo, $job_id, $data) {
    $sql = "UPDATE job_postings SET ";
    $updates = [];
    $params = [];
    
    $fields = ['title', 'department', 'position', 'process', 'experience_required', 
               'education_required', 'skills_required', 'description', 'responsibilities',
               'salary_range', 'location', 'employment_type', 'min_experience_years',
               'max_experience_years', 'status', 'closing_date', 'vacancies'];
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $sql .= implode(', ', $updates) . " WHERE id = ?";
    $params[] = $job_id;
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

// Update job status with remarks
function updateJobStatus($pdo, $job_id, $status, $remarks, $user_id) {
    $valid_statuses = ['draft', 'published', 'closed', 'cancelled', 'selected', 'rejected', 'on_hold'];
    if (!in_array($status, $valid_statuses)) {
        return ['success' => false, 'message' => 'Invalid status'];
    }
    
    $stmt = $pdo->prepare("UPDATE job_postings 
                          SET status = ?, status_remark = ?, status_updated_at = NOW(), status_updated_by = ? 
                          WHERE id = ?");
    return $stmt->execute([$status, $remarks, $user_id, $job_id]);
}

// Get all jobs for management
function getManagedJobs($pdo, $user_id, $role, $process = null) {
    $sql = "SELECT j.*, 
            (SELECT COUNT(*) FROM job_applications WHERE job_id = j.id) as total_applications,
            (SELECT COUNT(*) FROM job_applications WHERE job_id = j.id AND status = 'pending') as pending_applications,
            u.full_name as posted_by_name
            FROM job_postings j 
            JOIN users u ON j.posted_by = u.id 
            WHERE 1=1";
    
    $params = [];
    
    if ($role === 'process_head' && $process) {
        $sql .= " AND j.process = ?";
        $params[] = $process;
    }
    
    if ($role !== 'hr' && $role !== 'centre_head') {
        $sql .= " AND j.posted_by = ?";
        $params[] = $user_id;
    }
    
    $sql .= " ORDER BY j.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Delete job posting
function deleteJobPosting($pdo, $job_id) {
    $stmt = $pdo->prepare("DELETE FROM job_postings WHERE id = ?");
    return $stmt->execute([$job_id]);
}

// Schedule interview for job
function scheduleJobInterview($pdo, $job_id, $interview_date, $interview_type, $interview_location, $interviewer_id, $interview_notes) {
    $stmt = $pdo->prepare("UPDATE job_postings 
                          SET interview_date = ?, interview_type = ?, interview_location = ?, 
                              interviewer_id = ?, interview_notes = ? 
                          WHERE id = ?");
    return $stmt->execute([
        $interview_date,
        $interview_type,
        $interview_location,
        $interviewer_id,
        $interview_notes,
        $job_id
    ]);
}

// Get interview details for a job
function getJobInterviewDetails($pdo, $job_id) {
    $stmt = $pdo->prepare("SELECT j.*, u.full_name as interviewer_name, u.role as interviewer_role
                          FROM job_postings j 
                          LEFT JOIN users u ON j.interviewer_id = u.id 
                          WHERE j.id = ?");
    $stmt->execute([$job_id]);
    return $stmt->fetch();
}

// Get job status history
function getJobStatusHistory($pdo, $job_id) {
    // Note: This would require a separate status history table
    // For now, we'll return basic info
    $stmt = $pdo->prepare("SELECT j.status, j.status_remark, j.status_updated_at, u.full_name as updated_by
                          FROM job_postings j 
                          LEFT JOIN users u ON j.status_updated_by = u.id 
                          WHERE j.id = ?");
    $stmt->execute([$job_id]);
    return $stmt->fetchAll();
}

// ============================================
// APPLICATION FUNCTIONS
// ============================================

// Check if user has already applied
function hasApplied($pdo, $job_id, $user_id) {
    $stmt = $pdo->prepare("SELECT id FROM job_applications WHERE job_id = ? AND user_id = ?");
    $stmt->execute([$job_id, $user_id]);
    return $stmt->fetch() ? true : false;
}

// Submit job application
function submitApplication($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO job_applications 
                          (job_id, user_id, cover_letter, expected_salary, availability_date) 
                          VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([
        $data['job_id'],
        $data['user_id'],
        $data['cover_letter'] ?? null,
        $data['expected_salary'] ?? null,
        $data['availability_date'] ?? null
    ]);
}

// Get user's applications
function getUserApplications($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT a.*, j.title, j.department, j.position, j.status as job_status,
                          j.closing_date, j.location
                          FROM job_applications a 
                          JOIN job_postings j ON a.job_id = j.id 
                          WHERE a.user_id = ? 
                          ORDER BY a.created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Get applications for a job
function getJobApplications($pdo, $job_id, $status_filter = null) {
    $sql = "SELECT a.*, u.full_name, u.role, u.department, u.process, u.email, u.person_id
            FROM job_applications a 
            JOIN users u ON a.user_id = u.id 
            WHERE a.job_id = ?";
    
    $params = [$job_id];
    
    if ($status_filter) {
        $sql .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " ORDER BY a.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Update application status
function updateApplicationStatus($pdo, $application_id, $status, $reviewed_by, $notes = null) {
    $valid_statuses = ['pending', 'reviewing', 'shortlisted', 'interviewed', 'offered', 'accepted', 'rejected', 'withdrawn', 'on_hold'];
    if (!in_array($status, $valid_statuses)) {
        return false;
    }
    
    $stmt = $pdo->prepare("UPDATE job_applications 
                          SET status = ?, reviewed_by = ?, reviewed_at = NOW(), notes = ? 
                          WHERE id = ?");
    return $stmt->execute([$status, $reviewed_by, $notes, $application_id]);
}

// Get application details
function getApplicationDetails($pdo, $application_id) {
    $stmt = $pdo->prepare("SELECT a.*, u.full_name, u.role, u.department, u.process, u.email,
                          j.title, j.department as job_department, j.position as job_position,
                          j.location as job_location
                          FROM job_applications a 
                          JOIN users u ON a.user_id = u.id 
                          JOIN job_postings j ON a.job_id = j.id 
                          WHERE a.id = ?");
    $stmt->execute([$application_id]);
    return $stmt->fetch();
}

// Get application status history
function getApplicationStatusHistory($pdo, $application_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT h.*, u.full_name as updated_by_name 
            FROM application_status_history h
            LEFT JOIN users u ON h.updated_by = u.id
            WHERE h.application_id = ?
            ORDER BY h.created_at DESC
        ");
        $stmt->execute([$application_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return []; // Return empty array if table error occurs to prevent crash
    }
}

// Withdraw application
function withdrawApplication($pdo, $application_id, $user_id) {
    $stmt = $pdo->prepare("UPDATE job_applications SET status = 'withdrawn' 
                          WHERE id = ? AND user_id = ? AND status = 'pending'");
    return $stmt->execute([$application_id, $user_id]);
}

// ============================================
// INTERVIEW FUNCTIONS
// ============================================

// Schedule interview for application
function scheduleApplicationInterview($pdo, $application_id, $interview_date, $interview_type, $interview_location, $interviewer_id, $interview_notes) {
    $stmt = $pdo->prepare("INSERT INTO job_interviews 
                          (application_id, interview_date, interview_type, interview_location, interviewer_id, notes, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, NOW())");
    return $stmt->execute([
        $application_id,
        $interview_date,
        $interview_type,
        $interview_location,
        $interviewer_id,
        $interview_notes
    ]);
}

// Get interview details for an application
function getInterviewDetails($pdo, $application_id) {
    $stmt = $pdo->prepare("SELECT i.*, u.full_name as interviewer_name, u.role as interviewer_role
                          FROM job_interviews i 
                          LEFT JOIN users u ON i.interviewer_id = u.id 
                          WHERE i.application_id = ? 
                          ORDER BY i.created_at DESC LIMIT 1");
    $stmt->execute([$application_id]);
    return $stmt->fetch();
}

// Get all interviews for a user
function getUserInterviews($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT i.*, j.title as job_title, j.department, a.user_id as applicant_id,
                          u.full_name as applicant_name
                          FROM job_interviews i 
                          JOIN job_applications a ON i.application_id = a.id 
                          JOIN job_postings j ON a.job_id = j.id 
                          JOIN users u ON a.user_id = u.id 
                          WHERE a.user_id = ? OR i.interviewer_id = ?
                          ORDER BY i.interview_date DESC");
    $stmt->execute([$user_id, $user_id]);
    return $stmt->fetchAll();
}

// Update interview status
function updateInterviewStatus($pdo, $interview_id, $status, $feedback = null) {
    $valid_statuses = ['scheduled', 'completed', 'cancelled', 'no_show'];
    if (!in_array($status, $valid_statuses)) {
        return false;
    }
    
    $stmt = $pdo->prepare("UPDATE job_interviews 
                          SET status = ?, feedback = ?, updated_at = NOW() 
                          WHERE id = ?");
    return $stmt->execute([$status, $feedback, $interview_id]);
}

// ============================================
// STATISTICS FUNCTIONS
// ============================================

// Get application statistics
function getApplicationStats($pdo, $user_id = null) {
    $sql = "SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END) as reviewing,
            SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
            SUM(CASE WHEN status = 'interviewed' THEN 1 ELSE 0 END) as interviewed,
            SUM(CASE WHEN status = 'offered' THEN 1 ELSE 0 END) as offered,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'withdrawn' THEN 1 ELSE 0 END) as withdrawn,
            SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold
            FROM job_applications";
    
    if ($user_id) {
        $sql .= " WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    
    return $stmt->fetch();
}

// Get job statistics
function getJobStats($pdo, $job_id = null) {
    $sql = "SELECT 
            COUNT(*) as total_jobs,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'selected' THEN 1 ELSE 0 END) as selected,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold
            FROM job_postings";
    
    if ($job_id) {
        $sql .= " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$job_id]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    
    return $stmt->fetch();
}

// ============================================
// HELPER FUNCTIONS
// ============================================

// Get status badge color for applications
function getStatusBadge($status) {
    $badge_map = [
        'pending' => 'warning',
        'reviewing' => 'info',
        'shortlisted' => 'primary',
        'interviewed' => 'info',
        'offered' => 'success',
        'accepted' => 'success',
        'rejected' => 'danger',
        'withdrawn' => 'secondary',
        'on_hold' => 'secondary'
    ];
    return $badge_map[$status] ?? 'secondary';
}

// Get job status badge
function getJobStatusBadge($status) {
    $badge_map = [
        'draft' => 'secondary',
        'published' => 'success',
        'closed' => 'danger',
        'cancelled' => 'dark',
        'selected' => 'success',
        'rejected' => 'danger',
        'on_hold' => 'warning'
    ];
    return $badge_map[$status] ?? 'secondary';
}

// Get job status label
function getJobStatusLabel($status) {
    $label_map = [
        'draft' => 'Draft',
        'published' => 'Published',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
        'selected' => 'Selected',
        'rejected' => 'Rejected',
        'on_hold' => 'On Hold'
    ];
    return $label_map[$status] ?? ucfirst($status);
}

// Get interview status badge
function getInterviewStatusBadge($status) {
    $badge_map = [
        'scheduled' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger',
        'no_show' => 'warning'
    ];
    return $badge_map[$status] ?? 'secondary';
}

// Get departments list
function getDepartments($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get processes list
function getProcesses($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT process FROM users WHERE process IS NOT NULL AND process != '' ORDER BY process");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get all interviewers (for dropdown)
function getInterviewers($pdo) {
    $stmt = $pdo->prepare("SELECT id, full_name, role FROM users 
                          WHERE role IN ('process_head', 'centre_head', 'hr', 'manager') 
                          ORDER BY full_name");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Check if user can apply for job
function canApply($pdo, $user_id, $job_id) {
    // Check if already applied
    if (hasApplied($pdo, $job_id, $user_id)) {
        return ['can' => false, 'reason' => 'You have already applied for this position.'];
    }
    
    // Check if job is published and not closed
    $job = getJobDetails($pdo, $job_id);
    if (!$job) {
        return ['can' => false, 'reason' => 'Job not found.'];
    }
    
    if ($job['status'] !== 'published') {
        return ['can' => false, 'reason' => 'This job is not open for applications.'];
    }
    
    // Check closing date
    if ($job['closing_date'] && $job['closing_date'] < date('Y-m-d')) {
        return ['can' => false, 'reason' => 'This job has expired.'];
    }
    
    return ['can' => true, 'reason' => ''];
}

// Send notification
function sendIJPNotification($pdo, $user_id, $title, $message, $type = 'ijp') {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                          VALUES (?, ?, ?, ?, 0, NOW())");
    return $stmt->execute([$user_id, $title, $message, $type]);
}

// Get application count by status for a job
function getApplicationCountByStatus($pdo, $job_id) {
    $sql = "SELECT status, COUNT(*) as count 
            FROM job_applications 
            WHERE job_id = ? 
            GROUP BY status";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$job_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $counts = [
        'pending' => 0,
        'shortlisted' => 0,
        'interviewed' => 0,
        'offered' => 0,
        'accepted' => 0,
        'rejected' => 0,
        'on_hold' => 0
    ];
    
    foreach ($results as $row) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = $row['count'];
        }
    }
    
    return $counts;
}
?>