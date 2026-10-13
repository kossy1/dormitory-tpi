<?php
session_start();
include '../config/db.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // For demo — in production use proper users table + hashed passwords
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_name'] = 'Administrator';
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f0c29">
    <title>Admin Login - Dormitory MS</title>
    
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ========== GLOBAL RESET ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            min-height: 100%;
            width: 100%;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            background-size: 300% 300%;
            animation: gradientShift 15s ease infinite;
            padding: 20px;
            overflow-x: hidden;
            position: relative;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* ========== ANIMATED BACKGROUND ========== */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .bg-animation .circle {
            position: absolute;
            border-radius: 50%;
            animation: float 20s infinite ease-in-out;
            opacity: 0.12;
            will-change: transform;
        }

        .bg-animation .circle:nth-child(1) {
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, #ffd700, transparent 70%);
            top: -80px;
            left: -80px;
            animation-delay: 0s;
        }

        .bg-animation .circle:nth-child(2) {
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, #0d6efd, transparent 70%);
            bottom: -120px;
            right: -120px;
            animation-delay: -5s;
        }

        .bg-animation .circle:nth-child(3) {
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, #20c997, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: -10s;
        }

        .bg-animation .circle:nth-child(4) {
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, #e83e8c, transparent 70%);
            top: 20%;
            right: 10%;
            animation-delay: -7s;
        }

        .bg-animation .circle:nth-child(5) {
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, #6f42c1, transparent 70%);
            bottom: 20%;
            left: 10%;
            animation-delay: -13s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(60px, -60px) scale(1.15); }
            50% { transform: translate(-40px, 60px) scale(0.9); }
            75% { transform: translate(50px, -40px) scale(1.08); }
        }

        /* ========== FLOATING PARTICLES ========== */
        .particles {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 215, 0, 0.5);
            border-radius: 50%;
            animation: riseUp 15s linear infinite;
            will-change: transform, opacity;
        }

        @keyframes riseUp {
            0% {
                transform: translateY(100vh) scale(0);
                opacity: 0;
            }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% {
                transform: translateY(-100vh) scale(1);
                opacity: 0;
            }
        }

        /* ========== BACK TO HOMEPAGE LINK ========== */
        .back-home-top {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 100;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 50px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            animation: fadeInLeft 0.6s ease;
        }

        .back-home-top:hover,
        .back-home-top:focus {
            background: rgba(255, 215, 0, 0.2);
            border-color: rgba(255, 215, 0, 0.4);
            color: #ffd700;
            transform: translateX(-4px);
            outline: none;
        }

        .back-home-top i { font-size: 0.9rem; }

        @keyframes fadeInLeft {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* ========== LOGIN CONTAINER ========== */
        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            animation: slideUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ========== LOGIN CARD ========== */
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px 35px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.1) inset;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        /* Subtle shine on card */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.7s ease;
            pointer-events: none;
        }

        .login-card:hover::before {
            left: 100%;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.6),
                        0 0 0 1px rgba(255, 215, 0, 0.2) inset;
        }

        /* ========== LOGO ========== */
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
            animation: fadeIn 0.8s ease 0.2s backwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .login-logo .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2.5rem;
            color: #ffd700;
            box-shadow: 0 10px 30px rgba(26, 58, 107, 0.3),
                        0 0 0 6px rgba(255, 215, 0, 0.1);
            animation: pulse 2.5s infinite;
            position: relative;
        }

        /* Pulsing ring */
        .login-logo .logo-icon::after {
            content: '';
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            border: 2px solid rgba(255, 215, 0, 0.4);
            animation: ripple 2s ease-out infinite;
        }

        @keyframes ripple {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(1.4); opacity: 0; }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.06); }
        }

        .login-logo h2 {
            font-weight: 800;
            color: #1a2a4a;
            margin: 0;
            font-size: 1.8rem;
            animation: fadeInUp 0.7s ease 0.3s backwards;
        }

        .login-logo h2 span { color: #0d6efd; }

        .login-logo .subtitle {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 4px;
            font-weight: 400;
            animation: fadeInUp 0.7s ease 0.4s backwards;
        }

        .login-logo .subtitle i { color: #ffd700; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ========== FORM STYLES ========== */
        .login-card .login-box-msg {
            text-align: center;
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 25px;
            font-weight: 400;
            animation: fadeInUp 0.7s ease 0.5s backwards;
        }

        .input-group {
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 18px;
            transition: all 0.3s ease;
            animation: fadeInUp 0.7s ease backwards;
        }

        .input-group:nth-of-type(1) { animation-delay: 0.6s; }
        .input-group:nth-of-type(2) { animation-delay: 0.7s; }

        .input-group:focus-within {
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.15);
            transform: translateY(-2px);
        }

        .input-group .form-control {
            border: 2px solid #e9ecef;
            border-right: none;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            height: 52px;
            background: #f8f9fa;
            outline: none;
        }

        .input-group .form-control:focus {
            border-color: #0d6efd;
            box-shadow: none;
            background: white;
        }

        .input-group .form-control:focus + .input-group-text {
            border-color: #0d6efd;
            background: white;
        }

        .input-group .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-left: none;
            padding: 0 18px;
            color: #6c757d;
            transition: all 0.3s ease;
            height: 52px;
        }

        .input-group .input-group-text i { font-size: 1.1rem; }

        .input-group:focus-within .input-group-text {
            border-color: #0d6efd;
            background: white;
            color: #0d6efd;
        }

        /* ========== BUTTONS ========== */
        .btn-primary {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            background-size: 200% 200%;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(26, 58, 107, 0.3);
            height: 52px;
            width: 100%;
            color: white;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.7s ease 0.8s backwards;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            background-position: right center;
            box-shadow: 0 8px 30px rgba(26, 58, 107, 0.4);
            color: white;
        }

        .btn-primary:active { transform: translateY(0); }

        .btn-primary i { margin-right: 10px; }

        .btn-primary::after {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            width: 0; height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.25);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-primary:active::after {
            width: 300px;
            height: 300px;
        }

        .btn-outline-dark {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            color: #1a2a4a;
            background: transparent;
            margin-top: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            animation: fadeInUp 0.7s ease 0.9s backwards;
        }

        .btn-outline-dark:hover {
            background: #f8f9fa;
            border-color: #ced4da;
            transform: translateY(-2px);
            color: #1a2a4a;
        }

        /* ========== ALERT ========== */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 14px 18px;
            animation: shake 0.5s ease;
            background: #fde8e8;
            color: #721c24;
            border-left: 4px solid #dc3545;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        /* ========== FOOTER ========== */
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.8rem;
            position: relative;
            z-index: 1;
            animation: fadeIn 1s ease 1s backwards;
            padding: 0 10px;
        }

        .login-footer a {
            color: rgba(255, 255, 255, 0.75);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .login-footer a:hover { color: #ffd700; }

        /* ========== LOADING STATE ========== */
        .btn-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.9;
        }

        .btn-loading i { visibility: hidden; }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 22px;
            height: 22px;
            top: 50%;
            left: 50%;
            margin-top: -11px;
            margin-left: -11px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ========== PASSWORD TOGGLE ========== */
        .password-toggle {
            cursor: pointer;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-left: none;
            padding: 0 18px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            height: 52px;
            color: #6c757d;
            user-select: none;
        }

        .password-toggle:hover { background: #e9ecef; }
        .password-toggle i { font-size: 1.1rem; }

        /* ========== RESPONSIVE: TABLETS (≤ 768px) ========== */
        @media (max-width: 768px) {
            body { padding: 16px; }

            .login-container { max-width: 400px; }

            .login-card { padding: 35px 28px; }
        }

        /* ========== RESPONSIVE: MOBILE (≤ 576px) ========== */
        @media (max-width: 576px) {
            body {
                padding: 12px;
                align-items: center;
                min-height: 100dvh;
            }

            .back-home-top {
                top: 12px;
                left: 12px;
                padding: 8px 14px;
                font-size: 0.78rem;
            }

            .back-home-top .back-text { display: none; }
            .back-home-top i { font-size: 1rem; }

            .login-container {
                max-width: 100%;
                width: 100%;
            }

            .login-card {
                padding: 30px 22px;
                border-radius: 20px;
            }

            .login-logo { margin-bottom: 22px; }

            .login-logo .logo-icon {
                width: 68px;
                height: 68px;
                font-size: 2.1rem;
                box-shadow: 0 8px 25px rgba(26, 58, 107, 0.35),
                            0 0 0 5px rgba(255, 215, 0, 0.1);
            }

            .login-logo h2 { font-size: 1.5rem; }
            .login-logo .subtitle { font-size: 0.82rem; }

            .login-card .login-box-msg {
                font-size: 0.88rem;
                margin-bottom: 20px;
            }

            .input-group .form-control {
                height: 48px;
                padding: 10px 14px;
                font-size: 0.92rem;
            }

            .input-group .input-group-text {
                height: 48px;
                padding: 0 14px;
            }

            .input-group .input-group-text i { font-size: 1rem; }

            .password-toggle {
                height: 48px;
                padding: 0 14px;
            }

            .btn-primary {
                height: 48px;
                font-size: 0.95rem;
                padding: 12px;
            }

            .btn-outline-dark {
                height: 48px;
                font-size: 0.88rem;
                padding: 10px;
            }

            .alert {
                font-size: 0.85rem;
                padding: 12px 14px;
                margin-bottom: 16px;
            }

            .login-footer { font-size: 0.75rem; }

            /* Reduce circle sizes on mobile */
            .bg-animation .circle:nth-child(1) { width: 180px; height: 180px; }
            .bg-animation .circle:nth-child(2) { width: 220px; height: 220px; }
            .bg-animation .circle:nth-child(3) { width: 120px; height: 120px; }
            .bg-animation .circle:nth-child(4),
            .bg-animation .circle:nth-child(5) { display: none; }
        }

        /* ========== RESPONSIVE: SMALL MOBILE (≤ 400px) ========== */
        @media (max-width: 400px) {
            body { padding: 10px; }

            .back-home-top {
                top: 10px;
                left: 10px;
                padding: 7px 12px;
            }

            .login-card {
                padding: 25px 18px;
                border-radius: 18px;
            }

            .login-logo .logo-icon {
                width: 58px;
                height: 58px;
                font-size: 1.7rem;
            }

            .login-logo h2 { font-size: 1.3rem; }
            .login-logo .subtitle { font-size: 0.76rem; }

            .login-card .login-box-msg {
                font-size: 0.82rem;
                margin-bottom: 16px;
            }

            .input-group .form-control {
                height: 44px;
                font-size: 0.88rem;
                padding: 8px 12px;
            }

            .input-group .input-group-text {
                height: 44px;
                padding: 0 12px;
            }

            .password-toggle {
                height: 44px;
                padding: 0 12px;
            }

            .btn-primary {
                height: 44px;
                font-size: 0.9rem;
                padding: 10px;
            }

            .btn-outline-dark {
                height: 44px;
                font-size: 0.84rem;
                padding: 8px;
            }

            .alert {
                font-size: 0.8rem;
                padding: 10px 12px;
            }

            .login-footer {
                font-size: 0.7rem;
                line-height: 1.5;
            }
        }

        /* ========== LANDSCAPE MOBILE ========== */
        @media (max-height: 500px) and (orientation: landscape) {
            body {
                align-items: flex-start;
                padding-top: 60px;
                padding-bottom: 30px;
            }

            .login-card { padding: 25px 30px; }
            .login-logo { margin-bottom: 18px; }
            .login-logo .logo-icon { width: 55px; height: 55px; font-size: 1.6rem; }
            .login-logo h2 { font-size: 1.3rem; }
            .login-card .login-box-msg { margin-bottom: 15px; }
        }

        /* ========== REDUCED MOTION ========== */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>

<!-- ====== ANIMATED BACKGROUND ====== -->
<div class="bg-animation">
    <div class="circle"></div>
    <div class="circle"></div>
    <div class="circle"></div>
    <div class="circle"></div>
    <div class="circle"></div>
</div>

<!-- ====== FLOATING PARTICLES ====== -->
<div class="particles" id="particles"></div>

<!-- ====== BACK TO HOMEPAGE (top-left) ====== -->
<a href="../index.php" class="back-home-top">
    <i class="fas fa-arrow-left"></i>
    <span class="back-text">Back to Homepage</span>
</a>

<!-- ====== LOGIN CONTAINER ====== -->
<div class="login-container">
    <div class="login-card">
        
        <!-- Logo -->
        <div class="login-logo">
            <div class="logo-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2>Admin <span>Panel</span></h2>
            <p class="subtitle">
                <i class="fas fa-user-shield me-1"></i>
                Dormitory Management System
            </p>
        </div>

        <!-- Message -->
        <p class="login-box-msg">
            <i class="fas fa-lock me-2"></i>Sign in to start your session
        </p>

        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="post" id="loginForm">
            
            <!-- Username Field -->
            <div class="input-group">
                <input type="text" class="form-control" name="username" 
                       placeholder="Username" required autofocus
                       autocomplete="username">
                <span class="input-group-text">
                    <i class="fas fa-user"></i>
                </span>
            </div>

            <!-- Password Field -->
            <div class="input-group">
                <input type="password" class="form-control" name="password" 
                       id="password" placeholder="Password" required
                       autocomplete="current-password">
                <span class="input-group-text password-toggle" id="togglePassword" role="button" tabindex="0" aria-label="Toggle password visibility">
                    <i class="fas fa-eye" id="passwordIcon"></i>
                </span>
            </div>

            <!-- Submit Button -->
            <div class="row mt-3">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </div>
            </div>

            <!-- Back to Homepage (in-form) -->
            <div class="row mt-2">
                <div class="col-12">
                    <a href="../index.php" class="btn btn-outline-dark">
                        <i class="fas fa-arrow-left"></i> Back to Homepage
                    </a>
                </div>
            </div>

        </form>

        <!-- Demo Credentials -->
        <div class="mt-3 text-center">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Demo: <strong>admin</strong> / <strong>admin123</strong>
            </small>
        </div>

    </div>
</div>

<!-- ====== FOOTER ====== -->
<div class="login-footer">
    <p class="mb-0">
        &copy; <?= date('Y') ?> <a href="../index.php">Dormitory Management System</a> 
        <span class="mx-1">•</span> The Polytechnic Ibadan
    </p>
</div>

<!-- ====== SCRIPTS ====== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ====== FLOATING PARTICLES GENERATION ======
    (function createParticles() {
        const container = document.getElementById('particles');
        if (!container) return;

        // Reduce count on mobile for performance
        const isMobile = window.innerWidth <= 576;
        const count = isMobile ? 12 : 25;

        for (let i = 0; i < count; i++) {
            const p = document.createElement('div');
            p.className = 'particle';
            p.style.left = Math.random() * 100 + '%';
            p.style.animationDelay = Math.random() * 15 + 's';
            p.style.animationDuration = (12 + Math.random() * 8) + 's';

            // Random sizes
            const size = 2 + Math.random() * 4;
            p.style.width = size + 'px';
            p.style.height = size + 'px';

            // Slight color variety
            const colors = [
                'rgba(255, 215, 0, 0.6)',
                'rgba(13, 110, 253, 0.5)',
                'rgba(32, 201, 151, 0.5)',
                'rgba(232, 62, 140, 0.5)',
            ];
            p.style.background = colors[Math.floor(Math.random() * colors.length)];

            container.appendChild(p);
        }
    })();

    // ====== PASSWORD TOGGLE ======
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    const passwordIcon = document.getElementById('passwordIcon');

    function togglePasswordVisibility() {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        passwordIcon.classList.toggle('fa-eye');
        passwordIcon.classList.toggle('fa-eye-slash');
    }

    if (togglePassword) {
        togglePassword.addEventListener('click', togglePasswordVisibility);
        togglePassword.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                togglePasswordVisibility();
            }
        });
    }

    // ====== FORM SUBMIT LOADING ======
    const form = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');

    if (form) {
        form.addEventListener('submit', function(e) {
            loginBtn.classList.add('btn-loading');
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
            loginBtn.disabled = true;
        });
    }

    // ====== KEYBOARD SHORTCUT (Enter to submit) ======
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const activeElement = document.activeElement;
            if (activeElement && activeElement.tagName === 'INPUT') {
                const f = activeElement.closest('form');
                if (f) f.submit();
            }
        }
    });

    // ====== INPUT FOCUS ANIMATION ======
    document.querySelectorAll('.form-control').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'translateY(-2px)';
        });
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'translateY(0)';
        });
    });

    console.log('✅ Admin Login page initialized');
});
</script>

</body>
</html>