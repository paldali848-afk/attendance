<?php
// policy.php - Complete Policy Management Page
// ============================================
// MATCHING UI DESIGN WITH USER.PHP - CARD VIEW
// ============================================

// Include config first
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get user details from session
$user_role = $_SESSION['role'] ?? 'employee';
$user_id = $_SESSION['user_id'] ?? 0;
$person_id = $_SESSION['person_id'] ?? '';

// ===== DEFINE WHO CAN MANAGE POLICIES =====
$is_admin = (
    in_array($user_role, ['admin', 'super_admin']) ||
    $person_id === 'FNM001' ||
    $user_role === 'centre_head'
);

// ===== HANDLE UPLOAD =====
$upload_error = "";
$success_message = "";

if ($is_admin && isset($_POST['upload_policy'])) {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($title) || empty($category)) {
        $_SESSION['upload_error'] = "Title and Category are required.";
    } elseif (isset($_FILES['policy_file']) && $_FILES['policy_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['policy_file'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file_ext !== 'pdf') {
            $_SESSION['upload_error'] = "Only PDF files are allowed.";
        } else {
            $upload_dir = 'uploads/policies/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $file_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO policies (title, category, description, file_path, file_name, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$title, $category, $description, $file_path, $new_filename, $user_id])) {
                        $_SESSION['success_message'] = "Policy uploaded successfully!";
                    } else {
                        $_SESSION['upload_error'] = "Database error: Could not save policy.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['upload_error'] = "Database error: " . $e->getMessage();
                }
            } else {
                $_SESSION['upload_error'] = "Failed to upload file.";
            }
        }
    } else {
        $_SESSION['upload_error'] = "Please select a PDF file.";
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit();
}

