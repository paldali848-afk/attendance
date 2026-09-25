<?php
require_once 'config.php';

// If not logged in, they shouldn't be here
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$error = '';
$success = '';

// Check if there is a welcome message from the login page
$message = $_SESSION['reset_message'] ?? 'Please set a new password for your account to continue.';
unset($_SESSION['reset_message']); // Clear it after showing once

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password and set is_first_login to 0
            $stmt = $pdo->prepare("UPDATE users SET password = ?, is_first_login = 0 WHERE id = ?");
            $result = $stmt->execute([$hashed_password, $_SESSION['user_id']]);

            if ($result) {
                $success = 'Password updated successfully! Redirecting to dashboard...';
                // Update session if needed
                header("refresh:2;url=dashboard.php");
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'Database Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password · FNM Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <style>
        /* matching your login page style */
        body {
            font-family: 'Inter', sans-serif;
            background: #eef1f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .reset-card {
            width: 100%;
            max-width: 430px;
            background: #ffffff;
            border-radius: 36px;
            padding: 2.8rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .logo-image { height: 80px; margin-bottom: 1rem; }

        .brand-name {
            font-size: 1.8rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
        }

        .text-dark { color: #0a1628; }
        .text-blue { color: #2563eb; }

        .welcome-msg {
            background: #eff6ff;
            color: #2563eb;
            padding: 1rem;
            border-radius: 16px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            line-height: 1.4;
            border: 1px solid #dbeafe;
        }

        .error {
            background: #fef2f2;
            color: #dc2626;
            padding: 0.8rem;
            border-radius: 60px;
            margin-bottom: 1rem;
            font-size: 0.8rem;
            border: 1px solid #fecaca;
        }

        .success {
            background: #ecfdf5;
            color: #059669;
            padding: 0.8rem;
            border-radius: 60px;
            margin-bottom: 1rem;
            font-size: 0.8rem;
            border: 1px solid #a7f3d0;
        }

        .form-group { margin-bottom: 1.2rem; text-align: left; }
        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 0.4rem;
            color: #1e293b;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
            background: #ffffff;
            border-radius: 60px;
            border: 2px solid #e2e8f0;
        }

        .input-group i {
            position: absolute;
            left: 1.2rem;
            color: #94a3b8;
        }

        .input-group input {
            width: 100%;
            padding: 0.95rem 1.2rem 0.95rem 3.4rem;
            border: none;
            background: transparent;
            border-radius: 60px;
            outline: none;
            font-size: 0.95rem;
        }

        .btn-reset {
            width: 100%;
            padding: 1rem;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 60px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .btn-reset:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="reset-card">
    <img src="Blue.png" alt="Logo" class="logo-image" />
    <div class="brand-name">
        <span class="text-dark">RESET</span><span class="text-blue"> PASSWORD</span>
    </div>

    <!-- Instructions -->
    <div class="welcome-msg">
        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($message); ?>
    </div>

    <?php if ($error): ?>
        <div class="error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>New Password</label>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="new_password" required placeholder="Minimum 6 characters">
            </div>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <div class="input-group">
                <i class="fas fa-check-double"></i>
                <input type="password" name="confirm_password" required placeholder="Repeat new password">
            </div>
        </div>

        <button type="submit" class="btn-reset">
            UPDATE PASSWORD <i class="fas fa-save" style="margin-left:8px"></i>
        </button>
    </form>
</div>

</body>
</html>