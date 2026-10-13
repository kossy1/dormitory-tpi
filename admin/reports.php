<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';

$report_type = $_GET['type'] ?? 'overview';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d');       // Today

// ========== OVERVIEW STATISTICS ==========
$total_students = $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0] ?? 0;
$active_students = $conn->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetch_row()[0] ?? 0;
$total_rooms = $conn->query("SELECT COUNT(*) FROM rooms")->fetch_row()[0] ?? 0;
$occupied_rooms = $conn->query("SELECT COUNT(*) FROM rooms WHERE status='occupied'")->fetch_row()[0] ?? 0;
$available_rooms = $total_rooms - $occupied_rooms;
$total_hostels = $conn->query("SELECT COUNT(*) FROM hostels")->fetch_row()[0] ?? 0;
$total_revenue = $conn->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'")->fetch_row()[0] ?? 0;
$occupancy_rate = $total_rooms > 0 ? round(($occupied_rooms / $total_rooms) * 100, 1) : 0;

// ========== PAYMENT STATS (by status) ==========
$payment_stats = [];
$res = $conn->query("SELECT status, COUNT(*) as count, COALESCE(SUM(amount),0) as total FROM payments GROUP BY status");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $payment_stats[$row['status']] = $row;
    }
}

// ========== MONTHLY REVENUE (last 12 months) ==========
$monthly_revenue = [];
$res = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count,
        COALESCE(SUM(amount), 0) as total
    FROM payments 
    WHERE status='completed' 
      AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $monthly_revenue[$row['month']] = $row;
    }
}

// ========== GENDER DISTRIBUTION ==========
$gender_stats = [];
$res = $conn->query("SELECT gender, COUNT(*) as count FROM students GROUP BY gender");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $gender_stats[$row['gender']] = $row['count'];
    }
}

// ========== LEVEL DISTRIBUTION ==========
$level_stats = [];
$res = $conn->query("SELECT level, COUNT(*) as count FROM students GROUP BY level ORDER BY level");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $level_stats[$row['level']] = $row['count'];
    }
}

// ========== ROOM OCCUPANCY BY HOSTEL ==========
$hostel_stats = [];
$res = $conn->query("
    SELECT h.id, h.name,
           COUNT(r.id) as total_rooms,
           SUM(CASE WHEN r.status='occupied' THEN 1 ELSE 0 END) as occupied,
           SUM(CASE WHEN r.status='available' THEN 1 ELSE 0 END) as available
    FROM hostels h
    LEFT JOIN rooms r ON r.hostel_id = h.id
    GROUP BY h.id, h.name
    ORDER BY h.name
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $hostel_stats[] = $row;
    }
}

// ========== TOP PAYING STUDENTS ==========
$top_students = [];
$res = $conn->query("
    SELECT s.id, s.full_name, s.matric_no,
           COUNT(p.id) as payment_count,
           COALESCE(SUM(p.amount), 0) as total_paid
    FROM students s
    JOIN payments p ON p.student_id = s.id
    WHERE p.status = 'completed'
    GROUP BY s.id, s.full_name, s.matric_no
    ORDER BY total_paid DESC
    LIMIT 10
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $top_students[] = $row;
    }
}

// ========== RECENT PAYMENTS (filtered by date) ==========
$recent_payments = [];
$stmt = $conn->prepare("
    SELECT p.*, s.full_name, s.matric_no
    FROM payments p
    JOIN students s ON p.student_id = s.id
    WHERE p.created_at BETWEEN ? AND ?
    ORDER BY p.created_at DESC
    LIMIT 20
");
$date_from_dt = $date_from . ' 00:00:00';
$date_to_dt = $date_to . ' 23:59:59';
$stmt->bind_param("ss", $date_from_dt, $date_to_dt);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $recent_payments[] = $row;
}

