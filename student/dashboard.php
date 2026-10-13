<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}
include '../config/db.php';
include_once '../config/functions.php';

$student_id = $_SESSION['student_id'];

// Flash messages
$flash_success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

// Student
$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();

// Current allocation
$allocation = $conn->query("
    SELECT a.*, r.room_number, r.block, r.price,
           h.name AS hostel_name, h.location AS hostel_location
    FROM allocations a 
    JOIN rooms r ON a.room_id = r.id 
    LEFT JOIN hostels h ON r.hostel_id = h.id
    WHERE a.student_id = $student_id AND a.status = 'active'
    ORDER BY a.allocation_date DESC 
    LIMIT 1
")->fetch_assoc();

// Payments
$completed_payment = $conn->query("
    SELECT * FROM payments WHERE student_id = $student_id AND status='completed'
    ORDER BY created_at DESC LIMIT 1
")->fetch_assoc();

$pending_payment = $conn->query("
    SELECT * FROM payments WHERE student_id = $student_id AND status='pending'
    ORDER BY created_at DESC LIMIT 1
")->fetch_assoc();

// Pending counts
$pending_complaints  = $conn->query("SELECT COUNT(*) FROM complaints WHERE student_id = $student_id AND status='pending'")->fetch_row()[0];
$pending_maintenance = $conn->query("SELECT COUNT(*) FROM maintenance_requests WHERE student_id = $student_id AND status='pending'")->fetch_row()[0];
$pending_refunds     = $conn->query("SELECT COUNT(*) FROM refund_requests WHERE student_id = $student_id AND status='pending'")->fetch_row()[0];

// Available rooms count (matching gender)
$gender = $conn->real_escape_string($student['gender'] ?? '');
$available_rooms = $conn->query("
    SELECT COUNT(*) FROM rooms 
    WHERE status='available' AND (gender='$gender' OR gender='Mixed')
")->fetch_row()[0];

// Level display
$level_map = ['ND1' => 'ND I', 'ND2' => 'ND II', 'HND1' => 'HND I', 'HND2' => 'HND II'];
$level_display = $level_map[$student['level']] ?? ($student['level'] ?? 'N/A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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

        .welcome-hero { background: linear-gradient(135deg, #1a3a6b 0%, #0d1b3e 100%); color: white; padding: 35px 30px; border-radius: 20px; margin-bottom: 25px; position: relative; overflow: hidden; box-shadow: 0 10px 40px rgba(26, 58, 107, 0.25); animation: fadeInUp 0.5s ease; }
        .welcome-hero::before { content: ''; position: absolute; top: -50%; right: -30%; width: 60%; height: 200%; background: radial-gradient(circle, rgba(255,215,0,0.15) 0%, transparent 70%); pointer-events: none; }
        .welcome-hero::after { content: '\f19c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: 20px; bottom: -30px; font-size: 10rem; opacity: 0.06; transform: rotate(-15deg); }
        .welcome-hero .welcome-content { position: relative; z-index: 1; }
        .welcome-hero h2 { font-weight: 800; font-size: 1.8rem; margin-bottom: 5px; }
        .welcome-hero h2 span { color: #ffd700; }
        .welcome-hero .student-meta { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 15px; }
        .welcome-hero .meta-badge { background: rgba(255,255,255,0.12); padding: 8px 16px; border-radius: 30px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; border: 1px solid rgba(255,255,255,0.15); }
        .welcome-hero .meta-badge i { color: #ffd700; }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        /* ===== BROWSE ROOMS CTA ===== */
        .browse-cta {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
            box-shadow: 0 15px 40px rgba(40,167,69,0.3);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
        }
        .browse-cta::before {
            content: '\f002';
            font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; right: -20px; bottom: -40px;
            font-size: 10rem; opacity: 0.08; transform: rotate(-15deg);
        }
        .browse-cta .cta-icon {
            width: 85px; height: 85px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; color: white; flex-shrink: 0;
            border: 2px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(4px);
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .browse-cta .cta-info { flex: 1; min-width: 220px; position: relative; z-index: 1; }
        .browse-cta .cta-info h3 { font-weight: 800; font-size: 1.5rem; margin-bottom: 6px; }
        .browse-cta .cta-info p { margin: 0; font-size: 0.95rem; opacity: 0.95; }
        .browse-cta .cta-info .counter-badge {
            display: inline-block;
            background: rgba(255,255,255,0.25);
            padding: 4px 14px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
            margin-top: 10px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .browse-cta .cta-btn {
            background: white;
            color: #28a745;
            border: none;
            border-radius: 12px;
            padding: 16px 32px;
            font-weight: 800;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .browse-cta .cta-btn:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(0,0,0,0.25); color: #1e7e34; }

        /* ===== CLEARANCE CARD ===== */
        .clearance-card {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            display: flex; align-items: center; gap: 25px; flex-wrap: wrap;
            box-shadow: 0 10px 35px rgba(23,162,184,0.3);
            position: relative; overflow: hidden;
            animation: fadeInUp 0.7s ease;
        }
        .clearance-card::before {
            content: '\f15c';
            font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; right: -20px; bottom: -40px;
            font-size: 9rem; opacity: 0.08; transform: rotate(-15deg);
        }
        .clearance-card .clearance-icon {
            width: 75px; height: 75px;
            background: rgba(255,255,255,0.2);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.3rem; flex-shrink: 0;
            border: 2px solid rgba(255,255,255,0.3);
        }
        .clearance-card .clearance-info { flex: 1; min-width: 220px; position: relative; z-index: 1; }
        .clearance-card .clearance-info h4 { font-weight: 700; font-size: 1.25rem; margin-bottom: 5px; }
        .clearance-card .clearance-info p { margin: 0; font-size: 0.9rem; opacity: 0.95; }
        .clearance-card .clearance-btn {
            background: white; color: #17a2b8;
            border: none; border-radius: 10px;
            padding: 14px 28px; font-weight: 700; font-size: 0.95rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: 10px;
            transition: all 0.3s ease; position: relative; z-index: 1; flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .clearance-card .clearance-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.25); color: #0f6674; }

        /* STAT CARDS */
        .stat-card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06); transition: all 0.3s ease; overflow: hidden; animation: fadeInUp 0.6s ease; margin-bottom: 20px; height: calc(100% - 20px); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12); }
        .stat-card .stat-body { padding: 25px; position: relative; z-index: 1; }
        .stat-card .stat-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.7rem; color: white; margin-bottom: 15px; }
        .stat-card .stat-value { font-size: 1.9rem; font-weight: 800; margin: 0; color: #1a2a4a; line-height: 1.2; }
        .stat-card .stat-label { margin: 5px 0 0; color: #6c757d; font-size: 0.9rem; font-weight: 500; }
        .stat-card .stat-link { display: inline-block; margin-top: 15px; font-weight: 600; font-size: 0.85rem; text-decoration: none; transition: all 0.3s ease; }
        .stat-card .stat-link:hover { transform: translateX(5px); }
        .stat-card.bg-info .stat-icon { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
        .stat-card.bg-info .stat-link { color: #0d6efd; }
        .stat-card.bg-success .stat-icon { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-card.bg-success .stat-link { color: #28a745; }
        .stat-card.bg-warning .stat-icon { background: linear-gradient(135deg, #ffc107, #fd7e14); }
        .stat-card.bg-warning .stat-link { color: #fd7e14; }
        .stat-card.bg-danger .stat-icon { background: linear-gradient(135deg, #dc3545, #e74c3c); }
        .stat-card.bg-danger .stat-link { color: #dc3545; }

        .action-card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06); transition: all 0.3s ease; height: 100%; text-align: center; padding: 25px 18px; background: white; animation: fadeInUp 0.6s ease; text-decoration: none; color: inherit; display: block; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12); color: inherit; }
        .action-card .action-icon { width: 65px; height: 65px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 1.6rem; transition: all 0.3s ease; }
        .action-card:hover .action-icon { transform: scale(1.1); }
        .action-card h6 { font-weight: 700; margin-bottom: 5px; font-size: 0.95rem; color: #1a2a4a; }
        .action-card p { font-size: 0.8rem; color: #6c757d; margin: 0; }
        .action-icon.icon-primary { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
        .action-icon.icon-warning { background: rgba(255, 193, 7, 0.15); color: #fd7e14; }
        .action-icon.icon-danger { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .action-icon.icon-info { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }

        .section-header { display: flex; align-items: center; justify-content: space-between; margin: 35px 0 20px; padding-bottom: 12px; border-bottom: 2px solid #e9ecef; }
        .section-header h4 { font-weight: 700; font-size: 1.2rem; color: #1a2a4a; margin: 0; display: flex; align-items: center; gap: 10px; }
        .section-header h4 i { color: #1a3a6b; }
        .section-header .view-all { font-size: 0.85rem; font-weight: 600; color: #1a3a6b; text-decoration: none; }

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
            .welcome-hero { padding: 25px 20px; }
            .welcome-hero h2 { font-size: 1.35rem; }
            .browse-cta { padding: 20px; flex-direction: column; text-align: center; gap: 15px; }
            .browse-cta .cta-icon { width: 65px; height: 65px; font-size: 2rem; }
            .browse-cta .cta-info h3 { font-size: 1.2rem; }
            .browse-cta .cta-btn { width: 100%; justify-content: center; padding: 14px 20px; }
            .clearance-card { padding: 20px; flex-direction: column; text-align: center; gap: 15px; }
            .clearance-card .clearance-btn { width: 100%; justify-content: center; }
            .stat-card .stat-value { font-size: 1.5rem; }
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
        <div class="brand-text">Dormitory MS<small>The Polytechnic Ibadan</small></div>
    </div>
    <div class="user-panel">
        <?php 
        $photo = $student['photo'] ?? '';
        if ($photo && file_exists('../' . $photo)): ?>
            <img src="../<?= htmlspecialchars($photo) ?>">
        <?php else: ?>
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['full_name'] ?? 'Student') ?>&background=ffd700&color=1a3a6b&size=42">
        <?php endif; ?>
        <div>
            <a href="profile.php"><?= htmlspecialchars($student['full_name'] ?? 'Student') ?></a>
            <small><?= htmlspecialchars($student['matric_no'] ?? '') ?></small>
        </div>
    </div>
    <ul class="nav nav-pills nav-sidebar flex-column">
        <li class="nav-item"><a href="dashboard.php" class="nav-link active"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
        <li class="nav-item"><a href="browse-rooms.php" class="nav-link"><i class="nav-icon fas fa-search"></i><p>Browse Rooms</p></a></li>
        <li class="nav-item"><a href="my-room.php" class="nav-link"><i class="nav-icon fas fa-bed"></i><p>My Room</p></a></li>
        <li class="nav-item"><a href="profile.php" class="nav-link"><i class="nav-icon fas fa-user"></i><p>My Profile</p></a></li>
        <li class="nav-item"><a href="payment.php" class="nav-link"><i class="nav-icon fas fa-credit-card"></i><p>Payment History</p></a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
    </ul>
</aside>

<div class="content-wrapper">

    <?php if ($flash_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius:12px; border-left:5px solid #28a745; margin-bottom: 20px;">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($flash_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- WELCOME HERO -->
    <div class="welcome-hero">
        <div class="welcome-content">
            <h2>Hello, <span><?= htmlspecialchars($student['full_name'] ?? 'Student') ?></span> 👋</h2>
            <p class="mb-0">Welcome to your student dashboard</p>
            <div class="student-meta">
                <div class="meta-badge"><i class="fas fa-id-card"></i> <?= htmlspecialchars($student['matric_no'] ?? 'N/A') ?></div>
                <div class="meta-badge"><i class="fas fa-graduation-cap"></i> <?= $level_display ?></div>
                <div class="meta-badge"><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($student['gender'] ?? 'N/A') ?></div>
            </div>
        </div>
    </div>

    <!-- ===== BROWSE ROOMS CTA (if not allocated) ===== -->
    <?php if (!$allocation): ?>
        <div class="browse-cta">
            <div class="cta-icon"><i class="fas fa-search"></i></div>
            <div class="cta-info">
                <h3>Find Your Perfect Room</h3>
                <p>Browse available rooms, compare prices, and secure your accommodation in just a few clicks.</p>
                <div class="counter-badge">
                    <i class="fas fa-door-open me-1"></i>
                    <?= number_format($available_rooms) ?> rooms currently available
                </div>
            </div>
            <a href="browse-rooms.php" class="cta-btn">
                <i class="fas fa-arrow-right"></i> Browse Rooms Now
            </a>
        </div>
    <?php endif; ?>

    <!-- ===== CLEARANCE SLIP CARD ===== -->
    <?php if ($allocation && $completed_payment): ?>
        <div class="clearance-card">
            <div class="clearance-icon"><i class="fas fa-file-alt"></i></div>
            <div class="clearance-info">
                <h4><i class="fas fa-check-circle me-2"></i>Accommodation Clearance Ready</h4>
                <p>Your official clearance slip for Room <strong><?= htmlspecialchars($allocation['room_number']) ?></strong> at 
                <strong><?= htmlspecialchars($allocation['hostel_name'] ?? 'N/A') ?></strong> is ready. Download and present at the Hall of Residence.</p>
            </div>
            <a href="clearance_slip.php" class="clearance-btn" target="_blank">
                <i class="fas fa-download"></i> Download Slip
            </a>
        </div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="row g-3">
        <div class="col-lg-3 col-md-6 col-12">
            <div class="stat-card bg-success">
                <div class="stat-body">
                    <div class="stat-icon"><i class="fas fa-bed"></i></div>
                    <h3 class="stat-value"><?= $allocation ? htmlspecialchars($allocation['room_number']) : '—' ?></h3>
                    <p class="stat-label"><?= $allocation ? 'My Current Room' : 'Not Allocated Yet' ?></p>
                    <?php if ($allocation): ?>
                        <a href="my-room.php" class="stat-link">View Details <i class="fas fa-arrow-right ms-1"></i></a>
                    <?php else: ?>
                        <a href="browse-rooms.php" class="stat-link">Select a Room <i class="fas fa-arrow-right ms-1"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-12">
            <div class="stat-card bg-warning">
                <div class="stat-body">
                    <div class="stat-icon"><i class="fas fa-comments"></i></div>
                    <h3 class="stat-value"><?= (int)$pending_complaints ?></h3>
                    <p class="stat-label">Pending Complaints</p>
                    <a href="complaints.php" class="stat-link">Manage <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-12">
            <div class="stat-card bg-danger">
                <div class="stat-body">
                    <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                    <h3 class="stat-value"><?= (int)($pending_maintenance + $pending_refunds) ?></h3>
                    <p class="stat-label">Pending Requests</p>
                    <a href="maintenance.php" class="stat-link">View All <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-12">
            <div class="stat-card bg-info">
                <div class="stat-body">
                    <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                    <h3 class="stat-value" style="font-size:1.4rem;">
                        <?php if ($completed_payment): ?>
                            <span style="color:#28a745;">Paid</span>
                        <?php elseif ($pending_payment): ?>
                            <span style="color:#fd7e14;">Pending</span>
                        <?php else: ?>
                            <span style="color:#dc3545;">Unpaid</span>
                        <?php endif; ?>
                    </h3>
                    <p class="stat-label">Payment Status</p>
                    <a href="payment.php" class="stat-link">View History <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="section-header">
        <h4><i class="fas fa-bolt"></i> Quick Actions</h4>
    </div>
    <div class="row g-3">
        <div class="col-lg-3 col-md-6 col-6">
            <a href="browse-rooms.php" class="action-card">
                <div class="action-icon icon-info"><i class="fas fa-search"></i></div>
                <h6>Browse Rooms</h6>
                <p>View available rooms</p>
            </a>
        </div>
        <div class="col-lg-3 col-md-6 col-6">
            <a href="complaints.php" class="action-card">
                <div class="action-icon icon-primary"><i class="fas fa-comments"></i></div>
                <h6>Make Complaint</h6>
                <p>Report an issue</p>
            </a>
        </div>
        <div class="col-lg-3 col-md-6 col-6">
            <a href="maintenance.php" class="action-card">
                <div class="action-icon icon-warning"><i class="fas fa-wrench"></i></div>
                <h6>Maintenance</h6>
                <p>Request a fix</p>
            </a>
        </div>
        <div class="col-lg-3 col-md-6 col-6">
            <a href="refund.php" class="action-card">
                <div class="action-icon icon-danger"><i class="fas fa-money-bill-wave"></i></div>
                <h6>Request Refund</h6>
                <p>Ask for a refund</p>
            </a>
        </div>
    </div>

    <!-- MY ACCOMMODATION -->
    <?php if ($allocation): ?>
        <div class="section-header">
            <h4><i class="fas fa-home"></i> My Accommodation</h4>
            <a href="my-room.php" class="view-all">View Full Details <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="stat-card bg-success">
            <div class="stat-body">
                <div class="row align-items-center g-3">
                    <div class="col-md-2 text-center">
                        <div class="stat-icon" style="margin: 0 auto 15px; background: linear-gradient(135deg,#1a3a6b,#0d1b3e);">
                            <i class="fas fa-door-open"></i>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <h3 class="stat-value" style="font-size:1.5rem;">Room <?= htmlspecialchars($allocation['room_number']) ?></h3>
                        <p class="stat-label mb-2">
                            <i class="fas fa-building me-1"></i><?= htmlspecialchars($allocation['hostel_name'] ?? 'N/A') ?>
                            <?php if (!empty($allocation['block'])): ?> • Block <?= htmlspecialchars($allocation['block']) ?><?php endif; ?>
                        </p>
                        <small class="text-muted">
                            <i class="fas fa-calendar-alt me-1"></i>Allocated on <?= date('d M Y', strtotime($allocation['allocation_date'])) ?>
                            • ₦<?= number_format((float)$allocation['price'], 2) ?>
                        </small>
                    </div>
                    <div class="col-md-3 text-md-end text-center">
                        <?php if ($completed_payment): ?>
                            <span class="badge bg-success mb-2 d-inline-block" style="padding: 8px 16px;"> <i class="fas fa-check-circle me-1"></i>Active</span>
                            <br>
                            <a href="clearance_slip.php" target="_blank" class="btn btn-sm btn-success" style="border-radius:8px;">
                                <i class="fas fa-file-pdf me-1"></i>Clearance
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
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
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('active'); document.body.style.overflow = ''; }
    if (toggleBtn) toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open'); overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    });
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);
});
</script>
</body>
</html>