// ===== HANDLE DELETE =====
if ($is_admin && isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $policy_id = (int)$_GET['delete'];

    try {
        $stmt = $pdo->prepare("SELECT file_path FROM policies WHERE id = ?");
        $stmt->execute([$policy_id]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($policy) {
            if (file_exists($policy['file_path'])) {
                unlink($policy['file_path']);
            }

            $stmt = $pdo->prepare("DELETE FROM policies WHERE id = ?");
            if ($stmt->execute([$policy_id])) {
                $_SESSION['success_message'] = "Policy deleted successfully!";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['upload_error'] = "Error deleting policy: " . $e->getMessage();
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit();
}

// ===== HANDLE EDIT =====
if ($is_admin && isset($_POST['edit_policy'])) {
    $policy_id = (int)$_POST['policy_id'];
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($title) || empty($category)) {
        $_SESSION['upload_error'] = "Title and Category are required.";
    } else {
        try {
            if (isset($_FILES['policy_file']) && $_FILES['policy_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['policy_file'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if ($file_ext !== 'pdf') {
                    $_SESSION['upload_error'] = "Only PDF files are allowed.";
                } else {
                    $stmt = $pdo->prepare("SELECT file_path FROM policies WHERE id = ?");
                    $stmt->execute([$policy_id]);
                    $old_policy = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($old_policy && file_exists($old_policy['file_path'])) {
                        unlink($old_policy['file_path']);
                    }

                    $upload_dir = 'uploads/policies/';
                    $new_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
                    $file_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        $stmt = $pdo->prepare("UPDATE policies SET title = ?, category = ?, description = ?, file_path = ?, file_name = ? WHERE id = ?");
                        $stmt->execute([$title, $category, $description, $file_path, $new_filename, $policy_id]);
                        $_SESSION['success_message'] = "Policy updated successfully!";
                    } else {
                        $_SESSION['upload_error'] = "Failed to upload new file.";
                    }
                }
            } else {
                $stmt = $pdo->prepare("UPDATE policies SET title = ?, category = ?, description = ? WHERE id = ?");
                if ($stmt->execute([$title, $category, $description, $policy_id])) {
                    $_SESSION['success_message'] = "Policy updated successfully!";
                } else {
                    $_SESSION['upload_error'] = "Database error: Could not update policy.";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['upload_error'] = "Database error: " . $e->getMessage();
        }
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit();
}

// ===== DISPLAY MESSAGES FROM SESSION =====
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['upload_error'])) {
    $upload_error = $_SESSION['upload_error'];
    unset($_SESSION['upload_error']);
}

// ===== GET FILTER =====
$selected_category = isset($_GET['category']) ? trim($_GET['category']) : 'all';

// ===== FETCH POLICIES =====
try {
    $query = "SELECT p.*, u.full_name as uploaded_by_name 
              FROM policies p 
              LEFT JOIN users u ON p.uploaded_by = u.id";

    $params = [];
    if ($selected_category !== 'all') {
        $query .= " WHERE p.category = ?";
        $params[] = $selected_category;
    }
    $query .= " ORDER BY p.uploaded_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $policies = [];
    $upload_error = "Error fetching policies: " . $e->getMessage();
}

// ===== GET ALL CATEGORIES =====
try {
    $cat_stmt = $pdo->query("SELECT DISTINCT category FROM policies ORDER BY category");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Policies - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           MATCHING UI DESIGN FROM USER.PHP
           ============================================ */
        :root {
            --white: #ffffff;
            --bg-light: #f4f6fa;
            --bg-card: #ffffff;
            --text-primary: #0a1628;
            --text-secondary: #1e293b;
            --text-muted: #64748b;
            --border-light: #e8edf4;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 8px 28px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.06);
            --radius: 16px;
            --radius-sm: 10px;

            --blue: #2563eb;
            --blue-hover: #1d4ed8;
            --blue-light: #dbeafe;
            --blue-bg: rgba(37, 99, 235, 0.06);

            --green: #16a34a;
            --green-light: #dcfce7;
            --green-bg: rgba(22, 163, 74, 0.06);

            --red: #dc2626;
            --red-light: #fee2e2;
            --red-bg: rgba(220, 38, 38, 0.06);

            --yellow: #d97706;
            --yellow-light: #fef3c7;
            --yellow-bg: rgba(217, 119, 6, 0.06);

            --purple: #7c3aed;
            --purple-light: #ede9fe;
            --purple-bg: rgba(124, 58, 237, 0.06);

            --cyan: #0891b2;
            --cyan-light: #cffafe;
            --cyan-bg: rgba(8, 145, 178, 0.06);

            --orange: #ea580c;
            --orange-light: #ffedd5;
            --orange-bg: rgba(234, 88, 12, 0.06);

            --pink: #db2777;
            --pink-light: #fce7f3;
            --pink-bg: rgba(219, 39, 119, 0.06);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f2f6;
            padding: 20px;
            min-height: 100vh;
            color: var(--text-primary);
        }

        .container {
            max-width: 1440px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .anim-fade-up {
            animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }

        .anim-delay-1 {
            animation-delay: 0.05s;
        }
        .anim-delay-2 {
            animation-delay: 0.1s;
        }
        .anim-delay-3 {
            animation-delay: 0.15s;
        }
        .anim-delay-4 {
            animation-delay: 0.2s;
        }

        /* ============================================
           HEADER - MATCHING USER.PHP
           ============================================ */
        .header {
            background: var(--white);
            border-radius: var(--radius);
            padding: 22px 30px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            transition: all 0.3s ease;
        }

        .header:hover {
            box-shadow: var(--shadow-md);
        }

        .header .header-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .header .system-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 2.5px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header .system-label .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
            animation: pulseDot 2s ease-in-out infinite;
            display: inline-block;
        }

        @keyframes pulseDot {
            0%, 100% {
                opacity: 0.6;
                transform: scale(1);
            }
            50% {
                opacity: 1;
                transform: scale(1.3);
            }
        }

        .header h1 {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-primary);
            flex-wrap: wrap;
        }

        .header h1 i {
            color: var(--purple);
            font-size: 26px;
        }

        .header h1 .highlight {
            color: var(--blue);
            font-weight: 700;
        }

        .header h1 .admin-badge {
            background: var(--purple);
            color: #fff;
            padding: 3px 14px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.5px;
        }

        .header h1 .admin-badge i {
            color: #fff;
            font-size: 12px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .header-actions .filter-select {
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-light);
            font-weight: 600;
            cursor: pointer;
            background: var(--white);
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }

        .header-actions .filter-select:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .header-actions .btn-upload {
            background: linear-gradient(135deg, var(--blue), var(--purple));
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
        }

        .header-actions .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
        }

        .header-actions .btn-upload i {
            color: #fff;
        }

        .header-actions .btn-dashboard {
            color: var(--text-secondary);
            text-decoration: none;
            padding: 10px 22px;
            border-radius: var(--radius-sm);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border-light);
            background: var(--bg-light);
        }

        .header-actions .btn-dashboard:hover {
            background: var(--blue-bg);
            border-color: var(--blue);
            color: var(--blue);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .header-actions .btn-dashboard i {
            color: var(--blue);
        }

        .header-actions .btn-dashboard:hover i {
            color: var(--blue);
        }

        /* ============================================
           ALERT MESSAGES
           ============================================ */
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
        }

        .alert-success {
            background: var(--green-light);
            color: #065f46;
            border-color: #a7f3d0;
        }

        .alert-error {
            background: var(--red-light);
            color: #991b1b;
            border-color: #fca5a5;
        }

        .alert i {
            font-size: 18px;
        }

        /* ============================================
           CARD - MATCHING USER.PHP
           ============================================ */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 28px 32px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-light);
        }

        .card-header h2 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.3px;
        }

        .card-header h2 i {
            color: var(--purple);
            font-size: 18px;
        }

        .card-header .policy-count {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            padding: 6px 18px;
            border: 1px solid var(--border-light);
            border-radius: 50px;
            background: var(--bg-light);
        }

        .card-header .policy-count i {
            color: var(--purple);
            margin-right: 6px;
        }

        /* ============================================
           POLICY GRID - IMPROVED CARDS
           ============================================ */
        .policy-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        .policy-card {
            background: var(--white);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-light);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .policy-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--blue);
        }

        .policy-card .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .policy-card .policy-icon {
            font-size: 32px;
            color: #dc2626;
        }

        .policy-card .category-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid transparent;
            flex-shrink: 0;
        }

        .category-hr { background: var(--blue-bg); color: var(--blue); border-color: rgba(37, 99, 235, 0.15); }
        .category-it { background: var(--green-bg); color: #065f46; border-color: rgba(22, 163, 74, 0.15); }
        .category-operations { background: var(--yellow-bg); color: #92400e; border-color: rgba(217, 119, 6, 0.15); }
        .category-finance { background: #e0e7ff; color: #3730a3; border-color: rgba(55, 48, 163, 0.15); }
        .category-marketing { background: var(--pink-bg); color: #9d174d; border-color: rgba(219, 39, 119, 0.15); }
        .category-sales { background: var(--green-bg); color: #166534; border-color: rgba(22, 163, 74, 0.15); }
        .category-legal { background: #f1f5f9; color: #1f2937; border-color: rgba(31, 41, 55, 0.15); }
        .category-security { background: var(--red-bg); color: #991b1b; border-color: rgba(220, 38, 38, 0.15); }
        .category-default { background: var(--bg-light); color: var(--text-muted); border-color: var(--border-light); }

        .policy-card h4 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 8px 0;
            line-height: 1.3;
        }

        .policy-card .description {
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 15px;
            flex-grow: 1;
        }

        .policy-card .meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 14px;
            border-top: 1px solid var(--border-light);
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 15px;
        }

        .policy-card .meta .uploader {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .policy-card .meta .uploader i {
            color: var(--blue);
        }

        .policy-card .meta .date {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ============================================
           ACTION BUTTONS - ONE ROW
           ============================================ */
        .policy-card .actions {
            display: flex;
            gap: 6px;
            flex-wrap: nowrap;
            margin-top: auto;
        }

        .policy-card .actions a,
        .policy-card .actions button {
            padding: 6px 14px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 700;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
            flex-shrink: 0;
            flex: 1;
            justify-content: center;
        }

        .policy-card .actions .btn-view {
            background: var(--blue-bg);
            color: var(--blue);
            border: 1px solid rgba(37, 99, 235, 0.1);
        }

        .policy-card .actions .btn-view:hover {
            background: var(--blue);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
        }

        .policy-card .actions .btn-edit {
            background: var(--yellow-bg);
            color: var(--yellow);
            border: 1px solid rgba(217, 119, 6, 0.1);
        }

        .policy-card .actions .btn-edit:hover {
            background: var(--yellow);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 2px 10px rgba(217, 119, 6, 0.2);
        }

        .policy-card .actions .btn-delete {
            background: var(--red-bg);
            color: var(--red);
            border: 1px solid rgba(220, 38, 38, 0.1);
        }

        .policy-card .actions .btn-delete:hover {
            background: var(--red);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 2px 10px rgba(220, 38, 38, 0.2);
        }

        /* ============================================
           EMPTY STATE
           ============================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 48px;
            color: var(--text-muted);
            opacity: 0.2;
            margin-bottom: 16px;
        }

        .empty-state h4 {
            color: var(--text-secondary);
            font-weight: 700;
            font-size: 18px;
            margin: 0 0 8px 0;
        }

        .empty-state p {
            color: var(--text-muted);
            font-weight: 500;
            margin: 0;
        }

        .empty-state .btn-upload {
            margin-top: 16px;
            background: linear-gradient(135deg, var(--blue), var(--purple));
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
        }

        .empty-state .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
        }

        /* ============================================
           MODAL STYLES
           ============================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 99999;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius);
            padding: 30px 32px;
            max-width: 540px;
            width: 92%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: modalSlide 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-content h3 {
            margin: 0 0 20px 0;
            font-weight: 800;
            color: var(--text-primary);
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-content h3 i {
            color: var(--blue);
        }

        .modal-content label {
            display: block;
            margin: 16px 0 6px;
            font-weight: 700;
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-content label .required {
            color: var(--red);
        }

        .modal-content input,
        .modal-content select,
        .modal-content textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--text-secondary);
        }

        .modal-content input:focus,
        .modal-content select:focus,
        .modal-content textarea:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .modal-content textarea {
            height: 70px;
            resize: vertical;
        }

        .file-upload-wrapper {
            position: relative;
            margin-top: 4px;
        }

        .file-upload-wrapper .file-label {
            display: block;
            padding: 14px;
            background: var(--bg-light);
            border: 2px dashed var(--border-light);
            border-radius: var(--radius-sm);
            text-align: center;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 13px;
        }

        .file-upload-wrapper .file-label:hover {
            border-color: var(--blue);
            background: var(--blue-bg);
            color: var(--blue);
        }

        .file-upload-wrapper .file-label i {
            margin-right: 8px;
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-name-display {
            font-size: 13px;
            color: var(--blue);
            margin-top: 6px;
            font-weight: 600;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .modal-actions button {
            padding: 12px 20px;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
            font-family: 'Inter', sans-serif;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--blue), var(--purple));
            color: #fff;
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
        }

        .btn-cancel-modal {
            background: var(--bg-light);
            color: var(--text-muted);
            border: 1px solid var(--border-light);
        }

        .btn-cancel-modal:hover {
            background: var(--border-light);
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 1024px) {
            .policy-grid {
                grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 12px;
            }

            .header {
                padding: 20px 24px;
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }

            .header h1 {
                font-size: 22px;
            }

            .header h1 .admin-badge {
                font-size: 10px;
                padding: 2px 10px;
            }

            .header-actions {
                flex-direction: column;
                width: 100%;
            }

            .header-actions .filter-select {
                width: 100%;
            }

            .header-actions .btn-upload {
                width: 100%;
                justify-content: center;
            }

            .header-actions .btn-dashboard {
                width: 100%;
                justify-content: center;
            }

            .card {
                padding: 18px 16px;
                border-radius: var(--radius-sm);
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .card-header h2 {
                font-size: 16px;
            }

            .policy-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .policy-card {
                padding: 18px;
            }

            .policy-card .actions a,
            .policy-card .actions button {
                font-size: 10px;
                padding: 4px 10px;
            }

            .modal-content {
                padding: 20px;
                width: 95%;
            }

            .modal-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 8px;
            }

            .header {
                padding: 14px 16px;
                border-radius: var(--radius-sm);
            }

            .header h1 {
                font-size: 18px;
            }

            .header .system-label {
                font-size: 9px;
            }

            .header-actions .btn-upload {
                font-size: 12px;
                padding: 8px 16px;
            }

            .header-actions .btn-dashboard {
                font-size: 12px;
                padding: 8px 16px;
            }

            .card {
                padding: 12px 10px;
            }

            .card-header h2 {
                font-size: 14px;
            }

            .card-header .policy-count {
                font-size: 11px;
                padding: 4px 12px;
            }

            .policy-card h4 {
                font-size: 15px;
            }

            .policy-card .description {
                font-size: 12px;
            }

            .policy-card .actions a,
            .policy-card .actions button {
                font-size: 9px;
                padding: 3px 8px;
            }

            .policy-card .actions {
                gap: 4px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- ============================================
        HEADER - MATCHING USER.PHP
        ============================================ -->
        <div class="header anim-fade-up">
            <div class="header-left">
                <div class="system-label">
                    <span class="dot"></span>
                    POLICY MANAGEMENT
                </div>
                <h1>
                    <i class="fas fa-file-contract"></i>
                    COMPANY <span class="highlight">POLICIES</span>
                    <?php if ($is_admin): ?>
                        <span class="admin-badge">
                            <i class="fas fa-shield-alt"></i> Admin Access
                        </span>
                    <?php endif; ?>
                </h1>
            </div>
            <div class="header-actions">
                <form method="GET" style="margin: 0;">
                    <select name="category" class="filter-select" onchange="this.form.submit()">
                        <option value="all">All Categories</option>
                        <?php
                        $all_categories = ['HR', 'IT', 'Operations'];
                        foreach ($all_categories as $cat) {
                            $selected = ($selected_category === $cat) ? 'selected' : '';
                            echo "<option value=\"$cat\" $selected>$cat</option>";
                        }
                        ?>
                    </select>
                </form>
                <?php if ($is_admin): ?>
                    <button class="btn-upload" onclick="openUploadModal()">
                        <i class="fas fa-plus-circle"></i> Upload Policy
                    </button>
                <?php endif; ?>
                <a href="dashboard.php" class="btn-dashboard">
                    <i class="fas fa-th-large"></i> DASHBOARD
                </a>
            </div>
        </div>

        <!-- ============================================
        ALERT MESSAGES
        ============================================ -->
        <?php if ($success_message): ?>
            <div class="alert alert-success anim-fade-up anim-delay-1">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($upload_error): ?>
            <div class="alert alert-error anim-fade-up anim-delay-1">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($upload_error); ?>
            </div>
        <?php endif; ?>

        <!-- ============================================
        POLICY CARD - MATCHING USER.PHP CARD WITH GRID
        ============================================ -->
        <div class="card anim-fade-up anim-delay-2">
            <div class="card-header">
                <h2>
                    <i class="fas fa-list"></i> POLICY DIRECTORY
                </h2>
                <span class="policy-count">
                    <i class="fas fa-file-pdf"></i> <?php echo count($policies); ?> POLICIES
                </span>
            </div>

            <div class="policy-grid">
                <?php if (empty($policies)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h4>No Policies Found</h4>
                        <p><?php echo ($selected_category !== 'all') ? "No policies in the '{$selected_category}' category." : "No policies have been uploaded yet."; ?></p>
                        <?php if ($is_admin && $selected_category === 'all'): ?>
                            <button class="btn-upload" onclick="openUploadModal()">
                                <i class="fas fa-plus-circle"></i> Upload First Policy
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($policies as $policy): ?>
                        <?php
                        $cat_class = 'category-default';
                        $cat_lower = strtolower($policy['category']);
                        $cat_map = [
                            'hr' => 'category-hr',
                            'it' => 'category-it',
                            'operations' => 'category-operations',
                            
                        ];
                        if (isset($cat_map[$cat_lower])) {
                            $cat_class = $cat_map[$cat_lower];
                        }
                        ?>
                        <div class="policy-card">
                            <div class="card-top">
                                <div class="policy-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <span class="category-badge <?php echo $cat_class; ?>">
                                    <?php echo htmlspecialchars($policy['category']); ?>
                                </span>
                            </div>
                            <h4><?php echo htmlspecialchars($policy['title']); ?></h4>
                            <?php if (!empty($policy['description'])): ?>
                                <p class="description"><?php echo htmlspecialchars($policy['description']); ?></p>
                            <?php endif; ?>
                            <div class="meta">
                                <span class="uploader">
                                    <i class="fas fa-user-circle"></i>
                                    <?php echo htmlspecialchars($policy['uploaded_by_name'] ?? 'Unknown'); ?>
                                </span>
                                <span class="date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('d M Y', strtotime($policy['uploaded_at'])); ?>
                                </span>
                            </div>
                            <div class="actions">
                                <button onclick="window.location.href='view_pdf.php?id=<?php echo $policy['id']; ?>'" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <?php if ($is_admin): ?>
                                    <button onclick="openEditModal(<?php echo $policy['id']; ?>, '<?php echo addslashes($policy['title']); ?>', '<?php echo addslashes($policy['category']); ?>', '<?php echo addslashes($policy['description']); ?>')" class="btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="?delete=<?php echo $policy['id']; ?>" onclick="return confirmDelete(<?php echo $policy['id']; ?>)" class="btn-delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================
    UPLOAD MODAL
    ============================================ -->
    <div class="modal-overlay" id="uploadModal">
        <div class="modal-content">
            <h3><i class="fas fa-upload"></i> Upload New Policy</h3>
            <form action="" method="POST" enctype="multipart/form-data" id="uploadForm">
                <label>Policy Title <span class="required">*</span></label>
                <input type="text" name="title" placeholder="Enter policy title" required>

                <label>Category <span class="required">*</span></label>
                <select name="category" required>
                    <option value="">Select Category</option>
                    <option value="HR">HR</option>
                    <option value="IT">IT</option>
                    <option value="Operations">Operations</option>
                   
                </select>

                <label>Description</label>
                <textarea name="description" placeholder="Brief description of the policy"></textarea>

                <label>PDF File <span class="required">*</span></label>
                <div class="file-upload-wrapper">
                    <div class="file-label" id="uploadFileLabel">
                        <i class="fas fa-cloud-upload-alt"></i> Click to select PDF file
                    </div>
                    <input type="file" name="policy_file" accept=".pdf" required onchange="updateFileLabel(this, 'uploadFileLabel', 'uploadFileName')">
                </div>
                <div class="file-name-display" id="uploadFileName"></div>

                <div class="modal-actions">
                    <button type="submit" name="upload_policy" class="btn-submit">
                        <i class="fas fa-check"></i> Upload
                    </button>
                    <button type="button" class="btn-cancel-modal" onclick="closeModal('uploadModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================
    EDIT MODAL
    ============================================ -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-content">
            <h3><i class="fas fa-edit"></i> Edit Policy</h3>
            <form action="" method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="policy_id" id="edit_policy_id">
                <input type="hidden" name="edit_policy" value="1">

                <label>Policy Title <span class="required">*</span></label>
                <input type="text" name="title" id="edit_title" required>

                <label>Category <span class="required">*</span></label>
                <select name="category" id="edit_category" required>
                    <option value="HR">HR</option>
                    <option value="IT">IT</option>
                    <option value="Operations">Operations</option>
                   
                </select>

                <label>Description</label>
                <textarea name="description" id="edit_description"></textarea>

                <label>Replace PDF (Optional)</label>
                <div class="file-upload-wrapper">
                    <div class="file-label" id="editFileLabel">
                        <i class="fas fa-cloud-upload-alt"></i> Click to select new PDF
                    </div>
                    <input type="file" name="policy_file" accept=".pdf" onchange="updateFileLabel(this, 'editFileLabel', 'editFileName')">
                </div>
                <div class="file-name-display" id="editFileName"></div>
                <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                    <i class="fas fa-info-circle"></i> Leave empty to keep existing file
                </p>

                <div class="modal-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Update
                    </button>
                    <button type="button" class="btn-cancel-modal" onclick="closeModal('editModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ============================================
        // MODAL FUNCTIONS
        // ============================================
        function openUploadModal() {
            document.getElementById('uploadModal').classList.add('active');
            document.getElementById('uploadFileName').textContent = '';
            document.getElementById('uploadFileLabel').innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Click to select PDF file';
        }

        function openEditModal(id, title, category, description) {
            document.getElementById('editModal').classList.add('active');
            document.getElementById('edit_policy_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_description').value = description || '';
            document.getElementById('editFileName').textContent = '';
            document.getElementById('editFileLabel').innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Click to select new PDF';

            // Reset file input
            document.querySelector('#editForm input[type="file"]').value = '';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // ============================================
        // CONFIRM DELETE
        // ============================================
        function confirmDelete(policyId) {
            if (confirm('Are you sure you want to delete this policy? This action cannot be undone.')) {
                return true;
            }
            return false;
        }

        // ============================================
        // FILE LABEL UPDATE
        // ============================================
        function updateFileLabel(input, labelId, displayId) {
            const label = document.getElementById(labelId);
            const display = document.getElementById(displayId);

            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                label.innerHTML = '<i class="fas fa-file-pdf" style="color: #dc2626;"></i> ' + fileName;
                display.textContent = '?? ' + fileName;
            } else {
                label.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Click to select PDF file';
                display.textContent = '';
            }
        }

        // ============================================
        // CLOSE MODAL ON OVERLAY CLICK
        // ============================================
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // ============================================
        // CLOSE ON ESC
        // ============================================
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });

        console.log('Policy management loaded successfully!');
    </script>
</body>

</html>