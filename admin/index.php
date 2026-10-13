<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';
include 'log_action.php';

$admin_name = $_SESSION['admin_name'] ?? 'Administrator';

// ===== STATS =====
$total_students = (int)($conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'] ?? 0);
$total_rooms    = (int)($conn->query("SELECT COUNT(*) c FROM rooms")->fetch_assoc()['c'] ?? 0);
$total_hostels  = (int)($conn->query("SELECT COUNT(*) c FROM hostels WHERE status='active'")->fetch_assoc()['c'] ?? 0);
$allocated      = (int)($conn->query("SELECT COUNT(*) c FROM allocations WHERE status='active'")->fetch_assoc()['c'] ?? 0);
$total_revenue  = (float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed'")->fetch_assoc()['s'] ?? 0);
$pending        = (int)($conn->query("SELECT COUNT(*) c FROM payments WHERE status='pending'")->fetch_assoc()['c'] ?? 0);

$recent_logs = $conn->query("
    SELECT action, description, admin_name, created_at 
    FROM activity_logs 
    ORDER BY created_at DESC 
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$recent_allocs = $conn->query("
    SELECT a.allocation_date, s.full_name, s.matric_no, r.room_number, h.name AS hostel_name
    FROM allocations a
    JOIN students s ON a.student_id = s.id
    JOIN rooms r ON a.room_id = r.id
    LEFT JOIN hostels h ON r.hostel_id = h.id
    WHERE a.status = 'active'
    ORDER BY a.allocation_date DESC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <style>
        /* ========== BASE ========== */
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family:'Inter',sans-serif; background:#f4f6f9; overflow-x: hidden; }

        /* ========== ANIMATIONS ========== */
        @keyframes fadeInUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        @keyframes bounceIn { 0% { opacity:0; transform:scale(0.9); } 60% { opacity:1; transform:scale(1.02); } 100% { transform:scale(1); } }
        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }

        /* ========== SIDEBAR ========== */
        .sidebar-toggle-btn {
            position:fixed; top:12px; left:12px; z-index:1050;
            background:#1a3a6b; color:white; border:none; border-radius:10px;
            padding:12px 16px; display:none;
            box-shadow: 0 4px 20px rgba(26,58,107,0.3);
            transition: all 0.3s ease;
        }
        .sidebar-toggle-btn:hover { background:#0d1b3e; transform:scale(1.05); }
        .sidebar-toggle-btn:active { transform:scale(0.95); }
        .sidebar-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.5); z-index:1039;
            animation: fadeIn 0.3s ease;
        }
        .sidebar-overlay.active { display:block; }

        .main-sidebar {
            position:fixed; top:0; left:0; height:100vh; width:280px;
            background:linear-gradient(180deg,#1a3a6b 0%,#0d1b3e 100%);
            z-index:1040; overflow-y:auto;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform:translateX(-100%);
            box-shadow: 2px 0 30px rgba(0,0,0,0.15);
        }
        .main-sidebar.open { transform:translateX(0); }
        .main-sidebar::-webkit-scrollbar { width:4px; }
        .main-sidebar::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.2); border-radius:4px; }

        .sidebar-brand {
            padding:20px 18px 15px; border-bottom:1px solid rgba(255,255,255,0.08);
            display:flex; align-items:center; gap:12px;
        }
        .sidebar-brand img { width:45px; height:45px; background:white; padding:5px; border-radius:10px; }
        .sidebar-brand .brand-text { color:white; font-weight:700; font-size:1.1rem; line-height:1.2; }
        .sidebar-brand .brand-text small { display:block; font-size:0.7rem; opacity:0.7; font-weight:300; }
        .sidebar-close-btn {
            display:none; position:absolute; top:12px; right:12px;
            background:rgba(255,255,255,0.1); color:white; border:none;
            border-radius:50%; width:36px; height:36px;
            transition: all 0.3s ease;
        }
        .sidebar-close-btn:hover { background:rgba(255,255,255,0.2); transform:rotate(90deg); }

        .user-panel {
            padding:18px 20px !important; border-bottom:1px solid rgba(255,255,255,0.08);
            display:flex !important; align-items:center; gap:12px;
        }
        .user-panel img { width:42px; height:42px; border-radius:50%; border:2px solid rgba(255,255,255,0.2); }
        .user-panel a { color:white; font-weight:600; text-decoration:none; font-size:0.95rem; transition:color 0.3s; }
        .user-panel a:hover { color:#ffd700; }
        .user-panel small { color:rgba(255,255,255,0.6); display:block; font-size:0.75rem; }

        .nav-sidebar { padding:8px 0; list-style:none; }
        .nav-sidebar .nav-item { margin:2px 8px; }
        .nav-sidebar .nav-link {
            color:rgba(255,255,255,0.75); padding:10px 16px; border-radius:10px;
            display:flex; align-items:center; gap:12px; text-decoration:none;
            transition: all 0.25s ease;
        }
        .nav-sidebar .nav-link:hover { color:white; background:rgba(255,255,255,0.1); transform:translateX(4px); }
        .nav-sidebar .nav-link.active { color:white; background:rgba(255,215,0,0.15); border-left:3px solid #ffd700; }
        .nav-sidebar .nav-icon { width:22px; text-align:center; color:rgba(255,255,255,0.6); }
        .nav-sidebar .nav-link.active .nav-icon { color:#ffd700; }
        .nav-treeview { padding-left:15px; max-height:0; overflow:hidden; transition:max-height 0.3s ease; }
        .nav-treeview.show { max-height:500px; }
        .nav-treeview .nav-link { padding:8px 16px 8px 35px; font-size:0.85rem; color:rgba(255,255,255,0.6); }

        /* ========== CONTENT ========== */
        .content-wrapper { min-height:100vh; padding:20px; transition:margin-left 0.3s ease; }
        .content-header {
            padding-bottom:15px; border-bottom:2px solid #e9ecef; margin-bottom:20px;
            animation: fadeInUp 0.5s ease both;
        }
        .content-header h1 { font-weight:700; font-size:1.8rem; color:#1a2a4a; }
        .content-header h1 i { color:#1a3a6b; }

        /* ========== STAT CARDS ========== */
        .stat-card {
            background:white; border-radius:16px; padding:22px;
            box-shadow:0 4px 20px rgba(0,0,0,0.06);
            display:flex; align-items:center; gap:18px; height:100%;
            transition: all 0.3s ease;
            animation: bounceIn 0.6s ease both;
        }
        .stat-card:hover { transform:translateY(-5px); box-shadow:0 12px 35px rgba(0,0,0,0.12); }
        .stat-card .stat-icon {
            width:58px; height:58px; border-radius:14px;
            display:flex; align-items:center; justify-content:center;
            font-size:1.5rem; color:white; flex-shrink:0;
        }
        .stat-card .stat-info h3 { font-weight:800; font-size:1.6rem; color:#1a2a4a; margin:0; }
        .stat-card .stat-info p { margin:4px 0 0; color:#6c757d; font-size:0.85rem; }

        /* Stagger the stat cards */
        .row > [class*="col-"]:nth-child(1) .stat-card { animation-delay: 0.05s; }
        .row > [class*="col-"]:nth-child(2) .stat-card { animation-delay: 0.10s; }
        .row > [class*="col-"]:nth-child(3) .stat-card { animation-delay: 0.15s; }
        .row > [class*="col-"]:nth-child(4) .stat-card { animation-delay: 0.20s; }
        .row > [class*="col-"]:nth-child(5) .stat-card { animation-delay: 0.25s; }
        .row > [class*="col-"]:nth-child(6) .stat-card { animation-delay: 0.30s; }

        .bg-gradient-blue   { background:linear-gradient(135deg,#1a3a6b,#2c5aa0) !important; }
        .bg-gradient-green  { background:linear-gradient(135deg,#28a745,#20c997) !important; }
        .bg-gradient-orange { background:linear-gradient(135deg,#fd7e14,#ffb700) !important; }
        .bg-gradient-red    { background:linear-gradient(135deg,#dc3545,#c82333) !important; }
        .bg-gradient-purple { background:linear-gradient(135deg,#6f42c1,#8b5cf6) !important; }
        .bg-gradient-teal   { background:linear-gradient(135deg,#17a2b8,#20c997) !important; }
        .bg-gradient-pink   { background:linear-gradient(135deg,#e83e8c,#ff6fa8) !important; }

        /* ========== CARDS ========== */
        .card {
            border:none; border-radius:16px;
            box-shadow:0 4px 20px rgba(0,0,0,0.06);
            margin-bottom:20px;
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease both;
        }
        .card:hover { transform:translateY(-3px); box-shadow:0 12px 35px rgba(0,0,0,0.1); }
        .card-header {
            background:white; border-bottom:2px solid #f0f2f5;
            padding:18px 25px; border-radius:16px 16px 0 0 !important;
            display:flex; justify-content:space-between; align-items:center;
            flex-wrap: wrap; gap: 10px;
        }
        .card-body { padding:0; }

        .table { margin:0; }
        .table thead th {
            background:#f8f9fa; color:#1a2a4a; font-weight:700;
            font-size:0.78rem; text-transform:uppercase;
            border-bottom:2px solid #e9ecef; padding:14px 20px;
            white-space: nowrap;
        }
        .table tbody td { padding:14px 20px; border-bottom:1px solid #f0f2f5; font-size:0.9rem; }
        .table tbody tr { animation: fadeIn 0.4s ease both; transition: background 0.2s; }
        .table tbody tr:hover { background: #f0f4fa !important; }

        .activity-item {
            padding:12px 20px; border-bottom:1px solid #f0f2f5;
            display:flex; gap:12px; align-items:center;
            animation: fadeIn 0.4s ease both;
            transition: background 0.2s ease;
        }
        .activity-item:hover { background: #f8f9fa; }
        .activity-item:last-child { border-bottom: none; }

        /* ========== FOOTER ========== */
        .main-footer {
            background:white; padding:15px 20px; border-top:1px solid #e9ecef;
            text-align:center; font-size:0.9rem; color:#6c757d;
            margin-left:280px; transition: margin-left 0.3s ease;
        }

        /* ========== RESPONSIVE ========== */
        @media (min-width:992px) {
            .main-sidebar { transform:translateX(0) !important; }
            .sidebar-toggle-btn, .sidebar-overlay { display:none !important; }
            .content-wrapper { margin-left:280px; }
        }
        @media (max-width:991.98px) {
            .sidebar-toggle-btn { display:block; }
            .sidebar-close-btn { display:block; }
            .main-sidebar.open { transform:translateX(0); }
            .content-wrapper { margin-left:0; padding-top:70px; }
            .main-footer { margin-left:0; }
            .content-header h1 { font-size: 1.4rem; }
        }
        @media (max-width:767.98px) {
            .card-header { padding: 12px 15px; }
            .card-header h3 { font-size: 0.95rem; }
            .table thead th { font-size: 0.7rem; padding: 8px 10px; }
            .table tbody td { font-size: 0.8rem; padding: 8px 10px; }
            .content-header h1 { font-size: 1.2rem; }
            .stat-card { padding: 16px !important; gap: 12px !important; }
            .stat-card .stat-icon { width: 48px !important; height: 48px !important; font-size: 1.25rem !important; }
            .stat-card .stat-info h3 { font-size: 1.3rem !important; }
            .stat-card .stat-info p { font-size: 0.78rem !important; }
        }
        @media (max-width:575.98px) {
            .content-wrapper { padding: 12px; padding-top: 68px; }
            .content-header h1 { font-size: 1.1rem; }
            .stat-card { padding: 12px !important; }
            .stat-card .stat-icon { width: 42px !important; height: 42px !important; font-size: 1rem !important; }
            .stat-card .stat-info h3 { font-size: 1rem !important; }
            .stat-card .stat-info p { font-size: 0.7rem !important; }
            .main-sidebar { width: 260px; max-width: 85%; }
            .activity-item { padding: 10px 12px; }
        }

        /* Prevent iOS zoom */
        input, select, textarea { font-size: 16px !important; }

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>

<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="main-sidebar" id="mainSidebar">
    <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close menu"><i class="fas fa-times"></i></button>
    <div class="sidebar-brand">
        <img src="../assets/images/logo.png" onerror="this.src='https://via.placeholder.com/45x45/ffffff/1a3a6b?text=PI'">
        <div class="brand-text">Dormitory MS<small>Admin Panel</small></div>
    </div>
    <div class="user-panel">
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin_name) ?>&background=ffd700&color=1a3a6b&size=42">
        <div><a href="#"><?= htmlspecialchars($admin_name) ?></a><small>Admin</small></div>
    </div>
    <ul class="nav nav-pills nav-sidebar flex-column">
        <li class="nav-item"><a href="index.php" class="nav-link active"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
        <li class="nav-item"><a href="students.php" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Students</p></a></li>
        <li class="nav-item"><a href="bulk-students.php" class="nav-link"><i class="nav-icon fas fa-file-upload"></i><p>Bulk Upload</p></a></li>
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
        <h1><i class="fas fa-tachometer-alt me-2"></i>Welcome, <?= htmlspecialchars($admin_name) ?>!</h1>
        <p class="text-muted mt-1 mb-0">Here's an overview of your dormitory system.</p>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-lg-4 col-md-6 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-blue"><i class="fas fa-users"></i></div>
                <div class="stat-info"><h3><?= number_format($total_students) ?></h3><p>Total Students</p></div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-green"><i class="fas fa-user-check"></i></div>
                <div class="stat-info"><h3><?= number_format($allocated) ?></h3><p>Allocated</p></div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 col-12">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-purple"><i class="fas fa-hotel"></i></div>
                <div class="stat-info"><h3><?= number_format($total_hostels) ?></h3><p>Active Hostels</p></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-4 col-md-6 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-teal"><i class="fas fa-bed"></i></div>
                <div class="stat-info"><h3><?= number_format($total_rooms) ?></h3><p>Total Rooms</p></div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-green"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-info">
                    <h3 style="font-size:1.3rem;">₦<?= number_format($total_revenue, 0) ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 col-12">
            <div class="stat-card">
                <div class="stat-icon bg-gradient-orange"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-info"><h3><?= number_format($pending) ?></h3><p>Pending Payments</p></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="mb-0" style="font-weight:700;color:#1a2a4a;"><i class="fas fa-history me-2" style="color:#1a3a6b;"></i>Recent Activity</h3>
                    <a href="logs.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_logs)): ?>
                        <?php foreach ($recent_logs as $log): ?>
                            <div class="activity-item">
                                <div style="width:36px;height:36px;border-radius:50%;background:#f0f4fa;color:#1a3a6b;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:600;color:#1a2a4a;font-size:0.88rem;"><?= htmlspecialchars($log['action']) ?></div>
                                    <div style="font-size:0.78rem;color:#6c757d;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars(mb_substr($log['description'] ?? '', 0, 60)) ?><?= strlen($log['description'] ?? '') > 60 ? '...' : '' ?></div>
                                </div>
                                <div style="font-size:0.72rem;color:#adb5bd;text-align:right;white-space:nowrap;">
                                    <?= date('d M H:i', strtotime($log['created_at'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-4 mb-0">No recent activity.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="mb-0" style="font-weight:700;color:#1a2a4a;"><i class="fas fa-clipboard-check me-2" style="color:#1a3a6b;"></i>Recent Allocations</h3>
                    <a href="allocations.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recent_allocs)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>Student</th><th>Room</th><th>Date</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recent_allocs as $a): ?>
                                    <tr>
                                        <td>
                                            <strong style="color:#1a2a4a;"><?= htmlspecialchars($a['full_name']) ?></strong>
                                            <div style="font-size:0.72rem;color:#6c757d;"><?= htmlspecialchars($a['matric_no']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= htmlspecialchars($a['room_number']) ?></span>
                                            <div style="font-size:0.72rem;color:#6c757d;"><?= htmlspecialchars($a['hostel_name'] ?? '') ?></div>
                                        </td>
                                        <td style="font-size:0.8rem;color:#6c757d;"><?= date('d M Y', strtotime($a['allocation_date'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-4 mb-0">No recent allocations.</p>
                    <?php endif; ?>
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
    const body = document.body;

    function openSidebar() {
        sidebar.classList.add('open'); overlay.classList.add('active');
        body.style.overflow = 'hidden';
        if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-times"></i>';
    }
    function closeSidebar() {
        sidebar.classList.remove('open'); overlay.classList.remove('active');
        body.style.overflow = '';
        if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
    }

    toggleBtn?.addEventListener('click', () => {
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    closeBtn?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });

    // Auto-close sidebar on link click (mobile only)
    if (window.innerWidth <= 991.98) {
        document.querySelectorAll('.nav-sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => setTimeout(closeSidebar, 200));
        });
    }
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