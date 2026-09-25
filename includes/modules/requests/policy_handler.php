<?php
// includes/modules/requests/policy_handler.php
// API endpoint for policy operations

session_start();
require_once '../../../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'hr'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'upload':
        handleUpload();
        break;
    case 'delete':
        handleDelete();
        break;
    case 'update':
        handleUpdate();
        break;
    case 'get':
        handleGet();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function handleUpload() {
    global $pdo;
    
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($title) || empty($category)) {
        echo json_encode(['error' => 'Title and category are required']);
        return;
    }
    
    if (!isset($_FILES['policy_file']) || $_FILES['policy_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'File upload failed']);
        return;
    }
    
    $file = $_FILES['policy_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_ext !== 'pdf') {
        echo json_encode(['error' => 'Only PDF files are allowed']);
        return;
    }
    
    // Create upload directory
    $upload_dir = '../../../uploads/policies/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $new_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $file_path = 'uploads/policies/' . $new_filename;
    $full_path = '../../../' . $file_path;
    
    if (move_uploaded_file($file['tmp_name'], $full_path)) {
        $stmt = $pdo->prepare("INSERT INTO policies (title, category, description, file_path, file_name, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $category, $description, $file_path, $new_filename, $_SESSION['user_id']])) {
            echo json_encode(['success' => true, 'message' => 'Policy uploaded successfully']);
        } else {
            echo json_encode(['error' => 'Database error']);
        }
    } else {
        echo json_encode(['error' => 'Failed to save file']);
    }
}

function handleDelete() {
    global $pdo;
    
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid policy ID']);
        return;
    }
    
    // Get file path
    $stmt = $pdo->prepare("SELECT file_path FROM policies WHERE id = ?");
    $stmt->execute([$id]);
    $policy = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($policy) {
        $full_path = '../../../' . $policy['file_path'];
        if (file_exists($full_path)) {
            unlink($full_path);
        }
        
        $stmt = $pdo->prepare("DELETE FROM policies WHERE id = ?");
        if ($stmt->execute([$id])) {
            echo json_encode(['success' => true, 'message' => 'Policy deleted successfully']);
        } else {
            echo json_encode(['error' => 'Database error']);
        }
    } else {
        echo json_encode(['error' => 'Policy not found']);
    }
}

function handleUpdate() {
    global $pdo;
    
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if ($id <= 0 || empty($title) || empty($category)) {
        echo json_encode(['error' => 'Invalid data']);
        return;
    }
    
    // Check if new file uploaded
    if (isset($_FILES['policy_file']) && $_FILES['policy_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['policy_file'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_ext !== 'pdf') {
            echo json_encode(['error' => 'Only PDF files are allowed']);
            return;
        }
        
        // Get old file
        $stmt = $pdo->prepare("SELECT file_path FROM policies WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($old) {
            $old_full = '../../../' . $old['file_path'];
            if (file_exists($old_full)) {
                unlink($old_full);
            }
        }
        
        // Upload new file
        $upload_dir = '../../../uploads/policies/';
        $new_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
        $file_path = 'uploads/policies/' . $new_filename;
        $full_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            $stmt = $pdo->prepare("UPDATE policies SET title = ?, category = ?, description = ?, file_path = ?, file_name = ? WHERE id = ?");
            $stmt->execute([$title, $category, $description, $file_path, $new_filename, $id]);
            echo json_encode(['success' => true, 'message' => 'Policy updated successfully']);
        } else {
            echo json_encode(['error' => 'Failed to upload new file']);
        }
    } else {
        // Update without changing file
        $stmt = $pdo->prepare("UPDATE policies SET title = ?, category = ?, description = ? WHERE id = ?");
        if ($stmt->execute([$title, $category, $description, $id])) {
            echo json_encode(['success' => true, 'message' => 'Policy updated successfully']);
        } else {
            echo json_encode(['error' => 'Database error']);
        }
    }
}

function handleGet() {
    global $pdo;
    
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid policy ID']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM policies WHERE id = ?");
    $stmt->execute([$id]);
    $policy = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($policy) {
        echo json_encode(['success' => true, 'data' => $policy]);
    } else {
        echo json_encode(['error' => 'Policy not found']);
    }
}
?>