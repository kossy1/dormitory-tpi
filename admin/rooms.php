<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';

$success = $error = '';

// ====== CHECK TABLE STRUCTURE ======
$hostel_exists = $conn->query("SHOW TABLES LIKE 'hostels'")->num_rows > 0;

$has_hostel_id = false;
if ($hostel_exists) {
    $column_check = $conn->query("SHOW COLUMNS FROM rooms LIKE 'hostel_id'");
    $has_hostel_id = $column_check && $column_check->num_rows > 0;
}

// ====== FETCH HOSTELS ======
$hostels = [];
if ($hostel_exists) {
    $result = $conn->query("SELECT id, name FROM hostels ORDER BY name ASC");
    if ($result) {
        $hostels = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// ====== ADD NEW ROOM ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
    $room_number = trim($_POST['room_number'] ?? '');
    $hostel_id   = isset($_POST['hostel_id']) ? (int)$_POST['hostel_id'] : 0;
    $block       = trim($_POST['block'] ?? '');
    $capacity    = (int)($_POST['capacity'] ?? 0);
    $gender      = $_POST['gender'] ?? '';
    $price       = (float)($_POST['price'] ?? 0);

    // Validation with clear messages
    if (empty($room_number)) {
        $error = "Room number is required.";
    } elseif (!$hostel_exists) {
        $error = "The 'hostels' table does not exist. Please run the database setup SQL.";
    } elseif (!$has_hostel_id) {
        $error = "The 'rooms' table is missing the 'hostel_id' column. Please run: ALTER TABLE rooms ADD COLUMN hostel_id INT DEFAULT NULL;";
    } elseif (empty($hostels)) {
        $error = "No hostels found. Please add a hostel first from the Hostels page.";
    } elseif ($hostel_id <= 0) {
        $error = "Please select a valid hostel.";
    } elseif ($capacity <= 0) {
        $error = "Capacity must be a positive number.";
    } elseif (!in_array($gender, ['Male', 'Female', 'Mixed'])) {
        $error = "Please select a valid gender.";
    } elseif ($price <= 0) {
        $error = "Price must be a positive number.";
    } else {
        // Check duplicate room number in same hostel
        $check = $conn->prepare("SELECT id FROM rooms WHERE room_number = ? AND hostel_id = ?");
        $check->bind_param("si", $room_number, $hostel_id);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error = "Room number '$room_number' already exists in this hostel. Please use a different room number.";
        } else {
            // Insert room
            $stmt = $conn->prepare("
                INSERT INTO rooms (room_number, hostel_id, block, capacity, gender, price, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'available')
            ");
            
            if (!$stmt) {
                $error = "SQL prepare error: " . $conn->error;
            } else {
                $stmt->bind_param("sisisd", $room_number, $hostel_id, $block, $capacity, $gender, $price);
                
                if ($stmt->execute()) {
                    $success = "Room '$room_number' added successfully!";
                } else {
                    $error = "Failed to add room: " . $stmt->error;
                }
            }
        }
    }
}

// ====== DELETE ROOM ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_room'])) {
    $room_id = (int)$_POST['room_id'];

    $check = $conn->query("SELECT status FROM rooms WHERE id = $room_id")->fetch_assoc();

    if ($check && $check['status'] === 'occupied') {
        $error = "Cannot delete an occupied room. Please deallocate first.";
    } else {
        if ($conn->query("DELETE FROM rooms WHERE id = $room_id")) {
            $success = "Room deleted successfully.";
        } else {
            $error = "Failed to delete room: " . $conn->error;
        }
    }
}

