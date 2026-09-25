<?php
// forgot_password.php

// Initialize variables and error messages
$username = $newPassword = $confirmPassword = '';
$usernameErr = $newPasswordErr = $confirmPasswordErr = '';
$successMsg = '';

// Database connection settings
$servername = "localhost";
$dbusername = "root";       // Changed from 'your_username'
$dbpassword = "Aks@93290090";           // Changed from 'your_password' (empty for XAMPP)
$dbname = "atten";  // Make sure this matches your actual database name
// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to validate password strength (example rules)
function validatePasswordStrength($password) {
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long.";
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        return "Password must contain at least one letter.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number.";
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $username = trim($_POST['username'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    // Validate username - check against database
    if (empty($username)) {
        $usernameErr = 'Username is required.';
    } else {
        // Check if username exists in database
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $usernameErr = 'Username not found in our records.';
        }
        $stmt->close();
    }

    // Validate new password (only if username is valid)
    if (empty($usernameErr)) {
        if (empty($newPassword)) {
            $newPasswordErr = 'New password is required.';
        } else {
            $passwordStrengthMsg = validatePasswordStrength($newPassword);
            if (!empty($passwordStrengthMsg)) {
                $newPasswordErr = $passwordStrengthMsg;
            }
        }

        // Validate confirm password
        if (empty($confirmPassword)) {
            $confirmPasswordErr = 'Please confirm your new password.';
        } elseif ($newPassword !== $confirmPassword) {
            $confirmPasswordErr = 'Passwords do not match.';
        }
    }

    // If no errors, update the password in database
    if (empty($usernameErr) && empty($newPasswordErr) && empty($confirmPasswordErr)) {
        // Hash the password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password in database
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
        $stmt->bind_param("ss", $hashedPassword, $username);
        
        if ($stmt->execute()) {
            $successMsg = "Password for <strong>$username</strong> has been updated successfully!";
            // Clear form fields
            $newPassword = $confirmPassword = '';
        } else {
            $usernameErr = "Error updating password. Please try again.";
        }
        $stmt->close();
    }
}

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Reset</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            background: #f0f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
        }
        .card {
            background: white;
            max-width: 480px;
            width: 100%;
            padding: 2rem 2rem 2.2rem;
            border-radius: 24px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08), 0 6px 10px rgba(0,0,0,0.02);
        }
        h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 0.25rem;
            color: #0b1e33;
        }
        .subhead {
            color: #476788;
            font-size: 0.95rem;
            margin-top: 0;
            margin-bottom: 1.8rem;
            border-left: 4px solid #2a7de1;
            padding-left: 0.75rem;
            background: #f5f9ff;
            padding: 0.65rem 1rem;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 1.4rem;
        }
        label {
            display: block;
            font-weight: 500;
            font-size: 0.9rem;
            color: #1f3a5f;
            margin-bottom: 0.3rem;
        }
        label .required {
            color: #c92a2a;
            margin-left: 2px;
        }
        input {
            width: 100%;
            padding: 0.7rem 1rem;
            font-size: 1rem;
            border: 1.5px solid #dce4ec;
            border-radius: 12px;
            background: #fafcff;
            transition: 0.2s;
        }
        input:focus {
            border-color: #2a7de1;
            outline: none;
            box-shadow: 0 0 0 3px rgba(42, 125, 225, 0.15);
            background: white;
        }
        .input-error {
            border-color: #d93c3c !important;
            background: #fff7f7;
        }
        .error-text {
            color: #c92a2a;
            font-size: 0.8rem;
            margin-top: 0.3rem;
            display: block;
        }
        .btn {
            background: #1a5bbf;
            color: white;
            border: none;
            padding: 0.8rem 1.8rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 40px;
            cursor: pointer;
            width: 100%;
            transition: 0.15s;
            letter-spacing: 0.3px;
            margin-top: 0.5rem;
        }
        .btn:hover {
            background: #124aa3;
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(26, 91, 191, 0.25);
        }
        .btn:active {
            transform: scale(0.98);
        }
        .success-msg {
            background: #e2f3e4;
            color: #0b5e2e;
            padding: 0.9rem 1.2rem;
            border-radius: 14px;
            margin-bottom: 1.8rem;
            border-left: 6px solid #1e7b43;
            font-weight: 500;
        }
        .footer-note {
            margin-top: 1.8rem;
            font-size: 0.85rem;
            color: #5a738b;
            text-align: center;
            border-top: 1px solid #e6edf5;
            padding-top: 1.2rem;
        }
        .footer-note a {
            color: #1a5bbf;
            text-decoration: none;
            font-weight: 500;
        }
        .field-hint {
            font-size: 0.8rem;
            color: #5d7a9a;
            margin-top: 0.2rem;
        }
        @media (max-width: 500px) {
            .card { padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔐 Reset Password</h1>
        <div class="subhead">
            Enter your username and set a new password.
        </div>

        <?php if (!empty($successMsg)): ?>
            <div class="success-msg">
                ✅ <?php echo $successMsg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="form-group">
                <label for="username">Username <span class="required">*</span></label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    placeholder="Enter your username" 
                    value="<?php echo htmlspecialchars($username); ?>"
                    class="<?php echo !empty($usernameErr) ? 'input-error' : ''; ?>"
                    required
                >
                <?php if (!empty($usernameErr)): ?>
                    <span class="error-text"><?php echo $usernameErr; ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="new_password">New Password <span class="required">*</span></label>
                <input 
                    type="password" 
                    id="new_password" 
                    name="new_password" 
                    placeholder="Min 8 chars, letters &amp; numbers" 
                    class="<?php echo !empty($newPasswordErr) ? 'input-error' : ''; ?>"
                    required
                >
                <?php if (!empty($newPasswordErr)): ?>
                    <span class="error-text"><?php echo $newPasswordErr; ?></span>
                <?php else: ?>
                    <div class="field-hint">Must be at least 8 characters with letters and numbers.</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    placeholder="Re-enter new password" 
                    class="<?php echo !empty($confirmPasswordErr) ? 'input-error' : ''; ?>"
                    required
                >
                <?php if (!empty($confirmPasswordErr)): ?>
                    <span class="error-text"><?php echo $confirmPasswordErr; ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn">Update Password</button>
        </form>

        <div class="footer-note">
            <a href="index.php">Back to login</a> 
        </div>
    </div>
</body>
</html>