<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
include '../config/db.php';

$success = $error = '';
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $status = $_POST['status'];
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    $stmt = $conn->prepare("UPDATE maintenance_requests SET status = ?, admin_notes = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $admin_notes, $id);
    if ($stmt->execute()) {
        $success = "Maintenance request #$id updated successfully.";
    } else {
        $error = "Failed to update maintenance request.";
    }
}

// Build query with filters
$query = "
    SELECT m.*, s.full_name, s.matric_no, r.room_number, r.block
    FROM maintenance_requests m 
    JOIN students s ON m.student_id = s.id 
    LEFT JOIN rooms r ON m.room_id = r.id 
    WHERE 1=1
";

if ($status_filter) {
    $query .= " AND m.status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($search) {
    $search_term = $conn->real_escape_string($search);
    $query .= " AND (s.full_name LIKE '%$search_term%' 
                     OR s.matric_no LIKE '%$search_term%' 
                     OR m.issue_type LIKE '%$search_term%'
                     OR r.room_number LIKE '%$search_term%')";
}

$query .= " ORDER BY m.created_at DESC";

$requests = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_requests = count($requests);
$pending_count = 0;
$in_progress_count = 0;
$resolved_count = 0;

foreach ($requests as $req) {
    if ($req['status'] === 'pending') $pending_count++;
    if ($req['status'] === 'in-progress') $in_progress_count++;
    if ($req['status'] === 'resolved') $resolved_count++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance Requests - Dormitory MS</title>
    
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
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
            color: #fd7e14;
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
            padding: 20px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .stat-card .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: white;
            flex-shrink: 0;
        }

        .stat-card .stat-info h3 {
            font-size: 1.8rem;
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
            font-size: 5rem;
            opacity: 0.05;
            z-index: 0;
            color: #1a2a4a;
        }

        .stat-card.bg-primary .stat-icon {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
        }

        .stat-card.bg-warning .stat-icon {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
        }

        .stat-card.bg-info .stat-icon {
            background: linear-gradient(135deg, #17a2b8, #0dcaf0);
        }

        .stat-card.bg-success .stat-icon {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        /* ========== CARDS ========== */
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
            color: #fd7e14;
        }

        .card-body {
            padding: 25px;
        }

        /* ========== TABLE ========== */
        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #f8f9fa;
            color: #1a2a4a;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef;
            padding: 12px 15px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f2f5;
            font-size: 0.9rem;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
        }

        /* ========== BADGES ========== */
        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .badge.bg-warning {
            background: #ffc107 !important;
            color: #1a2a4a;
        }

        .badge.bg-info {
            background: #17a2b8 !important;
        }

        .badge.bg-success {
            background: #28a745 !important;
        }

        /* ========== BUTTONS ========== */
        .btn-primary {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            border: none;
            border-radius: 8px;
            padding: 6px 16px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(26, 58, 107, 0.3);
            color: white;
        }

        .btn-outline-secondary {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: #f8f9fa;
            border-color: #ced4da;
        }

        /* ========== FILTER & SEARCH ========== */
        .filter-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-wrapper .form-select,
        .filter-wrapper .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 8px 16px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .filter-wrapper .form-select:focus,
        .filter-wrapper .form-control:focus {
            border-color: #fd7e14;
            box-shadow: 0 0 0 4px rgba(253, 126, 20, 0.12);
            background: white;
        }

        .filter-wrapper .form-control {
            min-width: 200px;
        }

        /* ========== ALERTS ========== */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 22px;
            animation: slideDown 0.5s ease;
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

        /* ========== MODAL ========== */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, #fd7e14, #ffc107);
            color: white;
            border-radius: 16px 16px 0 0 !important;
            padding: 18px 25px;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-title {
            font-weight: 700;
        }

        .modal-title i {
            margin-right: 10px;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 15px 25px;
            border-top: 2px solid #f0f2f5;
        }

        /* ========== FORM STYLES ========== */
        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #1a2a4a;
        }

        .form-label i {
            margin-right: 8px;
            color: #fd7e14;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .form-control:focus, .form-select:focus {
            border-color: #fd7e14;
            box-shadow: 0 0 0 4px rgba(253, 126, 20, 0.12);
            background: white;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
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
                padding: 15px 18px;
            }

            .stat-card .stat-info h3 {
                font-size: 1.4rem;
            }

            .stat-card .stat-icon {
                width: 45px;
                height: 45px;
                font-size: 1.3rem;
            }

            .card-header {
                padding: 15px 18px;
                flex-wrap: wrap;
            }

            .card-header .card-title {
                font-size: 1rem;
                margin-bottom: 10px;
            }

            .card-body {
                padding: 18px 15px;
            }

            .table thead th {
                font-size: 0.7rem;
                padding: 8px 10px;
            }

            .table tbody td {
                padding: 8px 10px;
                font-size: 0.8rem;
            }

            .table-responsive {
                border-radius: 10px;
                overflow-x: auto;
            }

            .filter-wrapper .form-select,
            .filter-wrapper .form-control {
                min-width: 100%;
                font-size: 0.9rem;
            }

            .btn-primary {
                font-size: 0.75rem;
                padding: 4px 12px;
            }

            .modal-body {
                padding: 18px 15px;
            }

            .form-control, .form-select {
                font-size: 0.9rem;
                padding: 8px 14px;
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
                width: 38px;
                height: 38px;
                font-size: 1.1rem;
            }
        }

        /* ========== BODY SCROLL LOCK ========== */
        body.sidebar-open {
            overflow: hidden;
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-state .empty-icon {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-state h4 {
            color: #1a2a4a;
            font-weight: 600;
        }

        .empty-state p {
            color: #6c757d;
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
                <a href="index.php" class="nav-link">
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

           <li class="nav-item"><a href="bulk-students.php" class="nav-link"><i class="nav-icon fas fa-file-upload"></i><p>Bulk Upload</p></a></li>
           <li class="nav-item">
    <a href="manage-staff.php" class="nav-link">
        <i class="nav-icon fas fa-user-tie"></i> Manage Staff
    </a>
</li>
           <li class="nav-item"><a href="rooms.php" class="nav-link"><i class="nav-icon fas fa-bed"></i><p>Rooms</p></a></li>
        <li class="nav-item"><a href="post-room.php" class="nav-link"><i class="nav-icon fas fa-plus-square"></i><p>Post Room</p></a></li>
        

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

            <li class="nav-item has-treeview menu-open">
                <a href="#" class="nav-link active">
                    <i class="nav-icon fas fa-clipboard-list"></i>
                    <p>
                        Requests
                        <i class="right fas fa-angle-left"></i>
                    </p>
                </a>
                <ul class="nav nav-treeview show">
                    <li class="nav-item">
                        <a href="complaints.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Complaints</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="maintenance.php" class="nav-link active">
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
            <h1><i class="fas fa-tools me-2"></i>Maintenance Requests</h1>
        </div>
    </section>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <!-- Success/Error Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-6">
                    <div class="stat-card bg-primary">
                        <div class="stat-body">
                            <div class="stat-info">
                                <h3><?= $total_requests ?></h3>
                                <p>Total Requests</p>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-tools"></i>
                            </div>
                        </div>
                        <div class="stat-bg-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="stat-card bg-warning">
                        <div class="stat-body">
                            <div class="stat-info">
                                <h3><?= $pending_count ?></h3>
                                <p>Pending</p>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="stat-bg-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="stat-card bg-info">
                        <div class="stat-body">
                            <div class="stat-info">
                                <h3><?= $in_progress_count ?></h3>
                                <p>In Progress</p>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-spinner"></i>
                            </div>
                        </div>
                        <div class="stat-bg-icon">
                            <i class="fas fa-spinner"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="stat-card bg-success">
                        <div class="stat-body">
                            <div class="stat-info">
                                <h3><?= $resolved_count ?></h3>
                                <p>Resolved</p>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="stat-bg-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Maintenance Requests Table -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <h3 class="card-title">
                            <i class="fas fa-list me-2"></i>
                            All Maintenance Requests
                            <span class="badge bg-secondary ms-2"><?= $total_requests ?></span>
                        </h3>
                        <div class="filter-wrapper">
                            <form method="GET" class="d-flex gap-2 flex-wrap">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search by name, matric or issue..." 
                                       value="<?= htmlspecialchars($search) ?>">
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="in-progress" <?= $status_filter == 'in-progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="resolved" <?= $status_filter == 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                </select>
                                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #fd7e14, #ffc107); border: none; border-radius: 10px; padding: 8px 16px; color: white;">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if ($search || $status_filter): ?>
                                    <a href="maintenance.php" class="btn btn-outline-secondary">
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
                                <th><i class="far fa-calendar-alt me-1"></i>Date</th>
                                <th><i class="fas fa-user me-1"></i>Student</th>
                                <th><i class="fas fa-id-card me-1"></i>Matric No</th>
                                <th><i class="fas fa-door-open me-1"></i>Room</th>
                                <th><i class="fas fa-tag me-1"></i>Issue Type</th>
                                <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                <th width="180"><i class="fas fa-cog me-1"></i>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <div class="empty-icon">
                                                <i class="fas fa-tools"></i>
                                            </div>
                                            <h4>No Maintenance Requests Found</h4>
                                            <p><?= ($search || $status_filter) ? 'No requests match your search criteria.' : 'No maintenance requests have been submitted yet.' ?></p>
                                            <?php if ($search || $status_filter): ?>
                                                <a href="maintenance.php" class="btn btn-primary">
                                                    <i class="fas fa-arrow-left me-2"></i>Clear Filters
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $req): ?>
                                    <tr>
                                        <td><?= date('d M Y H:i', strtotime($req['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($req['full_name']) ?></td>
                                        <td><strong><?= htmlspecialchars($req['matric_no']) ?></strong></td>
                                        <td><?= htmlspecialchars($req['room_number'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-tag me-1"></i>
                                                <?= htmlspecialchars($req['issue_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $req['status'] === 'pending' ? 'warning' : ($req['status'] === 'in-progress' ? 'info' : 'success') ?>">
                                                <?php if ($req['status'] === 'pending'): ?>
                                                    <i class="fas fa-clock me-1"></i>
                                                <?php elseif ($req['status'] === 'in-progress'): ?>
                                                    <i class="fas fa-spinner fa-spin me-1"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-check-circle me-1"></i>
                                                <?php endif; ?>
                                                <?= ucfirst($req['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <button class="btn btn-primary view-btn" 
                                                        data-id="<?= $req['id'] ?>"
                                                        data-student="<?= htmlspecialchars($req['full_name']) ?>"
                                                        data-matric="<?= htmlspecialchars($req['matric_no']) ?>"
                                                        data-room="<?= htmlspecialchars($req['room_number'] ?? 'N/A') ?>"
                                                        data-issue="<?= htmlspecialchars($req['issue_type']) ?>"
                                                        data-description="<?= htmlspecialchars($req['description']) ?>"
                                                        data-status="<?= $req['status'] ?>"
                                                        data-notes="<?= htmlspecialchars($req['admin_notes'] ?? '') ?>"
                                                        data-date="<?= date('d M Y H:i', strtotime($req['created_at'])) ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-sm btn-primary update-btn" 
                                                        data-id="<?= $req['id'] ?>"
                                                        data-status="<?= $req['status'] ?>"
                                                        data-notes="<?= htmlspecialchars($req['admin_notes'] ?? '') ?>">
                                                    <i class="fas fa-edit"></i> Update
                                                </button>
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

<!-- ====== FOOTER ====== -->
<footer class="main-footer">
    <strong>Dormitory Management System</strong> &copy; <?= date('Y') ?> 
    <span class="text-muted">| The Polytechnic Ibadan</span>
</footer>

<!-- ====== VIEW REQUEST MODAL ====== -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-file-alt"></i> Maintenance Request Details
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="fw-bold text-muted small">Student</label>
                        <p id="viewStudent" class="mb-0"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold text-muted small">Matric Number</label>
                        <p id="viewMatric" class="mb-0"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="fw-bold text-muted small">Date Submitted</label>
                        <p id="viewDate" class="mb-0"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold text-muted small">Status</label>
                        <p id="viewStatus" class="mb-0"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="fw-bold text-muted small">Room</label>
                        <p id="viewRoom" class="mb-0"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold text-muted small">Issue Type</label>
                        <p id="viewIssue" class="mb-0"></p>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="fw-bold text-muted small">Description</label>
                    <p id="viewDescription" class="mb-0"></p>
                </div>
                <div class="mb-3">
                    <label class="fw-bold text-muted small">Admin Notes</label>
                    <p id="viewNotes" class="mb-0 text-muted font-italic">No admin notes yet.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ====== UPDATE REQUEST MODAL ====== -->
<div class="modal fade" id="updateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-edit"></i> Update Maintenance Request
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="updateForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="updateId">
                    <div class="form-group mb-3">
                        <label class="form-label">
                            <i class="fas fa-info-circle"></i>Status
                        </label>
                        <select name="status" id="updateStatus" class="form-select">
                            <option value="pending">Pending</option>
                            <option value="in-progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">
                            <i class="fas fa-sticky-note"></i>Admin Notes
                        </label>
                        <textarea name="admin_notes" id="updateNotes" class="form-control" rows="5" 
                                  placeholder="Add your resolution notes here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" name="update" class="btn btn-primary" id="updateSubmitBtn" style="background: linear-gradient(135deg, #fd7e14, #ffc107); border: none;">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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

    // ====== VIEW REQUEST MODAL ======
    const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
    
    document.querySelectorAll('.view-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const student = this.dataset.student;
            const matric = this.dataset.matric;
            const room = this.dataset.room;
            const issue = this.dataset.issue;
            const description = this.dataset.description;
            const status = this.dataset.status;
            const notes = this.dataset.notes;
            const date = this.dataset.date;

            document.getElementById('viewStudent').textContent = student;
            document.getElementById('viewMatric').textContent = matric;
            document.getElementById('viewRoom').textContent = room;
            document.getElementById('viewIssue').textContent = issue;
            document.getElementById('viewDescription').textContent = description;
            document.getElementById('viewDate').textContent = date;

            const statusEl = document.getElementById('viewStatus');
            const statusMap = {
                'pending': '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Pending</span>',
                'in-progress': '<span class="badge bg-info"><i class="fas fa-spinner fa-spin me-1"></i>In Progress</span>',
                'resolved': '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Resolved</span>'
            };
            statusEl.innerHTML = statusMap[status] || status;

            const notesEl = document.getElementById('viewNotes');
            if (notes && notes.trim() !== '') {
                notesEl.textContent = notes;
                notesEl.className = 'mb-0';
            } else {
                notesEl.textContent = 'No admin notes yet.';
                notesEl.className = 'mb-0 text-muted font-italic';
            }

            viewModal.show();
        });
    });

    // ====== UPDATE REQUEST MODAL ======
    const updateModal = new bootstrap.Modal(document.getElementById('updateModal'));
    
    document.querySelectorAll('.update-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const status = this.dataset.status;
            const notes = this.dataset.notes;

            document.getElementById('updateId').value = id;
            document.getElementById('updateStatus').value = status;
            document.getElementById('updateNotes').value = notes || '';

            updateModal.show();
        });
    });

    // ====== FORM SUBMIT HANDLER ======
    const updateForm = document.getElementById('updateForm');
    const updateSubmitBtn = document.getElementById('updateSubmitBtn');

    if (updateForm) {
        updateForm.addEventListener('submit', function(e) {
            updateSubmitBtn.disabled = true;
            updateSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
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
        confirmButtonColor: '#fd7e14'
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

    console.log('✅ Maintenance Requests page initialized');
});
</script>

</body>
</html>