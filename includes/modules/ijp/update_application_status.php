<?php
// ============================================
// IJP - UPDATE APPLICATION STATUS (ULTRA SIMPLE)
// ============================================

require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json');

// Simple test - just update the database
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $remarks = isset($_POST['status_remarks']) ? trim($_POST['status_remarks']) : '';
    
    if ($application_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit();
    }
    
    if (empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Status required']);
        exit();
    }
    
    try {
        // Direct update
        $stmt = $pdo->prepare("UPDATE job_applications SET status = ?, notes = ? WHERE id = ?");
        $result = $stmt->execute([$status, $remarks, $application_id]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'POST required']);
}
?>