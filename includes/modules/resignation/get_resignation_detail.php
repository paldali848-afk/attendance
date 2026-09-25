<?php
// ============================================
// GET RESIGNATION DETAIL - AJAX HANDLER
// ============================================

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../auth/authentication.php';
require_once __DIR__ . '/resignation_functions.php';

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

// Check permission
if (!in_array($user['role'], ['hr', 'process_head', 'manager', 'centre_head', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission.']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid resignation ID.']);
    exit;
}

try {
    // Get resignation details with user info
    $sql = "SELECT r.*, 
            u.full_name as user_name, u.role as user_role,
            a.full_name as approved_by_name, 
            rej.full_name as rejected_by_name
            FROM resignation_requests r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN users a ON r.approved_by = a.id
            LEFT JOIN users rej ON r.rejected_by = rej.id
            WHERE r.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $resignation = $stmt->fetch();
    
    if (!$resignation) {
        echo json_encode(['success' => false, 'message' => 'Resignation not found.']);
        exit;
    }
    
    echo json_encode(['success' => true, 'resignation' => $resignation]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
exit;
?>