// ====== FETCH ROOMS ======
$rooms = [];
if ($has_hostel_id) {
    $result = $conn->query("
        SELECT r.*, h.name as hostel_name 
        FROM rooms r 
        LEFT JOIN hostels h ON r.hostel_id = h.id 
        ORDER BY h.name, r.room_number ASC
    ");
    if ($result) $rooms = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query("SELECT * FROM rooms ORDER BY room_number ASC");
    if ($result) $rooms = $result->fetch_all(MYSQLI_ASSOC);
}

// ====== STATISTICS ======
$total_rooms = count($rooms);
$available_rooms = 0;
$occupied_rooms = 0;
$total_capacity = 0;

foreach ($rooms as $room) {
    $status = $room['status'] ?? 'available';
    if ($status === 'available') $available_rooms++;
    if ($status === 'occupied') $occupied_rooms++;
    $total_capacity += (int)($room['capacity'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Rooms - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; }
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
        .user-panel a { color: white; font-weight: 600; text-decoration: none; }
        .user-panel small { color: rgba(255,255,255,0.6); display: block; font-size: 0.75rem; }
        .nav-sidebar { padding: 8px 0; }
        .nav-sidebar .nav-item { margin: 2px 8px; }
        .nav-sidebar .nav-link { color: rgba(255,255,255,0.75); padding: 10px 16px; border-radius: 10px; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .nav-sidebar .nav-link:hover { color: white; background: rgba(255,255,255,0.1); }
        .nav-sidebar .nav-link.active { color: white; background: rgba(255,215,0,0.15); border-left: 3px solid #ffd700; }
        .nav-sidebar .nav-icon { width: 22px; text-align: center; color: rgba(255,255,255,0.6); }
        .nav-sidebar .nav-link.active .nav-icon { color: #ffd700; }
        .nav-treeview { padding-left: 15px; max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
        .nav-treeview.show { max-height: 500px; }
        .nav-treeview .nav-link { padding: 8px 16px 8px 35px; font-size: 0.85rem; color: rgba(255,255,255,0.6); }
        .content-wrapper { min-height: 100vh; padding: 20px; transition: margin-left 0.3s ease; }
        .content-header { padding-bottom: 15px; border-bottom: 2px solid #e9ecef; margin-bottom: 20px; }
        .content-header h1 { font-weight: 700; font-size: 1.8rem; color: #1a2a4a; }
        .stat-card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .stat-card .stat-body { padding: 20px 25px; display: flex; align-items: center; justify-content: space-between; }
        .stat-card .stat-icon { width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; color: white; }
        .stat-card .stat-info h3 { font-size: 1.8rem; font-weight: 700; margin: 0; color: #1a2a4a; }
        .stat-card .stat-info p { margin: 0; color: #6c757d; font-size: 0.9rem; }
        .stat-card.bg-info .stat-icon { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
        .stat-card.bg-success .stat-icon { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-card.bg-warning .stat-icon { background: linear-gradient(135deg, #ffc107, #fd7e14); }
        .stat-card.bg-primary .stat-icon { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .card-header { background: white; border-bottom: 2px solid #f0f2f5; padding: 18px 25px; border-radius: 16px 16px 0 0 !important; }
        .card-primary .card-header { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); color: white; }
        .card-primary .card-header .card-title { color: white; }
        .card-body { padding: 25px; }
        .form-label { font-weight: 600; font-size: 0.9rem; color: #1a2a4a; }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 10px; padding: 10px 16px; }
        .form-control:focus, .form-select:focus { border-color: #1a3a6b; box-shadow: 0 0 0 4px rgba(26,58,107,0.12); }
        .table thead th { background: #f8f9fa; color: #1a2a4a; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #e9ecef; padding: 12px 15px; }
        .table tbody td { padding: 12px 15px; border-bottom: 1px solid #f0f2f5; font-size: 0.9rem; }
        .badge { padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 0.75rem; }
        .btn-primary { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); border: none; border-radius: 10px; padding: 10px 24px; font-weight: 600; color: white; }
        .btn-info { border-radius: 8px; padding: 4px 12px; font-size: 0.8rem; }
        .btn-danger { border-radius: 8px; padding: 4px 12px; font-size: 0.8rem; }
        .btn-outline-secondary { border: 2px solid #e9ecef; border-radius: 10px; padding: 8px 20px; font-weight: 600; }
        .alert { border-radius: 12px; border: none; padding: 16px 22px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .alert-danger { background: #fde8e8; color: #721c24; border-left: 5px solid #dc3545; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 5px solid #ffc107; }
        .modal-content { border-radius: 16px; border: none; }
        .modal-header { background: linear-gradient(135deg, #dc3545, #e74c3c); color: white; border-radius: 16px 16px 0 0 !important; }
        .modal-header .btn-close { filter: brightness(0) invert(1); }
        .main-footer { background: white; padding: 15px 20px; border-top: 1px solid #e9ecef; text-align: center; font-size: 0.9rem; color: #6c757d; margin-left: 280px; }
        .hostel-badge { background: #e9ecef; color: #1a2a4a; padding: 4px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; display: inline-block; }
        .empty-state { padding: 60px 20px; text-align: center; }
        .empty-state .empty-icon { font-size: 4rem; color: #d1d5db; margin-bottom: 20px; }
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
            .card-header { padding: 15px 18px; }
            .card-body { padding: 18px 15px; }
            .btn-primary { width: 100%; }
            .btn-outline-secondary.ms-2 { margin-left: 0 !important; margin-top: 8px; }
        }

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
    <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column">
            <li class="nav-item"><a href="index.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
            <li class="nav-item"><a href="students.php" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Students</p></a></li>
           <li class="nav-item"><a href="bulk-students.php" class="nav-link"><i class="nav-icon fas fa-file-upload"></i><p>Bulk Upload</p></a></li>
           <li class="nav-item">
    <a href="manage-staff.php" class="nav-link">
        <i class="nav-icon fas fa-user-tie"></i> Manage Staff
    </a>
</li>
           <li class="nav-item"><a href="rooms.php" class="nav-link active"><i class="nav-icon fas fa-bed"></i><p>Rooms</p></a></li>
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
            <li class="nav-item">
                <a href="reports.php" class="nav-link">
                    <i class="nav-icon fas fa-chart-line"></i><p>Reports</p>
                </a>
            </li>

        <li class="nav-item">
            <a href="logs.php" class="nav-link">
                <i class="nav-icon fas fa-history"></i><p>Activity Logs</p>
            </a>
        </li>
            <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
        </ul>
    </nav>
</aside>

<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fas fa-bed me-2"></i>Manage Rooms</h1>
    </section>

    <section class="content">
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$hostel_exists): ?>
            <div class="alert alert-danger">
                <i class="fas fa-database"></i> 
                <strong>Database Setup Required:</strong> The <code>hostels</code> table doesn't exist. 
                Run the SQL setup in phpMyAdmin first.
            </div>
        <?php elseif (!$has_hostel_id): ?>
            <div class="alert alert-danger">
                <i class="fas fa-database"></i> 
                <strong>Database Setup Required:</strong> The <code>rooms</code> table is missing the <code>hostel_id</code> column. 
                Run this SQL: <code>ALTER TABLE rooms ADD COLUMN hostel_id INT DEFAULT NULL;</code>
            </div>
        <?php elseif (empty($hostels)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>No Hostels Found:</strong> <a href="hostels.php">Add a hostel</a> before creating rooms.
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-6">
                <div class="stat-card bg-info">
                    <div class="stat-body">
                        <div class="stat-info"><h3><?= $total_rooms ?></h3><p>Total Rooms</p></div>
                        <div class="stat-icon"><i class="fas fa-door-open"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="stat-card bg-success">
                    <div class="stat-body">
                        <div class="stat-info"><h3><?= $available_rooms ?></h3><p>Available</p></div>
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="stat-card bg-warning">
                    <div class="stat-body">
                        <div class="stat-info"><h3><?= $occupied_rooms ?></h3><p>Occupied</p></div>
                        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="stat-card bg-primary">
                    <div class="stat-body">
                        <div class="stat-info"><h3><?= $total_capacity ?></h3><p>Capacity</p></div>
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Room Form -->
        <?php if ($hostel_exists && $has_hostel_id && !empty($hostels)): ?>
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-plus-circle"></i> Add New Room</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="addRoomForm">
                    <div class="row g-3">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label">Room Number <span class="text-danger">*</span></label>
                            <input type="text" name="room_number" class="form-control" required 
                                   placeholder="e.g. A101" id="roomNumber"
                                   value="<?= htmlspecialchars($_POST['room_number'] ?? '') ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label">Select Hostel <span class="text-danger">*</span></label>
                            <select name="hostel_id" class="form-select" required>
                                <option value="">-- Select Hostel --</option>
                                <?php foreach ($hostels as $h): ?>
                                    <option value="<?= $h['id'] ?>" <?= (($_POST['hostel_id'] ?? '') == $h['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($h['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label">Block / Wing</label>
                            <input type="text" name="block" class="form-control" 
                                   placeholder="Block A" value="<?= htmlspecialchars($_POST['block'] ?? '') ?>">
                        </div>
                        <div class="col-md-2 col-sm-4">
                            <label class="form-label">Capacity <span class="text-danger">*</span></label>
                            <input type="number" name="capacity" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['capacity'] ?? '2') ?>" min="1" required>
                        </div>
                        <div class="col-md-2 col-sm-4">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male Only</option>
                                <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female Only</option>
                                <option value="Mixed" <?= (($_POST['gender'] ?? '') === 'Mixed') ? 'selected' : '' ?>>Mixed</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-4">
                            <label class="form-label">Price (₦) <span class="text-danger">*</span></label>
                            <input type="number" name="price" class="form-control" 
                                   step="0.01" required placeholder="50000"
                                   value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" name="add_room" class="btn btn-primary" id="addRoomBtn">
                            <i class="fas fa-save me-2"></i>Add Room
                        </button>
                        <button type="reset" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-undo me-2"></i>Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rooms Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list me-2"></i>All Rooms (<?= $total_rooms ?>)</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Room #</th>
                            <th>Hostel</th>
                            <th>Block</th>
                            <th>Capacity</th>
                            <th>Gender</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rooms)): ?>
                            <tr><td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-bed empty-icon"></i>
                                    <h4>No Rooms Found</h4>
                                    <p>Start by adding your first room above.</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($rooms as $room): 
                                $status = $room['status'] ?? 'available';
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($room['room_number']) ?></strong></td>
                                    <td><span class="hostel-badge"><i class="fas fa-building me-1"></i><?= htmlspecialchars($room['hostel_name'] ?? 'N/A') ?></span></td>
                                    <td><?= htmlspecialchars($room['block'] ?? '-') ?></td>
                                    <td><?= (int)$room['capacity'] ?></td>
                                    <td><?= htmlspecialchars($room['gender'] ?? 'N/A') ?></td>
                                    <td>₦<?= number_format((float)$room['price'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $status === 'available' ? 'success' : ($status === 'occupied' ? 'warning' : 'danger') ?>">
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_room.php?id=<?= $room['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                                        <button class="btn btn-sm btn-danger delete-btn" 
                                                data-id="<?= $room['id'] ?>" 
                                                data-room="<?= htmlspecialchars($room['room_number']) ?>"
                                                data-status="<?= $status ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<footer class="main-footer">
    <strong>Dormitory Management System</strong> &copy; <?= date('Y') ?> | The Polytechnic Ibadan
</footer>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong id="deleteRoomName"></strong>?</p>
                <div id="deleteWarning" class="alert alert-warning" style="display:none;">
                    <i class="fas fa-exclamation-triangle me-2"></i>Room is occupied. Cannot delete.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="room_id" id="deleteRoomId">
                    <button type="submit" name="delete_room" class="btn btn-danger" id="confirmDeleteBtn">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    toggleBtn?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
        body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    });
    closeBtn?.addEventListener('click', function() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        body.style.overflow = '';
    });
    overlay?.addEventListener('click', function() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        body.style.overflow = '';
    });

    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('deleteRoomName').textContent = 'Room ' + this.dataset.room;
            document.getElementById('deleteRoomId').value = this.dataset.id;
            const warn = document.getElementById('deleteWarning');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            if (this.dataset.status === 'occupied') {
                warn.style.display = 'block';
                confirmBtn.disabled = true;
                confirmBtn.style.opacity = '0.5';
            } else {
                warn.style.display = 'none';
                confirmBtn.disabled = false;
                confirmBtn.style.opacity = '1';
            }
            deleteModal.show();
        });
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