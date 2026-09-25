<?php
require_once 'config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Verify password
            if ($user && password_verify($password, $user['password'])) {
                // Set Session Data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['person_id'] = $user['person_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['position'] = $user['position'];

                // Log login activity
                $stmt = $pdo->prepare("INSERT INTO login_logs (user_id, login_time, ip_address) VALUES (?, NOW(), ?)");
                $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);

                // --- NEW LOGIC: FIRST LOGIN CHECK ---
                if (isset($user['is_first_login']) && $user['is_first_login'] == 1) {
                    $_SESSION['reset_message'] = "Welcome! This is your first login. Please reset your password to continue.";
                    redirect('reset_password.php'); // Create this file to handle the reset
                    exit();
                }

                redirect('dashboard.php');
            } else {
                $error = 'Invalid username or password';
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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet" />
    <style>
        /* ===== PROFESSIONAL BRIGHT WHITE THEME ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef1f5;
            padding: 1.5rem;
            position: relative;
        }

        /* Subtle background pattern */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(37, 99, 235, 0.03) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 50%, rgba(124, 58, 237, 0.03) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        /* ===== MAIN CARD ===== */
        .login-card {
            width: 100%;
            max-width: 430px;
            background: #ffffff;
            border-radius: 36px;
            padding: 2.8rem 2.8rem 2.5rem;
            box-shadow:
                0 20px 60px rgba(0, 0, 0, 0.05),
                0 8px 24px rgba(0, 0, 0, 0.02),
                0 2px 8px rgba(0, 0, 0, 0.01);
            border: 1px solid rgba(0, 0, 0, 0.03);
            position: relative;
            z-index: 1;
            transition: box-shadow 0.3s ease;
        }

        .login-card:hover {
            box-shadow:
                0 30px 80px rgba(0, 0, 0, 0.07),
                0 12px 32px rgba(0, 0, 0, 0.03);
        }

        /* Top accent bar */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: #2563eb;
            border-radius: 0 0 4px 4px;
        }

        /* ===== HEADER ===== */
        .login-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2.2rem;
            text-align: center;
        }

        .logo-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 0.3rem;
        }

        .logo-image {
            height: 100px;
            width: auto;
            object-fit: contain;
            margin-bottom: 0.3rem;
        }

        .brand-name {
            font-size: 2.1rem;
            font-weight: 900;
            letter-spacing: -0.03em;
            line-height: 1.1;
        }

        .brand-name .text-dark {
            color: #0a1628;
        }

        .brand-name .text-blue {
            color: #2563eb;
        }

        .brand-subtitle {
            font-size: 0.6rem;
            font-weight: 600;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #94a3b8;
            margin-top: 0.15rem;
        }

        .status-badge {
            margin-top: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.55rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #64748b;
            background: #f8fafc;
            padding: 0.35rem 1.2rem;
            border-radius: 40px;
            border: 1px solid #eef2f6;
        }

        .status-badge .dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulseDot 2s ease-in-out infinite;
        }

        @keyframes pulseDot {

            0%,
            100% {
                opacity: 0.6;
                transform: scale(1);
            }

            50% {
                opacity: 1;
                transform: scale(1.3);
            }
        }

        .auth-badge {
            margin-top: 0.8rem;
            background: #f8fafc;
            border-radius: 40px;
            padding: 0.35rem 1.4rem;
            font-size: 0.6rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #1e293b;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            border: 1px solid #eef2f6;
        }

        .auth-badge i {
            color: #2563eb;
            font-size: 0.6rem;
        }

        .auth-badge .cursor-blink {
            display: inline-block;
            width: 2px;
            height: 14px;
            background: #2563eb;
            animation: blink 1s step-end infinite;
            border-radius: 4px;
        }

        @keyframes blink {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0;
            }
        }

        /* ===== ERROR ===== */
        .error {
            background: #fef2f2;
            color: #dc2626;
            padding: 0.7rem 1.2rem;
            border-radius: 60px;
            margin-bottom: 1.5rem;
            font-size: 0.8rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            border: 1px solid #fecaca;
        }

        .error i {
            color: #dc2626;
            font-size: 0.9rem;
        }

        /* ===== FORM - Maximum Visibility ===== */
        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #1e293b;
            margin-bottom: 0.4rem;
        }

        .form-group label i {
            margin-right: 0.5rem;
            color: #64748b;
            font-size: 0.65rem;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
            background: #ffffff;
            border-radius: 60px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .input-group:focus-within {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.06);
        }

        .input-group .input-icon {
            position: absolute;
            left: 1.2rem;
            color: #94a3b8;
            font-size: 0.95rem;
            transition: color 0.3s ease;
            pointer-events: none;
            width: 18px;
            text-align: center;
        }

        .input-group:focus-within .input-icon {
            color: #2563eb;
        }

        .input-group input {
            width: 100%;
            padding: 0.95rem 1.2rem 0.95rem 3.4rem;
            border: none;
            background: transparent;
            font-size: 0.95rem;
            font-weight: 500;
            color: #0a1628;
            font-family: 'Inter', sans-serif;
            border-radius: 60px;
            outline: none;
        }

        .input-group input::placeholder {
            color: #b8c5d9;
            font-weight: 400;
            font-size: 0.9rem;
        }

        .input-group input:-webkit-autofill {
            background: transparent !important;
            -webkit-box-shadow: 0 0 0 30px white inset !important;
        }

        /* ===== BUTTON ===== */
        .btn-login {
            width: 100%;
            padding: 1rem;
            background: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 60px;
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.2);
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.3px;
            margin-top: 0.4rem;
        }

        .btn-login:hover {
            background: #1d4ed8;
            box-shadow: 0 8px 28px rgba(37, 99, 235, 0.3);
            transform: translateY(-2px);
        }

        .btn-login:active {
            transform: translateY(0px) scale(0.98);
        }

        .btn-login i {
            font-size: 1rem;
        }

        /* ===== DEMO CREDENTIALS ===== */
        .demo-credentials {
            margin-top: 2rem;
            background: #f8fafc;
            border-radius: 24px;
            padding: 1.2rem 1.4rem;
            border: 1px solid #eef2f6;
        }

        .demo-credentials .demo-title {
            font-size: 0.55rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 700;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.6rem;
        }

        .demo-credentials .demo-title i {
            color: #f59e0b;
            font-size: 0.7rem;
        }

        .demo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.2rem 1.2rem;
        }

        .demo-grid p {
            font-size: 0.8rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            margin: 0.1rem 0;
            padding: 0.15rem 0;
        }

        .demo-grid p strong {
            font-weight: 700;
            color: #0a1628;
        }

        .role-tag {
            display: inline-block;
            font-size: 0.45rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.12rem 0.6rem;
            border-radius: 40px;
            margin-left: 0.15rem;
            letter-spacing: 0.3px;
        }

        .role-tag.centre_head {
            background: #eff6ff;
            color: #2563eb;
        }

        .role-tag.process_head {
            background: #eff6ff;
            color: #3b82f6;
        }

        .role-tag.manager {
            background: #ecfdf5;
            color: #10b981;
        }

        .role-tag.am {
            background: #fffbeb;
            color: #f59e0b;
        }

        .role-tag.tl {
            background: #fef2f2;
            color: #ef4444;
        }

        .role-tag.agent {
            background: #f1f5f9;
            color: #64748b;
        }

        .demo-password-hint {
            margin-top: 0.6rem;
            font-size: 0.55rem;
            color: #94a3b8;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            font-weight: 500;
        }

        .demo-password-hint i {
            color: #f59e0b;
            font-size: 0.55rem;
        }

        .demo-password-hint strong {
            color: #1e293b;
            font-weight: 700;
        }

        /* ===== FOOTER ===== */
        .login-footer {
            margin-top: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            font-size: 0.6rem;
            color: #94a3b8;
            font-weight: 500;
            flex-wrap: wrap;
        }

        .login-footer .divider {
            display: inline-block;
            width: 16px;
            height: 1px;
            background: #e2e8f0;
        }

        .login-footer i {
            color: #94a3b8;
            opacity: 0.4;
            font-size: 0.65rem;
        }

        .login-footer .brand-footer {
            color: #2563eb;
            font-weight: 700;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            .login-card {
                padding: 2rem 1.5rem 1.8rem;
                border-radius: 28px;
            }

            .logo-image {
                height: 65px;
            }

            .brand-name {
                font-size: 1.7rem;
            }

            .demo-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.1rem 0.8rem;
            }

            .demo-grid p {
                font-size: 0.7rem;
            }

            .input-group input {
                font-size: 0.9rem;
                padding: 0.85rem 1rem 0.85rem 3rem;
            }

            .input-group .input-icon {
                left: 1rem;
                font-size: 0.9rem;
            }

            .btn-login {
                font-size: 0.9rem;
                padding: 0.9rem;
            }
        }

        @media (max-width: 380px) {
            .login-card {
                padding: 1.5rem 1rem 1.5rem;
                border-radius: 24px;
            }

            .brand-name {
                font-size: 1.4rem;
            }

            .logo-image {
                height: 55px;
            }

            .demo-grid {
                grid-template-columns: 1fr;
            }

            .status-badge {
                font-size: 0.5rem;
                padding: 0.25rem 0.8rem;
            }

            .auth-badge {
                font-size: 0.5rem;
                padding: 0.3rem 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="login-card">
        <!-- Header -->
        <div class="login-header">
            <div class="logo-wrapper">
                <img src="Blue.png" alt="<?php echo SITE_NAME; ?>" class="logo-image" />
                <div class="brand-name">
                    <span class="text-dark">ATTEN</span><span class="text-blue">DANCE</span>
                </div>
                <div class="brand-subtitle">Attendance Management System</div>
            </div>
            <div class="auth-badge">
                <i class="fas fa-shield-alt"></i> secure login
            </div>
        </div>

        <!-- Error -->
        <?php if ($error): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-user"></i></span>
                    <input type="text" id="username" name="username" required placeholder="Enter your username" autofocus />
                </div>
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                    <input type="password" id="password" name="password" required placeholder="Enter your password" />
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-arrow-right"></i> Sign In
            </button>

                    </form>



        <!-- Footer -->
        <div class="login-footer">
            <i class="fas fa-shield-alt"></i>
            <span class="divider"></span>
            <span>Developed by <span class="brand-footer">Finmech IT</span></span>
            <span class="divider"></span>
            <i class="fas fa-shield-alt"></i>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const form = document.querySelector('form');
                if (form) {
                    form.submit();
                }
            }
        });
    </script>
</body>

</html>