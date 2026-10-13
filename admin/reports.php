<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';
include 'log_action.php';

$page_title = 'Reports & Analytics';

// ===== OVERVIEW STATS =====
$total_students   = (int)($conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'] ?? 0);
$allocated        = (int)($conn->query("SELECT COUNT(*) c FROM allocations WHERE status='active'")->fetch_assoc()['c'] ?? 0);
$unallocated      = max(0, $total_students - $allocated);
$total_rooms      = (int)($conn->query("SELECT COUNT(*) c FROM rooms")->fetch_assoc()['c'] ?? 0);
$available_rooms  = (int)($conn->query("SELECT COUNT(*) c FROM rooms WHERE status='available'")->fetch_assoc()['c'] ?? 0);
$occupied_rooms   = (int)($conn->query("SELECT COUNT(*) c FROM rooms WHERE status='occupied'")->fetch_assoc()['c'] ?? 0);
$total_hostels    = (int)($conn->query("SELECT COUNT(*) c FROM hostels WHERE status='active'")->fetch_assoc()['c'] ?? 0);
$total_capacity   = (int)($conn->query("SELECT COALESCE(SUM(capacity),0) c FROM rooms")->fetch_assoc()['c'] ?? 0);
$occupancy_rate   = $total_capacity > 0 ? ($allocated / $total_capacity) * 100 : 0;

// Payments
$payments_date_col = 'paid_at';
$chk = $conn->query("SHOW COLUMNS FROM payments LIKE 'paid_at'");
if (!$chk || $chk->num_rows === 0) $payments_date_col = 'created_at';

$total_collected = (float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed'")->fetch_assoc()['s'] ?? 0);
$total_pending   = (float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='pending'")->fetch_assoc()['s'] ?? 0);
$total_failed    = (float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='failed'")->fetch_assoc()['s'] ?? 0);

// Revenue by month (last 6)
$revenue_by_month = $conn->query("
    SELECT DATE_FORMAT($payments_date_col, '%Y-%m') AS ym,
           DATE_FORMAT($payments_date_col, '%b %Y') AS label,
           SUM(amount) AS total, COUNT(*) AS count
    FROM payments
    WHERE status='completed'
      AND $payments_date_col >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
      AND $payments_date_col IS NOT NULL
    GROUP BY ym ORDER BY ym ASC
");

// Occupancy by hostel
$occupancy = $conn->query("
    SELECT h.name,
           COALESCE(SUM(r.capacity),0) AS capacity,
           (SELECT COUNT(*) FROM allocations a 
            JOIN rooms r2 ON a.room_id=r2.id 
            WHERE r2.hostel_id=h.id AND a.status='active') AS occupied
    FROM hostels h
    LEFT JOIN rooms r ON r.hostel_id=h.id
    WHERE h.status='active'
    GROUP BY h.id ORDER BY h.name
");

// Bed spacing distribution
$spacing_dist = $conn->query("
    SELECT bed_spacing, COUNT(*) AS cnt, SUM(capacity) AS total_beds
    FROM rooms GROUP BY bed_spacing ORDER BY cnt DESC
");

// Gender distribution
$gender_dist = $conn->query("SELECT gender, COUNT(*) AS cnt FROM students GROUP BY gender");

// Level distribution
$level_dist = $conn->query("SELECT level, COUNT(*) AS cnt FROM students GROUP BY level ORDER BY level");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="preconnect" href="https://cdnjs.cloudflare.com">
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
        .stat-card { background:white; border-radius:16px; padding:22px; box-shadow:0 4px 20px rgba(0,0,0,0.06); display:flex; align-items:center; gap:18px; height:100%; transition:all 0.3s ease; }
        .stat-card:hover { transform:translateY(-4px); box-shadow:0 12px 35px rgba(0,0,0,0.1); }
        .stat-card .stat-icon { width:58px; height:58px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; color:white; flex-shrink:0; }
        .stat-card .stat-info h3 { font-weight:800; font-size:1.6rem; color:#1a2a4a; margin:0; }
        .stat-card .stat-info p { margin:4px 0 0; color:#6c757d; font-size:0.85rem; }
        .bg-gradient-blue { background:linear-gradient(135deg,#1a3a6b,#2c5aa0) !important; }
        .bg-gradient-green { background:linear-gradient(135deg,#28a745,#20c997) !important; }
        .bg-gradient-orange { background:linear-gradient(135deg,#fd7e14,#ffb700) !important; }
        .bg-gradient-red { background:linear-gradient(135deg,#dc3545,#c82333) !important; }
        .bg-gradient-purple { background:linear-gradient(135deg,#6f42c1,#8b5cf6) !important; }
        .bg-gradient-teal { background:linear-gradient(135deg,#17a2b8,#20c997) !important; }
        .bg-gradient-pink { background:linear-gradient(135deg,#e83e8c,#ff6fa8) !important; }
        .card { border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.06); margin-bottom:20px; }
        .card-header { background:white; border-bottom:2px solid #f0f2f5; padding:18px 25px; border-radius:16px 16px 0 0 !important; }
        .card-body { padding:25px; }
        .table { margin:0; }
        .table thead th { background:#f8f9fa; color:#1a2a4a; font-weight:700; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; border-bottom:2px solid #e9ecef; padding:14px 15px; }
        .table tbody td { padding:14px 15px; border-bottom:1px solid #f0f2f5; font-size:0.9rem; }
        .main-footer { background:white; padding:15px 20px; border-top:1px solid #e9ecef; text-align:center; font-size:0.9rem; color:#6c757d; margin-left:280px; }
        .progress { height:10px; border-radius:5px; background:#e9ecef; }
        .progress-bar { border-radius:5px; }
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
        <li class="nav-item"><a href="bulk-students.php" class="nav-link"><i class="nav-icon fas fa-file-upload"></i><p>Bulk Upload</p></a></li>
        <li class="nav-item"><a href="manage-staff.php" class="nav-link"><i class="nav-icon fas fa-user-tie"></i><p>Manage Staff</p></a></li>
        <li class="nav-item"><a href="rooms.php" class="nav-link"><i class="nav-icon fas fa-bed"></i><p>Rooms</p></a></li>
        <li class="nav-item"><a href="post-room.php" class="nav-link"><i class="nav-icon fas fa-plus-square"></i><p>Post Room</p></a></li>
        <li class="nav-item"><a href="hostels.php" class="nav-link"><i class="nav-icon fas fa-building"></i><p>Hostels</p></a></li>
        <li class="nav-item"><a href="allocations.php" class="nav-link"><i class="nav-icon fas fa-door-open"></i><p>Allocations</p></a></li>
        <li class="nav-item"><a href="payments.php" class="nav-link"><i class="nav-icon fas fa-credit-card"></i><p>Payments</p></a></li>
        <li class="nav-item"><a href="reports.php" class="nav-link active"><i class="nav-icon fas fa-chart-line"></i><p>Reports</p></a></li>
        <li class="nav-item"><a href="logs.php" class="nav-link"><i class="nav-icon fas fa-history"></i><p>Activity Logs</p></a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
    </ul>
</aside>

<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fas fa-chart-line me-2"></i>Reports & Analytics</h1>
    </section>

    <!-- Overview Stats Row 1 -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-blue"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?= number_format($total_students) ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-green"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3><?= number_format($allocated) ?></h3>
                    <p>Allocated</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-orange"><i class="fas fa-user-clock"></i></div>
                <div class="stat-info">
                    <h3><?= number_format($unallocated) ?></h3>
                    <p>Unallocated</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-teal"><i class="fas fa-percentage"></i></div>
                <div class="stat-info">
                    <h3><?= number_format($occupancy_rate, 1) ?>%</h3>
                    <p>Occupancy Rate</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Overview Stats Row 2 -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-purple"><i class="fas fa-hotel"></i></div>
                <div class="stat-info">
                    <h3><?= number_format($total_hostels) ?></h3>
                    <p>Active Hostels</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-blue"><i class="fas fa-door-open"></i></div>
                <div class="stat-info">
                    <h3><?= number_format($total_rooms) ?></h3>
                    <p>Total Rooms</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-green"><i class="fas fa-bed"></i></div>
                <div class="stat-info">
                    <h3><?= number_format($available_rooms) ?></h3>
                    <p>Available Rooms</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-orange"><i class="fas fa-door-closed"></i></div>
                <div class="stat-info">
                    <h3><?= number_format($occupied_rooms) ?></h3>
                    <p>Occupied Rooms</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Stats -->
    <div class="row g-3 mb-4">
        <div class="col-lg-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-green"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-info">
                    <h3 style="font-size:1.3rem;">₦<?= number_format($total_collected, 0) ?></h3>
                    <p>Total Collected</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-orange"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-info">
                    <h3 style="font-size:1.3rem;">₦<?= number_format($total_pending, 0) ?></h3>
                    <p>Pending Payments</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-red"><i class="fas fa-times-circle"></i></div>
                <div class="stat-info">
                    <h3 style="font-size:1.3rem;">₦<?= number_format($total_failed, 0) ?></h3>
                    <p>Failed Payments</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Revenue by Month -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Revenue (Last 6 Months)</h3>
                </div>
                <div class="card-body p-0">
                    <?php if ($revenue_by_month && $revenue_by_month->num_rows > 0): ?>
                    <table class="table">
                        <thead><tr><th>Month</th><th>Transactions</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php $grand = 0; while ($r = $revenue_by_month->fetch_assoc()): $grand += $r['total']; ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['label']) ?></strong></td>
                                <td><?= (int)$r['count'] ?></td>
                                <td><strong style="color:#28a745;">₦<?= number_format((float)$r['total'], 0) ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                            <tr style="background:#f8f9fa;">
                                <td colspan="2"><strong>Grand Total</strong></td>
                                <td><strong style="color:#1a3a6b;font-size:1.05rem;">₦<?= number_format($grand, 0) ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p class="text-muted text-center py-4 mb-0">No revenue data yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Occupancy by Hostel -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Hostel Occupancy</h3>
                </div>
                <div class="card-body">
                    <?php if ($occupancy && $occupancy->num_rows > 0): 
                        while ($o = $occupancy->fetch_assoc()): 
                            $cap = (int)$o['capacity'];
                            $occ = (int)$o['occupied'];
                            $pct = $cap > 0 ? ($occ / $cap) * 100 : 0;
                            $color = $pct >= 90 ? 'danger' : ($pct >= 60 ? 'warning' : 'success');
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <strong style="color:#1a2a4a;"><?= htmlspecialchars($o['name']) ?></strong>
                            <span style="font-size:0.85rem;color:#6c757d;"><?= $occ ?>/<?= $cap ?> (<?= number_format($pct,0) ?>%)</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endwhile; endif; ?>
                </div>
            </div>
        </div>

        <!-- Bed Spacing Distribution -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-bed me-2"></i>Bed Spacing Distribution</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead><tr><th>Spacing</th><th>Rooms</th><th>Total Beds</th></tr></thead>
                        <tbody>
                            <?php if ($spacing_dist && $spacing_dist->num_rows > 0): 
                                while ($s = $spacing_dist->fetch_assoc()): ?>
                            <tr>
                                <td><span class="badge" style="background:#fff4cc;color:#856404;padding:6px 12px;border-radius:8px;">
                                    <i class="fas fa-bed me-1"></i><?= htmlspecialchars(ucfirst($s['bed_spacing'] ?? 'Standard')) ?>
                                </span></td>
                                <td><strong><?= (int)$s['cnt'] ?></strong></td>
                                <td><?= (int)$s['total_beds'] ?></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="3" class="text-muted text-center py-4">No data.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Gender Distribution -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-venus-mars me-2"></i>Students by Gender</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead><tr><th>Gender</th><th>Count</th></tr></thead>
                        <tbody>
                            <?php if ($gender_dist && $gender_dist->num_rows > 0): 
                                while ($g = $gender_dist->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <i class="fas fa-<?= strtolower($g['gender']) === 'female' ? 'venus' : 'mars' ?> me-2" style="color:#1a3a6b;"></i>
                                    <strong><?= htmlspecialchars($g['gender'] ?: 'Unspecified') ?></strong>
                                </td>
                                <td><strong><?= (int)$g['cnt'] ?></strong></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="2" class="text-muted text-center py-4">No data.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Level Distribution -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Students by Level</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead><tr><th>Level</th><th>Count</th></tr></thead>
                        <tbody>
                            <?php if ($level_dist && $level_dist->num_rows > 0): 
                                while ($l = $level_dist->fetch_assoc()): ?>
                            <tr>
                                <td><span class="badge bg-primary" style="padding:6px 14px;"><?= htmlspecialchars($l['level'] ?: 'N/A') ?></span></td>
                                <td><strong><?= (int)$l['cnt'] ?></strong></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="2" class="text-muted text-center py-4">No data.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="students.php" class="btn btn-outline-secondary text-start">
                            <i class="fas fa-users me-2"></i>View All Students
                        </a>
                        <a href="allocations.php" class="btn btn-outline-secondary text-start">
                            <i class="fas fa-door-open me-2"></i>View Allocations
                        </a>
                        <a href="payments.php" class="btn btn-outline-secondary text-start">
                            <i class="fas fa-credit-card me-2"></i>View Payments
                        </a>
                        <a href="logs.php" class="btn btn-outline-secondary text-start">
                            <i class="fas fa-history me-2"></i>View Activity Logs
                        </a>
                    </div>
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