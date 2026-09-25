<?php
// ============================================
// IJP - SCHEDULE INTERVIEW (FIXED)
// ============================================

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
    $interview_date = $_POST['interview_date'] ?? '';
    $interview_type = $_POST['interview_type'] ?? '';
    $location = $_POST['interview_location'] ?? '';
    $interviewer_id = !empty($_POST['interviewer_id']) ? (int)$_POST['interviewer_id'] : null;
    $notes = $_POST['interview_notes'] ?? '';

    // Validation
    if ($application_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
        exit;
    }
    
    if (empty($interview_date)) {
        echo json_encode(['success' => false, 'message' => 'Interview date and time is required']);
        exit;
    }
    
    if (empty($interview_type)) {
        echo json_encode(['success' => false, 'message' => 'Interview type is required']);
        exit;
    }

    try {
        // 1. Save Interview details
        $result = scheduleApplicationInterview($pdo, $application_id, $interview_date, $interview_type, $location, $interviewer_id, $notes);

        if ($result) {
            // 2. Change application status to 'interviewed'
            $user = getCurrentUser();
            updateApplicationStatus($pdo, $application_id, 'interviewed', $user['id'], 'Interview scheduled for ' . $interview_date);
            
            echo json_encode(['success' => true, 'message' => 'Interview scheduled successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to schedule interview. Database error.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'POST method required']);
}
exit;
?>