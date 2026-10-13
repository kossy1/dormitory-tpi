<?php
session_start();
include '../config/db.php';

if (isset($_SESSION['student_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$matric_prefill = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matric_no = strtoupper(trim($_POST['matric_no'] ?? ''));
    $surname   = strtoupper(trim($_POST['surname'] ?? ''));
    $matric_prefill = $matric_no;

    if (empty($matric_no) || empty($surname)) {
        $error = "Matric number and surname are required.";
    } else {
        $stmt = $conn->prepare("
            SELECT id, full_name, surname, matric_no, status
            FROM students 
            WHERE matric_no = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $matric_no);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['status'] !== 'active') {
                $error = "Your account has been suspended. Please contact the Student Affairs Unit.";
            }
            elseif (strtoupper(trim($user['surname'])) === $surname) {
                session_regenerate_id(true);
                $_SESSION['student_id'] = $user['id'];
                $_SESSION['full_name']  = $user['full_name'];
                $_SESSION['matric_no']  = $user['matric_no'];
                $_SESSION['last_activity'] = time();

                $conn->query("UPDATE students SET last_login = NOW() WHERE id = {$user['id']}");

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Incorrect surname. Please try again.";
            }
        } else {
            $error = "No student found with this matric number.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a3a6b 0%, #0d1b3e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle at 30% 40%, rgba(255,215,0,0.08) 0%, transparent 40%),
                        radial-gradient(circle at 70% 80%, rgba(13,110,253,0.08) 0%, transparent 40%);
            animation: bgMove 20s linear infinite;
            pointer-events: none;
        }
        @keyframes bgMove {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, -30px); }
        }

        /* ========== BACK TO HOMEPAGE (top-left) ========== */
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
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 50px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            animation: fadeInLeft 0.6s ease;
        }
        .back-home-top:hover {
            background: rgba(255, 215, 0, 0.2);
            border-color: rgba(255, 215, 0, 0.4);
            color: #ffd700;
            transform: translateX(-4px);
        }
        .back-home-top i { font-size: 0.9rem; }

        @keyframes fadeInLeft {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @media (max-width: 576px) {
            .back-home-top {
                top: 12px;
                left: 12px;
                padding: 8px 14px;
                font-size: 0.78rem;
            }
            .back-home-top .back-text { display: none; }
            .back-home-top i { font-size: 1rem; }
        }

        .login-container {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.6s ease;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.4);
        }

        .login-header {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            color: white;
            padding: 35px 30px;
            text-align: center;
        }
        .login-header .logo-circle {
            width: 85px;
            height: 85px;
            margin: 0 auto 15px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        .login-header .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .login-header h2 {
            font-weight: 800;
            font-size: 1.6rem;
            margin: 0 0 5px;
        }
        .login-header .subtitle {
            font-size: 0.9rem;
            opacity: 0.85;
            font-weight: 400;
        }
        .login-header .motto {
            font-size: 0.75rem;
            font-style: italic;
            color: #ffd700;
            margin-top: 8px;
        }

        .login-body {
            padding: 40px 35px;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #1a2a4a;
            margin-bottom: 8px;
        }
        .form-label i { color: #1a3a6b; margin-right: 6px; }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 0.95rem;
            height: 52px;
            background: #fafbfc;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #1a3a6b;
            box-shadow: 0 0 0 4px rgba(26,58,107,0.12);
            background: white;
        }
        .form-control::placeholder { text-transform: none; }

        .btn-login {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-weight: 700;
            font-size: 1.05rem;
            color: white;
            width: 100%;
            height: 56px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(26, 58, 107, 0.3);
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26, 58, 107, 0.5);
            color: white;
        }
        .btn-login i { margin-right: 8px; }

        .btn-register {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            font-size: 0.95rem;
            color: #1a2a4a;
            width: 100%;
            margin-top: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-register:hover {
            background: #f8f9fa;
            border-color: #1a3a6b;
            color: #1a3a6b;
        }
        .btn-register i { margin-right: 8px; }

        .btn-back-home {
            background: transparent;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #6c757d;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-back-home:hover {
            background: #f8f9fa;
            border-color: #1a3a6b;
            color: #1a3a6b;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 14px 20px;
            font-size: 0.9rem;
            margin-bottom: 22px;
            animation: shake 0.5s ease;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }
        .alert-danger {
            background: #fde8e8;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        .alert i { margin-right: 8px; }

        .divider-text {
            text-align: center;
            margin: 25px 0 15px;
            position: relative;
            font-size: 0.8rem;
            color: #adb5bd;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .divider-text::before,
        .divider-text::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: #e9ecef;
        }
        .divider-text::before { left: 0; }
        .divider-text::after { right: 0; }

        .login-footer {
            text-align: center;
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
            margin-top: 25px;
            position: relative;
            z-index: 1;
        }
        .login-footer a { color: #ffd700; text-decoration: none; font-weight: 600; }
        .login-footer a:hover { text-decoration: underline; }

        .demo-hint {
            background: #f0f4fa;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 0.85rem;
            color: #495057;
            text-align: center;
            margin-top: 20px;
            border-left: 4px solid #0d6efd;
        }
        .demo-hint i { color: #0d6efd; margin-right: 5px; }
        .demo-hint strong { color: #1a3a6b; }

        @media (max-width: 576px) {
            body { padding: 10px; }
            .login-header { padding: 25px 20px; }
            .login-header h2 { font-size: 1.3rem; }
            .login-header .logo-circle { width: 70px; height: 70px; }
            .login-body { padding: 25px 20px; }
        }
    </style>
</head>
<body>

<!-- ====== BACK TO HOMEPAGE (top-left floating) ====== -->
<a href="../index.php" class="back-home-top">
    <i class="fas fa-arrow-left"></i>
    <span class="back-text">Back to Homepage</span>
</a>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="logo-circle">
                <img src="../assets/images/logo.png" onerror="this.src='https://via.placeholder.com/85x85/1a3a6b/ffffff?text=PI'">
            </div>
            <h2>Student Login</h2>
            <div class="subtitle">Dormitory Management System</div>
            <div class="motto"><i class="fas fa-star"></i> The Polytechnic Ibadan</div>
        </div>

        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-id-card"></i>Matric Number</label>
                    <input type="text" name="matric_no" class="form-control" 
                           placeholder="e.g. 2024239010098" required 
                           value="<?= htmlspecialchars($matric_prefill) ?>"
                           style="text-transform: uppercase;"
                           autocomplete="username"
                           autofocus>
                </div>

                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-user"></i>Surname</label>
                    <input type="text" name="surname" class="form-control" 
                           placeholder="Enter your surname" required 
                           style="text-transform: uppercase;"
                           autocomplete="off">
                    <small class="text-muted d-block mt-2" style="font-size:0.78rem;">
                        <i class="fas fa-info-circle me-1"></i>
                        Use the surname you registered with (e.g. <strong>OKUEWU</strong>).
                    </small>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>Sign In
                </button>

                <div class="divider-text">OR</div>

                <a href="register.php" class="btn-register">
                    <i class="fas fa-user-plus"></i>Create New Account
                </a>

                <a href="../index.php" class="btn-back-home">
                    <i class="fas fa-arrow-left"></i>
                    Back to Homepage
                </a>
            </form>

            <div class="demo-hint">
                <i class="fas fa-lightbulb"></i>
                <strong>First time?</strong> Use your <strong>matric number</strong> and <strong>surname</strong> to log in.
            </div>
        </div>
    </div>

    <div class="login-footer">
        &copy; <?= date('Y') ?> <a href="../index.php">Dormitory Management System</a> • The Polytechnic Ibadan
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');

    // Auto-uppercase inputs
    document.querySelectorAll('input[type="text"]').forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    });

    form.addEventListener('submit', function() {
        loginBtn.disabled = true;
        loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing in...';
    });
});
</script>
</body>
</html>