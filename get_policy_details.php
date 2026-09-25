<?php
// get_policy_details.php - Fetch policy details for AJAX

// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get policy ID
$policy_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($policy_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid policy ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, title, category, file_path FROM policies WHERE id = ?");
    $stmt->execute([$policy_id]);
    $policy = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($policy) {
        // Check if file exists
        if (!file_exists($policy['file_path'])) {
            echo json_encode(['success' => false, 'message' => 'Policy file not found']);
            exit();
        }
        
        echo json_encode([
            'success' => true,
            'policy' => [
                'id' => $policy['id'],
                'title' => htmlspecialchars($policy['title']),
                'category' => htmlspecialchars($policy['category']),
                'file_path' => $policy['file_path']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Policy not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>