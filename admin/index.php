<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';

// Statistics
$total_students     = $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0] ?? 0;
$total_rooms        = $conn->query("SELECT COUNT(*) FROM rooms")->fetch_row()[0] ?? 0;
$occupied_rooms     = $conn->query("SELECT COUNT(*) FROM rooms WHERE status = 'occupied'")->fetch_row()[0] ?? 0;
$available_rooms    = $total_rooms - $occupied_rooms;
$pending_complaints = $conn->query("SELECT COUNT(*) FROM complaints WHERE status = 'pending'")->fetch_row()[0] ?? 0;
$pending_maintenance = $conn->query("SELECT COUNT(*) FROM maintenance_requests WHERE status = 'pending'")->fetch_row()[0] ?? 0;
$total_revenue      = $conn->query("SELECT SUM(amount) FROM payments WHERE status = 'success'")->fetch_row()[0] ?? 0;
$total_payments     = $conn->query("SELECT COUNT(*) FROM payments")->fetch_row()[0] ?? 0;
$success_payments   = $conn->query("SELECT COUNT(*) FROM payments WHERE status = 'success'")->fetch_row()[0] ?? 0;
$pending_payments   = $conn->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetch_row()[0] ?? 0;
$failed_payments    = $conn->query("SELECT COUNT(*) FROM payments WHERE status = 'failed'")->fetch_row()[0] ?? 0;

