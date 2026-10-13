<?php
session_start();
include '../config/db.php';

$errors = [];
$success = '';
$verified_student = null;

// Redirect if already logged in
if (isset($_SESSION['student_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matric_no = strtoupper(trim($_POST['matric_no'] ?? ''));

    if (empty($matric_no)) {
        $errors[] = "Matric number is required.";
    } elseif (empty($_POST['terms_accepted'])) {
        $errors[] = "You must accept the Terms and Conditions to continue.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            SELECT s.*, f.name AS faculty_name, d.name AS department_name
            FROM students s
            LEFT JOIN faculties f ON s.faculty_id = f.id
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE s.matric_no = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $matric_no);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $errors[] = "No student found with this matric number. Please contact the Student Affairs Unit.";
        } else {
            $verified_student = $result->fetch_assoc();

            // Check if student already logged in before (has last_login set)
            if (!empty($verified_student['last_login'])) {
                $errors[] = "This account has already been activated. Please login with your matric number and surname.";
            } elseif (empty($verified_student['surname'])) {
                $errors[] = "Your account setup is incomplete. Please contact the Student Affairs Unit.";
            } elseif ($verified_student['status'] !== 'active') {
                $errors[] = "Your account is not active. Please contact the Student Affairs Unit.";
            } else {
                // Mark as activated by setting last_login
                $update = $conn->prepare("UPDATE students SET last_login = NOW() WHERE id = ?");
                $update->bind_param("i", $verified_student['id']);

                if ($update->execute()) {
                    // Auto-login
                    session_regenerate_id(true);
                    $_SESSION['student_id'] = $verified_student['id'];
                    $_SESSION['full_name']  = $verified_student['full_name'];
                    $_SESSION['matric_no']  = $verified_student['matric_no'];
                    $_SESSION['last_activity'] = time();
                    $_SESSION['flash_success'] = "Welcome, {$verified_student['full_name']}! Browse available rooms to select your accommodation.";

                    header("Location: browse-rooms.php");
                    exit;
                } else {
                    $errors[] = "Failed to complete activation. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1a3a6b">
    <title>Student Activation - The Polytechnic Ibadan</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a3a6b 0%, #0d1b3e 100%);
            min-height: 100vh;
            min-height: 100dvh;
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

        /* Back to Homepage */
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
        .back-home-top:hover {
            background: rgba(255, 215, 0, 0.2);
            border-color: rgba(255, 215, 0, 0.4);
            color: #ffd700;
            transform: translateX(-4px);
        }
        @keyframes fadeInLeft {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @media (max-width: 576px) {
            .back-home-top { top: 12px; left: 12px; padding: 8px 14px; font-size: 0.78rem; }
            .back-home-top .back-text { display: none; }
        }

        .register-container {
            width: 100%;
            max-width: 720px;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.6s ease;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .register-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.4);
        }

        .register-header {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .register-header::after {
            content: '\f19c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: -20px;
            bottom: -30px;
            font-size: 8rem;
            opacity: 0.05;
            transform: rotate(-15deg);
        }
        .register-header .logo-circle {
            width: 75px;
            height: 75px;
            margin: 0 auto 15px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            position: relative;
            z-index: 1;
        }
        .register-header .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .register-header h2 {
            font-weight: 800;
            font-size: 1.5rem;
            margin: 0 0 5px;
            position: relative;
            z-index: 1;
        }
        .register-header .subtitle {
            font-size: 0.88rem;
            opacity: 0.85;
            position: relative;
            z-index: 1;
        }
        .register-header .motto {
            font-size: 0.75rem;
            font-style: italic;
            color: #ffd700;
            margin-top: 6px;
            position: relative;
            z-index: 1;
        }

        .register-body { padding: 40px 35px; }

        /* Steps */
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #adb5bd;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .step .step-num {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            transition: all 0.3s ease;
        }
        .step.active .step-num {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            color: white;
            box-shadow: 0 4px 15px rgba(26,58,107,0.4);
            transform: scale(1.1);
        }
        .step.active { color: #1a3a6b; }
        .step.done .step-num { background: #28a745; color: white; }
        .step.done { color: #28a745; }
        .step-line {
            width: 60px;
            height: 2px;
            background: #e9ecef;
            margin: 0 8px;
            align-self: center;
        }
        .step-line.done { background: #28a745; }
        @media (max-width: 576px) {
            .step-line { width: 30px; }
            .step span:not(.step-num) { display: none; }
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
            padding: 12px 16px;
            font-size: 0.95rem;
            background: #fafbfc;
            transition: all 0.3s ease;
            height: 52px;
        }
        .form-control:focus {
            border-color: #1a3a6b;
            box-shadow: 0 0 0 4px rgba(26,58,107,0.12);
            background: white;
        }

        /* Matric field */
        .matric-input-wrap { position: relative; }
        .matric-input {
            font-size: 1.15rem !important;
            font-weight: 700;
            letter-spacing: 1px;
            text-align: center;
            height: 62px !important;
            text-transform: uppercase;
            padding-right: 55px !important;
        }
        .matric-check-icon {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.4rem;
            color: #adb5bd;
            transition: all 0.3s ease;
            pointer-events: none;
        }
        .matric-input-wrap.verified .matric-check-icon { color: #28a745; }
        .matric-input-wrap.loading .matric-check-icon {
            color: #0d6efd;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

        /* Verified card */
        .verified-card {
            display: none;
            background: linear-gradient(135deg, #f0f9f0, #e8f5e9);
            border: 2px solid #28a745;
            border-radius: 16px;
            padding: 22px;
            margin: 22px 0;
            animation: slideDown 0.5s ease;
        }
        .verified-card.show { display: block; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .verified-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px dashed #28a745;
        }
        .verified-header .check-icon {
            width: 45px; height: 45px;
            border-radius: 50%;
            background: #28a745; color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }
        .verified-header h5 { margin: 0; color: #155724; font-weight: 800; font-size: 1.1rem; }
        .verified-header p { margin: 0; color: #28a745; font-size: 0.85rem; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 12px;
        }
        .info-item {
            background: white;
            padding: 10px 14px;
            border-radius: 10px;
            border-left: 4px solid #28a745;
        }
        .info-item .info-label {
            font-size: 0.7rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .info-item .info-value {
            font-size: 0.92rem;
            color: #1a2a4a;
            font-weight: 700;
            word-break: break-word;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 22px;
            margin-bottom: 22px;
        }
        .alert-danger {
            background: #fde8e8; color: #721c24;
            border-left: 5px solid #dc3545;
        }
        .alert-info {
            background: #e7f3ff; color: #0c5460;
            border-left: 5px solid #0d6efd;
        }
        .alert i { margin-right: 8px; }
        .alert ul { margin: 8px 0 0; padding-left: 22px; }

        .btn-primary {
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
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26, 58, 107, 0.5);
            color: white;
        }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary i { margin-right: 8px; }

        .btn-login-link {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 13px;
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
        .btn-login-link:hover {
            background: #f8f9fa;
            border-color: #1a3a6b;
            color: #1a3a6b;
        }
        .btn-login-link i { margin-right: 8px; }

        .hidden { display: none !important; }

        .spinner-inline {
            display: inline-block;
            width: 18px; height: 18px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
        }

        .register-footer {
            text-align: center;
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
            margin-top: 25px;
            position: relative;
            z-index: 1;
        }
        .register-footer a { color: #ffd700; text-decoration: none; font-weight: 600; }
        .register-footer a:hover { text-decoration: underline; }

        /* Activation info box */
        .activation-info {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 18px;
            background: #e7f3ff;
            border-left: 4px solid #0d6efd;
            border-radius: 10px;
            margin-bottom: 18px;
        }
        .activation-info i {
            font-size: 1.3rem;
            color: #0d6efd;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .activation-info .info-text {
            font-size: 0.88rem;
            color: #084298;
            line-height: 1.5;
        }
        .activation-info .info-text strong { color: #1a3a6b; }

        @media (max-width: 576px) {
            body { padding: 10px; }
            .register-header { padding: 22px 20px; }
            .register-header h2 { font-size: 1.2rem; }
            .register-header::after { font-size: 5rem; }
            .register-body { padding: 25px 18px; }
            .info-grid { grid-template-columns: 1fr; }
            .matric-input { font-size: 1rem !important; height: 55px !important; }
            .step { font-size: 0.8rem; gap: 6px; }
            .step .step-num { width: 32px; height: 32px; font-size: 0.9rem; }
        }
        @media (max-width: 400px) {
            .register-header h2 { font-size: 1.05rem; }
            .register-body { padding: 20px 14px; }
            .matric-input { font-size: 0.92rem !important; }
        }

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>

<!-- Back to Homepage -->
<a href="../index.php" class="back-home-top">
    <i class="fas fa-arrow-left"></i>
    <span class="back-text">Back to Homepage</span>
</a>

<div class="register-container">
    <div class="register-card">
        <div class="register-header">
            <div class="logo-circle">
                <img src="../assets/images/logo.png" onerror="this.src='https://via.placeholder.com/75x75/1a3a6b/ffffff?text=PI'">
            </div>
            <h2>Student Activation</h2>
            <div class="subtitle">Dormitory Management System</div>
            <div class="motto"><i class="fas fa-star"></i> Excellence in Technology and Innovation</div>
        </div>

        <div class="register-body">

            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step active" id="step1">
                    <span class="step-num">1</span>
                    <span>Verify Matric</span>
                </div>
                <div class="step-line" id="line1"></div>
                <div class="step" id="step2">
                    <span class="step-num">2</span>
                    <span>Confirm</span>
                </div>
                <div class="step-line" id="line2"></div>
                <div class="step" id="step3">
                    <span class="step-num">3</span>
                    <span>Complete</span>
                </div>
            </div>

            <!-- Errors -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Please fix the following:</strong>
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Info Banner -->
            <div class="activation-info">
                <i class="fas fa-info-circle"></i>
                <div class="info-text">
                    <strong>Activating your account is easy!</strong><br>
                    Enter your <strong>matric number</strong> to fetch your pre-registered details, then confirm to get started.
                    You'll log in later with your <strong>matric number + surname</strong>.
                </div>
            </div>

            <!-- Form -->
            <form method="POST" id="registerForm" autocomplete="off">

                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-id-card"></i>Matric Number <span class="text-danger">*</span>
                    </label>
                    <div class="matric-input-wrap" id="matricWrap">
                        <input type="text" 
                               name="matric_no" 
                               id="matricInput"
                               class="form-control matric-input" 
                               required 
                               placeholder="Enter your matric number"
                               value="<?= htmlspecialchars($_POST['matric_no'] ?? '') ?>"
                               autocomplete="off">
                        <i class="fas fa-search matric-check-icon" id="matricIcon"></i>
                    </div>
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Type your matric number exactly as registered by the Student Affairs Unit.
                    </small>
                </div>

                <!-- Verified student card -->
                <div class="verified-card" id="verifiedCard">
                    <div class="verified-header">
                        <div class="check-icon"><i class="fas fa-check"></i></div>
                        <div>
                            <h5>Student Verified ✓</h5>
                            <p>Please review your details below</p>
                        </div>
                    </div>
                    <div class="info-grid" id="studentInfoGrid">
                        <!-- filled by JS -->
                    </div>
                </div>

                <!-- Confirm section (no password!) -->
                <div id="confirmSection" class="hidden">
                    <hr style="border-top: 2px dashed #e9ecef; margin: 25px 0;">

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="terms_accepted" id="termsCheck" value="1">
                            <label class="form-check-label" for="termsCheck" style="font-size:0.9rem; line-height:1.6;">
                                I confirm that the details above are correct, and I agree to the 
                                <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> 
                                of The Polytechnic Ibadan Dormitory Management System
                            </label>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                        <button type="submit" class="btn btn-primary" id="registerBtn" disabled>
                            <i class="fas fa-user-check"></i> Complete Activation
                        </button>
                    </div>
                </div>

                <a href="login.php" class="btn-login-link">
                    <i class="fas fa-sign-in-alt"></i>Already activated? Login here
                </a>
            </form>
        </div>
    </div>

    <div class="register-footer">
        &copy; <?= date('Y') ?> <a href="../index.php">Dormitory Management System</a> • The Polytechnic Ibadan
    </div>
</div>

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:16px; border:none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #1a3a6b, #0d1b3e); color:white; border-radius:16px 16px 0 0;">
                <h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>Terms and Conditions</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>1. Acceptance of Terms</h6>
                <p>By activating your account on the Dormitory Management System of The Polytechnic Ibadan, you agree to comply with all rules and regulations of the institution.</p>
                <h6>2. Eligibility</h6>
                <p>You must be a registered student of The Polytechnic Ibadan to be eligible for dormitory allocation.</p>
                <h6>3. Login Credentials</h6>
                <p>You will log in using your <strong>matric number</strong> and your <strong>surname</strong>. Keep your matric number confidential.</p>
                <h6>4. Payment</h6>
                <p>Room allocation is subject to payment of applicable fees. Failure to pay may result in cancellation of allocation.</p>
                <h6>5. Conduct</h6>
                <p>Students are expected to maintain good conduct and adhere to hostel rules and regulations.</p>
                <h6>6. Data Privacy</h6>
                <p>Your personal data will be protected and used only for dormitory management purposes.</p>
                <h6>7. Room Allocation</h6>
                <p>Rooms are allocated on a first-come, first-served basis after successful payment.</p>
                <h6>8. Cancellation</h6>
                <p>The institution reserves the right to cancel any allocation due to policy violations.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="border-radius:10px; background: linear-gradient(135deg, #1a3a6b, #0d1b3e); border:none; padding: 10px 24px; color:white;">I Understand</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const matricInput = document.getElementById('matricInput');
    const matricWrap  = document.getElementById('matricWrap');
    const matricIcon  = document.getElementById('matricIcon');
    const verifiedCard = document.getElementById('verifiedCard');
    const studentInfoGrid = document.getElementById('studentInfoGrid');
    const confirmSection = document.getElementById('confirmSection');
    const registerBtn = document.getElementById('registerBtn');
    const termsCheck = document.getElementById('termsCheck');
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');
    const line1 = document.getElementById('line1');
    const line2 = document.getElementById('line2');

    let verifiedStudent = null;
    let debounceTimer = null;

    // ===== MATRIC INPUT =====
    matricInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
        clearTimeout(debounceTimer);
        const matric = this.value.trim();

        // Reset state if user changes input after verification
        if (verifiedStudent) {
            verifiedStudent = null;
            verifiedCard.classList.remove('show');
            confirmSection.classList.add('hidden');
            registerBtn.disabled = true;
            matricWrap.classList.remove('verified', 'loading');
            matricIcon.className = 'fas fa-search matric-check-icon';
            matricIcon.style.color = '';
            step1.classList.add('active'); step1.classList.remove('done');
            step2.classList.remove('active', 'done');
            step3.classList.remove('active', 'done');
            line1.classList.remove('done');
            line2.classList.remove('done');
        }

        if (matric.length < 3) {
            matricWrap.classList.remove('loading');
            matricIcon.className = 'fas fa-search matric-check-icon';
            return;
        }

        matricWrap.classList.add('loading');
        matricWrap.classList.remove('verified');
        matricIcon.className = 'fas fa-sync-alt matric-check-icon';

        debounceTimer = setTimeout(() => fetchStudent(matric), 600);
    });

    matricInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(debounceTimer);
            fetchStudent(this.value.trim());
        }
    });

    // ===== FETCH STUDENT =====
    function fetchStudent(matric) {
        if (!matric) return;
        fetch(`fetch_student.php?matric_no=${encodeURIComponent(matric)}`)
            .then(res => res.json())
            .then(data => {
                matricWrap.classList.remove('loading');
                if (!data.success) {
                    matricWrap.classList.remove('verified');
                    matricIcon.className = 'fas fa-times-circle matric-check-icon';
                    matricIcon.style.color = '#dc3545';
                    verifiedCard.classList.remove('show');
                    confirmSection.classList.add('hidden');
                    registerBtn.disabled = true;
                    showToast(data.message, 'danger');
                    return;
                }

                verifiedStudent = data.student;
                matricWrap.classList.add('verified');
                matricIcon.className = 'fas fa-check-circle matric-check-icon';
                matricIcon.style.color = '#28a745';
                renderStudentCard(verifiedStudent);
                verifiedCard.classList.add('show');
                confirmSection.classList.remove('hidden');

                // Update register button based on terms checkbox
                registerBtn.disabled = !termsCheck.checked;

                step1.classList.remove('active'); step1.classList.add('done');
                step2.classList.add('active');
                line1.classList.add('done');

                setTimeout(() => confirmSection.scrollIntoView({ behavior: 'smooth', block: 'center' }), 200);
            })
            .catch(err => {
                matricWrap.classList.remove('loading');
                matricIcon.className = 'fas fa-exclamation-circle matric-check-icon';
                matricIcon.style.color = '#dc3545';
                showToast('Failed to fetch student data.', 'danger');
                console.error(err);
            });
    }

    // ===== RENDER STUDENT CARD =====
    function renderStudentCard(student) {
        const levelMap = { 'ND1': 'ND I', 'ND2': 'ND II', 'HND1': 'HND I', 'HND2': 'HND II' };
        const levelDisplay = levelMap[student.level] || student.level || 'N/A';
        studentInfoGrid.innerHTML = `
            <div class="info-item">
                <div class="info-label"><i class="fas fa-user"></i> Full Name</div>
                <div class="info-value">${escapeHtml(student.full_name)}</div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-id-card"></i> Matric Number</div>
                <div class="info-value">${escapeHtml(student.matric_no)}</div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                <div class="info-value">${escapeHtml(student.email || 'N/A')}</div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-phone"></i> Phone</div>
                <div class="info-value">${escapeHtml(student.phone || 'N/A')}</div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-venus-mars"></i> Gender</div>
                <div class="info-value">${escapeHtml(student.gender || 'N/A')}</div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-graduation-cap"></i> Level</div>
                <div class="info-value">${escapeHtml(levelDisplay)}</div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-building"></i> Faculty</div>
                <div class="info-value">${escapeHtml(student.faculty_name || 'N/A')}</div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-book"></i> Department</div>
                <div class="info-value">${escapeHtml(student.department_name || 'N/A')}</div>
            </div>
        `;
    }

    // ===== ESCAPE HTML =====
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ===== TOAST =====
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type}`;
        toast.style.cssText = 'position:fixed;top:20px;right:20px;left:20px;z-index:9999;max-width:400px;margin-left:auto;box-shadow:0 10px 30px rgba(0,0,0,0.2);';
        toast.innerHTML = `<i class="fas fa-${type === 'danger' ? 'exclamation-triangle' : 'info-circle'}"></i> ${escapeHtml(message)}`;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.transition = 'opacity 0.5s';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 500);
        }, 4500);
    }

    // ===== TERMS CHECKBOX → ENABLE BUTTON =====
    termsCheck.addEventListener('change', function() {
        if (verifiedStudent) {
            registerBtn.disabled = !this.checked;
        }
    });

    // ===== FORM SUBMIT =====
    const form = document.getElementById('registerForm');
    form.addEventListener('submit', function(e) {
        if (!verifiedStudent) {
            e.preventDefault();
            showToast('Please verify your matric number first.', 'danger');
            return;
        }
        if (!termsCheck.checked) {
            e.preventDefault();
            showToast('Please accept the Terms and Conditions.', 'danger');
            return;
        }
        registerBtn.disabled = true;
        registerBtn.innerHTML = '<span class="spinner-inline"></span> Activating...';
        step2.classList.remove('active'); step2.classList.add('done');
        line2.classList.add('done');
        step3.classList.add('active');
    });

    // Auto-fetch if page reloaded after POST error
    <?php if (!empty($_POST['matric_no'])): ?>
    setTimeout(() => fetchStudent('<?= addslashes($_POST['matric_no']) ?>'), 300);
    <?php endif; ?>
});
</script>
</body>
</html>