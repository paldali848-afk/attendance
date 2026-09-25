<?php
// serve_pdf.php - Serve PDF for inline display
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config.php';

// Get policy ID
$policy_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($policy_id <= 0) {
    die('Invalid policy ID');
}

// Fetch policy details
try {
    $stmt = $pdo->prepare("SELECT file_path, title FROM policies WHERE id = ?");
    $stmt->execute([$policy_id]);
    $policy = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$policy) {
        die('Policy not found');
    }
    
    $file_path = $policy['file_path'];
    
    // Check if file exists
    if (!file_exists($file_path)) {
        die('File not found');
    }
    
    // Set headers to display PDF inline
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $policy['title'] . '.pdf"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Read and output the file
    readfile($file_path);
    exit;
    
} catch (PDOException $e) {
    die('Database error');
}
?>