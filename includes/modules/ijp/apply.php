<?php
// ============================================
// IJP - SUBMIT APPLICATION
// ============================================

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    redirect('../../../login.php');
}

$user = getCurrentUser();
$user_id = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
    
    if ($job_id <= 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid job ID.'];
        redirect('jobs.php');
    }
    
    // Check if already applied
    if (hasApplied($pdo, $job_id, $user_id)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'You have already applied for this position.'];
        redirect('job_details.php?id=' . $job_id);
    }
    
    // Check if job is still open
    $job = getJobDetails($pdo, $job_id);
    if (!$job || $job['status'] !== 'published') {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'This job is no longer accepting applications.'];
        redirect('jobs.php');
    }
    
    // Submit application
    $data = [
        'job_id' => $job_id,
        'user_id' => $user_id,
        'cover_letter' => $_POST['cover_letter'] ?? '',
        'expected_salary' => $_POST['expected_salary'] ?? null,
        'availability_date' => $_POST['availability_date'] ?? null
    ];
    
    if (submitApplication($pdo, $data)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Your application has been submitted successfully!'];
        
        // Send notification
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                               VALUES (?, ?, ?, ?, 0, NOW())");
        $stmt->execute([
            $user_id,
            'Application Submitted',
            'You have successfully applied for the position: ' . $job['title'],
            'ijp'
        ]);
        
        redirect('job_details.php?id=' . $job_id);
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to submit application. Please try again.'];
        redirect('job_details.php?id=' . $job_id);
    }
} else {
    redirect('jobs.php');
}
include __DIR__ . '/../../header.php';
?>