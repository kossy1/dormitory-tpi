<?php
session_start();
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

include '../config/db.php';
include 'includes/logger.php';   // optional — comment out if you don't have it yet

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Please enter both email and password.";
    } else {
        // Try admin table first
        $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $admin = null;
        }

        // If not found, try staff table (admin/staff roles)
        if (!$admin) {
            $stmt = $conn->prepare("SELECT * FROM staff WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $admin = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }

        // Now $admin is either an array or null — check before using
        if ($admin && !empty($admin['password']) && password_verify($password, $admin['password'])) {
            // Set both keys so the rest of the panel keeps working
            $_SESSION['admin_id']    = $admin['id'];
            $_SESSION['staff_id']    = $admin['id'];
            $_SESSION['admin_name']  = $admin['full_name']  ?? 'Administrator';
            $_SESSION['staff_name']  = $admin['full_name']  ?? 'Administrator';
            $_SESSION['admin_role']  = $admin['role']       ?? 'admin';
            $_SESSION['staff_role']  = $admin['role']       ?? 'admin';
            $_SESSION['admin_photo'] = $admin['photo']      ?? '';

            // Update last login if column exists
            $table = isset($admin['role']) && $admin['role'] ? 'staff' : 'admin';
            @$conn->query("UPDATE $table SET last_login = NOW() WHERE id = " . (int)$admin['id']);

            // Log success
            if (function_exists('logActivity')) {
                logActivity($conn, 'login', 'admin', (int)$admin['id'],
                    "Admin {$admin['full_name']} logged in successfully");
            }

            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid email or password.";

            // Log failed attempt
            if (function_exists('logActivity')) {
                logActivity($conn, 'failed_login', 'admin', null,
                    "Failed login attempt for email: " . htmlspecialchars($email));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1a3a6b 0%, #0d1b3e 100%);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: -50%; right: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,215,0,0.08) 0%, transparent 60%);
            animation: rotate 30s linear infinite;
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        .login-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.4);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            position: relative;
            z-index: 1;
        }
        .login-left {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            padding: 60px 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .login-left::before {
            content: '\f19c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: -50px; bottom: -50px;
            font-size: 15rem;
            opacity: 0.05;
            transform: rotate(-20deg);
        }
        .login-left .brand-icon {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, #ffd700, #ffb700);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #1a3a6b;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(255,215,0,0.4);
        }
        .login-left h2 { font-weight: 800; margin-bottom: 10px; position: relative; z-index: 1; }
        .login-left p { opacity: 0.8; font-size: 0.95rem; margin-bottom: 30px; position: relative; z-index: 1; }
        .login-right { padding: 60px 45px; }
        .login-right h3 { font-weight: 800; color: #1a2a4a; margin-bottom: 8px; }
        .login-right .subtitle { color: #6c757d; font-size: 0.9rem; margin-bottom: 35px; }
        .form-label {
            font-weight: 600;
            color: #1a2a4a;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
            color: #6c757d;
            border-radius: 12px 0 0 12px;
        }
        .form-control {
            border: 2px solid #e9ecef;
            border-left: none;
            border-radius: 0 12px 12px 0;
            padding: 12px 15px;
            font-size: 0.95rem;
        }
        .form-control:focus {
            border-color: #1a3a6b;
            box-shadow: 0 0 0 4px rgba(26,58,107,0.1);
        }
        .btn-login {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 700;
            width: 100%;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26,58,107,0.4);
            color: white;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: none;
            border-left: 4px solid #dc3545;
            border-radius: 10px;
            font-size: 0.9rem;
        }
        @media (max-width: 768px) {
            .login-container { grid-template-columns: 1fr; }
            .login-left { padding: 40px 30px; }
            .login-left::before { font-size: 10rem; }
            .login-right { padding: 40px 30px; }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-left">
        <div class="brand-icon"><i class="fas fa-user-shield"></i></div>
        <h2>Admin Panel</h2>
        <p>Dormitory Management System — The Polytechnic Ibadan</p>
    </div>
    <div class="login-right">
        <h3>Welcome Back</h3>
        <p class="subtitle">Sign in to access the admin dashboard</p>

        <?php if ($error): ?>
            <div class="alert alert-danger mb-4">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="admin@dormitory.com" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" id="passwordField" class="form-control" placeholder="Enter your password" required>
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()" style="border:2px solid #e9ecef;border-left:none;border-radius:0 12px 12px 0;">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Sign In
            </button>
        </form>

        <p class="text-center text-muted mt-4" style="font-size:0.8rem;">
            <i class="fas fa-lock me-1"></i> Authorized personnel only
        </p>
    </div>
</div>

<script>
function togglePassword() {
    const f = document.getElementById('passwordField');
    const i = document.getElementById('eyeIcon');
    if (f.type === 'password') {
        f.type = 'text';
        i.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        f.type = 'password';
        i.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>