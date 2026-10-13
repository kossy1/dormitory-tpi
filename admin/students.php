<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';

$search = trim($_GET['search'] ?? '');
$success = $error = '';

// ===== HANDLE SUSPEND / REACTIVATE =====
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'suspend') {
        $conn->query("UPDATE students SET status = 'suspended' WHERE id = $id");
        $success = "Student has been suspended.";
    } elseif ($action === 'reactivate') {
        $conn->query("UPDATE students SET status = 'active' WHERE id = $id");
        $success = "Student has been reactivated.";
    }
}

// ===== HANDLE ADD NEW STUDENT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $matric_no   = strtoupper(trim($_POST['matric_no'] ?? ''));
    $full_name   = strtoupper(trim($_POST['full_name'] ?? ''));
    $surname     = strtoupper(trim($_POST['surname'] ?? ''));
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $gender      = $_POST['gender'] ?? '';
    $faculty_id  = (int)($_POST['faculty_id'] ?? 0);
    $department_id = (int)($_POST['department_id'] ?? 0);
    $level       = $_POST['level'] ?? '';
    $semester    = $_POST['semester'] ?? '';

    // Auto-derive surname if left blank
    if (empty($surname) && !empty($full_name)) {
        $parts = preg_split('/\s+/', $full_name);
        $surname = $parts[0];
    }

    // Validation
    if (empty($matric_no) || empty($full_name) || empty($email) || empty($gender) || $faculty_id <= 0 || $department_id <= 0) {
        $error = "Please fill all required fields.";
    } elseif (!in_array($level, ['ND1', 'ND2', 'HND1', 'HND2'])) {
        $error = "Invalid level. Must be ND1, ND2, HND1, or HND2.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } else {
        // Duplicate check
        $check = $conn->prepare("SELECT id FROM students WHERE matric_no = ? OR email = ?");
        $check->bind_param("ss", $matric_no, $email);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "Matric number or email already exists.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO students 
                (matric_no, full_name, surname, email, phone, gender, faculty_id, department_id, level, semester, password, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'active')
            ");
            $stmt->bind_param(
                "ssssssiiss",
                $matric_no, $full_name, $surname, $email, $phone,
                $gender, $faculty_id, $department_id, $level, $semester
            );

            if ($stmt->execute()) {
                $success = "Student '$full_name' added successfully! Login with matric: <strong>$matric_no</strong>, Surname: <strong>$surname</strong>";
            } else {
                $error = "Failed to add student: " . $stmt->error;
            }
        }
    }
}

