<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';
include 'log_action.php';

$success = $error = '';
$results = ['inserted' => 0, 'skipped' => 0, 'errors' => []];

// ===== HANDLE CSV UPLOAD =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload failed (error code: {$file['error']})";
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error = "Only .csv files are allowed.";
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $error = "Could not open uploaded file.";
        } else {
            $row_num = 0;
            $header = null;

            while (($row = fgetcsv($handle)) !== false) {
                $row_num++;

                // Skip BOM on first row
                if ($row_num === 1) {
                    if (isset($row[0])) $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                    $header = array_map('strtolower', array_map('trim', $row));
                    continue;
                }

                // Skip empty rows
                if (empty(array_filter($row))) continue;

                // Map columns
                $data = [];
                foreach ($header as $i => $col) {
                    $data[$col] = isset($row[$i]) ? trim($row[$i]) : '';
                }

                $matric_no     = strtoupper($data['matric_no'] ?? '');
                $full_name     = strtoupper($data['full_name'] ?? '');
                $surname       = strtoupper($data['surname'] ?? '');
                $email         = trim($data['email'] ?? '');
                $phone         = trim($data['phone'] ?? '');
                $gender        = ucfirst(strtolower($data['gender'] ?? ''));
                $faculty_id    = (int)($data['faculty_id'] ?? 0);
                $department_id = (int)($data['department_id'] ?? 0);
                $level         = strtoupper($data['level'] ?? '');
                $semester      = $data['semester'] ?? '1';

                // Auto-derive surname
                if (empty($surname) && !empty($full_name)) {
                    $surname = explode(' ', $full_name)[0];
                }

                // Validate
                if (empty($matric_no) || empty($full_name) || empty($email) || empty($gender) || $faculty_id <= 0 || $department_id <= 0) {
                    $results['errors'][] = "Row $row_num: missing required fields";
                    continue;
                }
                if (!in_array($gender, ['Male', 'Female'], true)) {
                    $results['errors'][] = "Row $row_num: invalid gender '$gender'";
                    continue;
                }
                if (!in_array($level, ['ND1', 'ND2', 'HND1', 'HND2'], true)) {
                    $results['errors'][] = "Row $row_num: invalid level '$level'";
                    continue;
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $results['errors'][] = "Row $row_num: invalid email '$email'";
                    continue;
                }

                // Duplicate check
                $check = $conn->prepare("SELECT id FROM students WHERE matric_no = ? OR email = ?");
                $check->bind_param("ss", $matric_no, $email);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $results['skipped']++;
                    continue;
                }

                // Insert
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
                    $results['inserted']++;
                } else {
                    $results['errors'][] = "Row $row_num: " . $stmt->error;
                }
            }
            fclose($handle);

            log_action($conn, 'create', 'student', null,
                "Bulk uploaded students: {$results['inserted']} inserted, {$results['skipped']} skipped, " . count($results['errors']) . " errors");

            $success = "Bulk upload complete! {$results['inserted']} student(s) inserted, {$results['skipped']} skipped.";
        }
    }
}

// ===== FETCH FACULTIES & DEPARTMENTS FOR TEMPLATE =====
$faculties = $conn->query("SELECT id, name FROM faculties ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bulk Upload Students - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ========== ANIMATIONS & GLOBAL ENHANCEMENTS ========== */
* { -webkit-tap-highlight-color: transparent; }

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}
@keyframes slideInLeft {
    from { opacity: 0; transform: translateX(-30px); }
    to   { opacity: 1; transform: translateX(0); }
}
@keyframes slideInRight {
    from { opacity: 0; transform: translateX(30px); }
    to   { opacity: 1; transform: translateX(0); }
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50%      { transform: scale(1.05); }
}
@keyframes shimmer {
    0%   { background-position: -500px 0; }
    100% { background-position: 500px 0; }
}
@keyframes bounceIn {
    0%   { opacity: 0; transform: scale(0.85); }
    60%  { opacity: 1; transform: scale(1.03); }
    100% { transform: scale(1); }
}

/* Apply to common elements */
.content-header { animation: fadeInUp 0.5s ease both; }
.card { animation: fadeInUp 0.5s ease both; }
.stat-card { animation: bounceIn 0.6s ease both; }

/* Stagger stat cards */
.row > [class*="col-"]:nth-child(1) .stat-card { animation-delay: 0.05s; }
.row > [class*="col-"]:nth-child(2) .stat-card { animation-delay: 0.10s; }
.row > [class*="col-"]:nth-child(3) .stat-card { animation-delay: 0.15s; }
.row > [class*="col-"]:nth-child(4) .stat-card { animation-delay: 0.20s; }
.row > [class*="col-"]:nth-child(5) .stat-card { animation-delay: 0.25s; }
.row > [class*="col-"]:nth-child(6) .stat-card { animation-delay: 0.30s; }

