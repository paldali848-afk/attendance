<?php
// ============================================
// IJP - PROVIDE INTERVIEW FEEDBACK (FIXED)
// ============================================

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
    $decision = isset($_POST['decision']) ? trim($_POST['decision']) : '';
    $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;

    // Validation
    if ($application_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
        exit;
    }
    
    if (!in_array($decision, ['selected', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid decision. Must be "selected" or "rejected"']);
        exit;
    }
    
    if (empty($feedback)) {
        echo json_encode(['success' => false, 'message' => 'Feedback is required']);
        exit;
    }
    
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
        exit;
    }

    try {
        // Update latest interview for this application
        $stmt = $pdo->prepare("UPDATE job_interviews SET status = 'completed', feedback = ?, rating = ?, updated_at = NOW() 
                              WHERE application_id = ? ORDER BY created_at DESC LIMIT 1");
        $result = $stmt->execute([$feedback, $rating, $application_id]);

        if ($result) {
            // Update application status
            $user = getCurrentUser();
            $stmtApp = $pdo->prepare("UPDATE job_applications SET status = ?, notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $stmtApp->execute([$decision, $feedback, $user['id'], $application_id]);
            
            echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update interview record']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'POST method required']);
}
exit;
?>