// ========== REQUEST STATS ==========
$complaints_total = $conn->query("SELECT COUNT(*) FROM complaints")->fetch_row()[0] ?? 0;
$complaints_pending = $conn->query("SELECT COUNT(*) FROM complaints WHERE status='pending'")->fetch_row()[0] ?? 0;
$maintenance_total = $conn->query("SELECT COUNT(*) FROM maintenance_requests")->fetch_row()[0] ?? 0;
$maintenance_pending = $conn->query("SELECT COUNT(*) FROM maintenance_requests WHERE status='pending'")->fetch_row()[0] ?? 0;
$refund_total = $conn->query("SELECT COUNT(*) FROM refund_requests")->fetch_row()[0] ?? 0;
$refund_pending = $conn->query("SELECT COUNT(*) FROM refund_requests WHERE status='pending'")->fetch_row()[0] ?? 0;
$room_change_total = $conn->query("SELECT COUNT(*) FROM room_change_requests")->fetch_row()[0] ?? 0;
$room_change_pending = $conn->query("SELECT COUNT(*) FROM room_change_requests WHERE status='pending'")->fetch_row()[0] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports & Analytics - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6f9;
            overflow-x: hidden;
        }

        .sidebar-toggle-btn {
            position: fixed; top: 12px; left: 12px; z-index: 1050;
            background: #1a3a6b; color: white; border: none;
            border-radius: 10px; padding: 12px 16px; display: none;
            box-shadow: 0 4px 20px rgba(26,58,107,0.3);
        }
        .sidebar-toggle-btn:hover { background: #0d1b3e; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1039; }
        .sidebar-overlay.active { display: block; }

        .main-sidebar {
            position: fixed; top: 0; left: 0; height: 100vh; width: 280px;
            background: linear-gradient(180deg, #1a3a6b 0%, #0d1b3e 100%);
            z-index: 1040; overflow-y: auto; transition: transform 0.3s ease;
            transform: translateX(-100%);
        }
        .main-sidebar.open { transform: translateX(0); }
        .sidebar-brand {
            padding: 20px 18px 15px; border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex; align-items: center; gap: 12px;
        }
        .sidebar-brand img { width: 45px; height: 45px; background: white; padding: 5px; border-radius: 10px; }
        .sidebar-brand .brand-text { color: white; font-weight: 700; font-size: 1.1rem; line-height: 1.2; }
        .sidebar-brand .brand-text small { display: block; font-size: 0.7rem; opacity: 0.7; font-weight: 300; }
        .sidebar-close-btn {
            display: none; position: absolute; top: 12px; right: 12px;
            background: rgba(255,255,255,0.1); color: white; border: none;
            border-radius: 50%; width: 36px; height: 36px;
        }
        .user-panel {
            padding: 18px 20px !important; border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex !important; align-items: center; gap: 12px;
        }
        .user-panel img { width: 42px; height: 42px; border-radius: 50%; }
        .user-panel a { color: white; font-weight: 600; text-decoration: none; }
        .user-panel small { color: rgba(255,255,255,0.6); display: block; font-size: 0.75rem; }

        .nav-sidebar { padding: 8px 0; list-style: none; }
        .nav-sidebar .nav-item { margin: 2px 8px; }
        .nav-sidebar .nav-link {
            color: rgba(255,255,255,0.75); padding: 10px 16px; border-radius: 10px;
            display: flex; align-items: center; gap: 12px; text-decoration: none;
            transition: all 0.3s ease;
        }
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

        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .card-header { background: white; border-bottom: 2px solid #f0f2f5; padding: 18px 25px; border-radius: 16px 16px 0 0 !important; }
        .card-body { padding: 25px; }
        .card-title { font-weight: 700; font-size: 1.1rem; color: #1a2a4a; }

        .stat-card {
            border: none; border-radius: 16px; padding: 22px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06); position: relative;
            overflow: hidden; transition: all 0.3s ease;
            animation: fadeInUp 0.5s ease;
            height: 100%;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(0,0,0,0.12); }
        .stat-card .stat-icon {
            width: 55px; height: 55px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; color: white; margin-bottom: 12px;
        }
        .stat-card .stat-value { font-size: 1.9rem; font-weight: 800; margin: 0; color: #1a2a4a; line-height: 1.2; }
        .stat-card .stat-label { color: #6c757d; font-size: 0.9rem; margin: 5px 0 0; font-weight: 500; }
        .stat-card .stat-bg-icon {
            position: absolute; right: -10px; bottom: -10px;
            font-size: 5rem; opacity: 0.05; pointer-events: none;
        }
        .stat-card.bg-primary .stat-icon { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); }
        .stat-card.bg-success .stat-icon { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-card.bg-warning .stat-icon { background: linear-gradient(135deg, #ffc107, #fd7e14); }
        .stat-card.bg-info .stat-icon { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
        .stat-card.bg-danger .stat-icon { background: linear-gradient(135deg, #dc3545, #e74c3c); }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .filter-bar {
            background: white; border-radius: 16px; padding: 18px 22px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px;
            display: flex; gap: 15px; flex-wrap: wrap; align-items: end;
        }
        .filter-bar .form-group { flex: 1; min-width: 180px; }
        .filter-bar label { font-size: 0.82rem; font-weight: 600; color: #1a2a4a; margin-bottom: 5px; display: block; }
        .filter-bar .form-control { border: 2px solid #e9ecef; border-radius: 10px; padding: 9px 14px; font-size: 0.9rem; }
        .filter-bar .form-control:focus { border-color: #1a3a6b; box-shadow: 0 0 0 4px rgba(26,58,107,0.12); }

        .btn-primary {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            border: none; border-radius: 10px; padding: 10px 22px;
            font-weight: 600; color: white; transition: all 0.3s ease;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(26,58,107,0.4); color: white; }
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none; border-radius: 10px; padding: 10px 22px;
            font-weight: 600; color: white;
        }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(40,167,69,0.4); color: white; }
        .btn-outline-secondary { border: 2px solid #e9ecef; border-radius: 10px; padding: 10px 20px; font-weight: 600; }

        .table { margin-bottom: 0; }
        .table thead th {
            background: #f8f9fa; color: #1a2a4a; font-weight: 600;
            font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef; padding: 12px 15px;
        }
        .table tbody td { padding: 12px 15px; border-bottom: 1px solid #f0f2f5; font-size: 0.9rem; vertical-align: middle; }
        .table tbody tr:hover { background: #f8f9fa; }

        .badge { padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 0.72rem; }
        .badge.bg-success { background: #28a745 !important; }
        .badge.bg-warning { background: #ffc107 !important; color: #333; }
        .badge.bg-danger { background: #dc3545 !important; }
        .badge.bg-info { background: #17a2b8 !important; }
        .badge.bg-secondary { background: #6c757d !important; }

        .main-footer {
            background: white; padding: 15px 20px; border-top: 1px solid #e9ecef;
            text-align: center; font-size: 0.9rem; color: #6c757d; margin-left: 280px;
        }

        .progress { height: 8px; border-radius: 4px; background: #e9ecef; }
        .progress-bar { border-radius: 4px; }

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
            .stat-card { padding: 18px; }
            .stat-card .stat-value { font-size: 1.5rem; }
            .stat-card .stat-icon { width: 45px; height: 45px; font-size: 1.3rem; }
            .card-header, .card-body { padding: 15px 18px; }
            .filter-bar { padding: 15px; }
            .btn-primary, .btn-success { width: 100%; }
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
        <li class="nav-item"><a href="reports.php" class="nav-link active"><i class="nav-icon fas fa-chart-line"></i><p>Reports</p></a></li>
        <li class="nav-item"><a href="logs.php" class="nav-link"><i class="nav-icon fas fa-history"></i><p>Activity Logs</p></a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
    </ul>
</aside>

<div class="content-wrapper">
    <section class="content-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <h1><i class="fas fa-chart-line me-2"></i>Reports &amp; Analytics</h1>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-2"></i>Print Report
            </button>
        </div>
    </section>

    <!-- FILTER BAR -->
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label><i class="fas fa-calendar-alt me-1"></i>From Date</label>
            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="form-group">
            <label><i class="fas fa-calendar-alt me-1"></i>To Date</label>
            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <div class="form-group" style="flex: 0 0 auto;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-2"></i>Apply Filter</button>
        </div>
        <div class="form-group" style="flex: 0 0 auto;">
            <a href="reports.php" class="btn btn-outline-secondary"><i class="fas fa-redo me-2"></i>Reset</a>
        </div>
    </form>

    <!-- KEY STATS -->
    <div class="row g-3 mb-3">
        <div class="col-lg-3 col-md-6 col-6">
            <div class="stat-card bg-primary">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <h3 class="stat-value"><?= number_format($total_students) ?></h3>
                <p class="stat-label">Total Students</p>
                <div class="stat-bg-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-6">
            <div class="stat-card bg-success">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <h3 class="stat-value"><?= number_format($active_students) ?></h3>
                <p class="stat-label">Active Students</p>
                <div class="stat-bg-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-6">
            <div class="stat-card bg-info">
                <div class="stat-icon"><i class="fas fa-building"></i></div>
                <h3 class="stat-value"><?= number_format($total_hostels) ?></h3>
                <p class="stat-label">Total Hostels</p>
                <div class="stat-bg-icon"><i class="fas fa-building"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-6">
            <div class="stat-card bg-warning">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <h3 class="stat-value" style="font-size:1.3rem;">₦<?= number_format((float)$total_revenue, 0) ?></h3>
                <p class="stat-label">Total Revenue</p>
                <div class="stat-bg-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW 1: MONTHLY REVENUE + GENDER -->
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Revenue (Last 12 Months)</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-venus-mars me-2"></i>Gender Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="genderChart" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW 2: PAYMENT STATUS + LEVEL -->
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-credit-card me-2"></i>Payment Status Breakdown</h5>
                </div>
                <div class="card-body">
                    <canvas id="paymentChart" height="180"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-graduation-cap me-2"></i>Students by Level</h5>
                </div>
                <div class="card-body">
                    <canvas id="levelChart" height="180"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- OCCUPANCY BY HOSTEL -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-building me-2"></i>Room Occupancy by Hostel</h5>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Hostel</th>
                        <th>Total Rooms</th>
                        <th>Occupied</th>
                        <th>Available</th>
                        <th style="width: 250px;">Occupancy Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($hostel_stats)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No hostels found</td></tr>
                    <?php else: ?>
                        <?php foreach ($hostel_stats as $h): 
                            $rate = $h['total_rooms'] > 0 ? round(($h['occupied'] / $h['total_rooms']) * 100, 1) : 0;
                            $color = $rate > 80 ? 'bg-danger' : ($rate > 50 ? 'bg-warning' : 'bg-success');
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($h['name']) ?></strong></td>
                                <td><?= $h['total_rooms'] ?></td>
                                <td><span class="badge bg-danger"><?= $h['occupied'] ?></span></td>
                                <td><span class="badge bg-success"><?= $h['available'] ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1">
                                            <div class="progress-bar <?= $color ?>" style="width: <?= $rate ?>%"></div>
                                        </div>
                                        <strong style="min-width: 45px;"><?= $rate ?>%</strong>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TOP PAYING STUDENTS -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-trophy me-2"></i>Top 10 Paying Students</h5>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Matric No</th>
                        <th>Payments</th>
                        <th>Total Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_students)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No payment data available</td></tr>
                    <?php else: ?>
                        <?php foreach ($top_students as $i => $s): ?>
                            <tr>
                                <td><strong><?= $i + 1 ?></strong></td>
                                <td><?= htmlspecialchars($s['full_name']) ?></td>
                                <td><?= htmlspecialchars($s['matric_no']) ?></td>
                                <td><span class="badge bg-info"><?= $s['payment_count'] ?></span></td>
                                <td><strong style="color: #28a745;">₦<?= number_format((float)$s['total_paid'], 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- REQUEST STATS -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-clipboard-list me-2"></i>Requests Overview</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <div class="text-center p-3" style="background: #f0f4fa; border-radius: 12px;">
                        <i class="fas fa-comments fa-2x" style="color:#0d6efd;"></i>
                        <h4 class="mt-2 mb-0"><?= $complaints_total ?></h4>
                        <small class="text-muted">Complaints</small>
                        <div class="mt-2"><span class="badge bg-warning"><?= $complaints_pending ?> pending</span></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-center p-3" style="background: #fff3cd; border-radius: 12px;">
                        <i class="fas fa-wrench fa-2x" style="color:#fd7e14;"></i>
                        <h4 class="mt-2 mb-0"><?= $maintenance_total ?></h4>
                        <small class="text-muted">Maintenance</small>
                        <div class="mt-2"><span class="badge bg-warning"><?= $maintenance_pending ?> pending</span></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-center p-3" style="background: #f8d7da; border-radius: 12px;">
                        <i class="fas fa-money-bill-wave fa-2x" style="color:#dc3545;"></i>
                        <h4 class="mt-2 mb-0"><?= $refund_total ?></h4>
                        <small class="text-muted">Refunds</small>
                        <div class="mt-2"><span class="badge bg-warning"><?= $refund_pending ?> pending</span></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-center p-3" style="background: #d1ecf1; border-radius: 12px;">
                        <i class="fas fa-exchange-alt fa-2x" style="color:#17a2b8;"></i>
                        <h4 class="mt-2 mb-0"><?= $room_change_total ?></h4>
                        <small class="text-muted">Room Changes</small>
                        <div class="mt-2"><span class="badge bg-warning"><?= $room_change_pending ?> pending</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RECENT PAYMENTS (filtered) -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-history me-2"></i>Payments from <?= date('d M Y', strtotime($date_from)) ?> to <?= date('d M Y', strtotime($date_to)) ?>
            </h5>
            <span class="badge bg-secondary"><?= count($recent_payments) ?> records</span>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Matric</th>
                        <th>Reference</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_payments)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No payments in this period</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_payments as $p): ?>
                            <tr>
                                <td><?= date('d M Y H:i', strtotime($p['created_at'])) ?></td>
                                <td><?= htmlspecialchars($p['full_name']) ?></td>
                                <td><?= htmlspecialchars($p['matric_no']) ?></td>
                                <td><code style="font-size:0.75rem;"><?= htmlspecialchars($p['reference']) ?></code></td>
                                <td><strong>₦<?= number_format((float)$p['amount'], 2) ?></strong></td>
                                <td>
                                    <span class="badge bg-<?= $p['status'] === 'completed' ? 'success' : ($p['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (toggleBtn) toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    });
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // ========== MONTHLY REVENUE CHART ==========
    const revenueLabels = <?= json_encode(array_map(function($m) {
        return date('M Y', strtotime($m . '-01'));
    }, array_keys($monthly_revenue))) ?>;
    const revenueData = <?= json_encode(array_map(function($m) {
        return (float)$m['total'];
    }, array_values($monthly_revenue))) ?>;

    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: revenueLabels.length ? revenueLabels : ['No Data'],
            datasets: [{
                label: 'Revenue (₦)',
                data: revenueData.length ? revenueData : [0],
                borderColor: '#1a3a6b',
                backgroundColor: 'rgba(26, 58, 107, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#1a3a6b',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return '₦' + ctx.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(v) { return '₦' + v.toLocaleString(); }
                    }
                }
            }
        }
    });

    // ========== GENDER CHART ==========
    new Chart(document.getElementById('genderChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($gender_stats)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($gender_stats)) ?>,
                backgroundColor: ['#0d6efd', '#e83e8c', '#6c757d'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true } }
            }
        }
    });

    // ========== PAYMENT STATUS CHART ==========
    const paymentLabels = <?= json_encode(array_map('ucfirst', array_keys($payment_stats))) ?>;
    const paymentData = <?= json_encode(array_map(function($p) { return (int)$p['count']; }, array_values($payment_stats))) ?>;
    const paymentColors = ['#28a745', '#ffc107', '#dc3545'];

    new Chart(document.getElementById('paymentChart'), {
        type: 'bar',
        data: {
            labels: paymentLabels.length ? paymentLabels : ['No Data'],
            datasets: [{
                label: 'Payments',
                data: paymentData.length ? paymentData : [0],
                backgroundColor: paymentColors,
                borderRadius: 8,
                barThickness: 50
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // ========== LEVEL CHART ==========
    const levelLabels = <?= json_encode(array_map(function($l) {
        $map = ['100' => 'ND I', '200' => 'ND II', '300' => 'HND I', '400' => 'HND II'];
        return $map[$l] ?? $l;
    }, array_keys($level_stats))) ?>;
    const levelData = <?= json_encode(array_values($level_stats)) ?>;

    new Chart(document.getElementById('levelChart'), {
        type: 'bar',
        data: {
            labels: levelLabels.length ? levelLabels : ['No Data'],
            datasets: [{
                label: 'Students',
                data: levelData.length ? levelData : [0],
                backgroundColor: ['#0d6efd', '#28a745', '#fd7e14', '#6f42c1'],
                borderRadius: 8,
                barThickness: 50
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
});
</script>
</body>
</html>