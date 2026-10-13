<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';
include_once '../config/functions.php';

$student_id = $_SESSION['student_id'];
$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? $student['full_name']);
    $phone = trim($_POST['phone'] ?? $student['phone']);
    $email = trim($_POST['email'] ?? $student['email']);

    // Validate email if changed
    if ($email !== $student['email']) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check if email already exists for other students
            $check = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
            $check->bind_param("si", $email, $student_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = "Email already in use by another student.";
            }
        }
    }

    // Photo upload
    $photo_path = $student['photo'];
    if (empty($error) && !empty($_FILES['photo']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['photo']['size'] <= 2097152) { // 2MB
            $photo_path = "uploads/" . $student['matric_no'] . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], "../" . $photo_path)) {
                // Delete old photo if exists
                if ($student['photo'] && file_exists("../" . $student['photo'])) {
                    unlink("../" . $student['photo']);
                }
            } else {
                $error = "Failed to upload photo.";
            }
        } else {
            $error = "Invalid photo. Only JPG/PNG under 2MB allowed.";
        }
    }

    // Password change
    $password_sql = "";
    if (empty($error) && !empty($_POST['new_password'])) {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if (password_verify($current_pass, $student['password'])) {
            if ($new_pass === $confirm_pass && strlen($new_pass) >= 6) {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $password_sql = ", password = '$hashed'";
            } else {
                $error = "New passwords do not match or are too short (minimum 6 characters).";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }

    if (empty($error)) {
        $query = "UPDATE students SET full_name = ?, phone = ?, email = ?, photo = ? $password_sql WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssi", $full_name, $phone, $email, $photo_path, $student_id);

        if ($stmt->execute()) {
            $success = "Profile updated successfully!";
            // Refresh data
            $student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();
            // Update session name if changed
            $_SESSION['full_name'] = $student['full_name'];
        } else {
            $error = "Update failed: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Dormitory MS</title>
    
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* ========== GLOBAL RESET ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
            overflow-x: hidden;
        }

        /* ========== SIDEBAR TOGGLE BUTTON ========== */
        .sidebar-toggle-btn {
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 1050;
            background: #1a3a6b;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(26, 58, 107, 0.3);
            transition: all 0.3s ease;
            display: none;
        }

        .sidebar-toggle-btn:hover {
            background: #0d1b3e;
            transform: scale(1.05);
            box-shadow: 0 6px 25px rgba(26, 58, 107, 0.4);
        }

        .sidebar-toggle-btn:active {
            transform: scale(0.95);
        }

        .sidebar-toggle-btn i {
            font-size: 1.4rem;
        }

        /* ========== SIDEBAR OVERLAY ========== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1039;
            animation: fadeIn 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* ========== MAIN SIDEBAR ========== */
        .main-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(180deg, #1a3a6b 0%, #0d1b3e 100%);
            z-index: 1040;
            overflow-y: auto;
            overflow-x: hidden;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 2px 0 30px rgba(0, 0, 0, 0.15);
            padding: 0;
            transform: translateX(-100%);
        }

        .main-sidebar.open {
            transform: translateX(0);
        }

        /* Desktop - always visible */
        @media (min-width: 992px) {
            .main-sidebar {
                transform: translateX(0) !important;
            }
            
            .sidebar-toggle-btn {
                display: none !important;
            }
            
            .sidebar-overlay {
                display: none !important;
            }
            
            .content-wrapper {
                margin-left: 280px;
                transition: margin-left 0.3s ease;
            }
        }

        /* Mobile - hidden by default, shown with toggle */
        @media (max-width: 991.98px) {
            .sidebar-toggle-btn {
                display: block;
            }
            
            .main-sidebar {
                transform: translateX(-100%);
                width: 280px;
                max-width: 85%;
            }
            
            .main-sidebar.open {
                transform: translateX(0);
            }
            
            .content-wrapper {
                margin-left: 0;
                padding-top: 70px;
            }
        }

        /* ========== SIDEBAR SCROLLBAR ========== */
        .main-sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .main-sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .main-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        /* ========== SIDEBAR BRAND ========== */
        .sidebar-brand {
            padding: 20px 18px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-brand img {
            width: 45px;
            height: 45px;
            object-fit: contain;
            background: white;
            padding: 5px;
            border-radius: 10px;
        }

        .sidebar-brand .brand-text {
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            line-height: 1.2;
        }

        .sidebar-brand .brand-text small {
            display: block;
            font-weight: 300;
            font-size: 0.7rem;
            opacity: 0.7;
        }

        /* ========== SIDEBAR CLOSE BUTTON (mobile) ========== */
        .sidebar-close-btn {
            display: none;
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1041;
        }

        .sidebar-close-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        @media (max-width: 991.98px) {
            .sidebar-close-btn {
                display: block;
            }
        }

        /* ========== USER PANEL ========== */
        .user-panel {
            padding: 18px 20px 15px !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex !important;
            align-items: center;
            gap: 12px;
            margin: 0 !important;
        }

        .user-panel .image img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .user-panel .info {
            flex: 1;
        }

        .user-panel .info a {
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .user-panel .info a:hover {
            color: #ffd700;
        }

        .user-panel .info small {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            display: block;
        }

        /* ========== NAVIGATION ========== */
        .nav-sidebar {
            padding: 8px 0;
        }

        .nav-sidebar .nav-item {
            margin: 2px 8px;
        }

        .nav-sidebar .nav-link {
            color: rgba(255, 255, 255, 0.75);
            padding: 10px 16px;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .nav-sidebar .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(4px);
        }

        .nav-sidebar .nav-link.active {
            color: white;
            background: rgba(255, 215, 0, 0.15);
            border-left: 3px solid #ffd700;
        }

        .nav-sidebar .nav-link .nav-icon {
            width: 22px;
            text-align: center;
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .nav-sidebar .nav-link.active .nav-icon {
            color: #ffd700;
        }

        .nav-sidebar .nav-link p {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 500;
            flex: 1;
        }

        /* ========== TREEVIEW ========== */
        .nav-sidebar .has-treeview > .nav-link {
            position: relative;
        }

        .nav-sidebar .has-treeview > .nav-link .right {
            transition: transform 0.3s ease;
        }

        .nav-sidebar .has-treeview.menu-open > .nav-link .right {
            transform: rotate(-90deg);
        }

        .nav-treeview {
            padding-left: 15px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .nav-treeview.show {
            max-height: 500px;
        }

        .nav-treeview .nav-link {
            padding: 8px 16px 8px 35px;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .nav-treeview .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .nav-treeview .nav-link.active {
            color: #ffd700;
            background: rgba(255, 215, 0, 0.08);
        }

        .nav-treeview .nav-link .far.fa-circle {
            font-size: 0.5rem;
            margin-right: 10px;
        }

        /* ========== LOGOUT ========== */
        .nav-sidebar .nav-link.text-danger {
            color: #ff6b6b !important;
        }

        .nav-sidebar .nav-link.text-danger:hover {
            background: rgba(255, 107, 107, 0.15) !important;
            color: #ff6b6b !important;
        }

        .nav-sidebar .nav-link.text-danger .nav-icon {
            color: #ff6b6b !important;
        }

        /* ========== CONTENT WRAPPER ========== */
        .content-wrapper {
            min-height: 100vh;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        /* ========== CONTENT HEADER ========== */
        .content-header {
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
        }

        .content-header h1 {
            font-weight: 700;
            font-size: 1.8rem;
            color: #1a2a4a;
        }

        .content-header h1 i {
            color: #1a3a6b;
        }

        /* ========== CARDS ========== */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: white;
            border-bottom: 2px solid #f0f2f5;
            padding: 18px 25px;
            border-radius: 16px 16px 0 0 !important;
        }

        .card-header .card-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #1a2a4a;
        }

        .card-header .card-title i {
            margin-right: 10px;
            color: #1a3a6b;
        }

        .card-body {
            padding: 25px;
        }

        .card-footer {
            background: white;
            border-top: 2px solid #f0f2f5;
            padding: 18px 25px;
            border-radius: 0 0 16px 16px !important;
        }

        /* Primary card */
        .card-primary .card-header {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            color: white;
        }

        .card-primary .card-header .card-title {
            color: white;
        }

        .card-primary .card-header .card-title i {
            color: white;
        }

        /* ========== FORM STYLES ========== */
        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #1a2a4a;
        }

        .form-label i {
            margin-right: 8px;
            color: #1a3a6b;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 11px 16px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .form-control:focus {
            border-color: #1a3a6b;
            box-shadow: 0 0 0 4px rgba(26, 58, 107, 0.12);
            background: white;
        }

        .form-control[disabled] {
            background: #e9ecef;
            cursor: not-allowed;
        }

        /* ========== BUTTONS ========== */
        .btn-primary {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(26, 58, 107, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26, 58, 107, 0.4);
        }

        .btn-primary i {
            margin-right: 8px;
        }

        .btn-outline-secondary {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: #f8f9fa;
            border-color: #ced4da;
        }

        /* ========== ALERTS ========== */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 22px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .alert-danger {
            background: #fde8e8;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        .alert i {
            margin-right: 8px;
        }

        /* ========== PROFILE PHOTO ========== */
        .profile-photo-container {
            text-align: center;
            padding: 10px;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #1a3a6b;
            box-shadow: 0 4px 20px rgba(26, 58, 107, 0.2);
            transition: all 0.3s ease;
        }

        .profile-photo:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 30px rgba(26, 58, 107, 0.3);
        }

        .profile-photo-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            margin: 0 auto;
            border: 4px solid #1a3a6b;
            box-shadow: 0 4px 20px rgba(26, 58, 107, 0.2);
        }

        /* ========== SECTION DIVIDER ========== */
        .section-divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            color: #6c757d;
        }

        .section-divider::before,
        .section-divider::after {
            content: '';
            flex: 1;
            border-bottom: 2px dashed #e9ecef;
        }

        .section-divider::before {
            margin-right: 15px;
        }

        .section-divider::after {
            margin-left: 15px;
        }

        .section-divider i {
            margin-right: 8px;
            color: #1a3a6b;
        }

        /* ========== FOOTER ========== */
        .main-footer {
            background: white;
            padding: 15px 20px;
            border-top: 1px solid #e9ecef;
            text-align: center;
            font-size: 0.9rem;
            color: #6c757d;
            margin-left: 280px;
        }

        @media (max-width: 991.98px) {
            .main-footer {
                margin-left: 0;
            }
        }

        /* ========== RESPONSIVE FINE-TUNING ========== */
        @media (max-width: 576px) {
            .sidebar-toggle-btn {
                top: 8px;
                left: 8px;
                padding: 10px 14px;
                font-size: 1rem;
                border-radius: 8px;
            }

            .sidebar-toggle-btn i {
                font-size: 1.2rem;
            }

            .main-sidebar {
                width: 260px;
                max-width: 90%;
            }

            .sidebar-brand {
                padding: 15px 14px 12px;
            }

            .sidebar-brand .brand-text {
                font-size: 0.95rem;
            }

            .sidebar-brand img {
                width: 38px;
                height: 38px;
            }

            .user-panel {
                padding: 14px 16px 12px !important;
            }

            .user-panel .image img {
                width: 36px;
                height: 36px;
            }

            .user-panel .info a {
                font-size: 0.85rem;
            }

            .nav-sidebar .nav-link {
                padding: 8px 14px;
                font-size: 0.85rem;
            }

            .content-wrapper {
                padding: 15px;
                padding-top: 70px;
            }

            .sidebar-close-btn {
                top: 8px;
                right: 8px;
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }

            .content-header h1 {
                font-size: 1.4rem;
            }

            .card-header {
                padding: 15px 18px;
            }

            .card-header .card-title {
                font-size: 1rem;
            }

            .card-body {
                padding: 18px 15px;
            }

            .form-control {
                padding: 9px 14px;
                font-size: 0.9rem;
            }

            .btn-primary, .btn-outline-secondary {
                padding: 10px 20px;
                font-size: 0.95rem;
                width: 100%;
                margin-bottom: 8px;
            }

            .profile-photo {
                width: 120px;
                height: 120px;
            }

            .profile-photo-placeholder {
                width: 120px;
                height: 120px;
                font-size: 3rem;
            }

            .section-divider {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 400px) {
            .main-sidebar {
                width: 240px;
                max-width: 92%;
            }

            .sidebar-brand .brand-text {
                font-size: 0.85rem;
            }

            .sidebar-brand img {
                width: 32px;
                height: 32px;
            }

            .nav-sidebar .nav-link {
                padding: 6px 12px;
                font-size: 0.8rem;
                gap: 10px;
            }

            .nav-sidebar .nav-link .nav-icon {
                width: 18px;
                font-size: 0.85rem;
            }

            .content-wrapper {
                padding: 10px;
                padding-top: 65px;
            }

            .content-header h1 {
                font-size: 1.2rem;
            }

            .card-body {
                padding: 15px 12px;
            }

            .profile-photo {
                width: 100px;
                height: 100px;
            }

            .profile-photo-placeholder {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
        }

        /* ========== BODY SCROLL LOCK ========== */
        body.sidebar-open {
            overflow: hidden;
        }

        /* ========== PASSWORD STRENGTH INDICATOR ========== */
        .password-strength {
            margin-top: 5px;
        }

        .password-strength .progress {
            height: 4px;
            border-radius: 2px;
            background: #e9ecef;
        }

        .password-strength .progress-bar {
            transition: width 0.3s ease;
        }

        .password-strength-text {
            font-size: 0.75rem;
            margin-top: 2px;
        }
    </style>
</head>
<body>

<!-- ====== SIDEBAR TOGGLE BUTTON ====== -->
<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle Navigation Menu">
    <i class="fas fa-bars"></i>
</button>

<!-- ====== SIDEBAR OVERLAY ====== -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ====== MAIN SIDEBAR ====== -->
<aside class="main-sidebar" id="mainSidebar">
    
    <!-- Close button -->
    <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close Sidebar">
        <i class="fas fa-times"></i>
    </button>

    <!-- Brand -->
    <div class="sidebar-brand">
        <img src="../assets/images/logo.png" alt="Logo" onerror="this.src='https://via.placeholder.com/45x45/ffffff/1a3a6b?text=PI'">
        <div class="brand-text">
            Dormitory MS
            <small>The Polytechnic Ibadan</small>
        </div>
    </div>

    <!-- User Panel -->
    <div class="user-panel">
        <div class="image">
            <?php 
            $photo = $student['photo'] ?? '';
            if ($photo && file_exists('../' . $photo)): ?>
                <img src="../<?= htmlspecialchars($photo) ?>" alt="Profile">
            <?php else: ?>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['full_name'] ?? 'Student') ?>&background=ffd700&color=1a3a6b&size=42" alt="Profile">
            <?php endif; ?>
        </div>
        <div class="info">
            <a href="profile.php"><?= htmlspecialchars($student['full_name'] ?? 'Student') ?></a>
            <small><?= htmlspecialchars($student['matric_no'] ?? '') ?></small>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" role="menu">
            
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="nav-icon fas fa-tachometer-alt"></i>
                    <p>Dashboard</p>
                </a>
            </li>

            <li class="nav-item">
    <a href="browse-rooms.php" class="nav-link">
        <i class="nav-icon fas fa-search"></i>
        <p>Browse Rooms</p>
    </a>
</li>

            <li class="nav-item">
                <a href="my-room.php" class="nav-link">
                    <i class="nav-icon fas fa-bed"></i>
                    <p>My Room</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="profile.php" class="nav-link active">
                    <i class="nav-icon fas fa-user"></i>
                    <p>My Profile</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="payment.php" class="nav-link">
                    <i class="nav-icon fas fa-credit-card"></i>
                    <p>Payment</p>
                </a>
            </li>

            <!-- Requests Dropdown -->
            <li class="nav-item has-treeview">
                <a href="#" class="nav-link">
                    <i class="nav-icon fas fa-clipboard-list"></i>
                    <p>
                        Requests
                        <i class="right fas fa-angle-left"></i>
                    </p>
                </a>
                <ul class="nav nav-treeview">
                    <li class="nav-item">
                        <a href="complaints.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Complaints</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="maintenance.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Maintenance</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="refund.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Refund</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="room-change.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Room Change</p>
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item mt-4">
                <a href="logout.php" class="nav-link text-danger">
                    <i class="nav-icon fas fa-sign-out-alt"></i>
                    <p>Logout</p>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<!-- ====== CONTENT WRAPPER ====== -->
<div class="content-wrapper" id="contentWrapper">
    
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-user me-2"></i>My Profile</h1>
        </div>
    </section>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <!-- Success/Error Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Profile Form -->
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-edit"></i>Update Profile Information
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="profileForm">
                        
                        <!-- Profile Photo -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="profile-photo-container">
                                    <?php if ($student['photo'] && file_exists('../' . $student['photo'])): ?>
                                        <img src="../<?= htmlspecialchars($student['photo']) ?>" alt="Profile Photo" class="profile-photo">
                                    <?php else: ?>
                                        <div class="profile-photo-placeholder">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mt-2">
                                        <label class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-camera me-1"></i> Change Photo
                                            <input type="file" name="photo" class="d-none" accept="image/jpeg,image/png" id="photoInput">
                                        </label>
                                        <small class="text-muted d-block mt-1">JPG or PNG, max 2MB</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Personal Information -->
                        <div class="row">
                            <div class="col-12 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-id-card"></i>Matric Number
                                    </label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['matric_no']) ?>" disabled>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-user"></i>Full Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="full_name" class="form-control" 
                                           value="<?= htmlspecialchars($student['full_name']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-envelope"></i>Email Address <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($student['email'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-phone"></i>Phone Number
                                    </label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?= htmlspecialchars($student['phone'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-graduation-cap"></i>Level
                                    </label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['level'] ?? 'N/A') ?>" disabled>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-venus-mars"></i>Gender
                                    </label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['gender'] ?? 'N/A') ?>" disabled>
                                </div>
                            </div>
                        </div>

                        <!-- Password Change Section -->
                        <div class="section-divider">
                            <i class="fas fa-lock"></i> Change Password <span class="text-muted">(optional)</span>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-4">
                                <div class="form-group mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-key"></i>Current Password
                                    </label>
                                    <input type="password" name="current_password" class="form-control" id="currentPassword">
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="form-group mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-lock"></i>New Password
                                    </label>
                                    <input type="password" name="new_password" class="form-control" id="newPassword" minlength="6">
                                    <div class="password-strength" id="passwordStrength" style="display:none;">
                                        <div class="progress">
                                            <div class="progress-bar" id="strengthBar" role="progressbar" style="width: 0%;"></div>
                                        </div>
                                        <small class="password-strength-text" id="strengthText">Password strength: Weak</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="form-group mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-check-circle"></i>Confirm New Password
                                    </label>
                                    <input type="password" name="confirm_password" class="form-control" id="confirmPassword">
                                    <small class="text-muted" id="confirmMessage"></small>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- ====== FOOTER ====== -->
<footer class="main-footer">
    <strong>Dormitory Management System</strong> &copy; <?= date('Y') ?> 
    <span class="text-muted">| The Polytechnic Ibadan</span>
</footer>

<!-- ====== SCRIPTS ====== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ====== SIDEBAR TOGGLE ======
    const sidebar = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        body.classList.add('sidebar-open');
        body.style.overflow = 'hidden';
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-times"></i>';
        }
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        body.classList.remove('sidebar-open');
        body.style.overflow = '';
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // ====== TREEVIEW TOGGLE ======
    document.querySelectorAll('.has-treeview > .nav-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const parent = this.closest('.has-treeview');
            const submenu = parent.querySelector('.nav-treeview');
            
            document.querySelectorAll('.has-treeview').forEach(function(item) {
                if (item !== parent) {
                    item.classList.remove('menu-open');
                    const otherSub = item.querySelector('.nav-treeview');
                    if (otherSub) otherSub.classList.remove('show');
                }
            });
            
            parent.classList.toggle('menu-open');
            if (submenu) submenu.classList.toggle('show');
        });
    });

    // ====== CLOSE ON NAVIGATION (mobile) ======
    document.querySelectorAll('.nav-sidebar .nav-link:not(.has-treeview > .nav-link)').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 991.98) {
                setTimeout(closeSidebar, 300);
            }
        });
    });

    // ====== HANDLE RESIZE ======
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth >= 992) {
                closeSidebar();
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                body.classList.remove('sidebar-open');
                body.style.overflow = '';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                }
            }
        }, 250);
    });

    // ====== ESC KEY ======
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });

    // ====== PHOTO PREVIEW ======
    const photoInput = document.getElementById('photoInput');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const preview = document.querySelector('.profile-photo');
                    if (preview) {
                        preview.src = ev.target.result;
                    } else {
                        const container = document.querySelector('.profile-photo-container');
                        const placeholder = container.querySelector('.profile-photo-placeholder');
                        if (placeholder) {
                            const img = document.createElement('img');
                            img.src = ev.target.result;
                            img.alt = 'Profile Photo';
                            img.className = 'profile-photo';
                            placeholder.replaceWith(img);
                        }
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // ====== PASSWORD STRENGTH ======
    const newPassword = document.getElementById('newPassword');
    const passwordStrength = document.getElementById('passwordStrength');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');

    if (newPassword) {
        newPassword.addEventListener('input', function() {
            const val = this.value;
            if (val.length === 0) {
                passwordStrength.style.display = 'none';
                return;
            }

            passwordStrength.style.display = 'block';
            let strength = 0;
            let color = '#dc3545';
            let text = 'Weak';

            if (val.length >= 6) strength += 1;
            if (val.length >= 10) strength += 1;
            if (/[A-Z]/.test(val) && /[a-z]/.test(val)) strength += 1;
            if (/\d/.test(val)) strength += 1;
            if (/[^A-Za-z0-9]/.test(val)) strength += 1;

            if (strength >= 4) {
                color = '#28a745';
                text = 'Strong';
            } else if (strength >= 3) {
                color = '#ffc107';
                text = 'Medium';
            } else if (strength >= 2) {
                color = '#fd7e14';
                text = 'Fair';
            }

            strengthBar.style.width = (strength / 5 * 100) + '%';
            strengthBar.style.background = color;
            strengthText.textContent = 'Password strength: ' + text;
            strengthText.style.color = color;
        });
    }

    // ====== CONFIRM PASSWORD VALIDATION ======
    const confirmPassword = document.getElementById('confirmPassword');
    const confirmMessage = document.getElementById('confirmMessage');

    if (confirmPassword && newPassword) {
        confirmPassword.addEventListener('input', function() {
            const pwd = newPassword.value;
            const confirm = this.value;
            
            if (confirm.length > 0 && pwd !== confirm) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
                confirmMessage.textContent = '❌ Passwords do not match';
                confirmMessage.style.color = '#dc3545';
            } else if (confirm.length > 0 && pwd === confirm) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                confirmMessage.textContent = '✅ Passwords match';
                confirmMessage.style.color = '#198754';
            } else {
                this.classList.remove('is-invalid', 'is-valid');
                confirmMessage.textContent = '';
            }
        });

        newPassword.addEventListener('input', function() {
            const confirm = confirmPassword.value;
            if (confirm.length > 0 && this.value !== confirm) {
                confirmPassword.classList.add('is-invalid');
                confirmPassword.classList.remove('is-valid');
                confirmMessage.textContent = '❌ Passwords do not match';
                confirmMessage.style.color = '#dc3545';
            } else if (confirm.length > 0 && this.value === confirm) {
                confirmPassword.classList.remove('is-invalid');
                confirmPassword.classList.add('is-valid');
                confirmMessage.textContent = '✅ Passwords match';
                confirmMessage.style.color = '#198754';
            } else {
                confirmPassword.classList.remove('is-invalid', 'is-valid');
                confirmMessage.textContent = '';
            }
        });
    }

    // ====== FORM SUBMIT HANDLER ======
    const form = document.getElementById('profileForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form) {
        form.addEventListener('submit', function(e) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        });
    }

    // ====== SWEETALERT FOR SUCCESS/ERROR ======
    <?php if ($success): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?= addslashes($success) ?>',
        timer: 4000,
        showConfirmButton: true,
        confirmButtonColor: '#1a3a6b'
    });
    <?php endif; ?>

    <?php if ($error): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '<?= addslashes($error) ?>',
        confirmButtonColor: '#dc3545'
    });
    <?php endif; ?>

    console.log('✅ Profile page with responsive sidebar initialized');
});
</script>

</body>
</html>