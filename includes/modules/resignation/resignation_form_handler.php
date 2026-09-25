<?php
// ============================================
// RESIGNATION FORM HANDLER
// ============================================

// Include bootstrap
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/resignation_functions.php';

// Set header for JSON response
header('Content-Type: application/json');

// Check if user is logged in
$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get form data
$employee_name = trim($_POST['employee_name'] ?? '');
$resignation_date = trim($_POST['resignation_date'] ?? '');
$notice_period_days = (int)($_POST['notice_period_days'] ?? 0);
$position = trim($_POST['position'] ?? '');
$company_name = trim($_POST['company_name'] ?? '');
$last_working_date = trim($_POST['last_working_date'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$reason_detail = trim($_POST['reason_detail'] ?? '');

// Combine reason with detail
$full_reason = $reason;
if (!empty($reason_detail)) {
    $full_reason .= " - " . $reason_detail;
}

// Validate required fields
if (empty($employee_name) || empty($resignation_date) || empty($notice_period_days) || 
    empty($position) || empty($company_name) || empty($last_working_date) || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
    exit;
}

// Validate dates
if (strtotime($last_working_date) <= strtotime($resignation_date)) {
    echo json_encode(['success' => false, 'message' => 'Last working day must be after resignation date.']);
    exit;
}

// Check if user already has pending resignation
if (hasPendingResignation($pdo, $user['id'])) {
    echo json_encode(['success' => false, 'message' => 'You already have a pending resignation request.']);
    exit;
}

// Prepare data for submission
$data = [
    'employee_name' => $employee_name,
    'position' => $position,
    'company_name' => $company_name,
    'resignation_date' => $resignation_date,
    'last_working_date' => $last_working_date,
    'reason' => $full_reason,
    'notice_period_days' => $notice_period_days
];

// Submit resignation
$result = submitResignation($pdo, $user['id'], $data);

echo json_encode($result);
exit;
?>