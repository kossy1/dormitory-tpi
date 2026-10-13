<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';
include '../config/paystack.php';

$student_id = $_SESSION['student_id'];
$flash_success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

// Get student details
$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();

// Current allocation — include bed spacing
$allocation = $conn->query("
    SELECT r.id as room_id, r.room_number, r.block, r.price, r.capacity,
           r.bed_spacing, r.room_type, r.gender,
           a.allocation_date, a.status as allocation_status, a.semester,
           h.name AS hostel_name, h.location AS hostel_location,
           (SELECT COUNT(*) FROM allocations a2 WHERE a2.room_id = r.id AND a2.status = 'active') as current_occupants
    FROM allocations a 
    JOIN rooms r ON a.room_id = r.id 
    LEFT JOIN hostels h ON r.hostel_id = h.id
    WHERE a.student_id = $student_id AND a.status = 'active'
    LIMIT 1
")->fetch_assoc();

// Pending payment
$pending_payment = $conn->query("
    SELECT * FROM payments WHERE student_id = $student_id AND status = 'pending'
    ORDER BY created_at DESC LIMIT 1
")->fetch_assoc();

// Completed payment
$completed_payment = $conn->query("
    SELECT * FROM payments WHERE student_id = $student_id AND status = 'completed'
    ORDER BY created_at DESC LIMIT 1
")->fetch_assoc();

$room_fee = 50000;

// Helper: map bed_spacing value to a friendly label
function bedSpacingLabel($spacing) {
    switch (strtolower(trim($spacing ?? ''))) {
        case 'single':    return 'Single (1 per room)';
        case 'double':    return 'Double (2 per room)';
        case 'triple':    return 'Triple (3 per room)';
        case 'quad':      return 'Quad (4 per room)';
        case 'bunk':      return 'Bunk Beds';
        case 'spacious':  return 'Spacious';
        case 'standard':  return 'Standard';
        case 'compact':   return 'Compact';
        default:          return $spacing ? ucfirst($spacing) : 'Standard';
    }
}

// Helper: icon for bed spacing
function bedSpacingIcon($spacing) {
    switch (strtolower(trim($spacing ?? ''))) {
        case 'single':    return 'fa-bed';
        case 'double':    return 'fa-bed';
        case 'triple':    return 'fa-bed';
        case 'quad':      return 'fa-bed';
        case 'bunk':      return 'fa-layer-group';
        case 'spacious':  return 'fa-arrows-alt';
        case 'compact':   return 'fa-compress';
        default:          return 'fa-bed';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Room & Payment - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://js.paystack.co/v1/inline.js"></script>
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

        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; animation: fadeInUp 0.6s ease; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .card-header { background: white; border-bottom: 2px solid #f0f2f5; padding: 18px 25px; border-radius: 16px 16px 0 0 !important; }
        .card-header .card-title { font-weight: 600; font-size: 1.1rem; color: #1a2a4a; margin: 0; }
        .card-header .card-title i { margin-right: 10px; color: #1a3a6b; }
        .card-body { padding: 25px; }
        .card-footer { background: white; border-top: 2px solid #f0f2f5; padding: 18px 25px; border-radius: 0 0 16px 16px !important; }

        .card-success .card-header { background: linear-gradient(135deg, #28a745, #20c997); }
        .card-success .card-header .card-title { color: white; }
        .card-success .card-header .card-title i { color: white; }
        .card-primary .card-header { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); }
        .card-primary .card-header .card-title { color: white; }
        .card-primary .card-header .card-title i { color: white; }
        .card-warning .card-header { background: linear-gradient(135deg, #fd7e14, #ffc107); }
        .card-warning .card-header .card-title { color: white; }
        .card-warning .card-header .card-title i { color: white; }

        .room-hero { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 40px 30px; border-radius: 20px; text-align: center; margin-bottom: 25px; box-shadow: 0 15px 40px rgba(40, 167, 69, 0.25); position: relative; overflow: hidden; }
        .room-hero::before { content: ''; position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%); pointer-events: none; }
        .room-hero .hero-icon { font-size: 5rem; margin-bottom: 10px; animation: float 3s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        .room-hero h2 { font-weight: 800; margin: 5px 0; position: relative; z-index: 1; }
        .room-hero p { opacity: 0.95; margin: 0; position: relative; z-index: 1; }

        /* ===== BED SPACING HERO BADGE ===== */
        .bed-spacing-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.35);
            padding: 8px 18px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.9rem;
            margin-top: 12px;
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            position: relative;
            z-index: 1;
        }
        .bed-spacing-hero-badge i { color: #ffd700; }

        .room-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 15px; }
        .room-detail-item { background: #f8f9fa; padding: 15px 20px; border-radius: 12px; border-left: 4px solid #28a745; position: relative; overflow: hidden; }
        .room-detail-item .label { font-size: 0.8rem; color: #6c757d; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px; }
        .room-detail-item .label i { color: #1a3a6b; font-size: 0.85rem; }
        .room-detail-item .value { font-size: 1.2rem; font-weight: 600; color: #1a2a4a; margin-top: 6px; }

        /* Highlighted bed spacing tile */
        .room-detail-item.bed-spacing-item { border-left-color: #ffd700; background: linear-gradient(135deg, #fffbea, #fff4cc); }
        .room-detail-item.bed-spacing-item .value { color: #856404; display: flex; align-items: center; gap: 8px; }
        .room-detail-item.bed-spacing-item .value i { color: #d4a017; font-size: 1.1rem; }

        .btn-success { background: linear-gradient(135deg, #28a745, #20c997); border: none; border-radius: 10px; padding: 14px 35px; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); color: white; }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4); color: white; }
        .btn-success i { margin-right: 10px; }
        .btn-outline-secondary { border: 2px solid #e9ecef; border-radius: 10px; padding: 12px 30px; font-weight: 600; transition: all 0.3s ease; }
        .btn-outline-secondary:hover { background: #f8f9fa; border-color: #ced4da; }
        .btn-primary { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); border: none; border-radius: 10px; padding: 12px 30px; font-weight: 600; color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(26,58,107,0.4); color: white; }

        .clearance-card { background: linear-gradient(135deg, #17a2b8, #138496); color: white; padding: 30px; border-radius: 20px; margin-bottom: 20px; box-shadow: 0 10px 35px rgba(23, 162, 184, 0.3); display: flex; align-items: center; gap: 25px; flex-wrap: wrap; position: relative; overflow: hidden; animation: fadeInUp 0.7s ease; }
        .clearance-card::before { content: '\f15c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: -20px; bottom: -40px; font-size: 10rem; opacity: 0.06; transform: rotate(-15deg); }
        .clearance-card .clearance-icon { width: 80px; height: 80px; background: rgba(255, 255, 255, 0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: white; flex-shrink: 0; border: 2px solid rgba(255,255,255,0.3); }
        .clearance-card .clearance-info { flex: 1; min-width: 220px; position: relative; z-index: 1; }
        .clearance-card .clearance-info h4 { font-weight: 700; font-size: 1.25rem; margin-bottom: 5px; }
        .clearance-card .clearance-info p { margin: 0; font-size: 0.9rem; opacity: 0.9; }
        .clearance-card .clearance-btn { background: white; color: #17a2b8; border: none; border-radius: 10px; padding: 14px 28px; font-weight: 700; font-size: 0.95rem; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s ease; position: relative; z-index: 1; flex-shrink: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
        .clearance-card .clearance-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.25); color: #0f6674; }

        .payment-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 15px; }
        .payment-item { background: #f8f9fa; padding: 15px 20px; border-radius: 12px; border-left: 4px solid #28a745; text-align: center; }
        .payment-item .label { font-size: 0.8rem; color: #6c757d; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        .payment-item .value { font-size: 1.2rem; font-weight: 700; color: #1a2a4a; margin-top: 4px; }
        .payment-item .value.amount { color: #28a745; font-size: 1.5rem; }

        .payment-status { display: inline-block; padding: 6px 16px; border-radius: 20px; font-weight: 600; font-size: 0.85rem; }
        .payment-status.paid { background: #d4edda; color: #155724; }
        .payment-status.pending { background: #fff3cd; color: #856404; }
        .payment-status.unpaid { background: #f8d7da; color: #721c24; }

        .alert { border-radius: 12px; border: none; padding: 16px 22px; animation: slideDown 0.5s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .alert-info { background: #e7f3ff; color: #084298; border-left: 5px solid #0d6efd; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 5px solid #ffc107; }

        .main-footer { background: white; padding: 15px 20px; border-top: 1px solid #e9ecef; text-align: center; font-size: 0.9rem; color: #6c757d; margin-left: 280px; }

        /* ===== BED SPACING INFO STRIP ===== */
        .bed-spacing-strip {
            display: flex;
            align-items: center;
            gap: 14px;
            background: linear-gradient(135deg, #fffbea, #fff4cc);
            border: 2px dashed #ffd700;
            border-radius: 14px;
            padding: 16px 20px;
            margin-top: 20px;
        }
        .bed-spacing-strip .strip-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #ffd700, #ffb700);
            color: #856404;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(255, 183, 0, 0.35);
        }
        .bed-spacing-strip .strip-text { flex: 1; }
        .bed-spacing-strip .strip-text h6 { margin: 0 0 2px; font-weight: 700; color: #856404; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .bed-spacing-strip .strip-text p { margin: 0; color: #6c757d; font-size: 0.9rem; font-weight: 500; }

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
            .room-hero { padding: 25px 18px; }
            .room-hero .hero-icon { font-size: 3.5rem; }
            .room-hero h2 { font-size: 1.3rem; }
            .bed-spacing-hero-badge { font-size: 0.8rem; padding: 6px 14px; }
            .room-details { grid-template-columns: 1fr; }
            .clearance-card { padding: 20px; flex-direction: column; text-align: center; }
            .clearance-card .clearance-btn { width: 100%; justify-content: center; }
            .btn-success, .btn-outline-secondary, .btn-primary { width: 100%; }
            .bed-spacing-strip { flex-direction: column; text-align: center; }
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
        <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
        <li class="nav-item"><a href="browse-rooms.php" class="nav-link"><i class="nav-icon fas fa-search"></i><p>Browse Rooms</p></a></li>
        <li class="nav-item"><a href="my-room.php" class="nav-link active"><i class="nav-icon fas fa-bed"></i><p>My Room</p></a></li>
        <li class="nav-item"><a href="profile.php" class="nav-link"><i class="nav-icon fas fa-user"></i><p>My Profile</p></a></li>
        <li class="nav-item"><a href="payment.php" class="nav-link"><i class="nav-icon fas fa-credit-card"></i><p>Payment History</p></a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
    </ul>
</aside>

<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fas fa-bed me-2"></i>My Room & Payment</h1>
    </section>

    <?php if ($flash_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($flash_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($allocation): ?>

        <?php
        // Prepare bed spacing values
        $bed_spacing_raw = $allocation['bed_spacing'] ?? '';
        $bed_spacing_label = bedSpacingLabel($bed_spacing_raw);
        $bed_spacing_icon = bedSpacingIcon($bed_spacing_raw);
        $bed_spacing_display = $bed_spacing_raw ? $bed_spacing_label : 'Standard';
        ?>

        <!-- CLEARANCE SLIP DOWNLOAD -->
        <?php if ($completed_payment): ?>
        <div class="clearance-card">
            <div class="clearance-icon"><i class="fas fa-file-alt"></i></div>
            <div class="clearance-info">
                <h4><i class="fas fa-check-circle me-2"></i>Accommodation Clearance Ready</h4>
                <p>Your official clearance slip has been generated. Download, print, and present it at the Hall of Residence.</p>
            </div>
            <a href="clearance_slip.php" class="clearance-btn" target="_blank">
                <i class="fas fa-download"></i> Download Slip
            </a>
        </div>
        <?php endif; ?>

        <!-- ROOM HERO -->
        <div class="room-hero">
            <div class="hero-icon"><i class="fas fa-door-open"></i></div>
            <h2>Room <?= htmlspecialchars($allocation['room_number']) ?></h2>
            <p>
                <i class="fas fa-building me-1"></i><?= htmlspecialchars($allocation['hostel_name'] ?? 'N/A') ?>
                <?php if (!empty($allocation['block'])): ?> • Block <?= htmlspecialchars($allocation['block']) ?><?php endif; ?>
            </p>

            <!-- BED SPACING BADGE IN HERO -->
            <div class="bed-spacing-hero-badge">
                <i class="fas <?= $bed_spacing_icon ?>"></i>
                Bed Spacing: <?= htmlspecialchars($bed_spacing_display) ?>
            </div>

            <p class="mt-3">
                <span class="badge bg-light text-dark">
                    <i class="fas fa-check-circle text-success me-1"></i>
                    Allocated on <?= date('d M Y', strtotime($allocation['allocation_date'])) ?>
                </span>
            </p>
        </div>

        <!-- ROOM DETAILS -->
        <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle"></i>Room Details</h3>
            </div>
            <div class="card-body">
                <div class="room-details">
                    <div class="room-detail-item">
                        <div class="label"><i class="fas fa-building"></i> Hostel / Hall</div>
                        <div class="value"><?= htmlspecialchars($allocation['hostel_name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="room-detail-item">
                        <div class="label"><i class="fas fa-door-open"></i> Room Number</div>
                        <div class="value"><?= htmlspecialchars($allocation['room_number']) ?></div>
                    </div>
                    <div class="room-detail-item">
                        <div class="label"><i class="fas fa-th-large"></i> Block</div>
                        <div class="value"><?= htmlspecialchars($allocation['block'] ?? 'N/A') ?></div>
                    </div>
                    <div class="room-detail-item bed-spacing-item">
                        <div class="label"><i class="fas <?= $bed_spacing_icon ?>"></i> Bed Spacing</div>
                        <div class="value">
                            <i class="fas <?= $bed_spacing_icon ?>"></i>
                            <?= htmlspecialchars($bed_spacing_display) ?>
                        </div>
                    </div>
                    <div class="room-detail-item">
                        <div class="label"><i class="fas fa-users"></i> Capacity</div>
                        <div class="value"><?= (int)$allocation['capacity'] ?> student(s)</div>
                    </div>
                    <div class="room-detail-item">
                        <div class="label"><i class="fas fa-user-check"></i> Occupants</div>
                        <div class="value"><?= (int)$allocation['current_occupants'] ?> / <?= (int)$allocation['capacity'] ?></div>
                    </div>
                    <div class="room-detail-item">
                        <div class="label"><i class="fas fa-money-bill-wave"></i> Fee Paid</div>
                        <div class="value">₦<?= number_format((float)$allocation['price'], 2) ?></div>
                    </div>
                    <?php if (!empty($allocation['gender'])): ?>
                    <div class="room-detail-item">
                        <div class="label"><i class="fas fa-<?= $allocation['gender'] === 'Male' ? 'mars' : ($allocation['gender'] === 'Female' ? 'venus' : 'genderless') ?>"></i> Gender</div>
                        <div class="value"><?= htmlspecialchars($allocation['gender']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- BED SPACING INFO STRIP -->
                <div class="bed-spacing-strip">
                    <div class="strip-icon"><i class="fas <?= $bed_spacing_icon ?>"></i></div>
                    <div class="strip-text">
                        <h6>Bed Spacing Information</h6>
                        <p>
                            This room is configured as <strong><?= htmlspecialchars($bed_spacing_display) ?></strong>.
                            <?php if (strtolower($bed_spacing_raw) === 'bunk'): ?>
                                Beds are arranged as bunk beds (stacked vertically) to maximize floor space.
                            <?php elseif (strtolower($bed_spacing_raw) === 'single'): ?>
                                You have a single bed in this room with maximum personal space.
                            <?php elseif (strtolower($bed_spacing_raw) === 'double'): ?>
                                Two beds are arranged with comfortable spacing between them.
                            <?php elseif (strtolower($bed_spacing_raw) === 'triple'): ?>
                                Three beds are arranged with adequate spacing for each occupant.
                            <?php elseif (strtolower($bed_spacing_raw) === 'quad'): ?>
                                Four beds are arranged in this room. Space is shared among occupants.
                            <?php elseif (strtolower($bed_spacing_raw) === 'spacious'): ?>
                                This room offers extra space between beds for comfort.
                            <?php elseif (strtolower($bed_spacing_raw) === 'compact'): ?>
                                This room has a compact layout with beds placed close together.
                            <?php else: ?>
                                Standard bed spacing arrangement with adequate room for movement.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="mt-4 text-center">
                    <span class="payment-status paid">
                        <i class="fas fa-check-circle me-1"></i> Room Allocation Active
                    </span>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
                    <a href="room-change.php" class="btn btn-primary"><i class="fas fa-exchange-alt me-2"></i>Request Room Change</a>
                    <?php if ($completed_payment): ?>
                        <a href="clearance_slip.php" class="btn btn-success" target="_blank">
                            <i class="fas fa-file-pdf me-2"></i>View Clearance Slip
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($pending_payment): ?>
        <div class="card card-warning">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clock"></i>Payment Pending</h3>
            </div>
            <div class="card-body text-center py-4">
                <div class="display-1 text-warning mb-3"><i class="fas fa-hourglass-half"></i></div>
                <h4>Payment is Being Processed</h4>
                <p class="lead">Your payment of <strong>₦<?= number_format((float)$pending_payment['amount'], 2) ?></strong> is currently pending verification.</p>
                <p class="text-muted">Reference: <?= htmlspecialchars($pending_payment['reference'] ?? 'N/A') ?></p>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Please wait while we verify your payment. This usually takes a few minutes.
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary mt-3">
                    <i class="fas fa-arrow-left me-2"></i>Return to Dashboard
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- NO ROOM — DIRECT TO BROWSE -->
        <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-search"></i>Browse Rooms to Get Allocated</h3>
            </div>
            <div class="card-body text-center py-5">
                <div class="display-1 text-success mb-3"><i class="fas fa-door-open"></i></div>
                <h4 class="mb-2">No Room Allocated Yet</h4>
                <p class="lead">Browse available rooms, choose your preferred one, and pay to get allocated instantly.</p>

                <a href="browse-rooms.php" class="btn btn-success btn-lg px-5 mt-3" style="border-radius:12px; padding:15px 40px; font-weight:700;">
                    <i class="fas fa-search me-2"></i> Browse Available Rooms
                </a>

                <p class="text-muted mt-4 mb-0" style="font-size:0.9rem;">
                    <i class="fas fa-info-circle me-1"></i>
                    You can filter by hostel, price, capacity, and bed spacing before selecting.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- PAYMENT HISTORY -->
    <?php 
    $payments = $conn->query("SELECT * FROM payments WHERE student_id = $student_id ORDER BY created_at DESC LIMIT 5");
    if ($payments && $payments->num_rows > 0): 
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-history me-2"></i>Payment History</h3>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th style="background:#f8f9fa;color:#1a2a4a;font-weight:600;font-size:0.85rem;text-transform:uppercase;border-bottom:2px solid #e9ecef;padding:12px 15px;">Date</th>
                        <th style="background:#f8f9fa;color:#1a2a4a;font-weight:600;font-size:0.85rem;text-transform:uppercase;border-bottom:2px solid #e9ecef;padding:12px 15px;">Reference</th>
                        <th style="background:#f8f9fa;color:#1a2a4a;font-weight:600;font-size:0.85rem;text-transform:uppercase;border-bottom:2px solid #e9ecef;padding:12px 15px;">Amount</th>
                        <th style="background:#f8f9fa;color:#1a2a4a;font-weight:600;font-size:0.85rem;text-transform:uppercase;border-bottom:2px solid #e9ecef;padding:12px 15px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($payment = $payments->fetch_assoc()): ?>
                    <tr>
                        <td style="padding:12px 15px;border-bottom:1px solid #f0f2f5;"><?= date('d M Y H:i', strtotime($payment['created_at'])) ?></td>
                        <td style="padding:12px 15px;border-bottom:1px solid #f0f2f5;"><code style="font-size:0.8rem;"><?= htmlspecialchars($payment['reference']) ?></code></td>
                        <td style="padding:12px 15px;border-bottom:1px solid #f0f2f5;"><strong>₦<?= number_format((float)$payment['amount'], 2) ?></strong></td>
                        <td style="padding:12px 15px;border-bottom:1px solid #f0f2f5;">
                            <span class="badge bg-<?= $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                <i class="fas fa-<?= $payment['status']==='completed'?'check-circle':($payment['status']==='pending'?'clock':'times-circle') ?> me-1"></i>
                                <?= ucfirst($payment['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
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