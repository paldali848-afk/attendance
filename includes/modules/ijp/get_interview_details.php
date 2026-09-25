<?php
// ============================================
// IJP - GET INTERVIEW DETAILS (AJAX)
// ============================================

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$application_id = isset($_GET['application_id']) ? (int)$_GET['application_id'] : 0;

if ($application_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit();
}

$interview = getInterviewDetails($pdo, $application_id);

if ($interview) {
    // Format date
    $interview['interview_date'] = date('M d, Y h:i A', strtotime($interview['interview_date']));
    
    echo json_encode([
        'success' => true,
        'interview' => $interview
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No interview scheduled for this application'
    ]);
}

?>