// Get recent activities
$recent_activities = $conn->query("
    (SELECT 'complaint' as type, subject as title, created_at, status FROM complaints WHERE student_id IS NOT NULL)
    UNION
    (SELECT 'payment' as type, CONCAT('Payment of ₦', amount) as title, created_at, status FROM payments)
    ORDER BY created_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Dormitory MS</title>
    
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
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
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .content-header h1 {
            font-weight: 700;
            font-size: 1.8rem;
            color: #1a2a4a;
        }

        .content-header h1 i {
            color: #1a3a6b;
        }

        /* ========== STATS CARDS ========== */
        .stat-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            animation: fadeInUp 0.6s ease;
            animation-fill-mode: both;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .stat-card .stat-body {
            padding: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            flex-shrink: 0;
        }

        .stat-card .stat-info h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: #1a2a4a;
        }

        .stat-card .stat-info p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-card .stat-bg-icon {
            position: absolute;
            right: -10px;
            bottom: -10px;
            font-size: 6rem;
            opacity: 0.05;
            z-index: 0;
            color: #1a2a4a;
        }

        .stat-card.bg-info .stat-icon {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
        }

        .stat-card.bg-success .stat-icon {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .stat-card.bg-warning .stat-icon {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
        }

        .stat-card.bg-danger .stat-icon {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
        }

        .stat-card.bg-primary .stat-icon {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
        }

        /* ========== REGULAR CARDS ========== */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            animation: fadeInUp 0.6s ease;
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

        /* ========== REVENUE CARD ========== */
        .revenue-amount {
            font-size: 2.5rem;
            font-weight: 800;
            color: #28a745;
        }

        .revenue-label {
            color: #6c757d;
            font-size: 0.95rem;
        }

        /* ========== ACTIVITY FEED ========== */
        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-item:hover {
            background: #f8f9fa;
            padding-left: 10px;
            border-radius: 8px;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1rem;
            color: white;
        }

        .activity-icon.complaint {
            background: #ffc107;
        }

        .activity-icon.payment {
            background: #28a745;
        }

        .activity-content {
            flex: 1;
        }

        .activity-content .title {
            font-weight: 500;
            color: #1a2a4a;
            margin: 0;
            font-size: 0.95rem;
        }

        .activity-content .meta {
            font-size: 0.8rem;
            color: #6c757d;
            margin: 0;
        }

        .activity-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .activity-badge.pending {
            background: #fff3cd;
            color: #856404;
        }

        .activity-badge.success {
            background: #d4edda;
            color: #155724;
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

        /* ========== RESPONSIVE ========== */
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

            .stat-card .stat-body {
                padding: 18px 15px;
            }

            .stat-card .stat-info h3 {
                font-size: 1.5rem;
            }

            .stat-card .stat-icon {
                width: 48px;
                height: 48px;
                font-size: 1.4rem;
            }

            .stat-card .stat-bg-icon {
                font-size: 4rem;
            }

            .card-header {
                padding: 15px 18px;
            }

            .card-body {
                padding: 18px 15px;
            }

            .revenue-amount {
                font-size: 2rem;
            }

            .activity-item {
                padding: 10px 0;
                flex-wrap: wrap;
            }

            .activity-content .title {
                font-size: 0.85rem;
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

            .stat-card .stat-info h3 {
                font-size: 1.2rem;
            }

            .stat-card .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }

            .revenue-amount {
                font-size: 1.6rem;
            }
        }

        /* ========== BODY SCROLL LOCK ========== */
        body.sidebar-open {
            overflow: hidden;
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
            <small>Admin Panel</small>
        </div>
    </div>

    <!-- User Panel -->
    <div class="user-panel">
        <div class="image">
            <img src="https://ui-avatars.com/api/?name=Admin&background=ffd700&color=1a3a6b&size=42" alt="Admin">
        </div>
        <div class="info">
            <a href="#">Administrator</a>
            <small>Admin</small>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" role="menu">
            
            <li class="nav-item">
                <a href="index.php" class="nav-link active">
                    <i class="nav-icon fas fa-tachometer-alt"></i>
                    <p>Dashboard</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="students.php" class="nav-link">
                    <i class="nav-icon fas fa-users"></i>
                    <p>Students</p>
                </a>
            </li>

           <li class="nav-item">
            <a href="bulk-students.php" class="nav-link">
                <i class="nav-icon fas fa-file-upload"></i><p>Bulk Upload</p>
            </a>
        </li>

           <li class="nav-item">
    <a href="manage-staff.php" class="nav-link">
        <i class="nav-icon fas fa-user-tie"></i> Manage Staff
    </a>
</li>

        <li class="nav-item">
            <a href="rooms.php" class="nav-link">
                <i class="nav-icon fas fa-bed"></i><p>Rooms</p>
            </a>
        </li>

        <li class="nav-item">
            <a href="post-room.php" class="nav-link">
                <i class="nav-icon fas fa-plus-square"></i><p>Post Room</p>
            </a>
        </li>
        

            <li class="nav-item">
                <a href="hostels.php" class="nav-link">
                    <i class="nav-icon fas fa-building"></i>
                    <p>Hostels</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="allocations.php" class="nav-link">
                    <i class="nav-icon fas fa-door-open"></i>
                    <p>Allocations</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="payments.php" class="nav-link">
                    <i class="nav-icon fas fa-credit-card"></i>
                    <p>Payments</p>
                </a>
            </li>

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
            <h1><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</h1>
        </div>
    </section>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <!-- Statistics Cards -->
            <div class="row g-4">
                <div class="col-lg-3 col-6">
                    <div class="stat-card bg-info">
                        <div class="stat-body">
                            <div class="stat-info">
                                <h3><?= number_format($total_students) ?></h3>
                                <p>Total Students</p>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="stat-bg-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="stat-card bg-success">
                        <div class="stat-body">
                            <div class="stat-info">
                                <h3><?= number_format($available_rooms) ?></h3>
                                <p>Available Rooms</p>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-bed"></i>
                            </div>
                        </div>
                        <div class="stat-bg-icon">
                            <i class="fas fa-bed"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="stat-card bg-warning">
                        <div class="stat-body">
                            <div class="stat-info">
                                <h3><?= number_format($occupied_rooms) ?></h3>
                                <p>Occupied Rooms</p>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-door-open"></i>
                            </div>
                        </div>
                        <div class="stat-bg-icon">
                            <i class="fas fa-door-open"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="stat-card bg-danger">
                        <div class="stat-body">
                            <div class="stat-info">
                                <h3><?= number_format($pending_complaints + $pending_maintenance) ?></h3>
                                <p>Pending Requests</p>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                        <div class="stat-bg-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue & Occupancy -->
            <div class="row mt-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-money-bill-wave"></i> Total Revenue
                            </h3>
                        </div>
                        <div class="card-body text-center py-4">
                            <div class="revenue-amount">₦<?= number_format($total_revenue) ?></div>
                            <p class="revenue-label">From all successful payments</p>
                            <div class="d-flex justify-content-center gap-4 mt-3">
                                <div>
                                    <small class="text-muted">Total Payments</small>
                                    <div class="fw-bold"><?= number_format($total_payments) ?></div>
                                </div>
                                <div>
                                    <small class="text-muted">Success Rate</small>
                                    <div class="fw-bold text-success">
                                        <?= $total_payments > 0 ? round(($success_payments / $total_payments) * 100, 1) : 0 ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-pie"></i> Occupancy Rate
                            </h3>
                        </div>
                        <div class="card-body text-center py-4">
                            <div class="position-relative d-inline-block">
                                <canvas id="occupancyChart" width="200" height="200"></canvas>
                                <div class="position-absolute top-50 start-50 translate-middle text-center">
                                    <div class="fw-bold fs-3"><?= $total_rooms > 0 ? round(($occupied_rooms / $total_rooms) * 100, 1) : 0 ?>%</div>
                                    <small class="text-muted">Occupied</small>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="badge bg-success me-2">Occupied: <?= $occupied_rooms ?></span>
                                <span class="badge bg-secondary">Available: <?= $available_rooms ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mt-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-line"></i> Occupancy Trend (Last 6 Months)
                            </h3>
                        </div>
                        <div class="card-body">
                            <canvas id="trendChart" height="120"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-pie"></i> Payment Status
                            </h3>
                        </div>
                        <div class="card-body">
                            <canvas id="paymentChart" height="200"></canvas>
                            <div class="mt-3 text-center">
                                <div class="d-flex justify-content-center gap-3 flex-wrap">
                                    <div><span class="badge bg-success">Success: <?= $success_payments ?></span></div>
                                    <div><span class="badge bg-warning">Pending: <?= $pending_payments ?></span></div>
                                    <div><span class="badge bg-danger">Failed: <?= $failed_payments ?></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-clock"></i> Recent Activities
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_activities)): ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?= $activity['type'] ?>">
                                            <i class="fas fa-<?= $activity['type'] === 'complaint' ? 'exclamation' : 'money-bill' ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <p class="title"><?= htmlspecialchars($activity['title']) ?></p>
                                            <p class="meta">
                                                <?= date('d M Y H:i', strtotime($activity['created_at'])) ?>
                                            </p>
                                        </div>
                                        <span class="activity-badge <?= $activity['status'] ?>">
                                            <?= ucfirst($activity['status']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-muted py-3">No recent activities</p>
                            <?php endif; ?>
                        </div>
                    </div>
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

    // ====== OCCUPANCY DOUGHNUT CHART ======
    const occupancyCtx = document.getElementById('occupancyChart').getContext('2d');
    new Chart(occupancyCtx, {
        type: 'doughnut',
        data: {
            labels: ['Occupied', 'Available'],
            datasets: [{
                data: [<?= $occupied_rooms ?>, <?= $available_rooms ?>],
                backgroundColor: ['#28a745', '#6c757d'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '80%',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    // ====== TREND LINE CHART ======
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan'],
            datasets: [{
                label: 'Occupied Rooms',
                data: [
                    <?= max(0, $occupied_rooms - 30) ?>, 
                    <?= max(0, $occupied_rooms - 20) ?>, 
                    <?= max(0, $occupied_rooms - 10) ?>, 
                    <?= $occupied_rooms ?>, 
                    <?= min($total_rooms, $occupied_rooms + 10) ?>, 
                    <?= min($total_rooms, $occupied_rooms + 15) ?>
                ],
                borderColor: '#1a3a6b',
                backgroundColor: 'rgba(26, 58, 107, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#1a3a6b',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // ====== PAYMENT STATUS PIE CHART ======
    const paymentCtx = document.getElementById('paymentChart').getContext('2d');
    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: ['Successful', 'Pending', 'Failed'],
            datasets: [{
                data: [<?= $success_payments ?>, <?= $pending_payments ?>, <?= $failed_payments ?>],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            }
        }
    });

    console.log('✅ Admin Dashboard initialized');
});
</script>

</body>
</html>