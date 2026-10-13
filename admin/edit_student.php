<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';

$student_id = (int)($_GET['id'] ?? 0);
$success = '';
$error = '';
$errors = [];

if ($student_id <= 0) {
    header("Location: students.php");
    exit;
}

// Fetch student
$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();
if (!$student) {
    header("Location: students.php");
    exit;
}

// Fetch faculties
$faculties = $conn->query("SELECT id, name FROM faculties ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Fetch departments of current faculty
$departments = [];
if (!empty($student['faculty_id'])) {
    $fid = (int)$student['faculty_id'];
    $res = $conn->query("SELECT id, name FROM departments WHERE faculty_id = $fid ORDER BY name");
    if ($res) $departments = $res->fetch_all(MYSQLI_ASSOC);
}

// ===== HANDLE POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name     = trim($_POST['full_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $gender        = $_POST['gender'] ?? '';
    $faculty_id    = (int)($_POST['faculty_id'] ?? 0);
    $department_id = (int)($_POST['department_id'] ?? 0);
    $level         = $_POST['level'] ?? '';
    $semester      = $_POST['semester'] ?? '';
    $status        = $_POST['status'] ?? 'active';

    // Validation
    if (empty($full_name)) {
        $errors['full_name'] = "Full name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Valid email address is required.";
    }
    if (!in_array($gender, ['Male', 'Female'])) {
        $errors['gender'] = "Please select a valid gender.";
    }
    if ($faculty_id <= 0) {
        $errors['faculty_id'] = "Please select a faculty.";
    }
    if ($department_id <= 0) {
        $errors['department_id'] = "Please select a department.";
    }
    if (!in_array($level, ['ND1', 'ND2', 'HND1', 'HND2', '100', '200', '300', '400'])) {
        $errors['level'] = "Please select a valid level.";
    }
    if (!in_array($semester, ['1', '2'])) {
        $errors['semester'] = "Please select a valid semester.";
    }
    if (!in_array($status, ['active', 'suspended'])) {
        $errors['status'] = "Invalid status.";
    }

    // Normalize level (in case numeric values come in)
    $level_map = ['100' => 'ND1', '200' => 'ND2', '300' => 'HND1', '400' => 'HND2'];
    if (isset($level_map[$level])) {
        $level = $level_map[$level];
    }

    // Check email uniqueness
    if (empty($errors['email'])) {
        $check = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $student_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors['email'] = "This email is already used by another student.";
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE students 
            SET full_name = ?, email = ?, phone = ?, gender = ?, 
                faculty_id = ?, department_id = ?, level = ?, semester = ?, status = ? 
            WHERE id = ?
        ");
        $stmt->bind_param(
            "ssssiisssi",
            $full_name, $email, $phone, $gender,
            $faculty_id, $department_id, $level, $semester, $status,
            $student_id
        );

        if ($stmt->execute()) {
            $success = "Student information updated successfully!";
            // Refresh data
            $student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();
            // Refresh departments for current faculty
            $fid = (int)$student['faculty_id'];
            $departments = [];
            $res = $conn->query("SELECT id, name FROM departments WHERE faculty_id = $fid ORDER BY name");
            if ($res) $departments = $res->fetch_all(MYSQLI_ASSOC);
        } else {
            $error = "Failed to update student: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Student - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; overflow-x: hidden; }

        /* Sidebar */
        .sidebar-toggle-btn { position: fixed; top: 12px; left: 12px; z-index: 1050; background: #1a3a6b; color: white; border: none; border-radius: 10px; padding: 12px 16px; display: none; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1039; }
        .sidebar-overlay.active { display: block; }
        .main-sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, #1a3a6b 0%, #0d1b3e 100%); z-index: 1040; overflow-y: auto; transition: transform 0.3s ease; transform: translateX(-100%); }
        .main-sidebar.open { transform: translateX(0); }
        .sidebar-brand { padding: 20px 18px 15px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 12px; }
        .sidebar-brand img { width: 45px; height: 45px; background: white; padding: 5px; border-radius: 10px; }
        .sidebar-brand .brand-text { color: white; font-weight: 700; font-size: 1.1rem; }
        .sidebar-brand .brand-text small { display: block; font-size: 0.7rem; opacity: 0.7; font-weight: 300; }
        .sidebar-close-btn { display: none; position: absolute; top: 12px; right: 12px; background: rgba(255,255,255,0.1); color: white; border: none; border-radius: 50%; width: 36px; height: 36px; }
        .user-panel { padding: 18px 20px !important; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex !important; align-items: center; gap: 12px; }
        .user-panel img { width: 42px; height: 42px; border-radius: 50%; }
        .user-panel a { color: white; font-weight: 600; text-decoration: none; font-size: 0.95rem; }
        .user-panel small { color: rgba(255,255,255,0.6); display: block; font-size: 0.75rem; }
        .nav-sidebar { padding: 8px 0; list-style: none; }
        .nav-sidebar .nav-item { margin: 2px 8px; }
        .nav-sidebar .nav-link { color: rgba(255,255,255,0.75); padding: 10px 16px; border-radius: 10px; display: flex; align-items: center; gap: 12px; text-decoration: none; transition: all 0.3s ease; }
        .nav-sidebar .nav-link:hover { color: white; background: rgba(255,255,255,0.1); }
        .nav-sidebar .nav-link.active { color: white; background: rgba(255,215,0,0.15); border-left: 3px solid #ffd700; }
        .nav-sidebar .nav-icon { width: 22px; text-align: center; color: rgba(255,255,255,0.6); }
        .nav-sidebar .nav-link.active .nav-icon { color: #ffd700; }
        .nav-sidebar .nav-link.text-danger { color: #ff6b6b !important; }
        .nav-treeview { padding-left: 15px; max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
        .nav-treeview.show { max-height: 500px; }
        .nav-treeview .nav-link { padding: 8px 16px 8px 35px; font-size: 0.85rem; color: rgba(255,255,255,0.6); }

        .content-wrapper { min-height: 100vh; padding: 20px; transition: margin-left 0.3s ease; }
        .content-header { padding-bottom: 15px; border-bottom: 2px solid #e9ecef; margin-bottom: 20px; }
        .content-header h1 { font-weight: 700; font-size: 1.8rem; color: #1a2a4a; }
        .content-header h1 i { color: #1a3a6b; }

        /* Info banner */
        .student-banner {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            color: white;
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 22px;
            flex-wrap: wrap;
            box-shadow: 0 10px 40px rgba(26,58,107,0.25);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.5s ease;
        }
        .student-banner::before {
            content: '\f501';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: -20px;
            bottom: -30px;
            font-size: 10rem;
            opacity: 0.06;
            transform: rotate(-15deg);
        }
        .student-banner .student-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            border: 3px solid rgba(255,215,0,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #ffd700;
            flex-shrink: 0;
            font-weight: 800;
            position: relative;
            z-index: 1;
        }
        .student-banner .student-info {
            flex: 1;
            min-width: 220px;
            position: relative;
            z-index: 1;
        }
        .student-banner .student-info h2 {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0 0 5px;
        }
        .student-banner .student-info .matric {
            font-size: 0.95rem;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .student-banner .student-info .matric i { color: #ffd700; }
        .student-banner .status-pill {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.78rem;
            margin-top: 8px;
        }
        .student-banner .status-pill.active { background: #28a745; color: white; }
        .student-banner .status-pill.suspended { background: #dc3545; color: white; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; animation: fadeInUp 0.6s ease; }
        .card-header { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); color: white; border-radius: 16px 16px 0 0 !important; padding: 18px 25px; border: none; }
        .card-header h3 { color: white; margin: 0; font-size: 1.15rem; font-weight: 700; }
        .card-header h3 i { margin-right: 8px; }
        .card-body { padding: 30px; }

        /* Form sections */
        .form-section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
            font-weight: 700;
            color: #1a2a4a;
            padding: 15px 0 12px;
            margin-top: 15px;
            border-bottom: 2px dashed #e9ecef;
        }
        .form-section-title:first-child { padding-top: 0; margin-top: 0; }
        .form-section-title .icon {
            width: 34px;
            height: 34px;
            background: #e8f0fe;
            color: #0d6efd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .form-label { font-weight: 600; font-size: 0.9rem; color: #1a2a4a; margin-bottom: 8px; }
        .form-label i { color: #0d6efd; margin-right: 6px; }
        .form-label .text-danger { margin-left: 2px; }
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 11px 16px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1a3a6b;
            box-shadow: 0 0 0 4px rgba(26,58,107,0.12);
            background: white;
        }
        .form-control:disabled, .form-control[disabled] {
            background: #e9ecef;
            cursor: not-allowed;
            color: #6c757d;
        }
        .form-control.is-invalid, .form-select.is-invalid { border-color: #dc3545; }

        /* Level radio cards */
        .level-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 5px;
        }
        .level-option {
            position: relative;
        }
        .level-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .level-option label {
            display: block;
            padding: 14px 16px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.25s ease;
            text-align: center;
            background: #fafbfc;
            margin: 0;
        }
        .level-option label .level-code {
            font-size: 1.1rem;
            font-weight: 800;
            color: #1a2a4a;
            display: block;
            margin-bottom: 3px;
        }
        .level-option label .level-name {
            font-size: 0.72rem;
            color: #6c757d;
            font-weight: 500;
        }
        .level-option label:hover {
            border-color: #cbd5e1;
            background: #f0f4fa;
        }
        .level-option input[type="radio"]:checked + label {
            border-color: #1a3a6b;
            background: linear-gradient(135deg, #e8f0fe, #d4e4fc);
            box-shadow: 0 4px 15px rgba(26,58,107,0.15);
            transform: translateY(-2px);
        }
        .level-option input[type="radio"]:checked + label .level-code {
            color: #1a3a6b;
        }
        .level-option input[type="radio"]:checked + label::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 8px;
            right: 10px;
            color: #28a745;
            font-size: 0.75rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            border: none;
            border-radius: 12px;
            padding: 13px 32px;
            font-weight: 700;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(26,58,107,0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26,58,107,0.4);
            color: white;
        }
        .btn-outline-secondary {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 12px 28px;
            font-weight: 600;
        }
        .btn-outline-secondary:hover { background: #f8f9fa; }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 22px;
            margin-bottom: 22px;
            animation: slideDown 0.4s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .alert-danger { background: #fde8e8; color: #721c24; border-left: 5px solid #dc3545; }
        .alert i { margin-right: 8px; }

        .main-footer { background: white; padding: 15px 20px; border-top: 1px solid #e9ecef; text-align: center; font-size: 0.9rem; color: #6c757d; margin-left: 280px; }

        @media (min-width: 992px) {
            .main-sidebar { transform: translateX(0) !important; }
            .sidebar-toggle-btn, .sidebar-overlay { display: none !important; }
            .content-wrapper { margin-left: 280px; }
        }
        @media (max-width: 991.98px) {
            .sidebar-toggle-btn { display: block; }
            .main-sidebar.open { transform: translateX(0); }
            .content-wrapper { margin-left: 0; padding-top: 70px; }
            .sidebar-close-btn { display: block; }
            .main-footer { margin-left: 0; }
        }
        @media (max-width: 576px) {
            .content-header h1 { font-size: 1.4rem; }
            .card-body { padding: 20px 16px; }
            .student-banner { padding: 20px; text-align: center; justify-content: center; }
            .student-banner .student-avatar { width: 65px; height: 65px; font-size: 1.6rem; }
            .student-banner .student-info { text-align: center; }
            .student-banner .student-info h2 { font-size: 1.2rem; }
            .btn-primary, .btn-outline-secondary { width: 100%; margin-bottom: 8px; }
            .level-options { grid-template-columns: repeat(2, 1fr); }
        }
        body.sidebar-open { overflow: hidden; }
    </style>
</head>
<body>

<button class="sidebar-toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="main-sidebar" id="mainSidebar">
    <button class="sidebar-close-btn" id="sidebarClose"><i class="fas fa-times"></i></button>
    <div class="sidebar-brand">
        <img src="../assets/images/logo.png" onerror="this.src='https://via.placeholder.com/45x45/ffffff/1a3a6b?text=PI'">
        <div class="brand-text">Dormitory MS<small>Admin Panel</small></div>
    </div>
    <div class="user-panel">
        <img src="https://ui-avatars.com/api/?name=Admin&background=ffd700&color=1a3a6b&size=42">
        <div><a href="#">Administrator</a><small>Admin</small></div>
    </div>
    <ul class="nav nav-pills nav-sidebar flex-column">
        <li class="nav-item"><a href="index.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
        <li class="nav-item"><a href="students.php" class="nav-link active"><i class="nav-icon fas fa-users"></i><p>Students</p></a></li>
        <li class="nav-item"><a href="bulk-students.php" class="nav-link"><i class="nav-icon fas fa-file-upload"></i><p>Bulk Upload</p></a></li>
        <li class="nav-item"><a href="rooms.php" class="nav-link"><i class="nav-icon fas fa-bed"></i><p>Rooms</p></a></li>
        <li class="nav-item"><a href="post-room.php" class="nav-link"><i class="nav-icon fas fa-plus-square"></i><p>Post Room</p></a></li>
        <li class="nav-item"><a href="hostels.php" class="nav-link"><i class="nav-icon fas fa-building"></i><p>Hostels</p></a></li>
        <li class="nav-item"><a href="allocations.php" class="nav-link"><i class="nav-icon fas fa-door-open"></i><p>Allocations</p></a></li>
        <li class="nav-item"><a href="payments.php" class="nav-link"><i class="nav-icon fas fa-credit-card"></i><p>Payments</p></a></li>
        <li class="nav-item"><a href="reports.php" class="nav-link"><i class="nav-icon fas fa-chart-line"></i><p>Reports</p></a></li>
        <li class="nav-item"><a href="logs.php" class="nav-link"><i class="nav-icon fas fa-history"></i><p>Activity Logs</p></a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
    </ul>
</aside>

<div class="content-wrapper">
    <section class="content-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h1><i class="fas fa-user-edit me-2"></i>Edit Student</h1>
            <a href="students.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Students
            </a>
        </div>
    </section>

    <!-- Student banner -->
    <?php 
    $initial = strtoupper(substr($student['full_name'] ?? 'S', 0, 1));
    ?>
    <div class="student-banner">
        <div class="student-avatar"><?= htmlspecialchars($initial) ?></div>
        <div class="student-info">
            <h2><?= htmlspecialchars($student['full_name']) ?></h2>
            <div class="matric">
                <i class="fas fa-id-card"></i>
                <?= htmlspecialchars($student['matric_no']) ?>
                <span class="status-pill <?= $student['status'] === 'active' ? 'active' : 'suspended' ?>">
                    <?= $student['status'] === 'active' ? 'Active' : 'Suspended' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Success/Error Alerts -->
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-edit me-2"></i>Update Student Details</h3>
        </div>
        <div class="card-body">
            <form method="POST" id="editStudentForm">

                <!-- ===== PERSONAL INFO ===== -->
                <div class="form-section-title">
                    <div class="icon"><i class="fas fa-user"></i></div>
                    Personal Information
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-id-card"></i>Matric Number</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($student['matric_no']) ?>" disabled>
                        <small class="text-muted">Matric number cannot be changed</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-user"></i>Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" 
                               class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" 
                               value="<?= htmlspecialchars($_POST['full_name'] ?? $student['full_name']) ?>" required>
                        <?php if (isset($errors['full_name'])): ?>
                            <div class="invalid-feedback"><?= $errors['full_name'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-envelope"></i>Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" 
                               class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                               value="<?= htmlspecialchars($_POST['email'] ?? $student['email']) ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?= $errors['email'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-phone"></i>Phone Number</label>
                        <input type="text" name="phone" class="form-control" 
                               value="<?= htmlspecialchars($_POST['phone'] ?? $student['phone'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-venus-mars"></i>Gender <span class="text-danger">*</span></label>
                        <select name="gender" class="form-select <?= isset($errors['gender']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Select --</option>
                            <option value="Male" <?= (($_POST['gender'] ?? $student['gender']) === 'Male') ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= (($_POST['gender'] ?? $student['gender']) === 'Female') ? 'selected' : '' ?>>Female</option>
                        </select>
                        <?php if (isset($errors['gender'])): ?>
                            <div class="invalid-feedback"><?= $errors['gender'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-toggle-on"></i>Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" required>
                            <option value="active" <?= (($_POST['status'] ?? $student['status']) === 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="suspended" <?= (($_POST['status'] ?? $student['status']) === 'suspended') ? 'selected' : '' ?>>Suspended</option>
                        </select>
                        <?php if (isset($errors['status'])): ?>
                            <div class="invalid-feedback"><?= $errors['status'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ===== ACADEMIC INFO ===== -->
                <div class="form-section-title">
                    <div class="icon"><i class="fas fa-graduation-cap"></i></div>
                    Academic Information
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-building"></i>Faculty <span class="text-danger">*</span></label>
                        <select name="faculty_id" id="faculty" class="form-select <?= isset($errors['faculty_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Select Faculty --</option>
                            <?php foreach ($faculties as $f): 
                                $sel = (($_POST['faculty_id'] ?? $student['faculty_id']) == $f['id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $f['id'] ?>" <?= $sel ?>><?= htmlspecialchars($f['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['faculty_id'])): ?>
                            <div class="invalid-feedback"><?= $errors['faculty_id'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-book"></i>Department <span class="text-danger">*</span></label>
                        <select name="department_id" id="department" class="form-select <?= isset($errors['department_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): 
                                $sel = (($_POST['department_id'] ?? $student['department_id']) == $d['id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $d['id'] ?>" <?= $sel ?>><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['department_id'])): ?>
                            <div class="invalid-feedback"><?= $errors['department_id'] ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- ===== LEVEL SELECTION (Radio Cards) ===== -->
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-layer-group"></i>Level <span class="text-danger">*</span>
                        </label>
                        <div class="level-options">
                            <?php 
                            $current_level = $_POST['level'] ?? $student['level'];
                            // Normalize numeric values
                            $level_map = ['100' => 'ND1', '200' => 'ND2', '300' => 'HND1', '400' => 'HND2'];
                            if (isset($level_map[$current_level])) $current_level = $level_map[$current_level];
                            
                            $levels = [
                                'ND1' => 'National Diploma 1',
                                'ND2' => 'National Diploma 2',
                                'HND1' => 'Higher National Diploma 1',
                                'HND2' => 'Higher National Diploma 2',
                            ];
                            foreach ($levels as $code => $name): ?>
                                <div class="level-option">
                                    <input type="radio" name="level" id="lvl_<?= $code ?>" value="<?= $code ?>"
                                           <?= $current_level === $code ? 'checked' : '' ?> required>
                                    <label for="lvl_<?= $code ?>">
                                        <span class="level-code"><?= $code ?></span>
                                        <span class="level-name"><?= $name ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (isset($errors['level'])): ?>
                            <div class="text-danger mt-2" style="font-size:0.85rem;"><i class="fas fa-exclamation-circle"></i> <?= $errors['level'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-calendar"></i>Semester <span class="text-danger">*</span></label>
                        <select name="semester" class="form-select <?= isset($errors['semester']) ? 'is-invalid' : '' ?>" required>
                            <option value="1" <?= (($_POST['semester'] ?? $student['semester']) == '1') ? 'selected' : '' ?>>First Semester</option>
                            <option value="2" <?= (($_POST['semester'] ?? $student['semester']) == '2') ? 'selected' : '' ?>>Second Semester</option>
                        </select>
                        <?php if (isset($errors['semester'])): ?>
                            <div class="invalid-feedback"><?= $errors['semester'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ===== ACTIONS ===== -->
                <div class="d-flex flex-wrap gap-2 mt-4 pt-3" style="border-top: 2px dashed #e9ecef;">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save me-2"></i>Update Student
                    </button>
                    <a href="students.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

<footer class="main-footer">
    <strong>Dormitory Management System</strong> &copy; <?= date('Y') ?> | The Polytechnic Ibadan
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== SIDEBAR =====
    const sidebar = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('active'); document.body.style.overflow = ''; }
    if (toggleBtn) toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open'); overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    });
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // ===== CASCADING FACULTY → DEPARTMENT =====
    $('#faculty').on('change', function() {
        const facultyId = $(this).val();
        const deptSelect = $('#department');

        if (!facultyId) {
            deptSelect.html('<option value="">-- Select Department --</option>');
            return;
        }

        deptSelect.html('<option value="">Loading...</option>');

        $.get('get_departments.php', { faculty_id: facultyId }, function(data) {
            deptSelect.html('<option value="">-- Select Department --</option>');
            $.each(data, function(i, dept) {
                deptSelect.append(`<option value="${dept.id}">${dept.name}</option>`);
            });
        }, 'json').fail(function() {
            deptSelect.html('<option value="">Error loading departments</option>');
        });
    });

    // ===== FORM SUBMIT LOADING =====
    const form = document.getElementById('editStudentForm');
    const submitBtn = document.getElementById('submitBtn');
    if (form) {
        form.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
        });
    }

    // ===== SUCCESS ALERT =====
    <?php if ($success): ?>
    Swal.fire({
        icon: 'success',
        title: 'Updated!',
        text: '<?= addslashes($success) ?>',
        timer: 3000,
        showConfirmButton: true,
        confirmButtonColor: '#1a3a6b'
    });
    <?php endif; ?>
});
</script>
</body>
</html>