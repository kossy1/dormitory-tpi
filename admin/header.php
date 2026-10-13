<?php
// ===== SESSION + AUTH (Optional: remove if not needed here) =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detect current page for active nav highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Admin user info (adjust to match your login session keys)
$admin_name  = $_SESSION['admin_name']  ?? $_SESSION['staff_name']  ?? 'Administrator';
$admin_role  = $_SESSION['admin_role']  ?? $_SESSION['staff_role']  ?? 'Admin';
$admin_photo = $_SESSION['admin_photo'] ?? $_SESSION['staff_photo'] ?? '';
$avatar_url  = ($admin_photo && file_exists('../' . $admin_photo))
    ? '../' . htmlspecialchars($admin_photo)
    : 'https://ui-avatars.com/api/?name=' . urlencode($admin_name) . '&background=ffd700&color=1a3a6b&size=42';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>Admin Dashboard - Dormitory MS</title>
    
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
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
        .main-sidebar::-webkit-scrollbar { width: 4px; }
        .main-sidebar::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); }
        .main-sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 4px; }

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

        /* ========== SIDEBAR CLOSE BUTTON ========== */
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
            .sidebar-close-btn { display: block; }
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

        .user-panel .info { flex: 1; }

        .user-panel .info a {
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .user-panel .info a:hover { color: #ffd700; }

        .user-panel .info small {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            display: block;
        }

        /* ========== NAVIGATION ========== */
        .nav-sidebar { padding: 8px 0; }
        .nav-sidebar .nav-item { margin: 2px 8px; }

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

        .nav-sidebar .nav-link.active .nav-icon { color: #ffd700; }

        .nav-sidebar .nav-link p {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 500;
            flex: 1;
        }

        /* ========== TREEVIEW ========== */
        .nav-sidebar .has-treeview > .nav-link { position: relative; }
        .nav-sidebar .has-treeview > .nav-link .right { transition: transform 0.3s ease; }
        .nav-sidebar .has-treeview.menu-open > .nav-link .right { transform: rotate(-90deg); }

        .nav-treeview {
            padding-left: 15px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .nav-treeview.show { max-height: 500px; }

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
        .nav-sidebar .nav-link.text-danger { color: #ff6b6b !important; }
        .nav-sidebar .nav-link.text-danger:hover {
            background: rgba(255, 107, 107, 0.15) !important;
            color: #ff6b6b !important;
        }
        .nav-sidebar .nav-link.text-danger .nav-icon { color: #ff6b6b !important; }

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
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .content-header h1 {
            font-weight: 700;
            font-size: 1.8rem;
            color: #1a2a4a;
        }

        .content-header h1 i { color: #1a3a6b; }

        /* ============================================ */
        /* ========== STATISTICS CARDS (NEW) ========== */
        /* ============================================ */
        
        /* Primary stat cards — icon + number layout */
        .stat-card {
            background: white;
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            padding: 22px;
            display: flex;
            align-items: center;
            gap: 18px;
            height: 100%;
            animation: fadeInUp 0.5s ease;
            animation-fill-mode: both;
        }

        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.10s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.20s; }
        .stat-card:nth-child(5) { animation-delay: 0.25s; }
        .stat-card:nth-child(6) { animation-delay: 0.30s; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.1);
        }

        /* Stat card variants — legacy (bg-info, bg-success, etc.) */
        .stat-card.bg-info    { background: linear-gradient(135deg, #1a3a6b, #2c5aa0); color: white; }
        .stat-card.bg-success { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .stat-card.bg-warning { background: linear-gradient(135deg, #fd7e14, #ffb700); color: white; }
        .stat-card.bg-danger  { background: linear-gradient(135deg, #dc3545, #c82333); color: white; }
        .stat-card.bg-primary { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); color: white; }

        .stat-card.bg-info .stat-info h3,
        .stat-card.bg-success .stat-info h3,
        .stat-card.bg-warning .stat-info h3,
        .stat-card.bg-danger .stat-info h3,
        .stat-card.bg-primary .stat-info h3 { color: white; }

        .stat-card.bg-info .stat-info p,
        .stat-card.bg-success .stat-info p,
        .stat-card.bg-warning .stat-info p,
        .stat-card.bg-danger .stat-info p,
        .stat-card.bg-primary .stat-info p { color: rgba(255, 255, 255, 0.85); }

        .stat-card.bg-info .stat-icon,
        .stat-card.bg-success .stat-icon,
        .stat-card.bg-warning .stat-icon,
        .stat-card.bg-danger .stat-icon,
        .stat-card.bg-primary .stat-icon {
            background: rgba(255, 255, 255, 0.2) !important;
            color: white;
        }

        /* Icon in stat card */
        .stat-card .stat-icon {
            width: 58px;
            height: 58px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        /* Info block */
        .stat-card .stat-info { flex: 1; min-width: 0; }

        .stat-card .stat-info h3 {
            font-size: 1.7rem;
            font-weight: 800;
            margin: 0;
            color: #1a2a4a;
            line-height: 1.1;
        }

        .stat-card .stat-info p {
            margin: 4px 0 0;
            color: #6c757d;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Background decorative icon (for legacy .stat-bg-icon) */
        .stat-card .stat-bg-icon {
            position: absolute;
            right: -15px;
            bottom: -15px;
            font-size: 6rem;
            opacity: 0.05;
            z-index: 0;
            color: #1a2a4a;
            pointer-events: none;
        }

        /* ---- GRADIENT COLOR UTILITIES ---- */
        .bg-gradient-blue   { background: linear-gradient(135deg, #1a3a6b, #2c5aa0) !important; }
        .bg-gradient-green  { background: linear-gradient(135deg, #28a745, #20c997) !important; }
        .bg-gradient-orange { background: linear-gradient(135deg, #fd7e14, #ffb700) !important; }
        .bg-gradient-red    { background: linear-gradient(135deg, #dc3545, #c82333) !important; }
        .bg-gradient-purple { background: linear-gradient(135deg, #6f42c1, #8b5cf6) !important; }
        .bg-gradient-teal   { background: linear-gradient(135deg, #17a2b8, #20c997) !important; }
        .bg-gradient-pink   { background: linear-gradient(135deg, #e83e8c, #ff6fa8) !important; }
        .bg-gradient-dark   { background: linear-gradient(135deg, #343a40, #1d2124) !important; }

        /* Progress bar inside ratio card */
        .stat-card .progress {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
        }

        .stat-card .progress-bar {
            border-radius: 4px;
            transition: width 0.6s ease;
        }

        /* ========== REGULAR CARDS ========== */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            animation: fadeInUp 0.6s ease;
            background: white;
        }

        .card:hover { box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1); }

        .card-header {
            background: white;
            border-bottom: 2px solid #f0f2f5;
            padding: 18px 25px;
            border-radius: 16px 16px 0 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .card-header .card-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #1a2a4a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header .card-title i { color: #1a3a6b; }

        .card-body { padding: 25px; }

        /* ========== TABLE STYLING ========== */
        .table { margin: 0; }

        .table thead th {
            background: #f8f9fa;
            color: #1a2a4a;
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef;
            padding: 14px 15px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 14px 15px;
            border-bottom: 1px solid #f0f2f5;
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .table tbody tr:hover { background: #f8f9fa; }

        /* ========== BADGES ========== */
        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        /* ========== BUTTONS ========== */
        .btn-primary {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            border: none;
            border-radius: 10px;
            padding: 10px 22px;
            font-weight: 600;
            color: white;
            transition: all 0.25s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26, 58, 107, 0.35);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 10px 22px;
            font-weight: 600;
            color: white;
        }

        .btn-success:hover { transform: translateY(-2px); color: white; }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            border-radius: 10px;
            padding: 10px 22px;
            font-weight: 600;
            color: white;
        }

        .btn-danger:hover { transform: translateY(-2px); color: white; }

        .btn-outline-secondary {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 9px 18px;
            font-weight: 600;
            color: #495057;
        }

        .btn-outline-secondary:hover {
            background: #f8f9fa;
            border-color: #ced4da;
            color: #1a2a4a;
        }

        /* ========== FORM CONTROLS ========== */
        .form-label {
            font-weight: 700;
            color: #1a2a4a;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 7px;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.9rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: #1a3a6b;
            box-shadow: 0 0 0 4px rgba(26, 58, 107, 0.1);
        }

        /* ========== ALERTS ========== */
        .alert { border-radius: 12px; border: none; padding: 15px 22px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .alert-danger  { background: #f8d7da; color: #721c24; border-left: 5px solid #dc3545; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 5px solid #ffc107; }
        .alert-info    { background: #e7f3ff; color: #084298; border-left: 5px solid #0d6efd; }

        /* ========== REVENUE CARD ========== */
        .revenue-amount {
            font-size: 2.5rem;
            font-weight: 800;
            color: #28a745;
        }

        .revenue-label { color: #6c757d; font-size: 0.95rem; }

        /* ========== ACTIVITY FEED ========== */
        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .activity-item:last-child { border-bottom: none; }

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

        .activity-icon.complaint { background: #ffc107; }
        .activity-icon.payment   { background: #28a745; }

        .activity-content { flex: 1; }

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

        .activity-badge.pending { background: #fff3cd; color: #856404; }
        .activity-badge.success { background: #d4edda; color: #155724; }

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
            .main-footer { margin-left: 0; }
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

            .sidebar-toggle-btn i { font-size: 1.2rem; }

            .main-sidebar { width: 260px; max-width: 90%; }

            .sidebar-brand { padding: 15px 14px 12px; }
            .sidebar-brand .brand-text { font-size: 0.95rem; }
            .sidebar-brand img { width: 38px; height: 38px; }

            .user-panel { padding: 14px 16px 12px !important; }
            .user-panel .image img { width: 36px; height: 36px; }
            .user-panel .info a { font-size: 0.85rem; }

            .nav-sidebar .nav-link { padding: 8px 14px; font-size: 0.85rem; }

            .content-wrapper { padding: 15px; padding-top: 70px; }

            .sidebar-close-btn {
                top: 8px;
                right: 8px;
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }

            .content-header h1 { font-size: 1.4rem; }

            /* Stat cards responsive */
            .stat-card { padding: 18px 15px; gap: 12px; }
            .stat-card .stat-info h3 { font-size: 1.3rem; }
            .stat-card .stat-icon {
                width: 48px;
                height: 48px;
                font-size: 1.2rem;
            }
            .stat-card .stat-bg-icon { font-size: 4rem; }

            .card-header { padding: 15px 18px; }
            .card-body { padding: 18px 15px; }
            .revenue-amount { font-size: 2rem; }

            .activity-item { padding: 10px 0; flex-wrap: wrap; }
            .activity-content .title { font-size: 0.85rem; }
        }

        @media (max-width: 400px) {
            .main-sidebar { width: 240px; max-width: 92%; }
            .sidebar-brand .brand-text { font-size: 0.85rem; }
            .sidebar-brand img { width: 32px; height: 32px; }
            .nav-sidebar .nav-link { padding: 6px 12px; font-size: 0.8rem; gap: 10px; }
            .nav-sidebar .nav-link .nav-icon { width: 18px; font-size: 0.85rem; }
            .content-wrapper { padding: 10px; padding-top: 65px; }
            .content-header h1 { font-size: 1.2rem; }
            .stat-card .stat-info h3 { font-size: 1.1rem; }
            .stat-card .stat-icon { width: 42px; height: 42px; font-size: 1rem; }
            .revenue-amount { font-size: 1.6rem; }
        }

        /* ========== BODY SCROLL LOCK ========== */
        body.sidebar-open { overflow: hidden; }

        /* ========== MODAL TWEAKS ========== */
        .modal-content { border-radius: 16px !important; border: none !important; }

        .modal-header {
            border-radius: 16px 16px 0 0 !important;
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
            <img src="<?= $avatar_url ?>" alt="Admin">
        </div>
        <div class="info">
            <a href="profile.php"><?= htmlspecialchars($admin_name) ?></a>
            <small><?= htmlspecialchars($admin_role) ?></small>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" role="menu">

            <li class="nav-item">
                <a href="index.php" class="nav-link <?= $current_page === 'index' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-tachometer-alt"></i>
                    <p>Dashboard</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="students.php" class="nav-link <?= $current_page === 'students' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-users"></i>
                    <p>Students</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="bulk-students.php" class="nav-link <?= $current_page === 'bulk-students' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-file-upload"></i>
                    <p>Bulk Upload</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="manage-staff.php" class="nav-link <?= $current_page === 'manage-staff' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-user-tie"></i>
                    <p>Manage Staff</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="rooms.php" class="nav-link <?= $current_page === 'rooms' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-bed"></i>
                    <p>Rooms</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="post-room.php" class="nav-link <?= $current_page === 'post-room' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-plus-square"></i>
                    <p>Post Room</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="hostels.php" class="nav-link <?= $current_page === 'hostels' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-building"></i>
                    <p>Hostels</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="allocations.php" class="nav-link <?= $current_page === 'allocations' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-door-open"></i>
                    <p>Allocations</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="payments.php" class="nav-link <?= $current_page === 'payments' ? 'active' : '' ?>">
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
                <a href="reports.php" class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-chart-line"></i>
                    <p>Reports</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="logs.php" class="nav-link <?= $current_page === 'logs' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-history"></i>
                    <p>Activity Logs</p>
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