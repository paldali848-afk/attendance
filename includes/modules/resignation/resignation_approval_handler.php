<?php
// ============================================
// RESIGNATION APPROVAL HANDLER - FIXED
// ============================================

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/resignation_functions.php';

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

$user_role = $user['role'] ?? '';
$user_id = $user['id'] ?? 0;

$action = $_POST['action'] ?? '';
$resignation_id = (int)($_POST['resignation_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if (empty($resignation_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid resignation ID.']);
    exit;
}

// 1. FETCH CURRENT STATE OF THE RESIGNATION
$stmt = $pdo->prepare("SELECT approval_level, status FROM resignation_requests WHERE id = ?");
$stmt->execute([$resignation_id]);
$resData = $stmt->fetch();

if (!$resData) {
    echo json_encode(['success' => false, 'message' => 'Resignation record not found.']);
    exit;
}

// 2. CHECK PERMISSIONS BASED ON CURRENT LEVEL
$is_process_head = isProcessHead($pdo, $user_id);
$is_designated_hr = isDesignatedHrApprover($pdo, $user_id);

switch ($action) {
    case 'approve':
        // If it's at Process Head level and user is a Process Head
        if ($resData['approval_level'] === 'process_head' && $is_process_head) {
            $result = processHeadApprove($pdo, $resignation_id, $user_id, $comment);
        } 
        // If it's at HR level and user is FNM5735
        else if ($resData['approval_level'] === 'hr' && $is_designated_hr) {
            $result = hrApprove($pdo, $resignation_id, $user_id, $comment);
        } 
        else {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to approve at this current stage.']);
            exit;
        }
        break;
        
    case 'reject':
        // Rejection can usually be done by either if they have the right role
        $result = rejectResignation($pdo, $resignation_id, $user_id, $comment);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit;
}

echo json_encode($result);
exit;