// ===== FETCH STUDENTS =====
$where = $search ? "WHERE s.matric_no LIKE ? OR s.full_name LIKE ? OR s.surname LIKE ? OR s.email LIKE ?" : "";
$stmt = $conn->prepare("
    SELECT s.*, f.name AS faculty_name, d.name AS department_name 
    FROM students s 
    LEFT JOIN faculties f ON s.faculty_id = f.id 
    LEFT JOIN departments d ON s.department_id = d.id 
    $where 
    ORDER BY s.matric_no ASC
");

if ($search) {
    $like = "%$search%";
    $stmt->bind_param("ssss", $like, $like, $like, $like);
}
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch faculties
$faculties = $conn->query("SELECT id, name FROM faculties ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Students - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; overflow-x: hidden; }

        .sidebar-toggle-btn { position: fixed; top: 12px; left: 12px; z-index: 1050; background: #1a3a6b; color: white; border: none; border-radius: 10px; padding: 12px 16px; display: none; }
        .sidebar-toggle-btn:hover { background: #0d1b3e; }
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

        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; animation: fadeInUp 0.6s ease; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .card-header { background: white; border-bottom: 2px solid #f0f2f5; padding: 18px 25px; border-radius: 16px 16px 0 0 !important; }
        .card-header .card-title { font-weight: 600; font-size: 1.1rem; color: #1a2a4a; margin: 0; }
        .card-header .card-title i { margin-right: 10px; color: #1a3a6b; }
        .card-body { padding: 25px; }

        .table { margin-bottom: 0; }
        .table thead th { background: #f8f9fa; color: #1a2a4a; font-weight: 600; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e9ecef; padding: 12px 15px; white-space: nowrap; }
        .table tbody td { padding: 12px 15px; vertical-align: middle; border-bottom: 1px solid #f0f2f5; font-size: 0.9rem; }
        .table tbody tr { transition: all 0.3s ease; }
        .table tbody tr:hover { background: #f8f9fa; }

        .badge { padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 0.75rem; }
        .badge.bg-success { background: #28a745 !important; }
        .badge.bg-danger { background: #dc3545 !important; }
        .badge.bg-secondary { background: #6c757d !important; }

        .level-nd1 { background: #17a2b8; color: white; }
        .level-nd2 { background: #28a745; color: white; }
        .level-hnd1 { background: #fd7e14; color: white; }
        .level-hnd2 { background: #6f42c1; color: white; }
        .level-default { background: #6c757d; color: white; }

        .btn-success { background: linear-gradient(135deg, #28a745, #20c997); border: none; border-radius: 10px; padding: 10px 24px; font-weight: 600; color: white; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(40,167,69,0.3); }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(40,167,69,0.4); color: white; }
        .btn-primary { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); border: none; border-radius: 10px; padding: 10px 24px; font-weight: 600; color: white; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(26,58,107,0.3); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(26,58,107,0.4); color: white; }
        .btn-warning { border-radius: 8px; padding: 4px 12px; font-weight: 600; font-size: 0.8rem; }
        .btn-danger { border-radius: 8px; padding: 4px 12px; font-weight: 600; font-size: 0.8rem; }
        .btn-outline-secondary { border: 2px solid #e9ecef; border-radius: 10px; padding: 8px 20px; font-weight: 600; }
        .btn-outline-secondary:hover { background: #f8f9fa; border-color: #ced4da; }

        .search-wrapper { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .search-wrapper .form-control { border: 2px solid #e9ecef; border-radius: 10px; padding: 10px 16px; font-size: 0.95rem; background: #fafbfc; min-width: 280px; transition: all 0.3s ease; }
        .search-wrapper .form-control:focus { border-color: #1a3a6b; box-shadow: 0 0 0 4px rgba(26,58,107,0.12); background: white; }
        .search-wrapper .btn-search { background: #1a3a6b; color: white; border: none; border-radius: 10px; padding: 10px 18px; }

        .alert { border-radius: 12px; border: none; padding: 16px 22px; margin-bottom: 20px; animation: slideDown 0.4s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-15px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .alert-danger { background: #fde8e8; color: #721c24; border-left: 5px solid #dc3545; }
        .alert i { margin-right: 8px; }

        .modal-content { border-radius: 16px; border: none; box-shadow: 0 25px 60px rgba(0,0,0,0.2); }
        .modal-header { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); color: white; border-radius: 16px 16px 0 0 !important; padding: 18px 25px; }
        .modal-header .btn-close { filter: brightness(0) invert(1); }
        .modal-title { font-weight: 700; }
        .modal-title i { margin-right: 10px; }
        .modal-body { padding: 25px; }
        .modal-footer { padding: 15px 25px; border-top: 2px solid #f0f2f5; }

        .form-label { font-weight: 600; font-size: 0.9rem; color: #1a2a4a; }
        .form-label .text-danger { margin-left: 2px; }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 10px; padding: 10px 16px; font-size: 0.95rem; background: #fafbfc; transition: all 0.3s ease; }
        .form-control:focus, .form-select:focus { border-color: #1a3a6b; box-shadow: 0 0 0 4px rgba(26,58,107,0.12); background: white; }

        .surname-badge {
            background: #e8f0fe;
            color: #0d6efd;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

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
            .card-header, .card-body { padding: 15px 18px; }
            .search-wrapper { width: 100%; }
            .search-wrapper .form-control { min-width: 100%; }
            .table thead th { font-size: 0.7rem; padding: 8px 10px; }
            .table tbody td { padding: 8px 10px; font-size: 0.8rem; }
            .btn-success { width: 100%; }
            .modal-body { padding: 18px 15px; }
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
          <li class="nav-item">
    <a href="manage-staff.php" class="nav-link">
        <i class="nav-icon fas fa-user-tie"></i> Manage Staff
    </a>
</li>
        <li class="nav-item"><a href="rooms.php" class="nav-link"><i class="nav-icon fas fa-bed"></i><p>Rooms</p></a></li>
        <li class="nav-item"><a href="post-room.php" class="nav-link"><i class="nav-icon fas fa-plus-square"></i><p>Post Room</p></a></li>
        <li class="nav-item"><a href="hostels.php" class="nav-link"><i class="nav-icon fas fa-building"></i><p>Hostels</p></a></li>
        <li class="nav-item"><a href="allocations.php" class="nav-link"><i class="nav-icon fas fa-door-open"></i><p>Allocations</p></a></li>
        <li class="nav-item"><a href="payments.php" class="nav-link"><i class="nav-icon fas fa-credit-card"></i><p>Payments</p></a></li>
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
                        <a href="refunds.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Refunds</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="room-change.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Room Changes</p>
                        </a>
                    </li>
                </ul>
            </li>
        <li class="nav-item"><a href="reports.php" class="nav-link"><i class="nav-icon fas fa-chart-line"></i><p>Reports</p></a></li>
        <li class="nav-item"><a href="logs.php" class="nav-link"><i class="nav-icon fas fa-history"></i><p>Activity Logs</p></a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
    </ul>
</aside>

<div class="content-wrapper">
    <section class="content-header">
        <div class="row align-items-center">
            <div class="col-12 col-md-6">
                <h1><i class="fas fa-users me-2"></i>Manage Students</h1>
            </div>
            <div class="col-12 col-md-6 text-md-end mt-3 mt-md-0">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="fas fa-plus me-2"></i> Add New Student
                </button>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i>
                    <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <h3 class="card-title">
                            <i class="fas fa-list me-2"></i>
                            All Students 
                            <span class="badge bg-secondary ms-2"><?= count($students) ?></span>
                        </h3>
                        <div class="search-wrapper">
                            <form method="GET" class="d-flex gap-2">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search by matric, name, surname or email..." 
                                       value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="btn-search">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if ($search): ?>
                                    <a href="students.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card-body table-responsive p-0">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Matric No</th>
                                <th>Full Name</th>
                                <th>Surname (Login)</th>
                                <th>Faculty</th>
                                <th>Department</th>
                                <th>Level</th>
                                <th>Gender</th>
                                <th>Status</th>
                                <th width="180">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="text-center py-5">
                                            <i class="fas fa-user-graduate" style="font-size:4rem;color:#d1d5db;"></i>
                                            <h4 class="mt-3">No Students Found</h4>
                                            <p class="text-muted"><?= $search ? 'No results match your search criteria.' : 'Start by adding your first student.' ?></p>
                                            <?php if ($search): ?>
                                                <a href="students.php" class="btn btn-primary">
                                                    <i class="fas fa-arrow-left me-2"></i>Clear Search
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $s): 
                                    // Level display — handle both ND1 style and 100 style
                                    $level_raw = $s['level'];
                                    $level_class = 'level-default';
                                    $level_display = htmlspecialchars($level_raw);
                                    switch ($level_raw) {
                                        case 'ND1': case '100': $level_class = 'level-nd1'; $level_display = 'ND1'; break;
                                        case 'ND2': case '200': $level_class = 'level-nd2'; $level_display = 'ND2'; break;
                                        case 'HND1': case '300': $level_class = 'level-hnd1'; $level_display = 'HND1'; break;
                                        case 'HND2': case '400': $level_class = 'level-hnd2'; $level_display = 'HND2'; break;
                                    }
                                    $surname = !empty($s['surname']) ? $s['surname'] : explode(' ', $s['full_name'])[0];
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($s['matric_no']) ?></strong></td>
                                        <td><?= htmlspecialchars($s['full_name']) ?></td>
                                        <td><span class="surname-badge"><?= htmlspecialchars($surname) ?></span></td>
                                        <td><?= htmlspecialchars($s['faculty_name'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($s['department_name'] ?? '-') ?></td>
                                        <td><span class="badge <?= $level_class ?>"><?= $level_display ?></span></td>
                                        <td>
                                            <i class="fas fa-<?= $s['gender'] === 'Male' ? 'mars' : 'venus' ?> me-1"></i>
                                            <?= htmlspecialchars($s['gender']) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'danger' ?>">
                                                <i class="fas fa-<?= $s['status'] === 'active' ? 'check' : 'times' ?>-circle me-1"></i>
                                                <?= ucfirst($s['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <a href="edit_student.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <?php if ($s['status'] === 'active'): ?>
                                                    <a href="#" class="btn btn-sm btn-danger suspend-btn"
                                                       data-id="<?= $s['id'] ?>"
                                                       data-name="<?= htmlspecialchars($s['full_name']) ?>">
                                                        <i class="fas fa-ban"></i> Suspend
                                                    </a>
                                                <?php else: ?>
                                                    <a href="#" class="btn btn-sm btn-success reactivate-btn"
                                                       data-id="<?= $s['id'] ?>"
                                                       data-name="<?= htmlspecialchars($s['full_name']) ?>">
                                                        <i class="fas fa-undo"></i> Reactivate
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>

<footer class="main-footer">
    <strong>Dormitory Management System</strong> &copy; <?= date('Y') ?> | The Polytechnic Ibadan
</footer>

<!-- ====== ADD STUDENT MODAL ====== -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><i class="fas fa-user-plus"></i> Add New Student</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addStudentForm">
                <div class="modal-body">
                    <div class="alert alert-info" style="border-radius:10px;padding:12px 16px;font-size:0.85rem;">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Login credentials:</strong> Student will log in using their <strong>matric number</strong> + <strong>surname</strong>.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-id-card me-1"></i>Matric Number <span class="text-danger">*</span></label>
                            <input type="text" name="matric_no" class="form-control" required 
                                   placeholder="e.g. 2024239010098"
                                   style="text-transform: uppercase;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-user-tag me-1"></i>Surname <span class="text-danger">*</span></label>
                            <input type="text" name="surname" class="form-control" required 
                                   placeholder="e.g. OKUEWU"
                                   style="text-transform: uppercase;">
                            <small class="text-muted">Used for login (unique)</small>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label"><i class="fas fa-user me-1"></i>Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required 
                                   placeholder="e.g. OKUEWU ODUNAYO GLORIA"
                                   style="text-transform: uppercase;">
                            <small class="text-muted">Format: SURNAME FIRSTNAME MIDDLENAME</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-envelope me-1"></i>Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required 
                                   placeholder="student@polytechnic.edu.ng">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-phone me-1"></i>Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="08012345678">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-venus-mars me-1"></i>Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-building me-1"></i>Faculty <span class="text-danger">*</span></label>
                            <select name="faculty_id" id="faculty" class="form-select" required>
                                <option value="">-- Select Faculty --</option>
                                <?php foreach ($faculties as $f): ?>
                                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-book me-1"></i>Department <span class="text-danger">*</span></label>
                            <select name="department_id" id="department" class="form-select" required>
                                <option value="">-- Select Department --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-graduation-cap me-1"></i>Level <span class="text-danger">*</span></label>
                            <select name="level" class="form-select" required>
                                <option value="">-- Select Level --</option>
                                <option value="ND1">ND1 (National Diploma 1)</option>
                                <option value="ND2">ND2 (National Diploma 2)</option>
                                <option value="HND1">HND1 (Higher National Diploma 1)</option>
                                <option value="HND2">HND2 (Higher National Diploma 2)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-calendar me-1"></i>Semester <span class="text-danger">*</span></label>
                            <select name="semester" class="form-select" required>
                                <option value="1">First Semester</option>
                                <option value="2">Second Semester</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" name="add_student" class="btn btn-primary" id="addStudentBtn">
                        <i class="fas fa-save me-2"></i>Add Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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

    // ===== AUTO-UPPERCASE INPUTS =====
    document.querySelectorAll('input[name="matric_no"], input[name="full_name"], input[name="surname"]').forEach(input => {
        input.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
    });

    // ===== CASCADING FACULTY → DEPARTMENT =====
    $('#faculty').change(function() {
        const facultyId = $(this).val();
        const deptSelect = $('#department');
        deptSelect.html('<option value="">Loading departments...</option>');
        if (!facultyId) {
            deptSelect.html('<option value="">-- Select Department --</option>');
            return;
        }
        $.get('get_departments.php', { faculty_id: facultyId }, function(data) {
            deptSelect.html('<option value="">-- Select Department --</option>');
            $.each(data, function(i, dept) {
                deptSelect.append(`<option value="${dept.id}">${dept.name}</option>`);
            });
        }, 'json').fail(function() {
            deptSelect.html('<option value="">Error loading departments</option>');
        });
    });

    // ===== SUSPEND =====
    document.querySelectorAll('.suspend-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id, name = this.dataset.name;
            Swal.fire({
                title: 'Suspend Student?',
                html: `Are you sure you want to suspend <strong>${name}</strong>?`,
                icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Suspend', cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) window.location.href = `?action=suspend&id=${id}`;
            });
        });
    });

    // ===== REACTIVATE =====
    document.querySelectorAll('.reactivate-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id, name = this.dataset.name;
            Swal.fire({
                title: 'Reactivate Student?',
                html: `Reactivate <strong>${name}</strong>?`,
                icon: 'info', showCancelButton: true,
                confirmButtonColor: '#28a745', cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Reactivate', cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) window.location.href = `?action=reactivate&id=${id}`;
            });
        });
    });

    // ===== FORM SUBMIT LOADING =====
    const form = document.getElementById('addStudentForm');
    const submitBtn = document.getElementById('addStudentBtn');
    if (form) {
        form.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
        });
    }
});
</script>
</body>
</html>