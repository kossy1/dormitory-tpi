<?php
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$staff_id = $_SESSION['staff_id'];
$staff_role = $_SESSION['staff_role'] ?? 'staff';

// Fetch staff fresh data
$staff = $conn->query("SELECT * FROM staff WHERE id = $staff_id")->fetch_assoc();
if (!$staff) { session_destroy(); header("Location: login.php"); exit; }

// Photo fallback
$staff_photo = $staff['photo'] ?? '';
$avatar_url = ($staff_photo && file_exists('../' . $staff_photo))
    ? '../' . htmlspecialchars($staff_photo)
    : 'https://ui-avatars.com/api/?name=' . urlencode($staff['full_name']) . '&background=1a3a6b&color=fff&size=42';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Staff Portal' ?> - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; }

        /* Sidebar */
        .main-sidebar {
            position: fixed; top: 0; left: 0;
            width: 270px; height: 100vh;
            background: linear-gradient(180deg, #1a3a6b 0%, #0d1b3e 100%);
            z-index: 1040;
            overflow-y: auto;
            transition: transform 0.3s ease;
            transform: translateX(-100%);
        }
        .main-sidebar.open { transform: translateX(0); }
        .sidebar-brand {
            padding: 22px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex; align-items: center; gap: 12px;
        }
        .sidebar-brand .brand-icon {
            width: 45px; height: 45px;
            background: linear-gradient(135deg, #ffd700, #ffb700);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: #1a3a6b; font-size: 1.3rem;
            box-shadow: 0 4px 15px rgba(255,215,0,0.35);
        }
        .sidebar-brand .brand-text { color: white; font-weight: 700; font-size: 1rem; line-height: 1.2; }
        .sidebar-brand .brand-text small { display: block; font-size: 0.7rem; opacity: 0.6; font-weight: 400; margin-top: 2px; }

        .user-panel {
            padding: 18px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex; align-items: center; gap: 12px;
        }
        .user-panel img {
            width: 42px; height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,215,0,0.5);
        }
        .user-panel .user-info { flex: 1; min-width: 0; }
        .user-panel .user-name {
            color: white; font-weight: 600; font-size: 0.9rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .user-panel .user-role {
            color: #ffd700; font-size: 0.72rem;
            text-transform: uppercase; letter-spacing: 0.5px;
            font-weight: 600;
        }

        .nav-sidebar { padding: 10px 0; list-style: none; }
        .nav-sidebar .nav-item { margin: 3px 10px; }
        .nav-sidebar .nav-link {
            color: rgba(255,255,255,0.75);
            padding: 11px 16px;
            border-radius: 10px;
            display: flex; align-items: center; gap: 12px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.25s ease;
        }
        .nav-sidebar .nav-link:hover { color: white; background: rgba(255,255,255,0.08); }
        .nav-sidebar .nav-link.active {
            color: white;
            background: rgba(255,215,0,0.15);
            border-left: 3px solid #ffd700;
        }
        .nav-sidebar .nav-icon { width: 22px; text-align: center; opacity: 0.8; font-size: 0.95rem; }
        .nav-sidebar .nav-link.active .nav-icon { color: #ffd700; opacity: 1; }
        .nav-sidebar .nav-link.text-danger { color: #ff7b7b !important; }
        .nav-section-title {
            color: rgba(255,255,255,0.4);
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            padding: 15px 26px 6px;
        }

        .sidebar-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1039;
        }
        .sidebar-overlay.active { display: block; }
        .sidebar-toggle-btn {
            position: fixed; top: 12px; left: 12px;
            z-index: 1050;
            background: #1a3a6b; color: white;
            border: none; border-radius: 10px;
            padding: 12px 16px;
            display: none;
            box-shadow: 0 4px 15px rgba(26,58,107,0.4);
        }
        .sidebar-close-btn {
            display: none;
            position: absolute; top: 14px; right: 14px;
            background: rgba(255,255,255,0.1);
            color: white; border: none;
            border-radius: 50%;
            width: 34px; height: 34px;
        }

        /* Content */
        .content-wrapper {
            min-height: 100vh;
            padding: 25px;
            transition: margin-left 0.3s ease;
        }
        .content-header {
            padding-bottom: 18px;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .content-header h1 {
            font-weight: 800;
            font-size: 1.7rem;
            color: #1a2a4a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .content-header h1 i { color: #1a3a6b; }

        /* Cards */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            margin-bottom: 22px;
            animation: fadeIn 0.4s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .card-header {
            background: white;
            border-bottom: 2px solid #f0f2f5;
            padding: 18px 24px;
            border-radius: 16px 16px 0 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-header .card-title {
            font-weight: 700;
            font-size: 1.05rem;
            color: #1a2a4a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header .card-title i { color: #1a3a6b; }
        .card-body { padding: 24px; }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            border: none; border-radius: 10px;
            padding: 10px 22px;
            font-weight: 600; color: white;
            transition: all 0.25s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26,58,107,0.35);
            color: white;
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none; border-radius: 10px;
            padding: 10px 22px;
            font-weight: 600; color: white;
            transition: all 0.25s ease;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40,167,69,0.35);
            color: white;
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none; border-radius: 10px;
            padding: 10px 22px;
            font-weight: 600; color: white;
        }
        .btn-danger:hover { transform: translateY(-2px); color: white; }
        .btn-outline-secondary {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 9px 18px;
            font-weight: 600;
            color: #495057;
        }
        .btn-outline-secondary:hover { background: #f8f9fa; border-color: #ced4da; color: #1a2a4a; }

        /* Alerts */
        .alert { border-radius: 12px; border: none; padding: 15px 22px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left: 5px solid #dc3545; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 5px solid #ffc107; }
        .alert-info { background: #e7f3ff; color: #084298; border-left: 5px solid #0d6efd; }

        /* Stat cards */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            gap: 18px;
            transition: all 0.3s ease;
            height: 100%;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 35px rgba(0,0,0,0.1); }
        .stat-card .stat-icon {
            width: 58px; height: 58px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: white;
            flex-shrink: 0;
        }
        .stat-card .stat-info h3 { font-weight: 800; font-size: 1.6rem; color: #1a2a4a; margin: 0; }
        .stat-card .stat-info p { margin: 0; color: #6c757d; font-size: 0.85rem; font-weight: 500; }

        .bg-gradient-blue { background: linear-gradient(135deg, #1a3a6b, #2c5aa0); }
        .bg-gradient-green { background: linear-gradient(135deg, #28a745, #20c997); }
        .bg-gradient-orange { background: linear-gradient(135deg, #fd7e14, #ffb700); }
        .bg-gradient-red { background: linear-gradient(135deg, #dc3545, #c82333); }
        .bg-gradient-purple { background: linear-gradient(135deg, #6f42c1, #8b5cf6); }
        .bg-gradient-teal { background: linear-gradient(135deg, #17a2b8, #20c997); }

        /* Tables */
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

        /* Badges */
        .badge { padding: 6px 12px; border-radius: 8px; font-weight: 600; font-size: 0.75rem; }

        /* Form controls */
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
            box-shadow: 0 0 0 4px rgba(26,58,107,0.1);
        }

        /* Footer */
        .main-footer {
            background: white;
            padding: 16px 25px;
            border-top: 1px solid #e9ecef;
            text-align: center;
            font-size: 0.85rem;
            color: #6c757d;
            margin-left: 0;
        }

        @media (min-width: 992px) {
            .main-sidebar { transform: translateX(0) !important; }
            .sidebar-toggle-btn, .sidebar-overlay, .sidebar-close-btn { display: none !important; }
            .content-wrapper { margin-left: 270px; }
            .main-footer { margin-left: 270px; }
        }
        @media (max-width: 991.98px) {
            .sidebar-toggle-btn { display: block; }
            .sidebar-close-btn { display: block; }
            .content-wrapper { padding-top: 75px; }
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
        <div class="brand-icon"><i class="fas fa-building"></i></div>
        <div class="brand-text">
            Dormitory MS
            <small>Staff Portal</small>
        </div>
    </div>

    <div class="user-panel">
        <img src="<?= $avatar_url ?>" alt="Staff">
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($staff['full_name']) ?></div>
            <div class="user-role"><?= htmlspecialchars($staff['role']) ?></div>
        </div>
    </div>

    <div class="nav-section-title">Main</div>
    <ul class="nav-sidebar">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
    </ul>

    <div class="nav-section-title">Management</div>
    <ul class="nav-sidebar">
        <li class="nav-item">
            <a href="manage-rooms.php" class="nav-link <?= in_array($current_page, ['manage-rooms','add-room','edit-room']) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-bed"></i> Rooms
            </a>
        </li>
        <li class="nav-item">
            <a href="manage-hostels.php" class="nav-link <?= $current_page === 'manage-hostels' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-hotel"></i> Hostels
            </a>
        </li>
        <?php if (($_SESSION['staff_role'] ?? '') === 'admin'): ?>
<li class="nav-item">
    <a href="manage-staff.php" class="nav-link <?= $current_page === 'manage-staff' ? 'active' : '' ?>">
        <i class="nav-icon fas fa-user-tie"></i> Manage Staff
    </a>
</li>
<?php endif; ?>
        <li class="nav-item">
            <a href="manage-students.php" class="nav-link <?= in_array($current_page, ['manage-students','view-student']) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-users"></i> Students
            </a>
        </li>
        <li class="nav-item">
            <a href="manage-allocations.php" class="nav-link <?= $current_page === 'manage-allocations' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-clipboard-check"></i> Allocations
            </a>
        </li>
    </ul>

    <div class="nav-section-title">Finance</div>
    <ul class="nav-sidebar">
        <li class="nav-item">
            <a href="manage-payments.php" class="nav-link <?= $current_page === 'manage-payments' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-credit-card"></i> Payments
            </a>
        </li>
        <li class="nav-item">
            <a href="reports.php" class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-chart-line"></i> Reports
            </a>
        </li>
    </ul>

    <div class="nav-section-title">Account</div>
    <ul class="nav-sidebar">
        <li class="nav-item">
            <a href="profile.php" class="nav-link <?= $current_page === 'profile' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-user"></i> My Profile
            </a>
        </li>
        <li class="nav-item">
            <a href="logout.php" class="nav-link text-danger">
                <i class="nav-icon fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</aside>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas <?= $page_icon ?? 'fa-tachometer-alt' ?>"></i> <?= $page_title ?? 'Dashboard' ?></h1>
        <?php if (!empty($page_actions)) echo $page_actions; ?>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['flash_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['flash_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_info'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i><?= htmlspecialchars($_SESSION['flash_info']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_info']); ?>
    <?php endif; ?>