.table tbody tr { animation: fadeIn 0.4s ease both; }
.table tbody tr:nth-child(1)  { animation-delay: 0.02s; }
.table tbody tr:nth-child(2)  { animation-delay: 0.04s; }
.table tbody tr:nth-child(3)  { animation-delay: 0.06s; }
.table tbody tr:nth-child(4)  { animation-delay: 0.08s; }
.table tbody tr:nth-child(5)  { animation-delay: 0.10s; }
.table tbody tr:nth-child(6)  { animation-delay: 0.12s; }
.table tbody tr:nth-child(7)  { animation-delay: 0.14s; }
.table tbody tr:nth-child(8)  { animation-delay: 0.16s; }
.table tbody tr:nth-child(9)  { animation-delay: 0.18s; }
.table tbody tr:nth-child(10) { animation-delay: 0.20s; }

/* Hover enhancements */
.card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
.card:hover { transform: translateY(-3px); box-shadow: 0 12px 35px rgba(0,0,0,0.1); }
.btn { transition: all 0.25s ease; position: relative; overflow: hidden; }
.btn:hover { transform: translateY(-2px); }
.btn:active { transform: translateY(0) scale(0.98); }

/* Table row hover */
.table tbody tr { transition: background 0.2s ease, transform 0.2s ease; }
.table tbody tr:hover { background: #f0f4fa !important; }

/* Modal animations */
.modal.fade .modal-dialog {
    transform: scale(0.9);
    transition: transform 0.3s ease-out;
}
.modal.show .modal-dialog { transform: scale(1); }

/* Sidebar smoothness */
.main-sidebar { will-change: transform; }

/* Loading shimmer on images */
img[loading="lazy"] {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 500px 100%;
    animation: shimmer 1.5s infinite linear;
}

/* Focus outline for accessibility */
button:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible {
    outline: 2px solid #1a3a6b;
    outline-offset: 2px;
}

/* Reduce motion for users who prefer it */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* ========== MOBILE RESPONSIVENESS ========== */
@media (max-width: 991.98px) {
    .content-wrapper { padding: 15px !important; padding-top: 75px !important; }
    .content-header h1 { font-size: 1.4rem !important; }
    .card-header { padding: 15px 18px !important; }
    .card-body { padding: 18px !important; }
    .stat-card { padding: 16px !important; gap: 12px !important; }
    .stat-card .stat-icon { width: 48px !important; height: 48px !important; font-size: 1.25rem !important; }
    .stat-card .stat-info h3 { font-size: 1.3rem !important; }
    .stat-card .stat-info p { font-size: 0.78rem !important; }
}

@media (max-width: 767.98px) {
    .content-header h1 { font-size: 1.25rem !important; }
    .content-header { padding-bottom: 12px !important; margin-bottom: 15px !important; }

    .card { border-radius: 12px !important; margin-bottom: 15px !important; }
    .card-header { padding: 12px 15px !important; }
    .card-header h3, .card-header .card-title { font-size: 0.95rem !important; }
    .card-body { padding: 15px !important; }

    /* Make tables scroll horizontally */
    .table-responsive { -webkit-overflow-scrolling: touch; border-radius: 10px; }
    .table thead th { font-size: 0.7rem !important; padding: 8px 10px !important; white-space: nowrap; }
    .table tbody td { font-size: 0.8rem !important; padding: 8px 10px !important; }

    /* Full-width buttons in modals */
    .modal-footer .btn { width: 100%; margin: 4px 0 !important; }
    .modal-footer { flex-direction: column; }
    .modal-footer > * { margin: 4px 0 !important; }
    .modal-body { padding: 15px !important; }
    .modal-header { padding: 12px 15px !important; }
    .modal-header .modal-title { font-size: 1rem !important; }

    /* Stacked stat cards */
    .stat-card { flex-direction: row !important; padding: 14px !important; }
    .stat-card .stat-icon { width: 42px !important; height: 42px !important; font-size: 1.1rem !important; }
    .stat-card .stat-info h3 { font-size: 1.15rem !important; }
    .stat-card .stat-info p { font-size: 0.72rem !important; margin-top: 2px !important; }

    /* Better form controls on mobile */
    .form-control, .form-select {
        font-size: 16px !important; /* Prevents iOS zoom on focus */
        padding: 10px 14px !important;
    }

    /* Full-width actions */
    .d-flex.gap-2.flex-wrap > .btn,
    .d-flex.gap-1.flex-wrap > .btn { flex: 1 1 auto; min-width: 45%; }
}

@media (max-width: 575.98px) {
    .content-wrapper { padding: 10px !important; padding-top: 70px !important; }
    .content-header h1 { font-size: 1.1rem !important; }
    .content-header h1 i { font-size: 0.95rem !important; }

    .card { border-radius: 10px !important; }
    .card-header { padding: 10px 12px !important; }
    .card-body { padding: 12px !important; }

    .stat-card { padding: 12px !important; gap: 10px !important; }
    .stat-card .stat-icon { width: 38px !important; height: 38px !important; font-size: 1rem !important; }
    .stat-card .stat-info h3 { font-size: 1rem !important; }
    .stat-card .stat-info p { font-size: 0.68rem !important; }

    /* Sidebar takes more width on small screens */
    .main-sidebar { width: 260px !important; max-width: 85% !important; }

    /* Compact stat grid - 2 per row */
    .row.g-3 > .col-6 { flex: 0 0 50%; max-width: 50%; }

    /* Make the search form full width */
    .search-wrapper { width: 100%; }
    .search-wrapper form { width: 100%; }
    .search-wrapper .form-control { min-width: 100% !important; }

    /* Compact tables */
    .table thead th { font-size: 0.65rem !important; padding: 6px 8px !important; }
    .table tbody td { font-size: 0.75rem !important; padding: 6px 8px !important; }
}

/* Touch-friendly target sizes */
@media (hover: none) and (pointer: coarse) {
    .nav-sidebar .nav-link { padding: 12px 16px !important; }
    .btn { min-height: 40px; }
    .btn-sm { min-height: 34px; padding: 6px 12px !important; }
}

/* Print styles */
@media print {
    .main-sidebar, .sidebar-toggle-btn, .sidebar-overlay,
    .main-footer, .content-header .btn, .card-header .btn { display: none !important; }
    .content-wrapper { margin-left: 0 !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    body { background: white !important; }
}

        body { font-family:'Inter',sans-serif; background:#f4f6f9; }
        .sidebar-toggle-btn { position:fixed; top:12px; left:12px; z-index:1050; background:#1a3a6b; color:white; border:none; border-radius:10px; padding:12px 16px; display:none; }
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1039; }
        .sidebar-overlay.active { display:block; }
        .main-sidebar { position:fixed; top:0; left:0; height:100vh; width:280px; background:linear-gradient(180deg,#1a3a6b 0%,#0d1b3e 100%); z-index:1040; overflow-y:auto; transition:transform 0.3s ease; transform:translateX(-100%); }
        .main-sidebar.open { transform:translateX(0); }
        .sidebar-brand { padding:20px 18px 15px; border-bottom:1px solid rgba(255,255,255,0.08); display:flex; align-items:center; gap:12px; }
        .sidebar-brand img { width:45px; height:45px; background:white; padding:5px; border-radius:10px; }
        .sidebar-brand .brand-text { color:white; font-weight:700; font-size:1.1rem; }
        .sidebar-brand .brand-text small { display:block; font-size:0.7rem; opacity:0.7; font-weight:300; }
        .sidebar-close-btn { display:none; position:absolute; top:12px; right:12px; background:rgba(255,255,255,0.1); color:white; border:none; border-radius:50%; width:36px; height:36px; }
        .user-panel { padding:18px 20px !important; border-bottom:1px solid rgba(255,255,255,0.08); display:flex !important; align-items:center; gap:12px; }
        .user-panel img { width:42px; height:42px; border-radius:50%; }
        .user-panel a { color:white; font-weight:600; text-decoration:none; }
        .user-panel small { color:rgba(255,255,255,0.6); display:block; font-size:0.75rem; }
        .nav-sidebar { padding:8px 0; list-style:none; }
        .nav-sidebar .nav-item { margin:2px 8px; }
        .nav-sidebar .nav-link { color:rgba(255,255,255,0.75); padding:10px 16px; border-radius:10px; display:flex; align-items:center; gap:12px; text-decoration:none; }
        .nav-sidebar .nav-link:hover { color:white; background:rgba(255,255,255,0.1); }
        .nav-sidebar .nav-link.active { color:white; background:rgba(255,215,0,0.15); border-left:3px solid #ffd700; }
        .nav-sidebar .nav-icon { width:22px; text-align:center; color:rgba(255,255,255,0.6); }
        .nav-sidebar .nav-link.active .nav-icon { color:#ffd700; }
        .nav-treeview { padding-left:15px; max-height:0; overflow:hidden; transition:max-height 0.3s ease; }
        .nav-treeview.show { max-height:500px; }
        .nav-treeview .nav-link { padding:8px 16px 8px 35px; font-size:0.85rem; color:rgba(255,255,255,0.6); }
        .content-wrapper { min-height:100vh; padding:20px; transition:margin-left 0.3s ease; }
        .content-header { padding-bottom:15px; border-bottom:2px solid #e9ecef; margin-bottom:20px; }
        .content-header h1 { font-weight:700; font-size:1.8rem; color:#1a2a4a; }
        .card { border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.06); margin-bottom:20px; }
        .card-header { background:white; border-bottom:2px solid #f0f2f5; padding:18px 25px; border-radius:16px 16px 0 0 !important; }
        .card-body { padding:25px; }
        .upload-area { border:3px dashed #cbd5e1; border-radius:12px; padding:40px; text-align:center; cursor:pointer; background:#fafbfc; transition:all 0.3s ease; }
        .upload-area:hover, .upload-area.dragover { border-color:#1a3a6b; background:#f0f4fa; }
        .upload-area input { display:none; }
        .btn-primary { background:linear-gradient(135deg,#1a3a6b,#0d1b3e); border:none; border-radius:10px; padding:10px 24px; font-weight:600; color:white; }
        .btn-success { background:linear-gradient(135deg,#28a745,#20c997); border:none; border-radius:10px; padding:10px 24px; font-weight:600; color:white; }
        .btn-outline-secondary { border:2px solid #e9ecef; border-radius:10px; padding:8px 20px; font-weight:600; }
        .alert { border-radius:12px; border:none; padding:16px 22px; margin-bottom:20px; }
        .alert-success { background:#d4edda; color:#155724; border-left:5px solid #28a745; }
        .alert-danger { background:#fde8e8; color:#721c24; border-left:5px solid #dc3545; }
        .alert-info { background:#e7f3ff; color:#084298; border-left:5px solid #0d6efd; }
        .alert-warning { background:#fff3cd; color:#856404; border-left:5px solid #ffc107; }
        .main-footer { background:white; padding:15px 20px; border-top:1px solid #e9ecef; text-align:center; font-size:0.9rem; color:#6c757d; margin-left:280px; }
        @media (min-width:992px) {
            .main-sidebar { transform:translateX(0) !important; }
            .sidebar-toggle-btn, .sidebar-overlay { display:none !important; }
            .content-wrapper { margin-left:280px; }
        }
        @media (max-width:991.98px) {
            .sidebar-toggle-btn { display:block; }
            .main-sidebar.open { transform:translateX(0); }
            .content-wrapper { margin-left:0; padding-top:70px; }
            .sidebar-close-btn { display:block; }
            .main-footer { margin-left:0; }
        }
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
        <li class="nav-item"><a href="students.php" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Students</p></a></li>
        <li class="nav-item"><a href="bulk-students.php" class="nav-link active"><i class="nav-icon fas fa-file-upload"></i><p>Bulk Upload</p></a></li>
        <li class="nav-item"><a href="manage-staff.php" class="nav-link"><i class="nav-icon fas fa-user-tie"></i><p>Manage Staff</p></a></li>
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
        <h1><i class="fas fa-file-upload me-2"></i>Bulk Upload Students</h1>
    </section>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($results['errors'])): ?>
        <div class="alert alert-warning">
            <strong><i class="fas fa-exclamation-triangle me-2"></i>Errors encountered:</strong>
            <ul class="mb-0 mt-2" style="max-height:200px;overflow:auto;">
                <?php foreach (array_slice($results['errors'], 0, 50) as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
                <?php if (count($results['errors']) > 50): ?>
                    <li><em>... and <?= count($results['errors']) - 50 ?> more</em></li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="mb-0"><i class="fas fa-cloud-upload-alt me-2"></i>Upload CSV File</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <strong><i class="fas fa-info-circle me-2"></i>CSV Format Required</strong>
                <p class="mb-2 mt-2">Your CSV must have these columns (in any order):</p>
                <code>matric_no, full_name, surname, email, phone, gender, faculty_id, department_id, level, semester</code>
                <p class="mt-2 mb-0"><strong>Level:</strong> ND1, ND2, HND1, or HND2 &nbsp;|&nbsp; <strong>Gender:</strong> Male or Female &nbsp;|&nbsp; <strong>Semester:</strong> 1 or 2</p>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="upload-area" id="uploadArea" onclick="document.getElementById('csvInput').click()">
                    <input type="file" name="csv_file" id="csvInput" accept=".csv" required>
                    <i class="fas fa-file-csv" style="font-size:4rem;color:#1a3a6b;"></i>
                    <h4 class="mt-3">Click to select CSV file</h4>
                    <p class="text-muted mb-0">or drag and drop here</p>
                    <p class="mt-2 mb-0" id="fileName" style="font-weight:600;color:#1a3a6b;"></p>
                </div>
                <div class="mt-3 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Upload & Process
                    </button>
                    <a href="download-csv-template.php" class="btn btn-outline-secondary">
                        <i class="fas fa-download me-2"></i>Download Template
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="mb-0"><i class="fas fa-list me-2"></i>Available Faculties & Departments</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 style="font-weight:700;color:#1a2a4a;">Faculties</h6>
                    <table class="table table-sm">
                        <thead><tr><th>ID</th><th>Name</th></tr></thead>
                        <tbody>
                            <?php foreach ($faculties as $f): ?>
                                <tr><td><strong><?= $f['id'] ?></strong></td><td><?= htmlspecialchars($f['name']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 style="font-weight:700;color:#1a2a4a;">Departments</h6>
                    <table class="table table-sm">
                        <thead><tr><th>ID</th><th>Name</th></tr></thead>
                        <tbody>
                            <?php foreach ($departments as $d): ?>
                                <tr><td><strong><?= $d['id'] ?></strong></td><td><?= htmlspecialchars($d['name']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="main-footer">
    <strong>Dormitory Management System</strong> &copy; <?= date('Y') ?> | The Polytechnic Ibadan
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('active'); }
    toggleBtn?.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('active'); });
    closeBtn?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    const input = document.getElementById('csvInput');
    const area = document.getElementById('uploadArea');
    const fileName = document.getElementById('fileName');

    input?.addEventListener('change', function() {
        fileName.textContent = this.files[0] ? '✓ ' + this.files[0].name : '';
    });

    area?.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('dragover'); });
    area?.addEventListener('dragleave', () => area.classList.remove('dragover'));
    area?.addEventListener('drop', e => {
        e.preventDefault();
        area.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            fileName.textContent = '✓ ' + e.dataTransfer.files[0].name;
        }
    });
});
</script>

<script>
/* ========== UNIVERSAL SIDEBAR + ANIMATIONS ========== */
document.addEventListener('DOMContentLoaded', function() {
    const sidebar  = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const overlay  = document.getElementById('sidebarOverlay');
    const body     = document.body;

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        overlay?.classList.add('active');
        body.classList.add('sidebar-open');
        body.style.overflow = 'hidden';
        if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-times"></i>';
    }
    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        overlay?.classList.remove('active');
        body.classList.remove('sidebar-open');
        body.style.overflow = '';
        if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
    }
    function toggleSidebar() {
        if (sidebar?.classList.contains('open')) closeSidebar();
        else openSidebar();
    }

    toggleBtn?.addEventListener('click', toggleSidebar);
    closeBtn?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    // ESC key closes sidebar
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeSidebar();
    });

    // Auto-close on link click (mobile)
    if (window.innerWidth <= 991.98) {
        document.querySelectorAll('.nav-sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (!link.closest('.has-treeview')) {
                    setTimeout(closeSidebar, 200);
                }
            });
        });
    }

    // Handle resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (window.innerWidth >= 992) {
                sidebar?.classList.remove('open');
                overlay?.classList.remove('active');
                body.classList.remove('sidebar-open');
                body.style.overflow = '';
                if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
            }
        }, 200);
    });

    // Treeview toggle
    document.querySelectorAll('.has-treeview > .nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const parent = this.closest('.has-treeview');
            const submenu = parent.querySelector('.nav-treeview');
            document.querySelectorAll('.has-treeview').forEach(item => {
                if (item !== parent) {
                    item.classList.remove('menu-open');
                    item.querySelector('.nav-treeview')?.classList.remove('show');
                }
            });
            parent.classList.toggle('menu-open');
            submenu?.classList.toggle('show');
        });
    });

    // Add animation to table rows and cards on scroll (IntersectionObserver)
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.05 });

        document.querySelectorAll('.card, .stat-card').forEach(el => {
            if (el.dataset.observed) return;
            el.dataset.observed = '1';
            io.observe(el);
        });
    }
});
</script>
</body>
</html>